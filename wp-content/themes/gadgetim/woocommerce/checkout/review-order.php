<?php
/**
 * Review order table
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/review-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the order summary — items with a thumbnail and a quantity badge (collapsible on
 * phones, tbody#co-items), then the totals. The shipping options are chosen in the shipping card of
 * the main column (templates/commerce/checkout-shipping.php); here a single row shows the chosen
 * cost, between the woocommerce_review_order_before/after_shipping hooks. A «you save» row closes
 * the table. The table is the fragment WooCommerce replaces on every order-review refresh.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 */

defined( 'ABSPATH' ) || exit;

$gadgetim_saved = gadgetim_cart_savings();
?>
<table class="shop_table woocommerce-checkout-review-order-table co-rev">
	<thead class="sr-only">
		<tr>
			<th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
			<th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody class="co-rev__items" id="co-items">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			/**
			 * Filter whether this cart item is visible in the checkout review order table.
			 *
			 * @since 2.1.0
			 * @param bool   $visible       Whether the cart item is visible. Default true.
			 * @param array  $cart_item     The cart item data.
			 * @param string $cart_item_key The cart item key.
			 */
			$visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

			if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) {
				?>
				<tr class="co-rev__i <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<td class="product-name">
						<span class="co-rev__img">
							<?php
							echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
								$_product,
								'gtm-thumb',
								[
									'sizes'   => '60px',
									'alt'     => '',
									'loading' => 'lazy',
								]
							);
							?>
							<b class="co-rev__q" aria-hidden="true"><?php echo esc_html( gadgetim_number( (float) $cart_item['quantity'] ) ); ?></b>
						</span>
						<span class="co-rev__body">
						<?php
						$cart_item_name = WC()->cart->get_item_product_name( $cart_item, $_product );
						/**
						 * This filter is documented in woocommerce/templates/cart/cart.php.
						 *
						 * @since 2.1.0
						 */
						$product_name = apply_filters( 'woocommerce_cart_item_name', gadgetim_line_name( $_product, (string) $cart_item_name ), $cart_item, $cart_item_key );
						?>
						<span class="co-rev__t"><?php echo wp_kses( $product_name, gadgetim_commerce_kses_name() ) . '&nbsp;'; ?></span>
						<?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', esc_html( gadgetim_number( (float) $cart_item['quantity'] ) ) ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo wc_get_formatted_cart_item_data( $cart_item, false, $cart_item_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
					</td>
					<td class="product-total">
						<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>

		<tr class="cart-subtotal">
			<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<?php
			$gadgetim_rates = false;
			foreach ( WC()->shipping()->get_packages() as $gadgetim_package ) {
				if ( ! empty( $gadgetim_package['rates'] ) ) {
					$gadgetim_rates = true;
					break;
				}
			}
			?>
			<tr class="co-rev__ship shipping">
				<th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
				<td>
					<?php if ( $gadgetim_rates ) : ?>
						<?php echo wp_kses_post( gadgetim_commerce_digits_html( (string) WC()->cart->get_cart_shipping_total() ) ); ?>
					<?php else : ?>
						<span class="co-rev__tbd"><?php esc_html_e( 'After you enter the address', 'gadgetim' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>

			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total">
			<th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

		<?php if ( $gadgetim_saved > 0 ) : ?>
			<tr class="co-rev__save">
				<th><?php gadgetim_the_icon( 'gift' ); ?><?php esc_html_e( 'Your saving on this order', 'gadgetim' ); ?></th>
				<td><?php echo gadgetim_money( $gadgetim_saved ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin markup. ?></td>
			</tr>
		<?php endif; ?>

	</tfoot>
</table>
