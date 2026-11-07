<?php
/**
 * Checkout page enhancement: the city text field becomes a select that follows the selected
 * province (design spec §6.4). Without JavaScript the text field stays; "other city" always
 * reveals it, so no city is ever unreachable.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Context;
use Gadgetim\Core\Support\Html;

/**
 * Prints a JSON data block (#gadgetim-iran-cities) and a small inline script on the checkout
 * page only. The theme may replace it: add_filter( 'gadgetim_checkout_city_script', '__return_false' ).
 */
final class CityScript {

	public const DATA_ID = 'gadgetim-iran-cities';

	public const SCRIPT_ID = 'gadgetim-city-select';

	/**
	 * Attaches the footer printer.
	 */
	public static function register_hooks(): void {
		add_action( 'wp_footer', [ self::class, 'print_assets' ], 20 );
	}

	/**
	 * Whether the enhancement is on (setting checkout.city_select, default on, and the field set active).
	 */
	public static function enabled(): bool {
		if ( ! Fields::active() ) {
			return false;
		}
		$enabled = class_exists( Settings::class ) ? (bool) Settings::get( 'checkout.city_select', true ) : true;

		/**
		 * Filters whether the inline city-select script is printed on the checkout page.
		 *
		 * @param bool $enabled Setting.
		 */
		return (bool) apply_filters( 'gadgetim_checkout_city_script', $enabled );
	}

	/**
	 * wp_footer: data + script on the checkout form page (not on order-received / order-pay).
	 */
	public static function print_assets(): void {
		if ( ! self::enabled() || ! Context::is_storefront_render() ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}
		// The checkout no longer offers a city field (client, 2026-11-08): print nothing then.
		$billing = function_exists( 'WC' ) && WC()->checkout() ? (array) WC()->checkout()->get_checkout_fields( 'billing' ) : [];
		if ( ! isset( $billing['billing_city'] ) ) {
			return;
		}

		$lang = function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';

		/**
		 * Filters the languages whose checkout gets the Persian city list as a select
		 * (English shoppers keep the free text field).
		 *
		 * @param string[] $langs Language codes.
		 */
		$langs = (array) apply_filters( 'gadgetim_checkout_city_script_langs', [ 'fa', 'ar' ] );

		$payload = [
			'enhance' => in_array( $lang, $langs, true ),
			'cities'  => Provinces::cities_map(),
			'i18n'    => [
				'choose' => __( 'Select a city', 'gadgetim-core' ),
				'other'  => __( 'Another city (type its name)', 'gadgetim-core' ),
			],
		];

		echo Html::json_script( self::DATA_ID, $payload ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX flags by Html::json().
		echo '<script id="' . esc_attr( self::SCRIPT_ID ) . '">' . self::script() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static script, no interpolated data.
	}

	/**
	 * The inline script (static: all data comes from the JSON block).
	 */
	public static function script(): string {
		return <<<'JS'
(function () {
	'use strict';
	var node = document.getElementById('gadgetim-iran-cities');
	if (!node) { return; }
	var data;
	try { data = JSON.parse(node.textContent); } catch (e) { return; }
	if (!data || !data.enhance || !data.cities) { return; }
	var OTHER = '__other__';

	function addOption(select, value, text) {
		var option = document.createElement('option');
		option.value = value;
		option.textContent = text;
		select.appendChild(option);
	}

	function labelFor(input) {
		var row = input.closest('.form-row');
		var label = row ? row.querySelector('label') : null;
		return label ? label.textContent.replace('*', '').replace(/\s+/g, ' ').trim() : '';
	}

	function enhance(type) {
		var state = document.getElementById(type + '_state');
		var input = document.getElementById(type + '_city');
		if (!state || !input || input.tagName !== 'INPUT' || input.getAttribute('data-gtm-enhanced')) { return; }
		input.setAttribute('data-gtm-enhanced', '1');

		var select = document.createElement('select');
		select.id = type + '_city_select';
		select.className = 'select gtm-city-select';
		select.setAttribute('aria-label', labelFor(input));
		if (input.getAttribute('aria-required') === 'true') { select.setAttribute('aria-required', 'true'); }
		input.parentNode.insertBefore(select, input);

		function render() {
			var list = data.cities[state.value] || [];
			select.innerHTML = '';
			if (!list.length) {
				select.hidden = true;
				input.hidden = false;
				return;
			}
			addOption(select, '', data.i18n.choose);
			for (var i = 0; i < list.length; i++) { addOption(select, list[i], list[i]); }
			addOption(select, OTHER, data.i18n.other);
			var current = input.value.trim();
			if (current && list.indexOf(current) !== -1) {
				select.value = current;
				input.hidden = true;
			} else if (current) {
				select.value = OTHER;
				input.hidden = false;
			} else {
				select.value = '';
				input.hidden = true;
			}
			select.hidden = false;
		}

		select.addEventListener('change', function () {
			if (select.value === OTHER) {
				input.hidden = false;
				input.value = '';
				input.focus();
			} else {
				input.value = select.value;
				input.hidden = true;
			}
			if (window.jQuery) { window.jQuery(input).trigger('change'); }
		});

		// selectWoo (WooCommerce's enhanced select) fires jQuery events only.
		if (window.jQuery) { window.jQuery(state).on('change', render); } else { state.addEventListener('change', render); }
		render();
	}

	enhance('billing');
	enhance('shipping');
})();
JS;
	}
}
