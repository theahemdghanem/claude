<?php
/**
 * In-app notifications for the HypeIt app: new blogger submissions, profile
 * updates and client responses. One shared feed (newest first, capped); each
 * user keeps their own read / cleared state and preferences, so "mark all as
 * read" clears the icon bubble for that user only.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Inbox {

	const OPT   = 'cp_inbox';
	const MAX   = 200;
	const READ  = '_cp_inbox_read';    // Everything up to this item ID is read.
	const SEEN  = '_cp_inbox_seen';    // Individually opened item IDs above it.
	const CLEAR = '_cp_inbox_cleared'; // Items up to this ID are hidden.
	const PREFS = '_cp_inbox_prefs';

	/** Kinds → preference key. */
	const KINDS = array(
		'blogger_new'    => 'new',
		'blogger_update' => 'update',
		'response'       => 'response',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			CP_REST::NS,
			'/inbox',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_get' ),
					'permission_callback' => array( 'CP_REST', 'require_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_post' ),
					'permission_callback' => array( 'CP_REST', 'require_auth' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Feed                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Stored feed.
	 *
	 * @return array{next:int,items:array}
	 */
	private static function feed() {
		$f = get_option( self::OPT );
		if ( ! is_array( $f ) || ! isset( $f['items'] ) ) {
			$f = array( 'next' => 1, 'items' => array() );
		}
		return $f;
	}

	/**
	 * Add (or merge into) a notification.
	 *
	 * @param string $kind  blogger_new | blogger_update | response.
	 * @param array  $data  ref (blogger / campaign ID), plus kind-specific fields.
	 */
	private static function push( $kind, $data ) {
		$f   = self::feed();
		$now = time();
		$ref = (int) $data['ref'];

		// Merge repeats into the newest matching item (one row per blogger / campaign burst).
		foreach ( $f['items'] as $i => $it ) {
			if ( $it['kind'] !== $kind || (int) $it['ref'] !== $ref ) {
				continue;
			}
			$window = 'response' === $kind ? 2 * HOUR_IN_SECONDS : DAY_IN_SECONDS;
			if ( $now - (int) $it['t'] > $window ) {
				break;
			}
			array_splice( $f['items'], $i, 1 );
			if ( 'response' === $kind ) {
				foreach ( array( 'yes', 'no', 'undo' ) as $k ) {
					$data[ $k ] = (int) ( $it[ $k ] ?? 0 ) + (int) ( $data[ $k ] ?? 0 );
				}
			}
			break;
		}

		$item = array_merge( $data, array( 'id' => (int) $f['next'], 'kind' => $kind, 't' => $now ) );
		array_unshift( $f['items'], $item );
		$f['items'] = array_slice( $f['items'], 0, self::MAX );
		$f['next']  = (int) $f['next'] + 1;
		update_option( self::OPT, $f, false );
	}

	/**
	 * A blogger submitted the join form.
	 *
	 * @param int  $blogger_id Blogger ID.
	 * @param bool $updated    Existing profile updated.
	 */
	public static function blogger( $blogger_id, $updated ) {
		self::push( $updated ? 'blogger_update' : 'blogger_new', array( 'ref' => (int) $blogger_id ) );
	}

	/**
	 * A client changed a response on a campaign.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $old         Old status.
	 * @param string $new         New status.
	 */
	public static function response( $campaign_id, $old, $new ) {
		if ( $old === $new ) {
			return;
		}
		self::push(
			'response',
			array(
				'ref'  => (int) $campaign_id,
				'yes'  => 'confirmed' === $new ? 1 : 0,
				'no'   => 'declined' === $new ? 1 : 0,
				'undo' => 'pending' === $new ? 1 : 0,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Per-user state                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Preferences (all on by default).
	 *
	 * @param int $uid User ID.
	 * @return array{new:bool,update:bool,response:bool,badge:bool}
	 */
	public static function prefs( $uid ) {
		$p = get_user_meta( $uid, self::PREFS, true );
		$p = is_array( $p ) ? $p : array();
		$out = array();
		foreach ( array( 'new', 'update', 'response', 'badge' ) as $k ) {
			$out[ $k ] = isset( $p[ $k ] ) ? (bool) $p[ $k ] : true;
		}
		return $out;
	}

	/**
	 * Items this user sees (their kinds, not cleared), with read flags.
	 *
	 * @param int $uid User ID.
	 * @return array
	 */
	private static function visible( $uid ) {
		$prefs   = self::prefs( $uid );
		$read    = (int) get_user_meta( $uid, self::READ, true );
		$cleared = (int) get_user_meta( $uid, self::CLEAR, true );
		$seen    = array_map( 'intval', (array) get_user_meta( $uid, self::SEEN, true ) );
		$out     = array();
		foreach ( self::feed()['items'] as $it ) {
			$pk = self::KINDS[ $it['kind'] ] ?? '';
			if ( ! $pk || empty( $prefs[ $pk ] ) || (int) $it['id'] <= $cleared ) {
				continue;
			}
			$it['read'] = (int) $it['id'] <= $read || in_array( (int) $it['id'], $seen, true );
			$out[]      = $it;
		}
		return $out;
	}

	/**
	 * Unread count for a user (0 when they turned the bubble off).
	 *
	 * @param int $uid User ID.
	 * @return int
	 */
	public static function unread( $uid ) {
		return count( array_filter( self::visible( $uid ), static function ( $i ) { return ! $i['read']; } ) );
	}

	/**
	 * Item → what the app shows.
	 *
	 * @param array $it Item.
	 * @return array|null
	 */
	private static function present( $it ) {
		$ref = (int) $it['ref'];
		$out = array( 'id' => (int) $it['id'], 'kind' => $it['kind'], 't' => (int) $it['t'], 'read' => ! empty( $it['read'] ) );
		if ( 'response' === $it['kind'] ) {
			if ( CP_POST_TYPE !== get_post_type( $ref ) ) {
				return null;
			}
			$bits = array();
			if ( ! empty( $it['yes'] ) ) {
				/* translators: %d: count. */
				$bits[] = sprintf( _n( '%d accepted', '%d accepted', (int) $it['yes'], 'hypeit' ), (int) $it['yes'] );
			}
			if ( ! empty( $it['no'] ) ) {
				/* translators: %d: count. */
				$bits[] = sprintf( _n( '%d declined', '%d declined', (int) $it['no'], 'hypeit' ), (int) $it['no'] );
			}
			if ( ! empty( $it['undo'] ) ) {
				/* translators: %d: count. */
				$bits[] = sprintf( _n( '%d reset', '%d reset', (int) $it['undo'], 'hypeit' ), (int) $it['undo'] );
			}
			$out['title'] = html_entity_decode( get_the_title( $ref ), ENT_QUOTES );
			/* translators: %s: e.g. "3 accepted · 1 declined". */
			$out['body']  = sprintf( __( 'Client responded: %s', 'hypeit' ), implode( ' · ', $bits ) );
			$out['route'] = '/c/' . $ref;
			$out['logo']  = CP_Logo::url( $ref );
			return $out;
		}
		if ( CP_Library::CPT !== get_post_type( $ref ) ) {
			return null;
		}
		$name   = trim( get_post_meta( $ref, '_cp_first', true ) . ' ' . get_post_meta( $ref, '_cp_last', true ) );
		$handle = CP_Library::handle( $ref );
		$bits   = array( '@' . $handle );
		$f      = (int) get_post_meta( $ref, '_cp_followers', true );
		if ( $f ) {
			/* translators: %s: follower count. */
			$bits[] = sprintf( __( '%s followers', 'hypeit' ), number_format_i18n( $f ) );
		}
		$city = (string) get_post_meta( $ref, '_cp_city', true );
		if ( $city ) {
			$bits[] = $city;
		}
		$out['title']  = 'blogger_new' === $it['kind']
			/* translators: %s: name. */
			? sprintf( __( 'New submission: %s', 'hypeit' ), $name ? $name : '@' . $handle )
			/* translators: %s: name. */
			: sprintf( __( '%s updated their profile', 'hypeit' ), $name ? $name : '@' . $handle );
		$out['body']   = implode( ' · ', $bits );
		$out['route']  = '/b/' . $ref;
		$out['name']   = $name;
		$out['handle'] = $handle;
		$out['photo']  = CP_Photo::url( $ref, 'm' );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* REST                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * GET /inbox (?count=1 for just the number).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function rest_get( $request ) {
		$uid   = CP_REST::user_id();
		$prefs = self::prefs( $uid );
		$vis   = self::visible( $uid );
		$unread = count( array_filter( $vis, static function ( $i ) { return ! $i['read']; } ) );
		if ( $request->get_param( 'count' ) ) {
			return array( 'unread' => $unread, 'prefs' => $prefs );
		}
		$items = array();
		foreach ( array_slice( $vis, 0, 100 ) as $it ) {
			$p = self::present( $it );
			if ( $p ) {
				$items[] = $p;
			}
		}
		return array( 'items' => $items, 'unread' => $unread, 'prefs' => $prefs );
	}

	/**
	 * POST /inbox: op = read_all | read (id) | clear | prefs (prefs{}).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function rest_post( $request ) {
		$uid  = CP_REST::user_id();
		$op   = sanitize_key( (string) $request->get_param( 'op' ) );
		$last = (int) self::feed()['next'] - 1;
		switch ( $op ) {
			case 'read_all':
				update_user_meta( $uid, self::READ, $last );
				delete_user_meta( $uid, self::SEEN );
				break;
			case 'read':
				$id   = absint( $request->get_param( 'id' ) );
				$seen = array_map( 'intval', (array) get_user_meta( $uid, self::SEEN, true ) );
				if ( $id && ! in_array( $id, $seen, true ) ) {
					$seen[] = $id;
					update_user_meta( $uid, self::SEEN, array_slice( $seen, -self::MAX ) );
				}
				break;
			case 'clear':
				update_user_meta( $uid, self::CLEAR, $last );
				update_user_meta( $uid, self::READ, $last );
				delete_user_meta( $uid, self::SEEN );
				break;
			case 'prefs':
				$in  = (array) $request->get_param( 'prefs' );
				$cur = self::prefs( $uid );
				foreach ( $cur as $k => $v ) {
					if ( array_key_exists( $k, $in ) ) {
						$cur[ $k ] = (bool) $in[ $k ];
					}
				}
				update_user_meta( $uid, self::PREFS, $cur );
				break;
			default:
				return new WP_Error( 'cp_bad_op', __( 'Unknown action.', 'hypeit' ), array( 'status' => 400 ) );
		}
		return self::rest_get( new WP_REST_Request( 'GET' ) );
	}
}
