<?php
/**
 * Translation memory: source strings, their translations and the lookup path.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the strings and translations tables.
 *
 * The lookup order implemented here is the one described in the plugin
 * design: current language -> translation database -> cache -> automatic
 * provider -> original content fallback. The original text is *always* the
 * final fallback, so a failing provider can never blank out a page.
 */
class Translation_Manager {

	/**
	 * Translation is finished and human approved.
	 */
	public const STATUS_TRANSLATED = 'translated';

	/**
	 * No translation stored yet.
	 */
	public const STATUS_MISSING = 'missing';

	/**
	 * Machine translated, awaiting review.
	 */
	public const STATUS_AUTOMATIC = 'automatic';

	/**
	 * Edited by a human after machine translation.
	 */
	public const STATUS_MANUAL = 'manual';

	/**
	 * Flagged for review.
	 */
	public const STATUS_REVIEW = 'needs_review';

	/**
	 * Cache service.
	 *
	 * @var Translation_Cache
	 */
	protected Translation_Cache $cache;

	/**
	 * Language service.
	 *
	 * @var Language_Manager
	 */
	protected Language_Manager $languages;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * Per-request map of language_id => (hash => translation).
	 *
	 * @var array<int,array<string,string>>
	 */
	protected array $preloaded = array();

	/**
	 * Languages whose full dictionary has already been preloaded.
	 *
	 * @var array<int,bool>
	 */
	protected array $preloaded_languages = array();

	/**
	 * Constructor.
	 *
	 * @param Translation_Cache $cache     Cache service.
	 * @param Language_Manager  $languages Language service.
	 * @param Logger            $logger    Logger service.
	 */
	public function __construct( Translation_Cache $cache, Language_Manager $languages, Logger $logger ) {
		$this->cache     = $cache;
		$this->languages = $languages;
		$this->logger    = $logger;
	}

	/**
	 * All selectable translation statuses with labels.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_TRANSLATED => __( 'Translated', 'advanced-language-switcher' ),
			self::STATUS_MISSING    => __( 'Missing', 'advanced-language-switcher' ),
			self::STATUS_REVIEW     => __( 'Needs Review', 'advanced-language-switcher' ),
			self::STATUS_AUTOMATIC  => __( 'Automatically Translated', 'advanced-language-switcher' ),
			self::STATUS_MANUAL     => __( 'Manually Edited', 'advanced-language-switcher' ),
		);
	}

	/**
	 * Normalizes a source string so trivial whitespace differences collapse
	 * into one translation memory entry.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function normalize( string $text ): string {
		$text = str_replace( "\xC2\xA0", ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;

		return trim( $text );
	}

	/**
	 * Builds the deduplication hash for a string.
	 *
	 * @param string $text    Source text.
	 * @param string $context Optional context.
	 * @return string
	 */
	public static function hash( string $text, string $context = '' ): string {
		return sha1( self::normalize( $text ) . '||' . $context );
	}

	/**
	 * Whether a piece of text is worth storing as a translatable string.
	 *
	 * Filters out numbers, punctuation-only fragments, URLs, entities and
	 * anything that is clearly technical rather than human readable.
	 *
	 * @param string $text Candidate text.
	 * @return bool
	 */
	public static function is_translatable( string $text ): bool {
		$trimmed = self::normalize( $text );

		if ( '' === $trimmed ) {
			return false;
		}

		// Needs at least one letter in any script.
		if ( ! preg_match( '/\p{L}/u', $trimmed ) ) {
			return false;
		}

		// Single characters are almost always icons or separators.
		if ( mb_strlen( $trimmed ) < 2 ) {
			return false;
		}

		// Plain URLs, anchors, protocol handlers and file paths.
		if ( preg_match( '~^(https?://|//|mailto:|tel:|\#)~i', $trimmed ) ) {
			return false;
		}

		if ( is_email( $trimmed ) ) {
			return false;
		}

		// CSS/JS leftovers and template placeholders on their own.
		if ( preg_match( '/^[\{\}\[\]\(\);:,.\-_=+*\/\\\\|<>@#$%^&~`"\']+$/', $trimmed ) ) {
			return false;
		}

		// Data URIs and base64 blobs.
		if ( preg_match( '/^data:[a-z\/]+;base64,/i', $trimmed ) ) {
			return false;
		}

		/**
		 * Filters whether a string should enter the translation memory.
		 *
		 * @param bool   $translatable Current decision.
		 * @param string $trimmed      Normalized text.
		 */
		return (bool) apply_filters( 'als_is_translatable_string', true, $trimmed );
	}

	/* ---------------------------------------------------------------------
	 * Source strings
	 * ------------------------------------------------------------------ */

	/**
	 * Registers a source string, returning its id. Existing strings are reused.
	 *
	 * @param string              $text Source text.
	 * @param string              $context Context label, e.g. "elementor:heading".
	 * @param array<string,mixed> $meta Optional metadata columns.
	 * @return int String id, or 0 when the text is not translatable.
	 */
	public function register_string( string $text, string $context = '', array $meta = array() ): int {
		if ( ! self::is_translatable( $text ) ) {
			return 0;
		}

		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$normalized = self::normalize( $text );
		$hash       = self::hash( $normalized, $context );
		$table      = Database::table( 'strings' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_hash = %s LIMIT 1", $hash ) );

		if ( $existing ) {
			return (int) $existing;
		}

		$row = array(
			'source_hash'     => $hash,
			'source_text'     => $normalized,
			'context'         => substr( $context, 0, 191 ),
			'source_location' => substr( (string) ( $meta['source_location'] ?? '' ), 0, 191 ),
			'element_id'      => substr( (string) ( $meta['element_id'] ?? '' ), 0, 100 ),
			'widget_type'     => substr( (string) ( $meta['widget_type'] ?? '' ), 0, 100 ),
			'object_type'     => substr( (string) ( $meta['object_type'] ?? '' ), 0, 50 ),
			'object_id'       => (int) ( $meta['object_id'] ?? 0 ),
			'group_id'        => (int) ( $meta['group_id'] ?? 0 ),
			'is_html'         => (int) ( $meta['is_html'] ?? ( $normalized !== wp_strip_all_tags( $normalized ) ) ),
			'created_at'      => Database::now(),
			'updated_at'      => Database::now(),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, $row );

		$id = (int) $wpdb->insert_id;

		if ( $id > 0 ) {
			/**
			 * Fires when a new translatable string is discovered.
			 *
			 * @param int                 $id  String id.
			 * @param array<string,mixed> $row Stored columns.
			 */
			do_action( 'als_string_registered', $id, $row );
		}

		return $id;
	}

	/**
	 * Registers many strings in one batch, skipping duplicates efficiently.
	 *
	 * @param array<int,array{text:string,context?:string,meta?:array<string,mixed>}> $items Strings.
	 * @return int Number of new rows created.
	 */
	public function register_strings( array $items ): int {
		$created = 0;

		foreach ( $items as $item ) {
			$text = (string) ( $item['text'] ?? '' );

			if ( ! self::is_translatable( $text ) ) {
				continue;
			}

			$context = (string) ( $item['context'] ?? '' );
			$meta    = (array) ( $item['meta'] ?? array() );

			if ( $this->find_string_id( $text, $context ) > 0 ) {
				continue;
			}

			if ( $this->register_string( $text, $context, $meta ) > 0 ) {
				++$created;
			}
		}

		return $created;
	}

	/**
	 * Looks up a string id by text + context.
	 *
	 * @param string $text    Source text.
	 * @param string $context Context.
	 * @return int
	 */
	public function find_string_id( string $text, string $context = '' ): int {
		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$table = Database::table( 'strings' );
		$hash  = self::hash( $text, $context );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_hash = %s LIMIT 1", $hash ) );
	}

	/**
	 * Fetches a single string row.
	 *
	 * @param int $id String id.
	 * @return array<string,mixed>|null
	 */
	public function get_string( int $id ): ?array {
		if ( ! Database::tables_exist() ) {
			return null;
		}

		global $wpdb;

		$table = Database::table( 'strings' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Deletes a string and all of its translations.
	 *
	 * @param int $id String id.
	 * @return void
	 */
	public function delete_string( int $id ): void {
		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Database::table( 'translations' ), array( 'string_id' => $id ) );
		$wpdb->delete( Database::table( 'strings' ), array( 'id' => $id ) );
		// phpcs:enable
	}

	/* ---------------------------------------------------------------------
	 * Querying for the admin UI
	 * ------------------------------------------------------------------ */

	/**
	 * Queries strings joined with the translation for one language.
	 *
	 * @param array<string,mixed> $args {
	 *     @type int    $language_id Language to join.
	 *     @type string $search      Free text search.
	 *     @type string $status      Status filter.
	 *     @type string $object_type Object type filter.
	 *     @type string $widget_type Widget type filter.
	 *     @type int    $per_page    Page size.
	 *     @type int    $page        1-based page number.
	 *     @type string $orderby     Column to order by.
	 *     @type string $order       ASC or DESC.
	 * }
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		if ( ! Database::tables_exist() ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
			);
		}

		global $wpdb;

		$defaults = array(
			'language_id' => 0,
			'search'      => '',
			'status'      => '',
			'object_type' => '',
			'widget_type' => '',
			'context'     => '',
			'per_page'    => 25,
			'page'        => 1,
			'orderby'     => 'id',
			'order'       => 'DESC',
		);

		$args = array_merge( $defaults, $args );

		$strings      = Database::table( 'strings' );
		$translations = Database::table( 'translations' );

		$language_id = (int) $args['language_id'];
		$per_page    = max( 1, min( 200, (int) $args['per_page'] ) );
		$page        = max( 1, (int) $args['page'] );
		$offset      = ( $page - 1 ) * $per_page;

		$allowed_orderby = array( 'id', 'source_text', 'context', 'updated_at', 'widget_type' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$where  = array( '1=1' );
		$params = array( $language_id );

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(s.source_text LIKE %s OR t.translated_text LIKE %s OR s.context LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $args['object_type'] ) {
			$where[]  = 's.object_type = %s';
			$params[] = (string) $args['object_type'];
		}

		if ( '' !== $args['widget_type'] ) {
			$where[]  = 's.widget_type = %s';
			$params[] = (string) $args['widget_type'];
		}

		if ( '' !== $args['context'] ) {
			$where[]  = 's.context LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['context'] ) . '%';
		}

		$status = (string) $args['status'];

		if ( self::STATUS_MISSING === $status ) {
			$where[] = "(t.id IS NULL OR t.translated_text = '' OR t.status = 'missing')";
		} elseif ( '' !== $status && array_key_exists( $status, self::statuses() ) ) {
			$where[]  = 't.status = %s';
			$params[] = $status;
		}

		$where_sql = implode( ' AND ', $where );

		$base = "FROM {$strings} s LEFT JOIN {$translations} t ON t.string_id = s.id AND t.language_id = %d WHERE {$where_sql}";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(s.id) {$base}", $params ) );

		$select = "SELECT s.*, t.id AS translation_id, t.translated_text, t.status AS translation_status, t.provider, t.updated_at AS translated_at {$base} ORDER BY s.{$orderby} {$order} LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $select, array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['translation_status'] = $this->resolve_status( $row );
			$rows[ $index ]['translated_text']    = (string) ( $row['translated_text'] ?? '' );
		}

		return array(
			'items' => $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Normalizes a joined row's status.
	 *
	 * @param array<string,mixed> $row Joined row.
	 * @return string
	 */
	protected function resolve_status( array $row ): string {
		$stored = (string) ( $row['translation_status'] ?? '' );
		$text   = (string) ( $row['translated_text'] ?? '' );

		if ( '' === $text ) {
			return self::STATUS_MISSING;
		}

		return '' !== $stored ? $stored : self::STATUS_TRANSLATED;
	}

	/* ---------------------------------------------------------------------
	 * Translations
	 * ------------------------------------------------------------------ */

	/**
	 * Saves a translation for a string.
	 *
	 * @param int    $string_id   String id.
	 * @param int    $language_id Language id.
	 * @param string $text        Translated text.
	 * @param string $status      Translation status.
	 * @param string $provider    Provider slug that produced it.
	 * @return bool
	 */
	public function save_translation( int $string_id, int $language_id, string $text, string $status = self::STATUS_MANUAL, string $provider = 'manual' ): bool {
		if ( ! Database::tables_exist() || $string_id <= 0 || $language_id <= 0 ) {
			return false;
		}

		global $wpdb;

		$string = $this->get_string( $string_id );

		if ( ! $string ) {
			return false;
		}

		$table    = Database::table( 'translations' );
		$statuses = self::statuses();
		$status   = array_key_exists( $status, $statuses ) ? $status : self::STATUS_MANUAL;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE string_id = %d AND language_id = %d LIMIT 1",
				$string_id,
				$language_id
			)
		);

		$data = array(
			'string_id'       => $string_id,
			'language_id'     => $language_id,
			'translated_text' => $text,
			'status'          => '' === trim( $text ) ? self::STATUS_MISSING : $status,
			'provider'        => substr( $provider, 0, 50 ),
			'context'         => (string) $string['context'],
			'source_hash'     => (string) $string['source_hash'],
			'updated_at'      => Database::now(),
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
		} else {
			$data['created_at'] = Database::now();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $table, $data );
		}

		$this->invalidate_language( $language_id );

		/**
		 * Fires after a translation is stored.
		 *
		 * @param int    $string_id   String id.
		 * @param int    $language_id Language id.
		 * @param string $text        Translated text.
		 * @param string $status      Stored status.
		 */
		do_action( 'als_translation_saved', $string_id, $language_id, $text, $data['status'] );

		return true;
	}

	/**
	 * Stores a translation for raw text, registering the source string first.
	 *
	 * @param string $source      Source text.
	 * @param string $translation Translated text.
	 * @param int    $language_id Language id.
	 * @param string $context     Context.
	 * @param string $status      Status.
	 * @param string $provider    Provider slug.
	 * @return bool
	 */
	public function save_translation_for_text( string $source, string $translation, int $language_id, string $context = '', string $status = self::STATUS_MANUAL, string $provider = 'manual' ): bool {
		$string_id = $this->find_string_id( $source, $context );

		if ( 0 === $string_id ) {
			$string_id = $this->register_string( $source, $context );
		}

		if ( 0 === $string_id ) {
			return false;
		}

		return $this->save_translation( $string_id, $language_id, $translation, $status, $provider );
	}

	/**
	 * Clears a stored translation.
	 *
	 * @param int $string_id   String id.
	 * @param int $language_id Language id.
	 * @return void
	 */
	public function reset_translation( int $string_id, int $language_id ): void {
		if ( ! Database::tables_exist() ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			Database::table( 'translations' ),
			array(
				'string_id'   => $string_id,
				'language_id' => $language_id,
			)
		);

		$this->invalidate_language( $language_id );
	}

	/**
	 * Reads a stored translation row.
	 *
	 * @param int $string_id   String id.
	 * @param int $language_id Language id.
	 * @return array<string,mixed>|null
	 */
	public function get_translation_row( int $string_id, int $language_id ): ?array {
		if ( ! Database::tables_exist() ) {
			return null;
		}

		global $wpdb;

		$table = Database::table( 'translations' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE string_id = %d AND language_id = %d LIMIT 1",
				$string_id,
				$language_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/* ---------------------------------------------------------------------
	 * Rendering lookups
	 * ------------------------------------------------------------------ */

	/**
	 * Loads the full dictionary for a language into request memory.
	 *
	 * One query per language per request replaces thousands of small lookups
	 * while a page is being rewritten. The dictionary is also cached so the
	 * query itself is skipped on subsequent requests.
	 *
	 * @param int $language_id Language id.
	 * @return array<string,string> hash => translation.
	 */
	public function get_dictionary( int $language_id ): array {
		if ( isset( $this->preloaded_languages[ $language_id ] ) ) {
			return $this->preloaded[ $language_id ] ?? array();
		}

		$this->preloaded_languages[ $language_id ] = true;
		$this->preloaded[ $language_id ]           = array();

		if ( ! Database::tables_exist() || $language_id <= 0 ) {
			return array();
		}

		$cache_key = $this->cache->key( 'dictionary', (string) $language_id );
		$cached    = $this->cache->get( $cache_key, 'dictionary' );

		if ( is_array( $cached ) ) {
			$this->preloaded[ $language_id ] = $cached;

			return $cached;
		}

		global $wpdb;

		$translations = Database::table( 'translations' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_hash, translated_text FROM {$translations}
				 WHERE language_id = %d AND translated_text <> '' AND status <> 'missing'",
				$language_id
			),
			ARRAY_A
		);

		$dictionary = array();

		foreach ( (array) $rows as $row ) {
			$dictionary[ (string) $row['source_hash'] ] = (string) $row['translated_text'];
		}

		$this->preloaded[ $language_id ] = $dictionary;
		$this->cache->set( $cache_key, $dictionary, 'dictionary', 0, $language_id );

		return $dictionary;
	}

	/**
	 * Returns a stored translation for a piece of text, or null.
	 *
	 * Context specific entries win over global ones, which is what makes
	 * "Read More" translatable differently per widget.
	 *
	 * @param string        $text     Source text.
	 * @param Language|null $language Target language; current language when null.
	 * @param string        $context  Optional context.
	 * @return string|null
	 */
	public function lookup( string $text, ?Language $language = null, string $context = '' ): ?string {
		if ( ! $language instanceof Language ) {
			return null;
		}

		$dictionary = $this->get_dictionary( $language->id );

		if ( '' !== $context ) {
			$contextual = self::hash( $text, $context );

			if ( isset( $dictionary[ $contextual ] ) ) {
				return $dictionary[ $contextual ];
			}
		}

		$global = self::hash( $text, '' );

		return $dictionary[ $global ] ?? null;
	}

	/**
	 * Full translation path for a piece of text.
	 *
	 * @param string        $text     Source text.
	 * @param Language|null $language Target language.
	 * @param string        $context  Optional context.
	 * @param array         $args     Extra options: register (bool), allow_auto (bool), is_html (bool).
	 * @return string Translated text, or the original when nothing is available.
	 */
	public function translate( string $text, ?Language $language = null, string $context = '', array $args = array() ): string {
		if ( '' === trim( $text ) ) {
			return $text;
		}

		if ( ! $language instanceof Language ) {
			$language = plugin()->router()->get_current_language();
		}

		if ( ! $language instanceof Language ) {
			return $text;
		}

		$default = $this->languages->get_default();

		if ( $default instanceof Language && $default->id === $language->id ) {
			return $text;
		}

		/**
		 * Fires before a string is translated.
		 *
		 * @param string   $text     Source text.
		 * @param Language $language Target language.
		 * @param string   $context  Context.
		 */
		do_action( 'als_before_translate', $text, $language, $context );

		/**
		 * Short circuits translation. Returning a string skips every lookup.
		 *
		 * @param string|null $pre      Null to continue.
		 * @param string      $text     Source text.
		 * @param Language    $language Target language.
		 * @param string      $context  Context.
		 */
		$pre = apply_filters( 'als_pre_translate', null, $text, $language, $context );

		if ( is_string( $pre ) ) {
			return $pre;
		}

		$stored = $this->lookup( $text, $language, $context );

		if ( null !== $stored && '' !== $stored ) {
			return $this->finish( $stored, $text, $language, $context );
		}

		$register = $args['register'] ?? true;

		if ( $register && Settings::is_enabled( 'auto_translate_new' ) ) {
			$this->register_string( $text, $context, $args['meta'] ?? array() );
		}

		$allow_auto = $args['allow_auto'] ?? Settings::is_enabled( 'auto_translate_on_render' );

		if ( $allow_auto ) {
			$translated = $this->translate_via_provider( $text, $language, $context, (bool) ( $args['is_html'] ?? false ) );

			if ( is_string( $translated ) && '' !== $translated ) {
				return $this->finish( $translated, $text, $language, $context );
			}
		}

		// Final fallback: the original text. Never blank, never broken.
		return $this->finish( $text, $text, $language, $context );
	}

	/**
	 * Applies the post-translation filter chain.
	 *
	 * @param string   $translated Translated text.
	 * @param string   $original   Original text.
	 * @param Language $language   Target language.
	 * @param string   $context    Context.
	 * @return string
	 */
	protected function finish( string $translated, string $original, Language $language, string $context ): string {
		/**
		 * Filters the final translated string.
		 *
		 * @param string   $translated Translated text.
		 * @param string   $original   Original text.
		 * @param Language $language   Target language.
		 * @param string   $context    Context.
		 */
		$translated = (string) apply_filters( 'als_translation_string', $translated, $original, $language, $context );

		/**
		 * Fires after a string has been translated.
		 *
		 * @param string   $translated Translated text.
		 * @param string   $original   Original text.
		 * @param Language $language   Target language.
		 */
		do_action( 'als_after_translate', $translated, $original, $language );

		return $translated;
	}

	/**
	 * Sends one string to the configured provider and stores the result.
	 *
	 * @param string   $text     Source text.
	 * @param Language $language Target language.
	 * @param string   $context  Context.
	 * @param bool     $is_html  Whether the payload contains markup.
	 * @return string|null
	 */
	protected function translate_via_provider( string $text, Language $language, string $context = '', bool $is_html = false ): ?string {
		$engine = plugin()->engine();

		if ( ! $engine->is_automatic() ) {
			return null;
		}

		$source = $this->languages->get_default();
		$result = $engine->translate_batch( array( $text ), $source, $language, $is_html );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Provider translation failed: ' . $result->get_error_message(),
				array(
					'provider' => $engine->get_provider_slug(),
					'language' => $language->code,
				)
			);

			return null;
		}

		$translated = $result[0] ?? null;

		if ( ! is_string( $translated ) || '' === trim( $translated ) ) {
			return null;
		}

		$this->save_translation_for_text(
			$text,
			$translated,
			$language->id,
			$context,
			self::STATUS_AUTOMATIC,
			$engine->get_provider_slug()
		);

		return $translated;
	}

	/* ---------------------------------------------------------------------
	 * Bulk translation
	 * ------------------------------------------------------------------ */

	/**
	 * Returns string ids that still need a translation for a language.
	 *
	 * @param int    $language_id Language id.
	 * @param int    $limit       Maximum ids.
	 * @param string $scope       "missing" or "all".
	 * @return array<int,array{id:int,source_text:string,context:string,is_html:int}>
	 */
	public function get_pending_strings( int $language_id, int $limit = 25, string $scope = 'missing' ): array {
		if ( ! Database::tables_exist() ) {
			return array();
		}

		global $wpdb;

		$strings      = Database::table( 'strings' );
		$translations = Database::table( 'translations' );
		$limit        = max( 1, min( 200, $limit ) );

		if ( 'all' === $scope ) {
			$condition = Settings::is_enabled( 'overwrite_manual' )
				? '1=1'
				: "(t.id IS NULL OR t.status IN ('missing','automatic'))";
		} else {
			$condition = "(t.id IS NULL OR t.translated_text = '' OR t.status = 'missing')";
		}

		$sql = "SELECT s.id, s.source_text, s.context, s.is_html
			FROM {$strings} s
			LEFT JOIN {$translations} t ON t.string_id = s.id AND t.language_id = %d
			WHERE {$condition}
			ORDER BY s.id ASC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $language_id, $limit ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Counts strings still pending translation for a language.
	 *
	 * @param int    $language_id Language id.
	 * @param string $scope       "missing" or "all".
	 * @return int
	 */
	public function count_pending( int $language_id, string $scope = 'missing' ): int {
		if ( ! Database::tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$strings      = Database::table( 'strings' );
		$translations = Database::table( 'translations' );

		if ( 'all' === $scope ) {
			$condition = Settings::is_enabled( 'overwrite_manual' )
				? '1=1'
				: "(t.id IS NULL OR t.status IN ('missing','automatic'))";
		} else {
			$condition = "(t.id IS NULL OR t.translated_text = '' OR t.status = 'missing')";
		}

		$sql = "SELECT COUNT(s.id)
			FROM {$strings} s
			LEFT JOIN {$translations} t ON t.string_id = s.id AND t.language_id = %d
			WHERE {$condition}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $language_id ) );
	}

	/**
	 * Translates a batch of pending strings for one language.
	 *
	 * Failures are logged and reported, never fatal: the strings simply stay
	 * pending and the original text keeps rendering on the front end.
	 *
	 * @param int    $language_id Language id.
	 * @param int    $batch_size  How many strings to process.
	 * @param string $scope       "missing" or "all".
	 * @param int[]  $string_ids  Optional explicit id list ("selected" mode).
	 * @return array{processed:int,translated:int,failed:int,remaining:int,message:string}
	 */
	public function translate_batch( int $language_id, int $batch_size = 0, string $scope = 'missing', array $string_ids = array() ): array {
		$language = $this->languages->get_by_id( $language_id );
		$source   = $this->languages->get_default();

		$result = array(
			'processed'  => 0,
			'translated' => 0,
			'failed'     => 0,
			'remaining'  => 0,
			'message'    => '',
		);

		if ( ! $language instanceof Language ) {
			$result['message'] = __( 'Unknown language.', 'advanced-language-switcher' );

			return $result;
		}

		$engine = plugin()->engine();

		if ( ! $engine->is_automatic() ) {
			$result['message'] = __( 'Automatic translation requires a translation provider other than Manual.', 'advanced-language-switcher' );

			return $result;
		}

		$batch_size = $batch_size > 0 ? $batch_size : Settings::get_int( 'batch_size', 25 );

		if ( $string_ids ) {
			$rows = array();

			foreach ( array_slice( $string_ids, 0, $batch_size ) as $id ) {
				$row = $this->get_string( (int) $id );

				if ( $row ) {
					$rows[] = $row;
				}
			}
		} else {
			$rows = $this->get_pending_strings( $language_id, $batch_size, $scope );
		}

		if ( ! $rows ) {
			$result['message'] = __( 'Nothing left to translate.', 'advanced-language-switcher' );

			return $result;
		}

		$texts = array_map( static fn( $row ) => (string) $row['source_text'], $rows );
		$html  = false;

		foreach ( $rows as $row ) {
			if ( ! empty( $row['is_html'] ) ) {
				$html = true;
				break;
			}
		}

		$translations = $engine->translate_batch( $texts, $source, $language, $html );

		if ( is_wp_error( $translations ) ) {
			$result['failed']  = count( $rows );
			$result['message'] = $translations->get_error_message();

			$this->logger->error(
				'Batch translation failed: ' . $translations->get_error_message(),
				array(
					'provider' => $engine->get_provider_slug(),
					'language' => $language->code,
					'count'    => count( $rows ),
				)
			);

			$result['remaining'] = $this->count_pending( $language_id, $scope );

			return $result;
		}

		foreach ( $rows as $index => $row ) {
			++$result['processed'];

			$translated = $translations[ $index ] ?? '';

			if ( ! is_string( $translated ) || '' === trim( $translated ) ) {
				++$result['failed'];
				continue;
			}

			$this->save_translation(
				(int) $row['id'],
				$language_id,
				$translated,
				self::STATUS_AUTOMATIC,
				$engine->get_provider_slug()
			);

			++$result['translated'];
		}

		$result['remaining'] = $this->count_pending( $language_id, $scope );

		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Statistics
	 * ------------------------------------------------------------------ */

	/**
	 * Per-language completion statistics.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function language_stats(): array {
		if ( ! Database::tables_exist() ) {
			return array();
		}

		global $wpdb;

		$strings      = Database::table( 'strings' );
		$translations = Database::table( 'translations' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$strings}" );

		$stats   = array();
		$default = $this->languages->get_default();

		foreach ( $this->languages->all() as $language ) {
			if ( $default instanceof Language && $language->id === $default->id ) {
				$stats[] = array(
					'language'   => $language,
					'total'      => $total,
					'translated' => $total,
					'missing'    => 0,
					'review'     => 0,
					'automatic'  => 0,
					'percent'    => 100.0,
					'is_source'  => true,
				);

				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$translated = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$translations} WHERE language_id = %d AND translated_text <> '' AND status <> 'missing'",
					$language->id
				)
			);

			$review = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$translations} WHERE language_id = %d AND status = %s",
					$language->id,
					self::STATUS_REVIEW
				)
			);

			$automatic = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$translations} WHERE language_id = %d AND status = %s",
					$language->id,
					self::STATUS_AUTOMATIC
				)
			);
			// phpcs:enable

			$stats[] = array(
				'language'   => $language,
				'total'      => $total,
				'translated' => $translated,
				'missing'    => max( 0, $total - $translated ),
				'review'     => $review,
				'automatic'  => $automatic,
				'percent'    => $total > 0 ? round( ( $translated / $total ) * 100, 1 ) : 0.0,
				'is_source'  => false,
			);
		}

		return $stats;
	}

	/**
	 * Aggregate totals used by the dashboard cards.
	 *
	 * @return array<string,mixed>
	 */
	public function global_stats(): array {
		$per_language = $this->language_stats();
		$db           = Database::stats();

		$translated = 0;
		$expected   = 0;

		foreach ( $per_language as $row ) {
			if ( ! empty( $row['is_source'] ) ) {
				continue;
			}

			$translated += (int) $row['translated'];
			$expected   += (int) $row['total'];
		}

		return array(
			'languages'   => $db['languages'],
			'strings'     => $db['strings'],
			'translated'  => $translated,
			'missing'     => max( 0, $expected - $translated ),
			'cached'      => $db['cache'],
			'completion'  => $expected > 0 ? round( ( $translated / $expected ) * 100, 1 ) : 0.0,
			'per_language' => $per_language,
		);
	}

	/**
	 * Drops cached dictionaries for a language.
	 *
	 * @param int $language_id Language id.
	 * @return void
	 */
	public function invalidate_language( int $language_id ): void {
		unset( $this->preloaded[ $language_id ], $this->preloaded_languages[ $language_id ] );

		$this->cache->delete( $this->cache->key( 'dictionary', (string) $language_id ) );
		$this->cache->flush( 'page', $language_id );
	}

	/**
	 * Drops every cached dictionary.
	 *
	 * @return void
	 */
	public function invalidate_all(): void {
		$this->preloaded           = array();
		$this->preloaded_languages = array();

		$this->cache->flush();
	}
}
