<?php
/**
 * "Popular" catalog ordering: total_sales first, sampled views as the tiebreaker (architecture §6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use WP_Query;

/**
 * Replaces WooCommerce's popularity clauses (total_sales DESC, product_id DESC) with
 * total_sales DESC, _gtm_views DESC, ID DESC. The views join is a LEFT JOIN with COALESCE,
 * so a product without the meta is never dropped (the meta is also seeded with 0, §6).
 */
final class Popularity {

	/**
	 * Hooks the ordering filter after WooCommerce's own switch.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_get_catalog_ordering_args', [ self::class, 'ordering_args' ], 20, 3 );
	}

	/**
	 * woocommerce_get_catalog_ordering_args: swap the popularity clauses.
	 *
	 * @param array  $args    Query args.
	 * @param string $orderby Requested orderby.
	 * @param string $order   Requested order.
	 */
	public static function ordering_args( array $args, string $orderby = '', string $order = '' ): array {
		if ( 'popularity' !== $orderby ) {
			return $args;
		}
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( is_object( $wc ) && isset( $wc->query ) && is_object( $wc->query ) ) {
			remove_filter( 'posts_clauses', [ $wc->query, 'order_by_popularity_post_clauses' ] );
		}
		add_filter( 'posts_clauses', [ self::class, 'clauses' ], 10, 2 );
		return $args;
	}

	/**
	 * posts_clauses: one-shot, removes itself after the product query it was armed for.
	 *
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query   Query.
	 */
	public static function clauses( array $clauses, WP_Query $query ): array {
		global $wpdb;

		remove_filter( 'posts_clauses', [ self::class, 'clauses' ], 10 );

		$lookup = isset( $wpdb->wc_product_meta_lookup ) && is_string( $wpdb->wc_product_meta_lookup )
			? $wpdb->wc_product_meta_lookup
			: $wpdb->prefix . 'wc_product_meta_lookup';

		$join = (string) ( $clauses['join'] ?? '' );
		if ( ! str_contains( $join, 'wc_product_meta_lookup' ) ) {
			$join .= " LEFT JOIN {$lookup} wc_product_meta_lookup ON {$wpdb->posts}.ID = wc_product_meta_lookup.product_id ";
		}
		$join .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} gtm_views ON ( gtm_views.post_id = {$wpdb->posts}.ID AND gtm_views.meta_key = %s ) ",
			ProductExtras::META_VIEWS
		);
		$clauses['join']    = $join;
		$clauses['orderby'] = ' COALESCE( wc_product_meta_lookup.total_sales, 0 ) DESC, CAST( COALESCE( gtm_views.meta_value, 0 ) AS UNSIGNED ) DESC, ' . $wpdb->posts . '.ID DESC ';

		return $clauses;
	}
}
