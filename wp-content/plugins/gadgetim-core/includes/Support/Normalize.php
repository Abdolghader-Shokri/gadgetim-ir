<?php
/**
 * Text and path normalisation (architecture §3.6 and §6 Redirects).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

use Normalizer;

/**
 * Normalisers used for the search blobs, slug lookups and redirect keys.
 * Both sides of every comparison must go through the same function.
 */
final class Normalize {

	/** Arabic letters -> Persian letters, plus teh marbuta -> heh. */
	private const LETTERS = [
		"\u{064A}" => "\u{06CC}", // ي -> ی
		"\u{0649}" => "\u{06CC}", // ى -> ی
		"\u{06CD}" => "\u{06CC}", // ۍ -> ی
		"\u{0643}" => "\u{06A9}", // ك -> ک
		"\u{06AA}" => "\u{06A9}", // ڪ -> ک
		"\u{0629}" => "\u{0647}", // ة -> ه
		"\u{06C0}" => "\u{0647}", // ۀ -> ه
		"\u{0624}" => "\u{0648}", // ؤ -> و
		"\u{0625}" => "\u{0627}", // إ -> ا
		"\u{0623}" => "\u{0627}", // أ -> ا
		"\u{0671}" => "\u{0627}", // ٱ -> ا
	];

	/** Tashkeel, superscript alef, tatweel and the rarer combining marks. */
	private const MARKS = '/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{06D6}-\x{06ED}]/u';

	/** ZWSP, ZWNJ, ZWJ, LRM, RLM, ALM, word joiner, BOM, soft hyphen. */
	private const ZERO_WIDTH = '/[\x{200B}-\x{200F}\x{061C}\x{2060}\x{FEFF}\x{00AD}]/u';

	/**
	 * Search-blob normaliser: NFC, Arabic -> Persian letters, strip marks and zero-width
	 * characters, all digit sets -> ASCII, lowercase, collapse whitespace.
	 */
	public static function text( string $text ): string {
		if ( '' === $text ) {
			return '';
		}
		$text = self::nfc( $text );
		$text = self::letters( $text );
		$text = self::strip_marks( $text );
		$text = Digits::to_latin( $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		return trim( $text );
	}

	/**
	 * Redirect-key normaliser: path only (query and fragment dropped), rawurldecoded,
	 * NFC, Arabic -> Persian letters, zero-width characters removed, digits -> ASCII,
	 * ASCII letters lowercased, duplicate slashes collapsed, leading slash, trailing
	 * slash added unless the last segment looks like a file (robots.txt, feed.xml).
	 *
	 * Accepts a full URL or a path. The install sub-directory is NOT stripped here;
	 * use Context::path_without_home() for the request side when needed.
	 */
	public static function path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '/';
		}
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			$parsed = wp_parse_url( $path );
			$path   = is_array( $parsed ) ? (string) ( $parsed['path'] ?? '/' ) : '/';
		}
		$path = preg_replace( '/[?#].*$/su', '', $path ) ?? $path;

		for ( $i = 0; $i < 3 && str_contains( $path, '%' ); $i++ ) {
			$decoded = rawurldecode( $path );
			if ( $decoded === $path ) {
				break;
			}
			$path = $decoded;
		}

		$path = self::nfc( $path );
		$path = self::letters( $path );
		$path = preg_replace( self::ZERO_WIDTH, '', $path ) ?? $path;
		$path = Digits::to_latin( $path );
		$path = preg_replace_callback( '/[A-Z]+/', static fn( array $m ): string => strtolower( $m[0] ), $path ) ?? $path;
		$path = preg_replace( '#/{2,}#', '/', $path ) ?? $path;
		$path = str_replace( [ "\r", "\n", "\t" ], '', $path );

		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		$last = substr( $path, (int) strrpos( $path, '/' ) + 1 );
		if ( '' !== $last && ! preg_match( '/\.[a-z0-9]{1,8}$/', $last ) ) {
			$path .= '/';
		}
		return $path;
	}

	/**
	 * Unicode NFC through the intl Normalizer when available; returns the input unchanged otherwise.
	 */
	public static function nfc( string $text ): string {
		if ( '' === $text || ! class_exists( Normalizer::class ) ) {
			return $text;
		}
		if ( Normalizer::isNormalized( $text, Normalizer::FORM_C ) ) {
			return $text;
		}
		$normalized = Normalizer::normalize( $text, Normalizer::FORM_C );
		return false === $normalized ? $text : $normalized;
	}

	/**
	 * Arabic ي/ك/ة (and a few more variants) -> Persian ی/ک/ه.
	 */
	public static function letters( string $text ): string {
		return '' === $text ? '' : strtr( $text, self::LETTERS );
	}

	/**
	 * Removes tashkeel, tatweel and zero-width characters (ZWNJ included).
	 */
	public static function strip_marks( string $text ): string {
		if ( '' === $text ) {
			return '';
		}
		$text = preg_replace( self::MARKS, '', $text ) ?? $text;
		return preg_replace( self::ZERO_WIDTH, '', $text ) ?? $text;
	}

	/**
	 * Persian text hygiene for display (not for matching): Arabic letters -> Persian,
	 * NFC, digits untouched, ZWNJ kept. Use before saving titles/descriptions.
	 */
	public static function persian( string $text ): string {
		if ( '' === $text ) {
			return '';
		}
		$text = self::nfc( $text );
		$text = self::letters( $text );
		return preg_replace( self::MARKS, '', $text ) ?? $text;
	}
}
