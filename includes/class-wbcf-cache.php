<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Caches WBCF_Render::render_results()'s output HTML in a transient so a
 * tab that's already been rendered once skips the WP_Query + template
 * loop entirely on the next request. Cache invalidation is
 * version-based rather than key-enumeration-based: bumping
 * `wbcf_cache_version` makes every previously-cached key implicitly
 * stale (it's baked into the transient key), without needing to know
 * every filter_id/tab combination that might be cached.
 *
 * The version is bumped whenever content that could affect a filter's
 * output changes: a post is saved/trashed/deleted, a post's terms
 * change, a term itself changes, or a filter's own settings are saved.
 * This is intentionally a little eager (any post save bumps the
 * version, even for post types no filter uses) in exchange for never
 * serving stale content -- correctness over squeezing out the last bit
 * of cache-hit rate.
 */
class WBCF_Cache {

	private static $instance = null;
	const VERSION_OPTION = 'wbcf_cache_version';
	private $bumped_this_request = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'save_post', array( $this, 'bump' ) );
		add_action( 'deleted_post', array( $this, 'bump' ) );
		add_action( 'trashed_post', array( $this, 'bump' ) );
		add_action( 'untrashed_post', array( $this, 'bump' ) );
		add_action( 'set_object_terms', array( $this, 'bump' ) );
		add_action( 'edited_term', array( $this, 'bump' ) );
		add_action( 'delete_term', array( $this, 'bump' ) );
		add_action( 'wbcf_after_save_filter', array( $this, 'bump' ) );
	}

	/**
	 * Build the transient key for one (filter, tab, overrides, page) combination.
	 */
	public static function build_key( $filter_id, $tab, $overrides = array(), $page = 1 ) {
		$version     = get_option( self::VERSION_OPTION, '1' );
		$fingerprint = md5( wp_json_encode( array( $tab, $overrides, (int) $page ) ) );
		// Transient keys are capped at 172 chars by WordPress -- keep this well under that.
		return 'wbcf_c_' . $filter_id . '_' . substr( $fingerprint, 0, 12 ) . '_' . $version;
	}

	public static function get( $key ) {
		return get_transient( $key );
	}

	public static function set( $key, $value, $ttl ) {
		set_transient( $key, $value, $ttl );
	}

	/**
	 * Invalidate every cached filter result. Debounced to once per
	 * request -- a page save can fire save_post + set_object_terms
	 * several times, and bumping the option is the only part that
	 * actually costs a DB write.
	 */
	public function bump() {
		if ( $this->bumped_this_request ) {
			return;
		}
		$this->bumped_this_request = true;
		update_option( self::VERSION_OPTION, (string) time(), false );
	}
}
