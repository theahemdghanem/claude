<?php
/**
 * AJAX handlers for client responses (auto-save).
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Ajax {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'wp_ajax_cp_save_response', array( __CLASS__, 'save_response' ) );
		add_action( 'wp_ajax_nopriv_cp_save_response', array( __CLASS__, 'save_response' ) );
		add_action( 'wp_ajax_cp_admin_reset', array( __CLASS__, 'admin_reset' ) );
		add_action( 'wp_ajax_cp_import_bloggers', array( __CLASS__, 'import_bloggers' ) );
		add_action( 'wp_ajax_cp_library_fetch', array( __CLASS__, 'library_fetch' ) );
	}

	/**
	 * Save a single blogger response.
	 */
	public static function save_response() {
		check_ajax_referer( 'cp_frontend', 'nonce' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$blogger_id  = isset( $_POST['blogger_id'] ) ? absint( $_POST['blogger_id'] ) : 0;
		$status      = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$guests      = isset( $_POST['extra_guests'] ) ? absint( $_POST['extra_guests'] ) : 0;

		if ( ! $campaign_id || ! $blogger_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hypeit' ) ), 400 );
		}
		if ( CP_Close::is_closed( $campaign_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This campaign is closed.', 'hypeit' ) ), 403 );
		}

		// The client must be authenticated for this campaign.
		if ( ! CP_Auth::is_authed( $campaign_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please refresh and re-enter the password.', 'hypeit' ) ), 403 );
		}

		// The blogger must belong to this campaign.
		$blogger = CP_DB::get_blogger( $blogger_id );
		if ( ! $blogger || (int) $blogger->campaign_id !== $campaign_id || CP_Library::handle_inactive( $blogger->ig_account ) ) {
			wp_send_json_error( array( 'message' => __( 'Blogger not found.', 'hypeit' ) ), 404 );
		}

		if ( ! in_array( $status, array( 'confirmed', 'declined', 'pending' ), true ) ) {
			$status = 'pending';
		}

		// Clamp guests to the campaign maximum; declined/pending carry no guests.
		$max = (int) ( get_post_meta( $campaign_id, '_cp_max_guests', true ) ?: 4 );
		if ( 'confirmed' !== $status ) {
			$guests = 0;
		} else {
			$guests = min( max( 0, $guests ), $max );
		}

		$updated = CP_DB::update_response( $blogger_id, $campaign_id, $status, $guests );

		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not save. Please try again.', 'hypeit' ) ), 500 );
		}

		// First client response locks the campaign: Everyone stops auto-adding.
		CP_Everyone::mark_started( $campaign_id );

		// Buffer the change for a consolidated, session-based notification.
		CP_Notify::record_change( $campaign_id, $blogger->status, (int) $blogger->extra_guests, $status, $guests );
		CP_Insights::bust();

		wp_send_json_success(
			array(
				'blogger_id'   => $blogger_id,
				'status'       => $status,
				'extra_guests' => $guests,
				'people'       => ( 'confirmed' === $status ) ? ( 1 + $guests ) : 0,
			)
		);
	}

	/**
	 * Admin-side reset (from the campaign edit screen).
	 */
	public static function admin_reset() {
		check_ajax_referer( 'cp_admin_reset', 'nonce' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		if ( ! $campaign_id || ! current_user_can( 'edit_post', $campaign_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hypeit' ) ), 403 );
		}

		CP_DB::reset_campaign( $campaign_id );

		// Responses cleared → selection hasn't started; Everyone resumes and catches up.
		CP_Everyone::clear_started( $campaign_id );
		CP_Everyone::fill( $campaign_id );

		wp_send_json_success( array( 'reset' => true ) );
	}

	/**
	 * Return the account list of another campaign (for importing).
	 */
	public static function import_bloggers() {
		check_ajax_referer( 'cp_admin_import', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hypeit' ) ), 403 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$post        = $campaign_id ? get_post( $campaign_id ) : null;

		if ( ! $post || CP_POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Campaign not found.', 'hypeit' ) ), 404 );
		}

		$only_confirmed = ! empty( $_POST['confirmed_only'] );
		$accounts       = array();
		foreach ( CP_DB::visible_bloggers( $campaign_id ) as $row ) {
			if ( $only_confirmed && 'confirmed' !== $row->status ) {
				continue;
			}
			$accounts[] = $row->ig_account;
		}

		wp_send_json_success( array( 'accounts' => $accounts ) );
	}

	/**
	 * Return library handles matching selected lists / tags / gender.
	 */
	public static function library_fetch() {
		check_ajax_referer( 'cp_admin_library', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hypeit' ) ), 403 );
		}

		$lists  = isset( $_POST['lists'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['lists'] ) ) : array();
		$tags   = isset( $_POST['tags'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['tags'] ) ) : array();
		$gender = isset( $_POST['gender'] ) ? sanitize_key( wp_unslash( $_POST['gender'] ) ) : '';
		$city   = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';

		if ( empty( $lists ) && empty( $tags ) && '' === $city ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one list, tag or city.', 'hypeit' ) ), 400 );
		}

		wp_send_json_success( array( 'accounts' => CP_Library::handles_for( $lists, $tags, $gender, $city ) ) );
	}
}
