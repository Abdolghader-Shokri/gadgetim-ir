<?php
/**
 * Archive page layer: shop, product category, brand, tag and product-search archives.
 *
 * Templates: woocommerce/archive-product.php (page), woocommerce/content-product.php (card),
 * woocommerce/loop/* (compatibility overrides), templates/archive/* (hero, filters, sheet, results,
 * toolbar, active filters, load more, empty state, description). Styles: assets/css/archive.css.
 * Script: assets/js/modules/filters.js (loaded by app.js on data-page="archive").
 *
 * Data comes from the plugin class Gadgetim\Core\Catalog\Filters (facets, counts, the
 * GET /wp-json/gadgetim/v1/count endpoint, fragment responses, out-of-stock-last and discount
 * ordering, noindex on filtered URLs). Without the plugin the archive still renders: hero, sorting,
 * grid and pagination, no filter panel.
 *
 * What this file wires:
 * - unhooks the foundation's loop card (content-product.php renders gadgetim_product_card() itself)
 *   and WooCommerce's taxonomy header, archive description and subcategory tiles; its result
 *   count, ordering form, pagination and "no products" notice are quiet around the main
 *   catalogue loop only (shortcode loops keep them);
 * - sort options «پربازدیدترین · جدیدترین · ارزان‌ترین · گران‌ترین · بیشترین تخفیف» (popularity is the
 *   default while WooCommerce's own default is still "menu_order"; relevance first on searches);
 * - the results fragment (action gadgetim_catalog_fragment, fired by the plugin);
 * - #gadgetim-config strings for filters.js, the density pre-paint class, the LCP image.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/* ---------------------------------------------------------------------------
 * Hooks
 * ------------------------------------------------------------------------- */

/**
 * init 30 (after the foundation's WooCommerce wiring at 20): replace the default archive parts.
 */
function gadgetim_archive_hooks(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	// content-product.php prints the card for every loop (archive, related, up-sells, cross-sells).
	remove_action( 'woocommerce_before_shop_loop_item', 'gadgetim_wc_loop_card', 10 );

	// Archive-only parts (the hero, the description block and the chips replace them).
	remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
	remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
	remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
	remove_filter( 'woocommerce_product_loop_start', 'woocommerce_maybe_show_product_subcategories' );
}
add_action( 'init', 'gadgetim_archive_hooks', 30 );

/**
 * WooCommerce's result count, ordering form, pagination and "no products" notice, switched off
 * around the main catalogue loop only (templates/archive/results.php prints the theme's own), so
 * paginated [products] shortcodes keep theirs.
 *
 * @param bool $quiet True before the main loop, false after it.
 */
function gadgetim_archive_quiet_wc_parts( bool $quiet ): void {
	$parts = [
		[ 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 ],
		[ 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 ],
		[ 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 ],
		[ 'woocommerce_no_products_found', 'wc_no_products_found', 10 ],
	];
	static $removed = [];
	foreach ( $parts as [ $hook, $callback, $priority ] ) {
		$key = $hook . '|' . $callback;
		if ( $quiet ) {
			if ( has_action( $hook, $callback ) === $priority ) {
				remove_action( $hook, $callback, $priority );
				$removed[ $key ] = true;
			}
		} elseif ( ! empty( $removed[ $key ] ) ) {
			add_action( $hook, $callback, $priority );
			unset( $removed[ $key ] );
		}
	}
}

add_filter( 'woocommerce_default_catalog_orderby', 'gadgetim_archive_default_orderby', 20 );
add_filter( 'woocommerce_catalog_orderby', 'gadgetim_archive_orderby_options', 30 );
add_filter( 'woocommerce_product_loop_start', 'gadgetim_archive_loop_start', 20 );
add_action( 'gadgetim_catalog_fragment', 'gadgetim_archive_fragment' );
add_filter( 'gadgetim_config', 'gadgetim_archive_config' );
add_action( 'wp_head', 'gadgetim_archive_prepaint', 0 );
add_filter( 'gadgetim_lcp_image', 'gadgetim_archive_lcp' );
add_filter( 'body_class', 'gadgetim_archive_body_class' );
add_filter( 'gadgetim_breadcrumbs', 'gadgetim_archive_breadcrumbs' );

/**
 * Breadcrumb labels on catalogue pages: WooCommerce's search crumb carries entities
 * (&ldquo;jbl&rdquo;) that the renderer would escape a second time.
 *
 * @param mixed $crumbs [ [ label, url ], … ].
 * @return mixed
 */
function gadgetim_archive_breadcrumbs( $crumbs ) {
	if ( ! is_array( $crumbs ) || 'archive' !== gadgetim_page_kind() ) {
		return $crumbs;
	}
	foreach ( $crumbs as &$crumb ) {
		if ( is_array( $crumb ) && isset( $crumb[0] ) ) {
			$crumb[0] = html_entity_decode( (string) $crumb[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
	}
	unset( $crumb );
	if ( is_search() && $crumbs ) {
		$last = array_key_last( $crumbs );
		if ( is_array( $crumbs[ $last ] ) && isset( $crumbs[ $last ][0] ) ) {
			$crumbs[ $last ][0] = gadgetim_isolate_query( (string) $crumbs[ $last ][0], (string) get_search_query( false ) );
		}
	}
	return $crumbs;
}

/**
 * Default sort: WooCommerce ships "menu_order", which in this catalogue is alphabetical (every
 * menu_order is 0). Most viewed / best selling is what the storefront promises first.
 *
 * @param mixed $value Default orderby.
 * @return mixed
 */
function gadgetim_archive_default_orderby( $value ) {
	return ( '' === $value || 'menu_order' === $value ) ? 'popularity' : $value;
}

/**
 * Sort options in storefront order; "Default order" and "rating" removed.
 *
 * @param mixed $options Options (key => label).
 * @return mixed
 */
function gadgetim_archive_orderby_options( $options ) {
	if ( ! is_array( $options ) ) {
		return $options;
	}
	unset( $options['menu_order'], $options['rating'] );
	$order  = [ 'relevance', 'popularity', 'date', 'price', 'price-desc', 'discount' ];
	$sorted = [];
	foreach ( $order as $key ) {
		if ( isset( $options[ $key ] ) ) {
			$sorted[ $key ] = $options[ $key ];
		}
	}
	return $sorted + $options;
}

/**
 * The main grid <ul>: archive classes, density and the live-region target.
 */
function gadgetim_archive_loop_start( string $html ): string {
	if ( 'archive' !== gadgetim_page_kind() || '' !== (string) wc_get_loop_prop( 'name' ) ) {
		return $html;
	}
	$swapped = preg_replace( '/class="products pgrid"/', 'class="products pgrid arch__grid" data-grid', $html, 1 );
	return is_string( $swapped ) ? $swapped : $html;
}

/**
 * Body classes on catalogue pages.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function gadgetim_archive_body_class( array $classes ): array {
	if ( 'archive' === gadgetim_page_kind() ) {
		$classes[] = gadgetim_archive_filters_ready() ? 'has-filters' : 'no-filters';
		// WordPress's "search" body class collides with the header search component (.search in
		// layout.css) and turns <body> into the search bar; product searches keep "search-results".
		$classes = array_values( array_diff( $classes, [ 'search' ] ) );
		if ( is_search() ) {
			$classes[] = 'is-product-search';
		}
	}
	return $classes;
}

/**
 * wp_head 0: the grid density chosen on this device (localStorage), before first paint.
 */
function gadgetim_archive_prepaint(): void {
	if ( 'archive' !== gadgetim_page_kind() ) {
		return;
	}
	echo '<script id="gadgetim-density">try{if(localStorage.getItem("gtm_dense")==="1"){document.documentElement.classList.add("is-dense")}}catch(e){}</script>' . "\n";
}

/**
 * LCP preload on catalogue pages: the first product's image (the first card loads eagerly with
 * fetchpriority=high).
 *
 * @param mixed $lcp LCP image.
 * @return mixed
 */
function gadgetim_archive_lcp( $lcp ) {
	if ( 'archive' !== gadgetim_page_kind() || ! empty( $lcp ) ) {
		return $lcp;
	}
	global $wp_query;
	$first = $wp_query instanceof WP_Query && ! empty( $wp_query->posts[0] ) ? $wp_query->posts[0] : null;
	$id    = $first instanceof WP_Post ? (int) get_post_thumbnail_id( $first ) : 0;
	return $id > 0 ? [ $id, 'gtm-card', 'card' ] : $lcp;
}

/* ---------------------------------------------------------------------------
 * State (plugin API with a no-plugin fallback)
 * ------------------------------------------------------------------------- */

/**
 * Whether the filters engine (gadgetim-core Catalog\Filters) is available.
 */
function gadgetim_archive_filters_ready(): bool {
	return class_exists( 'Gadgetim\Core\Catalog\Filters' ) && function_exists( 'wc_get_product' );
}

/**
 * Whether the page shows the filter panel: the engine is available and the context lists at
 * least two products.
 */
function gadgetim_archive_has_filters(): bool {
	$state = gadgetim_archive_state();
	return ! empty( $state['available'] ) && (int) ( $state['facets']['total'] ?? 0 ) > 1;
}

/**
 * Whether this request asks only for the results fragment (header X-Requested-With: fragment).
 */
function gadgetim_archive_is_fragment(): bool {
	if ( gadgetim_archive_filters_ready() ) {
		return Gadgetim\Core\Catalog\Filters::is_fragment_request();
	}
	return isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'fragment' === strtolower( sanitize_key( wp_unslash( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) );
}

/**
 * State of the current catalogue page: context (tax, term, s), filters, active flags, orderby,
 * base URL, facets (count, total, instock, sale, brands, price), brand list, threshold.
 *
 * @return array<string,mixed>
 */
function gadgetim_archive_state(): array {
	static $state = null;
	if ( null !== $state ) {
		return $state;
	}
	if ( gadgetim_archive_filters_ready() ) {
		$state              = Gadgetim\Core\Catalog\Filters::state();
		$state['available'] = true;
		return $state;
	}
	global $wp_query;
	$found = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
	$term  = is_tax() ? get_queried_object() : null;
	$state = [
		'available'       => false,
		'context'         => [
			'tax'  => $term instanceof WP_Term ? $term->taxonomy : '',
			'term' => $term instanceof WP_Term ? (int) $term->term_id : 0,
			's'    => is_search() ? (string) get_search_query( false ) : '',
		],
		'filters'         => [
			'instock' => false,
			'sale'    => false,
			'brand'   => [],
			'min'     => 0,
			'max'     => 0,
		],
		'active'          => false,
		'active_count'    => 0,
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'orderby'         => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : ( is_search() ? 'relevance' : (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) ) ),
		'default_orderby' => is_search() ? 'relevance' : (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) ),
		'base_url'        => (string) get_pagenum_link( 1, false ),
		'facets'          => [
			'count'   => $found,
			'total'   => $found,
			'instock' => 0,
			'sale'    => 0,
			'brands'  => [],
			'price'   => [
				'min'   => 0,
				'max'   => 0,
				'steps' => [],
			],
		],
		'brands'          => [],
		'threshold'       => gadgetim_sale_thresholds()['strike'],
		'prices_hidden'   => gadgetim_contact_mode(),
		'fragment'        => gadgetim_archive_is_fragment(),
	];
	return $state;
}

/**
 * URL of the current archive (page 1) with a filter set and an orderby ('' keeps the current sort).
 *
 * @param array<string,mixed> $filters Filters (instock, sale, brand[], min, max).
 * @param string              $orderby Orderby key.
 */
function gadgetim_archive_url( array $filters, string $orderby = '' ): string {
	if ( gadgetim_archive_filters_ready() ) {
		return Gadgetim\Core\Catalog\Filters::url( $filters, $orderby );
	}
	$state   = gadgetim_archive_state();
	$orderby = '' !== $orderby ? $orderby : (string) $state['orderby'];
	$base    = remove_query_arg( [ 'orderby', 'paged' ], (string) $state['base_url'] );
	return $orderby !== $state['default_orderby'] ? add_query_arg( 'orderby', $orderby, $base ) : $base;
}

/**
 * Sort options for the toolbar: key => label (relevance first on searches).
 *
 * @return array<string,string>
 */
function gadgetim_archive_sort_options(): array {
	$options = (array) apply_filters(
		'woocommerce_catalog_orderby',
		[
			'menu_order' => __( 'Default order', 'gadgetim' ),
			'popularity' => __( 'Most viewed', 'gadgetim' ),
			'date'       => __( 'Newest', 'gadgetim' ),
			'price'      => __( 'Cheapest first', 'gadgetim' ),
			'price-desc' => __( 'Most expensive first', 'gadgetim' ),
		]
	);
	unset( $options['menu_order'], $options['rating'] );
	if ( is_search() ) {
		$options = [ 'relevance' => __( 'Most relevant', 'gadgetim' ) ] + $options;
	}
	$state = gadgetim_archive_state();
	if ( ! empty( $state['prices_hidden'] ) ) {
		unset( $options['price'], $options['price-desc'], $options['discount'] );
	}
	return array_map( 'strval', $options );
}

/**
 * The active sort key (a requested key that is not offered falls back to the default).
 */
function gadgetim_archive_orderby(): string {
	$state   = gadgetim_archive_state();
	$options = gadgetim_archive_sort_options();
	$current = (string) $state['orderby'];
	if ( isset( $options[ $current ] ) ) {
		return $current;
	}
	return isset( $options[ $state['default_orderby'] ] ) ? (string) $state['default_orderby'] : (string) array_key_first( $options );
}

/* ---------------------------------------------------------------------------
 * Hero data
 * ------------------------------------------------------------------------- */

/**
 * Hero of the current catalogue page (memoised): kind (shop|category|brand|tag|search), term,
 * title parts, eyebrow, sub line, count, tone, decorative word, art products, max discount,
 * price from, brand logo, chips.
 *
 * @return array<string,mixed>
 */
function gadgetim_archive_info(): array {
	static $info = null;
	if ( null !== $info ) {
		return $info;
	}
	$state   = gadgetim_archive_state();
	$context = (array) $state['context'];
	$term    = null;
	if ( ! empty( $context['tax'] ) && ! empty( $context['term'] ) ) {
		$found = get_term( (int) $context['term'], (string) $context['tax'] );
		$term  = $found instanceof WP_Term ? $found : null;
	}
	$search = '' !== (string) ( $context['s'] ?? '' );
	$kind   = 'shop';
	if ( $search ) {
		$kind = 'search';
	} elseif ( $term && 'product_brand' === $term->taxonomy ) {
		$kind = 'brand';
	} elseif ( $term && 'product_cat' === $term->taxonomy ) {
		$kind = 'category';
	} elseif ( $term ) {
		$kind = 'tag';
	}

	$total = (int) ( $state['facets']['total'] ?? 0 );
	$name  = $term ? html_entity_decode( gadgetim_term_name( $term ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
	$icon  = 'grid';
	$lead  = '';
	$grad  = '';
	$sub   = '';
	$eyebrow = '';
	$word    = 'GADGETIM';

	switch ( $kind ) {
		case 'search':
			$eyebrow = __( 'Search', 'gadgetim' );
			/* translators: %s: search query */
			$pattern     = __( 'Results for “%s”', 'gadgetim' );
			$parts       = explode( '%s', $pattern, 2 );
			$lead        = (string) $parts[0];
			$grad        = (string) $context['s'];
			$after_title = (string) ( $parts[1] ?? '' );
			// The quotes go inside the isolated query (<bdi>), so they stay around it in either direction.
			if ( preg_match( '/([“«"‹„])\s*$/u', $lead, $open ) && preg_match( '/^\s*([”»"›“])/u', $after_title, $close ) ) {
				$lead        = (string) mb_substr( rtrim( $lead ), 0, -1 );
				$after_title = (string) mb_substr( ltrim( $after_title ), 1 );
				$grad        = $open[1] . $grad . $close[1];
			}
			$isolate     = true;
			$icon        = 'search';
			$word        = 'SEARCH';
			$sub         = $total > 0
				/* translators: %s: number of products */
				? sprintf( _n( '%s original product matches your search.', '%s original products match your search.', $total, 'gadgetim' ), gadgetim_number( $total ) )
				: __( 'Nothing matches these words yet.', 'gadgetim' );
			if ( $term ) {
				/* translators: %s: category name */
				$sub .= ' ' . sprintf( __( 'Showing the results in %s.', 'gadgetim' ), $name );
			}
			break;
		case 'brand':
			$eyebrow = __( 'Brand', 'gadgetim' );
			$grad    = $name;
			$icon    = 'shield';
			$word    = strtoupper( (string) preg_replace( '/[^A-Za-z0-9 ]+/', '', remove_accents( $name ) ) );
			$word    = '' !== trim( $word ) ? $word : 'BRAND';
			/* translators: %s: brand name */
			$sub = sprintf( __( 'Every %s product here is original and comes with an authenticity guarantee.', 'gadgetim' ), $name );
			break;
		case 'category':
		case 'tag':
			$eyebrow = 'category' === $kind ? __( 'Category', 'gadgetim' ) : __( 'Tag', 'gadgetim' );
			$icon    = 'category' === $kind && $term ? gadgetim_archive_term_icon( $term ) : 'tag';
			$words   = preg_split( '/\s+/u', trim( $name ) ) ?: [ $name ];
			$grad    = (string) array_pop( $words );
			$lead    = implode( ' ', $words );
			$word    = gadgetim_archive_word( $icon );
			$sub     = __( 'Original products with an authenticity guarantee, a 7-day return and fast delivery across Iran.', 'gadgetim' );
			break;
		default:
			$eyebrow = __( 'Shop', 'gadgetim' );
			$title   = function_exists( 'woocommerce_page_title' ) ? (string) woocommerce_page_title( false ) : '';
			$grad    = '' !== $title ? $title : __( 'All products', 'gadgetim' );
			$cats    = count( array_filter( gadgetim_category_terms(), static fn( WP_Term $t ): bool => gadgetim_term_count( $t ) > 0 ) );
			$sub = $total > 0
				/* translators: 1: number of products, 2: number of categories */
				? sprintf( __( '%1$s original gadgets in %2$s categories, every one with an authenticity guarantee.', 'gadgetim' ), gadgetim_number( $total ), gadgetim_number( $cats ) )
				: __( 'New original gadgets are on their way.', 'gadgetim' );
	}

	// A short legacy description replaces the generic line; long ones go to the block at the bottom.
	$desc = $term && ! $search ? gadgetim_archive_term_description( $term ) : '';
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $desc ) ) );
	$long = '' !== $text;
	if ( '' !== $text && ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ) <= 220 ) {
		$sub  = $text;
		$long = false;
	}

	// Art, discount sticker and the lowest price from the context's in-stock products.
	$art     = [];
	$max_off = 0;
	$from    = 0;
	if ( gadgetim_archive_filters_ready() && $total > 0 ) {
		$rows    = Gadgetim\Core\Catalog\Filters::rows( $context );
		$instock = array_filter( $rows, static fn( array $r ): bool => 'outofstock' !== $r['stock'] );
		foreach ( $instock as $r ) {
			$max_off = max( $max_off, (int) $r['off'] );
			if ( (float) $r['pmin'] > 0 && ( 0 === $from || (float) $r['pmin'] < $from ) ) {
				$from = (int) $r['pmin'];
			}
		}
		uasort( $instock, static fn( array $a, array $b ): int => (int) $b['off'] <=> (int) $a['off'] );
		foreach ( array_keys( $instock ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product && $product->get_image_id() && $product->is_visible() ) {
				$art[] = $product;
			}
			if ( count( $art ) >= 2 ) {
				break;
			}
		}
	} else {
		global $wp_query;
		foreach ( $wp_query instanceof WP_Query ? array_slice( (array) $wp_query->posts, 0, 4 ) : [] as $post ) {
			$product = wc_get_product( $post );
			if ( $product && $product->get_image_id() && $product->is_in_stock() ) {
				$art[] = $product;
				$max_off = max( $max_off, gadgetim_product_discount( $product ) );
			}
			if ( count( $art ) >= 2 ) {
				break;
			}
		}
	}
	if ( ! empty( $state['prices_hidden'] ) ) {
		$max_off = 0;
		$from    = 0;
	}

	$info = [
		'kind'        => $kind,
		'term'        => $term,
		'name'        => $name,
		'eyebrow'     => $eyebrow,
		'lead'        => $lead,
		'grad'        => $grad,
		'after'       => $after_title ?? '',
		'isolate'     => ! empty( $isolate ),
		'sub'         => $sub,
		'count'       => $total,
		'icon'        => $icon,
		'tone'        => gadgetim_archive_tone( 'search' === $kind ? 'search' : ( 'brand' === $kind ? 'brand' : $icon ) ),
		'word'        => $word,
		'art'         => $art,
		'max_off'     => $max_off >= gadgetim_sale_thresholds()['sticker'] ? $max_off : 0,
		'from'        => $from,
		'logo'        => 'brand' === $kind && $term && class_exists( 'Gadgetim\Core\Catalog\Brands' ) ? (string) Gadgetim\Core\Catalog\Brands::logo( $term, 'thumbnail', [ 'class' => 'ahero__logo-img', 'loading' => 'eager' ] ) : '',
		'description' => $long && ! is_paged() ? $desc : '',
		'chips'       => gadgetim_archive_chips( $kind, $term, $state ),
	];
	return $info;
}

/**
 * The same catalogue page in Persian ('' when unknown): the way out of a language section whose
 * products are not translated yet.
 */
function gadgetim_archive_persian_url(): string {
	$languages = 'Gadgetim\Core\I18n\Languages';
	$hreflang  = 'Gadgetim\Core\I18n\Hreflang';
	if ( class_exists( $languages ) && method_exists( $languages, 'with_lang' ) ) {
		if ( is_search() ) {
			$url = $languages::with_lang(
				'fa',
				static fn() => add_query_arg(
					[
						's'         => rawurlencode( (string) get_search_query( false ) ),
						'post_type' => 'product',
					],
					home_url( '/' )
				)
			);
		} elseif ( class_exists( $hreflang ) && method_exists( $hreflang, 'current_object_url' ) ) {
			$url = $languages::with_lang( 'fa', static fn() => $hreflang::current_object_url( 'fa' ) );
		}
		if ( ! empty( $url ) && is_string( $url ) ) {
			return $url;
		}
	}
	$links = gadgetim_language_links();
	return isset( $links['fa']['url'] ) ? (string) $links['fa']['url'] : '';
}

/**
 * Monogram letters of a brand without a logo: "Green Lion" → "GL", "ANKER" → "AN".
 */
function gadgetim_archive_initials( string $name ): string {
	$words = preg_split( '/[\s\-&_.]+/u', trim( $name ) ) ?: [];
	$words = array_values( array_filter( $words, static fn( string $w ): bool => '' !== $w ) );
	if ( ! $words ) {
		return '';
	}
	$pick = static fn( string $w, int $n ): string => function_exists( 'mb_substr' ) ? mb_substr( $w, 0, $n, 'UTF-8' ) : substr( $w, 0, $n );
	$out  = count( $words ) > 1 ? $pick( $words[0], 1 ) . $pick( $words[1], 1 ) : $pick( $words[0], 2 );
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out, 'UTF-8' ) : strtoupper( $out );
}

/**
 * Translated term description (raw HTML, kses on output), or '' when it is demo filler.
 *
 * Legacy descriptions can hold the old theme's «لورم ایپسوم» filler (the smart-watch category
 * did until 2026-11-14). The plugin kept it out of the meta description through the
 * term_description filter, but this path reads the description directly, so shoppers still saw
 * it under «درباره ساعت هوشمند». The same placeholder test (gadgetim-core Seo\Text) now applies here.
 */
function gadgetim_archive_term_description( WP_Term $term ): string {
	if ( class_exists( 'Gadgetim\Core\I18n\TermTranslations' ) && method_exists( 'Gadgetim\Core\I18n\TermTranslations', 'description_or_native' ) ) {
		$description = (string) Gadgetim\Core\I18n\TermTranslations::description_or_native( $term );
	} else {
		$description = (string) $term->description;
	}
	if ( '' !== $description && class_exists( 'Gadgetim\Core\Seo\Text' ) && Gadgetim\Core\Seo\Text::is_placeholder( Gadgetim\Core\Seo\Text::plain( $description ) ) ) {
		return '';
	}
	return $description;
}

/**
 * Icon of a category: its curated group's icon when it belongs to one, else a guess from the name.
 */
function gadgetim_archive_term_icon( WP_Term $term ): string {
	foreach ( gadgetim_category_groups() as $group ) {
		if ( (int) $group['id'] === (int) $term->term_id ) {
			return (string) $group['icon'];
		}
		foreach ( (array) $group['links'] as $link ) {
			if ( (int) $link['id'] === (int) $term->term_id ) {
				$own = gadgetim_category_icon( $term );
				return 'grid' !== $own ? $own : (string) $group['icon'];
			}
		}
	}
	return gadgetim_category_icon( $term );
}

/**
 * Colour family of the hero by icon / kind (archive.css: [data-tone]).
 */
function gadgetim_archive_tone( string $key ): string {
	$map = [
		'headphones' => 'violet',
		'speaker'    => 'violet',
		'mobile'     => 'cool',
		'usb'        => 'cool',
		'plug'       => 'cool',
		'search'     => 'cool',
		'watch'      => 'green',
		'tent'       => 'green',
		'car'        => 'gold',
		'bolt'       => 'gold',
		'cup'        => 'warm',
		'drop'       => 'pink',
		'brand'      => 'violet',
	];
	return (string) apply_filters( 'gadgetim_archive_tone', $map[ $key ] ?? 'violet', $key );
}

/**
 * Giant outlined background word of the hero (decorative, Latin).
 */
function gadgetim_archive_word( string $icon ): string {
	$map = [
		'headphones' => 'SOUND',
		'speaker'    => 'SOUND',
		'mobile'     => 'MOBILE',
		'usb'        => 'TECH',
		'plug'       => 'CABLE',
		'watch'      => 'SMART',
		'tent'       => 'TRAVEL',
		'car'        => 'DRIVE',
		'bolt'       => 'POWER',
		'cup'        => 'HOME',
		'drop'       => 'CARE',
	];
	return (string) apply_filters( 'gadgetim_archive_word', $map[ $icon ] ?? 'GADGET', $icon );
}

/* ---------------------------------------------------------------------------
 * Category chips (hero) and filter-panel categories
 * ------------------------------------------------------------------------- */

/**
 * Chips under the hero title: [ label, items[ id, name, url, count, img, current, all ] ].
 *
 * shop      the curated category groups
 * category  children; else the curated group (main or member) or the siblings, current marked
 * brand     the categories of the brand's products, each linking to the category filtered by brand
 * search    the categories of the results (a refinement of the search), "All results" first
 *
 * @param string     $kind  Page kind.
 * @param ?WP_Term   $term  Queried term.
 * @param array      $state Archive state.
 * @return array{label:string,items:array<int,array<string,mixed>>}
 */
function gadgetim_archive_chips( string $kind, ?WP_Term $term, array $state ): array {
	$items = [];
	$label = __( 'Categories', 'gadgetim' );
	if ( 'shop' === $kind ) {
		$items = gadgetim_nav_cached( 'achips_shop', 'gadgetim_archive_build_shop_chips' );
	} elseif ( 'category' === $kind && $term ) {
		$label = __( 'Subcategories', 'gadgetim' );
		$items = gadgetim_nav_cached(
			'achips_' . (int) $term->term_id,
			static fn(): array => gadgetim_archive_build_category_chips( $term )
		);
		foreach ( $items as &$item ) {
			$item['current'] = (int) $item['id'] === (int) $term->term_id && empty( $item['all'] );
		}
		unset( $item );
	} elseif ( in_array( $kind, [ 'brand', 'search' ], true ) && gadgetim_archive_filters_ready() ) {
		$context = (array) $state['context'];
		if ( 'search' === $kind ) {
			// Counts over the whole search, not only the refined category.
			$context['tax']  = '';
			$context['term'] = 0;
		}
		$counts = Gadgetim\Core\Catalog\Filters::category_counts( $context );
		$images = gadgetim_archive_term_images();
		$terms  = [];
		foreach ( gadgetim_category_terms() as $t ) {
			$terms[ (int) $t->term_id ] = $t;
		}
		foreach ( $counts as $id => $n ) {
			if ( ! isset( $terms[ $id ] ) ) {
				continue;
			}
			// Skip a parent whose products all sit in one child (the child chip says the same).
			foreach ( $terms as $child ) {
				if ( (int) $child->parent === (int) $id && ( $counts[ (int) $child->term_id ] ?? 0 ) === $n ) {
					continue 2;
				}
			}
			$t    = $terms[ $id ];
			$link = get_term_link( $t );
			if ( ! is_string( $link ) ) {
				continue;
			}
			if ( 'brand' === $kind && $term ) {
				$url = add_query_arg( 'brand', $term->slug, $link );
			} else {
				$url = add_query_arg(
					[
						's'           => rawurlencode( (string) $state['context']['s'] ),
						'post_type'   => 'product',
						'product_cat' => $t->slug,
					],
					home_url( '/' )
				);
			}
			$items[] = [
				'id'      => (int) $id,
				'name'    => html_entity_decode( gadgetim_term_name( $t ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'url'     => $url,
				'count'   => (int) $n,
				'img'     => (int) ( $images[ $id ] ?? 0 ),
				'current' => 'search' === $kind && $term && (int) $term->term_id === (int) $id,
				'all'     => false,
			];
			if ( count( $items ) >= 14 ) {
				break;
			}
		}
		if ( 'search' === $kind && $term && $items ) {
			array_unshift(
				$items,
				[
					'id'      => 0,
					'name'    => __( 'All results', 'gadgetim' ),
					'url'     => add_query_arg(
						[
							's'         => rawurlencode( (string) $state['context']['s'] ),
							'post_type' => 'product',
						],
						home_url( '/' )
					),
					'count'   => array_sum( array_intersect_key( $counts, array_filter( $terms, static fn( WP_Term $t ): bool => 0 === (int) $t->parent ) ) ),
					'img'     => 0,
					'current' => false,
					'all'     => true,
				]
			);
		}
		$label = 'brand' === $kind ? __( 'Categories of this brand', 'gadgetim' ) : __( 'Refine by category', 'gadgetim' );
	}
	return [
		'label' => $label,
		'items' => array_values( (array) $items ),
	];
}

/**
 * Shop chips: the curated groups (built once per navigation-cache version).
 *
 * @return array<int,array<string,mixed>>
 */
function gadgetim_archive_build_shop_chips(): array {
	$items = [];
	foreach ( gadgetim_category_groups() as $group ) {
		$main = get_term( (int) $group['id'], 'product_cat' );
		$items[] = [
			'id'      => (int) $group['id'],
			'name'    => (string) $group['name'],
			'url'     => (string) $group['url'],
			'count'   => (int) $group['count'],
			'img'     => $main instanceof WP_Term ? gadgetim_category_image_id( $main ) : 0,
			'current' => false,
			'all'     => false,
		];
	}
	return $items;
}

/**
 * Category chips for a category archive (built once per navigation-cache version).
 *
 * @return array<int,array<string,mixed>>
 */
function gadgetim_archive_build_category_chips( WP_Term $term ): array {
	$terms = gadgetim_category_terms();
	$chip  = static fn( WP_Term $t, bool $all = false ): array => [
		'id'      => (int) $t->term_id,
		/* translators: %s: category name */
		'name'    => $all ? sprintf( __( 'All of %s', 'gadgetim' ), html_entity_decode( gadgetim_term_name( $t ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : html_entity_decode( gadgetim_term_name( $t ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		'url'     => (string) get_term_link( $t ),
		'count'   => gadgetim_lane_count( $t ),
		'img'     => gadgetim_category_image_id( $t ),
		'current' => false,
		'all'     => $all,
	];
	$items = [];
	// A curated group's main category: the group's links (its children first, then the related
	// top-level categories), like the mega menu.
	foreach ( gadgetim_category_groups() as $group ) {
		if ( (int) $group['id'] === (int) $term->term_id ) {
			foreach ( (array) $group['links'] as $link ) {
				$t = get_term( (int) $link['id'], 'product_cat' );
				if ( $t instanceof WP_Term ) {
					$items[] = $chip( $t );
				}
			}
			break;
		}
	}
	if ( ! $items ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === (int) $term->term_id && gadgetim_lane_count( $t ) > 0 ) {
				$items[] = $chip( $t );
			}
		}
	}
	if ( ! $items && (int) $term->parent > 0 ) {
		$parent = get_term( (int) $term->parent, 'product_cat' );
		if ( $parent instanceof WP_Term ) {
			$items[] = $chip( $parent, true );
			foreach ( $terms as $t ) {
				if ( (int) $t->parent === (int) $parent->term_id && gadgetim_lane_count( $t ) > 0 ) {
					$items[] = $chip( $t );
				}
			}
		}
	}
	if ( ! $items ) {
		foreach ( gadgetim_category_groups() as $group ) {
			$ids = array_map( static fn( array $l ): int => (int) $l['id'], (array) $group['links'] );
			if ( in_array( (int) $term->term_id, $ids, true ) ) {
				$main = get_term( (int) $group['id'], 'product_cat' );
				if ( $main instanceof WP_Term ) {
					$items[] = $chip( $main, true );
				}
				foreach ( $ids as $id ) {
					$t = get_term( $id, 'product_cat' );
					if ( $t instanceof WP_Term ) {
						$items[] = $chip( $t );
					}
				}
				break;
			}
		}
	}
	return array_values( array_filter( $items, static fn( array $i ): bool => $i['count'] > 0 ) );
}

/**
 * Image attachment per visible category (term thumbnail or product art), cached.
 *
 * @return array<int,int>
 */
function gadgetim_archive_term_images(): array {
	return gadgetim_nav_cached(
		'aimgs',
		static function (): array {
			$map = [];
			foreach ( gadgetim_category_terms() as $t ) {
				if ( gadgetim_lane_count( $t ) > 0 ) {
					$map[ (int) $t->term_id ] = gadgetim_category_image_id( $t );
				}
			}
			return $map;
		}
	);
}

/* ---------------------------------------------------------------------------
 * Active filters, price labels
 * ------------------------------------------------------------------------- */

/**
 * Removable chips for the active filters: [ label, url, type ].
 *
 * @return array<int,array{label:string,url:string,type:string}>
 */
function gadgetim_archive_active_chips(): array {
	$state   = gadgetim_archive_state();
	$filters = (array) $state['filters'];
	if ( empty( $state['active'] ) ) {
		return [];
	}
	$chips  = [];
	$names  = [];
	foreach ( (array) $state['brands'] as $brand ) {
		$names[ (string) $brand['slug'] ] = (string) $brand['name'];
	}
	if ( ! empty( $filters['instock'] ) ) {
		$chips[] = [
			'label' => __( 'In stock', 'gadgetim' ),
			'url'   => gadgetim_archive_url( array_merge( $filters, [ 'instock' => false ] ) ),
			'type'  => 'instock',
		];
	}
	if ( ! empty( $filters['sale'] ) ) {
		$chips[] = [
			'label' => __( 'Discounted', 'gadgetim' ),
			'url'   => gadgetim_archive_url( array_merge( $filters, [ 'sale' => false ] ) ),
			'type'  => 'sale',
		];
	}
	foreach ( (array) $filters['brand'] as $slug ) {
		$chips[] = [
			'label' => $names[ $slug ] ?? strtoupper( rawurldecode( (string) $slug ) ),
			'url'   => gadgetim_archive_url( array_merge( $filters, [ 'brand' => array_values( array_diff( (array) $filters['brand'], [ $slug ] ) ) ] ) ),
			'type'  => 'brand',
		];
	}
	if ( (int) $filters['min'] > 0 || (int) $filters['max'] > 0 ) {
		$chips[] = [
			'label' => gadgetim_archive_price_range_label( (int) $filters['min'], (int) $filters['max'] ),
			'url'   => gadgetim_archive_url(
				array_merge(
					$filters,
					[
						'min' => 0,
						'max' => 0,
					]
				)
			),
			'type'  => 'price',
		];
	}
	return $chips;
}

/**
 * Short price for chips: «۷۵۰ هزار», «۱٫۲ میلیون».
 */
function gadgetim_archive_short_price( int $amount ): string {
	if ( $amount >= 1000000 ) {
		$m = $amount / 1000000;
		/* translators: %s: amount in millions */
		return sprintf( __( '%s million', 'gadgetim' ), gadgetim_number( $m, abs( $m - round( $m ) ) < 0.05 ? 0 : 1 ) );
	}
	if ( $amount >= 1000 ) {
		/* translators: %s: amount in thousands */
		return sprintf( __( '%s thousand', 'gadgetim' ), gadgetim_number( round( $amount / 1000 ) ) );
	}
	return gadgetim_number( $amount );
}

/**
 * «۱ تا ۵ میلیون تومان», «از ۲ میلیون تومان», «تا ۹۰۰ هزار تومان».
 */
function gadgetim_archive_price_range_label( int $min, int $max ): string {
	$unit = gadgetim_price_unit();
	if ( $min > 0 && $max > 0 ) {
		/* translators: 1: lower price, 2: upper price, 3: currency unit */
		return sprintf( __( '%1$s to %2$s %3$s', 'gadgetim' ), gadgetim_archive_short_price( $min ), gadgetim_archive_short_price( $max ), $unit );
	}
	if ( $min > 0 ) {
		/* translators: 1: lower price, 2: currency unit */
		return sprintf( __( 'From %1$s %2$s', 'gadgetim' ), gadgetim_archive_short_price( $min ), $unit );
	}
	/* translators: 1: upper price, 2: currency unit */
	return sprintf( __( 'Up to %1$s %2$s', 'gadgetim' ), gadgetim_archive_short_price( $max ), $unit );
}

/* ---------------------------------------------------------------------------
 * Cards, fragment, config
 * ------------------------------------------------------------------------- */

/**
 * Card of the current loop item (content-product.php). Main catalogue loop: the first rows load
 * eagerly (budget set in results.php), cards from the third desktop row on reveal on scroll;
 * fragments render without reveal (filters.js animates them). Other loops (related, up-sells,
 * cross-sells) get the foundation's settings.
 */
function gadgetim_archive_card( WC_Product $product ): string {
	global $wp_query;
	$main = 'archive' === gadgetim_page_kind() && '' === (string) wc_get_loop_prop( 'name' ) && $wp_query instanceof WP_Query && in_the_loop();
	if ( $main ) {
		$index  = max( 0, (int) $wp_query->current_post );
		$reveal = ! gadgetim_archive_is_fragment() && $index >= 8;
		return gadgetim_product_card(
			$product,
			'default',
			[
				'tag'    => 'div',
				'class'  => 'pc--loop',
				'index'  => $index % 4,
				'reveal' => $reveal,
			]
		);
	}
	static $other = 0;
	return gadgetim_product_card(
		$product,
		'default',
		[
			'tag'    => 'div',
			'class'  => 'pc--loop',
			'index'  => $other++ % 8,
			'reveal' => false,
		]
	);
}

/**
 * gadgetim_catalog_fragment (plugin): the results block alone.
 *
 * @param mixed $state Filters::state() (unused; the templates read gadgetim_archive_state()).
 */
function gadgetim_archive_fragment( $state = null ): void {
	gadgetim_part( 'archive/results' );
}

/**
 * #gadgetim-config additions on catalogue pages: filters.js strings.
 *
 * @param array $config Config.
 * @return array
 */
function gadgetim_archive_config( array $config ): array {
	if ( 'archive' !== gadgetim_page_kind() ) {
		return $config;
	}
	$config['strings'] = array_merge(
		(array) ( $config['strings'] ?? [] ),
		[
			/* translators: %s: number of products */
			'showOne'     => __( 'Show %s product', 'gadgetim' ),
			/* translators: %s: number of products */
			'showMany'    => __( 'Show %s products', 'gadgetim' ),
			'showNone'    => __( 'No product matches', 'gadgetim' ),
			'showAny'     => __( 'Show the products', 'gadgetim' ),
			/* translators: %s: number of products */
			'foundOne'    => __( '%s product found', 'gadgetim' ),
			/* translators: %s: number of products */
			'foundMany'   => __( '%s products found', 'gadgetim' ),
			'foundNone'   => __( 'No product matches these filters', 'gadgetim' ),
			/* translators: 1: first product shown, 2: last product shown, 3: total products */
			'shownRange'  => __( 'Showing %1$s–%2$s of %3$s products', 'gadgetim' ),
			/* translators: %s: page number */
			'pageLoaded'  => __( 'Page %s added to the list', 'gadgetim' ),
			/* translators: 1: price, 2: currency unit */
			'priceText'   => _x( '%1$s %2$s', 'price and currency unit', 'gadgetim' ),
		]
	);
	return $config;
}
