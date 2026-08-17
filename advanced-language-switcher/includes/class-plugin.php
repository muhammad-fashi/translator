<?php
/**
 * Service container and boot sequence.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every subsystem together.
 *
 * Each integration is optional and fails soft: a missing Elementor or
 * WooCommerce install simply means that integration never registers, while
 * the core language system keeps working.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static ?Plugin $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	protected bool $booted = false;

	/**
	 * Service instances keyed by name.
	 *
	 * @var array<string,object>
	 */
	protected array $services = array();

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( ! self::$instance instanceof Plugin ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor: use instance().
	 */
	private function __construct() {
		$this->services['languages'] = new Language_Manager();
		$this->services['cache']     = new Translation_Cache();
		$this->services['logger']    = new Logger();
		$this->services['glossary']  = new Glossary();

		$this->services['engine'] = new Translation_Engine(
			$this->services['glossary'],
			$this->services['logger']
		);

		$this->services['translations'] = new Translation_Manager(
			$this->services['cache'],
			$this->services['languages'],
			$this->services['logger']
		);

		$this->services['router']    = new Url_Manager( $this->services['languages'] );
		$this->services['scanner']   = new Scanner( $this->services['translations'], $this->services['languages'] );
		$this->services['porter']    = new Porter( $this->services['translations'], $this->services['languages'] );
		$this->services['frontend']  = new Frontend( $this );
		$this->services['seo']       = new Seo( $this );
		$this->services['rest']      = new Rest_Api( $this );
		$this->services['ajax']      = new Ajax( $this );
		$this->services['elementor'] = new Elementor_Integration( $this );
		$this->services['woo']       = new Woocommerce_Integration( $this );
	}

	/**
	 * Registers hooks. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// URL handling must run before WordPress parses the request.
		$this->router()->register();

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'admin_init', array( Database::class, 'maybe_upgrade' ) );
		add_action( 'als_cache_cleanup', array( $this, 'run_maintenance' ) );
		add_action( 'init', array( $this, 'schedule_events' ) );

		$this->frontend()->register();
		$this->seo()->register();
		$this->rest()->register();
		$this->ajax()->register();
		$this->elementor()->register();
		$this->woocommerce()->register();

		if ( is_admin() ) {
			$this->services['admin'] = new Admin\Admin( $this );
			$this->services['admin']->register();
		}

		/**
		 * Fires once every plugin subsystem has registered its hooks.
		 *
		 * @param Plugin $plugin Plugin container.
		 */
		do_action( 'als_loaded', $this );
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'advanced-language-switcher',
			false,
			dirname( ALS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Flushes rewrite rules once after activation or a language change.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( ! get_option( Installer::FLUSH_OPTION ) ) {
			return;
		}

		delete_option( Installer::FLUSH_OPTION );
		flush_rewrite_rules( false );
	}

	/**
	 * Registers the daily maintenance event.
	 *
	 * @return void
	 */
	public function schedule_events(): void {
		if ( ! wp_next_scheduled( 'als_cache_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'als_cache_cleanup' );
		}
	}

	/**
	 * Daily maintenance: expire cache rows and trim the log table.
	 *
	 * @return void
	 */
	public function run_maintenance(): void {
		$this->cache()->purge_expired();
		$this->logger()->purge_old();
	}

	/* ---------------------------------------------------------------------
	 * Service accessors
	 * ------------------------------------------------------------------ */

	/**
	 * Language manager.
	 *
	 * @return Language_Manager
	 */
	public function languages(): Language_Manager {
		return $this->services['languages'];
	}

	/**
	 * Translation cache.
	 *
	 * @return Translation_Cache
	 */
	public function cache(): Translation_Cache {
		return $this->services['cache'];
	}

	/**
	 * Logger.
	 *
	 * @return Logger
	 */
	public function logger(): Logger {
		return $this->services['logger'];
	}

	/**
	 * Glossary.
	 *
	 * @return Glossary
	 */
	public function glossary(): Glossary {
		return $this->services['glossary'];
	}

	/**
	 * Translation engine.
	 *
	 * @return Translation_Engine
	 */
	public function engine(): Translation_Engine {
		return $this->services['engine'];
	}

	/**
	 * Translation manager.
	 *
	 * @return Translation_Manager
	 */
	public function translations(): Translation_Manager {
		return $this->services['translations'];
	}

	/**
	 * URL manager / language router.
	 *
	 * @return Url_Manager
	 */
	public function router(): Url_Manager {
		return $this->services['router'];
	}

	/**
	 * Content scanner.
	 *
	 * @return Scanner
	 */
	public function scanner(): Scanner {
		return $this->services['scanner'];
	}

	/**
	 * Import / export service.
	 *
	 * @return Porter
	 */
	public function porter(): Porter {
		return $this->services['porter'];
	}

	/**
	 * Front end controller.
	 *
	 * @return Frontend
	 */
	public function frontend(): Frontend {
		return $this->services['frontend'];
	}

	/**
	 * SEO controller.
	 *
	 * @return Seo
	 */
	public function seo(): Seo {
		return $this->services['seo'];
	}

	/**
	 * REST controller.
	 *
	 * @return Rest_Api
	 */
	public function rest(): Rest_Api {
		return $this->services['rest'];
	}

	/**
	 * AJAX controller.
	 *
	 * @return Ajax
	 */
	public function ajax(): Ajax {
		return $this->services['ajax'];
	}

	/**
	 * Elementor integration.
	 *
	 * @return Elementor_Integration
	 */
	public function elementor(): Elementor_Integration {
		return $this->services['elementor'];
	}

	/**
	 * WooCommerce integration.
	 *
	 * @return Woocommerce_Integration
	 */
	public function woocommerce(): Woocommerce_Integration {
		return $this->services['woo'];
	}
}
