<?php
/**
 * CRUD for configured languages.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the languages table.
 */
class Language_Manager {

	/**
	 * Runtime cache of all languages keyed by code.
	 *
	 * @var array<string,Language>|null
	 */
	protected ?array $cache = null;

	/**
	 * Returns every language, enabled or not, ordered by sort order.
	 *
	 * @return Language[]
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return array_values( $this->cache );
		}

		global $wpdb;

		if ( ! Database::tables_exist() ) {
			return array();
		}

		$table = Database::table( 'languages' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();

		$this->cache = array();

		foreach ( $rows as $row ) {
			$language                            = new Language( $row );
			$this->cache[ $language->code ] = $language;
		}

		return array_values( $this->cache );
	}

	/**
	 * Returns only enabled languages.
	 *
	 * @return Language[]
	 */
	public function active(): array {
		$active = array_values(
			array_filter(
				$this->all(),
				static fn( Language $language ) => $language->status
			)
		);

		/**
		 * Filters the list of languages exposed to visitors.
		 *
		 * @param Language[] $active Active languages.
		 */
		return apply_filters( 'als_supported_languages', $active );
	}

	/**
	 * Counts all configured languages.
	 *
	 * @return int
	 */
	public function count_all(): int {
		return count( $this->all() );
	}

	/**
	 * Finds a language by its short code.
	 *
	 * @param string $code Language code.
	 * @return Language|null
	 */
	public function get_by_code( string $code ): ?Language {
		$this->all();

		$code = strtolower( trim( $code ) );

		return $this->cache[ $code ] ?? null;
	}

	/**
	 * Finds a language by row id.
	 *
	 * @param int $id Row id.
	 * @return Language|null
	 */
	public function get_by_id( int $id ): ?Language {
		foreach ( $this->all() as $language ) {
			if ( $language->id === $id ) {
				return $language;
			}
		}

		return null;
	}

	/**
	 * Returns the default language, falling back to the first configured one.
	 *
	 * @return Language|null
	 */
	public function get_default(): ?Language {
		$languages = $this->all();

		foreach ( $languages as $language ) {
			if ( $language->is_default ) {
				return $language;
			}
		}

		return $languages[0] ?? null;
	}

	/**
	 * Whether the given code belongs to an enabled language.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public function is_active_code( string $code ): bool {
		$language = $this->get_by_code( $code );

		return $language instanceof Language && $language->status;
	}

	/**
	 * Creates a language row.
	 *
	 * @param array<string,mixed> $data Raw language data.
	 * @return int|\WP_Error New row id, or an error.
	 */
	public function create( array $data ) {
		global $wpdb;

		$prepared = $this->prepare( $data );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		if ( $this->get_by_code( $prepared['code'] ) instanceof Language ) {
			return new \WP_Error(
				'als_duplicate_code',
				__( 'A language with that code already exists.', 'advanced-language-switcher' )
			);
		}

		$prepared['created_at'] = Database::now();
		$prepared['updated_at'] = Database::now();

		if ( ! isset( $data['sort_order'] ) ) {
			$prepared['sort_order'] = $this->count_all();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( Database::table( 'languages' ), $prepared );

		if ( ! $inserted ) {
			return new \WP_Error(
				'als_insert_failed',
				__( 'The language could not be saved.', 'advanced-language-switcher' )
			);
		}

		$id = (int) $wpdb->insert_id;
		$this->flush();

		if ( ! empty( $prepared['is_default'] ) ) {
			$this->set_default( $id );
		} elseif ( ! $this->get_default() instanceof Language ) {
			$this->set_default( $id );
		}

		/**
		 * Fires after a language is created.
		 *
		 * @param int                 $id       New language id.
		 * @param array<string,mixed> $prepared Stored columns.
		 */
		do_action( 'als_language_created', $id, $prepared );

		return $id;
	}

	/**
	 * Updates a language row.
	 *
	 * @param int                 $id   Row id.
	 * @param array<string,mixed> $data Raw language data.
	 * @return true|\WP_Error
	 */
	public function update( int $id, array $data ) {
		global $wpdb;

		$existing = $this->get_by_id( $id );

		if ( ! $existing instanceof Language ) {
			return new \WP_Error( 'als_not_found', __( 'Language not found.', 'advanced-language-switcher' ) );
		}

		$prepared = $this->prepare( $data, $existing );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$conflict = $this->get_by_code( $prepared['code'] );

		if ( $conflict instanceof Language && $conflict->id !== $id ) {
			return new \WP_Error(
				'als_duplicate_code',
				__( 'A language with that code already exists.', 'advanced-language-switcher' )
			);
		}

		$prepared['updated_at'] = Database::now();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( Database::table( 'languages' ), $prepared, array( 'id' => $id ) );

		$this->flush();

		if ( ! empty( $prepared['is_default'] ) ) {
			$this->set_default( $id );
		}

		if ( $existing->code !== $prepared['code'] ) {
			// URL structure changed, rewrite rules must be rebuilt.
			update_option( Installer::FLUSH_OPTION, 1, false );
		}

		/**
		 * Fires after a language is updated.
		 *
		 * @param int                 $id       Language id.
		 * @param array<string,mixed> $prepared Stored columns.
		 */
		do_action( 'als_language_updated', $id, $prepared );

		return true;
	}

	/**
	 * Deletes a language and every translation attached to it.
	 *
	 * The default language cannot be deleted; the administrator must promote
	 * another language first, otherwise the site would lose its source content.
	 *
	 * @param int $id Row id.
	 * @return true|\WP_Error
	 */
	public function delete( int $id ) {
		global $wpdb;

		$language = $this->get_by_id( $id );

		if ( ! $language instanceof Language ) {
			return new \WP_Error( 'als_not_found', __( 'Language not found.', 'advanced-language-switcher' ) );
		}

		if ( $language->is_default ) {
			return new \WP_Error(
				'als_default_language',
				__( 'Set another language as default before deleting this one.', 'advanced-language-switcher' )
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Database::table( 'translations' ), array( 'language_id' => $id ) );
		$wpdb->delete( Database::table( 'glossary' ), array( 'language_id' => $id ) );
		$wpdb->delete( Database::table( 'cache' ), array( 'language_id' => $id ) );
		$wpdb->delete( Database::table( 'languages' ), array( 'id' => $id ) );
		// phpcs:enable

		$this->flush();
		update_option( Installer::FLUSH_OPTION, 1, false );

		/**
		 * Fires after a language is deleted.
		 *
		 * @param int      $id       Deleted language id.
		 * @param Language $language The deleted language.
		 */
		do_action( 'als_language_deleted', $id, $language );

		return true;
	}

	/**
	 * Duplicates a language row with a free code.
	 *
	 * @param int $id Row id.
	 * @return int|\WP_Error
	 */
	public function duplicate( int $id ) {
		$language = $this->get_by_id( $id );

		if ( ! $language instanceof Language ) {
			return new \WP_Error( 'als_not_found', __( 'Language not found.', 'advanced-language-switcher' ) );
		}

		$data               = $language->to_array();
		$data['is_default'] = 0;
		$data['status']     = 0;
		$data['code']       = $this->unique_code( $language->code );
		$data['name']       = $language->name . ' (copy)';

		unset( $data['id'], $data['html_lang'] );

		return $this->create( $data );
	}

	/**
	 * Promotes a language to be the site default.
	 *
	 * @param int $id Row id.
	 * @return true|\WP_Error
	 */
	public function set_default( int $id ) {
		global $wpdb;

		$language = $this->get_by_id( $id );

		if ( ! $language instanceof Language ) {
			return new \WP_Error( 'als_not_found', __( 'Language not found.', 'advanced-language-switcher' ) );
		}

		$table = Database::table( 'languages' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE {$table} SET is_default = 0" );
		$wpdb->update( $table, array( 'is_default' => 1, 'status' => 1 ), array( 'id' => $id ) );
		// phpcs:enable

		$this->flush();
		update_option( Installer::FLUSH_OPTION, 1, false );

		/**
		 * Fires after the default language changes.
		 *
		 * @param int $id New default language id.
		 */
		do_action( 'als_default_language_changed', $id );

		return true;
	}

	/**
	 * Enables or disables a language.
	 *
	 * @param int  $id     Row id.
	 * @param bool $status Desired status.
	 * @return true|\WP_Error
	 */
	public function set_status( int $id, bool $status ) {
		global $wpdb;

		$language = $this->get_by_id( $id );

		if ( ! $language instanceof Language ) {
			return new \WP_Error( 'als_not_found', __( 'Language not found.', 'advanced-language-switcher' ) );
		}

		if ( $language->is_default && ! $status ) {
			return new \WP_Error(
				'als_default_language',
				__( 'The default language cannot be disabled.', 'advanced-language-switcher' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			Database::table( 'languages' ),
			array(
				'status'     => $status ? 1 : 0,
				'updated_at' => Database::now(),
			),
			array( 'id' => $id )
		);

		$this->flush();

		return true;
	}

	/**
	 * Persists a manual ordering.
	 *
	 * @param int[] $ordered_ids Language ids in the desired order.
	 * @return void
	 */
	public function reorder( array $ordered_ids ): void {
		global $wpdb;

		$position = 0;

		foreach ( $ordered_ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				Database::table( 'languages' ),
				array( 'sort_order' => $position ),
				array( 'id' => (int) $id )
			);

			++$position;
		}

		$this->flush();
	}

	/**
	 * Normalizes and validates incoming language data.
	 *
	 * @param array<string,mixed> $data     Raw data.
	 * @param Language|null       $existing Existing row when updating.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected function prepare( array $data, ?Language $existing = null ) {
		$code = isset( $data['code'] ) ? sanitize_text_field( (string) $data['code'] ) : ( $existing->code ?? '' );
		$code = strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', $code ) ?? '' );

		if ( '' === $code ) {
			return new \WP_Error(
				'als_missing_code',
				__( 'A language code is required.', 'advanced-language-switcher' )
			);
		}

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : ( $existing->name ?? '' );

		if ( '' === $name ) {
			return new \WP_Error(
				'als_missing_name',
				__( 'A language name is required.', 'advanced-language-switcher' )
			);
		}

		$catalog = Language_Catalog::get( substr( $code, 0, 2 ) );

		$direction = isset( $data['direction'] ) ? strtolower( sanitize_text_field( (string) $data['direction'] ) ) : ( $existing->direction ?? 'auto' );

		if ( 'auto' === $direction || ! in_array( $direction, array( 'ltr', 'rtl' ), true ) ) {
			$direction = Language_Catalog::is_rtl( $code ) ? 'rtl' : 'ltr';
		}

		$native = isset( $data['native_name'] ) ? sanitize_text_field( (string) $data['native_name'] ) : ( $existing->native_name ?? '' );

		if ( '' === $native ) {
			$native = $catalog['native_name'] ?? $name;
		}

		$locale = isset( $data['locale'] ) ? sanitize_text_field( (string) $data['locale'] ) : ( $existing->locale ?? '' );

		if ( '' === $locale ) {
			$locale = $catalog['locale'] ?? $code;
		}

		$flag = isset( $data['flag'] ) ? sanitize_text_field( (string) $data['flag'] ) : ( $existing->flag ?? '' );

		if ( '' === $flag ) {
			$flag = $catalog['flag'] ?? '';
		}

		$flag_url = isset( $data['flag_url'] ) ? esc_url_raw( (string) $data['flag_url'] ) : ( $existing->flag_url ?? '' );

		$country = isset( $data['country'] ) ? strtoupper( sanitize_text_field( (string) $data['country'] ) ) : ( $existing->country ?? '' );

		if ( '' === $country ) {
			$country = $catalog['country'] ?? '';
		}

		return array(
			'name'        => $name,
			'native_name' => $native,
			'code'        => $code,
			'locale'      => $locale,
			'country'     => substr( $country, 0, 10 ),
			'flag'        => $flag,
			'flag_url'    => $flag_url,
			'direction'   => $direction,
			'status'      => isset( $data['status'] ) ? (int) (bool) $data['status'] : (int) ( $existing->status ?? true ),
			'is_default'  => isset( $data['is_default'] ) ? (int) (bool) $data['is_default'] : (int) ( $existing->is_default ?? false ),
			'sort_order'  => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : (int) ( $existing->sort_order ?? 0 ),
		);
	}

	/**
	 * Builds a code that is not yet in use.
	 *
	 * @param string $code Base code.
	 * @return string
	 */
	protected function unique_code( string $code ): string {
		$candidate = $code . '-copy';
		$suffix    = 2;

		while ( $this->get_by_code( $candidate ) instanceof Language ) {
			$candidate = $code . '-copy-' . $suffix;
			++$suffix;
		}

		return $candidate;
	}

	/**
	 * Clears the runtime cache.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->cache = null;
	}
}
