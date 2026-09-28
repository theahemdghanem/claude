<?php
/**
 * Public blogger onboarding: front-end form, submission processing,
 * settings, and admin notification email.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Onboarding {

	const QV     = 'cp_onboard';
	const OPTION = 'cp_onboard';

	/**
	 * Per-request state for the template (errors, values, success).
	 *
	 * @var array
	 */
	public static $state = array(
		'errors' => array(),
		'values' => array(),
	);

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_process' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}
	}

	/**
	 * Settings with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'enabled'       => 1,
			'slug'          => 'campaigns/onboarding',
			'intro'         => __( 'Welcome to iLike Agency. We are a professional agency working in marketing, PR, and brand collaborations. We’re happy to have you in our blogger list for future campaigns. Please complete your information below, and our team will contact you whenever a suitable campaign becomes available.', 'hypeit' ),
			'email_enabled' => 1,
			'email_to'      => '',
			'email_mode'    => 'instant',
			'digest_hour'   => 9,
			'digest_day'    => 0,
			'allow_update'  => 0,
			'follow_on'     => 1,
			'follow_user'   => '',
		);
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Instagram account for the thank-you Follow button.
	 *
	 * @return string
	 */
	public static function follow_user() {
		$s = self::get();
		if ( '' !== (string) $s['follow_user'] ) {
			return (string) $s['follow_user'];
		}
		$sync = class_exists( 'CP_IGSync' ) ? CP_IGSync::get() : array();
		return ! empty( $sync['ig_user'] ) ? (string) $sync['ig_user'] : 'ilikeagency';
	}

	/** @return string Onboarding slug. */
	public static function slug() {
		$s = self::get();
		$slug = trim( (string) $s['slug'], '/' );
		return '' !== $slug ? $slug : 'campaigns/onboarding';
	}

	/** @return string Onboarding URL. */
	public static function url() {
		return home_url( '/' . self::slug() . '/' );
	}

	/** @return string Raw user agent. */
	private static function ua() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	/** @return bool Whether the page is loaded inside an app's in-app browser. */
	public static function is_in_app_browser() {
		return (bool) preg_match( '/Instagram|FBAN|FBAV|FB_IAB|FBIOS|Line\/|Snapchat|TikTok|Musical|Twitter/i', self::ua() );
	}

	/** @return bool */
	public static function is_android() {
		return (bool) preg_match( '/Android/i', self::ua() );
	}

	/** @return string Android intent URL that opens the onboarding page in Chrome. */
	public static function android_intent_url() {
		$noscheme = preg_replace( '#^https?://#', '', self::url() );
		return 'intent://' . $noscheme . '#Intent;scheme=https;package=com.android.chrome;end';
	}

	/** @return bool Whether onboarding is enabled. */
	public static function enabled() {
		$s = self::get();
		return ! empty( $s['enabled'] );
	}

	/**
	 * Register the rewrite rule.
	 */
	public static function add_rewrite_rules() {
		$slug = self::slug();
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?' . self::QV . '=1', 'top' );
	}

	/**
	 * Flush rewrite rules once after a slug change.
	 */
	public static function maybe_flush() {
		if ( get_transient( 'cp_flush_rewrite' ) ) {
			delete_transient( 'cp_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QV;
		return $vars;
	}

	/**
	 * Is the current request the onboarding page?
	 *
	 * @return bool
	 */
	private static function is_page() {
		return (bool) get_query_var( self::QV );
	}

	/**
	 * Body classes for styling parity with the campaign page.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( self::is_page() ) {
			$classes[] = 'cp-campaign-page';
			$classes[] = 'page';
			$classes[] = 'page-template-default';
		}
		return $classes;
	}

	/**
	 * Swap in the onboarding template.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( ! self::is_page() ) {
			return $template;
		}
		if ( ! self::enabled() ) {
			status_header( 404 );
			nocache_headers();
			return $template;
		}
		return CP_DIR . 'templates/onboarding.php';
	}

	/**
	 * Enqueue assets.
	 */
	public static function enqueue() {
		if ( ! self::is_page() || ! self::enabled() ) {
			return;
		}
		$theme = CP_Settings::get();
		if ( ! empty( $theme['google_font_url'] ) ) {
			wp_enqueue_style( 'cp-google-font', $theme['google_font_url'], array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_style( 'cp-frontend', CP_URL . 'assets/css/frontend.css', array(), CP_VERSION );
		wp_add_inline_style( 'cp-frontend', CP_Frontend::inline_css( $theme ) );

		wp_enqueue_script( 'cp-onboarding', CP_URL . 'assets/js/onboarding.js', array(), CP_VERSION, true );
		wp_localize_script(
			'cp-onboarding',
			'CP_OB',
			array( 'areas' => CP_Location::areas_map() )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Submission                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Process a submitted onboarding form (before output).
	 */
	public static function maybe_process() {
		if ( ! self::is_page() || ! self::enabled() ) {
			return;
		}
		if ( empty( $_POST['cp_ob_submit'] ) ) {
			return;
		}
		// template_redirect can fire more than once per request — process once.
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		if ( ! isset( $_POST['cp_ob_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_ob_nonce'] ) ), 'cp_onboard' ) ) {
			self::$state['errors'][] = __( 'Security check failed. Please try again.', 'hypeit' );
			return;
		}

		$post = wp_unslash( $_POST );

		$v = array(
			'first'      => isset( $post['cp_first'] ) ? sanitize_text_field( $post['cp_first'] ) : '',
			'last'       => isset( $post['cp_last'] ) ? sanitize_text_field( $post['cp_last'] ) : '',
			'birthday'   => isset( $post['cp_birthday'] ) ? sanitize_text_field( $post['cp_birthday'] ) : '',
			'gender'     => isset( $post['cp_gender'] ) ? sanitize_key( $post['cp_gender'] ) : '',
			'phone'      => isset( $post['cp_phone'] ) ? sanitize_text_field( $post['cp_phone'] ) : '',
			'whatsapp'   => isset( $post['cp_whatsapp'] ) ? sanitize_text_field( $post['cp_whatsapp'] ) : '',
			'email'      => isset( $post['cp_email'] ) ? trim( sanitize_email( $post['cp_email'] ) ) : '',
			'city'       => isset( $post['cp_city'] ) ? sanitize_text_field( $post['cp_city'] ) : '',
			'area'       => isset( $post['cp_area'] ) ? sanitize_text_field( $post['cp_area'] ) : '',
			'area_other' => isset( $post['cp_area_other'] ) ? sanitize_text_field( $post['cp_area_other'] ) : '',
			'address'    => isset( $post['cp_address'] ) ? sanitize_text_field( $post['cp_address'] ) : '',
			'ig'         => isset( $post['cp_ig'] ) ? sanitize_text_field( $post['cp_ig'] ) : '',
			'followers'  => isset( $post['cp_followers'] ) ? absint( preg_replace( '/[^0-9]/', '', $post['cp_followers'] ) ) : 0,
			'categories' => isset( $post['cp_categories'] ) && is_array( $post['cp_categories'] ) ? array_map( 'absint', $post['cp_categories'] ) : array(),
			'cat_other'  => isset( $post['cp_cat_other'] ) ? sanitize_text_field( $post['cp_cat_other'] ) : '',
			'collab'     => isset( $post['cp_collab'] ) && is_array( $post['cp_collab'] ) ? array_map( 'sanitize_key', $post['cp_collab'] ) : array(),
			'photo_token'=> isset( $post['cp_photo_token'] ) && CP_Photo::has_temp( sanitize_key( $post['cp_photo_token'] ) ) ? sanitize_key( $post['cp_photo_token'] ) : '',
		);

		// Profile photo: process a new upload now so it survives a failed submit.
		$photo_error = '';
		if ( ! empty( $_FILES['cp_photo'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['cp_photo']['error'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$staged = CP_Photo::stage_upload( $_FILES['cp_photo'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_wp_error( $staged ) ) {
				$photo_error = $staged->get_error_message();
			} else {
				$v['photo_token'] = $staged;
			}
		}
		self::$state['values'] = $v;

		$errors  = array();
		$genders = CP_Library::genders();

		if ( '' === $v['first'] ) {
			$errors[] = __( 'First name is required.', 'hypeit' );
		}
		if ( '' === $v['last'] ) {
			$errors[] = __( 'Last name is required.', 'hypeit' );
		}
		if ( ! array_key_exists( $v['gender'], $genders ) ) {
			$errors[] = __( 'Please select your gender.', 'hypeit' );
		}
		if ( $v['followers'] <= 0 ) {
			$errors[] = __( 'Please enter your follower count.', 'hypeit' );
		}
		if ( '' === $v['birthday'] ) {
			$errors[] = __( 'Birthday is required.', 'hypeit' );
		}
		if ( '' === $v['phone'] ) {
			$errors[] = __( 'Phone number is required.', 'hypeit' );
		}
		if ( '' === trim( (string) ( $post['cp_email'] ?? '' ) ) ) {
			$errors[] = __( 'Email is required.', 'hypeit' );
		} elseif ( ! is_email( $v['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'hypeit' );
		}
		if ( '' === $v['whatsapp'] ) {
			$errors[] = __( 'WhatsApp number is required.', 'hypeit' );
		}
		if ( '' === $v['city'] ) {
			$errors[] = __( 'Please select your city.', 'hypeit' );
		}
		if ( empty( $v['categories'] ) ) {
			$errors[] = __( 'Please select at least one category.', 'hypeit' );
		}
		$collab_types = CP_Library::collab_types();
		$v['collab']  = array_values( array_intersect( array_keys( $collab_types ), $v['collab'] ) );
		if ( empty( $v['collab'] ) ) {
			$errors[] = __( 'Please choose which campaign types you are open to.', 'hypeit' );
		}

		$handle = CP_Library::extract_handle( $v['ig'] );

		// If the blogger connected Instagram before submitting, trust that data.
		$cp_conn = ( ! empty( $post['cp_ig_token'] ) ) ? CP_Verify::get_connection( sanitize_text_field( $post['cp_ig_token'] ) ) : null;
		if ( $cp_conn && ! empty( $cp_conn['username'] ) ) {
			$handle         = CP_Library::extract_handle( $cp_conn['username'] );
			$v['followers'] = (int) $cp_conn['followers'];
		}

		if ( '' === $handle ) {
			$errors[] = __( 'Please enter your Instagram username or profile link.', 'hypeit' );
		}

		if ( '' !== $handle && CP_Library::is_blocked( $handle ) ) {
			$errors[] = __( 'This Instagram account cannot submit the form. Please contact us if you think this is a mistake.', 'hypeit' );
		}

		$settings = self::get();
		$existing = '' !== $handle ? CP_Library::find_by_handle( $handle ) : 0;
		if ( $existing && empty( $settings['allow_update'] ) ) {
			$errors[] = __( 'An account with this Instagram username already exists in our list.', 'hypeit' );
		}

		// Photo is required — unless an existing profile being updated already has one.
		if ( '' !== $photo_error ) {
			$errors[] = $photo_error;
		} elseif ( '' === $v['photo_token'] && ! ( $existing && CP_Photo::url( $existing ) ) ) {
			$errors[] = __( 'Please add a profile photo.', 'hypeit' );
		}

		if ( ! empty( $errors ) ) {
			self::$state['errors'] = $errors;
			return;
		}

		// Area / full address are no longer collected on the form.
		$area = '';

		// Category term IDs (from the fixed list only — no custom categories).
		$cats = array_values( array_unique( array_filter( array_map( 'absint', $v['categories'] ) ) ) );

		$first = CP_Library::normalize_name( $v['first'] );
		$last  = CP_Library::normalize_name( $v['last'] );
		$name  = trim( $first . ' ' . $last );
		$title = $name ? $name . ' (@' . $handle . ')' : '@' . $handle;

		if ( $existing ) {
			$id = $existing;
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
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
			self::$state['errors'][] = __( 'Something went wrong saving your information. Please try again.', 'hypeit' );
			return;
		}

		if ( '' !== $v['photo_token'] ) {
			CP_Photo::attach_temp( $id, $v['photo_token'] );
		}

		update_post_meta( $id, '_cp_first', $first );
		update_post_meta( $id, '_cp_last', $last );
		update_post_meta( $id, '_cp_ig', $handle );
		update_post_meta( $id, '_cp_ig_url', CP_Library::profile_url( $handle ) );
		update_post_meta( $id, '_cp_gender', $v['gender'] );
		update_post_meta( $id, '_cp_followers', $v['followers'] );
		update_post_meta( $id, '_cp_country', 'Egypt' );
		update_post_meta( $id, '_cp_city', $v['city'] );
		update_post_meta( $id, '_cp_area', $area );
		// Private.
		update_post_meta( $id, '_cp_birthday', $v['birthday'] );
		update_post_meta( $id, '_cp_phone', $v['phone'] );
		update_post_meta( $id, '_cp_whatsapp', $v['whatsapp'] );
		if ( '' !== $v['email'] ) {
			update_post_meta( $id, '_cp_email', $v['email'] );
		}
		update_post_meta( $id, '_cp_address', $v['address'] );
		update_post_meta( $id, '_cp_blocked', '0' );
		update_post_meta( $id, '_cp_source', 'onboarding' );
		// The blogger updated their own profile (Bloggers page: "Last updated by blogger").
		update_post_meta( $id, '_cp_blogger_updated', time() );
		CP_Bloggers_UI::touch( $id );
		update_post_meta( $id, '_cp_collab', empty( $v['collab'] ) ? '' : ',' . implode( ',', $v['collab'] ) . ',' );

		if ( ! empty( $cats ) ) {
			wp_set_post_terms( $id, $cats, CP_Library::TAX_TAG, true );
		}

		$cat_names = array();
		foreach ( $cats as $cid ) {
			$term = get_term( $cid, CP_Library::TAX_TAG );
			if ( $term && ! is_wp_error( $term ) ) {
				$cat_names[] = $term->name;
			}
		}

		CP_Blogger_CPT::sync_title( $id );

		if ( $cp_conn ) {
			CP_Verify::apply_connection( $id, $cp_conn );
			CP_Verify::consume_connection( sanitize_text_field( $post['cp_ig_token'] ) );
		}

		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}

		if ( 'instant' === CP_Digest::mode() ) {
			self::notify_admin( $id, $v, $handle, $area, $cat_names );
		} else {
			CP_Digest::queue( $id, (bool) $existing );
		}

		// Read the real Instagram numbers (no login) right after the response is sent.
		CP_IGSync::queue( $id );

		// Push to subscribed admin devices (sent after the response is flushed).
		CP_Push::queue_new_blogger( $id, (bool) $existing );

		wp_safe_redirect( add_query_arg( array( 'cp_ob' => 'success', 'b' => (int) $id, 't' => CP_Verify::token( $id ) ), self::url() ) );
		exit;
	}

	/**
	 * Send the admin notification email.
	 *
	 * @param int    $id     Blogger ID.
	 * @param array  $v      Values.
	 * @param string $handle Handle.
	 * @param string $area   Area.
	 * @param array  $cats   Categories.
	 */
	private static function notify_admin( $id, $v, $handle, $area, $cats ) {
		$s = self::get();
		if ( empty( $s['email_enabled'] ) ) {
			return;
		}
		$to = $s['email_to'] ? $s['email_to'] : get_option( 'admin_email' );
		$to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) ) );
		if ( empty( $to ) ) {
			return;
		}

		$genders = CP_Library::genders();
		$name    = trim( CP_Library::normalize_name( $v['first'] ) . ' ' . CP_Library::normalize_name( $v['last'] ) );
		$gender  = isset( $genders[ $v['gender'] ] ) ? $genders[ $v['gender'] ] : $v['gender'];
		$loc     = trim( $v['city'] . ( $area ? ' — ' . $area : '' ) );

		$rows = array(
			array( __( 'Name', 'hypeit' ), $name ),
			array( __( 'Instagram', 'hypeit' ), '@' . $handle ),
			array( __( 'Followers', 'hypeit' ), number_format_i18n( (int) $v['followers'] ) ),
			array( __( 'Gender', 'hypeit' ), $gender ),
			array( __( 'Categories', 'hypeit' ), implode( ', ', $cats ) ),
			array( __( 'Location', 'hypeit' ), $loc ),
			array( __( 'Email', 'hypeit' ), '' !== $v['email'] ? $v['email'] : '—' ),
		);

		$b  = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;max-width:600px;margin:0 auto;">';
		$b .= '<h2 style="margin:0 0 4px;">' . esc_html__( 'New Blogger Submission', 'hypeit' ) . '</h2>';
		$b .= '<p style="color:#555;margin:0 0 16px;">' . esc_html__( 'A new blogger has submitted their information through the onboarding form.', 'hypeit' ) . '</p>';
		$b .= '<table style="border-collapse:collapse;width:100%;">';
		foreach ( $rows as $r ) {
			$b .= '<tr><td style="padding:8px 10px;border-bottom:1px solid #eee;">' . esc_html( $r[0] ) . '</td><td style="padding:8px 10px;border-bottom:1px solid #eee;font-weight:700;">' . esc_html( $r[1] ) . '</td></tr>';
		}
		$b .= '</table>';
		$b .= '<p style="margin:18px 0 0;"><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) ) . '" style="color:#0a58ca;">' . esc_html__( 'Review the submission in the dashboard', 'hypeit' ) . '</a></p>';
		$b .= '</div>';

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] New blogger submission', 'hypeit' ),
			get_bloginfo( 'name' )
		);

		add_filter( 'wp_mail_content_type', array( 'CP_Notify', 'html_content_type' ) );
		wp_mail( $to, $subject, $b );
		remove_filter( 'wp_mail_content_type', array( 'CP_Notify', 'html_content_type' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings page                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Onboarding', 'hypeit' ),
			__( 'Onboarding', 'hypeit' ),
			'manage_options',
			'cp-onboarding',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting( 'cp_onboard_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	/**
	 * Sanitize settings. Slug changes trigger a rewrite flush.
	 *
	 * @param array $in Raw.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$in   = is_array( $in ) ? $in : array();
		$old  = self::get();
		$slug = isset( $in['slug'] ) ? trim( (string) $in['slug'], '/' ) : '';
		$slug = preg_replace( '#[^a-z0-9/_-]#', '', strtolower( $slug ) );
		if ( '' === $slug ) {
			$slug = 'campaigns/onboarding';
		}

		$clean = array(
			'enabled'       => empty( $in['enabled'] ) ? 0 : 1,
			'slug'          => $slug,
			'intro'         => isset( $in['intro'] ) ? wp_kses_post( $in['intro'] ) : '',
			'email_enabled' => empty( $in['email_enabled'] ) ? 0 : 1,
			'email_to'      => isset( $in['email_to'] ) ? sanitize_text_field( $in['email_to'] ) : '',
			'email_mode'    => isset( $in['email_mode'] ) && in_array( $in['email_mode'], array( 'instant', 'daily', 'weekly' ), true ) ? $in['email_mode'] : 'instant',
			'digest_hour'   => isset( $in['digest_hour'] ) ? max( 0, min( 23, (int) $in['digest_hour'] ) ) : 9,
			'digest_day'    => isset( $in['digest_day'] ) ? max( 0, min( 6, (int) $in['digest_day'] ) ) : 0,
			'allow_update'  => empty( $in['allow_update'] ) ? 0 : 1,
			'follow_on'     => empty( $in['follow_on'] ) ? 0 : 1,
			'follow_user'   => isset( $in['follow_user'] ) ? preg_replace( '/[^A-Za-z0-9._]/', '', ltrim( (string) $in['follow_user'], '@' ) ) : '',
		);

		if ( $old['slug'] !== $clean['slug'] ) {
			// Slug changed: refresh rules on next load.
			add_rewrite_rule( '^' . $clean['slug'] . '/?$', 'index.php?' . self::QV . '=1', 'top' );
			set_transient( 'cp_flush_rewrite', 1, 60 );
		}

		return $clean;
	}

	/**
	 * Settings page URL.
	 *
	 * @param string $anchor Optional #anchor.
	 * @return string
	 */
	public static function settings_url( $anchor = '' ) {
		return admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-onboarding' ) . ( $anchor ? '#' . $anchor : '' );
	}

	/**
	 * A card-style on/off switch (shared admin design).
	 *
	 * @param string $name    Field name.
	 * @param bool   $checked State.
	 * @param string $label   Label.
	 * @param string $help    Help.
	 */
	private static function switch_row( $name, $checked, $label, $help = '' ) {
		?>
		<label class="cpw-switchrow">
			<span class="cpw-switch"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> /><span class="cpw-slider" aria-hidden="true"></span></span>
			<span class="cpw-switchtext"><strong><?php echo esc_html( $label ); ?></strong><?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?></span>
		</label>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings() {
		$v    = self::get();
		$n    = self::OPTION;
		$cp_q = get_option( CP_Digest::QUEUE, array() );
		$cp_q = is_array( $cp_q ) ? count( $cp_q ) : 0;
		global $wp_locale;
		?>
		<div class="wrap cp-admin cp-wide cps">
			<div class="cp-pagehead">
				<div>
					<h1><?php esc_html_e( 'Blogger Onboarding', 'hypeit' ); ?></h1>
					<p class="cp-sub"><?php esc_html_e( 'The public form bloggers use to join your library — what it says, where it lives, who hears about new sign-ups, and the categories they can pick.', 'hypeit' ); ?></p>
				</div>
				<?php if ( $v['enabled'] ) : ?>
					<div class="cps-live">
						<span class="cps-dot is-on" aria-hidden="true"></span>
						<code><?php echo esc_html( self::url() ); ?></code>
						<button type="button" class="button cps-copy" data-link="<?php echo esc_url( self::url() ); ?>" data-done="<?php esc_attr_e( 'Copied!', 'hypeit' ); ?>"><?php esc_html_e( 'Copy', 'hypeit' ); ?></button>
						<a class="button" href="<?php echo esc_url( self::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open form', 'hypeit' ); ?></a>
					</div>
				<?php else : ?>
					<div class="cps-live is-off"><span class="cps-dot" aria-hidden="true"></span><?php esc_html_e( 'The form is turned off', 'hypeit' ); ?></div>
				<?php endif; ?>
			</div>
			<?php settings_errors(); ?>

			<div class="cps-layout">
				<form method="post" action="options.php" class="cps-main">
					<?php settings_fields( 'cp_onboard_group' ); ?>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Form page', 'hypeit' ); ?></h3>
						<?php self::switch_row( $n . '[enabled]', (bool) $v['enabled'], __( 'Onboarding page is on', 'hypeit' ), __( 'Turn off to stop new sign-ups; existing bloggers are not affected.', 'hypeit' ) ); ?>
						<div class="cps-field">
							<label for="cp_ob_slug"><?php esc_html_e( 'Page address', 'hypeit' ); ?></label>
							<div class="cpw-slug"><code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="cp_ob_slug" name="<?php echo esc_attr( $n ); ?>[slug]" value="<?php echo esc_attr( $v['slug'] ); ?>" /></div>
							<p class="cpw-muted"><?php esc_html_e( 'Default: campaigns/onboarding. May include slashes.', 'hypeit' ); ?></p>
						</div>
						<div class="cps-field">
							<label for="cp_ob_intro"><?php esc_html_e( 'Introduction message', 'hypeit' ); ?></label>
							<textarea id="cp_ob_intro" name="<?php echo esc_attr( $n ); ?>[intro]" rows="5" class="large-text"><?php echo esc_textarea( $v['intro'] ); ?></textarea>
							<p class="cpw-muted"><?php esc_html_e( 'Shown above the form.', 'hypeit' ); ?></p>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Thank-you page', 'hypeit' ); ?></h3>
						<?php self::switch_row( $n . '[follow_on]', (bool) $v['follow_on'], __( 'Show a big “Follow us on Instagram” button', 'hypeit' ) ); ?>
						<div class="cps-field">
							<label for="cp_ob_follow"><?php esc_html_e( 'Your Instagram username', 'hypeit' ); ?></label>
							<div class="cpb-at cps-at"><em>@</em><input type="text" id="cp_ob_follow" name="<?php echo esc_attr( $n ); ?>[follow_user]" value="<?php echo esc_attr( $v['follow_user'] ); ?>" placeholder="<?php echo esc_attr( self::follow_user() ); ?>" /></div>
							<p class="cpw-muted"><?php esc_html_e( 'Leave empty to use the account connected for Instagram sync.', 'hypeit' ); ?></p>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'New sign-up emails', 'hypeit' ); ?></h3>
						<?php self::switch_row( $n . '[email_enabled]', (bool) $v['email_enabled'], __( 'Email me when a blogger submits the form', 'hypeit' ) ); ?>
						<div class="cps-field">
							<label for="cp_ob_email"><?php esc_html_e( 'Recipient(s)', 'hypeit' ); ?></label>
							<input type="text" id="cp_ob_email" name="<?php echo esc_attr( $n ); ?>[email_to]" value="<?php echo esc_attr( $v['email_to'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
							<p class="cpw-muted"><?php esc_html_e( 'Comma-separated. Blank uses the site admin email.', 'hypeit' ); ?></p>
						</div>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Delivery', 'hypeit' ); ?></span>
							<div class="cps-options">
								<?php
								$modes = array(
									'instant' => array( __( 'Right away', 'hypeit' ), __( 'One email per submission', 'hypeit' ) ),
									'daily'   => array( __( 'Daily digest', 'hypeit' ), __( 'One email a day', 'hypeit' ) ),
									'weekly'  => array( __( 'Weekly digest', 'hypeit' ), __( 'One email a week', 'hypeit' ) ),
								);
								foreach ( $modes as $mk => $ml ) :
									?>
									<label class="cps-option"><input type="radio" name="<?php echo esc_attr( $n ); ?>[email_mode]" value="<?php echo esc_attr( $mk ); ?>" <?php checked( $v['email_mode'], $mk ); ?> /><span><strong><?php echo esc_html( $ml[0] ); ?></strong><small><?php echo esc_html( $ml[1] ); ?></small></span></label>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="cps-field cps-inline">
							<label><?php esc_html_e( 'Send digests at', 'hypeit' ); ?>
								<select name="<?php echo esc_attr( $n ); ?>[digest_hour]">
									<?php for ( $h = 0; $h < 24; $h++ ) : ?>
										<option value="<?php echo (int) $h; ?>" <?php selected( (int) $v['digest_hour'], $h ); ?>><?php echo esc_html( date_i18n( get_option( 'time_format' ), mktime( $h, 0, 0 ) ) ); ?></option>
									<?php endfor; ?>
								</select>
							</label>
							<label><?php esc_html_e( 'Weekly on', 'hypeit' ); ?>
								<select name="<?php echo esc_attr( $n ); ?>[digest_day]">
									<?php for ( $d = 0; $d < 7; $d++ ) : ?>
										<option value="<?php echo (int) $d; ?>" <?php selected( (int) $v['digest_day'], $d ); ?>><?php echo esc_html( $wp_locale->get_weekday( $d ) ); ?></option>
									<?php endfor; ?>
								</select>
							</label>
						</div>
						<p class="cpw-muted">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: timezone. */
									__( 'Times use your site timezone (%s). A digest lists every new or updated blogger since the last one; nothing is sent if nobody new joined.', 'hypeit' ),
									wp_timezone_string()
								)
							);
							if ( $cp_q ) {
								echo ' ' . esc_html( sprintf( /* translators: %d: count. */ _n( '%d blogger is waiting for the next digest.', '%d bloggers are waiting for the next digest.', $cp_q, 'hypeit' ), $cp_q ) );
							}
							?>
						</p>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Existing Instagram accounts', 'hypeit' ); ?></h3>
						<?php self::switch_row( $n . '[allow_update]', (bool) $v['allow_update'], __( 'Let bloggers update their existing profile', 'hypeit' ), __( 'Off: a submission with a username that’s already in the library is rejected as a duplicate.', 'hypeit' ) ); ?>
					</section>

					<div class="cps-save"><?php submit_button( __( 'Save settings', 'hypeit' ), 'primary', 'submit', false ); ?></div>
				</form>

				<aside class="cps-side">
					<?php CP_Tags::render_card(); ?>
				</aside>
			</div>
		</div>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.cps-copy' );
			if ( ! b ) { return; }
			var done = function () { var t = b.textContent; b.textContent = b.getAttribute( 'data-done' ); setTimeout( function () { b.textContent = t; }, 1400 ); };
			if ( navigator.clipboard && window.isSecureContext ) { navigator.clipboard.writeText( b.getAttribute( 'data-link' ) ).then( done ); }
		} );
		</script>
		<?php
	}
}
