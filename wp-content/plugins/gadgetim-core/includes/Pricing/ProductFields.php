<?php
/**
 * Product and variation admin fields for base-currency pricing (architecture §4.4).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Support\Digits;
use WC_Admin_Meta_Boxes;
use WC_Product;
use WP_Post;

/**
 * Product → General gets "Pricing basis" (Fixed Toman / AED / …), base regular and base sale.
 * Variations get the same three fields in their pricing section. The meta is read on
 * woocommerce_admin_process_product_object / woocommerce_admin_process_variation_object, which
 * run BEFORE the save, so the materialiser sees the final values in the same save. With a
 * currency chosen the Toman inputs are read-only with a live preview (Admin/assets/product-fields.js);
 * the server ignores Toman edits for currency products because the materialiser recomputes them.
 */
final class ProductFields {

	public const BULK_ACTION = 'gtm_set_currency';

	public const SCRIPT = 'gadgetim-pricing-product';
	public const STYLE  = 'gadgetim-pricing-admin';

	/**
	 * woocommerce_product_options_general_product_data.
	 */
	public static function render_product_fields(): void {
		global $product_object, $post;

		$product = $product_object instanceof WC_Product ? $product_object : null;
		if ( null === $product && $post instanceof WP_Post && function_exists( 'wc_get_product' ) ) {
			$candidate = wc_get_product( $post->ID );
			$product   = $candidate instanceof WC_Product ? $candidate : null;
		}
		$code = $product ? Meta::currency( $product ) : '';

		echo '<div class="options_group gtm-pricing show_if_simple show_if_external show_if_variable" data-gtm-pricing="product">';
		woocommerce_wp_select(
			[
				'id'            => Meta::CURRENCY,
				'name'          => Meta::CURRENCY,
				'value'         => $code,
				'label'         => __( 'Pricing basis', 'gadgetim-core' ),
				'options'       => self::currency_options( $code ),
				'class'         => 'select short gtm-pricing__currency',
				'wrapper_class' => 'gtm-pricing__field',
				'desc_tip'      => true,
				'description'   => __( 'With a currency, the Toman prices are computed from the base prices at every save and whenever the rate changes. "Fixed Toman price" keeps the WooCommerce price fields authoritative. For variable products the basis is set per variation (bulk action: "Set pricing currency for all variations").', 'gadgetim-core' ),
			]
		);
		echo '</div>';

		echo '<div class="options_group gtm-pricing gtm-pricing--base show_if_simple show_if_external" data-gtm-pricing="base">';
		woocommerce_wp_text_input(
			[
				'id'                => Meta::BASE_REGULAR,
				'name'              => Meta::BASE_REGULAR,
				'value'             => $product ? Meta::base_regular( $product ) : '',
				'label'             => __( 'Base regular price', 'gadgetim-core' ),
				'placeholder'       => '30',
				'class'             => 'short gtm-pricing__base-regular',
				'wrapper_class'     => 'gtm-pricing__field',
				'custom_attributes' => [
					'inputmode'    => 'decimal',
					'dir'          => 'ltr',
					'autocomplete' => 'off',
				],
				'desc_tip'          => true,
				'description'       => __( 'In the selected currency, for example 30 for 30 AED. Persian digits are accepted.', 'gadgetim-core' ),
			]
		);
		woocommerce_wp_text_input(
			[
				'id'                => Meta::BASE_SALE,
				'name'              => Meta::BASE_SALE,
				'value'             => $product ? Meta::base_sale( $product ) : '',
				'label'             => __( 'Base sale price', 'gadgetim-core' ),
				'placeholder'       => '',
				'class'             => 'short gtm-pricing__base-sale',
				'wrapper_class'     => 'gtm-pricing__field',
				'custom_attributes' => [
					'inputmode'    => 'decimal',
					'dir'          => 'ltr',
					'autocomplete' => 'off',
				],
				'desc_tip'          => true,
				'description'       => __( 'Leave empty for no sale. Emptying this field is the only way to end a sale on a currency-priced product; the Toman sale field is read-only.', 'gadgetim-core' ),
			]
		);
		echo '<p class="form-field gtm-pricing__field gtm-pricing__note"><label>' . esc_html__( 'Computed Toman price', 'gadgetim-core' ) . '</label><span class="description gtm-pricing__note-text">' . esc_html( self::note( $product ) ) . '</span></p>';
		echo '</div>';
	}

	/**
	 * woocommerce_variation_options_pricing.
	 *
	 * @param mixed $loop           Row index.
	 * @param mixed $variation_data Legacy data array (unused).
	 * @param mixed $variation      WP_Post of the variation.
	 */
	public static function render_variation_fields( $loop, $variation_data = [], $variation = null ): void {
		$loop   = (int) $loop;
		$object = null;
		if ( $variation instanceof WP_Post && function_exists( 'wc_get_product' ) ) {
			$candidate = wc_get_product( $variation->ID );
			$object    = $candidate instanceof WC_Product ? $candidate : null;
		}
		$code = $object ? Meta::currency( $object ) : '';

		echo '<div class="gtm-pricing gtm-pricing--variation" data-gtm-pricing="variation">';
		woocommerce_wp_select(
			[
				'id'            => 'variable_gtm_price_currency_' . $loop,
				'name'          => 'variable_gtm_price_currency[' . $loop . ']',
				'value'         => $code,
				'label'         => __( 'Pricing basis', 'gadgetim-core' ),
				'options'       => self::currency_options( $code ),
				'class'         => 'gtm-pricing__currency',
				'wrapper_class' => 'form-row form-row-full gtm-pricing__field',
			]
		);
		woocommerce_wp_text_input(
			[
				'id'                => 'variable_gtm_base_regular_price_' . $loop,
				'name'              => 'variable_gtm_base_regular_price[' . $loop . ']',
				'value'             => $object ? Meta::base_regular( $object ) : '',
				'label'             => __( 'Base regular price', 'gadgetim-core' ),
				'placeholder'       => '30',
				'class'             => 'gtm-pricing__base-regular',
				'wrapper_class'     => 'form-row form-row-first gtm-pricing__field',
				'custom_attributes' => [
					'inputmode'    => 'decimal',
					'dir'          => 'ltr',
					'autocomplete' => 'off',
				],
			]
		);
		woocommerce_wp_text_input(
			[
				'id'                => 'variable_gtm_base_sale_price_' . $loop,
				'name'              => 'variable_gtm_base_sale_price[' . $loop . ']',
				'value'             => $object ? Meta::base_sale( $object ) : '',
				'label'             => __( 'Base sale price', 'gadgetim-core' ),
				'class'             => 'gtm-pricing__base-sale',
				'wrapper_class'     => 'form-row form-row-last gtm-pricing__field',
				'custom_attributes' => [
					'inputmode'    => 'decimal',
					'dir'          => 'ltr',
					'autocomplete' => 'off',
				],
			]
		);
		echo '<p class="form-row form-row-full gtm-pricing__note"><span class="description gtm-pricing__note-text">' . esc_html( self::note( $object ) ) . '</span></p>';
		echo '</div>';
	}

	/**
	 * woocommerce_admin_process_product_object (before the save).
	 *
	 * @param mixed $product WC_Product.
	 */
	public static function read_post_fields( $product ): void {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC_Admin_Meta_Boxes verified woocommerce_meta_nonce before WooCommerce fires this hook.
		if ( ! isset( $_POST[ Meta::CURRENCY ] ) ) {
			return;
		}
		if ( $product->get_id() && ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		self::apply_posted(
			$product,
			sanitize_text_field( wp_unslash( (string) $_POST[ Meta::CURRENCY ] ) ),
			isset( $_POST[ Meta::BASE_REGULAR ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Meta::BASE_REGULAR ] ) ) : null,
			isset( $_POST[ Meta::BASE_SALE ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Meta::BASE_SALE ] ) ) : null
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * woocommerce_admin_process_variation_object (before the variation save).
	 *
	 * @param mixed $variation WC_Product_Variation.
	 * @param mixed $i         Row index.
	 */
	public static function read_variation_fields( $variation, $i = 0 ): void {
		if ( ! $variation instanceof WC_Product ) {
			return;
		}
		$i = (int) $i;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the save-variations AJAX nonce or woocommerce_meta_nonce before firing this hook.
		if ( ! isset( $_POST['variable_gtm_price_currency'] ) || ! is_array( $_POST['variable_gtm_price_currency'] ) || ! isset( $_POST['variable_gtm_price_currency'][ $i ] ) ) {
			return;
		}
		if ( $variation->get_id() && ! current_user_can( 'edit_post', $variation->get_id() ) ) {
			return;
		}
		$regular = isset( $_POST['variable_gtm_base_regular_price'][ $i ] ) ? sanitize_text_field( wp_unslash( (string) $_POST['variable_gtm_base_regular_price'][ $i ] ) ) : null;
		$sale    = isset( $_POST['variable_gtm_base_sale_price'][ $i ] ) ? sanitize_text_field( wp_unslash( (string) $_POST['variable_gtm_base_sale_price'][ $i ] ) ) : null;
		self::apply_posted( $variation, sanitize_text_field( wp_unslash( (string) $_POST['variable_gtm_price_currency'][ $i ] ) ), $regular, $sale );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Validates posted values and writes them onto the object (no save). Shared by products and variations.
	 *
	 * @param WC_Product  $product  Object being saved.
	 * @param string      $code_raw Posted currency code.
	 * @param string|null $regular  Posted base regular price (null = field absent).
	 * @param string|null $sale     Posted base sale price (null = field absent).
	 */
	public static function apply_posted( WC_Product $product, string $code_raw, ?string $regular, ?string $sale ): void {
		$current = Meta::currency( $product );
		$code    = Currencies::normalize_code( $code_raw );
		if ( '' !== $code && ! Currencies::enabled( $code ) && $code !== $current ) {
			// Only enabled currencies can be newly assigned.
			$code = '';
		}
		$product->update_meta_data( Meta::CURRENCY, $code );

		if ( null !== $regular ) {
			$regular_clean = Calculator::decimal( $regular );
			if ( '' !== trim( $regular ) && '' === $regular_clean ) {
				self::error( __( 'The base regular price is not a valid number and was not saved.', 'gadgetim-core' ) );
				$regular_clean = Meta::base_regular( $product );
			}
			$product->update_meta_data( Meta::BASE_REGULAR, $regular_clean );
		}
		if ( null !== $sale ) {
			$sale_clean = Calculator::decimal( $sale );
			if ( '' !== trim( $sale ) && '' === $sale_clean ) {
				self::error( __( 'The base sale price is not a valid number and was not saved.', 'gadgetim-core' ) );
			}
			$regular_now = Meta::base_regular( $product );
			if ( '' !== $sale_clean && '' !== $regular_now && (float) $sale_clean >= (float) $regular_now ) {
				self::error( __( 'The base sale price must be lower than the base regular price; the sale was not saved.', 'gadgetim-core' ) );
				$sale_clean = '';
			}
			$product->update_meta_data( Meta::BASE_SALE, $sale_clean );
		}

		if ( '' !== $code && '' === Meta::base_regular( $product ) ) {
			self::error( __( 'A base regular price is required when a pricing currency is selected; the product has no computed price yet.', 'gadgetim-core' ) );
		}
	}

	/**
	 * woocommerce_variable_product_bulk_edit_actions: the "Set pricing currency" bulk action.
	 */
	public static function bulk_edit_option(): void {
		echo '<optgroup label="' . esc_attr__( 'Gadgetim pricing', 'gadgetim-core' ) . '">';
		echo '<option value="' . esc_attr( self::BULK_ACTION ) . '">' . esc_html__( 'Set pricing currency for all variations', 'gadgetim-core' ) . '</option>';
		echo '</optgroup>';
	}

	/**
	 * woocommerce_bulk_edit_variations_default: applies the bulk action (WooCommerce verified the
	 * bulk-edit-variations nonce and edit_products before firing it).
	 *
	 * @param mixed $bulk_action Action id.
	 * @param mixed $data        Posted data ('value' = currency code, 'gtm_cancel' when the prompt was cancelled).
	 * @param mixed $product_id  Parent product ID.
	 * @param mixed $variations  Variation IDs.
	 */
	public static function bulk_set_currency( $bulk_action, $data, $product_id, $variations ): void {
		if ( self::BULK_ACTION !== $bulk_action || ! is_array( $data ) || ! current_user_can( 'edit_post', (int) $product_id ) ) {
			return;
		}
		if ( ! empty( $data['gtm_cancel'] ) ) {
			return;
		}
		$code = Currencies::normalize_code( is_scalar( $data['value'] ?? null ) ? (string) $data['value'] : '' );
		if ( '' !== $code && ! Currencies::enabled( $code ) ) {
			return;
		}
		foreach ( (array) $variations as $variation_id ) {
			$variation = wc_get_product( (int) $variation_id );
			if ( ! $variation instanceof WC_Product || (int) $variation->get_parent_id() !== (int) $product_id ) {
				continue;
			}
			$variation->update_meta_data( Meta::CURRENCY, $code );
			$variation->save();
		}
	}

	/**
	 * admin_enqueue_scripts: preview script and styles on the product edit screen only.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( self::STYLE, Plugin::url( 'includes/Pricing/Admin/assets/pricing-admin.css' ), [], GADGETIM_CORE_VERSION );
		wp_enqueue_script( self::SCRIPT, Plugin::url( 'includes/Pricing/Admin/assets/product-fields.js' ), [ 'jquery' ], GADGETIM_CORE_VERSION, true );
		wp_localize_script(
			self::SCRIPT,
			'gadgetimPricing',
			[
				'currencies' => Currencies::js_config(),
				'unit'       => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : 'Toman',
				'bulkAction' => self::BULK_ACTION,
				'i18n'       => [
					/* translators: 1: Toman price, 2: unit, 3: base amount with currency, 4: rate, 5: rounding mode */
					'computed'   => __( '%1$s %2$s = %3$s × %4$s (%5$s)', 'gadgetim-core' ),
					'fixed'      => __( 'Fixed Toman price: the WooCommerce price fields are used as entered.', 'gadgetim-core' ),
					'noBase'     => __( 'Enter a base regular price to compute the Toman price.', 'gadgetim-core' ),
					'saleTooBig' => __( 'The base sale price must be lower than the base regular price.', 'gadgetim-core' ),
					'readonly'   => __( 'Computed from the base price; edit the base fields instead.', 'gadgetim-core' ),
					'bulkPrompt' => __( 'Currency code for all variations (empty = fixed Toman price):', 'gadgetim-core' ),
					'modes'      => Calculator::mode_labels(),
				],
			]
		);
	}

	/**
	 * Select options: fixed Toman + enabled currencies (+ the product's own disabled code, marked).
	 *
	 * @return array<string,string>
	 */
	public static function currency_options( string $current = '' ): array {
		$options = [ '' => __( 'Fixed Toman price', 'gadgetim-core' ) ];
		foreach ( Currencies::all( true ) as $code => $currency ) {
			$currency         = Currencies::applied( $currency );
			$options[ $code ] = sprintf(
				/* translators: 1: currency code, 2: currency name, 3: rate */
				__( '%1$s — %2$s (%3$s Toman)', 'gadgetim-core' ),
				$code,
				Currencies::label( $currency, 'fa' ),
				number_format_i18n( (float) $currency['rate'] )
			);
		}
		if ( '' !== $current && ! isset( $options[ $current ] ) ) {
			/* translators: %s: currency code */
			$options[ $current ] = sprintf( __( '%s (disabled — last prices kept)', 'gadgetim-core' ), $current );
		}
		return $options;
	}

	/**
	 * Server-rendered preview line (the script keeps it current while editing).
	 */
	public static function note( ?WC_Product $product ): string {
		if ( null === $product ) {
			return __( 'Fixed Toman price: the WooCommerce price fields are used as entered.', 'gadgetim-core' );
		}
		$code = Meta::currency( $product );
		if ( '' === $code ) {
			return __( 'Fixed Toman price: the WooCommerce price fields are used as entered.', 'gadgetim-core' );
		}
		$currency = Currencies::get( $code );
		if ( null === $currency || empty( $currency['enabled'] ) ) {
			/* translators: %s: currency code */
			return sprintf( __( 'Currency %s is disabled: the last computed Toman prices are kept until it is enabled again.', 'gadgetim-core' ), $code );
		}
		$regular = Meta::base_regular( $product );
		if ( '' === $regular ) {
			return __( 'Enter a base regular price to compute the Toman price.', 'gadgetim-core' );
		}
		$pending  = Currencies::has_pending( $currency );
		$currency = Currencies::applied( $currency );
		$toman    = Calculator::to_toman( $regular, $currency );
		$modes    = Calculator::mode_labels();
		$line     = sprintf(
			/* translators: 1: Toman price, 2: unit, 3: base amount with currency, 4: rate, 5: rounding mode */
			__( '%1$s %2$s = %3$s × %4$s (%5$s)', 'gadgetim-core' ),
			Digits::format_number( $toman, 'en' ),
			function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : 'Toman',
			Currencies::format_amount( $regular, $currency ),
			Digits::format_number( Currencies::rate_string( $currency ), 'en' ),
			$modes[ (string) $currency['rounding_mode'] ] ?? (string) $currency['rounding_mode']
		);
		if ( $pending ) {
			$line .= ' ' . __( '(A new rate is saved but not applied yet: "Apply rates" on the Currencies page recomputes every product.)', 'gadgetim-core' );
		}
		return $line;
	}

	private static function error( string $message ): void {
		if ( class_exists( 'WC_Admin_Meta_Boxes' ) && method_exists( 'WC_Admin_Meta_Boxes', 'add_error' ) ) {
			WC_Admin_Meta_Boxes::add_error( $message );
		}
	}
}
