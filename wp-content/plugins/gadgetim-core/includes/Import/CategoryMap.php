<?php
/**
 * data/category-map.json: old category id → new slug/name/parent and an action
 * (create, skip, default, merge). Identity mapping until the client prunes the tree.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

/**
 * Entry shape: { "slug": "...", "name": "...", "parent": <old id>, "action": "create|skip|default|merge",
 * "merge_into": <old id>, "note": "..." }. Missing entries behave like "create" with the export data.
 */
final class CategoryMap {

	public const FILE = 'category-map.json';

	public const ACTIONS = [ 'create', 'skip', 'default', 'merge' ];

	/** @var array<int,array>|null */
	private ?array $entries = null;

	private string $file;

	public function __construct( string $file = '' ) {
		$this->file = '' !== $file ? $file : Paths::data_file( self::FILE );
	}

	/**
	 * Mapping entry for an old category id (normalised; defaults when absent).
	 *
	 * @param int   $old_id   Old term id.
	 * @param array $exported The category as exported (fallback for slug/name/parent).
	 * @return array{slug:string,name:string,parent:int,action:string,merge_into:int,note:string}
	 */
	public function entry( int $old_id, array $exported = [] ): array {
		$entry  = $this->entries()[ $old_id ] ?? [];
		$action = strtolower( (string) ( $entry['action'] ?? 'create' ) );
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			$action = 'create';
		}
		return [
			'slug'       => (string) ( $entry['slug'] ?? $exported['slug'] ?? '' ),
			'name'       => (string) ( $entry['name'] ?? $exported['name'] ?? '' ),
			'parent'     => (int) ( $entry['parent'] ?? $exported['parent'] ?? 0 ),
			'action'     => $action,
			'merge_into' => (int) ( $entry['merge_into'] ?? 0 ),
			'note'       => (string) ( $entry['note'] ?? '' ),
		];
	}

	/**
	 * Whether the file exists and parsed.
	 */
	public function loaded(): bool {
		return [] !== $this->entries();
	}

	/**
	 * File path.
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * All entries indexed by old id.
	 *
	 * @return array<int,array>
	 */
	public function entries(): array {
		if ( null !== $this->entries ) {
			return $this->entries;
		}
		$this->entries = [];
		if ( is_readable( $this->file ) ) {
			$data = json_decode( (string) file_get_contents( $this->file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$list = is_array( $data ) ? ( $data['categories'] ?? $data ) : [];
			foreach ( (array) $list as $key => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$old_id = (int) ( $entry['old_id'] ?? $key );
				if ( $old_id > 0 ) {
					$this->entries[ $old_id ] = $entry;
				}
			}
		}
		return $this->entries;
	}
}
