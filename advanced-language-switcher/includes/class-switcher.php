<?php
/**
 * Front end markup for the language switcher.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the switcher for the Elementor widget, the shortcode and the
 * template function, so all three stay byte-identical.
 */
class Switcher {

	/**
	 * Counter used to build unique instance ids.
	 *
	 * @var int
	 */
	protected static int $instances = 0;

	/**
	 * Available preset slugs mapped to their labels.
	 *
	 * @return array<string,string>
	 */
	public static function presets(): array {
		return array(
			'compact-pill'     => __( 'Compact Pill', 'advanced-language-switcher' ),
			'minimal-text'     => __( 'Minimal Text', 'advanced-language-switcher' ),
			'flag-code'        => __( 'Flag + Code', 'advanced-language-switcher' ),
			'flag-name'        => __( 'Flag + Language Name', 'advanced-language-switcher' ),
			'dropdown'         => __( 'Dropdown', 'advanced-language-switcher' ),
			'modern-segmented' => __( 'Modern Segmented', 'advanced-language-switcher' ),
			'glassmorphism'    => __( 'Glassmorphism', 'advanced-language-switcher' ),
			'minimal-border'   => __( 'Minimal Border', 'advanced-language-switcher' ),
		);
	}

	/**
	 * Layout slugs mapped to their labels.
	 *
	 * @return array<string,string>
	 */
	public static function layouts(): array {
		return array(
			'horizontal' => __( 'Horizontal', 'advanced-language-switcher' ),
			'vertical'   => __( 'Vertical', 'advanced-language-switcher' ),
			'dropdown'   => __( 'Dropdown', 'advanced-language-switcher' ),
			'pills'      => __( 'Pills', 'advanced-language-switcher' ),
			'segmented'  => __( 'Segmented Control', 'advanced-language-switcher' ),
			'minimal'    => __( 'Minimal', 'advanced-language-switcher' ),
			'compact'    => __( 'Compact', 'advanced-language-switcher' ),
		);
	}

	/**
	 * Display mode slugs mapped to their labels.
	 *
	 * @return array<string,string>
	 */
	public static function display_modes(): array {
		return array(
			'code'           => __( 'Language Code', 'advanced-language-switcher' ),
			'name'           => __( 'Language Name', 'advanced-language-switcher' ),
			'flag'           => __( 'Flag', 'advanced-language-switcher' ),
			'flag_code'      => __( 'Flag + Language Code', 'advanced-language-switcher' ),
			'flag_name'      => __( 'Flag + Language Name', 'advanced-language-switcher' ),
			'code_name'      => __( 'Language Code + Language Name', 'advanced-language-switcher' ),
			'flag_code_name' => __( 'Flag + Code + Name', 'advanced-language-switcher' ),
		);
	}

	/**
	 * Presets that imply a particular layout and display mode.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function preset_defaults(): array {
		return array(
			'compact-pill'     => array( 'layout' => 'pills', 'display' => 'code' ),
			'minimal-text'     => array( 'layout' => 'minimal', 'display' => 'code' ),
			'flag-code'        => array( 'layout' => 'horizontal', 'display' => 'flag_code' ),
			'flag-name'        => array( 'layout' => 'horizontal', 'display' => 'flag_name' ),
			'dropdown'         => array( 'layout' => 'dropdown', 'display' => 'flag_name' ),
			'modern-segmented' => array( 'layout' => 'segmented', 'display' => 'code' ),
			'glassmorphism'    => array( 'layout' => 'pills', 'display' => 'code' ),
			'minimal-border'   => array( 'layout' => 'horizontal', 'display' => 'code' ),
		);
	}

	/**
	 * Default render arguments, seeded from the global switcher settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'preset'        => (string) Settings::get( 'switcher_preset', 'compact-pill' ),
			'layout'        => (string) Settings::get( 'switcher_layout', 'pills' ),
			'display'       => (string) Settings::get( 'switcher_display', 'code' ),
			'flag_style'    => (string) Settings::get( 'switcher_flag_style', 'emoji' ),
			'flag_shape'    => 'rounded',
			'behaviour'     => (string) Settings::get( 'switcher_behaviour', 'navigate' ),
			'animation'     => (string) Settings::get( 'switcher_animation', 'fade' ),
			'languages'     => array(),
			'hide_current'  => false,
			'current_first' => false,
			'active_source' => 'auto',
			'active_custom' => '',
			'label'         => __( 'Select language', 'advanced-language-switcher' ),
			'instance_id'   => '',
			'extra_class'   => '',
			'is_preview'    => false,
		);
	}

	/**
	 * Renders the switcher.
	 *
	 * @param array<string,mixed> $args Render arguments.
	 * @return string HTML, or an empty string when there is nothing to show.
	 */
	public static function render( array $args = array() ): string {
		$args = wp_parse_args( $args, self::defaults() );

		$plugin    = plugin();
		$languages = $plugin->languages()->active();

		// Honour an explicit subset chosen in the widget.
		if ( ! empty( $args['languages'] ) && is_array( $args['languages'] ) ) {
			$allowed   = array_map( 'strval', $args['languages'] );
			$languages = array_values(
				array_filter(
					$languages,
					static fn( Language $language ) => in_array( $language->code, $allowed, true )
				)
			);
		}

		if ( ! $languages ) {
			return self::notice( __( 'No languages are enabled yet.', 'advanced-language-switcher' ), (bool) $args['is_preview'] );
		}

		$current = self::resolve_active( $args, $languages );
		$router  = $plugin->router();

		if ( ! empty( $args['hide_current'] ) && $current instanceof Language && count( $languages ) > 1 ) {
			$languages = array_values(
				array_filter(
					$languages,
					static fn( Language $language ) => $language->code !== $current->code
				)
			);
		}

		if ( ! empty( $args['current_first'] ) && $current instanceof Language ) {
			usort(
				$languages,
				static fn( Language $a, Language $b ) => ( $a->code === $current->code ? -1 : 0 ) + ( $b->code === $current->code ? 1 : 0 )
			);
		}

		++self::$instances;

		$instance_id = '' !== (string) $args['instance_id']
			? sanitize_html_class( (string) $args['instance_id'] )
			: 'als-switcher-' . self::$instances;

		$layout    = array_key_exists( (string) $args['layout'], self::layouts() ) ? (string) $args['layout'] : 'pills';
		$preset    = array_key_exists( (string) $args['preset'], self::presets() ) ? (string) $args['preset'] : 'compact-pill';
		$display   = array_key_exists( (string) $args['display'], self::display_modes() ) ? (string) $args['display'] : 'code';
		$behaviour = in_array( (string) $args['behaviour'], array( 'navigate', 'reload', 'ajax', 'url' ), true ) ? (string) $args['behaviour'] : 'navigate';
		$animation = in_array( (string) $args['animation'], array( 'none', 'fade', 'slide', 'scale' ), true ) ? (string) $args['animation'] : 'fade';

		$classes = array(
			'als-language-switcher',
			'als-layout--' . $layout,
			'als-preset--' . $preset,
			'als-display--' . str_replace( '_', '-', $display ),
			'als-animation--' . $animation,
		);

		if ( '' !== (string) $args['extra_class'] ) {
			$classes[] = sanitize_html_class( (string) $args['extra_class'] );
		}

		if ( $current instanceof Language && $current->is_rtl() ) {
			$classes[] = 'als-is-rtl';
		}

		/**
		 * Filters the switcher container classes.
		 *
		 * @param string[]            $classes Class names.
		 * @param array<string,mixed> $args    Render arguments.
		 */
		$classes = apply_filters( 'als_switcher_classes', $classes, $args );

		$items = array();

		foreach ( $languages as $language ) {
			$items[] = array(
				'language'  => $language,
				'url'       => $router->get_language_url( $language ),
				'is_active' => $current instanceof Language && $current->code === $language->code,
			);
		}

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', array_unique( $classes ) ) ); ?>"
			id="<?php echo esc_attr( $instance_id ); ?>"
			data-als-switcher="1"
			data-behaviour="<?php echo esc_attr( $behaviour ); ?>"
			data-layout="<?php echo esc_attr( $layout ); ?>"
			data-current="<?php echo esc_attr( $current instanceof Language ? $current->code : '' ); ?>"
			role="group"
			aria-label="<?php echo esc_attr( (string) $args['label'] ); ?>"
		>
			<?php if ( 'dropdown' === $layout ) : ?>
				<?php echo self::render_dropdown( $items, $current, $display, $args, $instance_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php echo self::render_item( $item, $display, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php

		$html = (string) ob_get_clean();

		/**
		 * Filters the rendered switcher markup.
		 *
		 * @param string              $html Rendered HTML.
		 * @param array<string,mixed> $args Render arguments.
		 */
		return (string) apply_filters( 'als_switcher_html', $html, $args );
	}

	/**
	 * Renders one language entry.
	 *
	 * @param array<string,mixed> $item    Item data.
	 * @param string              $display Display mode.
	 * @param array<string,mixed> $args    Render arguments.
	 * @return string
	 */
	protected static function render_item( array $item, string $display, array $args ): string {
		/** @var Language $language */
		$language = $item['language'];
		$active   = (bool) $item['is_active'];

		$classes = array( 'als-language-item' );

		if ( $active ) {
			$classes[] = 'als-language-item--active';
			$classes[] = 'active';
		}

		$attributes = array(
			'class'     => implode( ' ', $classes ),
			'href'      => esc_url( (string) $item['url'] ),
			'data-language' => $language->code,
			'data-als-language' => $language->code,
			'lang'      => $language->html_lang(),
			'hreflang'  => $language->html_lang(),
			'dir'       => $language->direction,
			'title'     => $language->display_name(),
		);

		if ( $active ) {
			$attributes['aria-current'] = 'true';
		}

		$rendered = '';

		foreach ( $attributes as $name => $value ) {
			$rendered .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		return '<a' . $rendered . '>' . self::render_label( $language, $display, $args ) . '</a>';
	}

	/**
	 * Renders the dropdown variant.
	 *
	 * @param array<int,array<string,mixed>> $items       Items.
	 * @param Language|null                  $current     Active language.
	 * @param string                         $display     Display mode.
	 * @param array<string,mixed>            $args        Render arguments.
	 * @param string                         $instance_id Instance id.
	 * @return string
	 */
	protected static function render_dropdown( array $items, ?Language $current, string $display, array $args, string $instance_id ): string {
		$list_id = $instance_id . '-list';
		$label   = $current instanceof Language
			? self::render_label( $current, $display, $args )
			: esc_html__( 'Language', 'advanced-language-switcher' );

		$html  = '<button type="button" class="als-language-toggle" aria-haspopup="listbox" aria-expanded="false"';
		$html .= ' aria-controls="' . esc_attr( $list_id ) . '">';
		$html .= '<span class="als-language-toggle__label">' . $label . '</span>';
		$html .= '<span class="als-language-caret" aria-hidden="true"></span>';
		$html .= '</button>';

		$html .= '<div class="als-language-dropdown" id="' . esc_attr( $list_id ) . '" role="listbox" hidden>';

		foreach ( $items as $item ) {
			/** @var Language $language */
			$language  = $item['language'];
			$is_active = (bool) $item['is_active'];

			$html .= '<a class="als-language-item als-language-dropdown__item' . ( $is_active ? ' als-language-item--active active' : '' ) . '"';
			$html .= ' href="' . esc_url( (string) $item['url'] ) . '"';
			$html .= ' role="option" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '"';
			$html .= ' data-language="' . esc_attr( $language->code ) . '"';
			$html .= ' data-als-language="' . esc_attr( $language->code ) . '"';
			$html .= ' lang="' . esc_attr( $language->html_lang() ) . '"';
			$html .= ' hreflang="' . esc_attr( $language->html_lang() ) . '"';
			$html .= $is_active ? ' aria-current="true"' : '';
			$html .= '>' . self::render_label( $language, $display, $args ) . '</a>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders the visible label of a language according to the display mode.
	 *
	 * A flag is never rendered on its own without an accessible name, because
	 * flags represent countries rather than languages.
	 *
	 * @param Language            $language Language.
	 * @param string              $display  Display mode.
	 * @param array<string,mixed> $args     Render arguments.
	 * @return string
	 */
	protected static function render_label( Language $language, string $display, array $args ): string {
		$show_flag = str_contains( $display, 'flag' );
		$show_code = 'code' === $display || str_contains( $display, '_code' );
		$show_name = 'name' === $display || str_contains( $display, '_name' );

		$flag_style = (string) $args['flag_style'];

		if ( 'none' === $flag_style ) {
			$show_flag = false;
		}

		$html = '';

		if ( $show_flag ) {
			$html .= self::render_flag( $language, $args );
		}

		if ( $show_code ) {
			$html .= '<span class="als-language-code">' . esc_html( $language->display_code() ) . '</span>';
		}

		if ( $show_name ) {
			$html .= '<span class="als-language-name">' . esc_html( $language->display_name() ) . '</span>';
		}

		if ( ! $show_code && ! $show_name ) {
			// Flag-only mode still exposes the language name to assistive tech.
			$html .= '<span class="als-language-name als-screen-reader-text">' . esc_html( $language->display_name() ) . '</span>';
		}

		return $html;
	}

	/**
	 * Renders the flag for a language.
	 *
	 * @param Language            $language Language.
	 * @param array<string,mixed> $args     Render arguments.
	 * @return string
	 */
	protected static function render_flag( Language $language, array $args ): string {
		$style = (string) $args['flag_style'];
		$shape = in_array( (string) $args['flag_shape'], array( 'square', 'rounded', 'circle' ), true )
			? (string) $args['flag_shape']
			: 'rounded';

		$classes = 'als-language-flag als-language-flag--' . $shape . ' als-language-flag--' . $style;

		if ( 'image' === $style && '' !== $language->flag_url ) {
			return sprintf(
				'<img class="%s" src="%s" alt="" aria-hidden="true" loading="lazy" decoding="async" width="20" height="15" />',
				esc_attr( $classes ),
				esc_url( $language->flag_url )
			);
		}

		if ( 'svg' === $style ) {
			$svg = self::svg_flag_url( $language );

			if ( '' !== $svg ) {
				return sprintf(
					'<img class="%s" src="%s" alt="" aria-hidden="true" loading="lazy" decoding="async" width="20" height="15" />',
					esc_attr( $classes ),
					esc_url( $svg )
				);
			}
		}

		$emoji = '' !== $language->flag ? $language->flag : self::emoji_from_country( $language->country );

		if ( '' === $emoji ) {
			return '';
		}

		return sprintf(
			'<span class="%s" aria-hidden="true">%s</span>',
			esc_attr( $classes ),
			esc_html( $emoji )
		);
	}

	/**
	 * Resolves the URL of a bundled SVG flag, if one exists.
	 *
	 * @param Language $language Language.
	 * @return string
	 */
	protected static function svg_flag_url( Language $language ): string {
		$country = strtolower( $language->country );

		if ( '' === $country ) {
			return '';
		}

		$relative = 'assets/flags/' . sanitize_file_name( $country ) . '.svg';

		if ( ! file_exists( ALS_PLUGIN_DIR . $relative ) ) {
			return '';
		}

		return ALS_PLUGIN_URL . $relative;
	}

	/**
	 * Builds a regional-indicator emoji from a two letter country code.
	 *
	 * @param string $country Country code.
	 * @return string
	 */
	public static function emoji_from_country( string $country ): string {
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', $country ) ?? '' );

		if ( 2 !== strlen( $country ) ) {
			return '';
		}

		$emoji = '';

		foreach ( str_split( $country ) as $char ) {
			$emoji .= mb_chr( 0x1F1E6 + ( ord( $char ) - 65 ), 'UTF-8' );
		}

		return $emoji;
	}

	/**
	 * Works out which language should be shown as active.
	 *
	 * @param array<string,mixed> $args      Render arguments.
	 * @param Language[]          $languages Available languages.
	 * @return Language|null
	 */
	protected static function resolve_active( array $args, array $languages ): ?Language {
		$plugin = plugin();
		$source = (string) $args['active_source'];

		if ( 'default' === $source ) {
			return $plugin->languages()->get_default();
		}

		if ( 'custom' === $source && '' !== (string) $args['active_custom'] ) {
			$custom = $plugin->languages()->get_by_code( (string) $args['active_custom'] );

			if ( $custom instanceof Language ) {
				return $custom;
			}
		}

		$current = $plugin->router()->get_current_language();

		if ( $current instanceof Language ) {
			return $current;
		}

		return $languages[0] ?? null;
	}

	/**
	 * Renders an editor-only notice.
	 *
	 * Visitors never see configuration warnings.
	 *
	 * @param string $message    Message.
	 * @param bool   $is_preview Whether the Elementor editor is rendering.
	 * @return string
	 */
	protected static function notice( string $message, bool $is_preview ): string {
		if ( ! $is_preview && ! current_user_can( Security::CAPABILITY ) ) {
			return '';
		}

		return '<div class="als-language-switcher als-language-switcher--empty">'
			. '<span class="als-empty-notice">' . esc_html( $message ) . '</span>'
			. '</div>';
	}
}
