<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Minimal
 * No thumbnail, no shadows/borders -- just clean typography and a thin
 * divider. Built for text-heavy content where the words should carry
 * the design.
 */
?>
<article <?php post_class( 'wbcf-card wbcf-card--minimal' ); ?>>
	<?php if ( 'yes' === $settings['display']['date'] ) : ?>
	<div class="wbcf-card__meta">
		<span class="wbcf-card__date"><?php echo esc_html( get_the_date() ); ?></span>
	</div>
	<?php endif; ?>

	<?php if ( 'yes' === $settings['display']['title'] ) : ?>
	<h3 class="wbcf-card__title">
		<a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a>
	</h3>
	<?php endif; ?>

	<?php WBCF_Render::maybe_render_woocommerce_meta(); ?>

	<div class="wbcf-card__footer">
		<?php if ( 'yes' === $settings['display']['author'] ) : ?>
			<span class="wbcf-card__author"><?php echo esc_html( get_the_author() ); ?></span>
		<?php endif; ?>
		<a class="wbcf-card__readmore" href="<?php the_permalink(); ?>">
			<?php echo esc_html( WBCF_Render::get_readmore_text( $settings ) ); ?>
		</a>
	</div>
</article>
