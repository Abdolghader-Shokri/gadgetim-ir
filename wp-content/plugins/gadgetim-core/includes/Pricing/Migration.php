<?php
/**
 * Migration of the old plugin's AED meta onto the new fields (architecture §4.8).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Admin\Tools;
use Gadgetim\Core\Support\Cache;
use Gadgetim\Core\Support\Context;
use Throwable;
use WC_Product;
use WP_Error;

/**
 * The old gadgetim-currency-pricing plugin stored `_gadgetim_currency_regular_price` and
 * `_gadgetim_currency_sale_price` per product and one option `gadgetim_currency_rate` (78,000).
 * migrate_currency() converts every product that still carries the old keys: currency AED, base
 * prices copied as entered ('14.16666' stays), one CRUD save (materialises), old keys deleted.
 * Registered as the Tools entry "migrate-currency"; the importer calls convert_product().
 */
final class Migration {

	public const OLD_REGULAR     = '_gadgetim_currency_regular_price';
	public const OLD_SALE        = '_gadgetim_currency_sale_price';
	public const OLD_RATE_OPTION = 'gadgetim_currency_rate';

	public const CURRENCY    = 'AED';
	public const LEGACY_RATE = 78000;

	public const TOOL_ID = 'migrate-currency';

	/**
	 * admin_init: the Tools tab entry (manage_options).
	 */
	public static function register_tool(): void {
		if ( ! class_exists( Tools::class ) ) {
			return;
		}
		$description = __( 'Converts every product that still carries the old plugin\'s _gadgetim_currency_* meta: pricing currency AED, base prices copied as entered, Toman prices recomputed, old keys removed.', 'gadgetim-core' );
		if ( self::on_settings_page() ) {
			$count = Products::count_with_legacy_meta();
			/* translators: %s: number of products */
			$description .= ' ' . sprintf( _n( '%s product is waiting.', '%s products are waiting.', $count, 'gadgetim-core' ), number_format_i18n( $count ) );
		}
		Tools::add(
			self::TOOL_ID,
			__( 'Migrate legacy AED prices', 'gadgetim-core' ),
			[ self::class, 'run_tool' ],
			'manage_options',
			[
				'description' => $description,
				'button'      => __( 'Migrate', 'gadgetim-core' ),
				'confirm'     => __( 'Convert every product with the old currency meta to AED base prices and recompute its Toman prices now?', 'gadgetim-core' ),
				'order'       => 40,
			]
		);
	}

	/**
	 * Tools callable.
	 *
	 * @return array|WP_Error
	 */
	public static function run_tool( array $tool = [] ): array|WP_Error {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'gadgetim_no_woocommerce', __( 'WooCommerce is not active.', 'gadgetim-core' ) );
		}
		$result = self::migrate_currency();
		if ( 0 === $result['migrated'] && ! $result['errors'] ) {
			return [
				'type'    => 'info',
				'message' => __( 'No product carries the old currency meta; nothing to migrate.', 'gadgetim-core' ),
			];
		}
		$message = sprintf(
			/* translators: 1: number of products migrated, 2: currency code, 3: rate */
			__( '%1$s products now have AED base prices (rate %3$s Toman per %2$s) and recomputed Toman prices.', 'gadgetim-core' ),
			number_format_i18n( $result['migrated'] ),
			self::CURRENCY,
			number_format_i18n( (float) ( Currencies::get( self::CURRENCY )['rate'] ?? 0 ) )
		);
		if ( $result['errors'] ) {
			$message .= ' ' . sprintf(
				/* translators: %s: list of product IDs */
				__( 'Skipped (invalid old values, old keys kept): %s.', 'gadgetim-core' ),
				implode( ', ', array_map( static fn( int $id, string $why ): string => '#' . $id . ' (' . $why . ')', array_keys( $result['errors'] ), $result['errors'] ) )
			);
		}
		return [
			'type'    => $result['errors'] ? 'warning' : 'success',
			'message' => $message,
		];
	}

	/**
	 * Converts every product that still carries the old meta.
	 *
	 * @param int   $limit    0 = all.
	 * @param int[] $only_ids Restrict to these IDs (tests, partial runs).
	 * @return array{migrated:int,skipped:int,errors:array<int,string>,remaining:int}
	 */
	public static function migrate_currency( int $limit = 0, array $only_ids = [] ): array {
		self::seed_currency();

		$ids = Products::ids_with_legacy_meta();
		if ( $only_ids ) {
			$ids = array_values( array_intersect( $ids, array_map( 'intval', $only_ids ) ) );
		}
		if ( $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		$result = [
			'migrated'  => 0,
			'skipped'   => 0,
			'errors'    => [],
			'remaining' => 0,
		];
		if ( ! $ids ) {
			return $result;
		}

		Cache::suspend();
		try {
			Context::with_suspended(
				static function () use ( $ids, &$result ): void {
					foreach ( $ids as $id ) {
						$product = wc_get_product( $id );
						if ( ! $product instanceof WC_Product ) {
							++$result['skipped'];
							continue;
						}
						$regular = Meta::raw( $product, self::OLD_REGULAR );
						$sale    = Meta::raw( $product, self::OLD_SALE );
						if ( ! self::convert_product( $product, $regular, $sale ) ) {
							$result['errors'][ $id ] = __( 'invalid base price', 'gadgetim-core' );
							continue;
						}
						try {
							$product->save();
						} catch ( Throwable $e ) {
							$result['errors'][ $id ] = $e->getMessage();
							continue;
						}
						++$result['migrated'];
					}
				}
			);
		} finally {
			Cache::resume();
		}
		if ( $result['migrated'] > 0 ) {
			Currencies::bump_prices_version();
			Cache::purge_all();
		}
		$result['remaining'] = Products::count_with_legacy_meta();

		/**
		 * Fires after a legacy currency migration run.
		 *
		 * @param array $result migrated, skipped, errors, remaining.
		 */
		do_action( 'gadgetim_currency_migrated', $result );

		return $result;
	}

	/**
	 * Puts the new pricing meta on a product from old-plugin values and removes the old keys.
	 * The caller saves (the save materialises). Returns false when the old regular price is
	 * unusable; the object is then left untouched.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $regular Old base regular price as stored/exported.
	 * @param string     $sale    Old base sale price ('' when none).
	 * @param string     $code    Currency code (AED).
	 */
	public static function convert_product( WC_Product $product, string $regular, string $sale = '', string $code = self::CURRENCY ): bool {
		$code    = Currencies::normalize_code( $code );
		$regular = Calculator::decimal( $regular );
		if ( '' === $code || '' === $regular || (float) $regular <= 0 ) {
			return false;
		}
		$sale = Calculator::decimal( $sale );
		if ( '' !== $sale && ( (float) $sale <= 0 || (float) $sale >= (float) $regular ) ) {
			$sale = '';
		}
		$product->update_meta_data( Meta::CURRENCY, $code );
		$product->update_meta_data( Meta::BASE_REGULAR, $regular );
		$product->update_meta_data( Meta::BASE_SALE, $sale );
		$product->delete_meta_data( self::OLD_REGULAR );
		$product->delete_meta_data( self::OLD_SALE );
		return true;
	}

	/**
	 * Makes sure AED exists and is enabled; a fresh currency takes the rate from the old option
	 * (gadgetim_currency_rate) or the backup-time default of 78,000 Toman.
	 */
	public static function seed_currency(): void {
		$current = Currencies::get( self::CURRENCY );
		if ( null === $current ) {
			Currencies::save(
				self::CURRENCY,
				[
					'rate'          => self::legacy_rate(),
					'rounding_mode' => Currencies::DEFAULTS[ self::CURRENCY ]['rounding_mode'],
					'rounding_step' => Currencies::DEFAULTS[ self::CURRENCY ]['rounding_step'],
					'decimals'      => 2,
					'enabled'       => true,
					'label'         => Currencies::DEFAULTS[ self::CURRENCY ]['label'],
					'symbol'        => 'AED',
				],
				get_current_user_id()
			);
			return;
		}
		$data = [];
		if ( empty( $current['enabled'] ) ) {
			$data['enabled'] = true;
		}
		if ( (float) $current['rate'] <= 0 ) {
			$data['rate'] = self::legacy_rate();
		}
		if ( $data ) {
			Currencies::save( self::CURRENCY, $data, get_current_user_id() );
		}
	}

	/**
	 * Rate of the old plugin when its option survived the data move, else 78,000.
	 */
	public static function legacy_rate(): int|float {
		$stored = get_option( self::OLD_RATE_OPTION, null );
		if ( is_numeric( $stored ) && (float) $stored > 0 ) {
			return (float) $stored === floor( (float) $stored ) ? (int) $stored : (float) $stored;
		}
		return self::LEGACY_RATE;
	}

	/**
	 * Whether the request renders the Gadgetim settings page (the count query is only worth running there).
	 */
	private static function on_settings_page(): bool {
		if ( ! class_exists( SettingsPage::class ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		return isset( $_GET['page'] ) && SettingsPage::SLUG === sanitize_key( wp_unslash( (string) $_GET['page'] ) );
	}
}
