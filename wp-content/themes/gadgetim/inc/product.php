<?php
/**
 * Product page layer (single product): WooCommerce hook wiring, data helpers for the templates in
 * woocommerce/single-product* and templates/product/*, the «خرید سریع» redirect, the recently viewed
 * card fragment (GET gadgetim/v1/cards), the product-page settings section and the JS config strings.
 *
 * Page anatomy (content-single-product.php):
 *   .pdp__hero   gallery (product-image.php) · title area (product/head) · buy box (the
 *                woocommerce_single_product_summary hook) · key features + guarantee (product/side)
 *   .pdp-body    in-page chips (product/nav) + stacked sections (tabs/tabs.php) · desktop mini buy box
 *   then woocommerce_after_single_product_summary (up-sells 15, related 20, recently viewed 25),
 *   the mobile sticky buy bar and the lightbox <dialog>.
 *
 * Summary hook after this file runs:
 *   10 gadgetim_pdp_price · 15 gadgetim_pdp_stock · 30 woocommerce_template_single_add_to_cart (simple.php)
 *   32 gadgetim_pdp_secondary · 45 gadgetim_pdp_perks · 60 WC_Structured_Data::generate_product_data
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * init 25 (after the foundation's init 20): rewire the single-product hooks.
 */
function gadgetim_pdp_hooks(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Gallery: the theme's product-image.php renders slides, thumbnails and the sticker itself.
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );

	// Title, rating, excerpt, meta and sharing live in the title area (templates/product/head.php);
	// price and stock are the theme's buy-box parts.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
	add_action( 'woocommerce_single_product_summary', 'gadgetim_pdp_price', 10 );
	add_action( 'woocommerce_single_product_summary', 'gadgetim_pdp_stock', 15 );
	add_action( 'woocommerce_single_product_summary', 'gadgetim_pdp_secondary', 32 );
	add_action( 'woocommerce_single_product_summary', 'gadgetim_pdp_perks', 45 );

	// The sections are printed inside the two-column body (content-single-product.php calls
	// woocommerce_output_product_data_tabs() there); up-sells and related stay on the hook, full width.
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	add_action( 'woocommerce_after_single_product_summary', 'gadgetim_pdp_recent', 25 );

	// The specs table renders the attributes itself (through woocommerce_display_product_attributes);
	// the hook still fires inside it for third-party additions.
	remove_action( 'woocommerce_product_additional_information', 'wc_display_product_attributes', 10 );

	// The meta hooks fire in the title area; WooCommerce Brands' "Brand:" line repeats the brand chip.
	if ( isset( $GLOBALS['WC_Brands'] ) && is_object( $GLOBALS['WC_Brands'] ) ) {
		remove_action( 'woocommerce_product_meta_end', [ $GLOBALS['WC_Brands'], 'show_brand' ] );
	}

	// Reviews: an initial avatar instead of the Gravatar request.
	remove_action( 'woocommerce_review_before', 'woocommerce_review_display_gravatar', 10 );
	add_action( 'woocommerce_review_before', 'gadgetim_pdp_review_avatar', 10 );

	add_filter( 'woocommerce_add_to_cart_redirect', 'gadgetim_pdp_buy_now_redirect', 20, 2 );
	add_filter( 'woocommerce_product_review_comment_form_args', 'gadgetim_pdp_review_form_args', 20 );
	add_filter( 'gadgetim_image_sizes_attr', 'gadgetim_pdp_sizes', 10, 2 );
	add_filter( 'gadgetim_breadcrumbs', 'gadgetim_pdp_breadcrumbs' );
	add_filter( 'gadgetim_config', 'gadgetim_pdp_config' );
	add_filter( 'gadgetim_contact_slip_html', 'gadgetim_pdp_slip_icons', 20 );
}
add_action( 'init', 'gadgetim_pdp_hooks', 25 );

/* ---------------------------------------------------------------------------
 * Settings: product page texts (Storefront tab)
 * ------------------------------------------------------------------------- */

/**
 * init 6: «Product page» section of the Storefront tab — the popover texts of the delivery, return
 * and payment cards under the buy button (per language; empty → the built-in sentence).
 */
function gadgetim_pdp_settings(): void {
	$schema = 'Gadgetim\Core\Settings\Schema';
	if ( ! class_exists( $schema ) || ! method_exists( $schema, 'add_section' ) || ! method_exists( $schema, 'add_field' ) ) {
		return;
	}
	$schema::add_section( 'theme', 'pdp', __( 'Product page', 'gadgetim' ), __( 'Texts shown when a shopper taps the delivery, return or payment card under the buy button. The card titles come from the Trust tab. Leave a text empty to use the built-in sentence.', 'gadgetim' ), 30 );
	$fields = [
		'theme.pdp_delivery' => __( 'Delivery card text', 'gadgetim' ),
		'theme.pdp_return'   => __( 'Return card text', 'gadgetim' ),
		'theme.pdp_payment'  => __( 'Payment card text', 'gadgetim' ),
	];
	foreach ( $fields as $path => $label ) {
		$schema::add_field(
			'theme',
			'pdp',
			$path,
			[
				'type'  => 'textarea',
				'i18n'  => true,
				'rows'  => 3,
				'label' => $label,
			]
		);
	}
}
add_action( 'init', 'gadgetim_pdp_settings', 6 );

/* ---------------------------------------------------------------------------
 * Data helpers
 * ------------------------------------------------------------------------- */

/**
 * Gallery items in display order: [ [ 'type' => 'image', 'id' => int ] | [ 'type' => 'video', 'id' => int, 'poster' => int ], … ].
 * Uses WooCommerce 11's media gallery when present (videos survive), else the image ids.
 *
 * @return array<int,array{type:string,id:int,poster?:int}>
 */
function gadgetim_pdp_gallery_items( WC_Product $product ): array {
	$items = [];
	$class = 'Automattic\WooCommerce\Internal\ProductGallery\ProductMediaGallery';
	if ( class_exists( $class ) && method_exists( $class, 'get_product_media_gallery_items_for_display' ) ) {
		foreach ( (array) $class::get_product_media_gallery_items_for_display( $product ) as $item ) {
			if ( ! is_array( $item ) || 'placeholder' === ( $item['source_type'] ?? '' ) ) {
				continue;
			}
			$id = (int) ( $item['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			if ( 'video' === ( $item['media_type'] ?? '' ) ) {
				$poster  = method_exists( $class, 'get_video_poster_id' ) ? (int) $class::get_video_poster_id( $item ) : 0;
				$items[] = [
					'type'   => 'video',
					'id'     => $id,
					'poster' => $poster,
				];
			} elseif ( wp_attachment_is_image( $id ) ) {
				$items[] = [
					'type' => 'image',
					'id'   => $id,
				];
			}
		}
	}
	if ( ! $items ) {
		$ids = array_merge( [ (int) $product->get_image_id() ], array_map( 'intval', (array) $product->get_gallery_image_ids() ) );
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			if ( wp_attachment_is_image( $id ) ) {
				$items[] = [
					'type' => 'image',
					'id'   => $id,
				];
			}
		}
	}
	/**
	 * Filters the product page gallery items.
	 *
	 * @param array      $items   Items.
	 * @param WC_Product $product Product.
	 */
	return (array) apply_filters( 'gadgetim_pdp_gallery_items', $items, $product );
}

/**
 * The product's tabs after the woocommerce_product_tabs filter (cached per product and request), so
 * the sections and the chips honour stores and plugins that unset or add a tab.
 *
 * @return array<string,array>
 */
function gadgetim_pdp_tabs( WC_Product $product ): array {
	static $cache = [];
	$id = $product->get_id();
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}
	// woocommerce_default_product_tabs() reads the global $product.
	$previous           = $GLOBALS['product'] ?? null;
	$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
	/** This filter is documented in woocommerce/templates/single-product/tabs/tabs.php */
	$tabs               = (array) apply_filters( 'woocommerce_product_tabs', [] );
	$GLOBALS['product'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$cache[ $id ]       = $tabs;
	return $tabs;
}

/**
 * Specification rows: the plugin's _gtm_specs (ProductExtras::specs) followed by the visible
 * WooCommerce attributes / weight / dimensions (through woocommerce_display_product_attributes).
 *
 * @return array<int,array{0:string,1:string}>
 */
function gadgetim_pdp_specs( WC_Product $product ): array {
	static $cache = [];
	$key = $product->get_id() . ':' . gadgetim_lang();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$rows = [];
	if ( class_exists( 'Gadgetim\Core\Catalog\ProductExtras' ) ) {
		foreach ( (array) Gadgetim\Core\Catalog\ProductExtras::specs( $product ) as $row ) {
			$label = trim( wp_strip_all_tags( (string) ( $row[0] ?? '' ) ) );
			$value = trim( wp_strip_all_tags( (string) ( $row[1] ?? '' ) ) );
			if ( '' !== $label && '' !== $value ) {
				$rows[] = [ $label, $value ];
			}
		}
	}
	$tabs = gadgetim_pdp_tabs( $product );
	if ( isset( $tabs['additional_information'] ) || ! $rows ) {
		$rows = array_merge( $rows, gadgetim_pdp_attribute_rows( $product ) );
	}
	/**
	 * Filters the specification rows of the product page.
	 *
	 * @param array      $rows    [ [ label, value ], … ].
	 * @param WC_Product $product Product.
	 */
	$cache[ $key ] = (array) apply_filters( 'gadgetim_product_specs', $rows, $product );
	return $cache[ $key ];
}

/**
 * Visible attributes, weight and dimensions as [ label, value ] rows, built in the shape of
 * wc_display_product_attributes() and passed through its filter so plugins that edit those rows work.
 *
 * @return array<int,array{0:string,1:string}>
 */
function gadgetim_pdp_attribute_rows( WC_Product $product ): array {
	$separator = 'en' === gadgetim_lang() ? ', ' : '، ';
	$items     = [];
	if ( $product->has_weight() ) {
		$items['weight'] = [
			'label' => __( 'Weight', 'gadgetim' ),
			'value' => wc_format_weight( $product->get_weight() ),
		];
	}
	if ( $product->has_dimensions() ) {
		$items['dimensions'] = [
			'label' => __( 'Dimensions', 'gadgetim' ),
			'value' => wc_format_dimensions( $product->get_dimensions( false ) ),
		];
	}
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) {
			continue;
		}
		if ( $attribute->is_taxonomy() ) {
			$terms  = wc_get_product_terms( $product->get_id(), $attribute->get_name(), [ 'fields' => 'all' ] );
			$values = array_map( static fn( $t ): string => $t instanceof WP_Term ? gadgetim_term_name( $t ) : '', (array) $terms );
		} else {
			$values = array_map( 'trim', (array) $attribute->get_options() );
		}
		$values = array_filter( array_map( 'wp_strip_all_tags', $values ) );
		if ( $values ) {
			$items[ 'attribute_' . sanitize_title_with_dashes( $attribute->get_name() ) ] = [
				'label' => wc_attribute_label( $attribute->get_name(), $product ),
				'value' => implode( $separator, $values ),
			];
		}
	}
	/** This filter is documented in woocommerce/includes/wc-template-functions.php (wc_display_product_attributes). */
	$items = (array) apply_filters( 'woocommerce_display_product_attributes', $items, $product );
	$rows  = [];
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['label'], $item['value'] ) ) {
			continue;
		}
		$value = trim( wp_strip_all_tags( (string) $item['value'] ) );
		if ( '' !== $value ) {
			$rows[] = [ wp_strip_all_tags( (string) $item['label'] ), $value ];
		}
	}
	return $rows;
}

/**
 * Up to $limit "key feature" rows for the title area: the first spec rows, the brand row skipped
 * (the brand chip already says it).
 *
 * @return array<int,array{0:string,1:string}>
 */
function gadgetim_pdp_key_specs( WC_Product $product, int $limit = 4 ): array {
	$brand = gadgetim_product_brand( $product );
	$skip  = $brand ? [ mb_strtolower( trim( $brand->name ) ), mb_strtolower( trim( gadgetim_term_name( $brand ) ) ) ] : [];
	$out   = [];
	foreach ( gadgetim_pdp_specs( $product ) as $row ) {
		if ( in_array( mb_strtolower( trim( $row[1] ) ), $skip, true ) || mb_strlen( $row[1] ) > 60 ) {
			continue;
		}
		$out[] = $row;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * FAQ rows: the product's own (ProductExtras::faq), else a short store FAQ built from the trust
 * settings (authenticity, delivery, returns, advice) — filter gadgetim_pdp_faq_fallback → false hides it.
 *
 * @return array{rows:array<int,array{q:string,a:string}>,own:bool}
 */
function gadgetim_pdp_faq( WC_Product $product ): array {
	$rows = [];
	if ( class_exists( 'Gadgetim\Core\Catalog\ProductExtras' ) ) {
		foreach ( (array) Gadgetim\Core\Catalog\ProductExtras::faq( $product ) as $row ) {
			$q = trim( wp_strip_all_tags( (string) ( $row['q'] ?? '' ) ) );
			$a = trim( (string) ( $row['a'] ?? '' ) );
			if ( '' !== $q && '' !== $a ) {
				$rows[] = [
					'q' => $q,
					'a' => $a,
				];
			}
		}
	}
	if ( $rows ) {
		return [
			'rows' => $rows,
			'own'  => true,
		];
	}
	/**
	 * Filters whether the store FAQ is shown on products without their own questions.
	 *
	 * @param bool       $show    Default true.
	 * @param WC_Product $product Product.
	 */
	if ( ! apply_filters( 'gadgetim_pdp_faq_fallback', true, $product ) ) {
		return [
			'rows' => [],
			'own'  => false,
		];
	}
	$guarantee = class_exists( 'Gadgetim\Core\Catalog\ProductExtras' ) ? wp_strip_all_tags( (string) Gadgetim\Core\Catalog\ProductExtras::guarantee( $product ) ) : '';
	$steps     = gadgetim_list_setting( 'trust.verification_steps' );
	$delivery  = gadgetim_t( 'trust.delivery', __( 'Delivery across Iran', 'gadgetim' ) );
	$return    = gadgetim_t( 'trust.return_window', __( '7-day return guarantee', 'gadgetim' ) );
	$separator = 'en' === gadgetim_lang() ? ', ' : '، ';

	$authentic = '' !== trim( $guarantee ) ? trim( $guarantee ) : __( 'Every product is genuine, with an authenticity guarantee.', 'gadgetim' );
	if ( $steps ) {
		/* translators: %s: list of verification steps */
		$authentic .= ' ' . sprintf( __( 'You can check it yourself: %s.', 'gadgetim' ), implode( $separator, $steps ) );
	}
	$rows = [
		[
			'q' => __( 'Is this product original?', 'gadgetim' ),
			'a' => $authentic,
		],
		[
			'q' => __( 'How is my order delivered?', 'gadgetim' ),
			/* translators: %s: delivery promise from the settings, e.g. "Delivery across Iran" */
			'a' => sprintf( __( '%s. Shipping is paid on delivery: you pay the courier when the parcel reaches you.', 'gadgetim' ), $delivery ),
		],
		[
			'q' => __( 'Can I return it?', 'gadgetim' ),
			/* translators: %s: return promise from the settings, e.g. "7-day return guarantee" */
			'a' => sprintf( __( 'Yes — %s. If the product does not match its description, contact us within that window and we will arrange the return.', 'gadgetim' ), $return ),
		],
	];
	if ( '' !== gadgetim_whatsapp_url() ) {
		$rows[] = [
			'q' => __( 'Not sure it is the right one for you?', 'gadgetim' ),
			'a' => __( 'Ask us on WhatsApp before you buy: we will check compatibility with your device and suggest the right model.', 'gadgetim' ),
		];
	}
	return [
		'rows' => (array) apply_filters( 'gadgetim_pdp_store_faq', $rows, $product ),
		'own'  => false,
	];
}

/**
 * In-page sections for the chips, in page order: id => label. Only sections with content and, for
 * the WooCommerce-backed ones, only while their tab survives the woocommerce_product_tabs filter.
 *
 * @return array<string,string>
 */
function gadgetim_pdp_sections( WC_Product $product ): array {
	$tabs     = gadgetim_pdp_tabs( $product );
	$sections = [];
	if ( isset( $tabs['description'] ) && '' !== trim( (string) $product->get_description() ) ) {
		$sections['tab-description'] = __( 'Overview', 'gadgetim' );
	}
	if ( gadgetim_pdp_specs( $product ) ) {
		$sections['tab-specs'] = __( 'Specifications', 'gadgetim' );
	}
	if ( isset( $tabs['reviews'] ) && comments_open( $product->get_id() ) && function_exists( 'wc_reviews_enabled' ) && wc_reviews_enabled() ) {
		$sections['tab-reviews'] = __( 'Reviews', 'gadgetim' );
	}
	if ( gadgetim_pdp_faq( $product )['rows'] ) {
		$sections['tab-faq'] = __( 'FAQ', 'gadgetim' );
	}
	foreach ( $tabs as $key => $tab ) {
		if ( in_array( $key, [ 'description', 'additional_information', 'reviews' ], true ) || empty( $tab['title'] ) ) {
			continue;
		}
		/** This filter is documented in woocommerce/templates/single-product/tabs/tabs.php */
		$sections[ 'tab-' . sanitize_html_class( (string) $key ) ] = wp_strip_all_tags( (string) apply_filters( 'woocommerce_product_' . $key . '_tab_title', $tab['title'], $key ) );
	}
	if ( gadgetim_pdp_has_related( $product ) ) {
		$sections['related'] = __( 'Related', 'gadgetim' );
	}
	/**
	 * Filters the in-page sections (chip links) of the product page.
	 *
	 * @param array      $sections id => label.
	 * @param WC_Product $product  Product.
	 */
	return (array) apply_filters( 'gadgetim_pdp_sections', $sections, $product );
}

/**
 * Whether single-product/related.php will print something (no dead #related chip).
 */
function gadgetim_pdp_has_related( WC_Product $product ): bool {
	if ( ! function_exists( 'wc_get_related_products' ) || ! has_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products' ) ) {
		return false;
	}
	return (bool) wc_get_related_products( $product->get_id(), 1, $product->get_upsell_ids() );
}

/**
 * Review numbers: average, count, histogram [ 5 => n, …, 1 => n ] and the total of rated reviews.
 *
 * @return array{average:float,count:int,rated:int,bars:array<int,int>}
 */
function gadgetim_pdp_review_stats( WC_Product $product ): array {
	$counts = array_map( 'intval', (array) $product->get_rating_counts() );
	$bars   = [];
	for ( $r = 5; $r >= 1; $r-- ) {
		$bars[ $r ] = (int) ( $counts[ $r ] ?? 0 );
	}
	return [
		'average' => (float) $product->get_average_rating(),
		'count'   => (int) $product->get_review_count(),
		'rated'   => array_sum( $bars ),
		'bars'    => $bars,
	];
}

/**
 * The three promise cards under the buy button: id => [ icon, tone, title, body ].
 * Titles from the Trust tab, bodies from Storefront → Product page (built-in sentences when empty).
 *
 * @return array<string,array{icon:string,tone:string,title:string,body:string}>
 */
function gadgetim_pdp_perks_data(): array {
	$perks = [
		'ship'   => [
			'icon'  => 'truck',
			'tone'  => 'cool',
			'title' => gadgetim_t( 'trust.delivery', __( 'Delivery across Iran', 'gadgetim' ) ),
			'body'  => gadgetim_t( 'theme.pdp_delivery', __( 'Orders are packed securely and sent to every city in Iran. Shipping is paid on delivery: you pay the courier when the parcel reaches you.', 'gadgetim' ) ),
		],
		'return' => [
			'icon'  => 'return',
			'tone'  => 'green',
			'title' => gadgetim_t( 'trust.return_window', __( '7-day return guarantee', 'gadgetim' ) ),
			'body'  => gadgetim_t( 'theme.pdp_return', __( 'If the product does not match its description or arrives faulty, contact us within the return window and we will arrange the return.', 'gadgetim' ) ),
		],
		'pay'    => [
			'icon'  => 'lock',
			'tone'  => 'pink',
			'title' => __( 'Secure payment', 'gadgetim' ),
			'body'  => gadgetim_t( 'theme.pdp_payment', __( 'You pay on the secure page of the bank gateway. Your card details are entered there and are never stored by Gadgetim.', 'gadgetim' ) ),
		],
	];
	/**
	 * Filters the promise cards of the buy box.
	 *
	 * @param array $perks id => [ icon, tone, title, body ].
	 */
	return (array) apply_filters( 'gadgetim_pdp_perks', $perks );
}

/**
 * Explanation of a currency-priced product for the base-hint popover ('' for fixed-Toman products):
 * the base amount, the rate with its date, the rounding rule, «payment is always in Toman».
 *
 * @return array{hint:string,text:string}
 */
function gadgetim_pdp_base_note( WC_Product $product ): array {
	$empty   = [
		'hint' => '',
		'text' => '',
	];
	$display = 'Gadgetim\Core\Pricing\Display';
	$meta    = 'Gadgetim\Core\Pricing\Meta';
	$curr    = 'Gadgetim\Core\Pricing\Currencies';
	if ( ! class_exists( $display ) || ! method_exists( $display, 'base_hint' ) || ! (bool) gadgetim_setting( 'pricing.base_hint_single', true ) ) {
		return $empty;
	}
	$lang = gadgetim_lang();
	$hint = (string) $display::base_hint( $product, $lang, $product->is_on_sale() );
	if ( '' === trim( wp_strip_all_tags( $hint ) ) ) {
		return $empty;
	}
	$text = '';
	if ( class_exists( $meta ) && class_exists( $curr ) && method_exists( $curr, 'get' ) ) {
		$code     = (string) $meta::currency( $product );
		$currency = '' !== $code ? $curr::get( $code ) : null;
		if ( is_array( $currency ) ) {
			$applied = method_exists( $curr, 'applied' ) ? (array) $curr::applied( $currency ) : $currency;
			$rate    = (string) $meta::rate_used( $product );
			$rate    = '' !== $rate ? $rate : (string) ( $applied['rate'] ?? '' );
			$amount  = $product->is_on_sale() && '' !== (string) $meta::base_sale( $product ) ? (string) $meta::base_sale( $product ) : (string) $meta::base_regular( $product );
			$base    = method_exists( $curr, 'format_amount' ) ? (string) $curr::format_amount( $amount, $currency ) : $code . ' ' . $amount;
			$label   = method_exists( $curr, 'label' ) ? (string) $curr::label( $currency, $lang ) : $code;
			$when    = (int) $meta::priced_at( $product );
			$when    = $when > 0 ? $when : (int) ( $currency['updated_at'] ?? 0 );
			$step    = (int) ( $applied['rounding_step'] ?? 0 );
			$mode    = (string) ( $applied['rounding_mode'] ?? '' );
			if ( is_numeric( $rate ) && (float) $rate > 0 && '' !== $base ) {
				$base_html = '<bdi dir="ltr">' . esc_html( $base ) . '</bdi>';
				$text      = $when > 0
					/* translators: 1: base price with its currency (AED 25), 2: rate in Toman, 3: currency name, 4: date of the rate */
					? sprintf( esc_html__( 'This product is priced at %1$s. Its Toman price uses a rate of %2$s Toman per %3$s (rate of %4$s).', 'gadgetim' ), $base_html, esc_html( gadgetim_number( (float) $rate ) ), esc_html( $label ), esc_html( gadgetim_date( $when, 'medium' ) ) )
					/* translators: 1: base price with its currency (AED 25), 2: rate in Toman, 3: currency name */
					: sprintf( esc_html__( 'This product is priced at %1$s. Its Toman price uses a rate of %2$s Toman per %3$s.', 'gadgetim' ), $base_html, esc_html( gadgetim_number( (float) $rate ) ), esc_html( $label ) );
				if ( $step > 1 && in_array( $mode, [ 'nearest', 'up', 'down' ], true ) ) {
					$rounding = [
						/* translators: %s: rounding step in Toman */
						'nearest' => esc_html__( 'The result is rounded to the nearest %s Toman.', 'gadgetim' ),
						/* translators: %s: rounding step in Toman */
						'up'      => esc_html__( 'The result is rounded up to the next %s Toman.', 'gadgetim' ),
						/* translators: %s: rounding step in Toman */
						'down'    => esc_html__( 'The result is rounded down to a whole %s Toman.', 'gadgetim' ),
					];
					$text .= ' ' . sprintf( $rounding[ $mode ], esc_html( gadgetim_number( $step ) ) );
				}
			}
		}
	}
	$text .= ( '' !== $text ? ' ' : '' ) . esc_html__( 'You always pay in Toman.', 'gadgetim' );
	return [
		'hint' => $hint,
		'text' => $text,
	];
}

/**
 * WhatsApp order link with the product, the quantity and its URL ('' when no number is set).
 * The placeholder __QTY__ (kept intact by the URL encoding) lets gallery.js swap the quantity.
 */
function gadgetim_pdp_whatsapp_order_url( WC_Product $product, string $qty = '__QTY__' ): string {
	/* translators: 1: product title, 2: quantity, 3: product URL */
	$text = sprintf( __( 'Hello, I would like to order “%1$s” (quantity: %2$s). %3$s', 'gadgetim' ), gadgetim_short_title( $product ), $qty, $product->get_permalink() );
	return gadgetim_whatsapp_url( $text );
}

/**
 * WhatsApp availability question for an out-of-stock product ('' when no number is set).
 */
function gadgetim_pdp_whatsapp_ask_url( WC_Product $product ): string {
	/* translators: 1: product title, 2: product URL */
	$text = sprintf( __( 'Hello, when will “%1$s” be available again? %2$s', 'gadgetim' ), gadgetim_short_title( $product ), $product->get_permalink() );
	return gadgetim_whatsapp_url( $text );
}

/**
 * Real deadline for the buy box countdown: the product's scheduled sale end, else the admin-set
 * campaign end while the product is on sale; 0 when none (never a fake timer).
 */
function gadgetim_pdp_deadline( WC_Product $product ): int {
	if ( ! $product->is_on_sale() || ! $product->is_in_stock() || gadgetim_contact_mode( $product ) ) {
		return 0;
	}
	$end = gadgetim_sale_end( $product );
	if ( $end <= 0 && gadgetim_product_discount( $product ) >= gadgetim_sale_thresholds()['sticker'] ) {
		$end = gadgetim_campaign_end();
	}
	return $end;
}

/**
 * Share targets: [ [ id, label, url, icon ], … ].
 *
 * @return array<int,array{id:string,label:string,url:string,icon:string}>
 */
function gadgetim_pdp_share_links( WC_Product $product ): array {
	$url  = (string) $product->get_permalink();
	$text = gadgetim_short_title( $product );
	return (array) apply_filters(
		'gadgetim_pdp_share_links',
		[
			[
				'id'    => 'telegram',
				'label' => __( 'Telegram', 'gadgetim' ),
				'url'   => 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $text ),
				'icon'  => 'telegram',
			],
			[
				'id'    => 'whatsapp',
				'label' => __( 'WhatsApp', 'gadgetim' ),
				'url'   => 'https://wa.me/?text=' . rawurlencode( $text . ' ' . $url ),
				'icon'  => 'chat',
			],
		],
		$product
	);
}

/**
 * lang/dir attributes for content blocks while the I18n plugin shows Persian content as a
 * fallback under /en/ or /ar/ ('' otherwise).
 */
function gadgetim_pdp_content_attrs(): string {
	$class = 'Gadgetim\Core\I18n\Visibility';
	if ( class_exists( $class ) && method_exists( $class, 'fallback_active' ) && $class::fallback_active() ) {
		$lang = method_exists( $class, 'content_lang' ) ? (string) $class::content_lang() : 'fa';
		$dir  = method_exists( $class, 'content_dir' ) ? (string) $class::content_dir() : 'rtl';
		return ' lang="' . esc_attr( $lang ) . '" dir="' . esc_attr( $dir ) . '"';
	}
	return '';
}

/**
 * Spec value for display: language digits (Latin tokens protected) and Latin runs isolated.
 */
function gadgetim_pdp_text( string $text ): string {
	return gadgetim_bidi( gadgetim_digits( $text ) );
}

/**
 * Language digits in the text nodes of an HTML fragment (description, short description, FAQ
 * answers, guarantee): Support\Digits::html leaves tags, attributes, model names, URLs and e-mails
 * alone. Fragments carrying scripts or styles are returned untouched.
 */
function gadgetim_pdp_html_digits( string $html ): string {
	$digits = 'Gadgetim\Core\Support\Digits';
	if ( '' === $html || ! class_exists( $digits ) || ! method_exists( $digits, 'html' ) || preg_match( '/<(script|style|svg|math)\b/i', $html ) ) {
		return $html;
	}
	return (string) $digits::html( $html, gadgetim_lang() );
}

/**
 * Inline SVG icons the shared sprite does not carry (expand, copy link, quote).
 */
function gadgetim_pdp_svg( string $name, string $class = '' ): string {
	$paths = [
		'expand' => '<path d="M14.5 4.5h5v5M9.5 19.5h-5v-5M19.5 4.5 14 10M4.5 19.5 10 14"/>',
		'link'   => '<path d="M10 14a4.2 4.2 0 0 0 6 0l3-3a4.24 4.24 0 0 0-6-6l-1.2 1.2"/><path d="M14 10a4.2 4.2 0 0 0-6 0l-3 3a4.24 4.24 0 0 0 6 6l1.2-1.2"/>',
		'quote'  => '<path d="M9.5 7C6.9 7.6 5 9.9 5 12.7V17h4.6v-4.4H7.4c.1-1.6 1.1-2.9 2.6-3.4L9.5 7Zm8 0c-2.6.6-4.5 2.9-4.5 5.7V17h4.6v-4.4h-2.2c.1-1.6 1.1-2.9 2.6-3.4L17.5 7Z"/>',
		'seal'   => '<path class="pdp-draw" pathLength="1" d="M12 2.8 4.8 5.6v5.6c0 4.6 3 8.4 7.2 9.9 4.2-1.5 7.2-5.3 7.2-9.9V5.6L12 2.8Z"/><path class="pdp-draw" pathLength="1" d="m8.6 12.2 2.4 2.4 4.4-4.6"/>',
	];
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="' . esc_attr( trim( 'ic ' . $class ) ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/* ---------------------------------------------------------------------------
 * Summary hook callbacks (the buy box)
 * ------------------------------------------------------------------------- */

/**
 * Summary 10: the price block (big price, struck regular + discount, saving, base-currency line with
 * its explanation, real countdown) — or the contact slip in call-for-price mode.
 */
function gadgetim_pdp_price(): void {
	global $product;
	if ( $product instanceof WC_Product ) {
		gadgetim_part( 'product/price', [ 'product' => $product ] );
	}
}

/**
 * Summary 15: stock state (in stock · «only N left» with a bar · on backorder · out of stock),
 * passed through woocommerce_get_stock_html like WooCommerce's own stock line.
 */
function gadgetim_pdp_stock(): void {
	global $product;
	if ( ! $product instanceof WC_Product || $product->is_type( 'variable' ) ) {
		return;
	}
	ob_start();
	gadgetim_part( 'product/stock', [ 'product' => $product ] );
	$html = (string) ob_get_clean();
	/** This filter is documented in woocommerce/includes/wc-template-functions.php (wc_get_stock_html). */
	echo apply_filters( 'woocommerce_get_stock_html', $html, $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme template output passed through WooCommerce's filter.
}

/**
 * Summary 32: the WhatsApp order / availability link for products whose add-to-cart template does
 * not print it (out of stock, variable, grouped, external). simple.php prints its own row.
 */
function gadgetim_pdp_secondary(): void {
	global $product;
	if ( ! $product instanceof WC_Product || gadgetim_contact_mode( $product ) || ! empty( $GLOBALS['gadgetim_pdp_alt_done'] ) ) {
		return;
	}
	$in  = $product->is_in_stock();
	$url = $in ? gadgetim_pdp_whatsapp_order_url( $product, gadgetim_number( 1 ) ) : gadgetim_pdp_whatsapp_ask_url( $product );
	if ( '' === $url ) {
		return;
	}
	echo '<div class="buy__alt buy__alt--solo"><a class="btn buy__wa" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . gadgetim_icon( 'chat' ) . '<span>' . ( $in ? esc_html__( 'Order on WhatsApp', 'gadgetim' ) : esc_html__( 'Ask about availability on WhatsApp', 'gadgetim' ) ) . '</span></a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon escaped in the helper.
}

/**
 * Summary 45: delivery · return · secure payment cards with popovers.
 */
function gadgetim_pdp_perks(): void {
	gadgetim_part( 'product/perks', [ 'perks' => gadgetim_pdp_perks_data() ] );
}

/**
 * After summary 25: «recently viewed» rail, filled by gallery.js from localStorage through
 * GET gadgetim/v1/cards (hidden until it has cards; nothing without JS).
 */
function gadgetim_pdp_recent(): void {
	global $product;
	if ( $product instanceof WC_Product ) {
		gadgetim_part( 'product/recent', [ 'product' => $product ] );
	}
}

/**
 * woocommerce_review_before 10: initial-letter avatar (no Gravatar request).
 *
 * @param WP_Comment $comment Review.
 */
function gadgetim_pdp_review_avatar( $comment ): void {
	if ( ! $comment instanceof WP_Comment ) {
		return;
	}
	$name    = trim( wp_strip_all_tags( (string) $comment->comment_author ) );
	$initial = '' !== $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '؟';
	$tone    = abs( crc32( $name ) ) % 5;
	echo '<span class="rv-ava rv-ava--' . esc_attr( (string) $tone ) . '" aria-hidden="true">' . esc_html( $initial ) . '</span>';
}

/* ---------------------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------------------- */

/**
 * «خرید سریع»: the quick-buy button posts the add-to-cart form to «product URL?gtm-buy-now=1»;
 * after a successful add WooCommerce redirects to the checkout instead of reloading the product.
 *
 * @param string|false $url     Redirect URL.
 * @param mixed        $product Product added.
 * @return string|false
 */
function gadgetim_pdp_buy_now_redirect( $url, $product = null ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects the redirect target after WooCommerce's own add-to-cart handling.
	if ( empty( $_GET['gtm-buy-now'] ) || ! function_exists( 'wc_get_checkout_url' ) || wc_notice_count( 'error' ) > 0 ) {
		return $url;
	}
	return wc_get_checkout_url();
}

/**
 * Review form: theme button and form classes.
 *
 * @param array $args comment_form() arguments.
 * @return array
 */
function gadgetim_pdp_review_form_args( array $args ): array {
	$args['class_form']    = trim( (string) ( $args['class_form'] ?? 'comment-form' ) . ' rv-form__form' );
	$args['class_submit']  = 'btn btn--lg rv-form__submit';
	$args['submit_button'] = '<button name="%1$s" type="submit" id="%2$s" class="%3$s">' . gadgetim_icon( 'check' ) . '<span>%4$s</span></button>';
	return $args;
}

/**
 * Accurate `sizes` for the product page plate (also used by the LCP preload in <head>).
 */
function gadgetim_pdp_sizes( string $sizes, string $context ): string {
	if ( 'pdp' === $context ) {
		return '(min-width:1240px) 500px, (min-width:768px) 44vw, calc(100vw - 32px)';
	}
	if ( 'pdp-lb' === $context ) {
		return '100vw';
	}
	return $sizes;
}

/**
 * Breadcrumbs on the product page end with the short title, not the long legacy one.
 *
 * @param array $crumbs [ [ label, url ], … ].
 * @return array
 */
function gadgetim_pdp_breadcrumbs( array $crumbs ): array {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! $crumbs ) {
		return $crumbs;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( $product instanceof WC_Product ) {
		$last               = count( $crumbs ) - 1;
		$crumbs[ $last ][0] = gadgetim_digits( gadgetim_short_title( $product ) );
	}
	return $crumbs;
}

/**
 * #gadgetim-config on the product page: the fragment endpoint and the gallery/buy strings.
 *
 * @param array $config Config.
 * @return array
 */
function gadgetim_pdp_config( array $config ): array {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $config;
	}
	$config['pdp']     = [
		'cards'     => esc_url_raw( rest_url( 'gadgetim/v1/cards' ) ),
		'recentKey' => 'gtm_recent',
	];
	$config['strings'] = array_merge(
		(array) ( $config['strings'] ?? [] ),
		[
			/* translators: 1: image number, 2: number of images */
			'pdpImage'    => __( 'Image %1$s of %2$s', 'gadgetim' ),
			'pdpZoomIn'   => __( 'Zoom in', 'gadgetim' ),
			'pdpZoomOut'  => __( 'Zoom out', 'gadgetim' ),
			'pdpCopied'   => __( 'Link copied', 'gadgetim' ),
			'pdpCopyFail' => __( 'Copying is blocked here. Hold the address bar to copy the link.', 'gadgetim' ),
			'pdpMore'     => __( 'Read more', 'gadgetim' ),
			'pdpLess'     => __( 'Show less', 'gadgetim' ),
			'pdpRate1'    => __( 'Very poor', 'gadgetim' ),
			'pdpRate2'    => __( 'Not that bad', 'gadgetim' ),
			'pdpRate3'    => __( 'Average', 'gadgetim' ),
			'pdpRate4'    => __( 'Good', 'gadgetim' ),
			'pdpRate5'    => __( 'Perfect', 'gadgetim' ),
		]
	);
	return $config;
}

/**
 * The plugin's contact slip names sprite symbols without the theme's "i-" prefix (#phone, #whatsapp,
 * #telegram): point them at the theme sprite so the channel icons show.
 */
function gadgetim_pdp_slip_icons( string $html ): string {
	return str_replace(
		[ 'href="#phone"', 'href="#whatsapp"', 'href="#telegram"' ],
		[ 'href="#i-phone"', 'href="#i-chat"', 'href="#i-telegram"' ],
		$html
	);
}

/* ---------------------------------------------------------------------------
 * REST: card fragment for «recently viewed»
 * ------------------------------------------------------------------------- */

/**
 * rest_api_init 20: GET gadgetim/v1/cards?ids=1,2,3[&lang=fa] → { html, count } — rail cards
 * rendered by gadgetim_product_card(), so they match every other card (price partial, pills,
 * hearts, add buttons). Lives under gadgetim/v1 because only that namespace (and wc/store) honours
 * ?lang= in the I18n plugin. Skipped when the plugin registers the same route.
 */
function gadgetim_pdp_rest_routes(): void {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$server = rest_get_server();
	if ( method_exists( $server, 'get_routes' ) && isset( $server->get_routes( 'gadgetim/v1' )['/gadgetim/v1/cards'] ) ) {
		return;
	}
	register_rest_route(
		'gadgetim/v1',
		'/cards',
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'gadgetim_pdp_rest_cards',
			'permission_callback' => '__return_true',
			'args'                => [
				'ids' => [
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => static fn( $value ): bool => is_string( $value ) && 1 === preg_match( '/^\d{1,10}(,\d{1,10}){0,11}$/', $value ),
				],
			],
		]
	);
}
add_action( 'rest_api_init', 'gadgetim_pdp_rest_routes', 20 );

/**
 * Card fragment callback.
 */
function gadgetim_pdp_rest_cards( WP_REST_Request $request ): WP_REST_Response {
	$rate = 'Gadgetim\Core\Support\RateLimit';
	if ( class_exists( $rate ) && method_exists( $rate, 'allow' ) && ! $rate::allow( 'pdp_cards', 60, 60 ) ) {
		return new WP_REST_Response( [ 'html' => '', 'count' => 0 ], 429 );
	}
	$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) ) ) ), 0, 12 );
	$vis = 'Gadgetim\Core\I18n\Visibility';
	if ( $ids && class_exists( $vis ) && method_exists( $vis, 'filter_ids' ) ) {
		$ids = (array) $vis::filter_ids( $ids );
	}
	// The cards' no-JS add link would otherwise point at this REST URL.
	$fix_url = static function ( $url, $product ) {
		return $product instanceof WC_Product && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock()
			? add_query_arg( 'add-to-cart', $product->get_id(), $product->get_permalink() )
			: $url;
	};
	add_filter( 'woocommerce_product_add_to_cart_url', $fix_url, 99, 2 );
	$html = '';
	$n    = 0;
	foreach ( $ids as $id ) {
		$card = gadgetim_product_card(
			(int) $id,
			'rail',
			[
				'tag'   => 'li',
				'index' => $n,
			]
		);
		if ( '' !== $card ) {
			$html .= $card;
			++$n;
		}
	}
	remove_filter( 'woocommerce_product_add_to_cart_url', $fix_url, 99 );
	$response = new WP_REST_Response(
		[
			'html'  => $html,
			'count' => $n,
		],
		200
	);
	$response->header( 'Cache-Control', 'public, max-age=300' );
	return $response;
}
