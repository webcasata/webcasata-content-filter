<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * @var array           $data
 * @var WP_Post_Type[]   $post_types
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is always include()'d from within a WBCF_Admin method (never loaded standalone), so every variable declared here is scoped to that method's call frame in actual execution, not the true PHP global scope.
$shortcode = $data['ID'] ? '[wbcf_filter id="' . $data['ID'] . '"]' : '';

$icon_map = array(
	'post'       => 'dashicons-admin-post',
	'page'       => 'dashicons-admin-page',
	'attachment' => 'dashicons-admin-media',
);

if ( ! function_exists( 'wbcf_admin_pt_icon' ) ) {
	function wbcf_admin_pt_icon( $pt, $icon_map ) {
		if ( isset( $icon_map[ $pt->name ] ) ) {
			return $icon_map[ $pt->name ];
		}
		if ( ! empty( $pt->menu_icon ) && 0 === strpos( $pt->menu_icon, 'dashicons-' ) ) {
			return $pt->menu_icon;
		}
		return 'dashicons-admin-post';
	}
}
?>
<div class="wrap wbcf-wrap">
	<h1><?php echo $data['ID'] ? esc_html__( 'Edit Filter', 'webcasata-content-filter' ) : esc_html__( 'Add New Filter', 'webcasata-content-filter' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: just shows a success notice after the redirect from save_filter(), which is itself nonce-protected; nothing here is processed or acted on. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Filter saved.', 'webcasata-content-filter' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbcf-form" id="wbcf-filter-form">
		<input type="hidden" name="action" value="wbcf_save_filter">
		<input type="hidden" name="filter_id" value="<?php echo esc_attr( $data['ID'] ); ?>">
		<?php wp_nonce_field( 'wbcf_save_filter' ); ?>

		<div class="wbcf-builder">
			<div class="wbcf-builder__main">

				<div class="wbcf-step">
					<div class="wbcf-step__num">1</div>
					<div class="wbcf-step__body">
						<h2><?php esc_html_e( 'Name your filter', 'webcasata-content-filter' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Internal label -- not shown publicly.', 'webcasata-content-filter' ); ?></p>
						<input type="text" id="wbcf_title" name="wbcf_title" class="regular-text"
							value="<?php echo esc_attr( $data['title'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Homepage News', 'webcasata-content-filter' ); ?>" required>
					</div>
				</div>

				<div class="wbcf-step">
					<div class="wbcf-step__num">2</div>
					<div class="wbcf-step__body">
						<h2><?php esc_html_e( 'Select post type', 'webcasata-content-filter' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Which content should this filter search?', 'webcasata-content-filter' ); ?></p>

						<div class="wbcf-pt-grid" id="wbcf-pt-grid">
							<?php foreach ( $post_types as $pt ) :
								if ( WBCF_POST_TYPE === $pt->name ) continue;
								$icon      = wbcf_admin_pt_icon( $pt, $icon_map );
								$is_active = ( $data['post_type'] === $pt->name );
							?>
								<label class="wbcf-pt-card<?php echo $is_active ? ' is-selected' : ''; ?>">
									<input type="radio" name="wbcf_post_type" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( $is_active ); ?>>
									<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
									<strong><?php echo esc_html( $pt->labels->singular_name ); ?></strong>
									<code><?php echo esc_html( $pt->name ); ?></code>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="wbcf-step">
					<div class="wbcf-step__num">3</div>
					<div class="wbcf-step__body">
						<h2><?php esc_html_e( 'Taxonomy', 'webcasata-content-filter' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Only taxonomies registered to the selected post type are listed. Tabs are generated from this taxonomy\'s terms.', 'webcasata-content-filter' ); ?></p>

						<select id="wbcf_taxonomy" name="wbcf_taxonomy" data-current="<?php echo esc_attr( $data['taxonomy'] ); ?>">
							<option value=""><?php esc_html_e( 'Loading…', 'webcasata-content-filter' ); ?></option>
						</select>
					</div>
				</div>

				<div class="wbcf-step">
					<div class="wbcf-step__num">4</div>
					<div class="wbcf-step__body">
						<h2><?php esc_html_e( 'Terms (Tabs)', 'webcasata-content-filter' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Check every term that should get its own tab. Drag support for ordering arrives in a later phase -- for now they display in this order.', 'webcasata-content-filter' ); ?></p>

						<div id="wbcf-terms-list" class="wbcf-tax-list" data-selected="<?php echo esc_attr( implode( ',', $data['terms'] ) ); ?>">
							<p class="description"><?php esc_html_e( 'Loading terms…', 'webcasata-content-filter' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wbcf-step">
					<div class="wbcf-step__num">5</div>
					<div class="wbcf-step__body">
						<h2><?php esc_html_e( 'Display options', 'webcasata-content-filter' ); ?></h2>

						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wbcf_posts_per_page"><?php esc_html_e( 'Posts Per Page', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<input type="number" id="wbcf_posts_per_page" name="wbcf_posts_per_page" min="1" max="50"
										value="<?php echo esc_attr( $data['posts_per_page'] ); ?>" class="small-text">
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Show "All" Tab', 'webcasata-content-filter' ); ?></th>
								<td>
									<label class="wbcf-toggle-label">
										<input type="checkbox" class="wbcf-toggle" id="wbcf_show_all_tab" name="wbcf_show_all_tab" value="yes" <?php checked( $data['show_all_tab'], 'yes' ); ?>>
										<?php esc_html_e( 'Show an "All" tab that combines every selected term', 'webcasata-content-filter' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th><label for="wbcf_default_tab"><?php esc_html_e( 'Default Active Tab', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<select id="wbcf_default_tab" name="wbcf_default_tab" data-current="<?php echo esc_attr( $data['default_tab'] ); ?>">
										<option value="all"><?php esc_html_e( 'All', 'webcasata-content-filter' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="wbcf_template"><?php esc_html_e( 'Card Template', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<select id="wbcf_template" name="wbcf_template">
										<?php
										$no_columns_templates = WBCF_Render::get_templates_without_columns();
										foreach ( WBCF_Render::get_template_choices() as $slug => $label ) :
											$no_columns = in_array( $slug, $no_columns_templates, true );
										?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $data['template'], $slug ); ?> data-no-columns="<?php echo $no_columns ? '1' : '0'; ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'More templates are on the way. Use the Styling step below to adjust colors, spacing, and columns for whichever template you pick.', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="wbcf_pagination"><?php esc_html_e( 'Pagination Style', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<select id="wbcf_pagination" name="wbcf_pagination">
										<option value="none" <?php selected( $data['pagination'], 'none' ); ?>><?php esc_html_e( 'None (just the first page)', 'webcasata-content-filter' ); ?></option>
										<option value="load_more" <?php selected( $data['pagination'], 'load_more' ); ?>><?php esc_html_e( 'Load More button', 'webcasata-content-filter' ); ?></option>
										<option value="infinite_scroll" <?php selected( $data['pagination'], 'infinite_scroll' ); ?>><?php esc_html_e( 'Infinite Scroll', 'webcasata-content-filter' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Only appears when a tab actually has more posts than "Posts Per Page".', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
						</table>

						<h4 class="wbcf-style-subhead"><?php esc_html_e( 'Card Elements', 'webcasata-content-filter' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Turn any of these off to hide them from every card, on every tab, for this filter. A template that never showed a given element (e.g. News has no comment count) simply ignores that toggle.', 'webcasata-content-filter' ); ?></p>

						<div class="wbcf-toggle-grid">
							<label class="wbcf-toggle-label wbcf-toggle-card">
								<input type="checkbox" class="wbcf-toggle" name="wbcf_display[title]" value="yes" <?php checked( $data['display']['title'], 'yes' ); ?>>
								<span><?php esc_html_e( 'Title', 'webcasata-content-filter' ); ?></span>
							</label>
							<label class="wbcf-toggle-label wbcf-toggle-card">
								<input type="checkbox" class="wbcf-toggle" name="wbcf_display[date]" value="yes" <?php checked( $data['display']['date'], 'yes' ); ?>>
								<span><?php esc_html_e( 'Date', 'webcasata-content-filter' ); ?></span>
							</label>
							<label class="wbcf-toggle-label wbcf-toggle-card">
								<input type="checkbox" class="wbcf-toggle" name="wbcf_display[author]" value="yes" <?php checked( $data['display']['author'], 'yes' ); ?>>
								<span><?php esc_html_e( 'Username / Author', 'webcasata-content-filter' ); ?></span>
							</label>
							<label class="wbcf-toggle-label wbcf-toggle-card">
								<input type="checkbox" class="wbcf-toggle" name="wbcf_display[excerpt]" value="yes" <?php checked( $data['display']['excerpt'], 'yes' ); ?>>
								<span><?php esc_html_e( 'Excerpt', 'webcasata-content-filter' ); ?></span>
							</label>
							<label class="wbcf-toggle-label wbcf-toggle-card">
								<input type="checkbox" class="wbcf-toggle" name="wbcf_display[comments]" value="yes" <?php checked( $data['display']['comments'], 'yes' ); ?>>
								<span><?php esc_html_e( 'Comment Count', 'webcasata-content-filter' ); ?></span>
							</label>
						</div>

						<h4 class="wbcf-style-subhead"><?php esc_html_e( 'Button Text', 'webcasata-content-filter' ); ?></h4>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wbcf_readmore_text"><?php esc_html_e( 'Card Button Label', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<input type="text" id="wbcf_readmore_text" name="wbcf_readmore_text" class="regular-text"
										maxlength="50"
										value="<?php echo esc_attr( $data['readmore_text'] ); ?>"
										placeholder="<?php esc_attr_e( 'Read More', 'webcasata-content-filter' ); ?>">
									<p class="description"><?php esc_html_e( 'Shown on every card for this filter, e.g. "Read more about doctor" or "Visit Hospital". Leave blank to use the default "Read More".', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<?php
				/**
				 * Fires right after Display Options (which includes Card
				 * Template) and before Styling -- the natural place for
				 * template-specific admin UI that depends on which Card
				 * Template was picked above. Pro's Visual Layout Builder
				 * injects its step here, so it appears immediately below
				 * the selector that controls whether it's relevant, rather
				 * than at the bottom of the whole form.
				 */
				do_action( 'wbcf_admin_edit_after_template_field', $data );
				?>

				<div class="wbcf-step">
					<div class="wbcf-step__num">6</div>
					<div class="wbcf-step__body">
						<h2><?php esc_html_e( 'Styling', 'webcasata-content-filter' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Leave any field blank/default to keep the template\'s normal look.', 'webcasata-content-filter' ); ?></p>

						<h4 class="wbcf-style-subhead"><?php esc_html_e( 'Tabs', 'webcasata-content-filter' ); ?></h4>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wbcf_style_tab_color"><?php esc_html_e( 'Color', 'webcasata-content-filter' ); ?></label></th>
								<td><input type="text" id="wbcf_style_tab_color" name="wbcf_style[tab_color]" class="wbcf-color-field" value="<?php echo esc_attr( $data['styles']['tab_color'] ); ?>" data-default-color="#444444"></td>
							</tr>
							<tr>
								<th><label for="wbcf_style_tab_active_color"><?php esc_html_e( 'Active Color', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<input type="text" id="wbcf_style_tab_active_color" name="wbcf_style[tab_active_color]" class="wbcf-color-field" value="<?php echo esc_attr( $data['styles']['tab_active_color'] ); ?>" data-default-color="#2271b1">
									<p class="description"><?php esc_html_e( 'Also tints Read More links and other accents to match.', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="wbcf_style_tab_hover_color"><?php esc_html_e( 'Hover', 'webcasata-content-filter' ); ?></label></th>
								<td><input type="text" id="wbcf_style_tab_hover_color" name="wbcf_style[tab_hover_color]" class="wbcf-color-field" value="<?php echo esc_attr( $data['styles']['tab_hover_color'] ); ?>" data-default-color="#2271b1"></td>
							</tr>
							<tr>
								<th><label for="wbcf_style_tab_radius"><?php esc_html_e( 'Border Radius', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<input type="number" id="wbcf_style_tab_radius" name="wbcf_style[tab_radius]" min="0" max="999" class="small-text" value="<?php echo esc_attr( $data['styles']['tab_radius'] ); ?>"> px
									<p class="description"><?php esc_html_e( '0 keeps the underline style. Anything above 0 switches tabs to filled, rounded buttons.', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
						</table>

						<h4 class="wbcf-style-subhead"><?php esc_html_e( 'Cards', 'webcasata-content-filter' ); ?></h4>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wbcf_style_card_radius"><?php esc_html_e( 'Border Radius', 'webcasata-content-filter' ); ?></label></th>
								<td><input type="number" id="wbcf_style_card_radius" name="wbcf_style[card_radius]" min="0" max="60" class="small-text" placeholder="6" value="<?php echo esc_attr( $data['styles']['card_radius'] ); ?>"> px</td>
							</tr>
							<tr>
								<th><label for="wbcf_style_card_shadow"><?php esc_html_e( 'Shadow', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<select id="wbcf_style_card_shadow" name="wbcf_style[card_shadow]">
										<option value="default" <?php selected( $data['styles']['card_shadow'], 'default' ); ?>><?php esc_html_e( 'Default', 'webcasata-content-filter' ); ?></option>
										<option value="none" <?php selected( $data['styles']['card_shadow'], 'none' ); ?>><?php esc_html_e( 'None', 'webcasata-content-filter' ); ?></option>
										<option value="soft" <?php selected( $data['styles']['card_shadow'], 'soft' ); ?>><?php esc_html_e( 'Soft', 'webcasata-content-filter' ); ?></option>
										<option value="strong" <?php selected( $data['styles']['card_shadow'], 'strong' ); ?>><?php esc_html_e( 'Strong', 'webcasata-content-filter' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="wbcf_style_card_gap"><?php esc_html_e( 'Gap', 'webcasata-content-filter' ); ?></label></th>
								<td><input type="number" id="wbcf_style_card_gap" name="wbcf_style[card_gap]" min="0" max="80" class="small-text" placeholder="20" value="<?php echo esc_attr( $data['styles']['card_gap'] ); ?>"> px</td>
							</tr>
							<tr id="wbcf-style-columns-row">
								<th><label for="wbcf_style_card_columns"><?php esc_html_e( 'Columns', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<select id="wbcf_style_card_columns" name="wbcf_style[card_columns]">
										<option value="auto" <?php selected( $data['styles']['card_columns'], 'auto' ); ?>><?php esc_html_e( 'Auto (fills available width)', 'webcasata-content-filter' ); ?></option>
										<option value="2" <?php selected( $data['styles']['card_columns'], '2' ); ?>>2</option>
										<option value="3" <?php selected( $data['styles']['card_columns'], '3' ); ?>>3</option>
										<option value="4" <?php selected( $data['styles']['card_columns'], '4' ); ?>>4</option>
										<option value="5" <?php selected( $data['styles']['card_columns'], '5' ); ?>>5</option>
									</select>
									<p class="description"><?php esc_html_e( 'Fixed column counts still wrap responsively on smaller screens.', 'webcasata-content-filter' ); ?></p>
									<p class="description wbcf-style-columns-na" style="display:none;"><?php esc_html_e( 'This template always shows one full-width entry per row, so Columns doesn\'t apply.', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
						</table>

						<h4 class="wbcf-style-subhead"><?php esc_html_e( 'Typography', 'webcasata-content-filter' ); ?></h4>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wbcf_style_title_size"><?php esc_html_e( 'Font Size', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<select id="wbcf_style_title_size" name="wbcf_style[title_size]">
										<option value="small" <?php selected( $data['styles']['title_size'], 'small' ); ?>><?php esc_html_e( 'Small', 'webcasata-content-filter' ); ?></option>
										<option value="medium" <?php selected( $data['styles']['title_size'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'webcasata-content-filter' ); ?></option>
										<option value="large" <?php selected( $data['styles']['title_size'], 'large' ); ?>><?php esc_html_e( 'Large', 'webcasata-content-filter' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="wbcf_style_title_color"><?php esc_html_e( 'Title Color', 'webcasata-content-filter' ); ?></label></th>
								<td><input type="text" id="wbcf_style_title_color" name="wbcf_style[title_color]" class="wbcf-color-field" value="<?php echo esc_attr( $data['styles']['title_color'] ); ?>" data-default-color="#1d2327"></td>
							</tr>
							<tr>
								<th><label for="wbcf_style_meta_color"><?php esc_html_e( 'Meta Color', 'webcasata-content-filter' ); ?></label></th>
								<td>
									<input type="text" id="wbcf_style_meta_color" name="wbcf_style[meta_color]" class="wbcf-color-field" value="<?php echo esc_attr( $data['styles']['meta_color'] ); ?>" data-default-color="#777777">
									<p class="description"><?php esc_html_e( 'Controls dates, categories, author, and footer text.', 'webcasata-content-filter' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<?php
				/**
				 * Fires after the built-in steps (Title, Post Type, Taxonomy,
				 * Terms, Display Options, Styling), still inside the same
				 * <form> and .wbcf-builder__main column. Pro's Visual Layout
				 * Builder injects its step here rather than needing this
				 * file edited -- $data is the same array used to render
				 * the steps above (includes 'ID' for loading saved state).
				 */
				do_action( 'wbcf_admin_edit_after_steps', $data );
				?>

			</div><!-- .wbcf-builder__main -->

			<div class="wbcf-builder__side">
				<div class="wbcf-side-box">
					<h3><?php esc_html_e( 'Publish', 'webcasata-content-filter' ); ?></h3>
					<?php submit_button( $data['ID'] ? __( 'Update Filter', 'webcasata-content-filter' ) : __( 'Create Filter', 'webcasata-content-filter' ), 'primary', 'submit', false ); ?>
					<?php if ( $data['ID'] ) : ?>
						<p class="wbcf-published-status">● <?php esc_html_e( 'Published', 'webcasata-content-filter' ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( $shortcode ) : ?>
				<div class="wbcf-side-box">
					<h3><?php esc_html_e( 'Shortcode', 'webcasata-content-filter' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Paste this anywhere on your site.', 'webcasata-content-filter' ); ?></p>
					<input type="text" readonly class="wbcf-shortcode-box" value="<?php echo esc_attr( $shortcode ); ?>" onclick="this.select();">
				</div>
				<?php endif; ?>

				<div class="wbcf-side-box">
					<h3><?php esc_html_e( 'Summary', 'webcasata-content-filter' ); ?></h3>
					<ul class="wbcf-summary-list" id="wbcf-summary-list">
						<li><span><?php esc_html_e( 'Post type', 'webcasata-content-filter' ); ?></span><strong id="wbcf-summary-pt"><?php echo esc_html( $data['post_type'] ); ?></strong></li>
						<li><span><?php esc_html_e( 'Taxonomy', 'webcasata-content-filter' ); ?></span><strong id="wbcf-summary-taxonomy">&mdash;</strong></li>
						<li><span><?php esc_html_e( 'Tabs (terms)', 'webcasata-content-filter' ); ?></span><strong id="wbcf-summary-terms"><?php echo esc_html( count( $data['terms'] ) ); ?></strong></li>
						<li><span><?php esc_html_e( 'Posts / tab', 'webcasata-content-filter' ); ?></span><strong id="wbcf-summary-ppp"><?php echo esc_html( $data['posts_per_page'] ); ?></strong></li>
					</ul>
				</div>
			</div><!-- .wbcf-builder__side -->
		</div><!-- .wbcf-builder -->
	</form>
</div>
