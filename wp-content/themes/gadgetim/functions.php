<?php
/**
 * Gadgetim — Aurora theme bootstrap.
 *
 * The theme is presentation only. Data, routing, pricing and settings live in the
 * gadgetim-core plugin; inc/compat.php supplies safe fallbacks for its helpers when the
 * plugin is missing, so a wrong upload order degrades instead of fataling.
 *
 * Foundation files (inc/*.php below) are loaded in a fixed order; page layers owned by the
 * page builders (inc/home.php, inc/archive.php, inc/product.php, inc/commerce.php,
 * inc/pages.php) are loaded afterwards when present.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/** Plugin API generation this theme was written against (gadgetim-core GADGETIM_CORE_API). */
define( 'GADGETIM_THEME_CORE_API', 1 );
define( 'GADGETIM_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'GADGETIM_THEME_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Theme version from the style.css header (asset cache-busting, config JSON).
 */
function gadgetim_theme_version(): string {
	static $version = null;
	if ( null === $version ) {
		$version = (string) wp_get_theme( get_template() )->get( 'Version' );
		if ( '' === $version ) {
			$version = '1.0.0';
		}
	}
	return $version;
}

// compat first: every later file may call the plugin helpers.
foreach ( [ 'compat', 'setup', 'settings', 'assets', 'images', 'template-tags', 'fx', 'menus', 'woocommerce', 'newsletter' ] as $gadgetim_inc ) {
	require GADGETIM_THEME_DIR . 'inc/' . $gadgetim_inc . '.php';
}

// Page layers (owned by the page builders): loaded only when the file exists.
foreach ( [ 'home', 'archive', 'product', 'commerce', 'pages' ] as $gadgetim_inc ) {
	$gadgetim_file = GADGETIM_THEME_DIR . 'inc/' . $gadgetim_inc . '.php';
	if ( is_readable( $gadgetim_file ) ) {
		require $gadgetim_file;
	}
}
unset( $gadgetim_inc, $gadgetim_file );
