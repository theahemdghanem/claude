<?php
/**
 * Preferences, asset loading, toolbar menu and profile settings.
 *
 * @package EyeComfortDarkMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class. Everything is per user: each person picks their own
 * mode and palette, stored in user meta.
 */
final class ECDM_Plugin {

	const META   = 'ecdm_prefs';
	const COOKIE = 'ecdm_pref';

	/**
	 * Whether the engine was enqueued on this request (the toolbar menu is
	 * only useful then).
	 *
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 1 );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ), 1 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login' ), 1 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 999 );
		add_action( 'wp_ajax_ecdm_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_cookie' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ), 1 );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ), 1 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ECDM_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/* ------------------------------------------------------------------
	 * Preferences
	 * ------------------------------------------------------------------ */

	/**
	 * Default preferences for someone who has not chosen yet.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			'mode'    => 'on',
			'palette' => 'dim',
			'text'    => 'normal',
			'images'  => true,
			'canvas'  => true,
			'front'   => false,
		);

		/**
		 * Filters the defaults used for users who have not saved preferences.
		 *
		 * @param array $defaults Default preferences.
		 */
		return apply_filters( 'ecdm_default_prefs', $defaults );
	}

	/**
	 * Allowed values for the choice settings.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'mode'    => array(
				'on'   => __( 'Always dark', 'eye-comfort-dark-mode' ),
				'auto' => __( 'Follow my system', 'eye-comfort-dark-mode' ),
				'off'  => __( 'Off (light)', 'eye-comfort-dark-mode' ),
			),
			'palette' => array(
				'dim'   => __( 'Dim grey', 'eye-comfort-dark-mode' ),
				'dark'  => __( 'Dark', 'eye-comfort-dark-mode' ),
				'black' => __( 'Pure black', 'eye-comfort-dark-mode' ),
			),
			'text'    => array(
				'soft'   => __( 'Soft', 'eye-comfort-dark-mode' ),
				'normal' => __( 'Normal', 'eye-comfort-dark-mode' ),
				'bright' => __( 'Bright', 'eye-comfort-dark-mode' ),
			),
		);
	}

	/**
	 * Merge untrusted input over a base set of preferences.
	 *
	 * @param array $raw  Input values (only known keys are read).
	 * @param array $base Current preferences.
	 * @return array
	 */
	public static function sanitize( $raw, $base ) {
		$prefs = $base;
		foreach ( self::choices() as $key => $allowed ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && isset( $allowed[ $raw[ $key ] ] ) ) {
				$prefs[ $key ] = $raw[ $key ];
			}
		}
		foreach ( array( 'images', 'canvas', 'front' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$prefs[ $key ] = in_array( $raw[ $key ], array( true, 1, '1', 'true', 'on' ), true );
			}
		}
		return $prefs;
	}

	/**
	 * A user's preferences, falling back to the defaults.
	 *
	 * @param int $user_id User ID; the current user when omitted.
	 * @return array
	 */
	public static function get_prefs( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$stored  = $user_id ? get_user_meta( $user_id, self::META, true ) : array();
		return self::sanitize( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * Store a user's preferences.
	 *
	 * @param int   $user_id User ID.
	 * @param array $prefs   Sanitized preferences.
	 */
	private static function save_prefs( $user_id, $prefs ) {
		update_user_meta( $user_id, self::META, $prefs );
		if ( get_current_user_id() === (int) $user_id ) {
			self::set_cookie( $prefs );
		}
	}

	/* ------------------------------------------------------------------
	 * Cookie, so the login screen can be dark before anyone is signed in.
	 * ------------------------------------------------------------------ */

	/**
	 * Cookie value for a set of preferences.
	 *
	 * @param array $prefs Preferences.
	 * @return string
	 */
	private static function cookie_value( $prefs ) {
		return implode( '.', array( $prefs['mode'], $prefs['palette'], $prefs['text'], $prefs['images'] ? '1' : '0' ) );
	}

	/**
	 * Remember the look for the login screen.
	 *
	 * @param array $prefs Preferences.
	 */
	private static function set_cookie( $prefs ) {
		if ( headers_sent() ) {
			return;
		}
		$value = self::cookie_value( $prefs );
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => SITECOOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * Keep the cookie in step with the saved preferences.
	 */
	public static function sync_cookie() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$prefs   = self::get_prefs();
		$current = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( self::cookie_value( $prefs ) !== $current ) {
			self::set_cookie( $prefs );
		}
	}

	/**
	 * Preferences stored in the cookie, or null.
	 *
	 * @return array|null
	 */
	private static function cookie_prefs() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
		if ( count( $parts ) !== 4 ) {
			return null;
		}
		return self::sanitize(
			array(
				'mode'    => $parts[0],
				'palette' => $parts[1],
				'text'    => $parts[2],
				'images'  => $parts[3],
			),
			self::defaults()
		);
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueue the engine in <head>, after the page's stylesheets.
	 *
	 * @param array $prefs   Preferences.
	 * @param bool  $with_ui Also load the toolbar menu and saving.
	 * @param bool  $canvas  Allow darkening editor iframes.
	 */
	private static function enqueue( $prefs, $with_ui, $canvas ) {
		$config = array(
			'mode'    => $prefs['mode'],
			'palette' => $prefs['palette'],
			'text'    => $prefs['text'],
			'images'  => (bool) $prefs['images'],
			'canvas'  => $canvas && $prefs['canvas'],
		);

		if ( $with_ui ) {
			$config['ajaxUrl'] = admin_url( 'admin-ajax.php' );
			$config['nonce']   = wp_create_nonce( 'ecdm_save' );
			$config['i18n']    = array(
				'dark'  => __( 'Dark', 'eye-comfort-dark-mode' ),
				'light' => __( 'Light', 'eye-comfort-dark-mode' ),
			);
		}

		wp_enqueue_script( 'ecdm-engine', ECDM_URL . 'assets/js/ecdm-engine.js', array(), ECDM_VERSION, false );
		wp_add_inline_script( 'ecdm-engine', 'window.ecdmConfig = ' . wp_json_encode( $config ) . ';', 'before' );

		if ( $with_ui ) {
			wp_enqueue_script( 'ecdm-ui', ECDM_URL . 'assets/js/ecdm-ui.js', array( 'ecdm-engine' ), ECDM_VERSION, true );
			wp_enqueue_style( 'ecdm-ui', ECDM_URL . 'assets/css/ecdm-ui.css', array(), ECDM_VERSION );
		}

		self::$loaded = true;
	}

	/**
	 * Dashboard screens.
	 */
	public static function enqueue_admin() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		self::enqueue( self::get_prefs(), true, true );
	}

	/**
	 * The public site, only for signed-in users who asked for it.
	 */
	public static function enqueue_front() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$prefs = self::get_prefs();
		if ( ! $prefs['front'] ) {
			return;
		}
		self::enqueue( $prefs, is_admin_bar_showing(), false );
	}

	/**
	 * Login, lost password and similar screens.
	 */
	public static function enqueue_login() {
		$prefs = self::cookie_prefs();
		if ( ! $prefs || 'off' === $prefs['mode'] ) {
			return;
		}
		self::enqueue( $prefs, false, false );
	}

	/* ------------------------------------------------------------------
	 * Toolbar menu
	 * ------------------------------------------------------------------ */

	/**
	 * Add the dark mode menu to the toolbar.
	 *
	 * @param WP_Admin_Bar $bar Toolbar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! self::$loaded || ! is_user_logged_in() ) {
			return;
		}

		$prefs   = self::get_prefs();
		$choices = self::choices();
		$moon    = '<svg viewBox="0 0 20 20" width="20" height="20" focusable="false"><path fill="currentColor" d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>';

		$bar->add_node(
			array(
				'id'     => 'ecdm',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon ecdm-ab-icon" aria-hidden="true">' . $moon . '</span><span class="ab-label ecdm-ab-label">' . esc_html__( 'Dark', 'eye-comfort-dark-mode' ) . '</span>',
				'href'   => '#',
				'meta'   => array(
					'title' => __( 'Toggle dark mode (Alt+Shift+D)', 'eye-comfort-dark-mode' ),
				),
			)
		);

		$groups = array(
			'mode'    => __( 'Dark mode', 'eye-comfort-dark-mode' ),
			'palette' => __( 'Palette', 'eye-comfort-dark-mode' ),
			'text'    => __( 'Text brightness', 'eye-comfort-dark-mode' ),
		);

		foreach ( $groups as $key => $heading ) {
			$group = 'ecdm-group-' . $key;
			$bar->add_group(
				array(
					'id'     => $group,
					'parent' => 'ecdm',
					'meta'   => array( 'class' => 'ecdm-ab-group' ),
				)
			);
			$bar->add_node(
				array(
					'id'     => 'ecdm-heading-' . $key,
					'parent' => $group,
					'title'  => esc_html( $heading ),
					'meta'   => array( 'class' => 'ecdm-ab-heading' ),
				)
			);
			foreach ( $choices[ $key ] as $value => $label ) {
				$bar->add_node(
					array(
						'id'     => 'ecdm-set-' . $key . '-' . $value,
						'parent' => $group,
						'title'  => esc_html( $label ),
						'href'   => '#',
						'meta'   => array(
							'class' => $prefs[ $key ] === $value ? 'ecdm-current' : '',
						),
					)
				);
			}
		}

		$bar->add_group(
			array(
				'id'     => 'ecdm-group-more',
				'parent' => 'ecdm',
				'meta'   => array( 'class' => 'ecdm-ab-group' ),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'ecdm-settings-link',
				'parent' => 'ecdm-group-more',
				'title'  => esc_html__( 'More settings…', 'eye-comfort-dark-mode' ),
				'href'   => admin_url( 'profile.php#ecdm-settings' ),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'ecdm-shortcut',
				'parent' => 'ecdm-group-more',
				'title'  => esc_html__( 'Shortcut: Alt+Shift+D', 'eye-comfort-dark-mode' ),
				'meta'   => array( 'class' => 'ecdm-ab-note' ),
			)
		);
	}

	/**
	 * Save changes made from the toolbar menu or the shortcut.
	 */
	public static function ajax_save() {
		check_ajax_referer( 'ecdm_save' );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( null, 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() whitelists every value.
		$prefs = self::sanitize( wp_unslash( $_POST ), self::get_prefs( $user_id ) );
		self::save_prefs( $user_id, $prefs );
		wp_send_json_success( $prefs );
	}

	/* ------------------------------------------------------------------
	 * Profile screen
	 * ------------------------------------------------------------------ */

	/**
	 * Settings section on the profile screen.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function profile_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$prefs   = self::get_prefs( $user->ID );
		$choices = self::choices();
		$labels  = array(
			'mode'    => __( 'Dark mode', 'eye-comfort-dark-mode' ),
			'palette' => __( 'Palette', 'eye-comfort-dark-mode' ),
			'text'    => __( 'Text brightness', 'eye-comfort-dark-mode' ),
		);
		$hints   = array(
			'mode'    => __( '“Follow my system” switches with your computer’s light/dark setting.', 'eye-comfort-dark-mode' ),
			'palette' => __( 'Dim grey is the gentlest on the eyes; pure black suits OLED screens and dark rooms.', 'eye-comfort-dark-mode' ),
			'text'    => __( 'Soft lowers text brightness to reduce glare. Changes preview instantly.', 'eye-comfort-dark-mode' ),
		);
		$toggles = array(
			'images' => __( 'Dim images and videos slightly', 'eye-comfort-dark-mode' ),
			'canvas' => __( 'Darken the writing area of the block and classic editors', 'eye-comfort-dark-mode' ),
			'front'  => __( 'Also darken the public site while I am signed in (only I see it)', 'eye-comfort-dark-mode' ),
		);
		?>
		<div id="ecdm-settings">
			<h2><?php esc_html_e( 'Eye Comfort Dark Mode', 'eye-comfort-dark-mode' ); ?></h2>
			<?php wp_nonce_field( 'ecdm_profile_' . $user->ID, 'ecdm_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $labels as $key => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<fieldset class="ecdm-choices ecdm-choices-<?php echo esc_attr( $key ); ?>">
								<legend class="screen-reader-text"><span><?php echo esc_html( $label ); ?></span></legend>
								<?php foreach ( $choices[ $key ] as $value => $text ) : ?>
									<label>
										<input type="radio" name="ecdm[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $prefs[ $key ], $value ); ?> />
										<?php if ( 'palette' === $key ) : ?>
											<span class="ecdm-swatch ecdm-swatch-<?php echo esc_attr( $value ); ?>" aria-hidden="true"></span>
										<?php endif; ?>
										<?php echo esc_html( $text ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html( $hints[ $key ] ); ?></p>
							</fieldset>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'eye-comfort-dark-mode' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Options', 'eye-comfort-dark-mode' ); ?></span></legend>
							<?php foreach ( $toggles as $key => $text ) : ?>
								<label>
									<input type="checkbox" name="ecdm[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $prefs[ $key ] ); ?> />
									<?php echo esc_html( $text ); ?>
								</label><br />
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'Tip: press Alt+Shift+D on any admin screen to switch between dark and light, or use the moon in the toolbar.', 'eye-comfort-dark-mode' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save the profile section.
	 *
	 * @param int $user_id User being saved.
	 */
	public static function save_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['ecdm_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ecdm_nonce'] ) ), 'ecdm_profile_' . $user_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() whitelists every value.
		$raw = isset( $_POST['ecdm'] ) && is_array( $_POST['ecdm'] ) ? wp_unslash( $_POST['ecdm'] ) : array();
		// Unticked checkboxes are not submitted.
		foreach ( array( 'images', 'canvas', 'front' ) as $key ) {
			if ( ! isset( $raw[ $key ] ) ) {
				$raw[ $key ] = '0';
			}
		}
		self::save_prefs( $user_id, self::sanitize( $raw, self::get_prefs( $user_id ) ) );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'profile.php#ecdm-settings' ) ) . '">' . esc_html__( 'Settings', 'eye-comfort-dark-mode' ) . '</a>' );
		return $links;
	}
}
