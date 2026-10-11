<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var bool $delete_data_on_uninstall */
?>
<div class="wrap wbcf-wrap">
	<h1><?php esc_html_e( 'Settings', 'webcasata-content-filter' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: just shows a success notice after the redirect from save_settings(), which is itself nonce-protected; nothing here is processed or acted on. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'webcasata-content-filter' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wbcf_save_settings">
		<?php wp_nonce_field( 'wbcf_save_settings' ); ?>

		<div class="wbcf-side-box wbcf-settings-box">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'On plugin deletion', 'webcasata-content-filter' ); ?></th>
					<td>
						<label class="wbcf-toggle-label">
							<input type="checkbox" class="wbcf-toggle" name="wbcf_delete_data_on_uninstall" value="yes" <?php checked( $delete_data_on_uninstall ); ?>>
							<?php esc_html_e( 'Delete all filters and settings when this plugin is deleted', 'webcasata-content-filter' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Off by default: if you delete the plugin (e.g. to reinstall an update), every filter you\'ve built stays in the database and comes back as soon as the plugin is reactivated. Turn this on only if you want a full, permanent cleanup when the plugin is removed.', 'webcasata-content-filter' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'webcasata-content-filter' ) ); ?>
		</div>
	</form>
</div>
