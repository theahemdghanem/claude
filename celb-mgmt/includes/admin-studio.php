<?php
/**
 * CELB MGMT — Studio admin UI.
 *
 * Everything the team sees on four screens:
 *   1. Celebrities list   (edit.php?post_type=celebrity)
 *   2. Celebrity editor   (post.php / post-new.php for `celebrity`)
 *   3. Newsroom editor    (post.php / post-new.php for `celeb_news`)
 *   4. Settings           (Celebrities → Settings)
 *
 * Only the presentation lives here. Every field keeps the exact name the
 * existing save handlers in celb-mgmt.php read (celb_save_meta,
 * celb_news_save, celb_login_save, the smart-link toggle and the
 * celb_settings option), so stored data and behaviour are unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 0. SHARED BUILDING BLOCKS
 * ====================================================================== */

/* Small stroke icon set (24px grid, currentColor). */
function celb_studio_icon( $name, $size = 18 ) {
	$p = array(
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'text'     => '<path d="M4 6h16M4 12h16M4 18h10"/>',
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
		'film'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M17 9h4M3 15h4M17 15h4"/>',
		'award'    => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14.5-1.5 7 5-3 5 3-1.5-7"/>',
		'play'     => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/>',
		'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		'share'    => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M15 8l2 2"/>',
		'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
		'grip'     => '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
		'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
		'download' => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
		'sort'     => '<path d="M7 4v16M3 16l4 4 4-4M17 20V4M13 8l4-4 4 4"/>',
		'check'    => '<path d="m5 12 5 5 9-10"/>',
		'star'     => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
		'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
		'news'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'palette'  => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.4A4.6 4.6 0 0 0 21 9.8C21 6 17 3 12 3z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="15.5" cy="7.5" r="1"/>',
		'layout'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
		'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
		'door'     => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h8"/><path d="M10 12h11M18 9l3 3-3 3"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'chat'     => '<path d="M4 5h16v11H8l-4 4z"/>',
		'pen'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
		'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
		'phone'    => '<rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>',
		'code'     => '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>',
		'pin'      => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		'refresh'  => '<path d="M20 11a8 8 0 0 0-14.8-4M4 4v4h4M4 13a8 8 0 0 0 14.8 4M20 20v-4h-4"/>',
		'alert'    => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
		'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
	);
	$d = isset( $p[ $name ] ) ? $p[ $name ] : $p['sparkle'];
	return '<svg class="cs-ic" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* Card shell. */
function celb_studio_card_open( $title, $desc = '', $icon = '', $extra_class = '', $id = '' ) {
	echo '<section class="cs-card ' . esc_attr( $extra_class ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
	if ( $title ) {
		echo '<header class="cs-card-head">';
		if ( $icon ) {
			echo '<span class="cs-card-ic">' . celb_studio_icon( $icon ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="cs-card-titles"><h3 class="cs-card-title">' . esc_html( $title ) . '</h3>';
		if ( $desc ) {
			echo '<p class="cs-card-desc">' . wp_kses_post( $desc ) . '</p>';
		}
		echo '</div></header>';
	}
	echo '<div class="cs-card-body">';
}
function celb_studio_card_close() {
	echo '</div></section>';
}

/* Labelled field wrapper. */
function celb_studio_field_open( $label, $for = '', $hint = '', $class = '' ) {
	echo '<div class="cs-field ' . esc_attr( $class ) . '">';
	if ( '' !== $label ) {
		echo '<label class="cs-label"' . ( $for ? ' for="' . esc_attr( $for ) . '"' : '' ) . '>' . esc_html( $label ) . '</label>';
	}
	if ( $hint ) {
		echo '<p class="cs-hint">' . wp_kses_post( $hint ) . '</p>';
	}
}
function celb_studio_field_close( $help = '' ) {
	if ( $help ) {
		echo '<p class="cs-help">' . wp_kses_post( $help ) . '</p>';
	}
	echo '</div>';
}

/* Toggle switch (a real checkbox underneath, so the form posts as before). */
function celb_studio_switch( $name, $checked, $label, $desc = '', $value = '1', $extra_attr = '' ) {
	$id = 'cs-sw-' . substr( md5( $name . $label ), 0, 8 );
	echo '<label class="cs-switch" for="' . esc_attr( $id ) . '">';
	echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . checked( $checked, true, false ) . ' ' . $extra_attr . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cs-switch-track" aria-hidden="true"><span class="cs-switch-thumb"></span></span>';
	echo '<span class="cs-switch-text"><span class="cs-switch-label">' . esc_html( $label ) . '</span>';
	if ( $desc ) {
		echo '<span class="cs-switch-desc">' . wp_kses_post( $desc ) . '</span>';
	}
	echo '</span></label>';
}

/*
 * Single-image picker.
 * $store = 'id'  → the hidden input holds an attachment ID (most fields)
 * $store = 'url' → a visible URL input that the picker fills (settings)
 * $shape = square | wide | tall | portrait | logo | signature
 */
function celb_studio_media( $name, $value, $args = array() ) {
	$a = wp_parse_args( $args, array(
		'store'   => 'id',
		'shape'   => 'square',
		'label'   => __( 'Choose image', 'celb-mgmt' ),
		'hint'    => '',
		'stage'   => '',       // '' | 'dark' — background behind the image
		'id'      => '',
		'size'    => 'medium',
		'placeholder' => '',
	) );
	$url = '';
	if ( 'id' === $a['store'] ) {
		$value = (int) $value;
		$url   = $value ? wp_get_attachment_image_url( $value, 'large' === $a['size'] ? 'large' : 'medium_large' ) : '';
		if ( ! $url && $value ) {
			$url = wp_get_attachment_image_url( $value, 'full' );
		}
	} else {
		$url = (string) $value;
	}
	$has = '' !== (string) $url;
	echo '<div class="cs-media cs-media--' . esc_attr( $a['shape'] ) . ( $has ? ' has-image' : '' ) . ( 'dark' === $a['stage'] ? ' cs-media--dark' : '' ) . '" data-cs-media data-store="' . esc_attr( $a['store'] ) . '">';
	echo '<button type="button" class="cs-media-stage" data-cs-media-pick aria-label="' . esc_attr( $a['label'] ) . '">';
	echo '<span class="cs-media-img"' . ( $has ? ' style="background-image:url(' . esc_url( $url ) . ')"' : '' ) . '></span>';
	echo '<span class="cs-media-empty">' . celb_studio_icon( 'upload', 22 ) . '<span>' . esc_html( $a['label'] ) . '</span>' . ( $a['hint'] ? '<small>' . esc_html( $a['hint'] ) . '</small>' : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</button>';
	if ( 'id' === $a['store'] ) {
		echo '<input type="hidden" class="cs-media-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ? $value : '' ) . '"' . ( $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : '' ) . ' />';
	}
	echo '<div class="cs-media-bar">';
	if ( 'url' === $a['store'] ) {
		echo '<input type="url" class="cs-input cs-media-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $a['placeholder'] ? $a['placeholder'] : 'https://' ) . '"' . ( $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : '' ) . ' />';
	}
	echo '<button type="button" class="cs-btn cs-btn--sm" data-cs-media-pick>' . esc_html( $has ? __( 'Replace', 'celb-mgmt' ) : __( 'Choose', 'celb-mgmt' ) ) . '</button>';
	echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost cs-btn--danger" data-cs-media-clear' . ( $has ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'celb-mgmt' ) . '</button>';
	echo '</div></div>';
}

/* Read-only value with a copy button. */
function celb_studio_copy_field( $value, $open = false, $label = '' ) {
	echo '<div class="cs-copy">';
	if ( $label ) {
		echo '<span class="cs-copy-label">' . esc_html( $label ) . '</span>';
	}
	echo '<input type="text" class="cs-input cs-copy-input" readonly value="' . esc_attr( $value ) . '" onclick="this.select();" />';
	echo '<button type="button" class="cs-btn cs-btn--sm" data-cs-copy="' . esc_attr( $value ) . '">' . celb_studio_icon( 'copy', 15 ) . '<span>' . esc_html__( 'Copy', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $open ) {
		echo '<a class="cs-btn cs-btn--sm cs-btn--ghost" href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . celb_studio_icon( 'external', 15 ) . '<span>' . esc_html__( 'Open', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
}

/* Sortable multi-image gallery. */
function celb_studio_gallery( $name, $ids, $help = '' ) {
	$ids = is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	echo '<div class="cs-gallery" data-cs-gallery>';
	echo '<input type="hidden" class="cs-gallery-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '" />';
	echo '<ul class="cs-gallery-list">';
	foreach ( $ids as $id ) {
		$thumb = wp_get_attachment_image_url( $id, 'medium' );
		if ( ! $thumb ) {
			continue;
		}
		echo '<li class="cs-gallery-item" data-id="' . esc_attr( $id ) . '"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" /><button type="button" class="cs-gallery-remove" data-cs-gallery-remove aria-label="' . esc_attr__( 'Remove', 'celb-mgmt' ) . '">' . celb_studio_icon( 'x', 14 ) . '</button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul>';
	echo '<button type="button" class="cs-gallery-add" data-cs-gallery-add>' . celb_studio_icon( 'plus', 22 ) . '<span>' . esc_html__( 'Add images', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	if ( $help ) {
		echo '<p class="cs-help">' . esc_html( $help ) . '</p>';
	}
}

/* Repeater shell: toolbar, column header, rows, <template>, empty state. */
function celb_studio_repeater( $type, $rows, $row_cb, $args = array() ) {
	$a = wp_parse_args( $args, array(
		'add'     => __( 'Add row', 'celb-mgmt' ),
		'cols'    => array(),
		'csv'     => false,
		'sort'    => false,
		'empty'   => __( 'Nothing here yet.', 'celb-mgmt' ),
		'icon'    => 'plus',
		'blank'   => array(),
		'help'    => '',
	) );
	$rows = is_array( $rows ) ? array_values( $rows ) : array();
	echo '<div class="cs-rep cs-rep--' . esc_attr( $type ) . '" data-cs-repeater="' . esc_attr( $type ) . '">';
	echo '<div class="cs-rep-toolbar">';
	echo '<span class="cs-rep-count"><b data-cs-rep-count>' . count( $rows ) . '</b> ' . esc_html__( 'entries', 'celb-mgmt' ) . '</span>';
	echo '<span class="cs-rep-tools">';
	if ( $a['sort'] ) {
		echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost" data-cs-rep-sort title="' . esc_attr__( 'Coming soon first, then newest year first', 'celb-mgmt' ) . '">' . celb_studio_icon( 'sort', 15 ) . '<span>' . esc_html__( 'Sort by year', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $a['csv'] ) {
		echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost" data-cs-csv-sample>' . celb_studio_icon( 'download', 15 ) . '<span>' . esc_html__( 'Sample CSV', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="cs-btn cs-btn--sm cs-btn--ghost cs-file">' . celb_studio_icon( 'upload', 15 ) . '<span>' . esc_html__( 'Import CSV', 'celb-mgmt' ) . '</span><input type="file" accept=".csv,text/csv" data-cs-csv /></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--primary" data-cs-rep-add>' . celb_studio_icon( 'plus', 15 ) . '<span>' . esc_html( $a['add'] ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</span></div>';
	echo '<p class="cs-rep-status" data-cs-rep-status aria-live="polite"></p>';
	if ( $a['cols'] ) {
		echo '<div class="cs-rep-head" aria-hidden="true"><span></span>';
		foreach ( $a['cols'] as $c ) {
			echo '<span>' . esc_html( $c ) . '</span>';
		}
		echo '<span></span></div>';
	}
	echo '<div class="cs-rep-items">';
	foreach ( $rows as $i => $row ) {
		call_user_func( $row_cb, $i, $row );
	}
	echo '</div>';
	echo '<div class="cs-rep-empty"' . ( $rows ? ' hidden' : '' ) . '>' . celb_studio_icon( $a['icon'], 26 ) . '<p>' . esc_html( $a['empty'] ) . '</p><button type="button" class="cs-btn cs-btn--sm" data-cs-rep-add>' . esc_html( $a['add'] ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<template class="cs-rep-tpl">';
	call_user_func( $row_cb, '__i__', $a['blank'] );
	echo '</template>';
	if ( $a['help'] ) {
		echo '<p class="cs-help">' . esc_html( $a['help'] ) . '</p>';
	}
	echo '</div>';
}

function celb_studio_row_remove() {
	return '<button type="button" class="cs-row-remove" data-cs-rep-remove aria-label="' . esc_attr__( 'Remove row', 'celb-mgmt' ) . '">' . celb_studio_icon( 'x', 15 ) . '</button>';
}
function celb_studio_row_handle() {
	return '<span class="cs-row-handle" title="' . esc_attr__( 'Drag to reorder', 'celb-mgmt' ) . '">' . celb_studio_icon( 'grip', 16 ) . '</span>';
}

/* Workspace tab bar (sticky) with a Save proxy for the core Publish box. */
function celb_studio_tabs( $tabs, $default ) {
	echo '<div class="cs-tabs-bar"><nav class="cs-tabs" role="tablist">';
	foreach ( $tabs as $key => $t ) {
		$count = isset( $t['count'] ) ? '<span class="cs-tab-count" data-cs-count="' . esc_attr( $key ) . '"' . ( $t['count'] ? '' : ' hidden' ) . '>' . (int) $t['count'] . '</span>' : '';
		echo '<button type="button" role="tab" class="cs-tab' . ( $key === $default ? ' is-active' : '' ) . '" data-cs-tab="' . esc_attr( $key ) . '" aria-selected="' . ( $key === $default ? 'true' : 'false' ) . '">'
			. celb_studio_icon( $t['icon'], 17 ) . '<span class="cs-tab-label">' . esc_html( $t['label'] ) . '</span>' . $count . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</nav>';
	echo '<button type="button" class="cs-btn cs-btn--primary cs-save" data-cs-save>' . celb_studio_icon( 'check', 16 ) . '<span>' . esc_html__( 'Save', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}
function celb_studio_panel_open( $key, $default ) {
	echo '<div class="cs-panel' . ( $key === $default ? ' is-active' : '' ) . '" role="tabpanel" data-cs-panel="' . esc_attr( $key ) . '">';
}
function celb_studio_panel_close() {
	echo '</div>';
}

/* Is the current admin screen one of ours? Returns a key or ''. */
function celb_studio_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return '';
	}
	$screen = get_current_screen();
	if ( ! $screen ) {
		return '';
	}
	if ( isset( $_GET['page'] ) && 'celb-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return 'settings';
	}
	if ( 'post' === $screen->base && CELB_CPT === $screen->post_type ) {
		return 'celebrity';
	}
	if ( 'post' === $screen->base && 'celeb_news' === $screen->post_type ) {
		return 'news';
	}
	if ( 'edit-' . CELB_CPT === $screen->id ) {
		return 'list';
	}
	/* Other modules (e.g. Artist Requests) register their screens here. */
	return (string) apply_filters( 'celb_studio_screen', '', $screen );
}

/* =========================================================================
 * 1. ASSETS
 * ====================================================================== */

function celb_studio_enqueue( $hook ) {
	$which = celb_studio_screen();
	if ( ! $which ) {
		return;
	}
	wp_enqueue_style( 'celb-studio-fonts', 'https://fonts.googleapis.com/css2?family=Alan+Sans:wght@300..900&display=swap', array(), null );
	wp_enqueue_style( 'celb-studio', CELB_URL . 'assets/celb-studio.css', array(), CELB_VERSION );

	$deps = array( 'jquery' );
	if ( in_array( $which, array( 'celebrity', 'news', 'settings' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		$deps[] = 'jquery-ui-sortable';
	}
	if ( 'settings' === $which ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		$deps[] = 'wp-color-picker';
	}
	wp_enqueue_script( 'celb-studio', CELB_URL . 'assets/celb-studio.js', $deps, CELB_VERSION, true );
	wp_localize_script( 'celb-studio', 'CELB_STUDIO', array(
		'screen' => $which,
		'i18n'   => array(
			'pick'        => __( 'Select image', 'celb-mgmt' ),
			'use'         => __( 'Use image', 'celb-mgmt' ),
			'addImages'   => __( 'Add images', 'celb-mgmt' ),
			'choose'      => __( 'Choose', 'celb-mgmt' ),
			'replace'     => __( 'Replace', 'celb-mgmt' ),
			'copied'      => __( 'Copied', 'celb-mgmt' ),
			'imported'    => __( '%d row(s) imported — save to keep them.', 'celb-mgmt' ),
			'readError'   => __( 'Could not read that file.', 'celb-mgmt' ),
			'unsaved'     => __( 'Unsaved changes', 'celb-mgmt' ),
			'allSaved'    => __( 'All changes saved', 'celb-mgmt' ),
			'confirmLeave'=> __( 'You have unsaved changes.', 'celb-mgmt' ),
			'noMatches'   => __( 'No settings match your search.', 'celb-mgmt' ),
			'mediaLoading'=> __( 'The media library is still loading — try again in a moment.', 'celb-mgmt' ),
		),
	) );

	/* Canvas card generators (announcement / social card). */
	global $post;
	$pid = ( $post && in_array( $which, array( 'celebrity', 'news' ), true ) ) ? (int) $post->ID : 0;
	if ( 'news' === $which ) {
		$custom_id = $pid ? (int) get_post_meta( $pid, '_news_card_img', true ) : 0;
		$card_img  = $custom_id ? wp_get_attachment_image_url( $custom_id, 'full' ) : '';
		if ( ! $card_img && $pid ) {
			$card_img = get_the_post_thumbnail_url( $pid, 'full' );
		}
		if ( ! $card_img && $pid ) {
			$card_img = celb_news_header_img( $pid, 'full' );
		}
		$card_title = $pid ? get_the_title( $pid ) : '';
		$card_slug  = sanitize_title( $card_title );
		$cel_id     = $pid ? (int) get_post_meta( $pid, '_news_celebrity', true ) : 0;
		wp_enqueue_style( 'celb-card-fonts', 'https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Raleway:wght@600;700;800&display=swap', array(), null );
		wp_enqueue_script( 'celb-card', CELB_URL . 'assets/celb-card.js', array(), CELB_VERSION, true );
		wp_localize_script( 'celb-card', 'CELB_CARD', array(
			'image'    => $card_img ? $card_img : '',
			'logo'     => celb_logo_url(),
			'title'    => $card_title,
			'name'     => $cel_id ? get_the_title( $cel_id ) : '',
			'accent'   => celb_accent(),
			'location' => $pid ? get_post_meta( $pid, '_news_location', true ) : '',
			'date'     => $pid ? get_the_date( 'Y-m-d', $pid ) : '',
			'filename' => $card_slug ? $card_slug . '-card' : 'news-card',
		) );
	}
	if ( 'celebrity' === $which ) {
		$custom_id = $pid ? (int) get_post_meta( $pid, '_celb_card_img', true ) : 0;
		$card_img  = $custom_id ? wp_get_attachment_image_url( $custom_id, 'full' ) : '';
		if ( ! $card_img && $pid ) {
			$prof_id  = (int) get_post_meta( $pid, '_celb_profile', true );
			$card_img = $prof_id ? wp_get_attachment_image_url( $prof_id, 'full' ) : '';
		}
		if ( ! $card_img && $pid ) {
			$card_img = get_the_post_thumbnail_url( $pid, 'full' );
		}
		$name     = $pid ? get_the_title( $pid ) : '';
		$sub      = $pid ? implode( ' / ', array_filter( array( get_post_meta( $pid, '_celb_nationality', true ), get_post_meta( $pid, '_celb_role', true ) ) ) ) : '';
		$url      = $pid ? celb_smartlink_url( $pid ) : '';
		$url_disp = $url ? preg_replace( '#^https?://#', '', untrailingslashit( $url ) ) : '';
		wp_enqueue_style( 'celb-card-fonts', 'https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Raleway:wght@600;700;800&display=swap', array(), null );
		wp_enqueue_script( 'celb-card', CELB_URL . 'assets/celb-card.js', array(), CELB_VERSION, true );
		wp_localize_script( 'celb-card', 'CELB_CARD', array(
			'image'        => $card_img ? $card_img : '',
			'logo'         => celb_logo_url(),
			'title'        => $name ? $name . ' joins iLike Agency' : '',
			'subtitle'     => $sub,
			'eyebrow'      => 'Announcement',
			'eyebrowIcon'  => 'announcement',
			'titleOneLine' => true,
			'moreLabel'    => 'Know more',
			'moreUrl'      => $url_disp,
			'accent'       => celb_accent(),
			'filename'     => ( $name ? sanitize_title( $name ) : 'celebrity' ) . '-announcement',
		) );
	}
}
add_action( 'admin_enqueue_scripts', 'celb_studio_enqueue' );

/* Body classes so the stylesheet only ever touches our screens. */
add_filter( 'admin_body_class', function ( $classes ) {
	$which = celb_studio_screen();
	if ( ! $which ) {
		return $classes;
	}
	/* Both inbox-style lists share the list styling. */
	$extra = 'requests' === $which ? ' cs-screen-list' : ( 'list' === $which ? ' cs-screen-roster' : '' );
	return $classes . ' celb-studio cs-screen-' . $which . $extra . ' ';
} );

/* =========================================================================
 * 2. PROFILE COMPLETENESS (shared by the editor and the list)
 * ====================================================================== */

function celb_studio_checks( $post_id ) {
	$bio    = (string) get_post_meta( $post_id, '_celb_bio', true );
	if ( '' === trim( wp_strip_all_tags( $bio ) ) ) {
		$bio = (string) get_post_field( 'post_content', $post_id );
	}
	$social = false;
	foreach ( array_keys( celb_social_platforms() ) as $k ) {
		if ( get_post_meta( $post_id, '_celb_social_' . $k, true ) ) {
			$social = true;
			break;
		}
	}
	$career  = get_post_meta( $post_id, '_celb_career', true );
	$gallery = get_post_meta( $post_id, '_celb_gallery', true );
	$photo   = (int) get_post_meta( $post_id, '_celb_profile', true ) || has_post_thumbnail( $post_id );
	return array(
		'photo'       => array( __( 'Profile image', 'celb-mgmt' ), $photo, 'media' ),
		'role'        => array( __( 'Role', 'celb-mgmt' ), '' !== (string) get_post_meta( $post_id, '_celb_role', true ), 'profile' ),
		'nationality' => array( __( 'Nationality', 'celb-mgmt' ), '' !== (string) get_post_meta( $post_id, '_celb_nationality', true ), 'profile' ),
		'bio'         => array( __( 'Biography', 'celb-mgmt' ), '' !== trim( wp_strip_all_tags( $bio ) ), 'bio' ),
		'hero_d'      => array( __( 'Desktop hero', 'celb-mgmt' ), (bool) get_post_meta( $post_id, '_celb_hero_desktop', true ), 'media' ),
		'hero_m'      => array( __( 'Mobile hero', 'celb-mgmt' ), (bool) get_post_meta( $post_id, '_celb_hero_mobile', true ), 'media' ),
		'social'      => array( __( 'A social link', 'celb-mgmt' ), $social, 'social' ),
		'career'      => array( __( 'Career history', 'celb-mgmt' ), is_array( $career ) && count( $career ) > 0, 'career' ),
		'gallery'     => array( __( 'Gallery photos', 'celb-mgmt' ), is_array( $gallery ) && count( array_filter( $gallery ) ) > 0, 'media' ),
	);
}
function celb_studio_score( $checks ) {
	$done = 0;
	foreach ( $checks as $c ) {
		if ( $c[1] ) {
			$done++;
		}
	}
	return $checks ? (int) round( 100 * $done / count( $checks ) ) : 0;
}
function celb_studio_photo_url( $post_id, $size = 'medium' ) {
	$pid = (int) get_post_meta( $post_id, '_celb_profile', true );
	$url = $pid ? wp_get_attachment_image_url( $pid, $size ) : '';
	return $url ? $url : (string) get_the_post_thumbnail_url( $post_id, $size );
}

/* =========================================================================
 * 3. CELEBRITY EDITOR
 * ====================================================================== */

/* Ensure the right meta boxes: our snapshot in the sidebar; the old field
   boxes are gone (their content lives in the workspace tabs). */
add_action( 'add_meta_boxes_' . CELB_CPT, function () {
	add_meta_box( 'celb_snapshot', __( 'Profile', 'celb-mgmt' ), 'celb_studio_snapshot_box', CELB_CPT, 'side', 'high' );
	remove_meta_box( 'postimagediv', CELB_CPT, 'side' );
	add_meta_box( 'postimagediv', __( 'Featured image (fallback)', 'celb-mgmt' ), 'post_thumbnail_meta_box', CELB_CPT, 'side', 'low' );
}, 20 );


add_action( 'edit_form_after_title', function ( $post ) {
	if ( CELB_CPT === $post->post_type ) {
		celb_studio_celebrity_workspace( $post );
	} elseif ( 'celeb_news' === $post->post_type ) {
		celb_studio_news_workspace( $post );
	}
} );

function celb_studio_celebrity_workspace( $post ) {
	$id      = $post->ID;
	$career  = get_post_meta( $id, '_celb_career', true );
	$awards  = get_post_meta( $id, '_celb_awards', true );
	$videos  = get_post_meta( $id, '_celb_videos', true );
	$gallery = get_post_meta( $id, '_celb_gallery', true );
	$career  = is_array( $career ) ? $career : array();
	$awards  = is_array( $awards ) ? $awards : array();
	$videos  = is_array( $videos ) ? $videos : array();
	$gallery = is_array( $gallery ) ? array_filter( $gallery ) : array();
	$social_n = 0;
	foreach ( array_keys( celb_social_platforms() ) as $k ) {
		if ( get_post_meta( $id, '_celb_social_' . $k, true ) ) {
			$social_n++;
		}
	}
	$tabs = array(
		'profile' => array( 'label' => __( 'Profile', 'celb-mgmt' ), 'icon' => 'user' ),
		'bio'     => array( 'label' => __( 'Biography', 'celb-mgmt' ), 'icon' => 'text' ),
		'media'   => array( 'label' => __( 'Photos', 'celb-mgmt' ), 'icon' => 'image', 'count' => count( $gallery ) ),
		'career'  => array( 'label' => __( 'Career', 'celb-mgmt' ), 'icon' => 'film', 'count' => count( $career ) ),
		'awards'  => array( 'label' => __( 'Awards', 'celb-mgmt' ), 'icon' => 'award', 'count' => count( $awards ) ),
		'videos'  => array( 'label' => __( 'Videos', 'celb-mgmt' ), 'icon' => 'play', 'count' => count( $videos ) ),
		'social'  => array( 'label' => __( 'Social', 'celb-mgmt' ), 'icon' => 'globe', 'count' => $social_n ),
		'share'   => array( 'label' => __( 'Share & Access', 'celb-mgmt' ), 'icon' => 'share' ),
	);
	$default = 'profile';

	echo '<div class="cs-app cs-app--celebrity" data-cs-app="celebrity" data-post="' . (int) $id . '">';
	wp_nonce_field( 'celb_save_meta', 'celb_meta_nonce' );
	celb_project_types_datalist();
	celb_studio_tabs( $tabs, $default );

	/* ---- Profile ---- */
	celb_studio_panel_open( 'profile', $default );
	$role      = get_post_meta( $id, '_celb_role', true );
	$nat       = get_post_meta( $id, '_celb_nationality', true );
	$birth     = get_post_meta( $id, '_celb_birthdate', true );
	$show_year = '1' === get_post_meta( $id, '_celb_show_year', true );
	$stored    = get_post_meta( $id, '_celb_category', true );
	$sel       = is_array( $stored ) ? $stored : ( '' !== $stored ? array( $stored ) : array() );
	$sel       = array_map( function ( $c ) { return 'music' === $c ? 'musician' : $c; }, $sel );
	$defs      = celb_roster_cat_defs();

	echo '<div class="cs-grid cs-grid--2">';
	celb_studio_card_open( __( 'Identity', 'celb-mgmt' ), __( 'Shown under the name everywhere as “Role / Nationality”.', 'celb-mgmt' ), 'user' );
	echo '<div class="cs-row-fields">';
	celb_studio_field_open( __( 'Role', 'celb-mgmt' ), 'celb_role' );
	echo '<input type="text" class="cs-input" id="celb_role" name="celb_role" value="' . esc_attr( $role ) . '" placeholder="' . esc_attr__( 'Actor, Actress, Presenter, Singer…', 'celb-mgmt' ) . '" data-cs-live="role" />';
	celb_studio_field_close();
	celb_studio_field_open( __( 'Nationality', 'celb-mgmt' ), 'celb_nationality' );
	echo '<input type="text" class="cs-input" id="celb_nationality" name="celb_nationality" value="' . esc_attr( $nat ) . '" placeholder="' . esc_attr__( 'Egyptian, Lebanese…', 'celb-mgmt' ) . '" data-cs-live="nationality" />';
	celb_studio_field_close();
	echo '</div>';
	celb_studio_field_open( __( 'Birthdate', 'celb-mgmt' ), 'celb_birthdate', '', 'cs-field--inline' );
	echo '<div class="cs-inline"><input type="date" class="cs-input cs-input--auto" id="celb_birthdate" name="celb_birthdate" value="' . esc_attr( $birth ) . '" />';
	celb_studio_switch( 'celb_show_year', $show_year, __( 'Show year publicly', 'celb-mgmt' ) );
	echo '</div>';
	celb_studio_field_close( __( 'Optional. On their birthday the profile shows a “Happy Birthday” greeting with confetti.', 'celb-mgmt' ) );
	celb_studio_card_close();

	celb_studio_card_open( __( 'Roster placement', 'celb-mgmt' ), __( 'Up to two categories — the talent appears under each in the roster filter.', 'celb-mgmt' ), 'tag' );
	echo '<div class="cs-row-fields">';
	for ( $n = 0; $n < 2; $n++ ) {
		$cur = isset( $sel[ $n ] ) ? $sel[ $n ] : '';
		celb_studio_field_open( 0 === $n ? __( 'Primary category', 'celb-mgmt' ) : __( 'Second category', 'celb-mgmt' ), 'celb_category_' . $n );
		echo '<select class="cs-input" id="celb_category_' . (int) $n . '" name="celb_category[]">';
		echo '<option value="">' . esc_html( 0 === $n ? __( 'Auto — guess from role', 'celb-mgmt' ) : __( 'None', 'celb-mgmt' ) ) . '</option>';
		foreach ( $defs as $k => $lbl ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select>';
		celb_studio_field_close();
	}
	echo '</div>';
	echo '<div class="cs-stack">';
	celb_studio_switch( 'celb_lead', '1' === get_post_meta( $id, '_celb_lead', true ), __( 'Lead talent', 'celb-mgmt' ), __( 'Shows a “Lead” badge and is listed first on the Our Stars page.', 'celb-mgmt' ), '1', 'data-cs-live="lead"' );
	echo '</div>';
	celb_studio_card_close();
	echo '</div>';

	celb_studio_card_open( __( 'Visibility', 'celb-mgmt' ), '', 'eye' );
	celb_studio_switch( 'celb_locked', '1' === get_post_meta( $id, '_celb_locked', true ), __( 'Keep profile private', 'celb-mgmt' ), __( 'The talent still appears in the grid and carousel (photo, name, role / nationality), but the card is not clickable and the profile page is hidden from visitors. You can still open it while logged in.', 'celb-mgmt' ), '1', 'data-cs-live="locked"' );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Biography ---- */
	celb_studio_panel_open( 'bio', $default );
	celb_studio_card_open( __( 'Biography', 'celb-mgmt' ), __( 'Shown under the “Biography” heading on the profile and as the intro on the smart link.', 'celb-mgmt' ), 'text', 'cs-card--editor' );
	$bio = get_post_meta( $id, '_celb_bio', true );
	if ( '' === $bio && ! empty( $post->post_content ) ) {
		$bio = $post->post_content; // carry legacy body content over
	}
	wp_editor( $bio, 'celb_bio_editor', array(
		'textarea_name' => 'celb_bio',
		'media_buttons' => true,
		'editor_height' => 380,
		'teeny'         => false,
	) );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Photos ---- */
	celb_studio_panel_open( 'media', $default );
	echo '<div class="cs-grid cs-grid--media">';
	celb_studio_card_open( __( 'Profile image', 'celb-mgmt' ), __( 'Square portrait for grid and carousel cards. Falls back to the featured image.', 'celb-mgmt' ), 'user' );
	celb_studio_media( 'celb_profile', get_post_meta( $id, '_celb_profile', true ), array( 'shape' => 'square', 'hint' => __( 'Square · 1:1', 'celb-mgmt' ), 'id' => 'celb_profile' ) );
	celb_studio_card_close();
	celb_studio_card_open( __( 'Hero images', 'celb-mgmt' ), __( 'The full-bleed banner at the top of the profile — a wide crop for desktop and a tall one for phones.', 'celb-mgmt' ), 'layout' );
	echo '<div class="cs-hero-pair">';
	echo '<div class="cs-hero-slot cs-hero-slot--wide"><span class="cs-mini-label">' . celb_studio_icon( 'layout', 14 ) . esc_html__( 'Desktop', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_media( 'celb_hero_desktop', get_post_meta( $id, '_celb_hero_desktop', true ), array( 'shape' => 'wide', 'hint' => __( 'Landscape · 16:9', 'celb-mgmt' ) ) );
	echo '</div><div class="cs-hero-slot cs-hero-slot--tall"><span class="cs-mini-label">' . celb_studio_icon( 'phone', 14 ) . esc_html__( 'Mobile', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_media( 'celb_hero_mobile', get_post_meta( $id, '_celb_hero_mobile', true ), array( 'shape' => 'tall', 'hint' => __( 'Portrait · 9:16', 'celb-mgmt' ) ) );
	echo '</div></div>';
	celb_studio_card_close();
	echo '</div>';
	celb_studio_card_open( __( 'Photo gallery', 'celb-mgmt' ), __( 'Drag to reorder. Shown as square thumbnails with a zoomable lightbox.', 'celb-mgmt' ), 'image' );
	celb_studio_gallery( 'celb_gallery', $gallery );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Career ---- */
	celb_studio_panel_open( 'career', $default );
	celb_studio_card_open( __( 'Career history', 'celb-mgmt' ), __( 'Shown newest year first. “Coming soon” pins a project to the top with a badge.', 'celb-mgmt' ), 'film' );
	celb_studio_repeater( 'career', $career, 'celb_studio_career_row', array(
		'add'   => __( 'Add project', 'celb-mgmt' ),
		'cols'  => array( __( 'Project', 'celb-mgmt' ), __( 'Character / role', 'celb-mgmt' ), __( 'Type', 'celb-mgmt' ), __( 'Year', 'celb-mgmt' ), __( 'Flags', 'celb-mgmt' ) ),
		'csv'   => true,
		'sort'  => true,
		'icon'  => 'film',
		'empty' => __( 'No projects yet. Add one or import a CSV (Project, Role, Year, Type).', 'celb-mgmt' ),
	) );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Awards ---- */
	celb_studio_panel_open( 'awards', $default );
	celb_studio_card_open( __( 'Awards', 'celb-mgmt' ), __( 'Leave Project empty for Lifetime Achievement or honorary awards.', 'celb-mgmt' ), 'award' );
	celb_studio_repeater( 'awards', $awards, 'celb_studio_award_row', array(
		'add'   => __( 'Add award', 'celb-mgmt' ),
		'cols'  => array( __( 'Festival / organisation', 'celb-mgmt' ), __( 'Award title', 'celb-mgmt' ), __( 'Project', 'celb-mgmt' ), __( 'Year', 'celb-mgmt' ), __( 'Location', 'celb-mgmt' ) ),
		'csv'   => true,
		'sort'  => true,
		'icon'  => 'award',
		'empty' => __( 'No awards yet. Add one or import a CSV (Festival, Award Title, Project, Year, Location).', 'celb-mgmt' ),
	) );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Videos ---- */
	celb_studio_panel_open( 'videos', $default );
	celb_studio_card_open( __( 'Videos', 'celb-mgmt' ), __( 'YouTube and Vimeo play inline; Instagram and TikTok embed as posts; anything else shows as a “Watch” card. A cover image controls the thumbnail.', 'celb-mgmt' ), 'play' );
	celb_studio_field_open( __( 'Section title', 'celb-mgmt' ), 'celb_videos_title', '', 'cs-field--narrow' );
	echo '<input type="text" class="cs-input" id="celb_videos_title" name="celb_videos_title" value="' . esc_attr( get_post_meta( $id, '_celb_videos_title', true ) ) . '" placeholder="' . esc_attr__( 'Videos', 'celb-mgmt' ) . '" />';
	celb_studio_field_close();
	celb_studio_repeater( 'videos', $videos, 'celb_studio_video_row', array(
		'add'   => __( 'Add video', 'celb-mgmt' ),
		'icon'  => 'play',
		'empty' => __( 'No videos yet. Paste a YouTube, Vimeo, Instagram or TikTok link.', 'celb-mgmt' ),
	) );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Social ---- */
	celb_studio_panel_open( 'social', $default );
	celb_studio_card_open( __( 'Social media', 'celb-mgmt' ), __( 'Only filled-in platforms appear on the profile and the smart link.', 'celb-mgmt' ), 'globe' );
	echo '<div class="cs-social">';
	foreach ( celb_social_platforms() as $key => $label ) {
		$val = get_post_meta( $id, '_celb_social_' . $key, true );
		echo '<label class="cs-social-item' . ( $val ? ' is-filled' : '' ) . '" for="celb_social_' . esc_attr( $key ) . '">';
		echo '<span class="cs-social-ic cs-social-ic--' . esc_attr( $key ) . '">' . celb_social_icon( $key ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="cs-social-main"><span class="cs-social-name">' . esc_html( $label ) . '</span>';
		echo '<input type="url" class="cs-input cs-input--bare" id="celb_social_' . esc_attr( $key ) . '" name="celb_social_' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" placeholder="https://" data-cs-social /></span>';
		echo '<span class="cs-social-dot" aria-hidden="true"></span></label>';
	}
	echo '</div>';
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Share & Access ---- */
	celb_studio_panel_open( 'share', $default );
	/* Two independent columns so a short card never leaves a gap beside a tall one. */
	echo '<div class="cs-columns">';
	echo '<div class="cs-col">';
	celb_studio_smartlink_card( $post );
	celb_studio_announce_card( $post );
	echo '</div><div class="cs-col">';
	celb_studio_cal_card( $post );
	celb_studio_login_card( $post );
	echo '</div></div>';
	celb_studio_panel_close();

	echo '</div>';
}

function celb_studio_career_row( $i, $row ) {
	$row = wp_parse_args( is_array( $row ) ? $row : array(), array( 'project' => '', 'role' => '', 'year' => '', 'type' => '', 'special' => 0, 'production' => 0 ) );
	$n   = 'celb_career[' . $i . ']';
	echo '<div class="cs-row cs-row--career' . ( ! empty( $row['production'] ) ? ' is-soon' : '' ) . '">';
	echo celb_studio_row_handle(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[project]" value="' . esc_attr( $row['project'] ) . '" placeholder="' . esc_attr__( 'Project name', 'celb-mgmt' ) . '" aria-label="' . esc_attr__( 'Project', 'celb-mgmt' ) . '" />';
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[role]" value="' . esc_attr( $row['role'] ) . '" placeholder="' . esc_attr__( 'Character / role', 'celb-mgmt' ) . '" aria-label="' . esc_attr__( 'Role', 'celb-mgmt' ) . '" />';
	echo '<input type="text" class="cs-input" list="celb-project-types" name="' . esc_attr( $n ) . '[type]" value="' . esc_attr( $row['type'] ) . '" placeholder="' . esc_attr__( 'Type', 'celb-mgmt' ) . '" aria-label="' . esc_attr__( 'Type', 'celb-mgmt' ) . '" />';
	echo '<input type="number" class="cs-input" name="' . esc_attr( $n ) . '[year]" value="' . esc_attr( $row['year'] ? $row['year'] : '' ) . '" placeholder="' . esc_attr__( 'Year', 'celb-mgmt' ) . '" min="1900" max="2100" aria-label="' . esc_attr__( 'Year', 'celb-mgmt' ) . '" data-cs-year />';
	echo '<span class="cs-chips">';
	echo '<label class="cs-chip" title="' . esc_attr__( 'Special / guest appearance', 'celb-mgmt' ) . '"><input type="checkbox" name="' . esc_attr( $n ) . '[special]" value="1" ' . checked( ! empty( $row['special'] ), true, false ) . ' /><span>' . esc_html__( 'Special', 'celb-mgmt' ) . '</span></label>';
	echo '<label class="cs-chip cs-chip--gold" title="' . esc_attr__( 'Under production — shows “Coming Soon” and pins to the top', 'celb-mgmt' ) . '"><input type="checkbox" name="' . esc_attr( $n ) . '[production]" value="1" ' . checked( ! empty( $row['production'] ), true, false ) . ' data-cs-soon /><span>' . esc_html__( 'Coming soon', 'celb-mgmt' ) . '</span></label>';
	echo '</span>';
	echo celb_studio_row_remove(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

function celb_studio_award_row( $i, $row ) {
	$row = wp_parse_args( is_array( $row ) ? $row : array(), array( 'festival' => '', 'title' => '', 'project' => '', 'year' => '', 'location' => '' ) );
	$n   = 'celb_awards[' . $i . ']';
	echo '<div class="cs-row cs-row--awards">';
	echo celb_studio_row_handle(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[festival]" value="' . esc_attr( $row['festival'] ) . '" placeholder="' . esc_attr__( 'Festival / organisation', 'celb-mgmt' ) . '" />';
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[title]" value="' . esc_attr( $row['title'] ) . '" placeholder="' . esc_attr__( 'Award title', 'celb-mgmt' ) . '" />';
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[project]" value="' . esc_attr( $row['project'] ) . '" placeholder="' . esc_attr__( 'Project (optional)', 'celb-mgmt' ) . '" />';
	echo '<input type="number" class="cs-input" name="' . esc_attr( $n ) . '[year]" value="' . esc_attr( $row['year'] ? $row['year'] : '' ) . '" placeholder="' . esc_attr__( 'Year', 'celb-mgmt' ) . '" min="1900" max="2100" data-cs-year />';
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[location]" value="' . esc_attr( $row['location'] ) . '" placeholder="' . esc_attr__( 'Location', 'celb-mgmt' ) . '" />';
	echo celb_studio_row_remove(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

function celb_studio_video_row( $i, $row ) {
	$row      = wp_parse_args( is_array( $row ) ? $row : array(), array( 'name' => '', 'url' => '', 'cover' => 0 ) );
	$cover_id = (int) $row['cover'];
	$cover    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : '';
	$n        = 'celb_videos[' . $i . ']';
	$platform = $row['url'] && function_exists( 'celb_video_platform' ) ? celb_video_platform( $row['url'] ) : '';
	echo '<div class="cs-row cs-row--video">';
	echo celb_studio_row_handle(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cs-vcover' . ( $cover ? ' has-image' : '' ) . '" data-cs-media data-store="id">';
	echo '<button type="button" class="cs-vcover-stage" data-cs-media-pick title="' . esc_attr__( 'Set cover image', 'celb-mgmt' ) . '"><span class="cs-media-img"' . ( $cover ? ' style="background-image:url(' . esc_url( $cover ) . ')"' : '' ) . '></span><span class="cs-media-empty">' . celb_studio_icon( 'image', 18 ) . '<small>' . esc_html__( 'Cover', 'celb-mgmt' ) . '</small></span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="hidden" class="cs-media-input" name="' . esc_attr( $n ) . '[cover]" value="' . esc_attr( $cover_id ? $cover_id : '' ) . '" />';
	echo '<button type="button" class="cs-vcover-clear" data-cs-media-clear' . ( $cover ? '' : ' hidden' ) . ' aria-label="' . esc_attr__( 'Remove cover', 'celb-mgmt' ) . '">' . celb_studio_icon( 'x', 12 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	echo '<div class="cs-vfields">';
	echo '<input type="text" class="cs-input cs-input--strong" name="' . esc_attr( $n ) . '[name]" value="' . esc_attr( $row['name'] ) . '" placeholder="' . esc_attr__( 'Video name (e.g. Latest Music Video)', 'celb-mgmt' ) . '" />';
	echo '<div class="cs-vurl"><span class="cs-platform" data-cs-platform>' . esc_html( $platform ? celb_platform_label( $platform ) : __( 'Link', 'celb-mgmt' ) ) . '</span>';
	echo '<input type="url" class="cs-input" name="' . esc_attr( $n ) . '[url]" value="' . esc_attr( $row['url'] ) . '" placeholder="https://youtube.com/…" data-cs-video-url /></div>';
	echo '</div>';
	echo celb_studio_row_remove(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

/* Share & Access cards ---------------------------------------------------- */

function celb_studio_smartlink_card( $post ) {
	celb_studio_card_open( __( 'Smart link', 'celb-mgmt' ), __( 'Premium shareable landing page that updates itself with this profile.', 'celb-mgmt' ), 'link' );
	if ( 'publish' !== $post->post_status ) {
		echo '<div class="cs-note">' . celb_studio_icon( 'alert', 16 ) . '<span>' . esc_html__( 'Publish this profile to generate its smart link.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		celb_studio_card_close();
		return;
	}
	wp_nonce_field( 'celb_sl_rate_save', 'celb_sl_rate_nonce' );
	celb_studio_copy_field( celb_smartlink_url( $post->ID ), true );
	$rate_url = celb_rate_smartlink_url( $post->ID );
	echo '<div class="cs-divider"></div>';
	if ( $rate_url ) {
		celb_studio_switch( 'celb_sl_rate', get_post_meta( $post->ID, '_celb_sl_rate', true ) !== '0', __( 'Show “View Rate Card” button', 'celb-mgmt' ), sprintf( '%s <a href="%s" target="_blank" rel="noopener">%s</a>', esc_html__( 'Links to the private rate card (still password-protected).', 'celb-mgmt' ), esc_url( $rate_url ), esc_html__( 'Open rate card', 'celb-mgmt' ) ) );
	} else {
		if ( get_post_meta( $post->ID, '_celb_sl_rate', true ) !== '0' ) {
			echo '<input type="hidden" name="celb_sl_rate" value="1" />';
		}
		echo '<p class="cs-help">' . esc_html__( 'Create an enabled Rate Card for this talent to add a rate-card button here.', 'celb-mgmt' ) . '</p>';
	}
	celb_studio_card_close();
}

function celb_studio_cal_card( $post ) {
	celb_studio_card_open( __( 'Calendar sync', 'celb-mgmt' ), __( 'A private, always-live feed of this talent’s projects, appointments and confirmed bookings.', 'celb-mgmt' ), 'calendar', '', 'celb-cal' );
	if ( 'publish' !== $post->post_status ) {
		echo '<div class="cs-note">' . celb_studio_icon( 'alert', 16 ) . '<span>' . esc_html__( 'Publish this profile to generate its calendar link.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		celb_studio_card_close();
		return;
	}
	$https  = celb_cal_feed_url( $post->ID, 'https' );
	$webcal = celb_cal_feed_url( $post->ID, 'webcal' );
	$regen  = wp_nonce_url( admin_url( 'admin-post.php?action=celb_cal_regen&celeb=' . $post->ID ), 'celb_cal_regen_' . $post->ID );
	celb_studio_copy_field( $webcal, false, __( 'Apple / Outlook', 'celb-mgmt' ) );
	celb_studio_copy_field( $https, false, __( 'Google', 'celb-mgmt' ) );
	echo '<div class="cs-actions">';
	echo '<a class="cs-btn cs-btn--sm" href="' . esc_url( $webcal ) . '">' . celb_studio_icon( 'calendar', 15 ) . '<span>' . esc_html__( 'Add to Apple Calendar', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<a class="cs-btn cs-btn--sm cs-btn--ghost cs-btn--danger" href="' . esc_url( $regen ) . '" onclick="return confirm(\'' . esc_js( __( 'Generate a new link? The old link will stop working on all devices.', 'celb-mgmt' ) ) . '\');">' . celb_studio_icon( 'refresh', 15 ) . '<span>' . esc_html__( 'Reset link', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	echo '<p class="cs-help">' . esc_html__( 'Google Calendar: Other calendars → + → From URL → paste.', 'celb-mgmt' ) . '</p>';
	celb_studio_card_close();
}

function celb_studio_announce_card( $post ) {
	celb_studio_card_open( __( 'Announcement card', 'celb-mgmt' ), __( '1500×2000 PNG — “Name joins iLike Agency” with the role, nationality and a link to the profile.', 'celb-mgmt' ), 'sparkle', 'cs-card--canvas' );
	echo '<div class="cs-canvas-layout">';
	echo '<div class="cs-canvas-side">';
	celb_studio_field_open( __( 'Custom card image', 'celb-mgmt' ), '', __( 'Optional — otherwise the profile image is used.', 'celb-mgmt' ) );
	celb_studio_media( 'celb_card_img', get_post_meta( $post->ID, '_celb_card_img', true ), array( 'shape' => 'portrait', 'hint' => '3:4' ) );
	celb_studio_field_close();
	echo '</div>';
	$img = '';
	foreach ( array( (int) get_post_meta( $post->ID, '_celb_card_img', true ), (int) get_post_meta( $post->ID, '_celb_profile', true ) ) as $cand ) {
		if ( $cand && ! $img ) {
			$img = wp_get_attachment_image_url( $cand, 'full' );
		}
	}
	if ( ! $img ) {
		$img = get_the_post_thumbnail_url( $post->ID, 'full' );
	}
	echo '<div class="cs-canvas-main">';
	if ( $img ) {
		echo '<canvas class="celb-card-canvas cs-canvas" width="1500" height="2000"></canvas>';
		echo '<div class="cs-actions"><button type="button" class="cs-btn cs-btn--sm cs-btn--primary celb-card-download" disabled>' . celb_studio_icon( 'download', 15 ) . '<span>' . esc_html__( 'Download PNG', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost celb-card-refresh">' . celb_studio_icon( 'refresh', 15 ) . '<span>' . esc_html__( 'Refresh', 'celb-mgmt' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="cs-help">' . esc_html__( 'Save the profile after changing the image, name, nationality or role.', 'celb-mgmt' ) . '</p>';
	} else {
		echo '<div class="cs-canvas-empty">' . celb_studio_icon( 'image', 26 ) . '<p>' . esc_html__( 'Add a profile image (or a custom image) and save to generate the card.', 'celb-mgmt' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div>';
	celb_studio_card_close();
}

function celb_studio_login_card( $post ) {
	celb_studio_card_open( __( 'App login', 'celb-mgmt' ), __( 'A private login for the Talent app, where this talent sees only their own schedule, projects and contracts. No email needed — you send the details yourself.', 'celb-mgmt' ), 'key' );
	wp_nonce_field( 'celb_login_save', 'celb_login_nonce' );
	$uid = (int) get_post_meta( $post->ID, '_celb_user', true );
	$u   = $uid ? get_userdata( $uid ) : false;
	if ( $u ) {
		$pw = get_post_meta( $post->ID, '_celb_login_pw', true );
		echo '<div class="cs-status cs-status--ok">' . celb_studio_icon( 'check', 15 ) . '<span>' . esc_html__( 'Access is active', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		celb_studio_copy_field( celb_talent_url(), false, __( 'Talent app link', 'celb-mgmt' ) );
		celb_studio_copy_field( $u->user_login, false, __( 'Username', 'celb-mgmt' ) );
		if ( $pw ) {
			celb_studio_copy_field( $pw, false, __( 'Password', 'celb-mgmt' ) );
		} else {
			echo '<p class="cs-help">' . esc_html__( 'Password was set by the user. Reset it below to generate a fresh one.', 'celb-mgmt' ) . '</p>';
		}
		echo '<div class="cs-divider"></div><div class="cs-stack">';
		celb_studio_switch( 'celb_login_regen', false, __( 'Reset password on save', 'celb-mgmt' ), __( 'Generates a new password the next time you save.', 'celb-mgmt' ) );
		celb_studio_switch( 'celb_login_unlink', false, __( 'Remove access on save', 'celb-mgmt' ), __( 'Unlinks the user from this profile.', 'celb-mgmt' ), '1', 'class="cs-danger-toggle"' );
		echo '</div>';
	} else {
		if ( current_user_can( 'create_users' ) ) {
			celb_studio_switch( 'celb_login_gen', false, __( 'Generate login & password on save', 'celb-mgmt' ), __( 'Creates a “celebrity” user with a strong password.', 'celb-mgmt' ) );
		} else {
			echo '<div class="cs-note">' . celb_studio_icon( 'alert', 16 ) . '<span>' . esc_html__( 'You need user-creation rights to generate a login.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		celb_studio_field_open( __( 'Or link an existing user', 'celb-mgmt' ), 'celb_login_existing' );
		echo '<select class="cs-input" id="celb_login_existing" name="celb_login_existing"><option value="0">' . esc_html__( 'None', 'celb-mgmt' ) . '</option>';
		foreach ( get_users( array( 'number' => 200 ) ) as $usr ) {
			if ( celb_user_celeb_id( $usr->ID ) ) {
				continue;
			}
			echo '<option value="' . (int) $usr->ID . '">' . esc_html( $usr->display_name . ' (' . $usr->user_login . ')' ) . '</option>';
		}
		echo '</select>';
		celb_studio_field_close();
	}
	celb_studio_card_close();
}

/* Sidebar: live snapshot + completeness ----------------------------------- */

function celb_studio_snapshot_box( $post ) {
	$id      = $post->ID;
	$photo   = celb_studio_photo_url( $id, 'medium' );
	$checks  = celb_studio_checks( $id );
	$score   = celb_studio_score( $checks );
	$role    = get_post_meta( $id, '_celb_role', true );
	$nat     = get_post_meta( $id, '_celb_nationality', true );
	$status  = get_post_status( $id );
	$labels  = array( 'publish' => __( 'Published', 'celb-mgmt' ), 'draft' => __( 'Draft', 'celb-mgmt' ), 'pending' => __( 'Pending review', 'celb-mgmt' ), 'future' => __( 'Scheduled', 'celb-mgmt' ), 'private' => __( 'Private', 'celb-mgmt' ), 'auto-draft' => __( 'New', 'celb-mgmt' ) );
	echo '<div class="cs-snap" data-cs-snapshot>';
	echo '<div class="cs-snap-photo" data-cs-snap-photo' . ( $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '><span class="cs-snap-mono">' . esc_html( celb_monogram( get_the_title( $id ) ) ) . '</span></div>';
	echo '<div class="cs-snap-name" data-cs-snap-name>' . esc_html( get_the_title( $id ) ? get_the_title( $id ) : __( 'New talent', 'celb-mgmt' ) ) . '</div>';
	echo '<div class="cs-snap-sub" data-cs-snap-sub>' . esc_html( implode( ' / ', array_filter( array( $role, $nat ) ) ) ) . '</div>';
	echo '<div class="cs-badges">';
	echo '<span class="cs-badge cs-badge--' . esc_attr( $status ) . '">' . esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status ) ) . '</span>';
	echo '<span class="cs-badge cs-badge--lead" data-cs-badge="lead"' . ( get_post_meta( $id, '_celb_lead', true ) ? '' : ' hidden' ) . '>' . celb_studio_icon( 'star', 12 ) . esc_html__( 'Lead', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cs-badge cs-badge--locked" data-cs-badge="locked"' . ( get_post_meta( $id, '_celb_locked', true ) ? '' : ' hidden' ) . '>' . celb_studio_icon( 'lock', 12 ) . esc_html__( 'Private profile', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( get_post_meta( $id, '_celb_submission', true ) ) {
		echo '<span class="cs-badge cs-badge--sub">' . esc_html__( 'Self-submitted', 'celb-mgmt' ) . '</span>';
	}
	echo '</div>';

	echo '<div class="cs-score" data-cs-score>';
	echo '<div class="cs-score-head"><span>' . esc_html__( 'Profile strength', 'celb-mgmt' ) . '</span><b data-cs-score-num>' . (int) $score . '%</b></div>';
	echo '<div class="cs-meter"><span data-cs-score-bar style="width:' . (int) $score . '%"></span></div>';
	echo '<ul class="cs-checks">';
	foreach ( $checks as $key => $c ) {
		echo '<li class="' . ( $c[1] ? 'is-done' : '' ) . '" data-cs-check="' . esc_attr( $key ) . '"><button type="button" data-cs-goto="' . esc_attr( $c[2] ) . '"><span class="cs-check-dot">' . celb_studio_icon( 'check', 11 ) . '</span>' . esc_html( $c[0] ) . '</button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul></div>';

	if ( 'publish' === $status || 'private' === $status ) {
		echo '<div class="cs-snap-links">';
		echo '<a class="cs-btn cs-btn--sm cs-btn--ghost" href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">' . celb_studio_icon( 'eye', 15 ) . '<span>' . esc_html__( 'View profile', 'celb-mgmt' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'publish' === $status ) {
			echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost" data-cs-copy="' . esc_attr( celb_smartlink_url( $id ) ) . '">' . celb_studio_icon( 'link', 15 ) . '<span>' . esc_html__( 'Copy smart link', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}
	echo '</div>';
}

/* =========================================================================
 * 4. NEWSROOM EDITOR
 * ====================================================================== */

/* The article body is edited inside the workspace (English / Arabic tabs),
   so the core editor box is switched off on the edit screen only. That also
   moves the screen to the classic editor, where the workspace can render. */
function celb_studio_news_edit_screen() {
	$type = '';
	if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$type = get_post_type( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( 'celeb_news' === $type ) {
		remove_post_type_support( 'celeb_news', 'editor' );
	}
}
add_action( 'load-post.php', 'celb_studio_news_edit_screen' );
add_action( 'load-post-new.php', 'celb_studio_news_edit_screen' );

add_action( 'add_meta_boxes_celeb_news', function () {
	add_meta_box( 'news_details', __( 'Article', 'celb-mgmt' ), 'celb_studio_news_details_box', 'celeb_news', 'side', 'high' );
}, 20 );

function celb_studio_news_ar_dir( $html ) {
	return str_replace( '<textarea', '<textarea dir="rtl"', $html );
}

function celb_studio_news_workspace( $post ) {
	$id      = $post->ID;
	$gallery = get_post_meta( $id, '_news_gallery', true );
	$gallery = is_array( $gallery ) ? array_filter( $gallery ) : array();
	$links   = get_post_meta( $id, '_news_links', true );
	$links   = is_array( $links ) ? $links : array();
	$tabs = array(
		'story' => array( 'label' => __( 'Story', 'celb-mgmt' ), 'icon' => 'pen' ),
		'media' => array( 'label' => __( 'Media', 'celb-mgmt' ), 'icon' => 'image', 'count' => count( $gallery ) ),
		'links' => array( 'label' => __( 'Related links', 'celb-mgmt' ), 'icon' => 'link', 'count' => count( $links ) ),
		'card'  => array( 'label' => __( 'Social card', 'celb-mgmt' ), 'icon' => 'sparkle' ),
	);
	$default  = 'story';
	$title_ar = get_post_meta( $id, '_news_title_ar', true );
	$body_ar  = get_post_meta( $id, '_news_body_ar', true );

	echo '<div class="cs-app cs-app--news" data-cs-app="news" data-post="' . (int) $id . '">';
	wp_nonce_field( 'celb_news_save', 'celb_news_nonce' );
	celb_studio_tabs( $tabs, $default );

	/* ---- Story: English / Arabic ---- */
	celb_studio_panel_open( 'story', $default );
	echo '<section class="cs-card cs-card--editor cs-story">';
	echo '<header class="cs-story-head">';
	echo '<div class="cs-seg" role="tablist" aria-label="' . esc_attr__( 'Language', 'celb-mgmt' ) . '">';
	echo '<button type="button" class="cs-seg-btn is-active" data-cs-lang="en"><span class="cs-lang-code">EN</span>' . esc_html__( 'English', 'celb-mgmt' ) . '</button>';
	echo '<button type="button" class="cs-seg-btn" data-cs-lang="ar"><span class="cs-lang-code">AR</span>العربية<span class="cs-seg-dot' . ( ( $title_ar || $body_ar ) ? ' is-on' : '' ) . '" data-cs-ar-dot></span></button>';
	echo '</div>';
	echo '<p class="cs-hint">' . esc_html__( 'English is the main version. Fill Arabic to add an in-page language switch on the article.', 'celb-mgmt' ) . '</p>';
	echo '</header>';

	echo '<div class="cs-lang-pane is-active" data-cs-lang-pane="en">';
	wp_editor( $post->post_content, 'content', array(
		'textarea_name' => 'content',
		'media_buttons' => true,
		'editor_height' => 420,
		'drag_drop_upload' => true,
	) );
	echo '</div>';

	echo '<div class="cs-lang-pane" data-cs-lang-pane="ar" dir="rtl">';
	echo '<input type="text" class="cs-input cs-input--headline" name="news_title_ar" dir="rtl" value="' . esc_attr( $title_ar ) . '" placeholder="العنوان بالعربية" aria-label="' . esc_attr__( 'Arabic title', 'celb-mgmt' ) . '" data-cs-ar />';
	add_filter( 'the_editor', 'celb_studio_news_ar_dir' );
	wp_editor( $body_ar, 'news_body_ar', array(
		'textarea_name' => 'news_body_ar',
		'editor_height' => 380,
		'media_buttons' => false,
		'teeny'         => false,
		'quicktags'     => true,
		'tinymce'       => array(
			'directionality' => 'rtl',
			'plugins'        => 'lists,link,paste,directionality,charmap,wordpress,wplink,wptextpattern',
			'toolbar1'       => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignright,aligncenter,alignleft,link,unlink,ltr,rtl,removeformat,undo,redo',
			'toolbar2'       => '',
			'block_formats'  => 'Paragraph=p;Heading 2=h2;Heading 3=h3',
		),
	) );
	remove_filter( 'the_editor', 'celb_studio_news_ar_dir' );
	echo '</div>';
	echo '</section>';
	celb_studio_panel_close();

	/* ---- Media ---- */
	celb_studio_panel_open( 'media', $default );
	celb_studio_card_open( __( 'Hero images', 'celb-mgmt' ), __( 'Top of the article page and pop-up, with the gradient + title overlay. Mobile falls back to desktop; both fall back to the featured image (which is also the list thumbnail).', 'celb-mgmt' ), 'layout' );
	echo '<div class="cs-hero-pair">';
	echo '<div class="cs-hero-slot cs-hero-slot--wide"><span class="cs-mini-label">' . celb_studio_icon( 'layout', 14 ) . esc_html__( 'Desktop', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_media( 'news_header', get_post_meta( $id, '_news_header', true ), array( 'shape' => 'wide', 'hint' => __( 'Landscape · 16:9', 'celb-mgmt' ) ) );
	echo '</div><div class="cs-hero-slot cs-hero-slot--tall"><span class="cs-mini-label">' . celb_studio_icon( 'phone', 14 ) . esc_html__( 'Mobile', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_media( 'news_header_mobile', get_post_meta( $id, '_news_header_mobile', true ), array( 'shape' => 'tall', 'hint' => __( 'Portrait · 9:16', 'celb-mgmt' ) ) );
	echo '</div></div>';
	celb_studio_card_close();
	celb_studio_card_open( __( 'Media gallery', 'celb-mgmt' ), __( 'Shown in the article gallery and bundled into the “Download all media” zip. Drag to reorder.', 'celb-mgmt' ), 'image' );
	celb_studio_gallery( 'news_gallery', $gallery );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Links ---- */
	celb_studio_panel_open( 'links', $default );
	celb_studio_card_open( __( 'Related links', 'celb-mgmt' ), __( 'YouTube, social posts, external coverage… Each opens in a new tab.', 'celb-mgmt' ), 'link' );
	celb_studio_repeater( 'links', $links, 'celb_studio_link_row', array(
		'add'   => __( 'Add link', 'celb-mgmt' ),
		'cols'  => array( __( 'Label', 'celb-mgmt' ), __( 'URL', 'celb-mgmt' ) ),
		'icon'  => 'link',
		'empty' => __( 'No related links yet.', 'celb-mgmt' ),
	) );
	celb_studio_card_close();
	celb_studio_panel_close();

	/* ---- Social card ---- */
	celb_studio_panel_open( 'card', $default );
	celb_studio_card_open( __( 'Social media card', 'celb-mgmt' ), __( 'Portrait 3:4 card: photo, frame, logo, headline, date / location and the talent’s name. Aligns right automatically for Arabic headlines.', 'celb-mgmt' ), 'sparkle', 'cs-card--canvas' );
	echo '<div class="cs-canvas-layout"><div class="cs-canvas-side">';
	celb_studio_field_open( __( 'Custom card image', 'celb-mgmt' ), '', __( 'Optional — otherwise the featured or hero image is used.', 'celb-mgmt' ) );
	celb_studio_media( 'news_card_img', get_post_meta( $id, '_news_card_img', true ), array( 'shape' => 'portrait', 'hint' => '3:4' ) );
	celb_studio_field_close();
	echo '</div><div class="cs-canvas-main">';
	$custom = (int) get_post_meta( $id, '_news_card_img', true );
	$img    = $custom ? wp_get_attachment_image_url( $custom, 'medium' ) : get_the_post_thumbnail_url( $id, 'full' );
	if ( ! $img ) {
		$img = celb_news_header_img( $id, 'full' );
	}
	if ( $img ) {
		echo '<canvas class="celb-card-canvas cs-canvas" width="1080" height="1440"></canvas>';
		echo '<div class="cs-actions"><button type="button" class="cs-btn cs-btn--sm cs-btn--primary celb-card-download" disabled>' . celb_studio_icon( 'download', 15 ) . '<span>' . esc_html__( 'Download PNG', 'celb-mgmt' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost celb-card-refresh">' . celb_studio_icon( 'refresh', 15 ) . '<span>' . esc_html__( 'Refresh', 'celb-mgmt' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="cs-help">' . esc_html__( 'Save the article after changing the image, headline, celebrity, location or date.', 'celb-mgmt' ) . '</p>';
	} else {
		echo '<div class="cs-canvas-empty">' . celb_studio_icon( 'image', 26 ) . '<p>' . esc_html__( 'Add a hero, featured or custom image and save to generate the card.', 'celb-mgmt' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div>';
	celb_studio_card_close();
	celb_studio_panel_close();

	echo '</div>';
}

function celb_studio_link_row( $i, $row ) {
	$row = wp_parse_args( is_array( $row ) ? $row : array(), array( 'label' => '', 'url' => '' ) );
	$n   = 'news_links[' . $i . ']';
	echo '<div class="cs-row cs-row--links">';
	echo celb_studio_row_handle(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" class="cs-input" name="' . esc_attr( $n ) . '[label]" value="' . esc_attr( $row['label'] ) . '" placeholder="' . esc_attr__( 'Label (e.g. YouTube interview)', 'celb-mgmt' ) . '" />';
	$platform = $row['url'] ? celb_video_platform( $row['url'] ) : '';
	echo '<div class="cs-vurl"><span class="cs-platform" data-cs-platform>' . esc_html( $platform ? celb_platform_label( $platform ) : __( 'Link', 'celb-mgmt' ) ) . '</span>';
	echo '<input type="url" class="cs-input" name="' . esc_attr( $n ) . '[url]" value="' . esc_attr( $row['url'] ) . '" placeholder="https://" data-cs-video-url /></div>';
	echo celb_studio_row_remove(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

function celb_studio_news_details_box( $post ) {
	$cel  = (int) get_post_meta( $post->ID, '_news_celebrity', true );
	$loc  = get_post_meta( $post->ID, '_news_location', true );
	$cels = get_posts( array(
		'post_type'   => CELB_CPT,
		'numberposts' => -1,
		'post_status' => array( 'publish', 'draft' ),
		'orderby'     => 'title',
		'order'       => 'ASC',
	) );
	$photo = $cel ? celb_studio_photo_url( $cel, 'thumbnail' ) : '';
	echo '<div class="cs-article">';
	echo '<div class="cs-who" data-cs-who>';
	echo '<span class="cs-who-photo" data-cs-who-photo' . ( $photo ? ' style="background-image:url(' . esc_url( $photo ) . ')"' : '' ) . '>' . celb_studio_icon( 'user', 18 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cs-who-main"><label class="cs-label" for="news_celebrity">' . esc_html__( 'Celebrity', 'celb-mgmt' ) . '</label>';
	echo '<select id="news_celebrity" name="news_celebrity" class="cs-input" data-cs-who-select><option value="0" data-photo="">' . esc_html__( '— Select —', 'celb-mgmt' ) . '</option>';
	foreach ( $cels as $c ) {
		echo '<option value="' . esc_attr( $c->ID ) . '" data-photo="' . esc_attr( celb_studio_photo_url( $c->ID, 'thumbnail' ) ) . '" ' . selected( $cel, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
	}
	echo '</select></div></div>';
	celb_studio_field_open( __( 'Location', 'celb-mgmt' ), 'news_location' );
	echo '<div class="cs-input-ic">' . celb_studio_icon( 'pin', 16 ) . '<input type="text" id="news_location" name="news_location" value="' . esc_attr( $loc ) . '" class="cs-input" placeholder="Cairo, Egypt" /></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	celb_studio_field_close();

	$checks = array(
		'headline' => array( __( 'Headline', 'celb-mgmt' ), '' !== $post->post_title, 'story' ),
		'body'     => array( __( 'English story', 'celb-mgmt' ), '' !== trim( wp_strip_all_tags( $post->post_content ) ), 'story' ),
		'celeb'    => array( __( 'Celebrity', 'celb-mgmt' ), $cel > 0, '' ),
		'hero'     => array( __( 'Hero or featured image', 'celb-mgmt' ), (bool) ( get_post_meta( $post->ID, '_news_header', true ) || has_post_thumbnail( $post->ID ) ), 'media' ),
		'arabic'   => array( __( 'Arabic version', 'celb-mgmt' ), '' !== (string) get_post_meta( $post->ID, '_news_title_ar', true ), 'story' ),
	);
	$score = celb_studio_score( $checks );
	echo '<div class="cs-score" data-cs-score>';
	echo '<div class="cs-score-head"><span>' . esc_html__( 'Ready to publish', 'celb-mgmt' ) . '</span><b data-cs-score-num>' . (int) $score . '%</b></div>';
	echo '<div class="cs-meter"><span data-cs-score-bar style="width:' . (int) $score . '%"></span></div><ul class="cs-checks">';
	foreach ( $checks as $key => $c ) {
		echo '<li class="' . ( $c[1] ? 'is-done' : '' ) . '" data-cs-check="' . esc_attr( $key ) . '"><button type="button"' . ( $c[2] ? ' data-cs-goto="' . esc_attr( $c[2] ) . '"' : '' ) . ( 'arabic' === $key ? ' data-cs-goto-lang="ar"' : '' ) . '><span class="cs-check-dot">' . celb_studio_icon( 'check', 11 ) . '</span>' . esc_html( $c[0] ) . '</button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul></div></div>';
}

/* =========================================================================
 * 5. CELEBRITIES LIST
 * ====================================================================== */

/* Roster-wide numbers for the header (one query, meta cache primed). */
function celb_studio_roster_stats() {
	$ids = get_posts( array(
		'post_type'      => CELB_CPT,
		'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	update_meta_cache( 'post', $ids );
	$s = array( 'total' => count( $ids ), 'publish' => 0, 'draft' => 0, 'lead' => 0, 'locked' => 0, 'submitted' => 0, 'incomplete' => array() );
	foreach ( $ids as $id ) {
		$st = get_post_status( $id );
		if ( 'publish' === $st ) {
			$s['publish']++;
		} elseif ( in_array( $st, array( 'draft', 'pending' ), true ) ) {
			$s['draft']++;
		}
		if ( get_post_meta( $id, '_celb_lead', true ) ) {
			$s['lead']++;
		}
		if ( get_post_meta( $id, '_celb_locked', true ) ) {
			$s['locked']++;
		}
		if ( get_post_meta( $id, '_celb_submission', true ) ) {
			$s['submitted']++;
		}
		if ( celb_studio_score( celb_studio_checks( $id ) ) < 100 ) {
			$s['incomplete'][] = $id;
		}
	}
	return $s;
}

/* Header: title, stats, add button, view toggle. */
add_action( 'all_admin_notices', function () {
	if ( 'list' !== celb_studio_screen() ) {
		return;
	}
	$s     = celb_studio_roster_stats();
	$base  = admin_url( 'edit.php?post_type=' . CELB_CPT );
	$view  = isset( $_GET['celb_view'] ) ? sanitize_key( wp_unslash( $_GET['celb_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$pstat = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$tiles = array(
		array( __( 'Talent', 'celb-mgmt' ), $s['total'], $base, 'users', '' === $view && '' === $pstat ),
		array( __( 'Published', 'celb-mgmt' ), $s['publish'], add_query_arg( 'post_status', 'publish', $base ), 'eye', 'publish' === $pstat ),
		array( __( 'Drafts', 'celb-mgmt' ), $s['draft'], add_query_arg( 'post_status', 'draft', $base ), 'pen', 'draft' === $pstat ),
		array( __( 'Lead', 'celb-mgmt' ), $s['lead'], add_query_arg( 'celb_view', 'lead', $base ), 'star', 'lead' === $view ),
		array( __( 'Private', 'celb-mgmt' ), $s['locked'], add_query_arg( 'celb_view', 'private', $base ), 'lock', 'private' === $view ),
		array( __( 'Needs work', 'celb-mgmt' ), count( $s['incomplete'] ), add_query_arg( 'celb_view', 'incomplete', $base ), 'alert', 'incomplete' === $view ),
	);
	?>
	<div class="cs-hero">
		<div class="cs-hero-top">
			<div>
				<p class="cs-eyebrow"><?php esc_html_e( 'CELB MGMT', 'celb-mgmt' ); ?></p>
				<h1 class="cs-hero-title"><?php esc_html_e( 'Celebrities', 'celb-mgmt' ); ?></h1>
				<p class="cs-hero-sub"><?php esc_html_e( 'Your roster at a glance — open a profile to edit it, or start a new one.', 'celb-mgmt' ); ?></p>
			</div>
			<div class="cs-hero-actions">
				<div class="cs-seg cs-viewtoggle" role="group" aria-label="<?php esc_attr_e( 'Layout', 'celb-mgmt' ); ?>">
					<button type="button" class="cs-seg-btn" data-cs-view="grid"><?php echo celb_studio_icon( 'grid', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'Cards', 'celb-mgmt' ); ?></span></button>
					<button type="button" class="cs-seg-btn" data-cs-view="list"><?php echo celb_studio_icon( 'list', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'List', 'celb-mgmt' ); ?></span></button>
				</div>
				<a class="cs-btn cs-btn--primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CELB_CPT ) ); ?>"><?php echo celb_studio_icon( 'plus', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'Add celebrity', 'celb-mgmt' ); ?></span></a>
			</div>
		</div>
		<div class="cs-tiles">
			<?php foreach ( $tiles as $t ) : ?>
				<a class="cs-tile<?php echo $t[4] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $t[2] ); ?>">
					<span class="cs-tile-ic"><?php echo celb_studio_icon( $t[3], 17 ); // phpcs:ignore ?></span>
					<span class="cs-tile-num"><?php echo (int) $t[1]; ?></span>
					<span class="cs-tile-label"><?php echo esc_html( $t[0] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}, 1 );

/* Apply the saved Cards/List choice before paint (no flash). */
add_action( 'admin_head', function () {
	if ( 'list' !== celb_studio_screen() ) {
		return;
	}
	echo "<script>try{if(localStorage.getItem('celbRosterView')!=='list'){document.documentElement.classList.add('cs-roster-grid');}}catch(e){document.documentElement.classList.add('cs-roster-grid');}</script>\n";
} );

/* Columns. */
add_filter( 'manage_' . CELB_CPT . '_posts_columns', function ( $cols ) {
	$out = array();
	if ( isset( $cols['cb'] ) ) {
		$out['cb'] = $cols['cb'];
	}
	$out['celb_thumb']    = '<span class="screen-reader-text">' . esc_html__( 'Photo', 'celb-mgmt' ) . '</span>';
	$out['title']         = __( 'Talent', 'celb-mgmt' );
	$out['celb_cats']     = __( 'Categories', 'celb-mgmt' );
	$out['celb_flags']    = __( 'Status', 'celb-mgmt' );
	$out['celb_strength'] = __( 'Profile', 'celb-mgmt' );
	$out['celb_link']     = __( 'Smart link', 'celb-mgmt' );
	foreach ( $cols as $k => $v ) {
		if ( ! isset( $out[ $k ] ) && ! in_array( $k, array( 'date' ), true ) ) {
			$out[ $k ] = $v; // keep columns other plugins add
		}
	}
	$out['date'] = __( 'Updated', 'celb-mgmt' );
	return $out;
}, 100 );

add_action( 'manage_' . CELB_CPT . '_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'celb_thumb':
			$url = celb_studio_photo_url( $post_id, 'medium' );
			echo '<a class="cs-thumb" href="' . esc_url( get_edit_post_link( $post_id ) ) . '" tabindex="-1" aria-hidden="true"' . ( $url ? ' style="background-image:url(' . esc_url( $url ) . ')"' : '' ) . '>';
			if ( ! $url ) {
				echo '<span>' . esc_html( celb_monogram( get_the_title( $post_id ) ) ) . '</span>';
			}
			echo '</a>';
			$sub = implode( ' / ', array_filter( array( get_post_meta( $post_id, '_celb_role', true ), get_post_meta( $post_id, '_celb_nationality', true ) ) ) );
			echo '<span class="cs-row-sub" data-cs-sub>' . esc_html( $sub ) . '</span>';
			break;
		case 'celb_cats':
			echo '<span class="cs-cats">';
			$stored = get_post_meta( $post_id, '_celb_category', true );
			foreach ( celb_roster_categories( $post_id ) as $c ) {
				echo '<span class="cs-cat' . ( empty( $stored ) ? ' is-auto' : '' ) . '"' . ( empty( $stored ) ? ' title="' . esc_attr__( 'Guessed from role', 'celb-mgmt' ) . '"' : '' ) . '>' . esc_html( celb_roster_cat_label( $c ) ) . '</span>';
			}
			echo '</span>';
			break;
		case 'celb_flags':
			$st     = get_post_status( $post_id );
			$labels = array( 'publish' => __( 'Published', 'celb-mgmt' ), 'draft' => __( 'Draft', 'celb-mgmt' ), 'pending' => __( 'Pending', 'celb-mgmt' ), 'future' => __( 'Scheduled', 'celb-mgmt' ), 'private' => __( 'Private', 'celb-mgmt' ), 'trash' => __( 'Trash', 'celb-mgmt' ) );
			echo '<span class="cs-badges">';
			echo '<span class="cs-badge cs-badge--' . esc_attr( $st ) . '">' . esc_html( isset( $labels[ $st ] ) ? $labels[ $st ] : $st ) . '</span>';
			if ( get_post_meta( $post_id, '_celb_lead', true ) ) {
				echo '<span class="cs-badge cs-badge--lead">' . celb_studio_icon( 'star', 12 ) . esc_html__( 'Lead', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( get_post_meta( $post_id, '_celb_locked', true ) ) {
				echo '<span class="cs-badge cs-badge--locked">' . celb_studio_icon( 'lock', 12 ) . esc_html__( 'Private', 'celb-mgmt' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( get_post_meta( $post_id, '_celb_submission', true ) ) {
				echo '<span class="cs-badge cs-badge--sub">' . esc_html__( 'Self-submitted', 'celb-mgmt' ) . '</span>';
			}
			echo '</span>';
			break;
		case 'celb_strength':
			$checks  = celb_studio_checks( $post_id );
			$score   = celb_studio_score( $checks );
			$missing = array();
			foreach ( $checks as $c ) {
				if ( ! $c[1] ) {
					$missing[] = $c[0];
				}
			}
			$tone = $score >= 100 ? 'full' : ( $score >= 70 ? 'good' : 'low' );
			echo '<span class="cs-strength cs-strength--' . esc_attr( $tone ) . '" title="' . esc_attr( $missing ? __( 'Missing:', 'celb-mgmt' ) . ' ' . implode( ', ', $missing ) : __( 'Complete', 'celb-mgmt' ) ) . '">';
			echo '<span class="cs-meter"><span style="width:' . (int) $score . '%"></span></span><b>' . (int) $score . '%</b></span>';
			break;
		case 'celb_link':
			if ( 'publish' === get_post_status( $post_id ) ) {
				$url = celb_smartlink_url( $post_id );
				echo '<button type="button" class="cs-linkchip" data-cs-copy="' . esc_attr( $url ) . '" title="' . esc_attr__( 'Copy smart link', 'celb-mgmt' ) . '">' . celb_studio_icon( 'link', 14 ) . '<span>/' . esc_html( trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) ) . '/</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="cs-muted">—</span>';
			}
			break;
	}
}, 10, 2 );

/* Drop views other plugins add to our lists (e.g. Yoast "Cornerstone content"). */
add_action( 'current_screen', function ( $screen ) {
	if ( 'edit' !== $screen->base || ! celb_studio_screen() ) {
		return;
	}
	add_filter( 'views_' . $screen->id, function ( $views ) {
		foreach ( array_keys( $views ) as $k ) {
			if ( false !== stripos( $k, 'cornerstone' ) || false !== stripos( (string) $views[ $k ], 'cornerstone' ) ) {
				unset( $views[ $k ] );
			}
		}
		return $views;
	}, 999 );
} );

/* Status badges replace the " — Draft" post states on the roster list. */
add_filter( 'display_post_states', function ( $states, $post ) {
	return ( CELB_CPT === $post->post_type && 'list' === celb_studio_screen() ) ? array() : $states;
}, 99, 2 );

/* Quick "View smart link" row action. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( CELB_CPT === $post->post_type && 'publish' === $post->post_status ) {
		$actions['celb_smartlink'] = '<a href="' . esc_url( celb_smartlink_url( $post->ID ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Smart link', 'celb-mgmt' ) . '</a>';
	}
	return $actions;
}, 10, 2 );

/* Extra views: Lead / Private / Self-submitted / Needs work. */
add_filter( 'views_edit-' . CELB_CPT, function ( $views ) {
	$s    = celb_studio_roster_stats();
	$base = admin_url( 'edit.php?post_type=' . CELB_CPT );
	$cur  = isset( $_GET['celb_view'] ) ? sanitize_key( wp_unslash( $_GET['celb_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$add  = array(
		'lead'       => array( __( 'Lead', 'celb-mgmt' ), $s['lead'] ),
		'private'    => array( __( 'Private profile', 'celb-mgmt' ), $s['locked'] ),
		'submitted'  => array( __( 'Self-submitted', 'celb-mgmt' ), $s['submitted'] ),
		'incomplete' => array( __( 'Needs work', 'celb-mgmt' ), count( $s['incomplete'] ) ),
	);
	if ( $cur ) {
		foreach ( $views as $k => $v ) {
			$views[ $k ] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $v );
		}
	}
	foreach ( $add as $k => $v ) {
		if ( ! $v[1] ) {
			continue;
		}
		$views[ 'celb_' . $k ] = '<a href="' . esc_url( add_query_arg( 'celb_view', $k, $base ) ) . '"' . ( $cur === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . (int) $v[1] . ')</span></a>';
	}
	return $views;
} );

/* Category filter dropdown. */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( CELB_CPT !== $post_type ) {
		return;
	}
	$cur = isset( $_GET['celb_cat'] ) ? sanitize_key( wp_unslash( $_GET['celb_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<label class="screen-reader-text" for="celb_cat">' . esc_html__( 'Filter by category', 'celb-mgmt' ) . '</label>';
	echo '<select name="celb_cat" id="celb_cat"><option value="">' . esc_html__( 'All categories', 'celb-mgmt' ) . '</option>';
	foreach ( celb_roster_cat_defs() as $k => $lbl ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cur, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select>';
	if ( isset( $_GET['celb_view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<input type="hidden" name="celb_view" value="' . esc_attr( sanitize_key( wp_unslash( $_GET['celb_view'] ) ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification
	}
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || CELB_CPT !== $q->get( 'post_type' ) ) {
		return;
	}
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}
	$view = isset( $_GET['celb_view'] ) ? sanitize_key( wp_unslash( $_GET['celb_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$cat  = isset( $_GET['celb_cat'] ) ? sanitize_key( wp_unslash( $_GET['celb_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$meta = (array) $q->get( 'meta_query' );
	if ( 'lead' === $view ) {
		$meta[] = array( 'key' => '_celb_lead', 'value' => '1' );
	} elseif ( 'private' === $view ) {
		$meta[] = array( 'key' => '_celb_locked', 'value' => '1' );
	} elseif ( 'submitted' === $view ) {
		$meta[] = array( 'key' => '_celb_submission', 'value' => '', 'compare' => '!=' );
	}
	if ( $meta ) {
		$q->set( 'meta_query', $meta );
	}
	$in = null;
	if ( 'incomplete' === $view ) {
		$s  = celb_studio_roster_stats();
		$in = $s['incomplete'];
	}
	if ( $cat && array_key_exists( $cat, celb_roster_cat_defs() ) ) {
		$all  = get_posts( array( 'post_type' => CELB_CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		$hits = array();
		foreach ( $all as $id ) {
			if ( in_array( $cat, celb_roster_categories( $id ), true ) ) {
				$hits[] = $id;
			}
		}
		$in = null === $in ? $hits : array_values( array_intersect( $in, $hits ) );
	}
	if ( null !== $in ) {
		$q->set( 'post__in', $in ? $in : array( 0 ) );
	}
} );

/* =========================================================================
 * 6. SETTINGS
 * ====================================================================== */

function celb_studio_pw_row( $i, $pw ) {
	$pw = wp_parse_args( is_array( $pw ) ? $pw : array(), array( 'label' => '', 'pass' => '' ) );
	echo '<div class="cs-row cs-row--pw">';
	echo '<input type="text" class="cs-input" name="celb_settings[portal_passwords][' . esc_attr( $i ) . '][label]" value="' . esc_attr( $pw['label'] ) . '" placeholder="' . esc_attr__( 'Label (e.g. talent or campaign)', 'celb-mgmt' ) . '" />';
	echo '<div class="cs-pw"><input type="text" class="cs-input cs-mono" name="celb_settings[portal_passwords][' . esc_attr( $i ) . '][pass]" value="' . esc_attr( $pw['pass'] ) . '" placeholder="' . esc_attr__( 'Password', 'celb-mgmt' ) . '" data-cs-pw autocomplete="off" />';
	echo '<button type="button" class="cs-btn cs-btn--sm cs-btn--ghost" data-cs-pw-gen>' . celb_studio_icon( 'refresh', 14 ) . '<span>' . esc_html__( 'Generate', 'celb-mgmt' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo celb_studio_row_remove(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

/* Settings field helpers (all names live under celb_settings[...]). */
function celb_studio_s_text( $key, $s, $label, $help = '', $args = array() ) {
	$a  = wp_parse_args( $args, array( 'type' => 'text', 'placeholder' => '', 'class' => '', 'attr' => '', 'prefix' => '', 'suffix' => '' ) );
	$id = 'celb_s_' . $key;
	celb_studio_field_open( $label, $id, '', $a['class'] );
	$input = '<input type="' . esc_attr( $a['type'] ) . '" class="cs-input" id="' . esc_attr( $id ) . '" name="celb_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( isset( $s[ $key ] ) ? $s[ $key ] : '' ) . '" placeholder="' . esc_attr( $a['placeholder'] ) . '" ' . $a['attr'] . ' />';
	if ( $a['prefix'] || $a['suffix'] ) {
		echo '<div class="cs-affix">' . ( $a['prefix'] ? '<span class="cs-affix-pre">' . esc_html( $a['prefix'] ) . '</span>' : '' ) . $input . ( $a['suffix'] ? '<span class="cs-affix-suf">' . esc_html( $a['suffix'] ) . '</span>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo $input; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	celb_studio_field_close( $help );
}
function celb_studio_s_textarea( $key, $s, $label, $help = '', $rows = 3, $placeholder = '' ) {
	$id = 'celb_s_' . $key;
	celb_studio_field_open( $label, $id );
	echo '<textarea class="cs-input" id="' . esc_attr( $id ) . '" name="celb_settings[' . esc_attr( $key ) . ']" rows="' . (int) $rows . '" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( isset( $s[ $key ] ) ? $s[ $key ] : '' ) . '</textarea>';
	celb_studio_field_close( $help );
}
function celb_studio_s_switch( $key, $s, $label, $desc = '' ) {
	celb_studio_switch( 'celb_settings[' . $key . ']', ! empty( $s[ $key ] ), $label, $desc );
}
function celb_studio_s_color( $key, $value, $label, $help = '', $default = '' ) {
	$id = 'celb_s_' . $key;
	celb_studio_field_open( $label, $id, '', 'cs-field--color' );
	echo '<input type="text" class="cs-color" id="' . esc_attr( $id ) . '" name="celb_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" data-default-color="' . esc_attr( $default ) . '" />';
	celb_studio_field_close( $help );
}

/* Navigation model: group → sections. */
function celb_studio_settings_nav() {
	return array(
		__( 'Brand & look', 'celb-mgmt' ) => array(
			'brand'      => array( __( 'Brand', 'celb-mgmt' ), 'sparkle', 'logo brand' ),
			'appearance' => array( __( 'Colours & fonts', 'celb-mgmt' ), 'palette', 'accent colour color theme font typography background text dark light' ),
			'layout'     => array( __( 'Grid & carousel', 'celb-mgmt' ), 'grid', 'columns carousel items layout' ),
		),
		__( 'Roster & profiles', 'celb-mgmt' ) => array(
			'roster'  => array( __( 'Roster header', 'celb-mgmt' ), 'users', 'eyebrow title intro roster view all button carousel' ),
			'profile' => array( __( 'Profile page', 'celb-mgmt' ), 'user', 'hero spacing cta call to action button lets talk' ),
			'stars'   => array( __( 'Our Stars page', 'celb-mgmt' ), 'star', 'all in one our stars mobile standalone' ),
		),
		__( 'Portals', 'celb-mgmt' ) => array(
			'portal' => array( __( 'Submission portal', 'celb-mgmt' ), 'door', 'submission portal passwords self onboarding' ),
			'pdata'  => array( __( 'Personal data', 'celb-mgmt' ), 'lock', 'personal data emergency contacts password' ),
			'pages'  => array( __( 'Page addresses', 'celb-mgmt' ), 'link', 'slug url address pages' ),
		),
		__( 'Communication', 'celb-mgmt' ) => array(
			'notify'  => array( __( 'Notification emails', 'celb-mgmt' ), 'mail', 'email from reply cc reminder schedule notification' ),
			'contact' => array( __( 'Artist contact form', 'celb-mgmt' ), 'chat', 'contact form recaptcha recipient lets talk spam' ),
		),
		__( 'Business', 'celb-mgmt' ) => array(
			'contracts' => array( __( 'Contracts', 'celb-mgmt' ), 'pen', 'contract signature logo email subject body signing' ),
			'rate'      => array( __( 'Rate cards', 'celb-mgmt' ), 'tag', 'rate card usd egp currency markup contact button' ),
		),
		__( 'Apps & sync', 'celb-mgmt' ) => array(
			'studioapp' => array( __( 'Studio app', 'celb-mgmt' ), 'sparkle', 'studio app pwa icon home screen push notifications install phone super app managers' ),
			'talentapp' => array( __( 'Talent app', 'celb-mgmt' ), 'phone', 'talent app pwa celebrity login push notifications schedule contracts install phone' ),
			'calendar' => array( __( 'Agency calendar', 'celb-mgmt' ), 'calendar', 'calendar ics feed webcal google apple' ),
		),
		__( 'Reference', 'celb-mgmt' ) => array(
			'shortcodes' => array( __( 'Shortcodes', 'celb-mgmt' ), 'code', 'shortcode embed' ),
		),
	);
}

function celb_studio_section_open( $key, $title, $desc ) {
	echo '<div class="cs-section" data-cs-section="' . esc_attr( $key ) . '">';
	echo '<header class="cs-section-head"><h2>' . esc_html( $title ) . '</h2>' . ( $desc ? '<p>' . wp_kses_post( $desc ) . '</p>' : '' ) . '</header>';
}
function celb_studio_section_close() {
	echo '</div>';
}

function celb_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s     = celb_get_settings();
	$nav   = celb_studio_settings_nav();
	$saved = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated']; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="wrap cs-settings">
		<h1 class="screen-reader-text"><?php esc_html_e( 'CELB MGMT Settings', 'celb-mgmt' ); ?></h1>
		<hr class="wp-header-end" />
		<form method="post" action="options.php" class="cs-settings-form" data-cs-settings>
			<?php settings_fields( 'celb_settings_group' ); ?>

			<header class="cs-settings-bar">
				<div class="cs-settings-brand">
					<span class="cs-settings-logo"><?php if ( celb_logo_url() ) : ?><img src="<?php echo esc_url( celb_logo_url() ); ?>" alt="" /><?php endif; ?></span>
					<div>
						<p class="cs-eyebrow"><?php esc_html_e( 'CELB MGMT', 'celb-mgmt' ); ?> · v<?php echo esc_html( CELB_VERSION ); ?></p>
						<p class="cs-settings-title"><?php esc_html_e( 'Settings', 'celb-mgmt' ); ?></p>
					</div>
				</div>
				<div class="cs-settings-search"><?php echo celb_studio_icon( 'search', 16 ); // phpcs:ignore ?><input type="search" class="cs-input" placeholder="<?php esc_attr_e( 'Search settings…', 'celb-mgmt' ); ?>" data-cs-search aria-label="<?php esc_attr_e( 'Search settings', 'celb-mgmt' ); ?>" /></div>
				<div class="cs-settings-save">
					<span class="cs-dirty" data-cs-dirty><?php echo $saved ? esc_html__( 'All changes saved', 'celb-mgmt' ) : ''; ?></span>
					<button type="submit" class="cs-btn cs-btn--primary"><?php echo celb_studio_icon( 'check', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'Save changes', 'celb-mgmt' ); ?></span></button>
				</div>
			</header>

			<div class="cs-settings-body">
				<nav class="cs-snav" aria-label="<?php esc_attr_e( 'Settings sections', 'celb-mgmt' ); ?>">
					<?php foreach ( $nav as $group => $items ) : ?>
						<div class="cs-snav-group">
							<p class="cs-snav-title"><?php echo esc_html( $group ); ?></p>
							<?php foreach ( $items as $key => $it ) : ?>
								<button type="button" class="cs-snav-item" data-cs-nav="<?php echo esc_attr( $key ); ?>" data-keywords="<?php echo esc_attr( strtolower( $it[0] . ' ' . $it[2] ) ); ?>"><?php echo celb_studio_icon( $it[1], 17 ); // phpcs:ignore ?><span><?php echo esc_html( $it[0] ); ?></span></button>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</nav>

				<div class="cs-sections">
					<p class="cs-search-empty" data-cs-search-empty hidden><?php esc_html_e( 'No settings match your search.', 'celb-mgmt' ); ?></p>
					<?php
					/* ---------------- Brand ---------------- */
					celb_studio_section_open( 'brand', __( 'Brand', 'celb-mgmt' ), __( 'One logo for the smart link footer, rate cards, social cards, talent portals and notification emails.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Brand logo', 'celb-mgmt' ), __( 'Use a light / white logo — those pages have dark backgrounds.', 'celb-mgmt' ), 'sparkle' );
					celb_studio_media( 'celb_settings[brand_logo_url]', $s['brand_logo_url'], array( 'store' => 'url', 'shape' => 'logo', 'stage' => 'dark', 'label' => __( 'Choose logo', 'celb-mgmt' ), 'placeholder' => 'https://…/logo.png' ) );
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Appearance ---------------- */
					celb_studio_section_open( 'appearance', __( 'Colours & fonts', 'celb-mgmt' ), __( 'Keep these in step with your theme so the whole site feels like one brand.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Accent & background', 'celb-mgmt' ), '', 'palette' );
					echo '<div class="cs-row-fields">';
					celb_studio_s_color( 'accent', celb_accent(), __( 'Accent colour', 'celb-mgmt' ), __( 'Roster highlights, Lead badges, links, grid, carousel and profile pages. Default #999999.', 'celb-mgmt' ), '#999999' );
					celb_studio_field_open( __( 'Profile page background', 'celb-mgmt' ) );
					echo '<div class="cs-choice">';
					foreach ( array( 'light' => __( 'Light', 'celb-mgmt' ), 'dark' => __( 'Dark', 'celb-mgmt' ) ) as $k => $lbl ) {
						echo '<label class="cs-choice-item cs-choice-item--' . esc_attr( $k ) . '"><input type="radio" name="celb_settings[theme]" value="' . esc_attr( $k ) . '" ' . checked( $s['theme'], $k, false ) . ' /><span class="cs-choice-swatch"><i></i><i></i><i></i></span><span class="cs-choice-label">' . esc_html( $lbl ) . '</span></label>';
					}
					echo '</div>';
					celb_studio_field_close( __( 'Match your theme. The hero always keeps its gradient and white name.', 'celb-mgmt' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_card_open( __( 'Custom theme', 'celb-mgmt' ), __( 'Leave everything empty to inherit your theme’s fonts and colours. Fill a field only to override it across the plugin and the standalone pages.', 'celb-mgmt' ), 'text' );
					celb_studio_s_text( 'font_url', $s, __( 'Google Fonts URL', 'celb-mgmt' ), '', array( 'type' => 'url', 'placeholder' => 'https://fonts.googleapis.com/css2?family=…' ) );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'font_body', $s, __( 'Body font family', 'celb-mgmt' ), '', array( 'placeholder' => "'Inter', sans-serif" ) );
					celb_studio_s_text( 'font_display', $s, __( 'Heading font family', 'celb-mgmt' ), '', array( 'placeholder' => "'Bodoni Moda', serif" ) );
					echo '</div><div class="cs-row-fields">';
					celb_studio_s_color( 'text_color', $s['text_color'], __( 'Text colour', 'celb-mgmt' ) );
					celb_studio_s_color( 'bg_color', $s['bg_color'], __( 'Background colour', 'celb-mgmt' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Layout ---------------- */
					celb_studio_section_open( 'layout', __( 'Grid & carousel', 'celb-mgmt' ), __( 'Desktop density for the [CLEB_celebrities] grid and the homepage carousel. Phones always get a 2-column grid and swipeable cards.', 'celb-mgmt' ) );
					celb_studio_card_open( '', '' );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'grid_cols', $s, __( 'Grid columns', 'celb-mgmt' ), __( '1–6 · default 3', 'celb-mgmt' ), array( 'type' => 'number', 'attr' => 'min="1" max="6"', 'suffix' => __( 'per row', 'celb-mgmt' ) ) );
					celb_studio_s_text( 'carousel_items', $s, __( 'Carousel items', 'celb-mgmt' ), __( '2–8 · default 6', 'celb-mgmt' ), array( 'type' => 'number', 'attr' => 'min="2" max="8"', 'suffix' => __( 'visible', 'celb-mgmt' ) ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Roster ---------------- */
					celb_studio_section_open( 'roster', __( 'Roster header', 'celb-mgmt' ), __( 'The heading above the [CLEB_celebrities] grid. Leave a field empty to hide it; shortcode attributes still override these.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Heading', 'celb-mgmt' ), '', 'text' );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'roster_eyebrow', $s, __( 'Eyebrow', 'celb-mgmt' ), __( 'The small line above the title.', 'celb-mgmt' ) );
					celb_studio_s_text( 'roster_title', $s, __( 'Title', 'celb-mgmt' ), __( 'e.g. “Our Stars”.', 'celb-mgmt' ) );
					echo '</div>';
					celb_studio_s_textarea( 'roster_intro', $s, __( 'Intro paragraph', 'celb-mgmt' ) );
					celb_studio_card_close();
					celb_studio_card_open( __( 'Carousel “View all” button', 'celb-mgmt' ), '', 'grid' );
					celb_studio_s_switch( 'carousel_viewall', $s, __( 'Show a button below the carousel', 'celb-mgmt' ) );
					echo '<div class="cs-row-fields" data-cs-depends="celb_settings[carousel_viewall]">';
					celb_studio_s_text( 'carousel_viewall_label', $s, __( 'Label', 'celb-mgmt' ), '', array( 'placeholder' => 'View all talent' ) );
					celb_studio_s_text( 'carousel_viewall_url', $s, __( 'Link', 'celb-mgmt' ), '', array( 'type' => 'url', 'placeholder' => 'https://ilikeagency.co/our-celebrities/' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Profile page ---------------- */
					celb_studio_section_open( 'profile', __( 'Profile page', 'celb-mgmt' ), __( 'Applies to every individual celebrity page.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Hero', 'celb-mgmt' ), '', 'layout' );
					celb_studio_s_switch( 'pull_hero', $s, __( 'Pull the hero flush under the site header', 'celb-mgmt' ), __( 'Removes the theme’s top gap. Turn off only if your header overlaps the hero.', 'celb-mgmt' ) );
					celb_studio_card_close();
					celb_studio_card_open( __( 'Call-to-action button', 'celb-mgmt' ), __( 'Shown below the gallery on each profile (e.g. “Let’s Talk”). If a contact page is set under Artist contact form, the button opens it with the artist pre-selected.', 'celb-mgmt' ), 'chat' );
					celb_studio_s_switch( 'cta_show', $s, __( 'Show the button', 'celb-mgmt' ) );
					echo '<div class="cs-row-fields" data-cs-depends="celb_settings[cta_show]">';
					celb_studio_s_text( 'cta_label', $s, __( 'Label', 'celb-mgmt' ), '', array( 'placeholder' => __( 'Button label', 'celb-mgmt' ) ) );
					celb_studio_s_text( 'cta_url', $s, __( 'Link', 'celb-mgmt' ), '', array( 'type' => 'url', 'placeholder' => 'https://' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Our Stars ---------------- */
					celb_studio_section_open( 'stars', __( 'Our Stars page', 'celb-mgmt' ), __( 'A standalone, agency-branded mobile page that gathers every profile as a card — Lead talent first, then shuffled. On desktop it shows an “open on mobile” message.', 'celb-mgmt' ) );
					celb_studio_card_open( '', '' );
					celb_studio_s_switch( 'aio_enabled', $s, __( 'Enable the Our Stars page', 'celb-mgmt' ) );
					echo '<div data-cs-depends="celb_settings[aio_enabled]">';
					celb_studio_copy_field( celb_stars_url(), true, __( 'Private link', 'celb-mgmt' ) );
					celb_studio_s_text( 'aio_title', $s, __( 'Page title', 'celb-mgmt' ), '', array( 'placeholder' => 'Our Stars' ) );
					celb_studio_s_textarea( 'aio_intro', $s, __( 'Intro text', 'celb-mgmt' ), '', 3, __( 'Short description shown under the logo…', 'celb-mgmt' ) );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'aio_cta_label', $s, __( 'Bottom button label', 'celb-mgmt' ), '', array( 'placeholder' => __( 'View Our Roster', 'celb-mgmt' ) ) );
					celb_studio_s_text( 'aio_cta_url', $s, __( 'Bottom button link', 'celb-mgmt' ), __( 'Empty = reuse the profile call-to-action link.', 'celb-mgmt' ), array( 'type' => 'url', 'placeholder' => 'https://…/our-roster/' ) );
					echo '</div></div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Submission portal ---------------- */
					celb_studio_section_open( 'portal', __( 'Submission portal', 'celb-mgmt' ), __( 'Password-protected self-submission form ([CLEB_submit]) and the standalone onboarding page. Submissions arrive as Draft profiles for review.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Access', 'celb-mgmt' ), '', 'door' );
					celb_studio_s_switch( 'portal_enabled', $s, __( 'Enable the self-submission portal', 'celb-mgmt' ), __( 'Applies to both the shortcode page and the onboarding page.', 'celb-mgmt' ) );
					celb_studio_copy_field( celb_onboarding_url(), true, __( 'Onboarding page', 'celb-mgmt' ) );
					celb_studio_card_close();
					celb_studio_card_open( __( 'Access passwords', 'celb-mgmt' ), __( 'Give each talent or campaign their own password so you can revoke access individually.', 'celb-mgmt' ), 'key' );
					celb_studio_repeater( 'passwords', ! empty( $s['portal_passwords'] ) ? $s['portal_passwords'] : array(), 'celb_studio_pw_row', array(
						'add'   => __( 'Add password', 'celb-mgmt' ),
						'icon'  => 'key',
						'empty' => __( 'No passwords yet — nobody can open the portal.', 'celb-mgmt' ),
					) );
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Personal data ---------------- */
					celb_studio_section_open( 'pdata', __( 'Personal data', 'celb-mgmt' ), sprintf( __( 'A standalone, password-protected page for personal details and emergency contacts. Build the questions under %1$s; read submissions under %2$s.', 'celb-mgmt' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-pdata-form' ) ) . '">' . esc_html__( 'Personal Data Form', 'celb-mgmt' ) . '</a>', '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CELB_CPT . '&page=celb-pdata' ) ) . '">' . esc_html__( 'Personal Data', 'celb-mgmt' ) . '</a>' ) );
					celb_studio_card_open( '', '' );
					celb_studio_s_switch( 'pdata_enabled', $s, __( 'Enable the Personal Data page', 'celb-mgmt' ) );
					echo '<div data-cs-depends="celb_settings[pdata_enabled]">';
					celb_studio_s_text( 'pdata_password', $s, __( 'Access password', 'celb-mgmt' ), '', array( 'class' => 'cs-field--narrow', 'attr' => 'autocomplete="off"' ) );
					celb_studio_copy_field( celb_pdata_url(), true, __( 'Secure page link', 'celb-mgmt' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Page addresses ---------------- */
					celb_studio_section_open( 'pages', __( 'Page addresses', 'celb-mgmt' ), __( 'Custom URL endings for the standalone pages. Links update everywhere automatically.', 'celb-mgmt' ) );
					celb_studio_card_open( '', '' );
					$home = preg_replace( '#^https?://#', '', home_url( '/' ) );
					foreach ( array(
						'stars_slug'   => __( 'Our Stars page', 'celb-mgmt' ),
						'onb_slug'     => __( 'Talent onboarding', 'celb-mgmt' ),
						'pdata_slug'   => __( 'Personal data', 'celb-mgmt' ),
						'rateonb_slug' => __( 'Rate-card forms (base)', 'celb-mgmt' ),
						'app_slug'     => __( 'Studio app', 'celb-mgmt' ),
						'talent_slug'  => __( 'Talent app', 'celb-mgmt' ),
					) as $sk => $sl ) {
						celb_studio_s_text( $sk, $s, $sl, '', array( 'prefix' => $home ) );
					}
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Notifications ---------------- */
					celb_studio_section_open( 'notify', __( 'Notification emails', 'celb-mgmt' ), __( 'When a project or schedule is created, postponed or canceled, the celebrity (if an email is set on it) is notified automatically.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Sender', 'celb-mgmt' ), __( 'The From address must be one your site may send from (ideally on your domain) or messages may land in spam.', 'celb-mgmt' ), 'mail' );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'sched_email_from_name', $s, __( 'From name', 'celb-mgmt' ), '', array( 'placeholder' => 'iLike Agency' ) );
					celb_studio_s_text( 'sched_email_from', $s, __( 'From email', 'celb-mgmt' ), '', array( 'type' => 'email', 'placeholder' => 'schedule@ilikeagency.co' ) );
					echo '</div><div class="cs-row-fields">';
					celb_studio_s_text( 'sched_email_replyto', $s, __( 'Reply-to', 'celb-mgmt' ), '', array( 'type' => 'email', 'placeholder' => __( 'Optional', 'celb-mgmt' ) ) );
					celb_studio_s_text( 'sched_email_cc', $s, __( 'CC', 'celb-mgmt' ), __( 'Comma-separated.', 'celb-mgmt' ), array( 'placeholder' => 'ahmed@ilikeagency.co' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_card_open( __( 'Reminders', 'celb-mgmt' ), '', 'calendar' );
					celb_studio_s_text( 'sched_email_reminder', $s, __( 'Send a reminder', 'celb-mgmt' ), __( '0 turns reminders off.', 'celb-mgmt' ), array( 'type' => 'number', 'attr' => 'min="0"', 'suffix' => __( 'days before', 'celb-mgmt' ), 'class' => 'cs-field--narrow' ) );
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Contact form ---------------- */
					celb_studio_section_open( 'contact', __( 'Artist contact form', 'celb-mgmt' ), __( 'A roster-connected request form, separate from the site’s general Contact Us. Put [artist_contact_form] on any page.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Routing', 'celb-mgmt' ), '', 'chat' );
					celb_studio_field_open( __( 'Contact page (Let’s Talk)', 'celb-mgmt' ), 'celb_s_contact_page_id' );
					wp_dropdown_pages( array(
						'name'              => 'celb_settings[contact_page_id]',
						'id'                => 'celb_s_contact_page_id',
						'class'             => 'cs-input',
						'selected'          => (int) $s['contact_page_id'],
						'show_option_none'  => __( '— Use the profile CTA link —', 'celb-mgmt' ),
						'option_none_value' => 0,
					) );
					celb_studio_field_close( __( 'The Let’s Talk button on each profile opens this page with that artist pre-selected.', 'celb-mgmt' ) );
					celb_studio_s_text( 'contact_recipient', $s, __( 'Send requests to', 'celb-mgmt' ), __( 'Comma-separated emails.', 'celb-mgmt' ), array( 'placeholder' => get_option( 'admin_email' ) ) );
					celb_studio_card_close();
					celb_studio_card_open( __( 'Anti-spam · Google reCAPTCHA v2', 'celb-mgmt' ), __( 'Get keys at google.com/recaptcha (“I’m not a robot”). Leave empty to rely on the built-in honeypot and rate limit.', 'celb-mgmt' ), 'lock' );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'recaptcha_site', $s, __( 'Site key', 'celb-mgmt' ), '', array( 'attr' => 'autocomplete="off"' ) );
					celb_studio_s_text( 'recaptcha_secret', $s, __( 'Secret key', 'celb-mgmt' ), '', array( 'attr' => 'autocomplete="off"' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Contracts ---------------- */
					celb_studio_section_open( 'contracts', __( 'Contracts', 'celb-mgmt' ), __( 'Applied to every online contract and its signing page.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Branding & signature', 'celb-mgmt' ), '', 'pen' );
					echo '<div class="cs-row-fields">';
					celb_studio_field_open( __( 'Agency signature', 'celb-mgmt' ), '', __( 'Transparent PNG works best.', 'celb-mgmt' ) );
					celb_studio_media( 'celb_settings[agency_sig]', $s['agency_sig'], array( 'shape' => 'signature', 'label' => __( 'Choose signature', 'celb-mgmt' ) ) );
					celb_studio_field_close();
					celb_studio_field_open( __( 'Contract logo', 'celb-mgmt' ), '', __( 'Contract pages are white — use a dark / colour logo.', 'celb-mgmt' ) );
					celb_studio_media( 'celb_settings[contract_logo]', $s['contract_logo'], array( 'shape' => 'signature', 'label' => __( 'Choose logo', 'celb-mgmt' ) ) );
					celb_studio_field_close();
					echo '</div>';
					celb_studio_card_close();
					celb_studio_card_open( __( 'Signed contract email', 'celb-mgmt' ), __( 'The signed PDF is attached automatically. For reliable delivery your domain needs SPF + DKIM and the site should send via authenticated SMTP.', 'celb-mgmt' ), 'mail' );
					celb_studio_s_text( 'contract_from', $s, __( 'From address', 'celb-mgmt' ), '', array( 'type' => 'email', 'placeholder' => 'ahmed@ilikeagency.co' ) );
					celb_studio_s_text( 'contract_subject', $s, __( 'Subject', 'celb-mgmt' ) );
					celb_studio_s_textarea( 'contract_body', $s, __( 'Body', 'celb-mgmt' ), '', 4 );
					celb_studio_card_close();
					celb_studio_card_open( __( 'Signing page', 'celb-mgmt' ), '', 'link' );
					celb_studio_s_text( 'contract_sign_url', $s, __( 'Signing page URL', 'celb-mgmt' ), __( 'A page holding the [CLEB_sign] shortcode. Empty = /sign/.', 'celb-mgmt' ), array( 'type' => 'url', 'placeholder' => home_url( '/sign/' ) ) );
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Rate cards ---------------- */
					celb_studio_section_open( 'rate', __( 'Rate cards', 'celb-mgmt' ), sprintf( __( 'Shared settings only — pricing, sections, terms and passwords live on each card under %s.', 'celb-mgmt' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CELB_RATE_CPT ) ) . '">' . esc_html__( 'Rate Cards', 'celb-mgmt' ) . '</a>' ) );
					celb_studio_card_open( __( 'Currency', 'celb-mgmt' ), __( 'Cards stay in EGP by default. USD = EGP ÷ rate, plus markup. Percentage options (e.g. rush) are unaffected.', 'celb-mgmt' ), 'tag' );
					celb_studio_s_switch( 'rc_usd_enabled', $s, __( 'Let visitors switch between EGP and USD', 'celb-mgmt' ) );
					echo '<div class="cs-row-fields" data-cs-depends="celb_settings[rc_usd_enabled]">';
					celb_studio_s_text( 'rc_usd_rate', $s, __( 'Exchange rate', 'celb-mgmt' ), '', array( 'type' => 'number', 'attr' => 'step="any" min="0.0001"', 'prefix' => '1 USD =', 'suffix' => 'EGP' ) );
					celb_studio_s_text( 'rc_usd_markup', $s, __( 'Markup', 'celb-mgmt' ), '', array( 'type' => 'number', 'attr' => 'step="any" min="0"', 'suffix' => '%' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_card_open( __( 'Buttons', 'celb-mgmt' ), '', 'chat' );
					celb_studio_s_text( 'rc_contact_url', $s, __( 'Contact link', 'celb-mgmt' ), __( 'Where “Contact My Management” points — a WhatsApp link or your contact page.', 'celb-mgmt' ), array( 'type' => 'url', 'placeholder' => 'https://wa.me/2010…' ) );
					echo '<div class="cs-row-fields">';
					celb_studio_s_text( 'rc_cta_label', $s, __( 'Copy / CTA button', 'celb-mgmt' ) );
					celb_studio_s_text( 'rc_contact_label', $s, __( 'Contact button', 'celb-mgmt' ) );
					echo '</div>';
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Studio app ---------------- */
					celb_studio_section_open( 'studioapp', __( 'Studio app', 'celb-mgmt' ), __( 'One app for the whole agency: inbox, calendar, roster, projects, newsroom, contracts, rate cards, onboarding and personal data — with push notifications. Managers sign in with their WordPress account.', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'App icon', 'celb-mgmt' ), __( 'Shown on the phone home screen, in the app switcher and on notifications — for the Studio app and the Talent app.', 'celb-mgmt' ), 'image' );
					echo '<div class="cs-field--narrow">';
					celb_studio_field_open( __( 'Icon', 'celb-mgmt' ), '', __( 'Square PNG, at least 512×512. Falls back to the brand logo.', 'celb-mgmt' ) );
					celb_studio_media( 'celb_settings[pwa_icon]', $s['pwa_icon'], array( 'store' => 'url', 'shape' => 'icon', 'label' => __( 'Choose icon', 'celb-mgmt' ), 'placeholder' => 'https://…/app-icon.png' ) );
					celb_studio_field_close();
					echo '</div>';
					celb_studio_card_close();
					celb_studio_card_open( __( 'Open & install', 'celb-mgmt' ), __( 'iPhone: open the link in Safari → Share → Add to Home Screen, then open it from the Home Screen and turn on notifications in More → App settings. Android / desktop Chrome: open the link and choose Install.', 'celb-mgmt' ), 'phone' );
					celb_studio_copy_field( celb_app_url(), true, __( 'App link', 'celb-mgmt' ) );
					celb_studio_s_text( 'app_name', $s, __( 'App name', 'celb-mgmt' ), __( 'Shown under the home-screen icon.', 'celb-mgmt' ), array( 'placeholder' => 'CELB Studio', 'class' => 'cs-field--narrow' ) );
					echo '<div class="cs-note">' . celb_studio_icon( CELB_Push::supported() ? 'check' : 'alert', 16 ) . '<span>' . esc_html( CELB_Push::supported() ? __( 'This server can send push notifications.', 'celb-mgmt' ) : __( 'This server’s PHP/OpenSSL cannot send push notifications; the in-app activity feed still works.', 'celb-mgmt' ) ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Talent app ---------------- */
					celb_studio_section_open( 'talentapp', __( 'Talent app', 'celb-mgmt' ), __( 'The talent’s own app: their schedule and shooting days, projects, contracts to review and sign, profile links and notifications. Each talent signs in with the login created on their profile (App login box).', 'celb-mgmt' ) );
					celb_studio_card_open( __( 'Open & install', 'celb-mgmt' ), __( 'Send this link with the talent’s login. They add it to the Home Screen the same way as the Studio app. It uses the app icon set under Studio app.', 'celb-mgmt' ), 'phone' );
					celb_studio_copy_field( celb_talent_url(), true, __( 'App link', 'celb-mgmt' ) );
					celb_studio_s_text( 'talent_name', $s, __( 'App name', 'celb-mgmt' ), __( 'Shown under the home-screen icon.', 'celb-mgmt' ), array( 'placeholder' => 'CELB Talent', 'class' => 'cs-field--narrow' ) );
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Agency calendar ---------------- */
					celb_studio_section_open( 'calendar', __( 'Agency calendar', 'celb-mgmt' ), __( 'A private, always-live feed of every celebrity’s projects, shooting days and confirmed bookings. Subscribe once on your phone or computer.', 'celb-mgmt' ) );
					celb_studio_card_open( '', '' );
					celb_studio_copy_field( celb_agency_cal_url( 'webcal' ), false, __( 'Apple / Outlook', 'celb-mgmt' ) );
					celb_studio_copy_field( celb_agency_cal_url( 'https' ), false, __( 'Google', 'celb-mgmt' ) );
					echo '<div class="cs-actions"><a class="cs-btn cs-btn--sm" href="' . esc_url( celb_agency_cal_url( 'webcal' ) ) . '">' . celb_studio_icon( 'calendar', 15 ) . '<span>' . esc_html__( 'Add to Apple Calendar', 'celb-mgmt' ) . '</span></a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '<div class="cs-note">' . celb_studio_icon( 'lock', 16 ) . '<span>' . esc_html__( 'Keep this link private — anyone with it can view the full agency schedule.', 'celb-mgmt' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
					celb_studio_card_close();
					celb_studio_section_close();

					/* ---------------- Shortcodes ---------------- */
					celb_studio_section_open( 'shortcodes', __( 'Shortcodes', 'celb-mgmt' ), __( 'Paste these into page content, a text widget or a builder shortcode block.', 'celb-mgmt' ) );
					echo '<div class="cs-codes">';
					foreach ( array(
						'[CLEB_celebrities]'          => __( 'Full roster grid, randomised each load. Optional limit="9".', 'celb-mgmt' ),
						'[CLEB_celebrities_carousel]' => __( 'Homepage carousel, randomised each load. Optional limit="12" (pool size).', 'celb-mgmt' ),
						'[CLEB_newsroom]'             => __( 'Global newsroom with a “Filter by artist” dropdown.', 'celb-mgmt' ),
						'[CLEB_newsroom_carousel]'    => __( 'Six most recent articles with a “View More” button. Optional title, count, more_url, more_label.', 'celb-mgmt' ),
						'[ilike_works_archive]'       => __( 'Talent Works Archive — every production with artist / year / type / search filters.', 'celb-mgmt' ),
						'[CLEB_submit]'               => __( 'Password-protected self-submission form; creates Draft profiles.', 'celb-mgmt' ),
						'[artist_contact_form]'       => __( 'Roster-connected artist request form.', 'celb-mgmt' ),
						'[CLEB_request]'              => __( 'Booking / contact request form. Add celeb="ID" to lock it to one celebrity.', 'celb-mgmt' ),
						'[CLEB_sign]'                 => __( 'Contract signing page.', 'celb-mgmt' ),
					) as $code => $desc ) {
						echo '<div class="cs-code"><button type="button" class="cs-code-tag" data-cs-copy="' . esc_attr( $code ) . '" title="' . esc_attr__( 'Copy', 'celb-mgmt' ) . '"><code>' . esc_html( $code ) . '</code>' . celb_studio_icon( 'copy', 14 ) . '</button><p>' . esc_html( $desc ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					echo '</div>';
					celb_studio_section_close();
					?>
				</div>
			</div>
		</form>
		<div class="cs-toast<?php echo $saved ? ' is-on' : ''; ?>" data-cs-toast role="status"><?php echo celb_studio_icon( 'check', 16 ); // phpcs:ignore ?><span><?php echo $saved ? esc_html__( 'Settings saved', 'celb-mgmt' ) : ''; ?></span></div>
	</div>
	<?php
}
