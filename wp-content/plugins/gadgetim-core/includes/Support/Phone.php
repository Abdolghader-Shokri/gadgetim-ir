<?php
/**
 * Phone number normalisation for Iranian numbers (architecture §4.6, §5.10).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

/**
 * Phone helpers. Input may carry Persian/Arabic digits, spaces, dashes, dots,
 * parentheses and bidi marks; output is ASCII.
 */
final class Phone {

	public const IRAN_CC = '98';

	/**
	 * ASCII digits with an optional leading '+'. "00" international prefix becomes '+'.
	 * Everything else (spaces, dashes, dots, parentheses, letters, bidi marks) is dropped.
	 */
	public static function normalize( string $raw ): string {
		$s = Digits::to_latin( trim( $raw ) );
		$s = str_replace( "\u{FF0B}", '+', $s ); // full-width plus
		$s = preg_replace( '/[\s\x{200B}-\x{200F}\x{061C}\x{2060}\x{FEFF}]+/u', '', $s ) ?? $s;
		if ( '' === $s ) {
			return '';
		}
		$plus = '+' === $s[0];
		$s    = preg_replace( '/\D+/', '', $s ) ?? '';
		if ( str_starts_with( $s, '00' ) ) {
			$s    = substr( $s, 2 );
			$plus = true;
		}
		if ( '' === $s ) {
			return '';
		}
		return ( $plus ? '+' : '' ) . $s;
	}

	/**
	 * Digits only (for wa.me links).
	 */
	public static function digits_only( string $raw ): string {
		return preg_replace( '/\D+/', '', self::normalize( $raw ) ) ?? '';
	}

	/**
	 * E.164 for Iranian numbers: 09171234567, 9171234567, 989171234567, 00989171234567,
	 * +989171234567 and landlines 02112345678 all become +98… . A number that already
	 * carries another country code is returned as is when it is a plausible E.164 number.
	 * Returns '' when the input cannot be understood.
	 */
	public static function to_e164_iran( string $raw ): string {
		$n = self::normalize( $raw );
		if ( '' === $n ) {
			return '';
		}
		if ( '+' === $n[0] ) {
			if ( preg_match( '/^\+' . self::IRAN_CC . '0?(\d{10})$/', $n, $m ) ) {
				return '+' . self::IRAN_CC . $m[1];
			}
			return preg_match( '/^\+[1-9]\d{6,14}$/', $n ) ? $n : '';
		}
		if ( preg_match( '/^0(\d{10})$/', $n, $m ) ) {          // 09xx xxx xxxx or 0xx landline
			return '+' . self::IRAN_CC . $m[1];
		}
		if ( preg_match( '/^' . self::IRAN_CC . '0?(\d{10})$/', $n, $m ) ) { // 98… without plus
			return '+' . self::IRAN_CC . $m[1];
		}
		if ( preg_match( '/^9\d{9}$/', $n ) ) {                 // 9xx xxx xxxx
			return '+' . self::IRAN_CC . $n;
		}
		return '';
	}

	/**
	 * Whether the number is an Iranian mobile (+989xxxxxxxxx).
	 */
	public static function is_iran_mobile( string $raw ): bool {
		return 1 === preg_match( '/^\+' . self::IRAN_CC . '9\d{9}$/', self::to_e164_iran( $raw ) );
	}

	/**
	 * Whether the number is Iranian (mobile or landline).
	 */
	public static function is_iran( string $raw ): bool {
		return str_starts_with( self::to_e164_iran( $raw ), '+' . self::IRAN_CC );
	}

	/**
	 * National display form for Iranian numbers ("0917 123 4567", "021 1234 5678") in the
	 * language's digit set; other numbers are returned in E.164 with Latin digits.
	 */
	public static function national( string $raw, string $lang = 'fa' ): string {
		$e164 = self::to_e164_iran( $raw );
		if ( '' === $e164 ) {
			return '';
		}
		if ( ! str_starts_with( $e164, '+' . self::IRAN_CC ) ) {
			return $e164;
		}
		$local = '0' . substr( $e164, 3 );
		if ( '09' === substr( $local, 0, 2 ) ) {
			$display = substr( $local, 0, 4 ) . ' ' . substr( $local, 4, 3 ) . ' ' . substr( $local, 7 );
		} else {
			$display = substr( $local, 0, 3 ) . ' ' . substr( $local, 3, 4 ) . ' ' . substr( $local, 7 );
		}
		return Digits::for_lang( $display, $lang, false );
	}

	/**
	 * tel: href (unescaped; pass through esc_url when printing).
	 */
	public static function tel_href( string $raw ): string {
		$e164 = self::to_e164_iran( $raw );
		if ( '' === $e164 ) {
			$e164 = self::normalize( $raw );
		}
		return '' === $e164 ? '' : 'tel:' . $e164;
	}

	/**
	 * WhatsApp deep link (unescaped). $text is pre-filled in the chat.
	 */
	public static function whatsapp_url( string $raw, string $text = '' ): string {
		$digits = self::digits_only( self::to_e164_iran( $raw ) ?: $raw );
		if ( '' === $digits ) {
			return '';
		}
		$url = 'https://wa.me/' . $digits;
		if ( '' !== $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}
		return $url;
	}

	/**
	 * Telegram link from a handle, "@handle" or a t.me URL (unescaped). '' when invalid.
	 */
	public static function telegram_url( string $handle ): string {
		$handle = self::handle( $handle );
		return '' === $handle ? '' : 'https://t.me/' . $handle;
	}

	/**
	 * Cleans a social handle: strips "@", a URL prefix and anything but [A-Za-z0-9_.].
	 */
	public static function handle( string $raw ): string {
		$raw = trim( Digits::to_latin( $raw ) );
		$raw = preg_replace( '#^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me|instagram\.com)/#i', '', $raw ) ?? $raw;
		$raw = ltrim( $raw, '@' );
		$raw = preg_replace( '/[?#].*$/', '', $raw ) ?? $raw;
		$raw = preg_replace( '/[^A-Za-z0-9_.]/', '', $raw ) ?? '';
		return trim( $raw, '.' );
	}
}
