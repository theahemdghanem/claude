<?php
/**
 * Activation / deactivation and database schema.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Install {

	const DB_VERSION = '1.3.0';

	/**
	 * Activation callback.
	 */
	public static function activate() {
		self::create_tables();

		// Make sure rewrite rules for the client page and the app exist, then flush.
		CP_Post_Type::register();
		CP_Blogger_CPT::register();
		CP_Frontend::add_rewrite_rules();
		CP_PWA::add_rewrite_rules();
		CP_Onboarding::add_rewrite_rules();
		CP_Verify::add_rewrite_rules();
		flush_rewrite_rules();

		// Seed default categories once, if none exist.
		$existing = get_terms( array( 'taxonomy' => CP_Library::TAX_TAG, 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( ! is_wp_error( $existing ) && empty( $existing ) ) {
			foreach ( CP_Library::categories() as $cat ) {
				if ( ! term_exists( $cat, CP_Library::TAX_TAG ) ) {
					wp_insert_term( $cat, CP_Library::TAX_TAG );
				}
			}
		}

		CP_Notify::maybe_schedule();
		CP_Verify::maybe_schedule();
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
		CP_Notify::unschedule();
		CP_Verify::unschedule();
	}

	/**
	 * Create the bloggers/responses table.
	 */
	public static function create_tables() {
		global $wpdb;

		$table           = $wpdb->prefix . 'cp_bloggers';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			ig_account VARCHAR(255) NOT NULL DEFAULT '',
			ig_url VARCHAR(255) NOT NULL DEFAULT '',
			gender VARCHAR(20) NOT NULL DEFAULT '',
			followers BIGINT UNSIGNED NOT NULL DEFAULT 0,
			tags VARCHAR(255) NOT NULL DEFAULT '',
			city VARCHAR(100) NOT NULL DEFAULT '',
			area VARCHAR(100) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			extra_guests INT NOT NULL DEFAULT 0,
			created_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'cp_db_version', self::DB_VERSION );
	}

	/**
	 * Run schema upgrades if the stored version is behind.
	 * Called on plugins_loaded via CP_DB::maybe_upgrade().
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'cp_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}
}
