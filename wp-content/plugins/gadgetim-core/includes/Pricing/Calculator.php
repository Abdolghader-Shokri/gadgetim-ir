<?php
/**
 * Base-currency to Toman conversion and rounding (architecture §4.2, decisions.md).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Support\Digits;

/**
 * Pure arithmetic, no WordPress state. The admin preview (Admin/assets/product-fields.js)
 * mirrors every rule here; keep the two in step.
 *
 * Rounding modes:
 *  - nearest | up | down: the raw Toman amount is rounded to the currency's rounding_step.
 *  - charm: nearest 10,000 Toman then minus 1,000 (2,340,000 → 2,339,000); from 10,000,000
 *    Toman the step is 100,000 (56,550,000 → 56,499,000). Exact midpoints round DOWN so a
 *    charm price never lands on the higher side of a tie; the rounding_step is ignored.
 */
final class Calculator {

	public const MODE_NEAREST = 'nearest';
	public const MODE_UP      = 'up';
	public const MODE_DOWN    = 'down';
	public const MODE_CHARM   = 'charm';

	public const MODES = [ self::MODE_NEAREST, self::MODE_UP, self::MODE_DOWN, self::MODE_CHARM ];

	public const CHARM_STEP       = 10000;
	public const CHARM_STEP_LARGE = 100000;
	public const CHARM_LARGE_FROM = 10000000;
	public const CHARM_OFFSET     = 1000;

	/**
	 * Toman price of a base-currency amount under a currency definition (0 when the
	 * amount or the rate is missing/invalid).
	 *
	 * @param string $base     Base amount as entered ('30', '14.16666', Persian digits allowed).
	 * @param array  $currency Currency definition (rate, rounding_mode, rounding_step).
	 */
	public static function to_toman( string $base, array $currency ): int {
		$amount = self::decimal( $base );
		if ( '' === $amount ) {
			return 0;
		}
		$rate = is_numeric( $currency['rate'] ?? null ) ? (float) $currency['rate'] : 0.0;
		if ( $rate <= 0 ) {
			return 0;
		}
		return self::round_toman(
			(float) $amount * $rate,
			(string) ( $currency['rounding_mode'] ?? self::MODE_NEAREST ),
			(int) ( $currency['rounding_step'] ?? 1000 )
		);
	}

	/**
	 * Applies a rounding mode to a raw Toman amount.
	 */
	public static function round_toman( float $raw, string $mode = self::MODE_NEAREST, int $step = 1000 ): int {
		if ( $raw <= 0 || ! is_finite( $raw ) ) {
			return 0;
		}
		if ( self::MODE_CHARM === $mode ) {
			return self::charm( $raw );
		}
		$step = max( 1, $step );
		// Snap away binary noise first: 2.2 × 85000 / 1000 is 187.00000000000003 in floats, which
		// ceil() would turn into 188. Six decimals of a step are far below any real price difference.
		$q = round( $raw / $step, 6 );
		$q = match ( $mode ) {
			self::MODE_UP   => ceil( $q ),
			self::MODE_DOWN => floor( $q ),
			default         => round( $q ),
		};
		return (int) round( $q * $step );
	}

	/**
	 * Charm rounding (see the class description).
	 */
	public static function charm( float $raw ): int {
		if ( $raw <= 0 || ! is_finite( $raw ) ) {
			return 0;
		}
		$step    = $raw >= self::CHARM_LARGE_FROM ? self::CHARM_STEP_LARGE : self::CHARM_STEP;
		$rounded = (int) round( round( $raw / $step, 0, PHP_ROUND_HALF_DOWN ) * $step );
		if ( $rounded <= 0 ) {
			// Below half a step there is nothing to charm; keep the plain amount.
			return (int) round( $raw );
		}
		$price = $rounded - self::CHARM_OFFSET;
		return $price > 0 ? $price : $rounded;
	}

	/**
	 * Normalises a user-entered decimal: Persian/Arabic digits, Arabic separators, thousands
	 * separators and spaces are accepted; negatives, letters and malformed numbers yield ''.
	 * The value is kept as entered ('14.16666' stays '14.16666').
	 */
	public static function decimal( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$raw = trim( Digits::to_latin( (string) $value ) );
		if ( '' === $raw ) {
			return '';
		}
		$raw = str_replace(
			[ "\u{066B}", "\u{066C}", "\u{060C}", ',', "\u{00A0}", "\u{202F}", "\u{200E}", "\u{200F}", ' ' ],
			[ '.', '', '', '', '', '', '', '', '' ],
			$raw
		);
		if ( str_starts_with( $raw, '-' ) || ! preg_match( '/^\d*\.?\d*$/', $raw ) || '' === trim( $raw, '.' ) ) {
			return '';
		}
		if ( '.' === $raw[0] ) {
			$raw = '0' . $raw;
		}
		$raw = rtrim( $raw, '.' );
		if ( ! is_numeric( $raw ) ) {
			return '';
		}
		if ( function_exists( 'wc_format_decimal' ) ) {
			$formatted = (string) wc_format_decimal( $raw );
			if ( '' !== $formatted && is_numeric( $formatted ) ) {
				$raw = $formatted;
			}
		}
		return $raw;
	}

	/**
	 * Whole percentage saved between an old and a new price (0 when not a discount).
	 */
	public static function percent_off( float $was, float $now ): int {
		if ( $was <= 0 || $now >= $was ) {
			return 0;
		}
		return (int) round( ( $was - $now ) / $was * 100 );
	}

	/**
	 * Whether a rounding mode is known.
	 */
	public static function is_mode( string $mode ): bool {
		return in_array( $mode, self::MODES, true );
	}

	/**
	 * Translated labels of the rounding modes (call after init).
	 *
	 * @return array<string,string>
	 */
	public static function mode_labels(): array {
		return [
			self::MODE_NEAREST => __( 'Nearest step', 'gadgetim-core' ),
			self::MODE_UP      => __( 'Round up to the step', 'gadgetim-core' ),
			self::MODE_DOWN    => __( 'Round down to the step', 'gadgetim-core' ),
			self::MODE_CHARM   => __( 'Charm (…9,000: nearest 10,000 minus 1,000)', 'gadgetim-core' ),
		];
	}
}
