<?php
/**
 * Step 1: categories, brands, tags.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Steps;

/**
 * Items: every exported category (parents first), every canonical brand group, every used tag.
 */
final class TermsStep extends Step {

	/** @var array<int,array{type:string,data:array}>|null */
	private ?array $items = null;

	public function name(): string {
		return 'terms';
	}

	public function count(): int {
		return count( $this->items() );
	}

	public function item( int $index ): void {
		$entry = $this->items()[ $index ] ?? null;
		if ( null === $entry ) {
			return;
		}
		$terms = $this->importer->terms();
		switch ( $entry['type'] ) {
			case 'category':
				$terms->import_category( $entry['data'] );
				break;
			case 'brand':
				$terms->import_brand_group( $entry['data'] );
				break;
			case 'tag':
				$terms->import_tag( $entry['data'] );
				break;
		}
	}

	/**
	 * The ordered work list.
	 *
	 * @return array<int,array{type:string,data:array}>
	 */
	private function items(): array {
		if ( null !== $this->items ) {
			return $this->items;
		}
		$items = [];
		foreach ( $this->importer->terms()->ordered_categories() as $category ) {
			$items[] = [
				'type' => 'category',
				'data' => $category,
			];
		}
		foreach ( $this->importer->terms()->brand_groups() as $group ) {
			$items[] = [
				'type' => 'brand',
				'data' => $group,
			];
		}
		foreach ( $this->importer->terms()->used_tags() as $tag ) {
			$items[] = [
				'type' => 'tag',
				'data' => $tag,
			];
		}
		$this->items = $items;
		return $items;
	}
}
