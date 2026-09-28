/* HypeIt — client-facing interactions (vanilla JS, no dependencies). */
( function () {
	'use strict';

	if ( typeof window.CP_FRONT === 'undefined' ) {
		return;
	}

	var cfg = window.CP_FRONT;

	function sprintf( str, value ) {
		return str.replace( '%d', value );
	}

	function setBadge( card, status ) {
		var badge = card.querySelector( '.cp-state-badge' );
		if ( ! badge ) {
			return;
		}
		if ( status === 'confirmed' ) {
			badge.textContent = 'Confirmed';
		} else if ( status === 'declined' ) {
			badge.textContent = 'Declined';
		} else {
			badge.textContent = '';
		}
	}

	function updateTotal( card ) {
		var totalEl = card.querySelector( '.cp-total' );
		if ( ! totalEl ) {
			return;
		}
		var guests = parseInt( card.getAttribute( 'data-guests' ), 10 ) || 0;
		if ( guests === 0 ) {
			totalEl.textContent = cfg.i18n.totalOne;
		} else {
			totalEl.textContent = sprintf( cfg.i18n.totalMany, guests + 1 );
		}
	}

	function feedback( card, message, type ) {
		var el = card.querySelector( '.cp-feedback' );
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.className = 'cp-feedback' + ( type ? ' is-' + type : '' );
	}

	function save( card, status, guests ) {
		var bloggerId = card.getAttribute( 'data-blogger-id' );

		feedback( card, cfg.i18n.saving, '' );

		var body = new FormData();
		body.append( 'action', 'cp_save_response' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'campaign_id', cfg.campaignId );
		body.append( 'blogger_id', bloggerId );
		body.append( 'status', status );
		body.append( 'extra_guests', guests );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					feedback( card, cfg.i18n.saved, 'saved' );
				} else {
					var msg = json && json.data && json.data.message ? json.data.message : cfg.i18n.error;
					feedback( card, msg, 'error' );
				}
			} )
			.catch( function () {
				feedback( card, cfg.i18n.error, 'error' );
			} );
	}

	function applyStatus( card, status ) {
		card.classList.remove( 'cp-status-confirmed', 'cp-status-declined', 'cp-status-pending' );
		card.classList.add( 'cp-status-' + status );

		var guestsWrap = card.querySelector( '.cp-guests' );

		if ( status === 'confirmed' ) {
			if ( guestsWrap ) {
				guestsWrap.hidden = false;
			}
			updateTotal( card );
		} else {
			if ( guestsWrap ) {
				guestsWrap.hidden = true;
			}
		}

		setBadge( card, status );
	}

	function setGuests( card, guests ) {
		card.setAttribute( 'data-guests', guests );
		var buttons = card.querySelectorAll( '.cp-guest' );
		buttons.forEach( function ( b ) {
			if ( parseInt( b.getAttribute( 'data-guests' ), 10 ) === guests ) {
				b.classList.add( 'is-active' );
			} else {
				b.classList.remove( 'is-active' );
			}
		} );
		updateTotal( card );
	}

	function initCard( card ) {
		// Initialise visible total for already-confirmed rows.
		if ( card.classList.contains( 'cp-status-confirmed' ) ) {
			updateTotal( card );
			setBadge( card, 'confirmed' );
		} else if ( card.classList.contains( 'cp-status-declined' ) ) {
			setBadge( card, 'declined' );
		}

		card.addEventListener( 'click', function ( e ) {
			var target = e.target;

			// Confirm / Decline.
			var action = target.getAttribute( 'data-action' );
			if ( action === 'confirm' ) {
				var guests = parseInt( card.getAttribute( 'data-guests' ), 10 ) || 0;
				applyStatus( card, 'confirmed' );
				save( card, 'confirmed', guests );
				return;
			}
			if ( action === 'decline' ) {
				applyStatus( card, 'declined' );
				save( card, 'declined', 0 );
				return;
			}

			// Guest selection.
			if ( target.classList.contains( 'cp-guest' ) ) {
				var g = parseInt( target.getAttribute( 'data-guests' ), 10 ) || 0;
				setGuests( card, g );
				// Selecting a guest count implies confirmation.
				if ( ! card.classList.contains( 'cp-status-confirmed' ) ) {
					applyStatus( card, 'confirmed' );
				}
				save( card, 'confirmed', g );
			}
		} );
	}

	function isMobileDevice() {
		return /android|iphone|ipad|ipod/i.test( navigator.userAgent || '' );
	}

	// Open the Instagram profile: on mobile, try the Instagram app first and
	// fall back to the web profile if the app is not installed. On desktop,
	// the normal link opens the web profile in a new tab.
	function openInstagram( e ) {
		var link = e.currentTarget;
		var user = link.getAttribute( 'data-username' );
		var webUrl = link.getAttribute( 'href' );

		if ( ! user || ! isMobileDevice() ) {
			return; // Desktop: let the anchor open the web profile in a new tab.
		}

		e.preventDefault();

		var appUrl = 'instagram://user?username=' + encodeURIComponent( user );
		var fallback = window.setTimeout( function () {
			window.location = webUrl;
		}, 1200 );

		var cancel = function () {
			window.clearTimeout( fallback );
		};

		// If the app opens, the page is backgrounded — cancel the web fallback.
		window.addEventListener( 'pagehide', cancel, { once: true } );
		document.addEventListener( 'visibilitychange', function onVis() {
			if ( document.hidden ) {
				cancel();
				document.removeEventListener( 'visibilitychange', onVis );
			}
		} );

		window.location = appUrl;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var cards = document.querySelectorAll( '.cp-blogger' );
		cards.forEach( initCard );

		var links = document.querySelectorAll( '.cp-account' );
		links.forEach( function ( a ) {
			a.addEventListener( 'click', openInstagram );
		} );
	} );
} )();

/* Tap a blogger photo to view it larger. */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '.cp-photo[data-full]' );
		if ( ! btn ) { return; }
		e.preventDefault();
		var row = btn.closest( '.cp-blogger' );
		var acc = row ? row.querySelector( '.cp-account' ) : null;
		var box = document.createElement( 'div' );
		box.className = 'cp-lightbox';
		box.setAttribute( 'role', 'dialog' );
		var img = document.createElement( 'img' );
		img.src = btn.getAttribute( 'data-full' );
		img.alt = '';
		box.appendChild( img );
		if ( acc ) { var cap = document.createElement( 'span' ); cap.textContent = acc.textContent.replace( '✓', '' ).trim(); box.appendChild( cap ); }
		var close = function () { box.remove(); document.removeEventListener( 'keydown', onKey ); };
		var onKey = function ( k ) { if ( k.key === 'Escape' ) { close(); } };
		box.addEventListener( 'click', close );
		document.addEventListener( 'keydown', onKey );
		document.body.appendChild( box );
	} );
} )();

/* Client review helper: progress, filters, "Next to review". */
( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		var box = document.getElementById( 'cp-review' );
		var list = document.querySelector( '.cp-list' );
		var next = document.getElementById( 'cp-next' );
		if ( ! box || ! list ) { return; }
		var cards = Array.prototype.slice.call( list.querySelectorAll( '.cp-blogger' ) );
		var total = cards.length;
		var tpl = box.querySelector( '.cp-review-filters' ).getAttribute( 'data-reviewed-tpl' );

		function statusOf( c ) {
			if ( c.classList.contains( 'cp-status-confirmed' ) ) { return 'confirmed'; }
			if ( c.classList.contains( 'cp-status-declined' ) ) { return 'declined'; }
			return 'pending';
		}

		// Page colours → the floating button matches light and dark sites.
		( function () {
			var node = list, bg = null;
			while ( node && node.nodeType === 1 && ! bg ) {
				var c = window.getComputedStyle( node ).backgroundColor;
				if ( c && c !== 'transparent' && ! /rgba\(.*,\s*0\)$/.test( c ) ) { bg = c; }
				node = node.parentElement;
			}
			var root = list.closest( '.cp-campaign' ) || document.body;
			root.style.setProperty( '--cp-page-bg', bg || '#ffffff' );
			root.style.setProperty( '--cp-page-fg', window.getComputedStyle( list ).color );
		} )();

		function paint() {
			var n = { pending: 0, confirmed: 0, declined: 0 };
			cards.forEach( function ( c ) { n[ statusOf( c ) ]++; } );
			var done = total - n.pending;
			box.querySelector( '.cp-review-count' ).textContent = tpl.replace( '%1$d', done ).replace( '%2$d', total );
			box.querySelector( '.cp-review-left' ).textContent = n.pending === 0 ? box.getAttribute( 'data-done' ) : ( n.pending === 1 ? box.getAttribute( 'data-left-one' ) : box.getAttribute( 'data-left-many' ).replace( '%d', n.pending ) );
			box.querySelector( '.cp-review-bar i' ).style.width = ( total ? Math.round( done / total * 100 ) : 0 ) + '%';
			[ 'pending', 'confirmed', 'declined' ].forEach( function ( k ) {
				var el = box.querySelector( '[data-n="' + k + '"]' );
				if ( el ) { el.textContent = n[ k ]; }
			} );
			if ( next ) {
				next.hidden = n.pending === 0 || n.pending === total;
			}
			box.classList.toggle( 'is-done', n.pending === 0 );
		}

		// Filters.
		Array.prototype.forEach.call( box.querySelectorAll( '.cp-review-filters button' ), function ( b ) {
			b.addEventListener( 'click', function () {
				Array.prototype.forEach.call( box.querySelectorAll( '.cp-review-filters button' ), function ( x ) { x.classList.toggle( 'is-active', x === b ); } );
				list.setAttribute( 'data-filter', b.getAttribute( 'data-f' ) );
				cards.forEach( function ( c ) { c.classList.remove( 'cp-keep' ); } );
				box.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );

		// React to choices: recount; in a filtered view, fade the answered card out gently.
		var obs = new MutationObserver( function ( muts ) {
			var changed = false;
			muts.forEach( function ( m ) {
				var c = m.target;
				var was = /cp-status-(\w+)/.exec( m.oldValue || '' );
				var now = statusOf( c );
				if ( ! was || was[ 1 ] === now ) { return; }
				changed = true;
				var f = list.getAttribute( 'data-filter' );
				if ( f !== 'all' && f !== now ) {
					c.classList.add( 'cp-keep' );
					window.setTimeout( function () { c.classList.add( 'cp-leaving' ); }, 900 );
					window.setTimeout( function () { c.classList.remove( 'cp-keep', 'cp-leaving' ); }, 1400 );
				}
			} );
			if ( changed ) { paint(); }
		} );
		cards.forEach( function ( c ) { obs.observe( c, { attributes: true, attributeFilter: [ 'class' ], attributeOldValue: true } ); } );

		// Next to review: the first unreviewed card below the current view (wraps to the top).
		if ( next ) {
			next.addEventListener( 'click', function () {
				var f = list.getAttribute( 'data-filter' );
				if ( f !== 'all' && f !== 'pending' ) {
					// Unreviewed bloggers are hidden by the current filter — switch to "To review".
					var tb = box.querySelector( '.cp-review-filters [data-f="pending"]' );
					Array.prototype.forEach.call( box.querySelectorAll( '.cp-review-filters button' ), function ( x ) { x.classList.toggle( 'is-active', x === tb ); } );
					list.setAttribute( 'data-filter', 'pending' );
				}
				var pend = cards.filter( function ( c ) { return statusOf( c ) === 'pending'; } );
				if ( ! pend.length ) { return; }
				var y = window.innerHeight * 0.35;
				var target = pend.filter( function ( c ) { return c.getBoundingClientRect().top > y; } )[ 0 ] || pend[ 0 ];
				target.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				target.classList.add( 'cp-flash' );
				window.setTimeout( function () { target.classList.remove( 'cp-flash' ); }, 1200 );
			} );
		}

		paint();
	} );
} )();
