<?php
/**
 * iPhone / iPad launch screens for the installed app.
 *
 * iOS only shows an apple-touch-startup-image whose pixel size exactly matches
 * the device screen, picked with a media query — a single uploaded image never
 * shows. So we generate one image per device size:
 *   - with a launch image: the image, scaled to fill the screen (centre crop);
 *   - without one: the app icon centred on the launch background colour.
 * Files live in wp-content/uploads/campaign-bloggers/splash/ and are rebuilt
 * whenever the app settings change.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Splash {

	const OPT = 'cp_app_splash';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'update_option_' . CP_App_Settings::OPTION, array( __CLASS__, 'rebuild' ) );
		add_action( 'add_option_' . CP_App_Settings::OPTION, array( __CLASS__, 'rebuild' ) );
	}

	/**
	 * Portrait device screens: CSS width, CSS height, pixel ratio.
	 *
	 * @return array
	 */
	public static function devices() {
		return array(
			array( 440, 956, 3 ),  // iPhone 16 Pro Max.
			array( 402, 874, 3 ),  // iPhone 16 Pro.
			array( 430, 932, 3 ),  // iPhone 14/15 Pro Max, 15/16 Plus.
			array( 393, 852, 3 ),  // iPhone 14/15 Pro, 15/16.
			array( 428, 926, 3 ),  // iPhone 12–14 Pro Max / Plus.
			array( 390, 844, 3 ),  // iPhone 12–14.
			array( 375, 812, 3 ),  // iPhone X/XS/11 Pro, 12/13 mini.
			array( 414, 896, 3 ),  // iPhone XS Max / 11 Pro Max.
			array( 414, 896, 2 ),  // iPhone XR / 11.
			array( 414, 736, 3 ),  // iPhone 6–8 Plus.
			array( 375, 667, 2 ),  // iPhone 6–8, SE 2/3.
			array( 320, 568, 2 ),  // iPhone SE 1.
			array( 1024, 1366, 2 ), // iPad Pro 12.9".
			array( 834, 1194, 2 ), // iPad Pro 11".
			array( 820, 1180, 2 ), // iPad Air.
			array( 768, 1024, 2 ), // iPad / mini.
		);
	}

	/**
	 * Folder.
	 *
	 * @return array{dir:string,url:string}
	 */
	private static function base() {
		$b   = CP_Photo::base();
		$dir = $b['dir'] . '/splash';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}
		return array( 'dir' => $dir, 'url' => $b['url'] . '/splash' );
	}

	/**
	 * What the current images were built from (changes → rebuild).
	 *
	 * @return string
	 */
	private static function key() {
		$a = CP_App_Settings::get();
		return md5( wp_json_encode( array( (int) $a['app_splash_id'], (int) $a['app_icon_id'], (string) $a['app_bg_color'], 3 ) ) );
	}

	/**
	 * <link> tags for the app shell (builds the images the first time).
	 *
	 * @return array of array( url, media )
	 */
	public static function links() {
		$saved = get_option( self::OPT );
		if ( ! is_array( $saved ) || ( $saved['key'] ?? '' ) !== self::key() ) {
			$saved = self::rebuild();
		}
		$out = array();
		$b   = self::base();
		foreach ( (array) ( $saved['files'] ?? array() ) as $f ) {
			$out[] = array(
				$b['url'] . '/' . $f[0] . '?v=' . substr( $saved['key'], 0, 8 ),
				sprintf( '(device-width: %1$dpx) and (device-height: %2$dpx) and (-webkit-device-pixel-ratio: %3$d) and (orientation: portrait)', $f[1], $f[2], $f[3] ),
			);
		}
		return $out;
	}

	/**
	 * Generated icon URLs ('i180', 'i192', 'i512', 'm512'); empty without an app icon.
	 *
	 * @return array
	 */
	public static function icons() {
		$saved = get_option( self::OPT );
		if ( ! is_array( $saved ) || ( $saved['key'] ?? '' ) !== self::key() ) {
			$saved = self::rebuild();
		}
		$b   = self::base();
		$out = array();
		foreach ( (array) ( $saved['icons'] ?? array() ) as $k => $name ) {
			$out[ $k ] = $b['url'] . '/' . $name . '?v=' . substr( $saved['key'], 0, 8 );
		}
		return $out;
	}

	/**
	 * Generate every device image. Returns what was stored.
	 *
	 * @return array{key:string,files:array}
	 */
	public static function rebuild() {
		$key   = self::key();
		$state = array( 'key' => $key, 'files' => array() );
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			update_option( self::OPT, $state, false );
			return $state;
		}
		$a    = CP_App_Settings::get();
		$b    = self::base();
		$bg   = self::rgb( $a['app_bg_color'] );
		$src  = self::load( (int) $a['app_splash_id'] );
		$icon = $src ? null : self::load( (int) $a['app_icon_id'] );

		// Remove images from earlier builds.
		foreach ( (array) glob( $b['dir'] . '/splash-*' ) as $old ) {
			@unlink( $old ); // phpcs:ignore
		}

		@set_time_limit( 60 ); // phpcs:ignore
		$ext = $src ? 'jpg' : 'png';
		foreach ( self::devices() as $d ) {
			list( $cw, $ch, $dpr ) = $d;
			$w   = $cw * $dpr;
			$h   = $ch * $dpr;
			$img = imagecreatetruecolor( $w, $h );
			imagefill( $img, 0, 0, imagecolorallocate( $img, $bg[0], $bg[1], $bg[2] ) );
			imagealphablending( $img, true );
			if ( $src ) {
				// Cover: fill the screen, centre-crop the overflow.
				$sw    = imagesx( $src );
				$sh    = imagesy( $src );
				$scale = max( $w / $sw, $h / $sh );
				$dw    = (int) ceil( $sw * $scale );
				$dh    = (int) ceil( $sh * $scale );
				imagecopyresampled( $img, $src, (int) ( ( $w - $dw ) / 2 ), (int) ( ( $h - $dh ) / 2 ), 0, 0, $dw, $dh, $sw, $sh );
			} elseif ( $icon ) {
				$side = (int) round( min( $w, $h ) * 0.3 );
				imagecopyresampled( $img, $icon, (int) ( ( $w - $side ) / 2 ), (int) ( ( $h - $side ) / 2 ), 0, 0, $side, $side, imagesx( $icon ), imagesy( $icon ) );
			}
			$name = sprintf( 'splash-%s-%dx%d.%s', substr( $key, 0, 8 ), $w, $h, $ext );
			$ok   = 'jpg' === $ext ? imagejpeg( $img, $b['dir'] . '/' . $name, 84 ) : imagepng( $img, $b['dir'] . '/' . $name, 6 );
			imagedestroy( $img );
			if ( $ok ) {
				$state['files'][] = array( $name, $cw, $ch, $dpr );
			}
		}
		// App icons at the exact sizes phones ask for (+ a "maskable" one with safe padding).
		$state['icons'] = array();
		$ic             = $icon ? $icon : self::load( (int) $a['app_icon_id'] );
		if ( $ic ) {
			foreach ( array( 'i180' => array( 180, 1.0 ), 'i192' => array( 192, 1.0 ), 'i512' => array( 512, 1.0 ), 'm512' => array( 512, 0.8 ) ) as $k => $spec ) {
				$px  = $spec[0];
				$img = imagecreatetruecolor( $px, $px );
				if ( $spec[1] < 1 ) {
					imagefill( $img, 0, 0, imagecolorallocate( $img, $bg[0], $bg[1], $bg[2] ) );
				} else {
					imagealphablending( $img, false );
					imagesavealpha( $img, true );
					imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
					imagealphablending( $img, true );
				}
				$side = (int) round( $px * $spec[1] );
				$off  = (int) ( ( $px - $side ) / 2 );
				imagecopyresampled( $img, $ic, $off, $off, 0, 0, $side, $side, imagesx( $ic ), imagesy( $ic ) );
				imagesavealpha( $img, true );
				$name = sprintf( 'splash-%s-%s.png', substr( $key, 0, 8 ), $k );
				if ( imagepng( $img, $b['dir'] . '/' . $name, 6 ) ) {
					$state['icons'][ $k ] = $name;
				}
				imagedestroy( $img );
			}
			if ( $ic !== $icon ) {
				imagedestroy( $ic );
			}
		}

		foreach ( array( $src, $icon ) as $res ) {
			if ( $res ) {
				imagedestroy( $res );
			}
		}
		update_option( self::OPT, $state, false );
		return $state;
	}

	/**
	 * Load a Media Library image into GD (null if none / unreadable).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return resource|GdImage|null
	 */
	private static function load( $attachment_id ) {
		if ( ! $attachment_id ) {
			return null;
		}
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return null;
		}
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$img  = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $img ) {
			return null;
		}
		imagealphablending( $img, true );
		return $img;
	}

	/**
	 * "#112233" → array( r, g, b ) (black when invalid).
	 *
	 * @param string $hex Colour.
	 * @return array
	 */
	private static function rgb( $hex ) {
		$hex = ltrim( trim( (string) $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}
}
