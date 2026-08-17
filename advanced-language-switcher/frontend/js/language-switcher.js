/**
 * Advanced Language Switcher - front end behaviour.
 *
 * Responsibilities:
 *  - detect the current language and expose it as window.ALS.currentLanguage
 *  - handle clicks on every switcher on the page
 *  - keep multiple switchers synchronised
 *  - persist the choice in a cookie
 *  - navigate to the translated URL, or swap content over AJAX
 *  - keep the html lang / dir attributes in step
 *  - dispatch the `als_language_changed` event
 *
 * No dependencies, no build step, and it degrades to plain links when
 * JavaScript is unavailable.
 */

( function () {
	'use strict';

	var data = window.ALS_DATA || {};

	/**
	 * Reads a cookie value.
	 *
	 * @param {string} name Cookie name.
	 * @return {string} Value, or an empty string.
	 */
	function readCookie( name ) {
		var match = document.cookie.match(
			new RegExp( '(?:^|; )' + name.replace( /([.*+?^${}()|[\]\\])/g, '\\$1' ) + '=([^;]*)' )
		);

		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

	/**
	 * Writes the language cookie.
	 *
	 * @param {string} code Language code.
	 */
	function writeCookie( code ) {
		var days = parseInt( data.cookieDays, 10 ) || 30;
		var expires = new Date( Date.now() + days * 864e5 ).toUTCString();
		var secure = 'https:' === window.location.protocol ? '; Secure' : '';

		document.cookie =
			( data.cookieName || 'als_language' ) +
			'=' + encodeURIComponent( code ) +
			'; expires=' + expires +
			'; path=/; SameSite=Lax' + secure;
	}

	/**
	 * Finds a configured language by code.
	 *
	 * @param {string} code Language code.
	 * @return {Object|null} Language descriptor.
	 */
	function findLanguage( code ) {
		var languages = data.languages || [];

		for ( var i = 0; i < languages.length; i++ ) {
			if ( languages[ i ].code === code ) {
				return languages[ i ];
			}
		}

		return null;
	}

	/**
	 * The public API exposed on window.ALS.
	 */
	var ALS = window.ALS || {};

	ALS.currentLanguage = data.currentLanguage || readCookie( data.cookieName || 'als_language' ) || '';
	ALS.defaultLanguage = data.defaultLanguage || '';
	ALS.languages = data.languages || [];

	/**
	 * Returns the URL of the current page in another language.
	 *
	 * @param {string} code Language code.
	 * @return {string} URL.
	 */
	ALS.getLanguageUrl = function ( code ) {
		return ( data.urls && data.urls[ code ] ) || window.location.href;
	};

	/**
	 * Translates a string using the stored translation memory.
	 *
	 * Resolves with the original text when nothing is stored, so callers never
	 * have to handle a failure case.
	 *
	 * @param {string|string[]} text     Text to translate.
	 * @param {string}          language Target language code.
	 * @param {string}          context  Optional context.
	 * @return {Promise<string|string[]>} Translated text.
	 */
	ALS.translate = function ( text, language, context ) {
		var target = language || ALS.currentLanguage;
		var cacheKey = target + '|' + ( context || '' ) + '|' + text;

		if ( ALS._cache[ cacheKey ] !== undefined ) {
			return Promise.resolve( ALS._cache[ cacheKey ] );
		}

		if ( ! data.restUrl || ! window.fetch ) {
			return Promise.resolve( text );
		}

		return window
			.fetch( data.restUrl + 'translate', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					text: text,
					language: target,
					context: context || ''
				} )
			} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( payload ) {
				var result = payload && payload.translations !== undefined ? payload.translations : text;

				ALS._cache[ cacheKey ] = result;

				return result;
			} )
			.catch( function () {
				// A failed lookup must never break the page.
				return text;
			} );
	};

	ALS._cache = {};

	/**
	 * Switches the site to another language.
	 *
	 * @param {string} code      Language code.
	 * @param {string} behaviour navigate | url | reload | ajax.
	 */
	ALS.setLanguage = function ( code, behaviour ) {
		var language = findLanguage( code );

		if ( ! language || code === ALS.currentLanguage ) {
			return;
		}

		var url = ALS.getLanguageUrl( code );
		var mode = behaviour || data.behaviour || 'navigate';

		writeCookie( code );

		ALS.currentLanguage = code;

		syncSwitchers( code );
		applyDocumentLanguage( language );

		document.dispatchEvent(
			new CustomEvent( 'als_language_changed', {
				detail: {
					language: code,
					direction: language.direction,
					url: url
				}
			} )
		);

		if ( 'ajax' === mode && data.ajaxEnabled ) {
			loadOverAjax( url, code );

			return;
		}

		if ( 'reload' === mode ) {
			window.location.reload();

			return;
		}

		if ( 'url' === mode && window.history && window.history.pushState ) {
			window.history.pushState( { alsLanguage: code }, '', url );
			window.location.reload();

			return;
		}

		window.location.href = url;
	};

	/**
	 * Marks the active item in every switcher on the page.
	 *
	 * @param {string} code Language code.
	 */
	function syncSwitchers( code ) {
		var switchers = document.querySelectorAll( '[data-als-switcher]' );

		Array.prototype.forEach.call( switchers, function ( switcher ) {
			switcher.setAttribute( 'data-current', code );

			var items = switcher.querySelectorAll( '.als-language-item' );

			Array.prototype.forEach.call( items, function ( item ) {
				var isActive = item.getAttribute( 'data-language' ) === code;

				item.classList.toggle( 'als-language-item--active', isActive );
				item.classList.toggle( 'active', isActive );

				if ( isActive ) {
					item.setAttribute( 'aria-current', 'true' );
				} else {
					item.removeAttribute( 'aria-current' );
				}

				if ( item.hasAttribute( 'role' ) && 'option' === item.getAttribute( 'role' ) ) {
					item.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				}
			} );

			// Refresh the dropdown toggle label.
			var toggle = switcher.querySelector( '.als-language-toggle__label' );
			var active = switcher.querySelector( '.als-language-item--active' );

			if ( toggle && active ) {
				toggle.innerHTML = active.innerHTML;
			}
		} );
	}

	ALS.syncSwitchers = syncSwitchers;

	/**
	 * Updates the document language and direction.
	 *
	 * @param {Object} language Language descriptor.
	 */
	function applyDocumentLanguage( language ) {
		var root = document.documentElement;

		root.setAttribute( 'lang', language.htmlLang || language.code );
		root.setAttribute( 'dir', language.direction || 'ltr' );

		document.body.classList.toggle( 'als-rtl', 'rtl' === language.direction );
	}

	/**
	 * Replaces the page body with the translated version over AJAX.
	 *
	 * Falls back to a normal navigation whenever anything goes wrong, so the
	 * visitor always ends up on the right page.
	 *
	 * @param {string} url  Target URL.
	 * @param {string} code Language code.
	 */
	function loadOverAjax( url, code ) {
		if ( ! window.fetch ) {
			window.location.href = url;

			return;
		}

		document.documentElement.classList.add( 'als-loading' );

		window
			.fetch( url, {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Request failed' );
				}

				return response.text();
			} )
			.then( function ( html ) {
				var parsed = new DOMParser().parseFromString( html, 'text/html' );

				if ( ! parsed || ! parsed.body ) {
					throw new Error( 'Unparseable response' );
				}

				document.body.innerHTML = parsed.body.innerHTML;
				document.title = parsed.title;

				if ( window.history && window.history.pushState ) {
					window.history.pushState( { alsLanguage: code }, '', url );
				}

				document.documentElement.classList.remove( 'als-loading' );

				bindSwitchers();
				syncSwitchers( code );

				document.dispatchEvent(
					new CustomEvent( 'als_content_replaced', { detail: { language: code, url: url } } )
				);
			} )
			.catch( function () {
				window.location.href = url;
			} );
	}

	/**
	 * Handles a click on a language item.
	 *
	 * @param {Event} event Click event.
	 */
	function onItemClick( event ) {
		var item = event.currentTarget;
		var switcher = item.closest( '[data-als-switcher]' );
		var code = item.getAttribute( 'data-language' );

		if ( ! code ) {
			return;
		}

		var behaviour = switcher ? switcher.getAttribute( 'data-behaviour' ) : data.behaviour;

		// Let modified clicks (new tab, download) behave normally.
		if ( event.metaKey || event.ctrlKey || event.shiftKey || 1 === event.button ) {
			return;
		}

		event.preventDefault();

		closeDropdowns();
		ALS.setLanguage( code, behaviour );
	}

	/**
	 * Opens or closes a dropdown.
	 *
	 * @param {Event} event Click event.
	 */
	function onToggleClick( event ) {
		event.preventDefault();

		var toggle = event.currentTarget;
		var switcher = toggle.closest( '[data-als-switcher]' );

		if ( ! switcher ) {
			return;
		}

		var list = switcher.querySelector( '.als-language-dropdown' );

		if ( ! list ) {
			return;
		}

		var isOpen = 'true' === toggle.getAttribute( 'aria-expanded' );

		closeDropdowns();

		if ( ! isOpen ) {
			toggle.setAttribute( 'aria-expanded', 'true' );
			list.hidden = false;

			var first = list.querySelector( '.als-language-item' );

			if ( first ) {
				first.focus();
			}
		}
	}

	/**
	 * Closes every open dropdown.
	 */
	function closeDropdowns() {
		var toggles = document.querySelectorAll( '.als-language-toggle[aria-expanded="true"]' );

		Array.prototype.forEach.call( toggles, function ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'false' );

			var switcher = toggle.closest( '[data-als-switcher]' );
			var list = switcher ? switcher.querySelector( '.als-language-dropdown' ) : null;

			if ( list ) {
				list.hidden = true;
			}
		} );
	}

	/**
	 * Keyboard support: arrows move between languages, Escape closes.
	 *
	 * @param {KeyboardEvent} event Key event.
	 */
	function onKeyDown( event ) {
		if ( 'Escape' === event.key ) {
			var openToggle = document.querySelector( '.als-language-toggle[aria-expanded="true"]' );

			closeDropdowns();

			if ( openToggle ) {
				openToggle.focus();
			}

			return;
		}

		if ( 'ArrowDown' !== event.key && 'ArrowUp' !== event.key ) {
			return;
		}

		var active = document.activeElement;

		if ( ! active || ! active.classList || ! active.classList.contains( 'als-language-item' ) ) {
			return;
		}

		var switcher = active.closest( '[data-als-switcher]' );

		if ( ! switcher ) {
			return;
		}

		var items = Array.prototype.slice.call( switcher.querySelectorAll( '.als-language-item' ) );
		var index = items.indexOf( active );

		if ( -1 === index ) {
			return;
		}

		event.preventDefault();

		var next = 'ArrowDown' === event.key ? index + 1 : index - 1;

		if ( next < 0 ) {
			next = items.length - 1;
		}

		if ( next >= items.length ) {
			next = 0;
		}

		items[ next ].focus();
	}

	/**
	 * Binds every switcher currently in the document.
	 */
	function bindSwitchers() {
		var items = document.querySelectorAll( '[data-als-switcher] .als-language-item' );

		Array.prototype.forEach.call( items, function ( item ) {
			if ( item.alsBound ) {
				return;
			}

			item.alsBound = true;
			item.addEventListener( 'click', onItemClick );
		} );

		var toggles = document.querySelectorAll( '[data-als-switcher] .als-language-toggle' );

		Array.prototype.forEach.call( toggles, function ( toggle ) {
			if ( toggle.alsBound ) {
				return;
			}

			toggle.alsBound = true;
			toggle.addEventListener( 'click', onToggleClick );
		} );
	}

	ALS.bind = bindSwitchers;

	/**
	 * Boots the script.
	 */
	function init() {
		bindSwitchers();

		if ( ALS.currentLanguage ) {
			syncSwitchers( ALS.currentLanguage );
		}

		document.addEventListener( 'keydown', onKeyDown );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest || ! event.target.closest( '[data-als-switcher]' ) ) {
				closeDropdowns();
			}
		} );

		// Elementor re-renders widgets in the editor preview.
		if ( window.jQuery ) {
			window.jQuery( window ).on( 'elementor/frontend/init', function () {
				bindSwitchers();
			} );
		}

		// New switchers injected by other scripts are picked up automatically.
		if ( window.MutationObserver ) {
			new window.MutationObserver( function () {
				bindSwitchers();
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	window.ALS = ALS;

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
