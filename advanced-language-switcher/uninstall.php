<?php
/**
 * Uninstall routine.
 *
 * Nothing is removed unless the administrator explicitly opted in on the
 * Translation Settings screen. Deleting a plugin should never silently destroy
 * months of translation work.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin's data from the current site, honouring the settings.
 *
 * @return void
 */
function als_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'als_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	$remove_data     = ! empty( $settings['uninstall_remove_data'] );
	$remove_settings = ! empty( $settings['uninstall_remove_settings'] );

	if ( $remove_data ) {
		$tables = array(
			'languages',
			'strings',
			'translations',
			'translation_groups',
			'cache',
			'glossary',
			'logs',
			'backups',
		);

		foreach ( $tables as $table ) {
			$name = $wpdb->prefix . 'als_' . $table;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS {$name}" );
		}

		delete_option( 'als_schema_version' );
		delete_option( 'als_scan_counters' );
		delete_option( 'als_last_scan' );
		delete_option( 'als_last_scan_started' );
		delete_option( 'als_new_strings_notice' );

		// User level language preferences.
		delete_metadata( 'user', 0, 'als_language', '', true );
	}

	if ( $remove_settings ) {
		delete_option( 'als_settings' );
		delete_option( 'als_credentials' );
		delete_option( 'als_caps_version' );
		delete_option( 'als_activated_at' );
		delete_option( 'als_show_onboarding' );
		delete_option( 'als_flush_rewrite_rules' );

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof WP_Role ) {
				$role->remove_cap( 'als_edit_translations' );
			}
		}
	}

	// Transients used by the discovery throttle are always safe to remove.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_als_discovery_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_als_discovery_' ) . '%'
		)
	);

	wp_clear_scheduled_hook( 'als_cache_cleanup' );
	wp_clear_scheduled_hook( 'als_auto_translate_queue' );
}

if ( is_multisite() ) {
	$als_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $als_sites as $als_site_id ) {
		switch_to_blog( (int) $als_site_id );
		als_uninstall_site();
		restore_current_blog();
	}
} else {
	als_uninstall_site();
}
