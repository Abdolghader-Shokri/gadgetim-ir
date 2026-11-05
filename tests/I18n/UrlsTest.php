<?php
/**
 * Urls: prefixing rules of the home_url filter (§3.2) and the segment helpers.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

namespace Gadgetim\Tests\I18n;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Urls;

/**
 * Two-phase prefixing, excluded paths, relative branch, scheme handling.
 */
final class UrlsTest extends TestCase {

	public function testApplyPrefix(): void {
		$this->assertSame( 'http://gadgetim.local/en/shop/', Urls::apply_prefix( 'http://gadgetim.local/shop/', 'en' ) );
		$this->assertSame( 'https://gadgetim.local/en/shop/', Urls::apply_prefix( 'https://gadgetim.local/shop/', 'en' ), 'scheme kept' );
		$this->assertSame( 'http://gadgetim.local/en', Urls::apply_prefix( 'http://gadgetim.local', 'en' ), 'bare home' );
		$this->assertSame( 'http://gadgetim.local/en/', Urls::apply_prefix( 'http://gadgetim.local/', 'en' ), 'home with slash' );
		$this->assertSame( 'http://gadgetim.local/en/shop/', Urls::apply_prefix( 'http://gadgetim.local/en/shop/', 'en' ), 'already prefixed' );
		$this->assertSame( 'http://gadgetim.local/en', Urls::apply_prefix( 'http://gadgetim.local/en', 'en' ), 'already prefixed, no slash' );
		$this->assertSame( 'http://other.example/shop/', Urls::apply_prefix( 'http://other.example/shop/', 'en' ), 'other host' );
		$this->assertSame( 'http://gadgetim.local.example/shop/', Urls::apply_prefix( 'http://gadgetim.local.example/shop/', 'en' ), 'host that merely starts with ours' );
		$this->assertSame( '//gadgetim.local/en/x/', Urls::apply_prefix( '//gadgetim.local/x/', 'en' ), 'protocol-relative' );
		$this->assertSame( 'http://gadgetim.local/en/?wc-ajax=x', Urls::apply_prefix( 'http://gadgetim.local/?wc-ajax=x', 'en' ), 'query on the root' );
	}

	public function testRelativeBranch(): void {
		$this->assertSame( '/en/', Urls::apply_prefix( '/', 'en', true ), 'WC_AJAX::get_endpoint base' );
		$this->assertSame( '/en/?wc-ajax=%%endpoint%%', Urls::apply_prefix( '/?wc-ajax=%%endpoint%%', 'en', true ) );
		$this->assertSame( '/en/shop/', Urls::apply_prefix( '/shop/', 'en', true ) );
		$this->assertSame( '/en/shop/', Urls::apply_prefix( '/en/shop/', 'en', true ), 'already prefixed' );
		$this->assertSame( '/ar/', Urls::apply_prefix( '/', 'ar' ), 'root-relative without the relative flag' );
	}

	public function testHomeUrlFilterIsInertBeforeParseAndForPersian(): void {
		Urls::disable_prefix();
		Languages::set_current( 'en' );
		$this->assertSame( 'http://gadgetim.local/shop/', home_url( '/shop/' ), 'phase 1: no prefix before do_parse_request' );

		Urls::enable_prefix();
		$this->assertSame( 'http://gadgetim.local/en/shop/', home_url( '/shop/' ), 'phase 2: prefixed' );
		$this->assertSame( 'http://gadgetim.local/en', home_url(), 'bare home_url()' );
		$this->assertSame( '/en/', home_url( '/', 'relative' ), 'relative scheme' );
		$this->assertSame( 'http://gadgetim.local/en/?wc-ajax=checkout', home_url( '/?wc-ajax=checkout' ), 'wc-ajax under the prefix' );

		Languages::set_current( 'fa' );
		$this->assertSame( 'http://gadgetim.local/shop/', home_url( '/shop/' ), 'Persian is never prefixed' );
		Urls::disable_prefix();
	}

	public function testExcludedPathsAndSchemes(): void {
		Urls::enable_prefix();
		Languages::set_current( 'en' );
		foreach ( [ '/wp-json/wc/store/v1/cart', '/wp-admin/', '/wp-login.php', '/wp-comments-post.php', '/wp-content/x.css', '/wp-includes/js/x.js', '/xmlrpc.php', '/wp-cron.php' ] as $path ) {
			$this->assertSame( 'http://gadgetim.local' . $path, home_url( $path ), 'never prefixed: ' . $path );
		}
		$this->assertSame( 'http://gadgetim.local/wp-json/', home_url( '/wp-json/', 'rest' ), 'rest scheme' );
		$this->assertStringContains( '/wp-json/', rest_url(), 'rest_url()' );
		$this->assertStringNotContains( '/en/', rest_url(), 'rest_url() unprefixed' );
		Urls::disable_prefix();
		Languages::set_current( 'fa' );
	}

	public function testWithLangForcesThePrefixAndRestores(): void {
		Urls::disable_prefix();
		Languages::set_current( 'fa' );
		$url = Languages::with_lang( 'en', static fn(): string => home_url( '/shop/' ) );
		$this->assertSame( 'http://gadgetim.local/en/shop/', $url, 'with_lang prefixes even before parse (admin, cron)' );
		$this->assertSame( 'http://gadgetim.local/shop/', home_url( '/shop/' ), 'restored after with_lang' );
		$this->assertSame( 'fa', Languages::current() );

		$nested = Languages::with_lang( 'ar', static fn(): array => [ Languages::current(), Languages::with_lang( 'en', static fn(): string => Languages::current() ), Languages::current() ] );
		$this->assertSame( [ 'ar', 'en', 'ar' ], $nested, 'nesting' );
	}

	public function testStripLocalizeAndSegments(): void {
		$this->assertSame( 'http://gadgetim.local/shop/', Urls::strip_prefixes( 'http://gadgetim.local/en/shop/' ) );
		$this->assertSame( 'http://gadgetim.local/', Urls::strip_prefixes( 'http://gadgetim.local/ar' ) );
		$this->assertSame( '/shop/', Urls::strip_prefixes( '/ar/shop/' ) );
		$this->assertSame( 'http://gadgetim.local/entries/', Urls::strip_prefixes( 'http://gadgetim.local/entries/' ), 'only whole segments' );
		$this->assertSame( 'http://gadgetim.local/ar/shop/', Urls::localize( 'http://gadgetim.local/en/shop/', 'ar' ) );
		$this->assertSame( 'http://gadgetim.local/shop/', Urls::localize( 'http://gadgetim.local/en/shop/', 'fa' ) );

		$this->assertSame( 'http://x/en/product/charger/', Urls::replace_segments( 'http://x/en/product/charger-fa/', 'charger-fa', 'charger' ) );
		$this->assertSame( 'http://x/en/product/charger', Urls::replace_segments( 'http://x/en/product/charger-fa', 'charger-fa', 'charger' ), 'no trailing slash' );
		$this->assertSame( 'http://x/en/product/charger/?a=1', Urls::replace_segments( 'http://x/en/product/charger-fa/?a=1', 'charger-fa', 'charger' ), 'query kept' );
		$this->assertSame( 'http://x/en/product-category/a-en/b-en/', Urls::replace_segments( 'http://x/en/product-category/a/b/', 'a/b', 'a-en/b-en' ), 'hierarchical run' );
		$this->assertSame( 'http://x/product/product/', Urls::replace_segments( 'http://x/product/product/', 'product', 'product' ), 'identical' );
		$this->assertSame( 'http://x/product/x/', Urls::replace_segments( 'http://x/product/x/', 'y', 'z' ), 'no match' );
	}

	public function testRewriteRulesAreRegeneratedWithTheUnprefixedHome(): void {
		global $wp_rewrite;
		$this->assertTrue( $wp_rewrite->using_permalinks(), 'Local uses pretty permalinks' );

		delete_option( 'rewrite_rules' );
		$wp_rewrite->rules = [];
		$wp = $this->parseFrontend( '/en/shop/', 'en' );

		$rules = get_option( 'rewrite_rules' );
		$this->assertTrue( is_array( $rules ) && isset( $rules['robots\.txt$'] ), 'robots.txt rule present after a first request under /en/' );
		$this->assertTrue( isset( $rules['favicon\.ico$'] ), 'favicon.ico rule present' );
		$this->assertTrue( isset( $rules['sitemap\.xml'] ) || isset( $rules['^wp-sitemap\.xml$'] ), 'sitemap rule present' );
		$this->assertSame( 'product', $wp->query_vars['post_type'] ?? '', '/en/shop/ resolved to the product archive' );
		$this->assertTrue( Urls::prefix_enabled(), 'prefix enabled by do_parse_request' );
	}

	public function testCanonicalRedirectsInsideThePrefix(): void {
		$this->parseFrontend( '/en', 'en' );
		$this->assertSame( 'http://gadgetim.local/en/', redirect_canonical( 'http://gadgetim.local/en', false ), '/en -> /en/' );

		$this->parseFrontend( '/en/shop', 'en' );
		$this->assertSame( 'http://gadgetim.local/en/shop/', redirect_canonical( 'http://gadgetim.local/en/shop', false ), 'trailing slash added under the prefix' );

		$this->parseFrontend( '/en/shop/', 'en' );
		$this->assertSame( null, redirect_canonical( 'http://gadgetim.local/en/shop/', false ), 'canonical URL: no redirect loop' );
	}
}
