<?php
/**
 * Instant search endpoint GET /wp-json/gadgetim/v1/search (architecture §3.6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Rest;

use Gadgetim\Core\ContactMode\ContactMode;
use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Search as Index;
use Gadgetim\Core\Support\Normalize;
use Gadgetim\Core\Support\RateLimit;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public, GET only. q capped at 64 characters after normalisation (fewer than 2 -> empty),
 * no transient per query, WP_Query with fields => ids / posts_per_page => 8 / no_found_rows,
 * Cache-Control: public, max-age=300, token bucket of 30 requests per minute per hashed IP.
 * Steps aside when the Rest module ships its own Gadgetim\Core\Rest\Search.
 */
final class Search {

	public const NAMESPACE = 'gadgetim/v1';

	public const ROUTE = '/search';

	public const LIMIT = 8;

	public const RATE_LIMIT = 30;

	public const RATE_WINDOW = 60;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * rest_api_init.
	 */
	public static function register_routes(): void {
		if ( class_exists( 'Gadgetim\Core\Rest\Search' ) ) {
			return;
		}
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ self::class, 'handle' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'q'    => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => [ self::class, 'sanitize_query' ],
						],
						'lang' => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => [ self::class, 'validate_lang' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Normalised, capped query string.
	 */
	public static function sanitize_query( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$q = Normalize::text( wp_strip_all_tags( (string) $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $q, 0, 64, 'UTF-8' ) : substr( $q, 0, 64 );
	}

	/**
	 * lang must be a configured language.
	 */
	public static function validate_lang( mixed $value ): bool {
		return ! is_string( $value ) || '' === $value || Languages::exists( $value );
	}

	/**
	 * GET handler.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! RateLimit::allow( 'search', self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			$retry    = max( 1, RateLimit::retry_after( 'search', self::RATE_LIMIT, self::RATE_WINDOW ) );
			$response = new WP_REST_Response(
				[
					'code'    => 'gadgetim_rate_limited',
					'message' => __( 'Too many searches. Please wait a moment.', 'gadgetim-core' ),
					'data'    => [ 'status' => 429 ],
				],
				429
			);
			$response->header( 'Retry-After', (string) $retry );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		$q    = (string) $request->get_param( 'q' );
		$lang = (string) $request->get_param( 'lang' );
		if ( '' === $lang || ! Languages::exists( $lang ) ) {
			$lang = Languages::current();
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $q, 'UTF-8' ) : strlen( $q );
		$items  = $length < 2 ? [] : Languages::with_lang( $lang, static fn(): array => self::query( $q, $lang ) );

		$response = new WP_REST_Response(
			[
				'q'     => $q,
				'lang'  => $lang,
				'items' => $items,
			],
			200
		);
		$response->header( 'Cache-Control', 'public, max-age=300' );
		// The language comes from ?lang (part of the URL), the X-Gadgetim-Lang header or the path.
		$response->header( 'Vary', 'X-Gadgetim-Lang' );
		return $response;
	}

	/**
	 * Runs the product query inside the language context and shapes the items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function query( string $q, string $lang ): array {
		$args = [
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => self::LIMIT,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			's'                   => $q,
			'gadgetim_search'     => $lang,
			'orderby'             => 'relevance',
		];
		if ( taxonomy_exists( 'product_visibility' ) ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => [ 'exclude-from-search' ],
					'operator' => 'NOT IN',
				],
			];
		}
		$query = new WP_Query( $args );
		$items = [];
		foreach ( $query->posts as $id ) {
			$id   = (int) $id;
			$item = [
				'id'         => $id,
				'title'      => PostTranslations::title( $id, $lang ),
				'subtitle'   => self::subtitle( $id, $lang ),
				'url'        => (string) get_permalink( $id ),
				'thumb'      => (string) ( get_the_post_thumbnail_url( $id, 'woocommerce_thumbnail' ) ?: '' ),
				// Price HTML (gadgetim_price(), already escaped); empty when contact mode hides it.
				'price_html' => '',
			];
			if ( function_exists( 'wc_get_product' ) && function_exists( 'gadgetim_price' ) ) {
				$product = wc_get_product( $id );
				if ( $product && self::price_visible( $product ) ) {
					$item['price_html'] = gadgetim_price(
						$product,
						[
							'lang' => $lang,
							'tag'  => 'span',
						]
					);
				}
			}
			$items[] = $item;
		}
		return $items;
	}

	/**
	 * Whether a product's price may be exposed: not in contact mode, or contact mode with the
	 * price still shown (ContactMode::offers_enabled()).
	 */
	private static function price_visible( \WC_Product $product ): bool {
		if ( class_exists( ContactMode::class ) ) {
			return ContactMode::offers_enabled( $product );
		}
		return true;
	}

	/**
	 * Translated subtitle, else the Persian one.
	 */
	private static function subtitle( int $id, string $lang ): string {
		$subtitle = PostTranslations::get( $id, 'subtitle', $lang );
		if ( '' === $subtitle ) {
			$subtitle = (string) get_post_meta( $id, '_gtm_subtitle', true );
		}
		return $subtitle;
	}
}
