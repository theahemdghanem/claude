<?php
/**
 * Talent Works Archive
 * -------------------------------------------------------------------------
 * A visual archive of every production (TV series, film, theatre, commercial,
 * music video, …) managed by the agency — for both current and former talent.
 *
 * Self-contained module. Shortcode: [ilike_works_archive]
 *
 * @package celb-mgmt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELB_WORK_CPT', 'celb_work' );
define( 'CELB_WORK_OPT', 'celb_works_opts' );

/* =========================================================================
 * 1. DATA MODEL
 * ====================================================================== */

/** Production types (slug => label). Extend freely. */
function celb_work_types() {
	return array(
		'tv_series'   => __( 'TV Series', 'celb-mgmt' ),
		'mini_series' => __( 'Mini Series', 'celb-mgmt' ),
		'film'        => __( 'Film', 'celb-mgmt' ),
		'theater'     => __( 'Theater', 'celb-mgmt' ),
		'tv_show'     => __( 'TV Show', 'celb-mgmt' ),
		'commercial'  => __( 'Commercial', 'celb-mgmt' ),
		'music_video' => __( 'Music Video', 'celb-mgmt' ),
		'other'       => __( 'Other', 'celb-mgmt' ),
	);
}

/** Month number => localized name (1–12). */
function celb_work_months() {
	global $wp_locale;
	$out = array();
	for ( $m = 1; $m <= 12; $m++ ) {
		$out[ $m ] = ( $wp_locale && method_exists( $wp_locale, 'get_month' ) )
			? $wp_locale->get_month( $m )
			: gmdate( 'F', gmmktime( 0, 0, 0, $m, 1 ) );
	}
	return $out;
}

function celb_work_type_label( $slug ) {
	$t = celb_work_types();
	return isset( $t[ $slug ] ) ? $t[ $slug ] : '';
}

/** Register the archive post type as its own top-level admin menu. */
function celb_work_register_cpt() {
	$labels = array(
		'name'               => __( 'Talent Works', 'celb-mgmt' ),
		'singular_name'      => __( 'Production', 'celb-mgmt' ),
		'menu_name'          => __( 'Talent Works', 'celb-mgmt' ),
		'add_new'            => __( 'Add Production', 'celb-mgmt' ),
		'add_new_item'       => __( 'Add Production', 'celb-mgmt' ),
		'edit_item'          => __( 'Edit Production', 'celb-mgmt' ),
		'new_item'           => __( 'New Production', 'celb-mgmt' ),
		'view_item'          => __( 'View Production', 'celb-mgmt' ),
		'search_items'       => __( 'Search Productions', 'celb-mgmt' ),
		'not_found'          => __( 'No productions yet.', 'celb-mgmt' ),
		'not_found_in_trash' => __( 'No productions in trash.', 'celb-mgmt' ),
		'all_items'          => __( 'All Productions', 'celb-mgmt' ),
	);
	register_post_type( CELB_WORK_CPT, array(
		'labels'             => $labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_icon'          => 'dashicons-format-video',
		'menu_position'      => 27,
		'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'has_archive'        => false,
		'publicly_queryable' => false,
		'exclude_from_search'=> true,
		'rewrite'            => false,
		'show_in_rest'       => false,
	) );
}
add_action( 'init', 'celb_work_register_cpt' );

/** Rename the featured-image box so editors know it's the poster. */
function celb_work_poster_label( $content, $post_id ) {
	if ( get_post_type( $post_id ) === CELB_WORK_CPT ) {
		$content = str_replace( __( 'Set featured image' ), __( 'Set production poster', 'celb-mgmt' ), $content );
	}
	return $content;
}
add_filter( 'admin_post_thumbnail_html', 'celb_work_poster_label', 10, 2 );

/**
 * Build one normalized record for a production.
 *
 * @return array{id:int,title:string,title_l:string,year:int,type:string,
 *   type_label:string,date:string,order:int,poster_id:int,artists:array,blob:string}
 */
function celb_work_record( $pid ) {
	$pid      = (int) $pid;
	$title    = get_the_title( $pid );
	$type     = (string) get_post_meta( $pid, '_work_type', true );
	$year     = (int) get_post_meta( $pid, '_work_year', true );
	$month    = (int) get_post_meta( $pid, '_work_month', true );
	if ( ! $month ) {
		$legacy = (string) get_post_meta( $pid, '_work_date', true );
		if ( preg_match( '/^\d{4}-(\d{2})-\d{2}$/', $legacy, $mm ) ) {
			$month = (int) $mm[1];
		}
	}
	$ramadan  = (bool) get_post_meta( $pid, '_work_ramadan', true );
	$raw      = get_post_meta( $pid, '_work_artists', true );
	$raw      = is_array( $raw ) ? $raw : array();
	$post     = get_post( $pid );
	$artists  = array();
	$names    = array();

	foreach ( $raw as $row ) {
		$celeb = isset( $row['celeb'] ) ? (int) $row['celeb'] : 0;
		$char  = isset( $row['char'] ) ? trim( (string) $row['char'] ) : '';
		$name  = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		$url   = '';
		$key   = '';

		if ( $celeb > 0 && get_post_status( $celeb ) ) {
			$name = get_the_title( $celeb );
			$key  = 'c:' . $celeb;
			// Link to the artist's main profile page (not the Smart Link).
			// Skip private/locked profiles, which 404 for visitors.
			$locked = (bool) get_post_meta( $celeb, '_celb_locked', true );
			if ( 'publish' === get_post_status( $celeb ) && ! $locked ) {
				$url = get_permalink( $celeb );
			}
		} elseif ( '' !== $name ) {
			$key = 'n:' . strtolower( $name );
		}

		if ( '' === $name ) {
			continue;
		}
		$artists[] = array(
			'display' => $name,
			'key'     => $key,
			'url'     => $url,
			'char'    => $char,
		);
		$names[] = $name;
	}

	return array(
		'id'         => $pid,
		'title'      => $title,
		'title_l'    => function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title ),
		'year'       => $year,
		'month'      => $month,
		'type'       => $type,
		'type_label' => celb_work_type_label( $type ),
		'ramadan'    => $ramadan,
		'order'      => $post ? (int) $post->menu_order : 0,
		'poster_id'  => (int) get_post_thumbnail_id( $pid ),
		'artists'    => $artists,
		'blob'       => strtolower( $title . ' ' . implode( ' ', $names ) ),
	);
}

/** Transient key, versioned so plugin updates rebuild the dataset automatically. */
function celb_work_ds_key() {
	return 'celb_works_ds_' . CELB_VERSION;
}

/** All published productions, normalized + cached. */
function celb_work_dataset() {
	$cache = get_transient( celb_work_ds_key() );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$q = new WP_Query( array(
		'post_type'      => CELB_WORK_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );
	$out = array();
	foreach ( $q->posts as $pid ) {
		$out[] = celb_work_record( $pid );
	}
	set_transient( celb_work_ds_key(), $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/** Bust the dataset cache whenever archive content changes. */
function celb_work_bust_cache( $post_id = 0 ) {
	if ( $post_id && get_post_type( $post_id ) && get_post_type( $post_id ) !== CELB_WORK_CPT ) {
		return;
	}
	delete_transient( celb_work_ds_key() );
}
add_action( 'save_post_' . CELB_WORK_CPT, 'celb_work_bust_cache' );
add_action( 'deleted_post', 'celb_work_bust_cache' );
add_action( 'trashed_post', 'celb_work_bust_cache' );
add_action( 'untrashed_post', 'celb_work_bust_cache' );

/* =========================================================================
 * 2. ADMIN — META BOXES
 * ====================================================================== */

function celb_work_meta_boxes() {
	add_meta_box( 'work_details', __( 'Production Details', 'celb-mgmt' ), 'celb_work_mb_details', CELB_WORK_CPT, 'side', 'high' );
	add_meta_box( 'work_artists', __( 'Artists & Characters', 'celb-mgmt' ), 'celb_work_mb_artists', CELB_WORK_CPT, 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'celb_work_meta_boxes' );

function celb_work_mb_details( $post ) {
	wp_nonce_field( 'celb_work_save', 'celb_work_nonce' );
	$type    = get_post_meta( $post->ID, '_work_type', true );
	$year    = get_post_meta( $post->ID, '_work_year', true );
	$month   = (int) get_post_meta( $post->ID, '_work_month', true );
	if ( ! $month ) {
		$legacy = (string) get_post_meta( $post->ID, '_work_date', true );
		if ( preg_match( '/^\d{4}-(\d{2})-\d{2}$/', $legacy, $mm ) ) {
			$month = (int) $mm[1];
		}
	}
	$ramadan = (bool) get_post_meta( $post->ID, '_work_ramadan', true );

	echo '<p><label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html__( 'Production Type', 'celb-mgmt' ) . '</label>';
	echo '<select name="work_type" style="width:100%;">';
	echo '<option value="">' . esc_html__( '— Select —', 'celb-mgmt' ) . '</option>';
	foreach ( celb_work_types() as $slug => $label ) {
		echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $type, $slug, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html__( 'Release Year', 'celb-mgmt' ) . '</label>';
	echo '<input type="number" name="work_year" value="' . esc_attr( $year ) . '" min="1900" max="2100" placeholder="2026" style="width:100%;" /></p>';
	echo '<p><label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html__( 'Release Month', 'celb-mgmt' ) . ' <span style="font-weight:400;color:#888;">(' . esc_html__( 'optional', 'celb-mgmt' ) . ')</span></label>';
	echo '<select name="work_month" style="width:100%;">';
	echo '<option value="0">' . esc_html__( '— None —', 'celb-mgmt' ) . '</option>';
	foreach ( celb_work_months() as $n => $lbl ) {
		echo '<option value="' . esc_attr( $n ) . '" ' . selected( $month, $n, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select></p>';
	echo '<p style="margin-top:12px;"><label><input type="checkbox" name="work_ramadan" value="1" ' . checked( $ramadan, true, false ) . ' /> <strong>' . esc_html__( 'Ramadan project', 'celb-mgmt' ) . '</strong></label><br /><span style="color:#888;font-size:12px;">' . esc_html__( 'Shows a “Ramadan” badge over the poster.', 'celb-mgmt' ) . '</span></p>';
	echo '<p style="color:#888;font-size:12px;margin-top:10px;">' . esc_html__( 'The poster is the Featured Image (right/below).', 'celb-mgmt' ) . '</p>';
}

function celb_work_mb_artists( $post ) {
	$rows = get_post_meta( $post->ID, '_work_artists', true );
	$rows = is_array( $rows ) && $rows ? $rows : array( array( 'celeb' => 0, 'name' => '', 'char' => '' ) );
	$celebs = get_posts( array(
		'post_type'      => CELB_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );

	echo '<p style="color:#666;margin-top:0;">' . esc_html__( 'Attach one row per artist. Pick a roster artist (their card will link to their profile) or type a former/guest artist by name. Add a character for each. Drag rows to reorder.', 'celb-mgmt' ) . '</p>';
	echo '<div id="work-artists" class="work-artists">';
	foreach ( $rows as $i => $row ) {
		celb_work_artist_row( $i, $row, $celebs );
	}
	echo '</div>';
	echo '<p><button type="button" class="button" id="work-add-artist">+ ' . esc_html__( 'Add artist', 'celb-mgmt' ) . '</button></p>';
	echo '<script type="text/template" id="work-artist-tpl">';
	celb_work_artist_row( '__i__', array( 'celeb' => 0, 'name' => '', 'char' => '' ), $celebs );
	echo '</script>';
	celb_work_artist_row_js();
}

function celb_work_artist_row( $i, $row, $celebs ) {
	$celeb = isset( $row['celeb'] ) ? (int) $row['celeb'] : 0;
	$name  = isset( $row['name'] ) ? $row['name'] : '';
	$char  = isset( $row['char'] ) ? $row['char'] : '';
	echo '<div class="work-artist-row" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;padding:10px;border:1px solid #e0e0e0;border-radius:6px;background:#fff;">';
	echo '<span class="work-drag" style="cursor:move;color:#a0a5aa;padding-top:6px;">&#9776;</span>';
	echo '<div style="flex:1;">';
	echo '<label style="display:block;font-size:11px;color:#666;">' . esc_html__( 'Roster artist', 'celb-mgmt' ) . '</label>';
	echo '<select name="work_artists[' . esc_attr( $i ) . '][celeb]" class="work-celeb" style="width:100%;">';
	echo '<option value="0">' . esc_html__( '— none / custom below —', 'celb-mgmt' ) . '</option>';
	foreach ( $celebs as $cid ) {
		echo '<option value="' . esc_attr( $cid ) . '" ' . selected( $celeb, $cid, false ) . '>' . esc_html( get_the_title( $cid ) ) . '</option>';
	}
	echo '</select>';
	echo '</div>';
	echo '<div style="flex:1;">';
	echo '<label style="display:block;font-size:11px;color:#666;">' . esc_html__( 'or Former / custom name', 'celb-mgmt' ) . '</label>';
	echo '<input type="text" name="work_artists[' . esc_attr( $i ) . '][name]" value="' . esc_attr( $name ) . '" placeholder="' . esc_attr__( 'Artist name', 'celb-mgmt' ) . '" style="width:100%;" />';
	echo '</div>';
	echo '<div style="flex:1;">';
	echo '<label style="display:block;font-size:11px;color:#666;">' . esc_html__( 'Character', 'celb-mgmt' ) . '</label>';
	echo '<input type="text" name="work_artists[' . esc_attr( $i ) . '][char]" value="' . esc_attr( $char ) . '" placeholder="' . esc_attr__( 'Character name', 'celb-mgmt' ) . '" style="width:100%;" />';
	echo '</div>';
	echo '<button type="button" class="button-link work-remove" title="' . esc_attr__( 'Remove', 'celb-mgmt' ) . '" style="color:#b32d2e;padding-top:6px;">&times;</button>';
	echo '</div>';
}

function celb_work_artist_row_js() {
	?>
	<script>
	( function ( $ ) {
		var wrap = $( '#work-artists' );
		if ( wrap.sortable ) { wrap.sortable( { handle: '.work-drag', items: '.work-artist-row' } ); }
		$( '#work-add-artist' ).on( 'click', function () {
			var i = Date.now();
			var html = $( '#work-artist-tpl' ).html().replace( /__i__/g, i );
			wrap.append( html );
		} );
		wrap.on( 'click', '.work-remove', function () {
			if ( wrap.find( '.work-artist-row' ).length > 1 ) {
				$( this ).closest( '.work-artist-row' ).remove();
			} else {
				$( this ).closest( '.work-artist-row' ).find( 'input,select' ).val( '' );
			}
		} );
	} )( jQuery );
	</script>
	<?php
}

/** Enqueue sortable on the works editor + list screens. */
function celb_work_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== CELB_WORK_CPT ) {
		return;
	}
	wp_enqueue_script( 'jquery-ui-sortable' );
}
add_action( 'admin_enqueue_scripts', 'celb_work_admin_assets' );

/* -------- Save -------- */
function celb_work_save( $post_id ) {
	if ( ! isset( $_POST['celb_work_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['celb_work_nonce'] ), 'celb_work_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$types = celb_work_types();
	$type  = isset( $_POST['work_type'] ) ? sanitize_key( wp_unslash( $_POST['work_type'] ) ) : '';
	update_post_meta( $post_id, '_work_type', isset( $types[ $type ] ) ? $type : '' );
	update_post_meta( $post_id, '_work_year', isset( $_POST['work_year'] ) ? (int) $_POST['work_year'] : 0 );
	update_post_meta( $post_id, '_work_month', isset( $_POST['work_month'] ) ? min( 12, max( 0, (int) $_POST['work_month'] ) ) : 0 );
	update_post_meta( $post_id, '_work_ramadan', empty( $_POST['work_ramadan'] ) ? 0 : 1 );

	$clean = array();
	if ( isset( $_POST['work_artists'] ) && is_array( $_POST['work_artists'] ) ) {
		foreach ( wp_unslash( $_POST['work_artists'] ) as $row ) {
			$celeb = isset( $row['celeb'] ) ? (int) $row['celeb'] : 0;
			$name  = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$char  = isset( $row['char'] ) ? sanitize_text_field( $row['char'] ) : '';
			if ( ! $celeb && '' === $name ) {
				continue;
			}
			$clean[] = array( 'celeb' => $celeb, 'name' => $name, 'char' => $char );
		}
	}
	update_post_meta( $post_id, '_work_artists', $clean );
	delete_transient( celb_work_ds_key() );
}
add_action( 'save_post_' . CELB_WORK_CPT, 'celb_work_save' );

/* -------- Admin list columns -------- */
function celb_work_columns( $cols ) {
	$new = array();
	$new['cb']         = isset( $cols['cb'] ) ? $cols['cb'] : '';
	$new['work_thumb'] = __( 'Poster', 'celb-mgmt' );
	$new['title']      = __( 'Production', 'celb-mgmt' );
	$new['work_type']  = __( 'Type', 'celb-mgmt' );
	$new['work_year']  = __( 'Year', 'celb-mgmt' );
	$new['work_cast']  = __( 'Artists', 'celb-mgmt' );
	$new['date']       = isset( $cols['date'] ) ? $cols['date'] : __( 'Date' );
	return $new;
}
add_filter( 'manage_' . CELB_WORK_CPT . '_posts_columns', 'celb_work_columns' );

function celb_work_column( $col, $pid ) {
	if ( 'work_thumb' === $col ) {
		$tid = get_post_thumbnail_id( $pid );
		if ( $tid ) {
			echo '<img src="' . esc_url( wp_get_attachment_image_url( $tid, 'thumbnail' ) ) . '" style="width:46px;height:64px;object-fit:cover;border-radius:4px;" alt="" />';
		} else {
			echo '<span style="display:inline-block;width:46px;height:64px;background:#e6e6e6;border-radius:4px;"></span>';
		}
	} elseif ( 'work_type' === $col ) {
		echo esc_html( celb_work_type_label( get_post_meta( $pid, '_work_type', true ) ) );
	} elseif ( 'work_year' === $col ) {
		echo esc_html( get_post_meta( $pid, '_work_year', true ) );
	} elseif ( 'work_cast' === $col ) {
		$rec  = celb_work_record( $pid );
		$bits = array();
		foreach ( $rec['artists'] as $a ) {
			$bits[] = $a['display'] . ( $a['char'] ? ' (' . $a['char'] . ')' : '' );
		}
		echo esc_html( implode( ', ', $bits ) );
	}
}
add_action( 'manage_' . CELB_WORK_CPT . '_posts_custom_column', 'celb_work_column', 10, 2 );

/* -------- Admin drag & drop ordering -------- */
function celb_work_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->id === 'edit-' . CELB_WORK_CPT && ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'celb_work_admin_order' );

function celb_work_reorder_ui() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-' . CELB_WORK_CPT ) {
		return;
	}
	$nonce = wp_create_nonce( 'celb_work_reorder' );
	?>
	<script>
	( function ( $ ) {
		var list = $( '#the-list' );
		if ( ! list.length || ! list.sortable ) { return; }
		list.sortable( {
			items: 'tr',
			axis: 'y',
			cursor: 'move',
			helper: function ( e, tr ) {
				var helper = tr.clone();
				helper.children().each( function ( i ) { $( this ).width( tr.children().eq( i ).width() ); } );
				return helper;
			},
			update: function () {
				var ids = list.find( 'tr' ).map( function () { return this.id ? this.id.replace( 'post-', '' ) : null; } ).get();
				$.post( ajaxurl, { action: 'celb_work_reorder', order: ids, _nonce: '<?php echo esc_js( $nonce ); ?>' } );
			}
		} );
	} )( jQuery );
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'celb_work_reorder_ui' );

function celb_work_reorder_ajax() {
	if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'celb_work_reorder', '_nonce', false ) ) {
		wp_send_json_error();
	}
	$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'intval', wp_unslash( $_POST['order'] ) ) : array();
	$i = 0;
	foreach ( $order as $pid ) {
		if ( $pid && get_post_type( $pid ) === CELB_WORK_CPT ) {
			wp_update_post( array( 'ID' => $pid, 'menu_order' => $i ) );
			$i++;
		}
	}
	delete_transient( celb_work_ds_key() );
	wp_send_json_success();
}
add_action( 'wp_ajax_celb_work_reorder', 'celb_work_reorder_ajax' );

/* =========================================================================
 * 3. APPEARANCE SETTINGS  (own submenu, own option — never touches theme)
 * ====================================================================== */

function celb_work_opts() {
	$d = array(
		'accent_mode'  => 'default', // default | theme | custom
		'accent'       => '#999999',
		'accent_hover' => '',
		'accent_btn'   => '',
		'per_page'     => 12,
	);
	$o = get_option( CELB_WORK_OPT, array() );
	return wp_parse_args( is_array( $o ) ? $o : array(), $d );
}

function celb_work_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . CELB_WORK_CPT,
		__( 'Appearance', 'celb-mgmt' ),
		__( 'Appearance', 'celb-mgmt' ),
		'manage_options',
		'celb-works-appearance',
		'celb_work_settings_page'
	);
}
add_action( 'admin_menu', 'celb_work_settings_menu' );

function celb_work_settings_register() {
	register_setting( 'celb_works_group', CELB_WORK_OPT, 'celb_work_settings_sanitize' );
}
add_action( 'admin_init', 'celb_work_settings_register' );

function celb_work_settings_sanitize( $in ) {
	$d = celb_work_opts();
	$hex = function ( $v ) {
		$v = is_string( $v ) ? trim( $v ) : '';
		return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ? $v : '';
	};
	$mode = isset( $in['accent_mode'] ) ? (string) $in['accent_mode'] : 'default';
	if ( ! in_array( $mode, array( 'default', 'theme', 'custom' ), true ) ) {
		$mode = 'default';
	}
	return array(
		'accent_mode'  => $mode,
		'accent'       => isset( $in['accent'] ) && $hex( $in['accent'] ) ? $hex( $in['accent'] ) : $d['accent'],
		'accent_hover' => isset( $in['accent_hover'] ) ? $hex( $in['accent_hover'] ) : '',
		'accent_btn'   => isset( $in['accent_btn'] ) ? $hex( $in['accent_btn'] ) : '',
		'per_page'     => isset( $in['per_page'] ) ? max( 3, min( 60, (int) $in['per_page'] ) ) : $d['per_page'],
	);
}

function celb_work_settings_page() {
	$o = celb_work_opts();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Talent Works — Appearance', 'celb-mgmt' ); ?></h1>
		<p><?php esc_html_e( 'The archive inherits your theme’s fonts, colours and spacing by default. Optionally override just the plugin’s accent colour below — this never affects the rest of your site.', 'celb-mgmt' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'celb_works_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Accent colour', 'celb-mgmt' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px;"><input type="radio" name="<?php echo esc_attr( CELB_WORK_OPT ); ?>[accent_mode]" value="default" <?php checked( $o['accent_mode'], 'default' ); ?> /> <?php esc_html_e( 'Plugin default — soft grey (#999999) on white', 'celb-mgmt' ); ?></label>
						<label style="display:block;margin-bottom:6px;"><input type="radio" name="<?php echo esc_attr( CELB_WORK_OPT ); ?>[accent_mode]" value="theme" <?php checked( $o['accent_mode'], 'theme' ); ?> /> <?php esc_html_e( 'Inherit the theme’s accent colour', 'celb-mgmt' ); ?></label>
						<label style="display:block;"><input type="radio" name="<?php echo esc_attr( CELB_WORK_OPT ); ?>[accent_mode]" value="custom" <?php checked( $o['accent_mode'], 'custom' ); ?> /> <?php esc_html_e( 'Use a custom accent colour', 'celb-mgmt' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Custom colours', 'celb-mgmt' ); ?></th>
					<td>
						<p><label style="display:inline-block;width:150px;"><?php esc_html_e( 'Primary accent', 'celb-mgmt' ); ?></label>
						<input type="text" name="<?php echo esc_attr( CELB_WORK_OPT ); ?>[accent]" value="<?php echo esc_attr( $o['accent'] ); ?>" placeholder="#b9975b" /></p>
						<p><label style="display:inline-block;width:150px;"><?php esc_html_e( 'Hover accent', 'celb-mgmt' ); ?> <span style="color:#888;">(<?php esc_html_e( 'optional', 'celb-mgmt' ); ?>)</span></label>
						<input type="text" name="<?php echo esc_attr( CELB_WORK_OPT ); ?>[accent_hover]" value="<?php echo esc_attr( $o['accent_hover'] ); ?>" placeholder="#c9aa6c" /></p>
						<p><label style="display:inline-block;width:150px;"><?php esc_html_e( 'Button colour', 'celb-mgmt' ); ?> <span style="color:#888;">(<?php esc_html_e( 'optional', 'celb-mgmt' ); ?>)</span></label>
						<input type="text" name="<?php echo esc_attr( CELB_WORK_OPT ); ?>[accent_btn]" value="<?php echo esc_attr( $o['accent_btn'] ); ?>" placeholder="#b9975b" /></p>
						<p class="description"><?php esc_html_e( 'Only used when “custom accent” is selected above.', 'celb-mgmt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Shortcode', 'celb-mgmt' ); ?></th>
					<td><code>[ilike_works_archive]</code></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Scoped accent CSS. Theme mode inherits common theme/builder accent tokens with
 * graceful fallback to currentColor; custom mode applies the admin's colours.
 * Only ever scoped to .ilw — never leaks to the rest of the site.
 */
function celb_work_accent_css() {
	$o  = celb_work_opts();
	$on = '#fff';
	if ( 'custom' === $o['accent_mode'] ) {
		$a = $o['accent'] ? $o['accent'] : '#999999';
		$h = $o['accent_hover'] ? $o['accent_hover'] : $a;
		$b = $o['accent_btn'] ? $o['accent_btn'] : $a;
	} elseif ( 'theme' === $o['accent_mode'] ) {
		// Inherit primary accent from block themes / Elementor / Kadence / Astra; fall back to the grey default.
		$chain = 'var(--wp--preset--color--primary,var(--e-global-color-primary,var(--global-palette1,var(--ast-global-color-0,#999999))))';
		$a     = $chain;
		$h     = $chain;
		$b     = $chain;
	} else {
		// Plugin default: soft grey paired with white.
		$a = '#999999';
		$h = '#808080';
		$b = '#999999';
	}
	return '.ilw{--ilw-accent:' . esc_attr( $a ) . ';--ilw-accent-hover:' . esc_attr( $h ) . ';--ilw-btn:' . esc_attr( $b ) . ';--ilw-on-accent:' . $on . ';}';
}

/* =========================================================================
 * 4. FRONTEND — assets, shortcode, render, AJAX
 * ====================================================================== */

function celb_work_register_assets() {
	wp_register_style( 'celb-works', CELB_URL . 'assets/celb-works.css', array(), CELB_VERSION );
	wp_register_script( 'celb-works', CELB_URL . 'assets/celb-works.js', array(), CELB_VERSION, true );
}
add_action( 'init', 'celb_work_register_assets' );

function celb_work_maybe_enqueue() {
	$load = false;
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, 'ilike_works_archive' ) || has_shortcode( $post->post_content, 'CLEB_works' ) ) ) {
			$load = true;
		}
	}
	$load = apply_filters( 'celb_works_load_assets', $load );
	if ( $load ) {
		celb_work_enqueue_now();
	}
}
add_action( 'wp_enqueue_scripts', 'celb_work_maybe_enqueue' );

function celb_work_enqueue_now() {
	wp_enqueue_style( 'celb-works' );
	wp_enqueue_script( 'celb-works' );
	wp_localize_script( 'celb-works', 'CELB_WORKS', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'celb_works' ),
	) );
}

/** Distinct artists across the archive: [ key => display ]. */
function celb_work_filter_artists( $data ) {
	$out = array();
	foreach ( $data as $rec ) {
		foreach ( $rec['artists'] as $a ) {
			if ( '' !== $a['key'] && ! isset( $out[ $a['key'] ] ) ) {
				$out[ $a['key'] ] = $a['display'];
			}
		}
	}
	asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
	return $out;
}

/** Filter the dataset, then sort newest-first (year desc, then date, then order). */
function celb_work_filtered_rows( $data, $args ) {
	$artist = isset( $args['artist'] ) ? (string) $args['artist'] : '';
	$year   = isset( $args['year'] ) ? (int) $args['year'] : 0;
	$type   = isset( $args['type'] ) ? (string) $args['type'] : '';
	$search = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';

	$rows = array_filter( $data, function ( $rec ) use ( $artist, $year, $type, $search ) {
		if ( $artist ) {
			$hit = false;
			foreach ( $rec['artists'] as $a ) {
				if ( $a['key'] === $artist ) { $hit = true; break; }
			}
			if ( ! $hit ) { return false; }
		}
		if ( $year && (int) $rec['year'] !== $year ) { return false; }
		if ( $type && $rec['type'] !== $type ) { return false; }
		if ( '' !== $search && false === strpos( $rec['blob'], $search ) ) { return false; }
		return true;
	} );
	$rows = array_values( $rows );

	usort( $rows, function ( $a, $b ) {
		if ( $a['year'] !== $b['year'] ) { return $b['year'] <=> $a['year']; }
		if ( $a['month'] !== $b['month'] ) { return $b['month'] <=> $a['month']; }
		return $a['order'] <=> $b['order'];
	} );
	return $rows;
}

/**
 * Filter rows and group them by year (newest first). Returns [ groups, total ].
 * Each group: [ 'year' => int, 'items' => [] ]. No pagination — every year is
 * rendered; collapsed years simply hide their grid (and defer their images).
 */
function celb_work_grouped( $data, $args ) {
	$rows  = celb_work_filtered_rows( $data, $args );
	$total = count( $rows );

	$byyear = array();
	foreach ( $rows as $r ) {
		$byyear[ (int) $r['year'] ][] = $r;
	}
	$years = array_keys( $byyear );
	usort( $years, function ( $a, $b ) {
		if ( 0 === $a ) { return 1; }
		if ( 0 === $b ) { return -1; }
		return $b <=> $a;
	} );

	$ordered = array();
	foreach ( $years as $y ) {
		$ordered[] = array( 'year' => $y, 'items' => $byyear[ $y ] );
	}
	return array( $ordered, $total );
}

/** One production card. */
function celb_work_card_html( $rec ) {
	$h  = '<article class="ilw-card">';
	$h .= '<div class="ilw-poster">';
	if ( $rec['poster_id'] ) {
		$h .= wp_get_attachment_image( $rec['poster_id'], 'medium_large', false, array(
			'class'    => 'ilw-img',
			'loading'  => 'lazy',
			'decoding' => 'async',
			'alt'      => $rec['title'],
		) );
	} else {
		$initials = '';
		$parts = preg_split( '/\s+/', trim( $rec['title'] ) );
		foreach ( array_slice( $parts, 0, 2 ) as $p ) {
			$initials .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
		}
		$h .= '<span class="ilw-poster-fallback">' . esc_html( strtoupper( $initials ) ) . '</span>';
	}
	if ( $rec['type_label'] ) {
		$h .= '<span class="ilw-badge">' . esc_html( $rec['type_label'] ) . '</span>';
	}
	if ( ! empty( $rec['ramadan'] ) ) {
		$h .= '<span class="ilw-ramadan"><span class="ilw-ramadan-moon" aria-hidden="true">&#9789;</span>' . esc_html__( 'Ramadan', 'celb-mgmt' ) . '</span>';
	}
	$h .= '</div>';

	$h .= '<div class="ilw-body">';
	$h .= '<h3 class="ilw-title">' . esc_html( $rec['title'] ) . '</h3>';
	if ( $rec['artists'] ) {
		$h .= '<ul class="ilw-cast">';
		foreach ( $rec['artists'] as $a ) {
			$who = $a['url']
				? '<a class="ilw-artist" href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['display'] ) . '</a>'
				: '<span class="ilw-artist">' . esc_html( $a['display'] ) . '</span>';
			$char = $a['char'] ? '<span class="ilw-char">' . esc_html( $a['char'] ) . '</span>' : '';
			$h .= '<li>' . $who . $char . '</li>';
		}
		$h .= '</ul>';
	}
	$h .= '</div></article>';
	return $h;
}

function celb_work_cards_html( $slice ) {
	$out = '';
	foreach ( $slice as $rec ) {
		$out .= celb_work_card_html( $rec );
	}
	return $out;
}

/**
 * Render collapsible year sections. Current calendar year is open by default
 * (or the newest year if the current year has no works); all others collapsed.
 * When $open_all is true (a filter/search is active) every section is expanded.
 */
function celb_work_groups_html( $groups, $open_all = false ) {
	$cur     = (int) current_time( 'Y' );
	$has_cur = false;
	foreach ( $groups as $g ) {
		if ( (int) $g['year'] === $cur ) { $has_cur = true; break; }
	}
	$out = '';
	$i   = 0;
	foreach ( $groups as $g ) {
		$open = $open_all || ( $has_cur ? ( (int) $g['year'] === $cur ) : ( 0 === $i ) );
		$label = $g['year'] ? $g['year'] : __( 'Undated', 'celb-mgmt' );
		$count = count( $g['items'] );
		$out  .= '<section class="ilw-year-group' . ( $open ? ' is-open' : '' ) . '">';
		$out  .= '<button type="button" class="ilw-year-head" aria-expanded="' . ( $open ? 'true' : 'false' ) . '">';
		$out  .= '<span class="ilw-year-num">' . esc_html( $label ) . '</span>';
		$out  .= '<span class="ilw-year-count">' . esc_html( $count ) . '</span>';
		$out  .= '<span class="ilw-year-line" aria-hidden="true"></span>';
		$out  .= '<span class="ilw-year-chev" aria-hidden="true"></span>';
		$out  .= '</button>';
		$out  .= '<div class="ilw-year-body"><div class="ilw-tiles">' . celb_work_cards_html( $g['items'] ) . '</div></div>';
		$out  .= '</section>';
		$i++;
	}
	return $out;
}

/** Shortcode. */
function celb_work_shortcode( $atts ) {
	celb_work_enqueue_now(); // safety for builders that bypass post_content detection

	$data = celb_work_dataset();
	if ( ! $data ) {
		return '<div class="ilw"><p class="ilw-empty">' . esc_html__( 'No productions to display yet.', 'celb-mgmt' ) . '</p></div>';
	}

	$artists  = celb_work_filter_artists( $data );
	$years    = array();
	$types    = array();
	foreach ( $data as $rec ) {
		if ( $rec['year'] ) { $years[ (int) $rec['year'] ] = 1; }
		if ( $rec['type'] ) { $types[ $rec['type'] ] = 1; }
	}
	$years = array_keys( $years );
	rsort( $years );

	list( $groups ) = celb_work_grouped( $data, array() );

	ob_start();
	echo '<style>' . celb_work_accent_css() . '</style>'; // phpcs:ignore
	echo '<div class="ilw">';

	/* Controls */
	echo '<button type="button" class="ilw-filter-toggle" aria-expanded="false">' . esc_html__( 'Search &amp; filter', 'celb-mgmt' ) . '<span class="ilw-filter-chev" aria-hidden="true"></span></button>';
	echo '<form class="ilw-controls" onsubmit="return false;">';

	// Search
	echo '<div class="ilw-field ilw-field-search">';
	echo '<label class="ilw-label" for="ilw-search">' . esc_html__( 'Search', 'celb-mgmt' ) . '</label>';
	echo '<input type="search" id="ilw-search" class="ilw-search" placeholder="' . esc_attr__( 'Title or artist…', 'celb-mgmt' ) . '" autocomplete="off" />';
	echo '</div>';

	// Artist — searchable combobox
	echo '<div class="ilw-field ilw-combo">';
	echo '<label class="ilw-label" for="ilw-artist-input">' . esc_html__( 'Artist', 'celb-mgmt' ) . '</label>';
	echo '<div class="ilw-combo-wrap">';
	echo '<input type="text" id="ilw-artist-input" class="ilw-combo-input" placeholder="' . esc_attr__( 'All artists', 'celb-mgmt' ) . '" autocomplete="off" role="combobox" aria-expanded="false" />';
	echo '<input type="hidden" class="ilw-artist" value="" />';
	echo '<button type="button" class="ilw-combo-clear" aria-label="' . esc_attr__( 'Clear', 'celb-mgmt' ) . '" hidden>&times;</button>';
	echo '<ul class="ilw-combo-list" role="listbox" hidden>';
	echo '<li class="ilw-combo-opt" data-val="">' . esc_html__( 'All artists', 'celb-mgmt' ) . '</li>';
	foreach ( $artists as $key => $name ) {
		echo '<li class="ilw-combo-opt" data-val="' . esc_attr( $key ) . '">' . esc_html( $name ) . '</li>';
	}
	echo '</ul></div></div>';

	// Year
	echo '<div class="ilw-field">';
	echo '<label class="ilw-label" for="ilw-year">' . esc_html__( 'Year', 'celb-mgmt' ) . '</label>';
	echo '<select id="ilw-year" class="ilw-year"><option value="">' . esc_html__( 'All years', 'celb-mgmt' ) . '</option>';
	foreach ( $years as $y ) {
		echo '<option value="' . esc_attr( $y ) . '">' . esc_html( $y ) . '</option>';
	}
	echo '</select></div>';

	// Type
	echo '<div class="ilw-field">';
	echo '<label class="ilw-label" for="ilw-type">' . esc_html__( 'Type', 'celb-mgmt' ) . '</label>';
	echo '<select id="ilw-type" class="ilw-type"><option value="">' . esc_html__( 'All types', 'celb-mgmt' ) . '</option>';
	foreach ( celb_work_types() as $slug => $label ) {
		if ( isset( $types[ $slug ] ) ) {
			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
		}
	}
	echo '</select></div>';

	echo '</form>';

	echo '<div class="ilw-status" aria-live="polite"></div>';
	echo '<div class="ilw-groups">' . celb_work_groups_html( $groups, false ) . '</div>';
	echo '<p class="ilw-empty"' . ( $groups ? ' hidden' : '' ) . '>' . esc_html__( 'No productions match your filters.', 'celb-mgmt' ) . '</p>';
	echo '</div>';

	return ob_get_clean();
}
add_shortcode( 'ilike_works_archive', 'celb_work_shortcode' );
add_shortcode( 'CLEB_works', 'celb_work_shortcode' );

/** AJAX: filtered, year-grouped sections (all years; open when a filter is active). */
function celb_work_query_ajax() {
	check_ajax_referer( 'celb_works', 'nonce' );
	$data = celb_work_dataset();
	$args = array(
		'artist' => isset( $_POST['artist'] ) ? sanitize_text_field( wp_unslash( $_POST['artist'] ) ) : '',
		'year'   => isset( $_POST['year'] ) ? (int) $_POST['year'] : 0,
		'type'   => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
		'search' => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
	);
	$filtering = ( '' !== $args['artist'] || $args['year'] || '' !== $args['type'] || '' !== $args['search'] );
	list( $groups, $total ) = celb_work_grouped( $data, $args );
	wp_send_json_success( array(
		'html'  => celb_work_groups_html( $groups, $filtering ),
		'total' => $total,
	) );
}
add_action( 'wp_ajax_celb_work_query', 'celb_work_query_ajax' );
add_action( 'wp_ajax_nopriv_celb_work_query', 'celb_work_query_ajax' );
