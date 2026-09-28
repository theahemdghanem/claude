<?php
/**
 * Web Push notifications for the Campaign app (iPhone 16.4+ Home Screen app,
 * Android, desktop). Self-contained: VAPID (RFC 8292) + aes128gcm payload
 * encryption (RFC 8291) using PHP's OpenSSL — no third-party service.
 *
 * Subscriptions are stored per WordPress user (user meta), one per device.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Push {

	const OPT_KEYS = 'cp_push_vapid';
	const META     = '_cp_push_subs';

	/** @var array Pending sends for this request. */
	private static $queue = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		// Nothing global; sending is queued per request (see queue_new_blogger).
	}

	/* ------------------------------------------------------------------ */
	/* Capability + keys                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Can this server send Web Push?
	 *
	 * @return bool
	 */
	public static function supported() {
		return function_exists( 'openssl_pkey_new' )
			&& function_exists( 'openssl_pkey_derive' )
			&& function_exists( 'hash_hkdf' )
			&& function_exists( 'random_bytes' )
			&& in_array( 'aes-128-gcm', array_map( 'strtolower', (array) openssl_get_cipher_methods() ), true );
	}

	/**
	 * VAPID key pair (generated once, stored privately).
	 *
	 * @return array{pem:string,pub:string}|null
	 */
	public static function keys() {
		$k = get_option( self::OPT_KEYS );
		if ( is_array( $k ) && ! empty( $k['pem'] ) && ! empty( $k['pub'] ) ) {
			return $k;
		}
		if ( ! self::supported() ) {
			return null;
		}
		$pair = self::new_ec_key();
		if ( ! $pair ) {
			return null;
		}
		$pem = '';
		if ( ! openssl_pkey_export( $pair['res'], $pem ) ) {
			return null;
		}
		$k = array( 'pem' => $pem, 'pub' => self::b64u( $pair['pub'] ) );
		update_option( self::OPT_KEYS, $k, false );
		return $k;
	}

	/**
	 * Generate a P-256 key: resource + raw uncompressed public point.
	 *
	 * @return array{res:mixed,pub:string}|null
	 */
	private static function new_ec_key() {
		$res = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
		if ( ! $res ) {
			return null;
		}
		$d = openssl_pkey_get_details( $res );
		if ( empty( $d['ec']['x'] ) || empty( $d['ec']['y'] ) ) {
			return null;
		}
		$pub = "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
		return array( 'res' => $res, 'pub' => $pub );
	}

	/* ------------------------------------------------------------------ */
	/* Subscriptions                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Only real push services — prevents using this as a request relay.
	 *
	 * @param string $endpoint URL.
	 * @return bool
	 */
	private static function valid_endpoint( $endpoint ) {
		$p = wp_parse_url( (string) $endpoint );
		if ( empty( $p['scheme'] ) || 'https' !== $p['scheme'] || empty( $p['host'] ) ) {
			return false;
		}
		return (bool) preg_match( '/(^|\.)(push\.apple\.com|fcm\.googleapis\.com|push\.services\.mozilla\.com|notify\.windows\.com|push\.api\.chrome\.google\.com)$/i', $p['host'] );
	}

	/**
	 * A user's subscriptions.
	 *
	 * @param int $uid User ID.
	 * @return array
	 */
	private static function subs( $uid ) {
		$s = get_user_meta( $uid, self::META, true );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Remove a subscription by endpoint from a user.
	 *
	 * @param int    $uid      User ID.
	 * @param string $endpoint Endpoint.
	 */
	private static function drop_sub( $uid, $endpoint ) {
		$s = self::subs( $uid );
		unset( $s[ md5( $endpoint ) ] );
		update_user_meta( $uid, self::META, $s );
	}

	/**
	 * REST handler: /push/{key|status|subscribe|unsubscribe|test}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function rest( $request ) {
		$uid = CP_REST::user_id();
		$op  = (string) $request['op'];

		if ( 'key' === $op ) {
			$k = self::keys();
			return array( 'supported' => (bool) $k, 'key' => $k ? $k['pub'] : '' );
		}

		$endpoint = (string) $request->get_param( 'endpoint' );
		$sub      = $request->get_param( 'subscription' );
		if ( is_array( $sub ) && ! empty( $sub['endpoint'] ) ) {
			$endpoint = (string) $sub['endpoint'];
		}

		if ( 'status' === $op ) {
			$s = self::subs( $uid );
			return array( 'subscribed' => '' !== $endpoint && isset( $s[ md5( $endpoint ) ] ) );
		}

		if ( 'subscribe' === $op ) {
			if ( ! self::keys() ) {
				return new WP_Error( 'cp_push_unsupported', __( 'This server cannot send push notifications (OpenSSL is missing features).', 'hypeit' ), array( 'status' => 500 ) );
			}
			$p256 = is_array( $sub ) && isset( $sub['keys']['p256dh'] ) ? (string) $sub['keys']['p256dh'] : '';
			$auth = is_array( $sub ) && isset( $sub['keys']['auth'] ) ? (string) $sub['keys']['auth'] : '';
			if ( ! self::valid_endpoint( $endpoint ) || 65 !== strlen( self::b64u_dec( $p256 ) ) || 16 !== strlen( self::b64u_dec( $auth ) ) ) {
				return new WP_Error( 'cp_push_bad', __( 'Invalid push subscription.', 'hypeit' ), array( 'status' => 400 ) );
			}
			$s                     = self::subs( $uid );
			$s[ md5( $endpoint ) ] = array(
				'endpoint' => esc_url_raw( $endpoint ),
				'p256dh'   => $p256,
				'auth'     => $auth,
				'events'   => array( 'blogger' => true ),
				'ua'       => substr( sanitize_text_field( (string) $request->get_header( 'user_agent' ) ), 0, 180 ),
				'created'  => time(),
			);
			update_user_meta( $uid, self::META, $s );
			return array( 'subscribed' => true );
		}

		if ( 'unsubscribe' === $op ) {
			if ( '' !== $endpoint ) {
				self::drop_sub( $uid, $endpoint );
			}
			return array( 'subscribed' => false );
		}

		if ( 'test' === $op ) {
			$s = self::subs( $uid );
			if ( '' === $endpoint || ! isset( $s[ md5( $endpoint ) ] ) ) {
				return new WP_Error( 'cp_push_none', __( 'This device is not subscribed yet.', 'hypeit' ), array( 'status' => 400 ) );
			}
			$code = self::send(
				$s[ md5( $endpoint ) ],
				array(
					'title' => __( 'Notifications are on', 'hypeit' ),
					'body'  => __( 'You’ll get a notification here whenever a blogger submits the form.', 'hypeit' ),
					'url'   => CP_PWA::app_url() . '#/more',
					'tag'   => 'cp-test',
				)
			);
			if ( in_array( $code, array( 404, 410 ), true ) ) {
				self::drop_sub( $uid, $endpoint );
			}
			if ( $code < 200 || $code >= 300 ) {
				/* translators: %d: HTTP status. */
				return new WP_Error( 'cp_push_failed', sprintf( __( 'The push service rejected the message (code %d). Try turning notifications off and on again.', 'hypeit' ), $code ), array( 'status' => 502 ) );
			}
			return array( 'sent' => true );
		}

		return new WP_Error( 'cp_bad_op', __( 'Unknown action.', 'hypeit' ), array( 'status' => 400 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Sending                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Queue a "new blogger" push; sent after the page response is delivered.
	 *
	 * @param int  $blogger_id Blogger ID.
	 * @param bool $updated    Existing profile updated (vs new).
	 */
	public static function queue_new_blogger( $blogger_id, $updated = false ) {
		if ( ! self::supported() ) {
			return;
		}
		if ( empty( self::$queue ) ) {
			register_shutdown_function( array( __CLASS__, 'flush_queue' ) );
		}
		self::$queue[] = array( (int) $blogger_id, (bool) $updated );
	}

	/**
	 * Shutdown: release the visitor's connection, then deliver pushes.
	 */
	public static function flush_queue() {
		if ( empty( self::$queue ) ) {
			return;
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		$jobs        = self::$queue;
		self::$queue = array();
		foreach ( $jobs as $job ) {
			self::send_blogger( $job[0], $job[1] );
		}
	}

	/**
	 * Notify every subscribed admin device about a blogger submission.
	 *
	 * @param int  $id      Blogger ID.
	 * @param bool $updated Updated profile.
	 */
	public static function send_blogger( $id, $updated = false ) {
		$name   = trim( get_post_meta( $id, '_cp_first', true ) . ' ' . get_post_meta( $id, '_cp_last', true ) );
		$handle = CP_Library::handle( $id );
		$bits   = array();
		$bits[] = ( $name ? $name . ' ' : '' ) . '(@' . $handle . ')';
		$f      = (int) get_post_meta( $id, '_cp_followers', true );
		if ( $f ) {
			/* translators: %s: follower count. */
			$bits[] = sprintf( __( '%s followers', 'hypeit' ), number_format_i18n( $f ) );
		}
		$city = (string) get_post_meta( $id, '_cp_city', true );
		if ( $city ) {
			$bits[] = $city;
		}
		$payload = array(
			'title' => $updated ? __( 'Blogger updated their profile', 'hypeit' ) : __( 'New blogger submission', 'hypeit' ),
			'body'  => implode( ' · ', $bits ),
			'url'   => CP_PWA::app_url() . '#/b/' . (int) $id,
			'tag'   => 'cp-blogger-' . (int) $id,
			'icon'  => CP_Photo::url( $id, 'm' ),
		);

		$uids = get_users( array( 'meta_key' => self::META, 'fields' => 'ID' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		foreach ( (array) $uids as $uid ) {
			if ( ! user_can( $uid, 'edit_posts' ) ) {
				continue;
			}
			foreach ( self::subs( $uid ) as $sub ) {
				if ( empty( $sub['events']['blogger'] ) ) {
					continue;
				}
				$code = self::send( $sub, $payload );
				if ( in_array( $code, array( 404, 410 ), true ) ) {
					self::drop_sub( $uid, $sub['endpoint'] );
				}
			}
		}
	}

	/**
	 * Encrypt + deliver one push. Returns the HTTP status (0 on failure).
	 *
	 * @param array $sub     Subscription.
	 * @param array $payload Data.
	 * @return int
	 */
	public static function send( $sub, $payload ) {
		$keys = self::keys();
		if ( ! $keys || ! self::valid_endpoint( $sub['endpoint'] ) ) {
			return 0;
		}
		$body = self::encrypt( wp_json_encode( $payload ), self::b64u_dec( $sub['p256dh'] ), self::b64u_dec( $sub['auth'] ) );
		$jwt  = self::vapid_jwt( $sub['endpoint'], $keys['pem'] );
		if ( null === $body || null === $jwt ) {
			return 0;
		}
		$res = wp_remote_post(
			$sub['endpoint'],
			array(
				'timeout' => 8,
				'headers' => array(
					'Content-Type'     => 'application/octet-stream',
					'Content-Encoding' => 'aes128gcm',
					'TTL'              => '86400',
					'Urgency'          => 'high',
					'Authorization'    => 'vapid t=' . $jwt . ', k=' . $keys['pub'],
				),
				'body'    => $body,
			)
		);
		return is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}

	/**
	 * RFC 8291 aes128gcm encryption of a payload for one subscriber.
	 *
	 * @param string $plain  Payload.
	 * @param string $ua_pub Subscriber public key (65 bytes).
	 * @param string $auth   Subscriber auth secret (16 bytes).
	 * @return string|null
	 */
	public static function encrypt( $plain, $ua_pub, $auth ) {
		if ( 65 !== strlen( $ua_pub ) || 16 !== strlen( $auth ) ) {
			return null;
		}
		$local = self::new_ec_key();
		$peer  = openssl_pkey_get_public( self::pem_from_point( $ua_pub ) );
		if ( ! $local || ! $peer ) {
			return null;
		}
		$shared = openssl_pkey_derive( $peer, $local['res'], 32 );
		if ( false === $shared || 32 !== strlen( $shared ) ) {
			return null;
		}
		$as_pub = $local['pub'];
		$ikm    = hash_hkdf( 'sha256', $shared, 32, "WebPush: info\0" . $ua_pub . $as_pub, $auth );
		$salt   = random_bytes( 16 );
		$cek    = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
		$nonce  = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );
		$tag    = '';
		$cipher = openssl_encrypt( $plain . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( false === $cipher ) {
			return null;
		}
		return $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_pub . $cipher . $tag;
	}

	/**
	 * Signed VAPID JWT (ES256) for an endpoint's origin.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @param string $pem      Private key PEM.
	 * @return string|null
	 */
	public static function vapid_jwt( $endpoint, $pem ) {
		$p      = wp_parse_url( $endpoint );
		$header = self::b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$claims = self::b64u(
			wp_json_encode(
				array(
					'aud' => $p['scheme'] . '://' . $p['host'],
					'exp' => time() + 12 * HOUR_IN_SECONDS,
					'sub' => 'mailto:' . get_option( 'admin_email' ),
				)
			)
		);
		$input = $header . '.' . $claims;
		$der   = '';
		if ( ! openssl_sign( $input, $der, $pem, OPENSSL_ALGO_SHA256 ) ) {
			return null;
		}
		$raw = self::der_to_raw( $der );
		return $raw ? $input . '.' . self::b64u( $raw ) : null;
	}

	/* ------------------------------------------------------------------ */
	/* Encoding helpers                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * PEM public key from a raw uncompressed P-256 point.
	 *
	 * @param string $point 65 bytes.
	 * @return string
	 */
	private static function pem_from_point( $point ) {
		$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $point;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * DER ECDSA signature → raw 64-byte r||s.
	 *
	 * @param string $der DER.
	 * @return string|null
	 */
	private static function der_to_raw( $der ) {
		$pos = 0;
		if ( "\x30" !== ( $der[ $pos++ ] ?? '' ) ) {
			return null;
		}
		$len = ord( $der[ $pos++ ] );
		if ( $len & 0x80 ) {
			$pos += $len & 0x7f;
		}
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			if ( "\x02" !== ( $der[ $pos++ ] ?? '' ) ) {
				return null;
			}
			$l    = ord( $der[ $pos++ ] );
			$int  = ltrim( substr( $der, $pos, $l ), "\0" );
			$pos += $l;
			$out .= str_pad( $int, 32, "\0", STR_PAD_LEFT );
		}
		return 64 === strlen( $out ) ? $out : null;
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $bin Binary.
	 * @return string
	 */
	public static function b64u( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function b64u_dec( $s ) {
		$s = strtr( (string) $s, '-_', '+/' );
		$r = base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return false === $r ? '' : $r;
	}
}
