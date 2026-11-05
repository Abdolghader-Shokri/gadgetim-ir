<?php
/**
 * Result Count
 *
 * Shows text: Showing x - x of x results.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/result-count.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): «نمایش ۱–۲۴ از ۱۰۷ کالا» in locale digits (the catalogue toolbar prints its
 * own count; this override serves other callers of woocommerce_result_count()).
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     10.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="woocommerce-result-count rcount" role="status" aria-relevant="all" <?php echo ( empty( $orderedby ) || 1 === intval( $total ) ) ? '' : 'data-is-sorted-by="true"'; ?>>
	<?php
	if ( $total <= $per_page || -1 === $per_page ) {
		echo esc_html( gadgetim_count_label( (int) $total ) );
	} else {
		$gadgetim_first = ( $per_page * $current ) - $per_page + 1;
		$gadgetim_last  = min( $total, $per_page * $current );
		/* translators: 1: first result, 2: last result, 3: total results */
		echo esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s products', 'gadgetim' ), gadgetim_number( $gadgetim_first ), gadgetim_number( $gadgetim_last ), gadgetim_number( (int) $total ) ) );
	}
	if ( ! empty( $orderedby ) && 1 !== intval( $total ) ) {
		echo '<span class="sr-only">' . esc_html( (string) $orderedby ) . '</span>';
	}
	?>
</p>
