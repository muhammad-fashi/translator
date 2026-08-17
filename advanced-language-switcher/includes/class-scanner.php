<?php
/**
 * Discovers translatable content across the site.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Walks posts, Elementor documents, taxonomies, menus and widgets and records
 * every human readable string it finds.
 *
 * The scan is chunked so it can run from the browser without ever hitting a
 * PHP time limit: each AJAX call processes one slice and returns the offset
 * for the next.
 */
class Scanner {

	/**
	 * Rows processed per step.
	 */
	protected const CHUNK = 20;

	/**
	 * Elementor control keys that always hold human readable text.
	 *
	 * @var string[]
	 */
	protected const ELEMENTOR_KEYS = array(
		'title',
		'editor',
		'text',
		'description',
		'description_text',
		'heading',
		'sub_heading',
		'caption',
		'alert_title',
		'alert_description',
		'button_text',
		'testimonial_content',
		'testimonial_name',
		'testimonial_job',
		'tab_title',
		'tab_content',
		'inner_text',
		'price',
		'period',
		'ribbon_title',
		'feature_list_item',
		'before_text',
		'highlighted_text',
		'rotating_text',
		'after_text',
		'form_name',
		'field_label',
		'field_value',
		'placeholder',
		'success_message',
		'error_message',
		'required_field_message',
		'invalid_message',
		'label',
		'item_title',
		'item_description',
		'counter_title',
		'counter_prefix',
		'counter_suffix',
		'read_more_text',
		'nothing_found_message',
		'quote',
		'author',
		'cite',
		'excerpt',
		'toggle_title',
		'toggle_content',
		'accordion_title',
		'accordion_content',
	);

	/**
	 * Suffixes that identify additional text-bearing control keys.
	 *
	 * @var string[]
	 */
	protected const ELEMENTOR_KEY_SUFFIXES = array( '_text', '_title', '_label', '_description', '_content', '_message', '_placeholder', '_caption', '_heading', '_name' );

	/**
	 * Keys that must never be treated as text even if they look like it.
	 *
	 * @var string[]
	 */
	protected const ELEMENTOR_SKIP_KEYS = array(
		'_id',
		'id',
		'url',
		'link',
		'css',
		'custom_css',
		'selector',
		'css_classes',
		'_css_classes',
		'_element_id',
		'shortcode',
		'html',
		'image',
		'background_image',
		'font_family',
		'icon',
		'library',
		'value',
		'size',
		'unit',
		'widgetType',
		'elType',
		'templateID',
		'form_id',
		'field_type',
		'custom_id',
		'mailchimp_api_key',
		'redirect_to',
	);

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
	 * The ordered list of scan steps.
	 *
	 * @return string[]
	 */
	public function steps(): array {
		$steps = array( 'posts', 'terms', 'menus', 'widgets', 'theme' );

		/**
		 * Filters the scanner steps.
		 *
		 * @param string[] $steps Step slugs.
		 */
		return apply_filters( 'als_scanner_steps', $steps );
	}

	/**
	 * Runs one scan step.
	 *
	 * @param string $step   Step slug, or "start".
	 * @param int    $offset Offset within the step.
	 * @return array<string,mixed>
	 */
	public function run_step( string $step, int $offset = 0 ): array {
		$steps = $this->steps();

		if ( 'start' === $step ) {
			update_option( 'als_last_scan_started', time(), false );

			return array(
				'step'     => $steps[0],
				'offset'   => 0,
				'done'     => false,
				'found'    => 0,
				'total'    => 0,
				'label'    => $this->step_label( $steps[0] ),
				'counters' => $this->reset_counters(),
			);
		}

		if ( ! in_array( $step, $steps, true ) ) {
			return $this->finish();
		}

		$method = 'scan_' . $step;

		if ( ! method_exists( $this, $method ) ) {
			return $this->advance( $step, 0, 0 );
		}

		$result = $this->$method( $offset );

		$counters = $this->bump_counters( $step, (int) $result['processed'], (int) $result['found'] );

		if ( ! empty( $result['has_more'] ) ) {
			return array(
				'step'     => $step,
				'offset'   => (int) $result['next_offset'],
				'done'     => false,
				'found'    => (int) $result['found'],
				'total'    => (int) $result['total'],
				'label'    => $this->step_label( $step ),
				'counters' => $counters,
			);
		}

		return $this->advance( $step, (int) $result['found'], (int) $result['total'] );
	}

	/**
	 * Moves to the next step, or finishes.
	 *
	 * @param string $step  Current step.
	 * @param int    $found Strings found in the finished step.
	 * @param int    $total Items in the finished step.
	 * @return array<string,mixed>
	 */
	protected function advance( string $step, int $found, int $total ): array {
		$steps = $this->steps();
		$index = array_search( $step, $steps, true );

		if ( false === $index || ! isset( $steps[ (int) $index + 1 ] ) ) {
			return $this->finish();
		}

		$next = $steps[ (int) $index + 1 ];

		return array(
			'step'     => $next,
			'offset'   => 0,
			'done'     => false,
			'found'    => $found,
			'total'    => $total,
			'label'    => $this->step_label( $next ),
			'counters' => $this->get_counters(),
		);
	}

	/**
	 * Builds the final response.
	 *
	 * @return array<string,mixed>
	 */
	protected function finish(): array {
		update_option( 'als_last_scan', time(), false );

		$counters = $this->get_counters();

		return array(
			'step'     => 'done',
			'offset'   => 0,
			'done'     => true,
			'found'    => (int) ( $counters['strings'] ?? 0 ),
			'total'    => (int) ( $counters['strings'] ?? 0 ),
			'label'    => __( 'Scan complete', 'advanced-language-switcher' ),
			'counters' => $counters,
		);
	}

	/**
	 * Human label for a step.
	 *
	 * @param string $step Step slug.
	 * @return string
	 */
	protected function step_label( string $step ): string {
		$labels = array(
			'posts'   => __( 'Scanning posts, pages and products', 'advanced-language-switcher' ),
			'terms'   => __( 'Scanning categories and attributes', 'advanced-language-switcher' ),
			'menus'   => __( 'Scanning menus', 'advanced-language-switcher' ),
			'widgets' => __( 'Scanning widgets', 'advanced-language-switcher' ),
			'theme'   => __( 'Scanning theme strings', 'advanced-language-switcher' ),
		);

		return $labels[ $step ] ?? $step;
	}

	/* ---------------------------------------------------------------------
	 * Counters
	 * ------------------------------------------------------------------ */

	/**
	 * Resets the running counters.
	 *
	 * @return array<string,int>
	 */
	protected function reset_counters(): array {
		$counters = array(
			'posts'     => 0,
			'elementor' => 0,
			'products'  => 0,
			'terms'     => 0,
			'menus'     => 0,
			'widgets'   => 0,
			'theme'     => 0,
			'strings'   => 0,
		);

		update_option( 'als_scan_counters', $counters, false );

		return $counters;
	}

	/**
	 * Reads the running counters.
	 *
	 * @return array<string,int>
	 */
	protected function get_counters(): array {
		$counters = get_option( 'als_scan_counters', array() );

		return is_array( $counters ) ? $counters : $this->reset_counters();
	}

	/**
	 * Updates the running counters.
	 *
	 * @param string $step      Step slug.
	 * @param int    $processed Items processed.
	 * @param int    $found     Strings found.
	 * @return array<string,int>
	 */
	protected function bump_counters( string $step, int $processed, int $found ): array {
		$counters = $this->get_counters();

		$counters[ $step ]     = ( $counters[ $step ] ?? 0 ) + $processed;
		$counters['strings']   = ( $counters['strings'] ?? 0 ) + $found;

		update_option( 'als_scan_counters', $counters, false );

		return $counters;
	}

	/* ---------------------------------------------------------------------
	 * Steps
	 * ------------------------------------------------------------------ */

	/**
	 * Post types included in the scan, honouring the scope settings.
	 *
	 * @return string[]
	 */
	public function scannable_post_types(): array {
		$types = array();

		if ( Settings::is_enabled( 'translate_pages' ) ) {
			$types[] = 'page';
		}

		if ( Settings::is_enabled( 'translate_posts' ) ) {
			$types[] = 'post';

			foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
				if ( in_array( $type, array( 'attachment', 'page', 'post', 'product' ), true ) ) {
					continue;
				}

				$types[] = $type;
			}
		}

		if ( Settings::is_enabled( 'translate_products' ) && post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}

		if ( Settings::is_enabled( 'translate_elementor' ) && post_type_exists( 'elementor_library' ) ) {
			$types[] = 'elementor_library';
		}

		/**
		 * Filters the post types included in a scan.
		 *
		 * @param string[] $types Post types.
		 */
		return array_values( array_unique( apply_filters( 'als_scannable_post_types', $types ) ) );
	}

	/**
	 * Scans a slice of posts.
	 *
	 * @param int $offset Offset.
	 * @return array<string,mixed>
	 */
	protected function scan_posts( int $offset ): array {
		$types = $this->scannable_post_types();

		if ( ! $types ) {
			return $this->step_result( 0, 0, 0, false );
		}

		$excluded = array_map( 'absint', Settings::get_lines( 'excluded_post_ids' ) );

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'private', 'draft' ),
				'posts_per_page'         => self::CHUNK,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
				'post__not_in'           => array_filter( $excluded ),
			)
		);

		$found     = 0;
		$processed = 0;

		foreach ( $query->posts as $post ) {
			++$processed;
			$found += $this->scan_post( $post );
		}

		$total    = (int) $query->found_posts;
		$next     = $offset + self::CHUNK;
		$has_more = $next < $total;

		return $this->step_result( $found, $processed, $total, $has_more, $next );
	}

	/**
	 * Scans one post: title, excerpt, content and Elementor data.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int Strings recorded.
	 */
	public function scan_post( \WP_Post $post ): int {
		$found   = 0;
		$context = 'post:' . $post->post_type;

		$meta = array(
			'object_type'     => $post->post_type,
			'object_id'       => $post->ID,
			'source_location' => get_permalink( $post ) ?: '',
		);

		if ( '' !== trim( $post->post_title ) ) {
			$found += $this->translations->register_string( $post->post_title, $context, $meta ) > 0 ? 1 : 0;
		}

		if ( '' !== trim( $post->post_excerpt ) ) {
			$found += $this->translations->register_string( $post->post_excerpt, $context, $meta ) > 0 ? 1 : 0;
		}

		if ( '' !== trim( $post->post_content ) ) {
			$found += $this->register_html_strings( $post->post_content, $context, $meta );
		}

		if ( Settings::is_enabled( 'translate_elementor' ) ) {
			$found += $this->scan_elementor_document( $post );
		}

		if ( Settings::is_enabled( 'translate_seo_meta' ) ) {
			$found += $this->scan_seo_meta( $post );
		}

		return $found;
	}

	/**
	 * Extracts the text nodes of an HTML blob and registers each one.
	 *
	 * @param string              $html    Markup.
	 * @param string              $context Context label.
	 * @param array<string,mixed> $meta    String metadata.
	 * @return int
	 */
	protected function register_html_strings( string $html, string $context, array $meta = array() ): int {
		$found = 0;

		foreach ( $this->extract_text_nodes( $html ) as $text ) {
			if ( $this->translations->register_string( $text, $context, $meta ) > 0 ) {
				++$found;
			}
		}

		return $found;
	}

	/**
	 * Pulls the human readable runs out of an HTML fragment.
	 *
	 * @param string $html Markup.
	 * @return string[]
	 */
	public function extract_text_nodes( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		// Remove raw text elements outright.
		$html = (string) preg_replace( '#<(script|style|noscript|svg|code|pre)\b[^>]*>.*?</\1>#is', ' ', $html );

		// Shortcodes are executed at render time; their output is caught by the
		// page pass instead of being stored as literal shortcode text.
		$html = (string) preg_replace( '/\[[^\]]{1,200}\]/', ' ', $html );

		$parts = preg_split( '/<[^>]*>/s', $html );
		$parts = is_array( $parts ) ? $parts : array();

		$texts = array();

		foreach ( $parts as $part ) {
			$decoded = html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$decoded = Translation_Manager::normalize( $decoded );

			if ( Translation_Manager::is_translatable( $decoded ) ) {
				$texts[ $decoded ] = $decoded;
			}
		}

		// Translatable attributes.
		if ( preg_match_all( '/\s(?:alt|title|placeholder|aria-label)\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
			foreach ( $matches[2] as $value ) {
				$decoded = Translation_Manager::normalize( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

				if ( Translation_Manager::is_translatable( $decoded ) ) {
					$texts[ $decoded ] = $decoded;
				}
			}
		}

		return array_values( $texts );
	}

	/* ---------------------------------------------------------------------
	 * Elementor
	 * ------------------------------------------------------------------ */

	/**
	 * Scans the Elementor document attached to a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int
	 */
	public function scan_elementor_document( \WP_Post $post ): int {
		$raw = get_post_meta( $post->ID, '_elementor_data', true );

		if ( empty( $raw ) ) {
			return 0;
		}

		if ( is_string( $raw ) ) {
			$data = json_decode( $raw, true );
		} else {
			$data = $raw;
		}

		if ( ! is_array( $data ) ) {
			return 0;
		}

		$found    = 0;
		$excluded = array_map( 'strtolower', Settings::get_lines( 'excluded_widget_types' ) );

		$this->walk_elementor(
			$data,
			function ( string $text, string $widget_type, string $element_id, string $key ) use ( $post, &$found, $excluded ): void {
				if ( in_array( strtolower( $widget_type ), $excluded, true ) ) {
					return;
				}

				$meta = array(
					'object_type'     => $post->post_type,
					'object_id'       => $post->ID,
					'widget_type'     => $widget_type,
					'element_id'      => $element_id,
					'source_location' => get_permalink( $post ) ?: '',
				);

				$context = 'elementor:' . ( '' !== $widget_type ? $widget_type : 'element' );

				// Rich text controls carry markup; store their text nodes.
				if ( $text !== wp_strip_all_tags( $text ) ) {
					foreach ( $this->extract_text_nodes( $text ) as $node ) {
						if ( $this->translations->register_string( $node, $context, $meta ) > 0 ) {
							++$found;
						}
					}

					return;
				}

				if ( $this->translations->register_string( $text, $context, $meta ) > 0 ) {
					++$found;
				}

				unset( $key );
			}
		);

		return $found;
	}

	/**
	 * Recursively walks an Elementor element tree.
	 *
	 * @param array<int|string,mixed> $nodes       Element tree or settings array.
	 * @param callable                $callback    Receives (text, widget_type, element_id, key).
	 * @param string                  $widget_type Current widget type.
	 * @param string                  $element_id  Current element id.
	 * @return void
	 */
	protected function walk_elementor( array $nodes, callable $callback, string $widget_type = '', string $element_id = '' ): void {
		foreach ( $nodes as $key => $node ) {
			if ( is_array( $node ) ) {
				$next_type = isset( $node['widgetType'] ) && is_string( $node['widgetType'] ) ? $node['widgetType'] : $widget_type;
				$next_type = '' === $next_type && isset( $node['elType'] ) && is_string( $node['elType'] ) ? $node['elType'] : $next_type;
				$next_id   = isset( $node['id'] ) && is_string( $node['id'] ) ? $node['id'] : $element_id;

				$this->walk_elementor( $node, $callback, $next_type, $next_id );

				continue;
			}

			if ( ! is_string( $node ) || is_int( $key ) ) {
				continue;
			}

			if ( ! $this->is_translatable_elementor_key( (string) $key ) ) {
				continue;
			}

			$text = Translation_Manager::normalize( $node );

			if ( ! Translation_Manager::is_translatable( $text ) ) {
				continue;
			}

			$callback( $text, $widget_type, $element_id, (string) $key );
		}
	}

	/**
	 * Whether an Elementor control key holds human readable text.
	 *
	 * @param string $key Control key.
	 * @return bool
	 */
	protected function is_translatable_elementor_key( string $key ): bool {
		if ( str_starts_with( $key, '_' ) && ! in_array( $key, self::ELEMENTOR_KEYS, true ) ) {
			return false;
		}

		if ( in_array( $key, self::ELEMENTOR_SKIP_KEYS, true ) ) {
			return false;
		}

		if ( in_array( $key, self::ELEMENTOR_KEYS, true ) ) {
			return true;
		}

		foreach ( self::ELEMENTOR_KEY_SUFFIXES as $suffix ) {
			if ( str_ends_with( $key, $suffix ) ) {
				return true;
			}
		}

		/**
		 * Filters whether an Elementor control key is translatable.
		 *
		 * @param bool   $translatable Current decision.
		 * @param string $key          Control key.
		 */
		return (bool) apply_filters( 'als_elementor_key_translatable', false, $key );
	}

	/* ---------------------------------------------------------------------
	 * Other steps
	 * ------------------------------------------------------------------ */

	/**
	 * Scans SEO meta fields written by the popular SEO plugins.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int
	 */
	protected function scan_seo_meta( \WP_Post $post ): int {
		$keys = array(
			'_yoast_wpseo_title',
			'_yoast_wpseo_metadesc',
			'_yoast_wpseo_opengraph-title',
			'_yoast_wpseo_opengraph-description',
			'rank_math_title',
			'rank_math_description',
			'_aioseo_title',
			'_aioseo_description',
		);

		$found = 0;

		foreach ( $keys as $key ) {
			$value = get_post_meta( $post->ID, $key, true );

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			$registered = $this->translations->register_string(
				$value,
				'seo:meta',
				array(
					'object_type' => $post->post_type,
					'object_id'   => $post->ID,
				)
			);

			$found += $registered > 0 ? 1 : 0;
		}

		return $found;
	}

	/**
	 * Scans taxonomy terms.
	 *
	 * @param int $offset Offset.
	 * @return array<string,mixed>
	 */
	protected function scan_terms( int $offset ): array {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		if ( ! Settings::is_enabled( 'translate_products' ) ) {
			$taxonomies = array_diff( $taxonomies, array( 'product_cat', 'product_tag' ) );
		}

		$taxonomies = array_values( $taxonomies );

		if ( ! $taxonomies ) {
			return $this->step_result( 0, 0, 0, false );
		}

		$total = (int) wp_count_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
			)
		);

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'number'     => self::CHUNK,
				'offset'     => $offset,
				'orderby'    => 'term_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $this->step_result( 0, 0, 0, false );
		}

		$found     = 0;
		$processed = 0;

		foreach ( $terms as $term ) {
			++$processed;

			$meta = array(
				'object_type' => 'term:' . $term->taxonomy,
				'object_id'   => $term->term_id,
			);

			if ( $this->translations->register_string( $term->name, 'taxonomy:' . $term->taxonomy, $meta ) > 0 ) {
				++$found;
			}

			if ( '' !== trim( (string) $term->description ) ) {
				$found += $this->register_html_strings( (string) $term->description, 'taxonomy:' . $term->taxonomy, $meta );
			}
		}

		$next = $offset + self::CHUNK;

		return $this->step_result( $found, $processed, $total, $next < $total, $next );
	}

	/**
	 * Scans navigation menus.
	 *
	 * @param int $offset Offset.
	 * @return array<string,mixed>
	 */
	protected function scan_menus( int $offset ): array {
		if ( ! Settings::is_enabled( 'translate_menus' ) ) {
			return $this->step_result( 0, 0, 0, false );
		}

		$menus = wp_get_nav_menus();
		$menus = is_array( $menus ) ? $menus : array();

		$found     = 0;
		$processed = 0;

		foreach ( $menus as $menu ) {
			++$processed;

			$items = wp_get_nav_menu_items( $menu->term_id );

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				$meta = array(
					'object_type' => 'menu',
					'object_id'   => (int) $item->ID,
				);

				if ( $this->translations->register_string( (string) $item->title, 'menu', $meta ) > 0 ) {
					++$found;
				}

				if ( '' !== trim( (string) $item->description ) ) {
					if ( $this->translations->register_string( (string) $item->description, 'menu', $meta ) > 0 ) {
						++$found;
					}
				}
			}
		}

		return $this->step_result( $found, $processed, count( $menus ), false );
	}

	/**
	 * Scans widget content.
	 *
	 * @param int $offset Offset.
	 * @return array<string,mixed>
	 */
	protected function scan_widgets( int $offset ): array {
		if ( ! Settings::is_enabled( 'translate_widgets' ) ) {
			return $this->step_result( 0, 0, 0, false );
		}

		global $wp_registered_widgets;

		$found     = 0;
		$processed = 0;

		$option_names = array( 'widget_text', 'widget_block', 'widget_custom_html', 'widget_rss', 'widget_nav_menu', 'widget_search', 'widget_categories', 'widget_recent-posts' );

		foreach ( $option_names as $option_name ) {
			$instances = get_option( $option_name, array() );

			if ( ! is_array( $instances ) ) {
				continue;
			}

			foreach ( $instances as $key => $instance ) {
				if ( ! is_array( $instance ) ) {
					continue;
				}

				++$processed;

				$meta = array(
					'object_type' => 'widget',
					'widget_type' => $option_name,
					'element_id'  => (string) $key,
				);

				foreach ( array( 'title', 'text', 'content' ) as $field ) {
					if ( empty( $instance[ $field ] ) || ! is_string( $instance[ $field ] ) ) {
						continue;
					}

					$found += $this->register_html_strings( $instance[ $field ], 'widget:' . $option_name, $meta );
				}
			}
		}

		unset( $wp_registered_widgets, $offset );

		return $this->step_result( $found, $processed, $processed, false );
	}

	/**
	 * Registers the common theme and WordPress interface strings.
	 *
	 * @param int $offset Offset.
	 * @return array<string,mixed>
	 */
	protected function scan_theme( int $offset ): array {
		if ( ! Settings::is_enabled( 'translate_theme' ) ) {
			return $this->step_result( 0, 0, 0, false );
		}

		$strings = array(
			__( 'Read More', 'advanced-language-switcher' ),
			__( 'Continue reading', 'advanced-language-switcher' ),
			__( 'Search', 'advanced-language-switcher' ),
			__( 'Search results', 'advanced-language-switcher' ),
			__( 'Search for:', 'advanced-language-switcher' ),
			__( 'Nothing Found', 'advanced-language-switcher' ),
			__( 'Previous', 'advanced-language-switcher' ),
			__( 'Next', 'advanced-language-switcher' ),
			__( 'Older posts', 'advanced-language-switcher' ),
			__( 'Newer posts', 'advanced-language-switcher' ),
			__( 'Submit', 'advanced-language-switcher' ),
			__( 'Send', 'advanced-language-switcher' ),
			__( 'Name', 'advanced-language-switcher' ),
			__( 'Email', 'advanced-language-switcher' ),
			__( 'Message', 'advanced-language-switcher' ),
			__( 'Subject', 'advanced-language-switcher' ),
			__( 'Phone', 'advanced-language-switcher' ),
			__( 'Home', 'advanced-language-switcher' ),
			__( 'Categories', 'advanced-language-switcher' ),
			__( 'Tags', 'advanced-language-switcher' ),
			__( 'Archives', 'advanced-language-switcher' ),
			__( 'Comments', 'advanced-language-switcher' ),
			__( 'Leave a comment', 'advanced-language-switcher' ),
			__( 'Reply', 'advanced-language-switcher' ),
			__( 'Posted on', 'advanced-language-switcher' ),
			__( 'by', 'advanced-language-switcher' ),
			__( 'Page not found', 'advanced-language-switcher' ),
		);

		if ( Settings::is_enabled( 'translate_products' ) && class_exists( 'WooCommerce' ) ) {
			$strings = array_merge( $strings, $this->woocommerce_strings() );
		}

		/**
		 * Filters the baseline interface strings registered by a scan.
		 *
		 * @param string[] $strings Strings.
		 */
		$strings = apply_filters( 'als_theme_strings', $strings );

		$found = 0;

		foreach ( $strings as $string ) {
			if ( $this->translations->register_string( $string, '', array( 'object_type' => 'theme' ) ) > 0 ) {
				++$found;
			}
		}

		unset( $offset );

		return $this->step_result( $found, count( $strings ), count( $strings ), false );
	}

	/**
	 * The WooCommerce interface strings worth pre-registering.
	 *
	 * @return string[]
	 */
	protected function woocommerce_strings(): array {
		return array(
			'Add to cart',
			'Add to basket',
			'View cart',
			'Cart',
			'Checkout',
			'My account',
			'Shop',
			'Sale!',
			'Out of stock',
			'In stock',
			'Related products',
			'You may also like',
			'Description',
			'Additional information',
			'Reviews',
			'Quantity',
			'Subtotal',
			'Total',
			'Shipping',
			'Billing details',
			'Shipping details',
			'Place order',
			'Order received',
			'Continue shopping',
			'Your cart is currently empty.',
			'Return to shop',
			'Apply coupon',
			'Coupon code',
			'Update cart',
			'Proceed to checkout',
			'Product',
			'Price',
			'SKU',
			'Category',
			'Categories',
			'Tag',
			'Tags',
			'Sort by popularity',
			'Sort by latest',
			'Sort by price: low to high',
			'Sort by price: high to low',
			'Showing all results',
			'No products were found matching your selection.',
			'Select options',
			'Read more',
			'Clear',
			'Choose an option',
		);
	}

	/**
	 * Builds a step result array.
	 *
	 * @param int  $found       Strings found.
	 * @param int  $processed   Items processed.
	 * @param int  $total       Total items.
	 * @param bool $has_more    Whether another slice is pending.
	 * @param int  $next_offset Next offset.
	 * @return array<string,mixed>
	 */
	protected function step_result( int $found, int $processed, int $total, bool $has_more, int $next_offset = 0 ): array {
		return array(
			'found'       => $found,
			'processed'   => $processed,
			'total'       => $total,
			'has_more'    => $has_more,
			'next_offset' => $next_offset,
		);
	}

	/**
	 * Scans one post on demand, used when content is saved.
	 *
	 * @param int $post_id Post id.
	 * @return int
	 */
	public function scan_post_id( int $post_id ): int {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		return $this->scan_post( $post );
	}

	/**
	 * Timestamp of the last completed scan.
	 *
	 * @return int
	 */
	public function last_scan(): int {
		return (int) get_option( 'als_last_scan', 0 );
	}

	/**
	 * Counters from the last scan.
	 *
	 * @return array<string,int>
	 */
	public function last_counters(): array {
		return $this->get_counters();
	}
}
