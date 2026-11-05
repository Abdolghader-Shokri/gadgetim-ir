<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): items with icons (gadgetim_account_icon()); a vertical card list on wide
 * screens, a scrolling chip row on phones; the current item in the gradient, log out in rose.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation myac-nav" aria-label="<?php esc_html_e( 'Account pages', 'woocommerce' ); ?>">
	<ul class="myac-nav__list">
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
			<li class="<?php echo wc_get_account_menu_item_classes( $endpoint ); ?>">
				<a class="myac-nav__a" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
					<span class="myac-nav__ic" aria-hidden="true"><?php gadgetim_the_icon( gadgetim_account_icon( (string) $endpoint ) ); ?></span>
					<span class="myac-nav__t"><?php echo esc_html( $label ); ?></span>
					<?php gadgetim_the_icon( 'chev-l', 'myac-nav__chev ic--dir' ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
