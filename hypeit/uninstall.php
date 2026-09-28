<?php
/**
 * Uninstall cleanup.
 *
 * By default this preserves all data (campaigns, responses, settings) so the
 * plugin can be safely deleted and reinstalled. Data is only removed if the
 * "Delete all data when the plugin is deleted" option was enabled under
 * HypeIt App → Data.
 *
 * @package HypeIt
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cp_app    = get_option( 'cp_app', array() );
$cp_delete = is_array( $cp_app ) && ! empty( $cp_app['delete_data'] );

// Always clear the scheduled cron events.
wp_clear_scheduled_hook( 'cp_notify_flush' );
wp_clear_scheduled_hook( 'cp_verify_resync' );

if ( ! $cp_delete ) {
	// Preserve everything else so reinstalling keeps campaigns and settings.
	return;
}

global $wpdb;

// Delete all campaign posts and their meta.
$cp_post_ids = get_posts(
	array(
		'post_type'   => 'cp_campaign',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( (array) $cp_post_ids as $cp_post_id ) {
	wp_delete_post( $cp_post_id, true );
}

// Delete all library bloggers and their meta.
$cp_blogger_ids = get_posts(
	array(
		'post_type'   => 'cp_blogger',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( (array) $cp_blogger_ids as $cp_blogger_id ) {
	wp_delete_post( $cp_blogger_id, true );
}

// Delete library taxonomy terms.
foreach ( array( 'cp_blogger_list', 'cp_blogger_tag' ) as $cp_tax ) {
	$cp_terms = get_terms(
		array(
			'taxonomy'   => $cp_tax,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $cp_terms ) ) {
		foreach ( $cp_terms as $cp_term_id ) {
			wp_delete_term( $cp_term_id, $cp_tax );
		}
	}
}

// Drop the bloggers table.
$cp_table = $wpdb->prefix . 'cp_bloggers';
$wpdb->query( "DROP TABLE IF EXISTS {$cp_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Remove options.
delete_option( 'cp_theme' );
delete_option( 'cp_app' );
delete_option( 'cp_onboard' );
delete_option( 'cp_verify' );
delete_option( 'cp_blocked_handles' );
delete_option( 'cp_titles_v2' );
delete_option( 'cp_db_version' );

// Remove app login tokens from all users.
delete_metadata( 'user', 0, '_cp_app_tokens', '', true );
