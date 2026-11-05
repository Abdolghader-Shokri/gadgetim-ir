<?php
/**
 * Checkout Payment Section
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/payment.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): payment methods as selectable cards (checkout/payment-method.php); with no
 * gateway available, WooCommerce's notice in a friendly card with ordering by WhatsApp or phone
 * (and the place-order button disabled); the terms, the trust line and the large gradient
 * place-order button. #payment is the fragment WooCommerce replaces on every order-review refresh.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

$gadgetim_needs_payment = WC()->cart && WC()->cart->needs_payment();
$gadgetim_no_gateway    = $gadgetim_needs_payment && empty( $available_gateways );

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<div id="payment" class="woocommerce-checkout-payment co-payment">
	<?php if ( $gadgetim_needs_payment ) : ?>
		<ul class="wc_payment_methods payment_methods methods pm" aria-label="<?php esc_attr_e( 'Payment methods', 'woocommerce' ); ?>">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li class="pm__none">';
				wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ), 'notice' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
				$gadgetim_wa  = gadgetim_whatsapp_url( gadgetim_cart_whatsapp_text() );
				$gadgetim_tel = gadgetim_tel_href();
				if ( '' !== $gadgetim_wa || '' !== $gadgetim_tel ) {
					echo '<div class="pm__alt"><p class="pm__alt-t">' . esc_html__( 'You can still order: send us your cart on WhatsApp or call us, and we will confirm the order and the payment with you.', 'gadgetim' ) . '</p><div class="pm__alt-a">';
					if ( '' !== $gadgetim_wa ) {
						echo '<a class="btn btn--success btn--sm" href="' . esc_url( $gadgetim_wa ) . '" target="_blank" rel="noopener nofollow">' . gadgetim_icon( 'chat' ) . '<span>' . esc_html__( 'Order on WhatsApp', 'gadgetim' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon.
					}
					if ( '' !== $gadgetim_tel ) {
						echo '<a class="btn btn--outline btn--sm" href="' . esc_url( $gadgetim_tel ) . '">' . gadgetim_icon( 'phone' ) . '<span>' . gadgetim_phone_display() . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helpers.
					}
					echo '</div></div>';
				}
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>
	<div class="form-row place-order co-place">
		<noscript>
			<?php
			/* translators: $1 and $2 opening and closing emphasis tags respectively */
			printf( esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ), '<em>', '</em>' );
			?>
			<br/><button type="submit" class="button alt btn btn--outline btn--sm<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo gadgetim_commerce_trust( 'ctrust--pay' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>

		<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt co-place__btn' . esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) . '" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '"' . ( $gadgetim_no_gateway ? ' disabled aria-disabled="true"' : '' ) . '>' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine ?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
