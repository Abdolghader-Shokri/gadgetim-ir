<?php
/**
 * REST exposure of the pricing module (architecture §4.7).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Support\Options;
use Gadgetim\Core\Support\RateLimit;
use WC_Product;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Two surfaces, nothing else:
 *  - wc/v3 products and variations carry `gadgetim_pricing { currency, base_regular, base_sale,
 *    rate_used, priced_at }`; writes are read in woocommerce_rest_pre_insert_* (before the CRUD
 *    save, so the single save materialises) and rejected with 400 when invalid.
 *  - gadgetim/v1/currencies: GET public and cacheable (enabled currencies only), PUT/PATCH/POST
 *    …/currencies/{code} for manage_options (core's REST cookie nonce or an application password).
 * The base fields are never registered with show_in_rest, so wp/v2 ignores them.
 */
final class Rest {

	public const NAMESPACE = 'gadgetim/v1';
	public const FIELD     = 'gadgetim_pricing';

	/** Public endpoint: requests per minute per hashed IP. */
	public const RATE_LIMIT = 60;

	/**
	 * rest_api_init.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/currencies',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ self::class, 'get_currencies' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'lang' => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
				'schema' => [ self::class, 'currency_schema' ],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/currencies/(?P<code>[A-Za-z]{3})',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ self::class, 'get_currency' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'code' => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lang' => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
				[
					'methods'             => 'PUT, PATCH, POST',
					'callback'            => [ self::class, 'update_currency' ],
					'permission_callback' => [ self::class, 'can_manage' ],
					'args'                => self::update_args(),
				],
				'schema' => [ self::class, 'currency_schema' ],
			]
		);
	}

	/**
	 * permission_callback of the write route.
	 *
	 * @return true|WP_Error
	 */
	public static function can_manage(): bool|WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new WP_Error( 'gadgetim_rest_forbidden', __( 'You are not allowed to change currencies.', 'gadgetim-core' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * GET /gadgetim/v1/currencies — enabled currencies, public, cacheable for 5 minutes.
	 */
	public static function get_currencies( WP_REST_Request $request ): WP_REST_Response {
		if ( ! RateLimit::allow( 'currencies', self::RATE_LIMIT, MINUTE_IN_SECONDS ) ) {
			return self::too_many_requests();
		}
		$lang  = self::lang( $request );
		$items = [];
		foreach ( Currencies::all( true ) as $currency ) {
			$items[] = self::public_item( $currency, $lang );
		}
		$response = new WP_REST_Response( $items );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		// Labels follow ?lang (in the URL) or the X-Gadgetim-Lang header.
		$response->header( 'Vary', 'X-Gadgetim-Lang' );
		$response->header( 'X-Gadgetim-Prices-Version', Options::prices_version() );
		return $response;
	}

	/**
	 * GET /gadgetim/v1/currencies/{code}.
	 */
	public static function get_currency( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! RateLimit::allow( 'currencies', self::RATE_LIMIT, MINUTE_IN_SECONDS ) ) {
			return self::too_many_requests();
		}
		$code     = Currencies::normalize_code( (string) $request['code'] );
		$currency = Currencies::get( $code );
		$manager  = current_user_can( 'manage_options' );
		if ( null === $currency || ( ! $manager && empty( $currency['enabled'] ) ) ) {
			return new WP_Error( 'gadgetim_rest_currency_missing', __( 'Unknown currency.', 'gadgetim-core' ), [ 'status' => 404 ] );
		}
		$response = new WP_REST_Response( $manager ? self::admin_item( $currency ) : self::public_item( $currency, self::lang( $request ) ) );
		$response->header( 'Cache-Control', $manager ? 'no-store' : 'public, max-age=300' );
		$response->header( 'Vary', 'X-Gadgetim-Lang' );
		return $response;
	}

	/**
	 * PUT/PATCH/POST /gadgetim/v1/currencies/{code} — saves the definition; `apply: true` also
	 * starts the reprice job and returns its progress.
	 */
	public static function update_currency( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$code = Currencies::normalize_code( (string) $request['code'] );
		if ( '' === $code ) {
			return new WP_Error( 'gadgetim_rest_currency_code', __( 'A currency code is three Latin letters (ISO 4217), for example AED.', 'gadgetim-core' ), [ 'status' => 400 ] );
		}
		$data = [];
		foreach ( [ 'rate', 'rounding_mode', 'rounding_step', 'decimals', 'enabled', 'symbol', 'label' ] as $key ) {
			if ( $request->has_param( $key ) ) {
				$data[ $key ] = $request->get_param( $key );
			}
		}
		$apply = $request->has_param( 'apply' ) && rest_sanitize_boolean( $request->get_param( 'apply' ) );
		if ( ! $data && ! $apply ) {
			return new WP_Error( 'gadgetim_rest_nothing', __( 'Nothing to update.', 'gadgetim-core' ), [ 'status' => 400 ] );
		}

		$currency = $data ? Currencies::save( $code, $data, get_current_user_id() ) : Currencies::get( $code );
		if ( is_wp_error( $currency ) ) {
			$currency->add_data( [ 'status' => 400 ] );
			return $currency;
		}
		if ( null === $currency ) {
			return new WP_Error( 'gadgetim_rest_currency_missing', __( 'Unknown currency.', 'gadgetim-core' ), [ 'status' => 404 ] );
		}

		$out = self::admin_item( $currency );
		if ( $apply ) {
			$job = Reprice::reprice_currency( $code, get_current_user_id() );
			if ( is_wp_error( $job ) ) {
				$out['job']         = null;
				$out['apply_error'] = $job->get_error_message();
			} else {
				$out['job']      = $job;
				$out['progress'] = Reprice::progress( $job );
			}
		}
		$response = new WP_REST_Response( $out );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * woocommerce_rest_prepare_product_object / _product_variation_object: adds gadgetim_pricing.
	 *
	 * @param mixed $response WP_REST_Response.
	 * @param mixed $object   WC_Product.
	 * @param mixed $request  WP_REST_Request.
	 * @return mixed
	 */
	public static function prepare_product( $response, $object, $request = null ) {
		if ( $response instanceof WP_REST_Response && $object instanceof WC_Product ) {
			$data = $response->get_data();
			if ( is_array( $data ) ) {
				$data[ self::FIELD ] = self::payload( $object );
				$response->set_data( $data );
			}
		}
		return $response;
	}

	/**
	 * woocommerce_rest_pre_insert_product_object / _product_variation_object: reads gadgetim_pricing
	 * onto the object before WooCommerce saves it (one save, materialised by Materializer).
	 *
	 * @param mixed $product  WC_Product or WP_Error.
	 * @param mixed $request  WP_REST_Request.
	 * @param bool  $creating Creating or updating.
	 * @return mixed WC_Product, or WP_Error (400) when the payload is invalid.
	 */
	public static function pre_insert( $product, $request = null, $creating = false ) {
		if ( ! $product instanceof WC_Product || ! $request instanceof WP_REST_Request ) {
			return $product;
		}
		$payload = $request->get_param( self::FIELD );
		if ( ! is_array( $payload ) ) {
			return $product;
		}
		$clean = self::sanitize_payload( $payload, $product );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		foreach ( $clean as $key => $value ) {
			$product->update_meta_data( $key, $value );
		}
		return $product;
	}

	/**
	 * The gadgetim_pricing object of a product.
	 *
	 * @return array{currency:string,base_regular:string,base_sale:string,rate_used:string,priced_at:?int}
	 */
	public static function payload( WC_Product $product ): array {
		$meta = Meta::read( $product );
		return [
			'currency'     => $meta['currency'],
			'base_regular' => $meta['base_regular'],
			'base_sale'    => $meta['base_sale'],
			'rate_used'    => $meta['rate_used'],
			'priced_at'    => $meta['priced_at'] > 0 ? $meta['priced_at'] : null,
		];
	}

	/**
	 * Validates a gadgetim_pricing write: [ meta_key => value ] for the keys present, or WP_Error (400).
	 *
	 * @param array           $payload currency, base_regular, base_sale (any subset).
	 * @param WC_Product|null $product Current object (for the keys the payload leaves out).
	 */
	public static function sanitize_payload( array $payload, ?WC_Product $product = null ): array|WP_Error {
		$out = [];

		if ( array_key_exists( 'currency', $payload ) ) {
			$raw  = is_scalar( $payload['currency'] ) ? trim( (string) $payload['currency'] ) : '';
			$code = Currencies::normalize_code( $raw );
			if ( '' !== $raw && '' === $code ) {
				return new WP_Error( 'gadgetim_rest_currency', __( 'gadgetim_pricing.currency must be a three-letter currency code or empty.', 'gadgetim-core' ), [ 'status' => 400 ] );
			}
			$already = null !== $product && Meta::currency( $product ) === $code;
			if ( '' !== $code && ! Currencies::enabled( $code ) && ! $already ) {
				/* translators: %s: currency code */
				return new WP_Error( 'gadgetim_rest_currency_disabled', sprintf( __( 'Currency %s is unknown or disabled.', 'gadgetim-core' ), $code ), [ 'status' => 400 ] );
			}
			$out[ Meta::CURRENCY ] = $code;
		}

		foreach ( [ 'base_regular' => Meta::BASE_REGULAR, 'base_sale' => Meta::BASE_SALE ] as $field => $key ) {
			if ( ! array_key_exists( $field, $payload ) ) {
				continue;
			}
			$raw = $payload[ $field ];
			if ( null === $raw || '' === $raw ) {
				$out[ $key ] = '';
				continue;
			}
			$value = Calculator::decimal( $raw );
			if ( '' === $value ) {
				/* translators: %s: field name */
				return new WP_Error( 'gadgetim_rest_decimal', sprintf( __( 'gadgetim_pricing.%s must be a non-negative decimal number.', 'gadgetim-core' ), $field ), [ 'status' => 400 ] );
			}
			$out[ $key ] = $value;
		}

		$currency = array_key_exists( Meta::CURRENCY, $out ) ? $out[ Meta::CURRENCY ] : ( $product ? Meta::currency( $product ) : '' );
		$regular  = array_key_exists( Meta::BASE_REGULAR, $out ) ? $out[ Meta::BASE_REGULAR ] : ( $product ? Meta::base_regular( $product ) : '' );
		$sale     = array_key_exists( Meta::BASE_SALE, $out ) ? $out[ Meta::BASE_SALE ] : ( $product ? Meta::base_sale( $product ) : '' );

		if ( '' !== $sale && '' !== $regular && (float) $sale >= (float) $regular ) {
			return new WP_Error( 'gadgetim_rest_sale', __( 'gadgetim_pricing.base_sale must be lower than base_regular.', 'gadgetim-core' ), [ 'status' => 400 ] );
		}
		if ( '' !== $currency && '' === $regular && $out ) {
			return new WP_Error( 'gadgetim_rest_base_required', __( 'gadgetim_pricing.base_regular is required when a currency is set.', 'gadgetim-core' ), [ 'status' => 400 ] );
		}
		return $out;
	}

	/**
	 * JSON schema of the gadgetim_pricing object (for documentation and clients).
	 */
	public static function field_schema(): array {
		return [
			'description' => __( 'Base-currency pricing. With a currency, the Toman prices are computed from the base prices on every save; regular_price/sale_price writes are then ignored.', 'gadgetim-core' ),
			'type'        => 'object',
			'context'     => [ 'view', 'edit' ],
			'properties'  => [
				'currency'     => [
					'type'        => 'string',
					'description' => __( 'Three-letter currency code, or empty for a fixed Toman price.', 'gadgetim-core' ),
				],
				'base_regular' => [
					'type'        => 'string',
					'description' => __( 'Regular price in the base currency, as entered.', 'gadgetim-core' ),
				],
				'base_sale'    => [
					'type'        => 'string',
					'description' => __( 'Sale price in the base currency, or empty.', 'gadgetim-core' ),
				],
				'rate_used'    => [
					'type'        => 'string',
					'readonly'    => true,
					'description' => __( 'Rate the stored Toman prices were computed with.', 'gadgetim-core' ),
				],
				'priced_at'    => [
					'type'        => [ 'integer', 'null' ],
					'readonly'    => true,
					'description' => __( 'Unix time of the last computation.', 'gadgetim-core' ),
				],
			],
		];
	}

	/**
	 * Schema of a currency item (route schema callback).
	 */
	public static function currency_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'gadgetim_currency',
			'type'       => 'object',
			'properties' => [
				'code'          => [ 'type' => 'string' ],
				'symbol'        => [ 'type' => 'string' ],
				'label'         => [ 'type' => 'string' ],
				'labels'        => [ 'type' => 'object' ],
				'rate'          => [ 'type' => 'number' ],
				'rounding_mode' => [
					'type' => 'string',
					'enum' => Calculator::MODES,
				],
				'rounding_step' => [ 'type' => 'integer' ],
				'decimals'      => [ 'type' => 'integer' ],
				'enabled'       => [ 'type' => 'boolean' ],
				'updated_at'    => [ 'type' => [ 'string', 'null' ] ],
			],
		];
	}

	private static function update_args(): array {
		return [
			'code'          => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'rate'          => [ 'type' => [ 'number', 'string' ] ],
			'rounding_mode' => [
				'type' => 'string',
				'enum' => Calculator::MODES,
			],
			'rounding_step' => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'decimals'      => [
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 4,
			],
			'enabled'       => [ 'type' => 'boolean' ],
			'symbol'        => [ 'type' => 'string' ],
			'label'         => [ 'type' => [ 'object', 'string' ] ],
			'apply'         => [
				'type'        => 'boolean',
				'description' => __( 'Also recompute the Toman prices of every product in this currency.', 'gadgetim-core' ),
			],
		];
	}

	private static function public_item( array $currency, string $lang ): array {
		// Public figures describe the live prices, i.e. the applied snapshot.
		$currency = Currencies::applied( $currency );
		return [
			'code'          => (string) $currency['code'],
			'symbol'        => (string) $currency['symbol'],
			'label'         => Currencies::label( $currency, $lang ),
			'labels'        => (array) $currency['label'],
			'rate'          => $currency['rate'],
			'rounding_mode' => (string) $currency['rounding_mode'],
			'rounding_step' => (int) $currency['rounding_step'],
			'decimals'      => (int) $currency['decimals'],
			'updated_at'    => $currency['updated_at'] > 0 ? gmdate( 'c', (int) $currency['updated_at'] ) : null,
		];
	}

	private static function admin_item( array $currency ): array {
		$item               = self::public_item( $currency, 'fa' );
		$item['enabled']    = ! empty( $currency['enabled'] );
		$item['updated_by'] = (int) $currency['updated_by'];
		$item['products']   = Products::count_with_currency( (string) $currency['code'] );
		$item['unapplied']  = Currencies::has_unapplied_change( (string) $currency['code'] );
		$item['saved']      = [
			'rate'          => $currency['rate'],
			'rounding_mode' => (string) $currency['rounding_mode'],
			'rounding_step' => (int) $currency['rounding_step'],
		];
		return $item;
	}

	private static function lang( WP_REST_Request $request ): string {
		$lang = (string) ( $request->get_param( 'lang' ) ?? '' );
		if ( '' !== $lang && in_array( $lang, Options::language_codes(), true ) ) {
			return $lang;
		}
		return function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
	}

	private static function too_many_requests(): WP_REST_Response {
		$response = new WP_REST_Response(
			[
				'code'    => 'gadgetim_rate_limited',
				'message' => __( 'Too many requests. Try again in a moment.', 'gadgetim-core' ),
			],
			429
		);
		$response->header( 'Retry-After', (string) max( 1, RateLimit::retry_after( 'currencies', self::RATE_LIMIT, MINUTE_IN_SECONDS ) ) );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
