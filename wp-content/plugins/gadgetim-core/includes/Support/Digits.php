<?php
/**
 * Digit-set conversion and number formatting. Pure helpers; no global filters (D9).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

/**
 * Converts between ASCII, Persian (U+06F0–U+06F9) and Arabic-Indic (U+0660–U+0669) digits.
 *
 * The display conversions (to_persian/to_arabic) never touch digits inside a token
 * that contains Latin letters (USB-C, 65W, GKM-70), a bare number standing next to
 * such a token (iPhone 16 Pro), URLs, e-mail addresses or HTML entities. Converting
 * *to* ASCII (to_latin) is lossless and is what normalisers and parsers need, so it
 * converts every digit.
 */
final class Digits {

	public const LATIN = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];

	public const PERSIAN = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];

	public const ARABIC = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];

	/** Digit set identifiers, as stored in gadgetim_languages[lang]['digits']. */
	public const SET_LATIN   = 'latn';
	public const SET_PERSIAN = 'fa';
	public const SET_ARABIC  = 'arab';

	/** Thousands separator per language (fa/ar: U+066C ARABIC THOUSANDS SEPARATOR). */
	public const THOUSANDS = [
		'fa' => "\u{066C}",
		'ar' => "\u{066C}",
		'en' => ',',
	];

	/** Decimal separator per language (fa/ar: U+066B ARABIC DECIMAL SEPARATOR). */
	public const DECIMAL = [
		'fa' => "\u{066B}",
		'ar' => "\u{066B}",
		'en' => '.',
	];

	private const LRM = "\u{200E}";

	/**
	 * Converts digits to Persian.
	 *
	 * @param string $text    Text or plain string.
	 * @param bool   $protect Keep tokens with Latin letters, URLs, e-mails and entities untouched.
	 */
	public static function to_persian( string $text, bool $protect = true ): string {
		return self::to_set( $text, self::SET_PERSIAN, $protect );
	}

	/**
	 * Converts digits to Arabic-Indic.
	 *
	 * @param string $text    Text or plain string.
	 * @param bool   $protect Keep tokens with Latin letters, URLs, e-mails and entities untouched.
	 */
	public static function to_arabic( string $text, bool $protect = true ): string {
		return self::to_set( $text, self::SET_ARABIC, $protect );
	}

	/**
	 * Converts every Persian and Arabic-Indic digit to ASCII (no protection: ASCII is safe everywhere).
	 *
	 * @param string $text Text or plain string.
	 */
	public static function to_latin( string $text ): string {
		if ( '' === $text ) {
			return '';
		}
		return str_replace( array_merge( self::PERSIAN, self::ARABIC ), array_merge( self::LATIN, self::LATIN ), $text );
	}

	/**
	 * Converts digits to the given set ('fa', 'arab' or 'latn').
	 *
	 * @param string $text    Text or plain string.
	 * @param string $set     Target digit set.
	 * @param bool   $protect Keep tokens with Latin letters, URLs, e-mails and entities untouched.
	 */
	public static function to_set( string $text, string $set, bool $protect = true ): string {
		$set = self::normalize_set( $set );
		if ( '' === $text ) {
			return '';
		}
		if ( self::SET_LATIN === $set ) {
			return self::to_latin( $text );
		}
		if ( ! $protect ) {
			return self::replace_all( $text, $set );
		}

		// Whitespace-delimited tokens; delimiters are preserved (odd indexes are whitespace).
		$parts = preg_split( '/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $text;
		}
		$count = count( $parts );
		for ( $i = 0; $i < $count; $i += 2 ) {
			$token = $parts[ $i ];
			// Entities are not words: '2,340,000&nbsp;' (WooCommerce's right_space price) must not
			// count as Latin because of 'nbsp'.
			if ( '' === $token || self::is_protected( self::strip_entities( $token ) ) ) {
				continue;
			}
			// A bare number next to a Latin word is part of a model name (iPhone 16 Pro, Galaxy S24 Ultra 512);
			// a number next to a code, URL or e-mail (65W 2,340,000) is still a number.
			$prev = $i >= 2 ? $parts[ $i - 2 ] : '';
			$next = $i + 2 < $count ? $parts[ $i + 2 ] : '';
			if ( self::is_latin_word( $prev ) || self::is_latin_word( $next ) ) {
				continue;
			}
			$parts[ $i ] = self::replace_entities_aware( $token, $set );
		}
		return implode( '', $parts );
	}

	/**
	 * Converts digits to the digit set configured for a language.
	 *
	 * @param string $text    Text or plain string.
	 * @param string $lang    Language code (fa, en, ar).
	 * @param bool   $protect Keep tokens with Latin letters, URLs, e-mails and entities untouched.
	 */
	public static function for_lang( string $text, string $lang, bool $protect = true ): string {
		return self::to_set( $text, self::set_for_lang( $lang ), $protect );
	}

	/**
	 * Digit set for a language: gadgetim_languages[lang]['digits'] when set, else fa→fa, ar→arab, *→latn.
	 *
	 * @param string $lang Language code.
	 */
	public static function set_for_lang( string $lang ): string {
		$lang = strtolower( trim( $lang ) );
		$set  = '';
		if ( class_exists( Options::class ) ) {
			$language = Options::language( $lang );
			if ( is_array( $language ) && ! empty( $language['digits'] ) ) {
				$set = (string) $language['digits'];
			}
		}
		if ( '' === $set ) {
			$set = match ( $lang ) {
				'fa'    => self::SET_PERSIAN,
				'ar'    => self::SET_ARABIC,
				default => self::SET_LATIN,
			};
		}
		$set = self::normalize_set( $set );

		/**
		 * Filters the digit set used for a language ('fa', 'arab' or 'latn').
		 *
		 * @param string $set  Digit set.
		 * @param string $lang Language code.
		 */
		return self::normalize_set( (string) apply_filters( 'gadgetim_digit_set', $set, $lang ) );
	}

	/**
	 * Formats a number with the language's separators and digit set.
	 *
	 * fa: ۲٬۳۴۰٬۰۰۰ · ar: ٢٬٣٤٠٬٠٠٠ · en: 2,340,000. Negative numbers in RTL languages get a
	 * leading LRM so the minus sign renders on the correct side.
	 *
	 * @param int|float|string $number   Number (strings may contain Persian/Arabic digits).
	 * @param string           $lang     Language code.
	 * @param int              $decimals Decimal places.
	 */
	public static function format_number( int|float|string $number, string $lang = 'fa', int $decimals = 0 ): string {
		if ( is_string( $number ) ) {
			$number = str_replace( [ "\u{066C}", "\u{066B}", ',', ' ' ], [ '', '.', '', '' ], self::to_latin( trim( $number ) ) );
			$number = is_numeric( $number ) ? (float) $number : 0.0;
		}
		$lang      = strtolower( trim( $lang ) );
		$decimals  = max( 0, $decimals );
		$negative  = $number < 0;
		$thousands = self::THOUSANDS[ $lang ] ?? self::THOUSANDS['en'];
		$decimal   = self::DECIMAL[ $lang ] ?? self::DECIMAL['en'];

		$formatted = number_format( abs( (float) $number ), $decimals, $decimal, $thousands );
		$formatted = self::for_lang( $formatted, $lang, false );

		if ( $negative ) {
			$rtl       = isset( self::THOUSANDS[ $lang ] ) && 'en' !== $lang;
			$formatted = ( $rtl ? self::LRM : '' ) . '-' . $formatted;
		}
		return $formatted;
	}

	/**
	 * Converts digits inside the text nodes of an HTML fragment only (tags and attributes untouched).
	 *
	 * @param string $html    HTML fragment.
	 * @param string $lang    Language code.
	 * @param bool   $protect Keep tokens with Latin letters, URLs, e-mails and entities untouched.
	 */
	public static function html( string $html, string $lang, bool $protect = true ): string {
		if ( '' === $html ) {
			return '';
		}
		$set = self::set_for_lang( $lang );
		if ( self::SET_LATIN === $set ) {
			$protect = true; // Never touch attribute values; to_latin inside tags would be harmless but pointless.
		}
		$parts = preg_split( '/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( '' === $part || '<' === $part[0] ) {
				continue;
			}
			$parts[ $i ] = self::to_set( $part, $set, $protect );
		}
		return implode( '', $parts );
	}

	/**
	 * Whether a token contains Latin letters.
	 */
	public static function has_latin( string $token ): bool {
		return 1 === preg_match( '/[A-Za-z]/', $token );
	}

	/**
	 * Whether a token is a plain Latin word (letters, apostrophes, hyphens, trailing punctuation):
	 * "iPhone", "Pro", "Ultra", "USB-C" — but not "65W", "S24", a URL or an e-mail.
	 */
	public static function is_latin_word( string $token ): bool {
		return '' !== $token && 1 === preg_match( '/^[A-Za-z][A-Za-z\'’\-]*[,.;:!?)]*$/u', $token );
	}

	/**
	 * Whether a whitespace-free token must be left alone: Latin letters, URL, e-mail.
	 */
	public static function is_protected( string $token ): bool {
		if ( self::has_latin( $token ) ) {
			return true;
		}
		return str_contains( $token, '@' ) || str_contains( $token, '://' );
	}

	/**
	 * Named and numeric HTML entities (&nbsp;, &#160;, &#x200F;).
	 */
	private const ENTITY_RE = '/(&(?:#x?[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]*);)/';

	/**
	 * A token with its HTML entities removed (for the Latin/URL protection test).
	 */
	private static function strip_entities( string $token ): string {
		return str_contains( $token, '&' ) ? (string) preg_replace( self::ENTITY_RE, '', $token ) : $token;
	}

	/**
	 * Replaces digits in a token while keeping HTML entities (&#160;, &nbsp;) intact.
	 */
	private static function replace_entities_aware( string $token, string $set ): string {
		if ( ! str_contains( $token, '&' ) ) {
			return self::replace_all( $token, $set );
		}
		$parts = preg_split( self::ENTITY_RE, $token, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $token;
		}
		foreach ( $parts as $i => $part ) {
			if ( '' !== $part && '&' !== $part[0] ) {
				$parts[ $i ] = self::replace_all( $part, $set );
			}
		}
		return implode( '', $parts );
	}

	/**
	 * Unconditional replacement of all three digit sets by the target set.
	 */
	private static function replace_all( string $text, string $set ): string {
		$target = self::SET_ARABIC === $set ? self::ARABIC : self::PERSIAN;
		return str_replace(
			array_merge( self::LATIN, self::PERSIAN, self::ARABIC ),
			array_merge( $target, $target, $target ),
			$text
		);
	}

	/**
	 * Accepts a few spellings for the digit set identifiers.
	 */
	private static function normalize_set( string $set ): string {
		return match ( strtolower( trim( $set ) ) ) {
			'fa', 'persian', 'farsi', 'per'                 => self::SET_PERSIAN,
			'arab', 'ar', 'arabic', 'arabic-indic'          => self::SET_ARABIC,
			default                                         => self::SET_LATIN,
		};
	}
}
