<?php
/**
 * The "Checkout" settings tab (manage_woocommerce): field set switches, gateway toggles,
 * gateway round-trip parameters.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Schema;

/**
 * Settings paths: checkout.fields_enabled, checkout.city_select, checkout.warranty_enabled,
 * checkout.gateways.{id}, checkout.order_keys.
 */
final class SettingsTab {

	public const TAB = 'checkout';

	public const SECTION_FIELDS = 'fields';

	public const SECTION_GATEWAYS = 'gateways';

	public const SECTION_RETURNS = 'returns';

	public const SECTION_TRACKING = 'tracking';

	/**
	 * init (priority 5): registers the tab. Gateway toggles are added per installed gateway
	 * on admin_init by Gateways::register_fields().
	 */
	public static function register_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}

		Schema::add_tab(
			self::TAB,
			__( 'Checkout', 'gadgetim-core' ),
			Capabilities::SHOP,
			[
				self::SECTION_FIELDS   => [
					'label'       => __( 'Checkout fields', 'gadgetim-core' ),
					'description' => __( 'The short Iranian field set: full name in one field, mobile number (required, 09xx xxx xxxx, digits normalised), optional e-mail, a 10-digit postcode, the address and the order notes. Last name, province, city, company, the second address line and the country selector are removed; the country is always Iran.', 'gadgetim-core' ),
					'order'       => 10,
					'fields'      => [
						'checkout.fields_enabled' => [
							'type'    => 'toggle',
							'label'   => __( 'Use the Iranian checkout fields', 'gadgetim-core' ),
							'default' => true,
						],
						'checkout.warranty_enabled' => [
							'type'        => 'toggle',
							'label'       => __( 'Ask for the warranty choice before the place-order button', 'gadgetim-core' ),
							'description' => __( 'The shopper must choose "I want the warranty" or "I do not want the warranty"; wanting it also requires ticking that they have read the warranty and return terms. The choice is shown on the order, in the e-mails and in the dashboard.', 'gadgetim-core' ),
							'default'     => true,
						],
						'checkout.city_select'    => [
							'type'        => 'toggle',
							'label'       => __( 'Offer a city list that follows the selected province', 'gadgetim-core' ),
							'description' => __( 'Progressive enhancement on the checkout page: without JavaScript the city stays a text field, and a city that is not in the list can always be typed.', 'gadgetim-core' ),
							'default'     => true,
						],
					],
				],
				self::SECTION_GATEWAYS => [
					'label'       => __( 'Payment gateways on the storefront', 'gadgetim-core' ),
					'description' => __( 'A gateway switched off here is hidden from shoppers (checkout, pay-for-order links, Store API) but stays available for orders created in the dashboard. Gateways disabled under WooCommerce → Settings → Payments are never offered. Cash on delivery and bank transfer are off by default.', 'gadgetim-core' ),
					'order'       => 20,
					'fields'      => [],
				],
				self::SECTION_RETURNS  => [
					'label'       => __( 'Gateway round trip', 'gadgetim-core' ),
					'description' => __( 'Iranian gateways build their return URLs inside their own callback request, where the language of the shop is not in the URL. The order\'s language is restored from these parameters so the thank-you and cancel pages open in the language the order was placed in.', 'gadgetim-core' ),
					'order'       => 30,
					'fields'      => [
						'checkout.order_keys' => [
							'type'        => 'text',
							'label'       => __( 'Order parameters in gateway callbacks', 'gadgetim-core' ),
							'description' => __( 'Comma-separated names of query or form parameters that carry the order ID or the order key, e.g. "order_id, wc_order, key".', 'gadgetim-core' ),
							'default'     => ReturnUrls::DEFAULT_KEYS,
							'attrs'       => [ 'dir' => 'ltr' ],
							'sanitize'    => [ ReturnUrls::class, 'sanitize_keys' ],
						],
					],
				],
				self::SECTION_TRACKING => [
					'label'       => __( 'Shipment tracking', 'gadgetim-core' ),
					'description' => __( 'The tracking page of each shipping company. When the shop saves a tracking code on an order (the "Shipment" box on the order screen), the customer sees the company, the code and a button to this page in their account, on the order tracking page and in the e-mails. Leave a field empty when the company has no tracking page: the customer then gets the code only.', 'gadgetim-core' ),
					'order'       => 25,
					'fields'      => self::tracking_fields(),
				],
			],
			[ 'order' => 40 ]
		);
	}

	/**
	 * One address field per shipping company of Shipment::carriers().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function tracking_fields(): array {
		$fields = [];
		foreach ( Shipment::carriers() as $key => $carrier ) {
			$fields[ Shipment::url_setting( $key ) ] = [
				'type'    => 'url',
				/* translators: %s: shipping company */
				'label'   => sprintf( __( 'Tracking page of %s', 'gadgetim-core' ), $carrier['fa'] ),
				'default' => $carrier['url'],
				'attrs'   => [ 'dir' => 'ltr' ],
			];
		}
		return $fields;
	}
}
