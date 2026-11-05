<?php
/**
 * Shipping Methods Display
 *
 * In 2.1 we show methods per package. This allows for multiple methods per order if so desired.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-shipping.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): shipping options as selectable cards (name, cost or a «free» tag, a radio
 * indicator; the native radio stays for keyboard and WooCommerce's JS). Two contexts, set with
 * gadgetim_ship_context(): 'table' (default — a <tr> for the cart totals) and 'list' (a block for
 * the checkout's shipping card, templates/commerce/checkout-shipping.php).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 */

defined( 'ABSPATH' ) || exit;

$formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = '';
$gadgetim_list            = 'list' === gadgetim_ship_context();
$gadgetim_count           = is_array( $available_methods ) ? count( $available_methods ) : 0;

if ( $gadgetim_list ) {
	echo '<div class="woocommerce-shipping-totals shipping ship-pkg">';
	if ( $show_package_details || 0 < (int) $index ) {
		echo '<p class="ship-pkg__name">' . wp_kses_post( $package_name ) . '</p>';
	}
} else {
	echo '<tr class="woocommerce-shipping-totals shipping"><th>' . wp_kses_post( $package_name ) . '</th><td data-title="' . esc_attr( wp_strip_all_tags( $package_name ) ) . '">';
}
?>
		<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>
			<ul id="shipping_method" class="woocommerce-shipping-methods ship<?php echo 1 === $gadgetim_count ? ' ship--one' : ''; ?>">
				<?php foreach ( $available_methods as $method ) : ?>
					<?php
					$gadgetim_full  = (string) wc_cart_totals_shipping_method_label( $method );
					$gadgetim_label = (string) $method->get_label();
					$gadgetim_name  = $gadgetim_full;
					$gadgetim_cost  = '';
					foreach ( [ $gadgetim_label, gadgetim_commerce_digits_html( $gadgetim_label ) ] as $gadgetim_try ) {
						if ( '' !== $gadgetim_try && str_starts_with( $gadgetim_full, $gadgetim_try ) ) {
							$gadgetim_name = $gadgetim_try;
							$gadgetim_cost = trim( ltrim( trim( substr( $gadgetim_full, strlen( $gadgetim_try ) ) ), ':' ) );
							break;
						}
					}
					$gadgetim_free = '' === $gadgetim_cost && 0 >= (float) $method->cost;
					$gadgetim_id   = 'shipping_method_' . (int) $index . '_' . sanitize_title( $method->id );
					?>
					<li class="ship__o">
						<?php
						if ( 1 < count( $available_methods ) ) {
							printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', $index, esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- %1$d normalizes the package index; the remaining values are escaped or safe attribute markup.
						} else {
							printf( '<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />', $index, esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- %1$d normalizes the package index; the remaining attribute values are escaped.
						}
						?>
						<label class="ship__l" for="<?php echo esc_attr( $gadgetim_id ); ?>">
							<span class="ship__radio" aria-hidden="true"></span>
							<span class="ship__name"><?php echo wp_kses_post( $gadgetim_name ); ?></span>
							<?php if ( '' !== $gadgetim_cost ) : ?>
								<span class="ship__cost"><?php echo wp_kses_post( $gadgetim_cost ); ?></span>
							<?php elseif ( $gadgetim_free && $gadgetim_name === $gadgetim_full ) : ?>
								<span class="tag tag--success ship__free"><?php esc_html_e( 'Free', 'gadgetim' ); ?></span>
							<?php endif; ?>
						</label>
						<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( is_cart() ) : ?>
				<p class="woocommerce-shipping-destination ship__dest">
					<?php
					if ( $formatted_destination ) {
						// Translators: $s shipping destination.
						printf( esc_html__( 'Shipping to %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' );
						$calculator_text = esc_html__( 'Change address', 'woocommerce' );
					} else {
						echo wp_kses_post( apply_filters( 'woocommerce_shipping_estimate_html', __( 'Shipping options will be updated during checkout.', 'woocommerce' ) ) );
					}
					?>
				</p>
			<?php endif; ?>
			<?php
		elseif ( ! $has_calculated_shipping || ! $formatted_destination ) :
			echo '<p class="ship__msg">' . gadgetim_icon( 'truck' ) . '<span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon.
			if ( is_cart() && 'no' === get_option( 'woocommerce_enable_shipping_calc' ) ) {
				echo wp_kses_post( apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', __( 'Shipping costs are calculated during checkout.', 'woocommerce' ) ) );
			} else {
				echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'woocommerce' ) ) );
			}
			echo '</span></p>';
		elseif ( ! is_cart() ) :
			echo '<p class="ship__msg ship__msg--warn">' . gadgetim_icon( 'info' ) . '<span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon.
			echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) );
			echo '</span></p>';
		else :
			echo '<p class="ship__msg ship__msg--warn">' . gadgetim_icon( 'info' ) . '<span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon.
			echo wp_kses_post(
				/**
				 * Provides a means of overriding the default 'no shipping available' HTML string.
				 *
				 * @since 3.0.0
				 *
				 * @param string $html                  HTML message.
				 * @param string $formatted_destination The formatted shipping destination.
				 */
				apply_filters(
					'woocommerce_cart_no_shipping_available_html',
					// Translators: $s shipping destination.
					sprintf( esc_html__( 'No shipping options were found for %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' ),
					$formatted_destination
				)
			);
			echo '</span></p>';
			$calculator_text = esc_html__( 'Enter a different address', 'woocommerce' );
		endif;
		?>

		<?php if ( $show_package_details ) : ?>
			<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
		<?php endif; ?>

		<?php if ( $show_shipping_calculator ) : ?>
			<?php woocommerce_shipping_calculator( $calculator_text ); ?>
		<?php endif; ?>
<?php
echo $gadgetim_list ? '</div>' : '</td></tr>';
