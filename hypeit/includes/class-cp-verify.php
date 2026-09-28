<?php
/**
 * Instagram account verification.
 *
 * Option A (Meta): the blogger connects a professional Instagram account via
 * Facebook Login; the plugin reads accurate followers_count and reach and marks
 * the profile Verified. Works for Business/Creator accounts once the Meta app
 * is approved (tester accounts work immediately).
 *
 * Option B (code): the blogger places a one-time code in their bio/story and an
 * admin approves it. Works for any account, no Meta approval needed.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Verify {

	const OPTION  = 'cp_verify';
	const QV      = 'cp_verify';
	const GRAPH   = 'https://graph.instagram.com';
	const DIALOG  = 'https://www.instagram.com/oauth/authorize';
	const TOKEN   = 'https://api.instagram.com/oauth/access_token';
	const SCOPES  = 'instagram_business_basic';
	const EVENT   = 'cp_verify_resync';
	const MAPCACHE = 'cp_verified_map';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_routes' ) );

		add_action( self::EVENT, array( __CLASS__, 'resync' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
			add_action( 'admin_post_cp_verify_approve', array( __CLASS__, 'admin_approve' ) );
			add_action( 'admin_post_cp_verify_unverify', array( __CLASS__, 'admin_unverify' ) );
			add_action( 'admin_post_cp_verify_resync_one', array( __CLASS__, 'admin_resync_one' ) );
		}
	}

	/**
	 * Settings + defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'meta_enabled' => 0,
			'app_id'       => '28583291651308942',
			'app_secret'   => '',
			'request_insights' => 0,
			'code_enabled' => 1,
			'require'      => 'optional', // optional | require_show (hide unverified from clients).
		);
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/** @return bool */
	public static function meta_ready() {
		// The Instagram Login method is retired — verification is automatic via
		// CP_IGSync (no login). Previously verified bloggers stay verified.
		return false;
		$s = self::get();
		return ! empty( $s['meta_enabled'] ) && ! empty( $s['app_id'] ) && ! empty( $s['app_secret'] );
	}

	/** @return string */
	public static function redirect_uri() {
		return home_url( '/campaigns/ig-callback/' );
	}

	/**
	 * Signed token tying a public verify link to a blogger.
	 *
	 * @param int $id Blogger ID.
	 * @return string
	 */
	public static function token( $id ) {
		return substr( hash_hmac( 'sha256', 'cp-verify|' . (int) $id, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Public URL that starts the Meta OAuth flow for a blogger.
	 *
	 * @param int $id Blogger ID.
	 * @return string
	 */
	public static function start_url( $id ) {
		return add_query_arg(
			array( 'b' => (int) $id, 't' => self::token( $id ) ),
			home_url( '/campaigns/ig-verify/' )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Routing                                                             */
	/* ------------------------------------------------------------------ */

	public static function add_rewrite_rules() {
		add_rewrite_rule( '^campaigns/ig-verify/?$', 'index.php?' . self::QV . '=start', 'top' );
		add_rewrite_rule( '^campaigns/ig-connect/?$', 'index.php?' . self::QV . '=connect', 'top' );
		add_rewrite_rule( '^campaigns/ig-callback/?$', 'index.php?' . self::QV . '=callback', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QV;
		return $vars;
	}

	public static function handle_routes() {
		$what = get_query_var( self::QV );
		if ( ! $what ) {
			return;
		}
		if ( 'start' === $what ) {
			self::start_oauth();
		} elseif ( 'connect' === $what ) {
			self::start_connect();
		} elseif ( 'callback' === $what ) {
			self::handle_callback();
		}
	}

	/** @return string Connect (pre-submission) URL. */
	public static function connect_url() {
		return home_url( '/campaigns/ig-connect/' );
	}

	/**
	 * Build the Facebook OAuth dialog URL for a given state.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function build_dialog_url( $state ) {
		$s     = self::get();
		$scope = self::SCOPES;
		if ( ! empty( $s['request_insights'] ) ) {
			$scope .= ',instagram_business_manage_insights';
		}
		return self::DIALOG . '?client_id=' . rawurlencode( $s['app_id'] )
			. '&redirect_uri=' . rawurlencode( self::redirect_uri() )
			. '&state=' . rawurlencode( $state )
			. '&response_type=code'
			. '&scope=' . rawurlencode( $scope );
	}

	/**
	 * Redirect to the onboarding page with a verification status flag.
	 *
	 * @param int    $id     Blogger ID.
	 * @param string $status Status flag.
	 */
	private static function bounce( $id, $status ) {
		$url = add_query_arg(
			array(
				'cp_ob'    => 'success',
				'b'        => (int) $id,
				't'        => self::token( $id ),
				'cp_vstat' => $status,
			),
			CP_Onboarding::url()
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Begin the Meta OAuth flow.
	 */
	private static function start_oauth() {
		$id = isset( $_GET['b'] ) ? absint( $_GET['b'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$t  = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $id || ! hash_equals( self::token( $id ), $t ) || CP_Library::CPT !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Invalid verification link.', 'hypeit' ) );
		}
		if ( ! self::meta_ready() ) {
			wp_die( esc_html__( 'Instagram verification is not configured yet.', 'hypeit' ) );
		}

		$s     = self::get();
		$state = wp_generate_password( 24, false, false );
		set_transient( 'cp_vs_' . $state, array( 'mode' => 'verify', 'id' => $id ), 15 * MINUTE_IN_SECONDS );

		wp_redirect( self::build_dialog_url( $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Begin the Meta OAuth flow from the onboarding form (no blogger yet).
	 */
	private static function start_connect() {
		if ( ! self::meta_ready() ) {
			wp_die( esc_html__( 'Instagram verification is not configured yet.', 'hypeit' ) );
		}
		$state = wp_generate_password( 24, false, false );
		set_transient( 'cp_vs_' . $state, array( 'mode' => 'connect' ), 15 * MINUTE_IN_SECONDS );
		wp_redirect( self::build_dialog_url( $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Exchange an OAuth code and fetch the professional account's data.
	 *
	 * @param string $code OAuth code.
	 * @return array|null { ig_id, page_token, username, followers, reach } or null.
	 */
	private static function exchange_and_fetch( $code ) {
		$s = self::get();

		// 1. code -> short-lived token (Instagram Login uses a POST form exchange).
		$resp = wp_remote_post(
			self::TOKEN,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $s['app_id'],
					'client_secret' => $s['app_secret'],
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::redirect_uri(),
					'code'          => $code,
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return null;
		}
		$tok     = json_decode( wp_remote_retrieve_body( $resp ), true );
		$short   = is_array( $tok ) && ! empty( $tok['access_token'] ) ? $tok['access_token'] : '';
		$user_id = is_array( $tok ) && ! empty( $tok['user_id'] ) ? $tok['user_id'] : '';
		if ( '' === $short || '' === $user_id ) {
			return null;
		}

		// 2. short -> long-lived token (60 days).
		$long  = self::graph_get(
			'/access_token',
			array(
				'grant_type'    => 'ig_exchange_token',
				'client_secret' => $s['app_secret'],
				'access_token'  => $short,
			)
		);
		$token = ! empty( $long['access_token'] ) ? $long['access_token'] : $short;

		// 3. profile (direct on the Instagram account — no Facebook Page needed).
		$profile = self::graph_get(
			'/me',
			array(
				'fields'       => 'user_id,username,account_type,followers_count,media_count',
				'access_token' => $token,
			)
		);
		if ( empty( $profile['username'] ) ) {
			return null;
		}
		// Personal accounts cannot expose followers/insights.
		if ( isset( $profile['account_type'] ) && 'PERSONAL' === strtoupper( $profile['account_type'] ) ) {
			return null;
		}

		return array(
			'ig_id'      => $user_id,
			'page_token' => $token,
			'username'   => strtolower( $profile['username'] ),
			'followers'  => isset( $profile['followers_count'] ) ? (int) $profile['followers_count'] : 0,
			'reach'      => self::fetch_reach( $user_id, $token ),
		);
	}

	/**
	 * Read a pre-submission connection payload.
	 *
	 * @param string $token Connection token.
	 * @return array|null
	 */
	public static function get_connection( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return null;
		}
		$data = get_transient( 'cp_ig_conn_' . $token );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Consume (delete) a connection payload.
	 *
	 * @param string $token Connection token.
	 */
	public static function consume_connection( $token ) {
		delete_transient( 'cp_ig_conn_' . sanitize_text_field( $token ) );
	}

	/**
	 * Handle the OAuth callback: exchange code, read followers + reach, verify.
	 */
	private static function handle_callback() {
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( CP_Onboarding::url() );
			exit;
		}
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$payload = $state ? get_transient( 'cp_vs_' . $state ) : false;
		if ( ! $payload ) {
			wp_die( esc_html__( 'This verification link has expired. Please try again.', 'hypeit' ) );
		}
		delete_transient( 'cp_vs_' . $state );

		$mode = is_array( $payload ) && isset( $payload['mode'] ) ? $payload['mode'] : 'verify';
		$id   = is_array( $payload ) && isset( $payload['id'] ) ? absint( $payload['id'] ) : 0;

		if ( ! self::meta_ready() || '' === $code ) {
			if ( 'connect' === $mode ) {
				wp_safe_redirect( add_query_arg( 'cp_connect', 'error', CP_Onboarding::url() ) );
				exit;
			}
			self::bounce( $id, 'error' );
		}

		$data = self::exchange_and_fetch( $code );

		if ( null === $data ) {
			if ( 'connect' === $mode ) {
				wp_safe_redirect( add_query_arg( 'cp_connect', 'notpro', CP_Onboarding::url() ) );
				exit;
			}
			self::bounce( $id, 'notpro' );
		}

		if ( 'connect' === $mode ) {
			$conntoken = wp_generate_password( 24, false, false );
			set_transient(
				'cp_ig_conn_' . $conntoken,
				array(
					'username'  => $data['username'],
					'followers' => (int) $data['followers'],
					'reach'     => (int) $data['reach'],
					'ig_id'     => $data['ig_id'],
					'token'     => $data['page_token'],
				),
				20 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( add_query_arg( 'cp_ig_connected', $conntoken, CP_Onboarding::url() ) );
			exit;
		}

		// Verify mode: attach to an existing blogger.
		$expected = strtolower( CP_Library::handle( $id ) );
		if ( '' === $data['username'] || ( $expected && $data['username'] !== $expected ) ) {
			self::bounce( $id, 'mismatch' );
		}
		self::store_meta_verified( $id, $data['ig_id'], $data['page_token'], $data['followers'], $data['reach'] );
		self::bounce( $id, 'success' );
	}

	/**
	 * Fetch 28-day reach.
	 *
	 * @param string $ig_id      IG business account ID.
	 * @param string $page_token Page token.
	 * @return int
	 */
	private static function fetch_reach( $ig_id, $page_token ) {
		$res = self::graph_get(
			'/' . $ig_id . '/insights',
			array(
				'metric'       => 'reach',
				'period'       => 'days_28',
				'metric_type'  => 'total_value',
				'access_token' => $page_token,
			)
		);
		if ( isset( $res['data'][0]['total_value']['value'] ) ) {
			return (int) $res['data'][0]['total_value']['value'];
		}
		if ( ! empty( $res['data'][0]['values'] ) ) {
			$vals = $res['data'][0]['values'];
			$last = end( $vals );
			return isset( $last['value'] ) ? (int) $last['value'] : 0;
		}
		return 0;
	}

	/**
	 * Store Meta-verified data on a blogger.
	 *
	 * @param int    $id         Blogger ID.
	 * @param string $ig_id      IG account ID.
	 * @param string $page_token Page token.
	 * @param int    $followers  Followers.
	 * @param int    $reach      Reach.
	 */
	private static function store_meta_verified( $id, $ig_id, $page_token, $followers, $reach ) {
		update_post_meta( $id, '_cp_verified', '1' );
		update_post_meta( $id, '_cp_verified_method', 'meta' );
		update_post_meta( $id, '_cp_verified_at', time() );
		update_post_meta( $id, '_cp_ig_user_id', sanitize_text_field( $ig_id ) );
		update_post_meta( $id, '_cp_meta_token', $page_token );
		if ( $followers > 0 ) {
			update_post_meta( $id, '_cp_followers', $followers );
		}
		update_post_meta( $id, '_cp_reach', (int) $reach );
		self::bust();
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}
	}

	/**
	 * Apply a pre-submission connection to a newly created blogger.
	 *
	 * @param int   $id   Blogger ID.
	 * @param array $conn Connection payload.
	 */
	public static function apply_connection( $id, $conn ) {
		update_post_meta( $id, '_cp_verified', '1' );
		update_post_meta( $id, '_cp_verified_method', 'meta' );
		update_post_meta( $id, '_cp_verified_at', time() );
		update_post_meta( $id, '_cp_ig_user_id', sanitize_text_field( isset( $conn['ig_id'] ) ? $conn['ig_id'] : '' ) );
		update_post_meta( $id, '_cp_meta_token', isset( $conn['token'] ) ? $conn['token'] : '' );
		if ( ! empty( $conn['followers'] ) ) {
			update_post_meta( $id, '_cp_followers', (int) $conn['followers'] );
		}
		update_post_meta( $id, '_cp_reach', (int) ( isset( $conn['reach'] ) ? $conn['reach'] : 0 ) );
		self::bust();
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}
	}

	/**
	 * Graph GET helper.
	 *
	 * @param string $path Path.
	 * @param array  $args Query args.
	 * @return array
	 */
	private static function graph_get( $path, $args ) {
		$url  = self::GRAPH . $path . '?' . http_build_query( $args );
		$resp = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $resp ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $body ) ? $body : array();
	}

	/* ------------------------------------------------------------------ */
	/* Re-sync (cron)                                                      */
	/* ------------------------------------------------------------------ */

	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::EVENT );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
	}

	/**
	 * Refresh followers + reach for all Meta-verified bloggers.
	 */
	public static function resync() {
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array( 'key' => '_cp_verified_method', 'value' => 'meta' ),
					array( 'key' => '_cp_ig_user_id', 'compare' => 'EXISTS' ),
				),
			)
		);
		foreach ( (array) $ids as $id ) {
			self::resync_one( $id );
		}
		self::bust();
	}

	/**
	 * Refresh a single Meta-verified blogger.
	 *
	 * @param int $id Blogger ID.
	 * @return bool
	 */
	public static function resync_one( $id ) {
		$ig_id = get_post_meta( $id, '_cp_ig_user_id', true );
		$token = get_post_meta( $id, '_cp_meta_token', true );
		if ( ! $ig_id || ! $token ) {
			return false;
		}
		$profile = self::graph_get(
			'/' . $ig_id,
			array( 'fields' => 'username,followers_count', 'access_token' => $token )
		);
		if ( isset( $profile['followers_count'] ) ) {
			update_post_meta( $id, '_cp_followers', (int) $profile['followers_count'] );
		}
		update_post_meta( $id, '_cp_reach', self::fetch_reach( $ig_id, $token ) );
		update_post_meta( $id, '_cp_verified_at', time() );
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Code / manual verification                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Get or create a blogger's verification code.
	 *
	 * @param int $id Blogger ID.
	 * @return string
	 */
	public static function code( $id ) {
		$c = get_post_meta( $id, '_cp_verify_code', true );
		if ( ! $c ) {
			$c = 'iLike-' . strtoupper( wp_generate_password( 5, false, false ) );
			update_post_meta( $id, '_cp_verify_code', $c );
		}
		return $c;
	}

	/* ------------------------------------------------------------------ */
	/* Status helpers + maps                                               */
	/* ------------------------------------------------------------------ */

	public static function is_verified( $id ) {
		return '1' === (string) get_post_meta( $id, '_cp_verified', true );
	}

	/**
	 * Map of lowercase handle => { verified, reach } for all library bloggers.
	 * Cached for an hour.
	 *
	 * @return array
	 */
	public static function verified_map() {
		$cached = get_transient( self::MAPCACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => '_cp_verified', 'value' => '1' ),
				),
			)
		);
		foreach ( (array) $ids as $id ) {
			$h = strtolower( CP_Library::handle( $id ) );
			if ( '' !== $h ) {
				$map[ $h ] = array(
					'verified' => true,
					'reach'    => (int) get_post_meta( $id, '_cp_reach', true ),
					'method'   => (string) get_post_meta( $id, '_cp_verified_method', true ),
				);
			}
		}
		set_transient( self::MAPCACHE, $map, HOUR_IN_SECONDS );
		return $map;
	}

	public static function bust() {
		delete_transient( self::MAPCACHE );
	}

	/* ------------------------------------------------------------------ */
	/* Admin: metabox + actions                                            */
	/* ------------------------------------------------------------------ */

	public static function meta_box() {
		add_meta_box( 'cp_verify_box', __( 'Verification', 'hypeit' ), array( __CLASS__, 'render_box' ), CP_Library::CPT, 'side', 'high' );
	}

	public static function render_sync( $post ) {
		if ( ! CP_IGSync::ready() ) {
			return;
		}
		$i = CP_IGSync::info( $post->ID );
		echo '<div style="border:1px solid #e2e4e7;border-radius:10px;padding:12px 14px;margin:0 0 16px;background:#fafafa;">';
		echo '<p style="margin:0 0 6px;font-weight:600;">' . esc_html__( 'Automatic Instagram sync', 'hypeit' ) . '</p>';
		if ( 'ok' === $i['status'] ) {
			$bits = array( sprintf( /* translators: %s: followers. */ __( '%s followers', 'hypeit' ), number_format_i18n( (int) get_post_meta( $post->ID, '_cp_followers', true ) ) ) );
			if ( $i['posts'] ) {
				/* translators: %s: posts. */
				$bits[] = sprintf( __( '%s posts', 'hypeit' ), number_format_i18n( $i['posts'] ) );
			}
			if ( null !== $i['engagement'] ) {
				/* translators: %s: rate. */
				$bits[] = sprintf( __( '%s%% engagement', 'hypeit' ), number_format_i18n( $i['engagement'], 2 ) );
			}
			echo '<p style="margin:0;color:#1b7f4b;">● ' . esc_html( implode( ' · ', $bits ) ) . '</p>';
		} elseif ( 'personal' === $i['status'] ) {
			echo '<p style="margin:0;color:#b26a00;">' . esc_html__( 'Personal account (or wrong username) — numbers can’t be read. Ask the blogger to switch to a Creator account: Settings → Account type and tools → Switch to professional account.', 'hypeit' ) . '</p>';
			$wa = CP_IGSync::switch_wa( $post->ID );
			if ( $wa ) {
				echo '<p style="margin:8px 0 0;"><a class="button" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ask to switch on WhatsApp', 'hypeit' ) . '</a></p>';
			}
		} else {
			echo '<p style="margin:0;" class="description">' . esc_html__( 'Not synced yet — it will be picked up within the hour.', 'hypeit' ) . '</p>';
		}
		if ( $i['synced'] ) {
			/* translators: %s: time ago. */
			echo '<p class="description" style="margin:4px 0 0;">' . esc_html( sprintf( __( 'Last checked %s ago', 'hypeit' ), $i['ago'] ) ) . '</p>';
		}
		echo '<p style="margin:10px 0 0;"><a class="button" href="' . esc_url( CP_IGSync::sync_one_url( $post->ID ) ) . '">' . esc_html__( 'Sync now', 'hypeit' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Verification box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_box( $post ) {
		$verified = self::is_verified( $post->ID );
		$method   = get_post_meta( $post->ID, '_cp_verified_method', true );
		$reach    = (int) get_post_meta( $post->ID, '_cp_reach', true );
		$at       = (int) get_post_meta( $post->ID, '_cp_verified_at', true );
		$handle   = CP_Library::handle( $post->ID );

		self::render_sync( $post );

		$methods = array(
			'bio'    => __( 'bio code, detected automatically', 'hypeit' ),
			'meta'   => __( 'Instagram login', 'hypeit' ),
			'manual' => __( 'approved manually', 'hypeit' ),
		);
		if ( $verified ) {
			echo '<p><span style="color:#1b7f4b;font-weight:700;">✓ ' . esc_html__( 'Verified', 'hypeit' ) . '</span> (' . esc_html( $methods[ $method ] ?? ( $method ? $method : $methods['manual'] ) ) . ')</p>';
			if ( $at ) {
				echo '<p class="description">' . esc_html( date_i18n( get_option( 'date_format' ), $at ) ) . '</p>';
			}
			if ( 'meta' === $method ) {
				echo '<p>' . esc_html__( 'Reach (28d):', 'hypeit' ) . ' <strong>' . esc_html( number_format_i18n( $reach ) ) . '</strong></p>';
				echo '<p><a class="button" href="' . esc_url( self::action_url( 'cp_verify_resync_one', $post->ID ) ) . '">' . esc_html__( 'Re-sync now', 'hypeit' ) . '</a></p>';
			}
			echo '<p><a class="button" href="' . esc_url( self::action_url( 'cp_verify_unverify', $post->ID ) ) . '">' . esc_html__( 'Remove verification', 'hypeit' ) . '</a></p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Not verified.', 'hypeit' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( self::action_url( 'cp_verify_approve', $post->ID ) ) . '">' . esc_html__( 'Approve manually', 'hypeit' ) . '</a></p>';
			if ( $handle ) {
				echo '<p style="margin-top:8px;font-weight:600;">' . esc_html__( 'Code method', 'hypeit' ) . '</p>';
				$auto = CP_IGSync::ready() && ! empty( CP_IGSync::get()['bio_verify'] );
				echo '<p class="description">' . esc_html( $auto ? __( 'Ask the blogger to add this code to their Instagram bio — it’s detected automatically (within a day), no approval needed:', 'hypeit' ) : __( 'Ask the blogger to add this code to their bio/story, then approve:', 'hypeit' ) ) . '</p>';
				echo '<p><code>' . esc_html( self::code( $post->ID ) ) . '</code></p>';
				if ( self::meta_ready() ) {
					echo '<p style="margin-top:8px;font-weight:600;">' . esc_html__( 'Instagram (auto)', 'hypeit' ) . '</p>';
					echo '<p class="description">' . esc_html__( 'Send this link to the blogger to verify via Instagram:', 'hypeit' ) . '</p>';
					echo '<p><input type="text" class="widefat" readonly value="' . esc_url( self::start_url( $post->ID ) ) . '" onclick="this.select()" /></p>';
				}
			}
		}
	}

	private static function action_url( $action, $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&post=' . $id ), $action . '_' . $id );
	}

	private static function back() {
		$ref = wp_get_referer();
		wp_safe_redirect( $ref ? $ref : admin_url( 'edit.php?post_type=' . CP_Library::CPT ) );
		exit;
	}

	/**
	 * Verify or un-verify a blogger (used by the admin buttons and the app).
	 *
	 * @param int  $id       Blogger ID.
	 * @param bool $verified Verified.
	 */
	public static function set_verified( $id, $verified ) {
		if ( $verified ) {
			update_post_meta( $id, '_cp_verified', '1' );
			update_post_meta( $id, '_cp_verified_method', 'manual' );
			update_post_meta( $id, '_cp_verified_at', time() );
		} else {
			update_post_meta( $id, '_cp_verified', '0' );
			delete_post_meta( $id, '_cp_verified_method' );
			delete_post_meta( $id, '_cp_reach' );
		}
		self::bust();
		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
	}

	public static function admin_approve() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_verify_approve_' . $id );
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			self::set_verified( $id, true );
		}
		self::back();
	}

	public static function admin_unverify() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_verify_unverify_' . $id );
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			self::set_verified( $id, false );
		}
		self::back();
	}

	public static function admin_resync_one() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_verify_resync_one_' . $id );
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			self::resync_one( $id );
			self::bust();
		}
		self::back();
	}

	/* ------------------------------------------------------------------ */
	/* Settings page                                                       */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Verification', 'hypeit' ),
			__( 'Verification', 'hypeit' ),
			'manage_options',
			'cp-verify',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function register_settings() {
		register_setting( 'cp_verify_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$in = is_array( $in ) ? $in : array();
		return array(
			'meta_enabled' => empty( $in['meta_enabled'] ) ? 0 : 1,
			'app_id'       => sanitize_text_field( isset( $in['app_id'] ) ? $in['app_id'] : '' ),
			'app_secret'   => sanitize_text_field( isset( $in['app_secret'] ) ? $in['app_secret'] : '' ),
			'request_insights' => empty( $in['request_insights'] ) ? 0 : 1,
			'code_enabled' => empty( $in['code_enabled'] ) ? 0 : 1,
			'require'      => ( isset( $in['require'] ) && 'require_show' === $in['require'] ) ? 'require_show' : 'optional',
		);
	}

	public static function render_settings() {
		$v = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Account Verification', 'hypeit' ); ?></h1>
			<?php CP_IGSync::render_card(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'cp_verify_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Verification options', 'hypeit' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'hypeit' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[code_enabled]" value="1" <?php checked( $v['code_enabled'], 1 ); ?> /> <?php esc_html_e( 'Offer code verification on the thank-you page', 'hypeit' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Client visibility', 'hypeit' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[require]" value="optional" <?php checked( $v['require'], 'optional' ); ?> /> <?php esc_html_e( 'Show all bloggers (verified or not)', 'hypeit' ); ?></label><br />
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[require]" value="require_show" <?php checked( $v['require'], 'require_show' ); ?> /> <?php esc_html_e( 'Only show verified bloggers to clients', 'hypeit' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
