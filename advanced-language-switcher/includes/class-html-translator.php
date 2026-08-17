<?php
/**
 * Rewrites the human readable text of an HTML document into another language.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * A linear HTML scanner that replaces text nodes and translatable attributes.
 *
 * A DOM parse-and-reserialize round trip would silently rewrite the markup
 * Elementor and themes emit (self-closing tags, custom elements, conditional
 * comments, inline SVG). This scanner instead copies the document through
 * byte for byte and only swaps the exact ranges that contain human readable
 * text, so nothing else about the page can change.
 */
class Html_Translator {

	/**
	 * Elements whose contents are never natural language.
	 *
	 * @var string[]
	 */
	protected const RAW_ELEMENTS = array( 'script', 'style', 'noscript', 'svg', 'canvas', 'template', 'code', 'pre', 'textarea', 'math' );

	/**
	 * Void elements that never have a closing tag.
	 *
	 * @var string[]
	 */
	protected const VOID_ELEMENTS = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );

	/**
	 * Attributes whose values are shown to a human.
	 *
	 * @var string[]
	 */
	protected const TRANSLATABLE_ATTRIBUTES = array( 'alt', 'title', 'placeholder', 'aria-label', 'aria-placeholder', 'aria-description', 'aria-roledescription', 'data-placeholder' );

	/**
	 * Translation manager.
	 *
	 * @var Translation_Manager
	 */
	protected Translation_Manager $translations;

	/**
	 * Target language.
	 *
	 * @var Language
	 */
	protected Language $language;

	/**
	 * Class names that switch translation off for a subtree.
	 *
	 * @var string[]
	 */
	protected array $excluded_classes;

	/**
	 * Literal strings that must never be translated.
	 *
	 * @var string[]
	 */
	protected array $excluded_strings;

	/**
	 * Strings discovered during this pass that are not yet in the database.
	 *
	 * @var array<string,string>
	 */
	protected array $discovered = array();

	/**
	 * Whether discovered strings should be recorded.
	 *
	 * @var bool
	 */
	protected bool $discovery_enabled = false;

	/**
	 * Maximum number of new strings recorded per pass.
	 *
	 * @var int
	 */
	protected int $discovery_limit = 300;

	/**
	 * Memoized lookups for this pass.
	 *
	 * @var array<string,string|false>
	 */
	protected array $memo = array();

	/**
	 * Number of replacements made in the last run.
	 *
	 * @var int
	 */
	protected int $replacements = 0;

	/**
	 * Constructor.
	 *
	 * @param Translation_Manager $translations Translation manager.
	 * @param Language            $language     Target language.
	 */
	public function __construct( Translation_Manager $translations, Language $language ) {
		$this->translations = $translations;
		$this->language     = $language;

		$classes = Settings::get_lines( 'excluded_classes' );
		$classes = array_map( 'strtolower', $classes );
		$classes = array_merge( $classes, array( 'no-translate', 'notranslate' ) );

		/**
		 * Filters the CSS classes that switch translation off for a subtree.
		 *
		 * Integrations use this to protect prices, SKUs and other machine
		 * readable output without the administrator having to configure it.
		 *
		 * @param string[] $classes  Class names.
		 * @param Language $language Target language.
		 */
		$classes = apply_filters( 'als_excluded_classes', $classes, $language );

		$this->excluded_classes = array_values( array_unique( array_map( 'strtolower', $classes ) ) );
		$this->excluded_strings = Settings::get_lines( 'excluded_strings' );
	}

	/**
	 * Enables recording of untranslated strings found while rendering.
	 *
	 * @param bool $enabled Whether to record.
	 * @param int  $limit   Maximum strings per pass.
	 * @return void
	 */
	public function set_discovery( bool $enabled, int $limit = 300 ): void {
		$this->discovery_enabled = $enabled;
		$this->discovery_limit   = max( 0, $limit );
	}

	/**
	 * Strings discovered during the last run.
	 *
	 * @return array<string,string>
	 */
	public function get_discovered(): array {
		return $this->discovered;
	}

	/**
	 * Number of replacements made during the last run.
	 *
	 * @return int
	 */
	public function get_replacement_count(): int {
		return $this->replacements;
	}

	/**
	 * Translates an HTML fragment or a whole document.
	 *
	 * @param string $html Source markup.
	 * @return string
	 */
	public function translate( string $html ): string {
		if ( '' === $html ) {
			return $html;
		}

		$this->replacements = 0;

		$length     = strlen( $html );
		$output     = '';
		$position   = 0;
		$skip_stack = array();

		while ( $position < $length ) {
			$open = strpos( $html, '<', $position );

			if ( false === $open ) {
				$output  .= $this->handle_text( substr( $html, $position ), (bool) $skip_stack );
				$position = $length;
				break;
			}

			if ( $open > $position ) {
				$output .= $this->handle_text( substr( $html, $position, $open - $position ), (bool) $skip_stack );
			}

			// Comments, CDATA and doctypes are copied verbatim.
			if ( 0 === substr_compare( $html, '<!--', $open, 4 ) ) {
				$close    = strpos( $html, '-->', $open );
				$close    = false === $close ? $length : $close + 3;
				$output  .= substr( $html, $open, $close - $open );
				$position = $close;
				continue;
			}

			if ( 0 === substr_compare( $html, '<!', $open, 2 ) || 0 === substr_compare( $html, '<?', $open, 2 ) ) {
				$close    = strpos( $html, '>', $open );
				$close    = false === $close ? $length : $close + 1;
				$output  .= substr( $html, $open, $close - $open );
				$position = $close;
				continue;
			}

			$tag_end = $this->find_tag_end( $html, $open );

			if ( false === $tag_end ) {
				// Not a tag after all: treat the rest as text.
				$output  .= $this->handle_text( substr( $html, $open ), (bool) $skip_stack );
				$position = $length;
				break;
			}

			$tag  = substr( $html, $open, $tag_end - $open + 1 );
			$name = $this->tag_name( $tag );

			$position = $tag_end + 1;

			// Closing tag.
			if ( str_starts_with( $tag, '</' ) ) {
				$output .= $tag;

				if ( $skip_stack ) {
					$index = array_search( $name, $skip_stack, true );

					if ( false !== $index ) {
						$skip_stack = array_slice( $skip_stack, 0, (int) $index );
					}
				}

				continue;
			}

			$self_closing = str_ends_with( rtrim( $tag ), '/>' ) || in_array( $name, self::VOID_ELEMENTS, true );

			// Raw text elements: copy their whole contents untouched.
			if ( in_array( $name, self::RAW_ELEMENTS, true ) && ! $self_closing ) {
				$output .= $skip_stack ? $tag : $this->handle_tag( $tag, $name );

				$closing = $this->find_closing_tag( $html, $name, $position );

				if ( false === $closing ) {
					$output  .= substr( $html, $position );
					$position = $length;
					break;
				}

				$output  .= substr( $html, $position, $closing - $position );
				$position = $closing;

				continue;
			}

			$excluded = $this->tag_is_excluded( $tag );

			$output .= ( $skip_stack || $excluded ) ? $tag : $this->handle_tag( $tag, $name );

			if ( ! $self_closing && ( $skip_stack || $excluded ) ) {
				$skip_stack[] = $name;
			}
		}

		return $output;
	}

	/**
	 * Finds the byte offset of the `>` that closes a tag, respecting quoted
	 * attribute values.
	 *
	 * @param string $html   Document.
	 * @param int    $offset Offset of the opening `<`.
	 * @return int|false
	 */
	protected function find_tag_end( string $html, int $offset ) {
		$length = strlen( $html );
		$quote  = '';

		for ( $i = $offset + 1; $i < $length; $i++ ) {
			$char = $html[ $i ];

			if ( '' !== $quote ) {
				if ( $char === $quote ) {
					$quote = '';
				}

				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				continue;
			}

			if ( '>' === $char ) {
				return $i;
			}

			// A stray `<` means the first one was not a tag.
			if ( '<' === $char ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Finds the offset just past the closing tag of a raw text element.
	 *
	 * @param string $html   Document.
	 * @param string $name   Element name.
	 * @param int    $offset Search start.
	 * @return int|false
	 */
	protected function find_closing_tag( string $html, string $name, int $offset ) {
		if ( ! preg_match( '#</\s*' . preg_quote( $name, '#' ) . '\s*>#i', $html, $matches, PREG_OFFSET_CAPTURE, $offset ) ) {
			return false;
		}

		return (int) $matches[0][1] + strlen( $matches[0][0] );
	}

	/**
	 * Extracts a lowercase tag name.
	 *
	 * @param string $tag Full tag including brackets.
	 * @return string
	 */
	protected function tag_name( string $tag ): string {
		if ( ! preg_match( '#^</?\s*([a-zA-Z][a-zA-Z0-9:_-]*)#', $tag, $matches ) ) {
			return '';
		}

		return strtolower( $matches[1] );
	}

	/**
	 * Whether an opening tag switches translation off for its subtree.
	 *
	 * @param string $tag Full opening tag.
	 * @return bool
	 */
	protected function tag_is_excluded( string $tag ): bool {
		if ( preg_match( '/\stranslate\s*=\s*["\']?no["\']?/i', $tag ) ) {
			return true;
		}

		if ( preg_match( '/\sdata-als-skip\b/i', $tag ) ) {
			return true;
		}

		if ( ! $this->excluded_classes ) {
			return false;
		}

		if ( ! preg_match( '/\sclass\s*=\s*(["\'])(.*?)\1/is', $tag, $matches ) ) {
			return false;
		}

		$classes = preg_split( '/\s+/', strtolower( $matches[2] ) );
		$classes = is_array( $classes ) ? $classes : array();

		foreach ( $this->excluded_classes as $excluded ) {
			if ( in_array( ltrim( $excluded, '.' ), $classes, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Translates the human readable attributes of an opening tag.
	 *
	 * @param string $tag  Full opening tag.
	 * @param string $name Element name.
	 * @return string
	 */
	protected function handle_tag( string $tag, string $name ): string {
		if ( ! str_contains( $tag, '=' ) ) {
			return $tag;
		}

		$attributes = self::TRANSLATABLE_ATTRIBUTES;

		// Button-like inputs display their value.
		if ( 'input' === $name && preg_match( '/\stype\s*=\s*["\']?(submit|button|reset)["\']?/i', $tag ) ) {
			$attributes[] = 'value';
		}

		foreach ( $attributes as $attribute ) {
			$pattern = '/(\s' . preg_quote( $attribute, '/' ) . '\s*=\s*)(["\'])(.*?)\2/is';

			$tag = (string) preg_replace_callback(
				$pattern,
				function ( array $matches ): string {
					$value = $matches[3];

					if ( '' === trim( $value ) ) {
						return $matches[0];
					}

					$decoded    = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$translated = $this->lookup( $decoded );

					if ( null === $translated ) {
						return $matches[0];
					}

					++$this->replacements;

					return $matches[1] . $matches[2] . esc_attr( $translated ) . $matches[2];
				},
				$tag
			);
		}

		return $tag;
	}

	/**
	 * Translates a run of text between two tags.
	 *
	 * @param string $text     Raw text.
	 * @param bool   $skipping Whether the current subtree is excluded.
	 * @return string
	 */
	protected function handle_text( string $text, bool $skipping ): string {
		if ( $skipping || '' === trim( $text ) ) {
			return $text;
		}

		// Preserve the exact surrounding whitespace so inline layout is untouched.
		if ( ! preg_match( '/^(\s*)(.*?)(\s*)$/su', $text, $matches ) ) {
			return $text;
		}

		$lead  = $matches[1];
		$core  = $matches[2];
		$trail = $matches[3];

		if ( '' === $core ) {
			return $text;
		}

		$decoded    = html_entity_decode( $core, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$translated = $this->lookup( $decoded );

		if ( null === $translated ) {
			return $text;
		}

		++$this->replacements;

		return $lead . esc_html( $translated ) . $trail;
	}

	/**
	 * Resolves one string against the dictionary.
	 *
	 * @param string $text Decoded source text.
	 * @return string|null Null when there is nothing to replace it with.
	 */
	protected function lookup( string $text ): ?string {
		$key = $text;

		if ( array_key_exists( $key, $this->memo ) ) {
			$value = $this->memo[ $key ];

			return false === $value ? null : $value;
		}

		if ( ! Translation_Manager::is_translatable( $text ) ) {
			$this->memo[ $key ] = false;

			return null;
		}

		foreach ( $this->excluded_strings as $excluded ) {
			if ( $excluded === trim( $text ) ) {
				$this->memo[ $key ] = false;

				return null;
			}
		}

		$translated = $this->translations->lookup( $text, $this->language );

		if ( null === $translated || '' === $translated ) {
			$this->remember_discovery( $text );
			$this->memo[ $key ] = false;

			return null;
		}

		$normalized = Translation_Manager::normalize( $text );

		if ( $translated === $normalized ) {
			$this->memo[ $key ] = false;

			return null;
		}

		$this->memo[ $key ] = $translated;

		return $translated;
	}

	/**
	 * Records an untranslated string for the admin to review later.
	 *
	 * @param string $text Source text.
	 * @return void
	 */
	protected function remember_discovery( string $text ): void {
		if ( ! $this->discovery_enabled || count( $this->discovered ) >= $this->discovery_limit ) {
			return;
		}

		$normalized = Translation_Manager::normalize( $text );

		$this->discovered[ Translation_Manager::hash( $normalized ) ] = $normalized;
	}
}
