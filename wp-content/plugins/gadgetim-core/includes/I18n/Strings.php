<?php
/**
 * String overrides: option gadgetim_strings (architecture §3.3, §3.2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Options;

/**
 * gadgetim_strings = [ md5(source) => [ source, domain, context, en, ar ] ].
 *
 * Two uses: (1) WooCommerce-owned labels typed in Persian in the WC settings (gateway title
 * and description, shipping rate labels, account menu items, attribute labels) are replaced
 * through their output filters; (2) strings the incomplete WooCommerce `ar` language pack
 * lacks are supplied through gettext/gettext_with_context for the `woocommerce` domain when
 * the language is Arabic. Missing strings can be collected for the Strings page.
 */
final class Strings {

	public const OPTION = 'gadgetim_strings';

	public const MISSING_OPTION = 'gadgetim_strings_missing';

	public const MAX_MISSING = 500;

	private static ?array $entries = null;

	/** @var array<string,array<string,string>> [ lang => [ source => translation ] ] for gettext. */
	private static array $gettext = [];

	/** @var array<string,true> */
	private static array $missing = [];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'gettext', [ self::class, 'filter_gettext' ], 5, 3 );
		add_filter( 'gettext_with_context', [ self::class, 'filter_gettext_with_context' ], 5, 4 );
		add_filter( 'woocommerce_gateway_title', [ self::class, 'filter_label' ], 10, 1 );
		add_filter( 'woocommerce_gateway_description', [ self::class, 'filter_label' ], 10, 1 );
		add_filter( 'woocommerce_shipping_rate_label', [ self::class, 'filter_label' ], 10, 1 );
		add_filter( 'woocommerce_attribute_label', [ self::class, 'filter_label' ], 10, 1 );
		add_filter( 'woocommerce_account_menu_items', [ self::class, 'filter_account_menu_items' ], 10, 1 );
		add_action( 'update_option_' . self::OPTION, [ self::class, 'flush_cache' ], 10, 0 );
		add_action( 'add_option_' . self::OPTION, [ self::class, 'flush_cache' ], 10, 0 );
		add_action( 'shutdown', [ self::class, 'store_missing' ], 50 );
	}

	/**
	 * Stable key of a source string.
	 */
	public static function hash( string $source ): string {
		return md5( $source );
	}

	/**
	 * All entries [ hash => entry ].
	 */
	public static function all(): array {
		if ( null === self::$entries ) {
			$entries = Options::strings();
			$clean   = [];
			foreach ( $entries as $hash => $entry ) {
				if ( is_array( $entry ) && isset( $entry['source'] ) && is_string( $entry['source'] ) && '' !== $entry['source'] ) {
					$clean[ (string) $hash ] = $entry;
				}
			}
			self::$entries = $clean;
		}
		return self::$entries;
	}

	/**
	 * Drops the caches after the option changed.
	 */
	public static function flush_cache(): void {
		self::$entries = null;
		self::$gettext = [];
	}

	/**
	 * Translation of a source string for a language, else the source itself.
	 */
	public static function translate( string $source, ?string $lang = null ): string {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang || '' === $source ) {
			return $source;
		}
		$entry = self::all()[ self::hash( $source ) ] ?? null;
		$value = is_array( $entry ) ? (string) ( $entry[ $lang ] ?? '' ) : '';
		return '' !== $value ? $value : $source;
	}

	/**
	 * Whether an override exists for a source string in a language.
	 */
	public static function has( string $source, string $lang ): bool {
		$entry = self::all()[ self::hash( $source ) ] ?? null;
		return is_array( $entry ) && '' !== (string) ( $entry[ $lang ] ?? '' );
	}

	/**
	 * Adds or updates an entry. $translations = [ 'en' => ..., 'ar' => ... ].
	 */
	public static function set( string $source, array $translations, string $domain = 'woocommerce', string $context = '' ): void {
		$source = self::sanitize_source( $source );
		if ( '' === $source ) {
			return;
		}
		$entries = self::all();
		$hash    = self::hash( $source );
		$entry   = $entries[ $hash ] ?? [];
		$entry['source']  = $source;
		$entry['domain']  = self::sanitize_domain( $domain );
		$entry['context'] = sanitize_text_field( $context );
		foreach ( Languages::secondary_codes() as $lang ) {
			if ( array_key_exists( $lang, $translations ) ) {
				$entry[ $lang ] = is_scalar( $translations[ $lang ] ) ? wp_kses_post( trim( (string) $translations[ $lang ] ) ) : '';
			}
		}
		$entries[ $hash ] = $entry;
		self::save( $entries );
	}

	/**
	 * Removes entries by hash.
	 *
	 * @param string[] $hashes Hashes.
	 */
	public static function delete( array $hashes ): void {
		$entries = self::all();
		foreach ( $hashes as $hash ) {
			unset( $entries[ (string) $hash ] );
		}
		self::save( $entries );
	}

	/**
	 * Replaces the whole option (admin page save).
	 *
	 * @param array<string,array> $entries Entries keyed by hash.
	 */
	public static function save( array $entries ): void {
		update_option( self::OPTION, $entries, true );
		self::flush_cache();
	}

	/**
	 * Source strings keep placeholders and limited HTML (gateway descriptions carry markup).
	 */
	public static function sanitize_source( string $source ): string {
		return trim( wp_kses_post( $source ) );
	}

	/**
	 * Text domain names: lowercase letters, digits, dashes, underscores.
	 */
	public static function sanitize_domain( string $domain ): string {
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '/[^a-z0-9_-]/', '', $domain ) ?? '';
		return '' === $domain ? 'woocommerce' : $domain;
	}

	/**
	 * gettext (domain woocommerce only, Arabic only): fills the gaps of the ar language pack.
	 *
	 * @param mixed  $translation Translated text.
	 * @param mixed  $text        Source text.
	 * @param mixed  $domain      Text domain.
	 */
	public static function filter_gettext( $translation, $text = '', $domain = 'default' ) {
		if ( 'woocommerce' !== $domain || ! is_string( $text ) || ! is_string( $translation ) ) {
			return $translation;
		}
		$lang = Languages::current();
		if ( 'ar' !== $lang ) {
			return $translation;
		}
		$map = self::gettext_map( $lang );
		if ( ! isset( $map[ $text ] ) && ( $translation !== $text || ! self::collecting() ) ) {
			return $translation; // Nothing to do for this string: skip the context checks on a hot path.
		}
		if ( ! Context::may_translate() ) {
			return $translation;
		}
		if ( isset( $map[ $text ] ) ) {
			return $map[ $text ];
		}
		self::note_missing( $text );
		return $translation;
	}

	/**
	 * gettext_with_context (domain woocommerce, Arabic).
	 *
	 * @param mixed $translation Translated text.
	 * @param mixed $text        Source text.
	 * @param mixed $context     Context.
	 * @param mixed $domain      Text domain.
	 */
	public static function filter_gettext_with_context( $translation, $text = '', $context = '', $domain = 'default' ) {
		if ( 'woocommerce' !== $domain || ! is_string( $text ) || ! is_string( $translation ) ) {
			return $translation;
		}
		$lang = Languages::current();
		if ( 'ar' !== $lang ) {
			return $translation;
		}
		$map = self::gettext_map( $lang );
		$key = (string) $context . "\x04" . $text;
		$hit = isset( $map[ $key ] ) ? $key : ( isset( $map[ $text ] ) ? $text : '' );
		if ( '' === $hit && ( $translation !== $text || ! self::collecting() ) ) {
			return $translation;
		}
		if ( ! Context::may_translate() ) {
			return $translation;
		}
		if ( '' !== $hit ) {
			return $map[ $hit ];
		}
		self::note_missing( $text, (string) $context );
		return $translation;
	}

	/**
	 * WooCommerce-owned Persian labels (gateway title/description, shipping rate label,
	 * attribute label) replaced by their stored translation.
	 *
	 * @param mixed $label Label.
	 */
	public static function filter_label( $label ) {
		if ( ! is_string( $label ) || '' === $label || ! Context::may_translate() ) {
			return $label;
		}
		return self::translate( $label );
	}

	/**
	 * woocommerce_account_menu_items.
	 *
	 * @param mixed $items [ endpoint => label ].
	 */
	public static function filter_account_menu_items( $items ) {
		if ( ! is_array( $items ) || ! Context::may_translate() ) {
			return $items;
		}
		foreach ( $items as $endpoint => $label ) {
			if ( is_string( $label ) && '' !== $label ) {
				$items[ $endpoint ] = self::translate( $label );
			}
		}
		return $items;
	}

	/**
	 * Whether missing-string collection is on (Languages tab) and the visitor may collect.
	 */
	private static function collecting(): bool {
		static $on = null;
		if ( null !== $on ) {
			return $on;
		}
		// Pluggable functions (current user) exist only after plugins loaded; decide once init ran.
		if ( ! did_action( 'init' ) || ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}
		$on = class_exists( Settings::class ) && (bool) Settings::get( 'i18n.collect_missing', false ) && is_user_logged_in() && current_user_can( 'manage_options' );
		return $on;
	}

	/**
	 * Remembers an untranslated WooCommerce string seen in this request.
	 */
	private static function note_missing( string $text, string $context = '' ): void {
		if ( count( self::$missing ) >= 200 || ! preg_match( '/[A-Za-z]{3,}/', $text ) ) {
			return;
		}
		self::$missing[ ( '' !== $context ? $context . "\x04" : '' ) . $text ] = true;
	}

	/**
	 * shutdown: merges the strings seen into gadgetim_strings_missing (capped, autoload no).
	 */
	public static function store_missing(): void {
		if ( ! self::$missing ) {
			return;
		}
		$stored = get_option( self::MISSING_OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		$known  = self::all();
		$added  = false;
		foreach ( array_keys( self::$missing ) as $key ) {
			$parts   = explode( "\x04", (string) $key, 2 );
			$text    = end( $parts );
			$context = 2 === count( $parts ) ? $parts[0] : '';
			$hash    = self::hash( $text );
			if ( isset( $stored[ $hash ] ) || isset( $known[ $hash ] ) ) {
				continue;
			}
			$stored[ $hash ] = [
				'source'  => $text,
				'context' => $context,
				'domain'  => 'woocommerce',
				'seen'    => time(),
			];
			$added           = true;
		}
		if ( ! $added ) {
			return;
		}
		if ( count( $stored ) > self::MAX_MISSING ) {
			uasort( $stored, static fn( array $a, array $b ): int => ( $b['seen'] ?? 0 ) <=> ( $a['seen'] ?? 0 ) );
			$stored = array_slice( $stored, 0, self::MAX_MISSING, true );
		}
		update_option( self::MISSING_OPTION, $stored, false );
		self::$missing = [];
	}

	/**
	 * The collected missing strings [ hash => [ source, context, domain, seen ] ].
	 */
	public static function missing(): array {
		$stored = get_option( self::MISSING_OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Forgets collected missing strings (after they were added, or on request).
	 *
	 * @param string[]|null $hashes Specific hashes, or null for all.
	 */
	public static function forget_missing( ?array $hashes = null ): void {
		if ( null === $hashes ) {
			delete_option( self::MISSING_OPTION );
			return;
		}
		$stored = self::missing();
		foreach ( $hashes as $hash ) {
			unset( $stored[ (string) $hash ] );
		}
		update_option( self::MISSING_OPTION, $stored, false );
	}

	/**
	 * [ source => translation ] (and [ context\x04source => translation ]) for the gettext
	 * filters, built once per request from the woocommerce-domain entries.
	 *
	 * @return array<string,string>
	 */
	private static function gettext_map( string $lang ): array {
		if ( isset( self::$gettext[ $lang ] ) ) {
			return self::$gettext[ $lang ];
		}
		$map = [];
		foreach ( self::all() as $entry ) {
			$domain = (string) ( $entry['domain'] ?? 'woocommerce' );
			if ( 'woocommerce' !== $domain && '*' !== $domain ) {
				continue;
			}
			$value = (string) ( $entry[ $lang ] ?? '' );
			if ( '' === $value ) {
				continue;
			}
			$context = (string) ( $entry['context'] ?? '' );
			if ( '' !== $context ) {
				$map[ $context . "\x04" . $entry['source'] ] = $value;
			} else {
				$map[ (string) $entry['source'] ] = $value;
			}
		}
		self::$gettext[ $lang ] = $map;
		return $map;
	}
}
