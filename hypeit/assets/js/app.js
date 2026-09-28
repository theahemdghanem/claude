/* Campaign app (PWA). Hash-routed single page app talking to /wp-json/campaign/v1. */
( function () {
	'use strict';

	var C = window.CPA || {};
	var I = C.i18n || {};
	var TOKEN = 'cp_app_token';
	var THEME = 'cp_app_theme';

	/* ================================================================ Utils */

	function fmt( s ) {
		var a = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return String( s == null ? '' : s )
			.replace( /%(\d)\$[sd]/g, function ( m, n ) { return a[ n - 1 ]; } )
			.replace( /%[sd]/g, function () { return a[ i++ ]; } );
	}
	function t( k ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return fmt.apply( null, [ I[ k ] != null ? I[ k ] : k ].concat( args ) );
	}
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function $( id ) { return document.getElementById( id ); }
	function qs( sel, root ) { return ( root || document ).querySelector( sel ); }
	function qsa( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }
	function num( n ) { return Number( n || 0 ).toLocaleString(); }
	var compactFmt = null;
	try { compactFmt = new Intl.NumberFormat( undefined, { notation: 'compact', maximumFractionDigits: 1 } ); } catch ( e ) {}
	function compact( n ) { n = Number( n || 0 ); return compactFmt ? compactFmt.format( n ) : num( n ); }
	function lc( s ) { return String( s || '' ).toLowerCase(); }
	function debounce( fn, ms ) { var tm; return function () { var a = arguments, s = this; clearTimeout( tm ); tm = setTimeout( function () { fn.apply( s, a ); }, ms ); }; }
	function initials( name, handle ) {
		var src = String( name || '' ).trim();
		if ( src ) {
			var p = src.split( /\s+/ );
			return ( p[ 0 ][ 0 ] + ( p.length > 1 ? p[ p.length - 1 ][ 0 ] : '' ) ).toUpperCase();
		}
		return String( handle || '?' ).replace( /[^a-z0-9]/gi, '' ).slice( 0, 2 ).toUpperCase() || '?';
	}
	function hue( s ) { var h = 0; s = String( s || '' ); for ( var i = 0; i < s.length; i++ ) { h = ( h * 31 + s.charCodeAt( i ) ) % 360; } return h; }
	function avatar( name, handle, big, photo ) {
		if ( photo ) {
			return '<img class="avatar' + ( big ? ' lg' : '' ) + '" src="' + esc( photo ) + '" alt="" loading="lazy" />';
		}
		return '<span class="avatar' + ( big ? ' lg' : '' ) + '" style="background:hsl(' + hue( handle || name ) + ' 38% 36%)">' + esc( initials( name, handle ) ) + '</span>';
	}
	function igUrl( h ) { return 'https://www.instagram.com/' + encodeURIComponent( String( h || '' ).replace( /^@/, '' ) ) + '/'; }
	function genPassword() {
		var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '';
		var rnd = new Uint32Array( 10 );
		( window.crypto || window.msCrypto ).getRandomValues( rnd );
		for ( var i = 0; i < rnd.length; i++ ) { out += chars[ rnd[ i ] % chars.length ]; }
		return out;
	}
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text ).catch( fallback );
		}
		return Promise.resolve( fallback() );
		function fallback() {
			var ta = document.createElement( 'textarea' );
			ta.value = text; ta.setAttribute( 'readonly', '' ); ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild( ta ); ta.select();
			try { document.execCommand( 'copy' ); } catch ( e ) {}
			document.body.removeChild( ta );
		}
	}
	function shrinkImage( file, max ) {
		max = max || 1200;
		return new Promise( function ( resolve ) {
			var url = URL.createObjectURL( file ), img = new Image();
			img.onload = function () {
				var sc = Math.min( 1, max / Math.max( img.naturalWidth, img.naturalHeight ) );
				var cv = document.createElement( 'canvas' );
				cv.width = Math.round( img.naturalWidth * sc ); cv.height = Math.round( img.naturalHeight * sc );
				cv.getContext( '2d' ).drawImage( img, 0, 0, cv.width, cv.height );
				URL.revokeObjectURL( url );
				cv.toBlob( function ( b ) { resolve( b || file ); }, 'image/jpeg', 0.88 );
			};
			img.onerror = function () { URL.revokeObjectURL( url ); resolve( file ); };
			img.src = url;
		} );
	}
	function sget( k, d ) { try { var v = sessionStorage.getItem( 'cpa:' + k ); return v == null ? d : v; } catch ( e ) { return d; } }
	function sset( k, v ) { try { sessionStorage.setItem( 'cpa:' + k, v ); } catch ( e ) {} }

	/* ================================================================ Icons */

	var P = {
		campaigns: '<path d="M4 5h16v14H4z"/><path d="M4 9h16M9 9v10"/>',
		bloggers: '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3.2 2.9-5 5.5-5s4.9 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M16.5 14c2.2.2 3.7 1.7 4 4.5"/>',
		lists: '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2"/><circle cx="4.5" cy="12" r="1.2"/><circle cx="4.5" cy="18" r="1.2"/>',
		insights: '<path d="M5 19V11M12 19V5M19 19v-5"/>',
		more: '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		search: '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4-4"/>',
		filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
		sort: '<path d="M7 4v16M3.5 16.5 7 20l3.5-3.5M17 20V4M13.5 7.5 17 4l3.5 3.5"/>',
		link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		share: '<path d="M12 15V4M8 8l4-4 4 4"/><path d="M5 12v7h14v-7"/>',
		open: '<path d="M14 5h5v5M19 5l-8 8"/><path d="M18 14v5H5V6h5"/>',
		download: '<path d="M12 4v11M8 11l4 4 4-4"/><path d="M5 19h14"/>',
		edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
		trash: '<path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
		block: '<circle cx="12" cy="12" r="8"/><path d="M6.5 6.5l11 11"/>',
		unblock: '<circle cx="12" cy="12" r="8"/><path d="M8.5 12.5l2.3 2.3 4.7-5"/>',
		reset: '<path d="M4 12a8 8 0 1 0 2.4-5.7"/><path d="M4 4v5h5"/>',
		draft: '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/>',
		publish: '<path d="M5 12l4.5 4.5L19 7"/>',
		ig: '<rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17" cy="7" r=".9"/>',
		chat: '<path d="M5 18.5 4 21l3-1.2A8 8 0 1 0 4.2 15"/>',
		phone: '<path d="M6 3h3l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v3a3 3 0 0 1-3 3A15 15 0 0 1 3 6a3 3 0 0 1 3-3z"/>',
		user: '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5"/>',
		refresh: '<path d="M20 12a8 8 0 1 1-2.4-5.7"/><path d="M20 4v5h-5"/>',
		remove: '<path d="M6 6l12 12M18 6 6 18"/>',
		inbox: '<path d="M4 13h4l2 3h4l2-3h4"/><path d="M5 5h14l1 8v6H4v-6z"/>',
		wifi: '<path d="M3 9a14 14 0 0 1 18 0M6 12.5a9 9 0 0 1 12 0M9 16a4 4 0 0 1 6 0"/><circle cx="12" cy="19" r="1"/>'
	};
	function icon( name ) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ( P[ name ] || '' ) + '</svg>';
	}

	/* ================================================================ Auth + API */

	function getToken() { try { return localStorage.getItem( TOKEN ) || sessionStorage.getItem( TOKEN ) || ''; } catch ( e ) { return ''; } }
	function setToken( tok, remember ) { clearToken(); try { ( remember ? localStorage : sessionStorage ).setItem( TOKEN, tok ); } catch ( e ) {} }
	function clearToken() { try { localStorage.removeItem( TOKEN ); sessionStorage.removeItem( TOKEN ); } catch ( e ) {} }

	function api( path, opts ) {
		opts = opts || {};
		opts.headers = Object.assign( { 'X-CP-Token': getToken(), Accept: 'application/json' }, opts.headers || {} );
		return fetch( C.api + path, opts ).then( function ( res ) {
			if ( res.status === 401 || res.status === 403 ) { throw { code: getToken() ? 'expired' : 'auth' }; }
			return res.json().catch( function () { return null; } ).then( function ( data ) {
				if ( ! res.ok ) { throw { code: 'http', data: data, message: ( data && data.message ) || t( 'offline' ) }; }
				return data;
			} );
		}, function () { throw { code: 'offline', message: t( 'offline' ) }; } );
	}
	function post( path, body ) {
		return api( path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( body || {} ) } );
	}

	/* ================================================================ Store */

	var S = { campaigns: null, camp: {}, library: null, meta: null, lists: null, insights: null, user: null };
	function drop() {
		for ( var i = 0; i < arguments.length; i++ ) {
			var k = arguments[ i ];
			if ( k === 'camp' ) { S.camp = {}; } else { S[ k ] = null; }
		}
	}
	function getCampaigns( force ) {
		if ( S.campaigns && ! force ) { return Promise.resolve( S.campaigns ); }
		return api( '/campaigns' ).then( function ( d ) { S.campaigns = d.campaigns || []; return S.campaigns; } );
	}
	function getCampaign( id, force ) {
		if ( S.camp[ id ] && ! force ) { return Promise.resolve( S.camp[ id ] ); }
		return api( '/campaigns/' + id ).then( function ( c ) { S.camp[ id ] = c; return c; } );
	}
	function getLibrary( force ) {
		if ( S.library && ! force ) { return Promise.resolve( S.library ); }
		return api( '/bloggers' ).then( function ( d ) { S.library = d.bloggers || []; return S.library; } );
	}
	function getMeta( force ) {
		if ( S.meta && ! force ) { return Promise.resolve( S.meta ); }
		return api( '/meta' ).then( function ( m ) { S.meta = m; return m; } );
	}
	function getLists( force ) {
		if ( S.lists && ! force ) { return Promise.resolve( S.lists ); }
		return api( '/lists' ).then( function ( d ) { S.lists = d.lists || []; return S.lists; } );
	}
	function libByHandle( lib ) { var m = {}; ( lib || [] ).forEach( function ( b ) { m[ lc( b.handle ) ] = b; } ); return m; }

	/* ================================================================ UI: toast, sheet */

	function toast( msg, isErr ) {
		if ( ! msg ) { return; }
		var el = document.createElement( 'div' );
		el.className = 'toast' + ( isErr ? ' err' : '' );
		el.textContent = msg;
		$( 'toast-root' ).appendChild( el );
		requestAnimationFrame( function () { el.classList.add( 'is-in' ); } );
		setTimeout( function () { el.classList.remove( 'is-in' ); setTimeout( function () { el.remove(); }, 250 ); }, isErr ? 4200 : 2600 );
	}

	var openSheets = [];
	function sheet( o ) {
		var root = $( 'sheet-root' );
		var bd = document.createElement( 'div' );
		bd.className = 'sheet-backdrop';
		var sh = document.createElement( 'div' );
		sh.className = 'sheet';
		sh.setAttribute( 'role', 'dialog' );
		var html = '<div class="sheet-grip"></div>';
		if ( o.title ) { html += '<h3>' + esc( o.title ) + '</h3>'; }
		if ( o.message ) { html += '<p class="sheet-msg">' + esc( o.message ) + '</p>'; }
		if ( o.html ) { html += o.html; }
		if ( o.actions ) {
			html += '<div class="sheet-list">' + o.actions.map( function ( a, i ) {
				return '<button type="button" class="action' + ( a.danger ? ' danger' : '' ) + '" data-i="' + i + '">' + ( a.icon ? icon( a.icon ) : '' ) + '<span>' + esc( a.label ) + '</span>' + ( a.checked ? '<span style="margin-left:auto">✓</span>' : '' ) + '</button>';
			} ).join( '' ) + '</div>';
		}
		sh.innerHTML = html;
		root.appendChild( bd );
		root.appendChild( sh );
		requestAnimationFrame( function () { bd.classList.add( 'is-open' ); sh.classList.add( 'is-open' ); } );
		var closed = false;
		var api2 = {
			el: sh,
			close: function () {
				if ( closed ) { return; }
				closed = true;
				bd.classList.remove( 'is-open' ); sh.classList.remove( 'is-open' );
				openSheets = openSheets.filter( function ( x ) { return x !== api2; } );
				setTimeout( function () { bd.remove(); sh.remove(); }, 260 );
				if ( o.onClose ) { o.onClose(); }
			}
		};
		bd.addEventListener( 'click', api2.close );
		if ( o.actions ) {
			qsa( '.action', sh ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var a = o.actions[ parseInt( b.getAttribute( 'data-i' ), 10 ) ];
					api2.close();
					if ( a && a.onClick ) { a.onClick(); }
				} );
			} );
		}
		openSheets.push( api2 );
		if ( o.onOpen ) { o.onOpen( sh, api2 ); }
		return api2;
	}
	function closeSheets() { openSheets.slice().forEach( function ( s ) { s.close(); } ); }
	function confirmSheet( message, okLabel, danger ) {
		return new Promise( function ( resolve ) {
			var done = false;
			var s = sheet( {
				message: message,
				html: '<div class="sheet-actions"><button type="button" class="btn ' + ( danger ? 'btn-danger' : '' ) + '" data-ok>' + esc( okLabel || t( 'confirm' ) ) + '</button><button type="button" class="btn btn-ghost" data-no>' + esc( t( 'cancel' ) ) + '</button></div>',
				onClose: function () { if ( ! done ) { done = true; resolve( false ); } }
			} );
			qs( '[data-ok]', s.el ).addEventListener( 'click', function () { done = true; s.close(); resolve( true ); } );
			qs( '[data-no]', s.el ).addEventListener( 'click', function () { s.close(); } );
		} );
	}
	function choiceSheet( title, options, current, onPick ) {
		sheet( {
			title: title,
			actions: options.map( function ( o ) { return { label: o[ 1 ], checked: o[ 0 ] === current, onClick: function () { onPick( o[ 0 ] ); } }; } )
		} );
	}

	/* ================================================================ Frame */

	var bar, barTitle, barActions, barBack, view, tabbar, appEl;
	var TABS = [ [ 'campaigns', '/campaigns' ], [ 'bloggers', '/bloggers' ], [ 'lists', '/lists' ], [ 'insights', '/insights' ], [ 'more', '/more' ] ];

	function buildFrame() {
		appEl = $( 'app' ); bar = $( 'bar' ); barTitle = $( 'bar-title' ); barActions = $( 'bar-actions' );
		barBack = $( 'bar-back' ); view = $( 'view' ); tabbar = $( 'tabbar' );
		tabbar.innerHTML = TABS.map( function ( tb ) {
			return '<button type="button" class="tab" data-tab="' + tb[ 0 ] + '">' + icon( tb[ 0 ] ) + '<span>' + esc( t( tb[ 0 ] ) ) + '</span></button>';
		} ).join( '' );
		qsa( '.tab', tabbar ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var target = '/' + b.getAttribute( 'data-tab' );
				if ( currentPath() === target ) { window.scrollTo( { top: 0, behavior: 'smooth' } ); return; }
				go( target, false, true );
			} );
		} );
		barBack.addEventListener( 'click', goBack );
		window.addEventListener( 'scroll', function () { bar.classList.toggle( 'is-scrolled', window.scrollY > 24 ); }, { passive: true } );
		initPullToRefresh();
	}

	function setBar( o ) {
		barTitle.textContent = o.title || '';
		bar.classList.toggle( 'large', !! o.large );
		barBack.hidden = ! o.back;
		barActions.innerHTML = '';
		( o.actions || [] ).forEach( function ( a ) {
			var b = document.createElement( 'button' );
			b.type = 'button'; b.className = 'bar-btn'; b.setAttribute( 'aria-label', a.label ); b.title = a.label;
			b.innerHTML = icon( a.icon );
			b.addEventListener( 'click', a.onClick );
			barActions.appendChild( b );
		} );
		document.title = ( o.title ? o.title + ' · ' : '' ) + ( C.appName || '' );
	}

	function skeleton( kind, n ) {
		var h = '';
		if ( kind === 'hero' ) { h += '<div class="sk sk-hero"></div>'; kind = 'row'; }
		for ( var i = 0; i < ( n || 5 ); i++ ) { h += '<div class="sk ' + ( kind === 'card' ? 'sk-card' : 'sk-row' ) + '"></div>'; }
		return h;
	}
	function emptyState( ico, msg, btnLabel, btnId ) {
		return '<div class="empty">' + icon( ico ) + '<p>' + esc( msg ) + '</p>' + ( btnLabel ? '<button type="button" class="btn btn-sm" id="' + btnId + '">' + esc( btnLabel ) + '</button>' : '' ) + '</div>';
	}
	function fail( ctx, e ) {
		if ( e && ( e.code === 'expired' || e.code === 'auth' ) ) { signOut( e.code === 'expired' ? t( 'expired' ) : '' ); return; }
		if ( ctx.stale() ) { return; }
		view.innerHTML = '<div class="empty">' + icon( 'wifi' ) + '<p>' + esc( ( e && e.message ) || t( 'offline' ) ) + '</p><button type="button" class="btn btn-sm" id="retry">' + esc( t( 'retry' ) ) + '</button></div>';
		$( 'retry' ).addEventListener( 'click', function () { render( true ); } );
	}
	function actErr( e ) {
		if ( e && ( e.code === 'expired' || e.code === 'auth' ) ) { signOut( t( 'expired' ) ); return; }
		toast( ( e && e.message ) || t( 'offline' ), true );
	}

	/* ================================================================ Router */

	var ROUTES = [
		[ /^\/campaigns$/, vCampaigns, 'campaigns' ],
		[ /^\/campaigns\/new$/, vCampaignForm, 'campaigns', 'form' ],
		[ /^\/c\/(\d+)$/, vCampaign, 'campaigns', 'sub' ],
		[ /^\/c\/(\d+)\/edit$/, vCampaignForm, 'campaigns', 'form' ],
		[ /^\/c\/(\d+)\/add$/, vPicker, 'campaigns', 'form' ],
		[ /^\/bloggers$/, vBloggers, 'bloggers' ],
		[ /^\/bloggers\/new$/, vBloggerForm, 'bloggers', 'form' ],
		[ /^\/b\/(\d+)$/, vBlogger, 'bloggers', 'sub' ],
		[ /^\/b\/(\d+)\/edit$/, vBloggerForm, 'bloggers', 'form' ],
		[ /^\/lists$/, vLists, 'lists' ],
		[ /^\/lists\/(\d+)$/, vListBloggers, 'lists', 'sub' ],
		[ /^\/insights$/, vInsights, 'insights' ],
		[ /^\/more$/, vMore, 'more' ]
	];
	var depth = 0, renderSeq = 0, scrollMem = {}, programmatic = false;

	function parseHash() {
		var raw = ( location.hash || '' ).replace( /^#/, '' ) || '/campaigns';
		var qi = raw.indexOf( '?' );
		var path = qi === -1 ? raw : raw.slice( 0, qi );
		var query = {};
		if ( qi !== -1 ) {
			raw.slice( qi + 1 ).split( '&' ).forEach( function ( p ) {
				var kv = p.split( '=' );
				if ( kv[ 0 ] ) { query[ decodeURIComponent( kv[ 0 ] ) ] = decodeURIComponent( kv[ 1 ] || '' ); }
			} );
		}
		return { path: path, query: query };
	}
	function currentPath() { return parseHash().path; }
	function go( path, replace, reset ) {
		scrollMem[ currentPath() ] = window.scrollY;
		programmatic = true;
		if ( reset ) { depth = 0; }
		if ( replace ) {
			location.replace( '#' + path );
		} else {
			depth++;
			location.hash = path;
		}
	}
	function parentOf( path ) {
		var m;
		if ( ( m = path.match( /^\/c\/(\d+)\/(edit|add)$/ ) ) ) { return '/c/' + m[ 1 ]; }
		if ( ( m = path.match( /^\/b\/(\d+)\/edit$/ ) ) ) { return '/b/' + m[ 1 ]; }
		if ( /^\/c\//.test( path ) || path === '/campaigns/new' ) { return '/campaigns'; }
		if ( /^\/b\//.test( path ) || path === '/bloggers/new' ) { return '/bloggers'; }
		if ( /^\/lists\//.test( path ) ) { return '/lists'; }
		return '/campaigns';
	}
	function goBack() {
		scrollMem[ currentPath() ] = window.scrollY;
		if ( depth > 0 ) { history.back(); } else { go( parentOf( currentPath() ), true ); programmatic = false; }
	}

	function render( force ) {
		closeSheets();
		var h = parseHash(), route = null, params = [];
		for ( var i = 0; i < ROUTES.length; i++ ) {
			var m = h.path.match( ROUTES[ i ][ 0 ] );
			if ( m ) { route = ROUTES[ i ]; params = m.slice( 1 ); break; }
		}
		if ( ! route ) { go( '/campaigns', true ); return; }
		var seq = ++renderSeq;
		var kind = route[ 3 ] || 'root';
		appEl.classList.toggle( 'no-tabs', kind === 'form' );
		view.classList.toggle( 'is-sub', kind === 'form' );
		qsa( '.tab', tabbar ).forEach( function ( b ) { b.classList.toggle( 'is-active', b.getAttribute( 'data-tab' ) === route[ 2 ] ); } );
		var fab = qs( '.fab' ); if ( fab ) { fab.remove(); }
		var sb = qs( '.savebar' ); if ( sb ) { sb.remove(); }
		var ctx = {
			params: params, query: h.query, force: !! force, path: h.path, kind: kind,
			stale: function () { return seq !== renderSeq; }
		};
		// Returning via back/swipe (not a tap that navigated forward) restores the old scroll position.
		var restore = ( ! programmatic || kind === 'root' ) ? scrollMem[ h.path ] : 0;
		programmatic = false;
		if ( ! force ) { window.scrollTo( 0, 0 ); }
		Promise.resolve( route[ 1 ]( ctx ) ).then( function () {
			if ( ctx.stale() ) { return; }
			if ( restore ) { window.scrollTo( 0, restore ); }
		} );
	}

	function fabBtn( label, onClick ) {
		var b = document.createElement( 'button' );
		b.type = 'button'; b.className = 'fab';
		b.innerHTML = icon( 'plus' ) + '<span>' + esc( label ) + '</span>';
		b.addEventListener( 'click', onClick );
		document.body.appendChild( b );
	}
	function saveBar( label, onClick, noteId ) {
		var d = document.createElement( 'div' );
		d.className = 'savebar';
		d.innerHTML = '<div class="savebar-inner"><button type="button" class="btn" id="save-btn">' + esc( label ) + '</button></div>';
		document.body.appendChild( d );
		var btn = $( 'save-btn' );
		btn.addEventListener( 'click', onClick );
		return btn;
	}

	/* ================================================================ Pull to refresh */

	function initPullToRefresh() {
		var ptr = $( 'ptr' ), startY = 0, pulling = false, ready = false, busy = false;
		window.addEventListener( 'touchstart', function ( e ) {
			if ( busy || openSheets.length || appEl.classList.contains( 'no-tabs' ) || window.scrollY > 0 ) { pulling = false; return; }
			startY = e.touches[ 0 ].clientY; pulling = true; ready = false;
		}, { passive: true } );
		window.addEventListener( 'touchmove', function ( e ) {
			if ( ! pulling ) { return; }
			var dy = e.touches[ 0 ].clientY - startY;
			if ( dy <= 0 ) { ptr.style.transform = ''; return; }
			var off = Math.min( dy * 0.5, 80 );
			ptr.style.transition = 'none';
			ptr.style.transform = 'translate(-50%,' + ( off - 60 ) + 'px) rotate(' + ( dy * 2 ) + 'deg)';
			ready = off > 58;
		}, { passive: true } );
		window.addEventListener( 'touchend', function () {
			if ( ! pulling ) { return; }
			pulling = false;
			ptr.style.transition = '';
			if ( ! ready ) { ptr.style.transform = ''; return; }
			busy = true;
			ptr.classList.add( 'is-loading' );
			ptr.style.transform = 'translate(-50%, 0)';
			refreshAll().then( done, done );
			function done() { busy = false; ptr.classList.remove( 'is-loading' ); ptr.style.transform = ''; }
		} );
	}
	function refreshAll() {
		drop( 'campaigns', 'camp', 'library', 'lists', 'insights', 'meta' );
		render( true );
		return new Promise( function ( r ) { setTimeout( r, 700 ); } );
	}

	/* ================================================================ Shared bits */

	function statusBadge( s ) {
		var k = ( s === 'confirmed' || s === 'declined' ) ? s : 'pending';
		return '<span class="badge ' + k + '">' + esc( t( k ) ) + '</span>';
	}
	function progress( c, d, p ) {
		var tot = ( c + d + p ) || 1;
		return '<div class="progress"><i class="g" style="width:' + ( c / tot * 100 ) + '%"></i><i class="r" style="width:' + ( d / tot * 100 ) + '%"></i><i class="p" style="width:' + ( p / tot * 100 ) + '%"></i></div>';
	}
	function searchBox( id, ph, val ) {
		return '<label class="search">' + icon( 'search' ) + '<input class="input" id="' + id + '" type="search" placeholder="' + esc( ph ) + '" value="' + esc( val || '' ) + '" autocomplete="off" /></label>';
	}
	function sw( id, checked, extra ) {
		return '<label class="switch"><input type="checkbox" id="' + id + '"' + ( checked ? ' checked' : '' ) + ( extra || '' ) + ' /><i></i></label>';
	}
	function kv( k, v ) { return ( v === '' || v == null ) ? '' : '<div class="kv"><span class="k">' + esc( k ) + '</span><span class="v">' + esc( v ) + '</span></div>'; }

	/* ================================================================ Campaigns list */

	var UC = { q: '', f: 'all', sort: sget( 'campSort', 'newest' ) };

	function vCampaigns( ctx ) {
		setBar( { title: t( 'campaigns' ), large: true, actions: [ { icon: 'refresh', label: t( 'refresh' ), onClick: function () { refreshAll(); } } ] } );
		view.innerHTML = '<h1 class="h1">' + esc( t( 'campaigns' ) ) + '</h1>' + skeleton( 'card', 4 );
		fabBtn( t( 'newCampaign' ), function () { go( '/campaigns/new' ); } );
		return getCampaigns( ctx.force ).then( function ( items ) {
			if ( ctx.stale() ) { return; }
			if ( ! items.length ) {
				view.innerHTML = '<h1 class="h1">' + esc( t( 'campaigns' ) ) + '</h1>' + emptyState( 'campaigns', t( 'emptyCampaigns' ), t( 'newCampaign' ), 'empty-new' );
				$( 'empty-new' ).addEventListener( 'click', function () { go( '/campaigns/new' ); } );
				return;
			}
			var live = items.filter( function ( c ) { return c.status === 'publish' && ! c.closed; } ).length;
			var closedN = items.filter( function ( c ) { return c.closed; } ).length;
			view.innerHTML = '<h1 class="h1">' + esc( t( 'campaigns' ) ) + '</h1>' +
				'<div class="toolbar"><div class="search">' + searchBox( 'c-q', t( 'searchCampaigns' ), UC.q ).replace( /^<label class="search">|<\/label>$/g, '' ) + '</div>' +
				'<button type="button" class="iconbtn" id="c-sort" aria-label="' + esc( t( 'sortBy' ) ) + '">' + icon( 'sort' ) + '</button></div>' +
				'<div class="seg" id="c-seg" style="margin-bottom:14px">' +
					'<button type="button" data-f="all">' + esc( t( 'all' ) ) + '<span class="n">' + items.length + '</span></button>' +
					'<button type="button" data-f="publish">' + esc( t( 'live' ) ) + '<span class="n">' + live + '</span></button>' +
					'<button type="button" data-f="draft">' + esc( t( 'drafts' ) ) + '<span class="n">' + items.filter( function ( c ) { return c.status !== 'publish' && ! c.closed; } ).length + '</span></button>' +
					'<button type="button" data-f="closed">' + esc( t( 'closed' ) ) + '<span class="n">' + closedN + '</span></button>' +
				'</div><div id="c-list"></div>';
			function paint() {
				qsa( '#c-seg button' ).forEach( function ( b ) { b.classList.toggle( 'is-on', b.getAttribute( 'data-f' ) === UC.f ); } );
				var q = lc( UC.q );
				var rows = items.filter( function ( c ) {
					if ( UC.f === 'publish' && ( c.status !== 'publish' || c.closed ) ) { return false; }
					if ( UC.f === 'draft' && ( c.status === 'publish' || c.closed ) ) { return false; }
					if ( UC.f === 'closed' && ! c.closed ) { return false; }
					return ! q || lc( c.title ).indexOf( q ) !== -1;
				} );
				rows.sort( function ( a, b ) {
					switch ( UC.sort ) {
						case 'oldest': return String( a.date ).localeCompare( String( b.date ) );
						case 'name': return lc( a.title ).localeCompare( lc( b.title ) );
						case 'confirmed': return b.confirmed - a.confirmed;
						case 'pending': return b.pending - a.pending;
						case 'people': return b.attendance - a.attendance;
						default: return String( b.date ).localeCompare( String( a.date ) );
					}
				} );
				var box = $( 'c-list' );
				if ( ! rows.length ) { box.innerHTML = '<p class="empty">' + esc( t( 'noResults' ) ) + '</p>'; return; }
				box.innerHTML = rows.map( function ( c ) {
					var pill = c.closed ? '<span class="pill">' + esc( t( 'closed' ) ) + '</span>' : ( c.status === 'publish' ? '<span class="pill live">' + esc( t( 'live' ) ) + '</span>' : '<span class="pill draft">' + esc( t( 'draft' ) ) + '</span>' );
					return '<div class="card card-tap" data-id="' + c.id + '"><div class="card-title"><h3>' + esc( c.title || '—' ) + '</h3>' + pill + '</div>' +
						progress( c.confirmed, c.declined, c.pending ) +
						'<div class="counts"><span class="g"><b>' + c.confirmed + '</b> ' + esc( t( 'confirmed' ) ) + '</span><span class="r"><b>' + c.declined + '</b> ' + esc( t( 'declined' ) ) + '</span><span><b>' + c.pending + '</b> ' + esc( t( 'pending' ) ) + '</span><span><b>' + c.attendance + '</b> ' + esc( t( 'people' ) ) + '</span></div></div>';
				} ).join( '' );
				qsa( '.card-tap', box ).forEach( function ( el ) { el.addEventListener( 'click', function () { go( '/c/' + el.getAttribute( 'data-id' ) ); } ); } );
			}
			$( 'c-q' ).addEventListener( 'input', debounce( function ( e ) { UC.q = e.target.value; paint(); }, 120 ) );
			qsa( '#c-seg button' ).forEach( function ( b ) { b.addEventListener( 'click', function () { UC.f = b.getAttribute( 'data-f' ); paint(); } ); } );
			$( 'c-sort' ).addEventListener( 'click', function () {
				choiceSheet( t( 'sortBy' ), [ [ 'newest', t( 'sNewest' ) ], [ 'oldest', t( 'sOldest' ) ], [ 'name', t( 'sName' ) ], [ 'confirmed', t( 'sMostConfirmed' ) ], [ 'pending', t( 'sMostPending' ) ], [ 'people', t( 'sMostPeople' ) ] ], UC.sort, function ( v ) { UC.sort = v; sset( 'campSort', v ); paint(); } );
			} );
			paint();
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Campaign detail */

	var UP = { q: '', f: 'all', sort: sget( 'partSort', 'order' ) };

	function vCampaign( ctx ) {
		var id = parseInt( ctx.params[ 0 ], 10 );
		setBar( { title: '', back: true } );
		view.innerHTML = skeleton( 'hero', 6 );
		return Promise.all( [ getCampaign( id, ctx.force ), getLibrary().catch( function () { return []; } ) ] ).then( function ( r ) {
			if ( ctx.stale() ) { return; }
			drawCampaign( r[ 0 ], libByHandle( r[ 1 ] ) );
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	function drawCampaign( c, libMap ) {
		var s = c.stats, st = c.settings || {}, id = c.id, isLive = st.status === 'publish';
		setBar( {
			title: c.title, back: true,
			actions: [
				{ icon: 'edit', label: t( 'edit' ), onClick: function () { go( '/c/' + id + '/edit' ); } },
				{ icon: 'more', label: t( 'more' ), onClick: function () { campaignMenu( c ); } }
			]
		} );

		var h = '<div class="camp-head">' + ( st.logo_url ? '<img class="camp-logo" src="' + esc( st.logo_url ) + '" alt="" />' : '' ) +
			'<div style="min-width:0"><h2 class="h2">' + esc( c.title ) + '</h2>' +
			( st.closed ? '<span class="pill">' + esc( t( 'closed' ) ) + '</span>' : ( isLive ? '<span class="pill live">' + esc( t( 'live' ) ) + '</span>' : '<span class="pill draft">' + esc( t( 'draft' ) ) + '</span>' ) ) + '</div></div>';
		if ( st.closed ) { h += '<div class="banner info"><p>' + esc( t( 'closedBanner' ) ) + '</p><button type="button" class="btn btn-sm btn-ghost" id="d-reopen">' + esc( t( 'reopen' ) ) + '</button></div>'; }
		if ( ! isLive && ! st.closed ) { h += '<div class="banner warn"><p>' + esc( t( 'draftBanner' ) ) + '</p><button type="button" class="btn btn-sm" id="d-publish">' + esc( t( 'publishNow' ) ) + '</button></div>'; }
		if ( ! st.has_password ) { h += '<div class="banner warn"><p>' + esc( t( 'noPwBanner' ) ) + '</p><button type="button" class="btn btn-sm btn-ghost" id="d-setpw">' + esc( t( 'setPassword' ) ) + '</button></div>'; }
		h += '<div class="tiles"><div class="tile green"><b>' + s.confirmed + '</b><span>' + esc( t( 'confirmed' ) ) + '</span></div>' +
			'<div class="tile red"><b>' + s.declined + '</b><span>' + esc( t( 'declined' ) ) + '</span></div>' +
			'<div class="tile"><b>' + s.pending + '</b><span>' + esc( t( 'pending' ) ) + '</span></div>' +
			'<div class="tile inv"><b>' + s.attendance + '</b><span>' + esc( t( 'peopleCap' ) ) + '</span></div></div>' +
			progress( s.confirmed, s.declined, s.pending );
		h += '<div class="quick">' +
			'<button type="button" class="qa" id="q-copy"' + ( st.url ? '' : ' disabled' ) + '>' + icon( 'link' ) + esc( t( 'copyLink' ) ) + '</button>' +
			'<button type="button" class="qa" id="q-share"' + ( st.url ? '' : ' disabled' ) + '>' + icon( 'share' ) + esc( t( 'share' ) ) + '</button>' +
			( st.url ? '<a class="qa" href="' + esc( st.url ) + '" target="_blank" rel="noopener">' + icon( 'open' ) + esc( t( 'openPage' ) ) + '</a>' : '<button type="button" class="qa" disabled>' + icon( 'open' ) + esc( t( 'openPage' ) ) + '</button>' ) +
			'<button type="button" class="qa" id="q-export">' + icon( 'download' ) + esc( t( 'exportCsv' ) ) + '</button></div>';

		var at = c.atrium, atRows = {};
		if ( at ) {
			( at.rows || [] ).forEach( function ( r ) { atRows[ lc( r.handle ) ] = r; } );
			h += '<div class="section"><h3>' + esc( t( 'event' ) ) + '</h3></div>';
			if ( ! at.event ) {
				h += '<div class="card"><p class="muted" style="margin:0 0 12px">' + esc( t( 'evNone' ) ) + '</p><div class="btn-row"><button type="button" class="btn btn-sm" id="ev-create">' + esc( t( 'evCreate' ) ) + '</button><button type="button" class="btn btn-sm btn-ghost" id="ev-link">' + esc( t( 'evLink' ) ) + '</button></div></div>';
			} else {
				var ev = at.event, fu = at.funnel;
				var steps = [ [ 'selected', 'evSelected' ], [ 'invited', 'evInvited' ], [ 'opened', 'evOpened' ], [ 'confirmed', 'evConfirmed' ], [ 'attended', 'evAttended' ] ];
				h += '<div class="card"><div class="card-title"><h3>' + esc( ev.title ) + '</h3><span class="pill ' + ( ev.status === 'draft' ? 'draft' : 'live' ) + '">' + esc( ev.label ) + '</span></div>' +
					'<div class="small muted">' + esc( [ ev.date, ev.per_guest ? t( 'evPerGuest' ) : '' ].filter( Boolean ).join( ' · ' ) ) + '</div>' +
					( ev.status === 'draft' ? '<div class="banner warn" style="margin:12px 0 0"><p>' + esc( t( 'evDraft' ) ) + '</p></div>' : '' ) +
					'<div class="tiles ev-funnel">' + steps.map( function ( st2 ) { return '<div class="tile' + ( st2[ 0 ] === 'attended' ? ' green' : '' ) + '"><b>' + ( fu[ st2[ 0 ] ] || 0 ) + '</b><span>' + esc( t( st2[ 1 ] ) ) + '</span></div>'; } ).join( '' ) + '</div>' +
					'<div class="btn-row"><button type="button" class="btn btn-sm" id="ev-send">' + esc( at.pending ? t( 'evSendN', at.pending ) : t( 'evSync' ) ) + '</button><button type="button" class="btn btn-sm btn-ghost" id="ev-more" style="flex:0 0 48px">⋯</button></div></div>';
			}
		}

		var evTxt = c.everyone ? ( c.selection_started ? t( 'evPaused' ) : t( 'evActive' ) ) : t( 'evOff' );
		var evCol = c.everyone ? ( c.selection_started ? 'var(--orange)' : 'var(--green)' ) : 'var(--muted)';
		h += '<div class="section"><h3>' + esc( t( 'participants' ) ) + '</h3><span class="small">' + num( c.bloggers.length ) + '</span></div>';
		h += '<div class="group"><div class="item"><div class="item-main"><b>' + esc( t( 'everyone' ) ) + '</b><small style="color:' + evCol + '">' + esc( evTxt ) + '</small></div>' + sw( 'd-every', c.everyone ) + '</div>' +
			'<div class="item"><div class="btn-row" style="width:100%"><button type="button" class="btn btn-sm" id="d-add">' + icon( 'plus' ) + esc( t( 'addBloggers' ) ) + '</button><button type="button" class="btn btn-sm btn-ghost" id="d-all">' + esc( t( 'addAll' ) ) + '</button></div></div></div>';

		var counts = { all: 0, confirmed: 0, declined: 0, pending: 0 };
		c.bloggers.forEach( function ( b ) { counts.all++; counts[ ( b.status === 'confirmed' || b.status === 'declined' ) ? b.status : 'pending' ]++; } );
		h += '<div class="toolbar"><div class="search">' + searchBox( 'p-q', t( 'searchHere' ), UP.q ).replace( /^<label class="search">|<\/label>$/g, '' ) + '</div>' +
			'<button type="button" class="iconbtn" id="p-sort" aria-label="' + esc( t( 'sortBy' ) ) + '">' + icon( 'sort' ) + '</button></div>' +
			'<div class="seg" id="p-seg" style="margin-bottom:12px">' +
			[ 'all', 'confirmed', 'declined', 'pending' ].map( function ( k ) { return '<button type="button" data-f="' + k + '">' + esc( t( k ) ) + '<span class="n">' + counts[ k ] + '</span></button>'; } ).join( '' ) +
			'</div><div id="p-list"></div>';
		view.innerHTML = h;

		function paint() {
			qsa( '#p-seg button' ).forEach( function ( b ) { b.classList.toggle( 'is-on', b.getAttribute( 'data-f' ) === UP.f ); } );
			var q = lc( UP.q ).replace( /^@/, '' );
			var rank = function ( st2 ) { return st2 === 'confirmed' ? 0 : st2 === 'declined' ? 1 : 2; };
			var rows = c.bloggers.map( function ( b, i ) { return { b: b, i: i, l: libMap[ lc( b.account ) ] }; } ).filter( function ( r ) {
				var stt = ( r.b.status === 'confirmed' || r.b.status === 'declined' ) ? r.b.status : 'pending';
				if ( UP.f !== 'all' && stt !== UP.f ) { return false; }
				if ( ! q ) { return true; }
				return ( lc( r.b.account ) + ' ' + lc( r.l ? r.l.name : '' ) + ' ' + lc( r.b.city ) ).indexOf( q ) !== -1;
			} );
			var nm = function ( r ) { return lc( r.l && r.l.name ? r.l.name : r.b.account ); };
			rows.sort( function ( x, y ) {
				var d = 0;
				switch ( UP.sort ) {
					case 'status': d = rank( x.b.status ) - rank( y.b.status ); break;
					case 'status_rev': d = rank( y.b.status ) - rank( x.b.status ); break;
					case 'name': d = nm( x ).localeCompare( nm( y ) ); break;
					case 'followers_desc': d = ( y.b.followers || 0 ) - ( x.b.followers || 0 ); break;
					case 'followers_asc': d = ( x.b.followers || 0 ) - ( y.b.followers || 0 ); break;
					case 'people': d = ( y.b.people || 0 ) - ( x.b.people || 0 ); break;
					case 'city': d = ( lc( x.b.city ) || '\uffff' ).localeCompare( lc( y.b.city ) || '\uffff' ); break;
				}
				return d || ( x.i - y.i );
			} );
			var box = $( 'p-list' );
			if ( ! c.bloggers.length ) { box.innerHTML = emptyState( 'bloggers', t( 'emptyPart' ) ); return; }
			if ( ! rows.length ) { box.innerHTML = '<p class="empty">' + esc( t( 'noResults' ) ) + '</p>'; return; }
			box.innerHTML = '<div class="rows">' + rows.map( function ( r ) {
				var b = r.b, name = r.l && r.l.name ? r.l.name : '';
				var sub = [ '@' + b.account ];
				if ( b.followers ) { sub.push( compact( b.followers ) ); }
				if ( b.city ) { sub.push( b.city ); }
				var side = statusBadge( b.status ) + ( b.status === 'confirmed' ? '<small>' + b.people + ' ' + esc( t( 'people' ) ) + '</small>' : '' );
				var ar = atRows[ lc( b.account ) ];
				if ( ar && at.labels ) { side += '<small><span class="stg stg-' + esc( ar.stage ) + '">' + esc( at.labels[ ar.stage ] ) + '</span></small>'; }
				return '<div class="row row-tap" data-row="' + b.row_id + '">' + avatar( name, b.account, false, b.photo || ( r.l && r.l.photo ) ) +
					'<div class="row-main"><b>' + esc( name || '@' + b.account ) + ( b.verified ? ' <span class="ver">✓</span>' : '' ) + '</b><small>' + esc( sub.join( ' · ' ) ) + '</small></div>' +
					'<div class="row-side">' + side + '</div><button type="button" class="row-more" data-more="' + b.row_id + '" aria-label="' + esc( t( 'more' ) ) + '">⋯</button></div>';
			} ).join( '' ) + '</div>';
			qsa( '.row-tap', box ).forEach( function ( el ) {
				el.addEventListener( 'click', function ( e ) {
					var rid = parseInt( el.getAttribute( 'data-row' ), 10 );
					var b = c.bloggers.filter( function ( x ) { return x.row_id === rid; } )[ 0 ];
					if ( ! b ) { return; }
					if ( e.target.closest( '.row-more' ) ) { rowMenu( b ); return; }
					var l = libMap[ lc( b.account ) ];
					if ( l ) { go( '/b/' + l.id ); } else { window.open( igUrl( b.account ), '_blank', 'noopener' ); }
				} );
			} );
		}
		function rowMenu( b ) {
			var l = libMap[ lc( b.account ) ], acts = [];
			if ( l ) { acts.push( { icon: 'user', label: t( 'viewProfile' ), onClick: function () { go( '/b/' + l.id ); } } ); }
			acts.push( { icon: 'ig', label: t( 'openInstagram' ), onClick: function () { window.open( igUrl( b.account ), '_blank', 'noopener' ); } } );
			var inv = atRows[ lc( b.account ) ];
			if ( inv && inv.wa ) { acts.push( { icon: 'chat', label: t( 'waInvite' ), onClick: function () { window.open( inv.wa, '_blank', 'noopener' ); } } ); }
			if ( inv && inv.url ) { acts.push( { icon: 'link', label: t( 'copyInvite' ), onClick: function () { copyText( inv.url ).then( function () { toast( t( 'copied' ) ); } ); } } ); }
			acts.push( { icon: 'remove', label: t( 'removeFromCamp' ), danger: true, onClick: function () {
				confirmSheet( b.status === 'pending' ? t( 'removeQ' ) : t( 'removeRespQ' ), t( 'removeFromCamp' ), true ).then( function ( ok ) {
					if ( ok ) { participants( { action: 'remove', row_id: b.row_id } ); }
				} );
			} } );
			sheet( { title: '@' + b.account, actions: acts } );
		}
		function participants( body ) {
			return post( '/campaigns/' + id + '/participants', body ).then( function ( nc ) {
				S.camp[ id ] = nc; drop( 'campaigns', 'insights' );
				toast( nc.notice );
				drawCampaign( nc, libMap );
			} ).catch( actErr );
		}

		$( 'p-q' ).addEventListener( 'input', debounce( function ( e ) { UP.q = e.target.value; paint(); }, 120 ) );
		qsa( '#p-seg button' ).forEach( function ( b ) { b.addEventListener( 'click', function () { UP.f = b.getAttribute( 'data-f' ); paint(); } ); } );
		$( 'p-sort' ).addEventListener( 'click', function () {
			choiceSheet( t( 'sortBy' ), [ [ 'order', t( 'sOrder' ) ], [ 'status', t( 'sStatus' ) ], [ 'status_rev', t( 'sStatusRev' ) ], [ 'name', t( 'sName' ) ], [ 'followers_desc', t( 'sFollowersDesc' ) ], [ 'followers_asc', t( 'sFollowersAsc' ) ], [ 'people', t( 'sPeople' ) ], [ 'city', t( 'sCity' ) ] ], UP.sort, function ( v ) { UP.sort = v; sset( 'partSort', v ); paint(); } );
		} );
		$( 'd-every' ).addEventListener( 'change', function ( e ) { participants( { action: 'everyone', on: e.target.checked } ); } );
		$( 'd-add' ).addEventListener( 'click', function () { go( '/c/' + id + '/add' ); } );
		$( 'd-all' ).addEventListener( 'click', function () { confirmSheet( t( 'addAllQ' ), t( 'addAll' ) ).then( function ( ok ) { if ( ok ) { participants( { action: 'add_all' } ); } } ); } );
		var rop = $( 'd-reopen' ); if ( rop ) { rop.addEventListener( 'click', function () { campaignAction( c, 'reopen' ); } ); }
		var pub = $( 'd-publish' ); if ( pub ) { pub.addEventListener( 'click', function () { campaignAction( c, 'publish' ); } ); }
		var spw = $( 'd-setpw' ); if ( spw ) { spw.addEventListener( 'click', function () { go( '/c/' + id + '/edit?focus=pw' ); } ); }
		$( 'q-copy' ).addEventListener( 'click', function () { copyText( st.url ).then( function () { toast( t( 'copied' ) ); } ); } );
		$( 'q-share' ).addEventListener( 'click', function () {
			if ( navigator.share ) { navigator.share( { title: c.title, url: st.url } ).catch( function () {} ); } else { copyText( st.url ).then( function () { toast( t( 'copied' ) ); } ); }
		} );
		$( 'q-export' ).addEventListener( 'click', function () { exportCsv( id ); } );
		function atriumOp( body ) {
			return post( '/campaigns/' + id + '/atrium', body ).then( function ( nc ) {
				S.camp[ id ] = nc; drop( 'campaigns', 'insights', 'library' );
				toast( nc.notice ); drawCampaign( nc, libMap );
			} ).catch( actErr );
		}
		var evc = $( 'ev-create' ), evl = $( 'ev-link' ), evs = $( 'ev-send' ), evm = $( 'ev-more' );
		if ( evc ) {
			evc.addEventListener( 'click', function () {
				sheet( { title: t( 'evCreate' ), actions: [
					{ icon: 'campaigns', label: t( 'evShared' ), onClick: function () { atriumOp( { op: 'create' } ); } },
					{ icon: 'user', label: t( 'evPerGuestOpt' ), onClick: function () { atriumOp( { op: 'create', per_guest: 1 } ); } }
				] } );
			} );
		}
		if ( evl ) {
			evl.addEventListener( 'click', function () {
				api( '/atrium/events' ).then( function ( d ) {
					var evs2 = d.events || [];
					if ( ! evs2.length ) { toast( t( 'evNoEvents' ) ); return; }
					sheet( { title: t( 'evPick' ), actions: evs2.map( function ( e ) {
						return { label: e.title + ( e.date ? ' · ' + e.date : '' ) + ' (' + e.label + ')', onClick: function () { atriumOp( { op: 'link', event: e.id } ); } };
					} ) } );
				} ).catch( actErr );
			} );
		}
		if ( evs ) { evs.addEventListener( 'click', function () { evs.disabled = true; atriumOp( { op: 'send' } ); } ); }
		if ( evm && at && at.event ) {
			evm.addEventListener( 'click', function () {
				var acts2 = [];
				if ( at.event.edit ) { acts2.push( { icon: 'edit', label: t( 'evEdit' ), onClick: function () { window.open( at.event.edit, '_blank', 'noopener' ); } } ); }
				if ( at.event.url ) { acts2.push( { icon: 'open', label: t( 'evPage' ), onClick: function () { window.open( at.event.url, '_blank', 'noopener' ); } } ); }
				acts2.push( { icon: 'remove', label: t( 'evUnlink' ), danger: true, onClick: function () {
					confirmSheet( t( 'evUnlinkQ' ), t( 'evUnlink' ), true ).then( function ( ok ) { if ( ok ) { atriumOp( { op: 'unlink' } ); } } );
				} } );
				sheet( { title: at.event.title, actions: acts2 } );
			} );
		}
		paint();
	}

	function campaignMenu( c ) {
		var st = c.settings || {}, acts = [];
		acts.push( { icon: 'edit', label: t( 'edit' ), onClick: function () { go( '/c/' + c.id + '/edit' ); } } );
		acts.push( { icon: 'copy', label: t( 'duplicate' ), onClick: function () { campaignAction( c, 'duplicate' ); } } );
		if ( st.status === 'publish' ) { acts.push( { icon: 'draft', label: t( 'moveDraft' ), onClick: function () { campaignAction( c, 'draft' ); } } ); }
		else { acts.push( { icon: 'publish', label: t( 'publishNow' ), onClick: function () { campaignAction( c, 'publish' ); } } ); }
		if ( st.closed ) { acts.push( { icon: 'unblock', label: t( 'reopen' ), onClick: function () { campaignAction( c, 'reopen' ); } } ); }
		else { acts.push( { icon: 'block', label: t( 'closeCamp' ), onClick: function () { confirmSheet( t( 'closeQ' ), t( 'closeCamp' ) ).then( function ( ok ) { if ( ok ) { campaignAction( c, 'close' ); } } ); } } ); }
		acts.push( { icon: 'reset', label: t( 'resetResp' ), danger: true, onClick: function () { confirmSheet( t( 'resetQ' ), t( 'resetResp' ), true ).then( function ( ok ) { if ( ok ) { campaignAction( c, 'reset' ); } } ); } } );
		acts.push( { icon: 'trash', label: t( 'delete' ), danger: true, onClick: function () { confirmSheet( t( 'deleteCampQ' ), t( 'delete' ), true ).then( function ( ok ) { if ( ok ) { campaignAction( c, 'trash' ); } } ); } } );
		sheet( { title: c.title, actions: acts } );
	}
	function campaignAction( c, op ) {
		return post( '/campaigns/' + c.id + '/action', { op: op } ).then( function ( r ) {
			drop( 'campaigns', 'insights' );
			toast( r.notice );
			if ( op === 'trash' ) { delete S.camp[ c.id ]; go( '/campaigns', true, true ); return; }
			S.camp[ r.id ] = r;
			if ( op === 'duplicate' ) { go( '/c/' + r.id + '/edit', true ); return; }
			render( false );
		} ).catch( actErr );
	}
	function exportCsv( id ) {
		api( '/campaigns/' + id + '/export' ).then( function ( d ) {
			var lines = String( d.csv || '' ).trim().split( /\r?\n/ );
			if ( lines.length < 2 ) { toast( t( 'exportNone' ) ); return; }
			var blob = new Blob( [ '\ufeff' + d.csv ], { type: 'text/csv;charset=utf-8' } );
			var file = null;
			try { file = new File( [ blob ], d.filename, { type: 'text/csv' } ); } catch ( e ) {}
			if ( file && navigator.canShare && navigator.canShare( { files: [ file ] } ) ) {
				navigator.share( { files: [ file ], title: d.filename } ).catch( function () {} );
				return;
			}
			var a = document.createElement( 'a' );
			a.href = URL.createObjectURL( blob ); a.download = d.filename;
			document.body.appendChild( a ); a.click();
			setTimeout( function () { URL.revokeObjectURL( a.href ); a.remove(); }, 1000 );
		} ).catch( actErr );
	}

	/* ================================================================ Campaign form */

	function vCampaignForm( ctx ) {
		var id = ctx.params[ 0 ] ? parseInt( ctx.params[ 0 ], 10 ) : 0;
		setBar( { title: id ? t( 'editCampaign' ) : t( 'newCampaign' ), back: true } );
		view.innerHTML = skeleton( 'row', 6 );
		var load = id ? getCampaign( id, true ) : Promise.resolve( null );
		return load.then( function ( c ) {
			if ( ctx.stale() ) { return; }
			var st = c ? c.settings : { status: 'publish', brief: '', max_guests: 4, slug: '', slug_base: '', has_password: false, notify_email: '', logo_url: '', show_followers: false, show_gender: false, show_tags: false, show_location: false, show_popularity: true };
			var ev = c ? c.everyone : false;
			var state = { max: st.max_guests, status: st.status === 'publish' ? 'publish' : 'draft', file: null, removeLogo: false };
			var base = st.slug_base || ( C.api || '' ).replace( /wp-json.*$/, 'campaign/' );

			var h = '<form id="cf" novalidate>';
			h += '<div class="section"><h3>' + esc( t( 'basics' ) ) + '</h3></div><div class="card">' +
				'<label class="field"><span>' + esc( t( 'campName' ) ) + ' *</span><input class="input" id="cf-title" value="' + esc( c ? c.title : '' ) + '" autocomplete="off" /></label>' +
				'<label class="field" style="margin:0"><span>' + esc( t( 'brief' ) ) + '</span><textarea class="input" id="cf-brief" placeholder="' + esc( t( 'briefPh' ) ) + '">' + esc( st.brief ) + '</textarea></label></div>';

			h += '<div class="section"><h3>' + esc( t( 'logo' ) ) + '</h3></div><div class="card"><div class="logo-pick"><div class="logo-prev" id="cf-logo-prev">' + ( st.logo_url ? '<img src="' + esc( st.logo_url ) + '" alt="" />' : '—' ) + '</div>' +
				'<div><label class="btn btn-sm btn-ghost" style="cursor:pointer">' + esc( t( 'chooseImage' ) ) + '<input type="file" accept="image/*" id="cf-logo" hidden /></label> ' +
				'<button type="button" class="linkbtn" id="cf-logo-rm"' + ( st.logo_url ? '' : ' hidden' ) + '>' + esc( t( 'removeImage' ) ) + '</button></div></div></div>';

			h += '<div class="section"><h3>' + esc( t( 'startWith' ) ) + '</h3></div><div class="group"><div class="item"><div class="item-main"><b>' + esc( t( 'everyone' ) ) + '</b><small>' + esc( t( 'startEveryone' ) ) + '</small></div>' + sw( 'cf-every', ev ) + '</div></div>';
			if ( ! id ) { h += '<p class="hint" style="margin:-4px 4px 0">' + esc( t( 'startPick' ) ) + '</p>'; }

			h += '<div class="section"><h3>' + esc( t( 'access' ) ) + '</h3></div><div class="card">' +
				'<label class="field"><span>' + esc( t( 'password' ) ) + ( st.has_password ? '' : ' *' ) + '</span><div class="inline"><input class="input" id="cf-pw" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="' + esc( st.has_password ? t( 'pwKeep' ) : t( 'pwNew' ) ) + '" value="' + esc( id ? '' : genPassword() ) + '" /><button type="button" class="btn btn-sm btn-ghost" id="cf-gen">' + esc( t( 'generate' ) ) + '</button></div></label>' +
				'<label class="field" style="margin-bottom:' + ( id ? '14px' : '0' ) + '"><span>' + esc( t( 'customLink' ) ) + '</span><div class="slug"><span>' + esc( base ) + '</span><input class="input" id="cf-slug" value="' + esc( st.slug ) + '" autocapitalize="none" spellcheck="false" placeholder="my-campaign" /></div></label>' +
				( id ? '<label class="check" style="margin-bottom:0"><input type="checkbox" id="cf-random" /> <span>' + esc( t( 'randomLink' ) ) + '</span></label>' : '' ) + '</div>';

			h += '<div class="section"><h3>' + esc( t( 'clientPage' ) ) + '</h3></div><div class="group">' +
				'<div class="item"><div class="item-main"><b>' + esc( t( 'maxGuests' ) ) + '</b></div><div class="stepper"><button type="button" id="cf-minus">−</button><output id="cf-max">' + state.max + '</output><button type="button" id="cf-plus">+</button></div></div>' +
				[ [ 'followers', 'showFollowers' ], [ 'gender', 'showGender' ], [ 'tags', 'showCats' ], [ 'location', 'showLocation' ], [ 'popularity', 'showPopularity' ] ].map( function ( p ) {
					return '<div class="item"><div class="item-main"><b>' + esc( t( p[ 1 ] ) ) + '</b></div>' + sw( 'cf-show-' + p[ 0 ], st[ 'show_' + p[ 0 ] ] ) + '</div>';
				} ).join( '' ) + '</div>';

			h += '<div class="section"><h3>' + esc( t( 'notifications' ) ) + '</h3></div><div class="card"><input class="input" id="cf-notify" type="text" inputmode="email" autocapitalize="none" value="' + esc( st.notify_email ) + '" placeholder="' + esc( t( 'notifyPh' ) ) + '" /></div>';

			h += '<div class="section"><h3>' + esc( t( 'visibility' ) ) + '</h3></div><div class="seg" id="cf-status"><button type="button" data-s="publish">' + esc( t( 'publishLive' ) ) + '</button><button type="button" data-s="draft">' + esc( t( 'saveDraft' ) ) + '</button></div>';
			h += '<p class="error" id="cf-err"></p></form>';
			view.innerHTML = h;

			function paintStatus() { qsa( '#cf-status button' ).forEach( function ( b ) { b.classList.toggle( 'is-on', b.getAttribute( 'data-s' ) === state.status ); } ); }
			paintStatus();
			qsa( '#cf-status button' ).forEach( function ( b ) { b.addEventListener( 'click', function () { state.status = b.getAttribute( 'data-s' ); paintStatus(); } ); } );
			$( 'cf-minus' ).addEventListener( 'click', function () { state.max = Math.max( 0, state.max - 1 ); $( 'cf-max' ).textContent = state.max; } );
			$( 'cf-plus' ).addEventListener( 'click', function () { state.max = Math.min( 20, state.max + 1 ); $( 'cf-max' ).textContent = state.max; } );
			$( 'cf-gen' ).addEventListener( 'click', function () { $( 'cf-pw' ).value = genPassword(); } );
			$( 'cf-logo' ).addEventListener( 'change', function ( e ) {
				var f = e.target.files && e.target.files[ 0 ];
				if ( ! f ) { return; }
				state.file = f; state.removeLogo = false;
				var url = URL.createObjectURL( f );
				$( 'cf-logo-prev' ).innerHTML = '<img src="' + url + '" alt="" />';
				$( 'cf-logo-rm' ).hidden = false;
			} );
			$( 'cf-logo-rm' ).addEventListener( 'click', function () {
				state.file = null; state.removeLogo = true;
				$( 'cf-logo-prev' ).textContent = '—';
				$( 'cf-logo-rm' ).hidden = true;
			} );
			$( 'cf' ).addEventListener( 'submit', function ( e ) { e.preventDefault(); } );
			if ( ctx.query.focus === 'pw' ) { setTimeout( function () { $( 'cf-pw' ).scrollIntoView( { block: 'center' } ); $( 'cf-pw' ).focus(); }, 60 ); }

			var btn = saveBar( id ? t( 'save' ) : t( 'create' ), submit );
			function submit() {
				var err = $( 'cf-err' ); err.textContent = '';
				var title = $( 'cf-title' ).value.trim();
				if ( ! title ) { $( 'cf-title' ).classList.add( 'is-invalid' ); $( 'cf-title' ).focus(); toast( t( 'nameRequired' ), true ); return; }
				var body = {
					title: title, brief: $( 'cf-brief' ).value, max_guests: state.max, status: state.status,
					slug: $( 'cf-slug' ).value.trim(), password: $( 'cf-pw' ).value.trim(), notify_email: $( 'cf-notify' ).value.trim(),
					everyone: $( 'cf-every' ).checked, remove_logo: state.removeLogo
				};
				[ 'followers', 'gender', 'tags', 'location', 'popularity' ].forEach( function ( k ) { body[ 'show_' + k ] = $( 'cf-show-' + k ).checked; } );
				if ( id && $( 'cf-random' ) && $( 'cf-random' ).checked ) { body.regenerate = true; }
				btn.disabled = true; btn.textContent = t( 'saving' );
				post( id ? '/campaigns/' + id : '/campaigns', body ).then( function ( r ) {
					var cid = r.id;
					var up = state.file ? uploadLogo( cid, state.file ).then( function ( lr ) { if ( r.settings ) { r.settings.logo_url = lr.logo_url; } } ).catch( function () { toast( t( 'uploadFailed' ), true ); } ) : Promise.resolve();
					return up.then( function () {
						S.camp[ cid ] = r; drop( 'campaigns', 'insights' );
						toast( r.notice );
						if ( id ) { goBack(); } else {
							go( '/c/' + cid, true );
							if ( ! body.everyone ) { setTimeout( function () { go( '/c/' + cid + '/add' ); }, 30 ); }
						}
						if ( r.new_password ) { setTimeout( function () { shareSheet( r.settings.url, r.new_password ); }, 120 ); }
					} );
				} ).catch( function ( e ) {
					btn.disabled = false; btn.textContent = id ? t( 'save' ) : t( 'create' );
					actErr( e );
					err.textContent = ( e && e.message ) || '';
				} );
			}
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}
	function uploadLogo( id, file ) {
		var fd = new FormData();
		fd.append( 'file', file, file.name || 'logo.png' );
		return api( '/campaigns/' + id + '/logo', { method: 'POST', body: fd } );
	}
	function shareSheet( url, pw ) {
		var msg = t( 'shareMsg', url, pw );
		var s = sheet( {
			title: t( 'shareTitle' ),
			message: t( 'shareNote' ),
			html: '<div class="pwbox">' + kv( t( 'customLink' ), url ) + kv( t( 'password' ), pw ) + '</div><div class="sheet-actions"><button type="button" class="btn" data-copy>' + esc( t( 'copyBoth' ) ) + '</button>' + ( navigator.share ? '<button type="button" class="btn btn-ghost" data-share>' + esc( t( 'share' ) ) + '</button>' : '' ) + '<button type="button" class="btn btn-ghost" data-done>' + esc( t( 'done' ) ) + '</button></div>'
		} );
		qs( '[data-copy]', s.el ).addEventListener( 'click', function () { copyText( msg ).then( function () { toast( t( 'copied' ) ); } ); } );
		var sh = qs( '[data-share]', s.el ); if ( sh ) { sh.addEventListener( 'click', function () { navigator.share( { text: msg } ).catch( function () {} ); } ); }
		qs( '[data-done]', s.el ).addEventListener( 'click', s.close );
	}

	/* ================================================================ Picker (add bloggers to a campaign) */

	function vPicker( ctx ) {
		var id = parseInt( ctx.params[ 0 ], 10 );
		setBar( { title: t( 'addBloggers' ), back: true } );
		view.innerHTML = skeleton( 'row', 8 );
		var F = { q: '', list: '', gender: '', city: '', verified: false, sort: 'name' };
		var picked = {};
		return Promise.all( [ getCampaign( id ), getLibrary( ctx.force ), getMeta() ] ).then( function ( r ) {
			if ( ctx.stale() ) { return; }
			var c = r[ 0 ], lib = r[ 1 ], meta = r[ 2 ];
			var inCamp = {}; c.bloggers.forEach( function ( b ) { inCamp[ lc( b.account ) ] = 1; } );
			var avail = lib.filter( function ( b ) { return ! b.blocked && b.handle && ! inCamp[ lc( b.handle ) ]; } );
			view.innerHTML = '<div class="toolbar"><div class="search">' + searchBox( 'k-q', t( 'searchBloggers' ), '' ).replace( /^<label class="search">|<\/label>$/g, '' ) + '</div>' +
				'<button type="button" class="iconbtn" id="k-filter">' + icon( 'filter' ) + '<span class="dot" id="k-dot" hidden></span></button>' +
				'<button type="button" class="iconbtn" id="k-sort">' + icon( 'sort' ) + '</button></div>' +
				'<div class="chipbar" id="k-chips"></div><div class="countline"><span id="k-count"></span><button type="button" class="linkbtn" id="k-selall">' + esc( t( 'selectShown' ) ) + '</button></div><div id="k-list"></div>';
			var btn = saveBar( t( 'addSelected', 0 ), function () {
				var hs = Object.keys( picked ).map( function ( k ) { return picked[ k ]; } );
				if ( ! hs.length ) { return; }
				btn.disabled = true;
				post( '/campaigns/' + id + '/participants', { action: 'add', handles: hs } ).then( function ( nc ) {
					S.camp[ id ] = nc; drop( 'campaigns', 'insights' );
					toast( nc.notice ); goBack();
				} ).catch( function ( e ) { btn.disabled = false; actErr( e ); } );
			} );
			btn.disabled = true;
			var shown = [];
			function paint() {
				var q = lc( F.q ).replace( /^@/, '' );
				shown = avail.filter( function ( b ) {
					if ( F.list && ( b.lists || [] ).indexOf( parseInt( F.list, 10 ) ) === -1 ) { return false; }
					if ( F.gender && b.gender !== F.gender ) { return false; }
					if ( F.city && b.city !== F.city ) { return false; }
					if ( F.verified && ! b.verified ) { return false; }
					return ! q || ( lc( b.name ) + ' ' + lc( b.handle ) + ' ' + lc( b.city ) ).indexOf( q ) !== -1;
				} );
				sortBloggers( shown, F.sort );
				$( 'k-count' ).textContent = t( 'availableN', shown.length );
				$( 'k-dot' ).hidden = ! ( F.list || F.gender || F.city || F.verified );
				$( 'k-chips' ).innerHTML = filterChips( F, meta );
				bindChips( $( 'k-chips' ), F, paint );
				var n = Object.keys( picked ).length;
				btn.textContent = t( 'addSelected', n ); btn.disabled = ! n;
				var box = $( 'k-list' );
				if ( ! avail.length ) { box.innerHTML = emptyState( 'bloggers', t( 'allIn' ) ); return; }
				if ( ! shown.length ) { box.innerHTML = '<p class="empty">' + esc( t( 'noResults' ) ) + '</p>'; return; }
				box.innerHTML = '<div class="rows">' + shown.slice( 0, 400 ).map( function ( b ) {
					var on = !! picked[ lc( b.handle ) ];
					var sub = [ '@' + b.handle ]; if ( b.followers ) { sub.push( compact( b.followers ) ); } if ( b.city ) { sub.push( b.city ); }
					return '<div class="row row-tap' + ( on ? ' is-picked' : '' ) + '" data-h="' + esc( b.handle ) + '"><span class="pickbox"></span>' + avatar( b.name, b.handle, false, b.photo ) +
						'<div class="row-main"><b>' + esc( b.name || '@' + b.handle ) + ( b.verified ? ' <span class="ver">✓</span>' : '' ) + '</b><small>' + esc( sub.join( ' · ' ) ) + '</small></div></div>';
				} ).join( '' ) + '</div>';
				qsa( '.row-tap', box ).forEach( function ( el ) {
					el.addEventListener( 'click', function () {
						var hh = el.getAttribute( 'data-h' ), k = lc( hh );
						if ( picked[ k ] ) { delete picked[ k ]; el.classList.remove( 'is-picked' ); } else { picked[ k ] = hh; el.classList.add( 'is-picked' ); }
						var n2 = Object.keys( picked ).length; btn.textContent = t( 'addSelected', n2 ); btn.disabled = ! n2;
					} );
				} );
			}
			$( 'k-q' ).addEventListener( 'input', debounce( function ( e ) { F.q = e.target.value; paint(); }, 120 ) );
			$( 'k-filter' ).addEventListener( 'click', function () { filterSheet( F, meta, { lists: true }, paint ); } );
			$( 'k-sort' ).addEventListener( 'click', function () { choiceSheet( t( 'sortBy' ), bloggerSorts(), F.sort, function ( v ) { F.sort = v; paint(); } ); } );
			$( 'k-selall' ).addEventListener( 'click', function () { shown.forEach( function ( b ) { picked[ lc( b.handle ) ] = b.handle; } ); paint(); } );
			paint();
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Blogger filters (shared) */

	function bloggerSorts() {
		return [ [ 'name', t( 'sName' ) ], [ 'verified', t( 'sVerified' ) ], [ 'least_complete', t( 'sLeastComplete' ) ], [ 'most_complete', t( 'sMostComplete' ) ], [ 'followers_desc', t( 'sFollowersDesc' ) ], [ 'followers_asc', t( 'sFollowersAsc' ) ], [ 'newest', t( 'sNewestAdded' ) ], [ 'popular', t( 'sPopular' ) ], [ 'city', t( 'sCity' ) ] ];
	}
	function sortBloggers( arr, how ) {
		arr.sort( function ( a, b ) {
			switch ( how ) {
				case 'followers_desc': return ( b.followers || 0 ) - ( a.followers || 0 );
				case 'followers_asc': return ( a.followers || 0 ) - ( b.followers || 0 );
				case 'verified': return ( b.verified ? 1 : 0 ) - ( a.verified ? 1 : 0 ) || lc( a.name || a.handle ).localeCompare( lc( b.name || b.handle ) );
				case 'least_complete': return ( a.complete || 0 ) - ( b.complete || 0 ) || lc( a.name || a.handle ).localeCompare( lc( b.name || b.handle ) );
				case 'most_complete': return ( b.complete || 0 ) - ( a.complete || 0 ) || lc( a.name || a.handle ).localeCompare( lc( b.name || b.handle ) );
				case 'newest': return String( b.date || '' ).localeCompare( String( a.date || '' ) );
				case 'popular': return ( b.popularity ? 1 : 0 ) - ( a.popularity ? 1 : 0 ) || ( b.followers || 0 ) - ( a.followers || 0 );
				case 'city': return ( lc( a.city ) || '\uffff' ).localeCompare( lc( b.city ) || '\uffff' ) || lc( a.name ).localeCompare( lc( b.name ) );
				default: return lc( a.name || a.handle ).localeCompare( lc( b.name || b.handle ) );
			}
		} );
		return arr;
	}
	function filterChips( F, meta ) {
		var out = [];
		if ( F.list ) { var l = ( meta.lists || [] ).filter( function ( x ) { return String( x.id ) === String( F.list ); } )[ 0 ]; out.push( [ 'list', l ? l.name : t( 'list' ) ] ); }
		if ( F.gender ) { out.push( [ 'gender', ( meta.genders || {} )[ F.gender ] || F.gender ] ); }
		if ( F.city ) { out.push( [ 'city', F.city ] ); }
		if ( F.collab ) { out.push( [ 'collab', ( meta.collab || {} )[ F.collab ] || F.collab ] ); }
		if ( F.verified ) { out.push( [ 'verified', t( 'verifiedOnly' ) ] ); }
		if ( F.personal ) { out.push( [ 'personal', t( 'personalOnly' ) ] ); }
		( F.missing || [] ).forEach( function ( k ) { out.push( [ 'miss:' + k, t( 'missingX', ( meta.missItems || {} )[ k ] || k ) ] ); } );
		if ( F.state && F.state !== 'all' ) { out.push( [ 'state', F.state === 'blocked' ? t( 'onlyBlocked' ) : t( 'onlyActive' ) ] ); }
		return out.map( function ( o ) { return '<button type="button" class="chip is-on" data-clear="' + o[ 0 ] + '">' + esc( o[ 1 ] ) + ' <span class="x">×</span></button>'; } ).join( '' );
	}
	function bindChips( box, F, cb ) {
		qsa( '[data-clear]', box ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var k = b.getAttribute( 'data-clear' );
				if ( k.indexOf( 'miss:' ) === 0 ) { var mk = k.slice( 5 ); F.missing = ( F.missing || [] ).filter( function ( x ) { return x !== mk; } ); cb(); return; }
				F[ k ] = k === 'verified' ? false : ( k === 'state' ? 'all' : '' ); cb();
			} );
		} );
	}
	function filterSheet( F, meta, opt, cb ) {
		var sel = function ( id, label, items, val ) {
			return '<label class="field"><span>' + esc( label ) + '</span><select class="input" id="' + id + '"><option value="">' + esc( t( 'any' ) ) + '</option>' +
				items.map( function ( it ) { return '<option value="' + esc( it[ 0 ] ) + '"' + ( String( it[ 0 ] ) === String( val ) ? ' selected' : '' ) + '>' + esc( it[ 1 ] ) + '</option>'; } ).join( '' ) + '</select></label>';
		};
		var h = '';
		if ( opt.lists ) { h += sel( 'ff-list', t( 'list' ), ( meta.lists || [] ).map( function ( l ) { return [ l.id, l.name ]; } ), F.list ); }
		h += sel( 'ff-gender', t( 'gender' ), Object.keys( meta.genders || {} ).map( function ( k ) { return [ k, meta.genders[ k ] ]; } ), F.gender );
		h += sel( 'ff-city', t( 'city' ), ( meta.cities || [] ).map( function ( c ) { return [ c, c ]; } ), F.city );
		if ( opt.collab ) { h += sel( 'ff-collab', t( 'openFor' ), Object.keys( meta.collab || {} ).map( function ( k ) { return [ k, meta.collab[ k ] ]; } ), F.collab ); }
		if ( opt.state ) {
			h += '<label class="field"><span>' + esc( t( 'state' ) ) + '</span><div class="seg" id="ff-state">' + [ [ 'all', t( 'all' ) ], [ 'active', t( 'onlyActive' ) ], [ 'blocked', t( 'onlyBlocked' ) ] ].map( function ( o ) {
				return '<button type="button" data-v="' + o[ 0 ] + '" class="' + ( ( F.state || 'all' ) === o[ 0 ] ? 'is-on' : '' ) + '">' + esc( o[ 1 ] ) + '</button>';
			} ).join( '' ) + '</div></label>';
		}
		h += '<label class="check"><input type="checkbox" id="ff-ver"' + ( F.verified ? ' checked' : '' ) + ' /> <span>' + esc( t( 'verifiedOnly' ) ) + '</span></label>';
		if ( opt.state ) { h += '<label class="check"><input type="checkbox" id="ff-pers"' + ( F.personal ? ' checked' : '' ) + ' /> <span>' + esc( t( 'personalOnly' ) ) + '</span></label>'; }
		if ( opt.state && meta.missItems ) {
			var fm = F.missing || [];
			h += '<div class="field"><span>' + esc( t( 'missingInfo' ) ) + '</span><div class="chips" id="ff-miss">' + Object.keys( meta.missItems ).map( function ( k ) {
				return '<button type="button" class="chip' + ( fm.indexOf( k ) !== -1 ? ' is-on' : '' ) + '" data-m="' + esc( k ) + '">' + esc( meta.missItems[ k ] ) + '</button>';
			} ).join( '' ) + '</div><div class="seg" id="ff-mmode" style="margin-top:8px">' + [ [ 'any', t( 'missAny' ) ], [ 'all', t( 'missAll' ) ] ].map( function ( o ) {
				return '<button type="button" data-v="' + o[ 0 ] + '" class="' + ( ( F.missMode || 'any' ) === o[ 0 ] ? 'is-on' : '' ) + '">' + esc( o[ 1 ] ) + '</button>';
			} ).join( '' ) + '</div></div>';
		}
		h += '<div class="btn-row"><button type="button" class="btn btn-ghost" data-clear>' + esc( t( 'clear' ) ) + '</button><button type="button" class="btn" data-apply>' + esc( t( 'apply' ) ) + '</button></div>';
		var s = sheet( { title: t( 'filters' ), html: h } );
		var stateVal = F.state || 'all';
		var missSel = ( F.missing || [] ).slice(), missMode = F.missMode || 'any';
		qsa( '#ff-miss [data-m]', s.el ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var k = b.getAttribute( 'data-m' ), i = missSel.indexOf( k );
				if ( i === -1 ) { missSel.push( k ); } else { missSel.splice( i, 1 ); }
				b.classList.toggle( 'is-on', i === -1 );
			} );
		} );
		qsa( '#ff-mmode button', s.el ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { missMode = b.getAttribute( 'data-v' ); qsa( '#ff-mmode button', s.el ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } ); } );
		} );
		qsa( '#ff-state button', s.el ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { stateVal = b.getAttribute( 'data-v' ); qsa( '#ff-state button', s.el ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } ); } );
		} );
		qs( '[data-apply]', s.el ).addEventListener( 'click', function () {
			if ( opt.lists ) { F.list = $( 'ff-list' ).value; }
			F.gender = $( 'ff-gender' ).value; F.city = $( 'ff-city' ).value;
			if ( opt.collab ) { F.collab = $( 'ff-collab' ).value; }
			if ( opt.state ) { F.state = stateVal; }
			F.verified = $( 'ff-ver' ).checked;
			if ( $( 'ff-pers' ) ) { F.personal = $( 'ff-pers' ).checked; }
			if ( $( 'ff-miss' ) ) { F.missing = missSel; F.missMode = missMode; }
			s.close(); cb();
		} );
		qs( '[data-clear]', s.el ).addEventListener( 'click', function () {
			if ( opt.lists ) { F.list = ''; }
			F.gender = ''; F.city = ''; F.collab = ''; F.verified = false; F.personal = false; F.state = 'all'; F.missing = []; F.missMode = 'any';
			s.close(); cb();
		} );
	}

	/* ================================================================ Bloggers */

	var UB = { q: '', gender: '', city: '', collab: '', verified: false, state: 'all', sort: sget( 'blogSort', 'name' ) };
	var UL = {};

	function vBloggers( ctx ) {
		if ( ctx.query.q ) { UB.q = ctx.query.q; }
		return bloggerList( ctx, UB, 0, t( 'bloggers' ), true );
	}
	function vListBloggers( ctx ) {
		var lid = parseInt( ctx.params[ 0 ], 10 );
		UL[ lid ] = UL[ lid ] || { q: '', gender: '', city: '', collab: '', verified: false, state: 'all', sort: 'name' };
		return getLists().then( function ( ls ) {
			var l = ls.filter( function ( x ) { return x.id === lid; } )[ 0 ];
			return bloggerList( ctx, UL[ lid ], lid, l ? l.name : t( 'lists' ), false );
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	function bloggerList( ctx, F, listId, title, isRoot ) {
		setBar( { title: title, large: isRoot, back: ! isRoot, actions: isRoot ? [ { icon: 'refresh', label: t( 'refresh' ), onClick: function () { refreshAll(); } } ] : [] } );
		view.innerHTML = ( isRoot ? '<h1 class="h1">' + esc( title ) + '</h1>' : '<h2 class="h2" style="margin-bottom:14px">' + esc( title ) + '</h2>' ) + skeleton( 'row', 8 );
		fabBtn( t( 'addBlogger' ), function () { go( '/bloggers/new' + ( listId ? '?list=' + listId : '' ) ); } );
		return Promise.all( [ getLibrary( ctx.force ), getMeta( ctx.force ) ] ).then( function ( r ) {
			if ( ctx.stale() ) { return; }
			var lib = r[ 0 ], meta = r[ 1 ], limit = 150;
			var base = listId ? lib.filter( function ( b ) { return ( b.lists || [] ).indexOf( listId ) !== -1; } ) : lib;
			view.innerHTML = ( isRoot ? '<h1 class="h1">' + esc( title ) + '</h1>' : '<h2 class="h2" style="margin-bottom:14px">' + esc( title ) + '</h2>' ) +
				'<div class="toolbar"><div class="search">' + searchBox( 'b-q', t( 'searchBloggers' ), F.q ).replace( /^<label class="search">|<\/label>$/g, '' ) + '</div>' +
				'<button type="button" class="iconbtn" id="b-filter">' + icon( 'filter' ) + '<span class="dot" id="b-dot" hidden></span></button>' +
				'<button type="button" class="iconbtn" id="b-sort">' + icon( 'sort' ) + '</button></div>' +
				'<div class="chipbar" id="b-chips"></div><div class="countline"><span id="b-count"></span></div><div id="b-list"></div>';
			function paint() {
				var q = lc( F.q ).replace( /^@/, '' );
				var rows = base.filter( function ( b ) {
					if ( F.gender && b.gender !== F.gender ) { return false; }
					if ( F.city && b.city !== F.city ) { return false; }
					if ( F.collab && ( b.collab || '' ).indexOf( ',' + F.collab + ',' ) === -1 ) { return false; }
					if ( F.verified && ! b.verified ) { return false; }
					if ( F.personal && b.igs !== 'personal' ) { return false; }
					if ( F.missing && F.missing.length ) {
						var bm = b.miss || [];
						var hit = F.missing.filter( function ( k ) { return bm.indexOf( k ) !== -1; } ).length;
						if ( F.missMode === 'all' ? hit < F.missing.length : hit === 0 ) { return false; }
					}
					if ( F.state === 'blocked' && ! b.blocked ) { return false; }
					if ( F.state === 'active' && b.blocked ) { return false; }
					return ! q || ( lc( b.name ) + ' ' + lc( b.handle ) + ' ' + lc( b.city ) ).indexOf( q ) !== -1;
				} );
				sortBloggers( rows, F.sort );
				$( 'b-count' ).textContent = t( 'bloggersCount', num( rows.length ) );
				$( 'b-dot' ).hidden = ! ( F.gender || F.city || F.collab || F.verified || F.personal || ( F.missing && F.missing.length ) || ( F.state && F.state !== 'all' ) );
				$( 'b-chips' ).innerHTML = filterChips( F, meta );
				bindChips( $( 'b-chips' ), F, paint );
				var box = $( 'b-list' );
				if ( ! base.length ) { box.innerHTML = emptyState( 'bloggers', t( 'none' ) ); return; }
				if ( ! rows.length ) { box.innerHTML = '<p class="empty">' + esc( t( 'noResults' ) ) + '</p>'; return; }
				box.innerHTML = '<div class="rows">' + rows.slice( 0, limit ).map( function ( b ) {
					var sub = [ '@' + b.handle ]; if ( b.city ) { sub.push( b.city ); }
					var side = b.blocked ? '<span class="tag red">' + esc( t( 'blocked' ) ) + '</span>' : ( b.followers ? '<b>' + esc( compact( b.followers ) ) + '</b>' : '' );
					if ( b.popularity && ! b.blocked ) { side += '<small>' + esc( b.popularity ) + '</small>'; }
					return '<div class="row row-tap" data-id="' + b.id + '">' + avatar( b.name, b.handle, false, b.photo ) +
						'<div class="row-main"><b>' + esc( b.name || '@' + b.handle ) + ( b.verified ? ' <span class="ver">✓</span>' : '' ) + '</b><small>' + esc( sub.join( ' · ' ) ) + '</small></div><div class="row-side">' + side + '</div></div>';
				} ).join( '' ) + '</div>' + ( rows.length > limit ? '<p class="center"><button type="button" class="btn btn-sm btn-ghost" id="b-more">+ ' + num( rows.length - limit ) + '</button></p>' : '' );
				qsa( '.row-tap', box ).forEach( function ( el ) { el.addEventListener( 'click', function () { go( '/b/' + el.getAttribute( 'data-id' ) ); } ); } );
				var more = $( 'b-more' ); if ( more ) { more.addEventListener( 'click', function () { limit += 300; paint(); } ); }
			}
			$( 'b-q' ).addEventListener( 'input', debounce( function ( e ) { F.q = e.target.value; limit = 150; paint(); }, 120 ) );
			$( 'b-filter' ).addEventListener( 'click', function () { filterSheet( F, meta, { collab: true, state: true }, function () { limit = 150; paint(); } ); } );
			$( 'b-sort' ).addEventListener( 'click', function () {
				choiceSheet( t( 'sortBy' ), bloggerSorts(), F.sort, function ( v ) { F.sort = v; if ( isRoot ) { sset( 'blogSort', v ); } paint(); } );
			} );
			paint();
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Blogger detail */

	function vBlogger( ctx ) {
		var id = parseInt( ctx.params[ 0 ], 10 );
		setBar( { title: '', back: true } );
		view.innerHTML = skeleton( 'hero', 5 );
		return Promise.all( [ api( '/bloggers/' + id ), getMeta() ] ).then( function ( r ) {
			if ( ctx.stale() ) { return; }
			var b = r[ 0 ].blogger, meta = r[ 1 ];
			var name = ( ( b.first || '' ) + ' ' + ( b.last || '' ) ).trim();
			setBar( { title: name || '@' + b.handle, back: true, actions: [ { icon: 'edit', label: t( 'edit' ), onClick: function () { go( '/b/' + id + '/edit' ); } } ] } );
			var wa = String( b.whatsapp || b.phone || '' ).replace( /[^\d]/g, '' );
			var tel = String( b.phone || '' ).replace( /[^\d+]/g, '' );
			var ins = b.insights || null;
			var listNames = ( meta.lists || [] ).filter( function ( l ) { return ( b.lists || [] ).indexOf( l.id ) !== -1; } ).map( function ( l ) { return l.name; } );
			var collab = ( b.collab || [] ).map( function ( k ) { return ( meta.collab || {} )[ k ] || k; } );

			var h = '<div class="hero">' + ( b.photo ? '<button type="button" class="hero-photo" id="bd-photo">' + avatar( name, b.handle, true, b.photo ) + '</button>' : avatar( name, b.handle, true ) ) + '<h2 class="h2">' + esc( name || '@' + b.handle ) + '</h2>' +
				'<a class="handle" href="' + esc( b.url || igUrl( b.handle ) ) + '" target="_blank" rel="noopener">@' + esc( b.handle ) + '</a><div class="meta">' +
				( b.verified ? '<span class="pill live">' + esc( t( 'verified' ) ) + '</span>' : '' ) +
				( b.blocked ? '<span class="pill" style="color:var(--red)">' + esc( t( 'blocked' ) ) + '</span>' : '' ) +
				( ins && ins.label && ins.label.label ? '<span class="pill nodot">' + esc( ins.label.label ) + '</span>' : '' ) + '</div></div>';
			h += '<div class="quick">' +
				'<a class="qa" href="' + esc( b.url || igUrl( b.handle ) ) + '" target="_blank" rel="noopener">' + icon( 'ig' ) + 'Instagram</a>' +
				( wa ? '<a class="qa" href="https://wa.me/' + esc( wa ) + '" target="_blank" rel="noopener">' + icon( 'chat' ) + esc( t( 'whatsapp' ) ) + '</a>' : '<button type="button" class="qa" disabled>' + icon( 'chat' ) + esc( t( 'whatsapp' ) ) + '</button>' ) +
				( tel ? '<a class="qa" href="tel:' + esc( tel ) + '">' + icon( 'phone' ) + esc( t( 'call' ) ) + '</a>' : '<button type="button" class="qa" disabled>' + icon( 'phone' ) + esc( t( 'call' ) ) + '</button>' ) +
				'<button type="button" class="qa" id="bd-edit">' + icon( 'edit' ) + esc( t( 'edit' ) ) + '</button></div>';
			h += '<div class="tiles t3"><div class="tile"><b>' + ( b.followers ? esc( compact( b.followers ) ) : '—' ) + '</b><span>' + esc( t( 'followers' ) ) + '</span></div>' +
				'<div class="tile"><b>' + ( b.reach ? esc( compact( b.reach ) ) : '—' ) + '</b><span>' + esc( t( 'reach' ) ) + '</span></div>' +
				'<div class="tile green"><b>' + ( ins && ( ins.confirmed + ins.declined ) ? ins.acceptance_rate + '%' : '—' ) + '</b><span>' + esc( t( 'acceptance' ) ) + '</span></div></div>';
			var ig = b.ig;
			if ( ig && ig.ready ) {
				var igTxt = ig.status === 'ok'
					? t( 'igOk', ig.ago ) + ( ig.engagement !== null ? ' · ' + t( 'igEng', ig.engagement ) : '' ) + ( ig.posts ? ' · ' + t( 'igPosts', num( ig.posts ) ) : '' )
					: ( ig.status === 'personal' ? t( 'igPersonal' ) : t( 'igPending' ) );
				h += '<div class="banner ' + ( ig.status === 'personal' ? 'warn' : 'info' ) + '" style="margin-top:0"><p>' + esc( igTxt ) + '</p>' + ( ig.switch_wa ? '<a class="btn btn-sm" href="' + esc( ig.switch_wa ) + '" target="_blank" rel="noopener">' + esc( t( 'askSwitch' ) ) + '</a> ' : '' ) + '<button type="button" class="btn btn-sm btn-ghost" id="bd-igsync">' + esc( t( 'igSync' ) ) + '</button></div>';
			}
			var rel = b.reliability;
			if ( rel && rel.rate !== null && rel.rate !== undefined ) {
				h += '<div class="banner info" style="margin-top:0"><p><b>' + esc( t( 'reliability' ) ) + ' ' + rel.rate + '%</b> · ' + esc( t( 'relDetail', rel.attended, rel.expected ) ) + '</p></div>';
			}
			h += '<div class="section"><h3>' + esc( t( 'profile' ) ) + '</h3></div><div class="kvs">' +
				( kv( t( 'gender' ), ( meta.genders || {} )[ b.gender ] || '' ) + kv( t( 'categories' ), ( b.tags || [] ).join( ', ' ) ) +
				kv( t( 'location' ), b.city || '' ) + kv( t( 'openFor' ), collab.join( ', ' ) ) + kv( t( 'lists' ), listNames.join( ', ' ) ) || kv( t( 'profile' ), '—' ) ) + '</div>';
			var priv = kv( t( 'email' ), b.email ) + kv( t( 'birthday' ), b.birthday ) + kv( t( 'phone' ), b.phone ) + kv( t( 'whatsapp' ), b.whatsapp ) + kv( t( 'source' ), b.source );
			if ( priv ) { h += '<div class="section"><h3>' + esc( t( 'private' ) ) + '</h3></div><div class="kvs">' + priv + '</div>'; }
			if ( ins && ins.included ) {
				h += '<div class="section"><h3>' + esc( t( 'insights' ) ) + '</h3></div><div class="kvs">' + kv( t( 'included' ), ins.included ) + kv( t( 'accepted' ), ins.confirmed ) + kv( t( 'rejected' ), ins.declined ) + kv( t( 'rejection' ), ins.rejection_rate + '%' ) + '</div>';
			}
			if ( b.campaigns && b.campaigns.length ) {
				h += '<div class="section"><h3>' + esc( t( 'campaigns' ) ) + '</h3><span class="small">' + b.campaigns.length + '</span></div><div class="rows">' +
					b.campaigns.map( function ( cm ) {
						return '<div class="row row-tap" data-camp="' + cm.id + '"><div class="row-main"><b>' + esc( cm.title ) + '</b><small>' + esc( cm.date ) + ( cm.live ? '' : ' · ' + esc( t( 'draft' ) ) ) + '</small></div><div class="row-side">' + statusBadge( cm.status ) + ( cm.status === 'confirmed' ? '<small>' + ( 1 + cm.guests ) + ' ' + esc( t( 'people' ) ) + '</small>' : '' ) + ( cm.event ? '<small><span class="stg stg-' + esc( cm.stage ) + '">' + esc( cm.event ) + '</span></small>' : '' ) + '</div></div>';
					} ).join( '' ) + '</div>';
			}
			var vm = { manual: t( 'vmManual' ), bio: t( 'vmBio' ), meta: t( 'vmMeta' ) };
			h += '<div class="section"><h3>' + esc( t( 'verification' ) ) + '</h3></div><div class="card vcard">' +
				( b.verified
					? '<div class="vrow"><span class="vtick-big">✓</span><div><b>' + esc( t( 'verified' ) ) + '</b><small>' + esc( vm[ b.verified_method ] || vm.manual ) + '</small></div></div>' +
						'<button type="button" class="btn btn-sm btn-ghost" id="bd-unverify">' + esc( t( 'unverify' ) ) + '</button>'
					: '<div class="vrow"><span class="vtick-big is-off">✓</span><div><b>' + esc( t( 'notVerified' ) ) + '</b><small>' + esc( b.verify_code ? t( 'vCodeHint', b.verify_code ) : t( 'vManualHint' ) ) + '</small></div></div>' +
						'<button type="button" class="btn btn-sm" id="bd-verify">' + esc( t( 'markVerified' ) ) + '</button>' ) +
				'</div>';
			h += '<div class="section"><h3>' + esc( t( 'dangerZone' ) ) + '</h3></div><div class="sheet-actions">' +
				( b.blocked ? '<button type="button" class="btn btn-ghost" id="bd-unblock">' + esc( t( 'unblock' ) ) + '</button>' : '<button type="button" class="btn btn-ghost" id="bd-block">' + esc( t( 'block' ) ) + '</button>' ) +
				'<button type="button" class="btn btn-danger" id="bd-del">' + esc( t( 'delete' ) ) + '</button>' +
				'<button type="button" class="btn btn-danger" id="bd-delblock">' + esc( t( 'deleteBlock' ) ) + '</button></div>';
			view.innerHTML = h;

			function act( op, q, label, leave ) {
				var p = q ? confirmSheet( q, label, true ) : Promise.resolve( true );
				p.then( function ( ok ) {
					if ( ! ok ) { return; }
					post( '/bloggers/' + id + '/action', { op: op } ).then( function () {
						drop( 'library', 'lists', 'camp', 'campaigns', 'insights' );
						toast( label );
						if ( leave ) { goBack(); } else { render( true ); }
					} ).catch( actErr );
				} );
			}
			$( 'bd-edit' ).addEventListener( 'click', function () { go( '/b/' + id + '/edit' ); } );
			qsa( '[data-camp]', view ).forEach( function ( el ) { el.addEventListener( 'click', function () { go( '/c/' + el.getAttribute( 'data-camp' ) ); } ); } );
			var igb = $( 'bd-igsync' );
			if ( igb ) {
				igb.addEventListener( 'click', function () {
					igb.disabled = true; igb.textContent = t( 'saving' );
					post( '/bloggers/' + id + '/action', { op: 'ig_sync' } ).then( function ( r ) {
						var m = { ok: t( 'igSynced' ), personal: t( 'igPersonal' ), rate: t( 'igRate' ), token: t( 'igToken' ) };
						toast( m[ r.result ] || t( 'offline' ), r.result !== 'ok' );
						drop( 'library', 'camp', 'campaigns' );
						render( true );
					} ).catch( function ( e ) { igb.disabled = false; igb.textContent = t( 'igSync' ); actErr( e ); } );
				} );
			}
			var bph = $( 'bd-photo' );
			if ( bph ) { bph.addEventListener( 'click', function () { sheet( { html: '<img src="' + esc( b.photo ) + '" alt="" style="width:100%;border-radius:18px;display:block" />' } ); } ); }
			var bv = $( 'bd-verify' ), buv = $( 'bd-unverify' );
			if ( bv ) { bv.addEventListener( 'click', function () { act( 'verify', '', t( 'verifiedDone' ) ); } ); }
			if ( buv ) { buv.addEventListener( 'click', function () { act( 'unverify', t( 'unverifyQ' ), t( 'unverify' ) ); } ); }
			var bl = $( 'bd-block' ), ub = $( 'bd-unblock' );
			if ( bl ) { bl.addEventListener( 'click', function () { act( 'block', t( 'blockQ' ), t( 'block' ) ); } ); }
			if ( ub ) { ub.addEventListener( 'click', function () { act( 'unblock', '', t( 'unblock' ) ); } ); }
			$( 'bd-del' ).addEventListener( 'click', function () { act( 'delete', t( 'deleteQ' ), t( 'delete' ), true ); } );
			$( 'bd-delblock' ).addEventListener( 'click', function () { act( 'delete_block', t( 'deleteBlockQ' ), t( 'deleteBlock' ), true ); } );
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Blogger form */

	function vBloggerForm( ctx ) {
		var id = ctx.params[ 0 ] ? parseInt( ctx.params[ 0 ], 10 ) : 0;
		setBar( { title: id ? t( 'editBlogger' ) : t( 'addBlogger' ), back: true } );
		view.innerHTML = skeleton( 'row', 7 );
		return Promise.all( [ getMeta(), id ? api( '/bloggers/' + id ) : Promise.resolve( null ) ] ).then( function ( r ) {
			if ( ctx.stale() ) { return; }
			var meta = r[ 0 ], b = r[ 1 ] ? r[ 1 ].blogger : { tags: [], lists: ctx.query.list ? [ parseInt( ctx.query.list, 10 ) ] : [], collab: [] };
			var sel = { cats: {}, collab: {}, lists: {} };
			( b.tags || [] ).forEach( function ( x ) { sel.cats[ x ] = 1; } );
			( b.collab || [] ).forEach( function ( x ) { sel.collab[ x ] = 1; } );
			( b.lists || [] ).forEach( function ( x ) { sel.lists[ x ] = 1; } );
			function inp( idd, label, val, type, extra ) {
				return '<label class="field"><span>' + esc( label ) + '</span><input class="input" id="' + idd + '" type="' + ( type || 'text' ) + '" value="' + esc( val == null ? '' : val ) + '"' + ( extra || '' ) + ' /></label>';
			}
			function chipGroup( idd, items, map ) {
				return '<div class="chips" id="' + idd + '">' + items.map( function ( it ) {
					return '<button type="button" class="chip' + ( map[ it[ 0 ] ] ? ' is-on' : '' ) + '" data-v="' + esc( it[ 0 ] ) + '">' + esc( it[ 1 ] ) + '</button>';
				} ).join( '' ) + '</div>';
			}
			var bname = ( ( b.first || '' ) + ' ' + ( b.last || '' ) ).trim();
			var h = '<form id="bf" novalidate><div class="section"><h3>' + esc( t( 'photo' ) ) + '</h3></div><div class="card"><div class="photo-pick">' +
				'<span id="bf-photo-prev">' + avatar( bname, b.handle, true, b.photo ) + '</span><div>' +
				'<label class="btn btn-sm btn-ghost" style="cursor:pointer">' + esc( t( 'choosePhoto' ) ) + '<input type="file" accept="image/*" id="bf-photo" hidden /></label> ' +
				'<button type="button" class="linkbtn" id="bf-photo-rm"' + ( b.photo ? '' : ' hidden' ) + '>' + esc( t( 'removePhoto' ) ) + '</button></div></div></div>' +
				'<div class="section"><h3>' + esc( t( 'profile' ) ) + '</h3></div><div class="card">' +
				'<div class="btn-row">' + inp( 'bf-first', t( 'firstName' ), b.first, 'text', ' autocomplete="off"' ) + inp( 'bf-last', t( 'lastName' ), b.last, 'text', ' autocomplete="off"' ) + '</div>' +
				inp( 'bf-ig', t( 'igField' ) + ' *', b.handle, 'text', ' autocapitalize="none" spellcheck="false" autocomplete="off"' ) +
				inp( 'bf-followers', t( 'followers' ), b.followers || '', 'number', ' inputmode="numeric" min="0"' ) +
				'<label class="field"><span>' + esc( t( 'gender' ) ) + '</span><select class="input" id="bf-gender">' + ( b.gender && ( meta.genders || {} )[ b.gender ] ? '' : '<option value="">—</option>' ) +
				Object.keys( meta.genders || {} ).map( function ( k ) { return '<option value="' + esc( k ) + '"' + ( b.gender === k ? ' selected' : '' ) + '>' + esc( meta.genders[ k ] ) + '</option>'; } ).join( '' ) + '</select></label>' +
				'<div class="btn-row"><label class="field"><span>' + esc( t( 'city' ) ) + '</span><select class="input" id="bf-city"><option value="">—</option>' +
				( meta.cities || [] ).map( function ( c ) { return '<option value="' + esc( c ) + '"' + ( b.city === c ? ' selected' : '' ) + '>' + esc( c ) + '</option>'; } ).join( '' ) + '</select></label>' +
				'</div></div>';
			h += '<div class="section"><h3>' + esc( t( 'categories' ) ) + '</h3></div><div class="card">' + chipGroup( 'bf-cats', ( meta.categories || [] ).map( function ( c ) { return [ c, c ]; } ), sel.cats ) + '</div>';
			h += '<div class="section"><h3>' + esc( t( 'openFor' ) ) + '</h3></div><div class="card">' + chipGroup( 'bf-collab', Object.keys( meta.collab || {} ).map( function ( k ) { return [ k, meta.collab[ k ] ]; } ), sel.collab ) + '</div>';
			h += '<div class="section"><h3>' + esc( t( 'lists' ) ) + '</h3></div><div class="card">' + chipGroup( 'bf-lists', ( meta.lists || [] ).map( function ( l ) { return [ l.id, l.name ]; } ), sel.lists ) +
				'<div style="margin-top:12px">' + inp( 'bf-newlist', t( 'newList' ), '', 'text' ) + '</div></div>';
			h += '<div class="section"><h3>' + esc( t( 'private' ) ) + '</h3></div><div class="card">' +
				inp( 'bf-email', t( 'email' ), b.email, 'email', ' autocapitalize="none" autocomplete="off"' ) + inp( 'bf-birthday', t( 'birthday' ), b.birthday, 'date' ) + inp( 'bf-phone', t( 'phone' ), b.phone, 'tel' ) + inp( 'bf-whatsapp', t( 'whatsapp' ), b.whatsapp, 'tel' ) + '</div>';
			h += '<p class="error" id="bf-err"></p></form>';
			view.innerHTML = h;

			var photo = { file: null, remove: false };
			$( 'bf-photo' ).addEventListener( 'change', function ( e ) {
				var f = e.target.files && e.target.files[ 0 ];
				if ( ! f ) { return; }
				shrinkImage( f ).then( function ( blob ) {
					photo.file = blob; photo.remove = false;
					$( 'bf-photo-prev' ).innerHTML = avatar( '', '', true, URL.createObjectURL( blob ) );
					$( 'bf-photo-rm' ).hidden = false;
				} );
			} );
			$( 'bf-photo-rm' ).addEventListener( 'click', function () {
				photo.file = null; photo.remove = true;
				$( 'bf-photo-prev' ).innerHTML = avatar( bname, b.handle, true );
				$( 'bf-photo-rm' ).hidden = true;
			} );
			[ [ 'bf-cats', 'cats' ], [ 'bf-collab', 'collab' ], [ 'bf-lists', 'lists' ] ].forEach( function ( g ) {
				qsa( '#' + g[ 0 ] + ' .chip' ).forEach( function ( ch ) {
					ch.addEventListener( 'click', function () {
						var v = ch.getAttribute( 'data-v' );
						if ( sel[ g[ 1 ] ][ v ] ) { delete sel[ g[ 1 ] ][ v ]; ch.classList.remove( 'is-on' ); } else { sel[ g[ 1 ] ][ v ] = 1; ch.classList.add( 'is-on' ); }
					} );
				} );
			} );
			$( 'bf' ).addEventListener( 'submit', function ( e ) { e.preventDefault(); } );
			var btn = saveBar( t( 'save' ), function () {
				var ig = $( 'bf-ig' ).value.trim();
				if ( ! ig ) { $( 'bf-ig' ).classList.add( 'is-invalid' ); $( 'bf-ig' ).focus(); toast( t( 'igRequired' ), true ); return; }
				var em = $( 'bf-email' ).value.trim();
				if ( em && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( em ) ) { $( 'bf-email' ).classList.add( 'is-invalid' ); $( 'bf-email' ).focus(); toast( t( 'emailBad' ), true ); return; }
				var body = {
					id: id || 0, first: $( 'bf-first' ).value, last: $( 'bf-last' ).value, ig: ig,
					followers: parseInt( $( 'bf-followers' ).value || '0', 10 ) || 0, gender: $( 'bf-gender' ).value,
					city: $( 'bf-city' ).value, email: $( 'bf-email' ).value.trim(), birthday: $( 'bf-birthday' ).value,
					phone: $( 'bf-phone' ).value, whatsapp: $( 'bf-whatsapp' ).value,
					tags: Object.keys( sel.cats ), collab: Object.keys( sel.collab ),
					lists: Object.keys( sel.lists ).map( function ( x ) { return parseInt( x, 10 ); } ), new_list: $( 'bf-newlist' ).value.trim()
				};
				btn.disabled = true; btn.textContent = t( 'saving' );
				post( '/bloggers', body ).then( function ( res ) {
					if ( photo.file ) {
						var fd = new FormData();
						fd.append( 'file', photo.file, 'photo.jpg' );
						return api( '/bloggers/' + res.id + '/photo', { method: 'POST', body: fd } ).catch( function ( e ) { toast( ( e && e.message ) || t( 'photoFailed' ), true ); } ).then( function () { return res; } );
					}
					if ( photo.remove && res.id ) {
						return post( '/bloggers/' + res.id + '/photo', { remove: true } ).catch( function () {} ).then( function () { return res; } );
					}
					return res;
				} ).then( function ( res ) {
					drop( 'library', 'lists', 'camp', 'campaigns', 'insights' );
					if ( body.new_list ) { drop( 'meta' ); }
					toast( t( 'save' ) + ' ✓' );
					if ( id ) { goBack(); } else { go( '/b/' + res.id, true ); }
				} ).catch( function ( e ) {
					btn.disabled = false; btn.textContent = t( 'save' );
					var d = e && e.data;
					if ( d && d.code === 'cp_duplicate' && d.data && d.data.existing ) {
						$( 'bf-ig' ).classList.add( 'is-invalid' );
						$( 'bf-err' ).textContent = e.message;
						confirmSheet( e.message, t( 'openExisting' ) ).then( function ( ok ) { if ( ok ) { go( '/b/' + d.data.existing ); } } );
						return;
					}
					actErr( e ); $( 'bf-err' ).textContent = ( e && e.message ) || '';
				} );
			} );
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Lists */

	function vLists( ctx ) {
		setBar( { title: t( 'lists' ), large: true, actions: [ { icon: 'refresh', label: t( 'refresh' ), onClick: function () { refreshAll(); } } ] } );
		view.innerHTML = '<h1 class="h1">' + esc( t( 'lists' ) ) + '</h1>' + skeleton( 'row', 6 );
		return getLists( ctx.force ).then( function ( ls ) {
			if ( ctx.stale() ) { return; }
			var h = '<h1 class="h1">' + esc( t( 'lists' ) ) + '</h1>';
			if ( ! ls.length ) { view.innerHTML = h + emptyState( 'lists', t( 'none' ) ); return; }
			ls = ls.slice().sort( function ( a, b ) { return lc( a.name ).localeCompare( lc( b.name ) ); } );
			h += '<div class="rows">' + ls.map( function ( l ) {
				return '<div class="row row-tap" data-id="' + l.id + '"><span class="avatar" style="background:var(--s3);color:var(--text)">' + icon( 'lists' ).replace( '<svg ', '<svg width="20" height="20" ' ) + '</span><div class="row-main"><b>' + esc( l.name ) + '</b><small>' + esc( t( 'bloggersCount', num( l.count ) ) ) + '</small></div><span class="chev faint">›</span></div>';
			} ).join( '' ) + '</div>';
			view.innerHTML = h;
			qsa( '.row-tap', view ).forEach( function ( el ) { el.addEventListener( 'click', function () { go( '/lists/' + el.getAttribute( 'data-id' ) ); } ); } );
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Insights */

	function vInsights( ctx ) {
		var UI = sget( 'cp_ins_period', '90' );
		setBar( { title: t( 'insights' ), large: true, actions: [ { icon: 'refresh', label: t( 'refresh' ), onClick: function () { S.insights = null; render( true ); } } ] } );
		view.innerHTML = '<h1 class="h1">' + esc( t( 'insights' ) ) + '</h1>' + skeleton( 'hero', 5 );
		S.insights = S.insights || {};
		var load = S.insights[ UI ] && ! ctx.force ? Promise.resolve( S.insights[ UI ] ) : api( '/insights?period=' + UI ).then( function ( g ) { S.insights = S.insights || {}; S.insights[ UI ] = g; return g; } );
		return load.then( function ( g ) {
			if ( ctx.stale() ) { return; }
			var k = g.kpi, d = g.deltas || {};
			function pct( v ) { return v === null || v === undefined ? '—' : v + '%'; }
			function dl( v, suf ) {
				if ( v === null || v === undefined || v === 0 ) { return ''; }
				return '<em class="delta ' + ( v > 0 ? 'up' : 'down' ) + '">' + ( v > 0 ? '▲' : '▼' ) + ' ' + Math.abs( v ) + ( suf || '' ) + '</em>';
			}
			var periods = [ [ '30', t( 'p30' ) ], [ '90', t( 'p90' ) ], [ '365', t( 'p365' ) ], [ '0', t( 'pAll' ) ] ];
			var h = '<h1 class="h1">' + esc( t( 'insights' ) ) + '</h1>' +
				'<div class="seg" id="ins-seg">' + periods.map( function ( p ) { return '<button type="button" data-p="' + p[ 0 ] + '" class="' + ( p[ 0 ] === UI ? 'is-on' : '' ) + '">' + esc( p[ 1 ] ) + '</button>'; } ).join( '' ) + '</div>';

			h += '<div class="tiles t2 kpis">' +
				'<div class="tile"><b>' + num( k.campaigns ) + dl( d.campaigns ) + '</b><span>' + esc( t( 'campaigns' ) ) + '</span></div>' +
				'<div class="tile"><b>' + num( k.invited ) + dl( d.invited ) + '</b><span>' + esc( t( 'proposed' ) ) + '</span></div>' +
				'<div class="tile green"><b>' + pct( k.acceptance ) + dl( d.acceptance, ' ' + t( 'pts' ) ) + '</b><span>' + esc( t( 'acceptRate' ) ) + '</span></div>' +
				'<div class="tile"><b>' + pct( k.response ) + dl( d.response, ' ' + t( 'pts' ) ) + '</b><span>' + esc( t( 'responseRate' ) ) + '</span></div>' +
				'<div class="tile dark"><b>' + num( k.people ) + dl( d.people ) + '</b><span>' + esc( t( 'attending' ) ) + '</span></div>' +
				'<div class="tile"><b>' + num( g.library.total ) + ( g.period && g.library['new'] ? '<em class="delta up">+' + g.library['new'] + '</em>' : '' ) + '</b><span>' + esc( t( 'libVerified', g.library.verified_pct ) ) + '</span></div>' +
				'</div>';

			// 12-month trend.
			var mx = 1;
			g.trend.forEach( function ( x ) { mx = Math.max( mx, x.confirmed + x.declined + x.pending ); } );
			h += '<div class="section"><h3>' + esc( t( 'trend12' ) ) + '</h3></div><div class="card"><div class="trend">' + g.trend.map( function ( x ) {
				var tot = x.confirmed + x.declined + x.pending;
				return '<div class="tcol"><div class="tstack" style="height:' + Math.round( tot / mx * 100 ) + '%"><i class="c" style="flex:' + x.confirmed + '"></i><i class="d" style="flex:' + x.declined + '"></i><i class="p" style="flex:' + x.pending + '"></i></div><span>' + esc( x.month ) + '</span></div>';
			} ).join( '' ) + '</div><div class="legend"><i class="c"></i>' + esc( t( 'confirmed' ) ) + ' <i class="d"></i>' + esc( t( 'declined' ) ) + ' <i class="p"></i>' + esc( t( 'waiting' ) ) + '</div></div>';

			// What clients pick.
			var dims = [ [ 'category', t( 'byCategory' ) ], [ 'tier', t( 'byTier' ) ], [ 'gender', t( 'byGender' ) ], [ 'city', t( 'byCity' ) ] ];
			h += '<div class="section"><h3>' + esc( t( 'clientsPick' ) ) + '</h3></div><div class="seg seg-sm" id="ins-dim">' + dims.map( function ( x, i ) { return '<button type="button" data-d="' + x[ 0 ] + '" class="' + ( i === 0 ? 'is-on' : '' ) + '">' + esc( x[ 1 ] ) + '</button>'; } ).join( '' ) + '</div><div class="card" id="ins-dimbox"></div>';

			// Leaderboards.
			function board( title, rows, sub ) {
				var s2 = '<div class="section"><h3>' + esc( title ) + '</h3>' + ( sub ? '<span class="small">' + esc( sub ) + '</span>' : '' ) + '</div>';
				if ( ! rows || ! rows.length ) { return s2 + '<p class="muted" style="margin:0 4px">' + esc( t( 'noData' ) ) + '</p>'; }
				return s2 + '<div class="rows">' + rows.map( function ( p, i ) {
					return '<div class="row' + ( p.id ? ' row-tap' : '' ) + '"' + ( p.id ? ' data-id="' + p.id + '"' : '' ) + '><span class="rank">' + ( i + 1 ) + '</span>' + avatar( p.name, p.handle, false, p.photo ) +
						'<div class="row-main"><b>' + esc( p.name || '@' + p.handle ) + ( p.verified ? ' <span class="vtick">✓</span>' : '' ) + '</b><small>@' + esc( p.handle ) + '</small></div><div class="row-side"><b>' + esc( String( p.value ) ) + '</b>' + ( p.extra ? '<small>' + esc( p.extra ) + '</small>' : '' ) + '</div></div>';
				} ).join( '' ) + '</div>';
			}
			h += board( t( 'mostSelected' ), g.boards.selected ) + board( t( 'bestAcceptance' ), g.boards.acceptance, t( 'min3' ) ) + board( t( 'mostProposed' ), g.boards.invited ) + board( t( 'mostRejected' ), g.boards.declined );
			if ( g.boards.reliable && g.boards.reliable.length ) { h += board( t( 'mostReliable' ), g.boards.reliable, t( 'atriumCheckins' ) ); }

			// Needs attention.
			var at = g.attention;
			h += '<div class="section"><h3>' + esc( t( 'needsAttention' ) ) + '</h3></div><div class="kvs">' +
				kv( t( 'neverSelected' ), String( at.never_selected ) ) + kv( t( 'oftenDeclined' ), String( at.often_declined ) ) + kv( t( 'notVerified' ), String( at.unverified ) ) + kv( t( 'personalAccs' ), String( at.personal ) ) + '</div>';

			// Campaigns.
			h += '<div class="section"><h3>' + esc( t( 'campaigns' ) ) + '</h3><span class="small">' + g.campaigns.length + '</span></div>';
			h += g.campaigns.length ? '<div class="rows">' + g.campaigns.map( function ( c ) {
				return '<div class="row row-tap" data-camp="' + c.id + '"><div class="row-main"><b>' + esc( c.title ) + ( c.closed ? ' <span class="pill">' + esc( t( 'closed' ) ) + '</span>' : '' ) + '</b><small>' + esc( c.date ) + ' · ' + esc( t( 'respPct', c.response ) ) + '</small></div><div class="row-side"><b>' + pct( c.acceptance ) + '</b><small>' + c.confirmed + '/' + ( c.confirmed + c.declined ) + ' · ' + c.people + ' ' + esc( t( 'people' ) ) + '</small></div></div>';
			} ).join( '' ) + '</div>' : '<p class="muted" style="margin:0 4px">' + esc( t( 'noData' ) ) + '</p>';

			view.innerHTML = h;

			function paintDim( key ) {
				var rows = ( g.dims && g.dims[ key ] ) || [];
				$( 'ins-dimbox' ).innerHTML = rows.length ? rows.map( function ( r2 ) {
					return '<div class="dimrow"><div class="dimtop"><span>' + esc( r2.label ) + '</span><span><b>' + pct( r2.acceptance ) + '</b> <small>' + esc( t( 'ofProposed', r2.invited ) ) + '</small></span></div><div class="barline"><i style="width:' + ( r2.acceptance || 0 ) + '%"></i></div></div>';
				} ).join( '' ) + '<p class="small muted" style="margin:8px 0 0">' + esc( t( 'barMeaning' ) ) + '</p>' : '<p class="muted" style="margin:0">' + esc( t( 'noData' ) ) + '</p>';
			}
			paintDim( 'category' );
			qsa( '#ins-dim button', view ).forEach( function ( bt ) {
				bt.addEventListener( 'click', function () {
					qsa( '#ins-dim button', view ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === bt ); } );
					paintDim( bt.getAttribute( 'data-d' ) );
				} );
			} );
			qsa( '#ins-seg button', view ).forEach( function ( bt ) {
				bt.addEventListener( 'click', function () { sset( 'cp_ins_period', bt.getAttribute( 'data-p' ) ); render(); } );
			} );
			qsa( '.row-tap[data-id]', view ).forEach( function ( el ) { el.addEventListener( 'click', function () { go( '/b/' + el.getAttribute( 'data-id' ) ); } ); } );
			qsa( '.row-tap[data-camp]', view ).forEach( function ( el ) { el.addEventListener( 'click', function () { go( '/c/' + el.getAttribute( 'data-camp' ) ); } ); } );
		} ).catch( function ( e ) { fail( ctx, e ); } );
	}

	/* ================================================================ Push */

	function pushCapable() { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window; }
	function isIOS() { return /iPad|iPhone|iPod/.test( navigator.userAgent ) || ( navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1 ); }
	function isStandalone() { return navigator.standalone === true || ( window.matchMedia && matchMedia( '(display-mode: standalone)' ).matches ); }
	function b64ToU8( b64 ) {
		var pad = '='.repeat( ( 4 - b64.length % 4 ) % 4 );
		var raw = atob( ( b64 + pad ).replace( /-/g, '+' ).replace( /_/g, '/' ) );
		var out = new Uint8Array( raw.length );
		for ( var i = 0; i < raw.length; i++ ) { out[ i ] = raw.charCodeAt( i ); }
		return out;
	}
	function withTimeout( p, ms, msg ) {
		return Promise.race( [ p, new Promise( function ( res, rej ) { setTimeout( function () { rej( { message: msg } ); }, ms ); } ) ] );
	}
	// Get an ACTIVE service worker registration — registering it now if needed.
	function swReady() {
		var p = navigator.serviceWorker.getRegistration().then( function ( reg ) {
			return reg || navigator.serviceWorker.register( C.sw );
		} ).then( function ( reg ) {
			if ( reg.active ) { return reg; }
			var w = reg.installing || reg.waiting;
			if ( ! w ) { return reg; }
			return new Promise( function ( res ) {
				w.addEventListener( 'statechange', function () { if ( w.state === 'activated' ) { res( reg ); } } );
			} );
		} );
		return withTimeout( p, 10000, t( 'pushSwFail' ) );
	}
	function currentSub() {
		return swReady().then( function ( reg ) { return reg.pushManager.getSubscription(); } );
	}
	function askPermission() {
		// Works with both the promise and the older callback form.
		return new Promise( function ( resolve ) {
			var r = Notification.requestPermission( resolve );
			if ( r && typeof r.then === 'function' ) { r.then( resolve ); }
		} );
	}
	function pushEnable() {
		// Permission must be the very first call inside the tap (iOS rule).
		return askPermission().then( function ( perm ) {
			if ( perm !== 'granted' ) { throw { message: t( 'pushDenied' ) }; }
			return Promise.all( [ swReady(), withTimeout( api( '/push/key' ), 12000, t( 'offline' ) ) ] );
		} ).then( function ( r ) {
			var reg = r[ 0 ], k = r[ 1 ];
			if ( ! k || ! k.supported || ! k.key ) { throw { message: t( 'pushServer' ) }; }
			if ( ! reg.pushManager ) { throw { message: t( 'pushUnsupported' ) }; }
			return reg.pushManager.getSubscription().then( function ( ex ) {
				return ex || reg.pushManager.subscribe( { userVisibleOnly: true, applicationServerKey: b64ToU8( k.key ) } );
			} );
		} ).then( function ( sub ) {
			return withTimeout( post( '/push/subscribe', { subscription: sub.toJSON() } ), 12000, t( 'offline' ) );
		} );
	}
	function pushDisable() {
		return currentSub().then( function ( sub ) {
			if ( ! sub ) { return; }
			var ep = sub.endpoint;
			return sub.unsubscribe().catch( function () {} ).then( function () { return post( '/push/unsubscribe', { endpoint: ep } ); } );
		} );
	}
	function paintPush( box ) {
		if ( ! box ) { return; }
		if ( ! pushCapable() ) {
			box.innerHTML = '<div class="banner info"><p>' + esc( isIOS() && ! isStandalone() ? t( 'pushInstall' ) : t( 'pushUnsupported' ) ) + '</p></div>';
			return;
		}
		if ( Notification.permission === 'denied' ) {
			box.innerHTML = '<div class="banner warn"><p>' + esc( t( 'pushDenied' ) ) + '</p></div>';
			return;
		}
		box.innerHTML = '<div class="group"><div class="item"><div class="item-main"><b>' + esc( t( 'pushNew' ) ) + '</b><small>' + esc( t( 'pushNewSub' ) ) + '</small></div>' + sw( 'm-push', false ) + '</div>' +
			'<div class="item item-tap" id="m-push-test" hidden><div class="item-main"><b>' + esc( t( 'pushTest' ) ) + '</b></div><span class="chev">›</span></div></div>' +
			'<p class="hint" id="m-push-note" style="margin:-4px 4px 0"></p>';
		var toggle = $( 'm-push' ), test = $( 'm-push-test' ), note = $( 'm-push-note' );
		var busy = false;
		function setOn( on ) { toggle.checked = on; test.hidden = ! on; }
		function setNote( msg, isErr ) { note.textContent = msg || ''; note.style.color = isErr ? 'var(--red)' : ''; }

		// Show current state without ever blocking the switch.
		currentSub().then( function ( sub ) {
			if ( ! sub ) { return false; }
			return post( '/push/status', { endpoint: sub.endpoint } ).then( function ( r ) { return !! r.subscribed; } );
		} ).then( function ( on ) { if ( ! busy ) { setOn( !! on ); } } ).catch( function () { /* state unknown — leave off */ } );

		toggle.addEventListener( 'change', function () {
			if ( busy ) { return; }
			var want = toggle.checked;
			busy = true;
			setNote( t( 'saving' ) );
			( want ? pushEnable() : pushDisable() ).then( function () {
				setOn( want ); setNote( '' ); toast( want ? t( 'pushOn' ) : t( 'pushOff' ) );
			} ).catch( function ( e ) {
				setOn( ! want );
				var msg = ( e && e.message ) || String( e || '' ) || t( 'offline' );
				setNote( msg, true ); toast( msg, true );
				if ( window.Notification && Notification.permission === 'denied' ) { paintPush( box ); }
			} ).then( function () { busy = false; } );
		} );
		test.addEventListener( 'click', function () {
			setNote( '' );
			currentSub().then( function ( sub ) {
				if ( ! sub ) { throw { message: t( 'pushOff' ) }; }
				return post( '/push/test', { endpoint: sub.endpoint } );
			} ).then( function () { toast( t( 'pushTestSent' ) ); } ).catch( function ( e ) { setNote( ( e && e.message ) || t( 'offline' ), true ); actErr( e ); } );
		} );
	}

	/* ================================================================ More */

	function vMore( ctx ) {
		setBar( { title: t( 'more' ), large: true } );
		var theme = localStorage.getItem( THEME ) || 'dark';
		var loadUser = S.user ? Promise.resolve( S.user ) : api( '/session' ).then( function ( d ) { S.user = d.user || {}; return S.user; } ).catch( function () { return {}; } );
		return loadUser.then( function ( u ) {
			if ( ctx.stale() ) { return; }
			var h = '<h1 class="h1">' + esc( t( 'more' ) ) + '</h1>';
			h += '<div class="group"><div class="item">' + avatar( u.name || '', u.name || 'me' ) + '<div class="item-main"><small>' + esc( t( 'signedInAs' ) ) + '</small><b>' + esc( u.name || '—' ) + '</b></div></div></div>';
			h += '<div class="section"><h3>' + esc( t( 'appearance' ) ) + '</h3></div><div class="seg" id="m-theme">' + [ [ 'dark', t( 'dark' ) ], [ 'light', t( 'light' ) ], [ 'system', t( 'system' ) ] ].map( function ( o ) {
				return '<button type="button" data-v="' + o[ 0 ] + '" class="' + ( theme === o[ 0 ] ? 'is-on' : '' ) + '">' + esc( o[ 1 ] ) + '</button>';
			} ).join( '' ) + '</div>';
			h += '<div class="section"><h3>' + esc( t( 'pushTitle' ) ) + '</h3></div><div id="m-pushbox"></div>';
			h += '<div class="section"><h3>' + esc( C.appName || '' ) + '</h3></div><div class="group">' +
				'<div class="item item-tap" id="m-reload"><div class="item-main"><b>' + esc( t( 'reloadData' ) ) + '</b></div><span class="chev">›</span></div>' +
				'<a class="item item-tap" style="text-decoration:none" href="' + esc( C.adminUrl || '#' ) + '" target="_blank" rel="noopener"><div class="item-main"><b>' + esc( t( 'openAdmin' ) ) + '</b></div><span class="chev">›</span></a>' +
				'<div class="item"><div class="item-main"><b>' + esc( t( 'version' ) ) + '</b></div><span class="muted">' + esc( C.version || '' ) + '</span></div></div>';
			h += '<button type="button" class="btn btn-danger btn-block" id="m-out" style="margin-top:18px">' + esc( t( 'signOut' ) ) + '</button>';
			view.innerHTML = h;
			qsa( '#m-theme button' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					try { localStorage.setItem( THEME, b.getAttribute( 'data-v' ) ); } catch ( e ) {}
					applyTheme();
					qsa( '#m-theme button' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } );
				} );
			} );
			$( 'm-reload' ).addEventListener( 'click', function () { drop( 'campaigns', 'camp', 'library', 'lists', 'insights', 'meta', 'user' ); toast( t( 'reloaded' ) ); } );
			$( 'm-out' ).addEventListener( 'click', function () { post( '/logout' ).catch( function () {} ); signOut( '' ); } );
			paintPush( $( 'm-pushbox' ) );
		} );
	}

	/* ================================================================ Theme, auth, boot */

	function resolveTheme( p ) {
		if ( p === 'system' ) { return ( window.matchMedia && matchMedia( '(prefers-color-scheme: light)' ).matches ) ? 'light' : 'dark'; }
		return p === 'light' ? 'light' : 'dark';
	}
	function applyTheme() {
		var p = 'dark';
		try { p = localStorage.getItem( THEME ) || 'dark'; } catch ( e ) {}
		document.documentElement.setAttribute( 'data-theme', resolveTheme( p ) );
	}

	function showLogin( msg ) {
		$( 'boot' ).hidden = true;
		$( 'app' ).hidden = true;
		$( 'login' ).hidden = false;
		qsa( '.fab, .savebar' ).forEach( function ( x ) { x.remove(); } );
		$( 'login-error' ).textContent = msg || '';
		setTimeout( function () { var u = $( 'login-u' ); if ( u && ! u.value ) { u.focus(); } }, 50 );
	}
	function showApp() {
		$( 'boot' ).hidden = true;
		$( 'login' ).hidden = true;
		$( 'app' ).hidden = false;
		if ( ! location.hash || location.hash === '#' || location.hash === '#/' ) { location.replace( '#/campaigns' ); }
		render();
	}
	function signOut( msg ) {
		clearToken();
		drop( 'campaigns', 'camp', 'library', 'lists', 'insights', 'meta', 'user' );
		closeSheets();
		showLogin( msg );
	}

	function initLogin() {
		$( 'login-form' ).addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = $( 'login-btn' ), err = $( 'login-error' );
			var u = $( 'login-u' ).value.trim(), p = $( 'login-p' ).value, r = $( 'login-remember' ).checked;
			if ( ! u || ! p ) { return; }
			err.textContent = ''; btn.disabled = true;
			var body = new FormData();
			body.append( 'username', u ); body.append( 'password', p ); body.append( 'remember', r ? '1' : '' );
			fetch( C.api + '/auth', { method: 'POST', body: body, headers: { Accept: 'application/json' } } )
				.then( function ( x ) { return x.json().then( function ( d ) { return { ok: x.ok, d: d }; } ); } )
				.then( function ( res ) {
					btn.disabled = false;
					if ( res.ok && res.d && res.d.token ) { setToken( res.d.token, r ); $( 'login-p' ).value = ''; showApp(); }
					else { err.textContent = ( res.d && res.d.message ) || t( 'offline' ); }
				} )
				.catch( function () { btn.disabled = false; err.textContent = t( 'offline' ); } );
		} );
	}

	function boot() {
		applyTheme();
		if ( window.matchMedia ) {
			var mq = matchMedia( '(prefers-color-scheme: light)' );
			var onChange = function () { if ( ( localStorage.getItem( THEME ) || 'dark' ) === 'system' ) { applyTheme(); } };
			if ( mq.addEventListener ) { mq.addEventListener( 'change', onChange ); } else if ( mq.addListener ) { mq.addListener( onChange ); }
		}
		buildFrame();
		initLogin();
		var lastPath = currentPath();
		window.addEventListener( 'scroll', debounce( function () { lastPath = currentPath(); scrollMem[ lastPath ] = window.scrollY; }, 80 ), { passive: true } );
		window.addEventListener( 'hashchange', function () { if ( ! programmatic ) { depth = Math.max( 0, depth - 1 ); } if ( ! $( 'app' ).hidden ) { render(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { closeSheets(); } } );
		document.addEventListener( 'visibilitychange', function () {
			if ( document.visibilityState === 'visible' && ! $( 'app' ).hidden && ! appEl.classList.contains( 'no-tabs' ) ) {
				drop( 'campaigns', 'camp', 'insights' );
				if ( /^\/(campaigns|c\/\d+)$/.test( currentPath() ) ) { render( true ); }
			}
		} );
		if ( getToken() ) {
			api( '/session' ).then( function ( d ) { S.user = d.user || {}; showApp(); } ).catch( function ( e ) {
				if ( e && e.code === 'offline' ) { showApp(); return; }
				clearToken(); showLogin( e && e.code === 'expired' ? t( 'expired' ) : '' );
			} );
		} else {
			showLogin();
		}
		if ( 'serviceWorker' in navigator && C.sw ) {
			navigator.serviceWorker.register( C.sw ).catch( function () {} );
			// Tapping a notification while the app is open: jump to its screen.
			navigator.serviceWorker.addEventListener( 'message', function ( e ) {
				var u = e.data && e.data.type === 'cp-open' ? String( e.data.url || '' ) : '';
				var i = u.indexOf( '#' );
				if ( i !== -1 ) { location.hash = u.slice( i + 1 ); }
			} );
		}
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
} )();
