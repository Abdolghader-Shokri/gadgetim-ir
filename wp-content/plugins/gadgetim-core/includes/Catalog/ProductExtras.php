<?php
/**
 * Product extras: subtitle, short title, spec table, FAQ, guarantee override, view counter
 * (architecture §3.3, §5.10, §6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Context;
use WC_Product;
use WP_Post;
use WP_Query;

/**
 * Storage and read API for the `_gtm_*` product extras. Persian values live in the base
 * key, translations in `_gtm_{key}_{lang}` (written by the I18n metabox); readers fall back
 * to Persian field by field and return the source while translations are suspended.
 */
final class ProductExtras {

	public const META_SUBTITLE    = '_gtm_subtitle';
	public const META_SHORT_TITLE = '_gtm_short_title';
	public const META_SPECS       = '_gtm_specs';
	public const META_FAQ         = '_gtm_faq';
	public const META_GUARANTEE   = '_gtm_guarantee';
	public const META_VIEWS       = '_gtm_views';
	public const META_SEO_TITLE   = '_gtm_seo_title';
	public const META_SEO_DESC    = '_gtm_seo_desc';
	public const META_IMPORT_ID   = '_gtm_import_old_id';

	/** Keys that have per-language variants. */
	public const TRANSLATABLE = [ self::META_SUBTITLE, self::META_SPECS, self::META_FAQ, self::META_GUARANTEE, self::META_SEO_TITLE, self::META_SEO_DESC ];

	public const MAX_SPEC_ROWS = 80;
	public const MAX_FAQ_ROWS  = 40;

	/** Subtitles shorter than this (characters) are not shown (design spec §6.3). */
	public const MIN_SUBTITLE_LENGTH = 12;

	/**
	 * Hooks: meta registration, the `_gtm_views = 0` guarantee on creation, the admin metabox.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ], 20 );
		add_action( 'woocommerce_new_product', [ self::class, 'ensure_views_meta' ], 10, 1 );
		add_action( 'wp_insert_post', [ self::class, 'on_insert_post' ], 10, 3 );

		if ( is_admin() ) {
			Admin\ExtrasMetabox::register();
		}
	}

	/**
	 * init: register_post_meta for sanitisation and authorisation only (never exposed in REST, §4.3 guard).
	 */
	public static function register_meta(): void {
		if ( ! post_type_exists( 'product' ) ) {
			return;
		}
		$auth = static function (): bool {
			return current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
		};
		$text = [ self::META_SUBTITLE, self::META_SHORT_TITLE, self::META_SEO_TITLE, self::META_SEO_DESC ];
		foreach ( $text as $key ) {
			register_post_meta(
				'product',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => [ self::class, 'sanitize_text' ],
					'auth_callback'     => $auth,
				]
			);
		}
		register_post_meta(
			'product',
			self::META_SPECS,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_specs' ],
				'auth_callback'     => $auth,
			]
		);
		register_post_meta(
			'product',
			self::META_FAQ,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_faq' ],
				'auth_callback'     => $auth,
			]
		);
		register_post_meta(
			'product',
			self::META_GUARANTEE,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_guarantee' ],
				'auth_callback'     => $auth,
			]
		);
		register_post_meta(
			'product',
			self::META_VIEWS,
			[
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => 0,
				'sanitize_callback' => [ self::class, 'sanitize_views' ],
				'auth_callback'     => $auth,
			]
		);
	}

	/**
	 * woocommerce_new_product: `_gtm_views` is written as 0 so the popularity join never drops products (§6).
	 */
	public static function ensure_views_meta( int $product_id ): void {
		$product_id = absint( $product_id );
		if ( $product_id && '' === (string) get_post_meta( $product_id, self::META_VIEWS, true ) ) {
			add_post_meta( $product_id, self::META_VIEWS, 0, true );
		}
	}

	/**
	 * wp_insert_post: same guarantee for products created outside the WooCommerce CRUD.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Whether this is an update.
	 */
	public static function on_insert_post( int $post_id, WP_Post $post, bool $update ): void {
		if ( $update || 'product' !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}
		self::ensure_views_meta( $post_id );
	}

	// -------------------------------------------------------------------------------------
	// Read API (theme-facing; the theme guards every call with class_exists()).
	// -------------------------------------------------------------------------------------

	/**
	 * Short title: the `_gtm_short_title` override, else the title with the boilerplate stripped.
	 * For a non-Persian language the translated title (`_gtm_title_{lang}`) wins when present.
	 *
	 * @param WC_Product|WP_Post|int $product Product.
	 * @param string|null            $lang    Language code; current language by default.
	 */
	public static function short_title( WC_Product|WP_Post|int $product, ?string $lang = null ): string {
		$id = self::id( $product );
		if ( ! $id ) {
			return '';
		}
		$suffix = self::suffix( $lang );
		if ( '' !== $suffix ) {
			$translated = (string) get_post_meta( $id, '_gtm_title' . $suffix, true );
			if ( '' !== trim( $translated ) ) {
				return TitleCleaner::short( $translated );
			}
		}
		$override = (string) get_post_meta( $id, self::META_SHORT_TITLE, true );
		if ( '' !== trim( $override ) ) {
			return trim( $override );
		}
		$title = $product instanceof WC_Product ? $product->get_name( 'edit' ) : get_post_field( 'post_title', $id, 'raw' );
		return TitleCleaner::short( (string) $title );
	}

	/**
	 * The automatic short title for a raw title (no product needed).
	 */
	public static function short_title_fallback( string $title ): string {
		return TitleCleaner::short( $title );
	}

	/**
	 * Subtitle (the "deck" under the title), or '' when absent or not display-worthy
	 * (shorter than 12 characters, or all-caps Latin junk such as "KORANCO ORG").
	 */
	public static function subtitle( WC_Product|WP_Post|int $product, ?string $lang = null ): string {
		$raw = self::raw_subtitle( $product, $lang );
		return self::is_display_worthy_subtitle( $raw ) ? $raw : '';
	}

	/**
	 * Subtitle exactly as stored (admin and importer use this).
	 */
	public static function raw_subtitle( WC_Product|WP_Post|int $product, ?string $lang = null ): string {
		return trim( (string) self::meta( $product, self::META_SUBTITLE, $lang ) );
	}

	/**
	 * Display rule for subtitles (design spec §6.3).
	 */
	public static function is_display_worthy_subtitle( string $subtitle ): bool {
		$subtitle = trim( $subtitle );
		if ( mb_strlen( $subtitle ) < self::MIN_SUBTITLE_LENGTH ) {
			return false;
		}
		// All-caps Latin (digits, spaces and punctuation allowed) is a model code, not a sentence.
		if ( preg_match( '/^[A-Z0-9\s\-_.,&\/+()]+$/', $subtitle ) && preg_match( '/[A-Z]/', $subtitle ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Spec rows as [ [ 'label', 'value' ], … ] for the language (Persian fallback).
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function specs( WC_Product|WP_Post|int $product, ?string $lang = null ): array {
		return self::decode_specs( (string) self::meta( $product, self::META_SPECS, $lang ) );
	}

	/**
	 * FAQ rows as [ [ 'q' => …, 'a' => … ], … ] for the language (Persian fallback).
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function faq( WC_Product|WP_Post|int $product, ?string $lang = null ): array {
		return self::decode_faq( (string) self::meta( $product, self::META_FAQ, $lang ) );
	}

	/**
	 * Guarantee text (HTML): the product override, else the `trust.guarantee` setting.
	 */
	public static function guarantee( WC_Product|WP_Post|int $product, ?string $lang = null ): string {
		$own = trim( (string) self::meta( $product, self::META_GUARANTEE, $lang ) );
		if ( '' !== $own ) {
			return $own;
		}
		if ( class_exists( Settings::class ) ) {
			return Settings::get_lang( 'trust.guarantee', $lang );
		}
		return '';
	}

	/**
	 * Whether the product has its own guarantee text.
	 */
	public static function has_own_guarantee( WC_Product|WP_Post|int $product ): bool {
		return '' !== trim( (string) self::meta( $product, self::META_GUARANTEE, 'fa' ) );
	}

	/**
	 * Sampled view counter.
	 */
	public static function views( WC_Product|WP_Post|int $product ): int {
		$id = self::id( $product );
		return $id ? (int) get_post_meta( $id, self::META_VIEWS, true ) : 0;
	}

	/**
	 * SEO title override (import stores the full old title here, §5.7).
	 */
	public static function seo_title( WC_Product|WP_Post|int $product, ?string $lang = null ): string {
		return trim( (string) self::meta( $product, self::META_SEO_TITLE, $lang ) );
	}

	/**
	 * SEO description override.
	 */
	public static function seo_description( WC_Product|WP_Post|int $product, ?string $lang = null ): string {
		return trim( (string) self::meta( $product, self::META_SEO_DESC, $lang ) );
	}

	/**
	 * Most viewed published products, in-stock first when requested (home «پربازدیدها» rail).
	 *
	 * @param int  $limit    Number of IDs.
	 * @param bool $in_stock Only products in stock.
	 * @return int[]
	 */
	public static function most_viewed( int $limit = 8, bool $in_stock = true ): array {
		$args = [
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => max( 1, $limit ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'meta_key'               => self::META_VIEWS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'                => [
				'meta_value_num' => 'DESC',
				'date'           => 'DESC',
			],
			'gadgetim_product_loop'  => 'most_viewed',
		];
		if ( $in_stock ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_stock_status',
					'value' => 'instock',
				],
			];
		}
		if ( taxonomy_exists( 'product_visibility' ) ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => [ 'exclude-from-catalog' ],
					'operator' => 'NOT IN',
				],
			];
		}

		/**
		 * Filters the query arguments of the most-viewed products list.
		 *
		 * @param array $args WP_Query arguments.
		 */
		$args  = (array) apply_filters( 'gadgetim_most_viewed_args', $args );
		$query = new WP_Query( $args );
		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Raw meta for a key, translated when a language variant exists and translations are not suspended.
	 *
	 * @param WC_Product|WP_Post|int $product Product.
	 * @param string                 $key     Base meta key (e.g. '_gtm_specs').
	 * @param string|null            $lang    Language code; current language by default.
	 */
	public static function meta( WC_Product|WP_Post|int $product, string $key, ?string $lang = null ): mixed {
		$id = self::id( $product );
		if ( ! $id ) {
			return '';
		}
		$suffix = in_array( $key, self::TRANSLATABLE, true ) ? self::suffix( $lang ) : '';
		if ( '' !== $suffix ) {
			$translated = get_post_meta( $id, $key . $suffix, true );
			if ( is_string( $translated ) && '' !== trim( $translated ) ) {
				return $translated;
			}
		}
		return get_post_meta( $id, $key, true );
	}

	/**
	 * Product ID from a product object, post or ID (0 when unknown).
	 */
	public static function id( WC_Product|WP_Post|int $product ): int {
		if ( $product instanceof WC_Product ) {
			return (int) $product->get_id();
		}
		if ( $product instanceof WP_Post ) {
			return (int) $product->ID;
		}
		return absint( $product );
	}

	// -------------------------------------------------------------------------------------
	// Encoding / decoding and sanitisers (§5.10).
	// -------------------------------------------------------------------------------------

	/**
	 * Decodes a specs JSON string into [ [ label, value ], … ]. Accepts the pair list and
	 * the { label, value } object list; anything else yields [].
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function decode_specs( string $json ): array {
		$json = trim( $json );
		if ( '' === $json ) {
			return [];
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return [];
		}
		return self::normalize_specs( $data );
	}

	/**
	 * Decodes a FAQ JSON string into [ [ 'q' => …, 'a' => … ], … ].
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function decode_faq( string $json ): array {
		$json = trim( $json );
		if ( '' === $json ) {
			return [];
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return [];
		}
		return self::normalize_faq( $data );
	}

	/**
	 * Encodes spec rows as compact JSON ('' when empty).
	 *
	 * @param array $rows Rows in any accepted shape.
	 */
	public static function encode_specs( array $rows ): string {
		$rows = self::normalize_specs( $rows );
		if ( ! $rows ) {
			return '';
		}
		$json = wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $json ? '' : $json;
	}

	/**
	 * Encodes FAQ rows as compact JSON ('' when empty).
	 */
	public static function encode_faq( array $rows ): string {
		$rows = self::normalize_faq( $rows );
		if ( ! $rows ) {
			return '';
		}
		$json = wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $json ? '' : $json;
	}

	/**
	 * Meta sanitizer for the text keys.
	 */
	public static function sanitize_text( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Meta sanitizer for `_gtm_specs`: JSON string, pair list, object list or the metabox
	 * shape [ 'label' => [...], 'value' => [...] ] → normalised JSON string.
	 */
	public static function sanitize_specs( mixed $value ): string {
		if ( is_string( $value ) ) {
			return self::encode_specs( self::decode_specs( $value ) );
		}
		if ( is_array( $value ) ) {
			return self::encode_specs( $value );
		}
		return '';
	}

	/**
	 * Meta sanitizer for `_gtm_faq`: JSON string, object list or the metabox shape
	 * [ 'q' => [...], 'a' => [...] ] → normalised JSON string.
	 */
	public static function sanitize_faq( mixed $value ): string {
		if ( is_string( $value ) ) {
			return self::encode_faq( self::decode_faq( $value ) );
		}
		if ( is_array( $value ) ) {
			return self::encode_faq( $value );
		}
		return '';
	}

	/**
	 * Meta sanitizer for `_gtm_guarantee` (limited HTML).
	 */
	public static function sanitize_guarantee( mixed $value ): string {
		return is_scalar( $value ) ? trim( wp_kses_post( (string) $value ) ) : '';
	}

	/**
	 * Meta sanitizer for `_gtm_views`.
	 */
	public static function sanitize_views( mixed $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Normalises spec rows: each cell sanitize_text_field, empty rows dropped, capped.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function normalize_specs( array $data ): array {
		$rows = [];
		// Metabox shape: parallel arrays.
		if ( isset( $data['label'] ) && is_array( $data['label'] ) ) {
			$values = isset( $data['value'] ) && is_array( $data['value'] ) ? array_values( $data['value'] ) : [];
			foreach ( array_values( $data['label'] ) as $i => $label ) {
				$rows[] = [ $label, $values[ $i ] ?? '' ];
			}
			$data = $rows;
			$rows = [];
		}
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( array_key_exists( 'label', $row ) || array_key_exists( 'value', $row ) ) {
				$label = $row['label'] ?? '';
				$value = $row['value'] ?? '';
			} else {
				$row   = array_values( $row );
				$label = $row[0] ?? '';
				$value = $row[1] ?? '';
			}
			$label = is_scalar( $label ) ? sanitize_text_field( (string) $label ) : '';
			$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			$label = rtrim( trim( $label ), ':：' );
			if ( '' === $label && '' === $value ) {
				continue;
			}
			$rows[] = [ mb_substr( $label, 0, 120 ), mb_substr( $value, 0, 400 ) ];
			if ( count( $rows ) >= self::MAX_SPEC_ROWS ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * Normalises FAQ rows: question sanitize_text_field, answer wp_kses_post, empty rows dropped, capped.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function normalize_faq( array $data ): array {
		$rows = [];
		if ( isset( $data['q'] ) && is_array( $data['q'] ) ) {
			$answers = isset( $data['a'] ) && is_array( $data['a'] ) ? array_values( $data['a'] ) : [];
			foreach ( array_values( $data['q'] ) as $i => $q ) {
				$rows[] = [
					'q' => $q,
					'a' => $answers[ $i ] ?? '',
				];
			}
			$data = $rows;
			$rows = [];
		}
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( array_key_exists( 'q', $row ) || array_key_exists( 'a', $row ) ) {
				$q = $row['q'] ?? '';
				$a = $row['a'] ?? '';
			} else {
				$row = array_values( $row );
				$q   = $row[0] ?? '';
				$a   = $row[1] ?? '';
			}
			$q = is_scalar( $q ) ? sanitize_text_field( (string) $q ) : '';
			$a = is_scalar( $a ) ? trim( wp_kses_post( (string) $a ) ) : '';
			if ( '' === $q && '' === $a ) {
				continue;
			}
			$rows[] = [
				'q' => mb_substr( $q, 0, 300 ),
				'a' => $a,
			];
			if ( count( $rows ) >= self::MAX_FAQ_ROWS ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * Meta-key suffix for a language: '' for Persian, while translations are suspended, or
	 * when the language is unknown; '_en' / '_ar' otherwise.
	 */
	private static function suffix( ?string $lang ): string {
		if ( class_exists( Context::class ) && Context::translations_suspended() ) {
			return '';
		}
		$lang = $lang ?? ( function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa' );
		$lang = sanitize_key( $lang );
		if ( '' === $lang || 'fa' === $lang || ! preg_match( '/^[a-z]{2,3}$/', $lang ) ) {
			return '';
		}
		return '_' . $lang;
	}
}
