<?php
/**
 * Product queries of the pricing module (which products use which currency, stale prices).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

/**
 * Direct, prepared SQL on wp_postmeta: the lists are small (107 products at import), the
 * queries run from admin actions, cron and the reprice job, never on the storefront.
 */
final class Products {

	/**
	 * IDs of every product and variation priced in a currency (any status except auto-draft).
	 *
	 * @param string $code Currency code, or '' for any currency.
	 * @return int[]
	 */
	public static function ids_with_currency( string $code = '' ): array {
		global $wpdb;

		$code = Currencies::normalize_code( $code );
		if ( '' === $code ) {
			$sql = $wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value <> ''
				   AND p.post_type IN ( 'product', 'product_variation' ) AND p.post_status <> 'auto-draft'
				 ORDER BY p.post_type ASC, pm.post_id ASC",
				Meta::CURRENCY
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %s
				   AND p.post_type IN ( 'product', 'product_variation' ) AND p.post_status <> 'auto-draft'
				 ORDER BY p.post_type ASC, pm.post_id ASC",
				Meta::CURRENCY,
				$code
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return array_map( 'intval', (array) $wpdb->get_col( $sql ) );
	}

	/**
	 * Number of products and variations priced in a currency.
	 */
	public static function count_with_currency( string $code ): int {
		$code = Currencies::normalize_code( $code );
		if ( '' === $code ) {
			return 0;
		}
		return self::counts_by_currency()[ $code ] ?? 0;
	}

	/**
	 * [ code => count ] for every currency in use.
	 *
	 * @return array<string,int>
	 */
	public static function counts_by_currency(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS code, COUNT(*) AS total FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value <> ''
				   AND p.post_type IN ( 'product', 'product_variation' ) AND p.post_status <> 'auto-draft'
				 GROUP BY pm.meta_value",
				Meta::CURRENCY
			),
			ARRAY_A
		);
		$out  = [];
		foreach ( (array) $rows as $row ) {
			$code = Currencies::normalize_code( (string) ( $row['code'] ?? '' ) );
			if ( '' !== $code ) {
				$out[ $code ] = ( $out[ $code ] ?? 0 ) + (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Pricing meta of several products in one query: [ id => [ meta_key => value ] ].
	 *
	 * @param int[] $ids Post IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function price_meta( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return [];
		}
		$keys         = array_merge( Meta::ALL, [ '_regular_price', '_sale_price', '_price' ] );
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$id_list      = implode( ',', $ids ); // Integers only (cast above).

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list holds cast integers, $placeholders is a list of %s.
		$sql  = $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ( {$id_list} ) AND meta_key IN ( {$placeholders} )", ...$keys );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$out  = array_fill_keys( $ids, [] );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['post_id'] ][ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}
		return $out;
	}

	/**
	 * Currency products whose stored Toman prices no longer match the base price × applied rate
	 * (architecture §4.3 daily check). Products of a disabled currency keep their last prices by
	 * design and are not listed.
	 *
	 * @return array<int,string> [ id => 'unknown-currency' | 'rate' | 'regular' | 'sale' ].
	 */
	public static function mismatched(): array {
		$ids = self::ids_with_currency();
		if ( ! $ids ) {
			return [];
		}
		$out = [];
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$meta = self::price_meta( $chunk );
			foreach ( $chunk as $id ) {
				$values   = $meta[ $id ] ?? [];
				$code     = Currencies::normalize_code( $values[ Meta::CURRENCY ] ?? '' );
				$currency = Currencies::get( $code );
				if ( null === $currency ) {
					$out[ $id ] = 'unknown-currency';
					continue;
				}
				if ( empty( $currency['enabled'] ) ) {
					continue;
				}
				// Compare with what a save would write: the applied snapshot, not a pending rate.
				$currency = Currencies::applied( $currency );
				$regular  = Calculator::decimal( $values[ Meta::BASE_REGULAR ] ?? '' );
				if ( '' === $regular ) {
					continue;
				}
				$rate_used = Calculator::decimal( $values[ Meta::RATE_USED ] ?? '' );
				if ( '' === $rate_used || abs( (float) $rate_used - (float) $currency['rate'] ) > 0.000001 ) {
					$out[ $id ] = 'rate';
					continue;
				}
				$expected_regular = Calculator::to_toman( $regular, $currency );
				if ( (int) round( (float) ( $values['_regular_price'] ?? 0 ) ) !== $expected_regular ) {
					$out[ $id ] = 'regular';
					continue;
				}
				$sale          = Calculator::decimal( $values[ Meta::BASE_SALE ] ?? '' );
				$expected_sale = '' === $sale ? 0 : Calculator::to_toman( $sale, $currency );
				if ( $expected_sale >= $expected_regular ) {
					$expected_sale = 0;
				}
				$stored_sale = (int) round( (float) ( $values['_sale_price'] ?? 0 ) );
				if ( $stored_sale !== $expected_sale ) {
					$out[ $id ] = 'sale';
				}
			}
		}
		return $out;
	}

	/**
	 * IDs of products still carrying the old plugin's _gadgetim_currency_regular_price meta.
	 *
	 * @return int[]
	 */
	public static function ids_with_legacy_meta(): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND p.post_type IN ( 'product', 'product_variation' ) AND p.post_status <> 'auto-draft'
			 ORDER BY pm.post_id ASC",
			Migration::OLD_REGULAR
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return array_map( 'intval', (array) $wpdb->get_col( $sql ) );
	}

	/**
	 * Number of products still carrying the old plugin's currency meta.
	 */
	public static function count_with_legacy_meta(): int {
		return count( self::ids_with_legacy_meta() );
	}
}
