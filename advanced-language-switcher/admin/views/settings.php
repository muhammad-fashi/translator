<?php
/**
 * Translation settings screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ALS\Switcher;

$admin->field_group(
	__( 'Translation Scope', 'advanced-language-switcher' ),
	array(
		'translate_pages'     => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Pages', 'advanced-language-switcher' ),
		),
		'translate_posts'     => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Posts and custom post types', 'advanced-language-switcher' ),
		),
		'translate_products'  => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Products', 'advanced-language-switcher' ),
		),
		'translate_menus'     => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Menus', 'advanced-language-switcher' ),
		),
		'translate_widgets'   => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Widgets', 'advanced-language-switcher' ),
		),
		'translate_elementor' => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Elementor content', 'advanced-language-switcher' ),
		),
		'translate_theme'     => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Theme and plugin text', 'advanced-language-switcher' ),
		),
		'translate_forms'     => array(
			'type'  => 'toggle',
			'label' => __( 'Translate Forms', 'advanced-language-switcher' ),
		),
		'translate_seo_meta'  => array(
			'type'  => 'toggle',
			'label' => __( 'Translate SEO titles and descriptions', 'advanced-language-switcher' ),
		),
		'translate_urls'      => array(
			'type'        => 'toggle',
			'label'       => __( 'Translate URL slugs', 'advanced-language-switcher' ),
			'description' => __( 'Off by default: changing slugs affects existing links and search engine indexing.', 'advanced-language-switcher' ),
		),
	),
	__( 'Choose which parts of the site the translation layer applies to.', 'advanced-language-switcher' )
);

$admin->field_group(
	__( 'Automatic Translation', 'advanced-language-switcher' ),
	array(
		'auto_translate_new'       => array(
			'type'        => 'toggle',
			'label'       => __( 'Auto detect new content', 'advanced-language-switcher' ),
			'description' => __( 'Records newly published or edited text as translatable strings.', 'advanced-language-switcher' ),
		),
		'auto_translate_updated'   => array(
			'type'  => 'toggle',
			'label' => __( 'Re-scan updated content', 'advanced-language-switcher' ),
		),
		'auto_translate_missing'   => array(
			'type'        => 'toggle',
			'label'       => __( 'Automatically translate missing strings', 'advanced-language-switcher' ),
			'description' => __( 'Sends missing strings to the configured provider in the background.', 'advanced-language-switcher' ),
		),
		'auto_translate_on_render' => array(
			'type'        => 'toggle',
			'label'       => __( 'Translate on first view', 'advanced-language-switcher' ),
			'description' => __( 'Slower on the first request for a page and uses API quota. Leave off for production sites with many visitors.', 'advanced-language-switcher' ),
		),
		'overwrite_manual'         => array(
			'type'        => 'toggle',
			'label'       => __( 'Overwrite manual translations', 'advanced-language-switcher' ),
			'description' => __( 'Off by default so machine translation never replaces a human edit.', 'advanced-language-switcher' ),
		),
		'batch_size'               => array(
			'type'        => 'number',
			'label'       => __( 'Batch size', 'advanced-language-switcher' ),
			'min'         => 1,
			'max'         => 100,
			'description' => __( 'Strings sent to the provider per request. Lower this if you hit rate limits.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'Exclusions', 'advanced-language-switcher' ),
	array(
		'excluded_classes'      => array(
			'type'        => 'textarea',
			'label'       => __( 'Excluded CSS classes', 'advanced-language-switcher' ),
			'rows'        => 4,
			'description' => __( 'One class per line. Any element carrying one of these classes, and everything inside it, is left untranslated. The HTML attribute translate="no" is always honoured.', 'advanced-language-switcher' ),
		),
		'excluded_selectors'    => array(
			'type'        => 'textarea',
			'label'       => __( 'Excluded selectors', 'advanced-language-switcher' ),
			'rows'        => 3,
			'description' => __( 'One selector per line, used by the front end script for dynamically injected content.', 'advanced-language-switcher' ),
		),
		'excluded_strings'      => array(
			'type'        => 'textarea',
			'label'       => __( 'Excluded strings', 'advanced-language-switcher' ),
			'rows'        => 4,
			'description' => __( 'Exact strings that must never be translated, one per line. Useful for brand names.', 'advanced-language-switcher' ),
		),
		'excluded_post_ids'     => array(
			'type'        => 'textarea',
			'label'       => __( 'Excluded post IDs', 'advanced-language-switcher' ),
			'rows'        => 2,
			'description' => __( 'One ID per line. These posts, pages or products are skipped by the scanner.', 'advanced-language-switcher' ),
		),
		'excluded_widget_types' => array(
			'type'        => 'textarea',
			'label'       => __( 'Excluded Elementor widget types', 'advanced-language-switcher' ),
			'rows'        => 3,
			'description' => __( 'One widget type per line, for example html or shortcode.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'Language Switcher Defaults', 'advanced-language-switcher' ),
	array(
		'switcher_preset'    => array(
			'type'        => 'select',
			'label'       => __( 'Default Design', 'advanced-language-switcher' ),
			'options'     => Switcher::presets(),
			'description' => __( 'Used by the shortcode, the template function and any Elementor widget set to Global.', 'advanced-language-switcher' ),
		),
		'switcher_layout'    => array(
			'type'    => 'select',
			'label'   => __( 'Default Layout', 'advanced-language-switcher' ),
			'options' => Switcher::layouts(),
		),
		'switcher_display'   => array(
			'type'    => 'select',
			'label'   => __( 'Default Display', 'advanced-language-switcher' ),
			'options' => Switcher::display_modes(),
		),
		'switcher_flag_style' => array(
			'type'    => 'select',
			'label'   => __( 'Flag Type', 'advanced-language-switcher' ),
			'options' => array(
				'none'  => __( 'No Flag', 'advanced-language-switcher' ),
				'emoji' => __( 'Emoji Flag', 'advanced-language-switcher' ),
				'svg'   => __( 'SVG Flag', 'advanced-language-switcher' ),
				'image' => __( 'Image Flag', 'advanced-language-switcher' ),
			),
		),
		'switcher_behaviour' => array(
			'type'    => 'select',
			'label'   => __( 'Selection Behaviour', 'advanced-language-switcher' ),
			'options' => array(
				'navigate' => __( 'Navigate to translated URL', 'advanced-language-switcher' ),
				'url'      => __( 'Change current URL', 'advanced-language-switcher' ),
				'reload'   => __( 'Reload current page', 'advanced-language-switcher' ),
				'ajax'     => __( 'AJAX language change', 'advanced-language-switcher' ),
			),
		),
		'switcher_animation' => array(
			'type'    => 'select',
			'label'   => __( 'Dropdown Animation', 'advanced-language-switcher' ),
			'options' => array(
				'none'  => __( 'None', 'advanced-language-switcher' ),
				'fade'  => __( 'Fade', 'advanced-language-switcher' ),
				'slide' => __( 'Slide', 'advanced-language-switcher' ),
				'scale' => __( 'Scale', 'advanced-language-switcher' ),
			),
		),
		'ajax_switching'     => array(
			'type'        => 'toggle',
			'label'       => __( 'Enable AJAX language switching', 'advanced-language-switcher' ),
			'description' => __( 'For SEO friendly URLs, navigating to the translated URL is preferred and remains the default.', 'advanced-language-switcher' ),
		),
		'load_assets_everywhere' => array(
			'type'        => 'toggle',
			'label'       => __( 'Always load switcher assets', 'advanced-language-switcher' ),
			'description' => __( 'Off by default: the tiny CSS and JS load only when at least two languages are enabled.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'Right to Left', 'advanced-language-switcher' ),
	array(
		'rtl_support'    => array(
			'type'        => 'toggle',
			'label'       => __( 'Enable RTL handling', 'advanced-language-switcher' ),
			'description' => __( 'Sets dir="rtl" on the html element, adds an rtl body class and loads the theme rtl.css when one exists.', 'advanced-language-switcher' ),
		),
		'custom_rtl_css' => array(
			'type'        => 'textarea',
			'label'       => __( 'Custom RTL CSS', 'advanced-language-switcher' ),
			'rows'        => 6,
			'description' => __( 'Printed only when the active language is right to left.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'Diagnostics', 'advanced-language-switcher' ),
	array(
		'logging_enabled'           => array(
			'type'        => 'toggle',
			'label'       => __( 'Enable debug logging', 'advanced-language-switcher' ),
			'description' => __( 'Off by default. Logs provider calls, cache hits and errors to the System Status screen.', 'advanced-language-switcher' ),
		),
		'log_level'                 => array(
			'type'    => 'select',
			'label'   => __( 'Log level', 'advanced-language-switcher' ),
			'options' => array(
				'error'   => __( 'Errors only', 'advanced-language-switcher' ),
				'warning' => __( 'Warnings and errors', 'advanced-language-switcher' ),
				'info'    => __( 'Info', 'advanced-language-switcher' ),
				'debug'   => __( 'Debug (verbose)', 'advanced-language-switcher' ),
			),
		),
		'log_retention_days'        => array(
			'type'  => 'number',
			'label' => __( 'Keep logs for (days)', 'advanced-language-switcher' ),
			'min'   => 1,
			'max'   => 365,
		),
		'uninstall_remove_data'     => array(
			'type'        => 'toggle',
			'label'       => __( 'Remove translation data on uninstall', 'advanced-language-switcher' ),
			'description' => __( 'When off, deleting the plugin keeps every language and translation in place.', 'advanced-language-switcher' ),
		),
		'uninstall_remove_settings' => array(
			'type'  => 'toggle',
			'label' => __( 'Remove settings and API keys on uninstall', 'advanced-language-switcher' ),
		),
	)
);

$admin->save_button();
