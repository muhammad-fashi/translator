/**
 * Advanced Language Switcher - Elementor editor helper.
 *
 * The widget's live preview template reads the language list from ALSEditor,
 * which is localized by Elementor_Integration::enqueue_editor_assets(). This
 * file only wires the panel behaviour that the template cannot express.
 */

( function ( $ ) {
	'use strict';

	if ( ! window.elementor ) {
		return;
	}

	/**
	 * Preset slugs mapped to the layout and display mode they imply.
	 */
	var PRESETS = {
		'compact-pill': { layout: 'pills', display: 'code' },
		'minimal-text': { layout: 'minimal', display: 'code' },
		'flag-code': { layout: 'horizontal', display: 'flag_code' },
		'flag-name': { layout: 'horizontal', display: 'flag_name' },
		dropdown: { layout: 'dropdown', display: 'flag_name' },
		'modern-segmented': { layout: 'segmented', display: 'code' },
		glassmorphism: { layout: 'pills', display: 'code' },
		'minimal-border': { layout: 'horizontal', display: 'code' }
	};

	/**
	 * Applies a preset's implied layout and display mode when the preset
	 * control changes, so choosing a design is a single click.
	 */
	elementor.hooks.addAction( 'panel/open_editor/widget/als-language-switcher', function ( panel, model ) {
		var settings = model.get( 'settings' );

		panel.$el.on( 'change', '.elementor-control-preset select', function () {
			var preset = PRESETS[ $( this ).val() ];

			if ( ! preset || 'custom' === settings.get( 'design_mode' ) ) {
				return;
			}

			settings.set( 'layout', preset.layout );
			settings.set( 'display', preset.display );
		} );
	} );
} )( jQuery );
