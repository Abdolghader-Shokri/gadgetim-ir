<?php
/**
 * JSON-LD: the store (OnlineStore + WebSite) on the home page, a page node on every indexable
 * page, BreadcrumbList on inner pages, and corrections to WooCommerce's product markup.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Phone;
use WP_Post;

/**
 * Prices in the product markup: schema.org wants an ISO 4217 code, and the store currency
 * "IRT" (Toman) is not one. Amounts are converted to Rial (×10, "IRR") in every offer and
 * price specification. Offers stay removed in contact mode (ContactMode\Enforcement, which
 * runs first).
 */
final class JsonLd {

	/**
	 * Country of the market, per language (coverage: Iran only, owner 2026-11-12).
	 */
	private const COUNTRY = [
		'fa' => 'ایران',
		'en' => 'Iran',
		'ar' => 'إيران',
	];

	/**
	 * Locality and region of the registered address (the eNamad address in the contact settings),
	 * per language; the settings hold the address as one line. Filter: gadgetim_seo_address_parts.
	 */
	private const ADDRESS_PARTS = [
		'fa' => [ 'گله‌دار', 'فارس' ],
		'en' => [ 'Galehdar', 'Fars' ],
		'ar' => [ 'كله دار', 'فارس' ],
	];

	/**
	 * Longest product description Google reads in merchant listings.
	 */
	private const MAX_PRODUCT_DESCRIPTION = 5000;

	/**
	 * Other names the store is known by.
	 */
	private const ALTERNATE_NAMES = [ 'گجتیم', 'Gadgetim', 'GADGETIM' ];

	/** @var array<int,array{0:string,1:string}> Breadcrumbs the theme rendered. */
	private static array $crumbs = [];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'print_site' ], 25 );
		add_action( 'wp_head', [ self::class, 'print_page_type' ], 26 );
		add_filter( 'gadgetim_breadcrumbs', [ self::class, 'capture_crumbs' ], 999, 1 );
		add_action( 'wp_footer', [ self::class, 'print_breadcrumbs' ], 20 );
		add_filter( 'woocommerce_structured_data_product', [ self::class, 'filter_product' ], 30, 2 );
	}

	/**
	 * wp_head (25): OnlineStore + WebSite on the first page of the home page.
	 */
	public static function print_site(): void {
		if ( ! is_front_page() || is_paged() || ! Meta::data()['enabled'] ) {
			return;
		}
		self::print_json( self::site_graph( Languages::current() ) );
	}

	/**
	 * Schema type of a page by its role (the importer's _gtm_page_role); any other indexable page
	 * is a WebPage. The about and contact pages also say the store entity is their subject.
	 */
	private const PAGE_TYPES = [
		'about'   => 'AboutPage',
		'contact' => 'ContactPage',
		'faq'     => 'FAQPage',
	];

	/**
	 * Page types whose subject is the store itself.
	 */
	private const ABOUT_STORE = [ 'AboutPage', 'ContactPage' ];

	/**
	 * wp_head (26): the page node of an indexable page: its type, the website it belongs to, its
	 * own featured image (primaryImageOfPage) and, on the FAQ page, its questions. The theme
	 * reads the questions out of the page content (filter gadgetim_seo_faq_entities); without
	 * them the FAQ page is a plain WebPage, since an FAQPage needs at least one question.
	 */
	public static function print_page_type(): void {
		if ( ! is_page() ) {
			return;
		}
		$data = Meta::data();
		if ( ! $data['enabled'] || $data['noindex'] || '' === $data['canonical'] ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return;
		}
		$role = (string) get_post_meta( $post->ID, '_gtm_page_role', true );
		$type = self::PAGE_TYPES[ $role ] ?? 'WebPage';
		$root = (string) Languages::with_lang( Languages::DEFAULT, static fn(): string => home_url( '/' ) );
		$page = [
			'@context'   => 'https://schema.org',
			'@type'      => $type,
			'@id'        => $data['canonical'] . '#webpage',
			'url'        => $data['canonical'],
			'name'       => $data['title'],
			'inLanguage' => Languages::hreflang( $data['lang'] ),
			'isPartOf'   => [ '@id' => $root . '#website' ],
		];
		if ( in_array( $type, self::ABOUT_STORE, true ) ) {
			$page['about'] = [ '@id' => $root . '#organization' ];
		}
		if ( '' !== $data['description'] ) {
			$page['description'] = $data['description'];
		}
		$image = self::page_image( $post, $data['lang'] );
		if ( null !== $image ) {
			$page['primaryImageOfPage'] = $image;
		}
		if ( 'FAQPage' === $type ) {
			$questions = array_values( array_filter( (array) apply_filters( 'gadgetim_seo_faq_entities', [], $post ), 'is_array' ) );
			if ( $questions ) {
				$page['mainEntity'] = $questions;
			} else {
				$page['@type'] = 'WebPage';
			}
		}
		self::print_json( $page );
	}

	/**
	 * The page's own featured image as an ImageObject; null without one, or when its words are
	 * in another language (that page shares the store card of its language instead).
	 *
	 * @return array<string,mixed>|null
	 */
	private static function page_image( WP_Post $post, string $lang ): ?array {
		$id = (int) get_post_thumbnail_id( $post );
		if ( $id <= 0 || ! Meta::image_speaks( $id, $lang ) ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return null;
		}
		$node = [
			'@type'  => 'ImageObject',
			'url'    => Text::encode_url( (string) $src[0] ),
			'width'  => (int) ( $src[1] ?? 0 ),
			'height' => (int) ( $src[2] ?? 0 ),
		];
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt ) {
			$node['caption'] = $alt;
		}
		return $node;
	}

	/**
	 * The @graph of the home page in $lang.
	 *
	 * @return array<string,mixed>
	 */
	public static function site_graph( string $lang ): array {
		$root     = (string) Languages::with_lang( Languages::DEFAULT, static fn(): string => home_url( '/' ) );
		$home     = home_url( '/' );
		$name     = Settings::get_lang( 'brand.name', $lang, (string) get_bloginfo( 'name' ) );
		$icon     = get_theme_file_uri( 'assets/img/icon-512.png' );
		$phone    = Phone::to_e164_iran( (string) Settings::get( 'contact.phone', '' ) );
		$email    = sanitize_email( (string) Settings::get( 'contact.email', '' ) );
		$address  = Settings::get_lang( 'contact.address', $lang, '' );
		$country  = self::COUNTRY[ $lang ] ?? self::COUNTRY['fa'];
		$langs    = array_map( [ Languages::class, 'hreflang' ], Languages::public_codes() );
		$profiles = SettingsTab::profiles();
		foreach ( [ 'instagram' => 'https://www.instagram.com/%s/', 'telegram' => 'https://t.me/%s' ] as $network => $pattern ) {
			$handle = Phone::handle( (string) Settings::get( 'contact.' . $network, '' ) );
			if ( '' !== $handle ) {
				$profiles[] = sprintf( $pattern, rawurlencode( $handle ) );
			}
		}

		$store = [
			'@type'         => 'OnlineStore',
			'@id'           => $root . '#organization',
			'name'          => $name,
			'alternateName' => array_values( array_diff( self::ALTERNATE_NAMES, [ $name ] ) ),
			'url'           => $home,
			'logo'          => [
				'@type'  => 'ImageObject',
				'url'    => $icon,
				'width'  => 512,
				'height' => 512,
			],
			'description'   => Meta::description( $lang ),
			'areaServed'    => [
				'@type'      => 'Country',
				'name'       => $country,
				'identifier' => 'IR',
			],
			'knowsLanguage' => array_values( $langs ),
		];
		$image = Meta::data()['image'];
		if ( is_array( $image ) ) {
			$store['image'] = $image['url'];
		}
		if ( '' !== $email ) {
			$store['email'] = $email;
		}
		if ( '' !== $phone ) {
			$store['telephone']    = $phone;
			$store['contactPoint'] = [
				[
					'@type'             => 'ContactPoint',
					'telephone'         => $phone,
					'contactType'       => 'customer service',
					'areaServed'        => 'IR',
					'availableLanguage' => array_values( $langs ),
				],
			];
		}
		if ( '' !== $address ) {
			/**
			 * Filters the [ locality, region ] of the store address per language.
			 *
			 * @param array<string,array{0:string,1:string}> $parts Parts per language.
			 */
			$parts            = (array) apply_filters( 'gadgetim_seo_address_parts', self::ADDRESS_PARTS );
			$part             = (array) ( $parts[ $lang ] ?? $parts['fa'] ?? [] );
			$store['address'] = array_filter(
				[
					'@type'           => 'PostalAddress',
					'streetAddress'   => $address,
					'addressLocality' => (string) ( $part[0] ?? '' ),
					'addressRegion'   => (string) ( $part[1] ?? '' ),
					'addressCountry'  => 'IR',
				],
				static fn( $value ): bool => '' !== $value
			);
		}
		if ( $profiles ) {
			$store['sameAs'] = array_values( array_unique( $profiles ) );
		}

		$site = [
			'@type'      => 'WebSite',
			'@id'        => $root . '#website',
			'url'        => $home,
			'name'       => $name,
			'alternateName' => array_values( array_diff( self::ALTERNATE_NAMES, [ $name ] ) ),
			'inLanguage' => Languages::hreflang( $lang ),
			'publisher'  => [ '@id' => $root . '#organization' ],
		];

		return [
			'@context' => 'https://schema.org',
			'@graph'   => [ $store, $site ],
		];
	}

	/**
	 * gadgetim_breadcrumbs (999): remembers the trail the theme renders.
	 *
	 * @param mixed $crumbs [ [ label, url ], … ].
	 * @return mixed
	 */
	public static function capture_crumbs( $crumbs ) {
		if ( is_array( $crumbs ) ) {
			self::$crumbs = array_values( $crumbs );
		}
		return $crumbs;
	}

	/**
	 * wp_footer (20): BreadcrumbList for the trail shown on the page (indexable pages only).
	 */
	public static function print_breadcrumbs(): void {
		$data = Meta::data();
		if ( count( self::$crumbs ) < 2 || ! $data['enabled'] || $data['noindex'] ) {
			return;
		}
		$items = [];
		$last  = count( self::$crumbs ) - 1;
		foreach ( self::$crumbs as $i => $crumb ) {
			$label = trim( html_entity_decode( wp_strip_all_tags( (string) ( $crumb[0] ?? '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$url   = (string) ( $crumb[1] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			if ( $i === $last && '' !== $data['canonical'] ) {
				$url = $data['canonical'];
			}
			$item = [
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => $label,
			];
			if ( '' !== $url ) {
				$item['item'] = Text::encode_url( $url );
			}
			$items[] = $item;
		}
		if ( count( $items ) < 2 ) {
			return;
		}
		self::print_json(
			[
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			]
		);
	}

	/**
	 * woocommerce_structured_data_product (30): Toman → Rial in every offer, and a description
	 * within Google's 5,000-character limit (WooCommerce copies the short description, which on
	 * the imported products often holds the whole article: "Invalid string length" in the Rich
	 * Results Test, 2026-11-12).
	 *
	 * @param mixed $markup  Product markup.
	 * @param mixed $product Product.
	 * @return mixed
	 */
	public static function filter_product( $markup, $product = null ) {
		if ( ! is_array( $markup ) ) {
			return $markup;
		}
		if ( isset( $markup['description'] ) && is_string( $markup['description'] )
			&& mb_strlen( $markup['description'], 'UTF-8' ) > self::MAX_PRODUCT_DESCRIPTION ) {
			$markup['description'] = Text::shorten( Text::plain( $markup['description'] ), self::MAX_PRODUCT_DESCRIPTION - 10 );
		}
		if ( ! empty( $markup['offers'] ) && is_array( $markup['offers'] ) ) {
			$markup['offers'] = self::to_rial( $markup['offers'] );
		}
		return $markup;
	}

	/**
	 * Walks offers / price specifications and converts IRT amounts to IRR.
	 *
	 * @param array<mixed> $node Markup node.
	 * @return array<mixed>
	 */
	public static function to_rial( array $node ): array {
		$is_irt = isset( $node['priceCurrency'] ) && 'IRT' === strtoupper( (string) $node['priceCurrency'] );
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) ) {
				$node[ $key ] = self::to_rial( $value );
			} elseif ( $is_irt && in_array( $key, [ 'price', 'lowPrice', 'highPrice', 'minPrice', 'maxPrice' ], true ) && is_numeric( $value ) ) {
				$node[ $key ] = wc_format_decimal( (string) ( (float) $value * 10 ), wc_get_price_decimals() );
			}
		}
		if ( $is_irt ) {
			$node['priceCurrency'] = 'IRR';
		}
		return $node;
	}

	/**
	 * Prints one JSON-LD block.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	private static function print_json( array $data ): void {
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false !== $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the HEX flags.
		}
	}
}
