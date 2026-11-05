<?php
/**
 * Translated slugs (§3.4): uniqueness, native-first resolution, /en/shop/, outbound links, 301s.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

namespace Gadgetim\Tests\I18n;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\I18n\Visibility;

/**
 * Fixtures use the gtm-test- prefix and are removed after each test.
 */
final class SlugsTest extends TestCase {

	public function testTranslatedSlugEqualToAnotherNativeSlugIsRejected(): void {
		$a = $this->createProduct( 'شارژر تست الف', 'gtm-test-native-a' );
		$b = $this->createProduct( 'شارژر تست ب', 'gtm-test-native-b' );

		$this->assertSame( 'gtm-test-native-a-2', Slugs::validate( 'gtm-test-native-a', 'en', 'product', $b ), 'another product owns that native slug' );
		$this->assertSame( 'gtm-test-native-b', Slugs::validate( 'gtm-test-native-b', 'en', 'product', $b ), 'own native slug is fine' );
		$this->assertSame( 'gtm-test-free', Slugs::validate( 'gtm-test-free', 'en', 'product', $b ), 'free slug' );
		$this->assertSame( 'gtm-test-free', Slugs::validate( ' GTM Test Free ', 'en', 'product', $b ), 'sanitised' );

		update_post_meta( $a, '_gtm_slug_en', 'gtm-test-charger' );
		$this->assertSame( 'gtm-test-charger-2', Slugs::validate( 'gtm-test-charger', 'ar', 'product', $b ), 'translated slug of another object, any language' );
		$this->assertSame( 'gtm-test-charger', Slugs::validate( 'gtm-test-charger', 'ar', 'product', $a ), 'same object may reuse its own translated slug in another language' );

		update_post_meta( $b, '_gtm_slug_en_prev', [ 'gtm-test-charger-old' ] );
		$this->assertSame( 'gtm-test-charger-old', Slugs::validate( 'gtm-test-charger-old', 'en', 'product', $a ), 'previous slugs do not block' );
	}

	public function testWooCommercePageAndEndpointSlugsAreReserved(): void {
		$page = $this->createPage( 'صفحه تست', 'gtm-test-page' );
		$cart = (string) get_post_field( 'post_name', wc_get_page_id( 'cart' ), 'raw' );
		$this->assertNotEmpty( $cart, 'Local has a cart page' );

		$this->assertSame( $cart . '-2', Slugs::validate( $cart, 'en', 'page', $page ), 'cart page slug reserved' );
		$this->assertSame( 'orders-2', Slugs::validate( 'orders', 'en', 'page', $page ), 'account endpoint reserved' );
		$this->assertSame( 'en-2', Slugs::validate( 'en', 'ar', 'page', $page ), 'language prefix reserved' );
		$this->assertSame( 'product-2', Slugs::validate( 'product', 'en', 'page', $page ), 'WooCommerce base reserved' );
		$this->assertTrue( Slugs::is_wc_page( (int) wc_get_page_id( 'shop' ) ), 'shop page recognised' );
		$this->assertFalse( Slugs::is_wc_page( $page ), 'ordinary page is not a WooCommerce page' );
	}

	public function testTermSlugUniqueness(): void {
		$a = $this->createTerm( 'product_cat', 'دستهٴ تست الف', 'gtm-test-cat-a' );
		$b = $this->createTerm( 'product_cat', 'دستهٴ تست ب', 'gtm-test-cat-b' );
		$this->assertSame( 'gtm-test-cat-a-2', Slugs::validate( 'gtm-test-cat-a', 'en', 'product_cat', $b, 'term' ), 'native term slug of another term' );
		update_term_meta( $a, '_gtm_slug_en', 'gtm-test-chargers' );
		$this->assertSame( 'gtm-test-chargers-2', Slugs::validate( 'gtm-test-chargers', 'ar', 'product_cat', $b, 'term' ), 'translated term slug of another term' );
		$this->assertSame( 'gtm-test-chargers', Slugs::validate( 'gtm-test-chargers', 'en', 'product_tag', $b, 'term' ), 'other taxonomy: no collision' );
	}

	public function testInboundResolutionIsNativeFirst(): void {
		$a      = $this->createProduct( 'شارژر تست الف', 'gtm-test-native-a', [ '_gtm_slug_en' => 'gtm-test-charger', '_gtm_status_en' => 'published' ] );
		$shared = $this->createProduct( 'شارژر تست مشترک', 'gtm-test-shared' );
		$legacy = $this->createProduct( 'شارژر تست قدیمی', 'gtm-test-legacy', [ '_gtm_slug_en' => 'gtm-test-shared' ] ); // legacy collision written around validation
		Slugs::invalidate_all();

		$this->assertSame( 'gtm-test-native-a', Slugs::native_post_path( 'gtm-test-charger', 'en', 'product' ), 'translated -> native' );
		$this->assertSame( 'gtm-test-native-a', Slugs::native_post_path( 'gtm-test-native-a', 'en', 'product' ), 'native stays native' );
		$this->assertSame( 'gtm-test-shared', Slugs::native_post_path( 'gtm-test-shared', 'en', 'product' ), 'native wins over a legacy translated slug' );
		$this->assertSame( 'unknown-segment', Slugs::native_post_path( 'unknown-segment', 'en', 'product' ), 'unknown falls through' );
		$this->assertSame( 'gtm-test-charger', Slugs::native_post_path( 'gtm-test-charger', 'ar', 'product' ), 'other language: not an ar slug' );
		$this->assertContains( $a, Slugs::published_ids( 'en', 'product' ), 'published list' );
		$this->assertNotContains( $shared, Slugs::published_ids( 'en', 'product' ) );
		$this->assertNotContains( $legacy, Slugs::published_ids( 'en', 'product' ) );

		$qv = apply_filters( 'request', [ 'product' => 'gtm-test-charger', 'name' => 'gtm-test-charger', 'post_type' => 'product' ] );
		$this->assertSame( 'gtm-test-charger', $qv['product'], 'request filter inert for Persian' );

		$qv = Languages::with_lang( 'en', static fn(): array => apply_filters( 'request', [ 'product' => 'gtm-test-charger', 'name' => 'gtm-test-charger', 'post_type' => 'product' ] ) );
		$this->assertSame( 'gtm-test-native-a', $qv['product'], 'request filter under /en/' );
		$this->assertSame( 'gtm-test-native-a', $qv['name'] );
	}

	public function testEnShopResolvesWithIsShopTrue(): void {
		$wp = $this->parseFrontend( '/en/shop/', 'en' );
		$this->assertSame( 'product', $wp->query_vars['post_type'] ?? '', 'post_type=product' );
		$this->assertTrue( is_shop(), 'is_shop()' );
		$this->assertFalse( is_404() );
		$this->assertSame( 'http://gadgetim.local/en/shop/', wc_get_page_permalink( 'shop' ), 'shop permalink carries the prefix and the native slug' );
		$this->assertSame( 'http://gadgetim.local/en/cart/', wc_get_page_permalink( 'cart' ) );
		$this->assertStringContains( '/en/my-account/orders/', wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ), 'endpoints untranslated' );
	}

	public function testEnProductUrlResolvesThroughTheTranslatedSlug(): void {
		$a = $this->createProduct( 'شارژر تست الف', 'gtm-test-native-a', [ '_gtm_slug_en' => 'gtm-test-charger', '_gtm_title_en' => 'Test charger', '_gtm_status_en' => 'published' ] );
		Slugs::invalidate_all();

		$wp = $this->parseFrontend( '/en/product/gtm-test-charger/', 'en' );
		$this->assertSame( 'gtm-test-native-a', $wp->query_vars['product'] ?? '', 'query var rewritten to the native slug' );
		$this->assertTrue( is_singular( 'product' ), 'singular product' );
		$this->assertSame( $a, get_queried_object_id(), 'the right product' );

		$this->parseFrontend( '/product/gtm-test-charger/', 'fa' );
		$this->assertTrue( is_404(), 'the translated slug does not resolve at the Persian root' );
	}

	public function testEnPageUrlResolvesThroughTheTranslatedSlug(): void {
		$page = $this->createPage( 'دربارهٴ تست', 'gtm-test-about-fa', [ '_gtm_slug_en' => 'gtm-test-about-en', '_gtm_title_en' => 'About test', '_gtm_status_en' => 'published' ] );
		$child = $this->createPage( 'زیرصفحهٴ تست', 'gtm-test-child-fa', [ '_gtm_slug_en' => 'gtm-test-child-en', '_gtm_title_en' => 'Child test', '_gtm_status_en' => 'published' ], $page );
		Slugs::invalidate_all();

		$wp = $this->parseFrontend( '/en/gtm-test-about-en/', 'en' );
		$this->assertSame( 'gtm-test-about-fa', $wp->query_vars['pagename'] ?? '', 'pagename rewritten to the native path' );
		$this->assertFalse( isset( $wp->query_vars['name'] ), 'the post rule\'s name var is dropped' );
		$this->assertTrue( is_page( $page ), 'the translated page resolves' );
		$this->assertFalse( is_404() );

		$wp = $this->parseFrontend( '/en/gtm-test-about-en/gtm-test-child-en/', 'en' );
		$this->assertSame( 'gtm-test-about-fa/gtm-test-child-fa', $wp->query_vars['pagename'] ?? '', 'hierarchical path walked segment by segment' );
		$this->assertTrue( is_page( $child ), 'the translated child page resolves' );

		$this->assertStringContains( '/en/gtm-test-about-en/', Languages::with_lang( 'en', static fn(): string => (string) get_permalink( $page ) ), 'outbound link uses the translated slug' );
	}

	public function testNewNativeSlugCannotTakeATranslatedSlug(): void {
		$owner = $this->createProduct( 'شارژر تست مالک', 'gtm-test-owner', [ '_gtm_slug_en' => 'gtm-test-taken', '_gtm_status_en' => 'published' ] );
		$late  = $this->createProduct( 'شارژر تست بعدی', 'gtm-test-taken' );
		$this->assertSame( 'gtm-test-taken-2', (string) get_post_field( 'post_name', $late ), 'native slug suffixed away from the translated one' );
		$this->assertSame( 'gtm-test-owner', (string) get_post_field( 'post_name', $owner ) );

		$a = $this->createTerm( 'product_cat', 'دستهٴ تست مالک', 'gtm-test-cat-owner', 0, [ '_gtm_slug_en' => 'gtm-test-cat-taken' ] );
		$b = $this->createTerm( 'product_cat', 'دستهٴ تست بعدی', 'gtm-test-cat-taken' );
		$this->assertSame( 'gtm-test-cat-taken-2', get_term( $b, 'product_cat' )->slug, 'new term slug suffixed' );
		wp_update_term( $a, 'product_cat', [ 'slug' => 'gtm-test-cat-renamed' ] );
		$c = $this->createTerm( 'product_cat', 'دستهٴ تست سوم', 'gtm-test-cat-third' );
		wp_update_term( $c, 'product_cat', [ 'slug' => 'gtm-test-cat-taken' ] );
		$this->assertSame( 'gtm-test-cat-taken-3', get_term( $c, 'product_cat' )->slug, 'renamed term slug suffixed (-2 is native)' );
	}

	public function testOutboundLinksCarryTranslatedSlugsPerLanguage(): void {
		$a = $this->createProduct( 'شارژر تست الف', 'gtm-test-native-a', [ '_gtm_slug_en' => 'gtm-test-charger', '_gtm_status_en' => 'published' ] );
		$parent = $this->createTerm( 'product_cat', 'دستهٴ تست', 'gtm-test-cat', 0, [ '_gtm_slug_en' => 'gtm-test-cat-en' ] );
		$child  = $this->createTerm( 'product_cat', 'زیردستهٴ تست', 'gtm-test-sub', $parent, [ '_gtm_slug_en' => 'gtm-test-sub-en' ] );
		$page_p = $this->createPage( 'والد', 'gtm-test-parent', [ '_gtm_slug_en' => 'gtm-test-parent-en' ] );
		$page_c = $this->createPage( 'فرزند', 'gtm-test-child', [ '_gtm_slug_en' => 'gtm-test-child-en' ], $page_p );

		$this->assertSame( 'http://gadgetim.local/product/gtm-test-native-a/', get_permalink( $a ), 'Persian permalink untouched' );
		$this->assertSame( 'http://gadgetim.local/en/product/gtm-test-charger/', Languages::with_lang( 'en', static fn() => get_permalink( $a ) ) );
		$this->assertSame( 'http://gadgetim.local/ar/product/gtm-test-native-a/', Languages::with_lang( 'ar', static fn() => get_permalink( $a ) ), 'no ar slug: native under /ar/' );

		$this->assertSame( 'http://gadgetim.local/product-category/gtm-test-cat/gtm-test-sub/', get_term_link( $child, 'product_cat' ) );
		$this->assertSame( 'http://gadgetim.local/en/product-category/gtm-test-cat-en/gtm-test-sub-en/', Languages::with_lang( 'en', static fn() => get_term_link( $child, 'product_cat' ) ), 'hierarchical term path translated' );
		Slugs::invalidate_all();
		$this->assertSame( 'gtm-test-cat/gtm-test-sub', Slugs::native_term_path( 'gtm-test-cat-en/gtm-test-sub-en', 'en', 'product_cat' ), 'inbound hierarchical term path' );

		$this->assertSame( 'http://gadgetim.local/gtm-test-parent/gtm-test-child/', get_permalink( $page_c ) );
		$this->assertSame( 'http://gadgetim.local/en/gtm-test-parent-en/gtm-test-child-en/', Languages::with_lang( 'en', static fn() => get_permalink( $page_c ) ), 'hierarchical page path translated' );
		$this->assertSame( 'gtm-test-parent/gtm-test-child', Slugs::native_post_path( 'gtm-test-parent-en/gtm-test-child-en', 'en', 'page' ) );

		$shop = (int) wc_get_page_id( 'shop' );
		update_post_meta( $shop, '_gtm_slug_en', 'store' );
		$this->onTearDown( static fn() => delete_post_meta( $shop, '_gtm_slug_en' ) );
		$this->assertSame( 'http://gadgetim.local/en/shop/', Languages::with_lang( 'en', static fn() => get_permalink( $shop ) ), 'WooCommerce pages ignore translated slugs' );
	}

	public function testPersianSlugUnderThePrefixRedirectsToTheTranslatedOne(): void {
		$a = $this->createProduct( 'شارژر تست الف', 'gtm-test-native-a', [ '_gtm_slug_en' => 'gtm-test-charger', '_gtm_status_en' => 'published' ] );
		Slugs::invalidate_all();
		$redirect = $this->captureRedirect();

		$this->parseFrontend( '/en/product/gtm-test-native-a/', 'en' );
		$this->assertSame( $a, get_queried_object_id(), 'Persian slug still resolves under /en/' );
		Slugs::maybe_redirect();
		$this->assertSame( 'http://gadgetim.local/en/product/gtm-test-charger/', $redirect(), '301 to the translated slug' );
	}

	public function testPreviousTranslatedSlugRedirects(): void {
		$a = $this->createProduct( 'شارژر تست الف', 'gtm-test-native-a', [ '_gtm_slug_en' => 'gtm-test-charger', '_gtm_status_en' => 'published' ] );
		Slugs::remember_previous( 'post', $a, 'en', 'gtm-test-charger-old', 'gtm-test-charger' );
		$this->assertSame( [ 'gtm-test-charger-old' ], get_post_meta( $a, '_gtm_slug_en_prev', true ) );
		Slugs::invalidate_all();
		$this->assertSame( $a, Slugs::map( 'en' )['post_prev']['product']['gtm-test-charger-old'] ?? 0, 'previous slug in the map' );

		$redirect = $this->captureRedirect();
		$this->parseFrontend( '/en/product/gtm-test-charger-old/', 'en' );
		$this->assertTrue( is_404(), 'old slug is a 404 before our redirect' );
		Slugs::maybe_redirect();
		$this->assertSame( 'http://gadgetim.local/en/product/gtm-test-charger/', $redirect(), '301 from the previous slug' );

		$term = $this->createTerm( 'product_cat', 'دستهٴ تست', 'gtm-test-cat', 0, [ '_gtm_slug_en' => 'gtm-test-cat-en', '_gtm_slug_en_prev' => [ 'gtm-test-cat-old' ] ] );
		Slugs::invalidate_all();
		$this->parseFrontend( '/en/product-category/gtm-test-cat-old/', 'en' );
		$this->assertTrue( is_404() );
		Slugs::maybe_redirect();
		$this->assertSame( 'http://gadgetim.local/en/product-category/gtm-test-cat-en/', $redirect(), 'term previous slug' );
		$this->assertTrue( $term > 0 );
	}

	public function testVisibilityGateFiltersListings(): void {
		$a = $this->createProduct( 'محصول تست منتشر', 'gtm-test-pub', [ '_gtm_title_en' => 'Published one', '_gtm_status_en' => 'published' ] );
		$b = $this->createProduct( 'محصول تست پیش‌نویس', 'gtm-test-draft', [ '_gtm_title_en' => 'Draft one', '_gtm_status_en' => 'draft' ] );
		$c = $this->createProduct( 'محصول تست بدون ترجمه', 'gtm-test-none' );
		Slugs::invalidate_all();

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'post__in'       => [ $a, $b, $c ],
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];
		$fa = array_map( 'intval', ( new \WP_Query( $args ) )->posts );
		$this->assertSame( [ $a, $b, $c ], $fa, 'Persian lists everything' );

		$en = Languages::with_lang( 'en', static fn(): array => array_map( 'intval', ( new \WP_Query( $args ) )->posts ) );
		$this->assertSame( [ $a ], $en, 'English lists only published translations' );

		$skip = Languages::with_lang( 'en', static fn(): array => array_map( 'intval', ( new \WP_Query( $args + [ 'gadgetim_skip_gate' => true ] ) )->posts ) );
		$this->assertSame( [ $a, $b, $c ], $skip, 'gadgetim_skip_gate bypasses the gate' );

		$this->assertSame( [ $a ], Visibility::filter_ids( [ $a, $b, $c ], 'en' ), 'filter_ids (related/upsells)' );
		$this->assertSame( [ $a, $b, $c ], Visibility::filter_ids( [ $a, $b, $c ], 'fa' ) );
		$this->assertSame( [ $a ], Languages::with_lang( 'en', static fn(): array => apply_filters( 'woocommerce_related_products', [ $a, $b, $c ], $a, [] ) ), 'woocommerce_related_products' );
	}
}
