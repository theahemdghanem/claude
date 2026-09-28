<?php
/**
 * Campaign client logos — stored as plain files in the private HypeIt uploads
 * folder (like blogger photos), never as Media Library attachments.
 *
 * Folder: wp-content/uploads/campaign-bloggers/logos/{random}.png|jpg
 * Logos keep their shape (no square crop) and transparency, capped at 800×400.
 * Campaigns that still point at an old Media Library logo (_cp_logo_id) keep
 * showing it until a new logo is uploaded.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Logo {

	const META   = '_cp_logo';
	const LEGACY = '_cp_logo_id';
	const MAX_MB = 10;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );
	}

	/**
	 * Logo folder (created + protected on first use).
	 *
	 * @return array{dir:string,url:string}
	 */
	private static function base() {
		$b   = CP_Photo::base();
		$dir = $b['dir'] . '/logos';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}
		return array( 'dir' => $dir, 'url' => $b['url'] . '/logos' );
	}

	/**
	 * Stored file name for a campaign ('' when none / invalid).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	private static function file( $campaign_id ) {
		$name = (string) get_post_meta( $campaign_id, self::META, true );
		return preg_match( '/^[a-z0-9]{20,40}\.(png|jpg)$/', $name ) ? $name : '';
	}

	/**
	 * Logo URL ('' when none). Falls back to an old Media Library logo.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	public static function url( $campaign_id ) {
		$name = self::file( $campaign_id );
		if ( $name ) {
			$b = self::base();
			if ( file_exists( $b['dir'] . '/' . $name ) ) {
				return $b['url'] . '/' . $name;
			}
		}
		$legacy = (int) get_post_meta( $campaign_id, self::LEGACY, true );
		return $legacy ? (string) wp_get_attachment_image_url( $legacy, 'medium' ) : '';
	}

	/**
	 * Does the campaign have a logo?
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function has( $campaign_id ) {
		return '' !== self::url( $campaign_id );
	}

	/**
	 * Save an uploaded file as the campaign's logo.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $file        A $_FILES entry.
	 * @return true|WP_Error
	 */
	public static function set_from_upload( $campaign_id, $file ) {
		if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'cp_logo_err', __( 'The logo could not be uploaded. Please try a different image.', 'hypeit' ) );
		}
		if ( (int) $file['size'] > self::MAX_MB * MB_IN_BYTES ) {
			/* translators: %d: megabytes. */
			return new WP_Error( 'cp_logo_big', sprintf( __( 'The logo is too large (max %d MB).', 'hypeit' ), self::MAX_MB ) );
		}
		return self::set_from_path( $campaign_id, $file['tmp_name'] );
	}

	/**
	 * Save a local image file as the campaign's logo.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $path        Source file.
	 * @return true|WP_Error
	 */
	public static function set_from_path( $campaign_id, $path ) {
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$ok   = array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP );
		if ( ! $info || ! in_array( $info[2], $ok, true ) ) {
			return new WP_Error( 'cp_logo_type', __( 'Please upload a JPG, PNG or WebP image.', 'hypeit' ) );
		}
		$ed = wp_get_image_editor( $path );
		if ( is_wp_error( $ed ) ) {
			return new WP_Error( 'cp_logo_editor', __( 'The logo could not be processed. Please try a different image.', 'hypeit' ) );
		}
		if ( method_exists( $ed, 'maybe_exif_rotate' ) ) {
			$ed->maybe_exif_rotate();
		}
		$ed->resize( 800, 400, false ); // Keep the shape; only shrink.
		// PNG / WebP / GIF may be transparent — keep it as PNG. Photos become JPG.
		$png  = IMAGETYPE_JPEG !== $info[2];
		$name = strtolower( wp_generate_password( 32, false, false ) ) . ( $png ? '.png' : '.jpg' );
		$b    = self::base();
		if ( ! $png ) {
			$ed->set_quality( 88 );
		}
		$saved = $ed->save( $b['dir'] . '/' . $name, $png ? 'image/png' : 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'cp_logo_save', __( 'The logo could not be saved. Please try again.', 'hypeit' ) );
		}
		self::delete_file( $campaign_id );
		update_post_meta( $campaign_id, self::META, $name );
		delete_post_meta( $campaign_id, self::LEGACY ); // The old Media Library image itself is left alone.
		return true;
	}

	/**
	 * Remove the campaign's logo.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function remove( $campaign_id ) {
		self::delete_file( $campaign_id );
		delete_post_meta( $campaign_id, self::META );
		delete_post_meta( $campaign_id, self::LEGACY );
	}

	/**
	 * Give a duplicated campaign its own copy of the logo.
	 *
	 * @param int $from Source campaign.
	 * @param int $to   New campaign.
	 */
	public static function copy( $from, $to ) {
		$name = self::file( $from );
		if ( $name ) {
			$b    = self::base();
			$new  = strtolower( wp_generate_password( 32, false, false ) ) . substr( $name, -4 );
			if ( @copy( $b['dir'] . '/' . $name, $b['dir'] . '/' . $new ) ) { // phpcs:ignore
				update_post_meta( $to, self::META, $new );
			}
			return;
		}
		$legacy = (int) get_post_meta( $from, self::LEGACY, true );
		if ( $legacy ) {
			update_post_meta( $to, self::LEGACY, $legacy );
		}
	}

	/**
	 * Delete the stored file (not the meta).
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	private static function delete_file( $campaign_id ) {
		$name = self::file( $campaign_id );
		if ( $name ) {
			$f = self::base()['dir'] . '/' . $name;
			if ( file_exists( $f ) ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
	}

	/**
	 * Campaign permanently deleted: remove its logo file.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete_post( $post_id ) {
		if ( CP_POST_TYPE === get_post_type( $post_id ) ) {
			self::delete_file( $post_id );
		}
	}
}
