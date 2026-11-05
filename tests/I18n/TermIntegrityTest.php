<?php
/**
 * §3.3 regression net: writes in a non-Persian context never leak translations into
 * wp_terms.name or wp_posts.post_title; term objects are never altered.
 *
 * @package Gadgetim\Tests
 */

declare( strict_types=1 );

namespace Gadgetim\Tests\I18n;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Search;
use Gadgetim\Core\I18n\TermTranslations;

/**
 * Translation is applied at output points only; getters and term objects stay Persian.
 */
final class TermIntegrityTest extends TestCase {

	public function testTermUpdateUnderEnglishContextKeepsThePersianName(): void {
		global $wpdb;
		$id = $this->createTerm( 'product_cat', 'شارژر تست', 'gtm-test-chargers-fa', 0, [ '_gtm_name_en' => 'Chargers', '_gtm_status_en' => 'published' ] );

		$result = Languages::with_lang( 'en', static fn() => wp_update_term( $id, 'product_cat', [ 'description' => 'updated under /en/' ] ) );
		$this->assertTrue( is_array( $result ), 'wp_update_term succeeded' );

		$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id = %d", $id ) );
		$this->assertSame( 'شارژر تست', $name, 'wp_terms.name unchanged' );
		$this->assertSame( 'updated under /en/', get_term( $id, 'product_cat' )->description );
		$this->assertSame( 'Chargers', get_term_meta( $id, '_gtm_name_en', true ), 'translation meta intact' );
	}

	public function testTermObjectsAreNeverAlteredOnlyHelpersTranslate(): void {
		$id   = $this->createTerm( 'product_cat', 'شارژر تست', 'gtm-test-chargers-fa', 0, [ '_gtm_name_en' => 'Chargers', '_gtm_status_en' => 'published', '_gtm_name_ar' => 'شواحن', '_gtm_status_ar' => 'draft' ] );
		$term = get_term( $id, 'product_cat' );

		$check = static function () use ( $term, $id ): array {
			$fresh = get_term( $id, 'product_cat' );
			return [ $term->name, $fresh->name, TermTranslations::name( $fresh ), gadgetim_term_name( $fresh ) ];
		};
		$this->assertSame( [ 'شارژر تست', 'شارژر تست', '', 'شارژر تست' ], $check(), 'Persian context' );
		$this->assertSame( [ 'شارژر تست', 'شارژر تست', 'Chargers', 'Chargers' ], Languages::with_lang( 'en', $check ), 'English context: object Persian, helper English' );
		$this->assertSame( [ 'شارژر تست', 'شارژر تست', '', 'شارژر تست' ], Languages::with_lang( 'ar', $check ), 'Arabic draft is not shown' );

		$terms = Languages::with_lang( 'en', static fn() => get_terms( [ 'taxonomy' => 'product_cat', 'include' => [ $id ], 'hide_empty' => false ] ) );
		$this->assertSame( 'شارژر تست', $terms[0]->name ?? '', 'get_terms() untouched' );
		$this->assertSame( 'gtm-test-chargers-fa', $terms[0]->slug ?? '', 'slug untouched' );
	}

	public function testProductSaveUnderEnglishContextKeepsThePersianTitle(): void {
		$id = $this->createProduct( 'شارژر ۳۳ وات تست', 'gtm-test-33w', [ '_gtm_title_en' => '33W test charger', '_gtm_status_en' => 'published' ] );

		Languages::with_lang(
			'en',
			static function () use ( $id ): void {
				$product = wc_get_product( $id );
				$product->set_description( 'saved under /en/' );
				$product->save();
				wp_update_post( [ 'ID' => $id, 'post_excerpt' => 'excerpt under /en/' ] );
			}
		);

		$post = get_post( $id );
		$this->assertSame( 'شارژر ۳۳ وات تست', $post->post_title, 'post_title unchanged' );
		$this->assertSame( 'saved under /en/', $post->post_content );
		$this->assertSame( 'excerpt under /en/', $post->post_excerpt );
		$this->assertSame( '33W test charger', get_post_meta( $id, '_gtm_title_en', true ), 'translation meta intact' );
		$this->assertSame( 'شارژر ۳۳ وات تست', wc_get_product( $id )->get_name( 'edit' ), 'edit-context getter Persian in any language' );
		$this->assertSame( 'شارژر ۳۳ وات تست', Languages::with_lang( 'en', static fn() => wc_get_product( $id )->get_name( 'edit' ) ) );
	}

	public function testTranslationHelpersGateOnPublishedStatus(): void {
		$published = $this->createProduct( 'منتشر', 'gtm-test-pub', [ '_gtm_title_en' => 'Published', '_gtm_status_en' => 'published' ] );
		$draft     = $this->createProduct( 'پیش‌نویس', 'gtm-test-draft', [ '_gtm_title_en' => 'Draft', '_gtm_status_en' => 'draft' ] );

		$this->assertSame( '', PostTranslations::get( $published, 'title', 'fa' ), 'Persian never reads translation meta' );
		$this->assertSame( 'Published', PostTranslations::get( $published, 'title', 'en' ) );
		$this->assertSame( '', PostTranslations::get( $draft, 'title', 'en' ), 'draft is invisible' );
		$this->assertSame( 'Draft', PostTranslations::get( $draft, 'title', 'en', false ), 'raw read for the admin' );
		$this->assertSame( 'Draft', PostTranslations::raw( $draft, 'title', 'en' ) );
		$this->assertTrue( PostTranslations::is_published( $published, 'en' ) );
		$this->assertFalse( PostTranslations::is_published( $draft, 'en' ) );
		$this->assertTrue( PostTranslations::is_published( $draft, 'fa' ), 'Persian always exists' );
		$this->assertSame( 'پیش‌نویس', Languages::with_lang( 'en', static fn() => PostTranslations::title( $draft ) ), 'title() falls back to Persian' );
		$this->assertSame( 'Published', Languages::with_lang( 'en', static fn() => PostTranslations::title( $published ) ) );
	}

	public function testSanitisationPerSpec(): void {
		$id = $this->createProduct( 'محصول', 'gtm-test-sanitize' );
		PostTranslations::set( $id, 'title', 'en', '<b>Bold</b> title <script>x</script>' );
		PostTranslations::set( $id, 'content', 'en', '<p>ok</p><script>alert(1)</script><a href="javascript:x">l</a>' );
		PostTranslations::set( $id, 'slug', 'en', 'Hello World!' );
		PostTranslations::set( $id, 'status', 'en', 'bogus' );

		$this->assertSame( 'Bold title', get_post_meta( $id, '_gtm_title_en', true ), 'sanitize_text_field' );
		$this->assertSame( '<p>ok</p>alert(1)<a href="x">l</a>', get_post_meta( $id, '_gtm_content_en', true ), 'wp_kses_post (tags stripped, javascript: href neutralised)' );
		$this->assertSame( 'hello-world', get_post_meta( $id, '_gtm_slug_en', true ), 'sanitize_title' );
		$this->assertSame( '', (string) get_post_meta( $id, '_gtm_status_en', true ), 'status whitelist: bogus deleted' );

		PostTranslations::set( $id, 'title', 'fa', 'never' );
		$this->assertSame( '', (string) get_post_meta( $id, '_gtm_title_fa', true ), 'no Persian translation meta' );
	}

	public function testSearchBlobsAreNormalisedPerLanguage(): void {
		$cat = $this->createTerm( 'product_cat', 'شارژر', 'gtm-test-blob-cat', 0, [ '_gtm_name_en' => 'Chargers', '_gtm_status_en' => 'published' ] );
		$id  = $this->createProduct( 'شارژر ۳۳ وات شيائومي', 'gtm-test-blob', [ '_gtm_title_en' => 'Xiaomi 33W Charger', '_gtm_status_en' => 'published', '_gtm_subtitle' => 'شارژ سريع' ] );
		wp_set_object_terms( $id, [ $cat ], 'product_cat' );
		Search::index_post( $id );

		$fa = (string) get_post_meta( $id, Search::key( 'fa' ), true );
		$en = (string) get_post_meta( $id, Search::key( 'en' ), true );
		$this->assertStringContains( 'شارژر 33 وات شیائومی', $fa, 'Persian blob: Arabic letters -> Persian, digits -> ASCII' );
		$this->assertStringContains( 'xiaomi', $fa, 'Persian blob carries the English title too' );
		$this->assertStringContains( 'chargers', $fa, 'and the English category name' );
		$this->assertStringContains( 'xiaomi 33w charger', $en, 'English blob' );
		$this->assertStringContains( 'شارژر', $en, 'English blob keeps the Persian names (model names are language-neutral)' );
		$this->assertStringContains( 'شارژ سریع', $fa, 'subtitle normalised' );
		$this->assertSame( [ 'xiaomi', '33w' ], Search::terms_of( ' XIAOMI   ٣٣W ' ), 'query words normalised' );
	}
}
