<?php
/**
 * The 31 Iranian provinces and their cities, bundled as includes/Checkout/data/iran-provinces.json.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Support\Normalize;

/**
 * Keys are WooCommerce's IR state codes (THR, ESF, …) so stored addresses and shipping zones
 * keep working; names per language; cities in Persian.
 */
final class Provinces {

	public const FILE = __DIR__ . '/data/iran-provinces.json';

	public const LANGS = [ 'fa', 'en', 'ar' ];

	private static ?array $data = null;

	/**
	 * [ code => [ 'fa' => …, 'en' => …, 'ar' => …, 'cities' => [ … ] ] ].
	 */
	public static function all(): array {
		if ( null !== self::$data ) {
			return self::$data;
		}
		$decoded   = is_readable( self::FILE ) ? wp_json_file_decode( self::FILE, [ 'associative' => true ] ) : null;
		$provinces = [];
		foreach ( (array) ( $decoded['provinces'] ?? [] ) as $code => $province ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $code ) ?? '' );
			if ( '' === $code || ! is_array( $province ) ) {
				continue;
			}
			$entry = [];
			foreach ( self::LANGS as $lang ) {
				$entry[ $lang ] = isset( $province[ $lang ] ) && is_scalar( $province[ $lang ] ) ? trim( (string) $province[ $lang ] ) : '';
			}
			if ( '' === $entry['fa'] ) {
				continue;
			}
			$cities = [];
			foreach ( (array) ( $province['cities'] ?? [] ) as $city ) {
				if ( is_scalar( $city ) && '' !== trim( (string) $city ) ) {
					$cities[] = trim( (string) $city );
				}
			}
			$entry['cities']   = array_values( array_unique( $cities ) );
			$provinces[ $code ] = $entry;
		}

		/**
		 * Filters the bundled province/city data.
		 *
		 * @param array $provinces [ code => [ fa, en, ar, cities ] ].
		 */
		self::$data = (array) apply_filters( 'gadgetim_iran_provinces', $provinces );
		return self::$data;
	}

	/**
	 * Province codes.
	 *
	 * @return string[]
	 */
	public static function codes(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether a code is a known province (case-insensitive).
	 */
	public static function has( string $code ): bool {
		return isset( self::all()[ strtoupper( trim( $code ) ) ] );
	}

	/**
	 * Province name in a language (Persian when the translation is missing; '' for unknown codes).
	 */
	public static function name( string $code, string $lang = 'fa' ): string {
		$province = self::all()[ strtoupper( trim( $code ) ) ] ?? null;
		if ( null === $province ) {
			return '';
		}
		$name = (string) ( $province[ $lang ] ?? '' );
		return '' !== $name ? $name : (string) $province['fa'];
	}

	/**
	 * [ code => name ] for a language.
	 *
	 * @return array<string,string>
	 */
	public static function names( string $lang = 'fa' ): array {
		$names = [];
		foreach ( self::all() as $code => $province ) {
			$name           = (string) ( $province[ $lang ] ?? '' );
			$names[ $code ] = '' !== $name ? $name : (string) $province['fa'];
		}
		return $names;
	}

	/**
	 * Cities of a province (Persian names, capital first).
	 *
	 * @return string[]
	 */
	public static function cities( string $code ): array {
		return self::all()[ strtoupper( trim( $code ) ) ]['cities'] ?? [];
	}

	/**
	 * [ code => cities[] ] for the checkout script.
	 *
	 * @return array<string,string[]>
	 */
	public static function cities_map(): array {
		$map = [];
		foreach ( self::all() as $code => $province ) {
			$map[ $code ] = $province['cities'];
		}
		return $map;
	}

	/**
	 * Province code for a name in any language (Arabic/Persian letter variants tolerated), or ''.
	 */
	public static function code_for_name( string $name ): string {
		$needle = self::fold( $name );
		if ( '' === $needle ) {
			return '';
		}
		foreach ( self::all() as $code => $province ) {
			foreach ( self::LANGS as $lang ) {
				if ( '' !== $province[ $lang ] && self::fold( $province[ $lang ] ) === $needle ) {
					return $code;
				}
			}
		}
		return '';
	}

	/**
	 * Whether a city belongs to a province's list (letter variants tolerated).
	 */
	public static function is_city( string $code, string $city ): bool {
		$needle = self::fold( $city );
		foreach ( self::cities( $code ) as $candidate ) {
			if ( self::fold( $candidate ) === $needle ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Comparison key: Normalize::text when Support\Normalize is present, else trimmed lowercase.
	 */
	private static function fold( string $text ): string {
		if ( class_exists( Normalize::class ) ) {
			return Normalize::text( $text );
		}
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $text ) ) : strtolower( trim( $text ) );
	}
}
