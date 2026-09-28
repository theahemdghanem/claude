<?php
/**
 * New-blogger email notifications: right away, daily digest or weekly digest.
 *
 * In digest mode each submission is queued; an hourly check sends ONE email at
 * the chosen time (site timezone) listing every new / updated blogger since the
 * last digest. Nothing is sent when nobody new arrived.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Digest {

	const QUEUE = 'cp_ob_digest_queue';
	const LAST  = 'cp_ob_digest_last';
	const CRON  = 'cp_ob_digest';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'maybe_send' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Keep the hourly check scheduled.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON );
		}
	}

	/**
	 * Current delivery mode.
	 *
	 * @return string instant|daily|weekly
	 */
	public static function mode() {
		$s = CP_Onboarding::get();
		return in_array( $s['email_mode'] ?? 'instant', array( 'daily', 'weekly' ), true ) ? $s['email_mode'] : 'instant';
	}

	/**
	 * Add a submission to the next digest.
	 *
	 * @param int  $id      Blogger ID.
	 * @param bool $updated Existing profile updated.
	 */
	public static function queue( $id, $updated ) {
		$q = get_option( self::QUEUE, array() );
		$q = is_array( $q ) ? $q : array();
		$k = (string) (int) $id;
		if ( isset( $q[ $k ] ) ) {
			$q[ $k ]['updated'] = $q[ $k ]['updated'] && $updated;
		} else {
			$q[ $k ] = array( 'updated' => (bool) $updated, 'time' => time() );
		}
		update_option( self::QUEUE, $q, false );
	}

	/**
	 * Most recent scheduled send time (timestamp) for the current settings.
	 *
	 * @param int $now Now.
	 * @return int
	 */
	public static function last_slot( $now ) {
		$s    = CP_Onboarding::get();
		$hour = max( 0, min( 23, (int) ( $s['digest_hour'] ?? 9 ) ) );
		$tz   = wp_timezone();
		$d    = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->setTime( $hour, 0 );
		if ( 'weekly' === self::mode() ) {
			$want = max( 0, min( 6, (int) ( $s['digest_day'] ?? 0 ) ) ); // 0 = Sunday.
			$diff = ( (int) $d->format( 'w' ) - $want + 7 ) % 7;
			$d    = $d->modify( '-' . $diff . ' days' );
			if ( $d->getTimestamp() > $now ) {
				$d = $d->modify( '-7 days' );
			}
		} elseif ( $d->getTimestamp() > $now ) {
			$d = $d->modify( '-1 day' );
		}
		return $d->getTimestamp();
	}

	/**
	 * Hourly: send the digest when its time has come.
	 *
	 * @param int $now Override "now" (tests).
	 * @return bool Sent.
	 */
	public static function maybe_send( $now = 0 ) {
		$now = $now ? (int) $now : time();
		$q   = get_option( self::QUEUE, array() );
		if ( empty( $q ) || ! is_array( $q ) ) {
			return false;
		}
		$mode = self::mode();
		// Switched back to "right away" with a queue left over: send it now.
		if ( 'instant' !== $mode ) {
			// Send only once a scheduled time has passed after the oldest waiting
			// submission — and only once per scheduled time.
			$slot   = self::last_slot( $now );
			$oldest = min( array_map( static function ( $i ) { return (int) $i['time']; }, $q ) );
			if ( $slot <= $oldest || (int) get_option( self::LAST, 0 ) >= $slot ) {
				return false;
			}
		}
		$sent = self::send( $q );
		if ( $sent ) {
			update_option( self::QUEUE, array(), false );
			update_option( self::LAST, $now, false );
		}
		return $sent;
	}

	/**
	 * Build and send the digest email.
	 *
	 * @param array $q Queue.
	 * @return bool
	 */
	private static function send( $q ) {
		$s = CP_Onboarding::get();
		if ( empty( $s['email_enabled'] ) ) {
			return true; // Emails off: just clear the queue.
		}
		$to = $s['email_to'] ? $s['email_to'] : get_option( 'admin_email' );
		$to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) ) );
		if ( empty( $to ) ) {
			return true;
		}

		$rows    = array();
		$new     = 0;
		$updated = 0;
		$since   = PHP_INT_MAX;
		uasort( $q, static function ( $a, $b ) { return $a['time'] <=> $b['time']; } );
		foreach ( $q as $id => $info ) {
			$id = (int) $id;
			if ( CP_Library::CPT !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || '1' === (string) get_post_meta( $id, '_cp_blocked', true ) || CP_Library::is_inactive( $id ) ) {
				continue;
			}
			$since = min( $since, (int) $info['time'] );
			$info['updated'] ? $updated++ : $new++;
			$rows[] = array( $id, (bool) $info['updated'] );
		}
		if ( ! $rows ) {
			return true;
		}

		$weekly  = 'weekly' === self::mode();
		$title   = $weekly ? __( 'Your weekly blogger digest', 'hypeit' ) : __( 'Your daily blogger digest', 'hypeit' );
		$summary = array();
		if ( $new ) {
			/* translators: %d: count. */
			$summary[] = sprintf( _n( '%d new blogger', '%d new bloggers', $new, 'hypeit' ), $new );
		}
		if ( $updated ) {
			/* translators: %d: count. */
			$summary[] = sprintf( _n( '%d updated profile', '%d updated profiles', $updated, 'hypeit' ), $updated );
		}

		$b  = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;max-width:640px;margin:0 auto;">';
		$b .= '<h2 style="margin:0 0 4px;">' . esc_html( $title ) . '</h2>';
		$b .= '<p style="color:#555;margin:0 0 18px;">' . esc_html( implode( ' · ', $summary ) ) . ' — ' . esc_html( sprintf( /* translators: %s: date. */ __( 'since %s', 'hypeit' ), wp_date( get_option( 'date_format' ), $since ) ) ) . '</p>';
		$b .= '<table style="border-collapse:collapse;width:100%;">';
		foreach ( $rows as $r ) {
			list( $id, $upd ) = $r;
			$name   = trim( get_post_meta( $id, '_cp_first', true ) . ' ' . get_post_meta( $id, '_cp_last', true ) );
			$handle = CP_Library::handle( $id );
			$fol    = (int) get_post_meta( $id, '_cp_followers', true );
			$city   = (string) get_post_meta( $id, '_cp_city', true );
			$cats   = wp_get_post_terms( $id, CP_Library::TAX_TAG, array( 'fields' => 'names' ) );
			$photo  = CP_Photo::url( $id, 's' );
			$tags   = array();
			if ( $upd ) {
				$tags[] = array( __( 'Updated', 'hypeit' ), '#eef2ff', '#3730a3' );
			}
			if ( CP_Verify::is_verified( $id ) ) {
				$tags[] = array( __( 'Verified', 'hypeit' ), '#e7f6ee', '#1b7f4b' );
			}
			if ( 'personal' === get_post_meta( $id, '_cp_ig_status', true ) ) {
				$tags[] = array( __( 'Personal account', 'hypeit' ), '#fcf0e3', '#8a4b00' );
			}
			$meta = array_filter( array( $fol ? sprintf( /* translators: %s: followers. */ __( '%s followers', 'hypeit' ), number_format_i18n( $fol ) ) : '', $city, is_wp_error( $cats ) ? '' : implode( ', ', $cats ) ) );

			$b .= '<tr>';
			$b .= '<td style="padding:10px 10px 10px 0;border-bottom:1px solid #eee;width:48px;vertical-align:top;">' . ( $photo ? '<img src="' . esc_url( $photo ) . '" width="44" height="44" alt="" style="border-radius:50%;display:block;" />' : '<div style="width:44px;height:44px;border-radius:50%;background:#e5e7eb;"></div>' ) . '</td>';
			$b .= '<td style="padding:10px 0;border-bottom:1px solid #eee;vertical-align:top;">';
			$b .= '<strong>' . esc_html( $name ? $name : '@' . $handle ) . '</strong>';
			foreach ( $tags as $t ) {
				$b .= ' <span style="display:inline-block;font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:' . esc_attr( $t[1] ) . ';color:' . esc_attr( $t[2] ) . ';">' . esc_html( $t[0] ) . '</span>';
			}
			$b .= '<br /><a href="' . esc_url( CP_Library::profile_url( $handle ) ) . '" style="color:#0a58ca;text-decoration:none;">@' . esc_html( $handle ) . '</a>';
			$b .= $meta ? '<br /><span style="color:#555;font-size:13px;">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '';
			$b .= '</td>';
			$b .= '<td style="padding:10px 0 10px 10px;border-bottom:1px solid #eee;vertical-align:top;text-align:right;white-space:nowrap;"><a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '" style="color:#0a58ca;font-size:13px;">' . esc_html__( 'Open', 'hypeit' ) . '</a></td>';
			$b .= '</tr>';
		}
		$b .= '</table>';
		$b .= '<p style="margin:20px 0 0;"><a href="' . esc_url( admin_url( 'edit.php?post_type=' . CP_Library::CPT ) ) . '" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:700;">' . esc_html__( 'Open the bloggers list', 'hypeit' ) . '</a></p>';
		$b .= '</div>';

		$subject = sprintf(
			/* translators: 1: site name, 2: summary. */
			__( '[%1$s] %2$s', 'hypeit' ),
			get_bloginfo( 'name' ),
			implode( ' · ', $summary )
		);
		add_filter( 'wp_mail_content_type', array( 'CP_Notify', 'html_content_type' ) );
		$ok = wp_mail( $to, $subject, $b );
		remove_filter( 'wp_mail_content_type', array( 'CP_Notify', 'html_content_type' ) );
		return (bool) $ok;
	}
}
