/* Campaign — Join section: elegant count-up + live count refresh (works with page caching). */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var lang = document.documentElement.lang || undefined;
	var nf;
	try { nf = new Intl.NumberFormat( lang ); } catch ( e ) { nf = { format: function ( n ) { return String( n ); } }; }

	var fresh = null, fetchedAt = 0;
	function getFresh( url ) {
		if ( fresh && Date.now() - fetchedAt < 60000 ) { return fresh; }
		fetchedAt = Date.now();
		fresh = fetch( url, { credentials: 'omit', headers: { Accept: 'application/json' } } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.then( function ( d ) { return d && typeof d.count === 'number' ? d.count : null; } )
			.catch( function () { return null; } );
		return fresh;
	}

	// Ease-out quint: quick start, long graceful settle.
	function ease( t ) { return 1 - Math.pow( 1 - t, 5 ); }

	function run( el, from, to, ms, done ) {
		var val = el.querySelector( '.cp-join__val' );
		if ( ! val ) { return; }
		if ( reduce || from === to ) { val.textContent = nf.format( to ); el._cpNow = to; if ( done ) { done(); } return; }
		var start = null;
		function frame( ts ) {
			if ( start === null ) { start = ts; }
			var p = Math.min( 1, ( ts - start ) / ms );
			var n = Math.round( from + ( to - from ) * ease( p ) );
			if ( n !== el._cpNow ) { val.textContent = nf.format( n ); el._cpNow = n; }
			if ( p < 1 ) { requestAnimationFrame( frame ); } else if ( done ) { done(); }
		}
		requestAnimationFrame( frame );
	}

	function refresh( el ) {
		var url = el.getAttribute( 'data-cp-endpoint' );
		if ( ! url ) { return; }
		getFresh( url ).then( function ( n ) {
			if ( n === null || n === el._cpNow ) { return; }
			el.classList.add( 'is-updating' );
			setTimeout( function () {
				el.classList.remove( 'is-updating' );
				run( el, el._cpNow, n, 1400 );
			}, 250 );
		} );
	}

	function reveal( el ) {
		if ( el._cpShown ) { return; }
		el._cpShown = true;
		var target = parseInt( el.getAttribute( 'data-cp-count' ), 10 ) || 0;
		// Count up only the last stretch — it reads as confident, not gimmicky.
		var from = target < 25 ? 0 : Math.round( target * 0.72 );
		el._cpNow = from;
		var val = el.querySelector( '.cp-join__val' );
		if ( val && ! reduce ) { val.textContent = nf.format( from ); }
		el.classList.add( 'is-in' );
		run( el, from, target, 2000, function () { refresh( el ); } );
	}

	function colorOf( el, prop ) {
		var c = window.getComputedStyle( el )[ prop ];
		return ( ! c || c === 'transparent' || /rgba\(.*,\s*0\)$/.test( c ) ) ? null : c;
	}
	function pageBackground( el ) {
		var node = el;
		while ( node && node.nodeType === 1 ) {
			var bg = colorOf( node, 'backgroundColor' );
			if ( bg ) { return bg; }
			node = node.parentElement;
		}
		return null;
	}

	function init() {
		var sections = document.querySelectorAll( '.cp-join' );
		if ( ! sections.length ) { return; }
		var stats = [];
		Array.prototype.forEach.call( sections, function ( sec ) {
			if ( sec._cpInit ) { return; }
			sec._cpInit = true;
			if ( ! sec.classList.contains( 'cp-join--inline' ) ) {
				var bg = pageBackground( sec.parentElement ) || '#ffffff';
				var fg = window.getComputedStyle( sec ).color;
				sec.style.setProperty( '--cp-join-bg', bg );
				sec.style.setProperty( '--cp-join-fg', fg );
				sec.classList.add( 'is-inverted' );
			}
			sec.classList.add( 'is-armed' );
			Array.prototype.forEach.call( sec.querySelectorAll( '.cp-join__stat' ), function ( s ) { stats.push( s ); } );
		} );

		if ( ! ( 'IntersectionObserver' in window ) ) { stats.forEach( reveal ); return; }
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				if ( e.isIntersecting ) { reveal( e.target ); io.unobserve( e.target ); }
			} );
		}, { threshold: 0.35, rootMargin: '0px 0px -5% 0px' } );
		stats.forEach( function ( s ) { io.observe( s ); } );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.visibilityState === 'visible' ) {
				stats.forEach( function ( s ) { if ( s._cpShown ) { refresh( s ); } } );
			}
		} );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
