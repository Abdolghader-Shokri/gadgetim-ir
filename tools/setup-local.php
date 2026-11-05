<?php
/**
 * Configures the Local development site for the Gadgetim packages. Idempotent; safe to re-run.
 *
 * Run through the bootstrap (Git Bash; $PHP = Local's php.exe, $INI = the rendered php.ini):
 *   "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tools/setup-local.php
 *
 * Phase 1 (default): activates gadgetim-core then the gadgetim theme, sets permalinks, the
 * WooCommerce cart/checkout shortcodes, features and the general site options, then re-runs
 * itself with --flush so the rewrite rules are rebuilt in a process that already loaded the
 * plugin, the theme and the new WooCommerce permalink bases.
 * Phase 2 (--flush): flushes the rewrite rules inside the Persian language context and prints
 * the resulting state.
 *
 * @package Gadgetim
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file through tools/wp-bootstrap.php.\n" );
	exit( 1 );
}

$gtm_setup_args  = array_slice( (array) ( $GLOBALS['argv'] ?? [] ), 1 );
$gtm_setup_flush = in_array( '--flush', $gtm_setup_args, true );

/**
 * Prints one line.
 */
function gtm_setup_say( string $line ): void {
	fwrite( STDOUT, $line . PHP_EOL );
}

/**
 * Sets an option and reports whether it changed.
 *
 * @param string $name  Option name.
 * @param mixed  $value New value.
 */
function gtm_setup_option( string $name, $value ): void {
	$old  = get_option( $name, null );
	$same = $old === $value;
	if ( ! $same && is_array( $old ) && is_array( $value ) ) {
		// Same entries in a different key order count as unchanged.
		ksort( $old );
		ksort( $value );
		$same = $old === $value;
	}
	if ( $same ) {
		gtm_setup_say( sprintf( '[=] %s', $name ) );
		return;
	}
	update_option( $name, $value );
	gtm_setup_say( sprintf( '[+] %s => %s', $name, is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}

/**
 * Prints the resulting state.
 */
function gtm_setup_report(): void {
	gtm_setup_say( '' );
	gtm_setup_say( 'Active plugins: ' . implode( ', ', (array) get_option( 'active_plugins', [] ) ) );
	$theme = wp_get_theme();
	gtm_setup_say( sprintf( 'Theme: %s (%s, stylesheet %s, template %s)', $theme->get( 'Name' ), $theme->get( 'Version' ), get_stylesheet(), get_template() ) );
	foreach ( [ 'blogname', 'blogdescription', 'timezone_string', 'gmt_offset', 'date_format', 'permalink_structure', 'WPLANG', 'woocommerce_coming_soon', 'woocommerce_feature_email_improvements_enabled', 'woocommerce_currency', 'gadgetim_core_version' ] as $name ) {
		$value = get_option( $name );
		gtm_setup_say( sprintf( '  %s = %s', $name, is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}
	gtm_setup_say( '  woocommerce_permalinks = ' . wp_json_encode( get_option( 'woocommerce_permalinks' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	foreach ( [ 'shop', 'cart', 'checkout', 'myaccount' ] as $page ) {
		$id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( $page ) : 0;
		gtm_setup_say( sprintf( '  page %s = #%d %s [%s]', $page, $id, $id > 0 ? get_permalink( $id ) : '-', $id > 0 ? trim( (string) get_post_field( 'post_content', $id ) ) : '' ) );
	}
	$rules = get_option( 'rewrite_rules' );
	gtm_setup_say( sprintf( '  rewrite_rules: %d rules', is_array( $rules ) ? count( $rules ) : 0 ) );
}

if ( $gtm_setup_flush ) {
	$flush = static function (): void {
		flush_rewrite_rules( false );
	};
	if ( class_exists( 'Gadgetim\Core\I18n\Languages' ) ) {
		\Gadgetim\Core\I18n\Languages::with_lang( 'fa', $flush );
	} else {
		$flush();
	}
	gtm_setup_say( '[+] rewrite rules flushed in the Persian context' );
	gtm_setup_report();
	return;
}

// 1. Plugins: WooCommerce must be active, then gadgetim-core.
foreach ( [ 'woocommerce/woocommerce.php', 'gadgetim-core/gadgetim-core.php' ] as $plugin ) {
	if ( is_plugin_active( $plugin ) ) {
		gtm_setup_say( sprintf( '[=] plugin %s active', $plugin ) );
		continue;
	}
	$result = activate_plugin( $plugin );
	if ( is_wp_error( $result ) ) {
		gtm_setup_say( sprintf( '!! plugin %s: %s', $plugin, $result->get_error_message() ) );
		exit( 1 );
	}
	gtm_setup_say( sprintf( '[+] plugin %s activated', $plugin ) );
}

// 2. Theme.
$gtm_theme = wp_get_theme( 'gadgetim' );
if ( ! $gtm_theme->exists() || $gtm_theme->errors() ) {
	gtm_setup_say( '!! theme gadgetim is missing or broken: ' . ( $gtm_theme->errors() ? $gtm_theme->errors()->get_error_message() : 'not found' ) );
	exit( 1 );
}
if ( 'gadgetim' === get_stylesheet() ) {
	gtm_setup_say( '[=] theme gadgetim active' );
} else {
	switch_theme( 'gadgetim' );
	gtm_setup_say( '[+] theme gadgetim activated' );
}

// 3. Permalinks.
gtm_setup_option( 'permalink_structure', '/%postname%/' );
$gtm_wc_permalinks = (array) get_option( 'woocommerce_permalinks', [] );
$gtm_wc_permalinks = array_merge(
	[
		'attribute_base'         => '',
		'use_verbose_page_rules' => false,
	],
	$gtm_wc_permalinks,
	[
		'product_base'  => '/product',
		'category_base' => 'product-category',
		'tag_base'      => 'product-tag',
	]
);
gtm_setup_option( 'woocommerce_permalinks', $gtm_wc_permalinks );

// 4. Cart and checkout pages: classic shortcodes instead of the blocks.
foreach ( [ 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ] as $gtm_page => $gtm_shortcode ) {
	$gtm_id = wc_get_page_id( $gtm_page );
	if ( $gtm_id <= 0 || ! get_post( $gtm_id ) ) {
		gtm_setup_say( sprintf( '!! WooCommerce %s page not found; run WooCommerce > Status > Tools > Create default pages', $gtm_page ) );
		continue;
	}
	$gtm_content = '<!-- wp:shortcode -->' . $gtm_shortcode . '<!-- /wp:shortcode -->';
	if ( trim( (string) get_post_field( 'post_content', $gtm_id, 'raw' ) ) === $gtm_content ) {
		gtm_setup_say( sprintf( '[=] %s page #%d', $gtm_page, $gtm_id ) );
		continue;
	}
	wp_update_post(
		[
			'ID'           => $gtm_id,
			'post_content' => $gtm_content,
		]
	);
	gtm_setup_say( sprintf( '[+] %s page #%d => %s', $gtm_page, $gtm_id, $gtm_shortcode ) );
}

// 5. WooCommerce features and store visibility.
if ( function_exists( 'wc_get_container' ) && class_exists( 'Automattic\WooCommerce\Internal\Features\FeaturesController' ) ) {
	$gtm_features = wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' );
	if ( $gtm_features->feature_is_enabled( 'email_improvements' ) ) {
		$gtm_features->change_feature_enable( 'email_improvements', false );
		gtm_setup_say( '[+] feature email_improvements disabled' );
	} else {
		gtm_setup_say( '[=] feature email_improvements disabled' );
	}
}
// WooCommerce 9+ puts new stores in "coming soon" mode, which hides the storefront from visitors.
gtm_setup_option( 'woocommerce_coming_soon', 'no' );

// 6. General options.
gtm_setup_option( 'blogname', 'گجتیم' );
gtm_setup_option( 'blogdescription', 'فروشگاه آنلاین انواع گجت‌های خاص، جذاب و کاربردی با کیفیت عالی و قیمت مناسب' );
if ( 'Asia/Tehran' !== get_option( 'timezone_string' ) ) {
	// A named zone replaces the manual offset (get_option() derives gmt_offset from the zone).
	gtm_setup_option( 'timezone_string', 'Asia/Tehran' );
	update_option( 'gmt_offset', '' );
} else {
	gtm_setup_say( '[=] timezone_string' );
}
gtm_setup_option( 'date_format', 'j F Y' );

// 7. Rebuild the rewrite rules in a fresh process (plugin, theme and permalink bases loaded).
$gtm_ini  = php_ini_loaded_file();
$gtm_cmd  = escapeshellarg( PHP_BINARY );
$gtm_cmd .= $gtm_ini ? ' -c ' . escapeshellarg( $gtm_ini ) : '';
$gtm_cmd .= ' -d display_startup_errors=0 ' . escapeshellarg( __DIR__ . '/wp-bootstrap.php' ) . ' ' . escapeshellarg( __FILE__ ) . ' --flush';
passthru( $gtm_cmd, $gtm_status );
exit( (int) $gtm_status );
