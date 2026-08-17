<?php
/**
 * Multilingual SEO: hreflang, canonicals and meta translation.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Emits the alternate language signals search engines need and keeps popular
 * SEO plugins in step with the active language.
 */
class Seo {

	/**
	 * Plugin container.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin container.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'print_hreflang' ), 2 );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical' ), 20, 2 );
		add_filter( 'wpseo_canonical', array( $this, 'filter_plugin_canonical' ), 20 );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_plugin_canonical' ), 20 );
		add_filter( 'aioseo_canonical_url', array( $this, 'filter_plugin_canonical' ), 20 );

		if ( ! Settings::is_enabled( 'translate_seo_meta' ) ) {
			return;
		}

		// Yoast SEO.
		add_filter( 'wpseo_title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'wpseo_metadesc', array( $this, 'translate_meta' ), 20 );
		add_filter( 'wpseo_opengraph_title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'translate_meta' ), 20 );
		add_filter( 'wpseo_twitter_title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'wpseo_twitter_description', array( $this, 'translate_meta' ), 20 );

		// Rank Math.
		add_filter( 'rank_math/frontend/title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'rank_math/frontend/description', array( $this, 'translate_meta' ), 20 );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'translate_meta' ), 20 );

		// All in One SEO.
		add_filter( 'aioseo_title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'aioseo_description', array( $this, 'translate_meta' ), 20 );
		add_filter( 'aioseo_og_title', array( $this, 'translate_meta' ), 20 );
		add_filter( 'aioseo_og_description', array( $this, 'translate_meta' ), 20 );
	}

	/**
	 * Prints hreflang alternates for every enabled language.
	 *
	 * @return void
	 */
	public function print_hreflang(): void {
		if ( ! Settings::is_enabled( 'hreflang_enabled' ) ) {
			return;
		}

		if ( is_404() || is_search() || is_feed() ) {
			return;
		}

		$alternates = $this->plugin->router()->get_alternate_urls();

		if ( count( $alternates ) < 2 ) {
			return;
		}

		$x_default = (string) Settings::get( 'x_default_language', '' );
		$default   = $this->plugin->languages()->get_default();

		if ( '' === $x_default && $default instanceof Language ) {
			$x_default = $default->code;
		}

		$output = "\n<!-- Advanced Language Switcher: alternate languages -->\n";

		foreach ( $alternates as $code => $alternate ) {
			/** @var Language $language */
			$language = $alternate['language'];

			$output .= sprintf(
				"<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				esc_attr( $language->html_lang() ),
				esc_url( (string) $alternate['url'] )
			);

			if ( $code === $x_default ) {
				$output .= sprintf(
					"<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n",
					esc_url( (string) $alternate['url'] )
				);
			}
		}

		/**
		 * Filters the hreflang block.
		 *
		 * @param string $output     Markup.
		 * @param array  $alternates Alternate URLs.
		 */
		echo apply_filters( 'als_hreflang_output', $output, $alternates ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * Keeps the canonical URL inside the active language.
	 *
	 * @param string   $url  Canonical URL.
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function filter_canonical( $url, $post = null ): string {
		if ( ! Settings::is_enabled( 'canonical_enabled' ) ) {
			return (string) $url;
		}

		return $this->localize_url( (string) $url );
	}

	/**
	 * Canonical filter shape used by third party SEO plugins.
	 *
	 * @param string $url Canonical URL.
	 * @return string
	 */
	public function filter_plugin_canonical( $url ): string {
		if ( ! Settings::is_enabled( 'canonical_enabled' ) || ! is_string( $url ) || '' === $url ) {
			return (string) $url;
		}

		return $this->localize_url( $url );
	}

	/**
	 * Rewrites a URL into the active language.
	 *
	 * @param string $url Source URL.
	 * @return string
	 */
	protected function localize_url( string $url ): string {
		$current = $this->plugin->router()->get_current_language();

		if ( ! $current instanceof Language || '' === $url ) {
			return $url;
		}

		return $this->plugin->router()->get_language_url( $current, $url );
	}

	/**
	 * Translates a meta value produced by an SEO plugin.
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	public function translate_meta( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $value;
		}

		$router = $this->plugin->router();

		if ( $router->is_default_language() ) {
			return $value;
		}

		$language = $router->get_current_language();

		if ( ! $language instanceof Language ) {
			return $value;
		}

		return $this->plugin->translations()->translate( $value, $language, 'seo:meta' );
	}
}
