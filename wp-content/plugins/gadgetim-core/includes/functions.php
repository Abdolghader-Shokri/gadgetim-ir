<?php
/**
 * Global template helpers called by the theme (architecture §4.5, D9).
 *
 * Every helper degrades gracefully when a module is missing: Persian language,
 * plain Toman price, source term names. The theme's inc/compat.php defines the
 * same names only when this file was never loaded, hence the function_exists guards.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Gregorian;
use Gadgetim\Core\Support\Html;
use Gadgetim\Core\Support\Jalali;
use Gadgetim\Core\Support\Options;

if ( ! function_exists( 'gadgetim_lang' ) ) {
	/**
	 * Current language code ('fa', 'en', 'ar'); 'fa' when the I18n module is absent.
	 */
	function gadgetim_lang(): string {
		static $codes = null;
		$lang = 'fa';
		if ( class_exists( 'Gadgetim\Core\I18n\Languages' ) && method_exists( 'Gadgetim\Core\I18n\Languages', 'current' ) ) {
			$lang = (string) Gadgetim\Core\I18n\Languages::current();
		}
		$codes ??= Options::language_codes();
		return in_array( $lang, $codes, true ) ? $lang : 'fa';
	}
}

if ( ! function_exists( 'gadgetim_lang_dir' ) ) {
	/**
	 * Text direction of a language ('rtl' or 'ltr'); current language by default.
	 */
	function gadgetim_lang_dir( ?string $lang = null ): string {
		$language = Options::language( $lang ?? gadgetim_lang() );
		return ( $language && 'ltr' === ( $language['dir'] ?? 'rtl' ) ) ? 'ltr' : 'rtl';
	}
}

if ( ! function_exists( 'gadgetim_number' ) ) {
	/**
	 * Number in the current language's digits and separators (۲٬۳۴۰٬۰۰۰ / 2,340,000).
	 *
	 * @param int|float|string $n        Number.
	 * @param int              $decimals Decimal places.
	 * @param string|null      $lang     Language code; current language by default.
	 */
	function gadgetim_number( int|float|string $n, int $decimals = 0, ?string $lang = null ): string {
		return Digits::format_number( $n, $lang ?? gadgetim_lang(), $decimals );
	}
}

if ( ! function_exists( 'gadgetim_digits' ) ) {
	/**
	 * Converts the digits of an arbitrary string to the current language's digit set,
	 * leaving Latin tokens (USB-C, 65W), URLs and e-mails untouched.
	 *
	 * @param string      $text Text.
	 * @param string|null $lang Language code; current language by default.
	 */
	function gadgetim_digits( string $text, ?string $lang = null ): string {
		return Digits::for_lang( $text, $lang ?? gadgetim_lang() );
	}
}

if ( ! function_exists( 'gadgetim_date' ) ) {
	/**
	 * Date for display: Jalali for Persian, Gregorian with locale month names for en/ar,
	 * digits in the language's set. Never used for machine output (ISO stays Gregorian).
	 *
	 * @param int|string|DateTimeInterface $time  Unix timestamp, MySQL/ISO date string (site timezone) or date object.
	 * @param string                       $style short | medium | long | datetime | time | iso, or a date() pattern.
	 * @param string|null                  $lang  Language code; current language by default.
	 */
	function gadgetim_date( int|string|DateTimeInterface $time, string $style = 'medium', ?string $lang = null ): string {
		$lang = $lang ?? gadgetim_lang();

		if ( $time instanceof DateTimeInterface ) {
			$timestamp = $time->getTimestamp();
		} elseif ( is_int( $time ) || ( is_string( $time ) && preg_match( '/^-?\d+$/', $time ) ) ) {
			$timestamp = (int) $time;
		} elseif ( is_string( $time ) && '' !== trim( $time ) && '0000-00-00 00:00:00' !== $time ) {
			try {
				$timestamp = ( new DateTimeImmutable( $time, Jalali::timezone() ) )->getTimestamp();
			} catch ( Exception $e ) {
				return '';
			}
		} else {
			return '';
		}

		if ( 'fa' === $lang ) {
			return Jalali::format( $timestamp, $style, 'fa' );
		}

		$formats = [
			'short'    => 'Y/m/d',
			'medium'   => 'j F Y',
			'long'     => 'l, j F Y',
			'datetime' => 'j F Y H:i',
			'time'     => 'H:i',
			'iso'      => 'Y-m-d',
		];
		$format  = $formats[ $style ] ?? $style;

		// Language packs win when the active locale is the requested language (I18n switches it per request);
		// otherwise the built-in English/Arabic names, so an en/ar date never shows Persian month names.
		$language    = Options::language( $lang );
		$lang_locale = (string) ( $language['locale'] ?? $lang );
		if ( str_starts_with( determine_locale(), substr( $lang_locale, 0, 2 ) ) ) {
			$out = wp_date( $format, $timestamp );
			return Digits::for_lang( false === $out ? '' : $out, $lang, false );
		}
		return Gregorian::format( $timestamp, $format, $lang );
	}
}

if ( ! function_exists( 'gadgetim_price' ) ) {
	/**
	 * Price partial HTML for the current language (architecture §4.5).
	 *
	 * Delegates to Pricing\Display::html() when the Pricing module is present; otherwise a
	 * plain Toman price with the same markup and no base-currency hint.
	 *
	 * Options: 'lang', 'tag' (p), 'class', 'unit' (true), 'id' (data-id override),
	 * 'base_hint' (Pricing only), 'from' (variable products, auto).
	 *
	 * @param WC_Product|int|float|string $product_or_amount Product, product ID, or a Toman amount.
	 * @param array                       $opts              Options.
	 */
	function gadgetim_price( $product_or_amount, array $opts = [] ): string {
		if ( class_exists( 'Gadgetim\Core\Pricing\Display' ) && method_exists( 'Gadgetim\Core\Pricing\Display', 'html' ) ) {
			return (string) Gadgetim\Core\Pricing\Display::html( $product_or_amount, $opts );
		}

		$lang    = (string) ( $opts['lang'] ?? gadgetim_lang() );
		$now     = null;
		$was     = null;
		$from    = false;
		$id      = 0;
		$product = $product_or_amount;

		if ( is_int( $product ) && class_exists( 'WC_Product', false ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product );
		}

		if ( class_exists( 'WC_Product', false ) && $product instanceof WC_Product ) {
			$id = $product->get_id();
			if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
				$min = $product->get_variation_price( 'min' );
				$max = $product->get_variation_price( 'max' );
				if ( '' === (string) $min ) {
					return '';
				}
				$now  = (float) $min;
				$from = (string) $min !== (string) $max;
			} else {
				$price   = (string) $product->get_price();
				$regular = (string) $product->get_regular_price();
				$sale    = (string) $product->get_sale_price();
				if ( '' === $price && '' === $regular ) {
					return '';
				}
				// An unsaved product has no derived price yet: fall back to the sale/regular fields.
				if ( '' === $price ) {
					$price = ( $product->is_on_sale() && '' !== $sale ) ? $sale : $regular;
				}
				$now = (float) $price;
				if ( $product->is_on_sale() && '' !== $regular && (float) $regular > $now ) {
					$was = (float) $regular;
				}
			}
		} elseif ( is_numeric( $product_or_amount ) ) {
			$now = (float) Digits::to_latin( (string) $product_or_amount );
		} else {
			return '';
		}

		$id = (int) ( $opts['id'] ?? $id );

		$units = [ 'fa' => 'تومان', 'ar' => 'تومان', 'en' => 'Toman' ];
		/**
		 * Filters the currency unit label shown after prices.
		 *
		 * @param string $unit Label.
		 * @param string $lang Language code.
		 */
		$unit = (string) apply_filters( 'gadgetim_price_unit', $units[ $lang ] ?? $units['en'], $lang );

		$froms = [ 'fa' => 'از', 'ar' => 'من', 'en' => 'from' ];

		$inner = '';
		if ( $from ) {
			$inner .= Html::text( 'span', $froms[ $lang ] ?? $froms['en'], [ 'class' => 'price__from' ] ) . ' ';
		}
		$now_html = Html::bdi( Digits::format_number( $now, $lang ) );
		if ( $opts['unit'] ?? true ) {
			$now_html .= ' ' . Html::text( 'span', $unit, [ 'class' => 'price__unit' ] );
		}
		$inner .= Html::tag( 'span', [ 'class' => 'price__now' ], $now_html );

		if ( null !== $was && $was > 0 ) {
			$inner .= ' ' . Html::tag( 'del', [ 'class' => 'price__was' ], Html::bdi( Digits::format_number( $was, $lang ) ) );
			$off    = (int) round( ( $was - $now ) / $was * 100 );
			if ( $off > 0 ) {
				$percent = 'en' === $lang ? '%' : '٪';
				$inner  .= ' ' . Html::text( 'span', "\u{200E}-" . Digits::format_number( $off, $lang ) . $percent, [ 'class' => 'price__off' ] );
			}
		}

		$html = Html::tag(
			(string) ( $opts['tag'] ?? 'p' ),
			[
				'class'   => Html::classes( 'price', $opts['class'] ?? '', $was ? 'price--sale' : '' ),
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
		return (string) apply_filters( 'gadgetim_price_html', $html, $product_or_amount, $opts, $lang );
	}
}

if ( ! function_exists( 'gadgetim_setting' ) ) {
	/**
	 * A setting by dotted path ('contact.phone', 'views.sample'); schema default when unset.
	 */
	function gadgetim_setting( string $path, mixed $default = null ): mixed {
		if ( ! class_exists( Settings::class ) ) {
			return $default;
		}
		return Settings::get( $path, $default );
	}
}

if ( ! function_exists( 'gadgetim_t' ) ) {
	/**
	 * Per-language settings text ('brand.tagline', 'trust.return_window'): the value for the
	 * current language, else $default, else the Persian value, else ''.
	 */
	function gadgetim_t( string $key, string $default = '' ): string {
		$value = gadgetim_setting( $key, null );
		$lang  = gadgetim_lang();
		if ( is_array( $value ) ) {
			$candidate = $value[ $lang ] ?? '';
			if ( ! is_scalar( $candidate ) || '' === trim( (string) $candidate ) ) {
				$candidate = '' !== $default ? $default : ( $value['fa'] ?? '' );
			}
			$value = $candidate;
		}
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			$value = $default;
		}

		/**
		 * Filters a per-language settings text.
		 *
		 * @param string $value Resolved text.
		 * @param string $key   Settings path.
		 * @param string $lang  Language code.
		 */
		return (string) apply_filters( 'gadgetim_t', (string) $value, $key, $lang );
	}
}

if ( ! function_exists( 'gadgetim_term_name' ) ) {
	/**
	 * Translated term name for the current language (I18n\TermTranslations), else the source name.
	 * Term objects are never altered.
	 */
	function gadgetim_term_name( WP_Term $term ): string {
		if ( class_exists( 'Gadgetim\Core\I18n\TermTranslations' ) && method_exists( 'Gadgetim\Core\I18n\TermTranslations', 'name' ) ) {
			$name = Gadgetim\Core\I18n\TermTranslations::name( $term );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		return (string) $term->name;
	}
}
