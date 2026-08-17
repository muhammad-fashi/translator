<?php
/**
 * Central settings registry.
 *
 * Everything the administrator can configure lives in a small number of
 * autoloaded options; API credentials live in a separate, non-autoloaded
 * option so they are never accidentally dumped into a page payload.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and sanitizes plugin settings.
 */
class Settings {

	/**
	 * Main settings option name (autoloaded).
	 */
	public const OPTION = 'als_settings';

	/**
	 * Credentials option name (never autoloaded, admin-only).
	 */
	public const CREDENTIALS_OPTION = 'als_credentials';

	/**
	 * Runtime cache of the settings array.
	 *
	 * @var array<string,mixed>|null
	 */
	protected static ?array $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Translation engine.
			'provider'                  => 'builtin',
			'source_language'           => '',
			'builtin_service'           => 'mymemory',
			'builtin_email'             => '',
			'libretranslate_url'        => '',
			'openai_model'              => 'gpt-4o-mini',
			'openai_temperature'        => 0.2,
			'openai_instructions'       => self::default_instructions(),
			'deepl_formality'           => 'default',
			'request_timeout'           => 20,
			'batch_size'                => 25,

			// Automatic translation behaviour.
			'auto_translate_new'        => 1,
			'auto_translate_updated'    => 0,
			'auto_translate_missing'    => 1,
			'overwrite_manual'          => 0,
			'auto_translate_on_render'  => 0,
			'background_translate'      => 1,
			'background_batch'          => 10,

			// URL handling.
			'url_mode'                  => 'directory',
			'hide_default_prefix'       => 1,
			'browser_detection'         => 0,
			'respect_user_choice'       => 1,
			'cookie_lifetime_days'      => 30,
			'fallback_behaviour'        => 'original',

			// Caching.
			'cache_enabled'             => 1,
			'cache_ttl'                 => 86400,
			'page_cache_enabled'        => 0,
			'page_cache_ttl'            => 3600,
			'object_cache_enabled'      => 1,

			// Scope.
			'translate_pages'           => 1,
			'translate_posts'           => 1,
			'translate_products'        => 1,
			'translate_menus'           => 1,
			'translate_widgets'         => 1,
			'translate_elementor'       => 1,
			'translate_theme'           => 1,
			'translate_forms'           => 1,
			'translate_urls'            => 0,
			'translate_seo_meta'        => 1,

			// Exclusions.
			'excluded_classes'          => "no-translate\nnotranslate\nskiptranslate",
			'excluded_selectors'        => '',
			'excluded_strings'          => '',
			'excluded_post_ids'         => '',
			'excluded_widget_types'     => "html\nshortcode",

			// SEO.
			'hreflang_enabled'          => 1,
			'canonical_enabled'         => 1,
			'x_default_language'        => '',

			// Switcher global defaults.
			'switcher_preset'           => 'compact-pill',
			'switcher_display'          => 'code',
			'switcher_layout'           => 'pills',
			'switcher_flag_style'       => 'emoji',
			'switcher_behaviour'        => 'navigate',
			'switcher_animation'        => 'fade',
			'switcher_show_flag'        => 0,
			'switcher_show_code'        => 1,
			'switcher_show_name'        => 0,
			'switcher_active_bg'        => '#1f4f45',
			'switcher_active_color'     => '#ffffff',
			'switcher_inactive_color'   => '#4a5568',
			'switcher_container_bg'     => '#ffffff',
			'switcher_border_color'     => '#d7dde3',

			// Front end.
			'ajax_switching'            => 0,
			'load_assets_everywhere'    => 0,
			'rtl_support'               => 1,
			'custom_rtl_css'            => '',

			// Diagnostics.
			'logging_enabled'           => 0,
			'log_level'                 => 'error',
			'log_retention_days'        => 14,
			'uninstall_remove_data'     => 0,
			'uninstall_remove_settings' => 0,
		);
	}

	/**
	 * The default OpenAI style translation instruction.
	 *
	 * @return string
	 */
	public static function default_instructions(): string {
		return 'Translate the supplied website content naturally while preserving HTML, shortcodes, placeholders, URLs, variables, and formatting.';
	}

	/**
	 * Writes defaults for any setting that has never been saved.
	 *
	 * @return void
	 */
	public static function install_defaults(): void {
		$existing = get_option( self::OPTION, array() );
		$existing = is_array( $existing ) ? $existing : array();

		update_option( self::OPTION, array_merge( self::defaults(), $existing ) );

		if ( false === get_option( self::CREDENTIALS_OPTION, false ) ) {
			add_option( self::CREDENTIALS_OPTION, array(), '', false );
		}

		self::$cache = null;
	}

	/**
	 * Returns the full settings array merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		/**
		 * Filters the resolved plugin settings.
		 *
		 * @param array<string,mixed> $settings Settings merged over defaults.
		 */
		self::$cache = apply_filters( 'als_settings', array_merge( self::defaults(), $stored ) );

		return self::$cache;
	}

	/**
	 * Reads a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();

		if ( ! array_key_exists( $key, $all ) ) {
			return $default;
		}

		/**
		 * Filters a single setting value.
		 *
		 * @param mixed  $value Setting value.
		 * @param string $key   Setting key.
		 */
		return apply_filters( 'als_setting', $all[ $key ], $key );
	}

	/**
	 * Reads a boolean setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_enabled( string $key ): bool {
		return (bool) self::get( $key, false );
	}

	/**
	 * Reads an integer setting.
	 *
	 * @param string $key      Setting key.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	public static function get_int( string $key, int $fallback = 0 ): int {
		$value = self::get( $key, $fallback );

		return is_numeric( $value ) ? (int) $value : $fallback;
	}

	/**
	 * Persists a set of settings after sanitizing them.
	 *
	 * @param array<string,mixed> $values Raw values.
	 * @return array<string,mixed> The stored settings.
	 */
	public static function update( array $values ): array {
		$current   = self::all();
		$sanitized = self::sanitize( $values );
		$merged    = array_merge( $current, $sanitized );

		update_option( self::OPTION, $merged );
		self::$cache = null;

		/**
		 * Fires after settings are saved.
		 *
		 * @param array<string,mixed> $merged    Stored settings.
		 * @param array<string,mixed> $sanitized Values that were changed.
		 */
		do_action( 'als_settings_updated', $merged, $sanitized );

		return $merged;
	}

	/**
	 * Sanitizes an incoming settings payload against the known defaults.
	 *
	 * Unknown keys are dropped, which keeps the option tidy and blocks
	 * arbitrary option injection through the settings form.
	 *
	 * @param array<string,mixed> $values Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $values ): array {
		$defaults  = self::defaults();
		$sanitized = array();

		$textareas = array(
			'excluded_classes',
			'excluded_selectors',
			'excluded_strings',
			'excluded_post_ids',
			'excluded_widget_types',
			'openai_instructions',
			'custom_rtl_css',
		);

		$enums = array(
			'provider'           => array( 'builtin', 'manual', 'google', 'deepl', 'openai' ),
			'builtin_service'    => array( 'mymemory', 'libretranslate' ),
			'url_mode'           => array( 'directory', 'directory_all', 'query', 'cookie' ),
			'fallback_behaviour' => array( 'original', 'home' ),
			'log_level'          => array( 'error', 'warning', 'info', 'debug' ),
			'deepl_formality'    => array( 'default', 'more', 'less' ),
			'switcher_display'   => array( 'code', 'name', 'flag', 'flag_code', 'flag_name', 'code_name', 'flag_code_name' ),
			'switcher_layout'    => array( 'horizontal', 'vertical', 'dropdown', 'pills', 'segmented', 'minimal', 'compact' ),
			'switcher_flag_style' => array( 'none', 'emoji', 'svg', 'image' ),
			'switcher_behaviour' => array( 'navigate', 'reload', 'ajax', 'url' ),
			'switcher_animation' => array( 'none', 'fade', 'slide', 'scale' ),
			'switcher_preset'    => array( 'compact-pill', 'minimal-text', 'flag-code', 'flag-name', 'dropdown', 'modern-segmented', 'glassmorphism', 'minimal-border' ),
		);

		foreach ( $values as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			if ( isset( $enums[ $key ] ) ) {
				$value                = is_string( $value ) ? sanitize_key( $value ) : '';
				$sanitized[ $key ] = in_array( $value, $enums[ $key ], true ) ? $value : $defaults[ $key ];
				continue;
			}

			if ( in_array( $key, $textareas, true ) ) {
				$sanitized[ $key ] = sanitize_textarea_field( (string) $value );
				continue;
			}

			if ( str_ends_with( $key, '_color' ) || str_ends_with( $key, '_bg' ) ) {
				$color             = sanitize_hex_color( (string) $value );
				$sanitized[ $key ] = $color ? $color : $defaults[ $key ];
				continue;
			}

			if ( is_int( $defaults[ $key ] ) ) {
				$sanitized[ $key ] = (int) $value;
				continue;
			}

			if ( is_float( $defaults[ $key ] ) ) {
				$sanitized[ $key ] = (float) $value;
				continue;
			}

			$sanitized[ $key ] = sanitize_text_field( (string) $value );
		}

		// Clamp numeric ranges to sane values.
		if ( isset( $sanitized['openai_temperature'] ) ) {
			$sanitized['openai_temperature'] = max( 0.0, min( 2.0, (float) $sanitized['openai_temperature'] ) );
		}

		if ( isset( $sanitized['batch_size'] ) ) {
			$sanitized['batch_size'] = max( 1, min( 100, (int) $sanitized['batch_size'] ) );
		}

		if ( isset( $sanitized['request_timeout'] ) ) {
			$sanitized['request_timeout'] = max( 5, min( 120, (int) $sanitized['request_timeout'] ) );
		}

		if ( isset( $sanitized['cache_ttl'] ) ) {
			$sanitized['cache_ttl'] = max( 60, (int) $sanitized['cache_ttl'] );
		}

		if ( isset( $sanitized['cookie_lifetime_days'] ) ) {
			$sanitized['cookie_lifetime_days'] = max( 1, min( 365, (int) $sanitized['cookie_lifetime_days'] ) );
		}

		return $sanitized;
	}

	/**
	 * Returns the stored API credential for a provider.
	 *
	 * Credentials live in their own non-autoloaded option, so they are never
	 * pulled into a normal front end request. Callers that display a key must
	 * use mask_credential(); the raw value is only ever sent to the provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function get_credential( string $provider ): string {
		$credentials = get_option( self::CREDENTIALS_OPTION, array() );
		$credentials = is_array( $credentials ) ? $credentials : array();

		$value = isset( $credentials[ $provider ] ) ? (string) $credentials[ $provider ] : '';

		/**
		 * Filters a provider credential, allowing constants or a vault to win.
		 *
		 * @param string $value    Stored credential.
		 * @param string $provider Provider slug.
		 */
		return (string) apply_filters( 'als_provider_credential', $value, $provider );
	}

	/**
	 * Stores an API credential.
	 *
	 * @param string $provider Provider slug.
	 * @param string $value    Raw credential.
	 * @return void
	 */
	public static function set_credential( string $provider, string $value ): void {
		$credentials = get_option( self::CREDENTIALS_OPTION, array() );
		$credentials = is_array( $credentials ) ? $credentials : array();

		$provider = sanitize_key( $provider );
		$value    = trim( wp_unslash( $value ) );

		if ( '' === $value ) {
			unset( $credentials[ $provider ] );
		} else {
			$credentials[ $provider ] = sanitize_text_field( $value );
		}

		update_option( self::CREDENTIALS_OPTION, $credentials, false );
	}

	/**
	 * Returns a masked representation of a credential for display.
	 *
	 * Example: `sk-***************123`.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function mask_credential( string $provider ): string {
		$value = self::get_credential( $provider );

		if ( '' === $value ) {
			return '';
		}

		$length = strlen( $value );

		if ( $length <= 8 ) {
			return str_repeat( '*', $length );
		}

		$prefix = substr( $value, 0, 3 );
		$suffix = substr( $value, -3 );

		return $prefix . str_repeat( '*', max( 6, min( 15, $length - 6 ) ) ) . $suffix;
	}

	/**
	 * Splits a newline separated setting into a clean array.
	 *
	 * @param string $key Setting key.
	 * @return string[]
	 */
	public static function get_lines( string $key ): array {
		$raw = (string) self::get( $key, '' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/[\r\n]+/', $raw );
		$lines = is_array( $lines ) ? $lines : array();
		$lines = array_map( 'trim', $lines );

		return array_values( array_filter( $lines, static fn( $line ) => '' !== $line ) );
	}

	/**
	 * Clears the in-memory settings cache. Used by tests and after imports.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
