<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the front-end AJAX request fired when a visitor clicks a tab.
 * Logged-out visitors need access too, hence both wp_ajax_ and
 * wp_ajax_nopriv_ hooks.
 */
class WBCF_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_wbcf_filter_posts', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_wbcf_filter_posts', array( $this, 'handle' ) );
		add_action( 'wp_ajax_wbcf_load_more', array( $this, 'handle_load_more' ) );
		add_action( 'wp_ajax_nopriv_wbcf_load_more', array( $this, 'handle_load_more' ) );
	}

	public function handle() {
		check_ajax_referer( 'wbcf_public_nonce', 'nonce' );

		list( $filter_id, $settings, $tab ) = $this->validate_request();

		/**
		 * Fires when a visitor actually clicks a tab (not on Load
		 * More/Infinite Scroll requests for the same tab, and not on the
		 * server-rendered initial tab -- just genuine tab-switch clicks).
		 * This is Pro's Analytics extension point.
		 */
		do_action( 'wbcf_tab_viewed', $filter_id, $tab );

		$html = WBCF_Render::render_results( $filter_id, $tab, $this->read_overrides() );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Load More button / Infinite Scroll sentinel -- fetches one further
	 * page of cards to append to an already-rendered grid.
	 */
	public function handle_load_more() {
		check_ajax_referer( 'wbcf_public_nonce', 'nonce' );

		list( $filter_id, $settings, $tab ) = $this->validate_request();

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 2;
		if ( $page < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid page.', 'webcasata-content-filter' ) ), 400 );
		}
		if ( 'none' === $settings['pagination'] ) {
			wp_send_json_error( array( 'message' => __( 'This filter does not have pagination enabled.', 'webcasata-content-filter' ) ), 400 );
		}

		$result = WBCF_Render::render_more( $filter_id, $tab, $page, $this->read_overrides() );

		wp_send_json_success( $result );
	}

	/**
	 * Shared by both actions: pull filter_id/tab out of $_POST and make
	 * sure they're legitimate before any query runs. Ends the request
	 * with a JSON error (via wp_send_json_error, which exits) on failure.
	 *
	 * @return array { 0: int filter_id, 1: array settings, 2: string tab }
	 */
	private function validate_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already verified by check_ajax_referer() in handle()/handle_load_more(), the only two callers of this private method.
		$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;
		$tab       = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : 'all';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $filter_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing filter.', 'webcasata-content-filter' ) ), 400 );
		}

		$settings = WBCF_Render::get_settings( $filter_id );
		if ( ! $settings ) {
			wp_send_json_error( array( 'message' => __( 'Filter not found.', 'webcasata-content-filter' ) ), 404 );
		}

		// Validate the requested tab is actually one of this filter's terms (or "all"). Never trust the slug from the client directly.
		if ( 'all' !== $tab && ! in_array( $tab, WBCF_Render::valid_tab_slugs( $settings ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tab.', 'webcasata-content-filter' ) ), 400 );
		}
		if ( 'all' === $tab && 'yes' !== $settings['show_all_tab'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tab.', 'webcasata-content-filter' ) ), 400 );
		}

		return array( $filter_id, $settings, $tab );
	}

	/**
	 * A block placed with "Override Posts" / "Override Layout" needs
	 * those overrides to survive AJAX tab switches too, not just the
	 * first render -- wbcf-public.js forwards them from the widget's
	 * data-override-* attributes when present.
	 */
	private function read_overrides() {
		$overrides = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already verified by check_ajax_referer() in handle()/handle_load_more(), the only two callers of this private method.
		if ( ! empty( $_POST['override_ppp'] ) ) {
			$overrides['posts_per_page'] = absint( $_POST['override_ppp'] );
		}
		if ( ! empty( $_POST['override_template'] ) ) {
			$overrides['template'] = sanitize_key( wp_unslash( $_POST['override_template'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $overrides;
	}
}
