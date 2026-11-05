<?php
/**
 * Desktop mini buy box (≥ 1100 px, sticky beside the sections): thumbnail, title, price, stock,
 * add to cart (cart.js, quantity synced with the main stepper), WhatsApp, promise icons.
 * Call-for-price mode: the compact contact slip.
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

$gadgetim_id      = (string) $gadgetim_product->get_id();
$gadgetim_title   = gadgetim_short_title( $gadgetim_product );
$gadgetim_contact = gadgetim_contact_mode( $gadgetim_product );
$gadgetim_in      = $gadgetim_product->is_in_stock();
$gadgetim_simple  = $gadgetim_product->is_type( 'simple' ) && $gadgetim_product->is_purchasable();
$gadgetim_wa      = $gadgetim_in ? gadgetim_pdp_whatsapp_order_url( $gadgetim_product, gadgetim_number( 1 ) ) : gadgetim_pdp_whatsapp_ask_url( $gadgetim_product );
?>
<div class="pdp-mini" data-product-id="<?php echo esc_attr( $gadgetim_id ); ?>">
	<div class="pdp-mini__top">
		<span class="pdp-mini__img">
			<?php
			echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
				$gadgetim_product,
				'gtm-thumb',
				[
					'sizes'   => 'rank',
					'loading' => 'lazy',
					'alt'     => '',
				]
			);
			?>
		</span>
		<p class="pdp-mini__t" data-title><?php echo gadgetim_pdp_text( $gadgetim_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></p>
	</div>

	<?php if ( $gadgetim_contact ) : ?>
		<?php echo gadgetim_price_html( $gadgetim_product, [ 'slip' => 'compact' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin markup. ?>
	<?php else : ?>
		<div class="pdp-mini__price">
			<?php echo gadgetim_price_html( $gadgetim_product, [ 'class' => 'price--mini' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin markup. ?>
			<?php echo gadgetim_discount_pill( $gadgetim_product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		</div>
		<?php if ( $gadgetim_in && $gadgetim_simple ) : ?>
			<a class="btn btn--block buy-add pdp-mini__add" href="<?php echo esc_url( $gadgetim_product->add_to_cart_url() ); ?>" rel="nofollow" role="button" data-add-to-cart="<?php echo esc_attr( $gadgetim_id ); ?>" data-quantity="1">
				<span class="buy__add-in"><?php gadgetim_the_icon( 'bag' ); ?><span><?php echo esc_html( $gadgetim_product->single_add_to_cart_text() ); ?></span></span>
				<span class="buy__add-ok" aria-hidden="true"><?php gadgetim_the_icon( 'check' ); ?><span><?php esc_html_e( 'Added to cart', 'gadgetim' ); ?></span></span>
			</a>
		<?php elseif ( $gadgetim_in ) : ?>
			<a class="btn btn--block pdp-mini__add" href="#buybox"><?php esc_html_e( 'Choose options', 'gadgetim' ); ?></a>
		<?php else : ?>
			<span class="soldout pdp-mini__out"><?php esc_html_e( 'Out of stock', 'gadgetim' ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $gadgetim_wa ) : ?>
			<a class="btn btn--sm btn--block buy__wa" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener nofollow"><?php gadgetim_the_icon( 'chat' ); ?><span><?php echo $gadgetim_in ? esc_html__( 'Order on WhatsApp', 'gadgetim' ) : esc_html__( 'Ask about availability on WhatsApp', 'gadgetim' ); ?></span></a>
		<?php endif; ?>
	<?php endif; ?>

	<ul class="pdp-mini__trust">
		<li><?php gadgetim_the_icon( 'shield' ); ?><?php esc_html_e( 'Authenticity guarantee', 'gadgetim' ); ?></li>
		<li><?php gadgetim_the_icon( 'truck' ); ?><?php echo esc_html( gadgetim_digits( gadgetim_t( 'trust.delivery', __( 'Delivery across Iran', 'gadgetim' ) ) ) ); ?></li>
		<li><?php gadgetim_the_icon( 'return' ); ?><?php echo esc_html( gadgetim_digits( gadgetim_t( 'trust.return_window', __( '7-day return guarantee', 'gadgetim' ) ) ) ); ?></li>
	</ul>
</div>
