<?php
/**
 * Insights: popularity labels and analytics computed automatically from
 * existing campaign data (the cp_bloggers rows across all campaigns).
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Insights {

	const CACHE = 'cp_popularity_map';
	const RECENT_DAYS = 60;

	/**
	 * Invalidate cached insights (call after any response/campaign change).
	 */
	public static function bust() {
		foreach ( array( 30, 90, 365, 0 ) as $pd ) {
			delete_transient( 'cp_ins_report_' . $pd );
		}
		delete_transient( self::CACHE );
	}

	/**
	 * Map of lowercase handle => aggregate counts across published campaigns.
	 * Cached for an hour.
	 *
	 * @return array
	 */
	public static function popularity_map() {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cp_bloggers';
		$posts = $wpdb->posts;
		$since = gmdate( 'Y-m-d H:i:s', time() - self::RECENT_DAYS * DAY_IN_SECONDS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT LOWER(b.ig_account) AS hkey, MIN(b.ig_account) AS handle,
					COUNT(*) AS included,
					SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
					SUM(CASE WHEN b.status = 'declined' THEN 1 ELSE 0 END) AS declined,
					SUM(CASE WHEN b.created_at >= %s THEN 1 ELSE 0 END) AS recent
				FROM {$table} b
				INNER JOIN {$posts} p ON p.ID = b.campaign_id AND p.post_type = %s AND p.post_status = 'publish'
				LEFT JOIN {$wpdb->postmeta} cm ON cm.post_id = p.ID AND cm.meta_key = '_cp_closed'
				WHERE b.ig_account <> ''
					AND NOT ( b.status = 'pending' AND COALESCE( cm.meta_value, '' ) = '1' )
				GROUP BY LOWER(b.ig_account)",
				$since,
				CP_POST_TYPE
			)
		);

		$map = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r->hkey ] = array(
				'handle'    => $r->handle,
				'included'  => (int) $r->included,
				'confirmed' => (int) $r->confirmed,
				'declined'  => (int) $r->declined,
				'recent'    => (int) $r->recent,
			);
		}

		set_transient( self::CACHE, $map, HOUR_IN_SECONDS );
		return $map;
	}

	/**
	 * Popularity label for a set of counts.
	 *
	 * @param array $row Counts (included, confirmed, recent).
	 * @return array { key, label } or empty strings.
	 */
	public static function label( $row ) {
		$included  = isset( $row['included'] ) ? (int) $row['included'] : 0;
		$confirmed = isset( $row['confirmed'] ) ? (int) $row['confirmed'] : 0;
		$recent    = isset( $row['recent'] ) ? (int) $row['recent'] : 0;

		if ( $included >= 8 || $confirmed >= 5 ) {
			return array( 'key' => 'top', 'label' => __( 'Top Choice', 'hypeit' ) );
		}
		if ( $included >= 4 ) {
			return array( 'key' => 'popular', 'label' => __( 'Popular Choice', 'hypeit' ) );
		}
		if ( $recent >= 2 ) {
			return array( 'key' => 'trending', 'label' => __( 'Trending', 'hypeit' ) );
		}
		if ( $included >= 2 ) {
			return array( 'key' => 'frequent', 'label' => __( 'Frequently Selected', 'hypeit' ) );
		}
		return array( 'key' => '', 'label' => '' );
	}

	/**
	 * Label for a single handle (uses the cached map).
	 *
	 * @param string $handle Handle.
	 * @return array
	 */
	public static function label_for( $handle ) {
		$map = self::popularity_map();
		$key = strtolower( ltrim( (string) $handle, '@' ) );
		return isset( $map[ $key ] ) ? self::label( $map[ $key ] ) : array( 'key' => '', 'label' => '' );
	}

	/**
	 * Full analytics for one blogger handle.
	 *
	 * @param string $handle Handle.
	 * @return array
	 */
	public static function stats_for_handle( $handle ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cp_bloggers';
		$posts = $wpdb->posts;
		$key   = strtolower( ltrim( (string) $handle, '@' ) );

		$map     = self::popularity_map();
		$counts  = isset( $map[ $key ] ) ? $map[ $key ] : array( 'included' => 0, 'confirmed' => 0, 'declined' => 0, 'recent' => 0 );
		$responded = $counts['confirmed'] + $counts['declined'];

		$recent = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS title, b.status AS status, b.created_at AS created_at
				FROM {$table} b
				INNER JOIN {$posts} p ON p.ID = b.campaign_id AND p.post_type = %s
				WHERE LOWER(b.ig_account) = %s
				ORDER BY b.created_at DESC
				LIMIT 6",
				CP_POST_TYPE,
				$key
			)
		);

		$campaigns = array();
		foreach ( (array) $recent as $r ) {
			$campaigns[] = array(
				'id'     => (int) $r->id,
				'title'  => html_entity_decode( get_the_title( $r->id ), ENT_QUOTES ),
				'status' => $r->status,
				'date'   => $r->created_at,
			);
		}

		return array(
			'included'        => (int) $counts['included'],
			'confirmed'       => (int) $counts['confirmed'],
			'declined'        => (int) $counts['declined'],
			'pending'         => max( 0, (int) $counts['included'] - $responded ),
			'selected'        => (int) $counts['confirmed'],
			'acceptance_rate' => $responded ? round( $counts['confirmed'] / $responded * 100 ) : 0,
			'rejection_rate'  => $responded ? round( $counts['declined'] / $responded * 100 ) : 0,
			'label'           => self::label( $counts ),
			'recent'          => $campaigns,
		);
	}

	/**
	 * Global dashboard statistics.
	 *
	 * @return array
	 */
	public static function global_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'cp_bloggers';
		$posts = $wpdb->posts;

		$total_campaigns = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$posts} WHERE post_type = %s AND post_status = 'publish'", CP_POST_TYPE )
		);

		$map = self::popularity_map();

		$total_bloggers = count( $map );
		$sum_inc        = 0;
		$sum_conf       = 0;
		$sum_dec        = 0;
		foreach ( $map as $r ) {
			$sum_inc  += $r['included'];
			$sum_conf += $r['confirmed'];
			$sum_dec  += $r['declined'];
		}
		$responded = $sum_conf + $sum_dec;

		$top = static function ( $field, $limit = 8 ) use ( $map ) {
			$rows = array_values( $map );
			usort(
				$rows,
				static function ( $a, $b ) use ( $field ) {
					return $b[ $field ] <=> $a[ $field ];
				}
			);
			$out = array();
			foreach ( array_slice( $rows, 0, $limit ) as $r ) {
				if ( $r[ $field ] > 0 ) {
					$out[] = array( 'handle' => $r['handle'], 'value' => (int) $r[ $field ] );
				}
			}
			return $out;
		};

		// Activity over time (last 12 months).
		$activity = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(b.created_at, '%%Y-%%m') AS ym, COUNT(*) AS c
				FROM {$table} b
				INNER JOIN {$posts} p ON p.ID = b.campaign_id AND p.post_type = %s AND p.post_status = 'publish'
				WHERE b.created_at IS NOT NULL
				GROUP BY ym ORDER BY ym DESC LIMIT 12",
				CP_POST_TYPE
			)
		);
		$months = array();
		foreach ( array_reverse( (array) $activity ) as $a ) {
			$months[] = array( 'month' => $a->ym, 'count' => (int) $a->c );
		}

		return array(
			'total_campaigns'  => $total_campaigns,
			'total_bloggers'   => $total_bloggers,
			'total_inclusions' => $sum_inc,
			'acceptance_rate'  => $responded ? round( $sum_conf / $responded * 100 ) : 0,
			'rejection_rate'   => $responded ? round( $sum_dec / $responded * 100 ) : 0,
			'most_selected'    => $top( 'included' ),
			'trending'         => $top( 'recent' ),
			'most_accepted'    => $top( 'confirmed' ),
			'most_rejected'    => $top( 'declined' ),
			'activity'         => $months,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Report (dashboard + app)                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Follower tier label.
	 *
	 * @param int $f Followers.
	 * @return string
	 */
	public static function tier( $f ) {
		if ( $f >= 500000 ) {
			return __( 'Mega (500K+)', 'hypeit' );
		}
		if ( $f >= 100000 ) {
			return __( 'Macro (100K–500K)', 'hypeit' );
		}
		if ( $f >= 50000 ) {
			return __( 'Mid (50K–100K)', 'hypeit' );
		}
		if ( $f >= 10000 ) {
			return __( 'Micro (10K–50K)', 'hypeit' );
		}
		return $f > 0 ? __( 'Nano (under 10K)', 'hypeit' ) : __( 'Unknown', 'hypeit' );
	}

	/**
	 * Library snapshot: handle => profile bits (one query set, cached per request).
	 *
	 * @return array
	 */
	private static function library_index() {
		static $idx = null;
		if ( null !== $idx ) {
			return $idx;
		}
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$ids = array_map( 'intval', (array) $ids );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			update_object_term_cache( $ids, CP_Library::CPT );
		}
		$genders = CP_Library::genders();
		$idx     = array();
		foreach ( $ids as $id ) {
			$h = strtolower( CP_Library::handle( $id ) );
			if ( '' === $h ) {
				continue;
			}
			$cats = get_the_terms( $id, CP_Library::TAX_TAG );
			$g    = (string) get_post_meta( $id, '_cp_gender', true );
			$idx[ $h ] = array(
				'id'       => $id,
				'name'     => trim( get_post_meta( $id, '_cp_first', true ) . ' ' . get_post_meta( $id, '_cp_last', true ) ),
				'handle'   => CP_Library::handle( $id ),
				'gender'   => isset( $genders[ $g ] ) ? $genders[ $g ] : __( 'Unspecified', 'hypeit' ),
				'city'     => (string) get_post_meta( $id, '_cp_city', true ),
				'fol'      => (int) get_post_meta( $id, '_cp_followers', true ),
				'verified' => '1' === (string) get_post_meta( $id, '_cp_verified', true ),
				'blocked'  => '1' === (string) get_post_meta( $id, '_cp_blocked', true ),
				'inactive' => '1' === (string) get_post_meta( $id, CP_Library::META_INACTIVE, true ),
				'complete' => (int) get_post_meta( $id, '_cp_complete', true ),
				'fd'       => (int) get_post_meta( $id, '_cp_followers_delta', true ),
				'igstat'   => (string) get_post_meta( $id, '_cp_ig_status', true ),
				'created'  => get_post_time( 'U', true, $id ),
				'cats'     => ( $cats && ! is_wp_error( $cats ) ) ? wp_list_pluck( $cats, 'name' ) : array(),
			);
		}
		return $idx;
	}

	/**
	 * Full insights report for a period (days; 0 = all time).
	 *
	 * @param int $period 30 | 90 | 365 | 0.
	 * @return array
	 */
	public static function report( $period = 90 ) {
		$period = in_array( (int) $period, array( 30, 90, 365, 0 ), true ) ? (int) $period : 90;
		$cached = get_transient( 'cp_ins_report_' . $period );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'cp_bloggers';
		$now   = time();
		$since = $period ? $now - $period * DAY_IN_SECONDS : 0;
		$prev  = $period ? $since - $period * DAY_IN_SECONDS : 0;
		$from  = $period ? gmdate( 'Y-m-d H:i:s', $prev ) : '1970-01-01 00:00:00';

		// Every participant row of published campaigns since the start of the previous window.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT b.campaign_id AS cid, LOWER(b.ig_account) AS h, b.status, b.extra_guests AS g,
					UNIX_TIMESTAMP(p.post_date_gmt) AS pts, p.post_title AS title, COALESCE(cm.meta_value,'') AS closed
				FROM {$table} b
				INNER JOIN {$wpdb->posts} p ON p.ID = b.campaign_id AND p.post_type = %s AND p.post_status = 'publish'
				LEFT JOIN {$wpdb->postmeta} cm ON cm.post_id = p.ID AND cm.meta_key = '_cp_closed'
				WHERE p.post_date_gmt >= %s AND b.ig_account <> ''",
				CP_POST_TYPE,
				$from
			)
		);

		$lib = self::library_index();
		$cur = array();
		$old = array();
		foreach ( (array) $rows as $r ) {
			// Waiting-for-response rows of closed campaigns don't count against anyone.
			if ( 'pending' === $r->status && '1' === $r->closed ) {
				continue;
			}
			if ( ! $period || (int) $r->pts >= $since ) {
				$cur[] = $r;
			} else {
				$old[] = $r;
			}
		}

		$kpi = static function ( $set ) {
			$k = array( 'campaigns' => array(), 'invited' => 0, 'confirmed' => 0, 'declined' => 0, 'pending' => 0, 'people' => 0 );
			foreach ( $set as $r ) {
				$k['campaigns'][ (int) $r->cid ] = true;
				$k['invited']++;
				if ( 'confirmed' === $r->status ) {
					$k['confirmed']++;
					$k['people'] += 1 + (int) $r->g;
				} elseif ( 'declined' === $r->status ) {
					$k['declined']++;
				} else {
					$k['pending']++;
				}
			}
			$resp = $k['confirmed'] + $k['declined'];
			return array(
				'campaigns'  => count( $k['campaigns'] ),
				'invited'    => $k['invited'],
				'confirmed'  => $k['confirmed'],
				'declined'   => $k['declined'],
				'pending'    => $k['pending'],
				'people'     => $k['people'],
				'acceptance' => $resp ? (int) round( $k['confirmed'] / $resp * 100 ) : null,
				'response'   => $k['invited'] ? (int) round( $resp / $k['invited'] * 100 ) : null,
				'avg_size'   => count( $k['campaigns'] ) ? round( $k['invited'] / count( $k['campaigns'] ), 1 ) : 0,
			);
		};
		$k_cur = $kpi( $cur );
		$k_old = $period ? $kpi( $old ) : null;

		// Library health.
		$active   = array_filter( $lib, static function ( $b ) { return ! $b['blocked'] && ! $b['inactive']; } );
		$new_libs = 0;
		$verified = 0;
		$personal = 0;
		$complete = 0;
		$pct_sum  = 0;
		$growing  = 0;
		$shrink   = 0;
		$reach    = 0;
		foreach ( $active as $b ) {
			if ( $period && $b['created'] >= $since ) {
				$new_libs++;
			}
			$verified += $b['verified'] ? 1 : 0;
			$personal += 'personal' === $b['igstat'] ? 1 : 0;
			$complete += $b['complete'] >= 100 ? 1 : 0;
			$pct_sum  += $b['complete'];
			$growing  += $b['fd'] > 0 ? 1 : 0;
			$shrink   += $b['fd'] < 0 ? 1 : 0;
			$reach    += $b['fol'];
		}
		$tiers = array();
		foreach ( $active as $b ) {
			$t = self::tier( $b['fol'] );
			$tiers[ $t ] = isset( $tiers[ $t ] ) ? $tiers[ $t ] + 1 : 1;
		}

		// Breakdowns by profile.
		$dims = array( 'category' => array(), 'gender' => array(), 'city' => array(), 'tier' => array() );
		$per  = array();
		foreach ( $cur as $r ) {
			$b    = isset( $lib[ $r->h ] ) ? $lib[ $r->h ] : null;
			$keys = array(
				'category' => $b && $b['cats'] ? $b['cats'] : array( __( 'Uncategorised', 'hypeit' ) ),
				'gender'   => array( $b ? $b['gender'] : __( 'Unspecified', 'hypeit' ) ),
				'city'     => array( $b && $b['city'] ? $b['city'] : __( 'Unknown', 'hypeit' ) ),
				'tier'     => array( self::tier( $b ? $b['fol'] : 0 ) ),
			);
			foreach ( $keys as $dim => $vals ) {
				foreach ( $vals as $v ) {
					if ( ! isset( $dims[ $dim ][ $v ] ) ) {
						$dims[ $dim ][ $v ] = array( 'label' => $v, 'invited' => 0, 'confirmed' => 0, 'declined' => 0 );
					}
					$dims[ $dim ][ $v ]['invited']++;
					if ( 'confirmed' === $r->status || 'declined' === $r->status ) {
						$dims[ $dim ][ $v ][ $r->status ]++;
					}
				}
			}
			if ( ! isset( $per[ $r->h ] ) ) {
				$per[ $r->h ] = array( 'invited' => 0, 'confirmed' => 0, 'declined' => 0 );
			}
			$per[ $r->h ]['invited']++;
			if ( 'confirmed' === $r->status || 'declined' === $r->status ) {
				$per[ $r->h ][ $r->status ]++;
			}
		}
		foreach ( $dims as $dim => $list ) {
			foreach ( $list as $k => $d ) {
				$resp                           = $d['confirmed'] + $d['declined'];
				$dims[ $dim ][ $k ]['acceptance'] = $resp ? (int) round( $d['confirmed'] / $resp * 100 ) : null;
			}
			uasort( $dims[ $dim ], static function ( $a, $b ) { return $b['invited'] <=> $a['invited']; } );
			$dims[ $dim ] = array_values( array_slice( $dims[ $dim ], 0, 10 ) );
		}

		// Leaderboards (period).
		$person = static function ( $h, $value, $extra = '' ) use ( $lib ) {
			$b = isset( $lib[ $h ] ) ? $lib[ $h ] : null;
			return array(
				'id'       => $b ? $b['id'] : 0,
				'handle'   => $b ? $b['handle'] : $h,
				'name'     => $b ? $b['name'] : '',
				'verified' => $b ? $b['verified'] : false,
				'photo'    => $b ? CP_Photo::url( $b['id'], 's' ) : '',
				'value'    => $value,
				'extra'    => $extra,
			);
		};
		$board = static function ( $sort, $filter, $fmt, $limit = 8 ) use ( $per, $person, $lib ) {
			// Deactivated bloggers don't appear on leaderboards (their history still counts above).
			$list = array_filter(
				array_filter( $per, $filter ),
				static function ( $h ) use ( $lib ) { return ! ( isset( $lib[ $h ] ) && $lib[ $h ]['inactive'] ); },
				ARRAY_FILTER_USE_KEY
			);
			uasort( $list, $sort );
			$out = array();
			foreach ( array_slice( $list, 0, $limit, true ) as $h => $p ) {
				$out[] = $person( $h, $fmt( $p ), '' );
			}
			return $out;
		};
		$rate = static function ( $p ) { $r = $p['confirmed'] + $p['declined']; return $r ? (int) round( $p['confirmed'] / $r * 100 ) : 0; };

		$boards = array(
			'selected' => $board(
				static function ( $a, $b ) { return $b['confirmed'] <=> $a['confirmed'] ?: $b['invited'] <=> $a['invited']; },
				static function ( $p ) { return $p['confirmed'] > 0; },
				static function ( $p ) { return $p['confirmed']; }
			),
			'acceptance' => $board(
				static function ( $a, $b ) use ( $rate ) { return $rate( $b ) <=> $rate( $a ) ?: $b['confirmed'] <=> $a['confirmed']; },
				static function ( $p ) { return ( $p['confirmed'] + $p['declined'] ) >= 3; },
				static function ( $p ) use ( $rate ) { return $rate( $p ) . '%'; }
			),
			'declined' => $board(
				static function ( $a, $b ) { return $b['declined'] <=> $a['declined']; },
				static function ( $p ) { return $p['declined'] > 0; },
				static function ( $p ) { return $p['declined']; }
			),
			'invited' => $board(
				static function ( $a, $b ) { return $b['invited'] <=> $a['invited']; },
				static function ( $p ) { return $p['invited'] > 0; },
				static function ( $p ) { return $p['invited']; }
			),
			'reliable' => array(),
		);
		if ( class_exists( 'CP_Atrium' ) && CP_Atrium::active() ) {
			$rel = array();
			foreach ( $active as $h => $b ) {
				$x = CP_Atrium::reliability( $b['id'] );
				if ( null !== $x['rate'] && $x['expected'] >= 2 ) {
					$rel[ $h ] = $x;
				}
			}
			uasort( $rel, static function ( $a, $b ) { return $b['rate'] <=> $a['rate'] ?: $b['attended'] <=> $a['attended']; } );
			foreach ( array_slice( $rel, 0, 8, true ) as $h => $x ) {
				$boards['reliable'][] = $person( $h, $x['rate'] . '%', $x['attended'] . '/' . $x['expected'] );
			}
		}

		// Campaigns in the period.
		$camps = array();
		foreach ( $cur as $r ) {
			$c = (int) $r->cid;
			if ( ! isset( $camps[ $c ] ) ) {
				$camps[ $c ] = array( 'id' => $c, 'title' => html_entity_decode( $r->title, ENT_QUOTES ), 'ts' => (int) $r->pts, 'closed' => '1' === $r->closed, 'invited' => 0, 'confirmed' => 0, 'declined' => 0, 'people' => 0 );
			}
			$camps[ $c ]['invited']++;
			if ( 'confirmed' === $r->status ) {
				$camps[ $c ]['confirmed']++;
				$camps[ $c ]['people'] += 1 + (int) $r->g;
			} elseif ( 'declined' === $r->status ) {
				$camps[ $c ]['declined']++;
			}
		}
		foreach ( $camps as $c => $x ) {
			$resp                        = $x['confirmed'] + $x['declined'];
			$camps[ $c ]['response']     = $x['invited'] ? (int) round( $resp / $x['invited'] * 100 ) : 0;
			$camps[ $c ]['acceptance']   = $resp ? (int) round( $x['confirmed'] / $resp * 100 ) : null;
			$camps[ $c ]['date']         = date_i18n( get_option( 'date_format' ), $x['ts'] );
		}
		usort( $camps, static function ( $a, $b ) { return $b['ts'] <=> $a['ts']; } );

		// 12-month trend (always).
		$trend = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$key           = gmdate( 'Y-m', strtotime( gmdate( 'Y-m-01' ) . " -{$i} months" ) );
			$trend[ $key ] = array( 'month' => date_i18n( 'M', strtotime( $key . '-01' ) ), 'confirmed' => 0, 'declined' => 0, 'pending' => 0 );
		}
		$trows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DATE_FORMAT(p.post_date_gmt, '%%Y-%%m') AS ym, b.status, COUNT(*) AS n, COALESCE(cm.meta_value,'') AS closed
				FROM {$table} b
				INNER JOIN {$wpdb->posts} p ON p.ID = b.campaign_id AND p.post_type = %s AND p.post_status = 'publish'
				LEFT JOIN {$wpdb->postmeta} cm ON cm.post_id = p.ID AND cm.meta_key = '_cp_closed'
				WHERE p.post_date_gmt >= %s
				GROUP BY ym, b.status, closed",
				CP_POST_TYPE,
				gmdate( 'Y-m-01 00:00:00', strtotime( gmdate( 'Y-m-01' ) . ' -11 months' ) )
			)
		);
		foreach ( (array) $trows as $t ) {
			if ( ! isset( $trend[ $t->ym ] ) || ( 'pending' === $t->status && '1' === $t->closed ) ) {
				continue;
			}
			$st                          = in_array( $t->status, array( 'confirmed', 'declined' ), true ) ? $t->status : 'pending';
			$trend[ $t->ym ][ $st ] += (int) $t->n;
		}

		// Needs attention.
		$ever = self::popularity_map();
		$never = 0;
		foreach ( $active as $h => $b ) {
			if ( ( empty( $ever[ $h ] ) || 0 === (int) $ever[ $h ]['confirmed'] ) && $b['created'] < $now - 30 * DAY_IN_SECONDS ) {
				$never++;
			}
		}
		$cold = 0;
		foreach ( $ever as $h => $e ) {
			if ( isset( $active[ $h ] ) && 0 === (int) $e['confirmed'] && (int) $e['declined'] >= 3 ) {
				$cold++;
			}
		}

		$delta = static function ( $a, $b ) {
			return ( null === $a || null === $b ) ? null : $a - $b;
		};

		$report = array(
			'period'   => $period,
			'kpi'      => $k_cur,
			'prev'     => $k_old,
			'deltas'   => $k_old ? array(
				'invited'    => $delta( $k_cur['invited'], $k_old['invited'] ),
				'acceptance' => $delta( $k_cur['acceptance'], $k_old['acceptance'] ),
				'campaigns'  => $delta( $k_cur['campaigns'], $k_old['campaigns'] ),
				'people'     => $delta( $k_cur['people'], $k_old['people'] ),
				'response'   => $delta( $k_cur['response'], $k_old['response'] ),
			) : null,
			'library'  => array(
				'total'         => count( $active ),
				'new'           => $new_libs,
				'verified'      => $verified,
				'verified_pct'  => count( $active ) ? (int) round( $verified / count( $active ) * 100 ) : 0,
				'personal'      => $personal,
				'complete'      => $complete,
				'complete_pct'  => count( $active ) ? (int) round( $complete / count( $active ) * 100 ) : 0,
				'avg_complete'  => count( $active ) ? (int) round( $pct_sum / count( $active ) ) : 0,
				'growing'       => $growing,
				'shrinking'     => $shrink,
				'reach'         => $reach,
				'tiers'         => $tiers,
				'inactive'      => count( array_filter( $lib, static function ( $b ) { return $b['inactive']; } ) ),
			),
			'trend'    => array_values( $trend ),
			'dims'     => $dims,
			'boards'   => $boards,
			'campaigns'=> array_slice( $camps, 0, 50 ),
			'attention'=> array(
				'never_selected' => $never,
				'often_declined' => $cold,
				'unverified'     => count( $active ) - $verified,
				'personal'       => $personal,
			),
			'generated'=> $now,
		);
		set_transient( 'cp_ins_report_' . $period, $report, 10 * MINUTE_IN_SECONDS );
		return $report;
	}

	/**
	 * Register the admin dashboard.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Dashboard submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Insights', 'hypeit' ),
			__( 'Insights', 'hypeit' ),
			'edit_posts',
			'cp-insights',
			array( __CLASS__, 'render_dashboard' )
		);
	}

	/**
	 * Render the global dashboard.
	 */
	public static function render_dashboard() {
		$period = isset( $_GET['period'] ) ? (int) $_GET['period'] : 90; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$r      = self::report( $period );
		$k      = $r['kpi'];
		$d      = $r['deltas'];
		$base   = admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-insights' );
		$pl     = array( 30 => __( '30 days', 'hypeit' ), 90 => __( '90 days', 'hypeit' ), 365 => __( '12 months', 'hypeit' ), 0 => __( 'All time', 'hypeit' ) );

		$delta = static function ( $v, $suffix = '' ) {
			if ( null === $v || 0 === $v ) {
				return '';
			}
			$up = $v > 0;
			return '<span class="cpi-delta ' . ( $up ? 'is-up' : 'is-down' ) . '">' . ( $up ? '▲ ' : '▼ ' ) . esc_html( abs( $v ) . $suffix ) . '</span>';
		};
		$pct = static function ( $v ) {
			return null === $v ? '—' : (int) $v . '%';
		};
		$who = static function ( $p ) {
			$label = $p['name'] ? $p['name'] : '@' . $p['handle'];
			$html  = CP_Photo::avatar_html( $p['id'], 30, $p['name'], $p['handle'] ) . '<span class="cpi-who-name">' . esc_html( $label ) . ( $p['verified'] ? ' <b class="cpi-ok">✓</b>' : '' ) . '</span>';
			return $p['id'] ? '<a class="cpi-who" href="' . esc_url( get_edit_post_link( $p['id'] ) ) . '">' . $html . '</a>' : '<span class="cpi-who">' . $html . '</span>';
		};
		$max_trend = 1;
		foreach ( $r['trend'] as $t ) {
			$max_trend = max( $max_trend, $t['confirmed'] + $t['declined'] + $t['pending'] );
		}
		?>
		<?php
		$lib   = wp_parse_args( $r['library'], array( 'total' => 0, 'new' => 0, 'verified' => 0, 'verified_pct' => 0, 'personal' => 0, 'complete' => 0, 'complete_pct' => 0, 'avg_complete' => 0, 'growing' => 0, 'shrinking' => 0, 'reach' => 0, 'tiers' => array(), 'inactive' => 0 ) );
		$blist = admin_url( 'edit.php?post_type=' . CP_Library::CPT );
		$tmax  = $lib['tiers'] ? max( $lib['tiers'] ) : 1;
		$compact = static function ( $n ) {
			$n = (int) $n;
			if ( $n >= 1000000 ) {
				return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M';
			}
			if ( $n >= 1000 ) {
				return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K';
			}
			return number_format_i18n( $n );
		};
		$tot_trend = 0;
		foreach ( $r['trend'] as $t ) {
			$tot_trend += $t['confirmed'] + $t['declined'] + $t['pending'];
		}
		?>
		<div class="wrap cp-admin cp-wide cpi">
			<div class="cp-pagehead">
				<div>
					<h1><?php esc_html_e( 'Insights', 'hypeit' ); ?></h1>
					<p class="cp-sub">
						<?php
						echo esc_html(
							$r['period']
								/* translators: %s: period. */
								? sprintf( __( 'Published campaigns from the last %s, compared with the period before. Bloggers still waiting for a response in closed campaigns aren’t counted.', 'hypeit' ), $pl[ $r['period'] ] )
								: __( 'All published campaigns. Bloggers still waiting for a response in closed campaigns aren’t counted.', 'hypeit' )
						);
						?>
					</p>
				</div>
				<div class="cpw-seg" role="group" aria-label="<?php esc_attr_e( 'Period', 'hypeit' ); ?>">
					<?php foreach ( $pl as $days => $lab ) : ?>
						<a class="<?php echo (int) $r['period'] === (int) $days ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'period', $days, $base ) ); ?>"><?php echo esc_html( $lab ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="cpi-kpis">
				<div class="cpi-kpi"><span><?php esc_html_e( 'Campaigns', 'hypeit' ); ?></span><b><?php echo (int) $k['campaigns']; ?></b><?php echo $d ? $delta( $d['campaigns'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small><?php echo esc_html( sprintf( /* translators: %s: avg. */ __( '%s bloggers each', 'hypeit' ), number_format_i18n( $k['avg_size'], 1 ) ) ); ?></small></div>
				<div class="cpi-kpi"><span><?php esc_html_e( 'Bloggers proposed', 'hypeit' ); ?></span><b><?php echo esc_html( number_format_i18n( $k['invited'] ) ); ?></b><?php echo $d ? $delta( $d['invited'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small><?php echo esc_html( sprintf( /* translators: %s: pending. */ __( '%s still waiting', 'hypeit' ), number_format_i18n( $k['pending'] ) ) ); ?></small></div>
				<div class="cpi-kpi is-green"><span><?php esc_html_e( 'Client acceptance', 'hypeit' ); ?></span><b><?php echo esc_html( $pct( $k['acceptance'] ) ); ?></b><?php echo $d ? $delta( $d['acceptance'], ' pts' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small><?php echo esc_html( sprintf( /* translators: 1: confirmed, 2: declined. */ __( '%1$s confirmed · %2$s declined', 'hypeit' ), number_format_i18n( $k['confirmed'] ), number_format_i18n( $k['declined'] ) ) ); ?></small></div>
				<div class="cpi-kpi"><span><?php esc_html_e( 'Response rate', 'hypeit' ); ?></span><b><?php echo esc_html( $pct( $k['response'] ) ); ?></b><?php echo $d ? $delta( $d['response'], ' pts' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small><?php esc_html_e( 'of proposed bloggers answered', 'hypeit' ); ?></small></div>
				<div class="cpi-kpi is-dark"><span><?php esc_html_e( 'People attending', 'hypeit' ); ?></span><b><?php echo esc_html( number_format_i18n( $k['people'] ) ); ?></b><?php echo $d ? $delta( $d['people'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small><?php esc_html_e( 'confirmed bloggers + guests', 'hypeit' ); ?></small></div>
				<div class="cpi-kpi"><span><?php esc_html_e( 'Blogger library', 'hypeit' ); ?></span><b><?php echo esc_html( number_format_i18n( $lib['total'] ) ); ?></b><?php echo $r['period'] && $lib['new'] ? '<span class="cpi-delta is-up">+' . (int) $lib['new'] . ' ' . esc_html__( 'new', 'hypeit' ) . '</span>' : ''; ?><small><?php echo esc_html( sprintf( /* translators: %d: percent. */ __( '%d%% verified', 'hypeit' ), $lib['verified_pct'] ) ); ?></small></div>
			</div>

			<div class="cpi-top">
				<div class="cpw-card cpi-trendcard">
					<div class="cpi-cardhead">
						<h3><?php esc_html_e( 'Selections — last 12 months', 'hypeit' ); ?></h3>
						<p class="cpi-legend"><i class="c"></i> <?php esc_html_e( 'Confirmed', 'hypeit' ); ?> <i class="d"></i> <?php esc_html_e( 'Declined', 'hypeit' ); ?> <i class="p"></i> <?php esc_html_e( 'Waiting', 'hypeit' ); ?></p>
					</div>
					<?php if ( ! $tot_trend ) : ?>
						<p class="cpw-muted"><?php esc_html_e( 'No campaign activity in the last 12 months yet.', 'hypeit' ); ?></p>
					<?php endif; ?>
					<div class="cpi-trend">
						<?php foreach ( $r['trend'] as $t ) : ?>
							<?php $tot = $t['confirmed'] + $t['declined'] + $t['pending']; ?>
							<div class="cpi-col" title="<?php echo esc_attr( sprintf( /* translators: 1: month, 2: confirmed, 3: declined, 4: waiting. */ __( '%1$s: %2$d confirmed · %3$d declined · %4$d waiting', 'hypeit' ), $t['month'], $t['confirmed'], $t['declined'], $t['pending'] ) ); ?>">
								<em><?php echo $tot ? (int) $tot : ''; ?></em>
								<div class="cpi-stack" style="height:<?php echo (int) round( $tot / $max_trend * 100 ); ?>%">
									<i class="c" style="flex:<?php echo (int) $t['confirmed']; ?>"></i><i class="d" style="flex:<?php echo (int) $t['declined']; ?>"></i><i class="p" style="flex:<?php echo (int) $t['pending']; ?>"></i>
								</div>
								<span><?php echo esc_html( $t['month'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="cpw-card cpi-health">
					<h3><?php esc_html_e( 'Library health', 'hypeit' ); ?></h3>
					<div class="cpi-meter">
						<div class="cpi-meter-top"><span><?php esc_html_e( 'Verified', 'hypeit' ); ?></span><strong><?php echo (int) $lib['verified_pct']; ?>%</strong></div>
						<div class="cpi-track"><i style="width:<?php echo (int) $lib['verified_pct']; ?>%"></i></div>
					</div>
					<div class="cpi-meter">
						<div class="cpi-meter-top"><span><?php esc_html_e( 'Complete profiles', 'hypeit' ); ?></span><strong><?php echo (int) $lib['complete_pct']; ?>%</strong></div>
						<div class="cpi-track is-blue"><i style="width:<?php echo (int) $lib['complete_pct']; ?>%"></i></div>
						<small class="cpw-muted"><?php echo esc_html( sprintf( /* translators: %d: percent. */ __( 'Average profile is %d%% complete', 'hypeit' ), $lib['avg_complete'] ) ); ?></small>
					</div>
					<div class="cpi-minis">
						<div><b><?php echo esc_html( $compact( $lib['reach'] ) ); ?></b><span><?php esc_html_e( 'Total followers', 'hypeit' ); ?></span></div>
						<div><b class="cpi-up">↑ <?php echo (int) $lib['growing']; ?></b><span><?php esc_html_e( 'Growing', 'hypeit' ); ?></span></div>
						<div><b class="cpi-down">↓ <?php echo (int) $lib['shrinking']; ?></b><span><?php esc_html_e( 'Shrinking', 'hypeit' ); ?></span></div>
					</div>
					<?php if ( $lib['tiers'] ) : ?>
						<div class="cpi-tiers">
							<?php foreach ( $lib['tiers'] as $tl => $tn ) : ?>
								<div class="cpi-tier"><span><?php echo esc_html( $tl ); ?></span><i style="width:<?php echo (int) round( $tn / $tmax * 100 ); ?>%"></i><b><?php echo (int) $tn; ?></b></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<p class="cpi-foot">
						<?php if ( $lib['inactive'] ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'cp_blocked_filter', 'inactive', $blist ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: count. */ _n( '%d deactivated blogger', '%d deactivated bloggers', $lib['inactive'], 'hypeit' ), $lib['inactive'] ) ); ?></a> ·
						<?php endif; ?>
						<small class="cpw-muted"><?php esc_html_e( 'Arrows compare followers with the previous Instagram sync.', 'hypeit' ); ?></small>
					</p>
				</div>
			</div>

			<h2 class="cpi-h2"><?php esc_html_e( 'What clients pick', 'hypeit' ); ?> <small><?php esc_html_e( 'Bar = share of responses where the client said yes', 'hypeit' ); ?></small></h2>
			<div class="cpi-grid cpi-grid-4">
				<?php
				$dl = array(
					'category' => __( 'By category', 'hypeit' ),
					'tier'     => __( 'By follower size', 'hypeit' ),
					'gender'   => __( 'By gender', 'hypeit' ),
					'city'     => __( 'By city', 'hypeit' ),
				);
				foreach ( $dl as $dim => $title ) :
					?>
					<div class="cpw-card">
						<h3><?php echo esc_html( $title ); ?></h3>
						<?php if ( empty( $r['dims'][ $dim ] ) ) : ?>
							<p class="cpw-muted"><?php esc_html_e( 'No data yet for this period.', 'hypeit' ); ?></p>
						<?php else : ?>
							<?php foreach ( $r['dims'][ $dim ] as $row ) : ?>
								<div class="cpi-bar">
									<div class="cpi-bar-top"><span><?php echo esc_html( $row['label'] ); ?></span><span><strong><?php echo esc_html( $pct( $row['acceptance'] ) ); ?></strong> <small><?php echo esc_html( sprintf( /* translators: %d: count. */ _n( 'of %d', 'of %d', $row['invited'], 'hypeit' ), $row['invited'] ) ); ?></small></span></div>
									<div class="cpi-track"><i style="width:<?php echo (int) ( $row['acceptance'] ?? 0 ); ?>%"></i></div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<h2 class="cpi-h2"><?php esc_html_e( 'Bloggers', 'hypeit' ); ?></h2>
			<div class="cpi-grid cpi-grid-boards">
				<?php
				$bl = array(
					'selected'   => array( __( 'Most selected', 'hypeit' ), __( 'confirmed', 'hypeit' ) ),
					'acceptance' => array( __( 'Best acceptance', 'hypeit' ), __( '3+ responses', 'hypeit' ) ),
					'invited'    => array( __( 'Most proposed', 'hypeit' ), __( 'campaigns', 'hypeit' ) ),
					'declined'   => array( __( 'Most declined', 'hypeit' ), __( 'declined', 'hypeit' ) ),
				);
				if ( ! empty( $r['boards']['reliable'] ) ) {
					$bl['reliable'] = array( __( 'Most reliable (attendance)', 'hypeit' ), __( 'ATRIUM check-ins', 'hypeit' ) );
				}
				foreach ( $bl as $key => $lab ) :
					?>
					<div class="cpw-card">
						<h3><?php echo esc_html( $lab[0] ); ?> <small class="cpw-muted"><?php echo esc_html( $lab[1] ); ?></small></h3>
						<?php if ( empty( $r['boards'][ $key ] ) ) : ?>
							<p class="cpw-muted"><?php esc_html_e( 'No data yet for this period.', 'hypeit' ); ?></p>
						<?php else : ?>
							<ol class="cpi-board">
								<?php foreach ( $r['boards'][ $key ] as $p ) : ?>
									<li><?php echo $who( $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="cpi-val"><?php echo esc_html( $p['value'] ); ?><?php echo $p['extra'] ? ' <small>' . esc_html( $p['extra'] ) . '</small>' : ''; ?></span></li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="cpw-card cpi-attn">
					<h3><?php esc_html_e( 'Needs attention', 'hypeit' ); ?></h3>
					<ul>
						<li><b><?php echo (int) $r['attention']['never_selected']; ?></b> <span><?php esc_html_e( 'never selected by a client (in the library 30+ days)', 'hypeit' ); ?></span></li>
						<li><b><?php echo (int) $r['attention']['often_declined']; ?></b> <span><?php esc_html_e( 'declined 3+ times and never accepted', 'hypeit' ); ?></span></li>
						<li><b><?php echo (int) $r['attention']['unverified']; ?></b> <span><?php esc_html_e( 'not verified yet', 'hypeit' ); ?> — <a href="<?php echo esc_url( add_query_arg( 'cp_verified_filter', 'no', $blist ) ); ?>"><?php esc_html_e( 'view', 'hypeit' ); ?></a></span></li>
						<li><b><?php echo (int) $r['attention']['personal']; ?></b> <span><?php esc_html_e( 'personal Instagram accounts (numbers can’t be synced)', 'hypeit' ); ?> — <a href="<?php echo esc_url( CP_IGSync::personal_list_url() ); ?>"><?php esc_html_e( 'view', 'hypeit' ); ?></a></span></li>
						<li><b><?php echo (int) max( 0, $lib['total'] - $lib['complete'] ); ?></b> <span><?php esc_html_e( 'profiles not complete yet', 'hypeit' ); ?> — <a href="<?php echo esc_url( add_query_arg( 'cp_sort', 'cp_complete:asc', $blist ) ); ?>"><?php esc_html_e( 'view', 'hypeit' ); ?></a></span></li>
					</ul>
				</div>
			</div>

			<h2 class="cpi-h2"><?php esc_html_e( 'Campaigns', 'hypeit' ); ?> <small><?php echo esc_html( sprintf( /* translators: %d: count. */ _n( '%d in this period', '%d in this period', count( $r['campaigns'] ), 'hypeit' ), count( $r['campaigns'] ) ) ); ?></small></h2>
			<div class="cpw-card cpi-camps">
				<?php if ( empty( $r['campaigns'] ) ) : ?>
					<p class="cpw-empty"><?php esc_html_e( 'No published campaigns in this period.', 'hypeit' ); ?></p>
				<?php else : ?>
					<table class="cpw-table">
						<thead><tr>
							<th><?php esc_html_e( 'Campaign', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'Date', 'hypeit' ); ?></th>
							<th class="num"><?php esc_html_e( 'Proposed', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'Responses', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'Acceptance', 'hypeit' ); ?></th>
							<th class="num"><?php esc_html_e( 'People', 'hypeit' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $r['campaigns'] as $c ) : ?>
								<?php $wait = max( 0, $c['invited'] - $c['confirmed'] - $c['declined'] ); ?>
								<tr>
									<td data-label=""><a href="<?php echo esc_url( get_edit_post_link( $c['id'] ) ); ?>"><strong><?php echo esc_html( $c['title'] ); ?></strong></a><?php echo $c['closed'] ? ' <span class="cpw-tag">' . esc_html__( 'Closed', 'hypeit' ) . '</span>' : ''; ?></td>
									<td data-label="<?php esc_attr_e( 'Date', 'hypeit' ); ?>" class="cpw-muted"><?php echo esc_html( $c['date'] ); ?></td>
									<td data-label="<?php esc_attr_e( 'Proposed', 'hypeit' ); ?>" class="num"><?php echo (int) $c['invited']; ?></td>
									<td data-label="<?php esc_attr_e( 'Responses', 'hypeit' ); ?>">
										<div class="cpi-split" title="<?php echo esc_attr( sprintf( /* translators: 1: confirmed, 2: declined, 3: waiting. */ __( '%1$d confirmed · %2$d declined · %3$d waiting', 'hypeit' ), $c['confirmed'], $c['declined'], $wait ) ); ?>">
											<i class="c" style="flex:<?php echo (int) $c['confirmed']; ?>"></i><i class="d" style="flex:<?php echo (int) $c['declined']; ?>"></i><i class="p" style="flex:<?php echo (int) $wait; ?>"></i>
										</div>
										<small class="cpw-muted"><?php echo esc_html( sprintf( /* translators: %d: percent. */ __( '%d%% answered', 'hypeit' ), $c['response'] ) ); ?></small>
									</td>
									<td data-label="<?php esc_attr_e( 'Acceptance', 'hypeit' ); ?>"><strong><?php echo esc_html( $pct( $c['acceptance'] ) ); ?></strong> <span class="cpw-muted"><?php echo (int) $c['confirmed']; ?>/<?php echo (int) ( $c['confirmed'] + $c['declined'] ); ?></span></td>
									<td data-label="<?php esc_attr_e( 'People', 'hypeit' ); ?>" class="num"><strong><?php echo (int) $c['people']; ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
