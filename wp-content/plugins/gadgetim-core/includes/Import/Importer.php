<?php
/**
 * Import orchestrator: runs the enabled steps over the export, resumable in batches,
 * with cache purging and translations suspended for the whole run (architecture §6.1).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\Support\Cache;
use Gadgetim\Core\Support\Context;
use Throwable;
use WP_Error;

/**
 * Usage: $importer = Importer::create( [ 'source' => …, 'steps' => [...], 'dry_run' => true ] );
 * $importer->run(); — or run( 20, $deadline ) repeatedly from the batch runner.
 */
final class Importer {

	private Source $source;

	private Job $job;

	private Log $log;

	/** @var array<string,Steps\Step> */
	private array $steps = [];

	private ?Terms $terms = null;

	private ?Images $images = null;

	private ?Cleaner $cleaner = null;

	private ?Products $products = null;

	private ?Pages $pages = null;

	private ?Redirects $redirects = null;

	private ?CategoryMap $category_map = null;

	private bool $began = false;

	public function __construct( Source $source, Job $job, ?Log $log = null ) {
		$this->source = $source;
		$this->job    = $job;
		$this->log    = $log ?? new Log();
	}

	/**
	 * Builds an importer from options (see Job::create()) plus an optional log sink.
	 */
	public static function create( array $options, ?callable $sink = null ): self {
		$job = Job::create( $options );
		return new self( new Source( $job->source ), $job, new Log( $sink ) );
	}

	/**
	 * Resumes a persisted job (admin batch runner).
	 */
	public static function resume( Job $job, ?callable $sink = null ): self {
		return new self( new Source( $job->source ), $job, new Log( $sink ) );
	}

	/**
	 * Validates the environment and the source. Returns true or a WP_Error.
	 */
	public function validate(): bool|WP_Error {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'gadgetim_import_no_wc', __( 'WooCommerce must be active to import products.', 'gadgetim-core' ) );
		}
		if ( ! Brands::exists() && ( $this->job->has_step( 'terms' ) || $this->job->has_step( 'products' ) ) ) {
			return new WP_Error( 'gadgetim_import_no_brands', __( 'The product_brand taxonomy is not registered (WooCommerce brands are required).', 'gadgetim-core' ) );
		}
		return $this->source->validate();
	}

	/**
	 * Runs up to $max_items items (0 = everything), stopping early at $deadline (microtime, 0 = none).
	 * Returns the job; $job->finished tells whether every step completed.
	 */
	public function run( int $max_items = 0, float $deadline = 0.0 ): Job {
		$job = $this->job;
		if ( $job->finished ) {
			return $job;
		}
		$valid = $this->validate();
		if ( is_wp_error( $valid ) ) {
			$this->log->error( $valid->get_error_message() );
			$job->finished    = true;
			$job->finished_at = time();
			return $job;
		}

		$this->begin();
		$processed = 0;

		try {
			while ( ! $job->finished ) {
				$name = $job->current_step();
				if ( null === $name ) {
					$job->advance();
					break;
				}
				$step = $this->step( $name );
				if ( ! isset( $job->totals[ $name ] ) ) {
					$job->totals[ $name ] = max( 0, $step->count() );
					$step->before();
					$this->log->info(
						/* translators: 1: step name, 2: item count */
						sprintf( __( 'Step "%1$s": %2$d items.', 'gadgetim-core' ), $name, $job->totals[ $name ] ),
						$name
					);
				}
				while ( $job->position < $job->totals[ $name ] ) {
					if ( ( $max_items > 0 && $processed >= $max_items ) || ( $deadline > 0 && microtime( true ) >= $deadline ) ) {
						$this->end( false );
						return $job;
					}
					try {
						$step->item( $job->position );
					} catch ( Throwable $e ) {
						++$job->counts['failed'];
						$this->log->error(
							/* translators: 1: item index, 2: error message */
							sprintf( __( 'Item %1$d failed: %2$s', 'gadgetim-core' ), $job->position + 1, $e->getMessage() ),
							$name
						);
					}
					++$job->position;
					++$processed;
				}
				$step->after();
				$job->advance();
			}
		} finally {
			$this->end( $job->finished );
		}
		return $job;
	}

	/**
	 * Runs everything in one go (CLI).
	 */
	public function run_all(): Job {
		while ( ! $this->job->finished ) {
			$this->run( 0 );
		}
		return $this->job;
	}

	/**
	 * Step instance by name.
	 */
	public function step( string $name ): Steps\Step {
		if ( ! isset( $this->steps[ $name ] ) ) {
			$this->steps[ $name ] = match ( $name ) {
				'terms'     => new Steps\TermsStep( $this ),
				'products'  => new Steps\ProductsStep( $this ),
				'images'    => new Steps\ImagesStep( $this ),
				'pages'     => new Steps\PagesStep( $this ),
				'redirects' => new Steps\RedirectsStep( $this ),
				default     => throw new \InvalidArgumentException( 'Unknown import step: ' . $name ),
			};
		}
		return $this->steps[ $name ];
	}

	/**
	 * The export.
	 */
	public function source(): Source {
		return $this->source;
	}

	/**
	 * The job state.
	 */
	public function job(): Job {
		return $this->job;
	}

	/**
	 * The log.
	 */
	public function log(): Log {
		return $this->log;
	}

	/**
	 * Whether this is a dry run (no writes at all).
	 */
	public function dry(): bool {
		return $this->job->dry_run;
	}

	/**
	 * Term resolver/creator.
	 */
	public function terms(): Terms {
		return $this->terms ??= new Terms( $this );
	}

	/**
	 * Image sideloader.
	 */
	public function images(): Images {
		return $this->images ??= new Images( $this );
	}

	/**
	 * HTML cleaner (with the image URL rewriter wired in).
	 */
	public function cleaner(): Cleaner {
		return $this->cleaner ??= new Cleaner( fn( string $url ): string => $this->images()->rewrite_url( $url ) );
	}

	/**
	 * Product importer.
	 */
	public function products(): Products {
		return $this->products ??= new Products( $this );
	}

	/**
	 * Page importer.
	 */
	public function pages(): Pages {
		return $this->pages ??= new Pages( $this );
	}

	/**
	 * Redirect builder.
	 */
	public function redirects(): Redirects {
		return $this->redirects ??= new Redirects( $this );
	}

	/**
	 * Category mapping file.
	 */
	public function category_map(): CategoryMap {
		return $this->category_map ??= new CategoryMap();
	}

	/**
	 * Author for created posts (job option, else the first administrator, else 0).
	 */
	public function author_id(): int {
		if ( $this->job->author > 0 ) {
			return $this->job->author;
		}
		$current = get_current_user_id();
		if ( $current > 0 ) {
			return $current;
		}
		$admins = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			]
		);
		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * Suspends cache purges and translations, defers term counting (once per request).
	 */
	private function begin(): void {
		if ( $this->began ) {
			return;
		}
		$this->began = true;
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}
		Cache::suspend();
		++Context::$suspend_translations;
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( ! $this->dry() ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
		}
	}

	/**
	 * Ends the request's share of the run; after the final step: product transients, one purge-all.
	 */
	private function end( bool $completed ): void {
		if ( ! $this->began ) {
			return;
		}
		$this->began = false;
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		Context::$suspend_translations = max( 0, Context::$suspend_translations - 1 );
		Cache::resume();

		if ( $completed ) {
			if ( ! $this->dry() ) {
				if ( function_exists( 'wc_delete_product_transients' ) ) {
					wc_delete_product_transients();
				}
				Cache::purge_all();
			}
			$this->log->success(
				/* translators: %s: counters summary */
				sprintf( __( 'Import finished: %s', 'gadgetim-core' ), $this->job->summary() )
			);

			/**
			 * Fires when an import job completed every step.
			 *
			 * @param Job $job The finished job.
			 */
			do_action( 'gadgetim_import_finished', $this->job );
		}
	}
}
