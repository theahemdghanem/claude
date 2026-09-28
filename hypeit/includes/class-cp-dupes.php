<?php
/**
 * One Instagram username = one blogger profile.
 *
 * Prevents duplicates from every entry point (admin editor, app, restore from
 * trash) and flags any that already exist so they can be reviewed.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Dupes {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'pre_untrash_post', array( __CLASS__, 'guard_untrash' ), 10, 2 );
		add_action( 'wp_ajax_cp_handle_check', array( __CLASS__, 'ajax_check' ) );
		if ( is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_list' ) );
		}
	}

	/**
	 * Normalise any username / @handle / profile link to a comparable key.
	 *
	 * @param string $raw Input.
	 * @return string
	 */
	public static function key( $raw ) {
		return strtolower( rtrim( CP_Library::extract_handle( (string) $raw ), '.' ) );
	}

	/**
	 * The profile that already uses this username (0 if none), ignoring $exclude.
	 *
	 * @param string $handle  Username.
	 * @param int    $exclude Blogger ID to ignore (the one being edited).
	 * @return int
	 */
	public static function owner( $handle, $exclude = 0 ) {
		$key = self::key( $handle );
		if ( '' === $key ) {
			return 0;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_cp_ig' AND LOWER(TRIM(TRAILING '.' FROM pm.meta_value)) = %s
					AND p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft','inherit')
					AND pm.post_id <> %d
				ORDER BY p.ID ASC LIMIT 1",
				$key,
				CP_Library::CPT,
				(int) $exclude
			)
		);
		return $id;
	}

	/**
	 * Human label for a profile.
	 *
	 * @param int $id Blogger ID.
	 * @return string
	 */
	public static function label( $id ) {
		$name = trim( get_post_meta( $id, '_cp_first', true ) . ' ' . get_post_meta( $id, '_cp_last', true ) );
		return $name ? $name : '@' . CP_Library::handle( $id );
	}

	/**
	 * Error message for a taken username.
	 *
	 * @param string $handle Username.
	 * @param int    $owner  Existing profile.
	 * @return string
	 */
	public static function message( $handle, $owner ) {
		return sprintf(
			/* translators: 1: username, 2: name. */
			__( '@%1$s already belongs to %2$s. Each Instagram account can only have one profile.', 'hypeit' ),
			self::key( $handle ),
			self::label( $owner )
		);
	}

	/**
	 * Remember a refused username for the next admin page load.
	 *
	 * @param string $handle Username.
	 * @param int    $owner  Existing profile.
	 */
	public static function flag( $handle, $owner ) {
		set_transient( 'cp_dupe_' . get_current_user_id(), array( 'handle' => self::key( $handle ), 'owner' => (int) $owner ), 120 );
	}

	/**
	 * Don't restore a trashed blogger whose username is now used by another profile.
	 *
	 * @param null|bool $check Short-circuit.
	 * @param WP_Post   $post  Post.
	 * @return null|bool
	 */
	public static function guard_untrash( $check, $post ) {
		if ( null !== $check || ! $post || CP_Library::CPT !== $post->post_type ) {
			return $check;
		}
		$h     = CP_Library::handle( $post->ID );
		$owner = $h ? self::owner( $h, $post->ID ) : 0;
		if ( $owner ) {
			self::flag( $h, $owner );
			return false;
		}
		return $check;
	}

	/**
	 * Live check while typing in the blogger editor.
	 */
	public static function ajax_check() {
		check_ajax_referer( 'cp_handle_check', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$h     = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
		$post  = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		$owner = self::owner( $h, $post );
		wp_send_json_success(
			$owner ? array(
				'taken'   => true,
				'message' => self::message( $h, $owner ),
				'url'     => get_edit_post_link( $owner, 'raw' ),
			) : array( 'taken' => false )
		);
	}

	/**
	 * Groups of profiles sharing a username.
	 *
	 * @return array lowercase username => [ids]
	 */
	public static function groups() {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT LOWER(TRIM(TRAILING '.' FROM pm.meta_value)) AS h, GROUP_CONCAT(pm.post_id ORDER BY pm.post_id) AS ids, COUNT(*) AS n
				FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_cp_ig' AND pm.meta_value <> '' AND p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft','inherit')
				GROUP BY h HAVING n > 1",
				CP_Library::CPT
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r->h ] = array_map( 'intval', explode( ',', $r->ids ) );
		}
		return $out;
	}

	/**
	 * Notices: refused username + existing duplicates.
	 */
	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || CP_Library::CPT !== $screen->post_type ) {
			return;
		}
		$f = get_transient( 'cp_dupe_' . get_current_user_id() );
		if ( $f ) {
			delete_transient( 'cp_dupe_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Username not saved:', 'hypeit' ) . '</strong> '
				. esc_html( self::message( $f['handle'], $f['owner'] ) )
				. ' <a href="' . esc_url( get_edit_post_link( $f['owner'] ) ) . '">' . esc_html__( 'Open that profile', 'hypeit' ) . '</a></p></div>';
		}
		if ( 'edit' === $screen->base ) {
			$g = self::groups();
			if ( $g ) {
				$n = count( $g );
				echo '<div class="notice notice-warning"><p><strong>' . esc_html(
					sprintf(
						/* translators: %d: count. */
						_n( '%d Instagram username is used by more than one profile.', '%d Instagram usernames are used by more than one profile.', $n, 'hypeit' ),
						$n
					)
				) . '</strong> ' . esc_html__( 'Keep the best one and delete the others.', 'hypeit' )
					. ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . CP_Library::CPT . '&cp_dupes=1' ) ) . '">' . esc_html__( 'Review duplicates', 'hypeit' ) . '</a></p></div>';
			}
		}
	}

	/**
	 * ?cp_dupes=1 → show only duplicated profiles, grouped by username.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function filter_list( $q ) {
		if ( ! $q->is_main_query() || empty( $_GET['cp_dupes'] ) || CP_Library::CPT !== $q->get( 'post_type' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$ids = array();
		foreach ( self::groups() as $set ) {
			$ids = array_merge( $ids, $set );
		}
		$q->set( 'post__in', $ids ? $ids : array( 0 ) );
		$q->set( 'orderby', 'title' );
		$q->set( 'order', 'ASC' );
	}
}
