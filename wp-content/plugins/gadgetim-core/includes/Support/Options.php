<?php
/**
 * Typed access to the gadgetim_* options with defaults (architecture Appendix B).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

/**
 * Option getters. Modules own the *contents* of their options; this class only
 * guarantees shape and defaults so that readers never deal with `false`.
 */
final class Options {

	public const PREFIX = 'gadgetim_';

	/** Architecture §3.1 — used when gadgetim_languages has not been written yet. */
	public const LANGUAGES_DEFAULT = [
		'fa' => [
			'locale'   => 'fa_IR',
			'dir'      => 'rtl',
			'prefix'   => '',
			'hreflang' => 'fa-IR',
			'digits'   => 'fa',
			'public'   => true,
		],
		'en' => [
			'locale'   => 'en_US',
			'dir'      => 'ltr',
			'prefix'   => 'en',
			'hreflang' => 'en',
			'digits'   => 'latn',
			'public'   => false,
		],
		'ar' => [
			'locale'   => 'ar',
			'dir'      => 'rtl',
			'prefix'   => 'ar',
			'hreflang' => 'ar',
			'digits'   => 'arab',
			'public'   => false,
		],
	];

	/** Human labels for the language codes (admin UI only). */
	public const LANGUAGE_LABELS = [
		'fa' => 'فارسی',
		'en' => 'English',
		'ar' => 'العربية',
	];

	/**
	 * Raw option read; the name may be given with or without the gadgetim_ prefix.
	 */
	public static function get( string $name, mixed $default = false ): mixed {
		return get_option( self::name( $name ), $default );
	}

	/**
	 * Option write with the prefix enforced.
	 *
	 * @param bool|null $autoload true/false, or null to keep the current autoload flag.
	 */
	public static function update( string $name, mixed $value, ?bool $autoload = null ): bool {
		return update_option( self::name( $name ), $value, $autoload );
	}

	/**
	 * Option delete with the prefix enforced.
	 */
	public static function delete( string $name ): bool {
		return delete_option( self::name( $name ) );
	}

	/**
	 * Boolean option.
	 */
	public static function bool( string $name, bool $default = false ): bool {
		$value = self::get( $name, null );
		if ( null === $value || false === $value ) {
			return $default;
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? $default;
	}

	/**
	 * Integer option.
	 */
	public static function int( string $name, int $default = 0 ): int {
		$value = self::get( $name, null );
		return is_numeric( $value ) ? (int) $value : $default;
	}

	/**
	 * String option.
	 */
	public static function string( string $name, string $default = '' ): string {
		$value = self::get( $name, null );
		return is_scalar( $value ) ? (string) $value : $default;
	}

	/**
	 * Array option.
	 */
	public static function array( string $name, array $default = [] ): array {
		$value = self::get( $name, null );
		return is_array( $value ) ? $value : $default;
	}

	/**
	 * gadgetim_languages merged over the architecture defaults so every key exists.
	 *
	 * @return array<string,array{locale:string,dir:string,prefix:string,hreflang:string,digits:string,public:bool}>
	 */
	public static function languages(): array {
		$stored = self::array( 'languages' );
		$langs  = self::LANGUAGES_DEFAULT;
		foreach ( $stored as $code => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$code           = sanitize_key( (string) $code );
			$langs[ $code ] = array_merge( $langs[ $code ] ?? self::LANGUAGES_DEFAULT['en'], $def );
		}
		foreach ( $langs as $code => $def ) {
			$langs[ $code ]['public'] = ! empty( $def['public'] );
		}
		return $langs;
	}

	/**
	 * One language definition or null.
	 */
	public static function language( string $code ): ?array {
		return self::languages()[ sanitize_key( $code ) ] ?? null;
	}

	/**
	 * Codes of the languages flagged public (fa is always public).
	 *
	 * @return string[]
	 */
	public static function public_languages(): array {
		$public = [];
		foreach ( self::languages() as $code => $def ) {
			if ( 'fa' === $code || $def['public'] ) {
				$public[] = $code;
			}
		}
		return $public;
	}

	/**
	 * All language codes.
	 *
	 * @return string[]
	 */
	public static function language_codes(): array {
		return array_keys( self::languages() );
	}

	/**
	 * gadgetim_currencies (owned by Pricing; shape per architecture §4.1).
	 */
	public static function currencies(): array {
		return self::array( 'currencies' );
	}

	/**
	 * gadgetim_redirects (owned by Seo; autoload no).
	 */
	public static function redirects(): array {
		return self::array( 'redirects' );
	}

	/**
	 * gadgetim_strings (owned by I18n).
	 */
	public static function strings(): array {
		return self::array( 'strings' );
	}

	/**
	 * gadgetim_home (home sections).
	 */
	public static function home(): array {
		return self::array( 'home' );
	}

	/**
	 * gadgetim_rate_history (autoload no).
	 */
	public static function rate_history(): array {
		return self::array( 'rate_history' );
	}

	/**
	 * gadgetim_prices_version — part of the variation prices hash.
	 */
	public static function prices_version(): string {
		return self::string( 'prices_version', '0' );
	}

	/**
	 * Full option name with the prefix.
	 */
	public static function name( string $name ): string {
		return str_starts_with( $name, self::PREFIX ) ? $name : self::PREFIX . $name;
	}
}
