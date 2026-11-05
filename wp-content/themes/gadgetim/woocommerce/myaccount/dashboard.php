<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a greeting band (WooCommerce's hello / log out line), shortcut cards (orders,
 * addresses, account details, wishlist, order tracking, WhatsApp support) and the latest orders.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);

$gadgetim_uid     = get_current_user_id();
$gadgetim_menu    = wc_get_account_menu_items();
$gadgetim_count   = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $gadgetim_uid ) : 0;
$gadgetim_recent  = $gadgetim_count > 0 ? wc_get_orders(
	[
		'customer' => $gadgetim_uid,
		'limit'    => 3,
		'orderby'  => 'date',
		'order'    => 'DESC',
	]
) : [];
$gadgetim_address = WC()->customer && '' !== (string) WC()->customer->get_billing_address_1();
$gadgetim_track   = gadgetim_order_tracking_url();
$gadgetim_help    = gadgetim_order_help_url();
?>

<div class="mdash">
	<section class="mdash__hello">
		<span class="mdash__fx" aria-hidden="true"><i></i><i></i></span>
		<p class="mdash__hi">
			<?php
			printf(
				/* translators: 1: user display name 2: logout url */
				wp_kses( __( 'Hello %1$s (not %1$s? <a href="%2$s">Log out</a>)', 'woocommerce' ), $allowed_html ),
				'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
				esc_url( wc_logout_url() )
			);
			?>
		</p>
		<p class="mdash__lead"><?php esc_html_e( 'Your orders, addresses and details, all in one place.', 'gadgetim' ); ?></p>
	</section>

	<ul class="mdash__grid">
		<?php if ( isset( $gadgetim_menu['orders'] ) ) : ?>
			<li><a class="dcard dcard--violet" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">
				<span class="dcard__ic" aria-hidden="true"><?php gadgetim_the_icon( 'orders' ); ?></span>
				<span class="dcard__t"><?php echo esc_html( $gadgetim_menu['orders'] ); ?></span>
				<?php /* translators: %s: number of orders */ ?>
				<span class="dcard__v"><?php echo esc_html( sprintf( _n( '%s order', '%s orders', $gadgetim_count, 'gadgetim' ), gadgetim_number( $gadgetim_count ) ) ); ?></span>
			</a></li>
		<?php endif; ?>
		<?php if ( isset( $gadgetim_menu['edit-address'] ) ) : ?>
			<li><a class="dcard dcard--teal" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>">
				<span class="dcard__ic" aria-hidden="true"><?php gadgetim_the_icon( 'pin' ); ?></span>
				<span class="dcard__t"><?php echo esc_html( $gadgetim_menu['edit-address'] ); ?></span>
				<span class="dcard__v"><?php echo $gadgetim_address ? esc_html__( 'Address saved', 'gadgetim' ) : esc_html__( 'Add an address', 'gadgetim' ); ?></span>
			</a></li>
		<?php endif; ?>
		<?php if ( isset( $gadgetim_menu['edit-account'] ) ) : ?>
			<li><a class="dcard dcard--pink" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>">
				<span class="dcard__ic" aria-hidden="true"><?php gadgetim_the_icon( 'user' ); ?></span>
				<span class="dcard__t"><?php echo esc_html( $gadgetim_menu['edit-account'] ); ?></span>
				<span class="dcard__v"><?php esc_html_e( 'Name, email and password', 'gadgetim' ); ?></span>
			</a></li>
		<?php endif; ?>
		<li><button class="dcard dcard--rose" type="button" data-open="wish-drawer" aria-controls="wish-drawer" aria-expanded="false">
			<span class="dcard__ic" aria-hidden="true"><?php gadgetim_the_icon( 'heart' ); ?><b class="badge is-empty" data-wish-count></b></span>
			<span class="dcard__t"><?php esc_html_e( 'Wishlist', 'gadgetim' ); ?></span>
			<span class="dcard__v"><?php esc_html_e( 'Saved on this device', 'gadgetim' ); ?></span>
		</button></li>
		<?php if ( '' !== $gadgetim_track ) : ?>
			<li><a class="dcard dcard--amber" href="<?php echo esc_url( $gadgetim_track ); ?>">
				<span class="dcard__ic" aria-hidden="true"><?php gadgetim_the_icon( 'truck' ); ?></span>
				<span class="dcard__t"><?php esc_html_e( 'Track an order', 'gadgetim' ); ?></span>
				<span class="dcard__v"><?php esc_html_e( 'With the order number', 'gadgetim' ); ?></span>
			</a></li>
		<?php endif; ?>
		<?php if ( '' !== $gadgetim_help ) : ?>
			<li><a class="dcard dcard--green" href="<?php echo esc_url( $gadgetim_help ); ?>" target="_blank" rel="noopener nofollow">
				<span class="dcard__ic" aria-hidden="true"><?php gadgetim_the_icon( 'chat' ); ?></span>
				<span class="dcard__t"><?php esc_html_e( 'Support', 'gadgetim' ); ?></span>
				<span class="dcard__v"><?php esc_html_e( 'Advice and support on WhatsApp', 'gadgetim' ); ?></span>
			</a></li>
		<?php endif; ?>
	</ul>

	<?php if ( $gadgetim_recent ) : ?>
		<section class="mdash__recent" aria-labelledby="mdash-recent-t">
			<div class="mdash__recent-h">
				<h2 class="cm-card__t" id="mdash-recent-t"><?php esc_html_e( 'Latest orders', 'gadgetim' ); ?></h2>
				<a class="sect__more" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'View all', 'gadgetim' ); ?><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
			</div>
			<ul class="olist">
				<?php foreach ( $gadgetim_recent as $gadgetim_order ) : ?>
					<?php
					if ( ! $gadgetim_order instanceof WC_Order ) {
						continue;
					}
					?>
					<li><a class="ocard" href="<?php echo esc_url( $gadgetim_order->get_view_order_url() ); ?>">
						<span class="ocard__thumbs" aria-hidden="true">
							<?php
							$gadgetim_n = 0;
							foreach ( $gadgetim_order->get_items() as $gadgetim_item ) {
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
						<span class="ocard__b">
							<?php /* translators: %s: order number */ ?>
							<span class="ocard__n"><?php echo esc_html( sprintf( __( 'Order %s', 'gadgetim' ), gadgetim_order_number( $gadgetim_order ) ) ); ?></span>
							<span class="ocard__d"><?php echo esc_html( gadgetim_order_date( $gadgetim_order ) ); ?></span>
						</span>
						<span class="ocard__s">
							<?php echo gadgetim_order_status_chip( $gadgetim_order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
							<span class="ocard__p"><?php echo wp_kses_post( $gadgetim_order->get_formatted_order_total() ); ?></span>
						</span>
					</a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>

<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
