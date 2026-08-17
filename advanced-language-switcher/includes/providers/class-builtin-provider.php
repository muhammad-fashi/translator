<?php
/**
 * Built-in translator: automatic translation with no API key.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;
use ALS\Placeholder_Guard;
use ALS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Translates through free, keyless translation services.
 *
 * This is the engine the plugin ships with and uses by default, so a site
 * owner gets automatic translation without registering for anything.
 *
 * It is worth being precise about what "built-in" means here: real machine
 * translation needs a language model far larger than a WordPress plugin can
 * carry, so this adapter calls a free public endpoint rather than translating
 * offline. What it removes is the *account and API key*, not the network call.
 *
 * Two services are supported:
 *
 * - MyMemory (default). A documented, keyless public API with a daily
 *   character allowance. One string per request, so calls are made in a
 *   bounded loop with a time budget.
 * - LibreTranslate. Open source and self-hostable; point it at your own
 *   instance for unlimited, private translation with no third party involved.
 *
 * Because every result is written to the translation memory, each unique
 * string costs exactly one request in the lifetime of the site. A page that
 * has been translated once never contacts the service again.
 */
class Builtin_Provider extends Abstract_Translation_Provider {

	/**
	 * MyMemory endpoint.
	 */
	protected const MYMEMORY_ENDPOINT = 'https://api.mymemory.translated.net/get';

	/**
	 * Marker MyMemory returns instead of a translation once the free daily
	 * allowance is spent. Storing it would corrupt the translation memory.
	 */
	protected const MYMEMORY_QUOTA_MARKER = 'MYMEMORY WARNING';

	/**
	 * Seconds this provider may spend inside a single batch call.
	 *
	 * @var float
	 */
	protected float $time_budget = 12.0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug  = 'builtin';
		$this->label = __( 'Built-in Translator (no API key)', 'advanced-language-switcher' );
	}

	/**
	 * The built-in engine never needs configuring.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		if ( 'libretranslate' === $this->service() ) {
			return '' !== $this->libretranslate_url();
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_max_batch_size(): int {
		return 'libretranslate' === $this->service() ? 25 : 10;
	}

	/**
	 * The selected free service.
	 *
	 * @return string
	 */
	protected function service(): string {
		$service = (string) Settings::get( 'builtin_service', 'mymemory' );

		return in_array( $service, array( 'mymemory', 'libretranslate' ), true ) ? $service : 'mymemory';
	}

	/**
	 * Normalized LibreTranslate base URL.
	 *
	 * @return string
	 */
	protected function libretranslate_url(): string {
		$url = trim( (string) Settings::get( 'libretranslate_url', '' ) );

		return '' === $url ? '' : untrailingslashit( $url );
	}

	/**
	 * Overrides the time budget, used by the background translator.
	 *
	 * @param float $seconds Budget in seconds.
	 * @return void
	 */
	public function set_time_budget( float $seconds ): void {
		$this->time_budget = max( 1.0, $seconds );
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate( array $texts, ?Language $source, Language $target, bool $is_html = false ) {
		$texts = array_values( $texts );

		if ( ! $texts ) {
			return array();
		}

		if ( 'libretranslate' === $this->service() ) {
			return $this->translate_via_libretranslate( $texts, $source, $target, $is_html );
		}

		return $this->translate_via_mymemory( $texts, $source, $target );
	}

	/* ---------------------------------------------------------------------
	 * MyMemory
	 * ------------------------------------------------------------------ */

	/**
	 * Translates a batch through MyMemory, one request per string.
	 *
	 * @param string[]      $texts  Strings.
	 * @param Language|null $source Source language.
	 * @param Language      $target Target language.
	 * @return string[]|\WP_Error
	 */
	protected function translate_via_mymemory( array $texts, ?Language $source, Language $target ) {
		$source_code = $source instanceof Language ? $this->short_code( $source ) : 'en';
		$target_code = $this->short_code( $target );

		if ( $source_code === $target_code ) {
			return $texts;
		}

		$out     = array();
		$started = microtime( true );
		$failed  = 0;

		foreach ( $texts as $index => $text ) {
			// Respect the budget: whatever is left stays pending and is picked
			// up on the next run rather than stalling the request.
			if ( microtime( true ) - $started > $this->time_budget ) {
				break;
			}

			// MyMemory rejects very long queries; longer text is left for a
			// human or for LibreTranslate.
			if ( mb_strlen( $text ) > 500 ) {
				continue;
			}

			$args = array(
				'q'        => $text,
				'langpair' => $source_code . '|' . $target_code,
			);

			$email = sanitize_email( (string) Settings::get( 'builtin_email', '' ) );

			if ( '' !== $email && is_email( $email ) ) {
				// Optional and documented by MyMemory: supplying a contact
				// address raises the free daily allowance. It is not a key.
				$args['de'] = $email;
			}

			$response = $this->request(
				add_query_arg( array_map( 'rawurlencode', $args ), self::MYMEMORY_ENDPOINT ),
				array( 'method' => 'GET' )
			);

			if ( is_wp_error( $response ) ) {
				++$failed;
				continue;
			}

			$translated = $this->parse_mymemory( $response );

			if ( is_wp_error( $translated ) ) {
				// A quota error applies to every remaining string, so stop.
				if ( 'als_builtin_quota' === $translated->get_error_code() ) {
					return $out ? $this->fill( $out, $texts ) : $translated;
				}

				++$failed;
				continue;
			}

			$out[ $index ] = $translated;
		}

		if ( ! $out && $failed > 0 ) {
			return new \WP_Error(
				'als_builtin_failed',
				__( 'The built-in translation service could not be reached. The original text is still shown, and the strings stay queued for the next attempt.', 'advanced-language-switcher' )
			);
		}

		return $this->fill( $out, $texts );
	}

	/**
	 * Reads a translation out of a MyMemory response.
	 *
	 * @param array<string,mixed> $response Decoded body.
	 * @return string|\WP_Error
	 */
	protected function parse_mymemory( array $response ) {
		$status = isset( $response['responseStatus'] ) ? (int) $response['responseStatus'] : 0;
		$text   = $response['responseData']['translatedText'] ?? null;

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new \WP_Error(
				'als_builtin_empty',
				__( 'The translation service returned no text.', 'advanced-language-switcher' )
			);
		}

		// The daily allowance is reported *inside* translatedText, so this has
		// to be caught before the value is ever treated as a translation.
		if ( str_contains( strtoupper( $text ), self::MYMEMORY_QUOTA_MARKER ) ) {
			return new \WP_Error(
				'als_builtin_quota',
				__( 'The free daily translation allowance has been used up. Translations already saved keep working, and the remaining strings resume tomorrow. Adding a contact e-mail on the Translation Engine screen raises the allowance, or point the plugin at your own LibreTranslate server for no limit at all.', 'advanced-language-switcher' )
			);
		}

		if ( 200 !== $status && 0 !== $status ) {
			$detail = isset( $response['responseDetails'] ) && is_string( $response['responseDetails'] )
				? $response['responseDetails']
				: '';

			return new \WP_Error(
				'als_builtin_status',
				sprintf(
					/* translators: %s: service error message. */
					__( 'The translation service reported: %s', 'advanced-language-switcher' ),
					$detail
				)
			);
		}

		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/* ---------------------------------------------------------------------
	 * LibreTranslate
	 * ------------------------------------------------------------------ */

	/**
	 * Translates a batch through a LibreTranslate instance.
	 *
	 * @param string[]      $texts   Strings.
	 * @param Language|null $source  Source language.
	 * @param Language      $target  Target language.
	 * @param bool          $is_html Whether the payload contains markup.
	 * @return string[]|\WP_Error
	 */
	protected function translate_via_libretranslate( array $texts, ?Language $source, Language $target, bool $is_html ) {
		$base = $this->libretranslate_url();

		if ( '' === $base ) {
			return new \WP_Error(
				'als_builtin_no_server',
				__( 'Enter the address of a LibreTranslate server, or switch the built-in translator back to MyMemory.', 'advanced-language-switcher' )
			);
		}

		$body = array(
			'q'      => $texts,
			'source' => $source instanceof Language ? $this->short_code( $source ) : 'auto',
			'target' => $this->short_code( $target ),
			'format' => $is_html ? 'html' : 'text',
		);

		$response = $this->request(
			$base . '/translate',
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_libretranslate( $response, $texts );
	}

	/**
	 * Reads translations out of a LibreTranslate response.
	 *
	 * @param array<string,mixed> $response Decoded body.
	 * @param string[]            $texts    Source strings.
	 * @return string[]|\WP_Error
	 */
	protected function parse_libretranslate( array $response, array $texts ) {
		$translated = $response['translatedText'] ?? null;

		if ( is_string( $translated ) ) {
			$translated = array( $translated );
		}

		if ( ! is_array( $translated ) ) {
			return new \WP_Error(
				'als_builtin_unexpected',
				__( 'The LibreTranslate server returned an unexpected response.', 'advanced-language-switcher' )
			);
		}

		$out = array();

		foreach ( array_keys( $texts ) as $position => $index ) {
			$value = $translated[ $position ] ?? '';

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$out[ $index ] = $value;
			}
		}

		return $this->fill( $out, $texts );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Fills gaps in a partial result with the original text.
	 *
	 * A string that could not be translated stays untranslated rather than
	 * becoming empty, and remains pending for a later attempt.
	 *
	 * @param string[] $out   Partial translations keyed by index.
	 * @param string[] $texts Source strings.
	 * @return string[]
	 */
	protected function fill( array $out, array $texts ): array {
		$result = array();

		foreach ( $texts as $index => $text ) {
			$result[ $index ] = $out[ $index ] ?? $text;
		}

		return $result;
	}

	/**
	 * Two letter code, which is what both free services expect.
	 *
	 * @param Language $language Language.
	 * @return string
	 */
	protected function short_code( Language $language ): string {
		$code = strtolower( $language->code );
		$code = (string) strtok( str_replace( '_', '-', $code ), '-' );

		/** This filter is documented in includes/providers/abstract-translation-provider.php */
		return (string) apply_filters( 'als_provider_language_code', $code, $language, $this->slug );
	}

	/**
	 * Verifies the service is reachable by translating a single word.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$source = new Language( array( 'code' => 'en', 'locale' => 'en_US', 'name' => 'English' ) );
		$target = new Language( array( 'code' => 'es', 'locale' => 'es_ES', 'name' => 'Spanish' ) );

		$result = $this->translate( array( 'Welcome' ), $source, $target, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$value = $result[0] ?? '';

		if ( ! is_string( $value ) || '' === trim( $value ) || 'Welcome' === $value ) {
			return new \WP_Error(
				'als_builtin_no_result',
				__( 'The service replied but did not translate the test phrase. Try again in a moment, or switch service.', 'advanced-language-switcher' )
			);
		}

		return true;
	}
}
