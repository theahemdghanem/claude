/**
 * Eye Comfort Dark Mode — toolbar menu, keyboard shortcut and saving.
 */
( function ( win, doc ) {
	'use strict';

	var engine = win.ECDM;
	var cfg = win.ecdmConfig || {};
	if ( ! engine ) {
		return;
	}

	function save( changes ) {
		if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
			return;
		}
		var body = new URLSearchParams();
		body.append( 'action', 'ecdm_save' );
		body.append( '_ajax_nonce', cfg.nonce );
		Object.keys( changes ).forEach( function ( k ) {
			var v = changes[ k ];
			body.append( k, typeof v === 'boolean' ? ( v ? '1' : '0' ) : v );
		} );
		win.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).catch( function () {} );
	}

	function apply( changes ) {
		engine.setPrefs( changes );
		save( changes );
		render();
	}

	function toggle() {
		apply( { mode: engine.isActive() ? 'off' : 'on' } );
	}

	/* Toolbar menu ------------------------------------------------------ */

	function render() {
		var p = engine.prefs;
		var node = doc.getElementById( 'wp-admin-bar-ecdm' );
		if ( ! node ) {
			return;
		}
		node.classList.toggle( 'ecdm-is-dark', engine.isActive() );
		var label = node.querySelector( '.ecdm-ab-label' );
		if ( label ) {
			label.textContent = engine.isActive() ? cfg.i18n.dark : cfg.i18n.light;
		}
		var top = node.querySelector( '.ab-item' );
		if ( top ) {
			top.setAttribute( 'aria-pressed', engine.isActive() ? 'true' : 'false' );
		}
		node.querySelectorAll( '[id^="wp-admin-bar-ecdm-set-"]' ).forEach( function ( li ) {
			var parts = li.id.replace( 'wp-admin-bar-ecdm-set-', '' ).split( '-' );
			var on = String( p[ parts[ 0 ] ] ) === parts[ 1 ];
			li.classList.toggle( 'ecdm-current', on );
			var a = li.querySelector( '.ab-item' );
			if ( a ) {
				a.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			}
		} );
	}

	doc.addEventListener( 'click', function ( e ) {
		var a = e.target.closest && e.target.closest( '#wp-admin-bar-ecdm .ab-item' );
		if ( ! a ) {
			return;
		}
		var li = a.parentNode;
		if ( li.id === 'wp-admin-bar-ecdm' ) {
			e.preventDefault();
			// On touch screens a tap opens the menu instead (there is no hover).
			if ( e.pointerType !== 'touch' && ! ( win.matchMedia && win.matchMedia( '(hover: none)' ).matches ) ) {
				toggle();
			}
			return;
		}
		if ( li.id.indexOf( 'wp-admin-bar-ecdm-set-' ) === 0 ) {
			e.preventDefault();
			var parts = li.id.replace( 'wp-admin-bar-ecdm-set-', '' ).split( '-' );
			var change = {};
			change[ parts[ 0 ] ] = parts[ 1 ];
			apply( change );
		}
	} );

	/* Keyboard shortcut: Alt + Shift + D -------------------------------- */

	function onKey( e ) {
		if ( e.altKey && e.shiftKey && ! e.ctrlKey && ! e.metaKey && e.code === 'KeyD' ) {
			e.preventDefault();
			toggle();
		}
	}
	doc.addEventListener( 'keydown', onKey );

	// Let the shortcut work while typing inside the editor canvas too.
	function bindFrame( frame ) {
		try {
			var d = frame.contentDocument;
			if ( d && ! d.__ecdmKeys ) {
				d.__ecdmKeys = true;
				d.addEventListener( 'keydown', onKey );
			}
		} catch ( e ) {}
	}
	function scanFrames() {
		doc.querySelectorAll( 'iframe[name="editor-canvas"], iframe[id$="_ifr"]' ).forEach( function ( f ) {
			bindFrame( f );
			if ( ! f.__ecdmKeyLoad ) {
				f.__ecdmKeyLoad = true;
				f.addEventListener( 'load', function () {
					bindFrame( f );
				} );
			}
		} );
	}
	if ( win.MutationObserver ) {
		var t = 0;
		new MutationObserver( function () {
			clearTimeout( t );
			t = setTimeout( scanFrames, 400 );
		} ).observe( doc.body, { childList: true, subtree: true } );
	}
	scanFrames();

	/* Profile screen: preview choices before saving --------------------- */

	var box = doc.getElementById( 'ecdm-settings' );
	if ( box ) {
		box.addEventListener( 'change', function ( e ) {
			var el = e.target;
			var key = el.name && el.name.replace( /^ecdm\[|\]$/g, '' );
			if ( ! key || ! ( key in engine.prefs ) ) {
				return;
			}
			var change = {};
			change[ key ] = el.type === 'checkbox' ? el.checked : el.value;
			engine.setPrefs( change );
			render();
		} );
	}

	doc.addEventListener( 'ecdm:change', render );
	render();
}( window, document ) );
