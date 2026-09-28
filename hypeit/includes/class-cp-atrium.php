<?php
/**
 * Campaign ↔ ATRIUM bridge.
 *
 * - Link a campaign to an ATRIUM event (or create a private campaign event).
 * - Send the client-confirmed bloggers to it as guests (name, WhatsApp, photo,
 *   headcount); re-sending skips duplicates and keeps headcounts in sync.
 * - Read invitation / RSVP / check-in status back into Campaign.
 * - Reliability per blogger: attended vs confirmed across ended events.
 *
 * Nothing is copied into ATRIUM's Media Library: ATRIUM asks for the photo via
 * the `ilev_guest_photo_url` filter (ATRIUM 2.3.4+) and Campaign answers with
 * its private photo URL.
 *
 * Links: campaign meta _cp_atrium_event (event ID) and _cp_atrium_map
 * (campaign row ID => ATRIUM guest ID). Each guest's `extra` JSON also carries
 * {cp_blogger, cp_campaign, cp_row}.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Atrium {

	const META_EVENT = '_cp_atrium_event';
	const META_MAP   = '_cp_atrium_map';
	const META_MSG   = '_cp_atrium_msg';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'ilev_guest_photo_url', array( __CLASS__, 'photo_filter' ), 10, 3 );
		add_action( 'wp_ajax_cp_atrium', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Is ATRIUM installed and active?
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'ILEV_Guests' ) && class_exists( 'ILEV_DB' ) && class_exists( 'ILEV_Helpers' ) && defined( 'ILEV_POST_TYPE' );
	}

	/**
	 * Does the installed ATRIUM support external photos (2.3.4+)?
	 *
	 * @return bool
	 */
	public static function photos_supported() {
		return defined( 'ILEV_VERSION' ) && version_compare( ILEV_VERSION, '2.3.4', '>=' );
	}

	/* ------------------------------------------------------------------ */
	/* Photos                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Decode a guest's extra JSON.
	 *
	 * @param object $guest Guest row.
	 * @return array
	 */
	private static function extra( $guest ) {
		$e = ( $guest && ! empty( $guest->extra ) ) ? json_decode( $guest->extra, true ) : null;
		return is_array( $e ) ? $e : array();
	}

	/**
	 * Supply Campaign's private photo to ATRIUM for bridged guests.
	 *
	 * @param string $url   Existing URL.
	 * @param object $guest Guest row.
	 * @param string $size  Size.
	 * @return string
	 */
	public static function photo_filter( $url, $guest, $size ) {
		if ( '' !== (string) $url ) {
			return $url;
		}
		$e = self::extra( $guest );
		if ( empty( $e['cp_blogger'] ) ) {
			return $url;
		}
		$p = CP_Photo::url( (int) $e['cp_blogger'], 'thumbnail' === $size ? 's' : 'm' );
		return $p ? $p : $url;
	}

	/* ------------------------------------------------------------------ */
	/* Events                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Linked event ID (0 if none / deleted).
	 *
	 * @param int $cid Campaign ID.
	 * @return int
	 */
	public static function event_id( $cid ) {
		$e = (int) get_post_meta( $cid, self::META_EVENT, true );
		if ( ! $e || ! self::active() ) {
			return 0;
		}
		$p = get_post( $e );
		return ( $p && ILEV_POST_TYPE === $p->post_type && 'trash' !== $p->post_status ) ? $e : 0;
	}

	/**
	 * Recent ATRIUM events for the picker.
	 *
	 * @return array
	 */
	public static function events() {
		if ( ! self::active() ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => ILEV_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		$out = array();
		foreach ( (array) $ids as $id ) {
			$out[] = self::event_info( $id );
		}
		return $out;
	}

	/**
	 * Summary of one event.
	 *
	 * @param int $eid Event ID.
	 * @return array
	 */
	public static function event_info( $eid ) {
		$status = ILEV_Helpers::get_status( $eid );
		$date   = ILEV_Helpers::meta( $eid, 'date', '' );
		return array(
			'id'        => (int) $eid,
			'title'     => html_entity_decode( get_the_title( $eid ), ENT_QUOTES ),
			'status'    => $status,
			'label'     => ILEV_Helpers::status_label( $status ),
			'date'      => $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '',
			'per_guest' => ILEV_Helpers::campaign_per_guest( $eid ),
			'private'   => 'private' === ILEV_Helpers::meta( $eid, 'access', 'public' ),
			'edit'      => get_edit_post_link( $eid, 'raw' ),
			'url'       => 'publish' === get_post_status( $eid ) ? ILEV_Helpers::get_event_url( $eid ) : '',
		);
	}

	/**
	 * Link a campaign to an event.
	 *
	 * @param int $cid Campaign ID.
	 * @param int $eid Event ID (0 unlinks).
	 */
	public static function link( $cid, $eid ) {
		if ( $eid ) {
			update_post_meta( $cid, self::META_EVENT, (int) $eid );
		} else {
			delete_post_meta( $cid, self::META_EVENT );
		}
		// Guests belong to one event — a different event starts a fresh map.
		delete_post_meta( $cid, self::META_MAP );
	}

	/**
	 * Create a private campaign event (draft) named after the campaign and link it.
	 *
	 * @param int  $cid       Campaign ID.
	 * @param bool $per_guest Per-guest visit dates (restaurant-style reviews).
	 * @return int|WP_Error
	 */
	public static function create_event( $cid, $per_guest = false ) {
		if ( ! self::active() ) {
			return new WP_Error( 'cp_no_atrium', __( 'ATRIUM is not active.', 'hypeit' ) );
		}
		$eid = wp_insert_post(
			array(
				'post_type'   => ILEV_POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => get_the_title( $cid ),
			),
			true
		);
		if ( is_wp_error( $eid ) ) {
			return $eid;
		}
		update_post_meta( $eid, '_ilev_access', 'private' );
		update_post_meta( $eid, '_ilev_campaign', '1' );
		update_post_meta( $eid, '_ilev_campaign_dt_mode', $per_guest ? 'per_guest' : 'shared' );
		update_post_meta( $eid, '_ilev_require_photo', '1' );
		self::link( $cid, $eid );
		return (int) $eid;
	}

	/* ------------------------------------------------------------------ */
	/* Guests                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Row ID => guest ID map (only rows whose guest still exists).
	 *
	 * @param int $cid Campaign ID.
	 * @return array
	 */
	private static function map( $cid ) {
		$m = get_post_meta( $cid, self::META_MAP, true );
		return is_array( $m ) ? array_map( 'intval', $m ) : array();
	}

	/**
	 * Guest rows for a campaign, keyed by campaign row ID.
	 *
	 * @param int $cid Campaign ID.
	 * @return array
	 */
	public static function guests_by_row( $cid ) {
		$map = self::map( $cid );
		if ( ! $map || ! self::active() ) {
			return array();
		}
		global $wpdb;
		$t    = ILEV_DB::guests_table();
		$ids  = implode( ',', array_map( 'intval', array_values( $map ) ) );
		$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$byid = array();
		foreach ( (array) $rows as $g ) {
			$byid[ (int) $g->id ] = $g;
		}
		$out = array();
		foreach ( $map as $row => $gid ) {
			if ( isset( $byid[ $gid ] ) ) {
				$out[ (int) $row ] = $byid[ $gid ];
			}
		}
		return $out;
	}

	/**
	 * Where a guest is in the funnel.
	 *
	 * @param object $g     Guest row.
	 * @param bool   $ended Event (or their visit) has ended.
	 * @return string invited|opened|declined|confirmed|attended|no_show
	 */
	public static function stage( $g, $ended = false ) {
		if ( (int) $g->checked_in ) {
			return 'attended';
		}
		if ( 'no' === $g->rsvp_status ) {
			return 'declined';
		}
		if ( (int) $g->confirmed || 'yes' === $g->rsvp_status ) {
			return $ended ? 'no_show' : 'confirmed';
		}
		if ( ! empty( $g->opened_at ) && '0000-00-00 00:00:00' !== $g->opened_at ) {
			return 'opened';
		}
		return 'invited';
	}

	/**
	 * Stage labels.
	 *
	 * @return array
	 */
	public static function stage_labels() {
		return array(
			'invited'   => __( 'Invited', 'hypeit' ),
			'opened'    => __( 'Opened', 'hypeit' ),
			'declined'  => __( 'Can’t attend', 'hypeit' ),
			'confirmed' => __( 'Confirmed', 'hypeit' ),
			'attended'  => __( 'Attended', 'hypeit' ),
			'no_show'   => __( 'No-show', 'hypeit' ),
		);
	}

	/**
	 * Has this guest's event / visit ended?
	 *
	 * @param int    $eid Event ID.
	 * @param object $g   Guest.
	 * @return bool
	 */
	private static function ended( $eid, $g ) {
		$ts = ILEV_Helpers::get_guest_timestamps( $eid, $g );
		return ! empty( $ts['end'] ) && time() >= (int) $ts['end'];
	}

	/**
	 * Full event picture for a campaign: per-row status + funnel counts.
	 *
	 * @param int $cid Campaign ID.
	 * @return array
	 */
	public static function status( $cid ) {
		$eid = self::event_id( $cid );
		$out = array(
			'active'    => self::active(),
			'photos'    => self::photos_supported(),
			'event'     => $eid ? self::event_info( $eid ) : null,
			'rows'      => array(),
			'funnel'    => array( 'selected' => 0, 'invited' => 0, 'opened' => 0, 'confirmed' => 0, 'attended' => 0, 'no_show' => 0, 'declined' => 0 ),
			'pending'   => 0,
			'message'   => self::message_template( $cid ),
		);
		if ( ! $eid ) {
			foreach ( CP_DB::get_bloggers( $cid ) as $r ) {
				if ( 'confirmed' === $r->status ) {
					$out['funnel']['selected']++;
				}
			}
			return $out;
		}

		$guests = self::guests_by_row( $cid );
		foreach ( CP_DB::get_bloggers( $cid ) as $r ) {
			$selected = 'confirmed' === $r->status;
			if ( $selected ) {
				$out['funnel']['selected']++;
			}
			if ( ! isset( $guests[ (int) $r->id ] ) ) {
				if ( $selected ) {
					$out['pending']++;
				}
				continue;
			}
			$g     = $guests[ (int) $r->id ];
			$stage = self::stage( $g, self::ended( $eid, $g ) );
			$out['funnel']['invited']++;
			if ( in_array( $stage, array( 'opened', 'confirmed', 'attended', 'no_show', 'declined' ), true ) ) {
				$out['funnel']['opened']++;
			}
			if ( in_array( $stage, array( 'confirmed', 'attended', 'no_show' ), true ) ) {
				$out['funnel']['confirmed']++;
			}
			if ( isset( $out['funnel'][ $stage ] ) && in_array( $stage, array( 'attended', 'no_show', 'declined' ), true ) ) {
				$out['funnel'][ $stage ]++;
			}
			$lib   = CP_Library::find_by_handle( $r->ig_account );
			$phone = $g->phone;
			$out['rows'][ (int) $r->id ] = array(
				'row_id'   => (int) $r->id,
				'handle'   => $r->ig_account,
				'name'     => $g->full_name,
				'stage'    => $stage,
				'selected' => $selected,
				'party'    => (int) $g->party_size,
				'code'     => (int) $g->confirmed ? (string) $g->access_code : '',
				'visit'    => trim( $g->visit_date . ' ' . $g->visit_time ),
				'url'      => ILEV_Helpers::get_guest_url( $eid, $g->token ),
				'wa'       => self::wa_link( $phone, self::message( $cid, $g, $eid ) ),
				'blogger'  => $lib ? (int) $lib : 0,
			);
		}
		return $out;
	}

	/**
	 * Stage per handle for a campaign (for tables and the app).
	 *
	 * @param int $cid Campaign ID.
	 * @return array lowercase handle => stage
	 */
	public static function stages_by_handle( $cid ) {
		$st  = self::status( $cid );
		$out = array();
		foreach ( $st['rows'] as $r ) {
			$out[ strtolower( $r['handle'] ) ] = $r['stage'];
		}
		return $out;
	}

	/**
	 * Send the client-confirmed bloggers to the linked event.
	 *
	 * @param int   $cid  Campaign ID.
	 * @param array $opts visit_date, visit_time, prune (remove deselected, not checked in).
	 * @return array|WP_Error Counts.
	 */
	public static function send( $cid, $opts = array() ) {
		$eid = self::event_id( $cid );
		if ( ! $eid ) {
			return new WP_Error( 'cp_no_event', __( 'Link an ATRIUM event first.', 'hypeit' ) );
		}
		global $wpdb;
		$t      = ILEV_DB::guests_table();
		$map    = self::map( $cid );
		$guests = self::guests_by_row( $cid );
		$title  = html_entity_decode( get_the_title( $cid ), ENT_QUOTES );
		$added  = 0;
		$synced = 0;
		$pruned = 0;

		$vdate = isset( $opts['visit_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $opts['visit_date'] ) ? $opts['visit_date'] : '';
		$vtime = isset( $opts['visit_time'] ) && preg_match( '/^\d{2}:\d{2}$/', (string) $opts['visit_time'] ) ? $opts['visit_time'] : '';

		$rows = CP_DB::get_bloggers( $cid );
		$live = array();
		foreach ( $rows as $r ) {
			if ( 'confirmed' !== $r->status ) {
				continue;
			}
			$live[ (int) $r->id ] = true;
			$party = 1 + (int) $r->extra_guests;

			if ( isset( $guests[ (int) $r->id ] ) ) {
				$g = $guests[ (int) $r->id ];
				// Keep headcount in sync until they've checked in.
				if ( ! (int) $g->checked_in && (int) $g->party_size !== $party ) {
					$wpdb->update( $t, array( 'party_size' => $party ), array( 'id' => (int) $g->id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$synced++;
				}
				continue;
			}

			// Deactivated bloggers aren't invited (existing guests are kept).
			if ( CP_Library::handle_inactive( $r->ig_account ) ) {
				continue;
			}

			$lib   = CP_Library::find_by_handle( $r->ig_account );
			$name  = $lib ? trim( get_post_meta( $lib, '_cp_first', true ) . ' ' . get_post_meta( $lib, '_cp_last', true ) ) : '';
			$phone = $lib ? ( get_post_meta( $lib, '_cp_whatsapp', true ) ? get_post_meta( $lib, '_cp_whatsapp', true ) : get_post_meta( $lib, '_cp_phone', true ) ) : '';

			$gid = ILEV_Guests::add(
				$eid,
				array(
					'full_name'  => $name ? $name : '@' . $r->ig_account,
					'phone'      => $phone,
					'email'      => $lib ? (string) get_post_meta( $lib, '_cp_email', true ) : '',
					'party_size' => $party,
					'tags'       => 'Blogger,' . $title,
					'visit_date' => $vdate,
					'visit_time' => $vtime,
				)
			);
			if ( ! $gid ) {
				continue;
			}
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$t,
				array(
					'extra' => wp_json_encode(
						array(
							'cp_blogger'  => $lib ? (int) $lib : 0,
							'cp_campaign' => (int) $cid,
							'cp_row'      => (int) $r->id,
							'note'        => '@' . $r->ig_account,
						)
					),
				),
				array( 'id' => (int) $gid ),
				array( '%s' ),
				array( '%d' )
			);
			$map[ (int) $r->id ] = (int) $gid;
			$added++;
		}

		// Optionally remove guests the client has since deselected (never checked-in ones).
		if ( ! empty( $opts['prune'] ) ) {
			foreach ( $guests as $row => $g ) {
				if ( ! isset( $live[ $row ] ) && ! (int) $g->checked_in ) {
					ILEV_Guests::delete( (int) $g->id );
					unset( $map[ $row ] );
					$pruned++;
				}
			}
		}

		update_post_meta( $cid, self::META_MAP, $map );
		self::bust();
		return array( 'added' => $added, 'synced' => $synced, 'pruned' => $pruned );
	}

	/* ------------------------------------------------------------------ */
	/* WhatsApp invites                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Default invite text.
	 *
	 * @return string
	 */
	public static function default_message() {
		return __( "Hi {first}! You're invited to {event}.\nHere is your personal invitation — please confirm your attendance:\n{link}", 'hypeit' );
	}

	/**
	 * Campaign's invite template.
	 *
	 * @param int $cid Campaign ID.
	 * @return string
	 */
	public static function message_template( $cid ) {
		$m = (string) get_post_meta( $cid, self::META_MSG, true );
		return '' !== trim( $m ) ? $m : self::default_message();
	}

	/**
	 * Personalised invite text for a guest.
	 *
	 * @param int    $cid Campaign ID.
	 * @param object $g   Guest.
	 * @param int    $eid Event ID.
	 * @return string
	 */
	public static function message( $cid, $g, $eid ) {
		$parts = preg_split( '/\s+/', trim( (string) $g->full_name ) );
		$first = ( $parts && '' !== $parts[0] ) ? ltrim( $parts[0], '@' ) : '';
		return strtr(
			self::message_template( $cid ),
			array(
				'{first}' => $first,
				'{name}'  => ltrim( (string) $g->full_name, '@' ),
				'{event}' => html_entity_decode( get_the_title( $eid ), ENT_QUOTES ),
				'{link}'  => ILEV_Helpers::get_guest_url( $eid, $g->token ),
				'{date}'  => trim( $g->visit_date . ' ' . $g->visit_time ),
			)
		);
	}

	/**
	 * WhatsApp link with a prefilled message ('' when no usable number).
	 * Egyptian local numbers (01xxxxxxxxx) are converted to +20 format.
	 *
	 * @param string $phone Phone.
	 * @param string $text  Message.
	 * @return string
	 */
	public static function wa_link( $phone, $text ) {
		$d = preg_replace( '/\D/', '', (string) $phone );
		if ( 0 === strpos( $d, '00' ) ) {
			$d = substr( $d, 2 );
		} elseif ( 11 === strlen( $d ) && 0 === strpos( $d, '01' ) ) {
			$d = '20' . substr( $d, 1 );
		}
		if ( strlen( $d ) < 8 ) {
			return '';
		}
		return 'https://wa.me/' . $d . '?text=' . rawurlencode( $text );
	}

	/* ------------------------------------------------------------------ */
	/* Reliability                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Attendance record for a blogger across ended ATRIUM events.
	 *
	 * @param int $blogger_id Library blogger ID.
	 * @return array{expected:int,attended:int,rate:int|null}
	 */
	public static function reliability( $blogger_id ) {
		$none = array( 'expected' => 0, 'attended' => 0, 'rate' => null );
		if ( ! $blogger_id || ! self::active() ) {
			return $none;
		}
		$key    = 'cp_rel_' . (int) $blogger_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$t    = ILEV_DB::guests_table();
		$like = '%' . $wpdb->esc_like( '"cp_blogger":' . (int) $blogger_id . ',' ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE extra LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

		$expected = 0;
		$attended = 0;
		foreach ( (array) $rows as $g ) {
			if ( (int) $g->checked_in ) {
				$expected++;
				$attended++;
				continue;
			}
			$committed = (int) $g->confirmed || 'yes' === $g->rsvp_status;
			if ( $committed && self::ended( (int) $g->event_id, $g ) ) {
				$expected++;
			}
		}
		$out = array(
			'expected' => $expected,
			'attended' => $attended,
			'rate'     => $expected ? (int) round( $attended / $expected * 100 ) : null,
		);
		set_transient( $key, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Event status of a blogger per campaign (campaign ID => stage).
	 *
	 * @param int $blogger_id Library blogger ID.
	 * @return array
	 */
	public static function stages_for_blogger( $blogger_id ) {
		if ( ! $blogger_id || ! self::active() ) {
			return array();
		}
		global $wpdb;
		$t    = ILEV_DB::guests_table();
		$like = '%' . $wpdb->esc_like( '"cp_blogger":' . (int) $blogger_id . ',' ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE extra LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $g ) {
			$e = self::extra( $g );
			if ( ! empty( $e['cp_campaign'] ) ) {
				$out[ (int) $e['cp_campaign'] ] = self::stage( $g, self::ended( (int) $g->event_id, $g ) );
			}
		}
		return $out;
	}

	/**
	 * Clear reliability caches (after sending / changes).
	 */
	public static function bust() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cp\\_rel\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cp\\_rel\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ------------------------------------------------------------------ */
	/* Actions (admin AJAX + REST share this)                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Run an action.
	 *
	 * @param int   $cid Campaign ID.
	 * @param array $p   op + params.
	 * @return array|WP_Error
	 */
	public static function run( $cid, $p ) {
		if ( ! self::active() ) {
			return new WP_Error( 'cp_no_atrium', __( 'ATRIUM is not active.', 'hypeit' ) );
		}
		$op = isset( $p['op'] ) ? sanitize_key( $p['op'] ) : '';
		switch ( $op ) {
			case 'link':
				self::link( $cid, absint( $p['event'] ?? 0 ) );
				return array( 'notice' => __( 'Event linked.', 'hypeit' ) );
			case 'unlink':
				self::link( $cid, 0 );
				return array( 'notice' => __( 'Event unlinked. Guests already in ATRIUM were kept.', 'hypeit' ) );
			case 'create':
				$r = self::create_event( $cid, ! empty( $p['per_guest'] ) );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return array( 'notice' => __( 'Private event created as a draft — set its date and venue in ATRIUM, then publish it.', 'hypeit' ), 'event' => $r );
			case 'send':
				$r = self::send( $cid, $p );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$bits = array();
				/* translators: %d: count. */
				$bits[] = sprintf( _n( '%d blogger invited', '%d bloggers invited', $r['added'], 'hypeit' ), $r['added'] );
				if ( $r['synced'] ) {
					/* translators: %d: count. */
					$bits[] = sprintf( _n( '%d headcount updated', '%d headcounts updated', $r['synced'], 'hypeit' ), $r['synced'] );
				}
				if ( $r['pruned'] ) {
					/* translators: %d: count. */
					$bits[] = sprintf( _n( '%d removed', '%d removed', $r['pruned'], 'hypeit' ), $r['pruned'] );
				}
				return array( 'notice' => implode( ' · ', $bits ) . '.', 'counts' => $r );
			case 'message':
				$m = isset( $p['message'] ) ? sanitize_textarea_field( (string) $p['message'] ) : '';
				update_post_meta( $cid, self::META_MSG, $m );
				return array( 'notice' => __( 'Invitation message saved.', 'hypeit' ) );
		}
		return new WP_Error( 'cp_bad_op', __( 'Unknown action.', 'hypeit' ) );
	}

	/**
	 * Admin AJAX: cp_atrium.
	 */
	public static function ajax() {
		check_ajax_referer( 'cp_atrium', 'nonce' );
		$cid = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
		if ( ! $cid || CP_POST_TYPE !== get_post_type( $cid ) || ! current_user_can( 'edit_post', $cid ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'hypeit' ) ), 403 );
		}
		$r = self::run( $cid, wp_unslash( $_POST ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
		}
		wp_send_json_success( $r );
	}

	/* ------------------------------------------------------------------ */
	/* Campaign editor — Event tab                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Render the Event tab.
	 *
	 * @param WP_Post $post Campaign.
	 */
	public static function render_panel( $post ) {
		$cid = (int) $post->ID;
		echo '<div id="cpa" data-campaign="' . (int) $cid . '" data-nonce="' . esc_attr( wp_create_nonce( 'cp_atrium' ) ) . '">';

		if ( ! self::active() ) {
			echo '<div class="cpw-card"><h3>' . esc_html__( 'ATRIUM is not active', 'hypeit' ) . '</h3><p class="cpw-muted">' . esc_html__( 'Activate the ATRIUM plugin on this site to invite confirmed bloggers to your event, share personal invitations and track check-ins here.', 'hypeit' ) . '</p></div></div>';
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			echo '<div class="cpw-card"><p class="cpw-muted">' . esc_html__( 'Save the campaign first, then link it to an event.', 'hypeit' ) . '</p></div></div>';
			return;
		}

		$st     = self::status( $cid );
		$ev     = $st['event'];
		$labels = self::stage_labels();

		if ( ! self::photos_supported() ) {
			echo '<div class="cpw-note" style="margin-bottom:14px;">' . esc_html__( 'Update ATRIUM to 2.3.4 or newer so blogger photos appear in ATRIUM (for check-in verification) and bloggers aren’t asked to upload them again.', 'hypeit' ) . '</div>';
		}

		if ( ! $ev ) {
			$events = self::events();
			?>
			<div class="cpw-card">
				<h3><?php esc_html_e( 'Invite the confirmed bloggers to an event', 'hypeit' ); ?></h3>
				<p class="cpw-muted"><?php esc_html_e( 'Link this campaign to an ATRIUM event. Client-confirmed bloggers can then be invited in one click with their photo, WhatsApp and headcount — and their RSVP and check-in show up here.', 'hypeit' ); ?></p>
				<div class="cpw-grid" style="margin-top:14px;">
					<div class="cpw-field cpw-span2">
						<span class="cpw-flabel"><?php esc_html_e( 'Create a new private event', 'hypeit' ); ?></span>
						<label class="cpw-check" style="margin-bottom:10px;"><input type="checkbox" id="cpa-perguest" /> <?php esc_html_e( 'Each blogger gets their own visit date & time (e.g. restaurant reviews)', 'hypeit' ); ?></label><br />
						<button type="button" class="button button-primary" data-cpa="create"><?php esc_html_e( 'Create event from this campaign', 'hypeit' ); ?></button>
					</div>
					<?php if ( $events ) : ?>
						<div class="cpw-field cpw-span2">
							<span class="cpw-flabel"><?php esc_html_e( 'Or link an existing event', 'hypeit' ); ?></span>
							<div class="cpw-row">
								<select id="cpa-event" class="cpw-grow">
									<option value=""><?php esc_html_e( '— Select an event —', 'hypeit' ); ?></option>
									<?php foreach ( $events as $e ) : ?>
										<option value="<?php echo (int) $e['id']; ?>"><?php echo esc_html( $e['title'] . ( $e['date'] ? ' — ' . $e['date'] : '' ) . ' (' . $e['label'] . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button" data-cpa="link"><?php esc_html_e( 'Link', 'hypeit' ); ?></button>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			</div>
			<?php
			return;
		}

		$f = $st['funnel'];
		?>
		<div class="cpw-card cpa-event">
			<div class="cpa-event-head">
				<div>
					<h3 style="margin-bottom:4px;"><?php echo esc_html( $ev['title'] ); ?> <span class="cpw-tag<?php echo 'draft' === $ev['status'] ? ' is-rm' : ' is-new'; ?>"><?php echo esc_html( $ev['label'] ); ?></span></h3>
					<p class="cpw-muted" style="margin:0;">
						<?php
						$bits = array();
						if ( $ev['date'] ) {
							$bits[] = $ev['date'];
						}
						$bits[] = $ev['private'] ? __( 'Private (by invitation)', 'hypeit' ) : __( 'Public', 'hypeit' );
						if ( $ev['per_guest'] ) {
							$bits[] = __( 'Per-blogger visit times', 'hypeit' );
						}
						echo esc_html( implode( ' · ', $bits ) );
						?>
					</p>
				</div>
				<div class="cpw-row" style="margin:0;">
					<a class="button" href="<?php echo esc_url( $ev['edit'] ); ?>"><?php esc_html_e( 'Edit in ATRIUM', 'hypeit' ); ?></a>
					<?php if ( $ev['url'] ) : ?><a class="button" href="<?php echo esc_url( $ev['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Event page', 'hypeit' ); ?></a><?php endif; ?>
					<button type="button" class="button-link" data-cpa="unlink" style="color:#b3261e;"><?php esc_html_e( 'Unlink', 'hypeit' ); ?></button>
				</div>
			</div>
			<?php if ( 'draft' === $ev['status'] ) : ?>
				<p class="cpw-note" style="margin:12px 0 0;"><?php esc_html_e( 'The event is still a draft. Set its date and venue in ATRIUM and publish it before sending invitations — invitation links only open once it’s published.', 'hypeit' ); ?></p>
			<?php endif; ?>

			<div class="cpa-funnel">
				<?php
				$steps = array(
					'selected'  => __( 'Client selected', 'hypeit' ),
					'invited'   => __( 'Invited', 'hypeit' ),
					'opened'    => __( 'Opened', 'hypeit' ),
					'confirmed' => __( 'Confirmed', 'hypeit' ),
					'attended'  => __( 'Attended', 'hypeit' ),
				);
				foreach ( $steps as $k => $lab ) :
					?>
					<div class="cpa-step<?php echo 'attended' === $k ? ' is-green' : ''; ?>"><b><?php echo (int) $f[ $k ]; ?></b><span><?php echo esc_html( $lab ); ?></span></div>
				<?php endforeach; ?>
			</div>
			<?php if ( $f['no_show'] || $f['declined'] ) : ?>
				<p class="cpw-muted" style="margin:8px 0 0;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: no-shows, 2: can't attend. */
							__( '%1$d no-show · %2$d can’t attend', 'hypeit' ),
							(int) $f['no_show'],
							(int) $f['declined']
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="cpw-card">
			<h3><?php esc_html_e( 'Send confirmed bloggers to the event', 'hypeit' ); ?></h3>
			<p class="cpw-muted" style="margin-top:0;">
				<?php
				echo esc_html(
					$st['pending']
						/* translators: %d: count. */
						? sprintf( _n( '%d client-confirmed blogger is not invited yet.', '%d client-confirmed bloggers are not invited yet.', $st['pending'], 'hypeit' ), $st['pending'] )
						: __( 'Everyone the client confirmed is already invited. Sending again only updates headcounts.', 'hypeit' )
				);
				?>
			</p>
			<?php if ( $ev['per_guest'] ) : ?>
				<div class="cpw-row">
					<label class="cpw-check"><?php esc_html_e( 'Visit date for new invites', 'hypeit' ); ?> <input type="date" id="cpa-vdate" /></label>
					<label class="cpw-check"><?php esc_html_e( 'time', 'hypeit' ); ?> <input type="time" id="cpa-vtime" /></label>
					<span class="cpw-muted"><?php esc_html_e( 'Optional — you can set each blogger’s slot in ATRIUM.', 'hypeit' ); ?></span>
				</div>
			<?php endif; ?>
			<label class="cpw-check" style="margin:6px 0 12px;"><input type="checkbox" id="cpa-prune" /> <?php esc_html_e( 'Also remove bloggers the client has since deselected (never removes anyone who checked in)', 'hypeit' ); ?></label>
			<div><button type="button" class="button button-primary" data-cpa="send"><?php esc_html_e( 'Send to ATRIUM', 'hypeit' ); ?></button></div>
		</div>

		<?php if ( $st['rows'] ) : ?>
			<div class="cpw-card">
				<h3><?php esc_html_e( 'Invitations', 'hypeit' ); ?></h3>
				<div class="cpw-tablewrap">
					<table class="cpw-table">
						<thead><tr>
							<th><?php esc_html_e( 'Blogger', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'Status', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'People', 'hypeit' ); ?></th>
							<?php if ( $ev['per_guest'] ) : ?><th><?php esc_html_e( 'Visit', 'hypeit' ); ?></th><?php endif; ?>
							<th><?php esc_html_e( 'Invite', 'hypeit' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $st['rows'] as $r ) : ?>
								<tr>
									<td data-label=""><div class="cpw-who"><?php echo CP_Photo::avatar_html( $r['blogger'], 34, $r['name'], $r['handle'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div><span class="cpw-name"><?php echo esc_html( $r['name'] ); ?></span><span class="cpw-muted">@<?php echo esc_html( $r['handle'] ); ?><?php echo $r['selected'] ? '' : ' · ' . esc_html__( 'no longer selected', 'hypeit' ); ?></span></div></div></td>
									<td data-label="<?php esc_attr_e( 'Status', 'hypeit' ); ?>"><span class="cpa-stage cpa-<?php echo esc_attr( $r['stage'] ); ?>"><?php echo esc_html( $labels[ $r['stage'] ] ); ?></span><?php echo $r['code'] ? ' <code>' . esc_html( $r['code'] ) . '</code>' : ''; ?></td>
									<td data-label="<?php esc_attr_e( 'People', 'hypeit' ); ?>"><?php echo (int) $r['party']; ?></td>
									<?php if ( $ev['per_guest'] ) : ?><td data-label="<?php esc_attr_e( 'Visit', 'hypeit' ); ?>"><?php echo $r['visit'] ? esc_html( $r['visit'] ) : '&mdash;'; ?></td><?php endif; ?>
									<td class="cpa-actions">
										<?php if ( $r['wa'] ) : ?><a class="button button-small" href="<?php echo esc_url( $r['wa'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp', 'hypeit' ); ?></a><?php endif; ?>
										<button type="button" class="button button-small cp-copy-link" data-link="<?php echo esc_url( $r['url'] ); ?>"><?php esc_html_e( 'Copy link', 'hypeit' ); ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

		<div class="cpw-card">
			<h3><?php esc_html_e( 'WhatsApp invitation message', 'hypeit' ); ?></h3>
			<textarea id="cpa-msg" rows="4" class="widefat"><?php echo esc_textarea( $st['message'] ); ?></textarea>
			<p class="cpw-muted"><?php esc_html_e( 'Placeholders: {first} {name} {event} {link} {date}', 'hypeit' ); ?></p>
			<button type="button" class="button" data-cpa="message"><?php esc_html_e( 'Save message', 'hypeit' ); ?></button>
		</div>
		</div>
		<?php
	}
}
