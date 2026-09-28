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
		add_action( 'admin_post_cp_list_add', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_cp_list_remove', array( __CLASS__, 'handle_remove' ) );
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
			__( 'Lists', 'hypeit' ),
			__( 'Lists', 'hypeit' ),
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
		$ids    = CP_Library::filter_ids( $opts );
		$names  = array();
		$people = array();
		foreach ( array_slice( $ids, 0, 25 ) as $id ) {
			$name     = trim( get_post_meta( $id, '_cp_first', true ) . ' ' . get_post_meta( $id, '_cp_last', true ) );
			$h        = CP_Library::handle( $id );
			$names[]  = '' !== $name ? $name . ' (@' . $h . ')' : '@' . $h;
			$f        = (int) get_post_meta( $id, '_cp_followers', true );
			$people[] = array( 'name' => '' !== $name ? $name : '@' . $h, 'handle' => $h, 'f' => $f ? self::k( $f ) : '' );
		}
		wp_send_json_success( array( 'count' => count( $ids ), 'names' => $names, 'people' => $people ) );
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
	 * Active members of a list (published, not deactivated), by name.
	 *
	 * @param int $term_id Term ID.
	 * @return array Post IDs.
	 */
	public static function members( $term_id ) {
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array( 'taxonomy' => CP_Library::TAX_LIST, 'field' => 'term_id', 'terms' => (int) $term_id ),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array( 'key' => CP_Library::META_INACTIVE, 'compare' => 'NOT EXISTS' ),
					array( 'key' => CP_Library::META_INACTIVE, 'value' => '1', 'compare' => '!=' ),
				),
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Plain-language summary of a smart list's rules.
	 *
	 * @param array $r Rules.
	 * @return array Labels.
	 */
	public static function rule_chips( $r ) {
		if ( ! empty( $r['all'] ) ) {
			return array( __( 'Every blogger', 'hypeit' ) );
		}
		$out     = array();
		$genders = CP_Library::genders();
		if ( $r['gender'] && isset( $genders[ $r['gender'] ] ) ) {
			$out[] = $genders[ $r['gender'] ];
		}
		if ( $r['city'] ) {
			$out[] = $r['city'];
		}
		if ( $r['tag_ids'] ) {
			$names = array();
			foreach ( $r['tag_ids'] as $tid ) {
				$t = get_term( $tid, CP_Library::TAX_TAG );
				if ( $t && ! is_wp_error( $t ) ) {
					$names[] = $t->name;
				}
			}
			if ( $names ) {
				$out[] = implode( 'all' === $r['tag_match'] ? ' + ' : ' / ', $names );
			}
		}
		$fmin = '' !== $r['followers_min'] ? (int) $r['followers_min'] : null;
		$fmax = '' !== $r['followers_max'] ? (int) $r['followers_max'] : null;
		if ( null !== $fmin && null !== $fmax ) {
			$out[] = self::k( $fmin ) . '–' . self::k( $fmax ) . ' ' . __( 'followers', 'hypeit' );
		} elseif ( null !== $fmin ) {
			$out[] = self::k( $fmin ) . '+ ' . __( 'followers', 'hypeit' );
		} elseif ( null !== $fmax ) {
			/* translators: %s: follower count. */
			$out[] = sprintf( __( 'up to %s followers', 'hypeit' ), self::k( $fmax ) );
		}
		if ( $r['verified'] ) {
			$out[] = __( 'Verified', 'hypeit' );
		}
		if ( ! empty( $r['collab'] ) ) {
			$c = CP_Library::collab_types();
			/* translators: %s: collaboration type. */
			$out[] = sprintf( __( 'Open for %s', 'hypeit' ), isset( $c[ $r['collab'] ] ) ? $c[ $r['collab'] ] : $r['collab'] );
		}
		return $out;
	}

	/**
	 * 12400 → 12.4K.
	 *
	 * @param int $n Number.
	 * @return string
	 */
	private static function k( $n ) {
		$n = (int) $n;
		if ( $n >= 1000000 ) {
			return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M';
		}
		if ( $n >= 1000 ) {
			return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K';
		}
		return number_format_i18n( $n );
	}

	/**
	 * Add bloggers to a manual list.
	 */
	public static function handle_add() {
		$term_id = isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0;
		check_admin_referer( 'cp_list_add_' . $term_id );
		if ( ! $term_id || ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		$ids = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) : array();
		$n   = 0;
		if ( ! self::is_smart( $term_id ) ) {
			foreach ( $ids as $id ) {
				if ( CP_Library::CPT === get_post_type( $id ) && ! is_wp_error( wp_set_post_terms( $id, array( $term_id ), CP_Library::TAX_LIST, true ) ) ) {
					$n++;
				}
			}
		}
		wp_safe_redirect( add_query_arg( array( 'cp_view' => $term_id, 'cp_msg' => 'added', 'n' => $n ), self::page_url() ) );
		exit;
	}

	/**
	 * Remove one blogger from a manual list.
	 */
	public static function handle_remove() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_list_remove_' . $term_id . '_' . $post_id );
		if ( $term_id && $post_id && current_user_can( 'manage_categories' ) && ! self::is_smart( $term_id ) ) {
			wp_remove_object_terms( $post_id, $term_id, CP_Library::TAX_LIST );
		}
		wp_safe_redirect( add_query_arg( array( 'cp_view' => $term_id, 'cp_msg' => 'removed' ), self::page_url() ) );
		exit;
	}

	/**
	 * Render the Lists page (overview, one list, or the create/edit form).
	 */
	public static function render() {
		$genders   = CP_Library::genders();
		$tag_terms = get_terms( array( 'taxonomy' => CP_Library::TAX_TAG, 'hide_empty' => false ) );
		$tag_terms = is_wp_error( $tag_terms ) ? array() : $tag_terms;

		$view_id = isset( $_GET['cp_view'] ) ? absint( $_GET['cp_view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['cp_edit'] ) ? absint( $_GET['cp_edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new  = isset( $_GET['cp_new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$msg = isset( $_GET['cp_msg'] ) ? sanitize_key( wp_unslash( $_GET['cp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap cp-admin cp-wide cpls">';

		if ( $msg ) {
			$n    = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$text = array(
				'saved'   => __( 'List saved.', 'hypeit' ),
				'deleted' => __( 'List deleted.', 'hypeit' ),
				'rebuilt' => __( 'Smart list rebuilt.', 'hypeit' ),
				/* translators: %d: count. */
				'added'   => sprintf( _n( '%d blogger added to the list.', '%d bloggers added to the list.', $n, 'hypeit' ), $n ),
				'removed' => __( 'Blogger removed from the list.', 'hypeit' ),
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
	 * Overview: summary, search + filter, list cards.
	 */
	private static function render_grid() {
		$lists = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false, 'orderby' => 'name' ) );
		$lists = is_wp_error( $lists ) ? array() : $lists;
		$data  = array();
		$uniq  = array();
		$smart = 0;
		foreach ( $lists as $l ) {
			$r       = self::get_rules( $l->term_id );
			$is      = $r['enabled'] && self::has_rules( $r );
			$members = self::members( $l->term_id );
			$smart  += $is ? 1 : 0;
			foreach ( $members as $m ) {
				$uniq[ $m ] = true;
			}
			$data[] = array( $l, $r, $is, $members );
		}
		$new_url = add_query_arg( 'cp_new', '1', self::page_url() );
		?>
		<div class="cp-pagehead">
			<div>
				<h1><?php esc_html_e( 'Lists', 'hypeit' ); ?></h1>
				<p class="cp-sub"><?php esc_html_e( 'Group bloggers for quick picking. Manual lists hold who you add by hand; smart lists fill themselves from rules and keep up as bloggers join or change.', 'hypeit' ); ?></p>
			</div>
			<a class="button button-primary cpls-new" href="<?php echo esc_url( $new_url ); ?>">＋ <?php esc_html_e( 'New list', 'hypeit' ); ?></a>
		</div>

		<div class="cpl-stats cpls-stats">
			<div class="cpl-stat"><b><?php echo (int) count( $lists ); ?></b><span><?php esc_html_e( 'Lists', 'hypeit' ); ?></span></div>
			<div class="cpl-stat is-ok"><b><?php echo (int) $smart; ?></b><span><?php esc_html_e( 'Smart lists', 'hypeit' ); ?></span></div>
			<div class="cpl-stat"><b><?php echo (int) ( count( $lists ) - $smart ); ?></b><span><?php esc_html_e( 'Manual lists', 'hypeit' ); ?></span></div>
			<div class="cpl-stat is-dark"><b><?php echo (int) count( $uniq ); ?></b><span><?php esc_html_e( 'Bloggers in lists', 'hypeit' ); ?></span></div>
		</div>

		<?php if ( ! $lists ) : ?>
			<div class="cpw-card cpls-empty">
				<span class="cpls-empty-ico" aria-hidden="true">☰</span>
				<h3><?php esc_html_e( 'No lists yet', 'hypeit' ); ?></h3>
				<p class="cpw-muted"><?php esc_html_e( 'Try a smart list like “Cairo foodies over 10K” — it fills itself and stays up to date.', 'hypeit' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Create your first list', 'hypeit' ); ?></a>
			</div>
			<?php
			return;
		endif;
		?>

		<div class="cpls-bar">
			<input type="search" id="cpls-q" placeholder="<?php esc_attr_e( 'Search lists…', 'hypeit' ); ?>" aria-label="<?php esc_attr_e( 'Search lists', 'hypeit' ); ?>" />
			<div class="cpw-seg" id="cpls-seg" role="group">
				<button type="button" class="is-active" data-f="all"><?php esc_html_e( 'All', 'hypeit' ); ?></button>
				<button type="button" data-f="smart"><?php esc_html_e( 'Smart', 'hypeit' ); ?></button>
				<button type="button" data-f="manual"><?php esc_html_e( 'Manual', 'hypeit' ); ?></button>
			</div>
		</div>

		<div class="cpls-grid" id="cpls-grid">
			<?php foreach ( $data as $row ) : ?>
				<?php
				list( $l, $r, $is, $members ) = $row;
				$open  = add_query_arg( 'cp_view', $l->term_id, self::page_url() );
				$chips = $is ? self::rule_chips( $r ) : array();
				?>
				<article class="cpls-card" data-name="<?php echo esc_attr( strtolower( $l->name ) ); ?>" data-kind="<?php echo $is ? 'smart' : 'manual'; ?>">
					<a class="cpls-card-main" href="<?php echo esc_url( $open ); ?>">
						<div class="cpls-card-top">
							<h3><?php echo esc_html( $l->name ); ?></h3>
							<span class="cpls-kind <?php echo $is ? 'is-smart' : ''; ?>"><?php echo $is ? '<svg class="cpls-bolt" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>' . esc_html__( 'Smart', 'hypeit' ) : esc_html__( 'Manual', 'hypeit' ); ?></span>
						</div>
						<div class="cpls-count"><b><?php echo (int) count( $members ); ?></b> <?php echo esc_html( _n( 'blogger', 'bloggers', count( $members ), 'hypeit' ) ); ?></div>
						<div class="cpls-faces">
							<?php foreach ( array_slice( $members, 0, 6 ) as $m ) : ?>
								<?php echo CP_Photo::avatar_html( $m, 30, trim( get_post_meta( $m, '_cp_first', true ) . ' ' . get_post_meta( $m, '_cp_last', true ) ), CP_Library::handle( $m ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
							<?php if ( count( $members ) > 6 ) : ?><span class="cpls-more">+<?php echo (int) ( count( $members ) - 6 ); ?></span><?php endif; ?>
							<?php if ( ! $members ) : ?><span class="cpw-muted"><?php esc_html_e( 'Empty so far', 'hypeit' ); ?></span><?php endif; ?>
						</div>
						<div class="cpls-rules">
							<?php if ( $is ) : ?>
								<?php foreach ( $chips as $c ) : ?><span><?php echo esc_html( $c ); ?></span><?php endforeach; ?>
							<?php else : ?>
								<span class="is-plain"><?php esc_html_e( 'Bloggers added by hand', 'hypeit' ); ?></span>
							<?php endif; ?>
						</div>
					</a>
					<div class="cpls-card-foot">
						<a href="<?php echo esc_url( add_query_arg( 'cp_edit', $l->term_id, self::page_url() ) ); ?>"><?php esc_html_e( 'Edit', 'hypeit' ); ?></a>
						<a href="<?php echo esc_url( self::action_url( 'cp_list_export', $l->term_id ) ); ?>"><?php esc_html_e( 'Export', 'hypeit' ); ?></a>
						<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => CP_Library::CPT, CP_Library::TAX_LIST => $l->slug ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'In Blogger Library', 'hypeit' ); ?> →</a>
					</div>
				</article>
			<?php endforeach; ?>
			<a class="cpls-card cpls-newcard" href="<?php echo esc_url( $new_url ); ?>"><span>＋</span><?php esc_html_e( 'New list', 'hypeit' ); ?></a>
		</div>
		<p class="cpw-empty" id="cpls-none" hidden><?php esc_html_e( 'No lists match.', 'hypeit' ); ?></p>
		<script>
		( function () {
			var q = document.getElementById( 'cpls-q' ), seg = document.getElementById( 'cpls-seg' ), f = 'all';
			function paint() {
				var s = ( q.value || '' ).toLowerCase().trim(), n = 0;
				document.querySelectorAll( '#cpls-grid .cpls-card[data-name]' ).forEach( function ( c ) {
					var ok = ( ! s || c.getAttribute( 'data-name' ).indexOf( s ) !== -1 ) && ( f === 'all' || c.getAttribute( 'data-kind' ) === f );
					c.hidden = ! ok; n += ok ? 1 : 0;
				} );
				document.getElementById( 'cpls-none' ).hidden = n > 0;
			}
			q.addEventListener( 'input', paint );
			seg.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( 'button' ); if ( ! b ) { return; }
				f = b.getAttribute( 'data-f' );
				seg.querySelectorAll( 'button' ).forEach( function ( x ) { x.classList.toggle( 'is-active', x === b ); } );
				paint();
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * One list: stats, members, add/remove (manual lists).
	 *
	 * @param int $term_id Term ID.
	 */
	private static function render_detail( $term_id ) {
		$term = get_term( $term_id, CP_Library::TAX_LIST );
		if ( ! $term || is_wp_error( $term ) ) {
			echo '<p>' . esc_html__( 'List not found.', 'hypeit' ) . '</p>';
			return;
		}
		$lr      = self::get_rules( $term_id );
		$smart   = $lr['enabled'] && self::has_rules( $lr );
		$members = self::members( $term_id );
		if ( $members ) {
			update_meta_cache( 'post', $members );
			update_object_term_cache( $members, CP_Library::CPT );
		}
		$genders = CP_Library::genders();

		$ver = 0;
		$fol = 0;
		$pct = 0;
		$gen = array();
		foreach ( $members as $m ) {
			$ver += CP_Verify::is_verified( $m ) ? 1 : 0;
			$fol += (int) get_post_meta( $m, '_cp_followers', true );
			$pct += (int) get_post_meta( $m, '_cp_complete', true );
			$g    = (string) get_post_meta( $m, '_cp_gender', true );
			if ( isset( $genders[ $g ] ) ) {
				$gen[ $genders[ $g ] ] = isset( $gen[ $genders[ $g ] ] ) ? $gen[ $genders[ $g ] ] + 1 : 1;
			}
		}
		$n = count( $members );
		arsort( $gen );
		?>
		<a class="cp-back" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( '← All lists', 'hypeit' ); ?></a>
		<div class="cp-pagehead">
			<div>
				<h1><?php echo esc_html( $term->name ); ?> <span class="cpls-kind <?php echo $smart ? 'is-smart' : ''; ?>"><?php echo $smart ? '<svg class="cpls-bolt" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>' . esc_html__( 'Smart', 'hypeit' ) : esc_html__( 'Manual', 'hypeit' ); ?></span></h1>
				<div class="cpls-rules">
					<?php if ( $smart ) : ?>
						<?php foreach ( self::rule_chips( $lr ) as $c ) : ?><span><?php echo esc_html( $c ); ?></span><?php endforeach; ?>
						<span class="is-plain"><?php esc_html_e( 'Matching bloggers join automatically', 'hypeit' ); ?></span>
					<?php else : ?>
						<span class="is-plain"><?php esc_html_e( 'Bloggers added by hand', 'hypeit' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="cpls-actions">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'cp_edit', $term_id, self::page_url() ) ); ?>"><?php esc_html_e( 'Edit', 'hypeit' ); ?></a>
				<?php if ( $smart ) : ?>
					<a class="button" href="<?php echo esc_url( self::action_url( 'cp_list_rebuild', $term_id ) ); ?>" title="<?php esc_attr_e( 'Re-check every blogger against the rules now', 'hypeit' ); ?>"><?php esc_html_e( 'Refresh', 'hypeit' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( self::action_url( 'cp_list_export', $term_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'hypeit' ); ?></a>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => CP_Library::CPT, CP_Library::TAX_LIST => $term->slug ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'Open in Blogger Library', 'hypeit' ); ?></a>
				<a class="button cpv-danger" href="<?php echo esc_url( self::action_url( 'cp_list_delete', $term_id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this list? Bloggers are not deleted.', 'hypeit' ) ); ?>');"><?php esc_html_e( 'Delete', 'hypeit' ); ?></a>
			</div>
		</div>

		<div class="cpl-stats cpls-stats">
			<div class="cpl-stat is-dark"><b><?php echo (int) $n; ?></b><span><?php echo esc_html( _n( 'Blogger', 'Bloggers', $n, 'hypeit' ) ); ?></span></div>
			<div class="cpl-stat is-ok"><b><?php echo $n ? (int) round( $ver / $n * 100 ) : 0; ?>%</b><span><?php esc_html_e( 'Verified', 'hypeit' ); ?></span></div>
			<div class="cpl-stat"><b><?php echo esc_html( self::k( $fol ) ); ?></b><span><?php esc_html_e( 'Total followers', 'hypeit' ); ?></span></div>
			<div class="cpl-stat"><b><?php echo $n ? (int) round( $pct / $n ) : 0; ?>%</b><span><?php esc_html_e( 'Average profile', 'hypeit' ); ?></span></div>
			<div class="cpl-stat"><b><?php echo $gen ? esc_html( implode( ' · ', array_map( static function ( $k, $v ) { return $v . ' ' . $k; }, array_keys( $gen ), $gen ) ) ) : '—'; ?></b><span><?php esc_html_e( 'Gender', 'hypeit' ); ?></span></div>
		</div>

		<?php
		if ( ! $smart ) :
			$others = get_posts(
				array(
					'post_type'      => CP_Library::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'post__not_in'   => $members ? $members : array( 0 ),
					'meta_query'     => CP_Library::active_clause(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			);
			?>
			<details class="cpw-card cpls-add"<?php echo $members ? '' : ' open'; ?>>
				<summary><?php esc_html_e( '＋ Add bloggers to this list', 'hypeit' ); ?></summary>
				<?php if ( ! $others ) : ?>
					<p class="cpw-muted"><?php esc_html_e( 'Every active blogger is already in this list.', 'hypeit' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cpls-addform">
						<input type="hidden" name="action" value="cp_list_add" />
						<input type="hidden" name="term" value="<?php echo (int) $term_id; ?>" />
						<?php wp_nonce_field( 'cp_list_add_' . $term_id ); ?>
						<input type="search" id="cpls-addq" placeholder="<?php esc_attr_e( 'Search name, @username or city…', 'hypeit' ); ?>" />
						<div class="cpls-pick" id="cpls-pick">
							<?php foreach ( $others as $o ) : ?>
								<?php
								$on   = trim( get_post_meta( $o, '_cp_first', true ) . ' ' . get_post_meta( $o, '_cp_last', true ) );
								$oh   = CP_Library::handle( $o );
								$ocit = (string) get_post_meta( $o, '_cp_city', true );
								$of   = (int) get_post_meta( $o, '_cp_followers', true );
								?>
								<label data-s="<?php echo esc_attr( strtolower( $on . ' ' . $oh . ' ' . $ocit ) ); ?>">
									<input type="checkbox" name="ids[]" value="<?php echo (int) $o; ?>" />
									<?php echo CP_Photo::avatar_html( $o, 30, $on, $oh ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<span><strong><?php echo esc_html( $on ? $on : '@' . $oh ); ?></strong><small>@<?php echo esc_html( $oh . ( $ocit ? ' · ' . $ocit : '' ) ); ?></small></span>
									<em><?php echo $of ? esc_html( self::k( $of ) ) : ''; ?></em>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="cpls-addbar">
							<span class="cpw-muted" id="cpls-addn"></span>
							<button type="submit" class="button button-primary" id="cpls-addbtn" disabled><?php esc_html_e( 'Add selected', 'hypeit' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</details>
		<?php endif; ?>

		<div class="cpw-card cpls-members">
			<div class="cpls-members-head">
				<h3><?php esc_html_e( 'Bloggers', 'hypeit' ); ?> <span class="cpw-pill"><?php echo (int) $n; ?></span></h3>
				<?php if ( $n ) : ?><input type="search" id="cpls-mq" placeholder="<?php esc_attr_e( 'Search this list…', 'hypeit' ); ?>" /><?php endif; ?>
			</div>
			<?php if ( ! $members ) : ?>
				<p class="cpw-empty"><?php echo esc_html( $smart ? __( 'Nobody matches the rules yet — new bloggers who match will join automatically.', 'hypeit' ) : __( 'No bloggers in this list yet. Add some above.', 'hypeit' ) ); ?></p>
			<?php else : ?>
				<table class="cpw-table" id="cpls-mtable">
					<thead><tr>
						<th><?php esc_html_e( 'Blogger', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'Followers', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'City', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'Categories', 'hypeit' ); ?></th>
						<th><?php esc_html_e( 'Profile', 'hypeit' ); ?></th>
						<?php if ( ! $smart ) : ?><th class="cpw-c-act"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'hypeit' ); ?></span></th><?php endif; ?>
					</tr></thead>
					<tbody>
					<?php foreach ( $members as $m ) : ?>
						<?php
						$h    = CP_Library::handle( $m );
						$name = trim( get_post_meta( $m, '_cp_first', true ) . ' ' . get_post_meta( $m, '_cp_last', true ) );
						$f    = (int) get_post_meta( $m, '_cp_followers', true );
						$city = (string) get_post_meta( $m, '_cp_city', true );
						$cats = wp_get_post_terms( $m, CP_Library::TAX_TAG, array( 'fields' => 'names' ) );
						$cats = is_wp_error( $cats ) ? array() : $cats;
						$cpct = (int) get_post_meta( $m, '_cp_complete', true );
						?>
						<tr data-s="<?php echo esc_attr( strtolower( $name . ' ' . $h . ' ' . $city . ' ' . implode( ' ', $cats ) ) ); ?>">
							<td data-label=""><div class="cpw-who"><?php echo CP_Photo::avatar_html( $m, 36, $name, $h ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div><a class="cpw-name" href="<?php echo esc_url( get_edit_post_link( $m ) ); ?>"><?php echo esc_html( $name ? $name : '@' . $h ); ?><?php echo CP_Verify::is_verified( $m ) ? ' <span class="cpw-ver">✓</span>' : ''; ?></a><a class="cpw-handle" href="<?php echo esc_url( CP_Library::profile_url( $h ) ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $h ); ?></a></div></div></td>
							<td data-label="<?php esc_attr_e( 'Followers', 'hypeit' ); ?>"><?php echo $f ? esc_html( number_format_i18n( $f ) ) . CP_Bloggers_UI::trend_html( $m ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td data-label="<?php esc_attr_e( 'City', 'hypeit' ); ?>"><?php echo esc_html( $city ? $city : '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Categories', 'hypeit' ); ?>"><?php foreach ( array_slice( $cats, 0, 3 ) as $c ) : ?><span class="cpl-tag"><?php echo esc_html( $c ); ?></span><?php endforeach; ?><?php echo $cats ? '' : '—'; ?></td>
							<td data-label="<?php esc_attr_e( 'Profile', 'hypeit' ); ?>"><div class="cpl-pct <?php echo esc_attr( $cpct >= 90 ? 'is-good' : ( $cpct >= 60 ? 'is-mid' : 'is-low' ) ); ?>"><span class="cpl-pct-bar"><i style="width:<?php echo (int) $cpct; ?>%"></i></span><span class="cpl-pct-n"><?php echo (int) $cpct; ?>%</span></div></td>
							<?php if ( ! $smart ) : ?>
								<td class="cpw-c-act"><a class="cpw-rm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cp_list_remove&term=' . $term_id . '&post=' . $m ), 'cp_list_remove_' . $term_id . '_' . $m ) ); ?>" title="<?php esc_attr_e( 'Remove from this list', 'hypeit' ); ?>" aria-label="<?php esc_attr_e( 'Remove from this list', 'hypeit' ); ?>">✕</a></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<script>
		( function () {
			function filter( input, rows ) {
				if ( ! input ) { return; }
				input.addEventListener( 'input', function () {
					var s = input.value.toLowerCase().trim();
					document.querySelectorAll( rows ).forEach( function ( r ) { r.hidden = !! s && r.getAttribute( 'data-s' ).indexOf( s ) === -1; } );
				} );
			}
			filter( document.getElementById( 'cpls-mq' ), '#cpls-mtable tbody tr' );
			filter( document.getElementById( 'cpls-addq' ), '#cpls-pick label' );
			var form = document.getElementById( 'cpls-addform' );
			if ( form ) {
				form.addEventListener( 'change', function () {
					var n = form.querySelectorAll( 'input[name="ids[]"]:checked' ).length;
					document.getElementById( 'cpls-addbtn' ).disabled = ! n;
					document.getElementById( 'cpls-addn' ).textContent = n ? n + ' <?php echo esc_js( __( 'selected', 'hypeit' ) ); ?>' : '';
				} );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Create / edit a list.
	 *
	 * @param int   $edit_id   Term ID (0 = new).
	 * @param array $genders   Genders.
	 * @param array $tag_terms Category terms.
	 */
	private static function render_form( $edit_id, $genders, $tag_terms ) {
		$edit  = $edit_id ? get_term( $edit_id, CP_Library::TAX_LIST ) : null;
		$rules = self::get_rules( $edit_id );
		$smart = $edit ? $rules['enabled'] : true; // New lists start as smart.
		$tiers = array(
			array( __( 'Nano', 'hypeit' ), '', '9999' ),
			array( __( 'Micro', 'hypeit' ), '10000', '49999' ),
			array( __( 'Mid', 'hypeit' ), '50000', '99999' ),
			array( __( 'Macro', 'hypeit' ), '100000', '499999' ),
			array( __( 'Mega', 'hypeit' ), '500000', '' ),
		);
		?>
		<a class="cp-back" href="<?php echo esc_url( $edit ? add_query_arg( 'cp_view', $edit_id, self::page_url() ) : self::page_url() ); ?>"><?php echo $edit ? esc_html__( '← Back to the list', 'hypeit' ) : esc_html__( '← All lists', 'hypeit' ); ?></a>
		<div class="cp-pagehead"><div><h1><?php echo $edit ? esc_html( sprintf( /* translators: %s: list name. */ __( 'Edit “%s”', 'hypeit' ), $edit->name ) ) : esc_html__( 'New list', 'hypeit' ); ?></h1></div></div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cp-listform" class="cps-layout cpls-form<?php echo $smart ? ' is-smart' : ''; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_list_preview' ) ); ?>">
			<input type="hidden" name="action" value="cp_list_save" />
			<input type="hidden" name="cp_term_id" value="<?php echo (int) $edit_id; ?>" />
			<?php wp_nonce_field( 'cp_list_save', 'cp_list_nonce' ); ?>

			<div class="cps-main">
				<section class="cpw-card">
					<div class="cps-field" style="margin-top:0">
						<label for="cp_name"><?php esc_html_e( 'List name', 'hypeit' ); ?></label>
						<input type="text" id="cp_name" name="cp_name" required value="<?php echo esc_attr( $edit ? $edit->name : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Cairo foodies', 'hypeit' ); ?>" />
					</div>
					<div class="cps-field">
						<span class="cps-label"><?php esc_html_e( 'How bloggers get in', 'hypeit' ); ?></span>
						<div class="cps-options">
							<label class="cps-option"><input type="radio" name="cp_rule_enabled" value="1" <?php checked( $smart ); ?> /><span><strong><svg class="cpls-bolt" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg> <?php esc_html_e( 'Smart', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Everyone matching your rules — updates itself', 'hypeit' ); ?></small></span></label>
							<label class="cps-option"><input type="radio" name="cp_rule_enabled" value="" <?php checked( ! $smart ); ?> /><span><strong><?php esc_html_e( 'Manual', 'hypeit' ); ?></strong><small><?php esc_html_e( 'You pick the bloggers yourself', 'hypeit' ); ?></small></span></label>
						</div>
					</div>
				</section>

				<section class="cpw-card cpls-rulecard">
					<h3><?php esc_html_e( 'Rules', 'hypeit' ); ?> <small class="cpw-muted"><?php esc_html_e( 'bloggers must match all of these', 'hypeit' ); ?></small></h3>
					<label class="cpw-switchrow">
						<span class="cpw-switch"><input type="checkbox" name="cp_rule_all" value="1" <?php checked( ! empty( $rules['all'] ) ); ?> /><span class="cpw-slider" aria-hidden="true"></span></span>
						<span class="cpw-switchtext"><strong><?php esc_html_e( 'Every blogger', 'hypeit' ); ?></strong><small><?php esc_html_e( 'Include all current and future bloggers (the rules below are ignored).', 'hypeit' ); ?></small></span>
					</label>
					<div class="cpls-rules-body">
						<div class="cps-grid2">
							<div class="cps-field">
								<span class="cps-label"><?php esc_html_e( 'Gender', 'hypeit' ); ?></span>
								<div class="cpw-chips">
									<label class="cpw-chip"><input type="radio" name="cp_rule_gender" value="" <?php checked( $rules['gender'], '' ); ?> /> <?php esc_html_e( 'Any', 'hypeit' ); ?></label>
									<?php foreach ( $genders as $key => $label ) : ?>
										<label class="cpw-chip"><input type="radio" name="cp_rule_gender" value="<?php echo esc_attr( $key ); ?>" <?php checked( $rules['gender'], $key ); ?> /> <?php echo esc_html( $label ); ?></label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="cps-field">
								<label for="cpls-city"><?php esc_html_e( 'City', 'hypeit' ); ?></label>
								<select name="cp_rule_city" id="cpls-city">
									<option value=""><?php esc_html_e( 'Any city', 'hypeit' ); ?></option>
									<?php foreach ( CP_Location::cities() as $city ) : ?>
										<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $rules['city'], $city ); ?>><?php echo esc_html( $city ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Categories', 'hypeit' ); ?>
								<span class="cpls-match">
									<label><input type="radio" name="cp_rule_tag_match" value="any" <?php checked( $rules['tag_match'], 'any' ); ?> /> <?php esc_html_e( 'any of', 'hypeit' ); ?></label>
									<label><input type="radio" name="cp_rule_tag_match" value="all" <?php checked( $rules['tag_match'], 'all' ); ?> /> <?php esc_html_e( 'all of', 'hypeit' ); ?></label>
								</span>
							</span>
							<?php if ( empty( $tag_terms ) ) : ?>
								<p class="cpw-muted"><?php esc_html_e( 'No categories yet — add them on the Onboarding page.', 'hypeit' ); ?></p>
							<?php else : ?>
								<div class="cpw-chips">
									<?php foreach ( $tag_terms as $t ) : ?>
										<label class="cpw-chip"><input type="checkbox" name="cp_rule_tags[]" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( (int) $t->term_id, $rules['tag_ids'], true ) ); ?> /> <?php echo esc_html( $t->name ); ?></label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<div class="cps-field">
							<span class="cps-label"><?php esc_html_e( 'Followers', 'hypeit' ); ?></span>
							<div class="cpls-tiers">
								<?php foreach ( $tiers as $tier ) : ?>
									<button type="button" class="button button-small" data-min="<?php echo esc_attr( $tier[1] ); ?>" data-max="<?php echo esc_attr( $tier[2] ); ?>"><?php echo esc_html( $tier[0] ); ?></button>
								<?php endforeach; ?>
								<button type="button" class="button-link" data-min="" data-max=""><?php esc_html_e( 'Any size', 'hypeit' ); ?></button>
							</div>
							<span class="cpw-range">
								<input type="number" name="cp_rule_fmin" min="0" step="1" placeholder="<?php esc_attr_e( 'min', 'hypeit' ); ?>" value="<?php echo esc_attr( $rules['followers_min'] ); ?>" />
								<span>–</span>
								<input type="number" name="cp_rule_fmax" min="0" step="1" placeholder="<?php esc_attr_e( 'max', 'hypeit' ); ?>" value="<?php echo esc_attr( $rules['followers_max'] ); ?>" />
							</span>
						</div>
						<div class="cps-grid2">
							<div class="cps-field">
								<label for="cpls-collab"><?php esc_html_e( 'Open for', 'hypeit' ); ?></label>
								<select name="cp_rule_collab" id="cpls-collab">
									<option value=""><?php esc_html_e( 'Any campaign type', 'hypeit' ); ?></option>
									<?php foreach ( CP_Library::collab_types() as $ckey => $clabel ) : ?>
										<option value="<?php echo esc_attr( $ckey ); ?>" <?php selected( $rules['collab'], $ckey ); ?>><?php echo esc_html( $clabel ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="cps-field">
								<span class="cps-label">&nbsp;</span>
								<label class="cpw-switchrow">
									<span class="cpw-switch"><input type="checkbox" name="cp_rule_verified" value="1" <?php checked( $rules['verified'] ); ?> /><span class="cpw-slider" aria-hidden="true"></span></span>
									<span class="cpw-switchtext"><strong><?php esc_html_e( 'Verified only', 'hypeit' ); ?></strong></span>
								</label>
							</div>
						</div>
					</div>
				</section>

				<section class="cpw-card cpls-manualnote">
					<p><?php esc_html_e( 'After saving, open the list to add bloggers — or tick lists on any blogger’s profile.', 'hypeit' ); ?></p>
				</section>

				<div class="cps-save">
					<?php submit_button( $edit ? __( 'Save list', 'hypeit' ) : __( 'Create list', 'hypeit' ), 'primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( $edit ? add_query_arg( 'cp_view', $edit_id, self::page_url() ) : self::page_url() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'hypeit' ); ?></a>
				</div>
			</div>

			<aside class="cps-side">
				<section class="cpw-card cpls-preview">
					<h3><?php esc_html_e( 'Who’s in', 'hypeit' ); ?></h3>
					<div class="cpls-previewbig"><b id="cp-lp-count">—</b> <span><?php esc_html_e( 'bloggers match', 'hypeit' ); ?></span></div>
					<ul id="cp-lp-names"></ul>
					<p class="cpw-muted cpls-manualhint"><?php esc_html_e( 'Manual list — you choose who’s in after saving.', 'hypeit' ); ?></p>
				</section>
			</aside>
		</form>

		<script>
		( function () {
			var form = document.getElementById( 'cp-listform' );
			if ( ! form ) { return; }
			var nonce = form.getAttribute( 'data-nonce' ), countEl = document.getElementById( 'cp-lp-count' ), namesEl = document.getElementById( 'cp-lp-names' ), t;
			function isSmart() { var r = form.querySelector( '[name="cp_rule_enabled"]:checked' ); return r && r.value === '1'; }
			function esc( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); }
			function preview() {
				form.classList.toggle( 'is-smart', isSmart() );
				form.classList.toggle( 'is-all', !! form.querySelector( '[name="cp_rule_all"]:checked' ) );
				if ( ! isSmart() ) { return; }
				var body = new FormData( form );
				body.set( 'action', 'cp_list_preview' ); body.set( 'nonce', nonce );
				countEl.textContent = '…';
				fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) {
						if ( ! j || ! j.success ) { countEl.textContent = '0'; namesEl.innerHTML = ''; return; }
						countEl.textContent = j.data.count;
						namesEl.innerHTML = ( j.data.people || [] ).map( function ( p ) {
							return '<li><span>' + esc( p.name ) + '</span><small>@' + esc( p.handle ) + '</small><em>' + esc( p.f ) + '</em></li>';
						} ).join( '' ) + ( j.data.count > 25 ? '<li class="more">+ ' + ( j.data.count - 25 ) + '</li>' : '' );
					} )
					.catch( function () { countEl.textContent = '?'; } );
			}
			form.addEventListener( 'change', function () { clearTimeout( t ); t = setTimeout( preview, 200 ); } );
			form.addEventListener( 'input', function ( e ) { if ( e.target.type === 'number' ) { clearTimeout( t ); t = setTimeout( preview, 400 ); } } );
			form.querySelectorAll( '.cpls-tiers [data-min]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					form.querySelector( '[name="cp_rule_fmin"]' ).value = b.getAttribute( 'data-min' );
					form.querySelector( '[name="cp_rule_fmax"]' ).value = b.getAttribute( 'data-max' );
					preview();
				} );
			} );
			preview();
		} )();
		</script>
		<?php
	}
}
