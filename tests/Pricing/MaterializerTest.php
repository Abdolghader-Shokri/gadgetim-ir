<?php
/**
 * Materialisation against the Local site (architecture §4.3 tests): one save writes the Toman
 * prices and the lookup row, the guard catches bypassing writes, the reprice job (sync and
 * chained batches) ends with every _gtm_rate_used updated and exactly one cache purge, REST
 * exposes only wc/v3, Display renders the price partial, Migration converts the old meta.
 *
 * Writes to the Local database: temporary products (deleted afterwards), a test currency "TST"
 * (removed afterwards); the gadgetim_* options touched are restored to their previous values.
 *
 * Run: "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/Pricing/MaterializerTest.php
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

use Gadgetim\Core\Pricing\Calculator;
use Gadgetim\Core\Pricing\Currencies;
use Gadgetim\Core\Pricing\Display;
use Gadgetim\Core\Pricing\Guard;
use Gadgetim\Core\Pricing\Materializer;
use Gadgetim\Core\Pricing\Meta;
use Gadgetim\Core\Pricing\Migration;
use Gadgetim\Core\Pricing\Products;
use Gadgetim\Core\Pricing\Reprice;
use Gadgetim\Core\Pricing\Rest;
use Gadgetim\Core\Settings\Admin\Notices;
use Gadgetim\Core\Support\RateLimit;
use Gadgetim\Tests\Pricing\TestCase;

/**
 * Integration tests (WooCommerce + database).
 */
final class MaterializerTest extends TestCase {

	private const CUR      = 'TST';
	private const DISABLED = 'TSD';
	private const RATE     = 78000;

	private const OPTIONS = [ 'gadgetim_currencies', 'gadgetim_rate_history', 'gadgetim_prices_version', 'gadgetim_notices', 'gadgetim_reprice_last' ];

	private const MISSING = '__gtm_missing__';

	/** @var array<string,mixed> */
	private array $snapshot = [];

	/** @var int[] */
	private array $created = [];

	/** @var array<int,array{0:string,1:array}> */
	private array $queue = [];

	protected function setUpSuite(): void {
		if ( ! function_exists( 'wc_get_product' ) ) {
			throw new RuntimeException( 'WooCommerce is not loaded.' );
		}
		foreach ( self::OPTIONS as $name ) {
			$this->snapshot[ $name ] = get_option( $name, self::MISSING );
		}
		Currencies::save(
			self::CUR,
			[
				'rate'          => self::RATE,
				'rounding_mode' => Calculator::MODE_CHARM,
				'rounding_step' => 1000,
				'decimals'      => 2,
				'enabled'       => true,
				'symbol'        => 'TST',
				'label'         => [
					'fa' => 'واحد آزمایشی',
					'en' => 'Test unit',
				],
			],
			0
		);
		Currencies::save(
			self::DISABLED,
			[
				'rate'          => 50000,
				'rounding_mode' => Calculator::MODE_NEAREST,
				'rounding_step' => 1000,
				'enabled'       => false,
				'symbol'        => 'TSD',
				'label'         => [ 'en' => 'Disabled test unit' ],
			],
			0
		);
	}

	protected function tearDown(): void {
		$this->delete_created();
		Guard::flush();
	}

	protected function tearDownSuite(): void {
		global $wpdb;

		$this->delete_created();
		foreach ( self::OPTIONS as $name ) {
			if ( self::MISSING === $this->snapshot[ $name ] ) {
				delete_option( $name );
			} else {
				update_option( $name, $this->snapshot[ $name ] );
			}
		}
		Currencies::invalidate();
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gadgetim_reprice_%' OR option_name LIKE '_transient_timeout_gadgetim_reprice_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_transient( RateLimit::bucket_name() );
		RateLimit::flush();
		wp_set_current_user( 0 );
	}

	// ------------------------------------------------------------------ hooks

	public function test_hooks_are_registered(): void {
		$this->assertSame( 10, has_action( 'woocommerce_before_product_object_save', [ Materializer::class, 'apply' ] ) );
		$this->assertSame( 1, has_action( 'woocommerce_before_product_object_save', [ Guard::class, 'on_before_save' ] ) );
		$this->assertSame( 999, has_action( 'woocommerce_after_product_object_save', [ Guard::class, 'on_after_save' ] ) );
		$this->assertSame( 10, has_action( 'updated_post_meta', [ Guard::class, 'on_meta_change' ] ) );
		$this->assertSame( 20, has_filter( 'woocommerce_get_price_html', [ Display::class, 'filter_price_html' ] ) );
		$this->assertSame( 10, has_filter( 'woocommerce_rest_pre_insert_product_object', [ Rest::class, 'pre_insert' ] ) );
		$this->assertTrue( registered_meta_key_exists( 'post', Meta::BASE_REGULAR, 'product' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', Meta::CURRENCY, 'product_variation' ) );
		$meta = get_registered_meta_keys( 'post', 'product' );
		$this->assertFalse( (bool) $meta[ Meta::BASE_REGULAR ]['show_in_rest'], 'base fields hidden from wp/v2' );
	}

	// ----------------------------------------------------------- materialiser

	public function test_simple_product_materialises_in_one_save(): void {
		$product = $this->make_simple( 'GTM test simple', [ Meta::BASE_REGULAR => '30', Meta::BASE_SALE => '25' ] );
		$fresh   = $this->fresh( $product->get_id() );

		$this->assertSame( '2339000', $fresh->get_regular_price( 'edit' ), '30 × 78,000 charm' );
		$this->assertSame( '1949000', $fresh->get_sale_price( 'edit' ), '25 × 78,000 = 1,950,000 → 1,949,000' );
		$this->assertSame( '1949000', $fresh->get_price( 'edit' ) );
		$this->assertSame( '1949000', (string) get_post_meta( $product->get_id(), '_price', true ) );
		$this->assertSame( '78000', Meta::rate_used( $fresh ) );
		$this->assertGreaterThan( time() - 120, Meta::priced_at( $fresh ) );

		$row = $this->lookup( $product->get_id() );
		$this->assertNotEmpty( $row, 'wc_product_meta_lookup row exists' );
		$this->assertEquals( 1949000, $row['min_price'] );
		$this->assertEquals( 1949000, $row['max_price'] );
		$this->assertSame( 1, (int) $row['onsale'] );
	}

	public function test_variation_new_base_price_updates_price_and_lookup_in_one_save(): void {
		[ $parent, $variation ] = $this->make_variable( [ Meta::BASE_REGULAR => '30' ] );

		$fresh = $this->fresh( $variation->get_id() );
		$this->assertSame( '2339000', $fresh->get_price( 'edit' ) );
		$this->assertEquals( 2339000, $this->lookup( $variation->get_id() )['min_price'] );

		$fresh->update_meta_data( Meta::BASE_REGULAR, '40' );
		$fresh->save();

		$this->assertSame( '3119000', (string) get_post_meta( $variation->get_id(), '_price', true ), '40 × 78,000 = 3,120,000 → 3,119,000' );
		$this->assertEquals( 3119000, $this->lookup( $variation->get_id() )['min_price'], 'variation lookup row in the same save' );

		WC_Product_Variable::sync( $parent->get_id() );
		$parent_row = $this->lookup( $parent->get_id() );
		$this->assertEquals( 3119000, $parent_row['min_price'], 'parent range after sync' );
		$this->assertEquals( 3119000, $parent_row['max_price'] );
	}

	public function test_unapplied_rate_does_not_reach_products_until_apply(): void {
		$product = $this->make_simple( 'GTM unapplied rate', [ Meta::BASE_REGULAR => '30' ] );
		try {
			Currencies::save( self::CUR, [ 'rate' => 90000 ], 0 );
			$this->assertTrue( Currencies::has_pending( Currencies::get( self::CUR ) ), 'saved rate is pending' );

			// Any CRUD save (stock update, quick edit, review count…) keeps the applied snapshot.
			$fresh = $this->fresh( $product->get_id() );
			$fresh->set_stock_status( 'instock' );
			$fresh->save();
			$fresh = $this->fresh( $product->get_id() );
			$this->assertSame( '2339000', $fresh->get_regular_price( 'edit' ), 'still 30 × 78,000 (charm)' );
			$this->assertSame( '78000', Meta::rate_used( $fresh ) );
			$this->assertFalse( array_key_exists( $product->get_id(), Products::mismatched() ), 'not reported as drifted' );

			$job = Reprice::reprice_currency( self::CUR, 0 );
			$this->assertFalse( is_wp_error( $job ) );
			$this->assertFalse( Currencies::has_pending( Currencies::get( self::CUR ) ), 'apply wrote the snapshot' );
			$this->assertSame( '90000', Meta::rate_used( $this->fresh( $product->get_id() ) ) );
		} finally {
			Currencies::save( self::CUR, [ 'rate' => self::RATE ], 0 );
			Currencies::snapshot( self::CUR );
		}
	}

	public function test_toman_edits_are_ignored_for_currency_products(): void {
		$product = $this->make_simple( 'GTM toman edit', [ Meta::BASE_REGULAR => '30' ] );
		$product->set_regular_price( '1' );
		$product->set_sale_price( '' );
		$product->save();

		$fresh = $this->fresh( $product->get_id() );
		$this->assertSame( '2339000', $fresh->get_regular_price( 'edit' ) );
	}

	public function test_sale_not_below_regular_is_dropped(): void {
		$product = $this->make_simple( 'GTM bad sale', [ Meta::BASE_REGULAR => '30', Meta::BASE_SALE => '40' ] );
		$fresh   = $this->fresh( $product->get_id() );

		$this->assertSame( '', $fresh->get_sale_price( 'edit' ) );
		$this->assertSame( '', Meta::base_sale( $fresh ), 'base sale cleared for consistency' );
		$this->assertSame( '2339000', $fresh->get_price( 'edit' ) );
	}

	public function test_sale_end_clears_base_sale(): void {
		$product = $this->make_simple( 'GTM ended sale', [ Meta::BASE_REGULAR => '30', Meta::BASE_SALE => '25' ] );
		$product->set_date_on_sale_from( time() - 2 * DAY_IN_SECONDS );
		$product->set_date_on_sale_to( time() - DAY_IN_SECONDS );
		$product->save();

		$fresh = $this->fresh( $product->get_id() );
		$this->assertSame( '', Meta::base_sale( $fresh ) );
		$this->assertSame( '', $fresh->get_sale_price( 'edit' ) );
		$this->assertSame( '2339000', (string) get_post_meta( $product->get_id(), '_price', true ) );
	}

	public function test_disabled_currency_keeps_last_prices_and_flags_the_product(): void {
		$product = $this->make_simple( 'GTM disabled currency', [], '5000000' );
		$product->update_meta_data( Meta::CURRENCY, self::DISABLED );
		$product->update_meta_data( Meta::BASE_REGULAR, '10' );
		$product->save();

		$fresh = $this->fresh( $product->get_id() );
		$this->assertSame( self::DISABLED, Meta::currency( $fresh ), 'a known but disabled code is kept' );
		$this->assertSame( '5000000', $fresh->get_regular_price( 'edit' ), 'last Toman price kept' );
		if ( class_exists( Notices::class ) ) {
			$this->assertTrue( Notices::has( 'product-' . $product->get_id() ), 'product flagged' );
			$message = (string) ( Notices::all()[ 'product-' . $product->get_id() ]['message'] ?? '' );
			$this->assertStringContains( Materializer::FLAG_MARKER, $message );
		}
		$this->assertEmpty( Products::mismatched(), 'disabled currencies are not reported as mismatches' );
	}

	public function test_fixed_toman_products_are_untouched(): void {
		$product = $this->make_simple( 'GTM fixed', [], '1234000' );
		$fresh   = $this->fresh( $product->get_id() );
		$this->assertSame( '1234000', $fresh->get_regular_price( 'edit' ) );
		$this->assertSame( '', Meta::rate_used( $fresh ) );
		$this->assertSame( 0, Meta::priced_at( $fresh ) );
	}

	// ------------------------------------------------------------------ guard

	public function test_guard_resaves_after_a_direct_meta_write(): void {
		$product = $this->make_simple( 'GTM guard', [ Meta::BASE_REGULAR => '30' ] );
		$this->assertEmpty( Guard::pending(), 'CRUD saves do not trigger the guard' );

		update_post_meta( $product->get_id(), Meta::BASE_REGULAR, '40' );
		$this->assertContains( $product->get_id(), Guard::pending(), 'direct write scheduled a save' );

		$saved = Guard::flush();
		$this->assertContains( $product->get_id(), $saved );
		$this->assertSame( '3119000', $this->fresh( $product->get_id() )->get_regular_price( 'edit' ) );
		$this->assertEmpty( Guard::pending() );
	}

	public function test_deleting_a_currency_variation_leaves_no_orphan_rows(): void {
		[ $parent, $variation ] = $this->make_variable( [ Meta::BASE_REGULAR => '30' ] );
		$vid = $variation->get_id();
		$this->assertNotEmpty( $this->lookup( $vid ) );

		wc_get_product( $vid )->delete( true );
		$this->assertFalse( in_array( $vid, Guard::pending(), true ), 'meta deletions inside wp_delete_post are not guarded writes' );
		$this->assertFalse( in_array( $vid, Guard::flush(), true ) );
		$this->assertSame( 0, $this->meta_count( $vid ), 'no orphan meta rows' );
		$this->assertEmpty( $this->lookup( $vid ), 'no orphan lookup row' );

		// Belt and braces: even when a deleted ID is pending, flush() must not revive it. WooCommerce's
		// per-request product-type cache still hands out an object for the deleted variation.
		$variation2 = new WC_Product_Variation();
		$variation2->set_parent_id( $parent->get_id() );
		$variation2->set_attributes( [ 'size' => 'M' ] );
		$variation2->update_meta_data( Meta::CURRENCY, self::CUR );
		$variation2->update_meta_data( Meta::BASE_REGULAR, '20' );
		$variation2->save();
		$vid2 = $variation2->get_id();
		remove_action( 'before_delete_post', [ Guard::class, 'on_before_delete_post' ], 1 );
		remove_action( 'after_delete_post', [ Guard::class, 'on_after_delete_post' ], 999 );
		try {
			wc_get_product( $vid2 )->delete( true );
			$this->assertContains( $vid2, Guard::pending(), 'without the delete hooks the ID gets scheduled' );
			$zombie = wc_get_product( $vid2 );
			$this->assertTrue( $zombie instanceof WC_Product && $zombie->get_id() === $vid2, 'WooCommerce hands out a zombie object for the deleted ID' );
			$this->assertFalse( in_array( $vid2, Guard::flush(), true ), 'flush() refuses to save a deleted product' );
			$this->assertSame( 0, $this->meta_count( $vid2 ), 'no orphan meta rows from a zombie save' );
			$this->assertEmpty( $this->lookup( $vid2 ), 'no orphan lookup row from a zombie save' );
		} finally {
			add_action( 'before_delete_post', [ Guard::class, 'on_before_delete_post' ], 1, 1 );
			add_action( 'after_delete_post', [ Guard::class, 'on_after_delete_post' ], 999, 1 );
		}
	}

	public function test_meta_sanitizers_whitelist_codes_and_decimals(): void {
		$product = $this->make_simple( 'GTM sanitizer', [], '1000000' );
		$id      = $product->get_id();

		update_post_meta( $id, Meta::CURRENCY, 'xyz' );
		$this->assertSame( '', (string) get_post_meta( $id, Meta::CURRENCY, true ), 'unknown code rejected' );
		update_post_meta( $id, Meta::CURRENCY, strtolower( self::CUR ) );
		$this->assertSame( self::CUR, (string) get_post_meta( $id, Meta::CURRENCY, true ), 'code upper-cased' );
		update_post_meta( $id, Meta::BASE_REGULAR, '۳۰٫۵' );
		$this->assertSame( '30.5', (string) get_post_meta( $id, Meta::BASE_REGULAR, true ), 'Persian digits normalised' );
		update_post_meta( $id, Meta::BASE_SALE, '-4' );
		$this->assertSame( '', (string) get_post_meta( $id, Meta::BASE_SALE, true ), 'negative rejected' );

		Guard::flush();
		$this->assertSame( (string) Calculator::to_toman( '30.5', Currencies::get( self::CUR ) ), $this->fresh( $id )->get_regular_price( 'edit' ), 'guard materialised the direct writes' );
	}

	public function test_mismatch_detection_notice_and_fix(): void {
		$product = $this->make_simple( 'GTM mismatch', [ Meta::BASE_REGULAR => '30' ] );
		$id      = $product->get_id();
		$this->assertEmpty( Products::mismatched() );

		update_post_meta( $id, '_regular_price', '1' ); // bypasses everything: not a guarded key
		$bad = Products::mismatched();
		$this->assertSame( 'regular', $bad[ $id ] ?? null );

		$found = Guard::daily_check();
		$this->assertSame( $bad, $found );
		if ( class_exists( Notices::class ) ) {
			$this->assertTrue( Notices::has( Guard::NOTICE_ID ) );
		}

		$job = Reprice::reprice_ids( array_keys( $bad ), 0 );
		$this->assertFalse( is_wp_error( $job ) );
		$this->assertSame( '2339000', $this->fresh( $id )->get_regular_price( 'edit' ) );
		$this->assertEmpty( Products::mismatched() );

		Guard::daily_check();
		if ( class_exists( Notices::class ) ) {
			$this->assertFalse( Notices::has( Guard::NOTICE_ID ), 'notice cleared once everything matches' );
		}
	}

	// ---------------------------------------------------------------- reprice

	public function test_reprice_sync_updates_rate_used_and_purges_once(): void {
		$ids = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->make_simple( 'GTM reprice ' . $i, [ Meta::BASE_REGULAR => '30' ] )->get_id();
		}
		$this->assertSame( 3, Products::count_with_currency( self::CUR ) );

		$purges  = 0;
		$applied = [];
		$count_purge = static function () use ( &$purges ): void {
			++$purges;
		};
		$count_apply = static function ( string $code, array $summary ) use ( &$applied ): void {
			$applied[] = [ $code, $summary['total'] ];
		};
		add_action( 'gadgetim_cache_purged_all', $count_purge );
		add_action( 'gadgetim_rates_applied', $count_apply, 10, 2 );
		try {
			Currencies::save( self::CUR, [ 'rate' => 80000 ], 0 );
			$this->assertTrue( Currencies::has_unapplied_change( self::CUR ) );

			$job = Reprice::reprice_currency( self::CUR, 0 );
			$this->assertFalse( is_wp_error( $job ), is_wp_error( $job ) ? $job->get_error_message() : '' );
			$progress = Reprice::progress( $job );
			$this->assertTrue( $progress['finished'] );
			$this->assertSame( 3, $progress['total'] );
			$this->assertSame( 0, $progress['failed'] );

			foreach ( $ids as $id ) {
				$fresh = $this->fresh( $id );
				$this->assertSame( '80000', Meta::rate_used( $fresh ) );
				$this->assertSame( '2399000', $fresh->get_regular_price( 'edit' ), '30 × 80,000 = 2,400,000 → 2,399,000' );
			}
			$this->assertSame( 1, $purges, 'exactly one gadgetim_cache_purged_all' );
			$this->assertSame( [ [ self::CUR, 3 ] ], $applied );
			$this->assertFalse( Currencies::has_unapplied_change( self::CUR ) );
			$this->assertSame( '', Reprice::active_job( self::CUR ) );
			$this->assertSame( 3, (int) ( Reprice::last()['total'] ?? 0 ) );
		} finally {
			remove_action( 'gadgetim_cache_purged_all', $count_purge );
			remove_action( 'gadgetim_rates_applied', $count_apply, 10 );
			Currencies::save( self::CUR, [ 'rate' => self::RATE ], 0 );
			Currencies::snapshot( self::CUR ); // fixture reset: products are re-priced by the next test anyway.
		}
	}

	public function test_reprice_chained_batches_cover_every_product_with_one_purge(): void {
		$total = Reprice::SYNC_THRESHOLD + 5; // forces the Action Scheduler path
		$ids   = [];
		for ( $i = 0; $i < $total; $i++ ) {
			$ids[] = $this->make_simple( 'GTM batch ' . $i, [ Meta::BASE_REGULAR => '30' ] )->get_id();
		}

		$purges      = 0;
		$count_purge = static function () use ( &$purges ): void {
			++$purges;
		};
		$this->queue = [];
		$intercept   = function ( $pre, string $hook, array $args ) {
			if ( ! str_starts_with( $hook, 'gadgetim_reprice_' ) ) {
				return $pre;
			}
			$this->queue[] = [ $hook, $args ];
			return 1; // pretend Action Scheduler stored it
		};
		add_action( 'gadgetim_cache_purged_all', $count_purge );
		add_filter( 'pre_as_enqueue_async_action', $intercept, 10, 3 );
		try {
			Currencies::save( self::CUR, [ 'rate' => 81000 ], 0 );
			$job = Reprice::reprice_currency( self::CUR, 0 );
			$this->assertFalse( is_wp_error( $job ), is_wp_error( $job ) ? $job->get_error_message() : '' );
			$this->assertFalse( Reprice::progress( $job )['finished'], 'large job is deferred' );
			$this->assertSame( $job, Reprice::active_job( self::CUR ) );
			$this->assertCount( 1, $this->queue, 'exactly one action enqueued at start' );

			// Drain the queue the way Action Scheduler would: each batch enqueues the next, the last one enqueues finish.
			$steps = 0;
			while ( $this->queue ) {
				[ $hook, $args ] = array_shift( $this->queue );
				do_action( $hook, ...$args );
				++$steps;
			}
			$this->assertSame( 2, $steps, 'one batch of 45 (≤ 50) + finish' );

			$progress = Reprice::progress( $job );
			$this->assertTrue( $progress['finished'] );
			$this->assertSame( $total, $progress['done'] );
			$this->assertSame( 0, $progress['failed'] );
			foreach ( $ids as $id ) {
				$this->assertSame( '81000', Meta::rate_used( $this->fresh( $id ) ) );
			}
			$this->assertSame( 1, $purges, 'exactly one purge for the whole job' );
			$this->assertSame( '', Reprice::active_job( self::CUR ) );
		} finally {
			remove_filter( 'pre_as_enqueue_async_action', $intercept, 10 );
			remove_action( 'gadgetim_cache_purged_all', $count_purge );
			Currencies::save( self::CUR, [ 'rate' => self::RATE ], 0 );
			Currencies::snapshot( self::CUR ); // fixture reset: products are re-priced by the next test anyway.
		}
	}

	public function test_reprice_refuses_disabled_currency(): void {
		$result = Reprice::reprice_currency( self::DISABLED, 0 );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'gadgetim_currency_disabled', $result->get_error_code() );
	}

	// ------------------------------------------------------------------- REST

	public function test_rest_payload_validation(): void {
		$product = $this->make_simple( 'GTM rest payload', [], '1000000' );

		$clean = Rest::sanitize_payload(
			[
				'currency'     => strtolower( self::CUR ),
				'base_regular' => '۳۰',
				'base_sale'    => '',
			],
			$product
		);
		$this->assertSame(
			[
				Meta::CURRENCY     => self::CUR,
				Meta::BASE_REGULAR => '30',
				Meta::BASE_SALE    => '',
			],
			$clean
		);
		$this->assertTrue( is_wp_error( Rest::sanitize_payload( [ 'currency' => self::DISABLED, 'base_regular' => '5' ], $product ) ), 'disabled currency rejected' );
		$this->assertTrue( is_wp_error( Rest::sanitize_payload( [ 'currency' => 'ZZZZ' ], $product ) ), 'malformed code rejected' );
		$this->assertTrue( is_wp_error( Rest::sanitize_payload( [ 'base_regular' => '30', 'base_sale' => '50' ] ) ), 'sale ≥ regular rejected' );
		$this->assertTrue( is_wp_error( Rest::sanitize_payload( [ 'base_regular' => 'abc' ] ) ), 'non-numeric rejected' );
		$this->assertTrue( is_wp_error( Rest::sanitize_payload( [ 'currency' => self::CUR ], $product ) ), 'currency without a base price rejected' );
		$this->assertSame( [], Rest::sanitize_payload( [], $product ), 'empty payload changes nothing' );

		$payload = Rest::payload( $this->fresh( $product->get_id() ) );
		$this->assertSame( '', $payload['currency'] );
		$this->assertSame( null, $payload['priced_at'] );
	}

	public function test_rest_round_trip_wc_v3_writes_and_wp_v2_ignores(): void {
		$admins = get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			]
		);
		if ( ! $admins ) {
			$this->out( '  (no administrator user on this site: REST round trip skipped)' );
			return;
		}
		$product = $this->make_simple( 'GTM rest round trip', [], '1000000' );
		$id      = $product->get_id();
		wp_set_current_user( (int) $admins[0] );
		try {
			$request = new WP_REST_Request( 'PUT', '/wc/v3/products/' . $id );
			$request->set_body_params(
				[
					'gadgetim_pricing' => [
						'currency'     => self::CUR,
						'base_regular' => '30',
						'base_sale'    => '25',
					],
				]
			);
			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
			$data = $response->get_data();
			$this->assertSame( self::CUR, $data['gadgetim_pricing']['currency'] ?? null );
			$this->assertSame( '30', $data['gadgetim_pricing']['base_regular'] ?? null );
			$this->assertSame( '78000', $data['gadgetim_pricing']['rate_used'] ?? null );
			$this->assertSame( '2339000', $data['regular_price'] ?? null, 'materialised in the same request' );
			$this->assertSame( '1949000', $data['sale_price'] ?? null );

			$request = new WP_REST_Request( 'PUT', '/wc/v3/products/' . $id );
			$request->set_body_params( [ 'gadgetim_pricing' => [ 'base_sale' => '40' ] ] );
			$response = rest_do_request( $request );
			$this->assertSame( 400, $response->get_status(), 'sale ≥ regular is a 400' );
			$this->assertSame( '25', Meta::base_sale( $this->fresh( $id ) ), 'nothing written on a rejected payload' );

			$request = new WP_REST_Request( 'POST', '/wp/v2/product/' . $id );
			$request->set_body_params( [ 'meta' => [ Meta::BASE_REGULAR => '999' ] ] );
			$response = rest_do_request( $request );
			$this->assertTrue( in_array( $response->get_status(), [ 200, 400, 403, 404 ], true ), 'wp/v2 status ' . $response->get_status() );
			$fresh = $this->fresh( $id );
			$this->assertSame( '30', Meta::base_regular( $fresh ), 'wp/v2 meta write ignored' );
			$this->assertSame( '2339000', $fresh->get_regular_price( 'edit' ) );
			$wp_data = $response->get_data();
			$this->assertFalse( isset( $wp_data['meta'][ Meta::BASE_REGULAR ] ), 'base field not exposed by wp/v2' );
		} finally {
			wp_set_current_user( 0 );
		}

		$response = rest_do_request( new WP_REST_Request( 'GET', '/gadgetim/v1/currencies' ) );
		$this->assertSame( 200, $response->get_status() );
		$codes = array_column( (array) $response->get_data(), 'code' );
		$this->assertContains( self::CUR, $codes );
		$this->assertFalse( in_array( self::DISABLED, $codes, true ), 'disabled currencies are not public' );
		$this->assertSame( 'public, max-age=300', $response->get_headers()['Cache-Control'] ?? null );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/gadgetim/v1/currencies/' . self::DISABLED ) );
		$this->assertSame( 404, $response->get_status(), 'disabled currency hidden from anonymous readers' );

		$request = new WP_REST_Request( 'PUT', '/gadgetim/v1/currencies/' . self::CUR );
		$request->set_body_params( [ 'rate' => 79000 ] );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status(), 'anonymous write refused' );
		$this->assertEquals( self::RATE, Currencies::get( self::CUR )['rate'] );
	}

	// ---------------------------------------------------------------- display

	public function test_display_html_markup(): void {
		$product = $this->make_simple( 'GTM display', [ Meta::BASE_REGULAR => '30', Meta::BASE_SALE => '25' ] );
		$fresh   = $this->fresh( $product->get_id() );

		$fa = Display::html( $fresh, [ 'lang' => 'fa', 'base_hint' => true ] );
		$this->assertStringContains( '<p class="price price--sale" data-id="' . $product->get_id() . '">', $fa );
		$this->assertStringContains( '<span class="price__now"><bdi>۱٬۹۴۹٬۰۰۰</bdi> <span class="price__unit">تومان</span></span>', $fa );
		$this->assertStringContains( '<del class="price__was"><bdi>۲٬۳۳۹٬۰۰۰</bdi></del>', $fa );
		$this->assertStringContains( '<span class="price__off">' . "\u{200E}" . '-۱۷٪</span>', $fa );
		$this->assertStringContains( 'data-cur="' . self::CUR . '"', $fa );
		$this->assertStringContains( '≈ TST 25', $fa, 'base hint shows the base sale price during a sale' );

		$en = Display::html( $fresh, [ 'lang' => 'en', 'base_hint' => false ] );
		$this->assertStringContains( '<bdi>1,949,000</bdi>', $en );
		$this->assertStringContains( 'Toman', $en );
		$this->assertStringContains( '-17%', $en );
		$this->assertStringNotContains( 'price__base', $en );

		$ar = Display::html( $fresh, [ 'lang' => 'ar', 'base_hint' => false ] );
		$this->assertStringContains( '<bdi>١٬٩٤٩٬٠٠٠</bdi>', $ar );

		$amount = Display::html( 2340000, [ 'lang' => 'fa' ] );
		$this->assertStringContains( '<bdi>۲٬۳۴۰٬۰۰۰</bdi>', $amount );
		$this->assertStringNotContains( 'data-id', $amount );
		$this->assertStringNotContains( 'price__was', $amount );

		$this->assertSame( '', Display::html( 'not a price' ) );
		$this->assertSame( $fa, gadgetim_price( $fresh, [ 'lang' => 'fa', 'base_hint' => true ] ), 'gadgetim_price() delegates to Display::html()' );
	}

	public function test_display_price_filter_is_inert_outside_the_storefront(): void {
		$html = '<span class="amount">2,340,000</span>';
		$this->assertSame( $html, Display::filter_price_html( $html, null ), 'CLI is not a storefront render' );
	}

	// -------------------------------------------------------------- migration

	public function test_migration_converts_legacy_meta(): void {
		$product = $this->make_simple( 'GTM legacy', [], '1125000' );
		$id      = $product->get_id();
		update_post_meta( $id, Migration::OLD_REGULAR, '30' );
		update_post_meta( $id, Migration::OLD_SALE, '14.16666' );
		$this->assertContains( $id, Products::ids_with_legacy_meta() );

		$result = Migration::migrate_currency( 0, [ $id ] );
		$this->assertSame( 1, $result['migrated'], wp_json_encode( $result ) );
		$this->assertEmpty( $result['errors'] );

		$aed   = Currencies::get( Migration::CURRENCY );
		$fresh = $this->fresh( $id );
		$this->assertNotEmpty( $aed, 'AED exists after the migration' );
		$this->assertSame( Migration::CURRENCY, Meta::currency( $fresh ) );
		$this->assertSame( '30', Meta::base_regular( $fresh ) );
		$this->assertSame( '14.16666', Meta::base_sale( $fresh ), 'stored as entered' );
		$this->assertSame( '', (string) get_post_meta( $id, Migration::OLD_REGULAR, true ), 'old key removed' );
		$this->assertSame( '', (string) get_post_meta( $id, Migration::OLD_SALE, true ) );
		$this->assertSame( (string) Calculator::to_toman( '30', $aed ), $fresh->get_regular_price( 'edit' ) );
		$this->assertSame( (string) Calculator::to_toman( '14.16666', $aed ), $fresh->get_sale_price( 'edit' ) );
		$this->assertFalse( in_array( $id, Products::ids_with_legacy_meta(), true ) );
	}

	public function test_migration_skips_invalid_legacy_values(): void {
		$product = $this->make_simple( 'GTM legacy invalid', [], '900000' );
		$id      = $product->get_id();
		update_post_meta( $id, Migration::OLD_REGULAR, 'n/a' );

		$result = Migration::migrate_currency( 0, [ $id ] );
		$this->assertSame( 0, $result['migrated'] );
		$this->assertTrue( isset( $result['errors'][ $id ] ) );
		$fresh = $this->fresh( $id );
		$this->assertSame( '', Meta::currency( $fresh ) );
		$this->assertSame( '900000', $fresh->get_regular_price( 'edit' ) );
		$this->assertSame( 'n/a', (string) get_post_meta( $id, Migration::OLD_REGULAR, true ), 'old key kept for inspection' );
	}

	// ------------------------------------------------------------- currencies

	public function test_currency_crud_and_history(): void {
		$saved = Currencies::save( 'tsx', [ 'rate' => '۹۰٬۰۰۰', 'rounding_mode' => 'down', 'rounding_step' => '5000', 'enabled' => '1', 'label' => [ 'fa' => 'آزمون' ] ], 0 );
		$this->assertFalse( is_wp_error( $saved ) );
		$this->assertSame( 'TSX', $saved['code'] );
		$this->assertSame( 90000, $saved['rate'], 'Persian digits and separators accepted, stored as int' );
		$this->assertSame( 'down', $saved['rounding_mode'] );
		$this->assertSame( 5000, $saved['rounding_step'] );
		$this->assertTrue( $saved['enabled'] );
		$this->assertSame( 'آزمون', Currencies::label( 'TSX', 'fa' ) );
		$this->assertSame( 'آزمون', Currencies::label( 'TSX', 'en' ), 'falls back en → fa' );
		$this->assertSame( 'ZZZ', Currencies::label( [ 'code' => 'ZZZ' ], 'en' ), 'falls back to the code when no label exists' );

		$history = Currencies::history( 1 );
		$this->assertSame( 'TSX', $history[0]['code'] );
		$this->assertSame( null, $history[0]['old'] );
		$this->assertEquals( 90000, $history[0]['new'] );
		$this->assertFalse( $history[0]['applied'] );

		$this->assertTrue( is_wp_error( Currencies::save( 'TOOLONG', [ 'rate' => 1 ] ) ) );
		$this->assertSame( 'TSX 1.5', Currencies::format_amount( '1.50', $saved ) );
		$this->assertSame( 'TSX 12', Currencies::format_amount( '12', $saved ) );
		$this->assertSame( 'TSX 14.17', Currencies::format_amount( '14.16666', $saved ) );

		$this->assertTrue( Currencies::delete( 'TSX' ) );
		$this->assertFalse( Currencies::exists( 'TSX' ) );
		$this->assertTrue( is_wp_error( Currencies::delete( 'TSX' ) ) );

		$product = $this->make_simple( 'GTM in use', [ Meta::BASE_REGULAR => '1' ] );
		$blocked = Currencies::delete( self::CUR );
		$this->assertTrue( is_wp_error( $blocked ), 'a currency in use cannot be deleted' );
		$this->assertSame( 'gadgetim_currency_in_use', $blocked->get_error_code() );
		$this->assertSame( [ $product->get_id() ], Products::ids_with_currency( self::CUR ) );
	}

	// ---------------------------------------------------------------- helpers

	/**
	 * Creates and saves a simple product priced in TST (when $meta has a base price) or in fixed Toman.
	 *
	 * @param string $name          Title.
	 * @param array  $meta          Pricing meta (Meta::CURRENCY defaults to TST when a base price is given).
	 * @param string $regular_price Fixed Toman regular price.
	 */
	private function make_simple( string $name, array $meta = [], string $regular_price = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		if ( '' !== $regular_price ) {
			$product->set_regular_price( $regular_price );
		}
		if ( $meta && ! isset( $meta[ Meta::CURRENCY ] ) ) {
			$meta[ Meta::CURRENCY ] = self::CUR;
		}
		foreach ( $meta as $key => $value ) {
			$product->update_meta_data( $key, $value );
		}
		$product->save();
		$this->created[] = $product->get_id();
		return $product;
	}

	/**
	 * Creates a variable product with one variation priced in TST.
	 *
	 * @return array{0:WC_Product_Variable,1:WC_Product_Variation}
	 */
	private function make_variable( array $variation_meta ): array {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'Size' );
		$attribute->set_options( [ 'S', 'M' ] );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new WC_Product_Variable();
		$parent->set_name( 'GTM test variable' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( [ $attribute ] );
		$parent->save();
		$this->created[] = $parent->get_id();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_status( 'publish' );
		$variation->set_attributes( [ 'size' => 'S' ] );
		if ( ! isset( $variation_meta[ Meta::CURRENCY ] ) ) {
			$variation_meta[ Meta::CURRENCY ] = self::CUR;
		}
		foreach ( $variation_meta as $key => $value ) {
			$variation->update_meta_data( $key, $value );
		}
		$variation->save();
		$this->created[] = $variation->get_id();

		return [ $parent, $variation ];
	}

	private function fresh( int $id ): WC_Product {
		clean_post_cache( $id );
		wp_cache_delete( $id, 'post_meta' );
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product ) {
			$this->fail( 'product ' . $id . ' disappeared' );
		}
		return $product;
	}

	private function lookup( int $id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT min_price, max_price, onsale FROM {$wpdb->wc_product_meta_lookup} WHERE product_id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? $row : [];
	}

	private function meta_count( int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private function delete_created(): void {
		// Variations first (higher IDs), then parents. get_post() guards against WooCommerce's
		// per-request type cache handing out an object for an already deleted ID.
		foreach ( array_reverse( array_unique( $this->created ) ) as $id ) {
			if ( ! get_post( $id ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( $product instanceof WC_Product ) {
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $child_id ) {
						$child = wc_get_product( $child_id );
						if ( $child instanceof WC_Product ) {
							$child->delete( true );
						}
					}
				}
				$product->delete( true );
			}
		}
		$this->created = [];
	}
}

exit( ( new MaterializerTest() )->run() );
