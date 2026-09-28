<?php
/**
 * Client-facing campaign page. Rendered inside the active theme so it
 * inherits the theme header, footer, fonts and colors.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cp_campaign = isset( $GLOBALS['cp_current_campaign'] ) ? $GLOBALS['cp_current_campaign'] : null;

get_header();
?>

<div class="cp-campaign cp-campaign--full">
<?php if ( ! $cp_campaign ) : ?>

	<div class="cp-notfound">
		<h2><?php esc_html_e( 'Campaign not found', 'hypeit' ); ?></h2>
		<p><?php esc_html_e( 'This link is invalid or has expired.', 'hypeit' ); ?></p>
	</div>

<?php elseif ( CP_Close::is_closed( $cp_campaign->ID ) ) : ?>

	<div class="cp-notfound cp-closed">
		<h2><?php echo esc_html( get_the_title( $cp_campaign ) ); ?></h2>
		<p><?php esc_html_e( 'This campaign is now closed. Thank you — selections can no longer be viewed or changed.', 'hypeit' ); ?></p>
	</div>

<?php
elseif ( CP_Expiry::is_expired( $cp_campaign->ID ) ) :
	$cp_contact = CP_Expiry::contact( $cp_campaign->ID );
	$cp_logo    = CP_Logo::url( $cp_campaign->ID );
	?>

	<div class="cp-expired">
		<?php if ( $cp_logo ) : ?><div class="cp-logo"><img class="cp-logo-img" src="<?php echo esc_url( $cp_logo ); ?>" alt="" /></div><?php endif; ?>
		<span class="cp-expired-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg></span>
		<h1 class="cp-title"><?php echo esc_html( get_the_title( $cp_campaign ) ); ?></h1>
		<h2 class="cp-expired-title"><?php esc_html_e( 'This link has expired', 'hypeit' ); ?></h2>
		<p class="cp-expired-text">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: date and time. */
					__( 'The selection window closed on %s. To see the list again, ask for a fresh link:', 'hypeit' ),
					CP_Expiry::nice( CP_Expiry::get( $cp_campaign->ID ) )
				)
			);
			?>
		</p>
		<?php if ( $cp_contact['name'] || $cp_contact['wa'] || $cp_contact['email'] || $cp_contact['phone'] ) : ?>
			<div class="cp-expired-contact">
				<?php if ( $cp_contact['name'] ) : ?><strong class="cp-expired-name"><?php echo esc_html( $cp_contact['name'] ); ?></strong><?php endif; ?>
				<div class="cp-expired-actions">
					<?php if ( $cp_contact['wa'] ) : ?>
						<a class="cp-btn cp-btn-primary" href="<?php echo esc_url( $cp_contact['wa'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ask on WhatsApp', 'hypeit' ); ?></a>
					<?php endif; ?>
					<?php if ( $cp_contact['phone'] ) : ?>
						<a class="cp-btn" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $cp_contact['phone'] ) ); ?>"><?php esc_html_e( 'Call', 'hypeit' ); ?></a>
					<?php endif; ?>
					<?php if ( $cp_contact['email'] ) : ?>
						<a class="cp-btn" href="<?php echo esc_url( 'mailto:' . $cp_contact['email'] . '?subject=' . rawurlencode( sprintf( /* translators: %s: campaign title. */ __( 'New link for %s', 'hypeit' ), html_entity_decode( get_the_title( $cp_campaign ), ENT_QUOTES ) ) ) ); ?>"><?php esc_html_e( 'Email', 'hypeit' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<p class="cp-expired-text"><?php esc_html_e( 'Please contact the person who sent you this link.', 'hypeit' ); ?></p>
		<?php endif; ?>
	</div>

<?php else : ?>

	<?php
	$campaign_id = (int) $cp_campaign->ID;
	$token       = CP_Frontend::current_token();
	$is_authed   = CP_Auth::is_authed( $campaign_id );
	$logo_url    = CP_Logo::url( $campaign_id );
	$max_guests  = (int) ( get_post_meta( $campaign_id, '_cp_max_guests', true ) ?: 4 );
	$brief       = get_post_meta( $campaign_id, '_cp_brief', true );
	$show_foll   = '1' === get_post_meta( $campaign_id, '_cp_show_followers', true );
	$show_gender = '1' === get_post_meta( $campaign_id, '_cp_show_gender', true );
	$show_tags   = '1' === get_post_meta( $campaign_id, '_cp_show_tags', true );
	$show_loc    = '1' === get_post_meta( $campaign_id, '_cp_show_location', true );
	$show_pop    = '0' !== get_post_meta( $campaign_id, '_cp_show_popularity', true );
	$pop_map     = CP_Insights::popularity_map();
	$ver_map     = CP_Verify::verified_map();
	$photo_map   = CP_Photo::map_by_handle();
	$ver_conf    = CP_Verify::get();
	$ver_only    = ! empty( $ver_conf['require'] ) && 'require_show' === $ver_conf['require'];
	$genders     = CP_Library::genders();
	?>

	<header class="cp-header">
		<?php if ( $logo_url ) : ?>
			<div class="cp-logo"><img class="cp-logo-img" src="<?php echo esc_url( $logo_url ); ?>" alt="" /></div>
		<?php endif; ?>
		<h1 class="cp-title"><?php echo esc_html( get_the_title( $cp_campaign ) ); ?></h1>
	</header>

	<?php
	$cp_deadline = CP_Expiry::get( $campaign_id );
	if ( $cp_deadline ) :
		?>
		<div class="cp-countdown" id="cp-countdown" data-left="<?php echo (int) max( 0, $cp_deadline - time() ); ?>" role="timer" aria-live="off">
			<div class="cp-countdown-text">
				<strong><?php esc_html_e( 'Please finish your selection', 'hypeit' ); ?></strong>
				<span><?php echo esc_html( sprintf( /* translators: %s: date and time. */ __( 'This link expires on %s', 'hypeit' ), CP_Expiry::nice( $cp_deadline ) ) ); ?></span>
			</div>
			<div class="cp-countdown-clock" aria-hidden="true">
				<span><b data-u="d">0</b><small><?php esc_html_e( 'days', 'hypeit' ); ?></small></span>
				<span><b data-u="h">00</b><small><?php esc_html_e( 'hours', 'hypeit' ); ?></small></span>
				<span><b data-u="m">00</b><small><?php esc_html_e( 'min', 'hypeit' ); ?></small></span>
				<span><b data-u="s">00</b><small><?php esc_html_e( 'sec', 'hypeit' ); ?></small></span>
			</div>
		</div>
		<script>
		( function () {
			var el = document.getElementById( 'cp-countdown' );
			if ( ! el ) { return; }
			// Count from the server's remaining seconds so a wrong phone clock can't cheat it.
			var end = Date.now() + parseInt( el.getAttribute( 'data-left' ), 10 ) * 1000;
			function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
			function tick() {
				var left = Math.max( 0, Math.floor( ( end - Date.now() ) / 1000 ) );
				var d = Math.floor( left / 86400 ), h = Math.floor( left % 86400 / 3600 ), m = Math.floor( left % 3600 / 60 ), s = left % 60;
				el.querySelector( '[data-u="d"]' ).textContent = d;
				el.querySelector( '[data-u="h"]' ).textContent = pad( h );
				el.querySelector( '[data-u="m"]' ).textContent = pad( m );
				el.querySelector( '[data-u="s"]' ).textContent = pad( s );
				el.classList.toggle( 'is-soon', left < 86400 );
				el.classList.toggle( 'is-urgent', left < 3600 );
				if ( ! left ) { clearInterval( timer ); setTimeout( function () { window.location.reload(); }, 800 ); }
			}
			var timer = setInterval( tick, 1000 );
			tick();
		} )();
		</script>
	<?php endif; ?>

	<?php if ( ! $is_authed ) : ?>

		<div class="cp-gate">
			<p class="cp-gate-text"><?php esc_html_e( 'This campaign is private. Please enter the password to continue.', 'hypeit' ); ?></p>

			<?php if ( CP_Frontend::$auth_error ) : ?>
				<p class="cp-gate-error"><?php esc_html_e( 'Incorrect password. Please try again.', 'hypeit' ); ?></p>
			<?php endif; ?>

			<form class="cp-gate-form" method="post" action="<?php echo esc_url( CP_Frontend::campaign_url( $token ) ); ?>">
				<?php wp_nonce_field( 'cp_password', 'cp_pw_nonce' ); ?>
				<label class="screen-reader-text" for="cp-password"><?php esc_html_e( 'Password', 'hypeit' ); ?></label>
				<input type="password" id="cp-password" name="cp_password" class="cp-input" autocomplete="off" required placeholder="<?php esc_attr_e( 'Password', 'hypeit' ); ?>" />
				<button type="submit" name="cp_password_submit" value="1" class="cp-btn cp-btn-primary"><?php esc_html_e( 'Enter', 'hypeit' ); ?></button>
			</form>
		</div>

	<?php else : ?>

		<?php if ( $brief ) : ?>
			<div class="cp-brief"><?php echo wp_kses_post( wpautop( $brief ) ); ?></div>
		<?php endif; ?>

		<?php
		$bloggers = CP_DB::visible_bloggers( $campaign_id );
		// Once the client has started choosing, show the ones still to review first.
		$cp_counts = array( 'pending' => 0, 'confirmed' => 0, 'declined' => 0 );
		foreach ( $bloggers as $cp_b ) {
			$cp_st = in_array( $cp_b->status, array( 'confirmed', 'declined' ), true ) ? $cp_b->status : 'pending';
			$cp_counts[ $cp_st ]++;
		}
		$cp_started = ( $cp_counts['confirmed'] + $cp_counts['declined'] ) > 0;
		if ( $cp_started ) {
			$cp_rank = array( 'pending' => 0, 'confirmed' => 1, 'declined' => 2 );
			$cp_pos  = array();
			foreach ( $bloggers as $cp_i => $cp_b ) {
				$cp_pos[ spl_object_id( $cp_b ) ] = $cp_i;
			}
			usort(
				$bloggers,
				static function ( $a, $b ) use ( $cp_rank, $cp_pos ) {
					$ra = $cp_rank[ in_array( $a->status, array( 'confirmed', 'declined' ), true ) ? $a->status : 'pending' ];
					$rb = $cp_rank[ in_array( $b->status, array( 'confirmed', 'declined' ), true ) ? $b->status : 'pending' ];
					return $ra <=> $rb ?: $cp_pos[ spl_object_id( $a ) ] <=> $cp_pos[ spl_object_id( $b ) ];
				}
			);
		}
		$cp_total = count( $bloggers );
		?>

		<?php if ( empty( $bloggers ) ) : ?>
			<p class="cp-empty"><?php esc_html_e( 'No bloggers have been added to this campaign yet.', 'hypeit' ); ?></p>
		<?php else : ?>

			<div class="cp-review" id="cp-review"
				data-total="<?php echo (int) $cp_total; ?>"
				data-left-one="<?php esc_attr_e( '1 left', 'hypeit' ); ?>"
				data-left-many="<?php esc_attr_e( '%d left', 'hypeit' ); ?>"
				data-done="<?php esc_attr_e( 'All reviewed ✓', 'hypeit' ); ?>">
				<p class="cp-review-progress">
					<strong class="cp-review-count"><?php echo esc_html( sprintf( /* translators: 1: reviewed, 2: total. */ __( '%1$d of %2$d reviewed', 'hypeit' ), $cp_total - $cp_counts['pending'], $cp_total ) ); ?></strong>
					<span class="cp-review-left"></span>
				</p>
				<div class="cp-review-bar" aria-hidden="true"><i style="width:<?php echo $cp_total ? (int) round( ( $cp_total - $cp_counts['pending'] ) / $cp_total * 100 ) : 0; ?>%"></i></div>
				<div class="cp-review-filters" role="tablist" data-reviewed-tpl="<?php esc_attr_e( '%1$d of %2$d reviewed', 'hypeit' ); ?>">
					<button type="button" class="is-active" data-f="all"><?php esc_html_e( 'All', 'hypeit' ); ?> <span data-n="all"><?php echo (int) $cp_total; ?></span></button>
					<button type="button" data-f="pending"><?php esc_html_e( 'To review', 'hypeit' ); ?> <span data-n="pending"><?php echo (int) $cp_counts['pending']; ?></span></button>
					<button type="button" data-f="confirmed"><?php esc_html_e( 'Selected', 'hypeit' ); ?> <span data-n="confirmed"><?php echo (int) $cp_counts['confirmed']; ?></span></button>
					<button type="button" data-f="declined"><?php esc_html_e( 'Declined', 'hypeit' ); ?> <span data-n="declined"><?php echo (int) $cp_counts['declined']; ?></span></button>
				</div>
			</div>
			<button type="button" class="cp-next" id="cp-next" hidden><span class="cp-next-label"><?php esc_html_e( 'Next to review', 'hypeit' ); ?></span> <span class="cp-next-arrow" aria-hidden="true">↓</span></button>

			<div class="cp-list" role="list" data-filter="all">
				<?php foreach ( $bloggers as $i => $b ) : ?>
					<?php
					$status = in_array( $b->status, array( 'confirmed', 'declined', 'pending' ), true ) ? $b->status : 'pending';
					$guests = (int) $b->extra_guests;
					?>
					<div class="cp-blogger cp-status-<?php echo esc_attr( $status ); ?>"
						role="listitem"
						data-blogger-id="<?php echo (int) $b->id; ?>"
						data-max="<?php echo (int) $max_guests; ?>"
						data-guests="<?php echo (int) $guests; ?>">

					<?php
					$cp_ig     = $b->ig_account;
					$cp_ig_web = ! empty( $b->ig_url ) ? $b->ig_url : 'https://www.instagram.com/' . rawurlencode( $cp_ig ) . '/';
					$cp_vkey   = strtolower( $cp_ig );
					$cp_vinfo  = isset( $ver_map[ $cp_vkey ] ) ? $ver_map[ $cp_vkey ] : array( 'verified' => false, 'reach' => 0 );
					if ( $ver_only && empty( $cp_vinfo['verified'] ) ) {
						continue;
					}
					?>
						<div class="cp-blogger-main">
							<span class="cp-num"><?php echo (int) ( $i + 1 ); ?></span>
							<?php
							$cp_photo = isset( $photo_map[ $cp_vkey ] ) ? $photo_map[ $cp_vkey ] : '';
							if ( $cp_photo ) :
								?>
								<button type="button" class="cp-photo" data-full="<?php echo esc_url( str_replace( '-s.jpg', '.jpg', $cp_photo ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: username. */ __( 'View @%s photo', 'hypeit' ), $cp_ig ) ); ?>">
									<img src="<?php echo esc_url( $cp_photo ); ?>" alt="" loading="lazy" width="48" height="48" />
								</button>
							<?php else : ?>
								<span class="cp-photo cp-photo--empty" aria-hidden="true"><?php echo esc_html( strtoupper( substr( preg_replace( '/[^a-z0-9]/i', '', $cp_ig ), 0, 2 ) ) ); ?></span>
							<?php endif; ?>
							<span class="cp-account-wrap">
								<a class="cp-account" href="<?php echo esc_url( $cp_ig_web ); ?>" data-username="<?php echo esc_attr( $cp_ig ); ?>" target="_blank" rel="noopener noreferrer">@<?php echo esc_html( $cp_ig ); ?><?php if ( ! empty( $cp_vinfo['verified'] ) ) : ?> <span class="cp-verified" title="<?php esc_attr_e( 'Verified', 'hypeit' ); ?>">✓</span><?php endif; ?></a>
								<?php
								if ( $show_pop ) {
									$cp_pkey = strtolower( $cp_ig );
									$cp_lab  = isset( $pop_map[ $cp_pkey ] ) ? CP_Insights::label( $pop_map[ $cp_pkey ] ) : array( 'key' => '', 'label' => '' );
									if ( ! empty( $cp_lab['label'] ) ) :
										?>
										<span class="cp-pop cp-pop-<?php echo esc_attr( $cp_lab['key'] ); ?>"><?php echo esc_html( $cp_lab['label'] ); ?></span>
										<?php
									endif;
								}
								?>
								<?php
								$meta_bits = array();
								if ( $show_foll && (int) $b->followers > 0 ) {
									$meta_bits[] = esc_html( number_format_i18n( (int) $b->followers ) ) . ' ' . esc_html__( 'followers', 'hypeit' );
								}
								if ( $show_foll && ! empty( $cp_vinfo['reach'] ) ) {
									$meta_bits[] = esc_html( number_format_i18n( (int) $cp_vinfo['reach'] ) ) . ' ' . esc_html__( 'reach', 'hypeit' );
								}
								if ( $show_gender && ! empty( $b->gender ) && isset( $genders[ $b->gender ] ) ) {
									$meta_bits[] = esc_html( $genders[ $b->gender ] );
								}
								if ( $show_tags && ! empty( $b->tags ) ) {
									$meta_bits[] = esc_html( $b->tags );
								}
								if ( $show_loc && ! empty( $b->city ) ) {
									$loc = trim( $b->city );
									$meta_bits[] = esc_html( $loc );
								}
								if ( ! empty( $meta_bits ) ) :
									?>
									<span class="cp-account-meta"><?php echo implode( ' · ', $meta_bits ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endif; ?>
							</span>
							<span class="cp-state-badge" aria-live="polite"></span>
						</div>

						<div class="cp-decision">
							<button type="button" class="cp-btn cp-confirm" data-action="confirm"><?php esc_html_e( 'Confirm', 'hypeit' ); ?></button>
							<button type="button" class="cp-btn cp-decline" data-action="decline"><?php esc_html_e( 'Decline', 'hypeit' ); ?></button>
						</div>

						<div class="cp-guests"<?php echo ( 'confirmed' === $status ) ? '' : ' hidden'; ?>>
							<span class="cp-guests-label"><?php esc_html_e( 'Additional guests:', 'hypeit' ); ?></span>
							<div class="cp-guest-options">
								<?php for ( $n = 0; $n <= $max_guests; $n++ ) : ?>
									<button type="button"
										class="cp-guest<?php echo ( $n === $guests ) ? ' is-active' : ''; ?>"
										data-guests="<?php echo (int) $n; ?>">
										<?php echo 0 === $n ? esc_html__( 'Blogger only', 'hypeit' ) : esc_html( '+' . $n ); ?>
									</button>
								<?php endfor; ?>
							</div>
							<p class="cp-total" aria-live="polite"></p>
						</div>

						<div class="cp-feedback" aria-live="polite"></div>
					</div>
				<?php endforeach; ?>
			</div>

		<?php endif; ?>

	<?php endif; ?>

<?php endif; ?>
</div>

<?php
get_footer();
