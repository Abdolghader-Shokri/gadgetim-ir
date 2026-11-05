<?php
/**
 * Gregorian <-> Jalali (Solar Hijri) conversion and formatting. Pure PHP, no extension.
 *
 * Algorithm: Kazimierz M. Borkowski's break-year table (as used by jalaali-js),
 * accurate for Jalali years 1178–3177 (Gregorian 1799–3798).
 *
 * Never wired into wp_date()/date_i18n() (D9): the theme calls gadgetim_date().
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Jalali calendar helper.
 */
final class Jalali {

	private const BREAKS = [ -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178 ];

	public const MONTHS_FA = [ 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];

	public const MONTHS_EN = [ 1 => 'Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar', 'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand' ];

	/** Indexed like PHP's `w` (0 = Sunday). */
	public const WEEKDAYS_FA = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];

	public const WEEKDAYS_EN = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];

	/** Named styles -> format patterns (Jalali tokens, see format()). */
	public const STYLES = [
		'short'    => 'Y/m/d',
		'medium'   => 'j F Y',
		'long'     => 'l j F Y',
		'datetime' => 'j F Y، H:i',
		'time'     => 'H:i',
		'iso'      => 'Y-m-d',
	];

	/**
	 * Gregorian -> Jalali.
	 *
	 * @return array{0:int,1:int,2:int} [jy, jm, jd]
	 */
	public static function to_jalali( int $gy, int $gm, int $gd ): array {
		return self::d2j( self::g2d( $gy, $gm, $gd ) );
	}

	/**
	 * Jalali -> Gregorian.
	 *
	 * @return array{0:int,1:int,2:int} [gy, gm, gd]
	 */
	public static function to_gregorian( int $jy, int $jm, int $jd ): array {
		return self::d2g( self::j2d( $jy, $jm, $jd ) );
	}

	/**
	 * Whether a Jalali year is a leap year.
	 */
	public static function is_leap( int $jy ): bool {
		return 0 === self::jal_cal( $jy )['leap'];
	}

	/**
	 * Days in a Jalali month.
	 */
	public static function days_in_month( int $jy, int $jm ): int {
		if ( $jm <= 6 ) {
			return 31;
		}
		if ( $jm <= 11 ) {
			return 30;
		}
		return self::is_leap( $jy ) ? 30 : 29;
	}

	/**
	 * Whether a Jalali date is valid.
	 */
	public static function is_valid( int $jy, int $jm, int $jd ): bool {
		if ( $jy < -61 || $jy >= 3178 || $jm < 1 || $jm > 12 || $jd < 1 ) {
			return false;
		}
		return $jd <= self::days_in_month( $jy, $jm );
	}

	/**
	 * Breaks a timestamp into Jalali parts in the site's timezone.
	 *
	 * @param int|DateTimeInterface $time Unix timestamp or date object (its own timezone is kept).
	 * @return array{jy:int,jm:int,jd:int,gy:int,gm:int,gd:int,h:int,i:int,s:int,w:int,ts:int}
	 */
	public static function parts( int|DateTimeInterface $time ): array {
		$dt = self::to_datetime( $time );
		[ $gy, $gm, $gd, $h, $i, $s, $w ] = array_map( 'intval', explode( ' ', $dt->format( 'Y n j G i s w' ) ) );
		[ $jy, $jm, $jd ] = self::to_jalali( $gy, $gm, $gd );
		return compact( 'jy', 'jm', 'jd', 'gy', 'gm', 'gd', 'h', 'i', 's', 'w' ) + [ 'ts' => $dt->getTimestamp() ];
	}

	/**
	 * Builds a Unix timestamp from Jalali date/time parts in the site's timezone.
	 */
	public static function to_timestamp( int $jy, int $jm, int $jd, int $hour = 0, int $minute = 0, int $second = 0 ): int {
		[ $gy, $gm, $gd ] = self::to_gregorian( $jy, $jm, $jd );
		$dt               = new DateTimeImmutable( 'now', self::timezone() );
		return $dt->setDate( $gy, $gm, $gd )->setTime( $hour, $minute, $second )->getTimestamp();
	}

	/**
	 * Formats a time in the Jalali calendar.
	 *
	 * $style is a named style (short, medium, long, datetime, time, iso) or a pattern with
	 * PHP-date-like tokens: d j m n F M Y y l D N w t L H G i s A a U, backslash escapes a token.
	 * $lang picks month/weekday names (fa or en; ar uses the Persian names) and the digit set.
	 *
	 * @param int|DateTimeInterface $time  Unix timestamp or date object.
	 * @param string                $style Named style or pattern.
	 * @param string                $lang  Language code.
	 */
	public static function format( int|DateTimeInterface $time, string $style = 'medium', string $lang = 'fa' ): string {
		$pattern = self::STYLES[ $style ] ?? $style;
		$p       = self::parts( $time );
		$english = 'en' === $lang;
		$months  = $english ? self::MONTHS_EN : self::MONTHS_FA;
		$days    = $english ? self::WEEKDAYS_EN : self::WEEKDAYS_FA;

		$out = '';
		$len = strlen( $pattern );
		for ( $k = 0; $k < $len; $k++ ) {
			$c = $pattern[ $k ];
			if ( '\\' === $c && $k + 1 < $len ) {
				$out .= $pattern[ ++$k ];
				continue;
			}
			$out .= match ( $c ) {
				'd'     => sprintf( '%02d', $p['jd'] ),
				'j'     => (string) $p['jd'],
				'm'     => sprintf( '%02d', $p['jm'] ),
				'n'     => (string) $p['jm'],
				'F', 'M' => $months[ $p['jm'] ],
				'Y'     => (string) $p['jy'],
				'y'     => sprintf( '%02d', $p['jy'] % 100 ),
				'l', 'D' => $days[ $p['w'] ],
				'N'     => (string) ( 0 === $p['w'] ? 7 : $p['w'] ),
				'w'     => (string) $p['w'],
				't'     => (string) self::days_in_month( $p['jy'], $p['jm'] ),
				'L'     => self::is_leap( $p['jy'] ) ? '1' : '0',
				'H'     => sprintf( '%02d', $p['h'] ),
				'G'     => (string) $p['h'],
				'g'     => (string) ( $p['h'] % 12 ?: 12 ),
				'h'     => sprintf( '%02d', $p['h'] % 12 ?: 12 ),
				'i'     => sprintf( '%02d', $p['i'] ),
				's'     => sprintf( '%02d', $p['s'] ),
				'A', 'a' => $english ? ( $p['h'] < 12 ? 'AM' : 'PM' ) : ( $p['h'] < 12 ? 'ق.ظ' : 'ب.ظ' ),
				'U'     => (string) $p['ts'],
				default => $c,
			};
		}

		return Digits::for_lang( $out, $lang, false );
	}

	/**
	 * Persian (or transliterated) month name, 1-based.
	 */
	public static function month_name( int $jm, string $lang = 'fa' ): string {
		$months = 'en' === $lang ? self::MONTHS_EN : self::MONTHS_FA;
		return $months[ max( 1, min( 12, $jm ) ) ];
	}

	/**
	 * Today's Jalali date in the site's timezone.
	 *
	 * @return array{0:int,1:int,2:int} [jy, jm, jd]
	 */
	public static function today(): array {
		$p = self::parts( time() );
		return [ $p['jy'], $p['jm'], $p['jd'] ];
	}

	/**
	 * The site's timezone (wp_timezone() when WordPress is loaded).
	 */
	public static function timezone(): DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		return new DateTimeZone( date_default_timezone_get() ?: 'UTC' );
	}

	private static function to_datetime( int|DateTimeInterface $time ): DateTimeImmutable {
		if ( $time instanceof DateTimeInterface ) {
			return DateTimeImmutable::createFromInterface( $time );
		}
		return ( new DateTimeImmutable( '@' . $time ) )->setTimezone( self::timezone() );
	}

	/**
	 * Core of the algorithm: leap status, Gregorian year and the March day of Farvardin 1.
	 *
	 * @return array{leap:int,gy:int,march:int}
	 */
	private static function jal_cal( int $jy ): array {
		$count  = count( self::BREAKS );
		$gy     = $jy + 621;
		$leap_j = -14;
		$jp     = self::BREAKS[0];
		if ( $jy < $jp || $jy >= self::BREAKS[ $count - 1 ] ) {
			throw new InvalidArgumentException( 'Jalali year out of range: ' . $jy );
		}
		$jump = 0;
		for ( $i = 1; $i < $count; $i++ ) {
			$jm   = self::BREAKS[ $i ];
			$jump = $jm - $jp;
			if ( $jy < $jm ) {
				break;
			}
			$leap_j += intdiv( $jump, 33 ) * 8 + intdiv( $jump % 33, 4 );
			$jp      = $jm;
		}
		$n       = $jy - $jp;
		$leap_j += intdiv( $n, 33 ) * 8 + intdiv( ( $n % 33 ) + 3, 4 );
		if ( 4 === $jump % 33 && 4 === $jump - $n ) {
			$leap_j++;
		}
		$leap_g = intdiv( $gy, 4 ) - intdiv( ( intdiv( $gy, 100 ) + 1 ) * 3, 4 ) - 150;
		$march  = 20 + $leap_j - $leap_g;
		if ( $jump - $n < 6 ) {
			$n = $n - $jump + intdiv( $jump + 4, 33 ) * 33;
		}
		$leap = ( ( ( $n + 1 ) % 33 ) - 1 ) % 4;
		if ( -1 === $leap ) {
			$leap = 4;
		}
		return [ 'leap' => $leap, 'gy' => $gy, 'march' => $march ];
	}

	private static function j2d( int $jy, int $jm, int $jd ): int {
		$r = self::jal_cal( $jy );
		return self::g2d( $r['gy'], 3, $r['march'] ) + ( $jm - 1 ) * 31 - intdiv( $jm, 7 ) * ( $jm - 7 ) + $jd - 1;
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	private static function d2j( int $jdn ): array {
		$gy  = self::d2g( $jdn )[0];
		$jy  = $gy - 621;
		$r   = self::jal_cal( $jy );
		$k   = $jdn - self::g2d( $gy, 3, $r['march'] );
		if ( $k >= 0 ) {
			if ( $k <= 185 ) {
				return [ $jy, 1 + intdiv( $k, 31 ), ( $k % 31 ) + 1 ];
			}
			$k -= 186;
		} else {
			$jy--;
			$k += 179;
			if ( 1 === $r['leap'] ) {
				$k++;
			}
		}
		return [ $jy, 7 + intdiv( $k, 30 ), ( $k % 30 ) + 1 ];
	}

	private static function g2d( int $gy, int $gm, int $gd ): int {
		$d = intdiv( ( $gy + intdiv( $gm - 8, 6 ) + 100100 ) * 1461, 4 ) + intdiv( 153 * ( ( $gm + 9 ) % 12 ) + 2, 5 ) + $gd - 34840408;
		return $d - intdiv( intdiv( $gy + 100100 + intdiv( $gm - 8, 6 ), 100 ) * 3, 4 ) + 752;
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	private static function d2g( int $jdn ): array {
		$j  = 4 * $jdn + 139361631;
		$j += intdiv( intdiv( 4 * $jdn + 183187720, 146097 ) * 3, 4 ) * 4 - 3908;
		$i  = intdiv( $j % 1461, 4 ) * 5 + 308;
		$gd = intdiv( $i % 153, 5 ) + 1;
		$gm = ( intdiv( $i, 153 ) % 12 ) + 1;
		$gy = intdiv( $j, 1461 ) - 100100 + intdiv( 8 - $gm, 6 );
		return [ $gy, $gm, $gd ];
	}
}
