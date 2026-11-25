<?php
/**
 * Packages dropped on the host (client, 2026-11-11): the shop owner uploads a zip built by
 * tools/build-product-drop.php, tools/build-page-drop.php or tools/build-media-drop.php into
 * wp-content/gadgetim-drop/ with the host's file manager, and the content appears on the site by
 * itself.
 *
 * A package holds manifest.json (version 1) with one or more of these lists, and the files they name:
 * - products[]: each product is matched by SKU. A new SKU creates a published simple product, a
 *   known one is updated (texts, prices, specs, translations; images only when the product has none).
 *   "authenticity": "copy" marks a product the shop sells as a non-original copy, and a "guarantee"
 *   text (Persian and per translation) replaces the store-wide guarantee on its page (owner,
 *   2026-11-25: what the client's text says about a copy or a warranty is what the buyer sees).
 * - pages[]: a branded featured image for an existing published page, found by its path; the
 *   expected ID guards against a different page at that address. A page that already has a
 *   featured image keeps it unless the entry says "replace" (image program, 2026-11-14: the site
 *   database cannot be shipped, so the images travel this way).
 * - media[]: files of existing media-library images that are missing from the uploads folder, put
 *   back under the names the library already has. The move to the live host lost every upload
 *   with a Persian file name (2026-11-14: 25 product photos answered 404). Only files that the
 *   image's own record names can be written, a file that is there is never replaced, and no
 *   database row changes.
 * - posts[]: a page created at its path, or the texts of an existing page replaced (SEO engineer,
 *   2026-11-16: new guides and rewritten pages cannot travel in a database copy). A new page is
 *   made only where no page answers yet; an existing one changes only when the entry names its
 *   ID and says "replace". The replaced texts are kept: the Persian ones as a revision saved just
 *   before the update (WordPress itself saves a page only as it is after an update, so an imported
 *   page that never had a revision kept no copy), the en/ar ones in the page's
 *   _gtm_drop_prev_translations meta (JSON, lang => fields).
 * - terms[]: the introduction text of an existing product category or brand, in every language.
 *   A term that already has a text keeps it unless the entry says "replace"; the replaced text
 *   is kept in the term's _gtm_drop_prev_desc meta.
 *
 * The scan runs on the next dashboard page load of a shop manager and hourly through WP-Cron. A
 * processed package moves to done/ (or failed/ when nothing could be imported), a line per item
 * goes to log.txt, and the dashboard shows a notice with links.
 *
 * An update package (a zip with gadgetim-update.json, owner 2026-11-22: one zip for every update)
 * carries the plugin, the theme and drop packages together. Bundle applies it first, on a
 * dashboard page load of an administrator only; this scan leaves it alone.
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
use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Pricing\Handover;
use Gadgetim\Core\Seo\Meta;
use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Jalali;
use WC_Product;
use WC_Product_Simple;
use WP_Post;
use WP_Term;
use ZipArchive;

/**
 * Scanner and importer for dropped product, page-image and media packages.
 */
final class Drop {

	public const DIR = 'gadgetim-drop';

	public const VERSION = 1;

	public const CRON = 'gadgetim_drop_scan';

	public const LOCK = 'gadgetim_drop_lock';

	public const RESULTS = 'gadgetim_drop_results';

	public const META_PACKAGE = '_gtm_drop_package';

	/** en/ar texts a replaced page had before the package (JSON, lang => fields). */
	public const META_PREV_TRANSLATIONS = '_gtm_drop_prev_translations';

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
		add_action( 'wp_dashboard_setup', [ self::class, 'dashboard_widget' ] );
	}

	/**
	 * Whether the browser is only preloading this page (Chrome and Edge prerender a dashboard
	 * address while it is typed, Firefox and Safari prefetch). Owner, 2026-11-22: the 1.4.0 update
	 * package was applied and its content imported in about four seconds, yet no message was seen:
	 * a load the owner never looked at had used up the one-time notices. Such a load changes nothing
	 * now and leaves the notices for the real visit.
	 */
	public static function speculative(): bool {
		foreach ( [ 'HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ' ] as $header ) {
			$value = isset( $_SERVER[ $header ] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) : '';
			if ( '' !== $value && ( str_contains( $value, 'prefetch' ) || str_contains( $value, 'prerender' ) || str_contains( $value, 'preview' ) ) ) {
				return true;
			}
		}
		return false;
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
	 * admin_init: applies a waiting update package (Bundle, which may send the browser to the same
	 * page and stop here), then scans for a shop manager on a normal dashboard page.
	 */
	public static function maybe_scan(): void {
		if ( wp_doing_ajax() || self::speculative() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		Bundle::maybe_apply();
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
		// Update packages wait for an administrator's dashboard page load (Bundle).
		$packages = array_values( array_filter( $packages, static fn( string $p ): bool => ! Bundle::is_bundle( $p ) ) );
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

		foreach ( array_reverse( $run ) as $result ) {
			self::remember( $result );
		}
		return $run;
	}

	/**
	 * Keeps a package result for the dashboard notice (newest first, 20 kept).
	 */
	public static function remember( array $result ): void {
		$all = array_merge( [ $result ], (array) get_option( self::RESULTS, [] ) );
		update_option( self::RESULTS, array_slice( $all, 0, 20 ), false );
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
		$media    = is_array( $data ) && is_array( $data['media'] ?? null ) ? $data['media'] : [];
		$posts    = is_array( $data ) && is_array( $data['posts'] ?? null ) ? $data['posts'] : [];
		$terms    = is_array( $data ) && is_array( $data['terms'] ?? null ) ? $data['terms'] : [];
		if ( ! is_array( $data ) || (int) ( $data['version'] ?? 0 ) !== self::VERSION || ( ! $products && ! $pages && ! $media && ! $posts && ! $terms ) ) {
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
			foreach ( $media as $m ) {
				$item = is_array( $m ) ? self::restore_media( $m, $read ) : [ 'error' => 'invalid entry' ];
				self::collect( $result, $item, is_array( $m ) ? (string) ( $m['file'] ?? '' ) : '' );
			}
			// Pages before terms: a category text may link to a guide that arrives in the same package.
			foreach ( $posts as $p ) {
				$item = is_array( $p ) ? self::import_post( $p, $read, $name ) : [ 'error' => 'invalid entry' ];
				self::collect( $result, $item, is_array( $p ) ? rawurldecode( (string) ( $p['path'] ?? '' ) ) : '' );
			}
			foreach ( $terms as $t ) {
				$item = is_array( $t ) ? self::import_term( $t, $name ) : [ 'error' => 'invalid entry' ];
				self::collect( $result, $item, is_array( $t ) ? rawurldecode( (string) ( $t['slug'] ?? '' ) ) : '' );
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
		// What the client's text says about a copy or a warranty is what the buyer sees (owner,
		// 2026-11-25). No flag means original. The product's own guarantee text replaces the store-wide
		// one; the text a copy had goes when the product turns original.
		$was_copy  = ! $created && ProductExtras::COPY === $product->get_meta( ProductExtras::META_AUTHENTICITY );
		$is_copy   = ProductExtras::COPY === ( $p['authenticity'] ?? '' );
		$guarantee = array_key_exists( 'guarantee', $fa ) ? ProductExtras::sanitize_guarantee( $fa['guarantee'] ) : null;
		if ( $is_copy ) {
			$product->update_meta_data( ProductExtras::META_AUTHENTICITY, ProductExtras::COPY );
		} else {
			$product->delete_meta_data( ProductExtras::META_AUTHENTICITY );
		}
		if ( null !== $guarantee && '' !== $guarantee ) {
			$product->update_meta_data( ProductExtras::META_GUARANTEE, $guarantee );
		} elseif ( '' === $guarantee || ( $was_copy && ! $is_copy ) ) {
			$product->delete_meta_data( ProductExtras::META_GUARANTEE );
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

		if ( $was_copy && ! $is_copy ) {
			foreach ( Languages::secondary_codes() as $lang ) {
				delete_post_meta( $id, ProductExtras::META_GUARANTEE . '_' . $lang );
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
	 * Creates a page at its path, or replaces the texts of an existing one.
	 *
	 * @param array    $p       Manifest entry: path (after the domain, Persian as written), id (the
	 *                          ID this site has at that path, only for an existing page), fa { title,
	 *                          content, excerpt, seo_title, seo_desc }, translations (lang => fields,
	 *                          as for products), template, menu_order, image (as for pages[]),
	 *                          replace.
	 * @param callable $read    Package reader.
	 * @param string   $package Package name.
	 */
	public static function import_post( array $p, callable $read, string $package ): array {
		$path = trim( rawurldecode( (string) ( $p['path'] ?? '' ) ), '/' );
		$fa   = is_array( $p['fa'] ?? null ) ? $p['fa'] : [];
		if ( '' === $path || empty( $fa['title'] ) || empty( $fa['content'] ) ) {
			return [ 'error' => __( 'a page needs an address, a Persian title and a text.', 'gadgetim-core' ) ];
		}
		$expected = (int) ( $p['id'] ?? 0 );
		$found    = get_page_by_path( $path, OBJECT, 'page' );
		$found    = $found instanceof WP_Post && 'trash' !== $found->post_status ? $found : null;
		$fields   = [
			'post_title'   => sanitize_text_field( (string) $fa['title'] ),
			'post_content' => wp_kses_post( (string) $fa['content'] ),
			'post_excerpt' => wp_kses_post( (string) ( $fa['excerpt'] ?? '' ) ),
		];

		if ( $found ) {
			// Never write over a page the package does not know by its ID.
			if ( $expected <= 0 || $expected !== (int) $found->ID ) {
				return [ 'error' => __( 'another page already answers at this address: nothing was changed.', 'gadgetim-core' ) ];
			}
			if ( empty( $p['replace'] ) ) {
				return self::page_result( $found, 'kept_text' );
			}
			// Keep what is replaced: the Persian texts as a revision of the page as it is now (WordPress
			// saves one only after an update), the en/ar ones in META_PREV_TRANSLATIONS.
			wp_save_post_revision( (int) $found->ID );
			self::keep_previous_translations( (int) $found->ID, array_keys( (array) ( $p['translations'] ?? [] ) ) );
			$id     = wp_update_post( wp_slash( [ 'ID' => (int) $found->ID ] + $fields ), true );
			$action = 'updated';
		} else {
			if ( $expected > 0 ) {
				return [ 'error' => __( 'the page was not found on this site.', 'gadgetim-core' ) ];
			}
			$parent = 0;
			$slash  = strrpos( $path, '/' );
			if ( false !== $slash ) {
				$up = self::find_page( substr( $path, 0, $slash ) );
				if ( ! $up ) {
					return [ 'error' => __( 'the parent page of this address was not found.', 'gadgetim-core' ) ];
				}
				$parent = (int) $up->ID;
			}
			$id     = wp_insert_post(
				wp_slash(
					[
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_name'   => sanitize_title( false === $slash ? $path : substr( $path, $slash + 1 ) ),
						'post_parent' => $parent,
						'menu_order'  => (int) ( $p['menu_order'] ?? 0 ),
						'post_author' => self::author(),
					] + $fields
				),
				true
			);
			$action = 'created';
		}
		if ( is_wp_error( $id ) || (int) $id <= 0 ) {
			return [ 'error' => __( 'WordPress did not save the page.', 'gadgetim-core' ) ];
		}
		$id = (int) $id;

		foreach ( [
			'seo_title' => ProductExtras::META_SEO_TITLE,
			'seo_desc'  => ProductExtras::META_SEO_DESC,
		] as $field => $key ) {
			if ( isset( $fa[ $field ] ) ) {
				update_post_meta( $id, $key, wp_slash( sanitize_text_field( (string) $fa[ $field ] ) ) );
			}
		}
		$template = sanitize_text_field( (string) ( $p['template'] ?? '' ) );
		if ( '' !== $template && ( 'default' === $template || isset( wp_get_theme()->get_page_templates()[ $template ] ) ) ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}
		self::apply_translations( $id, (array) ( $p['translations'] ?? [] ), 'page' );
		update_post_meta( $id, self::META_PACKAGE, $package );

		$image = is_array( $p['image'] ?? null ) ? $p['image'] : [];
		if ( ! empty( $image['file'] ) && ( 'created' === $action || ! has_post_thumbnail( $id ) ) ) {
			$aid = self::import_image( $image, $read, $id );
			if ( $aid > 0 ) {
				$lang = sanitize_key( (string) ( $image['text_lang'] ?? '' ) );
				if ( '' !== $lang ) {
					update_post_meta( $aid, Meta::META_TEXT_LANG, $lang );
				}
				update_post_meta( $aid, self::META_PACKAGE, $package );
				set_post_thumbnail( $id, $aid );
			}
		}
		clean_post_cache( $id );
		return self::page_result( get_post( $id ), $action );
	}

	/**
	 * Gives an existing product category or brand its introduction text, in every language.
	 *
	 * @param array  $t       Manifest entry: taxonomy (product_cat or the brand taxonomy), slug, id
	 *                        (expected), fa { desc }, translations { lang: { desc, name, status } },
	 *                        replace.
	 * @param string $package Package name.
	 */
	public static function import_term( array $t, string $package ): array {
		$taxonomy = sanitize_key( (string) ( $t['taxonomy'] ?? 'product_cat' ) );
		if ( ! in_array( $taxonomy, [ 'product_cat', Brands::TAXONOMY ], true ) || ! taxonomy_exists( $taxonomy ) ) {
			return [ 'error' => __( 'only product categories and brands can get a text.', 'gadgetim-core' ) ];
		}
		$slug = sanitize_title( rawurldecode( (string) ( $t['slug'] ?? '' ) ) );
		$term = '' !== $slug ? get_term_by( 'slug', $slug, $taxonomy ) : false;
		if ( ! $term instanceof WP_Term ) {
			return [ 'error' => __( 'the category was not found on this site.', 'gadgetim-core' ) ];
		}
		$expected = (int) ( $t['id'] ?? 0 );
		if ( $expected > 0 && $expected !== (int) $term->term_id ) {
			return [ 'error' => __( 'the category at this address has a different ID than expected.', 'gadgetim-core' ) ];
		}
		$fa  = is_array( $t['fa'] ?? null ) ? $t['fa'] : [];
		$new = trim( wp_kses_post( (string) ( $fa['desc'] ?? '' ) ) );
		if ( '' === $new ) {
			return [ 'error' => __( 'a category entry needs a Persian text.', 'gadgetim-core' ) ];
		}
		$link = get_term_link( $term );
		$item = [
			'id'     => (int) $term->term_id,
			'sku'    => '',
			'title'  => (string) $term->name,
			'action' => 'term',
			'url'    => is_wp_error( $link ) ? '' : (string) $link,
		];
		$old = trim( (string) $term->description );
		if ( '' !== $old && empty( $t['replace'] ) ) {
			$item['action'] = 'term_kept';
			return $item;
		}
		if ( '' !== $old && $old !== $new ) {
			update_term_meta( (int) $term->term_id, '_gtm_drop_prev_desc', wp_slash( $old ) );
		}
		// WordPress runs every term description through wp_filter_kses, which keeps links and bold
		// text but drops headings, paragraphs, lists and tables (Local, 2026-11-16: a 6,730-character
		// introduction came out as one block of plain text). The text is already wp_kses_post'ed
		// above, the same filter the archive template applies when it prints it.
		$kses  = has_filter( 'pre_term_description', 'wp_filter_kses' );
		if ( false !== $kses ) {
			remove_filter( 'pre_term_description', 'wp_filter_kses', (int) $kses );
		}
		$saved = wp_update_term( (int) $term->term_id, $taxonomy, [ 'description' => wp_slash( $new ) ] );
		if ( false !== $kses ) {
			add_filter( 'pre_term_description', 'wp_filter_kses', (int) $kses );
		}
		if ( is_wp_error( $saved ) ) {
			return [ 'error' => $saved->get_error_message() ];
		}
		foreach ( (array) ( $t['translations'] ?? [] ) as $lang => $tr ) {
			$lang = sanitize_key( (string) $lang );
			if ( ! is_array( $tr ) ) {
				continue;
			}
			foreach ( [ 'name', 'desc' ] as $field ) {
				if ( isset( $tr[ $field ] ) && '' !== trim( (string) $tr[ $field ] ) ) {
					TermTranslations::set( (int) $term->term_id, $field, $lang, (string) $tr[ $field ] );
				}
			}
			if ( 'published' === ( $tr['status'] ?? '' ) ) {
				TermTranslations::set( (int) $term->term_id, 'status', $lang, 'published' );
			}
		}
		update_term_meta( (int) $term->term_id, self::META_PACKAGE, $package );
		clean_term_cache( (int) $term->term_id, $taxonomy );
		return $item;
	}

	/**
	 * The result line of a page.
	 */
	private static function page_result( ?WP_Post $page, string $action ): array {
		if ( ! $page instanceof WP_Post ) {
			return [ 'error' => __( 'WordPress did not save the page.', 'gadgetim-core' ) ];
		}
		return [
			'id'     => (int) $page->ID,
			'sku'    => '',
			'title'  => (string) $page->post_title,
			'action' => $action,
			'url'    => (string) get_permalink( $page ),
		];
	}

	/**
	 * Author of a new page: the user running the import, else the first administrator.
	 */
	private static function author(): int {
		$user = get_current_user_id();
		if ( $user > 0 ) {
			return $user;
		}
		$admins = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			]
		);
		return $admins ? (int) $admins[0] : 0;
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
	 * Puts back the missing files of one media-library image.
	 *
	 * @param array    $m    Manifest entry: id (expected), file (uploads-relative path as the
	 *                       library stores it), files[] { path (uploads-relative), zip (entry in
	 *                       the package), sha1 }.
	 * @param callable $read Package reader.
	 */
	public static function restore_media( array $m, callable $read ): array {
		$file = ltrim( str_replace( '\\', '/', (string) ( $m['file'] ?? '' ) ), '/' );
		$aid  = '' !== $file ? self::find_attachment( (int) ( $m['id'] ?? 0 ), $file ) : 0;
		if ( $aid <= 0 ) {
			return [ 'error' => __( 'the image was not found in the media library.', 'gadgetim-core' ) ];
		}
		if ( (string) get_post_meta( $aid, '_wp_attached_file', true ) !== $file ) {
			return [ 'error' => __( 'the image in the media library has a different file name.', 'gadgetim-core' ) ];
		}
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return [ 'error' => (string) $uploads['error'] ];
		}

		$own      = self::attachment_files( $aid, $file );
		$restored = 0;
		foreach ( (array) ( $m['files'] ?? [] ) as $f ) {
			$path = is_array( $f ) ? ltrim( str_replace( '\\', '/', (string) ( $f['path'] ?? '' ) ), '/' ) : '';
			if ( ! isset( $own[ $path ] ) ) {
				continue; // Not a file of this image: never written.
			}
			$target = trailingslashit( $uploads['basedir'] ) . $path;
			if ( file_exists( $target ) ) {
				continue;
			}
			$bytes = $read( (string) ( $f['zip'] ?? '' ) );
			if ( null === $bytes || ( isset( $f['sha1'] ) && sha1( $bytes ) !== (string) $f['sha1'] )
				|| false === @getimagesizefromstring( $bytes ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- damaged bytes are reported below.
				/* translators: %s: file name */
				return [ 'error' => sprintf( __( 'the copy of %s in the package is damaged.', 'gadgetim-core' ), wp_basename( $path ) ) ];
			}
			if ( ! wp_mkdir_p( dirname( $target ) )
				|| false === @file_put_contents( $target, $bytes ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- result checked.
				/* translators: %s: file name */
				return [ 'error' => sprintf( __( '%s could not be written to the uploads folder.', 'gadgetim-core' ), wp_basename( $path ) ) ];
			}
			@chmod( $target, fileperms( dirname( $target ) ) & 0000666 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- the mode WordPress gives uploads.
			++$restored;
		}

		$owner = get_post( (int) wp_get_post_parent_id( $aid ) );
		$shown = $owner instanceof WP_Post && 'publish' === $owner->post_status;
		return [
			'id'     => $aid,
			'sku'    => '',
			'title'  => $shown ? get_the_title( $owner ) : get_the_title( $aid ),
			'action' => $restored > 0 ? 'restored' : 'present',
			'url'    => $shown ? (string) get_permalink( $owner ) : (string) wp_get_attachment_url( $aid ),
			'files'  => $restored,
		];
	}

	/**
	 * The image whose stored file is $file: the expected ID when it matches, else the one this
	 * site has for that path; 0 when there is none.
	 */
	private static function find_attachment( int $expected, string $file ): int {
		if ( $expected > 0 && 'attachment' === get_post_type( $expected )
			&& (string) get_post_meta( $expected, '_wp_attached_file', true ) === $file ) {
			return $expected;
		}
		return (int) attachment_url_to_postid( trailingslashit( wp_get_upload_dir()['baseurl'] ) . $file );
	}

	/**
	 * Uploads-relative paths of an image's own files (the map's keys): the stored file, its
	 * sizes and the original of a scaled image.
	 *
	 * @return array<string,true>
	 */
	private static function attachment_files( int $aid, string $file ): array {
		$dir   = dirname( $file );
		$dir   = '.' === $dir ? '' : $dir . '/';
		$names = [ wp_basename( $file ) ];
		$meta  = wp_get_attachment_metadata( $aid );
		if ( is_array( $meta ) ) {
			foreach ( (array) ( $meta['sizes'] ?? [] ) as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) ) {
					$names[] = wp_basename( (string) $size['file'] );
				}
			}
			if ( ! empty( $meta['original_image'] ) ) {
				$names[] = wp_basename( (string) $meta['original_image'] );
			}
		}
		$own = [];
		foreach ( $names as $name ) {
			$ext = strtolower( (string) substr( (string) strrchr( $name, '.' ), 1 ) );
			if ( in_array( $ext, self::IMAGE_TYPES, true ) ) {
				$own[ $dir . $name ] = true;
			}
		}
		return $own;
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
	 * Copies the en/ar texts of a page about to be replaced into META_PREV_TRANSLATIONS, the way a
	 * replaced term text goes to _gtm_drop_prev_desc (revisions hold only the Persian texts).
	 *
	 * @param string[] $langs Languages the package writes.
	 */
	private static function keep_previous_translations( int $id, array $langs ): void {
		$prev = [];
		foreach ( $langs as $lang ) {
			$lang = sanitize_key( (string) $lang );
			foreach ( self::TRANSLATED as $field ) {
				$value = PostTranslations::raw( $id, $field, $lang );
				if ( '' !== $value ) {
					$prev[ $lang ][ $field ] = $value;
				}
			}
		}
		if ( $prev ) {
			update_post_meta( $id, self::META_PREV_TRANSLATIONS, wp_slash( (string) wp_json_encode( $prev, JSON_UNESCAPED_UNICODE ) ) );
		}
	}

	/**
	 * en/ar texts, slugs and specs; a language with a title is published.
	 *
	 * @param array  $translations lang => fields.
	 * @param string $type         Post type, for slug conflicts (specs are product only).
	 */
	private static function apply_translations( int $id, array $translations, string $type = 'product' ): void {
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
				$slug = Slugs::validate( (string) $t['slug'], $lang, $type, $id );
				if ( '' !== $slug ) {
					PostTranslations::set( $id, 'slug', $lang, $slug );
				}
			}
			if ( 'product' === $type && isset( $t['specs'] ) ) {
				// update_post_meta() unslashes its value: unprotected, a quote inside a row (\") broke
				// the JSON and the whole English table of a product was saved empty (2026-11-15).
				update_post_meta( $id, ProductExtras::META_SPECS . '_' . $lang, wp_slash( ProductExtras::sanitize_specs( wp_json_encode( $t['specs'] ) ) ) );
			}
			if ( 'product' === $type && isset( $t['guarantee'] ) ) {
				$guarantee = ProductExtras::sanitize_guarantee( $t['guarantee'] );
				if ( '' !== $guarantee ) {
					update_post_meta( $id, ProductExtras::META_GUARANTEE . '_' . $lang, wp_slash( $guarantee ) );
				} else {
					delete_post_meta( $id, ProductExtras::META_GUARANTEE . '_' . $lang );
				}
			}
			PostTranslations::set( $id, 'status', $lang, 'published' );
		}
	}

	/**
	 * Moves a processed package into done/ or failed/ (never imported twice).
	 *
	 * @return string Where it went ('' when it could not be moved and was deleted).
	 */
	public static function archive( string $path, bool $is_zip, string $to ): string {
		$dir = self::dir() . '/' . $to;
		wp_mkdir_p( $dir );
		$src  = $is_zip ? $path : dirname( $path );
		$dest = $dir . '/' . gmdate( 'Ymd-His' ) . '-' . basename( $src );
		if ( @rename( $src, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- result checked.
			return $dest;
		}
		if ( $is_zip ) {
			@unlink( $src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- never import twice.
		}
		return '';
	}

	/**
	 * Appends the run to gadgetim-drop/log.txt.
	 */
	public static function log( array $result ): void {
		$lines = [ gmdate( 'c' ) . ' ' . $result['package'] ];
		foreach ( $result['items'] as $i ) {
			if ( 'update' === ( $result['kind'] ?? '' ) ) {
				$lines[] = '  ' . $i['action'] . ' ' . $i['title']
					. ( isset( $i['to'] ) ? ' ' . $i['from'] . ' -> ' . $i['to'] : '' );
				continue;
			}
			$sku     = (string) ( $i['sku'] ?? '' );
			$lines[] = '  ' . $i['action'] . ' #' . $i['id'] . ( '' !== $sku ? ' ' . $sku : '' )
				. ( isset( $i['files'] ) ? ' files=' . (int) $i['files'] : '' ) . ' ' . $i['url'];
		}
		foreach ( $result['errors'] as $e ) {
			$lines[] = '  ERROR ' . $e;
		}
		@file_put_contents( self::dir() . '/log.txt', implode( "\n", $lines ) . "\n", FILE_APPEND ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- best effort log.
	}

	/**
	 * What happened to an item, for the notice.
	 *
	 * @param array $i Result item.
	 */
	private static function action_label( array $i ): string {
		return match ( (string) ( $i['action'] ?? '' ) ) {
			'created'      => __( 'added', 'gadgetim-core' ),
			'image'        => __( 'featured image added', 'gadgetim-core' ),
			'kept'         => __( 'kept: the page already has a featured image', 'gadgetim-core' ),
			/* translators: %d: number of files */
			'restored'     => sprintf( __( 'image files put back: %d', 'gadgetim-core' ), (int) ( $i['files'] ?? 0 ) ),
			'present'      => __( 'nothing to put back: the files are in place', 'gadgetim-core' ),
			'kept_text'    => __( 'kept: the page exists and the package does not replace it', 'gadgetim-core' ),
			'term'         => __( 'introduction text added', 'gadgetim-core' ),
			'term_kept'    => __( 'kept: the category already has a text', 'gadgetim-core' ),
			/* translators: 1: old version, 2: new version */
			'code_updated' => sprintf( __( 'updated from %1$s to %2$s', 'gadgetim-core' ), (string) ( $i['from'] ?? '' ), (string) ( $i['to'] ?? '' ) ),
			/* translators: %s: version number */
			'code_same'    => sprintf( __( 'already version %s', 'gadgetim-core' ), (string) ( $i['to'] ?? '' ) ),
			'queued'       => __( 'content package: imported right after this', 'gadgetim-core' ),
			default        => __( 'updated', 'gadgetim-core' ),
		};
	}

	/**
	 * admin_notices: results not shown yet (oldest first, so an update package comes before the
	 * content it brought), with links to the products and pages.
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$waiting = self::waiting();
		if ( $waiting ) {
			echo '<div class="notice notice-info"><p>' . esc_html( self::waiting_text( $waiting ) ) . '</p></div>';
		}

		$all     = (array) get_option( self::RESULTS, [] );
		$changed = false;
		foreach ( array_reverse( $all, true ) as $k => $r ) {
			if ( ! is_array( $r ) || ! empty( $r['seen'] ) ) {
				continue;
			}
			echo '<div class="notice ' . esc_attr( 'notice-' . self::state( $r ) ) . ' is-dismissible"><p><strong>';
			echo esc_html( self::heading( $r ) );
			echo '</strong></p>';
			self::result_list( $r, PHP_INT_MAX );
			echo '</div>';
			$all[ $k ]['seen'] = true;
			$changed           = true;
		}
		// A preloaded page, or the dashboard an update package loads to check itself, is never looked
		// at: the notices wait for the real visit.
		if ( $changed && ! self::speculative() && ! isset( $_GET['wp_scrape_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only tells the update's own check apart.
			update_option( self::RESULTS, $all, false );
		}
	}

	/**
	 * wp_dashboard_setup: the «Gadgetim packages» box on the dashboard home, at the top. It keeps
	 * the last results whether or not their one-time notice was seen.
	 */
	public static function dashboard_widget(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'gadgetim_drop', __( 'Gadgetim packages', 'gadgetim-core' ), [ self::class, 'render_widget' ], null, null, 'normal', 'high' );
	}

	/**
	 * The dashboard box: what waits in the drop folder, then the last five results, newest first.
	 */
	public static function render_widget(): void {
		$waiting = self::waiting();
		if ( $waiting ) {
			echo '<p><strong>' . esc_html( self::waiting_text( $waiting ) ) . '</strong></p>';
		}
		$all = array_slice( array_values( array_filter( (array) get_option( self::RESULTS, [] ), 'is_array' ) ), 0, 5 );
		if ( ! $all ) {
			echo '<p>' . esc_html__( 'No package has been processed yet. Put an update package into wp-content/gadgetim-drop/ and open the dashboard.', 'gadgetim-core' ) . '</p>';
			return;
		}
		$colors = [
			'success' => '#00a32a',
			'warning' => '#dba617',
			'error'   => '#d63638',
		];
		echo '<ul style="margin:0">';
		foreach ( $all as $r ) {
			$state = self::state( $r );
			echo '<li style="margin:0 0 14px;padding:0 0 10px;border-bottom:1px solid #f0f0f1">';
			echo '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-inline-end:6px;background:' . esc_attr( $colors[ $state ] ) . '"></span>';
			echo '<strong>' . esc_html( self::heading( $r ) ) . '</strong>';
			echo '<br><span style="color:#646970">' . esc_html( self::when( (int) ( $r['time'] ?? 0 ) ) ) . ' — ' . esc_html( self::state_label( $state ) ) . '</span>';
			self::result_list( $r, 4 );
			echo '</li>';
		}
		echo '</ul>';
		echo '<p style="color:#646970;margin-bottom:0">' . esc_html__( 'Every line is also kept in wp-content/gadgetim-drop/log.txt.', 'gadgetim-core' ) . '</p>';
	}

	/**
	 * success, warning (done with errors) or error (nothing done).
	 */
	private static function state( array $r ): string {
		return empty( $r['errors'] ) ? 'success' : ( empty( $r['items'] ) ? 'error' : 'warning' );
	}

	/**
	 * The state in words.
	 */
	private static function state_label( string $state ): string {
		return match ( $state ) {
			'success' => __( 'done', 'gadgetim-core' ),
			'warning' => __( 'done, with the warnings below', 'gadgetim-core' ),
			default   => __( 'not applied', 'gadgetim-core' ),
		};
	}

	/**
	 * First line of a result.
	 */
	private static function heading( array $r ): string {
		return 'update' === ( $r['kind'] ?? '' )
			/* translators: %s: update package file name */
			? sprintf( __( 'Update package %s was applied:', 'gadgetim-core' ), (string) ( $r['package'] ?? '' ) )
			/* translators: %s: package file name */
			: sprintf( __( 'Package %s was imported:', 'gadgetim-core' ), (string) ( $r['package'] ?? '' ) );
	}

	/**
	 * The item lines of a result (the first $show, the rest folded) and all its errors.
	 */
	private static function result_list( array $r, int $show ): void {
		$lines = [];
		foreach ( (array) ( $r['items'] ?? [] ) as $i ) {
			$i     = (array) $i;
			$title = esc_html( (string) ( $i['title'] ?? '' ) );
			if ( '' !== (string) ( $i['url'] ?? '' ) ) {
				$title = '<a href="' . esc_url( (string) $i['url'] ) . '" target="_blank">' . $title . '</a>';
			}
			$lines[] = '<li>' . $title . ' (' . esc_html( self::action_label( $i ) ) . ')</li>';
		}
		$ul = '<ul style="list-style:disc;padding-inline-start:20px;margin:6px 0 0">';
		echo $ul . implode( '', array_slice( $lines, 0, $show ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
		foreach ( (array) ( $r['errors'] ?? [] ) as $e ) {
			echo '<li style="color:#b32d2e">' . esc_html( (string) $e ) . '</li>';
		}
		echo '</ul>';
		if ( count( $lines ) > $show ) {
			/* translators: %d: number of further lines */
			$more = sprintf( __( '%d more', 'gadgetim-core' ), count( $lines ) - $show );
			if ( str_starts_with( get_user_locale(), 'fa' ) && class_exists( Digits::class ) ) {
				$more = Digits::for_lang( $more, 'fa', false );
			}
			echo '<details style="margin-top:4px"><summary style="cursor:pointer">' . esc_html( $more ) . '</summary>';
			echo $ul . implode( '', array_slice( $lines, $show ) ) . '</ul></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
		}
	}

	/**
	 * Date and time of a result: Jalali for a Persian dashboard, the site's format otherwise.
	 */
	private static function when( int $time ): string {
		if ( $time <= 0 ) {
			return '';
		}
		if ( str_starts_with( get_user_locale(), 'fa' ) && class_exists( Jalali::class ) ) {
			return Jalali::format( $time, 'j F Y، H:i', 'fa' );
		}
		return (string) wp_date( 'Y-m-d H:i', $time );
	}

	/**
	 * Packages waiting in the drop folder (zip names and extracted folders).
	 *
	 * @return string[]
	 */
	private static function waiting(): array {
		$dir = self::dir();
		return array_map(
			'basename',
			array_merge( glob( $dir . '/*.zip' ) ?: [], array_map( 'dirname', glob( $dir . '/*/manifest.json' ) ?: [] ) )
		);
	}

	/**
	 * What the waiting packages are doing, for the notice and the dashboard box.
	 *
	 * @param string[] $waiting Package names.
	 */
	private static function waiting_text( array $waiting ): string {
		$names = implode( '، ', $waiting );
		if ( get_transient( self::LOCK ) ) {
			/* translators: %s: package file names */
			return sprintf( __( 'Being applied right now: %s. Refresh this page in a few seconds.', 'gadgetim-core' ), $names );
		}
		/* translators: %s: package file names */
		return sprintf( __( 'Waiting in the drop folder: %s. An update package is applied when an administrator opens the dashboard; content packages also within the hour.', 'gadgetim-core' ), $names );
	}
}
