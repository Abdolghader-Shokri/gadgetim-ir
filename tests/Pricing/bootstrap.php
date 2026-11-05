<?php
/**
 * Shared bootstrap of the Pricing tests. Include it after WordPress is loaded, i.e. run the
 * test file through tools/wp-bootstrap.php (Git Bash):
 *
 *   PHP="C:/Users/GHADER/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
 *   INI="C:/Users/GHADER/AppData/Roaming/Local/run/sqs5AIpIj/conf/php/php.ini"
 *   "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/Pricing/CalculatorTest.php
 *   "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/Pricing/MaterializerTest.php
 *
 * When gadgetim-core is linked but not activated on the Local site, the plugin file is loaded
 * here and the pricing module's init-time registrations are replayed, so the tests exercise the
 * real hooks without activating anything.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "این آزمون باید از طریق tools/wp-bootstrap.php اجرا شود (وردپرس بارگذاری نشده است).\n" );
	exit( 1 );
}

if ( ! defined( 'GADGETIM_CORE_VERSION' ) ) {
	$gadgetim_plugin_file = dirname( __DIR__, 2 ) . '/wp-content/plugins/gadgetim-core/gadgetim-core.php';
	if ( ! is_file( $gadgetim_plugin_file ) ) {
		fwrite( STDERR, "فایل افزونه پیدا نشد: {$gadgetim_plugin_file}\n" );
		exit( 1 );
	}
	require $gadgetim_plugin_file;
	if ( class_exists( 'Gadgetim\Core\Pricing\Module' ) ) {
		Gadgetim\Core\Pricing\Module::init();
	}
	define( 'GADGETIM_TESTS_LOADED_PLUGIN', true );
	unset( $gadgetim_plugin_file );
}

require_once __DIR__ . '/TestCase.php';
