<?php
/**
 * Single Product Image
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/product-image.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim Aurora gallery: a white plate with a soft aurora glow behind it, a native scroll-snap
 * track of slides (swipe on phones, dots + counter), thumbnails from 768 px, hover zoom with a fine
 * pointer, and a full-screen <dialog> lightbox (templates/product/lightbox.php, gallery.js). The first
 * image is the LCP element: eager, fetchpriority="high", same size/sizes as the <head> preload.
 * WooCommerce's hooks and filters stay (woocommerce_single_product_image_thumbnail_html,
 * woocommerce_product_thumbnails, .woocommerce-product-gallery__image / .wp-post-image for the
 * variation image swap). Without JS the track still swipes, thumbnails jump to their slide and a
 * tap opens the full-size file.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.1.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$gadgetim_items   = gadgetim_pdp_gallery_items( $product );
$gadgetim_count   = count( $gadgetim_items );
$gadgetim_title   = gadgetim_short_title( $product );
$gadgetim_columns = (int) apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$gadgetim_classes = (array) apply_filters(
	'woocommerce_single_product_image_gallery_classes',
	[
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $gadgetim_count ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $gadgetim_columns ),
		'images',
	]
);
$gadgetim_contact = gadgetim_contact_mode( $product );
$gadgetim_in      = $product->is_in_stock();
$gadgetim_off     = ( $gadgetim_in && ! $gadgetim_contact ) ? gadgetim_product_discount( $product ) : 0;
$gadgetim_sticker = $gadgetim_off >= gadgetim_sale_thresholds()['sticker'] && $gadgetim_off > 0;
$gadgetim_new     = $gadgetim_in && gadgetim_is_new( $product );
?>
<div class="gal <?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $gadgetim_classes ) ) ); ?>" data-gallery data-count="<?php echo esc_attr( (string) $gadgetim_count ); ?>" data-columns="<?php echo esc_attr( (string) absint( $gadgetim_columns ) ); ?>">
	<div class="gal__stage">
		<div class="gal__glow" aria-hidden="true"><i class="gal__b gal__b--1"></i><i class="gal__b gal__b--2"></i><i class="gal__b gal__b--3"></i></div>
		<div class="gal__plate woocommerce-product-gallery__wrapper">
			<ul class="gal__track" data-gal-track aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product title */ __( 'Images of %s', 'gadgetim' ), $gadgetim_title ) ); ?>" tabindex="-1">
				<?php if ( $gadgetim_count ) : ?>
					<?php $gadgetim_img_n = 0; // Index among the images only (the lightbox skips videos). ?>
					<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
						<?php
						$gadgetim_first = 0 === $gadgetim_i;
						$gadgetim_id    = (int) $gadgetim_item['id'];
						$gadgetim_label = sprintf( /* translators: 1: image number, 2: number of images */ __( 'Image %1$s of %2$s', 'gadgetim' ), gadgetim_number( $gadgetim_i + 1 ), gadgetim_number( $gadgetim_count ) );
						ob_start();
						if ( 'video' === $gadgetim_item['type'] ) {
							$gadgetim_src    = (string) wp_get_attachment_url( $gadgetim_id );
							$gadgetim_poster = ! empty( $gadgetim_item['poster'] ) ? wp_get_attachment_image_url( (int) $gadgetim_item['poster'], 'gtm-hero' ) : '';
							if ( '' !== $gadgetim_src ) {
								echo '<video class="gal__video" src="' . esc_url( $gadgetim_src ) . '"' . ( $gadgetim_poster ? ' poster="' . esc_url( (string) $gadgetim_poster ) . '"' : '' ) . ' controls playsinline preload="none" width="800" height="800"></video>';
							}
						} else {
							$gadgetim_full = wp_get_attachment_image_src( $gadgetim_id, 'full' );
							$gadgetim_alt  = trim( (string) get_post_meta( $gadgetim_id, '_wp_attachment_image_alt', true ) );
							echo '<a class="gal__open" href="' . esc_url( $gadgetim_full ? (string) $gadgetim_full[0] : '' ) . '" data-lb-open="' . esc_attr( (string) $gadgetim_img_n++ ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: "Image 1 of 3" */ __( '%s — open full screen', 'gadgetim' ), $gadgetim_label ) ) . '">';
							echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
								$gadgetim_id,
								'gtm-hero',
								[
									'sizes'         => 'pdp',
									'loading'       => $gadgetim_first ? 'eager' : 'lazy',
									'fetchpriority' => $gadgetim_first ? 'high' : null,
									'class'         => 'gal__img' . ( $gadgetim_first ? ' wp-post-image' : '' ),
									'alt'           => '' !== $gadgetim_alt ? $gadgetim_alt : ( $gadgetim_first ? $gadgetim_title : $gadgetim_title . ' — ' . $gadgetim_label ),
									// What add-to-cart-variation.js and zoom/lightbox plugins read (wc_get_gallery_image_html()).
									'attrs'         => $gadgetim_full ? [
										'data-src'                => (string) $gadgetim_full[0],
										'data-large_image'        => (string) $gadgetim_full[0],
										'data-large_image_width'  => (string) $gadgetim_full[1],
										'data-large_image_height' => (string) $gadgetim_full[2],
									] : [],
								]
							);
							echo '</a>';
						}
						$gadgetim_html = (string) ob_get_clean();
						if ( 'video' === $gadgetim_item['type'] ) {
							/** This filter is documented in woocommerce/templates/single-product/product-image.php */
							$gadgetim_html = (string) apply_filters( 'woocommerce_single_product_video_thumbnail_html', $gadgetim_html, $gadgetim_id, $gadgetim_item );
						} else {
							/** This filter is documented in woocommerce/templates/single-product/product-image.php */
							$gadgetim_html = (string) apply_filters( 'woocommerce_single_product_image_thumbnail_html', $gadgetim_html, $gadgetim_id );
						}
						$gadgetim_thumb = wp_get_attachment_image_url( 'video' === $gadgetim_item['type'] ? (int) ( $gadgetim_item['poster'] ?? 0 ) : $gadgetim_id, 'gtm-thumb' );
						?>
						<li class="gal__slide<?php echo $gadgetim_first ? ' woocommerce-product-gallery__image is-active' : ''; ?><?php echo 'video' === $gadgetim_item['type'] ? ' gal__slide--video' : ''; ?>" id="gal-<?php echo esc_attr( (string) ( $gadgetim_i + 1 ) ); ?>" data-gal-slide="<?php echo esc_attr( (string) $gadgetim_i ); ?>"<?php echo $gadgetim_thumb ? ' data-thumb="' . esc_url( (string) $gadgetim_thumb ) . '"' : ''; ?>>
							<?php echo $gadgetim_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme/WooCommerce markup passed through WooCommerce's own filters. ?>
						</li>
					<?php endforeach; ?>
				<?php else : ?>
					<li class="gal__slide woocommerce-product-gallery__image--placeholder is-active" id="gal-1" data-gal-slide="0">
						<img src="<?php echo esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ); ?>" alt="<?php esc_attr_e( 'Awaiting product image', 'gadgetim' ); ?>" class="gal__img wp-post-image is-placeholder" width="800" height="800" decoding="async">
					</li>
				<?php endif; ?>
			</ul>

			<?php if ( $gadgetim_sticker || $gadgetim_new || ! $gadgetim_in ) : ?>
				<div class="gal__flags">
					<?php
					if ( $gadgetim_sticker ) {
						echo gadgetim_sticker( gadgetim_percent( $gadgetim_off ), '', _x( 'off', 'discount sticker, under the percentage', 'gadgetim' ), 'gal__sticker' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					}
					if ( $gadgetim_new ) {
						echo gadgetim_badge( __( 'New', 'gadgetim' ), 'new', [ 'icon' => 'sparkle' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					}
					if ( ! $gadgetim_in ) {
						echo gadgetim_badge( __( 'Out of stock', 'gadgetim' ), 'dark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					}
					?>
				</div>
			<?php endif; ?>

			<?php echo gadgetim_wish_button( $product, 'glass-btn wish-btn gal__wish' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>

			<?php if ( $gadgetim_count > 1 ) : ?>
				<p class="gal__count" aria-hidden="true"><bdi data-gal-count><?php echo esc_html( gadgetim_number( 1 ) . ' / ' . gadgetim_number( $gadgetim_count ) ); ?></bdi></p>
				<?php if ( $gadgetim_count <= 7 ) : ?>
				<div class="gal__dots" aria-hidden="true">
					<?php for ( $gadgetim_d = 0; $gadgetim_d < $gadgetim_count; $gadgetim_d++ ) : ?>
						<i<?php echo 0 === $gadgetim_d ? ' class="is-active"' : ''; ?>></i>
					<?php endfor; ?>
				</div>
				<?php endif; ?>
				<button class="gal__nav gal__nav--prev" type="button" data-gal-go="prev" aria-label="<?php esc_attr_e( 'Previous image', 'gadgetim' ); ?>" disabled><?php gadgetim_the_icon( 'chev-r', 'ic--dir' ); ?></button>
				<button class="gal__nav gal__nav--next" type="button" data-gal-go="next" aria-label="<?php esc_attr_e( 'Next image', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></button>
			<?php endif; ?>

			<?php if ( $gadgetim_count ) : ?>
				<button class="glass-btn gal__expand" type="button" data-lb-open-current aria-label="<?php esc_attr_e( 'View the images full screen', 'gadgetim' ); ?>" hidden><?php echo gadgetim_pdp_svg( 'expand' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			<?php endif; ?>
		</div>
		<?php
		/**
		 * Hook: woocommerce_product_thumbnails (the default thumbnails callback is removed; the hook stays for plugins).
		 */
		do_action( 'woocommerce_product_thumbnails' );
		?>
	</div>

	<?php if ( $gadgetim_count > 1 ) : ?>
		<ul class="gal__thumbs" aria-label="<?php esc_attr_e( 'Choose an image', 'gadgetim' ); ?>">
			<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
				<?php $gadgetim_thumb_id = 'video' === $gadgetim_item['type'] ? (int) ( $gadgetim_item['poster'] ?? 0 ) : (int) $gadgetim_item['id']; ?>
				<li>
					<a class="gal__thumb<?php echo 0 === $gadgetim_i ? ' is-active' : ''; ?>" href="#gal-<?php echo esc_attr( (string) ( $gadgetim_i + 1 ) ); ?>" data-gal-thumb="<?php echo esc_attr( (string) $gadgetim_i ); ?>"<?php echo 0 === $gadgetim_i ? ' aria-current="true"' : ''; ?>>
						<?php
						if ( $gadgetim_thumb_id > 0 ) {
							echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
								$gadgetim_thumb_id,
								'gtm-thumb',
								[
									'sizes'   => 'thumb',
									'loading' => $gadgetim_i < 6 ? 'eager' : 'lazy',
									'class'   => 'gal__thumb-img',
									'alt'     => '',
									'lqip'    => false,
								]
							);
						}
						?>
						<?php if ( 'video' === $gadgetim_item['type'] ) : ?>
							<span class="gal__thumb-play"><?php gadgetim_the_icon( 'play' ); ?></span>
						<?php endif; ?>
						<span class="sr-only"><?php echo esc_html( sprintf( /* translators: 1: image number, 2: number of images */ __( 'Image %1$s of %2$s', 'gadgetim' ), gadgetim_number( $gadgetim_i + 1 ), gadgetim_number( $gadgetim_count ) ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
