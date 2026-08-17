/**
 * Advanced Language Switcher - admin behaviour.
 *
 * Everything here talks to admin-ajax with the plugin nonce; the server
 * re-checks the nonce and the capability on every request, so nothing in this
 * file is a trust boundary.
 */

( function () {
	'use strict';

	var config = window.ALSAdmin || {};
	var i18n = config.i18n || {};

	/**
	 * Shows a message in the flash area.
	 *
	 * @param {string}  message Message text.
	 * @param {boolean} isError Whether this is an error.
	 */
	function flash( message, isError ) {
		var target = document.getElementById( 'als-flash' );

		if ( ! target ) {
			return;
		}

		target.textContent = message;
		target.className = 'als-flash' + ( isError ? ' als-flash--error' : '' );

		if ( ! isError ) {
			window.clearTimeout( flash.timer );
			flash.timer = window.setTimeout( function () {
				target.textContent = '';
				target.className = 'als-flash';
			}, 4000 );
		}
	}

	/**
	 * Posts to admin-ajax.
	 *
	 * @param {string}          action Action suffix, without the als_ prefix.
	 * @param {Object|FormData} data   Payload.
	 * @return {Promise<Object>} Resolved response data.
	 */
	function post( action, data ) {
		var body;

		if ( data instanceof FormData ) {
			body = data;
			body.append( 'action', 'als_' + action );
			body.append( 'nonce', config.nonce );
		} else {
			body = new FormData();
			body.append( 'action', 'als_' + action );
			body.append( 'nonce', config.nonce );

			Object.keys( data || {} ).forEach( function ( key ) {
				var value = data[ key ];

				if ( Array.isArray( value ) ) {
					value.forEach( function ( entry ) {
						body.append( key + '[]', entry );
					} );

					return;
				}

				body.append( key, value );
			} );
		}

		return window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					var message = payload && payload.data && payload.data.message ? payload.data.message : i18n.error;

					throw new Error( message );
				}

				return payload.data || {};
			} );
	}

	/**
	 * Collects every settings control on the page.
	 *
	 * @return {Object} Settings keyed by name.
	 */
	function collectSettings() {
		var settings = {};
		var fields = document.querySelectorAll( '[data-als-setting]' );

		Array.prototype.forEach.call( fields, function ( field ) {
			var key = field.getAttribute( 'data-als-setting' );

			if ( 'checkbox' === field.type ) {
				settings[ key ] = field.checked ? 1 : 0;

				return;
			}

			if ( 'radio' === field.type ) {
				if ( field.checked ) {
					settings[ key ] = field.value;
				}

				return;
			}

			settings[ key ] = field.value;
		} );

		return settings;
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	function bindSettings() {
		var buttons = document.querySelectorAll( '[data-als-save-settings]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				button.disabled = true;
				flash( i18n.saving, false );

				post( 'save_settings', { settings: JSON.stringify( collectSettings() ) } )
					.then( function ( data ) {
						flash( data.message || i18n.saved, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} )
					.finally( function () {
						button.disabled = false;
					} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Languages
	 * ------------------------------------------------------------------ */

	function bindLanguages() {
		var form = document.getElementById( 'als-language-form' );

		if ( form ) {
			var preset = document.getElementById( 'als-language-preset' );

			if ( preset ) {
				preset.addEventListener( 'change', function () {
					var option = preset.options[ preset.selectedIndex ];
					var raw = option ? option.getAttribute( 'data-language' ) : null;

					if ( ! raw ) {
						return;
					}

					var language = JSON.parse( raw );

					form.querySelector( '[name="name"]' ).value = language.name || '';
					form.querySelector( '[name="native_name"]' ).value = language.native_name || '';
					form.querySelector( '[name="code"]' ).value = language.code || '';
					form.querySelector( '[name="locale"]' ).value = language.locale || '';
					form.querySelector( '[name="country"]' ).value = language.country || '';
					form.querySelector( '[name="flag"]' ).value = language.flag || '';
					form.querySelector( '[name="direction"]' ).value = language.direction || 'auto';
				} );
			}

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var payload = {};

				Array.prototype.forEach.call( form.elements, function ( element ) {
					if ( ! element.name ) {
						return;
					}

					payload[ element.name ] = 'checkbox' === element.type ? ( element.checked ? 1 : 0 ) : element.value;
				} );

				flash( i18n.saving, false );

				post( 'save_language', payload )
					.then( function ( data ) {
						flash( data.message || i18n.saved, false );
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		}

		var editButtons = document.querySelectorAll( '[data-als-edit-language]' );

		Array.prototype.forEach.call( editButtons, function ( button ) {
			button.addEventListener( 'click', function () {
				var language = JSON.parse( button.getAttribute( 'data-als-edit-language' ) );
				var target = document.getElementById( 'als-language-form' );

				if ( ! target ) {
					return;
				}

				target.querySelector( '[name="id"]' ).value = language.id;
				target.querySelector( '[name="name"]' ).value = language.name;
				target.querySelector( '[name="native_name"]' ).value = language.native_name;
				target.querySelector( '[name="code"]' ).value = language.code;
				target.querySelector( '[name="locale"]' ).value = language.locale;
				target.querySelector( '[name="country"]' ).value = language.country;
				target.querySelector( '[name="flag"]' ).value = language.flag;
				target.querySelector( '[name="flag_url"]' ).value = language.flag_url;
				target.querySelector( '[name="direction"]' ).value = language.direction;
				target.querySelector( '[name="status"]' ).checked = !! language.status;
				target.querySelector( '[name="is_default"]' ).checked = !! language.is_default;

				var title = document.getElementById( 'als-language-form-title' );

				if ( title ) {
					title.textContent = language.name;
				}

				target.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			} );
		} );

		var resetButton = document.querySelector( '[data-als-reset-language-form]' );

		if ( resetButton ) {
			resetButton.addEventListener( 'click', function () {
				var target = document.getElementById( 'als-language-form' );

				if ( target ) {
					target.reset();
					target.querySelector( '[name="id"]' ).value = '0';
				}
			} );
		}

		var actionButtons = document.querySelectorAll( '[data-als-language-action]' );

		Array.prototype.forEach.call( actionButtons, function ( button ) {
			button.addEventListener( 'click', function () {
				if ( button.getAttribute( 'data-confirm' ) && ! window.confirm( i18n.confirmDelete ) ) {
					return;
				}

				var payload = { id: button.getAttribute( 'data-id' ) };

				if ( button.hasAttribute( 'data-status' ) ) {
					payload.status = button.getAttribute( 'data-status' );
				}

				post( button.getAttribute( 'data-als-language-action' ), payload )
					.then( function ( data ) {
						flash( data.message || i18n.saved, false );
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );

		bindReordering();
	}

	/**
	 * Simple drag and drop ordering for the languages table.
	 */
	function bindReordering() {
		var table = document.getElementById( 'als-languages-table' );

		if ( ! table ) {
			return;
		}

		var body = table.querySelector( 'tbody' );
		var dragged = null;

		body.addEventListener( 'dragstart', function ( event ) {
			dragged = event.target.closest( 'tr' );

			if ( dragged ) {
				event.dataTransfer.effectAllowed = 'move';
			}
		} );

		body.addEventListener( 'dragover', function ( event ) {
			event.preventDefault();

			var over = event.target.closest( 'tr' );

			if ( ! over || ! dragged || over === dragged ) {
				return;
			}

			var rect = over.getBoundingClientRect();
			var after = event.clientY > rect.top + rect.height / 2;

			body.insertBefore( dragged, after ? over.nextSibling : over );
		} );

		body.addEventListener( 'drop', function ( event ) {
			event.preventDefault();

			var order = Array.prototype.map.call( body.querySelectorAll( 'tr' ), function ( row ) {
				return row.getAttribute( 'data-language-id' );
			} );

			post( 'reorder_languages', { order: order } )
				.then( function ( data ) {
					flash( data.message || i18n.saved, false );
				} )
				.catch( function ( error ) {
					flash( error.message, true );
				} );

			dragged = null;
		} );
	}

	/* ---------------------------------------------------------------------
	 * Providers
	 * ------------------------------------------------------------------ */

	function bindProviders() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-save-key]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var provider = button.getAttribute( 'data-als-save-key' );
				var input = document.getElementById( 'als-key-' + provider );

				if ( ! input || '' === input.value.trim() ) {
					flash( i18n.error, true );

					return;
				}

				post( 'save_credential', { provider: provider, value: input.value } )
					.then( function ( data ) {
						input.value = '';
						input.placeholder = data.masked || '';
						flash( data.message || i18n.saved, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-clear-key]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				if ( ! window.confirm( i18n.confirmDelete ) ) {
					return;
				}

				post( 'save_credential', { provider: button.getAttribute( 'data-als-clear-key' ), value: '' } )
					.then( function () {
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-test-connection]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				button.disabled = true;
				flash( i18n.saving, false );

				post( 'test_connection', { provider: button.getAttribute( 'data-als-test-connection' ) } )
					.then( function ( data ) {
						flash( data.message || i18n.done, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} )
					.finally( function () {
						button.disabled = false;
					} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Translation editor
	 * ------------------------------------------------------------------ */

	function rowPayload( row ) {
		return {
			string_id: row.getAttribute( 'data-string-id' ),
			language_id: row.getAttribute( 'data-language-id' ),
			translation: row.querySelector( '[data-als-translation]' ).value
		};
	}

	function updateBadge( row, status ) {
		var badge = row.querySelector( '[data-als-status-badge]' );

		if ( ! badge ) {
			return;
		}

		badge.className = 'als-badge als-badge--' + String( status ).replace( /_/g, '-' );
		badge.textContent = status;
	}

	function saveRow( row, status ) {
		var payload = rowPayload( row );

		payload.status = status || 'manual';

		row.classList.add( 'als-row-saving' );

		return post( 'save_translation', payload )
			.then( function ( data ) {
				updateBadge( row, data.status || payload.status );
				flash( data.message || i18n.saved, false );
			} )
			.catch( function ( error ) {
				flash( error.message, true );
			} )
			.finally( function () {
				row.classList.remove( 'als-row-saving' );
			} );
	}

	function bindTranslationEditor() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-save-translation]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				saveRow( button.closest( 'tr' ) );
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-mark-reviewed]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				saveRow( button.closest( 'tr' ), 'translated' );
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-copy-original]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var row = button.closest( 'tr' );
				var source = row.querySelector( '.als-source-text' );
				var input = row.querySelector( '[data-als-translation]' );

				if ( source && input ) {
					input.value = source.textContent.trim();
					input.focus();
				}
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-reset-translation]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var row = button.closest( 'tr' );

				if ( ! window.confirm( i18n.confirmDelete ) ) {
					return;
				}

				post( 'reset_translation', {
					string_id: row.getAttribute( 'data-string-id' ),
					language_id: row.getAttribute( 'data-language-id' )
				} )
					.then( function ( data ) {
						row.querySelector( '[data-als-translation]' ).value = '';
						updateBadge( row, 'missing' );
						flash( data.message || i18n.done, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );

		// Ctrl+S saves, Ctrl+Enter saves and moves on.
		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-translation]' ), function ( input ) {
			input.addEventListener( 'keydown', function ( event ) {
				if ( ! event.ctrlKey && ! event.metaKey ) {
					return;
				}

				if ( 's' === event.key.toLowerCase() ) {
					event.preventDefault();
					saveRow( input.closest( 'tr' ) );

					return;
				}

				if ( 'Enter' === event.key ) {
					event.preventDefault();

					var row = input.closest( 'tr' );

					saveRow( row ).then( function () {
						var next = row.nextElementSibling;

						if ( next ) {
							var field = next.querySelector( '[data-als-translation]' );

							if ( field ) {
								field.focus();
							}
						}
					} );
				}
			} );
		} );

		var selectAll = document.querySelector( '[data-als-select-all]' );

		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				Array.prototype.forEach.call( document.querySelectorAll( '[data-als-string-check]' ), function ( box ) {
					box.checked = selectAll.checked;
				} );

				refreshSelectedButton();
			} );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-string-check]' ), function ( box ) {
			box.addEventListener( 'change', refreshSelectedButton );
		} );

		bindAutoTranslate();
		bindGlossary();
	}

	function selectedStringIds() {
		return Array.prototype.map.call(
			document.querySelectorAll( '[data-als-string-check]:checked' ),
			function ( box ) {
				return box.value;
			}
		);
	}

	function refreshSelectedButton() {
		var button = document.querySelector( '[data-als-auto-translate="selected"]' );

		if ( button ) {
			button.disabled = 0 === selectedStringIds().length;
		}
	}

	function bindAutoTranslate() {
		var panel = document.querySelector( '.als-bulk' );

		if ( ! panel ) {
			return;
		}

		var languageId = panel.getAttribute( 'data-language-id' );
		var progress = panel.querySelector( '.als-batch-progress' );
		var bar = progress ? progress.querySelector( '.als-progress__bar span' ) : null;
		var label = progress ? progress.querySelector( '.als-batch-progress__label' ) : null;

		Array.prototype.forEach.call( panel.querySelectorAll( '[data-als-auto-translate]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var scope = button.getAttribute( 'data-als-auto-translate' );
				var selected = 'selected' === scope ? selectedStringIds() : [];
				var translated = 0;

				if ( progress ) {
					progress.hidden = false;
				}

				function runBatch() {
					var payload = {
						language_id: languageId,
						scope: 'all' === scope ? 'all' : 'missing'
					};

					if ( selected.length ) {
						payload.string_ids = selected.splice( 0, 25 );
					}

					return post( 'auto_translate', payload ).then( function ( data ) {
						translated += data.translated || 0;

						if ( label ) {
							label.textContent = i18n.translating + ' ' + translated + ' / ' + ( translated + ( data.remaining || 0 ) );
						}

						if ( bar ) {
							var total = translated + ( data.remaining || 0 );
							bar.style.width = ( total > 0 ? Math.round( ( translated / total ) * 100 ) : 100 ) + '%';
						}

						var keepGoing = 'selected' === scope ? selected.length > 0 : data.remaining > 0 && data.translated > 0;

						if ( keepGoing ) {
							return runBatch();
						}

						if ( label ) {
							label.textContent = i18n.done + ' ' + translated;
						}

						flash( i18n.done, false );
						window.setTimeout( function () {
							window.location.reload();
						}, 900 );

						return data;
					} );
				}

				button.disabled = true;

				runBatch()
					.catch( function ( error ) {
						flash( error.message, true );

						if ( label ) {
							label.textContent = error.message;
						}
					} )
					.finally( function () {
						button.disabled = false;
					} );
			} );
		} );
	}

	function bindGlossary() {
		var form = document.getElementById( 'als-glossary-form' );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var payload = {};

				Array.prototype.forEach.call( form.elements, function ( element ) {
					if ( ! element.name ) {
						return;
					}

					payload[ element.name ] = 'checkbox' === element.type ? ( element.checked ? 1 : 0 ) : element.value;
				} );

				post( 'save_glossary', payload )
					.then( function () {
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-delete-glossary]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				post( 'delete_glossary', { id: button.getAttribute( 'data-als-delete-glossary' ) } )
					.then( function () {
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Scanner
	 * ------------------------------------------------------------------ */

	function bindScanner() {
		var button = document.querySelector( '[data-als-scan]' );

		if ( ! button ) {
			return;
		}

		var panel = document.getElementById( 'als-scan-progress' );
		var bar = panel ? panel.querySelector( '.als-progress__bar span' ) : null;
		var label = panel ? panel.querySelector( '.als-scan-progress__label' ) : null;
		var counters = panel ? panel.querySelector( '.als-scan-progress__counters' ) : null;

		var steps = [ 'posts', 'terms', 'menus', 'widgets', 'theme' ];

		function renderCounters( data ) {
			if ( ! counters || ! data ) {
				return;
			}

			counters.innerHTML = '';

			Object.keys( data ).forEach( function ( key ) {
				var item = document.createElement( 'li' );

				item.textContent = key + ': ' + data[ key ];
				counters.appendChild( item );
			} );
		}

		function step( name, offset ) {
			return post( 'scan_website', { step: name, offset: offset } ).then( function ( data ) {
				if ( label ) {
					label.textContent = data.label || i18n.scanning;
				}

				renderCounters( data.counters );

				if ( bar ) {
					var index = steps.indexOf( data.step );
					var percent = data.done ? 100 : Math.max( 5, Math.round( ( ( index < 0 ? steps.length : index ) / steps.length ) * 100 ) );

					bar.style.width = percent + '%';
				}

				if ( data.done ) {
					flash( i18n.done, false );
					window.setTimeout( function () {
						window.location.reload();
					}, 1200 );

					return data;
				}

				return step( data.step, data.offset );
			} );
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;

			if ( panel ) {
				panel.hidden = false;
			}

			flash( i18n.scanning, false );

			step( 'start', 0 )
				.catch( function ( error ) {
					flash( error.message, true );
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Cache, logs, backups, transfer
	 * ------------------------------------------------------------------ */

	function bindMaintenance() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-clear-cache]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				post( 'clear_cache', { scope: button.getAttribute( 'data-als-clear-cache' ) } )
					.then( function ( data ) {
						flash( data.message || i18n.done, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );

		var clearLogs = document.querySelector( '[data-als-clear-logs]' );

		if ( clearLogs ) {
			clearLogs.addEventListener( 'click', function () {
				post( 'clear_logs', {} )
					.then( function ( data ) {
						flash( data.message || i18n.done, false );
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		}

		var backupForm = document.getElementById( 'als-backup-form' );

		if ( backupForm ) {
			backupForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				post( 'create_backup', {
					language_id: backupForm.querySelector( '[name="language_id"]' ).value,
					name: backupForm.querySelector( '[name="name"]' ).value
				} )
					.then( function () {
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-restore-backup]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				if ( ! window.confirm( i18n.confirmDelete ) ) {
					return;
				}

				post( 'restore_backup', { id: button.getAttribute( 'data-als-restore-backup' ) } )
					.then( function ( data ) {
						flash( data.message || i18n.done, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-als-delete-backup]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				if ( ! window.confirm( i18n.confirmDelete ) ) {
					return;
				}

				post( 'delete_backup', { id: button.getAttribute( 'data-als-delete-backup' ) } )
					.then( function () {
						window.location.reload();
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		} );
	}

	function bindTransfer() {
		var exportForm = document.getElementById( 'als-export-form' );

		if ( exportForm ) {
			exportForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				post( 'export_translations', {
					language_id: exportForm.querySelector( '[name="language_id"]' ).value,
					format: exportForm.querySelector( '[name="format"]' ).value
				} )
					.then( function ( data ) {
						var blob;

						if ( 'base64' === data.encoding ) {
							var binary = window.atob( data.content );
							var bytes = new Uint8Array( binary.length );

							for ( var i = 0; i < binary.length; i++ ) {
								bytes[ i ] = binary.charCodeAt( i );
							}

							blob = new Blob( [ bytes ], { type: data.mime } );
						} else {
							blob = new Blob( [ data.content ], { type: data.mime + ';charset=utf-8' } );
						}

						var url = URL.createObjectURL( blob );
						var link = document.createElement( 'a' );

						link.href = url;
						link.download = data.filename;
						document.body.appendChild( link );
						link.click();
						document.body.removeChild( link );
						URL.revokeObjectURL( url );

						flash( i18n.done, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		}

		var importForm = document.getElementById( 'als-import-form' );

		if ( importForm ) {
			importForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var body = new FormData( importForm );

				flash( i18n.saving, false );

				post( 'import_translations', body )
					.then( function ( data ) {
						flash( data.message || i18n.done, false );
					} )
					.catch( function ( error ) {
						flash( error.message, true );
					} );
			} );
		}
	}

	function bindNotices() {
		var notice = document.querySelector( '[data-als-dismiss]' );

		if ( ! notice ) {
			return;
		}

		notice.addEventListener( 'click', function ( event ) {
			if ( ! event.target.classList.contains( 'notice-dismiss' ) ) {
				return;
			}

			post( 'dismiss_notice', {} ).catch( function () {} );
		} );
	}

	function init() {
		bindSettings();
		bindLanguages();
		bindProviders();
		bindTranslationEditor();
		bindScanner();
		bindMaintenance();
		bindTransfer();
		bindNotices();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
