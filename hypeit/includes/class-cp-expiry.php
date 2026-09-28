<?php
/**
 * Private link expiry.
 *
 * A campaign's client link can have a deadline (_cp_expires, a UTC timestamp).
 * Before it the client page shows a live countdown to nudge the client to
 * finish; after it the list is hidden and the client sees "This link has
 * expired" with the contact person's details to ask for a fresh link.
 * Extending the deadline (or clearing it) brings the same link back to life.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Expiry {

	const META = '_cp_expires';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_cp_link_extend', array( __CLASS__, 'handle_extend' ) );
	}

	/**
	 * Deadline (UTC timestamp, 0 = never).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	public static function get( $campaign_id ) {
		return (int) get_post_meta( $campaign_id, self::META, true );
	}

	/**
	 * Has the deadline passed?
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function is_expired( $campaign_id ) {
		$t = self::get( $campaign_id );
		return $t > 0 && $t <= time();
	}

	/**
	 * Set or clear (0) the deadline.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $ts          UTC timestamp.
	 */
	public static function set( $campaign_id, $ts ) {
		$ts = (int) $ts;
		if ( $ts > 0 ) {
			update_post_meta( $campaign_id, self::META, $ts );
		} else {
			delete_post_meta( $campaign_id, self::META );
		}
	}

	/**
	 * "2026-10-05T18:00" in the site's timezone → UTC timestamp (0 if empty/invalid).
	 *
	 * @param string $local Date-time.
	 * @return int
	 */
	public static function from_local( $local ) {
		$local = trim( (string) $local );
		if ( '' === $local ) {
			return 0;
		}
		$d = date_create_immutable( str_replace( 'T', ' ', $local ), wp_timezone() );
		return $d ? $d->getTimestamp() : 0;
	}

	/**
	 * UTC timestamp → "2026-10-05T18:00" in the site's timezone ('' for 0).
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function to_local( $ts ) {
		return $ts ? wp_date( 'Y-m-d\TH:i', (int) $ts ) : '';
	}

	/**
	 * Readable date, e.g. "Sun 5 Oct, 6:00 pm".
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function nice( $ts ) {
		return wp_date( 'D j M, ' . get_option( 'time_format' ), (int) $ts );
	}

	/**
	 * Short status for admin screens: "Expires in 2 days" / "Expired 3 hours ago" ('' when no deadline).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	public static function label( $campaign_id ) {
		$t = self::get( $campaign_id );
		if ( ! $t ) {
			return '';
		}
		/* translators: %s: time span. */
		return $t > time() ? sprintf( __( 'Expires in %s', 'hypeit' ), human_time_diff( time(), $t ) ) : sprintf( __( 'Expired %s ago', 'hypeit' ), human_time_diff( $t ) );
	}

	/**
	 * The person clients should contact for a fresh link. Falls back to the
	 * campaign's author.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array{name:string,phone:string,email:string,wa:string}
	 */
	public static function contact( $campaign_id ) {
		$name  = trim( (string) get_post_meta( $campaign_id, '_cp_contact_name', true ) );
		$phone = trim( (string) get_post_meta( $campaign_id, '_cp_contact_phone', true ) );
		$email = trim( (string) get_post_meta( $campaign_id, '_cp_contact_email', true ) );
		if ( '' === $name && '' === $phone && '' === $email ) {
			$author = get_userdata( (int) get_post_field( 'post_author', $campaign_id ) );
			if ( $author ) {
				$name  = $author->display_name;
				$email = $author->user_email;
			}
		}
		$digits = preg_replace( '/\D/', '', $phone );
		$wa     = '';
		if ( $digits ) {
			$text = sprintf(
				/* translators: %s: campaign title. */
				__( 'Hi! The link for “%s” has expired — could you please send me a fresh one?', 'hypeit' ),
				html_entity_decode( get_the_title( $campaign_id ), ENT_QUOTES )
			);
			$wa = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $text );
		}
		return array( 'name' => $name, 'phone' => $phone, 'email' => is_email( $email ) ? $email : '', 'wa' => $wa );
	}

	/**
	 * Save the contact person.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $c           name, phone, email (only keys present are saved).
	 */
	public static function set_contact( $campaign_id, $c ) {
		if ( isset( $c['name'] ) ) {
			update_post_meta( $campaign_id, '_cp_contact_name', sanitize_text_field( (string) $c['name'] ) );
		}
		if ( isset( $c['phone'] ) ) {
			update_post_meta( $campaign_id, '_cp_contact_phone', sanitize_text_field( (string) $c['phone'] ) );
		}
		if ( isset( $c['email'] ) ) {
			update_post_meta( $campaign_id, '_cp_contact_email', sanitize_email( (string) $c['email'] ) );
		}
	}

	/**
	 * Push the deadline forward by N hours (from now if it already passed).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $hours       Hours.
	 * @return int New deadline.
	 */
	public static function extend( $campaign_id, $hours ) {
		$from = max( time(), self::get( $campaign_id ) );
		$new  = $from + max( 1, (int) $hours ) * HOUR_IN_SECONDS;
		self::set( $campaign_id, $new );
		return $new;
	}

	/**
	 * Nonced "extend" URL for admin buttons.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $hours       Hours.
	 * @return string
	 */
	public static function extend_url( $campaign_id, $hours ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cp_link_extend&post=' . (int) $campaign_id . '&hours=' . (int) $hours ), 'cp_link_extend_' . (int) $campaign_id );
	}

	/**
	 * Admin: extend the deadline.
	 */
	public static function handle_extend() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_link_extend_' . $id );
		if ( ! $id || CP_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		self::extend( $id, isset( $_GET['hours'] ) ? absint( $_GET['hours'] ) : 24 );
		$ref = wp_get_referer();
		wp_safe_redirect( $ref ? $ref : get_edit_post_link( $id, 'raw' ) );
		exit;
	}
}
