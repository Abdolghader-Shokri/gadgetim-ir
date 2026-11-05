<?php
/**
 * Theme supports, menus, image sizes, text domain, page kind, head clean-up, favicons.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * after_setup_theme: supports, menus, image sizes.
 */
function gadgetim_setup(): void {
	load_theme_textdomain( 'gadgetim', GADGETIM_THEME_DIR . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ] );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'custom-logo',
		[
			'height'               => 64,
			'width'                => 240,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => false,
		]
	);
	// WooCommerce without gallery zoom, lightbox or slider: the theme ships its own gallery module.
	add_theme_support(
		'woocommerce',
		[
			'thumbnail_image_width'         => 400,
			'single_image_width'            => 800,
			'gallery_thumbnail_image_width' => 160,
			'product_grid'                  => [
				'default_rows'    => 6,
				'min_rows'        => 2,
				'max_rows'        => 12,
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 5,
			],
		]
	);
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );
	remove_theme_support( 'wc-product-gallery-slider' );
	remove_theme_support( 'widgets-block-editor' );
	remove_theme_support( 'core-block-patterns' );

	// Menus: the plugin's I18n\Menus registers {primary,footer,legal}_{fa,en,ar} on after_setup_theme 20.
	// Without it the theme registers the same locations so assigned menus survive a plugin outage.
	if ( ! class_exists( 'Gadgetim\Core\I18n\Menus' ) ) {
		$locations = [];
		foreach ( gadgetim_menu_languages() as $code => $label ) {
			/* translators: %s: language name */
			$locations[ 'primary_' . $code ] = sprintf( __( 'Primary — %s', 'gadgetim' ), $label );
			/* translators: %s: language name */
			$locations[ 'footer_' . $code ] = sprintf( __( 'Footer — %s', 'gadgetim' ), $label );
			/* translators: %s: language name */
			$locations[ 'legal_' . $code ] = sprintf( __( 'Legal — %s', 'gadgetim' ), $label );
		}
		register_nav_menus( $locations );
	}

	// Image sizes: square plates, sources are 800×800 WebP. gtm-card is soft (no crop).
	add_image_size( 'gtm-thumb', 160, 160, true );
	add_image_size( 'gtm-card', 400, 400, false );
	add_image_size( 'gtm-hero', 800, 800, false );
}
add_action( 'after_setup_theme', 'gadgetim_setup' );

/**
 * Language codes with labels for the fallback menu locations.
 *
 * @return array<string,string>
 */
function gadgetim_menu_languages(): array {
	return [
		'fa' => __( 'Persian', 'gadgetim' ),
		'en' => __( 'English', 'gadgetim' ),
		'ar' => __( 'Arabic', 'gadgetim' ),
	];
}

/**
 * Drops the core sizes the storefront never requests.
 *
 * @param array $sizes Intermediate sizes.
 * @return array
 */
function gadgetim_drop_image_sizes( array $sizes ): array {
	unset( $sizes['medium_large'], $sizes['1536x1536'], $sizes['2048x2048'] );
	return $sizes;
}
add_filter( 'intermediate_image_sizes_advanced', 'gadgetim_drop_image_sizes' );
add_filter( 'big_image_size_threshold', '__return_false' );

/**
 * Content width for embeds (prose measure).
 */
function gadgetim_content_width(): void {
	$GLOBALS['content_width'] = 760; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}
add_action( 'after_setup_theme', 'gadgetim_content_width', 0 );

/**
 * Kind of page: drives <body data-page>, the CSS page layer and the JS page modules.
 *
 * Values: home, product, archive, cart, checkout, account, page, post, blog, search, 404.
 */
function gadgetim_page_kind(): string {
	static $kind = null;
	if ( null !== $kind && did_action( 'wp' ) ) {
		return $kind;
	}
	$detected = 'page';
	if ( is_front_page() ) {
		$detected = 'home';
	} elseif ( function_exists( 'is_woocommerce' ) && is_product() ) {
		$detected = 'product';
	} elseif ( function_exists( 'is_woocommerce' ) && is_cart() ) {
		$detected = 'cart';
	} elseif ( function_exists( 'is_woocommerce' ) && is_checkout() ) {
		$detected = 'checkout';
	} elseif ( function_exists( 'is_woocommerce' ) && is_account_page() ) {
		$detected = 'account';
	} elseif ( function_exists( 'is_woocommerce' ) && ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) {
		$detected = 'archive';
	} elseif ( is_404() ) {
		$detected = '404';
	} elseif ( is_search() ) {
		$detected = 'search';
	} elseif ( is_singular( 'post' ) ) {
		$detected = 'post';
	} elseif ( is_home() || is_archive() ) {
		$detected = 'blog';
	}
	/**
	 * Filters the page kind (data-page, CSS layer, JS page modules).
	 *
	 * @param string $detected Page kind.
	 */
	$detected = (string) apply_filters( 'gadgetim_page_kind', $detected );
	if ( did_action( 'wp' ) ) {
		$kind = $detected;
	}
	return $detected;
}

/**
 * Body classes: language, direction, page kind, contact mode.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function gadgetim_body_class( array $classes ): array {
	$classes[] = 'lang-' . sanitize_html_class( gadgetim_lang() );
	$classes[] = 'is-' . sanitize_html_class( gadgetim_lang_dir() );
	$classes[] = 'page-' . sanitize_html_class( gadgetim_page_kind() );
	if ( function_exists( 'gadgetim_contact_mode' ) && gadgetim_contact_mode() ) {
		$classes[] = 'is-contact-mode';
	}
	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'gadgetim_body_class' );

/**
 * Removes the emoji scripts and other head noise the storefront does not need.
 */
function gadgetim_clean_head(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	add_filter( 'emoji_svg_url', '__return_false' );
	// Classic theme: no block-library CSS, no global styles, no SVG duotone filters.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
}
add_action( 'init', 'gadgetim_clean_head' );

/**
 * Excerpt length for cards and fallbacks.
 */
function gadgetim_excerpt_length(): int {
	return 28;
}
add_filter( 'excerpt_length', 'gadgetim_excerpt_length' );

/**
 * Ellipsis after excerpts.
 */
function gadgetim_excerpt_more(): string {
	return '…';
}
add_filter( 'excerpt_more', 'gadgetim_excerpt_more' );

/**
 * wp_head 1: theme colour, colour scheme and the favicon set (unless a Site Icon is set in the Customizer).
 */
function gadgetim_head_meta(): void {
	echo '<meta name="color-scheme" content="light only">' . "\n";
	echo '<meta name="theme-color" content="#ffffff">' . "\n";
	if ( has_site_icon() ) {
		return;
	}
	$img = GADGETIM_THEME_URI . 'assets/img/';
	echo '<link rel="icon" href="' . esc_url( $img . 'favicon.svg' ) . '" type="image/svg+xml">' . "\n";
	echo '<link rel="icon" href="' . esc_url( $img . 'favicon-32.png' ) . '" sizes="32x32" type="image/png">' . "\n";
	echo '<link rel="icon" href="' . esc_url( $img . 'favicon-16.png' ) . '" sizes="16x16" type="image/png">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $img . 'apple-touch-icon.png' ) . '">' . "\n";
	echo '<link rel="manifest" href="' . esc_url( $img . 'site.webmanifest' ) . '">' . "\n";
}
add_action( 'wp_head', 'gadgetim_head_meta', 1 );
