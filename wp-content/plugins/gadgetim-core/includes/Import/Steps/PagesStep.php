<?php
/**
 * Step 4: the five content pages.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Steps;

/**
 * One item per mapped page (Pages::MAP).
 */
final class PagesStep extends Step {

	/** @var array|null */
	private ?array $items = null;

	public function name(): string {
		return 'pages';
	}

	public function count(): int {
		return count( $this->items() );
	}

	public function item( int $index ): void {
		$page = $this->items()[ $index ] ?? null;
		if ( null !== $page ) {
			$this->importer->pages()->import( $page );
		}
	}

	private function items(): array {
		return $this->items ??= $this->importer->pages()->items();
	}
}
