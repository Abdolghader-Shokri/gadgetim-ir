<?php
/**
 * Step 2: products (with their images when the images step is part of the job).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Steps;

/**
 * Honours --limit (first N products in export order).
 */
final class ProductsStep extends Step {

	public function name(): string {
		return 'products';
	}

	public function count(): int {
		$total = count( $this->importer->source()->products() );
		$limit = $this->importer->job()->limit;
		return $limit > 0 ? min( $limit, $total ) : $total;
	}

	public function item( int $index ): void {
		$product = $this->importer->source()->products()[ $index ] ?? null;
		if ( null === $product ) {
			return;
		}
		$this->importer->products()->import( $product );
	}
}
