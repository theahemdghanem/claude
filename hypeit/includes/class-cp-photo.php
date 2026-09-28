<?php
/**
 * Blogger profile photos — stored as plain files in a private uploads folder,
 * never as Media Library attachments.
 *
 * Folder: wp-content/uploads/campaign-bloggers/
 *   {random}.jpg    400×400 square (profile)
 *   {random}-s.jpg   96×96  square (lists)
 *   tmp/{token}.jpg  photos held between a failed form submit and a retry (24h)
 *
 * Re-encoding strips all metadata (including phone GPS). Filenames are random
 * and unguessable; the folder blocks script execution and search indexing.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Photo {

	const META     = '_cp_photo';
	const DIRNAME  = 'campaign-bloggers';
	const MAX_MB   = 15;
	const CRON     = 'cp_photo_cleanup';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );
		add_action( self::CRON, array( __CLASS__, 'cleanup_tmp' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Paths                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Base dir/url, creating and protecting the folder on first use.
	 *
	 * @return array{dir:string,url:string}
	 */
	public static function base() {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . self::DIRNAME;
		$url = trailingslashit( $up['baseurl'] ) . self::DIRNAME;
		if ( ! is_dir( $dir . '/tmp' ) ) {
			wp_mkdir_p( $dir . '/tmp' );
		}
		self::protect( $dir );
		return array( 'dir' => $dir, 'url' => $url );
	}

	/**
	 * Write the protective files once.
	 *
	 * @param string $dir Folder.
	 */
	private static function protect( $dir ) {
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
			@file_put_contents( $dir . '/tmp/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			$rules = "# Campaign blogger photos — images only, never indexed.\n"
				. "Options -Indexes\n"
				. "<IfModule mod_headers.c>\n  Header set X-Robots-Tag \"noindex, nofollow, noimageindex\"\n</IfModule>\n"
				. "<FilesMatch \"\\.(php|phtml|php\\d|phar|pl|py|cgi|sh|html?|js|svg)$\">\n  Require all denied\n</FilesMatch>\n";
			@file_put_contents( $dir . '/.htaccess', $rules ); // phpcs:ignore
		}
	}

	/**
	 * Photo URL for a blogger.
	 *
	 * @param int    $id   Blogger post ID.
	 * @param string $size 's' (96px) or 'm' (400px).
	 * @return string '' when none.
	 */
	public static function url( $id, $size = 's' ) {
		$name = (string) get_post_meta( $id, self::META, true );
		if ( '' === $name || ! preg_match( '/^[a-z0-9]{20,40}$/', $name ) ) {
			return '';
		}
		$b    = self::base();
		$file = $name . ( 's' === $size ? '-s' : '' ) . '.jpg';
		if ( ! file_exists( $b['dir'] . '/' . $file ) ) {
			return '';
		}
		return $b['url'] . '/' . $file . '?v=' . substr( md5( $name ), 0, 6 );
	}

	/**
	 * Lowercase handle → thumbnail URL, for campaign rows.
	 *
	 * @return array
	 */
	public static function map_by_handle() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => self::META, 'compare' => 'EXISTS' ),
				),
			)
		);
		foreach ( (array) $ids as $id ) {
			$u = self::url( $id, 's' );
			if ( $u ) {
				$map[ strtolower( CP_Library::handle( $id ) ) ] = $u;
			}
		}
		return $map;
	}

	/* ------------------------------------------------------------------ */
	/* Processing                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Validate + process an uploaded file into a temporary normalised JPEG.
	 *
	 * @param array $file An entry from $_FILES.
	 * @return string|WP_Error Temp token.
	 */
	public static function stage_upload( $file ) {
		if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return new WP_Error( 'cp_photo_none', __( 'Please add a profile photo.', 'hypeit' ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'cp_photo_err', __( 'The photo could not be uploaded. Please try a different image.', 'hypeit' ) );
		}
		if ( (int) $file['size'] > self::MAX_MB * MB_IN_BYTES ) {
			/* translators: %d: megabytes. */
			return new WP_Error( 'cp_photo_big', sprintf( __( 'The photo is too large (max %d MB).', 'hypeit' ), self::MAX_MB ) );
		}
		return self::stage_path( $file['tmp_name'] );
	}

	/**
	 * Normalise any local image file into a temp JPEG.
	 *
	 * @param string $path Source path.
	 * @return string|WP_Error Temp token.
	 */
	public static function stage_path( $path ) {
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$ok   = array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP );
		if ( ! $info || ! in_array( $info[2], $ok, true ) ) {
			return new WP_Error( 'cp_photo_type', __( 'Please upload a JPG, PNG or WebP photo.', 'hypeit' ) );
		}
		if ( $info[0] < 120 || $info[1] < 120 ) {
			return new WP_Error( 'cp_photo_small', __( 'The photo is too small. Please use a clearer picture.', 'hypeit' ) );
		}

		$ed = wp_get_image_editor( $path );
		if ( is_wp_error( $ed ) ) {
			return new WP_Error( 'cp_photo_editor', __( 'The photo could not be processed. Please try a different image.', 'hypeit' ) );
		}
		if ( method_exists( $ed, 'maybe_exif_rotate' ) ) {
			$ed->maybe_exif_rotate();
		}
		$size = $ed->get_size();
		$side = min( $size['width'], $size['height'] );
		$ed->crop( (int) ( ( $size['width'] - $side ) / 2 ), (int) ( ( $size['height'] - $side ) / 2 ), $side, $side, min( 800, $side ), min( 800, $side ) );
		$ed->set_quality( 86 );

		$token = strtolower( wp_generate_password( 32, false, false ) );
		$b     = self::base();
		$saved = $ed->save( $b['dir'] . '/tmp/' . $token . '.jpg', 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'cp_photo_save', __( 'The photo could not be saved. Please try again.', 'hypeit' ) );
		}
		return $token;
	}

	/**
	 * Is a temp token valid (exists and well-formed)?
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	public static function has_temp( $token ) {
		if ( ! preg_match( '/^[a-z0-9]{32}$/', (string) $token ) ) {
			return false;
		}
		$b = self::base();
		return file_exists( $b['dir'] . '/tmp/' . $token . '.jpg' );
	}

	/**
	 * Preview URL of a temp photo (only shown back to the same submitter).
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function temp_url( $token ) {
		if ( ! self::has_temp( $token ) ) {
			return '';
		}
		$b = self::base();
		return $b['url'] . '/tmp/' . $token . '.jpg';
	}

	/**
	 * Promote a temp photo to a blogger's final 400px + 96px files.
	 *
	 * @param int    $id    Blogger ID.
	 * @param string $token Temp token.
	 * @return bool
	 */
	public static function attach_temp( $id, $token ) {
		if ( ! self::has_temp( $token ) ) {
			return false;
		}
		$b   = self::base();
		$src = $b['dir'] . '/tmp/' . $token . '.jpg';
		$name = strtolower( wp_generate_password( 32, false, false ) );

		foreach ( array( '' => 400, '-s' => 96 ) as $suffix => $px ) {
			$ed = wp_get_image_editor( $src );
			if ( is_wp_error( $ed ) ) {
				return false;
			}
			$ed->resize( $px, $px, true );
			$ed->set_quality( 's' === ltrim( $suffix, '-' ) ? 80 : 84 );
			$r = $ed->save( $b['dir'] . '/' . $name . $suffix . '.jpg', 'image/jpeg' );
			if ( is_wp_error( $r ) ) {
				return false;
			}
		}
		@unlink( $src ); // phpcs:ignore

		self::delete_files( $id );
		update_post_meta( $id, self::META, $name );
		return true;
	}

	/**
	 * Process an uploaded file straight onto a blogger (admin / app).
	 *
	 * @param int   $id   Blogger ID.
	 * @param array $file $_FILES entry.
	 * @return true|WP_Error
	 */
	public static function set_from_upload( $id, $file ) {
		$token = self::stage_upload( $file );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		return self::attach_temp( $id, $token ) ? true : new WP_Error( 'cp_photo_save', __( 'The photo could not be saved. Please try again.', 'hypeit' ) );
	}

	/**
	 * Remove a blogger's photo files + meta.
	 *
	 * @param int $id Blogger ID.
	 */
	public static function remove( $id ) {
		self::delete_files( $id );
		delete_post_meta( $id, self::META );
	}

	/**
	 * Delete the files currently recorded for a blogger.
	 *
	 * @param int $id Blogger ID.
	 */
	private static function delete_files( $id ) {
		$name = (string) get_post_meta( $id, self::META, true );
		if ( '' === $name || ! preg_match( '/^[a-z0-9]{20,40}$/', $name ) ) {
			return;
		}
		$b = self::base();
		foreach ( array( '', '-s' ) as $suffix ) {
			$f = $b['dir'] . '/' . $name . $suffix . '.jpg';
			if ( file_exists( $f ) ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
	}

	/**
	 * Clean up when a blogger is permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete_post( $post_id ) {
		if ( CP_Library::CPT === get_post_type( $post_id ) ) {
			self::delete_files( $post_id );
		}
	}

	/**
	 * Daily: delete temp photos older than 24 hours.
	 */
	public static function cleanup_tmp() {
		$b = self::base();
		foreach ( (array) glob( $b['dir'] . '/tmp/*.jpg' ) as $f ) {
			if ( is_file( $f ) && filemtime( $f ) < time() - DAY_IN_SECONDS ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
	}

	/**
	 * <img> or initials fallback, for admin screens.
	 *
	 * @param int    $id   Blogger ID (0 for none).
	 * @param int    $px   Display size.
	 * @param string $name Name for initials.
	 * @param string $handle Handle for initials/colour.
	 * @return string HTML.
	 */
	public static function avatar_html( $id, $px = 36, $name = '', $handle = '' ) {
		$u = $id ? self::url( $id, $px > 96 ? 'm' : 's' ) : '';
		$style = 'width:' . (int) $px . 'px;height:' . (int) $px . 'px;border-radius:50%;flex:0 0 auto;display:inline-block;vertical-align:middle;';
		if ( $u ) {
			return '<img src="' . esc_url( $u ) . '" alt="" loading="lazy" style="' . esc_attr( $style ) . 'object-fit:cover;background:#eee;" />';
		}
		$src  = trim( (string) $name ) !== '' ? $name : $handle;
		$ini  = '';
		$bits = preg_split( '/\s+/', trim( (string) $src ) );
		$first = static function ( $w ) {
			return function_exists( 'mb_substr' ) ? mb_substr( $w, 0, 1 ) : substr( $w, 0, 1 );
		};
		if ( $bits && '' !== $bits[0] ) {
			$ini = $first( $bits[0] ) . ( count( $bits ) > 1 ? $first( end( $bits ) ) : '' );
		}
		$ini = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $ini ? $ini : '?' ) : strtoupper( $ini ? $ini : '?' );
		$hue = abs( crc32( (string) ( $handle ? $handle : $name ) ) ) % 360;
		return '<span aria-hidden="true" style="' . esc_attr( $style ) . 'background:hsl(' . $hue . ' 38% 42%);color:#fff;font-weight:700;font-size:' . max( 11, (int) ( $px * 0.36 ) ) . 'px;line-height:' . (int) $px . 'px;text-align:center;">' . esc_html( $ini ) . '</span>';
	}
}
