<?php
/**
 * Per-language glossary of forced terminology.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Glossary terms take priority over whatever a provider returns.
 *
 * A term is applied when its source form is present in the original string;
 * the target form then replaces the provider's rendering of that term.
 */
class Glossary {

	/**
	 * Runtime cache of terms keyed by language id.
	 *
	 * @var array<int,array<int,array<string,mixed>>>
	 */
	protected array $cache = array();

	/**
	 * Returns every glossary term for a language.
	 *
	 * @param int $language_id Language id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_terms( int $language_id ): array {
		if ( isset( $this->cache[ $language_id ] ) ) {
			return $this->cache[ $language_id ];
		}

		if ( ! Database::tables_exist() ) {
			return array();
		}

		global $wpdb;

		$table = Database::table( 'glossary' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE language_id = %d ORDER BY CHAR_LENGTH(source_term) DESC",
				$language_id
			),
			ARRAY_A
		);

		$this->cache[ $language_id ] = is_array( $rows ) ? $rows : array();

		return $this->cache[ $language_id ];
	}

	/**
	 * Applies glossary terms to a translated string.
	 *
	 * @param string   $translated Provider output.
	 * @param Language $language   Target language.
	 * @param string   $original   Original source string.
	 * @return string
	 */
	public function apply( string $translated, Language $language, string $original = '' ): string {
		$terms = $this->get_terms( $language->id );

		if ( ! $terms ) {
			return $translated;
		}

		foreach ( $terms as $term ) {
			$source = (string) $term['source_term'];
			$target = (string) $term['target_term'];

			if ( '' === $source || '' === $target ) {
				continue;
			}

			$flags = 'u' . ( empty( $term['case_sensitive'] ) ? 'i' : '' );

			// Only force terminology when the source string actually used it.
			if ( '' !== $original && ! preg_match( '/' . preg_quote( $source, '/' ) . '/' . $flags, $original ) ) {
				continue;
			}

			// Replace the source term if the provider left it untranslated.
			$translated = (string) preg_replace(
				'/\b' . preg_quote( $source, '/' ) . '\b/' . $flags,
				str_replace( '$', '\\$', $target ),
				$translated
			);
		}

		return $translated;
	}

	/**
	 * Adds or updates a glossary term.
	 *
	 * @param int    $language_id    Language id.
	 * @param string $source_term    Source term.
	 * @param string $target_term    Target term.
	 * @param bool   $case_sensitive Whether matching is case sensitive.
	 * @return int|\WP_Error Row id.
	 */
	public function save( int $language_id, string $source_term, string $target_term, bool $case_sensitive = false ) {
		if ( ! Database::tables_exist() ) {
			return new \WP_Error( 'als_no_tables', __( 'Plugin tables are missing.', 'advanced-language-switcher' ) );
		}

		$source_term = trim( sanitize_text_field( $source_term ) );
		$target_term = trim( sanitize_text_field( $target_term ) );

		if ( '' === $source_term || '' === $target_term ) {
			return new \WP_Error(
				'als_glossary_incomplete',
				__( 'Both the source and target term are required.', 'advanced-language-switcher' )
			);
		}

		global $wpdb;

		$table = Database::table( 'glossary' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE language_id = %d AND source_term = %s LIMIT 1",
				$language_id,
				$source_term
			)
		);

		$data = array(
			'language_id'    => $language_id,
			'source_term'    => $source_term,
			'target_term'    => $target_term,
			'case_sensitive' => $case_sensitive ? 1 : 0,
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			$id = (int) $existing;
		} else {
			$data['created_at'] = Database::now();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		unset( $this->cache[ $language_id ] );

		return $id;
	}

	/**
	 * Deletes a glossary term.
	 *
	 * @param int $id Row id.
	 * @return void
	 */
	public function delete( int $id ): void {
		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Database::table( 'glossary' ), array( 'id' => $id ) );

		$this->cache = array();
	}
}
