<?php
/**
 * Client authentication: campaign password + per-campaign session cookie.
 *
 * Session model: after a correct password the client receives a signed,
 * per-campaign SESSION cookie (no expiry set, so it is cleared when the
 * browser is closed). A page refresh in the same browser session keeps the
 * client authenticated; closing the browser or opening a new session forces
 * the password to be entered again.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Auth {

	/**
	 * Store a hashed password for a campaign. Empty string keeps the current one.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $plain       Plain password.
	 */
	public static function set_password( $campaign_id, $plain ) {
		if ( '' === $plain ) {
			return;
		}
		update_post_meta( $campaign_id, '_cp_password', wp_hash_password( $plain ) );
	}

	/**
	 * Whether a campaign has a password set.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function has_password( $campaign_id ) {
		return (bool) get_post_meta( $campaign_id, '_cp_password', true );
	}

	/**
	 * Verify a plain password against the stored hash.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $plain       Plain password.
	 * @return bool
	 */
	public static function check_password( $campaign_id, $plain ) {
		$hash = get_post_meta( $campaign_id, '_cp_password', true );
		if ( empty( $hash ) || '' === $plain ) {
			return false;
		}
		return wp_check_password( $plain, $hash );
	}

	/**
	 * Cookie name for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	public static function cookie_name( $campaign_id ) {
		return 'cp_auth_' . absint( $campaign_id );
	}

	/**
	 * Signed token value for the cookie (server-secret + per-campaign secret).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	private static function token_for( $campaign_id ) {
		$secret = get_post_meta( $campaign_id, '_cp_token', true );
		return hash_hmac( 'sha256', absint( $campaign_id ) . '|' . $secret, wp_salt( 'auth' ) );
	}

	/**
	 * Grant access: set the per-campaign session cookie.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function grant( $campaign_id ) {
		$name  = self::cookie_name( $campaign_id );
		$value = self::token_for( $campaign_id );

		setcookie(
			$name,
			$value,
			array(
				'expires'  => 0, // Session cookie: cleared when the browser closes.
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Make it available within the current request.
		$_COOKIE[ $name ] = $value;
	}

	/**
	 * Whether the current client is authenticated for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function is_authed( $campaign_id ) {
		$name = self::cookie_name( $campaign_id );
		if ( empty( $_COOKIE[ $name ] ) ) {
			return false;
		}
		return hash_equals( self::token_for( $campaign_id ), (string) $_COOKIE[ $name ] );
	}
}
