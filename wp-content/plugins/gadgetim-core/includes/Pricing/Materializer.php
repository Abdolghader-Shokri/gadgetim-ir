<?php
/**
 * Writes the computed Toman prices into the WooCommerce fields on every save (architecture §4.3, D3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Settings\Admin\Notices;
use Gadgetim\Core\Support\Options;
use WC_DateTime;
use WC_Product;

/**
 * Runs inside woocommerce_before_product_object_save, which WC_Product_Variation shares with
 * products (its object_type stays 'product'), so one hook covers simple products, variable
 * parents and variations saved by anyone: admin, importer, wc/v3, quick edit, the reprice job
 * and wc_scheduled_sales. WooCommerce's data store then derives _price (respecting the sale
 * schedule) and refreshes wc_product_meta_lookup.
 */
final class Materializer {

	/** Marker class inside our product flags so unflag() never removes another module's flag. */
	public const FLAG_MARKER = 'gtm-pricing-flag';

	/**
	 * woocommerce_before_product_object_save.
	 *
	 * @param mixed $product    WC_Product being saved.
	 * @param mixed $data_store Data store (unused).
	 */
	public static function apply( $product, $data_store = null ): void {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$code = Meta::currency( $product );
		if ( '' === $code ) {
			return;
		}
		$id       = $product->get_id();
		$currency = Currencies::get( $code );
		if ( null === $currency ) {
			/* translators: %s: currency code */
			self::flag( $id, sprintf( __( 'its pricing currency %s does not exist any more; the last Toman prices were kept.', 'gadgetim-core' ), $code ) );
			return;
		}
		if ( empty( $currency['enabled'] ) ) {
			/* translators: %s: currency code */
			self::flag( $id, sprintf( __( 'its pricing currency %s is disabled; the last Toman prices were kept.', 'gadgetim-core' ), $code ) );
			return;
		}

		$regular = Meta::base_regular( $product );
		if ( '' === $regular ) {
			return;
		}

		// Always the applied snapshot: a saved but unapplied rate reaches products only via Reprice.
		$currency = Currencies::applied( $currency );

		$sale = Meta::base_sale( $product );
		if ( '' !== $sale && self::sale_has_ended( $product ) ) {
			// The base sale field is the only way to end a sale for a currency product: clear it once the schedule ran out.
			$sale = '';
			$product->update_meta_data( Meta::BASE_SALE, '' );
		}

		$regular_toman = Calculator::to_toman( $regular, $currency );
		if ( $regular_toman <= 0 ) {
			/* translators: %s: currency code */
			self::flag( $id, sprintf( __( 'its base price or the %s rate is zero; the last Toman prices were kept.', 'gadgetim-core' ), $code ) );
			return;
		}
		$sale_toman = '' === $sale ? 0 : Calculator::to_toman( $sale, $currency );
		if ( '' !== $sale && $sale_toman >= $regular_toman ) {
			// Sale ≥ regular is rejected: no sale, and the base field is cleared so the data stays consistent.
			$sale_toman = 0;
			$product->update_meta_data( Meta::BASE_SALE, '' );
		}

		$product->set_regular_price( (string) $regular_toman );
		$product->set_sale_price( $sale_toman > 0 ? (string) $sale_toman : '' );
		$product->update_meta_data( Meta::RATE_USED, Currencies::rate_string( $currency ) );
		$product->update_meta_data( Meta::PRICED_AT, (string) time() );

		self::unflag( $id );

		/**
		 * Fires after the Toman prices of a currency product were computed (before the save persists them).
		 *
		 * @param WC_Product $product       Product or variation.
		 * @param array      $currency      Currency definition used.
		 * @param int        $regular_toman Regular price in Toman.
		 * @param int        $sale_toman    Sale price in Toman, 0 when not on sale.
		 */
		do_action( 'gadgetim_price_materialized', $product, $currency, $regular_toman, $sale_toman );
	}

	/**
	 * woocommerce_get_variation_prices_hash: a rate change invalidates every cached variation price range.
	 *
	 * @param mixed $hash Hash factors.
	 * @return mixed
	 */
	public static function prices_hash( $hash ) {
		if ( is_array( $hash ) ) {
			$hash['gadgetim_prices_version'] = Options::prices_version();
		}
		return $hash;
	}

	/**
	 * Whether the product's sale schedule is over, or this save explicitly ends the sale
	 * (sale price and end date both cleared in the same CRUD call).
	 */
	public static function sale_has_ended( WC_Product $product ): bool {
		$to = $product->get_date_on_sale_to( 'edit' );
		if ( $to instanceof WC_DateTime && $to->getTimestamp() < time() ) {
			return true;
		}
		$changes = $product->get_changes();
		return array_key_exists( 'sale_price', $changes )
			&& '' === (string) $changes['sale_price']
			&& array_key_exists( 'date_on_sale_to', $changes )
			&& empty( $changes['date_on_sale_to'] );
	}

	/**
	 * Persistent warning for a product (Settings\Admin\Notices when present).
	 */
	private static function flag( int $id, string $message ): void {
		if ( $id <= 0 || ! class_exists( Notices::class ) ) {
			return;
		}
		Notices::flag_product( $id, '<span class="' . self::FLAG_MARKER . '">' . esc_html( $message ) . '</span>' );
	}

	/**
	 * Removes our own flag once the product materialises again.
	 */
	private static function unflag( int $id ): void {
		if ( $id <= 0 || ! class_exists( Notices::class ) ) {
			return;
		}
		$notice = Notices::all()[ 'product-' . $id ] ?? null;
		if ( is_array( $notice ) && str_contains( (string) ( $notice['message'] ?? '' ), self::FLAG_MARKER ) ) {
			Notices::clear_product( $id );
		}
	}
}
