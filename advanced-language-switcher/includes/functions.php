<?php
/**
 * Public developer API.
 *
 * These functions live in the global namespace so themes and other plugins can
 * call them without importing anything.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'als_get_languages' ) ) {
	/**
	 * Returns every enabled language.
	 *
	 * @param bool $include_disabled Whether to include disabled languages.
	 * @return \ALS\Language[]
	 */
	function als_get_languages( bool $include_disabled = false ): array {
		$manager = \ALS\plugin()->languages();

		return $include_disabled ? $manager->all() : $manager->active();
	}
}

if ( ! function_exists( 'als_get_current_language' ) ) {
	/**
	 * Returns the language of the current request.
	 *
	 * @return \ALS\Language|null
	 */
	function als_get_current_language(): ?\ALS\Language {
		return \ALS\plugin()->router()->get_current_language();
	}
}

if ( ! function_exists( 'als_get_current_language_code' ) ) {
	/**
	 * Returns the code of the current language, e.g. "es".
	 *
	 * @return string
	 */
	function als_get_current_language_code(): string {
		$language = als_get_current_language();

		return $language instanceof \ALS\Language ? $language->code : '';
	}
}

if ( ! function_exists( 'als_get_default_language' ) ) {
	/**
	 * Returns the site's default language.
	 *
	 * @return \ALS\Language|null
	 */
	function als_get_default_language(): ?\ALS\Language {
		return \ALS\plugin()->languages()->get_default();
	}
}

if ( ! function_exists( 'als_is_default_language' ) ) {
	/**
	 * Whether the current request is in the default language.
	 *
	 * @return bool
	 */
	function als_is_default_language(): bool {
		return \ALS\plugin()->router()->is_default_language();
	}
}

if ( ! function_exists( 'als_translate' ) ) {
	/**
	 * Translates a string into the given language.
	 *
	 * Falls back to the original text whenever no translation exists, so it is
	 * always safe to wrap output in this call.
	 *
	 * @param string $text     Source text.
	 * @param string $language Target language code; the current language when empty.
	 * @param string $context  Optional context.
	 * @return string
	 */
	function als_translate( string $text, string $language = '', string $context = '' ): string {
		$plugin = \ALS\plugin();

		$target = '' !== $language
			? $plugin->languages()->get_by_code( $language )
			: $plugin->router()->get_current_language();

		if ( ! $target instanceof \ALS\Language ) {
			return $text;
		}

		return $plugin->translations()->translate( $text, $target, $context );
	}
}

if ( ! function_exists( 'als_get_translation' ) ) {
	/**
	 * Returns a stored translation without any provider fallback.
	 *
	 * @param string $text     Source text.
	 * @param string $language Target language code.
	 * @param string $context  Optional context.
	 * @return string|null Null when no translation is stored.
	 */
	function als_get_translation( string $text, string $language, string $context = '' ): ?string {
		$plugin = \ALS\plugin();
		$target = $plugin->languages()->get_by_code( $language );

		if ( ! $target instanceof \ALS\Language ) {
			return null;
		}

		return $plugin->translations()->lookup( $text, $target, $context );
	}
}

if ( ! function_exists( 'als_translate_html' ) ) {
	/**
	 * Translates the human readable text inside an HTML fragment.
	 *
	 * @param string $html     Markup.
	 * @param string $language Target language code; the current language when empty.
	 * @return string
	 */
	function als_translate_html( string $html, string $language = '' ): string {
		$plugin = \ALS\plugin();

		$target = '' !== $language
			? $plugin->languages()->get_by_code( $language )
			: $plugin->router()->get_current_language();

		if ( ! $target instanceof \ALS\Language ) {
			return $html;
		}

		$translator = new \ALS\Html_Translator( $plugin->translations(), $target );

		return $translator->translate( $html );
	}
}

if ( ! function_exists( 'als_is_rtl' ) ) {
	/**
	 * Whether the current language is written right to left.
	 *
	 * @return bool
	 */
	function als_is_rtl(): bool {
		$language = als_get_current_language();

		return $language instanceof \ALS\Language && $language->is_rtl();
	}
}

if ( ! function_exists( 'als_get_language_url' ) ) {
	/**
	 * Returns the current page's URL in another language.
	 *
	 * @param string $language Target language code.
	 * @param string $url      Source URL; the current request when empty.
	 * @return string
	 */
	function als_get_language_url( string $language, string $url = '' ): string {
		$plugin = \ALS\plugin();
		$target = $plugin->languages()->get_by_code( $language );

		if ( ! $target instanceof \ALS\Language ) {
			return '' !== $url ? $url : $plugin->router()->current_url();
		}

		return $plugin->router()->get_language_url( $target, $url );
	}
}

if ( ! function_exists( 'als_get_alternate_urls' ) ) {
	/**
	 * Returns the current page's URL in every enabled language.
	 *
	 * @param string $url Source URL; the current request when empty.
	 * @return array<string,string> Language code => URL.
	 */
	function als_get_alternate_urls( string $url = '' ): array {
		$out = array();

		foreach ( \ALS\plugin()->router()->get_alternate_urls( $url ) as $code => $alternate ) {
			$out[ $code ] = (string) $alternate['url'];
		}

		return $out;
	}
}

if ( ! function_exists( 'als_language_switcher' ) ) {
	/**
	 * Renders a language switcher from a template file.
	 *
	 * @param array<string,mixed> $args Render arguments, see ALS\Switcher::defaults().
	 * @param bool                $echo Whether to print the markup.
	 * @return string
	 */
	function als_language_switcher( array $args = array(), bool $echo = true ): string {
		wp_enqueue_style( 'als-language-switcher' );
		wp_enqueue_script( 'als-language-switcher' );

		$html = \ALS\Switcher::render( $args );

		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Switcher::render().
		}

		return $html;
	}
}

if ( ! function_exists( 'als_register_string' ) ) {
	/**
	 * Registers a string so it appears in the translation editor.
	 *
	 * @param string $text    Source text.
	 * @param string $context Optional context.
	 * @return int String id, or 0 when the text is not translatable.
	 */
	function als_register_string( string $text, string $context = '' ): int {
		return \ALS\plugin()->translations()->register_string( $text, $context );
	}
}

if ( ! function_exists( 'als_get_translation_stats' ) ) {
	/**
	 * Returns translation completion statistics.
	 *
	 * @return array<string,mixed>
	 */
	function als_get_translation_stats(): array {
		return \ALS\plugin()->translations()->global_stats();
	}
}
