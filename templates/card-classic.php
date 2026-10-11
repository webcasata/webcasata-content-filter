<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Classic
 * Runs inside the WP_Query loop in WBCF_Render::render_results(), so all
 * the usual the_*() template tags are available.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this template is always include()'d from within WBCF_Render's rendering method (never loaded standalone), so every variable declared here is scoped to that method's call frame in actual execution, not the true PHP global scope.
$cats = get_the_category();
?>
<article <?php post_class( 'wbcf-card wbcf-card--classic' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="wbcf-card__thumb" href="<?php the_permalink(); ?>" tabindex="-1">
			<?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="wbcf-card__body">
		<?php if ( 'yes' === $settings['display']['date'] || ! empty( $cats ) ) : ?>
		<div class="wbcf-card__meta">
			<?php if ( 'yes' === $settings['display']['date'] ) : ?>
				<span class="wbcf-card__date"><?php echo esc_html( get_the_date() ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cats ) ) : ?>
				<span class="wbcf-card__category"><?php echo esc_html( $cats[0]->name ); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( 'yes' === $settings['display']['title'] ) : ?>
		<h3 class="wbcf-card__title">
			<a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a>
		</h3>
		<?php endif; ?>

		<?php if ( 'yes' === $settings['display']['excerpt'] ) : ?>
		<div class="wbcf-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></div>
		<?php endif; ?>

		<?php WBCF_Render::maybe_render_woocommerce_meta(); ?>

		<div class="wbcf-card__footer">
			<?php if ( 'yes' === $settings['display']['author'] ) : ?>
				<span class="wbcf-card__author"><?php echo esc_html( get_the_author() ); ?></span>
			<?php endif; ?>
			<?php if ( 'yes' === $settings['display']['comments'] ) : ?>
				<span class="wbcf-card__comments"><?php comments_number( '0', '1', '%' ); ?></span>
			<?php endif; ?>
			<a class="wbcf-card__readmore" href="<?php the_permalink(); ?>">
				<?php echo esc_html( WBCF_Render::get_readmore_text( $settings ) ); ?>
			</a>
		</div>
	</div>
</article>
