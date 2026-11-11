<?php
/**
 * Warranty choice at checkout (client, 2026-11-08).
 *
 * Right before the place-order button the shopper must pick one of two options — "I want the
 * warranty" (the product is unboxed and stamped with the warranty seal) or "I do not want the
 * warranty". The first option holds the warranty and return terms, in bold blinking red, and a
 * consent box that is required only with that option. Both are validated on the server. The choice is
 * saved on the order (meta _gtm_warranty = yes|no, _gtm_warranty_terms = time of consent) and
 * shown on the thank-you page, in the customer's account, in the order e-mails and on the
 * dashboard order screen.
 *
 * The block is printed inside #payment, which WooCommerce re-renders on every order-review
 * refresh; the current choice is read back from the refresh request's post_data so it survives.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Settings\Settings;
use WC_Order;
use WP_Error;

/**
 * Checkout field pair: gtm_warranty (radio yes|no) and gtm_warranty_terms (checkbox).
 */
final class Warranty {

	public const FIELD = 'gtm_warranty';

	public const TERMS_FIELD = 'gtm_warranty_terms';

	public const META = '_gtm_warranty';

	public const TERMS_META = '_gtm_warranty_terms';

	public const CHOICES = [ 'yes', 'no' ];

	/**
	 * Attaches the hooks (woocommerce_loaded).
	 */
	public static function register_hooks(): void {
		add_action( 'woocommerce_review_order_before_submit', [ self::class, 'render' ], 5 );
		add_action( 'woocommerce_after_checkout_validation', [ self::class, 'validate' ], 20, 2 );
		add_action( 'woocommerce_checkout_create_order', [ self::class, 'save' ], 20, 2 );
		add_filter( 'woocommerce_get_order_item_totals', [ self::class, 'order_rows' ], 20, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ self::class, 'admin_box' ], 20 );
	}

	/**
	 * Whether the choice is asked (setting checkout.warranty_enabled, default on).
	 */
	public static function active(): bool {
		$active = class_exists( Settings::class ) ? (bool) Settings::get( 'checkout.warranty_enabled', true ) : true;

		/**
		 * Filters whether the warranty choice is asked at checkout.
		 *
		 * @param bool $active Setting.
		 */
		return (bool) apply_filters( 'gadgetim_checkout_warranty_active', $active );
	}

	/**
	 * Option labels.
	 *
	 * @return array<string, array{0: string, 1: string}> choice => [ title, note ].
	 */
	public static function labels(): array {
		return [
			'yes' => [
				__( 'I want the warranty', 'gadgetim-core' ),
				__( 'The product is unboxed and the warranty seal is stamped on it.', 'gadgetim-core' ),
			],
			'no'  => [
				__( 'I do not want the warranty', 'gadgetim-core' ),
				'',
			],
		];
	}

	/**
	 * Short label for a stored choice ('' when unknown).
	 */
	public static function choice_label( string $choice ): string {
		$labels = self::labels();
		return isset( $labels[ $choice ] ) ? $labels[ $choice ][0] : '';
	}

	/**
	 * woocommerce_review_order_before_submit: the two options, the terms and the consent box.
	 */
	public static function render(): void {
		if ( ! self::active() ) {
			return;
		}
		[ $choice, $agreed ] = self::current_state();

		echo '<fieldset class="gtm-warranty" id="gtm-warranty">';
		echo '<legend class="gtm-warranty__t">' . esc_html__( 'Warranty', 'gadgetim-core' ) . ' <abbr class="required" title="' . esc_attr__( 'required', 'gadgetim-core' ) . '">*</abbr></legend>';
		echo '<div class="gtm-warranty__opts" role="radiogroup" aria-required="true">';
		foreach ( self::labels() as $value => [ $title, $note ] ) {
			$id = self::FIELD . '_' . $value;
			echo '<div class="gtm-warranty__o">';
			echo '<label class="gtm-warranty__l" for="' . esc_attr( $id ) . '">';
			echo '<input type="radio" class="input-radio" name="' . esc_attr( self::FIELD ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '"' . checked( $choice, $value, false ) . ' />';
			echo '<span class="gtm-warranty__radio" aria-hidden="true"></span>';
			echo '<span class="gtm-warranty__txt"><strong>' . esc_html( $title ) . '</strong>' . ( '' !== $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</span>';
			echo '</label>';
			if ( 'yes' === $value ) {
				self::render_terms( $agreed );
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</fieldset>';
	}

	/**
	 * The blinking terms and the consent box, inside the "I want the warranty" option. Ticking
	 * the box also selects that option.
	 */
	private static function render_terms( bool $agreed ): void {
		echo '<div class="gtm-warranty__terms" role="note">';
		echo '<p class="gtm-blink">' . esc_html__( 'The warranty covers the authenticity of the product, and the right to return it is kept for the customer for 7 days only.', 'gadgetim-core' ) . '</p>';
		echo '<p class="gtm-blink">' . esc_html__( 'Return conditions: a return is accepted in only two cases — the product is not original, or the product is not in sound condition.', 'gadgetim-core' ) . '</p>';
		echo '</div>';

		echo '<p class="form-row gtm-warranty__agree" id="' . esc_attr( self::TERMS_FIELD ) . '_field">';
		echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="' . esc_attr( self::TERMS_FIELD ) . '">';
		echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="' . esc_attr( self::TERMS_FIELD ) . '" id="' . esc_attr( self::TERMS_FIELD ) . '" value="1"' . checked( $agreed, true, false ) . ' onchange="if(this.checked){document.getElementById(\'' . esc_attr( self::FIELD ) . '_yes\').checked=true;}" />';
		echo '<span>' . esc_html__( 'I have read the warranty and return conditions and I accept them', 'gadgetim-core' ) . '</span>';
		echo '</label></p>';
	}

	/**
	 * The choice and the consent as currently posted: the checkout submission itself, or the
	 * serialized form of an order-review refresh (post_data).
	 *
	 * @return array{0: string, 1: bool}
	 */
	private static function current_state(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only redisplay of the shopper's own unsaved choice; WooCommerce verifies the update-order-review nonce.
		$data = [];
		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			parse_str( wp_unslash( $_POST['post_data'] ), $data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only two whitelisted keys are read below.
		} elseif ( isset( $_POST[ self::FIELD ] ) || isset( $_POST[ self::TERMS_FIELD ] ) ) {
			$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only two whitelisted keys are read below.
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$choice = isset( $data[ self::FIELD ] ) && is_string( $data[ self::FIELD ] ) ? sanitize_key( $data[ self::FIELD ] ) : '';
		$choice = in_array( $choice, self::CHOICES, true ) ? $choice : '';
		$agreed = ! empty( $data[ self::TERMS_FIELD ] );
		return [ $choice, $agreed ];
	}

	/**
	 * woocommerce_after_checkout_validation.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ): void {
		if ( ! self::active() || ! $errors instanceof WP_Error ) {
			return;
		}
		[ $choice, $agreed ] = self::posted();
		if ( '' === $choice ) {
			$errors->add( self::FIELD . '_required', __( 'Please choose whether you want the warranty.', 'gadgetim-core' ), [ 'id' => self::FIELD . '_yes' ] );
		}
		if ( 'yes' === $choice && ! $agreed ) {
			$errors->add( self::TERMS_FIELD . '_required', __( 'Please read the warranty and return conditions and tick the box to accept them.', 'gadgetim-core' ), [ 'id' => self::TERMS_FIELD ] );
		}
	}

	/**
	 * The posted choice of the checkout submission (nonce verified by WC_Checkout).
	 *
	 * @return array{0: string, 1: bool}
	 */
	private static function posted(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified woocommerce-process_checkout.
		$choice = isset( $_POST[ self::FIELD ] ) && is_string( $_POST[ self::FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		$agreed = ! empty( $_POST[ self::TERMS_FIELD ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return [ in_array( $choice, self::CHOICES, true ) ? $choice : '', $agreed ];
	}

	/**
	 * woocommerce_checkout_create_order: stores the choice and the moment of consent.
	 *
	 * @param WC_Order $order Order being created.
	 * @param array    $data  Posted data.
	 */
	public static function save( $order, $data = [] ): void {
		if ( ! self::active() || ! $order instanceof WC_Order ) {
			return;
		}
		[ $choice, $agreed ] = self::posted();
		if ( '' !== $choice ) {
			$order->update_meta_data( self::META, $choice );
		}
		if ( 'yes' === $choice && $agreed ) {
			$order->update_meta_data( self::TERMS_META, gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * woocommerce_get_order_item_totals: a "Warranty" row on the thank-you page, the account's
	 * order view and the order e-mails.
	 *
	 * @param array    $rows  Rows.
	 * @param WC_Order $order Order.
	 */
	public static function order_rows( $rows, $order = null ) {
		if ( ! is_array( $rows ) || ! $order instanceof WC_Order ) {
			return $rows;
		}
		$label = self::choice_label( (string) $order->get_meta( self::META ) );
		if ( '' === $label ) {
			return $rows;
		}
		$row  = [
			'gtm_warranty' => [
				'label' => __( 'Warranty:', 'gadgetim-core' ),
				'value' => esc_html( $label ),
			],
		];
		$keys = array_keys( $rows );
		$at   = array_search( 'payment_method', $keys, true );
		if ( false === $at ) {
			return $rows + $row;
		}
		return array_slice( $rows, 0, (int) $at, true ) + $row + array_slice( $rows, (int) $at, null, true );
	}

	/**
	 * woocommerce_admin_order_data_after_billing_address: the choice on the order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_box( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$choice = (string) $order->get_meta( self::META );
		$label  = self::choice_label( $choice );
		if ( '' === $label ) {
			return;
		}
		$color = 'yes' === $choice ? '#047857' : '#b45309';
		echo '<p class="gtm-warranty-admin"><strong>' . esc_html__( 'Warranty:', 'gadgetim-core' ) . '</strong> <span style="font-weight:700;color:' . esc_attr( $color ) . '">' . esc_html( $label ) . '</span>';
		$at = (string) $order->get_meta( self::TERMS_META );
		if ( '' !== $at ) {
			$time = strtotime( $at . ' UTC' );
			/* translators: %s: date and time the shopper accepted the conditions. */
			echo '<br><small>' . esc_html( sprintf( __( 'Warranty and return conditions accepted on %s', 'gadgetim-core' ), $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : $at ) ) . '</small>';
		}
		echo '</p>';
	}
}
