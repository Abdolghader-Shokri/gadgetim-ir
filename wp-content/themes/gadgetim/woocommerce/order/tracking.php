<?php
/**
 * Order tracking
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/tracking.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the order head card (number, Jalali date, status chip, total), the status
 * timeline, the order updates as a feed, then WooCommerce's order details (woocommerce_view_order).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();
?>

<div class="vo trk-result">
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

	<p class="order-info sr-only">
		<?php
		echo wp_kses_post(
			/**
			 * Filter to modify order tracking status text.
			 *
			 * @param string $order_status The order status text.
			 *
			 * @since 10.1.0
			 */
			apply_filters(
				'woocommerce_order_tracking_status',
				sprintf(
					/* translators: 1: order number 2: order date 3: order status */
					__( 'Order #%1$s was placed on %2$s and is currently %3$s.', 'woocommerce' ),
					'<mark class="order-number">' . gadgetim_order_number( $order ) . '</mark>',
					'<mark class="order-date">' . gadgetim_order_date( $order, 'medium' ) . '</mark>',
					'<mark class="order-status">' . wc_get_order_status_name( $order->get_status() ) . '</mark>'
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
			<ol class="commentlist notes vo-notes__list">
				<?php foreach ( $notes as $note ) : ?>
				<li class="comment note">
					<div class="comment_container">
						<div class="comment-text">
							<p class="meta"><?php echo esc_html( gadgetim_date( (int) strtotime( $note->comment_date_gmt . ' UTC' ), 'datetime' ) ); ?></p>
							<div class="description">
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

	<?php do_action( 'woocommerce_view_order', $order->get_id() ); ?>

	<p class="trk-again"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( (string) get_permalink() ); ?>"><?php gadgetim_the_icon( 'search' ); ?><span><?php esc_html_e( 'Track another order', 'gadgetim' ); ?></span></a></p>
</div>
