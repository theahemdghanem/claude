<?php
/**
 * CELB MGMT — CELB Talent app.
 *
 * The talent's own installable app (replaces the talent side of the old
 * [CLEB_manage] shortcode app). A talent account is a WordPress user linked
 * to one celebrity profile (App login box on the profile); it only ever sees
 * that profile's schedule, projects and contracts.
 *
 *   /{talent_slug}/                 app shell (served by includes/app.php)
 *   /wp-json/celb-talent/v1/…       JSON API below
 *
 * Talent can block their own time (Personal / Unavailable …) and edit or
 * remove what they added; agency bookings are read-only for them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. HELPERS
 * ====================================================================== */

/* User IDs linked to a celebrity profile. */
function celb_talent_users( $celeb ) {
	$celeb = (int) $celeb;
	if ( ! $celeb ) {
		return array();
	}
	return array_map( 'intval', get_users( array( 'meta_key' => '_celb_celebrity', 'meta_value' => $celeb, 'fields' => 'ID' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

/* Entries this talent added themselves (editable in the app). */
function celb_talent_owns_sched( $id ) {
	$by = (int) get_post_meta( $id, '_sched_by', true );
	return $by && get_current_user_id() === $by;
}

function celb_talent_sched( $id ) {
	$d             = celb_sched_data( $id );
	$d['status']   = (string) get_post_meta( $id, '_sched_status', true );
	$d['new_date'] = (string) get_post_meta( $id, '_sched_new_date', true );
	$d['new_time'] = (string) get_post_meta( $id, '_sched_new_time', true );
	$d['celeb']    = celb_app_celeb_brief( $d['celeb'] );
	$d['mine']     = celb_talent_owns_sched( $id );
	unset( $d['reminder'] );
	return $d;
}

function celb_talent_contracts( $celeb ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'celb_contract', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_contract_celeb', 'value' => (int) $celeb ) ) ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$c        = celb_contract_app_data( $id, false );
		$fee      = celb_contract_fee_amount_str( $id );
		$c['fee'] = '' !== $fee ? $fee . ' ' . celb_contract_monthly( $id )['currency'] : '';
		$out[]    = $c;
	}
	return $out;
}

function celb_talent_projects( $celeb ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'celb_project', 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_proj_celeb', 'value' => (int) $celeb ) ) ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$p = celb_app_project( $id );
		unset( $p['admin'] );
		$out[] = $p;
	}
	return $out;
}

/* =========================================================================
 * 2. REST API
 * ====================================================================== */

add_action( 'rest_api_init', function () {
	$ns  = 'celb-talent/v1';
	$can = array( 'permission_callback' => 'celb_talent_can' );
	$r   = function ( $path, $methods, $cb ) use ( $ns, $can ) {
		register_rest_route( $ns, $path, array( array( 'methods' => $methods, 'callback' => $cb ) + $can ) );
	};
	$r( '/home', 'GET', 'celb_talent_rest_home' );
	$r( '/calendar', 'GET', 'celb_talent_rest_calendar' );
	$r( '/sched', 'POST', 'celb_talent_rest_sched_create' );
	$r( '/sched/(?P<id>\d+)', 'GET', 'celb_talent_rest_sched_get' );
	$r( '/sched/(?P<id>\d+)', 'POST', 'celb_talent_rest_sched_update' );
	$r( '/projects', 'GET', 'celb_talent_rest_projects' );
	$r( '/projects/(?P<id>\d+)', 'GET', 'celb_talent_rest_project_get' );
	$r( '/contracts', 'GET', 'celb_talent_rest_contracts' );
	$r( '/profile', 'GET', 'celb_talent_rest_profile' );
	$r( '/prefs', 'GET', 'celb_app_rest_prefs' );
	$r( '/prefs', 'POST', 'celb_app_rest_prefs_save' );
	$r( '/push/(?P<op>key|status|subscribe|unsubscribe|test)', 'POST', array( 'CELB_Push', 'rest' ) );
} );

function celb_talent_404() {
	return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
}

function celb_talent_rest_home() {
	$me       = celb_user_celeb_id();
	$today    = current_time( 'Y-m-d' );
	$agenda   = celb_app_agenda( $today, gmdate( 'Y-m-d', strtotime( $today . ' +30 days' ) ), $me );
	$week     = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
	$con      = celb_talent_contracts( $me );
	$to_sign  = array_values( array_filter( $con, function ( $c ) { return 'signed' !== $c['status'] && $c['sign']; } ) );
	$projects = celb_talent_projects( $me );
	return array(
		'today'   => $today,
		'me'      => celb_app_talent( $me ),
		'counts'  => array(
			'today'    => count( array_filter( $agenda, function ( $i ) use ( $today ) { return $i['date'] === $today; } ) ),
			'week'     => count( array_filter( $agenda, function ( $i ) use ( $week ) { return $i['date'] <= $week; } ) ),
			'month'    => count( $agenda ),
			'sign'     => count( $to_sign ),
			'projects' => count( array_filter( $projects, function ( $p ) { return in_array( $p['status'], array( 'Ongoing', 'Upcoming' ), true ); } ) ),
		),
		'agenda'  => array_slice( $agenda, 0, 6 ),
		'to_sign' => $to_sign,
	);
}

function celb_talent_rest_calendar( $req ) {
	$ok    = function ( $d ) { return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ); };
	$today = current_time( 'Y-m-d' );
	$from  = (string) $req->get_param( 'from' );
	$to    = (string) $req->get_param( 'to' );
	$from  = $ok( $from ) ? $from : $today;
	$to    = $ok( $to ) ? $to : gmdate( 'Y-m-d', strtotime( $today . ' +90 days' ) );
	return array( 'from' => $from, 'to' => $to, 'today' => $today, 'items' => celb_app_agenda( $from, $to, celb_user_celeb_id() ), 'types' => celb_sched_types() );
}

function celb_talent_sched_ok( $id ) {
	return 'celb_sched' === get_post_type( $id ) && 'publish' === get_post_status( $id ) && (int) get_post_meta( $id, '_sched_celeb', true ) === celb_user_celeb_id();
}
function celb_talent_rest_sched_get( $req ) {
	$id = (int) $req['id'];
	return celb_talent_sched_ok( $id ) ? celb_talent_sched( $id ) : celb_talent_404();
}

/* Apply the fields a talent may set on their own entry. */
function celb_talent_apply_sched( $id, $req ) {
	$type = sanitize_text_field( (string) $req->get_param( 'type' ) );
	$type = in_array( $type, celb_sched_types(), true ) ? $type : 'Unavailable';
	$date = (string) $req->get_param( 'date' );
	$time = (string) $req->get_param( 'time' );
	update_post_meta( $id, '_sched_type', $type );
	update_post_meta( $id, '_sched_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_time( 'Y-m-d' ) );
	update_post_meta( $id, '_sched_time', preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '' );
	update_post_meta( $id, '_sched_duration', max( 0, (int) round( floatval( $req->get_param( 'hours' ) ) * 60 ) ) );
	update_post_meta( $id, '_sched_desc', sanitize_textarea_field( (string) $req->get_param( 'note' ) ) );
	$place = sanitize_text_field( (string) $req->get_param( 'place' ) );
	update_post_meta( $id, '_sched_location', '' !== $place ? array( 'label' => $place, 'address' => '', 'map' => '' ) : array() );
	return $type;
}

function celb_talent_rest_sched_create( $req ) {
	$me = celb_user_celeb_id();
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $req->get_param( 'date' ) ) ) {
		return new WP_Error( 'celb_bad', __( 'Pick a date.', 'celb-mgmt' ), array( 'status' => 400 ) );
	}
	$title = sanitize_text_field( (string) $req->get_param( 'title' ) );
	$id    = wp_insert_post( array( 'post_type' => 'celb_sched', 'post_status' => 'publish', 'post_title' => '' !== $title ? $title : __( 'Unavailable', 'celb-mgmt' ) ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_sched_celeb', $me );
	update_post_meta( $id, '_sched_by', get_current_user_id() );
	update_post_meta( $id, '_sched_status', 'Upcoming' );
	$type = celb_talent_apply_sched( $id, $req );
	if ( '' === $title ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $type ) );
	}
	return celb_talent_sched( $id );
}

function celb_talent_rest_sched_update( $req ) {
	$id = (int) $req['id'];
	if ( ! celb_talent_sched_ok( $id ) ) {
		return celb_talent_404();
	}
	if ( ! celb_talent_owns_sched( $id ) ) {
		return new WP_Error( 'celb_forbidden', __( 'Only the agency can change this booking.', 'celb-mgmt' ), array( 'status' => 403 ) );
	}
	if ( rest_sanitize_boolean( $req->get_param( 'delete' ) ) ) {
		wp_trash_post( $id );
		return array( 'deleted' => true );
	}
	$title = sanitize_text_field( (string) $req->get_param( 'title' ) );
	if ( '' !== $title ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
	}
	celb_talent_apply_sched( $id, $req );
	return celb_talent_sched( $id );
}

function celb_talent_rest_projects() {
	return celb_talent_projects( celb_user_celeb_id() );
}
function celb_talent_rest_project_get( $req ) {
	$id = (int) $req['id'];
	if ( 'celb_project' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || (int) get_post_meta( $id, '_proj_celeb', true ) !== celb_user_celeb_id() ) {
		return celb_talent_404();
	}
	$d          = celb_project_data( $id );
	$d['brief'] = celb_app_project( $id );
	unset( $d['brief']['admin'] );
	return $d;
}

function celb_talent_rest_contracts() {
	return celb_talent_contracts( celb_user_celeb_id() );
}

function celb_talent_rest_profile() {
	$me = celb_user_celeb_id();
	$t  = celb_app_talent( $me );
	$t['photo']     = celb_studio_photo_url( $me, 'large' );
	$pub            = 'publish' === $t['status'];
	$t['smartlink'] = $pub ? celb_smartlink_url( $me ) : '';
	$t['profile']   = $pub ? get_permalink( $me ) : '';
	$t['calendar']  = $pub ? celb_cal_feed_url( $me, 'webcal' ) : '';
	$t['bio']       = wp_strip_all_tags( (string) get_post_meta( $me, '_celb_bio', true ) );
	$card           = celb_rate_card_for_celeb( $me );
	$t['rate']      = ( $card && celb_ws_rate_live( $card ) ) ? array( 'title' => get_the_title( $card ), 'url' => celb_rate_card_url( $card ) ) : null;
	$t['socials']   = array();
	foreach ( celb_social_platforms() as $key => $label ) {
		$v = (string) get_post_meta( $me, '_celb_social_' . $key, true );
		if ( '' !== $v ) {
			$t['socials'][] = array( 'key' => $key, 'label' => $label, 'url' => $v );
		}
	}
	unset( $t['lead'], $t['locked'] );
	return $t;
}

/* =========================================================================
 * 3. EVENTS → push
 * ====================================================================== */

/* Collected during the request; built at shutdown once all meta is saved. */
function celb_talent_event( $event, $id, $extra = '' ) {
	static $pending = null;
	if ( null === $pending ) {
		$pending = array();
		register_shutdown_function( function () use ( &$pending ) {
			foreach ( $pending as $job ) {
				celb_talent_dispatch( $job[0], $job[1], $job[2] );
			}
		} );
	}
	$pending[ $event . ':' . (int) $id ] = array( $event, (int) $id, $extra );
}

function celb_talent_when( $id ) {
	$st   = (string) get_post_meta( $id, '_sched_status', true );
	$date = (string) get_post_meta( $id, 'Postponed' === $st ? '_sched_new_date' : '_sched_date', true );
	$time = (string) get_post_meta( $id, 'Postponed' === $st ? '_sched_new_time' : '_sched_time', true );
	return trim( ( $date ? date_i18n( 'D j M', strtotime( $date ) ) : '' ) . ( $time ? ', ' . $time : '' ), ', ' );
}

function celb_talent_dispatch( $event, $id, $extra ) {
	if ( 'publish' !== get_post_status( $id ) ) {
		return;
	}
	$app = celb_talent_url();
	switch ( $event ) {
		case 'sched_new':
			$celeb = (int) get_post_meta( $id, '_sched_celeb', true );
			$by    = (int) get_post_meta( $id, '_sched_by', true );
			if ( $by && celb_talent_user_can( $by ) ) {
				// Talent blocked their own time: tell the agency instead.
				$p = array(
					'title' => __( 'Talent blocked time', 'celb-mgmt' ),
					'body'  => implode( ' · ', array_filter( array( get_the_title( $celeb ), get_the_title( $id ), celb_talent_when( $id ) ) ) ),
					'url'   => celb_app_url() . '#/sched/' . $id,
					'tag'   => 'celb-block-' . $id,
				);
				celb_app_feed_add( 'block', $id, $p );
				CELB_Push::queue( 'block', $p + array( 'icon' => celb_app_icon() ) );
				return;
			}
			$p = array(
				'title' => __( 'New booking', 'celb-mgmt' ),
				'body'  => implode( ' · ', array_filter( array( get_the_title( $id ), (string) get_post_meta( $id, '_sched_type', true ), celb_talent_when( $id ) ) ) ),
				'url'   => $app . '#/sched/' . $id,
				'tag'   => 'celb-sched-' . $id,
			);
			CELB_Push::queue( 'sched', $p + array( 'icon' => celb_app_icon() ), 'talent', celb_talent_users( $celeb ) );
			return;
		case 'sched_status':
			$celeb = (int) get_post_meta( $id, '_sched_celeb', true );
			/* translators: %s: new status. */
			$what = 'Postponed' === $extra ? sprintf( __( 'Postponed to %s', 'celb-mgmt' ), celb_talent_when( $id ) ) : $extra;
			$p    = array(
				'title' => __( 'Schedule update', 'celb-mgmt' ),
				'body'  => get_the_title( $id ) . ' — ' . $what,
				'url'   => $app . '#/sched/' . $id,
				'tag'   => 'celb-sched-' . $id,
			);
			CELB_Push::queue( 'sched', $p + array( 'icon' => celb_app_icon() ), 'talent', celb_talent_users( $celeb ) );
			return;
		case 'project_new':
		case 'project_status':
			$celeb = (int) get_post_meta( $id, '_proj_celeb', true );
			$p     = array(
				'title' => 'project_new' === $event ? __( 'New project', 'celb-mgmt' ) : __( 'Project update', 'celb-mgmt' ),
				'body'  => get_the_title( $id ) . ( 'project_new' === $event ? '' : ' — ' . $extra ),
				'url'   => $app . '#/project/' . $id,
				'tag'   => 'celb-project-' . $id,
			);
			CELB_Push::queue( 'project', $p + array( 'icon' => celb_app_icon() ), 'talent', celb_talent_users( $celeb ) );
			return;
		case 'contract_new':
			$celeb = (int) get_post_meta( $id, '_contract_celeb', true );
			if ( 'signed' === get_post_meta( $id, '_contract_status', true ) ) {
				return;
			}
			$p = array(
				'title' => __( 'Contract to sign', 'celb-mgmt' ),
				'body'  => get_the_title( $id ),
				'url'   => $app . '#/contracts',
				'tag'   => 'celb-contract-' . $id,
			);
			CELB_Push::queue( 'contract', $p + array( 'icon' => celb_app_icon() ), 'talent', celb_talent_users( $celeb ) );
			return;
	}
}

/* Newly published bookings, projects and contracts (admin or app). */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old ) {
		return;
	}
	$map = array( 'celb_sched' => 'sched_new', 'celb_project' => 'project_new', 'celb_contract' => 'contract_new' );
	if ( isset( $map[ $post->post_type ] ) ) {
		celb_talent_event( $map[ $post->post_type ], $post->ID );
	}
}, 10, 3 );

/* Status changes made in wp-admin or the Studio app. */
add_action( 'celb_status_changed', function ( $kind, $post_id, $old, $new ) {
	if ( $old && $old !== $new ) {
		celb_talent_event( 'project' === $kind ? 'project_status' : 'sched_status', $post_id, (string) $new );
	}
}, 10, 4 );
