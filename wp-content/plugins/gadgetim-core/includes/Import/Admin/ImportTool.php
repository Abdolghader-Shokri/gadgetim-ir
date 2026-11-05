<?php
/**
 * Tools → "Import the old site": 20-item AJAX batches with progress (manage_options).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import\Admin;

use Gadgetim\Core\Import\Importer;
use Gadgetim\Core\Import\Job;
use Gadgetim\Core\Import\Paths;
use Gadgetim\Core\Import\Source;
use Gadgetim\Core\Settings\Admin\Tools;
use Gadgetim\Core\Settings\Capabilities;
use WP_Error;

/**
 * The job lives in a transient between requests (Job::save()); each request suspends the
 * cache and translations around its batch; the final batch purges once.
 */
final class ImportTool {

	public const TOOL_ID = 'import-old-site';

	public const AJAX_ACTION = 'gadgetim_import_batch';

	public const BATCH_SIZE = 20;

	public const TIME_BUDGET = 20.0;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'register_tool' ], 20 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'ajax' ] );
	}

	/**
	 * admin_init: the Tools entry.
	 */
	public static function register_tool(): void {
		if ( ! class_exists( Tools::class ) ) {
			return;
		}
		Tools::add(
			self::TOOL_ID,
			__( 'Import the old site', 'gadgetim-core' ),
			[ self::class, 'run_sync' ],
			Capabilities::ADMIN,
			[
				'description' => __( 'Imports categories, brands, tags, the 107 products with their images, the content pages and the redirect list from the extracted backup. Re-running updates what exists (idempotent).', 'gadgetim-core' ),
				'button'      => __( 'Run the whole import now', 'gadgetim-core' ),
				'confirm'     => __( 'Run the import synchronously? This can take several minutes.', 'gadgetim-core' ),
				'order'       => 30,
				'render'      => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * Tool UI.
	 */
	public static function render( array $tool ): void {
		BatchUi::render(
			[
				'id'           => 'gtm-import',
				'action'       => self::AJAX_ACTION,
				'nonce_action' => self::AJAX_ACTION,
				'start'        => __( 'Start import', 'gadgetim-core' ),
				'fields'       => [ self::class, 'fields' ],
				'fallback'     => static function () use ( $tool ): void {
					Tools::form( $tool, (string) $tool['button'] );
				},
			]
		);
	}

	/**
	 * Form fields of the runner.
	 */
	public static function fields(): void {
		$source  = Paths::default_source_dir();
		$output  = Paths::output_dir();
		$steps   = [
			'terms'     => __( 'Categories, brands, tags', 'gadgetim-core' ),
			'products'  => __( 'Products', 'gadgetim-core' ),
			'images'    => __( 'Images', 'gadgetim-core' ),
			'pages'     => __( 'Pages (privacy, terms, about, contact, FAQ)', 'gadgetim-core' ),
			'redirects' => __( 'Redirect list (data/redirects.csv)', 'gadgetim-core' ),
		];
		?>
		<div class="gtm-batch__field">
			<label for="gtm-import-source"><?php esc_html_e( 'Export directory', 'gadgetim-core' ); ?></label>
			<input type="text" id="gtm-import-source" name="source" value="<?php echo esc_attr( $source ); ?>" dir="ltr" class="code">
			<span class="description">
				<?php
				/* translators: %s: output directory */
				echo esc_html( sprintf( __( 'Must contain products.json and images/. The redirect CSV is written to %s.', 'gadgetim-core' ), $output ) );
				?>
			</span>
		</div>
		<fieldset class="gtm-batch__field">
			<legend><?php esc_html_e( 'Steps', 'gadgetim-core' ); ?></legend>
			<?php foreach ( $steps as $key => $label ) : ?>
				<label class="gtm-batch__option"><input type="checkbox" name="steps[]" value="<?php echo esc_attr( $key ); ?>" checked> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<label class="gtm-batch__option"><input type="checkbox" name="dry_run" value="1"> <?php esc_html_e( 'Dry run (report only, nothing is written)', 'gadgetim-core' ); ?></label>
		<label class="gtm-batch__option"><input type="checkbox" name="apply_redirects" value="1"> <?php esc_html_e( 'Also write the redirects into the gadgetim_redirects option', 'gadgetim-core' ); ?></label>
		<div class="gtm-batch__field">
			<label for="gtm-import-limit"><?php esc_html_e( 'Limit (first N products; 0 = all)', 'gadgetim-core' ); ?></label>
			<input type="number" id="gtm-import-limit" name="limit" value="0" min="0" step="1" class="small-text">
		</div>
		<?php
	}

	/**
	 * AJAX handler: nonce + manage_options; op=start|continue|stop.
	 */
	public static function ajax(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::ADMIN ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to run the import.', 'gadgetim-core' ) ], 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer() above.
		$op     = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : 'start';
		$job_id = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( (string) $_POST['job'] ) ) : '';

		if ( 'stop' === $op ) {
			$job = Job::load( $job_id );
			if ( $job ) {
				$job->delete();
			}
			wp_send_json_success( [ 'finished' => true ] );
		}

		if ( 'start' === $op ) {
			$source = isset( $_POST['source'] ) ? Paths::sanitize_dir( (string) $_POST['source'] ) : Paths::default_source_dir(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed -- sanitize_dir() unslashes.
			$steps  = isset( $_POST['steps'] ) && is_array( $_POST['steps'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['steps'] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$options = [
				'source'          => $source,
				'steps'           => $steps,
				'dry_run'         => ! empty( $_POST['dry_run'] ),
				'apply_redirects' => ! empty( $_POST['apply_redirects'] ),
				'limit'           => isset( $_POST['limit'] ) ? absint( wp_unslash( (string) $_POST['limit'] ) ) : 0,
				'author'          => get_current_user_id(),
			];
			// phpcs:enable
			if ( '' === $source ) {
				wp_send_json_error( [ 'message' => __( 'The source must be an existing local directory (no URLs or stream wrappers).', 'gadgetim-core' ) ], 400 );
			}
			$check = ( new Source( $source ) )->validate();
			if ( $check instanceof WP_Error ) {
				wp_send_json_error( [ 'message' => $check->get_error_message() ], 400 );
			}
			if ( $source !== Paths::default_source_dir() ) {
				update_option( Paths::OPTION_SOURCE, $source, false );
			}
			$job = Job::create( $options );
			$job->save();
			wp_send_json_success(
				[
					'job'      => $job->id,
					'total'    => 0,
					'done'     => 0,
					'finished' => false,
					'status'   => $job->dry_run ? __( 'Dry run started.', 'gadgetim-core' ) : __( 'Import started.', 'gadgetim-core' ),
					'log'      => [],
				]
			);
		}

		$job = Job::load( $job_id );
		if ( ! $job ) {
			wp_send_json_error( [ 'message' => __( 'The import job has expired; start again.', 'gadgetim-core' ) ], 410 );
		}
		$importer = Importer::resume( $job );
		$importer->run( self::BATCH_SIZE, microtime( true ) + self::TIME_BUDGET );
		$job = $importer->job();
		if ( $job->finished ) {
			$job->delete();
		} else {
			$job->save();
		}
		$lines = array_map(
			static fn( array $line ): array => [
				'level'   => $line['level'],
				'step'    => $line['step'],
				'message' => $line['message'],
			],
			$importer->log()->lines()
		);
		wp_send_json_success(
			[
				'job'      => $job->id,
				'total'    => $job->total(),
				'done'     => $job->done(),
				'finished' => $job->finished,
				'status'   => $job->finished
					/* translators: %s: counters summary */
					? sprintf( __( 'Finished: %s', 'gadgetim-core' ), $job->summary() )
					/* translators: 1: current step, 2: done, 3: total */
					: sprintf( __( 'Step "%1$s" — %2$d of %3$d items', 'gadgetim-core' ), (string) $job->current_step(), $job->done(), $job->total() ),
				'log'      => $lines,
			]
		);
	}

	/**
	 * No-JS fallback (standard Tools form): runs everything in one request. Returns the success
	 * message, a [ type, message ] warning when the import logged errors, or the validation error.
	 */
	public static function run_sync(): string|array|WP_Error {
		$importer = Importer::create(
			[
				'source' => Paths::default_source_dir(),
				'author' => get_current_user_id(),
			]
		);
		$check    = $importer->validate();
		if ( $check instanceof WP_Error ) {
			return $check;
		}
		$job = $importer->run_all();
		$log = $importer->log();
		if ( $log->errors() > 0 ) {
			return [
				'type'    => 'warning',
				/* translators: 1: counters summary, 2: error count */
				'message' => sprintf( __( 'Import finished with errors: %1$s (%2$d errors; see the log).', 'gadgetim-core' ), $job->summary(), $log->errors() ),
			];
		}
		/* translators: %s: counters summary */
		return sprintf( __( 'Import finished: %s', 'gadgetim-core' ), $job->summary() );
	}
}
