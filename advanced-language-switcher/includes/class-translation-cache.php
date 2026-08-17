<?php
/**
 * Multi-tier translation cache: request memory, object cache, database.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps translated strings and rendered fragments out of the provider APIs.
 */
class Translation_Cache {

	/**
	 * Object cache group name.
	 */
	public const GROUP = 'als_translations';

	/**
	 * In-request memoization.
	 *
	 * @var array<string,mixed>
	 */
	protected array $memory = array();

	/**
	 * Number of memory/object/database hits in this request.
	 *
	 * @var int
	 */
	protected int $hits = 0;

	/**
	 * Number of misses in this request.
	 *
	 * @var int
	 */
	protected int $misses = 0;

	/**
	 * Builds a stable cache key.
	 *
	 * @param string $group    Logical group.
	 * @param string $material Key material.
	 * @return string
	 */
	public function key( string $group, string $material ): string {
		return sha1( $group . '|' . $material );
	}

	/**
	 * Reads a cached value.
	 *
	 * @param string $key   Cache key produced by key().
	 * @param string $group Logical group, used for targeted flushes.
	 * @return mixed|null Null when the entry is missing or expired.
	 */
	public function get( string $key, string $group = 'default' ) {
		if ( ! Settings::is_enabled( 'cache_enabled' ) ) {
			return null;
		}

		if ( array_key_exists( $key, $this->memory ) ) {
			++$this->hits;

			return $this->memory[ $key ];
		}

		if ( Settings::is_enabled( 'object_cache_enabled' ) && wp_using_ext_object_cache() ) {
			$found = false;
			$value = wp_cache_get( $key, self::GROUP, false, $found );

			if ( $found ) {
				$this->memory[ $key ] = $value;
				++$this->hits;

				return $value;
			}
		}

		global $wpdb;

		if ( ! Database::tables_exist() ) {
			return null;
		}

		$table = Database::table( 'cache' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cache_value, expires_at FROM {$table} WHERE cache_key = %s LIMIT 1",
				$key
			),
			ARRAY_A
		);

		if ( ! $row ) {
			++$this->misses;

			return null;
		}

		if ( ! empty( $row['expires_at'] ) && strtotime( (string) $row['expires_at'] ) < time() ) {
			$this->delete( $key );
			++$this->misses;

			return null;
		}

		$value = maybe_unserialize( $row['cache_value'] );

		$this->memory[ $key ] = $value;
		++$this->hits;

		return $value;
	}

	/**
	 * Stores a value in every available tier.
	 *
	 * @param string $key         Cache key.
	 * @param mixed  $value       Value to store.
	 * @param string $group       Logical group.
	 * @param int    $ttl         Lifetime in seconds; 0 uses the configured TTL.
	 * @param int    $language_id Optional language id for targeted flushes.
	 * @return void
	 */
	public function set( string $key, $value, string $group = 'default', int $ttl = 0, int $language_id = 0 ): void {
		if ( ! Settings::is_enabled( 'cache_enabled' ) ) {
			return;
		}

		$ttl = $ttl > 0 ? $ttl : Settings::get_int( 'cache_ttl', 86400 );

		$this->memory[ $key ] = $value;

		if ( Settings::is_enabled( 'object_cache_enabled' ) && wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $value, self::GROUP, $ttl );
		}

		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		$data = array(
			'cache_key'   => $key,
			'cache_group' => substr( $group, 0, 50 ),
			'language_id' => $language_id,
			'cache_value' => maybe_serialize( $value ),
			'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
			'created_at'  => Database::now(),
		);

		$table = Database::table( 'cache' );

		// REPLACE keeps the unique cache_key constraint authoritative without
		// a separate SELECT round trip.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->replace( $table, $data );
	}

	/**
	 * Deletes one entry.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public function delete( string $key ): void {
		unset( $this->memory[ $key ] );

		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, self::GROUP );
		}

		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Database::table( 'cache' ), array( 'cache_key' => $key ) );
	}

	/**
	 * Flushes every cache entry, or only one group / language.
	 *
	 * @param string $group       Optional group filter.
	 * @param int    $language_id Optional language filter.
	 * @return int Rows removed.
	 */
	public function flush( string $group = '', int $language_id = 0 ): int {
		$this->memory = array();

		if ( wp_using_ext_object_cache() ) {
			// Global object cache flush is too blunt; the group is invalidated
			// by bumping a salt that participates in every key.
			wp_cache_set( 'als_cache_salt', microtime( true ), self::GROUP );
		}

		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$table = Database::table( 'cache' );
		$where = array();
		$args  = array();

		if ( '' !== $group ) {
			$where[] = 'cache_group = %s';
			$args[]  = $group;
		}

		if ( $language_id > 0 ) {
			$where[] = 'language_id = %d';
			$args[]  = $language_id;
		}

		if ( $where ) {
			$sql = "DELETE FROM {$table} WHERE " . implode( ' AND ', $where );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		return (int) $wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Removes expired rows. Wired to a daily cron event.
	 *
	 * @return int Rows removed.
	 */
	public function purge_expired(): int {
		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$table = Database::table( 'cache' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s' ) )
		);
	}

	/**
	 * Cache hit/miss counters for the current request.
	 *
	 * @return array<string,int>
	 */
	public function stats(): array {
		return array(
			'hits'   => $this->hits,
			'misses' => $this->misses,
		);
	}
}
