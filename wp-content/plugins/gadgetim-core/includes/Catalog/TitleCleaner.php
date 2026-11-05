<?php
/**
 * Short-title derivation: strips the old site's boilerplate from product titles (architecture §5.7).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\Support\Normalize;

/**
 * «شارژر اصل و اورجینال 33وات شیائومی 2 پین با ضمانت اصالت کالا» → «شارژر 33وات شیائومی 2 پین».
 *
 * Pure string logic shared by ProductExtras (display fallback) and the importer (post_title).
 */
final class TitleCleaner {

	/**
	 * Boilerplate phrases, longest first. Whitespace inside a phrase matches any run of
	 * whitespace; the match is case-insensitive and Arabic letter variants are normalised first.
	 */
	public const DEFAULT_BOILERPLATE = [
		'اصل و اورجینال با ضمانت اصالت کالا',
		'اصل و ارجینال با ضمانت اصالت کالا',
		'با ضمانت اصالت کالا',
		'و ضمانت اصالت کالا',
		'ضمانت اصالت کالا',
		'اصل و اورجینال',
		'اصل و ارجینال',
		'اصلی و اورجینال',
	];

	/**
	 * Boilerplate phrases (filterable).
	 *
	 * @return string[]
	 */
	public static function boilerplate(): array {
		/**
		 * Filters the title boilerplate phrases stripped from product titles.
		 *
		 * @param string[] $phrases Phrases, longest first.
		 */
		$phrases = (array) apply_filters( 'gadgetim_title_boilerplate', self::DEFAULT_BOILERPLATE );
		$phrases = array_values( array_filter( array_map( 'strval', $phrases ), static fn( string $p ): bool => '' !== trim( $p ) ) );
		usort( $phrases, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );
		return $phrases;
	}

	/**
	 * The title without boilerplate, with whitespace collapsed and dangling connectors
	 * («و», «با», punctuation) trimmed. Returns the original title when nothing is left.
	 */
	public static function short( string $title ): string {
		$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
		if ( '' === $title ) {
			return '';
		}

		$work = Normalize::persian( $title );
		foreach ( self::patterns() as $pattern ) {
			$work = (string) preg_replace( $pattern, ' ', $work );
		}
		$work = trim( (string) preg_replace( '/\s+/u', ' ', $work ) );
		$work = self::trim_connectors( $work );

		if ( mb_strlen( $work ) < 3 ) {
			return $title;
		}
		return $work;
	}

	/**
	 * Whether the title contains any boilerplate phrase.
	 */
	public static function has_boilerplate( string $title ): bool {
		$work = Normalize::persian( $title );
		foreach ( self::patterns() as $pattern ) {
			if ( preg_match( $pattern, $work ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Regular expressions for the phrases.
	 *
	 * @return string[]
	 */
	private static function patterns(): array {
		$patterns = [];
		foreach ( self::boilerplate() as $phrase ) {
			$words      = preg_split( '/\s+/u', trim( Normalize::persian( $phrase ) ) ) ?: [];
			$quoted     = array_map( static fn( string $w ): string => preg_quote( $w, '/' ), $words );
			$patterns[] = '/\s*' . implode( '\s+', $quoted ) . '\s*/iu';
		}
		return $patterns;
	}

	/**
	 * Removes connector words and punctuation left dangling at either end.
	 */
	private static function trim_connectors( string $text ): string {
		$previous = null;
		while ( $previous !== $text ) {
			$previous = $text;
			$text     = (string) preg_replace( '/^(?:(?:و|با|در|برای)\s+|[،,\-–—|؛;:.]\s*)+/u', '', $text );
			$text     = (string) preg_replace( '/(?:\s+(?:و|با|در|برای)|\s*[،,\-–—|؛;:])+$/u', '', $text );
			$text     = trim( $text );
		}
		return $text;
	}
}
