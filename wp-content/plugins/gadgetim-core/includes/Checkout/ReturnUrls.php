<?php
/**
 * Language-correct gateway round trip (architecture §3.8, D12).
 *
 * Iranian gateways build the return URL inside their callback request (/wc-api/…, ?wc-api=,
 * admin-ajax), where the request carries no language prefix and detection yields Persian.
 * The order's _gtm_lang (written by I18n\Orders) is the single source of truth: the four
 * URL filters rebuild their URL inside Languages::with_lang( $order_lang ), and the request
 * language context is switched to the order's language as soon as the order is known.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Options;
use WC_Order;

/**
 * Everything is a no-op until the I18n module ships Gadgetim\Core\I18n\Languages::with_lang().
 */
final class ReturnUrls {

	public const META_LANG = '_gtm_lang';

	public const LANGUAGES_CLASS = 'Gadgetim\Core\I18n\Languages';

	/** Default request parameters that carry an order id or order key in gateway callbacks. */
	public const DEFAULT_KEYS = 'order_id, order, wc_order, oid, key, order_key, wc_order_key';

	private static bool $rebuilding = false;

	private static ?WC_Order $context_order = null;

	private static bool $request_resolved = false;

	private static ?int $request_order_id = null;

	/**
	 * Attaches the hooks.
	 */
	public static function register_hooks(): void {
		add_filter( 'woocommerce_get_checkout_order_received_url', [ self::class, 'order_received_url' ], 20, 2 );
		add_filter( 'woocommerce_get_cancel_order_url', [ self::class, 'cancel_url' ], 20, 3 );
		add_filter( 'woocommerce_get_cancel_order_url_raw', [ self::class, 'cancel_url_raw' ], 20, 3 );
		add_filter( 'woocommerce_get_checkout_url', [ self::class, 'checkout_url' ], 20 );

		// Context from the order as soon as it is known in callback requests.
		add_action( 'woocommerce_api_request', [ self::class, 'on_api_request' ], 1 );
		add_action( 'woocommerce_pre_payment_complete', [ self::class, 'on_pre_payment_complete' ], 1 );
		add_action( 'woocommerce_order_status_changed', [ self::class, 'on_status_changed' ], 1, 4 );
	}

	/**
	 * woocommerce_get_checkout_order_received_url.
	 *
	 * @param string   $url   URL built in the request language.
	 * @param WC_Order $order Order.
	 */
	public static function order_received_url( $url, $order ): string {
		$url  = (string) $url;
		$lang = self::rebuild_lang( $order );
		if ( '' === $lang ) {
			return $url;
		}
		$rebuilt = self::with_lang(
			$lang,
			static function () use ( $order ): string {
				$received = wc_get_endpoint_url( 'order-received', (string) $order->get_id(), wc_get_checkout_url() );
				return add_query_arg( 'key', $order->get_order_key(), $received );
			}
		);
		return is_string( $rebuilt ) && '' !== $rebuilt ? $rebuilt : $url;
	}

	/**
	 * woocommerce_get_cancel_order_url.
	 *
	 * @param string   $url      URL built in the request language.
	 * @param WC_Order $order    Order.
	 * @param string   $redirect Redirect after cancelling.
	 */
	public static function cancel_url( $url, $order, $redirect = '' ): string {
		$url  = (string) $url;
		$lang = self::rebuild_lang( $order );
		if ( '' === $lang ) {
			return $url;
		}
		$rebuilt = self::with_lang(
			$lang,
			static function () use ( $order, $redirect ): string {
				return wp_nonce_url(
					add_query_arg(
						[
							'cancel_order' => 'true',
							'order'        => $order->get_order_key(),
							'order_id'     => $order->get_id(),
							'redirect'     => (string) $redirect,
						],
						$order->get_cancel_endpoint()
					),
					'woocommerce-cancel_order'
				);
			}
		);
		return is_string( $rebuilt ) && '' !== $rebuilt ? $rebuilt : $url;
	}

	/**
	 * woocommerce_get_cancel_order_url_raw.
	 *
	 * @param string   $url      URL built in the request language.
	 * @param WC_Order $order    Order.
	 * @param string   $redirect Redirect after cancelling.
	 */
	public static function cancel_url_raw( $url, $order, $redirect = '' ): string {
		$url  = (string) $url;
		$lang = self::rebuild_lang( $order );
		if ( '' === $lang ) {
			return $url;
		}
		$rebuilt = self::with_lang(
			$lang,
			static function () use ( $order, $redirect ): string {
				return add_query_arg(
					[
						'cancel_order' => 'true',
						'order'        => $order->get_order_key(),
						'order_id'     => $order->get_id(),
						'redirect'     => (string) $redirect,
						'_wpnonce'     => wp_create_nonce( 'woocommerce-cancel_order' ),
					],
					$order->get_cancel_endpoint()
				);
			}
		);
		return is_string( $rebuilt ) && '' !== $rebuilt ? $rebuilt : $url;
	}

	/**
	 * woocommerce_get_checkout_url: rebuilt when an order is resolvable from the request
	 * context, ?key= or the order-pay query var.
	 *
	 * @param string $url Checkout URL built in the request language.
	 */
	public static function checkout_url( $url ): string {
		$url = (string) $url;
		if ( self::$rebuilding ) {
			return $url;
		}
		$order = self::context_order() ?? self::order_from_request_args();
		$lang  = $order ? self::rebuild_lang( $order ) : '';
		if ( '' === $lang ) {
			return $url;
		}
		$rebuilt = self::with_lang(
			$lang,
			static function (): string {
				$checkout = wc_get_page_permalink( 'checkout' );
				if ( ! is_string( $checkout ) || '' === $checkout ) {
					return '';
				}
				if ( is_ssl() || 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
					$checkout = str_replace( 'http:', 'https:', $checkout );
				}
				return $checkout;
			}
		);
		return is_string( $rebuilt ) && '' !== $rebuilt ? $rebuilt : $url;
	}

	/**
	 * woocommerce_api_request (priority 1, before woocommerce_api_{gateway}): the order from
	 * the gateway's request parameters sets the language context.
	 *
	 * @param string $api_request Lower-cased wc-api value.
	 */
	public static function on_api_request( $api_request ): void {
		$order = self::resolve_request_order();
		if ( $order ) {
			self::apply_order_context( $order, 'wc-api:' . sanitize_key( (string) $api_request ) );
		}
	}

	/**
	 * woocommerce_pre_payment_complete.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_pre_payment_complete( $order_id ): void {
		$order = self::order( (int) $order_id );
		if ( $order ) {
			self::apply_order_context( $order, 'payment_complete' );
		}
	}

	/**
	 * woocommerce_order_status_changed.
	 *
	 * @param int           $order_id Order ID.
	 * @param string        $from     Old status.
	 * @param string        $to       New status.
	 * @param WC_Order|null $order    Order.
	 */
	public static function on_status_changed( $order_id, $from = '', $to = '', $order = null ): void {
		$order = $order instanceof WC_Order ? $order : self::order( (int) $order_id );
		if ( $order ) {
			// Status changes also happen in cron / Action Scheduler runs over many orders: the
			// language is entered only around the listeners, never for the rest of the run.
			self::apply_order_context( $order, 'status:' . sanitize_key( (string) $to ), false );
		}
	}

	/**
	 * Makes an order's language the context of the current request (gateway callbacks and
	 * payment completion; never the dashboard). With $persist = false (status changes) the
	 * language is entered only while gadgetim_order_language_context runs. Returns true when the
	 * context was switched or already matched.
	 */
	public static function apply_order_context( WC_Order $order, string $source = '', bool $persist = true ): bool {
		$lang = self::order_lang( $order );
		if ( '' === $lang ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! $persist ) {
			$fire = static function () use ( $lang, $order, $source ): void {
				/** This action is documented below. */
				do_action( 'gadgetim_order_language_context', $lang, $order, $source );
			};
			if ( $lang !== self::current_lang() && self::can_switch() ) {
				self::with_lang( $lang, $fire );
			} else {
				$fire();
			}
			return true;
		}

		self::$context_order = $order;

		if ( $lang !== self::current_lang() && class_exists( self::LANGUAGES_CLASS ) ) {
			// force() keeps the URL prefix on for the rest of the request whatever the request
			// phase (never restored on purpose); set_current() is the older/simpler setter.
			foreach ( [ 'force', 'set_current' ] as $method ) {
				if ( method_exists( self::LANGUAGES_CLASS, $method ) ) {
					call_user_func( [ self::LANGUAGES_CLASS, $method ], $lang );
					break;
				}
			}
		}

		/**
		 * Fires when an order's language becomes the context of a gateway callback or payment
		 * event. I18n may hook this to switch its request context.
		 *
		 * @param string   $lang   Order language.
		 * @param WC_Order $order  Order.
		 * @param string   $source 'wc-api:{request}', 'payment_complete' or 'status:{to}'.
		 */
		do_action( 'gadgetim_order_language_context', $lang, $order, $source );
		return true;
	}

	/**
	 * The order whose language was applied to this request, if any.
	 */
	public static function context_order(): ?WC_Order {
		return self::$context_order;
	}

	/**
	 * Forgets the request-level state (tests).
	 */
	public static function reset(): void {
		self::$context_order    = null;
		self::$request_resolved = false;
		self::$request_order_id = null;
		self::$rebuilding       = false;
	}

	/**
	 * Resolves an order from the request parameters named in checkout.order_keys (a wc_order_… key,
	 * or a numeric id only when the request also carries that order's key;
	 * see order_from_value()). Memoised per request. Only ever used to pick a language.
	 */
	public static function resolve_request_order(): ?WC_Order {
		if ( self::$request_resolved ) {
			return null !== self::$request_order_id ? self::order( self::$request_order_id ) : null;
		}
		self::$request_resolved = true;

		foreach ( self::request_keys() as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup of the order language; nothing is changed.
			if ( ! isset( $_REQUEST[ $key ] ) || ! is_scalar( $_REQUEST[ $key ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$value = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) );
			$order = self::order_from_value( $value );
			if ( $order ) {
				self::$request_order_id = $order->get_id();
				return $order;
			}
		}
		return null;
	}

	/**
	 * Request parameter names from the setting (default list when unset).
	 *
	 * @return string[]
	 */
	public static function request_keys(): array {
		$raw = class_exists( Settings::class ) ? (string) Settings::get( 'checkout.order_keys', self::DEFAULT_KEYS ) : self::DEFAULT_KEYS;
		if ( '' === trim( $raw ) ) {
			$raw = self::DEFAULT_KEYS;
		}
		$keys = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', $raw ) ?: [] ) );

		/**
		 * Filters the request parameter names that may carry an order id or key in gateway callbacks.
		 *
		 * @param string[] $keys Parameter names.
		 */
		return array_values( array_unique( (array) apply_filters( 'gadgetim_gateway_order_keys', array_values( $keys ) ) ) );
	}

	/**
	 * Settings sanitizer for checkout.order_keys: [a-z0-9_-] names, comma separated.
	 */
	public static function sanitize_keys( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return self::DEFAULT_KEYS;
		}
		$keys = [];
		foreach ( preg_split( '/[\s,;]+/', (string) $value ) ?: [] as $key ) {
			$key = strtolower( trim( $key ) );
			// A name with other characters is junk, not a parameter: dropped rather than mangled.
			if ( '' !== $key && preg_match( '/^[a-z0-9_-]+$/', $key ) ) {
				$keys[ $key ] = true;
			}
		}
		return $keys ? implode( ', ', array_keys( $keys ) ) : self::DEFAULT_KEYS;
	}

	/**
	 * Order language from _gtm_lang, validated against the configured languages ('' when absent).
	 */
	public static function order_lang( WC_Order $order ): string {
		$lang = $order->get_meta( self::META_LANG, true );
		$lang = is_scalar( $lang ) ? sanitize_key( (string) $lang ) : '';
		if ( '' === $lang ) {
			return '';
		}
		return in_array( $lang, Options::language_codes(), true ) ? $lang : '';
	}

	/**
	 * Current request language.
	 */
	public static function current_lang(): string {
		return function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
	}

	/**
	 * Whether the I18n module can switch the language context.
	 */
	public static function can_switch(): bool {
		return class_exists( self::LANGUAGES_CLASS ) && method_exists( self::LANGUAGES_CLASS, 'with_lang' );
	}

	/**
	 * Runs $fn inside the I18n language context (re-entrancy guarded so the inner
	 * wc_get_checkout_url() etc. are not rebuilt again).
	 */
	public static function with_lang( string $lang, callable $fn ): mixed {
		if ( ! self::can_switch() ) {
			return null;
		}
		self::$rebuilding = true;
		try {
			return call_user_func( [ self::LANGUAGES_CLASS, 'with_lang' ], $lang, $fn );
		} finally {
			self::$rebuilding = false;
		}
	}

	/**
	 * The language a URL must be rebuilt in, or '' when nothing has to change.
	 */
	private static function rebuild_lang( $order ): string {
		if ( self::$rebuilding || ! $order instanceof WC_Order || ! self::can_switch() ) {
			return '';
		}
		$lang = self::order_lang( $order );
		return ( '' !== $lang && $lang !== self::current_lang() ) ? $lang : '';
	}

	/**
	 * Order resolvable from ?key= or the order-pay query var (thank-you and pay pages).
	 */
	private static function order_from_request_args(): ?WC_Order {
		// ?key= is WooCommerce's secret order key: only wc_order_… values are looked up, so
		// '/?key=123' cannot reveal whether order 123 exists (or its language).
		$key   = self::request_order_key();
		$order = '' !== $key ? self::order_from_key( $key ) : null;
		if ( $order ) {
			return $order;
		}
		$pay = get_query_var( 'order-pay', '' );
		if ( is_scalar( $pay ) && ctype_digit( (string) $pay ) && '' !== $key ) {
			$order = self::order( (int) $pay );
			return $order && hash_equals( (string) $order->get_order_key(), $key ) ? $order : null;
		}
		return null;
	}

	/**
	 * The wc_order_… key of the request (?key= or any configured gateway parameter), '' when none.
	 */
	private static function request_order_key(): string {
		$names = array_unique( array_merge( [ 'key' ], self::request_keys() ) );
		foreach ( $names as $name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup of the order language.
			if ( isset( $_REQUEST[ $name ] ) && is_scalar( $_REQUEST[ $name ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$value = trim( sanitize_text_field( wp_unslash( (string) $_REQUEST[ $name ] ) ) );
				if ( str_starts_with( $value, 'wc_order_' ) ) {
					return $value;
				}
			}
		}
		return '';
	}

	/**
	 * Order from a wc_order_… key.
	 */
	private static function order_from_key( string $key ): ?WC_Order {
		if ( ! str_starts_with( $key, 'wc_order_' ) || ! function_exists( 'wc_get_order_id_by_order_key' ) ) {
			return null;
		}
		return self::order( (int) wc_get_order_id_by_order_key( $key ) );
	}

	/**
	 * Order from a wc_order_… key, or from a numeric id when the request also carries that
	 * order's key (a bare id is never trusted: it would be an order-existence oracle).
	 */
	private static function order_from_value( string $value ): ?WC_Order {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( ctype_digit( $value ) ) {
			$key   = self::request_order_key();
			$order = '' !== $key ? self::order( (int) $value ) : null;
			return $order && hash_equals( (string) $order->get_order_key(), $key ) ? $order : null;
		}
		return self::order_from_key( $value );
	}

	/**
	 * WC_Order by id (refunds and unknown ids yield null).
	 */
	private static function order( int $order_id ): ?WC_Order {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		return $order instanceof WC_Order ? $order : null;
	}
}
