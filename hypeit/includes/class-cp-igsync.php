<?php
/**
 * Instagram sync WITHOUT blogger login — Instagram Graph API "Business Discovery".
 *
 * The agency's own Instagram professional account (linked to a Facebook Page)
 * looks up any other Business/Creator account by username and reads its public
 * numbers: followers, following, posts, bio, recent likes/comments.
 *
 * - Onboarding form: live lookup auto-fills the real follower count.
 * - Background refresh: every blogger's numbers are kept current (rate-limit safe).
 * - Verification: the blogger puts their code in their bio → detected automatically.
 * - Engagement rate from the last posts.
 *
 * Personal (non-professional) accounts can't be looked up — they keep the
 * self-reported number and are marked as such.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_IGSync {

	const OPTION = 'cp_igsync';
	const GRAPH  = 'https://graph.facebook.com/v21.0';
	const CRON   = 'cp_igsync_batch';
	const BATCH  = 60;

	/** @var array Blogger IDs to sync after the response is sent. */
	private static $queue = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::CRON, array( __CLASS__, 'run_batch' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		// Server-cron link + backup trigger (page caching can starve WP-Cron).
		add_action( 'init', array( __CLASS__, 'cron_link' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'backup_trigger' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'backup_trigger' ) );
		add_action( 'wp_ajax_cp_ig_lookup', array( __CLASS__, 'ajax_lookup' ) );
		add_action( 'wp_ajax_nopriv_cp_ig_lookup', array( __CLASS__, 'ajax_lookup' ) );
		add_action( 'wp_ajax_cp_ig_verify_now', array( __CLASS__, 'ajax_verify_now' ) );
		add_action( 'wp_ajax_nopriv_cp_ig_verify_now', array( __CLASS__, 'ajax_verify_now' ) );
		// A changed username gets re-checked on the next run.
		add_action( 'updated_post_meta', array( __CLASS__, 'on_handle_change' ), 10, 3 );

		if ( is_admin() ) {
			add_action( 'admin_post_cp_igsync_save', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_cp_igsync_now', array( __CLASS__, 'handle_run_now' ) );
			add_action( 'admin_post_cp_igsync_one', array( __CLASS__, 'handle_one' ) );
			add_action( 'admin_post_cp_igsync_test', array( __CLASS__, 'handle_test' ) );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Settings.
	 *
	 * @return array
	 */
	public static function get() {
		$d = array(
			'token'      => '',
			'app_id'     => '',
			'app_secret' => '',
			'expires'    => 0,
			'ig_id'      => '',
			'ig_user'    => '',
			'interval'   => 7,
			'bio_verify' => 1,
			'switch_msg' => '',
			'cron_key'   => '',
			'status'     => '',
			'error'      => '',
			'paused'     => 0,
			'last_run'   => 0,
			'last_count' => 0,
		);
		$s = get_option( self::OPTION, array() );
		return array_merge( $d, is_array( $s ) ? $s : array() );
	}

	/**
	 * Merge + save settings.
	 *
	 * @param array $changes Changes.
	 */
	private static function put( $changes ) {
		update_option( self::OPTION, array_merge( self::get(), $changes ), false );
	}

	/**
	 * Connected and usable?
	 *
	 * @return bool
	 */
	public static function ready() {
		$s = self::get();
		return '' !== $s['token'] && '' !== $s['ig_id'] && ! in_array( $s['status'], array( 'token', 'perm' ), true );
	}

	/* ------------------------------------------------------------------ */
	/* Graph API                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * GET a Graph path.
	 *
	 * @param string $path  Path.
	 * @param array  $args  Query args.
	 * @param string $token Token (defaults to saved).
	 * @return array|WP_Error
	 */
	private static function graph( $path, $args, $token = '' ) {
		$args['access_token'] = $token ? $token : self::get()['token'];
		$res                  = wp_remote_get( self::GRAPH . $path . '?' . http_build_query( $args ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'network', $res->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! empty( $body['error'] ) ) {
			$e    = $body['error'];
			$code = (int) ( $e['code'] ?? 0 );
			$sub  = (int) ( $e['error_subcode'] ?? 0 );
			$kind = 'error';
			if ( 190 === $code || 102 === $code ) {
				$kind = 'token';
			} elseif ( in_array( $code, array( 4, 17, 32, 613, 80002 ), true ) ) {
				$kind = 'rate';
			} elseif ( in_array( $code, array( 3, 10, 200, 299 ), true ) || ( $code >= 200 && $code < 300 ) ) {
				$kind = 'perm';
			} elseif ( 110 === $code || 100 === $code || 2207013 === $sub ) {
				$kind = 'not_found';
			}
			return new WP_Error( $kind, isset( $e['message'] ) ? (string) $e['message'] : 'Graph error', array( 'code' => $code, 'sub' => $sub ) );
		}
		return is_array( $body ) ? $body : new WP_Error( 'error', 'Empty response' );
	}

	/**
	 * Instagram accounts reachable with a token (for "Test & detect").
	 *
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	public static function detect_accounts( $token ) {
		$r = self::graph( '/me/accounts', array( 'fields' => 'name,access_token,instagram_business_account{id,username,followers_count}', 'limit' => 100 ), $token );
		if ( is_wp_error( $r ) ) {
			// A Page token has no "accounts" — the token IS the Page: read it directly.
			$me = self::graph( '/me', array( 'fields' => 'name,instagram_business_account{id,username,followers_count}' ), $token );
			if ( is_wp_error( $me ) || empty( $me['instagram_business_account']['id'] ) ) {
				return $r;
			}
			$ig = $me['instagram_business_account'];
			return array(
				array(
					'id'        => (string) $ig['id'],
					'username'  => (string) ( $ig['username'] ?? '' ),
					'followers' => (int) ( $ig['followers_count'] ?? 0 ),
					'page'      => (string) ( $me['name'] ?? '' ),
					'ptoken'    => '',
				),
			);
		}
		$out = array();
		foreach ( (array) ( $r['data'] ?? array() ) as $page ) {
			if ( ! empty( $page['instagram_business_account']['id'] ) ) {
				$ig    = $page['instagram_business_account'];
				$out[] = array(
					'id'        => (string) $ig['id'],
					'username'  => (string) ( $ig['username'] ?? '' ),
					'followers' => (int) ( $ig['followers_count'] ?? 0 ),
					'page'      => (string) ( $page['name'] ?? '' ),
					'ptoken'    => (string) ( $page['access_token'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Real Business Discovery call against our own account (connection test).
	 *
	 * @param string $token    Token.
	 * @param string $ig_id    IG user ID.
	 * @param string $username Username to look up.
	 * @return true|WP_Error
	 */
	private static function probe( $token, $ig_id, $username ) {
		$r = self::graph( '/' . rawurlencode( $ig_id ), array( 'fields' => 'business_discovery.username(' . strtolower( $username ) . '){followers_count}' ), $token );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( $r->get_error_code(), self::explain( $r ) );
		}
		return true;
	}

	/**
	 * Friendly explanation of a Graph error (keeps Meta's own text).
	 *
	 * @param WP_Error $e Error.
	 * @return string
	 */
	private static function explain( $e ) {
		$m = $e->get_error_message();
		if ( 'perm' === $e->get_error_code() ) {
			return sprintf(
				/* translators: %s: Meta's message. */
				__( 'The token is missing a permission Instagram requires for looking up accounts. Generate a new token with: instagram_basic, instagram_manage_insights, pages_show_list, pages_read_engagement, business_management and ads_read. (Meta says: %s)', 'hypeit' ),
				$m
			);
		}
		if ( 'token' === $e->get_error_code() ) {
			/* translators: %s: Meta's message. */
			return sprintf( __( 'The token is no longer valid — generate a new one. (Meta says: %s)', 'hypeit' ), $m );
		}
		return $m;
	}

	/**
	 * Exchange a short-lived user token for a long-lived one.
	 *
	 * @param string $token  Short-lived token.
	 * @param string $app_id App ID.
	 * @param string $secret App secret.
	 * @return string|WP_Error
	 */
	private static function exchange_token( $token, $app_id, $secret ) {
		$res = wp_remote_get(
			self::GRAPH . '/oauth/access_token?' . http_build_query(
				array(
					'grant_type'        => 'fb_exchange_token',
					'client_id'         => $app_id,
					'client_secret'     => $secret,
					'fb_exchange_token' => $token,
				)
			),
			array( 'timeout' => 15 )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$b = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $b['access_token'] ) ) {
			return new WP_Error( 'exchange', isset( $b['error']['message'] ) ? (string) $b['error']['message'] : 'Exchange failed' );
		}
		return array(
			'token'   => (string) $b['access_token'],
			'expires' => ! empty( $b['expires_in'] ) ? time() + (int) $b['expires_in'] : 0,
		);
	}

	/**
	 * Ask Meta what a token really is and which permissions it holds.
	 *
	 * @param string $token  Token.
	 * @param string $app_id App ID.
	 * @param string $secret App secret.
	 * @return array|null {type, scopes[], expires}
	 */
	private static function debug_token( $token, $app_id, $secret ) {
		if ( '' === $app_id || '' === $secret ) {
			return null;
		}
		$r = self::graph( '/debug_token', array( 'input_token' => $token ), $app_id . '|' . $secret );
		if ( is_wp_error( $r ) || empty( $r['data'] ) ) {
			return null;
		}
		$d = $r['data'];
		return array(
			'type'    => (string) ( $d['type'] ?? '' ),
			'scopes'  => array_values( (array) ( $d['scopes'] ?? array() ) ),
			'expires' => (int) ( $d['expires_at'] ?? 0 ),
			'valid'   => ! empty( $d['is_valid'] ),
		);
	}

	/**
	 * Permissions Business Discovery needs.
	 *
	 * @return array
	 */
	private static function needed_scopes() {
		return array( 'instagram_basic', 'instagram_manage_insights', 'pages_show_list', 'pages_read_engagement', 'business_management', 'ads_read' );
	}

	/**
	 * Look up a professional account by username.
	 *
	 * @param string $username Username.
	 * @param bool   $fresh    Skip the 30-minute cache.
	 * @return array|WP_Error
	 */
	public static function lookup( $username, $fresh = false ) {
		$u = strtolower( preg_replace( '/[^A-Za-z0-9._]/', '', ltrim( (string) $username, '@' ) ) );
		if ( '' === $u ) {
			return new WP_Error( 'not_found', 'Empty username' );
		}
		if ( ! self::ready() ) {
			return new WP_Error( 'off', 'Not connected' );
		}
		$ck = 'cp_igl_' . md5( $u );
		if ( ! $fresh ) {
			$c = get_transient( $ck );
			if ( is_array( $c ) ) {
				return $c['ok'] ? $c['data'] : new WP_Error( $c['kind'], 'cached' );
			}
		}
		$s = self::get();
		if ( (int) $s['paused'] > time() ) {
			return new WP_Error( 'rate', 'Paused' );
		}

		$fields = 'business_discovery.username(' . $u . '){username,name,followers_count,follows_count,media_count,biography,media.limit(12){like_count,comments_count,timestamp}}';
		$r      = self::graph( '/' . rawurlencode( $s['ig_id'] ), array( 'fields' => $fields ) );

		if ( is_wp_error( $r ) ) {
			$kind = $r->get_error_code();
			if ( 'token' === $kind ) {
				self::put( array( 'status' => 'token', 'error' => $r->get_error_message() ) );
			} elseif ( 'perm' === $kind ) {
				self::put( array( 'status' => 'perm', 'error' => $r->get_error_message() ) );
			} elseif ( 'rate' === $kind ) {
				self::put( array( 'paused' => time() + HOUR_IN_SECONDS ) );
			}
			if ( 'not_found' === $kind ) {
				set_transient( $ck, array( 'ok' => false, 'kind' => 'not_found' ), 30 * MINUTE_IN_SECONDS );
			}
			return $r;
		}

		$bd = $r['business_discovery'] ?? null;
		if ( ! $bd ) {
			return new WP_Error( 'not_found', 'No data' );
		}
		$followers = (int) ( $bd['followers_count'] ?? 0 );
		$posts     = (array) ( $bd['media']['data'] ?? array() );
		$eng_sum   = 0;
		$eng_n     = 0;
		foreach ( $posts as $m ) {
			if ( isset( $m['like_count'] ) ) {
				$eng_sum += (int) $m['like_count'] + (int) ( $m['comments_count'] ?? 0 );
				$eng_n++;
			}
		}
		$data = array(
			'username'   => (string) ( $bd['username'] ?? $u ),
			'name'       => (string) ( $bd['name'] ?? '' ),
			'followers'  => $followers,
			'following'  => (int) ( $bd['follows_count'] ?? 0 ),
			'posts'      => (int) ( $bd['media_count'] ?? 0 ),
			'bio'        => (string) ( $bd['biography'] ?? '' ),
			'engagement' => ( $eng_n && $followers ) ? round( ( $eng_sum / $eng_n ) / $followers * 100, 2 ) : null,
		);
		set_transient( $ck, array( 'ok' => true, 'data' => $data ), 30 * MINUTE_IN_SECONDS );
		if ( '' !== $s['status'] ) {
			self::put( array( 'status' => '', 'error' => '' ) );
		}
		return $data;
	}

	/* ------------------------------------------------------------------ */
	/* Syncing bloggers                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Refresh one blogger. Returns 'ok' | 'personal' | 'rate' | 'token' | 'error' | 'off'.
	 *
	 * @param int  $id    Blogger ID.
	 * @param bool $fresh Bypass lookup cache.
	 * @return string
	 */
	public static function sync_blogger( $id, $fresh = true ) {
		$handle = CP_Library::handle( $id );
		if ( '' === $handle ) {
			return 'error';
		}
		$r = self::lookup( $handle, $fresh );
		if ( is_wp_error( $r ) ) {
			$kind = $r->get_error_code();
			if ( 'not_found' === $kind ) {
				update_post_meta( $id, '_cp_ig_status', 'personal' );
				update_post_meta( $id, '_cp_ig_synced', time() );
				return 'personal';
			}
			if ( in_array( $kind, array( 'rate', 'token', 'off', 'perm' ), true ) ) {
				return $kind;
			}
			update_post_meta( $id, '_cp_ig_synced', time() );
			return 'error';
		}

		// Update numbers without re-triggering our own "handle changed" hook.
		remove_action( 'updated_post_meta', array( __CLASS__, 'on_handle_change' ), 10 );
		update_post_meta( $id, '_cp_followers', (int) $r['followers'] );
		update_post_meta( $id, '_cp_ig_following', (int) $r['following'] );
		update_post_meta( $id, '_cp_ig_posts', (int) $r['posts'] );
		update_post_meta( $id, '_cp_ig_name', sanitize_text_field( $r['name'] ) );
		update_post_meta( $id, '_cp_engagement', null === $r['engagement'] ? '' : (string) $r['engagement'] );
		update_post_meta( $id, '_cp_ig_status', 'ok' );
		update_post_meta( $id, '_cp_ig_synced', time() );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_handle_change' ), 10, 3 );

		// Bio code → verified automatically (no login, no manual approval).
		$s = self::get();
		if ( ! empty( $s['bio_verify'] ) && ! CP_Verify::is_verified( $id ) ) {
			$code = (string) get_post_meta( $id, '_cp_verify_code', true );
			if ( '' !== $code && false !== stripos( $r['bio'], $code ) ) {
				update_post_meta( $id, '_cp_verified', '1' );
				update_post_meta( $id, '_cp_verified_method', 'bio' );
				update_post_meta( $id, '_cp_verified_at', time() );
			}
		}

		CP_Verify::bust();
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		if ( class_exists( 'CP_Join' ) ) {
			CP_Join::bust();
		}
		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}
		return 'ok';
	}

	/**
	 * Clear the sync stamp when a blogger's username changes.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	public static function on_handle_change( $meta_id, $post_id, $key ) {
		if ( '_cp_ig' === $key ) {
			delete_post_meta( $post_id, '_cp_ig_synced' );
			delete_post_meta( $post_id, '_cp_ig_status' );
		}
	}

	/**
	 * Queue a blogger for syncing after the page response is delivered.
	 *
	 * @param int $id Blogger ID.
	 */
	public static function queue( $id ) {
		if ( ! self::ready() ) {
			return;
		}
		CP_Verify::code( $id ); // Make sure a bio code exists.
		if ( empty( self::$queue ) ) {
			register_shutdown_function( array( __CLASS__, 'flush_queue' ) );
		}
		self::$queue[] = (int) $id;
	}

	/**
	 * Shutdown: release the visitor, then sync.
	 */
	public static function flush_queue() {
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		foreach ( array_unique( self::$queue ) as $id ) {
			self::sync_blogger( $id, false );
		}
		self::$queue = array();
	}

	/* ------------------------------------------------------------------ */
	/* Background refresh                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Hourly schedule.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function schedules( $s ) {
		if ( ! isset( $s['hourly'] ) ) {
			$s['hourly'] = array( 'interval' => HOUR_IN_SECONDS, 'display' => 'Hourly' );
		}
		return $s;
	}

	/**
	 * Schedule / unschedule with the connection state.
	 */
	public static function maybe_schedule() {
		$next = wp_next_scheduled( self::CRON );
		if ( self::ready() && ! $next ) {
			wp_schedule_event( time() + 120, 'hourly', self::CRON );
		} elseif ( ! self::ready() && $next ) {
			wp_unschedule_event( $next, self::CRON );
		}
	}

	/**
	 * Bloggers due for a refresh: never synced first, then oldest; pending bio
	 * codes are re-checked daily.
	 *
	 * @param int $limit Max.
	 * @return array IDs.
	 */
	public static function due( $limit ) {
		$s        = self::get();
		$interval = max( 1, (int) $s['interval'] ) * DAY_IN_SECONDS;
		$base     = array(
			'post_type'      => CP_Library::CPT,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'posts_per_page' => $limit,
		);
		$not_blocked = array(
			'relation' => 'OR',
			array( 'key' => '_cp_blocked', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_cp_blocked', 'value' => '1', 'compare' => '!=' ),
		);

		// 1) Never synced.
		$ids = get_posts(
			$base + array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					$not_blocked,
					array( 'key' => '_cp_ig_synced', 'compare' => 'NOT EXISTS' ),
				),
			)
		);
		// 2) Unverified with a bio code, not checked in the last day.
		if ( count( $ids ) < $limit && ! empty( $s['bio_verify'] ) ) {
			$ids = array_merge(
				$ids,
				get_posts(
					array_merge( $base, array( 'posts_per_page' => $limit - count( $ids ), 'post__not_in' => $ids ?: array( 0 ) ) ) + array(
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							$not_blocked,
							array( 'key' => '_cp_verify_code', 'compare' => 'EXISTS' ),
							array( 'key' => '_cp_ig_status', 'value' => 'ok' ),
							array(
								'relation' => 'OR',
								array( 'key' => '_cp_verified', 'compare' => 'NOT EXISTS' ),
								array( 'key' => '_cp_verified', 'value' => '1', 'compare' => '!=' ),
							),
							array( 'key' => '_cp_ig_synced', 'value' => time() - DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ),
						),
					)
				)
			);
		}
		// 3) Oldest refresh past the interval.
		if ( count( $ids ) < $limit ) {
			$ids = array_merge(
				$ids,
				get_posts(
					array_merge( $base, array( 'posts_per_page' => $limit - count( $ids ), 'post__not_in' => $ids ?: array( 0 ) ) ) + array(
						'meta_key'   => '_cp_ig_synced', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'orderby'    => 'meta_value_num',
						'order'      => 'ASC',
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							$not_blocked,
							array( 'key' => '_cp_ig_synced', 'value' => time() - $interval, 'compare' => '<', 'type' => 'NUMERIC' ),
						),
					)
				)
			);
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Hourly batch.
	 *
	 * @param int $limit Max per run.
	 * @return array Counts.
	 */
	public static function run_batch( $limit = 0 ) {
		$limit = $limit ? (int) $limit : self::BATCH;
		$out   = array( 'ok' => 0, 'personal' => 0, 'error' => 0, 'stopped' => '' );
		if ( ! self::ready() || (int) self::get()['paused'] > time() ) {
			return $out;
		}
		// One run at a time (cron, server link and backup trigger can overlap).
		if ( get_transient( 'cp_igsync_lock' ) ) {
			$out['stopped'] = 'busy';
			return $out;
		}
		set_transient( 'cp_igsync_lock', 1, 10 * MINUTE_IN_SECONDS );
		foreach ( self::due( $limit ) as $id ) {
			$r = self::sync_blogger( $id );
			if ( in_array( $r, array( 'rate', 'token', 'off', 'perm' ), true ) ) {
				$out['stopped'] = $r;
				break;
			}
			$out[ isset( $out[ $r ] ) ? $r : 'error' ]++;
		}
		self::put( array( 'last_run' => time(), 'last_count' => $out['ok'] + $out['personal'] ) );
		delete_transient( 'cp_igsync_lock' );
		return $out;
	}

	/**
	 * Secret key for the server-cron link.
	 *
	 * @return string
	 */
	public static function cron_key() {
		$s = self::get();
		if ( '' === (string) $s['cron_key'] ) {
			$s['cron_key'] = wp_generate_password( 24, false, false );
			self::put( array( 'cron_key' => $s['cron_key'] ) );
		}
		return (string) $s['cron_key'];
	}

	/**
	 * Server-cron link: https://site/?hypeit_cron=KEY runs one refresh batch.
	 */
	public static function cron_link() {
		if ( ! isset( $_GET['hypeit_cron'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$key = sanitize_text_field( wp_unslash( $_GET['hypeit_cron'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! self::ready() || ! hash_equals( self::cron_key(), $key ) ) {
			status_header( 403 );
			exit( 'no' );
		}
		nocache_headers();
		self::put( array( 'server_cron' => time() ) );
		$r = self::run_batch();
		exit( 'ok ' . (int) ( $r['ok'] + $r['personal'] ) );
	}

	/**
	 * Backup: when the admin or app is used and the refresh is overdue, run a
	 * small batch after the response has been sent (never slows the page).
	 */
	public static function backup_trigger() {
		static $done = false;
		if ( $done || ! self::ready() ) {
			return;
		}
		$done = true;
		$s    = self::get();
		if ( (int) $s['paused'] > time() || (int) $s['last_run'] > time() - 65 * MINUTE_IN_SECONDS || get_transient( 'cp_igsync_lock' ) || get_transient( 'cp_igsync_backup' ) ) {
			return;
		}
		set_transient( 'cp_igsync_backup', 1, 5 * MINUTE_IN_SECONDS );
		register_shutdown_function(
			static function () {
				if ( function_exists( 'litespeed_finish_request' ) ) {
					litespeed_finish_request();
				} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				ignore_user_abort( true );
				self::run_batch( 25 );
			}
		);
	}

	/* ------------------------------------------------------------------ */
	/* Onboarding live lookup                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Public AJAX: check a username while the blogger fills in the form.
	 */
	public static function ajax_lookup() {
		check_ajax_referer( 'cp_ig_lookup', 'nonce' );
		if ( ! self::ready() ) {
			wp_send_json_success( array( 'found' => false, 'reason' => 'off' ) );
		}
		// Gentle throttle per visitor.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'cp_iglt_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 15 ) {
			wp_send_json_success( array( 'found' => false, 'reason' => 'busy' ) );
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

		$u = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$r = self::lookup( $u );
		if ( is_wp_error( $r ) ) {
			wp_send_json_success( array( 'found' => false, 'reason' => 'not_found' === $r->get_error_code() ? 'personal' : 'busy' ) );
		}
		wp_send_json_success(
			array(
				'found'     => true,
				'username'  => $r['username'],
				'followers' => (int) $r['followers'],
			)
		);
	}

	/**
	 * Signature tying a "check now" button to one blogger (no login needed).
	 *
	 * @param int $bid Blogger ID.
	 * @return string
	 */
	public static function verify_sig( $bid ) {
		return substr( wp_hash( 'cp_verify_now|' . (int) $bid ), 0, 24 );
	}

	/**
	 * Public AJAX: the blogger added the code — check their bio right now.
	 */
	public static function ajax_verify_now() {
		$bid = isset( $_POST['bid'] ) ? absint( $_POST['bid'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sig = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $bid || ! hash_equals( self::verify_sig( $bid ), $sig ) || CP_Library::CPT !== get_post_type( $bid ) ) {
			wp_send_json_error( array( 'state' => 'busy' ), 403 );
		}
		if ( CP_Verify::is_verified( $bid ) ) {
			wp_send_json_success( array( 'state' => 'ok' ) );
		}
		// One live check per blogger every 30 seconds.
		$key = 'cp_ivn_' . $bid;
		if ( get_transient( $key ) ) {
			wp_send_json_success( array( 'state' => 'busy' ) );
		}
		set_transient( $key, 1, 30 );

		$r = self::sync_blogger( $bid, true );
		if ( CP_Verify::is_verified( $bid ) ) {
			wp_send_json_success( array( 'state' => 'ok' ) );
		}
		$map = array( 'ok' => 'missing', 'personal' => 'personal' );
		wp_send_json_success( array( 'state' => $map[ $r ] ?? 'busy' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Save / connect.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		check_admin_referer( 'cp_igsync_save', 'cp_igsync_nonce' );
		$p   = wp_unslash( $_POST );
		$s   = self::get();
		$msg = 'saved';

		if ( ! empty( $p['disconnect'] ) ) {
			self::put( array( 'token' => '', 'ig_id' => '', 'ig_user' => '', 'status' => '', 'error' => '' ) );
			self::maybe_schedule();
			wp_safe_redirect( add_query_arg( array( 'post_type' => CP_POST_TYPE, 'page' => 'cp-verify', 'cp_igs' => 'off' ), admin_url( 'edit.php' ) ) );
			exit;
		}

		$token   = isset( $p['token'] ) ? trim( sanitize_text_field( $p['token'] ) ) : '';
		$pasted  = '' !== $token;
		$app_id  = isset( $p['app_id'] ) ? preg_replace( '/\D/', '', (string) $p['app_id'] ) : $s['app_id'];
		$secret  = isset( $p['app_secret'] ) && '' !== trim( (string) $p['app_secret'] ) ? trim( sanitize_text_field( $p['app_secret'] ) ) : $s['app_secret'];
		if ( '' === $token ) {
			$token = $s['token'];
		}
		$changes = array(
			'interval'   => in_array( (int) ( $p['interval'] ?? 7 ), array( 1, 3, 7, 14, 30 ), true ) ? (int) $p['interval'] : 7,
			'bio_verify' => empty( $p['bio_verify'] ) ? 0 : 1,
			'switch_msg' => isset( $p['switch_msg'] ) ? sanitize_textarea_field( $p['switch_msg'] ) : $s['switch_msg'],
			'app_id'     => $app_id,
			'app_secret' => $secret,
		);

		// A token pasted from Graph API Explorer lasts ~1 hour: exchange it for a
		// long-lived one, then use the Page token (which never expires).
		$exchanged = false;
		$user_exp  = 0;
		if ( $pasted && '' !== $app_id && '' !== $secret ) {
			$long = self::exchange_token( $token, $app_id, $secret );
			if ( ! is_wp_error( $long ) ) {
				$token     = $long['token'];
				$user_exp  = $long['expires'];
				$exchanged = true;
			}
		}

		if ( ! $pasted && '' !== $token && '' !== $s['ig_id'] ) {
			// Settings-only save: keep the saved connection, just re-check lookups work.
			$probe = self::probe( $token, $s['ig_id'], $s['ig_user'] );
			if ( is_wp_error( $probe ) && 'rate' !== $probe->get_error_code() ) {
				$changes['status'] = 'token' === $probe->get_error_code() ? 'token' : 'perm';
				$changes['error']  = $probe->get_error_message();
				$msg               = 'perm';
			} else {
				$changes['status'] = '';
				$changes['error']  = '';
				$changes['paused'] = 0;
			}
		} elseif ( '' !== $token ) {
			$accounts = self::detect_accounts( $token );
			if ( is_wp_error( $accounts ) ) {
				$changes['error']  = $accounts->get_error_message();
				$changes['status'] = 'token';
				$msg               = 'bad';
			} elseif ( ! $accounts ) {
				$changes['error']  = __( 'No Instagram professional account is linked to the Facebook Pages this token can access.', 'hypeit' );
				$changes['status'] = 'token';
				$msg               = 'none';
			} else {
				$pick = isset( $p['ig_id'] ) ? sanitize_text_field( $p['ig_id'] ) : '';
				$acc  = $accounts[0];
				foreach ( $accounts as $a ) {
					if ( $a['id'] === $pick ) {
						$acc = $a;
					}
				}
				// Candidates, best first: the non-expiring Page token, then the user token.
				$candidates = array();
				if ( $exchanged && '' !== $acc['ptoken'] ) {
					$candidates[] = array( 'token' => $acc['ptoken'], 'expires' => 0 );
				}
				$candidates[] = array( 'token' => $token, 'expires' => $exchanged ? $user_exp : 0 );
				foreach ( $accounts as $i => $a ) {
					unset( $accounts[ $i ]['ptoken'] );
				}
				$changes += array(
					'token'    => $candidates[0]['token'],
					'expires'  => $candidates[0]['expires'],
					'ig_id'    => $acc['id'],
					'ig_user'  => $acc['username'],
					'status'   => '',
					'error'    => '',
					'paused'   => 0,
					'accounts' => $accounts,
				);
				$msg = 'connected';

				// Prove lookups really work before calling it connected — try each candidate.
				$probe = null;
				foreach ( $candidates as $c ) {
					$probe = self::probe( $c['token'], $acc['id'], $acc['username'] );
					if ( ! is_wp_error( $probe ) || 'rate' === $probe->get_error_code() ) {
						$changes['token']   = $c['token'];
						$changes['expires'] = $c['expires'];
						break;
					}
				}
				if ( is_wp_error( $probe ) && 'rate' !== $probe->get_error_code() ) {
					$changes['status'] = 'perm';
					$changes['error']  = $probe->get_error_message();
					$msg               = 'perm';
					// Ask Meta which permissions the token really has.
					$dbg = self::debug_token( $token, $app_id, $secret );
					if ( $dbg ) {
						$missing = array_values( array_diff( self::needed_scopes(), $dbg['scopes'] ) );
						$changes['error'] .= ' ' . sprintf(
							/* translators: 1: token type, 2: granted, 3: missing. */
							__( 'Token type: %1$s. Granted: %2$s. Missing: %3$s.', 'hypeit' ),
							$dbg['type'] ? $dbg['type'] : '?',
							$dbg['scopes'] ? implode( ', ', $dbg['scopes'] ) : '—',
							$missing ? implode( ', ', $missing ) : __( 'none — the permissions look right, so Meta is refusing for another reason. Please send a screenshot of this message.', 'hypeit' )
						);
					}
				}
			}
		}
		self::put( $changes );
		self::maybe_schedule();
		wp_safe_redirect( add_query_arg( array( 'post_type' => CP_POST_TYPE, 'page' => 'cp-verify', 'cp_igs' => $msg ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * "Sync now" (one batch).
	 */
	public static function handle_run_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		check_admin_referer( 'cp_igsync_now' );
		$r = self::run_batch( 40 );
		wp_safe_redirect( add_query_arg( array( 'post_type' => CP_POST_TYPE, 'page' => 'cp-verify', 'cp_igs' => 'ran', 'n' => $r['ok'] + $r['personal'], 'st' => $r['stopped'] ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Sync one blogger (from the blogger editor).
	 */
	public static function handle_one() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_igsync_one_' . $id );
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			$r = self::sync_blogger( $id );
			set_transient( 'cp_igs_one_' . get_current_user_id(), $r, 60 );
		}
		wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
		exit;
	}

	/**
	 * "Test a username": show exactly what Instagram returns.
	 */
	public static function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		check_admin_referer( 'cp_igsync_test', 'cp_igsync_test_nonce' );
		$u = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$s = self::get();
		if ( '' === $s['token'] || '' === $s['ig_id'] ) {
			$res = array( 'ok' => false, 'text' => __( 'Connect first.', 'hypeit' ) );
		} else {
			// Test even if a previous error paused things.
			self::put( array( 'status' => '', 'paused' => 0 ) );
			$r = self::lookup( $u, true );
			if ( is_wp_error( $r ) ) {
				$text = 'not_found' === $r->get_error_code()
					? __( 'Instagram says this account can’t be found as a Business/Creator account (check the spelling, or it’s a personal account).', 'hypeit' )
					: self::explain( $r );
				$res = array( 'ok' => false, 'text' => $text );
			} else {
				$res = array(
					'ok'   => true,
					/* translators: 1: username, 2: followers, 3: posts, 4: bio. */
					'text' => sprintf( __( '@%1$s — %2$s followers, %3$s posts. Bio: “%4$s”', 'hypeit' ), $r['username'], number_format_i18n( $r['followers'] ), number_format_i18n( $r['posts'] ), $r['bio'] ),
				);
			}
		}
		set_transient( 'cp_igs_test_' . get_current_user_id(), $res, 120 );
		wp_safe_redirect( add_query_arg( array( 'post_type' => CP_POST_TYPE, 'page' => 'cp-verify' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * URL for syncing one blogger.
	 *
	 * @param int $id Blogger ID.
	 * @return string
	 */
	public static function sync_one_url( $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cp_igsync_one&post=' . (int) $id ), 'cp_igsync_one_' . (int) $id );
	}

	/**
	 * Warn admins when the connection breaks; confirm single syncs.
	 */
	public static function admin_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ( CP_POST_TYPE !== $screen->post_type && CP_Library::CPT !== $screen->post_type ) ) {
			return;
		}
		$s = self::get();
		if ( in_array( $s['status'], array( 'token', 'perm' ), true ) && '' !== $s['ig_id'] ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Instagram sync has stopped:', 'hypeit' ) . '</strong> ' . esc_html( $s['error'] ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-verify' ) ) . '">' . esc_html__( 'Reconnect', 'hypeit' ) . '</a></p></div>';
		}
		if ( self::ready() && (int) $s['expires'] && (int) $s['expires'] < time() + 7 * DAY_IN_SECONDS ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( /* translators: %s: date. */ __( 'The Instagram sync token expires on %s. Paste a fresh token from Graph API Explorer in HypeIt → Verification to keep syncing.', 'hypeit' ), date_i18n( get_option( 'date_format' ), (int) $s['expires'] ) ) ) . '</p></div>';
		}
		$one = get_transient( 'cp_igs_one_' . get_current_user_id() );
		if ( $one ) {
			delete_transient( 'cp_igs_one_' . get_current_user_id() );
			$msgs = array(
				'ok'       => __( 'Instagram numbers updated.', 'hypeit' ),
				'personal' => __( 'This is a personal Instagram account (or the username is wrong), so its numbers can’t be read. Ask the blogger to switch to a Creator account.', 'hypeit' ),
				'rate'     => __( 'Instagram asked us to slow down — sync will resume automatically within the hour.', 'hypeit' ),
				'token'    => __( 'The Instagram connection needs to be renewed.', 'hypeit' ),
			);
			$cls = 'ok' === $one ? 'notice-success' : 'notice-warning';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $msgs[ $one ] ?? __( 'Sync failed. Please try again later.', 'hypeit' ) ) . '</p></div>';
		}
	}

	/**
	 * Connect card on the Verification page.
	 */
	public static function render_card() {
		$s   = self::get();
		$msg = isset( $_GET['cp_igs'] ) ? sanitize_key( wp_unslash( $_GET['cp_igs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$texts = array(
			'connected' => array( 'success', __( 'Connected. Bloggers will now be synced automatically.', 'hypeit' ) ),
			'saved'     => array( 'success', __( 'Saved.', 'hypeit' ) ),
			'off'       => array( 'success', __( 'Disconnected.', 'hypeit' ) ),
			'bad'       => array( 'error', __( 'That token didn’t work — see the error below.', 'hypeit' ) ),
			'perm'      => array( 'error', __( 'The token connects, but Instagram refused a test lookup — see the message below.', 'hypeit' ) ),
			'none'      => array( 'error', __( 'No Instagram professional account found for this token.', 'hypeit' ) ),
		);
		if ( 'ran' === $msg ) {
			$st = isset( $_GET['st'] ) ? sanitize_key( wp_unslash( $_GET['st'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %d: count. */
			$texts['ran'] = array( $st ? 'warning' : 'success', sprintf( __( 'Synced %d bloggers.', 'hypeit' ), isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0 ) . ( 'rate' === $st ? ' ' . __( 'Paused for an hour to respect Instagram’s limits.', 'hypeit' ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $texts[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $texts[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $texts[ $msg ][1] ) . '</p></div>';
		}

		global $wpdb;
		$counts = $wpdb->get_results( "SELECT meta_value AS st, COUNT(*) AS n FROM {$wpdb->postmeta} WHERE meta_key = '_cp_ig_status' GROUP BY meta_value", OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok       = isset( $counts['ok'] ) ? (int) $counts['ok']->n : 0;
		$personal = isset( $counts['personal'] ) ? (int) $counts['personal']->n : 0;
		$accounts = isset( $s['accounts'] ) && is_array( $s['accounts'] ) ? $s['accounts'] : array();
		?>
		<div class="cp-card" style="max-width:920px;border:1px solid #c3c4c7;border-radius:12px;padding:18px 20px;background:#fff;margin:14px 0 22px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Automatic Instagram sync — no login needed', 'hypeit' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Your agency’s Instagram account reads bloggers’ public numbers (followers, posts, engagement) and their bio. Bloggers never log in. Works for Business and Creator accounts.', 'hypeit' ); ?></p>

			<?php if ( self::ready() ) : ?>
				<p style="font-size:14px;margin:14px 0;">
					<span style="color:#1b7f4b;font-weight:700;">● <?php esc_html_e( 'Connected', 'hypeit' ); ?></span>
					<?php echo $s['ig_user'] ? ' ' . esc_html( sprintf( /* translators: %s: account. */ __( 'as @%s', 'hypeit' ), $s['ig_user'] ) ) : ''; ?>
					· <?php echo esc_html( sprintf( /* translators: %d: synced. */ __( '%d bloggers synced', 'hypeit' ), $ok ) ); ?>
					· <?php if ( $personal ) : ?><a href="<?php echo esc_url( self::personal_list_url() ); ?>"><?php echo esc_html( sprintf( /* translators: %d: count. */ _n( '%d personal account', '%d personal accounts', $personal, 'hypeit' ), $personal ) ); ?> →</a><?php else : ?><?php esc_html_e( '0 personal accounts', 'hypeit' ); ?><?php endif; ?>
					<?php if ( $s['last_run'] ) : ?> · <?php echo esc_html( sprintf( /* translators: %s: time ago. */ __( 'last run %s ago', 'hypeit' ), human_time_diff( (int) $s['last_run'] ) ) ); ?><?php endif; ?>
					<?php if ( (int) $s['expires'] ) : ?> · <?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'token renews by %s', 'hypeit' ), date_i18n( get_option( 'date_format' ), (int) $s['expires'] ) ) ); ?><?php endif; ?>
					<?php if ( (int) $s['paused'] > time() ) : ?> · <span style="color:#b26a00;"><?php esc_html_e( 'paused briefly (Instagram rate limit)', 'hypeit' ); ?></span><?php endif; ?>
				</p>
			<?php elseif ( in_array( $s['status'], array( 'token', 'perm' ), true ) && $s['error'] ) : ?>
				<p style="color:#b3261e;margin:14px 0;"><strong><?php esc_html_e( 'Not connected:', 'hypeit' ); ?></strong> <?php echo esc_html( $s['error'] ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cp_igsync_save" />
				<?php wp_nonce_field( 'cp_igsync_save', 'cp_igsync_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cp-igs-token"><?php esc_html_e( 'Access token', 'hypeit' ); ?></label></th>
						<td>
							<input type="password" id="cp-igs-token" name="token" class="large-text" autocomplete="off" placeholder="<?php echo esc_attr( $s['token'] ? __( 'Saved — paste a new token only to replace it', 'hypeit' ) : __( 'Paste the System User access token', 'hypeit' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Paste a token from Graph API Explorer (with the App ID and App Secret below, it’s made permanent automatically) — or a System User token, which never expires. Permissions needed: instagram_basic, instagram_manage_insights, pages_show_list, pages_read_engagement, business_management, ads_read.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cp-igs-app"><?php esc_html_e( 'App ID', 'hypeit' ); ?></label></th>
						<td>
							<input type="text" id="cp-igs-app" name="app_id" class="regular-text" value="<?php echo esc_attr( $s['app_id'] ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'From your sync app (e.g. iLike Sync) → App settings → Basic.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cp-igs-secret"><?php esc_html_e( 'App Secret', 'hypeit' ); ?></label></th>
						<td>
							<input type="password" id="cp-igs-secret" name="app_secret" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( $s['app_secret'] ? __( 'Saved', 'hypeit' ) : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Same page, click “Show”. Only used to make the token permanent; stored privately.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<?php if ( count( $accounts ) > 1 ) : ?>
						<tr>
							<th scope="row"><label for="cp-igs-acc"><?php esc_html_e( 'Instagram account', 'hypeit' ); ?></label></th>
							<td>
								<select id="cp-igs-acc" name="ig_id">
									<?php foreach ( $accounts as $a ) : ?>
										<option value="<?php echo esc_attr( $a['id'] ); ?>" <?php selected( $s['ig_id'], $a['id'] ); ?>>@<?php echo esc_html( $a['username'] . ' — ' . $a['page'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="cp-igs-int"><?php esc_html_e( 'Refresh followers', 'hypeit' ); ?></label></th>
						<td>
							<select id="cp-igs-int" name="interval">
								<?php foreach ( array( 1 => __( 'Every day', 'hypeit' ), 3 => __( 'Every 3 days', 'hypeit' ), 7 => __( 'Every week', 'hypeit' ), 14 => __( 'Every 2 weeks', 'hypeit' ), 30 => __( 'Every month', 'hypeit' ) ) as $d => $lab ) : ?>
									<option value="<?php echo (int) $d; ?>" <?php selected( (int) $s['interval'], $d ); ?>><?php echo esc_html( $lab ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'New bloggers are checked right away. Up to 60 bloggers are refreshed per hour to stay within Instagram’s limits.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verify by bio code', 'hypeit' ); ?></th>
						<td>
							<label><input type="checkbox" name="bio_verify" value="1" <?php checked( $s['bio_verify'] ); ?> /> <?php esc_html_e( 'Automatically verify bloggers who add their personal code to their Instagram bio', 'hypeit' ); ?></label>
							<p class="description"><?php esc_html_e( 'Each blogger sees their code after submitting the form. It’s detected within a day; they can remove it once verified.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cp-igs-msg"><?php esc_html_e( '“Switch to Creator” message', 'hypeit' ); ?></label></th>
						<td>
							<textarea id="cp-igs-msg" name="switch_msg" rows="6" class="large-text" placeholder="<?php echo esc_attr( self::default_switch_msg() ); ?>"><?php echo esc_textarea( $s['switch_msg'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Sent on WhatsApp from the “Ask to switch” buttons for personal accounts. Placeholders: {first} {handle}. Leave empty to use the default shown.', 'hypeit' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( self::ready() ? __( 'Save', 'hypeit' ) : __( 'Test & connect', 'hypeit' ), 'primary', 'submit', false ); ?>
					<?php if ( '' !== $s['token'] ) : ?>
						<button type="submit" name="disconnect" value="1" class="button" onclick="return confirm('<?php echo esc_js( __( 'Disconnect automatic Instagram sync?', 'hypeit' ) ); ?>');"><?php esc_html_e( 'Disconnect', 'hypeit' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
			<?php
			$test = get_transient( 'cp_igs_test_' . get_current_user_id() );
			if ( $test ) {
				delete_transient( 'cp_igs_test_' . get_current_user_id() );
				echo '<div class="notice notice-' . ( $test['ok'] ? 'success' : 'error' ) . ' inline" style="margin:12px 0;"><p>' . esc_html( $test['text'] ) . '</p></div>';
			}
			if ( '' !== $s['token'] ) :
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:14px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
					<input type="hidden" name="action" value="cp_igsync_test" />
					<?php wp_nonce_field( 'cp_igsync_test', 'cp_igsync_test_nonce' ); ?>
					<strong><?php esc_html_e( 'Test a username:', 'hypeit' ); ?></strong>
					<input type="text" name="username" placeholder="@username" required />
					<button type="submit" class="button"><?php esc_html_e( 'Test', 'hypeit' ); ?></button>
					<span class="description"><?php esc_html_e( 'Shows exactly what Instagram returns — handy for checking a blogger or your own account.', 'hypeit' ); ?></span>
				</form>
			<?php endif; ?>
			<?php if ( self::ready() ) : ?>
				<p style="margin:6px 0 0;"><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cp_igsync_now' ), 'cp_igsync_now' ) ); ?>"><?php esc_html_e( 'Sync bloggers now', 'hypeit' ); ?></a> <span class="description"><?php esc_html_e( 'Runs one batch immediately (otherwise it runs every hour on its own).', 'hypeit' ); ?></span></p>
			<?php endif; ?>
			<?php
			if ( self::ready() ) :
				$next    = wp_next_scheduled( self::CRON );
				$due     = count( self::due( 1000 ) );
				$stale   = ! $s['last_run'] || (int) $s['last_run'] < time() - 2 * HOUR_IN_SECONDS;
				$srv     = ! empty( $s['server_cron'] ) && (int) $s['server_cron'] > time() - 2 * HOUR_IN_SECONDS;
				$link    = add_query_arg( 'hypeit_cron', self::cron_key(), home_url( '/' ) );
				?>
				<div style="margin:16px 0 0;padding:14px 16px;border:1px solid #e2e4e7;border-radius:10px;background:#fafafa;">
					<p style="margin:0 0 6px;font-weight:600;"><?php esc_html_e( 'Automatic refresh status', 'hypeit' ); ?></p>
					<p style="margin:0;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: last run, 2: next run, 3: due count. */
								__( 'Last run: %1$s · Next scheduled: %2$s · Due for refresh now: %3$d bloggers', 'hypeit' ),
								$s['last_run'] ? sprintf( /* translators: %s: time. */ __( '%s ago', 'hypeit' ), human_time_diff( (int) $s['last_run'] ) ) : __( 'never', 'hypeit' ),
								$next ? ( $next > time() ? sprintf( /* translators: %s: time. */ __( 'in %s', 'hypeit' ), human_time_diff( $next ) ) : __( 'overdue', 'hypeit' ) ) : __( 'not scheduled', 'hypeit' ),
								$due
							)
						);
						?>
					</p>
					<p style="margin:6px 0 0;color:<?php echo $srv ? '#1b7f4b' : ( $stale ? '#b26a00' : '#646970' ); ?>;">
						<?php
						if ( $srv ) {
							esc_html_e( '● Server timer connected — refresh runs every hour automatically.', 'hypeit' );
						} elseif ( $stale ) {
							esc_html_e( 'Background tasks aren’t running often enough on this server (usually because of page caching). Refreshes also run whenever you use the admin or the app — for fully automatic hourly refresh, add the server timer below.', 'hypeit' );
						} else {
							esc_html_e( 'Refresh runs about once an hour; adding the server timer below makes it fully reliable.', 'hypeit' );
						}
						?>
					</p>
					<details style="margin-top:10px;">
						<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Set up the server timer (Bluehost, 2 minutes)', 'hypeit' ); ?></summary>
						<ol style="margin:10px 0 0 18px;">
							<li><?php esc_html_e( 'In Bluehost, open Advanced → cPanel → Cron Jobs.', 'hypeit' ); ?></li>
							<li><?php esc_html_e( 'Common Settings: “Once Per Hour”.', 'hypeit' ); ?></li>
							<li><?php esc_html_e( 'Command — paste this line, then click “Add New Cron Job”:', 'hypeit' ); ?>
								<br /><code style="display:inline-block;margin-top:6px;word-break:break-all;">curl -s "<?php echo esc_html( $link ); ?>" &gt;/dev/null 2&gt;&amp;1</code>
							</li>
						</ol>
						<p class="description" style="margin:8px 0 0;"><?php esc_html_e( 'Keep this link private — it only runs the refresh, nothing else. The status above turns green within the hour once it works.', 'hypeit' ); ?></p>
					</details>
				</div>
			<?php endif; ?>
		</div>
		<?php

	}

	/**
	 * Default "please switch to Creator" message.
	 *
	 * @return string
	 */
	public static function default_switch_msg() {
		return __( "Hi {first}! This is iLike Agency 👋 Thanks for joining our blogger list.\nYour Instagram account is currently a personal account, so we can't include your numbers or verify you — and brands can't see you properly.\nPlease switch it to a free Creator account (it takes a minute and you keep everything, plus you get the full music library for Reels and Stories):\nInstagram → your profile → ☰ menu → Settings and activity → Account type and tools → Switch to professional account → Creator.\nIf your account is private, please make it public too. Thank you!", 'hypeit' );
	}

	/**
	 * WhatsApp link asking a blogger to switch to Creator ('' without a number).
	 *
	 * @param int $id Blogger ID.
	 * @return string
	 */
	public static function switch_wa( $id ) {
		$s     = self::get();
		$tpl   = '' !== trim( (string) $s['switch_msg'] ) ? $s['switch_msg'] : self::default_switch_msg();
		$first = trim( (string) get_post_meta( $id, '_cp_first', true ) );
		$text  = strtr( $tpl, array( '{first}' => $first, '{handle}' => '@' . CP_Library::handle( $id ) ) );
		$phone = get_post_meta( $id, '_cp_whatsapp', true ) ? get_post_meta( $id, '_cp_whatsapp', true ) : get_post_meta( $id, '_cp_phone', true );
		return CP_Atrium::wa_link( $phone, $text );
	}

	/**
	 * Admin list URL showing bloggers whose account couldn't be read.
	 *
	 * @return string
	 */
	public static function personal_list_url() {
		return admin_url( 'edit.php?post_type=' . CP_Library::CPT . '&cp_igstatus=personal' );
	}

	/**
	 * Status summary for a blogger (editor + app).
	 *
	 * @param int $id Blogger ID.
	 * @return array
	 */
	public static function info( $id ) {
		$synced = (int) get_post_meta( $id, '_cp_ig_synced', true );
		$eng    = get_post_meta( $id, '_cp_engagement', true );
		return array(
			'ready'      => self::ready(),
			'status'     => (string) get_post_meta( $id, '_cp_ig_status', true ),
			'synced'     => $synced,
			'ago'        => $synced ? human_time_diff( $synced ) : '',
			'engagement' => '' === $eng ? null : (float) $eng,
			'following'  => (int) get_post_meta( $id, '_cp_ig_following', true ),
			'posts'      => (int) get_post_meta( $id, '_cp_ig_posts', true ),
			'code'       => (string) get_post_meta( $id, '_cp_verify_code', true ),
		);
	}
}
