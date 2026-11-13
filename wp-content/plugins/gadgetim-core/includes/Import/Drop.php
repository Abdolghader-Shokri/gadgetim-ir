<?php
/**
 * Packages dropped on the host (client, 2026-11-11): the shop owner uploads a zip built by
 * tools/build-product-drop.php or tools/build-page-drop.php into wp-content/gadgetim-drop/ with
 * the host's file manager, and the content appears on the site by itself.
 *
 * A package holds manifest.json (version 1) and images/, with either list or both:
 * - products[]: each product is matched by SKU. A new SKU creates a published simple product, a
 *   known one is updated (texts, prices, specs, translations; images only when the product has none).
 * - pages[]: a branded featured image for an existing published page, found by its path; the
 *   expected ID guards against a different page at that address. A page that already has a
 *   featured image keeps it unless the entry says "replace" (image program, 2026-11-14: the site
 *   database cannot be shipped, so the images travel this way).
 *
 * The scan runs on the next dashboard page load of a shop manager and hourly through WP-Cron. A
 * processed package moves to done/ (or failed/ when nothing could be imported), a line per item
 * goes to log.txt, and the dashboard shows a notice with links.
 *
 * Without PHP's zip extension an extracted package folder (gadgetim-drop/<name>/manifest.json)
 * is read the same way.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\Catalog\ProductExtras;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\Pricing\Handover;
use Gadgetim\Core\Seo\Meta;
use WC_Product;
use WC_Product_Simple;
use WP_Post;
use ZipArchive;

/**
 * Scanner and importer for dropped product and page-image packages.
 */
final class Drop {

	public const DIR = 'gadgetim-drop';

	public const VERSION = 1;

	public const CRON = 'gadgetim_drop_scan';

	public const LOCK = 'gadgetim_drop_lock';

	public const RESULTS = 'gadgetim_drop_results';

	public const META_PACKAGE = '_gtm_drop_package';

	public const IMAGE_TYPES = [ 'webp', 'jpg', 'jpeg', 'png' ];

	/** The open package of the current import. */
	private static ?ZipArchive $zip = null;

	public const TRANSLATED = [ 'title', 'excerpt', 'content', 'subtitle', 'seo_title', 'seo_desc' ];

	/**
	 * Attaches the hooks (Import module).
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'maybe_scan' ], 40 );
		add_action( self::CRON, [ self::class, 'scan' ] );
		add_action( 'init', [ self::class, 'schedule' ], 20 );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
	}

	/**
	 * init: hourly WP-Cron scan.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON );
		}
	}

	/**
	 * The drop folder (created with a deny-all .htaccess and an index.php on first use).
	 */
	public static function dir(): string {
		$dir = trailingslashit( WP_CONTENT_DIR ) . self::DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( is_dir( $dir ) && ! is_file( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- best effort.
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- best effort.
		}
		return $dir;
	}

	/**
	 * admin_init: scans for a shop manager on a normal dashboard page.
	 */
	public static function maybe_scan(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		self::scan();
	}

	/**
	 * Imports every waiting package.
	 *
	 * @return array<int,array> Results of this run.
	 */
	public static function scan(): array {
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return [];
		}
		$dir      = self::dir();
		$packages = array_merge( glob( $dir . '/*.zip' ) ?: [], glob( $dir . '/*/manifest.json' ) ?: [] );
		if ( ! $packages || get_transient( self::LOCK ) ) {
			return [];
		}
		set_transient( self::LOCK, 1, 15 * MINUTE_IN_SECONDS );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not allowed on every host.
		}

		$run = [];
		foreach ( $packages as $path ) {
			$run[] = self::import_package( $path );
		}
		delete_transient( self::LOCK );

		$all = array_merge( $run, (array) get_option( self::RESULTS, [] ) );
		update_option( self::RESULTS, array_slice( $all, 0, 20 ), false );
		return $run;
	}

	/**
	 * Imports one package (zip file or extracted folder) and moves it aside.
	 */
	public static function import_package( string $path ): array {
		$is_zip = str_ends_with( strtolower( $path ), '.zip' );
		$name   = $is_zip ? basename( $path ) : basename( dirname( $path ) );
		$result = [
			'package' => $name,
			'time'    => time(),
			'items'   => [],
			'errors'  => [],
			'seen'    => false,
		];

		$read     = self::reader( $path, $is_zip, $result['errors'] );
		$data     = $read ? json_decode( (string) $read( 'manifest.json' ), true ) : null;
		$products = is_array( $data ) && is_array( $data['products'] ?? null ) ? $data['products'] : [];
		$pages    = is_array( $data ) && is_array( $data['pages'] ?? null ) ? $data['pages'] : [];
		if ( ! is_array( $data ) || (int) ( $data['version'] ?? 0 ) !== self::VERSION || ( ! $products && ! $pages ) ) {
			if ( $read ) {
				$result['errors'][] = __( 'manifest.json is missing or not a version 1 package.', 'gadgetim-core' );
			}
		} else {
			foreach ( $products as $p ) {
				$item = is_array( $p ) ? self::import_product( $p, $read, $name ) : [ 'error' => 'invalid entry' ];
				self::collect( $result, $item, is_array( $p ) ? (string) ( $p['sku'] ?? '' ) : '' );
			}
			foreach ( $pages as $p ) {
				$item = is_array( $p ) ? self::import_page( $p, $read, $name ) : [ 'error' => 'invalid entry' ];
				self::collect( $result, $item, is_array( $p ) ? rawurldecode( (string) ( $p['path'] ?? '' ) ) : '' );
			}
		}

		if ( self::$zip instanceof ZipArchive ) {
			self::$zip->close(); // Windows cannot move an open file.
			self::$zip = null;
		}
		self::archive( $path, $is_zip, $result['items'] ? 'done' : 'failed' );
		self::log( $result );
		return $result;
	}

	/**
	 * Adds one entry's outcome to the package result.
	 *
	 * @param array  $result Package result.
	 * @param array  $item   Outcome, or [ 'error' => … ].
	 * @param string $label  SKU or page path, put in front of an error.
	 */
	private static function collect( array &$result, array $item, string $label ): void {
		if ( isset( $item['error'] ) ) {
			$result['errors'][] = ( '' !== $label ? $label . ': ' : '' ) . $item['error'];
			return;
		}
		$result['items'][] = $item;
	}

	/**
	 * A reader for files inside the package, or null (error recorded).
	 *
	 * @param string[] $errors Error list.
	 */
	private static function reader( string $path, bool $is_zip, array &$errors ): ?callable {
		if ( ! $is_zip ) {
			$base = dirname( $path );
			return static function ( string $file ) use ( $base ): ?string {
				$full = $base . '/' . $file;
				if ( str_contains( $file, '..' ) || ! is_file( $full ) ) {
					return null;
				}
				$bytes = file_get_contents( $full ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
				return false === $bytes ? null : $bytes;
			};
		}
		if ( ! class_exists( ZipArchive::class ) ) {
			$errors[] = __( 'PHP zip support is not available on this server: extract the zip into its own folder inside gadgetim-drop instead.', 'gadgetim-core' );
			return null;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			$errors[] = __( 'The zip file could not be opened.', 'gadgetim-core' );
			return null;
		}
		self::$zip = $zip;
		return static function ( string $file ) use ( $zip ): ?string {
			$bytes = $zip->getFromName( $file );
			return false === $bytes ? null : $bytes;
		};
	}

	/**
	 * Creates or updates one product.
	 *
	 * @param array    $p       Manifest entry.
	 * @param callable $read    Package reader.
	 * @param string   $package Package name.
	 */
	public static function import_product( array $p, callable $read, string $package ): array {
		$sku = sanitize_text_field( (string) ( $p['sku'] ?? '' ) );
		$fa  = is_array( $p['fa'] ?? null ) ? $p['fa'] : [];
		if ( '' === $sku || empty( $fa['title'] ) ) {
			return [ 'error' => __( 'a product needs a SKU and a Persian title.', 'gadgetim-core' ) ];
		}

		$id      = (int) wc_get_product_id_by_sku( $sku );
		$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
		if ( ! $product instanceof WC_Product ) {
			return [ 'error' => __( 'the existing product with this SKU could not be loaded.', 'gadgetim-core' ) ];
		}
		$created = 0 === $id;

		$product->set_name( sanitize_text_field( (string) $fa['title'] ) );
		if ( $created && ! empty( $fa['slug'] ) ) {
			$product->set_slug( sanitize_title( (string) $fa['slug'] ) );
		}
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( $sku );
		$product->set_description( wp_kses_post( (string) ( $fa['content'] ?? '' ) ) );
		$product->set_short_description( wp_kses_post( (string) ( $fa['excerpt'] ?? '' ) ) );

		$cats = self::category_ids( (array) ( $p['categories'] ?? [] ) );
		if ( $cats ) {
			$product->set_category_ids( $cats );
		}
		self::apply_stock( $product, (array) ( $p['stock'] ?? [] ) );
		self::apply_price( $product, (array) ( $p['price'] ?? [] ) );

		foreach ( [
			'subtitle'  => ProductExtras::META_SUBTITLE,
			'seo_title' => ProductExtras::META_SEO_TITLE,
			'seo_desc'  => ProductExtras::META_SEO_DESC,
		] as $field => $key ) {
			if ( isset( $fa[ $field ] ) ) {
				$product->update_meta_data( $key, sanitize_text_field( (string) $fa[ $field ] ) );
			}
		}
		if ( isset( $fa['specs'] ) ) {
			$product->update_meta_data( ProductExtras::META_SPECS, ProductExtras::sanitize_specs( wp_json_encode( $fa['specs'] ) ) );
		}
		$product->update_meta_data( self::META_PACKAGE, $package );

		$id = (int) $product->save();
		if ( $id <= 0 ) {
			return [ 'error' => __( 'WooCommerce did not save the product.', 'gadgetim-core' ) ];
		}

		if ( ! empty( $p['brand'] ) ) {
			$brand = self::term_id( (string) $p['brand'], Brands::TAXONOMY );
			if ( $brand ) {
				wp_set_object_terms( $id, [ $brand ], Brands::TAXONOMY, false );
			}
		}

		$images = [];
		if ( $created || ! $product->get_image_id() ) {
			foreach ( (array) ( $p['images'] ?? [] ) as $image ) {
				$aid = is_array( $image ) ? self::import_image( $image, $read, $id ) : 0;
				if ( $aid > 0 ) {
					$images[] = $aid;
				}
			}
			if ( $images ) {
				$product = wc_get_product( $id );
				$product->set_image_id( array_shift( $images ) );
				$product->set_gallery_image_ids( $images );
				$product->save();
			}
		}

		self::apply_translations( $id, (array) ( $p['translations'] ?? [] ) );
		clean_post_cache( $id );

		return [
			'id'     => $id,
			'sku'    => $sku,
			'title'  => (string) $fa['title'],
			'action' => $created ? 'created' : 'updated',
			'url'    => (string) get_permalink( $id ),
		];
	}

	/**
	 * Gives an existing page its featured image.
	 *
	 * @param array    $p       Manifest entry: path, id (expected), image { file, title, alt,
	 *                          caption, description, text_lang }, replace.
	 * @param callable $read    Package reader.
	 * @param string   $package Package name.
	 */
	public static function import_page( array $p, callable $read, string $package ): array {
		$page = self::find_page( (string) ( $p['path'] ?? '' ) );
		if ( ! $page ) {
			return [ 'error' => __( 'the page was not found on this site.', 'gadgetim-core' ) ];
		}
		$expected = (int) ( $p['id'] ?? 0 );
		if ( $expected > 0 && $expected !== (int) $page->ID ) {
			return [ 'error' => __( 'the page at this address has a different ID than expected.', 'gadgetim-core' ) ];
		}
		$image = is_array( $p['image'] ?? null ) ? $p['image'] : [];
		if ( empty( $image['file'] ) ) {
			return [ 'error' => __( 'a page entry needs an image file.', 'gadgetim-core' ) ];
		}

		$item = [
			'id'     => (int) $page->ID,
			'sku'    => '',
			'title'  => (string) $page->post_title,
			'action' => 'kept',
			'url'    => (string) get_permalink( $page ),
		];
		if ( has_post_thumbnail( $page ) && empty( $p['replace'] ) ) {
			return $item;
		}

		$aid = self::import_image( $image, $read, (int) $page->ID );
		if ( $aid <= 0 ) {
			return [ 'error' => __( 'the image could not be added to the media library.', 'gadgetim-core' ) ];
		}
		$lang = sanitize_key( (string) ( $image['text_lang'] ?? '' ) );
		if ( '' !== $lang ) {
			update_post_meta( $aid, Meta::META_TEXT_LANG, $lang );
		}
		update_post_meta( $aid, self::META_PACKAGE, $package );
		set_post_thumbnail( $page, $aid );
		clean_post_cache( (int) $page->ID );

		$item['action'] = 'image';
		return $item;
	}

	/**
	 * A published page by its path (Persian slugs as written or percent-encoded), or null.
	 */
	private static function find_page( string $path ): ?WP_Post {
		$path = trim( rawurldecode( $path ), '/' );
		if ( '' === $path ) {
			return null;
		}
		$page = get_page_by_path( $path, OBJECT, 'page' );
		return $page instanceof WP_Post && 'publish' === $page->post_status ? $page : null;
	}

	/**
	 * Category ids from ids, slugs or names (existing terms only).
	 *
	 * @param array $refs References.
	 * @return int[]
	 */
	private static function category_ids( array $refs ): array {
		$ids = [];
		foreach ( $refs as $ref ) {
			$tid = self::term_id( (string) $ref, 'product_cat' );
			if ( $tid ) {
				$ids[] = $tid;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Existing term id from an id, slug or name; 0 when there is none.
	 */
	private static function term_id( string $ref, string $taxonomy ): int {
		$ref = trim( $ref );
		if ( '' === $ref || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		foreach ( ctype_digit( $ref ) ? [ 'id' ] : [ 'slug', 'name' ] as $by ) {
			$term = get_term_by( $by, 'slug' === $by ? sanitize_title( $ref ) : ( 'id' === $by ? (int) $ref : $ref ), $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}
		return 0;
	}

	/**
	 * Stock: a quantity turns on stock management; otherwise in stock without a count.
	 *
	 * @param array $stock { quantity?: int, status?: instock|outofstock }.
	 */
	private static function apply_stock( WC_Product $product, array $stock ): void {
		if ( isset( $stock['quantity'] ) && is_numeric( $stock['quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $stock['quantity'] );
			$product->set_backorders( 'no' );
			return;
		}
		$product->set_manage_stock( false );
		$product->set_stock_status( 'outofstock' === ( $stock['status'] ?? '' ) ? 'outofstock' : 'instock' );
	}

	/**
	 * Price: a currency base (materialised into Toman by the Pricing module on save) or Toman.
	 *
	 * @param array $price { currency, regular, sale? }.
	 */
	private static function apply_price( WC_Product $product, array $price ): void {
		$num      = static fn( $v ): string => is_numeric( $v ) ? (string) ( 0 + $v ) : '';
		$regular  = $num( $price['regular'] ?? '' );
		$sale     = $num( $price['sale'] ?? '' );
		$currency = strtoupper( sanitize_key( (string) ( $price['currency'] ?? '' ) ) );
		if ( '' === $regular ) {
			return;
		}
		if ( '' !== $sale && (float) $sale >= (float) $regular ) {
			$sale = '';
		}
		if ( '' === $currency || in_array( $currency, [ 'IRT', 'TOMAN' ], true ) ) {
			$product->delete_meta_data( '_gtm_price_currency' );
			$product->set_regular_price( $regular );
			$product->set_sale_price( $sale );
			return;
		}
		$product->update_meta_data( '_gtm_price_currency', $currency );
		$product->update_meta_data( '_gtm_base_regular_price', $regular );
		$product->update_meta_data( '_gtm_base_sale_price', $sale );

		// With the currency pricing plugin active, its fields carry the price (Pricing\Handover).
		if ( Handover::active() && Handover::apply_to_product( $product, $currency, $regular, $sale ) ) {
			if ( class_exists( 'CRS_Product_Pricing' ) && method_exists( \CRS_Product_Pricing::instance(), 'apply_stored_prices' ) ) {
				\CRS_Product_Pricing::instance()->apply_stored_prices( $product );
			}
		}
	}

	/**
	 * Copies one image from the package into the media library.
	 *
	 * @param array    $image   { file, title, caption, alt, description }.
	 * @param callable $read    Package reader.
	 * @param int      $post_id Product.
	 */
	private static function import_image( array $image, callable $read, int $post_id ): int {
		$file = basename( (string) ( $image['file'] ?? '' ) );
		$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( '' === $file || ! in_array( $ext, self::IMAGE_TYPES, true ) ) {
			return 0;
		}
		$bytes = $read( 'images/' . $file );
		if ( null === $bytes || '' === $bytes ) {
			return 0;
		}
		$upload = wp_upload_bits( sanitize_file_name( $file ), null, $bytes );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}
		$type = wp_check_filetype( $upload['file'] );
		$aid  = wp_insert_attachment(
			[
				'post_title'     => sanitize_text_field( (string) ( $image['title'] ?? '' ) ),
				'post_excerpt'   => sanitize_text_field( (string) ( $image['caption'] ?? '' ) ),
				'post_content'   => sanitize_text_field( (string) ( $image['description'] ?? '' ) ),
				'post_mime_type' => (string) ( $type['type'] ?? 'image/webp' ),
				'post_status'    => 'inherit',
			],
			$upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $aid ) || ! $aid ) {
			return 0;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		wp_update_attachment_metadata( (int) $aid, wp_generate_attachment_metadata( (int) $aid, $upload['file'] ) );
		update_post_meta( (int) $aid, '_wp_attachment_image_alt', sanitize_text_field( (string) ( $image['alt'] ?? '' ) ) );
		return (int) $aid;
	}

	/**
	 * en/ar texts, slugs and specs; a language with a title is published.
	 *
	 * @param array $translations lang => fields.
	 */
	private static function apply_translations( int $id, array $translations ): void {
		foreach ( $translations as $lang => $t ) {
			$lang = sanitize_key( (string) $lang );
			if ( ! is_array( $t ) || empty( $t['title'] ) ) {
				continue;
			}
			foreach ( self::TRANSLATED as $field ) {
				if ( isset( $t[ $field ] ) ) {
					PostTranslations::set( $id, $field, $lang, (string) $t[ $field ] );
				}
			}
			if ( ! empty( $t['slug'] ) ) {
				$slug = Slugs::validate( (string) $t['slug'], $lang, 'product', $id );
				if ( '' !== $slug ) {
					PostTranslations::set( $id, 'slug', $lang, $slug );
				}
			}
			if ( isset( $t['specs'] ) ) {
				update_post_meta( $id, ProductExtras::META_SPECS . '_' . $lang, ProductExtras::sanitize_specs( wp_json_encode( $t['specs'] ) ) );
			}
			PostTranslations::set( $id, 'status', $lang, 'published' );
		}
	}

	/**
	 * Moves a processed package into done/ or failed/ (never imported twice).
	 */
	private static function archive( string $path, bool $is_zip, string $to ): void {
		$dir = self::dir() . '/' . $to;
		wp_mkdir_p( $dir );
		$src  = $is_zip ? $path : dirname( $path );
		$dest = $dir . '/' . gmdate( 'Ymd-His' ) . '-' . basename( $src );
		if ( ! @rename( $src, $dest ) && $is_zip ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- result checked.
			@unlink( $src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- never import twice.
		}
	}

	/**
	 * Appends the run to gadgetim-drop/log.txt.
	 */
	private static function log( array $result ): void {
		$lines = [ gmdate( 'c' ) . ' ' . $result['package'] ];
		foreach ( $result['items'] as $i ) {
			$sku     = (string) ( $i['sku'] ?? '' );
			$lines[] = '  ' . $i['action'] . ' #' . $i['id'] . ( '' !== $sku ? ' ' . $sku : '' ) . ' ' . $i['url'];
		}
		foreach ( $result['errors'] as $e ) {
			$lines[] = '  ERROR ' . $e;
		}
		@file_put_contents( self::dir() . '/log.txt', implode( "\n", $lines ) . "\n", FILE_APPEND ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- best effort log.
	}

	/**
	 * What happened to an item, for the notice.
	 */
	private static function action_label( string $action ): string {
		return match ( $action ) {
			'created' => __( 'added', 'gadgetim-core' ),
			'image'   => __( 'featured image added', 'gadgetim-core' ),
			'kept'    => __( 'kept: the page already has a featured image', 'gadgetim-core' ),
			default   => __( 'updated', 'gadgetim-core' ),
		};
	}

	/**
	 * admin_notices: results not shown yet, with links to the products and pages.
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$all     = (array) get_option( self::RESULTS, [] );
		$changed = false;
		foreach ( $all as $k => $r ) {
			if ( ! is_array( $r ) || ! empty( $r['seen'] ) ) {
				continue;
			}
			$class = empty( $r['errors'] ) ? 'notice-success' : ( empty( $r['items'] ) ? 'notice-error' : 'notice-warning' );
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>';
			/* translators: %s: package file name */
			echo esc_html( sprintf( __( 'Package %s was imported:', 'gadgetim-core' ), (string) $r['package'] ) );
			echo '</strong></p><ul style="list-style:disc;padding-inline-start:20px">';
			foreach ( (array) $r['items'] as $i ) {
				$label = self::action_label( (string) ( $i['action'] ?? '' ) );
				echo '<li><a href="' . esc_url( (string) $i['url'] ) . '" target="_blank">' . esc_html( (string) $i['title'] ) . '</a> (' . esc_html( $label ) . ')</li>';
			}
			foreach ( (array) $r['errors'] as $e ) {
				echo '<li style="color:#b32d2e">' . esc_html( (string) $e ) . '</li>';
			}
			echo '</ul></div>';
			$all[ $k ]['seen'] = true;
			$changed           = true;
		}
		if ( $changed ) {
			update_option( self::RESULTS, $all, false );
		}
	}
}
