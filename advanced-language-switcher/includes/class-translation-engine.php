<?php
/**
 * Translation engine: provider registry plus the safety layer around them.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

use ALS\Providers\Deepl_Provider;
use ALS\Providers\Google_Provider;
use ALS\Providers\Manual_Provider;
use ALS\Providers\Openai_Provider;
use ALS\Providers\Translation_Provider_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Chooses a provider, masks placeholders, applies the glossary and splits
 * work into provider-sized batches.
 *
 * Adding a new engine means registering one class through the
 * `als_translation_providers` filter; nothing else in the plugin changes.
 */
class Translation_Engine {

	/**
	 * Registered providers keyed by slug.
	 *
	 * @var array<string,Translation_Provider_Interface>|null
	 */
	protected ?array $providers = null;

	/**
	 * Glossary service.
	 *
	 * @var Glossary
	 */
	protected Glossary $glossary;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Glossary $glossary Glossary service.
	 * @param Logger   $logger   Logger service.
	 */
	public function __construct( Glossary $glossary, Logger $logger ) {
		$this->glossary = $glossary;
		$this->logger   = $logger;
	}

	/**
	 * Returns every registered provider.
	 *
	 * @return array<string,Translation_Provider_Interface>
	 */
	public function get_providers(): array {
		if ( null !== $this->providers ) {
			return $this->providers;
		}

		$providers = array(
			'manual' => new Manual_Provider(),
			'google' => new Google_Provider(),
			'deepl'  => new Deepl_Provider(),
			'openai' => new Openai_Provider(),
		);

		/**
		 * Filters the registered translation providers.
		 *
		 * Third party engines implement Translation_Provider_Interface and add
		 * themselves here.
		 *
		 * @param array<string,Translation_Provider_Interface> $providers Providers by slug.
		 */
		$providers = apply_filters( 'als_translation_providers', $providers );

		$this->providers = array_filter(
			$providers,
			static fn( $provider ) => $provider instanceof Translation_Provider_Interface
		);

		return $this->providers;
	}

	/**
	 * Returns one provider by slug, or null.
	 *
	 * @param string $slug Provider slug.
	 * @return Translation_Provider_Interface|null
	 */
	public function get_provider( string $slug ): ?Translation_Provider_Interface {
		$providers = $this->get_providers();

		return $providers[ $slug ] ?? null;
	}

	/**
	 * Returns the provider selected in the settings, defaulting to manual.
	 *
	 * @return Translation_Provider_Interface
	 */
	public function get_active_provider(): Translation_Provider_Interface {
		$slug     = (string) Settings::get( 'provider', 'manual' );
		$provider = $this->get_provider( $slug );

		if ( $provider instanceof Translation_Provider_Interface ) {
			return $provider;
		}

		return new Manual_Provider();
	}

	/**
	 * Slug of the active provider.
	 *
	 * @return string
	 */
	public function get_provider_slug(): string {
		return $this->get_active_provider()->get_slug();
	}

	/**
	 * Whether the active provider can translate without human input.
	 *
	 * @return bool
	 */
	public function is_automatic(): bool {
		$provider = $this->get_active_provider();

		return $provider->is_automatic() && $provider->is_configured();
	}

	/**
	 * Translates a batch of strings through the active provider.
	 *
	 * Placeholders are masked, the glossary is applied to the result and the
	 * batch is split to respect the provider's request limits.
	 *
	 * @param string[]      $texts   Source strings.
	 * @param Language|null $source  Source language.
	 * @param Language      $target  Target language.
	 * @param bool          $is_html Whether the payload contains markup.
	 * @return string[]|\WP_Error Translations in input order.
	 */
	public function translate_batch( array $texts, ?Language $source, Language $target, bool $is_html = false ) {
		$provider = $this->get_active_provider();

		if ( ! $provider->is_automatic() ) {
			return new \WP_Error(
				'als_manual_provider',
				__( 'The active translation provider does not support automatic translation.', 'advanced-language-switcher' )
			);
		}

		if ( ! $provider->is_configured() ) {
			return new \WP_Error(
				'als_provider_unconfigured',
				sprintf(
					/* translators: %s: provider label. */
					__( 'No API key is configured for %s.', 'advanced-language-switcher' ),
					$provider->get_label()
				)
			);
		}

		$texts = array_values( $texts );

		if ( ! $texts ) {
			return array();
		}

		// Mask placeholders and remember what has to be restored per string.
		$masked   = array();
		$maps     = array();
		$skipped  = array();

		foreach ( $texts as $index => $text ) {
			list( $masked_text, $map ) = Placeholder_Guard::mask( (string) $text );

			if ( Placeholder_Guard::is_empty_after_mask( $masked_text ) ) {
				// Nothing human readable left; do not spend an API call.
				$skipped[ $index ] = (string) $text;
				continue;
			}

			$masked[ $index ] = $masked_text;
			$maps[ $index ]   = $map;
		}

		$results = $skipped;

		if ( $masked ) {
			$limit      = max( 1, min( Settings::get_int( 'batch_size', 25 ), $provider->get_max_batch_size() ?: 25 ) );
			$chunks     = array_chunk( $masked, $limit, true );
			$start_time = microtime( true );

			foreach ( $chunks as $chunk ) {
				$indexes   = array_keys( $chunk );
				$payload   = array_values( $chunk );
				$translated = $provider->translate( $payload, $source, $target, $is_html );

				if ( is_wp_error( $translated ) ) {
					$this->logger->error(
						$translated->get_error_message(),
						array(
							'provider' => $provider->get_slug(),
							'language' => $target->code,
							'count'    => count( $payload ),
						)
					);

					return $translated;
				}

				foreach ( $indexes as $position => $index ) {
					$value = (string) ( $translated[ $position ] ?? '' );

					if ( '' === trim( $value ) ) {
						$results[ $index ] = (string) $texts[ $index ];
						continue;
					}

					$value = Placeholder_Guard::unmask( $value, $maps[ $index ] ?? array() );
					$value = $this->glossary->apply( $value, $target, (string) $texts[ $index ] );

					$results[ $index ] = $value;
				}
			}

			$this->logger->debug(
				'Provider batch completed',
				array(
					'provider' => $provider->get_slug(),
					'language' => $target->code,
					'strings'  => count( $masked ),
					'seconds'  => round( microtime( true ) - $start_time, 3 ),
				)
			);
		}

		ksort( $results );

		$ordered = array();

		foreach ( array_keys( $texts ) as $index ) {
			$ordered[ $index ] = $results[ $index ] ?? (string) $texts[ $index ];
		}

		/**
		 * Filters a completed provider batch.
		 *
		 * @param string[] $ordered Translations in input order.
		 * @param string[] $texts   Source strings.
		 * @param Language $target  Target language.
		 */
		return apply_filters( 'als_provider_batch_result', $ordered, $texts, $target );
	}

	/**
	 * Runs a connection test for a provider.
	 *
	 * @param string $slug Provider slug, empty for the active provider.
	 * @return true|\WP_Error
	 */
	public function test_connection( string $slug = '' ) {
		$provider = '' !== $slug ? $this->get_provider( $slug ) : $this->get_active_provider();

		if ( ! $provider instanceof Translation_Provider_Interface ) {
			return new \WP_Error(
				'als_unknown_provider',
				__( 'Unknown translation provider.', 'advanced-language-switcher' )
			);
		}

		return $provider->test_connection();
	}
}
