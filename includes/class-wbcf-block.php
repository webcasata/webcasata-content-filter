<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * A dynamic (server-rendered) block. The PHP side registers attributes
 * and the render_callback; the JS side (assets/js/wbcf-block.js) provides
 * the editor UI. Both share the block name 'webcasata/content-filter'.
 *
 * render_callback calls WBCF_Render::render_filter() -- the exact same
 * method the [wbcf_filter] shortcode uses -- so the block can never
 * render differently than the shortcode for the same filter/overrides.
 */
class WBCF_Block {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_block() {
		register_block_type(
			'webcasata/content-filter',
			array(
				'attributes'      => array(
					'filterId'             => array(
						'type'    => 'number',
						'default' => 0,
					),
					'overridePostsPerPage' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'overrideTemplate'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	public function render_block( $attributes ) {
		$filter_id = isset( $attributes['filterId'] ) ? absint( $attributes['filterId'] ) : 0;

		if ( ! $filter_id ) {
			// Only editors/admins see the empty-state placeholder; visitors get nothing.
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="wbcf-block-placeholder">' . esc_html__( 'Select a Webcasata Content Filter in the block settings.', 'webcasata-content-filter' ) . '</p>';
			}
			return '';
		}

		WBCF_Assets::instance()->mark_active();

		$overrides = array();
		if ( ! empty( $attributes['overridePostsPerPage'] ) ) {
			$overrides['posts_per_page'] = absint( $attributes['overridePostsPerPage'] );
		}
		if ( ! empty( $attributes['overrideTemplate'] ) ) {
			$overrides['template'] = sanitize_key( $attributes['overrideTemplate'] );
		}

		return WBCF_Render::render_filter( $filter_id, $overrides );
	}

	public function enqueue_editor_assets() {
		$suffix = wbcf_asset_suffix();

		wp_enqueue_script(
			'wbcf-block',
			WBCF_PLUGIN_URL . 'assets/js/wbcf-block' . $suffix . '.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			WBCF_VERSION,
			true
		);

		wp_enqueue_style( 'wbcf-block-editor', WBCF_PLUGIN_URL . 'assets/css/wbcf-block-editor' . $suffix . '.css', array(), WBCF_VERSION );

		$filters = get_posts(
			array(
				'post_type'      => WBCF_POST_TYPE,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$filter_options = array(
			array(
				'id'    => 0,
				'title' => __( '— Select a filter —', 'webcasata-content-filter' ),
			),
		);
		foreach ( $filters as $filter ) {
			$filter_options[] = array(
				'id'    => $filter->ID,
				'title' => $filter->post_title,
			);
		}

		wp_localize_script(
			'wbcf-block',
			'wbcfBlockData',
			array(
				'filters'   => $filter_options,
				'templates' => $this->build_template_options(),
			)
		);
	}

	/**
	 * "Don't override" plus every registered template (including Pro's,
	 * via the same wbcf_template_choices filter the admin dropdown uses).
	 */
	private function build_template_options() {
		$options = array(
			array(
				'value' => '',
				'label' => __( "Don't override", 'webcasata-content-filter' ),
			),
		);
		foreach ( WBCF_Render::get_template_choices() as $slug => $label ) {
			$options[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}
		return $options;
	}
}
