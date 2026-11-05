<?php
/**
 * Settings schema: a registry of tabs, sections and fields that drives the admin page,
 * the sanitizer and the defaults.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings;

use Gadgetim\Core\Support\Options;
use Gadgetim\Core\Support\Phone;

/**
 * Schema registry.
 *
 * Field types: text, textarea, richtext, number, toggle, select, phone, email, url,
 * image, repeater (list of text). Any text-like type and repeater accept 'i18n' => true,
 * which stores one value per language: [ 'fa' => …, 'en' => …, 'ar' => … ].
 *
 * Other modules add tabs with Schema::add_tab() / add_section() / add_field() (from init
 * or later) or through the gadgetim_settings_schema filter. Do not build the schema at
 * plugin load: labels are translated.
 */
final class Schema {

	public const TYPES = [ 'text', 'textarea', 'richtext', 'number', 'toggle', 'select', 'phone', 'email', 'url', 'image', 'repeater' ];

	public const I18N_TYPES = [ 'text', 'textarea', 'richtext', 'repeater' ];

	/** Defaults for paths that have no field in the core schema (other modules add the UI). */
	public const CORE_DEFAULTS = [
		'i18n' => [
			'fallback' => '404',
		],
	];

	private static ?array $cache = null;

	/** @var array<string,array> */
	private static array $extra_tabs = [];

	/** @var array<string,array<string,array>> */
	private static array $extra_sections = [];

	/** @var array<string,array<string,array<string,array>>> */
	private static array $extra_fields = [];

	/**
	 * The full, normalised schema: [ tab_id => tab ].
	 */
	public static function get(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$schema = self::core();

		foreach ( self::$extra_tabs as $id => $tab ) {
			$schema[ $id ] = isset( $schema[ $id ] ) ? self::merge_tab( $schema[ $id ], $tab ) : $tab;
		}
		foreach ( self::$extra_sections as $tab_id => $sections ) {
			$schema[ $tab_id ] ??= [ 'label' => ucfirst( $tab_id ) ];
			foreach ( $sections as $sid => $section ) {
				$existing                                = $schema[ $tab_id ]['sections'][ $sid ] ?? [];
				$schema[ $tab_id ]['sections'][ $sid ] = array_replace( $existing, $section, [ 'fields' => array_replace( $existing['fields'] ?? [], $section['fields'] ?? [] ) ] );
			}
		}
		foreach ( self::$extra_fields as $tab_id => $sections ) {
			$schema[ $tab_id ] ??= [ 'label' => ucfirst( $tab_id ) ];
			foreach ( $sections as $sid => $fields ) {
				$schema[ $tab_id ]['sections'][ $sid ] ??= [ 'label' => '' ];
				foreach ( $fields as $path => $def ) {
					$schema[ $tab_id ]['sections'][ $sid ]['fields'][ $path ] = $def;
				}
			}
		}

		/**
		 * Filters the settings schema before normalisation. Add tabs/sections/fields here.
		 *
		 * @param array $schema [ tab_id => [ 'label', 'capability', 'order', 'sections' => [ … ] ] ].
		 */
		$schema = (array) apply_filters( 'gadgetim_settings_schema', $schema );

		self::$cache = self::normalize( $schema );
		return self::$cache;
	}

	/**
	 * Tabs only (no sections): [ id => tab ].
	 */
	public static function tabs(): array {
		return self::get();
	}

	/**
	 * One tab or null.
	 */
	public static function tab( string $id ): ?array {
		return self::get()[ sanitize_key( $id ) ] ?? null;
	}

	/**
	 * Flat field map [ path => field ] for one tab or for the whole schema.
	 */
	public static function fields( ?string $tab = null ): array {
		$fields = [];
		foreach ( self::get() as $id => $def ) {
			if ( null !== $tab && $id !== $tab ) {
				continue;
			}
			foreach ( $def['sections'] as $section ) {
				foreach ( $section['fields'] as $path => $field ) {
					$fields[ $path ] = $field;
				}
			}
		}
		return $fields;
	}

	/**
	 * One field by path or null.
	 */
	public static function field( string $path ): ?array {
		return self::fields()[ $path ] ?? null;
	}

	/**
	 * Nested defaults for gadgetim_settings.
	 */
	public static function defaults(): array {
		$defaults = self::CORE_DEFAULTS;
		foreach ( self::fields() as $path => $field ) {
			self::path_set( $defaults, $path, $field['default'] );
		}

		/**
		 * Filters the settings defaults (nested array).
		 *
		 * @param array $defaults Defaults.
		 */
		return (array) apply_filters( 'gadgetim_settings_defaults', $defaults );
	}

	/**
	 * Registers (or extends) a tab.
	 *
	 * @param string $id         Tab id (sanitize_key).
	 * @param string $label      Translated label.
	 * @param string $capability Capability (Capabilities enforces manage_options for the D11 tabs regardless).
	 * @param array  $fields     Either [ path => field ], or [ section_id => [ 'label', 'fields' => [...] ] ], or [ 'sections' => … ].
	 * @param array  $args       'order', 'description', 'render' (callable for custom tabs), 'url' (link-only tab).
	 */
	public static function add_tab( string $id, string $label, string $capability = '', array $fields = [], array $args = [] ): void {
		$id  = sanitize_key( $id );
		$tab = array_merge(
			$args,
			[
				'id'         => $id,
				'label'      => $label,
				'capability' => $capability,
			]
		);
		if ( isset( $fields['sections'] ) && is_array( $fields['sections'] ) ) {
			$tab['sections'] = $fields['sections'];
		} elseif ( self::looks_like_sections( $fields ) ) {
			$tab['sections'] = $fields;
		} elseif ( $fields ) {
			$tab['sections'] = [ 'main' => [ 'label' => '', 'fields' => $fields ] ];
		} else {
			$tab['sections'] = [];
		}
		self::$extra_tabs[ $id ] = isset( self::$extra_tabs[ $id ] ) ? self::merge_tab( self::$extra_tabs[ $id ], $tab ) : $tab;
		self::invalidate();
	}

	/**
	 * Registers a section inside a tab (the tab is created if missing).
	 */
	public static function add_section( string $tab, string $id, string $label, string $description = '', int $order = 10 ): void {
		self::$extra_sections[ sanitize_key( $tab ) ][ sanitize_key( $id ) ] = [
			'label'       => $label,
			'description' => $description,
			'order'       => $order,
		];
		self::invalidate();
	}

	/**
	 * Registers a field inside a tab section (both created if missing).
	 *
	 * @param string $tab     Tab id.
	 * @param string $section Section id.
	 * @param string $path    Dotted settings path, e.g. 'contact.enabled'.
	 * @param array  $def     Field definition (type, label, description, default, i18n, options, min, max, step, placeholder, rows, capability, sanitize, render).
	 */
	public static function add_field( string $tab, string $section, string $path, array $def ): void {
		self::$extra_fields[ sanitize_key( $tab ) ][ sanitize_key( $section ) ][ self::clean_path( $path ) ] = $def;
		self::invalidate();
	}

	/**
	 * Drops the cached schema (called on init after the text domain loads, and after every add_*).
	 */
	public static function invalidate(): void {
		self::$cache = null;
	}

	/**
	 * Language codes for i18n fields.
	 *
	 * @return string[]
	 */
	public static function languages(): array {
		return Options::language_codes();
	}

	/**
	 * Resolves a select field's options (array or callable) to [ value => label ].
	 */
	public static function options( array $field ): array {
		$options = $field['options'] ?? [];
		if ( is_callable( $options ) ) {
			$options = call_user_func( $options, $field );
		}
		return is_array( $options ) ? $options : [];
	}

	/**
	 * Reads a dotted path from a nested array.
	 */
	public static function path_get( array $data, string $path, mixed $default = null ): mixed {
		$node = $data;
		foreach ( explode( '.', self::clean_path( $path ) ) as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $default;
			}
			$node = $node[ $key ];
		}
		return $node;
	}

	/**
	 * Whether a dotted path exists in a nested array (even with a null value).
	 */
	public static function path_exists( array $data, string $path ): bool {
		$node = $data;
		foreach ( explode( '.', self::clean_path( $path ) ) as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return false;
			}
			$node = $node[ $key ];
		}
		return true;
	}

	/**
	 * Writes a dotted path into a nested array.
	 */
	public static function path_set( array &$data, string $path, mixed $value ): void {
		$node = &$data;
		foreach ( explode( '.', self::clean_path( $path ) ) as $key ) {
			if ( ! isset( $node[ $key ] ) || ! is_array( $node[ $key ] ) ) {
				$node[ $key ] = [];
			}
			$node = &$node[ $key ];
		}
		$node = $value;
	}

	/**
	 * Keeps only [a-z0-9_.-] in a path.
	 */
	public static function clean_path( string $path ): string {
		return trim( preg_replace( '/[^a-z0-9_.-]/', '', strtolower( $path ) ) ?? '', '.' );
	}

	/**
	 * Core tabs: Brand & contact, Trust & policies, Catalog, Tools.
	 */
	private static function core(): array {
		$default_cap = Capabilities::default_capability();

		return [
			'brand'   => [
				'label'      => __( 'Brand & contact', 'gadgetim-core' ),
				'capability' => $default_cap,
				'order'      => 10,
				'sections'   => [
					'identity' => [
						'label'  => __( 'Identity', 'gadgetim-core' ),
						'fields' => [
							'brand.name'         => [
								'type'    => 'text',
								'i18n'    => true,
								'label'   => __( 'Brand name', 'gadgetim-core' ),
								'default' => [
									'fa' => 'گجتیم',
									'en' => 'Gadgetim',
									'ar' => 'Gadgetim',
								],
							],
							'brand.tagline'      => [
								'type'        => 'text',
								'i18n'        => true,
								'label'       => __( 'Tagline', 'gadgetim-core' ),
								'description' => __( 'Shown as the eyebrow line in the header and the hero.', 'gadgetim-core' ),
								'default'     => [ 'fa' => 'فروشگاه گجت‌های اصل' ],
							],
							'brand.logo_id'      => [
								'type'        => 'image',
								'label'       => __( 'Logo (light background)', 'gadgetim-core' ),
								'description' => __( 'SVG or PNG. The theme falls back to its built-in wordmark when empty.', 'gadgetim-core' ),
							],
							'brand.logo_dark_id' => [
								'type'  => 'image',
								'label' => __( 'Logo (dark background)', 'gadgetim-core' ),
							],
						],
					],
					'contact'  => [
						'label'       => __( 'Contact channels', 'gadgetim-core' ),
						'description' => __( 'Used in the header, the footer, the contact strip and the call-for-price buttons.', 'gadgetim-core' ),
						'fields'      => [
							'contact.phone'     => [
								'type'        => 'phone',
								'label'       => __( 'Phone', 'gadgetim-core' ),
								'placeholder' => '09xx xxx xxxx',
							],
							'contact.whatsapp'  => [
								'type'        => 'phone',
								'label'       => __( 'WhatsApp number', 'gadgetim-core' ),
								'placeholder' => '09xx xxx xxxx',
							],
							'contact.telegram'  => [
								'type'        => 'text',
								'label'       => __( 'Telegram username', 'gadgetim-core' ),
								'placeholder' => 'gadgetim',
								'sanitize'    => [ self::class, 'sanitize_handle' ],
								'attrs'       => [ 'dir' => 'ltr' ],
							],
							'contact.instagram' => [
								'type'        => 'text',
								'label'       => __( 'Instagram username', 'gadgetim-core' ),
								'placeholder' => 'gadgetim',
								'sanitize'    => [ self::class, 'sanitize_handle' ],
								'attrs'       => [ 'dir' => 'ltr' ],
							],
							'contact.email'     => [
								'type'  => 'email',
								'label' => __( 'E-mail', 'gadgetim-core' ),
							],
							'contact.address'   => [
								'type'  => 'textarea',
								'i18n'  => true,
								'rows'  => 3,
								'label' => __( 'Address', 'gadgetim-core' ),
							],
							'contact.hours'     => [
								'type'        => 'text',
								'i18n'        => true,
								'label'       => __( 'Working hours', 'gadgetim-core' ),
								'description' => __( 'One line, e.g. "Saturday to Thursday, 9 to 18".', 'gadgetim-core' ),
							],
						],
					],
				],
			],
			'trust'   => [
				'label'      => __( 'Trust & policies', 'gadgetim-core' ),
				'capability' => $default_cap,
				'order'      => 20,
				'sections'   => [
					'badges'   => [
						'label'       => __( 'Trust badges', 'gadgetim-core' ),
						'description' => __( 'The promises shown in the seal band on the home page and under the buy box. Confirm every claim with the client before launch.', 'gadgetim-core' ),
						'fields'      => [
							'trust.badges' => [
								'type'    => 'repeater',
								'i18n'    => true,
								'label'   => __( 'Badges', 'gadgetim-core' ),
								'default' => [
									'fa' => [
										'اصل و اورجینال با ضمانت اصالت کالا',
										'۷ روز ضمانت بازگشت',
										'ارسال به سراسر ایران',
										'پشتیبانی واتساپ و تلگرام',
									],
									'en' => [
										'Genuine products with an authenticity guarantee',
										'7-day return guarantee',
										'Delivery across Iran',
										'WhatsApp and Telegram support',
									],
								],
							],
						],
					],
					'policies' => [
						'label'  => __( 'Policy texts', 'gadgetim-core' ),
						'fields' => [
							'trust.return_window'      => [
								'type'    => 'text',
								'i18n'    => true,
								'label'   => __( 'Return window', 'gadgetim-core' ),
								'default' => [
									'fa' => '۷ روز ضمانت بازگشت کالا',
									'en' => '7-day return guarantee',
								],
							],
							'trust.delivery'           => [
								'type'    => 'text',
								'i18n'    => true,
								'label'   => __( 'Delivery promise', 'gadgetim-core' ),
								'default' => [
									'fa' => 'ارسال به سراسر ایران',
									'en' => 'Delivery across Iran',
								],
							],
							'trust.guarantee'          => [
								'type'        => 'richtext',
								'i18n'        => true,
								'rows'        => 5,
								'label'       => __( 'Guarantee text', 'gadgetim-core' ),
								'description' => __( 'Default text under the buy box; a product can override it in its own guarantee field.', 'gadgetim-core' ),
								'default'     => [
									'fa' => '<p>اصل و اورجینال با ضمانت اصالت کالا</p>',
									'en' => '<p>Genuine and original, with an authenticity guarantee.</p>',
								],
							],
							'trust.verification_steps' => [
								'type'        => 'repeater',
								'i18n'        => true,
								'label'       => __( 'Authenticity verification steps', 'gadgetim-core' ),
								'description' => __( 'The three lines of the authenticity receipt on the product page.', 'gadgetim-core' ),
								'default'     => [
									'fa' => [ 'بسته‌بندی پلمب', 'شناسهٔ گارانتی', 'فاکتور رسمی' ],
									'en' => [ 'Sealed packaging', 'Warranty ID', 'Official invoice' ],
								],
							],
							'trust.guarantee_page'     => [
								'type'        => 'select',
								'label'       => __( 'Guarantee page', 'gadgetim-core' ),
								'description' => __( 'The page the receipt and the seal band link to.', 'gadgetim-core' ),
								'options'     => [ self::class, 'page_options' ],
								'default'     => '0',
							],
						],
					],
				],
			],
			'catalog' => [
				'label'      => __( 'Catalog', 'gadgetim-core' ),
				'capability' => $default_cap,
				'order'      => 30,
				'sections'   => [
					'views' => [
						'label'       => __( 'Popularity', 'gadgetim-core' ),
						'description' => __( 'View counting is sampled: one in N page views sends a beacon, the counter grows by N.', 'gadgetim-core' ),
						'fields'      => [
							'views.enabled' => [
								'type'    => 'toggle',
								'label'   => __( 'Count product views', 'gadgetim-core' ),
								'default' => true,
							],
							'views.sample'  => [
								'type'    => 'number',
								'label'   => __( 'Sampling rate (1 in N)', 'gadgetim-core' ),
								'min'     => 1,
								'max'     => 100,
								'step'    => 1,
								'default' => 5,
							],
						],
					],
				],
			],
			'tools'   => [
				'label'      => __( 'Tools', 'gadgetim-core' ),
				'capability' => 'manage_options',
				'order'      => 90,
				'render'     => [ Admin\Tools::class, 'render_tab' ],
				'sections'   => [],
			],
		];
	}

	/**
	 * Sanitizer for social handles (Telegram, Instagram): "@name", URLs and junk removed.
	 */
	public static function sanitize_handle( mixed $value ): string {
		return is_scalar( $value ) ? Phone::handle( (string) $value ) : '';
	}

	/**
	 * Select options: published pages.
	 *
	 * @return array<string,string>
	 */
	public static function page_options(): array {
		$options = [ '0' => __( '— None —', 'gadgetim-core' ) ];
		foreach ( get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] ) ?: [] as $page ) {
			$options[ (string) $page->ID ] = $page->post_title;
		}
		return $options;
	}

	/**
	 * Normalises every tab, section and field and sorts by order.
	 */
	private static function normalize( array $schema ): array {
		$out   = [];
		$index = 0;
		foreach ( $schema as $id => $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			$id       = sanitize_key( (string) ( $tab['id'] ?? $id ) );
			$sections = [];
			$sindex   = 0;
			foreach ( (array) ( $tab['sections'] ?? [] ) as $sid => $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}
				$sid    = sanitize_key( (string) ( $section['id'] ?? $sid ) );
				$fields = [];
				$findex = 0;
				foreach ( (array) ( $section['fields'] ?? [] ) as $path => $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$path = self::clean_path( (string) ( $field['path'] ?? $path ) );
					if ( '' === $path ) {
						continue;
					}
					$fields[ $path ] = self::normalize_field( $field, $path, $id, $sid, $findex++ );
				}
				uasort( $fields, [ self::class, 'compare' ] );
				$sections[ $sid ] = [
					'id'          => $sid,
					'label'       => (string) ( $section['label'] ?? '' ),
					'description' => (string) ( $section['description'] ?? '' ),
					'order'       => (int) ( $section['order'] ?? 10 ),
					'_index'      => $sindex++,
					'fields'      => $fields,
				];
			}
			uasort( $sections, [ self::class, 'compare' ] );
			$out[ $id ] = [
				'id'          => $id,
				'label'       => (string) ( $tab['label'] ?? ucfirst( $id ) ),
				'description' => (string) ( $tab['description'] ?? '' ),
				'capability'  => (string) ( $tab['capability'] ?? '' ),
				'order'       => (int) ( $tab['order'] ?? 50 ),
				'render'      => is_callable( $tab['render'] ?? null ) ? $tab['render'] : null,
				'url'         => (string) ( $tab['url'] ?? '' ),
				'_index'      => $index++,
				'sections'    => $sections,
			];
			$out[ $id ]['capability'] = Capabilities::for_tab( $id, $out[ $id ] );
		}
		uasort( $out, [ self::class, 'compare' ] );
		return $out;
	}

	private static function normalize_field( array $field, string $path, string $tab, string $section, int $index ): array {
		$type = (string) ( $field['type'] ?? 'text' );
		if ( ! in_array( $type, self::TYPES, true ) && ! is_callable( $field['render'] ?? null ) ) {
			$type = 'text';
		}
		$i18n    = ! empty( $field['i18n'] ) && in_array( $type, self::I18N_TYPES, true );
		$default = $field['default'] ?? self::type_default( $type );
		if ( $i18n ) {
			$langs = self::languages();
			$per   = [];
			foreach ( $langs as $code ) {
				if ( is_array( $default ) && ! array_is_list( $default ) ) {
					$per[ $code ] = $default[ $code ] ?? self::type_default( $type );
				} else {
					$per[ $code ] = 'fa' === $code ? $default : self::type_default( $type );
				}
			}
			$default = $per;
		}
		return [
			'path'        => $path,
			'tab'         => $tab,
			'section'     => $section,
			'type'        => $type,
			'label'       => (string) ( $field['label'] ?? $path ),
			'description' => (string) ( $field['description'] ?? '' ),
			'default'     => $default,
			'i18n'        => $i18n,
			'options'     => $field['options'] ?? [],
			'min'         => $field['min'] ?? null,
			'max'         => $field['max'] ?? null,
			'step'        => $field['step'] ?? null,
			'placeholder' => (string) ( $field['placeholder'] ?? '' ),
			'rows'        => (int) ( $field['rows'] ?? 4 ),
			'capability'  => (string) ( $field['capability'] ?? '' ),
			'sanitize'    => is_callable( $field['sanitize'] ?? null ) ? $field['sanitize'] : null,
			'render'      => is_callable( $field['render'] ?? null ) ? $field['render'] : null,
			'attrs'       => is_array( $field['attrs'] ?? null ) ? $field['attrs'] : [],
			'order'       => (int) ( $field['order'] ?? 10 ),
			'_index'      => $index,
		];
	}

	private static function type_default( string $type ): mixed {
		return match ( $type ) {
			'toggle'   => false,
			'number'   => 0,
			'image'    => 0,
			'repeater' => [],
			default    => '',
		};
	}

	private static function compare( array $a, array $b ): int {
		return [ $a['order'], $a['_index'] ] <=> [ $b['order'], $b['_index'] ];
	}

	private static function looks_like_sections( array $fields ): bool {
		if ( ! $fields ) {
			return false;
		}
		foreach ( $fields as $value ) {
			if ( ! is_array( $value ) || ! array_key_exists( 'fields', $value ) ) {
				return false;
			}
		}
		return true;
	}

	private static function merge_tab( array $base, array $extra ): array {
		$sections = $base['sections'] ?? [];
		foreach ( (array) ( $extra['sections'] ?? [] ) as $sid => $section ) {
			$existing         = $sections[ $sid ] ?? [];
			$sections[ $sid ] = array_replace( $existing, (array) $section );
			if ( isset( $existing['fields'] ) || isset( $section['fields'] ) ) {
				$sections[ $sid ]['fields'] = array_replace( $existing['fields'] ?? [], (array) ( $section['fields'] ?? [] ) );
			}
		}
		unset( $extra['sections'] );
		$merged             = array_replace( $base, array_filter( $extra, static fn( $v ): bool => null !== $v && '' !== $v ) );
		$merged['sections'] = $sections;
		return $merged;
	}
}
