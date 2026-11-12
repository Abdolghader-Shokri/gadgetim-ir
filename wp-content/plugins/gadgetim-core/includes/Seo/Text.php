<?php
/**
 * Small text helpers for SEO output.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

/**
 * Plain-text cleaning, length-aware shortening and URL encoding for tags and JSON-LD.
 */
final class Text {

	/**
	 * Placeholder text that must never reach a search result (theme demo leftovers).
	 */
	private const PLACEHOLDERS = [ 'لورم ایپسوم', 'لورم اپسوم', 'متن ساختگی', 'lorem ipsum' ];

	/**
	 * HTML or block markup to one line of plain text.
	 */
	public static function plain( string $html ): string {
		if ( '' === $html ) {
			return '';
		}
		$text = strip_shortcodes( $html );
		$text = (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $text );
		// Headings, list items and line breaks end a sentence; keep them apart once the tags are gone.
		$text = (string) preg_replace( '#</(h[1-6]|li|p|div|tr|td|th|blockquote)>|<br\s*/?>#i', "$0\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// A line that ends without punctuation (a heading, the first line of an excerpt) gets a full stop.
		$text = (string) preg_replace( '/([^\s.!?:;,\x{060C}\x{061B}\x{061F}])[ \t\x{00A0}]*\R+/u', "$1.\n", $text );
		$text = (string) preg_replace( '/[\s\x{00A0}\x{200F}\x{200E}]+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * Whether a text is (or contains) demo placeholder copy.
	 */
	public static function is_placeholder( string $text ): bool {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		foreach ( self::PLACEHOLDERS as $needle ) {
			if ( false !== mb_stripos( $lower, $needle, 0, 'UTF-8' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Shortens to at most $max characters: the last full sentence that fits when one ends past
	 * half the budget, else the last whole word plus an ellipsis.
	 */
	public static function shorten( string $text, int $max = 158 ): string {
		$text = trim( $text );
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $max, 'UTF-8' );
		// Greedy: everything up to the last sentence end that is followed by a space.
		if ( preg_match( '/^(.+[.!?\x{061F}])\s/us', $cut . ' ', $m ) && mb_strlen( $m[1], 'UTF-8' ) >= (int) ( $max / 2 ) ) {
			return trim( $m[1] );
		}
		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( false !== $space && $space > (int) ( $max / 2 ) ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}
		return rtrim( $cut, " \t,،;؛:-–—" ) . '…';
	}

	/**
	 * Percent-encodes every non-ASCII character and space of a URL (sitemaps and canonical
	 * values stay RFC 3986; WordPress builds some term links with a raw Persian base).
	 */
	public static function encode_url( string $url ): string {
		return (string) preg_replace_callback(
			'/[^\x21-\x7E]+/u',
			static fn( array $m ): string => rawurlencode( $m[0] ),
			$url
		);
	}
}
