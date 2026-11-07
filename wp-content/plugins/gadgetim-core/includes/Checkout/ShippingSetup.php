<?php
/**
 * Shipping methods the client asked for (2026-11-08): Tipax, Dakapost and Post (Pishtaz).
 *
 * Shipping zones live in the database, and the live site is the master copy, so the methods are
 * created there by a one-time step on the first dashboard page load after the plugin update: an
 * "Iran" zone (or the zone that already covers Iran) gets three Flat rate methods with these
 * titles and cost 0. Prices are then set by the shop owner under WooCommerce → Settings →
 * Shipping. Nothing that already exists is changed or removed, and the step never runs twice
 * (option gadgetim_shipping_setup), so a method the owner deletes stays deleted.
 *
 * The checkout keeps one address (no "ship to a different address"), so the shipping
 * destination is set to the billing address only.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use WC_Shipping_Zone;
use WC_Shipping_Zones;

/**
 * One-time zone/method setup plus storefront titles in English and Arabic.
 */
final class ShippingSetup {

	public const OPTION = 'gadgetim_shipping_setup';

	public const VERSION = 1;

	/**
	 * Method titles as stored (Persian, the shop's default language) and their translations.
	 * Keys are the stored titles.
	 */
	public const METHODS = [
		'تیپاکس'  => [
			'en' => 'Tipax',
			'ar' => 'تيباكس',
		],
		'دکاپست'  => [
			'en' => 'Dakapost',
			'ar' => 'داكابست',
		],
		'پست پیشتاز' => [
			'en' => 'Post (Pishtaz express)',
			'ar' => 'البريد السريع (بيشتاز)',
		],
	];

	/**
	 * Attaches the hooks (woocommerce_loaded).
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', [ self::class, 'maybe_setup' ], 30 );
		add_filter( 'woocommerce_shipping_rate_label', [ self::class, 'translate_label' ], 20 );
	}

	/**
	 * admin_init: runs the setup once for a user who can manage the shop.
	 */
	public static function maybe_setup(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) || ! class_exists( WC_Shipping_Zones::class ) ) {
			return;
		}
		self::setup();
		update_option( self::OPTION, self::VERSION, false );
	}

	/**
	 * Creates the Iran zone when missing and adds the methods whose title is not there yet.
	 *
	 * @return string[] Titles of the methods that were added.
	 */
	public static function setup(): array {
		$zone = self::iran_zone();
		if ( ! $zone ) {
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( 'ایران' );
			$zone->add_location( Fields::COUNTRY, 'country' );
			$zone->save();
		}

		$existing = [];
		foreach ( $zone->get_shipping_methods() as $method ) {
			$existing[] = trim( (string) $method->get_title() );
		}

		$added = [];
		foreach ( array_keys( self::METHODS ) as $title ) {
			if ( in_array( $title, $existing, true ) ) {
				continue;
			}
			$instance_id = (int) $zone->add_shipping_method( 'flat_rate' );
			if ( $instance_id <= 0 ) {
				continue;
			}
			update_option(
				'woocommerce_flat_rate_' . $instance_id . '_settings',
				[
					'title'      => $title,
					'tax_status' => 'none',
					'cost'       => '0',
				]
			);
			$added[] = $title;
		}

		if ( 'billing_only' !== get_option( 'woocommerce_ship_to_destination' ) ) {
			update_option( 'woocommerce_ship_to_destination', 'billing_only' );
		}
		if ( 'disabled' === get_option( 'woocommerce_ship_to_countries' ) ) {
			update_option( 'woocommerce_ship_to_countries', '' );
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		return $added;
	}

	/**
	 * The first zone with Iran as a country location.
	 */
	private static function iran_zone(): ?WC_Shipping_Zone {
		foreach ( WC_Shipping_Zones::get_zones() as $data ) {
			foreach ( (array) ( $data['zone_locations'] ?? [] ) as $location ) {
				if ( isset( $location->type, $location->code ) && 'country' === $location->type && Fields::COUNTRY === $location->code ) {
					return new WC_Shipping_Zone( (int) $data['id'] );
				}
			}
		}
		return null;
	}

	/**
	 * woocommerce_shipping_rate_label: English and Arabic titles for the three methods on the
	 * storefront. Not while an order is being placed, so the order keeps the Persian title the
	 * shop owner reads.
	 *
	 * @param string $label Rate label.
	 */
	public static function translate_label( $label ) {
		if ( ! is_string( $label ) || did_action( 'woocommerce_checkout_process' ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $label;
		}
		$lang  = function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
		$title = trim( $label );
		return isset( self::METHODS[ $title ][ $lang ] ) ? self::METHODS[ $title ][ $lang ] : $label;
	}
}
