<?php
/**
 * Results block: toolbar, active filters, the product grid, load more + pagination, or the empty
 * state, plus the facet counts as JSON for filters.js. The same template answers the plugin's
 * fragment requests (header X-Requested-With: fragment), so a swap replaces this element whole.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

global $wp_query;

$gadgetim_state    = gadgetim_archive_state();
$gadgetim_fragment = gadgetim_archive_is_fragment();
$gadgetim_facets   = (array) $gadgetim_state['facets'];
$gadgetim_total    = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
$gadgetim_pages    = $wp_query instanceof WP_Query ? max( 1, (int) $wp_query->max_num_pages ) : 1;
$gadgetim_page     = max( 1, (int) get_query_var( 'paged' ) );
$gadgetim_per      = $wp_query instanceof WP_Query ? max( 1, (int) $wp_query->get( 'posts_per_page' ) ) : 24;

if ( function_exists( 'wc_setup_loop' ) ) {
	wc_setup_loop();
}
// WooCommerce's own count, ordering form, pagination and notice stay quiet in this loop.
gadgetim_archive_quiet_wc_parts( true );

$gadgetim_json = [
	'count'       => $gadgetim_total,
	'total'       => (int) ( $gadgetim_facets['total'] ?? 0 ),
	'instock'     => (int) ( $gadgetim_facets['instock'] ?? 0 ),
	'sale'        => (int) ( $gadgetim_facets['sale'] ?? 0 ),
	'brands'      => (object) ( $gadgetim_facets['brands'] ?? [] ),
	'active'      => (int) $gadgetim_state['active_count'],
	'orderby'     => gadgetim_archive_orderby(),
	'page'        => $gadgetim_page,
	'pages'       => $gadgetim_pages,
	'first'       => $gadgetim_total > 0 ? ( $gadgetim_page - 1 ) * $gadgetim_per + 1 : 0,
	'last'        => min( $gadgetim_total, $gadgetim_page * $gadgetim_per ),
	'title'       => html_entity_decode( wp_get_document_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
];
?>
<section class="results" id="results" data-results aria-labelledby="results-title">
	<?php gadgetim_part( 'archive/toolbar' ); ?>
	<?php gadgetim_part( 'archive/actives' ); ?>
	<?php
	if ( ! $gadgetim_fragment ) {
		/**
		 * Hook: woocommerce_before_shop_loop.
		 *
		 * @hooked woocommerce_output_all_notices - 10
		 * @hooked wc_setup_loop - 10
		 */
		do_action( 'woocommerce_before_shop_loop' );
	}

	if ( have_posts() ) {
		// First row: the first four images load eagerly, the first one with fetchpriority=high.
		gadgetim_set_eager_budget( $gadgetim_fragment ? 0 : 4, ! $gadgetim_fragment );

		woocommerce_product_loop_start();
		while ( have_posts() ) {
			the_post();

			/**
			 * Hook: woocommerce_shop_loop.
			 */
			do_action( 'woocommerce_shop_loop' );

			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();

		/**
		 * Hook: woocommerce_after_shop_loop (WooCommerce's pagination is replaced by more.php).
		 */
		do_action( 'woocommerce_after_shop_loop' );

		gadgetim_part(
			'archive/more',
			[
				'page'  => $gadgetim_page,
				'pages' => $gadgetim_pages,
				'first' => $gadgetim_json['first'],
				'last'  => $gadgetim_json['last'],
				'total' => $gadgetim_total,
			]
		);
	} else {
		/**
		 * Hook: woocommerce_no_products_found (WooCommerce's notice is replaced by empty.php).
		 */
		do_action( 'woocommerce_no_products_found' );

		gadgetim_part( 'archive/empty' );
	}
	gadgetim_set_eager_budget( 0 );
	gadgetim_archive_quiet_wc_parts( false );
	?>
	<script type="application/json" data-facets><?php echo wp_json_encode( $gadgetim_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with the HEX flags. ?></script>
</section>
