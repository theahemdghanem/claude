<?php
/**
 * Single News Article template (`celeb_news`).
 * Full-width hero image with a bottom gradient and the title overlaid (date +
 * clickable celebrity by-line beneath it). Body, gallery (lightbox) and links
 * follow in a readable centered column. Direction (RTL/LTR) is auto-detected.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$nid = get_the_ID();
	list( $hero_d, $hero_m ) = celb_news_hero_pair( $nid, 'full' );
	$loc     = get_post_meta( $nid, '_news_location', true );
	$cid     = (int) get_post_meta( $nid, '_news_celebrity', true );
	$gallery = get_post_meta( $nid, '_news_gallery', true );
	$gallery = is_array( $gallery ) ? array_filter( array_map( 'absint', $gallery ) ) : array();
	$links   = get_post_meta( $nid, '_news_links', true );
	$links   = is_array( $links ) ? $links : array();

	$title     = get_the_title();
	$title_dir = celb_is_rtl_text( $title ) ? 'rtl' : 'ltr';
	$body_dir  = celb_is_rtl_text( wp_strip_all_tags( get_the_content() ) ) ? 'rtl' : 'ltr';
	$title_ar  = (string) get_post_meta( $nid, '_news_title_ar', true );
	$body_ar   = (string) get_post_meta( $nid, '_news_body_ar', true );
	$has_ar    = ( '' !== trim( $title_ar ) || '' !== trim( wp_strip_all_tags( $body_ar ) ) );
	$zip_url   = add_query_arg( array( 'action' => 'celb_news_zip', 'news' => $nid ), admin_url( 'admin-ajax.php' ) );
	$hero_src  = $hero_d ? $hero_d : $hero_m;

	ob_start(); ?>
	<div class="celb-article-infomain">
		<div class="celb-article-meta" dir="<?php echo esc_attr( $title_dir ); ?>">
			<span class="celb-article-date"><?php echo esc_html( get_the_date() ); ?></span>
			<?php if ( $loc ) : ?><span class="celb-article-loc"><?php echo esc_html( $loc ); ?></span><?php endif; ?>
		</div>
		<?php if ( $cid ) : ?><a class="celb-article-celeb" href="<?php echo esc_url( get_permalink( $cid ) ); ?>"><?php echo esc_html( get_the_title( $cid ) ); ?></a><?php endif; ?>
	</div>
	<?php $info_html = ob_get_clean();

	ob_start(); if ( $has_ar ) : ?>
	<div class="celb-article-lang" role="group" aria-label="Language">
		<button type="button" class="celb-news-lang-btn is-active" data-lang="en">EN</button>
		<button type="button" class="celb-news-lang-btn" data-lang="ar" lang="ar">ع</button>
	</div>
	<?php endif; $switch_html = ob_get_clean();
	?>
	<div class="celb-scope celb-article">
		<article class="celb-article-inner">

			<header class="celb-article-hero">
				<?php if ( $hero_src ) : ?>
					<picture class="celb-article-hero-pic">
						<?php if ( $hero_m && $hero_m !== $hero_d ) : ?>
							<source media="(max-width:600px)" srcset="<?php echo esc_url( $hero_m ); ?>" />
						<?php endif; ?>
						<img class="celb-article-hero-img" src="<?php echo esc_url( $hero_src ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
					</picture>
				<?php endif; ?>
				<div class="celb-article-hero-grad"></div>
				<div class="celb-article-hero-text">
					<h1 class="celb-article-title" data-lang-en dir="<?php echo esc_attr( $title_dir ); ?>"><?php echo esc_html( $title ); ?></h1>
					<?php if ( $has_ar ) : ?>
						<h1 class="celb-article-title" data-lang-ar dir="rtl" hidden><?php echo esc_html( '' !== $title_ar ? $title_ar : $title ); ?></h1>
					<?php endif; ?>
					<div class="celb-article-heroinfo"><?php echo $info_html . $switch_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</div>
			</header>

			<div class="celb-article-content">

				<div class="celb-article-body" data-lang-en dir="<?php echo esc_attr( $body_dir ); ?>">
					<?php the_content(); ?>
				</div>
				<?php if ( $has_ar ) : ?>
					<div class="celb-article-body" data-lang-ar dir="rtl" hidden>
						<?php echo apply_filters( 'the_content', $body_ar ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $gallery ) ) : ?>
					<div class="celb-article-gallery celb-news-gallery">
						<?php foreach ( $gallery as $gid ) :
							$gt = wp_get_attachment_image_url( $gid, 'large' );
							$gf = wp_get_attachment_image_url( $gid, 'full' );
							if ( ! $gt ) {
								continue;
							}
							?>
							<button type="button" class="celb-article-gthumb celb-news-gthumb" data-full="<?php echo esc_url( $gf ); ?>" style="background-image:url(<?php echo esc_url( $gt ); ?>)" aria-label="<?php esc_attr_e( 'View image', 'celb-mgmt' ); ?>"></button>
						<?php endforeach; ?>
					</div>
					<div class="celb-article-dlwrap">
						<a class="celb-article-dl" href="<?php echo esc_url( $zip_url ); ?>"><?php esc_html_e( 'Download all media', 'celb-mgmt' ); ?></a>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $links ) ) : ?>
					<div class="celb-article-links">
						<?php foreach ( $links as $l ) :
							if ( empty( $l['url'] ) ) {
								continue;
							}
							$label = ! empty( $l['label'] ) ? $l['label'] : $l['url'];
							?>
							<a class="celb-article-link" href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo celb_news_link_icon( $l['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php echo esc_html( $label ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div>

			<?php if ( ! empty( $gallery ) ) : ?>
				<div class="celb-lightbox" data-celb-lightbox aria-hidden="true">
					<button type="button" class="celb-lb-close" aria-label="<?php esc_attr_e( 'Close', 'celb-mgmt' ); ?>">&times;</button>
					<button type="button" class="celb-lb-prev" aria-label="<?php esc_attr_e( 'Previous', 'celb-mgmt' ); ?>">&#8249;</button>
					<div class="celb-lb-stage"><img class="celb-lb-img" src="" alt="" /></div>
					<button type="button" class="celb-lb-next" aria-label="<?php esc_attr_e( 'Next', 'celb-mgmt' ); ?>">&#8250;</button>
				</div>
			<?php endif; ?>

		</article>
	</div>
	<?php
endwhile;

get_footer();
