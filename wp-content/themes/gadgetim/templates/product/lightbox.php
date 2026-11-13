<?php
/**
 * Full-screen gallery (<dialog>, opened through drawer.js by gallery.js): counter, title, zoom and
 * close buttons, a swipe track (pinch, double-tap, wheel and keyboard zoom, pan while zoomed,
 * swipe down to close), previous/next arrows and thumbnails. Images load only when the dialog opens.
 *
 * @package Gadgetim
 *
 * @var array $args { product: WC_Product }
 */

declare( strict_types=1 );

$gadgetim_product = $args['product'] ?? null;
if ( ! $gadgetim_product instanceof WC_Product ) {
	return;
}

$gadgetim_items = array_values( array_filter( gadgetim_pdp_gallery_items( $gadgetim_product ), static fn( array $item ): bool => 'image' === ( $item['type'] ?? '' ) ) );
if ( ! $gadgetim_items ) {
	return;
}
$gadgetim_count = count( $gadgetim_items );
$gadgetim_title = gadgetim_short_title( $gadgetim_product );
// The title below is a paragraph, not a heading: aria-labelledby already names the dialog, and an
// H2 here repeated the page's H1 on every product page (seo-engineer audit, 2026-11-13).
?>
<dialog class="lb" id="pdp-lb" aria-labelledby="pdp-lb-t" data-drawer data-lb data-count="<?php echo esc_attr( (string) $gadgetim_count ); ?>">
	<div class="lb__bar">
		<p class="lb__count" aria-hidden="true"><bdi data-lb-count><?php echo esc_html( gadgetim_number( 1 ) . ' / ' . gadgetim_number( $gadgetim_count ) ); ?></bdi></p>
		<p class="lb__t" id="pdp-lb-t"><?php echo gadgetim_pdp_text( $gadgetim_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></p>
		<div class="lb__tools">
			<button class="lb__btn lb__btn--zoom" type="button" data-lb-zoom="-1" aria-label="<?php esc_attr_e( 'Zoom out', 'gadgetim' ); ?>" disabled><?php gadgetim_the_icon( 'minus' ); ?></button>
			<button class="lb__btn lb__btn--zoom" type="button" data-lb-zoom="1" aria-label="<?php esc_attr_e( 'Zoom in', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'plus' ); ?></button>
			<button class="lb__btn lb__btn--close" type="button" data-close aria-label="<?php esc_attr_e( 'Close', 'gadgetim' ); ?>" autofocus><?php gadgetim_the_icon( 'close' ); ?></button>
		</div>
	</div>

	<div class="lb__stage" data-lb-stage>
		<ul class="lb__track" data-lb-track>
			<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
				<?php /* translators: 1: image number, 2: number of images */ ?>
				<li class="lb__slide<?php echo 0 === $gadgetim_i ? ' is-active' : ''; ?>" data-lb-slide="<?php echo esc_attr( (string) $gadgetim_i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Image %1$s of %2$s', 'gadgetim' ), gadgetim_number( $gadgetim_i + 1 ), gadgetim_number( $gadgetim_count ) ) ); ?>"<?php echo 0 === $gadgetim_i ? '' : ' aria-hidden="true"'; ?>>
					<?php
					echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						(int) $gadgetim_item['id'],
						'full',
						[
							'sizes'   => 'pdp-lb',
							'loading' => 'lazy',
							'class'   => 'lb__img',
							'alt'     => $gadgetim_title,
							'lqip'    => false,
						]
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $gadgetim_count > 1 ) : ?>
			<button class="lb__nav lb__nav--prev" type="button" data-lb-go="-1" aria-label="<?php esc_attr_e( 'Previous image', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'chev-r', 'ic--dir' ); ?></button>
			<button class="lb__nav lb__nav--next" type="button" data-lb-go="1" aria-label="<?php esc_attr_e( 'Next image', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></button>
		<?php endif; ?>
		<p class="lb__hint" aria-hidden="true"><span class="lb__hint-touch"><?php esc_html_e( 'Double-tap or pinch to zoom', 'gadgetim' ); ?></span><span class="lb__hint-mouse"><?php esc_html_e( 'Double-click or scroll to zoom', 'gadgetim' ); ?></span></p>
	</div>

	<?php if ( $gadgetim_count > 1 ) : ?>
		<div class="lb__thumbs" role="group" aria-label="<?php esc_attr_e( 'Choose an image', 'gadgetim' ); ?>">
			<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
				<?php /* translators: 1: image number, 2: number of images */ ?>
				<button class="lb__thumb<?php echo 0 === $gadgetim_i ? ' is-active' : ''; ?>" type="button" data-lb-thumb="<?php echo esc_attr( (string) $gadgetim_i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Image %1$s of %2$s', 'gadgetim' ), gadgetim_number( $gadgetim_i + 1 ), gadgetim_number( $gadgetim_count ) ) ); ?>"<?php echo 0 === $gadgetim_i ? ' aria-current="true"' : ''; ?>>
					<?php
					echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						(int) $gadgetim_item['id'],
						'gtm-thumb',
						[
							'sizes'   => 'mini',
							'loading' => 'lazy',
							'alt'     => '',
							'lqip'    => false,
						]
					);
					?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</dialog>
