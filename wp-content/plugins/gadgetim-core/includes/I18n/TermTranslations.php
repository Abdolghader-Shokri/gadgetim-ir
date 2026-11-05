<?php
/**
 * Term translations stored as term meta (architecture §3.3, D2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use WP_Post;
use WP_Term;

/**
 * Meta model: _gtm_name_{lang}, _gtm_slug_{lang}, _gtm_desc_{lang}, _gtm_status_{lang}.
 *
 * WP_Term objects are NEVER altered: no filter on get_term / get_terms (wp_update_term()
 * starts from the filtered object and would persist a translated name as the Persian one).
 * Translations are applied in single_term_title, get_the_archive_description,
 * wp_setup_nav_menu_item, term_links-{taxonomy}, woocommerce_get_breadcrumb,
 * woocommerce_layered_nav_term_html and the theme helper gadgetim_term_name().
 */
final class TermTranslations {

	public const FIELDS = [ 'name', 'slug', 'desc', 'status' ];

	public const STATUSES = [ 'published', 'draft' ];

	private static ?array $taxonomies = null;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'single_term_title', [ self::class, 'filter_single_term_title' ], 10, 1 );
		add_filter( 'single_cat_title', [ self::class, 'filter_single_term_title' ], 10, 1 );
		add_filter( 'single_tag_title', [ self::class, 'filter_single_term_title' ], 10, 1 );
		add_filter( 'get_the_archive_description', [ self::class, 'filter_archive_description' ], 10, 1 );
		add_filter( 'wp_setup_nav_menu_item', [ self::class, 'filter_nav_menu_item' ], 10, 1 );
		add_filter( 'woocommerce_get_breadcrumb', [ self::class, 'filter_breadcrumb' ], 10, 2 );
		add_filter( 'woocommerce_layered_nav_term_html', [ self::class, 'filter_layered_nav' ], 10, 4 );
		add_action( 'init', [ self::class, 'hook_term_links' ], 30 );
	}

	/**
	 * init (30, after WooCommerce registered its taxonomies): term_links-{taxonomy} filters.
	 */
	public static function hook_term_links(): void {
		self::$taxonomies = null;
		foreach ( self::taxonomies() as $taxonomy ) {
			add_filter( 'term_links-' . $taxonomy, [ self::class, 'filter_term_links' ], 10, 1 );
		}
	}

	/**
	 * Taxonomies that carry translations: product_cat, product_tag, product_brand, category,
	 * post_tag and the WooCommerce global attribute taxonomies (filter gadgetim_translatable_taxonomies).
	 *
	 * @return string[]
	 */
	public static function taxonomies(): array {
		if ( null !== self::$taxonomies ) {
			return self::$taxonomies;
		}
		$taxonomies = [ 'product_cat', 'product_tag', 'product_brand', 'category', 'post_tag' ];
		if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			$taxonomies = array_merge( $taxonomies, (array) wc_get_attribute_taxonomy_names() );
		}
		/**
		 * Filters the taxonomies that have per-language translation meta.
		 *
		 * @param string[] $taxonomies Taxonomy names.
		 */
		$taxonomies       = (array) apply_filters( 'gadgetim_translatable_taxonomies', $taxonomies );
		self::$taxonomies = array_values( array_unique( array_filter( array_map( 'strval', $taxonomies ) ) ) );
		return self::$taxonomies;
	}

	/**
	 * Whether a taxonomy is translatable.
	 */
	public static function supports( string $taxonomy ): bool {
		return in_array( $taxonomy, self::taxonomies(), true );
	}

	/**
	 * Meta key of a field for a language.
	 */
	public static function key( string $field, string $lang ): string {
		return '_gtm_' . $field . '_' . $lang;
	}

	/**
	 * Raw stored value (no status gate).
	 */
	public static function raw( int $term_id, string $field, string $lang ): string {
		if ( $term_id <= 0 || Languages::DEFAULT === $lang ) {
			return '';
		}
		$value = get_term_meta( $term_id, self::key( $field, $lang ), true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * 'published', 'draft' or ''.
	 */
	public static function status( int $term_id, string $lang ): string {
		$status = self::raw( $term_id, 'status', $lang );
		return in_array( $status, self::STATUSES, true ) ? $status : '';
	}

	/**
	 * Visibility gate for term archives, tiles, hreflang and sitemaps (§3.5).
	 */
	public static function is_published( int $term_id, string $lang ): bool {
		return Languages::DEFAULT === $lang || 'published' === self::status( $term_id, $lang );
	}

	/**
	 * Translated name for the current (or given) language, '' when none is published.
	 * The term object is untouched; functions.php's gadgetim_term_name() falls back to $term->name.
	 */
	public static function name( WP_Term $term, ?string $lang = null ): string {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang || ! self::is_published( $term->term_id, $lang ) ) {
			return '';
		}
		return self::raw( $term->term_id, 'name', $lang );
	}

	/**
	 * Translated name, else the source name.
	 */
	public static function name_or_native( WP_Term $term, ?string $lang = null ): string {
		$name = self::name( $term, $lang );
		return '' !== $name ? $name : (string) $term->name;
	}

	/**
	 * Translated description (wp_kses_post'ed on save), '' when none is published.
	 */
	public static function description( WP_Term $term, ?string $lang = null ): string {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang || ! self::is_published( $term->term_id, $lang ) ) {
			return '';
		}
		return self::raw( $term->term_id, 'desc', $lang );
	}

	/**
	 * Translated description, else the source description.
	 */
	public static function description_or_native( WP_Term $term, ?string $lang = null ): string {
		$description = self::description( $term, $lang );
		return '' !== $description ? $description : (string) $term->description;
	}

	/**
	 * Translated slug (not status-gated: routing).
	 */
	public static function slug( int $term_id, string $lang ): string {
		return self::raw( $term_id, 'slug', $lang );
	}

	/**
	 * All raw translations of a term for a language (admin screens).
	 *
	 * @return array<string,string>
	 */
	public static function all( int $term_id, string $lang ): array {
		$out = [];
		foreach ( self::FIELDS as $field ) {
			$out[ $field ] = self::raw( $term_id, $field, $lang );
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
			'slug'   => sanitize_title( $value ),
			'desc'   => wp_kses_post( $value ),
			'status' => in_array( $value, self::STATUSES, true ) ? $value : '',
			default  => sanitize_text_field( $value ),
		};
	}

	/**
	 * Writes one translated field (sanitised); '' deletes. Slugs are validated by the caller.
	 */
	public static function set( int $term_id, string $field, string $lang, mixed $value ): void {
		if ( $term_id <= 0 || Languages::DEFAULT === $lang || ! in_array( $field, self::FIELDS, true ) || ! Languages::exists( $lang ) ) {
			return;
		}
		$clean = self::sanitize( $field, $value );
		$key   = self::key( $field, $lang );
		if ( '' === $clean ) {
			delete_term_meta( $term_id, $key );
			return;
		}
		update_term_meta( $term_id, $key, $clean );
	}

	/**
	 * single_term_title / single_cat_title / single_tag_title: the queried term's translated name.
	 *
	 * @param mixed $title Term name.
	 */
	public static function filter_single_term_title( $title ) {
		if ( ! is_string( $title ) || ! Context::may_translate() ) {
			return $title;
		}
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || ! self::supports( $term->taxonomy ) ) {
			return $title;
		}
		$name = self::name( $term );
		return '' !== $name ? $name : $title;
	}

	/**
	 * get_the_archive_description: translated term description.
	 *
	 * @param mixed $description Description HTML.
	 */
	public static function filter_archive_description( $description ) {
		if ( ! is_string( $description ) || ! Context::may_translate() ) {
			return $description;
		}
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || ! self::supports( $term->taxonomy ) ) {
			return $description;
		}
		$translated = self::description( $term );
		return '' !== $translated ? wpautop( wp_kses_post( $translated ) ) : $description;
	}

	/**
	 * wp_setup_nav_menu_item: menu labels that follow the term name (empty custom label).
	 * Post-type items are covered by the the_title filter core already applies.
	 *
	 * @param mixed $item Menu item object.
	 */
	public static function filter_nav_menu_item( $item ) {
		if ( ! is_object( $item ) || ! Context::may_translate() ) {
			return $item;
		}
		if ( 'taxonomy' !== ( $item->type ?? '' ) || '' !== (string) ( $item->post_title ?? '' ) ) {
			return $item;
		}
		$taxonomy = (string) ( $item->object ?? '' );
		$term_id  = (int) ( $item->object_id ?? 0 );
		if ( $term_id <= 0 || ! self::supports( $taxonomy ) ) {
			return $item;
		}
		$term = get_term( $term_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$name = self::name( $term );
			if ( '' !== $name ) {
				$item->title = $name;
			}
		}
		return $item;
	}

	/**
	 * term_links-{taxonomy}: get_the_term_list() links for the current post.
	 *
	 * @param mixed $links Array of <a> strings.
	 */
	public static function filter_term_links( $links ) {
		if ( ! is_array( $links ) || ! $links || ! Context::may_translate() ) {
			return $links;
		}
		$taxonomy = substr( (string) current_filter(), strlen( 'term_links-' ) );
		$post_id  = (int) get_the_ID();
		if ( $post_id <= 0 || '' === $taxonomy ) {
			return $links;
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return $links;
		}
		$names = [];
		foreach ( $terms as $term ) {
			$translated = self::name( $term );
			if ( '' !== $translated ) {
				$names[ $term->name ] = $translated;
			}
		}
		if ( ! $names ) {
			return $links;
		}
		foreach ( $links as $i => $link ) {
			if ( ! is_string( $link ) ) {
				continue;
			}
			$links[ $i ] = preg_replace_callback(
				'#(<a\b[^>]*>)(.*?)(</a>)#su',
				static function ( array $m ) use ( $names ): string {
					$inner = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
					return isset( $names[ $inner ] ) ? $m[1] . esc_html( $names[ $inner ] ) . $m[3] : $m[0];
				},
				$link
			) ?? $link;
		}
		return $links;
	}

	/**
	 * woocommerce_get_breadcrumb: translates term crumbs by matching their links.
	 *
	 * @param mixed $crumbs     [ [ name, link ], ... ].
	 * @param mixed $breadcrumb WC_Breadcrumb or null.
	 */
	public static function filter_breadcrumb( $crumbs, $breadcrumb = null ) {
		if ( ! is_array( $crumbs ) || ! $crumbs || ! Context::may_translate() ) {
			return $crumbs;
		}
		$candidates = self::breadcrumb_terms();
		if ( ! $candidates ) {
			return $crumbs;
		}
		$by_link = [];
		foreach ( $candidates as $term ) {
			$link = get_term_link( $term );
			if ( is_string( $link ) ) {
				$by_link[ untrailingslashit( $link ) ] = $term;
			}
		}
		foreach ( $crumbs as $i => $crumb ) {
			if ( ! is_array( $crumb ) || empty( $crumb[1] ) || ! is_string( $crumb[1] ) ) {
				continue;
			}
			$key = untrailingslashit( $crumb[1] );
			if ( isset( $by_link[ $key ] ) ) {
				$name = self::name( $by_link[ $key ] );
				if ( '' !== $name ) {
					$crumbs[ $i ][0] = $name;
				}
			}
		}
		return $crumbs;
	}

	/**
	 * woocommerce_layered_nav_term_html.
	 *
	 * @param mixed  $html  Term HTML.
	 * @param mixed  $term  WP_Term.
	 * @param mixed  $link  Link.
	 * @param mixed  $count Count.
	 */
	public static function filter_layered_nav( $html, $term = null, $link = '', $count = 0 ) {
		if ( ! is_string( $html ) || ! $term instanceof WP_Term || ! Context::may_translate() ) {
			return $html;
		}
		$name = self::name( $term );
		if ( '' === $name ) {
			return $html;
		}
		return str_replace( esc_html( $term->name ), esc_html( $name ), $html );
	}

	/**
	 * Terms that may appear in the breadcrumb of the current page: the queried term with its
	 * ancestors, and for a single product its categories with their ancestors.
	 *
	 * @return WP_Term[]
	 */
	private static function breadcrumb_terms(): array {
		$terms = [];
		$add   = static function ( WP_Term $term ) use ( &$terms ): void {
			$terms[ $term->term_id ] = $term;
			if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
				foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor_id ) {
					$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );
					if ( $ancestor instanceof WP_Term ) {
						$terms[ $ancestor->term_id ] = $ancestor;
					}
				}
			}
		};
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term && self::supports( $queried->taxonomy ) ) {
			$add( $queried );
		} elseif ( $queried instanceof WP_Post ) {
			foreach ( get_object_taxonomies( $queried->post_type ) as $taxonomy ) {
				if ( ! self::supports( $taxonomy ) || ! is_taxonomy_hierarchical( $taxonomy ) ) {
					continue;
				}
				$post_terms = get_the_terms( $queried->ID, $taxonomy );
				if ( is_array( $post_terms ) ) {
					foreach ( $post_terms as $term ) {
						$add( $term );
					}
				}
			}
		}
		return array_values( $terms );
	}
}
