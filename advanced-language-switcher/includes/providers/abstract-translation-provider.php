<?php
/**
 * Shared provider behaviour: HTTP, retries, error normalization.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;
use ALS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for API backed providers.
 */
abstract class Abstract_Translation_Provider implements Translation_Provider_Interface {

	/**
	 * Provider slug.
	 *
	 * @var string
	 */
	protected string $slug = '';

	/**
	 * Provider label.
	 *
	 * @var string
	 */
	protected string $label = '';

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_automatic(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_max_batch_size(): int {
		return 25;
	}

	/**
	 * Returns the stored API key for this provider.
	 *
	 * @return string
	 */
	protected function api_key(): string {
		return Settings::get_credential( $this->slug );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		return '' !== $this->api_key();
	}

	/**
	 * Maps a plugin language onto the code the provider expects.
	 *
	 * @param Language|null $language Language, or null.
	 * @return string
	 */
	protected function language_code( ?Language $language ): string {
		if ( ! $language instanceof Language ) {
			return '';
		}

		$code = strtolower( $language->code );

		/**
		 * Filters the provider specific language code.
		 *
		 * @param string   $code     Resolved code.
		 * @param Language $language Language object.
		 * @param string   $provider Provider slug.
		 */
		return (string) apply_filters( 'als_provider_language_code', $code, $language, $this->slug );
	}

	/**
	 * Performs an HTTP request and normalizes transport/HTTP failures.
	 *
	 * @param string              $url  Endpoint.
	 * @param array<string,mixed> $args wp_remote_request() arguments.
	 * @return array<string,mixed>|\WP_Error Decoded JSON body, or an error.
	 */
	protected function request( string $url, array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'timeout'     => Settings::get_int( 'request_timeout', 20 ),
				'redirection' => 3,
				'user-agent'  => 'AdvancedLanguageSwitcher/' . ALS_VERSION . '; ' . home_url( '/' ),
			)
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'als_provider_transport',
				sprintf(
					/* translators: %s: transport error message. */
					__( 'Could not reach the translation service: %s', 'advanced-language-switcher' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'als_provider_http_' . $code,
				$this->extract_error_message( $code, is_array( $data ) ? $data : array(), $body ),
				array( 'status' => $code )
			);
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'als_provider_invalid_json',
				__( 'The translation service returned an unreadable response.', 'advanced-language-switcher' )
			);
		}

		return $data;
	}

	/**
	 * Builds a readable message out of an API error payload.
	 *
	 * The raw body is deliberately truncated and never shown to visitors; it
	 * only reaches the admin screens and the log table.
	 *
	 * @param int                 $code HTTP status code.
	 * @param array<string,mixed> $data Decoded body.
	 * @param string              $body Raw body.
	 * @return string
	 */
	protected function extract_error_message( int $code, array $data, string $body ): string {
		$message = '';

		if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
			$message = $data['error']['message'];
		} elseif ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$message = $data['message'];
		} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$message = $data['error'];
		} else {
			$message = substr( wp_strip_all_tags( $body ), 0, 200 );
		}

		if ( 401 === $code || 403 === $code ) {
			$message = __( 'Authentication failed. Check the API key.', 'advanced-language-switcher' ) . ' ' . $message;
		} elseif ( 429 === $code ) {
			$message = __( 'Rate limit reached. Try a smaller batch size.', 'advanced-language-switcher' ) . ' ' . $message;
		}

		return sprintf(
			/* translators: 1: HTTP status code, 2: error message. */
			__( 'HTTP %1$d: %2$s', 'advanced-language-switcher' ),
			$code,
			trim( $message )
		);
	}

	/**
	 * Guard used by every provider before it builds a request.
	 *
	 * @return true|\WP_Error
	 */
	protected function guard() {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'als_provider_unconfigured',
				sprintf(
					/* translators: %s: provider label. */
					__( 'No API key is configured for %s.', 'advanced-language-switcher' ),
					$this->get_label()
				)
			);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_connection() {
		$guard = $this->guard();

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$target = new Language(
			array(
				'code'   => 'es',
				'locale' => 'es_ES',
				'name'   => 'Spanish',
			)
		);

		$source = new Language(
			array(
				'code'   => 'en',
				'locale' => 'en_US',
				'name'   => 'English',
			)
		);

		$result = $this->translate( array( 'Hello' ), $source, $target, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! isset( $result[0] ) || '' === trim( (string) $result[0] ) ) {
			return new \WP_Error(
				'als_provider_empty',
				__( 'The provider responded but returned no translation.', 'advanced-language-switcher' )
			);
		}

		return true;
	}
}
