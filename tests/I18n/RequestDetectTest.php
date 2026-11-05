<?php
/**
 * Request::detect() against the §3.1/§3.2 rules.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

namespace Gadgetim\Tests\I18n;

use Gadgetim\Core\I18n\Request;

/**
 * Path prefix, read-only API query/header, wc-ajax referer, and everything that must stay Persian.
 */
final class RequestDetectTest extends TestCase {

	public function testPathPrefixWins(): void {
		$this->fakeRequest( '/en/shop/' );
		$this->assertSame( 'en', Request::detect(), '/en/shop/' );

		$this->fakeRequest( '/ar/' );
		$this->assertSame( 'ar', Request::detect(), '/ar/' );

		$this->fakeRequest( '/en' );
		$this->assertSame( 'en', Request::detect(), '/en without slash' );

		$this->fakeRequest( '/en/product/x/?orderby=price' );
		$this->assertSame( 'en', Request::detect(), 'query string after the prefix' );
	}

	public function testPersianStaysPersian(): void {
		$this->fakeRequest( '/' );
		$this->assertSame( 'fa', Request::detect(), 'home' );

		$this->fakeRequest( '/enzo-brand/' );
		$this->assertSame( 'fa', Request::detect(), '/enzo-brand/ is not /en/' );

		$this->fakeRequest( '/product/en/' );
		$this->assertSame( 'fa', Request::detect(), 'prefix only counts at the start' );

		$this->fakeRequest( '/shop/?lang=en' );
		$this->assertSame( 'fa', Request::detect(), '?lang= never switches a normal page' );

		$this->fakeRequest( '/wp-login.php?lang=ar' );
		$this->assertSame( 'fa', Request::detect(), 'wp-login.php' );
	}

	public function testReadOnlyApiHonoursLangQueryAndHeader(): void {
		$this->fakeRequest( '/wp-json/wc/store/v1/cart?lang=en' );
		$this->assertSame( 'en', Request::detect(), 'GET Store API ?lang=en' );

		$this->fakeRequest( '/wp-json/gadgetim/v1/search?q=x&lang=ar' );
		$this->assertSame( 'ar', Request::detect(), 'GET gadgetim/v1 ?lang=ar' );

		$this->fakeRequest( '/wp-json/gadgetim/v1/search?q=x', 'GET', [ 'HTTP_X_GADGETIM_LANG' => 'ar' ] );
		$this->assertSame( 'ar', Request::detect(), 'X-Gadgetim-Lang header' );

		$this->fakeRequest( '/index.php?rest_route=/wc/store/v1/products&lang=en' );
		$this->assertSame( 'en', Request::detect(), '?rest_route= form' );

		$this->fakeRequest( '/wp-json/wc/store/v1/cart?lang=de' );
		$this->assertSame( 'fa', Request::detect(), 'unknown language code' );

		$this->fakeRequest( '/wp-json/wc/store/v1/cart?lang=en', 'HEAD' );
		$this->assertSame( 'en', Request::detect(), 'HEAD counts as read-only' );
	}

	public function testWritesAndOtherNamespacesIgnoreLang(): void {
		$this->fakeRequest( '/wp-json/wc/store/v1/cart/add-item?lang=en', 'POST' );
		$this->assertSame( 'fa', Request::detect(), 'POST to the Store API' );

		$this->fakeRequest( '/wp-json/wc/v3/products/categories/5?lang=en', 'PUT' );
		$this->assertSame( 'fa', Request::detect(), 'PUT wc/v3 is treated as Persian' );

		$this->fakeRequest( '/wp-json/wc/v3/products?lang=en' );
		$this->assertSame( 'fa', Request::detect(), 'GET wc/v3 is not a read-only storefront API' );

		$this->fakeRequest( '/wp-json/wp/v2/posts?lang=ar' );
		$this->assertSame( 'fa', Request::detect(), 'wp/v2' );
	}

	public function testPrefixedRestPathsKeepContextOnlyForReadOnlyApis(): void {
		$this->fakeRequest( '/en/wp-json/wc/v3/products/categories/5', 'PUT' );
		$this->assertSame( 'fa', Request::detect(), 'PUT /en/wp-json/wc/v3/… never runs in English' );

		$this->fakeRequest( '/en/wp-json/wc/v3/products' );
		$this->assertSame( 'fa', Request::detect(), 'GET /en/wp-json/wc/v3/… is not a storefront API' );

		$this->fakeRequest( '/ar/wp-json/wp/v2/posts' );
		$this->assertSame( 'fa', Request::detect(), '/ar/wp-json/wp/v2/…' );

		$this->fakeRequest( '/en/wp-json/wc/store/v1/cart/add-item', 'POST' );
		$this->assertSame( 'fa', Request::detect(), 'POST to the Store API under the prefix' );

		$this->fakeRequest( '/en/?rest_route=/wc/v3/orders', 'POST' );
		$this->assertSame( 'fa', Request::detect(), '?rest_route= write on a prefixed URL' );

		$this->fakeRequest( '/en/wp-json/wc/store/v1/cart' );
		$this->assertSame( 'en', Request::detect(), 'GET Store API under the prefix keeps English' );
		$this->assertTrue( Request::is_readonly_api(), 'prefixed Store API GET is read-only' );

		$this->fakeRequest( '/en/wp-json-like-page/' );
		$this->assertSame( 'en', Request::detect(), 'a page slug that merely starts like wp-json' );
	}

	public function testStorefrontAjaxUsesTheRefererPrefix(): void {
		$this->fakeRequest( '/?wc-ajax=get_refreshed_fragments', 'POST', [ 'HTTP_REFERER' => 'http://gadgetim.local/en/shop/' ] );
		$this->assertSame( 'en', Request::detect(), 'wc-ajax with /en/ referer' );

		$this->fakeRequest( '/?wc-ajax=get_refreshed_fragments', 'POST', [ 'HTTP_REFERER' => 'http://gadgetim.local/enzo-brand/' ] );
		$this->assertSame( 'fa', Request::detect(), 'referer /enzo-brand/ is Persian' );

		$this->fakeRequest( '/?wc-ajax=get_refreshed_fragments', 'POST' );
		$this->assertSame( 'fa', Request::detect(), 'no referer' );

		$this->fakeRequest( '/?wc-ajax=checkout', 'POST', [ 'HTTP_REFERER' => 'https://gadgetim.local/ar' ] );
		$this->assertSame( 'ar', Request::detect(), 'referer /ar without slash' );

		$this->fakeRequest( '/en/?wc-ajax=checkout', 'POST' );
		$this->assertSame( 'en', Request::detect(), 'prefixed wc-ajax URL (relative home_url branch)' );
	}

	public function testRefererHelperIgnoresOtherHostsPaths(): void {
		$this->assertSame( 'en', Request::lang_from_referer( 'http://gadgetim.local/en/product/x/' ) );
		$this->assertSame( null, Request::lang_from_referer( 'http://gadgetim.local/entries/' ) );
		$this->assertSame( null, Request::lang_from_referer( '' ) );
		$this->assertSame( 'ar', Request::lang_from_referer( 'http://evil.example/ar/' ), 'host is not checked; only the path shape (harmless: context only)' );
	}

	public function testInstallSubdirectoryIsStripped(): void {
		$filter = static fn() => 'http://gadgetim.local/sub';
		add_filter( 'option_home', $filter );
		$this->onTearDown( static fn() => remove_filter( 'option_home', $filter ) );

		$this->fakeRequest( '/sub/en/shop/' );
		$this->assertSame( 'en', Request::detect(), '/sub/en/shop/ with home path /sub' );

		$this->fakeRequest( '/sub/shop/' );
		$this->assertSame( 'fa', Request::detect(), '/sub/shop/' );

		$this->fakeRequest( '/?wc-ajax=x', 'POST', [ 'HTTP_REFERER' => 'http://gadgetim.local/sub/ar/' ] );
		$this->assertSame( 'ar', Request::detect(), 'referer under the sub-directory' );
	}
}
