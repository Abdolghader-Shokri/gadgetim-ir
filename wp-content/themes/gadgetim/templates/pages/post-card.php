<?php
/**
 * Article card (blog lists, related posts, search results): image (or a gradient placeholder),
 * category chip, title (the whole card is the link), excerpt, Jalali date and reading time.
 *
 * Args: post (WP_Post|int), variant (default|feature), index (stagger, placeholder tone),
 *       heading (h2|h3, default h2), reveal (bool, default true), eager (bool: LCP candidate).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_post = get_post( $args['post'] ?? null );
if ( ! $gadgetim_post instanceof WP_Post ) {
	return;
}
$gadgetim_variant = 'feature' === ( $args['variant'] ?? '' ) ? 'feature' : 'default';
$gadgetim_index   = (int) ( $args['index'] ?? 0 );
$gadgetim_tag     = in_array( $args['heading'] ?? 'h2', [ 'h2', 'h3' ], true ) ? (string) ( $args['heading'] ?? 'h2' ) : 'h2';
$gadgetim_reveal  = false !== ( $args['reveal'] ?? true );
$gadgetim_eager   = ! empty( $args['eager'] );
$gadgetim_cats    = 'post' === $gadgetim_post->post_type ? gadgetim_pages_post_categories( $gadgetim_post ) : [];
$gadgetim_thumb   = (int) get_post_thumbnail_id( $gadgetim_post );
$gadgetim_minutes = gadgetim_pages_reading_minutes( (string) $gadgetim_post->post_content );
$gadgetim_excerpt = has_excerpt( $gadgetim_post ) ? (string) $gadgetim_post->post_excerpt : (string) $gadgetim_post->post_content;
$gadgetim_excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $gadgetim_excerpt ) ), 'feature' === $gadgetim_variant ? 34 : 22, '…' );
$gadgetim_tones   = [ 'violet', 'cool', 'warm', 'green', 'pink', 'gold' ];
$gadgetim_icons   = [ 'sparkle', 'bolt', 'headphones', 'mobile', 'gift', 'watch' ];
$gadgetim_attrs   = [
	'class'       => 'bcard bcard--' . $gadgetim_variant,
	'data-reveal' => $gadgetim_reveal ? true : null,
	'style'       => $gadgetim_reveal ? '--i:' . min( 7, $gadgetim_index ) : null,
];
?>
<li<?php echo gadgetim_attr( $gadgetim_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
	<article class="bcard__in">
		<div class="bcard__media">
			<?php if ( $gadgetim_thumb > 0 ) : ?>
				<?php
				echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					$gadgetim_thumb,
					'large',
					[
						'class'         => 'bcard__img',
						'alt'           => '',
						'sizes'         => 'post-card',
						'loading'       => $gadgetim_eager ? 'eager' : 'lazy',
						'fetchpriority' => $gadgetim_eager ? 'high' : null,
					]
				);
				?>
			<?php else : ?>
				<span class="bcard__ph bcard__ph--<?php echo esc_attr( $gadgetim_tones[ $gadgetim_index % count( $gadgetim_tones ) ] ); ?>" aria-hidden="true">
					<i class="bcard__orb"></i><i class="bcard__orb bcard__orb--2"></i>
					<?php gadgetim_the_icon( $gadgetim_icons[ $gadgetim_index % count( $gadgetim_icons ) ] ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $gadgetim_cats ) : ?>
				<span class="bcard__cat"><?php echo esc_html( $gadgetim_cats[0]->name ); ?></span>
			<?php elseif ( 'page' === $gadgetim_post->post_type ) : ?>
				<span class="bcard__cat"><?php esc_html_e( 'Page', 'gadgetim' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="bcard__body">
			<<?php echo esc_attr( $gadgetim_tag ); ?> class="bcard__t"><a href="<?php echo esc_url( (string) get_permalink( $gadgetim_post ) ); ?>"><?php echo gadgetim_bidi( get_the_title( $gadgetim_post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></a></<?php echo esc_attr( $gadgetim_tag ); ?>>
			<?php if ( '' !== $gadgetim_excerpt ) : ?>
				<p class="bcard__x"><?php echo esc_html( $gadgetim_excerpt ); ?></p>
			<?php endif; ?>
			<p class="bcard__meta">
				<span><?php echo gadgetim_pages_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><time datetime="<?php echo esc_attr( (string) get_the_date( DATE_W3C, $gadgetim_post ) ); ?>"><?php echo esc_html( gadgetim_date( (int) get_post_time( 'U', true, $gadgetim_post ), 'medium' ) ); ?></time></span>
				<span><?php gadgetim_the_icon( 'clock' ); ?><?php echo esc_html( gadgetim_pages_minutes_label( $gadgetim_minutes ) ); ?></span>
			</p>
		</div>
	</article>
</li>
