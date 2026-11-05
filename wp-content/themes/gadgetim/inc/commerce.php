<?php
/**
 * Commerce page layer: cart, checkout (with order received and order pay), my account (signed in
 * and signed out) and order tracking, in the Aurora style.
 *
 * What lives here:
 * - gadgetim_commerce_view(): which commerce screen the request shows (cart, checkout, received,
 *   pay, account, auth, tracking or '');
 * - routing of those pages to templates/commerce/page.php (steps indicator, headings, shell);
 * - commerce.css on the order-tracking page (the other screens get it from inc/assets.php),
 *   the commerce JS module (templates/commerce/commerce.js) and native selects instead of selectWoo;
 * - WooCommerce hook wiring: cart suggestions instead of the cross-sells column, the theme's empty
 *   cart, payment methods in their own checkout section, checkout fragments (shipping cards and the
 *   mobile pay bar), quiet "Cart updated." for in-place updates, account menu icons;
 * - helpers used by the templates: steps, trust line, savings, money, status chips, the order
 *   timeline, Persian digits for WooCommerce HTML, account profile data, WhatsApp help links;
 * - the order-tracking shortcode, which also accepts the order's mobile number (the checkout's
 *   e-mail field is optional), rate limited.
 *
 * WooCommerce template overrides: woocommerce/cart/*, checkout/*, myaccount/*, order/*, notices/*.
 * Partials: templates/commerce/*.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/* ---------------------------------------------------------------------------
 * Context
 * ------------------------------------------------------------------------- */

/**
 * The commerce screen of this request: cart, checkout, received (order received), pay (order pay),
 * account (signed in), auth (account pages while signed out: login, register, lost password),
 * tracking (the page with [woocommerce_order_tracking]) or '' elsewhere.
 */
function gadgetim_commerce_view(): string {
	static $view = null;
	if ( null !== $view && did_action( 'wp' ) ) {
		return $view;
	}
	$found = '';
	if ( function_exists( 'is_cart' ) && did_action( 'wp' ) ) {
		if ( is_cart() ) {
			$found = 'cart';
		} elseif ( is_checkout() ) {
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				$found = 'received';
			} elseif ( is_wc_endpoint_url( 'order-pay' ) ) {
				$found = 'pay';
			} else {
				$found = 'checkout';
			}
		} elseif ( is_account_page() ) {
			$found = is_user_logged_in() ? 'account' : 'auth';
		} elseif ( gadgetim_is_tracking_page() ) {
			$found = 'tracking';
		}
	}
	/**
	 * Filters the commerce screen of the request.
	 *
	 * @param string $found cart|checkout|received|pay|account|auth|tracking|''.
	 */
	$found = (string) apply_filters( 'gadgetim_commerce_view', $found );
	if ( did_action( 'wp' ) ) {
		$view = $found;
	}
	return $found;
}

/**
 * ID of the published page that holds [woocommerce_order_tracking] (0 when none), cached for a day
 * and refreshed whenever a page is saved or deleted. Filter: gadgetim_order_tracking_page_id.
 */
function gadgetim_order_tracking_page_id(): int {
	$cached = get_transient( 'gadgetim_tracking_page' );
	if ( false === $cached ) {
		global $wpdb;
		$found  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[woocommerce_order_tracking' ) . '%'
			)
		);
		$cached = (string) $found;
		set_transient( 'gadgetim_tracking_page', $cached, DAY_IN_SECONDS );
	}
	/**
	 * Filters the ID of the order-tracking page.
	 *
	 * @param int $page_id Page ID (0 when none).
	 */
	return (int) apply_filters( 'gadgetim_order_tracking_page_id', (int) $cached );
}

/**
 * Forgets the cached tracking page (save_post_page, deleted_post).
 */
function gadgetim_flush_tracking_page(): void {
	delete_transient( 'gadgetim_tracking_page' );
}
add_action( 'save_post_page', 'gadgetim_flush_tracking_page' );
add_action( 'deleted_post', 'gadgetim_flush_tracking_page' );

/**
 * Whether the current request shows the order-tracking page.
 */
function gadgetim_is_tracking_page(): bool {
	if ( ! is_page() ) {
		return false;
	}
	$id = gadgetim_order_tracking_page_id();
	if ( $id > 0 && get_queried_object_id() === $id ) {
		return true;
	}
	$post = get_queried_object();
	return $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'woocommerce_order_tracking' );
}

/**
 * URL of the order-tracking page ('' when the page does not exist). Header and footer links can use
 * it: function_exists( 'gadgetim_order_tracking_url' ) ? gadgetim_order_tracking_url() : ''.
 */
function gadgetim_order_tracking_url(): string {
	$id = gadgetim_order_tracking_page_id();
	if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
		return '';
	}
	return (string) get_permalink( $id );
}

/* ---------------------------------------------------------------------------
 * Routing, assets, config
 * ------------------------------------------------------------------------- */

/**
 * template_include: cart, checkout, account and tracking pages render through the commerce shell.
 */
function gadgetim_commerce_template( string $template ): string {
	if ( '' === gadgetim_commerce_view() || ! is_singular( 'page' ) ) {
		return $template;
	}
	$shell = locate_template( 'templates/commerce/page.php' );
	return '' !== $shell ? $shell : $template;
}
add_filter( 'template_include', 'gadgetim_commerce_template', 50 );

/**
 * wp_enqueue_scripts 21: commerce.css on the tracking page (inc/assets.php loads it for the
 * cart/checkout/account page kinds) and the commerce module on every commerce screen.
 */
function gadgetim_commerce_assets(): void {
	if ( '' === gadgetim_commerce_view() ) {
		return;
	}
	$css = 'assets/css/commerce.css';
	if ( ! wp_style_is( 'gadgetim-commerce', 'enqueued' ) && gadgetim_css_has_rules( $css ) ) {
		wp_enqueue_style( 'gadgetim-commerce', gadgetim_asset_url( $css ), [ 'gadgetim-layout' ], gadgetim_asset_version( $css ) );
	}
	$js = 'templates/commerce/commerce.js';
	if ( function_exists( 'wp_enqueue_script_module' ) && is_readable( GADGETIM_THEME_DIR . $js ) ) {
		wp_enqueue_script_module( '@gadgetim/commerce', gadgetim_asset_url( $js ), [], gadgetim_asset_version( $js ) );
	}
}
add_action( 'wp_enqueue_scripts', 'gadgetim_commerce_assets', 21 );

/**
 * wp_enqueue_scripts 101: native selects (province, city, shipping calculator) instead of selectWoo,
 * whose stylesheet the theme does not load; WooCommerce keeps working without it.
 */
function gadgetim_commerce_dequeue(): void {
	if ( '' !== gadgetim_commerce_view() ) {
		wp_dequeue_script( 'selectWoo' );
	}
}
add_action( 'wp_enqueue_scripts', 'gadgetim_commerce_dequeue', 101 );

/**
 * gadgetim_config: strings and URLs for the commerce module (commerce screens only).
 *
 * @param array $config Config.
 * @return array
 */
function gadgetim_commerce_config( array $config ): array {
	$view = gadgetim_commerce_view();
	if ( '' === $view ) {
		return $config;
	}
	$config['commerce'] = [
		'view'     => $view,
		'tracking' => gadgetim_order_tracking_url(),
	];
	$config['strings']  = array_merge(
		(array) ( $config['strings'] ?? [] ),
		[
			'removed'      => __( 'Removed from your cart', 'gadgetim' ),
			'undo'         => __( 'Undo', 'gadgetim' ),
			/* translators: %s: quantity */
			'qtyNow'       => __( 'Quantity: %s', 'gadgetim' ),
			'cartUpdating' => __( 'Updating your cart…', 'gadgetim' ),
			'cartUpdated'  => __( 'Your cart has been updated', 'gadgetim' ),
			'showItems'    => __( 'Show items', 'gadgetim' ),
			'hideItems'    => __( 'Hide items', 'gadgetim' ),
			'copied'       => __( 'Order number copied', 'gadgetim' ),
		]
	);
	return $config;
}
add_filter( 'gadgetim_config', 'gadgetim_commerce_config' );

/**
 * body_class: cm-<view> on commerce screens.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function gadgetim_commerce_body_class( array $classes ): array {
	$view = gadgetim_commerce_view();
	if ( '' !== $view ) {
		$classes[] = 'cm-' . sanitize_html_class( $view );
	}
	return $classes;
}
add_filter( 'body_class', 'gadgetim_commerce_body_class' );

/* ---------------------------------------------------------------------------
 * WooCommerce wiring
 * ------------------------------------------------------------------------- */

/**
 * init 30 (after inc/woocommerce.php): commerce hooks.
 */
function gadgetim_commerce_hooks(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Cart: a suggestions rail under the cart instead of the two-up cross-sells column; the theme's
	// empty state (cart/cart-empty.php) carries the empty-cart message.
	remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
	add_action( 'woocommerce_after_cart', 'gadgetim_cart_suggestions', 20 );
	remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
	add_filter( 'woocommerce_add_success', 'gadgetim_quiet_cart_updated' );

	// Checkout: payment methods get their own section in the main column (checkout/form-checkout.php);
	// the shipping cards and the phone pay bar are refreshed with the order review.
	remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	add_action( 'gadgetim_checkout_payment', 'woocommerce_checkout_payment', 10 );
	add_filter( 'woocommerce_update_order_review_fragments', 'gadgetim_checkout_fragments' );

	// Account.
	add_filter( 'woocommerce_account_menu_items', 'gadgetim_account_menu_items', 20 );
}
add_action( 'init', 'gadgetim_commerce_hooks', 30 );

/**
 * init 20 (after WC_Shortcodes::init): the tracking shortcode also accepts the mobile number.
 */
function gadgetim_replace_tracking_shortcode(): void {
	if ( shortcode_exists( 'woocommerce_order_tracking' ) ) {
		remove_shortcode( 'woocommerce_order_tracking' );
		add_shortcode( 'woocommerce_order_tracking', 'gadgetim_order_tracking_shortcode' );
	}
}
add_action( 'init', 'gadgetim_replace_tracking_shortcode', 20 );

/**
 * woocommerce_add_success: no "Cart updated." notice for in-place (XHR) cart updates; the quantity
 * stepper updates the line in place and announces it, and the notice would make WooCommerce scroll
 * the page to the top after every tap. Regular form posts (no JavaScript) keep the notice.
 *
 * @param mixed $message Notice.
 * @return mixed
 */
function gadgetim_quiet_cart_updated( $message ) {
	$xhr = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) );
	if ( $xhr && is_string( $message ) && __( 'Cart updated.', 'woocommerce' ) === $message ) {
		return '';
	}
	return $message;
}

/**
 * woocommerce_update_order_review_fragments: the shipping cards and the phone pay bar.
 *
 * @param mixed $fragments Fragments.
 * @return array
 */
function gadgetim_checkout_fragments( $fragments ): array {
	$fragments           = is_array( $fragments ) ? $fragments : [];
	$fragments['.co-ship'] = gadgetim_capture( 'commerce/checkout-shipping' );
	$fragments['.cbar']    = gadgetim_capture( 'commerce/checkout-bar' );
	return $fragments;
}

/**
 * A templates/<slug>.php part as a string.
 *
 * @param string $slug Part slug.
 * @param array  $args Arguments.
 */
function gadgetim_capture( string $slug, array $args = [] ): string {
	ob_start();
	gadgetim_part( $slug, $args );
	return (string) ob_get_clean();
}

/**
 * The country the checkout is fixed to by gadgetim-core's field set ('IR'), or '' when the field
 * set is inactive (the shipping calculator then keeps WooCommerce's country selector).
 */
function gadgetim_fixed_country(): string {
	if ( class_exists( 'Gadgetim\Core\Checkout\Fields' ) && method_exists( 'Gadgetim\Core\Checkout\Fields', 'active' ) && Gadgetim\Core\Checkout\Fields::active() ) {
		return (string) Gadgetim\Core\Checkout\Fields::COUNTRY;
	}
	return '';
}

/**
 * Where cart/cart-shipping.php renders: 'table' (cart totals) or 'list' (the checkout shipping card).
 */
function gadgetim_ship_context( ?string $set = null ): string {
	static $context = 'table';
	if ( null !== $set ) {
		$context = $set;
	}
	return $context;
}

/* ---------------------------------------------------------------------------
 * Formatting helpers
 * ------------------------------------------------------------------------- */

/**
 * WooCommerce HTML with the language's digits in text nodes only (tags and attributes untouched).
 */
function gadgetim_commerce_digits_html( string $html ): string {
	if ( '' === $html ) {
		return '';
	}
	if ( class_exists( 'Gadgetim\Core\Support\Digits' ) && method_exists( 'Gadgetim\Core\Support\Digits', 'html' ) ) {
		return (string) Gadgetim\Core\Support\Digits::html( $html, gadgetim_lang() );
	}
	$parts = preg_split( '/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( false === $parts ) {
		return $html;
	}
	foreach ( $parts as $i => $part ) {
		if ( '' !== $part && '<' !== $part[0] && ! str_contains( $part, '&#' ) ) {
			$parts[ $i ] = gadgetim_digits( $part );
		}
	}
	return implode( '', $parts );
}

/**
 * A Toman amount as the plugin's price markup (inline span by default).
 *
 * @param float  $amount Amount.
 * @param string $class  Extra classes.
 * @param string $tag    Wrapper tag.
 */
function gadgetim_money( float $amount, string $class = '', string $tag = 'span' ): string {
	return gadgetim_price(
		(float) $amount,
		[
			'tag'   => $tag,
			'class' => trim( 'price--money ' . $class ),
		]
	);
}

/**
 * Allowed HTML for product names in cart and order lines (WooCommerce's post HTML plus <bdi>).
 *
 * @return array
 */
function gadgetim_commerce_kses_name(): array {
	$allowed        = wp_kses_allowed_html( 'post' );
	$allowed['bdi'] = [];
	return $allowed;
}

/**
 * Display name of a cart or order line: the short title for simple products (boilerplate removed,
 * Latin runs isolated), else WooCommerce's own name.
 *
 * @param WC_Product|false|null $product Product.
 * @param string                $name    WooCommerce name.
 */
function gadgetim_line_name( $product, string $name ): string {
	if ( $product instanceof WC_Product && $product->is_type( 'simple' ) ) {
		return gadgetim_bidi( gadgetim_short_title( $product ) );
	}
	return gadgetim_bidi( wp_strip_all_tags( $name ) );
}

/**
 * Number of items in the cart (quantities summed).
 */
function gadgetim_cart_count(): int {
	return function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
}

/**
 * «N items» with locale digits.
 */
function gadgetim_items_label( int $count ): string {
	/* translators: %s: number of items */
	return sprintf( _n( '%s item', '%s items', $count, 'gadgetim' ), gadgetim_number( $count ) );
}

/**
 * Sale saving of one cart line: (regular − current) × quantity, 0 when not on sale.
 *
 * @param WC_Product $product  Product.
 * @param float      $quantity Quantity.
 */
function gadgetim_line_saving( WC_Product $product, float $quantity ): float {
	if ( ! $product->is_on_sale() ) {
		return 0.0;
	}
	$regular = (float) $product->get_regular_price();
	$price   = (float) $product->get_price();
	return $regular > $price ? ( $regular - $price ) * $quantity : 0.0;
}

/**
 * What the shopper saves on this cart: sale savings plus coupon discounts.
 */
function gadgetim_cart_savings(): float {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0.0;
	}
	$saved = 0.0;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
			$saved += gadgetim_line_saving( $item['data'], (float) $item['quantity'] );
		}
	}
	$saved += (float) WC()->cart->get_discount_total();
	/**
	 * Filters the "you save" amount shown in the cart and checkout summaries.
	 *
	 * @param float $saved Amount in Toman.
	 */
	return max( 0.0, (float) apply_filters( 'gadgetim_cart_savings', $saved ) );
}

/**
 * Checkout steps indicator (cart → delivery details → payment).
 *
 * @param string $current cart|details|payment|done.
 */
function gadgetim_commerce_steps( string $current ): string {
	$steps = [
		'cart'    => [ __( 'Cart', 'gadgetim' ), 'bag', function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '' ],
		'details' => [ __( 'Delivery details', 'gadgetim' ), 'pin', function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '' ],
		'payment' => [ __( 'Payment', 'gadgetim' ), 'lock', '' ],
	];
	$keys  = array_keys( $steps );
	$index = 'done' === $current ? count( $keys ) : (int) array_search( $current, $keys, true );
	$html  = '<nav class="csteps" aria-label="' . esc_attr__( 'Checkout progress', 'gadgetim' ) . '" data-steps style="--p:' . esc_attr( (string) min( 1, $index / ( count( $keys ) - 1 ) ) ) . '">';
	$html .= '<ol class="csteps__list">';
	foreach ( $keys as $i => $key ) {
		[ $label, $icon, $url ] = $steps[ $key ];
		$state                  = $i < $index ? 'is-done' : ( $i === $index ? 'is-current' : 'is-next' );
		$dot                    = '<span class="csteps__dot" aria-hidden="true">' . gadgetim_icon( 'is-done' === $state ? 'check' : $icon ) . '</span>';
		$text                   = '<span class="csteps__t"><small>' . esc_html( sprintf( /* translators: %s: step number */ __( 'Step %s', 'gadgetim' ), gadgetim_number( $i + 1 ) ) ) . '</small>' . esc_html( $label ) . '</span>';
		$html                  .= '<li class="csteps__i ' . $state . '" data-step="' . esc_attr( $key ) . '"' . ( 'is-current' === $state ? ' aria-current="step"' : '' ) . '>';
		$html                  .= 'is-done' === $state && '' !== $url && 'done' !== $current
			? '<a class="csteps__a" href="' . esc_url( $url ) . '">' . $dot . $text . '</a>'
			: '<span class="csteps__a">' . $dot . $text . '</span>';
		$html                  .= '</li>';
	}
	return $html . '</ol><span class="csteps__bar" aria-hidden="true"><i></i></span></nav>';
}

/**
 * Trust line under totals and before the pay button: secure payment, authenticity, returns.
 *
 * @param string $class Extra classes.
 */
function gadgetim_commerce_trust( string $class = '' ): string {
	$items = [
		[ 'lock', __( 'Secure payment', 'gadgetim' ) ],
		[ 'shield', __( 'Authenticity guarantee', 'gadgetim' ) ],
		[ 'return', gadgetim_t( 'trust.return_window', __( '7-day return guarantee', 'gadgetim' ) ) ],
	];
	$html = '<ul class="' . esc_attr( trim( 'ctrust ' . $class ) ) . '">';
	foreach ( $items as [ $icon, $label ] ) {
		$html .= '<li>' . gadgetim_icon( $icon ) . '<span>' . esc_html( wp_strip_all_tags( (string) $label ) ) . '</span></li>';
	}
	return $html . '</ul>';
}

/**
 * WhatsApp help link for an order (or a general question when no order is given).
 */
function gadgetim_order_help_url( ?WC_Order $order = null ): string {
	$text = $order instanceof WC_Order
		/* translators: %s: order number */
		? sprintf( __( 'Hello! I have a question about order %s.', 'gadgetim' ), gadgetim_order_number( $order ) )
		: __( 'Hello! I have a question about my order.', 'gadgetim' );
	return gadgetim_whatsapp_url( $text );
}

/**
 * WhatsApp text that lists the cart, for ordering by message when no online payment is available.
 */
function gadgetim_cart_whatsapp_text(): string {
	$lines = [];
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$lines[] = '• ' . gadgetim_short_title( $item['data'] ) . ' × ' . gadgetim_number( (int) $item['quantity'] );
			}
		}
	}
	/* translators: %s: list of products with quantities, one per line */
	return sprintf( __( "Hello! I would like to order:\n%s", 'gadgetim' ), implode( "\n", $lines ) );
}

/* ---------------------------------------------------------------------------
 * Orders: number, date, status chip, timeline
 * ------------------------------------------------------------------------- */

/**
 * Order number in the language's digits.
 */
function gadgetim_order_number( WC_Order $order ): string {
	return gadgetim_digits( (string) $order->get_order_number() );
}

/**
 * Order creation date (Jalali in Persian).
 *
 * @param WC_Order $order Order.
 * @param string   $style short|medium|long|datetime.
 */
function gadgetim_order_date( WC_Order $order, string $style = 'medium' ): string {
	$date = $order->get_date_created();
	return $date ? gadgetim_date( $date->getTimestamp(), $style ) : '';
}

/**
 * Colour family of an order status chip.
 */
function gadgetim_order_status_tone( string $status ): string {
	$tones = [
		'pending'        => 'wait',
		'on-hold'        => 'wait',
		'checkout-draft' => 'wait',
		'processing'     => 'busy',
		'completed'      => 'ok',
		'cancelled'      => 'off',
		'refunded'       => 'off',
		'failed'         => 'bad',
	];
	/**
	 * Filters the chip tone (wait, busy, ok, off, bad) of an order status.
	 *
	 * @param string $tone   Tone.
	 * @param string $status Status without the wc- prefix.
	 */
	return (string) apply_filters( 'gadgetim_order_status_tone', $tones[ $status ] ?? 'busy', $status );
}

/**
 * Status chip: coloured pill with a dot and WooCommerce's status name.
 */
function gadgetim_order_status_chip( WC_Order $order ): string {
	$status = (string) $order->get_status();
	return '<span class="ost ost--' . esc_attr( gadgetim_order_status_tone( $status ) ) . '"><i aria-hidden="true"></i>' . esc_html( wc_get_order_status_name( $status ) ) . '</span>';
}

/**
 * Timeline steps of an order: placed → payment → preparing → completed, each with a state
 * (done, current, next) and a date when known; a stopped order (cancelled, refunded, failed) gets
 * a final "stopped" step. Filter: gadgetim_order_timeline_steps.
 *
 * @return array<int,array{key:string,label:string,text:string,state:string,date:string,icon:string}>
 */
function gadgetim_order_timeline_steps( WC_Order $order ): array {
	$status  = (string) $order->get_status();
	$paid_at = $order->get_date_paid();
	$date    = static fn( $d ): string => $d instanceof WC_DateTime ? gadgetim_date( $d->getTimestamp(), 'medium' ) : '';
	$stopped = in_array( $status, [ 'cancelled', 'refunded', 'failed' ], true );
	$known   = in_array( $status, [ 'pending', 'on-hold', 'checkout-draft', 'processing', 'completed', 'cancelled', 'refunded', 'failed' ], true );
	$is_paid = $paid_at instanceof WC_DateTime || in_array( $status, [ 'processing', 'completed' ], true ) || ( ! $known && ! $order->needs_payment() );

	$paid_text = __( 'Waiting for payment.', 'gadgetim' );
	if ( $is_paid ) {
		$paid_text = __( 'Your payment is confirmed.', 'gadgetim' );
	} elseif ( 'on-hold' === $status ) {
		$paid_text = __( 'We are confirming your payment.', 'gadgetim' );
	}

	$steps = [
		[
			'key'   => 'placed',
			'label' => __( 'Order placed', 'gadgetim' ),
			'text'  => __( 'We have received your order.', 'gadgetim' ),
			'icon'  => 'orders',
			'date'  => $date( $order->get_date_created() ),
			'state' => 'done',
		],
		[
			'key'   => 'paid',
			'label' => __( 'Payment confirmed', 'gadgetim' ),
			'text'  => $paid_text,
			'icon'  => 'lock',
			'date'  => $date( $paid_at ),
			'state' => $is_paid ? 'done' : 'current',
		],
		[
			'key'   => 'preparing',
			'label' => __( 'Preparing your parcel', 'gadgetim' ),
			'text'  => __( 'Checked, sealed and packed with care.', 'gadgetim' ),
			'icon'  => 'box',
			'date'  => '',
			'state' => 'completed' === $status ? 'done' : ( $is_paid ? 'current' : 'next' ),
		],
		[
			'key'   => 'completed',
			'label' => __( 'Shipped and completed', 'gadgetim' ),
			'text'  => __( 'On its way to you, or already delivered.', 'gadgetim' ),
			'icon'  => 'truck',
			'date'  => $date( $order->get_date_completed() ),
			'state' => 'completed' === $status ? 'done' : 'next',
		],
	];

	if ( $stopped ) {
		$steps   = array_values( array_filter( $steps, static fn( array $s ): bool => 'done' === $s['state'] ) );
		$steps[] = [
			'key'   => 'stopped',
			'label' => wc_get_order_status_name( $status ),
			'text'  => 'failed' === $status ? __( 'The payment did not go through. You can try again.', 'gadgetim' ) : __( 'This order is closed. Contact us if you have a question.', 'gadgetim' ),
			'icon'  => 'alert',
			'date'  => $date( $order->get_date_modified() ),
			'state' => 'stopped',
		];
	}

	/**
	 * Filters the order timeline steps.
	 *
	 * @param array    $steps Steps (key, label, text, icon, date, state: done|current|next|stopped).
	 * @param WC_Order $order Order.
	 */
	return (array) apply_filters( 'gadgetim_order_timeline_steps', $steps, $order );
}

/**
 * The order timeline card.
 *
 * @param WC_Order $order Order.
 * @param array    $args  title (bool, default true), class.
 */
function gadgetim_order_timeline( WC_Order $order, array $args = [] ): string {
	return gadgetim_capture(
		'commerce/timeline',
		[
			'order' => $order,
			'steps' => gadgetim_order_timeline_steps( $order ),
			'title' => $args['title'] ?? true,
			'class' => (string) ( $args['class'] ?? '' ),
		]
	);
}

/* ---------------------------------------------------------------------------
 * Cart suggestions
 * ------------------------------------------------------------------------- */

/**
 * Product ids to suggest: the cart's cross-sells, then best sellers from the cart's categories,
 * then best sellers overall; in stock, visible, never already in the cart.
 *
 * @param int $limit Maximum ids.
 * @return int[]
 */
function gadgetim_commerce_suggestion_ids( int $limit = 10 ): array {
	$in_cart = [];
	$cats    = [];
	$ids     = [];
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $item ) {
			$in_cart[] = (int) ( $item['product_id'] ?? 0 );
			if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$parent = $item['data']->get_parent_id() ? wc_get_product( $item['data']->get_parent_id() ) : $item['data'];
				if ( $parent instanceof WC_Product ) {
					$cats = array_merge( $cats, $parent->get_category_ids() );
				}
			}
		}
		$ids = array_map( 'intval', (array) WC()->cart->get_cross_sells() );
	}
	if ( count( $ids ) < $limit && $cats ) {
		$ids = array_merge( $ids, gadgetim_category_product_ids( array_values( array_unique( array_map( 'intval', $cats ) ) ), $limit * 2, true ) );
	}
	if ( count( $ids ) < $limit ) {
		$query = new WP_Query(
			[
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $limit * 2,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'                => [
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				],
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => '_stock_status',
						'value' => 'instock',
					],
				],
			]
		);
		$ids   = array_merge( $ids, array_map( 'intval', (array) $query->posts ) );
	}
	$ids = array_values( array_diff( array_unique( $ids ), $in_cart, [ 0 ] ) );
	$out = [];
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( $product && $product->is_visible() && $product->is_in_stock() ) {
			$out[] = $id;
		}
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	/**
	 * Filters the products suggested in the cart and in the empty cart.
	 *
	 * @param int[] $out   Product ids.
	 * @param int   $limit Maximum.
	 */
	return array_map( 'intval', (array) apply_filters( 'gadgetim_cart_suggestion_ids', $out, $limit ) );
}

/**
 * woocommerce_after_cart 20: the suggestions rail.
 */
function gadgetim_cart_suggestions(): void {
	gadgetim_part(
		'commerce/suggestions',
		[
			'ids'   => gadgetim_commerce_suggestion_ids( 10 ),
			'title' => __( 'Goes well with your cart', 'gadgetim' ),
			'sub'   => __( 'Picked from the categories in your cart', 'gadgetim' ),
		]
	);
}

/* ---------------------------------------------------------------------------
 * Account
 * ------------------------------------------------------------------------- */

/**
 * woocommerce_account_menu_items: hides «Downloads» when the customer has none (the endpoint
 * itself keeps working).
 *
 * @param mixed $items Items.
 * @return array
 */
function gadgetim_account_menu_items( $items ): array {
	$items = is_array( $items ) ? $items : [];
	if ( isset( $items['downloads'] ) && function_exists( 'WC' ) && WC()->customer && ! WC()->customer->get_downloadable_products() ) {
		unset( $items['downloads'] );
	}
	return $items;
}

/**
 * Icon of an account menu endpoint.
 */
function gadgetim_account_icon( string $endpoint ): string {
	$icons = [
		'dashboard'       => 'grid',
		'orders'          => 'orders',
		'downloads'       => 'box',
		'edit-address'    => 'pin',
		'payment-methods' => 'lock',
		'edit-account'    => 'user',
		'customer-logout' => 'logout',
	];
	/**
	 * Filters the icon of an account menu item.
	 *
	 * @param string $icon     Sprite icon name.
	 * @param string $endpoint Endpoint.
	 */
	return (string) apply_filters( 'gadgetim_account_icon', $icons[ $endpoint ] ?? 'layers', $endpoint );
}

/**
 * The signed-in customer for the profile card: name, initials, contact line, member since.
 *
 * @return array{name:string,initials:string,contact:string,since:string}
 */
function gadgetim_account_profile(): array {
	$user  = wp_get_current_user();
	$first = (string) $user->first_name;
	$last  = (string) $user->last_name;
	$name  = trim( $first . ' ' . $last );
	if ( '' === $name ) {
		$name = (string) $user->display_name;
	}
	$initials = '';
	foreach ( array_filter( [ $first, $last ] ) ?: [ $name ] as $part ) {
		$initials .= mb_substr( trim( $part ), 0, 1 );
	}
	$phone   = (string) get_user_meta( $user->ID, 'billing_phone', true );
	$contact = '' !== $phone ? $phone : (string) $user->user_email;
	if ( '' !== $phone && class_exists( 'Gadgetim\Core\Support\Phone' ) ) {
		$contact = (string) Gadgetim\Core\Support\Phone::national( $phone, gadgetim_lang() );
	}
	$registered = strtotime( (string) $user->user_registered );
	return [
		'name'     => $name,
		'initials' => '' !== $initials ? mb_substr( $initials, 0, 2 ) : '?',
		'contact'  => $contact,
		'since'    => $registered ? gadgetim_date( (int) $registered, 'medium' ) : '',
	];
}

/* ---------------------------------------------------------------------------
 * Order tracking (order number + e-mail or mobile number)
 * ------------------------------------------------------------------------- */

/**
 * [woocommerce_order_tracking] replacement.
 *
 * @param mixed $atts Attributes.
 */
function gadgetim_order_tracking_shortcode( $atts = [] ): string {
	if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
		return '';
	}
	return WC_Shortcodes::shortcode_wrapper( 'gadgetim_order_tracking_output', is_array( $atts ) ? $atts : [] );
}

/**
 * Tracking output: the result when the order number and the contact match, else the form (with an
 * error notice after a failed attempt). Ten attempts per hour per visitor.
 *
 * @param array $atts Attributes.
 */
function gadgetim_order_tracking_output( array $atts = [] ): void {
	$atts = shortcode_atts( [], $atts, 'woocommerce_order_tracking' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- keeps the shortcode_atts filter.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
	$nonce = isset( $_REQUEST['woocommerce-order-tracking-nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['woocommerce-order-tracking-nonce'] ) ) : ( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '' );

	if ( isset( $_REQUEST['orderid'] ) && wp_verify_nonce( $nonce, 'woocommerce-order_tracking' ) ) {
		$raw_id  = sanitize_text_field( wp_unslash( (string) $_REQUEST['orderid'] ) );
		$raw_who = isset( $_REQUEST['order_email'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['order_email'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$order_id = ltrim( gadgetim_ascii_digits( trim( $raw_id ) ), '#' );
		$order_id = (string) preg_replace( '/[^0-9A-Za-z_-]/', '', $order_id );
		$who      = trim( gadgetim_ascii_digits( $raw_who ) );

		$limited = class_exists( 'Gadgetim\Core\Support\RateLimit' ) && ! Gadgetim\Core\Support\RateLimit::allow( 'track', 10, HOUR_IN_SECONDS );

		if ( $limited ) {
			wc_print_notice( __( 'Too many attempts. Please wait a few minutes and try again.', 'gadgetim' ), 'error' );
		} elseif ( '' === $order_id ) {
			wc_print_notice( __( 'Please enter a valid order ID', 'woocommerce' ), 'error' );
		} elseif ( '' === $who ) {
			wc_print_notice( __( 'Please enter the mobile number or email you used for the order.', 'gadgetim' ), 'error' );
		} else {
			/** This filter is documented in WooCommerce's WC_Shortcode_Order_Tracking. */
			$order = wc_get_order( apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $order_id ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			if ( $order instanceof WC_Order && $order->get_id() && gadgetim_tracking_contact_matches( $order, $who ) ) {
				do_action( 'woocommerce_track_order', $order->get_id() ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
				wc_get_template( 'order/tracking.php', [ 'order' => $order ] );
				return;
			}
			wc_print_notice( __( 'Sorry, the order could not be found. Please contact us if you are having difficulty finding your order details.', 'woocommerce' ), 'error' );
		}
	} elseif ( isset( $_REQUEST['orderid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
		// A stale form (cached page, expired nonce): say so instead of silently showing the form again.
		wc_print_notice( __( 'This form has expired. Please enter the details again and press Track.', 'gadgetim' ), 'notice' );
	}

	wc_get_template( 'order/form-tracking.php' );
}

/**
 * Whether the e-mail or mobile number entered matches the order (billing e-mail, billing or
 * shipping phone; phones compared in their normalised 09xxxxxxxxx form).
 */
function gadgetim_tracking_contact_matches( WC_Order $order, string $who ): bool {
	if ( str_contains( $who, '@' ) ) {
		$email = strtolower( (string) $order->get_billing_email() );
		return '' !== $email && hash_equals( $email, strtolower( sanitize_email( $who ) ) );
	}
	$norm  = static function ( string $raw ): string {
		if ( class_exists( 'Gadgetim\Core\Checkout\Fields' ) ) {
			return (string) Gadgetim\Core\Checkout\Fields::normalize_mobile( $raw );
		}
		$digits = (string) preg_replace( '/\D+/', '', gadgetim_ascii_digits( $raw ) );
		if ( str_starts_with( $digits, '0098' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( str_starts_with( $digits, '98' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		}
		return $digits;
	};
	$given = $norm( $who );
	if ( strlen( (string) preg_replace( '/\D+/', '', $given ) ) < 7 ) {
		return false;
	}
	foreach ( [ (string) $order->get_billing_phone(), (string) $order->get_shipping_phone() ] as $phone ) {
		if ( '' !== $phone && hash_equals( $norm( $phone ), $given ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Creates the «پیگیری سفارش» page with [woocommerce_order_tracking] when no published page holds
 * the shortcode (theme activation, and Gadgetim → Settings → Tools). Returns log lines.
 *
 * @return string[]
 */
function gadgetim_commerce_install(): array {
	gadgetim_flush_tracking_page();
	$id = gadgetim_order_tracking_page_id();
	if ( $id > 0 ) {
		return [ sprintf( 'Order tracking page #%d already exists: %s', $id, rawurldecode( (string) get_permalink( $id ) ) ) ];
	}
	$result = wp_insert_post(
		[
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'پیگیری سفارش',
			'post_name'      => 'پیگیری-سفارش',
			'post_content'   => '<!-- wp:shortcode -->[woocommerce_order_tracking]<!-- /wp:shortcode -->',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		],
		true
	);
	if ( is_wp_error( $result ) ) {
		return [ 'Could not create the order tracking page: ' . $result->get_error_message() ];
	}
	gadgetim_flush_tracking_page();
	if ( function_exists( 'gadgetim_flush_nav_cache' ) ) {
		gadgetim_flush_nav_cache();
	}
	return [ sprintf( 'Order tracking page #%d created: %s', (int) $result, rawurldecode( (string) get_permalink( (int) $result ) ) ) ];
}
add_action( 'after_switch_theme', 'gadgetim_commerce_install' );

/**
 * admin_init: "Order tracking page" in Gadgetim → Settings → Tools.
 */
function gadgetim_commerce_register_tool(): void {
	if ( ! class_exists( 'Gadgetim\Core\Settings\Admin\Tools' ) || ! method_exists( 'Gadgetim\Core\Settings\Admin\Tools', 'add' ) ) {
		return;
	}
	Gadgetim\Core\Settings\Admin\Tools::add(
		'gadgetim-tracking-page',
		__( 'Order tracking page', 'gadgetim' ),
		static fn(): string => implode( ' ', gadgetim_commerce_install() ),
		'manage_options',
		[
			'description' => __( 'Creates the «پیگیری سفارش» page with the order tracking form (order number + mobile number or email) when it is missing.', 'gadgetim' ),
			'button'      => __( 'Create the page', 'gadgetim' ),
			'order'       => 61,
		]
	);
}
add_action( 'admin_init', 'gadgetim_commerce_register_tool' );

/* ---------------------------------------------------------------------------
 * Extra icons (commerce screens only)
 * ------------------------------------------------------------------------- */

/**
 * wp_body_open 2: icons the shared sprite does not have (copy, card, edit, receipt, wallet, spark).
 */
function gadgetim_commerce_icons(): void {
	if ( '' === gadgetim_commerce_view() ) {
		return;
	}
	echo '<svg xmlns="http://www.w3.org/2000/svg" class="sprite" aria-hidden="true" focusable="false" width="0" height="0">'
		. '<symbol id="i-copy" viewBox="0 0 24 24"><rect x="8.4" y="8.4" width="11.6" height="11.6" rx="2.6"/><path d="M15.6 8.4V6.6A2.6 2.6 0 0 0 13 4H6.6A2.6 2.6 0 0 0 4 6.6V13a2.6 2.6 0 0 0 2.6 2.6h1.8"/></symbol>'
		. '<symbol id="i-card" viewBox="0 0 24 24"><rect x="3" y="5.4" width="18" height="13.2" rx="2.8"/><path d="M3 10h18M7 15h3.4"/></symbol>'
		. '<symbol id="i-edit" viewBox="0 0 24 24"><path d="M14.6 5.6 18.4 9.4M4.6 19.4l1-4.2L15.8 5a1.9 1.9 0 0 1 2.7 0l.5.5a1.9 1.9 0 0 1 0 2.7L8.8 18.4l-4.2 1Z"/></symbol>'
		. '<symbol id="i-receipt" viewBox="0 0 24 24"><path d="M6 3.6h12v17l-2.4-1.5-2.4 1.5-2.4-1.5-2.4 1.5L6 19.1Z"/><path d="M9 8.4h6M9 12h6M9 15.6h3.4"/></symbol>'
		. '<symbol id="i-wallet" viewBox="0 0 24 24"><path d="M4 7.6A2.6 2.6 0 0 1 6.6 5H17v3"/><rect x="4" y="8" width="16.4" height="11" rx="2.6"/><path d="M16.2 13.6h.01"/></symbol>'
		. '<symbol id="i-ticket" viewBox="0 0 24 24"><path d="M4 8.6V6.4A1.4 1.4 0 0 1 5.4 5h13.2A1.4 1.4 0 0 1 20 6.4v2.2a2.6 2.6 0 0 0 0 5v2.2a1.4 1.4 0 0 1-1.4 1.4H5.4A1.4 1.4 0 0 1 4 15.8v-2.2a2.6 2.6 0 0 0 0-5Z"/><path d="m9.4 14.6 5.2-5.2M9.6 9.6h.01M14.4 14.4h.01"/></symbol>'
		. '</svg>';
}
add_action( 'wp_body_open', 'gadgetim_commerce_icons', 2 );
