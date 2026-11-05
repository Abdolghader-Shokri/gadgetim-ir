<?php
/**
 * One post in a list (index, archive, search): thumbnail, title, date, excerpt.
 * The "pages" builder may restyle or replace it.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );
?>
<li class="post-card" data-reveal>
	<article <?php post_class( 'post-card__in' ); ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="post-card__media"><?php the_post_thumbnail( 'gtm-card', [ 'loading' => 'lazy', 'alt' => '' ] ); ?></div>
		<?php endif; ?>
		<div class="post-card__body">
			<h2 class="post-card__t"><a href="<?php the_permalink(); ?>"><?php echo gadgetim_bidi( get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></a></h2>
			<?php if ( 'post' === get_post_type() ) : ?>
				<time class="post-card__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( gadgetim_date( (int) get_post_time( 'U', true ), 'medium' ) ); ?></time>
			<?php endif; ?>
			<p class="post-card__x"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 26, '…' ) ); ?></p>
		</div>
	</article>
</li>
