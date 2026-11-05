<?php
/**
 * View Order
 *
 * Shows the details of a particular order on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/view-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the order head card (number, Jalali date, status chip, total), the status
 * timeline, the order updates as a feed, then WooCommerce's order details (woocommerce_view_order).
 * WooCommerce's status sentence stays for screen readers (filter woocommerce_order_details_status).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();
?>
<div class="vo">
	<header class="cm-card vo__head">
		<span class="vo__ic" aria-hidden="true"><?php gadgetim_the_icon( 'box' ); ?></span>
		<div class="vo__main">
			<p class="vo__k"><?php esc_html_e( 'Order number', 'gadgetim' ); ?></p>
			<p class="vo__n"><bdi><?php echo esc_html( gadgetim_order_number( $order ) ); ?></bdi></p>
			<p class="vo__meta"><?php gadgetim_the_icon( 'clock' ); ?><?php echo esc_html( gadgetim_order_date( $order, 'medium' ) ); ?></p>
		</div>
		<div class="vo__side">
			<?php echo gadgetim_order_status_chip( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
			<span class="vo__total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
		</div>
	</header>

	<p class="sr-only">
	<?php
	echo wp_kses_post(
		/**
		 * Filter to modify order detiails status text.
		 *
		 * @param string $order_status The order status text.
		 *
		 * @since 10.1.0
		 */
		apply_filters(
			'woocommerce_order_details_status',
			sprintf(
			/* translators: 1: order number 2: order date 3: order status */
				esc_html__( 'Order #%1$s was placed on %2$s and is currently %3$s.', 'woocommerce' ),
				'<mark class="order-number">' . gadgetim_order_number( $order ) . '</mark>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'<mark class="order-date">' . gadgetim_order_date( $order, 'medium' ) . '</mark>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'<mark class="order-status">' . wc_get_order_status_name( $order->get_status() ) . '</mark>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			),
			$order
		)
	);

	?>
	</p>

	<?php echo gadgetim_order_timeline( $order, [ 'title' => false ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template. ?>

	<?php if ( $notes ) : ?>
		<section class="cm-card vo-notes">
			<h2 class="cm-card__t vo-notes__t"><?php gadgetim_the_icon( 'bell' ); ?><?php esc_html_e( 'Order updates', 'woocommerce' ); ?></h2>
			<ol class="woocommerce-OrderUpdates commentlist notes vo-notes__list">
				<?php foreach ( $notes as $note ) : ?>
				<li class="woocommerce-OrderUpdate comment note">
					<div class="woocommerce-OrderUpdate-inner comment_container">
						<div class="woocommerce-OrderUpdate-text comment-text">
							<p class="woocommerce-OrderUpdate-meta meta"><?php echo esc_html( gadgetim_date( (int) strtotime( $note->comment_date_gmt . ' UTC' ), 'datetime' ) ); ?></p>
							<div class="woocommerce-OrderUpdate-description description">
								<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="clear"></div>
					</div>
				</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_view_order', $order_id ); ?>
</div>
