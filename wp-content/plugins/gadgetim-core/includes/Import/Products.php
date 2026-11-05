<?php
/**
 * Product import as WC_Product_Simple (architecture §6.1 step 2): short title, old slug,
 * old date, stock, featured, terms, extras meta, AED base prices (materialised by Pricing on save).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\Catalog\ProductExtras;
use Gadgetim\Core\Catalog\TitleCleaner;
use Gadgetim\Core\Support\Options;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WP_Error;
use WP_Post;

/**
 * Idempotent through `_gtm_import_old_id`; re-runs update the product from the export but
 * never touch the live view counter or a manual short-title override.
 */
final class Products {

	public const CURRENCY = 'AED';

	public const META_OLD_URL = '_gtm_import_old_url';

	public const FALLBACK_RATE = 78000;

	public const FALLBACK_STEP = 10000;

	/** Attribute keys of the export → display labels. */
	private const ATTRIBUTE_LABELS = [
		'color'    => 'رنگ',
		'colour'   => 'رنگ',
		'size'     => 'سایز',
		'material' => 'جنس',
		'capacity' => 'ظرفیت',
	];

	private Importer $importer;

	private bool $pricing_warned = false;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Imports one exported product (creates or updates).
	 */
	public function import( array $p ): void {
		$job    = $this->importer->job();
		$old_id = (int) ( $p['id'] ?? 0 );
		$title  = trim( (string) ( $p['title'] ?? '' ) );
		if ( $old_id <= 0 || '' === $title ) {
			throw new \RuntimeException( 'Product entry without id or title.' );
		}
		$short = TitleCleaner::short( $title );
		$slug  = rawurldecode( trim( (string) ( $p['slug'] ?? '' ) ) );
		if ( '' === $slug ) {
			$slug = sanitize_title( $short );
		}

		$existing_id = $this->find_existing( $old_id, $slug );
		$terms       = $this->importer->terms();
		$cat_ids     = $terms->category_ids_for_product( $p );
		$brand_names = array_values( array_filter( array_map( 'strval', (array) ( $p['brands'] ?? [] ) ), 'strlen' ) );
		$brand_ids   = $terms->brand_ids_for_names( $brand_names );
		$tag_ids     = $terms->tag_ids_for_names( (array) ( $p['tags'] ?? [] ) );
		$brand_label = $brand_names ? $terms->brand_display_name( $brand_names[0] ) : '';
		$alt_default = trim( $short . ( '' !== $brand_label ? ' ' . $brand_label : '' ) );

		$extra   = is_array( $p['extra_tab'] ?? null ) ? (string) ( $p['extra_tab']['content'] ?? '' ) : '';
		$cleaned = $this->importer->cleaner()->product(
			(string) ( $p['description'] ?? '' ),
			(string) ( $p['short_description'] ?? '' ),
			[
				'title'       => $title,
				'short_title' => $short,
				'extra'       => $extra,
			]
		);

		$image_count = ( ! empty( $p['image']['file'] ) ? 1 : 0 ) + count( (array) ( $p['gallery'] ?? [] ) );
		$with_images = $job->has_step( 'images' );

		if ( $this->importer->dry() ) {
			++$job->counts[ $existing_id > 0 ? 'updated' : 'created' ];
			if ( $with_images ) {
				$job->counts['images'] += $image_count;
			}
			$this->log(
				Log::INFO,
				sprintf(
					/* translators: 1: create/update verb, 2: short title, 3: slug, 4: category count, 5: brand count, 6: spec count, 7: image count, 8: AED regular, 9: AED sale */
					__( '[dry run] would %1$s "%2$s" (/product/%3$s/) — categories %4$d, brands %5$d, specs %6$d, images %7$d, AED %8$s / %9$s', 'gadgetim-core' ),
					$existing_id > 0 ? 'update' : 'create',
					$short,
					$slug,
					count( $cat_ids ),
					count( $brand_ids ),
					count( $cleaned['specs'] ),
					$image_count,
					(string) ( $p['currency_regular_price'] ?? '' ),
					(string) ( $p['currency_sale_price'] ?? '' )
				)
			);
			return;
		}

		$product = $existing_id > 0 ? wc_get_product( $existing_id ) : new WC_Product_Simple();
		if ( ! $product instanceof WC_Product ) {
			$product = new WC_Product_Simple();
		}
		$is_new = $product->get_id() <= 0;

		$product->set_name( $short );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_featured( ! empty( $p['special'] ) );
		$product->set_reviews_allowed( true );
		// Old upload URLs resolve through attachments that already exist (_gtm_import_src), so a
		// products-only re-run keeps the rewritten URLs instead of reverting to gadgetim.ir.
		$product->set_description( $this->importer->images()->rewrite_content( $cleaned['description'], true ) );
		$product->set_short_description( $cleaned['excerpt'] );

		$date = trim( (string) ( $p['date'] ?? '' ) );
		if ( '' !== $date && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
			$product->set_date_created( $date );
		}

		$this->apply_stock( $product, $p );
		$product->set_category_ids( $cat_ids );
		$product->set_tag_ids( $tag_ids );
		$attributes = $this->attributes( $p );
		if ( $attributes ) {
			$product->set_attributes( $attributes );
		}

		$product->update_meta_data( ProductExtras::META_IMPORT_ID, $old_id );
		$product->update_meta_data( self::META_OLD_URL, esc_url_raw( (string) ( $p['old_url'] ?? '' ) ) );

		$subtitle = sanitize_text_field( (string) ( $p['subtitle'] ?? '' ) );
		if ( '' !== $subtitle ) {
			$product->update_meta_data( ProductExtras::META_SUBTITLE, $subtitle );
		} else {
			$product->delete_meta_data( ProductExtras::META_SUBTITLE );
		}
		if ( $short !== $title ) {
			$product->update_meta_data( ProductExtras::META_SEO_TITLE, sanitize_text_field( $title ) );
		}
		$specs_json = ProductExtras::encode_specs( $cleaned['specs'] );
		if ( '' !== $specs_json ) {
			$product->update_meta_data( ProductExtras::META_SPECS, $specs_json );
		}
		if ( $is_new || '' === (string) $product->get_meta( ProductExtras::META_VIEWS, true ) ) {
			$product->update_meta_data( ProductExtras::META_VIEWS, max( 0, (int) ( $p['views'] ?? 0 ) ) );
		}

		$this->apply_pricing( $product, $p );

		$product_id = $product->save();
		if ( $product_id <= 0 ) {
			throw new \RuntimeException( 'WooCommerce returned no product id on save.' );
		}
		if ( $brand_ids && Brands::exists() ) {
			wp_set_object_terms( $product_id, $brand_ids, Brands::TAXONOMY, false );
		}

		if ( $with_images ) {
			$images = $this->import_images( $p, $product_id, $alt_default, $date );
			$changed = false;
			if ( $images['image'] > 0 && (string) $product->get_image_id( 'edit' ) !== (string) $images['image'] ) {
				$product->set_image_id( $images['image'] );
				$changed = true;
			}
			if ( $images['gallery'] && array_map( 'intval', (array) $product->get_gallery_image_ids( 'edit' ) ) !== $images['gallery'] ) {
				$product->set_gallery_image_ids( $images['gallery'] );
				$changed = true;
			}
			$description = $this->importer->images()->rewrite_content( $cleaned['description'] );
			if ( $description !== (string) $product->get_description( 'edit' ) ) {
				$product->set_description( $description );
				$changed = true;
			}
			if ( $changed ) {
				$product->save();
			}
		}

		$stored_slug = rawurldecode( (string) get_post_field( 'post_name', $product_id, 'raw' ) );
		if ( $stored_slug !== $slug ) {
			$this->log(
				Log::WARNING,
				/* translators: 1: short title, 2: requested slug, 3: stored slug */
				sprintf( __( '"%1$s": slug "%2$s" was stored as "%3$s" (collision); add a redirect.', 'gadgetim-core' ), $short, $slug, $stored_slug )
			);
		}

		++$job->counts[ $is_new ? 'created' : 'updated' ];
		$this->log(
			Log::SUCCESS,
			sprintf(
				/* translators: 1: created/updated, 2: product id, 3: short title, 4: spec count */
				__( '%1$s product #%2$d "%3$s" (%4$d spec rows).', 'gadgetim-core' ),
				$is_new ? __( 'Created', 'gadgetim-core' ) : __( 'Updated', 'gadgetim-core' ),
				$product_id,
				$short,
				count( $cleaned['specs'] )
			)
		);
	}

	/**
	 * Imports the featured image and the gallery of an already saved product; returns the ids.
	 *
	 * @return array{image:int,gallery:int[]}
	 */
	public function import_images( array $p, int $product_id, string $alt_default, string $date = '' ): array {
		$images  = $this->importer->images();
		$result  = [
			'image'   => 0,
			'gallery' => [],
		];
		$feature = is_array( $p['image'] ?? null ) ? $p['image'] : [];
		if ( ! empty( $feature['file'] ) ) {
			$id = $images->import( $feature, $product_id, $alt_default, $date );
			if ( $id instanceof WP_Error ) {
				++$this->importer->job()->counts['failed'];
				$this->log( Log::ERROR, $id->get_error_message() );
			} elseif ( $id > 0 ) {
				$result['image'] = $id;
			}
		}
		foreach ( (array) ( $p['gallery'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['file'] ) ) {
				continue;
			}
			$id = $images->import( $entry, $product_id, $alt_default, $date );
			if ( $id instanceof WP_Error ) {
				++$this->importer->job()->counts['failed'];
				$this->log( Log::ERROR, $id->get_error_message() );
			} elseif ( $id > 0 && $id !== $result['image'] ) {
				$result['gallery'][] = $id;
			}
		}
		$result['gallery'] = array_values( array_unique( $result['gallery'] ) );
		return $result;
	}

	/**
	 * Existing product id for an old id (meta), else by slug when that product carries no import id.
	 */
	public function find_existing( int $old_id, string $slug = '' ): int {
		$ids = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => ProductExtras::META_IMPORT_ID,
						'value' => (string) $old_id,
					],
				],
			]
		);
		if ( is_array( $ids ) && $ids ) {
			return (int) $ids[0];
		}
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $post instanceof WP_Post && '' === (string) get_post_meta( $post->ID, ProductExtras::META_IMPORT_ID, true ) ) {
				return (int) $post->ID;
			}
		}
		return 0;
	}

	/**
	 * Stock: managed quantity when the old site managed it (WooCommerce derives the status),
	 * otherwise the exported status.
	 */
	private function apply_stock( WC_Product $product, array $p ): void {
		$manage = 'yes' === strtolower( (string) ( $p['manage_stock'] ?? 'no' ) );
		$status = in_array( (string) ( $p['stock_status'] ?? '' ), [ 'instock', 'outofstock', 'onbackorder' ], true ) ? (string) $p['stock_status'] : 'instock';
		$product->set_manage_stock( $manage );
		if ( $manage ) {
			$quantity = (string) ( $p['stock'] ?? '' );
			$product->set_stock_quantity( is_numeric( $quantity ) ? (int) $quantity : 0 );
			$product->set_backorders( 'no' );
		} else {
			$product->set_stock_quantity( null );
		}
		$product->set_stock_status( $status );
	}

	/**
	 * Currency fields (§4.1). The Pricing materialiser turns them into Toman on save; without
	 * the Pricing module the Toman price is computed here with the stored or default AED rate.
	 */
	private function apply_pricing( WC_Product $product, array $p ): void {
		$regular = self::decimal( (string) ( $p['currency_regular_price'] ?? '' ) );
		$sale    = self::decimal( (string) ( $p['currency_sale_price'] ?? '' ) );
		if ( '' === $regular ) {
			$product->delete_meta_data( '_gtm_price_currency' );
			$product->delete_meta_data( '_gtm_base_regular_price' );
			$product->delete_meta_data( '_gtm_base_sale_price' );
			$toman_regular = self::decimal( (string) ( $p['regular_price'] ?? '' ) );
			$toman_sale    = self::decimal( (string) ( $p['sale_price'] ?? '' ) );
			$product->set_regular_price( $toman_regular );
			$product->set_sale_price( '' !== $toman_sale && '' !== $toman_regular && (float) $toman_sale < (float) $toman_regular ? $toman_sale : '' );
			return;
		}
		if ( '' !== $sale && (float) $sale >= (float) $regular ) {
			$sale = '';
		}
		$product->update_meta_data( '_gtm_price_currency', self::CURRENCY );
		$product->update_meta_data( '_gtm_base_regular_price', $regular );
		$product->update_meta_data( '_gtm_base_sale_price', $sale );

		if ( class_exists( 'Gadgetim\Core\Pricing\Materializer' ) ) {
			return; // woocommerce_before_product_object_save computes the Toman prices.
		}
		$currency = Options::currencies()[ self::CURRENCY ] ?? [];
		$rate     = (float) ( $currency['rate'] ?? self::FALLBACK_RATE );
		$step     = max( 1, (int) ( $currency['rounding_step'] ?? self::FALLBACK_STEP ) );
		$toman    = static fn( string $base ): string => (string) ( (int) round( (float) $base * $rate / $step ) * $step );
		$product->set_regular_price( $toman( $regular ) );
		$product->set_sale_price( '' !== $sale ? $toman( $sale ) : '' );
		$product->update_meta_data( '_gtm_rate_used', (string) $rate );
		$product->update_meta_data( '_gtm_priced_at', (string) time() );
		if ( ! $this->pricing_warned ) {
			$this->pricing_warned = true;
			$this->log( Log::WARNING, sprintf( 'Pricing module absent: Toman prices computed with AED rate %s and step %d; re-run "reprice" once Pricing is installed.', (string) $rate, $step ) );
		}
	}

	/**
	 * Custom (non-variation) attributes from the export's { key: [values] } map.
	 *
	 * @return WC_Product_Attribute[]
	 */
	private function attributes( array $p ): array {
		$out      = [];
		$position = 0;
		foreach ( (array) ( $p['attributes'] ?? [] ) as $key => $values ) {
			$values = array_values( array_filter( array_map( static fn( $v ): string => sanitize_text_field( (string) $v ), (array) $values ), 'strlen' ) );
			if ( ! $values || ! class_exists( WC_Product_Attribute::class ) ) {
				continue;
			}
			$label     = self::ATTRIBUTE_LABELS[ strtolower( (string) $key ) ] ?? ucfirst( (string) $key );
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $label );
			$attribute->set_options( $values );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( false );
			$out[] = $attribute;
		}
		return $out;
	}

	/**
	 * Decimal string as entered ('' when empty or not numeric).
	 */
	private static function decimal( string $value ): string {
		$value = trim( str_replace( ',', '', $value ) );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			return '';
		}
		return function_exists( 'wc_format_decimal' ) ? (string) wc_format_decimal( $value ) : $value;
	}

	private function log( string $level, string $message ): void {
		$this->importer->log()->add( $level, $message, 'products' );
	}
}
