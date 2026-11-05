<?php
/**
 * Minimal PHPUnit-style test case for the I18n tests (no PHPUnit dependency).
 *
 * The tests run against the Local WordPress site through tools/wp-bootstrap.php:
 *
 *   "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tests/I18n/run.php
 *
 * Fixtures are real posts/terms created in the Local database and removed in tearDown().
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

namespace Gadgetim\Tests\I18n;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\I18n\Urls;
use Gadgetim\Core\I18n\Visibility;
use Gadgetim\Core\Support\Context;
use Throwable;

/**
 * Thrown by a failed assertion.
 */
final class AssertionFailed extends \Exception {}

/**
 * Base class: assertions, fixtures, request simulation.
 */
abstract class TestCase {

	/** @var callable[] Cleanup callbacks, run in reverse order. */
	private array $cleanup = [];

	/** @var array Saved superglobals. */
	private array $saved = [];

	/**
	 * Before each test.
	 */
	public function setUp(): void {
		$this->saved = [
			'server' => $_SERVER,
			'get'    => $_GET,
			'post'   => $_POST,
		];
		Languages::set_current( Languages::DEFAULT );
		Urls::disable_prefix();
		Slugs::reset();
		Visibility::reset();
		Context::reset();
	}

	/**
	 * After each test: fixtures removed, request restored.
	 */
	public function tearDown(): void {
		foreach ( array_reverse( $this->cleanup ) as $fn ) {
			try {
				$fn();
			} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Best effort.
			}
		}
		$this->cleanup = [];
		$_SERVER       = $this->saved['server'];
		$_GET          = $this->saved['get'];
		$_POST         = $this->saved['post'];
		Languages::set_current( Languages::DEFAULT );
		Urls::disable_prefix();
		Slugs::invalidate_all();
		Slugs::reset();
		Visibility::reset();
		Context::reset();
		$this->reset_main_query();
	}

	/* --------------------------------------------------------------- asserts */

	protected function assertSame( mixed $expected, mixed $actual, string $message = '' ): void {
		if ( $expected !== $actual ) {
			$this->fail( sprintf( "%s\nexpected: %s\nactual:   %s", $message, $this->export( $expected ), $this->export( $actual ) ) );
		}
	}

	protected function assertNotSame( mixed $expected, mixed $actual, string $message = '' ): void {
		if ( $expected === $actual ) {
			$this->fail( sprintf( "%s\nboth: %s", $message, $this->export( $actual ) ) );
		}
	}

	protected function assertTrue( mixed $value, string $message = '' ): void {
		$this->assertSame( true, $value, $message );
	}

	protected function assertFalse( mixed $value, string $message = '' ): void {
		$this->assertSame( false, $value, $message );
	}

	protected function assertEmpty( mixed $value, string $message = '' ): void {
		if ( ! empty( $value ) ) {
			$this->fail( sprintf( "%s\nexpected empty, got: %s", $message, $this->export( $value ) ) );
		}
	}

	protected function assertNotEmpty( mixed $value, string $message = '' ): void {
		if ( empty( $value ) ) {
			$this->fail( sprintf( '%s (expected a non-empty value)', $message ) );
		}
	}

	protected function assertStringContains( string $needle, string $haystack, string $message = '' ): void {
		if ( ! str_contains( $haystack, $needle ) ) {
			$this->fail( sprintf( "%s\n\"%s\" not found in:\n%s", $message, $needle, $haystack ) );
		}
	}

	protected function assertStringNotContains( string $needle, string $haystack, string $message = '' ): void {
		if ( str_contains( $haystack, $needle ) ) {
			$this->fail( sprintf( "%s\n\"%s\" unexpectedly found in:\n%s", $message, $needle, $haystack ) );
		}
	}

	protected function assertContains( mixed $needle, array $haystack, string $message = '' ): void {
		if ( ! in_array( $needle, $haystack, true ) ) {
			$this->fail( sprintf( "%s\n%s not in %s", $message, $this->export( $needle ), $this->export( $haystack ) ) );
		}
	}

	protected function assertNotContains( mixed $needle, array $haystack, string $message = '' ): void {
		if ( in_array( $needle, $haystack, true ) ) {
			$this->fail( sprintf( "%s\n%s unexpectedly in %s", $message, $this->export( $needle ), $this->export( $haystack ) ) );
		}
	}

	protected function fail( string $message ): never {
		throw new AssertionFailed( trim( $message ) );
	}

	private function export( mixed $value ): string {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/* -------------------------------------------------------------- fixtures */

	/**
	 * Registers a cleanup callback.
	 */
	protected function onTearDown( callable $fn ): void {
		$this->cleanup[] = $fn;
	}

	/**
	 * Creates a published simple product (removed in tearDown).
	 *
	 * @param array<string,string> $meta Post meta to set.
	 */
	protected function createProduct( string $title, string $slug, array $meta = [], string $status = 'publish' ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $title );
		$product->set_slug( $slug );
		$product->set_status( $status );
		$product->set_regular_price( '1000000' );
		$id = $product->save();
		$this->assertTrue( $id > 0, 'product fixture created' );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$this->onTearDown( static fn() => wp_delete_post( $id, true ) );
		return $id;
	}

	/**
	 * Creates a published page (removed in tearDown).
	 */
	protected function createPage( string $title, string $slug, array $meta = [], int $parent = 0 ): int {
		$id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_parent'  => $parent,
				'post_content' => 'content ' . $slug,
			],
			true
		);
		$this->assertTrue( is_int( $id ) && $id > 0, 'page fixture created' );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$this->onTearDown( static fn() => wp_delete_post( $id, true ) );
		return (int) $id;
	}

	/**
	 * Creates a term (removed in tearDown).
	 */
	protected function createTerm( string $taxonomy, string $name, string $slug, int $parent = 0, array $meta = [] ): int {
		$result = wp_insert_term(
			$name,
			$taxonomy,
			[
				'slug'   => $slug,
				'parent' => $parent,
			]
		);
		$this->assertTrue( is_array( $result ) && ! empty( $result['term_id'] ), 'term fixture created: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		$id = (int) $result['term_id'];
		foreach ( $meta as $key => $value ) {
			update_term_meta( $id, $key, $value );
		}
		$this->onTearDown( static fn() => wp_delete_term( $id, $taxonomy ) );
		return $id;
	}

	/* --------------------------------------------------------------- request */

	/**
	 * Replaces the request superglobals for the rest of the test (restored in tearDown).
	 *
	 * @param string               $uri    REQUEST_URI.
	 * @param string               $method HTTP method.
	 * @param array<string,string> $server Extra $_SERVER keys.
	 * @param array<string,string> $get    $_GET.
	 */
	protected function fakeRequest( string $uri, string $method = 'GET', array $server = [], array $get = [] ): void {
		$_SERVER['REQUEST_URI']    = $uri;
		$_SERVER['REQUEST_METHOD'] = $method;
		$_SERVER['QUERY_STRING']   = (string) ( wp_parse_url( $uri, PHP_URL_QUERY ) ?? '' );
		unset( $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_X_GADGETIM_LANG'], $_SERVER['PATH_INFO'] );
		foreach ( $server as $key => $value ) {
			$_SERVER[ $key ] = $value;
		}
		$_GET  = $get;
		$_POST = [];
		if ( ! $get && '' !== $_SERVER['QUERY_STRING'] ) {
			wp_parse_str( $_SERVER['QUERY_STRING'], $_GET );
		}
		Context::reset();
	}

	/**
	 * Parses a front-end URL like WordPress does for a real request (do_parse_request ->
	 * WP::parse_request -> main query), in the given language context. Returns the WP object.
	 */
	protected function parseFrontend( string $uri, string $lang ): \WP {
		$this->fakeRequest( $uri );
		Languages::set_current( $lang );
		Urls::disable_prefix(); // Phase 1: on_do_parse_request enables it, like a real request.
		$this->reset_main_query();
		// The global WP object carries the public query vars post types registered on init
		// (a fresh `new WP()` would drop `product`, `product_cat` ...).
		$wp = $GLOBALS['wp'] instanceof \WP ? $GLOBALS['wp'] : new \WP();
		// A real request has a fresh object; clear what the previous parse left behind.
		unset( $wp->matched_rule, $wp->matched_query );
		$wp->did_permalink = false;
		$wp->query_vars    = [];
		$wp->extra_query_vars = [];
		$wp->parse_request();
		$wp->query_posts();
		$wp->handle_404();
		return $wp;
	}

	/**
	 * Fresh global query objects.
	 */
	protected function reset_main_query(): void {
		$GLOBALS['wp_the_query'] = new \WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		unset( $GLOBALS['post'] );
	}

	/**
	 * Captures the URL of the next wp_redirect() call instead of redirecting.
	 *
	 * @return callable(): ?string Returns the captured location (null when none).
	 */
	protected function captureRedirect(): callable {
		$captured = null;
		$filter   = static function ( $location ) use ( &$captured ) {
			$captured = (string) $location;
			return false; // wp_redirect() returns false -> callers do not exit.
		};
		add_filter( 'wp_redirect', $filter, 1, 1 );
		$this->onTearDown( static fn() => remove_filter( 'wp_redirect', $filter, 1 ) );
		// A closure with a by-reference use: an arrow function would copy the initial null.
		return static function () use ( &$captured ): ?string {
			return $captured;
		};
	}
}
