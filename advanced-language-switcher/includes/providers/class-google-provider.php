<?php
/**
 * Google Cloud Translation adapter (v2 REST API).
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to translation.googleapis.com/language/translate/v2.
 */
class Google_Provider extends Abstract_Translation_Provider {

	/**
	 * API endpoint.
	 */
	protected const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug  = 'google';
		$this->label = __( 'Google Translate', 'advanced-language-switcher' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_max_batch_size(): int {
		// The v2 endpoint accepts up to 128 q parameters per request.
		return 100;
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
			'q'      => $texts,
			'target' => $this->language_code( $target ),
			'format' => $is_html ? 'html' : 'text',
		);

		$source_code = $this->language_code( $source );

		if ( '' !== $source_code ) {
			$body['source'] = $source_code;
		}

		$response = $this->request(
			add_query_arg( 'key', rawurlencode( $this->api_key() ), self::ENDPOINT ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = $response['data']['translations'] ?? null;

		if ( ! is_array( $items ) ) {
			return new \WP_Error(
				'als_google_unexpected',
				__( 'Google returned an unexpected payload.', 'advanced-language-switcher' )
			);
		}

		$out = array();

		foreach ( $texts as $index => $unused ) {
			$translated = $items[ $index ]['translatedText'] ?? '';
			// The v2 API HTML-encodes its output even in text mode.
			$out[ $index ] = html_entity_decode( (string) $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return $out;
	}
}
