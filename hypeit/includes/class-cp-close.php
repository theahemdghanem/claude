<?php
/**
 * Close / reopen campaigns.
 *
 * A closed campaign's client link stops working (the client sees a "closed"
 * notice and can't sign in or change anything). Bloggers still waiting for a
 * response in a closed campaign are left out of insights, so it doesn't count
 * against them.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Close {

	const META = '_cp_closed';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_cp_campaign_close', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_cp_campaign_reopen', array( __CLASS__, 'handle' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
	}

	/**
	 * Is the campaign closed?
	 *
	 * @param int $id Campaign ID.
	 * @return bool
	 */
	public static function is_closed( $id ) {
		return '1' === (string) get_post_meta( (int) $id, self::META, true );
	}

	/**
	 * Close or reopen.
	 *
	 * @param int  $id     Campaign ID.
	 * @param bool $closed Closed.
	 */
	public static function set( $id, $closed ) {
		if ( $closed ) {
			update_post_meta( $id, self::META, '1' );
			update_post_meta( $id, '_cp_closed_at', time() );
		} else {
			delete_post_meta( $id, self::META );
			delete_post_meta( $id, '_cp_closed_at' );
		}
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
	}

	/**
	 * Nonced URL for the admin buttons.
	 *
	 * @param int  $id    Campaign ID.
	 * @param bool $close Close (true) or reopen (false).
	 * @return string
	 */
	public static function url( $id, $close ) {
		$action = $close ? 'cp_campaign_close' : 'cp_campaign_reopen';
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&post=' . (int) $id ), $action . '_' . (int) $id );
	}

	/**
	 * Admin action.
	 */
	public static function handle() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( $action . '_' . $id );
		if ( ! $id || CP_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		self::set( $id, 'cp_campaign_close' === $action );
		wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
		exit;
	}

	/**
	 * "— Closed" after the title in the campaigns list.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function post_state( $states, $post ) {
		if ( $post && CP_POST_TYPE === $post->post_type && self::is_closed( $post->ID ) ) {
			$states['cp_closed'] = __( 'Closed', 'hypeit' );
		}
		return $states;
	}
}
