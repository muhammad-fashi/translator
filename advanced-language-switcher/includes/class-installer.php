<?php
/**
 * Activation, deactivation and first-run provisioning.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Handles everything that must happen once, at activation time.
 */
class Installer {

	/**
	 * Option flag used to show the onboarding notice.
	 */
	public const ONBOARDING_OPTION = 'als_show_onboarding';

	/**
	 * Option flag used to schedule a rewrite flush on the next request.
	 */
	public const FLUSH_OPTION = 'als_flush_rewrite_rules';

	/**
	 * Runs on plugin activation.
	 *
	 * Deliberately cheap: tables, defaults, a single default language and a
	 * deferred rewrite flush. No website scanning happens here.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_site();
				restore_current_blog();
			}

			return;
		}

		self::install_site();
	}

	/**
	 * Provisions a single site.
	 *
	 * @return void
	 */
	public static function install_site(): void {
		Database::install();
		Settings::install_defaults();
		self::install_default_language();

		add_option( self::ONBOARDING_OPTION, 1, '', false );
		update_option( self::FLUSH_OPTION, 1, false );
		update_option( 'als_activated_at', time(), false );
	}

	/**
	 * Provisions a newly created multisite blog.
	 *
	 * @param \WP_Site $site New site object.
	 * @return void
	 */
	public static function on_new_site( $site ): void {
		if ( ! is_plugin_active_for_network( ALS_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Creates the default language row when the table is empty.
	 *
	 * The site locale is used so activation matches the existing WordPress
	 * configuration instead of blindly assuming English.
	 *
	 * @return void
	 */
	protected static function install_default_language(): void {
		$manager = new Language_Manager();

		if ( $manager->count_all() > 0 ) {
			return;
		}

		$locale  = get_locale();
		$code    = strtolower( substr( $locale, 0, 2 ) );
		$catalog = Language_Catalog::get( $code );

		$manager->create(
			array(
				'name'        => $catalog['name'] ?? 'English',
				'native_name' => $catalog['native_name'] ?? 'English',
				'code'        => $code ? $code : 'en',
				'locale'      => $locale ? $locale : 'en_US',
				'country'     => $catalog['country'] ?? 'US',
				'flag'        => $catalog['flag'] ?? '🇺🇸',
				'direction'   => $catalog['direction'] ?? 'ltr',
				'status'      => 1,
				'is_default'  => 1,
				'sort_order'  => 0,
			)
		);
	}

	/**
	 * Runs on deactivation: unschedule cron events and flush rewrites.
	 *
	 * Translation data is intentionally preserved; removal happens in
	 * uninstall.php and only when the administrator opted in.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'als_cache_cleanup' );
		wp_clear_scheduled_hook( 'als_auto_translate_queue' );
		flush_rewrite_rules();
	}
}
