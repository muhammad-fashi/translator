<?php
/**
 * Placeholder protection for outbound translation requests.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Swaps variables, shortcodes and URLs for opaque tokens before a string is
 * sent to a translation provider, then restores them afterwards.
 *
 * Machine translators happily "translate" `{first_name}` into
 * `{primer_nombre}`, which silently breaks merge tags. Masking removes that
 * whole class of bug.
 */
class Placeholder_Guard {

	/**
	 * Patterns that must survive translation untouched.
	 *
	 * Order matters: the longest / most specific patterns come first.
	 *
	 * @return string[]
	 */
	protected static function patterns(): array {
		$patterns = array(
			// Handlebars / Mustache style: {{ product_name }}.
			'/\{\{[^{}]{1,120}\}\}/u',
			// Elementor dynamic tags: [elementor-tag id="..." ...].
			'/\[elementor-tag[^\]]*\]/iu',
			// Any shortcode, self closing or opening: [contact-form-7 id="5"].
			'/\[\/?[a-zA-Z0-9_\-]+(?:[^\]]*)?\]/u',
			// Single brace merge tags: {name}.
			'/\{[a-zA-Z0-9_\-\.\s]{1,80}\}/u',
			// Percent wrapped tags: %name%.
			'/%[a-zA-Z0-9_\-]{1,60}%/u',
			// printf placeholders: %s, %d, %1$s, %.2f.
			'/%(?:\d+\$)?[+-]?(?:[ 0]|\'.)?-?\d*(?:\.\d+)?[bcdeEfFgGosuxX]/',
			// URLs and protocol relative links.
			'#\bhttps?://[^\s<>"\']+#i',
			// E-mail addresses.
			'/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/u',
			// HTML entities.
			'/&(?:[a-zA-Z][a-zA-Z0-9]{1,10}|#\d{1,6}|#x[0-9a-fA-F]{1,5});/',
		);

		/**
		 * Filters the placeholder patterns protected during translation.
		 *
		 * @param string[] $patterns Regular expressions.
		 */
		return apply_filters( 'als_placeholder_patterns', $patterns );
	}

	/**
	 * Replaces protected fragments with numbered tokens.
	 *
	 * @param string $text Source text.
	 * @return array{0:string,1:array<string,string>} Masked text and the token map.
	 */
	public static function mask( string $text ): array {
		$map     = array();
		$counter = 0;

		foreach ( self::patterns() as $pattern ) {
			$text = (string) preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( &$map, &$counter ): string {
					// Never mask an already-issued token.
					if ( preg_match( '/^\x{2E24}ALS\d+\x{2E25}$/u', $matches[0] ) ) {
						return $matches[0];
					}

					$token         = self::token( $counter );
					$map[ $token ] = $matches[0];
					++$counter;

					return $token;
				},
				$text
			);
		}

		return array( $text, $map );
	}

	/**
	 * Restores the original fragments.
	 *
	 * Providers sometimes add spaces around tokens or change their case, so
	 * restoration is deliberately tolerant.
	 *
	 * @param string                $text Translated text.
	 * @param array<string,string>  $map  Token map from mask().
	 * @return string
	 */
	public static function unmask( string $text, array $map ): string {
		if ( ! $map ) {
			return $text;
		}

		foreach ( $map as $token => $original ) {
			if ( str_contains( $text, $token ) ) {
				$text = str_replace( $token, $original, $text );
				continue;
			}

			// Tolerate whitespace injected inside the token by the provider.
			$loose = preg_quote( $token, '/' );
			$loose = str_replace( array( 'A', 'L', 'S' ), array( '\s*A', '\s*L', '\s*S' ), $loose );

			$text = (string) preg_replace( '/' . $loose . '/u', str_replace( '$', '\\$', $original ), $text, 1 );
		}

		// Any token the provider mangled beyond recognition is stripped rather
		// than left visible to a visitor.
		return (string) preg_replace( '/\x{2E24}\s*A\s*L\s*S\s*\d+\s*\x{2E25}/u', '', $text );
	}

	/**
	 * Builds the token for an index.
	 *
	 * Uses rare CJK-safe bracket characters so the token never collides with
	 * real content and is not word-segmented by translation engines.
	 *
	 * @param int $index Token index.
	 * @return string
	 */
	protected static function token( int $index ): string {
		return "\u{2E24}ALS{$index}\u{2E25}";
	}

	/**
	 * Whether a string is entirely made of protected fragments, meaning there
	 * is nothing to translate and the API call can be skipped.
	 *
	 * @param string $masked Masked text.
	 * @return bool
	 */
	public static function is_empty_after_mask( string $masked ): bool {
		$stripped = (string) preg_replace( '/\x{2E24}ALS\d+\x{2E25}/u', '', $masked );

		return ! preg_match( '/\p{L}/u', $stripped );
	}
}
