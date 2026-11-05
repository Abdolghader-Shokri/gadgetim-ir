<?php
/**
 * Core sitemaps per language (architecture §3.7).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

/**
 * /wp-sitemap.xml, /en/wp-sitemap.xml and /ar/wp-sitemap.xml are the indexes (the prefixed
 * ones resolve through the home_url prefix). Under a prefix the posts and taxonomies
 * providers only list items published in that language; cart/checkout/account pages are
 * excluded everywhere; a non-public language serves no sitemap (404). lastmod is untouched.
 */
final class Sitemaps {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_sitemaps_enabled', [ self::class, 'filter_enabled' ], 20, 1 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'filter_post_types' ], 20, 1 );
		add_filter( 'wp_sitemaps_posts_query_args', [ self::class, 'filter_posts_args' ], 20, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', [ self::class, 'filter_taxonomies_args' ], 20, 2 );
	}

	/**
	 * Launch gate: no sitemap for a non-public language.
	 *
	 * @param mixed $enabled Whether sitemaps are enabled.
	 */
	public static function filter_enabled( $enabled ) {
		if ( ! $enabled || Languages::is_default() ) {
			return $enabled;
		}
		return Languages::is_public( Languages::current() );
	}

	/**
	 * Products are listed; variations never.
	 *
	 * @param mixed $post_types [ name => WP_Post_Type ].
	 */
	public static function filter_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			return $post_types;
		}
		if ( ! isset( $post_types['product'] ) && post_type_exists( 'product' ) ) {
			$product = get_post_type_object( 'product' );
			if ( $product ) {
				$post_types['product'] = $product;
			}
		}
		unset( $post_types['product_variation'] );
		return $post_types;
	}

	/**
	 * Posts provider: exclude the WooCommerce transactional pages; gate by translation status.
	 *
	 * @param mixed  $args      WP_Query args.
	 * @param string $post_type Post type.
	 */
	public static function filter_posts_args( $args, $post_type = '' ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		if ( 'page' === $post_type ) {
			$excluded = self::excluded_pages();
			if ( $excluded ) {
				$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? [] ), $excluded ) ) );
			}
		}
		$lang = Languages::current();
		if ( Languages::DEFAULT !== $lang && PostTranslations::supports( (string) $post_type ) ) {
			$meta   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];
			$meta[] = [
				'key'     => PostTranslations::key( 'status', $lang ),
				'value'   => 'published',
				'compare' => '=',
			];
			$args['meta_query']     = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['gadgetim_gated'] = $lang;
		}
		return $args;
	}

	/**
	 * Taxonomies provider: gate by term status and by having published content.
	 *
	 * @param mixed  $args     get_terms args.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function filter_taxonomies_args( $args, $taxonomy = '' ) {
		if ( ! is_array( $args ) || Languages::is_default() || ! TermTranslations::supports( (string) $taxonomy ) ) {
			return $args;
		}
		$lang   = Languages::current();
		$meta   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];
		$meta[] = [
			'key'     => TermTranslations::key( 'status', $lang ),
			'value'   => 'published',
			'compare' => '=',
		];
		$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$published = Slugs::published_ids( $lang, Visibility::type_for_taxonomy( (string) $taxonomy ) );
		// Terms whose archive would be empty in this language are left out (object_ids restricts
		// to terms assigned to the published items; [0] matches nothing).
		$args['object_ids'] = $published ? $published : [ 0 ];
		return $args;
	}

	/**
	 * IDs of the transactional pages (cart, checkout, my-account and their legacy children).
	 *
	 * @return int[]
	 */
	public static function excluded_pages(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return [];
		}
		$ids = [];
		foreach ( [ 'cart', 'checkout', 'myaccount', 'view_order', 'logout' ] as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
