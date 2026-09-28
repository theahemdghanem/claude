<?php
/**
 * REST API for the Campaign review PWA.
 *
 * Uses a stateless bearer token (sent as the X-CP-Token header) so the app is
 * domain-independent and can stay logged in. Tokens are stored hashed in user
 * meta with a sliding expiry.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_REST {

	const NS = 'campaign/v1';

	/**
	 * The authenticated user id for the current request.
	 *
	 * @var int
	 */
	private static $current_user_id = 0;

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/auth',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username' => array( 'required' => true ),
					'password' => array( 'required' => true ),
					'remember' => array( 'required' => false ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/session',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'session' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'campaigns' ),
					'permission_callback' => array( __CLASS__, 'require_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_campaign' ),
					'permission_callback' => array( __CLASS__, 'require_auth' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'campaign' ),
					'permission_callback' => array( __CLASS__, 'require_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_campaign' ),
					'permission_callback' => array( __CLASS__, 'require_auth' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/action',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'campaign_action' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/atrium',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'campaign_atrium' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/atrium/events',
			array(
				'methods'             => 'GET',
				'callback'            => function () { return array( 'active' => CP_Atrium::active(), 'events' => CP_Atrium::events() ); },
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/logo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'campaign_logo' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'campaign_export' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/participants',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'participants' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'logout' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/insights',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'insights' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'meta' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/lists',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'lists' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bloggers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'bloggers' ),
					'permission_callback' => array( __CLASS__, 'require_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_blogger' ),
					'permission_callback' => array( __CLASS__, 'require_auth' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/bloggers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'blogger' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bloggers/(?P<id>\d+)/photo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'blogger_photo' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/push/(?P<op>key|subscribe|unsubscribe|status|test)',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( 'CP_Push', 'rest' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bloggers/(?P<id>\d+)/action',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'blogger_action' ),
				'permission_callback' => array( __CLASS__, 'require_auth' ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Token helpers                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Token lifetime.
	 *
	 * @param bool $remember Keep-me-logged-in flag.
	 * @return int
	 */
	private static function ttl( $remember ) {
		return $remember ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS;
	}

	/**
	 * Issue a token for a user.
	 *
	 * @param int  $user_id  User ID.
	 * @param bool $remember Keep-me-logged-in flag.
	 * @return string
	 */
	private static function issue_token( $user_id, $remember ) {
		$secret = wp_generate_password( 48, false, false );
		$now    = time();
		$ttl    = self::ttl( $remember );

		$tokens = get_user_meta( $user_id, '_cp_app_tokens', true );
		if ( ! is_array( $tokens ) ) {
			$tokens = array();
		}

		// Drop expired tokens.
		$tokens = array_values(
			array_filter(
				$tokens,
				static function ( $t ) use ( $now ) {
					return isset( $t['exp'] ) && $t['exp'] > $now;
				}
			)
		);

		$tokens[] = array(
			'h'   => hash( 'sha256', $secret ),
			'exp' => $now + $ttl,
			'ttl' => $ttl,
			'ua'  => substr( sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ), 0, 120 ),
		);

		if ( count( $tokens ) > 20 ) {
			$tokens = array_slice( $tokens, -20 );
		}

		update_user_meta( $user_id, '_cp_app_tokens', $tokens );

		return $user_id . '.' . $secret;
	}

	/**
	 * Read the token from the request (header preferred, param fallback).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private static function get_token( $request ) {
		$token = $request->get_header( 'x_cp_token' );
		if ( ! $token ) {
			$token = $request->get_header( 'X-CP-Token' );
		}
		if ( ! $token ) {
			$auth = $request->get_header( 'authorization' );
			if ( $auth && preg_match( '/Bearer\s+(.+)/i', $auth, $m ) ) {
				$token = trim( $m[1] );
			}
		}
		if ( ! $token ) {
			$token = $request->get_param( 'token' );
		}
		return (string) $token;
	}

	/**
	 * Parse a token into user id + secret.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	private static function parse_token( $token ) {
		$token = (string) $token;
		$pos   = strpos( $token, '.' );
		if ( false === $pos ) {
			return null;
		}
		$uid    = absint( substr( $token, 0, $pos ) );
		$secret = substr( $token, $pos + 1 );
		if ( ! $uid || '' === $secret ) {
			return null;
		}
		return array(
			'uid'    => $uid,
			'secret' => $secret,
		);
	}

	/**
	 * Permission callback: validate the bearer token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function require_auth( $request ) {
		$parsed = self::parse_token( self::get_token( $request ) );
		if ( ! $parsed ) {
			return false;
		}

		$tokens = get_user_meta( $parsed['uid'], '_cp_app_tokens', true );
		if ( ! is_array( $tokens ) ) {
			return false;
		}

		$now     = time();
		$hash    = hash( 'sha256', $parsed['secret'] );
		$ok      = false;
		$changed = false;

		foreach ( $tokens as $i => $t ) {
			if ( isset( $t['h'], $t['exp'] ) && hash_equals( (string) $t['h'], $hash ) && $t['exp'] > $now ) {
				$ok = true;
				// Sliding expiry keeps the app logged in with use.
				$ttl               = isset( $t['ttl'] ) ? (int) $t['ttl'] : DAY_IN_SECONDS;
				$tokens[ $i ]['exp'] = $now + $ttl;
				$changed           = true;
				break;
			}
		}

		if ( ! $ok || ! user_can( $parsed['uid'], 'edit_posts' ) ) {
			return false;
		}

		if ( $changed ) {
			update_user_meta( $parsed['uid'], '_cp_app_tokens', $tokens );
		}

		self::$current_user_id = $parsed['uid'];
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Endpoints                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Authenticate and return a token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function auth( $request ) {
		$ip       = self::client_ip();
		$key      = 'cp_login_' . md5( $ip );
		$attempts = (int) get_transient( $key );

		if ( $attempts >= 10 ) {
			return new WP_Error( 'cp_too_many', __( 'Too many attempts. Please try again later.', 'hypeit' ), array( 'status' => 429 ) );
		}

		$username = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );
		$remember = (bool) $request->get_param( 'remember' );

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
			return new WP_Error( 'cp_invalid', __( 'Invalid username or password.', 'hypeit' ), array( 'status' => 403 ) );
		}

		if ( ! user_can( $user, 'edit_posts' ) ) {
			return new WP_Error( 'cp_forbidden', __( 'This account cannot access the app.', 'hypeit' ), array( 'status' => 403 ) );
		}

		delete_transient( $key );

		return array(
			'token'   => self::issue_token( $user->ID, $remember ),
			'user'    => array( 'name' => $user->display_name ),
			'expires' => time() + self::ttl( $remember ),
		);
	}

	/**
	 * Return the current session's user.
	 *
	 * @return array
	 */
	public static function session() {
		$user = get_userdata( self::$current_user_id );
		return array( 'user' => array( 'name' => $user ? $user->display_name : '' ) );
	}

	/**
	 * Revoke the current token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function logout( $request ) {
		$parsed = self::parse_token( self::get_token( $request ) );
		if ( $parsed ) {
			$tokens = get_user_meta( $parsed['uid'], '_cp_app_tokens', true );
			if ( is_array( $tokens ) ) {
				$hash   = hash( 'sha256', $parsed['secret'] );
				$tokens = array_values(
					array_filter(
						$tokens,
						static function ( $t ) use ( $hash ) {
							return ! ( isset( $t['h'] ) && hash_equals( (string) $t['h'], $hash ) );
						}
					)
				);
				update_user_meta( $parsed['uid'], '_cp_app_tokens', $tokens );
			}
		}
		return array( 'ok' => true );
	}

	/**
	 * Campaign list with summary stats.
	 *
	 * @return array
	 */
	public static function campaigns() {
		return array( 'campaigns' => CP_DB::all_campaign_stats( true ) );
	}

	/**
	 * Campaign detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function campaign( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );

		if ( ! $post || CP_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}

		$s        = CP_DB::stats( $id );
		$bloggers = array();
		$pmap     = CP_Insights::popularity_map();
		$vmap     = CP_Verify::verified_map();
		$phmap    = CP_Photo::map_by_handle();

		foreach ( $s['rows'] as $r ) {
			$lab  = isset( $pmap[ strtolower( $r->ig_account ) ] ) ? CP_Insights::label( $pmap[ strtolower( $r->ig_account ) ] ) : array( 'label' => '', 'key' => '' );
			$vinf = isset( $vmap[ strtolower( $r->ig_account ) ] ) ? $vmap[ strtolower( $r->ig_account ) ] : array( 'verified' => false, 'reach' => 0 );
			$bloggers[] = array(
				'row_id'       => (int) $r->id,
				'account'      => $r->ig_account,
				'status'       => $r->status,
				'extra_guests' => (int) $r->extra_guests,
				'people'       => ( 'confirmed' === $r->status ) ? ( 1 + (int) $r->extra_guests ) : 0,
				'followers'    => (int) $r->followers,
				'gender'       => (string) $r->gender,
				'tags'         => (string) $r->tags,
				'city'         => (string) $r->city,
				'area'         => (string) $r->area,
				'popularity'   => $lab['label'],
				'verified'     => ! empty( $vinf['verified'] ),
				'reach'        => (int) $vinf['reach'],
				'photo'        => isset( $phmap[ strtolower( $r->ig_account ) ] ) ? $phmap[ strtolower( $r->ig_account ) ] : '',
			);
		}

		return array(
			'id'       => $id,
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
			'stats'    => array(
				'total'        => (int) $s['total'],
				'confirmed'    => (int) $s['confirmed'],
				'declined'     => (int) $s['declined'],
				'pending'      => (int) $s['pending'],
				'extra_guests' => (int) $s['extra_guests'],
				'attendance'   => (int) $s['attendance'],
			),
			'bloggers' => $bloggers,
			'everyone' => CP_Everyone::is_on( $id ),
			'selection_started' => CP_Everyone::selection_started( $id ),
			'library_total'     => count( CP_Everyone::all_handles() ),
			'settings'          => self::campaign_settings( $id ),
			'atrium'            => self::atrium_payload( $id ),
		);
	}

	/**
	 * ATRIUM event status for the app.
	 *
	 * @param int $id Campaign ID.
	 * @return array|null
	 */
	private static function atrium_payload( $id ) {
		if ( ! CP_Atrium::active() ) {
			return null;
		}
		$st = CP_Atrium::status( $id );
		return array(
			'event'   => $st['event'],
			'funnel'  => $st['funnel'],
			'pending' => $st['pending'],
			'rows'    => array_values( $st['rows'] ),
			'labels'  => CP_Atrium::stage_labels(),
		);
	}

	/**
	 * Run an ATRIUM action from the app (link, unlink, create, send).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function campaign_atrium( $request ) {
		$id = absint( $request['id'] );
		if ( CP_POST_TYPE !== get_post_type( $id ) ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}
		$r = CP_Atrium::run( $id, $request->get_params() );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 400 ) );
		}
		return self::campaign_response( $id, '', isset( $r['notice'] ) ? $r['notice'] : '' );
	}

	/**
	 * Editable settings of a campaign (for the app's edit form).
	 *
	 * @param int $id Campaign ID.
	 * @return array
	 */
	private static function campaign_settings( $id ) {
		$post    = get_post( $id );
		$token   = (string) get_post_meta( $id, '_cp_token', true );
		$max     = get_post_meta( $id, '_cp_max_guests', true );
		$logo_id = (int) get_post_meta( $id, '_cp_logo_id', true );
		return array(
			'status'          => $post ? $post->post_status : 'draft',
			'closed'          => CP_Close::is_closed( $id ),
			'brief'           => (string) get_post_meta( $id, '_cp_brief', true ),
			'max_guests'      => ( '' === $max ) ? 4 : (int) $max,
			'slug'            => $token,
			'url'             => $token ? CP_Frontend::campaign_url( $token ) : '',
			'slug_base'       => trailingslashit( home_url( '/campaign/' ) ),
			'has_password'    => CP_Auth::has_password( $id ),
			'notify_email'    => (string) get_post_meta( $id, '_cp_notify_email', true ),
			'logo_url'        => $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '',
			'show_followers'  => '1' === get_post_meta( $id, '_cp_show_followers', true ),
			'show_gender'     => '1' === get_post_meta( $id, '_cp_show_gender', true ),
			'show_tags'       => '1' === get_post_meta( $id, '_cp_show_tags', true ),
			'show_location'   => '1' === get_post_meta( $id, '_cp_show_location', true ),
			'show_popularity' => '0' !== get_post_meta( $id, '_cp_show_popularity', true ),
		);
	}

	/**
	 * Apply settings from an app request. Only fields present are changed.
	 *
	 * @param int             $id      Campaign ID.
	 * @param WP_REST_Request $request Request.
	 * @return string New plain password if one was set in this request, else ''.
	 */
	private static function apply_campaign_settings( $id, $request ) {
		$p = $request->get_params();

		if ( isset( $p['brief'] ) ) {
			update_post_meta( $id, '_cp_brief', wp_kses_post( (string) $p['brief'] ) );
		}
		if ( isset( $p['max_guests'] ) ) {
			update_post_meta( $id, '_cp_max_guests', min( 20, absint( $p['max_guests'] ) ) );
		}
		if ( isset( $p['notify_email'] ) ) {
			update_post_meta( $id, '_cp_notify_email', sanitize_text_field( (string) $p['notify_email'] ) );
		}
		foreach ( array( 'followers', 'gender', 'tags', 'location', 'popularity' ) as $k ) {
			if ( isset( $p[ 'show_' . $k ] ) ) {
				update_post_meta( $id, '_cp_show_' . $k, rest_sanitize_boolean( $p[ 'show_' . $k ] ) ? '1' : '0' );
			}
		}
		if ( isset( $p['remove_logo'] ) && rest_sanitize_boolean( $p['remove_logo'] ) ) {
			delete_post_meta( $id, '_cp_logo_id' );
		}

		// Link: random on request, else a custom slug, else keep/create.
		$current = (string) get_post_meta( $id, '_cp_token', true );
		$desired = isset( $p['slug'] ) ? sanitize_title( (string) $p['slug'] ) : '';
		if ( ! empty( $p['regenerate'] ) && rest_sanitize_boolean( $p['regenerate'] ) ) {
			update_post_meta( $id, '_cp_token', CP_Admin::generate_token() );
		} elseif ( '' !== $desired && $desired !== $current ) {
			update_post_meta( $id, '_cp_token', CP_Admin::unique_slug( $desired, $id ) );
		} elseif ( '' === $current ) {
			update_post_meta( $id, '_cp_token', CP_Admin::generate_token() );
		}

		$new_pw = '';
		if ( isset( $p['password'] ) && '' !== trim( (string) $p['password'] ) ) {
			$new_pw = (string) $p['password'];
			CP_Auth::set_password( $id, $new_pw );
		}

		if ( isset( $p['everyone'] ) ) {
			CP_Everyone::set_on( $id, rest_sanitize_boolean( $p['everyone'] ) );
		}
		return $new_pw;
	}

	/**
	 * Create a campaign from the app.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function create_campaign( $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( '' === $title ) {
			return new WP_Error( 'cp_no_title', __( 'Please give the campaign a name.', 'hypeit' ), array( 'status' => 400 ) );
		}
		$status = 'draft' === $request->get_param( 'status' ) ? 'draft' : 'publish';

		$id = wp_insert_post(
			array(
				'post_type'   => CP_POST_TYPE,
				'post_status' => $status,
				'post_title'  => $title,
				'post_author' => (int) self::$current_user_id,
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'cp_save_failed', __( 'Could not create the campaign.', 'hypeit' ), array( 'status' => 500 ) );
		}

		// Defaults for fields the form didn't send.
		add_post_meta( $id, '_cp_max_guests', 4, true );
		add_post_meta( $id, '_cp_show_popularity', '1', true );

		$handles = $request->get_param( 'handles' );
		if ( is_array( $handles ) && $handles ) {
			CP_Everyone::add_handles( $id, array_map( 'sanitize_text_field', $handles ) );
		}

		$pw  = self::apply_campaign_settings( $id, $request );
		return self::campaign_response( $id, $pw, __( 'Campaign created.', 'hypeit' ) );
	}

	/**
	 * Update a campaign's settings from the app.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function update_campaign( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );
		if ( ! $post || CP_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}

		$upd = array( 'ID' => $id );
		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$title = sanitize_text_field( (string) $title );
			if ( '' === $title ) {
				return new WP_Error( 'cp_no_title', __( 'Please give the campaign a name.', 'hypeit' ), array( 'status' => 400 ) );
			}
			$upd['post_title'] = $title;
		}
		$status = $request->get_param( 'status' );
		if ( in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$upd['post_status'] = $status;
		}
		if ( count( $upd ) > 1 ) {
			wp_update_post( $upd );
		}

		$pw = self::apply_campaign_settings( $id, $request );
		return self::campaign_response( $id, $pw, __( 'Campaign saved.', 'hypeit' ) );
	}

	/**
	 * Full campaign payload + notice (+ the new password, shown once).
	 *
	 * @param int    $id     Campaign ID.
	 * @param string $pw     New password or ''.
	 * @param string $notice Notice.
	 * @return array
	 */
	private static function campaign_response( $id, $pw, $notice ) {
		$req       = new WP_REST_Request( 'GET' );
		$req['id'] = $id;
		$out       = self::campaign( $req );
		if ( is_array( $out ) ) {
			$out['notice']       = $notice;
			$out['new_password'] = $pw;
		}
		return $out;
	}

	/**
	 * Campaign actions: publish, draft, reset, duplicate, trash.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function campaign_action( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );
		if ( ! $post || CP_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}

		switch ( sanitize_key( (string) $request->get_param( 'op' ) ) ) {
			case 'publish':
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
				return self::campaign_response( $id, '', __( 'Campaign is live.', 'hypeit' ) );

			case 'draft':
				wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
				return self::campaign_response( $id, '', __( 'Campaign moved to drafts. The client link is paused.', 'hypeit' ) );

			case 'close':
				CP_Close::set( $id, true );
				return self::campaign_response( $id, '', __( 'Campaign closed. The client link no longer works.', 'hypeit' ) );

			case 'reopen':
				CP_Close::set( $id, false );
				return self::campaign_response( $id, '', __( 'Campaign reopened.', 'hypeit' ) );

			case 'reset':
				CP_DB::reset_campaign( $id );
				CP_Everyone::clear_started( $id );
				CP_Everyone::fill( $id );
				return self::campaign_response( $id, '', __( 'All responses were reset.', 'hypeit' ) );

			case 'duplicate':
				$new = wp_insert_post(
					array(
						'post_type'   => CP_POST_TYPE,
						'post_status' => 'draft',
						/* translators: %s: campaign title. */
						'post_title'  => sprintf( __( '%s (copy)', 'hypeit' ), $post->post_title ),
						'post_author' => (int) self::$current_user_id,
					)
				);
				if ( ! $new || is_wp_error( $new ) ) {
					return new WP_Error( 'cp_save_failed', __( 'Could not duplicate.', 'hypeit' ), array( 'status' => 500 ) );
				}
				foreach ( array( '_cp_brief', '_cp_max_guests', '_cp_logo_id', '_cp_notify_email', '_cp_show_followers', '_cp_show_gender', '_cp_show_tags', '_cp_show_location', '_cp_show_popularity', CP_Everyone::META_ON ) as $k ) {
					$v = get_post_meta( $id, $k, true );
					if ( '' !== $v ) {
						update_post_meta( $new, $k, $v );
					}
				}
				update_post_meta( $new, '_cp_token', CP_Admin::generate_token() );
				$accounts = array();
				foreach ( CP_DB::get_bloggers( $id ) as $row ) {
					$accounts[] = $row->ig_account;
				}
				if ( $accounts ) {
					CP_DB::sync_accounts( $new, $accounts );
				}
				return self::campaign_response( $new, '', __( 'Duplicated as a draft. Set a password before sharing.', 'hypeit' ) );

			case 'trash':
				wp_trash_post( $id );
				return array( 'trashed' => true, 'notice' => __( 'Campaign moved to trash.', 'hypeit' ) );
		}
		return new WP_Error( 'cp_bad_op', __( 'Unknown action.', 'hypeit' ), array( 'status' => 400 ) );
	}

	/**
	 * Upload a campaign logo (multipart field "file").
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function campaign_logo( $request ) {
		$id = absint( $request['id'] );
		if ( CP_POST_TYPE !== get_post_type( $id ) ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'cp_no_file', __( 'No image received.', 'hypeit' ), array( 'status' => 400 ) );
		}
		$type = wp_check_filetype( $files['file']['name'] );
		if ( ! $type['type'] || 0 !== strpos( $type['type'], 'image/' ) ) {
			return new WP_Error( 'cp_bad_file', __( 'Please upload an image (JPG, PNG, WebP or SVG).', 'hypeit' ), array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		wp_set_current_user( (int) self::$current_user_id );
		$_FILES['cp_logo_upload'] = $files['file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$att = media_handle_upload( 'cp_logo_upload', $id, array(), array( 'test_form' => false ) );
		if ( is_wp_error( $att ) ) {
			return new WP_Error( 'cp_upload_failed', $att->get_error_message(), array( 'status' => 400 ) );
		}
		update_post_meta( $id, '_cp_logo_id', (int) $att );
		return array( 'logo_url' => (string) wp_get_attachment_image_url( $att, 'medium' ) );
	}

	/**
	 * Accepted bloggers as CSV text (the app turns it into a file).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function campaign_export( $request ) {
		$id = absint( $request['id'] );
		if ( CP_POST_TYPE !== get_post_type( $id ) ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}
		return array(
			'filename' => CP_Admin::export_filename( $id ),
			'csv'      => CP_Admin::accepted_csv( $id ),
		);
	}

	/**
	 * Manage a campaign's participants from the app.
	 * Actions: add (handles[]), add_all, remove (row_id), everyone (on).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function participants( $request ) {
		$id   = absint( $request['id'] );
		$post = get_post( $id );
		if ( ! $post || CP_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'cp_not_found', __( 'Campaign not found.', 'hypeit' ), array( 'status' => 404 ) );
		}

		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		$notice = '';

		switch ( $action ) {
			case 'add':
				$handles = array_map( 'sanitize_text_field', (array) $request->get_param( 'handles' ) );
				$n       = CP_Everyone::add_handles( $id, $handles );
				/* translators: %d count. */
				$notice = sprintf( _n( '%d blogger added.', '%d bloggers added.', $n, 'hypeit' ), $n );
				break;

			case 'add_all':
				$n = CP_Everyone::add_handles( $id, CP_Everyone::all_handles() );
				/* translators: %d count. */
				$notice = $n ? sprintf( _n( '%d blogger added.', '%d bloggers added.', $n, 'hypeit' ), $n ) : __( 'Everyone is already in this campaign.', 'hypeit' );
				break;

			case 'remove':
				if ( ! CP_Everyone::remove_row( $id, absint( $request->get_param( 'row_id' ) ) ) ) {
					return new WP_Error( 'cp_not_found', __( 'Blogger not found in this campaign.', 'hypeit' ), array( 'status' => 404 ) );
				}
				$notice = __( 'Blogger removed.', 'hypeit' );
				break;

			case 'everyone':
				$on = rest_sanitize_boolean( $request->get_param( 'on' ) );
				$n  = CP_Everyone::set_on( $id, $on );
				if ( ! $on ) {
					$notice = __( 'Everyone turned off.', 'hypeit' );
				} elseif ( CP_Everyone::selection_started( $id ) ) {
					$notice = __( 'Everyone is on, but the client has started selecting — use “Add everyone now” to include new bloggers.', 'hypeit' );
				} else {
					/* translators: %d count. */
					$notice = sprintf( __( 'Everyone is on. %d bloggers added.', 'hypeit' ), $n );
				}
				break;

			default:
				return new WP_Error( 'cp_bad_action', __( 'Unknown action.', 'hypeit' ), array( 'status' => 400 ) );
		}

		$out = self::campaign( $request );
		if ( is_array( $out ) ) {
			$out['notice'] = $notice;
		}
		return $out;
	}

	/**
	 * Metadata for the app's forms (genders, categories, cities, areas, lists).
	 *
	 * @return array
	 */
	public static function meta() {
		$lists = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false ) );
		$out   = array();
		if ( ! is_wp_error( $lists ) ) {
			foreach ( $lists as $t ) {
				$out[] = array(
					'id'   => (int) $t->term_id,
					'name' => $t->name,
				);
			}
		}
		$cat_names = array();
		foreach ( CP_Library::category_terms() as $ct ) {
			$cat_names[] = $ct->name;
		}
		return array(
			'genders'    => CP_Library::genders(),
			'missItems'  => CP_Bloggers_UI::items(),
			'categories' => $cat_names,
			'collab'     => CP_Library::collab_types(),
			'cities'     => CP_Location::cities(),
			'areas'      => CP_Location::areas_map(),
			'lists'      => $out,
		);
	}

	/**
	 * Lists with blogger counts.
	 *
	 * @return array
	 */
	public static function lists() {
		$terms = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false ) );
		$out   = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array(
					'id'    => (int) $t->term_id,
					'name'  => $t->name,
					'count' => (int) $t->count,
				);
			}
		}
		return array( 'lists' => $out );
	}

	/**
	 * Blogger list (optionally filtered by search + list).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function bloggers( $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$list   = absint( $request->get_param( 'list' ) );

		$args = array(
			'post_type'      => CP_Library::CPT,
			'post_status'    => 'any',
			'posts_per_page' => 2000,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( $list ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => CP_Library::TAX_LIST,
					'field'    => 'term_id',
					'terms'    => $list,
				),
			);
		}

		$ids = array_map( 'intval', (array) get_posts( $args ) );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			update_object_term_cache( $ids, CP_Library::CPT );
		}
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = self::blogger_row( $id );
		}
		return array( 'bloggers' => $out );
	}

	/**
	 * Compact blogger row.
	 *
	 * @param int $id Post ID.
	 * @return array
	 */
	private static function blogger_row( $id ) {
		$first = get_post_meta( $id, '_cp_first', true );
		$last  = get_post_meta( $id, '_cp_last', true );
		$name  = trim( $first . ' ' . $last );
		$lab   = CP_Insights::label_for( CP_Library::handle( $id ) );
		return array(
			'id'        => (int) $id,
			'name'      => '' !== $name ? $name : get_the_title( $id ),
			'handle'    => CP_Library::handle( $id ),
			'followers' => (int) get_post_meta( $id, '_cp_followers', true ),
			'gender'    => (string) get_post_meta( $id, '_cp_gender', true ),
			'blocked'   => '1' === (string) get_post_meta( $id, '_cp_blocked', true ),
			'popularity'=> $lab['label'],
			'verified'  => CP_Verify::is_verified( $id ),
			'igs'       => (string) get_post_meta( $id, '_cp_ig_status', true ),
			'miss'      => array_values( array_filter( explode( ',', (string) get_post_meta( $id, '_cp_missing', true ) ) ) ),
			'complete'  => (int) get_post_meta( $id, '_cp_complete', true ),
			'city'      => (string) get_post_meta( $id, '_cp_city', true ),
			'collab'    => (string) get_post_meta( $id, '_cp_collab', true ),
			'lists'     => array_map( 'intval', (array) wp_get_post_terms( $id, CP_Library::TAX_LIST, array( 'fields' => 'ids' ) ) ),
			'date'      => get_post_time( 'c', true, $id ),
			'photo'     => CP_Photo::url( $id, 's' ),
		);
	}

	/**
	 * Global insights.
	 *
	 * @return array
	 */
	public static function insights( $request = null ) {
		$period = ( $request instanceof WP_REST_Request && null !== $request->get_param( 'period' ) ) ? (int) $request->get_param( 'period' ) : 90;
		return CP_Insights::report( $period );
	}

	/**
	 * Full blogger detail (admin app — includes private fields).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function blogger( $request ) {
		$id = absint( $request['id'] );
		if ( CP_Library::CPT !== get_post_type( $id ) ) {
			return new WP_Error( 'cp_not_found', __( 'Blogger not found.', 'hypeit' ), array( 'status' => 404 ) );
		}

		$handle    = CP_Library::handle( $id );
		$tags      = wp_get_post_terms( $id, CP_Library::TAX_TAG, array( 'fields' => 'names' ) );
		$list_ids  = wp_get_post_terms( $id, CP_Library::TAX_LIST, array( 'fields' => 'ids' ) );

		return array(
			'blogger' => array(
				'id'        => (int) $id,
				'first'     => (string) get_post_meta( $id, '_cp_first', true ),
				'last'      => (string) get_post_meta( $id, '_cp_last', true ),
				'handle'    => $handle,
				'url'       => $handle ? CP_Library::profile_url( $handle ) : '',
				'gender'    => (string) get_post_meta( $id, '_cp_gender', true ),
				'followers' => (int) get_post_meta( $id, '_cp_followers', true ),
				'city'      => (string) get_post_meta( $id, '_cp_city', true ),
				'area'      => (string) get_post_meta( $id, '_cp_area', true ),
				'country'   => (string) get_post_meta( $id, '_cp_country', true ),
				'birthday'  => (string) get_post_meta( $id, '_cp_birthday', true ),
				'email'     => (string) get_post_meta( $id, '_cp_email', true ),
				'phone'     => (string) get_post_meta( $id, '_cp_phone', true ),
				'whatsapp'  => (string) get_post_meta( $id, '_cp_whatsapp', true ),
				'address'   => (string) get_post_meta( $id, '_cp_address', true ),
				'blocked'   => '1' === (string) get_post_meta( $id, '_cp_blocked', true ),
				'source'    => (string) get_post_meta( $id, '_cp_source', true ),
				'tags'      => is_wp_error( $tags ) ? array() : array_values( $tags ),
				'lists'     => is_wp_error( $list_ids ) ? array() : array_map( 'intval', $list_ids ),
				'insights'  => $handle ? CP_Insights::stats_for_handle( $handle ) : null,
				'verified'  => CP_Verify::is_verified( $id ),
				'verified_method' => (string) get_post_meta( $id, '_cp_verified_method', true ),
				'verify_code'     => (string) get_post_meta( $id, '_cp_verify_code', true ),
				'reach'     => (int) get_post_meta( $id, '_cp_reach', true ),
				'collab'    => array_values( array_filter( explode( ',', trim( (string) get_post_meta( $id, '_cp_collab', true ), ',' ) ) ) ),
				'photo'     => CP_Photo::url( $id, 'm' ),
				'photo_s'   => CP_Photo::url( $id, 's' ),
				'campaigns' => self::history_with_events( $id, $handle ),
				'reliability' => CP_Atrium::reliability( $id ),
				'ig'          => array_merge( CP_IGSync::info( $id ), array( 'switch_wa' => 'personal' === get_post_meta( $id, '_cp_ig_status', true ) ? CP_IGSync::switch_wa( $id ) : '' ) ),
			),
		);
	}

	/**
	 * Create or update a blogger from the app.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save_blogger( $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$first  = CP_Library::normalize_name( (string) $request->get_param( 'first' ) );
		$last   = CP_Library::normalize_name( (string) $request->get_param( 'last' ) );
		$handle = CP_Library::extract_handle( (string) $request->get_param( 'ig' ) );

		if ( '' === $handle ) {
			return new WP_Error( 'cp_no_handle', __( 'An Instagram username is required.', 'hypeit' ), array( 'status' => 400 ) );
		}

		// One Instagram account = one profile — never create a second, never
		// silently overwrite someone else's profile.
		$owner = CP_Dupes::owner( $handle, $id );
		if ( $owner ) {
			return new WP_Error( 'cp_duplicate', CP_Dupes::message( $handle, $owner ), array( 'status' => 409, 'existing' => $owner ) );
		}

		$name  = trim( $first . ' ' . $last );
		$title = $name ? $name . ' (@' . $handle . ')' : '@' . $handle;

		if ( $id ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
		} else {
			$id = wp_insert_post(
				array(
					'post_type'   => CP_Library::CPT,
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
		}
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'cp_save_failed', __( 'Could not save.', 'hypeit' ), array( 'status' => 500 ) );
		}

		$genders = CP_Library::genders();
		$gender  = sanitize_key( (string) $request->get_param( 'gender' ) );
		if ( ! array_key_exists( $gender, $genders ) ) {
			$gender = '';
		}

		update_post_meta( $id, '_cp_first', $first );
		update_post_meta( $id, '_cp_last', $last );
		update_post_meta( $id, '_cp_ig', $handle );
		update_post_meta( $id, '_cp_ig_url', CP_Library::profile_url( $handle ) );
		update_post_meta( $id, '_cp_gender', $gender );
		update_post_meta( $id, '_cp_followers', absint( $request->get_param( 'followers' ) ) );
		update_post_meta( $id, '_cp_country', sanitize_text_field( (string) ( $request->get_param( 'country' ) ? $request->get_param( 'country' ) : 'Egypt' ) ) );
		update_post_meta( $id, '_cp_city', sanitize_text_field( (string) $request->get_param( 'city' ) ) );
		if ( null !== $request->get_param( 'email' ) ) {
			update_post_meta( $id, '_cp_email', sanitize_email( (string) $request->get_param( 'email' ) ) );
		}
		update_post_meta( $id, '_cp_birthday', sanitize_text_field( (string) $request->get_param( 'birthday' ) ) );
		update_post_meta( $id, '_cp_phone', sanitize_text_field( (string) $request->get_param( 'phone' ) ) );
		update_post_meta( $id, '_cp_whatsapp', sanitize_text_field( (string) $request->get_param( 'whatsapp' ) ) );
		if ( null !== $request->get_param( 'address' ) ) {
			update_post_meta( $id, '_cp_address', sanitize_text_field( (string) $request->get_param( 'address' ) ) );
		}

		// Open for campaigns (only when sent).
		$collab = $request->get_param( 'collab' );
		if ( is_array( $collab ) ) {
			$collab = array_values( array_intersect( array_keys( CP_Library::collab_types() ), array_map( 'sanitize_key', $collab ) ) );
			update_post_meta( $id, '_cp_collab', $collab ? ',' . implode( ',', $collab ) . ',' : '' );
		}

		// Categories (only existing ones — new categories are created in Manage Categories).
		$tags = $request->get_param( 'tags' );
		if ( is_array( $tags ) ) {
			$ids = array();
			foreach ( array_filter( array_map( 'sanitize_text_field', $tags ) ) as $name ) {
				$term = get_term_by( 'name', $name, CP_Library::TAX_TAG );
				if ( $term ) {
					$ids[] = (int) $term->term_id;
				}
			}
			wp_set_post_terms( $id, $ids, CP_Library::TAX_TAG, false );
		}

		// Lists (existing ids + optional new name).
		$lists = $request->get_param( 'lists' );
		$lists = is_array( $lists ) ? array_filter( array_map( 'absint', $lists ) ) : array();
		$new_list = sanitize_text_field( (string) $request->get_param( 'new_list' ) );
		if ( '' !== $new_list ) {
			$term = get_term_by( 'name', $new_list, CP_Library::TAX_LIST );
			if ( ! $term ) {
				$created = wp_insert_term( $new_list, CP_Library::TAX_LIST );
				if ( ! is_wp_error( $created ) ) {
					$lists[] = (int) $created['term_id'];
				}
			} else {
				$lists[] = (int) $term->term_id;
			}
		}
		wp_set_post_terms( $id, $lists, CP_Library::TAX_LIST, false );

		CP_Blogger_CPT::sync_title( $id );

		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}

		return array( 'id' => (int) $id );
	}

	/**
	 * Upload (multipart "file") or remove ("remove"=1) a blogger's photo.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function blogger_photo( $request ) {
		$id = absint( $request['id'] );
		if ( CP_Library::CPT !== get_post_type( $id ) ) {
			return new WP_Error( 'cp_not_found', __( 'Blogger not found.', 'hypeit' ), array( 'status' => 404 ) );
		}
		if ( rest_sanitize_boolean( $request->get_param( 'remove' ) ) ) {
			CP_Photo::remove( $id );
			return array( 'photo' => '', 'photo_s' => '' );
		}
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'cp_no_file', __( 'No image received.', 'hypeit' ), array( 'status' => 400 ) );
		}
		$res = CP_Photo::set_from_upload( $id, $files['file'] );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
		}
		return array( 'photo' => CP_Photo::url( $id, 'm' ), 'photo_s' => CP_Photo::url( $id, 's' ) );
	}

	/**
	 * Campaign history with each campaign's event status.
	 *
	 * @param int    $id     Blogger ID.
	 * @param string $handle Handle.
	 * @return array
	 */
	private static function history_with_events( $id, $handle ) {
		$hist   = array_slice( CP_Blogger_CPT::history( $handle ), 0, 50 );
		$stages = CP_Atrium::stages_for_blogger( $id );
		$labels = CP_Atrium::stage_labels();
		foreach ( $hist as $i => $h ) {
			$hist[ $i ]['event'] = isset( $stages[ $h['id'] ] ) ? $labels[ $stages[ $h['id'] ] ] : '';
			$hist[ $i ]['stage'] = isset( $stages[ $h['id'] ] ) ? $stages[ $h['id'] ] : '';
		}
		return $hist;
	}

	/**
	 * Current authenticated app user ID (for push subscriptions).
	 *
	 * @return int
	 */
	public static function user_id() {
		return (int) self::$current_user_id;
	}

	/**
	 * Block / unblock / delete a blogger.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function blogger_action( $request ) {
		$id = absint( $request['id'] );
		if ( CP_Library::CPT !== get_post_type( $id ) ) {
			return new WP_Error( 'cp_not_found', __( 'Blogger not found.', 'hypeit' ), array( 'status' => 404 ) );
		}

		$action = sanitize_key( (string) $request->get_param( 'op' ) );
		$handle = CP_Library::handle( $id );

		if ( 'ig_sync' === $action ) {
			return array( 'ok' => true, 'result' => CP_IGSync::sync_blogger( $id ), 'ig' => CP_IGSync::info( $id ) );
		}

		switch ( $action ) {
			case 'verify':
				CP_Verify::set_verified( $id, true );
				break;
			case 'unverify':
				CP_Verify::set_verified( $id, false );
				break;
			case 'block':
				update_post_meta( $id, '_cp_blocked', '1' );
				CP_Library::block_handle( $handle );
				break;
			case 'unblock':
				update_post_meta( $id, '_cp_blocked', '0' );
				CP_Library::unblock_handle( $handle );
				break;
			case 'delete':
				wp_delete_post( $id, true );
				break;
			case 'delete_block':
				CP_Library::block_handle( $handle );
				wp_delete_post( $id, true );
				break;
			default:
				return new WP_Error( 'cp_bad_op', __( 'Unknown action.', 'hypeit' ), array( 'status' => 400 ) );
		}

		return array( 'ok' => true );
	}

	/**
	 * Best-effort client IP for rate limiting.
	 *
	 * @return string
	 */
	private static function client_ip() {
		return sanitize_text_field( isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '' );
	}
}
