<?php
/**
 * The Campaigns list page — redesigned (edit.php?post_type=cp_campaign).
 *
 * Same idea as the Bloggers page: WordPress's list engine stays (pagination,
 * bulk + row actions, search), but you get a summary strip, one toolbar
 * (search, status, sort), and columns that show where each campaign stands:
 * status, client progress, attendance and the private link.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Campaigns_UI {

	/** @var array|null Campaign ID => stats (one query for the whole page). */
	private static $stats = null;

	/**
	 * Hooks (admin only).
	 */
	public static function init() {
		add_filter( 'manage_' . CP_POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_' . CP_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . CP_POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_filter( 'views_edit-' . CP_POST_TYPE, array( __CLASS__, 'header' ), 99 );
		add_filter( 'disable_months_dropdown', array( __CLASS__, 'no_months' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_action( 'admin_footer-edit.php', array( __CLASS__, 'footer_js' ) );
	}

	/**
	 * Are we on the campaigns list?
	 *
	 * @return bool
	 */
	private static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'edit-' . CP_POST_TYPE === $screen->id;
	}

	/**
	 * Stats for one campaign (cached for the page).
	 *
	 * @param int $id Campaign ID.
	 * @return array
	 */
	private static function stats( $id ) {
		if ( null === self::$stats ) {
			self::$stats = array();
			foreach ( CP_DB::all_campaign_stats( true ) as $row ) {
				self::$stats[ $row['id'] ] = $row;
			}
		}
		if ( ! isset( self::$stats[ $id ] ) ) {
			$s                  = CP_DB::stats( $id ); // Trashed campaigns aren't in the page query.
			self::$stats[ $id ] = array(
				'total'        => $s['total'],
				'confirmed'    => $s['confirmed'],
				'declined'     => $s['declined'],
				'pending'      => $s['pending'],
				'extra_guests' => $s['extra_guests'],
				'attendance'   => $s['attendance'],
				'closed'       => CP_Close::is_closed( $id ),
				'status'       => get_post_status( $id ),
			);
		}
		return self::$stats[ $id ];
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
			'cb'            => isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox" />',
			'title'         => __( 'Campaign', 'hypeit' ),
			'cp_state'      => __( 'Status', 'hypeit' ),
			'cp_progress'   => __( 'Client responses', 'hypeit' ),
			'cp_attendance' => __( 'People', 'hypeit' ),
			'cp_link'       => __( 'Client link', 'hypeit' ),
			'date'          => __( 'Created', 'hypeit' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function sortable( $cols ) {
		$cols['title'] = 'title';
		$cols['date']  = array( 'date', true );
		return $cols;
	}

	/**
	 * Column content.
	 *
	 * @param string $col Column.
	 * @param int    $id  Campaign ID.
	 */
	public static function column( $col, $id ) {
		$s = self::stats( $id );
		switch ( $col ) {
			case 'cp_state':
				$status = get_post_status( $id );
				if ( ! empty( $s['closed'] ) ) {
					echo '<span class="cpc-pill is-closed">' . esc_html__( 'Closed', 'hypeit' ) . '</span>';
				} elseif ( 'publish' === $status ) {
					echo '<span class="cpc-pill is-live">' . esc_html__( 'Live', 'hypeit' ) . '</span>';
				} elseif ( 'future' === $status ) {
					echo '<span class="cpc-pill">' . esc_html__( 'Scheduled', 'hypeit' ) . '</span>';
				} elseif ( 'trash' === $status ) {
					echo '<span class="cpc-pill">' . esc_html__( 'Trash', 'hypeit' ) . '</span>';
				} else {
					echo '<span class="cpc-pill is-draft">' . esc_html__( 'Draft', 'hypeit' ) . '</span>';
				}
				$ct = CP_Library::campaign_type( $id );
				if ( $ct ) {
					echo '<span class="cpc-type is-' . esc_attr( $ct ) . '">' . esc_html( CP_Library::campaign_type_label( $id ) ) . '</span>';
				}
				if ( CP_Everyone::is_on( $id ) ) {
					echo '<span class="cpl-sub" title="' . esc_attr__( 'Every blogger in the library is included.', 'hypeit' ) . '">' . esc_html__( 'Everyone', 'hypeit' ) . '</span>';
				}
				break;

			case 'cp_progress':
				$total = (int) $s['total'];
				if ( ! $total ) {
					echo '<span class="cpl-muted">' . esc_html__( 'No bloggers yet', 'hypeit' ) . '</span>';
					break;
				}
				$done = (int) $s['confirmed'] + (int) $s['declined'];
				echo '<div class="cpc-progress" title="' . esc_attr( sprintf( /* translators: 1: confirmed, 2: declined, 3: waiting. */ __( '%1$d confirmed · %2$d declined · %3$d waiting', 'hypeit' ), $s['confirmed'], $s['declined'], $s['pending'] ) ) . '">'
					. '<span class="cpc-bar"><i class="c" style="flex:' . (int) $s['confirmed'] . '"></i><i class="d" style="flex:' . (int) $s['declined'] . '"></i><i class="p" style="flex:' . (int) $s['pending'] . '"></i></span>'
					. '<span class="cpc-pct">' . (int) round( $done / $total * 100 ) . '%</span></div>';
				echo '<span class="cpc-counts"><b class="is-c">' . (int) $s['confirmed'] . '</b> ' . esc_html__( 'confirmed', 'hypeit' )
					. ' · <b class="is-d">' . (int) $s['declined'] . '</b> ' . esc_html__( 'declined', 'hypeit' )
					. ' · <b>' . (int) $s['pending'] . '</b> ' . esc_html__( 'waiting', 'hypeit' ) . '</span>';
				break;

			case 'cp_attendance':
				echo '<strong class="cpc-big">' . (int) $s['attendance'] . '</strong>';
				if ( (int) $s['extra_guests'] ) {
					echo '<span class="cpl-sub">' . esc_html( sprintf( /* translators: %d: guests. */ _n( 'incl. %d guest', 'incl. %d guests', (int) $s['extra_guests'], 'hypeit' ), (int) $s['extra_guests'] ) ) . '</span>';
				}
				break;

			case 'cp_link':
				$token = get_post_meta( $id, '_cp_token', true );
				$url   = $token ? CP_Frontend::campaign_url( $token ) : '';
				if ( ! $url || 'publish' !== get_post_status( $id ) || ! empty( $s['closed'] ) ) {
					echo '<span class="cpl-muted">' . esc_html( ! empty( $s['closed'] ) ? __( 'Disabled (closed)', 'hypeit' ) : __( 'Publish to activate', 'hypeit' ) ) . '</span>';
					break;
				}
				echo '<span class="cpc-link">'
					. '<button type="button" class="button button-small cpc-copy" data-link="' . esc_url( $url ) . '" data-done="' . esc_attr__( 'Copied!', 'hypeit' ) . '">' . esc_html__( 'Copy', 'hypeit' ) . '</button>'
					. '<a class="button button-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'hypeit' ) . '</a></span>';
				if ( ! CP_Auth::has_password( $id ) ) {
					echo '<span class="cpl-sub cpc-warn">' . esc_html__( 'No password set', 'hypeit' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Close / reopen right from the list.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( CP_POST_TYPE !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] ); // Quick Edit has nothing useful for campaigns.
		if ( CP_Close::is_closed( $post->ID ) ) {
			$actions['cp_reopen'] = '<a href="' . esc_url( CP_Close::url( $post->ID, false ) ) . '">' . esc_html__( 'Reopen', 'hypeit' ) . '</a>';
		} elseif ( 'publish' === $post->post_status ) {
			$actions['cp_close'] = '<a href="' . esc_url( CP_Close::url( $post->ID, true ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Close this campaign? The client will no longer be able to open it. You can reopen it any time.', 'hypeit' ) ) . '\');">' . esc_html__( 'Close', 'hypeit' ) . '</a>';
		}
		return $actions;
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
		return CP_POST_TYPE === $type ? true : $off;
	}

	/**
	 * Status filter + sort menu.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || CP_POST_TYPE !== $q->get( 'post_type' ) ) {
			return;
		}
		$state = isset( $_GET['cp_state'] ) ? sanitize_key( wp_unslash( $_GET['cp_state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$open  = array(
			'relation' => 'OR',
			array( 'key' => '_cp_closed', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_cp_closed', 'value' => '1', 'compare' => '!=' ),
		);
		if ( 'live' === $state ) {
			$q->set( 'post_status', 'publish' );
			$q->set( 'meta_query', array( $open ) );
		} elseif ( 'draft' === $state ) {
			$q->set( 'post_status', array( 'draft', 'pending', 'future', 'private' ) );
			$q->set( 'meta_query', array( $open ) );
		} elseif ( 'closed' === $state ) {
			$q->set( 'meta_query', array( array( 'key' => '_cp_closed', 'value' => '1' ) ) );
		}

		$type = isset( $_GET['cp_type'] ) ? sanitize_key( wp_unslash( $_GET['cp_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( CP_Library::campaign_types()[ $type ] ) ) {
			$mq   = (array) $q->get( 'meta_query' );
			$mq[] = array( 'key' => '_cp_type', 'value' => $type );
			$q->set( 'meta_query', $mq );
		}

		if ( ! empty( $_GET['cp_sort'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			list( $ob, $dir ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_GET['cp_sort'] ) ) ), 2, 'desc' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $ob, array( 'date', 'title', 'modified' ), true ) ) {
				$q->set( 'orderby', $ob );
				$q->set( 'order', 'asc' === $dir ? 'ASC' : 'DESC' );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Header                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * List URL.
	 *
	 * @param array $args Args.
	 * @return string
	 */
	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'post_type' => CP_POST_TYPE ), $args ), admin_url( 'edit.php' ) );
	}

	/**
	 * Summary strip + toolbar (replaces the views row).
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function header( $views ) {
		$g = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		};

		$all = CP_DB::all_campaign_stats( true );
		$c   = array( 'total' => count( $all ), 'live' => 0, 'draft' => 0, 'closed' => 0, 'waiting' => 0, 'people' => 0 );
		foreach ( $all as $row ) {
			if ( $row['closed'] ) {
				$c['closed']++;
			} elseif ( 'publish' === $row['status'] ) {
				$c['live']++;
				$c['waiting'] += (int) $row['pending'];
			} else {
				$c['draft']++;
			}
			$c['people'] += (int) $row['attendance'];
		}

		$state = $g( 'cp_state' );
		$tiles = array(
			array( __( 'All campaigns', 'hypeit' ), $c['total'], array(), '', '' === $state ),
			array( __( 'Live', 'hypeit' ), $c['live'], array( 'cp_state' => 'live' ), 'is-ok', 'live' === $state ),
			array( __( 'Drafts', 'hypeit' ), $c['draft'], array( 'cp_state' => 'draft' ), '', 'draft' === $state ),
			array( __( 'Closed', 'hypeit' ), $c['closed'], array( 'cp_state' => 'closed' ), 'is-off', 'closed' === $state ),
			array( __( 'Waiting on clients', 'hypeit' ), $c['waiting'], array( 'cp_state' => 'live' ), 'is-warn', false ),
			array( __( 'People attending', 'hypeit' ), $c['people'], array(), 'is-dark', false ),
		);
		echo '<div class="cpl-stats cpc-stats">';
		foreach ( $tiles as $t ) {
			echo '<a class="cpl-stat ' . esc_attr( $t[3] . ( $t[4] ? ' is-current' : '' ) ) . '" href="' . esc_url( self::url( $t[2] ) ) . '"><b>' . esc_html( number_format_i18n( $t[1] ) ) . '</b><span>' . esc_html( $t[0] ) . '</span></a>';
		}
		echo '</div>';

		$sorts = array(
			''               => __( 'Newest first', 'hypeit' ),
			'date:asc'       => __( 'Oldest first', 'hypeit' ),
			'modified:desc'  => __( 'Recently updated', 'hypeit' ),
			'title:asc'      => __( 'Name A–Z', 'hypeit' ),
			'title:desc'     => __( 'Name Z–A', 'hypeit' ),
		);
		$states = array(
			'live'   => __( 'Live', 'hypeit' ),
			'draft'  => __( 'Drafts', 'hypeit' ),
			'closed' => __( 'Closed', 'hypeit' ),
		);
		$trash = 'trash' === $g( 'post_status' );
		?>
		<form class="cpl-bar cpc-toolbar" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( CP_POST_TYPE ); ?>" />
			<?php if ( $trash ) : ?><input type="hidden" name="post_status" value="trash" /><?php endif; ?>
			<div class="cpl-row">
				<label class="cpl-search"><span class="screen-reader-text"><?php esc_html_e( 'Search', 'hypeit' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search campaigns…', 'hypeit' ); ?>" />
				</label>
				<?php if ( ! $trash ) : ?>
					<select name="cp_state" aria-label="<?php esc_attr_e( 'Status', 'hypeit' ); ?>">
						<option value=""><?php esc_html_e( 'Any status', 'hypeit' ); ?></option>
						<?php foreach ( $states as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $state, $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<select name="cp_type" aria-label="<?php esc_attr_e( 'Campaign type', 'hypeit' ); ?>">
					<option value=""><?php esc_html_e( 'Any type', 'hypeit' ); ?></option>
					<?php foreach ( CP_Library::campaign_types() as $tk => $tv ) : ?>
						<option value="<?php echo esc_attr( $tk ); ?>" <?php selected( $g( 'cp_type' ), $tk ); ?>><?php echo esc_html( $tv[0] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="cpl-sort"><?php esc_html_e( 'Sort', 'hypeit' ); ?>
					<select name="cp_sort">
						<?php foreach ( $sorts as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $g( 'cp_sort' ), $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<span class="cpl-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'hypeit' ); ?></button>
					<a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Reset', 'hypeit' ); ?></a>
				</span>
			</div>
		</form>
		<?php
		// Keep only Trash in the views row (as a small link), if there is any.
		return isset( $views['trash'] ) ? array( 'trash' => $views['trash'] ) : array();
	}

	/**
	 * Copy-link buttons.
	 */
	public static function footer_js() {
		if ( ! self::is_screen() ) {
			return;
		}
		?>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.cpc-copy' );
			if ( ! b ) { return; }
			e.preventDefault();
			var done = function () { var t = b.textContent; b.textContent = b.getAttribute( 'data-done' ); setTimeout( function () { b.textContent = t; }, 1400 ); };
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( b.getAttribute( 'data-link' ) ).then( done );
			} else {
				var ta = document.createElement( 'textarea' ); ta.value = b.getAttribute( 'data-link' ); document.body.appendChild( ta ); ta.select();
				try { document.execCommand( 'copy' ); done(); } catch ( err ) {} ta.remove();
			}
		} );
		</script>
		<?php
	}
}
