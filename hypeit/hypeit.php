<?php
/**
 * Plugin Name: HypeIt
 * Description: Blogger management for agencies — a blogger library with onboarding, verification and automatic Instagram sync, plus private campaign pages where clients pick the bloggers they want. Includes insights, a companion app and push notifications.
 * Version:     2.4.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      iLike Agency
 * Text Domain: hypeit
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * HypeIt is the renamed "Campaign" plugin (v1.x). Both share the same data, so
 * they must never run together: if the old plugin is still active, step aside
 * and ask for it to be deactivated.
 */
if ( defined( 'CP_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p><strong>HypeIt:</strong> '
				. esc_html__( 'Please deactivate the old “Campaign” plugin. HypeIt replaces it and keeps all your bloggers, campaigns and settings — it will start automatically once “Campaign” is deactivated.', 'hypeit' )
				. '</p></div>';
		}
	);
	return;
}

define( 'CP_VERSION', '2.4.0' );
define( 'CP_FILE', __FILE__ );
define( 'CP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CP_URL', plugin_dir_url( __FILE__ ) );
define( 'CP_BASENAME', plugin_basename( __FILE__ ) );
define( 'CP_POST_TYPE', 'cp_campaign' );

require_once CP_DIR . 'includes/class-cp-db.php';
require_once CP_DIR . 'includes/class-cp-auth.php';
require_once CP_DIR . 'includes/class-cp-library.php';
require_once CP_DIR . 'includes/class-cp-location.php';
require_once CP_DIR . 'includes/class-cp-blogger-cpt.php';
require_once CP_DIR . 'includes/class-cp-lists.php';
require_once CP_DIR . 'includes/class-cp-everyone.php';
require_once CP_DIR . 'includes/class-cp-tags.php';
require_once CP_DIR . 'includes/class-cp-onboarding.php';
require_once CP_DIR . 'includes/class-cp-insights.php';
require_once CP_DIR . 'includes/class-cp-verify.php';
require_once CP_DIR . 'includes/class-cp-install.php';
require_once CP_DIR . 'includes/class-cp-post-type.php';
require_once CP_DIR . 'includes/class-cp-settings.php';
require_once CP_DIR . 'includes/class-cp-app-settings.php';
require_once CP_DIR . 'includes/class-cp-admin.php';
require_once CP_DIR . 'includes/class-cp-frontend.php';
require_once CP_DIR . 'includes/class-cp-ajax.php';
require_once CP_DIR . 'includes/class-cp-rest.php';
require_once CP_DIR . 'includes/class-cp-pwa.php';
require_once CP_DIR . 'includes/class-cp-notify.php';
require_once CP_DIR . 'includes/class-cp-photo.php';
require_once CP_DIR . 'includes/class-cp-logo.php';
require_once CP_DIR . 'includes/class-cp-splash.php';
require_once CP_DIR . 'includes/class-cp-push.php';
require_once CP_DIR . 'includes/class-cp-join.php';
require_once CP_DIR . 'includes/class-cp-atrium.php';
require_once CP_DIR . 'includes/class-cp-igsync.php';
require_once CP_DIR . 'includes/class-cp-close.php';
require_once CP_DIR . 'includes/class-cp-expiry.php';
require_once CP_DIR . 'includes/class-cp-digest.php';
require_once CP_DIR . 'includes/class-cp-roster.php';
require_once CP_DIR . 'includes/class-cp-dupes.php';
require_once CP_DIR . 'includes/class-cp-bloggers-ui.php';
require_once CP_DIR . 'includes/class-cp-campaigns-ui.php';
require_once CP_DIR . 'includes/class-cp-plugin.php';

register_activation_hook( __FILE__, array( 'CP_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CP_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'CP_Plugin', 'instance' ) );

// One-time note after the campaign tidy-up.
add_action(
	'admin_notices',
	static function () {
		$n = get_option( 'hypeit_cleanup_notice' );
		if ( ! $n || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		delete_option( 'hypeit_cleanup_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>HypeIt:</strong> ' . esc_html(
			sprintf(
				/* translators: 1: rows, 2: campaigns. */
				__( 'Campaigns tidied — removed %1$d waiting entries of bloggers no longer in your library (deleted, blocked or old usernames) from %2$d open campaigns. Client selections were kept.', 'hypeit' ),
				(int) $n['removed'],
				(int) $n['campaigns']
			)
		) . '</p></div>';
	}
);

/*
 * One-time setup for HypeIt 2.x — runs on the first load after the old plugin
 * is gone (even if HypeIt was activated while "Campaign" was still on):
 * ensures tables + rewrite rules, renames the app, and makes sure removing the
 * old plugin can never delete data.
 */
add_action(
	'init',
	static function () {
		if ( get_option( 'hypeit_version' ) === CP_VERSION ) {
			return;
		}
		CP_Install::activate();

		$app = get_option( 'cp_app', array() );
		$app = is_array( $app ) ? $app : array();
		if ( empty( $app['app_name'] ) || 'Campaign' === $app['app_name'] ) {
			$app['app_name'] = 'HypeIt';
		}
		if ( empty( $app['app_short_name'] ) || 'Campaign' === $app['app_short_name'] ) {
			$app['app_short_name'] = 'HypeIt';
		}
		// Deleting the old "Campaign" plugin reads this same option — keep data safe.
		$app['delete_data'] = 0;
		update_option( 'cp_app', $app );

		// Profile completeness + "missing info" for every blogger (Bloggers page
		// filters), plus the "completed at" / "updated by blogger" sort keys.
		CP_Bloggers_UI::refresh_all();
		CP_Insights::bust();

		// Tidy campaigns: drop waiting rows of bloggers no longer in the library
		// (deleted / blocked / old usernames). Client decisions are always kept.
		$cleaned = CP_Roster::clean_all();
		if ( $cleaned['removed'] ) {
			update_option( 'hypeit_cleanup_notice', $cleaned, false );
		}

		update_option( 'hypeit_version', CP_VERSION );
	},
	99
);
