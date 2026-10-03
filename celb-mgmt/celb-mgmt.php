<?php
/**
 * Plugin Name:       CELB MGMT
 * Plugin URI:        https://ilike.agency
 * Description:       Celebrity management directory for iLike Agency: profiles, grid, carousel, individual pages, awards, galleries, social links, and a password-protected front-end self-submission portal.
 * Version:           2.9.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            iLike Agency
 * Author URI:        https://ilikeagency.co
 * Text Domain:       celb-mgmt
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELB_VERSION', '2.9.0' );
define( 'CELB_PATH', plugin_dir_path( __FILE__ ) );
define( 'CELB_URL', plugin_dir_url( __FILE__ ) );
define( 'CELB_CPT', 'celebrity' );

/* Talent Works Archive module. */
require_once CELB_PATH . 'includes/works.php';

/* Admin UI ("Studio"): celebrity list, celebrity + newsroom editors, settings. */
require_once CELB_PATH . 'includes/admin-studio.php';
require_once CELB_PATH . 'includes/admin-requests.php';

/* -------------------------------------------------------------------------
 * 1. CUSTOM POST TYPE
 * ---------------------------------------------------------------------- */

function celb_register_cpt() {
	$labels = array(
		'name'               => __( 'Celebrities', 'celb-mgmt' ),
		'singular_name'      => __( 'Celebrity', 'celb-mgmt' ),
		'menu_name'          => __( 'Celebrities', 'celb-mgmt' ),
		'add_new'            => __( 'Add New', 'celb-mgmt' ),
		'add_new_item'       => __( 'Add New Celebrity', 'celb-mgmt' ),
		'edit_item'          => __( 'Edit Celebrity', 'celb-mgmt' ),
		'new_item'           => __( 'New Celebrity', 'celb-mgmt' ),
		'view_item'          => __( 'View Celebrity', 'celb-mgmt' ),
		'search_items'       => __( 'Search Celebrities', 'celb-mgmt' ),
		'not_found'          => __( 'No celebrities found', 'celb-mgmt' ),
		'not_found_in_trash' => __( 'No celebrities found in Trash', 'celb-mgmt' ),
		'all_items'          => __( 'All Celebrities', 'celb-mgmt' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'has_archive'        => false,
		'show_in_rest'       => true, // enables the block/WYSIWYG editor for the biography
		'menu_icon'          => 'dashicons-star-filled',
		'menu_position'      => 25,
		'supports'           => array( 'title', 'thumbnail', 'revisions' ),
		'rewrite'            => array( 'slug' => 'celebrity', 'with_front' => false ), // SEO-friendly URLs
		'capability_type'    => 'post',
	);

	register_post_type( CELB_CPT, $args );
}
add_action( 'init', 'celb_register_cpt' );

/* Flush rewrite rules on activation / deactivation so single URLs work immediately. */
function celb_activate() {
	celb_register_cpt();
	celb_register_news_cpt();
	celb_rebuild_smartlinks();
	celb_register_smartlink_rules();
	celb_register_stars_rewrite();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'celb_activate' );

/* Pretty URL /our-stars for the standalone "Our Stars" page. */
function celb_page_slug( $key ) {
	$s   = function_exists( 'celb_get_settings' ) ? celb_get_settings() : array();
	$def = array( 'stars_slug' => 'our-stars', 'onb_slug' => 'talent-onboarding', 'pdata_slug' => 'personal-data', 'rateonb_slug' => 'rate-form' );
	$v   = isset( $s[ $key ] ) ? sanitize_title( $s[ $key ] ) : '';
	return '' !== $v ? $v : ( isset( $def[ $key ] ) ? $def[ $key ] : $key );
}
/* Pretty URLs for the standalone pages (slugs editable in Settings). */
function celb_register_stars_rewrite() {
	add_rewrite_rule( '^' . preg_quote( celb_page_slug( 'stars_slug' ), '/' ) . '/?$', 'index.php?celb_page=stars', 'top' );
	add_rewrite_rule( '^' . preg_quote( celb_page_slug( 'onb_slug' ), '/' ) . '/?$', 'index.php?celb_page=onboarding', 'top' );
	add_rewrite_rule( '^' . preg_quote( celb_page_slug( 'pdata_slug' ), '/' ) . '/?$', 'index.php?celb_page=personal', 'top' );
	add_rewrite_rule( '^' . preg_quote( celb_page_slug( 'rateonb_slug' ), '/' ) . '/([^/]+)/?$', 'index.php?celb_page=rateonb&celb_t=$matches[1]', 'top' );
}
add_action( 'init', 'celb_register_stars_rewrite' );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'celb_page';
	$vars[] = 'celb_t';
	return $vars;
} );
/* Changing a slug in Settings re-flushes rewrite rules on the next load. */
add_action( 'update_option_celb_settings', function () {
	delete_option( 'celb_rewrite_version' );
} );

function celb_deactivate() {
	flush_rewrite_rules();
	wp_clear_scheduled_hook( 'celb_daily_reminders' );
}
register_deactivation_hook( __FILE__, 'celb_deactivate' );

/* Rename the main editor prompt so it reads as the Biography field. */
function celb_editor_placeholder( $title, $post ) {
	if ( $post && CELB_CPT === $post->post_type ) {
		return __( 'Biography', 'celb-mgmt' );
	}
	return $title;
}
add_filter( 'enter_title_here', function ( $text, $post ) {
	if ( $post && CELB_CPT === $post->post_type ) {
		return __( 'Celebrity Name', 'celb-mgmt' );
	}
	return $text;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 2. SOCIAL PLATFORM CONFIG  (config-driven: add/remove platforms here)
 * ---------------------------------------------------------------------- */

function celb_social_platforms() {
	return array(
		'instagram' => 'Instagram',
		'threads'   => 'Threads',
		'facebook'  => 'Facebook',
		'x'         => 'X (Twitter)',
		'tiktok'    => 'TikTok',
		'youtube'   => 'YouTube',
		'snapchat'  => 'Snapchat',
		'imdb'      => 'IMDb',
		'website'   => 'Website',
	);
}

/* Inline SVG icons for each platform (kept lightweight, no icon-font dependency). */
function celb_social_icon( $key ) {
	$icons = array(
		'instagram' => '<path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16Zm0 1.8c-3.15 0-3.52.01-4.76.07-.86.04-1.32.18-1.63.3-.41.16-.7.35-1.01.66-.31.31-.5.6-.66 1.01-.12.31-.26.77-.3 1.63-.06 1.24-.07 1.61-.07 4.76s.01 3.52.07 4.76c.04.86.18 1.32.3 1.63.16.41.35.7.66 1.01.31.31.6.5 1.01.66.31.12.77.26 1.63.3 1.24.06 1.61.07 4.76.07s3.52-.01 4.76-.07c.86-.04 1.32-.18 1.63-.3.41-.16.7-.35 1.01-.66.31-.31.5-.6.66-1.01.12-.31.26-.77.3-1.63.06-1.24.07-1.61.07-4.76s-.01-3.52-.07-4.76c-.04-.86-.18-1.32-.3-1.63a2.7 2.7 0 0 0-.66-1.01 2.7 2.7 0 0 0-1.01-.66c-.31-.12-.77-.26-1.63-.3-1.24-.06-1.61-.07-4.76-.07Zm0 3.06a4.98 4.98 0 1 1 0 9.96 4.98 4.98 0 0 1 0-9.96Zm0 8.21a3.23 3.23 0 1 0 0-6.46 3.23 3.23 0 0 0 0 6.46Zm6.34-8.41a1.16 1.16 0 1 1-2.32 0 1.16 1.16 0 0 1 2.32 0Z"/>',
		'facebook'  => '<path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.91 3.78-3.91 1.1 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.44 2.91h-2.34V22c4.78-.76 8.43-4.92 8.43-9.94Z"/>',
		'x'         => '<path d="M17.53 3h3.07l-6.71 7.67L21.75 21h-5.6l-4.4-5.75L6.7 21H3.62l7.18-8.2L2.6 3h5.74l3.98 5.26L17.53 3Zm-1.08 16.2h1.7L7.53 4.7H5.7l10.75 14.5Z"/>',
		'tiktok'    => '<path d="M16.6 5.82A4.28 4.28 0 0 1 15.4 3h-3.07v12.27a2.45 2.45 0 1 1-2.45-2.45c.13 0 .26.01.39.03v-3.12a5.6 5.6 0 0 0-.39-.01 5.55 5.55 0 1 0 5.55 5.55V9.4a7.3 7.3 0 0 0 4.28 1.37V7.7a4.28 4.28 0 0 1-3.1-1.88Z"/>',
		'youtube'   => '<path d="M23 12s0-3.36-.43-4.97a2.6 2.6 0 0 0-1.83-1.83C19.13 4.77 12 4.77 12 4.77s-7.13 0-8.74.43A2.6 2.6 0 0 0 1.43 7.03C1 8.64 1 12 1 12s0 3.36.43 4.97a2.6 2.6 0 0 0 1.83 1.83c1.61.43 8.74.43 8.74.43s7.13 0 8.74-.43a2.6 2.6 0 0 0 1.83-1.83C23 15.36 23 12 23 12Zm-13 3.2V8.8L15.6 12 10 15.2Z"/>',
		'snapchat'  => '<path d="M12 2.04c2.6 0 4.7 2.06 4.85 4.66.04.7 0 1.4.02 2.1.36.2.78.32 1.2.24.36-.06.7-.24 1.04-.36.27-.1.6-.04.74.22.16.3.02.64-.26.84-.46.33-1.02.5-1.56.66-.3.08-.6.16-.66.5-.05.3.1.58.25.84.6 1.04 1.5 1.86 2.6 2.34.3.13.46.42.36.7-.12.34-.5.46-.82.56-.5.16-1.04.2-1.52.42-.2.36-.16.84-.5 1.1-.34.16-.72.04-1.08-.02-.5-.08-1.04-.13-1.5.12-.42.24-.74.66-1.18.9-1 .56-2.3.56-3.3 0-.44-.24-.76-.66-1.18-.9-.46-.25-1-.2-1.5-.12-.36.06-.74.18-1.08.02-.34-.26-.3-.74-.5-1.1-.48-.22-1.02-.26-1.52-.42-.32-.1-.7-.22-.82-.56-.1-.28.06-.57.36-.7 1.1-.48 2-1.3 2.6-2.34.15-.26.3-.54.25-.84-.06-.34-.36-.42-.66-.5-.54-.16-1.1-.33-1.56-.66-.28-.2-.42-.54-.26-.84.14-.26.47-.32.74-.22.34.12.68.3 1.04.36.42.08.84-.04 1.2-.24.02-.7-.02-1.4.02-2.1C7.3 4.1 9.4 2.04 12 2.04Z"/>',
		'threads'   => '<path d="M12.19 24h-.01c-3.58-.02-6.33-1.2-8.18-3.51C2.35 18.44 1.5 15.59 1.47 12.01v-.02c.03-3.58.88-6.43 2.53-8.48C5.85 1.2 8.6.02 12.18 0h.01c2.75.02 5.04.72 6.83 2.1 1.68 1.29 2.86 3.13 3.51 5.46l-2.04.57c-1.1-3.96-3.9-5.98-8.3-6.01-2.91.02-5.11.94-6.54 2.72C4.31 6.5 3.62 8.91 3.59 12c.03 3.09.72 5.5 2.06 7.16 1.43 1.78 3.63 2.7 6.54 2.72 2.62-.02 4.36-.63 5.8-2.05 1.65-1.61 1.62-3.59 1.09-4.8-.31-.71-.87-1.3-1.63-1.75-.19 1.35-.62 2.45-1.28 3.27-.89 1.1-2.14 1.7-3.73 1.79-1.2.06-2.36-.22-3.26-.8-1.06-.69-1.69-1.74-1.75-2.96-.07-1.19.41-2.29 1.33-3.08.88-.76 2.12-1.21 3.58-1.29 1.08-.06 2.09-.01 3.02.14-.13-.74-.38-1.33-.75-1.76-.51-.59-1.3-.89-2.35-.9h-.03c-.84 0-1.98.23-2.71 1.4l-1.73-1.17c.98-1.56 2.56-2.42 4.58-2.42h.05c3.38.02 5.39 2.08 5.59 5.69.11.05.23.1.34.15 1.56.74 2.71 1.84 3.31 3.21.83 1.9.91 5.01-1.62 7.49C19.85 23.14 17.5 24 14.18 24h-1.99zm1.01-11.16c-.25 0-.51.01-.78.02-1.83.1-2.98.83-2.98 1.89 0 .94 1.04 1.39 2.04 1.39h.07c1.79-.05 2.78-1.12 2.98-3.21a8.9 8.9 0 0 0-1.33-.09z"/>',
		'imdb'      => '<text x="12" y="15.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="8.4" letter-spacing="-0.3">IMDb</text>',
		'website'   => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 6h-2.95a15.6 15.6 0 0 0-1.38-3.56A8.03 8.03 0 0 1 18.93 8ZM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96ZM4.26 14a7.96 7.96 0 0 1 0-4h3.38a16.6 16.6 0 0 0 0 4H4.26Zm.81 2h2.95c.34 1.27.8 2.46 1.38 3.56A8.03 8.03 0 0 1 5.07 16Zm2.95-8H5.07a8.03 8.03 0 0 1 4.33-3.56A15.6 15.6 0 0 0 8.02 8ZM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82A14.9 14.9 0 0 1 12 19.96ZM14.34 14H9.66a14.5 14.5 0 0 1 0-4h4.68a14.5 14.5 0 0 1 0 4Zm.28 5.56c.58-1.1 1.04-2.29 1.38-3.56h2.95a8.03 8.03 0 0 1-4.33 3.56ZM16.36 14a16.6 16.6 0 0 0 0-4h3.38a7.96 7.96 0 0 1 0 4h-3.38Z"/>',
	);
	$path = isset( $icons[ $key ] ) ? $icons[ $key ] : $icons['website'];
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">' . $path . '</svg>';
}

/* -------------------------------------------------------------------------
 * 3. META BOXES
 * ---------------------------------------------------------------------- */

/* Admin UI for this section lives in includes/admin-studio.php. */

/* Shared list of suggested production types (free text still allowed). */
function celb_project_types() {
	return array(
		'TV Series', 'TV Show', 'Movie', 'Play', 'Theatre', 'Short Film',
		'Web Series', 'Series', 'Podcast', 'Advertisement', 'Commercial',
		'Campaign', 'Documentary', 'Music Video', 'Presenting', 'Hosting',
		'Radio', 'Voice Over', 'Brand Ambassador', 'Endorsement', 'Interview', 'Event',
	);
}
function celb_project_types_datalist() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	echo '<datalist id="celb-project-types">';
	foreach ( celb_project_types() as $t ) {
		echo '<option value="' . esc_attr( $t ) . '"></option>';
	}
	echo '</datalist>';
}

/* -------------------------------------------------------------------------
 * 4. SAVE META
 * ---------------------------------------------------------------------- */

function celb_save_meta( $post_id ) {
	if ( ! isset( $_POST['celb_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_meta_nonce'] ), 'celb_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== CELB_CPT ) {
		return;
	}

	// Details.
	update_post_meta( $post_id, '_celb_role', isset( $_POST['celb_role'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_role'] ) ) : '' );
	$valid_cats = array_keys( celb_roster_cat_defs() );
	$cats_in    = array();
	if ( isset( $_POST['celb_category'] ) && is_array( $_POST['celb_category'] ) ) {
		foreach ( wp_unslash( $_POST['celb_category'] ) as $c ) {
			$c = sanitize_key( $c );
			$c = 'music' === $c ? 'musician' : $c;
			if ( in_array( $c, $valid_cats, true ) && ! in_array( $c, $cats_in, true ) ) {
				$cats_in[] = $c;
			}
			if ( count( $cats_in ) >= 2 ) {
				break;
			}
		}
	}
	update_post_meta( $post_id, '_celb_category', $cats_in );
	update_post_meta( $post_id, '_celb_lead', empty( $_POST['celb_lead'] ) ? '' : '1' );
	update_post_meta( $post_id, '_celb_nationality', isset( $_POST['celb_nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_nationality'] ) ) : '' );
	update_post_meta( $post_id, '_celb_card_img', isset( $_POST['celb_card_img'] ) ? (int) $_POST['celb_card_img'] : 0 );

	// Birthdate (validate YYYY-MM-DD) + year visibility.
	$bd = isset( $_POST['celb_birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_birthdate'] ) ) : '';
	if ( $bd && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bd ) ) {
		$bd = '';
	}
	update_post_meta( $post_id, '_celb_birthdate', $bd );
	update_post_meta( $post_id, '_celb_show_year', empty( $_POST['celb_show_year'] ) ? '' : '1' );
	update_post_meta( $post_id, '_celb_locked', empty( $_POST['celb_locked'] ) ? '' : '1' );

	// Biography (rich text).
	if ( isset( $_POST['celb_bio'] ) ) {
		update_post_meta( $post_id, '_celb_bio', wp_kses_post( wp_unslash( $_POST['celb_bio'] ) ) );
	}

	// Profile + hero images.
	update_post_meta( $post_id, '_celb_profile', isset( $_POST['celb_profile'] ) ? absint( $_POST['celb_profile'] ) : 0 );
	update_post_meta( $post_id, '_celb_hero_desktop', isset( $_POST['celb_hero_desktop'] ) ? absint( $_POST['celb_hero_desktop'] ) : 0 );
	update_post_meta( $post_id, '_celb_hero_mobile', isset( $_POST['celb_hero_mobile'] ) ? absint( $_POST['celb_hero_mobile'] ) : 0 );

	// Social.
	foreach ( celb_social_platforms() as $key => $label ) {
		$field = 'celb_social_' . $key;
		$value = isset( $_POST[ $field ] ) ? esc_url_raw( trim( wp_unslash( $_POST[ $field ] ) ) ) : '';
		update_post_meta( $post_id, '_celb_social_' . $key, $value );
	}

	// Career History.
	$career = array();
	if ( isset( $_POST['celb_career'] ) && is_array( $_POST['celb_career'] ) ) {
		foreach ( wp_unslash( $_POST['celb_career'] ) as $row ) {
			$project    = isset( $row['project'] ) ? sanitize_text_field( $row['project'] ) : '';
			$role       = isset( $row['role'] ) ? sanitize_text_field( $row['role'] ) : '';
			$year       = isset( $row['year'] ) ? absint( $row['year'] ) : 0;
			$type       = isset( $row['type'] ) ? sanitize_text_field( $row['type'] ) : '';
			$special    = empty( $row['special'] ) ? 0 : 1;
			$production = empty( $row['production'] ) ? 0 : 1;
			if ( '' === $project && '' === $role && 0 === $year ) {
				continue; // skip empty rows
			}
			$career[] = array(
				'project'    => $project,
				'role'       => $role,
				'year'       => $year,
				'type'       => $type,
				'special'    => $special,
				'production' => $production,
			);
		}
	}
	update_post_meta( $post_id, '_celb_career', $career );

	// Awards.
	$awards = array();
	if ( isset( $_POST['celb_awards'] ) && is_array( $_POST['celb_awards'] ) ) {
		foreach ( wp_unslash( $_POST['celb_awards'] ) as $row ) {
			$festival = isset( $row['festival'] ) ? sanitize_text_field( $row['festival'] ) : '';
			$title    = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$project  = isset( $row['project'] ) ? sanitize_text_field( $row['project'] ) : '';
			$year     = isset( $row['year'] ) ? absint( $row['year'] ) : 0;
			$location = isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '';
			if ( '' === $festival && '' === $title && '' === $project && 0 === $year && '' === $location ) {
				continue;
			}
			$awards[] = array(
				'festival' => $festival,
				'title'    => $title,
				'project'  => $project,
				'year'     => $year,
				'location' => $location,
			);
		}
	}
	update_post_meta( $post_id, '_celb_awards', $awards );

	// Gallery.
	$gallery = array();
	if ( isset( $_POST['celb_gallery'] ) ) {
		$raw     = explode( ',', sanitize_text_field( wp_unslash( $_POST['celb_gallery'] ) ) );
		$gallery = array_values( array_filter( array_map( 'absint', $raw ) ) );
	}
	update_post_meta( $post_id, '_celb_gallery', $gallery );

	// Videos.
	$videos = array();
	if ( isset( $_POST['celb_videos'] ) && is_array( $_POST['celb_videos'] ) ) {
		foreach ( wp_unslash( $_POST['celb_videos'] ) as $row ) {
			$vname = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$vurl  = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			if ( '' === $vurl ) {
				continue;
			}
			$videos[] = array(
				'name'  => $vname,
				'url'   => $vurl,
				'cover' => isset( $row['cover'] ) ? absint( $row['cover'] ) : 0,
			);
		}
	}
	update_post_meta( $post_id, '_celb_videos', $videos );
	update_post_meta( $post_id, '_celb_videos_title', isset( $_POST['celb_videos_title'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_videos_title'] ) ) : '' );
}
add_action( 'save_post_' . CELB_CPT, 'celb_save_meta' );

/* -------------------------------------------------------------------------
 * 5. DATA HELPERS (display side)
 * ---------------------------------------------------------------------- */

/* Career: under-production roles first, then newest -> oldest year. */
function celb_get_career( $post_id ) {
	$rows = get_post_meta( $post_id, '_celb_career', true );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}
	usort( $rows, function ( $a, $b ) {
		$pa = empty( $a['production'] ) ? 0 : 1;
		$pb = empty( $b['production'] ) ? 0 : 1;
		if ( $pa !== $pb ) {
			return $pb - $pa; // production first
		}
		return ( (int) $b['year'] ) <=> ( (int) $a['year'] );
	} );
	return $rows;
}

/* Awards sorted newest -> oldest year. */
function celb_get_awards( $post_id ) {
	$rows = get_post_meta( $post_id, '_celb_awards', true );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}
	usort( $rows, function ( $a, $b ) {
		return ( (int) $b['year'] ) <=> ( (int) $a['year'] );
	} );
	return $rows;
}

function celb_get_gallery( $post_id ) {
	$ids = get_post_meta( $post_id, '_celb_gallery', true );
	return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
}

/* Returns only the populated social links. */
function celb_get_socials( $post_id ) {
	$out = array();
	foreach ( celb_social_platforms() as $key => $label ) {
		$url = get_post_meta( $post_id, '_celb_social_' . $key, true );
		if ( $url ) {
			$out[ $key ] = array( 'label' => $label, 'url' => $url );
		}
	}
	return $out;
}

/* Subtitle: "Role / Nationality" with graceful fallback. */
function celb_subtitle( $post_id ) {
	$role        = get_post_meta( $post_id, '_celb_role', true );
	$nationality = get_post_meta( $post_id, '_celb_nationality', true );
	$parts       = array_filter( array( $role, $nationality ) );
	return implode( ' / ', $parts );
}

/* Biography content (dedicated field first, falls back to legacy post body). */
function celb_get_bio( $post_id ) {
	$bio = get_post_meta( $post_id, '_celb_bio', true );
	if ( '' === $bio ) {
		$bio = get_post_field( 'post_content', $post_id );
	}
	return $bio;
}

/* Birthday data: array( display, is_birthday ) or null when unset/invalid. */
function celb_get_birthday( $post_id ) {
	$bd = get_post_meta( $post_id, '_celb_birthdate', true );
	if ( ! $bd || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $bd, $m ) ) {
		return null;
	}
	$year  = (int) $m[1];
	$month = (int) $m[2];
	$day   = (int) $m[3];
	if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
		return null;
	}
	$show_year = ( '1' === get_post_meta( $post_id, '_celb_show_year', true ) );
	$ts        = mktime( 0, 0, 0, $month, $day, $year );
	$display   = date_i18n( $show_year ? 'j F Y' : 'j F', $ts );
	$is_today  = ( (int) current_time( 'n' ) === $month && (int) current_time( 'j' ) === $day );

	return array( 'display' => $display, 'is_birthday' => $is_today );
}

/* -------------------------------------------------------------------------
 * 6. CARD RENDERER (shared by grid + carousel)
 * ---------------------------------------------------------------------- */

function celb_render_card( $post_id ) {
	// Priority: dedicated Profile Image > Featured Image > Desktop Hero.
	$profile = (int) get_post_meta( $post_id, '_celb_profile', true );
	$img     = $profile ? wp_get_attachment_image_url( $profile, 'large' ) : '';
	if ( ! $img ) {
		$img = get_the_post_thumbnail_url( $post_id, 'large' );
	}
	if ( ! $img ) {
		$hero = (int) get_post_meta( $post_id, '_celb_hero_desktop', true );
		$img  = $hero ? wp_get_attachment_image_url( $hero, 'large' ) : '';
	}
	$name     = get_the_title( $post_id );
	$subtitle = celb_subtitle( $post_id );
	$link     = get_permalink( $post_id );
	$locked   = (bool) get_post_meta( $post_id, '_celb_locked', true );

	ob_start();
	if ( $locked ) {
		echo '<div class="celb-card celb-card--locked">';
	} else {
		echo '<a class="celb-card" href="' . esc_url( $link ) . '">';
	}
	?>
		<div class="celb-card-media"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : ''; ?>>
			<div class="celb-card-overlay"></div>
			<div class="celb-card-text">
				<span class="celb-card-name"><?php echo esc_html( $name ); ?></span>
				<?php if ( $subtitle ) : ?>
					<span class="celb-card-sub"><?php echo esc_html( $subtitle ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php
	echo $locked ? '</div>' : '</a>';
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * 7. SHORTCODES
 * ---------------------------------------------------------------------- */

/* Roster category ('actors' | 'rising' | 'music'); falls back to a guess from role text. */
function celb_roster_cat_defs() {
	return array(
		'actors'   => __( 'Actors', 'celb-mgmt' ),
		'actress'  => __( 'Actress', 'celb-mgmt' ),
		'host'     => __( 'Host', 'celb-mgmt' ),
		'director' => __( 'Director', 'celb-mgmt' ),
		'writer'   => __( 'Writer', 'celb-mgmt' ),
		'producer' => __( 'Producer', 'celb-mgmt' ),
		'musician' => __( 'Musician', 'celb-mgmt' ),
		'rising'   => __( 'Rising Stars', 'celb-mgmt' ),
	);
}
/* Returns up to two roster categories for a celebrity (auto-guesses if none set). */
function celb_roster_categories( $id ) {
	$stored = get_post_meta( $id, '_celb_category', true );
	$valid  = array_keys( celb_roster_cat_defs() );
	$out    = array();
	$list   = is_array( $stored ) ? $stored : ( '' !== $stored ? array( $stored ) : array() );
	foreach ( $list as $c ) {
		$c = 'music' === $c ? 'musician' : $c; // migrate old "Music & Hosts"
		if ( in_array( $c, $valid, true ) && ! in_array( $c, $out, true ) ) {
			$out[] = $c;
		}
	}
	if ( $out ) {
		return array_slice( $out, 0, 2 );
	}
	$role = strtolower( (string) get_post_meta( $id, '_celb_role', true ) );
	if ( false !== strpos( $role, 'rising' ) ) { return array( 'rising' ); }
	if ( preg_match( '/actress/', $role ) ) { return array( 'actress' ); }
	if ( preg_match( '/singer|musician|music|composer|vocal|\bband\b|\bdj\b/', $role ) ) { return array( 'musician' ); }
	if ( preg_match( '/host|present|anchor|\bmc\b/', $role ) ) { return array( 'host' ); }
	if ( preg_match( '/director/', $role ) ) { return array( 'director' ); }
	if ( preg_match( '/writer|screenwriter|author/', $role ) ) { return array( 'writer' ); }
	if ( preg_match( '/producer/', $role ) ) { return array( 'producer' ); }
	return array( 'actors' );
}
function celb_roster_category( $id ) {
	$c = celb_roster_categories( $id );
	return $c ? $c[0] : 'actors';
}
function celb_roster_cat_label( $c ) {
	$m = celb_roster_cat_defs();
	return isset( $m[ $c ] ) ? $m[ $c ] : $m['actors'];
}
/* Two-letter monogram (first + last word initials), multibyte-safe. */
function celb_monogram( $name ) {
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $name ) {
		return '';
	}
	$parts = preg_split( '/\s+/', $name );
	$sub   = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
	if ( count( $parts ) > 1 ) {
		$out = call_user_func( $sub, $parts[0], 0, 1 ) . call_user_func( $sub, $parts[ count( $parts ) - 1 ], 0, 1 );
	} else {
		$out = call_user_func( $sub, $parts[0], 0, 2 );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out ) : strtoupper( $out );
}

/* Roster card (Casting-Book style: monogram/photo tile, name + role beneath). */
function celb_render_roster_card( $post_id ) {
	$profile = (int) get_post_meta( $post_id, '_celb_profile', true );
	$img     = $profile ? wp_get_attachment_image_url( $profile, 'large' ) : '';
	if ( ! $img ) {
		$img = get_the_post_thumbnail_url( $post_id, 'large' );
	}
	$name   = get_the_title( $post_id );
	$role   = get_post_meta( $post_id, '_celb_role', true );
	$cat    = implode( ' ', celb_roster_categories( $post_id ) );
	$lead   = (bool) get_post_meta( $post_id, '_celb_lead', true );
	$locked = (bool) get_post_meta( $post_id, '_celb_locked', true );
	$link   = get_permalink( $post_id );

	ob_start();
	if ( $locked ) {
		echo '<div class="celb-rcard celb-rcard--locked" data-cat="' . esc_attr( $cat ) . '">';
	} else {
		echo '<a class="celb-rcard" href="' . esc_url( $link ) . '" data-cat="' . esc_attr( $cat ) . '">';
	}
	echo '<div class="celb-rcard-media">';
	if ( $img ) {
		echo '<img class="celb-rcard-img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
	} else {
		echo '<div class="celb-rcard-mono-wrap"><span class="celb-rcard-mono">' . esc_html( celb_monogram( $name ) ) . '</span></div>';
	}
	echo '<span class="celb-rcard-scrim"></span>';
	if ( $lead ) {
		echo '<span class="celb-rcard-badge">' . esc_html__( 'Lead', 'celb-mgmt' ) . '</span>';
	}
	if ( ! $locked ) {
		echo '<span class="celb-rcard-go" aria-hidden="true">&#8599;</span>';
	}
	echo '<div class="celb-rcard-info"><span class="celb-rcard-name">' . esc_html( $name ) . '</span>';
	if ( $role ) {
		echo '<span class="celb-rcard-role">' . esc_html( $role ) . '</span>';
	}
	echo '</div>';
	echo '</div>';
	echo $locked ? '</div>' : '</a>';
	return ob_get_clean();
}

/* One-time inline filter script for the roster. */
function celb_roster_filter_script() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<script>(function(){function init(root){var pills=root.querySelectorAll(".celb-rfilter");var cards=root.querySelectorAll(".celb-rcard");Array.prototype.forEach.call(pills,function(p){p.addEventListener("click",function(){Array.prototype.forEach.call(pills,function(x){x.classList.remove("is-active");});p.classList.add("is-active");var f=p.getAttribute("data-filter");Array.prototype.forEach.call(cards,function(c){var cats=(c.getAttribute("data-cat")||"").split(" ");var show=(f==="all")||(cats.indexOf(f)!==-1);if(show){c.classList.remove("celb-hide");}else{c.classList.add("celb-hide");}});});});}'
		. 'function boot(){Array.prototype.forEach.call(document.querySelectorAll(".celb-roster"),init);}'
		. 'if(document.readyState!=="loading"){boot();}else{document.addEventListener("DOMContentLoaded",boot);}'
		. '})();</script>';
}

/* [CLEB_celebrities] — Casting-Book roster: header, category filters, monogram grid. */
function celb_shortcode_grid( $atts ) {
	celb_ensure_frontend_assets( true );
	$rs   = celb_get_settings();
	$atts = shortcode_atts( array(
		'limit'   => -1,
		'header'  => 'yes',
		'eyebrow' => $rs['roster_eyebrow'],
		'title'   => $rs['roster_title'],
		'intro'   => $rs['roster_intro'],
		'filters' => 'yes',
		'orderby' => 'lead_rand', // lead_rand (leads pinned, rest shuffled) | rand | lead | title
		'full'    => 'yes',  // break out to full viewport width
		'cols'    => 'auto', // 'auto' fills the row; or a fixed number
	), $atts, 'CLEB_celebrities' );

	$q = new WP_Query( array(
		'post_type'      => CELB_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['limit'],
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}

	$ids = wp_list_pluck( $q->posts, 'ID' );
	wp_reset_postdata();

	if ( 'rand' === $atts['orderby'] ) {
		shuffle( $ids );
	} elseif ( 'lead' === $atts['orderby'] ) {
		usort( $ids, function ( $a, $b ) {
			$la = get_post_meta( $a, '_celb_lead', true ) ? 1 : 0;
			$lb = get_post_meta( $b, '_celb_lead', true ) ? 1 : 0;
			if ( $la !== $lb ) {
				return $lb - $la;
			}
			return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
		} );
	} else {
		// Default (lead_rand): Lead talent always first, everyone else shuffled.
		$leads = array();
		$rest  = array();
		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, '_celb_lead', true ) ) {
				$leads[] = $id;
			} else {
				$rest[] = $id;
			}
		}
		shuffle( $leads );
		shuffle( $rest );
		$ids = array_merge( $leads, $rest );
	}

	$present = array();
	foreach ( $ids as $id ) {
		foreach ( celb_roster_categories( $id ) as $c ) {
			$present[ $c ] = true;
		}
	}

	ob_start();
	$rose_accent = celb_accent();
	echo '<div class="celb-scope celb-roster' . ( 'no' === $atts['full'] ? '' : ' is-full' ) . '" style="--rose-accent:' . esc_attr( $rose_accent ) . '">';

	if ( 'yes' === $atts['header'] ) {
		echo '<div class="celb-roster-head">';
		if ( '' !== $atts['eyebrow'] ) {
			echo '<span class="celb-roster-eyebrow">' . esc_html( $atts['eyebrow'] ) . '</span>';
		}
		if ( '' !== $atts['title'] ) {
			echo '<h2 class="celb-roster-title">' . esc_html( $atts['title'] ) . '</h2>';
		}
		if ( '' !== $atts['intro'] ) {
			echo '<div class="celb-roster-introwrap"><span class="celb-roster-dot"></span><p class="celb-roster-intro">' . esc_html( $atts['intro'] ) . '</p></div>';
		}
		echo '</div>';
	}

	if ( 'yes' === $atts['filters'] ) {
		echo '<div class="celb-roster-filters">';
		echo '<button type="button" class="celb-rfilter is-active" data-filter="all">' . esc_html__( 'All', 'celb-mgmt' ) . '</button>';
		foreach ( array_keys( celb_roster_cat_defs() ) as $c ) {
			if ( ! empty( $present[ $c ] ) ) {
				echo '<button type="button" class="celb-rfilter" data-filter="' . esc_attr( $c ) . '">' . esc_html( celb_roster_cat_label( $c ) ) . '</button>';
			}
		}
		echo '</div>';
	}

	$grid_style = ( ctype_digit( (string) $atts['cols'] ) && (int) $atts['cols'] > 0 )
		? ' style="--roster-cols:repeat(' . (int) $atts['cols'] . ',minmax(0,1fr))"'
		: '';
	echo '<div class="celb-roster-tiles"' . $grid_style . '>';
	foreach ( $ids as $id ) {
		echo celb_render_roster_card( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '</div>';
	echo celb_roster_filter_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	return ob_get_clean();
}
add_shortcode( 'CLEB_celebrities', 'celb_shortcode_grid' );

/* [CLEB_celebrities_carousel] — compact homepage carousel, randomized each load. */
function celb_shortcode_carousel( $atts ) {
	celb_ensure_frontend_assets( true );
	$atts = shortcode_atts( array(
		'limit'         => 12,
		'viewall'       => '',
		'viewall_label' => '',
	), $atts, 'CLEB_celebrities_carousel' );

	$q = new WP_Query( array(
		'post_type'      => CELB_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['limit'],
		'orderby'        => 'rand',
		'no_found_rows'  => true,
	) );

	if ( ! $q->have_posts() ) {
		return '';
	}

	$s        = celb_get_settings();
	$va_url   = '' !== $atts['viewall'] ? $atts['viewall'] : ( ! empty( $s['carousel_viewall'] ) ? $s['carousel_viewall_url'] : '' );
	$va_label = '' !== $atts['viewall_label'] ? $atts['viewall_label'] : $s['carousel_viewall_label'];

	ob_start();
	echo '<div class="celb-scope celb-carousel-wrap">';
	echo '<div class="celb-carousel" data-celb-carousel>';
	echo '<button type="button" class="celb-carousel-nav celb-prev" aria-label="Previous">&#8249;</button>';
	echo '<div class="celb-carousel-track">';
	while ( $q->have_posts() ) {
		$q->the_post();
		echo '<div class="celb-carousel-cell">' . celb_render_roster_card( get_the_ID() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '<button type="button" class="celb-carousel-nav celb-next" aria-label="Next">&#8250;</button>';
	echo '</div>'; // .celb-carousel

	if ( $va_url ) {
		echo '<div class="celb-carousel-cta"><a class="celb-viewall" href="' . esc_url( $va_url ) . '">' . esc_html( $va_label ) . '</a></div>';
	}

	echo '</div>'; // .celb-carousel-wrap
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'CLEB_celebrities_carousel', 'celb_shortcode_carousel' );

/* -------------------------------------------------------------------------
 * 8. SINGLE PROFILE TEMPLATE
 * ---------------------------------------------------------------------- */

function celb_single_template( $template ) {
	if ( is_singular( CELB_CPT ) ) {
		$custom = CELB_PATH . 'templates/single-celebrity.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	if ( is_singular( 'celeb_news' ) ) {
		$custom = CELB_PATH . 'templates/single-celeb_news.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'template_include', 'celb_single_template' );

/* Cache-proof: pin the full-bleed article hero flush against the site header,
   whatever theme wrapper/padding sits in between. Emitted inline (not in a
   cached JS file) and scoped to the news article page. */
function celb_news_flush_script() {
	if ( ! is_singular( 'celeb_news' ) ) {
		return;
	}
	?>
	<script>
	(function () {
		function flush() {
			var a = document.querySelector('.celb-scope.celb-article');
			if (!a) { return; }
			// 1) Strip top spacing from the hero and EVERY ancestor up to <body>,
			//    and the bottom margin of anything sitting above it. No theme
			//    detection needed - this closes the gap wherever it lives.
			var el = a;
			while (el && el !== document.documentElement) {
				el.style.marginTop = '0px';
				if (el !== a) { el.style.paddingTop = '0px'; }
				var prev = el.previousElementSibling;
				while (prev) { prev.style.marginBottom = '0px'; prev = prev.previousElementSibling; }
				if (el === document.body) { break; }
				el = el.parentElement;
			}
			// 2) Finisher: if any gap to the site header remains, pin the hero to it.
			var header = document.querySelector('.elementor-location-header, [data-elementor-type="header"], #header-outer, .site-header, header[role="banner"], #masthead, #header');
			if (header && !header.contains(a)) {
				var sY = window.pageYOffset || document.documentElement.scrollTop || 0;
				var hpos = getComputedStyle(header).position;
				var target = (hpos === 'fixed' || hpos === 'sticky')
					? header.getBoundingClientRect().height
					: header.getBoundingClientRect().bottom + sY;
				var gap = (a.getBoundingClientRect().top + sY) - target;
				if (gap > 1) { a.style.marginTop = (-gap) + 'px'; }
			}
		}
		function run() { flush(); }
		if (document.readyState !== 'loading') { run(); } else { document.addEventListener('DOMContentLoaded', run); }
		window.addEventListener('load', run);
		window.addEventListener('resize', function () { setTimeout(run, 100); });
		[80, 200, 400, 800, 1500, 2500].forEach(function (t) { setTimeout(run, t); });
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'celb_news_flush_script', 99 );

/* Locked profiles are hidden from visitors (still viewable by editors). */
function celb_block_locked_single() {
	if ( ! is_singular( CELB_CPT ) ) {
		return;
	}
	$id = get_queried_object_id();
	if ( $id && get_post_meta( $id, '_celb_locked', true ) && ! current_user_can( 'edit_post', $id ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'celb_block_locked_single' );

/* -------------------------------------------------------------------------
 * 9. ASSETS
 * ---------------------------------------------------------------------- */

/* Frontend: load only where needed (single profile, or a page containing a shortcode). */
function celb_enqueue_frontend() {
	static $enqueued = false;
	$load = is_singular( CELB_CPT ) || is_singular( 'celeb_news' );

	if ( ! $load && is_singular() ) {
		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, 'CLEB_celebrities' ) || has_shortcode( $post->post_content, 'CLEB_celebrities_carousel' ) || has_shortcode( $post->post_content, 'CLEB_submit' ) || has_shortcode( $post->post_content, 'CLEB_newsroom' ) || has_shortcode( $post->post_content, 'CLEB_newsroom_carousel' ) ) ) {
			$load = true;
		}
	}

	/**
	 * Allow themes/builders to force-load assets (e.g. when shortcodes are
	 * placed inside widgets or page-builder modules that bypass post_content).
	 */
	$load = apply_filters( 'celb_load_frontend_assets', $load );

	if ( ! $load ) {
		return;
	}
	if ( $enqueued ) {
		return;
	}
	$enqueued = true;

	wp_enqueue_style(
		'celb-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@300;400;500;600&family=Cairo:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'celb-frontend', CELB_URL . 'assets/celb-frontend.css', array(), CELB_VERSION );

	// Casting-Book roster typography — only where the [CLEB_celebrities] grid is used.
	$roster_here = is_singular( CELB_CPT );
	if ( ! $roster_here && is_singular() ) {
		$rp = get_post();
		$roster_here = $rp && ( has_shortcode( $rp->post_content, 'CLEB_celebrities' ) || has_shortcode( $rp->post_content, 'CLEB_celebrities_carousel' ) );
	}
	// Custom Theme font (optional). Without it the plugin inherits the theme's fonts.
	$ct_font = celb_get_settings();
	if ( ! empty( $ct_font['font_url'] ) ) {
		wp_enqueue_style( 'celb-custom-font', $ct_font['font_url'], array(), null );
	}

	// Apply saved settings (accent colour, columns, carousel size, profile theme).
	$s      = celb_get_settings();
	$inline = sprintf(
		'.celb-scope{--celb-gold:%1$s;--celb-cn:%2$d;}@media(min-width:783px){.celb-grid{grid-template-columns:repeat(%3$d,1fr);}}',
		esc_attr( celb_accent() ),
		(int) $s['carousel_items'],
		(int) $s['grid_cols']
	);
	$inline .= celb_custom_theme_vars_css( '.celb-scope' );
	if ( 'dark' === $s['theme'] ) {
		$inline .= '.celb-scope.celb-single{--celb-surface:#0a0a0a;--celb-text:#f4f1ea;--celb-muted:rgba(244,241,234,.55);--celb-line:rgba(153,153,153,.22);background:#000;}';
	}
	// Critical carousel styles emitted inline so they apply even when a CDN /
	// page cache is still serving an older copy of celb-frontend.css.
	$inline .= '.celb-card{container-type:inline-size !important;}'
		. '.celb-card-overlay{background:linear-gradient(to top,rgba(0,0,0,.5) 0%,rgba(0,0,0,.1) 32%,rgba(0,0,0,0) 55%) !important;}'
		. '.celb-card-text{padding:clamp(32px,14cqw,58px) clamp(12px,5cqw,22px) clamp(13px,5cqw,20px) !important;background:linear-gradient(to top,rgba(0,0,0,.9) 0%,rgba(0,0,0,.62) 32%,rgba(0,0,0,.22) 62%,rgba(0,0,0,0) 100%) !important;}'
		. '@container (max-width:360px){.celb-card-text{padding-top:clamp(40px,18cqw,90px) !important;background:linear-gradient(to top,rgba(0,0,0,.97) 0%,rgba(0,0,0,.95) 55%,rgba(0,0,0,.8) 78%,rgba(0,0,0,.35) 92%,rgba(0,0,0,0) 100%) !important;}}'
		. '.celb-card-name{font-size:clamp(1rem,6.4cqw,1.5rem) !important;line-height:1.04 !important;}'
		. '.celb-card-sub{font-size:clamp(.56rem,2.4cqw,.7rem) !important;letter-spacing:.12em !important;line-height:1.3 !important;margin-top:clamp(3px,1.3cqw,6px) !important;}'
		. '.celb-carousel-cta{text-align:center !important;margin-top:clamp(22px,3vw,38px) !important;}'
		. '.celb-viewall{display:inline-block !important;width:auto !important;font-family:var(--celb-font-body);font-size:.76rem !important;font-weight:500 !important;letter-spacing:.2em !important;text-transform:uppercase !important;color:var(--celb-viewall-color,#15120d) !important;text-decoration:none !important;border:1px solid var(--celb-gold) !important;padding:15px 42px !important;border-radius:0 !important;line-height:1 !important;cursor:pointer;transition:background .3s ease,color .3s ease;}'
		. '.celb-viewall:hover{background:var(--celb-gold) !important;color:#000 !important;}'
		. '@media(max-width:782px){.celb-carousel-track{display:flex !important;grid-template-columns:none !important;overflow-x:auto !important;}.celb-carousel-cell{flex:0 0 80% !important;display:block !important;}.celb-carousel-nav{display:none !important;}.celb-card-media{aspect-ratio:3/4 !important;}.celb-card-name{font-size:clamp(1rem,6cqw,1.4rem) !important;}.celb-card-sub{letter-spacing:.1em !important;font-size:clamp(.55rem,2.3cqw,.68rem) !important;}}';
	if ( is_singular( 'celeb_news' ) ) {
		// Critical article layout emitted inline so it applies even when a CDN /
		// page cache is still serving an older copy of celb-frontend.css.
		$inline .= 'body.single-celeb_news .container-wrap,body.single-celeb_news #content,body.single-celeb_news .content-area,body.single-celeb_news .post-area,body.single-celeb_news .row,body.single-celeb_news .main-content,body.single-celeb_news .entry-content,body.single-celeb_news .site-content,body.single-celeb_news #primary,body.single-celeb_news #main,body.single-celeb_news .site-main,body.single-celeb_news main{padding-top:0 !important;margin-top:0 !important;}'
			. 'body.single-celeb_news .container,body.single-celeb_news .container-wrap,body.single-celeb_news #content{padding-left:0 !important;padding-right:0 !important;}'
			. 'body.single-celeb_news #ajax-content-wrap,body.single-celeb_news .container-wrap,body.single-celeb_news .container,body.single-celeb_news #content,body.single-celeb_news .content-area,body.single-celeb_news .post-area,body.single-celeb_news .nectar-post-area,body.single-celeb_news .row,body.single-celeb_news .col,body.single-celeb_news [class*="span_"],body.single-celeb_news .entry-content,body.single-celeb_news .main-content,body.single-celeb_news .site-content,body.single-celeb_news #primary,body.single-celeb_news #main,body.single-celeb_news .site-main,body.single-celeb_news main,body.single-celeb_news article{max-width:100% !important;width:100% !important;float:none !important;}'
			. 'body.single-celeb_news #page-header-wrap,body.single-celeb_news .page-header-no-bg,body.single-celeb_news .title-container{display:none !important;}'
			. '.celb-article{background:#000 !important;color:#fff !important;font-family:inherit;width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);margin-top:0;padding:0 0 90px;overflow-x:hidden;}'
			. '.celb-article-inner{width:100%;max-width:100%;}'
			. '.celb-article-hero{position:relative;width:100%;min-height:clamp(380px,58vh,660px);display:flex;background:#0a0a0a;overflow:hidden;}'
			. '.celb-article-hero-pic{position:absolute;inset:0;display:block;width:100%;height:100%;}'
			. '.celb-article-hero-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;}'
			. '.celb-article-hero-grad{position:absolute;inset:0;pointer-events:none;background:linear-gradient(to top,rgba(0,0,0,.92) 0%,rgba(0,0,0,.55) 28%,rgba(0,0,0,.12) 56%,rgba(0,0,0,0) 82%);}'
			. '.celb-article-hero-text{position:relative;z-index:2;align-self:flex-end;width:100%;max-width:900px;margin:0 auto;padding:clamp(22px,4vw,46px) clamp(20px,5vw,40px);}'
			. '.celb-article-title{font-family:inherit;font-weight:600;font-size:clamp(1.4rem,3vw,2.1rem);line-height:1.15;margin:0;color:#fff;}'
			. '.celb-article-title[dir="rtl"]{font-family:"Cairo","Tajawal",sans-serif;font-weight:700;line-height:1.4;}'
			. '.celb-article-meta{display:flex;flex-wrap:wrap;align-items:center;gap:14px;font-family:inherit;font-size:.78rem;letter-spacing:.04em;color:rgba(255,255,255,.78);}'
			. '.celb-article-celeb{display:inline-block;font-family:inherit;font-size:.7rem;font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.72);text-decoration:none;}'
			. '.celb-article-heroinfo{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-top:16px;}'
			. '.celb-article-infomain{display:flex;flex-direction:column;gap:8px;min-width:0;}'
			. '.celb-article-lang{display:inline-flex;gap:6px;flex:0 0 auto;position:relative;z-index:3;}'
			. '.celb-article-subbar{display:none !important;}'
			. '.celb-article.celb-lang-ar .celb-article-heroinfo{flex-direction:row-reverse;}'
			. '.celb-news-lang-btn{font:inherit;font-size:.72rem;letter-spacing:.1em;line-height:1;color:#fff;background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:7px 14px;cursor:pointer;position:relative;z-index:3;touch-action:manipulation;-webkit-tap-highlight-color:rgba(255,255,255,.25);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);}'
			. '.celb-news-lang-btn.is-active{background:#fff;color:#111;border-color:#fff;}'
			. '.celb-article-title[hidden],.celb-article-body[hidden]{display:none !important;}'
			. '@media(min-width:768px){.celb-article-hero{min-height:clamp(660px,86vh,1000px);}.celb-article-heroinfo{align-items:center;}.celb-article-infomain{flex-direction:row;align-items:center;gap:18px;flex-wrap:wrap;}.celb-article.celb-lang-ar .celb-article-heroinfo .celb-article-infomain{flex-direction:row-reverse;}}'
			. '.celb-article-content{max-width:780px;margin:0 auto;padding:clamp(34px,6vw,60px) clamp(20px,5vw,32px) 0;}'
			. '.celb-article-body{font-family:inherit;font-size:1.04rem;line-height:1.9;color:rgba(255,255,255,.86);}'
			. '.celb-article-body[dir="rtl"]{font-family:"Cairo","Tajawal",sans-serif;direction:rtl;text-align:right;line-height:2.05;}'
			. '.celb-article-gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:44px;}'
			. '.celb-article-gthumb{display:block;width:100%;aspect-ratio:16/9;background-size:cover;background-position:center;border:0;border-radius:3px;padding:0;cursor:pointer;}'
			. '@media(max-width:600px){.celb-article-hero{min-height:66vh;}.celb-article-gallery{grid-template-columns:repeat(2,1fr);}}';
	}
	wp_add_inline_style( 'celb-frontend', $inline );

	wp_enqueue_script( 'celb-frontend', CELB_URL . 'assets/celb-frontend.js', array(), CELB_VERSION, true );
	wp_localize_script( 'celb-frontend', 'CELB_FRONT', array(
		'pullHero' => (int) $s['pull_hero'],
	) );
}
add_action( 'wp_enqueue_scripts', 'celb_enqueue_frontend' );

/**
 * Force the frontend assets to load. Called from the shortcode callbacks so the
 * CSS/JS load even when a shortcode is placed inside a page-builder module or
 * widget whose markup never appears in the page's post_content (which is what
 * the has_shortcode() detection above relies on). Late-enqueued styles/scripts
 * are printed by WordPress in the footer, so this works during content render.
 */
function celb_ensure_frontend_assets( $roster = false ) {
	static $done         = false;
	static $roster_done  = false;
	static $print_hooked = false;

	if ( ! $done ) {
		add_filter( 'celb_load_frontend_assets', '__return_true' );
	}
	if ( $roster && ! $roster_done ) {
		add_filter( 'celb_load_roster_fonts', '__return_true' );
	}

	if ( ! did_action( 'wp_enqueue_scripts' ) ) {
		// Too early: the normal hook will enqueue with the filters forced.
		if ( $roster ) { $roster_done = true; }
		return;
	}

	if ( ! $done ) {
		$done = true;
		celb_enqueue_frontend();
	}
	if ( $roster ) { $roster_done = true; }

	/*
	 * Belt-and-suspenders: when a shortcode renders AFTER wp_enqueue_scripts
	 * (common with page builders that build the page outside post_content), the
	 * assets are enqueued late. Some themes/builders don't emit WordPress's
	 * automatic late assets, which leaves the cards unstyled or empty. Force our
	 * handles to print — wp_print_* marks them done, so this never
	 * double-prints alongside the normal output. Hooked once; it prints whatever
	 * is enqueued-but-not-yet-printed at footer time.
	 */
	if ( ! $print_hooked ) {
		$print_hooked = true;
		$print = function () {
			foreach ( array( 'celb-fonts', 'celb-roster-fonts', 'celb-frontend' ) as $h ) {
				if ( wp_style_is( $h, 'enqueued' ) && ! wp_style_is( $h, 'done' ) ) {
					wp_print_styles( $h );
				}
			}
			if ( wp_script_is( 'celb-frontend', 'enqueued' ) && ! wp_script_is( 'celb-frontend', 'done' ) ) {
				wp_print_scripts( 'celb-frontend' );
			}
		};
		if ( did_action( 'wp_footer' ) ) {
			$print();
		} else {
			add_action( 'wp_footer', $print, 1 );
		}
	}
}

/* Admin assets: see celb_studio_enqueue() in includes/admin-studio.php. */

/* -------------------------------------------------------------------------
 * 10. SETTINGS
 * ---------------------------------------------------------------------- */

function celb_default_settings() {
	return array(
		'accent'         => '#999999',
		'theme'          => 'light', // light | dark — profile page background
		'pwa_name'       => 'iLike Manage',
		'pwa_icon'       => '',      // URL of a square (≥512px) icon for home-screen install
		'grid_cols'      => 3,       // desktop grid columns (mobile stays 2)
		'carousel_items' => 6,       // desktop carousel items visible

		// Roster header ([CLEB_celebrities])
		'roster_eyebrow' => '04 — Roster',
		'roster_title'   => 'Our Stars',
		'roster_intro'   => 'The actors, singers and hosts under iLike management. Names and roles are live; portraits are placeholders until official headshots are supplied.',
		'carousel_viewall'       => 1, // show "View all" button under the carousel
		'carousel_viewall_label' => 'View all talent',
		'carousel_viewall_url'   => 'https://ilikeagency.co/our-celebrities/',
		'pull_hero'      => 1,       // remove theme's top gap above the hero
		'cta_show'       => 1,       // show the call-to-action button below the gallery
		'cta_label'      => 'Let\'s Talk',
		'cta_url'        => 'https://ilikeagency.co/lets-talk/',
		'aio_intro'      => 'Discover our roster of exceptional talent at iLike Agency, where creativity, professionalism, and influence come together.',
		'aio_cta_label'  => 'View Our Roster',
		'aio_cta_url'    => '',
		'aio_enabled'    => 0,
		'aio_title'      => 'Our Stars',
		'font_url'       => '',
		'font_body'      => '',
		'font_display'   => '',
		'text_color'     => '',
		'bg_color'       => '',
		'stars_slug'     => 'our-stars',
		'onb_slug'       => 'talent-onboarding',
		'pdata_slug'     => 'personal-data',
		'rateonb_slug'   => 'rate-form',
		'portal_enabled' => 0,       // enable the front-end self-submission portal
		'portal_passwords' => array(), // list of array( 'label' => , 'pass' => )
		'pdata_enabled'  => 0,       // enable the Personal Data / Emergency Contacts portal
		'pdata_password' => '',      // access password for the Personal Data portal

		// Online contracts
		'agency_sig'        => 0,  // attachment ID of the agency representative signature (applied to every contract)
		'contract_logo'     => 0,  // attachment ID of a logo that reads on white (top-left of every PDF page + signing header)
		'contract_from'     => 'ahmed@ilikeagency.co',
		'contract_subject'  => 'Welcome to iLike Agency, Your Contract Is Ready',
		'contract_body'     => "Thank you for choosing iLike Agency. We're excited to welcome you to the family. Your contract has been successfully signed and is attached to this email for your records. We look forward to working with you.",
		'contract_sign_url' => '', // page URL that holds the [CLEB_sign] shortcode (falls back to /sign/)

		// Branding
		'brand_logo_url'   => 'https://ilikeagency.co/wp-content/uploads/2026/07/ilike-logo.png',

		// Scheduling email notifications
		'sched_email_from_name' => 'iLike Agency',
		'sched_email_from'      => '',
		'sched_email_replyto'   => '',
		'sched_email_cc'        => '',
		'sched_email_reminder'  => 1, // days before to send a reminder (0 = off)

		// Rate Card — GLOBAL commons only (per-card config lives on each Rate Card).
		'rc_cta_label'     => 'Request Final Offer',
		'rc_contact_label' => 'Contact My Management',
		'rc_contact_url'   => '',
		'rc_usd_enabled'   => 0,
		'rc_usd_rate'      => 50,
		'rc_usd_markup'    => 0,

		// Artist Contact Form
		'contact_page_id'   => 0,
		'contact_recipient' => '',
		'recaptcha_site'    => '',
		'recaptcha_secret'  => '',
	);
}

function celb_get_settings() {
	$s = get_option( 'celb_settings', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), celb_default_settings() );
}

/**
 * Single source of truth for the agency logo used across the backend, social
 * cards, talent portal and email notifications. Resolves: the Brand Logo URL
 * setting → the site's custom logo → the packaged default. Routing every logo
 * through here means the admin only sets it once (Settings → Brand logo URL)
 * and it can never fall back to a stale/404 hardcoded path.
 */
function celb_logo_url() {
	$s = celb_get_settings();
	$u = isset( $s['brand_logo_url'] ) ? trim( (string) $s['brand_logo_url'] ) : '';
	if ( '' !== $u ) {
		return $u;
	}
	$custom = (int) get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$cu = wp_get_attachment_image_url( $custom, 'full' );
		if ( $cu ) {
			return $cu;
		}
	}
	return 'https://ilikeagency.co/wp-content/uploads/2026/07/ilike-logo.png';
}

function celb_register_settings() {
	register_setting( 'celb_settings_group', 'celb_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'celb_sanitize_settings',
		'default'           => celb_default_settings(),
	) );
}
add_action( 'admin_init', 'celb_register_settings' );

function celb_sanitize_settings( $input ) {
	$d   = celb_default_settings();
	$out = array();

	$accent       = isset( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
	$out['accent'] = $accent ? $accent : $d['accent'];

	$out['theme'] = ( isset( $input['theme'] ) && in_array( $input['theme'], array( 'light', 'dark' ), true ) )
		? $input['theme'] : $d['theme'];

	$out['pwa_name'] = isset( $input['pwa_name'] ) ? sanitize_text_field( $input['pwa_name'] ) : $d['pwa_name'];
	$out['pwa_icon'] = isset( $input['pwa_icon'] ) ? esc_url_raw( trim( (string) $input['pwa_icon'] ) ) : $d['pwa_icon'];

	$out['grid_cols']      = isset( $input['grid_cols'] ) ? min( 6, max( 1, absint( $input['grid_cols'] ) ) ) : $d['grid_cols'];
	$out['carousel_items'] = isset( $input['carousel_items'] ) ? min( 8, max( 2, absint( $input['carousel_items'] ) ) ) : $d['carousel_items'];

	$out['roster_eyebrow'] = isset( $input['roster_eyebrow'] ) ? sanitize_text_field( $input['roster_eyebrow'] ) : $d['roster_eyebrow'];
	$out['roster_title']   = isset( $input['roster_title'] ) ? sanitize_text_field( $input['roster_title'] ) : $d['roster_title'];
	$out['roster_intro']   = isset( $input['roster_intro'] ) ? sanitize_textarea_field( $input['roster_intro'] ) : $d['roster_intro'];

	$out['carousel_viewall']       = empty( $input['carousel_viewall'] ) ? 0 : 1;
	$out['carousel_viewall_label'] = isset( $input['carousel_viewall_label'] ) ? sanitize_text_field( $input['carousel_viewall_label'] ) : $d['carousel_viewall_label'];
	if ( '' === $out['carousel_viewall_label'] ) {
		$out['carousel_viewall_label'] = $d['carousel_viewall_label'];
	}
	$out['carousel_viewall_url'] = isset( $input['carousel_viewall_url'] ) ? esc_url_raw( trim( $input['carousel_viewall_url'] ) ) : $d['carousel_viewall_url'];

	$out['pull_hero']      = empty( $input['pull_hero'] ) ? 0 : 1;

	$out['cta_show']  = empty( $input['cta_show'] ) ? 0 : 1;
	$out['cta_label'] = isset( $input['cta_label'] ) ? sanitize_text_field( $input['cta_label'] ) : $d['cta_label'];
	if ( '' === $out['cta_label'] ) {
		$out['cta_label'] = $d['cta_label'];
	}
	$out['cta_url'] = isset( $input['cta_url'] ) ? esc_url_raw( trim( $input['cta_url'] ) ) : $d['cta_url'];
	$out['aio_intro']     = isset( $input['aio_intro'] ) ? sanitize_textarea_field( $input['aio_intro'] ) : '';
	$out['aio_cta_label'] = isset( $input['aio_cta_label'] ) ? sanitize_text_field( $input['aio_cta_label'] ) : $d['aio_cta_label'];
	$out['aio_cta_url']   = isset( $input['aio_cta_url'] ) ? esc_url_raw( trim( $input['aio_cta_url'] ) ) : '';
	$out['aio_enabled']   = empty( $input['aio_enabled'] ) ? 0 : 1;
	$out['aio_title']     = isset( $input['aio_title'] ) ? sanitize_text_field( $input['aio_title'] ) : $d['aio_title'];
	// Custom Theme overrides (empty = inherit the active theme).
	$out['font_url']     = isset( $input['font_url'] ) ? esc_url_raw( trim( $input['font_url'] ) ) : '';
	$out['font_body']    = isset( $input['font_body'] ) ? celb_sanitize_font_family( $input['font_body'] ) : '';
	$out['font_display'] = isset( $input['font_display'] ) ? celb_sanitize_font_family( $input['font_display'] ) : '';
	$out['text_color']   = isset( $input['text_color'] ) ? (string) sanitize_hex_color( $input['text_color'] ) : '';
	$out['bg_color']     = isset( $input['bg_color'] ) ? (string) sanitize_hex_color( $input['bg_color'] ) : '';
	// Page addresses (custom URL slugs).
	foreach ( array( 'stars_slug', 'onb_slug', 'pdata_slug', 'rateonb_slug' ) as $sk ) {
		$sv         = isset( $input[ $sk ] ) ? sanitize_title( $input[ $sk ] ) : '';
		$out[ $sk ] = '' !== $sv ? $sv : $d[ $sk ];
	}

	$out['portal_enabled'] = empty( $input['portal_enabled'] ) ? 0 : 1;
	$out['pdata_enabled']  = empty( $input['pdata_enabled'] ) ? 0 : 1;
	$out['pdata_password'] = isset( $input['pdata_password'] ) ? sanitize_text_field( $input['pdata_password'] ) : '';
	$out['portal_passwords'] = array();
	if ( isset( $input['portal_passwords'] ) && is_array( $input['portal_passwords'] ) ) {
		foreach ( $input['portal_passwords'] as $pw ) {
			$pass  = isset( $pw['pass'] ) ? sanitize_text_field( $pw['pass'] ) : '';
			$label = isset( $pw['label'] ) ? sanitize_text_field( $pw['label'] ) : '';
			if ( '' === $pass ) {
				continue;
			}
			$out['portal_passwords'][] = array( 'label' => $label, 'pass' => $pass );
		}
	}

	$out['agency_sig']        = isset( $input['agency_sig'] ) ? absint( $input['agency_sig'] ) : $d['agency_sig'];
	$out['contract_logo']     = isset( $input['contract_logo'] ) ? absint( $input['contract_logo'] ) : $d['contract_logo'];
	$from                     = isset( $input['contract_from'] ) ? sanitize_email( trim( $input['contract_from'] ) ) : '';
	$out['contract_from']     = is_email( $from ) ? $from : $d['contract_from'];
	$out['contract_subject']  = isset( $input['contract_subject'] ) ? sanitize_text_field( $input['contract_subject'] ) : $d['contract_subject'];
	if ( '' === $out['contract_subject'] ) {
		$out['contract_subject'] = $d['contract_subject'];
	}
	$out['contract_body']     = isset( $input['contract_body'] ) ? sanitize_textarea_field( $input['contract_body'] ) : $d['contract_body'];
	if ( '' === trim( $out['contract_body'] ) ) {
		$out['contract_body'] = $d['contract_body'];
	}
	$out['contract_sign_url'] = isset( $input['contract_sign_url'] ) ? esc_url_raw( trim( $input['contract_sign_url'] ) ) : $d['contract_sign_url'];
	$out['brand_logo_url']    = isset( $input['brand_logo_url'] ) ? esc_url_raw( trim( $input['brand_logo_url'] ) ) : $d['brand_logo_url'];
	$out['sched_email_from_name'] = isset( $input['sched_email_from_name'] ) ? sanitize_text_field( $input['sched_email_from_name'] ) : $d['sched_email_from_name'];
	$out['sched_email_from']      = isset( $input['sched_email_from'] ) ? sanitize_email( $input['sched_email_from'] ) : '';
	$out['sched_email_replyto']   = isset( $input['sched_email_replyto'] ) ? sanitize_email( $input['sched_email_replyto'] ) : '';
	$out['sched_email_cc']        = isset( $input['sched_email_cc'] ) ? implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $input['sched_email_cc'] ) ) ) ) ) : '';
	$out['sched_email_reminder']  = isset( $input['sched_email_reminder'] ) ? max( 0, (int) $input['sched_email_reminder'] ) : $d['sched_email_reminder'];

	/* Rate Card — global commons only */
	$out['rc_cta_label']     = isset( $input['rc_cta_label'] ) ? sanitize_text_field( $input['rc_cta_label'] ) : $d['rc_cta_label'];
	$out['rc_contact_label'] = isset( $input['rc_contact_label'] ) ? sanitize_text_field( $input['rc_contact_label'] ) : $d['rc_contact_label'];
	$out['rc_usd_enabled']   = empty( $input['rc_usd_enabled'] ) ? 0 : 1;
	$out['rc_usd_rate']      = isset( $input['rc_usd_rate'] ) ? max( 0.0001, (float) $input['rc_usd_rate'] ) : $d['rc_usd_rate'];
	$out['rc_usd_markup']    = isset( $input['rc_usd_markup'] ) ? max( 0, (float) $input['rc_usd_markup'] ) : 0;
	$out['rc_contact_url']   = isset( $input['rc_contact_url'] ) ? esc_url_raw( trim( $input['rc_contact_url'] ) ) : $d['rc_contact_url'];
	$out['contact_page_id']   = isset( $input['contact_page_id'] ) ? absint( $input['contact_page_id'] ) : 0;
	$out['contact_recipient'] = isset( $input['contact_recipient'] ) ? implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $input['contact_recipient'] ) ) ) ) ) : '';
	$out['recaptcha_site']    = isset( $input['recaptcha_site'] ) ? sanitize_text_field( $input['recaptcha_site'] ) : '';
	$out['recaptcha_secret']  = isset( $input['recaptcha_secret'] ) ? sanitize_text_field( $input['recaptcha_secret'] ) : '';

	return $out;
}

function celb_rate_sanitize_opts( $raw ) {
	$out = array();
	if ( ! is_array( $raw ) ) {
		return $out;
	}
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
		if ( '' === $label ) {
			continue;
		}
		$out[] = array(
			'label' => $label,
			'desc'  => isset( $row['desc'] ) ? sanitize_text_field( $row['desc'] ) : '',
			'type'  => ( isset( $row['type'] ) && in_array( $row['type'], array( 'none', 'percent', 'fixed' ), true ) ) ? $row['type'] : 'none',
			'value' => isset( $row['value'] ) ? max( 0, (float) $row['value'] ) : 0,
		);
	}
	return $out;
}

function celb_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . CELB_CPT,
		__( 'CELB MGMT Settings', 'celb-mgmt' ),
		__( 'Settings', 'celb-mgmt' ),
		'manage_options',
		'celb-settings',
		'celb_render_settings_page',
		999
	);
}
add_action( 'admin_menu', 'celb_settings_menu', 999 );

/* Settings screen: celb_render_settings_page() in includes/admin-studio.php. */

/* -------------------------------------------------------------------------
 * 11. FRONT-END SUBMISSION PORTAL  [CLEB_submit]
 * ---------------------------------------------------------------------- */

/* Configured access passwords (plain strings). */
function celb_portal_passwords() {
	$s   = celb_get_settings();
	$out = array();
	if ( ! empty( $s['portal_passwords'] ) && is_array( $s['portal_passwords'] ) ) {
		foreach ( $s['portal_passwords'] as $pw ) {
			if ( ! empty( $pw['pass'] ) ) {
				$out[] = $pw['pass'];
			}
		}
	}
	return $out;
}

function celb_portal_validate( $password ) {
	if ( '' === (string) $password ) {
		return false;
	}
	foreach ( celb_portal_passwords() as $p ) {
		if ( hash_equals( (string) $p, (string) $password ) ) {
			return true;
		}
	}
	return false;
}

/* Stateless unlock token derived from a validated password + site salt. */
function celb_portal_token( $password ) {
	return hash_hmac( 'sha256', 'celb-portal-unlock|' . $password, wp_salt( 'auth' ) );
}

function celb_portal_token_valid( $token ) {
	if ( '' === (string) $token ) {
		return false;
	}
	foreach ( celb_portal_passwords() as $p ) {
		if ( hash_equals( celb_portal_token( $p ), (string) $token ) ) {
			return true;
		}
	}
	return false;
}

function celb_portal_wrap( $inner ) {
	return '<div class="celb-scope celb-portal">' . $inner . '</div>';
}

/* Shortcode controller. */
function celb_portal_shortcode() {
	celb_ensure_frontend_assets();
	$s = celb_get_settings();

	if ( empty( $s['portal_enabled'] ) ) {
		return celb_portal_wrap( '<p class="celb-portal-note">' . esc_html__( 'Submissions are currently closed.', 'celb-mgmt' ) . '</p>' );
	}
	if ( empty( celb_portal_passwords() ) ) {
		return celb_portal_wrap( '<p class="celb-portal-note">' . esc_html__( 'The submission portal is not ready yet. Please check back soon.', 'celb-mgmt' ) . '</p>' );
	}

	$action = isset( $_POST['celb_portal_action'] ) ? sanitize_key( wp_unslash( $_POST['celb_portal_action'] ) ) : '';

	// Final profile submission.
	if ( 'submit_profile' === $action ) {
		if ( ! isset( $_POST['celb_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_profile_nonce'] ), 'celb_submit_profile' ) ) {
			return celb_portal_wrap( celb_portal_gate_html( __( 'Your session expired. Please enter the password again.', 'celb-mgmt' ) ) );
		}
		$token = isset( $_POST['celb_unlock'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_unlock'] ) ) : '';
		if ( ! celb_portal_token_valid( $token ) ) {
			return celb_portal_wrap( celb_portal_gate_html( __( 'Access could not be verified. Please enter the password again.', 'celb-mgmt' ) ) );
		}
		// Honeypot: silently accept without creating anything.
		if ( ! empty( $_POST['celb_hp'] ) ) {
			return celb_portal_wrap( celb_portal_confirmation_html() );
		}
		return celb_portal_wrap( celb_portal_process( $token ) );
	}

	// Password gate.
	if ( 'gate' === $action ) {
		if ( ! isset( $_POST['celb_gate_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_gate_nonce'] ), 'celb_portal_gate' ) ) {
			return celb_portal_wrap( celb_portal_gate_html( __( 'Please try again.', 'celb-mgmt' ) ) );
		}
		$pass = isset( $_POST['celb_portal_pass'] ) ? (string) wp_unslash( $_POST['celb_portal_pass'] ) : '';
		if ( celb_portal_validate( $pass ) ) {
			return celb_portal_wrap( celb_portal_form_html( celb_portal_token( $pass ) ) );
		}
		return celb_portal_wrap( celb_portal_gate_html( __( 'Incorrect password. Please try again.', 'celb-mgmt' ) ) );
	}

	return celb_portal_wrap( celb_portal_gate_html() );
}
add_shortcode( 'CLEB_submit', 'celb_portal_shortcode' );

/* Password gate screen. */
function celb_portal_gate_html( $error = '' ) {
	ob_start();
	?>
	<div class="celb-portal-gate">
		<p class="celb-portal-lead"><?php esc_html_e( 'This submission area is password protected. Enter your access password to continue.', 'celb-mgmt' ); ?></p>
		<?php if ( $error ) : ?>
			<p class="celb-portal-error"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>
		<form method="post" class="celb-gate-form">
			<?php wp_nonce_field( 'celb_portal_gate', 'celb_gate_nonce' ); ?>
			<input type="hidden" name="celb_portal_action" value="gate" />
			<input type="password" name="celb_portal_pass" class="celb-input" placeholder="<?php esc_attr_e( 'Access password', 'celb-mgmt' ); ?>" autocomplete="off" required />
			<button type="submit" class="celb-btn"><?php esc_html_e( 'Continue', 'celb-mgmt' ); ?></button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/* Confirmation screen. */
function celb_portal_confirmation_html() {
	return '<div class="celb-portal-confirm"><div class="celb-portal-check" aria-hidden="true">&#10003;</div><p class="celb-portal-confirm-text">'
		. esc_html__( 'Your profile is currently pending creation and review. It will be live very soon.', 'celb-mgmt' )
		. '</p></div>';
}

/* Helper: read a value from a (possibly posted) values array. */
function celb_v( $values, $key, $default = '' ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : $default;
}

/* A single career row on the front-end form. */
function celb_portal_career_row( $i, $row ) {
	$row = wp_parse_args( $row, array( 'project' => '', 'role' => '', 'year' => '', 'type' => '', 'special' => 0, 'production' => 0 ) );
	?>
	<div class="celb-portal-row celb-portal-career-row">
		<input type="text" name="celb_f_career[<?php echo esc_attr( $i ); ?>][project]" value="<?php echo esc_attr( $row['project'] ); ?>" placeholder="<?php esc_attr_e( 'Project name', 'celb-mgmt' ); ?>" />
		<input type="text" name="celb_f_career[<?php echo esc_attr( $i ); ?>][role]" value="<?php echo esc_attr( $row['role'] ); ?>" placeholder="<?php esc_attr_e( 'Character / Role', 'celb-mgmt' ); ?>" />
		<input type="text" list="celb-project-types" name="celb_f_career[<?php echo esc_attr( $i ); ?>][type]" value="<?php echo esc_attr( $row['type'] ); ?>" placeholder="<?php esc_attr_e( 'Type (TV Series, Movie…)', 'celb-mgmt' ); ?>" />
		<input type="number" name="celb_f_career[<?php echo esc_attr( $i ); ?>][year]" value="<?php echo esc_attr( $row['year'] ); ?>" placeholder="<?php esc_attr_e( 'Year', 'celb-mgmt' ); ?>" min="1900" max="2100" />
		<label class="celb-checkbox"><input type="checkbox" name="celb_f_career[<?php echo esc_attr( $i ); ?>][special]" value="1" <?php checked( ! empty( $row['special'] ) ); ?> /> <?php esc_html_e( 'Special appearance', 'celb-mgmt' ); ?></label>
		<label class="celb-checkbox"><input type="checkbox" name="celb_f_career[<?php echo esc_attr( $i ); ?>][production]" value="1" <?php checked( ! empty( $row['production'] ) ); ?> /> <?php esc_html_e( 'Coming soon', 'celb-mgmt' ); ?></label>
		<button type="button" class="celb-portal-remove" aria-label="<?php esc_attr_e( 'Remove', 'celb-mgmt' ); ?>">&times;</button>
	</div>
	<?php
}

/* A single award row on the front-end form. */
function celb_portal_award_row( $i, $row ) {
	$row = wp_parse_args( $row, array( 'festival' => '', 'title' => '', 'project' => '', 'year' => '', 'location' => '' ) );
	?>
	<div class="celb-portal-row celb-portal-award-row">
		<input type="text" name="celb_f_awards[<?php echo esc_attr( $i ); ?>][festival]" value="<?php echo esc_attr( $row['festival'] ); ?>" placeholder="<?php esc_attr_e( 'Festival / Organization', 'celb-mgmt' ); ?>" />
		<input type="text" name="celb_f_awards[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="<?php esc_attr_e( 'Award title', 'celb-mgmt' ); ?>" />
		<input type="text" name="celb_f_awards[<?php echo esc_attr( $i ); ?>][project]" value="<?php echo esc_attr( $row['project'] ); ?>" placeholder="<?php esc_attr_e( 'Project (optional)', 'celb-mgmt' ); ?>" />
		<input type="number" name="celb_f_awards[<?php echo esc_attr( $i ); ?>][year]" value="<?php echo esc_attr( $row['year'] ); ?>" placeholder="<?php esc_attr_e( 'Year', 'celb-mgmt' ); ?>" min="1900" max="2100" />
		<input type="text" name="celb_f_awards[<?php echo esc_attr( $i ); ?>][location]" value="<?php echo esc_attr( $row['location'] ); ?>" placeholder="<?php esc_attr_e( 'Location', 'celb-mgmt' ); ?>" />
		<button type="button" class="celb-portal-remove" aria-label="<?php esc_attr_e( 'Remove', 'celb-mgmt' ); ?>">&times;</button>
	</div>
	<?php
}

/* The submission form. */
function celb_portal_form_html( $token, $values = array(), $error = '' ) {
	$social = celb_social_platforms();

	$career_rows = array();
	if ( ! empty( $values['celb_f_career'] ) && is_array( $values['celb_f_career'] ) ) {
		foreach ( $values['celb_f_career'] as $r ) {
			$career_rows[] = $r;
		}
	}
	if ( empty( $career_rows ) ) {
		$career_rows = array( array() );
	}

	$award_rows = array();
	if ( ! empty( $values['celb_f_awards'] ) && is_array( $values['celb_f_awards'] ) ) {
		foreach ( $values['celb_f_awards'] as $r ) {
			$award_rows[] = $r;
		}
	}
	if ( empty( $award_rows ) ) {
		$award_rows = array( array() );
	}

	$soc_vals = isset( $values['celb_f_social'] ) && is_array( $values['celb_f_social'] ) ? $values['celb_f_social'] : array();

	ob_start();
	?>
	<form method="post" class="celb-portal-form" novalidate>
		<?php wp_nonce_field( 'celb_submit_profile', 'celb_profile_nonce' ); ?>
		<input type="hidden" name="celb_portal_action" value="submit_profile" />
		<input type="hidden" name="celb_unlock" value="<?php echo esc_attr( $token ); ?>" />
		<div class="celb-hp" aria-hidden="true">
			<label><?php esc_html_e( 'Leave this field empty', 'celb-mgmt' ); ?><input type="text" name="celb_hp" value="" tabindex="-1" autocomplete="off" /></label>
		</div>

		<p class="celb-portal-lead"><?php esc_html_e( 'Tell us about yourself. Your submission is reviewed by our team before it goes live, so don\'t worry about perfect formatting.', 'celb-mgmt' ); ?></p>
		<?php if ( $error ) : ?>
			<p class="celb-portal-error"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>

		<div class="celb-field">
			<label class="celb-label" for="celb_f_name"><?php esc_html_e( 'Full name', 'celb-mgmt' ); ?> *</label>
			<input type="text" id="celb_f_name" name="celb_f_name" class="celb-input" value="<?php echo esc_attr( celb_v( $values, 'celb_f_name' ) ); ?>" required />
		</div>

		<div class="celb-field celb-field-split">
			<div>
				<label class="celb-label" for="celb_f_role"><?php esc_html_e( 'Role / Profession', 'celb-mgmt' ); ?></label>
				<input type="text" id="celb_f_role" name="celb_f_role" class="celb-input" value="<?php echo esc_attr( celb_v( $values, 'celb_f_role' ) ); ?>" placeholder="Actor, Presenter, Singer…" />
			</div>
			<div>
				<label class="celb-label" for="celb_f_nationality"><?php esc_html_e( 'Nationality', 'celb-mgmt' ); ?></label>
				<input type="text" id="celb_f_nationality" name="celb_f_nationality" class="celb-input" value="<?php echo esc_attr( celb_v( $values, 'celb_f_nationality' ) ); ?>" />
			</div>
		</div>

		<div class="celb-field">
			<label class="celb-label" for="celb_f_birthdate"><?php esc_html_e( 'Birthday', 'celb-mgmt' ); ?></label>
			<div class="celb-row-inline">
				<input type="date" id="celb_f_birthdate" name="celb_f_birthdate" class="celb-input celb-input-auto" value="<?php echo esc_attr( celb_v( $values, 'celb_f_birthdate' ) ); ?>" />
				<label class="celb-checkbox"><input type="checkbox" name="celb_f_show_year" value="1" <?php checked( ! empty( $values['celb_f_show_year'] ) || empty( $values ) ); ?> /> <?php esc_html_e( 'Show my year of birth publicly', 'celb-mgmt' ); ?></label>
			</div>
		</div>

		<div class="celb-field">
			<label class="celb-label" for="celb_f_bio"><?php esc_html_e( 'Biography', 'celb-mgmt' ); ?></label>
			<textarea id="celb_f_bio" name="celb_f_bio" class="celb-input" rows="7"><?php echo esc_textarea( celb_v( $values, 'celb_f_bio' ) ); ?></textarea>
		</div>

		<h3 class="celb-portal-group-title"><?php esc_html_e( 'Roles & Upcoming Projects', 'celb-mgmt' ); ?></h3>
		<p class="celb-portal-hint"><?php esc_html_e( 'Add your work. Tick "Coming soon" for upcoming projects still in production.', 'celb-mgmt' ); ?></p>
		<div class="celb-portal-repeater" data-portal="career">
			<?php celb_project_types_datalist(); ?>
			<div class="celb-portal-items">
				<?php foreach ( $career_rows as $i => $r ) {
					celb_portal_career_row( $i, $r );
				} ?>
			</div>
			<script type="text/template" class="celb-portal-row-tpl"><?php celb_portal_career_row( '__i__', array() ); ?></script>
			<button type="button" class="celb-portal-add">+ <?php esc_html_e( 'Add project', 'celb-mgmt' ); ?></button>
		</div>

		<h3 class="celb-portal-group-title"><?php esc_html_e( 'Awards', 'celb-mgmt' ); ?> <span class="celb-portal-optional">(<?php esc_html_e( 'optional', 'celb-mgmt' ); ?>)</span></h3>
		<div class="celb-portal-repeater" data-portal="awards">
			<div class="celb-portal-items">
				<?php foreach ( $award_rows as $i => $r ) {
					celb_portal_award_row( $i, $r );
				} ?>
			</div>
			<script type="text/template" class="celb-portal-row-tpl"><?php celb_portal_award_row( '__i__', array() ); ?></script>
			<button type="button" class="celb-portal-add">+ <?php esc_html_e( 'Add award', 'celb-mgmt' ); ?></button>
		</div>

		<h3 class="celb-portal-group-title"><?php esc_html_e( 'Social Media', 'celb-mgmt' ); ?></h3>
		<div class="celb-social-fields">
			<?php foreach ( $social as $key => $label ) : ?>
				<div class="celb-field">
					<label class="celb-label" for="celb_f_social_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<input type="url" id="celb_f_social_<?php echo esc_attr( $key ); ?>" name="celb_f_social[<?php echo esc_attr( $key ); ?>]" class="celb-input" value="<?php echo esc_attr( celb_v( $soc_vals, $key ) ); ?>" placeholder="https://" />
				</div>
			<?php endforeach; ?>
		</div>

		<div class="celb-portal-submit">
			<button type="submit" class="celb-btn"><?php esc_html_e( 'Submit for review', 'celb-mgmt' ); ?></button>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

/* Create the draft profile from the submission. */
function celb_portal_process( $token ) {
	$name = isset( $_POST['celb_f_name'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_f_name'] ) ) : '';
	if ( '' === trim( $name ) ) {
		return celb_portal_form_html( $token, wp_unslash( $_POST ), __( 'Please enter the full name.', 'celb-mgmt' ) );
	}

	$post_id = wp_insert_post( array(
		'post_type'    => CELB_CPT,
		'post_status'  => 'draft',
		'post_title'   => $name,
		'post_content' => '',
	), true );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return celb_portal_form_html( $token, wp_unslash( $_POST ), __( 'Something went wrong saving your profile. Please try again.', 'celb-mgmt' ) );
	}

	update_post_meta( $post_id, '_celb_role', isset( $_POST['celb_f_role'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_f_role'] ) ) : '' );
	update_post_meta( $post_id, '_celb_nationality', isset( $_POST['celb_f_nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_f_nationality'] ) ) : '' );
	update_post_meta( $post_id, '_celb_bio', isset( $_POST['celb_f_bio'] ) ? wp_kses_post( wp_unslash( $_POST['celb_f_bio'] ) ) : '' );

	$bd = isset( $_POST['celb_f_birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_f_birthdate'] ) ) : '';
	if ( $bd && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bd ) ) {
		$bd = '';
	}
	update_post_meta( $post_id, '_celb_birthdate', $bd );
	update_post_meta( $post_id, '_celb_show_year', empty( $_POST['celb_f_show_year'] ) ? '' : '1' );

	foreach ( celb_social_platforms() as $key => $label ) {
		$url = isset( $_POST['celb_f_social'][ $key ] ) ? esc_url_raw( trim( wp_unslash( $_POST['celb_f_social'][ $key ] ) ) ) : '';
		update_post_meta( $post_id, '_celb_social_' . $key, $url );
	}

	$career = array();
	if ( ! empty( $_POST['celb_f_career'] ) && is_array( $_POST['celb_f_career'] ) ) {
		foreach ( wp_unslash( $_POST['celb_f_career'] ) as $row ) {
			$project    = isset( $row['project'] ) ? sanitize_text_field( $row['project'] ) : '';
			$role       = isset( $row['role'] ) ? sanitize_text_field( $row['role'] ) : '';
			$year       = isset( $row['year'] ) ? absint( $row['year'] ) : 0;
			$type       = isset( $row['type'] ) ? sanitize_text_field( $row['type'] ) : '';
			$special    = empty( $row['special'] ) ? 0 : 1;
			$production = empty( $row['production'] ) ? 0 : 1;
			if ( '' === $project && '' === $role && 0 === $year ) {
				continue;
			}
			$career[] = array( 'project' => $project, 'role' => $role, 'year' => $year, 'type' => $type, 'special' => $special, 'production' => $production );
		}
	}
	update_post_meta( $post_id, '_celb_career', $career );

	$awards = array();
	if ( ! empty( $_POST['celb_f_awards'] ) && is_array( $_POST['celb_f_awards'] ) ) {
		foreach ( wp_unslash( $_POST['celb_f_awards'] ) as $row ) {
			$festival = isset( $row['festival'] ) ? sanitize_text_field( $row['festival'] ) : '';
			$title    = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$project  = isset( $row['project'] ) ? sanitize_text_field( $row['project'] ) : '';
			$year     = isset( $row['year'] ) ? absint( $row['year'] ) : 0;
			$location = isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '';
			if ( '' === $festival && '' === $title && '' === $project && 0 === $year && '' === $location ) {
				continue;
			}
			$awards[] = array( 'festival' => $festival, 'title' => $title, 'project' => $project, 'year' => $year, 'location' => $location );
		}
	}
	update_post_meta( $post_id, '_celb_awards', $awards );

	update_post_meta( $post_id, '_celb_submission', 1 );
	update_post_meta( $post_id, '_celb_submitted', current_time( 'mysql' ) );

	// Notify the site admin.
	$admin_email = get_option( 'admin_email' );
	if ( $admin_email ) {
		$edit = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		wp_mail(
			$admin_email,
			sprintf( __( 'New celebrity submission: %s', 'celb-mgmt' ), $name ),
			sprintf( __( "A new profile was submitted via the front-end portal and saved as a Draft for review:\n\n%1\$s\n\nReview & publish: %2\$s", 'celb-mgmt' ), $name, $edit )
		);
	}

	return celb_portal_confirmation_html();
}

/* ---- Admin review aid: edit-screen notice (list columns: includes/admin-studio.php) ---- */

function celb_submission_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base || CELB_CPT !== $screen->post_type ) {
		return;
	}
	global $post;
	if ( ! $post || ! get_post_meta( $post->ID, '_celb_submission', true ) ) {
		return;
	}
	$when = get_post_meta( $post->ID, '_celb_submitted', true );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Self-submitted profile.', 'celb-mgmt' ) . '</strong> '
		. esc_html__( 'Submitted via the front-end portal', 'celb-mgmt' )
		. ( $when ? ' ' . esc_html( sprintf( __( 'on %s', 'celb-mgmt' ), $when ) ) : '' ) . '. '
		. esc_html__( 'Review the details, add the Profile Image and Hero banners, then Publish.', 'celb-mgmt' ) . '</p></div>';
}
add_action( 'admin_notices', 'celb_submission_notice' );

/* -------------------------------------------------------------------------
 * 12. NEWSROOM (celeb_news CPT + profile section)
 * ---------------------------------------------------------------------- */

function celb_register_news_cpt() {
	$labels = array(
		'name'               => __( 'Newsroom', 'celb-mgmt' ),
		'singular_name'      => __( 'News Article', 'celb-mgmt' ),
		'menu_name'          => __( 'Newsroom', 'celb-mgmt' ),
		'add_new'            => __( 'Add Article', 'celb-mgmt' ),
		'add_new_item'       => __( 'Add News Article', 'celb-mgmt' ),
		'edit_item'          => __( 'Edit News Article', 'celb-mgmt' ),
		'new_item'           => __( 'New Article', 'celb-mgmt' ),
		'all_items'          => __( 'Newsroom', 'celb-mgmt' ),
		'search_items'       => __( 'Search Articles', 'celb-mgmt' ),
		'not_found'          => __( 'No articles found', 'celb-mgmt' ),
	);
	register_post_type( 'celeb_news', array(
		'labels'       => $labels,
		'public'       => true,
		'show_in_menu' => 'edit.php?post_type=' . CELB_CPT,
		'has_archive'  => false,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		'rewrite'      => array( 'slug' => 'celebrity-news', 'with_front' => false ),
		'menu_icon'    => 'dashicons-megaphone',
	) );
}
add_action( 'init', 'celb_register_news_cpt' );

add_filter( 'enter_title_here', function ( $text, $post ) {
	if ( $post && 'celeb_news' === $post->post_type ) {
		return __( 'Headline', 'celb-mgmt' );
	}
	return $text;
}, 10, 2 );

/* ---- Editor UI: see includes/admin-studio.php ---- */

/* ---- Save ---- */
function celb_news_save( $post_id ) {
	if ( ! isset( $_POST['celb_news_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_news_nonce'] ), 'celb_news_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'celeb_news' ) {
		return;
	}

	update_post_meta( $post_id, '_news_celebrity', isset( $_POST['news_celebrity'] ) ? absint( $_POST['news_celebrity'] ) : 0 );
	update_post_meta( $post_id, '_news_card_img', isset( $_POST['news_card_img'] ) ? absint( $_POST['news_card_img'] ) : 0 );
	update_post_meta( $post_id, '_news_title_ar', isset( $_POST['news_title_ar'] ) ? sanitize_text_field( wp_unslash( $_POST['news_title_ar'] ) ) : '' );
	update_post_meta( $post_id, '_news_body_ar', isset( $_POST['news_body_ar'] ) ? wp_kses_post( wp_unslash( $_POST['news_body_ar'] ) ) : '' );
	update_post_meta( $post_id, '_news_header', isset( $_POST['news_header'] ) ? absint( $_POST['news_header'] ) : 0 );
	update_post_meta( $post_id, '_news_header_mobile', isset( $_POST['news_header_mobile'] ) ? absint( $_POST['news_header_mobile'] ) : 0 );
	update_post_meta( $post_id, '_news_location', isset( $_POST['news_location'] ) ? sanitize_text_field( wp_unslash( $_POST['news_location'] ) ) : '' );

	$gallery = array();
	if ( isset( $_POST['news_gallery'] ) ) {
		$raw     = explode( ',', sanitize_text_field( wp_unslash( $_POST['news_gallery'] ) ) );
		$gallery = array_values( array_filter( array_map( 'absint', $raw ) ) );
	}
	update_post_meta( $post_id, '_news_gallery', $gallery );

	$links = array();
	if ( isset( $_POST['news_links'] ) && is_array( $_POST['news_links'] ) ) {
		foreach ( wp_unslash( $_POST['news_links'] ) as $row ) {
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			if ( '' === $label && '' === $url ) {
				continue;
			}
			$links[] = array( 'label' => $label, 'url' => $url );
		}
	}
	update_post_meta( $post_id, '_news_links', $links );
}
add_action( 'save_post_celeb_news', 'celb_news_save' );

/* ---- Data helpers ---- */
function celb_get_news( $celeb_id ) {
	$q = new WP_Query( array(
		'post_type'      => 'celeb_news',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_news_celebrity',
		'meta_value'     => (int) $celeb_id,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	return $q->posts;
}

/* Detect whether a string reads right-to-left (contains Arabic script). */
function celb_is_rtl_text( $str ) {
	return (bool) preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', (string) $str );
}

/* Accent colour — fully controlled from Settings → Appearance. */
function celb_accent() {
	$s = function_exists( 'celb_get_settings' ) ? celb_get_settings() : array();
	return ! empty( $s['accent'] ) ? $s['accent'] : '#999999';
}

function celb_news_link_icon( $url ) {
	$u = strtolower( (string) $url );
	$key = 'website';
	if ( strpos( $u, 'youtu' ) !== false ) {
		$key = 'youtube';
	} elseif ( strpos( $u, 'instagram' ) !== false ) {
		$key = 'instagram';
	} elseif ( strpos( $u, 'tiktok' ) !== false ) {
		$key = 'tiktok';
	} elseif ( strpos( $u, 'facebook' ) !== false || strpos( $u, 'fb.' ) !== false ) {
		$key = 'facebook';
	} elseif ( strpos( $u, 'twitter' ) !== false || strpos( $u, 'x.com' ) !== false ) {
		$key = 'x';
	} elseif ( strpos( $u, 'threads' ) !== false ) {
		$key = 'threads';
	}
	return celb_social_icon( $key );
}

/* ---- Download-all-media ZIP endpoint ---- */
function celb_news_zip() {
	$news = isset( $_GET['news'] ) ? absint( $_GET['news'] ) : 0;
	if ( ! $news || 'celeb_news' !== get_post_type( $news ) || 'publish' !== get_post_status( $news ) ) {
		status_header( 404 );
		wp_die( esc_html__( 'Article not found.', 'celb-mgmt' ) );
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'ZIP downloads are not supported on this server.', 'celb-mgmt' ) );
	}

	$ids    = array();
	$header = get_post_thumbnail_id( $news );
	if ( $header ) {
		$ids[] = $header;
	}
	$gallery = get_post_meta( $news, '_news_gallery', true );
	if ( is_array( $gallery ) ) {
		$ids = array_merge( $ids, array_map( 'absint', $gallery ) );
	}
	$ids = array_unique( array_filter( $ids ) );
	if ( empty( $ids ) ) {
		wp_die( esc_html__( 'No media to download.', 'celb-mgmt' ) );
	}

	$tmp = wp_tempnam( 'celb-news-' . $news . '.zip' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		wp_die( esc_html__( 'Could not create the ZIP file.', 'celb-mgmt' ) );
	}
	$n = 1;
	foreach ( $ids as $id ) {
		$path = get_attached_file( $id );
		if ( $path && file_exists( $path ) ) {
			$zip->addFile( $path, $n . '-' . basename( $path ) );
			$n++;
		}
	}
	$zip->close();

	$name = sanitize_file_name( ( get_the_title( $news ) ? get_the_title( $news ) : 'newsroom-' . $news ) ) . '-media.zip';
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . filesize( $tmp ) );
	readfile( $tmp );
	@unlink( $tmp );
	exit;
}
add_action( 'wp_ajax_celb_news_zip', 'celb_news_zip' );
add_action( 'wp_ajax_nopriv_celb_news_zip', 'celb_news_zip' );

/* ---- Image resolvers (dedicated header image, featured thumbnail) ---- */
function celb_news_header_img( $news_id, $size = 'full' ) {
	$h = (int) get_post_meta( $news_id, '_news_header', true );
	if ( $h ) {
		$u = wp_get_attachment_image_url( $h, $size );
		if ( $u ) {
			return $u;
		}
	}
	return get_the_post_thumbnail_url( $news_id, $size ); // fall back to featured image
}

/* Returns array( desktop_url, mobile_url ) for the article hero, with
   fallbacks: desktop -> _news_header -> featured; mobile -> _news_header_mobile
   -> desktop. */
function celb_news_hero_pair( $news_id, $size = 'full' ) {
	$featured = get_the_post_thumbnail_url( $news_id, $size );
	$desk_id  = (int) get_post_meta( $news_id, '_news_header', true );
	$mob_id   = (int) get_post_meta( $news_id, '_news_header_mobile', true );

	$desk = $desk_id ? wp_get_attachment_image_url( $desk_id, $size ) : '';
	if ( ! $desk ) {
		$desk = $featured ? $featured : '';
	}
	$mob = $mob_id ? wp_get_attachment_image_url( $mob_id, $size ) : '';
	if ( ! $mob ) {
		$mob = $desk;
	}
	return array( $desk, $mob );
}
function celb_news_thumb_img( $news_id, $size = 'medium' ) {
	$u = get_the_post_thumbnail_url( $news_id, $size ); // featured image first
	if ( $u ) {
		return $u;
	}
	$h = (int) get_post_meta( $news_id, '_news_header', true );
	return $h ? wp_get_attachment_image_url( $h, $size ) : '';
}

/* ---- Reusable list item (used by profile + global newsroom) ---- */
function celb_news_item_html( $n, $show_celeb = false ) {
	$thumb = celb_news_thumb_img( $n->ID, 'medium' );
	$cid   = (int) get_post_meta( $n->ID, '_news_celebrity', true );
	ob_start();
	?>
	<a class="celb-news-item" href="<?php echo esc_url( get_permalink( $n->ID ) ); ?>" data-celeb="<?php echo esc_attr( $cid ); ?>">
		<span class="celb-news-thumb"<?php echo $thumb ? ' style="background-image:url(' . esc_url( $thumb ) . ')"' : ''; ?>></span>
		<span class="celb-news-headline">
			<span class="celb-news-title"><?php echo esc_html( get_the_title( $n->ID ) ); ?></span>
			<?php if ( $show_celeb && $cid ) : ?>
				<span class="celb-news-celeb"><?php echo esc_html( get_the_title( $cid ) ); ?></span>
			<?php endif; ?>
			<span class="celb-news-date"><?php echo esc_html( get_the_date( '', $n->ID ) ); ?></span>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

/* ---- Newsroom photo card (demo style: image + gradient + title/meta overlay) ----
   Used only by the global [CLEB_newsroom] grid. The profile-page list keeps
   using celb_news_item_html() above, so this redesign does not touch it. */
function celb_news_card_html( $n ) {
	$img       = celb_news_thumb_img( $n->ID, 'large' );
	$cid       = (int) get_post_meta( $n->ID, '_news_celebrity', true );
	$loc       = trim( (string) get_post_meta( $n->ID, '_news_location', true ) );
	$title     = get_the_title( $n->ID );
	$title_dir = celb_is_rtl_text( $title ) ? 'rtl' : 'ltr';
	$date      = get_the_date( 'd.m.Y', $n->ID );
	$meta      = $loc ? $loc . ' / ' . $date : $date;
	$style     = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
	ob_start();
	?>
	<a class="celb-news-card" href="<?php echo esc_url( get_permalink( $n->ID ) ); ?>" data-celeb="<?php echo esc_attr( $cid ); ?>"<?php echo $style; ?>>
		<span class="celb-news-card-shade"></span>
		<span class="celb-news-card-text">
			<span class="celb-news-card-title" dir="<?php echo esc_attr( $title_dir ); ?>"><?php echo esc_html( $title ); ?></span>
			<span class="celb-news-card-meta" dir="ltr"><?php echo esc_html( $meta ); ?></span>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

/* ---- Reusable popup (used by profile + global newsroom) ---- */
function celb_news_popup_html( $n ) {
	$header_img = celb_news_header_img( $n->ID, 'full' );
	$loc        = get_post_meta( $n->ID, '_news_location', true );
	$ngallery   = get_post_meta( $n->ID, '_news_gallery', true );
	$ngallery   = is_array( $ngallery ) ? array_filter( array_map( 'absint', $ngallery ) ) : array();
	$nlinks     = get_post_meta( $n->ID, '_news_links', true );
	$nlinks     = is_array( $nlinks ) ? $nlinks : array();
	$n_title    = get_the_title( $n->ID );
	$title_dir  = celb_is_rtl_text( $n_title ) ? 'rtl' : 'ltr';
	$body_dir   = celb_is_rtl_text( wp_strip_all_tags( $n->post_content ) ) ? 'rtl' : 'ltr';
	$title_ar   = (string) get_post_meta( $n->ID, '_news_title_ar', true );
	$body_ar    = (string) get_post_meta( $n->ID, '_news_body_ar', true );
	$has_ar     = ( '' !== trim( $title_ar ) || '' !== trim( wp_strip_all_tags( $body_ar ) ) );
	$zip_url    = add_query_arg( array( 'action' => 'celb_news_zip', 'news' => $n->ID ), admin_url( 'admin-ajax.php' ) );
	ob_start();
	?>
	<div class="celb-news-popup" id="celb-news-popup-<?php echo esc_attr( $n->ID ); ?>" role="dialog" aria-modal="true" aria-hidden="true">
		<div class="celb-news-overlay" data-news-close></div>
		<div class="celb-news-modal">
			<button type="button" class="celb-news-close" data-news-close aria-label="<?php esc_attr_e( 'Close', 'celb-mgmt' ); ?>">&times;</button>
			<div class="celb-news-header"<?php echo $header_img ? ' style="background-image:url(' . esc_url( $header_img ) . ')"' : ''; ?>>
				<div class="celb-news-header-gradient"></div>
				<div class="celb-news-header-text">
					<?php if ( $has_ar ) : ?>
					<div class="celb-news-lang" role="group" aria-label="Language">
						<button type="button" class="celb-news-lang-btn is-active" data-lang="en">EN</button>
						<button type="button" class="celb-news-lang-btn" data-lang="ar" lang="ar">ع</button>
					</div>
					<?php endif; ?>
					<h3 class="celb-news-h" data-lang-en dir="<?php echo esc_attr( $title_dir ); ?>"><?php echo esc_html( $n_title ); ?></h3>
					<?php if ( $has_ar ) : ?>
					<h3 class="celb-news-h" data-lang-ar dir="rtl" hidden><?php echo esc_html( '' !== $title_ar ? $title_ar : $n_title ); ?></h3>
					<?php endif; ?>
					<div class="celb-news-meta" dir="<?php echo esc_attr( $title_dir ); ?>">
						<span class="celb-news-pubdate"><?php echo esc_html( get_the_date( '', $n->ID ) ); ?></span>
						<?php if ( $loc ) : ?><span class="celb-news-loc"><?php echo esc_html( $loc ); ?></span><?php endif; ?>
					</div>
				</div>
			</div>
			<div class="celb-news-content">
				<div class="celb-news-body" data-lang-en dir="<?php echo esc_attr( $body_dir ); ?>"><?php echo apply_filters( 'the_content', $n->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php if ( $has_ar ) : ?>
				<div class="celb-news-body" data-lang-ar dir="rtl" hidden><?php echo apply_filters( 'the_content', $body_ar ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $ngallery ) ) : ?>
					<div class="celb-news-gallery">
						<?php foreach ( $ngallery as $gid ) :
							$gt = wp_get_attachment_image_url( $gid, 'large' );
							$gf = wp_get_attachment_image_url( $gid, 'full' );
							if ( ! $gt ) {
								continue;
							} ?>
							<button type="button" class="celb-news-gthumb" data-full="<?php echo esc_url( $gf ); ?>" style="background-image:url(<?php echo esc_url( $gt ); ?>)" aria-label="<?php esc_attr_e( 'View image', 'celb-mgmt' ); ?>"></button>
						<?php endforeach; ?>
					</div>
					<div class="celb-news-download">
						<a class="celb-news-dl" href="<?php echo esc_url( $zip_url ); ?>"><?php esc_html_e( 'Download all media', 'celb-mgmt' ); ?></a>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $nlinks ) ) : ?>
					<div class="celb-news-links">
						<?php foreach ( $nlinks as $l ) :
							if ( empty( $l['url'] ) ) {
								continue;
							}
							$label = ! empty( $l['label'] ) ? $l['label'] : $l['url'];
							?>
							<a class="celb-news-link" href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo celb_news_link_icon( $l['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php echo esc_html( $label ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ---- Global newsroom page  [CLEB_newsroom] ---- */
function celb_newsroom_shortcode( $atts ) {
	celb_ensure_frontend_assets();
	$q = new WP_Query( array(
		'post_type'      => 'celeb_news',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<div class="celb-scope celb-newsroom-page"><p class="celb-news-empty-static">' . esc_html__( 'No news yet.', 'celb-mgmt' ) . '</p></div>';
	}
	$news = $q->posts;

	$celebs = array();
	foreach ( $news as $n ) {
		$cid = (int) get_post_meta( $n->ID, '_news_celebrity', true );
		if ( $cid && ! isset( $celebs[ $cid ] ) ) {
			$celebs[ $cid ] = get_the_title( $cid );
		}
	}
	asort( $celebs );

	ob_start();
	echo '<div class="celb-scope celb-newsroom-page">';

	echo '<div class="celb-newsroom-bar">';
	echo '<div class="celb-newsroom-filter">';
	echo '<label class="celb-label" for="celb-news-filter">' . esc_html__( 'Filter by artist', 'celb-mgmt' ) . '</label>';
	echo '<select id="celb-news-filter" class="celb-news-select">';
	echo '<option value="">' . esc_html__( 'All artists', 'celb-mgmt' ) . '</option>';
	foreach ( $celebs as $id => $name ) {
		echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
	}
	echo '</select>';
	echo '</div>'; // filter
	echo '</div>'; // bar

	echo '<div class="celb-news-grid">';
	foreach ( $news as $n ) {
		echo celb_news_card_html( $n ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>'; // grid
	echo '<p class="celb-news-empty" hidden>' . esc_html__( 'No articles for this artist.', 'celb-mgmt' ) . '</p>';

	echo '</div>'; // newsroom-page
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'CLEB_newsroom', 'celb_newsroom_shortcode' );

/* Find the URL of the page that hosts the full [CLEB_newsroom] shortcode, so
 * the homepage carousel's "View More" can link to it automatically. Cached. */
function celb_find_newsroom_url() {
	$cached = get_transient( 'celb_newsroom_url' );
	if ( false !== $cached ) {
		return $cached;
	}
	$url   = '';
	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 300,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );
	foreach ( $pages as $pid ) {
		$content = (string) get_post_field( 'post_content', $pid );
		if ( has_shortcode( $content, 'CLEB_newsroom' ) ) {
			$url = get_permalink( $pid );
			break;
		}
	}
	set_transient( 'celb_newsroom_url', $url, HOUR_IN_SECONDS );
	return $url;
}

/* ---- Homepage newsroom carousel  [CLEB_newsroom_carousel]
 *      6 most recent articles; 4 per view on desktop, 1 per view on mobile,
 *      with a section title above and a "View More" button below. ---- */
function celb_newsroom_carousel_shortcode( $atts ) {
	celb_ensure_frontend_assets();
	$atts = shortcode_atts( array(
		'title'      => "Our Stars' Updates",
		'count'      => 6,
		'more_url'   => '',
		'more_label' => __( 'View More', 'celb-mgmt' ),
	), $atts, 'CLEB_newsroom_carousel' );

	$count = max( 1, (int) $atts['count'] );

	$q = new WP_Query( array(
		'post_type'      => 'celeb_news',
		'post_status'    => 'publish',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}

	$more_url = trim( (string) $atts['more_url'] );
	if ( '' === $more_url ) {
		$found    = celb_find_newsroom_url();
		$more_url = $found ? $found : home_url( '/newsroom/' );
	}

	ob_start();
	echo '<div class="celb-scope celb-news-carousel-wrap">';

	if ( '' !== trim( (string) $atts['title'] ) ) {
		echo '<h2 class="celb-news-carousel-title">' . esc_html( $atts['title'] ) . '</h2>';
	}

	echo '<div class="celb-carousel celb-news-carousel" data-celb-carousel>';
	echo '<button type="button" class="celb-carousel-nav celb-prev" aria-label="' . esc_attr__( 'Previous', 'celb-mgmt' ) . '">&#8249;</button>';
	echo '<div class="celb-carousel-track">';
	while ( $q->have_posts() ) {
		$q->the_post();
		echo '<div class="celb-carousel-cell">' . celb_news_card_html( get_post() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>'; // track
	echo '<button type="button" class="celb-carousel-nav celb-next" aria-label="' . esc_attr__( 'Next', 'celb-mgmt' ) . '">&#8250;</button>';
	echo '</div>'; // carousel

	if ( $more_url ) {
		echo '<div class="celb-news-carousel-cta"><a class="celb-news-viewmore" href="' . esc_url( $more_url ) . '">' . esc_html( $atts['more_label'] ) . '</a></div>';
	}

	echo '</div>'; // wrap
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'CLEB_newsroom_carousel', 'celb_newsroom_carousel_shortcode' );

/* -------------------------------------------------------------------------
 * 13. SMART LINK  (premium shareable landing page at /{name}/)
 * ---------------------------------------------------------------------- */

/* Slug from a celebrity name: lowercase, alphanumerics only (spaces removed). */
function celb_smartlink_slug( $name ) {
	$slug = strtolower( remove_accents( $name ) );
	$slug = preg_replace( '/[^a-z0-9]+/', '', $slug );
	return $slug;
}

/* Rebuild the slug => post_id map (option). Runs only when profiles change. */
function celb_rebuild_smartlinks() {
	$map = array();
	$posts = get_posts( array(
		'post_type'      => CELB_CPT,
		'post_status'    => 'publish',
		'numberposts'    => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'suppress_filters' => true,
	) );
	foreach ( $posts as $pid ) {
		$base = celb_smartlink_slug( get_the_title( $pid ) );
		if ( '' === $base ) {
			$base = 'celebrity' . $pid;
		}
		$slug = $base;
		$i    = 2;
		while ( isset( $map[ $slug ] ) ) {       // collision: append a number
			$slug = $base . $i;
			$i++;
		}
		$map[ $slug ] = $pid;
		update_post_meta( $pid, '_celb_smartlink', $slug );
	}
	update_option( 'celb_smartlinks', $map, false );
	return $map;
}

function celb_get_smartlinks() {
	$map = get_option( 'celb_smartlinks', null );
	if ( ! is_array( $map ) ) {
		$map = celb_rebuild_smartlinks();
	}
	return $map;
}

/* The public URL for a celebrity's smart link. */
function celb_smartlink_url( $post_id ) {
	$slug = get_post_meta( $post_id, '_celb_smartlink', true );
	if ( ! $slug ) {
		$slug = celb_smartlink_slug( get_the_title( $post_id ) );
	}
	return home_url( '/' . $slug . '/' );
}

/* Register one rewrite rule per known smart link slug (collision-safe). */
function celb_register_smartlink_rules() {
	$map = celb_get_smartlinks();
	foreach ( $map as $slug => $pid ) {
		add_rewrite_rule( '^' . preg_quote( $slug, '#' ) . '/?$', 'index.php?celb_smartlink=' . $slug, 'top' );
	}
}
add_action( 'init', 'celb_register_smartlink_rules', 20 );

/* Auto-flush rewrite rules after a plugin update (no manual Permalinks save). */
function celb_maybe_flush_rewrites() {
	if ( get_option( 'celb_rewrite_version' ) !== CELB_VERSION ) {
		celb_rebuild_smartlinks();
		celb_register_smartlink_rules();
		if ( function_exists( 'celb_rate_build_slug_map' ) ) {
			celb_rate_build_slug_map();
		}
		flush_rewrite_rules( false );
		update_option( 'celb_rewrite_version', CELB_VERSION, false );
	}
}
add_action( 'init', 'celb_maybe_flush_rewrites', 99 );

/* Never let WordPress guess-redirect a smart link slug to an attachment/image. */
add_filter( 'redirect_canonical', function ( $redirect, $requested ) {
	$path = trim( (string) wp_parse_url( $requested, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return $redirect;
	}
	if ( celb_smartlink_lookup( $path ) ) {
		return false;
	}
	return $redirect;
}, 10, 2 );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'celb_smartlink';
	return $vars;
} );

/* Resolve a slug to a published celebrity ID: option map first, then a direct
   meta lookup so a stale/missing map can't break a link. */
function celb_smartlink_lookup( $slug ) {
	$slug = (string) $slug;
	$map  = celb_get_smartlinks();
	if ( isset( $map[ $slug ] ) ) {
		return (int) $map[ $slug ];
	}
	if ( ! preg_match( '/^[a-z0-9]+$/', $slug ) ) {
		return 0; // smart link slugs are lowercase alphanumerics only
	}
	$ids = get_posts( array(
		'post_type'        => CELB_CPT,
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'meta_key'         => '_celb_smartlink',
		'meta_value'       => $slug,
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/* Primary router: catch /{slug}/ directly, independent of rewrite-rule flushing
   (some hosts cache rewrite rules aggressively). Single-segment paths only. */
function celb_smartlink_catch( $wp ) {
	$path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
	if ( '' === $path || false !== strpos( $path, '/' ) ) {
		return;
	}
	if ( celb_smartlink_lookup( $path ) ) {
		$wp->query_vars   = array( 'celb_smartlink' => $path );
		$wp->matched_rule = '^' . $path . '/?$';
	}
}
add_action( 'parse_request', 'celb_smartlink_catch' );

/* Keep the map + rewrite rules in sync when profiles change. */
function celb_smartlinks_resync( $post_id = 0 ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( get_post_type( $post_id ) !== CELB_CPT ) {
			return;
		}
	}
	celb_rebuild_smartlinks();
	celb_register_smartlink_rules();
	flush_rewrite_rules( false );
}
add_action( 'save_post_' . CELB_CPT, 'celb_smartlinks_resync' );
add_action( 'trashed_post', 'celb_smartlinks_resync' );
add_action( 'untrashed_post', 'celb_smartlinks_resync' );
add_action( 'before_delete_post', 'celb_smartlinks_resync' );

/* Resolve and render the smart link page. */
function celb_smartlink_render() {
	$slug = get_query_var( 'celb_smartlink' );
	if ( ! $slug ) {
		return;
	}
	$pid = celb_smartlink_lookup( $slug );

	if ( ! $pid || get_post_status( $pid ) !== 'publish' ) {
		return; // let WordPress 404 naturally
	}

	// Note: the "private" toggle hides the on-site profile page and makes the
	// grid card non-clickable, but the Smart Link is a deliberately shared asset,
	// so it stays available for any published profile.

	celb_smartlink_output( $pid );
	exit;
}
add_action( 'template_redirect', 'celb_smartlink_render', 1 );

/* First paragraph of the biography, plain text. */
function celb_first_paragraph( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}
	if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
		$text = wp_strip_all_tags( $m[1] );
		if ( '' !== trim( $text ) ) {
			return trim( $text );
		}
	}
	$parts = preg_split( '/\n\s*\n/', wp_strip_all_tags( $html ) );
	return trim( $parts[0] );
}

/* Best available portrait: Profile Image > Featured > Desktop Hero. */
function celb_smartlink_image( $post_id, $size = 'large' ) {
	$profile = (int) get_post_meta( $post_id, '_celb_profile', true );
	$img     = $profile ? wp_get_attachment_image_url( $profile, $size ) : '';
	if ( ! $img ) {
		$img = get_the_post_thumbnail_url( $post_id, $size );
	}
	if ( ! $img ) {
		$hero = (int) get_post_meta( $post_id, '_celb_hero_desktop', true );
		$img  = $hero ? wp_get_attachment_image_url( $hero, $size ) : '';
	}
	return $img;
}

/* The iLike Agency management links shown on every smart link. */
function celb_agency_socials() {
	return array(
		'instagram' => 'https://www.instagram.com/iLikeAgency',
		'facebook'  => 'https://www.facebook.com/iLikeAgency',
		'tiktok'    => 'https://www.tiktok.com/@ilikeagency',
	);
}

/* Output the full standalone premium landing page. */
function celb_smartlink_output( $post_id ) {
	$name      = get_the_title( $post_id );
	$first     = strtok( $name, ' ' );
	$subtitle  = celb_subtitle( $post_id );
	$bio       = celb_first_paragraph( celb_get_bio( $post_id ) );
	$img       = celb_smartlink_image( $post_id, 'large' );
	$og_img    = celb_smartlink_image( $post_id, 'full' );
	$socials   = celb_get_socials( $post_id );
	$year_now  = (int) current_time( 'Y' );
	$roles     = array();
	foreach ( celb_get_career( $post_id ) as $c ) {
		$coming = ! empty( $c['production'] );
		if ( $coming || (int) $c['year'] === $year_now ) {
			$c['coming'] = $coming;
			$roles[]     = $c;
		}
	}
	$settings  = celb_get_settings();
	$cpage     = ! empty( $settings['contact_page_id'] ) ? (int) $settings['contact_page_id'] : 0;
	if ( $cpage && get_post_status( $cpage ) ) {
		$talk_url    = add_query_arg( 'artist', $post_id, get_permalink( $cpage ) );
		$talk_target = '';
	} else {
		$talk_url    = ! empty( $settings['cta_url'] ) ? $settings['cta_url'] : 'https://ilikeagency.co/lets-talk/';
		$talk_target = ' target="_blank" rel="noopener noreferrer"';
	}
	$accent    = celb_accent();
	$logo      = celb_logo_url();
	$page_url  = celb_smartlink_url( $post_id );
	$agency    = celb_agency_socials();
	$locked      = (bool) get_post_meta( $post_id, '_celb_locked', true );
	$profile_url = get_permalink( $post_id );
	$news_all    = celb_get_news( $post_id );
	$latest      = ! empty( $news_all ) ? $news_all[0] : null;

	header( 'Content-Type: text/html; charset=utf-8' );
	?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title><?php echo esc_html( $name ); ?><?php echo $subtitle ? ' — ' . esc_html( $subtitle ) : ''; ?></title>
<?php if ( $bio ) : ?><meta name="description" content="<?php echo esc_attr( wp_trim_words( $bio, 30, '…' ) ); ?>" /><?php endif; ?>
<meta property="og:type" content="profile" />
<meta property="og:title" content="<?php echo esc_attr( $name ); ?>" />
<?php if ( $bio ) : ?><meta property="og:description" content="<?php echo esc_attr( wp_trim_words( $bio, 30, '…' ) ); ?>" /><?php endif; ?>
<meta property="og:url" content="<?php echo esc_url( $page_url ); ?>" />
<?php if ( $og_img ) : ?><meta property="og:image" content="<?php echo esc_url( $og_img ); ?>" /><?php endif; ?>
<meta name="twitter:card" content="summary_large_image" />
<meta name="robots" content="index, follow" />
<link rel="canonical" href="<?php echo esc_url( $page_url ); ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<style>
:root{--gold:<?php echo esc_html( $accent ); ?>;--bg:#000;--text:#fff;--muted:rgba(255,255,255,.62);--line:rgba(255,255,255,.12);}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{background:var(--bg);}
body{font-family:'Inter',system-ui,sans-serif;color:var(--text);-webkit-font-smoothing:antialiased;line-height:1.6;min-height:100vh;padding:env(safe-area-inset-top) 0 calc(40px + env(safe-area-inset-bottom));}
.wrap{max-width:480px;margin:0 auto;padding:0 22px;}
a{color:inherit;text-decoration:none;}
.fade{opacity:0;transform:translateY(14px);animation:fade .8s cubic-bezier(.2,.7,.2,1) forwards;}
@keyframes fade{to{opacity:1;transform:none;}}
@media (prefers-reduced-motion:reduce){.fade{animation:none;opacity:1;transform:none;}}
.hero{position:relative;width:100%;aspect-ratio:4/5;border-radius:18px;overflow:hidden;margin-top:30px;background:#0c0c0c center/cover no-repeat;<?php echo $img ? 'background-image:url(' . esc_url( $img ) . ');' : ''; ?>}
.hero::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.9) 0%,rgba(0,0,0,.2) 38%,rgba(0,0,0,0) 70%);}
.id{text-align:center;margin-top:26px;}
.id h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:clamp(2.1rem,9vw,2.9rem);line-height:1.02;letter-spacing:.01em;}
.id .role{margin-top:10px;font-size:.7rem;font-weight:500;letter-spacing:.28em;text-transform:uppercase;color:var(--gold);}
.bio{margin:26px auto 0;max-width:40ch;text-align:center;color:rgba(255,255,255,.8);font-size:1rem;}
.label{margin-top:38px;text-align:center;font-size:.66rem;font-weight:600;letter-spacing:.26em;text-transform:uppercase;color:var(--muted);}
.roles{margin-top:18px;}
.role-item{display:flex;align-items:baseline;justify-content:space-between;gap:14px;padding:15px 2px;border-bottom:1px solid var(--line);}
.role-item:first-child{border-top:1px solid var(--line);}
.role-main{min-width:0;}
.role-project{display:block;font-family:'Cormorant Garamond',serif;font-weight:600;font-size:1.3rem;line-height:1.12;}
.role-meta{display:block;margin-top:3px;font-size:.72rem;letter-spacing:.05em;color:var(--muted);}
.role-year{flex:0 0 auto;font-size:.64rem;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);white-space:nowrap;padding-top:4px;}
.role-year.coming{position:relative;padding-left:14px;}
.role-year.coming::before{content:"";position:absolute;left:0;top:50%;width:6px;height:6px;border-radius:50%;background:var(--gold);transform:translateY(-50%);animation:pulse 1.8s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.35;}}
@media (prefers-reduced-motion:reduce){.role-year.coming::before{animation:none;}}
.icons{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:16px;}
.icon{display:flex;align-items:center;justify-content:center;width:50px;height:50px;border-radius:50%;border:1px solid var(--line);color:#fff;transition:transform .25s ease,background .25s ease,color .25s ease,border-color .25s ease;}
.icon:hover{background:var(--gold);border-color:var(--gold);color:#000;transform:translateY(-3px);}
.icon svg{width:21px;height:21px;}
.divider{height:1px;background:var(--line);margin:40px 0 0;}
.talk{display:block;margin-top:26px;text-align:center;background:var(--gold);color:#000;font-weight:600;font-size:.82rem;letter-spacing:.18em;text-transform:uppercase;padding:18px 24px;border-radius:14px;transition:transform .25s ease,filter .25s ease;}
.talk:hover{transform:translateY(-2px);filter:brightness(1.05);}
.viewprofile{display:block;margin-top:14px;text-align:center;background:transparent;color:#fff;border:1px solid rgba(255,255,255,.28);font-weight:600;font-size:.82rem;letter-spacing:.18em;text-transform:uppercase;padding:17px 24px;border-radius:14px;transition:border-color .25s ease,transform .25s ease;}
.viewprofile:hover{transform:translateY(-2px);border-color:var(--gold);color:var(--gold);}
.newscard{display:flex;align-items:center;gap:14px;margin-top:8px;padding:12px;border:1px solid rgba(255,255,255,.10);border-radius:14px;background:rgba(255,255,255,.03);text-align:left;transition:border-color .25s ease,transform .25s ease;}
.newscard:hover{transform:translateY(-2px);border-color:rgba(153,153,153,.6);}
.newscard-thumb{flex:0 0 64px;width:64px;height:64px;border-radius:10px;background-size:cover;background-position:center;background-repeat:no-repeat;}
.newscard-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;}
.newscard-title{font-size:.95rem;line-height:1.32;color:#fff;font-weight:600;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.newscard-date{font-size:.72rem;letter-spacing:.04em;color:rgba(255,255,255,.55);}
.newscard.rtl{text-align:right;}
.newscard.rtl .newscard-body{align-items:flex-end;}
.foot{margin-top:46px;text-align:center;}
.foot img{height:34px;width:auto;opacity:.92;}
.foot .tag{margin-top:12px;font-size:.62rem;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.4);}
</style>
</head>
<body>
<div class="wrap">
	<?php if ( $img ) : ?>
		<div class="hero fade" style="animation-delay:.02s"></div>
	<?php endif; ?>

	<div class="id fade" style="animation-delay:.10s">
		<h1><?php echo esc_html( $name ); ?></h1>
		<?php if ( $subtitle ) : ?><div class="role"><?php echo esc_html( $subtitle ); ?></div><?php endif; ?>
	</div>

	<?php if ( $bio ) : ?>
		<p class="bio fade" style="animation-delay:.16s"><?php echo esc_html( $bio ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $roles ) ) : ?>
		<div class="label fade" style="animation-delay:.18s"><?php esc_html_e( 'Recent & Upcoming Roles', 'celb-mgmt' ); ?></div>
		<div class="roles fade" style="animation-delay:.20s">
			<?php foreach ( $roles as $r ) : ?>
				<div class="role-item">
					<div class="role-main">
						<span class="role-project"><?php echo esc_html( $r['project'] ); ?></span>
						<?php
						$rmeta = array();
						if ( ! empty( $r['role'] ) ) {
							$rmeta[] = $r['role'];
						}
						if ( ! empty( $r['type'] ) ) {
							$rmeta[] = $r['type'];
						}
						if ( ! empty( $r['special'] ) ) {
							$rmeta[] = __( 'Special Appearance', 'celb-mgmt' );
						}
						if ( $rmeta ) :
							?>
							<span class="role-meta"><?php echo esc_html( implode( ' · ', $rmeta ) ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $r['coming'] ) ) : ?>
						<span class="role-year coming"><?php esc_html_e( 'Coming Soon', 'celb-mgmt' ); ?></span>
					<?php elseif ( ! empty( $r['year'] ) ) : ?>
						<span class="role-year"><?php echo esc_html( $r['year'] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	if ( $latest ) :
		$n_title = get_the_title( $latest->ID );
		$n_url   = get_permalink( $latest->ID );
		$n_thumb = celb_news_thumb_img( $latest->ID, 'medium' );
		$n_date  = get_the_date( '', $latest->ID );
		$n_rtl   = (bool) preg_match( '/\p{Arabic}/u', $n_title );
		?>
		<div class="label fade" style="animation-delay:.205s"><?php esc_html_e( 'Latest News', 'celb-mgmt' ); ?></div>
		<a class="newscard fade<?php echo $n_rtl ? ' rtl' : ''; ?>" style="animation-delay:.215s" href="<?php echo esc_url( $n_url ); ?>" target="_blank" rel="noopener">
			<?php if ( $n_thumb ) : ?>
				<span class="newscard-thumb" style="background-image:url('<?php echo esc_url( $n_thumb ); ?>');"></span>
			<?php endif; ?>
			<span class="newscard-body"<?php echo $n_rtl ? ' dir="rtl"' : ''; ?>>
				<span class="newscard-title"><?php echo esc_html( $n_title ); ?></span>
				<?php if ( $n_date ) : ?>
					<span class="newscard-date"><?php echo esc_html( $n_date ); ?></span>
				<?php endif; ?>
			</span>
		</a>
	<?php endif; ?>

	<?php if ( ! empty( $socials ) ) : ?>
		<div class="label fade" style="animation-delay:.22s"><?php echo esc_html( sprintf( /* translators: %s: first name */ __( 'Follow %s', 'celb-mgmt' ), $first ) ); ?></div>
		<div class="icons fade" style="animation-delay:.26s">
			<?php foreach ( $socials as $key => $s ) : ?>
				<a class="icon" href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $s['label'] ); ?>"><?php echo celb_social_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="divider fade" style="animation-delay:.30s"></div>

	<div class="label fade" style="animation-delay:.34s"><?php esc_html_e( 'Contact My Management', 'celb-mgmt' ); ?></div>
	<div class="icons fade" style="animation-delay:.38s">
		<?php foreach ( $agency as $key => $url ) : ?>
			<a class="icon" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="iLike Agency <?php echo esc_attr( $key ); ?>"><?php echo celb_social_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php endforeach; ?>
	</div>

	<a class="talk fade" style="animation-delay:.42s" href="<?php echo esc_url( $talk_url ); ?>"<?php echo $talk_target; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( "Let's Talk", 'celb-mgmt' ); ?></a>

	<?php
	$rate_url = celb_rate_smartlink_url( $post_id );
	if ( $rate_url && get_post_meta( $post_id, '_celb_sl_rate', true ) !== '0' ) : ?>
	<a class="viewprofile fade" style="animation-delay:.44s" href="<?php echo esc_url( $rate_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Rate Card', 'celb-mgmt' ); ?></a>
	<?php endif; ?>

	<?php if ( ! $locked && $profile_url ) : ?>
	<a class="viewprofile fade" style="animation-delay:.45s" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'View Full Profile', 'celb-mgmt' ); ?></a>
	<?php endif; ?>

	<div class="foot fade" style="animation-delay:.48s">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $logo ); ?>" alt="iLike Agency" /></a>
	</div>
</div>
</body>
</html>
	<?php
}

/* The enabled rate-card URL for a celebrity, or '' if none. */
function celb_rate_smartlink_url( $celeb_id ) {
	$card = celb_rate_card_for_celeb( $celeb_id );
	if ( ! $card ) {
		return '';
	}
	$enabled = get_post_meta( $card, '_rate_enabled', true ) === '1';
	$pw      = (string) get_post_meta( $card, '_rate_pw', true );
	if ( ! $enabled || '' === $pw ) {
		return '';
	}
	return celb_rate_card_url( $card );
}

/* Save the smart-link rate toggle. */
add_action( 'save_post_' . CELB_CPT, function ( $post_id ) {
	if ( ! isset( $_POST['celb_sl_rate_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_sl_rate_nonce'] ), 'celb_sl_rate_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_celb_sl_rate', empty( $_POST['celb_sl_rate'] ) ? '0' : '1' );
} );

/* -------------------------------------------------------------------------
 * 14. MOBILE MANAGE APP  ([CLEB_manage] + REST API)
 *     Secure: reuses WordPress login (site credentials) + capability checks
 *     + REST cookie nonce. No separate password system.
 * ---------------------------------------------------------------------- */

function celb_rest_can_edit() {
	return current_user_can( 'edit_posts' );
}
function celb_rest_can_admin() {
	return current_user_can( 'manage_options' );
}

/* Serialize a profile for the editor. */
function celb_rest_profile_data( $id ) {
	$id      = (int) $id;
	$socials = array();
	foreach ( celb_social_platforms() as $key => $label ) {
		$socials[ $key ] = (string) get_post_meta( $id, '_celb_social_' . $key, true );
	}
	$profile_id = (int) get_post_meta( $id, '_celb_profile', true );
	$photo      = $profile_id ? wp_get_attachment_image_url( $profile_id, 'medium' ) : get_the_post_thumbnail_url( $id, 'medium' );
	$status     = get_post_status( $id );
	return array(
		'id'          => $id,
		'name'        => get_the_title( $id ),
		'role'        => (string) get_post_meta( $id, '_celb_role', true ),
		'nationality' => (string) get_post_meta( $id, '_celb_nationality', true ),
		'bio'         => (string) get_post_meta( $id, '_celb_bio', true ),
		'birthdate'   => (string) get_post_meta( $id, '_celb_birthdate', true ),
		'show_year'   => '1' === get_post_meta( $id, '_celb_show_year', true ),
		'locked'      => (bool) get_post_meta( $id, '_celb_locked', true ),
		'status'      => $status,
		'photo'       => $photo ? $photo : '',
		'smartlink'   => 'publish' === $status ? celb_smartlink_url( $id ) : '',
		'view_url'    => get_permalink( $id ),
		'socials'     => $socials,
		'rate'        => celb_rest_rate_data( $id ),
		'cal'         => ( 'publish' === $status ) ? celb_cal_feed_url( $id, 'webcal' ) : '',
	);
}

/* Rate card link + password for the manage app (managers only). */
function celb_rest_rate_data( $celeb_id ) {
	$card = celb_rate_card_for_celeb( $celeb_id );
	if ( ! $card ) {
		return array( 'has' => false );
	}
	$enabled = get_post_meta( $card, '_rate_enabled', true ) === '1';
	$pw      = (string) get_post_meta( $card, '_rate_pw', true );
	return array(
		'has'     => (bool) ( $enabled && '' !== $pw ),
		'url'     => celb_rate_card_url( $card ),
		'pw'      => current_user_can( 'edit_posts' ) ? $pw : '',
		'edit'    => current_user_can( 'edit_post', $card ) ? get_edit_post_link( $card, 'raw' ) : '',
	);
}

/* Apply editable fields from a REST request to a profile. */
function celb_rest_apply_profile( $id, $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_params();
	}
	$postarr = array( 'ID' => $id );
	if ( isset( $p['name'] ) && '' !== trim( $p['name'] ) ) {
		$postarr['post_title'] = sanitize_text_field( $p['name'] );
	}
	if ( current_user_can( 'edit_posts' ) && isset( $p['status'] ) && in_array( $p['status'], array( 'publish', 'draft' ), true ) ) {
		$postarr['post_status'] = $p['status'];
	}
	if ( count( $postarr ) > 1 ) {
		wp_update_post( $postarr );
	}
	if ( array_key_exists( 'role', $p ) ) {
		update_post_meta( $id, '_celb_role', sanitize_text_field( $p['role'] ) );
	}
	if ( array_key_exists( 'nationality', $p ) ) {
		update_post_meta( $id, '_celb_nationality', sanitize_text_field( $p['nationality'] ) );
	}
	if ( array_key_exists( 'bio', $p ) ) {
		update_post_meta( $id, '_celb_bio', wp_kses_post( $p['bio'] ) );
	}
	if ( array_key_exists( 'birthdate', $p ) ) {
		$bd = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $p['birthdate'] ) ? $p['birthdate'] : '';
		update_post_meta( $id, '_celb_birthdate', $bd );
	}
	if ( array_key_exists( 'show_year', $p ) ) {
		update_post_meta( $id, '_celb_show_year', ! empty( $p['show_year'] ) ? '1' : '' );
	}
	if ( array_key_exists( 'locked', $p ) ) {
		update_post_meta( $id, '_celb_locked', ! empty( $p['locked'] ) ? '1' : '' );
	}
	if ( isset( $p['socials'] ) && is_array( $p['socials'] ) ) {
		foreach ( celb_social_platforms() as $key => $label ) {
			if ( array_key_exists( $key, $p['socials'] ) ) {
				update_post_meta( $id, '_celb_social_' . $key, esc_url_raw( trim( (string) $p['socials'][ $key ] ) ) );
			}
		}
	}
}

function celb_rest_routes() {
	$access = array( 'permission_callback' => 'celb_rest_can_access' );
	$edit   = array( 'permission_callback' => 'celb_rest_can_edit' );
	$admin  = array( 'permission_callback' => 'celb_rest_can_admin' );

	register_rest_route( 'celb/v1', '/bootstrap', array( array(
		'methods'  => 'GET',
		'callback' => 'celb_rest_bootstrap',
	) + $access ) );

	register_rest_route( 'celb/v1', '/profiles', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_list' ) + $access,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_create' ) + $edit,
	) );

	register_rest_route( 'celb/v1', '/profiles/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_get' ) + $access,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_update' ) + $access,
	) );

	register_rest_route( 'celb/v1', '/profiles/(?P<id>\d+)/photo', array( array(
		'methods'  => 'POST',
		'callback' => 'celb_rest_photo',
	) + $access ) );

	register_rest_route( 'celb/v1', '/settings', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_get_settings' ) + $admin,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_save_settings' ) + $admin,
	) );
}
add_action( 'rest_api_init', 'celb_rest_routes' );

function celb_rest_bootstrap() {
	$user      = wp_get_current_user();
	$is_mgr    = current_user_can( 'edit_posts' );
	$celeb_id  = celb_user_celeb_id();
	$platforms = array();
	foreach ( celb_social_platforms() as $key => $label ) {
		$platforms[] = array( 'key' => $key, 'label' => $label );
	}
	$user_data = array(
		'name'       => $user->display_name,
		'can_admin'  => current_user_can( 'manage_options' ),
		'mode'       => $is_mgr ? 'manager' : 'celebrity',
		'celeb'      => $celeb_id,
		'celeb_name' => $celeb_id ? get_the_title( $celeb_id ) : '',
	);
	if ( ! $is_mgr ) {
		return rest_ensure_response( array( 'user' => $user_data, 'platforms' => $platforms, 'counts' => array() ) );
	}
	$count   = wp_count_posts( CELB_CPT );
	$private = get_posts( array(
		'post_type' => CELB_CPT, 'post_status' => 'publish', 'numberposts' => -1,
		'fields' => 'ids', 'meta_key' => '_celb_locked', 'meta_value' => '1', 'suppress_filters' => true,
	) );
	$news = wp_count_posts( 'celeb_news' );
	$celebs = array();
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $c ) {
		$celebs[] = array( 'id' => $c->ID, 'name' => get_the_title( $c->ID ) );
	}
	return rest_ensure_response( array(
		'user'      => $user_data,
		'counts'    => array(
			'total'     => (int) $count->publish + (int) $count->draft + (int) $count->pending,
			'published' => (int) $count->publish,
			'drafts'    => (int) $count->draft + (int) $count->pending,
			'private'   => count( $private ),
			'news'      => (int) $news->publish,
		),
		'celebs'    => $celebs,
		'requests'  => celb_request_counts(),
		'glance'    => celb_wc_glance(),
		'platforms' => $platforms,
	) );
}

function celb_rest_list( $req ) {
	$args = array(
		'post_type'      => CELB_CPT,
		'post_status'    => array( 'publish', 'draft', 'pending' ),
		'posts_per_page' => 200,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'suppress_filters' => true,
	);
	if ( ! current_user_can( 'edit_posts' ) ) {
		$mine = celb_user_celeb_id();
		$args['post__in'] = $mine ? array( $mine ) : array( 0 );
		$args['post_status'] = array( 'publish', 'draft', 'pending' );
	}
	$search = sanitize_text_field( (string) $req->get_param( 'search' ) );
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	$status = (string) $req->get_param( 'status' );
	if ( in_array( $status, array( 'publish', 'draft' ), true ) ) {
		$args['post_status'] = $status;
	}
	$q   = new WP_Query( $args );
	$out = array();
	foreach ( $q->posts as $post ) {
		$id         = $post->ID;
		$profile_id = (int) get_post_meta( $id, '_celb_profile', true );
		$thumb      = $profile_id ? wp_get_attachment_image_url( $profile_id, 'thumbnail' ) : get_the_post_thumbnail_url( $id, 'thumbnail' );
		$out[] = array(
			'id'        => $id,
			'name'      => get_the_title( $id ),
			'role'      => (string) get_post_meta( $id, '_celb_role', true ),
			'status'    => $post->post_status,
			'locked'    => (bool) get_post_meta( $id, '_celb_locked', true ),
			'thumb'     => $thumb ? $thumb : '',
			'smartlink' => 'publish' === $post->post_status ? celb_smartlink_url( $id ) : '',
		);
	}
	return rest_ensure_response( $out );
}

function celb_rest_get( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== CELB_CPT ) {
		return new WP_Error( 'celb_404', 'Profile not found.', array( 'status' => 404 ) );
	}
	if ( ! celb_guard_celeb( $id ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	return rest_ensure_response( celb_rest_profile_data( $id ) );
}

function celb_rest_create( $req ) {
	$p    = $req->get_json_params();
	$name = is_array( $p ) && isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
	if ( '' === trim( $name ) ) {
		return new WP_Error( 'celb_no_name', 'A name is required.', array( 'status' => 400 ) );
	}
	$status = ( is_array( $p ) && isset( $p['status'] ) && 'publish' === $p['status'] ) ? 'publish' : 'draft';
	$id     = wp_insert_post( array(
		'post_type'   => CELB_CPT,
		'post_title'  => $name,
		'post_status' => $status,
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	celb_rest_apply_profile( $id, $req );
	return rest_ensure_response( celb_rest_profile_data( $id ) );
}

function celb_rest_update( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== CELB_CPT ) {
		return new WP_Error( 'celb_404', 'Profile not found.', array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	celb_rest_apply_profile( $id, $req );
	return rest_ensure_response( celb_rest_profile_data( $id ) );
}

function celb_rest_photo( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== CELB_CPT ) {
		return new WP_Error( 'celb_404', 'Profile not found.', array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed to upload.', array( 'status' => 403 ) );
	}
	if ( empty( $_FILES['file'] ) ) {
		return new WP_Error( 'celb_nofile', 'No file received.', array( 'status' => 400 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$att = media_handle_upload( 'file', $id );
	if ( is_wp_error( $att ) ) {
		return $att;
	}
	update_post_meta( $id, '_celb_profile', $att );
	return rest_ensure_response( array(
		'id'    => $att,
		'photo' => wp_get_attachment_image_url( $att, 'medium' ),
	) );
}

function celb_rest_get_settings() {
	return rest_ensure_response( celb_get_settings() );
}

function celb_rest_save_settings( $req ) {
	$input   = $req->get_json_params();
	$current = celb_get_settings();
	$merged  = array_merge( $current, is_array( $input ) ? $input : array() );
	$clean   = celb_sanitize_settings( $merged );
	update_option( 'celb_settings', $clean );
	return rest_ensure_response( $clean );
}

/* The mobile app shell (login-gated). */
function celb_manage_assets() {
	wp_register_style( 'celb-manage', CELB_URL . 'assets/celb-manage.css', array(), CELB_VERSION );
	wp_register_script( 'celb-manage', CELB_URL . 'assets/celb-manage.js', array(), CELB_VERSION, true );
}
add_action( 'init', 'celb_manage_assets' );

function celb_manage_shortcode() {
	$current = home_url( add_query_arg( array() ) );

	if ( ! is_user_logged_in() ) {
		$login = wp_login_url( $current );
		return '<div class="celb-manage-gate"><div class="celb-gate-card">'
			. '<h2>' . esc_html__( 'Manage', 'celb-mgmt' ) . '</h2>'
			. '<p>' . esc_html__( 'Sign in with your website account to manage profiles.', 'celb-mgmt' ) . '</p>'
			. '<a class="celb-gate-btn" href="' . esc_url( $login ) . '">' . esc_html__( 'Log in', 'celb-mgmt' ) . '</a>'
			. '</div></div>';
	}
	if ( ! current_user_can( 'edit_posts' ) && ! celb_user_celeb_id() ) {
		return '<div class="celb-manage-gate"><div class="celb-gate-card">'
			. '<h2>' . esc_html__( 'No access', 'celb-mgmt' ) . '</h2>'
			. '<p>' . esc_html__( 'Your account does not have permission to manage profiles.', 'celb-mgmt' ) . '</p>'
			. '</div></div>';
	}

	wp_enqueue_style( 'celb-manage' );
	wp_enqueue_script( 'celb-manage' );
	wp_localize_script( 'celb-manage', 'CELB_MANAGE', array(
		'root'     => esc_url_raw( rest_url( 'celb/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'logout'   => wp_logout_url( $current ),
		'logo'     => celb_logo_url(),
		'site'     => home_url( '/' ),
	) );

	$s      = celb_get_settings();
	$accent = $s['accent'] ? $s['accent'] : '#999999';
	$style  = '<style>.celb-manage,.celb-manage-gate{--m-gold:' . esc_attr( $accent ) . ';}</style>';

	// Remember which page hosts the app, so the home-screen tags fire even when the
	// page is built with a page builder (shortcode not in raw post_content).
	$pid = get_the_ID();
	if ( $pid && (int) get_option( 'celb_manage_page_id' ) !== (int) $pid ) {
		update_option( 'celb_manage_page_id', (int) $pid, false );
	}

	return $style . '<div id="celb-manage-app" class="celb-manage"><div class="celb-loading">' . esc_html__( 'Loading…', 'celb-mgmt' ) . '</div></div>';
}
add_shortcode( 'CLEB_manage', 'celb_manage_shortcode' );

/** Home-screen (PWA) tags — only on the [CLEB_manage] page. */
function celb_manage_pwa_head() {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	$is_manage = $post && has_shortcode( (string) $post->post_content, 'CLEB_manage' );
	if ( ! $is_manage ) {
		// Fallback for page builders: match the remembered manage page id.
		$saved = (int) get_option( 'celb_manage_page_id' );
		$is_manage = ( $saved && $saved === (int) get_queried_object_id() );
	}
	if ( ! $is_manage ) {
		return;
	}
	$s    = celb_get_settings();
	$name = $s['pwa_name'] ? $s['pwa_name'] : 'iLike Manage';
	$icon = celb_manage_pwa_icon();
	echo '<link rel="manifest" href="' . esc_url( add_query_arg( 'celb_manifest', '1', home_url( '/' ) ) ) . '" />' . "\n";
	if ( $icon ) {
		echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $icon ) . '" />' . "\n";
		echo '<link rel="apple-touch-icon-precomposed" href="' . esc_url( $icon ) . '" />' . "\n";
		echo '<link rel="icon" type="image/png" href="' . esc_url( $icon ) . '" />' . "\n";
	}
	echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $name ) . '" />' . "\n";
	echo '<meta name="theme-color" content="#121212" />' . "\n";
}
add_action( 'wp_head', 'celb_manage_pwa_head' );

/** Resolve the PWA icon: explicit setting, else brand logo, else bundled logo. */
function celb_manage_pwa_icon() {
	$s = celb_get_settings();
	if ( ! empty( $s['pwa_icon'] ) ) {
		return $s['pwa_icon'];
	}
	if ( ! empty( $s['brand_logo_url'] ) ) {
		return $s['brand_logo_url'];
	}
	return celb_logo_url();
}

/** Serve the web-app manifest at /?celb_manifest=1 */
function celb_manage_manifest() {
	if ( ! isset( $_GET['celb_manifest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$s     = celb_get_settings();
	$name  = $s['pwa_name'] ? $s['pwa_name'] : 'iLike Manage';
	$icon  = celb_manage_pwa_icon();
	$start = function_exists( 'celb_manage_page_url' ) ? celb_manage_page_url() : '';
	if ( ! $start ) {
		$start = home_url( '/' );
	}
	$manifest = array(
		'name'             => $name,
		'short_name'       => $name,
		'start_url'        => $start,
		'scope'            => $start,
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'background_color' => '#000000',
		'theme_color'      => '#121212',
		'icons'            => array(
			array( 'src' => $icon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
		),
	);
	nocache_headers();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( $manifest );
	exit;
}
add_action( 'template_redirect', 'celb_manage_manifest' );

/* -------------------------------------------------------------------------
 * 15. WORK CENTER  (Projects + Schedule per celebrity; REST + app + admin)
 * ---------------------------------------------------------------------- */

function celb_register_workcenter_cpts() {
	register_post_type( 'celb_project', array(
		'labels'       => array(
			'name'               => __( 'Projects', 'celb-mgmt' ),
			'singular_name'      => __( 'Project', 'celb-mgmt' ),
			'add_new'            => __( 'Add Project', 'celb-mgmt' ),
			'add_new_item'       => __( 'Add Project', 'celb-mgmt' ),
			'edit_item'          => __( 'Edit Project', 'celb-mgmt' ),
			'new_item'           => __( 'New Project', 'celb-mgmt' ),
			'view_item'          => __( 'View Project', 'celb-mgmt' ),
			'search_items'       => __( 'Search Projects', 'celb-mgmt' ),
			'not_found'          => __( 'No projects yet', 'celb-mgmt' ),
			'not_found_in_trash' => __( 'No projects in trash', 'celb-mgmt' ),
			'all_items'          => __( 'Projects', 'celb-mgmt' ),
			'menu_name'          => __( 'Projects', 'celb-mgmt' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'edit.php?post_type=' . CELB_CPT,
		'supports'     => array( 'title' ),
		'menu_icon'    => 'dashicons-clapperboard',
	) );
	register_post_type( 'celb_sched', array(
		'labels'       => array(
			'name'               => __( 'Schedule', 'celb-mgmt' ),
			'singular_name'      => __( 'Schedule Entry', 'celb-mgmt' ),
			'add_new'            => __( 'Add Entry', 'celb-mgmt' ),
			'add_new_item'       => __( 'Add Schedule Entry', 'celb-mgmt' ),
			'edit_item'          => __( 'Edit Schedule Entry', 'celb-mgmt' ),
			'new_item'           => __( 'New Schedule Entry', 'celb-mgmt' ),
			'view_item'          => __( 'View Schedule Entry', 'celb-mgmt' ),
			'search_items'       => __( 'Search Schedule', 'celb-mgmt' ),
			'not_found'          => __( 'No entries yet', 'celb-mgmt' ),
			'not_found_in_trash' => __( 'No entries in trash', 'celb-mgmt' ),
			'all_items'          => __( 'Schedule', 'celb-mgmt' ),
			'menu_name'          => __( 'Schedule', 'celb-mgmt' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'edit.php?post_type=' . CELB_CPT,
		'supports'     => array( 'title' ),
		'menu_icon'    => 'dashicons-calendar-alt',
	) );
}
add_action( 'init', 'celb_register_workcenter_cpts' );

function celb_project_statuses() {
	return array( 'Upcoming', 'Ongoing', 'Completed', 'Postponed', 'Canceled' );
}
function celb_sched_types() {
	return array( 'Program', 'Podcast', 'Interview', 'Filming', 'TV Appearance', 'Meeting', 'Photoshoot', 'Event', 'Brand Campaign', 'Personal', 'Unavailable', 'Other' );
}
function celb_sched_statuses() {
	return array( 'Upcoming', 'Ongoing', 'Completed', 'Postponed', 'Canceled' );
}

function celb_maps_url( $address, $map = '' ) {
	$map = trim( (string) $map );
	if ( '' !== $map ) {
		return esc_url_raw( $map );
	}
	$address = trim( (string) $address );
	if ( '' === $address ) {
		return '';
	}
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

function celb_clean_location( $loc ) {
	if ( ! is_array( $loc ) ) {
		$loc = array();
	}
	$label   = isset( $loc['label'] ) ? sanitize_text_field( $loc['label'] ) : '';
	$address = isset( $loc['address'] ) ? sanitize_text_field( $loc['address'] ) : '';
	$map     = isset( $loc['map'] ) ? esc_url_raw( trim( $loc['map'] ) ) : '';
	if ( '' === $label && '' === $address && '' === $map ) {
		return null;
	}
	return array(
		'label'   => $label,
		'address' => $address,
		'map'     => celb_maps_url( $address, $map ),
	);
}

function celb_attachment_data( $ids ) {
	$out = array();
	if ( ! is_array( $ids ) ) {
		return $out;
	}
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			continue;
		}
		$mime = get_post_mime_type( $id );
		$kind = 'file';
		if ( strpos( $mime, 'image/' ) === 0 ) {
			$kind = 'image';
		} elseif ( strpos( $mime, 'video/' ) === 0 ) {
			$kind = 'video';
		} elseif ( 'application/pdf' === $mime ) {
			$kind = 'pdf';
		}
		$path = get_attached_file( $id );
		$out[] = array(
			'id'    => $id,
			'url'   => wp_get_attachment_url( $id ),
			'title' => get_the_title( $id ),
			'name'  => $path ? basename( $path ) : get_the_title( $id ),
			'mime'  => $mime,
			'kind'  => $kind,
			'thumb' => 'image' === $kind ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '',
		);
	}
	return $out;
}

function celb_project_counts( $days ) {
	$today = current_time( 'Y-m-d' );
	$total = is_array( $days ) ? count( $days ) : 0;
	$completed = 0;
	$upcoming  = 0;
	if ( is_array( $days ) ) {
		foreach ( $days as $d ) {
			$done = ! empty( $d['status'] ) && 'completed' === $d['status'];
			if ( $done ) {
				$completed++;
			} elseif ( ! empty( $d['date'] ) && $d['date'] >= $today ) {
				$upcoming++;
			}
		}
	}
	return array( 'total' => $total, 'completed' => $completed, 'upcoming' => $upcoming );
}

function celb_project_data( $id ) {
	$id        = (int) $id;
	$days      = get_post_meta( $id, '_proj_days', true );
	$days      = is_array( $days ) ? array_values( $days ) : array();
	$locations = get_post_meta( $id, '_proj_locations', true );
	$locations = is_array( $locations ) ? array_values( $locations ) : array();
	$updates   = get_post_meta( $id, '_proj_updates', true );
	$updates   = is_array( $updates ) ? array_values( $updates ) : array();
	return array(
		'id'          => $id,
		'name'        => get_the_title( $id ),
		'celeb'       => (int) get_post_meta( $id, '_proj_celeb', true ),
		'company'     => (string) get_post_meta( $id, '_proj_company', true ),
		'type'        => (string) get_post_meta( $id, '_proj_type', true ),
		'status'      => (string) get_post_meta( $id, '_proj_status', true ),
		'start'       => (string) get_post_meta( $id, '_proj_start', true ),
		'end'         => (string) get_post_meta( $id, '_proj_end', true ),
		'notes'       => (string) get_post_meta( $id, '_proj_notes', true ),
		'locations'   => $locations,
		'days'        => $days,
		'counts'      => celb_project_counts( $days ),
		'attachments' => celb_attachment_data( get_post_meta( $id, '_proj_attachments', true ) ),
		'updates'     => array_reverse( $updates ),
	);
}

function celb_sched_data( $id ) {
	$id  = (int) $id;
	$loc = get_post_meta( $id, '_sched_location', true );
	$loc = is_array( $loc ) ? $loc : null;
	return array(
		'id'          => $id,
		'title'       => get_the_title( $id ),
		'celeb'       => (int) get_post_meta( $id, '_sched_celeb', true ),
		'type'        => (string) get_post_meta( $id, '_sched_type', true ),
		'date'        => (string) get_post_meta( $id, '_sched_date', true ),
		'time'        => (string) get_post_meta( $id, '_sched_time', true ),
		'duration'    => (int) get_post_meta( $id, '_sched_duration', true ),
		'location'    => $loc,
		'description' => (string) get_post_meta( $id, '_sched_desc', true ),
		'prep'        => (string) get_post_meta( $id, '_sched_prep', true ),
		'reminder'    => (int) get_post_meta( $id, '_sched_reminder', true ),
		'attachments' => celb_attachment_data( get_post_meta( $id, '_sched_attachments', true ) ),
		'ics'         => celb_sched_ics_url( $id ),
	);
}

function celb_sched_ics_url( $id ) {
	return add_query_arg( array(
		'action'   => 'celb_ics',
		'id'       => $id,
		'_wpnonce' => wp_create_nonce( 'celb_ics_' . $id ),
	), admin_url( 'admin-ajax.php' ) );
}

function celb_proj_add_update( $id, $text, $user_name = '' ) {
	$updates = get_post_meta( $id, '_proj_updates', true );
	if ( ! is_array( $updates ) ) {
		$updates = array();
	}
	$updates[] = array(
		'time' => current_time( 'mysql' ),
		'user' => $user_name ? $user_name : wp_get_current_user()->display_name,
		'text' => sanitize_text_field( $text ),
	);
	update_post_meta( $id, '_proj_updates', $updates );
}

/* ---- REST ---- */
function celb_wc_routes() {
	$edit = array( 'permission_callback' => 'celb_rest_can_access' );

	register_rest_route( 'celb/v1', '/workcenter/(?P<celeb>\d+)', array( array( 'methods' => 'GET', 'callback' => 'celb_rest_workcenter' ) + $edit ) );

	register_rest_route( 'celb/v1', '/projects', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_proj_list' ) + $edit,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_proj_create' ) + $edit,
	) );
	register_rest_route( 'celb/v1', '/projects/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_proj_get' ) + $edit,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_proj_update' ) + $edit,
		array( 'methods' => 'DELETE', 'callback' => 'celb_rest_proj_delete' ) + $edit,
	) );
	register_rest_route( 'celb/v1', '/projects/(?P<id>\d+)/attach', array( array( 'methods' => 'POST', 'callback' => 'celb_rest_proj_attach' ) + $edit ) );
	register_rest_route( 'celb/v1', '/projects/(?P<id>\d+)/detach', array( array( 'methods' => 'POST', 'callback' => 'celb_rest_proj_detach' ) + $edit ) );

	register_rest_route( 'celb/v1', '/sched', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_sched_list' ) + $edit,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_sched_create' ) + $edit,
	) );
	register_rest_route( 'celb/v1', '/sched/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_sched_get' ) + $edit,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_sched_update' ) + $edit,
		array( 'methods' => 'DELETE', 'callback' => 'celb_rest_sched_delete' ) + $edit,
	) );
	register_rest_route( 'celb/v1', '/sched/(?P<id>\d+)/attach', array( array( 'methods' => 'POST', 'callback' => 'celb_rest_sched_attach' ) + $edit ) );
	register_rest_route( 'celb/v1', '/sched/(?P<id>\d+)/detach', array( array( 'methods' => 'POST', 'callback' => 'celb_rest_sched_detach' ) + $edit ) );
}
add_action( 'rest_api_init', 'celb_wc_routes' );

function celb_rest_workcenter( $req ) {
	$celeb = absint( $req['celeb'] );
	if ( get_post_type( $celeb ) !== CELB_CPT ) {
		return new WP_Error( 'celb_404', 'Celebrity not found.', array( 'status' => 404 ) );
	}
	if ( ! celb_guard_celeb( $celeb ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$today = current_time( 'Y-m-d' );

	$projects = get_posts( array(
		'post_type' => 'celb_project', 'post_status' => 'publish', 'numberposts' => -1,
		'meta_query' => array( array( 'key' => '_proj_celeb', 'value' => $celeb ) ),
		'meta_key' => '_proj_start', 'orderby' => 'meta_value', 'order' => 'DESC', 'suppress_filters' => true,
	) );
	$up_proj = array(); $past_proj = array();
	foreach ( $projects as $p ) {
		$d = celb_project_data( $p->ID );
		$summary = array( 'id' => $d['id'], 'name' => $d['name'], 'type' => $d['type'], 'status' => $d['status'], 'company' => $d['company'], 'start' => $d['start'], 'end' => $d['end'], 'counts' => $d['counts'] );
		$is_past = in_array( $d['status'], array( 'Completed', 'Cancelled' ), true ) || ( $d['end'] && $d['end'] < $today );
		if ( $is_past ) { $past_proj[] = $summary; } else { $up_proj[] = $summary; }
	}

	$sched = get_posts( array(
		'post_type' => 'celb_sched', 'post_status' => 'publish', 'numberposts' => -1,
		'meta_query' => array( array( 'key' => '_sched_celeb', 'value' => $celeb ) ),
		'meta_key' => '_sched_date', 'orderby' => 'meta_value', 'order' => 'ASC', 'suppress_filters' => true,
	) );
	$up_sched = array(); $past_sched = array(); $today_sched = array();
	foreach ( $sched as $s ) {
		$d = celb_sched_data( $s->ID );
		$row = array( 'id' => $d['id'], 'title' => $d['title'], 'type' => $d['type'], 'date' => $d['date'], 'time' => $d['time'], 'location' => $d['location'] );
		if ( $d['date'] === $today ) { $today_sched[] = $row; $up_sched[] = $row; }
		elseif ( $d['date'] > $today ) { $up_sched[] = $row; }
		else { $past_sched[] = $row; }
	}
	$past_sched = array_reverse( $past_sched );

	return rest_ensure_response( array(
		'celeb'         => array( 'id' => $celeb, 'name' => get_the_title( $celeb ) ),
		'today'         => $today,
		'today_agenda'  => $today_sched,
		'projects_up'   => $up_proj,
		'projects_past' => $past_proj,
		'sched_up'      => array_slice( $up_sched, 0, 50 ),
		'sched_past'    => array_slice( $past_sched, 0, 50 ),
		'statuses'      => celb_project_statuses(),
		'project_types' => celb_project_categories(),
		'sched_types'   => celb_sched_types(),
	) );
}

/* Projects */
function celb_rest_proj_list( $req ) {
	$celeb = absint( $req->get_param( 'celeb' ) );
	if ( ! current_user_can( 'edit_posts' ) ) { $celeb = celb_user_celeb_id(); }
	if ( $celeb && ! celb_guard_celeb( $celeb ) ) { return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) ); }
	$args  = array( 'post_type' => 'celb_project', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_proj_start', 'orderby' => 'meta_value', 'order' => 'DESC', 'suppress_filters' => true );
	if ( $celeb ) {
		$args['meta_query'] = array( array( 'key' => '_proj_celeb', 'value' => $celeb ) );
	}
	$out = array();
	foreach ( get_posts( $args ) as $p ) {
		$out[] = celb_project_data( $p->ID );
	}
	return rest_ensure_response( $out );
}
function celb_rest_proj_get( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_project' || ! celb_guard_celeb( celb_proj_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	return rest_ensure_response( celb_project_data( $id ) );
}
function celb_rest_apply_project( $id, $req, $is_new = false ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_params();
	}
	if ( isset( $p['name'] ) && '' !== trim( $p['name'] ) ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $p['name'] ) ) );
	}
	$map = array(
		'_proj_company' => 'company', '_proj_type' => 'type', '_proj_status' => 'status',
		'_proj_start' => 'start', '_proj_end' => 'end', '_proj_notes' => 'notes',
	);
	foreach ( $map as $meta => $key ) {
		if ( array_key_exists( $key, $p ) ) {
			$val = in_array( $key, array( 'start', 'end' ), true )
				? ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $p[ $key ] ) ? $p[ $key ] : '' )
				: ( 'notes' === $key ? sanitize_textarea_field( $p[ $key ] ) : sanitize_text_field( $p[ $key ] ) );
			update_post_meta( $id, $meta, $val );
		}
	}
	if ( isset( $p['locations'] ) && is_array( $p['locations'] ) ) {
		$locs = array();
		foreach ( $p['locations'] as $l ) {
			$c = celb_clean_location( $l );
			if ( $c ) {
				$locs[] = $c;
			}
		}
		update_post_meta( $id, '_proj_locations', $locs );
	}
	if ( isset( $p['days'] ) && is_array( $p['days'] ) ) {
		$days = array();
		foreach ( $p['days'] as $d ) {
			$date = isset( $d['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] ) ? $d['date'] : '';
			if ( '' === $date ) {
				continue;
			}
			$days[] = array(
				'date'     => $date,
				'call'     => isset( $d['call'] ) ? sanitize_text_field( $d['call'] ) : '',
				'location' => isset( $d['location'] ) ? sanitize_text_field( $d['location'] ) : '',
				'status'   => ( isset( $d['status'] ) && 'completed' === $d['status'] ) ? 'completed' : 'scheduled',
				'notes'    => isset( $d['notes'] ) ? sanitize_text_field( $d['notes'] ) : '',
			);
		}
		usort( $days, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );
		update_post_meta( $id, '_proj_days', $days );
	}
	$note = isset( $p['update_note'] ) ? trim( $p['update_note'] ) : '';
	if ( $is_new ) {
		celb_proj_add_update( $id, __( 'Project created', 'celb-mgmt' ) );
	} elseif ( '' !== $note ) {
		celb_proj_add_update( $id, $note );
	} else {
		celb_proj_add_update( $id, __( 'Details updated', 'celb-mgmt' ) );
	}
}
function celb_rest_proj_create( $req ) {
	$p     = $req->get_json_params();
	$name  = is_array( $p ) && isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
	$celeb = is_array( $p ) && isset( $p['celeb'] ) ? absint( $p['celeb'] ) : 0;
	if ( '' === $name || ! $celeb ) {
		return new WP_Error( 'celb_bad', 'Project name and celebrity are required.', array( 'status' => 400 ) );
	}
	if ( ! celb_guard_celeb( $celeb ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$id = wp_insert_post( array( 'post_type' => 'celb_project', 'post_title' => $name, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_proj_celeb', $celeb );
	celb_rest_apply_project( $id, $req, true );
	return rest_ensure_response( celb_project_data( $id ) );
}
function celb_rest_proj_update( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_project' || ! celb_guard_celeb( celb_proj_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	celb_rest_apply_project( $id, $req, false );
	return rest_ensure_response( celb_project_data( $id ) );
}
function celb_rest_proj_delete( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_project' || ! celb_guard_celeb( celb_proj_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	wp_trash_post( $id );
	return rest_ensure_response( array( 'deleted' => true ) );
}

/* Schedule */
function celb_rest_sched_list( $req ) {
	$celeb = absint( $req->get_param( 'celeb' ) );
	if ( ! current_user_can( 'edit_posts' ) ) { $celeb = celb_user_celeb_id(); }
	if ( $celeb && ! celb_guard_celeb( $celeb ) ) { return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) ); }
	$args  = array( 'post_type' => 'celb_sched', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_sched_date', 'orderby' => 'meta_value', 'order' => 'ASC', 'suppress_filters' => true );
	if ( $celeb ) {
		$args['meta_query'] = array( array( 'key' => '_sched_celeb', 'value' => $celeb ) );
	}
	$out = array();
	foreach ( get_posts( $args ) as $s ) {
		$out[] = celb_sched_data( $s->ID );
	}
	return rest_ensure_response( $out );
}
function celb_rest_sched_get( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_sched' || ! celb_guard_celeb( celb_sched_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	return rest_ensure_response( celb_sched_data( $id ) );
}
function celb_rest_apply_sched( $id, $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_params();
	}
	if ( isset( $p['title'] ) && '' !== trim( $p['title'] ) ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $p['title'] ) ) );
	}
	if ( array_key_exists( 'type', $p ) ) {
		update_post_meta( $id, '_sched_type', sanitize_text_field( $p['type'] ) );
	}
	if ( array_key_exists( 'date', $p ) ) {
		update_post_meta( $id, '_sched_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $p['date'] ) ? $p['date'] : '' );
	}
	if ( array_key_exists( 'time', $p ) ) {
		update_post_meta( $id, '_sched_time', preg_match( '/^\d{2}:\d{2}$/', (string) $p['time'] ) ? $p['time'] : '' );
	}
	if ( array_key_exists( 'duration', $p ) ) {
		update_post_meta( $id, '_sched_duration', absint( $p['duration'] ) );
	}
	if ( array_key_exists( 'description', $p ) ) {
		update_post_meta( $id, '_sched_desc', sanitize_textarea_field( $p['description'] ) );
	}
	if ( array_key_exists( 'prep', $p ) ) {
		update_post_meta( $id, '_sched_prep', sanitize_textarea_field( $p['prep'] ) );
	}
	if ( array_key_exists( 'reminder', $p ) ) {
		update_post_meta( $id, '_sched_reminder', absint( $p['reminder'] ) );
	}
	if ( array_key_exists( 'location', $p ) ) {
		$c = celb_clean_location( $p['location'] );
		update_post_meta( $id, '_sched_location', $c ? $c : array() );
	}
}
function celb_rest_sched_create( $req ) {
	$p     = $req->get_json_params();
	$title = is_array( $p ) && isset( $p['title'] ) ? sanitize_text_field( $p['title'] ) : '';
	$celeb = is_array( $p ) && isset( $p['celeb'] ) ? absint( $p['celeb'] ) : 0;
	if ( '' === $title || ! $celeb ) {
		return new WP_Error( 'celb_bad', 'Title and celebrity are required.', array( 'status' => 400 ) );
	}
	if ( ! celb_guard_celeb( $celeb ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$id = wp_insert_post( array( 'post_type' => 'celb_sched', 'post_title' => $title, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_sched_celeb', $celeb );
	celb_rest_apply_sched( $id, $req );
	return rest_ensure_response( celb_sched_data( $id ) );
}
function celb_rest_sched_update( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_sched' || ! celb_guard_celeb( celb_sched_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	celb_rest_apply_sched( $id, $req );
	return rest_ensure_response( celb_sched_data( $id ) );
}
function celb_rest_sched_delete( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_sched' || ! celb_guard_celeb( celb_sched_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	wp_trash_post( $id );
	return rest_ensure_response( array( 'deleted' => true ) );
}

/* Attachments (shared) */
function celb_rest_do_attach( $id, $meta_key ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed to upload.', array( 'status' => 403 ) );
	}
	if ( empty( $_FILES['file'] ) ) {
		return new WP_Error( 'celb_nofile', 'No file received.', array( 'status' => 400 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$att = media_handle_upload( 'file', $id );
	if ( is_wp_error( $att ) ) {
		return $att;
	}
	$ids = get_post_meta( $id, $meta_key, true );
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}
	$ids[] = $att;
	update_post_meta( $id, $meta_key, $ids );
	return $ids;
}
function celb_rest_proj_attach( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_project' || ! celb_guard_celeb( celb_proj_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$ids = celb_rest_do_attach( $id, '_proj_attachments' );
	if ( is_wp_error( $ids ) ) {
		return $ids;
	}
	celb_proj_add_update( $id, __( 'Attachment added', 'celb-mgmt' ) );
	return rest_ensure_response( array( 'attachments' => celb_attachment_data( $ids ) ) );
}
function celb_rest_sched_attach( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_sched' || ! celb_guard_celeb( celb_sched_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$ids = celb_rest_do_attach( $id, '_sched_attachments' );
	if ( is_wp_error( $ids ) ) {
		return $ids;
	}
	return rest_ensure_response( array( 'attachments' => celb_attachment_data( $ids ) ) );
}
function celb_rest_do_detach( $id, $meta_key, $att_id ) {
	$ids = get_post_meta( $id, $meta_key, true );
	$ids = is_array( $ids ) ? array_values( array_filter( $ids, function ( $x ) use ( $att_id ) { return (int) $x !== (int) $att_id; } ) ) : array();
	update_post_meta( $id, $meta_key, $ids );
	return $ids;
}
function celb_rest_proj_detach( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_project' || ! celb_guard_celeb( celb_proj_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$p  = $req->get_json_params();
	$att = is_array( $p ) && isset( $p['att_id'] ) ? absint( $p['att_id'] ) : 0;
	$ids = celb_rest_do_detach( $id, '_proj_attachments', $att );
	return rest_ensure_response( array( 'attachments' => celb_attachment_data( $ids ) ) );
}
function celb_rest_sched_detach( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_sched' || ! celb_guard_celeb( celb_sched_celeb( $id ) ) ) {
		return new WP_Error( 'celb_forbidden', 'Not allowed.', array( 'status' => 403 ) );
	}
	$p  = $req->get_json_params();
	$att = is_array( $p ) && isset( $p['att_id'] ) ? absint( $p['att_id'] ) : 0;
	$ids = celb_rest_do_detach( $id, '_sched_attachments', $att );
	return rest_ensure_response( array( 'attachments' => celb_attachment_data( $ids ) ) );
}

/* ---- Add-to-calendar (.ics) ---- */
function celb_ics_download() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_ics_' . $id ) ) {
		wp_die( 'Invalid request.' );
	}
	if ( ! current_user_can( 'edit_posts' ) || get_post_type( $id ) !== 'celb_sched' ) {
		wp_die( 'Not allowed.' );
	}
	$d        = celb_sched_data( $id );
	$date     = $d['date'] ? $d['date'] : current_time( 'Y-m-d' );
	$time     = $d['time'] ? $d['time'] : '09:00';
	$dtstart  = str_replace( '-', '', $date ) . 'T' . str_replace( ':', '', $time ) . '00';
	$dur      = $d['duration'] ? $d['duration'] : 60;
	$loc      = $d['location'] && ! empty( $d['location']['label'] ) ? $d['location']['label'] : ( $d['location'] && ! empty( $d['location']['address'] ) ? $d['location']['address'] : '' );
	$desc     = trim( $d['description'] . ( $d['prep'] ? "\n\nPrep: " . $d['prep'] : '' ) );
	$uid      = 'celb-' . $id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
	$esc      = function ( $s ) { return str_replace( array( "\\", ";", ",", "\n" ), array( "\\\\", "\\;", "\\,", "\\n" ), (string) $s ); };
	$lines    = array(
		'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//iLike Agency//Work Center//EN', 'CALSCALE:GREGORIAN',
		'BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'DTSTART:' . $dtstart, 'DURATION:PT' . (int) $dur . 'M',
		'SUMMARY:' . $esc( $d['title'] ),
	);
	if ( $loc ) { $lines[] = 'LOCATION:' . $esc( $loc ); }
	if ( $desc ) { $lines[] = 'DESCRIPTION:' . $esc( $desc ); }
	if ( $d['reminder'] ) {
		$lines[] = 'BEGIN:VALARM';
		$lines[] = 'ACTION:DISPLAY';
		$lines[] = 'DESCRIPTION:' . $esc( $d['title'] );
		$lines[] = 'TRIGGER:-PT' . (int) $d['reminder'] . 'M';
		$lines[] = 'END:VALARM';
	}
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $d['title'] ) . '.ics"' );
	echo implode( "\r\n", $lines );
	exit;
}
add_action( 'wp_ajax_celb_ics', 'celb_ics_download' );

/* -------------------------------------------------------------------------
 * 16. CELEBRITY SELF-SERVICE  (per-celebrity login + ownership)
 * ---------------------------------------------------------------------- */

function celb_register_celebrity_role() {
	if ( ! get_role( 'celebrity' ) ) {
		add_role( 'celebrity', __( 'Celebrity', 'celb-mgmt' ), array( 'read' => true, 'upload_files' => true ) );
	} else {
		$r = get_role( 'celebrity' );
		if ( $r && ! $r->has_cap( 'upload_files' ) ) { $r->add_cap( 'upload_files' ); }
	}
}
add_action( 'init', 'celb_register_celebrity_role' );

function celb_user_celeb_id( $uid = 0 ) {
	$uid = $uid ? (int) $uid : get_current_user_id();
	if ( ! $uid ) { return 0; }
	$cid = (int) get_user_meta( $uid, '_celb_celebrity', true );
	return ( $cid && get_post_type( $cid ) === CELB_CPT ) ? $cid : 0;
}
function celb_rest_can_access() {
	return current_user_can( 'edit_posts' ) || celb_user_celeb_id() > 0;
}
function celb_guard_celeb( $celeb_id ) {
	if ( current_user_can( 'edit_posts' ) ) { return true; }
	$mine = celb_user_celeb_id();
	return $mine && (int) $celeb_id === $mine;
}
function celb_proj_celeb( $id ) { return (int) get_post_meta( $id, '_proj_celeb', true ); }
function celb_sched_celeb( $id ) { return (int) get_post_meta( $id, '_sched_celeb', true ); }

function celb_manage_page_url() {
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
	foreach ( $pages as $pid ) {
		$c = get_post_field( 'post_content', $pid );
		if ( $c && has_shortcode( $c, 'CLEB_manage' ) ) { return get_permalink( $pid ); }
	}
	return home_url( '/' );
}

/* Keep celebrity-role users out of wp-admin; send them to the app. */
function celb_is_app_only_user() {
	return is_user_logged_in() && ! current_user_can( 'edit_posts' ) && celb_user_celeb_id() > 0;
}
add_filter( 'show_admin_bar', function ( $show ) { return celb_is_app_only_user() ? false : $show; } );
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() ) { return; }
	if ( celb_is_app_only_user() ) { wp_safe_redirect( celb_manage_page_url() ); exit; }
} );
add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
	if ( is_a( $user, 'WP_User' ) && celb_user_celeb_id( $user->ID ) && ! user_can( $user, 'edit_posts' ) ) {
		return celb_manage_page_url();
	}
	return $redirect;
}, 10, 3 );

/* Save the celebrity app login (UI: Share & Access tab, includes/admin-studio.php). */
function celb_login_save( $post_id ) {
	if ( ! isset( $_POST['celb_login_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_login_nonce'] ), 'celb_login_save' ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( get_post_type( $post_id ) !== CELB_CPT || ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$uid = (int) get_post_meta( $post_id, '_celb_user', true );

	if ( ! empty( $_POST['celb_login_unlink'] ) ) {
		if ( $uid ) { delete_user_meta( $uid, '_celb_celebrity' ); }
		delete_post_meta( $post_id, '_celb_user' );
		delete_post_meta( $post_id, '_celb_login_pw' );
		return;
	}

	if ( $uid && ! empty( $_POST['celb_login_regen'] ) && current_user_can( 'edit_users' ) ) {
		$pw = wp_generate_password( 14, false );
		wp_set_password( $pw, $uid );
		update_post_meta( $post_id, '_celb_login_pw', $pw );
		return;
	}

	if ( $uid ) { return; }

	$existing = isset( $_POST['celb_login_existing'] ) ? absint( $_POST['celb_login_existing'] ) : 0;
	if ( $existing && get_userdata( $existing ) && ! celb_user_celeb_id( $existing ) ) {
		update_post_meta( $post_id, '_celb_user', $existing );
		update_user_meta( $existing, '_celb_celebrity', $post_id );
		return;
	}

	if ( ! empty( $_POST['celb_login_gen'] ) && current_user_can( 'create_users' ) ) {
		$base = sanitize_title( get_the_title( $post_id ) );
		if ( '' === $base ) { $base = 'celeb' . $post_id; }
		$login = $base; $i = 1;
		while ( username_exists( $login ) ) { $login = $base . $i; $i++; }
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$host  = $host ? $host : 'example.com';
		$email = $login . '@' . $host;
		if ( email_exists( $email ) ) { $email = $login . '+' . $post_id . '@' . $host; }
		$pw  = wp_generate_password( 14, false );
		$new = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $pw,
			'display_name' => get_the_title( $post_id ),
			'role'         => 'celebrity',
		) );
		if ( ! is_wp_error( $new ) ) {
			update_post_meta( $post_id, '_celb_user', $new );
			update_user_meta( $new, '_celb_celebrity', $post_id );
			update_post_meta( $post_id, '_celb_login_pw', $pw );
		}
	}
}
add_action( 'save_post', 'celb_login_save' );

/* -------------------------------------------------------------------------
 * 17. WORK CENTER — NATIVE WP-ADMIN INTERFACE (meta boxes + save)
 *     Full, reliable backend so Projects & Schedule are fully editable in
 *     wp-admin (shares the same meta the mobile/web app uses).
 * ---------------------------------------------------------------------- */

function celb_reminder_options() {
	return array(
		-1   => __( 'No alert', 'celb-mgmt' ),
		0    => __( 'At time of event', 'celb-mgmt' ),
		15   => __( '15 minutes before', 'celb-mgmt' ),
		30   => __( '30 minutes before', 'celb-mgmt' ),
		60   => __( '1 hour before', 'celb-mgmt' ),
		120  => __( '2 hours before', 'celb-mgmt' ),
		1440 => __( '1 day before', 'celb-mgmt' ),
		2880 => __( '2 days before', 'celb-mgmt' ),
	);
}
function celb_reminder_select( $name, $current ) {
	$h = '<select name="' . esc_attr( $name ) . '" class="widefat" style="max-width:220px">';
	foreach ( celb_reminder_options() as $v => $lbl ) {
		$h .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $current, (string) $v, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	return $h . '</select>';
}

function celb_project_categories() {
	return array( 'Movie', 'TV Series', 'TV Show', 'Program', 'Podcast', 'Interview', 'Filming', 'Commercial', 'Campaign', 'Documentary', 'Theatre', 'Music Video', 'Web Series', 'Other' );
}

function celb_celeb_options( $selected ) {
	$celebs = get_posts( array(
		'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft', 'pending' ),
		'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true,
	) );
	$out = '<option value="0">— ' . esc_html__( 'Select celebrity', 'celb-mgmt' ) . ' —</option>';
	foreach ( $celebs as $c ) {
		$out .= '<option value="' . (int) $c->ID . '"' . selected( $selected, $c->ID, false ) . '>' . esc_html( get_the_title( $c->ID ) ) . '</option>';
	}
	return $out;
}
function celb_admin_select( $name, $options, $current, $extra_class = '' ) {
	$cls = 'widefat' . ( $extra_class ? ' ' . $extra_class : '' );
	$h = '<select name="' . esc_attr( $name ) . '" class="' . esc_attr( $cls ) . '">';
	foreach ( $options as $o ) {
		$h .= '<option' . selected( $current, $o, false ) . '>' . esc_html( $o ) . '</option>';
	}
	return $h . '</select>';
}

/* ---- Meta box registration ---- */
function celb_wc_meta_boxes() {
	add_meta_box( 'celb_proj_details', __( 'Project Details', 'celb-mgmt' ), 'celb_proj_box_details', 'celb_project', 'normal', 'high' );
	add_meta_box( 'celb_proj_locations', __( 'Shooting Locations', 'celb-mgmt' ), 'celb_proj_box_locations', 'celb_project', 'normal' );
	add_meta_box( 'celb_proj_days', __( 'Shooting Days', 'celb-mgmt' ), 'celb_proj_box_days', 'celb_project', 'normal' );
	add_meta_box( 'celb_proj_atts', __( 'Attachments', 'celb-mgmt' ), 'celb_proj_box_atts', 'celb_project', 'normal' );
	add_meta_box( 'celb_proj_updates', __( 'Update History', 'celb-mgmt' ), 'celb_proj_box_updates', 'celb_project', 'side' );

	add_meta_box( 'celb_sched_details', __( 'Schedule Details', 'celb-mgmt' ), 'celb_sched_box_details', 'celb_sched', 'normal', 'high' );
	add_meta_box( 'celb_sched_atts', __( 'Attachments', 'celb-mgmt' ), 'celb_sched_box_atts', 'celb_sched', 'normal' );
}
add_action( 'add_meta_boxes', 'celb_wc_meta_boxes' );

/* ---- Project boxes ---- */
function celb_proj_box_details( $post ) {
	wp_nonce_field( 'celb_proj_save', 'celb_proj_nonce' );
	$celeb   = (int) get_post_meta( $post->ID, '_proj_celeb', true );
	$company = get_post_meta( $post->ID, '_proj_company', true );
	$type    = get_post_meta( $post->ID, '_proj_type', true );
	$type_o  = get_post_meta( $post->ID, '_proj_type_other', true );
	$status  = get_post_meta( $post->ID, '_proj_status', true );
	$email   = get_post_meta( $post->ID, '_proj_celeb_email', true );
	$start   = get_post_meta( $post->ID, '_proj_start', true );
	$end     = get_post_meta( $post->ID, '_proj_end', true );
	$notes   = get_post_meta( $post->ID, '_proj_notes', true );
	$rec_on  = get_post_meta( $post->ID, '_proj_recur', true );
	$rec_day = get_post_meta( $post->ID, '_proj_recur_day', true );
	$rec_time = get_post_meta( $post->ID, '_proj_recur_time', true );
	$rec_dur  = (int) get_post_meta( $post->ID, '_proj_recur_dur', true );
	$rec_until = get_post_meta( $post->ID, '_proj_recur_until', true );
	$other_style = ( 'Other' === $type ) ? '' : ' style="display:none"';
	$rec_style   = $rec_on ? '' : ' style="display:none"';
	echo '<table class="form-table"><tbody>';
	echo '<tr><th><label>' . esc_html__( 'Celebrity', 'celb-mgmt' ) . '</label></th><td><select name="proj_celeb" class="widefat">' . celb_celeb_options( $celeb ) . '</select></td></tr>';
	echo '<tr><th>' . esc_html__( 'Celebrity email', 'celb-mgmt' ) . '</th><td><input type="email" name="proj_celeb_email" class="widefat" value="' . esc_attr( $email ) . '" placeholder="name@email.com"><p class="description">' . esc_html__( 'Notifications about this project (new date, postponement, cancellation) are sent here.', 'celb-mgmt' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Type', 'celb-mgmt' ) . '</th><td>' . celb_admin_select( 'proj_type', celb_project_categories(), $type, 'celb-type-select' ) . '</td></tr>';
	echo '<tr class="celb-type-other"' . $other_style . '><th>' . esc_html__( 'Specify type', 'celb-mgmt' ) . '</th><td><input type="text" name="proj_type_other" class="widefat" value="' . esc_attr( $type_o ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Status', 'celb-mgmt' ) . '</th><td>' . celb_admin_select( 'proj_status', celb_project_statuses(), $status ) . '</td></tr>';
	echo '<tr><th>' . esc_html__( 'Production company', 'celb-mgmt' ) . '</th><td><input type="text" name="proj_company" class="widefat" value="' . esc_attr( $company ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Start date', 'celb-mgmt' ) . '</th><td><input type="date" name="proj_start" value="' . esc_attr( $start ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'End date', 'celb-mgmt' ) . '</th><td><input type="date" name="proj_end" value="' . esc_attr( $end ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Recurring', 'celb-mgmt' ) . '</th><td><label><input type="checkbox" name="proj_recur" class="celb-recur-toggle" value="1"' . checked( $rec_on, '1', false ) . '> ' . esc_html__( 'Repeat weekly', 'celb-mgmt' ) . '</label><p class="description">' . esc_html__( 'For an ongoing weekly commitment (e.g. a program every week). Generates a repeating calendar entry from the start date.', 'celb-mgmt' ) . '</p></td></tr>';
	$daynames = array( '1' => __( 'Monday', 'celb-mgmt' ), '2' => __( 'Tuesday', 'celb-mgmt' ), '3' => __( 'Wednesday', 'celb-mgmt' ), '4' => __( 'Thursday', 'celb-mgmt' ), '5' => __( 'Friday', 'celb-mgmt' ), '6' => __( 'Saturday', 'celb-mgmt' ), '7' => __( 'Sunday', 'celb-mgmt' ) );
	$daysel = '';
	foreach ( $daynames as $dv => $dl ) { $daysel .= '<option value="' . esc_attr( $dv ) . '"' . selected( (string) $rec_day, (string) $dv, false ) . '>' . esc_html( $dl ) . '</option>'; }
	echo '<tr class="celb-recur-row"' . $rec_style . '><th>' . esc_html__( 'Recurring details', 'celb-mgmt' ) . '</th><td>'
		. esc_html__( 'Every', 'celb-mgmt' ) . ' <select name="proj_recur_day">' . $daysel . '</select> '
		. esc_html__( 'at', 'celb-mgmt' ) . ' <input type="time" name="proj_recur_time" value="' . esc_attr( $rec_time ) . '"> '
		. esc_html__( 'for', 'celb-mgmt' ) . ' <input type="number" min="0" step="0.5" name="proj_recur_dur" value="' . esc_attr( $rec_dur ? rtrim( rtrim( number_format( $rec_dur / 60, 2, '.', '' ), '0' ), '.' ) : '' ) . '" style="width:70px"> ' . esc_html__( 'hours', 'celb-mgmt' ) . '<br>'
		. esc_html__( 'Until (optional)', 'celb-mgmt' ) . ' <input type="date" name="proj_recur_until" value="' . esc_attr( $rec_until ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Calendar alert', 'celb-mgmt' ) . '</th><td>' . celb_reminder_select( 'proj_reminder', ( '' === get_post_meta( $post->ID, '_proj_reminder', true ) ? 1440 : (int) get_post_meta( $post->ID, '_proj_reminder', true ) ) ) . '<p class="description">' . esc_html__( 'How long before each shooting day the calendar should alert.', 'celb-mgmt' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Notes', 'celb-mgmt' ) . '</th><td><textarea name="proj_notes" class="widefat" rows="4">' . esc_textarea( $notes ) . '</textarea></td></tr>';
	echo '</tbody></table>';
}

function celb_loc_row_html( $i, $l ) {
	$label = isset( $l['label'] ) ? $l['label'] : '';
	$addr  = isset( $l['address'] ) ? $l['address'] : '';
	$map   = ! empty( $l['map'] ) ? $l['map'] : '';
	return '<div class="celb-rep-row"><input type="text" placeholder="' . esc_attr__( 'Label (Set / Studio)', 'celb-mgmt' ) . '" name="proj_loc[' . $i . '][label]" value="' . esc_attr( $label ) . '">'
		. '<input type="text" placeholder="' . esc_attr__( 'Full address e.g. 8 Soliman Pasha, Heliopolis, Cairo', 'celb-mgmt' ) . '" name="proj_loc[' . $i . '][address]" value="' . esc_attr( $addr ) . '">'
		. ( $map ? ' <a href="' . esc_url( $map ) . '" target="_blank" class="celb-maplink">Map ↗</a>' : '' )
		. '<button type="button" class="button-link celb-rep-del">' . esc_html__( 'Remove', 'celb-mgmt' ) . '</button></div>';
}
function celb_proj_box_locations( $post ) {
	$locs = get_post_meta( $post->ID, '_proj_locations', true );
	if ( ! is_array( $locs ) ) { $locs = array(); }
	echo '<p class="description" style="margin:0 0 8px;">' . esc_html__( 'Enter the Address as a plain, real address (e.g. "8 Soliman Pasha, Heliopolis, Cairo") — this is what makes the calendar event show an embedded map. Pasting a Maps link here will only show a link, not a map. A shooting day whose Location matches a label below uses that address.', 'celb-mgmt' ) . '</p>';
	echo '<div class="celb-rep"><div class="celb-rep-rows">';
	foreach ( $locs as $i => $l ) { echo celb_loc_row_html( (int) $i, $l ); }
	echo '</div><button type="button" class="button celb-rep-add" data-target="loc">+ ' . esc_html__( 'Add location', 'celb-mgmt' ) . '</button>';
	echo '<script type="text/html" class="celb-tpl-loc">' . celb_loc_row_html( '__i__', array() ) . '</script></div>';
}

function celb_day_row_html( $i, $d ) {
	$date   = isset( $d['date'] ) ? $d['date'] : '';
	$call   = isset( $d['call'] ) ? $d['call'] : '';
	$loc    = isset( $d['location'] ) ? $d['location'] : '';
	$notes  = isset( $d['notes'] ) ? $d['notes'] : '';
	$status = isset( $d['status'] ) ? $d['status'] : 'scheduled';
	if ( ! in_array( $status, array( 'scheduled', 'completed', 'postponed', 'canceled' ), true ) ) {
		$status = 'scheduled';
	}
	$ndate = isset( $d['new_date'] ) ? $d['new_date'] : '';
	$ncall = isset( $d['new_call'] ) ? $d['new_call'] : '';
	$time  = isset( $d['time'] ) ? $d['time'] : '';
	$dur   = isset( $d['dur'] ) ? (int) $d['dur'] : 0;
	$opts  = array(
		'scheduled' => __( 'Scheduled', 'celb-mgmt' ),
		'completed' => __( 'Completed', 'celb-mgmt' ),
		'postponed' => __( 'Postponed', 'celb-mgmt' ),
		'canceled'  => __( 'Canceled', 'celb-mgmt' ),
	);
	$sel = '';
	foreach ( $opts as $v => $lbl ) {
		$sel .= '<option value="' . esc_attr( $v ) . '"' . selected( $status, $v, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	$pp_style = ( 'postponed' === $status ) ? '' : ' style="display:none"';
	return '<div class="celb-rep-row celb-day-row">'
		. '<input type="date" class="celb-day-date" name="proj_day[' . $i . '][date]" value="' . esc_attr( $date ) . '">'
		. '<input type="time" name="proj_day[' . $i . '][time]" value="' . esc_attr( $time ) . '" title="' . esc_attr__( 'Start time', 'celb-mgmt' ) . '" style="width:110px">'
		. '<input type="number" min="0" step="0.5" name="proj_day[' . $i . '][dur]" value="' . esc_attr( $dur ? rtrim( rtrim( number_format( $dur / 60, 2, '.', '' ), '0' ), '.' ) : '' ) . '" placeholder="' . esc_attr__( 'hrs', 'celb-mgmt' ) . '" title="' . esc_attr__( 'Duration (hours)', 'celb-mgmt' ) . '" style="width:64px">'
		. '<input type="text" placeholder="' . esc_attr__( 'Location', 'celb-mgmt' ) . '" name="proj_day[' . $i . '][location]" value="' . esc_attr( $loc ) . '">'
		. '<input type="text" placeholder="' . esc_attr__( 'Notes', 'celb-mgmt' ) . '" name="proj_day[' . $i . '][notes]" value="' . esc_attr( $notes ) . '">'
		. '<select class="celb-day-status" name="proj_day[' . $i . '][status]">' . $sel . '</select>'
		. '<span class="celb-day-postpone"' . $pp_style . '> → '
		. '<input type="date" class="celb-day-newdate" name="proj_day[' . $i . '][new_date]" value="' . esc_attr( $ndate ) . '" title="' . esc_attr__( 'New date', 'celb-mgmt' ) . '">'
		. '<input type="time" name="proj_day[' . $i . '][new_time]" value="' . esc_attr( isset( $d['new_time'] ) ? $d['new_time'] : '' ) . '" title="' . esc_attr__( 'New time', 'celb-mgmt' ) . '" style="width:104px">'
		. '</span>'
		. '<button type="button" class="button-link celb-rep-del">' . esc_html__( 'Remove', 'celb-mgmt' ) . '</button></div>';
}
function celb_proj_box_days( $post ) {
	$days = get_post_meta( $post->ID, '_proj_days', true );
	if ( ! is_array( $days ) ) { $days = array(); }
	$c = celb_project_counts( $days );
	echo '<p class="celb-day-counts"><strong class="t">' . (int) $c['total'] . '</strong> ' . esc_html__( 'total', 'celb-mgmt' ) . ' · <strong class="c">' . (int) $c['completed'] . '</strong> ' . esc_html__( 'completed', 'celb-mgmt' ) . ' · <strong class="u">' . (int) $c['upcoming'] . '</strong> ' . esc_html__( 'upcoming', 'celb-mgmt' ) . '</p>';
	echo '<div class="celb-rep"><div class="celb-rep-rows">';
	foreach ( $days as $i => $d ) { echo celb_day_row_html( (int) $i, $d ); }
	echo '</div><button type="button" class="button celb-rep-add" data-target="day">+ ' . esc_html__( 'Add shooting day', 'celb-mgmt' ) . '</button>';
	echo '<script type="text/html" class="celb-tpl-day">' . celb_day_row_html( '__i__', array() ) . '</script></div>';
}

function celb_media_item_html( $a ) {
	if ( 'image' === $a['kind'] ) {
		$icon = '<img src="' . esc_url( $a['thumb'] ? $a['thumb'] : $a['url'] ) . '" alt="">';
	} else {
		$g    = 'video' === $a['kind'] ? '🎬' : ( 'pdf' === $a['kind'] ? '📄' : '📎' );
		$icon = '<span class="celb-media-ic">' . $g . '</span>';
	}
	return '<span class="celb-media-item" data-id="' . (int) $a['id'] . '">' . $icon . '<span class="nm">' . esc_html( $a['name'] ) . '</span><button type="button" class="celb-media-del">✕</button></span>';
}
function celb_media_box( $post_id, $meta_key, $field ) {
	$ids = get_post_meta( $post_id, $meta_key, true );
	if ( ! is_array( $ids ) ) { $ids = array(); }
	echo '<div class="celb-media" data-field="' . esc_attr( $field ) . '">';
	echo '<input type="hidden" name="' . esc_attr( $field ) . '" class="celb-media-ids" value="' . esc_attr( implode( ',', array_map( 'intval', $ids ) ) ) . '">';
	echo '<div class="celb-media-list">';
	foreach ( celb_attachment_data( $ids ) as $a ) { echo celb_media_item_html( $a ); }
	echo '</div>';
	echo '<button type="button" class="button celb-media-add">+ ' . esc_html__( 'Add files (PDF, script, call sheet, image, video, doc)', 'celb-mgmt' ) . '</button>';
	echo '</div>';
}
function celb_proj_box_atts( $post ) { celb_media_box( $post->ID, '_proj_attachments', 'proj_atts' ); }
function celb_sched_box_atts( $post ) { celb_media_box( $post->ID, '_sched_attachments', 'sched_atts' ); }

function celb_proj_box_updates( $post ) {
	$u = get_post_meta( $post->ID, '_proj_updates', true );
	if ( ! is_array( $u ) ) { $u = array(); }
	echo '<p><label>' . esc_html__( 'Add a note (saved to history on update):', 'celb-mgmt' ) . '<br><textarea name="proj_update_note" class="widefat" rows="2"></textarea></label></p>';
	echo '<div class="celb-updates">';
	if ( $u ) {
		foreach ( array_reverse( $u ) as $row ) {
			echo '<div class="celb-up"><div>' . esc_html( $row['text'] ) . '</div><small>' . esc_html( $row['user'] ) . ' · ' . esc_html( $row['time'] ) . '</small></div>';
		}
	} else {
		echo '<p class="description">' . esc_html__( 'No updates yet.', 'celb-mgmt' ) . '</p>';
	}
	echo '</div>';
}

/* UI: includes/admin-workspace.php */

/* ---- Save ---- */
function celb_wc_admin_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }

	if ( isset( $_POST['celb_proj_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['celb_proj_nonce'] ), 'celb_proj_save' ) && current_user_can( 'edit_post', $post_id ) ) {
		$proj_prev_status = (string) get_post_meta( $post_id, '_proj_status', true );
		update_post_meta( $post_id, '_proj_celeb', isset( $_POST['proj_celeb'] ) ? absint( $_POST['proj_celeb'] ) : 0 );
		update_post_meta( $post_id, '_proj_celeb_email', sanitize_email( wp_unslash( $_POST['proj_celeb_email'] ?? '' ) ) );
		update_post_meta( $post_id, '_proj_company', sanitize_text_field( wp_unslash( $_POST['proj_company'] ?? '' ) ) );
		update_post_meta( $post_id, '_proj_type', sanitize_text_field( wp_unslash( $_POST['proj_type'] ?? '' ) ) );
		update_post_meta( $post_id, '_proj_type_other', sanitize_text_field( wp_unslash( $_POST['proj_type_other'] ?? '' ) ) );
		$proj_new_status = sanitize_text_field( wp_unslash( $_POST['proj_status'] ?? '' ) );
		update_post_meta( $post_id, '_proj_status', $proj_new_status );
		update_post_meta( $post_id, '_proj_recur', ! empty( $_POST['proj_recur'] ) ? '1' : '' );
		update_post_meta( $post_id, '_proj_recur_day', isset( $_POST['proj_recur_day'] ) ? max( 1, min( 7, absint( $_POST['proj_recur_day'] ) ) ) : 1 );
		update_post_meta( $post_id, '_proj_recur_time', ( isset( $_POST['proj_recur_time'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['proj_recur_time'] ) ) ? $_POST['proj_recur_time'] : '' );
		update_post_meta( $post_id, '_proj_recur_dur', (int) round( floatval( $_POST['proj_recur_dur'] ?? 0 ) * 60 ) );
		update_post_meta( $post_id, '_proj_recur_until', ( isset( $_POST['proj_recur_until'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['proj_recur_until'] ) ) ? $_POST['proj_recur_until'] : '' );
		update_post_meta( $post_id, '_proj_start', ( isset( $_POST['proj_start'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['proj_start'] ) ) ? $_POST['proj_start'] : '' );
		update_post_meta( $post_id, '_proj_end', ( isset( $_POST['proj_end'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['proj_end'] ) ) ? $_POST['proj_end'] : '' );
		update_post_meta( $post_id, '_proj_notes', sanitize_textarea_field( wp_unslash( $_POST['proj_notes'] ?? '' ) ) );
		update_post_meta( $post_id, '_proj_reminder', isset( $_POST['proj_reminder'] ) ? (int) $_POST['proj_reminder'] : 1440 );
		// Project-level status change → log + email the celebrity.
		if ( $proj_prev_status !== $proj_new_status && '' !== $proj_new_status ) {
			celb_proj_add_update( $post_id, sprintf( /* translators: 1: old, 2: new */ __( 'Project status: %1$s → %2$s', 'celb-mgmt' ), $proj_prev_status ? $proj_prev_status : __( '(none)', 'celb-mgmt' ), $proj_new_status ) );
			celb_notify_status_change( 'project', $post_id, $proj_prev_status, $proj_new_status );
		}

		$locs = array();
		if ( ! empty( $_POST['proj_loc'] ) && is_array( $_POST['proj_loc'] ) ) {
			foreach ( wp_unslash( $_POST['proj_loc'] ) as $l ) {
				$c = celb_clean_location( array( 'label' => $l['label'] ?? '', 'address' => $l['address'] ?? '' ) );
				if ( $c ) { $locs[] = $c; }
			}
		}
		update_post_meta( $post_id, '_proj_locations', $locs );

		$days = array();
		$old_by_date = array();
		$old_days_p  = get_post_meta( $post_id, '_proj_days', true );
		if ( is_array( $old_days_p ) ) {
			foreach ( $old_days_p as $od ) {
				if ( ! empty( $od['date'] ) ) {
					$old_by_date[ $od['date'] ] = $od;
				}
			}
		}
		if ( ! empty( $_POST['proj_day'] ) && is_array( $_POST['proj_day'] ) ) {
			foreach ( wp_unslash( $_POST['proj_day'] ) as $d ) {
				$date = ( isset( $d['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] ) ) ? $d['date'] : '';
				if ( '' === $date ) { continue; }
				$status = isset( $d['status'] ) ? sanitize_key( $d['status'] ) : 'scheduled';
				if ( ! in_array( $status, array( 'scheduled', 'completed', 'postponed', 'canceled' ), true ) ) { $status = 'scheduled'; }
				$ndate = ( isset( $d['new_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['new_date'] ) ) ? $d['new_date'] : '';
				$ncall = sanitize_text_field( $d['new_call'] ?? '' );
				$ntime = ( isset( $d['new_time'] ) && preg_match( '/^\d{2}:\d{2}$/', $d['new_time'] ) ) ? $d['new_time'] : '';
				$dtime = ( isset( $d['time'] ) && preg_match( '/^\d{2}:\d{2}$/', $d['time'] ) ) ? $d['time'] : '';
				$ddur  = (int) round( floatval( $d['dur'] ?? 0 ) * 60 );
				$days[] = array(
					'date'     => $date,
					'time'     => $dtime,
					'dur'      => $ddur,
					'call'     => sanitize_text_field( $d['call'] ?? '' ),
					'location' => sanitize_text_field( $d['location'] ?? '' ),
					'status'   => $status,
					'new_date' => $ndate,
					'new_time' => $ntime,
					'new_call' => $ncall,
					'notes'    => sanitize_text_field( $d['notes'] ?? '' ),
				);
				// Change log: record status transitions, keeping the original date.
				$prev        = isset( $old_by_date[ $date ] ) ? $old_by_date[ $date ] : null;
				$prev_status = ( $prev && ! empty( $prev['status'] ) ) ? $prev['status'] : 'scheduled';
				if ( $prev_status !== $status ) {
					if ( 'postponed' === $status ) {
						/* translators: 1: original date, 2: new date */
						celb_proj_add_update( $post_id, sprintf( __( 'Shooting day %1$s postponed → %2$s', 'celb-mgmt' ), $date, $ndate ? $ndate : __( '(new date TBD)', 'celb-mgmt' ) ) );
						celb_notify_day_change( $post_id, $date, 'postponed', $ndate, $ncall );
					} elseif ( 'canceled' === $status ) {
						/* translators: %s: date */
						celb_proj_add_update( $post_id, sprintf( __( 'Shooting day %s canceled', 'celb-mgmt' ), $date ) );
						celb_notify_day_change( $post_id, $date, 'canceled' );
					} elseif ( 'completed' === $status ) {
						/* translators: %s: date */
						celb_proj_add_update( $post_id, sprintf( __( 'Shooting day %s marked completed', 'celb-mgmt' ), $date ) );
					} else {
						/* translators: %s: date */
						celb_proj_add_update( $post_id, sprintf( __( 'Shooting day %s reset to scheduled', 'celb-mgmt' ), $date ) );
					}
				}
			}
			usort( $days, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );
		}
		update_post_meta( $post_id, '_proj_days', $days );

		$ids = array();
		if ( isset( $_POST['proj_atts'] ) ) {
			foreach ( explode( ',', sanitize_text_field( wp_unslash( $_POST['proj_atts'] ) ) ) as $x ) {
				$x = (int) trim( $x );
				if ( $x ) { $ids[] = $x; }
			}
		}
		update_post_meta( $post_id, '_proj_attachments', $ids );

		$note = isset( $_POST['proj_update_note'] ) ? trim( wp_unslash( $_POST['proj_update_note'] ) ) : '';
		celb_proj_add_update( $post_id, '' !== $note ? $note : __( 'Updated in admin', 'celb-mgmt' ) );
	}

	if ( isset( $_POST['celb_sched_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['celb_sched_nonce'] ), 'celb_sched_save' ) && current_user_can( 'edit_post', $post_id ) ) {
		$sched_prev_status = (string) get_post_meta( $post_id, '_sched_status', true );
		update_post_meta( $post_id, '_sched_celeb', isset( $_POST['sched_celeb'] ) ? absint( $_POST['sched_celeb'] ) : 0 );
		update_post_meta( $post_id, '_sched_celeb_email', sanitize_email( wp_unslash( $_POST['sched_celeb_email'] ?? '' ) ) );
		$sched_new_status = sanitize_text_field( wp_unslash( $_POST['sched_status'] ?? '' ) );
		update_post_meta( $post_id, '_sched_status', $sched_new_status );
		update_post_meta( $post_id, '_sched_type', sanitize_text_field( wp_unslash( $_POST['sched_type'] ?? '' ) ) );
		update_post_meta( $post_id, '_sched_type_other', sanitize_text_field( wp_unslash( $_POST['sched_type_other'] ?? '' ) ) );
		update_post_meta( $post_id, '_sched_new_date', ( isset( $_POST['sched_new_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['sched_new_date'] ) ) ? $_POST['sched_new_date'] : '' );
		update_post_meta( $post_id, '_sched_new_time', ( isset( $_POST['sched_new_time'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['sched_new_time'] ) ) ? $_POST['sched_new_time'] : '' );
		update_post_meta( $post_id, '_sched_recur', ! empty( $_POST['sched_recur'] ) ? '1' : '' );
		update_post_meta( $post_id, '_sched_recur_until', ( isset( $_POST['sched_recur_until'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['sched_recur_until'] ) ) ? $_POST['sched_recur_until'] : '' );
		update_post_meta( $post_id, '_sched_date', ( isset( $_POST['sched_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['sched_date'] ) ) ? $_POST['sched_date'] : '' );
		update_post_meta( $post_id, '_sched_time', ( isset( $_POST['sched_time'] ) && preg_match( '/^\d{2}:\d{2}$/', $_POST['sched_time'] ) ) ? $_POST['sched_time'] : '' );
		update_post_meta( $post_id, '_sched_duration', (int) round( floatval( $_POST['sched_duration'] ?? 0 ) * 60 ) );
		update_post_meta( $post_id, '_sched_desc', sanitize_textarea_field( wp_unslash( $_POST['sched_desc'] ?? '' ) ) );
		update_post_meta( $post_id, '_sched_prep', sanitize_textarea_field( wp_unslash( $_POST['sched_prep'] ?? '' ) ) );
		update_post_meta( $post_id, '_sched_reminder', absint( $_POST['sched_reminder'] ?? 0 ) );
		if ( $sched_prev_status !== $sched_new_status && '' !== $sched_new_status ) {
			celb_notify_status_change( 'schedule', $post_id, $sched_prev_status, $sched_new_status );
		}
		$c = celb_clean_location( array( 'label' => wp_unslash( $_POST['sched_loc_label'] ?? '' ), 'address' => wp_unslash( $_POST['sched_loc_addr'] ?? '' ) ) );
		update_post_meta( $post_id, '_sched_location', $c ? $c : array() );
	}
}
add_action( 'save_post', 'celb_wc_admin_save' );

/* UI: includes/admin-workspace.php */

/* ---- Admin assets ---- */
function celb_wc_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'celb_project', 'celb_sched' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'celb-wc-admin', CELB_URL . 'assets/celb-workcenter-admin.js', array( 'jquery' ), CELB_VERSION, true );
	$css = '.celb-rep-row{display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap}'
		. '.celb-rep-row input[type=text],.celb-rep-row input[type=date]{flex:1 1 140px}'
		. '.celb-rep-add{margin-top:6px}.celb-day-done{white-space:nowrap}'
		. '.celb-day-counts{font-size:13px;background:#f6f7f7;padding:8px 12px;border-radius:6px;display:inline-block}'
		. '.celb-media-list{display:flex;flex-wrap:wrap;gap:8px;margin:6px 0}'
		. '.celb-media-item{display:inline-flex;align-items:center;gap:6px;border:1px solid #dcdcde;border-radius:6px;padding:4px 8px;background:#fff;max-width:240px}'
		. '.celb-media-item img{width:32px;height:32px;object-fit:cover;border-radius:4px}'
		. '.celb-media-ic{font-size:20px}.celb-media-item .nm{font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px}'
		. '.celb-media-del{border:0;background:none;color:#b32d2e;cursor:pointer;font-size:14px}'
		. '.celb-updates{max-height:300px;overflow:auto}.celb-up{border-left:3px solid #dcdcde;padding:2px 0 2px 10px;margin-bottom:8px}.celb-up small{color:#787c82}'
		. '.celb-maplink{font-size:12px}';
	wp_add_inline_style( 'wp-admin', $css );
}
add_action( 'admin_enqueue_scripts', 'celb_wc_admin_assets' );

/* -------------------------------------------------------------------------
 * 18. REQUESTS  (per-celebrity booking / contact inbox)
 *     Capture form -> celb_request CPT -> admin inbox + app tab + widget.
 * ---------------------------------------------------------------------- */

function celb_request_types() {
	return array( 'Booking', 'Appearance', 'Interview', 'Brand Collaboration', 'Event', 'Endorsement', 'Other' );
}
function celb_request_statuses() {
	return array( 'new', 'in_progress', 'closed' );
}
function celb_request_status_label( $s ) {
	$map = array( 'new' => __( 'New', 'celb-mgmt' ), 'in_progress' => __( 'In progress', 'celb-mgmt' ), 'closed' => __( 'Closed', 'celb-mgmt' ) );
	return isset( $map[ $s ] ) ? $map[ $s ] : __( 'New', 'celb-mgmt' );
}

function celb_register_request_cpt() {
	register_post_type( 'celb_request', array(
		'labels' => array(
			'name'          => __( 'Requests', 'celb-mgmt' ),
			'singular_name' => __( 'Request', 'celb-mgmt' ),
			'add_new'       => __( 'Add Request', 'celb-mgmt' ),
			'add_new_item'  => __( 'Add Request', 'celb-mgmt' ),
			'edit_item'     => __( 'Request', 'celb-mgmt' ),
			'all_items'     => __( 'Requests', 'celb-mgmt' ),
			'menu_name'     => __( 'Requests', 'celb-mgmt' ),
			'search_items'  => __( 'Search Requests', 'celb-mgmt' ),
			'not_found'     => __( 'No requests yet', 'celb-mgmt' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'edit.php?post_type=' . CELB_CPT,
		'supports'     => array( 'title' ),
		'menu_icon'    => 'dashicons-email-alt',
	) );
}
add_action( 'init', 'celb_register_request_cpt' );

function celb_request_data( $id ) {
	$celeb = (int) get_post_meta( $id, '_req_celeb', true );
	return array(
		'id'         => (int) $id,
		'celeb'      => $celeb,
		'celeb_name' => $celeb ? get_the_title( $celeb ) : '',
		'name'       => (string) get_post_meta( $id, '_req_name', true ),
		'email'      => (string) get_post_meta( $id, '_req_email', true ),
		'phone'      => (string) get_post_meta( $id, '_req_phone', true ),
		'company'    => (string) get_post_meta( $id, '_req_company', true ),
		'type'       => (string) get_post_meta( $id, '_req_type', true ),
		'message'    => (string) get_post_meta( $id, '_req_message', true ),
		'date'       => (string) get_post_meta( $id, '_req_date', true ),
		'status'     => (string) ( get_post_meta( $id, '_req_status', true ) ? get_post_meta( $id, '_req_status', true ) : 'new' ),
		'created'    => get_the_date( 'Y-m-d H:i', $id ),
	);
}

/* ---- Public capture form: [CLEB_request celeb="123"] ---- */
function celb_request_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'celeb' => '' ), $atts, 'CLEB_request' );
	$out   = '';
	$fixed = 0;
	if ( $atts['celeb'] ) {
		$fixed = is_numeric( $atts['celeb'] ) ? (int) $atts['celeb'] : (int) celb_smartlink_lookup( sanitize_title( $atts['celeb'] ) );
	}

	if ( isset( $_POST['celb_request_submit'] ) ) {
		$ok = isset( $_POST['celb_request_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['celb_request_nonce'] ), 'celb_request' );
		$hp = isset( $_POST['celb_company_url'] ) ? trim( wp_unslash( $_POST['celb_company_url'] ) ) : '';
		$nm = isset( $_POST['req_name'] ) ? sanitize_text_field( wp_unslash( $_POST['req_name'] ) ) : '';
		$cl = $fixed ? $fixed : ( isset( $_POST['req_celeb'] ) ? absint( $_POST['req_celeb'] ) : 0 );
		if ( $ok && '' === $hp && '' !== $nm && $cl ) {
			$rid = wp_insert_post( array(
				'post_type'   => 'celb_request',
				'post_status' => 'publish',
				'post_title'  => $nm . ' — ' . get_the_title( $cl ),
			) );
			if ( $rid && ! is_wp_error( $rid ) ) {
				update_post_meta( $rid, '_req_celeb', $cl );
				update_post_meta( $rid, '_req_name', $nm );
				update_post_meta( $rid, '_req_email', sanitize_email( wp_unslash( $_POST['req_email'] ?? '' ) ) );
				update_post_meta( $rid, '_req_phone', sanitize_text_field( wp_unslash( $_POST['req_phone'] ?? '' ) ) );
				update_post_meta( $rid, '_req_company', sanitize_text_field( wp_unslash( $_POST['req_company'] ?? '' ) ) );
				update_post_meta( $rid, '_req_type', sanitize_text_field( wp_unslash( $_POST['req_type'] ?? '' ) ) );
				update_post_meta( $rid, '_req_message', sanitize_textarea_field( wp_unslash( $_POST['req_message'] ?? '' ) ) );
				$rdate = isset( $_POST['req_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['req_date'] ) ? $_POST['req_date'] : '';
				update_post_meta( $rid, '_req_date', $rdate );
				update_post_meta( $rid, '_req_status', 'new' );
				return '<div class="celb-scope celb-request-done"><p>' . esc_html__( 'Thank you — your request has been received. The management team will be in touch.', 'celb-mgmt' ) . '</p></div>';
			}
		}
		$out .= '<p class="celb-request-err">' . esc_html__( 'Please complete the required fields and try again.', 'celb-mgmt' ) . '</p>';
	}

	ob_start();
	?>
	<div class="celb-scope celb-request-form">
		<form method="post">
			<?php wp_nonce_field( 'celb_request', 'celb_request_nonce' ); ?>
			<?php echo $out; ?>
			<input type="text" name="celb_company_url" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
			<?php if ( ! $fixed ) : ?>
				<label><?php esc_html_e( 'Celebrity', 'celb-mgmt' ); ?>
					<select name="req_celeb" required>
						<option value=""><?php esc_html_e( 'Select…', 'celb-mgmt' ); ?></option>
						<?php foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $c ) : ?>
							<option value="<?php echo (int) $c->ID; ?>"><?php echo esc_html( get_the_title( $c->ID ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<label><?php esc_html_e( 'Your name', 'celb-mgmt' ); ?> *<input type="text" name="req_name" required></label>
			<label><?php esc_html_e( 'Email', 'celb-mgmt' ); ?><input type="email" name="req_email"></label>
			<label><?php esc_html_e( 'Phone / WhatsApp', 'celb-mgmt' ); ?><input type="text" name="req_phone"></label>
			<label><?php esc_html_e( 'Company / Brand', 'celb-mgmt' ); ?><input type="text" name="req_company"></label>
			<label><?php esc_html_e( 'Request type', 'celb-mgmt' ); ?>
				<select name="req_type"><?php foreach ( celb_request_types() as $t ) : ?><option><?php echo esc_html( $t ); ?></option><?php endforeach; ?></select>
			</label>
			<label><?php esc_html_e( 'Preferred date', 'celb-mgmt' ); ?><input type="date" name="req_date"></label>
			<label><?php esc_html_e( 'Details', 'celb-mgmt' ); ?><textarea name="req_message" rows="4"></textarea></label>
			<button type="submit" name="celb_request_submit" value="1"><?php esc_html_e( 'Send request', 'celb-mgmt' ); ?></button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
/* CLEB_request now renders the new Artist Contact Form (checkbox multi-select,
   All-artists, WhatsApp, reCAPTCHA). The original booking form (celebrity
   dropdown, request type, preferred date) remains available as [CLEB_booking]. */
add_shortcode( 'CLEB_request', 'celb_artreq_shortcode' );
add_shortcode( 'CLEB_booking', 'celb_request_shortcode' );

/* ---- Admin inbox: filter by celebrity + sort by date + status ---- */
add_action( 'restrict_manage_posts', function ( $pt ) {
	if ( 'celb_request' !== $pt ) { return; }
	$sel = isset( $_GET['req_celeb'] ) ? (int) $_GET['req_celeb'] : 0;
	echo '<select name="req_celeb"><option value="0">' . esc_html__( 'All celebrities', 'celb-mgmt' ) . '</option>';
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $c ) {
		echo '<option value="' . (int) $c->ID . '"' . selected( $sel, $c->ID, false ) . '>' . esc_html( get_the_title( $c->ID ) ) . '</option>';
	}
	echo '</select>';
	$ss = isset( $_GET['req_status'] ) ? sanitize_key( $_GET['req_status'] ) : '';
	echo '<select name="req_status"><option value="">' . esc_html__( 'Any status', 'celb-mgmt' ) . '</option>';
	foreach ( celb_request_statuses() as $st ) {
		echo '<option value="' . esc_attr( $st ) . '"' . selected( $ss, $st, false ) . '>' . esc_html( celb_request_status_label( $st ) ) . '</option>';
	}
	echo '</select>';
} );
add_filter( 'parse_query', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) { return $q; }
	if ( ( $q->get( 'post_type' ) ) !== 'celb_request' ) { return $q; }
	$mq = array();
	if ( ! empty( $_GET['req_celeb'] ) ) { $mq[] = array( 'key' => '_req_celeb', 'value' => (int) $_GET['req_celeb'] ); }
	if ( ! empty( $_GET['req_status'] ) ) { $mq[] = array( 'key' => '_req_status', 'value' => sanitize_key( $_GET['req_status'] ) ); }
	if ( $mq ) { $q->set( 'meta_query', $mq ); }
	return $q;
} );

/* Request detail view: includes/admin-workspace.php. Status save: */
add_action( 'save_post', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['celb_request_admin_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_request_admin_nonce'] ), 'celb_request_admin' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	if ( isset( $_POST['req_status'] ) && in_array( $_POST['req_status'], celb_request_statuses(), true ) ) {
		update_post_meta( $post_id, '_req_status', sanitize_key( $_POST['req_status'] ) );
	}
} );

/* ---- REST (manager only) ---- */
function celb_req_routes() {
	$edit = array( 'permission_callback' => 'celb_rest_can_edit' );
	register_rest_route( 'celb/v1', '/requests', array( array( 'methods' => 'GET', 'callback' => 'celb_rest_req_list' ) + $edit ) );
	register_rest_route( 'celb/v1', '/requests/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'callback' => 'celb_rest_req_get' ) + $edit,
		array( 'methods' => 'POST', 'callback' => 'celb_rest_req_update' ) + $edit,
	) );
}
add_action( 'rest_api_init', 'celb_req_routes' );

function celb_rest_req_list( $req ) {
	$celeb = absint( $req->get_param( 'celeb' ) );
	$order = strtoupper( (string) $req->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
	$args  = array( 'post_type' => 'celb_request', 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'date', 'order' => $order, 'suppress_filters' => true );
	if ( $celeb ) { $args['meta_query'] = array( array( 'key' => '_req_celeb', 'value' => $celeb ) ); }
	$out = array();
	foreach ( get_posts( $args ) as $p ) { $out[] = celb_request_data( $p->ID ); }
	return rest_ensure_response( $out );
}
function celb_rest_req_get( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_request' ) { return new WP_Error( 'celb_404', 'Not found.', array( 'status' => 404 ) ); }
	return rest_ensure_response( celb_request_data( $id ) );
}
function celb_rest_req_update( $req ) {
	$id = absint( $req['id'] );
	if ( get_post_type( $id ) !== 'celb_request' ) { return new WP_Error( 'celb_404', 'Not found.', array( 'status' => 404 ) ); }
	$p = $req->get_json_params();
	if ( is_array( $p ) && isset( $p['status'] ) && in_array( $p['status'], celb_request_statuses(), true ) ) {
		update_post_meta( $id, '_req_status', $p['status'] );
	}
	return rest_ensure_response( celb_request_data( $id ) );
}

function celb_request_counts() {
	$total = (int) wp_count_posts( 'celb_request' )->publish;
	$new   = count( get_posts( array( 'post_type' => 'celb_request', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_req_status', 'meta_value' => 'new', 'suppress_filters' => true ) ) );
	return array( 'total' => $total, 'new' => $new );
}


function celb_wc_glance() {
	$today  = current_time( 'Y-m-d' );
	$ts     = strtotime( $today );
	$mstart = date( 'Y-m-01', $ts );
	$mend   = date( 'Y-m-t', $ts );

	$proj_total  = (int) wp_count_posts( 'celb_project' )->publish;
	$sched_total = (int) wp_count_posts( 'celb_sched' )->publish;

	$proj_month = array();
	$pm = get_posts( array(
		'post_type' => 'celb_project', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true,
		'meta_key' => '_proj_start', 'orderby' => 'meta_value', 'order' => 'ASC',
		'meta_query' => array( array( 'key' => '_proj_start', 'value' => array( $mstart, $mend ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) ),
	) );
	foreach ( $pm as $p ) {
		$st = get_post_meta( $p->ID, '_proj_status', true );
		if ( in_array( $st, array( 'Completed', 'Cancelled' ), true ) ) { continue; }
		$cl = (int) get_post_meta( $p->ID, '_proj_celeb', true );
		$proj_month[] = array( 'id' => $p->ID, 'name' => get_the_title( $p->ID ), 'celeb' => $cl, 'celeb_name' => $cl ? get_the_title( $cl ) : '', 'start' => get_post_meta( $p->ID, '_proj_start', true ), 'status' => $st );
	}

	$sched_month = array();
	$sm = get_posts( array(
		'post_type' => 'celb_sched', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true,
		'meta_key' => '_sched_date', 'orderby' => 'meta_value', 'order' => 'ASC',
		'meta_query' => array( array( 'key' => '_sched_date', 'value' => array( $today, $mend ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) ),
	) );
	foreach ( $sm as $s ) {
		$cl = (int) get_post_meta( $s->ID, '_sched_celeb', true );
		$sched_month[] = array( 'id' => $s->ID, 'title' => get_the_title( $s->ID ), 'celeb' => $cl, 'celeb_name' => $cl ? get_the_title( $cl ) : '', 'date' => get_post_meta( $s->ID, '_sched_date', true ), 'time' => get_post_meta( $s->ID, '_sched_time', true ), 'type' => get_post_meta( $s->ID, '_sched_type', true ) );
	}

	return array( 'proj_total' => $proj_total, 'sched_total' => $sched_total, 'proj_month' => $proj_month, 'sched_month' => $sched_month );
}

/* ---- WordPress dashboard widget ---- */
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'edit_posts' ) ) { return; }
	wp_add_dashboard_widget( 'celb_requests_widget', __( 'iLike — Incoming Requests', 'celb-mgmt' ), 'celb_dashboard_widget' );
} );
function celb_dashboard_widget() {
	$g  = celb_wc_glance();
	$rc = celb_request_counts();
	$au = function ( $pt ) { return get_admin_url( null, 'edit.php?post_type=' . $pt ); };

	echo '<div style="display:flex;gap:20px;flex-wrap:wrap;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#787c82;margin-top:0">';
	echo '<span><strong style="display:block;font-size:24px;color:#1d2327">' . (int) $g['sched_total'] . '</strong>' . esc_html__( 'Schedule', 'celb-mgmt' ) . '</span>';
	echo '<span><strong style="display:block;font-size:24px;color:#1d2327">' . (int) $g['proj_total'] . '</strong>' . esc_html__( 'Projects', 'celb-mgmt' ) . '</span>';
	echo '<span><strong style="display:block;font-size:24px;color:#1d2327">' . (int) $rc['new'] . '</strong>' . esc_html__( 'New requests', 'celb-mgmt' ) . '</span>';
	echo '</div>';

	echo '<h3 style="margin:16px 0 6px;font-size:13px">' . esc_html__( 'This month — Schedule', 'celb-mgmt' ) . '</h3>';
	if ( $g['sched_month'] ) {
		echo '<table class="widefat striped"><tbody>';
		foreach ( $g['sched_month'] as $sd ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $sd['id'] ) ) . '"><strong>' . esc_html( $sd['title'] ) . '</strong></a><br><span style="color:#787c82">' . esc_html( $sd['celeb_name'] . ( $sd['type'] ? ' · ' . $sd['type'] : '' ) ) . '</span></td>';
			echo '<td style="text-align:right;white-space:nowrap">' . esc_html( trim( $sd['date'] . ' ' . $sd['time'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p style="color:#787c82">' . esc_html__( 'Nothing scheduled for the rest of this month.', 'celb-mgmt' ) . '</p>';
	}

	echo '<h3 style="margin:16px 0 6px;font-size:13px">' . esc_html__( 'This month — Projects', 'celb-mgmt' ) . '</h3>';
	if ( $g['proj_month'] ) {
		echo '<table class="widefat striped"><tbody>';
		foreach ( $g['proj_month'] as $pr ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $pr['id'] ) ) . '"><strong>' . esc_html( $pr['name'] ) . '</strong></a><br><span style="color:#787c82">' . esc_html( $pr['celeb_name'] . ( $pr['status'] ? ' · ' . $pr['status'] : '' ) ) . '</span></td>';
			echo '<td style="text-align:right;white-space:nowrap">' . esc_html( $pr['start'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p style="color:#787c82">' . esc_html__( 'No projects starting this month.', 'celb-mgmt' ) . '</p>';
	}

	echo '<p style="margin:14px 0 0;display:flex;gap:8px;flex-wrap:wrap">';
	echo '<a class="button" href="' . esc_url( $au( 'celb_sched' ) ) . '">' . esc_html__( 'Schedule', 'celb-mgmt' ) . '</a>';
	echo '<a class="button" href="' . esc_url( $au( 'celb_project' ) ) . '">' . esc_html__( 'Projects', 'celb-mgmt' ) . '</a>';
	echo '<a class="button button-primary" href="' . esc_url( $au( 'celb_request' ) ) . '">' . esc_html__( 'Requests', 'celb-mgmt' ) . ' (' . (int) $rc['total'] . ')</a>';
	echo '</p>';
}
/* =========================================================================
 * 17. ONLINE CONTRACTS — templates, contract records, e-signature foundation
 *     (Drop 1: admin + data model. Signing page, PDF + email follow in Drop 2.)
 * ====================================================================== */

/* ---- Custom post types (admin-only, grouped under the Celebrities menu) ---- */
function celb_register_contract_cpts() {
	register_post_type( 'celb_ctpl', array(
		'labels' => array(
			'name'               => __( 'Contract Templates', 'celb-mgmt' ),
			'singular_name'      => __( 'Contract Template', 'celb-mgmt' ),
			'add_new'            => __( 'Add Template', 'celb-mgmt' ),
			'add_new_item'       => __( 'Add Contract Template', 'celb-mgmt' ),
			'edit_item'          => __( 'Edit Contract Template', 'celb-mgmt' ),
			'new_item'           => __( 'New Contract Template', 'celb-mgmt' ),
			'view_item'          => __( 'View Template', 'celb-mgmt' ),
			'search_items'       => __( 'Search Templates', 'celb-mgmt' ),
			'menu_name'          => __( 'Contract Templates', 'celb-mgmt' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=' . CELB_CPT,
		'supports'        => array( 'title', 'editor' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'menu_icon'       => 'dashicons-media-text',
	) );

	register_post_type( 'celb_contract', array(
		'labels' => array(
			'name'               => __( 'Contracts', 'celb-mgmt' ),
			'singular_name'      => __( 'Contract', 'celb-mgmt' ),
			'add_new'            => __( 'New Contract', 'celb-mgmt' ),
			'add_new_item'       => __( 'New Contract', 'celb-mgmt' ),
			'edit_item'          => __( 'Edit Contract', 'celb-mgmt' ),
			'new_item'           => __( 'New Contract', 'celb-mgmt' ),
			'view_item'          => __( 'View Contract', 'celb-mgmt' ),
			'search_items'       => __( 'Search Contracts', 'celb-mgmt' ),
			'menu_name'          => __( 'Contracts', 'celb-mgmt' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=' . CELB_CPT,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'menu_icon'       => 'dashicons-text-page',
	) );
}
add_action( 'init', 'celb_register_contract_cpts' );

/* ---- Helpers ---- */

/* Template field definitions: array of array( label, key, type, required ). */
function celb_ctpl_fields( $tpl_id ) {
	$f = get_post_meta( $tpl_id, '_ctpl_fields', true );
	return is_array( $f ) ? $f : array();
}

/* Allowed field input types. */
function celb_ctpl_field_types() {
	return array(
		'text'   => __( 'Text', 'celb-mgmt' ),
		'email'  => __( 'Email', 'celb-mgmt' ),
		'number' => __( 'Number', 'celb-mgmt' ),
		'date'   => __( 'Date', 'celb-mgmt' ),
		'tel'    => __( 'Phone', 'celb-mgmt' ),
	);
}

/* Protected directory for signed contract PDFs (web access denied). */
function celb_contract_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'celb-contracts';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		@file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}
	return $dir;
}

/* Base URL of the page that holds the [CLEB_sign] shortcode. */
function celb_contract_sign_base() {
	$s = celb_get_settings();
	$u = ! empty( $s['contract_sign_url'] ) ? $s['contract_sign_url'] : home_url( '/sign/' );
	return $u;
}

/* Full per-contract signing link (page URL + cid + token). */
function celb_contract_sign_link( $contract_id ) {
	$token = get_post_meta( $contract_id, '_contract_token', true );
	if ( ! $token ) {
		return '';
	}
	return add_query_arg( array( 'cid' => (int) $contract_id, 'token' => $token ), celb_contract_sign_base() );
}

/* Validate a contract id + token pair (used by the signing page in Drop 2). */
function celb_contract_token_ok( $contract_id, $token ) {
	if ( ! $contract_id || get_post_type( $contract_id ) !== 'celb_contract' ) {
		return false;
	}
	$saved = get_post_meta( $contract_id, '_contract_token', true );
	return $saved && hash_equals( (string) $saved, (string) $token );
}

/* Render the template body with {{placeholders}} replaced by saved values.
   Built-in tokens: {{talent_name}}, {{date}}. Used at signing time. */
function celb_contract_render_body( $contract_id ) {
	$tpl  = (int) get_post_meta( $contract_id, '_contract_tpl', true );
	$lang = celb_contract_lang( $contract_id );
	$data = get_post_meta( $contract_id, '_contract_data', true );
	$data = is_array( $data ) ? $data : array();
	$body = $tpl ? get_post_field( 'post_content', $tpl ) : '';
	$body = apply_filters( 'the_content', $body );

	$cel  = (int) get_post_meta( $contract_id, '_contract_celeb', true );
	$repl = array(
		'{{talent_name}}' => $cel ? get_the_title( $cel ) : '',
		'{{date}}'        => date_i18n( 'd.m.Y' ),
	);
	foreach ( $data as $k => $v ) {
		$repl[ '{{' . $k . '}}' ] = is_scalar( $v ) ? (string) $v : '';
	}
	if ( function_exists( 'celb_contract_rates' ) ) {
		foreach ( celb_contract_rates( $contract_id ) as $rk => $rv ) {
			$repl[ '{{rate_' . $rk . '}}' ] = celb_rate_fmt( $rv );
		}
		$repl['{{commission_table}}'] = celb_contract_commission_table_html( $contract_id, $lang );
		$mth = celb_contract_monthly( $contract_id );
		$repl['{{monthly_fee}}']        = celb_contract_fee_short( $contract_id );
		$repl['{{monthly_fee_amount}}'] = celb_bidi_ltr( celb_contract_fee_amount_str( $contract_id ) );
		$repl['{{currency}}']           = ( $mth['enabled'] && '' !== $mth['amount'] ) ? esc_html( $mth['currency'] ) : '';
		$repl['{{payment_day}}']        = celb_contract_payday_token( $contract_id, $lang );
	}
	return strtr( $body, $repl );
}

/* ---- Contract Template editor UI: includes/admin-workspace.php ---- */
function celb_ctpl_save( $post_id ) {
	if ( ! isset( $_POST['celb_ctpl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_ctpl_nonce'] ), 'celb_ctpl_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'celb_ctpl' ) {
		return;
	}
	update_post_meta( $post_id, '_ctpl_lang', ( isset( $_POST['ctpl_lang'] ) && 'ar' === $_POST['ctpl_lang'] ) ? 'ar' : 'en' );
	$fields = array();
	$valid  = array_keys( celb_ctpl_field_types() );
	if ( isset( $_POST['celb_fields'] ) && is_array( $_POST['celb_fields'] ) ) {
		foreach ( wp_unslash( $_POST['celb_fields'] ) as $row ) {
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$key = isset( $row['key'] ) && '' !== $row['key']
				? sanitize_key( str_replace( '-', '_', $row['key'] ) )
				: sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
			if ( '' === $key ) {
				continue;
			}
			$type = ( isset( $row['type'] ) && in_array( $row['type'], $valid, true ) ) ? $row['type'] : 'text';
			$fields[] = array(
				'label'    => $label,
				'key'      => $key,
				'type'     => $type,
				'required' => empty( $row['required'] ) ? 0 : 1,
			);
		}
	}
	update_post_meta( $post_id, '_ctpl_fields', $fields );
}
add_action( 'save_post_celb_ctpl', 'celb_ctpl_save' );

/* ---- Contract editor UI: includes/admin-workspace.php ---- */
function celb_contract_save( $post_id ) {
	if ( ! isset( $_POST['celb_contract_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_contract_nonce'] ), 'celb_contract_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'celb_contract' ) {
		return;
	}
	$tpl = isset( $_POST['contract_tpl'] ) ? absint( $_POST['contract_tpl'] ) : 0;
	$cel = isset( $_POST['contract_celeb'] ) ? absint( $_POST['contract_celeb'] ) : 0;
	update_post_meta( $post_id, '_contract_tpl', $tpl );
	update_post_meta( $post_id, '_contract_celeb', $cel );

	if ( ! get_post_meta( $post_id, '_contract_token', true ) ) {
		update_post_meta( $post_id, '_contract_token', wp_generate_password( 32, false ) );
	}
	if ( ! get_post_meta( $post_id, '_contract_status', true ) ) {
		update_post_meta( $post_id, '_contract_status', 'pending' );
	}

	// Keep the title meaningful without recursing into this hook.
	$talent = $cel ? get_the_title( $cel ) : __( 'Unassigned', 'celb-mgmt' );
	$want   = sprintf( '%s — %s', __( 'Contract', 'celb-mgmt' ), $talent );
	if ( get_post_field( 'post_title', $post_id ) !== $want ) {
		remove_action( 'save_post_celb_contract', 'celb_contract_save' );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $want ) );
		add_action( 'save_post_celb_contract', 'celb_contract_save' );
	}
}
add_action( 'save_post_celb_contract', 'celb_contract_save' );

/* ---- Gated PDF download (admin/manager, or the owning talent) ---- */
function celb_contract_download() {
	$id = isset( $_GET['contract'] ) ? absint( $_GET['contract'] ) : 0;
	if ( ! $id || get_post_type( $id ) !== 'celb_contract' ) {
		wp_die( esc_html__( 'Contract not found.', 'celb-mgmt' ) );
	}

	$can = current_user_can( 'edit_post', $id );
	if ( $can ) {
		check_admin_referer( 'celb_dl_' . $id );
	} else {
		$owner = (int) get_post_meta( $id, '_contract_celeb', true );
		$can   = ( $owner && function_exists( 'celb_user_celeb_id' ) && celb_user_celeb_id() === $owner );
	}
	if ( ! $can ) {
		wp_die( esc_html__( 'You do not have permission to download this contract.', 'celb-mgmt' ) );
	}

	$path = get_post_meta( $id, '_contract_pdf_path', true );
	if ( ! $path || ! file_exists( $path ) ) {
		wp_die( esc_html__( 'No signed PDF is available yet.', 'celb-mgmt' ) );
	}
	$name = sanitize_file_name( ( get_the_title( $id ) ? get_the_title( $id ) : 'contract-' . $id ) ) . '.pdf';
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore
	exit;
}
add_action( 'admin_post_celb_contract_download', 'celb_contract_download' );
add_action( 'admin_post_nopriv_celb_contract_download', function () {
	wp_die( esc_html__( 'Please open this link while signed in to your iLike dashboard.', 'celb-mgmt' ) );
} );

/* ---- Admin list: status + talent filters ---- */
add_action( 'restrict_manage_posts', function ( $pt ) {
	if ( 'celb_contract' !== $pt ) {
		return;
	}
	$cur = isset( $_GET['c_status'] ) ? sanitize_key( wp_unslash( $_GET['c_status'] ) ) : '';
	echo '<select name="c_status"><option value="">' . esc_html__( 'All statuses', 'celb-mgmt' ) . '</option>';
	foreach ( array( 'pending' => __( 'Pending', 'celb-mgmt' ), 'signed' => __( 'Signed', 'celb-mgmt' ) ) as $k => $lab ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $lab ) . '</option>';
	}
	echo '</select>';

	$curc = isset( $_GET['c_celeb'] ) ? absint( $_GET['c_celeb'] ) : 0;
	$cels = get_posts( array( 'post_type' => CELB_CPT, 'numberposts' => -1, 'post_status' => array( 'publish', 'draft' ), 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<select name="c_celeb"><option value="0">' . esc_html__( 'All talent', 'celb-mgmt' ) . '</option>';
	foreach ( $cels as $c ) {
		echo '<option value="' . esc_attr( $c->ID ) . '" ' . selected( $curc, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
	}
	echo '</select>';
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->get( 'post_type' ) !== 'celb_contract' ) {
		return;
	}
	$mq = array();
	if ( ! empty( $_GET['c_status'] ) ) {
		$mq[] = array( 'key' => '_contract_status', 'value' => sanitize_key( wp_unslash( $_GET['c_status'] ) ) );
	}
	if ( ! empty( $_GET['c_celeb'] ) ) {
		$mq[] = array( 'key' => '_contract_celeb', 'value' => absint( $_GET['c_celeb'] ) );
	}
	if ( $mq ) {
		if ( count( $mq ) > 1 ) {
			$mq['relation'] = 'AND';
		}
		$q->set( 'meta_query', $mq );
	}
} );

/* ---- Per-contract commission percentages ---- */
function celb_contract_rate_defs() {
	return array(
		'artistic_agency'        => __( 'Artistic Works – Agency-Sourced', 'celb-mgmt' ),
		'artistic_independent'   => __( 'Artistic Works – Independently Sourced (Agency-Managed)', 'celb-mgmt' ),
		'commercial_agency'      => __( 'Commercial & Advertising Works – Agency-Sourced', 'celb-mgmt' ),
		'commercial_independent' => __( 'Commercial & Advertising Works – Independently Sourced (Agency-Managed)', 'celb-mgmt' ),
	);
}
function celb_contract_rates( $id ) {
	$r = get_post_meta( $id, '_contract_rates', true );
	return is_array( $r ) ? $r : array();
}
function celb_contract_currencies() {
	return array(
		'EGP' => 'EGP — Egyptian Pound',
		'USD' => 'USD — US Dollar',
		'EUR' => 'EUR — Euro',
		'GBP' => 'GBP — British Pound',
		'SAR' => 'SAR — Saudi Riyal',
		'AED' => 'AED — UAE Dirham',
		'KWD' => 'KWD — Kuwaiti Dinar',
		'QAR' => 'QAR — Qatari Riyal',
	);
}
function celb_contract_monthly( $id ) {
	$cur = (string) get_post_meta( $id, '_contract_currency', true );
	$day = (int) get_post_meta( $id, '_contract_monthly_day', true );
	return array(
		'enabled'  => '1' === get_post_meta( $id, '_contract_monthly_enabled', true ),
		'amount'   => get_post_meta( $id, '_contract_monthly_fee', true ),
		'currency' => $cur ? $cur : 'EGP',
		'day'      => ( $day >= 1 && $day <= 31 ) ? $day : 0,
	);
}
function celb_ordinal_suffix( $n ) {
	$n = (int) $n;
	if ( $n % 100 >= 11 && $n % 100 <= 13 ) {
		return 'th';
	}
	$map = array( 1 => 'st', 2 => 'nd', 3 => 'rd' );
	return isset( $map[ $n % 10 ] ) ? $map[ $n % 10 ] : 'th';
}
function celb_contract_lang( $contract_id ) {
	$tpl = (int) get_post_meta( $contract_id, '_contract_tpl', true );
	$l   = $tpl ? get_post_meta( $tpl, '_ctpl_lang', true ) : '';
	return ( 'ar' === $l ) ? 'ar' : 'en';
}
/* Keep a number/currency run rendering left-to-right even inside Arabic text. */
function celb_bidi_ltr( $s ) {
	if ( '' === $s ) {
		return '';
	}
	return '<span dir="ltr" style="unicode-bidi:isolate;">' . esc_html( $s ) . '</span>';
}
function celb_contract_fee_amount_str( $id ) {
	$m = celb_contract_monthly( $id );
	if ( ! $m['enabled'] || '' === $m['amount'] || null === $m['amount'] ) {
		return '';
	}
	$n = (float) $m['amount'];
	return ( floor( $n ) == $n ) ? number_format( $n, 0 ) : number_format( $n, 2 );
}
/* Short value for the {{monthly_fee}} inline token: "30,000 EGP" (LTR-safe). */
function celb_contract_fee_short( $id ) {
	$amt = celb_contract_fee_amount_str( $id );
	if ( '' === $amt ) {
		return '';
	}
	$m = celb_contract_monthly( $id );
	return celb_bidi_ltr( $amt . ' ' . $m['currency'] );
}
/* Payment-day token: "5th" in English, "5" in Arabic (LTR-safe). */
function celb_contract_payday_token( $id, $lang = 'en' ) {
	$m = celb_contract_monthly( $id );
	if ( ! $m['enabled'] || ! $m['day'] ) {
		return '';
	}
	return celb_bidi_ltr( 'ar' === $lang ? (string) $m['day'] : ( $m['day'] . celb_ordinal_suffix( $m['day'] ) ) );
}
function celb_contract_labels( $lang ) {
	if ( 'ar' === $lang ) {
		return array(
			'category'   => 'الفئة',
			'commission' => 'العمولة',
			'monthly'    => 'الأجر الشهري',
			'per_month'  => 'شهرياً',
			'payable'    => 'تُدفع يوم %s من كل شهر',
			'defs'       => array(
				'artistic_agency'        => 'الأعمال الفنية – من مصادر الوكالة',
				'artistic_independent'   => 'الأعمال الفنية – من مصادر مستقلة (بإدارة الوكالة)',
				'commercial_agency'      => 'الأعمال التجارية والإعلانية – من مصادر الوكالة',
				'commercial_independent' => 'الأعمال التجارية والإعلانية – من مصادر مستقلة (بإدارة الوكالة)',
			),
		);
	}
	return array(
		'category'   => __( 'Category', 'celb-mgmt' ),
		'commission' => __( 'Commission', 'celb-mgmt' ),
		'monthly'    => __( 'Monthly fee / retainer', 'celb-mgmt' ),
		'per_month'  => '/ month',
		'payable'    => 'payable on the %s',
		'defs'       => celb_contract_rate_defs(),
	);
}
function celb_contract_monthly_display( $id, $lang = 'en' ) {
	$amt = celb_contract_fee_amount_str( $id );
	if ( '' === $amt ) {
		return '';
	}
	$m   = celb_contract_monthly( $id );
	$lab = celb_contract_labels( $lang );
	$out = celb_bidi_ltr( $amt . ' ' . $m['currency'] ) . ' ' . $lab['per_month'];
	if ( $m['day'] ) {
		$daytxt = celb_bidi_ltr( 'ar' === $lang ? (string) $m['day'] : ( $m['day'] . celb_ordinal_suffix( $m['day'] ) ) );
		$out   .= ( 'ar' === $lang ? '، ' : ', ' ) . sprintf( $lab['payable'], $daytxt );
	}
	return $out;
}
function celb_rate_fmt( $v ) {
	if ( '' === $v || null === $v ) {
		return '';
	}
	$n = (float) $v;
	$s = ( floor( $n ) == $n ) ? (string) (int) $n : rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
	return $s . '%';
}
function celb_contract_commission_table_html( $id, $lang = 'en' ) {
	$rates = celb_contract_rates( $id );
	$lab   = celb_contract_labels( $lang );
	$rows  = '';
	foreach ( $lab['defs'] as $key => $label ) {
		$val   = isset( $rates[ $key ] ) ? celb_rate_fmt( $rates[ $key ] ) : '';
		$rows .= '<tr><td style="padding:9px 12px;border:1px solid #cfcfcf;">' . esc_html( $label ) . '</td><td style="padding:9px 12px;border:1px solid #cfcfcf;text-align:center;white-space:nowrap;font-weight:600;">' . ( '' !== $val ? celb_bidi_ltr( $val ) : '&mdash;' ) . '</td></tr>';
	}
	return '<table class="celb-commission" style="border-collapse:collapse;width:100%;margin:14px 0;font-size:14px;"><thead><tr>'
		. '<th style="padding:9px 12px;border:1px solid #cfcfcf;text-align:left;background:#f3f3f3;">' . esc_html( $lab['category'] ) . '</th>'
		. '<th style="padding:9px 12px;border:1px solid #cfcfcf;background:#f3f3f3;">' . esc_html( $lab['commission'] ) . '</th>'
		. '</tr></thead><tbody>' . $rows . celb_contract_monthly_row_html( $id, $lang ) . '</tbody></table>';
}
function celb_contract_monthly_row_html( $id, $lang = 'en' ) {
	$disp = celb_contract_monthly_display( $id, $lang );
	if ( '' === $disp ) {
		return '';
	}
	$lab = celb_contract_labels( $lang );
	return '<tr><td style="padding:9px 12px;border:1px solid #cfcfcf;">' . esc_html( $lab['monthly'] ) . '</td><td style="padding:9px 12px;border:1px solid #cfcfcf;text-align:center;white-space:nowrap;font-weight:600;">' . $disp . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

function celb_contract_rates_save( $post_id ) {
	if ( ! isset( $_POST['celb_contract_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_contract_nonce'] ), 'celb_contract_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'celb_contract' ) {
		return;
	}
	$out = array();
	if ( isset( $_POST['contract_rates'] ) && is_array( $_POST['contract_rates'] ) ) {
		foreach ( celb_contract_rate_defs() as $key => $label ) {
			if ( isset( $_POST['contract_rates'][ $key ] ) && '' !== $_POST['contract_rates'][ $key ] ) {
				$num = (float) $_POST['contract_rates'][ $key ];
				$out[ $key ] = max( 0, min( 100, $num ) );
			}
		}
	}
	update_post_meta( $post_id, '_contract_rates', $out );
	update_post_meta( $post_id, '_contract_monthly_enabled', ! empty( $_POST['contract_monthly_enabled'] ) ? '1' : '' );
	update_post_meta( $post_id, '_contract_monthly_fee', ( isset( $_POST['contract_monthly_fee'] ) && '' !== $_POST['contract_monthly_fee'] ) ? max( 0, (float) $_POST['contract_monthly_fee'] ) : '' );
	$cur_in = isset( $_POST['contract_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_currency'] ) ) : 'EGP';
	update_post_meta( $post_id, '_contract_currency', array_key_exists( $cur_in, celb_contract_currencies() ) ? $cur_in : 'EGP' );
	$mday = isset( $_POST['contract_monthly_day'] ) ? (int) $_POST['contract_monthly_day'] : 0;
	update_post_meta( $post_id, '_contract_monthly_day', ( $mday >= 1 && $mday <= 31 ) ? $mday : '' );
}
add_action( 'save_post_celb_contract', 'celb_contract_rates_save' );
/* =========================================================================
 * 18. CONTRACT SIGNING PAGE  (/sign/?cid=&token=  or  [CLEB_sign])
 * ====================================================================== */

/* ---- Route: own the /sign/ URL so no manual page is required ---- */
add_action( 'init', function () {
	add_rewrite_rule( '^sign/?$', 'index.php?celb_sign=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'celb_sign';
	return $vars;
} );
add_action( 'template_redirect', function () {
	if ( get_query_var( 'celb_sign' ) ) {
		celb_sign_route();
		exit;
	}
}, 1 );

/* ---- Shortcode fallback (if placed on a normal page) ---- */
add_shortcode( 'CLEB_sign', function () {
	$cid   = isset( $_GET['cid'] ) ? absint( $_GET['cid'] ) : 0;
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	if ( ! celb_contract_token_ok( $cid, $token ) ) {
		return '<div class="celb-sign-msg"><h1>' . esc_html__( 'Contract not found', 'celb-mgmt' ) . '</h1><p>' . esc_html__( 'This signing link is invalid or has expired.', 'celb-mgmt' ) . '</p></div>';
	}
	wp_enqueue_style( 'celb-sign', CELB_URL . 'assets/celb-sign.css', array(), CELB_VERSION );
	wp_enqueue_script( 'celb-jspdf', CELB_URL . 'assets/vendor/jspdf.umd.min.js', array(), '2.5.2', true );
	wp_enqueue_script( 'celb-h2c', CELB_URL . 'assets/vendor/html2canvas.min.js', array(), '1.4.1', true );
	wp_enqueue_script( 'celb-sign', CELB_URL . 'assets/celb-sign.js', array( 'celb-jspdf', 'celb-h2c' ), CELB_VERSION, true );
	$b = celb_sign_build( $cid );
	wp_add_inline_script( 'celb-sign', 'window.CELB_SIGN=' . wp_json_encode( $b['config'] ) . ';', 'before' );
	return $b['html'];
} );

/* ---- Standalone route renderer ---- */
function celb_sign_route() {
	$cid   = isset( $_GET['cid'] ) ? absint( $_GET['cid'] ) : 0;
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	if ( ! celb_contract_token_ok( $cid, $token ) ) {
		celb_sign_shell( '<div class="celb-sign-msg"><h1>' . esc_html__( 'Contract not found', 'celb-mgmt' ) . '</h1><p>' . esc_html__( 'This signing link is invalid or has expired. Please ask the agency to resend it.', 'celb-mgmt' ) . '</p></div>', null );
		return;
	}

	$b = celb_sign_build( $cid );
	$head = '<link rel="stylesheet" href="' . esc_url( CELB_URL . 'assets/celb-sign.css?ver=' . CELB_VERSION ) . '" />';
	$foot = '<script src="' . esc_url( CELB_URL . 'assets/vendor/jspdf.umd.min.js' ) . '"></script>'
		. '<script src="' . esc_url( CELB_URL . 'assets/vendor/html2canvas.min.js' ) . '"></script>'
		. '<script>window.CELB_SIGN=' . wp_json_encode( $b['config'] ) . ';</script>'
		. '<script src="' . esc_url( CELB_URL . 'assets/celb-sign.js?ver=' . CELB_VERSION ) . '"></script>';
	celb_sign_shell( $b['html'], $head, $foot );
}

/* ---- Standalone HTML shell ---- */
function celb_sign_shell( $body_html, $head_extra = '', $foot_extra = '' ) {
	echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" />';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
	echo '<meta name="robots" content="noindex,nofollow" />';
	echo '<title>' . esc_html__( 'Sign your contract — iLike Agency', 'celb-mgmt' ) . '</title>';
	if ( $head_extra ) {
		echo $head_extra; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo celb_custom_theme_head_html(); // phpcs:ignore
	echo '</head><body class="celb-sign-body">';
	echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $foot_extra ) {
		echo $foot_extra; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</body></html>';
}

/* ---- Build the signing document + form + JS config for a contract ---- */
function celb_contract_logo_url() {
	$s  = celb_get_settings();
	$id = (int) $s['contract_logo'];
	if ( $id ) {
		$u = wp_get_attachment_image_url( $id, 'medium' );
		if ( $u ) {
			return $u;
		}
	}
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$u = wp_get_attachment_image_url( $custom, 'medium' );
		if ( $u ) {
			return $u;
		}
	}
	return '';
}

function celb_sign_build( $cid ) {
	$tpl_id = (int) get_post_meta( $cid, '_contract_tpl', true );
	$cel_id = (int) get_post_meta( $cid, '_contract_celeb', true );
	$fields = celb_ctpl_fields( $tpl_id );
	$s      = celb_get_settings();

	// Document body: server-fill everything except the actor's own fields.
	$body = $tpl_id ? get_post_field( 'post_content', $tpl_id ) : '';
	$body = apply_filters( 'the_content', $body );
	$clang = celb_contract_lang( $cid );
	$body = strtr( $body, array(
		'{{talent_name}}'      => esc_html( $cel_id ? get_the_title( $cel_id ) : '' ),
		'{{date}}'             => esc_html( date_i18n( 'd.m.Y' ) ),
		'{{commission_table}}' => celb_contract_commission_table_html( $cid, $clang ),
		'{{monthly_fee}}'      => celb_contract_fee_short( $cid ),
		'{{currency}}'         => esc_html( celb_contract_monthly( $cid )['currency'] ),
	) );
	$mth_amt = celb_contract_monthly( $cid );
	$body = str_replace( '{{monthly_fee_amount}}', celb_bidi_ltr( celb_contract_fee_amount_str( $cid ) ), $body );
	$body = str_replace( '{{payment_day}}', celb_contract_payday_token( $cid, $clang ), $body );
	foreach ( celb_contract_rates( $cid ) as $rk => $rv ) {
		$body = str_replace( '{{rate_' . $rk . '}}', esc_html( celb_rate_fmt( $rv ) ), $body );
	}
	// Wrap remaining actor-field tokens as live spans.
	foreach ( $fields as $f ) {
		$span = '<span class="celb-tok" data-key="' . esc_attr( $f['key'] ) . '" data-label="' . esc_attr( $f['label'] ) . '">' . esc_html( $f['label'] ) . '</span>';
		$body = str_replace( '{{' . $f['key'] . '}}', $span, $body );
	}
	// Auto-append the commission table if the template never referenced it.
	if ( false === strpos( $body, 'celb-commission' ) && celb_contract_rates( $cid ) ) {
		$body .= celb_contract_commission_table_html( $cid );
	}

	$rtl = celb_is_rtl_text( wp_strip_all_tags( $body ) ) ? 'rtl' : 'ltr';

	// Signature block.
	$agency_id  = (int) $s['agency_sig'];
	$agency_url = $agency_id ? wp_get_attachment_image_url( $agency_id, 'medium' ) : '';
	$talent_nm  = $cel_id ? get_the_title( $cel_id ) : '';

	$doc  = '<div class="celb-sign-doc" dir="' . esc_attr( $rtl ) . '">';
	$doc .= $body;
	$doc .= '<div class="celb-sign-block" dir="ltr">';
	$doc .= '<div class="celb-sign-col"><div class="celb-sign-line celb-actor-sig"></div><div class="celb-sign-name celb-actor-name" data-label="' . esc_attr__( 'Actor', 'celb-mgmt' ) . '">' . esc_html( $talent_nm ) . '</div><div class="celb-sign-role">' . esc_html__( 'Talent', 'celb-mgmt' ) . '</div></div>';
	$doc .= '<div class="celb-sign-col"><div class="celb-sign-line">' . ( $agency_url ? '<img src="' . esc_url( $agency_url ) . '" alt="" crossorigin="anonymous" />' : '' ) . '</div><div class="celb-sign-name">iLike Agency</div><div class="celb-sign-role">' . esc_html__( 'Agency Representative', 'celb-mgmt' ) . '</div></div>';
	$doc .= '</div></div>';

	// Form.
	$form  = '<div class="celb-sign-form"><h2>' . esc_html__( 'Your details', 'celb-mgmt' ) . '</h2>';
	foreach ( $fields as $f ) {
		$type = in_array( $f['type'], array( 'text', 'email', 'number', 'date', 'tel' ), true ) ? $f['type'] : 'text';
		$req  = ! empty( $f['required'] );
		$form .= '<div class="celb-field"><label for="cf_' . esc_attr( $f['key'] ) . '">' . esc_html( $f['label'] ) . ( $req ? ' <span class="req">*</span>' : '' ) . '</label>';
		$form .= '<input type="' . esc_attr( $type ) . '" id="cf_' . esc_attr( $f['key'] ) . '" data-fieldkey="' . esc_attr( $f['key'] ) . '"' . ( $req ? ' required' : '' ) . ' /></div>';
	}
	$form .= '<div class="celb-sigpad-wrap"><div class="celb-sigpad-label">' . esc_html__( 'Signature', 'celb-mgmt' ) . ' <span class="req">*</span></div>';
	$form .= '<canvas id="celb-sigpad"></canvas><br /><button type="button" class="celb-sig-clear">' . esc_html__( 'Clear', 'celb-mgmt' ) . '</button></div>';
	$form .= '<label class="celb-consent"><input type="checkbox" class="celb-sign-consent" /> <span>' . esc_html__( 'I confirm that the information above is correct and that this drawn signature is my legally binding signature on this contract.', 'celb-mgmt' ) . '</span></label>';
	$form .= '<button type="button" class="celb-sign-submit">' . esc_html__( 'Sign & Submit Contract', 'celb-mgmt' ) . '</button>';
	$form .= '<div class="celb-sign-status"></div>';
	$form .= '<p class="celb-sign-legal">' . esc_html__( 'Your signature, the date, and your IP address are recorded with this contract for verification.', 'celb-mgmt' ) . '</p>';
	$form .= '</div>';

	$done = '<div class="celb-sign-done"><h2>' . esc_html__( 'Contract signed', 'celb-mgmt' ) . '</h2><p>' . esc_html__( 'Thank you. A signed copy has been emailed to you for your records.', 'celb-mgmt' ) . '</p></div>';

	$logo_url = celb_contract_logo_url();
	$logo_html = $logo_url ? '<img class="celb-sign-logo" src="' . esc_url( $logo_url ) . '" alt="" />' : '';
	$html = '<div class="celb-sign-wrap"><div class="celb-sign-head">' . $logo_html . '<h1>' . esc_html__( 'Review & Sign Your Contract', 'celb-mgmt' ) . '</h1><p>' . esc_html__( 'Fill in the highlighted details, sign at the bottom, then submit.', 'celb-mgmt' ) . '</p></div>' . $doc . $form . $done . '</div>';

	$cfg_fields = array();
	foreach ( $fields as $f ) {
		$cfg_fields[] = array( 'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'], 'required' => ! empty( $f['required'] ) ? 1 : 0 );
	}
	$config = array(
		'restUrl' => esc_url_raw( rest_url( 'celb/v1/contract-sign' ) ),
		'cid'     => $cid,
		'token'   => get_post_meta( $cid, '_contract_token', true ),
		'fields'  => $cfg_fields,
		'logo'    => $logo_url,
		'title'   => get_the_title( $cid ),
	);

	return array( 'html' => $html, 'config' => $config );
}

/* ---- REST: receive the signed submission ---- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'celb/v1', '/contract-sign', array(
		'methods'             => 'POST',
		'callback'            => 'celb_rest_contract_sign',
		'permission_callback' => '__return_true', // token-authenticated below
	) );
} );

function celb_rest_contract_sign( $req ) {
	$p     = $req->get_json_params();
	$cid   = isset( $p['cid'] ) ? absint( $p['cid'] ) : 0;
	$token = isset( $p['token'] ) ? (string) $p['token'] : '';

	if ( ! celb_contract_token_ok( $cid, $token ) ) {
		return new WP_Error( 'celb_bad_token', __( 'Invalid signing link.', 'celb-mgmt' ), array( 'status' => 403 ) );
	}
	if ( 'signed' === get_post_meta( $cid, '_contract_status', true ) ) {
		return new WP_Error( 'celb_signed', __( 'This contract has already been signed.', 'celb-mgmt' ), array( 'status' => 409 ) );
	}

	$tpl_id = (int) get_post_meta( $cid, '_contract_tpl', true );
	$fields = celb_ctpl_fields( $tpl_id );
	$in     = isset( $p['data'] ) && is_array( $p['data'] ) ? $p['data'] : array();

	$clean = array();
	$email = '';
	foreach ( $fields as $f ) {
		$k = $f['key'];
		$v = isset( $in[ $k ] ) ? $in[ $k ] : '';
		switch ( $f['type'] ) {
			case 'email':
				$v = sanitize_email( $v );
				break;
			case 'number':
				$v = preg_replace( '/[^0-9.\-]/', '', (string) $v );
				break;
			default:
				$v = sanitize_text_field( $v );
		}
		if ( $f['required'] && '' === $v ) {
			return new WP_Error( 'celb_missing', sprintf( __( 'Please complete: %s', 'celb-mgmt' ), $f['label'] ), array( 'status' => 400 ) );
		}
		$clean[ $k ] = $v;
		if ( '' === $email && ( 'email' === $k || 'email' === $f['type'] ) && is_email( $v ) ) {
			$email = $v;
		}
	}

	$dir = celb_contract_dir();

	// Save the signed PDF (validated).
	$pdf_raw = celb_decode_data_uri( isset( $p['pdf'] ) ? $p['pdf'] : '', 'application/pdf' );
	if ( '' === $pdf_raw || 0 !== strpos( $pdf_raw, '%PDF' ) ) {
		return new WP_Error( 'celb_pdf', __( 'The signed PDF could not be read. Please try again.', 'celb-mgmt' ), array( 'status' => 400 ) );
	}
	if ( strlen( $pdf_raw ) > 15 * 1024 * 1024 ) {
		return new WP_Error( 'celb_pdf_big', __( 'The signed PDF is too large.', 'celb-mgmt' ), array( 'status' => 400 ) );
	}
	$pdf_path = trailingslashit( $dir ) . 'contract-' . $cid . '-' . time() . '.pdf';
	if ( false === file_put_contents( $pdf_path, $pdf_raw ) ) { // phpcs:ignore
		return new WP_Error( 'celb_save', __( 'Could not save the contract on the server.', 'celb-mgmt' ), array( 'status' => 500 ) );
	}

	// Save the actor signature PNG (best-effort, for the record).
	$sig_raw = celb_decode_data_uri( isset( $p['actor_sig'] ) ? $p['actor_sig'] : '', 'image/png' );
	if ( '' !== $sig_raw && strlen( $sig_raw ) < 4 * 1024 * 1024 ) {
		$sig_path = trailingslashit( $dir ) . 'sig-' . $cid . '-' . time() . '.png';
		if ( false !== file_put_contents( $sig_path, $sig_raw ) ) { // phpcs:ignore
			update_post_meta( $cid, '_contract_actor_sig_path', $sig_path );
		}
	}

	update_post_meta( $cid, '_contract_data', $clean );
	update_post_meta( $cid, '_contract_email', $email );
	update_post_meta( $cid, '_contract_pdf_path', $pdf_path );
	update_post_meta( $cid, '_contract_status', 'signed' );
	update_post_meta( $cid, '_contract_signed_at', time() );
	update_post_meta( $cid, '_contract_signed_ip', celb_client_ip() );

	// Email the signed PDF to the actor.
	$sent = false;
	if ( $email ) {
		$s       = celb_get_settings();
		$from    = ! empty( $s['contract_from'] ) ? $s['contract_from'] : get_option( 'admin_email' );
		$headers = array(
			'From: iLike Agency <' . $from . '>',
			'Content-Type: text/plain; charset=UTF-8',
		);
		$sent = wp_mail( $email, $s['contract_subject'], $s['contract_body'], $headers, array( $pdf_path ) );
	}

	return rest_ensure_response( array( 'ok' => true, 'emailed' => (bool) $sent ) );
}

/* ---- Helpers: decode a base64 data URI; client IP ---- */
function celb_decode_data_uri( $uri, $expect_mime = '' ) {
	$uri = (string) $uri;
	if ( false === strpos( $uri, 'base64,' ) ) {
		return '';
	}
	if ( '' !== $expect_mime && false === strpos( substr( $uri, 0, 60 ), $expect_mime ) ) {
		return '';
	}
	$b64 = substr( $uri, strpos( $uri, 'base64,' ) + 7 );
	$raw = base64_decode( $b64, true );
	return false === $raw ? '' : $raw;
}

function celb_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/* =========================================================================
 * 19. CONTRACTS IN THE WEB APP  (admin = all, talent = own)
 * ====================================================================== */
function celb_contract_app_data( $id, $is_mgr ) {
	$cel       = (int) get_post_meta( $id, '_contract_celeb', true );
	$tpl       = (int) get_post_meta( $id, '_contract_tpl', true );
	$status    = get_post_meta( $id, '_contract_status', true );
	$status    = $status ? $status : 'pending';
	$signed_at = (int) get_post_meta( $id, '_contract_signed_at', true );
	$path      = get_post_meta( $id, '_contract_pdf_path', true );
	$has_pdf   = ( $path && file_exists( $path ) );

	$download = '';
	if ( $has_pdf ) {
		$args = array( 'action' => 'celb_contract_download', 'contract' => (int) $id );
		if ( $is_mgr ) {
			$args['_wpnonce'] = wp_create_nonce( 'celb_dl_' . $id );
		}
		// add_query_arg keeps raw "&" (wp_nonce_url would HTML-escape it, which
		// breaks when the app passes the URL straight to window.open).
		$download = add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}
	$sign = ( 'signed' !== $status ) ? celb_contract_sign_link( $id ) : '';

	return array(
		'id'         => (int) $id,
		'title'      => get_the_title( $id ),
		'celeb'      => $cel,
		'celeb_name' => $cel ? get_the_title( $cel ) : '',
		'template'   => $tpl ? get_the_title( $tpl ) : '',
		'status'     => $status,
		'signed_at'  => $signed_at ? date_i18n( 'Y-m-d', $signed_at ) : '',
		'download'   => $download,
		'sign'       => $sign,
	);
}

function celb_rest_contract_list( $req ) {
	$is_mgr = current_user_can( 'edit_posts' );
	$args   = array(
		'post_type'        => 'celb_contract',
		'post_status'      => array( 'publish', 'draft' ),
		'numberposts'      => 300,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => true,
	);

	if ( ! $is_mgr ) {
		$mine = celb_user_celeb_id();
		if ( ! $mine ) {
			return rest_ensure_response( array() );
		}
		$args['meta_query'] = array( array( 'key' => '_contract_celeb', 'value' => $mine ) );
	} else {
		$celeb  = absint( $req->get_param( 'celeb' ) );
		$status = sanitize_key( (string) $req->get_param( 'status' ) );
		$mq     = array();
		if ( $celeb ) {
			$mq[] = array( 'key' => '_contract_celeb', 'value' => $celeb );
		}
		if ( 'pending' === $status || 'signed' === $status ) {
			$mq[] = array( 'key' => '_contract_status', 'value' => $status );
		}
		if ( $mq ) {
			if ( count( $mq ) > 1 ) {
				$mq['relation'] = 'AND';
			}
			$args['meta_query'] = $mq;
		}
	}

	$out = array();
	foreach ( get_posts( $args ) as $p ) {
		$out[] = celb_contract_app_data( $p->ID, $is_mgr );
	}
	return rest_ensure_response( $out );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'celb/v1', '/contracts', array( array(
		'methods'             => 'GET',
		'callback'            => 'celb_rest_contract_list',
		'permission_callback' => 'celb_rest_can_access',
	) ) );
} );

/* =========================================================================
 * 20. RATE CARD  (private, password-gated quotation builder)
 *     Own CPT (Celebrities → Rate Cards). Clean URL: /rate/{name}
 * ====================================================================== */

define( 'CELB_RATE_CPT', 'celb_ratecard' );

function celb_rate_platforms() {
	return array(
		'instagram' => array( 'label' => 'Instagram', 'unit' => 'followers' ),
		'tiktok'    => array( 'label' => 'TikTok', 'unit' => 'followers' ),
		'facebook'  => array( 'label' => 'Facebook', 'unit' => 'followers' ),
		'youtube'   => array( 'label' => 'YouTube', 'unit' => 'subscribers' ),
		'snapchat'  => array( 'label' => 'Snapchat', 'unit' => 'followers' ),
	);
}

/* Default per-card configuration (merged over saved meta). */
function celb_rate_defaults() {
	return array(
		'currency'    => 'EGP',
		'intro'       => 'Build an estimate by selecting the services and options you need. This is an indicative quote — final terms are confirmed by management.',
		'platforms'   => array_keys( celb_rate_platforms() ),
		'titles'      => array(
			'social'  => 'Audience',
			'usage'   => 'Usage Rights',
			'excl'    => 'Exclusivity',
			'rush'    => 'Rush Booking',
			'travel'  => 'Travel Policy',
			'notes'   => 'Notes',
			'terms'   => 'Terms & Conditions',
			'summary' => 'Your Selection',
		),
		'show'        => array(
			'social' => 1, 'usage' => 1, 'excl' => 1, 'rush' => 1, 'travel' => 1, 'notes' => 0, 'terms' => 1,
		),
		'usage'       => array(
			array( 'label' => 'Organic only', 'desc' => 'Content stays on the creator’s own channels.', 'type' => 'none', 'value' => 0 ),
			array( 'label' => '30 Days Paid Usage', 'desc' => 'Boosting / whitelisting for 30 days.', 'type' => 'percent', 'value' => 20 ),
			array( 'label' => '90 Days Paid Usage', 'desc' => 'Boosting / whitelisting for 90 days.', 'type' => 'percent', 'value' => 40 ),
		),
		'excl'        => array(
			array( 'label' => 'Category Exclusivity — 3 Months', 'desc' => 'No competing brands for 3 months.', 'type' => 'percent', 'value' => 25 ),
		),
		'rush'        => array( 'label' => 'Rush Booking', 'desc' => 'Delivery within 72 hours.', 'type' => 'percent', 'value' => 20 ),
		'travel_desc' => 'Travel outside Greater Cairo is billed at cost (flights, transport, accommodation) and agreed in advance.',
		'travel_fee'  => 0,
		'notes'       => '',
		'terms'       => 'All prices are exclusive of taxes. A 50% deposit confirms the booking. Content concepts are approved by the talent before publishing.',
	);
}

function celb_rate_money( $n, $currency = 'EGP' ) {
	$currency = trim( (string) $currency );
	$num      = number_format( (float) $n, 0, '.', ',' );
	return $currency ? $num . ' ' . $currency : $num;
}

/* ---- Register the Rate Card CPT under the Celebrities menu ---- */
function celb_register_ratecard_cpt() {
	register_post_type( CELB_RATE_CPT, array(
		'labels' => array(
			'name'          => __( 'Rate Cards', 'celb-mgmt' ),
			'singular_name' => __( 'Rate Card', 'celb-mgmt' ),
			'add_new'       => __( 'New Rate Card', 'celb-mgmt' ),
			'add_new_item'  => __( 'Add Rate Card', 'celb-mgmt' ),
			'edit_item'     => __( 'Edit Rate Card', 'celb-mgmt' ),
			'new_item'      => __( 'New Rate Card', 'celb-mgmt' ),
			'view_item'     => __( 'View Rate Card', 'celb-mgmt' ),
			'search_items'  => __( 'Search Rate Cards', 'celb-mgmt' ),
			'all_items'     => __( 'Rate Cards', 'celb-mgmt' ),
			'menu_name'     => __( 'Rate Cards', 'celb-mgmt' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=' . CELB_CPT,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'menu_icon'       => 'dashicons-money-alt',
	) );
}
add_action( 'init', 'celb_register_ratecard_cpt' );

/* ---- Slug map (slug => card_id), rebuilt when cards change ---- */
function celb_rate_build_slug_map() {
	$map   = array();
	$cards = get_posts( array(
		'post_type'        => CELB_RATE_CPT,
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	foreach ( $cards as $card_id ) {
		$celeb = (int) get_post_meta( $card_id, '_rate_celeb', true );
		$base  = $celeb ? celb_smartlink_slug( get_the_title( $celeb ) ) : '';
		if ( '' === $base ) {
			$base = celb_smartlink_slug( get_the_title( $card_id ) );
		}
		if ( '' === $base ) {
			$base = 'talent' . $card_id;
		}
		$slug = $base;
		$i    = 2;
		while ( isset( $map[ $slug ] ) ) {
			$slug = $base . $i;
			$i++;
		}
		$map[ $slug ] = $card_id;
		update_post_meta( $card_id, '_rate_slug', $slug );
	}
	update_option( 'celb_rate_slugs', $map, false );
	return $map;
}
function celb_rate_get_slug_map() {
	$map = get_option( 'celb_rate_slugs', null );
	if ( ! is_array( $map ) ) {
		$map = celb_rate_build_slug_map();
	}
	return $map;
}
function celb_rate_slug_lookup( $slug ) {
	$slug = (string) $slug;
	if ( ! preg_match( '/^[a-z0-9]+$/', $slug ) ) {
		return 0;
	}
	$map = celb_rate_get_slug_map();
	if ( isset( $map[ $slug ] ) ) {
		return (int) $map[ $slug ];
	}
	$ids = get_posts( array(
		'post_type'        => CELB_RATE_CPT,
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'meta_key'         => '_rate_slug',
		'meta_value'       => $slug,
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/* The rate card assigned to a celebrity (0 if none / disabled). */
function celb_rate_card_for_celeb( $celeb_id ) {
	$ids = get_posts( array(
		'post_type'        => CELB_RATE_CPT,
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'meta_key'         => '_rate_celeb',
		'meta_value'       => (int) $celeb_id,
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	return $ids ? (int) $ids[0] : 0;
}

function celb_rate_card_slug( $card_id ) {
	$slug = get_post_meta( $card_id, '_rate_slug', true );
	if ( ! $slug ) {
		$celeb = (int) get_post_meta( $card_id, '_rate_celeb', true );
		$slug  = $celeb ? celb_smartlink_slug( get_the_title( $celeb ) ) : '';
	}
	return $slug ? $slug : ( 'talent' . $card_id );
}
function celb_rate_card_url( $card_id ) {
	return home_url( '/rate/' . celb_rate_card_slug( $card_id ) );
}

/* Keep the slug map in sync when cards or celebrity names change. */
function celb_rate_resync( $post_id = 0 ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
		return;
	}
	$type = $post_id ? get_post_type( $post_id ) : '';
	if ( $post_id && CELB_RATE_CPT !== $type && CELB_CPT !== $type ) {
		return;
	}
	celb_rate_build_slug_map();
	flush_rewrite_rules( false );
}
add_action( 'save_post_' . CELB_RATE_CPT, 'celb_rate_resync' );
add_action( 'trashed_post', 'celb_rate_resync' );
add_action( 'untrashed_post', 'celb_rate_resync' );
add_action( 'before_delete_post', 'celb_rate_resync' );

/* ---- Routing: /rate/{name}  (+ /rate/?cid= backward compat) ---- */
add_action( 'init', function () {
	add_rewrite_rule( '^rate/([^/]+)/?$', 'index.php?celb_rate_slug=$matches[1]', 'top' );
	add_rewrite_rule( '^rate/?$', 'index.php?celb_rate=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'celb_rate_slug';
	$vars[] = 'celb_rate';
	return $vars;
} );
/* Robust catch in case host caches rewrite rules. */
add_action( 'parse_request', function ( $wp ) {
	$path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
	if ( 0 === strpos( $path, 'rate/' ) ) {
		$seg = substr( $path, 5 );
		if ( '' !== $seg && false === strpos( $seg, '/' ) ) {
			$wp->query_vars = array( 'celb_rate_slug' => $seg );
		}
	}
} );
add_action( 'template_redirect', function () {
	if ( get_query_var( 'celb_rate_slug' ) || get_query_var( 'celb_rate' ) ) {
		celb_rate_route();
		exit;
	}
}, 1 );

/* ---- Per-card data accessors ---- */
function celb_rate_social_get( $card_id ) {
	$s = get_post_meta( $card_id, '_rate_social', true );
	return is_array( $s ) ? $s : array();
}
function celb_rate_sections_get( $card_id ) {
	$s = get_post_meta( $card_id, '_rate_sections', true );
	return is_array( $s ) ? $s : array();
}
function celb_rate_get( $card_id ) {
	$d   = celb_rate_defaults();
	$cfg = get_post_meta( $card_id, '_rate_config', true );
	$cfg = wp_parse_args( is_array( $cfg ) ? $cfg : array(), $d );
	$cfg['titles'] = wp_parse_args( isset( $cfg['titles'] ) && is_array( $cfg['titles'] ) ? $cfg['titles'] : array(), $d['titles'] );
	$cfg['show']   = wp_parse_args( isset( $cfg['show'] ) && is_array( $cfg['show'] ) ? $cfg['show'] : array(), $d['show'] );
	foreach ( array( 'platforms', 'usage', 'excl' ) as $k ) {
		if ( ! is_array( $cfg[ $k ] ) ) {
			$cfg[ $k ] = $d[ $k ];
		}
	}
	if ( ! is_array( $cfg['rush'] ) ) {
		$cfg['rush'] = $d['rush'];
	}
	$cfg['celeb']    = (int) get_post_meta( $card_id, '_rate_celeb', true );
	$cfg['enabled']  = get_post_meta( $card_id, '_rate_enabled', true ) === '1';
	$cfg['pw']       = (string) get_post_meta( $card_id, '_rate_pw', true );
	$cfg['social']   = celb_rate_social_get( $card_id );
	$cfg['sections'] = celb_rate_sections_get( $card_id );
	return $cfg;
}

function celb_rate_celeb_photo( $celeb_id ) {
	$id = (int) get_post_meta( $celeb_id, '_celb_profile', true );
	if ( $id ) {
		$u = wp_get_attachment_image_url( $id, 'medium' );
		if ( $u ) {
			return $u;
		}
	}
	return '';
}

/* ---- Unlock cookie (per card) ---- */
function celb_rate_gate_token( $card_id, $exp ) {
	return hash_hmac( 'sha256', $card_id . '|' . $exp, wp_salt( 'auth' ) );
}
function celb_rate_is_unlocked( $card_id ) {
	$c = isset( $_COOKIE[ 'celb_rate_' . $card_id ] ) ? wp_unslash( $_COOKIE[ 'celb_rate_' . $card_id ] ) : '';
	if ( ! $c || false === strpos( $c, ':' ) ) {
		return false;
	}
	list( $exp, $tok ) = explode( ':', $c, 2 );
	if ( (int) $exp < time() ) {
		return false;
	}
	return hash_equals( celb_rate_gate_token( $card_id, (int) $exp ), (string) $tok );
}
function celb_rate_set_cookie( $card_id ) {
	$exp = time() + 12 * HOUR_IN_SECONDS;
	$val = $exp . ':' . celb_rate_gate_token( $card_id, $exp );
	setcookie( 'celb_rate_' . $card_id, $val, $exp, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN ? COOKIE_DOMAIN : '', is_ssl(), true );
}

/* ---- Route ---- */
function celb_rate_route() {
	$slug = get_query_var( 'celb_rate_slug' );
	$card = 0;
	if ( $slug ) {
		$card = celb_rate_slug_lookup( $slug );
	} else {
		$cid = isset( $_GET['cid'] ) ? absint( $_GET['cid'] ) : 0;
		if ( $cid ) {
			$card = celb_rate_card_for_celeb( $cid );
		}
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	$cfg = ( $card && get_post_type( $card ) === CELB_RATE_CPT ) ? celb_rate_get( $card ) : null;
	$ok  = $cfg && $cfg['enabled'] && '' !== $cfg['pw'];
	$meta = ( $cfg && ! empty( $cfg['celeb'] ) ) ? celb_rate_meta( $cfg['celeb'] ) : null;

	if ( ! $ok ) {
		celb_rate_shell( celb_rate_notavail_html(), '', $meta );
		return;
	}

	$unlocked = celb_rate_is_unlocked( $card );
	$error    = false;
	if ( ! $unlocked && isset( $_POST['celb_rate_pw'] ) ) {
		$try = sanitize_text_field( wp_unslash( $_POST['celb_rate_pw'] ) );
		if ( hash_equals( trim( $cfg['pw'] ), trim( $try ) ) ) {
			celb_rate_set_cookie( $card );
			$unlocked = true;
		} else {
			$error = true;
		}
	}

	if ( ! $unlocked ) {
		celb_rate_shell( celb_rate_gate_html( $card, $cfg, $error ), '', $meta );
		return;
	}

	$b    = celb_rate_build( $card, $cfg );
	$foot = '<script>window.CELB_RATE=' . wp_json_encode( $b['config'] ) . ';</script>'
		. '<script src="' . esc_url( CELB_URL . 'assets/celb-rate.js?ver=' . CELB_VERSION ) . '"></script>';
	celb_rate_shell( $b['html'], $foot, $meta );
}

function celb_rate_logo() {
	$s   = celb_get_settings();
	$url = ! empty( $s['brand_logo_url'] ) ? $s['brand_logo_url'] : celb_contract_logo_url();
	return $url ? '<img class="rc-brand-logo" src="' . esc_url( $url ) . '" alt="" />' : '';
}

function celb_rate_avatar( $celeb_id ) {
	$photo = $celeb_id ? celb_rate_celeb_photo( $celeb_id ) : '';
	if ( $photo ) {
		return '<span class="rc-avatar" style="background-image:url(' . esc_url( $photo ) . ')"></span>';
	}
	$name = $celeb_id ? get_the_title( $celeb_id ) : '';
	return '<span class="rc-avatar rc-avatar-mono">' . esc_html( celb_monogram( $name ) ) . '</span>';
}

function celb_rate_notavail_html() {
	return '<div class="rc-gate"><div class="rc-gate-card">' . celb_rate_logo()
		. '<h1 class="rc-gate-title">' . esc_html__( 'Not available', 'celb-mgmt' ) . '</h1>'
		. '<p class="rc-gate-sub">' . esc_html__( 'This rate card is not available. Please contact the agency.', 'celb-mgmt' ) . '</p></div></div>';
}

function celb_rate_gate_html( $card_id, $cfg, $error ) {
	$slug   = celb_rate_card_slug( $card_id );
	$action = esc_url( home_url( '/rate/' . $slug ) );
	$name   = $cfg['celeb'] ? get_the_title( $cfg['celeb'] ) : get_the_title( $card_id );
	ob_start(); ?>
	<div class="rc-gate"><div class="rc-gate-card">
		<?php echo celb_rate_logo(); // phpcs:ignore ?>
		<?php echo celb_rate_avatar( $cfg['celeb'] ); // phpcs:ignore ?>
		<span class="rc-gate-eyebrow"><?php esc_html_e( 'Private Rate Card', 'celb-mgmt' ); ?></span>
		<h1 class="rc-gate-title"><?php echo esc_html( $name ); ?></h1>
		<p class="rc-gate-sub"><?php esc_html_e( 'Enter the password shared with you to view pricing.', 'celb-mgmt' ); ?></p>
		<form method="post" action="<?php echo $action; // phpcs:ignore ?>">
			<input class="rc-gate-input" type="password" name="celb_rate_pw" placeholder="<?php esc_attr_e( 'Password', 'celb-mgmt' ); ?>" autofocus autocomplete="off" />
			<button class="rc-gate-btn" type="submit"><?php esc_html_e( 'View Rate Card', 'celb-mgmt' ); ?></button>
			<?php if ( $error ) : ?><p class="rc-gate-error"><?php esc_html_e( 'Incorrect password. Please try again.', 'celb-mgmt' ); ?></p><?php endif; ?>
		</form>
		<div class="rc-gate-foot"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
	</div></div>
	<?php
	return ob_get_clean();
}

/* ---- Share metadata (title / description / OG image) for a rate page ---- */
function celb_rate_meta( $celeb_id ) {
	$celeb_id = (int) $celeb_id;
	$agency   = get_bloginfo( 'name' );
	$name     = $celeb_id ? get_the_title( $celeb_id ) : '';
	$title    = ( $name ? $name . ' ' : '' ) . __( 'Rate Card', 'celb-mgmt' ) . ' | ' . $agency;
	$desc     = '';
	$image    = '';
	if ( $celeb_id ) {
		$desc = celb_first_paragraph( celb_get_bio( $celeb_id ) );
		$pid  = (int) get_post_meta( $celeb_id, '_celb_profile', true );
		if ( $pid ) {
			$image = wp_get_attachment_image_url( $pid, 'large' );
		}
		if ( ! $image ) {
			$image = celb_rate_celeb_photo( $celeb_id );
		}
	}
	return array(
		'title'       => $title,
		'description' => $desc ? $desc : '',
		'image'       => $image ? $image : '',
	);
}

/* ---- Standalone shell — always loads brand fonts + rate CSS ---- */
function celb_rate_shell( $body_html, $foot_extra = '', $meta = null ) {
	$fonts = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,500;1,6..96,400&family=Space+Grotesk:wght@300;400;500&family=Space+Mono:wght@400;700&display=swap">';
	$css   = '<link rel="stylesheet" href="' . esc_url( CELB_URL . 'assets/celb-rate.css?ver=' . CELB_VERSION ) . '" />';
	$title = ( $meta && ! empty( $meta['title'] ) ) ? $meta['title'] : ( __( 'Rate Card', 'celb-mgmt' ) . ' | ' . get_bloginfo( 'name' ) );
	echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" />';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
	echo '<meta name="robots" content="noindex,nofollow" />';
	echo '<title>' . esc_html( $title ) . '</title>';
	echo '<meta property="og:type" content="website" />';
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />';
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />';
	echo '<meta name="twitter:card" content="summary_large_image" />';
	if ( $meta && ! empty( $meta['description'] ) ) {
		echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '" />';
		echo '<meta property="og:description" content="' . esc_attr( $meta['description'] ) . '" />';
		echo '<meta name="twitter:description" content="' . esc_attr( $meta['description'] ) . '" />';
	}
	if ( $meta && ! empty( $meta['image'] ) ) {
		echo '<meta property="og:image" content="' . esc_url( $meta['image'] ) . '" />';
		echo '<meta name="twitter:image" content="' . esc_url( $meta['image'] ) . '" />';
	}
	echo $fonts . $css; // phpcs:ignore
	echo celb_custom_theme_head_html(); // phpcs:ignore
	echo '</head><body class="celb-rate-body">';
	echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $foot_extra ) {
		echo $foot_extra; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</body></html>';
}

/* ---- Build the interactive card ---- */
function celb_rate_platlabel( $platforms ) {
	$all = celb_rate_platforms();
	if ( empty( $platforms ) || count( $platforms ) >= count( $all ) ) {
		return __( 'All platforms', 'celb-mgmt' );
	}
	$labels = array();
	foreach ( $platforms as $p ) {
		if ( isset( $all[ $p ] ) ) {
			$labels[] = $all[ $p ]['label'];
		}
	}
	return $labels ? implode( ', ', $labels ) : __( 'All platforms', 'celb-mgmt' );
}
function celb_rate_adj_note( $type, $value, $currency ) {
	if ( 'percent' === $type && $value ) {
		return '+' . rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' ) . '%';
	}
	if ( 'fixed' === $type && $value ) {
		return '+' . celb_rate_money( $value, $currency );
	}
	return '';
}
function celb_rate_opt_radio( $group, $label, $desc, $type, $value, $currency, $checked ) {
	$note = celb_rate_adj_note( $type, $value, $currency );
	$h  = '<label class="rc-opt"><input type="radio" name="' . esc_attr( $group ) . '" data-type="' . esc_attr( $type ) . '" data-value="' . esc_attr( $value ) . '" data-label="' . esc_attr( $label ) . '"' . ( $checked ? ' checked' : '' ) . ' />';
	$h .= '<span class="rc-opt-main"><span class="rc-opt-label">' . esc_html( $label ) . '</span>';
	if ( '' !== $desc ) {
		$h .= '<span class="rc-opt-desc">' . esc_html( $desc ) . '</span>';
	}
	$h .= '</span>';
	if ( $note ) {
		$h .= '<span class="rc-opt-adj">' . esc_html( $note ) . '</span>';
	}
	$h .= '</label>';
	return $h;
}

function celb_rate_build( $card_id, $cfg ) {
	$g        = celb_get_settings();
	$cur      = $cfg['currency'];
	$name     = $cfg['celeb'] ? get_the_title( $cfg['celeb'] ) : get_the_title( $card_id );
	$platsAll = celb_rate_platforms();

	// USD is offered as a front-end toggle (default stays EGP). Conversion is done
	// client-side from the configured rate + markup.
	$usd_offer  = ! empty( $g['rc_usd_enabled'] );
	$usd_rate   = (float) $g['rc_usd_rate'] > 0 ? (float) $g['rc_usd_rate'] : 1;
	$usd_markup = (float) $g['rc_usd_markup'];

	ob_start();
	echo '<div id="celb-rate"><div class="rc-wrap">';

	/* Header */
	echo '<div class="rc-head">';
	echo celb_rate_logo(); // phpcs:ignore
	echo '<div class="rc-head-id">' . celb_rate_avatar( $cfg['celeb'] ); // phpcs:ignore
	echo '<div><span class="rc-eyebrow">' . esc_html__( 'Rate Card', 'celb-mgmt' ) . '</span>';
	echo '<h1 class="rc-title">' . esc_html( $name ) . '</h1></div></div>';
	if ( '' !== trim( (string) $cfg['intro'] ) ) {
		echo '<p class="rc-intro">' . esc_html( $cfg['intro'] ) . '</p>';
	}
	$has_profile = ( $cfg['celeb'] && 'publish' === get_post_status( $cfg['celeb'] ) );
	if ( $has_profile || $usd_offer ) {
		echo '<div class="rc-head-actions">';
		if ( $has_profile ) {
			echo '<a class="rc-viewprofile" href="' . esc_url( celb_smartlink_url( $cfg['celeb'] ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View My Profile', 'celb-mgmt' ) . '</a>';
		}
		if ( $usd_offer ) {
			echo '<div class="rc-curswitch" role="group" aria-label="' . esc_attr__( 'Currency', 'celb-mgmt' ) . '">';
			echo '<button type="button" class="rc-cur is-active" data-cur="base">' . esc_html( $cur ) . '</button>';
			echo '<button type="button" class="rc-cur" data-cur="usd">USD</button>';
			echo '</div>';
		}
		echo '</div>';
	}
	echo '</div>';

	/* Audience */
	if ( ! empty( $cfg['show']['social'] ) && ! empty( $cfg['social'] ) ) {
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['social'] ) . '</h2><div class="rc-social">';
		foreach ( $platsAll as $key => $meta ) {
			if ( empty( $cfg['social'][ $key ] ) ) {
				continue;
			}
			$h = isset( $cfg['social'][ $key ]['handle'] ) ? $cfg['social'][ $key ]['handle'] : '';
			$c = isset( $cfg['social'][ $key ]['count'] ) ? $cfg['social'][ $key ]['count'] : '';
			echo '<div class="rc-social-item"><div class="rc-social-plat">' . esc_html( $meta['label'] ) . '</div>';
			if ( '' !== $c ) {
				echo '<div class="rc-social-count">' . esc_html( $c ) . '</div>';
			}
			if ( '' !== $h ) {
				echo '<div class="rc-social-handle">' . esc_html( $h ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div></section>';
	}

	/* Custom pricing sections (unlimited) */
	foreach ( $cfg['sections'] as $section ) {
		$stitle = isset( $section['title'] ) ? $section['title'] : '';
		$items  = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
		if ( empty( $items ) ) {
			continue;
		}
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $stitle ) . '</h2><div class="rc-services">';
		foreach ( $items as $svc ) {
			$svc = wp_parse_args( $svc, array( 'name' => '', 'desc' => '', 'included' => 1, 'base' => 0, 'addl' => 0, 'platforms' => array() ) );
			if ( '' === $svc['name'] ) {
				continue;
			}
			$platlabel = celb_rate_platlabel( is_array( $svc['platforms'] ) ? $svc['platforms'] : array() );
			echo '<div class="rc-service" data-name="' . esc_attr( $svc['name'] ) . '" data-base="' . esc_attr( $svc['base'] ) . '" data-addl="' . esc_attr( $svc['addl'] ) . '" data-incl="' . esc_attr( $svc['included'] ) . '" data-extra="0" data-platlabel="' . esc_attr( $platlabel ) . '">';
			echo '<div class="rc-service-top"><div class="rc-service-info">';
			echo '<div class="rc-service-name">' . esc_html( $svc['name'] ) . '</div>';
			if ( '' !== $svc['desc'] ) {
				echo '<p class="rc-service-desc">' . esc_html( $svc['desc'] ) . '</p>';
			}
			if ( (int) $svc['included'] > 0 ) {
				$meta_line = sprintf( _n( 'Includes %d deliverable', 'Includes %d deliverables', (int) $svc['included'], 'celb-mgmt' ), (int) $svc['included'] );
				echo '<div class="rc-service-meta">' . esc_html( $meta_line ) . '</div>';
			}
			echo '<div class="rc-plats"><span class="rc-plat">' . esc_html( $platlabel ) . '</span></div>';
			echo '</div><div class="rc-service-right">';
			echo '<div class="rc-price-base">' . esc_html( celb_rate_money( $svc['base'], $cur ) ) . '</div>';
			if ( (float) $svc['addl'] > 0 ) {
				echo '<div class="rc-price-addl"></div>';
			}
			echo '</div></div>';
			echo '<div class="rc-controls">';
			echo '<button type="button" class="rc-add">' . esc_html__( 'Add to estimate', 'celb-mgmt' ) . '</button>';
			echo '<div class="rc-added">';
			echo '<button type="button" class="rc-remove"><span class="rc-added-tick">&#10003;</span> ' . esc_html__( 'Added', 'celb-mgmt' ) . ' &middot; ' . esc_html__( 'Remove', 'celb-mgmt' ) . '</button>';
			if ( (float) $svc['addl'] > 0 ) {
				echo '<div class="rc-extra">';
				echo '<span class="rc-extra-cap"></span>';
				echo '<div class="rc-stepper"><button type="button" class="rc-minus" aria-label="' . esc_attr__( 'remove one', 'celb-mgmt' ) . '">&minus;</button><span class="rc-extranum">0</span><button type="button" class="rc-plus" aria-label="' . esc_attr__( 'add one', 'celb-mgmt' ) . '">+</button></div>';
				echo '</div>';
			}
			echo '</div>';
			echo '<span class="rc-line-price"></span>';
			echo '</div></div>';
		}
		echo '</div></section>';
	}

	/* Usage rights */
	if ( ! empty( $cfg['show']['usage'] ) && ! empty( $cfg['usage'] ) ) {
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['usage'] ) . '</h2><div class="rc-opts">';
		echo celb_rate_opt_radio( 'rc_usage', __( 'None', 'celb-mgmt' ), '', 'none', 0, $cur, true ); // phpcs:ignore
		foreach ( $cfg['usage'] as $o ) {
			$o = wp_parse_args( $o, array( 'label' => '', 'desc' => '', 'type' => 'none', 'value' => 0 ) );
			if ( '' === $o['label'] ) {
				continue;
			}
			echo celb_rate_opt_radio( 'rc_usage', $o['label'], $o['desc'], $o['type'], $o['value'], $cur, false ); // phpcs:ignore
		}
		echo '</div></section>';
	}

	/* Exclusivity */
	if ( ! empty( $cfg['show']['excl'] ) && ! empty( $cfg['excl'] ) ) {
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['excl'] ) . '</h2><div class="rc-opts">';
		echo celb_rate_opt_radio( 'rc_excl', __( 'None', 'celb-mgmt' ), '', 'none', 0, $cur, true ); // phpcs:ignore
		foreach ( $cfg['excl'] as $o ) {
			$o = wp_parse_args( $o, array( 'label' => '', 'desc' => '', 'type' => 'none', 'value' => 0 ) );
			if ( '' === $o['label'] ) {
				continue;
			}
			echo celb_rate_opt_radio( 'rc_excl', $o['label'], $o['desc'], $o['type'], $o['value'], $cur, false ); // phpcs:ignore
		}
		echo '</div></section>';
	}

	/* Rush */
	if ( ! empty( $cfg['show']['rush'] ) ) {
		$rush = wp_parse_args( (array) $cfg['rush'], array( 'label' => 'Rush Booking', 'desc' => '', 'type' => 'none', 'value' => 0 ) );
		$note = celb_rate_adj_note( $rush['type'], $rush['value'], $cur );
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['rush'] ) . '</h2>';
		echo '<label class="rc-toggle"><input type="checkbox" class="rc-rush" data-type="' . esc_attr( $rush['type'] ) . '" data-value="' . esc_attr( $rush['value'] ) . '" data-label="' . esc_attr( $rush['label'] ) . '" />';
		echo '<span class="rc-opt-main"><span class="rc-opt-label">' . esc_html( $rush['label'] ) . '</span>';
		if ( '' !== $rush['desc'] ) {
			echo '<span class="rc-opt-desc">' . esc_html( $rush['desc'] ) . '</span>';
		}
		echo '</span>';
		if ( $note ) {
			echo '<span class="rc-opt-adj">' . esc_html( $note ) . '</span>';
		}
		echo '</label></section>';
	}

	/* Travel */
	if ( ! empty( $cfg['show']['travel'] ) && ( '' !== trim( (string) $cfg['travel_desc'] ) || (float) $cfg['travel_fee'] > 0 ) ) {
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['travel'] ) . '</h2>';
		if ( '' !== trim( (string) $cfg['travel_desc'] ) ) {
			echo '<p class="rc-note">' . esc_html( $cfg['travel_desc'] ) . '</p>';
		}
		if ( (float) $cfg['travel_fee'] > 0 ) {
			echo '<label class="rc-toggle" style="margin-top:12px;"><input type="checkbox" class="rc-travelfee" data-type="fixed" data-value="' . esc_attr( $cfg['travel_fee'] ) . '" data-label="' . esc_attr( $cfg['titles']['travel'] ) . '" />';
			echo '<span class="rc-opt-main"><span class="rc-opt-label">' . esc_html__( 'Add travel fee', 'celb-mgmt' ) . '</span></span><span class="rc-opt-adj">+' . esc_html( celb_rate_money( $cfg['travel_fee'], $cur ) ) . '</span></label>';
		}
		echo '</section>';
	}

	/* Notes */
	if ( ! empty( $cfg['show']['notes'] ) && '' !== trim( (string) $cfg['notes'] ) ) {
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['notes'] ) . '</h2><p class="rc-note">' . esc_html( $cfg['notes'] ) . '</p></section>';
	}

	/* Terms */
	if ( ! empty( $cfg['show']['terms'] ) && '' !== trim( (string) $cfg['terms'] ) ) {
		echo '<section class="rc-section"><h2 class="rc-section-title">' . esc_html( $cfg['titles']['terms'] ) . '</h2><p class="rc-note">' . esc_html( $cfg['terms'] ) . '</p></section>';
	}

	/* Summary — compact sticky bar */
	$cta      = $g['rc_cta_label'];
	$contact  = $g['rc_contact_label'];
	$has_ctc  = '' !== trim( (string) $g['rc_contact_url'] );
	echo '<div class="rc-bar" id="rc-bar">';
	echo '<div class="rc-bar-details"><div class="rc-bar-details-inner">';
	echo '<ol class="rc-flow">';
	echo '<li><span class="rc-flow-num">1</span><span>' . sprintf( /* translators: %s CTA label */ esc_html__( 'Tap “%s” — this copies your selected services.', 'celb-mgmt' ), esc_html( $cta ) ) . '</span></li>';
	echo '<li><span class="rc-flow-num">2</span><span>' . sprintf( /* translators: %s contact label */ esc_html__( 'Tap “%s” and paste it into our form to get your quotation.', 'celb-mgmt' ), esc_html( $contact ) ) . '</span></li>';
	echo '</ol>';
	echo '<div class="rc-summary-body"></div>';
	if ( $has_ctc ) {
		echo '<a class="rc-contact rc-contact-wide" href="' . esc_url( $g['rc_contact_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $contact ) . '</a>';
	}
	echo '</div></div>';
	echo '<div class="rc-bar-main">';
	echo '<div class="rc-bar-total"><span class="rc-total-label">' . esc_html__( 'Total Estimate', 'celb-mgmt' ) . '</span><span class="rc-total-value">' . esc_html( celb_rate_money( 0, $cur ) ) . '</span></div>';
	echo '<div class="rc-bar-actions">';
	echo '<button type="button" class="rc-bar-toggle" aria-expanded="false"><span>' . esc_html( $cfg['titles']['summary'] ) . '</span><span class="rc-bar-chevron" aria-hidden="true">&#9662;</span></button>';
	echo '<button type="button" class="rc-copy">' . esc_html( $cta ) . '</button>';
	echo '</div></div>';
	echo '<div class="rc-bar-hint">' . esc_html__( 'Step 1 of 2 — copies your selection to paste into our contact form.', 'celb-mgmt' ) . '</div>';
	echo '</div>';

	/* Popup */
	echo '<div class="rc-popup"><div class="rc-popup-backdrop"></div><div class="rc-popup-card"><button type="button" class="rc-popup-close" aria-label="close">&times;</button>';
	echo '<div class="rc-popup-check">&#10003;</div>';
	echo '<h3 class="rc-popup-title">' . esc_html__( 'Selection copied', 'celb-mgmt' ) . '</h3>';
	echo '<p class="rc-popup-text">' . sprintf( /* translators: %s contact label */ esc_html__( 'Your selected services are now on your clipboard. Tap “%s” below, then paste (long-press → Paste) into the message field and send — we’ll reply with your final quotation.', 'celb-mgmt' ), esc_html( $contact ) ) . '</p>';
	if ( $has_ctc ) {
		echo '<a class="rc-contact rc-contact-wide" href="' . esc_url( $g['rc_contact_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $contact ) . '</a>';
	} else {
		echo '<p class="rc-popup-text" style="color:var(--rc-accent);">' . esc_html__( 'Paste it into a message to management to get your quotation.', 'celb-mgmt' ) . '</p>';
	}
	echo '</div></div>';

	echo '</div></div>';
	$html = ob_get_clean();

	$config = array(
		'currencyPre' => '',
		'currencySuf' => trim( (string) $cur ),
		'talent'      => $name,
		'contactUrl'  => $g['rc_contact_url'],
		'tServices'   => __( 'Services', 'celb-mgmt' ),
		'tOptions'    => __( 'Options', 'celb-mgmt' ),
		'tTotal'      => __( 'Total Estimate', 'celb-mgmt' ),
		'emptyText'   => __( 'Select one or more services to build your estimate.', 'celb-mgmt' ),
		'copyFooter'  => __( 'This is an indicative estimate. Final terms confirmed by management.', 'celb-mgmt' ),
		'eachExtra'   => __( '+%s each extra', 'celb-mgmt' ),
		'perExtra'    => __( '%s per extra item', 'celb-mgmt' ),
		'extraCap'    => __( 'Extra items · %s each', 'celb-mgmt' ),
		'emptyHint'   => __( 'Please add at least one service first.', 'celb-mgmt' ),
		'usdEnabled'  => $usd_offer ? 1 : 0,
		'usdRate'     => $usd_rate,
		'usdMarkup'   => $usd_markup,
		'usdSuffix'   => 'USD',
	);
	return array( 'html' => $html, 'config' => $config );
}

/* =====================  RATE CARD — ADMIN EDITOR  ===================== */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'rate_setup', __( 'Setup & Access', 'celb-mgmt' ), 'celb_rate_mb_setup', CELB_RATE_CPT, 'normal', 'high' );
	add_meta_box( 'rate_sections', __( 'Sections & Pricing', 'celb-mgmt' ), 'celb_rate_mb_sections', CELB_RATE_CPT, 'normal', 'high' );
	add_meta_box( 'rate_audience', __( 'Audience', 'celb-mgmt' ), 'celb_rate_mb_audience', CELB_RATE_CPT, 'normal', 'default' );
	add_meta_box( 'rate_options', __( 'Options, Terms & Titles', 'celb-mgmt' ), 'celb_rate_mb_options', CELB_RATE_CPT, 'normal', 'default' );
} );

/* Enqueue sortable on the rate card editor. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( $screen && CELB_RATE_CPT === $screen->post_type ) {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}
} );

function celb_rate_mb_setup( $post ) {
	wp_nonce_field( 'celb_rate_card_save', 'celb_rate_card_nonce' );
	$celeb   = (int) get_post_meta( $post->ID, '_rate_celeb', true );
	$enabled = get_post_meta( $post->ID, '_rate_enabled', true );
	$pw      = (string) get_post_meta( $post->ID, '_rate_pw', true );
	$url     = celb_rate_card_url( $post->ID );
	$celebs  = get_posts( array( 'post_type' => CELB_CPT, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	?>
	<table class="form-table">
		<tr>
			<th><label for="rate_celeb"><?php esc_html_e( 'Assigned talent', 'celb-mgmt' ); ?></label></th>
			<td>
				<select id="rate_celeb" name="rate_celeb" class="regular-text">
					<option value="0"><?php esc_html_e( '— Select celebrity —', 'celb-mgmt' ); ?></option>
					<?php foreach ( $celebs as $c ) : ?>
						<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $celeb, $c->ID ); ?>><?php echo esc_html( get_the_title( $c ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The card’s clean URL is built from this talent’s name. One card per talent.', 'celb-mgmt' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Enable', 'celb-mgmt' ); ?></th>
			<td><label><input type="checkbox" name="rate_enabled" value="1" <?php checked( $enabled, '1' ); ?> /> <?php esc_html_e( 'Make this rate card live', 'celb-mgmt' ); ?></label></td>
		</tr>
		<tr>
			<th><label for="rate_pw"><?php esc_html_e( 'Access password', 'celb-mgmt' ); ?></label></th>
			<td>
				<input type="text" id="rate_pw" name="rate_pw" value="<?php echo esc_attr( $pw ); ?>" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. STARS2026', 'celb-mgmt' ); ?>" />
				<p class="description"><?php esc_html_e( 'Required. Share this with clients. Leave blank to keep the card closed.', 'celb-mgmt' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Private link', 'celb-mgmt' ); ?></th>
			<td>
				<code><?php echo esc_html( $url ); ?></code>
				<p class="description"><?php esc_html_e( 'Publish/update to lock in the URL. Changes automatically if you rename the talent.', 'celb-mgmt' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

function celb_rate_mb_audience( $post ) {
	$social = celb_rate_social_get( $post->ID );
	?>
	<table class="widefat striped" style="max-width:680px;">
		<thead><tr><th><?php esc_html_e( 'Platform', 'celb-mgmt' ); ?></th><th><?php esc_html_e( 'Handle / account', 'celb-mgmt' ); ?></th><th><?php esc_html_e( 'Followers / subscribers', 'celb-mgmt' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( celb_rate_platforms() as $key => $meta ) :
			$h = isset( $social[ $key ]['handle'] ) ? $social[ $key ]['handle'] : '';
			$c = isset( $social[ $key ]['count'] ) ? $social[ $key ]['count'] : ''; ?>
			<tr>
				<td><strong><?php echo esc_html( $meta['label'] ); ?></strong></td>
				<td><input type="text" name="rate_social[<?php echo esc_attr( $key ); ?>][handle]" value="<?php echo esc_attr( $h ); ?>" class="widefat" placeholder="@handle" /></td>
				<td><input type="text" name="rate_social[<?php echo esc_attr( $key ); ?>][count]" value="<?php echo esc_attr( $c ); ?>" class="widefat" placeholder="e.g. 250K" /></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Leave a platform blank to hide it. The whole Audience section can be toggled off under Options.', 'celb-mgmt' ); ?></p>
	<?php
}

/* ---- One service item row ---- */
function celb_rate_item_row( $s, $i, $row, $plats ) {
	$row = wp_parse_args( $row, array( 'name' => '', 'desc' => '', 'included' => 1, 'base' => '', 'addl' => '', 'platforms' => array() ) );
	$sel = is_array( $row['platforms'] ) ? $row['platforms'] : array();
	$base = 'rate_sections[' . $s . '][items][' . $i . ']';
	ob_start(); ?>
	<div class="rc-item">
		<span class="rc-item-drag dashicons dashicons-menu"></span>
		<button type="button" class="button-link rc-item-remove dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Remove', 'celb-mgmt' ); ?>"></button>
		<div class="rc-item-grid">
			<input type="text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $row['name'] ); ?>" placeholder="<?php esc_attr_e( 'Service name (e.g. Feed Post)', 'celb-mgmt' ); ?>" />
			<input type="text" name="<?php echo esc_attr( $base ); ?>[desc]" value="<?php echo esc_attr( $row['desc'] ); ?>" placeholder="<?php esc_attr_e( 'Short description (optional)', 'celb-mgmt' ); ?>" />
		</div>
		<div class="rc-item-nums">
			<label><?php esc_html_e( 'Included', 'celb-mgmt' ); ?><input type="number" min="0" name="<?php echo esc_attr( $base ); ?>[included]" value="<?php echo esc_attr( $row['included'] ); ?>" /></label>
			<label><?php esc_html_e( 'Base price', 'celb-mgmt' ); ?><input type="number" min="0" step="any" name="<?php echo esc_attr( $base ); ?>[base]" value="<?php echo esc_attr( $row['base'] ); ?>" /></label>
			<label><?php esc_html_e( 'Extra (each)', 'celb-mgmt' ); ?><input type="number" min="0" step="any" name="<?php echo esc_attr( $base ); ?>[addl]" value="<?php echo esc_attr( $row['addl'] ); ?>" /></label>
		</div>
		<div class="rc-item-plats">
			<?php foreach ( $plats as $pk => $pm ) : ?>
				<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[platforms][]" value="<?php echo esc_attr( $pk ); ?>" <?php checked( in_array( $pk, $sel, true ) ); ?> /> <?php echo esc_html( $pm['label'] ); ?></label>
			<?php endforeach; ?>
			<span class="description"><?php esc_html_e( '(none = all)', 'celb-mgmt' ); ?></span>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ---- One section block ---- */
function celb_rate_section_block( $s, $section, $plats ) {
	$title = isset( $section['title'] ) ? $section['title'] : '';
	$items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
	ob_start(); ?>
	<div class="rc-sec" data-s="<?php echo esc_attr( $s ); ?>">
		<div class="rc-sec-head">
			<span class="rc-sec-drag dashicons dashicons-menu"></span>
			<input type="text" class="rc-sec-title" name="rate_sections[<?php echo esc_attr( $s ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Section title (e.g. Services, Event Coverage)', 'celb-mgmt' ); ?>" />
			<button type="button" class="button-link rc-sec-remove dashicons dashicons-trash" title="<?php esc_attr_e( 'Remove section', 'celb-mgmt' ); ?>"></button>
		</div>
		<div class="rc-item-list">
			<?php
			if ( empty( $items ) ) {
				echo celb_rate_item_row( $s, 'i0', array(), $plats ); // phpcs:ignore
			} else {
				foreach ( $items as $i => $item ) {
					echo celb_rate_item_row( $s, $i, $item, $plats ); // phpcs:ignore
				}
			}
			?>
		</div>
		<button type="button" class="button rc-item-add"><?php esc_html_e( '+ Add service', 'celb-mgmt' ); ?></button>
	</div>
	<?php
	return ob_get_clean();
}

function celb_rate_mb_sections( $post ) {
	$plats    = celb_rate_platforms();
	$sections = celb_rate_sections_get( $post->ID );
	if ( empty( $sections ) ) {
		$sections = array( array( 'title' => 'Services', 'items' => array() ) );
	}
	?>
	<div id="rc-sec-wrap">
		<div class="rc-sec-list">
			<?php foreach ( $sections as $s => $section ) {
				echo celb_rate_section_block( 's' . $s, $section, $plats ); // phpcs:ignore
			} ?>
		</div>
		<button type="button" class="button button-primary rc-sec-add"><?php esc_html_e( '+ Add section', 'celb-mgmt' ); ?></button>
		<p class="description"><?php esc_html_e( 'Add as many sections as you like (Services, Event Coverage, PR Packages…). Drag to reorder. Base price covers the included quantity; “Extra (each)” is charged per deliverable above that in the interactive builder.', 'celb-mgmt' ); ?></p>
		<script type="text/template" class="rc-sec-tpl"><?php echo celb_rate_section_block( '__S__', array( 'title' => '', 'items' => array( '__ITEM__' ) ), $plats ); // phpcs:ignore ?></script>
		<script type="text/template" class="rc-item-tpl"><?php echo celb_rate_item_row( '__S__', '__I__', array(), $plats ); // phpcs:ignore ?></script>
	</div>
	<style>
	#rc-sec-wrap .rc-sec{border:1px solid #c3c4c7;border-radius:8px;padding:12px;margin-bottom:14px;background:#fff;}
	#rc-sec-wrap .rc-sec-head{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
	#rc-sec-wrap .rc-sec-title{flex:1;font-size:15px;font-weight:600;}
	#rc-sec-wrap .rc-sec-drag,#rc-sec-wrap .rc-item-drag{cursor:move;color:#8c8f94;}
	#rc-sec-wrap .rc-sec-remove{color:#b32d2e;}
	#rc-sec-wrap .rc-item{border:1px solid #e0e0e0;border-radius:6px;padding:10px 10px 10px 28px;margin-bottom:8px;position:relative;background:#fafafa;}
	#rc-sec-wrap .rc-item-drag{position:absolute;left:6px;top:12px;}
	#rc-sec-wrap .rc-item-remove{position:absolute;right:6px;top:8px;color:#b32d2e;}
	#rc-sec-wrap .rc-item-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
	#rc-sec-wrap .rc-item-nums{display:grid;grid-template-columns:repeat(3,120px);gap:8px;margin-top:8px;}
	#rc-sec-wrap .rc-item-nums label{font-size:11px;display:flex;flex-direction:column;}
	#rc-sec-wrap .rc-item-plats{margin-top:8px;font-size:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
	#rc-sec-wrap .rc-item-add{margin-top:4px;}
	</style>
	<script>
	(function(){
		var wrap = document.getElementById('rc-sec-wrap');
		if (!wrap) { return; }
		var secTpl  = wrap.querySelector('.rc-sec-tpl').innerHTML;
		var itemTpl = wrap.querySelector('.rc-item-tpl').innerHTML;
		var uid = Date.now();
		function nextS(){ return 's' + (uid++); }
		function nextI(){ return 'i' + (uid++); }
		function makeItem(sIndex){
			var wrapper = document.createElement('div');
			wrapper.innerHTML = itemTpl.replace(/__S__/g, sIndex).replace(/__I__/g, nextI());
			return wrapper.firstElementChild;
		}
		function initSortable(){
			if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.sortable) { return; }
			var $ = window.jQuery;
			$(wrap).find('.rc-sec-list').sortable({ handle:'.rc-sec-drag', items:'> .rc-sec', tolerance:'pointer' });
			$(wrap).find('.rc-item-list').sortable({ handle:'.rc-item-drag', items:'> .rc-item', tolerance:'pointer' });
		}
		wrap.addEventListener('click', function(e){
			var t = e.target;
			var addSec  = t.closest('.rc-sec-add');
			var addItem = t.closest('.rc-item-add');
			var rmSec   = t.closest('.rc-sec-remove');
			var rmItem  = t.closest('.rc-item-remove');
			if (addSec){
				e.preventDefault();
				var s = nextS();
				var holder = document.createElement('div');
				holder.innerHTML = secTpl.replace(/__S__/g, s).replace(/__ITEM__/g, '');
				var sec = holder.firstElementChild;
				sec.setAttribute('data-s', s);
				// remove the placeholder empty item template artifact, add one fresh item
				var list = sec.querySelector('.rc-item-list');
				list.innerHTML = '';
				list.appendChild(makeItem(s));
				wrap.querySelector('.rc-sec-list').appendChild(sec);
				initSortable();
			} else if (addItem){
				e.preventDefault();
				var sec2 = addItem.closest('.rc-sec');
				var sIndex = sec2.getAttribute('data-s');
				sec2.querySelector('.rc-item-list').appendChild(makeItem(sIndex));
				initSortable();
			} else if (rmSec){
				e.preventDefault();
				if (wrap.querySelectorAll('.rc-sec').length > 1) { rmSec.closest('.rc-sec').remove(); }
				else { var s3 = rmSec.closest('.rc-sec'); s3.querySelector('.rc-sec-title').value=''; }
			} else if (rmItem){
				e.preventDefault();
				var sec3 = rmItem.closest('.rc-sec');
				if (sec3.querySelectorAll('.rc-item').length > 1) { rmItem.closest('.rc-item').remove(); }
			}
		});
		initSortable();
	})();
	</script>
	<?php
}

/* ---- Options meta box ---- */
function celb_rate_opt_admin_row2( $prefix, $i, $row ) {
	$row = wp_parse_args( $row, array( 'label' => '', 'desc' => '', 'type' => 'none', 'value' => 0 ) );
	$base = $prefix . '[' . $i . ']';
	ob_start(); ?>
	<div class="rc-opt-row" style="display:grid;grid-template-columns:1.2fr 1.6fr .9fr .7fr 24px;gap:6px;margin-bottom:6px;align-items:center;">
		<input type="text" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label', 'celb-mgmt' ); ?>" />
		<input type="text" name="<?php echo esc_attr( $base ); ?>[desc]" value="<?php echo esc_attr( $row['desc'] ); ?>" placeholder="<?php esc_attr_e( 'Description (optional)', 'celb-mgmt' ); ?>" />
		<select name="<?php echo esc_attr( $base ); ?>[type]">
			<option value="none" <?php selected( $row['type'], 'none' ); ?>><?php esc_html_e( 'No price', 'celb-mgmt' ); ?></option>
			<option value="percent" <?php selected( $row['type'], 'percent' ); ?>><?php esc_html_e( '% of services', 'celb-mgmt' ); ?></option>
			<option value="fixed" <?php selected( $row['type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'celb-mgmt' ); ?></option>
		</select>
		<input type="number" step="any" min="0" name="<?php echo esc_attr( $base ); ?>[value]" value="<?php echo esc_attr( $row['value'] ); ?>" placeholder="0" />
		<button type="button" class="button-link rc-opt-remove dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Remove', 'celb-mgmt' ); ?>" style="color:#b32d2e;"></button>
	</div>
	<?php
	return ob_get_clean();
}
function celb_rate_opts_admin2( $prefix, $opts ) {
	echo '<div class="rc-opt-rep"><div class="rc-opt-items">';
	if ( empty( $opts ) ) {
		$opts = array( array() );
	}
	foreach ( $opts as $i => $row ) {
		echo celb_rate_opt_admin_row2( $prefix, $i, $row ); // phpcs:ignore
	}
	echo '</div>';
	echo '<script type="text/template" class="rc-opt-tpl">' . celb_rate_opt_admin_row2( $prefix, '__i__', array() ) . '</script>'; // phpcs:ignore
	echo '<button type="button" class="button rc-opt-add">' . esc_html__( '+ Add option', 'celb-mgmt' ) . '</button></div>';
}

function celb_rate_mb_options( $post ) {
	$cfg   = celb_rate_get( $post->ID );
	$rush  = wp_parse_args( (array) $cfg['rush'], array( 'label' => '', 'desc' => '', 'type' => 'none', 'value' => 0 ) );
	?>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Currency', 'celb-mgmt' ); ?></th>
			<td><input type="text" name="rate_config[currency]" value="<?php echo esc_attr( $cfg['currency'] ); ?>" class="small-text" placeholder="EGP" /> <span class="description"><?php esc_html_e( 'Shown after every price, e.g. “13,000 EGP”.', 'celb-mgmt' ); ?></span></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Intro text', 'celb-mgmt' ); ?></th>
			<td><textarea name="rate_config[intro]" rows="2" class="large-text"><?php echo esc_textarea( $cfg['intro'] ); ?></textarea></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Platforms', 'celb-mgmt' ); ?></th>
			<td><?php foreach ( celb_rate_platforms() as $pk => $pm ) {
				$on = in_array( $pk, (array) $cfg['platforms'], true );
				echo '<label style="margin-right:14px;"><input type="checkbox" name="rate_config[platforms][]" value="' . esc_attr( $pk ) . '" ' . checked( $on, true, false ) . ' /> ' . esc_html( $pm['label'] ) . '</label>';
			} ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Section titles', 'celb-mgmt' ); ?></th>
			<td>
				<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;max-width:720px;">
					<?php
					$tlabels = array( 'social' => 'Audience', 'usage' => 'Usage Rights', 'excl' => 'Exclusivity', 'rush' => 'Rush', 'travel' => 'Travel', 'notes' => 'Notes', 'terms' => 'Terms', 'summary' => 'Summary' );
					foreach ( $tlabels as $tk => $ph ) {
						echo '<input type="text" name="rate_config[titles][' . esc_attr( $tk ) . ']" value="' . esc_attr( $cfg['titles'][ $tk ] ) . '" placeholder="' . esc_attr( $ph ) . '" />';
					}
					?>
				</div>
				<p class="description"><?php esc_html_e( 'Rename the built-in sections. Your own pricing sections are named in “Sections & Pricing”.', 'celb-mgmt' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Show sections', 'celb-mgmt' ); ?></th>
			<td><?php
			$stoggles = array( 'social' => 'Audience', 'usage' => 'Usage Rights', 'excl' => 'Exclusivity', 'rush' => 'Rush', 'travel' => 'Travel', 'notes' => 'Notes', 'terms' => 'Terms' );
			foreach ( $stoggles as $sk => $lbl ) {
				echo '<label style="margin-right:14px;"><input type="checkbox" name="rate_config[show][' . esc_attr( $sk ) . ']" value="1" ' . checked( ! empty( $cfg['show'][ $sk ] ), true, false ) . ' /> ' . esc_html( $lbl ) . '</label>';
			}
			?></td>
		</tr>
		<tr>
			<th><?php echo esc_html( $cfg['titles']['usage'] ); ?></th>
			<td><?php celb_rate_opts_admin2( 'rate_config[usage]', $cfg['usage'] ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html( $cfg['titles']['excl'] ); ?></th>
			<td><?php celb_rate_opts_admin2( 'rate_config[excl]', $cfg['excl'] ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html( $cfg['titles']['rush'] ); ?></th>
			<td>
				<div style="display:grid;grid-template-columns:1.2fr 1.6fr .9fr .7fr;gap:6px;max-width:720px;">
					<input type="text" name="rate_config[rush][label]" value="<?php echo esc_attr( $rush['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label', 'celb-mgmt' ); ?>" />
					<input type="text" name="rate_config[rush][desc]" value="<?php echo esc_attr( $rush['desc'] ); ?>" placeholder="<?php esc_attr_e( 'Description', 'celb-mgmt' ); ?>" />
					<select name="rate_config[rush][type]">
						<option value="none" <?php selected( $rush['type'], 'none' ); ?>><?php esc_html_e( 'No price', 'celb-mgmt' ); ?></option>
						<option value="percent" <?php selected( $rush['type'], 'percent' ); ?>><?php esc_html_e( '% of services', 'celb-mgmt' ); ?></option>
						<option value="fixed" <?php selected( $rush['type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'celb-mgmt' ); ?></option>
					</select>
					<input type="number" step="any" min="0" name="rate_config[rush][value]" value="<?php echo esc_attr( $rush['value'] ); ?>" placeholder="0" />
				</div>
			</td>
		</tr>
		<tr>
			<th><?php echo esc_html( $cfg['titles']['travel'] ); ?></th>
			<td>
				<textarea name="rate_config[travel_desc]" rows="2" class="large-text"><?php echo esc_textarea( $cfg['travel_desc'] ); ?></textarea>
				<p style="margin-top:6px;"><label><?php esc_html_e( 'Optional selectable travel fee:', 'celb-mgmt' ); ?> <input type="number" step="any" min="0" name="rate_config[travel_fee]" value="<?php echo esc_attr( $cfg['travel_fee'] ); ?>" class="small-text" /></label> <span class="description"><?php esc_html_e( '0 = no fee toggle.', 'celb-mgmt' ); ?></span></p>
			</td>
		</tr>
		<tr>
			<th><?php echo esc_html( $cfg['titles']['notes'] ); ?></th>
			<td><textarea name="rate_config[notes]" rows="2" class="large-text"><?php echo esc_textarea( $cfg['notes'] ); ?></textarea></td>
		</tr>
		<tr>
			<th><?php echo esc_html( $cfg['titles']['terms'] ); ?></th>
			<td><textarea name="rate_config[terms]" rows="3" class="large-text"><?php echo esc_textarea( $cfg['terms'] ); ?></textarea></td>
		</tr>
	</table>
	<script>
	(function(){
		Array.prototype.forEach.call(document.querySelectorAll('.rc-opt-rep'), function(rep){
			var items = rep.querySelector('.rc-opt-items');
			var tpl = rep.querySelector('.rc-opt-tpl').innerHTML;
			var n = items.children.length + 1;
			rep.querySelector('.rc-opt-add').addEventListener('click', function(){
				var w = document.createElement('div');
				w.innerHTML = tpl.replace(/__i__/g, 'n' + (n++));
				if (w.firstElementChild) { items.appendChild(w.firstElementChild); }
			});
			rep.addEventListener('click', function(e){
				var b = e.target.closest ? e.target.closest('.rc-opt-remove') : null;
				if (b){ e.preventDefault(); var r = b.closest('.rc-opt-row'); if (r) { r.remove(); } }
			});
		});
	})();
	</script>
	<?php
}

/* ---- Save ---- */
add_action( 'save_post_' . CELB_RATE_CPT, function ( $post_id ) {
	if ( ! isset( $_POST['celb_rate_card_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_rate_card_nonce'] ), 'celb_rate_card_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$celeb = isset( $_POST['rate_celeb'] ) ? absint( $_POST['rate_celeb'] ) : 0;
	update_post_meta( $post_id, '_rate_celeb', $celeb );
	update_post_meta( $post_id, '_rate_enabled', empty( $_POST['rate_enabled'] ) ? '' : '1' );
	update_post_meta( $post_id, '_rate_pw', isset( $_POST['rate_pw'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_pw'] ) ) : '' );

	/* Social */
	$social = array();
	if ( isset( $_POST['rate_social'] ) && is_array( $_POST['rate_social'] ) ) {
		foreach ( celb_rate_platforms() as $key => $meta ) {
			$h = isset( $_POST['rate_social'][ $key ]['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_social'][ $key ]['handle'] ) ) : '';
			$c = isset( $_POST['rate_social'][ $key ]['count'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_social'][ $key ]['count'] ) ) : '';
			if ( '' !== $h || '' !== $c ) {
				$social[ $key ] = array( 'handle' => $h, 'count' => $c );
			}
		}
	}
	update_post_meta( $post_id, '_rate_social', $social );

	/* Sections + items */
	$plat_keys = array_keys( celb_rate_platforms() );
	$sections  = array();
	if ( isset( $_POST['rate_sections'] ) && is_array( $_POST['rate_sections'] ) ) {
		foreach ( $_POST['rate_sections'] as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$title = isset( $sec['title'] ) ? sanitize_text_field( wp_unslash( $sec['title'] ) ) : '';
			$items = array();
			if ( isset( $sec['items'] ) && is_array( $sec['items'] ) ) {
				foreach ( $sec['items'] as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$nm = isset( $item['name'] ) ? sanitize_text_field( wp_unslash( $item['name'] ) ) : '';
					if ( '' === $nm ) {
						continue;
					}
					$plat = array();
					if ( isset( $item['platforms'] ) && is_array( $item['platforms'] ) ) {
						foreach ( $item['platforms'] as $p ) {
							$p = sanitize_key( $p );
							if ( in_array( $p, $plat_keys, true ) ) {
								$plat[] = $p;
							}
						}
					}
					$items[] = array(
						'name'      => $nm,
						'desc'      => isset( $item['desc'] ) ? sanitize_text_field( wp_unslash( $item['desc'] ) ) : '',
						'included'  => isset( $item['included'] ) ? max( 0, (int) $item['included'] ) : 0,
						'base'      => isset( $item['base'] ) ? max( 0, (float) $item['base'] ) : 0,
						'addl'      => isset( $item['addl'] ) ? max( 0, (float) $item['addl'] ) : 0,
						'platforms' => $plat,
					);
				}
			}
			if ( '' === $title && empty( $items ) ) {
				continue;
			}
			$sections[] = array( 'title' => $title, 'items' => $items );
		}
	}
	update_post_meta( $post_id, '_rate_sections', $sections );

	/* Config */
	$d   = celb_rate_defaults();
	$in  = isset( $_POST['rate_config'] ) && is_array( $_POST['rate_config'] ) ? wp_unslash( $_POST['rate_config'] ) : array();
	$cfg = array();
	$cfg['currency']    = isset( $in['currency'] ) ? sanitize_text_field( $in['currency'] ) : $d['currency'];
	$cfg['intro']       = isset( $in['intro'] ) ? sanitize_textarea_field( $in['intro'] ) : '';
	$cfg['travel_desc'] = isset( $in['travel_desc'] ) ? sanitize_textarea_field( $in['travel_desc'] ) : '';
	$cfg['notes']       = isset( $in['notes'] ) ? sanitize_textarea_field( $in['notes'] ) : '';
	$cfg['terms']       = isset( $in['terms'] ) ? sanitize_textarea_field( $in['terms'] ) : '';
	$cfg['travel_fee']  = isset( $in['travel_fee'] ) ? max( 0, (float) $in['travel_fee'] ) : 0;

	$cfg['platforms'] = array();
	if ( isset( $in['platforms'] ) && is_array( $in['platforms'] ) ) {
		foreach ( $in['platforms'] as $p ) {
			$p = sanitize_key( $p );
			if ( in_array( $p, $plat_keys, true ) ) {
				$cfg['platforms'][] = $p;
			}
		}
	}
	if ( empty( $cfg['platforms'] ) ) {
		$cfg['platforms'] = $plat_keys;
	}

	$cfg['titles'] = array();
	foreach ( array_keys( $d['titles'] ) as $tk ) {
		$cfg['titles'][ $tk ] = isset( $in['titles'][ $tk ] ) && '' !== trim( $in['titles'][ $tk ] ) ? sanitize_text_field( $in['titles'][ $tk ] ) : $d['titles'][ $tk ];
	}
	$cfg['show'] = array();
	foreach ( array_keys( $d['show'] ) as $sk ) {
		$cfg['show'][ $sk ] = empty( $in['show'][ $sk ] ) ? 0 : 1;
	}

	$cfg['usage'] = celb_rate_sanitize_opts( isset( $in['usage'] ) ? $in['usage'] : array() );
	$cfg['excl']  = celb_rate_sanitize_opts( isset( $in['excl'] ) ? $in['excl'] : array() );

	$rush = isset( $in['rush'] ) && is_array( $in['rush'] ) ? $in['rush'] : array();
	$cfg['rush'] = array(
		'label' => isset( $rush['label'] ) ? sanitize_text_field( $rush['label'] ) : 'Rush Booking',
		'desc'  => isset( $rush['desc'] ) ? sanitize_text_field( $rush['desc'] ) : '',
		'type'  => ( isset( $rush['type'] ) && in_array( $rush['type'], array( 'none', 'percent', 'fixed' ), true ) ) ? $rush['type'] : 'none',
		'value' => isset( $rush['value'] ) ? max( 0, (float) $rush['value'] ) : 0,
	);
	update_post_meta( $post_id, '_rate_config', $cfg );

	/* Auto title from talent (keeps the admin list readable). */
	if ( $celeb ) {
		$want = get_the_title( $celeb ) . ' — ' . __( 'Rate Card', 'celb-mgmt' );
		$cur  = get_post_field( 'post_title', $post_id );
		if ( $cur !== $want ) {
			remove_action( 'save_post_' . CELB_RATE_CPT, __FUNCTION__ );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $want ) );
			add_action( 'save_post_' . CELB_RATE_CPT, __FUNCTION__ );
		}
	}
}, 10, 1 );

/* =========================================================================
 * 21. CALENDAR SYNC  (per-celebrity subscribable ICS feed)
 *     /celeb-cal/{token}.ics  — Projects, Schedule, confirmed Requests.
 *     Timezone-correct (Africa/Cairo VTIMEZONE from the tz database), so DST
 *     and device-local display are handled by the calendar app.
 * ====================================================================== */

/* Get (or lazily create) a celebrity's secret calendar token. */
function celb_cal_token( $celeb_id, $create = true ) {
	$celeb_id = (int) $celeb_id;
	$token    = (string) get_post_meta( $celeb_id, '_celb_cal_token', true );
	if ( '' === $token && $create ) {
		$token = bin2hex( random_bytes( 16 ) );
		update_post_meta( $celeb_id, '_celb_cal_token', $token );
	}
	return $token;
}
function celb_cal_regenerate_token( $celeb_id ) {
	$token = bin2hex( random_bytes( 16 ) );
	update_post_meta( (int) $celeb_id, '_celb_cal_token', $token );
	return $token;
}
function celb_cal_feed_url( $celeb_id, $scheme = 'https' ) {
	$token = celb_cal_token( $celeb_id );
	if ( '' === $token ) {
		return '';
	}
	$url = home_url( '/celeb-cal/' . $token . '.ics' );
	if ( 'webcal' === $scheme ) {
		// Use webcals:// (TLS) on HTTPS sites so Apple Calendar fetches the feed
		// over HTTPS. Plain webcal:// is fetched over HTTP by iOS, which triggers
		// an "Insecure Connection" warning and validation failure.
		if ( 0 === strpos( $url, 'https://' ) ) {
			$url = preg_replace( '#^https://#', 'webcals://', $url );
		} else {
			$url = preg_replace( '#^http://#', 'webcal://', $url );
		}
	}
	return $url;
}
function celb_cal_token_lookup( $token ) {
	if ( ! preg_match( '/^[a-f0-9]{16,}$/', (string) $token ) ) {
		return 0;
	}
	$ids = get_posts( array(
		'post_type'        => CELB_CPT,
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'meta_key'         => '_celb_cal_token',
		'meta_value'       => $token,
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/* ---- ICS helpers ---- */
function celb_ics_escape( $s ) {
	return str_replace( array( "\\", ";", ",", "\r\n", "\n", "\r" ), array( "\\\\", "\\;", "\\,", "\\n", "\\n", "\\n" ), (string) $s );
}
function celb_ics_fold( $line ) {
	// RFC 5545 line folding at 74 octets.
	if ( strlen( $line ) <= 74 ) {
		return $line;
	}
	$out   = '';
	$chunk = 74;
	while ( strlen( $line ) > $chunk ) {
		$out  .= substr( $line, 0, $chunk ) . "\r\n ";
		$line  = substr( $line, $chunk );
		$chunk = 73; // continuation lines start with a space
	}
	return $out . $line;
}
/* Build VALARM lines for an event. $mins < 0 = no alert; 0 = at event time. */
function celb_ics_valarm( $mins, $summary ) {
	$mins = (int) $mins;
	if ( $mins < 0 ) {
		return array();
	}
	return array(
		'BEGIN:VALARM',
		'ACTION:DISPLAY',
		celb_ics_fold( 'DESCRIPTION:' . celb_ics_escape( $summary ) ),
		'TRIGGER:-PT' . $mins . 'M',
		'END:VALARM',
	);
}
function celb_ics_offset( $sec ) {
	$sign = $sec < 0 ? '-' : '+';
	$sec  = abs( (int) $sec );
	return sprintf( '%s%02d%02d', $sign, intdiv( $sec, 3600 ), intdiv( $sec % 3600, 60 ) );
}
/* Build an accurate VTIMEZONE from the PHP timezone database for a window. */
function celb_ics_vtimezone( $tzid, $from_ts, $to_ts ) {
	try {
		$tz = new DateTimeZone( $tzid );
	} catch ( Exception $e ) {
		return array();
	}
	$trans = $tz->getTransitions( $from_ts, $to_ts );
	if ( ! is_array( $trans ) || empty( $trans ) ) {
		return array();
	}
	$lines = array( 'BEGIN:VTIMEZONE', 'TZID:' . $tzid );
	if ( count( $trans ) === 1 ) {
		$t       = $trans[0];
		$lines[] = 'BEGIN:STANDARD';
		$lines[] = 'TZOFFSETFROM:' . celb_ics_offset( $t['offset'] );
		$lines[] = 'TZOFFSETTO:' . celb_ics_offset( $t['offset'] );
		if ( ! empty( $t['abbr'] ) ) {
			$lines[] = 'TZNAME:' . $t['abbr'];
		}
		$lines[] = 'DTSTART:19700101T000000';
		$lines[] = 'END:STANDARD';
	} else {
		$prev_off = $trans[0]['offset'];
		for ( $i = 1; $i < count( $trans ); $i++ ) {
			$t    = $trans[ $i ];
			$wall = $t['ts'] + $prev_off; // wall-clock time in the previous offset
			$tag  = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';
			$lines[] = 'BEGIN:' . $tag;
			$lines[] = 'TZOFFSETFROM:' . celb_ics_offset( $prev_off );
			$lines[] = 'TZOFFSETTO:' . celb_ics_offset( $t['offset'] );
			if ( ! empty( $t['abbr'] ) ) {
				$lines[] = 'TZNAME:' . $t['abbr'];
			}
			$lines[]  = 'DTSTART:' . gmdate( 'Ymd\THis', $wall );
			$lines[]  = 'END:' . $tag;
			$prev_off = $t['offset'];
		}
	}
	$lines[] = 'END:VTIMEZONE';
	return $lines;
}

/* ---- Collect a celebrity's events into a normalized list ---- */
function celb_cal_collect( $celeb_id ) {
	$celeb_id = (int) $celeb_id;
	$events   = array();
	$host     = wp_parse_url( home_url(), PHP_URL_HOST );

	/* Schedule (timed appointments). */
	$scheds = get_posts( array(
		'post_type'        => 'celb_sched',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'meta_query'       => array( array( 'key' => '_sched_celeb', 'value' => $celeb_id ) ),
		'suppress_filters' => true,
	) );
	foreach ( $scheds as $sp ) {
		$d = celb_sched_data( $sp->ID );
		if ( empty( $d['date'] ) ) {
			continue;
		}
		$sstatus = (string) get_post_meta( $sp->ID, '_sched_status', true );
		// Canceled schedules never appear on the calendar.
		if ( 'Canceled' === $sstatus ) {
			continue;
		}
		$sdate = $d['date'];
		$stime = $d['time'] ? $d['time'] : '';
		// Postponed → move to the new date/time.
		if ( 'Postponed' === $sstatus ) {
			$nd = (string) get_post_meta( $sp->ID, '_sched_new_date', true );
			$nt = (string) get_post_meta( $sp->ID, '_sched_new_time', true );
			if ( '' === $nd ) {
				continue;
			}
			$sdate = $nd;
			if ( '' !== $nt ) {
				$stime = $nt;
			}
		}
		$loc = '';
		if ( ! empty( $d['location'] ) && is_array( $d['location'] ) ) {
			$loc = ! empty( $d['location']['address'] ) ? $d['location']['address'] : ( ! empty( $d['location']['label'] ) ? $d['location']['label'] : '' );
		}
		$stype = $d['type'];
		if ( 'Other' === $stype ) {
			$so = (string) get_post_meta( $sp->ID, '_sched_type_other', true );
			if ( '' !== $so ) { $stype = $so; }
		}
		$title  = $d['title'] ? $d['title'] : ( $stype ? $stype : __( 'Appointment', 'celb-mgmt' ) );
		$suffix = ( 'Postponed' === $sstatus ) ? ' (' . __( 'Postponed', 'celb-mgmt' ) . ')' : '';
		// Weekly recurrence via RRULE (calendar apps expand future occurrences).
		$rrule = '';
		if ( '1' === get_post_meta( $sp->ID, '_sched_recur', true ) ) {
			$until  = (string) get_post_meta( $sp->ID, '_sched_recur_until', true );
			$rrule  = 'FREQ=WEEKLY';
			if ( '' !== $until ) {
				$rrule .= ';UNTIL=' . str_replace( '-', '', $until ) . 'T235959Z';
			}
		}
		$events[] = array(
			'uid'      => 'sched-' . $sp->ID . '@' . $host,
			'all_day'  => empty( $stime ),
			'date'     => $sdate,
			'time'     => $stime,
			'dur'      => $d['duration'] ? (int) $d['duration'] : 60,
			'summary'  => $title . $suffix,
			'location' => $loc,
			'alarm'    => ( '' === get_post_meta( $sp->ID, '_sched_reminder', true ) ? 60 : (int) get_post_meta( $sp->ID, '_sched_reminder', true ) ),
			'rrule'    => $rrule,
			'desc'     => trim( (string) $d['description'] . ( ! empty( $d['prep'] ) ? "\n\nPrep: " . $d['prep'] : '' ) ),
			'modified' => get_post_modified_time( 'U', true, $sp->ID ),
		);
	}

	/* Projects (shooting days = all-day). */
	$projects = get_posts( array(
		'post_type'        => 'celb_project',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'meta_query'       => array( array( 'key' => '_proj_celeb', 'value' => $celeb_id ) ),
		'suppress_filters' => true,
	) );
	foreach ( $projects as $pp ) {
		// Canceled projects are hidden from the calendar entirely.
		if ( 'Canceled' === (string) get_post_meta( $pp->ID, '_proj_status', true ) ) {
			continue;
		}
		$ptitle = get_the_title( $pp->ID );
		$mod    = get_post_modified_time( 'U', true, $pp->ID );
		$pr_raw = get_post_meta( $pp->ID, '_proj_reminder', true );
		$proj_alarm = ( '' === $pr_raw ) ? 1440 : (int) $pr_raw;
		if ( '1' === get_post_meta( $pp->ID, '_proj_recur', true ) ) {
			$r_start = (string) get_post_meta( $pp->ID, '_proj_start', true );
			$r_time  = (string) get_post_meta( $pp->ID, '_proj_recur_time', true );
			$r_dur   = (int) get_post_meta( $pp->ID, '_proj_recur_dur', true );
			$r_until = (string) get_post_meta( $pp->ID, '_proj_recur_until', true );
			if ( '' !== $r_start ) {
				$r_rrule = 'FREQ=WEEKLY';
				if ( '' !== $r_until ) {
					$r_rrule .= ';UNTIL=' . str_replace( '-', '', $r_until ) . 'T235959Z';
				}
				$plocs_r = get_post_meta( $pp->ID, '_proj_locations', true );
				$rloc    = '';
				if ( is_array( $plocs_r ) && $plocs_r ) {
					$fr   = reset( $plocs_r );
					$rloc = ! empty( $fr['address'] ) ? $fr['address'] : ( ! empty( $fr['label'] ) ? $fr['label'] : '' );
				}
				$events[] = array(
					'uid'      => 'projrec-' . $pp->ID . '@' . $host,
					'all_day'  => empty( $r_time ),
					'date'     => $r_start,
					'time'     => $r_time,
					'dur'      => $r_dur ? $r_dur : 60,
					'summary'  => sprintf( /* translators: %s project */ __( 'Shoot: %s', 'celb-mgmt' ), $ptitle ),
					'location' => $rloc,
					'rrule'    => $r_rrule,
					'alarm'    => $proj_alarm,
					'desc'     => (string) get_post_meta( $pp->ID, '_proj_notes', true ),
					'modified' => $mod,
				);
			}
		}

		$days = get_post_meta( $pp->ID, '_proj_days', true );
		if ( ! is_array( $days ) ) {
			continue;
		}
		/* Map of Shooting Location label → address, so a day whose location
		   matches a defined location resolves to its geocodable address (which
		   is what makes the calendar app show a map pin). */
		$plocs        = get_post_meta( $pp->ID, '_proj_locations', true );
		$ploc_map     = array();
		$ploc_default = '';
		if ( is_array( $plocs ) && $plocs ) {
			foreach ( $plocs as $L ) {
				$lbl = isset( $L['label'] ) ? trim( (string) $L['label'] ) : '';
				$adr = isset( $L['address'] ) ? trim( (string) $L['address'] ) : '';
				if ( '' !== $lbl ) {
					$ploc_map[ mb_strtolower( $lbl ) ] = ( '' !== $adr ) ? $adr : $lbl;
				}
			}
			$first        = reset( $plocs );
			$ploc_default = trim( isset( $first['address'] ) ? (string) $first['address'] : '' );
			if ( '' === $ploc_default && ! empty( $first['label'] ) ) {
				$ploc_default = (string) $first['label'];
			}
		}
		foreach ( $days as $day ) {
			$dstatus = isset( $day['status'] ) ? $day['status'] : 'scheduled';
			// Canceled days never appear on the calendar.
			if ( 'canceled' === $dstatus ) {
				continue;
			}
			// Postponed days appear on their NEW date (the original is dropped).
			$ddate = $day['date'];
			if ( 'postponed' === $dstatus ) {
				if ( empty( $day['new_date'] ) ) {
					continue;
				}
				$ddate = $day['new_date'];
			}
			if ( empty( $ddate ) ) {
				continue;
			}
			$call = ( 'postponed' === $dstatus && ! empty( $day['new_call'] ) ) ? $day['new_call'] : ( isset( $day['call'] ) ? $day['call'] : '' );
			$dtime = isset( $day['time'] ) ? $day['time'] : '';
			if ( 'postponed' === $dstatus && ! empty( $day['new_time'] ) ) {
				$dtime = $day['new_time'];
			}
			$ddur = isset( $day['dur'] ) ? (int) $day['dur'] : 0;
			$descparts = array();
			if ( ! empty( $call ) ) {
				$descparts[] = 'Call time: ' . $call;
			}
			if ( ! empty( $day['notes'] ) ) {
				$descparts[] = $day['notes'];
			}
			// Resolve location to a full address where possible.
			$dloc = isset( $day['location'] ) ? trim( (string) $day['location'] ) : '';
			if ( '' !== $dloc ) {
				$k    = mb_strtolower( $dloc );
				$dloc = isset( $ploc_map[ $k ] ) ? $ploc_map[ $k ] : $dloc;
			} else {
				$dloc = $ploc_default;
			}
			$suffix = ( 'completed' === $dstatus ) ? ' (' . __( 'Completed', 'celb-mgmt' ) . ')' : ( ( 'postponed' === $dstatus ) ? ' (' . __( 'Postponed', 'celb-mgmt' ) . ')' : '' );
			$events[] = array(
				'uid'      => 'proj-' . $pp->ID . '-' . str_replace( '-', '', $ddate ) . '@' . $host,
				'all_day'  => ( '' === $dtime ),
				'date'     => $ddate,
				'time'     => $dtime,
				'dur'      => $ddur ? $ddur : 480,
				'summary'  => sprintf( /* translators: %s project */ __( 'Shoot: %s', 'celb-mgmt' ), $ptitle ) . $suffix,
				'location' => $dloc,
				'alarm'    => $proj_alarm,
				'desc'     => implode( "\n", $descparts ),
				'modified' => $mod,
			);
		}
	}

	/* Requests — accepted/confirmed only (in_progress) with a date. */
	$reqs = get_posts( array(
		'post_type'        => 'celb_request',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'meta_query'       => array(
			'relation' => 'AND',
			array( 'key' => '_req_celeb', 'value' => $celeb_id ),
			array( 'key' => '_req_status', 'value' => 'in_progress' ),
		),
		'suppress_filters' => true,
	) );
	foreach ( $reqs as $rp ) {
		$d = celb_request_data( $rp->ID );
		if ( empty( $d['date'] ) ) {
			continue;
		}
		$who   = $d['company'] ? $d['company'] : $d['name'];
		$title = trim( ( $d['type'] ? $d['type'] : __( 'Booking', 'celb-mgmt' ) ) . ( $who ? ' — ' . $who : '' ) );
		$events[] = array(
			'uid'      => 'req-' . $rp->ID . '@' . $host,
			'all_day'  => true,
			'date'     => $d['date'],
			'time'     => '',
			'dur'      => 0,
			'summary'  => $title,
			'location' => '',
			'desc'     => trim( (string) $d['message'] ),
			'modified' => get_post_modified_time( 'U', true, $rp->ID ),
		);
	}

	return $events;
}

/* ---- Output the ICS feed ---- */
function celb_cal_output( $celeb_id ) {
	$celeb_id = (int) $celeb_id;
	$tzid     = wp_timezone_string();
	if ( ! $tzid || false !== strpos( $tzid, '+' ) || false !== strpos( $tzid, '-' ) || false === strpos( $tzid, '/' ) ) {
		$tzid = 'Africa/Cairo'; // fall back to a real zone if the site is set to a raw UTC offset
	}
	$name   = get_the_title( $celeb_id );
	$events = celb_cal_collect( $celeb_id );

	$now    = time();
	$vtz    = celb_ics_vtimezone( $tzid, $now - YEAR_IN_SECONDS, $now + ( 3 * YEAR_IN_SECONDS ) );

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//iLike Agency//CELB MGMT//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . celb_ics_escape( $name . ' — ' . __( 'Schedule', 'celb-mgmt' ) ),
		'X-WR-TIMEZONE:' . $tzid,
		'X-PUBLISHED-TTL:PT1H',
		'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
	);
	$lines = array_merge( $lines, $vtz );

	$stamp = gmdate( 'Ymd\THis\Z' );
	foreach ( $events as $ev ) {
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $ev['uid'];
		$lines[] = 'DTSTAMP:' . $stamp;
		if ( ! empty( $ev['modified'] ) ) {
			$lines[] = 'LAST-MODIFIED:' . gmdate( 'Ymd\THis\Z', (int) $ev['modified'] );
		}
		if ( $ev['all_day'] ) {
			$start   = str_replace( '-', '', $ev['date'] );
			$endts   = strtotime( $ev['date'] . ' +1 day' );
			$lines[] = 'DTSTART;VALUE=DATE:' . $start;
			$lines[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', $endts );
		} else {
			$local   = str_replace( '-', '', $ev['date'] ) . 'T' . str_replace( ':', '', $ev['time'] ) . '00';
			$lines[] = 'DTSTART;TZID=' . $tzid . ':' . $local;
			$lines[] = 'DURATION:PT' . (int) $ev['dur'] . 'M';
		}
		if ( ! empty( $ev['rrule'] ) ) { $lines[] = 'RRULE:' . $ev['rrule']; }
		$lines[] = celb_ics_fold( 'SUMMARY:' . celb_ics_escape( $ev['summary'] ) );
		if ( ! empty( $ev['location'] ) ) {
			$lines[] = celb_ics_fold( 'LOCATION:' . celb_ics_escape( $ev['location'] ) );
		}
		if ( ! empty( $ev['desc'] ) ) {
			$lines[] = celb_ics_fold( 'DESCRIPTION:' . celb_ics_escape( $ev['desc'] ) );
		}
		$lines = array_merge( $lines, celb_ics_valarm( array_key_exists( 'alarm', $ev ) ? $ev['alarm'] : 1440, $ev['summary'] ) );
		$lines[] = 'END:VEVENT';
	}
	$lines[] = 'END:VCALENDAR';

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( ( $name ? $name : 'celebrity' ) . '.ics' ) . '"' );
	echo implode( "\r\n", $lines );
	exit;
}

/* ---- Scheduling email notifications ---- */
function celb_send_notice( $to, $subject, $heading, $rows, $intro ) {
	if ( false !== strpos( (string) $to, ',' ) ) { // comma-separated list: send to each
		$sent = false;
		foreach ( array_filter( array_map( 'trim', explode( ',', $to ) ) ) as $one ) {
			$sent = celb_send_notice( $one, $subject, $heading, $rows, $intro ) || $sent;
		}
		return $sent;
	}
	$to = sanitize_email( $to );
	if ( ! $to || ! is_email( $to ) ) {
		return false;
	}
	$s         = celb_get_settings();
	$from_name = ! empty( $s['sched_email_from_name'] ) ? $s['sched_email_from_name'] : get_bloginfo( 'name' );
	$from      = ! empty( $s['sched_email_from'] ) ? $s['sched_email_from'] : get_option( 'admin_email' );
	$logo      = celb_logo_url();
	$accent    = celb_accent();

	$rows_html = '';
	foreach ( $rows as $label => $val ) {
		if ( '' === $val || null === $val ) {
			continue;
		}
		$rows_html .= '<tr><td style="padding:6px 14px 6px 0;color:#888;font-size:13px;white-space:nowrap;vertical-align:top;">' . esc_html( $label ) . '</td><td style="padding:6px 0;color:#111;font-size:14px;font-weight:600;">' . esc_html( $val ) . '</td></tr>';
	}

	$logo_html = $logo
		? '<img src="' . esc_url( $logo ) . '" alt="" style="max-height:34px;width:auto;">'
		: '<span style="color:#fff;font-size:20px;font-weight:700;">' . esc_html( $from_name ) . '</span>';

	$body  = '<!DOCTYPE html><html><body style="margin:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">';
	$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:28px 0;"><tr><td align="center">';
	$body .= '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">';
	$body .= '<tr><td style="background:#000;padding:22px 28px;text-align:center;">' . $logo_html . '</td></tr>';
	$body .= '<tr><td style="padding:28px 28px 8px;">';
	$body .= '<h1 style="margin:0 0 6px;font-size:19px;color:#111;">' . esc_html( $heading ) . '</h1>';
	$body .= '<p style="margin:0 0 18px;font-size:14px;color:#555;line-height:1.5;">' . esc_html( $intro ) . '</p>';
	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-top:1px solid #eee;padding-top:8px;">' . $rows_html . '</table>';
	$body .= '</td></tr>';
	$body .= '<tr><td style="padding:20px 28px 26px;">';
	$body .= '<div style="height:3px;width:44px;background:' . esc_attr( $accent ) . ';margin:6px 0 14px;"></div>';
	$body .= '<p style="margin:0;font-size:12px;color:#999;line-height:1.5;">' . esc_html( $from_name ) . ' &middot; ' . esc_html__( 'This is an automated scheduling notification.', 'celb-mgmt' ) . '</p>';
	$body .= '</td></tr></table></td></tr></table></body></html>';

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'From: %s <%s>', $from_name, $from ),
	);
	if ( ! empty( $s['sched_email_replyto'] ) ) {
		$headers[] = 'Reply-To: ' . $s['sched_email_replyto'];
	}
	if ( ! empty( $s['sched_email_cc'] ) ) {
		$headers[] = 'Cc: ' . $s['sched_email_cc'];
	}
	return wp_mail( $to, $subject, $body, $headers );
}

/* Notify on a project- or schedule-level status change. */
function celb_notify_status_change( $kind, $post_id, $old, $new ) {
	$title = get_the_title( $post_id );
	if ( 'project' === $kind ) {
		$email = get_post_meta( $post_id, '_proj_celeb_email', true );
		$celeb = (int) get_post_meta( $post_id, '_proj_celeb', true );
		$label = __( 'Project', 'celb-mgmt' );
		$rows  = array(
			__( 'Project', 'celb-mgmt' )   => $title,
			__( 'Celebrity', 'celb-mgmt' ) => $celeb ? get_the_title( $celeb ) : '',
			__( 'Status', 'celb-mgmt' )    => $new,
		);
	} else {
		$email = get_post_meta( $post_id, '_sched_celeb_email', true );
		$celeb = (int) get_post_meta( $post_id, '_sched_celeb', true );
		$loc   = get_post_meta( $post_id, '_sched_location', true );
		$label = __( 'Schedule', 'celb-mgmt' );
		$rows  = array(
			__( 'Schedule', 'celb-mgmt' )  => $title,
			__( 'Celebrity', 'celb-mgmt' ) => $celeb ? get_the_title( $celeb ) : '',
			__( 'Date', 'celb-mgmt' )      => get_post_meta( $post_id, '_sched_date', true ),
			__( 'Time', 'celb-mgmt' )      => get_post_meta( $post_id, '_sched_time', true ),
			__( 'Location', 'celb-mgmt' )  => is_array( $loc ) ? ( ! empty( $loc['address'] ) ? $loc['address'] : ( ! empty( $loc['label'] ) ? $loc['label'] : '' ) ) : '',
			__( 'Status', 'celb-mgmt' )    => $new,
		);
	}
	if ( ! $email ) {
		return;
	}
	/* translators: 1: Project/Schedule, 2: new status */
	$heading = sprintf( __( '%1$s update — %2$s', 'celb-mgmt' ), $label, $new );
	/* translators: 1: title, 2: status */
	$intro   = sprintf( __( 'The status of "%1$s" has changed to %2$s.', 'celb-mgmt' ), $title, $new );
	celb_send_notice( $email, sprintf( '[%1$s] %2$s — %3$s', get_bloginfo( 'name' ), $title, $new ), $heading, $rows, $intro );
}

/* Notify on a single shooting-day postpone/cancel. */
function celb_notify_day_change( $post_id, $orig_date, $action, $new_date = '', $new_call = '' ) {
	$email = get_post_meta( $post_id, '_proj_celeb_email', true );
	if ( ! $email ) {
		return;
	}
	$title = get_the_title( $post_id );
	$celeb = (int) get_post_meta( $post_id, '_proj_celeb', true );
	if ( 'postponed' === $action ) {
		$heading = __( 'Shooting day postponed', 'celb-mgmt' );
		/* translators: 1: project, 2: old date, 3: new date */
		$intro   = sprintf( __( 'A shooting day for "%1$s" has been postponed from %2$s to %3$s.', 'celb-mgmt' ), $title, $orig_date, $new_date ? $new_date : __( 'a new date (TBD)', 'celb-mgmt' ) );
		$rows    = array(
			__( 'Project', 'celb-mgmt' )       => $title,
			__( 'Celebrity', 'celb-mgmt' )     => $celeb ? get_the_title( $celeb ) : '',
			__( 'Original date', 'celb-mgmt' ) => $orig_date,
			__( 'New date', 'celb-mgmt' )      => $new_date,
			__( 'New call time', 'celb-mgmt' ) => $new_call,
			__( 'Status', 'celb-mgmt' )        => __( 'Postponed', 'celb-mgmt' ),
		);
		$word = __( 'Postponed', 'celb-mgmt' );
	} else {
		$heading = __( 'Shooting day canceled', 'celb-mgmt' );
		/* translators: 1: project, 2: date */
		$intro   = sprintf( __( 'A shooting day for "%1$s" on %2$s has been canceled and removed from the calendar.', 'celb-mgmt' ), $title, $orig_date );
		$rows    = array(
			__( 'Project', 'celb-mgmt' )   => $title,
			__( 'Celebrity', 'celb-mgmt' ) => $celeb ? get_the_title( $celeb ) : '',
			__( 'Date', 'celb-mgmt' )      => $orig_date,
			__( 'Status', 'celb-mgmt' )    => __( 'Canceled', 'celb-mgmt' ),
		);
		$word = __( 'Canceled', 'celb-mgmt' );
	}
	celb_send_notice( $email, sprintf( '[%1$s] %2$s — %3$s', get_bloginfo( 'name' ), $title, $word ), $heading, $rows, $intro );
}

/* Daily reminder cron: email the celebrity N days before (configurable). */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'celb_daily_reminders' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'celb_daily_reminders' );
	}
} );
add_action( 'celb_daily_reminders', 'celb_send_reminders' );
function celb_send_reminders() {
	$s    = celb_get_settings();
	$days = (int) $s['sched_email_reminder'];
	if ( $days <= 0 ) {
		return;
	}
	$target = gmdate( 'Y-m-d', strtotime( '+' . $days . ' day', current_time( 'timestamp' ) ) );

	$scheds = get_posts( array(
		'post_type'        => 'celb_sched',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'meta_query'       => array( array( 'key' => '_sched_date', 'value' => $target ) ),
		'suppress_filters' => true,
	) );
	foreach ( $scheds as $sp ) {
		if ( 'Canceled' === get_post_meta( $sp->ID, '_sched_status', true ) ) {
			continue;
		}
		$email = get_post_meta( $sp->ID, '_sched_celeb_email', true );
		if ( ! $email ) {
			continue;
		}
		$title = get_the_title( $sp->ID );
		celb_send_notice(
			$email,
			sprintf( '[%1$s] %2$s — %3$s', get_bloginfo( 'name' ), $title, __( 'Reminder', 'celb-mgmt' ) ),
			__( 'Upcoming schedule reminder', 'celb-mgmt' ),
			array(
				__( 'Schedule', 'celb-mgmt' ) => $title,
				__( 'Date', 'celb-mgmt' )     => $target,
				__( 'Time', 'celb-mgmt' )     => get_post_meta( $sp->ID, '_sched_time', true ),
			),
			/* translators: 1: title, 2: date */
			sprintf( __( 'Reminder: "%1$s" is coming up on %2$s.', 'celb-mgmt' ), $title, $target )
		);
	}

	$projects = get_posts( array(
		'post_type'        => 'celb_project',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'suppress_filters' => true,
	) );
	foreach ( $projects as $pp ) {
		if ( 'Canceled' === get_post_meta( $pp->ID, '_proj_status', true ) ) {
			continue;
		}
		$email = get_post_meta( $pp->ID, '_proj_celeb_email', true );
		if ( ! $email ) {
			continue;
		}
		$days_arr = get_post_meta( $pp->ID, '_proj_days', true );
		if ( ! is_array( $days_arr ) ) {
			continue;
		}
		foreach ( $days_arr as $day ) {
			$st = isset( $day['status'] ) ? $day['status'] : 'scheduled';
			if ( 'canceled' === $st ) {
				continue;
			}
			$dd = ( 'postponed' === $st && ! empty( $day['new_date'] ) ) ? $day['new_date'] : $day['date'];
			if ( $dd !== $target ) {
				continue;
			}
			$title = get_the_title( $pp->ID );
			celb_send_notice(
				$email,
				sprintf( '[%1$s] %2$s — %3$s', get_bloginfo( 'name' ), $title, __( 'Reminder', 'celb-mgmt' ) ),
				__( 'Upcoming shooting day reminder', 'celb-mgmt' ),
				array(
					__( 'Project', 'celb-mgmt' ) => $title,
					__( 'Date', 'celb-mgmt' )    => $target,
				),
				/* translators: 1: title, 2: date */
				sprintf( __( 'Reminder: a shooting day for "%1$s" is on %2$s.', 'celb-mgmt' ), $title, $target )
			);
		}
	}
}

/* ---- Agency master calendar: all celebrities' events in one feed ---- */
function celb_agency_cal_token( $create = true ) {
	$token = (string) get_option( 'celb_agency_cal_token', '' );
	if ( '' === $token && $create ) {
		$token = bin2hex( random_bytes( 16 ) );
		update_option( 'celb_agency_cal_token', $token, false );
	}
	return $token;
}
function celb_agency_cal_url( $scheme = 'https' ) {
	$token = celb_agency_cal_token();
	if ( '' === $token ) {
		return '';
	}
	// Query-string form needs no rewrite rule (which may be unflushed after a
	// zip update), so the subscription URL always resolves.
	$url = home_url( '/?celb_agency_cal=' . $token );
	if ( 'webcal' === $scheme ) {
		$url = ( 0 === strpos( $url, 'https://' ) )
			? preg_replace( '#^https://#', 'webcals://', $url )
			: preg_replace( '#^http://#', 'webcal://', $url );
	}
	return $url;
}
/* Make sure the agency token exists on every load (cheap: one option read). */
add_action( 'init', function () {
	celb_agency_cal_token( true );
}, 5 );
function celb_agency_cal_collect() {
	$events = array();
	$celebs = get_posts( array(
		'post_type'        => CELB_CPT,
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	foreach ( $celebs as $cid ) {
		$name = get_the_title( $cid );
		foreach ( celb_cal_collect( (int) $cid ) as $ev ) {
			$ev['summary'] = ( $name ? $name . ' — ' : '' ) . $ev['summary'];
			$ev['uid']     = 'ag-' . (int) $cid . '-' . $ev['uid'];
			$events[]      = $ev;
		}
	}
	return $events;
}
function celb_agency_cal_output() {
	$tzid = wp_timezone_string();
	if ( ! $tzid || false !== strpos( $tzid, '+' ) || false !== strpos( $tzid, '-' ) || false === strpos( $tzid, '/' ) ) {
		$tzid = 'Africa/Cairo';
	}
	$events = celb_agency_cal_collect();
	$now    = time();
	$vtz    = celb_ics_vtimezone( $tzid, $now - YEAR_IN_SECONDS, $now + ( 3 * YEAR_IN_SECONDS ) );

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//iLike Agency//CELB MGMT//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . celb_ics_escape( get_bloginfo( 'name' ) . ' — ' . __( 'Agency Master Calendar', 'celb-mgmt' ) ),
		'X-WR-TIMEZONE:' . $tzid,
		'X-PUBLISHED-TTL:PT1H',
		'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
	);
	$lines = array_merge( $lines, $vtz );

	$stamp = gmdate( 'Ymd\\THis\\Z' );
	foreach ( $events as $ev ) {
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $ev['uid'];
		$lines[] = 'DTSTAMP:' . $stamp;
		if ( ! empty( $ev['modified'] ) ) {
			$lines[] = 'LAST-MODIFIED:' . gmdate( 'Ymd\\THis\\Z', (int) $ev['modified'] );
		}
		if ( $ev['all_day'] ) {
			$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $ev['date'] );
			$lines[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $ev['date'] . ' +1 day' ) );
		} else {
			$local   = str_replace( '-', '', $ev['date'] ) . 'T' . str_replace( ':', '', $ev['time'] ) . '00';
			$lines[] = 'DTSTART;TZID=' . $tzid . ':' . $local;
			$lines[] = 'DURATION:PT' . (int) $ev['dur'] . 'M';
		}
		if ( ! empty( $ev['rrule'] ) ) { $lines[] = 'RRULE:' . $ev['rrule']; }
		$lines[] = celb_ics_fold( 'SUMMARY:' . celb_ics_escape( $ev['summary'] ) );
		if ( ! empty( $ev['location'] ) ) {
			$lines[] = celb_ics_fold( 'LOCATION:' . celb_ics_escape( $ev['location'] ) );
		}
		if ( ! empty( $ev['desc'] ) ) {
			$lines[] = celb_ics_fold( 'DESCRIPTION:' . celb_ics_escape( $ev['desc'] ) );
		}
		$lines = array_merge( $lines, celb_ics_valarm( array_key_exists( 'alarm', $ev ) ? $ev['alarm'] : 1440, $ev['summary'] ) );
		$lines[] = 'END:VEVENT';
	}
	$lines[] = 'END:VCALENDAR';

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="agency-calendar.ics"' );
	echo implode( "\r\n", $lines ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
add_action( 'init', function () {
	add_rewrite_rule( '^agency-cal/([a-f0-9]+)\\.ics$', 'index.php?celb_agency_cal=$matches[1]', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'celb_agency_cal';
	return $vars;
} );
add_action( 'parse_request', function ( $wp ) {
	$path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
	if ( preg_match( '#^agency-cal/([a-f0-9]{16,})\\.ics$#', $path, $m ) ) {
		$wp->query_vars = array( 'celb_agency_cal' => $m[1] );
	}
} );
add_action( 'template_redirect', function () {
	$token = get_query_var( 'celb_agency_cal' );
	if ( ! $token && isset( $_GET['celb_agency_cal'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_GET['celb_agency_cal'] ) );
	}
	if ( ! $token ) {
		return;
	}
	if ( ! hash_equals( (string) celb_agency_cal_token(), (string) $token ) ) {
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		status_header( 404 );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//iLike Agency//CELB MGMT//EN\r\nEND:VCALENDAR";
		exit;
	}
	celb_agency_cal_output();
}, 1 );

/* Ensure the agency-cal token exists and rewrite rules are flushed once after
   each plugin update (uploading a new zip does not re-run activation), so the
   /agency-cal/ subscription URL always resolves instead of erroring. */
add_action( 'init', function () {
	if ( CELB_VERSION !== get_option( 'celb_rewrite_v' ) ) {
		celb_agency_cal_token( true );
		flush_rewrite_rules();
		update_option( 'celb_rewrite_v', CELB_VERSION, false );
	}
}, 99 );

/* ---- Routing: /celeb-cal/{token}.ics ---- */
add_action( 'init', function () {
	add_rewrite_rule( '^celeb-cal/([a-f0-9]+)\.ics$', 'index.php?celb_cal=$matches[1]', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'celb_cal';
	return $vars;
} );
add_action( 'parse_request', function ( $wp ) {
	$path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
	if ( preg_match( '#^celeb-cal/([a-f0-9]{16,})\.ics$#', $path, $m ) ) {
		$wp->query_vars = array( 'celb_cal' => $m[1] );
	}
} );
add_action( 'template_redirect', function () {
	$token = get_query_var( 'celb_cal' );
	if ( ! $token && isset( $_GET['celb_cal'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_GET['celb_cal'] ) );
	}
	if ( ! $token ) {
		return;
	}
	$celeb = celb_cal_token_lookup( $token );
	nocache_headers();
	if ( ! $celeb ) {
		header( 'Content-Type: text/calendar; charset=utf-8' );
		status_header( 404 );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//iLike Agency//CELB MGMT//EN\r\nEND:VCALENDAR";
		exit;
	}
	celb_cal_output( $celeb );
}, 1 );

/* ---- Regenerate token handler (revoke old link) ---- */
add_action( 'admin_post_celb_cal_regen', function () {
	$id = isset( $_GET['celeb'] ) ? absint( $_GET['celeb'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_cal_regen_' . $id ) ) {
		wp_die( 'Invalid request.' );
	}
	celb_cal_regenerate_token( $id );
	wp_safe_redirect( get_edit_post_link( $id, 'raw' ) . '#celb-cal' );
	exit;
} );

/* =========================================================================
 * ARTIST CONTACT FORM  — dedicated, roster-connected request system
 * Shortcode [artist_contact_form]. Separate from the site's general contact.
 * ========================================================================= */

/* Submissions store (admin inbox under Celebrities). */
function celb_artreq_register() {
	register_post_type( 'celb_artreq', array(
		'labels'          => array(
			'name'               => __( 'Artist Requests', 'celb-mgmt' ),
			'singular_name'      => __( 'Artist Request', 'celb-mgmt' ),
			'menu_name'          => __( 'Artist Requests', 'celb-mgmt' ),
			'all_items'          => __( 'Artist Requests', 'celb-mgmt' ),
			'edit_item'          => __( 'Artist Request', 'celb-mgmt' ),
			'view_item'          => __( 'View Request', 'celb-mgmt' ),
			'search_items'       => __( 'Search Requests', 'celb-mgmt' ),
			'not_found'          => __( 'No requests yet.', 'celb-mgmt' ),
			'not_found_in_trash' => __( 'No requests in the trash.', 'celb-mgmt' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=' . CELB_CPT,
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
		'supports'        => array( 'title' ),
		'menu_icon'       => 'dashicons-email-alt',
	) );
}
add_action( 'init', 'celb_artreq_register' );

/* Inbox UI (list + request view): includes/admin-requests.php. */

/* Live list of bookable artists (auto-updates as the roster changes). */
function celb_artreq_artists() {
	return get_posts( array(
		'post_type'        => CELB_CPT,
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'suppress_filters' => true,
	) );
}

/* The form. */
add_shortcode( 'artist_contact_form', 'celb_artreq_shortcode' );
add_shortcode( 'CLEB_contact', 'celb_artreq_shortcode' );
function celb_artreq_shortcode( $atts ) {
	if ( function_exists( 'celb_ensure_frontend_assets' ) ) {
		celb_ensure_frontend_assets();
	}
	$s        = celb_get_settings();
	$artists  = celb_artreq_artists();
	$pre      = isset( $_GET['artist'] ) ? absint( $_GET['artist'] ) : 0;
	$sitekey  = trim( (string) $s['recaptcha_site'] );
	$action   = esc_url( admin_url( 'admin-post.php' ) );
	$sent     = isset( $_GET['celb_sent'] ) && '1' === $_GET['celb_sent'];
	$err      = isset( $_GET['celb_err'] ) ? sanitize_key( $_GET['celb_err'] ) : '';

	if ( $sitekey ) {
		wp_enqueue_script( 'celb-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
	}

	$errmsg = array(
		'fields'    => __( 'Please fill in your name, a valid email, and your request.', 'celb-mgmt' ),
		'artist'    => __( 'Please select at least one artist (or “All artists”).', 'celb-mgmt' ),
		'captcha'   => __( 'Anti-spam check failed. Please try again.', 'celb-mgmt' ),
		'spam'      => __( 'Your request could not be sent. Please try again in a moment.', 'celb-mgmt' ),
		'rate'      => __( 'Too many requests. Please try again later.', 'celb-mgmt' ),
	);

	ob_start();
	echo '<div class="celb-scope celb-acf" id="artist-contact">';
	if ( $sent ) {
		echo '<div class="celb-acf-note celb-acf-ok">' . esc_html__( 'Thank you — your request has been sent. Our team will be in touch shortly.', 'celb-mgmt' ) . '</div>';
	}
	if ( $err && isset( $errmsg[ $err ] ) ) {
		echo '<div class="celb-acf-note celb-acf-err">' . esc_html( $errmsg[ $err ] ) . '</div>';
	}
	?>
	<form class="celb-acf-form" method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput ?>" novalidate>
		<input type="hidden" name="action" value="celb_artreq" />
		<?php wp_nonce_field( 'celb_artreq', 'celb_artreq_nonce' ); ?>
		<input type="hidden" name="ar_t" value="<?php echo esc_attr( time() ); ?>" />
		<input type="hidden" name="ar_ref" value="<?php echo esc_attr( home_url( add_query_arg( array() ) ) ); ?>" />
		<div class="celb-acf-hp" aria-hidden="true"><label>Company<input type="text" name="ar_company" tabindex="-1" autocomplete="off" /></label></div>

		<div class="celb-acf-grid">
			<label class="celb-acf-f"><span><?php esc_html_e( 'Full name', 'celb-mgmt' ); ?> *</span>
				<input type="text" name="ar_name" required /></label>
			<label class="celb-acf-f"><span><?php esc_html_e( 'Email', 'celb-mgmt' ); ?> *</span>
				<input type="email" name="ar_email" required /></label>
			<label class="celb-acf-f"><span><?php esc_html_e( 'Phone number', 'celb-mgmt' ); ?></span>
				<input type="tel" name="ar_phone" /></label>
			<label class="celb-acf-f"><span><?php esc_html_e( 'WhatsApp number', 'celb-mgmt' ); ?></span>
				<input type="tel" name="ar_wa" /></label>
		</div>

		<label class="celb-acf-f celb-acf-full"><span><?php esc_html_e( 'Company / brand', 'celb-mgmt' ); ?></span>
			<input type="text" name="ar_brand" /></label>

		<label class="celb-acf-f celb-acf-full"><span><?php esc_html_e( 'Request / campaign details', 'celb-mgmt' ); ?> *</span>
			<textarea name="ar_details" rows="5" required></textarea></label>

		<div class="celb-acf-artists">
			<div class="celb-acf-artists-head">
				<span class="celb-acf-lab"><?php esc_html_e( 'Select artist(s)', 'celb-mgmt' ); ?> *</span>
				<label class="celb-acf-all"><input type="checkbox" class="celb-acf-allbox" /> <?php esc_html_e( 'All artists', 'celb-mgmt' ); ?></label>
			</div>
			<div class="celb-acf-artist-grid">
				<?php if ( $artists ) : foreach ( $artists as $a ) : ?>
					<label class="celb-acf-chip">
						<input type="checkbox" name="ar_artists[]" value="<?php echo esc_attr( $a->ID ); ?>" <?php checked( $pre, $a->ID ); ?> />
						<span><?php echo esc_html( get_the_title( $a ) ); ?></span>
					</label>
				<?php endforeach; else : ?>
					<p class="description"><?php esc_html_e( 'No artists available yet.', 'celb-mgmt' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $sitekey ) : ?>
			<div class="g-recaptcha celb-acf-captcha" data-sitekey="<?php echo esc_attr( $sitekey ); ?>"></div>
		<?php endif; ?>

		<button type="submit" class="celb-acf-submit"><?php esc_html_e( 'Send request', 'celb-mgmt' ); ?></button>
	</form>
	<?php
	echo '</div>';
	// Scoped styles + the "All" toggle.
	?>
	<style>
	.celb-acf{max-width:820px;margin:0 auto;color:var(--rose-ink,#f4f1ea);font-family:inherit;}
	.celb-acf-note{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:.95rem;}
	.celb-acf-ok{background:rgba(255,255,255,.06);border:1px solid rgba(244,241,234,.25);}
	.celb-acf-err{background:rgba(179,45,46,.12);border:1px solid rgba(179,45,46,.5);color:#f0c9c9;}
	.celb-acf-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden;}
	.celb-acf-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
	@media(max-width:640px){.celb-acf-grid{grid-template-columns:1fr;}}
	.celb-acf-f{display:block;margin-bottom:16px;}
	.celb-acf-f>span{display:block;font-family:'Space Mono',monospace;font-size:.6rem;letter-spacing:.18em;text-transform:uppercase;color:var(--rose-mut,#8b8b86);margin-bottom:8px;}
	.celb-acf-f input,.celb-acf-f textarea{width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(244,241,234,.16);border-radius:8px;padding:12px 14px;color:#fff;font-family:inherit;font-size:1rem;box-sizing:border-box;transition:border-color .25s;}
	.celb-acf-f input:focus,.celb-acf-f textarea:focus{outline:none;border-color:rgba(244,241,234,.5);}
	.celb-acf-full{margin-top:4px;}
	.celb-acf-artists{margin:6px 0 22px;}
	.celb-acf-artists-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;}
	.celb-acf-lab{font-family:'Space Mono',monospace;font-size:.6rem;letter-spacing:.18em;text-transform:uppercase;color:var(--rose-mut,#8b8b86);}
	.celb-acf-all{font-size:.85rem;color:var(--rose-ink,#f4f1ea);cursor:pointer;display:flex;align-items:center;gap:8px;}
	.celb-acf-artist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;}
	.celb-acf-chip{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.03);border:1px solid rgba(244,241,234,.14);border-radius:8px;padding:11px 13px;cursor:pointer;font-size:.9rem;transition:border-color .2s,background .2s;}
	.celb-acf-chip:hover{border-color:rgba(244,241,234,.4);}
	.celb-acf-chip input{accent-color:#fff;}
	.celb-acf-chip input:checked+span{color:#fff;}
	.celb-acf-captcha{margin:6px 0 20px;}
	.celb-acf-submit{font-family:'Space Mono',monospace;font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:#000;background:var(--rose-ink,#f4f1ea);border:none;border-radius:999px;padding:15px 34px;cursor:pointer;transition:opacity .25s;}
	.celb-acf-submit:hover{opacity:.85;}
	</style>
	<script>
	(function(){var w=document.getElementById("artist-contact");if(!w)return;var all=w.querySelector(".celb-acf-allbox");var boxes=w.querySelectorAll('input[name="ar_artists[]"]');if(all){all.addEventListener("change",function(){Array.prototype.forEach.call(boxes,function(b){b.checked=all.checked;});});Array.prototype.forEach.call(boxes,function(b){b.addEventListener("change",function(){if(!b.checked){all.checked=false;}});});}})();
	</script>
	<?php
	return ob_get_clean();
}

/* Handle submission. */
add_action( 'admin_post_nopriv_celb_artreq', 'celb_artreq_handle' );
add_action( 'admin_post_celb_artreq', 'celb_artreq_handle' );
function celb_artreq_handle() {
	$ref = isset( $_POST['ar_ref'] ) ? esc_url_raw( wp_unslash( $_POST['ar_ref'] ) ) : home_url( '/' );
	$back = function ( $code ) use ( $ref ) {
		$url = add_query_arg( $code ? array( 'celb_err' => $code ) : array( 'celb_sent' => '1' ), remove_query_arg( array( 'celb_err', 'celb_sent' ), $ref ) );
		wp_safe_redirect( $url . '#artist-contact' );
		exit;
	};

	// Nonce + honeypot + time trap.
	if ( ! isset( $_POST['celb_artreq_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_artreq_nonce'] ), 'celb_artreq' ) ) {
		$back( 'spam' );
	}
	if ( ! empty( $_POST['ar_company'] ) ) {
		$back( 'spam' );
	}
	$t = isset( $_POST['ar_t'] ) ? (int) $_POST['ar_t'] : 0;
	if ( $t && ( time() - $t ) < 3 ) {
		$back( 'spam' );
	}

	// Rate limit per IP: 5 / hour.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'celb_ar_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		$back( 'rate' );
	}

	// reCAPTCHA (if configured).
	$s = celb_get_settings();
	if ( ! empty( $s['recaptcha_secret'] ) ) {
		$resp = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
		if ( '' === $resp ) {
			$back( 'captcha' );
		}
		$v = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 8,
			'body'    => array( 'secret' => $s['recaptcha_secret'], 'response' => $resp, 'remoteip' => $ip ),
		) );
		if ( is_wp_error( $v ) ) {
			$back( 'captcha' );
		}
		$body = json_decode( wp_remote_retrieve_body( $v ), true );
		if ( empty( $body['success'] ) ) {
			$back( 'captcha' );
		}
	}

	// Fields.
	$name    = sanitize_text_field( wp_unslash( $_POST['ar_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['ar_email'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['ar_phone'] ?? '' ) );
	$wa      = sanitize_text_field( wp_unslash( $_POST['ar_wa'] ?? '' ) );
	$brand   = sanitize_text_field( wp_unslash( $_POST['ar_brand'] ?? '' ) );
	$details = sanitize_textarea_field( wp_unslash( $_POST['ar_details'] ?? '' ) );
	if ( '' === $name || ! is_email( $email ) || '' === $details ) {
		$back( 'fields' );
	}

	// Artists — validate against the live roster.
	$valid_ids = wp_list_pluck( celb_artreq_artists(), 'ID' );
	$picked    = array();
	if ( isset( $_POST['ar_artists'] ) && is_array( $_POST['ar_artists'] ) ) {
		foreach ( wp_unslash( $_POST['ar_artists'] ) as $id ) {
			$id = (int) $id;
			if ( in_array( $id, $valid_ids, true ) && ! in_array( $id, $picked, true ) ) {
				$picked[] = $id;
			}
		}
	}
	$is_all = ( count( $picked ) === count( $valid_ids ) && count( $valid_ids ) > 0 );
	if ( empty( $picked ) ) {
		$back( 'artist' );
	}

	// Store.
	$post_id = wp_insert_post( array(
		'post_type'   => 'celb_artreq',
		'post_status' => 'publish',
		'post_title'  => $name . ' — ' . date_i18n( 'Y-m-d H:i' ),
	) );
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_ar_name', $name );
		update_post_meta( $post_id, '_ar_email', $email );
		update_post_meta( $post_id, '_ar_phone', $phone );
		update_post_meta( $post_id, '_ar_wa', $wa );
		update_post_meta( $post_id, '_ar_brand', $brand );
		update_post_meta( $post_id, '_ar_details', $details );
		update_post_meta( $post_id, '_ar_artists', $is_all ? array() : $picked );
		update_post_meta( $post_id, '_ar_ip', $ip );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	// Notify the agency.
	$names = array();
	foreach ( $picked as $id ) {
		$names[] = get_the_title( $id );
	}
	$to = ! empty( $s['contact_recipient'] ) ? $s['contact_recipient'] : get_option( 'admin_email' );
	foreach ( array_filter( array_map( 'trim', explode( ',', $to ) ) ) as $recipient ) {
		celb_send_notice(
			$recipient,
			sprintf( '[%1$s] %2$s — %3$s', get_bloginfo( 'name' ), __( 'New artist request', 'celb-mgmt' ), $name ),
			__( 'New artist request', 'celb-mgmt' ),
			array(
				__( 'Name', 'celb-mgmt' )     => $name,
				__( 'Email', 'celb-mgmt' )    => $email,
				__( 'Phone', 'celb-mgmt' )    => $phone,
				__( 'WhatsApp', 'celb-mgmt' ) => $wa,
				__( 'Company / brand', 'celb-mgmt' ) => $brand,
				__( 'Artists', 'celb-mgmt' )  => $is_all ? __( 'All artists', 'celb-mgmt' ) : implode( ', ', $names ),
				__( 'Details', 'celb-mgmt' )  => $details,
			),
			__( 'A new request was submitted through the artist contact form.', 'celb-mgmt' )
		);
	}
	$back( '' );
}

/* =========================================================================
 * STANDALONE PORTAL SHELL  — theme-independent full-page rendering
 * Used by the Self-Onboarding page and the Personal Data / Emergency
 * Contacts page. Outputs its own complete HTML document (own fonts, CSS,
 * agency logo) and does NOT load the site theme.
 * ========================================================================= */
function celb_sa_css() {
	return '
	*{box-sizing:border-box}
	html,body.celb-sa{margin:0;padding:0}
	body.celb-sa{background:#000;color:#f4f1ea;font-family:"Inter",system-ui,-apple-system,sans-serif;min-height:100vh;-webkit-font-smoothing:antialiased;line-height:1.5}
	.celb-sa-bg{position:fixed;inset:0;z-index:0;background:radial-gradient(1100px 560px at 50% -12%,rgba(244,241,234,.07),transparent 60%),#000}
	.celb-sa-wrap{position:relative;z-index:1;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:clamp(28px,5vw,64px) 18px 90px}
	.celb-sa-card{width:100%;max-width:840px;background:rgba(255,255,255,.02);border:1px solid rgba(244,241,234,.1);border-radius:18px;padding:clamp(24px,5vw,58px)}
	.celb-sa-top{text-align:center;margin-bottom:34px}
	.celb-sa-logo{max-height:46px;width:auto;margin:0 auto 24px;display:block}
	.celb-sa-eyebrow{display:block;font-family:"Space Mono",monospace;font-size:.6rem;letter-spacing:.34em;text-transform:uppercase;color:#8b8b86;margin-bottom:14px}
	.celb-sa-title{font-family:"Bodoni Moda",Georgia,serif;font-weight:500;font-size:clamp(2.1rem,6vw,3.4rem);line-height:1.03;letter-spacing:-.01em;margin:0;color:#f4f1ea}
	.celb-sa-lead{max-width:60ch;margin:16px auto 0;color:#9a978f;font-size:.98rem;line-height:1.7}
	.celb-sa-foot{margin-top:42px;padding-top:22px;border-top:1px solid rgba(244,241,234,.1);text-align:center;font-family:"Space Mono",monospace;font-size:.58rem;letter-spacing:.2em;text-transform:uppercase;color:#6b6862}
	.celb-sa .celb-scope.celb-portal{max-width:none;margin:0}
	/* Password gate */
	.celb-sa-gate{max-width:420px;margin:8px auto 0;text-align:center}
	.celb-sa-gate form{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap;justify-content:center}
	.celb-sa-input{width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(244,241,234,.18);border-radius:9px;padding:14px 16px;color:#fff;font-family:inherit;font-size:1rem}
	.celb-sa-input:focus{outline:none;border-color:rgba(244,241,234,.55)}
	.celb-sa-input::placeholder{color:rgba(244,241,234,.4)}
	.celb-sa-btn{font-family:"Space Mono",monospace;font-size:.7rem;letter-spacing:.18em;text-transform:uppercase;color:#000;background:#f4f1ea;border:0;border-radius:999px;padding:15px 32px;cursor:pointer;transition:opacity .25s}
	.celb-sa-btn:hover{opacity:.85}
	.celb-sa-err{background:rgba(179,45,46,.14);border:1px solid rgba(179,45,46,.5);color:#f0c9c9;border-radius:9px;padding:12px 16px;margin:18px 0 0;font-size:.9rem}
	/* Personal-data dynamic form */
	.celb-pd-section{margin:0 0 14px;border:1px solid rgba(244,241,234,.1);border-radius:14px;overflow:hidden}
	.celb-pd-sec-head{padding:16px 20px;background:rgba(255,255,255,.02);border-bottom:1px solid rgba(244,241,234,.08)}
	.celb-pd-sec-title{font-family:"Bodoni Moda",Georgia,serif;font-size:1.3rem;font-weight:500;margin:0;color:#f4f1ea}
	.celb-pd-sec-body{padding:20px}
	.celb-pd-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
	@media(max-width:640px){.celb-pd-grid{grid-template-columns:1fr}}
	.celb-pd-f{display:block;margin:0}
	.celb-pd-f.celb-pd-wide{grid-column:1 / -1}
	.celb-pd-f>span{display:block;font-family:"Space Mono",monospace;font-size:.58rem;letter-spacing:.16em;text-transform:uppercase;color:#8b8b86;margin-bottom:8px}
	.celb-pd-req{color:#c98b8b}
	.celb-pd-f input,.celb-pd-f textarea,.celb-pd-f select{width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(244,241,234,.16);border-radius:8px;padding:12px 14px;color:#fff;font-family:inherit;font-size:1rem}
	.celb-pd-f select{appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:40px;background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2712%27 height=%278%27 viewBox=%270 0 12 8%27 fill=%27none%27%3E%3Cpath d=%27M1 1.5 6 6.5 11 1.5%27 stroke=%27%23f4f1ea%27 stroke-width=%271.4%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 15px center;cursor:pointer}
	.celb-pd-f select option{color:#111}
	.celb-pd-f input:focus,.celb-pd-f textarea:focus,.celb-pd-f select:focus{outline:none;border-color:rgba(244,241,234,.5)}
	.celb-pd-f textarea{min-height:88px;resize:vertical;line-height:1.55}
	.celb-pd-yesno{display:flex;gap:22px;padding-top:4px}
	.celb-pd-yesno label{display:flex;align-items:center;gap:8px;font-size:.95rem;color:#f4f1ea;cursor:pointer}
	.celb-pd-submit{margin-top:26px;text-align:center}
	.celb-pd-ok{text-align:center;padding:26px 0}
	.celb-pd-ok .ic{width:60px;height:60px;border-radius:50%;border:1px solid rgba(244,241,234,.4);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 20px;color:#f4f1ea}
	';
}
function celb_sa_shell( $title, $body, $eyebrow = '', $lead = '' ) {
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=utf-8' );
	}
	$logo = celb_logo_url();
	echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head>';
	echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">';
	echo '<title>' . esc_html( $title . ' — ' . get_bloginfo( 'name' ) ) . '</title>';
	echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
	echo '<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,400;6..96,500&family=Inter:wght@400;500;600&family=Space+Mono&display=swap" rel="stylesheet">';
	echo '<link rel="stylesheet" href="' . esc_url( CELB_URL . 'assets/celb-frontend.css' ) . '?ver=' . esc_attr( CELB_VERSION ) . '">';
	echo '<style>' . celb_sa_css() . '</style>'; // phpcs:ignore
	echo celb_custom_theme_head_html(); // phpcs:ignore
	echo '</head><body class="celb-sa"><div class="celb-sa-bg"></div><div class="celb-sa-wrap"><div class="celb-sa-card">';
	echo '<div class="celb-sa-top">';
	if ( $logo ) {
		echo '<img class="celb-sa-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
	}
	if ( $eyebrow && ! $logo ) {
		echo '<span class="celb-sa-eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	echo '<h1 class="celb-sa-title">' . esc_html( $title ) . '</h1>';
	if ( $lead ) {
		echo '<p class="celb-sa-lead">' . esc_html( $lead ) . '</p>';
	}
	echo '</div>';
	echo '<div class="celb-sa-body">' . $body . '</div>'; // phpcs:ignore
	echo '<div class="celb-sa-foot">&copy; ' . esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ) . ' &middot; ' . esc_html__( 'Private & confidential', 'celb-mgmt' ) . '</div>';
	echo '</div></div>';
	echo '<script src="' . esc_url( CELB_URL . 'assets/celb-frontend.js' ) . '?ver=' . esc_attr( CELB_VERSION ) . '"></script>';
	echo '</body></html>';
	exit;
}
function celb_onboarding_url() {
	return home_url( '/' . celb_page_slug( 'onb_slug' ) . '/' );
}
function celb_pdata_url() {
	return home_url( '/' . celb_page_slug( 'pdata_slug' ) . '/' );
}

/* Route the two standalone pages. */
add_action( 'template_redirect', function () {
	$page = isset( $_GET['celb_page'] ) ? sanitize_key( wp_unslash( $_GET['celb_page'] ) ) : '';
	if ( '' === $page ) {
		$qv = get_query_var( 'celb_page' );
		if ( $qv ) {
			$page = sanitize_key( $qv );
		}
	}
	if ( 'onboarding' === $page ) {
		$body = function_exists( 'celb_portal_shortcode' ) ? celb_portal_shortcode() : '';
		celb_sa_shell( __( 'Talent Onboarding', 'celb-mgmt' ), $body, get_bloginfo( 'name' ) );
	}
	if ( 'personal' === $page ) {
		celb_pdata_front();
	}
	if ( 'stars' === $page ) {
		celb_aio_standalone();
	}
	if ( 'rateonb' === $page ) {
		celb_rate_onb_front();
		exit;
	}
}, 4 );

/* =========================================================================
 * PERSONAL DATA / EMERGENCY CONTACTS MODULE
 * ========================================================================= */
function celb_pdata_field_types() {
	return array(
		'text'     => __( 'Text', 'celb-mgmt' ),
		'textarea' => __( 'Long text', 'celb-mgmt' ),
		'tel'      => __( 'Phone number', 'celb-mgmt' ),
		'email'    => __( 'Email', 'celb-mgmt' ),
		'address'  => __( 'Address (multi-line)', 'celb-mgmt' ),
		'location' => __( 'Location (map link / area)', 'celb-mgmt' ),
		'number'   => __( 'Number', 'celb-mgmt' ),
		'date'     => __( 'Date', 'celb-mgmt' ),
		'select'   => __( 'Dropdown', 'celb-mgmt' ),
		'yesno'    => __( 'Yes / No', 'celb-mgmt' ),
	);
}
function celb_pdata_default_schema() {
	return array(
		array(
			'id'    => 'sec_personal',
			'title' => 'Personal Information',
			'questions' => array(
				array( 'id' => 'q_fullname', 'label' => 'Full name', 'type' => 'text', 'required' => 1, 'options' => array() ),
				array( 'id' => 'q_marital', 'label' => 'Marital status', 'type' => 'select', 'required' => 0, 'options' => array( 'Single', 'Married', 'Divorced', 'Widowed' ) ),
				array( 'id' => 'q_children', 'label' => 'Do you have children?', 'type' => 'yesno', 'required' => 0, 'options' => array() ),
				array( 'id' => 'q_children_n', 'label' => 'Number of children', 'type' => 'number', 'required' => 0, 'options' => array() ),
			),
		),
		array(
			'id'    => 'sec_contact',
			'title' => 'Contact Information',
			'questions' => array(
				array( 'id' => 'q_phone_personal', 'label' => 'Personal / private phone', 'type' => 'tel', 'required' => 1, 'options' => array() ),
				array( 'id' => 'q_phone_work', 'label' => 'Working / business phone', 'type' => 'tel', 'required' => 0, 'options' => array() ),
				array( 'id' => 'q_email', 'label' => 'Email address', 'type' => 'email', 'required' => 1, 'options' => array() ),
			),
		),
		array(
			'id'    => 'sec_home',
			'title' => 'Home Information',
			'questions' => array(
				array( 'id' => 'q_home_loc', 'label' => 'Home location (area / city)', 'type' => 'location', 'required' => 0, 'options' => array() ),
				array( 'id' => 'q_home_addr', 'label' => 'Home address', 'type' => 'address', 'required' => 0, 'options' => array() ),
			),
		),
		array(
			'id'    => 'sec_emergency',
			'title' => 'Emergency Information',
			'questions' => array(
				array( 'id' => 'q_em_name', 'label' => 'Emergency contact name', 'type' => 'text', 'required' => 1, 'options' => array() ),
				array( 'id' => 'q_em_phone', 'label' => 'Emergency contact phone', 'type' => 'tel', 'required' => 1, 'options' => array() ),
				array( 'id' => 'q_em_rel', 'label' => 'Relationship to emergency contact', 'type' => 'text', 'required' => 0, 'options' => array() ),
			),
		),
	);
}
function celb_pdata_schema() {
	$s = get_option( 'celb_pdata_schema', null );
	if ( ! is_array( $s ) || empty( $s ) ) {
		return celb_pdata_default_schema();
	}
	return $s;
}
function celb_pdata_token() {
	$s = celb_get_settings();
	return hash_hmac( 'sha256', 'celb-pdata|' . (string) $s['pdata_password'], wp_salt( 'auth' ) );
}
function celb_pdata_token_valid( $t ) {
	return is_string( $t ) && hash_equals( celb_pdata_token(), $t );
}

/* CPT store for submissions (custom card UI, so no default list). */
add_action( 'init', function () {
	register_post_type( 'celb_pdata', array(
		'labels'       => array( 'name' => __( 'Personal Data', 'celb-mgmt' ), 'singular_name' => __( 'Personal Data', 'celb-mgmt' ) ),
		'public'       => false,
		'show_ui'      => false,
		'supports'     => array( 'title' ),
	) );
} );

/* Front-end standalone page. */
function celb_pdata_front() {
	$s = celb_get_settings();
	if ( empty( $s['pdata_enabled'] ) ) {
		celb_sa_shell( __( 'Personal Data', 'celb-mgmt' ), '<p class="celb-sa-lead" style="text-align:center">' . esc_html__( 'This page is not available right now.', 'celb-mgmt' ) . '</p>', get_bloginfo( 'name' ) );
	}
	if ( '' === (string) $s['pdata_password'] ) {
		celb_sa_shell( __( 'Personal Data', 'celb-mgmt' ), '<p class="celb-sa-lead" style="text-align:center">' . esc_html__( 'This secure page is not ready yet. Please check back soon.', 'celb-mgmt' ) . '</p>', get_bloginfo( 'name' ) );
	}

	$action = isset( $_POST['celb_pd_action'] ) ? sanitize_key( wp_unslash( $_POST['celb_pd_action'] ) ) : '';

	// Submit.
	if ( 'submit' === $action ) {
		$ok = isset( $_POST['celb_pd_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['celb_pd_nonce'] ), 'celb_pd_submit' );
		$tok = isset( $_POST['celb_pd_token'] ) ? sanitize_text_field( wp_unslash( $_POST['celb_pd_token'] ) ) : '';
		if ( ! $ok || ! celb_pdata_token_valid( $tok ) ) {
			celb_sa_shell( __( 'Personal Data', 'celb-mgmt' ), celb_pdata_gate_html( __( 'Your session expired. Please enter the password again.', 'celb-mgmt' ) ), get_bloginfo( 'name' ) );
		}
		if ( ! empty( $_POST['celb_pd_hp'] ) ) {
			celb_sa_shell( __( 'Thank you', 'celb-mgmt' ), celb_pdata_confirm_html(), get_bloginfo( 'name' ) );
		}
		celb_pdata_store();
		celb_sa_shell( __( 'Thank you', 'celb-mgmt' ), celb_pdata_confirm_html(), get_bloginfo( 'name' ) );
	}

	// Gate.
	if ( 'gate' === $action ) {
		$pass = isset( $_POST['celb_pd_pass'] ) ? (string) wp_unslash( $_POST['celb_pd_pass'] ) : '';
		if ( hash_equals( (string) $s['pdata_password'], $pass ) && '' !== $pass ) {
			celb_sa_shell( __( 'Personal Data', 'celb-mgmt' ), celb_pdata_form_html(), get_bloginfo( 'name' ), __( 'Please complete your details below. This information is kept strictly private.', 'celb-mgmt' ) );
		}
		celb_sa_shell( __( 'Personal Data', 'celb-mgmt' ), celb_pdata_gate_html( __( 'Incorrect password. Please try again.', 'celb-mgmt' ) ), get_bloginfo( 'name' ) );
	}

	celb_sa_shell( __( 'Personal Data', 'celb-mgmt' ), celb_pdata_gate_html(), get_bloginfo( 'name' ) );
}
function celb_pdata_gate_html( $err = '' ) {
	ob_start(); ?>
	<div class="celb-sa-gate">
		<p class="celb-sa-lead"><?php esc_html_e( 'This is a private, password-protected page. Enter the access password provided by the agency to continue.', 'celb-mgmt' ); ?></p>
		<?php if ( $err ) : ?><div class="celb-sa-err"><?php echo esc_html( $err ); ?></div><?php endif; ?>
		<form method="post">
			<input type="hidden" name="celb_pd_action" value="gate" />
			<input type="password" name="celb_pd_pass" class="celb-sa-input" placeholder="<?php esc_attr_e( 'Access password', 'celb-mgmt' ); ?>" autocomplete="off" required />
			<button type="submit" class="celb-sa-btn"><?php esc_html_e( 'Continue', 'celb-mgmt' ); ?></button>
		</form>
	</div>
	<?php return ob_get_clean();
}
function celb_pdata_confirm_html() {
	return '<div class="celb-pd-ok"><div class="ic">&#10003;</div><p class="celb-sa-lead" style="margin-top:0">'
		. esc_html__( 'Thank you — your information has been securely received by the agency.', 'celb-mgmt' ) . '</p></div>';
}
function celb_pdata_form_html() {
	$schema = celb_pdata_schema();
	ob_start(); ?>
	<form method="post" class="celb-pd-form" novalidate>
		<input type="hidden" name="celb_pd_action" value="submit" />
		<input type="hidden" name="celb_pd_token" value="<?php echo esc_attr( celb_pdata_token() ); ?>" />
		<?php wp_nonce_field( 'celb_pd_submit', 'celb_pd_nonce' ); ?>
		<div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="celb_pd_hp" tabindex="-1" autocomplete="off" /></div>
		<?php foreach ( $schema as $sec ) : if ( empty( $sec['questions'] ) ) { continue; } ?>
			<div class="celb-pd-section">
				<div class="celb-pd-sec-head"><h2 class="celb-pd-sec-title"><?php echo esc_html( $sec['title'] ); ?></h2></div>
				<div class="celb-pd-sec-body"><div class="celb-pd-grid">
					<?php foreach ( $sec['questions'] as $q ) : echo celb_pdata_field_html( $q ); endforeach; // phpcs:ignore ?>
				</div></div>
			</div>
		<?php endforeach; ?>
		<div class="celb-pd-submit"><button type="submit" class="celb-sa-btn"><?php esc_html_e( 'Submit securely', 'celb-mgmt' ); ?></button></div>
	</form>
	<?php return ob_get_clean();
}
function celb_pdata_field_html( $q ) {
	$id    = isset( $q['id'] ) ? $q['id'] : '';
	$label = isset( $q['label'] ) ? $q['label'] : '';
	$type  = isset( $q['type'] ) ? $q['type'] : 'text';
	$req   = ! empty( $q['required'] );
	$name  = 'pd[' . esc_attr( $id ) . ']';
	$wide  = in_array( $type, array( 'textarea', 'address' ), true ) ? ' celb-pd-wide' : '';
	$rq    = $req ? ' <span class="celb-pd-req">*</span>' : '';
	$rattr = $req ? ' required' : '';
	ob_start();
	echo '<label class="celb-pd-f' . $wide . '"><span>' . esc_html( $label ) . $rq . '</span>'; // phpcs:ignore
	switch ( $type ) {
		case 'textarea':
		case 'address':
			echo '<textarea name="' . $name . '"' . $rattr . '></textarea>';
			break;
		case 'select':
			echo '<select name="' . $name . '"' . $rattr . '><option value="">' . esc_html__( 'Select…', 'celb-mgmt' ) . '</option>';
			foreach ( (array) ( isset( $q['options'] ) ? $q['options'] : array() ) as $opt ) {
				echo '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
			}
			echo '</select>';
			break;
		case 'yesno':
			echo '</span><div class="celb-pd-yesno">'
				. '<label><input type="radio" name="' . $name . '" value="Yes"' . $rattr . '> ' . esc_html__( 'Yes', 'celb-mgmt' ) . '</label>'
				. '<label><input type="radio" name="' . $name . '" value="No"> ' . esc_html__( 'No', 'celb-mgmt' ) . '</label></div>';
			echo '</label>';
			return ob_get_clean();
		case 'tel':
			echo '<input type="tel" name="' . $name . '"' . $rattr . '>';
			break;
		case 'email':
			echo '<input type="email" name="' . $name . '"' . $rattr . '>';
			break;
		case 'number':
			echo '<input type="number" name="' . $name . '"' . $rattr . '>';
			break;
		case 'date':
			echo '<input type="date" name="' . $name . '"' . $rattr . '>';
			break;
		case 'location':
		case 'text':
		default:
			echo '<input type="text" name="' . $name . '"' . $rattr . '>';
			break;
	}
	echo '</label>';
	return ob_get_clean();
}
function celb_pdata_store() {
	$schema  = celb_pdata_schema();
	$in      = isset( $_POST['pd'] ) && is_array( $_POST['pd'] ) ? wp_unslash( $_POST['pd'] ) : array();
	$answers = array();
	$name    = '';
	foreach ( $schema as $sec ) {
		foreach ( (array) $sec['questions'] as $q ) {
			$qid = $q['id'];
			$val = isset( $in[ $qid ] ) ? $in[ $qid ] : '';
			$val = is_string( $val ) ? sanitize_textarea_field( $val ) : '';
			$answers[ $qid ] = $val;
			if ( '' === $name && ! empty( $val ) && ( 'q_fullname' === $qid || false !== stripos( $q['label'], 'full name' ) || false !== stripos( $q['label'], 'name' ) ) ) {
				$name = $val;
			}
		}
	}
	$post_id = wp_insert_post( array(
		'post_type'   => 'celb_pdata',
		'post_status' => 'publish',
		'post_title'  => ( $name ? $name : __( 'Submission', 'celb-mgmt' ) ) . ' — ' . date_i18n( 'Y-m-d H:i' ),
	) );
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_pd_answers', $answers );
		update_post_meta( $post_id, '_pd_schema', $schema ); // snapshot so labels stay stable
		update_post_meta( $post_id, '_pd_name', $name );
	}
	$to = ( ! empty( celb_get_settings()['contact_recipient'] ) ) ? celb_get_settings()['contact_recipient'] : get_option( 'admin_email' );
	foreach ( array_filter( array_map( 'trim', explode( ',', $to ) ) ) as $recipient ) {
		celb_send_notice(
			$recipient,
			sprintf( '[%1$s] %2$s — %3$s', get_bloginfo( 'name' ), __( 'Personal data submitted', 'celb-mgmt' ), $name ? $name : __( 'New submission', 'celb-mgmt' ) ),
			__( 'Personal data received', 'celb-mgmt' ),
			array( __( 'Name', 'celb-mgmt' ) => $name, __( 'Received', 'celb-mgmt' ) => date_i18n( 'Y-m-d H:i' ) ),
			__( 'A celebrity submitted their personal / emergency information. View it under Celebrities → Personal Data.', 'celb-mgmt' )
		);
	}
}

/* ---- Admin: menu (builder + submissions cards) ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=' . CELB_CPT, __( 'Personal Data', 'celb-mgmt' ), __( 'Personal Data', 'celb-mgmt' ), 'manage_options', 'celb-pdata', 'celb_pdata_submissions_page' );
	add_submenu_page( 'edit.php?post_type=' . CELB_CPT, __( 'Personal Data Form', 'celb-mgmt' ), __( 'Personal Data Form', 'celb-mgmt' ), 'manage_options', 'celb-pdata-form', 'celb_pdata_builder_page' );
	add_submenu_page( 'edit.php?post_type=' . CELB_CPT, __( 'Rate Onboarding', 'celb-mgmt' ), __( 'Rate Onboarding', 'celb-mgmt' ), 'manage_options', 'celb-rate-onb', 'celb_rate_onb_admin_page' );
} );

/* ---- Builder + submissions pages: includes/admin-workspace.php ---- */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'celb-pdata-form' ) ) {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}
} );
/* Save the builder. */
add_action( 'admin_post_celb_pdata_save', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['celb_pdata_save_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_pdata_save_nonce'] ), 'celb_pdata_save' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'celb-mgmt' ) );
	}
	$valid_types = array_keys( celb_pdata_field_types() );
	$out = array();
	$raw = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : array();
	$sn  = 0;
	foreach ( $raw as $sec ) {
		$title = isset( $sec['title'] ) ? sanitize_text_field( $sec['title'] ) : '';
		$qout  = array();
		if ( ! empty( $sec['q'] ) && is_array( $sec['q'] ) ) {
			$qn = 0;
			foreach ( $sec['q'] as $q ) {
				$label = isset( $q['label'] ) ? sanitize_text_field( $q['label'] ) : '';
				if ( '' === $label ) {
					continue;
				}
				$type = ( isset( $q['type'] ) && in_array( $q['type'], $valid_types, true ) ) ? $q['type'] : 'text';
				$qid  = ! empty( $q['id'] ) ? sanitize_key( $q['id'] ) : ( 'q_' . substr( md5( $label . $sn . $qn . wp_rand() ), 0, 8 ) );
				$opts = array();
				if ( 'select' === $type && ! empty( $q['options'] ) ) {
					foreach ( explode( ',', $q['options'] ) as $o ) {
						$o = sanitize_text_field( trim( $o ) );
						if ( '' !== $o ) {
							$opts[] = $o;
						}
					}
				}
				$qout[] = array( 'id' => $qid, 'label' => $label, 'type' => $type, 'required' => empty( $q['required'] ) ? 0 : 1, 'options' => $opts );
				$qn++;
			}
		}
		if ( '' === $title && empty( $qout ) ) {
			continue;
		}
		$sid   = ! empty( $sec['id'] ) ? sanitize_key( $sec['id'] ) : ( 'sec_' . substr( md5( $title . $sn . wp_rand() ), 0, 8 ) );
		$out[] = array( 'id' => $sid, 'title' => ( '' !== $title ? $title : __( 'Section', 'celb-mgmt' ) ), 'questions' => $qout );
		$sn++;
	}
	if ( empty( $out ) ) {
		$out = celb_pdata_default_schema();
	}
	update_option( 'celb_pdata_schema', $out );
	wp_safe_redirect( add_query_arg( array( 'post_type' => CELB_CPT, 'page' => 'celb-pdata-form', 'celb_saved' => '1' ), admin_url( 'edit.php' ) ) );
	exit;
} );

/* =========================================================================
 * VIDEOS  — per-celebrity video section (YouTube, Vimeo, Instagram, TikTok…)
 * ========================================================================= */
function celb_get_videos( $post_id ) {
	$rows = get_post_meta( $post_id, '_celb_videos', true );
	if ( ! is_array( $rows ) ) {
		return array();
	}
	$out = array();
	foreach ( $rows as $r ) {
		if ( ! empty( $r['url'] ) ) {
			$out[] = array(
				'name'  => isset( $r['name'] ) ? $r['name'] : '',
				'url'   => $r['url'],
				'cover' => isset( $r['cover'] ) ? (int) $r['cover'] : 0,
			);
		}
	}
	return $out;
}
function celb_videos_title( $post_id ) {
	$t = get_post_meta( $post_id, '_celb_videos_title', true );
	return '' !== trim( (string) $t ) ? $t : __( 'Videos', 'celb-mgmt' );
}
function celb_video_platform( $url ) {
	$u = strtolower( $url );
	if ( preg_match( '~(youtube\.com|youtu\.be)~', $u ) ) { return 'youtube'; }
	if ( preg_match( '~vimeo\.com~', $u ) ) { return 'vimeo'; }
	if ( preg_match( '~tiktok\.com~', $u ) ) { return 'tiktok'; }
	if ( preg_match( '~instagram\.com~', $u ) ) { return 'instagram'; }
	if ( preg_match( '~(facebook\.com|fb\.watch)~', $u ) ) { return 'facebook'; }
	if ( preg_match( '~(twitter\.com|x\.com)~', $u ) ) { return 'twitter'; }
	return 'link';
}
function celb_youtube_id( $url ) {
	if ( preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|v/|live/))([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
		return $m[1];
	}
	return '';
}
function celb_vimeo_id( $url ) {
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
		return $m[1];
	}
	return '';
}
function celb_instagram_parts( $url ) {
	if ( preg_match( '~instagram\.com/(?:[^/]+/)?(p|reel|reels|tv)/([A-Za-z0-9_-]+)~', $url, $m ) ) {
		$type = ( 'reels' === $m[1] ) ? 'reel' : $m[1];
		return array( $type, $m[2] );
	}
	return null;
}
function celb_tiktok_id( $url ) {
	if ( preg_match( '~tiktok\.com/(?:@[^/]+/video|v|embed(?:/v2)?|player/v1)/(\d+)~', $url, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '~/video/(\d+)~', $url, $m ) ) {
		return $m[1];
	}
	return '';
}
function celb_platform_label( $p ) {
	$labels = array(
		'youtube'   => 'YouTube',
		'vimeo'     => 'Vimeo',
		'instagram' => 'Instagram',
		'tiktok'    => 'TikTok',
		'facebook'  => 'Facebook',
		'twitter'   => 'X / Twitter',
		'link'      => __( 'Video', 'celb-mgmt' ),
	);
	return isset( $labels[ $p ] ) ? $labels[ $p ] : ucfirst( $p );
}
/* Normalise any share URL into an iframe-embeddable player URL. */
function celb_video_data( $url ) {
	$p = celb_video_platform( $url );
	$d = array( 'platform' => $p, 'embed' => '', 'vertical' => false, 'href' => $url, 'thumb' => '' );
	if ( 'youtube' === $p ) {
		$id = celb_youtube_id( $url );
		if ( $id ) {
			$d['embed'] = 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0';
			$d['thumb'] = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
		}
	} elseif ( 'vimeo' === $p ) {
		$id = celb_vimeo_id( $url );
		if ( $id ) {
			$d['embed'] = 'https://player.vimeo.com/video/' . $id . '?autoplay=1';
			$d['thumb'] = celb_oembed_thumb( 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( $url ) );
		}
	} elseif ( 'instagram' === $p ) {
		$parts = celb_instagram_parts( $url );
		if ( $parts ) {
			$d['embed']    = 'https://www.instagram.com/' . $parts[0] . '/' . $parts[1] . '/embed/';
			$d['vertical'] = true;
			$d['thumb']    = 'https://www.instagram.com/p/' . $parts[1] . '/media/?size=l';
		}
	} elseif ( 'tiktok' === $p ) {
		$id = celb_tiktok_id( $url );
		if ( $id ) {
			$d['embed']    = 'https://www.tiktok.com/embed/v2/' . $id;
			$d['vertical'] = true;
			$d['thumb']    = celb_oembed_thumb( 'https://www.tiktok.com/oembed?url=' . rawurlencode( $url ) );
		}
	}
	return $d;
}
/* Fetch (and cache) a thumbnail_url from a provider oEmbed endpoint. */
function celb_oembed_thumb( $endpoint ) {
	$key    = 'celb_ot_' . md5( $endpoint );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$thumb = '';
	$resp  = wp_remote_get( $endpoint, array( 'timeout' => 6, 'headers' => array( 'Accept' => 'application/json' ) ) );
	if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $body['thumbnail_url'] ) ) {
			$thumb = esc_url_raw( $body['thumbnail_url'] );
		}
	}
	set_transient( $key, $thumb, 12 * HOUR_IN_SECONDS );
	return $thumb;
}
/* The whole Videos section: poster cards + a focused lightbox player. */
function celb_video_section_html( $post_id ) {
	$videos = celb_get_videos( $post_id );
	if ( empty( $videos ) ) {
		return '';
	}
	ob_start();
	echo '<section class="celb-section celb-videos"><h2 class="celb-section-title">' . esc_html( celb_videos_title( $post_id ) ) . '</h2>';
	echo '<div class="celb-video-grid">';
	foreach ( $videos as $v ) {
		$d    = celb_video_data( $v['url'] );
		if ( ! empty( $v['cover'] ) ) {
			$manual = wp_get_attachment_image_url( (int) $v['cover'], 'large' );
			if ( $manual ) {
				$d['thumb'] = $manual;
			}
		}
		$vert = $d['vertical'] ? ' is-vertical' : '';
		$name = $v['name'];
		$cap  = '<span class="celb-vcard-cap"><span class="celb-vplat">' . esc_html( celb_platform_label( $d['platform'] ) ) . '</span>'
			. ( $name ? '<span class="celb-vname">' . esc_html( $name ) . '</span>' : '' ) . '</span>';
		if ( '' === $d['embed'] ) {
			echo '<a class="celb-vcard' . $vert . '" href="' . esc_url( $d['href'] ) . '" target="_blank" rel="noopener noreferrer">'
				. '<span class="celb-vcard-poster"><span class="celb-vplay" aria-hidden="true">&#9654;</span></span>' . $cap . '</a>'; // phpcs:ignore
			continue;
		}
		$style = $d['thumb'] ? ' style="background-image:url(' . esc_url( $d['thumb'] ) . ')"' : '';
		echo '<button type="button" class="celb-vcard celb-vopen' . $vert . ( $d['thumb'] ? ' has-thumb' : '' ) . '" data-embed="' . esc_url( $d['embed'] ) . '" data-vertical="' . ( $d['vertical'] ? '1' : '0' ) . '">'
			. '<span class="celb-vcard-poster"' . $style . '><span class="celb-vplay" aria-hidden="true">&#9654;</span></span>' . $cap . '</button>'; // phpcs:ignore
	}
	echo '</div>';
	echo '<div class="celb-vlb" aria-hidden="true"><button type="button" class="celb-vlb-close" aria-label="Close">&times;</button><div class="celb-vlb-stage"></div></div>';
	echo '</section>';
	?>
	<script>
	(function(){
		var sec=document.currentScript.parentNode;if(!sec)return;
		var lb=sec.querySelector('.celb-vlb'),stage=lb.querySelector('.celb-vlb-stage');
		function open(embed,vert){stage.innerHTML='<div class="celb-vlb-frame'+(vert?' is-vertical':'')+'"><iframe src="'+embed+'" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen frameborder="0" scrolling="no"></iframe></div>';lb.classList.add('is-open');lb.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';}
		function close(){stage.innerHTML='';lb.classList.remove('is-open');lb.setAttribute('aria-hidden','true');document.body.style.overflow='';}
		sec.querySelectorAll('.celb-vopen').forEach(function(b){b.addEventListener('click',function(){open(b.getAttribute('data-embed'),b.getAttribute('data-vertical')==='1');});});
		lb.querySelector('.celb-vlb-close').addEventListener('click',close);
		lb.addEventListener('click',function(e){if(e.target===lb)close();});
		document.addEventListener('keydown',function(e){if(e.key==='Escape'&&lb.classList.contains('is-open'))close();});
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* =========================================================================
 * ALL-IN-ONE  — "Our Stars" standalone, MOBILE-ONLY page.
 * Own design (does NOT use the site theme). URL /?celb_page=stars.
 * Lead celebrities first, everyone else shuffled on each load.
 * Still available as shortcode [celb_all_in_one] if placed on a page.
 * ========================================================================= */
function celb_stars_url() {
	return home_url( '/' . celb_page_slug( 'stars_slug' ) . '/' );
}
add_shortcode( 'celb_all_in_one', 'celb_aio_shortcode' );
add_shortcode( 'CLEB_all_in_one', 'celb_aio_shortcode' );
function celb_aio_shortcode( $atts ) {
	return '<div class="celb-aio-embed-note" style="padding:24px;border:1px dashed #888;border-radius:10px;text-align:center;">'
		. esc_html__( 'The “Our Stars” page now has its own standalone address:', 'celb-mgmt' ) . '<br><a href="' . esc_url( celb_stars_url() ) . '">' . esc_html( celb_stars_url() ) . '</a></div>';
}

/* Ordered ids: Lead first, rest shuffled. */
function celb_aio_ordered_ids() {
	$ids = get_posts( array(
		'post_type'        => CELB_CPT,
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => false,
	) );
	$leads = array();
	$rest  = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_celb_lead', true ) ) {
			$leads[] = $id;
		} else {
			$rest[] = $id;
		}
	}
	shuffle( $leads );
	shuffle( $rest );
	return array_merge( $leads, $rest );
}

/* The standalone page. */
function celb_aio_standalone() {
	$s = celb_get_settings();
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=utf-8' );
	}
	$logo  = celb_logo_url();
	$title = ! empty( $s['aio_title'] ) ? $s['aio_title'] : __( 'Our Stars', 'celb-mgmt' );
	$intro = trim( (string) $s['aio_intro'] );

	echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head>';
	echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">';
	echo '<title>' . esc_html( $title . ' — ' . get_bloginfo( 'name' ) ) . '</title>';
	echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
	echo '<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,400;6..96,500&family=Inter:wght@400;500;600&family=Space+Mono&display=swap" rel="stylesheet">';
	echo '<style>' . celb_aio_sa_css() . '</style>'; // phpcs:ignore
	echo celb_custom_theme_head_html(); // phpcs:ignore
	echo '</head><body class="celb-stars">';

	if ( empty( $s['aio_enabled'] ) ) {
		echo '<div class="celb-stars-desktop"><div class="celb-stars-msg-inner">';
		if ( $logo ) { echo '<img class="celb-stars-logo" src="' . esc_url( $logo ) . '" alt="">'; }
		echo '<p>' . esc_html__( 'This page is not available right now.', 'celb-mgmt' ) . '</p></div></div></body></html>';
		exit;
	}

	// Desktop / tablet: ask to open on mobile (shown via CSS on wide screens).
	echo '<div class="celb-stars-desktop"><div class="celb-stars-msg-inner">';
	if ( $logo ) { echo '<img class="celb-stars-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">'; }
	echo '<span class="celb-stars-eyebrow">' . esc_html( $title ) . '</span>';
	echo '<p class="celb-stars-msg">' . esc_html__( 'This page is designed for mobile. Please open it on your phone for the best experience.', 'celb-mgmt' ) . '</p>';
	echo '</div></div>';

	// Mobile content.
	echo '<div class="celb-stars-content">';
	echo '<header class="celb-aio-head">';
	if ( $logo ) { echo '<img class="celb-aio-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">'; }
	echo '<h1 class="celb-aio-h1">' . esc_html( $title ) . '</h1>';
	if ( '' !== $intro ) { echo '<p class="celb-aio-intro">' . esc_html( $intro ) . '</p>'; }
	echo '<span class="celb-aio-rule" aria-hidden="true"></span>';
	echo '</header>';

	$ordered = celb_aio_ordered_ids();
	if ( empty( $ordered ) ) {
		echo '<p class="celb-aio-empty">' . esc_html__( 'No profiles to show yet.', 'celb-mgmt' ) . '</p>';
	} else {
		echo '<div class="celb-aio-list">';
		foreach ( $ordered as $id ) {
			echo celb_aio_card( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	$cta_url   = ! empty( $s['aio_cta_url'] ) ? $s['aio_cta_url'] : ( ! empty( $s['cta_url'] ) ? $s['cta_url'] : '' );
	$cta_label = ! empty( $s['aio_cta_label'] ) ? $s['aio_cta_label'] : __( 'View Our Roster', 'celb-mgmt' );
	if ( '' !== $cta_url && '' !== $cta_label ) {
		echo '<div class="celb-aio-cta"><a class="celb-aio-cta-btn" href="' . esc_url( $cta_url ) . '">' . esc_html( $cta_label ) . '</a></div>';
	}
	echo '</div>'; // .celb-stars-content
	echo '</body></html>';
	exit;
}

function celb_aio_card( $id ) {
	$name    = get_the_title( $id );
	$sub     = celb_subtitle( $id );
	$bio     = wp_trim_words( wp_strip_all_tags( celb_get_bio( $id ) ), 46, '…' );
	$link    = get_permalink( $id );
	$socials = celb_get_socials( $id );

	$hero = (int) get_post_meta( $id, '_celb_hero_desktop', true );
	$img  = $hero ? wp_get_attachment_image_url( $hero, 'large' ) : '';
	if ( ! $img ) {
		$img = get_the_post_thumbnail_url( $id, 'large' );
	}
	if ( ! $img ) {
		$prof = (int) get_post_meta( $id, '_celb_profile', true );
		$img  = $prof ? wp_get_attachment_image_url( $prof, 'large' ) : '';
	}

	ob_start();
	echo '<article class="celb-aio-card">';
	echo '<div class="celb-aio-namerow">';
	echo '<h2 class="celb-aio-name">' . esc_html( $name ) . '</h2>';
	echo '<a class="celb-aio-view" href="' . esc_url( $link ) . '">' . esc_html__( 'View Profile', 'celb-mgmt' ) . '</a>';
	echo '</div>';
	if ( '' !== $sub ) {
		echo '<div class="celb-aio-role">' . esc_html( $sub ) . '</div>';
	}
	if ( '' !== $bio ) {
		echo '<p class="celb-aio-bio">' . esc_html( $bio ) . '</p>';
	}
	echo '<div class="celb-aio-imgbox">';
	if ( $img ) {
		echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
	} else {
		echo '<span class="celb-aio-mono">' . esc_html( celb_monogram( $name ) ) . '</span>';
	}
	echo '</div>';
	if ( $socials ) {
		echo '<div class="celb-aio-socials">';
		foreach ( $socials as $k => $soc ) {
			echo '<a class="celb-aio-icon" href="' . esc_url( $soc['url'] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $soc['label'] ) . '">' . celb_social_icon( $k ) . '</a>'; // phpcs:ignore
		}
		echo '</div>';
	}
	echo '</article>';
	return ob_get_clean();
}

function celb_aio_sa_css() {
	return '
	*{box-sizing:border-box}
	html,body.celb-stars{margin:0;padding:0}
	body.celb-stars{background:#000;color:#f4f1ea;font-family:"Inter",system-ui,-apple-system,sans-serif;min-height:100vh;-webkit-font-smoothing:antialiased;line-height:1.6}
	/* Desktop / tablet notice (shown on wide screens only) */
	.celb-stars-desktop{display:none}
	.celb-stars-content{display:block}
	@media (min-width:768px){
		.celb-stars-content{display:none}
		.celb-stars-desktop{display:flex;min-height:100vh;align-items:center;justify-content:center;padding:40px;text-align:center}
	}
	.celb-stars-msg-inner{max-width:440px}
	.celb-stars-logo{max-height:56px;width:auto;margin:0 auto 26px;display:block}
	.celb-stars-eyebrow{display:block;font-family:"Bodoni Moda",Georgia,serif;font-size:2rem;margin-bottom:16px;color:#f4f1ea}
	.celb-stars-msg{font-size:1.05rem;line-height:1.7;color:#9a978f}
	/* Mobile content */
	.celb-stars-content{padding:34px 20px 70px}
	.celb-aio-head{text-align:center;margin:0 auto 44px;max-width:640px}
	.celb-aio-logo{max-height:52px;width:auto;margin:14px auto 26px;display:block}
	.celb-aio-h1{font-family:"Bodoni Moda",Georgia,serif;font-weight:500;font-size:clamp(2.4rem,11vw,3.4rem);line-height:1;margin:0 0 22px;color:#f4f1ea}
	.celb-aio-intro{font-family:"Inter",sans-serif;font-size:1.02rem;line-height:1.7;color:#f4f1ea;margin:0;text-align:center}
	.celb-aio-rule{display:block;width:96px;height:1px;background:rgba(244,241,234,.4);margin:30px auto 0}
	.celb-aio-empty{text-align:center;color:#8b8b86}
	.celb-aio-list{display:flex;flex-direction:column;gap:64px;max-width:560px;margin:0 auto}
	.celb-aio-card{margin:0}
	.celb-aio-namerow{display:flex;align-items:baseline;justify-content:space-between;gap:14px}
	.celb-aio-name{font-family:"Bodoni Moda",Georgia,serif;font-weight:500;font-size:clamp(1.9rem,8vw,2.6rem);line-height:1.02;margin:0;color:#f4f1ea}
	.celb-aio-view{flex:0 0 auto;font-family:"Inter",sans-serif;font-size:.95rem;color:#c8c4bc;text-decoration:none;white-space:nowrap}
	.celb-aio-view:hover{color:#fff}
	.celb-aio-role{margin-top:10px;font-family:"Inter",sans-serif;font-size:.95rem;letter-spacing:.03em;text-transform:uppercase;color:#8f8b83}
	.celb-aio-bio{margin:20px 0 0;font-family:"Inter",sans-serif;font-size:1rem;line-height:1.85;color:#b8b4ac;text-align:justify;text-justify:inter-word}
	.celb-aio-imgbox{position:relative;width:100%;margin-top:24px;border-radius:20px;overflow:hidden;background:#111}
	.celb-aio-imgbox::before{content:"";display:block;padding-top:120%}
	.celb-aio-imgbox img{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover!important;object-position:center 22%;display:block}
	.celb-aio-mono{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:"Bodoni Moda",Georgia,serif;font-size:4rem;color:rgba(244,241,234,.28);background:linear-gradient(160deg,#17140f,#0a0a0a)}
	.celb-aio-socials{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
	.celb-aio-icon{width:44px;height:44px;border:1px solid rgba(244,241,234,.22);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#f4f1ea;text-decoration:none}
	.celb-aio-icon:hover{border-color:rgba(244,241,234,.55);background:rgba(244,241,234,.06)}
	.celb-aio-icon svg{width:17px;height:17px;fill:currentColor}
	.celb-aio-cta{text-align:center;margin:72px auto 0;max-width:560px}
	.celb-aio-cta-btn{display:block;font-family:"Space Mono",monospace;font-size:.78rem;letter-spacing:.18em;text-transform:uppercase;color:#f4f1ea;border:1px solid rgba(244,241,234,.6);padding:18px 32px;text-decoration:none;transition:background .3s ease,color .3s ease}
	.celb-aio-cta-btn:hover{background:#f4f1ea;color:#000}
	';
}

/* =========================================================================
 * RATE CARD — Templates, Duplication & Self-Onboarding
 * ---------------------------------------------------------------------------
 * - Duplicate a card (full / empty rates), save a card as a reusable Template.
 * - Generate a private link per celebrity to collect their rates.
 * - Celebrity fills name + a price per service; submission saved.
 * - Import a submission into a new or existing rate card in one click.
 * ========================================================================= */

/* Submissions CPT (hidden). */
function celb_rateonb_register() {
	register_post_type( 'celb_rateonb', array(
		'public'          => false,
		'show_ui'         => false,
		'show_in_menu'    => false,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
	) );
}
add_action( 'init', 'celb_rateonb_register' );

/* Is this rate card a reusable template? */
function celb_rate_is_template( $card_id ) {
	return get_post_meta( $card_id, '_rate_is_template', true ) === '1';
}

/* All rate cards, split into live cards + templates: id => label. */
function celb_rate_all_cards( $which = 'all' ) {
	$ids = get_posts( array(
		'post_type'        => CELB_RATE_CPT,
		'post_status'      => array( 'publish', 'draft' ),
		'numberposts'      => -1,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	$cards = array();
	$tpls  = array();
	foreach ( $ids as $id ) {
		$label = get_the_title( $id );
		$celeb = (int) get_post_meta( $id, '_rate_celeb', true );
		if ( $celeb ) {
			$label .= ' — ' . get_the_title( $celeb );
		}
		if ( celb_rate_is_template( $id ) ) {
			$tpls[ $id ] = $label;
		} else {
			$cards[ $id ] = $label;
		}
	}
	if ( 'templates' === $which ) { return $tpls; }
	if ( 'cards' === $which ) { return $cards; }
	return array( 'cards' => $cards, 'templates' => $tpls );
}

/* Reset every service's base/extra price in a sections array. */
function celb_rate_reset_sections_prices( $sections ) {
	if ( ! is_array( $sections ) ) { return array(); }
	foreach ( $sections as &$sec ) {
		if ( ! empty( $sec['items'] ) && is_array( $sec['items'] ) ) {
			foreach ( $sec['items'] as &$it ) {
				$it['base'] = '';
				$it['addl'] = '';
			}
			unset( $it );
		}
	}
	unset( $sec );
	return $sections;
}

/* Clone a rate card. $args: title, reset_prices, as_template, celeb, enabled. */
function celb_rate_clone( $src, $args = array() ) {
	if ( get_post_type( $src ) !== CELB_RATE_CPT ) { return 0; }
	$args = wp_parse_args( $args, array(
		'title'        => '',
		'reset_prices' => false,
		'as_template'  => false,
		'celeb'        => 0,
		'enabled'      => false,
	) );
	$title = '' !== $args['title'] ? $args['title'] : get_the_title( $src ) . ' (copy)';
	$new   = wp_insert_post( array(
		'post_type'   => CELB_RATE_CPT,
		'post_status' => 'publish',
		'post_title'  => $title,
	) );
	if ( is_wp_error( $new ) || ! $new ) { return 0; }

	$config   = get_post_meta( $src, '_rate_config', true );
	$sections = celb_rate_sections_get( $src );
	$social   = celb_rate_social_get( $src );
	if ( $args['reset_prices'] ) {
		$sections = celb_rate_reset_sections_prices( $sections );
		$social   = array();
	}
	if ( is_array( $config ) ) {
		update_post_meta( $new, '_rate_config', $config );
	}
	update_post_meta( $new, '_rate_sections', $sections );
	update_post_meta( $new, '_rate_social', $social );
	update_post_meta( $new, '_rate_celeb', (int) $args['celeb'] );
	update_post_meta( $new, '_rate_enabled', $args['enabled'] ? '1' : '' );
	update_post_meta( $new, '_rate_pw', $args['as_template'] ? '' : (string) get_post_meta( $src, '_rate_pw', true ) );
	if ( $args['as_template'] ) {
		update_post_meta( $new, '_rate_is_template', '1' );
	}
	celb_rate_build_slug_map();
	return (int) $new;
}

/* ---- Row actions on the Rate Cards list ---- */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( CELB_RATE_CPT !== $post->post_type || ! current_user_can( 'manage_options' ) ) {
		return $actions;
	}
	$n    = wp_create_nonce( 'celb_rate_rowact_' . $post->ID );
	$base = admin_url( 'admin-post.php' );
	$actions['celb_dup']       = '<a href="' . esc_url( add_query_arg( array( 'action' => 'celb_rate_dup', 'card' => $post->ID, '_wpnonce' => $n ), $base ) ) . '">' . esc_html__( 'Duplicate', 'celb-mgmt' ) . '</a>';
	$actions['celb_dup_empty'] = '<a href="' . esc_url( add_query_arg( array( 'action' => 'celb_rate_dup', 'card' => $post->ID, 'empty' => 1, '_wpnonce' => $n ), $base ) ) . '">' . esc_html__( 'New (empty rates)', 'celb-mgmt' ) . '</a>';
	$actions['celb_tpl']       = '<a href="' . esc_url( add_query_arg( array( 'action' => 'celb_rate_tpl', 'card' => $post->ID, '_wpnonce' => $n ), $base ) ) . '">' . esc_html__( 'Save as Template', 'celb-mgmt' ) . '</a>';
	return $actions;
}, 10, 2 );

add_action( 'admin_post_celb_rate_dup', function () {
	$card = isset( $_GET['card'] ) ? absint( $_GET['card'] ) : 0;
	if ( ! $card || ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_rate_rowact_' . $card ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	$empty = ! empty( $_GET['empty'] );
	$title = get_the_title( $card ) . ( $empty ? ' (new — empty rates)' : ' (copy)' );
	$new   = celb_rate_clone( $card, array( 'title' => $title, 'reset_prices' => $empty ) );
	$dest  = $new ? get_edit_post_link( $new, 'raw' ) : admin_url( 'edit.php?post_type=' . CELB_RATE_CPT );
	wp_safe_redirect( $dest );
	exit;
} );

add_action( 'admin_post_celb_rate_tpl', function () {
	$card = isset( $_GET['card'] ) ? absint( $_GET['card'] ) : 0;
	if ( ! $card || ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_rate_rowact_' . $card ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	$new = celb_rate_clone( $card, array( 'title' => get_the_title( $card ) . ' (Template)', 'as_template' => true ) );
	wp_safe_redirect( $new ? get_edit_post_link( $new, 'raw' ) : admin_url( 'edit.php?post_type=' . CELB_RATE_CPT ) );
	exit;
} );

/* Show a "Template" tag in the list title. */
add_filter( 'display_post_states', function ( $states, $post ) {
	if ( CELB_RATE_CPT === $post->post_type && celb_rate_is_template( $post->ID ) ) {
		$states['celb_tpl'] = __( 'Template', 'celb-mgmt' );
	}
	return $states;
}, 10, 2 );

/* ---- Onboarding links (token => src card, celeb) ---- */
function celb_rate_onb_links() {
	$l = get_option( 'celb_rate_onb_links', array() );
	return is_array( $l ) ? $l : array();
}
function celb_rate_onb_url( $token ) {
	return home_url( '/' . celb_page_slug( 'rateonb_slug' ) . '/' . rawurlencode( $token ) . '/' );
}

add_action( 'admin_post_celb_rate_onb_gen', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'celb_rate_onb_gen' ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	$src   = isset( $_POST['src'] ) ? absint( $_POST['src'] ) : 0;
	$celeb = isset( $_POST['celeb'] ) ? absint( $_POST['celeb'] ) : 0;
	if ( $src && get_post_type( $src ) === CELB_RATE_CPT ) {
		$links = celb_rate_onb_links();
		$want  = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		if ( '' !== $want ) {
			$token = $want;
			$n     = 2;
			while ( isset( $links[ $token ] ) ) {
				$token = $want . '-' . $n;
				$n++;
			}
		} else {
			$token = strtolower( wp_generate_password( 16, false ) );
		}
		$links[ $token ] = array( 'src' => $src, 'celeb' => $celeb, 'created' => time() );
		update_option( 'celb_rate_onb_links', $links, false );
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-rate-onb&generated=1' ) );
	exit;
} );

add_action( 'admin_post_celb_rate_onb_revoke', function () {
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_rate_onb_revoke_' . $token ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	$links = celb_rate_onb_links();
	unset( $links[ $token ] );
	update_option( 'celb_rate_onb_links', $links, false );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-rate-onb' ) );
	exit;
} );

add_action( 'admin_post_celb_rate_onb_del', function () {
	$sub = isset( $_GET['sub'] ) ? absint( $_GET['sub'] ) : 0;
	if ( ! $sub || ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_rate_onb_del_' . $sub ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	wp_delete_post( $sub, true );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-rate-onb' ) );
	exit;
} );

add_action( 'admin_post_celb_rate_onb_import', function () {
	$sub = isset( $_POST['sub'] ) ? absint( $_POST['sub'] ) : 0;
	if ( ! $sub || ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'celb_rate_onb_import_' . $sub ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	$sections = get_post_meta( $sub, '_onb_sections', true );
	$src      = (int) get_post_meta( $sub, '_onb_src', true );
	$celeb    = (int) get_post_meta( $sub, '_onb_celeb', true );
	$target   = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : 'new';
	if ( ! is_array( $sections ) ) {
		$sections = array();
	}
	$dest = admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-rate-onb' );
	if ( 'new' === $target ) {
		$new = celb_rate_clone( $src, array(
			'title'   => get_the_title( $sub ) . ' — Rate Card',
			'celeb'   => $celeb,
			'enabled' => false,
		) );
		if ( $new ) {
			update_post_meta( $new, '_rate_sections', $sections );
			update_post_meta( $sub, '_onb_imported', $new );
			$dest = get_edit_post_link( $new, 'raw' );
		}
	} else {
		$tid = absint( $target );
		if ( $tid && get_post_type( $tid ) === CELB_RATE_CPT ) {
			update_post_meta( $tid, '_rate_sections', celb_rate_merge_prices( celb_rate_sections_get( $tid ), $sections ) );
			update_post_meta( $sub, '_onb_imported', $tid );
			$dest = get_edit_post_link( $tid, 'raw' );
		}
	}
	wp_safe_redirect( $dest );
	exit;
} );

/* ---- Admin page: includes/admin-workspace.php ---- */

/* ---- Front: the self-onboarding form (standalone) ---- */
function celb_rate_onb_front() {
	$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
	if ( '' === $token ) {
		$token = sanitize_text_field( (string) get_query_var( 'celb_t' ) );
	}
	$links = celb_rate_onb_links();
	if ( '' === $token || ! isset( $links[ $token ] ) ) {
		celb_sa_shell( __( 'Rate Card', 'celb-mgmt' ), '<p style="text-align:center;">' . esc_html__( 'This link is no longer available.', 'celb-mgmt' ) . '</p>', get_bloginfo( 'name' ) );
		return;
	}
	$src   = (int) $links[ $token ]['src'];
	$celeb = (int) $links[ $token ]['celeb'];
	if ( get_post_type( $src ) !== CELB_RATE_CPT ) {
		celb_sa_shell( __( 'Rate Card', 'celb-mgmt' ), '<p style="text-align:center;">' . esc_html__( 'This link is no longer available.', 'celb-mgmt' ) . '</p>', get_bloginfo( 'name' ) );
		return;
	}
	$cfg      = celb_rate_get( $src );
	$currency = isset( $cfg['currency'] ) ? $cfg['currency'] : 'EGP';
	$sections = celb_rate_sections_get( $src );
	$plats    = celb_rate_platforms();

	// Handle submission.
	if ( isset( $_POST['celb_onb_submit'] ) && isset( $_POST['celb_onb_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['celb_onb_nonce'] ), 'celb_onb_' . $token ) ) {
		$name = isset( $_POST['onb_name'] ) ? sanitize_text_field( wp_unslash( $_POST['onb_name'] ) ) : '';
		if ( '' === $name && $celeb ) {
			$name = get_the_title( $celeb );
		}
		$prices = isset( $_POST['price'] ) && is_array( $_POST['price'] ) ? wp_unslash( $_POST['price'] ) : array();
		$filled = $sections;
		foreach ( $filled as $si => &$sec ) {
			if ( empty( $sec['items'] ) || ! is_array( $sec['items'] ) ) { continue; }
			foreach ( $sec['items'] as $ii => &$it ) {
				$b = isset( $prices[ $si ][ $ii ]['base'] ) ? (float) $prices[ $si ][ $ii ]['base'] : 0;
				$a = isset( $prices[ $si ][ $ii ]['addl'] ) ? (float) $prices[ $si ][ $ii ]['addl'] : 0;
				$it['base'] = $b > 0 ? $b : 0;
				$it['addl'] = $a > 0 ? $a : 0;
			}
			unset( $it );
		}
		unset( $sec );
		$sub = wp_insert_post( array(
			'post_type'   => 'celb_rateonb',
			'post_status' => 'publish',
			'post_title'  => $name ? $name : __( 'Untitled', 'celb-mgmt' ),
		) );
		if ( $sub && ! is_wp_error( $sub ) ) {
			update_post_meta( $sub, '_onb_token', $token );
			update_post_meta( $sub, '_onb_src', $src );
			update_post_meta( $sub, '_onb_celeb', $celeb );
			update_post_meta( $sub, '_onb_currency', $currency );
			update_post_meta( $sub, '_onb_sections', $filled );
			update_post_meta( $sub, '_onb_created', time() );
			$st = celb_get_settings();
			$to = ! empty( $st['contact_recipient'] ) ? $st['contact_recipient'] : get_option( 'admin_email' );
			if ( function_exists( 'celb_send_notice' ) ) {
				celb_send_notice(
					$to,
					sprintf( __( 'New rate card submission: %s', 'celb-mgmt' ), $name ),
					__( 'New rate card submission', 'celb-mgmt' ),
					array(
						__( 'Talent', 'celb-mgmt' )   => $name,
						__( 'Based on', 'celb-mgmt' ) => get_the_title( $src ),
						__( 'Review', 'celb-mgmt' )   => admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-rate-onb' ),
					),
					__( 'A talent has submitted their rates. Open Rate Onboarding to import them into a rate card.', 'celb-mgmt' )
				);
			}
		}
		$done = '<div class="celb-onb-done"><h2>' . esc_html__( 'Thank you!', 'celb-mgmt' ) . '</h2><p>' . esc_html__( 'Your rates have been submitted to the agency. You can close this page.', 'celb-mgmt' ) . '</p></div>';
		celb_sa_shell( __( 'Rate Card', 'celb-mgmt' ), $done, get_bloginfo( 'name' ), __( 'Submitted', 'celb-mgmt' ) );
		return;
	}

	// Build the form.
	$pref = $celeb ? get_the_title( $celeb ) : '';
	ob_start();
	?>
	<style>
	.celb-onb-form{max-width:100%}
	.celb-onb-name{margin-bottom:8px}
	.celb-onb-name label,.celb-onb-svc-price label{display:block;font-family:'Space Mono',monospace;font-size:.62rem;letter-spacing:.16em;text-transform:uppercase;color:#8b8b86;margin-bottom:8px}
	.celb-onb-input{width:100%;background:#fff;border:1px solid #ddd;border-radius:8px;padding:13px 14px;font-size:1rem;color:#111}
	.celb-onb-step2{margin-top:26px}
	.celb-onb-hint{font-size:.9rem;color:#9a978f;margin:0 0 22px}
	.celb-onb-sec-title{font-family:'Bodoni Moda',Georgia,serif;font-size:1.35rem;margin:26px 0 4px;color:#f4f1ea}
	.celb-onb-svc{border:1px solid rgba(244,241,234,.14);border-radius:12px;padding:16px 16px 6px;margin-top:14px}
	.celb-onb-svc-head{display:flex;justify-content:space-between;gap:12px;align-items:baseline;flex-wrap:wrap}
	.celb-onb-svc-name{font-size:1.05rem;font-weight:600;color:#f4f1ea}
	.celb-onb-svc-plats{font-family:'Space Mono',monospace;font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;color:#8b8b86}
	.celb-onb-svc-desc{font-size:.9rem;color:#9a978f;margin:4px 0 12px}
	.celb-onb-price-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px}
	.celb-onb-price{flex:1 1 160px}
	.celb-onb-cur{font-family:'Space Mono',monospace;font-size:.7rem;color:#8b8b86}
	.celb-onb-submit{margin-top:28px}
	.celb-onb-btn{display:inline-block;width:100%;text-align:center;font-family:'Space Mono',monospace;font-size:.8rem;letter-spacing:.16em;text-transform:uppercase;color:#000;background:#f4f1ea;border:0;padding:16px 28px;border-radius:8px;cursor:pointer}
	.celb-onb-btn.ghost{background:transparent;color:#f4f1ea;border:1px solid rgba(244,241,234,.6)}
	.celb-onb-done{text-align:center}
	.celb-onb-done h2{font-family:'Bodoni Moda',Georgia,serif;font-weight:500}
	</style>
	<form class="celb-onb-form" method="post">
		<?php wp_nonce_field( 'celb_onb_' . $token, 'celb_onb_nonce' ); ?>
		<div class="celb-onb-step1">
			<div class="celb-onb-name">
				<label for="onb_name"><?php esc_html_e( 'Your name', 'celb-mgmt' ); ?></label>
				<input class="celb-onb-input" type="text" id="onb_name" name="onb_name" value="<?php echo esc_attr( $pref ); ?>" placeholder="<?php esc_attr_e( 'Full name', 'celb-mgmt' ); ?>" required />
			</div>
			<div class="celb-onb-submit">
				<button type="button" class="celb-onb-btn celb-onb-continue"><?php esc_html_e( 'Continue', 'celb-mgmt' ); ?></button>
			</div>
		</div>
		<div class="celb-onb-step2" style="display:none;">
			<p class="celb-onb-hint"><?php printf( esc_html__( 'Enter your rate for each service below (in %s). Leave anything blank if it doesn’t apply.', 'celb-mgmt' ), esc_html( $currency ) ); ?></p>
			<?php foreach ( $sections as $si => $sec ) :
				$items = ! empty( $sec['items'] ) && is_array( $sec['items'] ) ? $sec['items'] : array();
				if ( empty( $items ) ) { continue; }
				?>
				<?php if ( ! empty( $sec['title'] ) ) : ?><div class="celb-onb-sec-title"><?php echo esc_html( $sec['title'] ); ?></div><?php endif; ?>
				<?php foreach ( $items as $ii => $it ) :
					$it = wp_parse_args( $it, array( 'name' => '', 'desc' => '', 'platforms' => array() ) );
					$plabel = celb_rate_platlabel( is_array( $it['platforms'] ) ? $it['platforms'] : array() );
					?>
					<div class="celb-onb-svc">
						<div class="celb-onb-svc-head">
							<span class="celb-onb-svc-name"><?php echo esc_html( $it['name'] ); ?></span>
							<span class="celb-onb-svc-plats"><?php echo esc_html( $plabel ); ?></span>
						</div>
						<?php if ( ! empty( $it['desc'] ) ) : ?><div class="celb-onb-svc-desc"><?php echo esc_html( $it['desc'] ); ?></div><?php endif; ?>
						<div class="celb-onb-price-row">
							<div class="celb-onb-price celb-onb-svc-price">
								<label><?php esc_html_e( 'Price', 'celb-mgmt' ); ?> <span class="celb-onb-cur">(<?php echo esc_html( $currency ); ?>)</span></label>
								<input class="celb-onb-input" type="number" min="0" step="any" name="price[<?php echo esc_attr( $si ); ?>][<?php echo esc_attr( $ii ); ?>][base]" placeholder="0" />
							</div>
							<div class="celb-onb-price celb-onb-svc-price">
								<label><?php esc_html_e( 'Extra (each)', 'celb-mgmt' ); ?> <span class="celb-onb-cur"><?php esc_html_e( 'optional', 'celb-mgmt' ); ?></span></label>
								<input class="celb-onb-input" type="number" min="0" step="any" name="price[<?php echo esc_attr( $si ); ?>][<?php echo esc_attr( $ii ); ?>][addl]" placeholder="0" />
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<div class="celb-onb-submit">
				<button type="submit" name="celb_onb_submit" value="1" class="celb-onb-btn"><?php esc_html_e( 'Submit my rates', 'celb-mgmt' ); ?></button>
			</div>
		</div>
	</form>
	<script>
	(function(){
		var f=document.querySelector('.celb-onb-form');if(!f)return;
		var s1=f.querySelector('.celb-onb-step1'),s2=f.querySelector('.celb-onb-step2'),go=f.querySelector('.celb-onb-continue'),nm=f.querySelector('#onb_name');
		go.addEventListener('click',function(){if(!nm.value.trim()){nm.focus();nm.reportValidity&&nm.reportValidity();return;}s1.style.display='none';s2.style.display='block';window.scrollTo({top:0,behavior:'smooth'});});
	})();
	</script>
	<?php
	$body = ob_get_clean();
	celb_sa_shell( __( 'Your Rate Card', 'celb-mgmt' ), $body, get_bloginfo( 'name' ), __( 'Tell us your rates', 'celb-mgmt' ) );
}

/* =========================================================================
 * CUSTOM THEME overrides + rate-card price merge (v2.7.2)
 * ========================================================================= */
/* Allow only safe characters in a CSS font-family value. */
function celb_sanitize_font_family( $v ) {
	$v = wp_strip_all_tags( (string) $v );
	$v = preg_replace( '/[^A-Za-z0-9 ,\'"\-_]/', '', $v );
	return trim( substr( $v, 0, 200 ) );
}
/* CSS variables for the theme-embedded parts. Empty settings = inherit theme. */
function celb_custom_theme_vars_css( $scope ) {
	$s    = celb_get_settings();
	$vars = '';
	if ( ! empty( $s['font_body'] ) ) {
		$vars .= '--celb-font-body:' . $s['font_body'] . ';font-family:' . $s['font_body'] . ';';
	}
	if ( ! empty( $s['font_display'] ) ) {
		$vars .= '--celb-font-display:' . $s['font_display'] . ';';
	}
	if ( ! empty( $s['text_color'] ) ) {
		$vars .= '--celb-text:' . $s['text_color'] . ';--rose-ink:' . $s['text_color'] . ';';
	}
	if ( ! empty( $s['bg_color'] ) ) {
		$vars .= '--celb-bg:' . $s['bg_color'] . ';--rose-bg:' . $s['bg_color'] . ';';
	}
	return '' !== $vars ? $scope . '{' . $vars . '}' : '';
}
/* Head markup for the standalone pages (they have no theme to inherit). */
function celb_custom_theme_head_html() {
	$s   = celb_get_settings();
	$out = '';
	if ( ! empty( $s['font_url'] ) ) {
		$out .= '<link rel="stylesheet" href="' . esc_url( $s['font_url'] ) . '">';
	}
	$css = '';
	if ( ! empty( $s['font_body'] ) ) {
		$css .= 'body,input,select,textarea,button{font-family:' . $s['font_body'] . ' !important;}';
	}
	if ( ! empty( $s['font_display'] ) ) {
		$css .= 'h1,h2,h3,.celb-sa-title,.celb-aio-h1,.celb-aio-name,.celb-onb-sec-title{font-family:' . $s['font_display'] . ' !important;}';
	}
	if ( '' !== $css ) {
		$out .= '<style>' . $css . '</style>';
	}
	return $out;
}
/*
 * Merge submitted prices into an existing card WITHOUT replacing its structure.
 * Services are matched by section title + service name (case-insensitive).
 * Services the talent left blank keep their current price; unmatched services
 * in the target card are left untouched.
 */
function celb_rate_merge_prices( $target, $submitted ) {
	if ( ! is_array( $target ) ) { return is_array( $submitted ) ? $submitted : array(); }
	$map = array();
	foreach ( (array) $submitted as $sec ) {
		$st = strtolower( trim( isset( $sec['title'] ) ? $sec['title'] : '' ) );
		foreach ( ( isset( $sec['items'] ) && is_array( $sec['items'] ) ) ? $sec['items'] : array() as $it ) {
			$nm = strtolower( trim( isset( $it['name'] ) ? $it['name'] : '' ) );
			if ( '' === $nm ) { continue; }
			$p = array( 'base' => isset( $it['base'] ) ? (float) $it['base'] : 0, 'addl' => isset( $it['addl'] ) ? (float) $it['addl'] : 0 );
			$map[ $st . '|' . $nm ] = $p;
			if ( ! isset( $map[ '*|' . $nm ] ) ) {
				$map[ '*|' . $nm ] = $p;
			}
		}
	}
	foreach ( $target as &$sec ) {
		$st = strtolower( trim( isset( $sec['title'] ) ? $sec['title'] : '' ) );
		if ( empty( $sec['items'] ) || ! is_array( $sec['items'] ) ) { continue; }
		foreach ( $sec['items'] as &$it ) {
			$nm  = strtolower( trim( isset( $it['name'] ) ? $it['name'] : '' ) );
			$hit = isset( $map[ $st . '|' . $nm ] ) ? $map[ $st . '|' . $nm ] : ( isset( $map[ '*|' . $nm ] ) ? $map[ '*|' . $nm ] : null );
			if ( ! $hit ) { continue; }
			if ( $hit['base'] > 0 ) { $it['base'] = $hit['base']; }
			if ( $hit['addl'] > 0 ) { $it['addl'] = $hit['addl']; }
		}
		unset( $it );
	}
	unset( $sec );
	return $target;
}

/* Studio UI for the operations screens (loaded last: it uses CELB_RATE_CPT). */
require_once CELB_PATH . 'includes/admin-workspace.php';
