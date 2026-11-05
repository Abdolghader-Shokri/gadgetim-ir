<?php
/**
 * Calculator: conversion, the four rounding modes (charm examples from the task) and decimal parsing.
 *
 * Run: "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/Pricing/CalculatorTest.php
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

use Gadgetim\Core\Pricing\Calculator;
use Gadgetim\Tests\Pricing\TestCase;

/**
 * Pure arithmetic; WordPress is only loaded for the autoloader and wc_format_decimal().
 */
final class CalculatorTest extends TestCase {

	private function currency( string $mode = Calculator::MODE_CHARM, int $step = 1000, float $rate = 78000 ): array {
		return [
			'code'          => 'AED',
			'rate'          => $rate,
			'rounding_mode' => $mode,
			'rounding_step' => $step,
			'decimals'      => 2,
			'enabled'       => true,
		];
	}

	public function test_charm_examples_from_the_specification(): void {
		$aed = $this->currency();
		$this->assertSame( 2339000, Calculator::to_toman( '30', $aed ), '30 × 78000 = 2,340,000 → 2,339,000' );
		$this->assertSame( 1099000, Calculator::to_toman( '14.16666', $aed ), '14.16666 × 78000 = 1,104,999.5 → 1,099,000' );
		$this->assertSame( 56499000, Calculator::to_toman( '725', $aed ), '725 × 78000 = 56,550,000 → 56,499,000' );
	}

	public function test_charm_midpoints_round_down(): void {
		$this->assertSame( 56499000, Calculator::charm( 56550000.0 ), 'exact midpoint above 10M' );
		$this->assertSame( 2339000, Calculator::charm( 2345000.0 ), 'exact midpoint below 10M' );
		$this->assertSame( 2349000, Calculator::charm( 2345000.01 ), 'just above the midpoint rounds up' );
	}

	public function test_charm_threshold_and_small_amounts(): void {
		$this->assertSame( 9999000, Calculator::charm( 9996000.0 ), 'below 10M: step 10,000' );
		$this->assertSame( 9999000, Calculator::charm( 10000000.0 ), 'at 10M: step 100,000' );
		$this->assertSame( 10099000, Calculator::charm( 10149000.0 ), 'above 10M: 10,149,000 → 10,100,000 → 10,099,000' );
		$this->assertSame( 9000, Calculator::charm( 6000.0 ), '6,000 → 10,000 → 9,000' );
		$this->assertSame( 4000, Calculator::charm( 4000.0 ), 'below half a step: plain amount' );
		$this->assertSame( 0, Calculator::charm( 0.0 ) );
	}

	public function test_nearest_up_and_down(): void {
		$amount = '14.16666'; // raw 1,104,999.48
		$this->assertSame( 1105000, Calculator::to_toman( $amount, $this->currency( Calculator::MODE_NEAREST ) ) );
		$this->assertSame( 1105000, Calculator::to_toman( $amount, $this->currency( Calculator::MODE_UP ) ) );
		$this->assertSame( 1104000, Calculator::to_toman( $amount, $this->currency( Calculator::MODE_DOWN ) ) );
		$this->assertSame( 1100000, Calculator::to_toman( $amount, $this->currency( Calculator::MODE_NEAREST, 10000 ) ) );
		$this->assertSame( 1110000, Calculator::to_toman( $amount, $this->currency( Calculator::MODE_UP, 10000 ) ) );
		$this->assertSame( 1100000, Calculator::to_toman( $amount, $this->currency( Calculator::MODE_DOWN, 10000 ) ) );
		$this->assertSame( 2340000, Calculator::to_toman( '30', $this->currency( Calculator::MODE_NEAREST, 10000 ) ), 'client decision: nearest 10,000' );
		$this->assertSame( 1104999, Calculator::round_toman( 1104999.48, Calculator::MODE_NEAREST, 1 ) );
		$this->assertSame( 1105000, Calculator::round_toman( 1104999.48, Calculator::MODE_UP, 1 ) );
	}

	public function test_exact_multiples_are_not_pushed_a_step_by_float_noise(): void {
		$this->assertSame( 187000, Calculator::to_toman( '2.2', $this->currency( Calculator::MODE_UP, 1000, 85000 ) ), '2.2 × 85000 = 187,000 exactly (up)' );
		$this->assertSame( 119000, Calculator::to_toman( '1.4', $this->currency( Calculator::MODE_DOWN, 1000, 85000 ) ), '1.4 × 85000 = 119,000 exactly (down)' );
		$this->assertSame( 391000, Calculator::to_toman( '4.6', $this->currency( Calculator::MODE_DOWN, 1000, 85000 ) ), '4.6 × 85000 = 391,000 exactly (down)' );
		$this->assertSame( 188000, Calculator::to_toman( '2.21', $this->currency( Calculator::MODE_UP, 1000, 85000 ) ), 'a real remainder still rounds up' );
		$this->assertSame( 118000, Calculator::to_toman( '1.39', $this->currency( Calculator::MODE_DOWN, 1000, 85000 ) ), 'a real remainder still rounds down' );

		// Brute force over two-decimal bases and common rates: exact multiples must stay exact.
		$bad = 0;
		foreach ( [ 78000, 85000, 61000, 99500 ] as $rate ) {
			for ( $cents = 1; $cents <= 5000; $cents++ ) {
				$base  = number_format( $cents / 100, 2, '.', '' );
				$exact = intdiv( $cents * (int) ( $rate * 100 ), 10000 ); // rate has at most 2 decimals here.
				if ( 0 !== ( $cents * (int) ( $rate * 100 ) ) % 10000 || 0 !== $exact % 1000 ) {
					continue;
				}
				foreach ( [ Calculator::MODE_UP, Calculator::MODE_DOWN ] as $mode ) {
					if ( Calculator::to_toman( $base, $this->currency( $mode, 1000, (float) $rate ) ) !== $exact ) {
						++$bad;
					}
				}
			}
		}
		$this->assertSame( 0, $bad, 'exact multiples of the step rounded away' );
	}

	public function test_unknown_mode_falls_back_to_nearest(): void {
		$this->assertSame( 1105000, Calculator::to_toman( '14.16666', $this->currency( 'bogus' ) ) );
		$this->assertFalse( Calculator::is_mode( 'bogus' ) );
		$this->assertTrue( Calculator::is_mode( 'charm' ) );
	}

	public function test_decimal_normalisation(): void {
		$this->assertSame( '30', Calculator::decimal( '30' ) );
		$this->assertSame( '30', Calculator::decimal( ' ۳۰ ' ), 'Persian digits' );
		$this->assertSame( '14.16666', Calculator::decimal( '۱۴٫۱۶۶۶۶' ), 'Persian digits with Arabic decimal separator' );
		$this->assertSame( '14.16666', Calculator::decimal( '14.16666' ), 'kept as entered' );
		$this->assertSame( '1250.5', Calculator::decimal( '1,250.5' ), 'thousands separator removed' );
		$this->assertSame( '1250', Calculator::decimal( '۱٬۲۵۰' ), 'Arabic thousands separator removed' );
		$this->assertSame( '0.5', Calculator::decimal( '.5' ) );
		$this->assertSame( '30', Calculator::decimal( '30.' ) );
		$this->assertSame( '30', Calculator::decimal( 30 ) );
		$this->assertSame( '30.5', Calculator::decimal( 30.5 ) );
		$this->assertSame( '', Calculator::decimal( '-5' ), 'negatives rejected' );
		$this->assertSame( '', Calculator::decimal( 'abc' ) );
		$this->assertSame( '', Calculator::decimal( '1.2.3' ) );
		$this->assertSame( '', Calculator::decimal( '1e5' ) );
		$this->assertSame( '', Calculator::decimal( '' ) );
		$this->assertSame( '', Calculator::decimal( null ) );
		$this->assertSame( '', Calculator::decimal( [ '30' ] ) );
	}

	public function test_invalid_inputs_yield_zero(): void {
		$aed = $this->currency();
		$this->assertSame( 0, Calculator::to_toman( '', $aed ) );
		$this->assertSame( 0, Calculator::to_toman( 'abc', $aed ) );
		$this->assertSame( 0, Calculator::to_toman( '-30', $aed ) );
		$this->assertSame( 0, Calculator::to_toman( '30', $this->currency( Calculator::MODE_CHARM, 1000, 0 ) ), 'rate 0' );
		$this->assertSame( 0, Calculator::to_toman( '30', [] ), 'no rate at all' );
		$this->assertSame( 0, Calculator::round_toman( -5.0 ) );
		$this->assertSame( 0, Calculator::round_toman( NAN ) );
	}

	public function test_percent_off(): void {
		$this->assertSame( 14, Calculator::percent_off( 2730000, 2340000 ), 'architecture example -14%' );
		$this->assertSame( 17, Calculator::percent_off( 2339000, 1949000 ) );
		$this->assertSame( 0, Calculator::percent_off( 100, 100 ) );
		$this->assertSame( 0, Calculator::percent_off( 100, 120 ) );
		$this->assertSame( 0, Calculator::percent_off( 0, 1 ) );
	}
}

exit( ( new CalculatorTest() )->run() );
