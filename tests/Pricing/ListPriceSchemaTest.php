<?php
/**
 * Seo\JsonLd::list_price_as_shown(): the product markup keeps a ListPrice only where the page
 * strikes the old price through (deep check SCH.3-03, 2026-11-20).
 *
 * Run: "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/Pricing/ListPriceSchemaTest.php
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

use Gadgetim\Core\Pricing\Display;
use Gadgetim\Core\Seo\JsonLd;
use Gadgetim\Tests\Pricing\TestCase;

/**
 * Offers as WooCommerce builds them for a simple product on sale.
 */
final class ListPriceSchemaTest extends TestCase {

	private function offer( float $now, float $was ): array {
		return [
			'@type'              => 'Offer',
			'priceSpecification' => [
				[
					'@type'         => 'UnitPriceSpecification',
					'price'         => (string) $now,
					'priceCurrency' => 'IRR',
				],
				[
					'@type'         => 'UnitPriceSpecification',
					'price'         => (string) $was,
					'priceCurrency' => 'IRR',
					'priceType'     => 'https://schema.org/ListPrice',
				],
			],
			'price'              => (string) $now,
			'priceCurrency'      => 'IRR',
		];
	}

	private function strike(): int {
		return (int) Display::thresholds()['strike'];
	}

	public function test_small_discount_drops_the_list_price(): void {
		$was   = 1000000.0;
		$now   = $was * ( 100 - max( 1, $this->strike() - 4 ) ) / 100;
		$out   = JsonLd::list_price_as_shown( [ $this->offer( $now, $was ) ] );
		$specs = $out[0]['priceSpecification'];
		$this->assertSame( 1, count( $specs ), 'only the sale price is left' );
		$this->assertSame( (string) $now, $specs[0]['price'] );
		$this->assertSame( (string) $now, $out[0]['price'], 'the offer price is untouched' );
	}

	public function test_discount_at_the_threshold_keeps_the_list_price(): void {
		$was = 1000000.0;
		$now = $was * ( 100 - $this->strike() ) / 100;
		$out = JsonLd::list_price_as_shown( [ $this->offer( $now, $was ) ] );
		$this->assertSame( 2, count( $out[0]['priceSpecification'] ), 'the page strikes the old price, so the markup keeps it' );
	}

	public function test_offers_without_a_list_price_are_untouched(): void {
		$plain = [
			[
				'@type'         => 'Offer',
				'price'         => '500000',
				'priceCurrency' => 'IRR',
			],
			[
				'@type'              => 'AggregateOffer',
				'lowPrice'           => '100',
				'highPrice'          => '200',
				'priceSpecification' => [
					[
						'@type' => 'UnitPriceSpecification',
						'price' => '100',
					],
				],
			],
		];
		$this->assertSame( $plain, JsonLd::list_price_as_shown( $plain ) );
		$this->assertSame( [], JsonLd::list_price_as_shown( [] ) );
	}
}

exit( ( new ListPriceSchemaTest() )->run() );
