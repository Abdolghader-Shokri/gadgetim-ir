<?php
/**
 * Step 3: images for products that exist already (featured + gallery, content URL rewrite).
 * When the products step runs in the same job the images are imported there and this step is empty.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Steps;

use Gadgetim\Core\Catalog\TitleCleaner;
use Gadgetim\Core\Import\Log;

/**
 * One item per exported product (limit honoured).
 */
final class ImagesStep extends Step {

	public function name(): string {
		return 'images';
	}

	public function count(): int {
		if ( $this->importer->job()->has_step( 'products' ) ) {
			return 0;
		}
		$total = count( $this->importer->source()->products() );
		$limit = $this->importer->job()->limit;
		return $limit > 0 ? min( $limit, $total ) : $total;
	}

	public function before(): void {
		if ( $this->importer->job()->has_step( 'products' ) ) {
			$this->log( Log::INFO, __( 'Images are imported together with the products in this run.', 'gadgetim-core' ) );
		}
	}

	public function item( int $index ): void {
		$p = $this->importer->source()->products()[ $index ] ?? null;
		if ( null === $p ) {
			return;
		}
		$old_id = (int) ( $p['id'] ?? 0 );
		$title  = (string) ( $p['title'] ?? '' );
		$short  = TitleCleaner::short( $title );
		$slug   = rawurldecode( (string) ( $p['slug'] ?? '' ) );
		$count  = ( ! empty( $p['image']['file'] ) ? 1 : 0 ) + count( (array) ( $p['gallery'] ?? [] ) );

		$product_id = $this->importer->products()->find_existing( $old_id, $slug );
		if ( $this->importer->dry() ) {
			$this->importer->job()->counts['images'] += $count;
			$this->log(
				Log::INFO,
				$product_id > 0
					/* translators: 1: short title, 2: image count */
					? sprintf( __( '[dry run] would import %2$d images for "%1$s".', 'gadgetim-core' ), $short, $count )
					/* translators: %s: short title */
					: sprintf( __( '[dry run] "%s" is not imported yet; its images would be skipped.', 'gadgetim-core' ), $short )
			);
			return;
		}
		if ( $product_id <= 0 ) {
			++$this->importer->job()->counts['skipped'];
			/* translators: %s: short title */
			$this->log( Log::WARNING, sprintf( __( '"%s" is not imported yet; run the products step first.', 'gadgetim-core' ), $short ) );
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		$brands = (array) ( $p['brands'] ?? [] );
		$alt    = trim( $short . ( $brands ? ' ' . $this->importer->terms()->brand_display_name( (string) $brands[0] ) : '' ) );
		$images = $this->importer->products()->import_images( $p, $product_id, $alt, (string) ( $p['date'] ?? '' ) );

		$changed = false;
		if ( $images['image'] > 0 && (string) $product->get_image_id( 'edit' ) !== (string) $images['image'] ) {
			$product->set_image_id( $images['image'] );
			$changed = true;
		}
		if ( $images['gallery'] && array_map( 'intval', (array) $product->get_gallery_image_ids( 'edit' ) ) !== $images['gallery'] ) {
			$product->set_gallery_image_ids( $images['gallery'] );
			$changed = true;
		}
		$description = (string) $product->get_description( 'edit' );
		$rewritten   = $this->importer->images()->rewrite_content( $description );
		if ( $rewritten !== $description ) {
			$product->set_description( $rewritten );
			$changed = true;
		}
		if ( $changed ) {
			$product->save();
			++$this->importer->job()->counts['updated'];
		} else {
			++$this->importer->job()->counts['skipped'];
		}
	}
}
