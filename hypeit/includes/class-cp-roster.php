<?php
/**
 * Keeps campaign participant rows in step with the blogger library.
 *
 * Campaign rows are matched to bloggers by Instagram username. Without this,
 * bloggers deleted or blocked in the library would stay in campaigns, and a
 * changed username would leave the old one behind while "Everyone" adds the
 * new one (the same person twice).
 *
 * Rules:
 * - Only rows still WAITING for the client are ever removed — a client's
 *   decision (selected / declined) is always kept as history.
 * - Closed campaigns are never touched.
 * - Deactivated bloggers are never removed: their rows stay, hidden from the
 *   campaign UI (see CP_DB::visible_bloggers), until they're reactivated.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Roster {

	/** @var array Blogger ID => old handle, captured before a username change. */
	private static $old = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_trash_post', array( __CLASS__, 'on_remove' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_remove' ) );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_blocked' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_blocked' ), 10, 4 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'capture_old_handle' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_handle_changed' ), 10, 4 );
	}

	/**
	 * Open (not closed) campaign IDs.
	 *
	 * @return array
	 */
	private static function open_ids() {
		$ids = get_posts(
			array(
				'post_type'      => CP_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		return array_values(
			array_filter(
				array_map( 'intval', (array) $ids ),
				static function ( $id ) { return ! CP_Close::is_closed( $id ); }
			)
		);
	}

	/**
	 * Remove a username's waiting rows from every open campaign.
	 *
	 * @param string $handle Username.
	 * @return int Rows removed.
	 */
	public static function drop_handle( $handle ) {
		$handle = strtolower( ltrim( (string) $handle, '@' ) );
		$open   = self::open_ids();
		if ( '' === $handle || ! $open ) {
			return 0;
		}
		global $wpdb;
		$t   = CP_DB::table();
		$in  = implode( ',', $open );
		$n   = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "DELETE FROM {$t} WHERE LOWER(ig_account) = %s AND status NOT IN ('confirmed','declined') AND campaign_id IN ({$in})", $handle ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $n && class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		return $n;
	}

	/**
	 * Blogger trashed or deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_remove( $post_id ) {
		if ( CP_Library::CPT === get_post_type( $post_id ) ) {
			self::drop_handle( CP_Library::handle( $post_id ) );
		}
	}

	/**
	 * Blogger blocked.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 */
	public static function on_blocked( $meta_id, $post_id, $key, $value ) {
		if ( '_cp_blocked' === $key && '1' === (string) $value && CP_Library::CPT === get_post_type( $post_id ) ) {
			self::drop_handle( CP_Library::handle( $post_id ) );
		}
	}

	/**
	 * Remember the username before it changes.
	 *
	 * @param null|bool $check  Short-circuit.
	 * @param int       $id     Post ID.
	 * @param string    $key    Key.
	 * @param mixed     $value  New value.
	 * @return null|bool
	 */
	public static function capture_old_handle( $check, $id, $key, $value ) {
		if ( '_cp_ig' === $key ) {
			self::$old[ (int) $id ] = strtolower( (string) get_post_meta( $id, '_cp_ig', true ) );
		}
		return $check;
	}

	/**
	 * Username changed: move the blogger's rows to the new username, never duplicating.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @param mixed  $value   New value.
	 */
	public static function on_handle_changed( $meta_id, $post_id, $key, $value ) {
		if ( '_cp_ig' !== $key || ! isset( self::$old[ (int) $post_id ] ) ) {
			return;
		}
		$old = self::$old[ (int) $post_id ];
		$new = strtolower( ltrim( (string) $value, '@' ) );
		unset( self::$old[ (int) $post_id ] );
		if ( '' === $old || '' === $new || $old === $new || CP_Library::CPT !== get_post_type( $post_id ) ) {
			return;
		}
		global $wpdb;
		$t = CP_DB::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, campaign_id, status FROM {$t} WHERE LOWER(ig_account) IN (%s, %s)", $old, $new ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$by   = array();
		foreach ( (array) $rows as $r ) {
			$by[ (int) $r->campaign_id ][] = $r;
		}
		foreach ( $by as $set ) {
			// Keep one row per campaign: prefer a client decision, else the earliest.
			usort(
				$set,
				static function ( $a, $b ) {
					$ra = in_array( $a->status, array( 'confirmed', 'declined' ), true ) ? 0 : 1;
					$rb = in_array( $b->status, array( 'confirmed', 'declined' ), true ) ? 0 : 1;
					return $ra <=> $rb ?: (int) $a->id <=> (int) $b->id;
				}
			);
			$keep = array_shift( $set );
			$wpdb->update( $t, array( 'ig_account' => $new, 'ig_url' => CP_Library::profile_url( $new ) ), array( 'id' => (int) $keep->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $set as $dup ) {
				if ( ! in_array( $dup->status, array( 'confirmed', 'declined' ), true ) ) {
					$wpdb->delete( $t, array( 'id' => (int) $dup->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
		}
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
	}

	/**
	 * One-time / manual tidy-up of every open campaign: remove waiting rows of
	 * bloggers no longer in the library (deleted or blocked) and waiting duplicates.
	 *
	 * @return array{removed:int,campaigns:int}
	 */
	public static function clean_all() {
		$open = self::open_ids();
		if ( ! $open ) {
			return array( 'removed' => 0, 'campaigns' => 0 );
		}
		// Deactivated bloggers keep their rows (hidden, not removed) so reactivating restores them.
		$active = array_merge( array_flip( array_map( 'strtolower', CP_Everyone::all_handles() ) ), CP_Library::inactive_handles() );
		global $wpdb;
		$t       = CP_DB::table();
		$in      = implode( ',', $open );
		$rows    = $wpdb->get_results( "SELECT id, campaign_id, LOWER(ig_account) AS h, status FROM {$t} WHERE campaign_id IN ({$in}) ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$seen    = array();
		$drop    = array();
		$touched = array();
		// Decisions first so a waiting duplicate never wins over a decided row.
		usort(
			$rows,
			static function ( $a, $b ) {
				$ra = in_array( $a->status, array( 'confirmed', 'declined' ), true ) ? 0 : 1;
				$rb = in_array( $b->status, array( 'confirmed', 'declined' ), true ) ? 0 : 1;
				return $ra <=> $rb ?: (int) $a->id <=> (int) $b->id;
			}
		);
		foreach ( (array) $rows as $r ) {
			$decided = in_array( $r->status, array( 'confirmed', 'declined' ), true );
			$key     = (int) $r->campaign_id . '|' . $r->h;
			if ( ! $decided && ( ! isset( $active[ $r->h ] ) || isset( $seen[ $key ] ) ) ) {
				$drop[]                           = (int) $r->id;
				$touched[ (int) $r->campaign_id ] = true;
			}
			$seen[ $key ] = true;
		}
		foreach ( array_chunk( $drop, 200 ) as $chunk ) {
			$wpdb->query( "DELETE FROM {$t} WHERE id IN (" . implode( ',', $chunk ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		}
		if ( $drop && class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		return array( 'removed' => count( $drop ), 'campaigns' => count( $touched ) );
	}
}
