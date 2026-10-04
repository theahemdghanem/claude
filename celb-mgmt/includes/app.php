<?php
/**
 * CELB MGMT — CELB Studio app.
 *
 * One installable web app (PWA) for managers that brings every module
 * together: inbox (artist + booking requests), calendar (schedule + shooting
 * days), roster, projects, newsroom, contracts, rate cards, rate onboarding
 * and personal data — with an activity feed and Web Push notifications.
 *
 *   /{app_slug}/                       app shell (logged-in managers only)
 *   /{app_slug}/sw.js                  service worker (scope: the app)
 *   /{app_slug}/manifest.webmanifest   web app manifest
 *   /wp-json/celb-app/v1/…             JSON API used by the app
 *
 * The Talent app (includes/app-talent.php) is served the same way from
 * /{talent_slug}/ with its own API under /wp-json/celb-talent/v1/.
 *
 * The app reads and writes the same data as wp-admin; for long forms it links
 * straight to the matching admin editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CELB_PATH . 'includes/app-push.php';
require_once CELB_PATH . 'includes/app-talent.php';

/* =========================================================================
 * 0. BASICS
 * ====================================================================== */

function celb_app_url( $which = 'studio' ) {
	return home_url( '/' . celb_page_slug( 'talent' === $which ? 'talent_slug' : 'app_slug' ) . '/' );
}
function celb_talent_url() {
	return celb_app_url( 'talent' );
}
/* Talent accounts: users linked to a celebrity profile. */
function celb_talent_user_can( $uid = 0 ) {
	return celb_user_celeb_id( $uid ? (int) $uid : get_current_user_id() ) > 0;
}
function celb_talent_can() {
	return celb_talent_user_can();
}
/* The app a signed-in user belongs in ('' when none). */
function celb_user_app_url( $uid = 0 ) {
	if ( celb_app_user_can( $uid ) ) {
		return celb_app_url();
	}
	return celb_talent_user_can( $uid ) ? celb_talent_url() : '';
}
function celb_app_name( $which = 'studio' ) {
	$s = celb_get_settings();
	if ( 'talent' === $which ) {
		return ! empty( $s['talent_name'] ) ? $s['talent_name'] : __( 'CELB Talent', 'celb-mgmt' );
	}
	return ! empty( $s['app_name'] ) ? $s['app_name'] : __( 'CELB Studio', 'celb-mgmt' );
}
function celb_app_user_can( $uid = 0 ) {
	$uid = $uid ? (int) $uid : get_current_user_id();
	return $uid && user_can( $uid, 'edit_others_posts' );
}
function celb_app_can() {
	return celb_app_user_can();
}
function celb_app_can_admin() {
	return current_user_can( 'manage_options' );
}

/* Notification events a user can switch on/off, per app. */
function celb_app_events( $which = 'studio' ) {
	if ( 'talent' === $which ) {
		return array(
			'sched'    => __( 'New and changed bookings', 'celb-mgmt' ),
			'project'  => __( 'New projects', 'celb-mgmt' ),
			'contract' => __( 'Contracts to sign', 'celb-mgmt' ),
		);
	}
	return array(
		'artreq'   => __( 'New artist requests', 'celb-mgmt' ),
		'booking'  => __( 'New booking requests', 'celb-mgmt' ),
		'contract' => __( 'Contracts signed', 'celb-mgmt' ),
		'rateonb'  => __( 'Rate card submissions', 'celb-mgmt' ),
		'pdata'    => __( 'Personal data received', 'celb-mgmt' ),
		'block'    => __( 'Talent blocked time', 'celb-mgmt' ),
	);
}
function celb_app_prefs( $uid = 0, $which = 'studio' ) {
	$uid = $uid ? (int) $uid : get_current_user_id();
	$p   = get_user_meta( $uid, 'talent' === $which ? '_celb_talent_prefs' : '_celb_app_prefs', true );
	$p   = is_array( $p ) ? $p : array();
	$out = array();
	foreach ( array_keys( celb_app_events( $which ) ) as $k ) {
		$out[ $k ] = isset( $p[ $k ] ) ? (bool) $p[ $k ] : true;
	}
	return $out;
}

/* =========================================================================
 * 1. ROUTES — shell, service worker, manifest
 * ====================================================================== */

add_action( 'init', function () {
	foreach ( array( 'studio' => 'app_slug', 'talent' => 'talent_slug' ) as $which => $key ) {
		$slug = preg_quote( celb_page_slug( $key ), '/' );
		$app  = 'talent' === $which ? 't-' : '';
		add_rewrite_rule( '^' . $slug . '/sw\\.js$', 'index.php?celb_app=' . $app . 'sw', 'top' );
		add_rewrite_rule( '^' . $slug . '/manifest\\.webmanifest$', 'index.php?celb_app=' . $app . 'manifest', 'top' );
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?celb_app=' . $app . 'shell', 'top' );
	}
}, 5 );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'celb_app';
	return $v;
} );
add_filter( 'redirect_canonical', function ( $redirect ) {
	return get_query_var( 'celb_app' ) ? false : $redirect;
}, 5 );

add_action( 'template_redirect', function () {
	$what = (string) get_query_var( 'celb_app' );
	if ( '' === $what ) {
		return;
	}
	$which = 0 === strpos( $what, 't-' ) ? 'talent' : 'studio';
	$what  = preg_replace( '/^t-/', '', $what );
	if ( 'sw' === $what ) {
		celb_app_serve_sw( $which );
	} elseif ( 'manifest' === $what ) {
		celb_app_serve_manifest( $which );
	} else {
		celb_app_serve_shell( $which );
	}
	exit;
}, 0 );

/* Home-screen icon for both apps: Settings → Studio app, else the brand logo. */
function celb_app_icon() {
	$s = celb_get_settings();
	if ( ! empty( $s['pwa_icon'] ) ) {
		return $s['pwa_icon'];
	}
	if ( ! empty( $s['brand_logo_url'] ) ) {
		return $s['brand_logo_url'];
	}
	return celb_logo_url();
}

function celb_app_serve_sw( $which = 'studio' ) {
	nocache_headers();
	$scope = wp_parse_url( celb_app_url( $which ), PHP_URL_PATH );
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: ' . $scope );
	$js = (string) file_get_contents( CELB_PATH . 'assets/app/sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	echo str_replace( array( '__CELB_VERSION__', '__CELB_SCOPE__', '__CELB_APP__' ), array( CELB_VERSION, esc_js( $scope ), $which ), $js ); // phpcs:ignore WordPress.Security.EscapeOutput
}

function celb_app_serve_manifest( $which = 'studio' ) {
	$name = celb_app_name( $which );
	$icon = celb_app_icon();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( array(
		'name'             => $name,
		'short_name'       => $name,
		'description'      => 'talent' === $which ? __( 'Your schedule, projects and contracts.', 'celb-mgmt' ) : __( 'Manage the roster, inbox, calendar, contracts and more.', 'celb-mgmt' ),
		'id'               => wp_parse_url( celb_app_url( $which ), PHP_URL_PATH ),
		'start_url'        => celb_app_url( $which ),
		'scope'            => celb_app_url( $which ),
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'background_color' => '#f4f2ee',
		'theme_color'      => '#0a0a0a',
		'icons'            => array(
			array( 'src' => $icon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
		),
	) );
}

function celb_app_serve_shell( $which = 'studio' ) {
	nocache_headers();
	$url = celb_app_url( $which );
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $url ) );
		exit;
	}
	$ok = 'talent' === $which ? celb_talent_can() : celb_app_can();
	if ( ! $ok ) {
		$other = celb_user_app_url();
		if ( $other && $other !== $url ) {
			wp_safe_redirect( $other );
			exit;
		}
		wp_die( esc_html( 'talent' === $which ? __( 'This app is for talent with a login from the agency.', 'celb-mgmt' ) : __( 'The CELB Studio app is for agency managers.', 'celb-mgmt' ) ), '', array( 'response' => 403 ) );
	}
	$name = celb_app_name( $which );
	$u    = wp_get_current_user();
	$cfg  = array(
		'app_id'   => $which,
		'rest'     => esc_url_raw( rest_url( 'talent' === $which ? 'celb-talent/v1/' : 'celb-app/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'app'      => $url,
		'sw'       => $url . 'sw.js',
		'admin'    => admin_url(),
		'logout'   => wp_logout_url( $url ),
		'site'     => get_bloginfo( 'name' ),
		'name'     => $name,
		'logo'     => celb_logo_url(),
		'user'     => array( 'name' => $u->display_name, 'first' => $u->first_name ? $u->first_name : $u->display_name, 'avatar' => get_avatar_url( $u->ID, array( 'size' => 96 ) ), 'admin' => 'studio' === $which && celb_app_can_admin() ),
		'push'     => CELB_Push::supported(),
		'version'  => CELB_VERSION,
		'events'   => celb_app_events( $which ),
		'accent'   => '#536878',
	);
	if ( 'talent' === $which ) {
		$cid               = celb_user_celeb_id();
		$cfg['celeb']      = celb_app_celeb_brief( $cid );
		$cfg['user']['first'] = $cfg['celeb'] ? strtok( $cfg['celeb']['name'], ' ' ) : $cfg['user']['first'];
	}
	$v       = CELB_VERSION;
	$scripts = array( 'core.js', 'talent' === $which ? 'talent.js' : 'app.js' );
	?><!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title><?php echo esc_html( $name ); ?></title>
<meta name="theme-color" content="#0a0a0a" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $name ); ?>" />
<meta name="robots" content="noindex, nofollow" />
<link rel="manifest" href="<?php echo esc_url( $url . 'manifest.webmanifest' ); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url( celb_app_icon() ); ?>" />
<link rel="icon" href="<?php echo esc_url( celb_app_icon() ); ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alan+Sans:wght@300..900&display=swap" />
<link rel="stylesheet" href="<?php echo esc_url( CELB_URL . 'assets/app/app.css?ver=' . $v ); ?>" />
</head>
<body class="app-<?php echo esc_attr( $which ); ?>">
<div id="app" class="app" aria-live="polite">
	<div class="boot"><div class="boot-logo"><?php if ( celb_logo_url() ) : ?><img src="<?php echo esc_url( celb_logo_url() ); ?>" alt="" /><?php endif; ?></div><div class="boot-spin"></div></div>
</div>
<script>window.CELB_APP = <?php echo wp_json_encode( $cfg ); ?>;</script>
<?php foreach ( $scripts as $js ) : ?>
<script src="<?php echo esc_url( CELB_URL . 'assets/app/' . $js . '?ver=' . $v ); ?>"></script>
<?php endforeach; ?>
</body>
</html>
	<?php
}

/* Fresh REST nonce for an app that has been open for a long time. */
add_action( 'wp_ajax_celb_app_nonce', function () {
	if ( ! celb_app_can() && ! celb_talent_can() ) {
		wp_send_json_error( null, 403 );
	}
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
} );

/* =========================================================================
 * 2. ACTIVITY FEED + PUSH (events)
 * ====================================================================== */

function celb_app_feed() {
	$f = get_option( 'celb_app_feed', array() );
	return is_array( $f ) ? $f : array();
}

/* Collected during the request, built at shutdown (after meta is saved). */
function celb_app_event( $event, $id ) {
	static $pending = null;
	if ( null === $pending ) {
		$pending = array();
		register_shutdown_function( function () use ( &$pending ) {
			foreach ( $pending as $key => $job ) {
				celb_app_dispatch( $job[0], $job[1] );
			}
		} );
	}
	$pending[ $event . ':' . (int) $id ] = array( $event, (int) $id );
}

function celb_app_event_payload( $event, $id ) {
	$app = celb_app_url();
	switch ( $event ) {
		case 'artreq':
			$names = array_map( 'get_the_title', function_exists( 'celb_ar_artist_ids' ) ? celb_ar_artist_ids( $id ) : array() );
			return array(
				'title' => __( 'New artist request', 'celb-mgmt' ),
				'body'  => implode( ' · ', array_filter( array( function_exists( 'celb_ar_name' ) ? celb_ar_name( $id ) : get_the_title( $id ), (string) get_post_meta( $id, '_ar_brand', true ), $names ? implode( ', ', $names ) : __( 'All artists', 'celb-mgmt' ) ) ) ),
				'url'   => $app . '#/inbox/artreq/' . $id,
			);
		case 'booking':
			$d = celb_request_data( $id );
			return array(
				'title' => __( 'New booking request', 'celb-mgmt' ),
				'body'  => implode( ' · ', array_filter( array( $d['name'], $d['celeb_name'], $d['type'] ) ) ),
				'url'   => $app . '#/inbox/booking/' . $id,
			);
		case 'pdata':
			return array(
				'title' => __( 'Personal data received', 'celb-mgmt' ),
				'body'  => (string) get_post_meta( $id, '_pd_name', true ),
				'url'   => $app . '#/pdata',
			);
		case 'rateonb':
			return array(
				'title' => __( 'Rate card submission', 'celb-mgmt' ),
				'body'  => sprintf( __( '%s sent their rates', 'celb-mgmt' ), get_the_title( $id ) ),
				'url'   => $app . '#/onboarding',
			);
		case 'contract':
			$c = (int) get_post_meta( $id, '_contract_celeb', true );
			return array(
				'title' => __( 'Contract signed', 'celb-mgmt' ),
				'body'  => $c ? sprintf( __( '%s signed their contract', 'celb-mgmt' ), get_the_title( $c ) ) : get_the_title( $id ),
				'url'   => $app . '#/contracts',
			);
	}
	return null;
}

function celb_app_dispatch( $event, $id ) {
	$p = celb_app_event_payload( $event, $id );
	if ( ! $p ) {
		return;
	}
	$p['tag'] = 'celb-' . $event . '-' . $id;
	celb_app_feed_add( $event, $id, $p );
	CELB_Push::queue( $event, $p + array( 'icon' => celb_app_icon() ) );
}

/* Add an entry to the managers' activity feed (newest first, last 80). */
function celb_app_feed_add( $event, $id, $p ) {
	$feed = celb_app_feed();
	array_unshift( $feed, array( 'id' => $p['tag'], 't' => time(), 'event' => $event, 'ref' => (int) $id ) + $p );
	update_option( 'celb_app_feed', array_slice( $feed, 0, 80 ), false );
}

add_action( 'wp_insert_post', function ( $post_id, $post, $update ) {
	if ( $update || 'publish' !== $post->post_status ) {
		return;
	}
	$map = array( 'celb_artreq' => 'artreq', 'celb_request' => 'booking', 'celb_pdata' => 'pdata', 'celb_rateonb' => 'rateonb' );
	if ( isset( $map[ $post->post_type ] ) ) {
		celb_app_event( $map[ $post->post_type ], $post_id );
	}
}, 10, 3 );
$celb_app_signed = function ( $meta_id, $object_id, $meta_key, $value ) {
	if ( '_contract_status' === $meta_key && 'signed' === $value ) {
		celb_app_event( 'contract', $object_id );
	}
};
add_action( 'added_post_meta', $celb_app_signed, 10, 4 );
add_action( 'updated_post_meta', $celb_app_signed, 10, 4 );
unset( $celb_app_signed );

/* =========================================================================
 * 3. DATA SHAPES
 * ====================================================================== */

function celb_app_celeb_brief( $id ) {
	$id = (int) $id;
	if ( ! $id || ! get_post( $id ) ) {
		return null;
	}
	return array( 'id' => $id, 'name' => get_the_title( $id ), 'photo' => celb_studio_photo_url( $id, 'thumbnail' ) );
}
function celb_app_admin_link( $id ) {
	return (string) get_edit_post_link( $id, 'raw' );
}

function celb_app_artreq( $id, $full = false ) {
	$ids = celb_ar_artist_ids( $id );
	$out = array(
		'kind'    => 'artreq',
		'id'      => (int) $id,
		'name'    => celb_ar_name( $id ),
		'company' => (string) get_post_meta( $id, '_ar_brand', true ),
		'email'   => (string) get_post_meta( $id, '_ar_email', true ),
		'phone'   => (string) get_post_meta( $id, '_ar_phone', true ),
		'wa'      => celb_ar_wa_number( get_post_meta( $id, '_ar_wa', true ) ),
		'excerpt' => wp_trim_words( (string) get_post_meta( $id, '_ar_details', true ), 18, '…' ),
		'status'  => celb_ar_status( $id ),
		'unread'  => ! get_post_meta( $id, '_ar_seen', true ),
		'time'    => (int) get_post_time( 'U', true, $id ),
		'artists' => array_values( array_filter( array_map( 'celb_app_celeb_brief', $ids ) ) ),
		'all'     => ! $ids,
	);
	if ( $full ) {
		$out['message'] = (string) get_post_meta( $id, '_ar_details', true );
		$out['admin']   = celb_app_admin_link( $id );
		$log            = get_post_meta( $id, '_ar_log', true );
		$out['log']     = array();
		foreach ( is_array( $log ) ? array_reverse( $log ) : array() as $e ) {
			$u            = ! empty( $e['u'] ) ? get_userdata( (int) $e['u'] ) : false;
			$out['log'][] = array( 'type' => $e['type'], 'text' => (string) $e['text'], 'who' => $u ? $u->display_name : '', 't' => (int) $e['t'] );
		}
	}
	return $out;
}
function celb_app_booking( $id, $full = false ) {
	$d   = celb_request_data( $id );
	$out = array(
		'kind'    => 'booking',
		'id'      => (int) $id,
		'name'    => $d['name'],
		'company' => $d['company'],
		'email'   => $d['email'],
		'phone'   => $d['phone'],
		'wa'      => preg_replace( '/[^0-9]/', '', $d['phone'] ),
		'excerpt' => wp_trim_words( $d['message'], 18, '…' ),
		'status'  => $d['status'],
		'unread'  => 'new' === $d['status'],
		'time'    => (int) get_post_time( 'U', true, $id ),
		'artists' => array_values( array_filter( array( celb_app_celeb_brief( $d['celeb'] ) ) ) ),
		'all'     => false,
		'type'    => $d['type'],
		'date'    => $d['date'],
	);
	if ( $full ) {
		$out['message'] = $d['message'];
		$out['admin']   = celb_app_admin_link( $id );
		$out['log']     = array();
	}
	return $out;
}

/* Agenda: schedule entries + project shooting days between two dates. */
function celb_app_agenda( $from, $to, $celeb = 0 ) {
	$items = array();
	$mq    = array( array( 'key' => '_sched_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) );
	if ( $celeb ) {
		$mq[] = array( 'key' => '_sched_celeb', 'value' => (int) $celeb );
	}
	foreach ( get_posts( array( 'post_type' => 'celb_sched', 'post_status' => 'publish', 'numberposts' => 400, 'meta_query' => $mq, 'suppress_filters' => true ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$loc     = get_post_meta( $p->ID, '_sched_location', true );
		$items[] = array(
			'kind'     => 'sched',
			'id'       => $p->ID,
			'title'    => get_the_title( $p ),
			'date'     => (string) get_post_meta( $p->ID, '_sched_date', true ),
			'time'     => (string) get_post_meta( $p->ID, '_sched_time', true ),
			'duration' => (int) get_post_meta( $p->ID, '_sched_duration', true ),
			'type'     => (string) get_post_meta( $p->ID, '_sched_type', true ),
			'status'   => (string) get_post_meta( $p->ID, '_sched_status', true ),
			'where'    => is_array( $loc ) ? ( ! empty( $loc['label'] ) ? $loc['label'] : ( isset( $loc['address'] ) ? $loc['address'] : '' ) ) : '',
			'celeb'    => celb_app_celeb_brief( get_post_meta( $p->ID, '_sched_celeb', true ) ),
		);
	}
	$pq = array( 'post_type' => 'celb_project', 'post_status' => 'publish', 'numberposts' => 300, 'suppress_filters' => true );
	if ( $celeb ) {
		$pq['meta_query'] = array( array( 'key' => '_proj_celeb', 'value' => (int) $celeb ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}
	foreach ( get_posts( $pq ) as $p ) {
		$days = get_post_meta( $p->ID, '_proj_days', true );
		foreach ( is_array( $days ) ? $days : array() as $d ) {
			$st   = isset( $d['status'] ) ? $d['status'] : 'scheduled';
			$date = ( 'postponed' === $st && ! empty( $d['new_date'] ) ) ? $d['new_date'] : ( isset( $d['date'] ) ? $d['date'] : '' );
			if ( ! $date || $date < $from || $date > $to ) {
				continue;
			}
			$items[] = array(
				'kind'     => 'day',
				'id'       => $p->ID,
				'title'    => get_the_title( $p ),
				'date'     => $date,
				'time'     => (string) ( 'postponed' === $st && ! empty( $d['new_time'] ) ? $d['new_time'] : ( isset( $d['time'] ) ? $d['time'] : '' ) ),
				'duration' => isset( $d['dur'] ) ? (int) $d['dur'] : 0,
				'type'     => __( 'Shooting day', 'celb-mgmt' ),
				'status'   => ucfirst( $st ),
				'where'    => isset( $d['location'] ) ? (string) $d['location'] : '',
				'celeb'    => celb_app_celeb_brief( get_post_meta( $p->ID, '_proj_celeb', true ) ),
			);
		}
	}
	usort( $items, function ( $a, $b ) {
		return strcmp( $a['date'] . ( $a['time'] ? $a['time'] : '99' ), $b['date'] . ( $b['time'] ? $b['time'] : '99' ) );
	} );
	return $items;
}

function celb_app_project( $id ) {
	$c     = celb_project_counts( get_post_meta( $id, '_proj_days', true ) );
	$today = current_time( 'Y-m-d' );
	$next  = '';
	foreach ( (array) get_post_meta( $id, '_proj_days', true ) as $d ) {
		$date = ( isset( $d['status'] ) && 'postponed' === $d['status'] && ! empty( $d['new_date'] ) ) ? $d['new_date'] : ( isset( $d['date'] ) ? $d['date'] : '' );
		if ( $date && $date >= $today && ( empty( $d['status'] ) || in_array( $d['status'], array( 'scheduled', 'postponed' ), true ) ) && ( '' === $next || $date < $next ) ) {
			$next = $date;
		}
	}
	return array(
		'id'      => (int) $id,
		'name'    => get_the_title( $id ),
		'type'    => (string) get_post_meta( $id, '_proj_type', true ),
		'company' => (string) get_post_meta( $id, '_proj_company', true ),
		'status'  => (string) get_post_meta( $id, '_proj_status', true ),
		'start'   => (string) get_post_meta( $id, '_proj_start', true ),
		'end'     => (string) get_post_meta( $id, '_proj_end', true ),
		'done'    => (int) $c['completed'],
		'total'   => (int) $c['total'],
		'next'    => $next,
		'celeb'   => celb_app_celeb_brief( get_post_meta( $id, '_proj_celeb', true ) ),
		'admin'   => celb_app_admin_link( $id ),
	);
}

function celb_app_talent( $id ) {
	$checks = celb_studio_checks( $id );
	return array(
		'id'     => (int) $id,
		'name'   => get_the_title( $id ),
		'role'   => (string) get_post_meta( $id, '_celb_role', true ),
		'nat'    => (string) get_post_meta( $id, '_celb_nationality', true ),
		'photo'  => celb_studio_photo_url( $id, 'medium' ),
		'status' => get_post_status( $id ),
		'lead'   => (bool) get_post_meta( $id, '_celb_lead', true ),
		'locked' => (bool) get_post_meta( $id, '_celb_locked', true ),
		'score'  => celb_studio_score( $checks ),
		'cats'   => array_map( 'celb_roster_cat_label', celb_roster_categories( $id ) ),
	);
}

function celb_app_rate( $id ) {
	$secs  = celb_rate_sections_get( $id );
	$items = 0;
	foreach ( is_array( $secs ) ? $secs : array() as $s ) {
		$items += ! empty( $s['items'] ) && is_array( $s['items'] ) ? count( $s['items'] ) : 0;
	}
	$tpl = celb_rate_is_template( $id );
	return array(
		'id'       => (int) $id,
		'title'    => get_the_title( $id ),
		'template' => $tpl,
		'enabled'  => get_post_meta( $id, '_rate_enabled', true ) === '1',
		'haspw'    => '' !== (string) get_post_meta( $id, '_rate_pw', true ),
		'live'     => ! $tpl && celb_ws_rate_live( $id ),
		'url'      => $tpl ? '' : celb_rate_card_url( $id ),
		'services' => $items,
		'celeb'    => celb_app_celeb_brief( get_post_meta( $id, '_rate_celeb', true ) ),
		'admin'    => celb_app_admin_link( $id ),
	);
}

/* =========================================================================
 * 4. REST API
 * ====================================================================== */

add_action( 'rest_api_init', function () {
	$ns    = 'celb-app/v1';
	$can   = array( 'permission_callback' => 'celb_app_can' );
	$admin = array( 'permission_callback' => 'celb_app_can_admin' );
	$r     = function ( $path, $methods, $cb, $perm ) use ( $ns ) {
		register_rest_route( $ns, $path, array( array( 'methods' => $methods, 'callback' => $cb ) + $perm ) );
	};
	$r( '/home', 'GET', 'celb_app_rest_home', $can );
	$r( '/inbox', 'GET', 'celb_app_rest_inbox', $can );
	$r( '/inbox/(?P<kind>artreq|booking)/(?P<id>\d+)', 'GET', 'celb_app_rest_inbox_get', $can );
	$r( '/inbox/(?P<kind>artreq|booking)/(?P<id>\d+)', 'POST', 'celb_app_rest_inbox_update', $can );
	$r( '/calendar', 'GET', 'celb_app_rest_calendar', $can );
	$r( '/sched/(?P<id>\d+)', 'GET', 'celb_app_rest_sched_get', $can );
	$r( '/sched/(?P<id>\d+)', 'POST', 'celb_app_rest_sched_update', $can );
	$r( '/projects', 'GET', 'celb_app_rest_projects', $can );
	$r( '/projects/(?P<id>\d+)', 'GET', 'celb_app_rest_project_get', $can );
	$r( '/roster', 'GET', 'celb_app_rest_roster', $can );
	$r( '/roster/(?P<id>\d+)', 'GET', 'celb_app_rest_talent_get', $can );
	$r( '/roster/(?P<id>\d+)', 'POST', 'celb_app_rest_talent_update', $can );
	$r( '/news', 'GET', 'celb_app_rest_news', $can );
	$r( '/news/(?P<id>\d+)', 'POST', 'celb_app_rest_news_update', $can );
	$r( '/contracts', 'GET', 'celb_app_rest_contracts', $can );
	$r( '/rates', 'GET', 'celb_app_rest_rates', $can );
	$r( '/rates/(?P<id>\d+)', 'POST', 'celb_app_rest_rate_update', $can );
	$r( '/onboarding', 'GET', 'celb_app_rest_onb', $admin );
	$r( '/onboarding/link', 'POST', 'celb_app_rest_onb_link', $admin );
	$r( '/onboarding/revoke', 'POST', 'celb_app_rest_onb_revoke', $admin );
	$r( '/onboarding/(?P<id>\d+)/import', 'POST', 'celb_app_rest_onb_import', $admin );
	$r( '/pdata', 'GET', 'celb_app_rest_pdata', $admin );
	$r( '/feed', 'GET', 'celb_app_rest_feed', $can );
	$r( '/feed/read', 'POST', 'celb_app_rest_feed_read', $can );
	$r( '/prefs', 'GET', 'celb_app_rest_prefs', $can );
	$r( '/prefs', 'POST', 'celb_app_rest_prefs_save', $can );
	$r( '/push/(?P<op>key|status|subscribe|unsubscribe|test)', 'POST', array( 'CELB_Push', 'rest' ), $can );
} );

function celb_app_feed_unread() {
	$read = (int) get_user_meta( get_current_user_id(), '_celb_app_feed_read', true );
	$n    = 0;
	foreach ( celb_app_feed() as $e ) {
		if ( (int) $e['t'] > $read ) {
			$n++;
		}
	}
	return $n;
}

function celb_app_rest_home() {
	$today = current_time( 'Y-m-d' );
	$week  = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
	$ar    = celb_ar_counts();
	$book  = 0;
	foreach ( celb_ws_ids( 'celb_request' ) as $id ) {
		if ( 'new' === celb_request_data( $id )['status'] ) {
			$book++;
		}
	}
	$pending = 0;
	foreach ( celb_ws_ids( 'celb_contract' ) as $id ) {
		if ( 'signed' !== celb_ws_contract_status( $id ) ) {
			$pending++;
		}
	}
	$ongoing = 0;
	foreach ( celb_ws_ids( 'celb_project' ) as $id ) {
		if ( in_array( get_post_meta( $id, '_proj_status', true ), array( 'Ongoing', 'Upcoming' ), true ) ) {
			$ongoing++;
		}
	}
	$onb = 0;
	foreach ( get_posts( array( 'post_type' => 'celb_rateonb', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $sid ) {
		if ( ! get_post_meta( $sid, '_onb_imported', true ) ) {
			$onb++;
		}
	}
	$live = 0;
	foreach ( celb_ws_ids( CELB_RATE_CPT ) as $id ) {
		if ( ! celb_rate_is_template( $id ) && celb_ws_rate_live( $id ) ) {
			$live++;
		}
	}
	$news   = wp_count_posts( 'celeb_news' );
	$agenda = celb_app_agenda( $today, $week );
	return array(
		'today'    => $today,
		'counts'   => array(
			'inbox'     => (int) $ar['unread'] + $book,
			'artreq'    => (int) $ar['unread'],
			'booking'   => $book,
			'today'     => count( array_filter( $agenda, function ( $i ) use ( $today ) { return $i['date'] === $today; } ) ),
			'week'      => count( $agenda ),
			'projects'  => $ongoing,
			'contracts' => $pending,
			'onboard'   => $onb,
			'rates'     => $live,
			'roster'    => count( celb_ws_ids( CELB_CPT ) ),
			'news'      => (int) $news->publish,
			'drafts'    => (int) $news->draft,
			'pdata'     => celb_app_can_admin() ? count( get_posts( array( 'post_type' => 'celb_pdata', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) ) : 0,
		),
		'agenda'   => array_slice( $agenda, 0, 8 ),
		'feed'     => array_slice( celb_app_feed(), 0, 5 ),
		'unread'   => celb_app_feed_unread(),
	);
}

function celb_app_rest_inbox( $req ) {
	$kind  = (string) $req->get_param( 'kind' );
	$items = array();
	if ( 'booking' !== $kind ) {
		foreach ( get_posts( array( 'post_type' => CELB_AR_CPT, 'post_status' => 'publish', 'numberposts' => 150, 'fields' => 'ids' ) ) as $id ) {
			$items[] = celb_app_artreq( $id );
		}
	}
	if ( 'artreq' !== $kind ) {
		foreach ( get_posts( array( 'post_type' => 'celb_request', 'post_status' => 'publish', 'numberposts' => 150, 'fields' => 'ids' ) ) as $id ) {
			$items[] = celb_app_booking( $id );
		}
	}
	usort( $items, function ( $a, $b ) {
		return $b['time'] - $a['time'];
	} );
	return $items;
}
function celb_app_inbox_check( $kind, $id ) {
	$type = 'artreq' === $kind ? CELB_AR_CPT : 'celb_request';
	return get_post_type( $id ) === $type;
}
function celb_app_rest_inbox_get( $req ) {
	$id = (int) $req['id'];
	if ( ! celb_app_inbox_check( $req['kind'], $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	if ( 'artreq' === $req['kind'] ) {
		if ( ! get_post_meta( $id, '_ar_seen', true ) ) {
			update_post_meta( $id, '_ar_seen', time() );
			celb_ar_log( $id, 'seen' );
		}
		$out             = celb_app_artreq( $id, true );
		$out['statuses'] = celb_ar_statuses();
		return $out;
	}
	$out             = celb_app_booking( $id, true );
	$out['statuses'] = array();
	foreach ( celb_request_statuses() as $s ) {
		$out['statuses'][ $s ] = celb_request_status_label( $s );
	}
	return $out;
}
function celb_app_rest_inbox_update( $req ) {
	$id = (int) $req['id'];
	if ( ! celb_app_inbox_check( $req['kind'], $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	$status = sanitize_key( (string) $req->get_param( 'status' ) );
	$note   = trim( sanitize_textarea_field( (string) $req->get_param( 'note' ) ) );
	if ( 'artreq' === $req['kind'] ) {
		if ( $status ) {
			celb_ar_set_status( $id, $status );
		}
		if ( '' !== $note ) {
			celb_ar_log( $id, 'note', $note );
		}
		if ( null !== $req->get_param( 'unread' ) ) {
			if ( rest_sanitize_boolean( $req->get_param( 'unread' ) ) ) {
				delete_post_meta( $id, '_ar_seen' );
			} else {
				update_post_meta( $id, '_ar_seen', time() );
			}
		}
	} elseif ( $status && in_array( $status, celb_request_statuses(), true ) ) {
		update_post_meta( $id, '_req_status', $status );
	}
	return celb_app_rest_inbox_get( $req );
}

function celb_app_rest_calendar( $req ) {
	$from = (string) $req->get_param( 'from' );
	$to   = (string) $req->get_param( 'to' );
	$ok   = function ( $d ) { return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ); };
	$today = current_time( 'Y-m-d' );
	$from = $ok( $from ) ? $from : gmdate( 'Y-m-d', strtotime( $today . ' -7 days' ) );
	$to   = $ok( $to ) ? $to : gmdate( 'Y-m-d', strtotime( $today . ' +60 days' ) );
	return array( 'from' => $from, 'to' => $to, 'today' => $today, 'items' => celb_app_agenda( $from, $to, (int) $req->get_param( 'celeb' ) ) );
}
function celb_app_rest_sched_get( $req ) {
	$id = (int) $req['id'];
	if ( 'celb_sched' !== get_post_type( $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	$d             = celb_sched_data( $id );
	$d['status']   = (string) get_post_meta( $id, '_sched_status', true );
	$d['new_date'] = (string) get_post_meta( $id, '_sched_new_date', true );
	$d['new_time'] = (string) get_post_meta( $id, '_sched_new_time', true );
	$d['celeb']    = celb_app_celeb_brief( $d['celeb'] );
	$d['statuses'] = celb_sched_statuses();
	$d['admin']    = celb_app_admin_link( $id );
	return $d;
}
function celb_app_rest_sched_update( $req ) {
	$id = (int) $req['id'];
	if ( 'celb_sched' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	$status = sanitize_text_field( (string) $req->get_param( 'status' ) );
	if ( $status && in_array( $status, celb_sched_statuses(), true ) ) {
		$prev = (string) get_post_meta( $id, '_sched_status', true );
		foreach ( array( 'new_date' => '/^\d{4}-\d{2}-\d{2}$/', 'new_time' => '/^\d{2}:\d{2}$/' ) as $k => $rx ) {
			$v = (string) $req->get_param( $k );
			if ( null !== $req->get_param( $k ) ) {
				update_post_meta( $id, '_sched_' . $k, preg_match( $rx, $v ) ? $v : '' );
			}
		}
		update_post_meta( $id, '_sched_status', $status );
		if ( $prev !== $status ) {
			celb_notify_status_change( 'schedule', $id, $prev, $status );
		}
	}
	return celb_app_rest_sched_get( $req );
}

function celb_app_rest_projects() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'celb_project', 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids' ) ) as $id ) {
		$out[] = celb_app_project( $id );
	}
	return $out;
}
function celb_app_rest_project_get( $req ) {
	$id = (int) $req['id'];
	if ( 'celb_project' !== get_post_type( $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	$d          = celb_project_data( $id );
	$d['brief'] = celb_app_project( $id );
	return $d;
}

function celb_app_rest_roster() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) ) as $id ) {
		$out[] = celb_app_talent( $id );
	}
	return $out;
}
function celb_app_rest_talent_get( $req ) {
	$id = (int) $req['id'];
	if ( CELB_CPT !== get_post_type( $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	$t            = celb_app_talent( $id );
	$t['photo']   = celb_studio_photo_url( $id, 'large' );
	$t['missing'] = array();
	foreach ( celb_studio_checks( $id ) as $c ) {
		if ( ! $c[1] ) {
			$t['missing'][] = $c[0];
		}
	}
	$pub            = 'publish' === $t['status'];
	$t['smartlink'] = $pub ? celb_smartlink_url( $id ) : '';
	$t['profile']   = $pub ? get_permalink( $id ) : '';
	$t['calendar']  = $pub ? celb_cal_feed_url( $id, 'webcal' ) : '';
	$card           = celb_rate_card_for_celeb( $id );
	$t['rate']      = $card ? celb_app_rate( $card ) : null;
	$t['admin']     = celb_app_admin_link( $id );
	$today          = current_time( 'Y-m-d' );
	$t['agenda']    = array_slice( celb_app_agenda( $today, gmdate( 'Y-m-d', strtotime( $today . ' +90 days' ) ), $id ), 0, 10 );
	$t['contracts'] = array();
	foreach ( get_posts( array( 'post_type' => 'celb_contract', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_contract_celeb', 'value' => $id ) ) ) ) as $cid ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$t['contracts'][] = celb_contract_app_data( $cid, true );
	}
	return $t;
}
function celb_app_rest_talent_update( $req ) {
	$id = (int) $req['id'];
	if ( CELB_CPT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	foreach ( array( 'lead' => '_celb_lead', 'locked' => '_celb_locked' ) as $k => $meta ) {
		if ( null !== $req->get_param( $k ) ) {
			update_post_meta( $id, $meta, rest_sanitize_boolean( $req->get_param( $k ) ) ? '1' : '' );
		}
	}
	return celb_app_rest_talent_get( $req );
}

function celb_app_rest_news() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'celeb_news', 'post_status' => array( 'publish', 'draft', 'future', 'pending' ), 'numberposts' => 100, 'fields' => 'ids' ) ) as $id ) {
		$out[] = array(
			'id'     => $id,
			'title'  => get_the_title( $id ),
			'status' => get_post_status( $id ),
			'date'   => get_the_date( 'Y-m-d', $id ),
			'thumb'  => celb_news_thumb_img( $id, 'medium' ),
			'ar'     => '' !== (string) get_post_meta( $id, '_news_title_ar', true ),
			'loc'    => (string) get_post_meta( $id, '_news_location', true ),
			'celeb'  => celb_app_celeb_brief( get_post_meta( $id, '_news_celebrity', true ) ),
			'url'    => get_permalink( $id ),
			'admin'  => celb_app_admin_link( $id ),
		);
	}
	return $out;
}
function celb_app_rest_news_update( $req ) {
	$id = (int) $req['id'];
	$st = (string) $req->get_param( 'status' );
	if ( 'celeb_news' !== get_post_type( $id ) || ! current_user_can( 'publish_post', $id ) || ! in_array( $st, array( 'publish', 'draft' ), true ) ) {
		return new WP_Error( 'celb_bad', __( 'Cannot change this article.', 'celb-mgmt' ), array( 'status' => 400 ) );
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => $st ) );
	return celb_app_rest_news();
}

function celb_app_rest_contracts() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'celb_contract', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 200, 'fields' => 'ids' ) ) as $id ) {
		$c          = celb_contract_app_data( $id, true );
		$c['photo'] = $c['celeb'] ? celb_studio_photo_url( $c['celeb'], 'thumbnail' ) : '';
		$fee        = celb_contract_fee_amount_str( $id );
		$c['fee']   = '' !== $fee ? $fee . ' ' . celb_contract_monthly( $id )['currency'] : '';
		$c['admin'] = celb_app_admin_link( $id );
		$out[]      = $c;
	}
	return $out;
}

function celb_app_rest_rates() {
	$out = array();
	foreach ( celb_ws_ids( CELB_RATE_CPT ) as $id ) {
		$out[] = celb_app_rate( $id );
	}
	return $out;
}
function celb_app_rest_rate_update( $req ) {
	$id = (int) $req['id'];
	if ( CELB_RATE_CPT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	if ( null !== $req->get_param( 'enabled' ) ) {
		update_post_meta( $id, '_rate_enabled', rest_sanitize_boolean( $req->get_param( 'enabled' ) ) ? '1' : '' );
	}
	return celb_app_rate( $id );
}

function celb_app_rest_onb() {
	$links = array();
	foreach ( array_reverse( celb_rate_onb_links(), true ) as $token => $m ) {
		$links[] = array( 'token' => $token, 'url' => celb_rate_onb_url( $token ), 'src' => get_the_title( (int) $m['src'] ), 'celeb' => ! empty( $m['celeb'] ) ? get_the_title( (int) $m['celeb'] ) : '', 'created' => ! empty( $m['created'] ) ? (int) $m['created'] : 0 );
	}
	$subs = array();
	foreach ( get_posts( array( 'post_type' => 'celb_rateonb', 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids' ) ) as $sid ) {
		$secs = get_post_meta( $sid, '_onb_sections', true );
		$rows = array();
		foreach ( is_array( $secs ) ? $secs : array() as $s ) {
			foreach ( ! empty( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : array() as $it ) {
				$rows[] = array( 'section' => isset( $s['title'] ) ? $s['title'] : '', 'name' => isset( $it['name'] ) ? $it['name'] : '', 'base' => (float) ( isset( $it['base'] ) ? $it['base'] : 0 ), 'addl' => (float) ( isset( $it['addl'] ) ? $it['addl'] : 0 ) );
			}
		}
		$imp    = (int) get_post_meta( $sid, '_onb_imported', true );
		$cur    = (string) get_post_meta( $sid, '_onb_currency', true );
		$subs[] = array( 'id' => $sid, 'name' => get_the_title( $sid ), 'src' => get_the_title( (int) get_post_meta( $sid, '_onb_src', true ) ), 'date' => get_the_date( 'Y-m-d', $sid ), 'currency' => $cur ? $cur : 'EGP', 'rows' => $rows, 'imported' => $imp, 'imported_admin' => $imp ? celb_app_admin_link( $imp ) : '' );
	}
	$all     = celb_rate_all_cards();
	$sources = array();
	foreach ( array( 'templates', 'cards' ) as $g ) {
		foreach ( isset( $all[ $g ] ) ? $all[ $g ] : array() as $cid => $label ) {
			$sources[] = array( 'id' => $cid, 'label' => $label, 'template' => 'templates' === $g );
		}
	}
	$talent = array();
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) ) as $cid ) {
		$talent[] = array( 'id' => $cid, 'name' => get_the_title( $cid ) );
	}
	return array( 'links' => $links, 'subs' => $subs, 'sources' => $sources, 'talent' => $talent );
}
function celb_app_rest_onb_link( $req ) {
	$src = (int) $req->get_param( 'src' );
	if ( ! $src || get_post_type( $src ) !== CELB_RATE_CPT ) {
		return new WP_Error( 'celb_bad', __( 'Pick a rate card or template.', 'celb-mgmt' ), array( 'status' => 400 ) );
	}
	$links = celb_rate_onb_links();
	$want  = sanitize_title( (string) $req->get_param( 'slug' ) );
	$token = '' !== $want ? $want : strtolower( wp_generate_password( 16, false ) );
	for ( $n = 2; isset( $links[ $token ] ); $n++ ) {
		$token = $want . '-' . $n;
	}
	$links[ $token ] = array( 'src' => $src, 'celeb' => (int) $req->get_param( 'celeb' ), 'created' => time() );
	update_option( 'celb_rate_onb_links', $links, false );
	return celb_app_rest_onb();
}
function celb_app_rest_onb_revoke( $req ) {
	$links = celb_rate_onb_links();
	unset( $links[ (string) $req->get_param( 'token' ) ] );
	update_option( 'celb_rate_onb_links', $links, false );
	return celb_app_rest_onb();
}
function celb_app_rest_onb_import( $req ) {
	$sub = (int) $req['id'];
	if ( 'celb_rateonb' !== get_post_type( $sub ) ) {
		return new WP_Error( 'celb_404', __( 'Not found.', 'celb-mgmt' ), array( 'status' => 404 ) );
	}
	$sections = get_post_meta( $sub, '_onb_sections', true );
	$sections = is_array( $sections ) ? $sections : array();
	$target   = (string) $req->get_param( 'target' );
	$dest     = 0;
	if ( '' === $target || 'new' === $target ) {
		$dest = celb_rate_clone( (int) get_post_meta( $sub, '_onb_src', true ), array( 'title' => get_the_title( $sub ) . ' — Rate Card', 'celeb' => (int) get_post_meta( $sub, '_onb_celeb', true ), 'enabled' => false ) );
		if ( $dest ) {
			update_post_meta( $dest, '_rate_sections', $sections );
		}
	} elseif ( (int) $target && get_post_type( (int) $target ) === CELB_RATE_CPT ) {
		$dest = (int) $target;
		update_post_meta( $dest, '_rate_sections', celb_rate_merge_prices( celb_rate_sections_get( $dest ), $sections ) );
	}
	if ( ! $dest ) {
		return new WP_Error( 'celb_bad', __( 'Import failed.', 'celb-mgmt' ), array( 'status' => 400 ) );
	}
	update_post_meta( $sub, '_onb_imported', $dest );
	return celb_app_rest_onb();
}

function celb_app_rest_pdata() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'celb_pdata', 'post_status' => 'publish', 'numberposts' => 200 ) ) as $p ) {
		$answers = (array) get_post_meta( $p->ID, '_pd_answers', true );
		$snap    = get_post_meta( $p->ID, '_pd_schema', true );
		$snap    = is_array( $snap ) && $snap ? $snap : celb_pdata_schema();
		$secs    = array();
		foreach ( $snap as $sec ) {
			$rows = array();
			foreach ( (array) $sec['questions'] as $q ) {
				$v = isset( $answers[ $q['id'] ] ) ? trim( (string) $answers[ $q['id'] ] ) : '';
				if ( '' !== $v ) {
					$rows[] = array( 'label' => $q['label'], 'type' => isset( $q['type'] ) ? $q['type'] : 'text', 'value' => $v );
				}
			}
			if ( $rows ) {
				$secs[] = array( 'title' => $sec['title'], 'rows' => $rows, 'emergency' => false !== stripos( (string) $sec['title'], 'emergenc' ) );
			}
		}
		$nm    = (string) get_post_meta( $p->ID, '_pd_name', true );
		$out[] = array( 'id' => $p->ID, 'name' => '' !== $nm ? $nm : __( 'Submission', 'celb-mgmt' ), 'date' => get_the_date( 'Y-m-d H:i', $p ), 'sections' => $secs );
	}
	return $out;
}

function celb_app_rest_feed() {
	$read = (int) get_user_meta( get_current_user_id(), '_celb_app_feed_read', true );
	$out  = array();
	foreach ( celb_app_feed() as $e ) {
		$e['unread'] = (int) $e['t'] > $read;
		$out[]       = $e;
	}
	return $out;
}
function celb_app_rest_feed_read() {
	update_user_meta( get_current_user_id(), '_celb_app_feed_read', time() );
	return array( 'unread' => 0 );
}
function celb_app_rest_which( $req ) {
	return 0 === strpos( (string) $req->get_route(), '/celb-talent/' ) ? 'talent' : 'studio';
}
function celb_app_rest_prefs( $req ) {
	$which = celb_app_rest_which( $req );
	return array( 'prefs' => celb_app_prefs( 0, $which ), 'events' => celb_app_events( $which ), 'devices' => count( CELB_Push::subs( get_current_user_id(), $which ) ) );
}
function celb_app_rest_prefs_save( $req ) {
	$which = celb_app_rest_which( $req );
	$in    = (array) $req->get_param( 'prefs' );
	$out   = array();
	foreach ( array_keys( celb_app_events( $which ) ) as $k ) {
		$out[ $k ] = ! empty( $in[ $k ] );
	}
	update_user_meta( get_current_user_id(), 'talent' === $which ? '_celb_talent_prefs' : '_celb_app_prefs', $out );
	return celb_app_rest_prefs( $req );
}

/* =========================================================================
 * 5. ADMIN — link to the app
 * ====================================================================== */

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( celb_app_can() ) {
		$bar->add_node( array( 'id' => 'celb-app', 'title' => '<span class="ab-icon dashicons dashicons-smartphone" style="top:2px"></span><span class="ab-label">' . esc_html__( 'Studio app', 'celb-mgmt' ) . '</span>', 'href' => celb_app_url(), 'meta' => array( 'target' => '_blank' ) ) );
	}
}, 80 );
