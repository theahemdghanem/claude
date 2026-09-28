<?php
/**
 * Blogger Library helpers.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Library {

	const CPT      = 'cp_blogger';
	const TAX_TAG  = 'cp_blogger_tag';
	const TAX_LIST = 'cp_blogger_list';

	/** Meta flag for deactivated bloggers. */
	const META_INACTIVE = '_cp_inactive';

	/**
	 * Cache of deactivated handles.
	 *
	 * @var array|null
	 */
	private static $inactive = null;

	/**
	 * Gender options.
	 *
	 * @return array
	 */
	public static function genders() {
		return array(
			'female' => __( 'Female', 'hypeit' ),
			'male'   => __( 'Male', 'hypeit' ),
		);
	}

	/**
	 * The IG handle for a library blogger (meta, falling back to the title).
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	public static function handle( $id ) {
		$h = trim( (string) get_post_meta( $id, '_cp_ig', true ) );
		if ( '' === $h ) {
			$h = get_the_title( $id );
		}
		return ltrim( trim( (string) $h ), '@' );
	}

	/**
	 * Onboarding category options (become taxonomy terms). Used as seed defaults.
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'Fashion'       => __( 'Fashion', 'hypeit' ),
			'Beauty'        => __( 'Beauty', 'hypeit' ),
			'Lifestyle'     => __( 'Lifestyle', 'hypeit' ),
			'Foodie'        => __( 'Foodie', 'hypeit' ),
			'Travel'        => __( 'Travel', 'hypeit' ),
			'Entertainment' => __( 'Entertainment', 'hypeit' ),
		);
	}

	/**
	 * All category terms (dynamic — reflects what admins add).
	 *
	 * @return array term objects
	 */
	public static function category_terms() {
		$terms = get_terms( array( 'taxonomy' => self::TAX_TAG, 'hide_empty' => false, 'orderby' => 'name' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Collaboration types a blogger can be open to.
	 *
	 * @return array
	 */
	public static function collab_types() {
		return array(
			'paid'   => __( 'Paid', 'hypeit' ),
			'unpaid' => __( 'Non-paid', 'hypeit' ),
			'barter' => __( 'In return for product/service', 'hypeit' ),
		);
	}

	/**
	 * Normalize a name: first character upper-case, the rest lower-case.
	 * "mOnA" → "Mona".
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name ) {
			return '';
		}
		if ( function_exists( 'mb_strtolower' ) ) {
			$lower = mb_strtolower( $name, 'UTF-8' );
			$first = mb_strtoupper( mb_substr( $lower, 0, 1, 'UTF-8' ), 'UTF-8' );
			return $first . mb_substr( $lower, 1, null, 'UTF-8' );
		}
		return ucfirst( strtolower( $name ) );
	}

	/**
	 * Extract a clean Instagram handle from a username or a profile URL.
	 *
	 * @param string $input Raw username or URL.
	 * @return string Handle without the @, or '' if none.
	 */
	public static function extract_handle( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return '';
		}
		if ( preg_match( '~instagram\.com/([A-Za-z0-9._]+)~i', $input, $m ) ) {
			$input = $m[1];
		}
		$input = ltrim( $input, '@' );
		return preg_replace( '/[^A-Za-z0-9._]/', '', $input );
	}

	/**
	 * Web profile URL for a handle.
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function profile_url( $handle ) {
		return 'https://www.instagram.com/' . rawurlencode( ltrim( $handle, '@' ) ) . '/';
	}

	/**
	 * Blocked handles option.
	 *
	 * @return array
	 */
	public static function blocked_handles() {
		$list = get_option( 'cp_blocked_handles', array() );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Whether a handle is blocked.
	 *
	 * @param string $handle Handle.
	 * @return bool
	 */
	public static function is_blocked( $handle ) {
		return in_array( strtolower( ltrim( $handle, '@' ) ), array_map( 'strtolower', self::blocked_handles() ), true );
	}

	/**
	 * Block a handle.
	 *
	 * @param string $handle Handle.
	 */
	public static function block_handle( $handle ) {
		$handle = strtolower( ltrim( $handle, '@' ) );
		if ( '' === $handle ) {
			return;
		}
		$list = self::blocked_handles();
		if ( ! in_array( $handle, array_map( 'strtolower', $list ), true ) ) {
			$list[] = $handle;
			update_option( 'cp_blocked_handles', array_values( array_unique( $list ) ) );
		}
	}

	/**
	 * Unblock a handle.
	 *
	 * @param string $handle Handle.
	 */
	public static function unblock_handle( $handle ) {
		$handle = strtolower( ltrim( $handle, '@' ) );
		$list   = array_filter(
			self::blocked_handles(),
			static function ( $h ) use ( $handle ) {
				return strtolower( $h ) !== $handle;
			}
		);
		update_option( 'cp_blocked_handles', array_values( $list ) );
	}

	/* ------------------------------------------------------------------ */
	/* Campaign types                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Campaign types: key => array( label, short description, matching blogger "Open for" key ).
	 *
	 * @return array
	 */
	public static function campaign_types() {
		return array(
			'paid'    => array( __( 'Paid', 'hypeit' ), __( 'Bloggers are paid a fee', 'hypeit' ), 'paid' ),
			'service' => array( __( 'Service/Product Based', 'hypeit' ), __( 'Bloggers get the product or service in return', 'hypeit' ), 'barter' ),
		);
	}

	/**
	 * A campaign's type key ('' when not set yet).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	public static function campaign_type( $campaign_id ) {
		$t = (string) get_post_meta( $campaign_id, '_cp_type', true );
		return isset( self::campaign_types()[ $t ] ) ? $t : '';
	}

	/**
	 * Save a campaign's type (unknown values are ignored).
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $type        Type key.
	 */
	public static function set_campaign_type( $campaign_id, $type ) {
		$type = sanitize_key( (string) $type );
		if ( isset( self::campaign_types()[ $type ] ) ) {
			update_post_meta( $campaign_id, '_cp_type', $type );
		}
	}

	/**
	 * Label for a campaign's type ('' when not set).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	public static function campaign_type_label( $campaign_id ) {
		$t = self::campaign_type( $campaign_id );
		return $t ? self::campaign_types()[ $t ][0] : '';
	}

	/* ------------------------------------------------------------------ */
	/* Deactivated bloggers                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Is this blogger deactivated? (Kept in the library and in past campaign
	 * records, but hidden from campaigns, selections, lists and the app.)
	 *
	 * @param int $id Blogger ID.
	 * @return bool
	 */
	public static function is_inactive( $id ) {
		return '1' === (string) get_post_meta( $id, self::META_INACTIVE, true );
	}

	/**
	 * Deactivate or reactivate a blogger.
	 *
	 * @param int  $id       Blogger ID.
	 * @param bool $inactive Deactivate (true) or reactivate (false).
	 */
	public static function set_inactive( $id, $inactive ) {
		$id = (int) $id;
		if ( ! $id || self::CPT !== get_post_type( $id ) || (bool) $inactive === self::is_inactive( $id ) ) {
			return;
		}
		if ( $inactive ) {
			update_post_meta( $id, self::META_INACTIVE, '1' );
			update_post_meta( $id, '_cp_inactive_at', time() );
		} else {
			delete_post_meta( $id, self::META_INACTIVE );
			delete_post_meta( $id, '_cp_inactive_at' );
		}
		self::$inactive = null;
		if ( class_exists( 'CP_Lists' ) ) {
			CP_Lists::sync_blogger( $id );
		}
		if ( class_exists( 'CP_Join' ) ) {
			CP_Join::bust();
		}
		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}
		// Back in the library: join open "Everyone" campaigns like a new blogger would.
		if ( ! $inactive && class_exists( 'CP_Everyone' ) ) {
			CP_Everyone::on_blogger_saved( $id );
		}
	}

	/**
	 * Lower-case handles of deactivated bloggers (handle => true).
	 *
	 * @return array
	 */
	public static function inactive_handles() {
		if ( null !== self::$inactive ) {
			return self::$inactive;
		}
		self::$inactive = array();
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => self::META_INACTIVE, 'value' => '1' ),
				),
			)
		);
		foreach ( (array) $ids as $id ) {
			$h = strtolower( self::handle( $id ) );
			if ( '' !== $h ) {
				self::$inactive[ $h ] = true;
			}
		}
		return self::$inactive;
	}

	/**
	 * Is this username a deactivated blogger?
	 *
	 * @param string $handle Username.
	 * @return bool
	 */
	public static function handle_inactive( $handle ) {
		$list = self::inactive_handles();
		return isset( $list[ strtolower( ltrim( trim( (string) $handle ), '@' ) ) ] );
	}

	/**
	 * Meta query clause: not blocked and not deactivated.
	 *
	 * @return array
	 */
	public static function active_clause() {
		return array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array( 'key' => '_cp_blocked', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_cp_blocked', 'value' => '1', 'compare' => '!=' ),
			),
			array(
				'relation' => 'OR',
				array( 'key' => self::META_INACTIVE, 'compare' => 'NOT EXISTS' ),
				array( 'key' => self::META_INACTIVE, 'value' => '1', 'compare' => '!=' ),
			),
		);
	}

	/**
	 * Find a library blogger post ID by handle.
	 *
	 * @param string $handle Handle.
	 * @return int
	 */
	public static function find_by_handle( $handle ) {
		$handle = ltrim( trim( $handle ), '@' );
		if ( '' === $handle ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_cp_ig',
						'value' => $handle,
					),
				),
			)
		);
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Map of lowercase handle => attributes, for enriching campaign rows.
	 *
	 * @return array
	 */
	public static function get_map() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map = array();

		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( (array) $ids as $id ) {
			$handle = strtolower( self::handle( $id ) );
			if ( '' === $handle ) {
				continue;
			}
			if ( '1' === (string) get_post_meta( $id, '_cp_blocked', true ) ) {
				continue;
			}
			$tags        = wp_get_post_terms( $id, self::TAX_TAG, array( 'fields' => 'names' ) );
			$map[ $handle ] = array(
				'url'       => (string) get_post_meta( $id, '_cp_ig_url', true ),
				'gender'    => (string) get_post_meta( $id, '_cp_gender', true ),
				'followers' => (int) get_post_meta( $id, '_cp_followers', true ),
				'tags'      => is_array( $tags ) ? implode( ', ', $tags ) : '',
				'city'      => (string) get_post_meta( $id, '_cp_city', true ),
				'area'      => (string) get_post_meta( $id, '_cp_area', true ),
			);
		}

		return $map;
	}

	/**
	 * Handles of library bloggers matching the given lists / tags / gender.
	 *
	 * @param array  $list_ids List term IDs (OR).
	 * @param array  $tag_ids  Tag term IDs (OR).
	 * @param string $gender   Gender key.
	 * @return array
	 */
	public static function handles_for( $list_ids, $tag_ids, $gender, $city = '' ) {
		return self::query_for( $list_ids, $tag_ids, $gender, $city, 'handles' );
	}

	/**
	 * Post IDs matching a flexible set of filters (for the list builder).
	 *
	 * @param array $opts gender, city, tag_ids[], tag_match(any|all),
	 *                    followers_min, followers_max, verified.
	 * @return array
	 */
	public static function filter_ids( $opts ) {
		// "Everyone" — all non-blocked bloggers, ignore other conditions.
		if ( ! empty( $opts['all'] ) ) {
			return array_map(
				'intval',
				get_posts(
					array(
						'post_type'      => self::CPT,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_query'     => self::active_clause(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					)
				)
			);
		}

		$gender    = sanitize_key( isset( $opts['gender'] ) ? $opts['gender'] : '' );
		$city      = sanitize_text_field( isset( $opts['city'] ) ? $opts['city'] : '' );
		$tag_ids   = array_filter( array_map( 'absint', (array) ( isset( $opts['tag_ids'] ) ? $opts['tag_ids'] : array() ) ) );
		$tag_match = ( isset( $opts['tag_match'] ) && 'all' === $opts['tag_match'] ) ? 'all' : 'any';
		$fmin      = ( isset( $opts['followers_min'] ) && '' !== $opts['followers_min'] ) ? absint( $opts['followers_min'] ) : null;
		$fmax      = ( isset( $opts['followers_max'] ) && '' !== $opts['followers_max'] ) ? absint( $opts['followers_max'] ) : null;
		$verified  = ! empty( $opts['verified'] );
		$collab    = sanitize_key( isset( $opts['collab'] ) ? $opts['collab'] : '' );

		$tax = array();
		if ( $tag_ids ) {
			if ( 'all' === $tag_match ) {
				foreach ( $tag_ids as $tid ) {
					$tax[] = array( 'taxonomy' => self::TAX_TAG, 'field' => 'term_id', 'terms' => array( $tid ) );
				}
				if ( count( $tax ) > 1 ) {
					$tax['relation'] = 'AND';
				}
			} else {
				$tax[] = array( 'taxonomy' => self::TAX_TAG, 'field' => 'term_id', 'terms' => $tag_ids );
			}
		}

		$meta   = array( 'relation' => 'AND' );
		$meta[] = self::active_clause();
		if ( $gender ) {
			$meta[] = array( 'key' => '_cp_gender', 'value' => $gender );
		}
		if ( '' !== $city ) {
			$meta[] = array( 'key' => '_cp_city', 'value' => $city );
		}
		if ( $verified ) {
			$meta[] = array( 'key' => '_cp_verified', 'value' => '1' );
		}
		if ( $collab ) {
			$meta[] = array( 'key' => '_cp_collab', 'value' => ',' . $collab . ',', 'compare' => 'LIKE' );
		}
		if ( null !== $fmin ) {
			$meta[] = array( 'key' => '_cp_followers', 'value' => $fmin, 'type' => 'NUMERIC', 'compare' => '>=' );
		}
		if ( null !== $fmax ) {
			$meta[] = array( 'key' => '_cp_followers', 'value' => $fmax, 'type' => 'NUMERIC', 'compare' => '<=' );
		}

		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * Post IDs of library bloggers matching filters (for building lists).
	 *
	 * @param array  $list_ids List term IDs.
	 * @param array  $tag_ids  Tag term IDs.
	 * @param string $gender   Gender key.
	 * @param string $city     City.
	 * @return array
	 */
	public static function ids_for( $list_ids, $tag_ids, $gender, $city = '' ) {
		return self::query_for( $list_ids, $tag_ids, $gender, $city, 'ids' );
	}

	/**
	 * Shared query for handles_for / ids_for.
	 *
	 * @param array  $list_ids List term IDs.
	 * @param array  $tag_ids  Tag term IDs.
	 * @param string $gender   Gender key.
	 * @param string $city     City.
	 * @param string $return   'handles' or 'ids'.
	 * @return array
	 */
	private static function query_for( $list_ids, $tag_ids, $gender, $city, $return ) {
		$list_ids = array_filter( array_map( 'absint', (array) $list_ids ) );
		$tag_ids  = array_filter( array_map( 'absint', (array) $tag_ids ) );
		$gender   = sanitize_key( $gender );
		$city     = sanitize_text_field( $city );

		$tax = array();
		if ( $list_ids ) {
			$tax[] = array( 'taxonomy' => self::TAX_LIST, 'field' => 'term_id', 'terms' => $list_ids );
		}
		if ( $tag_ids ) {
			$tax[] = array( 'taxonomy' => self::TAX_TAG, 'field' => 'term_id', 'terms' => $tag_ids );
		}
		if ( count( $tax ) > 1 ) {
			$tax['relation'] = 'AND';
		}

		$meta = array( 'relation' => 'AND' );
		$meta[] = self::active_clause();
		if ( $gender ) {
			$meta[] = array( 'key' => '_cp_gender', 'value' => $gender );
		}
		if ( '' !== $city ) {
			$meta[] = array( 'key' => '_cp_city', 'value' => $city );
		}

		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$ids = get_posts( $args );

		if ( 'ids' === $return ) {
			return array_map( 'intval', (array) $ids );
		}

		$handles = array();
		foreach ( (array) $ids as $id ) {
			$h = self::handle( $id );
			if ( '' !== $h ) {
				$handles[] = $h;
			}
		}
		return $handles;
	}
}
