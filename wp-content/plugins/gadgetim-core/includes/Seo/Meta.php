<?php
/**
 * <head> tags: meta description, canonical, Open Graph and Twitter cards; archive titles.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\I18n\Hreflang;
use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Digits;
use WP_Post;
use WP_Term;

/**
 * One canonical per indexable page (self-referencing, absolute, language-aware via
 * Hreflang::current_object_url()), none on noindex pages so the two signals never disagree.
 * Descriptions come from the stored SEO description, then the excerpt, then the content; term
 * archives fall back to a per-language template. Everything is computed once per request.
 */
final class Meta {

	/**
	 * Attachment meta: the language of the words drawn on a branded image (title box of a page's
	 * featured image), e.g. 'fa'. Empty for photos without words.
	 */
	public const META_TEXT_LANG = '_gtm_text_lang';

	/**
	 * Title and description templates of term archives, per language. %1$s = name,
	 * %2$s = number of products (locale digits).
	 */
	private const TEMPLATES = [
		'cat_title'        => [
			'fa' => 'قیمت و خرید %1$s اصل',
			'en' => '%1$s: prices and original products',
			'ar' => '%1$s: الأسعار والمنتجات الأصلية',
		],
		'cat_parent_title' => [
			'fa' => '%1$s: خرید و قیمت انواع کالای اصل',
			'en' => '%1$s: original products and prices',
			'ar' => '%1$s: المنتجات الأصلية والأسعار',
		],
		'brand_title'      => [
			'fa' => 'محصولات اصل %1$s: قیمت و خرید',
			'en' => 'Original %1$s products and prices',
			'ar' => 'منتجات %1$s الأصلية وأسعارها',
		],
		'cat_desc'         => [
			'fa' => 'قیمت و خرید %1$s اصل از گجتیم: %2$s مدل با ضمانت اصالت کالا و تست سلامت ۷ روزه؛ ارسال با تیپاکس، دکاپست و پست پیشتاز به سراسر ایران.',
			'en' => 'Prices of original %1$s at Gadgetim, with an authenticity guarantee and a 7-day return for faulty items, shipped by Tipax and post across Iran. Products: %2$s.',
			'ar' => 'أسعار %1$s الأصلية في Gadgetim مع ضمان الأصالة وإرجاع المنتج المعيب خلال ٧ أيام، والشحن إلى جميع أنحاء إيران. عدد المنتجات: %2$s.',
		],
		'cat_parent_desc'  => [
			'fa' => '%1$s در گجتیم: %2$s محصول اصل با ضمانت اصالت کالا و تست سلامت ۷ روزه؛ مقایسه قیمت و خرید آنلاین با ارسال به سراسر ایران.',
			'en' => '%1$s at Gadgetim: original products with an authenticity guarantee and a 7-day return for faulty items, delivered across Iran. Products: %2$s.',
			'ar' => '%1$s في Gadgetim: منتجات أصلية مع ضمان الأصالة وإرجاع المنتج المعيب خلال ٧ أيام، والشحن إلى جميع أنحاء إيران. عدد المنتجات: %2$s.',
		],
		'brand_desc'       => [
			'fa' => 'محصولات اصل %1$s در گجتیم: %2$s محصول با ضمانت اصالت کالا و تست سلامت ۷ روزه؛ مقایسه قیمت و خرید آنلاین با ارسال به سراسر ایران.',
			'en' => 'Original %1$s products at Gadgetim, with an authenticity guarantee and a 7-day return for faulty items, delivered across Iran. Products: %2$s.',
			'ar' => 'منتجات %1$s الأصلية في Gadgetim مع ضمان الأصالة وإرجاع المنتج المعيب خلال ٧ أيام، والشحن إلى جميع أنحاء إيران. عدد المنتجات: %2$s.',
		],
		'tag_desc'         => [
			'fa' => 'کالاهای «%1$s» در گجتیم: %2$s کالای اصل با ضمانت اصالت کالا و ارسال به سراسر ایران.',
			'en' => 'Products for "%1$s" at Gadgetim: original items with an authenticity guarantee, delivered across Iran. Products: %2$s.',
			'ar' => 'منتجات «%1$s» في Gadgetim: منتجات أصلية مع ضمان الأصالة والشحن إلى جميع أنحاء إيران. عدد المنتجات: %2$s.',
		],
		'shop_desc'        => [
			'fa' => 'همه کالاهای گجتیم: هندزفری و ایرپاد، پاوربانک، شارژر و کابل، اسپیکر، ساعت هوشمند و گجت‌های کاربردی اصل با ضمانت اصالت کالا و ارسال به سراسر ایران.',
			'en' => 'Every product at Gadgetim: original earbuds, power banks, chargers, cables, speakers, smartwatches and handy gadgets, with an authenticity guarantee.',
			'ar' => 'جميع منتجات Gadgetim: سماعات وباور بانك وشواحن وكابلات ومكبرات صوت وساعات ذكية أصلية مع ضمان الأصالة.',
		],
		'page_n'           => [
			'fa' => '(صفحه %1$s)',
			'en' => '(page %1$s)',
			'ar' => '(الصفحة %1$s)',
		],
		'tracking_desc'    => [
			'fa' => 'پیگیری سفارش در گجتیم: شماره سفارش و شماره موبایل یا ایمیلی را که هنگام خرید وارد کردید بنویسید و وضعیت آماده‌سازی و ارسال سفارش را ببینید.',
			'en' => 'Track your Gadgetim order: enter the order number and the mobile number or email you used at checkout to see its preparation and shipping status.',
			'ar' => 'تتبع طلبك في Gadgetim: أدخل رقم الطلب ورقم الجوال أو البريد الإلكتروني الذي استخدمته عند الشراء لمعرفة حالة تجهيز الطلب وشحنه.',
		],
	];

	/**
	 * og:locale per language (Facebook's list wants a region for Arabic).
	 */
	private const OG_LOCALES = [
		'fa' => 'fa_IR',
		'en' => 'en_US',
		'ar' => 'ar_AR',
	];

	/** @var array<string,mixed>|null Per-request cache. */
	private static ?array $data = null;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		// Core prints a canonical only on singular pages and without the language context.
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', [ self::class, 'print_head' ], 1 );
		add_filter( 'document_title_parts', [ self::class, 'filter_title_parts' ], 30, 1 );
		add_filter( 'get_canonical_url', [ self::class, 'filter_canonical_url' ], 20, 1 );
		// Demo copy («لورم ایپسوم») left in a category description never reaches the page.
		add_filter( 'get_the_archive_description', [ self::class, 'hide_placeholder' ], 30, 1 );
		add_filter( 'term_description', [ self::class, 'hide_placeholder' ], 30, 1 );
	}

	/**
	 * Returns '' for placeholder text, else the description unchanged.
	 *
	 * @param mixed $description Description HTML.
	 * @return mixed
	 */
	public static function hide_placeholder( $description ) {
		if ( is_string( $description ) && '' !== $description && ! is_admin() && Text::is_placeholder( Text::plain( $description ) ) ) {
			return '';
		}
		return $description;
	}

	/**
	 * wp_head (priority 1).
	 */
	public static function print_head(): void {
		$d = self::data();
		if ( ! $d['enabled'] ) {
			return;
		}
		$out = [];
		if ( '' !== $d['description'] ) {
			$out[] = '<meta name="description" content="' . esc_attr( $d['description'] ) . '">';
		}
		if ( '' !== $d['canonical'] ) {
			$out[] = '<link rel="canonical" href="' . esc_url( $d['canonical'] ) . '">';
		}
		$og = [
			'og:locale'      => self::OG_LOCALES[ $d['lang'] ] ?? Languages::locale( $d['lang'] ),
			'og:type'        => $d['og_type'],
			'og:site_name'   => $d['site_name'],
			'og:title'       => $d['title'],
			'og:description' => $d['description'],
			'og:url'         => '' !== $d['canonical'] ? $d['canonical'] : '',
		];
		foreach ( $og as $property => $content ) {
			if ( '' !== $content ) {
				$out[] = '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">';
			}
		}
		foreach ( $d['alt_locales'] as $locale ) {
			$out[] = '<meta property="og:locale:alternate" content="' . esc_attr( $locale ) . '">';
		}
		if ( $d['image'] ) {
			$out[] = '<meta property="og:image" content="' . esc_url( $d['image']['url'] ) . '">';
			if ( $d['image']['width'] > 0 && $d['image']['height'] > 0 ) {
				$out[] = '<meta property="og:image:width" content="' . (int) $d['image']['width'] . '">';
				$out[] = '<meta property="og:image:height" content="' . (int) $d['image']['height'] . '">';
			}
			if ( '' !== $d['image']['alt'] ) {
				$out[] = '<meta property="og:image:alt" content="' . esc_attr( $d['image']['alt'] ) . '">';
			}
		}
		$out[] = '<meta name="twitter:card" content="summary_large_image">';
		$out[] = '<meta name="twitter:title" content="' . esc_attr( $d['title'] ) . '">';
		if ( '' !== $d['description'] ) {
			$out[] = '<meta name="twitter:description" content="' . esc_attr( $d['description'] ) . '">';
		}
		if ( $d['image'] ) {
			$out[] = '<meta name="twitter:image" content="' . esc_url( $d['image']['url'] ) . '">';
		}
		echo implode( "\n", $out ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped above.
	}

	/**
	 * get_canonical_url (singular pages, used by core and plugins): the language-aware URL.
	 *
	 * @param mixed $url Core's canonical.
	 * @return mixed
	 */
	public static function filter_canonical_url( $url ) {
		if ( ! is_string( $url ) || is_admin() || ! did_action( 'wp' ) ) {
			return $url;
		}
		$d = self::data();
		return '' !== $d['canonical'] ? $d['canonical'] : $url;
	}

	/**
	 * document_title_parts: the home title from the settings; "price and buy … original" on
	 * category and brand archives.
	 *
	 * @param mixed $parts Title parts.
	 * @return mixed
	 */
	public static function filter_title_parts( $parts ) {
		if ( ! is_array( $parts ) || is_admin() ) {
			return $parts;
		}
		$lang = Languages::current();
		if ( is_front_page() && ! is_paged() ) {
			$title = Settings::get_lang( 'seo.home_title', $lang, (string) ( SettingsTab::HOME_TITLE[ $lang ] ?? '' ) );
			if ( '' !== $title ) {
				// A complete title: no tagline and no second site name.
				return [ 'title' => $title ];
			}
			return $parts;
		}
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || ! ( is_tax() || is_category() || is_tag() ) || ! (bool) Settings::get( 'seo.archive_titles', true ) ) {
			return $parts;
		}
		$name = self::term_name( $term, $lang );
		if ( '' === $name ) {
			return $parts;
		}
		$key = '';
		if ( 'product_cat' === $term->taxonomy ) {
			$key = self::has_children( $term ) ? 'cat_parent_title' : 'cat_title';
		} elseif ( Brands::TAXONOMY === $term->taxonomy ) {
			$key = 'brand_title';
		}
		if ( '' !== $key ) {
			$parts['title'] = sprintf( self::template( $key, $lang ), $name );
		}
		return $parts;
	}

	/**
	 * Everything the head needs, computed once.
	 *
	 * @return array{enabled:bool,lang:string,noindex:bool,canonical:string,description:string,title:string,site_name:string,og_type:string,image:array{url:string,width:int,height:int,alt:string}|null,alt_locales:string[]}
	 */
	public static function data(): array {
		if ( null !== self::$data ) {
			return self::$data;
		}
		$lang = Languages::current();
		$d    = [
			'enabled'     => true,
			'lang'        => $lang,
			'noindex'     => false,
			'canonical'   => '',
			'description' => '',
			'title'       => '',
			'site_name'   => '',
			'og_type'     => 'website',
			'image'       => null,
			'alt_locales' => [],
		];
		if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_embed() || is_404() || is_search() || is_preview() ) {
			$d['enabled'] = false;
			return self::$data = $d;
		}
		$robots       = (array) apply_filters( 'wp_robots', [] );
		$d['noindex'] = ! empty( $robots['noindex'] ) || ! empty( $robots['none'] );

		if ( ! $d['noindex'] && Hreflang::applicable() ) {
			$url = Hreflang::current_object_url( $lang );
			if ( is_string( $url ) && '' !== $url ) {
				$d['canonical'] = Text::encode_url( $url );
			}
		}
		// Invalid UTF-8 would be printed by esc_attr() as content="": no tag at all is better.
		$d['description'] = wp_check_invalid_utf8( self::description( $lang ) );
		$d['site_name']   = self::site_name( $lang );
		$d['title']       = self::social_title();
		$d['og_type']     = self::og_type();
		$d['image']       = self::image( $lang );
		if ( '' !== $d['canonical'] ) {
			foreach ( array_keys( Hreflang::links() ) as $code ) {
				if ( 'x-default' === $code ) {
					continue;
				}
				$other = Languages::from_locale( (string) $code ) ?? self::lang_from_hreflang( (string) $code );
				if ( null !== $other && $other !== $lang && isset( self::OG_LOCALES[ $other ] ) ) {
					$d['alt_locales'][] = self::OG_LOCALES[ $other ];
				}
			}
		}
		return self::$data = $d;
	}

	/**
	 * Forgets the per-request cache (tests).
	 */
	public static function reset(): void {
		self::$data = null;
	}

	/**
	 * Meta description of the current page in $lang ('' when none fits).
	 */
	public static function description( string $lang ): string {
		$text = '';
		if ( is_front_page() ) {
			$text = Settings::get_lang( 'seo.home_description', $lang, (string) ( SettingsTab::HOME_DESCRIPTION[ $lang ] ?? '' ) );
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$text = self::post_description( (int) wc_get_page_id( 'shop' ), $lang, false );
			if ( '' === $text ) {
				$text = self::template( 'shop_desc', $lang );
			}
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$text = self::post_description( (int) $post->ID, $lang, true );
			}
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$text = self::term_description( $term, $lang );
			}
		}
		$text = Text::shorten( Text::plain( $text ) );
		if ( '' === $text || Text::is_placeholder( $text ) ) {
			return '';
		}
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		if ( $paged > 1 ) {
			$text = Text::shorten( $text, 140 ) . ' ' . sprintf( self::template( 'page_n', $lang ), Digits::for_lang( (string) $paged, $lang ) );
		}
		return $text;
	}

	/**
	 * Stored SEO description, else the excerpt, else (when $use_content) the content.
	 */
	private static function post_description( int $post_id, string $lang, bool $use_content ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$fields = $use_content ? [ 'seo_desc', 'excerpt', 'content' ] : [ 'seo_desc', 'excerpt' ];
		foreach ( $fields as $field ) {
			$value = self::post_field( $post_id, $field, $lang );
			$plain = Text::plain( $value );
			if ( '' !== $plain && ! Text::is_placeholder( $plain ) ) {
				return $plain;
			}
		}
		// A page that is only a shortcode (the order-tracking form) has no text of its own.
		if ( has_shortcode( (string) get_post_field( 'post_content', $post_id, 'raw' ), 'woocommerce_order_tracking' ) ) {
			return self::template( 'tracking_desc', $lang );
		}
		return '';
	}

	/**
	 * A post field in $lang: Persian reads the post itself, other languages the published
	 * translation only (never Persian text under /en/ or /ar/).
	 */
	private static function post_field( int $post_id, string $field, string $lang ): string {
		if ( Languages::DEFAULT !== $lang ) {
			return PostTranslations::get( $post_id, $field, $lang );
		}
		switch ( $field ) {
			case 'seo_desc':
				$value = get_post_meta( $post_id, '_gtm_seo_desc', true );
				return is_string( $value ) ? $value : '';
			case 'excerpt':
				return (string) get_post_field( 'post_excerpt', $post_id, 'raw' );
			case 'content':
				return (string) get_post_field( 'post_content', $post_id, 'raw' );
		}
		return '';
	}

	/**
	 * Own description of a term, else the template of its taxonomy.
	 */
	private static function term_description( WP_Term $term, string $lang ): string {
		$own = Languages::DEFAULT === $lang ? (string) $term->description : TermTranslations::description( $term, $lang );
		$own = Text::plain( $own );
		if ( '' !== $own && ! Text::is_placeholder( $own ) && mb_strlen( $own, 'UTF-8' ) >= 50 ) {
			return $own;
		}
		if ( ! (bool) Settings::get( 'seo.archive_titles', true ) ) {
			return $own;
		}
		$key = [
			'product_cat'    => self::has_children( $term ) ? 'cat_parent_desc' : 'cat_desc',
			Brands::TAXONOMY => 'brand_desc',
			'product_tag'    => 'tag_desc',
		][ $term->taxonomy ] ?? '';
		$name = self::term_name( $term, $lang );
		if ( '' === $key || '' === $name ) {
			return $own;
		}
		$count = (int) ( $GLOBALS['wp_query']->found_posts ?? 0 );
		if ( $count <= 0 ) {
			// An empty archive is noindex (Seo\Robots); a template would announce products it does not list.
			return $own;
		}
		return sprintf( self::template( $key, $lang ), $name, Digits::for_lang( (string) $count, $lang ) );
	}

	/**
	 * Names used in titles and descriptions instead of the stored term name, per taxonomy, decoded
	 * slug and language. Only where the shorter stored name hides the phrase shoppers type, and
	 * only when every product in the category fits the longer name:
	 * - «ایرپاد»: every product is a Bluetooth earbud; «هندزفری بلوتوثی» (2026-11-13).
	 * - «شارژر فندکی»: both products are super-fast car chargers; «شارژر فندکی ماشین» and
	 *   «شارژر فندکی فست شارژ» (2026-11-14).
	 * - «اسپیکر» and «هدفون»: all Bluetooth but one gaming model each; «اسپیکر بلوتوثی» and
	 *   «هدفون بلوتوثی» (2026-11-14).
	 * Filter: gadgetim_seo_term_names.
	 */
	private const TERM_NAMES = [
		'product_cat' => [
			'ایرپاد'                       => [ 'fa' => 'ایرپاد و هندزفری بلوتوثی' ],
			'شارژر-فندکی'                  => [ 'fa' => 'شارژر فندکی ماشین فست شارژ' ],
			'اسپیکر-کالای-دیجیتیال-و-برقی' => [ 'fa' => 'اسپیکر بلوتوثی و گیمینگ' ],
			'هدفون'                        => [ 'fa' => 'هدفون بلوتوثی و گیمینگ' ],
		],
	];

	/**
	 * Term name in $lang (published translation, else the stored name for Persian).
	 */
	private static function term_name( WP_Term $term, string $lang ): string {
		/**
		 * Filters the names used in archive titles and descriptions.
		 *
		 * @param array<string,array<string,array<string,string>>> $names [ taxonomy => [ slug => [ lang => name ] ] ].
		 */
		$names    = (array) apply_filters( 'gadgetim_seo_term_names', self::TERM_NAMES );
		$override = (string) ( $names[ $term->taxonomy ][ rawurldecode( (string) $term->slug ) ][ $lang ] ?? '' );
		if ( '' !== $override ) {
			return $override;
		}
		$name = Languages::DEFAULT === $lang ? (string) $term->name : TermTranslations::name_or_native( $term, $lang );
		$name = trim( html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( Languages::DEFAULT === $lang && Brands::TAXONOMY === $term->taxonomy ) {
			$local = self::brand_local_name( $term, $name );
			if ( '' !== $local ) {
				// Shoppers search «گرین لاین» far more than "Green Lion": both spellings in titles.
				$name = $local . ' ' . $name;
			}
		}
		return $name;
	}

	/**
	 * The Persian spelling of a Latin brand name, from the aliases the importer stored
	 * (_gtm_import_aliases), or '' when the brand has none or its name is already Persian.
	 */
	private static function brand_local_name( WP_Term $term, string $name ): string {
		if ( preg_match( '/\p{Arabic}/u', $name ) ) {
			return '';
		}
		$aliases = get_term_meta( (int) $term->term_id, Brands::META_ALIASES, true );
		foreach ( is_array( $aliases ) ? $aliases : [] as $alias ) {
			$alias = trim( (string) $alias );
			if ( '' !== $alias && preg_match( '/^[\p{Arabic}\s\x{200C}]+$/u', $alias ) ) {
				/**
				 * Filters the Persian spelling shown next to a brand name in titles.
				 *
				 * @param string  $alias Persian spelling.
				 * @param WP_Term $term  Brand term.
				 */
				return trim( (string) apply_filters( 'gadgetim_brand_local_name', $alias, $term ) );
			}
		}
		return '';
	}

	/**
	 * Whether a product category has child categories (a group such as «زیبایی و سلامت»).
	 */
	private static function has_children( WP_Term $term ): bool {
		$children = get_term_children( (int) $term->term_id, $term->taxonomy );
		return is_array( $children ) && [] !== $children;
	}

	/**
	 * Template text for a language (Persian when the language has none).
	 */
	private static function template( string $key, string $lang ): string {
		/**
		 * Filters the SEO title and description templates.
		 *
		 * @param array<string,array<string,string>> $templates Templates per key and language.
		 */
		$all = (array) apply_filters( 'gadgetim_seo_templates', self::TEMPLATES );
		return (string) ( $all[ $key ][ $lang ] ?? $all[ $key ][ Languages::DEFAULT ] ?? '' );
	}

	/**
	 * Store name in $lang.
	 */
	private static function site_name( string $lang ): string {
		$name = Settings::get_lang( 'brand.name', $lang, '' );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * Title for link previews: the document title without the store-name suffix.
	 */
	private static function social_title(): string {
		$title = html_entity_decode( wp_get_document_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$sep   = html_entity_decode( (string) apply_filters( 'document_title_separator', '-' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( ! is_front_page() ) {
			$suffix = ' ' . $sep . ' ' . self::site_name( Languages::current() );
			if ( str_ends_with( $title, $suffix ) ) {
				$title = substr( $title, 0, -strlen( $suffix ) );
			}
		}
		return trim( $title );
	}

	/**
	 * og:type: product, article or website.
	 */
	private static function og_type(): string {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return 'product';
		}
		if ( is_singular( 'post' ) ) {
			return 'article';
		}
		return 'website';
	}

	/**
	 * Share image: the product or page image, the category image or brand logo, else the
	 * default from the settings, else the store's share card, else the site icon.
	 *
	 * A featured image with words in another language (the Persian title box on the English
	 * page) gives way to the share card in the page's own language.
	 *
	 * @return array{url:string,width:int,height:int,alt:string}|null
	 */
	private static function image( string $lang ): ?array {
		$id = 0;
		if ( is_singular() ) {
			$id = (int) get_post_thumbnail_id( (int) get_queried_object_id() );
			if ( $id > 0 && ! self::image_speaks( $id, $lang ) ) {
				$id = 0;
			}
		} elseif ( is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$id = 'product_cat' === $term->taxonomy ? (int) get_term_meta( (int) $term->term_id, 'thumbnail_id', true ) : 0;
				if ( Brands::TAXONOMY === $term->taxonomy ) {
					$id = Brands::logo_id( $term );
				}
			}
		}
		if ( $id <= 0 ) {
			$id = (int) Settings::get( 'seo.share_image_id', 0 );
		}
		if ( $id > 0 ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$src = ShareJpeg::for_attachment( $id, $src );
				$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
				return [
					'url'    => Text::encode_url( (string) $src[0] ),
					'width'  => (int) ( $src[1] ?? 0 ),
					'height' => (int) ( $src[2] ?? 0 ),
					'alt'    => '' !== $alt ? $alt : self::social_title(),
				];
			}
		}
		$card = self::share_card( $lang );
		if ( null !== $card ) {
			return $card;
		}
		$icon = get_theme_file_uri( 'assets/img/icon-512.png' );
		if ( ! file_exists( get_theme_file_path( 'assets/img/icon-512.png' ) ) ) {
			return null;
		}
		return [
			'url'    => $icon,
			'width'  => 512,
			'height' => 512,
			'alt'    => self::site_name( $lang ),
		];
	}

	/**
	 * Whether an image can stand for a page in $lang: true for images without words, and for
	 * branded images whose drawn words (META_TEXT_LANG) are in $lang.
	 */
	public static function image_speaks( int $id, string $lang ): bool {
		$text = (string) get_post_meta( $id, self::META_TEXT_LANG, true );
		return '' === $text || $text === $lang;
	}

	/**
	 * The store's share card in $lang (else Persian): a 1200×630 theme file, assets/img/share-<lang>.jpg.
	 * Links to the home page and to pages without an image of their own showed the 512-pixel icon,
	 * which Telegram and WhatsApp draw as a small thumbnail instead of a large preview (2026-11-13).
	 *
	 * @return array{url:string,width:int,height:int,alt:string}|null
	 */
	private static function share_card( string $lang ): ?array {
		foreach ( array_unique( [ $lang, Languages::DEFAULT ] ) as $code ) {
			$file = 'assets/img/share-' . $code . '.jpg';
			if ( ! file_exists( get_theme_file_path( $file ) ) ) {
				continue;
			}
			$alt = Settings::get_lang( 'seo.home_title', $code, (string) ( SettingsTab::HOME_TITLE[ $code ] ?? '' ) );
			return [
				'url'    => get_theme_file_uri( $file ),
				'width'  => 1200,
				'height' => 630,
				'alt'    => '' !== $alt ? $alt : self::site_name( $code ),
			];
		}
		return null;
	}

	/**
	 * Language code from an hreflang value ('fa-IR' → 'fa').
	 */
	private static function lang_from_hreflang( string $hreflang ): ?string {
		foreach ( Languages::codes() as $code ) {
			if ( strtolower( Languages::hreflang( $code ) ) === strtolower( $hreflang ) ) {
				return $code;
			}
		}
		return null;
	}
}
