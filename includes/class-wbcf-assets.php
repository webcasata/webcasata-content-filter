<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps CSS/JS off pages that don't use the plugin. Checks post content
 * for the shortcode up front; if the shortcode only appears via a page
 * builder / dynamic content (so it wasn't in $post->post_content at the
 * time we checked), WBCF_Shortcode::render() calls mark_active() and we
 * enqueue as a late safety net before wp_footer.
 */
class WBCF_Assets {

	private static $instance = null;
	private $enqueued = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'wp_footer', array( $this, 'footer_safety_net' ), 1 );
	}

	public function maybe_enqueue() {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post
				&& ( has_shortcode( $post->post_content, 'wbcf_filter' ) || has_block( 'webcasata/content-filter', $post ) ) ) {
				$this->enqueue();
			}
		}
	}

	/**
	 * Called by WBCF_Shortcode::render() every time the shortcode actually
	 * executes, so we still load assets even if maybe_enqueue() above
	 * couldn't detect the shortcode ahead of time (widgets, page
	 * builders, block content, etc.).
	 */
	public function mark_active() {
		if ( ! $this->enqueued ) {
			$this->enqueue();
		}
	}

	public function footer_safety_net() {
		// If something enqueued after wp_enqueue_scripts already ran,
		// print the tags directly so the widget still works.
		if ( $this->enqueued && ! wp_script_is( 'wbcf-public', 'done' ) ) {
			wp_print_styles( 'wbcf-public' );
			wp_print_scripts( 'wbcf-public' );
		}
	}

	private function enqueue() {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		$suffix = wbcf_asset_suffix();

		wp_enqueue_style( 'wbcf-public', WBCF_PLUGIN_URL . 'assets/css/wbcf-public' . $suffix . '.css', array(), WBCF_VERSION );

		wp_enqueue_script( 'wbcf-public', WBCF_PLUGIN_URL . 'assets/js/wbcf-public' . $suffix . '.js', array(), WBCF_VERSION, true );
		wp_localize_script(
			'wbcf-public',
			'wbcfPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbcf_public_nonce' ),
				'i18n'    => array(
					'loading' => __( 'Loading…', 'webcasata-content-filter' ),
					'error'   => __( 'Something went wrong. Please try again.', 'webcasata-content-filter' ),
				),
			)
		);

		/**
		 * WooCommerce compatibility: a filter's cards can include a price
		 * + Add to Cart button for the 'product' post type (see
		 * WBCF_Render::maybe_render_woocommerce_meta()). WooCommerce
		 * itself registers 'wc-add-to-cart' (its own AJAX add-to-cart
		 * script, which delegates click handling from document.body so
		 * it works no matter what markup wraps the button) but only
		 * enqueues it on the pages it already recognizes as its own.
		 * A shortcode/block embedded in an ordinary post or page isn't
		 * one of those, so this plugin enqueues it itself whenever
		 * WooCommerce is active -- harmless on a page with no products,
		 * since the script does nothing without a matching button.
		 */
		if ( class_exists( 'WooCommerce' ) && wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}

		/**
		 * Fires right after this plugin's own public CSS/JS are enqueued
		 * -- whether that happened eagerly (wp_enqueue_scripts) or late
		 * (mark_active(), called from the shortcode/block render path).
		 * This is how Pro's premium templates load their own stylesheet
		 * reliably without needing to duplicate this detection logic.
		 */
		do_action( 'wbcf_assets_enqueued' );
	}
}
