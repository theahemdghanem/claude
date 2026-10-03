/* Talent Works Archive — AJAX filtering, sorting, load-more, searchable artist combobox */
( function () {
	'use strict';
	if ( typeof CELB_WORKS === 'undefined' ) { return; }

	function each( list, fn ) { Array.prototype.forEach.call( list, fn ); }
	function debounce( fn, ms ) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( ctx, args ); }, ms );
		};
	}

	function isOpaque( c ) {
		if ( ! c ) { return false; }
		if ( c === 'transparent' ) { return false; }
		var m = c.match( /rgba?\(([^)]+)\)/ );
		if ( m ) {
			var p = m[1].split( ',' );
			if ( p.length >= 4 && parseFloat( p[3] ) === 0 ) { return false; }
		}
		return true;
	}

	// Read the actual page background so cards inherit the active theme's scheme.
	function inheritSurface( root ) {
		var el = root.parentElement;
		var bg = '';
		while ( el && el !== document.documentElement ) {
			var c = getComputedStyle( el ).backgroundColor;
			if ( isOpaque( c ) ) { bg = c; break; }
			el = el.parentElement;
		}
		if ( ! bg ) {
			var bodyBg = getComputedStyle( document.body ).backgroundColor;
			if ( isOpaque( bodyBg ) ) { bg = bodyBg; }
		}
		if ( bg ) { root.style.setProperty( '--ilw-page-bg', bg ); }
	}

	function initRoot( root ) {
		inheritSurface( root );

		// Mobile: collapse the filter panel behind a toggle.
		var ftoggle = root.querySelector( '.ilw-filter-toggle' );
		if ( ftoggle ) {
			ftoggle.addEventListener( 'click', function () {
				var open = root.classList.toggle( 'filters-open' );
				ftoggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}
		var groups  = root.querySelector( '.ilw-groups' );
		var empty   = root.querySelector( '.ilw-empty' );
		var status  = root.querySelector( '.ilw-status' );
		var search  = root.querySelector( '.ilw-search' );
		var year    = root.querySelector( '.ilw-year' );
		var type    = root.querySelector( '.ilw-type' );

		// Combobox
		var comboInput = root.querySelector( '.ilw-combo-input' );
		var comboHidden = root.querySelector( '.ilw-artist' );
		var comboList = root.querySelector( '.ilw-combo-list' );
		var comboClear = root.querySelector( '.ilw-combo-clear' );

		var busy = false;

		function params() {
			var body = new URLSearchParams();
			body.set( 'action', 'celb_work_query' );
			body.set( 'nonce', CELB_WORKS.nonce );
			body.set( 'artist', comboHidden ? comboHidden.value : '' );
			body.set( 'year', year ? year.value : '' );
			body.set( 'type', type ? type.value : '' );
			body.set( 'search', search ? search.value : '' );
			return body;
		}

		function load() {
			if ( busy ) { return; }
			busy = true;
			root.classList.add( 'is-loading' );

			fetch( CELB_WORKS.ajax, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params().toString(),
				credentials: 'same-origin'
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					busy = false;
					root.classList.remove( 'is-loading' );
					if ( ! res || ! res.success ) { return; }
					var d = res.data;
					groups.innerHTML = d.html;
					if ( empty ) { empty.hidden = d.total !== 0; }
					groups.style.display = d.total === 0 ? 'none' : '';
					if ( status ) {
						status.textContent = d.total
							? ( d.total + ( d.total === 1 ? ' production' : ' productions' ) )
							: '';
					}
				} )
				.catch( function () {
					busy = false;
					root.classList.remove( 'is-loading' );
				} );
		}

		var onFilter = debounce( load, 60 );
		var onSearch = debounce( load, 320 );

		if ( search ) { search.addEventListener( 'input', onSearch ); }
		each( [ year, type ], function ( el ) { if ( el ) { el.addEventListener( 'change', onFilter ); } } );

		// Year accordion — delegated so it survives AJAX re-renders.
		if ( groups ) {
			groups.addEventListener( 'click', function ( e ) {
				var head = e.target.closest ? e.target.closest( '.ilw-year-head' ) : null;
				if ( ! head || ! groups.contains( head ) ) { return; }
				var section = head.parentNode;
				var open = section.classList.toggle( 'is-open' );
				head.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}

		/* ---- Searchable combobox ---- */
		if ( comboInput && comboList && comboHidden ) {
			var opts = comboList.querySelectorAll( '.ilw-combo-opt' );

			function openList() { comboList.hidden = false; comboInput.setAttribute( 'aria-expanded', 'true' ); }
			function closeList() { comboList.hidden = true; comboInput.setAttribute( 'aria-expanded', 'false' ); }
			function filterList() {
				var q = comboInput.value.trim().toLowerCase();
				each( opts, function ( li ) {
					var txt = li.textContent.toLowerCase();
					li.hidden = q && txt.indexOf( q ) === -1;
				} );
			}
			function pick( li ) {
				var val = li.getAttribute( 'data-val' ) || '';
				comboHidden.value = val;
				comboInput.value = val ? li.textContent : '';
				if ( comboClear ) { comboClear.hidden = ! val; }
				each( opts, function ( o ) { o.hidden = false; } );
				closeList();
				load();
			}

			comboInput.addEventListener( 'focus', function () { filterList(); openList(); } );
			comboInput.addEventListener( 'input', function () { openList(); filterList(); } );
			each( opts, function ( li ) {
				li.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); pick( li ); } );
			} );
			if ( comboClear ) {
				comboClear.addEventListener( 'click', function () {
					comboHidden.value = '';
					comboInput.value = '';
					comboClear.hidden = true;
					each( opts, function ( o ) { o.hidden = false; } );
					load();
				} );
			}
			document.addEventListener( 'click', function ( e ) {
				if ( ! root.contains( e.target ) || ! comboInput.parentNode.contains( e.target ) ) {
					closeList();
				}
			} );
		}
	}

	function boot() {
		each( document.querySelectorAll( '.ilw' ), initRoot );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
