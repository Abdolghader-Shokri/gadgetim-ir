<?php
/**
 * Checkout shipping card: the shipping options as selectable cards (cart/cart-shipping.php in its
 * 'list' context). Printed by checkout/form-checkout.php and returned again as the ".co-ship"
 * fragment of every order-review refresh (gadgetim_checkout_fragments()), so the options follow
 * the address. The shipping_method inputs live inside the checkout form.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_needs = function_exists( 'WC' ) && WC()->cart && WC()->cart->needs_shipping() && WC()->cart->show_shipping();
if ( ! $gadgetim_needs ) {
	echo '<div class="co-ship" hidden></div>';
	return;
}
?>
<section class="cm-card co-card co-ship" aria-labelledby="co-ship-t">
	<header class="co-card__head">
		<span class="co-card__n co-card__n--cool" aria-hidden="true"><?php gadgetim_the_icon( 'truck' ); ?></span>
		<div class="co-card__titles">
			<h3 class="co-card__t" id="co-ship-t"><?php esc_html_e( 'Shipping method', 'gadgetim' ); ?></h3>
			<p class="co-card__sub"><?php esc_html_e( 'How should we send your parcel?', 'gadgetim' ); ?></p>
		</div>
	</header>
	<?php
	gadgetim_ship_context( 'list' );
	wc_cart_totals_shipping_html();
	gadgetim_ship_context( 'table' );
	?>
</section>
