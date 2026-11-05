<?php
/**
 * Checkout shipping information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-shipping.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): "ship to a different address" as a switch row (WooCommerce's checkbox inside
 * #ship-to-different-address, which checkout.js listens to) that opens the second address form;
 * the order notes in their own compact card.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 * @global WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-shipping-fields">
	<?php if ( true === WC()->cart->needs_shipping_address() ) : ?>

		<div class="cm-card co-card co-card--ship">
			<h3 id="ship-to-different-address" class="co-switch">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 ), 1 ); ?> type="checkbox" name="ship_to_different_address" value="1" /> <span class="co-switch__t"><?php esc_html_e( 'Ship to a different address?', 'woocommerce' ); ?><small><?php esc_html_e( 'For a gift, or to deliver to your workplace.', 'gadgetim' ); ?></small></span>
				</label>
			</h3>

			<div class="shipping_address">

				<?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

				<div class="woocommerce-shipping-fields__field-wrapper co-fields">
					<?php
					$fields = $checkout->get_checkout_fields( 'shipping' );

					foreach ( $fields as $key => $field ) {
						woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
					}
					?>
				</div>

				<?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>

			</div>
		</div>

	<?php endif; ?>
</div>
<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php $order_fields = $checkout->get_checkout_fields( 'order' ); ?>

	<?php if ( ! empty( $order_fields ) ) : ?>

		<div class="cm-card co-card co-card--notes">
			<h3 class="co-card__t co-card__t--sm"><?php gadgetim_the_icon( 'edit' ); ?><?php esc_html_e( 'Additional information', 'woocommerce' ); ?></h3>

			<div class="woocommerce-additional-fields__field-wrapper co-fields">
				<?php foreach ( $order_fields as $key => $field ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
