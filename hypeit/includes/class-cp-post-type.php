<?php
/**
 * Campaign custom post type.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Post_Type {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the campaign post type.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Campaigns', 'hypeit' ),
			'singular_name'      => __( 'Campaign', 'hypeit' ),
			'menu_name'          => __( 'HypeIt', 'hypeit' ),
			'add_new'            => __( 'Add Campaign', 'hypeit' ),
			'add_new_item'       => __( 'Add New Campaign', 'hypeit' ),
			'edit_item'          => __( 'Edit Campaign', 'hypeit' ),
			'new_item'           => __( 'New Campaign', 'hypeit' ),
			'view_item'          => __( 'View Campaign', 'hypeit' ),
			'search_items'       => __( 'Search Campaigns', 'hypeit' ),
			'not_found'          => __( 'No campaigns found.', 'hypeit' ),
			'not_found_in_trash' => __( 'No campaigns found in Trash.', 'hypeit' ),
			'all_items'          => __( 'Campaigns', 'hypeit' ),
		);

		register_post_type(
			CP_POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-megaphone',
				'menu_position'       => 26,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
			)
		);
	}
}
