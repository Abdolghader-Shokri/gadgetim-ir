<?php
/**
 * Page import (architecture §6.1 step 5): privacy, terms, about, contact, FAQ with cleaned content.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Catalog\ProductExtras;
use WP_Post;

/**
 * Content comes from the export entry, <export>/pages/<slug>.html or data/pages.json
 * (Source::page_content()). The WooCommerce pages already exist and are left alone.
 */
final class Pages {

	public const META_ROLE = '_gtm_page_role';

	/** Old page id → slug and role. */
	public const MAP = [
		3    => [
			'slug' => 'حفظ-حریم-خصوصی',
			'role' => 'privacy',
		],
		3293 => [
			'slug' => 'شرایط-و-قوانین',
			'role' => 'terms',
		],
		4010 => [
			'slug' => 'درباره-ما',
			'role' => 'about',
		],
		3522 => [
			'slug' => 'ارتباط-با-ما',
			'role' => 'contact',
		],
		4017 => [
			'slug' => 'سوالات-متداول',
			'role' => 'faq',
		],
	];

	private Importer $importer;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Export entries to import, in MAP order (matched by old id, else by slug).
	 */
	public function items(): array {
		$pages = $this->importer->source()->pages();
		$items = [];
		foreach ( self::MAP as $old_id => $target ) {
			foreach ( $pages as $page ) {
				if ( 'page' !== (string) ( $page['type'] ?? 'page' ) ) {
					continue;
				}
				if ( (int) ( $page['id'] ?? 0 ) === $old_id || (string) ( $page['slug'] ?? '' ) === $target['slug'] ) {
					$page['role']  = $target['role'];
					$page['id']    = (int) ( $page['id'] ?? $old_id );
					$items[]       = $page;
					break;
				}
			}
		}
		return $items;
	}

	/**
	 * Imports one page entry.
	 */
	public function import( array $page ): void {
		$job    = $this->importer->job();
		$old_id = (int) ( $page['id'] ?? 0 );
		$slug   = rawurldecode( (string) ( $page['slug'] ?? '' ) );
		$title  = sanitize_text_field( (string) ( $page['title'] ?? '' ) );
		$role   = sanitize_key( (string) ( $page['role'] ?? '' ) );
		if ( '' === $slug || '' === $title ) {
			throw new \RuntimeException( 'Page entry without slug or title.' );
		}

		$raw = $this->importer->source()->page_content( $page );
		if ( null === $raw ) {
			/* translators: %s: page title */
			$this->log( Log::WARNING, sprintf( __( 'Page "%s": no content in the export or data/pages.json; created empty.', 'gadgetim-core' ), $title ) );
			$raw = '';
		}
		$content = $this->importer->cleaner()->page( $raw );
		if ( 'faq' === $role ) {
			$content = $this->importer->cleaner()->finalize( \Gadgetim\Core\Import\Cleaner::faq_to_details( $content ) );
		}
		$extra       = $this->importer->source()->page_extra( $page );
		$existing_id = $this->find_existing( $old_id, $slug );

		if ( $this->importer->dry() ) {
			++$job->counts[ $existing_id > 0 ? 'updated' : 'created' ];
			$this->log(
				Log::INFO,
				sprintf(
					/* translators: 1: create/update, 2: page title, 3: slug, 4: content length */
					__( '[dry run] would %1$s page "%2$s" (/%3$s/), %4$d characters of cleaned content.', 'gadgetim-core' ),
					$existing_id > 0 ? 'update' : 'create',
					$title,
					$slug,
					mb_strlen( wp_strip_all_tags( $content ) )
				)
			);
			return;
		}

		$data = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_author'  => $this->importer->author_id(),
			'meta_input'   => [
				ProductExtras::META_IMPORT_ID => $old_id,
				self::META_ROLE               => $role,
			],
		];
		$date = (string) ( $extra['date'] ?? '' );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) && $existing_id <= 0 ) {
			$data['post_date'] = $date;
		}

		if ( $existing_id > 0 ) {
			$data['ID'] = $existing_id;
			$result     = wp_update_post( wp_slash( $data ), true );
		} else {
			$result = wp_insert_post( wp_slash( $data ), true );
		}
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
		$page_id = (int) $result;
		$this->assign_role( $page_id, $role );

		++$job->counts[ $existing_id > 0 ? 'updated' : 'created' ];
		$this->log(
			Log::SUCCESS,
			sprintf(
				/* translators: 1: created/updated, 2: page id, 3: page title */
				__( '%1$s page #%2$d "%3$s".', 'gadgetim-core' ),
				$existing_id > 0 ? __( 'Updated', 'gadgetim-core' ) : __( 'Created', 'gadgetim-core' ),
				$page_id,
				$title
			)
		);
	}

	/**
	 * Existing page by old id, else by path.
	 */
	public function find_existing( int $old_id, string $slug ): int {
		if ( $old_id > 0 ) {
			$ids = get_posts(
				[
					'post_type'      => 'page',
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
		}
		$post = get_page_by_path( $slug, OBJECT, 'page' );
		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}

	/**
	 * Site-level role of a page: privacy policy page, WooCommerce terms page.
	 */
	private function assign_role( int $page_id, string $role ): void {
		if ( 'privacy' === $role ) {
			update_option( 'wp_page_for_privacy_policy', $page_id );
		} elseif ( 'terms' === $role ) {
			update_option( 'woocommerce_terms_page_id', $page_id );
		}
	}

	private function log( string $level, string $message ): void {
		$this->importer->log()->add( $level, $message, 'pages' );
	}
}
