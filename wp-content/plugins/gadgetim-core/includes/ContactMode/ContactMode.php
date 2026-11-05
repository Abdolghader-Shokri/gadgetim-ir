<?php
/**
 * Contact-mode state: settings tab, global switch, per-product override, channels.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\ContactMode;

use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Phone;
use WC_Product;

/**
 * Reads gadgetim_settings['contact'] and the _gtm_contact_only product meta.
 *
 * Settings paths (all under 'contact'): enabled, show_price, primary, label{lang},
 * note{lang}, mode.phone, mode.whatsapp, mode.telegram, mode.hours{lang}. The brand tab's
 * contact.phone / contact.whatsapp / contact.telegram / contact.hours are the fallbacks.
 */
final class ContactMode {

	public const TAB = 'contact_mode';

	public const META_OVERRIDE = '_gtm_contact_only';

	public const OVERRIDES = [ 'inherit', 'yes', 'no' ];

	public const CHANNELS = [ 'call', 'whatsapp', 'telegram' ];

	/** @var array<string,mixed> Per-request memo. */
	private static array $memo = [];

	/**
	 * init (priority 5): the "Contact instead of price" settings tab (manage_woocommerce, D11).
	 */
	public static function register_settings_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}

		Schema::add_tab(
			self::TAB,
			__( 'Contact instead of price', 'gadgetim-core' ),
			Capabilities::SHOP,
			[
				'mode'     => [
					'label'       => __( 'Mode', 'gadgetim-core' ),
					'description' => __( 'When enabled, products show contact buttons instead of a buy button: purchasing is disabled on the whole storefront, the cart and checkout pages redirect to the shop, payment gateways are withdrawn and the price is shown only when "Still show the price" is on. A single product can opt in or out from its edit screen.', 'gadgetim-core' ),
					'fields'      => [
						'contact.enabled'    => [
							'type'    => 'toggle',
							'label'   => __( 'Enable "contact instead of price"', 'gadgetim-core' ),
							'default' => false,
						],
						'contact.show_price' => [
							'type'        => 'toggle',
							'label'       => __( 'Still show the price next to the contact buttons', 'gadgetim-core' ),
							'description' => __( 'Also controls whether product JSON-LD keeps its offers while the mode is on.', 'gadgetim-core' ),
							'default'     => true,
						],
						'contact.primary'    => [
							'type'        => 'select',
							'label'       => __( 'Primary channel', 'gadgetim-core' ),
							'description' => __( 'Drawn as the filled button and used as the single button on product cards; the other channels are outlined.', 'gadgetim-core' ),
							'options'     => [
								'whatsapp' => __( 'WhatsApp', 'gadgetim-core' ),
								'call'     => __( 'Phone call', 'gadgetim-core' ),
								'telegram' => __( 'Telegram', 'gadgetim-core' ),
							],
							'default'     => 'whatsapp',
						],
					],
				],
				'texts'    => [
					'label'  => __( 'Texts', 'gadgetim-core' ),
					'fields' => [
						'contact.label' => [
							'type'        => 'text',
							'i18n'        => true,
							'label'       => __( 'Price line', 'gadgetim-core' ),
							'description' => __( 'Replaces the price, e.g. "Call for price".', 'gadgetim-core' ),
							'default'     => [
								'fa' => 'تماس بگیرید',
								'en' => 'Ask for price',
								'ar' => 'اطلب السعر',
							],
						],
						'contact.note'  => [
							'type'        => 'text',
							'i18n'        => true,
							'label'       => __( 'Explanation line', 'gadgetim-core' ),
							'description' => __( 'One honest sentence under the price line; leave empty to hide it.', 'gadgetim-core' ),
							'default'     => [
								'fa' => 'به‌دلیل نوسان نرخ ارز، قیمت را لحظه‌ای اعلام می‌کنیم',
								'en' => 'Exchange rates move daily, so we quote the price on request.',
								'ar' => 'بسبب تقلّب أسعار الصرف، نعلن السعر عند الطلب.',
							],
						],
					],
				],
				'channels' => [
					'label'       => __( 'Channels for this mode', 'gadgetim-core' ),
					'description' => __( 'Leave a field empty to use the number or username from Brand & contact.', 'gadgetim-core' ),
					'fields'      => [
						'contact.mode.phone'    => [
							'type'        => 'phone',
							'label'       => __( 'Phone (override)', 'gadgetim-core' ),
							'placeholder' => '09xx xxx xxxx',
						],
						'contact.mode.whatsapp' => [
							'type'        => 'phone',
							'label'       => __( 'WhatsApp number (override)', 'gadgetim-core' ),
							'placeholder' => '09xx xxx xxxx',
						],
						'contact.mode.telegram' => [
							'type'        => 'text',
							'label'       => __( 'Telegram username (override)', 'gadgetim-core' ),
							'placeholder' => 'gadgetim',
							'sanitize'    => [ self::class, 'sanitize_handle' ],
							'attrs'       => [ 'dir' => 'ltr' ],
						],
						'contact.mode.hours'    => [
							'type'        => 'text',
							'i18n'        => true,
							'label'       => __( 'Working hours (override)', 'gadgetim-core' ),
							'description' => __( 'Shown under the buttons so callers know when somebody answers.', 'gadgetim-core' ),
						],
					],
				],
			],
			[ 'order' => 25 ]
		);
	}

	/**
	 * Sanitizer for the Telegram override (same rules as the brand tab).
	 */
	public static function sanitize_handle( mixed $value ): string {
		return is_scalar( $value ) ? Phone::handle( (string) $value ) : '';
	}

	/**
	 * gadgetim_settings_saved: drop the per-request memo. Support\Cache already purges the
	 * whole page cache on every settings save, which covers the toggle (architecture §4.6).
	 *
	 * @param array $new New settings.
	 * @param array $old Previous settings.
	 */
	public static function on_settings_saved( array $new, array $old ): void {
		self::reset();

		$was = ! empty( $old['contact']['enabled'] );
		$now = ! empty( $new['contact']['enabled'] );
		if ( $was !== $now ) {
			/**
			 * Fires when the global contact mode is switched on or off.
			 *
			 * @param bool $enabled New state.
			 */
			do_action( 'gadgetim_contact_mode_toggled', $now );
		}
	}

	/**
	 * Forgets memoised settings (tests, settings saves).
	 */
	public static function reset(): void {
		self::$memo = [];
	}

	/**
	 * Whether the global mode is on.
	 */
	public static function enabled(): bool {
		return (bool) self::setting( 'contact.enabled', false );
	}

	/**
	 * Whether the price stays visible next to the buttons.
	 */
	public static function show_price(): bool {
		return (bool) self::setting( 'contact.show_price', true );
	}

	/**
	 * Primary channel: 'whatsapp' (default), 'call' or 'telegram'.
	 */
	public static function primary(): string {
		$primary = (string) self::setting( 'contact.primary', 'whatsapp' );
		return in_array( $primary, self::CHANNELS, true ) ? $primary : 'whatsapp';
	}

	/**
	 * Per-product override: 'inherit', 'yes' or 'no'. Variations resolve to their parent.
	 */
	public static function override( int $product_id ): string {
		$product_id = self::root_id( $product_id );
		if ( $product_id <= 0 ) {
			return 'inherit';
		}
		$value = get_post_meta( $product_id, self::META_OVERRIDE, true );
		return self::sanitize_override( is_scalar( $value ) ? (string) $value : '' ) ?: 'inherit';
	}

	/**
	 * Normalises an override value: 'yes' | 'no' | '' (inherit).
	 */
	public static function sanitize_override( mixed $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return in_array( $value, [ 'yes', 'no' ], true ) ? $value : '';
	}

	/**
	 * Whether contact mode applies to a product: the override wins, else the global switch.
	 *
	 * @param WC_Product|int $product Product, variation or ID.
	 */
	public static function applies( WC_Product|int $product ): bool {
		$id       = $product instanceof WC_Product ? $product->get_id() : $product;
		$override = $id > 0 ? self::override( $id ) : 'inherit';
		$applies  = match ( $override ) {
			'yes'   => true,
			'no'    => false,
			default => self::enabled(),
		};

		/**
		 * Filters whether contact mode applies to a product.
		 *
		 * @param bool           $applies Computed result.
		 * @param WC_Product|int $product Product or ID as passed in.
		 */
		return (bool) apply_filters( 'gadgetim_contact_mode_applies', $applies, $product );
	}

	/**
	 * Whether JSON-LD may carry offers (prices) for a product: not in contact mode, or
	 * contact mode with the price still shown. Seo\JsonLd should use this too.
	 *
	 * @param WC_Product|int $product Product or ID.
	 */
	public static function offers_enabled( WC_Product|int $product ): bool {
		$enabled = ! self::applies( $product ) || self::show_price();

		/**
		 * Filters whether product JSON-LD includes offers (architecture §4.6).
		 *
		 * @param bool           $enabled Computed result.
		 * @param WC_Product|int $product Product or ID.
		 */
		return (bool) apply_filters( 'gadgetim_jsonld_offers_enabled', $enabled, $product );
	}

	/**
	 * Price line for a language (configured text, else a translated default).
	 */
	public static function label( ?string $lang = null ): string {
		$text = class_exists( Settings::class ) ? Settings::get_lang( 'contact.label', $lang ) : '';
		return '' !== $text ? $text : __( 'Ask for price', 'gadgetim-core' );
	}

	/**
	 * Explanation line for a language ('' hides it).
	 */
	public static function note( ?string $lang = null ): string {
		return class_exists( Settings::class ) ? Settings::get_lang( 'contact.note', $lang ) : '';
	}

	/**
	 * Working hours for a language: the mode override, else the brand contact hours.
	 */
	public static function hours( ?string $lang = null ): string {
		if ( ! class_exists( Settings::class ) ) {
			return '';
		}
		$hours = Settings::get_lang( 'contact.mode.hours', $lang );
		return '' !== $hours ? $hours : Settings::get_lang( 'contact.hours', $lang );
	}

	/**
	 * Raw channel value: the mode override, else the brand contact value.
	 *
	 * @param string $channel 'phone', 'whatsapp' or 'telegram'.
	 */
	public static function channel_value( string $channel ): string {
		$channel = sanitize_key( $channel );
		$value   = (string) self::setting( 'contact.mode.' . $channel, '' );
		if ( '' === trim( $value ) ) {
			$value = (string) self::setting( 'contact.' . $channel, '' );
		}
		return trim( $value );
	}

	/**
	 * Configured channels with ready-to-escape hrefs:
	 * [ 'call' => [ 'href' => 'tel:+98…', 'display' => '0917 123 4567' ], 'whatsapp' => [ 'href' => 'https://wa.me/98…' ], 'telegram' => [ 'href' => 'https://t.me/x', 'handle' => 'x' ] ]
	 *
	 * @param string $whatsapp_text Text pre-filled in the WhatsApp chat.
	 * @param string $lang          Language for the phone display digits.
	 * @return array<string,array<string,string>>
	 */
	public static function channels( string $whatsapp_text = '', string $lang = 'fa' ): array {
		$channels = [];

		$phone = self::channel_value( 'phone' );
		if ( '' !== $phone ) {
			$href = Phone::tel_href( $phone );
			if ( '' !== $href ) {
				$channels['call'] = [
					'href'    => $href,
					'display' => Phone::national( $phone, $lang ),
				];
			}
		}

		$whatsapp = self::channel_value( 'whatsapp' );
		if ( '' !== $whatsapp ) {
			$href = Phone::whatsapp_url( $whatsapp, $whatsapp_text );
			if ( '' !== $href ) {
				$channels['whatsapp'] = [ 'href' => $href ];
			}
		}

		$telegram = self::channel_value( 'telegram' );
		if ( '' !== $telegram ) {
			$href = Phone::telegram_url( $telegram );
			if ( '' !== $href ) {
				$channels['telegram'] = [
					'href'   => $href,
					'handle' => Phone::handle( $telegram ),
				];
			}
		}

		/**
		 * Filters the contact channels offered in contact mode.
		 *
		 * @param array  $channels      [ channel => [ 'href' => …, … ] ].
		 * @param string $whatsapp_text Pre-filled WhatsApp text.
		 * @param string $lang          Language code.
		 */
		return (array) apply_filters( 'gadgetim_contact_channels', $channels, $whatsapp_text, $lang );
	}

	/**
	 * Root product ID: the parent for variations, the ID itself otherwise.
	 */
	public static function root_id( int $product_id ): int {
		if ( $product_id <= 0 ) {
			return 0;
		}
		$post = get_post( $product_id );
		if ( $post && 'product_variation' === $post->post_type && $post->post_parent > 0 ) {
			return (int) $post->post_parent;
		}
		return $product_id;
	}

	/**
	 * Memoised settings read (Settings may be absent in a stripped build).
	 */
	private static function setting( string $path, mixed $default ): mixed {
		if ( array_key_exists( $path, self::$memo ) ) {
			return self::$memo[ $path ];
		}
		$value = class_exists( Settings::class ) ? Settings::get( $path, $default ) : $default;
		if ( null === $value ) {
			$value = $default;
		}
		self::$memo[ $path ] = $value;
		return $value;
	}
}
