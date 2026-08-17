<?php
/**
 * Optional debug logging.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Writes provider and translation diagnostics to a dedicated table.
 *
 * Logging is disabled by default; nothing here is ever shown to visitors.
 */
class Logger {

	/**
	 * Severity ordering used to honour the configured minimum level.
	 *
	 * @var array<string,int>
	 */
	protected const LEVELS = array(
		'error'   => 40,
		'warning' => 30,
		'info'    => 20,
		'debug'   => 10,
	);

	/**
	 * Writes an entry when logging is enabled and the level is high enough.
	 *
	 * @param string              $level    One of error|warning|info|debug.
	 * @param string              $message  Human readable message.
	 * @param array<string,mixed> $context  Structured context.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! Settings::is_enabled( 'logging_enabled' ) ) {
			return;
		}

		$level     = isset( self::LEVELS[ $level ] ) ? $level : 'info';
		$threshold = self::LEVELS[ (string) Settings::get( 'log_level', 'error' ) ] ?? self::LEVELS['error'];

		if ( self::LEVELS[ $level ] < $threshold ) {
			return;
		}

		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		// Never persist credentials, even by accident.
		unset( $context['api_key'], $context['key'], $context['authorization'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			Database::table( 'logs' ),
			array(
				'level'      => $level,
				'message'    => substr( $message, 0, 5000 ),
				'context'    => wp_json_encode( $context ),
				'provider'   => isset( $context['provider'] ) ? substr( (string) $context['provider'], 0, 50 ) : '',
				'language'   => isset( $context['language'] ) ? substr( (string) $context['language'], 0, 12 ) : '',
				'created_at' => Database::now(),
			)
		);
	}

	/**
	 * Shortcut for error level entries.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Shortcut for warning level entries.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Shortcut for info level entries.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Shortcut for debug level entries.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Returns the most recent entries.
	 *
	 * @param int    $limit Maximum rows.
	 * @param string $level Optional level filter.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( int $limit = 100, string $level = '' ): array {
		if ( ! Database::tables_exist() ) {
			return array();
		}

		global $wpdb;

		$table = Database::table( 'logs' );
		$limit = max( 1, min( 500, $limit ) );

		if ( '' !== $level && isset( self::LEVELS[ $level ] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE level = %s ORDER BY id DESC LIMIT %d",
					$level,
					$limit
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Empties the log table.
	 *
	 * @return int Rows removed.
	 */
	public function clear(): int {
		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$table = Database::table( 'logs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		return (int) $wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Deletes entries older than the configured retention window.
	 *
	 * @return int Rows removed.
	 */
	public function purge_old(): int {
		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$days   = max( 1, Settings::get_int( 'log_retention_days', 14 ) );
		$table  = Database::table( 'logs' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}
}
