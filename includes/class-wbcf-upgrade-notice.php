<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Because Pro is sold and delivered manually (customer buys on your site,
 * you email the zip), the free plugin needs NO license-key API, remote
 * validation, or update-server integration. This class only shows a
 * banner on the plugin's own admin screens that links out to your sales
 * page. Update PRO_URL to your actual checkout/product page.
 *
 * The banner is permanently dismissible, per admin user: clicking the
 * close button fires a small AJAX request that stores a user meta flag,
 * and maybe_render_banner() / maybe_enqueue_assets() both check that flag
 * before doing anything else, so once dismissed it never renders again
 * for that user on any of the plugin's screens -- not just for the
 * current page load or browser session.
 */
class WBCF_Upgrade_Notice {

	private static $instance = null;
	const PRO_URL              = 'https://www.webcasata.com/webcasata-content-filter/';
	const DISMISSED_META_KEY   = 'wbcf_upgrade_notice_dismissed';
	const DISMISS_NONCE_ACTION = 'wbcf_dismiss_upgrade_notice';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_render_banner' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::DISMISS_NONCE_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * True on the plugin's own admin screens (list, add/edit, settings --
	 * every one of them registers a screen id containing "wbcf-").
	 */
	private function is_own_screen() {
		$screen = get_current_screen();
		return $screen && false !== strpos( $screen->id, 'wbcf-' );
	}

	/**
	 * True once this admin user has clicked the dismiss control. Stored
	 * in user meta (not a transient or cookie) so it's genuinely
	 * permanent -- it survives cache clears, new browsers/devices, and
	 * doesn't expire.
	 */
	private function is_dismissed() {
		return (bool) get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );
	}

	public function maybe_enqueue_assets( $hook ) {
		unset( $hook );

		if ( ! $this->is_own_screen() || $this->is_dismissed() ) {
			return;
		}

		$suffix = wbcf_asset_suffix();

		wp_enqueue_script(
			'wbcf-upgrade-notice',
			WBCF_PLUGIN_URL . 'assets/js/wbcf-upgrade-notice' . $suffix . '.js',
			array(),
			WBCF_VERSION,
			true
		);

		wp_localize_script(
			'wbcf-upgrade-notice',
			'wbcfUpgradeNotice',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::DISMISS_NONCE_ACTION,
				'nonce'   => wp_create_nonce( self::DISMISS_NONCE_ACTION ),
			)
		);
	}

	public function maybe_render_banner() {
		if ( ! $this->is_own_screen() || $this->is_dismissed() ) {
			return;
		}
		?>
		<div class="wbcf-upgrade-banner">
			<div>
				<strong><?php esc_html_e( 'Want more templates, the Visual Layout Builder, and analytics?', 'webcasata-content-filter' ); ?></strong><br>
				<span><?php esc_html_e( 'Webcasata Content Filter Pro adds 25+ premium templates, a drag-and-drop layout builder, advanced query rules, click analytics, and import/export.', 'webcasata-content-filter' ); ?></span>
			</div>
			<a href="<?php echo esc_url( self::PRO_URL ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'See Pro Features →', 'webcasata-content-filter' ); ?>
			</a>
			<button type="button" class="wbcf-upgrade-banner-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice.', 'webcasata-content-filter' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<?php
	}

	/**
	 * AJAX endpoint the dismiss button calls. Verifies a nonce scoped
	 * only to this one action, confirms the user can actually reach the
	 * plugin's admin screens in the first place, then permanently
	 * records the dismissal in that user's meta. No other request data
	 * is read or trusted here.
	 */
	public function ajax_dismiss() {
		check_ajax_referer( self::DISMISS_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'webcasata-content-filter' ) ), 403 );
		}

		update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, 1 );

		wp_send_json_success();
	}
}
