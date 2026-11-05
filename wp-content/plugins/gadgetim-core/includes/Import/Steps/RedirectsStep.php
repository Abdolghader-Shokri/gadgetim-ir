<?php
/**
 * Step 5: data/redirects.csv (and, on request, the gadgetim_redirects option).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Steps;

use Gadgetim\Core\Import\Log;
use Gadgetim\Core\Import\Paths;
use Gadgetim\Core\Import\Redirects;

/**
 * A single item.
 */
final class RedirectsStep extends Step {

	public function name(): string {
		return 'redirects';
	}

	public function count(): int {
		return 1;
	}

	public function item( int $index ): void {
		$job  = $this->importer->job();
		$rows = $this->importer->redirects()->rows();
		$dir  = '' !== $job->output_dir ? $job->output_dir : Paths::output_dir();
		$path = $dir . '/' . Redirects::FILE;

		$job->meta['redirect_rows'] = count( $rows );
		$job->meta['redirect_file'] = $path;

		if ( $this->importer->dry() ) {
			/* translators: 1: row count, 2: file path */
			$this->log( Log::INFO, sprintf( __( '[dry run] would write %1$d redirect rows to %2$s.', 'gadgetim-core' ), count( $rows ), $path ) );
			foreach ( array_slice( $rows, 0, 15 ) as $row ) {
				$this->log( Log::INFO, sprintf( '  %s → %s (%d) %s', $row['from'], '' !== $row['to'] ? $row['to'] : '—', $row['status'], $row['note'] ) );
			}
			if ( count( $rows ) > 15 ) {
				$this->log( Log::INFO, sprintf( '  … %d more', count( $rows ) - 15 ) );
			}
			return;
		}

		$written = Redirects::write_csv( $rows, $path );
		if ( false === $written ) {
			/* translators: %s: file path */
			$this->log( Log::ERROR, sprintf( __( 'Could not write %s.', 'gadgetim-core' ), $path ) );
			++$job->counts['failed'];
		} else {
			/* translators: 1: row count, 2: file path */
			$this->log( Log::SUCCESS, sprintf( __( 'Wrote %1$d redirect rows to %2$s.', 'gadgetim-core' ), $written, $path ) );
		}

		if ( $job->apply_redirects ) {
			$applied = Redirects::apply( $rows );
			/* translators: %d: row count */
			$this->log( Log::SUCCESS, sprintf( __( 'Applied %d redirects to the gadgetim_redirects option.', 'gadgetim-core' ), $applied ) );
		}
	}
}
