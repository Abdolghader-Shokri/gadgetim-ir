<?php
/**
 * The price slip («برگهٔ قیمت», design spec §5.7): markup that replaces the price in contact mode.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\ContactMode;

use Gadgetim\Core\Support\Context;
use Gadgetim\Core\Support\Html;
use WC_Product;

/**
 * Markup (classes are the theme's styling contract):
 *
 * <div class="gtm-contact gtm-contact--full gtm-contact--primary-whatsapp" data-id="4208">
 *   <p class="price …">…</p>                              (only when "still show the price" is on)
 *   <p class="gtm-contact__label">تماس بگیرید</p>
 *   <p class="gtm-contact__note">…</p>
 *   <div class="gtm-contact__actions">
 *     <a class="gtm-contact__btn gtm-contact__btn--call" href="tel:+98…">…</a>
 *     <a class="gtm-contact__btn gtm-contact__btn--whatsapp is-primary" href="https://wa.me/98…?text=…">…</a>
 *     <a class="gtm-contact__btn gtm-contact__btn--telegram" href="https://t.me/…">…</a>
 *   </div>
 *   <p class="gtm-contact__hours">…</p>
 * </div>
 *
 * The compact variant (cards, variation JSON) carries the label and the primary channel only.
 */
final class Display {

	public const VARIANTS = [ 'full', 'compact' ];

	/**
	 * Attaches the price filter (priority 50: after Pricing\Display's digit wrapping at 20).
	 */
	public static function register_hooks(): void {
		add_filter( 'woocommerce_get_price_html', [ self::class, 'price_html' ], 50, 2 );
	}

	/**
	 * woocommerce_get_price_html: the slip on the storefront for contact-mode products.
	 *
	 * @param string     $html    WooCommerce's price HTML.
	 * @param WC_Product $product Product or variation.
	 */
	public static function price_html( $html, $product ): string {
		$html = (string) $html;
		if ( ! $product instanceof WC_Product || ! Context::is_storefront_render() || ! Enforcement::active() ) {
			return $html;
		}
		if ( ! ContactMode::applies( $product ) ) {
			return $html;
		}
		$variant = 'compact';
		if ( ! $product->is_type( 'variation' ) && function_exists( 'is_product' ) && is_product() && get_queried_object_id() === $product->get_id() ) {
			$variant = 'full';
		}
		return self::slip( $product, $variant, $html );
	}

	/**
	 * Builds the slip. Theme templates may call this directly (class_exists-guarded).
	 *
	 * @param WC_Product $product    Product or variation.
	 * @param string     $variant    'full' (product page) or 'compact' (cards).
	 * @param string     $price_html Price HTML to include when "still show the price" is on ('' = none).
	 */
	public static function slip( WC_Product $product, string $variant = 'full', string $price_html = '' ): string {
		$variant = in_array( $variant, self::VARIANTS, true ) ? $variant : 'full';
		$lang    = function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa';
		$primary = ContactMode::primary();
		$root_id = ContactMode::root_id( $product->get_id() );

		$channels = ContactMode::channels( self::whatsapp_text( $product ), $lang );
		if ( 'compact' === $variant && $channels ) {
			$keep     = isset( $channels[ $primary ] ) ? $primary : (string) array_key_first( $channels );
			$channels = [ $keep => $channels[ $keep ] ];
		}

		$inner = '';
		if ( ContactMode::show_price() && '' !== trim( wp_strip_all_tags( $price_html ) ) ) {
			$inner .= $price_html;
		}
		$inner .= Html::text( 'p', ContactMode::label( $lang ), [ 'class' => 'gtm-contact__label' ] );

		if ( 'full' === $variant ) {
			$note = ContactMode::note( $lang );
			if ( '' !== $note ) {
				$inner .= Html::text( 'p', $note, [ 'class' => 'gtm-contact__note' ] );
			}
		}

		if ( $channels ) {
			$buttons = '';
			foreach ( $channels as $channel => $data ) {
				$buttons .= self::button( (string) $channel, $data, $channel === $primary );
			}
			$inner .= Html::tag( 'div', [ 'class' => 'gtm-contact__actions', 'role' => 'group', 'aria-label' => __( 'Contact us for the price', 'gadgetim-core' ) ], $buttons );
		}

		if ( 'full' === $variant ) {
			$hours = ContactMode::hours( $lang );
			if ( '' !== $hours ) {
				$inner .= Html::text( 'p', $hours, [ 'class' => 'gtm-contact__hours' ] );
			}
		}

		$html = Html::tag(
			'div',
			[
				'class'   => Html::classes( 'gtm-contact', 'gtm-contact--' . $variant, 'gtm-contact--primary-' . $primary, $channels ? '' : 'gtm-contact--no-channels' ),
				'data-id' => $root_id > 0 ? (string) $root_id : null,
			],
			$inner
		);

		/**
		 * Filters the contact-mode price slip markup.
		 *
		 * @param string     $html     Markup.
		 * @param WC_Product $product  Product or variation.
		 * @param string     $variant  'full' or 'compact'.
		 * @param array      $channels Channels rendered.
		 */
		return (string) apply_filters( 'gadgetim_contact_slip_html', $html, $product, $variant, $channels );
	}

	/**
	 * One channel button.
	 *
	 * @param string $channel 'call', 'whatsapp' or 'telegram'.
	 * @param array  $data    [ 'href' => …, 'display' => …, 'handle' => … ].
	 * @param bool   $primary Whether this is the filled button.
	 */
	public static function button( string $channel, array $data, bool $primary ): string {
		$href = (string) ( $data['href'] ?? '' );
		if ( '' === $href ) {
			return '';
		}
		$labels = [
			'call'     => __( 'Call', 'gadgetim-core' ),
			'whatsapp' => __( 'WhatsApp', 'gadgetim-core' ),
			'telegram' => __( 'Telegram', 'gadgetim-core' ),
		];
		$icons  = [
			'call'     => 'phone',
			'whatsapp' => 'whatsapp',
			'telegram' => 'telegram',
		];
		$label  = $labels[ $channel ] ?? ucfirst( $channel );

		$attrs = [
			'class'        => Html::classes( 'gtm-contact__btn', 'gtm-contact__btn--' . $channel, $primary ? 'is-primary' : 'is-secondary' ),
			'href'         => $href,
			'data-channel' => $channel,
		];
		if ( 'call' !== $channel ) {
			$attrs['target'] = '_blank';
			$attrs['rel']    = 'noopener noreferrer';
		}
		if ( ! empty( $data['display'] ) ) {
			$attrs['title'] = (string) $data['display'];
		}

		$inner = Html::icon( $icons[ $channel ] ?? $channel, 'icon gtm-contact__icon' ) . Html::text( 'span', $label, [ 'class' => 'gtm-contact__btn-text' ] );
		return Html::tag( 'a', $attrs, $inner );
	}

	/**
	 * Pre-filled WhatsApp text: short product title + URL (architecture §4.6).
	 */
	public static function whatsapp_text( WC_Product $product ): string {
		$root_id = ContactMode::root_id( $product->get_id() );
		$name    = trim( wp_strip_all_tags( (string) $product->get_name() ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $name ) > 80 ) {
			$name = rtrim( mb_substr( $name, 0, 79 ) ) . '…';
		}
		$url  = (string) get_permalink( $root_id > 0 ? $root_id : $product->get_id() );
		$text = trim( $name . ' ' . $url );

		/**
		 * Filters the text pre-filled in the WhatsApp chat.
		 *
		 * @param string     $text    Text.
		 * @param WC_Product $product Product or variation.
		 */
		return (string) apply_filters( 'gadgetim_contact_whatsapp_text', $text, $product );
	}
}
