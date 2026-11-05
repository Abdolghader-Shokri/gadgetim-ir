<?php
/**
 * Language registry and the current language context (architecture §3.1, §3.2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Support\Options;

/**
 * Option gadgetim_languages (seeded on activation), the detected request language, and the
 * two context switchers: with_lang() (context only: URL prefix, slug maps, translation-meta
 * lookups) and with_locale() (context + switch_to_locale(), used only for e-mails).
 */
final class Languages {

	public const DEFAULT = 'fa';

	public const OPTION = 'gadgetim_languages';

	public const DIGIT_SETS = [ 'fa', 'arab', 'latn' ];

	private static ?string $current = null;

	private static ?string $detected = null;

	/** @var string[] Languages saved by force(), restored by restore(). */
	private static array $stack = [];

	private static ?array $cache = null;

	/**
	 * Detects the request language once, at plugin-file load.
	 */
	public static function init(): void {
		if ( null !== self::$current ) {
			return;
		}
		self::$detected = Request::detect();
		self::$current  = self::$detected;
		add_action( 'update_option_' . self::OPTION, [ self::class, 'flush_cache' ], 10, 0 );
		add_action( 'add_option_' . self::OPTION, [ self::class, 'flush_cache' ], 10, 0 );
	}

	/**
	 * Current language code ('fa', 'en', 'ar').
	 */
	public static function current(): string {
		return self::$current ?? self::DEFAULT;
	}

	/**
	 * Language detected from the request (ignores with_lang()/force() contexts).
	 */
	public static function detected(): string {
		return self::$detected ?? self::DEFAULT;
	}

	/**
	 * Whether a language (current by default) is the primary one (Persian, no prefix).
	 */
	public static function is_default( ?string $lang = null ): bool {
		return self::DEFAULT === ( $lang ?? self::current() );
	}

	/**
	 * All language definitions [ code => def ], merged over the architecture defaults.
	 *
	 * @return array<string,array{locale:string,dir:string,prefix:string,hreflang:string,digits:string,public:bool}>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			self::$cache = Options::languages();
		}
		return self::$cache;
	}

	/**
	 * Drops the in-request cache (after the option changed).
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * One language definition or null.
	 */
	public static function get( string $code ): ?array {
		$code = sanitize_key( $code );
		return '' === $code ? null : ( self::all()[ $code ] ?? null );
	}

	/**
	 * Whether a code is a configured language.
	 */
	public static function exists( string $code ): bool {
		return null !== self::get( $code );
	}

	/**
	 * All codes.
	 *
	 * @return string[]
	 */
	public static function codes(): array {
		return array_keys( self::all() );
	}

	/**
	 * Codes flagged public (Persian always).
	 *
	 * @return string[]
	 */
	public static function public_codes(): array {
		$codes = [];
		foreach ( self::all() as $code => $def ) {
			if ( self::DEFAULT === $code || ! empty( $def['public'] ) ) {
				$codes[] = $code;
			}
		}
		return $codes;
	}

	/**
	 * Codes other than Persian, in option order.
	 *
	 * @return string[]
	 */
	public static function secondary_codes(): array {
		return array_values( array_diff( self::codes(), [ self::DEFAULT ] ) );
	}

	/**
	 * Launch gate (§3.5): Persian is always public, the others only when flagged.
	 */
	public static function is_public( string $code ): bool {
		return self::DEFAULT === $code || ! empty( self::get( $code )['public'] );
	}

	/**
	 * WordPress locale of a language ('fa_IR', 'en_US', 'ar').
	 */
	public static function locale( string $code ): string {
		$locale = (string) ( self::get( $code )['locale'] ?? '' );
		return '' !== $locale ? $locale : ( Options::LANGUAGES_DEFAULT[ $code ]['locale'] ?? 'fa_IR' );
	}

	/**
	 * 'rtl' or 'ltr'.
	 */
	public static function dir( string $code ): string {
		return 'ltr' === ( self::get( $code )['dir'] ?? 'rtl' ) ? 'ltr' : 'rtl';
	}

	/**
	 * URL prefix without slashes ('' for Persian, 'en', 'ar').
	 */
	public static function prefix( string $code ): string {
		return trim( (string) ( self::get( $code )['prefix'] ?? '' ), '/' );
	}

	/**
	 * hreflang value ('fa-IR', 'en', 'ar').
	 */
	public static function hreflang( string $code ): string {
		$value = (string) ( self::get( $code )['hreflang'] ?? '' );
		return '' !== $value ? $value : str_replace( '_', '-', self::locale( $code ) );
	}

	/**
	 * Digit set id ('fa', 'arab', 'latn').
	 */
	public static function digits( string $code ): string {
		$set = (string) ( self::get( $code )['digits'] ?? '' );
		return in_array( $set, self::DIGIT_SETS, true ) ? $set : ( self::DEFAULT === $code ? 'fa' : 'latn' );
	}

	/**
	 * Human label (native script) for admin UI and the switcher.
	 */
	public static function label( string $code ): string {
		return Options::LANGUAGE_LABELS[ $code ] ?? strtoupper( $code );
	}

	/**
	 * Languages that live under a URL prefix: [ prefix => code ].
	 *
	 * @return array<string,string>
	 */
	public static function prefixed(): array {
		$out = [];
		foreach ( self::all() as $code => $def ) {
			$prefix = trim( (string) ( $def['prefix'] ?? '' ), '/' );
			if ( '' !== $prefix ) {
				$out[ $prefix ] = $code;
			}
		}
		return $out;
	}

	/**
	 * Code owning a URL prefix, or null.
	 */
	public static function from_prefix( string $prefix ): ?string {
		return self::prefixed()[ trim( $prefix, '/' ) ] ?? null;
	}

	/**
	 * Code whose locale matches, or null.
	 */
	public static function from_locale( string $locale ): ?string {
		foreach ( self::all() as $code => $def ) {
			if ( ( $def['locale'] ?? '' ) === $locale ) {
				return $code;
			}
		}
		$short = substr( $locale, 0, 2 );
		return self::exists( $short ) ? $short : null;
	}

	/**
	 * Runs $fn with the language context switched (URL prefix, slug maps, translation lookups).
	 * No gettext reload: cheap, used for switcher links, hreflang, cache purges, gateway URLs.
	 */
	public static function with_lang( string $lang, callable $fn ): mixed {
		self::force( $lang );
		try {
			return $fn();
		} finally {
			self::restore();
		}
	}

	/**
	 * Runs $fn with the language context AND the locale switched (switch_to_locale()).
	 * Only for e-mail rendering (§3.2); everything else uses with_lang().
	 */
	public static function with_locale( string $lang, callable $fn ): mixed {
		$lang     = self::exists( $lang ) ? sanitize_key( $lang ) : self::DEFAULT;
		$locale   = self::locale( $lang );
		$switched = false;
		if ( function_exists( 'switch_to_locale' ) && determine_locale() !== $locale ) {
			$switched = (bool) switch_to_locale( $locale );
		}
		try {
			return self::with_lang( $lang, $fn );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Enters a language context until restore() (prefer with_lang()). Nestable.
	 */
	public static function force( string $lang ): void {
		$lang = self::exists( $lang ) ? sanitize_key( $lang ) : self::DEFAULT;
		if ( self::DEFAULT !== $lang ) {
			// Rewrite rules must never be regenerated under a prefixed home (§3.2).
			Urls::prime_rewrite_rules();
		}
		self::$stack[] = self::current();
		self::$current = $lang;
		Urls::push_forced();
	}

	/**
	 * Leaves the innermost force() context.
	 */
	public static function restore(): void {
		if ( ! self::$stack ) {
			return;
		}
		self::$current = array_pop( self::$stack );
		Urls::pop_forced();
	}

	/**
	 * Overrides the detected language for the rest of the request (tests, CLI tools).
	 */
	public static function set_current( string $lang ): void {
		$lang           = self::exists( $lang ) ? sanitize_key( $lang ) : self::DEFAULT;
		self::$current  = $lang;
		self::$detected = $lang;
		self::$stack    = [];
	}

	/**
	 * Forgets the detection so the next init() detects again (tests).
	 */
	public static function reset(): void {
		self::$current  = null;
		self::$detected = null;
		self::$stack    = [];
		self::$cache    = null;
	}

	/**
	 * Activation/upgrade: creates gadgetim_languages from the architecture defaults when missing.
	 *
	 * @param mixed ...$unused Hook arguments (gadgetim_core_upgrade passes $from, $to).
	 */
	public static function seed( mixed ...$unused ): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, Options::LANGUAGES_DEFAULT, '', true );
			self::flush_cache();
		}
	}

	/**
	 * Saves the editable parts of the languages option: 'public' flag and 'digits' set per code.
	 * Codes, locales, prefixes, directions and hreflang values are fixed by the architecture.
	 *
	 * @param array<string,array> $input [ code => [ 'public' => bool, 'digits' => string ] ].
	 */
	public static function update( array $input ): bool {
		$languages = self::all();
		foreach ( $languages as $code => $def ) {
			$row = is_array( $input[ $code ] ?? null ) ? $input[ $code ] : [];
			if ( self::DEFAULT === $code ) {
				$languages[ $code ]['public'] = true;
			} else {
				$languages[ $code ]['public'] = ! empty( $row['public'] ) && rest_sanitize_boolean( $row['public'] );
			}
			$digits = isset( $row['digits'] ) && is_scalar( $row['digits'] ) ? sanitize_key( (string) $row['digits'] ) : '';
			if ( in_array( $digits, self::DIGIT_SETS, true ) ) {
				$languages[ $code ]['digits'] = $digits;
			}
			// Keep the fixed keys exactly as the architecture defines them.
			foreach ( [ 'locale', 'dir', 'prefix', 'hreflang' ] as $fixed ) {
				if ( isset( Options::LANGUAGES_DEFAULT[ $code ][ $fixed ] ) ) {
					$languages[ $code ][ $fixed ] = Options::LANGUAGES_DEFAULT[ $code ][ $fixed ];
				}
			}
		}
		$ok = update_option( self::OPTION, $languages, true );
		self::flush_cache();
		return $ok;
	}
}
