<?php
/**
 * OpenAI chat completions adapter.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;
use ALS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Uses a chat model to translate batches of strings as structured JSON.
 *
 * The model is asked to return a JSON object keyed by the index of each input
 * string, which keeps ordering reliable even when a string is a single word.
 */
class Openai_Provider extends Abstract_Translation_Provider {

	/**
	 * API endpoint.
	 */
	protected const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug  = 'openai';
		$this->label = __( 'OpenAI', 'advanced-language-switcher' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_max_batch_size(): int {
		return 40;
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

		$model       = (string) Settings::get( 'openai_model', 'gpt-4o-mini' );
		$temperature = (float) Settings::get( 'openai_temperature', 0.2 );

		$payload = array(
			'model'           => $model,
			'temperature'     => max( 0.0, min( 2.0, $temperature ) ),
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $this->system_prompt( $source, $target, $is_html ),
				),
				array(
					'role'    => 'user',
					'content' => $this->user_prompt( $texts ),
				),
			),
		);

		/**
		 * Filters the OpenAI request payload before it is sent.
		 *
		 * @param array<string,mixed> $payload Request body.
		 * @param string[]            $texts   Strings being translated.
		 * @param Language            $target  Target language.
		 */
		$payload = apply_filters( 'als_openai_payload', $payload, $texts, $target );

		$response = $this->request(
			self::ENDPOINT,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => max( 30, Settings::get_int( 'request_timeout', 20 ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = $response['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return new \WP_Error(
				'als_openai_empty',
				__( 'OpenAI returned an empty response.', 'advanced-language-switcher' )
			);
		}

		$decoded = json_decode( $content, true );

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'als_openai_invalid_json',
				__( 'OpenAI did not return valid JSON.', 'advanced-language-switcher' )
			);
		}

		$items = $decoded['translations'] ?? $decoded;

		if ( ! is_array( $items ) ) {
			return new \WP_Error(
				'als_openai_invalid_shape',
				__( 'OpenAI returned an unexpected payload shape.', 'advanced-language-switcher' )
			);
		}

		$out = array();

		foreach ( $texts as $index => $original ) {
			$value = $items[ (string) $index ] ?? ( $items[ $index ] ?? '' );

			if ( is_array( $value ) ) {
				$value = $value['text'] ?? '';
			}

			$value = is_string( $value ) ? trim( $value ) : '';

			// A missing entry falls back to the original rather than blanking.
			$out[ $index ] = '' !== $value ? $value : $original;
		}

		return $out;
	}

	/**
	 * Builds the system instruction.
	 *
	 * @param Language|null $source  Source language.
	 * @param Language      $target  Target language.
	 * @param bool          $is_html Whether markup is present.
	 * @return string
	 */
	protected function system_prompt( ?Language $source, Language $target, bool $is_html ): string {
		$instructions = trim( (string) Settings::get( 'openai_instructions', Settings::default_instructions() ) );

		if ( '' === $instructions ) {
			$instructions = Settings::default_instructions();
		}

		$source_name = $source instanceof Language ? $source->name : 'the detected source language';
		$target_name = $target->name . ( '' !== $target->native_name ? ' (' . $target->native_name . ')' : '' );

		$rules = array(
			$instructions,
			sprintf( 'Translate from %s into %s.', $source_name, $target_name ),
			'You receive a JSON object whose keys are string indexes and whose values are the strings to translate.',
			'Reply with a JSON object using the exact same keys, where each value is the translated string. Do not add, remove or reorder keys.',
			'Never translate, reword or reformat any token that looks like ⟤ALS0⟥ — copy such tokens through verbatim, keeping their position in the sentence natural for the target language.',
			'Never translate brand names, product SKUs, e-mail addresses, URLs, CSS class names or code.',
			'Preserve leading and trailing whitespace, capitalisation style and punctuation conventions appropriate to the target language.',
		);

		if ( $is_html ) {
			$rules[] = 'The values contain HTML. Preserve every tag, attribute and entity exactly; translate only the human readable text between tags and the text inside title, alt, placeholder and aria-label attributes.';
		}

		if ( $target->is_rtl() ) {
			$rules[] = 'The target language is written right to left. Do not add directional control characters.';
		}

		/**
		 * Filters the OpenAI system prompt rules.
		 *
		 * @param string[] $rules  Prompt lines.
		 * @param Language $target Target language.
		 */
		$rules = apply_filters( 'als_openai_prompt_rules', $rules, $target );

		return implode( "\n", $rules );
	}

	/**
	 * Builds the user message containing the indexed strings.
	 *
	 * @param string[] $texts Strings.
	 * @return string
	 */
	protected function user_prompt( array $texts ): string {
		$indexed = array();

		foreach ( $texts as $index => $text ) {
			$indexed[ (string) $index ] = $text;
		}

		return (string) wp_json_encode( $indexed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Verifies the key with a lightweight models call before translating.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$guard = $this->guard();

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$response = $this->request(
			'https://api.openai.com/v1/models',
			array(
				'method'  => 'GET',
				'headers' => array( 'Authorization' => 'Bearer ' . $this->api_key() ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}
}
