<?php
/**
 * Show options for ordering
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/orderby.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the theme's sort select (the catalogue toolbar prints its own sort chips;
 * this override serves other callers of woocommerce_catalog_ordering()). The "Sort" button
 * submits the form.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id_suffix = wp_unique_id();

?>
<form class="woocommerce-ordering sortf" method="get">
	<label class="<?php echo $use_label ? 'sortf__label' : 'sr-only'; ?>" for="woocommerce-orderby-<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Sort by', 'gadgetim' ); ?></label>
	<span class="sortf__field">
		<?php gadgetim_the_icon( 'sort' ); ?>
		<select name="orderby" class="orderby sortf__select" id="woocommerce-orderby-<?php echo esc_attr( $id_suffix ); ?>">
			<?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
	</span>
	<button class="btn btn--sm btn--soft sortf__go" type="submit"><?php esc_html_e( 'Sort', 'gadgetim' ); ?></button>
	<input type="hidden" name="paged" value="1" />
	<?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
</form>
