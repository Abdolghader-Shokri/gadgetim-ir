<?php
/**
 * Orders
 *
 * Shows orders on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/orders.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): WooCommerce's orders table, every row a card on phones (number and date, item
 * thumbnails, status chip, total, actions) and a roomy table from 768 px. Columns come from
 * wc_get_account_orders_columns() and keep their woocommerce_my_account_my_orders_column_* hooks.
 * The empty state invites to the shop.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders ) : ?>

	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table otab">
		<thead>
			<tr>
				<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
					<th scope="col" class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $customer_orders->orders as $customer_order ) {
				$order      = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$item_count = $order->get_item_count() - $order->get_item_count_refunded();
				?>
				<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order otab__row">
					<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) :
						$is_order_number = 'order-number' === $column_id;
					?>
						<?php if ( $is_order_number ) : ?>
							<th class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>" scope="row">
						<?php else : ?>
							<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
						<?php endif; ?>

							<?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) : ?>
								<?php do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order ); ?>

							<?php elseif ( $is_order_number ) : ?>
								<span class="otab__thumbs" aria-hidden="true">
									<?php
									$gadgetim_n = 0;
									foreach ( $order->get_items() as $gadgetim_item ) {
										$gadgetim_p = $gadgetim_item instanceof WC_Order_Item_Product ? $gadgetim_item->get_product() : null;
										if ( $gadgetim_p ) {
											echo gadgetim_product_image( $gadgetim_p, 'gtm-thumb', [ 'sizes' => '44px', 'alt' => '', 'loading' => 'lazy' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
											if ( ++$gadgetim_n >= 3 ) {
												break;
											}
										}
									}
									?>
								</span>
								<?php /* translators: %s: the order number, usually accompanied by a leading # */ ?>
								<a class="otab__n" href="<?php echo esc_url( $order->get_view_order_url() ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View order number %s', 'woocommerce' ), $order->get_order_number() ) ); ?>">
									<?php echo esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . gadgetim_order_number( $order ) ); ?>
								</a>

							<?php elseif ( 'order-date' === $column_id ) : ?>
								<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( gadgetim_order_date( $order ) ); ?></time>

							<?php elseif ( 'order-status' === $column_id ) : ?>
								<?php echo gadgetim_order_status_chip( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>

							<?php elseif ( 'order-total' === $column_id ) : ?>
								<?php
								/* translators: 1: formatted order total 2: total order items */
								echo wp_kses_post( sprintf( _n( '%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'woocommerce' ), $order->get_formatted_order_total(), gadgetim_number( $item_count ) ) );
								?>

							<?php elseif ( 'order-actions' === $column_id ) : ?>
								<?php
								$actions = wc_get_account_orders_actions( $order );

								if ( ! empty( $actions ) ) {
									foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
										if ( empty( $action['aria-label'] ) ) {
											// Generate the aria-label based on the action name.
											/* translators: %1$s Action name, %2$s Order number. */
											$action_aria_label = sprintf( __( '%1$s order number %2$s', 'woocommerce' ), $action['name'], $order->get_order_number() );
										} else {
											$action_aria_label = $action['aria-label'];
										}
										echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button' . esc_attr( $wp_button_class ) . ' button ' . sanitize_html_class( $key ) . ' btn btn--sm ' . ( 'pay' === $key ? '' : ( 'view' === $key ? 'btn--soft' : 'btn--ghost' ) ) . '" aria-label="' . esc_attr( $action_aria_label ) . '">' . esc_html( $action['name'] ) . '</a>';
										unset( $action_aria_label );
									}
								}
								?>
							<?php endif; ?>

						<?php if ( $is_order_number ) : ?>
							</th>
						<?php else : ?>
							</td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination otab__pager">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button btn btn--outline btn--sm<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php gadgetim_the_icon( 'chev-r', 'ic--dir' ); ?><span><?php esc_html_e( 'Previous', 'woocommerce' ); ?></span></a>
			<?php endif; ?>

			<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
				<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button btn btn--outline btn--sm<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><span><?php esc_html_e( 'Next', 'woocommerce' ); ?></span><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else : ?>

	<?php
	echo gadgetim_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		[
			'icon'    => 'orders',
			'class'   => 'cm-card myac-empty',
			'title'   => __( 'No order has been made yet.', 'woocommerce' ),
			'text'    => __( 'When you place an order, it will appear here with its status, so you can follow it from checkout to your door.', 'gadgetim' ),
			'actions' => [
				[
					'label' => __( 'Browse products', 'woocommerce' ),
					'href'  => apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ),
					'icon'  => 'arrow',
				],
			],
		]
	);
	?>

<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
