<?php
/**
 * Displayed when no products are found matching the current query
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/no-products-found.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the theme empty state. Catalogue archives print the richer
 * templates/archive/empty.php; this override serves other callers of wc_no_products_found().
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.8.0
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="woocommerce-no-products-found">
	<?php
	echo gadgetim_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		[
			'icon'    => 'box',
			'title'   => __( 'No products were found', 'gadgetim' ),
			'text'    => __( 'Try another category or browse every product in the shop.', 'gadgetim' ),
			'actions' => [
				[
					'label' => __( 'Browse the shop', 'gadgetim' ),
					'href'  => gadgetim_shop_url(),
					'icon'  => 'arrow',
				],
			],
		]
	);
	?>
</div>
