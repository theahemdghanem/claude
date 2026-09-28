<?php
/**
 * Session-based email notifications.
 *
 * Every client response is buffered per campaign instead of emailed
 * immediately. A cron job flushes the buffer once a campaign has had no
 * client activity for the configured inactivity window (default 45 minutes),
 * sending a single consolidated summary email.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Notify {

	const EVENT    = 'cp_notify_flush';
	const SCHEDULE = 'cp_five_min';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::EVENT, array( __CLASS__, 'flush' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
	}

	/**
	 * Add a 5-minute cron interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (Campaign notifications)', 'hypeit' ),
		);
		return $schedules;
	}

	/**
	 * Ensure the cron event is scheduled.
	 */
	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + 300, self::SCHEDULE, self::EVENT );
		}
	}

	/**
	 * Remove the scheduled event (deactivation).
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
	}

	/** @return bool Whether notifications are enabled. */
	private static function enabled() {
		$app = CP_App_Settings::get();
		return ! empty( $app['notify_enabled'] );
	}

	/** @return int Inactivity window in seconds. */
	private static function inactivity_seconds() {
		$app = CP_App_Settings::get();
		$min = (int) $app['notify_inactivity'];
		if ( $min < 1 ) {
			$min = 45;
		}
		return $min * MINUTE_IN_SECONDS;
	}

	/**
	 * Record a single change into the campaign's session buffer.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $old_status  Previous status.
	 * @param int    $old_guests  Previous guests.
	 * @param string $new_status  New status.
	 * @param int    $new_guests  New guests.
	 */
	public static function record_change( $campaign_id, $old_status, $old_guests, $new_status, $new_guests ) {
		if ( ! self::enabled() ) {
			return;
		}

		$p = get_post_meta( $campaign_id, '_cp_notify_pending', true );
		if ( ! is_array( $p ) ) {
			$p = array(
				'accepted'      => 0,
				'declined'      => 0,
				'reverted'      => 0,
				'guest_changes' => 0,
				'total'         => 0,
			);
		}

		if ( 'confirmed' === $new_status && 'confirmed' !== $old_status ) {
			$p['accepted']++;
		} elseif ( 'declined' === $new_status && 'declined' !== $old_status ) {
			$p['declined']++;
		} elseif ( 'pending' === $new_status && 'pending' !== $old_status ) {
			$p['reverted']++;
		}

		if ( 'confirmed' === $new_status && (int) $new_guests !== (int) $old_guests ) {
			$p['guest_changes']++;
		}

		$p['total']++;

		update_post_meta( $campaign_id, '_cp_notify_pending', $p );

		if ( ! get_post_meta( $campaign_id, '_cp_notify_since', true ) ) {
			update_post_meta( $campaign_id, '_cp_notify_since', time() );
		}
		update_post_meta( $campaign_id, '_cp_notify_last_activity', time() );
	}

	/**
	 * Cron callback: flush buffers for inactive sessions.
	 */
	public static function flush() {
		if ( ! self::enabled() ) {
			return;
		}

		$threshold = time() - self::inactivity_seconds();

		$ids = get_posts(
			array(
				'post_type'      => CP_POST_TYPE,
				'post_status'    => 'publish',
				'numberposts'    => 50,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_cp_notify_last_activity',
						'value'   => $threshold,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( (array) $ids as $id ) {
			$p = get_post_meta( $id, '_cp_notify_pending', true );
			if ( is_array( $p ) && ! empty( $p['total'] ) ) {
				self::send( $id, $p );
			}
			self::clear( $id );
		}
	}

	/**
	 * Clear a campaign's buffer.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	private static function clear( $campaign_id ) {
		delete_post_meta( $campaign_id, '_cp_notify_pending' );
		delete_post_meta( $campaign_id, '_cp_notify_since' );
		delete_post_meta( $campaign_id, '_cp_notify_last_activity' );
	}

	/**
	 * Resolve recipient email addresses for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	private static function recipients( $campaign_id ) {
		$raw = get_post_meta( $campaign_id, '_cp_notify_email', true );
		if ( ! $raw ) {
			$app = CP_App_Settings::get();
			$raw = $app['notify_email'];
		}
		if ( ! $raw ) {
			$raw = get_option( 'admin_email' );
		}

		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $raw ) ) ) );
		return array_values( array_unique( $emails ) );
	}

	/**
	 * Send the consolidated summary email.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $p           Pending buffer.
	 */
	private static function send( $campaign_id, $p ) {
		$recipients = self::recipients( $campaign_id );
		if ( empty( $recipients ) ) {
			return;
		}

		$title = get_the_title( $campaign_id );
		$since = (int) get_post_meta( $campaign_id, '_cp_notify_since', true );
		$stats = CP_DB::stats( $campaign_id );
		$blog  = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: site name, 2: campaign title. */
			__( '[%1$s] Campaign update summary — %2$s', 'hypeit' ),
			$blog,
			$title
		);

		$since_txt = $since ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $since ) : '';

		$rows = array(
			array( __( 'Newly accepted invitations', 'hypeit' ), (int) ( isset( $p['accepted'] ) ? $p['accepted'] : 0 ) ),
			array( __( 'Declined invitations', 'hypeit' ), (int) ( isset( $p['declined'] ) ? $p['declined'] : 0 ) ),
			array( __( 'Responses reverted to “no response”', 'hypeit' ), (int) ( isset( $p['reverted'] ) ? $p['reverted'] : 0 ) ),
			array( __( 'Additional-guest changes', 'hypeit' ), (int) ( isset( $p['guest_changes'] ) ? $p['guest_changes'] : 0 ) ),
			array( __( 'Total campaign updates', 'hypeit' ), (int) ( isset( $p['total'] ) ? $p['total'] : 0 ) ),
		);

		$current = array(
			array( __( 'Confirmed bloggers', 'hypeit' ), (int) $stats['confirmed'] ),
			array( __( 'Declined bloggers', 'hypeit' ), (int) $stats['declined'] ),
			array( __( 'No response', 'hypeit' ), (int) $stats['pending'] ),
			array( __( 'Total expected attendance', 'hypeit' ), (int) $stats['attendance'] ),
		);

		$b  = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;max-width:600px;margin:0 auto;">';
		$b .= '<h2 style="margin:0 0 4px;">' . esc_html( $title ) . '</h2>';
		if ( $since_txt ) {
			$b .= '<p style="color:#666;margin:0 0 18px;">' . esc_html( sprintf( /* translators: %s: date/time. */ __( 'Activity since %s', 'hypeit' ), $since_txt ) ) . '</p>';
		}

		$b .= '<h3 style="margin:18px 0 6px;">' . esc_html__( 'Updates this session', 'hypeit' ) . '</h3>';
		$b .= self::table( $rows );

		$b .= '<h3 style="margin:22px 0 6px;">' . esc_html__( 'Current campaign status', 'hypeit' ) . '</h3>';
		$b .= self::table( $current );

		$b .= '<p style="margin:22px 0 0;"><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $campaign_id . '&action=edit' ) ) . '" style="color:#0a58ca;">' . esc_html__( 'Open the campaign', 'hypeit' ) . '</a></p>';
		$b .= '</div>';

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
		wp_mail( $recipients, $subject, $b );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
	}

	/**
	 * Simple HTML table for the email.
	 *
	 * @param array $rows Rows of [label, value].
	 * @return string
	 */
	private static function table( $rows ) {
		$html = '<table style="border-collapse:collapse;width:100%;">';
		foreach ( $rows as $r ) {
			$html .= '<tr>'
				. '<td style="padding:8px 10px;border-bottom:1px solid #eee;">' . esc_html( $r[0] ) . '</td>'
				. '<td style="padding:8px 10px;border-bottom:1px solid #eee;text-align:right;font-weight:700;">' . esc_html( $r[1] ) . '</td>'
				. '</tr>';
		}
		$html .= '</table>';
		return $html;
	}

	/**
	 * HTML content type for wp_mail.
	 *
	 * @return string
	 */
	public static function html_content_type() {
		return 'text/html';
	}
}
