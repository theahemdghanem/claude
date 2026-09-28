/**
 * Eye Comfort Dark Mode — recoloring engine.
 *
 * Instead of shipping a hand-written stylesheet that only knows about core
 * screens, this reads every stylesheet the page loaded (core, block editor,
 * other plugins) and writes a dark counterpart of each colour declaration,
 * scoped to `html.ecdm-dark`. Toggling the class switches everything at once.
 *
 * It runs in <head>, after the page's stylesheets have loaded and before the
 * body is painted, so there is no white flash.
 */
( function ( win ) {
	'use strict';

	if ( win.ECDM && win.ECDM.engine ) {
		return;
	}

	var cfg = win.ecdmConfig || {};
	var CLASS = 'ecdm-dark';
	var PREFIX = 'html.' + CLASS;
	var MARK = 'data-ecdm';

	/* ------------------------------------------------------------------ *
	 * Preferences and palettes
	 * ------------------------------------------------------------------ */

	var PALETTES = {
		// surface: lightness of cards (white in light mode); hue/sat tint the greys.
		dim: { surface: 0.175, hue: 220, sat: 0.09 },
		dark: { surface: 0.125, hue: 220, sat: 0.08 },
		black: { surface: 0.055, hue: 220, sat: 0.05 },
	};
	var TEXT = { soft: 0.74, normal: 0.84, bright: 0.93 };

	var prefs = {
		mode: cfg.mode || 'on',
		palette: PALETTES[ cfg.palette ] ? cfg.palette : 'dim',
		text: TEXT[ cfg.text ] ? cfg.text : 'normal',
		images: cfg.images !== false && cfg.images !== '0' && cfg.images !== 0,
		canvas: cfg.canvas !== false && cfg.canvas !== '0' && cfg.canvas !== 0,
	};

	var pal, textL, cache;

	function loadPalette() {
		pal = PALETTES[ prefs.palette ];
		textL = TEXT[ prefs.text ];
		cache = new Map();
	}
	loadPalette();

	/* ------------------------------------------------------------------ *
	 * Colour parsing and conversion
	 * ------------------------------------------------------------------ */

	var NAMED = {
		white: [ 255, 255, 255 ],
		black: [ 0, 0, 0 ],
		silver: [ 192, 192, 192 ],
		gray: [ 128, 128, 128 ],
		grey: [ 128, 128, 128 ],
		whitesmoke: [ 245, 245, 245 ],
		gainsboro: [ 220, 220, 220 ],
		lightgray: [ 211, 211, 211 ],
		lightgrey: [ 211, 211, 211 ],
		darkgray: [ 169, 169, 169 ],
		darkgrey: [ 169, 169, 169 ],
		dimgray: [ 105, 105, 105 ],
		dimgrey: [ 105, 105, 105 ],
		snow: [ 255, 250, 250 ],
		ivory: [ 255, 255, 240 ],
		beige: [ 245, 245, 220 ],
		linen: [ 250, 240, 230 ],
		aliceblue: [ 240, 248, 255 ],
		ghostwhite: [ 248, 248, 255 ],
		lightyellow: [ 255, 255, 224 ],
		red: [ 255, 0, 0 ],
		green: [ 0, 128, 0 ],
		blue: [ 0, 0, 255 ],
		navy: [ 0, 0, 128 ],
		maroon: [ 128, 0, 0 ],
		darkred: [ 139, 0, 0 ],
		darkblue: [ 0, 0, 139 ],
		darkgreen: [ 0, 100, 0 ],
	};

	function num( v, max ) {
		v = v.trim();
		if ( v.slice( -1 ) === '%' ) {
			return ( parseFloat( v ) / 100 ) * max;
		}
		return parseFloat( v );
	}

	function parseColor( str ) {
		var s = str.trim().toLowerCase();
		var m, parts, r, g, b, a;

		if ( s[ 0 ] === '#' ) {
			var h = s.slice( 1 );
			if ( ! /^[0-9a-f]+$/.test( h ) ) {
				return null;
			}
			if ( h.length === 3 || h.length === 4 ) {
				h = h.split( '' ).map( function ( c ) {
					return c + c;
				} ).join( '' );
			}
			if ( h.length !== 6 && h.length !== 8 ) {
				return null;
			}
			return {
				r: parseInt( h.substr( 0, 2 ), 16 ),
				g: parseInt( h.substr( 2, 2 ), 16 ),
				b: parseInt( h.substr( 4, 2 ), 16 ),
				a: h.length === 8 ? parseInt( h.substr( 6, 2 ), 16 ) / 255 : 1,
			};
		}

		m = s.match( /^(rgba?|hsla?)\((.*)\)$/ );
		if ( m ) {
			if ( m[ 2 ].indexOf( 'var(' ) !== -1 || m[ 2 ].indexOf( 'calc(' ) !== -1 || m[ 2 ].indexOf( 'from ' ) !== -1 ) {
				return null;
			}
			parts = m[ 2 ].replace( /\s*\/\s*/, ',' ).split( /\s*,\s*|\s+/ ).filter( Boolean );
			if ( parts.length < 3 ) {
				return null;
			}
			a = parts[ 3 ] !== undefined ? num( parts[ 3 ], 1 ) : 1;
			if ( m[ 1 ][ 0 ] === 'r' ) {
				r = num( parts[ 0 ], 255 );
				g = num( parts[ 1 ], 255 );
				b = num( parts[ 2 ], 255 );
			} else {
				var rgb = hslToRgb( parseFloat( parts[ 0 ] ) / 360, num( parts[ 1 ], 1 ), num( parts[ 2 ], 1 ) );
				r = rgb[ 0 ];
				g = rgb[ 1 ];
				b = rgb[ 2 ];
			}
			if ( [ r, g, b, a ].some( isNaN ) ) {
				return null;
			}
			return { r: r, g: g, b: b, a: a };
		}

		if ( NAMED[ s ] ) {
			return { r: NAMED[ s ][ 0 ], g: NAMED[ s ][ 1 ], b: NAMED[ s ][ 2 ], a: 1 };
		}
		return null;
	}

	function rgbToHsl( r, g, b ) {
		r /= 255;
		g /= 255;
		b /= 255;
		var max = Math.max( r, g, b );
		var min = Math.min( r, g, b );
		var l = ( max + min ) / 2;
		var h = 0;
		var s = 0;
		var d = max - min;
		if ( d ) {
			s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );
			if ( max === r ) {
				h = ( g - b ) / d + ( g < b ? 6 : 0 );
			} else if ( max === g ) {
				h = ( b - r ) / d + 2;
			} else {
				h = ( r - g ) / d + 4;
			}
			h /= 6;
		}
		return { h: h * 360, s: s, l: l, c: d };
	}

	function hslToRgb( h, s, l ) {
		if ( ! s ) {
			return [ l * 255, l * 255, l * 255 ];
		}
		function hue( p, q, t ) {
			if ( t < 0 ) {
				t += 1;
			}
			if ( t > 1 ) {
				t -= 1;
			}
			if ( t < 1 / 6 ) {
				return p + ( q - p ) * 6 * t;
			}
			if ( t < 1 / 2 ) {
				return q;
			}
			if ( t < 2 / 3 ) {
				return p + ( q - p ) * ( 2 / 3 - t ) * 6;
			}
			return p;
		}
		var q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s;
		var p = 2 * l - q;
		return [ hue( p, q, h + 1 / 3 ) * 255, hue( p, q, h ) * 255, hue( p, q, h - 1 / 3 ) * 255 ];
	}

	// Relative luminance (WCAG).
	function luminance( r, g, b ) {
		function ch( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * ch( r ) + 0.7152 * ch( g ) + 0.0722 * ch( b );
	}

	function textY() {
		return { soft: 0.24, normal: 0.32, bright: 0.42 }[ prefs.text ];
	}

	function clamp( v ) {
		return Math.max( 0, Math.min( 1, v ) );
	}

	function out( h, s, l, a ) {
		var rgb = hslToRgb( ( ( h % 360 ) + 360 ) % 360 / 360, clamp( s ), clamp( l ) );
		var r = Math.round( rgb[ 0 ] );
		var g = Math.round( rgb[ 1 ] );
		var b = Math.round( rgb[ 2 ] );
		if ( a < 1 ) {
			return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + Math.round( a * 1000 ) / 1000 + ')';
		}
		return 'rgb(' + r + ', ' + g + ', ' + b + ')';
	}

	/* ------------------------------------------------------------------ *
	 * The colour maps. Each keeps hue, flips lightness in a way that suits
	 * its role: light surfaces become dark ones (white cards stay a touch
	 * lighter than the page behind them), dark text becomes light, borders
	 * stay subtle, and saturated accents (buttons, badges) are left alone.
	 * ------------------------------------------------------------------ */

	function surfaceL( l ) {
		if ( l >= 0.985 ) {
			return pal.surface; // White cards and panels.
		}
		if ( l >= 0.93 ) {
			return Math.max( 0.015, pal.surface - 0.04 ); // Page backgrounds, sunken areas.
		}
		return pal.surface + ( 0.93 - l ) * 0.38; // Hover states, raised areas.
	}

	var MAPS = {
		bg: function ( c, hsl ) {
			if ( hsl.l < 0.5 ) {
				return null; // Already dark: menus, tooltips, primary buttons.
			}
			if ( hsl.c > 0.05 ) {
				if ( hsl.l < 0.7 && hsl.c > 0.2 ) {
					return null; // Saturated accent fills.
				}
				return out( hsl.h, Math.min( hsl.s, 0.5 ) * 0.8, surfaceL( hsl.l ) + 0.04, c.a );
			}
			return out( pal.hue, pal.sat, surfaceL( hsl.l ), c.a );
		},
		text: function ( c, hsl ) {
			if ( hsl.c < 0.12 ) {
				if ( hsl.l >= 0.5 ) {
					return null; // Already light, or a muted mid grey that still reads.
				}
				return out( pal.hue, 0.06, textL - hsl.l * 0.45, c.a );
			}
			if ( luminance( c.r, c.g, c.b ) >= textY() ) {
				return null;
			}
			// Raise lightness until the colour reads well on dark (blues need
			// more lift than yellows for the same perceived brightness).
			var s = Math.min( hsl.s, 0.85 );
			var l = Math.max( hsl.l, 0.6 );
			var rgb;
			for ( ; l < 0.92; l += 0.02 ) {
				rgb = hslToRgb( hsl.h / 360, s, l );
				if ( luminance( rgb[ 0 ], rgb[ 1 ], rgb[ 2 ] ) >= textY() ) {
					break;
				}
			}
			return out( hsl.h, s, l, c.a );
		},
		border: function ( c, hsl ) {
			if ( hsl.c < 0.12 ) {
				return out( pal.hue, pal.sat, 0.2 + ( pal.surface - 0.175 ) * 0.6 + ( 1 - hsl.l ) * 0.36, c.a );
			}
			if ( hsl.l > 0.7 ) {
				return out( hsl.h, Math.min( hsl.s, 0.5 ), 0.34, c.a );
			}
			return null;
		},
		shadow: function ( c, hsl ) {
			return hsl.l > 0.6 ? MAPS.border( c, hsl ) : null;
		},
		// Custom properties can hold either role; guess from lightness.
		auto: function ( c, hsl ) {
			if ( hsl.l > 0.6 ) {
				return MAPS.bg( c, hsl );
			}
			if ( hsl.l < 0.4 ) {
				return MAPS.text( c, hsl );
			}
			return null;
		},
	};

	function mapColor( str, role ) {
		var key = role + str;
		if ( cache.has( key ) ) {
			return cache.get( key );
		}
		var res = str;
		var c = parseColor( str );
		if ( c && c.a > 0 ) {
			var mapped = MAPS[ role ]( c, rgbToHsl( c.r, c.g, c.b ) );
			if ( mapped ) {
				res = mapped;
			}
		}
		cache.set( key, res );
		return res;
	}

	/* ------------------------------------------------------------------ *
	 * Value rewriting
	 * ------------------------------------------------------------------ */

	var TOKEN = /#[0-9a-fA-F]{3,8}\b|(?:rgba?|hsla?)\([^()]*\)|\b[a-zA-Z]+\b/g;

	// Rewrite the colours inside a value, leaving var(), url() and other
	// functions' arguments (apart from colour functions) untouched.
	function mapValue( value, role ) {
		var result = '';
		var i = 0;
		var len = value.length;
		while ( i < len ) {
			var fn = value.slice( i ).match( /^(url|var|env|attr)\(/i );
			if ( fn ) {
				var end = matchParen( value, i + fn[ 0 ].length - 1 );
				var chunk = value.slice( i, end + 1 );
				var kind = fn[ 1 ].toLowerCase();
				if ( kind === 'url' ) {
					chunk = mapSvgUrl( chunk );
				} else if ( kind === 'var' ) {
					chunk = mapVarFallback( chunk, role );
				}
				result += chunk;
				i = end + 1;
				continue;
			}
			var j = i;
			while ( j < len && ! /^(url|var|env|attr)\(/i.test( value.slice( j, j + 5 ) ) ) {
				j++;
			}
			result += value.slice( i, j ).replace( TOKEN, function ( t ) {
				return /^[a-z]+$/i.test( t ) && ! NAMED[ t.toLowerCase() ] ? t : mapColor( t, role );
			} );
			i = j;
		}
		return result;
	}

	// var(--token, #fff): design tokens that are often undefined, so the
	// fallback is what shows. Recolour the fallback.
	function mapVarFallback( chunk, role ) {
		var depth = 0;
		for ( var k = 4; k < chunk.length - 1; k++ ) {
			var ch = chunk[ k ];
			if ( ch === '(' ) {
				depth++;
			} else if ( ch === ')' ) {
				depth--;
			} else if ( ch === ',' && ! depth ) {
				return chunk.slice( 0, k + 1 ) + mapValue( chunk.slice( k + 1, -1 ), role ) + ')';
			}
		}
		return chunk;
	}

	function matchParen( s, open ) {
		var depth = 0;
		var quote = null;
		for ( var k = open; k < s.length; k++ ) {
			var ch = s[ k ];
			if ( quote ) {
				if ( ch === '\\' ) {
					k++;
				} else if ( ch === quote ) {
					quote = null;
				}
			} else if ( ch === '"' || ch === "'" ) {
				quote = ch;
			} else if ( ch === '(' ) {
				depth++;
			} else if ( ch === ')' ) {
				depth--;
				if ( ! depth ) {
					return k;
				}
			}
		}
		return s.length - 1;
	}

	// Icons drawn as inline SVG data URIs (select arrows, checkmarks, many
	// plugin icons) have their colours baked in; recolour those as text.
	var SVG_PAINT = /((?:fill|stroke|stop-color|color)\s*(?:=\s*["']?|:\s*))(#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|[a-zA-Z]+)/g;

	function mapSvgUrl( chunk ) {
		if ( chunk.indexOf( 'svg' ) === -1 || ! /data:image\/svg\+xml/i.test( chunk ) ) {
			return chunk;
		}
		var m = chunk.match( /^url\(\s*(["']?)(data:image\/svg\+xml[^,]*),([\s\S]*?)\1\s*\)$/i );
		if ( ! m ) {
			return chunk;
		}
		var head = m[ 2 ];
		var body = m[ 3 ];
		var base64 = /;base64$/i.test( head );
		var svg;
		try {
			svg = base64 ? atob( body ) : decodeURIComponent( body.replace( /\\(["'])/g, '$1' ) );
		} catch ( e ) {
			return chunk;
		}
		var mapped = svg.replace( SVG_PAINT, function ( all, pre, col ) {
			return parseColor( col ) ? pre + mapColor( col, 'text' ) : all;
		} );
		if ( mapped === svg ) {
			return chunk;
		}
		try {
			body = base64 ? btoa( mapped ) : encodeURIComponent( mapped ).replace( /'/g, '%27' ).replace( /\(/g, '%28' ).replace( /\)/g, '%29' );
		} catch ( e ) {
			return chunk;
		}
		return 'url("' + head + ',' + body + '")';
	}

	var ROLE = {
		color: 'text',
		'-webkit-text-fill-color': 'text',
		'-webkit-text-stroke-color': 'text',
		'caret-color': 'text',
		fill: 'text',
		stroke: 'text',
		'background-color': 'bg',
		'background-image': 'bg',
		'box-shadow': 'shadow',
		'text-shadow': 'shadow',
		'outline-color': 'border',
		'column-rule-color': 'border',
		'text-decoration-color': 'border',
		'text-emphasis-color': 'border',
		content: 'svg',
		'list-style-image': 'svg',
		'border-image-source': 'bg',
		'accent-color': 'keep',
	};

	var BG_VAR = /background|(^|-)bg(-|$)|surface|canvas|(^|-)base$/i;
	var FG_VAR = /foreground|(^|-)fg(-|$)|(^|-)text(-|$)|contrast$/i;

	// A rule or element that declares a dark background token is already a
	// dark theme (e.g. the site editor sidebar); leave its tokens alone.
	// Token sets that define both light and dark backgrounds (a design
	// system's palette) are decided by majority.
	function declaresDarkTheme( style ) {
		var dark = 0;
		var light = 0;
		for ( var k = 0; k < style.length; k++ ) {
			var prop = style[ k ];
			if ( prop.charCodeAt( 0 ) === 45 && prop.charCodeAt( 1 ) === 45 && BG_VAR.test( prop ) && ! /inverted/i.test( prop ) ) {
				var c = parseColor( style.getPropertyValue( prop ) );
				if ( c && c.a > 0.5 ) {
					if ( rgbToHsl( c.r, c.g, c.b ).l < 0.4 ) {
						dark++;
					} else {
						light++;
					}
				}
			}
		}
		return dark > light;
	}

	function roleOf( prop ) {
		if ( ROLE[ prop ] ) {
			return ROLE[ prop ];
		}
		if ( prop.charCodeAt( 0 ) === 45 && prop.charCodeAt( 1 ) === 45 ) {
			if ( /border|outline|divider|separator|stroke/i.test( prop ) ) {
				return 'varborder';
			}
			if ( /shadow/i.test( prop ) ) {
				return 'varshadow';
			}
			// Guess the role from the name; "inverted" swaps it.
			var bg = BG_VAR.test( prop );
			var fg = FG_VAR.test( prop );
			if ( bg !== fg ) {
				return ( bg !== /inverted/i.test( prop ) ) ? 'varbg' : 'vartext';
			}
			return 'var';
		}
		if ( /^border-.*color$/.test( prop ) ) {
			return 'border';
		}
		return null;
	}

	// Text coloured through a variable (e.g. the admin accent) cannot be
	// mapped up front; lift its lightness in CSS instead, where supported.
	// Only accent-like variables: greys held in variables are already mapped,
	// and lifting "inverted" pairs would make them unreadable.
	var ACCENT_VAR = /var\(\s*--[\w-]*(theme-color|accent|primary|brand|link|admin-color|highlight)/i;
	var RELATIVE = !! ( win.CSS && win.CSS.supports && win.CSS.supports( 'color', 'oklch(from red max(l, 0.5) c h)' ) );

	function liftText( value ) {
		var floor = { soft: 0.72, normal: 0.78, bright: 0.84 }[ prefs.text ];
		return 'oklch(from ' + value.trim() + ' max(l, ' + floor + ') c h)';
	}

	function mapDecl( prop, value, role ) {
		if ( role === 'text' && RELATIVE && ACCENT_VAR.test( value ) && ! /inverted|contrast/i.test( value ) && value.indexOf( 'url(' ) === -1 ) {
			return liftText( value );
		}
		switch ( role ) {
			case 'keep':
				return value;
			case 'svg':
				return value.indexOf( 'url(' ) === -1 ? value : mapValue( value, 'text' );
			case 'varbg':
			case 'vartext':
				if ( parseColor( value ) ) {
					return mapColor( value.trim(), role.slice( 3 ) );
				}
				return /#[0-9a-f]{3}|rgba?\(|hsla?\(|gradient/i.test( value ) ? mapValue( value, role.slice( 3 ) ) : value;
			case 'var':
			case 'varborder':
			case 'varshadow':
				// Only touch custom properties that plainly hold colours.
				if ( ! /#[0-9a-f]{3}|rgba?\(|hsla?\(|gradient|^\s*[a-z]+\s*$/i.test( value ) ) {
					return value;
				}
				if ( role === 'var' && parseColor( value ) ) {
					return mapColor( value.trim(), 'auto' );
				}
				return mapValue( value, role === 'var' ? 'bg' : role.slice( 3 ) );
			default:
				return mapValue( value, role );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Stylesheet processing
	 * ------------------------------------------------------------------ */

	function splitSelectors( text ) {
		var list = [];
		var depth = 0;
		var start = 0;
		for ( var k = 0; k < text.length; k++ ) {
			var ch = text[ k ];
			if ( ch === '(' || ch === '[' ) {
				depth++;
			} else if ( ch === ')' || ch === ']' ) {
				depth--;
			} else if ( ch === ',' && ! depth ) {
				list.push( text.slice( start, k ) );
				start = k + 1;
			}
		}
		list.push( text.slice( start ) );
		return list;
	}

	function scopeSelector( text ) {
		return splitSelectors( text ).map( function ( sel ) {
			sel = sel.trim();
			var m = sel.match( /^(html|:root)(?![\w-])/i );
			if ( m ) {
				return PREFIX + sel.slice( m[ 0 ].length );
			}
			return PREFIX + ' ' + sel;
		} ).join( ',' );
	}

	// Shorthands written with var() leave their longhands empty in the
	// CSSOM. Pull out the colour part as its longhand so the dark rules
	// keep the same cascade without resetting widths or styles.
	var SHORTHANDS = [ 'background', 'border', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-block', 'border-inline', 'border-block-start', 'border-block-end', 'border-inline-start', 'border-inline-end', 'outline', 'text-decoration', 'column-rule' ];

	function splitTokens( value ) {
		var list = [];
		var depth = 0;
		var cur = '';
		for ( var k = 0; k < value.length; k++ ) {
			var ch = value[ k ];
			if ( ch === '(' ) {
				depth++;
			} else if ( ch === ')' ) {
				depth--;
			}
			if ( /\s/.test( ch ) && ! depth ) {
				if ( cur ) {
					list.push( cur );
				}
				cur = '';
			} else {
				cur += ch;
			}
		}
		if ( cur ) {
			list.push( cur );
		}
		return list;
	}

	function shorthandColor( name, value ) {
		if ( name === 'border-color' ) {
			return [ name, value ];
		}
		var tokens = splitTokens( value.trim() );
		var colors = tokens.filter( function ( t ) {
			return ( /^var\(/i.test( t ) && ! /^var\(\s*--[\w-]*(width|size|style|radius|spacing|image|gradient|position|repeat)/i.test( t ) ) || parseColor( t );
		} );
		if ( colors.length !== 1 || ( tokens.length === 1 && name !== 'background' ) ) {
			return null; // Ambiguous: leave it alone rather than break layout.
		}
		return [ name + '-color', colors[ 0 ] ];
	}

	function processStyleRule( rule, parts ) {
		var style = rule.style;
		var decls = '';
		var pending = false;
		var darkTheme = null;
		for ( var k = 0; k < style.length; k++ ) {
			var prop = style[ k ];
			var role = roleOf( prop );
			if ( ! role ) {
				continue;
			}
			var value = style.getPropertyValue( prop );
			if ( ! value ) {
				pending = true; // Part of a shorthand written with var().
				continue;
			}
			if ( role.indexOf( 'var' ) === 0 ) {
				if ( darkTheme === null ) {
					darkTheme = declaresDarkTheme( style );
				}
				if ( darkTheme ) {
					role = 'keep';
				}
			}
			// Every colour declaration is re-emitted, changed or not, so the
			// dark rules keep the same relative cascade as the originals.
			decls += prop + ':' + mapDecl( prop, value, role ) + ( style.getPropertyPriority( prop ) ? ' !important;' : ';' );
		}
		if ( pending ) {
			var head = '';
			for ( var s = 0; s < SHORTHANDS.length; s++ ) {
				var sv = style.getPropertyValue( SHORTHANDS[ s ] );
				var pair = sv && sv.indexOf( 'var(' ) !== -1 && shorthandColor( SHORTHANDS[ s ], sv );
				if ( pair ) {
					head += pair[ 0 ] + ':' + mapDecl( pair[ 0 ], pair[ 1 ], roleOf( pair[ 0 ] ) ) + ( style.getPropertyPriority( SHORTHANDS[ s ] ) ? ' !important;' : ';' );
				}
			}
			decls = head + decls;
		}
		if ( decls && rule.selectorText ) {
			parts.push( scopeSelector( rule.selectorText ) + '{' + decls + '}' );
		}
	}

	function walk( rules, parts ) {
		for ( var k = 0; k < rules.length; k++ ) {
			var rule = rules[ k ];
			var inner;
			try {
				if ( rule.selectorText !== undefined && rule.style ) {
					processStyleRule( rule, parts );
				} else if ( rule.styleSheet ) {
					walk( rule.styleSheet.cssRules, parts ); // @import
				} else if ( rule.cssRules ) {
					inner = [];
					walk( rule.cssRules, inner );
					if ( ! inner.length ) {
						continue;
					}
					if ( rule.media && rule.conditionText === undefined ) {
						parts.push( '@media ' + rule.media.mediaText + '{' + inner.join( '' ) + '}' );
					} else if ( rule.conditionText !== undefined ) {
						var at = rule.constructor && /Supports/.test( rule.constructor.name ) ? '@supports ' : /Container/.test( rule.constructor && rule.constructor.name ) ? '@container ' : '@media ';
						parts.push( at + rule.conditionText + '{' + inner.join( '' ) + '}' );
					} else {
						// @layer blocks and the like: emit unlayered.
						parts.push( inner.join( '' ) );
					}
				}
			} catch ( e ) {
				// One odd rule must never break the page.
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Per-document state. The main document gets one, and so do editor
	 * iframes (block editor canvas, classic editor) when enabled.
	 * ------------------------------------------------------------------ */

	var docs = [];
	var active = false;

	function Doc( doc ) {
		this.doc = doc;
		this.win = doc.defaultView;
		this.overrides = new Map(); // owner node -> override <style>
		this.lengths = new WeakMap(); // sheet -> rule count at last pass
		this.inline = new Map(); // element -> original style attribute
		this.written = new WeakMap(); // element -> style attribute we wrote
		this.pending = new Set();
		this.timer = 0;
		this.observer = null;
	}

	Doc.prototype.start = function () {
		var self = this;
		var root = this.doc.documentElement;
		root.classList.add( CLASS );
		root.setAttribute( 'data-ecdm-engine', '' );
		root.setAttribute( 'data-ecdm-palette', prefs.palette );
		this.base();
		this.processAll( true );
		// Stylesheets still downloading get picked up when they arrive.
		this.doc.querySelectorAll( 'link[rel~="stylesheet"]' ).forEach( function ( link ) {
			self.watchLink( link, false );
		} );
		this.processInline( this.doc );
		if ( ! this.observer ) {
			this.observer = new this.win.MutationObserver( function ( list ) {
				self.onMutations( list );
			} );
			this.observer.observe( root, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'style' ], characterData: true } );
			patchSheets( this.win );
		}
		this.scanFrames( this.doc );
	};

	Doc.prototype.stop = function () {
		var root = this.doc.documentElement;
		root.classList.remove( CLASS );
		root.removeAttribute( 'data-ecdm-engine' );
		if ( this.observer ) {
			this.observer.disconnect();
			this.observer = null;
		}
		this.overrides.forEach( function ( el ) {
			el.remove();
		} );
		this.overrides.clear();
		this.lengths = new WeakMap();
		this.inline.forEach( function ( orig, el ) {
			if ( orig === null ) {
				el.removeAttribute( 'style' );
			} else {
				el.setAttribute( 'style', orig );
			}
		} );
		this.inline.clear();
		this.written = new WeakMap();
		var base = this.doc.getElementById( 'ecdm-base' );
		if ( base ) {
			base.remove();
		}
	};

	Doc.prototype.base = function () {
		// No background on <html>: that would stop a page's body background
		// from filling the window. color-scheme makes the bare canvas dark.
		var fg = out( pal.hue, 0.06, textL, 1 );
		var css = PREFIX + '{color-scheme:dark;color:' + fg + '}' +
			PREFIX + ' ::selection{background-color:' + out( 212, 0.55, 0.38, 1 ) + ';color:#fff}';
		if ( prefs.images ) {
			css += PREFIX + ' img,' + PREFIX + ' video{filter:brightness(.88) contrast(1.04)}';
		}
		var el = this.doc.getElementById( 'ecdm-base' );
		if ( ! el ) {
			el = this.doc.createElement( 'style' );
			el.id = 'ecdm-base';
			el.setAttribute( MARK, '' );
			var head = this.doc.head || this.doc.documentElement;
			// First in <head>, so any page rule for <html> still wins.
			head.insertBefore( el, head.firstChild );
		}
		el.textContent = css;
	};

	Doc.prototype.processAll = function ( force ) {
		var sheets = this.doc.styleSheets;
		for ( var k = 0; k < sheets.length; k++ ) {
			this.processSheet( sheets[ k ], force );
		}
	};

	Doc.prototype.processSheet = function ( sheet, force ) {
		var owner = sheet.ownerNode;
		if ( ! owner || owner.hasAttribute( MARK ) || sheet.disabled ) {
			return;
		}
		var rules;
		try {
			rules = sheet.cssRules;
		} catch ( e ) {
			return; // Cross-origin stylesheet: not readable.
		}
		if ( ! rules ) {
			return;
		}
		if ( ! force && this.lengths.get( sheet ) === rules.length ) {
			return;
		}
		this.lengths.set( sheet, rules.length );
		var parts = [];
		walk( rules, parts );
		var el = this.overrides.get( owner );
		if ( ! parts.length ) {
			if ( el ) {
				el.textContent = '';
			}
			return;
		}
		if ( ! el ) {
			el = this.doc.createElement( 'style' );
			el.setAttribute( MARK, '' );
			this.overrides.set( owner, el );
		}
		var css = parts.join( '\n' );
		if ( el.textContent !== css ) {
			el.textContent = css;
		}
		// Keep overrides in the same order as their sources.
		if ( el.previousSibling !== owner && owner.parentNode ) {
			owner.parentNode.insertBefore( el, owner.nextSibling );
		}
	};

	Doc.prototype.schedule = function ( sheet ) {
		var self = this;
		this.pending.add( sheet );
		if ( ! this.timer ) {
			this.timer = this.win.requestAnimationFrame( function () {
				self.timer = 0;
				var list = Array.from( self.pending );
				self.pending.clear();
				list.forEach( function ( s ) {
					self.processSheet( s );
				} );
			} );
		}
	};

	var INLINE_HINT = /color|background|border|fill|stroke|shadow|outline|--/i;

	Doc.prototype.processElement = function ( el ) {
		var attr = el.getAttribute( 'style' );
		if ( ! attr || this.written.get( el ) === attr || ! INLINE_HINT.test( attr ) ) {
			return;
		}
		if ( el.hasAttribute( MARK ) ) {
			return;
		}
		var style = el.style;
		var changes = [];
		var darkTheme = declaresDarkTheme( style );
		for ( var k = 0; k < style.length; k++ ) {
			var prop = style[ k ];
			var role = roleOf( prop );
			if ( ! role || ( darkTheme && role.indexOf( 'var' ) === 0 ) ) {
				continue;
			}
			var value = style.getPropertyValue( prop );
			var mapped = value && mapDecl( prop, value, role );
			if ( mapped && mapped !== value ) {
				changes.push( [ prop, mapped, style.getPropertyPriority( prop ) ] );
			}
		}
		if ( ! changes.length ) {
			return;
		}
		if ( ! this.inline.has( el ) ) {
			this.inline.set( el, attr );
		} else if ( this.written.get( el ) !== attr ) {
			this.inline.set( el, attr ); // The page changed it since.
		}
		changes.forEach( function ( c ) {
			style.setProperty( c[ 0 ], c[ 1 ], c[ 2 ] );
		} );
		this.written.set( el, el.getAttribute( 'style' ) );
	};

	Doc.prototype.processInline = function ( node ) {
		if ( node.nodeType !== 1 && node.nodeType !== 9 ) {
			return;
		}
		if ( node.nodeType === 1 && node.hasAttribute( 'style' ) ) {
			this.processElement( node );
		}
		var list = node.querySelectorAll( '[style]' );
		for ( var k = 0; k < list.length; k++ ) {
			this.processElement( list[ k ] );
		}
	};

	Doc.prototype.watchLink = function ( link, now ) {
		var self = this;
		if ( link.__ecdmWatched === this ) {
			return;
		}
		link.__ecdmWatched = this;
		if ( now !== false && link.sheet ) {
			try {
				if ( link.sheet.cssRules ) {
					self.processSheet( link.sheet, true );
				}
			} catch ( e ) {}
		}
		link.addEventListener( 'load', function () {
			if ( active && link.sheet ) {
				self.processSheet( link.sheet, true );
			}
		} );
	};

	Doc.prototype.onMutations = function ( list ) {
		var self = this;
		list.forEach( function ( m ) {
			if ( m.type === 'attributes' ) {
				self.processElement( m.target );
				return;
			}
			var target = m.target.nodeType === 3 ? m.target.parentNode : m.target;
			if ( target && target.nodeName === 'STYLE' && ! target.hasAttribute( MARK ) && target.sheet ) {
				self.schedule( target.sheet ); // Text inserted into a <style>.
			}
			m.addedNodes.forEach( function ( n ) {
				if ( n.nodeType !== 1 || n.hasAttribute( MARK ) ) {
					return;
				}
				if ( n.nodeName === 'STYLE' ) {
					if ( n.sheet ) {
						self.schedule( n.sheet );
					}
				} else if ( n.nodeName === 'LINK' ) {
					if ( /stylesheet/i.test( n.rel ) ) {
						self.watchLink( n );
					}
				} else {
					self.processInline( n );
					if ( n.nodeName === 'IFRAME' ) {
						attachFrame( n );
					} else if ( n.querySelector ) {
						self.scanFrames( n );
						n.querySelectorAll( 'link[rel~="stylesheet"],style' ).forEach( function ( s ) {
							if ( s.nodeName === 'LINK' ) {
								self.watchLink( s );
							} else if ( s.sheet ) {
								self.schedule( s.sheet );
							}
						} );
					}
				}
			} );
			m.removedNodes.forEach( function ( n ) {
				var el = self.overrides.get( n );
				if ( el ) {
					el.remove();
					self.overrides.delete( n );
				}
			} );
		} );
	};

	Doc.prototype.scanFrames = function ( node ) {
		if ( ! prefs.canvas || ! node.querySelectorAll ) {
			return;
		}
		node.querySelectorAll( 'iframe' ).forEach( attachFrame );
	};

	// Stylesheets filled through the CSSOM (the block editor's CSS-in-JS)
	// never show up as DOM mutations; hook the CSSOM itself.
	function patchSheets( w ) {
		var proto = w.CSSStyleSheet && w.CSSStyleSheet.prototype;
		if ( ! proto || proto.__ecdm ) {
			return;
		}
		proto.__ecdm = true;
		[ 'insertRule', 'deleteRule', 'addRule', 'removeRule', 'replaceSync' ].forEach( function ( name ) {
			var orig = proto[ name ];
			if ( typeof orig !== 'function' ) {
				return;
			}
			proto[ name ] = function () {
				var res = orig.apply( this, arguments );
				if ( active ) {
					var sheet = this;
					docs.forEach( function ( d ) {
						if ( sheet.ownerNode && sheet.ownerNode.ownerDocument === d.doc ) {
							d.schedule( sheet );
						}
					} );
				}
				return res;
			};
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Editor iframes
	 * ------------------------------------------------------------------ */

	var FRAME_SELECTOR = 'iframe[name="editor-canvas"], iframe.edit-site-visual-editor__editor-canvas, iframe[id$="_ifr"]';

	function attachFrame( frame ) {
		if ( ! frame.matches( FRAME_SELECTOR ) ) {
			return;
		}
		if ( ! frame.__ecdmBound ) {
			frame.__ecdmBound = true;
			frame.addEventListener( 'load', function () {
				attachFrameDoc( frame );
			} );
		}
		attachFrameDoc( frame );
	}

	function attachFrameDoc( frame ) {
		if ( ! active || ! prefs.canvas ) {
			return;
		}
		var d;
		try {
			d = frame.contentDocument;
		} catch ( e ) {
			return; // Cross-origin.
		}
		if ( ! d || ! d.documentElement || ! d.head || d.documentElement.hasAttribute( 'data-ecdm-engine' ) ) {
			return;
		}
		var state = new Doc( d );
		docs.push( state );
		state.start();
	}

	/* ------------------------------------------------------------------ *
	 * Switching
	 * ------------------------------------------------------------------ */

	var mql = win.matchMedia ? win.matchMedia( '(prefers-color-scheme: dark)' ) : null;

	function wanted() {
		if ( prefs.mode === 'on' ) {
			return true;
		}
		return prefs.mode === 'auto' && !! ( mql && mql.matches );
	}

	function prune() {
		docs = docs.filter( function ( d ) {
			return d.doc === document || !! d.doc.defaultView;
		} );
	}

	function enable() {
		active = true;
		prune();
		if ( ! docs.length ) {
			docs.push( new Doc( document ) );
		}
		docs.forEach( function ( d ) {
			d.start();
		} );
		fire();
	}

	function disable() {
		active = false;
		prune();
		docs.forEach( function ( d ) {
			d.stop();
		} );
		docs = docs.filter( function ( d ) {
			return d.doc === document;
		} );
		fire();
	}

	function sync() {
		var want = wanted();
		if ( want && ! active ) {
			enable();
		} else if ( ! want && active ) {
			disable();
		}
	}

	function fire() {
		document.documentElement.setAttribute( 'data-ecdm-state', active ? 'dark' : 'light' );
		try {
			document.dispatchEvent( new CustomEvent( 'ecdm:change', { detail: { active: active, prefs: prefs } } ) );
		} catch ( e ) {}
	}

	function setPrefs( next ) {
		var repaint = ( next.palette && next.palette !== prefs.palette ) ||
			( next.text && next.text !== prefs.text ) ||
			( next.images !== undefined && !! next.images !== prefs.images );
		var frames = next.canvas !== undefined && !! next.canvas !== prefs.canvas;
		Object.keys( next ).forEach( function ( k ) {
			if ( k === 'palette' && ! PALETTES[ next[ k ] ] ) {
				return;
			}
			if ( k === 'text' && ! TEXT[ next[ k ] ] ) {
				return;
			}
			prefs[ k ] = typeof prefs[ k ] === 'boolean' ? !! next[ k ] : next[ k ];
		} );
		if ( ( repaint || frames ) && active ) {
			loadPalette();
			disable();
			enable();
			return;
		}
		loadPalette();
		sync();
	}

	if ( mql ) {
		var onScheme = function () {
			if ( prefs.mode === 'auto' ) {
				sync();
			}
		};
		if ( mql.addEventListener ) {
			mql.addEventListener( 'change', onScheme );
		} else if ( mql.addListener ) {
			mql.addListener( onScheme );
		}
	}

	win.ECDM = {
		engine: true,
		prefs: prefs,
		isActive: function () {
			return active;
		},
		setPrefs: setPrefs,
		mapColor: function ( str, role ) {
			return mapColor( str, role || 'bg' );
		},
	};

	sync();

	// Late stylesheets (printed in the footer) and editor iframes.
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( active ) {
			docs.forEach( function ( d ) {
				d.processAll( false );
				d.processInline( d.doc );
				d.scanFrames( d.doc );
			} );
		}
	} );
	win.addEventListener( 'load', function () {
		if ( active ) {
			docs[ 0 ].processAll( false );
		}
	} );
}( window ) );
