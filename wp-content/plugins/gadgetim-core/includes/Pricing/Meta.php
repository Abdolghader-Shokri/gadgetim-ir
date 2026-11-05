<?php
/**
 * Pricing meta keys and typed readers (architecture §4.1, Appendix B).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use WC_Product;

/**
 * The five product/variation meta keys of the pricing module. Readers use the 'edit'
 * context so no view filter can alter what the materialiser computes from.
 */
final class Meta {

	public const CURRENCY     = '_gtm_price_currency';
	public const BASE_REGULAR = '_gtm_base_regular_price';
	public const BASE_SALE    = '_gtm_base_sale_price';
	public const RATE_USED    = '_gtm_rate_used';
	public const PRICED_AT    = '_gtm_priced_at';

	/** Inputs of the materialiser: a direct write to one of these must trigger a CRUD save (Guard). */
	public const GUARDED = [ self::CURRENCY, self::BASE_REGULAR, self::BASE_SALE ];

	/** Every key the module owns. */
	public const ALL = [ self::CURRENCY, self::BASE_REGULAR, self::BASE_SALE, self::RATE_USED, self::PRICED_AT ];

	/** Post types that carry the keys. */
	public const POST_TYPES = [ 'product', 'product_variation' ];

	/**
	 * Pricing currency code ('' = fixed Toman price, WooCommerce fields authoritative).
	 */
	public static function currency( WC_Product $product ): string {
		return Currencies::normalize_code( self::raw( $product, self::CURRENCY ) );
	}

	/**
	 * Base regular price as a normalised decimal string ('' when unset or invalid).
	 */
	public static function base_regular( WC_Product $product ): string {
		return Calculator::decimal( self::raw( $product, self::BASE_REGULAR ) );
	}

	/**
	 * Base sale price as a normalised decimal string ('' when unset or invalid).
	 */
	public static function base_sale( WC_Product $product ): string {
		return Calculator::decimal( self::raw( $product, self::BASE_SALE ) );
	}

	/**
	 * Rate the stored Toman prices were computed with ('' when never materialised).
	 */
	public static function rate_used( WC_Product $product ): string {
		return Calculator::decimal( self::raw( $product, self::RATE_USED ) );
	}

	/**
	 * Unix time of the last materialisation (0 when never).
	 */
	public static function priced_at( WC_Product $product ): int {
		return (int) self::raw( $product, self::PRICED_AT );
	}

	/**
	 * Whether the product is priced in a base currency.
	 */
	public static function has_currency( WC_Product $product ): bool {
		return '' !== self::currency( $product );
	}

	/**
	 * All five values at once (the REST payload shape).
	 *
	 * @return array{currency:string,base_regular:string,base_sale:string,rate_used:string,priced_at:int}
	 */
	public static function read( WC_Product $product ): array {
		return [
			'currency'     => self::currency( $product ),
			'base_regular' => self::base_regular( $product ),
			'base_sale'    => self::base_sale( $product ),
			'rate_used'    => self::rate_used( $product ),
			'priced_at'    => self::priced_at( $product ),
		];
	}

	/**
	 * Raw meta value in edit context, as a string.
	 */
	public static function raw( WC_Product $product, string $key ): string {
		$value = $product->get_meta( $key, true, 'edit' );
		return is_scalar( $value ) ? (string) $value : '';
	}
}
