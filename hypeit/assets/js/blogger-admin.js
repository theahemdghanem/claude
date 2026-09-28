/* Campaign — blogger editor workspace. */
( function () {
	'use strict';
	var root = document.getElementById( 'cpb' );
	if ( ! root ) { return; }
	var $ = function ( id ) { return document.getElementById( id ); };
	var qsa = function ( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); };
	var post = root.getAttribute( 'data-post' );

	/* Tabs (remembered per blogger). */
	var key = 'cpb-tab-' + post;
	function activate( name ) {
		qsa( '.cpw-tab', root ).forEach( function ( t ) { t.classList.toggle( 'is-active', t.getAttribute( 'data-tab' ) === name ); } );
		qsa( '.cpw-panel', root ).forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === name ); } );
		try { sessionStorage.setItem( key, name ); } catch ( e ) {}
	}
	qsa( '.cpw-tab', root ).forEach( function ( t ) { t.addEventListener( 'click', function () { activate( t.getAttribute( 'data-tab' ) ); } ); } );
	try { var saved = sessionStorage.getItem( key ); if ( saved && root.querySelector( '[data-tab="' + saved + '"]' ) ) { activate( saved ); } } catch ( e ) {}

	/* Live name in the header. */
	var nameEl = $( 'cpb-name' ), first = $( 'cp_first' ), last = $( 'cp_last' ), ig = $( 'cp_ig' );
	function paintName() {
		var n = ( ( first.value || '' ) + ' ' + ( last.value || '' ) ).trim();
		nameEl.textContent = n || ( ig.value ? '@' + ig.value.replace( /^@/, '' ) : nameEl.getAttribute( 'data-empty' ) );
	}
	[ first, last, ig ].forEach( function ( el ) { if ( el ) { el.addEventListener( 'input', paintName ); } } );

	/* Instagram: accept a pasted profile link or @name, keep just the username. */
	if ( ig ) {
		ig.addEventListener( 'blur', function () {
			var v = ig.value.trim(), m = v.match( /instagram\.com\/([A-Za-z0-9._]+)/i );
			if ( m ) { v = m[ 1 ]; }
			ig.value = v.replace( /^@+/, '' ).replace( /[^A-Za-z0-9._]/g, '' );
			paintName();
		} );
	}

	/* Followers: show a friendly short form (12.4K). */
	var fol = $( 'cp_followers' ), hint = $( 'cpb-fol-hint' );
	var cf = null;
	try { cf = new Intl.NumberFormat( undefined, { notation: 'compact', maximumFractionDigits: 1 } ); } catch ( e ) {}
	function paintFol() { if ( hint ) { var n = parseInt( fol.value, 10 ); hint.textContent = n > 999 && cf ? '(' + cf.format( n ) + ')' : ''; } }
	if ( fol ) { fol.addEventListener( 'input', paintFol ); paintFol(); }

	/* "All types" toggle for Open for. */
	var all = $( 'cp_collab_all_admin' );
	if ( all ) {
		var boxes = qsa( '#cp-collab-wrap input[name="cp_collab[]"]' );
		var sync = function () { all.checked = boxes.length > 0 && boxes.every( function ( b ) { return b.checked; } ); };
		all.addEventListener( 'change', function () { boxes.forEach( function ( b ) { b.checked = all.checked; } ); } );
		boxes.forEach( function ( b ) { b.addEventListener( 'change', sync ); } );
		sync();
	}

	/* Photo: instant preview; removing dims it. */
	var file = $( 'cp_photo_file' ), img = $( 'cpb-photo-img' ), rm = $( 'cpb-rmphoto' );
	if ( file && img ) {
		file.addEventListener( 'change', function () {
			var f = file.files && file.files[ 0 ];
			if ( ! f ) { return; }
			img.innerHTML = '<img src="' + URL.createObjectURL( f ) + '" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;display:block" />';
			img.classList.remove( 'is-removing' );
			if ( rm ) { rm.checked = false; }
		} );
	}
	if ( rm && img ) { rm.addEventListener( 'change', function () { img.classList.toggle( 'is-removing', rm.checked ); } ); }

	/* Copy @handle. */
	var copy = $( 'cpb-copy' );
	if ( copy ) {
		copy.addEventListener( 'click', function () {
			var t = copy.getAttribute( 'data-copy' ), was = copy.textContent;
			( navigator.clipboard ? navigator.clipboard.writeText( t ) : Promise.resolve() ).then( function () {
				copy.textContent = '✓'; setTimeout( function () { copy.textContent = was; }, 1200 );
			} );
		} );
	}

	/* Unsaved-changes guard. */
	var dirty = false, submitting = false;
	root.addEventListener( 'input', function () { dirty = true; } );
	root.addEventListener( 'change', function () { dirty = true; } );
	var form = $( 'post' );
	if ( form ) { form.addEventListener( 'submit', function () { submitting = true; } ); }
	window.addEventListener( 'beforeunload', function ( e ) { if ( dirty && ! submitting ) { e.preventDefault(); e.returnValue = ''; } } );
} )();

/* Live warning: this Instagram username already has a profile. */
( function () {
	'use strict';
	var wrap = document.querySelector( '.cpb-at[data-check-nonce]' );
	var ig = document.getElementById( 'cp_ig' );
	var out = document.getElementById( 'cpb-dupe' );
	var root = document.getElementById( 'cpb' );
	if ( ! wrap || ! ig || ! out || ! window.ajaxurl ) { return; }
	var last = null, timer;
	function check() {
		var v = ig.value.trim();
		if ( v === last ) { return; }
		last = v;
		if ( ! v ) { out.hidden = true; return; }
		var body = new FormData();
		body.append( 'action', 'cp_handle_check' );
		body.append( 'nonce', wrap.getAttribute( 'data-check-nonce' ) );
		body.append( 'handle', v );
		body.append( 'post', root ? root.getAttribute( 'data-post' ) : '0' );
		fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( ig.value.trim() !== v ) { return; }
				var d = j && j.data;
				if ( d && d.taken ) {
					out.innerHTML = '';
					out.appendChild( document.createTextNode( d.message + ' ' ) );
					var a = document.createElement( 'a' );
					a.href = d.url; a.textContent = '→';
					a.setAttribute( 'aria-label', 'Open that profile' );
					out.appendChild( a );
					out.hidden = false;
				} else {
					out.hidden = true;
				}
			} )
			.catch( function () {} );
	}
	ig.addEventListener( 'input', function () { clearTimeout( timer ); timer = setTimeout( check, 600 ); } );
	ig.addEventListener( 'blur', check );
} )();
