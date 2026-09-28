/* Campaign — editor workspace (tabs, participants, add drawer, media, link, reset). */
( function () {
	'use strict';

	var L = window.CP_ADMIN || {};

	function $( id ) { return document.getElementById( id ); }
	function qsa( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function fmt( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return String( tpl || '' )
			.replace( /%(\d)\$d/g, function ( m, n ) { return args[ parseInt( n, 10 ) - 1 ]; } )
			.replace( /%d/g, function () { return args[ i++ ]; } );
	}
	function num( n ) { return Number( n || 0 ).toLocaleString(); }
	// Follower change at the last Instagram sync: green ↑ / red ↓ (none when unchanged).
	function trend( d ) {
		d = parseInt( d, 10 ) || 0;
		if ( ! d ) { return ''; }
		return ' <span class="cp-trend ' + ( d > 0 ? 'is-up' : 'is-down' ) + '" title="' + ( d > 0 ? '+' : '−' ) + num( Math.abs( d ) ) + '">' + ( d > 0 ? '↑' : '↓' ) + '</span>';
	}
	function hue( s ) { var h = 0; s = String( s || '' ); for ( var i = 0; i < s.length; i++ ) { h = ( h * 31 + s.charCodeAt( i ) ) % 360; } return h; }
	function avatar( b, h ) {
		if ( b && b.p ) { return '<img class="cpw-av" src="' + esc( b.p ) + '" alt="" loading="lazy" />'; }
		var src = ( b && b.n ) ? b.n : String( h || '' );
		var parts = src.trim().split( /\s+/ );
		var ini = ( ( parts[ 0 ] || '?' )[ 0 ] + ( parts.length > 1 ? parts[ parts.length - 1 ][ 0 ] : '' ) ).toUpperCase();
		return '<span class="cpw-av" style="background:hsl(' + hue( h ) + ' 38% 42%)">' + esc( ini ) + '</span>';
	}
	function key( h ) { return String( h || '' ).replace( /^@+/, '' ).toLowerCase(); }
	function cleanHandle( raw ) {
		var v = String( raw || '' ).trim();
		var m = v.match( /instagram\.com\/([A-Za-z0-9._]+)/i );
		if ( m ) { v = m[ 1 ]; }
		return v.replace( /^@+/, '' ).replace( /[^A-Za-z0-9._]/g, '' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initMedia();
		initCopy();
		initReset();
		initPassword();
		initAtrium();

		var dataEl = $( 'cpw-data' );
		if ( ! dataEl ) { return; }
		var DATA;
		try { DATA = JSON.parse( dataEl.textContent ); } catch ( e ) { return; }

		initTabs( DATA.postId );
		initWorkspace( DATA );
	} );

	/* ---------------------------------------------------------------- Tabs */
	function initTabs( postId ) {
		var storeKey = 'cpw-tab-' + postId;
		function activate( name ) {
			qsa( '.cpw-tab' ).forEach( function ( t ) { t.classList.toggle( 'is-active', t.getAttribute( 'data-tab' ) === name ); } );
			qsa( '.cpw-panel' ).forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === name ); } );
			try { sessionStorage.setItem( storeKey, name ); } catch ( e ) {}
		}
		qsa( '.cpw-tab' ).forEach( function ( t ) {
			t.addEventListener( 'click', function () { activate( t.getAttribute( 'data-tab' ) ); } );
		} );
		var saved = null;
		try { saved = sessionStorage.getItem( storeKey ); } catch ( e ) {}
		if ( saved && document.querySelector( '.cpw-tab[data-tab="' + saved + '"]' ) ) { activate( saved ); }
	}

	/* ---------------------------------------------------------- Workspace */
	function initWorkspace( DATA ) {
		var lib = {};
		( DATA.library || [] ).forEach( function ( b ) { lib[ key( b.h ) ] = b; } );
		var active = ( DATA.library || [] ).filter( function ( b ) { return ! b.x; } );

		// Current participants: {h, s, g, u, orig, removed}.
		var items = ( DATA.rows || [] ).map( function ( r ) {
			return { h: r.h, s: r.s || 'pending', g: r.g || 0, u: r.u || '', orig: true, removed: false };
		} );
		var ev = DATA.everyone || { on: false, started: false };

		var area    = $( 'cp_bloggers_bulk' );
		var tbody   = $( 'cpw-tbody' );
		var empty   = $( 'cpw-empty' );
		var q       = '';
		var sFilter = 'all';
		var sortBy  = 'order';
		var dirty   = false;
		var submitting = false;

		function index() {
			var m = {};
			items.forEach( function ( it, i ) { m[ key( it.h ) ] = i; } );
			return m;
		}
		function inCampaign( h ) {
			var i = index()[ key( h ) ];
			return i !== undefined && ! items[ i ].removed;
		}
		function addHandles( list ) {
			var idx = index(), added = 0;
			( list || [] ).forEach( function ( raw ) {
				var h = cleanHandle( raw );
				if ( ! h ) { return; }
				var i = idx[ key( h ) ];
				if ( i !== undefined ) {
					if ( items[ i ].removed ) { items[ i ].removed = false; added++; }
					return;
				}
				var b = lib[ key( h ) ];
				items.push( { h: b ? b.h : h, s: 'pending', g: 0, u: b ? b.u : '', orig: false, removed: false } );
				idx[ key( h ) ] = items.length - 1;
				added++;
			} );
			if ( added ) { changed(); }
			return added;
		}

		function changed() {
			dirty = true;
			area.value = items.filter( function ( it ) { return ! it.removed; } ).map( function ( it ) { return it.h; } ).join( '\n' );
			render();
			refreshLibrary();
			refreshFilters();
		}

		/* ---- Table ---- */
		function statusBadge( s ) {
			var map = { confirmed: L.confirmed, declined: L.declined, pending: L.pending };
			var k = map[ s ] ? s : 'pending';
			return '<span class="cp-badge cp-badge-' + k + '">' + esc( map[ k ] ) + '</span>';
		}
		function render() {
			var counts = { all: 0, confirmed: 0, declined: 0, pending: 0 };
			var addedN = 0, removedN = 0;
			items.forEach( function ( it ) {
				if ( ! it.orig && ! it.removed ) { addedN++; }
				if ( it.orig && it.removed ) { removedN++; }
				if ( it.removed ) { return; }
				counts.all++;
				counts[ counts[ it.s ] !== undefined ? it.s : 'pending' ]++;
			} );
			qsa( '[data-count]' ).forEach( function ( el ) { el.textContent = counts[ el.getAttribute( 'data-count' ) ]; } );
			var tc = $( 'cpw-tab-count' );
			if ( tc ) { tc.textContent = counts.all; }

			var html = '', n = 0;
			var order = items.map( function ( it, i ) { return i; } );
			if ( sortBy !== 'order' ) {
				var rankMap = { confirmed: 0, declined: 1, pending: 2 };
				var rank = function ( st ) { return rankMap[ st ] !== undefined ? rankMap[ st ] : 2; };
				var nm = function ( it ) { var b = lib[ key( it.h ) ]; return ( b && b.n ? b.n : it.h ).toLowerCase(); };
				var fol = function ( it ) { var b = lib[ key( it.h ) ]; return b ? b.f : 0; };
				var ppl = function ( it ) { return it.s === 'confirmed' ? 1 + ( it.g || 0 ) : 0; };
				var cty = function ( it ) { var b = lib[ key( it.h ) ]; return b && b.c ? b.c.toLowerCase() : '\uffff'; };
				order.sort( function ( a, b ) {
					var x = items[ a ], y = items[ b ], r = 0;
					if ( sortBy === 'status' ) { r = rank( x.s ) - rank( y.s ); }
					else if ( sortBy === 'status_rev' ) { r = rank( y.s ) - rank( x.s ); }
					else if ( sortBy === 'name' ) { r = nm( x ).localeCompare( nm( y ) ); }
					else if ( sortBy === 'followers_desc' ) { r = fol( y ) - fol( x ); }
					else if ( sortBy === 'followers_asc' ) { r = fol( x ) - fol( y ); }
					else if ( sortBy === 'people' ) { r = ppl( y ) - ppl( x ); }
					else if ( sortBy === 'city' ) { r = cty( x ).localeCompare( cty( y ) ); }
					return r || ( a - b );
				} );
			}
			order.forEach( function ( i ) {
				var it = items[ i ];
				var b = lib[ key( it.h ) ];
				if ( sFilter !== 'all' && ( it.removed || it.s !== sFilter ) ) { return; }
				if ( q ) {
					var hay = ( it.h + ' ' + ( b ? b.n + ' ' + b.c : '' ) ).toLowerCase();
					if ( hay.indexOf( q ) === -1 ) { return; }
				}
				n++;
				var url  = it.u || ( b && b.u ) || ( 'https://www.instagram.com/' + encodeURIComponent( it.h ) + '/' );
				var name = b && b.n ? '<span class="cpw-name">' + esc( b.n ) + ( b.v ? ' <span class="cpw-ver" title="Verified">✓</span>' : '' ) + '</span>' : '';
				var tags = '';
				if ( ! it.orig && ! it.removed ) { tags += '<span class="cpw-tag is-new">' + esc( L.isNew ) + '</span>'; }
				if ( it.removed ) { tags += '<span class="cpw-tag is-rm">' + esc( L.removedTag ) + '</span>'; }
				if ( b && b.x ) { tags += '<span class="cpw-tag is-rm">' + esc( L.blocked ) + '</span>'; }
				if ( ! b ) { tags += '<span class="cpw-tag">' + esc( L.notInLibrary ) + '</span>'; }
				var stg = DATA.stages && DATA.stages[ key( it.h ) ];
				if ( stg && DATA.stageLabels ) { tags += '<span class="cpa-stage cpa-' + esc( stg ) + '">' + esc( DATA.stageLabels[ stg ] ) + '</span>'; }
				var gender = b && b.g && DATA.genders && DATA.genders[ b.g ] ? DATA.genders[ b.g ] : '—';
				var people = it.s === 'confirmed' ? ( 1 + ( it.g || 0 ) ) + ( it.g ? ' <small>(' + esc( fmt( L.guestsFmt, it.g ) ) + ')</small>' : '' ) : '—';
				var action = it.removed
					? '<button type="button" class="button-link cpw-undo" data-i="' + i + '">' + esc( L.undo ) + '</button>'
					: '<button type="button" class="cpw-rm" data-i="' + i + '" aria-label="' + esc( L.remove ) + '" title="' + esc( L.remove ) + '">✕</button>';
				html += '<tr class="' + ( it.removed ? 'is-removed' : '' ) + ( ! it.orig ? ' is-new' : '' ) + '">' +
					'<td class="cpw-c-idx" data-label="#">' + n + '</td>' +
					'<td data-label=""><div class="cpw-who">' + avatar( b, it.h ) + '<div>' + name + '<a href="' + esc( url ) + '" target="_blank" rel="noopener" class="cpw-handle">@' + esc( it.h ) + '</a>' + tags + '</div></div></td>' +
					'<td data-label="' + esc( L.colFollowers ) + '">' + ( b && b.f ? num( b.f ) + trend( b.d ) : '—' ) + '</td>' +
					'<td data-label="' + esc( L.colGender ) + '">' + esc( gender ) + '</td>' +
					'<td data-label="' + esc( L.colCity ) + '">' + esc( b && b.c ? b.c : '—' ) + '</td>' +
					'<td data-label="' + esc( L.colStatus ) + '">' + ( ! it.orig ? '<span class="cpw-muted">—</span>' : statusBadge( it.s ) ) + '</td>' +
					'<td data-label="' + esc( L.colPeople ) + '">' + people + '</td>' +
					'<td class="cpw-c-act">' + action + '</td></tr>';
			} );
			tbody.innerHTML = html;
			if ( empty ) {
				empty.hidden = n > 0;
				empty.textContent = counts.all ? L.noRows : empty.getAttribute( 'data-default' ) || empty.textContent;
			}

			var bar = $( 'cpw-changes' );
			if ( bar ) {
				bar.hidden = ! ( addedN || removedN );
				$( 'cpw-changes-text' ).textContent = fmt( L.changes, addedN, removedN );
			}
		}
		if ( empty ) { empty.setAttribute( 'data-default', empty.textContent ); }

		tbody.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.cpw-rm' );
			var un = e.target.closest( '.cpw-undo' );
			if ( rm ) {
				var it = items[ parseInt( rm.getAttribute( 'data-i' ), 10 ) ];
				if ( ! it ) { return; }
				if ( ! it.orig ) {
					items.splice( items.indexOf( it ), 1 );
				} else {
					if ( it.s !== 'pending' && ! window.confirm( L.confirmRm ) ) { return; }
					it.removed = true;
				}
				changed();
			} else if ( un ) {
				var it2 = items[ parseInt( un.getAttribute( 'data-i' ), 10 ) ];
				if ( it2 ) { it2.removed = false; changed(); }
			}
		} );

		var undoAll = $( 'cpw-undo-all' );
		if ( undoAll ) {
			undoAll.addEventListener( 'click', function () {
				items = items.filter( function ( it ) { return it.orig; } );
				items.forEach( function ( it ) { it.removed = false; } );
				changed();
				dirty = false;
			} );
		}

		var sortSel = $( 'cpw-sort' );
		if ( sortSel ) {
			try { sortBy = sessionStorage.getItem( 'cpw-sort' ) || 'order'; } catch ( e ) {}
			sortSel.value = sortBy;
			if ( sortSel.value !== sortBy ) { sortBy = 'order'; sortSel.value = 'order'; }
			sortSel.addEventListener( 'change', function () {
				sortBy = sortSel.value;
				try { sessionStorage.setItem( 'cpw-sort', sortBy ); } catch ( e ) {}
				render();
			} );
		}

		var fq = $( 'cpw-filter-q' );
		if ( fq ) { fq.addEventListener( 'input', function () { q = fq.value.trim().toLowerCase(); render(); } ); }
		qsa( '#cpw-filter-status button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				sFilter = b.getAttribute( 'data-status' );
				qsa( '#cpw-filter-status button' ).forEach( function ( x ) { x.classList.toggle( 'is-active', x === b ); } );
				render();
			} );
		} );

		/* ---- Everyone ---- */
		var evToggle = $( 'cpw-ev-toggle' );
		var evBox    = $( 'cpw-everyone' );
		function evState() {
			var on = evToggle && evToggle.checked;
			var txt = on ? ( ev.started ? L.evPaused : L.evActive ) : L.evOff;
			$( 'cpw-ev-state' ).textContent = txt;
			evBox.classList.toggle( 'is-on', !! on );
		}
		if ( evToggle ) {
			var evAdded = {};
			evToggle.addEventListener( 'change', function () {
				if ( evToggle.checked && ! ev.started ) {
					// Preview what saving will do: Everyone (before selection) = all active bloggers.
					var before = index();
					addHandles( active.map( function ( b ) { return b.h; } ) );
					items.forEach( function ( it ) { if ( before[ key( it.h ) ] === undefined ) { evAdded[ key( it.h ) ] = true; } } );
				} else if ( ! evToggle.checked ) {
					// Turning it back off drops the preview additions (never saved bloggers).
					var had = items.length;
					items = items.filter( function ( it ) { return it.orig || ! evAdded[ key( it.h ) ]; } );
					evAdded = {};
					if ( items.length !== had ) { changed(); }
				}
				evState();
			} );
			evState();
		}
		var addAll = $( 'cpw-add-all' );
		if ( addAll ) {
			addAll.addEventListener( 'click', function () {
				var missing = active.filter( function ( b ) { return ! inCampaign( b.h ); } );
				if ( ! missing.length ) { window.alert( L.noneNew ); return; }
				if ( ! window.confirm( fmt( L.allConfirm, missing.length ) ) ) { return; }
				addHandles( missing.map( function ( b ) { return b.h; } ) );
			} );
		}

		/* ---- Drawer ---- */
		var drawer = $( 'cpw-drawer' );
		var toggle = $( 'cpw-add-toggle' );
		if ( toggle && drawer ) {
			toggle.addEventListener( 'click', function () {
				drawer.hidden = ! drawer.hidden;
				toggle.setAttribute( 'aria-expanded', drawer.hidden ? 'false' : 'true' );
				if ( ! drawer.hidden ) { refreshLibrary(); var s = $( 'cpw-lib-q' ); if ( s ) { s.focus(); } }
			} );
		}
		qsa( '.cpw-subtabs button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var name = b.getAttribute( 'data-sub' );
				qsa( '.cpw-subtabs button' ).forEach( function ( x ) { x.classList.toggle( 'is-active', x === b ); } );
				qsa( '.cpw-sub' ).forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-subpanel' ) === name ); } );
				if ( name === 'filters' ) { refreshFilters(); }
			} );
		} );

		/* Search library */
		var libQ = $( 'cpw-lib-q' ), libBox = $( 'cpw-lib-results' ), libAdd = $( 'cpw-lib-add' ), libNote = $( 'cpw-lib-note' );
		var picked = {}, shown = [];
		function refreshLibrary() {
			if ( ! libBox ) { return; }
			var term = libQ ? libQ.value.trim().toLowerCase().replace( /^@/, '' ) : '';
			var avail = active.filter( function ( b ) { return ! inCampaign( b.h ); } );
			var match = avail.filter( function ( b ) {
				return ! term || ( b.h + ' ' + b.n + ' ' + b.c ).toLowerCase().indexOf( term ) !== -1;
			} );
			shown = match.slice( 0, 60 );
			Object.keys( picked ).forEach( function ( k ) { if ( inCampaign( k ) ) { delete picked[ k ]; } } );
			if ( ! shown.length ) {
				libBox.innerHTML = '<p class="cpw-muted cpw-pad">' + esc( L.libEmpty ) + '</p>';
			} else {
				libBox.innerHTML = shown.map( function ( b ) {
					var meta = [ '@' + b.h ];
					if ( b.f ) { meta.push( num( b.f ) ); }
					if ( b.c ) { meta.push( b.c ); }
					return '<label class="cpw-pick"><input type="checkbox" value="' + esc( b.h ) + '"' + ( picked[ key( b.h ) ] ? ' checked' : '' ) + ' />' + avatar( b, b.h ) +
						'<span><b>' + esc( b.n || '@' + b.h ) + ( b.v ? ' <span class="cpw-ver">✓</span>' : '' ) + '</b><small>' + esc( meta.join( ' · ' ) ) + '</small></span></label>';
				} ).join( '' );
			}
			var sel = Object.keys( picked ).length;
			if ( libNote ) { libNote.textContent = fmt( L.libShown, shown.length, match.length, sel ); }
			if ( libAdd ) { libAdd.disabled = ! sel; libAdd.textContent = fmt( L.addSelected, sel ); }
		}
		if ( libBox ) {
			libBox.addEventListener( 'change', function ( e ) {
				var cb = e.target;
				if ( cb.type !== 'checkbox' ) { return; }
				if ( cb.checked ) { picked[ key( cb.value ) ] = cb.value; } else { delete picked[ key( cb.value ) ]; }
				refreshLibrary();
			} );
		}
		if ( libQ ) { libQ.addEventListener( 'input', refreshLibrary ); }
		var selAll = $( 'cpw-lib-selall' );
		if ( selAll ) { selAll.addEventListener( 'click', function () { shown.forEach( function ( b ) { picked[ key( b.h ) ] = b.h; } ); refreshLibrary(); } ); }
		if ( libAdd ) {
			libAdd.addEventListener( 'click', function () {
				var hs = Object.keys( picked ).map( function ( k ) { return picked[ k ]; } );
				picked = {};
				addHandles( hs );
			} );
		}

		/* Filters */
		var fltNote = $( 'cpw-flt-note' ), fltAdd = $( 'cpw-flt-add' ), fltPending = [];
		function fval( name ) { var el = document.querySelector( '.cpw-f[data-f="' + name + '"]' ); return el ? el.value : ''; }
		function fchecked( name ) { return qsa( '.cpw-f[data-f="' + name + '"]:checked' ).map( function ( c ) { return parseInt( c.value, 10 ); } ); }
		function refreshFilters() {
			if ( ! fltNote ) { return; }
			var lists = fchecked( 'list' ), cats = fchecked( 'cat' ), catAll = fval( 'catmatch' ) === 'all';
			var gender = fval( 'gender' ), city = fval( 'city' ), collab = fval( 'collab' );
			var fmin = fval( 'fmin' ), fmax = fval( 'fmax' );
			var vOnly = qsa( '.cpw-f[data-f="verified"]:checked' ).length > 0;
			var any = lists.length || cats.length || gender || city || collab || fmin !== '' || fmax !== '' || vOnly;
			if ( ! any ) {
				fltNote.textContent = '';
				fltAdd.disabled = true;
				fltAdd.textContent = fmt( L.fltAdd, 0 );
				fltPending = [];
				return;
			}
			var match = active.filter( function ( b ) {
				if ( lists.length && ! lists.some( function ( id ) { return b.l.indexOf( id ) !== -1; } ) ) { return false; }
				if ( cats.length ) {
					var hit = cats.filter( function ( id ) { return b.t.indexOf( id ) !== -1; } ).length;
					if ( catAll ? hit !== cats.length : ! hit ) { return false; }
				}
				if ( gender && b.g !== gender ) { return false; }
				if ( city && b.c !== city ) { return false; }
				if ( collab && ( b.o || '' ).indexOf( ',' + collab + ',' ) === -1 ) { return false; }
				if ( fmin !== '' && b.f < parseInt( fmin, 10 ) ) { return false; }
				if ( fmax !== '' && b.f > parseInt( fmax, 10 ) ) { return false; }
				if ( vOnly && ! b.v ) { return false; }
				return true;
			} );
			fltPending = match.filter( function ( b ) { return ! inCampaign( b.h ); } );
			fltNote.textContent = match.length ? fmt( L.fltNote, match.length, fltPending.length ) : L.noMatches;
			fltAdd.disabled = ! fltPending.length;
			fltAdd.textContent = fmt( L.fltAdd, fltPending.length );
		}
		qsa( '.cpw-f' ).forEach( function ( el ) {
			el.addEventListener( 'change', refreshFilters );
			el.addEventListener( 'input', refreshFilters );
		} );
		if ( fltAdd ) { fltAdd.addEventListener( 'click', function () { addHandles( fltPending.map( function ( b ) { return b.h; } ) ); } ); }

		/* Paste */
		var pasteBtn = $( 'cpw-paste-add' ), pasteArea = $( 'cpw-paste' );
		if ( pasteBtn && pasteArea ) {
			pasteBtn.addEventListener( 'click', function () {
				var parts = pasteArea.value.split( /[\r\n,]+/ ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
				var n = addHandles( parts );
				pasteArea.value = '';
				window.alert( n ? fmt( L.added, n ) : L.noneNew );
			} );
		}

		/* Import */
		var impBtn = $( 'cpw-import-btn' );
		if ( impBtn ) {
			impBtn.addEventListener( 'click', function () {
				var src = $( 'cpw-import-src' ), note = $( 'cpw-import-note' );
				if ( ! src.value ) { note.textContent = L.importSelect; return; }
				impBtn.disabled = true;
				note.textContent = '…';
				var body = new FormData();
				body.append( 'action', 'cp_import_bloggers' );
				body.append( 'nonce', impBtn.getAttribute( 'data-nonce' ) );
				body.append( 'campaign_id', src.value );
				if ( $( 'cpw-import-confirmed' ) && $( 'cpw-import-confirmed' ).checked ) { body.append( 'confirmed_only', '1' ); }
				fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( json ) {
						impBtn.disabled = false;
						if ( ! json || ! json.success ) { note.textContent = ( json && json.data && json.data.message ) || L.importError; return; }
						var n = addHandles( json.data.accounts || [] );
						note.textContent = n ? fmt( L.added, n ) : L.noneNew;
					} )
					.catch( function () { impBtn.disabled = false; note.textContent = L.importError; } );
			} );
		}

		/* Enter inside our inputs must not submit the post form. */
		qsa( '#cpw input' ).forEach( function ( el ) {
			el.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); } } );
		} );

		/* Unsaved-changes guard. */
		var form = $( 'post' );
		if ( form ) { form.addEventListener( 'submit', function () { submitting = true; } ); }
		window.addEventListener( 'beforeunload', function ( e ) {
			var pending = items.some( function ( it ) { return ( ! it.orig && ! it.removed ) || ( it.orig && it.removed ); } );
			if ( dirty && pending && ! submitting ) { e.preventDefault(); e.returnValue = L.leave; return L.leave; }
		} );

		render();
		refreshLibrary();
	}

	/* ---------------------------------------------------------- Event (ATRIUM) */
	function initAtrium() {
		var box = $( 'cpa' );
		if ( ! box ) { return; }
		box.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-cpa]' );
			if ( ! btn ) { return; }
			var op = btn.getAttribute( 'data-cpa' );
			var body = new FormData();
			body.append( 'action', 'cp_atrium' );
			body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
			body.append( 'campaign', box.getAttribute( 'data-campaign' ) );
			body.append( 'op', op );
			if ( op === 'link' ) {
				var ev = $( 'cpa-event' );
				if ( ! ev || ! ev.value ) { return; }
				body.append( 'event', ev.value );
			}
			if ( op === 'create' && $( 'cpa-perguest' ) && $( 'cpa-perguest' ).checked ) { body.append( 'per_guest', '1' ); }
			if ( op === 'unlink' && ! window.confirm( L.atUnlinkQ ) ) { return; }
			if ( op === 'send' ) {
				if ( $( 'cpa-vdate' ) && $( 'cpa-vdate' ).value ) { body.append( 'visit_date', $( 'cpa-vdate' ).value ); }
				if ( $( 'cpa-vtime' ) && $( 'cpa-vtime' ).value ) { body.append( 'visit_time', $( 'cpa-vtime' ).value ); }
				if ( $( 'cpa-prune' ) && $( 'cpa-prune' ).checked ) { body.append( 'prune', '1' ); }
			}
			if ( op === 'message' ) { body.append( 'message', ( $( 'cpa-msg' ) || {} ).value || '' ); }
			btn.disabled = true;
			var was = btn.textContent;
			btn.textContent = '…';
			fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( j ) {
					btn.disabled = false; btn.textContent = was;
					if ( ! j || ! j.success ) { window.alert( ( j && j.data && j.data.message ) || L.atError ); return; }
					if ( op === 'message' ) { btn.textContent = '✓'; setTimeout( function () { btn.textContent = was; }, 1500 ); return; }
					try { sessionStorage.setItem( 'cpw-tab-' + box.getAttribute( 'data-campaign' ), 'event' ); sessionStorage.setItem( 'cpa-notice', j.data.notice || '' ); } catch ( err ) {}
					window.location.reload();
				} )
				.catch( function () { btn.disabled = false; btn.textContent = was; window.alert( L.atError ); } );
		} );
		var n = '';
		try { n = sessionStorage.getItem( 'cpa-notice' ) || ''; sessionStorage.removeItem( 'cpa-notice' ); } catch ( err ) {}
		if ( n ) {
			var d = document.createElement( 'div' );
			d.className = 'cpw-note';
			d.style.marginBottom = '14px';
			d.textContent = n;
			box.insertBefore( d, box.firstChild );
		}
	}

	/* ---------------------------------------------------------- Media */
	function initMedia() {
		qsa( '.cp-media-select' ).forEach( function ( btn ) {
			var input = $( btn.getAttribute( 'data-target' ) );
			var preview = $( btn.getAttribute( 'data-preview' ) );
			var frame;
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! window.wp || ! window.wp.media ) { return; }
				if ( frame ) { frame.open(); return; }
				frame = window.wp.media( { title: L.chooseLogo, button: { text: L.useImage }, multiple: false } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					if ( input ) { input.value = att.id; }
					if ( preview ) {
						var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
						preview.innerHTML = '<img src="' + esc( url ) + '" alt="" />';
					}
					var rm = document.querySelector( '.cp-media-remove[data-target="' + btn.getAttribute( 'data-target' ) + '"]' );
					if ( rm ) { rm.style.display = ''; }
				} );
				frame.open();
			} );
		} );
		qsa( '.cp-media-remove' ).forEach( function ( rm ) {
			rm.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var input = $( rm.getAttribute( 'data-target' ) );
				var preview = $( rm.getAttribute( 'data-preview' ) );
				if ( input ) { input.value = ''; }
				if ( preview ) { preview.innerHTML = '<span class="cpw-muted">' + esc( L.noImage ) + '</span>'; }
				rm.style.display = 'none';
			} );
		} );
	}

	/* ---------------------------------------------------------- Copy link */
	function initCopy() {
		qsa( '.cp-copy-link' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var link = btn.getAttribute( 'data-link' );
				var original = btn.textContent;
				var done = function () { btn.textContent = L.copied; setTimeout( function () { btn.textContent = original; }, 1500 ); };
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( link ).then( done ).catch( done );
				} else {
					var tmp = document.createElement( 'textarea' );
					tmp.value = link; document.body.appendChild( tmp ); tmp.select();
					try { document.execCommand( 'copy' ); } catch ( err ) {}
					document.body.removeChild( tmp ); done();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------- Reset */
	function initReset() {
		var btn = document.querySelector( '.cp-admin-reset' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( L.resetConfirm ) ) { return; }
			btn.disabled = true;
			var body = new FormData();
			body.append( 'action', 'cp_admin_reset' );
			body.append( 'nonce', btn.getAttribute( 'data-nonce' ) );
			body.append( 'campaign_id', btn.getAttribute( 'data-campaign' ) );
			fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( json && json.success ) { window.location.reload(); return; }
					btn.disabled = false;
					window.alert( ( json && json.data && json.data.message ) || L.resetError );
				} )
				.catch( function () { btn.disabled = false; window.alert( L.resetError ); } );
		} );
	}

	/* ---------------------------------------------------------- Password */
	function initPassword() {
		var gen = $( 'cpw-genpw' ), input = $( 'cp_password' );
		if ( ! gen || ! input ) { return; }
		gen.addEventListener( 'click', function () {
			var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '';
			var rnd = new Uint32Array( 10 );
			( window.crypto || window.msCrypto ).getRandomValues( rnd );
			for ( var i = 0; i < 10; i++ ) { out += chars[ rnd[ i ] % chars.length ]; }
			input.value = out;
			input.focus();
			input.select();
		} );
	}
} )();
