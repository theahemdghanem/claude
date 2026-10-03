<?php
/**
 * Single Celebrity profile template.
 * Loaded via template_include for the `celebrity` post type.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();

	$name        = get_the_title();
	$subtitle    = celb_subtitle( $post_id );
	$desktop_id  = (int) get_post_meta( $post_id, '_celb_hero_desktop', true );
	$mobile_id   = (int) get_post_meta( $post_id, '_celb_hero_mobile', true );
	$desktop_url = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : get_the_post_thumbnail_url( $post_id, 'full' );
	$mobile_url  = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'large' ) : $desktop_url;

	$socials = celb_get_socials( $post_id );
	$career  = celb_get_career( $post_id );
	$awards  = celb_get_awards( $post_id );
	$gallery = celb_get_gallery( $post_id );
	$bday    = celb_get_birthday( $post_id );
	$bio     = celb_get_bio( $post_id );
	$cta     = celb_get_settings();
	$news    = celb_get_news( $post_id );
	?>

	<div class="celb-scope celb-single">

		<!-- HERO -->
		<section class="celb-hero">
			<?php if ( $desktop_url ) : ?>
				<picture class="celb-hero-bg">
					<?php if ( $mobile_url ) : ?>
						<source media="(max-width: 768px)" srcset="<?php echo esc_url( $mobile_url ); ?>" />
					<?php endif; ?>
					<img src="<?php echo esc_url( $desktop_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
				</picture>
			<?php endif; ?>
			<div class="celb-hero-gradient"></div>
			<div class="celb-hero-content">
				<h1 class="celb-hero-name"><?php echo esc_html( $name ); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p class="celb-hero-sub"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<div class="celb-body">

			<!-- BORN / BIRTHDAY -->
			<?php if ( $bday ) : ?>
				<div class="celb-born"<?php echo $bday['is_birthday'] ? ' data-celb-birthday="1"' : ''; ?>>
					<span class="celb-born-label"><?php esc_html_e( 'Born', 'celb-mgmt' ); ?></span>
					<span class="celb-born-date"><?php echo esc_html( $bday['display'] ); ?></span>
					<span class="celb-bday-greeting"><?php esc_html_e( 'Happy Birthday', 'celb-mgmt' ); ?></span>
				</div>
			<?php endif; ?>

			<!-- BIOGRAPHY -->
			<?php if ( '' !== trim( wp_strip_all_tags( $bio ) ) ) : ?>
				<section class="celb-section celb-bio">
					<h2 class="celb-section-title"><?php esc_html_e( 'Biography', 'celb-mgmt' ); ?></h2>
					<div class="celb-bio-text"><?php echo apply_filters( 'the_content', $bio ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</section>
			<?php endif; ?>

			<!-- SOCIAL -->
			<?php if ( ! empty( $socials ) ) : ?>
				<section class="celb-section celb-social-row">
					<?php foreach ( $socials as $key => $s ) : ?>
						<a class="celb-social-link" href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $s['label'] ); ?>" title="<?php echo esc_attr( $s['label'] ); ?>">
							<?php echo celb_social_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</a>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<!-- CAREER HISTORY -->
			<?php if ( ! empty( $career ) ) : ?>
				<section class="celb-section celb-career celb-collapsible">
					<button type="button" class="celb-section-title celb-toggle" aria-expanded="false" aria-controls="celb-career-<?php echo esc_attr( $post_id ); ?>">
						<span><?php esc_html_e( 'Career History', 'celb-mgmt' ); ?></span>
						<span class="celb-toggle-icon" aria-hidden="true"></span>
					</button>
					<div class="celb-collapse-body" id="celb-career-<?php echo esc_attr( $post_id ); ?>">
						<div class="celb-collapse-inner">
							<?php
							$career_types = array();
							foreach ( $career as $row ) {
								if ( ! empty( $row['type'] ) && ! in_array( $row['type'], $career_types, true ) ) {
									$career_types[] = $row['type'];
								}
							}
							?>
							<?php if ( count( $career_types ) > 1 ) : ?>
								<div class="celb-career-filter" role="tablist" aria-label="<?php esc_attr_e( 'Filter roles by type', 'celb-mgmt' ); ?>">
									<button type="button" class="celb-filter-pill is-active" data-filter="" aria-pressed="true"><?php esc_html_e( 'All', 'celb-mgmt' ); ?></button>
									<?php foreach ( $career_types as $t ) : ?>
										<button type="button" class="celb-filter-pill" data-filter="<?php echo esc_attr( $t ); ?>" aria-pressed="false"><?php echo esc_html( $t ); ?></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="celb-table">
								<?php foreach ( $career as $row ) : ?>
									<?php $is_prod = ! empty( $row['production'] ); ?>
									<div class="celb-table-row<?php echo $is_prod ? ' celb-row-production' : ''; ?>" data-type="<?php echo esc_attr( ! empty( $row['type'] ) ? $row['type'] : '' ); ?>">
										<span class="celb-col-project"><?php echo esc_html( $row['project'] ); ?></span>
										<span class="celb-col-role">
											<?php echo esc_html( $row['role'] ); ?>
											<?php
											$cmeta = array();
											if ( ! empty( $row['type'] ) ) {
												$cmeta[] = $row['type'];
											}
											if ( ! empty( $row['special'] ) ) {
												$cmeta[] = __( 'Special Appearance', 'celb-mgmt' );
											}
											if ( $cmeta ) :
												?>
												<span class="celb-col-meta"><?php echo esc_html( implode( ' · ', $cmeta ) ); ?></span>
											<?php endif; ?>
										</span>
										<span class="celb-col-year">
											<?php if ( $is_prod ) : ?>
												<span class="celb-coming-soon"><?php esc_html_e( 'Coming Soon', 'celb-mgmt' ); ?></span>
											<?php else : ?>
												<?php echo $row['year'] ? esc_html( $row['year'] ) : ''; ?>
											<?php endif; ?>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- AWARDS -->
			<?php if ( ! empty( $awards ) ) : ?>
				<section class="celb-section celb-awards celb-collapsible">
					<button type="button" class="celb-section-title celb-toggle" aria-expanded="false" aria-controls="celb-awards-<?php echo esc_attr( $post_id ); ?>">
						<span><?php esc_html_e( 'Awards', 'celb-mgmt' ); ?></span>
						<span class="celb-toggle-icon" aria-hidden="true"></span>
					</button>
					<div class="celb-collapse-body" id="celb-awards-<?php echo esc_attr( $post_id ); ?>">
						<div class="celb-collapse-inner">
							<div class="celb-awards-list">
								<?php foreach ( $awards as $a ) : ?>
									<div class="celb-award">
										<?php if ( $a['year'] ) : ?>
											<span class="celb-award-year"><?php echo esc_html( $a['year'] ); ?></span>
										<?php endif; ?>
										<div class="celb-award-detail">
											<span class="celb-award-title"><?php echo esc_html( $a['title'] ); ?></span>
											<span class="celb-award-meta">
												<?php
												$meta = array_filter( array(
													$a['festival'],
													$a['project'],
													$a['location'],
												) );
												echo esc_html( implode( ' · ', $meta ) );
												?>
											</span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- NEWSROOM -->
			<?php if ( ! empty( $news ) ) : ?>
				<section class="celb-section celb-newsroom celb-collapsible">
					<button type="button" class="celb-section-title celb-toggle" aria-expanded="false" aria-controls="celb-newsroom-<?php echo esc_attr( $post_id ); ?>">
						<span><?php esc_html_e( 'Newsroom', 'celb-mgmt' ); ?></span>
						<span class="celb-toggle-icon" aria-hidden="true"></span>
					</button>
					<div class="celb-collapse-body" id="celb-newsroom-<?php echo esc_attr( $post_id ); ?>">
						<div class="celb-collapse-inner">
							<div class="celb-news-list">
								<?php foreach ( $news as $n ) {
									echo celb_news_item_html( $n, false ); // phpcs:ignore WordPress.Security.EscapeOutput
								} ?>
							</div>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<!-- GALLERY -->
			<?php if ( ! empty( $gallery ) ) : ?>
				<section class="celb-section celb-gallery">
					<h2 class="celb-section-title"><?php esc_html_e( 'Gallery', 'celb-mgmt' ); ?></h2>
					<div class="celb-gallery-grid">
						<?php foreach ( $gallery as $gid ) :
							$thumb = wp_get_attachment_image_url( $gid, 'large' );
							$full  = wp_get_attachment_image_url( $gid, 'full' );
							if ( ! $thumb ) {
								continue;
							} ?>
							<button type="button" class="celb-gallery-thumb" data-full="<?php echo esc_url( $full ); ?>" style="background-image:url(<?php echo esc_url( $thumb ); ?>)" aria-label="<?php esc_attr_e( 'View image', 'celb-mgmt' ); ?>"></button>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			if ( function_exists( 'celb_video_section_html' ) ) {
				echo celb_video_section_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
			<?php if ( ! empty( $cta['cta_show'] ) && ! empty( $cta['cta_url'] ) ) : ?>
				<section class="celb-section celb-cta">
					<a class="celb-cta-btn" href="<?php echo esc_url( $cta['cta_url'] ); ?>"><?php echo esc_html( $cta['cta_label'] ); ?></a>
				</section>
			<?php endif; ?>

		</div><!-- .celb-body -->

		<!-- LIGHTBOX -->
		<div class="celb-lightbox" data-celb-lightbox aria-hidden="true">
			<button type="button" class="celb-lb-close" aria-label="Close">&times;</button>
			<button type="button" class="celb-lb-prev" aria-label="Previous">&#8249;</button>
			<div class="celb-lb-stage"><img class="celb-lb-img" src="" alt="" /></div>
			<button type="button" class="celb-lb-next" aria-label="Next">&#8250;</button>
			<span class="celb-lb-hint"><?php esc_html_e( 'Click image to zoom', 'celb-mgmt' ); ?></span>
		</div>

	</div><!-- .celb-single -->

	<?php
endwhile;

get_footer();
