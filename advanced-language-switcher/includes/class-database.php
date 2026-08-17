<?php
/**
 * Database schema and low level table helpers.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the custom table schema.
 *
 * Every table is prefixed with the *blog* prefix so that the plugin works on
 * multisite with per-site language configuration out of the box.
 */
class Database {

	/**
	 * Schema version stored in options, bumped when tables change.
	 */
	public const SCHEMA_VERSION = '1.0.0';

	/**
	 * Option name holding the installed schema version.
	 */
	public const SCHEMA_OPTION = 'als_schema_version';

	/**
	 * Memoized result of the table existence check.
	 *
	 * @var bool|null
	 */
	protected static ?bool $tables_exist = null;

	/**
	 * Memoized list of missing tables.
	 *
	 * @var string[]|null
	 */
	protected static ?array $missing = null;

	/**
	 * Returns the fully qualified table name.
	 *
	 * @param string $table Logical table name (languages, strings, ...).
	 * @return string
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . 'als_' . $table;
	}

	/**
	 * All logical table names owned by the plugin.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array(
			'languages',
			'strings',
			'translations',
			'translation_groups',
			'cache',
			'glossary',
			'logs',
			'backups',
		);
	}

	/**
	 * Creates or upgrades the schema using dbDelta().
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$languages       = self::table( 'languages' );
		$strings         = self::table( 'strings' );
		$translations    = self::table( 'translations' );
		$groups          = self::table( 'translation_groups' );
		$cache           = self::table( 'cache' );
		$glossary        = self::table( 'glossary' );
		$logs            = self::table( 'logs' );
		$backups         = self::table( 'backups' );

		$queries = array();

		$queries[] = "CREATE TABLE {$languages} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL DEFAULT '',
			native_name VARCHAR(100) NOT NULL DEFAULT '',
			code VARCHAR(12) NOT NULL DEFAULT '',
			locale VARCHAR(20) NOT NULL DEFAULT '',
			country VARCHAR(10) NOT NULL DEFAULT '',
			flag VARCHAR(20) NOT NULL DEFAULT '',
			flag_url VARCHAR(255) NOT NULL DEFAULT '',
			direction VARCHAR(5) NOT NULL DEFAULT 'ltr',
			status TINYINT(1) NOT NULL DEFAULT 1,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY status (status),
			KEY is_default (is_default),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$strings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_hash CHAR(40) NOT NULL DEFAULT '',
			source_text LONGTEXT NOT NULL,
			context VARCHAR(191) NOT NULL DEFAULT '',
			source_location VARCHAR(191) NOT NULL DEFAULT '',
			element_id VARCHAR(100) NOT NULL DEFAULT '',
			widget_type VARCHAR(100) NOT NULL DEFAULT '',
			object_type VARCHAR(50) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			group_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			is_html TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY source_hash (source_hash),
			KEY object_lookup (object_type,object_id),
			KEY group_id (group_id),
			KEY widget_type (widget_type)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$translations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			string_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			language_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			translated_text LONGTEXT NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'missing',
			provider VARCHAR(50) NOT NULL DEFAULT 'manual',
			context VARCHAR(191) NOT NULL DEFAULT '',
			source_hash CHAR(40) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY string_language (string_id,language_id),
			KEY language_status (language_id,status),
			KEY source_hash (source_hash),
			KEY provider (provider)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$groups} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			slug VARCHAR(191) NOT NULL DEFAULT '',
			description TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$cache} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cache_key CHAR(40) NOT NULL DEFAULT '',
			cache_group VARCHAR(50) NOT NULL DEFAULT 'default',
			language_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cache_value LONGTEXT NOT NULL,
			expires_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY cache_key (cache_key),
			KEY group_language (cache_group,language_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$glossary} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			language_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			source_term VARCHAR(191) NOT NULL DEFAULT '',
			target_term VARCHAR(191) NOT NULL DEFAULT '',
			case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY language_term (language_id,source_term)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(20) NOT NULL DEFAULT 'info',
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			provider VARCHAR(50) NOT NULL DEFAULT '',
			language VARCHAR(12) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$backups} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			language_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NOT NULL,
			item_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY language_id (language_id)
		) {$charset_collate};";

		foreach ( $queries as $query ) {
			dbDelta( $query );
		}

		// Autoloaded on purpose: its presence is the cheap "schema is ready"
		// signal read on every request, which avoids a SHOW TABLES round trip.
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );

		self::$tables_exist = null;
		self::$missing      = null;
	}

	/**
	 * Runs install() when the stored schema version is out of date.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Whether every plugin table physically exists.
	 *
	 * @return bool
	 */
	public static function tables_exist(): bool {
		if ( null !== self::$tables_exist ) {
			return self::$tables_exist;
		}

		// Fast path: the schema version option is autoloaded, so a healthy
		// install answers this without touching the database at all.
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION ) ) {
			self::$tables_exist = true;

			return true;
		}

		self::$tables_exist = count( self::missing_tables() ) === 0;

		return self::$tables_exist;
	}

	/**
	 * Lists plugin tables that are missing from the database.
	 *
	 * @return string[]
	 */
	public static function missing_tables(): array {
		if ( null !== self::$missing ) {
			return self::$missing;
		}

		global $wpdb;

		$missing = array();

		foreach ( self::tables() as $table ) {
			$name = self::table( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );

			if ( $found !== $name ) {
				$missing[] = $name;
			}
		}

		self::$missing = $missing;

		return $missing;
	}

	/**
	 * Drops every plugin table. Used by uninstall only.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( self::tables() as $table ) {
			$name = self::table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS {$name}" );
		}

		delete_option( self::SCHEMA_OPTION );

		self::$tables_exist = null;
		self::$missing      = null;
	}

	/**
	 * Returns the current MySQL formatted datetime.
	 *
	 * @return string
	 */
	public static function now(): string {
		return current_time( 'mysql' );
	}

	/**
	 * Aggregated row counts used by the dashboard and system status page.
	 *
	 * @return array<string,int>
	 */
	public static function stats(): array {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return array(
				'strings'      => 0,
				'translations' => 0,
				'cache'        => 0,
				'languages'    => 0,
				'logs'         => 0,
			);
		}

		$strings      = self::table( 'strings' );
		$translations = self::table( 'translations' );
		$cache        = self::table( 'cache' );
		$languages    = self::table( 'languages' );
		$logs         = self::table( 'logs' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array(
			'strings'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$strings}" ),
			'translations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$translations} WHERE status <> 'missing' AND translated_text <> ''" ),
			'cache'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cache}" ),
			'languages'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$languages} WHERE status = 1" ),
			'logs'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs}" ),
		);
		// phpcs:enable
	}
}
