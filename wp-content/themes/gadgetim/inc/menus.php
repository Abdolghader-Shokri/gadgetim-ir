<?php
/**
 * Menus and navigation data: per-language menu locations, the category groups of the mega menu
 * and the mobile drawer, the story circles (one image per category), brands and footer links.
 *
 * The heavy data (groups, stories) is built once per language and kept in a transient; the cache
 * version is bumped on every product / category / settings save, so it never goes stale.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/* ---------------------------------------------------------------------------
 * Menu locations
 * ------------------------------------------------------------------------- */

/**
 * Location id for a base ('primary', 'footer', 'legal') in the current language, falling back to Persian.
 */
function gadgetim_menu_location( string $base ): string {
	if ( class_exists( 'Gadgetim\Core\I18n\Menus' ) ) {
		return (string) Gadgetim\Core\I18n\Menus::location( $base );
	}
	$location = $base . '_' . gadgetim_lang();
	return has_nav_menu( $location ) ? $location : $base . '_fa';
}

/**
 * Whether a menu is assigned for a base location in the current (or Persian) language.
 */
function gadgetim_has_menu( string $base ): bool {
	return has_nav_menu( gadgetim_menu_location( $base ) );
}

/**
 * Menu items of a location as [ [ label, url, current ], … ] (first level only), [] when unassigned.
 *
 * @return array<int,array{label:string,url:string,current:bool}>
 */
function gadgetim_menu_items( string $base ): array {
	if ( ! gadgetim_has_menu( $base ) ) {
		return [];
	}
	$locations = get_nav_menu_locations();
	$menu_id   = (int) ( $locations[ gadgetim_menu_location( $base ) ] ?? 0 );
	$items     = $menu_id > 0 ? ( wp_get_nav_menu_items( $menu_id ) ?: [] ) : [];
	$here      = gadgetim_current_url();
	$out       = [];
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent > 0 ) {
			continue;
		}
		$url   = (string) $item->url;
		$out[] = [
			'label'   => (string) $item->title,
			'url'     => $url,
			'current' => '' !== $here && untrailingslashit( $url ) === untrailingslashit( $here ),
		];
	}
	return $out;
}

/**
 * Current request URL without the query string.
 */
function gadgetim_current_url(): string {
	global $wp;
	return $wp instanceof WP ? home_url( '/' . ltrim( (string) $wp->request, '/' ) ) : '';
}

/**
 * URL of the shop page (home when WooCommerce is missing).
 */
function gadgetim_shop_url(): string {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$shop = (string) wc_get_page_permalink( 'shop' );
		if ( '' !== $shop ) {
			return $shop;
		}
	}
	return home_url( '/' );
}

/**
 * Shop URL in a sort order. The default order has no parameter: the shop answers
 * ?orderby=<default> with a redirect to the clean URL, so linking it cost every click a hop.
 *
 * @param string $orderby Sort key (popularity, date, price, …).
 */
function gadgetim_shop_sorted_url( string $orderby ): string {
	$default = (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
	$shop    = gadgetim_shop_url();
	return $orderby === $default ? $shop : add_query_arg( 'orderby', $orderby, $shop );
}

/**
 * URL of the first published page among candidate slugs ('' when none). Persian slugs work as typed.
 *
 * @param string[] $slugs Candidate slugs, in order.
 */
function gadgetim_page_url( array $slugs ): string {
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			return (string) get_permalink( $page );
		}
	}
	return '';
}

/* ---------------------------------------------------------------------------
 * Navigation cache
 * ------------------------------------------------------------------------- */

/**
 * Bumps the navigation cache version (all languages at once).
 */
function gadgetim_flush_nav_cache(): void {
	update_option( 'gadgetim_nav_version', (int) get_option( 'gadgetim_nav_version', 1 ) + 1, true );
}
add_action( 'save_post_product', 'gadgetim_flush_nav_cache' );
add_action( 'deleted_post', 'gadgetim_flush_nav_cache' );
add_action( 'woocommerce_product_set_stock_status', 'gadgetim_flush_nav_cache' );
add_action( 'created_product_cat', 'gadgetim_flush_nav_cache' );
add_action( 'edited_product_cat', 'gadgetim_flush_nav_cache' );
add_action( 'delete_product_cat', 'gadgetim_flush_nav_cache' );
add_action( 'edited_product_brand', 'gadgetim_flush_nav_cache' );
add_action( 'gadgetim_settings_saved', 'gadgetim_flush_nav_cache' );
add_action( 'gadgetim_cache_purged_all', 'gadgetim_flush_nav_cache' );

/**
 * Cached navigation data for the current language, built on a miss.
 *
 * @param string   $key   Part name (groups, stories).
 * @param callable $build Builder returning an array.
 */
function gadgetim_nav_cached( string $key, callable $build ): array {
	static $memo = [];
	static $i18n = null;
	// Labels are translated strings: a deployed translation file or theme version must rebuild the cache.
	if ( null === $i18n ) {
		$file = get_theme_file_path( 'languages/gadgetim-' . determine_locale() . '.l10n.php' );
		$i18n = substr( md5( wp_get_theme()->get( 'Version' ) . '|' . ( is_readable( $file ) ? (string) filemtime( $file ) : '0' ) ), 0, 8 );
	}
	$id = 'gadgetim_nav_' . $key . '_' . gadgetim_lang() . '_' . (int) get_option( 'gadgetim_nav_version', 1 ) . '_' . $i18n;
	if ( isset( $memo[ $id ] ) ) {
		return $memo[ $id ];
	}
	$data = get_transient( $id );
	if ( ! is_array( $data ) ) {
		$data = (array) $build();
		set_transient( $id, $data, 12 * HOUR_IN_SECONDS );
	}
	$memo[ $id ] = $data;
	return $data;
}

/* ---------------------------------------------------------------------------
 * Categories
 * ------------------------------------------------------------------------- */

/**
 * Visible product categories (the default category excluded, language-gated through gadgetim_menu_terms).
 *
 * @return WP_Term[]
 */
function gadgetim_category_terms(): array {
	static $memo = [];
	$lang = gadgetim_lang();
	if ( isset( $memo[ $lang ] ) ) {
		return $memo[ $lang ];
	}
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return [];
	}
	$default = (int) get_option( 'default_product_cat' );
	$terms   = get_terms(
		[
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'exclude'    => $default > 0 ? [ $default ] : [],
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		]
	);
	$terms = is_array( $terms ) ? $terms : [];
	/**
	 * Filters the category terms used by theme navigation (the I18n module hides untranslated ones).
	 *
	 * @param WP_Term[] $terms Terms.
	 * @param string    $lang  Language code.
	 */
	$terms         = array_values( array_filter( (array) apply_filters( 'gadgetim_menu_terms', $terms, $lang ), static fn( $t ): bool => $t instanceof WP_Term ) );
	$memo[ $lang ] = $terms;
	return $terms;
}

/**
 * Items of a term in the current language (plugin filter gadgetim_term_count).
 */
function gadgetim_term_count( WP_Term $term ): int {
	return (int) apply_filters( 'gadgetim_term_count', (int) $term->count, $term, gadgetim_lang() );
}

/**
 * Term ids of a category and all its descendants.
 *
 * @return int[]
 */
function gadgetim_category_subtree( int $term_id ): array {
	$ids   = [ $term_id ];
	$terms = gadgetim_category_terms();
	$queue = [ $term_id ];
	while ( $queue ) {
		$parent = array_shift( $queue );
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === $parent && ! in_array( (int) $t->term_id, $ids, true ) ) {
				$ids[]   = (int) $t->term_id;
				$queue[] = (int) $t->term_id;
			}
		}
	}
	return $ids;
}

/**
 * Items in a category including its descendants.
 */
function gadgetim_lane_count( WP_Term $term ): int {
	$sum  = 0;
	$tree = gadgetim_category_subtree( (int) $term->term_id );
	foreach ( gadgetim_category_terms() as $t ) {
		if ( in_array( (int) $t->term_id, $tree, true ) ) {
			$sum += gadgetim_term_count( $t );
		}
	}
	return $sum;
}

/**
 * A category term by (decoded or encoded) slug, from the visible set.
 */
function gadgetim_category_by_slug( string $slug ): ?WP_Term {
	$want = strtolower( rawurldecode( $slug ) );
	foreach ( gadgetim_category_terms() as $t ) {
		if ( strtolower( rawurldecode( $t->slug ) ) === $want ) {
			return $t;
		}
	}
	return null;
}

/**
 * Product ids in a set of categories (descendants included), in stock first by popularity.
 *
 * @param int[] $term_ids Category ids.
 * @param int   $limit    Maximum ids.
 * @param bool  $in_stock Only in-stock products.
 * @return int[]
 */
function gadgetim_category_product_ids( array $term_ids, int $limit = 12, bool $in_stock = true ): array {
	if ( ! $term_ids || ! post_type_exists( 'product' ) ) {
		return [];
	}
	$args = [
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'posts_per_page'         => $limit,
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_term_cache' => false,
		'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'                => [
			'meta_value_num' => 'DESC',
			'date'           => 'DESC',
		],
		'tax_query'              => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'relation' => 'AND',
			[
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => array_map( 'intval', $term_ids ),
				'include_children' => true,
			],
		],
	];
	if ( taxonomy_exists( 'product_visibility' ) ) {
		$args['tax_query'][] = [
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => [ 'exclude-from-catalog' ],
			'operator' => 'NOT IN',
		];
	}
	if ( $in_stock ) {
		$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'key'   => '_stock_status',
				'value' => 'instock',
			],
		];
	}
	$query = new WP_Query( $args );
	return array_map( 'intval', (array) $query->posts );
}

/**
 * Image attachment for a category: the term thumbnail, else the most popular in-stock product's
 * first gallery image (usually the product out of its box), else its main image. 0 when none.
 */
function gadgetim_category_image_id( WP_Term $term ): int {
	$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $thumb > 0 && wp_attachment_is_image( $thumb ) ) {
		return $thumb;
	}
	foreach ( gadgetim_category_product_ids( [ (int) $term->term_id ], 4 ) as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}
		$img = gadgetim_product_image_id( $product, 1 ) ?: (int) $product->get_image_id();
		if ( $img > 0 ) {
			return $img;
		}
	}
	return 0;
}

/**
 * Icon name for a category, guessed from its slug/name (filter gadgetim_category_icon).
 */
function gadgetim_category_icon( WP_Term $term ): string {
	$hay = strtolower( rawurldecode( $term->slug ) . ' ' . $term->name );
	$map = [
		'watch'      => [ 'ساعت', 'مچ', 'watch' ],
		'headphones' => [ 'صوتی', 'هدفون', 'هندزفری', 'ایرپاد', 'audio', 'head' ],
		'speaker'    => [ 'اسپیکر', 'speaker' ],
		'mobile'     => [ 'موبایل', 'گوشی', 'mobile', 'phone' ],
		'usb'        => [ 'کامپیوتر', 'فلش', 'حافظه', 'لپ', 'کیبورد', 'ماوس', 'computer', 'usb' ],
		'car'        => [ 'خودرو', 'پمپ', 'جامپ', 'car' ],
		'cup'        => [ 'خانگی', 'قهوه', 'آشپز', 'توستر', 'شیکر', 'home', 'coffee' ],
		'drop'       => [ 'زیبایی', 'سلامت', 'اصلاح', 'مسواک', 'سشوار', 'ماساژ', 'beauty' ],
		'tent'       => [ 'کمپینگ', 'سفر', 'camp', 'travel' ],
		'bolt'       => [ 'پاور', 'شارژر', 'شارژ', 'power', 'charger' ],
		'plug'       => [ 'کابل', 'رابط', 'تبدیل', 'برق', 'cable' ],
	];
	$icon = 'grid';
	foreach ( $map as $name => $needles ) {
		foreach ( $needles as $needle ) {
			if ( str_contains( $hay, $needle ) ) {
				$icon = $name;
				break 2;
			}
		}
	}
	return (string) apply_filters( 'gadgetim_category_icon', $icon, $term );
}

/**
 * Default group definitions for the mega menu / drawer: a main category plus related top-level
 * siblings merged into one group (the catalogue is flat). Slugs as typed in the admin.
 * Filter gadgetim_category_groups_def to curate; groups whose main slug is missing are skipped.
 *
 * @return array<int,array{main:string,subs:string[],icon:string}>
 */
function gadgetim_category_groups_def(): array {
	$def = [
		[ 'main' => 'لوازم-جانبی-موبایل', 'icon' => 'mobile', 'subs' => [ 'شارژر', 'کابل-شارژ', 'پاور-بانک', 'شارژر-فندکی', 'رابط-otg', 'پایه-نگهدارنده-گوشی', 'اتسند-موبایل-و-تبلت', 'محافظ-لنز', 'گجت-های-موبایل' ] ],
		[ 'main' => 'گجت-های-صوتی-وتصویری', 'icon' => 'headphones', 'subs' => [ 'هدفون', 'ایرپاد', 'هندزفری', 'اسپیکر-کالای-دیجیتیال-و-برقی', 'میکروفون', 'محافظ-برق-و-چندراهی' ] ],
		[ 'main' => 'smart-watch', 'icon' => 'watch', 'subs' => [ 'smart-watch', 'مچ-بند-هوشمند' ] ],
		[ 'main' => 'لوازم-جانبی-کامپیوتر', 'icon' => 'usb', 'subs' => [ 'فلش-مموری', 'کیبورد', 'رابط-otg' ] ],
		[ 'main' => 'لوازم-جانبی-خودرو', 'icon' => 'car', 'subs' => [ 'پمپ-باد', 'جامپ-استارتر-و-جت-فن', 'اینورتر-و-مبدل-برق', 'شارژر-فندکی', 'صداگیر-و-سایلنسر-کمربند' ] ],
		[ 'main' => 'گجت-های-خانگی', 'icon' => 'cup', 'subs' => [ 'قهوه-ساز', 'توستر', 'شیکر-و-بلندر', 'خشبو-کننده-هوا', 'تبدیل-3-به-2', 'فندک', 'اجاق-گاز' ] ],
		[ 'main' => 'زیبایی-و-سلامت', 'icon' => 'drop', 'subs' => [ 'ماشین-اصلاح-صورت-و-بدن', 'ماساژور', 'مسواک-برقی', 'سشوار-چندکاره', 'بالشتک-طبی', 'لوازم-برقی-شخصی' ] ],
		[ 'main' => 'کمپینگ-و-سفر', 'icon' => 'tent', 'subs' => [ 'لوازم-سفر', 'تراول-ماگ-و-فلاسک', 'مینی-واش', 'صندلی-و-میز-کمپینگ', 'مودم-و-روتر', 'اجاق-گاز' ] ],
	];
	return (array) apply_filters( 'gadgetim_category_groups_def', $def );
}

/**
 * Category groups for the mega menu, the drawer accordion and the search category selector.
 * Each group: id, name, url, slug, icon, count, links[] (id, name, url, count, img), feature (product id),
 * minis (product ids), brands[] (name, url, count).
 *
 * @return array<int,array<string,mixed>>
 */
function gadgetim_category_groups(): array {
	return gadgetim_nav_cached( 'groups', 'gadgetim_build_category_groups' );
}

/**
 * Builds the category groups (uncached).
 *
 * @return array<int,array<string,mixed>>
 */
function gadgetim_build_category_groups(): array {
	$groups = [];
	$used   = [];
	foreach ( gadgetim_category_groups_def() as $def ) {
		$main = gadgetim_category_by_slug( (string) ( $def['main'] ?? '' ) );
		if ( ! $main ) {
			continue;
		}
		$subs = [];
		// Children of the main category first, then the listed siblings.
		foreach ( gadgetim_category_terms() as $t ) {
			if ( (int) $t->parent === (int) $main->term_id ) {
				$subs[ (int) $t->term_id ] = $t;
			}
		}
		foreach ( (array) ( $def['subs'] ?? [] ) as $slug ) {
			$t = gadgetim_category_by_slug( (string) $slug );
			if ( $t ) {
				$subs[ (int) $t->term_id ] = $t;
			}
		}
		$groups[] = gadgetim_build_group( $main, array_values( $subs ), (string) ( $def['icon'] ?? '' ) );
		$used[]   = (int) $main->term_id;
	}
	if ( count( $groups ) < 3 ) {
		// Unknown catalogue: the top-level categories with the most items, their children as links.
		$groups = [];
		$tops   = array_filter( gadgetim_category_terms(), static fn( WP_Term $t ): bool => 0 === (int) $t->parent );
		usort( $tops, static fn( WP_Term $a, WP_Term $b ): int => gadgetim_lane_count( $b ) <=> gadgetim_lane_count( $a ) );
		foreach ( array_slice( $tops, 0, 8 ) as $top ) {
			$children = array_values( array_filter( gadgetim_category_terms(), static fn( WP_Term $t ): bool => (int) $t->parent === (int) $top->term_id ) );
			$groups[] = gadgetim_build_group( $top, $children, '' );
		}
	}
	$groups = array_values( array_filter( $groups, static fn( array $g ): bool => $g['count'] > 0 ) );
	// One feature product per group: skip products another group already features.
	$featured = [];
	foreach ( $groups as &$group ) {
		$pool             = array_values( array_diff( $group['ranked'], $featured ) );
		$group['feature'] = (int) ( $pool[0] ?? $group['feature'] );
		$group['minis']   = array_values( array_slice( array_diff( $group['ranked'], [ $group['feature'] ] ), 0, 3 ) );
		$featured[]       = $group['feature'];
		unset( $group['ranked'] );
	}
	unset( $group );
	/**
	 * Filters the built category groups (cached per language afterwards).
	 *
	 * @param array $groups Groups.
	 */
	return (array) apply_filters( 'gadgetim_category_groups', $groups );
}

/**
 * One group record.
 *
 * @param WP_Term   $main Main category.
 * @param WP_Term[] $subs Linked categories.
 * @param string    $icon Icon name ('' = guessed).
 * @return array<string,mixed>
 */
function gadgetim_build_group( WP_Term $main, array $subs, string $icon ): array {
	$links = [];
	$ids   = [ (int) $main->term_id ];
	foreach ( $subs as $t ) {
		$count = gadgetim_lane_count( $t );
		if ( $count <= 0 ) {
			continue;
		}
		$ids[]   = (int) $t->term_id;
		$links[] = [
			'id'    => (int) $t->term_id,
			'name'  => gadgetim_term_name( $t ),
			'url'   => (string) get_term_link( $t ),
			'count' => $count,
			'img'   => gadgetim_category_image_id( $t ),
		];
	}
	$ids      = array_values( array_unique( $ids ) );
	$products = gadgetim_category_product_ids( $ids, 40 );
	$count    = count( gadgetim_category_product_ids( $ids, 500, false ) );

	// Feature: the deepest discount with a gallery image; minis: the next best deals.
	$scored = [];
	$brands = [];
	foreach ( $products as $pid ) {
		$p = wc_get_product( $pid );
		if ( ! $p || ! $p->is_visible() ) {
			continue;
		}
		$scored[ $pid ] = gadgetim_product_discount( $p ) * 10 + ( gadgetim_product_image_id( $p, 1 ) ? 5 : 0 );
		$brand          = gadgetim_product_brand( $p );
		if ( $brand ) {
			$key = (int) $brand->term_id;
			if ( ! isset( $brands[ $key ] ) ) {
				$brands[ $key ] = [
					'name'  => gadgetim_term_name( $brand ),
					'url'   => (string) get_term_link( $brand ),
					'count' => 0,
				];
			}
			++$brands[ $key ]['count'];
		}
	}
	arsort( $scored );
	$ranked = array_keys( $scored );
	usort( $brands, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );

	return [
		'id'      => (int) $main->term_id,
		'name'    => gadgetim_term_name( $main ),
		'slug'    => rawurldecode( $main->slug ),
		'url'     => (string) get_term_link( $main ),
		'icon'    => '' !== $icon ? $icon : gadgetim_category_icon( $main ),
		'count'   => $count,
		'links'   => $links,
		'feature' => (int) ( $ranked[0] ?? 0 ),
		'minis'   => array_map( 'intval', array_slice( $ranked, 1, 3 ) ),
		'ranked'  => array_map( 'intval', array_slice( $ranked, 0, 8 ) ),
		'brands'  => array_slice( $brands, 0, 6 ),
	];
}

/**
 * Story circles: popular categories with one image each (term thumbnail, else the most popular
 * in-stock product image). Each: id, name, url, count, img. Filter gadgetim_story_slugs to curate.
 *
 * @return array<int,array{id:int,name:string,url:string,count:int,img:int}>
 */
function gadgetim_story_categories( int $limit = 15 ): array {
	$all = gadgetim_nav_cached(
		'stories',
		static function (): array {
			$slugs = (array) apply_filters(
				'gadgetim_story_slugs',
				[ 'هدفون', 'ایرپاد', 'اسپیکر-کالای-دیجیتیال-و-برقی', 'پاور-بانک', 'شارژر', 'کابل-شارژ', 'فلش-مموری', 'smart-watch', 'لوازم-جانبی-خودرو', 'گجت-های-خانگی', 'زیبایی-و-سلامت', 'کمپینگ-و-سفر', 'ماساژور', 'قهوه-ساز', 'هندزفری' ]
			);
			$terms = [];
			foreach ( $slugs as $slug ) {
				$t = gadgetim_category_by_slug( (string) $slug );
				if ( $t ) {
					$terms[ (int) $t->term_id ] = $t;
				}
			}
			if ( count( $terms ) < 6 ) {
				$pool = gadgetim_category_terms();
				usort( $pool, static fn( WP_Term $a, WP_Term $b ): int => gadgetim_lane_count( $b ) <=> gadgetim_lane_count( $a ) );
				foreach ( $pool as $t ) {
					$terms[ (int) $t->term_id ] = $t;
				}
			}
			$out = [];
			foreach ( $terms as $t ) {
				$count = gadgetim_lane_count( $t );
				if ( $count <= 0 ) {
					continue;
				}
				$out[] = [
					'id'    => (int) $t->term_id,
					'name'  => gadgetim_term_name( $t ),
					'url'   => (string) get_term_link( $t ),
					'count' => $count,
					'img'   => gadgetim_category_image_id( $t ),
				];
				if ( count( $out ) >= 24 ) {
					break;
				}
			}
			return $out;
		}
	);
	return array_slice( $all, 0, max( 1, $limit ) );
}

/**
 * Brands with products (native product_brand taxonomy), most populated first.
 *
 * @return WP_Term[]
 */
function gadgetim_brands( int $limit = 24 ): array {
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return [];
	}
	$lang  = gadgetim_lang();
	$terms = get_terms(
		[
			'taxonomy'   => 'product_brand',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 'fa' === $lang ? $limit : $limit * 4,
		]
	);
	if ( ! is_array( $terms ) ) {
		return [];
	}
	$terms = array_values( array_filter( (array) apply_filters( 'gadgetim_brand_terms', $terms, $lang ), static fn( $t ): bool => $t instanceof WP_Term ) );
	return array_slice( $terms, 0, $limit );
}

/* ---------------------------------------------------------------------------
 * Header and footer links
 * ------------------------------------------------------------------------- */

/**
 * Desktop nav row links: the primary menu when assigned, else deals, newest, best sellers, brands.
 * Each: label, url, current, icon ('' or an icon name), hot (bool).
 *
 * @return array<int,array{label:string,url:string,current:bool,icon:string,hot:bool}>
 */
function gadgetim_nav_links(): array {
	$items = array_map(
		static fn( array $i ): array => $i + [
			'icon' => '',
			'hot'  => false,
		],
		gadgetim_menu_items( 'primary' )
	);
	if ( ! $items ) {
		$shop = gadgetim_shop_url();
		if ( ! gadgetim_contact_mode() ) {
			$items[] = [
				'label'   => __( 'Amazing offers', 'gadgetim' ),
				'url'     => add_query_arg( 'sale', '1', $shop ),
				'current' => false,
				'icon'    => 'flame',
				'hot'     => true,
			];
		}
		$items[] = [
			'label'   => __( 'Newest', 'gadgetim' ),
			'url'     => gadgetim_shop_sorted_url( 'date' ),
			'current' => false,
			'icon'    => '',
			'hot'     => false,
		];
		$items[] = [
			'label'   => __( 'Best sellers', 'gadgetim' ),
			'url'     => gadgetim_shop_sorted_url( 'popularity' ),
			'current' => false,
			'icon'    => '',
			'hot'     => false,
		];
		$items[] = [
			'label'   => __( 'All products', 'gadgetim' ),
			'url'     => $shop,
			'current' => function_exists( 'is_shop' ) && is_shop(),
			'icon'    => '',
			'hot'     => false,
		];
		$brands = gadgetim_page_url( [ 'brands', 'برندها' ] );
		if ( '' !== $brands ) {
			$items[] = [
				'label'   => __( 'Brands', 'gadgetim' ),
				'url'     => $brands,
				'current' => false,
				'icon'    => '',
				'hot'     => false,
			];
		}
	}
	return (array) apply_filters( 'gadgetim_nav_links', $items );
}

/**
 * Footer «customer service» links: the footer menu when assigned, else order tracking and the
 * imported pages (FAQ, terms, privacy, contact) plus the guarantee page.
 *
 * @return array<int,array{label:string,url:string}>
 */
function gadgetim_footer_service_links(): array {
	$items = gadgetim_menu_items( 'footer' );
	if ( $items ) {
		return $items;
	}
	$out = [];
	if ( function_exists( 'wc_get_page_permalink' ) && ! gadgetim_contact_mode() ) {
		$account = (string) wc_get_page_permalink( 'myaccount' );
		if ( '' !== $account ) {
			$out[] = [
				'label' => __( 'Track your order', 'gadgetim' ),
				'url'   => wc_get_endpoint_url( 'orders', '', $account ),
			];
		}
	}
	$pages = [
		[ __( 'Frequently asked questions', 'gadgetim' ), [ 'سوالات-متداول', 'faq' ] ],
		[ __( 'Terms and conditions', 'gadgetim' ), [ 'شرایط-و-قوانین', 'terms' ] ],
		[ __( 'Privacy policy', 'gadgetim' ), [ 'حفظ-حریم-خصوصی', 'privacy-policy', 'privacy' ] ],
		[ __( 'Contact us', 'gadgetim' ), [ 'ارتباط-با-ما', 'contact', 'contact-us' ] ],
	];
	foreach ( $pages as [ $label, $slugs ] ) {
		$url = gadgetim_page_url( $slugs );
		if ( '' !== $url ) {
			$out[] = [
				'label' => $label,
				'url'   => $url,
			];
		}
	}
	$guarantee = gadgetim_guarantee_url();
	if ( '' !== $guarantee ) {
		$out[] = [
			'label' => __( 'Authenticity guarantee', 'gadgetim' ),
			'url'   => $guarantee,
		];
	}
	return (array) apply_filters( 'gadgetim_footer_service_links', $out );
}

/**
 * Footer «about» links: the legal menu when assigned, else about / contact / shop.
 *
 * @return array<int,array{label:string,url:string}>
 */
function gadgetim_footer_about_links(): array {
	$items = gadgetim_menu_items( 'legal' );
	if ( $items ) {
		return $items;
	}
	$out   = [];
	$about = gadgetim_page_url( [ 'درباره-ما', 'about', 'about-us' ] );
	if ( '' !== $about ) {
		$out[] = [
			'label' => __( 'About Gadgetim', 'gadgetim' ),
			'url'   => $about,
		];
	}
	$out[] = [
		'label' => __( 'All products', 'gadgetim' ),
		'url'   => gadgetim_shop_url(),
	];
	if ( ! gadgetim_contact_mode() ) {
		$out[] = [
			'label' => __( 'Amazing offers', 'gadgetim' ),
			'url'   => add_query_arg( 'sale', '1', gadgetim_shop_url() ),
		];
	}
	$out[] = [
		'label' => __( 'Newest products', 'gadgetim' ),
		'url'   => gadgetim_shop_sorted_url( 'date' ),
	];
	$out[] = [
		'label' => __( 'Best sellers', 'gadgetim' ),
		'url'   => gadgetim_shop_sorted_url( 'popularity' ),
	];
	return (array) apply_filters( 'gadgetim_footer_about_links', $out );
}

/**
 * Language links for the footer / drawer: rows of I18n\Hreflang::switcher() that are visible,
 * [] when the plugin is missing or only one language is visible.
 *
 * @return array<string,array{url:string,label:string,current:bool,hreflang:string}>
 */
function gadgetim_language_links(): array {
	if ( ! class_exists( 'Gadgetim\Core\I18n\Hreflang' ) || ! method_exists( 'Gadgetim\Core\I18n\Hreflang', 'switcher' ) ) {
		return [];
	}
	$rows = [];
	foreach ( (array) Gadgetim\Core\I18n\Hreflang::switcher() as $code => $row ) {
		if ( empty( $row['visible'] ) || empty( $row['url'] ) ) {
			continue;
		}
		$rows[ (string) $code ] = [
			'url'      => (string) $row['url'],
			'label'    => (string) ( $row['label'] ?? strtoupper( (string) $code ) ),
			'current'  => ! empty( $row['current'] ),
			'hreflang' => (string) ( $row['hreflang'] ?? $code ),
		];
	}
	return count( $rows ) > 1 ? $rows : [];
}

/**
 * Icon for a quick link in the menu drawer: the link's own icon, else one picked from what the
 * URL points at (newest, best sellers, offers, the whole shop, brands), so the rows differ.
 *
 * @param array $link gadgetim_nav_links() row.
 */
function gadgetim_nav_link_icon( array $link ): string {
	if ( '' !== (string) ( $link['icon'] ?? '' ) ) {
		return (string) $link['icon'];
	}
	$url   = (string) ( $link['url'] ?? '' );
	$query = [];
	wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$orderby = (string) ( $query['orderby'] ?? '' );
	if ( 'date' === $orderby ) {
		return 'sparkle';
	}
	if ( in_array( $orderby, [ 'popularity', 'rating' ], true ) ) {
		return 'trend';
	}
	if ( ! empty( $query['sale'] ) || ! empty( $query['on_sale'] ) || 'discount' === $orderby ) {
		return 'flame';
	}
	if ( '' !== $url && untrailingslashit( (string) strtok( $url, '?' ) ) === untrailingslashit( gadgetim_shop_url() ) ) {
		return 'grid';
	}
	if ( false !== strpos( rawurldecode( $url ), 'brand' ) || false !== strpos( rawurldecode( $url ), 'برند' ) ) {
		return 'crown';
	}
	return 'sparkle';
}

/**
 * ?gtm_mega=1 — the mega menu's panes 1…n as a cached, non-indexed HTML fragment (pane 0 ships with
 * the page). app.js fetches it on the first hover / focus / click of the menu button and swaps the
 * [data-lazy] placeholders. Language follows the URL prefix, like every page.
 */
function gadgetim_mega_fragment(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only fragment.
	if ( ! isset( $_GET['gtm_mega'] ) || is_admin() || wp_doing_ajax() ) {
		return;
	}
	$groups = gadgetim_category_groups();
	if ( count( $groups ) < 2 ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	header( 'Cache-Control: public, max-age=600' );
	header( 'X-Robots-Tag: noindex' );
	foreach ( $groups as $index => $group ) {
		if ( 0 === $index ) {
			continue;
		}
		gadgetim_part(
			'header/mega-pane',
			[
				'group' => $group,
				'index' => (int) $index,
			]
		);
	}
	exit;
}
add_action( 'template_redirect', 'gadgetim_mega_fragment', 4 );
