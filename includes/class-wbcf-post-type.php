<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the `wbcf_filter` custom post type. Each "Filter" a user
 * creates in the admin is one post of this type. All configuration
 * (post type filtered, taxonomy, terms, layout, colors, etc.) lives in
 * post meta -- see WBCF_Admin::save_filter() for the full meta schema.
 *
 * This is deliberately a CPT rather than a wp_options blob so filters
 * get native list-table UI, revisions, and clean export/import later.
 */
class WBCF_Post_Type {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$labels = array(
			'name'               => __( 'Content Filters', 'webcasata-content-filter' ),
			'singular_name'      => __( 'Content Filter', 'webcasata-content-filter' ),
			'add_new'            => __( 'Add New Filter', 'webcasata-content-filter' ),
			'add_new_item'       => __( 'Add New Filter', 'webcasata-content-filter' ),
			'edit_item'          => __( 'Edit Filter', 'webcasata-content-filter' ),
			'new_item'           => __( 'New Filter', 'webcasata-content-filter' ),
			'view_item'          => __( 'View Filter', 'webcasata-content-filter' ),
			'search_items'       => __( 'Search Filters', 'webcasata-content-filter' ),
			'not_found'          => __( 'No filters found. Create your first one!', 'webcasata-content-filter' ),
			'not_found_in_trash' => __( 'No filters found in Trash.', 'webcasata-content-filter' ),
			'all_items'          => __( 'All Filters', 'webcasata-content-filter' ),
		);

		register_post_type(
			WBCF_POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,      // Not a front-end viewable content type.
				'show_ui'             => false,       // We build a fully custom admin UI instead of relying on the default post editor.
				'show_in_menu'        => false,       // Menu is registered manually in WBCF_Admin so we control the whole screen.
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_in_rest'        => false,
			)
		);
	}

	public static function activate() {
		// Ensure the post type is registered before we flush rewrite rules.
		self::instance()->register();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
