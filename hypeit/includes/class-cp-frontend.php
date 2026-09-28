<?php
/**
 * Front-end client-facing campaign page.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Frontend {

	const QUERY_VAR = 'cp_campaign_token';

	/**
	 * Whether the current password attempt failed (for the form message).
	 *
	 * @var bool
	 */
	public static $auth_error = false;

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_authenticate' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register the /campaign/{token}/ rewrite rule.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&/]+)' );
		add_rewrite_rule( '^campaign/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Build a campaign URL from a token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function campaign_url( $token ) {
		return home_url( '/campaign/' . rawurlencode( $token ) . '/' );
	}

	/**
	 * Get the current token from the query var.
	 *
	 * @return string
	 */
	public static function current_token() {
		return sanitize_text_field( get_query_var( self::QUERY_VAR ) );
	}

	/**
	 * Look up a published campaign by token.
	 *
	 * @param string $token Token.
	 * @return WP_Post|null
	 */
	public static function get_campaign_by_token( $token ) {
		if ( '' === $token ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => CP_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_cp_token',
						'value' => $token,
					),
				),
			)
		);

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Handle the password submission before any output.
	 */
	public static function maybe_authenticate() {
		$token = self::current_token();
		if ( '' === $token ) {
			return;
		}

		if ( empty( $_POST['cp_password_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['cp_pw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_pw_nonce'] ) ), 'cp_password' ) ) {
			self::$auth_error = true;
			return;
		}

		$campaign = self::get_campaign_by_token( $token );
		if ( ! $campaign ) {
			return;
		}

		$password = isset( $_POST['cp_password'] ) ? (string) wp_unslash( $_POST['cp_password'] ) : '';

		if ( CP_Auth::check_password( $campaign->ID, $password ) ) {
			CP_Auth::grant( $campaign->ID );
			wp_safe_redirect( self::campaign_url( $token ) );
			exit;
		}

		self::$auth_error = true;
	}

	/**
	 * Add body classes on the campaign page. `cp-campaign-page` scopes the
	 * full-width CSS; the standard page classes help themes render their normal
	 * (inner-page) header layout on this custom endpoint.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( '' !== self::current_token() ) {
			$classes[] = 'cp-campaign-page';
			$classes[] = 'page';
			$classes[] = 'page-template-default';
		}
		return $classes;
	}

	/**
	 * Swap in the campaign template when the token is present.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public static function template_include( $template ) {
		$token = self::current_token();
		if ( '' === $token ) {
			return $template;
		}

		$campaign = self::get_campaign_by_token( $token );

		if ( ! $campaign ) {
			status_header( 404 );
			nocache_headers();
		}

		// Expose for the template.
		$GLOBALS['cp_current_campaign'] = $campaign;

		return CP_DIR . 'templates/campaign-page.php';
	}

	/**
	 * Enqueue front-end assets and inject theme-override CSS variables.
	 */
	public static function enqueue() {
		$token = self::current_token();
		if ( '' === $token ) {
			return;
		}

		$campaign = self::get_campaign_by_token( $token );
		if ( ! $campaign ) {
			return;
		}

		$theme = CP_Settings::get();

		if ( ! empty( $theme['google_font_url'] ) ) {
			wp_enqueue_style( 'cp-google-font', $theme['google_font_url'], array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}

		wp_enqueue_style( 'cp-frontend', CP_URL . 'assets/css/frontend.css', array(), CP_VERSION );
		wp_add_inline_style( 'cp-frontend', self::inline_css( $theme ) );

		wp_enqueue_script( 'cp-frontend', CP_URL . 'assets/js/frontend.js', array(), CP_VERSION, true );
		wp_localize_script(
			'cp-frontend',
			'CP_FRONT',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'cp_frontend' ),
				'campaignId'  => (int) $campaign->ID,
				'maxGuests'   => (int) ( get_post_meta( $campaign->ID, '_cp_max_guests', true ) ?: 4 ),
				'i18n'        => array(
					'saving'     => __( 'Saving…', 'hypeit' ),
					'saved'      => __( 'Saved', 'hypeit' ),
					'error'      => __( 'Could not save. Please try again.', 'hypeit' ),
					'bloggerOnly'=> __( 'Blogger only', 'hypeit' ),
					'totalOne'   => __( 'Blogger only — 1 person total.', 'hypeit' ),
					/* translators: %d total people. */
					'totalMany'  => __( 'Total attendance: %d people including the blogger.', 'hypeit' ),
					/* translators: %d additional guests. */
					'guestsLine' => __( 'Confirmed — %d additional guest(s).', 'hypeit' ),
				),
			)
		);
	}

	/**
	 * Build the inline CSS-variable overrides. Only non-empty values are output,
	 * so blank fields fall back to the theme via the stylesheet's defaults.
	 *
	 * @param array $theme Theme settings.
	 * @return string
	 */
	public static function inline_css( $theme ) {
		$map = array(
			'font_family'     => '--cp-font',
			'header_offset'   => '--cp-top-offset',
			'side_offset'     => '--cp-side-offset',
			'color_primary'   => '--cp-primary',
			'color_secondary' => '--cp-secondary',
			'color_accent'    => '--cp-accent',
			'color_bg'        => '--cp-bg',
			'color_text'      => '--cp-text',
			'border_radius'   => '--cp-radius',
			'button_bg'       => '--cp-btn-bg',
			'button_text'     => '--cp-btn-text',
			'button_radius'   => '--cp-btn-radius',
		);

		$lines = array();
		foreach ( $map as $key => $var ) {
			if ( ! empty( $theme[ $key ] ) ) {
				$lines[] = $var . ':' . $theme[ $key ] . ';';
			}
		}

		if ( empty( $lines ) ) {
			return '';
		}

		return '.cp-campaign{' . implode( '', $lines ) . '}';
	}
}
