<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Magazine
 * Editorial style -- full-bleed thumbnail with title/meta overlaid at
 * the bottom over a gradient scrim. Falls back to a solid dark panel
 * when there's no featured image.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this template is always include()'d from within WBCF_Render's rendering method (never loaded standalone), so every variable declared here is scoped to that method's call frame in actual execution, not the true PHP global scope.
$cats = get_the_category();
?>
<article <?php post_class( 'wbcf-card wbcf-card--magazine' ); ?>>
	<a class="wbcf-card__link" href="<?php the_permalink(); ?>">
		<div class="wbcf-card__media">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
			<?php endif; ?>
			<div class="wbcf-card__scrim">
				<?php if ( ! empty( $cats ) ) : ?>
					<span class="wbcf-card__badge"><?php echo esc_html( $cats[0]->name ); ?></span>
				<?php endif; ?>
				<?php if ( 'yes' === $settings['display']['title'] ) : ?>
					<h3 class="wbcf-card__title"><?php echo esc_html( get_the_title() ); ?></h3>
				<?php endif; ?>
				<?php if ( 'yes' === $settings['display']['date'] || 'yes' === $settings['display']['author'] ) : ?>
					<div class="wbcf-card__meta-row">
						<?php if ( 'yes' === $settings['display']['date'] ) : ?>
							<span><?php echo esc_html( get_the_date() ); ?></span>
						<?php endif; ?>
						<?php if ( 'yes' === $settings['display']['author'] ) : ?>
							<span><?php echo esc_html( get_the_author() ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</a>
	<?php // Outside the card-wide <a> on purpose -- the Add to Cart button is itself interactive and can't nest inside another link. ?>
	<?php WBCF_Render::maybe_render_woocommerce_meta(); ?>
</article>
