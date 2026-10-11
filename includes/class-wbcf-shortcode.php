<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WBCF_Shortcode {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'wbcf_filter', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'wbcf_filter' );

		$filter_id = absint( $atts['id'] );
		if ( ! $filter_id ) {
			return '';
		}

		// Flag that at least one filter is present on this request so
		// WBCF_Assets knows to enqueue CSS/JS even on pages that build
		// content dynamically (page builders, widgets, etc.).
		WBCF_Assets::instance()->mark_active();

		return WBCF_Render::render_filter( $filter_id );
	}
}
