<?php
/**
 * Admin: metaboxes, saving, list columns and results dashboard.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Admin {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . CP_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_shared' ) );
		add_action( 'edit_form_top', array( __CLASS__, 'back_link' ) );
		add_action( 'post_edit_form_tag', array( __CLASS__, 'form_enctype' ) );

		// List columns, header and filters live in CP_Campaigns_UI.

		add_action( 'admin_notices', array( __CLASS__, 'no_password_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'logo_notice' ) );
		add_action( 'admin_post_cp_campaign_export', array( __CLASS__, 'export_accepted' ) );
	}

	/**
	 * Load the shared admin stylesheet on every HypeIt screen.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue_shared( $hook ) {
		$screen = get_current_screen();
		$is_ours = false;
		if ( $screen && in_array( $screen->post_type, array( CP_POST_TYPE, 'cp_blogger' ), true ) ) {
			$is_ours = true;
		}
		if ( isset( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'cp-' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_ours = true;
		}
		if ( $is_ours ) {
			wp_enqueue_style( 'cp-admin', CP_URL . 'assets/css/admin.css', array(), CP_VERSION );
		}
	}

	/**
	 * Allow the logo upload on the campaign edit form.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function form_enctype( $post ) {
		if ( $post && CP_POST_TYPE === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/**
	 * Show a logo upload error after save (same notice as blogger photos).
	 */
	public static function logo_notice() {
		$screen = get_current_screen();
		if ( ! $screen || CP_POST_TYPE !== $screen->post_type ) {
			return;
		}
		$msg = get_transient( 'cp_photo_err_' . get_current_user_id() );
		if ( $msg ) {
			delete_transient( 'cp_photo_err_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	/**
	 * "Back to list" link at the top of blogger/campaign edit screens.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function back_link( $post ) {
		if ( ! $post || ! isset( $post->post_type ) ) {
			return;
		}
		if ( CP_POST_TYPE === $post->post_type ) {
			$url   = admin_url( 'edit.php?post_type=' . CP_POST_TYPE );
			$label = __( '← Back to Campaigns', 'hypeit' );
		} elseif ( 'cp_blogger' === $post->post_type ) {
			$url   = admin_url( 'edit.php?post_type=cp_blogger' );
			$label = __( '← Back to Bloggers', 'hypeit' );
		} else {
			return;
		}
		echo '<p style="margin:8px 0;"><a class="cp-back" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * Enqueue admin assets on the campaign edit screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || CP_POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		wp_enqueue_style( 'cp-admin', CP_URL . 'assets/css/admin.css', array(), CP_VERSION );
		wp_enqueue_script( 'cp-admin', CP_URL . 'assets/js/admin.js', array(), CP_VERSION, true );
		wp_localize_script(
			'cp-admin',
			'CP_ADMIN',
			array(
				'chooseLogo'   => __( 'Choose image', 'hypeit' ),
				'useImage'     => __( 'Use this image', 'hypeit' ),
				'noImage'      => __( 'No image', 'hypeit' ),
				'copied'       => __( 'Copied!', 'hypeit' ),
				'resetConfirm' => __( 'Reset all responses for this campaign? This cannot be undone.', 'hypeit' ),
				'resetError'   => __( 'Could not reset. Please try again.', 'hypeit' ),
				'importSelect' => __( 'Select a campaign first.', 'hypeit' ),
				'importError'  => __( 'Could not import. Please try again.', 'hypeit' ),
				'added'        => __( '%d added.', 'hypeit' ),
				'noneNew'      => __( 'Nothing new to add — they are already in this campaign.', 'hypeit' ),
				'evActive'     => __( 'On — every blogger is in this campaign, and new bloggers join automatically.', 'hypeit' ),
				'evPaused'     => __( 'On, but paused — the client has started selecting, so new bloggers are no longer added automatically. Use “Add everyone now” to include them.', 'hypeit' ),
				'evOff'        => __( 'Off — only the bloggers listed below are in this campaign.', 'hypeit' ),
				'changes'      => __( 'Unsaved changes: %1$d added, %2$d removed. Click Update to save.', 'hypeit' ),
				'leave'        => __( 'You have unsaved blogger changes.', 'hypeit' ),
				'remove'       => __( 'Remove', 'hypeit' ),
				'undo'         => __( 'Undo', 'hypeit' ),
				'isNew'        => __( 'New', 'hypeit' ),
				'removedTag'   => __( 'Will be removed', 'hypeit' ),
				'blocked'      => __( 'Blocked', 'hypeit' ),
				'notInLibrary' => __( 'Not in library', 'hypeit' ),
				'confirmRm'    => __( 'This blogger already responded. Remove them? Their response will be deleted when you save.', 'hypeit' ),
				'confirmed'    => __( 'Confirmed', 'hypeit' ),
				'declined'     => __( 'Declined', 'hypeit' ),
				'pending'      => __( 'No response', 'hypeit' ),
				'guestsFmt'    => __( '+%d guests', 'hypeit' ),
				'libShown'     => __( 'Showing %1$d of %2$d available. %3$d selected.', 'hypeit' ),
				'libEmpty'     => __( 'No available bloggers match — everyone matching is already in this campaign.', 'hypeit' ),
				'addSelected'  => __( 'Add %d selected', 'hypeit' ),
				'fltNote'      => __( '%1$d match · %2$d not in this campaign yet', 'hypeit' ),
				'fltAdd'       => __( 'Add %d', 'hypeit' ),
				'noMatches'    => __( 'No bloggers match these filters.', 'hypeit' ),
				'noRows'       => __( 'No bloggers match this view.', 'hypeit' ),
				'allConfirm'   => __( 'Add all %d active bloggers who are not in this campaign yet?', 'hypeit' ),
				'atUnlinkQ'    => __( 'Unlink this event? Guests already in ATRIUM are kept, but their status will no longer show here.', 'hypeit' ),
				'atError'      => __( 'Something went wrong. Please try again.', 'hypeit' ),
				'colFollowers' => __( 'Followers', 'hypeit' ),
				'colGender'    => __( 'Gender', 'hypeit' ),
				'colCity'      => __( 'City', 'hypeit' ),
				'colStatus'    => __( 'Status', 'hypeit' ),
				'colPeople'    => __( 'People', 'hypeit' ),
				/* translators: %s: campaign type. */
				'notOpenFor'   => __( 'Not open for %s', 'hypeit' ),
			)
		);
	}

	/**
	 * Register metaboxes: one tabbed workspace + a sidebar overview.
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'cp_workspace', __( 'Campaign', 'hypeit' ), array( __CLASS__, 'render_workspace' ), CP_POST_TYPE, 'normal', 'high' );
		add_meta_box( 'cp_overview', __( 'At a glance', 'hypeit' ), array( __CLASS__, 'render_overview' ), CP_POST_TYPE, 'side', 'high' );
		remove_meta_box( 'slugdiv', CP_POST_TYPE, 'normal' );
	}

	/**
	 * Library payload for the editor (one entry per published blogger).
	 *
	 * @return array
	 */
	private static function library_payload() {
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$ids = array_map( 'intval', (array) $ids );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			update_object_term_cache( $ids, CP_Library::CPT );
		}

		$out = array();
		foreach ( $ids as $id ) {
			$h = CP_Library::handle( $id );
			if ( '' === $h || CP_Library::is_inactive( $id ) ) {
				continue;
			}
			$name  = trim( get_post_meta( $id, '_cp_first', true ) . ' ' . get_post_meta( $id, '_cp_last', true ) );
			$lists = get_the_terms( $id, CP_Library::TAX_LIST );
			$cats  = get_the_terms( $id, CP_Library::TAX_TAG );
			$out[] = array(
				'id' => $id,
				'h'  => $h,
				'n'  => $name,
				'f'  => (int) get_post_meta( $id, '_cp_followers', true ),
				'd'  => CP_Bloggers_UI::follower_delta( $id ),
				'g'  => (string) get_post_meta( $id, '_cp_gender', true ),
				'c'  => (string) get_post_meta( $id, '_cp_city', true ),
				'v'  => CP_Verify::is_verified( $id ) ? 1 : 0,
				'u'  => (string) get_post_meta( $id, '_cp_ig_url', true ),
				'o'  => (string) get_post_meta( $id, '_cp_collab', true ),
				'x'  => '1' === (string) get_post_meta( $id, '_cp_blocked', true ) ? 1 : 0,
				'p'  => CP_Photo::url( $id, 's' ),
				'l'  => ( $lists && ! is_wp_error( $lists ) ) ? array_map( 'intval', wp_list_pluck( $lists, 'term_id' ) ) : array(),
				't'  => ( $cats && ! is_wp_error( $cats ) ) ? array_map( 'intval', wp_list_pluck( $cats, 'term_id' ) ) : array(),
			);
		}
		return $out;
	}

	/**
	 * A small on/off switch.
	 *
	 * @param string $name    Field name.
	 * @param bool   $checked State.
	 * @param string $label   Label.
	 * @param string $help    Help text.
	 * @param string $id      Optional id.
	 */
	private static function switch_field( $name, $checked, $label, $help = '', $id = '' ) {
		?>
		<label class="cpw-switchrow"<?php echo $id ? ' for="' . esc_attr( $id ) . '"' : ''; ?>>
			<span class="cpw-switch">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> <?php checked( $checked ); ?> />
				<span class="cpw-slider" aria-hidden="true"></span>
			</span>
			<span class="cpw-switchtext">
				<strong><?php echo esc_html( $label ); ?></strong>
				<?php if ( $help ) : ?>
					<small><?php echo esc_html( $help ); ?></small>
				<?php endif; ?>
			</span>
		</label>
		<?php
	}

	/**
	 * The tabbed campaign workspace.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_workspace( $post ) {
		wp_nonce_field( 'cp_save_campaign', 'cp_campaign_nonce' );

		$rows    = CP_DB::visible_bloggers( $post->ID );
		$hidden  = count( CP_DB::get_bloggers( $post->ID ) ) - count( $rows );
		$s       = CP_DB::stats( $post->ID );
		$genders = CP_Library::genders();
		$lists   = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false ) );
		$lists   = is_wp_error( $lists ) ? array() : $lists;
		$cats    = CP_Library::category_terms();
		$others  = get_posts(
			array(
				'post_type'      => CP_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post__not_in'   => array( $post->ID ),
				'fields'         => 'ids',
			)
		);

		$brief    = get_post_meta( $post->ID, '_cp_brief', true );
		$max      = get_post_meta( $post->ID, '_cp_max_guests', true );
		$max      = ( '' === $max ) ? 4 : (int) $max;
		$logo_url = CP_Logo::url( $post->ID );
		$has_pw   = CP_Auth::has_password( $post->ID );
		$token    = get_post_meta( $post->ID, '_cp_token', true );
		$url      = $token ? CP_Frontend::campaign_url( $token ) : '';

		// Campaign type — new campaigns start as Paid.
		$ctype  = CP_Library::campaign_type( $post->ID );
		$ctype  = $ctype ? $ctype : ( 'auto-draft' === $post->post_status ? 'paid' : '' );
		$ctypes = CP_Library::campaign_types();

		$ev_on      = CP_Everyone::is_on( $post->ID );
		$ev_started = CP_Everyone::selection_started( $post->ID );

		$bulk  = array();
		$state = array();
		foreach ( $rows as $row ) {
			$bulk[]  = $row->ig_account;
			$state[] = array(
				'h' => $row->ig_account,
				's' => $row->status,
				'g' => (int) $row->extra_guests,
				'u' => (string) $row->ig_url,
			);
		}

		$stages  = CP_Atrium::active() ? CP_Atrium::stages_by_handle( $post->ID ) : array();
		$payload = array(
			'stages'   => $stages,
			'stageLabels' => CP_Atrium::stage_labels(),
			'postId'   => (int) $post->ID,
			'rows'     => $state,
			'library'  => self::library_payload(),
			'genders'  => $genders,
			'types'    => array_map( static function ( $t ) { return array( 'label' => $t[0], 'collab' => $t[2] ); }, $ctypes ),
			'type'     => $ctype,
			'everyone' => array(
				'on'      => $ev_on,
				'started' => $ev_started,
			),
		);
		?>
		<div class="cpw" id="cpw">
			<script type="application/json" id="cpw-data"><?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>

			<div class="cpw-type" role="radiogroup" aria-label="<?php esc_attr_e( 'Campaign type', 'hypeit' ); ?>">
				<span class="cpw-type-label"><?php esc_html_e( 'Campaign type', 'hypeit' ); ?></span>
				<?php foreach ( $ctypes as $tk => $tv ) : ?>
					<label class="cpw-typeopt">
						<input type="radio" name="cp_type" value="<?php echo esc_attr( $tk ); ?>" <?php checked( $ctype, $tk ); ?> />
						<span class="cpw-typeicon" aria-hidden="true"><?php echo 'paid' === $tk ? '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v18M16.5 7.5c0-1.7-2-3-4.5-3s-4.5 1.3-4.5 3 2 2.6 4.5 3 4.5 1.3 4.5 3-2 3-4.5 3-4.5-1.3-4.5-3"/></svg>' : '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8S10.5 4 8 4a2 2 0 0 0 0 4h4m0 0s1.5-4 4-4a2 2 0 0 1 0 4h-4"/></svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><strong><?php echo esc_html( $tv[0] ); ?></strong><small><?php echo esc_html( $tv[1] ); ?></small></span>
					</label>
				<?php endforeach; ?>
				<?php if ( ! $ctype ) : ?><span class="cpw-type-warn"><?php esc_html_e( 'Choose a type', 'hypeit' ); ?></span><?php endif; ?>
			</div>

			<nav class="cpw-tabs" role="tablist">
				<button type="button" class="cpw-tab is-active" data-tab="bloggers" role="tab"><?php esc_html_e( 'Bloggers', 'hypeit' ); ?> <span class="cpw-pill" id="cpw-tab-count"><?php echo (int) count( $rows ); ?></span></button>
				<button type="button" class="cpw-tab" data-tab="event" role="tab"><?php esc_html_e( 'Event', 'hypeit' ); ?><?php echo CP_Atrium::event_id( $post->ID ) ? ' <span class="cpb-ok">●</span>' : ''; ?></button>
				<button type="button" class="cpw-tab" data-tab="details" role="tab"><?php esc_html_e( 'Details', 'hypeit' ); ?></button>
				<button type="button" class="cpw-tab" data-tab="client" role="tab"><?php esc_html_e( 'Client page', 'hypeit' ); ?></button>
				<button type="button" class="cpw-tab" data-tab="access" role="tab"><?php esc_html_e( 'Access & link', 'hypeit' ); ?><?php echo $has_pw ? '' : ' <span class="cpw-dot" title="' . esc_attr__( 'No password set', 'hypeit' ) . '"></span>'; ?></button>
				<button type="button" class="cpw-tab" data-tab="results" role="tab"><?php esc_html_e( 'Results', 'hypeit' ); ?></button>
			</nav>

			<?php /* ============================ BLOGGERS ============================ */ ?>
			<section class="cpw-panel is-active" data-panel="bloggers">

				<div class="cpw-everyone<?php echo $ev_on ? ' is-on' : ''; ?><?php echo $ev_started ? ' is-started' : ''; ?>" id="cpw-everyone">
					<?php self::switch_field( 'cp_everyone', $ev_on, __( 'Everyone', 'hypeit' ), '', 'cpw-ev-toggle' ); ?>
					<p class="cpw-ev-state" id="cpw-ev-state"></p>
					<button type="button" class="button" id="cpw-add-all"><?php esc_html_e( 'Add everyone now', 'hypeit' ); ?></button>
				</div>

				<div class="cpw-toolbar">
					<button type="button" class="button button-primary cpw-add-toggle" id="cpw-add-toggle" aria-expanded="false">＋ <?php esc_html_e( 'Add bloggers', 'hypeit' ); ?></button>
					<input type="search" class="cpw-search" id="cpw-filter-q" placeholder="<?php esc_attr_e( 'Search this campaign…', 'hypeit' ); ?>" />
					<select id="cpw-sort" class="cpw-sort" aria-label="<?php esc_attr_e( 'Sort', 'hypeit' ); ?>">
						<option value="order"><?php esc_html_e( 'Sort: campaign order', 'hypeit' ); ?></option>
						<option value="status"><?php esc_html_e( 'Sort: status (confirmed first)', 'hypeit' ); ?></option>
						<option value="status_rev"><?php esc_html_e( 'Sort: status (no response first)', 'hypeit' ); ?></option>
						<option value="name"><?php esc_html_e( 'Sort: name A–Z', 'hypeit' ); ?></option>
						<option value="followers_desc"><?php esc_html_e( 'Sort: followers (high → low)', 'hypeit' ); ?></option>
						<option value="followers_asc"><?php esc_html_e( 'Sort: followers (low → high)', 'hypeit' ); ?></option>
						<option value="people"><?php esc_html_e( 'Sort: people (most first)', 'hypeit' ); ?></option>
						<option value="city"><?php esc_html_e( 'Sort: city', 'hypeit' ); ?></option>
					</select>
					<div class="cpw-seg" id="cpw-filter-status" role="group">
						<button type="button" class="is-active" data-status="all"><?php esc_html_e( 'All', 'hypeit' ); ?> <span data-count="all"></span></button>
						<button type="button" data-status="confirmed"><?php esc_html_e( 'Confirmed', 'hypeit' ); ?> <span data-count="confirmed"></span></button>
						<button type="button" data-status="declined"><?php esc_html_e( 'Declined', 'hypeit' ); ?> <span data-count="declined"></span></button>
						<button type="button" data-status="pending"><?php esc_html_e( 'No response', 'hypeit' ); ?> <span data-count="pending"></span></button>
					</div>
				</div>

				<?php /* -------- Add drawer -------- */ ?>
				<div class="cpw-drawer" id="cpw-drawer" hidden>
					<div class="cpw-subtabs" role="tablist">
						<button type="button" class="is-active" data-sub="search"><?php esc_html_e( 'Search library', 'hypeit' ); ?></button>
						<button type="button" data-sub="filters"><?php esc_html_e( 'By filters', 'hypeit' ); ?></button>
						<button type="button" data-sub="paste"><?php esc_html_e( 'Paste handles', 'hypeit' ); ?></button>
						<?php if ( ! empty( $others ) ) : ?>
							<button type="button" data-sub="import"><?php esc_html_e( 'From another campaign', 'hypeit' ); ?></button>
						<?php endif; ?>
					</div>

					<div class="cpw-sub is-active" data-subpanel="search">
						<div class="cpw-row">
							<input type="search" class="cpw-search cpw-grow" id="cpw-lib-q" placeholder="<?php esc_attr_e( 'Search by name or @handle…', 'hypeit' ); ?>" />
							<button type="button" class="button" id="cpw-lib-selall"><?php esc_html_e( 'Select all shown', 'hypeit' ); ?></button>
						</div>
						<div class="cpw-picklist" id="cpw-lib-results"></div>
						<div class="cpw-row cpw-end">
							<span class="cpw-muted" id="cpw-lib-note"></span>
							<button type="button" class="button button-primary" id="cpw-lib-add" disabled><?php esc_html_e( 'Add selected', 'hypeit' ); ?></button>
						</div>
					</div>

					<div class="cpw-sub" data-subpanel="filters">
						<div class="cpw-grid">
							<?php if ( $lists ) : ?>
								<div class="cpw-field cpw-span2">
									<span class="cpw-flabel"><?php esc_html_e( 'Lists', 'hypeit' ); ?> <small><?php esc_html_e( '(in any of)', 'hypeit' ); ?></small></span>
									<div class="cpw-chips">
										<?php foreach ( $lists as $t ) : ?>
											<label class="cpw-chip"><input type="checkbox" class="cpw-f" data-f="list" value="<?php echo (int) $t->term_id; ?>" /> <?php echo esc_html( $t->name ); ?></label>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
							<?php if ( $cats ) : ?>
								<div class="cpw-field cpw-span2">
									<span class="cpw-flabel"><?php esc_html_e( 'Categories', 'hypeit' ); ?>
										<select class="cpw-f cpw-inline" data-f="catmatch">
											<option value="any"><?php esc_html_e( 'match any', 'hypeit' ); ?></option>
											<option value="all"><?php esc_html_e( 'match all', 'hypeit' ); ?></option>
										</select>
									</span>
									<div class="cpw-chips">
										<?php foreach ( $cats as $t ) : ?>
											<label class="cpw-chip"><input type="checkbox" class="cpw-f" data-f="cat" value="<?php echo (int) $t->term_id; ?>" /> <?php echo esc_html( $t->name ); ?></label>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
							<div class="cpw-field">
								<span class="cpw-flabel"><?php esc_html_e( 'Gender', 'hypeit' ); ?></span>
								<select class="cpw-f" data-f="gender">
									<option value=""><?php esc_html_e( 'Any', 'hypeit' ); ?></option>
									<?php foreach ( $genders as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="cpw-field">
								<span class="cpw-flabel"><?php esc_html_e( 'City', 'hypeit' ); ?></span>
								<select class="cpw-f" data-f="city">
									<option value=""><?php esc_html_e( 'Any', 'hypeit' ); ?></option>
									<?php foreach ( CP_Location::cities() as $city ) : ?>
										<option value="<?php echo esc_attr( $city ); ?>"><?php echo esc_html( $city ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="cpw-field">
								<span class="cpw-flabel"><?php esc_html_e( 'Followers', 'hypeit' ); ?></span>
								<span class="cpw-range">
									<input type="number" min="0" class="cpw-f" data-f="fmin" placeholder="<?php esc_attr_e( 'min', 'hypeit' ); ?>" />
									<span>–</span>
									<input type="number" min="0" class="cpw-f" data-f="fmax" placeholder="<?php esc_attr_e( 'max', 'hypeit' ); ?>" />
								</span>
							</div>
							<div class="cpw-field">
								<span class="cpw-flabel"><?php esc_html_e( 'Open for', 'hypeit' ); ?></span>
								<select class="cpw-f" data-f="collab">
									<option value=""><?php esc_html_e( 'Any', 'hypeit' ); ?></option>
									<?php foreach ( CP_Library::collab_types() as $ck => $cl ) : ?>
										<option value="<?php echo esc_attr( $ck ); ?>"><?php echo esc_html( $cl ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="cpw-field">
								<span class="cpw-flabel">&nbsp;</span>
								<label class="cpw-check"><input type="checkbox" class="cpw-f" data-f="verified" value="1" /> <?php esc_html_e( 'Verified only', 'hypeit' ); ?></label>
							</div>
						</div>
						<div class="cpw-row cpw-end">
							<span class="cpw-muted" id="cpw-flt-note"></span>
							<button type="button" class="button button-primary" id="cpw-flt-add" disabled><?php esc_html_e( 'Add matches', 'hypeit' ); ?></button>
						</div>
					</div>

					<div class="cpw-sub" data-subpanel="paste">
						<textarea id="cpw-paste" rows="6" class="widefat code" placeholder="<?php esc_attr_e( "@account_one\nhttps://instagram.com/account_two\naccount_three", 'hypeit' ); ?>"></textarea>
						<div class="cpw-row cpw-end">
							<span class="cpw-muted"><?php esc_html_e( 'One per line (commas also work). @ and profile links are cleaned automatically.', 'hypeit' ); ?></span>
							<button type="button" class="button button-primary" id="cpw-paste-add"><?php esc_html_e( 'Add', 'hypeit' ); ?></button>
						</div>
					</div>

					<?php if ( ! empty( $others ) ) : ?>
						<div class="cpw-sub" data-subpanel="import">
							<div class="cpw-row">
								<select id="cpw-import-src" class="cpw-grow">
									<option value=""><?php esc_html_e( '— Select a campaign —', 'hypeit' ); ?></option>
									<?php foreach ( $others as $oid ) : ?>
										<option value="<?php echo (int) $oid; ?>"><?php echo esc_html( get_the_title( $oid ) ? get_the_title( $oid ) : sprintf( /* translators: %d: post ID. */ __( 'Campaign #%d', 'hypeit' ), $oid ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<label class="cpw-check"><input type="checkbox" id="cpw-import-confirmed" /> <?php esc_html_e( 'Confirmed only', 'hypeit' ); ?></label>
								<button type="button" class="button button-primary" id="cpw-import-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_admin_import' ) ); ?>"><?php esc_html_e( 'Import', 'hypeit' ); ?></button>
							</div>
							<p class="cpw-muted" id="cpw-import-note"><?php esc_html_e( 'Copies that campaign’s bloggers into this one (responses are not copied).', 'hypeit' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<div class="cpw-changes" id="cpw-changes" hidden>
					<span id="cpw-changes-text"></span>
					<button type="button" class="button-link" id="cpw-undo-all"><?php esc_html_e( 'Undo all', 'hypeit' ); ?></button>
				</div>

				<div class="cpw-tablewrap">
					<table class="cpw-table">
						<thead>
							<tr>
								<th class="cpw-c-idx">#</th>
								<th><?php esc_html_e( 'Blogger', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'Followers', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'Gender', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'City', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'Status', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'People', 'hypeit' ); ?></th>
								<th class="cpw-c-act"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'hypeit' ); ?></span></th>
							</tr>
						</thead>
						<tbody id="cpw-tbody">
							<?php foreach ( $rows as $i => $row ) : ?>
								<tr><td><?php echo (int) ( $i + 1 ); ?></td><td colspan="7">@<?php echo esc_html( $row->ig_account ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $hidden > 0 ) : ?>
						<p class="cpw-muted cpw-hiddennote">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: count. */
									_n( '%d deactivated blogger is hidden from this campaign. Their record is kept — reactivate them in the Blogger Library to show them again.', '%d deactivated bloggers are hidden from this campaign. Their records are kept — reactivate them in the Blogger Library to show them again.', $hidden, 'hypeit' ),
									$hidden
								)
							);
							?>
						</p>
					<?php endif; ?>
					<p class="cpw-empty" id="cpw-empty"<?php echo $rows ? ' hidden' : ''; ?>><?php esc_html_e( 'No bloggers yet. Use “Add bloggers” or turn on Everyone.', 'hypeit' ); ?></p>
				</div>

				<textarea name="cp_bloggers_bulk" id="cp_bloggers_bulk" class="cpw-hidden" aria-hidden="true" tabindex="-1"><?php echo esc_textarea( implode( "\n", $bulk ) ); ?></textarea>
			</section>

			<?php /* ============================ EVENT (ATRIUM) ============================ */ ?>
			<section class="cpw-panel" data-panel="event">
				<?php CP_Atrium::render_panel( $post ); ?>
			</section>

			<?php /* ============================ DETAILS ============================ */ ?>
			<section class="cpw-panel" data-panel="details">
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Brief', 'hypeit' ); ?></h3>
					<p class="cpw-muted"><?php esc_html_e( 'Shown to the client at the top of the campaign page.', 'hypeit' ); ?></p>
					<textarea id="cp_brief" name="cp_brief" rows="7" class="widefat"><?php echo esc_textarea( $brief ); ?></textarea>
				</div>
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Client logo / image', 'hypeit' ); ?></h3>
					<div class="cpw-logo">
						<label class="cpw-logo-preview" id="cp_logo_preview" for="cp_logo_file" title="<?php esc_attr_e( 'Choose image', 'hypeit' ); ?>">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
							<?php else : ?>
								<span class="cpw-muted"><?php esc_html_e( 'No image', 'hypeit' ); ?></span>
							<?php endif; ?>
						</label>
						<span class="cpw-logo-actions">
							<label class="button" for="cp_logo_file"><?php echo esc_html( $logo_url ? __( 'Change image', 'hypeit' ) : __( 'Choose image', 'hypeit' ) ); ?></label>
							<input type="file" id="cp_logo_file" name="cp_logo_file" accept="image/jpeg,image/png,image/webp,image/gif" class="cpw-hidden" />
							<?php if ( $logo_url ) : ?>
								<label class="cpw-check"><input type="checkbox" name="cp_logo_remove" value="1" id="cp_logo_remove" /> <?php esc_html_e( 'Remove', 'hypeit' ); ?></label>
							<?php endif; ?>
							<small class="cpw-muted"><?php esc_html_e( 'JPG, PNG or WebP. Transparent PNGs stay transparent. Stored privately — not in your Media Library.', 'hypeit' ); ?></small>
						</span>
					</div>
					<script>
					( function () {
						var f = document.getElementById( 'cp_logo_file' ), p = document.getElementById( 'cp_logo_preview' ), rm = document.getElementById( 'cp_logo_remove' );
						if ( f ) { f.addEventListener( 'change', function () { if ( f.files && f.files[0] ) { p.innerHTML = '<img src="' + URL.createObjectURL( f.files[0] ) + '" alt="" />'; if ( rm ) { rm.checked = false; } } } ); }
						if ( rm ) { rm.addEventListener( 'change', function () { p.style.opacity = rm.checked ? '.3' : ''; } ); }
					} )();
					</script>
				</div>
			</section>

			<?php /* ============================ CLIENT PAGE ============================ */ ?>
			<section class="cpw-panel" data-panel="client">
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Guests', 'hypeit' ); ?></h3>
					<label class="cpw-inlinefield">
						<span><?php esc_html_e( 'Maximum additional guests per blogger', 'hypeit' ); ?></span>
						<input type="number" id="cp_max_guests" name="cp_max_guests" min="0" max="20" value="<?php echo esc_attr( $max ); ?>" class="small-text" />
					</label>
					<p class="cpw-muted"><?php esc_html_e( 'e.g. 4 lets the client choose up to “Plus 4” for each confirmed blogger. 0 = no guests.', 'hypeit' ); ?></p>
				</div>
				<div class="cpw-card">
					<h3><?php esc_html_e( 'What the client sees for each blogger', 'hypeit' ); ?></h3>
					<div class="cpw-switches">
						<?php
						self::switch_field( 'cp_show_followers', '1' === get_post_meta( $post->ID, '_cp_show_followers', true ), __( 'Followers', 'hypeit' ) );
						self::switch_field( 'cp_show_gender', '1' === get_post_meta( $post->ID, '_cp_show_gender', true ), __( 'Gender', 'hypeit' ) );
						self::switch_field( 'cp_show_tags', '1' === get_post_meta( $post->ID, '_cp_show_tags', true ), __( 'Categories', 'hypeit' ) );
						self::switch_field( 'cp_show_location', '1' === get_post_meta( $post->ID, '_cp_show_location', true ), __( 'Location', 'hypeit' ) );
						self::switch_field( 'cp_show_popularity', '0' !== get_post_meta( $post->ID, '_cp_show_popularity', true ), __( 'Popularity label', 'hypeit' ), __( 'Top Choice, Trending…', 'hypeit' ) );
						?>
					</div>
					<p class="cpw-muted"><?php esc_html_e( 'Private details (phone, WhatsApp, birthday, address) are never shown to clients.', 'hypeit' ); ?></p>
				</div>
			</section>

			<?php /* ============================ ACCESS ============================ */ ?>
			<section class="cpw-panel" data-panel="access">
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Private link', 'hypeit' ); ?></h3>
					<?php if ( 'publish' !== $post->post_status ) : ?>
						<p class="cpw-note"><?php esc_html_e( 'Publish the campaign to activate its link.', 'hypeit' ); ?></p>
					<?php endif; ?>
					<?php if ( $url ) : ?>
						<div class="cpw-row">
							<input type="text" class="cpw-grow cp-link-input" readonly value="<?php echo esc_url( $url ); ?>" onclick="this.select()" />
							<button type="button" class="button button-primary cp-copy-link" data-link="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Copy', 'hypeit' ); ?></button>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="button"><?php esc_html_e( 'Open', 'hypeit' ); ?></a>
						</div>
					<?php endif; ?>
					<label class="cpw-flabel" for="cp_custom_slug" style="margin-top:14px;"><?php esc_html_e( 'Custom URL', 'hypeit' ); ?></label>
					<div class="cpw-slug">
						<code><?php echo esc_html( trailingslashit( home_url( '/campaign/' ) ) ); ?></code>
						<input type="text" id="cp_custom_slug" name="cp_custom_slug" value="<?php echo esc_attr( $token ); ?>" />
					</div>
					<p class="cpw-muted"><?php esc_html_e( 'Letters, numbers and hyphens. Changing it disables the old link.', 'hypeit' ); ?></p>
					<label class="cpw-check"><input type="checkbox" name="cp_regenerate_token" value="1" /> <?php esc_html_e( 'Replace with a random link on save', 'hypeit' ); ?></label>
				</div>

				<?php
				$cp_exp     = CP_Expiry::get( $post->ID );
				$cp_contact = array(
					'name'  => (string) get_post_meta( $post->ID, '_cp_contact_name', true ),
					'phone' => (string) get_post_meta( $post->ID, '_cp_contact_phone', true ),
					'email' => (string) get_post_meta( $post->ID, '_cp_contact_email', true ),
				);
				?>
				<div class="cpw-card cpw-expiry">
					<h3><?php esc_html_e( 'Link expires', 'hypeit' ); ?>
						<?php if ( $cp_exp ) : ?>
							<span class="cp-badge <?php echo CP_Expiry::is_expired( $post->ID ) ? 'cp-badge-declined' : 'cp-badge-confirmed'; ?>"><?php echo esc_html( CP_Expiry::label( $post->ID ) ); ?></span>
						<?php endif; ?>
					</h3>
					<p class="cpw-muted"><?php esc_html_e( 'The client sees a live countdown to finish choosing. After this time the list is hidden and they’re asked to contact you for a fresh link — extend it any time to reopen the same link.', 'hypeit' ); ?></p>
					<div class="cpw-row">
						<input type="datetime-local" id="cp_expires" name="cp_expires" value="<?php echo esc_attr( CP_Expiry::to_local( $cp_exp ) ); ?>" data-now="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i' ) ); ?>" />
						<span class="cpw-quick" id="cpw-exp-quick">
							<button type="button" class="button" data-h="24"><?php esc_html_e( '+24 hours', 'hypeit' ); ?></button>
							<button type="button" class="button" data-h="72"><?php esc_html_e( '+3 days', 'hypeit' ); ?></button>
							<button type="button" class="button" data-h="168"><?php esc_html_e( '+1 week', 'hypeit' ); ?></button>
							<button type="button" class="button-link" data-h="0"><?php esc_html_e( 'No expiry', 'hypeit' ); ?></button>
						</span>
					</div>
					<p class="cpw-muted"><?php echo esc_html( sprintf( /* translators: %s: timezone. */ __( 'Site time (%s). Quick buttons count from now.', 'hypeit' ), wp_timezone_string() ) ); ?></p>
					<h4 class="cpw-subhead"><?php esc_html_e( 'Contact person for a new link', 'hypeit' ); ?></h4>
					<div class="cpb-grid cpb-grid3">
						<label class="cpb-f"><span><?php esc_html_e( 'Name', 'hypeit' ); ?></span><input type="text" name="cp_contact_name" value="<?php echo esc_attr( $cp_contact['name'] ); ?>" placeholder="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'WhatsApp / phone', 'hypeit' ); ?></span><input type="tel" name="cp_contact_phone" value="<?php echo esc_attr( $cp_contact['phone'] ); ?>" placeholder="+20…" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'Email', 'hypeit' ); ?></span><input type="email" name="cp_contact_email" value="<?php echo esc_attr( $cp_contact['email'] ); ?>" /></label>
					</div>
					<p class="cpw-muted"><?php esc_html_e( 'Shown on the expired page with WhatsApp, call and email buttons. Empty = the campaign’s author.', 'hypeit' ); ?></p>
					<script>
					( function () {
						var inp = document.getElementById( 'cp_expires' ), q = document.getElementById( 'cpw-exp-quick' );
						if ( ! inp || ! q ) { return; }
						function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
						q.addEventListener( 'click', function ( e ) {
							var b = e.target.closest( '[data-h]' ); if ( ! b ) { return; }
							var h = parseInt( b.getAttribute( 'data-h' ), 10 );
							if ( ! h ) { inp.value = ''; return; }
							// Site-local "now" treated as a plain clock value (no browser timezone shift).
							var d = new Date( inp.getAttribute( 'data-now' ) + ':00Z' );
							d = new Date( d.getTime() + h * 3600000 );
							inp.value = d.getUTCFullYear() + '-' + pad( d.getUTCMonth() + 1 ) + '-' + pad( d.getUTCDate() ) + 'T' + pad( d.getUTCHours() ) + ':' + pad( d.getUTCMinutes() );
						} );
					} )();
					</script>
				</div>

				<div class="cpw-card">
					<h3><?php esc_html_e( 'Password', 'hypeit' ); ?> <?php echo $has_pw ? '<span class="cp-badge cp-badge-confirmed">' . esc_html__( 'Set', 'hypeit' ) . '</span>' : '<span class="cp-badge cp-badge-declined">' . esc_html__( 'Not set', 'hypeit' ) . '</span>'; ?></h3>
					<div class="cpw-row">
						<input type="text" id="cp_password" name="cp_password" value="" class="cpw-grow" autocomplete="off" placeholder="<?php echo esc_attr( $has_pw ? __( 'Leave blank to keep the current password', 'hypeit' ) : __( 'Set a password', 'hypeit' ) ); ?>" />
						<button type="button" class="button" id="cpw-genpw"><?php esc_html_e( 'Generate', 'hypeit' ); ?></button>
					</div>
					<p class="cpw-muted"><?php esc_html_e( 'The client needs this to see the blogger list. Copy it before saving — it can’t be shown again.', 'hypeit' ); ?></p>
				</div>

				<div class="cpw-card">
					<h3><?php esc_html_e( 'Notifications', 'hypeit' ); ?></h3>
					<input type="text" id="cp_notify_email" name="cp_notify_email" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cp_notify_email', true ) ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Uses the global default if blank', 'hypeit' ); ?>" />
					<p class="cpw-muted"><?php esc_html_e( 'Emails that receive the client’s selection updates for this campaign. Comma-separated.', 'hypeit' ); ?></p>
				</div>
			</section>

			<?php /* ============================ RESULTS ============================ */ ?>
			<section class="cpw-panel" data-panel="results">
				<div class="cpw-stats">
					<div class="cpw-stat"><b><?php echo (int) $s['total']; ?></b><span><?php esc_html_e( 'Bloggers', 'hypeit' ); ?></span></div>
					<div class="cpw-stat is-green"><b><?php echo (int) $s['confirmed']; ?></b><span><?php esc_html_e( 'Confirmed', 'hypeit' ); ?></span></div>
					<div class="cpw-stat is-red"><b><?php echo (int) $s['declined']; ?></b><span><?php esc_html_e( 'Declined', 'hypeit' ); ?></span></div>
					<div class="cpw-stat"><b><?php echo (int) $s['pending']; ?></b><span><?php esc_html_e( 'No response', 'hypeit' ); ?></span></div>
					<div class="cpw-stat is-dark"><b><?php echo (int) $s['attendance']; ?></b><span><?php esc_html_e( 'Total attendance', 'hypeit' ); ?></span></div>
				</div>
				<p class="cpw-muted">
					<?php
					printf(
						/* translators: 1: confirmed bloggers, 2: additional guests. */
						esc_html__( 'Total attendance = %1$d confirmed bloggers + %2$d additional guests.', 'hypeit' ),
						(int) $s['confirmed'],
						(int) $s['extra_guests']
					);
					?>
				</p>
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Export', 'hypeit' ); ?></h3>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cp_campaign_export&post=' . (int) $post->ID ), 'cp_campaign_export_' . $post->ID ) ); ?>"><?php esc_html_e( 'Export accepted (CSV)', 'hypeit' ); ?></a>
					<p class="cpw-muted"><?php esc_html_e( 'Confirmed bloggers with names, Instagram username and link, guests and total people.', 'hypeit' ); ?></p>
				</div>
				<div class="cpw-card cpw-danger">
					<h3><?php esc_html_e( 'Reset responses', 'hypeit' ); ?></h3>
					<button type="button" class="button cp-admin-reset" data-campaign="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_admin_reset' ) ); ?>"><?php esc_html_e( 'Reset all responses', 'hypeit' ); ?></button>
					<p class="cpw-muted"><?php esc_html_e( 'Sets every blogger back to “no response” and re-opens the selection. This cannot be undone.', 'hypeit' ); ?></p>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Sidebar overview: link + live numbers.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_overview( $post ) {
		$s     = CP_DB::stats( $post->ID );
		$token = get_post_meta( $post->ID, '_cp_token', true );
		$url   = $token ? CP_Frontend::campaign_url( $token ) : '';
		?>
		<div class="cpw-glance">
			<?php
			if ( 'auto-draft' !== $post->post_status ) :
				$cp_closed = CP_Close::is_closed( $post->ID );
				?>
				<div class="cpw-state <?php echo $cp_closed ? 'is-closed' : 'is-open'; ?>">
					<div class="cpw-state-text">
						<span class="cpw-state-dot" aria-hidden="true"></span>
						<div>
							<strong><?php echo esc_html( $cp_closed ? __( 'Closed', 'hypeit' ) : __( 'Open', 'hypeit' ) ); ?></strong>
							<small><?php echo esc_html( $cp_closed ? __( 'Client link is disabled', 'hypeit' ) : __( 'Client can view and respond', 'hypeit' ) ); ?></small>
						</div>
					</div>
					<?php if ( $cp_closed ) : ?>
						<a class="cpw-state-btn" href="<?php echo esc_url( CP_Close::url( $post->ID, false ) ); ?>"><?php esc_html_e( 'Reopen', 'hypeit' ); ?></a>
					<?php else : ?>
						<a class="cpw-state-btn" href="<?php echo esc_url( CP_Close::url( $post->ID, true ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Close this campaign? The client will no longer be able to open it. Bloggers without a response won’t count in insights. You can reopen it any time.', 'hypeit' ) ); ?>');"><?php esc_html_e( 'Close', 'hypeit' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php
			$cp_dead = CP_Expiry::get( $post->ID );
			if ( $cp_dead && ! CP_Close::is_closed( $post->ID ) ) :
				$cp_expd = CP_Expiry::is_expired( $post->ID );
				?>
				<div class="cpw-expstate<?php echo $cp_expd ? ' is-expired' : ''; ?>">
					<span><strong><?php echo esc_html( CP_Expiry::label( $post->ID ) ); ?></strong><small><?php echo esc_html( CP_Expiry::nice( $cp_dead ) ); ?></small></span>
					<a class="cpw-state-btn" href="<?php echo esc_url( CP_Expiry::extend_url( $post->ID, 24 ) ); ?>"><?php esc_html_e( '+24h', 'hypeit' ); ?></a>
				</div>
			<?php endif; ?>
			<?php $cp_tl = CP_Library::campaign_type_label( $post->ID ); ?>
			<?php if ( $cp_tl ) : ?><p class="cpw-glance-type"><span class="cpc-type is-<?php echo esc_attr( CP_Library::campaign_type( $post->ID ) ); ?>"><?php echo esc_html( $cp_tl ); ?></span></p><?php endif; ?>
			<div class="cpw-glance-stats">
				<span class="is-green"><b><?php echo (int) $s['confirmed']; ?></b><?php esc_html_e( 'Confirmed', 'hypeit' ); ?></span>
				<span class="is-red"><b><?php echo (int) $s['declined']; ?></b><?php esc_html_e( 'Declined', 'hypeit' ); ?></span>
				<span><b><?php echo (int) $s['pending']; ?></b><?php esc_html_e( 'Pending', 'hypeit' ); ?></span>
				<span class="is-dark"><b><?php echo (int) $s['attendance']; ?></b><?php esc_html_e( 'People', 'hypeit' ); ?></span>
			</div>
			<?php if ( $url && 'publish' === $post->post_status ) : ?>
				<p class="cpw-glance-actions">
					<button type="button" class="button button-primary cp-copy-link" data-link="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Copy client link', 'hypeit' ); ?></button>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="button"><?php esc_html_e( 'Open', 'hypeit' ); ?></a>
				</p>
			<?php else : ?>
				<p class="cpw-muted"><?php esc_html_e( 'Publish to activate the client link.', 'hypeit' ); ?></p>
			<?php endif; ?>
			<?php if ( $s['confirmed'] ) : ?>
				<p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cp_campaign_export&post=' . (int) $post->ID ), 'cp_campaign_export_' . $post->ID ) ); ?>"><?php esc_html_e( 'Export accepted (CSV)', 'hypeit' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Export a campaign's accepted bloggers as CSV (with the campaign name).
	 */
	public static function export_accepted() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_campaign_export_' . $post_id );
		if ( ! $post_id || CP_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . self::export_filename( $post_id ) );
		echo self::accepted_csv( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * CSV filename for a campaign's accepted export.
	 *
	 * @param int $post_id Campaign ID.
	 * @return string
	 */
	public static function export_filename( $post_id ) {
		return sanitize_title( get_the_title( $post_id ) ) . '-accepted-' . gmdate( 'Ymd' ) . '.csv';
	}

	/**
	 * Build the accepted-bloggers CSV (shared by the admin download and the app).
	 *
	 * @param int $post_id Campaign ID.
	 * @return string
	 */
	public static function accepted_csv( $post_id ) {
		$fh = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $fh, array( 'First name', 'Last name', 'Instagram Username', 'Instagram URL', 'Additional guests', 'Total people' ) );

		foreach ( CP_DB::visible_bloggers( $post_id ) as $row ) {
			if ( 'confirmed' !== $row->status ) {
				continue;
			}
			$handle = $row->ig_account;
			$lib    = CP_Library::find_by_handle( $handle );
			$url    = ! empty( $row->ig_url ) ? $row->ig_url : ( $lib ? get_post_meta( $lib, '_cp_ig_url', true ) : '' );
			if ( ! $url ) {
				$url = CP_Library::profile_url( $handle );
			}
			fputcsv(
				$fh,
				array(
					$lib ? get_post_meta( $lib, '_cp_first', true ) : '',
					$lib ? get_post_meta( $lib, '_cp_last', true ) : '',
					$handle ? '@' . $handle : '',
					$url,
					(int) $row->extra_guests,
					1 + (int) $row->extra_guests,
				)
			);
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return (string) $csv;
	}

	/**
	 * Save campaign meta and sync bloggers.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['cp_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_campaign_nonce'] ) ), 'cp_save_campaign' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Link expiry + contact person.
		if ( isset( $_POST['cp_expires'] ) ) {
			CP_Expiry::set( $post_id, CP_Expiry::from_local( sanitize_text_field( wp_unslash( $_POST['cp_expires'] ) ) ) );
			CP_Expiry::set_contact(
				$post_id,
				array(
					'name'  => isset( $_POST['cp_contact_name'] ) ? wp_unslash( $_POST['cp_contact_name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					'phone' => isset( $_POST['cp_contact_phone'] ) ? wp_unslash( $_POST['cp_contact_phone'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					'email' => isset( $_POST['cp_contact_email'] ) ? wp_unslash( $_POST['cp_contact_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				)
			);
		}

		// Campaign type.
		if ( isset( $_POST['cp_type'] ) ) {
			CP_Library::set_campaign_type( $post_id, sanitize_key( wp_unslash( $_POST['cp_type'] ) ) );
		}

		// Brief.
		$brief = isset( $_POST['cp_brief'] ) ? wp_kses_post( wp_unslash( $_POST['cp_brief'] ) ) : '';
		update_post_meta( $post_id, '_cp_brief', $brief );

		// Max guests.
		$max = isset( $_POST['cp_max_guests'] ) ? absint( $_POST['cp_max_guests'] ) : 4;
		$max = min( 20, $max );
		update_post_meta( $post_id, '_cp_max_guests', $max );

		// Logo.
		// Logo: private file storage (not the Media Library).
		if ( ! empty( $_POST['cp_logo_remove'] ) ) {
			CP_Logo::remove( $post_id );
		}
		if ( ! empty( $_FILES['cp_logo_file'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['cp_logo_file']['error'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$res = CP_Logo::set_from_upload( $post_id, $_FILES['cp_logo_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_wp_error( $res ) ) {
				set_transient( 'cp_photo_err_' . get_current_user_id(), $res->get_error_message(), 60 );
			}
		}

		// Password (only if provided).
		if ( isset( $_POST['cp_password'] ) ) {
			$pw = (string) wp_unslash( $_POST['cp_password'] );
			CP_Auth::set_password( $post_id, $pw );
		}

		// Per-campaign notification recipients.
		if ( isset( $_POST['cp_notify_email'] ) ) {
			update_post_meta( $post_id, '_cp_notify_email', sanitize_text_field( wp_unslash( $_POST['cp_notify_email'] ) ) );
		}

		// Client display options.
		update_post_meta( $post_id, '_cp_show_followers', empty( $_POST['cp_show_followers'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_cp_show_gender', empty( $_POST['cp_show_gender'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_cp_show_tags', empty( $_POST['cp_show_tags'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_cp_show_location', empty( $_POST['cp_show_location'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_cp_show_popularity', empty( $_POST['cp_show_popularity'] ) ? '0' : '1' );

		// URL token: random on regenerate, otherwise a custom slug, else keep/create.
		$current = get_post_meta( $post_id, '_cp_token', true );
		$desired = isset( $_POST['cp_custom_slug'] ) ? sanitize_title( wp_unslash( $_POST['cp_custom_slug'] ) ) : '';

		if ( ! empty( $_POST['cp_regenerate_token'] ) ) {
			update_post_meta( $post_id, '_cp_token', self::generate_token() );
		} elseif ( '' !== $desired && $desired !== $current ) {
			update_post_meta( $post_id, '_cp_token', self::unique_slug( $desired, $post_id ) );
		} elseif ( empty( $current ) ) {
			update_post_meta( $post_id, '_cp_token', self::generate_token() );
		}

		// Bloggers.
		if ( isset( $_POST['cp_bloggers_bulk'] ) ) {
			$accounts = self::parse_accounts( (string) wp_unslash( $_POST['cp_bloggers_bulk'] ) );

			// Deactivated bloggers: never add them, and keep the rows they already have
			// (the editor doesn't show them, so they're missing from the submitted list).
			$accounts = array_values(
				array_filter(
					$accounts,
					static function ( $a ) { return ! CP_Library::handle_inactive( $a ); }
				)
			);
			foreach ( CP_DB::get_bloggers( $post_id ) as $row ) {
				if ( CP_Library::handle_inactive( $row->ig_account ) ) {
					$accounts[] = $row->ig_account;
				}
			}
			CP_DB::sync_accounts( $post_id, $accounts );

			// Everyone toggle — saved after the list so it can top it up.
			CP_Everyone::set_on( $post_id, ! empty( $_POST['cp_everyone'] ) );
		}
	}

	/**
	 * Parse a bulk textarea into a clean, ordered, de-duplicated account list.
	 *
	 * @param string $bulk Raw textarea.
	 * @return array
	 */
	private static function parse_accounts( $bulk ) {
		$lines    = preg_split( '/\r\n|\r|\n/', $bulk );
		$accounts = array();
		$seen     = array();

		foreach ( $lines as $line ) {
			$account = self::clean_account( $line );
			if ( '' === $account ) {
				continue;
			}
			$key = strtolower( $account );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ]  = true;
			$accounts[]    = $account;
		}

		return $accounts;
	}

	/**
	 * Clean a single Instagram account handle.
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	private static function clean_account( $raw ) {
		$raw = trim( sanitize_text_field( $raw ) );
		$raw = ltrim( $raw, '@' );
		// Instagram handles: letters, numbers, period, underscore.
		$raw = preg_replace( '/[^A-Za-z0-9._]/', '', $raw );
		return $raw;
	}

	/**
	 * Generate a URL-safe token.
	 *
	 * @return string
	 */
	public static function generate_token() {
		return strtolower( wp_generate_password( 24, false, false ) );
	}

	/**
	 * Ensure a custom slug is unique across campaigns; append -2, -3… if taken.
	 *
	 * @param string $slug    Desired slug (already sanitized).
	 * @param int    $exclude Campaign ID to exclude from the check.
	 * @return string
	 */
	public static function unique_slug( $slug, $exclude ) {
		$base = $slug;
		$i    = 2;

		while ( self::slug_taken( $slug, $exclude ) ) {
			$slug = $base . '-' . $i;
			$i++;
			if ( $i > 100 ) {
				$slug = $base . '-' . self::generate_token();
				break;
			}
		}
		return $slug;
	}

	/**
	 * Whether a slug is already used by another campaign.
	 *
	 * @param string $slug    Slug.
	 * @param int    $exclude Campaign ID to exclude.
	 * @return bool
	 */
	public static function slug_taken( $slug, $exclude ) {
		$found = get_posts(
			array(
				'post_type'      => CP_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => array( absint( $exclude ) ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_cp_token',
						'value' => $slug,
					),
				),
			)
		);
		return ! empty( $found );
	}

	/**
	 * Status badge markup.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_badge( $status ) {
		$labels = array(
			'confirmed' => __( 'Confirmed', 'hypeit' ),
			'declined'  => __( 'Declined', 'hypeit' ),
			'pending'   => __( 'No response', 'hypeit' ),
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'];
		return '<span class="cp-badge cp-badge-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Warn if a published campaign has no password.
	 */
	public static function no_password_notice() {
		$screen = get_current_screen();
		if ( ! $screen || CP_POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		global $post;
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! CP_Auth::has_password( $post->ID ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This campaign has no password yet. Set one so the blogger list stays private.', 'hypeit' ) . '</p></div>';
		}
	}
}
