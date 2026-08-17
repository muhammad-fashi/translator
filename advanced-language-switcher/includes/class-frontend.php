<?php
/**
 * Front end language rendering.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the active language to everything the visitor sees.
 *
 * Three complementary layers are used, in increasing order of coverage:
 *
 * 1. `determine_locale` switches WordPress, the theme and every plugin to the
 *    target locale, so their own translation catalogues are used.
 * 2. The gettext filters resolve strings that have no catalogue entry from
 *    the plugin's own translation memory.
 * 3. A full page pass rewrites the remaining human readable text, which is
 *    what covers Elementor content, menus, WooCommerce templates and any
 *    theme output that never went through gettext.
 */
class Frontend {

	/**
	 * Plugin container.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Whether the whole page buffer is active.
	 *
	 * @var bool
	 */
	protected bool $buffering = false;

	/**
	 * Recursion guard for the gettext filters.
	 *
	 * @var bool
	 */
	protected bool $in_gettext = false;

	/**
	 * Memoized gettext lookups.
	 *
	 * @var array<string,string>
	 */
	protected array $gettext_memo = array();

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
		add_shortcode( 'als_language_switcher', array( $this, 'shortcode' ) );

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		add_filter( 'determine_locale', array( $this, 'filter_locale' ), 20 );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 20 );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'locale_stylesheet_uri', array( $this, 'filter_locale_stylesheet' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'print_inline_state' ), 1 );
		add_action( 'wp_print_styles', array( $this, 'print_rtl_overrides' ), 100 );

		add_action( 'template_redirect', array( $this, 'start_buffer' ), 5 );
		add_action( 'shutdown', array( $this, 'flush_buffer' ), 0 );

		// Theme and plugin strings.
		if ( Settings::is_enabled( 'translate_theme' ) ) {
			add_filter( 'gettext', array( $this, 'filter_gettext' ), 20, 3 );
			add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 20, 4 );
			add_filter( 'ngettext', array( $this, 'filter_ngettext' ), 20, 5 );
		}

		// Menus.
		if ( Settings::is_enabled( 'translate_menus' ) ) {
			add_filter( 'wp_nav_menu_objects', array( $this, 'filter_menu_objects' ), 20 );
			add_filter( 'wp_setup_nav_menu_item', array( $this, 'filter_menu_item' ), 20 );
		}

		// Widgets.
		if ( Settings::is_enabled( 'translate_widgets' ) ) {
			add_filter( 'widget_title', array( $this, 'filter_text' ), 20 );
			add_filter( 'widget_text', array( $this, 'filter_html' ), 20 );
			add_filter( 'widget_block_content', array( $this, 'filter_html' ), 20 );
		}

		// Contexts where no page buffer exists (feeds, REST, AJAX fragments).
		add_filter( 'the_title', array( $this, 'filter_unbuffered_text' ), 20 );
		add_filter( 'the_content', array( $this, 'filter_unbuffered_html' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_unbuffered_html' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 20 );

		add_action( 'shutdown', array( $this, 'store_discovered_strings' ), 20 );
	}

	/* ---------------------------------------------------------------------
	 * Language context
	 * ------------------------------------------------------------------ */

	/**
	 * Whether this request belongs to the WordPress admin.
	 *
	 * The plugin translates the public website only; wp-admin always stays in
	 * the site's own language. Ordinary admin screens are easy to detect, but
	 * admin-ajax and REST need more care: `is_admin()` is true for every
	 * admin-ajax request including the ones fired from the front end (such as
	 * WooCommerce add-to-cart), and false for every REST request including the
	 * ones the block editor makes. The referer is what actually distinguishes
	 * them, so it decides here.
	 *
	 * @return bool
	 */
	public function is_admin_request(): bool {
		$is_ajax = wp_doing_ajax();
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( is_admin() && ! $is_ajax ) {
			return true;
		}

		if ( ! $is_ajax && ! $is_rest ) {
			return false;
		}

		$referer = wp_get_referer();

		if ( ! is_string( $referer ) || '' === $referer ) {
			// No usable referer. For admin-ajax the safe assumption is "admin",
			// because leaving output untranslated is never destructive.
			return is_admin();
		}

		$admin_path = wp_parse_url( admin_url( '/' ), PHP_URL_PATH );
		$admin_path = is_string( $admin_path ) && '' !== $admin_path ? $admin_path : '/wp-admin/';

		return str_contains( $referer, $admin_path );
	}

	/**
	 * Returns the language for the current request, or null when no
	 * translation should be applied.
	 *
	 * This is the single choke point every content filter goes through, so
	 * returning null here reliably switches the whole translation layer off.
	 *
	 * @return Language|null
	 */
	protected function target_language(): ?Language {
		if ( $this->is_admin_request() ) {
			return null;
		}

		$router = $this->plugin->router();

		if ( $router->is_default_language() ) {
			return null;
		}

		return $router->get_current_language();
	}

	/**
	 * Switches WordPress to the locale of the active language.
	 *
	 * @param string $locale Current locale.
	 * @return string
	 */
	public function filter_locale( $locale ): string {
		$locale = (string) $locale;

		// Never switch the locale of an admin screen: the dashboard stays in
		// the language the site owner configured in WordPress itself.
		if ( $this->is_admin_request() ) {
			return $locale;
		}

		$current = $this->plugin->router()->get_current_language();

		if ( ! $current instanceof Language || '' === $current->locale ) {
			return $locale;
		}

		return $current->locale;
	}

	/**
	 * Sets the lang and dir attributes on the html element.
	 *
	 * @param string $output Current attribute string.
	 * @return string
	 */
	public function filter_language_attributes( $output ): string {
		$current = $this->plugin->router()->get_current_language();

		if ( ! $current instanceof Language ) {
			return (string) $output;
		}

		$attributes = array( 'lang="' . esc_attr( $current->html_lang() ) . '"' );

		if ( Settings::is_enabled( 'rtl_support' ) ) {
			$attributes[] = 'dir="' . esc_attr( $current->direction ) . '"';
		}

		// Preserve anything the theme added that we are not replacing.
		$extra = preg_replace( '/\b(lang|dir)\s*=\s*(["\']).*?\2/i', '', (string) $output );
		$extra = trim( (string) $extra );

		if ( '' !== $extra ) {
			$attributes[] = $extra;
		}

		return implode( ' ', $attributes );
	}

	/**
	 * Adds language and direction classes to the body element.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function filter_body_class( $classes ): array {
		$classes = is_array( $classes ) ? $classes : array();
		$current = $this->plugin->router()->get_current_language();

		if ( ! $current instanceof Language ) {
			return $classes;
		}

		$classes[] = 'als-language';
		$classes[] = 'als-language-' . sanitize_html_class( $current->code );

		if ( $current->is_rtl() && Settings::is_enabled( 'rtl_support' ) ) {
			$classes[] = 'als-rtl';

			if ( ! in_array( 'rtl', $classes, true ) ) {
				$classes[] = 'rtl';
			}
		}

		return $classes;
	}

	/**
	 * Points WordPress at the theme's rtl.css when the language is RTL.
	 *
	 * @param string $uri Stylesheet URI.
	 * @return string
	 */
	public function filter_locale_stylesheet( $uri ): string {
		$current = $this->plugin->router()->get_current_language();

		if ( ! $current instanceof Language || ! $current->is_rtl() || ! Settings::is_enabled( 'rtl_support' ) ) {
			return (string) $uri;
		}

		if ( '' !== (string) $uri ) {
			return (string) $uri;
		}

		$rtl = get_template_directory() . '/rtl.css';

		if ( file_exists( $rtl ) ) {
			return get_template_directory_uri() . '/rtl.css';
		}

		return (string) $uri;
	}

	/* ---------------------------------------------------------------------
	 * Whole page translation
	 * ------------------------------------------------------------------ */

	/**
	 * Starts the output buffer that rewrites the rendered page.
	 *
	 * @return void
	 */
	public function start_buffer(): void {
		if ( ! $this->should_buffer() ) {
			return;
		}

		$this->buffering = true;

		ob_start( array( $this, 'process_buffer' ) );
	}

	/**
	 * Flushes the buffer if it is still open at shutdown.
	 *
	 * @return void
	 */
	public function flush_buffer(): void {
		if ( ! $this->buffering || ob_get_level() < 1 ) {
			return;
		}

		$this->buffering = false;

		// PHP flushes remaining buffers itself, but closing ours explicitly
		// guarantees the callback runs before other shutdown handlers print.
		@ob_end_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Whether the current request should be rewritten.
	 *
	 * @return bool
	 */
	protected function should_buffer(): bool {
		if ( ! $this->target_language() instanceof Language ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Never interfere with the Elementor editor or its preview iframe.
		if ( $this->is_elementor_editing() ) {
			return false;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		/**
		 * Filters whether the full page translation pass runs.
		 *
		 * @param bool $should_buffer Current decision.
		 */
		return (bool) apply_filters( 'als_should_translate_page', true );
	}

	/**
	 * Whether Elementor is currently editing or previewing.
	 *
	 * @return bool
	 */
	protected function is_elementor_editing(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) {
			return true;
		}

		if ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) {
			return true;
		}
		// phpcs:enable

		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}

		$elementor = \Elementor\Plugin::instance();

		if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}

		if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}

		return false;
	}

	/**
	 * Rewrites the buffered page.
	 *
	 * Any failure here returns the original buffer untouched: a translation
	 * problem must never blank out a page.
	 *
	 * @param string $buffer Rendered page.
	 * @return string
	 */
	public function process_buffer( $buffer ): string {
		$buffer = (string) $buffer;

		if ( '' === trim( $buffer ) ) {
			return $buffer;
		}

		$language = $this->target_language();

		if ( ! $language instanceof Language ) {
			return $buffer;
		}

		// Only rewrite HTML documents.
		if ( ! preg_match( '/^\s*(<!doctype|<html|<\!--)/i', $buffer ) && ! str_contains( $buffer, '</body>' ) ) {
			return $buffer;
		}

		try {
			$cache_key = '';

			if ( $this->page_cache_allowed() ) {
				$cache_key = $this->plugin->cache()->key(
					'page',
					$language->code . '|' . $this->plugin->router()->current_url()
				);

				$cached = $this->plugin->cache()->get( $cache_key, 'page' );

				if ( is_string( $cached ) && '' !== $cached ) {
					return $cached;
				}
			}

			$translator = new Html_Translator( $this->plugin->translations(), $language );
			$translator->set_discovery( $this->discovery_allowed() );

			$translated = $translator->translate( $buffer );

			$this->discovered = $translator->get_discovered();

			if ( '' === trim( $translated ) ) {
				return $buffer;
			}

			if ( '' !== $cache_key ) {
				$this->plugin->cache()->set(
					$cache_key,
					$translated,
					'page',
					Settings::get_int( 'page_cache_ttl', 3600 ),
					$language->id
				);
			}

			/**
			 * Filters the fully translated page.
			 *
			 * @param string   $translated Translated markup.
			 * @param string   $buffer     Original markup.
			 * @param Language $language   Target language.
			 */
			return (string) apply_filters( 'als_translated_page', $translated, $buffer, $language );
		} catch ( \Throwable $error ) {
			$this->plugin->logger()->error(
				'Page translation failed: ' . $error->getMessage(),
				array( 'language' => $language->code )
			);

			return $buffer;
		}
	}

	/**
	 * Strings discovered during the page pass.
	 *
	 * @var array<string,string>
	 */
	protected array $discovered = array();

	/**
	 * Whether new strings may be recorded on this request.
	 *
	 * @return bool
	 */
	protected function discovery_allowed(): bool {
		if ( ! Settings::is_enabled( 'auto_translate_new' ) ) {
			return false;
		}

		if ( is_user_logged_in() && Security::can_translate() ) {
			return true;
		}

		// Throttle anonymous discovery so a traffic spike cannot turn into a
		// write storm on the strings table.
		$lock = 'als_discovery_' . md5( $this->plugin->router()->current_url() );

		if ( get_transient( $lock ) ) {
			return false;
		}

		set_transient( $lock, 1, 15 * MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Persists strings discovered while rendering, after the response is sent.
	 *
	 * @return void
	 */
	public function store_discovered_strings(): void {
		if ( ! $this->discovered ) {
			return;
		}

		$discovered       = $this->discovered;
		$this->discovered = array();

		$manager = $this->plugin->translations();
		$context = 'frontend';

		foreach ( $discovered as $text ) {
			$manager->register_string(
				$text,
				'',
				array(
					'object_type'     => $context,
					'source_location' => $this->plugin->router()->get_original_request_uri(),
				)
			);
		}
	}

	/**
	 * Whether the rendered page may be cached.
	 *
	 * @return bool
	 */
	protected function page_cache_allowed(): bool {
		if ( ! Settings::is_enabled( 'page_cache_enabled' ) || ! Settings::is_enabled( 'cache_enabled' ) ) {
			return false;
		}

		if ( is_user_logged_in() || is_preview() || is_search() || is_404() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_POST ) ) {
			return false;
		}

		if ( $this->plugin->woocommerce()->is_dynamic_page() ) {
			return false;
		}

		/**
		 * Filters whether the translated page may be cached.
		 *
		 * @param bool $allowed Current decision.
		 */
		return (bool) apply_filters( 'als_page_cache_allowed', true );
	}

	/* ---------------------------------------------------------------------
	 * gettext
	 * ------------------------------------------------------------------ */

	/**
	 * Resolves a gettext string through the translation memory.
	 *
	 * @param string $translation Locale catalogue result.
	 * @param string $text        Original string.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function filter_gettext( $translation, $text, $domain ): string {
		return $this->resolve_gettext( (string) $translation, (string) $text, (string) $domain );
	}

	/**
	 * Resolves a gettext string that carries a context.
	 *
	 * @param string $translation Locale catalogue result.
	 * @param string $text        Original string.
	 * @param string $context     gettext context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function filter_gettext_with_context( $translation, $text, $context, $domain ): string {
		return $this->resolve_gettext( (string) $translation, (string) $text, (string) $domain, (string) $context );
	}

	/**
	 * Resolves a plural gettext string.
	 *
	 * @param string $translation Locale catalogue result.
	 * @param string $single      Singular form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Count.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function filter_ngettext( $translation, $single, $plural, $number, $domain ): string {
		$source = ( (int) $number === 1 ) ? (string) $single : (string) $plural;

		return $this->resolve_gettext( (string) $translation, $source, (string) $domain );
	}

	/**
	 * Shared gettext resolution.
	 *
	 * @param string $translation Catalogue result.
	 * @param string $source      Original string.
	 * @param string $domain      Text domain.
	 * @param string $context     Optional gettext context.
	 * @return string
	 */
	protected function resolve_gettext( string $translation, string $source, string $domain, string $context = '' ): string {
		if ( $this->in_gettext || 'advanced-language-switcher' === $domain ) {
			return $translation;
		}

		$language = $this->target_language();

		if ( ! $language instanceof Language ) {
			return $translation;
		}

		$key = $language->code . '|' . $domain . '|' . $context . '|' . $translation;

		if ( isset( $this->gettext_memo[ $key ] ) ) {
			return $this->gettext_memo[ $key ];
		}

		$this->in_gettext = true;

		$manager = $this->plugin->translations();

		// The catalogue result is tried first: if a language pack already
		// translated the string, a site-specific override still wins.
		$result = $manager->lookup( $translation, $language, 'gettext:' . $domain );

		if ( null === $result ) {
			$result = $manager->lookup( $translation, $language );
		}

		if ( null === $result && $source !== $translation ) {
			$result = $manager->lookup( $source, $language );
		}

		$this->in_gettext = false;

		$final = ( is_string( $result ) && '' !== $result ) ? $result : $translation;

		$this->gettext_memo[ $key ] = $final;

		return $final;
	}

	/* ---------------------------------------------------------------------
	 * Menus, widgets and content
	 * ------------------------------------------------------------------ */

	/**
	 * Translates menu item titles and descriptions.
	 *
	 * @param array<int,object> $items Menu items.
	 * @return array<int,object>
	 */
	public function filter_menu_objects( $items ): array {
		$items    = is_array( $items ) ? $items : array();
		$language = $this->target_language();

		if ( ! $language instanceof Language ) {
			return $items;
		}

		foreach ( $items as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			if ( ! empty( $item->title ) ) {
				$item->title = $this->plugin->translations()->translate(
					(string) $item->title,
					$language,
					'menu'
				);
			}

			if ( ! empty( $item->attr_title ) ) {
				$item->attr_title = $this->plugin->translations()->translate( (string) $item->attr_title, $language, 'menu' );
			}

			if ( ! empty( $item->description ) ) {
				$item->description = $this->plugin->translations()->translate( (string) $item->description, $language, 'menu' );
			}
		}

		return $items;
	}

	/**
	 * Translates a single menu item as it is set up.
	 *
	 * @param object $item Menu item.
	 * @return object
	 */
	public function filter_menu_item( $item ) {
		$language = $this->target_language();

		if ( ! $language instanceof Language || ! is_object( $item ) || empty( $item->title ) ) {
			return $item;
		}

		$item->title = $this->plugin->translations()->translate( (string) $item->title, $language, 'menu' );

		return $item;
	}

	/**
	 * Translates a plain text value.
	 *
	 * @param mixed $text Value.
	 * @return mixed
	 */
	public function filter_text( $text ) {
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return $text;
		}

		$language = $this->target_language();

		if ( ! $language instanceof Language ) {
			return $text;
		}

		return $this->plugin->translations()->translate( $text, $language );
	}

	/**
	 * Translates an HTML fragment.
	 *
	 * @param mixed $html Value.
	 * @return mixed
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}

		$language = $this->target_language();

		if ( ! $language instanceof Language ) {
			return $html;
		}

		$translator = new Html_Translator( $this->plugin->translations(), $language );

		return $translator->translate( $html );
	}

	/**
	 * Translates text only when no page buffer will do it later.
	 *
	 * @param mixed $text Value.
	 * @return mixed
	 */
	public function filter_unbuffered_text( $text ) {
		if ( $this->buffering ) {
			return $text;
		}

		return $this->filter_text( $text );
	}

	/**
	 * Translates markup only when no page buffer will do it later.
	 *
	 * @param mixed $html Value.
	 * @return mixed
	 */
	public function filter_unbuffered_html( $html ) {
		if ( $this->buffering ) {
			return $html;
		}

		return $this->filter_html( $html );
	}

	/**
	 * Translates the document title parts.
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function filter_title_parts( $parts ): array {
		$parts    = is_array( $parts ) ? $parts : array();
		$language = $this->target_language();

		if ( ! $language instanceof Language ) {
			return $parts;
		}

		foreach ( $parts as $key => $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$parts[ $key ] = $this->plugin->translations()->translate( $value, $language, 'seo:title' );
			}
		}

		return $parts;
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Registers and enqueues the front end assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$languages = $this->plugin->languages()->active();

		wp_register_style(
			'als-language-switcher',
			ALS_PLUGIN_URL . 'frontend/css/language-switcher.css',
			array(),
			ALS_VERSION
		);

		wp_register_script(
			'als-language-switcher',
			ALS_PLUGIN_URL . 'frontend/js/language-switcher.js',
			array(),
			ALS_VERSION,
			true
		);

		if ( count( $languages ) < 2 && ! Settings::is_enabled( 'load_assets_everywhere' ) ) {
			return;
		}

		wp_enqueue_style( 'als-language-switcher' );
		wp_enqueue_script( 'als-language-switcher' );

		wp_localize_script( 'als-language-switcher', 'ALS_DATA', $this->script_data() );
	}

	/**
	 * Builds the data object handed to the front end script.
	 *
	 * Nothing sensitive is exposed here: no API keys, no internal ids beyond
	 * the language ids that already appear in public URLs.
	 *
	 * @return array<string,mixed>
	 */
	public function script_data(): array {
		$router  = $this->plugin->router();
		$current = $router->get_current_language();
		$default = $this->plugin->languages()->get_default();

		$languages = array();
		$urls      = array();

		foreach ( $router->get_alternate_urls() as $code => $alternate ) {
			/** @var Language $language */
			$language = $alternate['language'];

			$languages[] = array(
				'code'      => $language->code,
				'name'      => $language->name,
				'native'    => $language->display_name(),
				'flag'      => $language->flag,
				'direction' => $language->direction,
				'htmlLang'  => $language->html_lang(),
				'url'       => $alternate['url'],
			);

			$urls[ $code ] = $alternate['url'];
		}

		$data = array(
			'currentLanguage' => $current instanceof Language ? $current->code : '',
			'defaultLanguage' => $default instanceof Language ? $default->code : '',
			'isRtl'           => $current instanceof Language && $current->is_rtl(),
			'htmlLang'        => $current instanceof Language ? $current->html_lang() : '',
			'languages'       => $languages,
			'urls'            => $urls,
			'cookieName'      => Url_Manager::COOKIE,
			'cookieDays'      => Settings::get_int( 'cookie_lifetime_days', 30 ),
			'queryVar'        => Url_Manager::QUERY_VAR,
			'behaviour'       => (string) Settings::get( 'switcher_behaviour', 'navigate' ),
			'ajaxEnabled'     => Settings::is_enabled( 'ajax_switching' ),
			'restUrl'         => esc_url_raw( rest_url( Rest_Api::NAMESPACE . '/' ) ),
			'nonce'           => Security::create_public_nonce(),
		);

		/**
		 * Filters the data exposed to the front end script.
		 *
		 * @param array<string,mixed> $data Script data.
		 */
		return apply_filters( 'als_script_data', $data );
	}

	/**
	 * Prints the global state object early so switchers can read it before the
	 * deferred script loads.
	 *
	 * @return void
	 */
	public function print_inline_state(): void {
		if ( count( $this->plugin->languages()->active() ) < 2 ) {
			return;
		}

		$state = $this->script_data();
		$data  = wp_json_encode( $state );

		if ( ! is_string( $data ) ) {
			return;
		}

		printf(
			"<script id=\"als-state\">window.ALS_DATA=%s;window.ALS=window.ALS||{};window.ALS.currentLanguage=%s;</script>\n",
			$data, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output.
			wp_json_encode( $state['currentLanguage'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Prints administrator supplied RTL overrides.
	 *
	 * @return void
	 */
	public function print_rtl_overrides(): void {
		$current = $this->plugin->router()->get_current_language();

		if ( ! $current instanceof Language || ! $current->is_rtl() ) {
			return;
		}

		if ( ! Settings::is_enabled( 'rtl_support' ) ) {
			return;
		}

		$css = trim( (string) Settings::get( 'custom_rtl_css', '' ) );

		if ( '' === $css ) {
			return;
		}

		printf(
			"<style id=\"als-rtl-overrides\">%s</style>\n",
			wp_strip_all_tags( $css ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/* ---------------------------------------------------------------------
	 * Shortcode
	 * ------------------------------------------------------------------ */

	/**
	 * Renders `[als_language_switcher]`.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'preset'       => (string) Settings::get( 'switcher_preset', 'compact-pill' ),
				'layout'       => (string) Settings::get( 'switcher_layout', 'pills' ),
				'display'      => (string) Settings::get( 'switcher_display', 'code' ),
				'flag_style'   => (string) Settings::get( 'switcher_flag_style', 'emoji' ),
				'flag_shape'   => 'rounded',
				'behaviour'    => (string) Settings::get( 'switcher_behaviour', 'navigate' ),
				'animation'    => (string) Settings::get( 'switcher_animation', 'fade' ),
				'hide_current' => '',
				'languages'    => '',
				'class'        => '',
			),
			is_array( $atts ) ? $atts : array(),
			'als_language_switcher'
		);

		wp_enqueue_style( 'als-language-switcher' );
		wp_enqueue_script( 'als-language-switcher' );

		$languages = '' !== $atts['languages']
			? array_filter( array_map( 'trim', explode( ',', (string) $atts['languages'] ) ) )
			: array();

		return Switcher::render(
			array(
				'preset'       => (string) $atts['preset'],
				'layout'       => (string) $atts['layout'],
				'display'      => (string) $atts['display'],
				'flag_style'   => (string) $atts['flag_style'],
				'flag_shape'   => (string) $atts['flag_shape'],
				'behaviour'    => (string) $atts['behaviour'],
				'animation'    => (string) $atts['animation'],
				'hide_current' => ! empty( $atts['hide_current'] ) && 'false' !== $atts['hide_current'],
				'languages'    => $languages,
				'extra_class'  => (string) $atts['class'],
			)
		);
	}
}
