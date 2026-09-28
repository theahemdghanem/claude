/* HypeIt — onboarding form interactions. */
( function () {
	'use strict';

	var cfg = window.CP_OB || { areas: {} };

	var citySel = document.getElementById( 'cp_city' );
	var areaSel = document.getElementById( 'cp_area' );
	var areaOtherWrap = document.getElementById( 'cp_area_other_wrap' );

	function opt( value, label ) {
		var o = document.createElement( 'option' );
		o.value = value;
		o.textContent = label;
		return o;
	}

	function rebuildAreas() {
		if ( ! areaSel ) { return; }
		var city = citySel ? citySel.value : '';
		var list = ( cfg.areas && cfg.areas[ city ] ) ? cfg.areas[ city ] : [];

		areaSel.innerHTML = '';
		areaSel.appendChild( opt( '', '— Select —' ) );
		list.forEach( function ( a ) { areaSel.appendChild( opt( a, a ) ); } );
		areaSel.appendChild( opt( '__other__', 'Other (type below)' ) );

		toggleAreaOther();
	}

	function toggleAreaOther() {
		if ( ! areaSel || ! areaOtherWrap ) { return; }
		var isOther = areaSel.value === '__other__' || ( areaSel.options.length <= 2 );
		areaOtherWrap.style.display = ( areaSel.value === '__other__' ) ? '' : 'none';
	}

	if ( citySel ) { citySel.addEventListener( 'change', rebuildAreas ); }
	if ( areaSel ) { areaSel.addEventListener( 'change', toggleAreaOther ); }

	// Category "Other" toggle.
	var otherBox = document.getElementById( 'cp_cat_other_box' );
	var otherWrap = document.getElementById( 'cp_cat_other_wrap' );
	function toggleCatOther() {
		if ( otherBox && otherWrap ) {
			otherWrap.style.display = otherBox.checked ? '' : 'none';
		}
	}
	if ( otherBox ) { otherBox.addEventListener( 'change', toggleCatOther ); }

	// "Open to all types" convenience toggle for the collaboration checkboxes.
	var collabAll = document.getElementById( 'cp_collab_all' );
	if ( collabAll ) {
		var collabBoxes = document.querySelectorAll( 'input[name="cp_collab[]"]' );
		var syncAll = function () {
			collabAll.checked = collabBoxes.length > 0 && Array.prototype.every.call( collabBoxes, function ( b ) { return b.checked; } );
		};
		collabAll.addEventListener( 'change', function () {
			collabBoxes.forEach( function ( b ) { b.checked = collabAll.checked; } );
		} );
		collabBoxes.forEach( function ( b ) { b.addEventListener( 'change', syncAll ); } );
		syncAll();
	}

	// Initial state (also restores after a validation reload).
	if ( citySel && citySel.value ) { rebuildAreas(); }
	toggleCatOther();

	// Post a plain @username instead of a full URL — many host firewalls
	// (ModSecurity) block requests that contain http(s):// in a field.
	var obForm = document.querySelector( '.cp-ob-form' );
	var igField = obForm ? obForm.querySelector( 'input[name="cp_ig"]' ) : null;
	function normalizeIg() {
		if ( ! igField || igField.readOnly ) { return; }
		var v = ( igField.value || '' ).trim();
		var m = v.match( /instagram\.com\/([A-Za-z0-9._]+)/i );
		if ( m ) { v = m[1]; }
		v = v.replace( /^@+/, '' ).replace( /[^A-Za-z0-9._]/g, '' );
		if ( v ) { igField.value = '@' + v; }
	}
	if ( igField ) { igField.addEventListener( 'blur', normalizeIg ); }
	if ( obForm ) { obForm.addEventListener( 'submit', normalizeIg ); }
} )();

/* Profile photo: preview + shrink in the browser before upload (fast on mobile,
   converts iPhone photos to JPEG, keeps the request small for host firewalls). */
( function () {
	'use strict';
	var input = document.getElementById( 'cp_photo' );
	if ( ! input ) { return; }
	var preview = document.getElementById( 'cp_photo_preview' );
	var label   = document.getElementById( 'cp_photo_label' );
	var token   = document.getElementById( 'cp_photo_token' );
	var wrap    = input.closest( '.cp-ob-photo' );
	var MAX     = 1200;

	function show( url ) {
		preview.style.backgroundImage = 'url(' + url + ')';
		preview.innerHTML = '';
		preview.classList.add( 'has-photo' );
		if ( label && label.getAttribute( 'data-change' ) ) { label.textContent = label.getAttribute( 'data-change' ); }
	}

	function loadImage( file ) {
		return new Promise( function ( resolve, reject ) {
			var url = URL.createObjectURL( file );
			var img = new Image();
			img.onload = function () { resolve( { img: img, url: url } ); };
			img.onerror = function () { URL.revokeObjectURL( url ); reject(); };
			img.src = url;
		} );
	}

	input.addEventListener( 'change', function () {
		var file = input.files && input.files[ 0 ];
		if ( ! file ) { return; }
		if ( token ) { token.value = ''; }
		wrap && wrap.classList.add( 'is-busy' );
		loadImage( file ).then( function ( r ) {
			show( r.url );
			var w = r.img.naturalWidth, h = r.img.naturalHeight;
			var scale = Math.min( 1, MAX / Math.max( w, h ) );
			var canvas = document.createElement( 'canvas' );
			canvas.width = Math.round( w * scale ); canvas.height = Math.round( h * scale );
			canvas.getContext( '2d' ).drawImage( r.img, 0, 0, canvas.width, canvas.height );
			return new Promise( function ( res ) { canvas.toBlob( res, 'image/jpeg', 0.88 ); } );
		} ).then( function ( blob ) {
			if ( ! blob || typeof DataTransfer === 'undefined' ) { return; }
			try {
				var dt = new DataTransfer();
				dt.items.add( new File( [ blob ], 'photo.jpg', { type: 'image/jpeg' } ) );
				input.files = dt.files;
			} catch ( e ) { /* keep the original file */ }
		} ).catch( function () {
			/* Browser can't read this format — the server will validate and explain. */
		} ).then( function () { wrap && wrap.classList.remove( 'is-busy' ); } );
	} );
} )();

/* Live Instagram check (no login): fills in the real follower count. */
( function () {
	'use strict';
	var box = document.getElementById( 'cp_igcheck' );
	if ( ! box ) { return; }
	var form = box.closest( 'form' );
	var ig = form && form.querySelector( 'input[name="cp_ig"]' );
	var fol = form && form.querySelector( 'input[name="cp_followers"]' );
	if ( ! ig || ! fol ) { return; }
	var last = '', timer;
	var nf;
	try { nf = new Intl.NumberFormat( document.documentElement.lang || undefined ); } catch ( e ) { nf = { format: String }; }

	function clean( v ) {
		v = String( v || '' ).trim();
		var m = v.match( /instagram\.com\/([A-Za-z0-9._]+)/i );
		if ( m ) { v = m[ 1 ]; }
		return v.replace( /^@+/, '' ).replace( /[^A-Za-z0-9._]/g, '' ).toLowerCase();
	}
	function say( text, kind ) { box.textContent = text; box.className = 'cp-ob-igcheck' + ( kind ? ' is-' + kind : '' ); }

	function check() {
		var u = clean( ig.value );
		if ( u.length < 2 || u === last ) { return; }
		last = u;
		say( box.getAttribute( 'data-checking' ), 'busy' );
		var body = new FormData();
		body.append( 'action', 'cp_ig_lookup' );
		body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
		body.append( 'username', u );
		fetch( box.getAttribute( 'data-ajax' ), { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( clean( ig.value ) !== u ) { return; }
				var d = j && j.data ? j.data : {};
				if ( d.found ) {
					fol.value = d.followers;
					fol.readOnly = true;
					say( box.getAttribute( 'data-found' ).replace( '%1$s', d.username ).replace( '%2$s', nf.format( d.followers ) ), 'ok' );
				} else {
					fol.readOnly = false;
					say( box.getAttribute( d.reason === 'personal' ? 'data-personal' : 'data-busy' ), d.reason === 'personal' ? 'warn' : '' );
				}
			} )
			.catch( function () { fol.readOnly = false; say( '', '' ); } );
	}
	ig.addEventListener( 'blur', check );
	ig.addEventListener( 'input', function () {
		clearTimeout( timer );
		if ( fol.readOnly && clean( ig.value ) !== last ) { fol.readOnly = false; say( '', '' ); }
		timer = setTimeout( check, 900 );
	} );
	if ( ig.value ) { check(); }
} )();

/* Copy buttons (thank-you page bio code). */
( function () {
	'use strict';
	Array.prototype.forEach.call( document.querySelectorAll( '.cp-ob-copy[data-copy]' ), function ( b ) {
		b.addEventListener( 'click', function () {
			var t = b.getAttribute( 'data-copy' ), was = b.textContent;
			( navigator.clipboard ? navigator.clipboard.writeText( t ) : Promise.resolve() ).then( function () {
				b.textContent = b.getAttribute( 'data-done' ) || '✓';
				setTimeout( function () { b.textContent = was; }, 1600 );
			} );
		} );
	} );
} )();

/* Thank-you page: "I've added it — check now". */
( function () {
	'use strict';
	var card = document.getElementById( 'cp_vcard' );
	var btn = document.getElementById( 'cp_vcheck' );
	var out = document.getElementById( 'cp_vstatus' );
	if ( ! card || ! btn || ! out ) { return; }
	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		out.className = 'cp-ob-vstatus is-busy';
		out.textContent = card.getAttribute( 'data-checking' );
		var body = new FormData();
		body.append( 'action', 'cp_ig_verify_now' );
		body.append( 'bid', card.getAttribute( 'data-bid' ) );
		body.append( 'sig', card.getAttribute( 'data-sig' ) );
		fetch( card.getAttribute( 'data-ajax' ), { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				var st = ( j && j.data && j.data.state ) || 'busy';
				out.className = 'cp-ob-vstatus is-' + st;
				out.textContent = card.getAttribute( 'data-' + st ) || card.getAttribute( 'data-busy' );
				if ( st === 'ok' ) { card.classList.add( 'is-verified' ); btn.hidden = true; return; }
				setTimeout( function () { btn.disabled = false; }, 30000 );
			} )
			.catch( function () {
				out.className = 'cp-ob-vstatus'; out.textContent = card.getAttribute( 'data-busy' );
				setTimeout( function () { btn.disabled = false; }, 10000 );
			} );
	} );
} )();

/* Follow button: inverted colours taken from the page (white on black sites, black on white). */
( function () {
	'use strict';
	var box = document.querySelector( '.cp-ob-follow' );
	if ( ! box ) { return; }
	var node = box, bg = null;
	while ( node && node.nodeType === 1 && ! bg ) {
		var c = window.getComputedStyle( node ).backgroundColor;
		if ( c && c !== 'transparent' && ! /rgba\(.*,\s*0\)$/.test( c ) ) { bg = c; }
		node = node.parentElement;
	}
	box.style.setProperty( '--cp-ob-bg', bg || '#ffffff' );
	box.style.setProperty( '--cp-ob-fg', window.getComputedStyle( box ).color );
} )();
