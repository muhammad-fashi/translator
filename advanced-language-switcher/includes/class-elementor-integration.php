<?php
/**
 * Elementor integration.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the widget category and the Language Switcher widget, and makes
 * sure Elementor rendered content (including Theme Builder headers, footers,
 * popups and loop templates) is translated.
 *
 * Every hook here is guarded: with Elementor absent or deactivated the plugin
 * keeps working and only the widget is unavailable.
 */
class Elementor_Integration {

	/**
	 * Elementor widget category slug.
	 */
	public const CATEGORY = 'als-language-translator';

	/**
	 * Minimum supported Elementor version.
	 */
	public const MIN_ELEMENTOR_VERSION = '3.0.0';

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
	 * Whether Elementor is installed and active.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return did_action( 'elementor/loaded' ) > 0 || class_exists( '\\Elementor\\Plugin' );
	}

	/**
	 * Whether Elementor Pro is active.
	 *
	 * @return bool
	 */
	public function has_pro(): bool {
		return class_exists( '\\ElementorPro\\Plugin' );
	}

	/**
	 * The installed Elementor version, or an empty string.
	 *
	 * @return string
	 */
	public function version(): string {
		return defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '';
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_assets' ) );

		// Content rendered by Elementor outside the main loop (templates,
		// popups, loop items) still goes through the translation pass.
		add_filter( 'elementor/frontend/the_content', array( $this, 'translate_rendered_content' ), 20 );
		add_filter( 'elementor/widget/render_content', array( $this, 'translate_widget_content' ), 20, 2 );

		// Keep the translation memory current when an Elementor page is saved.
		add_action( 'elementor/document/after_save', array( $this, 'on_document_saved' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'maybe_show_missing_notice' ) );
		}
	}

	/**
	 * Adds the plugin's own widget category.
	 *
	 * @param \Elementor\Elements_Manager $manager Elements manager.
	 * @return void
	 */
	public function register_category( $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_category' ) ) {
			return;
		}

		$manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Language Translator', 'advanced-language-switcher' ),
				'icon'  => 'eicon-globe',
			)
		);
	}

	/**
	 * Registers the Language Switcher widget.
	 *
	 * @param \Elementor\Widgets_Manager $manager Widgets manager.
	 * @return void
	 */
	public function register_widget( $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}

		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			return;
		}

		require_once ALS_PLUGIN_DIR . 'elementor/class-language-switcher-widget.php';

		$manager->register( new Elementor\Language_Switcher_Widget() );
	}

	/**
	 * Registers the switcher assets with Elementor's frontend.
	 *
	 * @return void
	 */
	public function register_frontend_assets(): void {
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
	}

	/**
	 * Loads the editor helper script that powers the live preview template.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'als-elementor-editor',
			ALS_PLUGIN_URL . 'elementor/assets/editor.css',
			array(),
			ALS_VERSION
		);

		wp_enqueue_script(
			'als-elementor-editor',
			ALS_PLUGIN_URL . 'elementor/assets/editor.js',
			array( 'jquery' ),
			ALS_VERSION,
			true
		);

		$languages = array();

		foreach ( $this->plugin->languages()->active() as $language ) {
			$languages[] = $language->to_array();
		}

		$current = $this->plugin->router()->get_current_language();
		$default = $this->plugin->languages()->get_default();

		wp_localize_script(
			'als-elementor-editor',
			'ALSEditor',
			array(
				'languages'      => $languages,
				'current'        => $current instanceof Language ? $current->code : ( $default instanceof Language ? $default->code : '' ),
				'settingsUrl'    => admin_url( 'admin.php?page=als-languages' ),
				'emptyMessage'   => __( 'No languages are enabled yet. Add languages in Language Translator → Languages.', 'advanced-language-switcher' ),
				'selectLanguage' => __( 'Select language', 'advanced-language-switcher' ),
			)
		);
	}

	/**
	 * Makes the switcher styles available inside the editor preview iframe.
	 *
	 * @return void
	 */
	public function enqueue_preview_styles(): void {
		wp_enqueue_style(
			'als-language-switcher',
			ALS_PLUGIN_URL . 'frontend/css/language-switcher.css',
			array(),
			ALS_VERSION
		);
	}

	/**
	 * Translates content rendered by Elementor.
	 *
	 * @param string $content Rendered markup.
	 * @return string
	 */
	public function translate_rendered_content( $content ): string {
		$content = (string) $content;

		if ( ! Settings::is_enabled( 'translate_elementor' ) ) {
			return $content;
		}

		// Never rewrite content being rendered for an admin screen or the
		// Elementor editor: the editor must always show the source language.
		if ( $this->plugin->frontend()->is_admin_request() ) {
			return $content;
		}

		$router = $this->plugin->router();

		if ( $router->is_default_language() ) {
			return $content;
		}

		$language = $router->get_current_language();

		if ( ! $language instanceof Language ) {
			return $content;
		}

		// The whole-page pass already covers standard front end requests; this
		// filter matters for AJAX rendered templates and popups.
		if ( ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		$translator = new Html_Translator( $this->plugin->translations(), $language );

		return $translator->translate( $content );
	}

	/**
	 * Translates a single widget's rendered markup.
	 *
	 * @param string $content Rendered markup.
	 * @param object $widget  Widget instance.
	 * @return string
	 */
	public function translate_widget_content( $content, $widget = null ): string {
		$content = (string) $content;

		if ( ! Settings::is_enabled( 'translate_elementor' ) ) {
			return $content;
		}

		// The switcher renders its own languages and must never be rewritten.
		if ( is_object( $widget ) && method_exists( $widget, 'get_name' ) && 'als-language-switcher' === $widget->get_name() ) {
			return $content;
		}

		return $this->translate_rendered_content( $content );
	}

	/**
	 * Rescans an Elementor document when it is saved.
	 *
	 * @param object              $document Elementor document.
	 * @param array<string,mixed> $data     Saved data.
	 * @return void
	 */
	public function on_document_saved( $document, $data = array() ): void {
		if ( ! Settings::is_enabled( 'auto_translate_new' ) || ! Settings::is_enabled( 'translate_elementor' ) ) {
			return;
		}

		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}

		$post_id = (int) $document->get_main_id();
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$found = $this->plugin->scanner()->scan_elementor_document( $post );

		if ( $found > 0 ) {
			$this->plugin->translations()->invalidate_all();
			update_option( 'als_new_strings_notice', $found, false );
		}

		unset( $data );
	}

	/**
	 * Explains, once, that the widget needs Elementor.
	 *
	 * @return void
	 */
	public function maybe_show_missing_notice(): void {
		if ( $this->is_available() || ! Security::can_manage() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! str_contains( (string) $screen->id, 'als-' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'Elementor integration requires Elementor to be installed and activated. The language system, translations and the [als_language_switcher] shortcode work without it.', 'advanced-language-switcher' )
		);
	}
}
