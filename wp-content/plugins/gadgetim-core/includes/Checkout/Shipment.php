<?php
/**
 * Shipment tracking on orders (owner, 2026-11-27).
 *
 * The shopper picks the shipping method at checkout (Tipax, Dekapost or Post, see ShippingSetup).
 * Once the parcel is on its way, the shop opens the order in the dashboard, picks the shipping
 * company in the "Shipment" box and types the tracking code. Saving the order:
 * - stores the company and the code on the order (meta below, HPOS and posts storage alike);
 * - tells the customer, unless that box is unticked: a customer note in the order's language,
 *   which WooCommerce e-mails ("Customer note") and lists under "Order updates" in the account;
 * - moves a "Processing" order to "Shipped" (wc-shipped), unless that box is unticked or the
 *   status was changed to something else in the same save.
 * The account's order view, the order tracking page and the customer e-mails then show the
 * company, the code and a link to the company's tracking page (Gadgetim → Checkout → Shipment
 * tracking). The dashboard's order list gets a "Tracking" column.
 *
 * Meta: _gtm_ship_carrier (a key of carriers() or "other"), _gtm_ship_carrier_name (the name
 * typed for "other"), _gtm_ship_code (A–Z, 0–9 and "-"; Persian and Arabic digits become Latin,
 * spaces are dropped) and _gtm_ship_date (UTC, when the code was saved or last changed).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\Settings\Settings;
use WC_Order;
use WP_Post;

/**
 * The dashboard box and its save, the shipment for the storefront, the e-mails and the order list.
 */
final class Shipment {

	public const META_CARRIER = '_gtm_ship_carrier';

	public const META_NAME = '_gtm_ship_carrier_name';

	public const META_CODE = '_gtm_ship_code';

	public const META_DATE = '_gtm_ship_date';

	public const OTHER = 'other';

	public const COLUMN = 'gtm_tracking';

	private const NONCE_FIELD = 'gtm_ship_nonce';

	private const NONCE_ACTION = 'gtm_ship_save';

	private const CODE_MAX = 40;

	private const NAME_MAX = 60;

	/**
	 * Shipping companies, in the order of the checkout methods: name per language, default
	 * tracking page and the words that recognise the company in a shipping method title.
	 *
	 * The Tipax and Iran Post pages were checked on 2026-11-27. Both ask for the code on the page
	 * itself (Tipax behind a captcha), so the link opens the page and the shopper pastes the code.
	 * Dekapost's site could not be reached from outside Iran that day, so it starts without a
	 * page; the owner can set one under Gadgetim → Checkout → Shipment tracking.
	 */
	public const CARRIERS = [
		'tipax'    => [
			'fa'    => 'تیپاکس',
			'en'    => 'Tipax',
			'ar'    => 'تيباكس',
			'url'   => 'https://tipaxco.com/tracking',
			'match' => [ 'تیپاکس', 'tipax' ],
		],
		'dekapost' => [
			'fa'    => 'دکاپست',
			'en'    => 'Dekapost',
			'ar'    => 'ديكا بوست',
			'url'   => '',
			'match' => [ 'دکاپست', 'دکا پست', 'dekapost', 'deka post' ],
		],
		'post'     => [
			'fa'    => 'پست',
			'en'    => 'Iran Post',
			'ar'    => 'بريد إيران',
			'url'   => 'https://tracking.post.ir/',
			'match' => [ 'پست', 'post' ],
		],
	];

	/**
	 * Attaches the hooks (woocommerce_loaded).
	 */
	public static function register_hooks(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_box' ], 30 );
		// After WooCommerce saved the order and the status the admin picked (priority 40).
		add_action( 'woocommerce_process_shop_order_meta', [ self::class, 'save' ], 60 );
		add_action( 'woocommerce_email_order_meta', [ self::class, 'email_block' ], 15, 4 );
		add_filter( 'woocommerce_shop_order_list_table_columns', [ self::class, 'list_columns' ], 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ self::class, 'list_column' ], 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', [ self::class, 'list_columns' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ self::class, 'list_column' ], 10, 2 );
	}

	/**
	 * The companies offered in the box. Filter: gadgetim_shipment_carriers (same shape as CARRIERS).
	 *
	 * @return array<string, array{fa: string, en: string, ar: string, url: string, match: string[]}>
	 */
	public static function carriers(): array {
		$carriers = (array) apply_filters( 'gadgetim_shipment_carriers', self::CARRIERS );
		$out      = [];
		foreach ( $carriers as $key => $carrier ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || self::OTHER === $key || ! is_array( $carrier ) ) {
				continue;
			}
			$out[ $key ] = [
				'fa'    => (string) ( $carrier['fa'] ?? $key ),
				'en'    => (string) ( $carrier['en'] ?? ( $carrier['fa'] ?? $key ) ),
				'ar'    => (string) ( $carrier['ar'] ?? ( $carrier['en'] ?? ( $carrier['fa'] ?? $key ) ) ),
				'url'   => (string) ( $carrier['url'] ?? '' ),
				'match' => array_values( array_filter( array_map( 'strval', (array) ( $carrier['match'] ?? [] ) ) ) ),
			];
		}
		return $out;
	}

	/**
	 * A company's name in a language ('' for an unknown key).
	 */
	public static function carrier_name( string $key, string $lang ): string {
		$carriers = self::carriers();
		if ( ! isset( $carriers[ $key ] ) ) {
			return '';
		}
		return (string) ( $carriers[ $key ][ $lang ] ?? $carriers[ $key ]['en'] );
	}

	/**
	 * Settings path of a company's tracking page.
	 */
	public static function url_setting( string $key ): string {
		return 'checkout.tracking_url_' . $key;
	}

	/**
	 * A company's tracking page: the setting, else the default; '' when none or not http(s).
	 */
	public static function tracking_url( string $key ): string {
		$carriers = self::carriers();
		if ( ! isset( $carriers[ $key ] ) ) {
			return '';
		}
		$url = $carriers[ $key ]['url'];
		if ( class_exists( Settings::class ) ) {
			$url = Settings::get( self::url_setting( $key ), $url );
		}
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	/**
	 * The stored values, unchanged.
	 *
	 * @return array{carrier: string, name: string, code: string, date: int}
	 */
	public static function raw( WC_Order $order ): array {
		$date = (string) $order->get_meta( self::META_DATE );
		$time = '' !== $date ? strtotime( $date . ' UTC' ) : false;
		return [
			'carrier' => sanitize_key( (string) $order->get_meta( self::META_CARRIER ) ),
			'name'    => (string) $order->get_meta( self::META_NAME ),
			'code'    => (string) $order->get_meta( self::META_CODE ),
			'date'    => false !== $time ? (int) $time : 0,
		];
	}

	/**
	 * The shipment for display in the current language, once a tracking code is saved.
	 *
	 * @return array{carrier: string, name: string, code: string, url: string, date: int}|null
	 */
	public static function get( WC_Order $order ): ?array {
		$raw = self::raw( $order );
		if ( '' === $raw['code'] ) {
			return null;
		}
		$lang = function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
		return [
			'carrier' => $raw['carrier'],
			'name'    => self::display_name( $raw, $lang ),
			'code'    => $raw['code'],
			'url'     => self::tracking_url( $raw['carrier'] ),
			'date'    => $raw['date'],
		];
	}

	/**
	 * The company as the customer reads it: the known company's name, the name typed for
	 * "another company", else a generic phrase.
	 *
	 * @param array{carrier: string, name: string} $raw  Stored values.
	 * @param string                               $lang Language code.
	 */
	public static function display_name( array $raw, string $lang ): string {
		$name = self::carrier_name( (string) ( $raw['carrier'] ?? '' ), $lang );
		if ( '' !== $name ) {
			return $name;
		}
		if ( self::OTHER === ( $raw['carrier'] ?? '' ) && '' !== (string) ( $raw['name'] ?? '' ) ) {
			return (string) $raw['name'];
		}
		return __( 'the shipping company', 'gadgetim-core' );
	}

	/**
	 * The company of the shipping method the customer chose ('' when it names none).
	 */
	public static function guess_carrier( WC_Order $order ): string {
		$title = self::lower( (string) $order->get_shipping_method() );
		if ( '' === trim( $title ) ) {
			return '';
		}
		foreach ( self::carriers() as $key => $carrier ) {
			foreach ( $carrier['match'] as $word ) {
				if ( '' !== $word && str_contains( $title, self::lower( $word ) ) ) {
					return $key;
				}
			}
		}
		return '';
	}

	/**
	 * A tracking code as stored: Persian and Arabic digits made Latin, letters upper-case, every
	 * character other than A–Z, 0–9 and "-" dropped (spaces, dots, direction marks), at most 40.
	 */
	public static function normalize_code( string $code ): string {
		$code = strtr(
			$code,
			[
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			]
		);
		$code = strtoupper( (string) preg_replace( '/[^A-Za-z0-9-]+/', '', $code ) );
		return substr( trim( $code, '-' ), 0, self::CODE_MAX );
	}

	/**
	 * add_meta_boxes: the box on the order screen (HPOS and posts storage), right under "Order
	 * actions" with its Update button. WooCommerce registers its attribution and customer history
	 * boxes as "high" before add_meta_boxes fires, so the box is moved up in that list. A user who
	 * dragged the boxes keeps their own order.
	 */
	public static function add_box(): void {
		global $wp_meta_boxes;
		$screens = [ 'shop_order' ];
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box( 'gtm-shipment', __( 'Shipment', 'gadgetim-core' ), [ self::class, 'render_box' ], $screen, 'side', 'high' );
			$high = $wp_meta_boxes[ $screen ]['side']['high'] ?? null;
			if ( ! is_array( $high ) || ! isset( $high['gtm-shipment'], $high['woocommerce-order-actions'] ) ) {
				continue;
			}
			$ours = [ 'gtm-shipment' => $high['gtm-shipment'] ];
			unset( $high['gtm-shipment'] );
			$sorted = [];
			foreach ( $high as $id => $box ) {
				$sorted[ $id ] = $box;
				if ( 'woocommerce-order-actions' === $id ) {
					$sorted += $ours;
				}
			}
			$wp_meta_boxes[ $screen ]['side']['high'] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- only reorders the boxes of this screen.
		}
	}

	/**
	 * The box: company, its name for "another company", the tracking code and the two choices.
	 *
	 * @param mixed $item WC_Order (HPOS) or WP_Post (posts storage).
	 */
	public static function render_box( $item ): void {
		$order = $item instanceof WC_Order ? $item : ( $item instanceof WP_Post ? wc_get_order( $item->ID ) : null );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$lang    = self::admin_lang();
		$saved   = self::raw( $order );
		$carrier = '' !== $saved['carrier'] ? $saved['carrier'] : ( '' === $saved['code'] ? self::guess_carrier( $order ) : '' );
		$chosen  = trim( (string) $order->get_shipping_method() );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		echo '<div class="gtm-ship">';

		echo '<p><label for="gtm_ship_carrier"><strong>' . esc_html__( 'Shipping company', 'gadgetim-core' ) . '</strong></label><br>';
		echo '<select name="gtm_ship_carrier" id="gtm_ship_carrier" style="width:100%">';
		echo '<option value="">' . esc_html__( '— Choose —', 'gadgetim-core' ) . '</option>';
		foreach ( array_keys( self::carriers() ) as $key ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $carrier, $key, false ) . '>' . esc_html( self::carrier_name( $key, $lang ) ) . '</option>';
		}
		echo '<option value="' . esc_attr( self::OTHER ) . '"' . selected( $carrier, self::OTHER, false ) . '>' . esc_html__( 'Another company', 'gadgetim-core' ) . '</option>';
		echo '</select></p>';

		echo '<p class="gtm-ship__other"><label for="gtm_ship_name">' . esc_html__( 'Name of the company', 'gadgetim-core' ) . '</label><br>';
		echo '<input type="text" name="gtm_ship_name" id="gtm_ship_name" value="' . esc_attr( $saved['name'] ) . '" maxlength="' . esc_attr( (string) self::NAME_MAX ) . '" style="width:100%"></p>';

		echo '<p><label for="gtm_ship_code"><strong>' . esc_html__( 'Tracking code', 'gadgetim-core' ) . '</strong></label><br>';
		echo '<input type="text" name="gtm_ship_code" id="gtm_ship_code" value="' . esc_attr( $saved['code'] ) . '" maxlength="64" dir="ltr" autocomplete="off" spellcheck="false" style="width:100%;font-family:monospace;font-size:14px"></p>';

		if ( '' !== $chosen ) {
			/* translators: %s: the shipping method the customer chose at checkout */
			echo '<p class="description">' . esc_html( sprintf( __( 'The customer chose: %s', 'gadgetim-core' ), $chosen ) ) . '</p>';
		}
		if ( $order->has_status( 'processing' ) ) {
			echo '<p><label><input type="checkbox" name="gtm_ship_mark" value="1" checked> ' . esc_html__( 'Set the order status to "Shipped" when a new code is saved', 'gadgetim-core' ) . '</label></p>';
		}
		echo '<p><label><input type="checkbox" name="gtm_ship_notify" value="1" checked> ' . esc_html__( 'Tell the customer about a new code (order note and e-mail)', 'gadgetim-core' ) . '</label></p>';

		if ( '' !== $saved['code'] ) {
			$when = $saved['date'] && function_exists( 'gadgetim_date' ) ? gadgetim_date( $saved['date'], 'datetime', $lang ) : '';
			$url  = self::tracking_url( $saved['carrier'] );
			echo '<p class="description">';
			if ( '' !== $when ) {
				/* translators: %s: date and time the tracking code was saved */
				echo esc_html( sprintf( __( 'Saved on %s.', 'gadgetim-core' ), $when ) ) . ' ';
			}
			if ( '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open the tracking page', 'gadgetim-core' ) . '</a>';
			}
			echo '</p>';
		}
		echo '</div>';
		// Show the name field only for "another company" (it stays visible without JavaScript).
		echo '<script>(function(){var s=document.getElementById("gtm_ship_carrier"),o=document.querySelector(".gtm-ship__other");if(!s||!o){return;}function t(){o.style.display=s.value==="' . esc_js( self::OTHER ) . '"?"":"none";}s.addEventListener("change",t);t();})();</script>';
	}

	/**
	 * woocommerce_process_shop_order_meta (60): reads the box. WooCommerce has checked the
	 * order form's own nonce; the box's nonce and the capability are checked here as well.
	 *
	 * @param int|string $order_id Order ID.
	 */
	public static function save( $order_id ): void {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitised below.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$carrier = isset( $_POST['gtm_ship_carrier'] ) && is_string( $_POST['gtm_ship_carrier'] ) ? sanitize_key( wp_unslash( $_POST['gtm_ship_carrier'] ) ) : '';
		$name    = isset( $_POST['gtm_ship_name'] ) && is_string( $_POST['gtm_ship_name'] ) ? (string) wp_unslash( $_POST['gtm_ship_name'] ) : '';
		$code    = isset( $_POST['gtm_ship_code'] ) && is_string( $_POST['gtm_ship_code'] ) ? (string) wp_unslash( $_POST['gtm_ship_code'] ) : '';
		$notify  = ! empty( $_POST['gtm_ship_notify'] );
		$mark    = ! empty( $_POST['gtm_ship_mark'] );
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		self::apply( $order, $carrier, $name, $code, $notify, $mark );
	}

	/**
	 * Stores a shipment and does what follows from a new code: the customer note (or a private
	 * note when the customer is not told) and "Processing" → "Shipped".
	 *
	 * @param WC_Order $order        Order (as saved by WooCommerce).
	 * @param string   $carrier      Company key, "other" or ''.
	 * @param string   $name         Name of "another company".
	 * @param string   $code         Tracking code as typed.
	 * @param bool     $notify       Tell the customer.
	 * @param bool     $mark_shipped Move a "Processing" order to "Shipped".
	 * @return string '' nothing changed, 'removed', 'new' (a new or corrected code).
	 */
	public static function apply( WC_Order $order, string $carrier, string $name, string $code, bool $notify = true, bool $mark_shipped = true ): string {
		$carrier = sanitize_key( $carrier );
		if ( self::OTHER !== $carrier && ! isset( self::carriers()[ $carrier ] ) ) {
			$carrier = '';
		}
		$name = self::OTHER === $carrier ? mb_substr( trim( sanitize_text_field( $name ) ), 0, self::NAME_MAX ) : '';
		$code = self::normalize_code( $code );
		$old  = self::raw( $order );

		if ( $old['carrier'] === $carrier && $old['name'] === $name && $old['code'] === $code ) {
			return '';
		}

		// A shipment exists only with a code. The box preselects the customer's company on every
		// order, so a save without a code stores nothing, and emptying the code removes it all.
		if ( '' === $code ) {
			if ( '' === $old['code'] && '' === $old['carrier'] && '' === $old['name'] ) {
				return '';
			}
			foreach ( [ self::META_CARRIER, self::META_NAME, self::META_CODE, self::META_DATE ] as $key ) {
				$order->delete_meta_data( $key );
			}
			$order->save();
			if ( '' !== $old['code'] ) {
				/* translators: 1: shipping company, 2: tracking code */
				$order->add_order_note( sprintf( __( 'Shipment removed (it was %1$s, tracking code %2$s).', 'gadgetim-core' ), self::display_name( $old, self::admin_lang() ), $old['code'] ), 0, true );
			}
			return 'removed';
		}

		if ( '' !== $carrier ) {
			$order->update_meta_data( self::META_CARRIER, $carrier );
		} else {
			$order->delete_meta_data( self::META_CARRIER );
		}
		if ( '' !== $name ) {
			$order->update_meta_data( self::META_NAME, $name );
		} else {
			$order->delete_meta_data( self::META_NAME );
		}
		$order->update_meta_data( self::META_CODE, $code );
		$order->update_meta_data( self::META_DATE, gmdate( 'Y-m-d H:i:s' ) );
		$order->save();

		if ( $notify ) {
			$order->add_order_note( self::customer_text( $order ), 1, true );
		} else {
			/* translators: 1: shipping company, 2: tracking code */
			$order->add_order_note( sprintf( __( 'Tracking code saved without telling the customer: %1$s, %2$s.', 'gadgetim-core' ), self::display_name( self::raw( $order ), self::admin_lang() ), $code ), 0, true );
		}
		if ( $mark_shipped && $order->has_status( 'processing' ) ) {
			$order->update_status( 'shipped', __( 'Parcel sent.', 'gadgetim-core' ), true );
		}
		return 'new';
	}

	/**
	 * The customer note announcing the code, in the order's language.
	 */
	public static function customer_text( WC_Order $order ): string {
		$build = static function () use ( $order ): string {
			$shipment = self::get( $order );
			if ( null === $shipment ) {
				return '';
			}
			/* translators: 1: shipping company, 2: tracking code */
			$text = sprintf( __( 'Your parcel has been sent with %1$s. Tracking code: %2$s', 'gadgetim-core' ), $shipment['name'], $shipment['code'] );
			if ( '' !== $shipment['url'] ) {
				/* translators: %s: address of the shipping company's tracking page */
				$text .= "\n" . sprintf( __( 'Track it on this page with the code: %s', 'gadgetim-core' ), $shipment['url'] );
			}
			return $text;
		};
		$lang = ReturnUrls::order_lang( $order );
		if ( '' !== $lang && class_exists( Languages::class ) ) {
			return (string) Languages::with_locale( $lang, $build );
		}
		return $build();
	}

	/**
	 * woocommerce_email_order_meta: the shipment in the customer e-mails once a code is saved.
	 * The customer note that announced the code already says it, so that one e-mail skips it.
	 *
	 * @param mixed $order         WC_Order.
	 * @param mixed $sent_to_admin Whether the e-mail goes to the shop.
	 * @param mixed $plain_text    Plain-text e-mail.
	 * @param mixed $email         WC_Email.
	 */
	public static function email_block( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		if ( ! $order instanceof WC_Order || $sent_to_admin ) {
			return;
		}
		$shipment = self::get( $order );
		if ( null === $shipment ) {
			return;
		}
		if ( is_object( $email ) && 'customer_note' === ( $email->id ?? '' ) && str_contains( (string) ( $email->customer_note ?? '' ), $shipment['code'] ) ) {
			return;
		}
		/* translators: %s: shipping company */
		$sent = sprintf( __( 'Sent with %s', 'gadgetim-core' ), $shipment['name'] );
		/* translators: %s: shipping company */
		$track = sprintf( __( 'Track it on the %s website', 'gadgetim-core' ), $shipment['name'] );
		if ( $plain_text ) {
			echo "\n" . esc_html( wp_strip_all_tags( __( 'Your parcel', 'gadgetim-core' ) ) ) . "\n";
			echo esc_html( wp_strip_all_tags( $sent ) ) . "\n";
			echo esc_html( wp_strip_all_tags( __( 'Tracking code:', 'gadgetim-core' ) ) ) . ' ' . esc_html( $shipment['code'] ) . "\n";
			if ( '' !== $shipment['url'] ) {
				echo esc_html( wp_strip_all_tags( $track ) ) . ': ' . esc_url_raw( $shipment['url'] ) . "\n";
			}
			echo "\n";
			return;
		}
		echo '<h2>' . esc_html__( 'Your parcel', 'gadgetim-core' ) . '</h2>';
		echo '<p style="margin:0 0 16px">' . esc_html( $sent ) . '<br>' . esc_html__( 'Tracking code:', 'gadgetim-core' ) . ' <strong dir="ltr" style="font-size:16px;letter-spacing:1px">' . esc_html( $shipment['code'] ) . '</strong>';
		if ( '' !== $shipment['url'] ) {
			echo '<br><a href="' . esc_url( $shipment['url'] ) . '">' . esc_html( $track ) . '</a>';
		}
		echo '</p>';
	}

	/**
	 * Order list columns: "Tracking" after "Ship to".
	 *
	 * @param mixed $columns Columns.
	 * @return array<string, string>
	 */
	public static function list_columns( $columns ): array {
		$out = [];
		foreach ( (array) $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'shipping_address' === $key ) {
				$out[ self::COLUMN ] = __( 'Tracking', 'gadgetim-core' );
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Tracking', 'gadgetim-core' );
		}
		return $out;
	}

	/**
	 * The "Tracking" cell: code and company, or a dash.
	 *
	 * @param mixed $column Column key.
	 * @param mixed $item   WC_Order (HPOS) or post ID (posts storage).
	 */
	public static function list_column( $column, $item ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$order = $item instanceof WC_Order ? $item : wc_get_order( (int) $item );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$raw = self::raw( $order );
		if ( '' === $raw['code'] ) {
			echo '<span aria-hidden="true">–</span><span class="screen-reader-text">' . esc_html__( 'No tracking code yet', 'gadgetim-core' ) . '</span>';
			return;
		}
		echo '<span dir="ltr" style="font-family:monospace">' . esc_html( $raw['code'] ) . '</span><br><small>' . esc_html( self::display_name( $raw, self::admin_lang() ) ) . '</small>';
	}

	/**
	 * Language of the dashboard user's locale (fa, ar, else en).
	 */
	private static function admin_lang(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( str_starts_with( $locale, 'fa' ) ) {
			return 'fa';
		}
		return str_starts_with( $locale, 'ar' ) ? 'ar' : 'en';
	}

	/**
	 * Lower case for matching Persian and Latin titles alike.
	 */
	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
