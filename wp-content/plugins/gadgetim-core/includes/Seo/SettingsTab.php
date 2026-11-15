<?php
/**
 * The "SEO" settings tab (manage_woocommerce).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Schema;

/**
 * Settings paths: seo.home_title, seo.home_description (per language), seo.archive_titles,
 * seo.share_image_id, seo.same_as, seo.indexnow.
 */
final class SettingsTab {

	public const TAB = 'seo';

	/**
	 * Defaults of the per-language home texts (the owner edits them on the tab).
	 */
	public const HOME_TITLE = [
		'fa' => 'گجتیم | فروشگاه گجت و لوازم جانبی موبایل اصل',
		'en' => 'Gadgetim | Original gadgets and mobile accessories',
		'ar' => 'Gadgetim | الأجهزة وإكسسوارات الجوال الأصلية',
	];

	public const HOME_DESCRIPTION = [
		'fa' => 'خرید هندزفری بلوتوثی، ایرپاد، پاوربانک، شارژر و کابل، اسپیکر و ساعت هوشمند اصل از گجتیم؛ با ضمانت اصالت کالا و ارسال با تیپاکس و پست به سراسر ایران.',
		'en' => 'Original wireless earbuds, power banks, chargers and cables, Bluetooth speakers and smartwatches from Gadgetim, with an authenticity guarantee and delivery across Iran.',
		'ar' => 'سماعات بلوتوث وباور بانك وشواحن وكابلات ومكبرات صوت وساعات ذكية أصلية من Gadgetim، مع ضمان الأصالة والشحن إلى جميع أنحاء إيران.',
	];

	/**
	 * init (priority 5): registers the tab.
	 */
	public static function register_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		Schema::add_tab(
			self::TAB,
			__( 'SEO', 'gadgetim-core' ),
			Capabilities::SHOP,
			[
				'home'    => [
					'label'       => __( 'Home page in search results', 'gadgetim-core' ),
					'description' => __( 'The title and the description Google shows for the home page, per language. Keep the title under about 60 characters and the description under about 155.', 'gadgetim-core' ),
					'order'       => 10,
					'fields'      => [
						'seo.home_title'       => [
							'type'    => 'text',
							'i18n'    => true,
							'label'   => __( 'Home page title', 'gadgetim-core' ),
							'default' => self::HOME_TITLE,
						],
						'seo.home_description' => [
							'type'    => 'textarea',
							'i18n'    => true,
							'rows'    => 3,
							'label'   => __( 'Home page description', 'gadgetim-core' ),
							'default' => self::HOME_DESCRIPTION,
						],
					],
				],
				'archive' => [
					'label'  => __( 'Categories and brands', 'gadgetim-core' ),
					'order'  => 20,
					'fields' => [
						'seo.archive_titles' => [
							'type'        => 'toggle',
							'label'       => __( 'Write "price and buy … original" into category and brand titles', 'gadgetim-core' ),
							'description' => __( 'For example «قیمت و خرید پاوربانک اصل – گجتیم» instead of «پاوربانک – گجتیم». Categories and brands without their own description also get a generated description.', 'gadgetim-core' ),
							'default'     => true,
						],
					],
				],
				'social'  => [
					'label'       => __( 'Sharing and profiles', 'gadgetim-core' ),
					'description' => __( 'Used for link previews in Telegram, WhatsApp and Instagram and in the store information Google reads.', 'gadgetim-core' ),
					'order'       => 30,
					'fields'      => [
						'seo.share_image_id' => [
							'type'        => 'image',
							'label'       => __( 'Default share image', 'gadgetim-core' ),
							'description' => __( 'Shown when a page has no image of its own. 1200 × 630 pixels works best; the site icon is used when empty.', 'gadgetim-core' ),
						],
						'seo.same_as'        => [
							'type'        => 'textarea',
							'rows'        => 4,
							'label'       => __( 'Official profile addresses', 'gadgetim-core' ),
							'description' => __( 'One address per line (Instagram, Telegram channel, Aparat, LinkedIn …). Only profiles that belong to the store.', 'gadgetim-core' ),
							'attrs'       => [ 'dir' => 'ltr' ],
							'sanitize'    => [ self::class, 'sanitize_urls' ],
						],
					],
				],
				'indexing' => [
					'label'  => __( 'Bing and other search engines', 'gadgetim-core' ),
					'order'  => 40,
					'fields' => [
						'seo.indexnow' => [
							'type'        => 'toggle',
							'label'       => __( 'Tell Bing and the other IndexNow engines about new and changed pages', 'gadgetim-core' ),
							'description' => self::indexnow_description(),
							'default'     => true,
						],
					],
				],
			],
			[ 'order' => 45 ]
		);
	}

	/**
	 * What IndexNow does, and in the admin its last submission.
	 */
	private static function indexnow_description(): string {
		$text = __( 'Sends the address of every product, page and post you publish or update, so Bing (and through it ChatGPT search and Copilot) and Yandex visit it soon. Only the live site sends.', 'gadgetim-core' );
		if ( ! is_admin() ) {
			return $text;
		}
		$rows = IndexNow::log_rows();
		if ( [] === $rows ) {
			return $text . ' ' . __( 'Nothing sent yet. The first product or page you publish or update is sent a minute later.', 'gadgetim-core' );
		}
		$row = $rows[0];
		return $text . ' ' . sprintf(
			/* translators: 1: date and time, 2: number of addresses, 3: HTTP status code or "no answer". */
			__( 'Last sent %1$s: %2$s addresses, answer %3$s.', 'gadgetim-core' ),
			wp_date( 'Y-m-d H:i', (int) $row['time'] ),
			number_format_i18n( (int) $row['count'] ),
			(int) $row['code'] > 0 ? (string) (int) $row['code'] : __( 'no answer', 'gadgetim-core' )
		);
	}

	/**
	 * One http(s) URL per line; anything else is dropped.
	 *
	 * @param mixed $value Raw textarea value.
	 */
	public static function sanitize_urls( $value ): string {
		$out = [];
		foreach ( preg_split( '/\R/', is_scalar( $value ) ? (string) $value : '' ) ?: [] as $line ) {
			$url = esc_url_raw( trim( $line ), [ 'http', 'https' ] );
			if ( '' !== $url ) {
				$out[ $url ] = true;
			}
		}
		return implode( "\n", array_slice( array_keys( $out ), 0, 20 ) );
	}

	/**
	 * The profile URLs as a list.
	 *
	 * @return string[]
	 */
	public static function profiles(): array {
		$raw = class_exists( '\Gadgetim\Core\Settings\Settings' ) ? (string) \Gadgetim\Core\Settings\Settings::get( 'seo.same_as', '' ) : '';
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $raw ) ?: [] ) ) );
	}
}
