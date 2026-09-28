/* HypeIt App settings: colour pickers, live phone preview, install QR code. */
( function () {
	'use strict';

	var $ = function ( id ) { return document.getElementById( id ); };
	var form = $( 'cpa-form' );
	if ( ! form ) { return; }
	var HEX = /^#[0-9a-f]{6}$/i;

	// Colour picker <-> hex text (the text field is what's saved).
	Array.prototype.forEach.call( form.querySelectorAll( 'input[type="color"][data-for]' ), function ( pick ) {
		var text = $( pick.getAttribute( 'data-for' ) );
		if ( ! text ) { return; }
		pick.addEventListener( 'input', function () { text.value = pick.value; paint(); } );
		text.addEventListener( 'input', function () { if ( HEX.test( text.value.trim() ) ) { pick.value = text.value.trim(); } paint(); } );
	} );

	function val( id ) { var el = $( id ); return el ? el.value.trim() : ''; }
	function iconHtml() {
		var img = document.querySelector( '#app_icon_preview img' );
		if ( img ) { return '<img src="' + img.getAttribute( 'src' ) + '" alt="" />'; }
		var s = val( 'app_short_name' ) || val( 'app_name' ) || 'H';
		return String( s ).charAt( 0 ).replace( /[&<>"]/g, '' );
	}
	function paint() {
		var theme = HEX.test( val( 'app_theme_color' ) ) ? val( 'app_theme_color' ) : '#000000';
		var short = val( 'app_short_name' ) || val( 'app_name' );
		if ( $( 'cpa-bar' ) ) { $( 'cpa-bar' ).style.background = theme; }
		[ 'cpa-icon' ].forEach( function ( id ) {
			var el = $( id ); if ( ! el ) { return; }
			el.innerHTML = iconHtml();
			el.style.background = document.querySelector( '#app_icon_preview img' ) ? 'transparent' : theme;
		} );
		if ( $( 'cpa-short' ) ) { $( 'cpa-short' ).textContent = short; }
	}
	form.addEventListener( 'input', paint );
	var prev = $( 'app_icon_preview' );
	if ( prev && window.MutationObserver ) { new MutationObserver( paint ).observe( prev, { childList: true, subtree: true } ); }
	paint();

	// QR code for installing on a phone.
	var qr = $( 'cpa-qr' );
	if ( qr && window.qrcode ) {
		try {
			var code = window.qrcode( 0, 'M' );
			code.addData( qr.getAttribute( 'data-url' ) );
			code.make();
			qr.innerHTML = code.createSvgTag( { cellSize: 5, margin: 2, scalable: true } );
		} catch ( e ) {
			qr.hidden = true;
		}
	}

	// Copy the app link.
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.cps-copy' ); if ( ! b ) { return; }
		var done = function () { var t = b.textContent; b.textContent = b.getAttribute( 'data-done' ); setTimeout( function () { b.textContent = t; }, 1400 ); };
		if ( navigator.clipboard && window.isSecureContext ) { navigator.clipboard.writeText( b.getAttribute( 'data-link' ) ).then( done ); }
	} );
} )();
