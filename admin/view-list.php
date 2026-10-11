<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var WP_Post[] $filters */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is always include()'d from within a WBCF_Admin method (never loaded standalone), so every variable declared here is scoped to that method's call frame in actual execution, not the true PHP global scope.
?>
<div class="wrap wbcf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Webcasata Content Filter', 'webcasata-content-filter' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcf-add-filter' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Filter', 'webcasata-content-filter' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: just shows a success notice after the redirect from the delete action, which is itself nonce-protected; nothing here is processed or acted on. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Filter deleted.', 'webcasata-content-filter' ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $filters ) ) : ?>
		<div class="wbcf-empty-state">
			<p><?php esc_html_e( 'No filters yet. Create your first one to get a shortcode you can drop into any post or page.', 'webcasata-content-filter' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcf-add-filter' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create New Filter', 'webcasata-content-filter' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'webcasata-content-filter' ); ?></th>
					<th><?php esc_html_e( 'Post Type', 'webcasata-content-filter' ); ?></th>
					<th><?php esc_html_e( 'Taxonomy', 'webcasata-content-filter' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'webcasata-content-filter' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'webcasata-content-filter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $filters as $filter ) :
					$edit_url   = admin_url( 'admin.php?page=wbcf-add-filter&filter_id=' . $filter->ID );
					$delete_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=wbcf_delete_filter&filter_id=' . $filter->ID ),
						'wbcf_delete_filter_' . $filter->ID
					);
					$post_type  = get_post_meta( $filter->ID, '_wbcf_post_type', true );
					$taxonomy_slug = get_post_meta( $filter->ID, '_wbcf_taxonomy', true );
					$tax_obj    = $taxonomy_slug ? get_taxonomy( $taxonomy_slug ) : false;
					$tax_label  = $tax_obj ? $tax_obj->labels->name : '—';
					$shortcode  = '[wbcf_filter id="' . $filter->ID . '"]';
				?>
				<tr>
					<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $filter->post_title ); ?></a></strong></td>
					<td><?php echo esc_html( $post_type ); ?></td>
					<td><?php echo esc_html( $tax_label ); ?></td>
					<td><code class="wbcf-shortcode-inline"><?php echo esc_html( $shortcode ); ?></code></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'webcasata-content-filter' ); ?></a>
						|
						<a href="<?php echo esc_url( $delete_url ); ?>" class="wbcf-delete-link" onclick="return confirm('<?php echo esc_js( __( 'Delete this filter? This cannot be undone.', 'webcasata-content-filter' ) ); ?>');">
							<?php esc_html_e( 'Delete', 'webcasata-content-filter' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
