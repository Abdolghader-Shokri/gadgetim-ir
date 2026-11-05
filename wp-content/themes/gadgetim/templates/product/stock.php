<?php
/**
 * Buy box stock line: «موجود در انبار» (pulsing green dot), «فقط N عدد مانده» with a scarcity bar
 * (managed stock 1–3), «قابل سفارش» (backorder) or «ناموجود». Printed through
 * woocommerce_get_stock_html by gadgetim_pdp_stock().
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

$gadgetim_low = gadgetim_low_stock( $gadgetim_product );

if ( ! $gadgetim_product->is_in_stock() ) : ?>
	<p class="stock out-of-stock buy__stock buy__stock--out"><i class="buy__dot" aria-hidden="true"></i><?php esc_html_e( 'Out of stock', 'gadgetim' ); ?></p>
<?php elseif ( $gadgetim_product->is_on_backorder( 1 ) ) : ?>
	<p class="stock available-on-backorder buy__stock buy__stock--back"><i class="buy__dot" aria-hidden="true"></i><?php esc_html_e( 'Available to order — ships as soon as it arrives', 'gadgetim' ); ?></p>
<?php elseif ( $gadgetim_low > 0 ) : ?>
	<div class="stock in-stock buy__stock buy__stock--low">
		<p><i class="dotty" aria-hidden="true"></i><?php echo esc_html( sprintf( /* translators: %s: number of items left */ _n( 'Only %s left in stock', 'Only %s left in stock', $gadgetim_low, 'gadgetim' ), gadgetim_number( $gadgetim_low ) ) ); ?></p>
		<span class="buy__bar" aria-hidden="true" style="--w:<?php echo esc_attr( (string) min( 100, 14 + $gadgetim_low * 16 ) ); ?>%"><i></i></span>
	</div>
<?php else : ?>
	<p class="stock in-stock buy__stock buy__stock--in"><i class="buy__dot" aria-hidden="true"></i><?php esc_html_e( 'In stock', 'gadgetim' ); ?></p>
<?php endif; ?>
