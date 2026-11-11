<?php
/**
 * Hand-over to the "Currency Pricing for WooCommerce" plugin (currency-rates-scraper, bought by
 * the client 2026-11-12 for live market rates).
 *
 * While that plugin is active, Gadgetim's own rate entry steps aside: no Currencies tab, no
 * currency fields on the product screen, no materialiser, guard or reprice job (see
 * Module::register()). The currency price of each product stays: a one-time copy moves the
 * Gadgetim base price (_gtm_base_regular_price / _gtm_base_sale_price in _gtm_price_currency)
 * into the plugin's own fields (_crs_*), with the plugin's name for that currency, and asks the
 * plugin to recompute the stored prices. The _gtm_* values are left in place as a record.
 *
 * The plugin names currencies after its rate source (Persian names such as «درهم امارات»), and
 * those names exist only once it has fetched rates, so the copy waits until the dirham is in
 * its list. Products priced later (e.g. by a product package) are picked up the same way.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

/**
 * Detection, currency-name mapping and the copy into the plugin's meta.
 */
final class Handover {

	public const PLUGIN = 'currency-rates-scraper/currency-rates-scraper.php';

	public const RESULT = 'gadgetim_crs_handover';

	public const CHECKED = 'gadgetim_crs_handover_checked';

	/** Words that identify a currency in the plugin's source names, per Gadgetim code. */
	public const NAME_HINTS = [
		'AED' => [ 'درهم امارات', 'درهم', 'dirham', 'AED' ],
		'USD' => [ 'دلار آمریکا', 'دلار', 'dollar', 'USD' ],
		'EUR' => [ 'یورو', 'euro', 'EUR' ],
	];

	public const META = [
		'enabled'  => '_crs_currency_pricing',
		'auto'     => '_crs_rate_auto',
		'currency' => '_crs_currency',
		'regular'  => '_crs_currency_regular_price',
		'sale'     => '_crs_currency_sale_price',
	];

	/**
	 * Whether the currency plugin is active (works before or after it is loaded).
	 */
	public static function active(): bool {
		if ( defined( 'CRS_VERSION' ) ) {
			return true;
		}
		$plugins = (array) get_option( 'active_plugins', [] );
		if ( in_array( self::PLUGIN, $plugins, true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', [] );
			return isset( $network[ self::PLUGIN ] );
		}
		return false;
	}

	/**
	 * Hooks used only while the plugin is active.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'maybe_copy' ], 50 );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
	}

	/**
	 * The plugin's name for a Gadgetim currency code, '' while it has no such rate.
	 */
	public static function source_name( string $code ): string {
		if ( ! class_exists( 'CRS_Rates' ) ) {
			return '';
		}
		$names = (array) \CRS_Rates::instance()->source_names();
		if ( ! $names ) {
			return '';
		}
		foreach ( self::NAME_HINTS[ strtoupper( $code ) ] ?? [ $code ] as $hint ) {
			foreach ( $names as $name ) {
				if ( (string) $name === $hint ) {
					return (string) $name;
				}
			}
			foreach ( $names as $name ) {
				if ( false !== mb_stripos( (string) $name, $hint ) ) {
					return (string) $name;
				}
			}
		}
		return '';
	}

	/**
	 * admin_init: copies waiting products at most every ten minutes.
	 */
	public static function maybe_copy(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) || get_transient( self::CHECKED ) ) {
			return;
		}
		set_transient( self::CHECKED, 1, 10 * MINUTE_IN_SECONDS );
		self::copy();
	}

	/**
	 * Copies every product (and variation) with a Gadgetim currency price and no plugin price yet.
	 *
	 * @return array{copied:int, waiting:array<string,int>, names:array<string,string>}
	 */
	public static function copy(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off scan, not cached on purpose.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, c.meta_value AS code, r.meta_value AS regular, s.meta_value AS sale
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = %s
				 JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} x ON x.post_id = p.ID AND x.meta_key = %s
				 WHERE p.post_type IN ('product', 'product_variation') AND c.meta_value <> '' AND r.meta_value <> ''
				   AND ( x.meta_value IS NULL OR x.meta_value = '' )",
				Meta::CURRENCY,
				Meta::BASE_REGULAR,
				Meta::BASE_SALE,
				self::META['regular']
			)
		);

		$result = [
			'copied'  => 0,
			'waiting' => [],
			'names'   => [],
			'time'    => time(),
		];
		foreach ( (array) $rows as $row ) {
			$code = strtoupper( (string) $row->code );
			if ( ! isset( $result['names'][ $code ] ) ) {
				$result['names'][ $code ] = self::source_name( $code );
			}
			$name = $result['names'][ $code ];
			if ( '' === $name ) {
				$result['waiting'][ $code ] = ( $result['waiting'][ $code ] ?? 0 ) + 1;
				continue;
			}
			$id = (int) $row->ID;
			update_post_meta( $id, self::META['enabled'], 'yes' );
			update_post_meta( $id, self::META['auto'], 'yes' );
			update_post_meta( $id, self::META['currency'], $name );
			update_post_meta( $id, self::META['regular'], wc_format_decimal( (string) $row->regular ) );
			update_post_meta( $id, self::META['sale'], '' !== (string) $row->sale ? wc_format_decimal( (string) $row->sale ) : '' );
			clean_post_cache( $id );
			++$result['copied'];
		}

		if ( $result['copied'] > 0 ) {
			if ( class_exists( 'CRS_Rates' ) && method_exists( 'CRS_Rates', 'flush_used' ) ) {
				\CRS_Rates::flush_used();
			}
			if ( class_exists( 'CRS_Settings' ) ) {
				// The plugin's own signal to recompute every currency product's stored price.
				do_action( 'crs_settings_changed', \CRS_Settings::all() );
			}
		}
		if ( $result['copied'] > 0 || $result['waiting'] ) {
			$previous = get_option( self::RESULT, [] );
			$result['total'] = (int) ( is_array( $previous ) ? ( $previous['total'] ?? 0 ) : 0 ) + $result['copied'];
			$result['seen']  = false;
			update_option( self::RESULT, $result, false );
		}
		return $result;
	}

	/**
	 * Gadgetim → currency plugin fields for one product being saved (product packages).
	 * Returns false while the plugin has no rate for that currency (the admin_init copy finishes it).
	 */
	public static function apply_to_product( \WC_Product $product, string $code, string $regular, string $sale ): bool {
		$name = self::source_name( $code );
		if ( '' === $name ) {
			return false;
		}
		$product->update_meta_data( self::META['enabled'], 'yes' );
		$product->update_meta_data( self::META['auto'], 'yes' );
		$product->update_meta_data( self::META['currency'], $name );
		$product->update_meta_data( self::META['regular'], wc_format_decimal( $regular ) );
		$product->update_meta_data( self::META['sale'], '' !== $sale ? wc_format_decimal( $sale ) : '' );
		return true;
	}

	/**
	 * admin_notices: the result of the last copy, once; or the waiting state on the plugin's screens.
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$r = get_option( self::RESULT, [] );
		if ( ! is_array( $r ) || ( ! empty( $r['seen'] ) && empty( $r['waiting'] ) ) ) {
			return;
		}
		if ( ! empty( $r['copied'] ) && empty( $r['seen'] ) ) {
			$names = implode( '، ', array_filter( (array) ( $r['names'] ?? [] ) ) );
			echo '<div class="notice notice-success is-dismissible"><p>';
			/* translators: 1: number of products, 2: currency names in the currency plugin */
			echo esc_html( sprintf( __( 'Gadgetim: the currency prices of %1$s products were moved to the currency pricing plugin (%2$s). Their Toman prices now follow its live rate.', 'gadgetim-core' ), number_format_i18n( (int) $r['copied'] ), $names ) );
			echo '</p></div>';
			$r['seen'] = true;
			update_option( self::RESULT, $r, false );
		}
		if ( ! empty( $r['waiting'] ) ) {
			echo '<div class="notice notice-warning"><p>';
			/* translators: %s: list like "AED: 110" */
			echo esc_html( sprintf( __( 'Gadgetim: waiting for the currency pricing plugin to receive its first rates before the currency prices of these products are moved to it: %s. Activate its license; the move then happens on its own.', 'gadgetim-core' ), implode( '، ', array_map( static fn( $c, $n ) => $c . ': ' . number_format_i18n( (int) $n ), array_keys( $r['waiting'] ), $r['waiting'] ) ) ) );
			echo '</p></div>';
		}
	}
}
