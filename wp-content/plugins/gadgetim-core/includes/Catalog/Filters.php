<?php
/**
 * Catalogue filters for the shop, category, brand, tag and product-search archives
 * (Aurora theme rebuild): facets, "out of stock last" ordering, "biggest discount" ordering,
 * the live count endpoint, fragment responses and robots rules for filtered URLs.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\ContactMode\ContactMode;
use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\Pricing\Calculator;
use Gadgetim\Core\Pricing\Display;
use Gadgetim\Core\Support\Context;
use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\RateLimit;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;

/**
 * Filters are plain GET parameters, so every state has a real, shareable URL that works without JS:
 *
 *   instock=1              only products in stock (or on backorder)
 *   sale=1                 only products whose discount reaches the strike threshold (10 % by default),
 *                          i.e. the products whose card shows a struck old price
 *   brand=anker,green-lion brand slugs (OR), children of a selected brand included
 *   min_price / max_price  WooCommerce's own price parameters (Toman, ASCII digits; Persian digits and
 *                          separators are normalised by a redirect)
 *   orderby=discount       biggest discount first (besides WooCommerce's popularity/date/price/price-desc)
 *
 * One engine computes the matching products in PHP from WooCommerce's product lookup table
 * (stock status, sale flag, active price), the `_regular_price` meta and the brand relationships:
 * the main archive query receives the result as `post__in`, the facet counts and the public count
 * endpoint use the very same rows, so «نمایش ۲۳ کالا» always equals what the grid then shows.
 *
 * Ordering: in every order of the main product query, out-of-stock products come last
 * (`posts_clauses` on wc_product_meta_lookup.stock_status).
 *
 * Endpoint: GET /wp-json/gadgetim/v1/count?tax=product_cat&term=96&brand=…&lang=fa — public,
 * cacheable (Cache-Control: public, max-age=300), token bucket of 60 requests per minute per hashed IP.
 *
 * Fragments: a GET of any catalogue URL with the header `X-Requested-With: fragment` returns only the
 * results block (toolbar, grid, pagination). The theme renders it on the action
 * `gadgetim_catalog_fragment` (state array); without a theme handler a plain product loop is printed.
 * Fragment responses are never page-cached (DONOTCACHEPAGE, no-store) and carry `X-Robots-Tag: noindex`.
 *
 * Robots: filtered or re-sorted catalogue URLs are `noindex, follow`.
 */
final class Filters {

	public const NAMESPACE = 'gadgetim/v1';

	public const ROUTE = '/count';

	public const RATE_LIMIT = 60;

	public const RATE_WINDOW = 60;

	/** Value of WooCommerce's orderby parameter for the discount ordering. */
	public const ORDERBY_DISCOUNT = 'discount';

	/** Internal WP_Query orderby marker the clauses filter recognises. */
	private const ORDERBY_MARKER = 'gadgetim_discount';

	/** Request header value that asks for the results fragment only. */
	public const FRAGMENT_HEADER = 'fragment';

	/** Query parameters owned by the filters. */
	public const PARAMS = [ 'instock', 'sale', 'brand', 'min_price', 'max_price' ];

	private const MAX_BRANDS = 30;

	private const MAX_PRICE = 1000000000000;

	/** @var array<string,array<int,array<string,mixed>>> Per-request rows by context key. */
	private static array $rows = [];

	/** @var array<int,array<string,mixed>>|null All published products (main query filtering). */
	private static ?array $all_rows = null;

	/** @var array<string,mixed>|null State of the main catalogue query. */
	private static ?array $state = null;

	/**
	 * Hooks only; WooCommerce is not loaded yet when this runs.
	 */
	public static function register(): void {
		add_action( 'parse_request', [ self::class, 'normalize_request' ], 20 );
		add_action( 'woocommerce_product_query', [ self::class, 'product_query' ], 20, 1 );
		add_filter( 'woocommerce_get_catalog_ordering_args', [ self::class, 'ordering_args' ], 30, 3 );
		add_filter( 'posts_clauses', [ self::class, 'clauses' ], 1000, 2 );
		add_filter( 'woocommerce_catalog_orderby', [ self::class, 'orderby_options' ], 20 );
		add_filter( 'wp_robots', [ self::class, 'robots' ], 30 );
		add_action( 'template_redirect', [ self::class, 'template_redirect' ], 1000 );
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/* ---------------------------------------------------------------------
	 * Parameters
	 * ------------------------------------------------------------------- */

	/**
	 * Normalised filters from a parameter source ($_GET by default).
	 *
	 * @param array<string,mixed>|null $source Raw parameters (unslashed).
	 * @return array{instock:bool,sale:bool,brand:string[],min:int,max:int}
	 */
	public static function parse( ?array $source = null ): array {
		if ( null === $source ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only catalogue filters.
			$source = wp_unslash( $_GET );
		}
		$min = self::price_value( $source['min_price'] ?? '' );
		$max = self::price_value( $source['max_price'] ?? '' );
		if ( $min > 0 && $max > 0 && $min > $max ) {
			[ $min, $max ] = [ $max, $min ];
		}
		return [
			'instock' => self::flag( $source['instock'] ?? '' ),
			'sale'    => self::flag( $source['sale'] ?? '' ),
			'brand'   => self::brand_list( $source['brand'] ?? '' ),
			'min'     => $min,
			'max'     => $max,
		];
	}

	/**
	 * Filters of the current request (memoised).
	 *
	 * @return array{instock:bool,sale:bool,brand:string[],min:int,max:int}
	 */
	public static function current(): array {
		static $current = null;
		if ( null === $current ) {
			$current = self::parse();
		}
		return $current;
	}

	/**
	 * Whether any filter is set.
	 *
	 * @param array<string,mixed> $filters Filters from parse().
	 */
	public static function is_active( array $filters ): bool {
		return ! empty( $filters['instock'] ) || ! empty( $filters['sale'] ) || ! empty( $filters['brand'] )
			|| (int) ( $filters['min'] ?? 0 ) > 0 || (int) ( $filters['max'] ?? 0 ) > 0;
	}

	/**
	 * Number of active filter values (each brand counts, the price range counts once).
	 *
	 * @param array<string,mixed> $filters Filters from parse().
	 */
	public static function active_count( array $filters ): int {
		$n  = ! empty( $filters['instock'] ) ? 1 : 0;
		$n += ! empty( $filters['sale'] ) ? 1 : 0;
		$n += count( (array) ( $filters['brand'] ?? [] ) );
		$n += ( (int) ( $filters['min'] ?? 0 ) > 0 || (int) ( $filters['max'] ?? 0 ) > 0 ) ? 1 : 0;
		return $n;
	}

	/**
	 * URL query arguments of a filter set (only the active ones).
	 *
	 * @param array<string,mixed> $filters Filters from parse().
	 * @return array<string,string>
	 */
	public static function query_args( array $filters ): array {
		$args = [];
		if ( ! empty( $filters['instock'] ) ) {
			$args['instock'] = '1';
		}
		if ( ! empty( $filters['sale'] ) ) {
			$args['sale'] = '1';
		}
		if ( ! empty( $filters['brand'] ) ) {
			$args['brand'] = implode( ',', (array) $filters['brand'] );
		}
		if ( (int) ( $filters['min'] ?? 0 ) > 0 ) {
			$args['min_price'] = (string) (int) $filters['min'];
		}
		if ( (int) ( $filters['max'] ?? 0 ) > 0 ) {
			$args['max_price'] = (string) (int) $filters['max'];
		}
		return $args;
	}

	/**
	 * Truthy flag parameter.
	 */
	public static function flag( mixed $value ): bool {
		return is_scalar( $value ) && in_array( strtolower( trim( (string) $value ) ), [ '1', 'on', 'yes', 'true' ], true );
	}

	/**
	 * Brand slugs from "a,b" or [ a, b ]: sanitised, unique, sorted, at most 30.
	 *
	 * @return string[]
	 */
	public static function brand_list( mixed $value ): array {
		$parts = is_array( $value ) ? $value : explode( ',', is_scalar( $value ) ? (string) $value : '' );
		$out   = [];
		foreach ( $parts as $part ) {
			if ( ! is_scalar( $part ) ) {
				continue;
			}
			$slug = sanitize_title( trim( (string) $part ) );
			if ( '' !== $slug ) {
				$out[ $slug ] = $slug;
			}
		}
		$out = array_values( $out );
		sort( $out, SORT_STRING );
		return array_slice( $out, 0, self::MAX_BRANDS );
	}

	/**
	 * A price parameter as a positive integer (Persian/Arabic digits and separators accepted; 0 = unset).
	 */
	public static function price_value( mixed $value ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}
		$text = class_exists( Digits::class ) ? Digits::to_latin( (string) $value ) : (string) $value;
		$text = (string) preg_replace( '/[^0-9]/', '', $text );
		if ( '' === $text ) {
			return 0;
		}
		$text = ltrim( $text, '0' );
		if ( '' === $text ) {
			return 0;
		}
		if ( strlen( $text ) > 13 ) {
			return self::MAX_PRICE;
		}
		return (int) min( self::MAX_PRICE, (int) $text );
	}

	/**
	 * Discount (percent) a product needs to count as "discounted": the strike threshold of the price
	 * display (filter gadgetim_filter_sale_threshold).
	 */
	public static function sale_threshold(): int {
		$strike = 10;
		if ( class_exists( Display::class ) && method_exists( Display::class, 'thresholds' ) ) {
			$strike = (int) ( Display::thresholds()['strike'] ?? 10 );
		}
		/**
		 * Filters the minimum discount (percent) for the "only discounted" filter.
		 *
		 * @param int $strike Default: the strike-through threshold (10).
		 */
		return max( 1, (int) apply_filters( 'gadgetim_filter_sale_threshold', $strike ) );
	}

	/* ---------------------------------------------------------------------
	 * Context (what the archive lists before filtering)
	 * ------------------------------------------------------------------- */

	/**
	 * Product taxonomies a context may name.
	 *
	 * @return string[]
	 */
	public static function taxonomies(): array {
		$taxonomies = function_exists( 'get_object_taxonomies' ) ? get_object_taxonomies( 'product' ) : [];
		return array_values( array_diff( array_map( 'strval', (array) $taxonomies ), [ 'product_type', 'product_visibility', 'product_shipping_class' ] ) );
	}

	/**
	 * Context of a catalogue query: [ tax, term (id), s ].
	 *
	 * @return array{tax:string,term:int,s:string}
	 */
	public static function context_from_query( ?WP_Query $query = null ): array {
		if ( null === $query ) {
			global $wp_query;
			$query = $wp_query instanceof WP_Query ? $wp_query : null;
		}
		$context = [
			'tax'  => '',
			'term' => 0,
			's'    => '',
		];
		if ( ! $query instanceof WP_Query ) {
			return $context;
		}
		if ( $query->is_search() ) {
			$context['s'] = self::sanitize_search( $query->get( 's' ) );
		}
		if ( $query->is_tax( self::taxonomies() ) ) {
			$term = $query->get_queried_object();
			if ( $term instanceof WP_Term ) {
				$context['tax']  = $term->taxonomy;
				$context['term'] = (int) $term->term_id;
			}
		}
		return $context;
	}

	/**
	 * Search words as the archive uses them (tags stripped, capped at 64 characters).
	 */
	public static function sanitize_search( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$s = trim( wp_strip_all_tags( (string) $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, 64, 'UTF-8' ) : substr( $s, 0, 64 );
	}

	/**
	 * Cache key of a context in the current language.
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 */
	private static function context_key( array $context ): string {
		return self::lang() . '|' . $context['tax'] . '|' . $context['term'] . '|' . md5( (string) $context['s'] );
	}

	/**
	 * Current language code ('fa' without the I18n module).
	 */
	private static function lang(): string {
		return class_exists( Languages::class ) ? Languages::current() : 'fa';
	}

	/**
	 * Product IDs of a context (visibility and the language gate applied, like the archive query).
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 * @return int[]
	 */
	private static function context_ids( array $context ): array {
		$search    = '' !== $context['s'];
		$tax_query = [ 'relation' => 'AND' ];
		if ( '' !== $context['tax'] && $context['term'] > 0 ) {
			$tax_query[] = [
				'taxonomy'         => $context['tax'],
				'field'            => 'term_id',
				'terms'            => [ (int) $context['term'] ],
				'include_children' => true,
			];
		}
		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$visibility = wc_get_product_visibility_term_ids();
			$not_in     = [ (int) ( $search ? ( $visibility['exclude-from-search'] ?? 0 ) : ( $visibility['exclude-from-catalog'] ?? 0 ) ) ];
			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$not_in[] = (int) ( $visibility['outofstock'] ?? 0 );
			}
			$not_in = array_values( array_filter( $not_in ) );
			if ( $not_in ) {
				$tax_query[] = [
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => $not_in,
					'operator' => 'NOT IN',
				];
			}
		}
		$args = [
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		];
		if ( $search ) {
			$args['s']               = $context['s'];
			$args['gadgetim_search'] = self::lang();
		}
		$query = new WP_Query( $args );
		return array_values( array_map( 'intval', (array) $query->posts ) );
	}

	/* ---------------------------------------------------------------------
	 * Engine
	 * ------------------------------------------------------------------- */

	/**
	 * Filterable rows of a context: [ id => [ stock, off, pmin, pmax, brands (slugs, ancestors
	 * included), cats (term ids, ancestors included) ] ]. Memoised per request.
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 * @return array<int,array<string,mixed>>
	 */
	public static function rows( array $context ): array {
		$context = self::clean_context( $context );
		$key     = self::context_key( $context );
		if ( ! isset( self::$rows[ $key ] ) ) {
			self::$rows[ $key ] = self::build_rows( self::context_ids( $context ) );
		}
		return self::$rows[ $key ];
	}

	/**
	 * Rows of every published product (the main query restricts to its own context itself).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function all_rows(): array {
		if ( null === self::$all_rows ) {
			global $wpdb;
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::$all_rows = self::build_rows( array_map( 'intval', (array) $ids ) );
		}
		return self::$all_rows;
	}

	/**
	 * Normalised context array.
	 *
	 * @param array<string,mixed> $context Context.
	 * @return array{tax:string,term:int,s:string}
	 */
	private static function clean_context( array $context ): array {
		return [
			'tax'  => (string) ( $context['tax'] ?? '' ),
			'term' => (int) ( $context['term'] ?? 0 ),
			's'    => (string) ( $context['s'] ?? '' ),
		];
	}

	/**
	 * Builds rows for product IDs with four direct queries (lookup table, regular prices, brand and
	 * category relationships) — no WP_Query, so it is safe inside the main query's pre_get_posts.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_rows( array $ids ): array {
		global $wpdb;
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$rows = [];
		if ( ! $ids ) {
			return $rows;
		}
		foreach ( $ids as $id ) {
			$rows[ $id ] = [
				'stock'  => 'instock',
				'onsale' => false,
				'off'    => 0,
				'pmin'   => 0.0,
				'pmax'   => 0.0,
				'brands' => [],
				'cats'   => [],
			];
		}
		$lookup = self::lookup_table();
		$brand  = Brands::TAXONOMY;
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in = implode( ',', array_map( 'intval', $chunk ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer lists and table names only.
			foreach ( (array) $wpdb->get_results( "SELECT product_id, stock_status, onsale, min_price, max_price FROM {$lookup} WHERE product_id IN ({$in})", ARRAY_A ) as $r ) {
				$id = (int) $r['product_id'];
				if ( ! isset( $rows[ $id ] ) ) {
					continue;
				}
				$rows[ $id ]['stock']  = (string) $r['stock_status'];
				$rows[ $id ]['onsale'] = '1' === (string) $r['onsale'];
				$rows[ $id ]['pmin']   = (float) $r['min_price'];
				$rows[ $id ]['pmax']   = (float) $r['max_price'];
			}
			$sale = array_keys( array_filter( array_intersect_key( $rows, array_flip( $chunk ) ), static fn( array $r ): bool => $r['onsale'] ) );
			if ( $sale ) {
				$sale_in = implode( ',', $sale );
				foreach ( (array) $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_regular_price' AND post_id IN ({$sale_in})", ARRAY_A ) as $r ) {
					$id = (int) $r['post_id'];
					if ( isset( $rows[ $id ] ) && is_numeric( $r['meta_value'] ) ) {
						$rows[ $id ]['off'] = self::percent_off( (float) $r['meta_value'], $rows[ $id ]['pmin'] );
					}
				}
				// Variable products keep their regular prices on the variations: the deepest variation discount.
				$missing = array_values( array_filter( $sale, static fn( int $id ): bool => 0 === $rows[ $id ]['off'] ) );
				if ( $missing ) {
					$parents = implode( ',', $missing );
					$sql     = "SELECT p.post_parent, l.min_price, m.meta_value FROM {$wpdb->posts} p INNER JOIN {$lookup} l ON l.product_id = p.ID INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_regular_price' WHERE p.post_type = 'product_variation' AND p.post_status = 'publish' AND l.onsale = 1 AND p.post_parent IN ({$parents})";
					foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) {
						$id = (int) $r['post_parent'];
						if ( isset( $rows[ $id ] ) && is_numeric( $r['meta_value'] ) ) {
							$rows[ $id ]['off'] = max( $rows[ $id ]['off'], self::percent_off( (float) $r['meta_value'], (float) $r['min_price'] ) );
						}
					}
				}
			}
			$terms = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tr.object_id, tt.term_id, tt.taxonomy FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy IN (%s, 'product_cat') AND tr.object_id IN ({$in})",
					$brand
				),
				ARRAY_A
			);
			// phpcs:enable
			$brand_slugs = self::brand_slugs();
			foreach ( $terms as $r ) {
				$id = (int) $r['object_id'];
				if ( ! isset( $rows[ $id ] ) ) {
					continue;
				}
				$term_id = (int) $r['term_id'];
				if ( 'product_cat' === $r['taxonomy'] ) {
					foreach ( self::with_ancestors( $term_id, 'product_cat' ) as $cat ) {
						$rows[ $id ]['cats'][ $cat ] = $cat;
					}
					continue;
				}
				foreach ( self::with_ancestors( $term_id, $brand ) as $brand_id ) {
					if ( isset( $brand_slugs[ $brand_id ] ) ) {
						$rows[ $id ]['brands'][ $brand_slugs[ $brand_id ] ] = $brand_slugs[ $brand_id ];
					}
				}
			}
		}
		foreach ( $rows as &$row ) {
			$row['brands'] = array_values( $row['brands'] );
			$row['cats']   = array_values( $row['cats'] );
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Discount percentage like Pricing\Calculator::percent_off(), but with integer maths for whole
	 * Toman amounts, so a x.5 % discount never flips between requests (float representation).
	 */
	private static function percent_off( float $was, float $now ): int {
		if ( $was <= 0 || $now < 0 || $now >= $was ) {
			return 0;
		}
		if ( floor( $was ) === $was && floor( $now ) === $now ) {
			$was_i = (int) $was;
			$now_i = (int) $now;
			// round( ( was - now ) / was * 100 ), half away from zero.
			return intdiv( 200 * ( $was_i - $now_i ) + $was_i, 2 * $was_i );
		}
		if ( class_exists( Calculator::class ) && method_exists( Calculator::class, 'percent_off' ) ) {
			return (int) Calculator::percent_off( $was, $now );
		}
		return (int) round( ( $was - $now ) / $was * 100 );
	}

	/**
	 * The term and its ancestors (hierarchical taxonomies), memoised.
	 *
	 * @return int[]
	 */
	private static function with_ancestors( int $term_id, string $taxonomy ): array {
		static $memo = [];
		$key = $taxonomy . ':' . $term_id;
		if ( ! isset( $memo[ $key ] ) ) {
			$memo[ $key ] = array_merge( [ $term_id ], array_map( 'intval', get_ancestors( $term_id, $taxonomy, 'taxonomy' ) ) );
		}
		return $memo[ $key ];
	}

	/**
	 * Every brand term, by id (one query per request).
	 *
	 * @return array<int,WP_Term>
	 */
	private static function brand_terms(): array {
		static $terms = null;
		if ( null === $terms ) {
			$terms = [];
			if ( taxonomy_exists( Brands::TAXONOMY ) ) {
				$found = get_terms(
					[
						'taxonomy'   => Brands::TAXONOMY,
						'hide_empty' => false,
					]
				);
				foreach ( is_array( $found ) ? $found : [] as $term ) {
					if ( $term instanceof WP_Term ) {
						$terms[ (int) $term->term_id ] = $term;
					}
				}
			}
		}
		return $terms;
	}

	/**
	 * Brand term id => slug.
	 *
	 * @return array<int,string>
	 */
	private static function brand_slugs(): array {
		return array_map( static fn( WP_Term $t ): string => (string) $t->slug, self::brand_terms() );
	}

	/**
	 * IDs of the rows that pass the filters; $skip names facets to ignore (for facet counts).
	 *
	 * @param array<int,array<string,mixed>> $rows    Rows.
	 * @param array<string,mixed>            $filters Filters from parse().
	 * @param string[]                       $skip    Facets to ignore: instock, sale, brand, price.
	 * @return int[]
	 */
	public static function match( array $rows, array $filters, array $skip = [] ): array {
		$instock   = ! empty( $filters['instock'] ) && ! in_array( 'instock', $skip, true );
		$sale      = ! empty( $filters['sale'] ) && ! in_array( 'sale', $skip, true );
		$brands    = in_array( 'brand', $skip, true ) ? [] : (array) ( $filters['brand'] ?? [] );
		$min       = in_array( 'price', $skip, true ) ? 0 : (int) ( $filters['min'] ?? 0 );
		$max       = in_array( 'price', $skip, true ) ? 0 : (int) ( $filters['max'] ?? 0 );
		$threshold = $sale ? self::sale_threshold() : 0;
		$out       = [];
		foreach ( $rows as $id => $row ) {
			if ( $instock && 'outofstock' === $row['stock'] ) {
				continue;
			}
			if ( $sale && (int) $row['off'] < $threshold ) {
				continue;
			}
			if ( $brands && ! array_intersect( $brands, (array) $row['brands'] ) ) {
				continue;
			}
			if ( $min > 0 || $max > 0 ) {
				$upper = $max > 0 ? $max : PHP_INT_MAX;
				// WooCommerce's rule: the product's price range overlaps [min, max].
				if ( $upper < (float) $row['pmin'] || $min > (float) $row['pmax'] ) {
					continue;
				}
			}
			$out[] = (int) $id;
		}
		return $out;
	}

	/**
	 * Facet counts of a context for a filter set.
	 *
	 * count: products matching every filter; total: products of the context;
	 * instock / sale: matches if that switch were on (the other filters kept);
	 * brands: slug => matches with every filter but the brand one (disjunctive);
	 * price: min, max and steps (deciles of the context's prices, for a proportional slider).
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 * @param array<string,mixed>                 $filters Filters from parse().
	 * @return array{count:int,total:int,instock:int,sale:int,brands:array<string,int>,price:array{min:int,max:int,steps:int[]}}
	 */
	public static function facets( array $context, array $filters ): array {
		$rows   = self::rows( $context );
		$brands = [];
		foreach ( self::match( $rows, $filters, [ 'brand' ] ) as $id ) {
			foreach ( (array) $rows[ $id ]['brands'] as $slug ) {
				$brands[ $slug ] = ( $brands[ $slug ] ?? 0 ) + 1;
			}
		}
		foreach ( self::brand_totals( $rows ) as $slug => $unused ) {
			$brands[ $slug ] = $brands[ $slug ] ?? 0;
		}
		return [
			'count'   => count( self::match( $rows, $filters ) ),
			'total'   => count( $rows ),
			'instock' => count( self::match( $rows, array_merge( $filters, [ 'instock' => true ] ) ) ),
			'sale'    => count( self::match( $rows, array_merge( $filters, [ 'sale' => true ] ) ) ),
			'brands'  => $brands,
			'price'   => self::price_bounds( $rows ),
		];
	}

	/**
	 * Products per brand slug in a set of rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return array<string,int>
	 */
	private static function brand_totals( array $rows ): array {
		$totals = [];
		foreach ( $rows as $row ) {
			foreach ( (array) $row['brands'] as $slug ) {
				$totals[ $slug ] = ( $totals[ $slug ] ?? 0 ) + 1;
			}
		}
		return $totals;
	}

	/**
	 * Price bounds and decile steps of a context.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return array{min:int,max:int,steps:int[]}
	 */
	private static function price_bounds( array $rows ): array {
		$prices = [];
		foreach ( $rows as $row ) {
			if ( (float) $row['pmin'] > 0 ) {
				$prices[] = (int) floor( (float) $row['pmin'] );
			}
			if ( (float) $row['pmax'] > (float) $row['pmin'] ) {
				$prices[] = (int) ceil( (float) $row['pmax'] );
			}
		}
		if ( ! $prices ) {
			return [
				'min'   => 0,
				'max'   => 0,
				'steps' => [],
			];
		}
		sort( $prices, SORT_NUMERIC );
		$n     = count( $prices );
		$steps = [];
		for ( $k = 0; $k <= 10; $k++ ) {
			$steps[] = $prices[ (int) round( $k / 10 * ( $n - 1 ) ) ];
		}
		$steps = array_values( array_unique( $steps ) );
		return [
			'min'   => $prices[0],
			'max'   => $prices[ $n - 1 ],
			'steps' => $steps,
		];
	}

	/**
	 * Brands present in a context, most products first: [ id, slug, name, total ].
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 * @return array<int,array{id:int,slug:string,name:string,total:int}>
	 */
	public static function brands_in( array $context ): array {
		$totals = self::brand_totals( self::rows( $context ) );
		if ( ! $totals ) {
			return [];
		}
		$by_slug = [];
		foreach ( self::brand_terms() as $term ) {
			$by_slug[ (string) $term->slug ] = $term;
		}
		$terms = [];
		foreach ( array_keys( $totals ) as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$terms[] = $by_slug[ $slug ];
			}
		}
		/**
		 * Filters brand terms shown by the storefront (the I18n module keeps only the brands
		 * published in the language). Documented in the theme.
		 *
		 * @param WP_Term[] $terms Terms.
		 * @param string    $lang  Language code.
		 */
		$terms = (array) apply_filters( 'gadgetim_brand_terms', $terms, self::lang() );
		$list  = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term || ! isset( $totals[ $term->slug ] ) ) {
				continue;
			}
			$list[] = [
				'id'    => (int) $term->term_id,
				'slug'  => (string) $term->slug,
				'name'  => html_entity_decode( Brands::name( $term ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'total' => (int) $totals[ $term->slug ],
			];
		}
		usort(
			$list,
			static fn( array $a, array $b ): int => $b['total'] <=> $a['total'] ?: strcasecmp( $a['name'], $b['name'] )
		);
		return $list;
	}

	/**
	 * Products per category (descendants included) in a context: [ term_id => count ].
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 * @return array<int,int>
	 */
	public static function category_counts( array $context ): array {
		$counts = [];
		foreach ( self::rows( $context ) as $row ) {
			foreach ( (array) $row['cats'] as $cat ) {
				$counts[ (int) $cat ] = ( $counts[ (int) $cat ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );
		return $counts;
	}

	/* ---------------------------------------------------------------------
	 * Main query integration
	 * ------------------------------------------------------------------- */

	/**
	 * woocommerce_product_query (main catalogue query, after WooCommerce set it up): restricts the
	 * query to the filtered products.
	 *
	 * @param mixed $query WP_Query.
	 */
	public static function product_query( $query ): void {
		if ( ! $query instanceof WP_Query || ! $query->is_main_query() || is_admin() ) {
			return;
		}
		$filters = self::current();
		if ( ! self::is_active( $filters ) ) {
			return;
		}
		$ids      = self::match( self::all_rows(), $filters );
		$existing = array_values( array_filter( array_map( 'intval', (array) $query->get( 'post__in' ) ) ) );
		if ( $existing ) {
			$ids = array_values( array_intersect( $ids, $existing ) );
		}
		$query->set( 'post__in', $ids ? $ids : [ 0 ] );
	}

	/**
	 * woocommerce_get_catalog_ordering_args: orderby=discount.
	 *
	 * @param array  $args    Ordering args.
	 * @param string $orderby Requested orderby.
	 * @param string $order   Requested order.
	 * @return array
	 */
	public static function ordering_args( $args, $orderby = '', $order = '' ) {
		if ( is_array( $args ) && self::ORDERBY_DISCOUNT === $orderby ) {
			$args['orderby']  = self::ORDERBY_MARKER;
			$args['order']    = 'DESC';
			$args['meta_key'] = ''; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
		return $args;
	}

	/**
	 * posts_clauses (late) on WooCommerce's main product query: out-of-stock products last in every
	 * order, and the discount ordering.
	 *
	 * @param mixed $clauses SQL clauses.
	 * @param mixed $query   WP_Query.
	 * @return mixed
	 */
	public static function clauses( $clauses, $query = null ) {
		if ( ! is_array( $clauses ) || ! $query instanceof WP_Query || 'product_query' !== $query->get( 'wc_query' ) ) {
			return $clauses;
		}
		global $wpdb;
		$join = (string) ( $clauses['join'] ?? '' );
		if ( ! str_contains( $join, 'wc_product_meta_lookup' ) ) {
			$join .= ' LEFT JOIN ' . self::lookup_table() . " wc_product_meta_lookup ON {$wpdb->posts}.ID = wc_product_meta_lookup.product_id ";
		}
		$orderby = trim( (string) ( $clauses['orderby'] ?? '' ) );
		if ( self::ORDERBY_MARKER === $query->get( 'orderby' ) ) {
			$join   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} gtm_reg ON ( gtm_reg.post_id = {$wpdb->posts}.ID AND gtm_reg.meta_key = %s ) ", '_regular_price' );
			$reg     = 'CAST( gtm_reg.meta_value AS DECIMAL(19,4) )';
			$orderby = "CASE WHEN wc_product_meta_lookup.onsale = 1 AND {$reg} > 0 AND wc_product_meta_lookup.min_price < {$reg} THEN ( {$reg} - wc_product_meta_lookup.min_price ) / {$reg} ELSE 0 END DESC, wc_product_meta_lookup.min_price ASC, {$wpdb->posts}.ID DESC";
			if ( empty( $clauses['groupby'] ) ) {
				$clauses['groupby'] = "{$wpdb->posts}.ID";
			}
		}
		$clauses['join']    = $join;
		$clauses['orderby'] = "( COALESCE( wc_product_meta_lookup.stock_status, 'instock' ) = 'outofstock' ) ASC" . ( '' !== $orderby ? ', ' . $orderby : '' );
		return $clauses;
	}

	/**
	 * woocommerce_catalog_orderby (after the theme's list): adds "Biggest discount" unless prices
	 * are hidden (call-for-price mode).
	 *
	 * @param mixed $options Options.
	 * @return mixed
	 */
	public static function orderby_options( $options ) {
		if ( ! is_array( $options ) || isset( $options[ self::ORDERBY_DISCOUNT ] ) || self::prices_hidden() ) {
			return $options;
		}
		$options[ self::ORDERBY_DISCOUNT ] = __( 'Biggest discount', 'gadgetim-core' );
		return $options;
	}

	/**
	 * Whether call-for-price mode is on site-wide (price sorts, the discount sort and the price
	 * filter are then not offered, like the theme's price sorts).
	 */
	public static function prices_hidden(): bool {
		return class_exists( ContactMode::class ) && method_exists( ContactMode::class, 'enabled' ) && ContactMode::enabled();
	}

	/**
	 * Default orderby of an archive: relevance for searches, else WooCommerce's (filtered) default.
	 */
	public static function default_orderby( bool $search = false ): string {
		if ( $search ) {
			return 'relevance';
		}
		return (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
	}

	/**
	 * The requested orderby key, or the default.
	 */
	public static function current_orderby( bool $search = false ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['orderby'] ) && is_scalar( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : '';
		return '' !== $raw ? $raw : self::default_orderby( $search );
	}

	/* ---------------------------------------------------------------------
	 * State and URLs for the theme
	 * ------------------------------------------------------------------- */

	/**
	 * State of the current catalogue page (memoised): context, filters, active flags, orderby,
	 * clean base URL, facets, brands and category counts of the context.
	 *
	 * @return array<string,mixed>
	 */
	public static function state(): array {
		if ( null !== self::$state ) {
			return self::$state;
		}
		$context = self::context_from_query();
		$filters = self::current();
		$search  = '' !== $context['s'];
		$facets  = self::facets( $context, $filters );

		self::$state = [
			'context'         => $context,
			'filters'         => $filters,
			'active'          => self::is_active( $filters ),
			'active_count'    => self::active_count( $filters ),
			'orderby'         => self::current_orderby( $search ),
			'default_orderby' => self::default_orderby( $search ),
			'base_url'        => self::base_url( $context ),
			'facets'          => $facets,
			'brands'          => 'product_brand' === $context['tax'] ? [] : self::brands_in( $context ),
			'threshold'       => self::sale_threshold(),
			'prices_hidden'   => self::prices_hidden(),
			'fragment'        => self::is_fragment_request(),
		];
		return self::$state;
	}

	/**
	 * Clean page-1 URL of a context without filters (keeps the search words and the category
	 * refinement of a search).
	 *
	 * @param array{tax:string,term:int,s:string} $context Context.
	 */
	public static function base_url( array $context ): string {
		$context = self::clean_context( $context );
		if ( '' !== $context['s'] ) {
			$args = [
				's'         => rawurlencode( $context['s'] ),
				'post_type' => 'product',
			];
			if ( 'product_cat' === $context['tax'] && $context['term'] > 0 ) {
				$term = get_term( $context['term'], 'product_cat' );
				if ( $term instanceof WP_Term ) {
					$args['product_cat'] = $term->slug;
				}
			}
			return add_query_arg( $args, home_url( '/' ) );
		}
		if ( '' !== $context['tax'] && $context['term'] > 0 ) {
			$link = get_term_link( $context['term'], $context['tax'] );
			if ( is_string( $link ) ) {
				return $link;
			}
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return (string) wc_get_page_permalink( 'shop' );
		}
		return home_url( '/' );
	}

	/**
	 * URL of the current catalogue page with a filter set and an orderby (default omitted), page 1.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @param string              $orderby Orderby key ('' = keep the current one).
	 */
	public static function url( array $filters, string $orderby = '' ): string {
		$state   = self::state();
		$orderby = '' !== $orderby ? $orderby : (string) $state['orderby'];
		$args    = self::query_args( $filters );
		if ( '' !== $orderby && $orderby !== $state['default_orderby'] ) {
			$args['orderby'] = $orderby;
		}
		return $args ? add_query_arg( $args, (string) $state['base_url'] ) : (string) $state['base_url'];
	}

	/* ---------------------------------------------------------------------
	 * Requests: normalisation redirect, fragments, robots
	 * ------------------------------------------------------------------- */

	/**
	 * parse_request: a no-JS filter form submits brand[]=…, empty prices and Persian digits;
	 * redirect (302) to the canonical parameter form before the main query runs.
	 *
	 * @param mixed $wp WP instance.
	 */
	public static function normalize_request( $wp = null ): void {
		if ( 'cli' === PHP_SAPI || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( class_exists( Context::class ) && Context::is_rest_request() ) ) {
			return;
		}
		if ( 'GET' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only parameters.
		$get = wp_unslash( $_GET );
		if ( ! array_intersect( array_keys( $get ), array_merge( self::PARAMS, [ 'orderby' ] ) ) ) {
			return;
		}
		$clean = self::clean_params( $get );
		if ( null === $clean ) {
			return;
		}
		// The raw request path keeps a language prefix (/en/…); wp_safe_redirect() sanitises it and
		// only allows the site's own host.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = wp_parse_url( home_url( '/' ) );
		$base = ( $home['scheme'] ?? 'http' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		wp_safe_redirect( $base . ( '' !== $path ? $path : '/' ) . ( '' !== $clean ? '?' . $clean : '' ), 302, 'Gadgetim' );
		exit;
	}

	/**
	 * Canonical query string for a parameter set, or null when it already is canonical.
	 *
	 * @param array<string,mixed> $get Unslashed parameters.
	 */
	public static function clean_params( array $get ): ?string {
		$dirty  = false;
		$out    = [];
		$search = isset( $get['s'] ) && '' !== $get['s'];
		$prices = [];
		foreach ( $get as $key => $value ) {
			$key = (string) $key;
			switch ( $key ) {
				case 'instock':
				case 'sale':
					if ( self::flag( $value ) ) {
						$out[ $key ] = '1';
						$dirty       = $dirty || '1' !== $value;
					} else {
						$dirty = true;
					}
					break;
				case 'brand':
					$list = self::brand_list( $value );
					if ( ! $list ) {
						$dirty = true;
						break;
					}
					$as_is = is_array( $value ) ? null : array_map( static fn( string $s ): string => sanitize_title( trim( $s ) ), explode( ',', (string) $value ) );
					$dirty = $dirty || $as_is !== $list;
					// The slugs are already URL-safe (WordPress stores non-Latin slugs percent-encoded).
					$out[ $key ] = implode( ',', $list );
					break;
				case 'min_price':
				case 'max_price':
					$n = self::price_value( $value );
					if ( $n <= 0 ) {
						$dirty = true;
						break;
					}
					$prices[ $key ] = $n;
					$dirty          = $dirty || ! is_string( $value ) || (string) $n !== $value;
					$out[ $key ]    = (string) $n;
					break;
				case 'orderby':
					$ob = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
					if ( '' === $ob || ( ! $search && self::default_orderby() === $ob ) ) {
						$dirty = true;
						break;
					}
					$dirty       = $dirty || $ob !== $value;
					$out[ $key ] = $ob;
					break;
				default:
					$out[ $key ] = $value;
			}
		}
		if ( isset( $prices['min_price'], $prices['max_price'] ) && $prices['min_price'] > $prices['max_price'] ) {
			$out['min_price'] = (string) $prices['max_price'];
			$out['max_price'] = (string) $prices['min_price'];
			$dirty            = true;
		}
		if ( ! $dirty ) {
			return null;
		}
		$parts = [];
		foreach ( $out as $key => $value ) {
			if ( is_array( $value ) ) {
				$parts[] = http_build_query( [ $key => $value ], '', '&', PHP_QUERY_RFC3986 );
			} elseif ( 'brand' === $key ) {
				$parts[] = 'brand=' . $value;
			} else {
				$parts[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
			}
		}
		return implode( '&', $parts );
	}

	/**
	 * Whether this request asks for the results fragment only.
	 */
	public static function is_fragment_request(): bool {
		$value = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ? strtolower( sanitize_key( wp_unslash( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) ) : '';
		return self::FRAGMENT_HEADER === $value;
	}

	/**
	 * Whether the main query is a product catalogue page (shop, product taxonomy, product search).
	 */
	public static function is_catalog(): bool {
		if ( ! function_exists( 'is_shop' ) || is_admin() || is_404() ) {
			return false;
		}
		return is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) );
	}

	/**
	 * template_redirect (late): Vary header on catalogue pages; fragment requests get only the
	 * results block (theme action gadgetim_catalog_fragment) and exit.
	 */
	public static function template_redirect(): void {
		if ( ! self::is_catalog() ) {
			return;
		}
		if ( ! headers_sent() ) {
			header( 'Vary: X-Requested-With', false );
		}
		if ( ! self::is_fragment_request() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
			header( 'X-Robots-Tag: noindex, follow' );
		}
		if ( has_action( 'gadgetim_catalog_fragment' ) ) {
			/**
			 * Prints the results fragment of a catalogue page (toolbar, grid, pagination).
			 *
			 * @param array $state Filters::state().
			 */
			do_action( 'gadgetim_catalog_fragment', self::state() );
		} else {
			self::default_fragment();
		}
		exit;
	}

	/**
	 * Fallback fragment without a theme handler: WooCommerce's own loop.
	 */
	private static function default_fragment(): void {
		echo '<div class="results" data-results>';
		if ( function_exists( 'wc_setup_loop' ) ) {
			wc_setup_loop();
		}
		if ( have_posts() && function_exists( 'woocommerce_product_loop_start' ) ) {
			woocommerce_product_loop_start();
			while ( have_posts() ) {
				the_post();
				wc_get_template_part( 'content', 'product' );
			}
			woocommerce_product_loop_end();
			woocommerce_pagination();
		} elseif ( function_exists( 'wc_no_products_found' ) ) {
			wc_no_products_found();
		}
		echo '</div>';
	}

	/**
	 * wp_robots: filtered or re-sorted catalogue URLs are noindex, follow.
	 *
	 * @param mixed $robots Directives.
	 * @return mixed
	 */
	public static function robots( $robots ) {
		if ( ! is_array( $robots ) || ! self::is_catalog() ) {
			return $robots;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sorted = isset( $_GET['orderby'] ) && ! is_search();
		if ( self::is_active( self::current() ) || $sorted ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/* ---------------------------------------------------------------------
	 * REST: GET /gadgetim/v1/count
	 * ------------------------------------------------------------------- */

	/**
	 * rest_api_init.
	 */
	public static function register_routes(): void {
		$text = [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => static fn( $v ): string => is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '',
		];
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ self::class, 'handle_count' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'tax'       => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => static fn( $v ): bool => ! is_string( $v ) || '' === $v || in_array( sanitize_key( $v ), self::taxonomies(), true ),
						],
						'term'      => [
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						's'         => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => [ self::class, 'sanitize_search' ],
						],
						'instock'   => $text,
						'sale'      => $text,
						'brand'     => $text,
						'min_price' => $text,
						'max_price' => $text,
						'lang'      => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => static fn( $v ): bool => ! is_string( $v ) || '' === $v || ! class_exists( Languages::class ) || Languages::exists( sanitize_key( $v ) ),
						],
					],
				],
			]
		);
	}

	/**
	 * GET handler: { count, total, instock, sale, brands: { slug: n }, price: { min, max } }.
	 */
	public static function handle_count( WP_REST_Request $request ): WP_REST_Response {
		if ( ! RateLimit::allow( 'count', self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			$response = new WP_REST_Response(
				[
					'code'    => 'gadgetim_rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'gadgetim-core' ),
					'data'    => [ 'status' => 429 ],
				],
				429
			);
			$response->header( 'Retry-After', (string) max( 1, RateLimit::retry_after( 'count', self::RATE_LIMIT, self::RATE_WINDOW ) ) );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		$tax  = (string) $request->get_param( 'tax' );
		$term = (int) $request->get_param( 'term' );
		if ( '' !== $tax || $term > 0 ) {
			if ( '' === $tax || $term <= 0 || ! in_array( $tax, self::taxonomies(), true ) || ! term_exists( $term, $tax ) ) {
				$response = new WP_REST_Response(
					[
						'code'    => 'gadgetim_unknown_term',
						'message' => __( 'Unknown catalogue section.', 'gadgetim-core' ),
						'data'    => [ 'status' => 404 ],
					],
					404
				);
				$response->header( 'Cache-Control', 'public, max-age=300' );
				return $response;
			}
		}
		$context = [
			'tax'  => $tax,
			'term' => $term,
			's'    => (string) $request->get_param( 's' ),
		];
		$filters = self::parse(
			[
				'instock'   => $request->get_param( 'instock' ),
				'sale'      => $request->get_param( 'sale' ),
				'brand'     => $request->get_param( 'brand' ),
				'min_price' => $request->get_param( 'min_price' ),
				'max_price' => $request->get_param( 'max_price' ),
			]
		);
		$facets = self::facets( $context, $filters );

		$response = new WP_REST_Response(
			[
				'count'   => $facets['count'],
				'total'   => $facets['total'],
				'instock' => $facets['instock'],
				'sale'    => $facets['sale'],
				'brands'  => (object) $facets['brands'],
				'price'   => [
					'min' => $facets['price']['min'],
					'max' => $facets['price']['max'],
				],
				'lang'    => self::lang(),
			],
			200
		);
		$response->header( 'Cache-Control', 'public, max-age=300' );
		$response->header( 'Vary', 'X-Gadgetim-Lang' );
		return $response;
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------- */

	/**
	 * WooCommerce's product lookup table name.
	 */
	private static function lookup_table(): string {
		global $wpdb;
		return isset( $wpdb->wc_product_meta_lookup ) && is_string( $wpdb->wc_product_meta_lookup )
			? $wpdb->wc_product_meta_lookup
			: $wpdb->prefix . 'wc_product_meta_lookup';
	}

	/**
	 * Forgets the per-request memo (tests, CLI loops).
	 */
	public static function flush(): void {
		self::$rows     = [];
		self::$all_rows = null;
		self::$state    = null;
	}
}
