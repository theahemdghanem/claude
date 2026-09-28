<?php
/**
 * App icons at the exact sizes phones ask for (180 for iPhone, 192/512 for
 * Android, plus a "maskable" 512 with safe padding), generated from the app
 * icon whenever the app settings change.
 * Files live in wp-content/uploads/campaign-bloggers/icons/.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Icons {

	const OPT = 'cp_app_icons';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'update_option_' . CP_App_Settings::OPTION, array( __CLASS__, 'rebuild' ) );
		add_action( 'add_option_' . CP_App_Settings::OPTION, array( __CLASS__, 'rebuild' ) );
	}

	/**
	 * Folder.
	 *
	 * @return array{dir:string,url:string}
	 */
	private static function base() {
		$b   = CP_Photo::base();
		$dir = $b['dir'] . '/icons';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}
		return array( 'dir' => $dir, 'url' => $b['url'] . '/icons' );
	}

	/**
	 * What the current icons were built from (changes → rebuild).
	 *
	 * @return string
	 */
	private static function key() {
		$a = CP_App_Settings::get();
		return md5( wp_json_encode( array( (int) $a['app_icon_id'], (string) $a['app_bg_color'], 1 ) ) );
	}

	/**
	 * Icon URLs ('i180', 'i192', 'i512', 'm512'); empty without an app icon.
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
			$out[ $k ] = $b['url'] . '/' . $name;
		}
		return $out;
	}

	/**
	 * Generate the icons. Returns what was stored.
	 *
	 * @return array{key:string,icons:array}
	 */
	public static function rebuild() {
		$key   = self::key();
		$state = array( 'key' => $key, 'icons' => array() );
		$b     = self::base();
		foreach ( (array) glob( $b['dir'] . '/icon-*' ) as $old ) {
			@unlink( $old ); // phpcs:ignore
		}
		// Launch screens were dropped in 2.4.1 — remove their files.
		$legacy = CP_Photo::base()['dir'] . '/splash';
		if ( is_dir( $legacy ) ) {
			foreach ( (array) glob( $legacy . '/*' ) as $old ) {
				@unlink( $old ); // phpcs:ignore
			}
			@rmdir( $legacy ); // phpcs:ignore
			delete_option( 'cp_app_splash' );
		}

		$a  = CP_App_Settings::get();
		$ic = function_exists( 'imagecreatetruecolor' ) ? self::load( (int) $a['app_icon_id'] ) : null;
		if ( $ic ) {
			$bg = self::rgb( $a['app_bg_color'] );
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
				$name = sprintf( 'icon-%s-%s.png', substr( $key, 0, 8 ), $k );
				if ( imagepng( $img, $b['dir'] . '/' . $name, 6 ) ) {
					$state['icons'][ $k ] = $name;
				}
				imagedestroy( $img );
			}
			imagedestroy( $ic );
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
