<?php
/**
 * Single Celebrity profile template.
 * Loaded via template_include for the `celebrity` post type.
 *
 * Layout: full-bleed hero → sticky section nav → overview (bio + at a glance)
 * → career → awards → gallery → videos → newsroom → closing call to action.
 * Typography and colours come from the active theme unless overridden under
 * Settings → Colours & fonts (see assets/celb-profile.css).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();

	$name        = get_the_title();
	$first       = strtok( $name, ' ' );
	$role        = (string) get_post_meta( $post_id, '_celb_role', true );
	$nationality = (string) get_post_meta( $post_id, '_celb_nationality', true );
	$desktop_id  = (int) get_post_meta( $post_id, '_celb_hero_desktop', true );
	$mobile_id   = (int) get_post_meta( $post_id, '_celb_hero_mobile', true );
	$desktop_url = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : get_the_post_thumbnail_url( $post_id, 'full' );
	$mobile_url  = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'large' ) : $desktop_url;

	$socials = celb_get_socials( $post_id );
	$career  = celb_get_career( $post_id );
	$awards  = celb_get_awards( $post_id );
	$gallery = array_values( array_filter( celb_get_gallery( $post_id ), function ( $gid ) { return (bool) wp_get_attachment_image_url( $gid, 'large' ); } ) );
	$bday    = celb_get_birthday( $post_id );
	$bio     = celb_get_bio( $post_id );
	$has_bio = '' !== trim( wp_strip_all_tags( $bio ) );
	$s       = celb_get_settings();
	$news    = celb_get_news( $post_id );
	$cats    = array_map( 'celb_roster_cat_label', celb_roster_categories( $post_id ) );
	$videos  = function_exists( 'celb_video_section_html' ) ? celb_video_section_html( $post_id ) : '';

	// Call to action: the artist contact page (artist pre-selected) wins over the plain link.
	$cta_url    = '';
	$cta_target = '';
	if ( ! empty( $s['cta_show'] ) ) {
		$cpage = ! empty( $s['contact_page_id'] ) ? (int) $s['contact_page_id'] : 0;
		if ( $cpage && 'publish' === get_post_status( $cpage ) ) {
			$cta_url = add_query_arg( 'artist', $post_id, get_permalink( $cpage ) );
		} elseif ( ! empty( $s['cta_url'] ) ) {
			$cta_url = $s['cta_url'];
		}
	}
	$cta_label = ! empty( $s['cta_label'] ) ? $s['cta_label'] : __( 'Let’s Talk', 'celb-mgmt' );

	// Career filter types and headline numbers.
	$career_types = array();
	$coming       = 0;
	$years        = array();
	foreach ( $career as $row ) {
		if ( ! empty( $row['type'] ) && ! in_array( $row['type'], $career_types, true ) ) {
			$career_types[] = $row['type'];
		}
		if ( ! empty( $row['production'] ) ) {
			$coming++;
		} elseif ( ! empty( $row['year'] ) ) {
			$years[] = (int) $row['year'];
		}
	}
	$since = $years ? min( $years ) : 0;

	// Section nav (only sections that have content).
	$nav = array();
	if ( $has_bio || $bday || $socials ) {
		$nav['overview'] = __( 'Overview', 'celb-mgmt' );
	}
	if ( $career ) {
		$nav['career'] = __( 'Career', 'celb-mgmt' );
	}
	if ( $awards ) {
		$nav['awards'] = __( 'Awards', 'celb-mgmt' );
	}
	if ( $gallery ) {
		$nav['gallery'] = __( 'Gallery', 'celb-mgmt' );
	}
	if ( '' !== $videos ) {
		$nav['videos'] = function_exists( 'celb_videos_title' ) ? celb_videos_title( $post_id ) : __( 'Videos', 'celb-mgmt' );
	}
	if ( $news ) {
		$nav['news'] = __( 'News', 'celb-mgmt' );
	}

	$icon = function ( $name ) {
		$p = array(
			'share'  => '<path d="M12 3v12M7.5 7.5 12 3l4.5 4.5"/><path d="M5 12v7a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-7"/>',
			'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'down'   => '<path d="M12 5v14M6 13l6 6 6-6"/>',
			'cake'   => '<path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0M12 12V8M12 5.5c.8-.8.8-1.7 0-2.5-.8.8-.8 1.7 0 2.5Z"/>',
			'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18"/>',
			'star'   => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
			'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4v3h-4z"/>',
			'film'   => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M8 4.5v15M16 4.5v15M3.5 9.5H8M16 9.5h4.5M3.5 14.5H8M16 14.5h4.5"/>',
			'plus'   => '<path d="M12 5v14M5 12h14"/>',
			'check'  => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		);
		return '<svg class="celb-p-i" viewBox="0 0 24 24" aria-hidden="true">' . ( isset( $p[ $name ] ) ? $p[ $name ] : '' ) . '</svg>';
	};
	?>

	<div class="celb-scope celb-profile<?php echo in_array( $s['theme'], array( 'light', 'dark' ), true ) ? ' is-' . esc_attr( $s['theme'] ) : ''; ?>" data-celb-profile data-name="<?php echo esc_attr( $name ); ?>" data-copied="<?php esc_attr_e( 'Link copied', 'celb-mgmt' ); ?>" data-less="<?php esc_attr_e( 'Show less', 'celb-mgmt' ); ?>">

		<!-- HERO -->
		<header class="celb-p-hero<?php echo $desktop_url ? '' : ' is-plain'; ?>">
			<?php if ( $desktop_url ) : ?>
				<picture class="celb-p-hero-media">
					<?php if ( $mobile_url && $mobile_url !== $desktop_url ) : ?>
						<source media="(max-width: 768px)" srcset="<?php echo esc_url( $mobile_url ); ?>" />
					<?php endif; ?>
					<img src="<?php echo esc_url( $desktop_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" fetchpriority="high" decoding="async" />
				</picture>
			<?php endif; ?>
			<div class="celb-p-hero-shade" aria-hidden="true"></div>
			<div class="celb-p-wrap celb-p-hero-in">
				<?php if ( $cats || $role ) : ?>
					<p class="celb-p-eyebrow"><?php echo esc_html( $cats ? implode( ' · ', $cats ) : $role ); ?></p>
				<?php endif; ?>
				<h1 class="celb-p-name"><?php echo esc_html( $name ); ?></h1>
				<?php
				$facts = array_filter( array( $cats ? $role : '', $nationality ) );
				if ( $facts ) :
					?>
					<p class="celb-p-facts"><?php echo esc_html( implode( '  ·  ', $facts ) ); ?></p>
				<?php endif; ?>
				<div class="celb-p-hero-actions">
					<?php if ( $cta_url ) : ?>
						<a class="celb-p-btn celb-p-btn--light" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?><?php echo $icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					<?php endif; ?>
					<button type="button" class="celb-p-btn celb-p-btn--glass" data-celb-share><?php echo $icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Share', 'celb-mgmt' ); ?></span></button>
					<?php if ( $socials ) : ?>
						<span class="celb-p-hero-social">
							<?php foreach ( $socials as $key => $sv ) : ?>
								<a href="<?php echo esc_url( $sv['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $sv['label'] ); ?>" title="<?php echo esc_attr( $sv['label'] ); ?>"><?php echo celb_social_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $nav ) : ?>
				<a class="celb-p-scroll" href="#<?php echo esc_attr( key( $nav ) ); ?>" aria-label="<?php esc_attr_e( 'Scroll to profile', 'celb-mgmt' ); ?>"><?php echo $icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php endif; ?>
		</header>

		<!-- SECTION NAV -->
		<?php if ( count( $nav ) > 1 ) : ?>
			<nav class="celb-p-nav" aria-label="<?php esc_attr_e( 'Profile sections', 'celb-mgmt' ); ?>" data-celb-nav>
				<div class="celb-p-wrap celb-p-nav-in">
					<span class="celb-p-nav-name"><?php echo esc_html( $name ); ?></span>
					<div class="celb-p-nav-links">
						<?php foreach ( $nav as $id => $label ) : ?>
							<a href="#<?php echo esc_attr( $id ); ?>" data-celb-nav-link="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</div>
					<?php if ( $cta_url ) : ?>
						<a class="celb-p-btn celb-p-btn--ink celb-p-btn--sm celb-p-nav-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					<?php endif; ?>
				</div>
			</nav>
		<?php endif; ?>

		<main class="celb-p-main">

			<!-- OVERVIEW -->
			<?php if ( isset( $nav['overview'] ) ) : ?>
				<section class="celb-p-sec celb-p-overview" id="overview">
					<div class="celb-p-wrap celb-p-overview-grid">
						<div class="celb-p-bio">
							<p class="celb-p-kicker"><?php esc_html_e( 'Biography', 'celb-mgmt' ); ?></p>
							<?php if ( $has_bio ) : ?>
								<div class="celb-p-bio-text" data-celb-clamp>
									<?php echo apply_filters( 'the_content', $bio ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</div>
								<button type="button" class="celb-p-more" data-celb-more hidden><span><?php esc_html_e( 'Read more', 'celb-mgmt' ); ?></span><?php echo $icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<?php else : ?>
								<h2 class="celb-p-lead"><?php echo esc_html( $name ); ?></h2>
							<?php endif; ?>
						</div>

						<aside class="celb-p-glance" aria-label="<?php esc_attr_e( 'At a glance', 'celb-mgmt' ); ?>">
							<p class="celb-p-kicker"><?php esc_html_e( 'At a glance', 'celb-mgmt' ); ?></p>
							<?php if ( $career || $awards ) : ?>
								<div class="celb-p-stats">
									<?php if ( $career ) : ?>
										<div><b><?php echo esc_html( count( $career ) ); ?></b><span><?php echo esc_html( _n( 'Credit', 'Credits', count( $career ), 'celb-mgmt' ) ); ?></span></div>
									<?php endif; ?>
									<?php if ( $awards ) : ?>
										<div><b><?php echo esc_html( count( $awards ) ); ?></b><span><?php echo esc_html( _n( 'Award', 'Awards', count( $awards ), 'celb-mgmt' ) ); ?></span></div>
									<?php endif; ?>
									<?php if ( $since ) : ?>
										<div><b><?php echo esc_html( $since ); ?></b><span><?php esc_html_e( 'On screen since', 'celb-mgmt' ); ?></span></div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<dl class="celb-p-dl">
								<?php if ( $role ) : ?>
									<div><dt><?php echo $icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Known for', 'celb-mgmt' ); ?></dt><dd><?php echo esc_html( $role ); ?></dd></div>
								<?php endif; ?>
								<?php if ( $nationality ) : ?>
									<div><dt><?php echo $icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Nationality', 'celb-mgmt' ); ?></dt><dd><?php echo esc_html( $nationality ); ?></dd></div>
								<?php endif; ?>
								<?php if ( $bday ) : ?>
									<div class="celb-born"<?php echo $bday['is_birthday'] ? ' data-celb-birthday="1"' : ''; ?>><dt><?php echo $icon( 'cake' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Born', 'celb-mgmt' ); ?></dt><dd><?php echo esc_html( $bday['display'] ); ?><?php if ( $bday['is_birthday'] ) : ?><span class="celb-p-bday"><?php esc_html_e( 'Happy Birthday!', 'celb-mgmt' ); ?></span><?php endif; ?></dd></div>
								<?php endif; ?>
								<?php if ( $coming ) : ?>
									<div><dt><?php echo $icon( 'film' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'In production', 'celb-mgmt' ); ?></dt><dd><?php echo esc_html( sprintf( _n( '%d project', '%d projects', $coming, 'celb-mgmt' ), $coming ) ); ?></dd></div>
								<?php endif; ?>
							</dl>
							<?php if ( $socials ) : ?>
								<div class="celb-p-social">
									<?php foreach ( $socials as $key => $sv ) : ?>
										<a href="<?php echo esc_url( $sv['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $sv['label'] ); ?>" title="<?php echo esc_attr( $sv['label'] ); ?>"><?php echo celb_social_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<?php if ( $cta_url ) : ?>
								<a class="celb-p-btn celb-p-btn--ink celb-p-btn--block" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?><?php echo $icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
							<?php endif; ?>
						</aside>
					</div>
				</section>
			<?php endif; ?>

			<!-- CAREER -->
			<?php if ( $career ) : ?>
				<section class="celb-p-sec celb-p-career" id="career" data-celb-career>
					<div class="celb-p-wrap">
						<div class="celb-p-head">
							<h2 class="celb-p-title"><?php esc_html_e( 'Career', 'celb-mgmt' ); ?> <span class="celb-p-count"><?php echo esc_html( count( $career ) ); ?></span></h2>
							<?php if ( count( $career_types ) > 1 ) : ?>
								<div class="celb-p-pills" role="group" aria-label="<?php esc_attr_e( 'Filter by type', 'celb-mgmt' ); ?>">
									<button type="button" class="is-on" data-celb-filter="" aria-pressed="true"><?php esc_html_e( 'All', 'celb-mgmt' ); ?></button>
									<?php foreach ( $career_types as $t ) : ?>
										<button type="button" data-celb-filter="<?php echo esc_attr( $t ); ?>" aria-pressed="false"><?php echo esc_html( $t ); ?></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<ol class="celb-p-credits">
							<?php foreach ( $career as $row ) :
								$is_prod = ! empty( $row['production'] );
								?>
								<li class="celb-p-credit<?php echo $is_prod ? ' is-coming' : ''; ?>" data-type="<?php echo esc_attr( ! empty( $row['type'] ) ? $row['type'] : '' ); ?>">
									<span class="celb-p-credit-year"><?php echo $is_prod ? esc_html__( 'Soon', 'celb-mgmt' ) : esc_html( $row['year'] ? $row['year'] : '—' ); ?></span>
									<span class="celb-p-credit-main">
										<span class="celb-p-credit-project"><?php echo esc_html( $row['project'] ); ?></span>
										<?php if ( ! empty( $row['role'] ) ) : ?>
											<span class="celb-p-credit-role"><?php echo esc_html( sprintf( /* translators: %s: character / role name. */ __( 'as %s', 'celb-mgmt' ), $row['role'] ) ); ?></span>
										<?php endif; ?>
									</span>
									<span class="celb-p-credit-tags">
										<?php if ( $is_prod ) : ?>
											<span class="celb-p-tag celb-p-tag--accent"><?php esc_html_e( 'Coming soon', 'celb-mgmt' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $row['special'] ) ) : ?>
											<span class="celb-p-tag"><?php esc_html_e( 'Special appearance', 'celb-mgmt' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $row['type'] ) ) : ?>
											<span class="celb-p-tag celb-p-tag--quiet"><?php echo esc_html( $row['type'] ); ?></span>
										<?php endif; ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ol>
						<?php if ( count( $career ) > 8 ) : ?>
							<button type="button" class="celb-p-more celb-p-more--center" data-celb-all hidden><span><?php echo esc_html( sprintf( __( 'Show all %d credits', 'celb-mgmt' ), count( $career ) ) ); ?></span><?php echo $icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<!-- AWARDS -->
			<?php if ( $awards ) : ?>
				<section class="celb-p-sec celb-p-awards" id="awards">
					<div class="celb-p-wrap">
						<div class="celb-p-head">
							<h2 class="celb-p-title"><?php esc_html_e( 'Awards', 'celb-mgmt' ); ?> <span class="celb-p-count"><?php echo esc_html( count( $awards ) ); ?></span></h2>
						</div>
						<div class="celb-p-award-grid">
							<?php foreach ( $awards as $a ) : ?>
								<article class="celb-p-award">
									<span class="celb-p-award-ic"><?php echo $icon( 'trophy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
									<?php if ( $a['year'] ) : ?>
										<span class="celb-p-award-year"><?php echo esc_html( $a['year'] ); ?></span>
									<?php endif; ?>
									<h3 class="celb-p-award-title"><?php echo esc_html( $a['title'] ); ?></h3>
									<?php if ( ! empty( $a['festival'] ) ) : ?>
										<p class="celb-p-award-fest"><?php echo esc_html( $a['festival'] ); ?></p>
									<?php endif; ?>
									<?php $am = array_filter( array( isset( $a['project'] ) ? $a['project'] : '', isset( $a['location'] ) ? $a['location'] : '' ) ); ?>
									<?php if ( $am ) : ?>
										<p class="celb-p-award-meta"><?php echo esc_html( implode( ' · ', $am ) ); ?></p>
									<?php endif; ?>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- GALLERY -->
			<?php if ( $gallery ) : ?>
				<section class="celb-p-sec celb-p-gallery" id="gallery">
					<div class="celb-p-wrap">
						<div class="celb-p-head">
							<h2 class="celb-p-title"><?php esc_html_e( 'Gallery', 'celb-mgmt' ); ?> <span class="celb-p-count"><?php echo esc_html( count( $gallery ) ); ?></span></h2>
						</div>
						<div class="celb-gallery-grid celb-p-gal celb-p-gal--<?php echo esc_attr( min( count( $gallery ), 5 ) ); ?>">
							<?php foreach ( $gallery as $i => $gid ) :
								$thumb = wp_get_attachment_image_url( $gid, 'large' );
								$full  = wp_get_attachment_image_url( $gid, 'full' );
								$more  = ( 4 === $i && count( $gallery ) > 5 ) ? count( $gallery ) - 5 : 0;
								?>
								<button type="button" class="celb-gallery-thumb celb-p-gal-item<?php echo $i > 4 ? ' is-extra' : ''; ?>" data-full="<?php echo esc_url( $full ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View photo %d', 'celb-mgmt' ), $i + 1 ) ); ?>">
									<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" decoding="async" />
									<?php if ( $more ) : ?>
										<span class="celb-p-gal-more">+<?php echo esc_html( $more ); ?></span>
									<?php endif; ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- VIDEOS -->
			<?php if ( '' !== $videos ) : ?>
				<div class="celb-p-sec celb-p-videos" id="videos">
					<div class="celb-p-wrap">
						<?php echo str_replace( 'class="celb-section-title"', 'class="celb-p-title"', $videos ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- NEWSROOM -->
			<?php if ( $news ) : ?>
				<section class="celb-p-sec celb-p-news" id="news">
					<div class="celb-p-wrap">
						<div class="celb-p-head">
							<h2 class="celb-p-title"><?php esc_html_e( 'In the news', 'celb-mgmt' ); ?></h2>
						</div>
						<div class="celb-news-list celb-p-news-list">
							<?php foreach ( array_slice( $news, 0, 9 ) as $n ) {
								echo celb_news_item_html( $n, false ); // phpcs:ignore WordPress.Security.EscapeOutput
							} ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- CLOSING CTA -->
			<?php if ( $cta_url ) : ?>
				<section class="celb-p-sec celb-p-closing">
					<div class="celb-p-wrap celb-p-closing-in">
						<div>
							<p class="celb-p-kicker"><?php esc_html_e( 'Bookings & collaborations', 'celb-mgmt' ); ?></p>
							<h2 class="celb-p-closing-title"><?php echo esc_html( sprintf( __( 'Work with %s', 'celb-mgmt' ), $first ) ); ?></h2>
						</div>
						<a class="celb-p-btn celb-p-btn--ink celb-p-btn--lg" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?><?php echo $icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					</div>
				</section>
			<?php endif; ?>

		</main>

		<!-- MOBILE ACTION BAR -->
		<?php if ( $cta_url ) : ?>
			<div class="celb-p-bar" data-celb-bar aria-hidden="true">
				<span class="celb-p-bar-name"><?php echo esc_html( $name ); ?></span>
				<button type="button" class="celb-p-bar-share" data-celb-share aria-label="<?php esc_attr_e( 'Share', 'celb-mgmt' ); ?>" tabindex="-1"><?php echo $icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<a class="celb-p-btn celb-p-btn--ink celb-p-btn--sm" href="<?php echo esc_url( $cta_url ); ?>" tabindex="-1"><?php echo esc_html( $cta_label ); ?></a>
			</div>
		<?php endif; ?>

		<div class="celb-p-toast" data-celb-toast role="status" aria-live="polite"></div>

		<!-- LIGHTBOX -->
		<div class="celb-lightbox" data-celb-lightbox aria-hidden="true">
			<button type="button" class="celb-lb-close" aria-label="<?php esc_attr_e( 'Close', 'celb-mgmt' ); ?>">&times;</button>
			<button type="button" class="celb-lb-prev" aria-label="<?php esc_attr_e( 'Previous', 'celb-mgmt' ); ?>">&#8249;</button>
			<div class="celb-lb-stage"><img class="celb-lb-img" src="" alt="" /></div>
			<button type="button" class="celb-lb-next" aria-label="<?php esc_attr_e( 'Next', 'celb-mgmt' ); ?>">&#8250;</button>
			<span class="celb-lb-hint"><?php esc_html_e( 'Click image to zoom', 'celb-mgmt' ); ?></span>
		</div>

	</div><!-- .celb-profile -->

	<?php
endwhile;

get_footer();
