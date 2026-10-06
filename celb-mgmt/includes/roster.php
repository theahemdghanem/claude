<?php
/**
 * CELB MGMT — Talent roster grid [CLEB_celebrities] and carousel
 * [CLEB_celebrities_carousel].
 *
 * Both share one card (celb_render_talent_card()) and one stylesheet
 * (assets/celb-roster.css) that inherits the active theme: its fonts
 * (names are headings, so theme heading fonts apply), its text colour and
 * background, and its accent colour unless one is set in Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Best portrait for a card: Profile image → featured image → desktop hero. */
function celb_talent_card_image_id( $post_id ) {
	foreach ( array( (int) get_post_meta( $post_id, '_celb_profile', true ), (int) get_post_thumbnail_id( $post_id ), (int) get_post_meta( $post_id, '_celb_hero_desktop', true ) ) as $id ) {
		if ( $id && wp_attachment_is_image( $id ) ) {
			return $id;
		}
	}
	return 0;
}

/**
 * One talent card.
 *
 * @param int    $post_id Celebrity ID.
 * @param string $sizes   <img sizes> hint for the layout it sits in.
 * @param bool   $eager   Load the image straight away (first visible cards).
 */
function celb_render_talent_card( $post_id, $sizes = '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 25vw', $eager = false ) {
	$img_id = celb_talent_card_image_id( $post_id );
	$name   = get_the_title( $post_id );
	$role   = (string) get_post_meta( $post_id, '_celb_role', true );
	$nat    = (string) get_post_meta( $post_id, '_celb_nationality', true );
	$cats   = celb_roster_categories( $post_id );
	$lead   = (bool) get_post_meta( $post_id, '_celb_lead', true );
	$locked = (bool) get_post_meta( $post_id, '_celb_locked', true );
	$labels = array_map( 'celb_roster_cat_label', $cats );

	$media = '';
	if ( $img_id ) {
		$media = wp_get_attachment_image(
			$img_id,
			'large',
			false,
			array(
				'class'    => 'celb-tc-img',
				'alt'      => $name,
				'sizes'    => $sizes,
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
	}
	if ( '' === $media ) {
		$media = '<span class="celb-tc-mono" aria-hidden="true">' . esc_html( celb_monogram( $name ) ) . '</span>';
	}

	$search = strtolower( remove_accents( $name . ' ' . $role . ' ' . $nat . ' ' . implode( ' ', $labels ) ) );
	$tag    = $locked ? 'div' : 'a';
	$href   = $locked ? '' : ' href="' . esc_url( get_permalink( $post_id ) ) . '"';

	ob_start();
	?>
	<article class="celb-tc<?php echo $locked ? ' is-private' : ''; ?><?php echo $lead ? ' is-lead' : ''; ?>" data-cat="<?php echo esc_attr( implode( ' ', $cats ) ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="celb-tc-link"<?php echo $href; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $locked ? ' aria-disabled="true"' : ''; ?>>
			<span class="celb-tc-media"><?php echo $media; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="celb-tc-shade" aria-hidden="true"></span>
			<?php if ( $lead || $locked ) : ?>
				<span class="celb-tc-badges">
					<?php if ( $lead ) : ?>
						<span class="celb-tc-badge"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg><?php esc_html_e( 'Lead', 'celb-mgmt' ); ?></span>
					<?php endif; ?>
					<?php if ( $locked ) : ?>
						<span class="celb-tc-badge"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg><?php esc_html_e( 'Private', 'celb-mgmt' ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<?php if ( ! $locked ) : ?>
				<span class="celb-tc-go" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 17 17 7M8 7h9v9"/></svg></span>
			<?php endif; ?>
			<span class="celb-tc-info">
				<h3 class="celb-tc-name"><?php echo esc_html( $name ); ?></h3>
				<?php if ( $role ) : ?>
					<span class="celb-tc-role"><?php echo esc_html( $role ); ?></span>
				<?php endif; ?>
				<?php if ( $nat || $labels ) : ?>
					<span class="celb-tc-meta"><?php echo esc_html( implode( ' · ', array_filter( array( $nat, $labels ? $labels[0] : '' ) ) ) ); ?></span>
				<?php endif; ?>
			</span>
		</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	</article>
	<?php
	return ob_get_clean();
}

/* Inline style for a roster root: accent (only when chosen in Settings) + custom colours. */
function celb_talent_root_style( $extra = '' ) {
	$s     = celb_get_settings();
	$style = $extra;
	if ( '#999999' !== strtolower( celb_accent() ) ) {
		$style .= '--celb-accent:' . celb_accent() . ';';
	}
	if ( ! empty( $s['bg_color'] ) ) {
		$style .= '--celb-r-bg:' . $s['bg_color'] . ';background:' . $s['bg_color'] . ';';
	}
	if ( ! empty( $s['text_color'] ) ) {
		$style .= 'color:' . $s['text_color'] . ';';
	}
	return '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '';
}

/* Ordered IDs for a roster query. */
function celb_talent_ids( $limit, $orderby ) {
	$ids = get_posts( array(
		'post_type'        => CELB_CPT,
		'post_status'      => 'publish',
		'numberposts'      => (int) $limit,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => false,
	) );
	if ( 'title' === $orderby ) {
		return $ids;
	}
	if ( 'rand' === $orderby ) {
		shuffle( $ids );
		return $ids;
	}
	$leads = array();
	$rest  = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_celb_lead', true ) ) {
			$leads[] = $id;
		} else {
			$rest[] = $id;
		}
	}
	if ( 'lead' !== $orderby ) { // lead_rand: leads first, everyone shuffled within their group.
		shuffle( $leads );
		shuffle( $rest );
	}
	return array_merge( $leads, $rest );
}

/* =========================================================================
 * [CLEB_celebrities] — full roster grid
 * ====================================================================== */
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
		'search'  => 'yes',
		'orderby' => 'lead_rand', // lead_rand (leads first, shuffled) | rand | lead | title
		'full'    => 'yes',       // yes = full viewport width (content stays centred); no = theme column
		'cols'    => '',          // desktop columns; empty = Settings → Grid & carousel
	), $atts, 'CLEB_celebrities' );

	$ids = celb_talent_ids( $atts['limit'], $atts['orderby'] );
	if ( ! $ids ) {
		return '';
	}

	$present = array();
	foreach ( $ids as $id ) {
		foreach ( celb_roster_categories( $id ) as $c ) {
			$present[ $c ] = isset( $present[ $c ] ) ? $present[ $c ] + 1 : 1;
		}
	}
	$cols = ctype_digit( (string) $atts['cols'] ) && (int) $atts['cols'] > 0 ? (int) $atts['cols'] : (int) $rs['grid_cols'];
	$cols = max( 2, min( 6, $cols ? $cols : 4 ) );
	$uid  = 'celb-tg-' . wp_unique_id();

	ob_start();
	echo '<section class="celb-scope celb-tg' . ( 'yes' === $atts['full'] ? ' is-full' : '' ) . '" id="' . esc_attr( $uid ) . '" data-celb-tg' . celb_talent_root_style( '--tg-cols:' . $cols . ';' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="celb-tg-in">';

	if ( 'yes' === $atts['header'] && ( '' !== $atts['eyebrow'] || '' !== $atts['title'] || '' !== $atts['intro'] ) ) {
		echo '<header class="celb-tg-head"><div class="celb-tg-head-main">';
		if ( '' !== $atts['eyebrow'] ) {
			echo '<p class="celb-tg-eyebrow">' . esc_html( $atts['eyebrow'] ) . '</p>';
		}
		if ( '' !== $atts['title'] ) {
			echo '<h2 class="celb-tg-title">' . esc_html( $atts['title'] ) . '</h2>';
		}
		echo '</div><div class="celb-tg-head-side">';
		if ( '' !== $atts['intro'] ) {
			echo '<p class="celb-tg-intro">' . esc_html( $atts['intro'] ) . '</p>';
		}
		/* translators: %d: number of talent. */
		echo '<p class="celb-tg-total"><b>' . esc_html( count( $ids ) ) . '</b> ' . esc_html( _n( 'talent', 'talent', count( $ids ), 'celb-mgmt' ) ) . '</p>';
		echo '</div></header>';
	}

	$show_filters = 'yes' === $atts['filters'] && count( $present ) > 1;
	if ( $show_filters || 'yes' === $atts['search'] ) {
		echo '<div class="celb-tg-bar">';
		if ( $show_filters ) {
			echo '<div class="celb-tg-pills" role="group" aria-label="' . esc_attr__( 'Filter by category', 'celb-mgmt' ) . '">';
			echo '<button type="button" class="is-on" data-filter="all" aria-pressed="true">' . esc_html__( 'All', 'celb-mgmt' ) . ' <span>' . esc_html( count( $ids ) ) . '</span></button>';
			foreach ( array_keys( celb_roster_cat_defs() ) as $c ) {
				if ( ! empty( $present[ $c ] ) ) {
					echo '<button type="button" data-filter="' . esc_attr( $c ) . '" aria-pressed="false">' . esc_html( celb_roster_cat_label( $c ) ) . ' <span>' . esc_html( $present[ $c ] ) . '</span></button>';
				}
			}
			echo '</div>';
		}
		if ( 'yes' === $atts['search'] ) {
			echo '<label class="celb-tg-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg><span class="screen-reader-text">' . esc_html__( 'Search talent', 'celb-mgmt' ) . '</span><input type="search" placeholder="' . esc_attr__( 'Search by name or role', 'celb-mgmt' ) . '" autocomplete="off" data-search /></label>';
		}
		echo '</div>';
	}

	echo '<div class="celb-tg-grid">';
	foreach ( array_values( $ids ) as $i => $id ) {
		echo celb_render_talent_card( $id, '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, ' . (int) round( 100 / $cols ) . 'vw', $i < $cols ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '<div class="celb-tg-empty" hidden><p>' . esc_html__( 'No talent matches your search.', 'celb-mgmt' ) . '</p><button type="button" data-reset>' . esc_html__( 'Show everyone', 'celb-mgmt' ) . '</button></div>';
	echo '<p class="celb-tg-live screen-reader-text" aria-live="polite"></p>';
	echo '</div></section>';
	return ob_get_clean();
}

/* =========================================================================
 * [CLEB_celebrities_carousel] — homepage carousel
 * ====================================================================== */
function celb_shortcode_carousel( $atts ) {
	celb_ensure_frontend_assets( true );
	$s    = celb_get_settings();
	$atts = shortcode_atts( array(
		'limit'         => 12,
		'orderby'       => 'rand', // rand | lead_rand | lead | title
		'title'         => '',
		'eyebrow'       => '',
		'viewall'       => '',
		'viewall_label' => '',
		'autoplay'      => 'yes',
	), $atts, 'CLEB_celebrities_carousel' );

	$ids = celb_talent_ids( $atts['limit'], $atts['orderby'] );
	if ( ! $ids ) {
		return '';
	}
	$va_url   = '' !== $atts['viewall'] ? $atts['viewall'] : ( ! empty( $s['carousel_viewall'] ) ? $s['carousel_viewall_url'] : '' );
	$va_label = '' !== $atts['viewall_label'] ? $atts['viewall_label'] : ( ! empty( $s['carousel_viewall_label'] ) ? $s['carousel_viewall_label'] : __( 'View all talent', 'celb-mgmt' ) );
	$n        = max( 2, min( 8, (int) $s['carousel_items'] ) );
	$arrow    = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';

	ob_start();
	echo '<section class="celb-scope celb-rail" data-celb-rail data-autoplay="' . ( 'no' === $atts['autoplay'] ? '0' : '1' ) . '" aria-roledescription="carousel" aria-label="' . esc_attr( '' !== $atts['title'] ? $atts['title'] : __( 'Talent', 'celb-mgmt' ) ) . '"' . celb_talent_root_style( '--rail-n:' . $n . ';' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( '' !== $atts['title'] || '' !== $atts['eyebrow'] ) {
		echo '<header class="celb-rail-head">';
		if ( '' !== $atts['eyebrow'] ) {
			echo '<p class="celb-tg-eyebrow">' . esc_html( $atts['eyebrow'] ) . '</p>';
		}
		if ( '' !== $atts['title'] ) {
			echo '<h2 class="celb-rail-title">' . esc_html( $atts['title'] ) . '</h2>';
		}
		echo '</header>';
	}
	echo '<div class="celb-rail-track" tabindex="0">';
	foreach ( array_values( $ids ) as $i => $id ) {
		echo '<div class="celb-rail-cell" role="group" aria-roledescription="slide" aria-label="' . esc_attr( sprintf( '%1$d / %2$d', $i + 1, count( $ids ) ) ) . '">' . celb_render_talent_card( $id, '(max-width: 600px) 62vw, (max-width: 1024px) 30vw, ' . (int) round( 100 / $n ) . 'vw', $i < $n ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '<div class="celb-rail-foot">';
	echo '<div class="celb-rail-progress" aria-hidden="true"><i></i></div>';
	echo '<div class="celb-rail-nav"><button type="button" class="celb-rail-btn" data-dir="-1" aria-label="' . esc_attr__( 'Previous', 'celb-mgmt' ) . '">' . $arrow . '</button><button type="button" class="celb-rail-btn is-next" data-dir="1" aria-label="' . esc_attr__( 'Next', 'celb-mgmt' ) . '">' . $arrow . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	if ( $va_url ) {
		echo '<div class="celb-rail-cta"><a class="celb-rail-all" href="' . esc_url( $va_url ) . '"><span>' . esc_html( $va_label ) . '</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>';
	}
	echo '</section>';
	return ob_get_clean();
}
