<?php
/**
 * Gregorian date formatting with built-in English/Arabic names, independent of the active locale.
 *
 * gadgetim_date() prefers wp_date() when the active locale already matches the requested
 * language (language packs win); this is the fallback for a mismatch, e.g. an English date
 * rendered during a Persian request, or a site without the I18n module.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Gregorian formatter (same token set as Jalali::format()).
 */
final class Gregorian {

	public const MONTHS_EN = [ 1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];

	public const MONTHS_AR = [ 1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' ];

	public const MONTHS_FA = [ 1 => 'ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر' ];

	/** Indexed like PHP's `w` (0 = Sunday). */
	public const WEEKDAYS_EN = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];

	public const WEEKDAYS_AR = [ 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت' ];

	public const WEEKDAYS_FA = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];

	/**
	 * Formats a time in the Gregorian calendar with the language's month/weekday names and digits.
	 *
	 * @param int|DateTimeInterface $time    Unix timestamp (site timezone) or date object.
	 * @param string                $pattern date()-like pattern: d j m n F M Y y l D N w t L H G g h i s A a U, backslash escapes.
	 * @param string                $lang    Language code (en, ar, fa).
	 */
	public static function format( int|DateTimeInterface $time, string $pattern, string $lang = 'en' ): string {
		$dt = $time instanceof DateTimeInterface
			? DateTimeImmutable::createFromInterface( $time )
			: ( new DateTimeImmutable( '@' . $time ) )->setTimezone( Jalali::timezone() );

		[ $months, $days ] = match ( $lang ) {
			'ar'    => [ self::MONTHS_AR, self::WEEKDAYS_AR ],
			'fa'    => [ self::MONTHS_FA, self::WEEKDAYS_FA ],
			default => [ self::MONTHS_EN, self::WEEKDAYS_EN ],
		};
		$month   = (int) $dt->format( 'n' );
		$weekday = (int) $dt->format( 'w' );
		$hour    = (int) $dt->format( 'G' );
		$meridiem = match ( $lang ) {
			'ar'    => $hour < 12 ? 'ص' : 'م',
			'fa'    => $hour < 12 ? 'ق.ظ' : 'ب.ظ',
			default => $hour < 12 ? 'AM' : 'PM',
		};

		$out = '';
		$len = strlen( $pattern );
		for ( $k = 0; $k < $len; $k++ ) {
			$c = $pattern[ $k ];
			if ( '\\' === $c && $k + 1 < $len ) {
				$out .= $pattern[ ++$k ];
				continue;
			}
			$out .= match ( $c ) {
				'F'      => $months[ $month ],
				'M'      => 'en' === $lang ? substr( $months[ $month ], 0, 3 ) : $months[ $month ],
				'l', 'D' => $days[ $weekday ],
				'A', 'a' => $meridiem,
				'd', 'j', 'm', 'n', 'Y', 'y', 'N', 'w', 't', 'L', 'H', 'G', 'g', 'h', 'i', 's', 'U' => $dt->format( $c ),
				default  => $c,
			};
		}

		return Digits::for_lang( $out, $lang, false );
	}
}
