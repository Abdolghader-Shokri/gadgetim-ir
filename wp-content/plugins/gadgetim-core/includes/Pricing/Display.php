<?php
/**
 * Price partial markup and the one guarded WooCommerce price filter (architecture §4.5, D9).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Context;
use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Html;
use WC_Product;

/**
 * Display::html() is what gadgetim_price() delegates to:
 *
 * <p class="price price--sale" data-id="4208">
 *   <span class="price__now"><bdi>۲٬۳۴۰٬۰۰۰</bdi> <span class="price__unit">تومان</span></span>
 *   <del class="price__was"><bdi>۲٬۷۳۰٬۰۰۰</bdi></del>
 *   <span class="price__off">‎-۱۴٪</span>
 *   <span class="price__base" dir="ltr" title="AED 30 × 78,000 Toman" data-cur="AED">≈ AED 30</span>
 * </p>
 *
 * Digits follow the language's digit set (Support\Digits). The old price and the sticker appear
 * only during an active sale and only from the configured discount thresholds. No global
 * wc_price / number_format_i18n filters exist; woocommerce_get_price_html is wrapped solely
 * for storefront renders.
 */
final class Display {

	public const DEFAULT_STRIKE_THRESHOLD  = 10;
	public const DEFAULT_STICKER_THRESHOLD = 15;

	/** Built-in words for storefronts whose translation files are not installed yet. */
	private const WORDS = [
		'from' => [
			'fa' => 'از',
			'ar' => 'من',
			'en' => 'from',
		],
	];

	private const UNITS = [
		'fa' => 'تومان',
		'ar' => 'تومان',
		'en' => 'Toman',
	];

	/**
	 * init: the "Price display" section of the Catalog settings tab (manage_woocommerce).
	 */
	public static function register_settings(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		Schema::add_section(
			'catalog',
			'pricing',
			__( 'Price display', 'gadgetim-core' ),
			__( 'How the theme price partial shows sales and the base-currency hint. Prices are always stored and sold in Toman; the currencies themselves live under Gadgetim → Currencies.', 'gadgetim-core' ),
			20
		);
		Schema::add_field(
			'catalog',
			'pricing',
			'pricing.strike_threshold',
			[
				'type'        => 'number',
				'label'       => __( 'Show the old price from (% off)', 'gadgetim-core' ),
				'description' => __( 'The struck-through old price appears only when the discount reaches this percentage.', 'gadgetim-core' ),
				'min'         => 0,
				'max'         => 100,
				'step'        => 1,
				'default'     => self::DEFAULT_STRIKE_THRESHOLD,
			]
		);
		Schema::add_field(
			'catalog',
			'pricing',
			'pricing.sticker_threshold',
			[
				'type'        => 'number',
				'label'       => __( 'Show the discount sticker from (% off)', 'gadgetim-core' ),
				'description' => __( 'The "-14%" sticker appears only when the discount reaches this percentage.', 'gadgetim-core' ),
				'min'         => 0,
				'max'         => 100,
				'step'        => 1,
				'default'     => self::DEFAULT_STICKER_THRESHOLD,
			]
		);
		Schema::add_field(
			'catalog',
			'pricing',
			'pricing.base_hint_single',
			[
				'type'        => 'toggle',
				'label'       => __( 'Base-currency hint on the product page', 'gadgetim-core' ),
				'description' => __( 'Shows "≈ AED 30" next to the Toman price of currency-priced products.', 'gadgetim-core' ),
				'default'     => true,
			]
		);
		Schema::add_field(
			'catalog',
			'pricing',
			'pricing.base_hint_cards',
			[
				'type'    => 'toggle',
				'label'   => __( 'Base-currency hint on product cards', 'gadgetim-core' ),
				'default' => false,
			]
		);
	}

	/**
	 * Price partial HTML.
	 *
	 * Options: 'lang' (current language), 'tag' ('p'), 'class', 'unit' (true), 'id' (data-id override),
	 * 'base_hint' (true/false; null = by 'context' and settings), 'context' ('single' | 'card').
	 *
	 * @param WC_Product|int|float|string $subject Product, product ID, or a Toman amount.
	 * @param array                       $opts    Options.
	 */
	public static function html( $subject, array $opts = [] ): string {
		$lang    = (string) ( $opts['lang'] ?? self::current_lang() );
		$product = null;
		$now     = null;
		$was     = null;
		$from    = false;
		$on_sale = false;
		$id      = 0;

		$has_wc = class_exists( 'WC_Product', false );
		if ( $has_wc && $subject instanceof WC_Product ) {
			$product = $subject;
		} elseif ( $has_wc && is_int( $subject ) && function_exists( 'wc_get_product' ) ) {
			$candidate = wc_get_product( $subject );
			if ( $candidate instanceof WC_Product ) {
				$product = $candidate;
			}
		}

		if ( $product instanceof WC_Product ) {
			$id = $product->get_id();
			if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_prices' ) ) {
				$prices = (array) $product->get_variation_prices( true );
				$list   = array_map( 'floatval', array_filter( (array) ( $prices['price'] ?? [] ), 'is_numeric' ) );
				if ( ! $list ) {
					return '';
				}
				$min  = min( $list );
				$max  = max( $list );
				$now  = $min;
				$from = $min < $max;
				if ( $product->is_on_sale() ) {
					$regulars    = array_map( 'floatval', array_filter( (array) ( $prices['regular_price'] ?? [] ), 'is_numeric' ) );
					$min_regular = $regulars ? min( $regulars ) : 0.0;
					if ( $min_regular > $now ) {
						$was     = $min_regular;
						$on_sale = true;
					}
				}
			} else {
				$price   = (string) $product->get_price();
				$regular = (string) $product->get_regular_price();
				$sale    = (string) $product->get_sale_price();
				if ( '' === $price && '' === $regular ) {
					return '';
				}
				$on_sale = $product->is_on_sale();
				if ( '' === $price ) {
					// An unsaved product has no derived price yet.
					$price = ( $on_sale && '' !== $sale ) ? $sale : $regular;
				}
				$now = (float) $price;
				if ( $on_sale && '' !== $regular && (float) $regular > $now ) {
					$was = (float) $regular;
				} else {
					$on_sale = false;
				}
			}
		} elseif ( is_int( $subject ) || is_float( $subject ) ) {
			$now = (float) $subject;
		} elseif ( is_string( $subject ) && '' !== Calculator::decimal( $subject ) ) {
			$now = (float) Calculator::decimal( $subject );
		} else {
			return '';
		}

		$id   = (int) ( $opts['id'] ?? $id );
		$unit = self::unit( $lang );

		$inner = '';
		if ( $from ) {
			$inner .= Html::text( 'span', self::word( 'from', $lang ), [ 'class' => 'price__from' ] ) . ' ';
		}
		$now_html = Html::bdi( Digits::format_number( $now, $lang ) );
		if ( $opts['unit'] ?? true ) {
			$now_html .= ' ' . Html::text( 'span', $unit, [ 'class' => 'price__unit' ] );
		}
		$inner .= Html::tag( 'span', [ 'class' => 'price__now' ], $now_html );

		if ( null !== $was && $was > $now ) {
			$off        = Calculator::percent_off( $was, $now );
			$thresholds = self::thresholds();
			if ( $off >= $thresholds['strike'] ) {
				$inner .= ' ' . Html::tag( 'del', [ 'class' => 'price__was' ], Html::bdi( Digits::format_number( $was, $lang ) ) );
			}
			if ( $off > 0 && $off >= $thresholds['sticker'] ) {
				$percent = 'en' === $lang ? '%' : "\u{066A}";
				$inner  .= ' ' . Html::text( 'span', "\u{200E}-" . Digits::format_number( $off, $lang ) . $percent, [ 'class' => 'price__off' ] );
			}
		}

		if ( $product instanceof WC_Product && self::wants_base_hint( $opts ) ) {
			$hint = self::base_hint( $product, $lang, $on_sale );
			if ( '' !== $hint ) {
				$inner .= ' ' . $hint;
			}
		}

		$html = Html::tag(
			(string) ( $opts['tag'] ?? 'p' ),
			[
				'class'   => Html::classes( 'price', $opts['class'] ?? '', $on_sale ? 'price--sale' : '', $from ? 'price--from' : '' ),
				'data-id' => $id > 0 ? (string) $id : null,
			],
			$inner
		);

		/**
		 * Filters the price partial HTML.
		 *
		 * @param string $html    Markup.
		 * @param mixed  $subject Product, ID or amount as passed in.
		 * @param array  $opts    Options.
		 * @param string $lang    Language code.
		 */
		return (string) apply_filters( 'gadgetim_price_html', $html, $subject, $opts, $lang );
	}

	/**
	 * woocommerce_get_price_html (priority 20): digit conversion for the few WooCommerce-owned
	 * places the theme does not template, only while rendering the storefront.
	 *
	 * @param mixed $html    WooCommerce price HTML.
	 * @param mixed $product Product (unused).
	 * @return mixed
	 */
	public static function filter_price_html( $html, $product = null ) {
		if ( ! is_string( $html ) || '' === $html || ! Context::is_storefront_render() ) {
			return $html;
		}
		return Digits::html( $html, self::current_lang() );
	}

	/**
	 * WooCommerce filters that carry wc_price() HTML on the cart, checkout, order-received and
	 * account order screens.
	 */
	public const AMOUNT_FILTERS = [
		'woocommerce_cart_item_price',
		'woocommerce_cart_item_subtotal',
		'woocommerce_cart_subtotal',
		'woocommerce_cart_totals_order_total_html',
		'woocommerce_cart_totals_coupon_html',
		'woocommerce_cart_totals_fee_html',
		'woocommerce_cart_shipping_method_full_label',
		'woocommerce_get_formatted_order_total',
		'woocommerce_order_formatted_line_subtotal',
		'woocommerce_order_subtotal_to_display',
		'woocommerce_order_shipping_to_display',
		'woocommerce_order_discount_to_display',
	];

	/**
	 * Cart/checkout/order amount filters: same digit conversion as filter_price_html(), only while
	 * rendering the storefront (Store API JSON, e-mails and wp-admin keep ASCII digits).
	 *
	 * @param mixed $html Amount HTML.
	 * @return mixed
	 */
	public static function filter_amount_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || ! Context::is_storefront_render() ) {
			return $html;
		}
		return Digits::html( $html, self::current_lang() );
	}

	/**
	 * Unit label after prices ("تومان" / "Toman"); filter gadgetim_price_unit.
	 */
	public static function unit( string $lang ): string {
		/**
		 * Filters the currency unit label shown after prices.
		 *
		 * @param string $unit Label.
		 * @param string $lang Language code.
		 */
		return (string) apply_filters( 'gadgetim_price_unit', self::UNITS[ $lang ] ?? self::UNITS['en'], $lang );
	}

	/**
	 * Discount thresholds from the settings: [ 'strike' => int, 'sticker' => int ].
	 *
	 * @return array{strike:int,sticker:int}
	 */
	public static function thresholds(): array {
		return [
			'strike'  => self::setting_int( 'pricing.strike_threshold', self::DEFAULT_STRIKE_THRESHOLD ),
			'sticker' => self::setting_int( 'pricing.sticker_threshold', self::DEFAULT_STICKER_THRESHOLD ),
		];
	}

	/**
	 * "≈ AED 30" hint for a currency product ('' for fixed-Toman products).
	 */
	public static function base_hint( WC_Product $product, string $lang, bool $on_sale = false ): string {
		if ( Handover::active() ) {
			return ''; // The currency pricing plugin owns rates now; Gadgetim's stored rate would be stale.
		}
		$code = Meta::currency( $product );
		if ( '' === $code ) {
			return '';
		}
		$currency = Currencies::get( $code );
		if ( null === $currency ) {
			return '';
		}
		$amount = Meta::base_regular( $product );
		if ( $on_sale && '' !== Meta::base_sale( $product ) ) {
			$amount = Meta::base_sale( $product );
		}
		$text = Currencies::format_amount( $amount, $currency );
		if ( '' === $text ) {
			return '';
		}
		$rate  = Meta::rate_used( $product );
		$rate  = '' !== $rate ? $rate : Currencies::rate_string( $currency );
		$title = sprintf(
			/* translators: 1: base amount with currency (AED 30), 2: rate in Toman */
			__( '%1$s × %2$s Toman', 'gadgetim-core' ),
			$text,
			Digits::format_number( $rate, 'en' )
		);
		return Html::text(
			'span',
			"\u{2248} " . $text,
			[
				'class'    => 'price__base',
				'dir'      => 'ltr',
				'title'    => $title,
				'data-cur' => $code,
			]
		);
	}

	/**
	 * Whether the hint is wanted: explicit option, else the setting for the context.
	 */
	private static function wants_base_hint( array $opts ): bool {
		if ( array_key_exists( 'base_hint', $opts ) && null !== $opts['base_hint'] ) {
			return (bool) $opts['base_hint'];
		}
		$card = 'card' === (string) ( $opts['context'] ?? 'single' );
		return self::setting_bool( $card ? 'pricing.base_hint_cards' : 'pricing.base_hint_single', ! $card );
	}

	/**
	 * Translated word with a built-in fallback for languages whose .l10n.php is not installed.
	 */
	private static function word( string $key, string $lang ): string {
		$source     = self::WORDS[ $key ]['en'];
		$translated = match ( $key ) {
			'from'  => _x( 'from', 'prefix of the lowest variation price', 'gadgetim-core' ),
			default => $source,
		};
		if ( $translated !== $source || 'en' === $lang ) {
			return $translated;
		}
		return self::WORDS[ $key ][ $lang ] ?? $source;
	}

	private static function current_lang(): string {
		return function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
	}

	private static function setting_int( string $path, int $default ): int {
		if ( ! class_exists( Settings::class ) ) {
			return $default;
		}
		$value = Settings::get( $path, $default );
		return is_numeric( $value ) ? max( 0, (int) $value ) : $default;
	}

	private static function setting_bool( string $path, bool $default ): bool {
		if ( ! class_exists( Settings::class ) ) {
			return $default;
		}
		$value = Settings::get( $path, $default );
		return is_scalar( $value ) ? rest_sanitize_boolean( $value ) : $default;
	}
}
