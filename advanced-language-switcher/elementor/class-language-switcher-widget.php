<?php
/**
 * Elementor "Language Switcher" widget.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Elementor;

use ALS\Elementor_Integration;
use ALS\Language;
use ALS\Settings;
use ALS\Switcher;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A fully styleable language switcher for the Elementor editor.
 *
 * Every visual property the design needs is exposed as a native Elementor
 * control, so no custom CSS is ever required for ordinary styling.
 */
class Language_Switcher_Widget extends Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'als-language-switcher';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Language Switcher', 'advanced-language-switcher' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-globe';
	}

	/**
	 * Widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( Elementor_Integration::CATEGORY, 'general' );
	}

	/**
	 * Editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'language', 'switcher', 'translate', 'translation', 'multilingual', 'flag', 'locale', 'als' );
	}

	/**
	 * Styles this widget depends on.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'als-language-switcher' );
	}

	/**
	 * Scripts this widget depends on.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return array( 'als-language-switcher' );
	}

	/**
	 * Elementor renders the widget server side whenever a control changes,
	 * which keeps the editor preview identical to the front end.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Language options for the select controls.
	 *
	 * @return array<string,string>
	 */
	protected function language_options(): array {
		$options = array();

		foreach ( \ALS\plugin()->languages()->active() as $language ) {
			$options[ $language->code ] = sprintf( '%s (%s)', $language->name, strtoupper( $language->code ) );
		}

		return $options;
	}

	/**
	 * Registers every control.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_container_style_controls();
		$this->register_item_style_controls();
		$this->register_flag_style_controls();
		$this->register_dropdown_style_controls();
	}

	/* ---------------------------------------------------------------------
	 * Content
	 * ------------------------------------------------------------------ */

	/**
	 * Content tab controls.
	 *
	 * @return void
	 */
	protected function register_content_controls(): void {
		$this->start_controls_section(
			'section_general',
			array(
				'label' => __( 'Language Switcher', 'advanced-language-switcher' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'design_mode',
			array(
				'label'       => __( 'Design Mode', 'advanced-language-switcher' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'preset',
				'options'     => array(
					'global' => __( 'Global (use plugin settings)', 'advanced-language-switcher' ),
					'preset' => __( 'Preset', 'advanced-language-switcher' ),
					'custom' => __( 'Custom', 'advanced-language-switcher' ),
				),
				'description' => __( 'Global inherits every default from Language Translator → Settings. Preset starts from a ready made design. Custom exposes all controls.', 'advanced-language-switcher' ),
			)
		);

		$this->add_control(
			'preset',
			array(
				'label'     => __( 'Preset', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'compact-pill',
				'options'   => Switcher::presets(),
				'condition' => array( 'design_mode' => array( 'preset', 'custom' ) ),
			)
		);

		$this->add_control(
			'display',
			array(
				'label'     => __( 'Language Display', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'code',
				'options'   => Switcher::display_modes(),
				'condition' => array( 'design_mode' => array( 'preset', 'custom' ) ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'     => __( 'Layout', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'pills',
				'options'   => Switcher::layouts(),
				'condition' => array( 'design_mode' => array( 'preset', 'custom' ) ),
			)
		);

		$this->add_control(
			'languages',
			array(
				'label'       => __( 'Languages', 'advanced-language-switcher' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->language_options(),
				'default'     => array(),
				'label_block' => true,
				'description' => __( 'Leave empty to show every enabled language.', 'advanced-language-switcher' ),
			)
		);

		$this->add_control(
			'active_source',
			array(
				'label'   => __( 'Active Language', 'advanced-language-switcher' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'    => __( 'Automatically detect current language', 'advanced-language-switcher' ),
					'default' => __( 'Default language', 'advanced-language-switcher' ),
					'custom'  => __( 'Custom language', 'advanced-language-switcher' ),
				),
			)
		);

		$this->add_control(
			'active_custom',
			array(
				'label'     => __( 'Custom Active Language', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $this->language_options(),
				'condition' => array( 'active_source' => 'custom' ),
			)
		);

		$this->add_control(
			'behaviour',
			array(
				'label'   => __( 'Language Selection Behavior', 'advanced-language-switcher' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'navigate',
				'options' => array(
					'navigate' => __( 'Navigate to translated URL', 'advanced-language-switcher' ),
					'url'      => __( 'Change current URL', 'advanced-language-switcher' ),
					'reload'   => __( 'Reload current page', 'advanced-language-switcher' ),
					'ajax'     => __( 'AJAX language change', 'advanced-language-switcher' ),
				),
			)
		);

		$this->add_control(
			'hide_current',
			array(
				'label'        => __( 'Hide Active Language', 'advanced-language-switcher' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'current_first',
			array(
				'label'        => __( 'Show Active Language First', 'advanced-language-switcher' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( 'hide_current!' => 'yes' ),
			)
		);

		$this->add_control(
			'aria_label',
			array(
				'label'   => __( 'Accessible Label', 'advanced-language-switcher' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Select language', 'advanced-language-switcher' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_flags',
			array(
				'label'     => __( 'Flags', 'advanced-language-switcher' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'design_mode' => array( 'preset', 'custom' ) ),
			)
		);

		$this->add_control(
			'flag_style',
			array(
				'label'   => __( 'Flag Type', 'advanced-language-switcher' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'emoji',
				'options' => array(
					'none'  => __( 'No Flag', 'advanced-language-switcher' ),
					'emoji' => __( 'Emoji Flag', 'advanced-language-switcher' ),
					'svg'   => __( 'SVG Flag', 'advanced-language-switcher' ),
					'image' => __( 'Image Flag (uploaded per language)', 'advanced-language-switcher' ),
				),
			)
		);

		$this->add_control(
			'flag_shape',
			array(
				'label'     => __( 'Flag Shape', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'rounded',
				'options'   => array(
					'square'  => __( 'Square', 'advanced-language-switcher' ),
					'rounded' => __( 'Rounded', 'advanced-language-switcher' ),
					'circle'  => __( 'Circle', 'advanced-language-switcher' ),
				),
				'condition' => array( 'flag_style!' => 'none' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Style: container
	 * ------------------------------------------------------------------ */

	/**
	 * Container style controls.
	 *
	 * @return void
	 */
	protected function register_container_style_controls(): void {
		$this->start_controls_section(
			'section_container_style',
			array(
				'label' => __( 'Container', 'advanced-language-switcher' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'advanced-language-switcher' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'advanced-language-switcher' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'advanced-language-switcher' ),
						'icon'  => 'eicon-text-align-right',
					),
					'stretch'    => array(
						'title' => __( 'Justified', 'advanced-language-switcher' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default'   => 'flex-start',
				'selectors' => array(
					'{{WRAPPER}} .als-language-switcher' => 'justify-content: {{VALUE}};',
					'{{WRAPPER}}' => '--als-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_width',
			array(
				'label'      => __( 'Width', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'vw' ),
				'range'      => array(
					'px' => array(
						'min' => 40,
						'max' => 800,
					),
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_min_width',
			array(
				'label'      => __( 'Min Width', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 800 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'min-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_max_width',
			array(
				'label'      => __( 'Max Width', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'vw' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 1200 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_height',
			array(
				'label'      => __( 'Height', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 200 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_gap',
			array(
				'label'      => __( 'Gap Between Languages', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 2,
				),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_padding',
			array(
				'label'      => __( 'Padding', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_margin',
			array(
				'label'      => __( 'Margin', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'container_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .als-language-switcher',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'container_border',
				'selector' => '{{WRAPPER}} .als-language-switcher',
			)
		);

		$this->add_responsive_control(
			'container_radius',
			array(
				'label'      => __( 'Border Radius', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-switcher' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'container_shadow',
				'selector' => '{{WRAPPER}} .als-language-switcher',
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Style: items
	 * ------------------------------------------------------------------ */

	/**
	 * Language item style controls, with normal / hover / active tabs.
	 *
	 * @return void
	 */
	protected function register_item_style_controls(): void {
		$this->start_controls_section(
			'section_item_style',
			array(
				'label' => __( 'Language Items', 'advanced-language-switcher' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'item_typography',
				'selector' => '{{WRAPPER}} .als-language-item',
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => __( 'Padding', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'default'    => array(
					'top'      => 8,
					'right'    => 14,
					'bottom'   => 8,
					'left'     => 14,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_margin',
			array(
				'label'      => __( 'Margin', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_min_width',
			array(
				'label'      => __( 'Min Width', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 300 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item' => 'min-width: {{SIZE}}{{UNIT}}; justify-content: center;',
				),
			)
		);

		$this->add_responsive_control(
			'item_content_gap',
			array(
				'label'      => __( 'Gap Between Flag and Text', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 6,
				),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'item_transition',
			array(
				'label'      => __( 'Transition Duration', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ) ),
				'default'    => array(
					'unit' => 's',
					'size' => 0.3,
				),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item' => 'transition: all {{SIZE}}{{UNIT}} ease;',
				),
			)
		);

		$this->start_controls_tabs( 'item_state_tabs' );

		/* Normal ------------------------------------------------------- */
		$this->start_controls_tab(
			'item_tab_normal',
			array( 'label' => __( 'Normal', 'advanced-language-switcher' ) )
		);

		$this->add_control(
			'item_color',
			array(
				'label'     => __( 'Text Color', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-item' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'item_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .als-language-item',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'item_border',
				'selector' => '{{WRAPPER}} .als-language-item',
			)
		);

		$this->add_responsive_control(
			'item_radius',
			array(
				'label'      => __( 'Border Radius', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'item_shadow',
				'selector' => '{{WRAPPER}} .als-language-item',
			)
		);

		$this->end_controls_tab();

		/* Hover -------------------------------------------------------- */
		$this->start_controls_tab(
			'item_tab_hover',
			array( 'label' => __( 'Hover', 'advanced-language-switcher' ) )
		);

		$this->add_control(
			'item_color_hover',
			array(
				'label'     => __( 'Text Color', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-item:hover, {{WRAPPER}} .als-language-item:focus-visible' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'item_background_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .als-language-item:hover, {{WRAPPER}} .als-language-item:focus-visible',
			)
		);

		$this->add_control(
			'item_border_color_hover',
			array(
				'label'     => __( 'Border Color', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-item:hover, {{WRAPPER}} .als-language-item:focus-visible' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_radius_hover',
			array(
				'label'      => __( 'Border Radius', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'item_shadow_hover',
				'selector' => '{{WRAPPER}} .als-language-item:hover',
			)
		);

		$this->add_control(
			'item_opacity_hover',
			array(
				'label'     => __( 'Opacity', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
				'selectors' => array(
					'{{WRAPPER}} .als-language-item:hover' => 'opacity: {{SIZE}};',
				),
			)
		);

		$this->add_control(
			'item_translate_y_hover',
			array(
				'label'      => __( 'Translate Y', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => -30, 'max' => 30 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item:hover' => '--als-translate-y: {{SIZE}}px;',
				),
			)
		);

		$this->add_control(
			'item_translate_x_hover',
			array(
				'label'      => __( 'Translate X', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => -30, 'max' => 30 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item:hover' => '--als-translate-x: {{SIZE}}px;',
				),
			)
		);

		$this->add_control(
			'item_scale_hover',
			array(
				'label'     => __( 'Scale', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 2, 'step' => 0.01 ) ),
				'selectors' => array(
					'{{WRAPPER}} .als-language-item:hover' => '--als-scale: {{SIZE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'item_typography_hover',
				'selector' => '{{WRAPPER}} .als-language-item:hover',
			)
		);

		$this->end_controls_tab();

		/* Active ------------------------------------------------------- */
		$this->start_controls_tab(
			'item_tab_active',
			array( 'label' => __( 'Active', 'advanced-language-switcher' ) )
		);

		$this->add_control(
			'item_color_active',
			array(
				'label'     => __( 'Text Color', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-item--active' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'item_background_active',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .als-language-item--active',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'item_border_active',
				'selector' => '{{WRAPPER}} .als-language-item--active',
			)
		);

		$this->add_responsive_control(
			'item_radius_active',
			array(
				'label'      => __( 'Border Radius', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item--active' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_padding_active',
			array(
				'label'      => __( 'Padding', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-item--active' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'item_shadow_active',
				'selector' => '{{WRAPPER}} .als-language-item--active',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'item_typography_active',
				'selector' => '{{WRAPPER}} .als-language-item--active',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Style: flags
	 * ------------------------------------------------------------------ */

	/**
	 * Flag style controls.
	 *
	 * @return void
	 */
	protected function register_flag_style_controls(): void {
		$this->start_controls_section(
			'section_flag_style',
			array(
				'label'     => __( 'Flag', 'advanced-language-switcher' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'flag_style!' => 'none' ),
			)
		);

		$this->add_responsive_control(
			'flag_width',
			array(
				'label'      => __( 'Width', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-flag' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'flag_height',
			array(
				'label'      => __( 'Height', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-flag' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'flag_font_size',
			array(
				'label'      => __( 'Emoji Size', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-flag--emoji' => 'font-size: {{SIZE}}{{UNIT}}; line-height: 1;',
				),
				'condition'  => array( 'flag_style' => 'emoji' ),
			)
		);

		$this->add_responsive_control(
			'flag_radius',
			array(
				'label'      => __( 'Border Radius', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-flag' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'flag_border',
				'selector' => '{{WRAPPER}} .als-language-flag',
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Style: dropdown
	 * ------------------------------------------------------------------ */

	/**
	 * Dropdown style controls.
	 *
	 * @return void
	 */
	protected function register_dropdown_style_controls(): void {
		$this->start_controls_section(
			'section_dropdown_style',
			array(
				'label'     => __( 'Dropdown', 'advanced-language-switcher' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'layout' => 'dropdown' ),
			)
		);

		$this->add_control(
			'dropdown_animation',
			array(
				'label'   => __( 'Animation', 'advanced-language-switcher' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'fade',
				'options' => array(
					'none'  => __( 'None', 'advanced-language-switcher' ),
					'fade'  => __( 'Fade', 'advanced-language-switcher' ),
					'slide' => __( 'Slide', 'advanced-language-switcher' ),
					'scale' => __( 'Scale', 'advanced-language-switcher' ),
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_width',
			array(
				'label'      => __( 'Dropdown Width', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 500 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-dropdown' => 'min-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'dropdown_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .als-language-dropdown',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'dropdown_border',
				'selector' => '{{WRAPPER}} .als-language-dropdown',
			)
		);

		$this->add_responsive_control(
			'dropdown_radius',
			array(
				'label'      => __( 'Border Radius', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'dropdown_shadow',
				'selector' => '{{WRAPPER}} .als-language-dropdown',
			)
		);

		$this->add_responsive_control(
			'dropdown_item_padding',
			array(
				'label'      => __( 'Item Padding', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-dropdown__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_item_spacing',
			array(
				'label'      => __( 'Item Spacing', 'advanced-language-switcher' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .als-language-dropdown' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'dropdown_item_hover_background',
			array(
				'label'     => __( 'Item Hover Background', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-dropdown__item:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'dropdown_item_active_background',
			array(
				'label'     => __( 'Item Active Background', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-dropdown__item.als-language-item--active' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'dropdown_toggle_typography',
				'label'    => __( 'Toggle Typography', 'advanced-language-switcher' ),
				'selector' => '{{WRAPPER}} .als-language-toggle',
			)
		);

		$this->add_control(
			'dropdown_toggle_color',
			array(
				'label'     => __( 'Toggle Text Color', 'advanced-language-switcher' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .als-language-toggle' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Resolves the render arguments from the widget settings.
	 *
	 * @return array<string,mixed>
	 */
	protected function render_args(): array {
		$settings = $this->get_settings_for_display();
		$mode     = (string) ( $settings['design_mode'] ?? 'preset' );

		$args = Switcher::defaults();

		if ( 'global' !== $mode ) {
			$preset          = (string) ( $settings['preset'] ?? 'compact-pill' );
			$preset_defaults = Switcher::preset_defaults()[ $preset ] ?? array();

			$args['preset']     = $preset;
			$args['layout']     = (string) ( $settings['layout'] ?? ( $preset_defaults['layout'] ?? 'pills' ) );
			$args['display']    = (string) ( $settings['display'] ?? ( $preset_defaults['display'] ?? 'code' ) );
			$args['flag_style'] = (string) ( $settings['flag_style'] ?? 'emoji' );
			$args['flag_shape'] = (string) ( $settings['flag_shape'] ?? 'rounded' );
			$args['animation']  = (string) ( $settings['dropdown_animation'] ?? 'fade' );
		}

		$args['behaviour']     = (string) ( $settings['behaviour'] ?? $args['behaviour'] );
		$args['active_source'] = (string) ( $settings['active_source'] ?? 'auto' );
		$args['active_custom'] = (string) ( $settings['active_custom'] ?? '' );
		$args['hide_current']  = 'yes' === ( $settings['hide_current'] ?? '' );
		$args['current_first'] = 'yes' === ( $settings['current_first'] ?? '' );
		$args['languages']     = is_array( $settings['languages'] ?? null ) ? $settings['languages'] : array();
		$args['instance_id']   = 'als-switcher-' . $this->get_id();
		$args['is_preview']    = \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( ! empty( $settings['aria_label'] ) ) {
			$args['label'] = (string) $settings['aria_label'];
		}

		return $args;
	}

	/**
	 * Renders the widget on the front end and in the editor preview.
	 *
	 * @return void
	 */
	protected function render(): void {
		$languages = \ALS\plugin()->languages()->active();

		if ( ! $languages && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		// Switcher::render() escapes every value it emits.
		echo Switcher::render( $this->render_args() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Live JavaScript preview so control changes appear instantly.
	 *
	 * @return void
	 */
	protected function content_template(): void {
		?>
		<#
		var languages = ( window.ALSEditor && ALSEditor.languages ) ? ALSEditor.languages : [];
		var current = ( window.ALSEditor && ALSEditor.current ) ? ALSEditor.current : '';
		var mode = settings.design_mode || 'preset';
		var layout = mode === 'global' ? 'pills' : ( settings.layout || 'pills' );
		var preset = mode === 'global' ? 'compact-pill' : ( settings.preset || 'compact-pill' );
		var display = mode === 'global' ? 'code' : ( settings.display || 'code' );
		var flagStyle = mode === 'global' ? 'emoji' : ( settings.flag_style || 'emoji' );
		var flagShape = settings.flag_shape || 'rounded';
		var chosen = settings.languages || [];

		if ( chosen.length ) {
			languages = _.filter( languages, function( language ) {
				return _.contains( chosen, language.code );
			} );
		}

		if ( settings.active_source === 'default' ) {
			var defaultLanguage = _.findWhere( languages, { is_default: true } );
			current = defaultLanguage ? defaultLanguage.code : current;
		} else if ( settings.active_source === 'custom' && settings.active_custom ) {
			current = settings.active_custom;
		}

		if ( settings.hide_current === 'yes' && languages.length > 1 ) {
			languages = _.filter( languages, function( language ) {
				return language.code !== current;
			} );
		}

		var showFlag = flagStyle !== 'none' && display.indexOf( 'flag' ) !== -1;
		var showCode = display === 'code' || display.indexOf( '_code' ) !== -1;
		var showName = display === 'name' || display.indexOf( '_name' ) !== -1;

		var classes = 'als-language-switcher als-layout--' + layout + ' als-preset--' + preset +
			' als-display--' + display.replace( /_/g, '-' ) +
			' als-animation--' + ( settings.dropdown_animation || 'fade' );
		#>
		<# if ( ! languages.length ) { #>
			<div class="als-language-switcher als-language-switcher--empty">
				<span class="als-empty-notice">{{{ ( window.ALSEditor && ALSEditor.emptyMessage ) || 'No languages are enabled yet.' }}}</span>
			</div>
		<# } else { #>
			<div class="{{ classes }}" data-als-switcher="1" data-layout="{{ layout }}" role="group"
				aria-label="{{ settings.aria_label || ( window.ALSEditor && ALSEditor.selectLanguage ) || 'Select language' }}">
				<# if ( 'dropdown' === layout ) { #>
					<# var active = _.findWhere( languages, { code: current } ) || languages[0]; #>
					<button type="button" class="als-language-toggle" aria-haspopup="listbox" aria-expanded="false">
						<span class="als-language-toggle__label">
							<# if ( showFlag ) { #><span class="als-language-flag als-language-flag--{{ flagShape }} als-language-flag--{{ flagStyle }}">{{{ active.flag }}}</span><# } #>
							<# if ( showCode ) { #><span class="als-language-code">{{ active.code.toUpperCase() }}</span><# } #>
							<# if ( showName || ( ! showCode && ! showFlag ) ) { #><span class="als-language-name">{{ active.native_name || active.name }}</span><# } #>
						</span>
						<span class="als-language-caret" aria-hidden="true"></span>
					</button>
					<div class="als-language-dropdown" role="listbox">
						<# _.each( languages, function( language ) { #>
							<a class="als-language-item als-language-dropdown__item{{ language.code === current ? ' als-language-item--active active' : '' }}"
								href="#" role="option" data-language="{{ language.code }}">
								<# if ( showFlag ) { #><span class="als-language-flag als-language-flag--{{ flagShape }} als-language-flag--{{ flagStyle }}">{{{ language.flag }}}</span><# } #>
								<# if ( showCode ) { #><span class="als-language-code">{{ language.code.toUpperCase() }}</span><# } #>
								<# if ( showName ) { #><span class="als-language-name">{{ language.native_name || language.name }}</span><# } #>
							</a>
						<# } ); #>
					</div>
				<# } else { #>
					<# _.each( languages, function( language ) { #>
						<a class="als-language-item{{ language.code === current ? ' als-language-item--active active' : '' }}"
							href="#" data-language="{{ language.code }}" lang="{{ language.code }}">
							<# if ( showFlag ) { #><span class="als-language-flag als-language-flag--{{ flagShape }} als-language-flag--{{ flagStyle }}">{{{ language.flag }}}</span><# } #>
							<# if ( showCode ) { #><span class="als-language-code">{{ language.code.toUpperCase() }}</span><# } #>
							<# if ( showName ) { #><span class="als-language-name">{{ language.native_name || language.name }}</span><# } #>
							<# if ( ! showCode && ! showName ) { #><span class="als-language-name als-screen-reader-text">{{ language.native_name || language.name }}</span><# } #>
						</a>
					<# } ); #>
				<# } #>
			</div>
		<# } #>
		<?php
	}
}
