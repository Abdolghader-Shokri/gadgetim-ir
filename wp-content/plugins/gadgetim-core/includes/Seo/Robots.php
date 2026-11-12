<?php
/**
 * robots.txt rules for parameter URLs and noindex for archives with no shopper value.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

/**
 * The catalogue links every sort order and filter combination as a real URL. Those pages are
 * already noindex,follow (Catalog\Filters), yet a crawl of the local copy on 2026-11-12 found
 * 3,476 HTML URLs for about 620 real pages: crawlers spent most of their visits on them.
 * They are now kept out of the crawl. Author and date archives repeat the shop and are noindex.
 */
final class Robots {

	/**
	 * Query parameters that only re-sort or narrow a listing, or are WordPress plumbing.
	 */
	public const BLOCKED_PARAMS = [ 'orderby', 'min_price', 'max_price', 'instock', 'sale', 'on_sale', 'brand', 'filter_', 'query_type_', 'replytocom', 's' ];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'robots_txt', [ self::class, 'filter_robots_txt' ], 30, 2 );
		add_filter( 'wp_robots', [ self::class, 'filter_robots' ], 40, 1 );
	}

	/**
	 * robots_txt: one Disallow line per parameter, in the first "User-agent: *" group.
	 *
	 * @param mixed $output Robots.txt body.
	 * @param mixed $public blog_public.
	 * @return mixed
	 */
	public static function filter_robots_txt( $output, $public = true ) {
		if ( ! is_string( $output ) || ! $public ) {
			return $output;
		}
		$lines = '';
		foreach ( self::BLOCKED_PARAMS as $param ) {
			foreach ( [ '?', '&' ] as $joiner ) {
				$rule = 'Disallow: /*' . $joiner . $param . ( str_ends_with( $param, '_' ) ? '' : '=' ) . "\n";
				if ( false === strpos( $output, $rule ) ) {
					$lines .= $rule;
				}
			}
		}
		if ( '' === $lines ) {
			return $output;
		}
		if ( preg_match( '/^User-agent: \*\R/m', $output ) ) {
			return preg_replace( '/^(User-agent: \*\R)/m', '$1' . $lines, $output, 1 ) ?? $output;
		}
		return "User-agent: *\n" . $lines . "\n" . $output;
	}

	/**
	 * Taxonomies whose archives are never indexed: the two product tags duplicate category
	 * names and hold one product each (2026-11-12), so they only compete with the categories.
	 */
	public const NOINDEX_TAXONOMIES = [ 'product_tag' ];

	/**
	 * wp_robots: noindex,follow on author and date archives, on product-tag archives and on any
	 * term or shop archive that lists nothing (eleven empty categories and nine empty brands
	 * answered 200 with an empty grid: soft 404s).
	 *
	 * @param mixed $robots Directives.
	 * @return mixed
	 */
	public static function filter_robots( $robots ) {
		if ( ! is_array( $robots ) ) {
			return $robots;
		}
		if ( is_author() || is_date() || self::is_noindex_taxonomy() || self::is_empty_listing() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Whether the request is an archive of a never-indexed taxonomy.
	 */
	private static function is_noindex_taxonomy(): bool {
		foreach ( self::NOINDEX_TAXONOMIES as $taxonomy ) {
			if ( is_tax( $taxonomy ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a term or shop archive (first page) found no products at all.
	 */
	private static function is_empty_listing(): bool {
		if ( ! ( is_tax() || is_category() || is_tag() || ( function_exists( 'is_shop' ) && is_shop() ) ) || is_search() ) {
			return false;
		}
		global $wp_query;
		return $wp_query instanceof \WP_Query && 0 === (int) $wp_query->found_posts && 0 === (int) $wp_query->post_count;
	}
}
