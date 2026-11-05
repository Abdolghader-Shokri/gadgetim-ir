<?php
/**
 * Home page content for the Aurora theme: the "Home page" settings tab and the data helpers the
 * front page reads (hero slides, promo banners, services strip, the amazing-offers campaign,
 * the product picks behind every product section, category tiles and shop statistics).
 *
 * Every getter works without any admin input: empty settings fall back to curated defaults that
 * reference categories by slug and products by their import id (_gtm_import_old_id, stable across
 * the Local and production imports), and when those are missing too, to automatic picks from the
 * catalogue (deepest discounts, newest, most viewed, priciest…). Heavy work runs once per
 * language and cache version: a catalogue snapshot is kept in a transient and the version is
 * bumped (once per request, at shutdown) on product, stock, term and settings changes.
 *
 * Settings paths (tab "home", manage_woocommerce):
 *   home.slides            hero slides (structured rows, max 6)
 *   home.banners           promo banners (structured rows, max 3)
 *   home.services          services strip items (structured rows, max 6)
 *   home.campaign.enabled  the amazing-offers countdown runs (needs a future end)
 *   home.campaign.title / home.campaign.sub (i18n), home.campaign.products (IDs)
 *   theme.promo_messages, theme.campaign_end   shared with the theme's Storefront tab
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use DateTimeImmutable;
use Exception;
use Gadgetim\Core\ContactMode\ContactMode;
use Gadgetim\Core\Pricing\Calculator;
use Gadgetim\Core\Pricing\Display;
use Gadgetim\Core\Settings\Admin\Fields;
use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Jalali;
use Gadgetim\Core\Support\Options;
use WC_Product;
use WP_Query;
use WP_Term;

/**
 * Data layer of the home page. Pure reads for templates plus the settings tab.
 */
final class HomeContent {

	public const TAB = 'home';

	public const VERSION_OPTION = 'gadgetim_home_version';

	public const MAX_SLIDES = 6;

	public const MAX_BANNERS = 3;

	public const MAX_SERVICES = 6;

	public const MAX_SNAPSHOT = 2000;

	public const SLIDE_TONES = [ 'indigo', 'violet', 'rose', 'amber', 'ocean', 'emerald' ];

	public const BANNER_TONES = [ 'violet', 'sunset', 'ocean', 'emerald', 'rose', 'midnight' ];

	public const SERVICE_TONES = [ 'violet', 'sky', 'emerald', 'green', 'pink', 'amber' ];

	public const CAMPAIGN_TITLE = [
		'fa' => 'پیشنهاد شگفت‌انگیز',
		'en' => 'Amazing offers',
		'ar' => 'عروض مذهلة',
	];

	public const CAMPAIGN_SUB = [
		'fa' => 'بیشترین تخفیف‌های امروز گجتیم',
		'en' => 'The biggest discounts at Gadgetim today',
		'ar' => 'أكبر خصومات اليوم في گجتیم',
	];

	public const ICONS = [ 'shield', 'truck', 'return', 'chat', 'lock', 'gift', 'bolt', 'phone', 'box', 'star', 'clock', 'tag', 'sparkle', 'headphones', 'gauge', 'crown', 'percent' ];

	/** @var array<string,mixed> Per-request memo (cleared on flush). */
	private static array $memo = [];

	private static bool $dirty = false;

	/**
	 * Hooks only (Catalog\Module calls this at plugin load): the tab on init, cache invalidation,
	 * the admin helpers of the structured repeaters.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_tab' ], 6 );
		$hooks = [
			'save_post_product',
			'woocommerce_update_product',
			'woocommerce_new_product',
			'deleted_post',
			'trashed_post',
			'untrashed_post',
			'woocommerce_product_set_stock_status',
			'woocommerce_variation_set_stock_status',
			'woocommerce_product_set_visibility',
			'created_product_cat',
			'edited_product_cat',
			'delete_product_cat',
			'created_product_brand',
			'edited_product_brand',
			'delete_product_brand',
			'gadgetim_settings_saved',
			'gadgetim_cache_purged_all',
		];
		foreach ( $hooks as $hook ) {
			add_action( $hook, [ self::class, 'flush' ], 10, 0 );
		}
		add_action( 'admin_enqueue_scripts', [ self::class, 'admin_assets' ], 20 );
	}

	/* ---------------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------------- */

	/**
	 * init (priority 6): the "Home page" tab.
	 */
	public static function register_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		Schema::add_tab(
			self::TAB,
			__( 'Home page', 'gadgetim-core' ),
			Capabilities::SHOP,
			[
				'hero'     => [
					'label'       => __( 'Hero slider', 'gadgetim-core' ),
					'description' => __( 'Up to six slides beside the instant offer. Each slide links to its first category (or its own link); the "up to x% off" sticker and the brand names are computed from the categories. Leave the list empty to use the automatic slides built from the catalogue.', 'gadgetim-core' ),
					'fields'      => [
						'home.slides' => [
							'type'     => 'repeater',
							'label'    => __( 'Slides', 'gadgetim-core' ),
							'default'  => [],
							'render'   => [ self::class, 'render_slides' ],
							'sanitize' => [ self::class, 'sanitize_slides' ],
						],
					],
				],
				'banners'  => [
					'label'       => __( 'Promo banners', 'gadgetim-core' ),
					'description' => __( 'Two or three gradient banners below the newest products. The first one is the large banner on desktop.', 'gadgetim-core' ),
					'fields'      => [
						'home.banners' => [
							'type'     => 'repeater',
							'label'    => __( 'Banners', 'gadgetim-core' ),
							'default'  => [],
							'render'   => [ self::class, 'render_banners' ],
							'sanitize' => [ self::class, 'sanitize_banners' ],
						],
					],
				],
				'strips'   => [
					'label'  => __( 'Strips', 'gadgetim-core' ),
					'fields' => [
						'theme.promo_messages' => [
							'type'        => 'repeater',
							'i18n'        => true,
							'label'       => __( 'Promo strip messages', 'gadgetim-core' ),
							'description' => __( 'Short lines rotating in the coloured strip above the header (the same list as in the Storefront tab). Empty: the trust badges are used.', 'gadgetim-core' ),
							'default'     => [],
						],
						'home.services'        => [
							'type'     => 'repeater',
							'label'    => __( 'Services strip', 'gadgetim-core' ),
							'default'  => [],
							'render'   => [ self::class, 'render_services' ],
							'sanitize' => [ self::class, 'sanitize_services' ],
						],
					],
				],
				'campaign' => [
					'label'       => __( 'Amazing offers', 'gadgetim-core' ),
					'description' => __( 'The band of the deepest discounts. A countdown is shown only while the campaign runs and its end lies in the future; otherwise the band simply lists the biggest discounts.', 'gadgetim-core' ),
					'fields'      => [
						'home.campaign.enabled'  => [
							'type'    => 'toggle',
							'label'   => __( 'The campaign is running', 'gadgetim-core' ),
							'default' => true,
						],
						'theme.campaign_end'     => [
							'type'        => 'text',
							'label'       => __( 'Campaign end', 'gadgetim-core' ),
							'description' => __( 'Date and time in site time, Gregorian (2026-11-30 23:59) or Jalali (1405/09/09 23:59). Empty: no countdown. The same value as in the Storefront tab.', 'gadgetim-core' ),
							'placeholder' => '2026-11-30 23:59',
							'default'     => '',
							'attrs'       => [ 'dir' => 'ltr' ],
						],
						'home.campaign.title'    => [
							'type'    => 'text',
							'i18n'    => true,
							'label'   => __( 'Band title', 'gadgetim-core' ),
							'default' => self::CAMPAIGN_TITLE,
						],
						'home.campaign.sub'      => [
							'type'    => 'text',
							'i18n'    => true,
							'label'   => __( 'Band sub-line', 'gadgetim-core' ),
							'default' => self::CAMPAIGN_SUB,
						],
						'home.campaign.products' => [
							'type'        => 'text',
							'label'       => __( 'Campaign products', 'gadgetim-core' ),
							'description' => __( 'Optional product IDs, comma separated, in display order. Empty: the deepest discounts in stock.', 'gadgetim-core' ),
							'placeholder' => '833, 846, 729',
							'sanitize'    => [ self::class, 'sanitize_id_list' ],
							'attrs'       => [ 'dir' => 'ltr' ],
						],
					],
				],
			],
			[ 'order' => 38 ]
		);
	}

	/**
	 * Sub-field specs of the structured repeaters.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function spec( string $kind ): array {
		$tones = static function ( array $keys ): array {
			$labels = [
				'indigo'   => __( 'Indigo dawn', 'gadgetim-core' ),
				'violet'   => __( 'Violet sky', 'gadgetim-core' ),
				'rose'     => __( 'Rose sunrise', 'gadgetim-core' ),
				'amber'    => __( 'Amber glow', 'gadgetim-core' ),
				'ocean'    => __( 'Ocean blue', 'gadgetim-core' ),
				'emerald'  => __( 'Emerald', 'gadgetim-core' ),
				'sunset'   => __( 'Sunset', 'gadgetim-core' ),
				'midnight' => __( 'Midnight', 'gadgetim-core' ),
				'sky'      => __( 'Sky', 'gadgetim-core' ),
				'green'    => __( 'Green', 'gadgetim-core' ),
				'pink'     => __( 'Pink', 'gadgetim-core' ),
			];
			$out = [];
			foreach ( $keys as $key ) {
				$out[ $key ] = $labels[ $key ] ?? ucfirst( $key );
			}
			return $out;
		};
		$icons = array_combine( self::ICONS, self::ICONS );
		$art   = [
			'type'        => 'art',
			'label'       => __( 'Artwork products', 'gadgetim-core' ),
			'description' => __( 'Product IDs, optionally with the image number (0 = main image, 1 = first gallery image): 641:1, 773:1. Add :f for a product standing on the bottom edge. Empty: picked automatically.', 'gadgetim-core' ),
			'wide'        => true,
		];

		if ( 'slides' === $kind ) {
			return [
				'tone'      => [
					'type'    => 'select',
					'label'   => __( 'Colour', 'gadgetim-core' ),
					'options' => $tones( self::SLIDE_TONES ),
				],
				'word'      => [
					'type'      => 'text',
					'label'     => __( 'Background word (Latin)', 'gadgetim-core' ),
					'dir'       => 'ltr',
					'maxlength' => 12,
				],
				'chip_icon' => [
					'type'    => 'select',
					'label'   => __( 'Feature chip icon', 'gadgetim-core' ),
					'options' => $icons,
				],
				'eyebrow'   => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Eyebrow', 'gadgetim-core' ),
				],
				'title'     => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Title, first line', 'gadgetim-core' ),
				],
				'highlight' => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Title, gradient line', 'gadgetim-core' ),
				],
				'sub'       => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Sub-line', 'gadgetim-core' ),
				],
				'sub_short' => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Sub-line on phones (optional)', 'gadgetim-core' ),
				],
				'cta'       => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Button label', 'gadgetim-core' ),
				],
				'cta_short' => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Button label on phones (optional)', 'gadgetim-core' ),
				],
				'chip'      => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Feature chip (optional)', 'gadgetim-core' ),
				],
				'terms'     => [
					'type'     => 'terms',
					'taxonomy' => 'product_cat',
					'multiple' => true,
					'label'    => __( 'Categories', 'gadgetim-core' ),
				],
				'url'       => [
					'type'  => 'url',
					'label' => __( 'Link (optional)', 'gadgetim-core' ),
				],
				'art'       => $art,
			];
		}
		if ( 'banners' === $kind ) {
			return [
				'tone'      => [
					'type'    => 'select',
					'label'   => __( 'Colour', 'gadgetim-core' ),
					'options' => $tones( self::BANNER_TONES ),
				],
				'label'     => [
					'type'      => 'text',
					'label'     => __( 'Small label (Latin brand name)', 'gadgetim-core' ),
					'dir'       => 'ltr',
					'maxlength' => 24,
				],
				'title'     => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Title, first line', 'gadgetim-core' ),
				],
				'highlight' => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Title, second line', 'gadgetim-core' ),
				],
				'sub'       => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Sub-line', 'gadgetim-core' ),
				],
				'cta'       => [
					'type'  => 'text',
					'i18n'  => true,
					'label' => __( 'Button label (optional)', 'gadgetim-core' ),
				],
				'terms'     => [
					'type'     => 'terms',
					'taxonomy' => 'product_cat',
					'multiple' => true,
					'label'    => __( 'Categories', 'gadgetim-core' ),
				],
				'brand'     => [
					'type'     => 'terms',
					'taxonomy' => 'product_brand',
					'multiple' => false,
					'label'    => __( 'Brand', 'gadgetim-core' ),
				],
				'url'       => [
					'type'  => 'url',
					'label' => __( 'Link (optional)', 'gadgetim-core' ),
				],
				'art'       => $art,
			];
		}
		return [
			'icon'  => [
				'type'    => 'select',
				'label'   => __( 'Icon', 'gadgetim-core' ),
				'options' => $icons,
			],
			'tone'  => [
				'type'    => 'select',
				'label'   => __( 'Colour', 'gadgetim-core' ),
				'options' => $tones( self::SERVICE_TONES ),
			],
			'title' => [
				'type'  => 'text',
				'i18n'  => true,
				'label' => __( 'Title', 'gadgetim-core' ),
			],
			'text'  => [
				'type'  => 'text',
				'i18n'  => true,
				'label' => __( 'Second line', 'gadgetim-core' ),
			],
			'url'   => [
				'type'  => 'url',
				'label' => __( 'Link (optional)', 'gadgetim-core' ),
			],
		];
	}

	/**
	 * Field renderer: hero slides.
	 */
	public static function render_slides( string $path, array $field, mixed $value ): void {
		self::render_rows( $path, 'slides', $value, self::MAX_SLIDES, __( 'Add slide', 'gadgetim-core' ) );
	}

	/**
	 * Field renderer: promo banners.
	 */
	public static function render_banners( string $path, array $field, mixed $value ): void {
		self::render_rows( $path, 'banners', $value, self::MAX_BANNERS, __( 'Add banner', 'gadgetim-core' ) );
	}

	/**
	 * Field renderer: services strip.
	 */
	public static function render_services( string $path, array $field, mixed $value ): void {
		self::render_rows( $path, 'services', $value, self::MAX_SERVICES, __( 'Add item', 'gadgetim-core' ) );
	}

	/**
	 * Structured repeater: one card per row (shared fields + one input per language for texts).
	 * The empty row lives in a <template> with the index placeholder __i__, replaced on add by
	 * the inline script of admin_assets(); admin.js handles add / move / remove.
	 *
	 * @param mixed $value Stored rows.
	 */
	private static function render_rows( string $path, string $kind, mixed $value, int $max, string $add_label ): void {
		$rows = is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : [];
		$auto = ! $rows;
		if ( $auto ) {
			$rows = match ( $kind ) {
				'slides'  => self::default_slide_rows(),
				'banners' => self::default_banner_rows(),
				default   => self::default_service_rows(),
			};
		}
		$spec = self::spec( $kind );
		$name = Fields::name( $path );

		echo '<div class="gtm-repeater gtm-home-rep" data-gtm-repeater data-gtm-home-rep data-max="' . esc_attr( (string) $max ) . '" id="' . esc_attr( Fields::id( $path ) ) . '">';
		if ( $auto && $rows ) {
			echo '<p class="gtm-home-rep__note">' . esc_html__( 'Nothing is saved yet: these rows show the automatic content the home page uses now. Edit and save to make them your own, or save as they are.', 'gadgetim-core' ) . '</p>';
		}
		echo '<ol class="gtm-repeater__list">';
		foreach ( array_slice( $rows, 0, $max ) as $i => $row ) {
			self::render_row( $name . '[' . $i . ']', $spec, $row, $i + 1 );
		}
		echo '</ol>';
		echo '<template class="gtm-repeater__template">';
		self::render_row( $name . '[__i__]', $spec, [], 0 );
		echo '</template>';
		echo '<button type="button" class="button gtm-repeater__add" data-gtm-action="add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html( $add_label ) . '</button>';
		echo '</div>';
	}

	/**
	 * One row card.
	 *
	 * @param array<string,array> $spec Sub-field specs.
	 * @param array<string,mixed> $row  Row values.
	 */
	private static function render_row( string $base, array $spec, array $row, int $number ): void {
		$uid = 'gtm_' . substr( md5( $base ), 0, 10 );
		echo '<li class="gtm-repeater__row gtm-home-row">';
		echo '<div class="gtm-home-row__head"><span class="gtm-home-row__n">' . esc_html( $number > 0 ? '#' . $number : '#' ) . '</span><span class="gtm-repeater__buttons">';
		echo '<button type="button" class="button-link gtm-repeater__btn" data-gtm-action="up" aria-label="' . esc_attr__( 'Move up', 'gadgetim-core' ) . '"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
		echo '<button type="button" class="button-link gtm-repeater__btn" data-gtm-action="down" aria-label="' . esc_attr__( 'Move down', 'gadgetim-core' ) . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '<button type="button" class="button-link button-link-delete gtm-repeater__btn" data-gtm-action="remove" aria-label="' . esc_attr__( 'Remove', 'gadgetim-core' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
		echo '</span></div><div class="gtm-home-row__grid">';
		foreach ( $spec as $key => $def ) {
			$input = $base . '[' . $key . ']';
			$id    = $uid . '_' . $key;
			$cell  = $row[ $key ] ?? null;
			$wide  = ! empty( $def['wide'] ) ? ' gtm-home-wide' : '';
			$type  = (string) ( $def['type'] ?? 'text' );

			if ( ! empty( $def['i18n'] ) ) {
				echo '<fieldset class="gtm-home-cell gtm-home-i18n' . esc_attr( $wide ) . '"><legend>' . esc_html( (string) $def['label'] ) . '</legend>';
				foreach ( Options::languages() as $code => $language ) {
					$lang_value = is_array( $cell ) ? (string) ( $cell[ $code ] ?? '' ) : '';
					echo '<label><span class="gtm-i18n__code">' . esc_html( (string) $code ) . '</span><input type="text" class="gtm-input" name="' . esc_attr( $input . '[' . $code . ']' ) . '" value="' . esc_attr( $lang_value ) . '" dir="' . esc_attr( (string) ( $language['dir'] ?? 'rtl' ) ) . '" lang="' . esc_attr( (string) $code ) . '"></label>';
				}
				echo '</fieldset>';
				continue;
			}

			echo '<div class="gtm-home-cell' . esc_attr( $wide ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( (string) $def['label'] ) . '</label>';
			if ( 'select' === $type ) {
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '">';
				foreach ( (array) $def['options'] as $opt => $label ) {
					echo '<option value="' . esc_attr( (string) $opt ) . '"' . selected( (string) $cell, (string) $opt, false ) . '>' . esc_html( (string) $label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'terms' === $type ) {
				$multiple = ! empty( $def['multiple'] );
				$chosen   = array_map( 'intval', is_array( $cell ) ? $cell : ( $cell ? [ $cell ] : [] ) );
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $input . ( $multiple ? '[]' : '' ) ) . '"' . ( $multiple ? ' multiple size="6"' : '' ) . '>';
				if ( ! $multiple ) {
					echo '<option value="0">' . esc_html__( '— None —', 'gadgetim-core' ) . '</option>';
				}
				foreach ( self::term_options( (string) $def['taxonomy'] ) as $term_id => $label ) {
					echo '<option value="' . esc_attr( (string) $term_id ) . '"' . ( in_array( (int) $term_id, $chosen, true ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'art' === $type ) {
				echo '<input type="text" class="regular-text gtm-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '" value="' . esc_attr( self::art_to_string( is_array( $cell ) ? $cell : [] ) ) . '" dir="ltr" placeholder="641:1, 773:1">';
				echo '<span class="description">' . esc_html( (string) $def['description'] ) . '</span>';
			} elseif ( 'url' === $type ) {
				echo '<input type="url" class="regular-text gtm-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '" value="' . esc_attr( (string) $cell ) . '" dir="ltr" placeholder="https://">';
			} else {
				$max = isset( $def['maxlength'] ) ? ' maxlength="' . esc_attr( (string) $def['maxlength'] ) . '"' : '';
				$dir = isset( $def['dir'] ) ? ' dir="' . esc_attr( (string) $def['dir'] ) . '"' : '';
				echo '<input type="text" class="regular-text gtm-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '" value="' . esc_attr( is_scalar( $cell ) ? (string) $cell : '' ) . '"' . $max . $dir . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above.
			}
			echo '</div>';
		}
		echo '</div></li>';
	}

	/**
	 * Select options of a taxonomy: [ term_id => indented label (count) ].
	 *
	 * @return array<int,string>
	 */
	private static function term_options( string $taxonomy ): array {
		$key = 'opts_' . $taxonomy;
		if ( isset( self::$memo[ $key ] ) ) {
			return self::$memo[ $key ];
		}
		$out = [];
		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'orderby'    => 'name',
				]
			);
			$terms = is_array( $terms ) ? $terms : [];
			$kids  = [];
			foreach ( $terms as $t ) {
				if ( $t instanceof WP_Term ) {
					$kids[ (int) $t->parent ][] = $t;
				}
			}
			$walk = static function ( int $parent, int $depth ) use ( &$walk, &$out, $kids ): void {
				foreach ( $kids[ $parent ] ?? [] as $t ) {
					$out[ (int) $t->term_id ] = str_repeat( '— ', $depth ) . html_entity_decode( (string) $t->name, ENT_QUOTES, 'UTF-8' ) . ' (' . (int) $t->count . ')';
					$walk( (int) $t->term_id, $depth + 1 );
				}
			};
			$walk( 0, 0 );
		}
		self::$memo[ $key ] = $out;
		return $out;
	}

	/**
	 * admin_enqueue_scripts: index placeholders and layout of the structured repeaters (Home tab only).
	 */
	public static function admin_assets( string $hook ): void {
		if ( ! class_exists( SettingsPage::class ) || ! SettingsPage::is_our_screen( $hook ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		if ( self::TAB !== $tab ) {
			return;
		}
		$js = <<<'JS'
(function () {
	'use strict';
	var seq = Date.now() % 100000;
	function fix(row) {
		if (!row || row.nodeType !== 1) { return; }
		var stamp = String(seq++);
		row.querySelectorAll('[name],[id],[for]').forEach(function (el) {
			['name', 'id', 'for'].forEach(function (attr) {
				var v = el.getAttribute(attr);
				if (v && v.indexOf('__i__') !== -1) { el.setAttribute(attr, v.split('__i__').join(stamp)); }
			});
		});
	}
	document.querySelectorAll('[data-gtm-home-rep]').forEach(function (rep) {
		var list = rep.querySelector('.gtm-repeater__list');
		var add = rep.querySelector('.gtm-repeater__add');
		var max = parseInt(rep.getAttribute('data-max'), 10) || 6;
		function sync() {
			Array.prototype.forEach.call(list.children, function (li, i) {
				var n = li.querySelector('.gtm-home-row__n');
				if (n) { n.textContent = '#' + (i + 1); }
			});
			if (add) { add.disabled = list.children.length >= max; }
		}
		new MutationObserver(function (muts) {
			muts.forEach(function (m) { m.addedNodes.forEach(fix); });
			sync();
		}).observe(list, { childList: true });
		sync();
	});
})();
JS;
		wp_add_inline_script( 'gadgetim-admin', $js );
		$css = '.gtm-home-rep__note{margin:0 0 10px;padding:8px 12px;background:#f0f6fc;border-inline-start:3px solid #72aee6}'
			. '.gtm-repeater__row.gtm-home-row{display:block;padding:12px 14px;margin:0 0 12px;background:#fff;border:1px solid #dcdcde;border-radius:8px}'
			. '.gtm-home-row__head{display:flex;align-items:center;justify-content:space-between;margin-block-end:8px;font-weight:600}'
			. '.gtm-home-row__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px 18px;align-items:start}'
			. '.gtm-home-cell{display:grid;gap:4px;margin:0;min-inline-size:0}.gtm-home-cell .regular-text,.gtm-home-cell select{inline-size:100%;max-inline-size:100%}'
			. '.gtm-home-i18n{border:0;padding:0}.gtm-home-i18n legend{padding:0;margin-block-end:4px;font-weight:600}'
			. '.gtm-home-i18n label{display:grid;grid-template-columns:28px minmax(0,1fr);align-items:center;gap:6px;margin-block-end:3px}'
			. '.gtm-home-i18n .gtm-input{inline-size:100%}.gtm-home-wide{grid-column:1/-1}';
		wp_add_inline_style( 'gadgetim-admin', $css );
	}

	/**
	 * Sanitizer: hero slides.
	 */
	public static function sanitize_slides( mixed $value ): array {
		return self::sanitize_rows( $value, 'slides', self::MAX_SLIDES );
	}

	/**
	 * Sanitizer: promo banners.
	 */
	public static function sanitize_banners( mixed $value ): array {
		return self::sanitize_rows( $value, 'banners', self::MAX_BANNERS );
	}

	/**
	 * Sanitizer: services strip.
	 */
	public static function sanitize_services( mixed $value ): array {
		return self::sanitize_rows( $value, 'services', self::MAX_SERVICES );
	}

	/**
	 * Sanitizer: comma-separated product IDs ("833, 846") → normalised string.
	 */
	public static function sanitize_id_list( mixed $value ): string {
		return implode( ', ', self::parse_ids( is_scalar( $value ) ? (string) $value : '' ) );
	}

	/**
	 * Rows through their sub-field specs; empty rows are dropped.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_rows( mixed $value, string $kind, int $max ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$spec = self::spec( $kind );
		$rows = [];
		foreach ( $value as $key => $row ) {
			if ( '__i__' === (string) $key || ! is_array( $row ) ) {
				continue;
			}
			$clean = [];
			$texts = false;
			foreach ( $spec as $name => $def ) {
				$clean[ $name ] = self::sanitize_cell( $def, $row[ $name ] ?? null );
				if ( ! empty( $def['i18n'] ) && in_array( $name, [ 'title', 'highlight' ], true ) && array_filter( (array) $clean[ $name ] ) ) {
					$texts = true;
				}
			}
			if ( ! $texts ) {
				continue;
			}
			$rows[] = $clean;
			if ( count( $rows ) >= $max ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * One sub-field value.
	 *
	 * @param array<string,mixed> $def Sub-field spec.
	 */
	private static function sanitize_cell( array $def, mixed $value ): mixed {
		$type = (string) ( $def['type'] ?? 'text' );
		if ( ! empty( $def['i18n'] ) ) {
			$out = [];
			foreach ( Schema::languages() as $code ) {
				$raw          = is_array( $value ) ? ( $value[ $code ] ?? '' ) : '';
				$out[ $code ] = is_scalar( $raw ) ? mb_substr( sanitize_text_field( (string) $raw ), 0, 160 ) : '';
			}
			return $out;
		}
		switch ( $type ) {
			case 'select':
				$options = (array) ( $def['options'] ?? [] );
				$key     = is_scalar( $value ) ? (string) $value : '';
				return array_key_exists( $key, $options ) ? $key : (string) array_key_first( $options );
			case 'terms':
				$ids = array_values(
					array_unique(
						array_filter(
							array_map( 'absint', is_array( $value ) ? $value : [ $value ] ),
							static fn( int $id ): bool => $id > 0 && get_term( $id, (string) $def['taxonomy'] ) instanceof WP_Term
						)
					)
				);
				return ! empty( $def['multiple'] ) ? array_slice( $ids, 0, 12 ) : (int) ( $ids[0] ?? 0 );
			case 'url':
				return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ) ) : '';
			case 'art':
				return self::parse_art( is_scalar( $value ) ? (string) $value : '' );
			default:
				$text = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
				return isset( $def['maxlength'] ) ? mb_substr( $text, 0, (int) $def['maxlength'] ) : $text;
		}
	}

	/**
	 * "641:1, 773:1:f" → [ [ 641, 1, false ], [ 773, 1, true ] ] (products only, at most 2).
	 *
	 * @return array<int,array{0:int,1:int,2:bool}>
	 */
	private static function parse_art( string $raw ): array {
		$raw = Digits::to_latin( $raw );
		$out = [];
		foreach ( preg_split( '/[\s,،]+/u', $raw ) ?: [] as $token ) {
			if ( ! preg_match( '/^(\d+)(?::(\d{1,2}))?(?::(f|fade))?$/i', trim( $token ), $m ) ) {
				continue;
			}
			$id = (int) $m[1];
			if ( $id <= 0 || 'product' !== get_post_type( $id ) ) {
				continue;
			}
			$out[] = [ $id, isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0, ! empty( $m[3] ) ];
			if ( count( $out ) >= 2 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Art list → "641:1, 773:1:f".
	 *
	 * @param array<int,array> $art Art entries.
	 */
	private static function art_to_string( array $art ): string {
		$parts = [];
		foreach ( $art as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry[0] ) ) {
				continue;
			}
			$parts[] = (int) $entry[0] . ':' . (int) ( $entry[1] ?? 0 ) . ( ! empty( $entry[2] ) ? ':f' : '' );
		}
		return implode( ', ', $parts );
	}

	/**
	 * "833, 846" (any digits) → [ 833, 846 ] (existing products only).
	 *
	 * @return int[]
	 */
	private static function parse_ids( string $raw ): array {
		$ids = [];
		foreach ( preg_split( '/[^0-9]+/', Digits::to_latin( $raw ) ) ?: [] as $part ) {
			$id = (int) $part;
			if ( $id > 0 && 'product' === get_post_type( $id ) && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return array_slice( $ids, 0, 24 );
	}

	/* ---------------------------------------------------------------------
	 * Cache
	 * ------------------------------------------------------------------- */

	/**
	 * Invalidates the home caches: the in-request memo now, the transients (version bump) once at shutdown.
	 */
	public static function flush(): void {
		self::$memo = [];
		if ( ! self::$dirty ) {
			self::$dirty = true;
			add_action( 'shutdown', [ self::class, 'bump_version' ] );
		}
	}

	/**
	 * shutdown: one version bump per request however many products were saved.
	 */
	public static function bump_version(): void {
		update_option( self::VERSION_OPTION, (int) get_option( self::VERSION_OPTION, 1 ) + 1, true );
	}

	/**
	 * Cached value per language and version (transient for 12 hours, plus the request memo).
	 */
	private static function cached( string $key, callable $build, ?string $lang = null ): mixed {
		$lang = $lang ?? self::lang();
		$id   = 'gadgetim_home_' . $key . '_' . $lang . '_' . (int) get_option( self::VERSION_OPTION, 1 );
		if ( array_key_exists( $id, self::$memo ) ) {
			return self::$memo[ $id ];
		}
		$data = self::$dirty ? false : get_transient( $id );
		if ( false === $data ) {
			$data = $build();
			if ( ! self::$dirty ) {
				set_transient( $id, $data, 12 * HOUR_IN_SECONDS );
			}
		}
		self::$memo[ $id ] = $data;
		return $data;
	}

	/**
	 * Current language code.
	 */
	private static function lang(): string {
		return function_exists( 'gadgetim_lang' ) ? (string) gadgetim_lang() : 'fa';
	}

	/* ---------------------------------------------------------------------
	 * Catalogue snapshot
	 * ------------------------------------------------------------------- */

	/**
	 * Compact record of every product visible in the current language (newest first):
	 * id, t (date), in (in stock), price, off (discount %), sales, views, feat, cats, brand,
	 * gal (gallery images), img (main image id), cm (contact mode).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function snapshot(): array {
		return (array) self::cached( 'catalog', [ self::class, 'build_snapshot' ] );
	}

	/**
	 * Builds the snapshot (one gated query, primed caches).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function build_snapshot(): array {
		if ( ! function_exists( 'wc_get_product' ) || ! post_type_exists( 'product' ) ) {
			return [];
		}
		$args = [
			'post_type'             => 'product',
			'post_status'           => 'publish',
			'fields'                => 'ids',
			'posts_per_page'        => self::MAX_SNAPSHOT,
			'no_found_rows'         => true,
			'ignore_sticky_posts'   => true,
			'orderby'               => [
				'date' => 'DESC',
				'ID'   => 'DESC',
			],
			'gadgetim_product_loop' => 'home',
		];
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
		$ids = array_map( 'intval', (array) ( new WP_Query( $args ) )->posts );
		if ( ! $ids ) {
			return [];
		}
		_prime_post_caches( $ids, true, true );
		$hide_out = 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' );
		$contact  = class_exists( ContactMode::class );
		$out      = [];
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
				continue;
			}
			$in = $product->is_in_stock();
			if ( $hide_out && ! $in ) {
				continue;
			}
			$created = $product->get_date_created();
			$cats    = get_the_terms( $id, 'product_cat' );
			$brand   = Brands::primary( $product );
			$out[]   = [
				'id'    => $id,
				't'     => $created ? $created->getTimestamp() : 0,
				'in'    => $in,
				'price' => (float) $product->get_price(),
				'off'   => self::discount( $product ),
				'sales' => (int) $product->get_total_sales(),
				'views' => (int) get_post_meta( $id, ProductExtras::META_VIEWS, true ),
				'feat'  => $product->is_featured(),
				'cats'  => is_array( $cats ) ? array_values( array_map( static fn( WP_Term $t ): int => (int) $t->term_id, array_filter( $cats, static fn( $t ): bool => $t instanceof WP_Term ) ) ) : [],
				'brand' => $brand instanceof WP_Term ? (int) $brand->term_id : 0,
				'gal'   => count( $product->get_gallery_image_ids() ),
				'img'   => (int) $product->get_image_id(),
				'cm'    => $contact ? ContactMode::applies( $product ) : false,
			];
		}
		return $out;
	}

	/**
	 * Discount % of a product from its final Toman pair (0 when not on sale).
	 */
	private static function discount( WC_Product $product ): int {
		if ( ! $product->is_on_sale() ) {
			return 0;
		}
		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_regular_price' ) ) {
			$was = (float) $product->get_variation_regular_price( 'min' );
			$now = (float) $product->get_variation_price( 'min' );
		} else {
			$was = (float) $product->get_regular_price();
			$now = (float) $product->get_price();
		}
		return class_exists( Calculator::class ) ? Calculator::percent_off( $was, $now ) : ( $was > 0 && $now < $was ? (int) round( ( $was - $now ) / $was * 100 ) : 0 );
	}

	/**
	 * Snapshot records keyed by product id.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function by_id(): array {
		$key = 'by_id_' . self::lang();
		if ( ! isset( self::$memo[ $key ] ) ) {
			$map = [];
			foreach ( self::snapshot() as $rec ) {
				$map[ (int) $rec['id'] ] = $rec;
			}
			self::$memo[ $key ] = $map;
		}
		return self::$memo[ $key ];
	}

	/**
	 * Sale thresholds (strike, sticker).
	 *
	 * @return array{strike:int,sticker:int}
	 */
	public static function thresholds(): array {
		if ( class_exists( Display::class ) ) {
			$t = Display::thresholds();
			return [
				'strike'  => (int) $t['strike'],
				'sticker' => (int) $t['sticker'],
			];
		}
		return [
			'strike'  => 10,
			'sticker' => 15,
		];
	}

	/**
	 * Category ids of the given categories and all their descendants.
	 *
	 * @param int[] $term_ids Category ids.
	 * @return array<int,true>
	 */
	public static function subtree( array $term_ids ): array {
		if ( ! isset( self::$memo['cat_kids'] ) ) {
			$kids  = [];
			$terms = taxonomy_exists( 'product_cat' ) ? get_terms(
				[
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'fields'     => 'id=>parent',
				]
			) : [];
			foreach ( is_array( $terms ) ? $terms : [] as $id => $parent ) {
				$kids[ (int) $parent ][] = (int) $id;
			}
			self::$memo['cat_kids'] = $kids;
		}
		$kids  = self::$memo['cat_kids'];
		$set   = [];
		$queue = array_map( 'intval', $term_ids );
		while ( $queue ) {
			$id = (int) array_shift( $queue );
			if ( $id <= 0 || isset( $set[ $id ] ) ) {
				continue;
			}
			$set[ $id ] = true;
			foreach ( $kids[ $id ] ?? [] as $child ) {
				$queue[] = $child;
			}
		}
		return $set;
	}

	/**
	 * Snapshot records filtered by categories (descendants included), brand and stock.
	 *
	 * @param int[] $term_ids Categories ([] = all).
	 * @return array<int,array<string,mixed>>
	 */
	public static function in_scope( array $term_ids = [], int $brand = 0, bool $in_stock = true ): array {
		$set = $term_ids ? self::subtree( $term_ids ) : [];
		return array_values(
			array_filter(
				self::snapshot(),
				static function ( array $rec ) use ( $set, $term_ids, $brand, $in_stock ): bool {
					if ( $in_stock && empty( $rec['in'] ) ) {
						return false;
					}
					if ( $brand > 0 && (int) $rec['brand'] !== $brand ) {
						return false;
					}
					if ( $term_ids ) {
						foreach ( $rec['cats'] as $cat ) {
							if ( isset( $set[ (int) $cat ] ) ) {
								return true;
							}
						}
						return false;
					}
					return true;
				}
			)
		);
	}

	/**
	 * Deepest discount (%) among buyable in-stock products of a scope (0 when none).
	 *
	 * @param int[] $term_ids Categories ([] = all).
	 */
	public static function max_discount( array $term_ids = [], int $brand = 0 ): int {
		$max = 0;
		foreach ( self::in_scope( $term_ids, $brand ) as $rec ) {
			if ( empty( $rec['cm'] ) ) {
				$max = max( $max, (int) $rec['off'] );
			}
		}
		return $max;
	}

	/**
	 * Brand term ids of a scope, most products first.
	 *
	 * @param int[] $term_ids Categories.
	 * @return int[]
	 */
	public static function scope_brands( array $term_ids, int $limit = 3 ): array {
		$count = [];
		foreach ( self::in_scope( $term_ids ) as $rec ) {
			if ( $rec['brand'] > 0 ) {
				$count[ (int) $rec['brand'] ] = ( $count[ (int) $rec['brand'] ] ?? 0 ) + 1 + min( 1, (int) $rec['views'] / 1000 );
			}
		}
		arsort( $count );
		return array_slice( array_map( 'intval', array_keys( $count ) ), 0, $limit );
	}

	/**
	 * Ids of records sorted by a comparator.
	 *
	 * @param array<int,array<string,mixed>> $records Records.
	 * @return int[]
	 */
	private static function ids_sorted( array $records, callable $compare, int $limit ): array {
		usort( $records, $compare );
		return array_slice( array_map( static fn( array $r ): int => (int) $r['id'], $records ), 0, max( 0, $limit ) );
	}

	/* ---------------------------------------------------------------------
	 * Product picks
	 * ------------------------------------------------------------------- */

	/**
	 * «پیشنهاد لحظه‌ای»: the deepest discounts in stock (≥ strike threshold), cheapest first on ties.
	 *
	 * @return int[]
	 */
	public static function flash_ids( int $limit = 5 ): array {
		$strike   = self::thresholds()['strike'];
		$campaign = self::campaign();
		$pool     = array_filter( self::in_scope(), static fn( array $r ): bool => empty( $r['cm'] ) && $r['off'] >= $strike && $r['price'] > 0 );
		if ( $campaign['active'] && $campaign['products'] ) {
			// A running campaign with its own products: the instant offer rotates through them, so its countdown is honest.
			$own = array_filter( $pool, static fn( array $r ): bool => in_array( (int) $r['id'], $campaign['products'], true ) );
			if ( $own ) {
				$pool = $own;
			}
		}
		return self::ids_sorted( $pool, static fn( array $a, array $b ): int => [ $b['off'], $a['price'] ] <=> [ $a['off'], $b['price'] ], $limit );
	}

	/**
	 * Whether every product of a list belongs to the running campaign (any discount counts when the
	 * campaign names no products) — then the campaign countdown may stand next to them.
	 *
	 * @param int[] $ids Product ids.
	 */
	public static function campaign_covers( array $ids ): bool {
		$campaign = self::campaign();
		if ( ! $campaign['active'] ) {
			return false;
		}
		if ( ! $campaign['products'] ) {
			return true;
		}
		return ! array_diff( array_map( 'intval', $ids ), $campaign['products'] );
	}

	/**
	 * Amazing-offers band: the campaign's own products while it runs, else the deepest discounts
	 * (≥ sticker threshold). $exclude (e.g. the instant offer) is skipped while enough remain.
	 *
	 * @param int[] $exclude Product ids to leave out.
	 * @return int[]
	 */
	public static function offer_ids( int $limit = 12, array $exclude = [] ): array {
		$campaign = self::campaign();
		$by_id    = self::by_id();
		if ( $campaign['active'] && $campaign['products'] ) {
			$ids = array_values( array_filter( $campaign['products'], static fn( int $id ): bool => ! empty( $by_id[ $id ]['in'] ) && empty( $by_id[ $id ]['cm'] ) ) );
			if ( $ids ) {
				return array_slice( $ids, 0, $limit );
			}
		}
		$sticker = self::thresholds()['sticker'];
		$pool    = array_filter( self::in_scope(), static fn( array $r ): bool => empty( $r['cm'] ) && $r['off'] >= $sticker && $r['price'] > 0 );
		$rest    = array_filter( $pool, static fn( array $r ): bool => ! in_array( (int) $r['id'], $exclude, true ) );
		if ( count( $rest ) >= min( 6, $limit ) ) {
			$pool = $rest;
		}
		return self::ids_sorted( $pool, static fn( array $a, array $b ): int => [ $b['off'], $b['price'] ] <=> [ $a['off'], $a['price'] ], $limit );
	}

	/**
	 * Number of buyable in-stock products at or above the sticker threshold.
	 */
	public static function discounted_count(): int {
		$sticker = self::thresholds()['sticker'];
		return count( array_filter( self::in_scope(), static fn( array $r ): bool => empty( $r['cm'] ) && $r['off'] >= $sticker ) );
	}

	/**
	 * Newest in-stock products.
	 *
	 * @return int[]
	 */
	public static function newest_ids( int $limit = 12 ): array {
		return self::ids_sorted( self::in_scope(), static fn( array $a, array $b ): int => [ $b['t'], $b['id'] ] <=> [ $a['t'], $a['id'] ], $limit );
	}

	/**
	 * Ranked board: by total sales once orders exist, else by (sampled) views — basis tells which.
	 *
	 * @return array{basis:string,ids:int[]}
	 */
	public static function ranked( int $limit = 12 ): array {
		$pool  = self::in_scope();
		$sales = (bool) array_filter( self::snapshot(), static fn( array $r ): bool => $r['sales'] > 0 );
		$ids   = self::ids_sorted(
			$pool,
			$sales
				? static fn( array $a, array $b ): int => [ $b['sales'], $b['views'], $b['t'] ] <=> [ $a['sales'], $a['views'], $a['t'] ]
				: static fn( array $a, array $b ): int => [ $b['views'], $b['t'] ] <=> [ $a['views'], $a['t'] ],
			$limit
		);
		return [
			'basis' => $sales ? 'sales' : 'views',
			'ids'   => $ids,
		];
	}

	/**
	 * Featured picks of a scope: products marked "featured" first, then the priciest in stock.
	 *
	 * @param int[] $term_ids Categories ([] = whole catalogue).
	 * @return int[]
	 */
	public static function featured_ids( array $term_ids = [], int $limit = 10, int $per_lane = 2 ): array {
		$ids = self::ids_sorted( self::in_scope( $term_ids ), static fn( array $a, array $b ): int => [ (int) $b['feat'], $b['price'] ] <=> [ (int) $a['feat'], $a['price'] ] ?: $b['views'] <=> $a['views'], self::MAX_SNAPSHOT );
		if ( $per_lane <= 0 ) {
			return array_slice( $ids, 0, $limit );
		}
		// "Category by category": at most $per_lane picks from one most-specific category, the rest fill up afterwards.
		$by_id = self::by_id();
		$count = [];
		$picks = [];
		$later = [];
		foreach ( $ids as $id ) {
			$lane = self::lane( (array) ( $by_id[ $id ]['cats'] ?? [] ) );
			if ( ( $count[ $lane ] ?? 0 ) < $per_lane ) {
				$count[ $lane ] = ( $count[ $lane ] ?? 0 ) + 1;
				$picks[]        = $id;
			} else {
				$later[] = $id;
			}
			if ( count( $picks ) >= $limit ) {
				break;
			}
		}
		return array_slice( array_merge( $picks, $later ), 0, $limit );
	}

	/**
	 * The most specific category among a product's categories (0 when none).
	 *
	 * @param int[] $cats Category ids.
	 */
	private static function lane( array $cats ): int {
		$best  = 0;
		$depth = -1;
		foreach ( $cats as $cat ) {
			$key = 'depth_' . (int) $cat;
			if ( ! isset( self::$memo[ $key ] ) ) {
				self::$memo[ $key ] = count( get_ancestors( (int) $cat, 'product_cat', 'taxonomy' ) );
			}
			if ( self::$memo[ $key ] > $depth ) {
				$depth = self::$memo[ $key ];
				$best  = (int) $cat;
			}
		}
		return $best;
	}

	/**
	 * Automatic artwork for a scope: in-stock products with a gallery image (the product out of
	 * its box), deepest discount then most viewed. Entries [ id, image index, fade ].
	 *
	 * @param int[] $term_ids Categories.
	 * @param int[] $skip     Products already used.
	 * @return array<int,array{0:int,1:int,2:bool}>
	 */
	public static function auto_art( array $term_ids, int $brand = 0, int $count = 2, array $skip = [] ): array {
		$pool = array_filter( self::in_scope( $term_ids, $brand ), static fn( array $r ): bool => $r['img'] > 0 && ! in_array( (int) $r['id'], $skip, true ) );
		usort( $pool, static fn( array $a, array $b ): int => [ min( 1, $b['gal'] ), $b['off'], $b['views'] ] <=> [ min( 1, $a['gal'] ), $a['off'], $a['views'] ] );
		$out = [];
		foreach ( array_slice( $pool, 0, $count ) as $rec ) {
			$out[] = [ (int) $rec['id'], $rec['gal'] > 0 ? 1 : 0, false ];
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Campaign
	 * ------------------------------------------------------------------- */

	/**
	 * The amazing-offers campaign: enabled, end (timestamp, 0 when none or past), active, title,
	 * sub, products (ids).
	 *
	 * @return array{enabled:bool,end:int,active:bool,title:string,sub:string,products:int[]}
	 */
	public static function campaign( ?string $lang = null ): array {
		$lang    = $lang ?? self::lang();
		$enabled = (bool) Settings::get( 'home.campaign.enabled', true );
		$end     = self::parse_end( (string) Settings::get( 'theme.campaign_end', '' ) );
		return [
			'enabled'  => $enabled,
			'end'      => $end,
			'active'   => $enabled && $end > time(),
			'title'    => Settings::get_lang( 'home.campaign.title', $lang, self::CAMPAIGN_TITLE[ $lang ] ?? self::CAMPAIGN_TITLE['fa'] ),
			'sub'      => Settings::get_lang( 'home.campaign.sub', $lang, self::CAMPAIGN_SUB[ $lang ] ?? self::CAMPAIGN_SUB['fa'] ),
			'products' => self::parse_ids( (string) Settings::get( 'home.campaign.products', '' ) ),
		];
	}

	/**
	 * Real campaign end (0 unless the campaign runs and ends in the future). The theme's
	 * gadgetim_campaign_end() should delegate here so every countdown honours the switch.
	 */
	public static function campaign_end(): int {
		$campaign = self::campaign();
		return $campaign['active'] ? $campaign['end'] : 0;
	}

	/**
	 * "2026-11-30 23:59" or Jalali "1405/09/09 23:59" (any digits, site time) → timestamp (0 when invalid or past).
	 */
	public static function parse_end( string $raw ): int {
		$raw = trim( Digits::to_latin( $raw ) );
		if ( '' === $raw ) {
			return 0;
		}
		$ts = 0;
		if ( preg_match( '#^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $raw, $m ) ) {
			$y = (int) $m[1];
			$h = isset( $m[4] ) ? (int) $m[4] : 23;
			$i = isset( $m[5] ) ? (int) $m[5] : 59;
			$s = isset( $m[6] ) ? (int) $m[6] : ( isset( $m[4] ) ? 0 : 59 );
			if ( $y < 1700 && class_exists( Jalali::class ) ) {
				if ( Jalali::is_valid( $y, (int) $m[2], (int) $m[3] ) ) {
					$ts = Jalali::to_timestamp( $y, (int) $m[2], (int) $m[3], $h, $i, $s );
				}
			} elseif ( checkdate( (int) $m[2], (int) $m[3], $y ) ) {
				$ts = ( new DateTimeImmutable( 'now', wp_timezone() ) )->setDate( $y, (int) $m[2], (int) $m[3] )->setTime( $h, $i, $s )->getTimestamp();
			}
		} else {
			try {
				$ts = ( new DateTimeImmutable( $raw, wp_timezone() ) )->getTimestamp();
			} catch ( Exception $e ) {
				$ts = 0;
			}
		}
		return $ts > time() ? $ts : 0;
	}

	/* ---------------------------------------------------------------------
	 * Slides, banners, services
	 * ------------------------------------------------------------------- */

	/**
	 * Hero slides for a language. Each: tone, word, eyebrow, title, highlight, sub, sub_short, cta,
	 * cta_short, chip, chip_icon, terms (ids), url, art ([ id, image, fade ] × ≤ 2, visible products),
	 * max_off, brands (term ids), auto (bool).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function slides( ?string $lang = null ): array {
		$lang = $lang ?? self::lang();
		return (array) self::cached( 'slides', static fn(): array => self::build_slides( $lang ), $lang );
	}

	/**
	 * Builds the slides.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_slides( string $lang ): array {
		$rows = self::stored_rows( 'home.slides' );
		$auto = ! $rows;
		if ( $auto ) {
			$rows = self::default_slide_rows();
		}
		$out  = [];
		$used = [];
		foreach ( $rows as $i => $row ) {
			$slide = self::normalize_slide( $row, $lang, $i, $used );
			if ( $slide ) {
				$slide['auto'] = $auto;
				$out[]         = $slide;
				foreach ( $slide['art'] as $entry ) {
					$used[] = (int) $entry[0];
				}
			}
		}
		return array_slice( $out, 0, self::MAX_SLIDES );
	}

	/**
	 * One slide row → display data (null when it has no title).
	 *
	 * @param array<string,mixed> $row  Row.
	 * @param int[]               $used Products already shown by earlier slides.
	 * @return array<string,mixed>|null
	 */
	private static function normalize_slide( array $row, string $lang, int $index, array $used ): ?array {
		$title     = self::pick( $row['title'] ?? '', $lang );
		$highlight = self::pick( $row['highlight'] ?? '', $lang );
		if ( '' === $title && '' === $highlight ) {
			return null;
		}
		$terms = self::existing_terms( (array) ( $row['terms'] ?? [] ), 'product_cat' );
		$art   = self::visible_art( (array) ( $row['art'] ?? [] ) );
		if ( count( $art ) < 2 && $terms ) {
			$skip = array_merge( $used, array_map( static fn( array $a ): int => (int) $a[0], $art ) );
			$art  = array_merge( $art, self::auto_art( $terms, 0, 2 - count( $art ), $skip ) );
		}
		$tone = in_array( $row['tone'] ?? '', self::SLIDE_TONES, true ) ? (string) $row['tone'] : self::SLIDE_TONES[ $index % count( self::SLIDE_TONES ) ];
		$icon = in_array( $row['chip_icon'] ?? '', self::ICONS, true ) ? (string) $row['chip_icon'] : 'shield';
		return [
			'tone'      => $tone,
			'word'      => strtoupper( preg_replace( '/[^A-Za-z0-9 &+-]/', '', (string) ( $row['word'] ?? '' ) ) ?? '' ),
			'eyebrow'   => self::pick( $row['eyebrow'] ?? '', $lang ),
			'title'     => $title,
			'highlight' => $highlight,
			'sub'       => self::pick( $row['sub'] ?? '', $lang ),
			'sub_short' => self::pick( $row['sub_short'] ?? '', $lang ),
			'cta'       => self::pick( $row['cta'] ?? '', $lang ),
			'cta_short' => self::pick( $row['cta_short'] ?? '', $lang ),
			'chip'      => self::pick( $row['chip'] ?? '', $lang ),
			'chip_icon' => $icon,
			'terms'     => $terms,
			'url'       => self::link( (string) ( $row['url'] ?? '' ), $terms, 0 ),
			'art'       => array_slice( $art, 0, 2 ),
			'max_off'   => $terms ? self::max_discount( $terms ) : 0,
			'brands'    => $terms ? self::scope_brands( $terms, 3 ) : [],
		];
	}

	/**
	 * Promo banners for a language. Each: tone, label, title, highlight, sub, cta, terms, brand, url,
	 * art, max_off, auto.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function banners( ?string $lang = null ): array {
		$lang = $lang ?? self::lang();
		return (array) self::cached(
			'banners',
			static function () use ( $lang ): array {
				$rows = self::stored_rows( 'home.banners' );
				$auto = ! $rows;
				if ( $auto ) {
					$rows = self::default_banner_rows();
				}
				$out = [];
				foreach ( $rows as $i => $row ) {
					$title     = self::pick( $row['title'] ?? '', $lang );
					$highlight = self::pick( $row['highlight'] ?? '', $lang );
					if ( '' === $title && '' === $highlight ) {
						continue;
					}
					$terms = self::existing_terms( (array) ( $row['terms'] ?? [] ), 'product_cat' );
					$brand = self::existing_terms( [ (int) ( $row['brand'] ?? 0 ) ], 'product_brand' )[0] ?? 0;
					$art   = self::visible_art( (array) ( $row['art'] ?? [] ) );
					if ( count( $art ) < 2 && ( $terms || $brand ) ) {
						$art = array_merge( $art, self::auto_art( $terms, $brand, 2 - count( $art ), array_map( static fn( array $a ): int => (int) $a[0], $art ) ) );
					}
					$out[] = [
						'tone'      => in_array( $row['tone'] ?? '', self::BANNER_TONES, true ) ? (string) $row['tone'] : self::BANNER_TONES[ $i % count( self::BANNER_TONES ) ],
						'label'     => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
						'title'     => $title,
						'highlight' => $highlight,
						'sub'       => self::pick( $row['sub'] ?? '', $lang ),
						'cta'       => self::pick( $row['cta'] ?? '', $lang ),
						'terms'     => $terms,
						'brand'     => $brand,
						'url'       => self::link( (string) ( $row['url'] ?? '' ), $terms, $brand ),
						'art'       => array_slice( $art, 0, 2 ),
						'max_off'   => ( $terms || $brand ) ? self::max_discount( $terms, $brand ) : 0,
						'auto'      => $auto,
					];
				}
				return array_slice( $out, 0, self::MAX_BANNERS );
			},
			$lang
		);
	}

	/**
	 * Services strip items for a language. Each: icon, tone, title, text, url.
	 *
	 * @return array<int,array{icon:string,tone:string,title:string,text:string,url:string}>
	 */
	public static function services( ?string $lang = null ): array {
		$lang = $lang ?? self::lang();
		$rows = self::stored_rows( 'home.services' );
		if ( ! $rows ) {
			$rows = self::default_service_rows();
		}
		$out = [];
		foreach ( $rows as $i => $row ) {
			$title = self::pick( $row['title'] ?? '', $lang );
			if ( '' === $title ) {
				continue;
			}
			$out[] = [
				'icon'  => in_array( $row['icon'] ?? '', self::ICONS, true ) ? (string) $row['icon'] : 'shield',
				'tone'  => in_array( $row['tone'] ?? '', self::SERVICE_TONES, true ) ? (string) $row['tone'] : self::SERVICE_TONES[ $i % count( self::SERVICE_TONES ) ],
				'title' => $title,
				'text'  => self::pick( $row['text'] ?? '', $lang ),
				'url'   => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
			];
		}
		return array_slice( $out, 0, self::MAX_SERVICES );
	}

	/**
	 * Stored rows of a structured repeater ([] when nothing is saved).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function stored_rows( string $path ): array {
		$value = Settings::get( $path, [] );
		return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : [];
	}

	/**
	 * Text of a per-language cell: the language, else Persian (the settings convention).
	 */
	private static function pick( mixed $cell, string $lang ): string {
		if ( is_array( $cell ) ) {
			$value = (string) ( $cell[ $lang ] ?? '' );
			if ( '' === trim( $value ) ) {
				$value = (string) ( $cell['fa'] ?? '' );
			}
			return trim( $value );
		}
		return is_scalar( $cell ) ? trim( (string) $cell ) : '';
	}

	/**
	 * Ids of terms that exist and are visible in the current language (theme filter gadgetim_menu_terms / gadgetim_brand_terms).
	 *
	 * @param int[] $ids Term ids.
	 * @return int[]
	 */
	private static function existing_terms( array $ids, string $taxonomy ): array {
		$out = [];
		foreach ( array_map( 'intval', $ids ) as $id ) {
			$term = $id > 0 ? get_term( $id, $taxonomy ) : null;
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$filter = 'product_brand' === $taxonomy ? 'gadgetim_brand_terms' : 'gadgetim_menu_terms';
			$kept   = (array) apply_filters( $filter, [ $term ], self::lang() );
			if ( $kept ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Art entries whose product is visible in this language and in stock.
	 *
	 * @param array<int,mixed> $art Art entries.
	 * @return array<int,array{0:int,1:int,2:bool}>
	 */
	private static function visible_art( array $art ): array {
		$by_id = self::by_id();
		$out   = [];
		foreach ( $art as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id = (int) ( $entry[0] ?? 0 );
			if ( empty( $by_id[ $id ]['in'] ) ) {
				continue;
			}
			$out[] = [ $id, max( 0, (int) ( $entry[1] ?? 0 ) ), ! empty( $entry[2] ) ];
		}
		return $out;
	}

	/**
	 * Link of a slide/banner: its own URL, else the first category, else the brand, else the shop.
	 *
	 * @param int[] $terms Category ids.
	 */
	private static function link( string $url, array $terms, int $brand ): string {
		if ( '' !== $url ) {
			return $url;
		}
		foreach ( $terms as $id ) {
			$link = get_term_link( (int) $id, 'product_cat' );
			if ( is_string( $link ) ) {
				return $link;
			}
		}
		if ( $brand > 0 ) {
			$link = get_term_link( $brand, 'product_brand' );
			if ( is_string( $link ) ) {
				return $link;
			}
		}
		return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	/* ---------------------------------------------------------------------
	 * Category tiles and statistics
	 * ------------------------------------------------------------------- */

	/**
	 * Shop-by-category tiles: id, big (bool), count (products incl. descendants), art ([ id, image, fade ] × ≤ 2).
	 * Curated defaults by slug, then the categories with the most products. Filter gadgetim_home_tiles.
	 *
	 * @return array<int,array{id:int,big:bool,count:int,art:array}>
	 */
	public static function category_tiles( int $limit = 12 ): array {
		$tiles = (array) self::cached(
			'tiles' . $limit,
			static function () use ( $limit ): array {
				$out  = [];
				$seen = [];
				$used = [];
				foreach ( self::curated_tiles() as [ $slug, $big, $refs ] ) {
					$term = get_term_by( 'slug', $slug, 'product_cat' );
					if ( ! $term instanceof WP_Term || isset( $seen[ $term->term_id ] ) || ! self::existing_terms( [ (int) $term->term_id ], 'product_cat' ) ) {
						continue;
					}
					$count = count( self::in_scope( [ (int) $term->term_id ], 0, false ) );
					if ( $count <= 0 ) {
						continue;
					}
					$art = self::visible_art( self::resolve_refs( $refs ) );
					$art = array_merge( $art, self::auto_art( [ (int) $term->term_id ], 0, ( $big ? 2 : 1 ) - count( $art ), array_merge( $used, array_map( static fn( array $a ): int => (int) $a[0], $art ) ) ) );
					foreach ( $art as $a ) {
						$used[] = (int) $a[0];
					}
					$seen[ $term->term_id ] = true;
					$out[]                  = [
						'id'    => (int) $term->term_id,
						'big'   => (bool) $big,
						'count' => $count,
						'art'   => array_slice( $art, 0, $big ? 2 : 1 ),
					];
				}
				if ( count( $out ) < $limit ) {
					// Unknown catalogue (or a sparse one): fill with the categories holding the most products.
					$counts = [];
					foreach ( self::snapshot() as $rec ) {
						foreach ( $rec['cats'] as $cat ) {
							$counts[ (int) $cat ] = ( $counts[ (int) $cat ] ?? 0 ) + 1;
						}
					}
					arsort( $counts );
					$default = (int) get_option( 'default_product_cat' );
					foreach ( array_keys( $counts ) as $cat ) {
						if ( count( $out ) >= $limit ) {
							break;
						}
						if ( $cat === $default || isset( $seen[ $cat ] ) || ! self::existing_terms( [ $cat ], 'product_cat' ) ) {
							continue;
						}
						$seen[ $cat ] = true;
						$big          = count( $out ) < 2 && ! array_filter( $out, static fn( array $t ): bool => $t['big'] );
						$art          = self::auto_art( [ $cat ], 0, $big ? 2 : 1, $used );
						foreach ( $art as $a ) {
							$used[] = (int) $a[0];
						}
						$out[] = [
							'id'    => $cat,
							'big'   => $big,
							'count' => count( self::in_scope( [ $cat ], 0, false ) ),
							'art'   => $art,
						];
					}
				}
				return array_slice( $out, 0, $limit );
			}
		);
		/**
		 * Filters the home category tiles.
		 *
		 * @param array $tiles Tiles (id, big, count, art).
		 */
		return (array) apply_filters( 'gadgetim_home_tiles', $tiles );
	}

	/**
	 * Real shop statistics: products, brands, categories (with products) and the return window in days.
	 *
	 * @return array{products:int,brands:int,categories:int,return_days:int}
	 */
	public static function stats(): array {
		return (array) self::cached( 'stats', [ self::class, 'build_stats' ] );
	}

	/**
	 * Builds the statistics (uncached).
	 *
	 * @return array{products:int,brands:int,categories:int,return_days:int}
	 */
	public static function build_stats(): array {
		$snapshot = self::snapshot();
		$brands   = [];
		$cats     = [];
		foreach ( $snapshot as $rec ) {
			if ( $rec['brand'] > 0 ) {
				$brands[ (int) $rec['brand'] ] = true;
			}
			foreach ( $rec['cats'] as $cat ) {
				$cats[ (int) $cat ] = true;
				foreach ( get_ancestors( (int) $cat, 'product_cat', 'taxonomy' ) as $parent ) {
					$cats[ (int) $parent ] = true;
				}
			}
		}
		unset( $cats[ (int) get_option( 'default_product_cat' ) ] );
		$days = 0;
		if ( preg_match( '/(\d{1,3})/', Digits::to_latin( Settings::get_lang( 'trust.return_window' ) ), $m ) ) {
			$days = (int) $m[1];
		}
		return [
			'products'    => count( $snapshot ),
			'brands'      => count( $brands ),
			'categories'  => count( $cats ),
			'return_days' => $days,
		];
	}

	/* ---------------------------------------------------------------------
	 * Defaults (the Aurora prototype's curated content, resolved against the catalogue)
	 * ------------------------------------------------------------------- */

	/**
	 * Old-site product ids (_gtm_import_old_id) → current ids, in order; unknown refs are dropped.
	 *
	 * @param array<int,array{0:int,1:int,2?:bool}> $refs [ old id, image index, fade ].
	 * @return array<int,array{0:int,1:int,2:bool}>
	 */
	private static function resolve_refs( array $refs ): array {
		global $wpdb;
		if ( ! isset( self::$memo['refs'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one small lookup, memoised and cached with the results.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product' WHERE pm.meta_key = %s", Brands::META_OLD_ID ) );
			$map  = [];
			foreach ( (array) $rows as $row ) {
				$map[ (int) $row->meta_value ] = (int) $row->post_id;
			}
			self::$memo['refs'] = $map;
		}
		$out = [];
		foreach ( $refs as $ref ) {
			$id = (int) ( self::$memo['refs'][ (int) $ref[0] ] ?? 0 );
			if ( $id > 0 ) {
				$out[] = [ $id, (int) ( $ref[1] ?? 0 ), ! empty( $ref[2] ) ];
			}
		}
		return $out;
	}

	/**
	 * Category slugs → ids (missing slugs dropped).
	 *
	 * @param string[] $slugs Slugs as typed in the admin.
	 * @return int[]
	 */
	private static function slugs_to_ids( array $slugs ): array {
		$out = [];
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term instanceof WP_Term ) {
				$out[] = (int) $term->term_id;
			}
		}
		return $out;
	}

	/**
	 * Automatic slide rows (stored-row shape): the prototype's four slides when their categories
	 * exist, else one slide per large category with generic copy.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function default_slide_rows(): array {
		$rows = [];
		foreach ( self::curated_slides() as $def ) {
			$terms = self::slugs_to_ids( $def['terms'] );
			if ( ! $terms ) {
				continue;
			}
			$rows[] = array_merge( $def, [
				'terms' => $terms,
				'art'   => self::resolve_refs( $def['art'] ),
				'url'   => '',
			] );
		}
		if ( $rows ) {
			return $rows;
		}
		// Generic: the four categories with the most products.
		$counts = [];
		foreach ( self::snapshot() as $rec ) {
			foreach ( $rec['cats'] as $cat ) {
				$counts[ (int) $cat ] = ( $counts[ (int) $cat ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );
		foreach ( array_slice( array_keys( $counts ), 0, 4 ) as $i => $cat ) {
			$term = get_term( (int) $cat, 'product_cat' );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$name   = (string) $term->name;
			$rows[] = [
				'tone'      => self::SLIDE_TONES[ $i % count( self::SLIDE_TONES ) ],
				'word'      => '',
				'chip_icon' => 'shield',
				'eyebrow'   => [ 'fa' => $name ],
				'title'     => [
					'fa' => 'بهترین‌های',
					'en' => 'The best of',
					'ar' => 'الأفضل في',
				],
				'highlight' => [ 'fa' => $name ],
				'sub'       => [
					'fa' => 'اصل و اورجینال، با ضمانت اصالت کالا',
					'en' => 'Genuine and original, with an authenticity guarantee',
					'ar' => 'أصلي ١٠٠٪ مع ضمان الأصالة',
				],
				'cta'       => [
					'fa' => 'مشاهده و خرید',
					'en' => 'Shop now',
					'ar' => 'تسوّق الآن',
				],
				'chip'      => [
					'fa' => '۱۰۰٪ اصل و اورجینال',
					'en' => '100% genuine',
					'ar' => 'أصلي ١٠٠٪',
				],
				'terms'     => [ (int) $cat ],
				'url'       => '',
				'art'       => [],
			];
		}
		return $rows;
	}

	/**
	 * Automatic banner rows (stored-row shape).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function default_banner_rows(): array {
		$rows = [];
		foreach ( self::curated_banners() as $def ) {
			$terms = self::slugs_to_ids( $def['terms'] );
			$brand = 0;
			if ( '' !== $def['brand'] && taxonomy_exists( 'product_brand' ) ) {
				$term  = get_term_by( 'slug', $def['brand'], 'product_brand' );
				$brand = $term instanceof WP_Term ? (int) $term->term_id : 0;
			}
			if ( ! $terms && ! $brand ) {
				continue;
			}
			$rows[] = array_merge( $def, [
				'terms' => $terms,
				'brand' => $brand,
				'art'   => self::resolve_refs( $def['art'] ),
				'url'   => '',
			] );
		}
		return $rows;
	}

	/**
	 * Default services strip rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function default_service_rows(): array {
		return [
			[
				'icon'  => 'shield',
				'tone'  => 'violet',
				'title' => [
					'fa' => 'اصل و اورجینال',
					'en' => 'Genuine and original',
					'ar' => 'أصلي ١٠٠٪',
				],
				'text'  => [
					'fa' => 'با ضمانت اصالت کالا',
					'en' => 'With an authenticity guarantee',
					'ar' => 'مع ضمان الأصالة',
				],
				'url'   => '',
			],
			[
				'icon'  => 'truck',
				'tone'  => 'sky',
				'title' => [
					'fa' => 'ارسال به سراسر ایران',
					'en' => 'Delivery across Iran',
					'ar' => 'شحن إلى كل إيران',
				],
				'text'  => [
					'fa' => 'بسته‌بندی ایمن و سریع',
					'en' => 'Safe, careful packaging',
					'ar' => 'تغليف آمن وسريع',
				],
				'url'   => '',
			],
			[
				'icon'  => 'return',
				'tone'  => 'emerald',
				'title' => [
					'fa' => '۷ روز ضمانت بازگشت',
					'en' => '7-day returns',
					'ar' => 'إرجاع خلال ٧ أيام',
				],
				'text'  => [
					'fa' => 'بی‌دردسر و شفاف',
					'en' => 'Simple and transparent',
					'ar' => 'بسهولة وشفافية',
				],
				'url'   => '',
			],
			[
				'icon'  => 'chat',
				'tone'  => 'green',
				'title' => [
					'fa' => 'پشتیبانی واتساپ',
					'en' => 'WhatsApp support',
					'ar' => 'دعم عبر واتساب',
				],
				'text'  => [
					'fa' => 'مشاوره پیش از خرید',
					'en' => 'Advice before you buy',
					'ar' => 'استشارة قبل الشراء',
				],
				'url'   => '',
			],
			[
				'icon'  => 'lock',
				'tone'  => 'pink',
				'title' => [
					'fa' => 'پرداخت امن',
					'en' => 'Secure payment',
					'ar' => 'دفع آمن',
				],
				'text'  => [
					'fa' => 'درگاه بانکی معتبر',
					'en' => 'Through a trusted bank gateway',
					'ar' => 'عبر بوابة بنكية موثوقة',
				],
				'url'   => '',
			],
		];
	}

	/**
	 * The prototype's slides: categories by slug, artwork by import id ([ old id, image, fade ]).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function curated_slides(): array {
		return [
			[
				'tone'      => 'indigo',
				'word'      => 'SOUND',
				'chip_icon' => 'shield',
				'terms'     => [ 'هدفون', 'ایرپاد', 'هندزفری' ],
				'art'       => [ [ 5970, 1 ], [ 6165, 1, true ] ],
				'eyebrow'   => [
					'fa' => 'هدفون و هندزفری',
					'en' => 'Headphones and earbuds',
					'ar' => 'سماعات الرأس والأذن',
				],
				'title'     => [
					'fa' => 'صدای ناب،',
					'en' => 'Pure sound,',
					'ar' => 'صوت نقي،',
				],
				'highlight' => [
					'fa' => 'آزادی بی‌سیم',
					'en' => 'wireless freedom',
					'ar' => 'وحرية لاسلكية',
				],
				'sub'       => [
					'fa' => 'هدفون و هندزفری‌های اورجینال با ضمانت اصالت کالا',
					'en' => 'Original headphones and earbuds with an authenticity guarantee',
					'ar' => 'سماعات أصلية مع ضمان الأصالة',
				],
				'sub_short' => [
					'fa' => 'هدفون و هندزفری اورجینال، با ضمانت اصالت',
					'en' => 'Original headphones, guaranteed',
					'ar' => 'سماعات أصلية مضمونة',
				],
				'cta'       => [
					'fa' => 'خرید هدفون و هندزفری',
					'en' => 'Shop headphones',
					'ar' => 'تسوّق السماعات',
				],
				'cta_short' => [
					'fa' => 'خرید هدفون',
					'en' => 'Headphones',
					'ar' => 'السماعات',
				],
				'chip'      => [
					'fa' => '۱۰۰٪ اصل و اورجینال',
					'en' => '100% genuine',
					'ar' => 'أصلي ١٠٠٪',
				],
			],
			[
				'tone'      => 'violet',
				'word'      => 'POWER',
				'chip_icon' => 'bolt',
				'terms'     => [ 'پاور-بانک', 'شارژر', 'کابل-شارژ' ],
				'art'       => [ [ 5995, 1 ], [ 6052, 1 ] ],
				'eyebrow'   => [
					'fa' => 'پاوربانک، شارژر و کابل',
					'en' => 'Power banks, chargers and cables',
					'ar' => 'باور بانك وشواحن وكابلات',
				],
				'title'     => [
					'fa' => 'همیشه شارژ،',
					'en' => 'Always charged,',
					'ar' => 'دائمًا مشحون،',
				],
				'highlight' => [
					'fa' => 'هرجا که باشی',
					'en' => 'wherever you are',
					'ar' => 'أينما كنت',
				],
				'sub'       => [
					'fa' => 'پاوربانک، شارژر و کابل‌های پرسرعت اورجینال',
					'en' => 'Original fast power banks, chargers and cables',
					'ar' => 'باور بانك وشواحن وكابلات سريعة أصلية',
				],
				'sub_short' => [
					'fa' => 'پاوربانک، شارژر و کابل پرسرعت اورجینال',
					'en' => 'Fast, original charging gear',
					'ar' => 'شحن سريع وأصلي',
				],
				'cta'       => [
					'fa' => 'مشاهده پاوربانک‌ها',
					'en' => 'See power banks',
					'ar' => 'تسوّق الباور بانك',
				],
				'cta_short' => [
					'fa' => 'پاوربانک‌ها',
					'en' => 'Power banks',
					'ar' => 'باور بانك',
				],
				'chip'      => [
					'fa' => 'شارژ فوق سریع',
					'en' => 'Super-fast charging',
					'ar' => 'شحن فائق السرعة',
				],
			],
			[
				'tone'      => 'rose',
				'word'      => 'HOME',
				'chip_icon' => 'sparkle',
				'terms'     => [ 'گجت-های-خانگی', 'قهوه-ساز', 'شیکر-و-بلندر', 'خشبو-کننده-هوا', 'توستر', 'اجاق-گاز' ],
				'art'       => [ [ 6181, 3, true ], [ 5980, 1, true ] ],
				'eyebrow'   => [
					'fa' => 'خانه و آشپزخانه',
					'en' => 'Home and kitchen',
					'ar' => 'المنزل والمطبخ',
				],
				'title'     => [
					'fa' => 'گجت‌های هوشمند',
					'en' => 'Smart gadgets',
					'ar' => 'أجهزة ذكية',
				],
				'highlight' => [
					'fa' => 'برای هر روز',
					'en' => 'for every day',
					'ar' => 'لكل يوم',
				],
				'sub'       => [
					'fa' => 'شیکر شارژی، اسپرسوساز سفری و گجت‌های خانه',
					'en' => 'Rechargeable blenders, travel espresso makers and home gadgets',
					'ar' => 'خلاط قابل للشحن وماكينة إسبريسو محمولة وأجهزة منزلية',
				],
				'sub_short' => [
					'fa' => 'شیکر شارژی، اسپرسوساز سفری و گجت خانه',
					'en' => 'Blenders, espresso makers and more',
					'ar' => 'خلاطات وماكينات إسبريسو',
				],
				'cta'       => [
					'fa' => 'گجت‌های خانگی',
					'en' => 'Home gadgets',
					'ar' => 'أجهزة المنزل',
				],
				'cta_short' => [
					'fa' => 'گجت خانگی',
					'en' => 'Home',
					'ar' => 'المنزل',
				],
				'chip'      => [
					'fa' => 'شیکر شارژی ۶ تیغه',
					'en' => '6-blade rechargeable blender',
					'ar' => 'خلاط قابل للشحن بست شفرات',
				],
			],
			[
				'tone'      => 'amber',
				'word'      => 'ROAD',
				'chip_icon' => 'gauge',
				'terms'     => [ 'لوازم-جانبی-خودرو', 'پمپ-باد', 'جامپ-استارتر-و-جت-فن', 'شارژر-فندکی', 'صداگیر-و-سایلنسر-کمربند', 'اینورتر-و-مبدل-برق' ],
				'art'       => [ [ 6154, 1, true ], [ 5812, 1 ] ],
				'eyebrow'   => [
					'fa' => 'لوازم جانبی خودرو',
					'en' => 'Car accessories',
					'ar' => 'إكسسوارات السيارة',
				],
				'title'     => [
					'fa' => 'همراه همیشگی',
					'en' => 'Your companion',
					'ar' => 'رفيقك الدائم',
				],
				'highlight' => [
					'fa' => 'جاده و سفر',
					'en' => 'on every road trip',
					'ar' => 'على الطريق',
				],
				'sub'       => [
					'fa' => 'پمپ باد شارژی، جت فن و شارژر فندکی سوپرفست',
					'en' => 'Cordless inflators, jet fans and fast car chargers',
					'ar' => 'منفاخ هواء لاسلكي وشاحن سيارة فائق السرعة',
				],
				'sub_short' => [
					'fa' => 'پمپ باد شارژی، جت فن و شارژر فندکی',
					'en' => 'Inflators, jet fans, car chargers',
					'ar' => 'منفاخ هواء وشاحن سيارة',
				],
				'cta'       => [
					'fa' => 'لوازم جانبی خودرو',
					'en' => 'Car accessories',
					'ar' => 'إكسسوارات السيارة',
				],
				'cta_short' => [
					'fa' => 'لوازم خودرو',
					'en' => 'Car',
					'ar' => 'السيارة',
				],
				'chip'      => [
					'fa' => 'فشار تا ۱۵۰ PSI',
					'en' => 'Up to 150 PSI',
					'ar' => 'ضغط حتى 150 PSI',
				],
			],
		];
	}

	/**
	 * The prototype's banners.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function curated_banners(): array {
		return [
			[
				'tone'      => 'violet',
				'label'     => 'GREEN LION',
				'terms'     => [ 'پاور-بانک' ],
				'brand'     => 'green-lion',
				'art'       => [ [ 5995, 1 ], [ 5995, 0 ] ],
				'title'     => [
					'fa' => 'پاوربانک‌های',
					'en' => 'Power banks by',
					'ar' => 'باور بانك من',
				],
				'highlight' => [
					'fa' => 'گرین لاین',
					'en' => 'Green Lion',
					'ar' => 'غرين ليون',
				],
				'sub'       => [
					'fa' => 'شارژ سریع، ظرفیت بالا و طراحی خوش‌دست برای روزهای پرمشغله',
					'en' => 'Fast charging, high capacity and a design that fits busy days',
					'ar' => 'شحن سريع وسعة عالية وتصميم مريح للأيام المزدحمة',
				],
				'cta'       => [
					'fa' => 'مشاهده و خرید',
					'en' => 'Shop now',
					'ar' => 'تسوّق الآن',
				],
			],
			[
				'tone'      => 'sunset',
				'label'     => 'JBL',
				'terms'     => [],
				'brand'     => 'jbl',
				'art'       => [ [ 5512, 3, true ], [ 5512, 1, true ] ],
				'title'     => [
					'fa' => 'دنیای صدای',
					'en' => 'The sound of',
					'ar' => 'عالم صوت',
				],
				'highlight' => [
					'fa' => 'JBL',
					'en' => 'JBL',
					'ar' => 'JBL',
				],
				'sub'       => [
					'fa' => 'هدفون‌های بی‌سیم و اسپیکرهای اورجینال جی‌بی‌ال',
					'en' => 'Original JBL wireless headphones and speakers',
					'ar' => 'سماعات ومكبرات صوت JBL أصلية',
				],
				'cta'       => [
					'fa' => 'مشاهده و خرید',
					'en' => 'Shop now',
					'ar' => 'تسوّق الآن',
				],
			],
			[
				'tone'      => 'ocean',
				'label'     => 'KORANCO',
				'terms'     => [],
				'brand'     => 'koranco',
				'art'       => [ [ 5357, 0 ], [ 5823, 0 ] ],
				'title'     => [
					'fa' => 'کابل، شارژر و فلش',
					'en' => 'Cables, chargers and flash drives',
					'ar' => 'كابلات وشواحن وذاكرات',
				],
				'highlight' => [
					'fa' => 'کورانکو',
					'en' => 'by Koranco',
					'ar' => 'من كورانكو',
				],
				'sub'       => [
					'fa' => 'لوازم جانبی پرسرعت و بادوام برای استفاده‌ی هر روز',
					'en' => 'Fast, durable accessories for every day',
					'ar' => 'إكسسوارات سريعة ومتينة للاستخدام اليومي',
				],
				'cta'       => [
					'fa' => 'مشاهده و خرید',
					'en' => 'Shop now',
					'ar' => 'تسوّق الآن',
				],
			],
		];
	}

	/**
	 * The prototype's bento tiles: [ category slug, big, [ [ old id, image, fade ] ] ].
	 *
	 * @return array<int,array{0:string,1:bool,2:array}>
	 */
	private static function curated_tiles(): array {
		return [
			[ 'لوازم-جانبی-موبایل', true, [ [ 6263, 1 ], [ 6277, 1 ] ] ],
			[ 'گجت-های-صوتی-وتصویری', true, [ [ 5896, 1, true ], [ 5970, 5 ] ] ],
			[ 'فلش-مموری', false, [ [ 5529, 2 ] ] ],
			[ 'شارژر', false, [ [ 5350, 1 ] ] ],
			[ 'کابل-شارژ', false, [ [ 6052, 2 ] ] ],
			[ 'ایرپاد', false, [ [ 6123, 2 ] ] ],
			[ 'پاور-بانک', false, [ [ 5995, 1 ] ] ],
			[ 'هدفون', false, [ [ 5986, 1 ] ] ],
			[ 'زیبایی-و-سلامت', false, [ [ 6058, 1, true ] ] ],
			[ 'لوازم-جانبی-خودرو', false, [ [ 6138, 0 ] ] ],
			[ 'کمپینگ-و-سفر', false, [ [ 5963, 0 ] ] ],
			[ 'ماساژور', false, [ [ 6065, 1 ] ] ],
		];
	}
}
