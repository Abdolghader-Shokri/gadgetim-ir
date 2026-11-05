<?php
/**
 * Checkout pay bar (phones): the order total and a «continue to payment» link that jumps to the
 * payment section. Shown while the place-order button is out of view (commerce.js); refreshed as
 * the ".cbar" fragment with every order-review update, so the total follows the shipping choice.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
	echo '<div class="cbar" hidden></div>';
	return;
}
?>
<div class="cbar cbar--co" data-cbar>
	<div class="cbar__sum">
		<span class="cbar__l"><?php esc_html_e( 'Total', 'woocommerce' ); ?></span>
		<b class="cbar__v"><?php wc_cart_totals_order_total_html(); ?></b>
	</div>
	<a class="btn btn--sm cbar__go" href="#co-pay" data-cbar-go><span><?php esc_html_e( 'Continue to payment', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
</div>
