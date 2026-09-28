<?php
/**
 * Public onboarding form template. Rendered inside the active theme so it
 * inherits fonts, colors, buttons and spacing.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cp_ob      = CP_Onboarding::get();
$cp_state   = CP_Onboarding::$state;
$cp_values  = $cp_state['values'];
$cp_errors  = $cp_state['errors'];
$cp_success = isset( $_GET['cp_ob'] ) && 'success' === $_GET['cp_ob']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/*
 * Account-type notice + step-by-step guides (used on the form and the thank-you page).
 */
$cp_account_note = static function ( $context = 'form' ) {
	?>
	<div class="cp-ob-acct">
		<p class="cp-ob-acct-title"><?php echo esc_html( 'form' === $context ? __( 'Use a public Creator account', 'hypeit' ) : __( 'Get selected for more campaigns', 'hypeit' ) ); ?></p>
		<ul class="cp-ob-acct-list">
			<li class="is-good">
				<span class="cp-ob-acct-ico" aria-hidden="true">✓</span>
				<span><strong><?php esc_html_e( 'Creator — recommended', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Full music library, auto-verified, more campaigns', 'hypeit' ); ?></small></span>
			</li>
			<li>
				<span class="cp-ob-acct-ico" aria-hidden="true">↻</span>
				<span><strong><?php esc_html_e( 'Business or personal? Switch to Creator', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Free, takes a minute — Business has limited music', 'hypeit' ); ?></small></span>
			</li>
			<li class="is-bad">
				<span class="cp-ob-acct-ico" aria-hidden="true">✕</span>
				<span><strong><?php esc_html_e( 'Private account? Please don’t apply', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Private accounts are blocked — make it public first', 'hypeit' ); ?></small></span>
			</li>
		</ul>

		<details class="cp-ob-guide">
			<summary><?php esc_html_e( 'Show me how', 'hypeit' ); ?></summary>
			<div class="cp-ob-guide-body">
				<p class="cp-ob-guide-lead"><?php esc_html_e( 'In Instagram: Profile → ☰ menu → Settings and activity, then:', 'hypeit' ); ?></p>
				<dl>
					<dt><?php esc_html_e( 'Check your type', 'hypeit' ); ?></dt>
					<dd><?php esc_html_e( 'Account type and tools. “Switch to professional account” = personal. No “Switch to creator account” option = you’re already Creator ✓', 'hypeit' ); ?></dd>
					<dt><?php esc_html_e( 'Personal → Creator', 'hypeit' ); ?></dt>
					<dd><?php esc_html_e( 'Account type and tools → Switch to professional account → pick a category → Creator', 'hypeit' ); ?></dd>
					<dt><?php esc_html_e( 'Business → Creator', 'hypeit' ); ?></dt>
					<dd><?php esc_html_e( 'Account type and tools → Switch account type → Switch to creator account', 'hypeit' ); ?></dd>
					<dt><?php esc_html_e( 'Private → Public', 'hypeit' ); ?></dt>
					<dd><?php esc_html_e( 'Account privacy → turn off “Private account”', 'hypeit' ); ?></dd>
				</dl>
			</div>
		</details>
	</div>
	<?php
};

$cp_val = static function ( $key, $default = '' ) use ( $cp_values ) {
	return isset( $cp_values[ $key ] ) ? $cp_values[ $key ] : $default;
};

get_header();
?>

<div class="cp-campaign cp-campaign--full cp-onboard">
<?php if ( $cp_success ) : ?>

	<div class="cp-ob-success">
		<h1 class="cp-title"><?php esc_html_e( 'Thank you!', 'hypeit' ); ?></h1>
		<p><?php esc_html_e( 'Your information has been submitted successfully. Our team will contact you whenever a suitable campaign becomes available.', 'hypeit' ); ?></p>

		<?php
		if ( ! empty( $cp_ob['follow_on'] ) ) :
			$cp_fu = CP_Onboarding::follow_user();
			?>
			<div class="cp-ob-follow">
				<p class="cp-ob-follow-kicker"><?php esc_html_e( 'One last step', 'hypeit' ); ?></p>
				<h2 class="cp-ob-follow-title"><?php esc_html_e( 'Follow us on Instagram', 'hypeit' ); ?></h2>
				<p class="cp-ob-follow-text"><?php esc_html_e( 'Stay close to new campaigns, events and announcements — follow our account so you never miss an opportunity.', 'hypeit' ); ?></p>
				<a class="cp-ob-follow-btn" href="<?php echo esc_url( 'https://www.instagram.com/' . rawurlencode( $cp_fu ) . '/' ); ?>" target="_blank" rel="noopener">
					<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
					<span><?php echo esc_html( sprintf( /* translators: %s: username. */ __( 'Follow @%s', 'hypeit' ), $cp_fu ) ); ?></span>
				</a>
			</div>
		<?php endif; ?>

		<?php
		$cp_bid = isset( $_GET['b'] ) ? absint( $_GET['b'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cp_tok = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cp_vok = $cp_bid && hash_equals( CP_Verify::token( $cp_bid ), $cp_tok );
		$cp_vconf = CP_Verify::get();
		$cp_vstat = isset( $_GET['cp_vstat'] ) ? sanitize_key( wp_unslash( $_GET['cp_vstat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>

		<?php if ( $cp_vok && 'success' === $cp_vstat ) : ?>
			<p class="cp-ob-verified">✓ <?php esc_html_e( 'Your Instagram account has been verified. Thank you!', 'hypeit' ); ?></p>
		<?php elseif ( $cp_vok && 'mismatch' === $cp_vstat ) : ?>
			<p class="cp-gate-error"><?php esc_html_e( 'The Instagram account you logged in with does not match the username you entered.', 'hypeit' ); ?></p>
		<?php elseif ( $cp_vok && 'notpro' === $cp_vstat ) : ?>
			<p class="cp-gate-error"><?php esc_html_e( 'That account is not a Professional (Business/Creator) Instagram account, so it can’t be verified automatically. Our team will follow up.', 'hypeit' ); ?></p>
		<?php elseif ( $cp_vok && 'error' === $cp_vstat ) : ?>
			<p class="cp-gate-error"><?php esc_html_e( 'Verification could not be completed. Please try again later.', 'hypeit' ); ?></p>
		<?php endif; ?>

		<?php
		$cp_igs = CP_IGSync::get();
		if ( $cp_vok && CP_IGSync::ready() && ! empty( $cp_igs['bio_verify'] ) && ! CP_Verify::is_verified( $cp_bid ) ) :
			?>
			<?php
			$cp_code   = CP_Verify::code( $cp_bid );
			$cp_istat  = (string) get_post_meta( $cp_bid, '_cp_ig_status', true );
			?>
			<div class="cp-ob-vcard" id="cp_vcard"
				data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-bid="<?php echo (int) $cp_bid; ?>"
				data-sig="<?php echo esc_attr( CP_IGSync::verify_sig( $cp_bid ) ); ?>"
				data-checking="<?php esc_attr_e( 'Checking your bio…', 'hypeit' ); ?>"
				data-ok="<?php esc_attr_e( '✓ You’re verified! You can remove the code from your bio now — your badge stays.', 'hypeit' ); ?>"
				data-missing="<?php esc_attr_e( 'We can’t see the code in your bio yet. Make sure you saved it, wait a minute, then try again. We also keep checking automatically.', 'hypeit' ); ?>"
				data-personal="<?php esc_attr_e( 'We can’t read your account yet — switch to a free Creator account first (step 1 below), then try again.', 'hypeit' ); ?>"
				data-busy="<?php esc_attr_e( 'Please try again in a minute — we also keep checking automatically.', 'hypeit' ); ?>">
				<span class="cp-ob-vtag"><?php esc_html_e( 'Highly recommended', 'hypeit' ); ?></span>
				<h2 class="cp-ob-vtitle"><?php esc_html_e( 'Get verified — get picked more', 'hypeit' ); ?></h2>
				<p><?php esc_html_e( 'Verified bloggers show a ✓ badge on the profiles our clients review. Brands trust verified accounts, so you’re more likely to be selected for campaigns. It takes about a minute.', 'hypeit' ); ?></p>

				<div class="cp-ob-vcode">
					<span><?php esc_html_e( 'Your code', 'hypeit' ); ?></span>
					<code><?php echo esc_html( $cp_code ); ?></code>
					<button type="button" class="cp-btn cp-ob-copy" data-copy="<?php echo esc_attr( $cp_code ); ?>" data-done="<?php esc_attr_e( 'Copied ✓', 'hypeit' ); ?>"><?php esc_html_e( 'Copy code', 'hypeit' ); ?></button>
				</div>

				<ol class="cp-ob-steps">
					<li class="<?php echo 'personal' === $cp_istat ? 'is-important' : ''; ?>"><strong><?php esc_html_e( 'Make sure you’re on a public Creator account', 'hypeit' ); ?></strong> <?php echo 'personal' === $cp_istat ? esc_html__( '— we couldn’t read your account yet, so please switch first.', 'hypeit' ) : ''; ?> <?php esc_html_e( '(guides below).', 'hypeit' ); ?></li>
					<li><?php esc_html_e( 'Copy your code above.', 'hypeit' ); ?></li>
					<li><?php esc_html_e( 'Open Instagram and go to your profile.', 'hypeit' ); ?></li>
					<li><?php esc_html_e( 'Tap “Edit profile”, then tap “Bio”.', 'hypeit' ); ?></li>
					<li><?php esc_html_e( 'Paste the code anywhere in your bio (the end is fine) and tap “Done” / “Save”.', 'hypeit' ); ?></li>
					<li><?php esc_html_e( 'Come back here and tap the button below.', 'hypeit' ); ?></li>
				</ol>

				<p class="cp-ob-vactions">
					<button type="button" class="cp-btn cp-btn-primary" id="cp_vcheck"><?php esc_html_e( 'I’ve added it — check now', 'hypeit' ); ?></button>
				</p>
				<p class="cp-ob-vstatus" id="cp_vstatus" aria-live="polite"></p>

				<p class="cp-ob-vnote"><?php esc_html_e( 'One-time step: once you’re verified you can remove the code — your badge stays. Your account must be a Business or Creator account. Closed this page? We still check your bio automatically every day.', 'hypeit' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $cp_vok ) { $cp_account_note( 'thanks' ); } ?>

		<?php if ( $cp_vok && 'success' !== $cp_vstat && ! CP_IGSync::ready() && ( CP_Verify::meta_ready() || ! empty( $cp_vconf['code_enabled'] ) ) ) : ?>
			<div class="cp-ob-verify">
				<h2 class="cp-ob-section"><?php esc_html_e( 'Verify your account (optional)', 'hypeit' ); ?></h2>
				<?php if ( CP_Verify::meta_ready() && ! CP_Onboarding::is_in_app_browser() ) : ?>
					<p><?php esc_html_e( 'Have a Professional (Business/Creator) account? Verify instantly and we’ll keep your followers and reach accurate.', 'hypeit' ); ?></p>
					<p><a class="cp-btn cp-btn-primary" href="<?php echo esc_url( CP_Verify::start_url( $cp_bid ) ); ?>"><?php esc_html_e( 'Verify with Instagram', 'hypeit' ); ?></a></p>
				<?php elseif ( CP_Verify::meta_ready() && CP_Onboarding::is_in_app_browser() ) : ?>
					<p class="description"><?php esc_html_e( 'To verify instantly with Instagram, open this page in your browser (••• or Aa menu → Open in browser). Or use the code below.', 'hypeit' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $cp_vconf['code_enabled'] ) ) : ?>
					<p style="margin-top:1rem;"><?php esc_html_e( 'Or add this code to your Instagram bio or a story, and our team will verify you:', 'hypeit' ); ?></p>
					<p><code style="font-size:1.1em;"><?php echo esc_html( CP_Verify::code( $cp_bid ) ); ?></code></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

<?php else : ?>

	<?php if ( ! empty( $cp_ob['intro'] ) ) : ?>
		<div class="cp-brief cp-ob-intro"><?php echo wp_kses_post( wpautop( $cp_ob['intro'] ) ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $cp_errors ) ) : ?>
		<div class="cp-ob-errors">
			<ul>
				<?php foreach ( $cp_errors as $err ) : ?>
					<li><?php echo esc_html( $err ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php $cp_account_note( 'form' ); ?>

	<form class="cp-ob-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( CP_Onboarding::url() ); ?>">
		<?php wp_nonce_field( 'cp_onboard', 'cp_ob_nonce' ); ?>

		<h2 class="cp-ob-section"><?php esc_html_e( 'Profile photo', 'hypeit' ); ?> *</h2>
		<?php
		$cp_photo_tok = (string) $cp_val( 'photo_token' );
		$cp_photo_src = $cp_photo_tok ? CP_Photo::temp_url( $cp_photo_tok ) : '';
		?>
		<div class="cp-ob-photo">
			<label class="cp-ob-photo-pick" for="cp_photo">
				<span class="cp-ob-photo-circle" id="cp_photo_preview"<?php echo $cp_photo_src ? ' style="background-image:url(' . esc_url( $cp_photo_src ) . ')"' : ''; ?>>
					<?php if ( ! $cp_photo_src ) : ?>
						<svg viewBox="0 0 24 24" width="30" height="30" aria-hidden="true"><path d="M4 8h3l2-2.5h6L17 8h3v11H4z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>
					<?php endif; ?>
				</span>
				<span class="cp-ob-photo-text">
					<strong id="cp_photo_label" data-change="<?php esc_attr_e( 'Change photo', 'hypeit' ); ?>"><?php echo $cp_photo_src ? esc_html__( 'Change photo', 'hypeit' ) : esc_html__( 'Add your photo', 'hypeit' ); ?></strong>
					<small><?php esc_html_e( 'A clear photo of your face. JPG, PNG or WebP.', 'hypeit' ); ?></small>
				</span>
			</label>
			<input type="file" id="cp_photo" name="cp_photo" accept="image/jpeg,image/png,image/webp,image/*" class="cp-ob-photo-input" <?php echo $cp_photo_src ? '' : 'required'; ?> />
			<input type="hidden" name="cp_photo_token" id="cp_photo_token" value="<?php echo esc_attr( $cp_photo_tok ); ?>" />
		</div>

		<h2 class="cp-ob-section"><?php esc_html_e( 'Personal information', 'hypeit' ); ?></h2>
		<div class="cp-ob-grid">
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'First name', 'hypeit' ); ?> *</span>
				<input type="text" name="cp_first" class="cp-input" required value="<?php echo esc_attr( $cp_val( 'first' ) ); ?>" />
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Last name', 'hypeit' ); ?> *</span>
				<input type="text" name="cp_last" class="cp-input" required value="<?php echo esc_attr( $cp_val( 'last' ) ); ?>" />
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Birthday', 'hypeit' ); ?> *</span>
				<input type="date" name="cp_birthday" class="cp-input" required value="<?php echo esc_attr( $cp_val( 'birthday' ) ); ?>" />
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Gender', 'hypeit' ); ?> *</span>
				<select name="cp_gender" class="cp-input" required>
					<option value=""><?php esc_html_e( '— Select —', 'hypeit' ); ?></option>
					<?php foreach ( CP_Library::genders() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cp_val( 'gender' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Phone number', 'hypeit' ); ?> *</span>
				<input type="tel" name="cp_phone" class="cp-input" required value="<?php echo esc_attr( $cp_val( 'phone' ) ); ?>" />
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'WhatsApp number', 'hypeit' ); ?> *</span>
				<input type="tel" name="cp_whatsapp" class="cp-input" required value="<?php echo esc_attr( $cp_val( 'whatsapp' ) ); ?>" />
			</label>
			<label class="cp-ob-field cp-ob-full">
				<span><?php esc_html_e( 'Email', 'hypeit' ); ?></span>
				<input type="email" name="cp_email" class="cp-input" required autocomplete="email" autocapitalize="none" value="<?php echo esc_attr( $cp_val( 'email' ) ); ?>" />
			</label>
		</div>

		<h2 class="cp-ob-section"><?php esc_html_e( 'Location', 'hypeit' ); ?></h2>
		<div class="cp-ob-grid">
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Country', 'hypeit' ); ?></span>
				<select name="cp_country" class="cp-input">
					<?php foreach ( CP_Location::countries() as $c => $label ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'City', 'hypeit' ); ?> *</span>
				<select name="cp_city" id="cp_city" class="cp-input" required>
					<option value=""><?php esc_html_e( '— Select —', 'hypeit' ); ?></option>
					<?php foreach ( CP_Location::cities() as $city ) : ?>
						<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $cp_val( 'city' ), $city ); ?>><?php echo esc_html( $city ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<?php
		$cp_conn_tok = isset( $_GET['cp_ig_connected'] ) ? sanitize_text_field( wp_unslash( $_GET['cp_ig_connected'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cp_conn     = $cp_conn_tok ? CP_Verify::get_connection( $cp_conn_tok ) : null;
		$cp_conn_err = isset( $_GET['cp_connect'] ) ? sanitize_key( wp_unslash( $_GET['cp_connect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<h2 class="cp-ob-section"><?php esc_html_e( 'Instagram', 'hypeit' ); ?></h2>

		<?php if ( CP_Verify::meta_ready() ) : ?>
			<?php if ( $cp_conn ) : ?>
				<p class="cp-ob-verified">✓ <?php echo esc_html( sprintf( /* translators: %s: handle. */ __( 'Connected as @%s', 'hypeit' ), $cp_conn['username'] ) ); ?></p>
				<input type="hidden" name="cp_ig_token" value="<?php echo esc_attr( $cp_conn_tok ); ?>" />
			<?php elseif ( CP_Onboarding::is_in_app_browser() ) : ?>
				<div class="cp-ob-inapp">
					<p><strong><?php esc_html_e( 'To auto-connect Instagram, open this page in your browser first.', 'hypeit' ); ?></strong></p>
					<?php if ( CP_Onboarding::is_android() ) : ?>
						<p><a class="cp-btn cp-btn-primary" href="<?php echo esc_attr( CP_Onboarding::android_intent_url() ); ?>"><?php esc_html_e( 'Open in browser', 'hypeit' ); ?></a></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Tap the ••• or Aa menu at the top of this screen and choose “Open in browser”, then tap Connect Instagram.', 'hypeit' ); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Or just fill in your details below — you can verify later, and you’ll get a code option on the next screen.', 'hypeit' ); ?></p>
				</div>
			<?php else : ?>
				<?php if ( 'notpro' === $cp_conn_err ) : ?>
					<p class="cp-gate-error"><?php esc_html_e( 'That account is not a Professional (Business/Creator) account, so it can’t be connected. You can still enter your details manually.', 'hypeit' ); ?></p>
				<?php elseif ( 'error' === $cp_conn_err ) : ?>
					<p class="cp-gate-error"><?php esc_html_e( 'Could not connect to Instagram. Please try again or enter your details manually.', 'hypeit' ); ?></p>
				<?php endif; ?>
				<p><a class="cp-btn cp-btn-primary" href="<?php echo esc_url( CP_Verify::connect_url() ); ?>"><?php esc_html_e( 'Connect Instagram (auto-fill & verify)', 'hypeit' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'Professional (Business/Creator) accounts can connect to auto-fill username and followers and get a verified badge. Connect first, then complete the rest of the form.', 'hypeit' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<div class="cp-ob-grid">
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Instagram username or profile link', 'hypeit' ); ?> *</span>
				<input type="text" name="cp_ig" class="cp-input" required placeholder="@username or https://instagram.com/username" value="<?php echo esc_attr( $cp_conn ? '@' . $cp_conn['username'] : $cp_val( 'ig' ) ); ?>" <?php echo $cp_conn ? 'readonly' : ''; ?> />
			</label>
			<label class="cp-ob-field">
				<span><?php esc_html_e( 'Followers', 'hypeit' ); ?> *</span>
				<input type="number" name="cp_followers" class="cp-input" min="1" step="1" required value="<?php echo esc_attr( $cp_conn ? (int) $cp_conn['followers'] : ( $cp_val( 'followers' ) ? (int) $cp_val( 'followers' ) : '' ) ); ?>" <?php echo $cp_conn ? 'readonly' : ''; ?> />
			</label>
		</div>
		<?php if ( CP_IGSync::ready() && ! empty( CP_IGSync::get()['bio_verify'] ) ) : ?>
			<p class="cp-ob-vteaser">✓ <?php esc_html_e( 'After you submit, you can verify your account in about a minute. Verified bloggers get a ✓ badge clients see — and are more likely to be selected.', 'hypeit' ); ?></p>
		<?php endif; ?>
		<?php if ( CP_IGSync::ready() ) : ?>
			<p class="cp-ob-igcheck" id="cp_igcheck" aria-live="polite"
				data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_ig_lookup' ) ); ?>"
				data-checking="<?php esc_attr_e( 'Checking your Instagram…', 'hypeit' ); ?>"
				data-found="<?php esc_attr_e( '✓ Found @%1$s · %2$s followers — we’ll keep this updated automatically.', 'hypeit' ); ?>"
				data-personal="<?php esc_attr_e( 'We couldn’t read this account. Check the username — or, if it’s a personal account, switch to a free Creator account (Instagram → Settings → Account type and tools) so we can verify your followers. For now, enter your followers yourself.', 'hypeit' ); ?>"
				data-busy="<?php esc_attr_e( 'Please enter your followers — we’ll confirm them automatically later.', 'hypeit' ); ?>"></p>
		<?php endif; ?>

		<h2 class="cp-ob-section"><?php esc_html_e( 'Categories', 'hypeit' ); ?> *</h2>
		<p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Select at least one.', 'hypeit' ); ?></p>
		<?php
		$chosen  = array_map( 'absint', (array) $cp_val( 'categories', array() ) );
		$cp_cats = CP_Library::category_terms();
		?>
		<div class="cp-ob-cats">
			<?php foreach ( $cp_cats as $cat ) : ?>
				<label class="cp-ob-check">
					<input type="checkbox" name="cp_categories[]" value="<?php echo (int) $cat->term_id; ?>" <?php checked( in_array( (int) $cat->term_id, $chosen, true ) ); ?> />
					<?php echo esc_html( $cat->name ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<h2 class="cp-ob-section"><?php esc_html_e( 'Open for campaigns', 'hypeit' ); ?> *</h2>
		<p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Which collaboration types are you open to? Select at least one.', 'hypeit' ); ?></p>
		<?php $chosen_collab = (array) $cp_val( 'collab', array() ); ?>
		<div class="cp-ob-cats">
			<label class="cp-ob-check"><input type="checkbox" id="cp_collab_all" /> <?php esc_html_e( 'Open to all types', 'hypeit' ); ?></label>
			<?php foreach ( CP_Library::collab_types() as $ckey => $clabel ) : ?>
				<label class="cp-ob-check">
					<input type="checkbox" name="cp_collab[]" value="<?php echo esc_attr( $ckey ); ?>" <?php checked( in_array( $ckey, $chosen_collab, true ) ); ?> />
					<?php echo esc_html( $clabel ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<p class="cp-ob-submit">
			<button type="submit" name="cp_ob_submit" value="1" class="cp-btn cp-btn-primary"><?php esc_html_e( 'Submit', 'hypeit' ); ?></button>
		</p>
	</form>

<?php endif; ?>
</div>

<?php
get_footer();
