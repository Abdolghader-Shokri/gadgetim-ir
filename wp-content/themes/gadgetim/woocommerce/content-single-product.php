<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim Aurora product page:
 *   .pdp__hero  gallery · title area · buy box (woocommerce_single_product_summary) · key features +
 *               authenticity card. Phones stack them; 768 px puts the gallery beside the rest;
 *               1240 px is three columns (gallery · features/guarantee · sticky buy box under a wide title).
 *   .pdp-body   sticky in-page chips + the stacked sections (tabs/tabs.php) · a sticky mini buy box (≥ 1100 px).
 *   Then woocommerce_after_single_product_summary (up-sells, related, recently viewed), the sticky
 *   buy bar (phones and tablets, replaces the bottom nav once the main button scrolls away) and the
 *   full-screen gallery <dialog>. Every WooCommerce hook of the original template still fires.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_password_form() returns WordPress-generated form markup.
	return;
}

if ( ! $product instanceof WC_Product ) {
	$product = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the WooCommerce global, as in WooCommerce's own setup.
	if ( ! $product instanceof WC_Product ) {
		return;
	}
}

$gadgetim_sections = gadgetim_pdp_sections( $product );
$gadgetim_contact  = gadgetim_contact_mode( $product );
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'pdp' . ( $gadgetim_contact ? ' pdp--contact' : '' ), $product ); ?> data-pdp="<?php echo esc_attr( (string) $product->get_id() ); ?>">

	<div class="pdp__hero">
		<div class="pdp__gallery">
			<?php
			/**
			 * Hook: woocommerce_before_single_product_summary.
			 *
			 * @hooked woocommerce_show_product_images - 20 (single-product/product-image.php)
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<?php gadgetim_part( 'product/head', [ 'product' => $product ] ); ?>

		<div class="pdp__buy">
			<div class="summary entry-summary buybox" id="buybox" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
				<img class="buy__fly" alt="" aria-hidden="true" width="1" height="1" decoding="async" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">
				<span hidden data-title><?php echo esc_html( gadgetim_digits( gadgetim_short_title( $product ) ) ); ?></span>
				<?php
				/**
				 * Hook: woocommerce_single_product_summary.
				 *
				 * @hooked gadgetim_pdp_price - 10
				 * @hooked gadgetim_pdp_stock - 15
				 * @hooked woocommerce_template_single_add_to_cart - 30
				 * @hooked gadgetim_pdp_secondary - 32
				 * @hooked gadgetim_pdp_perks - 45
				 * @hooked WC_Structured_Data::generate_product_data() - 60
				 */
				do_action( 'woocommerce_single_product_summary' );
				?>
			</div>
		</div>

		<?php gadgetim_part( 'product/side', [ 'product' => $product ] ); ?>
	</div>

	<div class="pdp-body">
		<div class="pdp-body__main">
			<?php gadgetim_part( 'product/nav', [ 'sections' => $gadgetim_sections ] ); ?>
			<?php
			// The sections (WooCommerce's woocommerce_output_product_data_tabs, moved here from
			// woocommerce_after_single_product_summary 10 so they share the body with the mini buy box).
			woocommerce_output_product_data_tabs();
			?>
		</div>
		<aside class="pdp-body__aside" aria-label="<?php esc_attr_e( 'Buy this product', 'gadgetim' ); ?>">
			<?php gadgetim_part( 'product/mini-buy', [ 'product' => $product ] ); ?>
		</aside>
	</div>

	<?php
	/**
	 * Hook: woocommerce_after_single_product_summary.
	 *
	 * @hooked woocommerce_upsell_display - 15
	 * @hooked woocommerce_output_related_products - 20
	 * @hooked gadgetim_pdp_recent - 25
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>

	<?php gadgetim_part( 'product/sticky-bar', [ 'product' => $product ] ); ?>
	<?php gadgetim_part( 'product/lightbox', [ 'product' => $product ] ); ?>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
