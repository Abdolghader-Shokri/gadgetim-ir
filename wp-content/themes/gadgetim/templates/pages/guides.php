<?php
/**
 * Buying-guide cards (SEO run of 2026-11-20): the published guide pages of the current language
 * (gadgetim_guides(), inc/pages.php), each with its excerpt. Printed at the end of every guide
 * (the other guides) and on the home page (templates/home/guides.php), so each guide is linked
 * from the strongest page and from its siblings. Prints nothing when there is no guide.
 *
 * Args: exclude (page ID), title, sub, id, class, limit (default 6).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_guides = array_slice( gadgetim_guides( (int) ( $args['exclude'] ?? 0 ) ), 0, max( 1, (int) ( $args['limit'] ?? 6 ) ) );
if ( ! $gadgetim_guides ) {
	return;
}
$gadgetim_id = (string) ( $args['id'] ?? 'guides-t' );
?>
<section class="<?php echo esc_attr( trim( 'guides sect ' . (string) ( $args['class'] ?? '' ) ) ); ?>" aria-labelledby="<?php echo esc_attr( $gadgetim_id ); ?>">
	<?php
	gadgetim_the_section_head(
		[
			'title' => (string) ( $args['title'] ?? __( 'Buying guides', 'gadgetim' ) ),
			'sub'   => (string) ( $args['sub'] ?? __( 'Clear answers before you buy', 'gadgetim' ) ),
			'id'    => $gadgetim_id,
			'icon'  => 'sparkle',
			'tone'  => 'pink',
		]
	);
	?>
	<ul class="guides__list">
		<?php foreach ( $gadgetim_guides as $gadgetim_i => $gadgetim_guide ) : ?>
			<li class="guide-card" data-reveal style="--i:<?php echo esc_attr( (string) $gadgetim_i ); ?>">
				<a class="guide-card__link" href="<?php echo esc_url( (string) get_permalink( $gadgetim_guide ) ); ?>">
					<span class="guide-card__t"><?php echo gadgetim_bidi( get_the_title( $gadgetim_guide ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></span>
					<?php if ( has_excerpt( $gadgetim_guide ) ) : ?>
						<span class="guide-card__p"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $gadgetim_guide ) ) ); ?></span>
					<?php endif; ?>
					<span class="guide-card__more"><?php esc_html_e( 'Read the guide', 'gadgetim' ); ?><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
