<?php
/**
 * Lists management + Smart Lists.
 *
 * A list can be "smart": it stores rules (tags, gender, city, followers,
 * verified) and any blogger who matches is added automatically — including new
 * bloggers as they're created. Location (country/city) is a structured rule,
 * not a tag. Manual lists (no rules) are left untouched by the auto-sync.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Lists {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_cp_list_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cp_list_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_cp_list_rebuild', array( __CLASS__, 'handle_rebuild' ) );
		add_action( 'admin_post_cp_list_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'wp_ajax_cp_list_preview', array( __CLASS__, 'ajax_preview' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Rules                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Get a list's rules.
	 *
	 * @param int $term_id Term ID.
	 * @return array
	 */
	public static function get_rules( $term_id ) {
		$tags = get_term_meta( $term_id, 'cp_rule_tags', true );
		return array(
			'enabled'       => '1' === (string) get_term_meta( $term_id, 'cp_rule_enabled', true ),
			'all'           => '1' === (string) get_term_meta( $term_id, 'cp_rule_all', true ),
			'gender'        => (string) get_term_meta( $term_id, 'cp_rule_gender', true ),
			'city'          => (string) get_term_meta( $term_id, 'cp_rule_city', true ),
			'tag_ids'       => is_array( $tags ) ? array_map( 'absint', $tags ) : array(),
			'tag_match'     => 'all' === get_term_meta( $term_id, 'cp_rule_tag_match', true ) ? 'all' : 'any',
			'followers_min' => (string) get_term_meta( $term_id, 'cp_rule_fmin', true ),
			'followers_max' => (string) get_term_meta( $term_id, 'cp_rule_fmax', true ),
			'verified'      => '1' === (string) get_term_meta( $term_id, 'cp_rule_verified', true ),
			'collab'        => (string) get_term_meta( $term_id, 'cp_rule_collab', true ),
		);
	}

	/**
	 * Whether a rule set has at least one active condition.
	 *
	 * @param array $r Rules.
	 * @return bool
	 */
	public static function has_rules( $r ) {
		return ! empty( $r['all'] ) || $r['gender'] || $r['city'] || $r['verified'] || ! empty( $r['collab'] ) || '' !== $r['followers_min'] || '' !== $r['followers_max'] || ! empty( $r['tag_ids'] );
	}

	/**
	 * Is this list a smart (rule-driven) list?
	 *
	 * @param int $term_id Term ID.
	 * @return bool
	 */
	public static function is_smart( $term_id ) {
		$r = self::get_rules( $term_id );
		return $r['enabled'] && self::has_rules( $r );
	}

	/**
	 * Smart list term IDs (enabled + at least one condition).
	 *
	 * @return array
	 */
	private static function smart_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => CP_Library::TAX_LIST,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => 'cp_rule_enabled', 'value' => '1' ),
				),
			)
		);
		return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
	}

	/**
	 * Does a blogger match a rule set?
	 *
	 * @param int   $id Blogger ID.
	 * @param array $r  Rules.
	 * @return bool
	 */
	public static function matches_blogger( $id, $r ) {
		if ( '1' === (string) get_post_meta( $id, '_cp_blocked', true ) || CP_Library::is_inactive( $id ) ) {
			return false;
		}
		if ( ! empty( $r['all'] ) ) {
			return true;
		}
		if ( '' !== $r['gender'] && get_post_meta( $id, '_cp_gender', true ) !== $r['gender'] ) {
			return false;
		}
		if ( '' !== $r['city'] && get_post_meta( $id, '_cp_city', true ) !== $r['city'] ) {
			return false;
		}
		if ( $r['verified'] && '1' !== (string) get_post_meta( $id, '_cp_verified', true ) ) {
			return false;
		}
		if ( ! empty( $r['collab'] ) && false === strpos( (string) get_post_meta( $id, '_cp_collab', true ), ',' . $r['collab'] . ',' ) ) {
			return false;
		}
		$f = (int) get_post_meta( $id, '_cp_followers', true );
		if ( '' !== $r['followers_min'] && $f < (int) $r['followers_min'] ) {
			return false;
		}
		if ( '' !== $r['followers_max'] && $f > (int) $r['followers_max'] ) {
			return false;
		}
		if ( ! empty( $r['tag_ids'] ) ) {
			$have = wp_get_post_terms( $id, CP_Library::TAX_TAG, array( 'fields' => 'ids' ) );
			$have = is_wp_error( $have ) ? array() : array_map( 'intval', $have );
			$inter = array_intersect( $r['tag_ids'], $have );
			if ( 'all' === $r['tag_match'] ) {
				if ( count( $inter ) !== count( $r['tag_ids'] ) ) {
					return false;
				}
			} elseif ( empty( $inter ) ) {
				return false;
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Auto-sync                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Add/remove a blogger from every smart list based on its rules.
	 * Called whenever a blogger is created or changed.
	 *
	 * @param int $id Blogger ID.
	 */
	public static function sync_blogger( $id ) {
		if ( CP_Library::CPT !== get_post_type( $id ) ) {
			return;
		}
		// New/updated bloggers auto-join campaigns that have Everyone on.
		if ( class_exists( 'CP_Everyone' ) ) {
			CP_Everyone::on_blogger_saved( $id );
		}
		foreach ( self::smart_terms() as $term_id ) {
			$rules = self::get_rules( $term_id );
			if ( ! self::has_rules( $rules ) ) {
				continue;
			}
			if ( self::matches_blogger( $id, $rules ) ) {
				wp_set_post_terms( $id, array( $term_id ), CP_Library::TAX_LIST, true );
			} else {
				wp_remove_object_terms( $id, $term_id, CP_Library::TAX_LIST );
			}
		}
	}

	/**
	 * Recompute a smart list's full membership from its rules.
	 *
	 * @param int $term_id Term ID.
	 * @return int Members after rebuild.
	 */
	public static function rebuild( $term_id ) {
		$rules = self::get_rules( $term_id );
		if ( ! self::has_rules( $rules ) ) {
			return 0;
		}
		$match   = CP_Library::filter_ids( $rules );
		$current = get_objects_in_term( $term_id, CP_Library::TAX_LIST );
		$current = is_wp_error( $current ) ? array() : array_map( 'intval', $current );

		foreach ( array_diff( $match, $current ) as $aid ) {
			wp_set_post_terms( $aid, array( $term_id ), CP_Library::TAX_LIST, true );
		}
		foreach ( array_diff( $current, $match ) as $rid ) {
			wp_remove_object_terms( $rid, $term_id, CP_Library::TAX_LIST );
		}
		return count( $match );
	}

	/* ------------------------------------------------------------------ */
	/* Admin page                                                          */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Manage Lists', 'hypeit' ),
			__( 'Manage Lists', 'hypeit' ),
			'manage_categories',
			'cp-lists',
			array( __CLASS__, 'render' )
		);
	}

	private static function page_url() {
		return admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-lists' );
	}

	public static function handle_save() {
		if ( ! isset( $_POST['cp_list_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_list_nonce'] ) ), 'cp_list_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'hypeit' ) );
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}

		$term_id = isset( $_POST['cp_term_id'] ) ? absint( $_POST['cp_term_id'] ) : 0;
		$name    = isset( $_POST['cp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_name'] ) ) : '';

		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( 'cp_msg', 'noname', self::page_url() ) );
			exit;
		}

		if ( $term_id ) {
			wp_update_term( $term_id, CP_Library::TAX_LIST, array( 'name' => $name ) );
		} else {
			$existing = get_term_by( 'name', $name, CP_Library::TAX_LIST );
			if ( $existing ) {
				$term_id = (int) $existing->term_id;
			} else {
				$new = wp_insert_term( $name, CP_Library::TAX_LIST );
				if ( is_wp_error( $new ) ) {
					wp_safe_redirect( add_query_arg( 'cp_msg', 'error', self::page_url() ) );
					exit;
				}
				$term_id = (int) $new['term_id'];
			}
		}

		$enabled   = ! empty( $_POST['cp_rule_enabled'] );
		$all       = ! empty( $_POST['cp_rule_all'] );
		$gender    = isset( $_POST['cp_rule_gender'] ) ? sanitize_key( wp_unslash( $_POST['cp_rule_gender'] ) ) : '';
		$city      = isset( $_POST['cp_rule_city'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_rule_city'] ) ) : '';
		$tag_ids   = isset( $_POST['cp_rule_tags'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['cp_rule_tags'] ) ) : array();
		$tag_match = ( isset( $_POST['cp_rule_tag_match'] ) && 'all' === $_POST['cp_rule_tag_match'] ) ? 'all' : 'any';
		$fmin      = isset( $_POST['cp_rule_fmin'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_rule_fmin'] ) ) : '';
		$fmax      = isset( $_POST['cp_rule_fmax'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_rule_fmax'] ) ) : '';
		$verified  = ! empty( $_POST['cp_rule_verified'] );
		$collab    = isset( $_POST['cp_rule_collab'] ) ? sanitize_key( wp_unslash( $_POST['cp_rule_collab'] ) ) : '';

		update_term_meta( $term_id, 'cp_rule_enabled', $enabled ? '1' : '0' );
		update_term_meta( $term_id, 'cp_rule_all', $all ? '1' : '0' );
		update_term_meta( $term_id, 'cp_rule_gender', $gender );
		update_term_meta( $term_id, 'cp_rule_city', $city );
		update_term_meta( $term_id, 'cp_rule_tags', $tag_ids );
		update_term_meta( $term_id, 'cp_rule_tag_match', $tag_match );
		update_term_meta( $term_id, 'cp_rule_fmin', $fmin );
		update_term_meta( $term_id, 'cp_rule_fmax', $fmax );
		update_term_meta( $term_id, 'cp_rule_verified', $verified ? '1' : '0' );
		update_term_meta( $term_id, 'cp_rule_collab', $collab );

		if ( $enabled ) {
			self::rebuild( $term_id );
		}

		wp_safe_redirect( add_query_arg( 'cp_msg', 'saved', self::page_url() ) );
		exit;
	}

	public static function handle_delete() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		check_admin_referer( 'cp_list_delete_' . $term_id );
		if ( $term_id && current_user_can( 'manage_categories' ) ) {
			wp_delete_term( $term_id, CP_Library::TAX_LIST );
		}
		wp_safe_redirect( add_query_arg( 'cp_msg', 'deleted', self::page_url() ) );
		exit;
	}

	public static function handle_rebuild() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		check_admin_referer( 'cp_list_rebuild_' . $term_id );
		if ( $term_id && current_user_can( 'manage_categories' ) ) {
			self::rebuild( $term_id );
		}
		wp_safe_redirect( add_query_arg( 'cp_msg', 'rebuilt', self::page_url() ) );
		exit;
	}

	/**
	 * Live preview of matches for the rules form.
	 */
	public static function ajax_preview() {
		check_ajax_referer( 'cp_list_preview', 'nonce' );
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'hypeit' ) ), 403 );
		}
		$opts = array(
			'all'           => ! empty( $_POST['cp_rule_all'] ),
			'gender'        => isset( $_POST['cp_rule_gender'] ) ? sanitize_key( wp_unslash( $_POST['cp_rule_gender'] ) ) : '',
			'city'          => isset( $_POST['cp_rule_city'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_rule_city'] ) ) : '',
			'tag_ids'       => isset( $_POST['cp_rule_tags'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['cp_rule_tags'] ) ) : array(),
			'tag_match'     => ( isset( $_POST['cp_rule_tag_match'] ) && 'all' === $_POST['cp_rule_tag_match'] ) ? 'all' : 'any',
			'followers_min' => isset( $_POST['cp_rule_fmin'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_rule_fmin'] ) ) : '',
			'followers_max' => isset( $_POST['cp_rule_fmax'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_rule_fmax'] ) ) : '',
			'verified'      => ! empty( $_POST['cp_rule_verified'] ),
			'collab'        => isset( $_POST['cp_rule_collab'] ) ? sanitize_key( wp_unslash( $_POST['cp_rule_collab'] ) ) : '',
		);
		$ids   = CP_Library::filter_ids( $opts );
		$names = array();
		foreach ( array_slice( $ids, 0, 25 ) as $id ) {
			$first = get_post_meta( $id, '_cp_first', true );
			$last  = get_post_meta( $id, '_cp_last', true );
			$name  = trim( $first . ' ' . $last );
			$names[] = '' !== $name ? $name . ' (@' . CP_Library::handle( $id ) . ')' : '@' . CP_Library::handle( $id );
		}
		wp_send_json_success( array( 'count' => count( $ids ), 'names' => $names ) );
	}

	/**
	 * Export a list's bloggers as a CSV download.
	 */
	public static function handle_export() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		check_admin_referer( 'cp_list_export_' . $term_id );
		if ( ! $term_id || ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}

		$term    = get_term( $term_id, CP_Library::TAX_LIST );
		$members = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array( 'taxonomy' => CP_Library::TAX_LIST, 'field' => 'term_id', 'terms' => $term_id ),
				),
			)
		);

		$slug = $term && ! is_wp_error( $term ) ? sanitize_title( $term->name ) : 'list';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $slug . '-' . gmdate( 'Ymd' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv(
			$out,
			array( 'First name', 'Last name', 'Instagram', 'Instagram URL', 'Gender', 'Followers', 'Reach', 'Verified', 'Open for', 'City', 'Categories', 'Email', 'Phone', 'WhatsApp' )
		);
		$genders = CP_Library::genders();
		$collabs = CP_Library::collab_types();
		foreach ( $members as $m ) {
			$h    = CP_Library::handle( $m->ID );
			$tags = wp_get_post_terms( $m->ID, CP_Library::TAX_TAG, array( 'fields' => 'names' ) );
			$g    = get_post_meta( $m->ID, '_cp_gender', true );
			$craw = trim( (string) get_post_meta( $m->ID, '_cp_collab', true ), ',' );
			$cout = array();
			foreach ( array_filter( explode( ',', $craw ) ) as $ck ) {
				$cout[] = isset( $collabs[ $ck ] ) ? $collabs[ $ck ] : $ck;
			}
			fputcsv(
				$out,
				array(
					get_post_meta( $m->ID, '_cp_first', true ),
					get_post_meta( $m->ID, '_cp_last', true ),
					$h ? '@' . $h : '',
					get_post_meta( $m->ID, '_cp_ig_url', true ),
					isset( $genders[ $g ] ) ? $genders[ $g ] : '',
					(int) get_post_meta( $m->ID, '_cp_followers', true ),
					(int) get_post_meta( $m->ID, '_cp_reach', true ),
					'1' === (string) get_post_meta( $m->ID, '_cp_verified', true ) ? 'Yes' : 'No',
					implode( ', ', $cout ),
					get_post_meta( $m->ID, '_cp_city', true ),
					is_wp_error( $tags ) ? '' : implode( ', ', $tags ),
					get_post_meta( $m->ID, '_cp_email', true ),
					get_post_meta( $m->ID, '_cp_phone', true ),
					get_post_meta( $m->ID, '_cp_whatsapp', true ),
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private static function action_url( $action, $term_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&term=' . $term_id ), $action . '_' . $term_id );
	}

	/**
	 * Render the Manage Lists page (cards + detail + add/edit).
	 */
	public static function render() {
		$genders   = CP_Library::genders();
		$tag_terms = get_terms( array( 'taxonomy' => CP_Library::TAX_TAG, 'hide_empty' => false ) );
		$tag_terms = is_wp_error( $tag_terms ) ? array() : $tag_terms;

		$view_id = isset( $_GET['cp_view'] ) ? absint( $_GET['cp_view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['cp_edit'] ) ? absint( $_GET['cp_edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new  = isset( $_GET['cp_new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$msg = isset( $_GET['cp_msg'] ) ? sanitize_key( wp_unslash( $_GET['cp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap cp-admin">';

		if ( $msg ) {
			$text = array(
				'saved'   => __( 'List saved.', 'hypeit' ),
				'deleted' => __( 'List deleted.', 'hypeit' ),
				'rebuilt' => __( 'Smart list rebuilt.', 'hypeit' ),
				'noname'  => __( 'Please enter a list name.', 'hypeit' ),
				'error'   => __( 'Could not save the list.', 'hypeit' ),
			);
			$cls = in_array( $msg, array( 'noname', 'error' ), true ) ? 'notice-error' : 'notice-success';
			if ( isset( $text[ $msg ] ) ) {
				echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $text[ $msg ] ) . '</p></div>';
			}
		}

		if ( $view_id ) {
			self::render_detail( $view_id );
		} elseif ( $edit_id || $is_new ) {
			self::render_form( $edit_id, $genders, $tag_terms );
		} else {
			self::render_grid();
		}

		echo '</div>';
	}

	/**
	 * Card grid of all lists.
	 */
	private static function render_grid() {
		$lists = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false ) );
		$lists = is_wp_error( $lists ) ? array() : $lists;
		?>
		<div class="cp-toolbar">
			<h1><?php esc_html_e( 'Lists', 'hypeit' ); ?></h1>
			<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'cp_new', '1', self::page_url() ) ); ?>"><?php esc_html_e( '+ Create list', 'hypeit' ); ?></a>
		</div>
		<p class="cp-sub"><?php esc_html_e( 'Click a list to open it. Smart lists fill themselves from rules; new matching bloggers join automatically.', 'hypeit' ); ?></p>

		<div class="cp-cards">
			<?php foreach ( $lists as $l ) : ?>
				<?php $lr = self::get_rules( $l->term_id ); $smart = $lr['enabled'] && self::has_rules( $lr ); ?>
				<a class="cp-tile" href="<?php echo esc_url( add_query_arg( 'cp_view', $l->term_id, self::page_url() ) ); ?>">
					<h3><?php echo esc_html( $l->name ); ?></h3>
					<div class="cp-tile-meta">
						<span class="cp-tile-count"><?php echo (int) $l->count; ?> <?php esc_html_e( 'bloggers', 'hypeit' ); ?></span>
						<?php if ( $smart ) : ?><span class="cp-badge cp-badge-confirmed"><?php esc_html_e( 'Smart', 'hypeit' ); ?></span><?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
			<a class="cp-tile-new" href="<?php echo esc_url( add_query_arg( 'cp_new', '1', self::page_url() ) ); ?>">＋ <?php esc_html_e( 'New list', 'hypeit' ); ?></a>
		</div>
		<?php
	}

	/**
	 * A single list's detail page (its bloggers) with back + actions.
	 *
	 * @param int $term_id Term ID.
	 */
	private static function render_detail( $term_id ) {
		$term = get_term( $term_id, CP_Library::TAX_LIST );
		if ( ! $term || is_wp_error( $term ) ) {
			echo '<p>' . esc_html__( 'List not found.', 'hypeit' ) . '</p>';
			return;
		}
		$lr    = self::get_rules( $term_id );
		$smart = $lr['enabled'] && self::has_rules( $lr );
		$genders = CP_Library::genders();

		$members = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array( 'taxonomy' => CP_Library::TAX_LIST, 'field' => 'term_id', 'terms' => $term_id ),
				),
			)
		);
		?>
		<a class="cp-back" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( '← Back to Lists', 'hypeit' ); ?></a>
		<div class="cp-toolbar">
			<h1><?php echo esc_html( $term->name ); ?> <?php if ( $smart ) : ?><span class="cp-badge cp-badge-confirmed" style="vertical-align:middle;"><?php esc_html_e( 'Smart', 'hypeit' ); ?></span><?php endif; ?></h1>
			<span>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'cp_edit', $term_id, self::page_url() ) ); ?>"><?php esc_html_e( 'Edit', 'hypeit' ); ?></a>
				<?php if ( $smart ) : ?>
					<a class="button" href="<?php echo esc_url( self::action_url( 'cp_list_rebuild', $term_id ) ); ?>"><?php esc_html_e( 'Rebuild', 'hypeit' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( self::action_url( 'cp_list_export', $term_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'hypeit' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::action_url( 'cp_list_delete', $term_id ) ); ?>" style="color:#b3261e;" onclick="return confirm('<?php echo esc_js( __( 'Delete this list? Bloggers are not deleted.', 'hypeit' ) ); ?>');"><?php esc_html_e( 'Delete', 'hypeit' ); ?></a>
			</span>
		</div>

		<div class="cp-card">
			<?php if ( empty( $members ) ) : ?>
				<p class="description"><?php esc_html_e( 'No bloggers in this list yet.', 'hypeit' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Name', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'Instagram', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'Gender', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'Followers', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'City', 'hypeit' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $members as $m ) : ?>
						<?php
						$h  = CP_Library::handle( $m->ID );
						$url = get_post_meta( $m->ID, '_cp_ig_url', true );
						if ( ! $url ) { $url = CP_Library::profile_url( $h ); }
						$g  = get_post_meta( $m->ID, '_cp_gender', true );
						$f  = (int) get_post_meta( $m->ID, '_cp_followers', true );
						?>
						<tr>
							<td><span style="display:inline-flex;align-items:center;gap:10px;"><?php echo CP_Photo::avatar_html( $m->ID, 34, get_the_title( $m->ID ), $h ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( get_edit_post_link( $m->ID ) ); ?>"><?php echo esc_html( get_the_title( $m->ID ) ); ?></a></span></td>
							<td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $h ); ?></a></td>
							<td><?php echo isset( $genders[ $g ] ) ? esc_html( $genders[ $g ] ) : '&mdash;'; ?></td>
							<td><?php echo $f ? esc_html( number_format_i18n( $f ) ) : '&mdash;'; ?></td>
							<td><?php echo esc_html( get_post_meta( $m->ID, '_cp_city', true ) ? get_post_meta( $m->ID, '_cp_city', true ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add / edit list form.
	 *
	 * @param int   $edit_id   Term ID (0 = new).
	 * @param array $genders   Genders.
	 * @param array $tag_terms Tag terms.
	 */
	private static function render_form( $edit_id, $genders, $tag_terms ) {
		$edit  = $edit_id ? get_term( $edit_id, CP_Library::TAX_LIST ) : null;
		$rules = self::get_rules( $edit_id );
		?>
		<a class="cp-back" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( '← Back to Lists', 'hypeit' ); ?></a>
		<h1><?php echo $edit ? esc_html__( 'Edit list', 'hypeit' ) : esc_html__( 'Create list', 'hypeit' ); ?></h1>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cp-listform" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_list_preview' ) ); ?>">
			<input type="hidden" name="action" value="cp_list_save" />
			<input type="hidden" name="cp_term_id" value="<?php echo (int) $edit_id; ?>" />
			<?php wp_nonce_field( 'cp_list_save', 'cp_list_nonce' ); ?>

			<div class="cp-card">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cp_name"><?php esc_html_e( 'List name', 'hypeit' ); ?></label></th>
						<td><input type="text" id="cp_name" name="cp_name" class="regular-text" required value="<?php echo esc_attr( $edit ? $edit->name : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Smart list', 'hypeit' ); ?></th>
						<td><label><input type="checkbox" name="cp_rule_enabled" value="1" <?php checked( $rules['enabled'] ); ?> /> <?php esc_html_e( 'Automatically add bloggers who match the rules below (including new ones as they apply)', 'hypeit' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Everyone', 'hypeit' ); ?></th>
						<td><label><input type="checkbox" name="cp_rule_all" value="1" class="cp-f" <?php checked( ! empty( $rules['all'] ) ); ?> /> <?php esc_html_e( 'Include ALL bloggers (every current and future blogger joins automatically)', 'hypeit' ); ?></label>
						<p class="description"><?php esc_html_e( 'When ticked, the rules below are ignored. Requires “Smart list” on.', 'hypeit' ); ?></p></td>
					</tr>
				</table>
			</div>

			<div class="cp-card">
				<h2 class="title"><?php esc_html_e( 'Rules', 'hypeit' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Gender', 'hypeit' ); ?></th>
						<td>
							<select name="cp_rule_gender" class="cp-f">
								<option value=""><?php esc_html_e( 'Any', 'hypeit' ); ?></option>
								<?php foreach ( $genders as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $rules['gender'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Location (city)', 'hypeit' ); ?></th>
						<td>
							<select name="cp_rule_city" class="cp-f">
								<option value=""><?php esc_html_e( 'Any', 'hypeit' ); ?></option>
								<?php foreach ( CP_Location::cities() as $city ) : ?>
									<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $rules['city'], $city ); ?>><?php echo esc_html( $city ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categories', 'hypeit' ); ?></th>
						<td>
							<?php if ( empty( $tag_terms ) ) : ?>
								<span class="description"><?php esc_html_e( 'No categories yet.', 'hypeit' ); ?></span>
							<?php else : ?>
								<div class="cp-chk-group">
									<?php foreach ( $tag_terms as $t ) : ?>
										<label><input type="checkbox" name="cp_rule_tags[]" value="<?php echo (int) $t->term_id; ?>" class="cp-f" <?php echo in_array( (int) $t->term_id, $rules['tag_ids'], true ) ? 'checked' : ''; ?> /> <?php echo esc_html( $t->name ); ?></label>
									<?php endforeach; ?>
								</div>
								<p>
									<label style="margin-right:14px;"><input type="radio" name="cp_rule_tag_match" value="any" class="cp-f" <?php checked( $rules['tag_match'], 'any' ); ?> /> <?php esc_html_e( 'Match ANY', 'hypeit' ); ?></label>
									<label><input type="radio" name="cp_rule_tag_match" value="all" class="cp-f" <?php checked( $rules['tag_match'], 'all' ); ?> /> <?php esc_html_e( 'Match ALL', 'hypeit' ); ?></label>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Followers', 'hypeit' ); ?></th>
						<td>
							<input type="number" name="cp_rule_fmin" min="0" step="1" class="small-text cp-f" placeholder="min" value="<?php echo esc_attr( $rules['followers_min'] ); ?>" />
							&ndash;
							<input type="number" name="cp_rule_fmax" min="0" step="1" class="small-text cp-f" placeholder="max" value="<?php echo esc_attr( $rules['followers_max'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verified', 'hypeit' ); ?></th>
						<td><label><input type="checkbox" name="cp_rule_verified" value="1" class="cp-f" <?php checked( $rules['verified'] ); ?> /> <?php esc_html_e( 'Verified accounts only', 'hypeit' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Open for', 'hypeit' ); ?></th>
						<td>
							<select name="cp_rule_collab" class="cp-f">
								<option value=""><?php esc_html_e( 'Any', 'hypeit' ); ?></option>
								<?php foreach ( CP_Library::collab_types() as $ckey => $clabel ) : ?>
									<option value="<?php echo esc_attr( $ckey ); ?>" <?php selected( $rules['collab'], $ckey ); ?>><?php echo esc_html( $clabel ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Bloggers open to this campaign type.', 'hypeit' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Matches', 'hypeit' ); ?></th>
						<td><strong id="cp-lp-count">—</strong> <?php esc_html_e( 'blogger(s)', 'hypeit' ); ?>
						<div id="cp-lp-names" class="description" style="margin-top:6px;max-height:120px;overflow:auto;"></div></td>
					</tr>
				</table>
			</div>

			<?php submit_button( $edit ? __( 'Save list', 'hypeit' ) : __( 'Create list', 'hypeit' ) ); ?>
			<a href="<?php echo esc_url( self::page_url() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'hypeit' ); ?></a>
		</form>

		<script>
		( function () {
			var form = document.getElementById( 'cp-listform' );
			if ( ! form ) { return; }
			var nonce = form.getAttribute( 'data-nonce' );
			var countEl = document.getElementById( 'cp-lp-count' );
			var namesEl = document.getElementById( 'cp-lp-names' );
			var t;
			function preview() {
				var body = new FormData();
				body.append( 'action', 'cp_list_preview' );
				body.append( 'nonce', nonce );
				[ 'cp_rule_gender', 'cp_rule_city', 'cp_rule_fmin', 'cp_rule_fmax', 'cp_rule_collab' ].forEach( function ( n ) {
					var el = form.querySelector( '[name="' + n + '"]' ); if ( el ) { body.append( n, el.value ); }
				} );
				var m = form.querySelector( '[name="cp_rule_tag_match"]:checked' ); body.append( 'cp_rule_tag_match', m ? m.value : 'any' );
				var v = form.querySelector( '[name="cp_rule_verified"]' ); if ( v && v.checked ) { body.append( 'cp_rule_verified', '1' ); }
				var a = form.querySelector( '[name="cp_rule_all"]' ); if ( a && a.checked ) { body.append( 'cp_rule_all', '1' ); }
				var tg = form.querySelectorAll( '[name="cp_rule_tags[]"]:checked' );
				Array.prototype.forEach.call( tg, function ( cb ) { body.append( 'cp_rule_tags[]', cb.value ); } );
				countEl.textContent = '…';
				fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) {
						if ( j && j.success ) {
							countEl.textContent = j.data.count;
							namesEl.innerHTML = ( j.data.names || [] ).map( function ( n ) { return n.replace( /[&<>]/g, '' ); } ).join( '<br>' ) + ( j.data.count > 25 ? '<br>…' : '' );
						} else { countEl.textContent = '0'; namesEl.innerHTML = ''; }
					} )
					.catch( function () { countEl.textContent = '?'; } );
			}
			form.addEventListener( 'change', function () { clearTimeout( t ); t = setTimeout( preview, 250 ); } );
			form.addEventListener( 'input', function ( e ) { if ( e.target.type === 'number' ) { clearTimeout( t ); t = setTimeout( preview, 400 ); } } );
			preview();
		} )();
		</script>
		<?php
	}
}
