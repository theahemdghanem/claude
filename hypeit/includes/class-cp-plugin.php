<?php
/**
 * Plugin bootstrap.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var CP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return CP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		load_plugin_textdomain( 'hypeit', false, dirname( CP_BASENAME ) . '/languages' );

		CP_Install::maybe_upgrade();

		// Always active (front + admin) so rewrite rules and AJAX are registered.
		CP_Post_Type::init();
		CP_Blogger_CPT::init();
		CP_Onboarding::init();
		CP_Frontend::init();
		CP_Ajax::init();
		CP_REST::init();
		CP_PWA::init();
		CP_Notify::init();
		CP_Verify::init();
		CP_Photo::init();
		CP_Logo::init();
		CP_Splash::init();
		CP_Push::init();
		CP_Join::init();
		CP_Atrium::init();
		CP_IGSync::init();
		CP_Close::init();
		CP_Expiry::init();
		CP_Digest::init();
		CP_Roster::init();
		CP_Dupes::init();
		CP_Bloggers_UI::init();

		if ( is_admin() ) {
			CP_Admin::init();
			CP_App_Settings::init();
			CP_Lists::init();
			CP_Tags::init();
			CP_Insights::init();
			CP_Campaigns_UI::init();
		}
	}
}
