<?php
/**
 * The gadgetim_currencies option and the rate-change history (architecture §4.1).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Options;
use WP_Error;

/**
 * CRUD for the currency definitions.
 *
 * Option gadgetim_currencies (autoload yes): [ 'AED' => [ code, symbol, label{fa,en,ar}, rate,
 * rounding_step, rounding_mode, decimals, enabled, updated_at, updated_by, applied ], … ].
 *
 * `applied` is the snapshot { rate, rounding_mode, rounding_step } the products were last priced
 * with. Product saves (Materializer) and the daily check (Products::mismatched()) always compute
 * from it, so saving a new rate changes no product until "Apply rates" (Reprice) writes the new
 * snapshot. A currency no product uses takes the new values as its snapshot straight away.
 * Option gadgetim_rate_history (autoload no): the last 500 rate changes.
 * Option gadgetim_prices_version: bumped on every rate change / reprice; part of the
 * WooCommerce variation prices hash.
 */
final class Currencies {

	public const OPTION         = 'gadgetim_currencies';
	public const HISTORY_OPTION = 'gadgetim_rate_history';
	public const VERSION_OPTION = 'gadgetim_prices_version';

	public const HISTORY_MAX = 500;

	/**
	 * Seeded on activation. Rates are Toman per unit; AED 78,000 was the rate at backup time.
	 * Client decision (docs/decisions.md, 2026-11-04): round to the nearest 10,000 Toman.
	 */
	public const DEFAULTS = [
		'AED' => [
			'code'          => 'AED',
			'symbol'        => 'AED',
			'label'         => [
				'fa' => 'درهم امارات',
				'en' => 'UAE Dirham',
				'ar' => 'درهم إماراتي',
			],
			'rate'          => 78000,
			'rounding_step' => 10000,
			'rounding_mode' => Calculator::MODE_NEAREST,
			'decimals'      => 2,
			'enabled'       => true,
		],
		'USD' => [
			'code'          => 'USD',
			'symbol'        => '$',
			'label'         => [
				'fa' => 'دلار آمریکا',
				'en' => 'US Dollar',
				'ar' => 'دولار أمريكي',
			],
			'rate'          => 0,
			'rounding_step' => 10000,
			'rounding_mode' => Calculator::MODE_NEAREST,
			'decimals'      => 2,
			'enabled'       => false,
		],
	];

	private static ?array $cache = null;

	/**
	 * Every currency, keyed by code; the seeded defaults while the option does not exist yet.
	 *
	 * @param bool $enabled_only Only enabled currencies.
	 * @return array<string,array>
	 */
	public static function all( bool $enabled_only = false ): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, false );
			$list   = [];
			if ( is_array( $stored ) ) {
				foreach ( $stored as $code => $def ) {
					if ( ! is_array( $def ) ) {
						continue;
					}
					$code = self::normalize_code( isset( $def['code'] ) ? (string) $def['code'] : (string) $code );
					if ( '' === $code ) {
						continue;
					}
					$list[ $code ] = self::with_defaults( $code, $def );
				}
			} else {
				foreach ( self::DEFAULTS as $code => $def ) {
					$list[ $code ] = self::with_defaults( $code, $def );
				}
			}
			self::$cache = $list;
		}
		if ( ! $enabled_only ) {
			return self::$cache;
		}
		return array_filter( self::$cache, static fn( array $currency ): bool => ! empty( $currency['enabled'] ) );
	}

	/**
	 * One currency or null.
	 */
	public static function get( string $code ): ?array {
		$code = self::normalize_code( $code );
		return '' === $code ? null : ( self::all()[ $code ] ?? null );
	}

	/**
	 * Whether a code exists.
	 */
	public static function exists( string $code ): bool {
		return null !== self::get( $code );
	}

	/**
	 * Whether a code exists and is enabled.
	 */
	public static function enabled( string $code ): bool {
		$currency = self::get( $code );
		return null !== $currency && ! empty( $currency['enabled'] );
	}

	/**
	 * Codes, optionally only the enabled ones.
	 *
	 * @return string[]
	 */
	public static function codes( bool $enabled_only = false ): array {
		return array_keys( self::all( $enabled_only ) );
	}

	/**
	 * Activation/upgrade: writes the option with the defaults when it is missing and adds
	 * any default currency that is absent (never overwrites an existing definition).
	 */
	public static function seed(): void {
		$stored = get_option( self::OPTION, false );
		$list   = is_array( $stored ) ? $stored : [];
		$dirty  = ! is_array( $stored );
		foreach ( self::DEFAULTS as $code => $def ) {
			if ( ! isset( $list[ $code ] ) ) {
				$list[ $code ] = $def + [
					'updated_at' => time(),
					'updated_by' => 0,
					'applied'    => self::snapshot_of( $def ),
				];
				$dirty         = true;
			}
		}
		if ( $dirty ) {
			self::write( $list );
		}
	}

	/**
	 * Creates or updates a currency. Only the keys present in $data change. A rate change is
	 * recorded in the history (unapplied) and bumps the prices version.
	 *
	 * @param string $code Three-letter code.
	 * @param array  $data rate, rounding_mode, rounding_step, decimals, enabled, symbol, label.
	 * @param int    $user Acting user ID.
	 * @return array|WP_Error The stored definition.
	 */
	public static function save( string $code, array $data, int $user = 0 ): array|WP_Error {
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return new WP_Error( 'gadgetim_currency_code', __( 'A currency code is three Latin letters (ISO 4217), for example AED.', 'gadgetim-core' ) );
		}
		$all      = self::all();
		$current  = $all[ $code ] ?? null;
		$clean    = self::sanitize( $code, $data, $current );
		$old_rate = null === $current ? null : (float) $current['rate'];
		$new_rate = (float) $clean['rate'];

		$clean['updated_at'] = time();
		$clean['updated_by'] = $user;
		$applied_rate = null === $current ? 0.0 : (float) ( self::applied( $current )['rate'] ?? 0 );
		if ( null === $current || $applied_rate <= 0 || 0 === Products::count_with_currency( $code ) ) {
			// Nothing is priced with it yet (new currency, no products, or a zero applied rate that
			// can never have produced a price): the new values apply at once.
			$clean['applied'] = self::snapshot_of( $clean );
		}
		$all[ $code ] = $clean;
		self::write( $all );

		if ( null === $old_rate || abs( $old_rate - $new_rate ) > 0.000001 ) {
			self::record_history( $code, $old_rate, $new_rate, $user, Products::count_with_currency( $code ), false );
			self::bump_prices_version();
		}

		/**
		 * Fires after a currency definition was saved.
		 *
		 * @param string     $code    Currency code.
		 * @param array      $clean   Stored definition.
		 * @param array|null $current Previous definition, null when created.
		 */
		do_action( 'gadgetim_currency_saved', $code, $clean, $current );

		return $clean;
	}

	/**
	 * Deletes a currency no product uses.
	 *
	 * @return true|WP_Error
	 */
	public static function delete( string $code ): bool|WP_Error {
		$code = self::normalize_code( $code );
		$all  = self::all();
		if ( '' === $code || ! isset( $all[ $code ] ) ) {
			return new WP_Error( 'gadgetim_currency_missing', __( 'Unknown currency.', 'gadgetim-core' ) );
		}
		$count = Products::count_with_currency( $code );
		if ( $count > 0 ) {
			return new WP_Error(
				'gadgetim_currency_in_use',
				sprintf(
					/* translators: 1: currency code, 2: number of products */
					_n( '%1$s cannot be deleted: %2$s product is priced in it.', '%1$s cannot be deleted: %2$s products are priced in it.', $count, 'gadgetim-core' ),
					$code,
					number_format_i18n( $count )
				)
			);
		}
		unset( $all[ $code ] );
		self::write( $all );
		return true;
	}

	/**
	 * Upper-case three-letter code, or '' when the input is not one.
	 */
	public static function normalize_code( string $code ): string {
		$code = strtoupper( trim( Digits::to_latin( $code ) ) );
		return 1 === preg_match( '/^[A-Z]{3}$/', $code ) ? $code : '';
	}

	/**
	 * Validates one definition. Unknown keys are dropped; absent keys keep the current value.
	 */
	public static function sanitize( string $code, array $data, ?array $current ): array {
		$out = $current ?? self::with_defaults( $code, self::DEFAULTS[ $code ] ?? [] );

		if ( array_key_exists( 'rate', $data ) ) {
			$rate        = Calculator::decimal( $data['rate'] );
			$out['rate'] = '' === $rate ? 0 : self::number( $rate );
		}
		if ( array_key_exists( 'rounding_mode', $data ) ) {
			$mode                 = is_scalar( $data['rounding_mode'] ) ? strtolower( trim( (string) $data['rounding_mode'] ) ) : '';
			$out['rounding_mode'] = Calculator::is_mode( $mode ) ? $mode : $out['rounding_mode'];
		}
		if ( array_key_exists( 'rounding_step', $data ) ) {
			$step                 = is_scalar( $data['rounding_step'] ) ? absint( Digits::to_latin( (string) $data['rounding_step'] ) ) : 0;
			$out['rounding_step'] = max( 1, $step );
		}
		if ( array_key_exists( 'decimals', $data ) ) {
			$decimals        = is_scalar( $data['decimals'] ) ? absint( Digits::to_latin( (string) $data['decimals'] ) ) : 2;
			$out['decimals'] = min( 4, $decimals );
		}
		if ( array_key_exists( 'enabled', $data ) ) {
			$out['enabled'] = is_scalar( $data['enabled'] ) ? rest_sanitize_boolean( $data['enabled'] ) : false;
		}
		if ( array_key_exists( 'symbol', $data ) ) {
			$symbol        = is_scalar( $data['symbol'] ) ? mb_substr( sanitize_text_field( (string) $data['symbol'] ), 0, 8 ) : '';
			$out['symbol'] = '' !== $symbol ? $symbol : $code;
		}
		if ( array_key_exists( 'label', $data ) ) {
			$labels = $out['label'];
			if ( is_array( $data['label'] ) ) {
				foreach ( Options::language_codes() as $lang ) {
					if ( array_key_exists( $lang, $data['label'] ) && is_scalar( $data['label'][ $lang ] ) ) {
						$labels[ $lang ] = mb_substr( sanitize_text_field( (string) $data['label'][ $lang ] ), 0, 60 );
					}
				}
			} elseif ( is_scalar( $data['label'] ) ) {
				$labels['fa'] = mb_substr( sanitize_text_field( (string) $data['label'] ), 0, 60 );
			}
			$out['label'] = $labels;
		}
		$out['code'] = $code;
		return $out;
	}

	/**
	 * Display label of a currency in a language (falls back en → fa → code).
	 *
	 * @param array|string $currency Definition or code.
	 * @param string|null  $lang     Language; current language by default.
	 */
	public static function label( array|string $currency, ?string $lang = null ): string {
		if ( is_string( $currency ) ) {
			$currency = self::get( $currency ) ?? [ 'code' => self::normalize_code( $currency ) ];
		}
		$lang   = $lang ?? ( function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa' );
		$labels = is_array( $currency['label'] ?? null ) ? $currency['label'] : [];
		foreach ( [ $lang, 'en', 'fa' ] as $candidate ) {
			if ( isset( $labels[ $candidate ] ) && '' !== trim( (string) $labels[ $candidate ] ) ) {
				return (string) $labels[ $candidate ];
			}
		}
		return (string) ( $currency['code'] ?? '' );
	}

	/**
	 * "AED 30" / "AED 14.17" in Latin digits (the base hint is always LTR).
	 */
	public static function format_amount( string $amount, array $currency ): string {
		$amount = Calculator::decimal( $amount );
		if ( '' === $amount ) {
			return '';
		}
		$max_decimals = (int) ( $currency['decimals'] ?? 2 );
		$fraction     = str_contains( $amount, '.' ) ? rtrim( substr( $amount, strpos( $amount, '.' ) + 1 ), '0' ) : '';
		$decimals     = min( $max_decimals, strlen( $fraction ) );
		return trim( (string) ( $currency['symbol'] ?? $currency['code'] ?? '' ) . ' ' . Digits::format_number( $amount, 'en', $decimals ) );
	}

	/**
	 * The definition with rate, rounding_mode and rounding_step taken from the applied snapshot:
	 * what product saves compute with.
	 */
	public static function applied( array $currency ): array {
		$snapshot = is_array( $currency['applied'] ?? null ) ? $currency['applied'] : self::snapshot_of( $currency );
		foreach ( [ 'rate', 'rounding_mode', 'rounding_step' ] as $key ) {
			if ( array_key_exists( $key, $snapshot ) ) {
				$currency[ $key ] = $snapshot[ $key ];
			}
		}
		return $currency;
	}

	/**
	 * Whether the saved rate, rounding mode or step differs from what the products were priced with.
	 */
	public static function has_pending( array $currency ): bool {
		$applied = self::applied( $currency );
		return abs( (float) $applied['rate'] - (float) ( $currency['rate'] ?? 0 ) ) > 0.000001
			|| (string) $applied['rounding_mode'] !== (string) ( $currency['rounding_mode'] ?? '' )
			|| (int) $applied['rounding_step'] !== (int) ( $currency['rounding_step'] ?? 0 );
	}

	/**
	 * Makes the currency's saved values its applied snapshot (Reprice, before the first batch).
	 *
	 * @return array|null The applied snapshot, null for an unknown currency.
	 */
	public static function snapshot( string $code ): ?array {
		$code = self::normalize_code( $code );
		$all  = self::all();
		if ( '' === $code || ! isset( $all[ $code ] ) ) {
			return null;
		}
		$all[ $code ]['applied'] = self::snapshot_of( $all[ $code ] );
		self::write( $all );
		return $all[ $code ]['applied'];
	}

	/**
	 * { rate, rounding_mode, rounding_step } of a definition.
	 */
	private static function snapshot_of( array $def ): array {
		$mode = (string) ( $def['rounding_mode'] ?? '' );
		return [
			'rate'          => is_numeric( $def['rate'] ?? null ) ? self::number( (string) $def['rate'] ) : 0,
			'rounding_mode' => Calculator::is_mode( $mode ) ? $mode : Calculator::MODE_NEAREST,
			'rounding_step' => max( 1, (int) ( $def['rounding_step'] ?? 1000 ) ),
		];
	}

	/**
	 * Rate as a plain decimal string ('78000', '78000.5').
	 */
	public static function rate_string( array $currency ): string {
		$rate = Calculator::decimal( (string) ( $currency['rate'] ?? '' ) );
		return '' === $rate ? '0' : $rate;
	}

	/**
	 * Enabled currencies in the shape the admin preview script needs.
	 *
	 * @return array<string,array>
	 */
	public static function js_config(): array {
		$out = [];
		foreach ( self::all( true ) as $code => $currency ) {
			// A product save computes with the applied snapshot, so the preview does too.
			$applied      = self::applied( $currency );
			$out[ $code ] = [
				'code'          => $code,
				'symbol'        => (string) $currency['symbol'],
				'label'         => self::label( $currency, 'fa' ),
				'rate'          => (float) $applied['rate'],
				'rounding_mode' => (string) $applied['rounding_mode'],
				'rounding_step' => (int) $applied['rounding_step'],
				'decimals'      => (int) $currency['decimals'],
				'pending'       => self::has_pending( $currency ) ? [
					'rate'          => (float) $currency['rate'],
					'rounding_mode' => (string) $currency['rounding_mode'],
					'rounding_step' => (int) $currency['rounding_step'],
				] : null,
			];
		}
		return $out;
	}

	/**
	 * Rate history, newest first.
	 *
	 * @param int $limit 0 = all.
	 * @return array<int,array>
	 */
	public static function history( int $limit = 0 ): array {
		$entries = array_reverse( self::history_raw() );
		return $limit > 0 ? array_slice( $entries, 0, $limit ) : $entries;
	}

	/**
	 * Appends a history entry.
	 *
	 * @param string     $code     Currency code.
	 * @param float|null $old      Previous rate (null when the currency was created).
	 * @param float      $new      New rate.
	 * @param int        $user     Acting user.
	 * @param int        $affected Products priced in the currency at that time.
	 * @param bool       $applied  Whether the rate was already applied to the products.
	 */
	public static function record_history( string $code, ?float $old, float $new, int $user, int $affected, bool $applied ): void {
		$entries   = self::history_raw();
		$entries[] = [
			'code'       => self::normalize_code( $code ),
			'old'        => $old,
			'new'        => $new,
			'user'       => $user,
			'time'       => time(),
			'affected'   => $affected,
			'applied'    => $applied,
			'applied_at' => $applied ? time() : 0,
		];
		if ( count( $entries ) > self::HISTORY_MAX ) {
			$entries = array_slice( $entries, -self::HISTORY_MAX );
		}
		update_option( self::HISTORY_OPTION, array_values( $entries ), false );
	}

	/**
	 * Marks the newest unapplied entry of a currency as applied, or records a re-apply.
	 */
	public static function mark_applied( string $code, int $affected, int $user ): void {
		$code    = self::normalize_code( $code );
		$entries = self::history_raw();
		for ( $i = count( $entries ) - 1; $i >= 0; $i-- ) {
			if ( ( $entries[ $i ]['code'] ?? '' ) !== $code ) {
				continue;
			}
			if ( ! empty( $entries[ $i ]['applied'] ) ) {
				break;
			}
			$entries[ $i ]['applied']    = true;
			$entries[ $i ]['applied_at'] = time();
			$entries[ $i ]['affected']   = $affected;
			update_option( self::HISTORY_OPTION, array_values( $entries ), false );
			return;
		}
		$rate = self::get( $code )['rate'] ?? 0;
		self::record_history( $code, (float) $rate, (float) $rate, $user, $affected, true );
	}

	/**
	 * Whether a currency has a rate change that was not applied to its products yet.
	 */
	public static function has_unapplied_change( string $code ): bool {
		$code     = self::normalize_code( $code );
		$currency = self::get( $code );
		if ( null !== $currency && self::has_pending( $currency ) ) {
			return true;
		}
		foreach ( self::history( 0 ) as $entry ) {
			if ( ( $entry['code'] ?? '' ) === $code ) {
				return empty( $entry['applied'] );
			}
		}
		return false;
	}

	/**
	 * Bumps gadgetim_prices_version (part of the variation prices hash).
	 */
	public static function bump_prices_version(): void {
		update_option( self::VERSION_OPTION, (string) time(), true );
	}

	/**
	 * Drops the in-request cache.
	 */
	public static function invalidate(): void {
		self::$cache = null;
	}

	/**
	 * Fills every key of a stored definition.
	 */
	private static function with_defaults( string $code, array $def ): array {
		$base  = self::DEFAULTS[ $code ] ?? [];
		$label = $def['label'] ?? $base['label'] ?? [];
		if ( ! is_array( $label ) ) {
			$label = [ 'fa' => (string) $label ];
		}
		$mode    = (string) ( $def['rounding_mode'] ?? '' );
		$applied = is_array( $def['applied'] ?? null ) ? self::snapshot_of( $def['applied'] ) : null;
		$out     = [
			'code'          => $code,
			'symbol'        => (string) ( $def['symbol'] ?? $base['symbol'] ?? $code ),
			'label'         => array_map( 'strval', $label ),
			'rate'          => is_numeric( $def['rate'] ?? null ) ? self::number( (string) $def['rate'] ) : self::number( (string) ( $base['rate'] ?? 0 ) ),
			'rounding_step' => max( 1, (int) ( $def['rounding_step'] ?? $base['rounding_step'] ?? 1000 ) ),
			'rounding_mode' => Calculator::is_mode( $mode ) ? $mode : (string) ( $base['rounding_mode'] ?? Calculator::MODE_NEAREST ),
			'decimals'      => min( 4, max( 0, (int) ( $def['decimals'] ?? $base['decimals'] ?? 2 ) ) ),
			'enabled'       => array_key_exists( 'enabled', $def ) ? ! empty( $def['enabled'] ) : ! empty( $base['enabled'] ),
			'updated_at'    => (int) ( $def['updated_at'] ?? 0 ),
			'updated_by'    => (int) ( $def['updated_by'] ?? 0 ),
		];
		// Definitions stored before the snapshot existed: their products were priced with the saved values.
		$out['applied'] = $applied ?? self::snapshot_of( $out );
		return $out;
	}

	/**
	 * Numeric string → int when integral, float otherwise.
	 */
	private static function number( string $value ): int|float {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}
		$float = (float) $value;
		return floor( $float ) === $float && abs( $float ) < PHP_INT_MAX ? (int) $float : $float;
	}

	private static function history_raw(): array {
		$entries = get_option( self::HISTORY_OPTION, [] );
		return is_array( $entries ) ? array_values( array_filter( $entries, 'is_array' ) ) : [];
	}

	private static function write( array $all ): void {
		update_option( self::OPTION, $all, true );
		self::$cache = null;
	}
}
