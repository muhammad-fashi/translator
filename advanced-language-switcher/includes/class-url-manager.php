<?php
/**
 * Language detection, URL rewriting and persistence.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which language the current request is in and keeps every generated
 * link inside that language.
 *
 * Directory mode works by stripping the language segment from REQUEST_URI
 * before WordPress parses the request, then filtering `home_url()` so every
 * permalink WordPress builds carries the segment again. That keeps routing,
 * pagination, search, WooCommerce endpoints and Elementor links working
 * without a parallel set of rewrite rules.
 */
class Url_Manager {

	/**
	 * Cookie used to remember an explicit choice.
	 */
	public const COOKIE = 'als_language';

	/**
	 * Query variable used by query-string mode.
	 */
	public const QUERY_VAR = 'lang';

	/**
	 * Language service.
	 *
	 * @var Language_Manager
	 */
	protected Language_Manager $languages;

	/**
	 * Resolved current language.
	 *
	 * @var Language|null
	 */
	protected ?Language $current = null;

	/**
	 * Whether detection has run.
	 *
	 * @var bool
	 */
	protected bool $resolved = false;

	/**
	 * Language code found in the URL, if any.
	 *
	 * @var string
	 */
	protected string $url_code = '';

	/**
	 * REQUEST_URI as received, before the language segment was stripped.
	 *
	 * @var string
	 */
	protected string $original_request_uri = '';

	/**
	 * Whether home_url() filtering is currently suspended.
	 *
	 * @var bool
	 */
	protected bool $suspend_filters = false;

	/**
	 * Constructor.
	 *
	 * @param Language_Manager $languages Language service.
	 */
	public function __construct( Language_Manager $languages ) {
		$this->languages            = $languages;
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
	}

	/**
	 * Registers hooks. Called very early so REQUEST_URI can still be adjusted.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->should_skip_request() ) {
			return;
		}

		$this->strip_language_segment();

		add_filter( 'home_url', array( $this, 'filter_home_url' ), 10, 4 );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_browser_language' ), 1 );
		add_action( 'init', array( $this, 'persist_cookie' ), 20 );
		add_filter( 'redirect_canonical', array( $this, 'guard_canonical' ), 10, 2 );
	}

	/**
	 * Whether URL handling should be skipped for this request.
	 *
	 * @return bool
	 */
	protected function should_skip_request(): bool {
		if ( wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		$uri = $this->original_request_uri;

		if ( '' === $uri ) {
			return true;
		}

		$skip = array( '/wp-admin', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/wp-content/', '/wp-includes/' );

		foreach ( $skip as $needle ) {
			if ( str_contains( $uri, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/* ---------------------------------------------------------------------
	 * Detection
	 * ------------------------------------------------------------------ */

	/**
	 * Removes the language segment from REQUEST_URI in directory mode so that
	 * WordPress routes the request exactly as it would in the default language.
	 *
	 * @return void
	 */
	protected function strip_language_segment(): void {
		if ( ! $this->uses_directories() ) {
			return;
		}

		$code = $this->detect_code_from_path( $this->original_request_uri );

		if ( '' === $code ) {
			return;
		}

		$this->url_code = $code;

		$home_path = $this->home_path();
		$parts     = wp_parse_url( $this->original_request_uri );
		$path      = isset( $parts['path'] ) ? $parts['path'] : '/';
		$query     = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';

		$relative = substr( $path, strlen( $home_path ) );
		$relative = ltrim( (string) $relative, '/' );
		$stripped = preg_replace( '#^' . preg_quote( $code, '#' ) . '(/|$)#', '', $relative );
		$stripped = is_string( $stripped ) ? $stripped : '';

		$new_uri = $home_path . $stripped . $query;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$_SERVER['REQUEST_URI'] = $new_uri;
	}

	/**
	 * Extracts an active language code from a URL path.
	 *
	 * @param string $url URL or path.
	 * @return string Empty string when no language segment is present.
	 */
	public function detect_code_from_path( string $url ): string {
		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		if ( '' === $path ) {
			return '';
		}

		$home_path = $this->home_path();

		if ( ! str_starts_with( $path, $home_path ) ) {
			return '';
		}

		$relative = ltrim( substr( $path, strlen( $home_path ) ), '/' );

		if ( '' === $relative ) {
			return '';
		}

		$segment = strtolower( (string) strtok( $relative, '/' ) );

		if ( '' === $segment ) {
			return '';
		}

		foreach ( $this->languages->active() as $language ) {
			if ( $language->code === $segment ) {
				return $segment;
			}
		}

		return '';
	}

	/**
	 * Resolves the language for the current request.
	 *
	 * Priority: URL, then explicit query parameter, then cookie, then browser
	 * detection, then the site default. An explicit choice always wins over
	 * browser detection.
	 *
	 * @return Language|null
	 */
	public function get_current_language(): ?Language {
		if ( $this->resolved ) {
			return $this->current;
		}

		// Detection may be triggered before `init` (for example by an early
		// home_url() call). The result is still returned, but it is only
		// memoized once pluggable functions and the user session exist.
		$this->resolved = did_action( 'init' ) > 0 && function_exists( 'is_user_logged_in' );
		$default        = $this->languages->get_default();
		$resolved       = null;

		// 1. URL segment.
		if ( '' !== $this->url_code ) {
			$resolved = $this->languages->get_by_code( $this->url_code );
		}

		// 2. Query parameter (also used as the canonical switch mechanism).
		if ( ! $resolved instanceof Language ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ) : '';

			if ( '' !== $requested && $this->languages->is_active_code( $requested ) ) {
				$resolved = $this->languages->get_by_code( $requested );
			}
		}

		// 3. Stored preference.
		if ( ! $resolved instanceof Language ) {
			$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) ) : '';

			if ( '' !== $cookie && $this->languages->is_active_code( $cookie ) ) {
				$resolved = $this->languages->get_by_code( $cookie );
			}
		}

		// 4. Logged in user preference.
		if ( ! $resolved instanceof Language && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$stored = (string) get_user_meta( get_current_user_id(), 'als_language', true );

			if ( '' !== $stored && $this->languages->is_active_code( $stored ) ) {
				$resolved = $this->languages->get_by_code( $stored );
			}
		}

		// 5. Site default.
		if ( ! $resolved instanceof Language ) {
			$resolved = $default;
		}

		/**
		 * Filters the resolved current language.
		 *
		 * @param Language|null $resolved Detected language.
		 */
		$this->current = apply_filters( 'als_current_language', $resolved );

		return $this->current;
	}

	/**
	 * Forces a language for the remainder of the request (used by AJAX/REST).
	 *
	 * @param Language|null $language Language to use.
	 * @return void
	 */
	public function set_current_language( ?Language $language ): void {
		$this->current  = $language;
		$this->resolved = true;
	}

	/**
	 * Whether the current request is in the default language.
	 *
	 * @return bool
	 */
	public function is_default_language(): bool {
		$current = $this->get_current_language();
		$default = $this->languages->get_default();

		if ( ! $current instanceof Language || ! $default instanceof Language ) {
			return true;
		}

		return $current->id === $default->id;
	}

	/**
	 * Detects the visitor's preferred language from the Accept-Language header.
	 *
	 * @return Language|null
	 */
	public function detect_browser_language(): ?Language {
		$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
			: '';

		if ( '' === $header ) {
			return null;
		}

		$candidates = array();

		foreach ( explode( ',', $header ) as $chunk ) {
			$pieces = explode( ';', trim( $chunk ) );
			$tag    = strtolower( trim( $pieces[0] ) );
			$weight = 1.0;

			if ( isset( $pieces[1] ) && str_starts_with( trim( $pieces[1] ), 'q=' ) ) {
				$weight = (float) substr( trim( $pieces[1] ), 2 );
			}

			if ( '' !== $tag ) {
				$candidates[ $tag ] = $weight;
			}
		}

		arsort( $candidates );

		$active = $this->languages->active();

		foreach ( array_keys( $candidates ) as $tag ) {
			$primary = strtok( $tag, '-' );

			foreach ( $active as $language ) {
				$locale = strtolower( str_replace( '_', '-', $language->locale ) );

				if ( $locale === $tag || $language->code === $tag || $language->code === $primary ) {
					return $language;
				}
			}
		}

		return null;
	}

	/**
	 * Redirects first-time visitors to their browser language when enabled.
	 *
	 * A visitor who already made a choice (cookie or explicit URL) is never
	 * redirected.
	 *
	 * @return void
	 */
	public function maybe_redirect_browser_language(): void {
		if ( ! Settings::is_enabled( 'browser_detection' ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || is_robots() || is_feed() || is_404() ) {
			return;
		}

		// Respect explicit choices.
		if ( '' !== $this->url_code || isset( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// Search engines must see the URL they requested.
		if ( $this->is_bot() ) {
			return;
		}

		$detected = $this->detect_browser_language();

		if ( ! $detected instanceof Language ) {
			return;
		}

		$default = $this->languages->get_default();

		if ( $default instanceof Language && $detected->id === $default->id ) {
			return;
		}

		$target = $this->get_language_url( $detected, $this->current_url() );

		if ( '' === $target || $target === $this->current_url() ) {
			return;
		}

		nocache_headers();
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * Crude bot sniffing used only to skip the browser-language redirect.
	 *
	 * @return bool
	 */
	protected function is_bot(): bool {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) )
			: '';

		if ( '' === $agent ) {
			return true;
		}

		foreach ( array( 'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'lighthouse', 'preview' ) as $needle ) {
			if ( str_contains( $agent, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stores the resolved language in a cookie so it survives navigation even
	 * in cookie mode, and for cross-domain-free persistence in other modes.
	 *
	 * @return void
	 */
	public function persist_cookie(): void {
		if ( headers_sent() || is_admin() || wp_doing_ajax() ) {
			return;
		}

		$current = $this->get_current_language();

		if ( ! $current instanceof Language ) {
			return;
		}

		$existing = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) ) : '';

		if ( $existing === $current->code ) {
			return;
		}

		$this->set_cookie( $current->code );
	}

	/**
	 * Writes the language cookie.
	 *
	 * @param string $code Language code.
	 * @return void
	 */
	public function set_cookie( string $code ): void {
		$days = max( 1, Settings::get_int( 'cookie_lifetime_days', 30 ) );

		$_COOKIE[ self::COOKIE ] = $code;

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$code,
			array(
				'expires'  => time() + ( $days * DAY_IN_SECONDS ),
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false, // The front end script reads it to sync switchers.
				'samesite' => 'Lax',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * URL building
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the configured mode puts the language in the path.
	 *
	 * @return bool
	 */
	public function uses_directories(): bool {
		$mode = (string) Settings::get( 'url_mode', 'directory' );

		return in_array( $mode, array( 'directory', 'directory_all' ), true );
	}

	/**
	 * Whether the default language also gets a prefix.
	 *
	 * @return bool
	 */
	public function prefixes_default(): bool {
		if ( 'directory_all' === (string) Settings::get( 'url_mode', 'directory' ) ) {
			return true;
		}

		return ! Settings::is_enabled( 'hide_default_prefix' );
	}

	/**
	 * The path portion of home_url(), always with a trailing slash.
	 *
	 * @return string
	 */
	public function home_path(): string {
		static $path = null;

		if ( null !== $path ) {
			return $path;
		}

		$this->suspend_filters = true;
		$home                  = home_url( '/' );
		$this->suspend_filters = false;

		$parsed = wp_parse_url( $home );
		$path   = isset( $parsed['path'] ) && '' !== $parsed['path'] ? $parsed['path'] : '/';
		$path   = '/' . ltrim( $path, '/' );
		$path   = rtrim( $path, '/' ) . '/';

		return $path;
	}

	/**
	 * Prefixes every generated URL with the active language segment.
	 *
	 * @param string      $url     Generated URL.
	 * @param string      $path    Requested path.
	 * @param string|null $scheme  URL scheme.
	 * @param int|null    $blog_id Blog id.
	 * @return string
	 */
	public function filter_home_url( $url, $path = '', $scheme = null, $blog_id = null ): string {
		$url = (string) $url;

		if ( $this->suspend_filters || is_admin() || ! $this->uses_directories() ) {
			return $url;
		}

		// Never touch infrastructure endpoints.
		if ( is_string( $path ) && preg_match( '#^/?(wp-json|wp-admin|wp-login|wp-content|wp-includes|feed)#', ltrim( $path, '/' ) ) ) {
			return $url;
		}

		if ( in_array( $scheme, array( 'rest', 'rss', 'rss2', 'atom', 'rdf', 'json' ), true ) ) {
			return $url;
		}

		$current = $this->get_current_language();

		if ( ! $current instanceof Language ) {
			return $url;
		}

		if ( $this->is_default_language() && ! $this->prefixes_default() ) {
			return $url;
		}

		return $this->add_prefix( $url, $current->code );
	}

	/**
	 * Adds a language segment to a URL, replacing any existing one.
	 *
	 * @param string $url  Absolute or relative URL.
	 * @param string $code Language code.
	 * @return string
	 */
	public function add_prefix( string $url, string $code ): string {
		$url = $this->remove_prefix( $url );

		if ( '' === $code ) {
			return $url;
		}

		$parts = wp_parse_url( $url );

		if ( false === $parts ) {
			return $url;
		}

		$path      = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$home_path = $this->home_path();

		if ( ! str_starts_with( $path, $home_path ) ) {
			// Not a URL we own (external link, different install path).
			if ( '/' !== $home_path ) {
				return $url;
			}

			$path = '/' . ltrim( $path, '/' );
		}

		$relative = ltrim( substr( $path, strlen( $home_path ) ), '/' );
		$new_path = $home_path . $code . ( '' !== $relative ? '/' . $relative : '/' );

		return $this->rebuild( $parts, $new_path );
	}

	/**
	 * Strips any language segment from a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function remove_prefix( string $url ): string {
		$code = $this->detect_code_from_path( $url );

		if ( '' === $code ) {
			return $url;
		}

		$parts     = wp_parse_url( $url );
		$path      = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$home_path = $this->home_path();
		$relative  = ltrim( substr( $path, strlen( $home_path ) ), '/' );
		$stripped  = (string) preg_replace( '#^' . preg_quote( $code, '#' ) . '(/|$)#', '', $relative );

		return $this->rebuild( is_array( $parts ) ? $parts : array(), $home_path . $stripped );
	}

	/**
	 * Reassembles a parsed URL with a new path.
	 *
	 * @param array<string,mixed> $parts wp_parse_url() output.
	 * @param string              $path  Replacement path.
	 * @return string
	 */
	protected function rebuild( array $parts, string $path ): string {
		$url = '';

		if ( ! empty( $parts['scheme'] ) ) {
			$url .= $parts['scheme'] . '://';
		} elseif ( ! empty( $parts['host'] ) ) {
			$url .= '//';
		}

		if ( ! empty( $parts['user'] ) ) {
			$url .= $parts['user'];
			$url .= ! empty( $parts['pass'] ) ? ':' . $parts['pass'] : '';
			$url .= '@';
		}

		if ( ! empty( $parts['host'] ) ) {
			$url .= $parts['host'];
		}

		if ( ! empty( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}

		$url .= $path;

		if ( ! empty( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}

		if ( ! empty( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}

		return $url;
	}

	/**
	 * Returns the equivalent of a URL in another language.
	 *
	 * The visitor stays on the page they were reading; only the language
	 * marker changes.
	 *
	 * @param Language $language Target language.
	 * @param string   $url      Source URL, defaults to the current request.
	 * @return string
	 */
	public function get_language_url( Language $language, string $url = '' ): string {
		$url = '' !== $url ? $url : $this->current_url();

		// Always start from a clean, language-free URL.
		$url = $this->remove_prefix( $url );
		$url = remove_query_arg( self::QUERY_VAR, $url );

		$default    = $this->languages->get_default();
		$is_default = $default instanceof Language && $default->id === $language->id;

		if ( $this->uses_directories() ) {
			if ( ! $is_default || $this->prefixes_default() ) {
				$url = $this->add_prefix( $url, $language->code );
			}
		} elseif ( 'query' === (string) Settings::get( 'url_mode', 'directory' ) ) {
			if ( ! $is_default ) {
				$url = add_query_arg( self::QUERY_VAR, $language->code, $url );
			}
		} else {
			// Cookie mode: the switch is a query parameter that the front end
			// consumes once, then the cookie carries the choice.
			$url = add_query_arg( self::QUERY_VAR, $language->code, $url );
		}

		/**
		 * Filters a language specific URL.
		 *
		 * @param string   $url      Target URL.
		 * @param Language $language Target language.
		 */
		return (string) apply_filters( 'als_language_url', $url, $language );
	}

	/**
	 * Absolute URL of the current request, in its original (prefixed) form.
	 *
	 * @return string
	 */
	public function current_url(): string {
		$this->suspend_filters = true;
		$home                  = home_url( '/' );
		$this->suspend_filters = false;

		$parsed = wp_parse_url( $home );
		$base   = ( $parsed['scheme'] ?? ( is_ssl() ? 'https' : 'http' ) ) . '://' . ( $parsed['host'] ?? '' );

		if ( ! empty( $parsed['port'] ) ) {
			$base .= ':' . $parsed['port'];
		}

		$uri = '' !== $this->original_request_uri ? $this->original_request_uri : '/';

		return $base . $uri;
	}

	/**
	 * Alternate URLs for every active language, used for hreflang tags and the
	 * switcher markup.
	 *
	 * @param string $url Source URL, defaults to the current request.
	 * @return array<string,array{language:Language,url:string}>
	 */
	public function get_alternate_urls( string $url = '' ): array {
		$url         = '' !== $url ? $url : $this->current_url();
		$alternates  = array();

		foreach ( $this->languages->active() as $language ) {
			$alternates[ $language->code ] = array(
				'language' => $language,
				'url'      => $this->get_language_url( $language, $url ),
			);
		}

		/**
		 * Filters the alternate URL map.
		 *
		 * @param array<string,array{language:Language,url:string}> $alternates Alternates.
		 * @param string                                            $url        Source URL.
		 */
		return apply_filters( 'als_alternate_urls', $alternates, $url );
	}

	/**
	 * Registers the `lang` query variable.
	 *
	 * @param string[] $vars Registered query vars.
	 * @return string[]
	 */
	public function register_query_var( $vars ): array {
		$vars   = is_array( $vars ) ? $vars : array();
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Stops WordPress from canonical-redirecting a language URL back to the
	 * unprefixed one when the two only differ by the language segment.
	 *
	 * @param string|false $redirect_url  Proposed redirect.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function guard_canonical( $redirect_url, $requested_url ) {
		if ( ! $redirect_url || ! $this->uses_directories() ) {
			return $redirect_url;
		}

		if ( $this->remove_prefix( (string) $redirect_url ) === $this->remove_prefix( (string) $requested_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * The language segment currently present in the URL, if any.
	 *
	 * @return string
	 */
	public function get_url_code(): string {
		return $this->url_code;
	}

	/**
	 * The unmodified REQUEST_URI captured before stripping.
	 *
	 * @return string
	 */
	public function get_original_request_uri(): string {
		return $this->original_request_uri;
	}
}
