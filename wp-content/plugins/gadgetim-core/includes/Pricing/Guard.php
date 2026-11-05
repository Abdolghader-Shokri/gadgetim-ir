<?php
/**
 * Guards the base-price meta against writes that bypass the WooCommerce CRUD (architecture §4.3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

use Gadgetim\Core\Settings\Admin\Notices;
use Gadgetim\Core\Support\Context;
use WC_Product;

/**
 * Three layers:
 *  1. register_post_meta() with show_in_rest => false for the pricing keys, so wp/v2 never
 *     writes them, plus sanitizers that run on every add/update (CRUD included).
 *  2. updated/added/deleted_post_meta: a write to a materialiser input from outside a
 *     WC_Data::save() schedules a CRUD save of that product on shutdown.
 *  3. A daily cron lists products whose stored prices no longer match base × rate and surfaces
 *     them as a persistent admin notice with a "Fix now" link.
 */
final class Guard {

	public const CRON_HOOK  = 'gadgetim_pricing_daily_check';
	public const FIX_ACTION = 'gadgetim_pricing_fix';
	public const NOTICE_ID  = 'pricing-mismatch';

	private static int $depth = 0;

	/**
	 * Products inside a WC_Data::save() right now, keyed by spl_object_id(). The ID is read from
	 * the object at check time, so a product being created matches its own new ID (assigned by
	 * the data store before its meta is written) and nothing else.
	 *
	 * @var array<int,WC_Product>
	 */
	private static array $saving = [];

	/** @var array<int,true> Products to re-save on shutdown. */
	private static array $pending = [];

	/** @var array<int,true> Posts inside wp_delete_post() right now (their meta deletions are not writes to guard). */
	private static array $deleting = [];

	private static bool $shutdown_hooked = false;

	/**
	 * before_delete_post: meta deletions of a post being removed must not schedule a re-save.
	 *
	 * @param int|string $post_id Post ID.
	 */
	public static function on_before_delete_post( $post_id ): void {
		self::$deleting[ (int) $post_id ] = true;
		unset( self::$pending[ (int) $post_id ] );
	}

	/**
	 * after_delete_post: the post is gone; forget it entirely.
	 *
	 * @param int|string $post_id Post ID.
	 */
	public static function on_after_delete_post( $post_id ): void {
		unset( self::$deleting[ (int) $post_id ], self::$pending[ (int) $post_id ] );
	}

	/**
	 * init: registers the five keys for both post types (sanitizers + auth, hidden from wp/v2).
	 */
	public static function register_meta(): void {
		foreach ( Meta::POST_TYPES as $type ) {
			register_post_meta(
				$type,
				Meta::CURRENCY,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => [ self::class, 'sanitize_currency_meta' ],
					'auth_callback'     => [ self::class, 'auth_meta' ],
				]
			);
			foreach ( [ Meta::BASE_REGULAR, Meta::BASE_SALE, Meta::RATE_USED ] as $key ) {
				register_post_meta(
					$type,
					$key,
					[
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => [ self::class, 'sanitize_decimal_meta' ],
						'auth_callback'     => [ self::class, 'auth_meta' ],
					]
				);
			}
			register_post_meta(
				$type,
				Meta::PRICED_AT,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => [ self::class, 'sanitize_int_meta' ],
					'auth_callback'     => [ self::class, 'auth_meta' ],
				]
			);
		}
	}

	/**
	 * _gtm_price_currency: a known currency code or ''.
	 */
	public static function sanitize_currency_meta( mixed $value ): string {
		$code = Currencies::normalize_code( is_scalar( $value ) ? (string) $value : '' );
		return Currencies::exists( $code ) ? $code : '';
	}

	/**
	 * _gtm_base_*_price / _gtm_rate_used: non-negative decimal string or ''.
	 */
	public static function sanitize_decimal_meta( mixed $value ): string {
		return Calculator::decimal( $value );
	}

	/**
	 * _gtm_priced_at: non-negative integer string.
	 */
	public static function sanitize_int_meta( mixed $value ): string {
		return is_scalar( $value ) ? (string) absint( $value ) : '0';
	}

	/**
	 * auth_callback: whoever may edit the product may edit its pricing meta.
	 *
	 * @param bool   $allowed   Default.
	 * @param string $meta_key  Key.
	 * @param int    $object_id Post ID.
	 * @param int    $user_id   User ID.
	 */
	public static function auth_meta( $allowed, $meta_key, $object_id, $user_id ): bool {
		return user_can( (int) $user_id, 'edit_post', (int) $object_id );
	}

	/**
	 * woocommerce_before_product_object_save (priority 1): marks the object as saving.
	 *
	 * @param mixed $product WC_Product.
	 */
	public static function on_before_save( $product ): void {
		++self::$depth;
		if ( $product instanceof WC_Product ) {
			self::$saving[ spl_object_id( $product ) ] = $product;
		}
	}

	/**
	 * woocommerce_after_product_object_save (priority 999): clears the mark.
	 *
	 * @param mixed $product WC_Product.
	 */
	public static function on_after_save( $product ): void {
		self::$depth = max( 0, self::$depth - 1 );
		if ( $product instanceof WC_Product ) {
			unset( self::$saving[ spl_object_id( $product ) ] );
		}
		if ( 0 === self::$depth ) {
			self::$saving = [];
		}
	}

	/**
	 * Whether a meta write for this object happens inside a WC_Data::save().
	 */
	public static function in_save( int $object_id ): bool {
		if ( self::$depth <= 0 || $object_id <= 0 ) {
			return false;
		}
		foreach ( self::$saving as $product ) {
			if ( $product->get_id() === $object_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * updated_post_meta / added_post_meta.
	 *
	 * @param int|int[] $meta_id   Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Key.
	 */
	public static function on_meta_change( $meta_id, $object_id, $meta_key ): void {
		self::maybe_schedule( (int) $object_id, (string) $meta_key );
	}

	/**
	 * deleted_post_meta.
	 *
	 * @param int[]  $meta_ids  Meta IDs.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Key.
	 */
	public static function on_meta_deleted( $meta_ids, $object_id, $meta_key ): void {
		self::maybe_schedule( (int) $object_id, (string) $meta_key );
	}

	/**
	 * Re-saves the scheduled products through the CRUD (shutdown; callable directly by tests).
	 *
	 * @return int[] IDs that were saved.
	 */
	public static function flush(): array {
		$ids           = array_keys( self::$pending );
		self::$pending = [];
		$saved         = [];
		if ( ! $ids || ! function_exists( 'wc_get_product' ) ) {
			return $saved;
		}
		Context::with_suspended(
			static function () use ( $ids, &$saved ): void {
				foreach ( $ids as $id ) {
					// The post must still exist: WooCommerce's per-request product-type cache can hand out an
					// object for a deleted ID, and saving it would recreate orphan meta and a lookup row.
					$post = get_post( (int) $id );
					if ( ! $post || ! in_array( $post->post_type, Meta::POST_TYPES, true ) ) {
						continue;
					}
					$product = wc_get_product( (int) $id );
					if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
						continue;
					}
					$product->save();
					$saved[] = (int) $id;
				}
			}
		);

		/**
		 * Fires after the guard re-saved products whose pricing meta was written outside the CRUD.
		 *
		 * @param int[] $saved Product IDs.
		 */
		do_action( 'gadgetim_pricing_guard_resaved', $saved );

		return $saved;
	}

	/**
	 * Products waiting for a shutdown save (tests).
	 *
	 * @return int[]
	 */
	public static function pending(): array {
		return array_keys( self::$pending );
	}

	/**
	 * Schedules the daily mismatch check.
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the daily check.
	 */
	public static function unschedule_cron(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * admin_init: re-arms the cron when it went missing (activation before this module existed).
	 */
	public static function ensure_cron(): void {
		self::schedule_cron();
	}

	/**
	 * Daily cron: stale prices → persistent notice with a "Fix now" link; none → notice cleared.
	 *
	 * @return array<int,string> The mismatches found.
	 */
	public static function daily_check(): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [];
		}
		$bad = Products::mismatched();
		if ( ! class_exists( Notices::class ) ) {
			return $bad;
		}
		if ( ! $bad ) {
			Notices::clear( self::NOTICE_ID );
			return $bad;
		}
		$count = count( $bad );
		Notices::add(
			self::NOTICE_ID,
			sprintf(
				/* translators: %s: number of products */
				_n(
					'%s product has a stale Toman price (the rate changed or its base price was edited outside WooCommerce). "Fix now" recomputes it from the base currency.',
					'%s products have stale Toman prices (the rate changed or their base prices were edited outside WooCommerce). "Fix now" recomputes them from the base currency.',
					$count,
					'gadgetim-core'
				),
				number_format_i18n( $count )
			),
			'warning',
			[
				'capability'  => 'manage_options',
				'dismissible' => true,
				'link'        => [
					'url'   => self::fix_url(),
					'label' => __( 'Fix now', 'gadgetim-core' ),
				],
			]
		);
		return $bad;
	}

	/**
	 * Nonce-signed admin-post URL of the "Fix now" action.
	 */
	public static function fix_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::FIX_ACTION ), self::FIX_ACTION );
	}

	/**
	 * admin-post gadgetim_pricing_fix: recomputes every mismatched product (manage_options + nonce).
	 */
	public static function handle_fix(): void {
		check_admin_referer( self::FIX_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'gadgetim-core' ), 403 );
		}
		$ids = array_keys( Products::mismatched() );
		$job = $ids ? Reprice::reprice_ids( $ids, get_current_user_id() ) : '';
		if ( class_exists( Notices::class ) ) {
			Notices::clear( self::NOTICE_ID );
		}
		$args = is_wp_error( $job ) ? [ 'gtm_error' => $job->get_error_code() ] : ( '' !== $job ? [ 'job' => $job ] : [ 'gtm_fixed' => '0' ] );
		if ( class_exists( Admin\CurrenciesPage::class ) ) {
			wp_safe_redirect( Admin\CurrenciesPage::url( $args ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=gadgetim' ) );
		}
		exit;
	}

	private static function maybe_schedule( int $object_id, string $meta_key ): void {
		if ( $object_id <= 0 || ! in_array( $meta_key, Meta::GUARDED, true ) ) {
			return;
		}
		if ( self::in_save( $object_id ) || isset( self::$deleting[ $object_id ] ) ) {
			return;
		}
		if ( ! in_array( (string) get_post_type( $object_id ), Meta::POST_TYPES, true ) ) {
			return;
		}
		self::$pending[ $object_id ] = true;
		if ( ! self::$shutdown_hooked ) {
			self::$shutdown_hooked = true;
			add_action( 'shutdown', [ self::class, 'flush' ], 5 );
		}
	}
}
