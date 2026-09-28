<?php
/**
 * "Everyone" campaigns + participant management.
 *
 * When a campaign has Everyone ON, every (non-blocked) blogger in the library is
 * in the campaign, and any new blogger joins automatically — until the client
 * starts making selections. From that point auto-joining stops; the admin can
 * still add people manually (Add everyone now / pick bloggers).
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Everyone {

	const META_ON      = '_cp_everyone';
	const META_STARTED = '_cp_selection_started';

	/**
	 * Is Everyone enabled on this campaign?
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function is_on( $campaign_id ) {
		return '1' === (string) get_post_meta( $campaign_id, self::META_ON, true );
	}

	/**
	 * Turn Everyone on/off. Turning it on (before selection) fills the campaign.
	 *
	 * @param int  $campaign_id Campaign ID.
	 * @param bool $on          Enabled.
	 * @return int Bloggers added.
	 */
	public static function set_on( $campaign_id, $on ) {
		update_post_meta( $campaign_id, self::META_ON, $on ? '1' : '0' );
		return $on ? self::fill( $campaign_id ) : 0;
	}

	/**
	 * Has the client started selecting? (First client response locks it.)
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function selection_started( $campaign_id ) {
		if ( '1' === (string) get_post_meta( $campaign_id, self::META_STARTED, true ) ) {
			return true;
		}
		foreach ( CP_DB::get_bloggers( $campaign_id ) as $row ) {
			if ( 'pending' !== $row->status ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Mark that the client has started selecting.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function mark_started( $campaign_id ) {
		update_post_meta( $campaign_id, self::META_STARTED, '1' );
	}

	/**
	 * Clear the selection lock (used by the admin "Reset all responses").
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function clear_started( $campaign_id ) {
		delete_post_meta( $campaign_id, self::META_STARTED );
	}

	/**
	 * Handles of every active (published, non-blocked) library blogger.
	 *
	 * @return array
	 */
	public static function all_handles() {
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array( 'key' => '_cp_blocked', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_cp_blocked', 'value' => '1', 'compare' => '!=' ),
				),
			)
		);
		$out = array();
		foreach ( (array) $ids as $id ) {
			$h = CP_Library::handle( $id );
			if ( '' !== $h ) {
				$out[] = $h;
			}
		}
		return $out;
	}

	/**
	 * Append handles to a campaign (existing rows and their responses kept).
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $handles     Handles to add.
	 * @return int Number actually added.
	 */
	public static function add_handles( $campaign_id, $handles ) {
		$accounts = array();
		$seen     = array();
		foreach ( CP_DB::get_bloggers( $campaign_id ) as $row ) {
			$accounts[]                           = $row->ig_account;
			$seen[ strtolower( $row->ig_account ) ] = true;
		}

		$added = 0;
		foreach ( (array) $handles as $h ) {
			$h = preg_replace( '/[^A-Za-z0-9._]/', '', ltrim( trim( (string) $h ), '@' ) );
			if ( '' === $h || isset( $seen[ strtolower( $h ) ] ) ) {
				continue;
			}
			$accounts[]               = $h;
			$seen[ strtolower( $h ) ] = true;
			$added++;
		}

		if ( $added ) {
			CP_DB::sync_accounts( $campaign_id, $accounts );
		}
		return $added;
	}

	/**
	 * Remove one participant row from a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $row_id      Row ID.
	 * @return bool
	 */
	public static function remove_row( $campaign_id, $row_id ) {
		$row = CP_DB::get_blogger( $row_id );
		if ( ! $row || (int) $row->campaign_id !== (int) $campaign_id ) {
			return false;
		}
		CP_DB::delete_blogger( $row_id );
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		return true;
	}

	/**
	 * Add all bloggers if Everyone is on and selection hasn't started.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int Added.
	 */
	public static function fill( $campaign_id ) {
		if ( ! self::is_on( $campaign_id ) || self::selection_started( $campaign_id ) ) {
			return 0;
		}
		return self::add_handles( $campaign_id, self::all_handles() );
	}

	/**
	 * Campaigns that should auto-receive new bloggers right now.
	 *
	 * @return array IDs.
	 */
	public static function open_campaigns() {
		$ids = get_posts(
			array(
				'post_type'      => CP_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => self::META_ON, 'value' => '1' ),
				),
			)
		);
		$open = array();
		foreach ( (array) $ids as $cid ) {
			if ( ! self::selection_started( $cid ) && ! CP_Close::is_closed( $cid ) ) {
				$open[] = (int) $cid;
			}
		}
		return $open;
	}

	/**
	 * A library blogger was created/changed: auto-join open Everyone campaigns.
	 *
	 * @param int $blogger_id Library blogger post ID.
	 */
	public static function on_blogger_saved( $blogger_id ) {
		if ( CP_Library::CPT !== get_post_type( $blogger_id ) || 'publish' !== get_post_status( $blogger_id ) ) {
			return;
		}
		if ( '1' === (string) get_post_meta( $blogger_id, '_cp_blocked', true ) ) {
			return;
		}
		$handle = CP_Library::handle( $blogger_id );
		if ( '' === $handle ) {
			return;
		}
		foreach ( self::open_campaigns() as $cid ) {
			self::add_handles( $cid, array( $handle ) );
		}
	}
}
