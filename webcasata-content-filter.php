<?php
/**
 * Plugin Name:       Webcasata Content Filter
 * Plugin URI:        https://www.webcasata.com/webcasata-content-filter/
 * Description:       Create tabbed, AJAX-powered post filters with a shortcode or block. No page reload, SEO-friendly initial render.
 * Version:           1.4.0
 * Author:            Webcasata
 * Author URI:        https://webcasata.com
 * Text Domain:       webcasata-content-filter
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WBCF_VERSION', '1.4.0' );
define( 'WBCF_PLUGIN_FILE', __FILE__ );
define( 'WBCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCF_POST_TYPE', 'wbcf_filter' ); // NOTE: intentionally NOT "wc_filter" to avoid colliding with WooCommerce's "wc_" namespace.

require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-post-type.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-admin.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-render.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-cache.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-ajax.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-assets.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-shortcode.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-block.php';
require_once WBCF_PLUGIN_DIR . 'includes/class-wbcf-upgrade-notice.php';

/**
 * Core plugin bootstrap. Kept intentionally thin -- every real feature
 * lives in its own class under /includes so Phase 2+ can extend without
 * touching this file.
 */
final class Webcasata_Content_Filter {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// WordPress.org auto-loads translations for this plugin's text domain
		// as of WP 4.6+ (matching slug required, which this has) -- no
		// manual load_plugin_textdomain() call needed.

		WBCF_Post_Type::instance();
		WBCF_Admin::instance();
		WBCF_Cache::instance();
		WBCF_Ajax::instance();
		WBCF_Assets::instance();
		WBCF_Shortcode::instance();
		WBCF_Block::instance();
		WBCF_Upgrade_Notice::instance();

		register_activation_hook( WBCF_PLUGIN_FILE, array( 'WBCF_Post_Type', 'activate' ) );
		register_deactivation_hook( WBCF_PLUGIN_FILE, array( 'WBCF_Post_Type', 'deactivate' ) );
	}
}

Webcasata_Content_Filter::instance();

/**
 * ".min" in production, "" when SCRIPT_DEBUG is on (matches WP core's own
 * convention for its bundled scripts/styles) -- so minified assets are
 * used automatically for real visitors, while developers debugging the
 * plugin (or the person who requested it) can flip on SCRIPT_DEBUG in
 * wp-config.php and get the readable, commented source instead.
 */
function wbcf_asset_suffix() {
	return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
}
