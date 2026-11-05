<?php
/**
 * Order Customer Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-customer.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): address cards with icons; phone and e-mail isolated left-to-right, the phone
 * in national format with the language's digits.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.7.0
 */

defined( 'ABSPATH' ) || exit;

$show_shipping = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();
?>
<section class="woocommerce-customer-details oaddr<?php echo $show_shipping ? ' oaddr--2' : ''; ?>">

	<?php if ( $show_shipping ) : ?>

	<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses oaddr__grid">
		<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1 cm-card oaddr__card">

	<?php else : ?>
		<div class="cm-card oaddr__card">
	<?php endif; ?>

	<h2 class="woocommerce-column__title oaddr__t"><span class="cm-card__ic cm-card__ic--cool" aria-hidden="true"><?php gadgetim_the_icon( 'user' ); ?></span><?php esc_html_e( 'Billing address', 'woocommerce' ); ?></h2>

	<address>
		<?php echo wp_kses_post( gadgetim_commerce_digits_html( (string) $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ) ); ?>

		<?php if ( $order->get_billing_phone() ) : ?>
			<p class="woocommerce-customer-details--phone"><?php gadgetim_the_icon( 'phone' ); ?><?php echo gadgetim_phone_display( (string) $order->get_billing_phone() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></p>
		<?php endif; ?>

		<?php if ( $order->get_billing_email() ) : ?>
			<p class="woocommerce-customer-details--email"><?php gadgetim_the_icon( 'mail' ); ?><bdi dir="ltr"><?php echo esc_html( $order->get_billing_email() ); ?></bdi></p>
		<?php endif; ?>

		<?php
			/**
			 * Action hook fired after an address in the order customer details.
			 *
			 * @since 8.7.0
			 * @param string $address_type Type of address (billing or shipping).
			 * @param WC_Order $order Order object.
			 */
			do_action( 'woocommerce_order_details_after_customer_address', 'billing', $order );
		?>
	</address>

	<?php if ( $show_shipping ) : ?>

		</div><!-- /.col-1 -->

		<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2 cm-card oaddr__card">
			<h2 class="woocommerce-column__title oaddr__t"><span class="cm-card__ic cm-card__ic--green" aria-hidden="true"><?php gadgetim_the_icon( 'pin' ); ?></span><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>
			<address>
				<?php echo wp_kses_post( gadgetim_commerce_digits_html( (string) $order->get_formatted_shipping_address( esc_html__( 'N/A', 'woocommerce' ) ) ) ); ?>

				<?php if ( $order->get_shipping_phone() ) : ?>
					<p class="woocommerce-customer-details--phone"><?php gadgetim_the_icon( 'phone' ); ?><?php echo gadgetim_phone_display( (string) $order->get_shipping_phone() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></p>
				<?php endif; ?>

				<?php
					/**
					 * Action hook fired after an address in the order customer details.
					 *
					 * @since 8.7.0
					 * @param string $address_type Type of address (billing or shipping).
					 * @param WC_Order $order Order object.
					 */
					do_action( 'woocommerce_order_details_after_customer_address', 'shipping', $order );
				?>
			</address>
		</div><!-- /.col-2 -->

	</section><!-- /.col2-set -->

	<?php else : ?>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

</section>
