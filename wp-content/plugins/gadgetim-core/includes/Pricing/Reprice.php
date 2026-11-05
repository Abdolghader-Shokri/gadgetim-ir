<?php
/**
 * Rate change → chained background re-save of every product in a currency (architecture §4.3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use ActionScheduler;
use Gadgetim\Core\Support\Cache;
use Gadgetim\Core\Support\Context;
use Throwable;
use WC_Product;
use WC_Product_Variable;
use WP_Error;

/**
 * Each job is a transient gadgetim_reprice_{job} holding the ID list and the progress. Up to
 * SYNC_THRESHOLD products are re-saved in the current request; larger sets run as ONE Action
 * Scheduler async action per batch, each batch enqueueing the next and only the last one
 * enqueueing finish(). Page-cache purging is suspended during batches and the cache is purged
 * exactly once in finish(). The admin progress bar polls ajax_progress().
 */
final class Reprice {

	public const BATCH_SIZE     = 50;
	public const SYNC_THRESHOLD = 40;

	public const HOOK_BATCH  = 'gadgetim_reprice_batch';
	public const HOOK_FINISH = 'gadgetim_reprice_finish';
	public const GROUP       = 'gadgetim';

	public const AJAX_ACTION = 'gadgetim_reprice_progress';
	public const NONCE       = 'gadgetim_reprice_progress';
	public const OPTION_LAST = 'gadgetim_reprice_last';

	private const STATE_TTL = DAY_IN_SECONDS;
	private const DONE_TTL  = 15 * MINUTE_IN_SECONDS;

	/**
	 * A job whose last batch is older than this, with no batch or finish action still queued,
	 * is treated as dead (a batch fatal-errored and never enqueued its successor).
	 */
	private const STALE_AFTER = 10 * MINUTE_IN_SECONDS;

	/**
	 * Applies the current rate of a currency to every product priced in it.
	 *
	 * @param string $code Currency code.
	 * @param int    $user Acting user ID.
	 * @return string|WP_Error Job ID (progress() tells whether it already finished).
	 */
	public static function reprice_currency( string $code, int $user ): string|WP_Error {
		$code     = Currencies::normalize_code( $code );
		$currency = Currencies::get( $code );
		if ( null === $currency ) {
			return new WP_Error( 'gadgetim_unknown_currency', __( 'Unknown currency.', 'gadgetim-core' ) );
		}
		if ( empty( $currency['enabled'] ) ) {
			return new WP_Error( 'gadgetim_currency_disabled', __( 'The currency is disabled; its products keep their last Toman prices.', 'gadgetim-core' ) );
		}
		if ( '' !== self::active_job( $code ) ) {
			return new WP_Error( 'gadgetim_reprice_running', __( 'A reprice of this currency is already running.', 'gadgetim-core' ) );
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'gadgetim_no_woocommerce', __( 'WooCommerce is not active.', 'gadgetim-core' ) );
		}

		$ids = Products::ids_with_currency( $code );
		// The saved rate, mode and step become the applied snapshot before the first batch saves.
		Currencies::snapshot( $code );
		Currencies::bump_prices_version();
		Currencies::mark_applied( $code, count( $ids ), $user );

		return self::start( $code, $ids, $user );
	}

	/**
	 * Re-saves specific products (the daily "Fix now" path).
	 *
	 * @param int[]  $ids  Product/variation IDs.
	 * @param int    $user Acting user ID.
	 * @param string $code Currency code for the record, '' when mixed.
	 * @return string|WP_Error Job ID, or '' when there was nothing to do.
	 */
	public static function reprice_ids( array $ids, int $user, string $code = '' ): string|WP_Error {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return '';
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'gadgetim_no_woocommerce', __( 'WooCommerce is not active.', 'gadgetim-core' ) );
		}
		Currencies::bump_prices_version();
		return self::start( Currencies::normalize_code( $code ), $ids, $user );
	}

	/**
	 * Action Scheduler callback: one batch, then the next action (or finish).
	 *
	 * @param string $job    Job ID.
	 * @param int    $offset First index of this batch.
	 */
	public static function batch( string $job, int $offset ): void {
		self::run_batch( sanitize_key( $job ), max( 0, $offset ), true );
	}

	/**
	 * Re-saves one product; the materialiser does the work inside the save.
	 *
	 * @param int   $id      Product or variation ID.
	 * @param array $parents Collects variable parent IDs for finish().
	 */
	public static function reprice_product( int $id, array &$parents ): bool {
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
			return false;
		}
		try {
			$product->save();
		} catch ( Throwable $e ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			$parents[ $product->get_parent_id() ] = true;
		}
		return true;
	}

	/**
	 * Action Scheduler callback: syncs variable parents, clears product transients, purges the
	 * page cache once and records the summary.
	 */
	public static function finish( string $job ): void {
		$job   = sanitize_key( $job );
		$state = self::state( $job );
		if ( null === $state ) {
			return;
		}

		Cache::suspend();
		try {
			Context::with_suspended(
				static function () use ( $state ): void {
					if ( ! class_exists( 'WC_Product_Variable' ) ) {
						return;
					}
					foreach ( array_keys( $state['parents'] ) as $parent_id ) {
						WC_Product_Variable::sync( (int) $parent_id );
					}
				}
			);
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients();
			}
		} finally {
			Cache::resume();
		}
		Cache::purge_all();

		delete_transient( self::state_key( $job ) );
		if ( '' !== $state['code'] ) {
			delete_transient( self::active_key( $state['code'] ) );
		}

		$summary = [
			'job'      => $job,
			'code'     => $state['code'],
			'total'    => $state['total'],
			'done'     => $state['total'],
			'failed'   => $state['failed'],
			'user'     => $state['user'],
			'started'  => $state['started'],
			'finished' => time(),
		];
		set_transient( self::done_key( $job ), $summary, self::DONE_TTL );
		update_option( self::OPTION_LAST, $summary, false );

		/**
		 * Fires once a reprice job finished (after the single cache purge).
		 *
		 * @param string $code    Currency code ('' for a mixed fix-now job).
		 * @param array  $summary job, code, total, done, failed, user, started, finished.
		 */
		do_action( 'gadgetim_rates_applied', $state['code'], $summary );
	}

	/**
	 * Progress of a job: running state, finished summary, or null when unknown/expired.
	 *
	 * @return array{job:string,code:string,total:int,done:int,failed:int,finished:bool}|null
	 */
	public static function progress( string $job ): ?array {
		$job   = sanitize_key( $job );
		$state = self::state( $job );
		if ( null !== $state ) {
			return [
				'job'      => $job,
				'code'     => $state['code'],
				'total'    => $state['total'],
				'done'     => $state['done'],
				'failed'   => $state['failed'],
				'finished' => false,
			];
		}
		$done = get_transient( self::done_key( $job ) );
		if ( is_array( $done ) ) {
			return [
				'job'      => $job,
				'code'     => (string) ( $done['code'] ?? '' ),
				'total'    => (int) ( $done['total'] ?? 0 ),
				'done'     => (int) ( $done['done'] ?? 0 ),
				'failed'   => (int) ( $done['failed'] ?? 0 ),
				'finished' => true,
			];
		}
		return null;
	}

	/**
	 * wp_ajax_gadgetim_reprice_progress: JSON progress for the admin bar (nonce + manage_options).
	 */
	public static function ajax_progress(): void {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'gadgetim-core' ) ], 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_ajax_referer() above.
		$job      = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( (string) $_GET['job'] ) ) : '';
		$progress = '' !== $job ? self::progress( $job ) : null;
		if ( null === $progress ) {
			wp_send_json_error( [ 'message' => __( 'Unknown or expired job.', 'gadgetim-core' ) ], 404 );
		}
		wp_send_json_success( $progress );
	}

	/**
	 * ID of the job currently running for a currency, '' when none.
	 */
	public static function active_job( string $code ): string {
		$code = Currencies::normalize_code( $code );
		if ( '' === $code ) {
			return '';
		}
		$job = get_transient( self::active_key( $code ) );
		if ( ! is_string( $job ) || '' === $job ) {
			return '';
		}
		$state = self::state( $job );
		if ( null === $state ) {
			// The job state expired or was lost (e.g. Action Scheduler never ran): do not block forever.
			delete_transient( self::active_key( $code ) );
			return '';
		}
		if ( self::is_stale( $state ) ) {
			// A batch died without enqueueing the next one: release the currency.
			delete_transient( self::state_key( $job ) );
			delete_transient( self::active_key( $code ) );
			return '';
		}
		return $job;
	}

	/**
	 * Whether a running job stopped making progress: no heartbeat for STALE_AFTER seconds and
	 * no pending batch/finish action for it.
	 */
	private static function is_stale( array $state ): bool {
		if ( time() - $state['beat'] < self::STALE_AFTER ) {
			return false;
		}
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			foreach ( [ self::HOOK_BATCH, self::HOOK_FINISH ] as $hook ) {
				if ( as_has_scheduled_action( $hook, null, self::GROUP ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Summary of the last finished job (option gadgetim_reprice_last).
	 */
	public static function last(): array {
		$last = get_option( self::OPTION_LAST, [] );
		return is_array( $last ) ? $last : [];
	}

	/**
	 * Whether Action Scheduler can take async actions right now.
	 */
	public static function async_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
			&& class_exists( 'ActionScheduler' )
			&& method_exists( 'ActionScheduler', 'is_initialized' )
			&& ActionScheduler::is_initialized();
	}

	/**
	 * Creates the job state and either runs it now (small sets) or enqueues the first batch.
	 *
	 * @param string $code Currency code or ''.
	 * @param int[]  $ids  Product IDs.
	 * @param int    $user Acting user.
	 */
	private static function start( string $code, array $ids, int $user ): string {
		$job   = wp_generate_uuid4();
		$state = [
			'job'     => $job,
			'code'    => $code,
			'ids'     => array_values( $ids ),
			'total'   => count( $ids ),
			'done'    => 0,
			'failed'  => 0,
			'parents' => [],
			'user'    => $user,
			'started' => time(),
			'beat'    => time(),
		];
		set_transient( self::state_key( $job ), $state, self::STATE_TTL );
		if ( '' !== $code ) {
			set_transient( self::active_key( $code ), $job, self::STATE_TTL );
		}

		/**
		 * Fires when a reprice job starts.
		 *
		 * @param string $job   Job ID.
		 * @param array  $state Initial state.
		 */
		do_action( 'gadgetim_reprice_started', $job, $state );

		if ( count( $ids ) <= self::SYNC_THRESHOLD || ! self::async_available() ) {
			self::run_sync( $job );
		} else {
			self::enqueue( self::HOOK_BATCH, [ $job, 0 ] );
		}
		return $job;
	}

	/**
	 * Runs every batch in the current request, then finish().
	 */
	private static function run_sync( string $job ): void {
		$offset = 0;
		do {
			$state = self::run_batch( $job, $offset, false );
			if ( null === $state ) {
				return;
			}
			$offset = $state['done'];
		} while ( $state['done'] < $state['total'] );
		self::finish( $job );
	}

	/**
	 * One batch of BATCH_SIZE saves with purging and translations suspended.
	 *
	 * @param string $job    Job ID.
	 * @param int    $offset First index.
	 * @param bool   $chain  Enqueue the next action / finish afterwards.
	 */
	private static function run_batch( string $job, int $offset, bool $chain ): ?array {
		$state = self::state( $job );
		if ( null === $state ) {
			return null;
		}
		$slice = array_slice( $state['ids'], $offset, self::BATCH_SIZE );

		$state['beat'] = time();
		set_transient( self::state_key( $job ), $state, self::STATE_TTL );

		Cache::suspend();
		try {
			Context::with_suspended(
				static function () use ( $slice, &$state ): void {
					$before = $state['parents'];
					foreach ( $slice as $id ) {
						if ( ! self::reprice_product( (int) $id, $state['parents'] ) ) {
							++$state['failed'];
						}
					}
					// Sync this batch's variable parents now, while purging is suspended, instead of
					// in WooCommerce's deferred shutdown sync (which would purge per product).
					self::sync_parents( array_keys( array_diff_key( $state['parents'], $before ) ) );
				}
			);
		} finally {
			Cache::resume();
		}

		$state['done'] = min( $state['total'], $offset + self::BATCH_SIZE );
		$state['beat'] = time();
		set_transient( self::state_key( $job ), $state, self::STATE_TTL );

		if ( $chain ) {
			if ( $state['done'] < $state['total'] ) {
				self::enqueue( self::HOOK_BATCH, [ $job, $state['done'] ] );
			} else {
				self::enqueue( self::HOOK_FINISH, [ $job ] );
			}
		}
		return $state;
	}

	/**
	 * Enqueues an Action Scheduler async action; runs the step inline when the scheduler is unavailable.
	 */
	private static function enqueue( string $hook, array $args ): void {
		$id = self::async_available() ? (int) as_enqueue_async_action( $hook, $args, self::GROUP ) : 0;
		if ( $id > 0 ) {
			return;
		}
		if ( self::HOOK_FINISH === $hook ) {
			self::finish( (string) $args[0] );
		} else {
			self::run_batch( (string) $args[0], (int) $args[1], true );
		}
	}

	/**
	 * Syncs variable parents and removes them from WooCommerce's deferred sync list.
	 *
	 * @param int[] $parent_ids Parent product IDs.
	 */
	private static function sync_parents( array $parent_ids ): void {
		global $wc_deferred_product_sync;

		$parent_ids = array_values( array_filter( array_map( 'intval', $parent_ids ) ) );
		if ( ! $parent_ids || ! class_exists( 'WC_Product_Variable' ) ) {
			return;
		}
		foreach ( $parent_ids as $parent_id ) {
			WC_Product_Variable::sync( $parent_id );
		}
		if ( ! empty( $wc_deferred_product_sync ) && is_array( $wc_deferred_product_sync ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- removing our own entries from WooCommerce's deferred list.
			$wc_deferred_product_sync = array_values( array_diff( wp_parse_id_list( $wc_deferred_product_sync ), $parent_ids ) );
		}
	}

	/**
	 * Job state or null.
	 */
	private static function state( string $job ): ?array {
		if ( '' === $job ) {
			return null;
		}
		$state = get_transient( self::state_key( $job ) );
		if ( ! is_array( $state ) || ! isset( $state['ids'], $state['total'] ) ) {
			return null;
		}
		$state['ids']     = array_map( 'intval', (array) $state['ids'] );
		$state['total']   = (int) $state['total'];
		$state['done']    = (int) ( $state['done'] ?? 0 );
		$state['failed']  = (int) ( $state['failed'] ?? 0 );
		$state['parents'] = is_array( $state['parents'] ?? null ) ? $state['parents'] : [];
		$state['code']    = (string) ( $state['code'] ?? '' );
		$state['user']    = (int) ( $state['user'] ?? 0 );
		$state['started'] = (int) ( $state['started'] ?? 0 );
		$state['beat']    = (int) ( $state['beat'] ?? $state['started'] );
		return $state;
	}

	private static function state_key( string $job ): string {
		return 'gadgetim_reprice_' . $job;
	}

	private static function done_key( string $job ): string {
		return 'gadgetim_reprice_done_' . $job;
	}

	private static function active_key( string $code ): string {
		return 'gadgetim_reprice_active_' . strtolower( $code );
	}
}
