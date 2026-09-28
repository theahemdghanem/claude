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
			'app_splash_id'    => 0,
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
			'app_splash_id'    => absint( isset( $in['app_splash_id'] ) ? $in['app_splash_id'] : 0 ),
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
	 */
	private static function media_field( $id, $val, $preview ) {
		$url = $val ? wp_get_attachment_image_url( $val, 'medium' ) : '';
		?>
		<span class="cp-logo-preview" id="<?php echo esc_attr( $preview ); ?>">
			<?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt="" /><?php endif; ?>
		</span>
		<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $val ); ?>" />
		<button type="button" class="button cp-media-select" data-target="<?php echo esc_attr( $id ); ?>" data-preview="<?php echo esc_attr( $preview ); ?>"><?php esc_html_e( 'Select image', 'hypeit' ); ?></button>
		<button type="button" class="button cp-media-remove" data-target="<?php echo esc_attr( $id ); ?>" data-preview="<?php echo esc_attr( $preview ); ?>"<?php echo $val ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'hypeit' ); ?></button>
		<?php
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		$v = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'HypeIt App', 'hypeit' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'cp_app_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Review App (PWA)', 'hypeit' ); ?></h2>
				<p class="description"><?php esc_html_e( 'An installable app to review campaigns and monitor responses. It works on any domain where this plugin is installed.', 'hypeit' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable app', 'hypeit' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[pwa_enabled]" value="1" <?php checked( $v['pwa_enabled'], 1 ); ?> /> <?php esc_html_e( 'Enable the review app', 'hypeit' ); ?></label>
							<?php if ( $v['pwa_enabled'] ) : ?>
								<p class="description">
									<?php esc_html_e( 'App URL:', 'hypeit' ); ?>
									<a href="<?php echo esc_url( CP_PWA::app_url() ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( CP_PWA::app_url() ); ?></code></a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="app_name"><?php esc_html_e( 'App name', 'hypeit' ); ?></label></th>
						<td><input type="text" id="app_name" name="<?php echo esc_attr( self::OPTION ); ?>[app_name]" value="<?php echo esc_attr( $v['app_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="app_short_name"><?php esc_html_e( 'Short name', 'hypeit' ); ?></label></th>
						<td><input type="text" id="app_short_name" name="<?php echo esc_attr( self::OPTION ); ?>[app_short_name]" value="<?php echo esc_attr( $v['app_short_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="app_slug"><?php esc_html_e( 'App address', 'hypeit' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="app_slug" name="<?php echo esc_attr( self::OPTION ); ?>[app_slug]" value="<?php echo esc_attr( CP_PWA::slug() ); ?>" class="regular-text" style="max-width:220px;" />
							<p class="description"><?php esc_html_e( 'Custom link for the app, e.g. “hypeit”. After changing it, open the new address and add the app to your Home Screen again.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="app_theme_color"><?php esc_html_e( 'Theme color', 'hypeit' ); ?></label></th>
						<td><input type="text" id="app_theme_color" name="<?php echo esc_attr( self::OPTION ); ?>[app_theme_color]" value="<?php echo esc_attr( $v['app_theme_color'] ); ?>" class="regular-text" placeholder="#000000" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="app_bg_color"><?php esc_html_e( 'Background color', 'hypeit' ); ?></label></th>
						<td><input type="text" id="app_bg_color" name="<?php echo esc_attr( self::OPTION ); ?>[app_bg_color]" value="<?php echo esc_attr( $v['app_bg_color'] ); ?>" class="regular-text" placeholder="#000000" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'App icon', 'hypeit' ); ?></th>
						<td>
							<?php self::media_field( 'app_icon_id', (int) $v['app_icon_id'], 'app_icon_preview' ); ?>
							<p class="description"><?php esc_html_e( 'Square PNG, at least 512×512, for the installed app icon.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Splash image', 'hypeit' ); ?></th>
						<td>
							<?php self::media_field( 'app_splash_id', (int) $v['app_splash_id'], 'app_splash_preview' ); ?>
							<p class="description"><?php esc_html_e( 'Optional. Used as the iOS launch image.', 'hypeit' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Email Notifications', 'hypeit' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Instead of one email per action, updates are collected during a client’s session and sent as a single summary once the session has been inactive for the window below.', 'hypeit' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable notifications', 'hypeit' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[notify_enabled]" value="1" <?php checked( $v['notify_enabled'], 1 ); ?> /> <?php esc_html_e( 'Send consolidated campaign update emails', 'hypeit' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="notify_email"><?php esc_html_e( 'Default recipient(s)', 'hypeit' ); ?></label></th>
						<td>
							<input type="text" id="notify_email" name="<?php echo esc_attr( self::OPTION ); ?>[notify_email]" value="<?php echo esc_attr( $v['notify_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated. Leave blank to use the site admin email. Can be overridden per campaign.', 'hypeit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="notify_inactivity"><?php esc_html_e( 'Inactivity window (minutes)', 'hypeit' ); ?></label></th>
						<td>
							<input type="number" id="notify_inactivity" name="<?php echo esc_attr( self::OPTION ); ?>[notify_inactivity]" value="<?php echo esc_attr( $v['notify_inactivity'] ); ?>" min="1" max="1440" class="small-text" />
							<span class="description"><?php esc_html_e( 'Send the summary after this many minutes with no client activity (default 45).', 'hypeit' ); ?></span>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2 class="title"><?php esc_html_e( 'Data', 'hypeit' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'cp_app_group' ); ?>
				<?php
				// Re-submit the other values unchanged so this second form doesn't clear them.
				foreach ( self::get() as $k => $val ) {
					if ( 'delete_data' === $k ) {
						continue;
					}
					printf(
						'<input type="hidden" name="%1$s[%2$s]" value="%3$s" />',
						esc_attr( self::OPTION ),
						esc_attr( $k ),
						esc_attr( $val )
					);
				}
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'On uninstall', 'hypeit' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[delete_data]" value="1" <?php checked( $v['delete_data'], 1 ); ?> /> <?php esc_html_e( 'Delete all campaigns, responses and settings when the plugin is deleted', 'hypeit' ); ?></label>
							<p class="description"><?php esc_html_e( 'Off by default: deleting the plugin keeps your data so you can safely reinstall or update.', 'hypeit' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save data setting', 'hypeit' ) ); ?>
			</form>
		</div>
		<?php
	}
}
