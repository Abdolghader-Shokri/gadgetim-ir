<?php
/**
 * Cart Page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): items as cards (thumbnail, brand, title, unit price, quantity stepper, line
 * total with the old price and the saving), the coupon field behind a disclosure, the totals card in
 * a sticky side column. The table, its classes and every hook stay, so WooCommerce's cart.js
 * (AJAX quantity updates, remove + undo, coupons) keeps working.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 */

defined( 'ABSPATH' ) || exit;

$gadgetim_count      = gadgetim_cart_count();
$gadgetim_thresholds = gadgetim_sale_thresholds();
gadgetim_set_eager_budget( 3 );

do_action( 'woocommerce_before_cart' ); ?>

<div class="cart-grid">
<form class="woocommerce-cart-form cart-main" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<div class="cm-card citems-card">
		<header class="cm-card__head citems__head">
			<span class="cm-card__ic" aria-hidden="true"><?php gadgetim_the_icon( 'bag' ); ?></span>
			<div class="cm-card__titles">
				<h2 class="cm-card__t"><?php esc_html_e( 'Items in your cart', 'gadgetim' ); ?></h2>
				<p class="cm-card__sub"><?php echo esc_html( gadgetim_items_label( $gadgetim_count ) ); ?></p>
			</div>
		</header>

	<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents citems" cellspacing="0" role="table">
		<thead class="sr-only" role="rowgroup">
			<tr role="row">
				<th scope="col" class="product-remove" role="columnheader"><span class="screen-reader-text"><?php esc_html_e( 'Remove item', 'woocommerce' ); ?></span></th>
				<th scope="col" class="product-thumbnail" role="columnheader"><span class="screen-reader-text"><?php esc_html_e( 'Thumbnail image', 'woocommerce' ); ?></span></th>
				<th scope="col" class="product-name" role="columnheader"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th scope="col" class="product-price" role="columnheader"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
				<th scope="col" class="product-quantity" role="columnheader"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
				<th scope="col" class="product-subtotal" role="columnheader"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody role="rowgroup">
			<?php do_action( 'woocommerce_before_cart_contents' ); ?>

			<?php
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

				/**
				 * Filter whether this cart item is visible in the cart.
				 *
				 * @since 2.1.0
				 * @param bool   $visible     Whether the cart item is visible. Default true.
				 * @param array  $cart_item     The cart item data.
				 * @param string $cart_item_key The cart item key.
				 */
				$visible = apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key );

				if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) {
					$cart_item_name = WC()->cart->get_item_product_name( $cart_item, $_product );

					/**
					 * Filter the product name.
					 *
					 * @since 2.1.0
					 * @param string $product_name Name of the product in the cart.
					 * @param array $cart_item The product in the cart.
					 * @param string $cart_item_key Key for the product in the cart.
					 */
					$product_name      = apply_filters( 'woocommerce_cart_item_name', $cart_item_name, $cart_item, $cart_item_key );
					$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
					$gadgetim_name     = gadgetim_line_name( $_product, (string) $cart_item_name );
					$gadgetim_brand    = gadgetim_product_brand( $_product->get_parent_id() ? ( wc_get_product( $_product->get_parent_id() ) ?: $_product ) : $_product );
					$gadgetim_qty      = (float) $cart_item['quantity'];
					$gadgetim_saving   = gadgetim_line_saving( $_product, $gadgetim_qty );
					$gadgetim_off      = gadgetim_product_discount( $_product );
					?>
					<tr class="woocommerce-cart-form__cart-item ci<?php echo 1.0 === $gadgetim_qty ? ' ci--one' : ''; ?> <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>" role="row" data-key="<?php echo esc_attr( $cart_item_key ); ?>">

						<td class="product-remove ci__rm" role="cell">
							<?php
								echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									'woocommerce_cart_item_remove_link',
									sprintf(
										'<a role="button" href="%s" class="remove ci__x" aria-label="%s" data-product_id="%s" data-product_sku="%s">%s</a>',
										esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
										/* translators: %s is the product name */
										esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
										esc_attr( $product_id ),
										esc_attr( $_product->get_sku() ),
										gadgetim_icon( 'trash' )
									),
									$cart_item_key
								);
							?>
						</td>

						<td class="product-thumbnail ci__img" role="cell">
						<?php
						/**
						 * Filter the product thumbnail displayed in the WooCommerce cart.
						 *
						 * This filter allows developers to customize the HTML output of the product
						 * thumbnail. It passes the product image along with cart item data
						 * for potential modifications before being displayed in the cart.
						 *
						 * @param string $thumbnail     The HTML for the product image.
						 * @param array  $cart_item     The cart item data.
						 * @param string $cart_item_key Unique key for the cart item.
						 *
						 * @since 2.1.0
						 */
						$thumbnail = apply_filters(
							'woocommerce_cart_item_thumbnail',
							gadgetim_product_image(
								$_product,
								'gtm-thumb',
								[
									'sizes' => '(min-width:768px) 104px, 84px',
									'alt'   => '',
								]
							),
							$cart_item,
							$cart_item_key
						);

						if ( ! $product_permalink ) {
							echo $thumbnail; // PHPCS: XSS ok.
						} else {
							printf( '<a href="%s" tabindex="-1" aria-hidden="true">%s</a>', esc_url( $product_permalink ), $thumbnail ); // PHPCS: XSS ok.
						}
						?>
						</td>

						<td role="rowheader" class="product-name ci__name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
						<?php
						if ( $gadgetim_brand ) {
							echo '<span class="ci__brand" dir="auto">' . esc_html( gadgetim_term_name( $gadgetim_brand ) ) . '</span>';
						}
						echo '<span class="ci__t">';
						if ( ! $product_permalink ) {
							echo wp_kses( $gadgetim_name . '&nbsp;', gadgetim_commerce_kses_name() );
						} else {
							/**
							 * This filter is documented above.
							 *
							 * @since 2.1.0
							 */
							echo wp_kses( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $gadgetim_name ), $cart_item, $cart_item_key ), gadgetim_commerce_kses_name() );
						}
						echo '</span>';

						do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

						// Meta data.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo wc_get_formatted_cart_item_data( $cart_item, false, $cart_item_name );

						// Backorder notification.
						if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
							echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
						}

						$gadgetim_low = gadgetim_low_stock( $_product );
						if ( $gadgetim_low > 0 ) {
							/* translators: %s: number of items left */
							echo '<span class="ci__low"><i class="dotty" aria-hidden="true"></i>' . esc_html( sprintf( __( 'Only %s left', 'gadgetim' ), gadgetim_number( $gadgetim_low ) ) ) . '</span>';
						}
						?>
						</td>

						<td class="product-price ci__unit" role="cell" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
							<span class="ci__lbl"><?php esc_html_e( 'Each', 'gadgetim' ); ?></span>
							<?php
								echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
							?>
						</td>

						<td class="product-quantity ci__qty" role="cell" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
						<?php
						if ( $_product->is_sold_individually() ) {
							$min_quantity = 1;
							$max_quantity = 1;
						} else {
							$min_quantity = 0;
							$max_quantity = $_product->get_max_purchase_quantity();
						}

						$product_quantity = woocommerce_quantity_input(
							array(
								'input_name'   => "cart[{$cart_item_key}][qty]",
								'input_value'  => $cart_item['quantity'],
								'max_value'    => $max_quantity,
								'min_value'    => $min_quantity,
								'product_name' => $product_name,
							),
							$_product,
							false
						);

						$product_quantity = apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
						$gadgetim_stepper = ! $_product->is_sold_individually() && str_contains( (string) $product_quantity, 'type="number"' );
						$gadgetim_plain   = wp_strip_all_tags( (string) $product_name );
						if ( $gadgetim_stepper ) {
							$gadgetim_at_max = $max_quantity > 0 && $gadgetim_qty >= $max_quantity;
							/* translators: %s: product name */
							$gadgetim_inc = sprintf( __( 'Increase quantity of %s', 'gadgetim' ), $gadgetim_plain );
							/* translators: %s: product name */
							$gadgetim_dec = 1.0 === $gadgetim_qty ? sprintf( __( 'Remove %s from the cart', 'gadgetim' ), $gadgetim_plain ) : sprintf( __( 'Decrease quantity of %s', 'gadgetim' ), $gadgetim_plain );
							$gadgetim_btn_inc = '<button type="button" class="cqty__b cqty__b--inc" data-cqty-step="1" aria-label="' . esc_attr( $gadgetim_inc ) . '"' . ( $gadgetim_at_max ? ' disabled' : '' ) . '>' . gadgetim_icon( 'plus' ) . '</button>';
							$gadgetim_btn_dec = '<button type="button" class="cqty__b cqty__b--dec" data-cqty-step="-1" aria-label="' . esc_attr( $gadgetim_dec ) . '">' . gadgetim_icon( 'minus', 'ic-minus' ) . gadgetim_icon( 'trash', 'ic-trash' ) . '</button>';
							$gadgetim_rtl     = 'rtl' === gadgetim_lang_dir();
							// Reading order: «+ n −» from the right in RTL, «− n +» from the left in LTR (DOM = visual = focus order).
							echo '<div class="cqty" data-cqty="' . esc_attr( $cart_item_key ) . '" role="group" aria-label="' . esc_attr( wp_strip_all_tags( sprintf( /* translators: %s: product name */ __( '%s quantity', 'woocommerce' ), $gadgetim_plain ) ) ) . '">';
							echo $gadgetim_rtl ? $gadgetim_btn_inc : $gadgetim_btn_dec; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
							echo '<span class="cqty__in">' . $product_quantity . '<span class="cqty__v" aria-hidden="true">' . esc_html( gadgetim_number( $gadgetim_qty ) ) . '</span></span>'; // PHPCS: XSS ok.
							echo $gadgetim_rtl ? $gadgetim_btn_dec : $gadgetim_btn_inc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
							echo '</div>';
						} else {
							echo '<span class="cqty cqty--fixed">' . $product_quantity . '<span class="cqty__x">' . esc_html( '× ' . gadgetim_number( $gadgetim_qty ) ) . '</span></span>'; // PHPCS: XSS ok.
						}
						?>
						</td>

						<td class="product-subtotal ci__sub" role="cell" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
							<?php if ( $gadgetim_saving > 0 && $gadgetim_off >= $gadgetim_thresholds['strike'] ) : ?>
								<span class="ci__was">
									<del><bdi><?php echo esc_html( gadgetim_number( (float) $_product->get_regular_price() * $gadgetim_qty ) ); ?></bdi></del>
									<?php echo gadgetim_discount_pill( $gadgetim_off, 'off--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
								</span>
							<?php endif; ?>
							<span class="ci__now">
							<?php
								echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
							?>
							</span>
							<?php if ( $gadgetim_saving > 0 ) : ?>
								<?php /* translators: %s: amount saved with the currency */ ?>
								<span class="ci__save"><?php gadgetim_the_icon( 'sparkle' ); ?><?php echo wp_kses_post( sprintf( __( 'You save %s', 'gadgetim' ), gadgetim_money( $gadgetim_saving ) ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				}
			}
			?>

			<?php do_action( 'woocommerce_cart_contents' ); ?>

			<tr class="citems__actions" role="row">
				<td colspan="6" class="actions" role="cell">

					<?php if ( wc_coupons_enabled() ) { ?>
						<div class="ccoupon" data-ccoupon>
							<button type="button" class="ccoupon__toggle" aria-expanded="true" aria-controls="ccoupon-box" data-ccoupon-toggle>
								<span class="ccoupon__ic" aria-hidden="true"><?php gadgetim_the_icon( 'ticket' ); ?></span>
								<span class="ccoupon__q"><?php esc_html_e( 'Have a discount code?', 'gadgetim' ); ?></span>
								<span class="ccoupon__go"><?php esc_html_e( 'Enter it here', 'gadgetim' ); ?><?php gadgetim_the_icon( 'chev-d', 'ccoupon__chev' ); ?></span>
							</button>
							<div class="coupon ccoupon__box" id="ccoupon-box">
								<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label> <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" autocomplete="off" dir="auto" /> <button type="submit" class="button btn btn--dark btn--sm<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
								<?php do_action( 'woocommerce_cart_coupon' ); ?>
							</div>
						</div>
					<?php } ?>

					<button type="submit" class="button btn btn--outline btn--sm citems__update<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

					<?php do_action( 'woocommerce_cart_actions' ); ?>

					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</td>
			</tr>

			<?php do_action( 'woocommerce_after_cart_contents' ); ?>
		</tbody>
	</table>
	</div>
	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="cart-collaterals cart-side">
	<?php
		/**
		 * Cart collaterals hook.
		 *
		 * @hooked woocommerce_cross_sell_display
		 * @hooked woocommerce_cart_totals - 10
		 */
		do_action( 'woocommerce_cart_collaterals' );
	?>
</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
