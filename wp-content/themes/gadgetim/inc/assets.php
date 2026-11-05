<?php
/**
 * Styles, script modules, fonts, the pre-paint script and the #gadgetim-config JSON.
 *
 * CSS cascade layers (declared first, in tokens.css, which is inlined in <head>):
 *   reset, tokens, base, layout, components, pages, fx, utilities
 * Always loaded, in this order: base.css, components.css, layout.css (layout.css also carries the site
 * chrome in the components layer, after the generic components). Page layers (each wraps its rules in
 * `@layer pages`) load only on their page kind: home.css, archive.css, product.css,
 * commerce.css (cart/checkout/account), pages.css (pages, posts, search, 404). fx.css (the delight
 * layer, `@layer fx` plus reveal variants in `utilities`) loads last on every page.
 *
 * JS ships as ES modules through the Script Modules API. app.js is the only enqueued module;
 * every other module is registered as a dynamic dependency of app.js (import map entry
 * "@gadgetim/<name>") and imported on demand. A module is registered only when its file exists.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * Cache-busting version for a theme asset: theme version + file mtime.
 */
function gadgetim_asset_version( string $relative ): string {
	$file = GADGETIM_THEME_DIR . ltrim( $relative, '/' );
	$time = is_file( $file ) ? (int) filemtime( $file ) : 0;
	return $time > 0 ? gadgetim_theme_version() . '.' . $time : gadgetim_theme_version();
}

/**
 * URL of a theme asset.
 */
function gadgetim_asset_url( string $relative ): string {
	return GADGETIM_THEME_URI . ltrim( $relative, '/' );
}

/**
 * Pages where WooCommerce's own (jQuery) scripts must stay: cart, checkout, account, and
 * variable/grouped product pages until the theme ships a variations module.
 */
function gadgetim_needs_wc_scripts(): bool {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	if ( is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url() ) {
		return true;
	}
	if ( is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product && $product->is_type( [ 'variable', 'grouped' ] ) ) {
			return (bool) apply_filters( 'gadgetim_keep_wc_variation_scripts', true, $product );
		}
	}
	return false;
}

/**
 * Page CSS layer for the current page kind ('' when none).
 */
function gadgetim_page_css(): string {
	$map = [
		'home'     => 'home',
		'archive'  => 'archive',
		'product'  => 'product',
		'cart'     => 'commerce',
		'checkout' => 'commerce',
		'account'  => 'commerce',
		'page'     => 'pages',
		'post'     => 'pages',
		'blog'     => 'pages',
		'search'   => 'pages',
		'404'      => 'pages',
	];
	return $map[ gadgetim_page_kind() ] ?? '';
}

/**
 * Whether a theme stylesheet exists and holds more than comments and an empty layer block
 * (the page layers start empty; they are not requested until a page builder fills them).
 */
function gadgetim_css_has_rules( string $relative ): bool {
	$file = GADGETIM_THEME_DIR . $relative;
	if ( ! is_readable( $file ) ) {
		return false;
	}
	$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-owned static file.
	$css = (string) preg_replace( [ '#/\*.*?\*/#s', '/\s+/' ], [ '', '' ], $css );
	return '' !== $css && '@layerpages{}' !== $css;
}

/**
 * JS modules the theme knows. 'filters' and 'gallery' belong to the archive / product builders.
 *
 * @return string[]
 */
function gadgetim_js_modules(): array {
	return (array) apply_filters( 'gadgetim_js_modules', [ 'motion', 'fx', 'carousel', 'drawer', 'toast', 'cart', 'search', 'countdown', 'filters', 'gallery' ] );
}

/**
 * wp_enqueue_scripts: theme styles and modules.
 */
function gadgetim_enqueue(): void {
	$prev = [];
	foreach ( [ 'base', 'components', 'layout' ] as $sheet ) {
		$path = 'assets/css/' . $sheet . '.css';
		wp_enqueue_style( 'gadgetim-' . $sheet, gadgetim_asset_url( $path ), $prev, gadgetim_asset_version( $path ) );
		$prev = [ 'gadgetim-' . $sheet ];
	}
	$page = gadgetim_page_css();
	if ( '' !== $page ) {
		$path = 'assets/css/' . $page . '.css';
		if ( gadgetim_css_has_rules( $path ) ) {
			wp_enqueue_style( 'gadgetim-' . $page, gadgetim_asset_url( $path ), $prev, gadgetim_asset_version( $path ) );
			$prev = [ 'gadgetim-' . $page ];
		}
	}
	// The delight layer (@layer fx, between pages and utilities): every page, last.
	if ( is_readable( GADGETIM_THEME_DIR . 'assets/css/fx.css' ) ) {
		wp_enqueue_style( 'gadgetim-fx', gadgetim_asset_url( 'assets/css/fx.css' ), $prev, gadgetim_asset_version( 'assets/css/fx.css' ) );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	if ( ! function_exists( 'wp_enqueue_script_module' ) ) {
		return;
	}
	$deps = [];
	foreach ( gadgetim_js_modules() as $module ) {
		$module = sanitize_key( (string) $module );
		$path   = 'assets/js/modules/' . $module . '.js';
		if ( '' === $module || ! is_readable( GADGETIM_THEME_DIR . $path ) ) {
			continue;
		}
		wp_register_script_module( '@gadgetim/' . $module, gadgetim_asset_url( $path ), [], gadgetim_asset_version( $path ) );
		$deps[] = [
			'id'     => '@gadgetim/' . $module,
			'import' => 'dynamic',
		];
	}
	wp_enqueue_script_module( '@gadgetim/app', gadgetim_asset_url( 'assets/js/app.js' ), $deps, gadgetim_asset_version( 'assets/js/app.js' ) );
}
add_action( 'wp_enqueue_scripts', 'gadgetim_enqueue', 20 );

add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * wp_enqueue_scripts (late): drop WooCommerce, block and jQuery assets the storefront does not use.
 */
function gadgetim_dequeue(): void {
	if ( is_admin() ) {
		return;
	}
	$styles = [ 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-inline', 'wc-blocks-style', 'wc-blocks-vendors-style', 'wc-all-blocks-style', 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'brands-styles', 'photoswipe', 'photoswipe-default-skin', 'select2' ];
	if ( ! is_user_logged_in() ) {
		$styles[] = 'dashicons';
	}
	foreach ( (array) apply_filters( 'gadgetim_dequeue_styles', $styles ) as $handle ) {
		wp_dequeue_style( $handle );
	}

	// WooCommerce order attribution (sourcebuster-js, wc-order-attribution) is switched off through its
	// own WooCommerce setting, never by dequeuing.
	$scripts = [ 'wc-cart-fragments', 'zoom', 'flexslider', 'photoswipe', 'photoswipe-ui-default', 'wp-embed' ];
	if ( ! gadgetim_needs_wc_scripts() ) {
		$scripts = array_merge( $scripts, [ 'woocommerce', 'wc-add-to-cart', 'wc-single-product', 'wc-add-to-cart-variation', 'selectWoo', 'select2', 'wc-country-select', 'wc-address-i18n', 'jquery-blockui', 'js-cookie', 'wc-password-strength-meter', 'jquery' ] );
	}
	foreach ( (array) apply_filters( 'gadgetim_dequeue_scripts', $scripts ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'gadgetim_dequeue', 100 );

/**
 * wp_head 0: pre-paint script. Flags html.js (reveal styles apply only then), html.lite on
 * Save-Data / reduced-data connections (heavy effects off) and html.fx-intro on the first page of a
 * browsing session or a reload (fx.css plays the header intro only then, never on every click).
 * Every element whose fx-* animation has ended gets .fx-shot, so a one-shot entrance never replays
 * when a breakpoint change re-renders it (fx.css keys those entrances on :not(.fx-shot)).
 */
function gadgetim_prepaint_script(): void {
	$js = '(function(h){h.classList.remove("no-js");h.classList.add("js");try{var n=navigator.connection;if((n&&n.saveData)||matchMedia("(prefers-reduced-data: reduce)").matches){h.classList.add("lite")}}catch(e){}try{var s=sessionStorage,t=performance.getEntriesByType("navigation")[0];if(!s.getItem("gtm_fx")||(t&&t.type==="reload")){h.classList.add("fx-intro");s.setItem("gtm_fx","1")}}catch(e){}document.addEventListener("animationend",function(e){var x=e.target;if(x&&x.classList&&/^fx-/.test(e.animationName)){x.classList.add("fx-shot")}},true)})(document.documentElement);';
	echo '<script id="gadgetim-prepaint">' . $js . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static theme script.
}
add_action( 'wp_head', 'gadgetim_prepaint_script', 0 );

/**
 * Font files (variable fonts, both preloaded: Vazirmatn for text, Estedad for display).
 *
 * @return array<string,array{family:string,file:string,weight:string}>
 */
function gadgetim_font_files(): array {
	return [
		'vazirmatn' => [
			'family' => 'Vazirmatn',
			'file'   => 'assets/fonts/Vazirmatn[wght].woff2',
			'weight' => '100 900',
		],
		'estedad'   => [
			'family' => 'Estedad',
			'file'   => 'assets/fonts/Estedad[wght].woff2',
			'weight' => '100 900',
		],
	];
}

/**
 * URL of a font file with the basename encoded (the variable-font names carry brackets).
 */
function gadgetim_font_url( string $relative ): string {
	$dir  = dirname( $relative );
	$name = rawurlencode( basename( $relative ) );
	return gadgetim_asset_url( $dir . '/' . $name ) . '?v=' . rawurlencode( gadgetim_asset_version( $relative ) );
}

/**
 * @font-face rules for the files present plus metric-adjusted local fallbacks (less layout shift on swap).
 */
function gadgetim_font_faces(): string {
	$css = '';
	foreach ( gadgetim_font_files() as $font ) {
		if ( ! is_file( GADGETIM_THEME_DIR . $font['file'] ) ) {
			continue;
		}
		$css .= '@font-face{font-family:"' . $font['family'] . '";src:url("' . esc_url( gadgetim_font_url( $font['file'] ) ) . '") format("woff2");font-weight:' . $font['weight'] . ';font-style:normal;font-display:swap}';
	}
	$css .= '@font-face{font-family:"Vazirmatn-fb";src:local("Tahoma"),local("Segoe UI"),local("Noto Naskh Arabic"),local("Roboto");size-adjust:96%;ascent-override:105%;descent-override:40%;line-gap-override:0%}';
	$css .= '@font-face{font-family:"Estedad-fb";src:local("Tahoma Bold"),local("Tahoma"),local("Segoe UI Bold"),local("Roboto");font-weight:700 900;size-adjust:97%;ascent-override:108%;descent-override:38%;line-gap-override:0%}';
	return $css;
}

/**
 * wp_head 1: the inlined tokens layer + font faces (render-critical, < 4 kB).
 */
function gadgetim_inline_tokens(): void {
	$file = GADGETIM_THEME_DIR . 'assets/css/tokens.css';
	$css  = is_file( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-owned static file.
	$css  = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$css  = (string) preg_replace( '/\s+/', ' ', $css );
	echo '<style id="gadgetim-tokens">' . trim( $css ) . gadgetim_font_faces() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-owned CSS.
}
add_action( 'wp_head', 'gadgetim_inline_tokens', 1 );

/**
 * wp_head 2: font preloads.
 */
function gadgetim_font_preloads(): void {
	foreach ( gadgetim_font_files() as $font ) {
		if ( is_file( GADGETIM_THEME_DIR . $font['file'] ) ) {
			echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="' . esc_url( gadgetim_font_url( $font['file'] ) ) . '">' . "\n";
		}
	}
}
add_action( 'wp_head', 'gadgetim_font_preloads', 2 );

/**
 * Digit set identifier for a language ('fa', 'arab', 'latn').
 */
function gadgetim_digit_set( ?string $lang = null ): string {
	$lang = $lang ?? gadgetim_lang();
	if ( class_exists( 'Gadgetim\Core\Support\Digits' ) && method_exists( 'Gadgetim\Core\Support\Digits', 'set_for_lang' ) ) {
		return (string) Gadgetim\Core\Support\Digits::set_for_lang( $lang );
	}
	return match ( $lang ) {
		'fa'    => 'fa',
		'ar'    => 'arab',
		default => 'latn',
	};
}

/**
 * BCP 47 locale for Intl.NumberFormat in JS, matching the language's digit set.
 */
function gadgetim_js_locale( ?string $lang = null ): string {
	$lang = $lang ?? gadgetim_lang();
	return match ( gadgetim_digit_set( $lang ) ) {
		'fa'    => 'fa-IR',
		'arab'  => 'ar-u-nu-arab',
		default => 'en-US',
	};
}

/**
 * Price unit label for a language.
 */
function gadgetim_price_unit( ?string $lang = null ): string {
	$lang = $lang ?? gadgetim_lang();
	if ( class_exists( 'Gadgetim\Core\Pricing\Display' ) && method_exists( 'Gadgetim\Core\Pricing\Display', 'unit' ) ) {
		return (string) Gadgetim\Core\Pricing\Display::unit( $lang );
	}
	$units = [
		'fa' => 'تومان',
		'ar' => 'تومان',
		'en' => 'Toman',
	];
	return (string) apply_filters( 'gadgetim_price_unit', $units[ $lang ] ?? $units['en'], $lang );
}

/**
 * The #gadgetim-config JSON consumed by app.js and its modules.
 */
function gadgetim_config(): array {
	$lang   = gadgetim_lang();
	$has_wc = function_exists( 'wc_get_cart_url' );
	$shop   = $has_wc ? (string) get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

	$config = [
		'version'     => gadgetim_theme_version(),
		'lang'        => $lang,
		'dir'         => gadgetim_lang_dir( $lang ),
		'locale'      => gadgetim_js_locale( $lang ),
		'digits'      => gadgetim_digit_set( $lang ),
		'page'        => gadgetim_page_kind(),
		'home'        => home_url( '/' ),
		'rest'        => esc_url_raw( rest_url( 'gadgetim/v1/' ) ),
		'restNonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'store'       => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
		'wcAjax'      => $has_wc && class_exists( 'WC_AJAX' ) ? esc_url_raw( WC_AJAX::get_endpoint( '%%endpoint%%' ) ) : '',
		'cart'        => $has_wc ? wc_get_cart_url() : '',
		'checkout'    => $has_wc ? wc_get_checkout_url() : '',
		'shop'        => $shop,
		'account'     => $has_wc ? (string) wc_get_page_permalink( 'myaccount' ) : '',
		'search'      => add_query_arg( 'post_type', 'product', home_url( '/' ) ),
		'contactMode' => gadgetim_contact_mode(),
		'cartCookie'  => 'gtm_cart_count',
		'wishKey'     => 'gtm_wish',
		'currency'    => [
			'unit'      => gadgetim_price_unit( $lang ),
			'code'      => 'IRT',
			'thousands' => 'en' === $lang ? ',' : "\u{066C}",
			'decimal'   => 'en' === $lang ? '.' : "\u{066B}",
			'percent'   => 'en' === $lang ? '%' : "\u{066A}",
		],
		'thresholds'  => gadgetim_sale_thresholds(),
		'strings'     => [
			'add'          => __( 'Add to cart', 'gadgetim' ),
			'adding'       => __( 'Adding…', 'gadgetim' ),
			'added'        => __( 'Added to cart', 'gadgetim' ),
			'viewCart'     => __( 'View cart', 'gadgetim' ),
			'checkout'     => __( 'Continue to checkout', 'gadgetim' ),
			'error'        => __( 'Something went wrong. Please try again.', 'gadgetim' ),
			'emptyCart'    => __( 'Your cart is empty', 'gadgetim' ),
			'emptyCartTip' => __( 'Add original gadgets with the + button.', 'gadgetim' ),
			'browse'       => __( 'Browse the shop', 'gadgetim' ),
			'subtotal'     => __( 'Cart total', 'gadgetim' ),
			/* translators: %s: amount saved, already formatted with the currency */
			'saved'        => __( 'You save %s on this order', 'gadgetim' ),
			/* translators: %s: number of items */
			'items'        => __( '%s items', 'gadgetim' ),
			/* translators: %s: number of items */
			'cartLabel'    => __( 'Cart, %s items', 'gadgetim' ),
			'cartEmpty'    => __( 'Cart, empty', 'gadgetim' ),
			/* translators: %s: number of items */
			'wishLabel'    => __( 'Wishlist, %s items', 'gadgetim' ),
			'wishEmpty'    => __( 'Your wishlist is empty', 'gadgetim' ),
			'wishEmptyTip' => __( 'Tap the heart on any product to keep it here. The list is saved on this device.', 'gadgetim' ),
			'wishAdded'    => __( 'Saved to your wishlist', 'gadgetim' ),
			'wishRemoved'  => __( 'Removed from your wishlist', 'gadgetim' ),
			'viewWish'     => __( 'View list', 'gadgetim' ),
			'remove'       => __( 'Remove', 'gadgetim' ),
			/* translators: %s: product name */
			'increase'     => __( 'Increase quantity of %s', 'gadgetim' ),
			/* translators: %s: product name */
			'decrease'     => __( 'Decrease quantity of %s', 'gadgetim' ),
			/* translators: %s: product name */
			'removeItem'   => __( 'Remove %s from the cart', 'gadgetim' ),
			'outOfStock'   => __( 'Out of stock', 'gadgetim' ),
			'searching'    => __( 'Searching…', 'gadgetim' ),
			'products'     => __( 'Products', 'gadgetim' ),
			'categories'   => __( 'Categories', 'gadgetim' ),
			'popular'      => __( 'Popular searches', 'gadgetim' ),
			/* translators: %s: search query */
			'seeAll'       => __( 'See all results for “%s”', 'gadgetim' ),
			/* translators: %s: search query */
			'noResults'    => __( 'Nothing found for “%s”', 'gadgetim' ),
			'noResultsTip' => __( 'Check the spelling or try one of these categories.', 'gadgetim' ),
			'rateLimited'  => __( 'Too many searches. Please wait a moment.', 'gadgetim' ),
			'close'        => __( 'Close', 'gadgetim' ),
			'pause'        => __( 'Pause autoplay', 'gadgetim' ),
			'play'         => __( 'Resume autoplay', 'gadgetim' ),
			/* translators: 1: slide number, 2: number of slides */
			'slideOf'      => __( 'Slide %1$s of %2$s', 'gadgetim' ),
			'hours'        => __( 'hours', 'gadgetim' ),
			'minutes'      => __( 'minutes', 'gadgetim' ),
			'seconds'      => __( 'seconds', 'gadgetim' ),
			'days'         => __( 'days', 'gadgetim' ),
			/* translators: 1: hours, 2: minutes */
			'timeLeft'     => __( '%1$s hours and %2$s minutes left', 'gadgetim' ),
			'ended'        => __( 'This offer has ended', 'gadgetim' ),
			'newsOk'       => __( 'Thank you! You are on the list.', 'gadgetim' ),
			'newsInvalid'  => __( 'Please enter a valid email address.', 'gadgetim' ),
		],
	];

	/**
	 * Filters the #gadgetim-config JSON printed in <head>.
	 *
	 * @param array $config Configuration.
	 */
	return (array) apply_filters( 'gadgetim_config', $config );
}

/**
 * wp_head 3: <script type="application/json" id="gadgetim-config">.
 */
function gadgetim_config_script(): void {
	$json = wp_json_encode( gadgetim_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo '<script type="application/json" id="gadgetim-config">' . ( false === $json ? '{}' : $json ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the HEX flags.
}
add_action( 'wp_head', 'gadgetim_config_script', 3 );

/**
 * Prints the icon sprite once (right after <body>), so <use href="#i-…"> resolves everywhere.
 */
function gadgetim_sprite(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	$file = GADGETIM_THEME_DIR . 'assets/icons/sprite.svg';
	if ( is_file( $file ) ) {
		echo (string) file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-owned static SVG.
	}
}
add_action( 'wp_body_open', 'gadgetim_sprite', 1 );
