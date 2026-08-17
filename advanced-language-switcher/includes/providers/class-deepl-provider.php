<?php
/**
 * DeepL adapter (free and pro endpoints).
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;
use ALS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the DeepL v2 translate endpoint.
 */
class Deepl_Provider extends Abstract_Translation_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug  = 'deepl';
		$this->label = __( 'DeepL', 'advanced-language-switcher' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_max_batch_size(): int {
		return 50;
	}

	/**
	 * Free API keys carry a `:fx` suffix and use a different host.
	 *
	 * @return string
	 */
	protected function endpoint(): string {
		$key  = $this->api_key();
		$free = str_ends_with( $key, ':fx' );

		return $free
			? 'https://api-free.deepl.com/v2/translate'
			: 'https://api.deepl.com/v2/translate';
	}

	/**
	 * DeepL expects uppercase codes, and regional variants for some languages.
	 *
	 * @param Language|null $language Language.
	 * @return string
	 */
	protected function language_code( ?Language $language ): string {
		$code = parent::language_code( $language );

		if ( '' === $code ) {
			return '';
		}

		$map = array(
			'pt' => 'PT-PT',
			'br' => 'PT-BR',
			'zh' => 'ZH',
			'tw' => 'ZH',
			'en' => 'EN-GB',
			'no' => 'NB',
		);

		return $map[ $code ] ?? strtoupper( substr( $code, 0, 2 ) );
	}

	/**
	 * Target codes allow regional variants, source codes do not.
	 *
	 * @param Language|null $language Language.
	 * @return string
	 */
	protected function source_code( ?Language $language ): string {
		$code = $this->language_code( $language );

		return '' === $code ? '' : strtoupper( substr( $code, 0, 2 ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate( array $texts, ?Language $source, Language $target, bool $is_html = false ) {
		$guard = $this->guard();

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$texts = array_values( $texts );

		if ( ! $texts ) {
			return array();
		}

		$body = array(
			'text'        => $texts,
			'target_lang' => $this->language_code( $target ),
			'tag_handling' => $is_html ? 'html' : null,
		);

		$source_code = $this->source_code( $source );

		if ( '' !== $source_code ) {
			$body['source_lang'] = $source_code;
		}

		$formality = (string) Settings::get( 'deepl_formality', 'default' );

		if ( 'default' !== $formality ) {
			$body['formality'] = $formality;
		}

		$body = array_filter( $body, static fn( $value ) => null !== $value );

		$response = $this->request(
			$this->endpoint(),
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $this->api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = $response['translations'] ?? null;

		if ( ! is_array( $items ) ) {
			return new \WP_Error(
				'als_deepl_unexpected',
				__( 'DeepL returned an unexpected payload.', 'advanced-language-switcher' )
			);
		}

		$out = array();

		foreach ( $texts as $index => $unused ) {
			$out[ $index ] = (string) ( $items[ $index ]['text'] ?? '' );
		}

		return $out;
	}
}
