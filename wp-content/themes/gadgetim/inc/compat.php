<?php
/**
 * Fallbacks for the gadgetim-core helpers (architecture §2.3).
 *
 * Plugins load before the theme, so when gadgetim-core is active its functions.php has
 * already defined every helper below and these definitions are skipped. Without the
 * plugin the storefront still renders: Persian digits, plain Toman prices, source
 * term names, settings read straight from the option.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * Admin notice when the core plugin is missing or speaks another API generation.
 */
function gadgetim_core_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( ! defined( 'GADGETIM_CORE_VERSION' ) ) {
		$message = __( 'The Gadgetim theme needs the gadgetim-core plugin. Prices, languages and settings are running on fallbacks until it is activated.', 'gadgetim' );
	} elseif ( ! defined( 'GADGETIM_CORE_API' ) || GADGETIM_CORE_API !== GADGETIM_THEME_CORE_API ) {
		$message = sprintf(
			/* translators: 1: API generation the theme expects, 2: the plugin's API generation */
			__( 'The Gadgetim theme expects gadgetim-core API %1$s but the active plugin provides %2$s. Update both packages together.', 'gadgetim' ),
			(string) GADGETIM_THEME_CORE_API,
			defined( 'GADGETIM_CORE_API' ) ? (string) GADGETIM_CORE_API : '—'
		);
	} else {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'gadgetim_core_notice' );

/**
 * Whether the core plugin is loaded.
 */
function gadgetim_has_core(): bool {
	return defined( 'GADGETIM_CORE_VERSION' );
}

if ( ! function_exists( 'gadgetim_lang' ) ) {
	/**
	 * Current language code from the active locale ('fa' by default).
	 */
	function gadgetim_lang(): string {
		$locale = strtolower( substr( (string) determine_locale(), 0, 2 ) );
		return in_array( $locale, [ 'fa', 'en', 'ar' ], true ) ? $locale : 'fa';
	}
}

if ( ! function_exists( 'gadgetim_lang_dir' ) ) {
	/**
	 * Text direction of a language.
	 */
	function gadgetim_lang_dir( ?string $lang = null ): string {
		$lang = $lang ?? gadgetim_lang();
		return 'en' === $lang ? 'ltr' : 'rtl';
	}
}

/**
 * Digit set replacement (fallback only; the plugin's Digits class protects Latin tokens more carefully).
 */
function gadgetim_compat_digits( string $text, string $lang ): string {
	if ( 'en' === $lang || '' === $text ) {
		return $text;
	}
	$target = 'ar' === $lang
		? [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ]
		: [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
	// Tokens with Latin letters, URLs and e-mails keep their digits (USB-C, 65W, GKM-70).
	return (string) preg_replace_callback(
		'/\S+/u',
		static function ( array $m ) use ( $target ): string {
			$token = $m[0];
			if ( preg_match( '/[A-Za-z@]|:\/\//', $token ) ) {
				return $token;
			}
			return str_replace( [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], $target, $token );
		},
		$text
	);
}

if ( ! function_exists( 'gadgetim_number' ) ) {
	/**
	 * Number with the language's separators and digits.
	 *
	 * @param int|float|string $n        Number.
	 * @param int              $decimals Decimal places.
	 * @param string|null      $lang     Language code.
	 */
	function gadgetim_number( int|float|string $n, int $decimals = 0, ?string $lang = null ): string {
		$lang = $lang ?? gadgetim_lang();
		if ( is_string( $n ) ) {
			$n = (float) str_replace( [ ',', ' ' ], '', $n );
		}
		if ( 'en' === $lang ) {
			return number_format( (float) $n, max( 0, $decimals ), '.', ',' );
		}
		$formatted = number_format( (float) $n, max( 0, $decimals ), "\u{066B}", "\u{066C}" );
		return gadgetim_compat_digits( $formatted, $lang );
	}
}

if ( ! function_exists( 'gadgetim_digits' ) ) {
	/**
	 * Digits of a string in the language's digit set.
	 */
	function gadgetim_digits( string $text, ?string $lang = null ): string {
		return gadgetim_compat_digits( $text, $lang ?? gadgetim_lang() );
	}
}

/**
 * Gregorian to Jalali conversion (fallback only).
 *
 * @return int[] [ year, month, day ]
 */
function gadgetim_compat_jalali( int $gy, int $gm, int $gd ): array {
	$g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy   += intdiv( $days - 1, 365 );
		$days  = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return [ $jy, $jm, $jd ];
}

if ( ! function_exists( 'gadgetim_date' ) ) {
	/**
	 * Display date: Jalali for Persian, Gregorian otherwise (fallback only).
	 *
	 * @param int|string|DateTimeInterface $time  Timestamp, date string or object.
	 * @param string                       $style short | medium | long | datetime | time | iso or a date() pattern.
	 * @param string|null                  $lang  Language code.
	 */
	function gadgetim_date( int|string|DateTimeInterface $time, string $style = 'medium', ?string $lang = null ): string {
		$lang = $lang ?? gadgetim_lang();
		if ( $time instanceof DateTimeInterface ) {
			$timestamp = $time->getTimestamp();
		} elseif ( is_int( $time ) || preg_match( '/^-?\d+$/', (string) $time ) ) {
			$timestamp = (int) $time;
		} else {
			$timestamp = strtotime( (string) $time );
			if ( false === $timestamp ) {
				return '';
			}
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

		if ( 'fa' !== $lang ) {
			$out = wp_date( $format, $timestamp );
			return gadgetim_compat_digits( false === $out ? '' : $out, $lang );
		}

		$months = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
		$days   = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];
		$gy     = (int) wp_date( 'Y', $timestamp );
		$gm     = (int) wp_date( 'n', $timestamp );
		$gd     = (int) wp_date( 'j', $timestamp );
		[ $jy, $jm, $jd ] = gadgetim_compat_jalali( $gy, $gm, $gd );
		$map    = [
			'Y' => (string) $jy,
			'y' => substr( (string) $jy, -2 ),
			'm' => sprintf( '%02d', $jm ),
			'n' => (string) $jm,
			'd' => sprintf( '%02d', $jd ),
			'j' => (string) $jd,
			'F' => $months[ $jm - 1 ],
			'M' => $months[ $jm - 1 ],
			'l' => $days[ (int) wp_date( 'w', $timestamp ) ],
			'D' => $days[ (int) wp_date( 'w', $timestamp ) ],
			'H' => wp_date( 'H', $timestamp ),
			'i' => wp_date( 'i', $timestamp ),
			's' => wp_date( 's', $timestamp ),
		];
		$out    = '';
		$chars  = preg_split( '//u', $format, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$escape = false;
		foreach ( $chars as $char ) {
			if ( $escape ) {
				$out   .= $char;
				$escape = false;
				continue;
			}
			if ( '\\' === $char ) {
				$escape = true;
				continue;
			}
			$out .= $map[ $char ] ?? $char;
		}
		return gadgetim_compat_digits( $out, 'fa' );
	}
}

if ( ! function_exists( 'gadgetim_price' ) ) {
	/**
	 * Price partial (architecture §4.5 markup) without the Pricing module: plain Toman.
	 *
	 * @param WC_Product|int|float|string $product_or_amount Product, product ID or Toman amount.
	 * @param array                       $opts              lang, tag, class, unit, id.
	 */
	function gadgetim_price( $product_or_amount, array $opts = [] ): string {
		$lang    = (string) ( $opts['lang'] ?? gadgetim_lang() );
		$product = $product_or_amount;
		$now     = null;
		$was     = null;
		$from    = false;
		$id      = 0;

		if ( is_int( $product ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product );
		}
		if ( class_exists( 'WC_Product', false ) && $product instanceof WC_Product ) {
			$id = $product->get_id();
			if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
				$min = (string) $product->get_variation_price( 'min' );
				$max = (string) $product->get_variation_price( 'max' );
				if ( '' === $min ) {
					return '';
				}
				$now  = (float) $min;
				$from = $min !== $max;
			} else {
				$price   = (string) $product->get_price();
				$regular = (string) $product->get_regular_price();
				if ( '' === $price && '' === $regular ) {
					return '';
				}
				$now = (float) ( '' !== $price ? $price : $regular );
				if ( $product->is_on_sale() && '' !== $regular && (float) $regular > $now ) {
					$was = (float) $regular;
				}
			}
		} elseif ( is_numeric( $product_or_amount ) ) {
			$now = (float) $product_or_amount;
		} else {
			return '';
		}

		$id    = (int) ( $opts['id'] ?? $id );
		$units = [ 'fa' => 'تومان', 'ar' => 'تومان', 'en' => 'Toman' ];
		$froms = [ 'fa' => 'از', 'ar' => 'من', 'en' => 'from' ];
		$unit  = (string) apply_filters( 'gadgetim_price_unit', $units[ $lang ] ?? $units['en'], $lang );

		$inner = '';
		if ( $from ) {
			$inner .= '<span class="price__from">' . esc_html( $froms[ $lang ] ?? $froms['en'] ) . '</span> ';
		}
		$now_html = '<bdi>' . esc_html( gadgetim_number( $now, 0, $lang ) ) . '</bdi>';
		if ( $opts['unit'] ?? true ) {
			$now_html .= ' <span class="price__unit">' . esc_html( $unit ) . '</span>';
		}
		$inner .= '<span class="price__now">' . $now_html . '</span>';
		if ( null !== $was && $was > 0 ) {
			$inner .= ' <del class="price__was"><bdi>' . esc_html( gadgetim_number( $was, 0, $lang ) ) . '</bdi></del>';
			$off    = (int) round( ( $was - $now ) / $was * 100 );
			if ( $off > 0 ) {
				$percent = 'en' === $lang ? '%' : '٪';
				$inner  .= ' <span class="price__off">' . esc_html( "\u{200E}-" . gadgetim_number( $off, 0, $lang ) . $percent ) . '</span>';
			}
		}
		$tag   = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $opts['tag'] ?? 'p' ) ) ) ?: 'p';
		$class = trim( 'price ' . (string) ( $opts['class'] ?? '' ) . ( $was ? ' price--sale' : '' ) );
		$html  = '<' . $tag . ' class="' . esc_attr( $class ) . '"' . ( $id > 0 ? ' data-id="' . esc_attr( (string) $id ) . '"' : '' ) . '>' . $inner . '</' . $tag . '>';

		return (string) apply_filters( 'gadgetim_price_html', $html, $product_or_amount, $opts, $lang );
	}
}

if ( ! function_exists( 'gadgetim_setting' ) ) {
	/**
	 * A setting by dotted path from the gadgetim_settings option (no schema defaults without the plugin).
	 */
	function gadgetim_setting( string $path, mixed $default = null ): mixed {
		$node = get_option( 'gadgetim_settings', [] );
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $default;
			}
			$node = $node[ $key ];
		}
		return $node;
	}
}

if ( ! function_exists( 'gadgetim_t' ) ) {
	/**
	 * Per-language settings text: current language, else $default, else Persian.
	 */
	function gadgetim_t( string $key, string $default = '' ): string {
		$value = gadgetim_setting( $key );
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
		return (string) apply_filters( 'gadgetim_t', (string) $value, $key, $lang );
	}
}

if ( ! function_exists( 'gadgetim_term_name' ) ) {
	/**
	 * Term name (no translations without the I18n module).
	 */
	function gadgetim_term_name( WP_Term $term ): string {
		return (string) $term->name;
	}
}
