<?php
/**
 * Home page layer (front-page.php, templates/home/*, assets/css/home.css, assets/js/modules/home.js).
 *
 * Data comes from gadgetim-core's Gadgetim\Core\Catalog\HomeContent (settings tab "Home page" with
 * automatic defaults from the catalogue, cached per language); without the plugin the page falls
 * back to plain WooCommerce queries for the product rails. This file also wires the hero LCP
 * preload, the home script module and the featured-tab fragments (?gtm_home=tab&group=ID on the
 * home URL, so the language prefix keeps its context), plus small presentation helpers.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/* ---------------------------------------------------------------------------
 * Data
 * ------------------------------------------------------------------------- */

/**
 * Whether the plugin's home data layer is available.
 */
function gadgetim_home_core(): bool {
	return class_exists( 'Gadgetim\Core\Catalog\HomeContent' );
}

/**
 * Calls a HomeContent method when the plugin is there, else returns $fallback().
 *
 * @param string   $method   Static method name.
 * @param array    $args     Arguments.
 * @param callable $fallback Fallback producer.
 */
function gadgetim_home_call( string $method, array $args, callable $fallback ): mixed {
	if ( gadgetim_home_core() && method_exists( 'Gadgetim\Core\Catalog\HomeContent', $method ) ) {
		return call_user_func_array( [ 'Gadgetim\Core\Catalog\HomeContent', $method ], $args );
	}
	return $fallback();
}

/**
 * Sections of the front page, in order (filter gadgetim_home_sections).
 *
 * @return string[]
 */
function gadgetim_home_sections(): array {
	$sections = [ 'stories', 'hero', 'services', 'offers', 'newest', 'banners', 'ranked', 'categories', 'featured', 'brands', 'contact' ];
	return array_values( array_map( 'sanitize_key', (array) apply_filters( 'gadgetim_home_sections', $sections ) ) );
}

/**
 * Visually hidden page heading: shop name and tagline.
 */
function gadgetim_home_heading(): string {
	$tagline = gadgetim_t( 'brand.tagline', '' );
	if ( '' === $tagline ) {
		$tagline = __( 'Original gadgets with an authenticity guarantee', 'gadgetim' );
	}
	/* translators: 1: shop name, 2: tagline */
	return sprintf( __( '%1$s — %2$s', 'gadgetim' ), gadgetim_brand_name(), $tagline );
}

/**
 * Products for a list of ids (caches primed, invisible ones dropped), in order.
 *
 * @param int[] $ids Product ids.
 * @return WC_Product[]
 */
function gadgetim_home_products( array $ids ): array {
	if ( ! $ids || ! function_exists( 'wc_get_product' ) ) {
		return [];
	}
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	_prime_post_caches( $ids, true, true );
	$out = [];
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( $product instanceof WC_Product && $product->is_visible() ) {
			$out[] = $product;
		}
	}
	return $out;
}

/**
 * Fallback product ids (no plugin): WooCommerce query.
 *
 * @param array $args wc_get_products() arguments.
 * @return int[]
 */
function gadgetim_home_query_ids( array $args ): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return [];
	}
	$ids = wc_get_products(
		array_merge(
			[
				'status'       => 'publish',
				'limit'        => 12,
				'return'       => 'ids',
				'stock_status' => 'instock',
				'visibility'   => 'catalog',
			],
			$args
		)
	);
	return array_map( 'intval', is_array( $ids ) ? $ids : [] );
}

/**
 * The amazing-offers campaign: enabled, end, active, title, sub, products.
 *
 * @return array{enabled:bool,end:int,active:bool,title:string,sub:string,products:int[]}
 */
function gadgetim_home_campaign(): array {
	return (array) gadgetim_home_call(
		'campaign',
		[],
		static function (): array {
			$end = gadgetim_campaign_end();
			return [
				'enabled'  => true,
				'end'      => $end,
				'active'   => $end > 0,
				'title'    => __( 'Amazing offers', 'gadgetim' ),
				'sub'      => '',
				'products' => [],
			];
		}
	);
}

/**
 * Hero slides (see HomeContent::slides()).
 *
 * @return array<int,array<string,mixed>>
 */
function gadgetim_home_slides(): array {
	return (array) gadgetim_home_call( 'slides', [], static fn(): array => [] );
}

/**
 * Promo banners (see HomeContent::banners()).
 *
 * @return array<int,array<string,mixed>>
 */
function gadgetim_home_banners(): array {
	return (array) gadgetim_home_call( 'banners', [], static fn(): array => [] );
}

/**
 * Services strip items (see HomeContent::services()).
 *
 * @return array<int,array<string,string>>
 */
function gadgetim_home_services(): array {
	return (array) gadgetim_home_call( 'services', [], static fn(): array => [] );
}

/**
 * «پیشنهاد لحظه‌ای» product ids (nothing in call-for-price mode).
 *
 * @return int[]
 */
function gadgetim_home_flash_ids(): array {
	if ( gadgetim_contact_mode() ) {
		return [];
	}
	return (array) gadgetim_home_call(
		'flash_ids',
		[ 5 ],
		static fn(): array => array_slice( array_values( array_intersect( gadgetim_home_query_ids( [ 'limit' => 20 ] ), function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : [] ) ), 0, 5 )
	);
}

/**
 * Amazing-offers band ids (nothing in call-for-price mode).
 *
 * @param int[] $exclude Ids shown elsewhere (the instant offer).
 * @return int[]
 */
function gadgetim_home_offer_ids( array $exclude = [] ): array {
	if ( gadgetim_contact_mode() ) {
		return [];
	}
	return (array) gadgetim_home_call(
		'offer_ids',
		[ 12, $exclude ],
		static fn(): array => array_slice( array_values( array_diff( array_intersect( gadgetim_home_query_ids( [ 'limit' => 40 ] ), function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : [] ), $exclude ) ), 0, 12 )
	);
}

/**
 * Newest in-stock product ids.
 *
 * @return int[]
 */
function gadgetim_home_newest_ids(): array {
	return (array) gadgetim_home_call(
		'newest_ids',
		[ 12 ],
		static fn(): array => gadgetim_home_query_ids(
			[
				'orderby' => 'date',
				'order'   => 'DESC',
			]
		)
	);
}

/**
 * Ranked board: basis ('sales' once orders exist, else 'views') and ids.
 *
 * @return array{basis:string,ids:int[]}
 */
function gadgetim_home_ranked(): array {
	return (array) gadgetim_home_call(
		'ranked',
		[ 12 ],
		static fn(): array => [
			'basis' => 'sales',
			'ids'   => gadgetim_home_query_ids(
				[
					'orderby' => 'popularity',
					'order'   => 'DESC',
				]
			),
		]
	);
}

/**
 * Featured tabs: "all" plus the category groups (mega menu groups) holding enough stock.
 * Each: key, label, ids (only the first tab), src (fragment URL), href (category URL).
 *
 * @return array<int,array{key:string,label:string,ids:int[],src:string,href:string}>
 */
function gadgetim_home_featured_tabs(): array {
	$all = (array) gadgetim_home_call(
		'featured_ids',
		[ [], 10 ],
		static fn(): array => gadgetim_home_query_ids(
			[
				'limit'   => 10,
				'orderby' => 'price',
				'order'   => 'DESC',
			]
		)
	);
	if ( ! $all ) {
		return [];
	}
	$tabs = [
		[
			'key'   => '0',
			'label' => __( 'All', 'gadgetim' ),
			'ids'   => $all,
			'src'   => '',
			'href'  => gadgetim_shop_url(),
		],
	];
	if ( gadgetim_home_core() ) {
		foreach ( gadgetim_category_groups() as $group ) {
			$terms = array_merge( [ (int) $group['id'] ], array_map( static fn( array $l ): int => (int) $l['id'], (array) ( $group['links'] ?? [] ) ) );
			if ( count( Gadgetim\Core\Catalog\HomeContent::in_scope( $terms ) ) < 4 ) {
				continue;
			}
			$tabs[] = [
				'key'   => (string) (int) $group['id'],
				'label' => gadgetim_home_label( (string) $group['name'] ),
				'ids'   => [],
				'src'   => add_query_arg(
					[
						'gtm_home' => 'tab',
						'group'    => (int) $group['id'],
					],
					home_url( '/' )
				),
				'href'  => (string) $group['url'],
			];
			if ( count( $tabs ) >= 6 ) {
				break;
			}
		}
	}
	return (array) apply_filters( 'gadgetim_home_featured_tabs', $tabs );
}

/**
 * Featured product ids of one category group (fragment endpoint).
 *
 * @return int[]
 */
function gadgetim_home_group_ids( int $group_id ): array {
	if ( ! gadgetim_home_core() ) {
		return [];
	}
	foreach ( gadgetim_category_groups() as $group ) {
		if ( (int) $group['id'] === $group_id ) {
			$terms = array_merge( [ $group_id ], array_map( static fn( array $l ): int => (int) $l['id'], (array) ( $group['links'] ?? [] ) ) );
			return Gadgetim\Core\Catalog\HomeContent::featured_ids( $terms, 10 );
		}
	}
	return [];
}

/**
 * Category tiles for the bento: id, big, count, art.
 *
 * @return array<int,array{id:int,big:bool,count:int,art:array}>
 */
function gadgetim_home_tiles(): array {
	return (array) gadgetim_home_call(
		'category_tiles',
		[ 12 ],
		static function (): array {
			$out = [];
			foreach ( gadgetim_story_categories( 12 ) as $i => $story ) {
				$out[] = [
					'id'    => (int) $story['id'],
					'big'   => $i < 2,
					'count' => (int) $story['count'],
					'art'   => [],
					'img'   => (int) $story['img'],
				];
			}
			return $out;
		}
	);
}

/**
 * Shop statistics: products, brands, categories, return_days.
 *
 * @return array{products:int,brands:int,categories:int,return_days:int}
 */
function gadgetim_home_stats(): array {
	return (array) gadgetim_home_call(
		'stats',
		[],
		static fn(): array => [
			'products'    => (int) ( wp_count_posts( 'product' )->publish ?? 0 ),
			'brands'      => count( gadgetim_brands( 200 ) ),
			'categories'  => count( gadgetim_category_terms() ),
			'return_days' => 0,
		]
	);
}

/**
 * Story circles: the «شگفت‌انگیزها» ring (when there are offers) and the popular categories.
 * Each: id, label, name (full name for the accessible label), url, img, hot.
 *
 * @return array<int,array{id:int,label:string,name:string,url:string,img:int,hot:bool}>
 */
function gadgetim_home_stories(): array {
	$out = [];
	if ( gadgetim_home_offer_ids() ) {
		$out[] = [
			'id'    => 0,
			// Short label under the circle (one line on a 72 px circle); the full name is the accessible label.
			'label' => _x( 'Offers', 'story circle label, keep short', 'gadgetim' ),
			'name'  => __( 'Amazing offers', 'gadgetim' ),
			'url'   => '#offers',
			'img'   => 0,
			'hot'   => true,
		];
	}
	// The bento's curated packshots make better rings than the automatic category images.
	$curated = [];
	if ( gadgetim_home_core() ) {
		foreach ( gadgetim_home_tiles() as $tile ) {
			$entry   = $tile['art'][0] ?? null;
			$product = is_array( $entry ) ? gadgetim_product( (int) $entry[0] ) : null;
			if ( $product ) {
				$curated[ (int) $tile['id'] ] = gadgetim_product_image_id( $product, (int) $entry[1] ) ?: (int) $product->get_image_id();
			}
		}
	}
	foreach ( gadgetim_story_categories( 15 ) as $story ) {
		$name  = gadgetim_home_label( (string) $story['name'] );
		$out[] = [
			'id'    => (int) $story['id'],
			'label' => gadgetim_home_story_label( (int) $story['id'], $name ),
			'name'  => $name,
			'url'   => (string) $story['url'],
			'img'   => (int) ( $curated[ (int) $story['id'] ] ?? $story['img'] ),
			'hot'   => false,
		];
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Presentation helpers
 * ------------------------------------------------------------------------- */

/**
 * Display hygiene for Persian category names typed in the admin (ZWNJ before plural suffixes and a
 * few known typos). Persian only; filter gadgetim_home_label. The source data should be fixed too.
 */
function gadgetim_home_label( string $name ): string {
	$name = trim( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) );
	if ( 'fa' === gadgetim_lang() ) {
		$zwnj = "\u{200C}";
		$name = (string) preg_replace( '/ (ها|های|هایی)(?=$|[\s،.])/u', $zwnj . '$1', $name );
		$name = str_replace(
			[ 'وتصویری', 'دیجیتیال', 'مچ بند', 'پاور بانک', 'خوشبو کننده', 'قهوه ساز' ],
			[ 'و تصویری', 'دیجیتال', 'مچ' . $zwnj . 'بند', 'پاوربانک', 'خوشبوکننده', 'قهوه' . $zwnj . 'ساز' ],
			$name
		);
	}
	/**
	 * Filters a category label shown on the home page.
	 *
	 * @param string $name Label.
	 */
	return (string) apply_filters( 'gadgetim_home_label', $name );
}

/**
 * Short one-line story caption (Persian shortenings by slug; filter gadgetim_home_story_labels).
 */
function gadgetim_home_story_label( int $term_id, string $name ): string {
	if ( 'fa' !== gadgetim_lang() ) {
		return $name;
	}
	$map  = (array) apply_filters(
		'gadgetim_home_story_labels',
		[
			'لوازم-جانبی-خودرو' => 'لوازم خودرو',
			'گجت-های-خانگی'     => 'گجت خانگی',
			'زیبایی-و-سلامت'    => 'زیبایی',
			'کمپینگ-و-سفر'      => 'کمپینگ',
			'لوازم-جانبی-موبایل' => 'لوازم موبایل',
		]
	);
	$term = get_term( $term_id, 'product_cat' );
	$slug = $term instanceof WP_Term ? rawurldecode( $term->slug ) : '';
	return isset( $map[ $slug ] ) ? (string) $map[ $slug ] : $name;
}

/**
 * Decorative product art (watermark mask): the n-th image of a product.
 *
 * @param array $entry [ product id, image index, fade ].
 * @param array $args  gadgetim_decor_image() arguments (class, sizes, loading, fetchpriority, alt).
 */
function gadgetim_home_art( array $entry, string $size = 'gtm-card', array $args = [] ): string {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) ( $entry[0] ?? 0 ) ) : null;
	if ( ! $product instanceof WC_Product ) {
		return '';
	}
	return gadgetim_decor_image(
		$product,
		$size,
		array_merge(
			[
				'index' => (int) ( $entry[1] ?? 0 ),
				'fade'  => ! empty( $entry[2] ),
			],
			$args
		)
	);
}

/**
 * «تا ۵۷٪ تخفیف» as an inline pill (escaped HTML; '' below the sticker threshold).
 */
function gadgetim_home_deal( int $off, string $class = 'deal' ): string {
	if ( $off < gadgetim_sale_thresholds()['sticker'] ) {
		return '';
	}
	/* translators: %s: discount percentage, e.g. 57% */
	return '<span class="' . esc_attr( $class ) . '">' . sprintf( esc_html__( 'Up to %s off', 'gadgetim' ), '<b>' . esc_html( gadgetim_percent( $off ) ) . '</b>' ) . '</span>';
}

/**
 * Round three-line sticker «تا / ۵۷٪ / تخفیف» ('' below the sticker threshold).
 */
function gadgetim_home_sticker( int $off, string $class = '' ): string {
	if ( $off < gadgetim_sale_thresholds()['sticker'] ) {
		return '';
	}
	return gadgetim_sticker(
		gadgetim_percent( $off ),
		_x( 'up to', 'discount sticker: line above the percentage', 'gadgetim' ),
		_x( 'off', 'discount sticker: line below the percentage', 'gadgetim' ),
		$class
	);
}

/**
 * Inline SVG of a services icon (each shape carries pathLength="1" for the draw animation).
 */
function gadgetim_home_service_icon( string $name ): string {
	$shapes = [
		'shield'     => '<path d="M12 3.2 5.2 6v5.4c0 4.3 2.9 7.9 6.8 9.4 3.9-1.5 6.8-5.1 6.8-9.4V6L12 3.2Z"/><path d="m9 12.1 2.1 2.1 4-4.2"/>',
		'truck'      => '<path d="M2.8 6.6h11v9.2h-11z"/><path d="M13.8 9.4h3.7l3.7 3.8v2.6h-7.4"/><circle class="w" cx="6.8" cy="17.6" r="1.9"/><circle class="w" cx="17" cy="17.6" r="1.9"/>',
		'return'     => '<path d="M4.3 12.4A7.8 7.8 0 1 0 6.6 6.4"/><path d="M4.4 3.8v4.4h4.4"/><path d="M10.2 9.4h3.9l-2.4 5.6"/>',
		'chat'       => '<path d="M4.6 19.6 5.7 16A8.2 8.2 0 1 1 8.5 18.7l-3.9.9Z"/><path d="M9.3 9.2c.3 2.4 2.2 4.6 4.7 5.3l1.2-1.2-1.8-1.1-.9.6a4.2 4.2 0 0 1-1.9-1.9l.6-.9-1.1-1.8-.8 1Z"/>',
		'lock'       => '<rect x="5" y="10.4" width="14" height="10.2" rx="2.6"/><path class="shackle" d="M8.2 10.4V8a3.8 3.8 0 0 1 7.6 0v2.4"/><path d="M12 14.3v2.4"/>',
		'gift'       => '<rect x="4" y="9.2" width="16" height="11" rx="2.2"/><path d="M3.2 9.2h17.6M12 9.2v11"/><path d="M12 9.2C10.1 5.6 6 5.6 6.9 8c.5 1.2 5.1 1.2 5.1 1.2Zm0 0c1.9-3.6 6-3.6 5.1-1.2-.5 1.2-5.1 1.2-5.1 1.2Z"/>',
		'bolt'       => '<path d="M13.2 3 5.4 13.4h6.1L10.8 21l7.8-10.4h-6.1L13.2 3Z"/>',
		'phone'      => '<path d="M6.7 3.6h2.6l1.3 4-2 1.3a11 11 0 0 0 6.5 6.5l1.3-2 4 1.3v2.6a2 2 0 0 1-2.2 2A16.4 16.4 0 0 1 4.7 5.8a2 2 0 0 1 2-2.2Z"/>',
		'box'        => '<path d="m12 3.4 7.6 4.2v8.8L12 20.6l-7.6-4.2V7.6L12 3.4Z"/><path d="m4.6 7.8 7.4 4.1 7.4-4.1M12 11.9v8.6"/>',
		'star'       => '<path d="m12 3.9 2.5 5.1 5.6.8-4.1 4 1 5.6-5-2.7-5 2.7 1-5.6-4.1-4 5.6-.8L12 3.9Z"/>',
		'clock'      => '<circle cx="12" cy="12" r="8.4"/><path d="M12 7.6V12l2.9 1.9"/>',
		'tag'        => '<path d="M3.6 12.5V5.1a1.5 1.5 0 0 1 1.5-1.5h7.4l7.9 7.9-8.9 8.9-7.9-7.9Z"/><path d="M8 8h.01"/>',
		'sparkle'    => '<path d="M11 3.5c.7 4.6 2.7 6.6 7.3 7.3-4.6.7-6.6 2.7-7.3 7.3-.7-4.6-2.7-6.6-7.3-7.3 4.6-.7 6.6-2.7 7.3-7.3Z"/><path d="M18.6 15.2c.3 1.6 1 2.3 2.6 2.6-1.6.3-2.3 1-2.6 2.6-.3-1.6-1-2.3-2.6-2.6 1.6-.3 2.3-1 2.6-2.6Z"/>',
		'headphones' => '<path d="M4.2 15.2v-3a7.8 7.8 0 0 1 15.6 0v3"/><rect x="3.4" y="13.8" width="4.2" height="6.4" rx="1.7"/><rect x="16.4" y="13.8" width="4.2" height="6.4" rx="1.7"/>',
		'gauge'      => '<path d="M4.2 16.4a7.8 7.8 0 1 1 15.6 0"/><path d="m12 16.4 3.6-4.6"/><path d="M6.8 16.4h.01M17.2 16.4h.01"/>',
		'crown'      => '<path d="M4.5 19h15"/><path d="M5.4 16 3.8 7.4l4.8 3.6L12 5l3.4 6 4.8-3.6L18.6 16Z"/>',
		'percent'    => '<path d="M18.6 5.4 5.4 18.6"/><circle cx="7.4" cy="7.4" r="2.3"/><circle cx="16.6" cy="16.6" r="2.3"/>',
	];
	$svg = $shapes[ $name ] ?? $shapes['shield'];
	$svg = (string) preg_replace( '/<(path|circle|rect)\b/', '<$1 pathLength="1"', $svg );
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

/**
 * Typographic style of a brand chip (label, colour, weight, letter-spacing, italic) by slug;
 * unknown brands get their own name and a palette colour. Filter gadgetim_home_brand_styles.
 *
 * @return array{label:string,style:string}
 */
function gadgetim_home_brand_style( WP_Term $brand ): array {
	static $map = null;
	if ( null === $map ) {
		$map = (array) apply_filters(
			'gadgetim_home_brand_styles',
			[
				'green-lion' => [ 'GREEN LION', '#15803d', 900, '.14em', '' ],
				'koranco'    => [ 'KORANCO', '#b45309', 900, '.08em', 'italic' ],
				'porodo'     => [ 'porodo', '#111827', 800, '.01em', '' ],
				'onesam'     => [ 'ONESAM', '#0369a1', 900, '.12em', '' ],
				'samsung'    => [ 'SAMSUNG', '#1d4ed8', 800, '.2em', '' ],
				'sandisk'    => [ 'SanDisk', '#dc2626', 800, '0', '' ],
				'anker'      => [ 'ANKER', '#0284c7', 800, '.14em', '' ],
				'apple'      => [ 'Apple', '#111827', 600, '0', '' ],
				'jbl'        => [ 'JBL', '#ea580c', 900, '.06em', '' ],
				'lepresso'   => [ 'LEPRESSO', '#92400e', 600, '.26em', '' ],
				'powerology' => [ 'Powerology', '#334155', 700, '.02em', '' ],
				'philips'    => [ 'PHILIPS', '#1d4ed8', 800, '.18em', '' ],
				'xiaomi'     => [ 'Xiaomi', '#c2410c', 700, '.02em', '' ],
				'ez'         => [ 'E&Z', '#7c3aed', 900, '.08em', '' ],
				'enzo'       => [ 'ENZO', '#be185d', 800, '.22em', '' ],
				'jbq'        => [ 'JBQ', '#0f766e', 900, '.1em', '' ],
				'depak'      => [ 'DEPAK', '#4338ca', 800, '.16em', '' ],
				'pentica'    => [ 'PENTICA', '#9333ea', 700, '.18em', '' ],
			]
		);
	}
	$slug = rawurldecode( $brand->slug );
	$def  = $map[ $slug ] ?? null;
	if ( ! is_array( $def ) ) {
		$palette = [ '#7c3aed', '#be185d', '#0369a1', '#15803d', '#b45309', '#4338ca' ];
		$def     = [ html_entity_decode( gadgetim_term_name( $brand ), ENT_QUOTES, 'UTF-8' ), $palette[ abs( crc32( $slug ) ) % count( $palette ) ], 800, '.06em', '' ];
	}
	$style = '--bc:' . $def[1] . ';--w:' . (int) $def[2] . ';--ls:' . $def[3] . ( '' !== $def[4] ? ';--fs:' . $def[4] : '' );
	return [
		'label' => (string) $def[0],
		'style' => (string) preg_replace( '/[^#a-z0-9.:;\-%]/i', '', $style ),
	];
}

/* ---------------------------------------------------------------------------
 * Wiring: LCP preload, script module, tab fragments
 * ------------------------------------------------------------------------- */

/**
 * gadgetim_lcp_image: on the home page the first slide's main packshot.
 *
 * @param array|null $lcp Current value.
 * @return array|null
 */
function gadgetim_home_lcp( $lcp ) {
	if ( 'home' !== gadgetim_page_kind() ) {
		return $lcp;
	}
	$slides = gadgetim_home_slides();
	$entry  = $slides[0]['art'][0] ?? null;
	if ( ! is_array( $entry ) || ! function_exists( 'wc_get_product' ) ) {
		return $lcp;
	}
	$product = wc_get_product( (int) $entry[0] );
	if ( ! $product instanceof WC_Product ) {
		return $lcp;
	}
	$id = gadgetim_product_image_id( $product, (int) $entry[1] );
	if ( $id <= 0 ) {
		$id = (int) $product->get_image_id();
	}
	return $id > 0 ? [ $id, 'gtm-hero', 'hero' ] : $lcp;
}
add_filter( 'gadgetim_lcp_image', 'gadgetim_home_lcp' );

/**
 * gadgetim_js_modules: registers assets/js/modules/home.js (import map "@gadgetim/home") on the home page.
 *
 * @param string[] $modules Module names.
 * @return string[]
 */
function gadgetim_home_js_module( array $modules ): array {
	if ( 'home' === gadgetim_page_kind() ) {
		$modules[] = 'home';
	}
	return $modules;
}
add_filter( 'gadgetim_js_modules', 'gadgetim_home_js_module' );

/**
 * wp_enqueue_scripts (after the foundation registered the modules): print home.js as a module
 * script so it downloads in parallel; it boots itself through app.js's loader (init(ctx) once).
 */
function gadgetim_home_enqueue(): void {
	if ( 'home' === gadgetim_page_kind() && function_exists( 'wp_enqueue_script_module' ) && is_readable( GADGETIM_THEME_DIR . 'assets/js/modules/home.js' ) ) {
		wp_enqueue_script_module( '@gadgetim/home' );
	}
}
add_action( 'wp_enqueue_scripts', 'gadgetim_home_enqueue', 21 );

/**
 * template_redirect: featured-tab fragments (?gtm_home=tab&group=ID on the home URL) — the cards of
 * one category group as server-rendered HTML for home.js. Public, cacheable for five minutes, noindex.
 */
function gadgetim_home_fragment(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only fragment.
	if ( ! isset( $_GET['gtm_home'] ) || ! is_front_page() ) {
		return;
	}
	$part  = sanitize_key( wp_unslash( (string) $_GET['gtm_home'] ) );
	$group = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( 'tab' !== $part ) {
		return;
	}
	$ids = $group > 0 ? gadgetim_home_group_ids( $group ) : [];
	if ( ! $ids ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	header( 'Cache-Control: public, max-age=300' );
	header( 'X-Robots-Tag: noindex' );
	foreach ( gadgetim_home_products( $ids ) as $i => $product ) {
		gadgetim_the_product_card(
			$product,
			'default',
			[
				'index'  => $i,
				'reveal' => false,
			]
		);
	}
	exit;
}
add_action( 'template_redirect', 'gadgetim_home_fragment', 5 );

/**
 * Decorative art for a slide or banner that has none in the current language (a language whose
 * products are not translated yet): the same entry's art in the source language (Persian).
 * Images are language-neutral; the caller shows them without product links or prices.
 *
 * @param string $kind  'slides' or 'banners'.
 * @param int    $index Position of the entry.
 * @return array<int,array> Art entries ([ product id, image index, fade ]) or [].
 */
function gadgetim_home_source_art( string $kind, int $index ): array {
	if ( 'fa' === gadgetim_lang() || ! gadgetim_home_core() || ! in_array( $kind, [ 'slides', 'banners' ], true ) ) {
		return [];
	}
	$class = 'Gadgetim\Core\Catalog\HomeContent';
	if ( ! method_exists( $class, $kind ) ) {
		return [];
	}
	$here   = (array) call_user_func( [ $class, $kind ] );
	$source = (array) call_user_func( [ $class, $kind ], 'fa' );
	// Only when both languages list the same entries (positions line up).
	if ( count( $here ) !== count( $source ) || empty( $source[ $index ]['art'] ) ) {
		return [];
	}
	return array_values( (array) $source[ $index ]['art'] );
}
