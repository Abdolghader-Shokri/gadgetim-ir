<?php
/**
 * Runs the I18n test classes against the Local site.
 *
 *   "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/I18n/run.php [--filter=Name]
 *
 * Loads gadgetim-core manually when it is not active (hooks that fire on init are then not
 * exercised; the tests only need the filters added in Module::register()).
 * Output for the user is Persian; identifiers stay Latin. Exit code 1 on any failure.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

if ( ! function_exists( 'add_action' ) ) {
	fwrite( STDERR, "این فایل باید از طریق tools/wp-bootstrap.php اجرا شود.\n" );
	exit( 1 );
}

if ( ! defined( 'GADGETIM_CORE_VERSION' ) ) {
	$gtm_plugin = dirname( __DIR__, 2 ) . '/wp-content/plugins/gadgetim-core/gadgetim-core.php';
	if ( ! is_file( $gtm_plugin ) ) {
		fwrite( STDERR, "افزونهٴ gadgetim-core پیدا نشد: {$gtm_plugin}\n" );
		exit( 1 );
	}
	require_once $gtm_plugin;
	fwrite( STDERR, "توجه: افزونه فعال نیست؛ به‌صورت دستی بارگذاری شد (هوک‌های init اجرا نمی‌شوند).\n" );
}

if ( ! class_exists( 'WooCommerce', false ) ) {
	fwrite( STDERR, "ووکامرس فعال نیست؛ این تست‌ها به ووکامرس نیاز دارند.\n" );
	exit( 1 );
}

require_once __DIR__ . '/TestCase.php';

$gtm_filter = '';
foreach ( $_SERVER['argv'] ?? [] as $gtm_arg ) {
	if ( str_starts_with( (string) $gtm_arg, '--filter=' ) ) {
		$gtm_filter = substr( (string) $gtm_arg, 9 );
	}
}

$gtm_files = glob( __DIR__ . '/*Test.php' ) ?: [];
sort( $gtm_files );
$gtm_classes = [];
foreach ( $gtm_files as $gtm_file ) {
	$gtm_before = get_declared_classes();
	require_once $gtm_file;
	foreach ( array_diff( get_declared_classes(), $gtm_before ) as $gtm_class ) {
		if ( is_subclass_of( $gtm_class, 'Gadgetim\Tests\I18n\TestCase' ) && str_ends_with( $gtm_class, 'Test' ) ) {
			$gtm_classes[] = $gtm_class;
		}
	}
}

$gtm_passed   = 0;
$gtm_failed   = 0;
$gtm_errors   = 0;
$gtm_failures = [];

foreach ( $gtm_classes as $gtm_class ) {
	$gtm_short = substr( $gtm_class, (int) strrpos( $gtm_class, '\\' ) + 1 );
	if ( '' !== $gtm_filter && false === stripos( $gtm_short, $gtm_filter ) ) {
		continue;
	}
	echo "\n== {$gtm_short}\n";
	foreach ( get_class_methods( $gtm_class ) as $gtm_method ) {
		if ( ! str_starts_with( $gtm_method, 'test' ) ) {
			continue;
		}
		$gtm_test = new $gtm_class();
		try {
			$gtm_test->setUp();
			$gtm_test->{$gtm_method}();
			echo "  ✔ {$gtm_method}\n";
			$gtm_passed++;
		} catch ( Gadgetim\Tests\I18n\AssertionFailed $e ) {
			echo "  ✘ {$gtm_method}\n";
			$gtm_failed++;
			$gtm_failures[] = "{$gtm_short}::{$gtm_method}\n    " . str_replace( "\n", "\n    ", $e->getMessage() );
		} catch ( Throwable $e ) {
			echo "  ⚠ {$gtm_method} (خطا)\n";
			$gtm_errors++;
			$gtm_failures[] = "{$gtm_short}::{$gtm_method}\n    " . get_class( $e ) . ': ' . $e->getMessage() . "\n    " . $e->getFile() . ':' . $e->getLine();
		} finally {
			try {
				$gtm_test->tearDown();
			} catch ( Throwable $e ) {
				echo "  ⚠ tearDown: " . $e->getMessage() . "\n";
			}
		}
	}
}

echo "\n";
if ( $gtm_failures ) {
	echo "شکست‌ها:\n";
	foreach ( $gtm_failures as $gtm_failure ) {
		echo "  - {$gtm_failure}\n";
	}
	echo "\n";
}
printf( "نتیجه: %d موفق، %d ناموفق، %d خطا\n", $gtm_passed, $gtm_failed, $gtm_errors );
exit( ( $gtm_failed + $gtm_errors ) > 0 ? 1 : 0 );
