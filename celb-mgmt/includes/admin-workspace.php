<?php
/**
 * CELB MGMT — Studio UI for the operations screens.
 *
 *   Lists:   Projects, Schedule, Requests (bookings), Contracts,
 *            Contract Templates, Rate Cards, Newsroom
 *   Editors: Schedule entry, Contract, Contract Template
 *            (Projects, Requests and Rate Cards get the shared editor skin)
 *   Pages:   Rate Card Onboarding, Personal Data form builder,
 *            Personal Data submissions
 *
 * Presentation only: every field keeps the name the existing save handlers
 * in celb-mgmt.php read, and stored data is unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 0. ROUTING + SHARED PIECES
 * ====================================================================== */

/* post type => array( list screen key, editor screen key ). */
function celb_ws_types() {
	return array(
		'celb_project'  => array( 'projects', 'project' ),
		'celb_sched'    => array( 'schedule', 'sched' ),
		'celb_request'  => array( 'bookings', 'booking' ),
		'celb_contract' => array( 'contracts', 'contract' ),
		'celb_ctpl'     => array( 'templates', 'template' ),
		CELB_RATE_CPT   => array( 'ratecards', 'ratecard' ),
		'celeb_news'    => array( 'newslist', '' ), // the editor lives in admin-studio.php
	);
}
function celb_ws_pages() {
	return array( 'celb-rate-onb' => 'rateonb', 'celb-pdata-form' => 'pdbuilder', 'celb-pdata' => 'pdsubs' );
}
function celb_ws_list_screens() {
	return array( 'projects', 'schedule', 'bookings', 'contracts', 'templates', 'ratecards', 'newslist' );
}
function celb_ws_editor_screens() {
	return array( 'project', 'sched', 'booking', 'contract', 'template', 'ratecard' );
}

add_filter( 'celb_studio_screen', function ( $which, $screen ) {
	if ( $which ) {
		return $which;
	}
	if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$pages = celb_ws_pages();
		$p     = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $pages[ $p ] ) ) {
			return $pages[ $p ];
		}
	}
	$types = celb_ws_types();
	if ( isset( $types[ $screen->post_type ] ) ) {
		if ( 'edit' === $screen->base ) {
			return $types[ $screen->post_type ][0];
		}
		if ( 'post' === $screen->base && $types[ $screen->post_type ][1] ) {
			return $types[ $screen->post_type ][1];
		}
	}
	return '';
}, 20, 2 );

add_filter( 'admin_body_class', function ( $classes ) {
	$w = celb_studio_screen();
	if ( in_array( $w, celb_ws_list_screens(), true ) ) {
		$classes .= ' cs-screen-list ';
	} elseif ( in_array( $w, celb_ws_editor_screens(), true ) ) {
		$classes .= ' cs-screen-editor ';
	} elseif ( in_array( $w, celb_ws_pages(), true ) ) {
		$classes .= ' cs-screen-page ';
	}
	return $classes;
}, 20 );

/* Page header: eyebrow, title, subline, actions and filter tiles. */
function celb_ws_hero( $title, $sub, $tiles = array(), $actions = '' ) {
	echo '<div class="cs-hero"><div class="cs-hero-top"><div>';
	echo '<p class="cs-eyebrow">' . esc_html__( 'CELB MGMT', 'celb-mgmt' ) . '</p>';
	echo '<h1 class="cs-hero-title">' . esc_html( $title ) . '</h1>';
	if ( $sub ) {
		echo '<p class="cs-hero-sub">' . wp_kses_post( $sub ) . '</p>';
	}
	echo '</div>';
	if ( $actions ) {
		echo '<div class="cs-hero-actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	if ( $tiles ) {
		echo '<div class="cs-tiles cs-tiles--' . count( $tiles ) . '">';
		foreach ( $tiles as $t ) {
			$t = wp_parse_args( $t, array( 'label' => '', 'num' => 0, 'url' => '', 'icon' => 'sparkle', 'active' => false, 'tone' => '' ) );
			$tag = $t['url'] ? 'a' : 'div';
			echo '<' . $tag . ' class="cs-tile' . ( $t['active'] ? ' is-active' : '' ) . ( $t['tone'] ? ' cs-tile--' . esc_attr( $t['tone'] ) : '' ) . '"' . ( $t['url'] ? ' href="' . esc_url( $t['url'] ) . '"' : '' ) . '>';
			echo '<span class="cs-tile-ic">' . celb_studio_icon( $t['icon'], 17 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span class="cs-tile-num">' . esc_html( $t['num'] ) . '</span><span class="cs-tile-label">' . esc_html( $t['label'] ) . '</span></' . $tag . '>';
		}
		echo '</div>';
	}
	echo '</div>';
}
function celb_ws_button( $label, $url, $icon = 'plus', $primary = true ) {
	return '<a class="cs-btn' . ( $primary ? ' cs-btn--primary' : '' ) . '" href="' . esc_url( $url ) . '">' . celb_studio_icon( $icon, 16 ) . '<span>' . esc_html( $label ) . '</span></a>';
}

/* Coloured status pill. Tones: blue amber green purple red grey slate. */
function celb_ws_pill( $label, $tone = 'grey' ) {
	return '<span class="cs-badge cs-tone--' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
}
function celb_ws_status_tone( $status ) {
	$map = array(
		'upcoming' => 'blue', 'scheduled' => 'blue', 'new' => 'blue', 'pending' => 'amber',
		'ongoing' => 'amber', 'in_progress' => 'amber', 'in progress' => 'amber',
		'completed' => 'green', 'signed' => 'green', 'live' => 'green', 'publish' => 'green',
		'postponed' => 'purple', 'canceled' => 'red', 'cancelled' => 'red', 'closed' => 'grey', 'draft' => 'grey',
	);
	$k = strtolower( (string) $status );
	return isset( $map[ $k ] ) ? $map[ $k ] : 'grey';
}

/* Talent chip: small photo + name, linking to the profile editor. */
function celb_ws_celeb_chip( $celeb_id, $empty = '' ) {
	$celeb_id = (int) $celeb_id;
	if ( ! $celeb_id || ! get_post( $celeb_id ) ) {
		return '<span class="cs-muted">' . esc_html( $empty ? $empty : '—' ) . '</span>';
	}
	$photo = celb_studio_photo_url( $celeb_id, 'thumbnail' );
	$name  = get_the_title( $celeb_id );
	return '<a class="cs-chip-talent" href="' . esc_url( get_edit_post_link( $celeb_id ) ) . '"><span class="cs-chip-talent-ph"' . ( $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '>' . ( $photo ? '' : esc_html( celb_monogram( $name ) ) ) . '</span><span>' . esc_html( $name ) . '</span></a>';
}

/* Calendar tile for a Y-m-d date. */
function celb_ws_dateblock( $ymd ) {
	$ts = $ymd ? strtotime( $ymd ) : 0;
	if ( ! $ts ) {
		return '<span class="cs-dateblock is-empty"><b>—</b><small>' . esc_html__( 'No date', 'celb-mgmt' ) . '</small></span>';
	}
	$today = current_time( 'Y-m-d' );
	$cls   = $ymd === $today ? ' is-today' : ( $ymd < $today ? ' is-past' : '' );
	return '<span class="cs-dateblock' . $cls . '"><small>' . esc_html( date_i18n( 'M', $ts ) ) . '</small><b>' . esc_html( date_i18n( 'j', $ts ) ) . '</b><small>' . esc_html( date_i18n( 'D', $ts ) ) . '</small></span>';
}
function celb_ws_hours( $minutes ) {
	$m = (int) $minutes;
	if ( ! $m ) {
		return '';
	}
	$h = floor( $m / 60 );
	$r = $m % 60;
	return trim( ( $h ? $h . 'h' : '' ) . ( $r ? ' ' . $r . 'm' : '' ) );
}
function celb_ws_hours_input( $minutes ) {
	$m = (int) $minutes;
	return $m ? rtrim( rtrim( number_format( $m / 60, 2, '.', '' ), '0' ), '.' ) : '';
}

/* All IDs of a post type (any live status) with meta primed. */
function celb_ws_ids( $type ) {
	static $cache = array();
	if ( ! isset( $cache[ $type ] ) ) {
		$cache[ $type ] = get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => true ) );
		update_meta_cache( 'post', $cache[ $type ] );
	}
	return $cache[ $type ];
}
function celb_ws_get( $key ) {
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
}
function celb_ws_list_url( $type, $args = array() ) {
	return add_query_arg( $args, admin_url( 'edit.php?post_type=' . $type ) );
}

/* Celebrity select options (shared by filters). */
function celb_ws_celeb_filter( $name, $current, $all_label ) {
	echo '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html( $all_label ) . '</option>';
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $c ) {
		echo '<option value="' . (int) $c->ID . '" ' . selected( (int) $current, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
	}
	echo '</select>';
}

/* Main-query guard for our list screens. */
function celb_ws_is_list_query( $q, $type ) {
	global $pagenow;
	return is_admin() && 'edit.php' === $pagenow && $q->is_main_query() && $type === $q->get( 'post_type' );
}

/* Hero dispatcher: one header per list screen. */
add_action( 'all_admin_notices', function () {
	$w = celb_studio_screen();
	$fn = 'celb_ws_hero_' . $w;
	if ( in_array( $w, celb_ws_list_screens(), true ) && function_exists( $fn ) ) {
		call_user_func( $fn );
	}
}, 1 );

/* =========================================================================
 * 1. PROJECTS
 * ====================================================================== */

function celb_ws_hero_projects() {
	$counts = array_fill_keys( celb_project_statuses(), 0 );
	foreach ( celb_ws_ids( 'celb_project' ) as $id ) {
		$s = (string) get_post_meta( $id, '_proj_status', true );
		if ( isset( $counts[ $s ] ) ) {
			$counts[ $s ]++;
		}
	}
	$cur   = celb_ws_get( 'proj_status' );
	$icons = array( 'Upcoming' => 'calendar', 'Ongoing' => 'film', 'Completed' => 'check', 'Postponed' => 'refresh', 'Canceled' => 'x' );
	$tiles = array( array( 'label' => __( 'All projects', 'celb-mgmt' ), 'num' => count( celb_ws_ids( 'celb_project' ) ), 'url' => celb_ws_list_url( 'celb_project' ), 'icon' => 'film', 'active' => '' === $cur ) );
	foreach ( $counts as $s => $n ) {
		$tiles[] = array( 'label' => $s, 'num' => $n, 'url' => celb_ws_list_url( 'celb_project', array( 'proj_status' => $s ) ), 'icon' => $icons[ $s ], 'active' => $cur === $s, 'tone' => celb_ws_status_tone( $s ) );
	}
	celb_ws_hero( __( 'Projects', 'celb-mgmt' ), __( 'Productions, shooting days and their progress across the roster.', 'celb-mgmt' ), $tiles, celb_ws_button( __( 'Add project', 'celb-mgmt' ), admin_url( 'post-new.php?post_type=celb_project' ) ) );
}

add_filter( 'manage_celb_project_posts_columns', function ( $c ) {
	return array(
		'cb'        => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'title'     => __( 'Project', 'celb-mgmt' ),
		'ws_celeb'  => __( 'Talent', 'celb-mgmt' ),
		'ws_status' => __( 'Status', 'celb-mgmt' ),
		'ws_dates'  => __( 'Dates', 'celb-mgmt' ),
		'ws_days'   => __( 'Shooting days', 'celb-mgmt' ),
		'ws_next'   => __( 'Next day', 'celb-mgmt' ),
	);
}, 100 );
add_action( 'manage_celb_project_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'ws_celeb':
			echo celb_ws_celeb_chip( get_post_meta( $id, '_proj_celeb', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			$type = (string) get_post_meta( $id, '_proj_type', true );
			if ( 'Other' === $type && get_post_meta( $id, '_proj_type_other', true ) ) {
				$type = (string) get_post_meta( $id, '_proj_type_other', true );
			}
			echo '<span class="cs-row-sub" data-cs-sub>' . esc_html( implode( ' · ', array_filter( array( $type, get_post_meta( $id, '_proj_company', true ) ) ) ) ) . '</span>';
			break;
		case 'ws_status':
			$s = (string) get_post_meta( $id, '_proj_status', true );
			echo $s ? celb_ws_pill( $s, celb_ws_status_tone( $s ) ) : '<span class="cs-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( get_post_meta( $id, '_proj_recur', true ) ) {
				echo ' ' . celb_ws_pill( __( 'Weekly', 'celb-mgmt' ), 'slate' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			break;
		case 'ws_dates':
			$s = (string) get_post_meta( $id, '_proj_start', true );
			$e = (string) get_post_meta( $id, '_proj_end', true );
			echo $s || $e ? '<span class="cs-range">' . esc_html( $s ? date_i18n( 'j M Y', strtotime( $s ) ) : '…' ) . '<i>→</i>' . esc_html( $e ? date_i18n( 'j M Y', strtotime( $e ) ) : '…' ) . '</span>' : '<span class="cs-muted">—</span>';
			break;
		case 'ws_days':
			$c   = celb_project_counts( get_post_meta( $id, '_proj_days', true ) );
			$pct = $c['total'] ? round( 100 * $c['completed'] / $c['total'] ) : 0;
			echo '<span class="cs-strength cs-strength--' . ( $pct >= 100 ? 'full' : 'good' ) . '"><span class="cs-meter"><span style="width:' . (int) $pct . '%"></span></span><b>' . (int) $c['completed'] . '/' . (int) $c['total'] . '</b></span>';
			break;
		case 'ws_next':
			$days  = get_post_meta( $id, '_proj_days', true );
			$today = current_time( 'Y-m-d' );
			$next  = '';
			foreach ( is_array( $days ) ? $days : array() as $d ) {
				$date = ( isset( $d['status'] ) && 'postponed' === $d['status'] && ! empty( $d['new_date'] ) ) ? $d['new_date'] : ( isset( $d['date'] ) ? $d['date'] : '' );
				if ( $date && $date >= $today && ( empty( $d['status'] ) || in_array( $d['status'], array( 'scheduled', 'postponed' ), true ) ) && ( '' === $next || $date < $next ) ) {
					$next = $date;
				}
			}
			echo $next ? '<span class="cs-next">' . esc_html( date_i18n( 'D j M', strtotime( $next ) ) ) . '</span>' : '<span class="cs-muted">—</span>';
			break;
	}
}, 10, 2 );

add_action( 'restrict_manage_posts', function ( $pt ) {
	if ( 'celb_project' !== $pt ) {
		return;
	}
	celb_ws_celeb_filter( 'proj_celeb', celb_ws_get( 'proj_celeb' ), __( 'All talent', 'celb-mgmt' ) );
	if ( celb_ws_get( 'proj_status' ) ) {
		echo '<input type="hidden" name="proj_status" value="' . esc_attr( celb_ws_get( 'proj_status' ) ) . '" />';
	}
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! celb_ws_is_list_query( $q, 'celb_project' ) ) {
		return;
	}
	$mq = array();
	if ( celb_ws_get( 'proj_status' ) ) {
		$mq[] = array( 'key' => '_proj_status', 'value' => celb_ws_get( 'proj_status' ) );
	}
	if ( (int) celb_ws_get( 'proj_celeb' ) ) {
		$mq[] = array( 'key' => '_proj_celeb', 'value' => (int) celb_ws_get( 'proj_celeb' ) );
	}
	if ( $mq ) {
		$q->set( 'meta_query', $mq );
	}
} );

/* =========================================================================
 * 2. SCHEDULE — list
 * ====================================================================== */

function celb_ws_hero_schedule() {
	$today = current_time( 'Y-m-d' );
	$week  = gmdate( 'Y-m-d', strtotime( $today . ' +6 days' ) );
	$n     = array( 'all' => 0, 'today' => 0, 'week' => 0, 'upcoming' => 0, 'past' => 0 );
	foreach ( celb_ws_ids( 'celb_sched' ) as $id ) {
		$d = (string) get_post_meta( $id, '_sched_date', true );
		$n['all']++;
		if ( ! $d ) {
			continue;
		}
		if ( $d === $today ) {
			$n['today']++;
		}
		if ( $d >= $today && $d <= $week ) {
			$n['week']++;
		}
		if ( $d >= $today ) {
			$n['upcoming']++;
		} else {
			$n['past']++;
		}
	}
	$cur   = celb_ws_get( 'sched_when' );
	$tiles = array(
		array( 'label' => __( 'All entries', 'celb-mgmt' ), 'num' => $n['all'], 'url' => celb_ws_list_url( 'celb_sched' ), 'icon' => 'calendar', 'active' => '' === $cur ),
		array( 'label' => __( 'Today', 'celb-mgmt' ), 'num' => $n['today'], 'url' => celb_ws_list_url( 'celb_sched', array( 'sched_when' => 'today' ) ), 'icon' => 'sparkle', 'active' => 'today' === $cur, 'tone' => 'blue' ),
		array( 'label' => __( 'Next 7 days', 'celb-mgmt' ), 'num' => $n['week'], 'url' => celb_ws_list_url( 'celb_sched', array( 'sched_when' => 'week' ) ), 'icon' => 'list', 'active' => 'week' === $cur ),
		array( 'label' => __( 'Upcoming', 'celb-mgmt' ), 'num' => $n['upcoming'], 'url' => celb_ws_list_url( 'celb_sched', array( 'sched_when' => 'upcoming' ) ), 'icon' => 'external', 'active' => 'upcoming' === $cur ),
		array( 'label' => __( 'Past', 'celb-mgmt' ), 'num' => $n['past'], 'url' => celb_ws_list_url( 'celb_sched', array( 'sched_when' => 'past' ) ), 'icon' => 'check', 'active' => 'past' === $cur ),
	);
	$actions = celb_ws_button( __( 'Agency calendar', 'celb-mgmt' ), admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-settings#calendar' ), 'calendar', false )
		. celb_ws_button( __( 'Add entry', 'celb-mgmt' ), admin_url( 'post-new.php?post_type=celb_sched' ) );
	celb_ws_hero( __( 'Schedule', 'celb-mgmt' ), __( 'Appointments, appearances and blocked-off time for every talent.', 'celb-mgmt' ), $tiles, $actions );
}

add_filter( 'manage_celb_sched_posts_columns', function ( $c ) {
	return array(
		'cb'        => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'ws_when'   => '<span class="screen-reader-text">' . esc_html__( 'Date', 'celb-mgmt' ) . '</span>',
		'title'     => __( 'Entry', 'celb-mgmt' ),
		'ws_celeb'  => __( 'Talent', 'celb-mgmt' ),
		'ws_type'   => __( 'Type', 'celb-mgmt' ),
		'ws_time'   => __( 'Time', 'celb-mgmt' ),
		'ws_where'  => __( 'Where', 'celb-mgmt' ),
		'ws_status' => __( 'Status', 'celb-mgmt' ),
	);
}, 100 );
add_filter( 'manage_edit-celb_sched_sortable_columns', function ( $c ) {
	$c['ws_when'] = 'sched_date';
	return $c;
} );
add_action( 'manage_celb_sched_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'ws_when':
			echo celb_ws_dateblock( get_post_meta( $id, '_sched_date', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_celeb':
			echo celb_ws_celeb_chip( get_post_meta( $id, '_sched_celeb', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_type':
			$t = (string) get_post_meta( $id, '_sched_type', true );
			if ( 'Other' === $t && get_post_meta( $id, '_sched_type_other', true ) ) {
				$t = (string) get_post_meta( $id, '_sched_type_other', true );
			}
			echo $t ? '<span class="cs-cat' . ( in_array( $t, array( 'Unavailable', 'Personal' ), true ) ? ' is-block' : '' ) . '">' . esc_html( $t ) . '</span>' : '<span class="cs-muted">—</span>';
			if ( get_post_meta( $id, '_sched_recur', true ) ) {
				echo ' ' . celb_ws_pill( __( 'Weekly', 'celb-mgmt' ), 'slate' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			break;
		case 'ws_time':
			$t = (string) get_post_meta( $id, '_sched_time', true );
			$d = celb_ws_hours( get_post_meta( $id, '_sched_duration', true ) );
			echo $t ? '<b class="cs-time">' . esc_html( $t ) . '</b>' . ( $d ? '<span class="cs-row-sub">' . esc_html( $d ) . '</span>' : '' ) : '<span class="cs-muted">' . esc_html__( 'All day', 'celb-mgmt' ) . '</span>';
			break;
		case 'ws_where':
			$l = get_post_meta( $id, '_sched_location', true );
			$l = is_array( $l ) ? $l : array();
			$label = ! empty( $l['label'] ) ? $l['label'] : ( ! empty( $l['address'] ) ? $l['address'] : '' );
			if ( $label ) {
				echo ! empty( $l['map'] ) ? '<a class="cs-where" href="' . esc_url( $l['map'] ) . '" target="_blank" rel="noopener">' . celb_studio_icon( 'pin', 14 ) . '<span>' . esc_html( $label ) . '</span></a>' : '<span class="cs-where">' . celb_studio_icon( 'pin', 14 ) . '<span>' . esc_html( $label ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="cs-muted">—</span>';
			}
			break;
		case 'ws_status':
			$s = (string) get_post_meta( $id, '_sched_status', true );
			echo $s ? celb_ws_pill( $s, celb_ws_status_tone( $s ) ) : '<span class="cs-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'Postponed' === $s && get_post_meta( $id, '_sched_new_date', true ) ) {
				echo '<span class="cs-row-sub">→ ' . esc_html( date_i18n( 'j M', strtotime( get_post_meta( $id, '_sched_new_date', true ) ) ) ) . '</span>';
			}
			break;
	}
}, 10, 2 );

add_action( 'restrict_manage_posts', function ( $pt ) {
	if ( 'celb_sched' !== $pt ) {
		return;
	}
	celb_ws_celeb_filter( 'sched_celeb_f', celb_ws_get( 'sched_celeb_f' ), __( 'All talent', 'celb-mgmt' ) );
	$cur = celb_ws_get( 'sched_type_f' );
	echo '<select name="sched_type_f"><option value="">' . esc_html__( 'All types', 'celb-mgmt' ) . '</option>';
	foreach ( celb_sched_types() as $t ) {
		echo '<option ' . selected( $cur, $t, false ) . '>' . esc_html( $t ) . '</option>';
	}
	echo '</select>';
	if ( celb_ws_get( 'sched_when' ) ) {
		echo '<input type="hidden" name="sched_when" value="' . esc_attr( celb_ws_get( 'sched_when' ) ) . '" />';
	}
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! celb_ws_is_list_query( $q, 'celb_sched' ) ) {
		return;
	}
	$today = current_time( 'Y-m-d' );
	$mq    = array();
	$when  = celb_ws_get( 'sched_when' );
	$order = 'DESC';
	if ( 'today' === $when ) {
		$mq[] = array( 'key' => '_sched_date', 'value' => $today );
		$order = 'ASC';
	} elseif ( 'week' === $when ) {
		$mq[] = array( 'key' => '_sched_date', 'value' => array( $today, gmdate( 'Y-m-d', strtotime( $today . ' +6 days' ) ) ), 'compare' => 'BETWEEN', 'type' => 'DATE' );
		$order = 'ASC';
	} elseif ( 'upcoming' === $when ) {
		$mq[] = array( 'key' => '_sched_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' );
		$order = 'ASC';
	} elseif ( 'past' === $when ) {
		$mq[] = array( 'key' => '_sched_date', 'value' => $today, 'compare' => '<', 'type' => 'DATE' );
	}
	if ( (int) celb_ws_get( 'sched_celeb_f' ) ) {
		$mq[] = array( 'key' => '_sched_celeb', 'value' => (int) celb_ws_get( 'sched_celeb_f' ) );
	}
	if ( celb_ws_get( 'sched_type_f' ) ) {
		$mq[] = array( 'key' => '_sched_type', 'value' => celb_ws_get( 'sched_type_f' ) );
	}
	if ( $mq ) {
		$q->set( 'meta_query', $mq );
	}
	// Order by the entry date unless the user picked another column.
	if ( ! $q->get( 'orderby' ) || 'sched_date' === $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', '_sched_date' );
		$q->set( 'orderby', 'meta_value' );
		if ( ! celb_ws_get( 'order' ) ) {
			$q->set( 'order', $order );
		}
	}
} );

/* =========================================================================
 * 3. SCHEDULE — editor
 * ====================================================================== */

add_action( 'add_meta_boxes_celb_sched', function () {
	remove_meta_box( 'celb_sched_details', 'celb_sched', 'normal' );
	remove_meta_box( 'celb_sched_atts', 'celb_sched', 'normal' );
	add_meta_box( 'celb_ws_sched_glance', __( 'At a glance', 'celb-mgmt' ), 'celb_ws_sched_glance_box', 'celb_sched', 'side', 'high' );
}, 20 );

function celb_ws_select( $name, $options, $current, $attr = '' ) {
	$h = '<select class="cs-input" name="' . esc_attr( $name ) . '" ' . $attr . '>';
	foreach ( $options as $v => $l ) {
		if ( is_int( $v ) ) {
			$v = $l;
		}
		$h .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $current, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
	}
	return $h . '</select>';
}
function celb_ws_celeb_select( $name, $current, $placeholder ) {
	$h = '<select class="cs-input" name="' . esc_attr( $name ) . '" data-cs-celeb-select><option value="0" data-photo="">' . esc_html( $placeholder ) . '</option>';
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $c ) {
		$h .= '<option value="' . (int) $c->ID . '" data-photo="' . esc_attr( celb_studio_photo_url( $c->ID, 'thumbnail' ) ) . '"' . selected( (int) $current, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
	}
	return $h . '</select>';
}
/* Celebrity picker with a live photo next to it. */
function celb_ws_celeb_field( $name, $current, $label, $placeholder ) {
	$photo = $current ? celb_studio_photo_url( (int) $current, 'thumbnail' ) : '';
	echo '<div class="cs-who cs-who--field"><span class="cs-who-photo' . ( $photo ? ' has-photo' : '' ) . '" data-cs-who-photo' . ( $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '>' . celb_studio_icon( 'user', 18 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cs-who-main"><label class="cs-label">' . esc_html( $label ) . '</label>' . celb_ws_celeb_select( $name, $current, $placeholder ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

add_action( 'edit_form_after_title', function ( $post ) {
	if ( 'celb_sched' !== $post->post_type ) {
		return;
	}
	$id     = $post->ID;
	$m      = function ( $k ) use ( $id ) { return get_post_meta( $id, $k, true ); };
	$status = (string) $m( '_sched_status' );
	$type   = (string) $m( '_sched_type' );
	$loc    = $m( '_sched_location' );
	$loc    = is_array( $loc ) ? $loc : array();
	$rem    = (int) $m( '_sched_reminder' );

	echo '<div class="cs-app cs-app--sched" data-cs-app="sched" data-post="' . (int) $id . '">';
	wp_nonce_field( 'celb_sched_save', 'celb_sched_nonce' );
	wp_nonce_field( 'celb_ws_sched', 'celb_ws_sched_nonce' );

	echo '<div class="cs-columns"><div class="cs-col">';

	celb_studio_card_open( __( 'Who & what', 'celb-mgmt' ), '', 'user' );
	celb_ws_celeb_field( 'sched_celeb', (int) $m( '_sched_celeb' ), __( 'Celebrity', 'celb-mgmt' ), __( '— Select celebrity —', 'celb-mgmt' ) );
	celb_studio_field_open( __( 'Celebrity email', 'celb-mgmt' ), 'sched_celeb_email' );
	echo '<input type="email" class="cs-input" id="sched_celeb_email" name="sched_celeb_email" value="' . esc_attr( $m( '_sched_celeb_email' ) ) . '" placeholder="name@email.com" />';
	celb_studio_field_close( __( 'New dates, postponements and cancellations are emailed here.', 'celb-mgmt' ) );
	echo '<div class="cs-row-fields">';
	celb_studio_field_open( __( 'Type', 'celb-mgmt' ) );
	echo celb_ws_select( 'sched_type', celb_sched_types(), $type, 'data-cs-other="sched-type"' ); // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_field_close();
	celb_studio_field_open( __( 'Specify type', 'celb-mgmt' ), '', '', 'Other' === $type ? '' : 'is-hidden' );
	echo '<input type="text" class="cs-input" name="sched_type_other" value="' . esc_attr( $m( '_sched_type_other' ) ) . '" data-cs-other-for="sched-type" />';
	celb_studio_field_close();
	echo '</div>';
	echo '<p class="cs-help">' . esc_html__( '“Unavailable” and “Personal” block the talent’s time without details.', 'celb-mgmt' ) . '</p>';
	celb_studio_card_close();

	celb_studio_card_open( __( 'Status', 'celb-mgmt' ), '', 'refresh' );
	echo '<div class="cs-segpick" role="radiogroup">';
	foreach ( celb_sched_statuses() as $s ) {
		echo '<label class="cs-segpick-item cs-tone--' . esc_attr( celb_ws_status_tone( $s ) ) . '"><input type="radio" name="sched_status" value="' . esc_attr( $s ) . '" ' . checked( $status ? $status : 'Upcoming', $s, false ) . ' data-cs-status /><span>' . esc_html( $s ) . '</span></label>';
	}
	echo '</div>';
	echo '<div class="cs-reveal' . ( 'Postponed' === $status ? ' is-on' : '' ) . '" data-cs-postponed>';
	echo '<p class="cs-label">' . esc_html__( 'Postponed to', 'celb-mgmt' ) . '</p><div class="cs-row-fields">';
	echo '<input type="date" class="cs-input" name="sched_new_date" value="' . esc_attr( $m( '_sched_new_date' ) ) . '" /><input type="time" class="cs-input" name="sched_new_time" value="' . esc_attr( $m( '_sched_new_time' ) ) . '" />';
	echo '</div><p class="cs-help">' . esc_html__( 'The calendar event moves to this date and the celebrity is emailed.', 'celb-mgmt' ) . '</p></div>';
	celb_studio_card_close();

	echo '</div><div class="cs-col">';

	celb_studio_card_open( __( 'When', 'celb-mgmt' ), '', 'calendar' );
	echo '<div class="cs-row-fields cs-row-fields--3">';
	celb_studio_field_open( __( 'Date', 'celb-mgmt' ) );
	echo '<input type="date" class="cs-input" name="sched_date" value="' . esc_attr( $m( '_sched_date' ) ) . '" data-cs-glance="date" />';
	celb_studio_field_close();
	celb_studio_field_open( __( 'Time', 'celb-mgmt' ) );
	echo '<input type="time" class="cs-input" name="sched_time" value="' . esc_attr( $m( '_sched_time' ) ) . '" data-cs-glance="time" />';
	celb_studio_field_close();
	celb_studio_field_open( __( 'Duration', 'celb-mgmt' ) );
	echo '<div class="cs-affix"><input type="number" class="cs-input" min="0" step="0.5" name="sched_duration" value="' . esc_attr( celb_ws_hours_input( $m( '_sched_duration' ) ) ) . '" /><span class="cs-affix-suf">' . esc_html__( 'hrs', 'celb-mgmt' ) . '</span></div>';
	celb_studio_field_close();
	echo '</div>';
	celb_studio_switch( 'sched_recur', '1' === $m( '_sched_recur' ), __( 'Repeat weekly on this weekday', 'celb-mgmt' ), __( 'For a weekly commitment (e.g. a show every Wednesday). Blocks the talent each week.', 'celb-mgmt' ), '1', 'data-cs-toggle="recur"' );
	echo '<div class="cs-reveal' . ( '1' === $m( '_sched_recur' ) ? ' is-on' : '' ) . '" data-cs-toggled="recur">';
	celb_studio_field_open( __( 'Repeat until (optional)', 'celb-mgmt' ), '', '', 'cs-field--narrow' );
	echo '<input type="date" class="cs-input" name="sched_recur_until" value="' . esc_attr( $m( '_sched_recur_until' ) ) . '" />';
	celb_studio_field_close();
	echo '</div>';
	celb_studio_field_open( __( 'Reminder', 'celb-mgmt' ), '', '', 'cs-field--narrow cs-mt' );
	echo celb_ws_select( 'sched_reminder', array( '0' => __( 'No reminder', 'celb-mgmt' ), '5' => __( '5 minutes before', 'celb-mgmt' ), '15' => __( '15 minutes before', 'celb-mgmt' ), '30' => __( '30 minutes before', 'celb-mgmt' ), '60' => __( '1 hour before', 'celb-mgmt' ), '120' => __( '2 hours before', 'celb-mgmt' ), '1440' => __( '1 day before', 'celb-mgmt' ) ), (string) $rem ); // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_field_close();
	celb_studio_card_close();

	celb_studio_card_open( __( 'Where', 'celb-mgmt' ), __( 'A real street address shows an embedded map in calendar apps.', 'celb-mgmt' ), 'pin' );
	celb_studio_field_open( __( 'Place name', 'celb-mgmt' ) );
	echo '<input type="text" class="cs-input" name="sched_loc_label" value="' . esc_attr( isset( $loc['label'] ) ? $loc['label'] : '' ) . '" placeholder="' . esc_attr__( 'Studio 5, Media City', 'celb-mgmt' ) . '" />';
	celb_studio_field_close();
	celb_studio_field_open( __( 'Address', 'celb-mgmt' ) );
	echo '<input type="text" class="cs-input" name="sched_loc_addr" value="' . esc_attr( isset( $loc['address'] ) ? $loc['address'] : '' ) . '" placeholder="' . esc_attr__( '8 Soliman Pasha, Heliopolis, Cairo', 'celb-mgmt' ) . '" />';
	celb_studio_field_close();
	if ( ! empty( $loc['map'] ) ) {
		echo '<a class="cs-btn cs-btn--sm cs-btn--ghost" href="' . esc_url( $loc['map'] ) . '" target="_blank" rel="noopener">' . celb_studio_icon( 'external', 14 ) . '<span>' . esc_html__( 'Open in Maps', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	celb_studio_card_close();

	echo '</div></div>';

	celb_studio_card_open( __( 'Notes', 'celb-mgmt' ), '', 'text' );
	echo '<div class="cs-row-fields">';
	celb_studio_field_open( __( 'Description', 'celb-mgmt' ) );
	echo '<textarea class="cs-input" name="sched_desc" rows="4">' . esc_textarea( $m( '_sched_desc' ) ) . '</textarea>';
	celb_studio_field_close();
	celb_studio_field_open( __( 'Preparation notes', 'celb-mgmt' ) );
	echo '<textarea class="cs-input" name="sched_prep" rows="4" placeholder="' . esc_attr__( 'Wardrobe, talking points, call time…', 'celb-mgmt' ) . '">' . esc_textarea( $m( '_sched_prep' ) ) . '</textarea>';
	celb_studio_field_close();
	echo '</div>';
	celb_studio_card_close();

	celb_studio_card_open( __( 'Attachments', 'celb-mgmt' ), __( 'Call sheets, scripts, briefs, images or videos.', 'celb-mgmt' ), 'upload' );
	celb_media_box( $id, '_sched_attachments', 'sched_atts' );
	celb_studio_card_close();

	echo '</div>';
} );

function celb_ws_sched_glance_box( $post ) {
	$id   = $post->ID;
	$date = (string) get_post_meta( $id, '_sched_date', true );
	$time = (string) get_post_meta( $id, '_sched_time', true );
	$st   = (string) get_post_meta( $id, '_sched_status', true );
	echo '<div class="cs-glance">' . celb_ws_dateblock( $date ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div><p class="cs-glance-when">' . esc_html( $date ? date_i18n( 'l j F Y', strtotime( $date ) ) : __( 'No date yet', 'celb-mgmt' ) ) . '</p>';
	echo '<p class="cs-glance-sub">' . esc_html( $time ? $time : __( 'All day', 'celb-mgmt' ) ) . ( get_post_meta( $id, '_sched_duration', true ) ? ' · ' . esc_html( celb_ws_hours( get_post_meta( $id, '_sched_duration', true ) ) ) : '' ) . '</p>';
	echo $st ? celb_ws_pill( $st, celb_ws_status_tone( $st ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div></div>';
	if ( $id && 'auto-draft' !== get_post_status( $id ) ) {
		echo '<a class="cs-btn cs-btn--sm cs-glance-btn" href="' . esc_url( celb_sched_ics_url( $id ) ) . '">' . celb_studio_icon( 'calendar', 15 ) . '<span>' . esc_html__( 'Add to calendar (.ics)', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/* Attachments were never saved from the admin schedule editor; persist them. */
add_action( 'save_post_celb_sched', function ( $post_id ) {
	if ( ! isset( $_POST['celb_ws_sched_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_ws_sched_nonce'] ), 'celb_ws_sched' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['sched_atts'] ) ) {
		return;
	}
	$ids = array();
	foreach ( explode( ',', sanitize_text_field( wp_unslash( $_POST['sched_atts'] ) ) ) as $x ) {
		$x = (int) trim( $x );
		if ( $x ) {
			$ids[] = $x;
		}
	}
	update_post_meta( $post_id, '_sched_attachments', $ids );
} );

/* =========================================================================
 * 4. REQUESTS (booking inbox, celb_request)
 * ====================================================================== */

function celb_ws_hero_bookings() {
	$n = array( 'new' => 0, 'in_progress' => 0, 'closed' => 0 );
	foreach ( celb_ws_ids( 'celb_request' ) as $id ) {
		$s = (string) get_post_meta( $id, '_req_status', true );
		$s = isset( $n[ $s ] ) ? $s : 'new';
		$n[ $s ]++;
	}
	$cur   = celb_ws_get( 'req_status' );
	$tiles = array( array( 'label' => __( 'All requests', 'celb-mgmt' ), 'num' => array_sum( $n ), 'url' => celb_ws_list_url( 'celb_request' ), 'icon' => 'chat', 'active' => '' === $cur ) );
	foreach ( array( 'new' => 'sparkle', 'in_progress' => 'refresh', 'closed' => 'lock' ) as $k => $ic ) {
		$tiles[] = array( 'label' => celb_request_status_label( $k ), 'num' => $n[ $k ], 'url' => celb_ws_list_url( 'celb_request', array( 'req_status' => $k ) ), 'icon' => $ic, 'active' => $cur === $k, 'tone' => celb_ws_status_tone( $k ) );
	}
	celb_ws_hero( __( 'Requests', 'celb-mgmt' ), __( 'Booking requests sent through the [CLEB_booking] form and the app.', 'celb-mgmt' ), $tiles, celb_ws_button( __( 'Artist requests', 'celb-mgmt' ), admin_url( 'edit.php?post_type=celb_artreq' ), 'mail', false ) );
}
add_filter( 'manage_celb_request_posts_columns', function ( $c ) {
	return array(
		'cb'         => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'ws_avatar'  => '<span class="screen-reader-text">' . esc_html__( 'Sender', 'celb-mgmt' ) . '</span>',
		'title'      => __( 'Requester', 'celb-mgmt' ),
		'ws_celeb'   => __( 'Talent', 'celb-mgmt' ),
		'ws_type'    => __( 'Type', 'celb-mgmt' ),
		'ws_pref'    => __( 'Preferred date', 'celb-mgmt' ),
		'ws_status'  => __( 'Status', 'celb-mgmt' ),
		'ws_reach'   => __( 'Reach', 'celb-mgmt' ),
		'date'       => __( 'Received', 'celb-mgmt' ),
	);
}, 100 );
add_action( 'manage_celb_request_posts_custom_column', function ( $col, $id ) {
	$d = celb_request_data( $id );
	switch ( $col ) {
		case 'ws_avatar':
			echo '<span class="cs-ar-avatar">' . esc_html( celb_monogram( $d['name'] ) ) . '</span>';
			echo '<span class="cs-row-sub" data-cs-sub>' . esc_html( implode( ' · ', array_filter( array( $d['company'], $d['email'] ) ) ) ) . '</span>';
			break;
		case 'ws_celeb':
			echo celb_ws_celeb_chip( $d['celeb'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_type':
			echo $d['type'] ? '<span class="cs-cat">' . esc_html( $d['type'] ) . '</span>' : '<span class="cs-muted">—</span>';
			break;
		case 'ws_pref':
			echo $d['date'] ? esc_html( date_i18n( 'D j M Y', strtotime( $d['date'] ) ) ) : '<span class="cs-muted">—</span>';
			break;
		case 'ws_status':
			echo celb_ws_pill( celb_request_status_label( $d['status'] ), celb_ws_status_tone( $d['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_reach':
			echo '<span class="cs-ar-reach">';
			if ( $d['email'] ) {
				echo '<a href="mailto:' . esc_attr( $d['email'] ) . '" title="' . esc_attr( $d['email'] ) . '">' . celb_studio_icon( 'mail', 16 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( $d['phone'] ) {
				echo '<a href="https://wa.me/' . esc_attr( preg_replace( '/[^0-9]/', '', $d['phone'] ) ) . '" target="_blank" rel="noopener" title="' . esc_attr( $d['phone'] ) . '">' . celb_studio_icon( 'chat', 16 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</span>';
			break;
	}
}, 10, 2 );
add_filter( 'the_title', function ( $title, $id = 0 ) {
	if ( $id && is_admin() && 'celb_request' === get_post_type( $id ) && 'bookings' === celb_studio_screen() ) {
		$n = (string) get_post_meta( $id, '_req_name', true );
		return '' !== $n ? $n : $title;
	}
	return $title;
}, 10, 2 );
add_filter( 'views_edit-celb_request', function ( $v ) {
	unset( $v['publish'], $v['mine'] );
	return $v;
} );

/* Request detail (editor) rebuilt as cards; status saved by the existing handler. */
add_action( 'add_meta_boxes_celb_request', function () {
	remove_meta_box( 'celb_request_box', 'celb_request', 'normal' );
	add_meta_box( 'celb_ws_req_status', __( 'Status', 'celb-mgmt' ), 'celb_ws_req_status_box', 'celb_request', 'side', 'high' );
}, 20 );
function celb_ws_req_status_box( $post ) {
	wp_nonce_field( 'celb_request_admin', 'celb_request_admin_nonce' );
	$cur = celb_request_data( $post->ID )['status'];
	echo '<div class="cs-ar-status">';
	foreach ( celb_request_statuses() as $s ) {
		echo '<label class="cs-ar-opt cs-ar-opt--' . esc_attr( 'in_progress' === $s ? 'progress' : $s ) . '"><input type="radio" name="req_status" value="' . esc_attr( $s ) . '" ' . checked( $cur, $s, false ) . ' /><span class="cs-ar-opt-dot"></span><span>' . esc_html( celb_request_status_label( $s ) ) . '</span></label>';
	}
	echo '</div>';
}
add_action( 'edit_form_after_title', function ( $post ) {
	if ( 'celb_request' !== $post->post_type ) {
		return;
	}
	$d = celb_request_data( $post->ID );
	echo '<div class="cs-app cs-ar">';
	echo '<section class="cs-card cs-ar-head"><div class="cs-card-body"><div class="cs-ar-who"><span class="cs-ar-avatar cs-ar-avatar--lg">' . esc_html( celb_monogram( $d['name'] ) ) . '</span><div>';
	echo '<p class="cs-eyebrow">' . esc_html__( 'Booking request', 'celb-mgmt' ) . ' · #' . (int) $post->ID . '</p><h2 class="cs-ar-name">' . esc_html( $d['name'] ? $d['name'] : __( 'Unknown sender', 'celb-mgmt' ) ) . '</h2>';
	echo '<p class="cs-ar-meta">' . ( $d['company'] ? '<strong>' . esc_html( $d['company'] ) . '</strong> · ' : '' ) . esc_html( get_the_date( 'j F Y, H:i', $post ) ) . '</p></div>';
	echo celb_ws_pill( celb_request_status_label( $d['status'] ), celb_ws_status_tone( $d['status'] ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cs-ar-actions">';
	if ( $d['email'] ) {
		echo '<a class="cs-btn cs-btn--primary" href="mailto:' . esc_attr( $d['email'] ) . '?subject=' . rawurlencode( sprintf( __( 'Your request for %s', 'celb-mgmt' ), $d['celeb_name'] ) ) . '">' . celb_studio_icon( 'mail', 16 ) . '<span>' . esc_html__( 'Reply by email', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $d['phone'] ) {
		echo '<a class="cs-btn cs-ar-wa" href="https://wa.me/' . esc_attr( preg_replace( '/[^0-9]/', '', $d['phone'] ) ) . '" target="_blank" rel="noopener">' . celb_studio_icon( 'chat', 16 ) . '<span>WhatsApp</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<a class="cs-btn" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['phone'] ) ) . '">' . celb_studio_icon( 'phone', 16 ) . '<span>' . esc_html__( 'Call', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div></section>';
	echo '<div class="cs-columns cs-columns--ar"><div class="cs-col">';
	celb_studio_card_open( __( 'Details', 'celb-mgmt' ), '', 'text' );
	echo $d['message'] ? '<div class="cs-ar-body">' . nl2br( esc_html( $d['message'] ) ) . '</div>' : '<p class="cs-muted">' . esc_html__( 'No details were provided.', 'celb-mgmt' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_card_close();
	echo '</div><div class="cs-col">';
	celb_studio_card_open( __( 'Booking', 'celb-mgmt' ), '', 'calendar' );
	echo '<dl class="cs-ar-dl">';
	foreach ( array(
		__( 'Talent', 'celb-mgmt' )         => celb_ws_celeb_chip( $d['celeb'] ),
		__( 'Type', 'celb-mgmt' )           => esc_html( $d['type'] ),
		__( 'Preferred date', 'celb-mgmt' ) => $d['date'] ? esc_html( date_i18n( 'l j F Y', strtotime( $d['date'] ) ) ) : '',
		__( 'Email', 'celb-mgmt' )          => $d['email'] ? '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a>' : '',
		__( 'Phone', 'celb-mgmt' )          => esc_html( $d['phone'] ),
		__( 'Company', 'celb-mgmt' )        => esc_html( $d['company'] ),
	) as $k => $v ) {
		echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . ( '' !== $v ? $v : '<span class="cs-muted">—</span>' ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</dl>';
	celb_studio_card_close();
	echo '</div></div></div>';
} );

/* =========================================================================
 * 5. CONTRACTS — list
 * ====================================================================== */

function celb_ws_contract_status( $id ) {
	$st = (string) get_post_meta( $id, '_contract_status', true );
	return $st ? $st : 'pending';
}
function celb_ws_hero_contracts() {
	$n = array( 'pending' => 0, 'signed' => 0 );
	$month = 0;
	foreach ( celb_ws_ids( 'celb_contract' ) as $id ) {
		$s = celb_ws_contract_status( $id );
		$n[ 'signed' === $s ? 'signed' : 'pending' ]++;
		$ts = (int) get_post_meta( $id, '_contract_signed_at', true );
		if ( $ts && gmdate( 'Y-m', $ts ) === current_time( 'Y-m' ) ) {
			$month++;
		}
	}
	$cur   = celb_ws_get( 'c_status' );
	$tiles = array(
		array( 'label' => __( 'All contracts', 'celb-mgmt' ), 'num' => array_sum( $n ), 'url' => celb_ws_list_url( 'celb_contract' ), 'icon' => 'pen', 'active' => '' === $cur ),
		array( 'label' => __( 'Awaiting signature', 'celb-mgmt' ), 'num' => $n['pending'], 'url' => celb_ws_list_url( 'celb_contract', array( 'c_status' => 'pending' ) ), 'icon' => 'refresh', 'active' => 'pending' === $cur, 'tone' => 'amber' ),
		array( 'label' => __( 'Signed', 'celb-mgmt' ), 'num' => $n['signed'], 'url' => celb_ws_list_url( 'celb_contract', array( 'c_status' => 'signed' ) ), 'icon' => 'check', 'active' => 'signed' === $cur, 'tone' => 'green' ),
		array( 'label' => __( 'Signed this month', 'celb-mgmt' ), 'num' => $month, 'icon' => 'calendar' ),
	);
	$actions = celb_ws_button( __( 'Templates', 'celb-mgmt' ), admin_url( 'edit.php?post_type=celb_ctpl' ), 'text', false )
		. celb_ws_button( __( 'New contract', 'celb-mgmt' ), admin_url( 'post-new.php?post_type=celb_contract' ) );
	celb_ws_hero( __( 'Contracts', 'celb-mgmt' ), __( 'Send a signing link, track who has signed, download the signed PDF.', 'celb-mgmt' ), $tiles, $actions );
}
add_filter( 'manage_celb_contract_posts_columns', function ( $c ) {
	return array(
		'cb'        => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'title'     => __( 'Contract', 'celb-mgmt' ),
		'ws_celeb'  => __( 'Talent', 'celb-mgmt' ),
		'ws_tpl'    => __( 'Template', 'celb-mgmt' ),
		'ws_terms'  => __( 'Terms', 'celb-mgmt' ),
		'ws_status' => __( 'Status', 'celb-mgmt' ),
		'ws_action' => __( 'Link / PDF', 'celb-mgmt' ),
	);
}, 100 );
add_action( 'manage_celb_contract_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'ws_celeb':
			echo celb_ws_celeb_chip( get_post_meta( $id, '_contract_celeb', true ), __( 'Unassigned', 'celb-mgmt' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_tpl':
			$t = (int) get_post_meta( $id, '_contract_tpl', true );
			if ( $t && get_post( $t ) ) {
				echo '<a class="cs-plain" href="' . esc_url( get_edit_post_link( $t ) ) . '">' . esc_html( get_the_title( $t ) ) . '</a> ' . celb_ws_pill( 'ar' === get_post_meta( $t, '_ctpl_lang', true ) ? 'AR' : 'EN', 'slate' ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="cs-muted">—</span>';
			}
			break;
		case 'ws_terms':
			$rates = celb_contract_rates( $id );
			$bits  = array();
			if ( $rates ) {
				$vals   = array_map( 'floatval', $rates );
				$bits[] = esc_html( min( $vals ) === max( $vals ) ? celb_rate_fmt( min( $vals ) ) : celb_rate_fmt( min( $vals ) ) . '–' . celb_rate_fmt( max( $vals ) ) ) . ' ' . esc_html__( 'commission', 'celb-mgmt' );
			}
			$fee = celb_contract_fee_amount_str( $id );
			if ( '' !== $fee ) {
				$bits[] = esc_html( $fee . ' ' . celb_contract_monthly( $id )['currency'] . ' / ' . __( 'month', 'celb-mgmt' ) );
			}
			echo $bits ? '<span class="cs-terms">' . implode( '<br>', $bits ) . '</span>' : '<span class="cs-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_status':
			$s = celb_ws_contract_status( $id );
			echo celb_ws_pill( 'signed' === $s ? __( 'Signed', 'celb-mgmt' ) : __( 'Awaiting signature', 'celb-mgmt' ), 'signed' === $s ? 'green' : 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput
			$ts = (int) get_post_meta( $id, '_contract_signed_at', true );
			if ( $ts ) {
				echo '<span class="cs-row-sub">' . esc_html( date_i18n( 'j M Y, H:i', $ts ) ) . '</span>';
			}
			break;
		case 'ws_action':
			$path = get_post_meta( $id, '_contract_pdf_path', true );
			if ( $path && file_exists( $path ) ) {
				$u = wp_nonce_url( admin_url( 'admin-post.php?action=celb_contract_download&contract=' . $id ), 'celb_dl_' . $id );
				echo '<a class="cs-linkchip" href="' . esc_url( $u ) . '">' . celb_studio_icon( 'download', 14 ) . '<span>' . esc_html__( 'Signed PDF', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} elseif ( celb_contract_sign_link( $id ) ) {
				echo '<button type="button" class="cs-linkchip" data-cs-copy="' . esc_attr( celb_contract_sign_link( $id ) ) . '">' . celb_studio_icon( 'link', 14 ) . '<span>' . esc_html__( 'Copy signing link', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="cs-muted">' . esc_html__( 'Publish to get a link', 'celb-mgmt' ) . '</span>';
			}
			break;
	}
}, 10, 2 );
add_filter( 'views_edit-celb_contract', function ( $v ) {
	unset( $v['mine'] );
	return $v;
} );

/* =========================================================================
 * 6. CONTRACTS — editor
 * ====================================================================== */

add_action( 'add_meta_boxes_celb_contract', function () {
	remove_meta_box( 'celb_contract_setup', 'celb_contract', 'normal' );
	remove_meta_box( 'celb_contract_status', 'celb_contract', 'side' );
	remove_meta_box( 'celb_contract_rates', 'celb_contract', 'normal' );
	add_meta_box( 'celb_ws_contract_sign', __( 'Signing', 'celb-mgmt' ), 'celb_ws_contract_sign_box', 'celb_contract', 'side', 'high' );
}, 20 );

add_action( 'edit_form_after_title', function ( $post ) {
	if ( 'celb_contract' !== $post->post_type ) {
		return;
	}
	$id     = $post->ID;
	$tpl_id = (int) get_post_meta( $id, '_contract_tpl', true );
	$cel_id = (int) get_post_meta( $id, '_contract_celeb', true );
	$tpls   = get_posts( array( 'post_type' => 'celb_ctpl', 'numberposts' => -1, 'post_status' => array( 'publish', 'draft' ), 'orderby' => 'title', 'order' => 'ASC' ) );
	$rates  = celb_contract_rates( $id );
	$mon    = celb_contract_monthly( $id );
	$signed = 'signed' === celb_ws_contract_status( $id );

	echo '<div class="cs-app cs-app--contract" data-cs-app="contract" data-post="' . (int) $id . '">';
	wp_nonce_field( 'celb_contract_save', 'celb_contract_nonce' );

	echo '<section class="cs-card cs-doc-head"><div class="cs-card-body">';
	echo '<p class="cs-eyebrow">' . esc_html__( 'Contract', 'celb-mgmt' ) . ( 'auto-draft' !== $post->post_status ? ' · #' . (int) $id : '' ) . '</p>';
	echo '<h2 class="cs-doc-title" data-cs-doc-title>' . esc_html( $cel_id ? sprintf( __( 'Contract — %s', 'celb-mgmt' ), get_the_title( $cel_id ) ) : __( 'New contract', 'celb-mgmt' ) ) . '</h2>';
	echo '<p class="cs-ar-meta">' . esc_html__( 'The title updates automatically from the talent when you save.', 'celb-mgmt' ) . '</p>';
	if ( $signed ) {
		echo '<div class="cs-note cs-note--ok">' . celb_studio_icon( 'lock', 16 ) . '<span>' . esc_html__( 'This contract is signed. Changes here do not alter the signed PDF.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></section>';

	celb_studio_card_open( __( 'Parties', 'celb-mgmt' ), __( 'Pick the template and the talent, then Publish to generate the signing link.', 'celb-mgmt' ), 'users' );
	echo '<div class="cs-row-fields">';
	celb_studio_field_open( __( 'Contract template', 'celb-mgmt' ), 'contract_tpl' );
	echo '<select class="cs-input" id="contract_tpl" name="contract_tpl"><option value="0">' . esc_html__( '— Select template —', 'celb-mgmt' ) . '</option>';
	foreach ( $tpls as $t ) {
		echo '<option value="' . (int) $t->ID . '" ' . selected( $tpl_id, $t->ID, false ) . '>' . esc_html( $t->post_title . ( 'ar' === get_post_meta( $t->ID, '_ctpl_lang', true ) ? ' (AR)' : ' (EN)' ) ) . '</option>';
	}
	echo '</select>';
	celb_studio_field_close( $tpls ? ( $tpl_id ? '<a href="' . esc_url( get_edit_post_link( $tpl_id ) ) . '">' . esc_html__( 'Edit this template', 'celb-mgmt' ) . '</a>' : '' ) : sprintf( '%s <a href="%s">%s</a>', esc_html__( 'No templates yet.', 'celb-mgmt' ), esc_url( admin_url( 'post-new.php?post_type=celb_ctpl' ) ), esc_html__( 'Create one', 'celb-mgmt' ) ) );
	echo '<div class="cs-field">';
	celb_ws_celeb_field( 'contract_celeb', $cel_id, __( 'Assign to talent', 'celb-mgmt' ), __( '— Select talent —', 'celb-mgmt' ) );
	echo '</div></div>';
	celb_studio_card_close();

	celb_studio_card_open( __( 'Commission', 'celb-mgmt' ), __( 'Percentages for this contract. {{commission_table}} prints all four as a table; {{rate_KEY}} prints one value.', 'celb-mgmt' ), 'tag' );
	echo '<div class="cs-rates">';
	foreach ( celb_contract_rate_defs() as $key => $label ) {
		echo '<label class="cs-rate"><span class="cs-rate-label">' . esc_html( $label ) . '<code class="cs-token">{{rate_' . esc_html( $key ) . '}}</code></span>';
		echo '<span class="cs-affix"><input type="number" class="cs-input" min="0" max="100" step="0.01" name="contract_rates[' . esc_attr( $key ) . ']" value="' . esc_attr( isset( $rates[ $key ] ) ? $rates[ $key ] : '' ) . '" placeholder="0" /><span class="cs-affix-suf">%</span></span></label>';
	}
	echo '</div>';
	celb_studio_card_close();

	celb_studio_card_open( __( 'Monthly retainer', 'celb-mgmt' ), __( '{{monthly_fee}} prints e.g. “5,000 EGP / month, payable on the 5th” (blank when off). {{monthly_fee_amount}}, {{currency}} and {{payment_day}} print the parts.', 'celb-mgmt' ), 'calendar' );
	celb_studio_switch( 'contract_monthly_enabled', $mon['enabled'], __( 'Charge a fixed monthly fee', 'celb-mgmt' ), '', '1', 'data-cs-toggle="retainer"' );
	echo '<div class="cs-reveal' . ( $mon['enabled'] ? ' is-on' : '' ) . '" data-cs-toggled="retainer"><div class="cs-row-fields cs-row-fields--3 cs-mt">';
	celb_studio_field_open( __( 'Amount', 'celb-mgmt' ) );
	echo '<input type="number" class="cs-input" min="0" step="0.01" name="contract_monthly_fee" value="' . esc_attr( $mon['amount'] ) . '" />';
	celb_studio_field_close();
	celb_studio_field_open( __( 'Currency', 'celb-mgmt' ) );
	echo celb_ws_select( 'contract_currency', celb_contract_currencies(), $mon['currency'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_field_close();
	celb_studio_field_open( __( 'Payment day', 'celb-mgmt' ) );
	echo '<div class="cs-affix"><span class="cs-affix-pre">' . esc_html__( 'Day', 'celb-mgmt' ) . '</span><input type="number" class="cs-input" min="1" max="31" step="1" name="contract_monthly_day" value="' . esc_attr( $mon['day'] ? $mon['day'] : '' ) . '" placeholder="1–31" /></div>';
	celb_studio_field_close();
	echo '</div></div>';
	celb_studio_card_close();

	echo '</div>';
} );

function celb_ws_contract_sign_box( $post ) {
	$id     = $post->ID;
	$status = celb_ws_contract_status( $id );
	echo '<div class="cs-sign">';
	if ( 'signed' === $status ) {
		echo celb_ws_pill( __( 'Signed', 'celb-mgmt' ), 'green' ); // phpcs:ignore WordPress.Security.EscapeOutput
		$ts = (int) get_post_meta( $id, '_contract_signed_at', true );
		echo '<dl class="cs-ar-dl cs-mt">';
		foreach ( array(
			__( 'Signed', 'celb-mgmt' )  => $ts ? date_i18n( 'j M Y, H:i', $ts ) : '',
			__( 'Sent to', 'celb-mgmt' ) => (string) get_post_meta( $id, '_contract_email', true ),
			__( 'IP', 'celb-mgmt' )      => (string) get_post_meta( $id, '_contract_signed_ip', true ),
		) as $k => $v ) {
			if ( '' !== $v ) {
				echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
			}
		}
		echo '</dl>';
		$path = get_post_meta( $id, '_contract_pdf_path', true );
		if ( $path && file_exists( $path ) ) {
			echo '<a class="cs-btn cs-btn--primary cs-btn--block" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=celb_contract_download&contract=' . $id ), 'celb_dl_' . $id ) ) . '">' . celb_studio_icon( 'download', 16 ) . '<span>' . esc_html__( 'Download signed PDF', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		return;
	}
	echo celb_ws_pill( __( 'Awaiting signature', 'celb-mgmt' ), 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput
	$link = celb_contract_sign_link( $id );
	if ( ! $link ) {
		echo '<p class="cs-help">' . esc_html__( 'Publish this contract to generate its private signing link.', 'celb-mgmt' ) . '</p></div>';
		return;
	}
	echo '<p class="cs-label cs-mt">' . esc_html__( 'Signing link', 'celb-mgmt' ) . '</p>';
	celb_studio_copy_field( $link, true );
	$s = celb_get_settings();
	if ( empty( $s['contract_sign_url'] ) ) {
		echo '<p class="cs-help">' . sprintf( esc_html__( 'Using /sign/. Set the signing page in %s.', 'celb-mgmt' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-settings#contracts' ) ) . '">' . esc_html__( 'Settings', 'celb-mgmt' ) . '</a>' ) . '</p>';
	}
	echo '<p class="cs-help">' . esc_html__( 'Send this link to the talent. They fill in the fields, sign, and receive the PDF by email.', 'celb-mgmt' ) . '</p>';
	echo '</div>';
}

/* =========================================================================
 * 7. CONTRACT TEMPLATES — list + editor
 * ====================================================================== */

function celb_ws_hero_templates() {
	$n = array( 'en' => 0, 'ar' => 0 );
	foreach ( celb_ws_ids( 'celb_ctpl' ) as $id ) {
		$n[ 'ar' === get_post_meta( $id, '_ctpl_lang', true ) ? 'ar' : 'en' ]++;
	}
	$cur   = celb_ws_get( 'ctpl_lang' );
	$tiles = array(
		array( 'label' => __( 'Templates', 'celb-mgmt' ), 'num' => array_sum( $n ), 'url' => celb_ws_list_url( 'celb_ctpl' ), 'icon' => 'text', 'active' => '' === $cur ),
		array( 'label' => __( 'English', 'celb-mgmt' ), 'num' => $n['en'], 'url' => celb_ws_list_url( 'celb_ctpl', array( 'ctpl_lang' => 'en' ) ), 'icon' => 'globe', 'active' => 'en' === $cur ),
		array( 'label' => __( 'Arabic', 'celb-mgmt' ), 'num' => $n['ar'], 'url' => celb_ws_list_url( 'celb_ctpl', array( 'ctpl_lang' => 'ar' ) ), 'icon' => 'globe', 'active' => 'ar' === $cur ),
		array( 'label' => __( 'Contracts issued', 'celb-mgmt' ), 'num' => count( celb_ws_ids( 'celb_contract' ) ), 'url' => celb_ws_list_url( 'celb_contract' ), 'icon' => 'pen' ),
	);
	celb_ws_hero( __( 'Contract Templates', 'celb-mgmt' ), __( 'Reusable contract wording with fill-in fields. Contracts are issued from these.', 'celb-mgmt' ), $tiles, celb_ws_button( __( 'Add template', 'celb-mgmt' ), admin_url( 'post-new.php?post_type=celb_ctpl' ) ) );
}
function celb_ws_tpl_usage( $tpl ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( celb_ws_ids( 'celb_contract' ) as $cid ) {
			$t = (int) get_post_meta( $cid, '_contract_tpl', true );
			$map[ $t ] = isset( $map[ $t ] ) ? $map[ $t ] + 1 : 1;
		}
	}
	return isset( $map[ $tpl ] ) ? $map[ $tpl ] : 0;
}
add_filter( 'manage_celb_ctpl_posts_columns', function ( $c ) {
	return array(
		'cb'        => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'title'     => __( 'Template', 'celb-mgmt' ),
		'ws_lang'   => __( 'Language', 'celb-mgmt' ),
		'ws_fields' => __( 'Fields the talent fills in', 'celb-mgmt' ),
		'ws_used'   => __( 'Contracts', 'celb-mgmt' ),
		'date'      => __( 'Updated', 'celb-mgmt' ),
	);
}, 100 );
add_action( 'manage_celb_ctpl_posts_custom_column', function ( $col, $id ) {
	if ( 'ws_lang' === $col ) {
		echo 'ar' === get_post_meta( $id, '_ctpl_lang', true ) ? celb_ws_pill( 'العربية', 'slate' ) : celb_ws_pill( 'English', 'slate' ); // phpcs:ignore WordPress.Security.EscapeOutput
	} elseif ( 'ws_fields' === $col ) {
		$f = celb_ctpl_fields( $id );
		echo '<span class="cs-cats">';
		foreach ( array_slice( $f, 0, 4 ) as $row ) {
			echo '<span class="cs-cat">' . esc_html( $row['label'] ) . ( ! empty( $row['required'] ) ? ' *' : '' ) . '</span>';
		}
		if ( count( $f ) > 4 ) {
			echo '<span class="cs-cat is-auto">+' . ( count( $f ) - 4 ) . '</span>';
		}
		echo $f ? '' : '<span class="cs-muted">—</span>';
		echo '</span>';
	} elseif ( 'ws_used' === $col ) {
		$n = celb_ws_tpl_usage( $id );
		echo $n ? '<a class="cs-plain" href="' . esc_url( celb_ws_list_url( 'celb_contract' ) ) . '">' . (int) $n . '</a>' : '<span class="cs-muted">0</span>';
	}
}, 10, 2 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! celb_ws_is_list_query( $q, 'celb_ctpl' ) || ! celb_ws_get( 'ctpl_lang' ) ) {
		return;
	}
	if ( 'ar' === celb_ws_get( 'ctpl_lang' ) ) {
		$q->set( 'meta_query', array( array( 'key' => '_ctpl_lang', 'value' => 'ar' ) ) );
	} else {
		$q->set( 'meta_query', array( 'relation' => 'OR', array( 'key' => '_ctpl_lang', 'compare' => 'NOT EXISTS' ), array( 'key' => '_ctpl_lang', 'value' => 'ar', 'compare' => '!=' ) ) );
	}
} );

add_action( 'add_meta_boxes_celb_ctpl', function () {
	add_meta_box( 'celb_ctpl_fields', __( 'Fields the talent fills in', 'celb-mgmt' ), 'celb_ws_ctpl_fields_box', 'celb_ctpl', 'normal', 'high' );
	add_meta_box( 'celb_ctpl_lang', __( 'Language', 'celb-mgmt' ), 'celb_ws_ctpl_lang_box', 'celb_ctpl', 'side', 'high' );
	add_meta_box( 'celb_ctpl_help', __( 'Placeholders', 'celb-mgmt' ), 'celb_ws_ctpl_tokens_box', 'celb_ctpl', 'side', 'default' );
}, 20 );

function celb_ws_cf_row( $i, $f ) {
	$f = wp_parse_args( is_array( $f ) ? $f : array(), array( 'label' => '', 'key' => '', 'type' => 'text', 'required' => 0 ) );
	$n = 'celb_fields[' . $i . ']';
	echo '<div class="cs-row cs-row--cf">';
	echo celb_studio_row_handle(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[label]" value="' . esc_attr( $f['label'] ) . '" placeholder="' . esc_attr__( 'Field label, e.g. Full Name', 'celb-mgmt' ) . '" data-cs-cf-label />';
	echo '<div class="cs-cf-key"><input type="text" class="cs-input cs-mono" name="' . esc_attr( $n ) . '[key]" value="' . esc_attr( $f['key'] ) . '" placeholder="full_name" data-cs-cf-key' . ( $f['key'] ? ' data-touched="1"' : '' ) . ' />';
	echo '<button type="button" class="cs-token cs-token--btn" data-cs-insert="{{' . esc_attr( $f['key'] ) . '}}" title="' . esc_attr__( 'Insert into the contract text', 'celb-mgmt' ) . '">{{' . esc_html( $f['key'] ? $f['key'] : 'key' ) . '}}</button></div>';
	echo celb_ws_select( $n . '[type]', celb_ctpl_field_types(), $f['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<label class="cs-chip"><input type="checkbox" name="' . esc_attr( $n ) . '[required]" value="1" ' . checked( ! empty( $f['required'] ), true, false ) . ' /><span>' . esc_html__( 'Required', 'celb-mgmt' ) . '</span></label>';
	echo celb_studio_row_remove(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}
function celb_ws_ctpl_fields_box( $post ) {
	wp_nonce_field( 'celb_ctpl_save', 'celb_ctpl_nonce' );
	$fields = celb_ctpl_fields( $post->ID );
	if ( empty( $fields ) && 'auto-draft' === $post->post_status ) {
		$fields = array(
			array( 'label' => 'Full Name', 'key' => 'full_name', 'type' => 'text', 'required' => 1 ),
			array( 'label' => 'National ID / Passport Number', 'key' => 'national_id', 'type' => 'text', 'required' => 1 ),
			array( 'label' => 'Email Address', 'key' => 'email', 'type' => 'email', 'required' => 1 ),
		);
	}
	echo '<p class="cs-help cs-help--top">' . esc_html__( 'Each field gets a key you drop into the contract text as a placeholder. Click a placeholder to insert it where your cursor is. Keep one field with the key “email” — that address receives the signed PDF.', 'celb-mgmt' ) . '</p>';
	$has_email = false;
	foreach ( $fields as $f ) {
		if ( isset( $f['key'] ) && 'email' === $f['key'] ) {
			$has_email = true;
		}
	}
	if ( $fields && ! $has_email ) {
		echo '<div class="cs-note">' . celb_studio_icon( 'alert', 16 ) . '<span>' . esc_html__( 'No field uses the key “email”, so the signed PDF cannot be emailed to the talent.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	celb_studio_repeater( 'cf', $fields, 'celb_ws_cf_row', array(
		'add'   => __( 'Add field', 'celb-mgmt' ),
		'cols'  => array( __( 'Label', 'celb-mgmt' ), __( 'Key / placeholder', 'celb-mgmt' ), __( 'Type', 'celb-mgmt' ), '' ),
		'icon'  => 'pen',
		'empty' => __( 'No fields yet — the talent will only sign.', 'celb-mgmt' ),
	) );
}
function celb_ws_ctpl_lang_box( $post ) {
	$lang = 'ar' === get_post_meta( $post->ID, '_ctpl_lang', true ) ? 'ar' : 'en';
	echo '<div class="cs-segpick cs-segpick--2">';
	foreach ( array( 'en' => 'English', 'ar' => 'العربية' ) as $k => $l ) {
		echo '<label class="cs-segpick-item"><input type="radio" name="ctpl_lang" value="' . esc_attr( $k ) . '" ' . checked( $lang, $k, false ) . ' /><span>' . esc_html( $l ) . '</span></label>';
	}
	echo '</div><p class="cs-help">' . esc_html__( 'How auto-filled values (fee, payment day, commission table) are written.', 'celb-mgmt' ) . '</p>';
}
function celb_ws_ctpl_tokens_box( $post ) {
	$groups = array(
		__( 'Always available', 'celb-mgmt' ) => array( 'talent_name', 'date' ),
		__( 'Your fields', 'celb-mgmt' )      => array_values( array_filter( wp_list_pluck( celb_ctpl_fields( $post->ID ), 'key' ) ) ),
		__( 'Terms (set per contract)', 'celb-mgmt' ) => array_merge( array( 'commission_table' ), array_map( function ( $k ) { return 'rate_' . $k; }, array_keys( celb_contract_rate_defs() ) ), array( 'monthly_fee', 'monthly_fee_amount', 'currency', 'payment_day' ) ),
	);
	echo '<p class="cs-help cs-help--top">' . esc_html__( 'Click to insert at the cursor.', 'celb-mgmt' ) . '</p>';
	foreach ( $groups as $g => $tokens ) {
		echo '<p class="cs-token-group">' . esc_html( $g ) . '</p><div class="cs-tokens"' . ( __( 'Your fields', 'celb-mgmt' ) === $g ? ' data-cs-field-tokens' : '' ) . '>';
		foreach ( $tokens as $t ) {
			echo '<button type="button" class="cs-token cs-token--btn" data-cs-insert="{{' . esc_attr( $t ) . '}}">{{' . esc_html( $t ) . '}}</button>';
		}
		if ( ! $tokens ) {
			echo '<span class="cs-muted">' . esc_html__( 'Add fields to see them here.', 'celb-mgmt' ) . '</span>';
		}
		echo '</div>';
	}
	echo '<p class="cs-help">' . esc_html__( 'Both signatures (the talent’s and the agency’s from Settings) are added to the PDF automatically.', 'celb-mgmt' ) . '</p>';
}

/* =========================================================================
 * 8. RATE CARDS — list
 * ====================================================================== */

function celb_ws_rate_live( $id ) {
	return get_post_meta( $id, '_rate_enabled', true ) === '1' && '' !== (string) get_post_meta( $id, '_rate_pw', true );
}
function celb_ws_hero_ratecards() {
	$n = array( 'cards' => 0, 'live' => 0, 'off' => 0, 'tpl' => 0 );
	foreach ( celb_ws_ids( CELB_RATE_CPT ) as $id ) {
		if ( celb_rate_is_template( $id ) ) {
			$n['tpl']++;
			continue;
		}
		$n['cards']++;
		$n[ celb_ws_rate_live( $id ) ? 'live' : 'off' ]++;
	}
	$cur   = celb_ws_get( 'rc_view' );
	$tiles = array(
		array( 'label' => __( 'Rate cards', 'celb-mgmt' ), 'num' => $n['cards'], 'url' => celb_ws_list_url( CELB_RATE_CPT ), 'icon' => 'tag', 'active' => '' === $cur ),
		array( 'label' => __( 'Live', 'celb-mgmt' ), 'num' => $n['live'], 'url' => celb_ws_list_url( CELB_RATE_CPT, array( 'rc_view' => 'live' ) ), 'icon' => 'eye', 'active' => 'live' === $cur, 'tone' => 'green' ),
		array( 'label' => __( 'Off', 'celb-mgmt' ), 'num' => $n['off'], 'url' => celb_ws_list_url( CELB_RATE_CPT, array( 'rc_view' => 'off' ) ), 'icon' => 'lock', 'active' => 'off' === $cur ),
		array( 'label' => __( 'Templates', 'celb-mgmt' ), 'num' => $n['tpl'], 'url' => celb_ws_list_url( CELB_RATE_CPT, array( 'rc_view' => 'tpl' ) ), 'icon' => 'copy', 'active' => 'tpl' === $cur ),
	);
	$actions = celb_ws_button( __( 'Rate onboarding', 'celb-mgmt' ), admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-rate-onb' ), 'door', false )
		. celb_ws_button( __( 'New rate card', 'celb-mgmt' ), admin_url( 'post-new.php?post_type=' . CELB_RATE_CPT ) );
	celb_ws_hero( __( 'Rate Cards', 'celb-mgmt' ), __( 'Private, password-protected price lists for each talent.', 'celb-mgmt' ), $tiles, $actions );
}
add_filter( 'manage_' . CELB_RATE_CPT . '_posts_columns', function ( $c ) {
	return array(
		'cb'          => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'title'       => __( 'Rate card', 'celb-mgmt' ),
		'ws_celeb'    => __( 'Talent', 'celb-mgmt' ),
		'ws_status'   => __( 'Status', 'celb-mgmt' ),
		'ws_services' => __( 'Services', 'celb-mgmt' ),
		'ws_link'     => __( 'Link', 'celb-mgmt' ),
		'date'        => __( 'Updated', 'celb-mgmt' ),
	);
}, 100 );
add_action( 'manage_' . CELB_RATE_CPT . '_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'ws_celeb':
			echo celb_rate_is_template( $id ) ? celb_ws_pill( __( 'Template', 'celb-mgmt' ), 'slate' ) : celb_ws_celeb_chip( get_post_meta( $id, '_rate_celeb', true ), __( 'Unassigned', 'celb-mgmt' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_status':
			if ( celb_rate_is_template( $id ) ) {
				echo '<span class="cs-muted">—</span>';
			} elseif ( celb_ws_rate_live( $id ) ) {
				echo celb_ws_pill( __( 'Live', 'celb-mgmt' ), 'green' ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo celb_ws_pill( __( 'Off', 'celb-mgmt' ), 'grey' ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( get_post_meta( $id, '_rate_enabled', true ) === '1' ) {
					echo '<span class="cs-row-sub">' . esc_html__( 'Needs a password', 'celb-mgmt' ) . '</span>';
				}
			}
			break;
		case 'ws_services':
			$secs  = celb_rate_sections_get( $id );
			$items = 0;
			foreach ( is_array( $secs ) ? $secs : array() as $s ) {
				$items += ! empty( $s['items'] ) && is_array( $s['items'] ) ? count( $s['items'] ) : 0;
			}
			echo '<span class="cs-terms">' . esc_html( sprintf( _n( '%d service', '%d services', $items, 'celb-mgmt' ), $items ) ) . '<br><span class="cs-muted">' . esc_html( sprintf( _n( '%d section', '%d sections', count( (array) $secs ), 'celb-mgmt' ), count( (array) $secs ) ) ) . '</span></span>';
			break;
		case 'ws_link':
			if ( celb_rate_is_template( $id ) ) {
				echo '<span class="cs-muted">—</span>';
				break;
			}
			$url = celb_rate_card_url( $id );
			echo '<button type="button" class="cs-linkchip" data-cs-copy="' . esc_attr( $url ) . '" title="' . esc_attr__( 'Copy link', 'celb-mgmt' ) . '">' . celb_studio_icon( 'link', 14 ) . '<span>/rate/' . esc_html( celb_rate_card_slug( $id ) ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
			break;
	}
}, 10, 2 );
/* The Template pill replaces the " — Template" post state. */
add_filter( 'display_post_states', function ( $states, $post ) {
	if ( CELB_RATE_CPT === $post->post_type && 'ratecards' === celb_studio_screen() ) {
		unset( $states['celb_tpl'] );
	}
	return $states;
}, 20, 2 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! celb_ws_is_list_query( $q, CELB_RATE_CPT ) ) {
		return;
	}
	$v = celb_ws_get( 'rc_view' );
	if ( 'tpl' === $v ) {
		$q->set( 'meta_query', array( array( 'key' => '_rate_is_template', 'value' => '1' ) ) );
		return;
	}
	$ids = array();
	foreach ( celb_ws_ids( CELB_RATE_CPT ) as $id ) {
		if ( celb_rate_is_template( $id ) ) {
			continue;
		}
		if ( 'live' === $v && ! celb_ws_rate_live( $id ) ) {
			continue;
		}
		if ( 'off' === $v && celb_ws_rate_live( $id ) ) {
			continue;
		}
		$ids[] = $id;
	}
	if ( $v ) {
		$q->set( 'post__in', $ids ? $ids : array( 0 ) );
	}
} );

/* =========================================================================
 * 9. NEWSROOM — list
 * ====================================================================== */

function celb_ws_hero_newslist() {
	$c  = wp_count_posts( 'celeb_news' );
	$ar = 0;
	foreach ( celb_ws_ids( 'celeb_news' ) as $id ) {
		if ( '' !== (string) get_post_meta( $id, '_news_title_ar', true ) ) {
			$ar++;
		}
	}
	$cur   = celb_ws_get( 'post_status' );
	$v     = celb_ws_get( 'news_view' );
	$tiles = array(
		array( 'label' => __( 'Articles', 'celb-mgmt' ), 'num' => (int) $c->publish + (int) $c->draft + (int) $c->future + (int) $c->pending, 'url' => celb_ws_list_url( 'celeb_news' ), 'icon' => 'news', 'active' => '' === $cur && '' === $v ),
		array( 'label' => __( 'Published', 'celb-mgmt' ), 'num' => (int) $c->publish, 'url' => celb_ws_list_url( 'celeb_news', array( 'post_status' => 'publish' ) ), 'icon' => 'eye', 'active' => 'publish' === $cur, 'tone' => 'green' ),
		array( 'label' => __( 'Drafts', 'celb-mgmt' ), 'num' => (int) $c->draft, 'url' => celb_ws_list_url( 'celeb_news', array( 'post_status' => 'draft' ) ), 'icon' => 'pen', 'active' => 'draft' === $cur ),
		array( 'label' => __( 'With Arabic', 'celb-mgmt' ), 'num' => $ar, 'url' => celb_ws_list_url( 'celeb_news', array( 'news_view' => 'ar' ) ), 'icon' => 'globe', 'active' => 'ar' === $v ),
	);
	celb_ws_hero( __( 'Newsroom', 'celb-mgmt' ), __( 'Press, announcements and coverage for the roster — in English and Arabic.', 'celb-mgmt' ), $tiles, celb_ws_button( __( 'New article', 'celb-mgmt' ), admin_url( 'post-new.php?post_type=celeb_news' ) ) );
}
add_filter( 'manage_celeb_news_posts_columns', function ( $c ) {
	return array(
		'cb'       => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'ws_thumb' => '<span class="screen-reader-text">' . esc_html__( 'Image', 'celb-mgmt' ) . '</span>',
		'title'    => __( 'Headline', 'celb-mgmt' ),
		'ws_celeb' => __( 'Talent', 'celb-mgmt' ),
		'ws_lang'  => __( 'Languages', 'celb-mgmt' ),
		'ws_media' => __( 'Media', 'celb-mgmt' ),
		'ws_state' => __( 'Status', 'celb-mgmt' ),
		'date'     => __( 'Date', 'celb-mgmt' ),
	);
}, 100 );
add_action( 'manage_celeb_news_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'ws_thumb':
			$u = celb_news_thumb_img( $id, 'medium' );
			echo '<a class="cs-news-thumb" href="' . esc_url( get_edit_post_link( $id ) ) . '" tabindex="-1" aria-hidden="true"' . ( $u ? ' style="background-image:url(' . esc_url( $u ) . ')"' : '' ) . '>' . ( $u ? '' : celb_studio_icon( 'image', 18 ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$loc = (string) get_post_meta( $id, '_news_location', true );
			echo '<span class="cs-row-sub" data-cs-sub>' . esc_html( $loc ) . '</span>';
			break;
		case 'ws_celeb':
			echo celb_ws_celeb_chip( get_post_meta( $id, '_news_celebrity', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_lang':
			echo celb_ws_pill( 'EN', 'slate' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '' !== (string) get_post_meta( $id, '_news_title_ar', true ) ? ' ' . celb_ws_pill( 'AR', 'slate' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ws_media':
			$g = get_post_meta( $id, '_news_gallery', true );
			$l = get_post_meta( $id, '_news_links', true );
			$g = is_array( $g ) ? count( array_filter( $g ) ) : 0;
			$l = is_array( $l ) ? count( $l ) : 0;
			echo '<span class="cs-terms">' . esc_html( sprintf( _n( '%d photo', '%d photos', $g, 'celb-mgmt' ), $g ) ) . '<br><span class="cs-muted">' . esc_html( sprintf( _n( '%d link', '%d links', $l, 'celb-mgmt' ), $l ) ) . '</span></span>';
			break;
		case 'ws_state':
			$st  = get_post_status( $id );
			$lab = array( 'publish' => __( 'Published', 'celb-mgmt' ), 'draft' => __( 'Draft', 'celb-mgmt' ), 'future' => __( 'Scheduled', 'celb-mgmt' ), 'pending' => __( 'Pending', 'celb-mgmt' ), 'private' => __( 'Private', 'celb-mgmt' ) );
			echo celb_ws_pill( isset( $lab[ $st ] ) ? $lab[ $st ] : $st, 'future' === $st ? 'blue' : celb_ws_status_tone( $st ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
	}
}, 10, 2 );
add_filter( 'display_post_states', function ( $states, $post ) {
	return ( 'celeb_news' === $post->post_type && 'newslist' === celb_studio_screen() ) ? array() : $states;
}, 99, 2 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( celb_ws_is_list_query( $q, 'celeb_news' ) && 'ar' === celb_ws_get( 'news_view' ) ) {
		$q->set( 'meta_query', array( array( 'key' => '_news_title_ar', 'value' => '', 'compare' => '!=' ) ) );
	}
} );
add_action( 'restrict_manage_posts', function ( $pt ) {
	if ( 'celeb_news' !== $pt ) {
		return;
	}
	celb_ws_celeb_filter( 'news_celeb_f', celb_ws_get( 'news_celeb_f' ), __( 'All talent', 'celb-mgmt' ) );
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( celb_ws_is_list_query( $q, 'celeb_news' ) && (int) celb_ws_get( 'news_celeb_f' ) ) {
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = array( 'key' => '_news_celebrity', 'value' => (int) celb_ws_get( 'news_celeb_f' ) );
		$q->set( 'meta_query', $mq );
	}
}, 11 );

/* Every list keeps only the Trash view link; the tiles do the filtering. */
foreach ( array( 'celb_project', 'celb_sched', CELB_RATE_CPT, 'celb_ctpl', 'celeb_news' ) as $celb_ws_pt ) {
	add_filter( 'views_edit-' . $celb_ws_pt, function ( $v ) {
		unset( $v['mine'] );
		return $v;
	} );
}
unset( $celb_ws_pt );

/* =========================================================================
 * 10. RATE CARD ONBOARDING (page)
 * ====================================================================== */

function celb_ws_onb_total( $sections ) {
	$n = 0;
	foreach ( is_array( $sections ) ? $sections : array() as $s ) {
		foreach ( ! empty( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : array() as $it ) {
			if ( ! empty( $it['base'] ) ) {
				$n++;
			}
		}
	}
	return $n;
}

function celb_rate_onb_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$all    = celb_rate_all_cards();
	$links  = celb_rate_onb_links();
	$celebs = get_posts( array( 'post_type' => CELB_CPT, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) );
	$subs   = get_posts( array( 'post_type' => 'celb_rateonb', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) );
	$pending = 0;
	foreach ( $subs as $sid ) {
		if ( ! get_post_meta( $sid, '_onb_imported', true ) ) {
			$pending++;
		}
	}
	echo '<div class="wrap cs-page">';
	echo '<h1 class="screen-reader-text">' . esc_html__( 'Rate Card Onboarding', 'celb-mgmt' ) . '</h1><hr class="wp-header-end" />';
	celb_ws_hero(
		__( 'Rate Card Onboarding', 'celb-mgmt' ),
		__( 'Send a talent a private link to fill in their own prices, then import them into a rate card in one click.', 'celb-mgmt' ),
		array(
			array( 'label' => __( 'Active links', 'celb-mgmt' ), 'num' => count( $links ), 'icon' => 'link' ),
			array( 'label' => __( 'Submissions', 'celb-mgmt' ), 'num' => count( $subs ), 'icon' => 'mail' ),
			array( 'label' => __( 'Waiting to import', 'celb-mgmt' ), 'num' => $pending, 'icon' => 'download', 'tone' => $pending ? 'amber' : '' ),
		),
		celb_ws_button( __( 'Rate cards', 'celb-mgmt' ), admin_url( 'edit.php?post_type=' . CELB_RATE_CPT ), 'tag', false )
	);
	if ( isset( $_GET['generated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="cs-toast is-on" data-cs-toast role="status">' . celb_studio_icon( 'check', 16 ) . '<span>' . esc_html__( 'Link generated — copy it below and send it to the talent.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	echo '<div class="cs-page-grid"><div class="cs-col">';

	/* Generate */
	celb_studio_card_open( __( 'New onboarding link', 'celb-mgmt' ), __( 'The talent sees the services and platforms of the card you pick, with every price empty.', 'celb-mgmt' ), 'plus' );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="celb_rate_onb_gen" />';
	wp_nonce_field( 'celb_rate_onb_gen' );
	celb_studio_field_open( __( 'Base it on', 'celb-mgmt' ), 'celb-onb-src' );
	echo '<select class="cs-input" name="src" id="celb-onb-src" required><option value="">' . esc_html__( '— Select a template or rate card —', 'celb-mgmt' ) . '</option>';
	foreach ( array( 'templates' => __( 'Templates', 'celb-mgmt' ), 'cards' => __( 'Rate cards', 'celb-mgmt' ) ) as $grp => $glabel ) {
		if ( ! empty( $all[ $grp ] ) ) {
			echo '<optgroup label="' . esc_attr( $glabel ) . '">';
			foreach ( $all[ $grp ] as $cid => $label ) {
				echo '<option value="' . esc_attr( $cid ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</optgroup>';
		}
	}
	echo '</select>';
	celb_studio_field_close();
	celb_studio_field_open( __( 'For talent (optional)', 'celb-mgmt' ), 'celb-onb-celeb' );
	echo '<select class="cs-input" name="celeb" id="celb-onb-celeb"><option value="0">' . esc_html__( '— Not linked —', 'celb-mgmt' ) . '</option>';
	foreach ( $celebs as $cid ) {
		echo '<option value="' . esc_attr( $cid ) . '">' . esc_html( get_the_title( $cid ) ) . '</option>';
	}
	echo '</select>';
	celb_studio_field_close( __( 'Linking a talent lets the import assign their card automatically.', 'celb-mgmt' ) );
	celb_studio_field_open( __( 'Link ending (optional)', 'celb-mgmt' ), 'celb-onb-slug' );
	echo '<div class="cs-affix"><span class="cs-affix-pre">/' . esc_html( celb_page_slug( 'rateonb_slug' ) ) . '/</span><input type="text" class="cs-input" name="slug" id="celb-onb-slug" placeholder="' . esc_attr__( 'e.g. mai-el-kady', 'celb-mgmt' ) . '" /></div>';
	celb_studio_field_close( __( 'Leave empty for a private random link.', 'celb-mgmt' ) );
	echo '<button type="submit" class="cs-btn cs-btn--primary">' . celb_studio_icon( 'link', 16 ) . '<span>' . esc_html__( 'Generate link', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</form>';
	celb_studio_card_close();

	/* Active links */
	celb_studio_card_open( __( 'Active links', 'celb-mgmt' ), __( 'Anyone with a link can submit prices until you revoke it.', 'celb-mgmt' ), 'link' );
	if ( ! $links ) {
		echo '<div class="cs-rep-empty">' . celb_studio_icon( 'link', 26 ) . '<p>' . esc_html__( 'No links yet.', 'celb-mgmt' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<div class="cs-linklist">';
	foreach ( array_reverse( $links, true ) as $token => $meta ) {
		$url = celb_rate_onb_url( $token );
		$rev = wp_nonce_url( admin_url( 'admin-post.php?action=celb_rate_onb_revoke&token=' . rawurlencode( $token ) ), 'celb_rate_onb_revoke_' . $token );
		echo '<div class="cs-linkitem"><div class="cs-linkitem-head"><div>';
		echo '<p class="cs-linkitem-title">' . ( ! empty( $meta['celeb'] ) ? esc_html( get_the_title( (int) $meta['celeb'] ) ) : esc_html__( 'Not linked to a talent', 'celb-mgmt' ) ) . '</p>';
		echo '<p class="cs-linkitem-sub">' . esc_html( sprintf( __( 'Based on %s', 'celb-mgmt' ), get_the_title( (int) $meta['src'] ) ) ) . ( celb_rate_is_template( (int) $meta['src'] ) ? ' · ' . esc_html__( 'template', 'celb-mgmt' ) : '' ) . ( ! empty( $meta['created'] ) ? ' · ' . esc_html( date_i18n( 'j M Y', (int) $meta['created'] ) ) : '' ) . '</p>';
		echo '</div><a class="cs-btn cs-btn--sm cs-btn--ghost cs-btn--danger" href="' . esc_url( $rev ) . '" onclick="return confirm(\'' . esc_js( __( 'Revoke this link? It will stop working immediately.', 'celb-mgmt' ) ) . '\');">' . esc_html__( 'Revoke', 'celb-mgmt' ) . '</a></div>';
		celb_studio_copy_field( $url, true );
		echo '</div>';
	}
	echo '</div>';
	celb_studio_card_close();

	echo '</div><div class="cs-col">';

	/* Submissions */
	celb_studio_card_open( __( 'Submitted rates', 'celb-mgmt' ), __( 'Import into a new card built from the source, or merge into an existing card (blank prices keep the current value).', 'celb-mgmt' ), 'mail' );
	if ( ! $subs ) {
		echo '<div class="cs-rep-empty">' . celb_studio_icon( 'mail', 26 ) . '<p>' . esc_html__( 'No submissions yet. They appear here as soon as a talent sends their rates.', 'celb-mgmt' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$cards = celb_rate_all_cards( 'cards' );
	foreach ( $subs as $sid ) {
		$src      = (int) get_post_meta( $sid, '_onb_src', true );
		$imported = (int) get_post_meta( $sid, '_onb_imported', true );
		$secs     = get_post_meta( $sid, '_onb_sections', true );
		$cur      = (string) get_post_meta( $sid, '_onb_currency', true );
		$cur      = $cur ? $cur : 'EGP';
		$del      = wp_nonce_url( admin_url( 'admin-post.php?action=celb_rate_onb_del&sub=' . $sid ), 'celb_rate_onb_del_' . $sid );
		echo '<article class="cs-sub' . ( $imported ? ' is-done' : '' ) . '">';
		echo '<header class="cs-sub-head"><span class="cs-ar-avatar">' . esc_html( celb_monogram( get_the_title( $sid ) ) ) . '</span><div>';
		echo '<p class="cs-sub-name">' . esc_html( get_the_title( $sid ) ) . '</p>';
		echo '<p class="cs-linkitem-sub">' . esc_html( sprintf( __( 'Based on %s', 'celb-mgmt' ), get_the_title( $src ) ) ) . ' · ' . esc_html( get_the_date( 'j M Y', $sid ) ) . ' · ' . esc_html( sprintf( _n( '%d price', '%d prices', celb_ws_onb_total( $secs ), 'celb-mgmt' ), celb_ws_onb_total( $secs ) ) ) . '</p></div>';
		echo $imported ? celb_ws_pill( __( 'Imported', 'celb-mgmt' ), 'green' ) : celb_ws_pill( __( 'New', 'celb-mgmt' ), 'blue' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</header>';
		echo '<details class="cs-sub-prices"><summary>' . esc_html__( 'View submitted prices', 'celb-mgmt' ) . '</summary>';
		foreach ( is_array( $secs ) ? $secs : array() as $s ) {
			if ( empty( $s['items'] ) ) {
				continue;
			}
			echo '<p class="cs-token-group">' . esc_html( isset( $s['title'] ) ? $s['title'] : '' ) . '</p><table class="cs-pricetable"><tbody>';
			foreach ( $s['items'] as $it ) {
				$b = ! empty( $it['base'] ) ? number_format( (float) $it['base'] ) . ' ' . $cur : '—';
				$a = ! empty( $it['addl'] ) ? '+' . number_format( (float) $it['addl'] ) . ' ' . __( 'each extra', 'celb-mgmt' ) : '';
				echo '<tr><td>' . esc_html( isset( $it['name'] ) ? $it['name'] : '' ) . '</td><td><b>' . esc_html( $b ) . '</b>' . ( $a ? '<small>' . esc_html( $a ) . '</small>' : '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</details>';
		echo '<form class="cs-sub-import" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="celb_rate_onb_import" /><input type="hidden" name="sub" value="' . esc_attr( $sid ) . '" />';
		wp_nonce_field( 'celb_rate_onb_import_' . $sid );
		echo '<select class="cs-input" name="target"><option value="new">' . esc_html__( 'New rate card (from the source)', 'celb-mgmt' ) . '</option>';
		foreach ( $cards as $cid => $label ) {
			echo '<option value="' . esc_attr( $cid ) . '" ' . selected( $imported, $cid, false ) . '>' . esc_html( sprintf( __( 'Merge into: %s', 'celb-mgmt' ), $label ) ) . '</option>';
		}
		echo '</select><button type="submit" class="cs-btn cs-btn--sm cs-btn--primary">' . celb_studio_icon( 'download', 14 ) . '<span>' . esc_html( $imported ? __( 'Import again', 'celb-mgmt' ) : __( 'Import', 'celb-mgmt' ) ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $imported && get_post( $imported ) ) {
			echo '<a class="cs-btn cs-btn--sm cs-btn--ghost" href="' . esc_url( get_edit_post_link( $imported ) ) . '">' . esc_html__( 'Open card', 'celb-mgmt' ) . '</a>';
		}
		echo '<a class="cs-btn cs-btn--sm cs-btn--ghost cs-btn--danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this submission?', 'celb-mgmt' ) ) . '\');">' . esc_html__( 'Delete', 'celb-mgmt' ) . '</a>';
		echo '</form></article>';
	}
	celb_studio_card_close();

	echo '</div></div></div>';
}

/* =========================================================================
 * 11. PERSONAL DATA — form builder (page)
 * ====================================================================== */

function celb_pdata_builder_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$schema = celb_pdata_schema();
	$types  = celb_pdata_field_types();
	$s      = celb_get_settings();
	$qcount = 0;
	foreach ( $schema as $sec ) {
		$qcount += count( (array) $sec['questions'] );
	}
	$live = ! empty( $s['pdata_enabled'] ) && '' !== (string) $s['pdata_password'];
	echo '<div class="wrap cs-page">';
	echo '<h1 class="screen-reader-text">' . esc_html__( 'Personal Data — Form Builder', 'celb-mgmt' ) . '</h1><hr class="wp-header-end" />';
	celb_ws_hero(
		__( 'Personal Data — Form', 'celb-mgmt' ),
		__( 'Build the secure personal details and emergency contacts form. Drag sections and questions to reorder.', 'celb-mgmt' ),
		array(
			array( 'label' => __( 'Sections', 'celb-mgmt' ), 'num' => count( $schema ), 'icon' => 'layout' ),
			array( 'label' => __( 'Questions', 'celb-mgmt' ), 'num' => $qcount, 'icon' => 'list' ),
			array( 'label' => __( 'Submissions', 'celb-mgmt' ), 'num' => count( get_posts( array( 'post_type' => 'celb_pdata', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'url' => admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-pdata' ), 'icon' => 'users' ),
		),
		celb_ws_button( __( 'Submissions', 'celb-mgmt' ), admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-pdata' ), 'users', false )
	);
	if ( isset( $_GET['celb_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="cs-toast is-on" data-cs-toast role="status">' . celb_studio_icon( 'check', 16 ) . '<span>' . esc_html__( 'Form saved', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cs-pdb" data-cs-pdb>';
	echo '<input type="hidden" name="action" value="celb_pdata_save" />';
	wp_nonce_field( 'celb_pdata_save', 'celb_pdata_save_nonce' );

	echo '<div class="cs-page-grid cs-page-grid--main"><div class="cs-col">';
	echo '<div class="cs-pdb-sections" data-cs-pdb-sections>';
	foreach ( $schema as $si => $sec ) {
		celb_pdb_section_html( $si, $sec, $types );
	}
	echo '</div>';
	echo '<button type="button" class="cs-gallery-add cs-pdb-addsec" data-cs-pdb-addsec>' . celb_studio_icon( 'plus', 20 ) . '<span>' . esc_html__( 'Add section', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div><div class="cs-col cs-col--sticky">';

	celb_studio_card_open( __( 'Publish', 'celb-mgmt' ), '', 'check' );
	echo '<div class="cs-status ' . ( $live ? 'cs-status--ok' : 'cs-status--off' ) . '">' . celb_studio_icon( $live ? 'check' : 'lock', 15 ) . '<span>' . esc_html( $live ? __( 'The page is live', 'celb-mgmt' ) : __( 'The page is off', 'celb-mgmt' ) ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_copy_field( celb_pdata_url(), true );
	echo '<p class="cs-help">' . sprintf( esc_html__( 'Turn it on and set the password in %s.', 'celb-mgmt' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-settings#pdata' ) ) . '">' . esc_html__( 'Settings → Personal data', 'celb-mgmt' ) . '</a>' ) . '</p>';
	echo '<button type="submit" class="cs-btn cs-btn--primary cs-btn--block cs-mt">' . celb_studio_icon( 'check', 16 ) . '<span>' . esc_html__( 'Save form', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p class="cs-help">' . esc_html__( 'Past submissions keep the questions they were answered with.', 'celb-mgmt' ) . '</p>';
	celb_studio_card_close();

	celb_studio_card_open( __( 'Field types', 'celb-mgmt' ), '', 'list' );
	echo '<div class="cs-cats">';
	foreach ( $types as $tl ) {
		echo '<span class="cs-cat">' . esc_html( $tl ) . '</span>';
	}
	echo '</div><p class="cs-help">' . esc_html__( 'Dropdown options are comma-separated. Phone and email answers become tap-to-call and mailto links in the submissions.', 'celb-mgmt' ) . '</p>';
	celb_studio_card_close();

	echo '</div></div></form>';
	echo '<template id="celb-pdb-sec-tpl">';
	celb_pdb_section_html( '__S__', array( 'id' => '', 'title' => '', 'questions' => array() ), $types );
	echo '</template><template id="celb-pdb-q-tpl">';
	celb_pdb_question_html( '__S__', '__Q__', array(), $types );
	echo '</template></div>';
}
function celb_pdb_section_html( $si, $sec, $types ) {
	$title = isset( $sec['title'] ) ? $sec['title'] : '';
	$secid = isset( $sec['id'] ) ? $sec['id'] : '';
	$qs    = ! empty( $sec['questions'] ) ? $sec['questions'] : array();
	echo '<section class="cs-card cs-pdb-sec" data-sid="' . esc_attr( $si ) . '">';
	echo '<input type="hidden" name="sections[' . esc_attr( $si ) . '][id]" value="' . esc_attr( $secid ) . '" />';
	echo '<header class="cs-pdb-sec-head">';
	echo '<span class="cs-row-handle cs-pdb-drag-sec" title="' . esc_attr__( 'Drag to reorder section', 'celb-mgmt' ) . '">' . celb_studio_icon( 'grip', 16 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" class="cs-input cs-pdb-sec-title" name="sections[' . esc_attr( $si ) . '][title]" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr__( 'Section name, e.g. Emergency Information', 'celb-mgmt' ) . '" />';
	echo '<span class="cs-pdb-count"><b data-cs-pdb-count>' . count( $qs ) . '</b> ' . esc_html__( 'questions', 'celb-mgmt' ) . '</span>';
	echo '<button type="button" class="cs-row-remove" data-cs-pdb-delsec title="' . esc_attr__( 'Remove section', 'celb-mgmt' ) . '">' . celb_studio_icon( 'x', 15 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</header><div class="cs-card-body"><div class="cs-pdb-qs">';
	foreach ( $qs as $qi => $q ) {
		celb_pdb_question_html( $si, $qi, $q, $types );
	}
	echo '</div><button type="button" class="cs-btn cs-btn--sm cs-btn--ghost cs-pdb-addq" data-cs-pdb-addq>' . celb_studio_icon( 'plus', 14 ) . '<span>' . esc_html__( 'Add question', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div></section>';
}
function celb_pdb_question_html( $si, $qi, $q, $types ) {
	$label = isset( $q['label'] ) ? $q['label'] : '';
	$type  = isset( $q['type'] ) ? $q['type'] : 'text';
	$req   = ! empty( $q['required'] );
	$qid   = isset( $q['id'] ) ? $q['id'] : '';
	$opts  = ! empty( $q['options'] ) ? implode( ', ', (array) $q['options'] ) : '';
	$base  = 'sections[' . $si . '][q][' . $qi . ']';
	echo '<div class="cs-row cs-row--pdq' . ( 'select' === $type ? ' has-opts' : '' ) . '">';
	echo '<span class="cs-row-handle cs-pdb-drag-q" title="' . esc_attr__( 'Drag to reorder', 'celb-mgmt' ) . '">' . celb_studio_icon( 'grip', 16 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="hidden" name="' . esc_attr( $base ) . '[id]" value="' . esc_attr( $qid ) . '" />';
	echo '<input type="text" class="cs-input" name="' . esc_attr( $base ) . '[label]" value="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'Question', 'celb-mgmt' ) . '" />';
	echo celb_ws_select( $base . '[type]', $types, $type, 'data-cs-pdb-type' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<label class="cs-chip"><input type="checkbox" name="' . esc_attr( $base ) . '[required]" value="1" ' . checked( $req, true, false ) . ' /><span>' . esc_html__( 'Required', 'celb-mgmt' ) . '</span></label>';
	echo '<button type="button" class="cs-row-remove" data-cs-pdb-delq aria-label="' . esc_attr__( 'Remove question', 'celb-mgmt' ) . '">' . celb_studio_icon( 'x', 15 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" class="cs-input cs-pdb-opts" name="' . esc_attr( $base ) . '[options]" value="' . esc_attr( $opts ) . '" placeholder="' . esc_attr__( 'Dropdown options, comma-separated (e.g. Single, Married)', 'celb-mgmt' ) . '" />';
	echo '</div>';
}

/* =========================================================================
 * 12. PERSONAL DATA — submissions (page)
 * ====================================================================== */

/* Delete via its own request, then come back (no output-before-headers). */
add_action( 'admin_post_celb_pd_del', function () {
	$del = isset( $_GET['sub'] ) ? absint( $_GET['sub'] ) : 0;
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'celb_pd_del_' . $del ) ) {
		wp_die( esc_html__( 'Invalid request.', 'celb-mgmt' ) );
	}
	if ( $del && 'celb_pdata' === get_post_type( $del ) ) {
		wp_delete_post( $del, true );
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-pdata&deleted=1' ) );
	exit;
} );

function celb_pdata_submissions_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$subs = get_posts( array( 'post_type' => 'celb_pdata', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC' ) );
	$month = 0;
	foreach ( $subs as $p ) {
		if ( get_the_date( 'Y-m', $p ) === current_time( 'Y-m' ) ) {
			$month++;
		}
	}
	echo '<div class="wrap cs-page">';
	echo '<h1 class="screen-reader-text">' . esc_html__( 'Personal Data — Submissions', 'celb-mgmt' ) . '</h1><hr class="wp-header-end" />';
	celb_ws_hero(
		__( 'Personal Data', 'celb-mgmt' ),
		__( 'Private details and emergency contacts sent by the talent. Handle with care.', 'celb-mgmt' ),
		array(
			array( 'label' => __( 'Submissions', 'celb-mgmt' ), 'num' => count( $subs ), 'icon' => 'users' ),
			array( 'label' => __( 'This month', 'celb-mgmt' ), 'num' => $month, 'icon' => 'calendar' ),
		),
		'<div class="cs-settings-search">' . celb_studio_icon( 'search', 16 ) . '<input type="search" class="cs-input" placeholder="' . esc_attr__( 'Search by name or answer…', 'celb-mgmt' ) . '" data-cs-pd-search /></div>' . celb_ws_button( __( 'Edit form', 'celb-mgmt' ), admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-pdata-form' ), 'pen', false )
	);
	if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="cs-toast is-on" data-cs-toast role="status">' . celb_studio_icon( 'check', 16 ) . '<span>' . esc_html__( 'Submission deleted', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( ! $subs ) {
		echo '<div class="cs-card"><div class="cs-card-body"><div class="cs-rep-empty">' . celb_studio_icon( 'users', 26 ) . '<p>' . esc_html__( 'No submissions yet. Share the secure page link with your talent to collect their details.', 'celb-mgmt' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		celb_studio_copy_field( celb_pdata_url(), true );
		echo '</div></div></div></div>';
		return;
	}
	echo '<p class="cs-search-empty" data-cs-pd-empty hidden>' . esc_html__( 'No submissions match your search.', 'celb-mgmt' ) . '</p>';
	echo '<div class="cs-pdc-grid">';
	foreach ( $subs as $sub ) {
		$answers = (array) get_post_meta( $sub->ID, '_pd_answers', true );
		$snap    = get_post_meta( $sub->ID, '_pd_schema', true );
		$snap    = is_array( $snap ) && $snap ? $snap : celb_pdata_schema();
		$nm      = (string) get_post_meta( $sub->ID, '_pd_name', true );
		$nm      = '' !== $nm ? $nm : __( 'Submission', 'celb-mgmt' );
		$del     = wp_nonce_url( admin_url( 'admin-post.php?action=celb_pd_del&sub=' . $sub->ID ), 'celb_pd_del_' . $sub->ID );
		$plain   = $nm . "\n" . get_the_date( 'j M Y, H:i', $sub ) . "\n";
		ob_start();
		foreach ( $snap as $sec ) {
			$rows = '';
			$txt  = '';
			foreach ( (array) $sec['questions'] as $q ) {
				$v = isset( $answers[ $q['id'] ] ) ? trim( (string) $answers[ $q['id'] ] ) : '';
				if ( '' === $v ) {
					continue;
				}
				$type = isset( $q['type'] ) ? $q['type'] : 'text';
				if ( 'tel' === $type ) {
					$val = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>';
				} elseif ( 'email' === $type && is_email( $v ) ) {
					$val = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
				} elseif ( 'location' === $type && preg_match( '#^https?://#', $v ) ) {
					$val = '<a href="' . esc_url( $v ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open map', 'celb-mgmt' ) . '</a>';
				} else {
					$val = nl2br( esc_html( $v ) );
				}
				$rows .= '<div class="cs-pdc-row"><dt>' . esc_html( $q['label'] ) . '</dt><dd>' . $val . '</dd></div>';
				$txt  .= $q['label'] . ': ' . $v . "\n";
			}
			if ( '' === $rows ) {
				continue;
			}
			$em = false !== stripos( (string) $sec['title'], 'emergenc' );
			echo '<div class="cs-pdc-sec' . ( $em ? ' is-emergency' : '' ) . '"><p class="cs-pdc-sec-t">' . ( $em ? celb_studio_icon( 'alert', 13 ) : '' ) . esc_html( $sec['title'] ) . '</p><dl>' . $rows . '</dl></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$plain .= "\n" . $sec['title'] . "\n" . $txt;
		}
		$body = ob_get_clean();
		echo '<article class="cs-card cs-pdc" data-cs-pd-card>';
		echo '<header class="cs-pdc-head"><span class="cs-ar-avatar cs-ar-avatar--md">' . esc_html( celb_monogram( $nm ) ) . '</span><div><p class="cs-pdc-name">' . esc_html( $nm ) . '</p><p class="cs-linkitem-sub">' . esc_html( get_the_date( 'j M Y, H:i', $sub ) ) . '</p></div>';
		echo '<div class="cs-pdc-tools"><button type="button" class="cs-btn cs-btn--sm cs-btn--ghost" data-cs-copy="' . esc_attr( $plain ) . '" title="' . esc_attr__( 'Copy all answers as text', 'celb-mgmt' ) . '">' . celb_studio_icon( 'copy', 14 ) . '<span>' . esc_html__( 'Copy', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<a class="cs-btn cs-btn--sm cs-btn--ghost cs-btn--danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this submission permanently?', 'celb-mgmt' ) ) . '\');" title="' . esc_attr__( 'Delete', 'celb-mgmt' ) . '">' . celb_studio_icon( 'x', 14 ) . '</a></div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $body ? $body : '<p class="cs-muted cs-pdc-empty">' . esc_html__( 'No answers.', 'celb-mgmt' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</article>';
	}
	echo '</div></div>';
}
