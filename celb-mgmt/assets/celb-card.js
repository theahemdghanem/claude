/* global CELB_CARD */
/* ==========================================================================
   Social Media Card Generator (Newsroom)
   Renders a 3:4 PNG from the article's featured image entirely in the browser,
   so Arabic shaping + RTL alignment are handled natively by the canvas and the
   same-origin images don't taint the export.
   ========================================================================== */
( function () {
	'use strict';

	var cfg = window.CELB_CARD || {};
	var W = 1080, H = 1440, M = 80;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	function isArabic( str ) {
		return /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/.test( String( str || '' ) );
	}

	function loadImg( src ) {
		return new Promise( function ( resolve ) {
			if ( ! src ) { resolve( null ); return; }
			var im = new Image();
			im.crossOrigin = 'anonymous';
			im.onload = function () { resolve( im ); };
			im.onerror = function () { resolve( null ); };
			im.src = src;
		} );
	}

	function drawCover( ctx, im ) {
		var ir = im.width / im.height, cr = W / H, sw, sh, sx, sy;
		if ( ir > cr ) { sh = im.height; sw = sh * cr; sx = ( im.width - sw ) / 2; sy = 0; }
		else { sw = im.width; sh = sw / cr; sx = 0; sy = ( im.height - sh ) / 2; }
		ctx.drawImage( im, sx, sy, sw, sh, 0, 0, W, H );
	}

	/* Recolour a (transparent-background) logo to solid white. */
	function whiteLogo( logo ) {
		var off = document.createElement( 'canvas' );
		off.width = logo.width; off.height = logo.height;
		var oc = off.getContext( '2d' );
		oc.drawImage( logo, 0, 0 );
		oc.globalCompositeOperation = 'source-in';
		oc.fillStyle = '#ffffff';
		oc.fillRect( 0, 0, off.width, off.height );
		return off;
	}

	function fmtDate( rtl ) {
		if ( ! cfg.date ) { return ''; }
		var d = new Date( cfg.date + 'T00:00:00' );
		if ( isNaN( d.getTime() ) ) { return cfg.date; }
		try {
			return d.toLocaleDateString( rtl ? 'ar-EG' : 'en-GB', { day: 'numeric', month: 'long', year: 'numeric' } );
		} catch ( e ) { return cfg.date; }
	}

	function wrap( ctx, text, maxW, font ) {
		ctx.font = font;
		var words = String( text || '' ).split( /\s+/ );
		var lines = [], line = '';
		for ( var i = 0; i < words.length; i++ ) {
			var test = line ? line + ' ' + words[ i ] : words[ i ];
			if ( ctx.measureText( test ).width > maxW && line ) { lines.push( line ); line = words[ i ]; }
			else { line = test; }
		}
		if ( line ) { lines.push( line ); }
		return lines;
	}

	function roundRect( ctx, x, y, w, h, r ) {
		if ( r > h / 2 ) { r = h / 2; }
		if ( r > w / 2 ) { r = w / 2; }
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r );
		ctx.arcTo( x + w, y + h, x, y + h, r );
		ctx.arcTo( x, y + h, x, y, r );
		ctx.arcTo( x, y, x + w, y, r );
		ctx.closePath();
	}

	/* A small "document / press release" glyph — outlined page with a folded
	   top corner and a few text lines. Drawn at (x, y), width w, height h. */
	function drawDocIcon( ctx, x, y, w, h, color ) {
		var fold = w * 0.34;
		ctx.save();
		ctx.strokeStyle = color;
		ctx.lineWidth = 2.4;
		ctx.lineJoin = 'round';
		ctx.lineCap = 'round';
		ctx.beginPath();
		ctx.moveTo( x, y );
		ctx.lineTo( x + w - fold, y );
		ctx.lineTo( x + w, y + fold );
		ctx.lineTo( x + w, y + h );
		ctx.lineTo( x, y + h );
		ctx.closePath();
		ctx.stroke();
		ctx.beginPath();
		ctx.moveTo( x + w - fold, y );
		ctx.lineTo( x + w - fold, y + fold );
		ctx.lineTo( x + w, y + fold );
		ctx.stroke();
		ctx.lineWidth = 2;
		var ly = y + h * 0.46;
		for ( var k = 0; k < 3; k++ ) {
			ctx.beginPath();
			ctx.moveTo( x + w * 0.22, ly );
			ctx.lineTo( x + w * 0.78, ly );
			ctx.stroke();
			ly += h * 0.17;
		}
		ctx.restore();
	}

	/* A small chain-link glyph (two interlocking capsules at 45°). Drawn
	   within an s×s box at (x, y). */
	function drawLinkIcon( ctx, x, y, s, color ) {
		ctx.save();
		ctx.translate( x + s / 2, y + s / 2 );
		ctx.rotate( -Math.PI / 4 );
		ctx.strokeStyle = color;
		ctx.lineWidth = 2;
		ctx.lineCap = 'round';
		ctx.lineJoin = 'round';
		var cw = s * 0.66, ch = s * 0.36, r = ch / 2;
		roundRect( ctx, -cw / 2 - s * 0.14, -ch / 2, cw, ch, r );
		ctx.stroke();
		roundRect( ctx, -cw / 2 + s * 0.14, -ch / 2, cw, ch, r );
		ctx.stroke();
		ctx.restore();
	}

	/* A megaphone / announcement glyph, drawn within a w×h box at (x, y). */
	function drawMegaphone( ctx, x, y, w, h, color ) {
		ctx.save();
		ctx.strokeStyle = color;
		ctx.lineWidth = 2.4;
		ctx.lineJoin = 'round';
		ctx.lineCap = 'round';
		/* horn — opening to the left, narrowing to the right */
		ctx.beginPath();
		ctx.moveTo( x + w * 0.02, y + h * 0.30 );
		ctx.lineTo( x + w * 0.58, y + h * 0.06 );
		ctx.lineTo( x + w * 0.58, y + h * 0.94 );
		ctx.lineTo( x + w * 0.02, y + h * 0.70 );
		ctx.closePath();
		ctx.stroke();
		/* handle */
		ctx.beginPath();
		ctx.moveTo( x + w * 0.20, y + h * 0.70 );
		ctx.lineTo( x + w * 0.20, y + h * 1.0 );
		ctx.stroke();
		/* sound waves */
		ctx.lineWidth = 2;
		ctx.beginPath();
		ctx.moveTo( x + w * 0.70, y + h * 0.34 ); ctx.lineTo( x + w * 0.92, y + h * 0.24 );
		ctx.moveTo( x + w * 0.74, y + h * 0.50 ); ctx.lineTo( x + w * 1.0, y + h * 0.50 );
		ctx.moveTo( x + w * 0.70, y + h * 0.66 ); ctx.lineTo( x + w * 0.92, y + h * 0.76 );
		ctx.stroke();
		ctx.restore();
	}

	function render( canvas, onDone ) {
		var ctx = canvas.getContext( '2d' );
		var accent  = cfg.accent || '#999999';
		var Mx = 92;          // side / bottom margin for content
		var FR = 44;          // frame inset

		// Draw in a fixed 1080x1440 design space, scaled up to the canvas's real
		// pixel size (e.g. 1500x2000) so exports come out at the requested size.
		var S = ( canvas.width || W ) / W;
		ctx.setTransform( S, 0, 0, S, 0, 0 );

		Promise.all( [ loadImg( cfg.image ), loadImg( cfg.logo ) ] ).then( function ( res ) {
			var bg = res[ 0 ], logo = res[ 1 ];

			ctx.clearRect( 0, 0, W, H );
			ctx.fillStyle = '#0e0e0e';
			ctx.fillRect( 0, 0, W, H );
			if ( bg ) { drawCover( ctx, bg ); }

			/* Bottom-to-black gradient. */
			var g = ctx.createLinearGradient( 0, H, 0, H * 0.34 );
			g.addColorStop( 0, 'rgba(0,0,0,0.96)' );
			g.addColorStop( 0.5, 'rgba(0,0,0,0.62)' );
			g.addColorStop( 1, 'rgba(0,0,0,0)' );
			ctx.fillStyle = g;
			ctx.fillRect( 0, 0, W, H );

			/* Soft top scrim for the logo. */
			var tg = ctx.createLinearGradient( 0, 0, 0, 300 );
			tg.addColorStop( 0, 'rgba(0,0,0,0.42)' );
			tg.addColorStop( 1, 'rgba(0,0,0,0)' );
			ctx.fillStyle = tg;
			ctx.fillRect( 0, 0, W, 300 );

			/* Agency logo, top-left, recoloured white. */
			if ( logo && logo.width ) {
				var lw = 135, lh = lw * ( logo.height / logo.width );
				var white = whiteLogo( logo );
				ctx.save();
				ctx.shadowColor = 'rgba(0,0,0,0.45)';
				ctx.shadowBlur = 14;
				ctx.shadowOffsetY = 2;
				ctx.drawImage( white, Mx, Mx, lw, lh );
				ctx.restore();
			}

			/* ---- Bottom block: location / date as thin outlined pills, then the
			   headline. The celebrity name is not shown separately — it already
			   appears in the title. ---- */
			var hero    = String( cfg.title || cfg.name || '' );
			var alignR  = isArabic( hero );
			var x = alignR ? ( W - Mx ) : Mx;
			var maxW = W - Mx * 2;
			var fam = alignR ? '"Cairo", Arial, sans-serif' : '"Raleway", Arial, sans-serif';

			var pillTexts = [];
			if ( cfg.location ) { pillTexts.push( String( cfg.location ).toUpperCase() ); }
			var dt = fmtDate( alignR );
			if ( dt ) { pillTexts.push( String( dt ).toUpperCase() ); }

			ctx.save();
			ctx.textBaseline = 'top';
			ctx.direction = alignR ? 'rtl' : 'ltr';
			ctx.textAlign = alignR ? 'right' : 'left';

			/* Auto-fit the title. Default: largest size at which the FULL title
			   fits within a sane line count and the vertical budget (never
			   sliced). One-line mode (cfg.titleOneLine): shrink until the whole
			   title fits on a single line, whatever the name length. */
			var heroText   = hero.toUpperCase();
			var maxLines   = 5;
			var heroBudget = 600;
			var heroSize   = 52;
			var hLH        = 0;
			var heroLines  = [];
			if ( cfg.titleOneLine ) {
				for ( heroSize = 58; heroSize >= 20; heroSize -= 1 ) {
					ctx.font = '800 ' + heroSize + 'px ' + fam;
					if ( ctx.measureText( heroText ).width <= maxW ) { break; }
				}
				if ( heroSize < 20 ) { heroSize = 20; }
				hLH = Math.round( heroSize * 1.06 );
				heroLines = [ heroText ];
			} else {
				for ( ; heroSize >= 30; heroSize -= 2 ) {
					var tf = '800 ' + heroSize + 'px ' + fam;
					hLH = Math.round( heroSize * 1.06 );
					heroLines = wrap( ctx, heroText, maxW, tf );
					if ( heroLines.length <= maxLines && heroLines.length * hLH <= heroBudget ) { break; }
				}
				if ( heroSize < 30 ) { heroSize = 30; hLH = Math.round( heroSize * 1.06 ); }
			}
			var heroFont = '800 ' + heroSize + 'px ' + fam;

			/* Lay the meta pills into rows (wrap to a second row if too wide). */
			var pillFont = '700 21px ' + fam;
			var pillH = 50, pillPadX = 26, pillGap = 14, pillRowGap = 14;
			ctx.font = pillFont;
			try { ctx.letterSpacing = '2px'; } catch ( ep ) {}
			var pillObjs = [];
			for ( var pi = 0; pi < pillTexts.length; pi++ ) {
				pillObjs.push( { text: pillTexts[ pi ], w: ctx.measureText( pillTexts[ pi ] ).width + pillPadX * 2 } );
			}
			var pillRows = [];
			var row = [], rowW = 0;
			for ( var pj = 0; pj < pillObjs.length; pj++ ) {
				var add = pillObjs[ pj ].w + ( row.length ? pillGap : 0 );
				if ( row.length && rowW + add > maxW ) { pillRows.push( row ); row = []; rowW = 0; add = pillObjs[ pj ].w; }
				row.push( pillObjs[ pj ] ); rowW += add;
			}
			if ( row.length ) { pillRows.push( row ); }
			try { ctx.letterSpacing = '0px'; } catch ( ep2 ) {}

			var pillsH  = pillRows.length ? ( pillRows.length * pillH + ( pillRows.length - 1 ) * pillRowGap + 28 ) : 0;
			var heroH   = heroLines.length * hLH;
			var accentGap = 24;

			/* Eyebrow (icon + label), above the accent line. Defaults to a
			   "Press Release" doc icon; the announcement card uses a megaphone. */
			var ebIsAnn   = ( cfg.eyebrowIcon === 'announcement' );
			var ebLabel   = ( cfg.eyebrow || 'PRESS RELEASE' ).toUpperCase();
			var ebFont    = '700 22px ' + fam;
			var ebIconW   = ebIsAnn ? 34 : 26;
			var ebIconH   = ebIsAnn ? 28 : 32;
			var ebIconGap = 13, ebGap = 22;
			var ebH       = ebIconH + ebGap;
			var drawEyeIcon = ebIsAnn ? drawMegaphone : drawDocIcon;

			/* Optional subtitle (e.g. "Nationality / Role") under the headline. */
			var subText   = cfg.subtitle ? String( cfg.subtitle ).toUpperCase() : '';
			var subFont   = '600 27px ' + fam;
			var subTopGap = 20;
			var subH      = subText ? ( subTopGap + 30 ) : 0;

			/* "Read more" / "Know more" line — static text (the card is an image,
			   so the URL is shown for the viewer to open). */
			var rmText   = ( cfg.moreLabel || 'Read more' ) + ': ' + ( cfg.moreUrl || 'ilikeagency.co/newsroom' );
			var rmFont   = '600 22px ' + fam;
			var rmIcon   = 21, rmIconGap = 11, rmTopGap = 26;
			var rmH      = rmTopGap + rmIcon;

			var blockH  = ebH + 3 + accentGap + pillsH + heroH + subH + rmH;
			var cy      = ( H - Mx ) - blockH;

			/* eyebrow row */
			ctx.save();
			ctx.font = ebFont;
			try { ctx.letterSpacing = '3px'; } catch ( ee ) {}
			var ebTextW = ctx.measureText( ebLabel ).width;
			if ( alignR ) {
				var ebIconX = ( W - Mx ) - ebIconW;
				drawEyeIcon( ctx, ebIconX, cy, ebIconW, ebIconH, '#ffffff' );
				ctx.textAlign = 'right';
				ctx.textBaseline = 'middle';
				ctx.fillStyle = '#ffffff';
				ctx.fillText( ebLabel, ebIconX - ebIconGap, cy + ebIconH / 2 + 1 );
			} else {
				drawEyeIcon( ctx, Mx, cy, ebIconW, ebIconH, '#ffffff' );
				ctx.textAlign = 'left';
				ctx.textBaseline = 'middle';
				ctx.fillStyle = '#ffffff';
				ctx.fillText( ebLabel, Mx + ebIconW + ebIconGap, cy + ebIconH / 2 + 1 );
			}
			try { ctx.letterSpacing = '0px'; } catch ( ee2 ) {}
			ctx.restore();
			cy += ebH;

			/* accent line */
			ctx.save();
			ctx.strokeStyle = accent;
			ctx.lineWidth = 3;
			ctx.beginPath();
			if ( alignR ) { ctx.moveTo( W - Mx, cy + 1 ); ctx.lineTo( W - Mx - 64, cy + 1 ); }
			else { ctx.moveTo( Mx, cy + 1 ); ctx.lineTo( Mx + 64, cy + 1 ); }
			ctx.stroke();
			ctx.restore();
			cy += 3 + accentGap;

			/* meta pills */
			if ( pillRows.length ) {
				ctx.font = pillFont;
				try { ctx.letterSpacing = '2px'; } catch ( ep3 ) {}
				for ( var ri = 0; ri < pillRows.length; ri++ ) {
					var px = x;
					for ( var ci = 0; ci < pillRows[ ri ].length; ci++ ) {
						var po = pillRows[ ri ][ ci ];
						var rx = alignR ? ( px - po.w ) : px;
						roundRect( ctx, rx, cy, po.w, pillH, pillH / 2 );
						ctx.lineWidth = 1.5;
						ctx.strokeStyle = 'rgba(255,255,255,0.5)';
						ctx.stroke();
						ctx.save();
						ctx.textBaseline = 'middle';
						ctx.textAlign = 'left';
						ctx.fillStyle = '#ffffff';
						ctx.fillText( po.text, rx + pillPadX, cy + pillH / 2 + 1 );
						ctx.restore();
						px = alignR ? ( rx - pillGap ) : ( rx + po.w + pillGap );
					}
					cy += pillH + pillRowGap;
				}
				try { ctx.letterSpacing = '0px'; } catch ( ep4 ) {}
				cy += 28 - pillRowGap;
			}

			/* headline */
			ctx.font = heroFont;
			ctx.fillStyle = '#ffffff';
			ctx.textBaseline = 'top';
			ctx.textAlign = alignR ? 'right' : 'left';
			ctx.shadowColor = 'rgba(0,0,0,0.55)';
			ctx.shadowBlur = 14;
			for ( var i = 0; i < heroLines.length; i++ ) {
				ctx.fillText( heroLines[ i ], x, cy + i * hLH );
			}
			cy += heroH;

			/* subtitle (e.g. nationality / role) */
			if ( subText ) {
				cy += subTopGap;
				ctx.shadowBlur = 0;
				ctx.font = subFont;
				ctx.fillStyle = '#cfcfcf';
				ctx.textBaseline = 'top';
				ctx.textAlign = alignR ? 'right' : 'left';
				try { ctx.letterSpacing = '1.5px'; } catch ( es ) {}
				ctx.fillText( subText, x, cy );
				try { ctx.letterSpacing = '0px'; } catch ( es2 ) {}
				cy += 30;
			}

			/* read more line */
			var rmY = cy + rmTopGap;
			ctx.shadowBlur = 0;
			ctx.font = rmFont;
			try { ctx.letterSpacing = '0.5px'; } catch ( er ) {}
			ctx.fillStyle = '#c9c9c9';
			ctx.textBaseline = 'middle';
			if ( alignR ) {
				var rmIconX = ( W - Mx ) - rmIcon;
				drawLinkIcon( ctx, rmIconX, rmY, rmIcon, '#c9c9c9' );
				ctx.textAlign = 'right';
				ctx.fillText( rmText, rmIconX - rmIconGap, rmY + rmIcon / 2 + 1 );
			} else {
				drawLinkIcon( ctx, Mx, rmY, rmIcon, '#c9c9c9' );
				ctx.textAlign = 'left';
				ctx.fillText( rmText, Mx + rmIcon + rmIconGap, rmY + rmIcon / 2 + 1 );
			}
			try { ctx.letterSpacing = '0px'; } catch ( er2 ) {}
			ctx.restore();

			if ( onDone ) { onDone( true ); }
		} ).catch( function () {
			if ( onDone ) { onDone( false ); }
		} );
	}

	function download( canvas ) {
		try {
			canvas.toBlob( function ( blob ) {
				if ( ! blob ) {
					window.alert( 'Could not export the card. Make sure the featured image is uploaded to this site.' );
					return;
				}
				var a = document.createElement( 'a' );
				a.href = URL.createObjectURL( blob );
				a.download = ( cfg.filename || 'news-card' ) + '.png';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( a.href );
			}, 'image/png' );
		} catch ( e ) {
			window.alert( 'Could not export the card (image security). Make sure the featured image is uploaded to this site.' );
		}
	}

	ready( function () {
		var canvas = document.querySelector( '.celb-card-canvas' );
		if ( ! canvas ) { return; }
		var dl = document.querySelector( '.celb-card-download' );
		var rf = document.querySelector( '.celb-card-refresh' );

		function loadFonts() {
			if ( ! document.fonts || ! document.fonts.load ) { return Promise.resolve(); }
			var faces = [ '800 86px "Cairo"', '700 30px "Cairo"', '600 22px "Cairo"', '800 86px "Raleway"', '700 30px "Raleway"', '600 22px "Raleway"' ];
			return Promise.all( faces.map( function ( f ) {
				try { return document.fonts.load( f ); } catch ( e ) { return Promise.resolve(); }
			} ) ).catch( function () {} );
		}

		function run() {
			if ( dl ) { dl.disabled = true; }
			loadFonts().then( function () {
				render( canvas, function ( ok ) {
					if ( dl ) { dl.disabled = ! ok; }
				} );
			} );
		}

		run();
		if ( dl ) { dl.addEventListener( 'click', function () { download( canvas ); } ); }
		if ( rf ) { rf.addEventListener( 'click', function ( e ) { e.preventDefault(); run(); } ); }
	} );
} )();
