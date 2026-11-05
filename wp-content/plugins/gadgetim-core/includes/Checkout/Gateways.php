<?php
/**
 * Storefront visibility of payment gateways (port of the old site-custom-payment-gateways
 * plugin, which hid cod and bacs) with a per-gateway toggle in the Checkout tab.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;

/**
 * Setting per gateway: checkout.gateways.{id} (toggle, "offer on the storefront").
 * Unset = the default: offered, except cod and bacs (decisions.md 2026-11-04).
 */
final class Gateways {

	public const DEFAULT_HIDDEN = [ 'cod', 'bacs' ];

	/**
	 * Attaches the storefront filter (priority 100: after gateways' own availability checks).
	 */
	public static function register_hooks(): void {
		add_filter( 'woocommerce_available_payment_gateways', [ self::class, 'available_payment_gateways' ], 100 );
	}

	/**
	 * admin_init: one toggle per installed gateway in the Checkout tab.
	 */
	public static function register_fields(): void {
		if ( ! class_exists( Schema::class ) || ! Plugin::woocommerce_ok() || ! function_exists( 'WC' ) ) {
			return;
		}
		$registry = WC()->payment_gateways();
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'payment_gateways' ) ) {
			return;
		}
		foreach ( (array) $registry->payment_gateways() as $gateway ) {
			if ( ! is_object( $gateway ) || empty( $gateway->id ) ) {
				continue;
			}
			$id    = (string) $gateway->id;
			$title = method_exists( $gateway, 'get_method_title' ) ? (string) $gateway->get_method_title() : $id;
			$on    = isset( $gateway->enabled ) && 'yes' === $gateway->enabled;

			Schema::add_field(
				SettingsTab::TAB,
				SettingsTab::SECTION_GATEWAYS,
				self::path( $id ),
				[
					'type'        => 'toggle',
					/* translators: %s: payment gateway name */
					'label'       => sprintf( __( 'Offer "%s" on the storefront', 'gadgetim-core' ), $title ),
					'description' => $on
						? __( 'Enabled in WooCommerce.', 'gadgetim-core' )
						: __( 'Disabled in WooCommerce → Settings → Payments, so it is not offered regardless of this switch.', 'gadgetim-core' ),
					'default'     => self::default_offered( $id ),
				]
			);
		}
	}

	/**
	 * woocommerce_available_payment_gateways: drops gateways switched off for the storefront.
	 * wp-admin (including admin-ajax) is left alone so manual orders can use any gateway.
	 *
	 * @param array $gateways Available gateways [ id => WC_Payment_Gateway ].
	 */
	public static function available_payment_gateways( $gateways ): array {
		if ( ! is_array( $gateways ) ) {
			return [];
		}
		if ( is_admin() ) {
			return $gateways;
		}
		foreach ( array_keys( $gateways ) as $id ) {
			if ( ! self::is_offered( (string) $id ) ) {
				unset( $gateways[ $id ] );
			}
		}
		return $gateways;
	}

	/**
	 * Whether a gateway is offered on the storefront.
	 */
	public static function is_offered( string $id ): bool {
		$value = class_exists( Settings::class ) ? Settings::get( self::path( $id ), null ) : null;
		if ( null === $value ) {
			$value = self::default_offered( $id );
		}

		/**
		 * Filters whether a gateway is offered on the storefront.
		 *
		 * @param bool   $offered Setting (or default).
		 * @param string $id      Gateway id.
		 */
		return (bool) apply_filters( 'gadgetim_gateway_offered', (bool) $value, $id );
	}

	/**
	 * Default when no setting is stored: everything but cod and bacs.
	 */
	public static function default_offered( string $id ): bool {
		return ! in_array( self::key( $id ), self::DEFAULT_HIDDEN, true );
	}

	/**
	 * Settings path for a gateway id.
	 */
	public static function path( string $id ): string {
		return 'checkout.gateways.' . self::key( $id );
	}

	/**
	 * Settings-safe key for a gateway id (lowercase, [a-z0-9_-]).
	 */
	public static function key( string $id ): string {
		return sanitize_key( $id );
	}
}
