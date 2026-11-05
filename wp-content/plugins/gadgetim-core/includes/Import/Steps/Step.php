<?php
/**
 * Contract of one import step (terms, products, images, pages, redirects).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Steps;

use Gadgetim\Core\Import\Importer;

/**
 * Steps are resumable: the importer stores only the item index between batches, so
 * count() must be stable for a given source and item() must be idempotent.
 */
abstract class Step {

	protected Importer $importer;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Step name as used in Job::$steps.
	 */
	abstract public function name(): string;

	/**
	 * Number of items this step processes (stable for the same source and job options).
	 */
	abstract public function count(): int;

	/**
	 * Processes item $index (0-based). Throw to mark the item failed; the importer logs it.
	 */
	abstract public function item( int $index ): void;

	/**
	 * Runs once before the first item of this step (in the request that starts it).
	 */
	public function before(): void {}

	/**
	 * Runs once after the last item of this step.
	 */
	public function after(): void {}

	/**
	 * Shorthand: logs a line for this step.
	 */
	protected function log( string $level, string $message ): void {
		$this->importer->log()->add( $level, $message, $this->name() );
	}
}
