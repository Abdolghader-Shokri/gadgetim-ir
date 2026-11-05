<?php
/**
 * Post translations stored as meta on the same post (architecture §3.3, D2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use WP_Post;

/**
 * Meta model: _gtm_title_{lang}, _gtm_slug_{lang}, _gtm_excerpt_{lang}, _gtm_content_{lang},
 * _gtm_subtitle_{lang}, _gtm_seo_title_{lang}, _gtm_seo_desc_{lang}, _gtm_status_{lang}.
 * A translation "exists" when _gtm_status_{lang} === 'published'; draft translations are
 * invisible on the storefront except to users who may edit the post (preview).
 *
 * Translations are applied at OUTPUT POINTS ONLY: the_title, the_content, get_the_excerpt,
 * single_post_title, document_title_parts, woocommerce_page_title and the WooCommerce view
 * getters in storefront/customer-email context. Never get_post or edit-context getters.
 */
final class PostTranslations {

	public const FIELDS = [ 'title', 'slug', 'excerpt', 'content', 'subtitle', 'seo_title', 'seo_desc', 'status' ];

	public const STATUSES = [ 'published', 'draft' ];

	/** @var array<int,true> Posts whose draft translation may be shown in this request (editor preview). */
	private static array $preview = [];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'the_title', [ self::class, 'filter_title' ], 10, 2 );
		add_filter( 'the_content', [ self::class, 'filter_content' ], 1, 1 );
		add_filter( 'get_the_excerpt', [ self::class, 'filter_excerpt' ], 1, 2 );
		add_filter( 'single_post_title', [ self::class, 'filter_title' ], 10, 2 );
		add_filter( 'document_title_parts', [ self::class, 'filter_document_title' ], 10, 1 );
		add_filter( 'woocommerce_page_title', [ self::class, 'filter_wc_page_title' ], 10, 1 );
		add_filter( 'woocommerce_product_get_name', [ self::class, 'filter_product_name' ], 10, 2 );
		add_filter( 'woocommerce_product_get_description', [ self::class, 'filter_product_description' ], 10, 2 );
		add_filter( 'woocommerce_product_get_short_description', [ self::class, 'filter_product_short_description' ], 10, 2 );
	}

	/**
	 * Post types that carry translations (filter gadgetim_translatable_post_types).
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		/**
		 * Filters the post types that have per-language translation meta.
		 *
		 * @param string[] $types Post types.
		 */
		$types = (array) apply_filters( 'gadgetim_translatable_post_types', [ 'product', 'page', 'post' ] );
		return array_values( array_unique( array_filter( array_map( 'strval', $types ) ) ) );
	}

	/**
	 * Whether a post type is translatable.
	 */
	public static function supports( string $post_type ): bool {
		return in_array( $post_type, self::post_types(), true );
	}

	/**
	 * Meta key of a field for a language.
	 */
	public static function key( string $field, string $lang ): string {
		return '_gtm_' . $field . '_' . $lang;
	}

	/**
	 * Raw stored value (no status gate). Reads the post-meta cache WP_Query already primed.
	 */
	public static function raw( int $post_id, string $field, string $lang ): string {
		if ( $post_id <= 0 || Languages::DEFAULT === $lang ) {
			return '';
		}
		$value = get_post_meta( $post_id, self::key( $field, $lang ), true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Translation status: 'published', 'draft' or '' (absent).
	 */
	public static function status( int $post_id, string $lang ): string {
		$status = self::raw( $post_id, 'status', $lang );
		return in_array( $status, self::STATUSES, true ) ? $status : '';
	}

	/**
	 * The visibility gate (§3.5): a translation exists when its status is 'published'
	 * (or the post is in editor preview for this request).
	 */
	public static function is_published( int $post_id, string $lang ): bool {
		if ( Languages::DEFAULT === $lang ) {
			return true;
		}
		return 'published' === self::status( $post_id, $lang ) || isset( self::$preview[ $post_id ] );
	}

	/**
	 * Lets a draft translation render for one post in this request (translator preview).
	 */
	public static function allow_preview( int $post_id ): void {
		if ( $post_id > 0 ) {
			self::$preview[ $post_id ] = true;
		}
	}

	/**
	 * Whether a post is in preview mode for this request.
	 */
	public static function is_preview( int $post_id ): bool {
		return isset( self::$preview[ $post_id ] );
	}

	/**
	 * A translated field, '' when absent or (by default) when the translation is not published.
	 *
	 * @param int         $post_id        Post ID.
	 * @param string      $field          title | slug | excerpt | content | subtitle | seo_title | seo_desc.
	 * @param string|null $lang           Language; current by default. Persian always returns ''.
	 * @param bool        $published_only Require _gtm_status_{lang} = published.
	 */
	public static function get( int $post_id, string $field, ?string $lang = null, bool $published_only = true ): string {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang || $post_id <= 0 ) {
			return '';
		}
		if ( $published_only && ! self::is_published( $post_id, $lang ) ) {
			return '';
		}
		return self::raw( $post_id, $field, $lang );
	}

	/**
	 * Translated title, else the Persian title.
	 */
	public static function title( int $post_id, ?string $lang = null ): string {
		$title = self::get( $post_id, 'title', $lang );
		return '' !== $title ? $title : (string) get_post_field( 'post_title', $post_id, 'raw' );
	}

	/**
	 * Translated slug (not status-gated: routing needs draft slugs for previews).
	 */
	public static function slug( int $post_id, string $lang ): string {
		return self::raw( $post_id, 'slug', $lang );
	}

	/**
	 * Every translation of a post for a language (raw, for admin screens).
	 *
	 * @return array<string,string>
	 */
	public static function all( int $post_id, string $lang ): array {
		$out = [];
		foreach ( self::FIELDS as $field ) {
			$out[ $field ] = self::raw( $post_id, $field, $lang );
		}
		return $out;
	}

	/**
	 * Sanitises one field per architecture §5.10.
	 */
	public static function sanitize( string $field, mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = (string) $value;
		return match ( $field ) {
			'slug'              => sanitize_title( $value ),
			'excerpt', 'content' => wp_kses_post( $value ),
			'status'            => in_array( $value, self::STATUSES, true ) ? $value : '',
			default             => sanitize_text_field( $value ),
		};
	}

	/**
	 * Writes one translated field (sanitised); an empty value deletes the meta.
	 * Slugs must be validated with Slugs::validate() by the caller first.
	 */
	public static function set( int $post_id, string $field, string $lang, mixed $value ): void {
		if ( $post_id <= 0 || Languages::DEFAULT === $lang || ! in_array( $field, self::FIELDS, true ) || ! Languages::exists( $lang ) ) {
			return;
		}
		$clean = self::sanitize( $field, $value );
		$key   = self::key( $field, $lang );
		if ( '' === $clean ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $clean );
	}

	/**
	 * the_title / single_post_title filter (view only).
	 *
	 * @param mixed $title Title.
	 * @param mixed $post  Post ID (the_title) or WP_Post (single_post_title).
	 */
	public static function filter_title( $title, $post = 0 ) {
		if ( ! is_string( $title ) || ! Context::may_translate() ) {
			return $title;
		}
		$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
		if ( $post_id <= 0 ) {
			return $title;
		}
		$translated = self::get( $post_id, 'title' );
		return '' !== $translated ? $translated : $title;
	}

	/**
	 * the_content filter at priority 1 (front end only): the translated body replaces the
	 * Persian one before wpautop/shortcodes run. Empty translations fall back to Persian.
	 *
	 * @param mixed $content Content.
	 */
	public static function filter_content( $content ) {
		if ( ! is_string( $content ) || ! Context::may_translate() ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! self::supports( (string) get_post_type( $post_id ) ) ) {
			return $content;
		}
		$translated = self::get( $post_id, 'content' );
		return '' !== $translated ? $translated : $content;
	}

	/**
	 * get_the_excerpt filter at priority 1.
	 *
	 * @param mixed        $excerpt Excerpt.
	 * @param WP_Post|null $post    Post.
	 */
	public static function filter_excerpt( $excerpt, $post = null ) {
		if ( ! is_string( $excerpt ) || ! $post instanceof WP_Post || ! Context::may_translate() ) {
			return $excerpt;
		}
		$translated = self::get( $post->ID, 'excerpt' );
		return '' !== $translated ? $translated : $excerpt;
	}

	/**
	 * document_title_parts: translated <title> for singular pages and the shop archive.
	 *
	 * @param mixed $parts Title parts.
	 */
	public static function filter_document_title( $parts ) {
		if ( ! is_array( $parts ) || ! Context::may_translate() ) {
			return $parts;
		}
		$post_id = 0;
		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
		} elseif ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
			$post_id = (int) wc_get_page_id( 'shop' );
		}
		if ( $post_id > 0 ) {
			$seo_title = self::get( $post_id, 'seo_title' );
			$title     = '' !== $seo_title ? $seo_title : self::get( $post_id, 'title' );
			if ( '' !== $title ) {
				$parts['title'] = $title;
			}
		}
		return $parts;
	}

	/**
	 * woocommerce_page_title: translated shop page title.
	 *
	 * @param mixed $title Title.
	 */
	public static function filter_wc_page_title( $title ) {
		if ( ! is_string( $title ) || ! Context::may_translate() || ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return $title;
		}
		$translated = self::get( (int) wc_get_page_id( 'shop' ), 'title' );
		return '' !== $translated ? $translated : $title;
	}

	/**
	 * woocommerce_product_get_name (view context) in storefront / customer-email output only.
	 *
	 * @param mixed  $name    Name.
	 * @param object $product Product.
	 */
	public static function filter_product_name( $name, $product = null ) {
		return self::product_field( $name, $product, 'title' );
	}

	/**
	 * woocommerce_product_get_description (view context).
	 *
	 * @param mixed  $description Description.
	 * @param object $product     Product.
	 */
	public static function filter_product_description( $description, $product = null ) {
		return self::product_field( $description, $product, 'content' );
	}

	/**
	 * woocommerce_product_get_short_description (view context).
	 *
	 * @param mixed  $description Short description.
	 * @param object $product     Product.
	 */
	public static function filter_product_short_description( $description, $product = null ) {
		return self::product_field( $description, $product, 'excerpt' );
	}

	/**
	 * Shared body of the WooCommerce getter filters: only context-less view calls that
	 * originate in storefront or customer-email rendering are translated (§3.3).
	 */
	private static function product_field( mixed $value, $product, string $field ): mixed {
		if ( ! is_string( $value ) || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $value;
		}
		if ( ! Context::is_customer_output() || ! Context::may_translate() ) {
			return $value;
		}
		$product_id = (int) $product->get_id();
		if ( $product_id <= 0 ) {
			return $value;
		}
		// Variations carry no translation meta of their own: use the parent's.
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) && method_exists( $product, 'get_parent_id' ) ) {
			if ( 'title' === $field ) {
				return $value;
			}
			$product_id = (int) $product->get_parent_id();
		}
		$translated = self::get( $product_id, $field );
		return '' !== $translated ? $translated : $value;
	}
}
