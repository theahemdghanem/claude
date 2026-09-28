<?php
/**
 * Blogger Library (BWA): CPT, Tags + Lists taxonomies, full attribute set,
 * public/private separation, columns, sorting, filtering, and block/delete.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Blogger_CPT {

	/**
	 * Guard against recursive save.
	 *
	 * @var bool
	 */
	private static $saving = false;

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
			add_action( 'save_post_' . CP_Library::CPT, array( __CLASS__, 'save' ), 10, 2 );
			add_action( 'post_edit_form_tag', array( __CLASS__, 'form_enctype' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'admin_notices', array( __CLASS__, 'photo_notice' ) );

			// Columns, header toolbar and sorting now live in CP_Bloggers_UI.

			add_filter( 'views_edit-' . CP_Library::CPT, array( __CLASS__, 'drop_mine' ) );
			add_filter( 'views_edit-' . CP_POST_TYPE, array( __CLASS__, 'drop_mine' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters_query' ) );

			add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
			add_filter( 'bulk_actions-edit-' . CP_Library::CPT, array( __CLASS__, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . CP_Library::CPT, array( __CLASS__, 'handle_bulk' ), 10, 3 );

			add_action( 'admin_post_cp_block', array( __CLASS__, 'action_block' ) );
			add_action( 'admin_post_cp_unblock', array( __CLASS__, 'action_unblock' ) );
			add_action( 'admin_post_cp_delete_block', array( __CLASS__, 'action_delete_block' ) );

			add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_titles' ) );
		}
	}

	/**
	 * One-time: recompute all blogger titles to the name-only format.
	 */
	public static function maybe_migrate_titles() {
		if ( get_option( 'cp_titles_v2' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( (array) $ids as $id ) {
			self::sync_title( $id );
		}
		update_option( 'cp_titles_v2', 1 );
	}

	/**
	 * Register CPT + taxonomies.
	 */
	public static function register() {
		register_post_type(
			CP_Library::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Bloggers', 'hypeit' ),
					'singular_name' => __( 'Blogger', 'hypeit' ),
					'menu_name'     => __( 'Blogger Library', 'hypeit' ),
					'add_new_item'  => __( 'Add New Blogger', 'hypeit' ),
					'edit_item'     => __( 'Edit Blogger', 'hypeit' ),
					'search_items'  => __( 'Search Bloggers', 'hypeit' ),
					'all_items'     => __( 'Blogger Library', 'hypeit' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . CP_POST_TYPE,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'hierarchical'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
			)
		);

		register_taxonomy(
			CP_Library::TAX_LIST,
			CP_Library::CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Lists', 'hypeit' ),
					'singular_name' => __( 'List', 'hypeit' ),
					'add_new_item'  => __( 'Add New List', 'hypeit' ),
					'menu_name'     => __( 'Lists', 'hypeit' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_menu'      => false,
				'hierarchical'      => true,
				'rewrite'           => false,
			)
		);

		register_taxonomy(
			CP_Library::TAX_TAG,
			CP_Library::CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Categories', 'hypeit' ),
					'singular_name' => __( 'Category', 'hypeit' ),
					'add_new_item'  => __( 'Add New Category', 'hypeit' ),
					'menu_name'     => __( 'Categories', 'hypeit' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_menu'      => false,
				'hierarchical'      => false,
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Attributes metabox.
	 */
	public static function meta_box() {
		add_meta_box( 'cp_blogger_ws', __( 'Blogger', 'hypeit' ), array( __CLASS__, 'render' ), CP_Library::CPT, 'normal', 'high' );
		add_meta_box( 'cp_blogger_glance', __( 'At a glance', 'hypeit' ), array( __CLASS__, 'render_glance' ), CP_Library::CPT, 'side', 'high' );
		// Everything now lives in the workspace tabs.
		remove_meta_box( 'cp_verify_box', CP_Library::CPT, 'side' );
		remove_meta_box( 'tagsdiv-' . CP_Library::TAX_TAG, CP_Library::CPT, 'side' );
		remove_meta_box( CP_Library::TAX_LIST . 'div', CP_Library::CPT, 'side' );
		remove_meta_box( 'slugdiv', CP_Library::CPT, 'normal' );
	}

	/**
	 * Every campaign this handle was part of (newest first).
	 *
	 * @param string $handle Handle.
	 * @return array
	 */
	public static function history( $handle ) {
		global $wpdb;
		$handle = strtolower( ltrim( (string) $handle, '@' ) );
		if ( '' === $handle ) {
			return array();
		}
		$table = $wpdb->prefix . 'cp_bloggers';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT b.campaign_id, b.status, b.extra_guests, p.post_title, p.post_status, p.post_date
				FROM {$table} b INNER JOIN {$wpdb->posts} p ON p.ID = b.campaign_id
				WHERE LOWER(b.ig_account) = %s AND p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft')
				ORDER BY p.post_date DESC LIMIT 200",
				$handle,
				CP_POST_TYPE
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'     => (int) $r->campaign_id,
				'title'  => html_entity_decode( $r->post_title ? $r->post_title : '#' . $r->campaign_id, ENT_QUOTES ),
				'status' => in_array( $r->status, array( 'confirmed', 'declined' ), true ) ? $r->status : 'pending',
				'guests' => (int) $r->extra_guests,
				'live'   => 'publish' === $r->post_status,
				'date'   => mysql2date( get_option( 'date_format' ), $r->post_date ),
			);
		}
		return $out;
	}

	/**
	 * What's missing from a profile (for the completeness meter).
	 *
	 * @param int $id Blogger ID.
	 * @return array{pct:int,missing:array}
	 */
	public static function completeness( $id ) {
		$c     = CP_Bloggers_UI::compute( $id );
		$items = CP_Bloggers_UI::items();
		$names = array();
		foreach ( $c['missing'] as $k ) {
			$names[] = $items[ $k ];
		}
		return array(
			'pct'     => $c['pct'],
			'missing' => $names,
		);
	}

	/**
	 * Tabbed blogger workspace.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'cp_save_blogger', 'cp_blogger_nonce' );
		$id  = $post->ID;
		$get = static function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, $k, true );
		};

		$first    = $get( '_cp_first' );
		$last     = $get( '_cp_last' );
		$name     = trim( $first . ' ' . $last );
		$handle   = CP_Library::handle( $id );
		$ig_url   = $get( '_cp_ig_url' ) ? $get( '_cp_ig_url' ) : ( $handle ? CP_Library::profile_url( $handle ) : '' );
		$blocked  = '1' === $get( '_cp_blocked' );
		$verified = CP_Verify::is_verified( $id );
		$photo    = CP_Photo::url( $id, 'm' );
		$genders  = CP_Library::genders();
		$collab   = $get( '_cp_collab' );
		$phone    = preg_replace( '/[^\d+]/', '', $get( '_cp_phone' ) );
		$wa       = preg_replace( '/\D/', '', $get( '_cp_whatsapp' ) ? $get( '_cp_whatsapp' ) : $get( '_cp_phone' ) );
		$source   = $get( '_cp_source' );
		$stats    = $handle ? CP_Insights::stats_for_handle( $handle ) : null;
		$label    = $stats && ! empty( $stats['label']['label'] ) ? $stats['label']['label'] : '';
		$history  = self::history( $handle );

		$cats      = CP_Library::category_terms();
		$my_cats   = array_map( 'intval', (array) wp_get_post_terms( $id, CP_Library::TAX_TAG, array( 'fields' => 'ids' ) ) );
		$lists     = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false ) );
		$lists     = is_wp_error( $lists ) ? array() : $lists;
		$my_lists  = array_map( 'intval', (array) wp_get_post_terms( $id, CP_Library::TAX_LIST, array( 'fields' => 'ids' ) ) );
		$birthday  = $get( '_cp_birthday' );
		$age       = '';
		if ( $birthday && strtotime( $birthday ) ) {
			$age = (int) floor( ( time() - strtotime( $birthday ) ) / ( 365.25 * DAY_IN_SECONDS ) );
		}
		?>
		<div class="cpw cpb" id="cpb" data-post="<?php echo (int) $id; ?>">
			<input type="hidden" name="cp_terms_marker" value="1" />

			<?php /* ---------------- Header ---------------- */ ?>
			<div class="cpb-hero">
				<label class="cpb-photo" for="cp_photo_file" title="<?php esc_attr_e( 'Change photo', 'hypeit' ); ?>">
					<span class="cpb-photo-img" id="cpb-photo-img">
						<?php echo CP_Photo::avatar_html( $id, 96, $name, $handle ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<span class="cpb-photo-edit" aria-hidden="true">✎</span>
					<input type="file" id="cp_photo_file" name="cp_photo_file" accept="image/jpeg,image/png,image/webp" class="cpw-hidden" />
				</label>
				<div class="cpb-id">
					<h2 class="cpb-name" id="cpb-name" data-empty="<?php esc_attr_e( 'New blogger', 'hypeit' ); ?>"><?php echo esc_html( $name ? $name : ( $handle ? '@' . $handle : __( 'New blogger', 'hypeit' ) ) ); ?></h2>
					<div class="cpb-handle">
						<?php if ( $handle ) : ?>
							<a href="<?php echo esc_url( $ig_url ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $handle ); ?></a>
						<?php else : ?>
							<span class="cpw-muted"><?php esc_html_e( 'No Instagram yet', 'hypeit' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="cpb-badges">
						<?php if ( $verified ) : ?><span class="cpw-tag is-new">✓ <?php esc_html_e( 'Verified', 'hypeit' ); ?></span><?php endif; ?>
						<?php if ( $blocked ) : ?><span class="cpw-tag is-rm"><?php esc_html_e( 'Blocked', 'hypeit' ); ?></span><?php endif; ?>
						<?php if ( $label ) : ?><span class="cpw-tag"><?php echo esc_html( $label ); ?></span><?php endif; ?>
						<?php
						$rel = CP_Atrium::reliability( $id );
						if ( null !== $rel['rate'] ) :
							?>
							<span class="cpw-tag<?php echo $rel['rate'] >= 80 ? ' is-new' : ( $rel['rate'] < 50 ? ' is-rm' : '' ); ?>" title="<?php esc_attr_e( 'Attended vs confirmed, across ended ATRIUM events', 'hypeit' ); ?>"><?php echo esc_html( sprintf( /* translators: 1: rate, 2: attended, 3: expected. */ __( 'Reliability %1$d%% (%2$d/%3$d)', 'hypeit' ), $rel['rate'], $rel['attended'], $rel['expected'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( 'onboarding' === $source ) : ?><span class="cpw-tag"><?php esc_html_e( 'Joined via form', 'hypeit' ); ?></span><?php endif; ?>
						<?php if ( 'publish' === $post->post_status ) : ?><span class="cpw-tag"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Added %s', 'hypeit' ), get_the_date( '', $post ) ) ); ?></span><?php endif; ?>
					</div>
					<?php if ( $photo ) : ?>
						<label class="cpb-rmphoto"><input type="checkbox" name="cp_photo_remove" value="1" id="cpb-rmphoto" /> <?php esc_html_e( 'Remove photo', 'hypeit' ); ?></label>
					<?php endif; ?>
				</div>
				<div class="cpb-quick">
					<?php if ( $handle ) : ?>
						<a class="button" href="<?php echo esc_url( $ig_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Instagram', 'hypeit' ); ?></a>
						<button type="button" class="button" id="cpb-copy" data-copy="@<?php echo esc_attr( $handle ); ?>"><?php esc_html_e( 'Copy @', 'hypeit' ); ?></button>
					<?php endif; ?>
					<?php if ( $wa ) : ?><a class="button" href="https://wa.me/<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp', 'hypeit' ); ?></a><?php endif; ?>
					<?php if ( $phone ) : ?><a class="button" href="tel:<?php echo esc_attr( $phone ); ?>"><?php esc_html_e( 'Call', 'hypeit' ); ?></a><?php endif; ?>
					<?php if ( is_email( $get( '_cp_email' ) ) ) : ?><a class="button" href="mailto:<?php echo esc_attr( $get( '_cp_email' ) ); ?>"><?php esc_html_e( 'Email', 'hypeit' ); ?></a><?php endif; ?>
				</div>
			</div>

			<nav class="cpw-tabs" role="tablist">
				<button type="button" class="cpw-tab is-active" data-tab="profile"><?php esc_html_e( 'Profile', 'hypeit' ); ?></button>
				<button type="button" class="cpw-tab" data-tab="groups"><?php esc_html_e( 'Categories & lists', 'hypeit' ); ?> <span class="cpw-pill"><?php echo (int) ( count( $my_cats ) + count( $my_lists ) ); ?></span></button>
				<button type="button" class="cpw-tab" data-tab="contact"><?php esc_html_e( 'Contact', 'hypeit' ); ?></button>
				<button type="button" class="cpw-tab" data-tab="verify"><?php esc_html_e( 'Verification', 'hypeit' ); ?><?php echo $verified ? ' <span class="cpb-ok">✓</span>' : ''; ?></button>
				<button type="button" class="cpw-tab" data-tab="campaigns"><?php esc_html_e( 'Campaigns', 'hypeit' ); ?> <span class="cpw-pill"><?php echo (int) count( $history ); ?></span></button>
				<button type="button" class="cpw-tab" data-tab="status"><?php esc_html_e( 'Status', 'hypeit' ); ?><?php echo $blocked ? ' <span class="cpw-dot"></span>' : ''; ?></button>
			</nav>

			<?php /* ---------------- Profile ---------------- */ ?>
			<section class="cpw-panel is-active" data-panel="profile">
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Identity', 'hypeit' ); ?></h3>
					<div class="cpb-grid">
						<label class="cpb-f"><span><?php esc_html_e( 'First name', 'hypeit' ); ?></span><input type="text" id="cp_first" name="cp_first" value="<?php echo esc_attr( $first ); ?>" autocomplete="off" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'Last name', 'hypeit' ); ?></span><input type="text" id="cp_last" name="cp_last" value="<?php echo esc_attr( $last ); ?>" autocomplete="off" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'Instagram username', 'hypeit' ); ?></span>
							<span class="cpb-at" data-check-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_handle_check' ) ); ?>"><em>@</em><input type="text" id="cp_ig" name="cp_ig" value="<?php echo esc_attr( $handle ); ?>" placeholder="<?php esc_attr_e( 'username or profile link', 'hypeit' ); ?>" autocapitalize="none" spellcheck="false" /></span>
						<span class="cpb-dupe" id="cpb-dupe" hidden></span></label>
						<label class="cpb-f"><span><?php esc_html_e( 'Followers', 'hypeit' ); ?> <small id="cpb-fol-hint" class="cpw-muted"></small></span><input type="number" id="cp_followers" name="cp_followers" min="0" step="1" value="<?php echo esc_attr( (int) $get( '_cp_followers' ) ); ?>" /></label>
						<label class="cpb-f cpb-span2"><span><?php esc_html_e( 'Instagram profile link', 'hypeit' ); ?> <small class="cpw-muted"><?php esc_html_e( '(filled in automatically if empty)', 'hypeit' ); ?></small></span><input type="url" id="cp_ig_url" name="cp_ig_url" value="<?php echo esc_attr( $get( '_cp_ig_url' ) ); ?>" /></label>
					</div>
				</div>

				<div class="cpw-card">
					<h3><?php esc_html_e( 'Gender', 'hypeit' ); ?></h3>
					<div class="cpw-chips">
						<?php foreach ( $genders as $key => $lab ) : ?>
							<label class="cpw-chip"><input type="radio" name="cp_gender" value="<?php echo esc_attr( $key ); ?>" <?php checked( $get( '_cp_gender' ), $key ); ?> /> <?php echo esc_html( $lab ); ?></label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="cpw-card">
					<h3><?php esc_html_e( 'Location', 'hypeit' ); ?></h3>
					<div class="cpb-grid">
						<label class="cpb-f"><span><?php esc_html_e( 'Country', 'hypeit' ); ?></span>
							<select id="cp_country" name="cp_country">
								<?php foreach ( CP_Location::countries() as $c => $lab ) : ?>
									<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $get( '_cp_country' ) ? $get( '_cp_country' ) : 'Egypt', $c ); ?>><?php echo esc_html( $lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="cpb-f"><span><?php esc_html_e( 'City', 'hypeit' ); ?></span>
							<select id="cp_city" name="cp_city">
								<option value=""><?php esc_html_e( '— Select —', 'hypeit' ); ?></option>
								<?php foreach ( CP_Location::cities() as $city ) : ?>
									<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $get( '_cp_city' ), $city ); ?>><?php echo esc_html( $city ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</div>

				<div class="cpw-card">
					<h3><?php esc_html_e( 'Open for campaigns', 'hypeit' ); ?></h3>
					<div class="cpw-chips" id="cp-collab-wrap">
						<label class="cpw-chip"><input type="checkbox" id="cp_collab_all_admin" /> <?php esc_html_e( 'All types', 'hypeit' ); ?></label>
						<?php foreach ( CP_Library::collab_types() as $ck => $cl ) : ?>
							<label class="cpw-chip"><input type="checkbox" name="cp_collab[]" value="<?php echo esc_attr( $ck ); ?>" <?php checked( false !== strpos( $collab, ',' . $ck . ',' ) ); ?> /> <?php echo esc_html( $cl ); ?></label>
						<?php endforeach; ?>
					</div>
				</div>
			</section>

			<?php /* ---------------- Categories & lists ---------------- */ ?>
			<section class="cpw-panel" data-panel="groups">
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Categories', 'hypeit' ); ?></h3>
					<?php if ( $cats ) : ?>
						<div class="cpw-chips">
							<?php foreach ( $cats as $t ) : ?>
								<label class="cpw-chip"><input type="checkbox" name="cp_cats[]" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( (int) $t->term_id, $my_cats, true ) ); ?> /> <?php echo esc_html( $t->name ); ?></label>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="cpw-muted"><?php esc_html_e( 'No categories yet.', 'hypeit' ); ?></p>
					<?php endif; ?>
					<p class="cpw-muted"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-tags' ) ); ?>"><?php esc_html_e( 'Manage categories', 'hypeit' ); ?></a></p>
				</div>
				<div class="cpw-card">
					<h3><?php esc_html_e( 'Lists', 'hypeit' ); ?></h3>
					<?php if ( $lists ) : ?>
						<div class="cpw-chips">
							<?php
							foreach ( $lists as $l ) :
								$smart = class_exists( 'CP_Lists' ) && CP_Lists::is_smart( $l->term_id );
								$in    = in_array( (int) $l->term_id, $my_lists, true );
								?>
								<label class="cpw-chip<?php echo $smart ? ' is-locked' : ''; ?>" title="<?php echo $smart ? esc_attr__( 'Smart list — membership is automatic, based on its rules.', 'hypeit' ) : ''; ?>">
									<input type="checkbox" <?php echo $smart ? 'disabled' : 'name="cp_lists[]"'; ?> value="<?php echo (int) $l->term_id; ?>" <?php checked( $in ); ?> />
									<?php echo esc_html( $l->name ); ?><?php echo $smart ? ' <small>' . esc_html__( 'auto', 'hypeit' ) . '</small>' : ''; ?>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<div class="cpw-row" style="margin-top:12px;">
						<input type="text" name="cp_new_list" class="cpw-grow" placeholder="<?php esc_attr_e( 'Add to a new list…', 'hypeit' ); ?>" />
					</div>
					<p class="cpw-muted"><?php esc_html_e( 'Lists marked “auto” are smart lists: they include this blogger automatically when their rules match.', 'hypeit' ); ?></p>
				</div>
			</section>

			<?php /* ---------------- Contact ---------------- */ ?>
			<section class="cpw-panel" data-panel="contact">
				<div class="cpw-note" style="margin-bottom:14px;">🔒 <?php esc_html_e( 'Private — visible to admins only, never shown to clients.', 'hypeit' ); ?></div>
				<div class="cpw-card">
					<div class="cpb-grid">
						<label class="cpb-f cpb-span2"><span><?php esc_html_e( 'Email', 'hypeit' ); ?></span><input type="email" id="cp_email" name="cp_email" value="<?php echo esc_attr( $get( '_cp_email' ) ); ?>" autocomplete="off" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'Phone', 'hypeit' ); ?></span><input type="tel" id="cp_phone" name="cp_phone" value="<?php echo esc_attr( $get( '_cp_phone' ) ); ?>" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'WhatsApp', 'hypeit' ); ?> <small class="cpw-muted"><?php esc_html_e( '(if different)', 'hypeit' ); ?></small></span><input type="tel" id="cp_whatsapp" name="cp_whatsapp" value="<?php echo esc_attr( $get( '_cp_whatsapp' ) ); ?>" /></label>
						<label class="cpb-f"><span><?php esc_html_e( 'Birthday', 'hypeit' ); ?> <?php echo '' !== $age ? '<small class="cpw-muted">' . esc_html( sprintf( /* translators: %d: age. */ __( '(%d years)', 'hypeit' ), $age ) ) . '</small>' : ''; ?></span><input type="date" id="cp_birthday" name="cp_birthday" value="<?php echo esc_attr( $birthday ); ?>" /></label>
					</div>
				</div>
			</section>

			<?php /* ---------------- Verification ---------------- */ ?>
			<section class="cpw-panel" data-panel="verify">
				<div class="cpw-card">
					<?php CP_Verify::render_box( $post ); ?>
				</div>
			</section>

			<?php /* ---------------- Campaigns ---------------- */ ?>
			<section class="cpw-panel" data-panel="campaigns">
				<?php if ( $stats ) : ?>
					<div class="cpw-stats">
						<div class="cpw-stat"><b><?php echo (int) $stats['included']; ?></b><span><?php esc_html_e( 'Campaigns', 'hypeit' ); ?></span></div>
						<div class="cpw-stat is-green"><b><?php echo (int) $stats['confirmed']; ?></b><span><?php esc_html_e( 'Accepted', 'hypeit' ); ?></span></div>
						<div class="cpw-stat is-red"><b><?php echo (int) $stats['declined']; ?></b><span><?php esc_html_e( 'Declined', 'hypeit' ); ?></span></div>
						<div class="cpw-stat is-dark"><b><?php echo (int) $stats['acceptance_rate']; ?>%</b><span><?php esc_html_e( 'Acceptance', 'hypeit' ); ?></span></div>
					</div>
				<?php endif; ?>
				<?php if ( $history ) : ?>
					<div class="cpw-tablewrap">
						<table class="cpw-table">
							<thead><tr>
								<th><?php esc_html_e( 'Campaign', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'Date', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'Client response', 'hypeit' ); ?></th>
								<th><?php esc_html_e( 'People', 'hypeit' ); ?></th>
								<?php if ( CP_Atrium::active() ) : ?><th><?php esc_html_e( 'Event', 'hypeit' ); ?></th><?php endif; ?>
							</tr></thead>
							<tbody>
								<?php
								$ev_st  = CP_Atrium::stages_for_blogger( $id );
								$ev_lab = CP_Atrium::stage_labels();
								$map = array( 'confirmed' => __( 'Confirmed', 'hypeit' ), 'declined' => __( 'Declined', 'hypeit' ), 'pending' => __( 'No response', 'hypeit' ) );
								foreach ( $history as $h ) :
									?>
									<tr>
										<td data-label=""><a href="<?php echo esc_url( get_edit_post_link( $h['id'] ) ); ?>"><strong><?php echo esc_html( $h['title'] ); ?></strong></a><?php echo $h['live'] ? '' : ' <span class="cpw-tag">' . esc_html__( 'Draft', 'hypeit' ) . '</span>'; ?></td>
										<td data-label="<?php esc_attr_e( 'Date', 'hypeit' ); ?>"><?php echo esc_html( $h['date'] ); ?></td>
										<td data-label="<?php esc_attr_e( 'Response', 'hypeit' ); ?>"><span class="cp-badge cp-badge-<?php echo esc_attr( $h['status'] ); ?>"><?php echo esc_html( $map[ $h['status'] ] ); ?></span></td>
										<td data-label="<?php esc_attr_e( 'People', 'hypeit' ); ?>"><?php echo 'confirmed' === $h['status'] ? (int) ( 1 + $h['guests'] ) : '&mdash;'; ?></td>
										<?php if ( CP_Atrium::active() ) : ?>
											<td data-label="<?php esc_attr_e( 'Event', 'hypeit' ); ?>"><?php echo isset( $ev_st[ $h['id'] ] ) ? '<span class="cpa-stage cpa-' . esc_attr( $ev_st[ $h['id'] ] ) . '">' . esc_html( $ev_lab[ $ev_st[ $h['id'] ] ] ) . '</span>' : '&mdash;'; ?></td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p class="cpw-empty"><?php esc_html_e( 'Not part of any campaign yet.', 'hypeit' ); ?></p>
				<?php endif; ?>
			</section>

			<?php /* ---------------- Status ---------------- */ ?>
			<section class="cpw-panel" data-panel="status">
				<div class="cpw-card">
					<?php self::switch_row( 'cp_blocked', $blocked, __( 'Blocked', 'hypeit' ), __( 'Hidden from campaigns, lists and client pages, and can’t re-submit the onboarding form.', 'hypeit' ) ); ?>
				</div>
				<?php if ( 'auto-draft' !== $post->post_status && current_user_can( 'delete_post', $id ) ) : ?>
					<div class="cpw-card cpw-danger">
						<h3><?php esc_html_e( 'Delete', 'hypeit' ); ?></h3>
						<p>
							<a class="button" href="<?php echo esc_url( get_delete_post_link( $id ) ); ?>"><?php esc_html_e( 'Move to trash', 'hypeit' ); ?></a>
							<a class="button" style="color:#b3261e;border-color:#e3b4b0;" href="<?php echo esc_url( self::action_url( 'cp_delete_block', $id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this blogger permanently and block them from re-applying?', 'hypeit' ) ); ?>');"><?php esc_html_e( 'Delete & block', 'hypeit' ); ?></a>
						</p>
						<p class="cpw-muted"><?php esc_html_e( 'Delete & block removes the profile permanently and stops this Instagram account from applying again.', 'hypeit' ); ?></p>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * On/off switch row (matches the campaign editor).
	 *
	 * @param string $name    Field.
	 * @param bool   $checked State.
	 * @param string $label   Label.
	 * @param string $help    Help.
	 */
	private static function switch_row( $name, $checked, $label, $help ) {
		?>
		<label class="cpw-switchrow">
			<span class="cpw-switch"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> /><span class="cpw-slider" aria-hidden="true"></span></span>
			<span class="cpw-switchtext"><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $help ); ?></small></span>
		</label>
		<?php
	}

	/**
	 * Sidebar: key numbers + profile completeness.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_glance( $post ) {
		$id     = $post->ID;
		$handle = CP_Library::handle( $id );
		$stats  = $handle ? CP_Insights::stats_for_handle( $handle ) : null;
		$comp   = self::completeness( $id );
		$f      = (int) get_post_meta( $id, '_cp_followers', true );
		$reach  = (int) get_post_meta( $id, '_cp_reach', true );
		?>
		<div class="cpw-glance">
			<div class="cpw-glance-stats">
				<span><b><?php echo $f ? esc_html( number_format_i18n( $f ) ) : '—'; ?></b><?php esc_html_e( 'Followers', 'hypeit' ); ?></span>
				<?php $eng = get_post_meta( $id, '_cp_engagement', true ); ?>
				<?php if ( '' !== $eng ) : ?>
					<span><b><?php echo esc_html( number_format_i18n( (float) $eng, 2 ) ); ?>%</b><?php esc_html_e( 'Engagement', 'hypeit' ); ?></span>
				<?php else : ?>
					<span><b><?php echo $reach ? esc_html( number_format_i18n( $reach ) ) : '—'; ?></b><?php esc_html_e( 'Reach (28d)', 'hypeit' ); ?></span>
				<?php endif; ?>
				<span><b><?php echo $stats ? (int) $stats['included'] : 0; ?></b><?php esc_html_e( 'Campaigns', 'hypeit' ); ?></span>
				<span class="is-green"><b><?php echo $stats && ( $stats['confirmed'] + $stats['declined'] ) ? (int) $stats['acceptance_rate'] . '%' : '—'; ?></b><?php esc_html_e( 'Acceptance', 'hypeit' ); ?></span>
				<?php
				$rel = CP_Atrium::reliability( $id );
				if ( CP_Atrium::active() ) :
					?>
					<span class="is-dark" style="grid-column:1/-1;"><b><?php echo null === $rel['rate'] ? '—' : (int) $rel['rate'] . '%'; ?></b><?php echo esc_html( null === $rel['rate'] ? __( 'Reliability — no ended events yet', 'hypeit' ) : sprintf( /* translators: 1: attended, 2: expected. */ __( 'Reliability — attended %1$d of %2$d', 'hypeit' ), $rel['attended'], $rel['expected'] ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="cpb-meter" title="<?php esc_attr_e( 'Profile completeness', 'hypeit' ); ?>">
				<div class="cpb-meter-top"><strong><?php esc_html_e( 'Profile', 'hypeit' ); ?></strong><span><?php echo (int) $comp['pct']; ?>%</span></div>
				<div class="cpb-meter-bar"><i style="width:<?php echo (int) $comp['pct']; ?>%"></i></div>
				<?php if ( $comp['missing'] ) : ?>
					<p class="cpw-muted" style="margin:6px 0 0;"><?php echo esc_html( sprintf( /* translators: %s: missing fields. */ __( 'Missing: %s', 'hypeit' ), implode( ', ', $comp['missing'] ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save metabox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( self::$saving ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['cp_blogger_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_blogger_nonce'] ) ), 'cp_save_blogger' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Profile photo (private storage, not the Media Library).
		if ( ! empty( $_POST['cp_photo_remove'] ) ) {
			CP_Photo::remove( $post_id );
		}
		if ( ! empty( $_FILES['cp_photo_file'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['cp_photo_file']['error'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$res = CP_Photo::set_from_upload( $post_id, $_FILES['cp_photo_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_wp_error( $res ) ) {
				set_transient( 'cp_photo_err_' . get_current_user_id(), $res->get_error_message(), 60 );
			}
		}

		$first = isset( $_POST['cp_first'] ) ? CP_Library::normalize_name( wp_unslash( $_POST['cp_first'] ) ) : '';
		$last  = isset( $_POST['cp_last'] ) ? CP_Library::normalize_name( wp_unslash( $_POST['cp_last'] ) ) : '';
		update_post_meta( $post_id, '_cp_first', $first );
		update_post_meta( $post_id, '_cp_last', $last );

		$ig = isset( $_POST['cp_ig'] ) ? CP_Library::extract_handle( wp_unslash( $_POST['cp_ig'] ) ) : '';
		// One Instagram account = one profile: refuse a username another profile already uses.
		$owner = '' !== $ig ? CP_Dupes::owner( $ig, $post_id ) : 0;
		if ( $owner ) {
			CP_Dupes::flag( $ig, $owner );
			$ig = CP_Library::handle( $post_id ); // Keep whatever this profile had before.
		} else {
			update_post_meta( $post_id, '_cp_ig', $ig );
		}

		$url = isset( $_POST['cp_ig_url'] ) ? esc_url_raw( wp_unslash( $_POST['cp_ig_url'] ) ) : '';
		if ( '' === $url && '' !== $ig ) {
			$url = CP_Library::profile_url( $ig );
		}
		update_post_meta( $post_id, '_cp_ig_url', $url );

		$gender = isset( $_POST['cp_gender'] ) ? sanitize_key( wp_unslash( $_POST['cp_gender'] ) ) : '';
		if ( ! array_key_exists( $gender, CP_Library::genders() ) ) {
			$gender = '';
		}
		update_post_meta( $post_id, '_cp_gender', $gender );

		update_post_meta( $post_id, '_cp_followers', isset( $_POST['cp_followers'] ) ? absint( $_POST['cp_followers'] ) : 0 );

		update_post_meta( $post_id, '_cp_country', isset( $_POST['cp_country'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_country'] ) ) : 'Egypt' );
		update_post_meta( $post_id, '_cp_city', isset( $_POST['cp_city'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_city'] ) ) : '' );
		update_post_meta( $post_id, '_cp_email', isset( $_POST['cp_email'] ) ? sanitize_email( wp_unslash( $_POST['cp_email'] ) ) : '' );

		$collab_types = CP_Library::collab_types();
		$collab_sel   = isset( $_POST['cp_collab'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['cp_collab'] ) ) : array();
		$collab_sel   = array_values( array_intersect( array_keys( $collab_types ), $collab_sel ) );
		update_post_meta( $post_id, '_cp_collab', empty( $collab_sel ) ? '' : ',' . implode( ',', $collab_sel ) . ',' );

		// Private.
		update_post_meta( $post_id, '_cp_birthday', isset( $_POST['cp_birthday'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_birthday'] ) ) : '' );
		update_post_meta( $post_id, '_cp_phone', isset( $_POST['cp_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_phone'] ) ) : '' );
		update_post_meta( $post_id, '_cp_whatsapp', isset( $_POST['cp_whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_whatsapp'] ) ) : '' );

		// Blocked.
		$blocked = ! empty( $_POST['cp_blocked'] );
		update_post_meta( $post_id, '_cp_blocked', $blocked ? '1' : '0' );
		if ( $blocked && '' !== $ig ) {
			CP_Library::block_handle( $ig );
		} elseif ( ! $blocked && '' !== $ig ) {
			CP_Library::unblock_handle( $ig );
		}

		// Categories + manual lists (from the workspace chips).
		if ( isset( $_POST['cp_terms_marker'] ) ) {
			$cats = isset( $_POST['cp_cats'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['cp_cats'] ) ) ) : array();
			wp_set_post_terms( $post_id, array_values( $cats ), CP_Library::TAX_TAG, false );

			$lists = isset( $_POST['cp_lists'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['cp_lists'] ) ) ) : array();
			// Keep smart-list memberships — those are managed by their rules.
			foreach ( (array) wp_get_post_terms( $post_id, CP_Library::TAX_LIST, array( 'fields' => 'ids' ) ) as $tid ) {
				if ( class_exists( 'CP_Lists' ) && CP_Lists::is_smart( $tid ) ) {
					$lists[] = (int) $tid;
				}
			}
			$new_list = isset( $_POST['cp_new_list'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_new_list'] ) ) : '';
			if ( '' !== $new_list ) {
				$term = get_term_by( 'name', $new_list, CP_Library::TAX_LIST );
				if ( ! $term ) {
					$made = wp_insert_term( $new_list, CP_Library::TAX_LIST );
					$term = is_wp_error( $made ) ? null : get_term( $made['term_id'], CP_Library::TAX_LIST );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$lists[] = (int) $term->term_id;
				}
			}
			wp_set_post_terms( $post_id, array_values( array_unique( $lists ) ), CP_Library::TAX_LIST, false );
		}

		self::sync_title( $post_id );

		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $post_id );
		}
	}

	/**
	 * Set the post title from the name (name only; falls back to @handle when
	 * there is no name). The handle is stored in post_content so it stays
	 * searchable without being displayed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync_title( $post_id ) {
		$first  = get_post_meta( $post_id, '_cp_first', true );
		$last   = get_post_meta( $post_id, '_cp_last', true );
		$handle = CP_Library::handle( $post_id );
		$name   = trim( $first . ' ' . $last );

		if ( '' !== $name ) {
			$title = $name;
		} else {
			$title = $handle ? '@' . $handle : __( '(no name)', 'hypeit' );
		}
		$content = $handle; // Hidden, keeps the handle searchable.

		$post = get_post( $post_id );
		if ( $post && $post->post_title === $title && $post->post_content === $content ) {
			return;
		}

		self::$saving = true;
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
		self::$saving = false;
	}

	/**
	 * Editor assets (blogger edit screen only).
	 */
	public static function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || CP_Library::CPT !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		wp_enqueue_style( 'cp-admin', CP_URL . 'assets/css/admin.css', array(), CP_VERSION );
		wp_enqueue_script( 'cp-blogger-admin', CP_URL . 'assets/js/blogger-admin.js', array(), CP_VERSION, true );
	}

	/**
	 * Allow file uploads on the blogger edit form.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function form_enctype( $post ) {
		if ( $post && CP_Library::CPT === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/**
	 * Show a photo processing error after save.
	 */
	public static function photo_notice() {
		$msg = get_transient( 'cp_photo_err_' . get_current_user_id() );
		if ( $msg ) {
			delete_transient( 'cp_photo_err_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	/**
	 * List table columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['cp_photo'] = '<span class="screen-reader-text">' . esc_html__( 'Photo', 'hypeit' ) . '</span>';
			}
			if ( 'date' === $key ) {
				$new['cp_ig']        = __( 'Instagram', 'hypeit' );
				$new['cp_verified']  = __( 'Verified', 'hypeit' );
				$new['cp_gender']    = __( 'Gender', 'hypeit' );
				$new['cp_followers'] = __( 'Followers', 'hypeit' );
				$new['cp_popular']   = __( 'Popularity', 'hypeit' );
				$new['cp_city']      = __( 'City', 'hypeit' );
				$new['cp_blocked']   = __( 'Blocked', 'hypeit' );
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'cp_photo' === $column ) {
			echo CP_Photo::avatar_html( $post_id, 40, trim( get_post_meta( $post_id, '_cp_first', true ) . ' ' . get_post_meta( $post_id, '_cp_last', true ) ), CP_Library::handle( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( 'cp_ig' === $column ) {
			$h = CP_Library::handle( $post_id );
			if ( $h ) {
				$url = get_post_meta( $post_id, '_cp_ig_url', true );
				if ( ! $url ) {
					$url = CP_Library::profile_url( $h );
				}
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">@' . esc_html( $h ) . '</a>';
				if ( 'personal' === get_post_meta( $post_id, '_cp_ig_status', true ) ) {
					echo ' <span title="' . esc_attr__( 'Instagram couldn’t read this account: personal/private account, or a wrong username.', 'hypeit' ) . '" style="display:inline-block;font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:#fcf0e3;color:#8a4b00;">' . esc_html__( 'Personal', 'hypeit' ) . '</span>';
				}
			} else {
				echo '&mdash;';
			}
		} elseif ( 'cp_verified' === $column ) {
			echo CP_Verify::is_verified( $post_id ) ? '<span style="color:#1b7f4b;font-weight:800;" title="' . esc_attr__( 'Verified', 'hypeit' ) . '">✓</span>' : '<span style="color:#a7aaad;">&mdash;</span>';
		} elseif ( 'cp_gender' === $column ) {
			$g       = get_post_meta( $post_id, '_cp_gender', true );
			$genders = CP_Library::genders();
			echo isset( $genders[ $g ] ) ? esc_html( $genders[ $g ] ) : '&mdash;';
		} elseif ( 'cp_followers' === $column ) {
			$f = (int) get_post_meta( $post_id, '_cp_followers', true );
			echo $f ? esc_html( number_format_i18n( $f ) ) : '&mdash;';
		} elseif ( 'cp_popular' === $column ) {
			$lab = CP_Insights::label_for( CP_Library::handle( $post_id ) );
			echo $lab['label'] ? '<span style="font-weight:700;">' . esc_html( $lab['label'] ) . '</span>' : '&mdash;';
		} elseif ( 'cp_city' === $column ) {
			$city = get_post_meta( $post_id, '_cp_city', true );
			echo $city ? esc_html( $city ) : '&mdash;';
		} elseif ( 'cp_blocked' === $column ) {
			echo '1' === (string) get_post_meta( $post_id, '_cp_blocked', true )
				? '<span style="color:#b3261e;font-weight:700;">' . esc_html__( 'Blocked', 'hypeit' ) . '</span>'
				: '&mdash;';
		}
	}

	/**
	 * Remove WordPress's "Mine" view — authorship isn't meaningful here.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function drop_mine( $views ) {
		unset( $views['mine'] );
		return $views;
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['cp_followers'] = 'cp_followers';
		$columns['cp_verified']  = array( 'cp_verified', true );
		$columns['cp_city']      = 'cp_city';
		return $columns;
	}

	/**
	 * Admin filter dropdowns.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function filters( $post_type ) {
		if ( CP_Library::CPT !== $post_type ) {
			return;
		}

		// Gender.
		$g = isset( $_GET['cp_gender_filter'] ) ? sanitize_key( wp_unslash( $_GET['cp_gender_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="cp_gender_filter"><option value="">' . esc_html__( 'All genders', 'hypeit' ) . '</option>';
		foreach ( CP_Library::genders() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $g, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// City.
		$c = isset( $_GET['cp_city_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['cp_city_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="cp_city_filter"><option value="">' . esc_html__( 'All cities', 'hypeit' ) . '</option>';
		foreach ( CP_Location::cities() as $city ) {
			echo '<option value="' . esc_attr( $city ) . '" ' . selected( $c, $city, false ) . '>' . esc_html( $city ) . '</option>';
		}
		echo '</select>';

		// Blocked.
		$b = isset( $_GET['cp_blocked_filter'] ) ? sanitize_key( wp_unslash( $_GET['cp_blocked_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="cp_blocked_filter">';
		echo '<option value="">' . esc_html__( 'All (blocked + active)', 'hypeit' ) . '</option>';
		echo '<option value="active" ' . selected( $b, 'active', false ) . '>' . esc_html__( 'Active only', 'hypeit' ) . '</option>';
		echo '<option value="blocked" ' . selected( $b, 'blocked', false ) . '>' . esc_html__( 'Blocked only', 'hypeit' ) . '</option>';
		echo '</select>';

		// Instagram sync status.
		$ig = isset( $_GET['cp_igstatus'] ) ? sanitize_key( wp_unslash( $_GET['cp_igstatus'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="cp_igstatus">';
		echo '<option value="">' . esc_html__( 'Any Instagram status', 'hypeit' ) . '</option>';
		echo '<option value="personal" ' . selected( $ig, 'personal', false ) . '>' . esc_html__( 'Personal / can’t read', 'hypeit' ) . '</option>';
		echo '<option value="ok" ' . selected( $ig, 'ok', false ) . '>' . esc_html__( 'Creator / Business (synced)', 'hypeit' ) . '</option>';
		echo '<option value="none" ' . selected( $ig, 'none', false ) . '>' . esc_html__( 'Not checked yet', 'hypeit' ) . '</option>';
		echo '</select>';

		// Verified.
		$v = isset( $_GET['cp_verified_filter'] ) ? sanitize_key( wp_unslash( $_GET['cp_verified_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="cp_verified_filter">';
		echo '<option value="">' . esc_html__( 'Verified + not verified', 'hypeit' ) . '</option>';
		echo '<option value="yes" ' . selected( $v, 'yes', false ) . '>' . esc_html__( 'Verified only', 'hypeit' ) . '</option>';
		echo '<option value="no" ' . selected( $v, 'no', false ) . '>' . esc_html__( 'Not verified', 'hypeit' ) . '</option>';
		echo '</select>';

		// List + Category.
		foreach ( array( CP_Library::TAX_LIST => __( 'All lists', 'hypeit' ), CP_Library::TAX_TAG => __( 'All categories', 'hypeit' ) ) as $tax => $all ) {
			$selected = isset( $_GET[ $tax ] ) ? sanitize_text_field( wp_unslash( $_GET[ $tax ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_dropdown_categories(
				array(
					'taxonomy'        => $tax,
					'name'            => $tax,
					'value_field'     => 'slug',
					'show_option_all' => $all,
					'hide_empty'      => false,
					'selected'        => $selected,
				)
			);
		}
	}

	/**
	 * Apply meta filters + sorting.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function apply_filters_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( CP_Library::CPT !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta = array();

		if ( ! empty( $_GET['cp_gender_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta[] = array(
				'key'   => '_cp_gender',
				'value' => sanitize_key( wp_unslash( $_GET['cp_gender_filter'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}
		if ( ! empty( $_GET['cp_city_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta[] = array(
				'key'   => '_cp_city',
				'value' => sanitize_text_field( wp_unslash( $_GET['cp_city_filter'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}
		if ( ! empty( $_GET['cp_blocked_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$bf = sanitize_key( wp_unslash( $_GET['cp_blocked_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'blocked' === $bf ) {
				$meta[] = array(
					'key'   => '_cp_blocked',
					'value' => '1',
				);
			} elseif ( 'active' === $bf ) {
				$meta[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_cp_blocked',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_cp_blocked',
						'value'   => '1',
						'compare' => '!=',
					),
				);
			}
		}

		if ( ! empty( $_GET['cp_igstatus'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$igs = sanitize_key( wp_unslash( $_GET['cp_igstatus'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $igs, array( 'personal', 'ok' ), true ) ) {
				$meta[] = array( 'key' => '_cp_ig_status', 'value' => $igs );
			} elseif ( 'none' === $igs ) {
				$meta[] = array( 'key' => '_cp_ig_status', 'compare' => 'NOT EXISTS' );
			}
		}
		if ( ! empty( $_GET['cp_verified_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$vf = sanitize_key( wp_unslash( $_GET['cp_verified_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'yes' === $vf ) {
				$meta[] = array( 'key' => '_cp_verified', 'value' => '1' );
			} elseif ( 'no' === $vf ) {
				$meta[] = array(
					'relation' => 'OR',
					array( 'key' => '_cp_verified', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_cp_verified', 'value' => '1', 'compare' => '!=' ),
				);
			}
		}

		$orderby = $query->get( 'orderby' );
		if ( 'cp_verified' === $orderby ) {
			// Verified first (or last), then by name; bloggers without the flag included.
			$meta['cp_ver'] = array(
				'relation'  => 'OR',
				'cp_ver_y'  => array( 'key' => '_cp_verified', 'compare' => 'EXISTS' ),
				'cp_ver_n'  => array( 'key' => '_cp_verified', 'compare' => 'NOT EXISTS' ),
			);
			$dir = 'asc' === strtolower( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
			$query->set( 'orderby', array( 'cp_ver_y' => $dir, 'title' => 'ASC' ) );
		}

		if ( $meta ) {
			if ( count( $meta ) > 1 ) {
				$meta['relation'] = 'AND';
			}
			$query->set( 'meta_query', $meta );
		}

		if ( 'cp_followers' === $orderby ) {
			$query->set( 'meta_key', '_cp_followers' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'cp_city' === $orderby ) {
			$query->set( 'meta_key', '_cp_city' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Block / delete                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Row actions.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( CP_Library::CPT !== $post->post_type ) {
			return $actions;
		}
		$blocked = '1' === (string) get_post_meta( $post->ID, '_cp_blocked', true );

		if ( $blocked ) {
			$actions['cp_unblock'] = '<a href="' . esc_url( self::action_url( 'cp_unblock', $post->ID ) ) . '">' . esc_html__( 'Unblock', 'hypeit' ) . '</a>';
		} else {
			$actions['cp_block'] = '<a href="' . esc_url( self::action_url( 'cp_block', $post->ID ) ) . '">' . esc_html__( 'Block', 'hypeit' ) . '</a>';
		}
		if ( 'personal' === get_post_meta( $post->ID, '_cp_ig_status', true ) ) {
			$wa = CP_IGSync::switch_wa( $post->ID );
			if ( $wa ) {
				$actions['cp_ask_switch'] = '<a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener" style="color:#1b7f4b;font-weight:600;">' . esc_html__( 'Ask to switch (WhatsApp)', 'hypeit' ) . '</a>';
			}
		}
		$actions['cp_delete_block'] = '<a href="' . esc_url( self::action_url( 'cp_delete_block', $post->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this blogger and block them from re-submitting?', 'hypeit' ) ) . '\');" style="color:#b3261e;">' . esc_html__( 'Delete &amp; Block', 'hypeit' ) . '</a>';

		return $actions;
	}

	/**
	 * Build a nonced admin-post action URL.
	 *
	 * @param string $action  Action.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private static function action_url( $action, $post_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&post=' . $post_id ),
			$action . '_' . $post_id
		);
	}

	/**
	 * Redirect back to the list.
	 */
	private static function back() {
		$ref = wp_get_referer();
		wp_safe_redirect( $ref ? $ref : admin_url( 'edit.php?post_type=' . CP_Library::CPT ) );
		exit;
	}

	/**
	 * Block action.
	 */
	public static function action_block() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_block_' . $id );
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			update_post_meta( $id, '_cp_blocked', '1' );
			CP_Library::block_handle( CP_Library::handle( $id ) );
		}
		self::back();
	}

	/**
	 * Unblock action.
	 */
	public static function action_unblock() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_unblock_' . $id );
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			update_post_meta( $id, '_cp_blocked', '0' );
			CP_Library::unblock_handle( CP_Library::handle( $id ) );
		}
		self::back();
	}

	/**
	 * Delete & block action.
	 */
	public static function action_delete_block() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_delete_block_' . $id );
		if ( $id && current_user_can( 'delete_post', $id ) ) {
			CP_Library::block_handle( CP_Library::handle( $id ) );
			wp_delete_post( $id, true );
		}
		// The edit screen we came from no longer exists — always return to the list.
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CP_Library::CPT ) );
		exit;
	}

	/**
	 * Bulk actions.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		$actions['cp_block']   = __( 'Block', 'hypeit' );
		$actions['cp_unblock'] = __( 'Unblock', 'hypeit' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param array  $ids      Post IDs.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'cp_block' !== $action && 'cp_unblock' !== $action ) {
			return $redirect;
		}
		$block = ( 'cp_block' === $action );
		foreach ( (array) $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			update_post_meta( $id, '_cp_blocked', $block ? '1' : '0' );
			if ( $block ) {
				CP_Library::block_handle( CP_Library::handle( $id ) );
			} else {
				CP_Library::unblock_handle( CP_Library::handle( $id ) );
			}
		}
		return add_query_arg( 'cp_bulk_blocked', count( (array) $ids ), $redirect );
	}
}
