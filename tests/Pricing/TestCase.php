<?php
/**
 * Minimal test runner for the plain-PHP tests (architecture 2.6: PHPUnit is not installed on
 * this machine yet; the tests run through tools/wp-bootstrap.php against the Local site).
 *
 * A test class extends TestCase, names its tests test_*, and ends its file with
 * `exit( ( new TheTest() )->run() );`. Output for the user is Persian, identifiers stay Latin.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

namespace Gadgetim\Tests\Pricing;

use RuntimeException;
use Throwable;

/**
 * Thrown by a failed assertion.
 */
final class AssertionFailed extends RuntimeException {}

/**
 * Base class: assertions, per-test setUp/tearDown, per-suite setUpSuite/tearDownSuite.
 */
abstract class TestCase {

	private int $assertions = 0;

	/** @var array<string,string> */
	private array $failures = [];

	/** @var string[] */
	private array $passed = [];

	/**
	 * Before each test.
	 */
	protected function setUp(): void {}

	/**
	 * After each test (also after a failure).
	 */
	protected function tearDown(): void {}

	/**
	 * Once before the first test.
	 */
	protected function setUpSuite(): void {}

	/**
	 * Once after the last test.
	 */
	protected function tearDownSuite(): void {}

	/**
	 * Runs every test_* method; an optional first script argument filters by substring.
	 *
	 * @return int Exit code (0 = all passed).
	 */
	public function run(): int {
		$filter  = isset( $_SERVER['argv'][1] ) ? (string) $_SERVER['argv'][1] : '';
		$methods = array_values( array_filter( get_class_methods( $this ), static fn( string $m ): bool => str_starts_with( $m, 'test' ) ) );
		if ( '' !== $filter ) {
			$methods = array_values( array_filter( $methods, static fn( string $m ): bool => str_contains( $m, $filter ) ) );
		}

		$this->out( '▶ ' . static::class );
		try {
			$this->setUpSuite();
		} catch ( Throwable $e ) {
			$this->out( '  ✘ setUpSuite: ' . $this->describe( $e ) );
			return 1;
		}

		foreach ( $methods as $method ) {
			$before = $this->assertions;
			try {
				$this->setUp();
				$this->$method();
				$this->passed[] = $method;
				$this->out( sprintf( '  ✔ %s (%d)', $method, $this->assertions - $before ) );
			} catch ( AssertionFailed $e ) {
				$this->failures[ $method ] = $e->getMessage();
				$this->out( sprintf( '  ✘ %s: %s', $method, $e->getMessage() ) );
			} catch ( Throwable $e ) {
				$this->failures[ $method ] = $this->describe( $e );
				$this->out( sprintf( '  ✘ %s: %s', $method, $this->failures[ $method ] ) );
			} finally {
				try {
					$this->tearDown();
				} catch ( Throwable $e ) {
					$this->out( '  ! tearDown: ' . $this->describe( $e ) );
				}
			}
		}

		try {
			$this->tearDownSuite();
		} catch ( Throwable $e ) {
			$this->out( '  ! tearDownSuite: ' . $this->describe( $e ) );
		}

		$this->out(
			sprintf(
				'نتیجه: %d آزمون، %d ادعا، %d شکست%s',
				count( $this->passed ) + count( $this->failures ),
				$this->assertions,
				count( $this->failures ),
				$this->failures ? '' : ' — همه گذشتند'
			)
		);
		return $this->failures ? 1 : 0;
	}

	protected function assertSame( mixed $expected, mixed $actual, string $message = '' ): void {
		++$this->assertions;
		if ( $expected !== $actual ) {
			$this->fail( $this->prefix( $message ) . sprintf( 'expected %s, got %s', $this->export( $expected ), $this->export( $actual ) ) );
		}
	}

	protected function assertEquals( float|int|string $expected, mixed $actual, string $message = '', float $delta = 0.000001 ): void {
		++$this->assertions;
		if ( ! is_numeric( $actual ) || abs( (float) $expected - (float) $actual ) > $delta ) {
			$this->fail( $this->prefix( $message ) . sprintf( 'expected ≈ %s, got %s', $this->export( $expected ), $this->export( $actual ) ) );
		}
	}

	protected function assertTrue( mixed $actual, string $message = '' ): void {
		++$this->assertions;
		if ( true !== $actual ) {
			$this->fail( $this->prefix( $message ) . 'expected true, got ' . $this->export( $actual ) );
		}
	}

	protected function assertFalse( mixed $actual, string $message = '' ): void {
		++$this->assertions;
		if ( false !== $actual ) {
			$this->fail( $this->prefix( $message ) . 'expected false, got ' . $this->export( $actual ) );
		}
	}

	protected function assertEmpty( mixed $actual, string $message = '' ): void {
		++$this->assertions;
		if ( ! empty( $actual ) ) {
			$this->fail( $this->prefix( $message ) . 'expected empty, got ' . $this->export( $actual ) );
		}
	}

	protected function assertNotEmpty( mixed $actual, string $message = '' ): void {
		++$this->assertions;
		if ( empty( $actual ) ) {
			$this->fail( $this->prefix( $message ) . 'expected a non-empty value' );
		}
	}

	protected function assertCount( int $expected, mixed $actual, string $message = '' ): void {
		++$this->assertions;
		$count = is_countable( $actual ) ? count( $actual ) : -1;
		if ( $expected !== $count ) {
			$this->fail( $this->prefix( $message ) . sprintf( 'expected %d items, got %d', $expected, $count ) );
		}
	}

	protected function assertContains( mixed $needle, array $haystack, string $message = '' ): void {
		++$this->assertions;
		if ( ! in_array( $needle, $haystack, true ) ) {
			$this->fail( $this->prefix( $message ) . sprintf( '%s not found in %s', $this->export( $needle ), $this->export( $haystack ) ) );
		}
	}

	protected function assertStringContains( string $needle, string $haystack, string $message = '' ): void {
		++$this->assertions;
		if ( ! str_contains( $haystack, $needle ) ) {
			$this->fail( $this->prefix( $message ) . sprintf( '"%s" not found in %s', $needle, $this->export( $haystack ) ) );
		}
	}

	protected function assertStringNotContains( string $needle, string $haystack, string $message = '' ): void {
		++$this->assertions;
		if ( str_contains( $haystack, $needle ) ) {
			$this->fail( $this->prefix( $message ) . sprintf( '"%s" unexpectedly found in %s', $needle, $this->export( $haystack ) ) );
		}
	}

	protected function assertGreaterThan( float|int $limit, mixed $actual, string $message = '' ): void {
		++$this->assertions;
		if ( ! is_numeric( $actual ) || $actual <= $limit ) {
			$this->fail( $this->prefix( $message ) . sprintf( 'expected > %s, got %s', $this->export( $limit ), $this->export( $actual ) ) );
		}
	}

	protected function fail( string $message ): never {
		throw new AssertionFailed( $message );
	}

	/**
	 * Writes a line to STDOUT.
	 */
	protected function out( string $line ): void {
		fwrite( STDOUT, $line . PHP_EOL );
	}

	private function prefix( string $message ): string {
		return '' === $message ? '' : $message . ' — ';
	}

	private function export( mixed $value ): string {
		$text = var_export( $value, true );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		return mb_strlen( $text ) > 240 ? mb_substr( $text, 0, 237 ) . '…' : $text;
	}

	private function describe( Throwable $e ): string {
		return get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
}
