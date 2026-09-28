<?php
/**
 * The Bloggers list page — redesigned.
 *
 * Keeps WordPress's list engine (pagination, bulk + row actions, search) but
 * replaces everything you see: a summary strip, one toolbar with all filters,
 * a "Missing info" picker (any / all of the chosen items), sort menu, active
 * filter chips, and new columns (Blogger, Followers, City, Categories, Contact,
 * Profile completeness, Added).
 *
 * Profile completeness is stored per blogger (_cp_complete = %, _cp_missing =
 * ",photo,email,") and refreshed automatically whenever a relevant field changes.
 * An admin can mark a profile complete by hand (_cp_complete_manual) for
 * bloggers they know personally: it then counts as 100% as soon as the basic
 * profile (Instagram username) and a follower count are there.
 * _cp_completed_at stamps when a profile became complete (0 = not complete) and
 * _cp_blogger_updated when the blogger last updated their own profile (form).
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Bloggers_UI {

	/** @var array Bloggers whose completeness needs recomputing at shutdown. */
	private static $dirty = array();

	/** Meta keys that affect completeness. */
	const WATCH = array( '_cp_photo', '_cp_first', '_cp_last', '_cp_ig', '_cp_followers', '_cp_gender', '_cp_city', '_cp_collab', '_cp_phone', '_cp_whatsapp', '_cp_email', '_cp_birthday', self::MANUAL );

	/** Meta flag: profile marked complete by an admin. */
	const MANUAL = '_cp_complete_manual';

	/**
	 * Hooks.
	 */
	public static function init() {
		// Keep completeness fresh from every path (admin, app, form, sync, imports).
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $h ) {
			add_action( $h, array( __CLASS__, 'on_meta' ), 10, 3 );
		}
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms' ), 10, 4 );
		add_action( 'save_post_' . CP_Library::CPT, array( __CLASS__, 'mark' ), 99 );

		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'manage_' . CP_Library::CPT . '_posts_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_' . CP_Library::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . CP_Library::CPT . '_sortable_columns', array( __CLASS__, 'sortable' ), 20 );
		add_filter( 'list_table_primary_column', array( __CLASS__, 'primary' ), 10, 2 );
		add_filter( 'views_edit-' . CP_Library::CPT, array( __CLASS__, 'header' ), 99 );
		add_filter( 'disable_months_dropdown', array( __CLASS__, 'no_months' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Completeness                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Profile items that can be missing.
	 *
	 * @return array key => label
	 */
	public static function items() {
		return array(
			'photo'      => __( 'Photo', 'hypeit' ),
			'instagram'  => __( 'Instagram', 'hypeit' ),
			'name'       => __( 'Name', 'hypeit' ),
			'followers'  => __( 'Followers', 'hypeit' ),
			'gender'     => __( 'Gender', 'hypeit' ),
			'city'       => __( 'City', 'hypeit' ),
			'categories' => __( 'Categories', 'hypeit' ),
			'collab'     => __( 'Open for', 'hypeit' ),
			'phone'      => __( 'Phone', 'hypeit' ),
			'whatsapp'   => __( 'WhatsApp', 'hypeit' ),
			'email'      => __( 'Email', 'hypeit' ),
			'birthday'   => __( 'Birthday', 'hypeit' ),
		);
	}

	/**
	 * What's missing + completeness % (phone OR WhatsApp counts as one contact item).
	 *
	 * @param int $id Blogger ID.
	 * @return array{missing:array,pct:int}
	 */
	public static function compute( $id ) {
		$m = static function ( $k ) use ( $id ) {
			return trim( (string) get_post_meta( $id, $k, true ) );
		};
		$cats = wp_get_object_terms( $id, CP_Library::TAX_TAG, array( 'fields' => 'ids' ) );
		$has  = array(
			'photo'      => '' !== $m( '_cp_photo' ),
			'instagram'  => '' !== $m( '_cp_ig' ),
			'name'       => '' !== $m( '_cp_first' ),
			'followers'  => (int) $m( '_cp_followers' ) > 0,
			'gender'     => '' !== $m( '_cp_gender' ),
			'city'       => '' !== $m( '_cp_city' ),
			'categories' => ! is_wp_error( $cats ) && ! empty( $cats ),
			'collab'     => '' !== trim( $m( '_cp_collab' ), ',' ),
			'phone'      => '' !== $m( '_cp_phone' ),
			'whatsapp'   => '' !== $m( '_cp_whatsapp' ),
			'email'      => '' !== $m( '_cp_email' ),
			'birthday'   => '' !== $m( '_cp_birthday' ),
		);
		$missing = array_keys( array_filter( $has, static function ( $v ) { return ! $v; } ) );

		// Marked complete by an admin: the basic profile + follower count are enough.
		if ( self::is_manual( $id ) && $has['instagram'] && $has['followers'] ) {
			return array( 'missing' => array(), 'pct' => 100, 'manual' => true );
		}

		// Score over the 10 profile essentials (birthday is optional; phone/WhatsApp = one "contact").
		$core = array(
			$has['photo'], $has['instagram'], $has['name'], $has['followers'], $has['gender'],
			$has['city'], $has['categories'], $has['collab'], $has['phone'] || $has['whatsapp'], $has['email'],
		);
		$pct = (int) round( count( array_filter( $core ) ) / count( $core ) * 100 );
		return array( 'missing' => $missing, 'pct' => $pct, 'manual' => false );
	}

	/**
	 * Stamp "profile updated now" (admin editor, app, form — not Instagram syncs).
	 *
	 * @param int $id Blogger ID.
	 */
	public static function touch( $id ) {
		update_post_meta( (int) $id, '_cp_updated_at', time() );
	}

	/**
	 * Has an admin marked this profile complete?
	 *
	 * @param int $id Blogger ID.
	 * @return bool
	 */
	public static function is_manual( $id ) {
		return '1' === (string) get_post_meta( $id, self::MANUAL, true );
	}

	/**
	 * Mark / unmark a profile as complete (recomputed right away).
	 *
	 * @param int  $id Blogger ID.
	 * @param bool $on Mark complete.
	 * @return array Completeness after the change.
	 */
	public static function set_manual( $id, $on ) {
		if ( $on ) {
			update_post_meta( $id, self::MANUAL, '1' );
		} else {
			delete_post_meta( $id, self::MANUAL );
		}
		self::touch( $id );
		return self::refresh( $id );
	}

	/**
	 * Is the profile complete (every detail filled in, or marked complete)?
	 *
	 * @param int $id Blogger ID.
	 * @return bool
	 */
	public static function is_complete( $id ) {
		return (int) get_post_meta( $id, '_cp_complete', true ) >= 100;
	}

	/**
	 * Complete on its own merit (every detail filled in, no manual mark needed)?
	 *
	 * @param int $id Blogger ID.
	 * @return bool
	 */
	public static function is_auto_complete( $id ) {
		return ! self::is_manual( $id ) && self::is_complete( $id );
	}

	/**
	 * Can the manual "complete" mark take effect? (Needs a username + followers.)
	 *
	 * @param int $id Blogger ID.
	 * @return bool
	 */
	public static function manual_ready( $id ) {
		return '' !== trim( (string) get_post_meta( $id, '_cp_ig', true ) ) && (int) get_post_meta( $id, '_cp_followers', true ) > 0;
	}

	/* ------------------------------------------------------------------ */
	/* Follower tracking                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Follower change measured at the last Instagram sync (0 = no change).
	 *
	 * @param int $id Blogger ID.
	 * @return int
	 */
	public static function follower_delta( $id ) {
		return (int) get_post_meta( $id, '_cp_followers_delta', true );
	}

	/**
	 * Green ↑ / red ↓ next to a follower count ('' when unchanged).
	 *
	 * @param int $id Blogger ID.
	 * @return string HTML.
	 */
	public static function trend_html( $id ) {
		$d = self::follower_delta( $id );
		if ( ! $d ) {
			return '';
		}
		$prev = (int) get_post_meta( $id, '_cp_followers_prev', true );
		$now  = (int) get_post_meta( $id, '_cp_followers', true );
		$tip  = sprintf(
			/* translators: 1: signed change, 2: previous count, 3: current count. */
			__( '%1$s since the last sync (%2$s → %3$s)', 'hypeit' ),
			( $d > 0 ? '+' : '−' ) . number_format_i18n( abs( $d ) ),
			number_format_i18n( $prev ),
			number_format_i18n( $now )
		);
		return '<span class="cp-trend ' . ( $d > 0 ? 'is-up' : 'is-down' ) . '" title="' . esc_attr( $tip ) . '" aria-label="' . esc_attr( $tip ) . '">' . ( $d > 0 ? '▲' : '▼' ) . '</span>';
	}

	/**
	 * Recompute and store for one blogger.
	 *
	 * @param int $id Blogger ID.
	 * @return array
	 */
	public static function refresh( $id ) {
		$c = self::compute( $id );
		update_post_meta( $id, '_cp_complete', $c['pct'] );
		update_post_meta( $id, '_cp_missing', $c['missing'] ? ',' . implode( ',', $c['missing'] ) . ',' : '' );

		// When the profile became complete (for "Latest completed"); 0 while incomplete.
		$raw  = get_post_meta( $id, '_cp_completed_at', true );
		$done = (int) $raw;
		if ( $c['pct'] >= 100 && ! $done ) {
			// First time we look (upgrade): best guess is the profile's last change.
			$when = '' === $raw ? (int) get_post_modified_time( 'U', true, $id ) : 0;
			update_post_meta( $id, '_cp_completed_at', $when ? $when : time() );
		} elseif ( $c['pct'] < 100 && ( $done || '' === $raw ) ) {
			update_post_meta( $id, '_cp_completed_at', 0 );
		}
		// "Recently updated": seed from the post's last change the first time.
		if ( '' === get_post_meta( $id, '_cp_updated_at', true ) ) {
			update_post_meta( $id, '_cp_updated_at', (int) get_post_modified_time( 'U', true, $id ) );
		}
		// Every blogger carries the key so "Last updated by blogger" can sort everyone.
		if ( '' === get_post_meta( $id, '_cp_blogger_updated', true ) ) {
			update_post_meta( $id, '_cp_blogger_updated', 0 );
		}
		return $c;
	}

	/**
	 * Recompute every blogger (upgrade / repair).
	 */
	public static function refresh_all() {
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			update_object_term_cache( $ids, CP_Library::CPT );
		}
		foreach ( (array) $ids as $id ) {
			self::refresh( (int) $id );
		}
	}

	/**
	 * Mark a blogger for recompute at the end of the request.
	 *
	 * @param int $id Post ID.
	 */
	public static function mark( $id ) {
		$id = (int) $id;
		if ( ! $id || CP_Library::CPT !== get_post_type( $id ) ) {
			return;
		}
		if ( ! self::$dirty ) {
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 1 );
		}
		self::$dirty[ $id ] = true;
	}

	/**
	 * Meta changed.
	 *
	 * @param int    $meta_id Meta ID(s).
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 */
	public static function on_meta( $meta_id, $post_id, $key ) {
		if ( in_array( $key, self::WATCH, true ) ) {
			self::mark( $post_id );
		}
	}

	/**
	 * Categories changed.
	 *
	 * @param int    $object_id Post ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 */
	public static function on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( CP_Library::TAX_TAG === $taxonomy ) {
			self::mark( $object_id );
		}
	}

	/**
	 * Recompute queued bloggers.
	 */
	public static function flush() {
		foreach ( array_keys( self::$dirty ) as $id ) {
			self::refresh( $id );
		}
		self::$dirty = array();
	}

	/* ------------------------------------------------------------------ */
	/* Columns                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		return array(
			'cb'           => isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox" />',
			'cp_blogger'   => __( 'Blogger', 'hypeit' ),
			'cp_followers' => __( 'Followers', 'hypeit' ),
			'cp_city'      => __( 'City', 'hypeit' ),
			'cp_cats'      => __( 'Categories', 'hypeit' ),
			'cp_contact'   => __( 'Contact', 'hypeit' ),
			'cp_complete'  => __( 'Profile', 'hypeit' ),
			'cp_added'     => __( 'Added', 'hypeit' ),
		);
	}

	/**
	 * Primary column (row actions live here).
	 *
	 * @param string $col    Default.
	 * @param string $screen Screen ID.
	 * @return string
	 */
	public static function primary( $col, $screen ) {
		return 'edit-' . CP_Library::CPT === $screen ? 'cp_blogger' : $col;
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function sortable( $cols ) {
		return array(
			'cp_blogger'   => 'title',
			'cp_followers' => array( 'cp_followers', true ),
			'cp_city'      => 'cp_city',
			'cp_complete'  => 'cp_complete',
			'cp_added'     => array( 'date', true ),
		);
	}

	/**
	 * Small inline icons.
	 *
	 * @param string $name Icon.
	 * @return string
	 */
	private static function icon( $name ) {
		$p = array(
			'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'wa'    => '<path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
			'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		);
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[ $name ] . '</svg>';
	}

	/**
	 * Column content.
	 *
	 * @param string $col Column.
	 * @param int    $id  Post ID.
	 */
	public static function column( $col, $id ) {
		$get = static function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, $k, true );
		};
		switch ( $col ) {
			case 'cp_blogger':
				$name   = trim( $get( '_cp_first' ) . ' ' . $get( '_cp_last' ) );
				$handle = CP_Library::handle( $id );
				$badges = '';
				if ( CP_Verify::is_verified( $id ) ) {
					$badges .= '<span class="cpl-b is-ok" title="' . esc_attr__( 'Verified', 'hypeit' ) . '">✓</span>';
				}
				if ( 'personal' === $get( '_cp_ig_status' ) ) {
					$badges .= '<span class="cpl-b is-warn" title="' . esc_attr__( 'Personal / private account, or wrong username — numbers can’t be synced.', 'hypeit' ) . '">' . esc_html__( 'Personal', 'hypeit' ) . '</span>';
				}
				if ( '1' === $get( '_cp_blocked' ) ) {
					$badges .= '<span class="cpl-b is-bad">' . esc_html__( 'Blocked', 'hypeit' ) . '</span>';
				}
				if ( CP_Library::is_inactive( $id ) ) {
					$badges .= '<span class="cpl-b is-off" title="' . esc_attr__( 'Hidden from campaigns, selections and lists. Past campaign records are kept.', 'hypeit' ) . '">' . esc_html__( 'Deactivated', 'hypeit' ) . '</span>';
				}
				if ( 'publish' !== get_post_status( $id ) ) {
					$badges .= '<span class="cpl-b">' . esc_html( get_post_status_object( get_post_status( $id ) )->label ) . '</span>';
				}
				echo '<div class="cpl-who">' . CP_Photo::avatar_html( $id, 44, $name, $handle ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					. '<div class="cpl-id"><a class="cpl-name row-title" href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $name ? $name : ( $handle ? '@' . $handle : __( '(no name)', 'hypeit' ) ) ) . '</a>' . $badges // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					. ( $handle ? '<a class="cpl-handle" href="' . esc_url( CP_Library::profile_url( $handle ) ) . '" target="_blank" rel="noopener">@' . esc_html( $handle ) . '</a>' : '<span class="cpl-muted">' . esc_html__( 'No Instagram', 'hypeit' ) . '</span>' )
					. '</div></div>';
				break;

			case 'cp_followers':
				$f   = (int) $get( '_cp_followers' );
				$eng = $get( '_cp_engagement' );
				echo $f ? '<strong class="cpl-num" title="' . esc_attr( number_format_i18n( $f ) ) . '">' . esc_html( self::compact( $f ) ) . '</strong>' . self::trend_html( $id ) : '<span class="cpl-muted">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( '' !== $eng ) {
					echo '<span class="cpl-sub">' . esc_html( sprintf( /* translators: %s: rate. */ __( '%s%% eng.', 'hypeit' ), number_format_i18n( (float) $eng, 1 ) ) ) . '</span>';
				}
				break;

			case 'cp_city':
				$city = $get( '_cp_city' );
				echo $city ? esc_html( $city ) : '<span class="cpl-muted">&mdash;</span>';
				break;

			case 'cp_cats':
				$cats = wp_get_post_terms( $id, CP_Library::TAX_TAG, array( 'fields' => 'names' ) );
				if ( is_wp_error( $cats ) || ! $cats ) {
					echo '<span class="cpl-muted">&mdash;</span>';
					break;
				}
				$show = array_slice( $cats, 0, 2 );
				foreach ( $show as $c ) {
					echo '<span class="cpl-tag">' . esc_html( $c ) . '</span>';
				}
				if ( count( $cats ) > 2 ) {
					echo '<span class="cpl-tag is-more" title="' . esc_attr( implode( ', ', array_slice( $cats, 2 ) ) ) . '">+' . (int) ( count( $cats ) - 2 ) . '</span>';
				}
				break;

			case 'cp_contact':
				$phone = preg_replace( '/[^\d+]/', '', $get( '_cp_phone' ) );
				$wa    = $get( '_cp_whatsapp' );
				$mail  = $get( '_cp_email' );
				$wa_l  = $wa ? CP_Atrium::wa_link( $wa, '' ) : '';
				$wa_l  = $wa_l ? strtok( $wa_l, '?' ) : '';
				echo '<div class="cpl-contact">';
				echo $phone ? '<a class="cpl-ci is-on" href="tel:' . esc_attr( $phone ) . '" title="' . esc_attr( $get( '_cp_phone' ) ) . '">' . self::icon( 'phone' ) . '</a>' : '<span class="cpl-ci" title="' . esc_attr__( 'No phone', 'hypeit' ) . '">' . self::icon( 'phone' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $wa_l ? '<a class="cpl-ci is-on" href="' . esc_url( $wa_l ) . '" target="_blank" rel="noopener" title="' . esc_attr( $wa ) . '">' . self::icon( 'wa' ) . '</a>' : '<span class="cpl-ci" title="' . esc_attr__( 'No WhatsApp', 'hypeit' ) . '">' . self::icon( 'wa' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo is_email( $mail ) ? '<a class="cpl-ci is-on" href="mailto:' . esc_attr( $mail ) . '" title="' . esc_attr( $mail ) . '">' . self::icon( 'mail' ) . '</a>' : '<span class="cpl-ci" title="' . esc_attr__( 'No email', 'hypeit' ) . '">' . self::icon( 'mail' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</div>';
				break;

			case 'cp_complete':
				$pct = $get( '_cp_complete' );
				$mis = $get( '_cp_missing' );
				if ( '' === $pct ) {
					$c   = self::refresh( $id );
					$pct = $c['pct'];
					$mis = ',' . implode( ',', $c['missing'] ) . ',';
				}
				$pct   = (int) $pct;
				$items = self::items();
				$names = array();
				foreach ( array_filter( explode( ',', $mis ) ) as $k ) {
					if ( isset( $items[ $k ] ) ) {
						$names[] = $items[ $k ];
					}
				}
				$tone   = $pct >= 90 ? 'is-good' : ( $pct >= 60 ? 'is-mid' : 'is-low' );
				$manual = self::is_manual( $id );
				$title  = $names ? sprintf( /* translators: %s: items. */ __( 'Missing: %s', 'hypeit' ), implode( ', ', $names ) ) : __( 'Complete', 'hypeit' );
				if ( $manual && $pct >= 100 ) {
					$title = __( 'Marked complete by an admin', 'hypeit' );
				}
				echo '<div class="cpl-pct ' . esc_attr( $tone ) . '" title="' . esc_attr( $title ) . '">'
					. '<span class="cpl-pct-bar"><i style="width:' . (int) $pct . '%"></i></span><span class="cpl-pct-n">' . (int) $pct . '%</span></div>';
				// Count only what lowers the score (10 essentials; birthday is optional).
				$gaps = (int) round( ( 100 - $pct ) / 10 );
				if ( $manual && $pct >= 100 ) {
					echo '<span class="cpl-sub cpl-manual">✓ ' . esc_html__( 'Marked complete', 'hypeit' ) . '</span>';
				} elseif ( $pct >= 100 ) {
					// Every detail filled in: complete automatically, no need to mark it.
					echo '<span class="cpl-sub cpl-manual" title="' . esc_attr__( 'Every profile detail is filled in', 'hypeit' ) . '">✓ ' . esc_html__( 'Complete', 'hypeit' ) . '</span>';
				} elseif ( $manual ) {
					echo '<span class="cpl-sub" title="' . esc_attr__( 'Add the Instagram username and follower count to complete it.', 'hypeit' ) . '">' . esc_html__( 'Marked — needs followers', 'hypeit' ) . '</span>';
				} elseif ( $gaps > 0 ) {
					echo '<span class="cpl-sub">' . esc_html( sprintf( /* translators: %d: count. */ _n( '%d missing', '%d missing', $gaps, 'hypeit' ), $gaps ) ) . '</span>';
				}
				break;

			case 'cp_added':
				echo '<span class="cpl-date">' . esc_html( get_the_date( 'M j, Y', $id ) ) . '</span>';
				if ( 'onboarding' === $get( '_cp_source' ) ) {
					echo '<span class="cpl-sub">' . esc_html__( 'via form', 'hypeit' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * 12400 → 12.4K.
	 *
	 * @param int $n Number.
	 * @return string
	 */
	private static function compact( $n ) {
		if ( $n >= 1000000 ) {
			return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M';
		}
		if ( $n >= 1000 ) {
			return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K';
		}
		return number_format_i18n( $n );
	}

	/* ------------------------------------------------------------------ */
	/* Query                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Hide the month dropdown on this screen.
	 *
	 * @param bool   $off  Disabled.
	 * @param string $type Post type.
	 * @return bool
	 */
	public static function no_months( $off, $type ) {
		return CP_Library::CPT === $type ? true : $off;
	}

	/**
	 * Sort menu (value = orderby:order).
	 *
	 * @return array
	 */
	public static function sorts() {
		return array(
			''                  => __( 'Newest first', 'hypeit' ),
			'date:asc'          => __( 'Oldest first', 'hypeit' ),
			'cp_updated:desc'   => __( 'Recently updated', 'hypeit' ),
			'title:asc'         => __( 'Name A–Z', 'hypeit' ),
			'title:desc'        => __( 'Name Z–A', 'hypeit' ),
			'cp_followers:desc' => __( 'Most followers', 'hypeit' ),
			'cp_followers:asc'  => __( 'Fewest followers', 'hypeit' ),
			'cp_verified:desc'  => __( 'Verified first', 'hypeit' ),
			'cp_completed:desc' => __( 'Latest completed', 'hypeit' ),
			'cp_complete:desc'  => __( 'Most complete', 'hypeit' ),
			'cp_complete:asc'   => __( 'Least complete', 'hypeit' ),
			'cp_bupdated:desc'  => __( 'Last updated by blogger', 'hypeit' ),
		);
	}

	/**
	 * Current "missing" selection.
	 *
	 * @return array
	 */
	private static function sel_missing() {
		$raw = isset( $_GET['cp_missing'] ) ? (array) wp_unslash( $_GET['cp_missing'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
		return array_values( array_intersect( array_map( 'sanitize_key', $raw ), array_keys( self::items() ) ) );
	}

	/**
	 * Missing-info filter, completeness sort and sort menu (runs after the
	 * existing filters in CP_Blogger_CPT, which handle the other params).
	 *
	 * @param WP_Query $q Query.
	 */
	public static function query( $q ) {
		if ( ! $q->is_main_query() || CP_Library::CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		$meta = (array) $q->get( 'meta_query' );

		// Deactivated bloggers only show when asked for (Status: Deactivated) or in Trash.
		$state = isset( $_GET['cp_blocked_filter'] ) ? sanitize_key( wp_unslash( $_GET['cp_blocked_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'inactive' === $state ) {
			$meta[] = array( 'key' => CP_Library::META_INACTIVE, 'value' => '1' );
		} elseif ( 'trash' !== $q->get( 'post_status' ) ) {
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => CP_Library::META_INACTIVE, 'compare' => 'NOT EXISTS' ),
				array( 'key' => CP_Library::META_INACTIVE, 'value' => '1', 'compare' => '!=' ),
			);
		}

		$miss = self::sel_missing();
		if ( $miss ) {
			$mode   = isset( $_GET['cp_mmode'] ) && 'all' === $_GET['cp_mmode'] ? 'AND' : 'OR'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$clause = array( 'relation' => $mode );
			foreach ( $miss as $k ) {
				$clause[] = array( 'key' => '_cp_missing', 'value' => ',' . $k . ',', 'compare' => 'LIKE' );
			}
			$meta[] = $clause;
		}

		// Sort menu (one select → orderby + order).
		if ( ! empty( $_GET['cp_sort'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			list( $ob, $dir ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_GET['cp_sort'] ) ) ), 2, 'desc' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q->set( 'orderby', sanitize_key( $ob ) );
			$q->set( 'order', 'asc' === $dir ? 'ASC' : 'DESC' );
		}
		$num = array(
			'cp_complete'  => '_cp_complete',
			'cp_completed' => '_cp_completed_at',
			'cp_bupdated'  => '_cp_blogger_updated',
			'cp_updated'   => '_cp_updated_at',
		);
		if ( isset( $num[ $q->get( 'orderby' ) ] ) ) {
			// Ties (e.g. never completed / never updated) fall back to newest first.
			$dir = 'ASC' === strtoupper( (string) $q->get( 'order' ) ) ? 'ASC' : 'DESC';
			$q->set( 'meta_key', $num[ $q->get( 'orderby' ) ] );
			$q->set( 'meta_type', 'NUMERIC' );
			$q->set( 'orderby', array( 'meta_value_num' => $dir, 'date' => 'DESC', 'ID' => 'DESC' ) );
		} elseif ( 'cp_followers' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_cp_followers' );
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( 'cp_verified' === $q->get( 'orderby' ) ) {
			$meta['cp_ver'] = array(
				'relation' => 'OR',
				'cp_ver_y' => array( 'key' => '_cp_verified', 'compare' => 'EXISTS' ),
				'cp_ver_n' => array( 'key' => '_cp_verified', 'compare' => 'NOT EXISTS' ),
			);
			$q->set( 'orderby', array( 'cp_ver_y' => 'DESC', 'title' => 'ASC' ) );
		}

		$meta = array_filter( $meta );
		if ( $meta ) {
			if ( ! isset( $meta['relation'] ) ) {
				$meta['relation'] = 'AND';
			}
			$q->set( 'meta_query', $meta );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Header: summary strip + toolbar + chips                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Counts for the summary strip.
	 *
	 * @return array
	 */
	private static function counts() {
		global $wpdb;
		$cpt = CP_Library::CPT;
		$base = "FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish'";
		$has  = static function ( $key, $val = null, $like = false ) use ( $wpdb ) {
			$cmp = null === $val ? "pm.meta_value <> ''" : ( $like ? $wpdb->prepare( 'pm.meta_value LIKE %s', $val ) : $wpdb->prepare( 'pm.meta_value = %s', $val ) );
			return $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s AND ", $key ) . $cmp . ')';
		};
		// Deactivated bloggers are counted on their own tile only.
		$off = $has( CP_Library::META_INACTIVE, '1' );
		$q   = static function ( $where = '' ) use ( $wpdb, $base, $cpt, $off ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base}", $cpt ) . ' AND NOT ' . $off . ( $where ? ' AND ' . $where : '' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		};
		$total    = $q();
		$verified = $q( $has( '_cp_verified', '1' ) );
		return array(
			'total'      => $total,
			'verified'   => $verified,
			'unverified' => $total - $verified,
			'personal'   => $q( $has( '_cp_ig_status', 'personal' ) ),
			'nocontact'  => $q( $has( '_cp_missing', '%,phone,%', true ) . ' AND ' . $has( '_cp_missing', '%,whatsapp,%', true ) . ' AND ' . $has( '_cp_missing', '%,email,%', true ) ),
			'blocked'    => $q( $has( '_cp_blocked', '1' ) ),
			'inactive'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base}", $cpt ) . ' AND ' . $off ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		);
	}

	/**
	 * Build a list URL with params.
	 *
	 * @param array $args Args.
	 * @return string
	 */
	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'post_type' => CP_Library::CPT ), $args ), admin_url( 'edit.php' ) );
	}

	/**
	 * Replace the views row with the summary strip + toolbar (printed above the table form).
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function header( $views ) {
		$g   = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		};
		$c        = self::counts();
		$miss     = self::sel_missing();
		$mode     = 'all' === $g( 'cp_mmode' ) ? 'all' : 'any';
		$items    = self::items();
		$sort     = $g( 'cp_sort' );
		$trash    = 'trash' === $g( 'post_status' );

		$tiles = array(
			array( __( 'Total', 'hypeit' ), $c['total'], array(), '' ),
			array( __( 'Verified', 'hypeit' ), $c['verified'], array( 'cp_verified_filter' => 'yes' ), 'is-ok' ),
			array( __( 'Not verified', 'hypeit' ), $c['unverified'], array( 'cp_verified_filter' => 'no' ), '' ),
			array( __( 'Personal accounts', 'hypeit' ), $c['personal'], array( 'cp_igstatus' => 'personal' ), 'is-warn' ),
			array( __( 'No contact info', 'hypeit' ), $c['nocontact'], array( 'cp_missing' => array( 'phone', 'whatsapp', 'email' ), 'cp_mmode' => 'all' ), 'is-warn' ),
			array( __( 'Blocked', 'hypeit' ), $c['blocked'], array( 'cp_blocked_filter' => 'blocked' ), 'is-bad' ),
			array( __( 'Deactivated', 'hypeit' ), $c['inactive'], array( 'cp_blocked_filter' => 'inactive' ), 'is-off' ),
		);
		echo '<div class="cpl-stats">';
		foreach ( $tiles as $t ) {
			echo '<a class="cpl-stat ' . esc_attr( $t[3] ) . '" href="' . esc_url( self::url( $t[2] ) ) . '"><b>' . esc_html( number_format_i18n( $t[1] ) ) . '</b><span>' . esc_html( $t[0] ) . '</span></a>';
		}
		echo '</div>';

		// Toolbar (own GET form — the table's form stays below for bulk actions).
		$sel = static function ( $name, $all, $opts, $cur ) {
			$h = '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $all ) . '</option>';
			foreach ( $opts as $v => $l ) {
				$h .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $cur, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
			}
			return $h . '</select>';
		};
		$terms = static function ( $tax ) {
			$out = array();
			foreach ( (array) get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t ) {
				if ( is_object( $t ) ) {
					$out[ $t->slug ] = $t->name;
				}
			}
			return $out;
		};
		$cities = array();
		foreach ( CP_Location::cities() as $city ) {
			$cities[ $city ] = $city;
		}
		?>
		<form class="cpl-bar" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( CP_Library::CPT ); ?>" />
			<?php if ( $trash ) : ?><input type="hidden" name="post_status" value="trash" /><?php endif; ?>
			<div class="cpl-row">
				<label class="cpl-search"><span class="screen-reader-text"><?php esc_html_e( 'Search', 'hypeit' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search name or @username…', 'hypeit' ); ?>" />
				</label>
				<?php
				echo $sel( CP_Library::TAX_TAG, __( 'All categories', 'hypeit' ), $terms( CP_Library::TAX_TAG ), $g( CP_Library::TAX_TAG ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $sel( CP_Library::TAX_LIST, __( 'All lists', 'hypeit' ), $terms( CP_Library::TAX_LIST ), $g( CP_Library::TAX_LIST ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $sel( 'cp_gender_filter', __( 'Any gender', 'hypeit' ), CP_Library::genders(), $g( 'cp_gender_filter' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $sel( 'cp_city_filter', __( 'Any city', 'hypeit' ), $cities, $g( 'cp_city_filter' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $sel( 'cp_verified_filter', __( 'Verified: any', 'hypeit' ), array( 'yes' => __( 'Verified', 'hypeit' ), 'no' => __( 'Not verified', 'hypeit' ) ), $g( 'cp_verified_filter' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $sel( 'cp_igstatus', __( 'Instagram: any', 'hypeit' ), array( 'ok' => __( 'Creator / Business', 'hypeit' ), 'personal' => __( 'Personal / can’t read', 'hypeit' ), 'none' => __( 'Not checked yet', 'hypeit' ) ), $g( 'cp_igstatus' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $sel( 'cp_blocked_filter', __( 'Active + blocked', 'hypeit' ), array( 'active' => __( 'Active only', 'hypeit' ), 'blocked' => __( 'Blocked only', 'hypeit' ), 'inactive' => __( 'Deactivated', 'hypeit' ) ), $g( 'cp_blocked_filter' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<div class="cpl-row">
				<details class="cpl-missing">
					<summary><?php esc_html_e( 'Missing info', 'hypeit' ); ?><?php echo $miss ? ' <span class="cpl-count">' . count( $miss ) . '</span>' : ''; ?></summary>
					<div class="cpl-missing-panel">
						<div class="cpl-missing-grid">
							<?php foreach ( $items as $k => $lab ) : ?>
								<label class="cpl-chk"><input type="checkbox" name="cp_missing[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $miss, true ) ); ?> /> <?php echo esc_html( $lab ); ?></label>
							<?php endforeach; ?>
						</div>
						<div class="cpl-mode">
							<label><input type="radio" name="cp_mmode" value="any" <?php checked( $mode, 'any' ); ?> /> <?php esc_html_e( 'Missing any of these', 'hypeit' ); ?></label>
							<label><input type="radio" name="cp_mmode" value="all" <?php checked( $mode, 'all' ); ?> /> <?php esc_html_e( 'Missing all of these', 'hypeit' ); ?></label>
						</div>
					</div>
				</details>
				<label class="cpl-sort"><?php esc_html_e( 'Sort', 'hypeit' ); ?>
					<select name="cp_sort">
						<?php
						$sorts = self::sorts();
						foreach ( $sorts as $v => $l ) {
							echo '<option value="' . esc_attr( $v ) . '"' . selected( $sort, $v, false ) . '>' . esc_html( $l ) . '</option>';
						}
						?>
					</select>
				</label>
				<span class="cpl-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'hypeit' ); ?></button>
					<a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Reset', 'hypeit' ); ?></a>
				</span>
			</div>
		</form>
		<?php
		// Active filter chips.
		$chips = array();
		$drop  = static function ( $key, $val = null ) {
			$q = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( null === $val ) {
				unset( $q[ $key ] );
			} else {
				$q[ $key ] = array_values( array_diff( (array) $q[ $key ], array( $val ) ) );
			}
			unset( $q['paged'] );
			return add_query_arg( array_map( static function ( $v ) { return is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_text_field( wp_unslash( $v ) ); }, $q ), admin_url( 'edit.php' ) );
		};
		$labels = array(
			's'                   => __( 'Search', 'hypeit' ),
			CP_Library::TAX_TAG   => __( 'Category', 'hypeit' ),
			CP_Library::TAX_LIST  => __( 'List', 'hypeit' ),
			'cp_gender_filter'    => __( 'Gender', 'hypeit' ),
			'cp_city_filter'      => __( 'City', 'hypeit' ),
			'cp_verified_filter'  => __( 'Verified', 'hypeit' ),
			'cp_igstatus'         => __( 'Instagram', 'hypeit' ),
			'cp_blocked_filter'   => __( 'Status', 'hypeit' ),
			'cp_dupes'            => __( 'Duplicates', 'hypeit' ),
		);
		foreach ( $labels as $k => $lab ) {
			$v = $g( $k );
			if ( '' !== $v ) {
				$chips[] = array( $lab . ': ' . ( 'cp_dupes' === $k ? __( 'only', 'hypeit' ) : $v ), $drop( $k ) );
			}
		}
		foreach ( $miss as $k ) {
			$chips[] = array( sprintf( /* translators: %s: item. */ __( 'Missing %s', 'hypeit' ), $items[ $k ] ), $drop( 'cp_missing', $k ) );
		}
		if ( $chips ) {
			echo '<div class="cpl-chips">';
			foreach ( $chips as $ch ) {
				echo '<a class="cpl-chip" href="' . esc_url( $ch[1] ) . '">' . esc_html( $ch[0] ) . ' <span aria-hidden="true">✕</span></a>';
			}
			if ( count( $miss ) > 1 ) {
				echo '<span class="cpl-muted">' . esc_html( 'all' === $mode ? __( '(missing all)', 'hypeit' ) : __( '(missing any)', 'hypeit' ) ) . '</span>';
			}
			echo '</div>';
		}

		// Keep only Trash in the views row (as a small link), if there is any.
		return isset( $views['trash'] ) ? array( 'trash' => $views['trash'] ) : array();
	}

	/**
	 * Page assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( 'edit.php' === $hook && $screen && CP_Library::CPT === $screen->post_type ) {
			wp_enqueue_style( 'cp-admin', CP_URL . 'assets/css/admin.css', array(), CP_VERSION );
		}
	}
}
