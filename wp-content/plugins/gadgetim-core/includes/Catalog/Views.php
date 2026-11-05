<?php
/**
 * Sampled view counting: POST gadgetim/v1/view/{id} beacon, per-product hourly cap, de-duplication
 * in the object cache when one exists, rate limit, admin switch (architecture §6, §8.1 #12).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Html;
use Gadgetim\Core\Support\RateLimit;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * One in N product page views sends a beacon; the server adds N to `_gtm_views`
 * (settings `views.enabled`, `views.sample`).
 */
final class Views {

	public const NAMESPACE = 'gadgetim/v1';

	public const ROUTE = '/view/(?P<id>\d+)';

	/**
	 * Legacy per-client de-duplication transients (no longer written; uninstall still removes them).
	 */
	public const TRANSIENT_PREFIX = 'gadgetim_view_';

	public const CACHE_GROUP = 'gadgetim_views';

	public const RATE_LIMIT  = 30;
	public const RATE_WINDOW = 60;

	/**
	 * Default maximum of counted beacons per product per hour.
	 */
	public const HOURLY_CAP = 60;

	/**
	 * Counted beacons allowed per product per hour (filter gadgetim_views_hourly_cap).
	 */
	public static function hourly_cap(): int {
		/**
		 * Filters how many sampled views one product may count per hour.
		 *
		 * @param int $cap Default 60 (i.e. 60 × views.sample page views).
		 */
		return max( 1, (int) apply_filters( 'gadgetim_views_hourly_cap', self::HOURLY_CAP ) );
	}

	/**
	 * Hooks: REST route and the storefront beacon.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_action( 'wp_footer', [ self::class, 'print_beacon' ], 20 );
	}

	/**
	 * Whether view counting is switched on (Settings → Catalog).
	 */
	public static function enabled(): bool {
		if ( ! class_exists( Settings::class ) ) {
			return true;
		}
		return (bool) Settings::get( 'views.enabled', true );
	}

	/**
	 * Sampling rate N (one beacon per N views; the counter grows by N).
	 */
	public static function sample(): int {
		$sample = class_exists( Settings::class ) ? (int) Settings::get( 'views.sample', 5 ) : 5;
		return max( 1, min( 100, $sample ) );
	}

	/**
	 * Beacon URL for a product.
	 */
	public static function endpoint( int $product_id ): string {
		return rest_url( self::NAMESPACE . '/view/' . absint( $product_id ) );
	}

	/**
	 * rest_api_init: the beacon route (skipped when the Rest module ships its own Views controller).
	 */
	public static function register_routes(): void {
		if ( class_exists( 'Gadgetim\Core\Rest\Views' ) ) {
			return;
		}
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value > 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * POST handler: validates, rate-limits, records. Always `Cache-Control: no-store`.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! self::enabled() ) {
			return new WP_Error( 'gadgetim_views_disabled', __( 'View counting is switched off.', 'gadgetim-core' ), [ 'status' => 404 ] );
		}
		$id = absint( $request->get_param( 'id' ) );
		if ( ! self::is_countable( $id ) ) {
			return new WP_Error( 'gadgetim_views_not_found', __( 'Unknown product.', 'gadgetim-core' ), [ 'status' => 404 ] );
		}
		if ( ! RateLimit::allow( 'views', self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			$error = new WP_Error( 'gadgetim_views_rate_limited', __( 'Too many requests.', 'gadgetim-core' ), [ 'status' => 429 ] );
			return $error;
		}

		$counted  = self::record( $id );
		$response = new WP_REST_Response(
			[
				'ok'      => true,
				'counted' => $counted,
			],
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Records one sampled view and adds N.
	 *
	 * Abuse brakes, none of which writes a row per client:
	 * - a per-product hourly cap of counted beacons (shared bucket in RateLimit's hourly table);
	 * - with a persistent object cache, 24 h de-duplication per (client network, product) in the
	 *   cache. Without one there is no per-client de-duplication (it would cost two wp_options rows
	 *   per client and product); sampling, the per-client rate limit and the cap bound the counter.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $ip         Client IP (request IP by default).
	 * @return bool Whether the view was counted.
	 */
	public static function record( int $product_id, ?string $ip = null ): bool {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return false;
		}

		$dedupe_key = '';
		if ( wp_using_ext_object_cache() ) {
			$dedupe_key = RateLimit::hash( 'view:' . $product_id, $ip ?? RateLimit::client_ip() );
			if ( false !== wp_cache_get( $dedupe_key, self::CACHE_GROUP ) ) {
				return false;
			}
		}

		if ( ! RateLimit::allow_shared( 'view:' . $product_id, self::hourly_cap(), HOUR_IN_SECONDS ) ) {
			return false;
		}
		if ( '' !== $dedupe_key ) {
			wp_cache_set( $dedupe_key, 1, self::CACHE_GROUP, DAY_IN_SECONDS );
		}

		$views = (int) get_post_meta( $product_id, ProductExtras::META_VIEWS, true ) + self::sample();
		update_post_meta( $product_id, ProductExtras::META_VIEWS, $views );

		/**
		 * Fires after a sampled product view was counted.
		 *
		 * @param int $product_id Product ID.
		 * @param int $views      New counter value.
		 */
		do_action( 'gadgetim_product_viewed', $product_id, $views );
		return true;
	}

	/**
	 * Whether the ID is a published, visible product.
	 */
	public static function is_countable( int $product_id ): bool {
		$post = get_post( $product_id );
		return $post instanceof \WP_Post && 'product' === $post->post_type && 'publish' === $post->post_status;
	}

	/**
	 * wp_footer: tiny inline beacon on single product pages. The theme may replace it
	 * (filter gadgetim_views_inline_script → false) and read the JSON config itself.
	 */
	public static function print_beacon(): void {
		if ( ! self::enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product_id = (int) get_queried_object_id();
		if ( ! $product_id ) {
			return;
		}

		/**
		 * Filters whether the plugin prints its own view beacon script on product pages.
		 *
		 * @param bool $print      Default true.
		 * @param int  $product_id Product ID.
		 */
		if ( ! apply_filters( 'gadgetim_views_inline_script', true, $product_id ) ) {
			return;
		}

		$config = [
			'url'    => self::endpoint( $product_id ),
			'sample' => self::sample(),
		];
		echo Html::json_script( 'gadgetim-views-config', $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built with the §5.10 flags.
		echo '<script>' . self::script() . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static script, no dynamic parts.
	}

	/**
	 * The beacon script (static; configuration comes from the JSON block).
	 */
	public static function script(): string {
		return '(function(){var c=document.getElementById("gadgetim-views-config");if(!c){return;}var d;try{d=JSON.parse(c.textContent);}catch(e){return;}'
			. 'if(!d||!d.url||Math.random()*(d.sample||1)>=1){return;}'
			. 'var send=function(){try{if(navigator.sendBeacon&&navigator.sendBeacon(d.url,new Blob(["{}"],{type:"text/plain"}))){return;}'
			. 'if(window.fetch){fetch(d.url,{method:"POST",keepalive:true,credentials:"omit",headers:{"Content-Type":"text/plain"},body:"{}"}).catch(function(){});}}catch(e){}};'
			. 'if("requestIdleCallback" in window){requestIdleCallback(send,{timeout:3000});}else{setTimeout(send,1500);}})();';
	}
}
