<?php
/**
 * Data access for blogger entries and campaign statistics.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_DB {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cp_bloggers';
	}

	/**
	 * Current MySQL datetime.
	 *
	 * @return string
	 */
	private static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * Get all bloggers for a campaign, ordered.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public static function get_bloggers( $campaign_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY sort_order ASC, id ASC",
				absint( $campaign_id )
			)
		);
	}

	/**
	 * Rows shown in the campaign UI (client page, editor, app, exports):
	 * every row except those of deactivated bloggers. Their records stay in
	 * the table and reappear if the blogger is reactivated.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public static function visible_bloggers( $campaign_id ) {
		$rows = self::get_bloggers( $campaign_id );
		$off  = class_exists( 'CP_Library' ) ? CP_Library::inactive_handles() : array();
		if ( ! $off ) {
			return $rows;
		}
		return array_values(
			array_filter(
				$rows,
				static function ( $r ) use ( $off ) {
					return ! isset( $off[ strtolower( $r->ig_account ) ] );
				}
			)
		);
	}

	/**
	 * Get a single blogger row.
	 *
	 * @param int $id Blogger ID.
	 * @return object|null
	 */
	public static function get_blogger( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
		);
	}

	/**
	 * Update a blogger response (status + guests).
	 *
	 * @param int    $id           Blogger ID.
	 * @param int    $campaign_id  Campaign ID (ownership check).
	 * @param string $status       pending|confirmed|declined.
	 * @param int    $extra_guests Number of additional guests.
	 * @return bool
	 */
	public static function update_response( $id, $campaign_id, $status, $extra_guests ) {
		global $wpdb;
		$result = $wpdb->update(
			self::table(),
			array(
				'status'       => $status,
				'extra_guests' => absint( $extra_guests ),
				'updated_at'   => self::now(),
			),
			array(
				'id'          => absint( $id ),
				'campaign_id' => absint( $campaign_id ),
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%d' )
		);
		return false !== $result;
	}

	/**
	 * Delete a single blogger.
	 *
	 * @param int $id Blogger ID.
	 */
	public static function delete_blogger( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Delete every blogger for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public static function delete_by_campaign( $campaign_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'campaign_id' => absint( $campaign_id ) ), array( '%d' ) );
	}

	/**
	 * Sync a campaign's blogger list from an ordered list of account names.
	 * Existing accounts keep their responses; missing accounts are removed;
	 * new accounts are added as "pending".
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $accounts    Ordered, cleaned, de-duplicated account names.
	 */
	public static function sync_accounts( $campaign_id, array $accounts ) {
		global $wpdb;
		$campaign_id = absint( $campaign_id );
		$table       = self::table();
		$now         = self::now();
		$library     = class_exists( 'CP_Library' ) ? CP_Library::get_map() : array();

		$existing = self::get_bloggers( $campaign_id );
		$map      = array();
		foreach ( $existing as $row ) {
			$map[ strtolower( $row->ig_account ) ] = $row;
		}

		foreach ( $accounts as $i => $account ) {
			$key   = strtolower( $account );
			$attrs = isset( $library[ $key ] ) ? $library[ $key ] : array(
				'url'       => '',
				'gender'    => '',
				'followers' => 0,
				'tags'      => '',
				'city'      => '',
				'area'      => '',
			);

			if ( isset( $map[ $key ] ) ) {
				$wpdb->update(
					$table,
					array(
						'sort_order' => $i,
						'ig_account' => $account,
						'ig_url'     => $attrs['url'],
						'gender'     => $attrs['gender'],
						'followers'  => (int) $attrs['followers'],
						'tags'       => $attrs['tags'],
						'city'       => isset( $attrs['city'] ) ? $attrs['city'] : '',
						'area'       => isset( $attrs['area'] ) ? $attrs['area'] : '',
						'updated_at' => $now,
					),
					array( 'id' => $map[ $key ]->id ),
					array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
				unset( $map[ $key ] );
			} else {
				$wpdb->insert(
					$table,
					array(
						'campaign_id'  => $campaign_id,
						'sort_order'   => $i,
						'ig_account'   => $account,
						'ig_url'       => $attrs['url'],
						'gender'       => $attrs['gender'],
						'followers'    => (int) $attrs['followers'],
						'tags'         => $attrs['tags'],
						'city'         => isset( $attrs['city'] ) ? $attrs['city'] : '',
						'area'         => isset( $attrs['area'] ) ? $attrs['area'] : '',
						'status'       => 'pending',
						'extra_guests' => 0,
						'created_at'   => $now,
						'updated_at'   => $now,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
				);
			}
		}

		// Anything left in $map was removed from the list.
		foreach ( $map as $row ) {
			self::delete_blogger( $row->id );
		}

		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
	}

	/**
	 * Reset every response in a campaign back to "no response".
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function reset_campaign( $campaign_id ) {
		global $wpdb;
		$result = $wpdb->update(
			self::table(),
			array(
				'status'       => 'pending',
				'extra_guests' => 0,
				'updated_at'   => self::now(),
			),
			array( 'campaign_id' => absint( $campaign_id ) ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Aggregate stats for every published campaign in a single query (fast list).
	 *
	 * @return array
	 */
	public static function all_campaign_stats( $include_drafts = false ) {
		global $wpdb;
		$table = self::table();
		$posts = $wpdb->posts;
		$statuses = $include_drafts ? "'publish','draft','pending','private','future'" : "'publish'";

		// Deactivated bloggers' rows are kept but not counted (hidden from the campaign UI).
		$off    = class_exists( 'CP_Library' ) ? array_keys( CP_Library::inactive_handles() ) : array();
		$hide   = $off ? $wpdb->prepare( ' AND LOWER( b.ig_account ) NOT IN ( ' . implode( ',', array_fill( 0, count( $off ), '%s' ) ) . ' )', $off ) : ''; // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS title, p.post_status AS pstatus, p.post_date AS pdate,
					COUNT( b.id ) AS total,
					SUM( CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END ) AS confirmed,
					SUM( CASE WHEN b.status = 'declined' THEN 1 ELSE 0 END ) AS declined,
					SUM( CASE WHEN b.status NOT IN ( 'confirmed', 'declined' ) THEN 1 ELSE 0 END ) AS pending,
					SUM( CASE WHEN b.status = 'confirmed' THEN b.extra_guests ELSE 0 END ) AS extra_guests
				FROM {$posts} p
				LEFT JOIN {$table} b ON b.campaign_id = p.ID{$hide}
				WHERE p.post_type = %s AND p.post_status IN ( {$statuses} )
				GROUP BY p.ID, p.post_title, p.post_status, p.post_date
				ORDER BY p.post_date DESC",
				CP_POST_TYPE
			)
		);

		$out = array();
		foreach ( (array) $rows as $r ) {
			$confirmed = (int) $r->confirmed;
			$guests    = (int) $r->extra_guests;
			$out[]     = array(
				'id'           => (int) $r->id,
				'title'        => html_entity_decode( get_the_title( $r->id ), ENT_QUOTES ),
				'total'        => (int) $r->total,
				'confirmed'    => $confirmed,
				'declined'     => (int) $r->declined,
				'pending'      => (int) $r->pending,
				'extra_guests' => $guests,
				'attendance'   => $confirmed + $guests,
				'status'       => (string) $r->pstatus,
				'closed'       => '1' === (string) get_post_meta( (int) $r->id, '_cp_closed', true ),
				'type'         => class_exists( 'CP_Library' ) ? CP_Library::campaign_type( (int) $r->id ) : '',
				'expires'      => class_exists( 'CP_Expiry' ) ? CP_Expiry::get( (int) $r->id ) : 0,
				'logo'         => class_exists( 'CP_Logo' ) ? CP_Logo::url( (int) $r->id ) : '',
				'date'         => mysql2date( 'c', $r->pdate ),
			);
		}
		return $out;
	}

	/**
	 * Aggregate statistics for a campaign (visible rows only).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public static function stats( $campaign_id ) {
		$rows      = self::visible_bloggers( $campaign_id );
		$total     = count( $rows );
		$confirmed = 0;
		$declined  = 0;
		$pending   = 0;
		$guests    = 0;

		foreach ( $rows as $row ) {
			if ( 'confirmed' === $row->status ) {
				$confirmed++;
				$guests += (int) $row->extra_guests;
			} elseif ( 'declined' === $row->status ) {
				$declined++;
			} else {
				$pending++;
			}
		}

		return array(
			'total'       => $total,
			'confirmed'   => $confirmed,
			'declined'    => $declined,
			'pending'     => $pending,
			'extra_guests'=> $guests,
			// Total attendance = confirmed bloggers + their additional guests.
			'attendance'  => $confirmed + $guests,
			'rows'        => $rows,
		);
	}
}
