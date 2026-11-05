<?php
/**
 * Import job state: options, step cursor, counters. Serialisable so the admin batch runner
 * can continue it across AJAX requests (transient gadgetim_import_job_{id}).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

/**
 * A plain data holder; Importer::run() advances it.
 */
final class Job {

	public const TRANSIENT_PREFIX = 'gadgetim_import_job_';

	public const ALL_STEPS = [ 'terms', 'products', 'images', 'pages', 'redirects' ];

	public string $id = '';

	public string $source = '';

	/** @var string[] Enabled steps, in run order. */
	public array $steps = [];

	public bool $dry_run = false;

	public int $limit = 0;

	public int $author = 0;

	public bool $apply_redirects = false;

	public string $output_dir = '';

	public int $step_index = 0;

	public int $position = 0;

	/** @var array<string,int> Item counts per step, filled when a step starts. */
	public array $totals = [];

	/** @var array<string,int> */
	public array $counts = [
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'failed'  => 0,
		'images'  => 0,
	];

	/** @var array<string,mixed> Free-form state shared between steps (e.g. redirect row count). */
	public array $meta = [];

	public bool $finished = false;

	public int $started = 0;

	public int $finished_at = 0;

	/**
	 * Builds a job from options: source, steps (array or comma list), dry_run, limit, author,
	 * apply_redirects, output_dir. No steps → every step.
	 */
	public static function create( array $options ): self {
		$job         = new self();
		$job->id     = substr( md5( wp_generate_uuid4() . microtime( true ) ), 0, 12 );
		$job->source = Paths::normalize( (string) ( $options['source'] ?? Paths::default_source_dir() ) );

		$steps = $options['steps'] ?? [];
		if ( is_string( $steps ) ) {
			$steps = array_map( 'trim', explode( ',', $steps ) );
		}
		$steps = array_values( array_intersect( self::ALL_STEPS, array_map( 'strval', (array) $steps ) ) );
		if ( ! $steps ) {
			$steps = self::ALL_STEPS;
		} elseif ( ! in_array( 'redirects', $steps, true ) && ( in_array( 'terms', $steps, true ) || in_array( 'pages', $steps, true ) ) && ! empty( $options['auto_redirects'] ) ) {
			$steps[] = 'redirects';
		}
		$job->steps = $steps;

		$job->dry_run         = ! empty( $options['dry_run'] );
		$job->limit           = max( 0, (int) ( $options['limit'] ?? 0 ) );
		$job->author          = max( 0, (int) ( $options['author'] ?? 0 ) );
		$job->apply_redirects = ! empty( $options['apply_redirects'] );
		$job->output_dir      = Paths::normalize( (string) ( $options['output_dir'] ?? '' ) );
		$job->started         = time();
		return $job;
	}

	/**
	 * Name of the step the cursor is on, or null when every step ran.
	 */
	public function current_step(): ?string {
		return $this->steps[ $this->step_index ] ?? null;
	}

	/**
	 * Whether a step is part of this job.
	 */
	public function has_step( string $name ): bool {
		return in_array( $name, $this->steps, true );
	}

	/**
	 * Moves the cursor to the next step.
	 */
	public function advance(): void {
		++$this->step_index;
		$this->position = 0;
		if ( null === $this->current_step() ) {
			$this->finished    = true;
			$this->finished_at = time();
		}
	}

	/**
	 * Total items across the steps counted so far.
	 */
	public function total(): int {
		return (int) array_sum( $this->totals );
	}

	/**
	 * Items done across all steps (completed steps count fully).
	 */
	public function done(): int {
		$done = 0;
		foreach ( $this->steps as $index => $name ) {
			if ( $index < $this->step_index ) {
				$done += (int) ( $this->totals[ $name ] ?? 0 );
			} elseif ( $index === $this->step_index ) {
				$done += $this->position;
			}
		}
		return $done;
	}

	/**
	 * Array form for the transient.
	 */
	public function to_array(): array {
		return get_object_vars( $this );
	}

	/**
	 * Rebuilds a job from its array form.
	 */
	public static function from_array( array $data ): self {
		$job = new self();
		foreach ( $data as $key => $value ) {
			if ( property_exists( $job, (string) $key ) ) {
				$job->{$key} = $value;
			}
		}
		return $job;
	}

	/**
	 * Persists the job for the batch runner.
	 */
	public function save(): void {
		if ( '' !== $this->id ) {
			set_transient( self::TRANSIENT_PREFIX . $this->id, $this->to_array(), DAY_IN_SECONDS );
		}
	}

	/**
	 * Loads a persisted job, or null.
	 */
	public static function load( string $id ): ?self {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return null;
		}
		$data = get_transient( self::TRANSIENT_PREFIX . $id );
		return is_array( $data ) ? self::from_array( $data ) : null;
	}

	/**
	 * Deletes a persisted job.
	 */
	public function delete(): void {
		if ( '' !== $this->id ) {
			delete_transient( self::TRANSIENT_PREFIX . $this->id );
		}
	}

	/**
	 * One-line summary of the counters.
	 */
	public function summary(): string {
		return sprintf(
			/* translators: 1: created, 2: updated, 3: skipped, 4: failed, 5: images */
			__( 'created %1$d · updated %2$d · skipped %3$d · failed %4$d · images %5$d', 'gadgetim-core' ),
			$this->counts['created'],
			$this->counts['updated'],
			$this->counts['skipped'],
			$this->counts['failed'],
			$this->counts['images']
		);
	}
}
