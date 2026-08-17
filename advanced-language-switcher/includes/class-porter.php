<?php
/**
 * Import, export and backup of translation data.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Moves translations in and out of the plugin in JSON, CSV, PO and MO form.
 *
 * Imports are validated before a single row is written, and a backup is taken
 * automatically so a bad file can always be undone.
 */
class Porter {

	/**
	 * Translation manager.
	 *
	 * @var Translation_Manager
	 */
	protected Translation_Manager $translations;

	/**
	 * Language manager.
	 *
	 * @var Language_Manager
	 */
	protected Language_Manager $languages;

	/**
	 * Constructor.
	 *
	 * @param Translation_Manager $translations Translation manager.
	 * @param Language_Manager    $languages    Language manager.
	 */
	public function __construct( Translation_Manager $translations, Language_Manager $languages ) {
		$this->translations = $translations;
		$this->languages    = $languages;
	}

	/**
	 * Supported formats.
	 *
	 * @return array<string,string>
	 */
	public static function formats(): array {
		return array(
			'json' => 'JSON',
			'csv'  => 'CSV',
			'po'   => 'PO (gettext)',
			'mo'   => 'MO (compiled gettext)',
		);
	}

	/* ---------------------------------------------------------------------
	 * Export
	 * ------------------------------------------------------------------ */

	/**
	 * Reads every string plus its translation for a language.
	 *
	 * @param int $language_id Language id.
	 * @return array<int,array<string,string>>
	 */
	protected function collect( int $language_id ): array {
		if ( ! Database::tables_exist() ) {
			return array();
		}

		global $wpdb;

		$strings      = Database::table( 'strings' );
		$translations = Database::table( 'translations' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.source_text, s.context, s.source_hash, s.widget_type, s.object_type,
					COALESCE(t.translated_text, '') AS translated_text,
					COALESCE(t.status, 'missing') AS status,
					COALESCE(t.provider, '') AS provider
				 FROM {$strings} s
				 LEFT JOIN {$translations} t ON t.string_id = s.id AND t.language_id = %d
				 ORDER BY s.id ASC",
				$language_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Builds an export payload.
	 *
	 * @param int    $language_id Language id.
	 * @param string $format      json|csv|po|mo.
	 * @return array{filename:string,mime:string,content:string,encoding:string,count:int}|\WP_Error
	 */
	public function export( int $language_id, string $format = 'json' ) {
		$language = $this->languages->get_by_id( $language_id );

		if ( ! $language instanceof Language ) {
			return new \WP_Error( 'als_unknown_language', __( 'Unknown language.', 'advanced-language-switcher' ) );
		}

		if ( ! array_key_exists( $format, self::formats() ) ) {
			return new \WP_Error( 'als_unknown_format', __( 'Unsupported export format.', 'advanced-language-switcher' ) );
		}

		$rows = $this->collect( $language_id );
		$slug = sanitize_file_name( strtolower( $language->name ) . '-translations' );

		switch ( $format ) {
			case 'csv':
				return array(
					'filename' => $slug . '.csv',
					'mime'     => 'text/csv',
					'content'  => $this->to_csv( $rows ),
					'encoding' => 'text',
					'count'    => count( $rows ),
				);

			case 'po':
				return array(
					'filename' => $slug . '.po',
					'mime'     => 'text/x-gettext-translation',
					'content'  => $this->to_po( $rows, $language ),
					'encoding' => 'text',
					'count'    => count( $rows ),
				);

			case 'mo':
				return array(
					'filename' => $slug . '.mo',
					'mime'     => 'application/x-gettext-translation',
					'content'  => base64_encode( $this->to_mo( $rows ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'encoding' => 'base64',
					'count'    => count( $rows ),
				);

			default:
				return array(
					'filename' => $slug . '.json',
					'mime'     => 'application/json',
					'content'  => (string) wp_json_encode(
						array(
							'plugin'    => 'advanced-language-switcher',
							'version'   => ALS_VERSION,
							'generated' => gmdate( 'c' ),
							'language'  => $language->to_array(),
							'items'     => $rows,
						),
						JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
					),
					'encoding' => 'text',
					'count'    => count( $rows ),
				);
		}
	}

	/**
	 * Serializes rows to CSV.
	 *
	 * @param array<int,array<string,string>> $rows Rows.
	 * @return string
	 */
	protected function to_csv( array $rows ): string {
		$handle = fopen( 'php://temp', 'r+' );

		if ( ! $handle ) {
			return '';
		}

		// The escape character is passed explicitly: relying on the default is
		// deprecated in PHP 8.4, and an empty escape produces RFC 4180 output
		// that spreadsheet applications read correctly.
		fputcsv( $handle, array( 'source', 'translation', 'context', 'status', 'provider', 'hash' ), ',', '"', '' );

		foreach ( $rows as $row ) {
			fputcsv(
				$handle,
				array(
					$row['source_text'],
					$row['translated_text'],
					$row['context'],
					$row['status'],
					$row['provider'],
					$row['source_hash'],
				),
				',',
				'"',
				''
			);
		}

		rewind( $handle );
		$content = (string) stream_get_contents( $handle );
		fclose( $handle );

		// A BOM keeps spreadsheet applications from mangling UTF-8.
		return "\xEF\xBB\xBF" . $content;
	}

	/**
	 * Serializes rows to a PO catalogue.
	 *
	 * @param array<int,array<string,string>> $rows     Rows.
	 * @param Language                        $language Language.
	 * @return string
	 */
	protected function to_po( array $rows, Language $language ): string {
		$lines = array(
			'msgid ""',
			'msgstr ""',
			'"Project-Id-Version: Advanced Language Switcher ' . ALS_VERSION . '\n"',
			'"Report-Msgid-Bugs-To: \n"',
			'"POT-Creation-Date: ' . gmdate( 'Y-m-d H:iO' ) . '\n"',
			'"MIME-Version: 1.0\n"',
			'"Content-Type: text/plain; charset=UTF-8\n"',
			'"Content-Transfer-Encoding: 8bit\n"',
			'"Language: ' . $language->locale . '\n"',
			'"Plural-Forms: nplurals=2; plural=(n != 1);\n"',
			'',
		);

		foreach ( $rows as $row ) {
			if ( '' !== $row['context'] ) {
				$lines[] = '#. context: ' . $this->po_comment( $row['context'] );
			}

			if ( '' !== $row['status'] && 'translated' !== $row['status'] ) {
				$lines[] = '#. status: ' . $this->po_comment( $row['status'] );
			}

			if ( '' !== $row['context'] ) {
				$lines[] = 'msgctxt ' . $this->po_string( $row['context'] );
			}

			$lines[] = 'msgid ' . $this->po_string( $row['source_text'] );
			$lines[] = 'msgstr ' . $this->po_string( $row['translated_text'] );
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Escapes a PO comment.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected function po_comment( string $text ): string {
		return str_replace( array( "\r", "\n" ), ' ', $text );
	}

	/**
	 * Escapes a PO string literal.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected function po_string( string $text ): string {
		$escaped = str_replace(
			array( '\\', '"', "\t", "\r", "\n" ),
			array( '\\\\', '\\"', '\\t', '\\r', '\\n' ),
			$text
		);

		return '"' . $escaped . '"';
	}

	/**
	 * Compiles rows into a binary MO catalogue.
	 *
	 * @param array<int,array<string,string>> $rows Rows.
	 * @return string
	 */
	protected function to_mo( array $rows ): string {
		$entries = array( '' => "Content-Type: text/plain; charset=UTF-8\n" );

		foreach ( $rows as $row ) {
			if ( '' === trim( (string) $row['translated_text'] ) ) {
				continue;
			}

			$key = '' !== $row['context']
				? $row['context'] . "\x04" . $row['source_text']
				: $row['source_text'];

			$entries[ $key ] = (string) $row['translated_text'];
		}

		ksort( $entries );

		$count            = count( $entries );
		$originals        = '';
		$translations     = '';
		$original_table   = '';
		$translation_table = '';

		$header_size    = 28;
		$table_size     = $count * 8;
		$originals_off  = $header_size + ( $table_size * 2 );
		$offset         = $originals_off;

		foreach ( $entries as $original => $translation ) {
			$original_table .= pack( 'VV', strlen( (string) $original ), $offset );
			$originals      .= $original . "\0";
			$offset         += strlen( (string) $original ) + 1;
		}

		foreach ( $entries as $translation ) {
			$translation_table .= pack( 'VV', strlen( $translation ), $offset );
			$translations      .= $translation . "\0";
			$offset            += strlen( $translation ) + 1;
		}

		$header = pack(
			'VVVVVVV',
			0x950412de,               // Magic number.
			0,                        // Revision.
			$count,                   // Number of strings.
			$header_size,             // Offset of the original table.
			$header_size + $table_size, // Offset of the translation table.
			0,                        // Hash table size.
			$header_size + ( $table_size * 2 ) // Hash table offset.
		);

		return $header . $original_table . $translation_table . $originals . $translations;
	}

	/* ---------------------------------------------------------------------
	 * Import
	 * ------------------------------------------------------------------ */

	/**
	 * Imports a translation file.
	 *
	 * @param string $path        Path to the uploaded file.
	 * @param string $filename    Original file name, used to detect the format.
	 * @param int    $language_id Target language.
	 * @param bool   $overwrite   Whether manual translations may be replaced.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function import( string $path, string $filename, int $language_id, bool $overwrite = false ) {
		$language = $this->languages->get_by_id( $language_id );

		if ( ! $language instanceof Language ) {
			return new \WP_Error( 'als_unknown_language', __( 'Unknown language.', 'advanced-language-switcher' ) );
		}

		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'als_unreadable', __( 'The uploaded file could not be read.', 'advanced-language-switcher' ) );
		}

		$size = (int) filesize( $path );

		if ( $size > 20 * MB_IN_BYTES ) {
			return new \WP_Error( 'als_too_large', __( 'The file is larger than the 20 MB import limit.', 'advanced-language-switcher' ) );
		}

		$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		$contents  = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		switch ( $extension ) {
			case 'json':
				$pairs = $this->parse_json( $contents );
				break;

			case 'csv':
				$pairs = $this->parse_csv( $contents );
				break;

			case 'po':
				$pairs = $this->parse_po( $contents );
				break;

			case 'mo':
				$pairs = $this->parse_mo( $contents );
				break;

			default:
				return new \WP_Error(
					'als_unknown_format',
					__( 'Unsupported file type. Use JSON, CSV, PO or MO.', 'advanced-language-switcher' )
				);
		}

		if ( is_wp_error( $pairs ) ) {
			return $pairs;
		}

		if ( ! $pairs ) {
			return new \WP_Error(
				'als_empty_import',
				__( 'No translations were found in that file.', 'advanced-language-switcher' )
			);
		}

		// Validation passed: take a safety backup before writing anything.
		$this->create_backup(
			$language_id,
			sprintf(
				/* translators: %s: import file name. */
				__( 'Before importing %s', 'advanced-language-switcher' ),
				$filename
			)
		);

		$imported = 0;
		$skipped  = 0;
		$created  = 0;

		foreach ( $pairs as $pair ) {
			$source      = (string) $pair['source'];
			$translation = (string) $pair['translation'];
			$context     = (string) ( $pair['context'] ?? '' );

			if ( '' === trim( $source ) || '' === trim( $translation ) ) {
				++$skipped;
				continue;
			}

			$string_id = $this->translations->find_string_id( $source, $context );

			if ( 0 === $string_id ) {
				$string_id = $this->translations->register_string( $source, $context, array( 'object_type' => 'import' ) );
				++$created;
			}

			if ( 0 === $string_id ) {
				++$skipped;
				continue;
			}

			if ( ! $overwrite ) {
				$existing = $this->translations->get_translation_row( $string_id, $language_id );

				if ( $existing && Translation_Manager::STATUS_MANUAL === $existing['status'] ) {
					++$skipped;
					continue;
				}
			}

			$status = (string) ( $pair['status'] ?? Translation_Manager::STATUS_MANUAL );
			$status = array_key_exists( $status, Translation_Manager::statuses() ) ? $status : Translation_Manager::STATUS_MANUAL;

			if ( $this->translations->save_translation( $string_id, $language_id, $translation, $status, 'import' ) ) {
				++$imported;
			} else {
				++$skipped;
			}
		}

		$this->translations->invalidate_language( $language_id );

		return array(
			'message'  => sprintf(
				/* translators: 1: imported count, 2: skipped count. */
				__( '%1$d translations imported, %2$d skipped.', 'advanced-language-switcher' ),
				$imported,
				$skipped
			),
			'imported' => $imported,
			'skipped'  => $skipped,
			'created'  => $created,
		);
	}

	/**
	 * Parses a JSON export.
	 *
	 * @param string $contents File contents.
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	protected function parse_json( string $contents ) {
		$data = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'als_invalid_json', __( 'That file is not valid JSON.', 'advanced-language-switcher' ) );
		}

		$items = $data['items'] ?? $data;

		if ( ! is_array( $items ) ) {
			return new \WP_Error( 'als_invalid_json', __( 'That JSON file has no translation items.', 'advanced-language-switcher' ) );
		}

		$pairs = array();

		foreach ( $items as $key => $item ) {
			if ( is_string( $item ) ) {
				// Flat { "source": "translation" } shape.
				$pairs[] = array(
					'source'      => (string) $key,
					'translation' => $item,
					'context'     => '',
				);

				continue;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$source      = (string) ( $item['source_text'] ?? $item['source'] ?? '' );
			$translation = (string) ( $item['translated_text'] ?? $item['translation'] ?? '' );

			if ( '' === $source ) {
				continue;
			}

			$pairs[] = array(
				'source'      => $source,
				'translation' => $translation,
				'context'     => (string) ( $item['context'] ?? '' ),
				'status'      => (string) ( $item['status'] ?? Translation_Manager::STATUS_MANUAL ),
			);
		}

		return $pairs;
	}

	/**
	 * Parses a CSV export.
	 *
	 * @param string $contents File contents.
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	protected function parse_csv( string $contents ) {
		$contents = preg_replace( '/^\xEF\xBB\xBF/', '', $contents ) ?? $contents;
		$handle   = fopen( 'php://temp', 'r+' );

		if ( ! $handle ) {
			return new \WP_Error( 'als_csv_failed', __( 'The CSV file could not be opened.', 'advanced-language-switcher' ) );
		}

		fwrite( $handle, $contents );
		rewind( $handle );

		$header = fgetcsv( $handle, 0, ',', '"', '' );

		if ( ! is_array( $header ) ) {
			fclose( $handle );

			return new \WP_Error( 'als_csv_empty', __( 'The CSV file is empty.', 'advanced-language-switcher' ) );
		}

		$header = array_map( static fn( $value ) => strtolower( trim( (string) $value ) ), $header );

		$source_index      = array_search( 'source', $header, true );
		$translation_index = array_search( 'translation', $header, true );
		$context_index     = array_search( 'context', $header, true );
		$status_index      = array_search( 'status', $header, true );

		if ( false === $source_index || false === $translation_index ) {
			fclose( $handle );

			return new \WP_Error(
				'als_csv_columns',
				__( 'The CSV file needs at least a "source" and a "translation" column.', 'advanced-language-switcher' )
			);
		}

		$pairs = array();

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			if ( ! is_array( $row ) || ! isset( $row[ $source_index ] ) ) {
				continue;
			}

			$pairs[] = array(
				'source'      => (string) $row[ $source_index ],
				'translation' => (string) ( $row[ $translation_index ] ?? '' ),
				'context'     => false !== $context_index ? (string) ( $row[ $context_index ] ?? '' ) : '',
				'status'      => false !== $status_index ? (string) ( $row[ $status_index ] ?? '' ) : Translation_Manager::STATUS_MANUAL,
			);
		}

		fclose( $handle );

		return $pairs;
	}

	/**
	 * Parses a PO catalogue.
	 *
	 * @param string $contents File contents.
	 * @return array<int,array<string,string>>
	 */
	protected function parse_po( string $contents ): array {
		$pairs   = array();
		$current = array(
			'context'     => '',
			'source'      => '',
			'translation' => '',
		);

		$mode  = '';
		$lines = preg_split( '/\r\n|\r|\n/', $contents );
		$lines = is_array( $lines ) ? $lines : array();

		$flush = static function () use ( &$current, &$pairs ): void {
			if ( '' !== $current['source'] ) {
				$pairs[] = $current;
			}

			$current = array(
				'context'     => '',
				'source'      => '',
				'translation' => '',
			);
		};

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			if ( str_starts_with( $line, 'msgctxt ' ) ) {
				$flush();
				$mode               = 'context';
				$current['context'] = $this->unescape_po( substr( $line, 8 ) );
				continue;
			}

			if ( str_starts_with( $line, 'msgid ' ) ) {
				if ( 'translation' === $mode ) {
					$flush();
				}

				$mode              = 'source';
				$current['source'] = $this->unescape_po( substr( $line, 6 ) );
				continue;
			}

			if ( str_starts_with( $line, 'msgstr ' ) ) {
				$mode                   = 'translation';
				$current['translation'] = $this->unescape_po( substr( $line, 7 ) );
				continue;
			}

			if ( str_starts_with( $line, '"' ) && '' !== $mode ) {
				$key             = 'source' === $mode ? 'source' : ( 'context' === $mode ? 'context' : 'translation' );
				$current[ $key ] .= $this->unescape_po( $line );
			}
		}

		$flush();

		return array_values(
			array_filter(
				$pairs,
				static fn( array $pair ) => '' !== $pair['source'] && '' !== $pair['translation']
			)
		);
	}

	/**
	 * Unescapes a quoted PO literal.
	 *
	 * @param string $literal Quoted literal.
	 * @return string
	 */
	protected function unescape_po( string $literal ): string {
		$literal = trim( $literal );
		$literal = preg_replace( '/^"|"$/', '', $literal ) ?? $literal;

		return str_replace(
			array( '\\n', '\\r', '\\t', '\\"', '\\\\' ),
			array( "\n", "\r", "\t", '"', '\\' ),
			$literal
		);
	}

	/**
	 * Parses a binary MO catalogue.
	 *
	 * @param string $contents File contents.
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	protected function parse_mo( string $contents ) {
		if ( strlen( $contents ) < 28 ) {
			return new \WP_Error( 'als_invalid_mo', __( 'That MO file is not valid.', 'advanced-language-switcher' ) );
		}

		$magic = substr( $contents, 0, 4 );

		if ( "\xde\x12\x04\x95" === $magic ) {
			$format = 'V'; // Little endian.
		} elseif ( "\x95\x04\x12\xde" === $magic ) {
			$format = 'N'; // Big endian.
		} else {
			return new \WP_Error( 'als_invalid_mo', __( 'That MO file is not valid.', 'advanced-language-switcher' ) );
		}

		$header = unpack( $format . 'revision/' . $format . 'count/' . $format . 'original/' . $format . 'translation', substr( $contents, 4, 16 ) );

		if ( ! is_array( $header ) ) {
			return new \WP_Error( 'als_invalid_mo', __( 'That MO file could not be read.', 'advanced-language-switcher' ) );
		}

		$count = (int) $header['count'];
		$pairs = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$original_meta    = unpack( $format . 'length/' . $format . 'offset', substr( $contents, (int) $header['original'] + ( $index * 8 ), 8 ) );
			$translation_meta = unpack( $format . 'length/' . $format . 'offset', substr( $contents, (int) $header['translation'] + ( $index * 8 ), 8 ) );

			if ( ! is_array( $original_meta ) || ! is_array( $translation_meta ) ) {
				continue;
			}

			$original    = substr( $contents, (int) $original_meta['offset'], (int) $original_meta['length'] );
			$translation = substr( $contents, (int) $translation_meta['offset'], (int) $translation_meta['length'] );

			if ( '' === $original ) {
				continue; // Catalogue header.
			}

			$context = '';

			if ( str_contains( $original, "\x04" ) ) {
				list( $context, $original ) = explode( "\x04", $original, 2 );
			}

			// Plural forms are stored NUL separated; only the singular is used.
			$original    = explode( "\0", $original )[0];
			$translation = explode( "\0", $translation )[0];

			$pairs[] = array(
				'source'      => $original,
				'translation' => $translation,
				'context'     => $context,
			);
		}

		return $pairs;
	}

	/* ---------------------------------------------------------------------
	 * Backups
	 * ------------------------------------------------------------------ */

	/**
	 * Snapshots the translations of one language, or of every language.
	 *
	 * @param int    $language_id Language id, 0 for all languages.
	 * @param string $name        Backup label.
	 * @return int|\WP_Error New backup id.
	 */
	public function create_backup( int $language_id = 0, string $name = '' ) {
		if ( ! Database::tables_exist() ) {
			return new \WP_Error( 'als_no_tables', __( 'Plugin tables are missing.', 'advanced-language-switcher' ) );
		}

		global $wpdb;

		$translations = Database::table( 'translations' );

		if ( $language_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$translations} WHERE language_id = %d", $language_id ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( "SELECT * FROM {$translations}", ARRAY_A );
		}

		$rows = is_array( $rows ) ? $rows : array();

		if ( '' === trim( $name ) ) {
			$language = $this->languages->get_by_id( $language_id );

			$name = $language instanceof Language
				? sprintf(
					/* translators: %s: language name. */
					__( '%s backup', 'advanced-language-switcher' ),
					$language->name
				)
				: __( 'Full backup', 'advanced-language-switcher' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			Database::table( 'backups' ),
			array(
				'name'        => substr( sanitize_text_field( $name ), 0, 191 ),
				'language_id' => $language_id,
				'payload'     => (string) wp_json_encode( $rows ),
				'item_count'  => count( $rows ),
				'created_at'  => Database::now(),
			)
		);

		$this->prune_backups();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Keeps only the most recent backups so the table cannot grow forever.
	 *
	 * @param int $keep How many to retain.
	 * @return void
	 */
	protected function prune_backups( int $keep = 20 ): void {
		global $wpdb;

		$table = Database::table( 'backups' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY id DESC LIMIT %d, 1000", $keep ) );

		foreach ( (array) $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'id' => (int) $id ) );
		}
	}

	/**
	 * Lists stored backups.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_backups(): array {
		if ( ! Database::tables_exist() ) {
			return array();
		}

		global $wpdb;

		$table = Database::table( 'backups' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT id, name, language_id, item_count, created_at FROM {$table} ORDER BY id DESC LIMIT 50", ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Restores a backup, replacing the translations it covers.
	 *
	 * @param int $backup_id Backup id.
	 * @return int|\WP_Error Number of restored rows.
	 */
	public function restore_backup( int $backup_id ) {
		if ( ! Database::tables_exist() ) {
			return new \WP_Error( 'als_no_tables', __( 'Plugin tables are missing.', 'advanced-language-switcher' ) );
		}

		global $wpdb;

		$table = Database::table( 'backups' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$backup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $backup_id ), ARRAY_A );

		if ( ! $backup ) {
			return new \WP_Error( 'als_backup_missing', __( 'That backup no longer exists.', 'advanced-language-switcher' ) );
		}

		$rows = json_decode( (string) $backup['payload'], true );

		if ( ! is_array( $rows ) ) {
			return new \WP_Error( 'als_backup_corrupt', __( 'That backup could not be read.', 'advanced-language-switcher' ) );
		}

		$translations = Database::table( 'translations' );
		$language_id  = (int) $backup['language_id'];

		if ( $language_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $translations, array( 'language_id' => $language_id ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DELETE FROM {$translations}" );
		}

		$restored = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			unset( $row['id'] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->insert( $translations, $row ) ) {
				++$restored;
			}
		}

		$this->translations->invalidate_all();

		return $restored;
	}

	/**
	 * Deletes a backup.
	 *
	 * @param int $backup_id Backup id.
	 * @return void
	 */
	public function delete_backup( int $backup_id ): void {
		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Database::table( 'backups' ), array( 'id' => $backup_id ) );
	}
}
