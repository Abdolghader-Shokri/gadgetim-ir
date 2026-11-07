<?php
/**
 * Iranian checkout field set (architecture §5.6, design spec §6.4).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Normalize;
use Gadgetim\Core\Support\Phone;
use WP_Error;

/**
 * Billing (and shipping), as the client asked (2026-11-08): full name in one input (stored in
 * first_name), mobile (required, normalised to 09xxxxxxxxx), e-mail (optional), postcode
 * (10 digits) and address (textarea, the city is written in it); the order notes field stays.
 * Last name, province, city, company, address line 2 and the country selector are removed; the
 * country is fixed to IR through a hidden input. Only woocommerce_checkout_fields is filtered, so the dashboard's
 * order screens and the account address form are untouched.
 */
final class Fields {

	public const COUNTRY = 'IR';

	public const MOBILE_PATTERN = '/^09\d{9}$/';

	public const POSTCODE_PATTERN = '/^\d{10}$/';

	public const SETS = [ 'billing', 'shipping' ];

	/**
	 * Attaches the hooks.
	 */
	public static function register_hooks(): void {
		add_filter( 'woocommerce_checkout_fields', [ self::class, 'checkout_fields' ], 20 );
		add_filter( 'woocommerce_states', [ self::class, 'states' ], 20 );
		add_filter( 'default_checkout_billing_country', [ self::class, 'default_country' ], 20 );
		add_filter( 'default_checkout_shipping_country', [ self::class, 'default_country' ], 20 );
		add_filter( 'woocommerce_form_field_hidden', [ self::class, 'hidden_country_field' ], 20, 4 );

		add_action( 'woocommerce_checkout_process', [ self::class, 'normalize_posted_input' ], 5 );
		add_filter( 'woocommerce_checkout_posted_data', [ self::class, 'posted_data' ], 20 );
		add_action( 'woocommerce_after_checkout_validation', [ self::class, 'validate' ], 20, 2 );

		// Account address form: same normalisation for the values it shares with the checkout.
		add_filter( 'woocommerce_process_myaccount_field_billing_phone', [ self::class, 'normalize_mobile' ] );
		add_filter( 'woocommerce_process_myaccount_field_billing_postcode', [ self::class, 'normalize_postcode' ] );
		add_filter( 'woocommerce_process_myaccount_field_shipping_postcode', [ self::class, 'normalize_postcode' ] );
	}

	/**
	 * Whether the field set is active (setting checkout.fields_enabled, default on).
	 */
	public static function active(): bool {
		$active = class_exists( Settings::class ) ? (bool) Settings::get( 'checkout.fields_enabled', true ) : true;

		/**
		 * Filters whether the Iranian checkout field set is applied.
		 *
		 * @param bool $active Setting.
		 */
		return (bool) apply_filters( 'gadgetim_checkout_fields_active', $active );
	}

	/**
	 * woocommerce_checkout_fields.
	 *
	 * @param array $fields [ 'billing' => [...], 'shipping' => [...], 'account' => [...], 'order' => [...] ].
	 */
	public static function checkout_fields( $fields ): array {
		if ( ! is_array( $fields ) ) {
			return [];
		}
		if ( ! self::active() ) {
			return $fields;
		}
		foreach ( self::SETS as $set ) {
			if ( ! empty( $fields[ $set ] ) && is_array( $fields[ $set ] ) ) {
				$fields[ $set ] = self::address_set( $fields[ $set ], $set );
			}
		}

		/**
		 * Filters the checkout fields after the Iranian set was applied.
		 *
		 * @param array $fields Checkout fields.
		 */
		return (array) apply_filters( 'gadgetim_checkout_fields', $fields );
	}

	/**
	 * Reshapes one address set. Only fields WooCommerce offers are changed; nothing is invented.
	 *
	 * @param array  $set  Fields of the set.
	 * @param string $type 'billing' or 'shipping'.
	 */
	public static function address_set( array $set, string $type ): array {
		$p = $type . '_';

		unset( $set[ $p . 'company' ], $set[ $p . 'address_2' ], $set[ $p . 'last_name' ], $set[ $p . 'state' ], $set[ $p . 'city' ] );

		if ( isset( $set[ $p . 'country' ] ) ) {
			$set[ $p . 'country' ] = [
				'type'     => 'hidden',
				'label'    => '',
				'required' => false,
				'default'  => self::COUNTRY,
				'class'    => [ 'form-row-wide', 'gtm-field--hidden' ],
				'priority' => 5,
			];
		}

		$defs = [
			'first_name' => [
				'label'             => __( 'Full name', 'gadgetim-core' ),
				'required'          => true,
				'class'             => [ 'form-row-wide', 'gtm-field', 'gtm-field--name' ],
				'priority'          => 10,
				'custom_attributes' => [ 'autocomplete' => 'name' ],
			],
			'phone'      => [
				'type'              => 'tel',
				'label'             => __( 'Mobile number', 'gadgetim-core' ),
				'placeholder'       => gadgetim_digits( '0912 345 6789' ),
				'required'          => true,
				'class'             => [ 'form-row-wide', 'gtm-field', 'gtm-field--mobile' ],
				'validate'          => [],
				'priority'          => 20,
				'custom_attributes' => [
					'inputmode'       => 'tel',
					'dir'             => 'ltr',
					'maxlength'       => '16',
					'data-gtm-mobile' => '1',
				],
			],
			'email'      => [
				'type'              => 'email',
				'label'             => __( 'Email address', 'gadgetim-core' ),
				'required'          => false,
				'class'             => [ 'form-row-wide', 'gtm-field', 'gtm-field--email' ],
				'validate'          => [ 'email' ],
				'priority'          => 30,
				'custom_attributes' => [
					'inputmode' => 'email',
					'dir'       => 'ltr',
				],
			],
			'postcode'   => [
				'type'              => 'text',
				'label'             => __( 'Postcode', 'gadgetim-core' ),
				'placeholder'       => __( '10 digits', 'gadgetim-core' ),
				'required'          => true,
				'class'             => [ 'form-row-wide', 'address-field', 'gtm-field', 'gtm-field--postcode' ],
				'validate'          => [],
				'priority'          => 40,
				'custom_attributes' => [
					'inputmode'         => 'numeric',
					'dir'               => 'ltr',
					'maxlength'         => '10',
					'data-gtm-postcode' => '1',
				],
			],
			'address_1'  => [
				'type'              => 'textarea',
				'label'             => __( 'Address', 'gadgetim-core' ),
				'placeholder'       => __( 'Province, city, street, alley, building number and unit', 'gadgetim-core' ),
				'required'          => true,
				'class'             => [ 'form-row-wide', 'address-field', 'gtm-field', 'gtm-field--address' ],
				'priority'          => 50,
				'custom_attributes' => [ 'rows' => '3' ],
			],
		];

		foreach ( $defs as $key => $def ) {
			$full = $p . $key;
			if ( ! isset( $set[ $full ] ) || ! is_array( $set[ $full ] ) ) {
				continue;
			}
			$set[ $full ] = array_merge( $set[ $full ], $def );
		}

		return $set;
	}

	/**
	 * woocommerce_states: province names from the bundled JSON in the current language on the
	 * storefront (WooCommerce's own labels stay in the dashboard).
	 *
	 * @param array $states [ country => [ code => name ] ].
	 */
	public static function states( $states ): array {
		if ( ! is_array( $states ) ) {
			return [];
		}
		if ( is_admin() || ! self::active() || ! isset( $states[ self::COUNTRY ] ) || ! is_array( $states[ self::COUNTRY ] ) ) {
			return $states;
		}
		$lang = function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
		foreach ( Provinces::names( $lang ) as $code => $name ) {
			$states[ self::COUNTRY ][ $code ] = $name;
		}
		return $states;
	}

	/**
	 * default_checkout_billing_country / default_checkout_shipping_country: Iran.
	 *
	 * @param mixed  $value Default value.
	 * @param string $input Field key.
	 */
	public static function default_country( $value, $input = '' ) {
		return self::active() ? self::COUNTRY : $value;
	}

	/**
	 * woocommerce_form_field_hidden: the country hidden input without the form-row wrapper.
	 *
	 * @param string $field Rendered field.
	 * @param string $key   Field key.
	 * @param array  $args  Field args.
	 * @param mixed  $value Value.
	 */
	public static function hidden_country_field( $field, $key, $args = [], $value = null ): string {
		if ( ! self::active() || ! in_array( $key, [ 'billing_country', 'shipping_country' ], true ) ) {
			return (string) $field;
		}
		$id = is_array( $args ) && ! empty( $args['id'] ) ? (string) $args['id'] : (string) $key;
		return '<input type="hidden" class="input-hidden gtm-country" name="' . esc_attr( (string) $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( self::COUNTRY ) . '" />';
	}

	/**
	 * woocommerce_checkout_process (before WooCommerce reads the form): digits to ASCII, the
	 * mobile number to 09xxxxxxxxx, the postcode to bare digits, names and city to Persian letters.
	 * process_checkout() verified the checkout nonce before firing this action.
	 */
	public static function normalize_posted_input(): void {
		if ( ! self::active() ) {
			return;
		}
		foreach ( self::SETS as $set ) {
			self::rewrite_post( $set . '_phone', [ self::class, 'normalize_mobile' ] );
			self::rewrite_post( $set . '_postcode', [ self::class, 'normalize_postcode' ] );
			self::rewrite_post( $set . '_email', [ self::class, 'normalize_email' ] );
			foreach ( [ 'first_name', 'address_1' ] as $key ) {
				self::rewrite_post( $set . '_' . $key, [ self::class, 'normalize_text' ] );
			}
		}
	}

	/**
	 * woocommerce_checkout_posted_data: the country is always IR; phone/postcode normalised
	 * again for paths that skip woocommerce_checkout_process.
	 *
	 * @param array $data Posted data.
	 */
	public static function posted_data( $data ): array {
		if ( ! is_array( $data ) ) {
			return [];
		}
		if ( ! self::active() ) {
			return $data;
		}
		foreach ( self::SETS as $set ) {
			if ( array_key_exists( $set . '_country', $data ) ) {
				$data[ $set . '_country' ] = self::COUNTRY;
			}
			if ( isset( $data[ $set . '_phone' ] ) && is_string( $data[ $set . '_phone' ] ) ) {
				$data[ $set . '_phone' ] = self::normalize_mobile( $data[ $set . '_phone' ] );
			}
			if ( isset( $data[ $set . '_postcode' ] ) && is_string( $data[ $set . '_postcode' ] ) ) {
				$data[ $set . '_postcode' ] = self::normalize_postcode( $data[ $set . '_postcode' ] );
			}
		}
		return $data;
	}

	/**
	 * woocommerce_after_checkout_validation: mobile 09xxxxxxxxx, postcode 10 digits, province
	 * from the list when a province is posted (the field is no longer offered at checkout). Errors carry the field id so the theme can show them inline.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ): void {
		if ( ! self::active() || ! is_array( $data ) || ! $errors instanceof WP_Error ) {
			return;
		}
		$ship_separately = ! empty( $data['ship_to_different_address'] );

		foreach ( self::SETS as $set ) {
			if ( 'shipping' === $set && ! $ship_separately ) {
				continue;
			}
			$p = $set . '_';

			$phone = isset( $data[ $p . 'phone' ] ) && is_string( $data[ $p . 'phone' ] ) ? $data[ $p . 'phone' ] : '';
			if ( '' !== $phone && ! preg_match( self::MOBILE_PATTERN, $phone ) ) {
				$errors->add(
					$p . 'phone_validation',
					__( 'Please enter a valid Iranian mobile number (09xx xxx xxxx).', 'gadgetim-core' ),
					[ 'id' => $p . 'phone' ]
				);
			}

			$postcode = isset( $data[ $p . 'postcode' ] ) && is_string( $data[ $p . 'postcode' ] ) ? $data[ $p . 'postcode' ] : '';
			if ( '' !== $postcode && ! preg_match( self::POSTCODE_PATTERN, $postcode ) ) {
				$errors->add(
					$p . 'postcode_validation',
					__( 'The postcode must be exactly 10 digits.', 'gadgetim-core' ),
					[ 'id' => $p . 'postcode' ]
				);
			}

			$state = isset( $data[ $p . 'state' ] ) && is_string( $data[ $p . 'state' ] ) ? $data[ $p . 'state' ] : '';
			if ( '' !== $state && ! Provinces::has( $state ) && ! $errors->get_error_messages( $p . 'state_validation' ) ) {
				$errors->add(
					$p . 'state_validation',
					__( 'Please select a province from the list.', 'gadgetim-core' ),
					[ 'id' => $p . 'state' ]
				);
			}
		}
	}

	/**
	 * Mobile number -> 09xxxxxxxxx when it is an Iranian mobile in any notation (Persian digits,
	 * +98, 0098, 98…, spaces); otherwise the ASCII-normalised input so validation can report it.
	 *
	 * @param mixed $raw Posted value.
	 */
	public static function normalize_mobile( $raw ): string {
		$raw  = is_scalar( $raw ) ? (string) $raw : '';
		$e164 = Phone::to_e164_iran( $raw );
		if ( '' !== $e164 && Phone::is_iran_mobile( $e164 ) ) {
			return '0' . substr( $e164, 3 );
		}
		return Phone::normalize( $raw );
	}

	/**
	 * Postcode -> ASCII digits only ("۱۲۳۴۵-۶۷۸۹۰" -> "1234567890").
	 *
	 * @param mixed $raw Posted value.
	 */
	public static function normalize_postcode( $raw ): string {
		$raw = is_scalar( $raw ) ? (string) $raw : '';
		return preg_replace( '/\D+/', '', Digits::to_latin( $raw ) ) ?? '';
	}

	/**
	 * E-mail -> trimmed, ASCII digits.
	 *
	 * @param mixed $raw Posted value.
	 */
	public static function normalize_email( $raw ): string {
		$raw = is_scalar( $raw ) ? (string) $raw : '';
		return trim( Digits::to_latin( $raw ) );
	}

	/**
	 * Free text (names, city, address) -> Persian letters (ي/ك -> ی/ک), NFC, trimmed.
	 *
	 * @param mixed $raw Posted value.
	 */
	public static function normalize_text( $raw ): string {
		$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		if ( '' === $raw ) {
			return '';
		}
		return class_exists( Normalize::class ) ? Normalize::persian( $raw ) : $raw;
	}

	/**
	 * Rewrites one $_POST entry through a normaliser (slashes preserved as WordPress expects).
	 */
	private static function rewrite_post( string $key, callable $normalize ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified woocommerce-process_checkout before this action.
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return;
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised below; WooCommerce sanitises when it reads the field.
		$new = (string) $normalize( $raw );
		if ( $new !== $raw ) {
			$_POST[ $key ] = wp_slash( $new );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
