<?php
/**
 * "HypeIt App" settings: PWA controls + email notification controls.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_App_Settings {

	const OPTION = 'cp_app';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Defaults + saved settings.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'pwa_enabled'      => 1,
			'app_name'         => 'HypeIt',
			'app_short_name'   => 'HypeIt',
			'app_theme_color'  => '#000000',
			'app_bg_color'     => '#000000',
			'app_icon_id'      => 0,
			'notify_enabled'   => 0,
			'notify_email'     => '',
			'notify_inactivity'=> 45,
			'delete_data'      => 0,
		);
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Add the submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'HypeIt App', 'hypeit' ),
			__( 'HypeIt App', 'hypeit' ),
			'manage_options',
			'cp-app',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the setting.
	 */
	public static function register() {
		register_setting(
			'cp_app_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	/**
	 * Enqueue media + admin assets on this page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue( $hook ) {
		// Match the page by its slug, not the menu title (the title can be renamed).
		if ( ! is_string( $hook ) || ! preg_match( '/_page_cp-app$/', $hook ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'cp-admin', CP_URL . 'assets/css/admin.css', array(), CP_VERSION );
		wp_enqueue_script( 'cp-admin', CP_URL . 'assets/js/admin.js', array(), CP_VERSION, true );
		wp_enqueue_script( 'cp-qrcode', CP_URL . 'assets/js/vendor/qrcode.js', array(), '1.4.4', true );
		wp_enqueue_script( 'cp-app-settings', CP_URL . 'assets/js/app-settings.js', array( 'cp-qrcode', 'cp-admin' ), CP_VERSION, true );
		wp_localize_script(
			'cp-admin',
			'CP_ADMIN',
			array(
				'chooseLogo' => __( 'Select image', 'hypeit' ),
				'useImage'   => __( 'Use this image', 'hypeit' ),
				'copied'     => __( 'Copied!', 'hypeit' ),
			)
		);
	}

	/**
	 * Sanitize.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$in       = is_array( $input ) ? $input : array();
		$old_slug = CP_PWA::slug();
		$new_slug = preg_replace( '#[^a-z0-9/_-]#', '', strtolower( trim( (string) ( $in['app_slug'] ?? '' ), '/' ) ) );
		$new_slug = '' !== $new_slug ? $new_slug : 'campaign-app';
		if ( $new_slug !== $old_slug ) {
			// Refresh rewrite rules on the next load so the new app address works.
			set_transient( 'cp_flush_rewrite', 1, 60 );
		}
		return array(
			'app_slug'         => $new_slug,
			'pwa_enabled'      => empty( $in['pwa_enabled'] ) ? 0 : 1,
			'app_name'         => sanitize_text_field( isset( $in['app_name'] ) ? $in['app_name'] : '' ),
			'app_short_name'   => sanitize_text_field( isset( $in['app_short_name'] ) ? $in['app_short_name'] : '' ),
			'app_theme_color'  => sanitize_text_field( isset( $in['app_theme_color'] ) ? $in['app_theme_color'] : '' ),
			'app_bg_color'     => sanitize_text_field( isset( $in['app_bg_color'] ) ? $in['app_bg_color'] : '' ),
			'app_icon_id'      => absint( isset( $in['app_icon_id'] ) ? $in['app_icon_id'] : 0 ),
			'notify_enabled'   => empty( $in['notify_enabled'] ) ? 0 : 1,
			'notify_email'     => sanitize_text_field( isset( $in['notify_email'] ) ? $in['notify_email'] : '' ),
			'notify_inactivity'=> max( 1, absint( isset( $in['notify_inactivity'] ) ? $in['notify_inactivity'] : 45 ) ),
			'delete_data'      => empty( $in['delete_data'] ) ? 0 : 1,
		);
	}

	/**
	 * Media picker field.
	 *
	 * @param string $id      Input id.
	 * @param int    $val     Attachment id.
	 * @param string $preview Preview id.
	 * @param string $label   Visible label.
	 * @param string $help    Help text.
	 */
	private static function media_field( $id, $val, $preview, $label, $help ) {
		$url = $val ? wp_get_attachment_image_url( $val, 'medium' ) : '';
		?>
		<div class="cpa-media">
			<span class="cp-logo-preview cpa-media-prev" id="<?php echo esc_attr( $preview ); ?>">
				<?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt="" /><?php endif; ?>
			</span>
			<div>
				<strong><?php echo esc_html( $label ); ?></strong>
				<small><?php echo esc_html( $help ); ?></small>
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $val ); ?>" />
				<span class="cpa-media-btns">
					<button type="button" class="button cp-media-select" data-target="<?php echo esc_attr( $id ); ?>" data-preview="<?php echo esc_attr( $preview ); ?>"><?php echo esc_html( $url ? __( 'Change', 'hypeit' ) : __( 'Select image', 'hypeit' ) ); ?></button>
					<button type="button" class="button-link cp-media-remove" data-target="<?php echo esc_attr( $id ); ?>" data-preview="<?php echo esc_attr( $preview ); ?>"<?php echo $val ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'hypeit' ); ?></button>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Switch row.
	 *
	 * @param string $key   Setting key.
	 * @param bool   $on    State.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	private static function switch_row( $key, $on, $label, $help = '' ) {
		?>
		<label class="cpw-switchrow">
			<span class="cpw-switch"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $on ); ?> /><span class="cpw-slider" aria-hidden="true"></span></span>
			<span class="cpw-switchtext"><strong><?php echo esc_html( $label ); ?></strong><?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?></span>
		</label>
		<?php
	}

	/**
	 * Colour field: picker + hex text (the text field is what's saved).
	 *
	 * @param string $key   Setting key.
	 * @param string $val   Value.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	private static function color_field( $key, $val, $label, $help ) {
		$hex = preg_match( '/^#[0-9a-f]{6}$/i', (string) $val ) ? $val : '#000000';
		?>
		<div class="cps-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<span class="cpa-color">
				<input type="color" value="<?php echo esc_attr( $hex ); ?>" data-for="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" />
				<input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" placeholder="#000000" maxlength="7" spellcheck="false" />
			</span>
			<p class="cpw-muted"><?php echo esc_html( $help ); ?></p>
		</div>
		<?php
	}

	/**
	 * Devices signed up for push notifications.
	 *
	 * @return int
	 */
	private static function push_devices() {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", CP_Push::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n    = 0;
		foreach ( (array) $rows as $r ) {
			$v  = maybe_unserialize( $r );
			$n += is_array( $v ) ? count( $v ) : 0;
		}
		return $n;
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		$v       = self::get();
		$n       = self::OPTION;
		$on      = ! empty( $v['pwa_enabled'] );
		$url     = CP_PWA::app_url();
		$icon    = $v['app_icon_id'] ? wp_get_attachment_image_url( (int) $v['app_icon_id'], 'medium' ) : '';
		$push_ok = class_exists( 'CP_Push' ) && CP_Push::supported();
		$devices = $push_ok ? self::push_devices() : 0;
		$short   = $v['app_short_name'] ? $v['app_short_name'] : $v['app_name'];
		?>
		<div class="wrap cp-admin cp-wide cps cpa">
			<div class="cp-pagehead">
				<div>
					<h1><?php esc_html_e( 'HypeIt App', 'hypeit' ); ?></h1>
					<p class="cp-sub"><?php esc_html_e( 'Your team’s phone app for campaigns, bloggers and insights — installed from the browser, no app store needed.', 'hypeit' ); ?></p>
				</div>
				<?php if ( $on ) : ?>
					<div class="cps-live">
						<span class="cps-dot is-on" aria-hidden="true"></span>
						<code><?php echo esc_html( $url ); ?></code>
						<button type="button" class="button cps-copy" data-link="<?php echo esc_url( $url ); ?>" data-done="<?php esc_attr_e( 'Copied!', 'hypeit' ); ?>"><?php esc_html_e( 'Copy', 'hypeit' ); ?></button>
						<a class="button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open app', 'hypeit' ); ?></a>
					</div>
				<?php else : ?>
					<div class="cps-live is-off"><span class="cps-dot" aria-hidden="true"></span><?php esc_html_e( 'The app is turned off', 'hypeit' ); ?></div>
				<?php endif; ?>
			</div>
			<?php settings_errors(); ?>

			<div class="cps-layout">
				<form method="post" action="options.php" class="cps-main" id="cpa-form">
					<?php settings_fields( 'cp_app_group' ); ?>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'App', 'hypeit' ); ?></h3>
						<?php self::switch_row( 'pwa_enabled', $on, __( 'App is on', 'hypeit' ), __( 'Anyone who can edit campaigns in WordPress can sign in with their WordPress username and password.', 'hypeit' ) ); ?>
						<div class="cps-field">
							<label for="app_slug"><?php esc_html_e( 'App address', 'hypeit' ); ?></label>
							<div class="cpw-slug"><code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="app_slug" name="<?php echo esc_attr( $n ); ?>[app_slug]" value="<?php echo esc_attr( CP_PWA::slug() ); ?>" /></div>
							<p class="cpw-muted"><?php esc_html_e( 'e.g. “hypeit”. After changing it, open the new address and add the app to your Home Screen again.', 'hypeit' ); ?></p>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Look & feel', 'hypeit' ); ?></h3>
						<div class="cps-grid2">
							<div class="cps-field">
								<label for="app_name"><?php esc_html_e( 'App name', 'hypeit' ); ?></label>
								<input type="text" id="app_name" name="<?php echo esc_attr( $n ); ?>[app_name]" value="<?php echo esc_attr( $v['app_name'] ); ?>" />
								<p class="cpw-muted"><?php esc_html_e( 'Shown when installing and in the app switcher.', 'hypeit' ); ?></p>
							</div>
							<div class="cps-field">
								<label for="app_short_name"><?php esc_html_e( 'Home Screen name', 'hypeit' ); ?></label>
								<input type="text" id="app_short_name" name="<?php echo esc_attr( $n ); ?>[app_short_name]" value="<?php echo esc_attr( $v['app_short_name'] ); ?>" maxlength="20" />
								<p class="cpw-muted"><?php esc_html_e( 'Short — about 12 characters fit under the icon.', 'hypeit' ); ?></p>
							</div>
						</div>
						<div class="cps-grid2">
							<?php
							self::color_field( 'app_theme_color', $v['app_theme_color'], __( 'Theme colour', 'hypeit' ), __( 'Colours the phone’s status bar.', 'hypeit' ) );
							self::color_field( 'app_bg_color', $v['app_bg_color'], __( 'Background colour', 'hypeit' ), __( 'The app’s background colour on Android.', 'hypeit' ) );
							?>
						</div>
						<div class="cps-grid2 cpa-medias">
							<?php
							self::media_field( 'app_icon_id', (int) $v['app_icon_id'], 'app_icon_preview', __( 'App icon', 'hypeit' ), __( 'Square PNG, at least 512×512.', 'hypeit' ) );
							?>
						</div>
					</section>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Client response emails', 'hypeit' ); ?></h3>
						<?php self::switch_row( 'notify_enabled', ! empty( $v['notify_enabled'] ), __( 'Email me when a client responds', 'hypeit' ), __( 'Updates are collected during the client’s visit and sent as one summary — not one email per click.', 'hypeit' ) ); ?>
						<div class="cps-grid2">
							<div class="cps-field">
								<label for="notify_email"><?php esc_html_e( 'Recipient(s)', 'hypeit' ); ?></label>
								<input type="text" id="notify_email" name="<?php echo esc_attr( $n ); ?>[notify_email]" value="<?php echo esc_attr( $v['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
								<p class="cpw-muted"><?php esc_html_e( 'Comma-separated. Blank uses the site admin email. Each campaign can override it.', 'hypeit' ); ?></p>
							</div>
							<div class="cps-field">
								<label for="notify_inactivity"><?php esc_html_e( 'Send the summary after', 'hypeit' ); ?></label>
								<span class="cpa-suffix"><input type="number" id="notify_inactivity" name="<?php echo esc_attr( $n ); ?>[notify_inactivity]" value="<?php echo esc_attr( $v['notify_inactivity'] ); ?>" min="1" max="1440" /><em><?php esc_html_e( 'minutes quiet', 'hypeit' ); ?></em></span>
								<p class="cpw-muted"><?php esc_html_e( 'Default 45.', 'hypeit' ); ?></p>
							</div>
						</div>
					</section>

					<section class="cpw-card cpa-danger">
						<h3><?php esc_html_e( 'Data', 'hypeit' ); ?></h3>
						<?php self::switch_row( 'delete_data', ! empty( $v['delete_data'] ), __( 'Delete everything when the plugin is deleted', 'hypeit' ), __( 'Off (recommended): deleting the plugin keeps all bloggers, campaigns and settings, so you can reinstall or update safely.', 'hypeit' ) ); ?>
					</section>

					<div class="cps-save"><?php submit_button( __( 'Save settings', 'hypeit' ), 'primary', 'submit', false ); ?></div>
				</form>

				<aside class="cps-side">
					<section class="cpw-card cpa-phonecard">
						<h3><?php esc_html_e( 'Preview', 'hypeit' ); ?></h3>
						<div class="cpa-phones">
							<div class="cpa-phone" aria-hidden="true">
								<span class="cpa-bar" id="cpa-bar"></span>
								<div class="cpa-home">
									<span class="cpa-app is-ghost"></span><span class="cpa-app is-ghost"></span>
									<span class="cpa-app"><span class="cpa-icon" id="cpa-icon"><?php echo $icon ? '<img src="' . esc_url( $icon ) . '" alt="" />' : esc_html( mb_substr( $short, 0, 1 ) ); ?></span><small id="cpa-short"><?php echo esc_html( $short ); ?></small></span>
									<span class="cpa-app is-ghost"></span><span class="cpa-app is-ghost"></span><span class="cpa-app is-ghost"></span>
								</div>
								<small class="cpa-caption"><?php esc_html_e( 'Home Screen', 'hypeit' ); ?></small>
							</div>
						</div>
					</section>

					<?php if ( $on ) : ?>
						<section class="cpw-card cpa-install">
							<h3><?php esc_html_e( 'Install on your phone', 'hypeit' ); ?></h3>
							<div class="cpa-qr" id="cpa-qr" data-url="<?php echo esc_url( $url ); ?>"></div>
							<p class="cpw-muted cpa-center"><?php esc_html_e( 'Scan with the phone camera to open the app.', 'hypeit' ); ?></p>
							<ol class="cpa-steps">
								<li><strong>iPhone</strong> — <?php esc_html_e( 'open in Safari, tap Share, then “Add to Home Screen”.', 'hypeit' ); ?></li>
								<li><strong>Android</strong> — <?php esc_html_e( 'open in Chrome, tap ⋮, then “Install app”.', 'hypeit' ); ?></li>
							</ol>
						</section>
					<?php endif; ?>

					<section class="cpw-card">
						<h3><?php esc_html_e( 'Push notifications', 'hypeit' ); ?></h3>
						<?php if ( $push_ok ) : ?>
							<div class="cpa-push"><b><?php echo (int) $devices; ?></b><span><?php echo esc_html( _n( 'device signed up', 'devices signed up', $devices, 'hypeit' ) ); ?></span></div>
							<p class="cpw-muted"><?php esc_html_e( 'Turn notifications on in the app (More → Notifications) to get a push when a new blogger joins.', 'hypeit' ); ?></p>
						<?php else : ?>
							<p class="cpv-note is-warn"><?php esc_html_e( 'This server can’t send push notifications (it needs PHP’s OpenSSL with AES-GCM). Ask your host to enable it; everything else works.', 'hypeit' ); ?></p>
						<?php endif; ?>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}
}
