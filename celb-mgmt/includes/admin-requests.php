<?php
/**
 * CELB MGMT — Artist Requests inbox (Studio UI).
 *
 * Requests arrive from [artist_contact_form] as `celb_artreq` posts with the
 * submitted fields in `_ar_*` meta (see celb_artreq_handle() in the main
 * file). This module adds the admin side: an inbox list with statuses,
 * filters and bulk actions, and a request view with contact actions, the
 * requested artists and an activity log with internal notes.
 *
 * New meta (admin only):
 *   _ar_status  new | progress | replied | booked | closed   (missing = new)
 *   _ar_seen    unix time the request was first opened         (missing = unread)
 *   _ar_log     array of { t, u, type: note|status|seen, text }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELB_AR_CPT', 'celb_artreq' );

function celb_ar_statuses() {
	return array(
		'new'      => __( 'New', 'celb-mgmt' ),
		'progress' => __( 'In progress', 'celb-mgmt' ),
		'replied'  => __( 'Replied', 'celb-mgmt' ),
		'booked'   => __( 'Booked', 'celb-mgmt' ),
		'closed'   => __( 'Closed', 'celb-mgmt' ),
	);
}
function celb_ar_status( $id ) {
	$s = (string) get_post_meta( $id, '_ar_status', true );
	return array_key_exists( $s, celb_ar_statuses() ) ? $s : 'new';
}
function celb_ar_status_badge( $status ) {
	$all = celb_ar_statuses();
	return '<span class="cs-badge cs-ar-st cs-ar-st--' . esc_attr( $status ) . '">' . esc_html( isset( $all[ $status ] ) ? $all[ $status ] : $status ) . '</span>';
}
function celb_ar_name( $id ) {
	$n = (string) get_post_meta( $id, '_ar_name', true );
	if ( '' === $n ) {
		$n = preg_replace( '/\s+—\s+\d{4}-\d{2}-\d{2}.*$/u', '', get_post_field( 'post_title', $id ) );
	}
	return $n;
}
function celb_ar_log( $id, $type, $text = '' ) {
	$log   = get_post_meta( $id, '_ar_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 't' => time(), 'u' => get_current_user_id(), 'type' => $type, 'text' => $text );
	update_post_meta( $id, '_ar_log', $log );
}
function celb_ar_set_status( $id, $status ) {
	if ( ! array_key_exists( $status, celb_ar_statuses() ) || celb_ar_status( $id ) === $status ) {
		return false;
	}
	update_post_meta( $id, '_ar_status', $status );
	celb_ar_log( $id, 'status', $status );
	return true;
}
function celb_ar_wa_number( $raw ) {
	return preg_replace( '/[^0-9]/', '', (string) $raw );
}

/* Counts per status + unread, for the header, views and the menu bubble. */
function celb_ar_counts() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	$ids = get_posts( array( 'post_type' => CELB_AR_CPT, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
	update_meta_cache( 'post', $ids );
	$c = array_fill_keys( array_keys( celb_ar_statuses() ), 0 );
	$c['all']    = count( $ids );
	$c['unread'] = 0;
	foreach ( $ids as $id ) {
		$c[ celb_ar_status( $id ) ]++;
		if ( ! get_post_meta( $id, '_ar_seen', true ) ) {
			$c['unread']++;
		}
	}
	return $c;
}

/* Screen routing: plug the two request screens into the Studio shell. */
add_filter( 'celb_studio_screen', function ( $which, $screen ) {
	if ( 'edit-' . CELB_AR_CPT === $screen->id ) {
		return 'requests';
	}
	if ( 'post' === $screen->base && CELB_AR_CPT === $screen->post_type ) {
		return 'request';
	}
	return $which;
}, 10, 2 );

/* Unread bubble on the menu item. */
add_action( 'admin_menu', function () {
	global $submenu;
	$parent = 'edit.php?post_type=' . CELB_CPT;
	if ( empty( $submenu[ $parent ] ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$n = celb_ar_counts()['unread'];
	if ( ! $n ) {
		return;
	}
	foreach ( $submenu[ $parent ] as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . CELB_AR_CPT === $item[2] ) {
			$submenu[ $parent ][ $i ][0] .= ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>';
		}
	}
}, 1000 );

/* =========================================================================
 * LIST (inbox)
 * ====================================================================== */

add_action( 'all_admin_notices', function () {
	if ( 'requests' !== celb_studio_screen() ) {
		return;
	}
	$c    = celb_ar_counts();
	$base = admin_url( 'edit.php?post_type=' . CELB_AR_CPT );
	$cur  = isset( $_GET['ar_status'] ) ? sanitize_key( wp_unslash( $_GET['ar_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$unread_only = isset( $_GET['ar_unread'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$icons = array( 'new' => 'sparkle', 'progress' => 'refresh', 'replied' => 'mail', 'booked' => 'check', 'closed' => 'lock' );
	?>
	<div class="cs-hero">
		<div class="cs-hero-top">
			<div>
				<p class="cs-eyebrow"><?php esc_html_e( 'CELB MGMT', 'celb-mgmt' ); ?></p>
				<h1 class="cs-hero-title"><?php esc_html_e( 'Artist Requests', 'celb-mgmt' ); ?></h1>
				<p class="cs-hero-sub">
					<?php
					echo $c['unread']
						? esc_html( sprintf( _n( '%d unread request waiting for you.', '%d unread requests waiting for you.', $c['unread'], 'celb-mgmt' ), $c['unread'] ) )
						: esc_html__( 'You’re all caught up. New requests from the artist contact form land here.', 'celb-mgmt' );
					?>
				</p>
			</div>
			<div class="cs-hero-actions">
				<?php if ( $c['unread'] ) : ?>
					<a class="cs-btn<?php echo $unread_only ? ' cs-btn--primary' : ''; ?>" href="<?php echo esc_url( $unread_only ? $base : add_query_arg( 'ar_unread', 1, $base ) ); ?>"><?php echo celb_studio_icon( 'mail', 16 ); // phpcs:ignore ?><span><?php echo esc_html( sprintf( __( 'Unread (%d)', 'celb-mgmt' ), $c['unread'] ) ); ?></span></a>
				<?php endif; ?>
				<a class="cs-btn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-settings#contact' ) ); ?>"><?php echo celb_studio_icon( 'chat', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'Form settings', 'celb-mgmt' ); ?></span></a>
			</div>
		</div>
		<div class="cs-tiles">
			<a class="cs-tile<?php echo '' === $cur && ! $unread_only ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base ); ?>">
				<span class="cs-tile-ic"><?php echo celb_studio_icon( 'chat', 17 ); // phpcs:ignore ?></span>
				<span class="cs-tile-num"><?php echo (int) $c['all']; ?></span>
				<span class="cs-tile-label"><?php esc_html_e( 'All requests', 'celb-mgmt' ); ?></span>
			</a>
			<?php foreach ( celb_ar_statuses() as $k => $lbl ) : ?>
				<a class="cs-tile cs-tile--<?php echo esc_attr( $k ); ?><?php echo $cur === $k ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ar_status', $k, $base ) ); ?>">
					<span class="cs-tile-ic"><?php echo celb_studio_icon( $icons[ $k ], 17 ); // phpcs:ignore ?></span>
					<span class="cs-tile-num"><?php echo (int) $c[ $k ]; ?></span>
					<span class="cs-tile-label"><?php echo esc_html( $lbl ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}, 1 );

/* The stats tiles already filter by status; keep only All + Trash in views. */
add_filter( 'views_edit-' . CELB_AR_CPT, function ( $views ) {
	unset( $views['publish'], $views['mine'] );
	return $views;
} );

add_filter( 'manage_' . CELB_AR_CPT . '_posts_columns', function ( $c ) {
	return array(
		'cb'         => isset( $c['cb'] ) ? $c['cb'] : '<input type="checkbox" />',
		'ar_avatar'  => '<span class="screen-reader-text">' . esc_html__( 'Sender', 'celb-mgmt' ) . '</span>',
		'title'      => __( 'From', 'celb-mgmt' ),
		'ar_msg'     => __( 'Request', 'celb-mgmt' ),
		'ar_artists' => __( 'Artists', 'celb-mgmt' ),
		'ar_status'  => __( 'Status', 'celb-mgmt' ),
		'ar_contact' => __( 'Reach', 'celb-mgmt' ),
		'ar_date'    => __( 'Received', 'celb-mgmt' ),
	);
}, 100 );
add_filter( 'manage_edit-' . CELB_AR_CPT . '_sortable_columns', function ( $c ) {
	$c['ar_date'] = 'date';
	return $c;
} );

/* Show the sender's name (not "Name — date") as the row title. */
add_filter( 'the_title', function ( $title, $id = 0 ) {
	if ( $id && is_admin() && CELB_AR_CPT === get_post_type( $id ) && in_array( celb_studio_screen(), array( 'requests', 'request' ), true ) ) {
		return celb_ar_name( $id );
	}
	return $title;
}, 10, 2 );

/* Unread rows are highlighted. */
add_filter( 'post_class', function ( $classes, $class, $id ) {
	if ( is_admin() && CELB_AR_CPT === get_post_type( $id ) ) {
		$classes[] = get_post_meta( $id, '_ar_seen', true ) ? 'cs-ar-read' : 'cs-ar-unread';
	}
	return $classes;
}, 10, 3 );

function celb_ar_artist_ids( $id ) {
	$ids = get_post_meta( $id, '_ar_artists', true );
	return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
}

add_action( 'manage_' . CELB_AR_CPT . '_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'ar_avatar':
			echo '<span class="cs-ar-avatar" aria-hidden="true">' . esc_html( celb_monogram( celb_ar_name( $id ) ) ) . '</span>';
			$brand = (string) get_post_meta( $id, '_ar_brand', true );
			$email = (string) get_post_meta( $id, '_ar_email', true );
			echo '<span class="cs-row-sub" data-cs-sub>' . esc_html( implode( ' · ', array_filter( array( $brand, $email ) ) ) ) . '</span>';
			break;
		case 'ar_msg':
			echo '<span class="cs-ar-excerpt">' . esc_html( wp_trim_words( (string) get_post_meta( $id, '_ar_details', true ), 20, '…' ) ) . '</span>';
			break;
		case 'ar_artists':
			$ids = celb_ar_artist_ids( $id );
			if ( ! $ids ) {
				echo '<span class="cs-cat">' . esc_html__( 'All artists', 'celb-mgmt' ) . '</span>';
				break;
			}
			echo '<span class="cs-ar-stack">';
			foreach ( array_slice( $ids, 0, 4 ) as $aid ) {
				$photo = celb_studio_photo_url( $aid, 'thumbnail' );
				echo '<span class="cs-ar-face" title="' . esc_attr( get_the_title( $aid ) ) . '"' . ( $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '>' . ( $photo ? '' : esc_html( celb_monogram( get_the_title( $aid ) ) ) ) . '</span>';
			}
			if ( count( $ids ) > 4 ) {
				echo '<span class="cs-ar-face cs-ar-face--more">+' . ( count( $ids ) - 4 ) . '</span>';
			}
			echo '</span><span class="cs-ar-names">' . esc_html( implode( ', ', array_map( 'get_the_title', array_slice( $ids, 0, 2 ) ) ) . ( count( $ids ) > 2 ? ' +' . ( count( $ids ) - 2 ) : '' ) ) . '</span>';
			break;
		case 'ar_status':
			echo celb_ar_status_badge( celb_ar_status( $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'ar_contact':
			$email = (string) get_post_meta( $id, '_ar_email', true );
			$phone = (string) get_post_meta( $id, '_ar_phone', true );
			$wa    = celb_ar_wa_number( get_post_meta( $id, '_ar_wa', true ) );
			echo '<span class="cs-ar-reach">';
			if ( $email ) {
				echo '<a href="mailto:' . esc_attr( $email ) . '" title="' . esc_attr( $email ) . '">' . celb_studio_icon( 'mail', 16 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( $wa ) {
				echo '<a href="https://wa.me/' . esc_attr( $wa ) . '" target="_blank" rel="noopener" title="WhatsApp ' . esc_attr( get_post_meta( $id, '_ar_wa', true ) ) . '">' . celb_studio_icon( 'chat', 16 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( $phone ) {
				echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '" title="' . esc_attr( $phone ) . '">' . celb_studio_icon( 'phone', 16 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</span>';
			break;
		case 'ar_date':
			$t = get_post_time( 'U', true, $id );
			echo '<span class="cs-ar-when" title="' . esc_attr( get_the_date( 'j M Y, H:i', $id ) ) . '">' . esc_html( celb_ar_ago( $t ) ) . '</span>';
			break;
	}
}, 10, 2 );

function celb_ar_ago( $ts ) {
	$diff = time() - (int) $ts;
	if ( $diff < 60 ) {
		return __( 'Just now', 'celb-mgmt' );
	}
	if ( $diff < 7 * DAY_IN_SECONDS ) {
		/* translators: %s: human time difference, e.g. "3 hours" */
		return sprintf( __( '%s ago', 'celb-mgmt' ), human_time_diff( $ts ) );
	}
	return date_i18n( 'j M Y', $ts + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
}

/* Row actions: Open + quick status + WhatsApp/Email. Drop Quick Edit. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( CELB_AR_CPT !== $post->post_type ) {
		return $actions;
	}
	$out = array( 'open' => '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open', 'celb-mgmt' ) . '</a>' );
	$email = (string) get_post_meta( $post->ID, '_ar_email', true );
	if ( $email ) {
		$out['reply'] = '<a href="' . esc_url( celb_ar_mailto( $post->ID ) ) . '">' . esc_html__( 'Reply', 'celb-mgmt' ) . '</a>';
	}
	if ( isset( $actions['trash'] ) ) {
		$out['trash'] = $actions['trash'];
	}
	foreach ( array( 'untrash', 'delete' ) as $k ) {
		if ( isset( $actions[ $k ] ) ) {
			$out[ $k ] = $actions[ $k ];
		}
	}
	return $out;
}, 20, 2 );

/* Filters: status (tiles), unread, artist, and search across the submitted fields. */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( CELB_AR_CPT !== $post_type ) {
		return;
	}
	$cur = isset( $_GET['ar_artist'] ) ? absint( $_GET['ar_artist'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<label class="screen-reader-text" for="ar_artist">' . esc_html__( 'Filter by artist', 'celb-mgmt' ) . '</label>';
	echo '<select name="ar_artist" id="ar_artist"><option value="0">' . esc_html__( 'All artists', 'celb-mgmt' ) . '</option>';
	foreach ( get_posts( array( 'post_type' => CELB_CPT, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $a ) {
		echo '<option value="' . (int) $a->ID . '" ' . selected( $cur, $a->ID, false ) . '>' . esc_html( $a->post_title ) . '</option>';
	}
	echo '</select>';
	foreach ( array( 'ar_status', 'ar_unread' ) as $keep ) {
		if ( isset( $_GET[ $keep ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<input type="hidden" name="' . esc_attr( $keep ) . '" value="' . esc_attr( sanitize_key( wp_unslash( $_GET[ $keep ] ) ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
} );

add_action( 'pre_get_posts', function ( $q ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $q->is_main_query() || CELB_AR_CPT !== $q->get( 'post_type' ) ) {
		return;
	}
	$meta   = (array) $q->get( 'meta_query' );
	$status = isset( $_GET['ar_status'] ) ? sanitize_key( wp_unslash( $_GET['ar_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'new' === $status ) {
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => '_ar_status', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_ar_status', 'value' => array( 'new', '' ), 'compare' => 'IN' ),
		);
	} elseif ( $status && array_key_exists( $status, celb_ar_statuses() ) ) {
		$meta[] = array( 'key' => '_ar_status', 'value' => $status );
	}
	if ( isset( $_GET['ar_unread'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$meta[] = array( 'key' => '_ar_seen', 'compare' => 'NOT EXISTS' );
	}
	$artist = isset( $_GET['ar_artist'] ) ? absint( $_GET['ar_artist'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $artist ) {
		// Requests for this artist, plus "All artists" requests (stored as an empty list).
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => '_ar_artists', 'value' => 'i:' . $artist . ';', 'compare' => 'LIKE' ),
			array( 'key' => '_ar_artists', 'value' => 'a:0:{}' ),
		);
	}
	if ( $meta ) {
		$q->set( 'meta_query', $meta );
	}

	/* Search the submitted fields (name, email, phone, brand, message), not just the title. */
	$s = (string) $q->get( 's' );
	if ( '' !== $s ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $s ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 AND m.meta_key IN ('_ar_name','_ar_email','_ar_phone','_ar_wa','_ar_brand','_ar_details')
			 WHERE p.post_type = %s AND ( p.post_title LIKE %s OR m.meta_value LIKE %s )",
			CELB_AR_CPT,
			$like,
			$like
		) );
		$q->set( 's', '' );
		$q->set( 'post__in', $ids ? array_map( 'intval', $ids ) : array( 0 ) );
		$q->set( 'celb_ar_search', $s );
	}
} );
/* Keep the typed search visible in the box after we moved it into post__in. */
add_filter( 'get_search_query', function ( $q ) {
	global $wp_query;
	if ( '' === $q && $wp_query && $wp_query->get( 'celb_ar_search' ) ) {
		return esc_attr( $wp_query->get( 'celb_ar_search' ) );
	}
	return $q;
} );

/* Bulk actions: set status, mark read / unread. */
add_filter( 'bulk_actions-edit-' . CELB_AR_CPT, function ( $actions ) {
	unset( $actions['edit'] );
	$out = array();
	foreach ( celb_ar_statuses() as $k => $lbl ) {
		/* translators: %s: status name */
		$out[ 'ar_status_' . $k ] = sprintf( __( 'Mark as %s', 'celb-mgmt' ), $lbl );
	}
	$out['ar_read']   = __( 'Mark as read', 'celb-mgmt' );
	$out['ar_unread'] = __( 'Mark as unread', 'celb-mgmt' );
	return $out + $actions;
} );
add_filter( 'handle_bulk_actions-edit-' . CELB_AR_CPT, function ( $redirect, $action, $ids ) {
	$n = 0;
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		if ( 0 === strpos( $action, 'ar_status_' ) ) {
			if ( celb_ar_set_status( $id, substr( $action, 10 ) ) ) {
				$n++;
			}
		} elseif ( 'ar_read' === $action ) {
			if ( ! get_post_meta( $id, '_ar_seen', true ) ) {
				update_post_meta( $id, '_ar_seen', time() );
				$n++;
			}
		} elseif ( 'ar_unread' === $action ) {
			delete_post_meta( $id, '_ar_seen' );
			$n++;
		}
	}
	return add_query_arg( 'ar_bulk', $n, remove_query_arg( 'ar_bulk', $redirect ) );
}, 10, 3 );
add_action( 'admin_notices', function () {
	if ( 'requests' === celb_studio_screen() && isset( $_GET['ar_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = absint( $_GET['ar_bulk'] ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d request updated.', '%d requests updated.', $n, 'celb-mgmt' ), $n ) ) . '</p></div>';
	}
} );

/* =========================================================================
 * REQUEST VIEW
 * ====================================================================== */

function celb_ar_mailto( $id ) {
	$email = (string) get_post_meta( $id, '_ar_email', true );
	$first = strtok( celb_ar_name( $id ), ' ' );
	$subj  = sprintf( __( 'Your request to %s', 'celb-mgmt' ), get_bloginfo( 'name' ) );
	/* translators: %s: first name */
	$body  = sprintf( __( "Hi %s,\n\nThank you for reaching out.\n\n", 'celb-mgmt' ), $first );
	return 'mailto:' . $email . '?subject=' . rawurlencode( $subj ) . '&body=' . rawurlencode( $body );
}

/* First open marks the request as read and logs it. */
add_action( 'load-post.php', function () {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && CELB_AR_CPT === get_post_type( $id ) && ! get_post_meta( $id, '_ar_seen', true ) && current_user_can( 'edit_post', $id ) ) {
		update_post_meta( $id, '_ar_seen', time() );
		celb_ar_log( $id, 'seen' );
	}
} );

add_action( 'add_meta_boxes_' . CELB_AR_CPT, function () {
	add_meta_box( 'celb_ar_status', __( 'Status', 'celb-mgmt' ), 'celb_ar_status_box', CELB_AR_CPT, 'side', 'high' );
	remove_meta_box( 'slugdiv', CELB_AR_CPT, 'normal' );
}, 20 );

function celb_ar_status_box( $post ) {
	$cur = celb_ar_status( $post->ID );
	echo '<div class="cs-ar-status">';
	foreach ( celb_ar_statuses() as $k => $lbl ) {
		echo '<label class="cs-ar-opt cs-ar-opt--' . esc_attr( $k ) . '"><input type="radio" name="ar_status" value="' . esc_attr( $k ) . '" ' . checked( $cur, $k, false ) . ' /><span class="cs-ar-opt-dot"></span><span>' . esc_html( $lbl ) . '</span></label>';
	}
	echo '</div>';
	echo '<p class="cs-help">' . esc_html__( 'Saved with Update. Every change is recorded in the activity log.', 'celb-mgmt' ) . '</p>';
}

add_action( 'edit_form_after_title', function ( $post ) {
	if ( CELB_AR_CPT !== $post->post_type ) {
		return;
	}
	$id      = $post->ID;
	$name    = celb_ar_name( $id );
	$email   = (string) get_post_meta( $id, '_ar_email', true );
	$phone   = (string) get_post_meta( $id, '_ar_phone', true );
	$wa_raw  = (string) get_post_meta( $id, '_ar_wa', true );
	$wa      = celb_ar_wa_number( $wa_raw );
	$brand   = (string) get_post_meta( $id, '_ar_brand', true );
	$details = (string) get_post_meta( $id, '_ar_details', true );
	$artists = celb_ar_artist_ids( $id );
	$ts      = get_post_time( 'U', true, $id );
	$status  = celb_ar_status( $id );
	$older   = get_adjacent_post( false, '', true );
	$newer   = get_adjacent_post( false, '', false );
	$back    = admin_url( 'edit.php?post_type=' . CELB_AR_CPT );

	echo '<div class="cs-app cs-ar" data-cs-app="request" data-post="' . (int) $id . '">';
	wp_nonce_field( 'celb_ar_admin', 'celb_ar_nonce' );

	/* Header */
	echo '<div class="cs-ar-nav">';
	echo '<a class="cs-btn cs-btn--sm cs-btn--ghost" href="' . esc_url( $back ) . '">' . celb_studio_icon( 'list', 15 ) . '<span>' . esc_html__( 'All requests', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cs-ar-pager">';
	echo $newer ? '<a class="cs-btn cs-btn--sm" href="' . esc_url( get_edit_post_link( $newer->ID ) ) . '" title="' . esc_attr__( 'Newer request', 'celb-mgmt' ) . '">‹ <span>' . esc_html__( 'Newer', 'celb-mgmt' ) . '</span></a>' : '<span class="cs-btn cs-btn--sm is-disabled">‹ <span>' . esc_html__( 'Newer', 'celb-mgmt' ) . '</span></span>';
	echo $older ? '<a class="cs-btn cs-btn--sm" href="' . esc_url( get_edit_post_link( $older->ID ) ) . '" title="' . esc_attr__( 'Older request', 'celb-mgmt' ) . '"><span>' . esc_html__( 'Older', 'celb-mgmt' ) . '</span> ›</a>' : '<span class="cs-btn cs-btn--sm is-disabled"><span>' . esc_html__( 'Older', 'celb-mgmt' ) . '</span> ›</span>';
	echo '</span></div>';

	echo '<section class="cs-card cs-ar-head"><div class="cs-card-body">';
	echo '<div class="cs-ar-who"><span class="cs-ar-avatar cs-ar-avatar--lg">' . esc_html( celb_monogram( $name ) ) . '</span><div>';
	echo '<p class="cs-eyebrow">' . esc_html__( 'Artist request', 'celb-mgmt' ) . ' · #' . (int) $id . '</p>';
	echo '<h2 class="cs-ar-name">' . esc_html( $name ) . '</h2>';
	echo '<p class="cs-ar-meta">' . ( $brand ? '<strong>' . esc_html( $brand ) . '</strong> · ' : '' ) . '<span title="' . esc_attr( celb_ar_ago( $ts ) ) . '">' . esc_html( get_the_date( 'j F Y, H:i', $id ) ) . '</span> · ' . esc_html( celb_ar_ago( $ts ) ) . '</p>';
	echo '</div>' . celb_ar_status_badge( $status ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	echo '<div class="cs-ar-actions">';
	if ( $email ) {
		echo '<a class="cs-btn cs-btn--primary" href="' . esc_url( celb_ar_mailto( $id ) ) . '" data-cs-ar-replied>' . celb_studio_icon( 'mail', 16 ) . '<span>' . esc_html__( 'Reply by email', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $wa ) {
		$msg = sprintf( __( 'Hi %1$s, this is %2$s about your artist request.', 'celb-mgmt' ), strtok( $name, ' ' ), get_bloginfo( 'name' ) );
		echo '<a class="cs-btn cs-ar-wa" href="https://wa.me/' . esc_attr( $wa ) . '?text=' . rawurlencode( $msg ) . '" target="_blank" rel="noopener" data-cs-ar-replied>' . celb_studio_icon( 'chat', 16 ) . '<span>WhatsApp</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $phone ) {
		echo '<a class="cs-btn" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . celb_studio_icon( 'phone', 16 ) . '<span>' . esc_html__( 'Call', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $email ) {
		echo '<button type="button" class="cs-btn cs-btn--ghost" data-cs-copy="' . esc_attr( $email ) . '">' . celb_studio_icon( 'copy', 16 ) . '<span>' . esc_html__( 'Copy email', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '<p class="cs-ar-hint" data-cs-ar-hint hidden>' . celb_studio_icon( 'check', 14 ) . '<span>' . esc_html__( 'Status set to Replied — click Update to save.', 'celb-mgmt' ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div></section>';

	echo '<div class="cs-columns cs-columns--ar"><div class="cs-col">';

	/* Message */
	celb_studio_card_open( __( 'Request / campaign details', 'celb-mgmt' ), '', 'text', 'cs-ar-message' );
	echo $details ? '<div class="cs-ar-body">' . nl2br( esc_html( $details ) ) . '</div>' : '<p class="cs-muted">' . esc_html__( 'No details were provided.', 'celb-mgmt' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_card_close();

	/* Artists */
	celb_studio_card_open( $artists ? sprintf( _n( 'Requested artist', 'Requested artists (%d)', count( $artists ), 'celb-mgmt' ), count( $artists ) ) : __( 'Requested artists', 'celb-mgmt' ), '', 'star' );
	if ( ! $artists ) {
		echo '<div class="cs-note">' . celb_studio_icon( 'users', 16 ) . '<span>' . esc_html__( 'All artists — the sender is open to anyone on the roster.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<div class="cs-ar-artists">';
		foreach ( $artists as $aid ) {
			if ( ! get_post( $aid ) ) {
				continue;
			}
			$photo = celb_studio_photo_url( $aid, 'medium' );
			$sub   = implode( ' / ', array_filter( array( get_post_meta( $aid, '_celb_role', true ), get_post_meta( $aid, '_celb_nationality', true ) ) ) );
			echo '<a class="cs-ar-artist" href="' . esc_url( get_edit_post_link( $aid ) ) . '">';
			echo '<span class="cs-ar-artist-photo"' . ( $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '>' . ( $photo ? '' : esc_html( celb_monogram( get_the_title( $aid ) ) ) ) . '</span>';
			echo '<span class="cs-ar-artist-txt"><b>' . esc_html( get_the_title( $aid ) ) . '</b><small>' . esc_html( $sub ) . '</small></span></a>';
		}
		echo '</div>';
	}
	celb_studio_card_close();

	echo '</div><div class="cs-col">';

	/* Contact details */
	celb_studio_card_open( __( 'Contact', 'celb-mgmt' ), '', 'user' );
	echo '<dl class="cs-ar-dl">';
	$rows = array(
		__( 'Name', 'celb-mgmt' )     => esc_html( $name ),
		__( 'Email', 'celb-mgmt' )    => $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '',
		__( 'Phone', 'celb-mgmt' )    => $phone ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '',
		__( 'WhatsApp', 'celb-mgmt' ) => $wa ? '<a href="https://wa.me/' . esc_attr( $wa ) . '" target="_blank" rel="noopener">' . esc_html( $wa_raw ) . '</a>' : '',
		__( 'Company / brand', 'celb-mgmt' ) => esc_html( $brand ),
	);
	foreach ( $rows as $k => $v ) {
		echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . ( '' !== $v ? $v : '<span class="cs-muted">—</span>' ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</dl>';
	$ip = (string) get_post_meta( $id, '_ar_ip', true );
	if ( $ip ) {
		echo '<p class="cs-help">' . esc_html( sprintf( __( 'Sent from IP %s', 'celb-mgmt' ), $ip ) ) . '</p>';
	}
	celb_studio_card_close();

	/* Activity + notes */
	celb_studio_card_open( __( 'Activity & notes', 'celb-mgmt' ), __( 'Internal only — the sender never sees this.', 'celb-mgmt' ), 'pen' );
	echo '<div class="cs-ar-compose"><textarea class="cs-input" name="ar_note" rows="3" placeholder="' . esc_attr__( 'Add a note for the team…', 'celb-mgmt' ) . '"></textarea>';
	echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--primary" data-cs-ar-submit>' . celb_studio_icon( 'plus', 14 ) . '<span>' . esc_html__( 'Add note', 'celb-mgmt' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$log   = get_post_meta( $id, '_ar_log', true );
	$log   = is_array( $log ) ? array_reverse( $log ) : array();
	$all   = celb_ar_statuses();
	echo '<ol class="cs-ar-log">';
	foreach ( $log as $e ) {
		$u    = ! empty( $e['u'] ) ? get_userdata( (int) $e['u'] ) : false;
		$who  = $u ? $u->display_name : __( 'Someone', 'celb-mgmt' );
		$type = isset( $e['type'] ) ? $e['type'] : 'note';
		echo '<li class="cs-ar-log-item cs-ar-log--' . esc_attr( $type ) . '"><span class="cs-ar-log-dot"></span><div>';
		if ( 'note' === $type ) {
			echo '<p class="cs-ar-log-head"><b>' . esc_html( $who ) . '</b> ' . esc_html__( 'added a note', 'celb-mgmt' ) . '</p><div class="cs-ar-log-note">' . nl2br( esc_html( $e['text'] ) ) . '</div>';
		} elseif ( 'status' === $type ) {
			echo '<p class="cs-ar-log-head"><b>' . esc_html( $who ) . '</b> ' . esc_html__( 'set the status to', 'celb-mgmt' ) . ' ' . celb_ar_status_badge( $e['text'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<p class="cs-ar-log-head"><b>' . esc_html( $who ) . '</b> ' . esc_html__( 'opened the request', 'celb-mgmt' ) . '</p>';
		}
		echo '<time>' . esc_html( celb_ar_ago( (int) $e['t'] ) ) . '</time></div></li>';
	}
	echo '<li class="cs-ar-log-item cs-ar-log--received"><span class="cs-ar-log-dot"></span><div><p class="cs-ar-log-head"><b>' . esc_html( $name ) . '</b> ' . esc_html__( 'sent the request via the artist contact form', 'celb-mgmt' ) . '</p><time>' . esc_html( get_the_date( 'j M Y, H:i', $id ) ) . '</time></div></li>';
	echo '</ol>';
	celb_studio_card_close();

	echo '</div></div></div>';
} );

/* Save status + note. */
add_action( 'save_post_' . CELB_AR_CPT, function ( $post_id ) {
	if ( ! isset( $_POST['celb_ar_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_ar_nonce'] ), 'celb_ar_admin' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['ar_status'] ) ) {
		celb_ar_set_status( $post_id, sanitize_key( wp_unslash( $_POST['ar_status'] ) ) );
	}
	$note = isset( $_POST['ar_note'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['ar_note'] ) ) ) : '';
	if ( '' !== $note ) {
		celb_ar_log( $post_id, 'note', $note );
	}
} );

/* Saving a request should not show the generic "Post updated. View post" link. */
add_filter( 'post_updated_messages', function ( $m ) {
	$m[ CELB_AR_CPT ] = array_fill( 0, 11, __( 'Request updated.', 'celb-mgmt' ) );
	$m[ CELB_AR_CPT ][0] = '';
	return $m;
} );
