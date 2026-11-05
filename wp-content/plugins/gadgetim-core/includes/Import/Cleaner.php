<?php
/**
 * Description cleaning for the import (architecture §6.1 step 3): strip inline attributes and
 * pasted wrapper markup, demote H1, drop empty paragraphs, lift `<strong>Label:</strong> value`
 * rows and two-column tables into the spec table, rewrite in-content image URLs.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Catalog\ProductExtras;
use Gadgetim\Core\Catalog\TitleCleaner;
use Gadgetim\Core\Support\Normalize;
use WP_HTML_Tag_Processor;

/**
 * Pure text processing; the only WordPress dependencies are kses and the tag processor.
 */
final class Cleaner {

	/** Attributes kept per tag; every other attribute (style, dir, lang, class, data-*) is dropped. */
	public const ALLOWED_ATTRIBUTES = [
		'a'       => [ 'href', 'title', 'rel' ],
		'img'     => [ 'src', 'alt', 'width', 'height', 'title' ],
		'td'      => [ 'colspan', 'rowspan' ],
		'th'      => [ 'colspan', 'rowspan', 'scope' ],
		'ol'      => [ 'start' ],
		'details' => [ 'open' ],
		'time'    => [ 'datetime' ],
	];

	/** Removed together with their content. */
	private const DROP_WITH_CONTENT = 'script|style|iframe|object|embed|form|input|button|select|textarea|svg|noscript|video|audio|canvas|map|template';

	/** Removed, content kept; block-level ones become line breaks. */
	private const UNWRAP_BLOCK = 'div|section|article|main|aside|header|footer|nav|figure|figcaption|center|address|hgroup';

	private const UNWRAP_INLINE = 'span|font|u|small|big|mark|abbr|label|tt|ins';

	/** Shortcodes of the old page builder/forms plugins (removed, content kept). */
	private const SHORTCODES = 'contact-form-7|elementor-template|vc_[a-z_]+|woodmart_[a-z_]+|et_pb_[a-z_]+|fusion_[a-z_]+|rev_slider|wp_show_posts|yith_[a-z_]+|product_page|ux_[a-z_]+|section|row|col';

	private const OLD_HOSTS = [ 'gadgetim.ir', 'www.gadgetim.ir' ];

	/** Max plain-text length of a spec value; longer "Label: text" lines are feature bullets, not specs. */
	public const MAX_SPEC_VALUE = 100;

	public const MAX_SPEC_LABEL = 50;

	/** @var callable|null */
	private $url_rewriter;

	/**
	 * @param callable|null $url_rewriter fn( string $url ): string — new URL, or '' to drop the image.
	 */
	public function __construct( ?callable $url_rewriter = null ) {
		$this->url_rewriter = $url_rewriter;
	}

	/**
	 * Cleans a product: description (+ optional extra tab), short description → excerpt, specs.
	 *
	 * @param string $description Long description HTML.
	 * @param string $short       Short description (HTML or plain lines).
	 * @param array  $options     'title' (full old title), 'short_title', 'extra' (extra tab HTML).
	 * @return array{description:string,excerpt:string,specs:array<int,array{0:string,1:string}>}
	 */
	public function product( string $description, string $short, array $options = [] ): array {
		$short_result = $this->short_description( $short );
		$specs        = $short_result['specs'];

		$html = $this->html( $description );
		if ( ! empty( $options['extra'] ) && is_string( $options['extra'] ) ) {
			$extra = $this->html( $options['extra'] );
			if ( '' !== trim( wp_strip_all_tags( $extra ) ) ) {
				$html .= "\n\n" . $extra;
			}
		}
		$html = $this->remove_title_headings( $html, array_filter( [ (string) ( $options['title'] ?? '' ), (string) ( $options['short_title'] ?? '' ) ] ) );

		$extracted = $this->extract_specs( $html );
		$specs     = self::merge_specs( $specs, $extracted['specs'] );
		// A description that was nothing but a spec list keeps its lists (the page needs body text);
		// the specs are still returned for the table.
		$remaining = mb_strlen( self::text( $extracted['html'] ) );
		if ( $remaining >= 200 || $remaining >= (int) floor( mb_strlen( self::text( $html ) ) * 0.5 ) ) {
			$html = $extracted['html'];
		}

		return [
			'description' => $this->finalize( $html ),
			'excerpt'     => $this->finalize( $short_result['excerpt'] ),
			'specs'       => $specs,
		];
	}

	/**
	 * Cleans a page: generic pipeline, FAQ accordions, images that no longer exist removed.
	 */
	public function page( string $html ): string {
		return $this->finalize( $this->html( $html ) );
	}

	/**
	 * The generic pipeline without the final kses: wrappers, attributes, headings, links, images,
	 * shortcodes, empty blocks, whitespace. Safe to run twice.
	 */
	public function html( string $html ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}
		$html = str_replace( [ "\r\n", "\r" ], "\n", $html );
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		$html = (string) preg_replace( '#<(' . self::DROP_WITH_CONTENT . ')\b[^>]*>.*?</\1\s*>#is', '', $html );
		$html = (string) preg_replace( '#<(' . self::DROP_WITH_CONTENT . '|source|track|param)\b[^>]*/?>#i', '', $html );
		$html = (string) preg_replace( '#\[/?(' . self::SHORTCODES . ')\b[^\]]*\]#i', '', $html );
		$html = (string) preg_replace( '#</?(' . self::UNWRAP_BLOCK . ')\b[^>]*>#i', "\n", $html );
		$html = (string) preg_replace( '#</?(' . self::UNWRAP_INLINE . ')\b[^>]*>#i', '', $html );
		$html = (string) preg_replace( '#<(/?)h1\b([^>]*)>#i', '<$1h2$2>', $html );
		$html = (string) preg_replace( '#<br\s*/?>\s*(</(?:p|li|h[1-6]|td|th)>)#i', '$1', $html );

		$html = $this->process_tags( $html );
		$html = self::normalize_tags( $html );
		$html = self::faq_to_details( $html );
		$html = (string) preg_replace( '#<a\b[^>]*data-gtm-unwrap="1"[^>]*>(.*?)</a>#is', '$1', $html );
		$html = (string) preg_replace( '#<img\b[^>]*data-gtm-remove="1"[^>]*/?>#i', '', $html );
		$html = (string) preg_replace( '#<a\b[^>]*>\s*</a>#i', '', $html );
		// A heading as the only content of a list item is a styled bullet, not a heading.
		$html = (string) preg_replace( '#<li>\s*<h[1-6]>(.*?)</h[1-6]>\s*</li>#is', '<li>$1</li>', $html );

		$html = self::remove_empty_blocks( $html );
		return self::tidy_whitespace( $html );
	}

	/**
	 * Final pass: kses (Shop Managers lack unfiltered_html, §5.10) and whitespace.
	 */
	public function finalize( string $html ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}
		return self::tidy_whitespace( wp_kses_post( $html ) );
	}

	/**
	 * Lifts spec-like structures out of cleaned HTML: two-column tables, bullet lists and runs of
	 * paragraphs shaped `<strong>Label:</strong> value`.
	 *
	 * @return array{html:string,specs:array<int,array{0:string,1:string}>}
	 */
	public function extract_specs( string $html ): array {
		$specs = [];

		// A) Two-column tables.
		$html = (string) preg_replace_callback(
			'#<table>(.*?)</table>#is',
			function ( array $m ) use ( &$specs ): string {
				$rows   = [];
				$cells2 = true;
				if ( ! preg_match_all( '#<tr>(.*?)</tr>#is', $m[1], $trs ) ) {
					return $m[0];
				}
				foreach ( $trs[1] as $tr ) {
					preg_match_all( '#<t[dh](?:\s[^>]*)?>(.*?)</t[dh]>#is', $tr, $tds );
					if ( 2 !== count( $tds[1] ) ) {
						$cells2 = false;
						break;
					}
					$rows[] = [ self::text( $tds[1][0] ), self::text( $tds[1][1] ) ];
				}
				if ( ! $cells2 || count( $rows ) < 2 ) {
					return $m[0];
				}
				if ( self::is_spec_header( $rows[0][0], $rows[0][1] ) ) {
					array_shift( $rows );
				}
				$added = 0;
				foreach ( $rows as $row ) {
					$spec = self::spec_row( $row[0], $row[1] );
					if ( $spec ) {
						$specs[] = $spec;
						++$added;
					}
				}
				return $added > 0 ? "\n" : $m[0];
			},
			$html
		);

		// B) Bullet lists whose items are mostly "Label: short value".
		$html = (string) preg_replace_callback(
			'#<(ul|ol)(\s[^>]*)?>(.*?)</\1>#is',
			function ( array $m ) use ( &$specs ): string {
				if ( preg_match( '#<(ul|ol|table)\b#i', $m[3] ) ) {
					return $m[0]; // nested structure: leave alone.
				}
				if ( ! preg_match_all( '#<li>(.*?)</li>#is', $m[3], $lis ) ) {
					return $m[0];
				}
				$matched = [];
				$kept    = [];
				foreach ( $lis[1] as $li ) {
					$spec = self::label_value( $li );
					if ( $spec ) {
						$matched[] = $spec;
					} else {
						$kept[] = $li;
					}
				}
				$total = count( $lis[1] );
				if ( count( $matched ) < 3 || count( $matched ) / max( 1, $total ) < 0.5 ) {
					return $m[0];
				}
				foreach ( $matched as $spec ) {
					$specs[] = $spec;
				}
				if ( ! $kept ) {
					return "\n";
				}
				return '<' . $m[1] . ( $m[2] ?? '' ) . '>' . "\n" . implode( "\n", array_map( static fn( string $li ): string => '<li>' . trim( $li ) . '</li>', $kept ) ) . "\n" . '</' . $m[1] . '>';
			},
			$html
		);

		// C) Runs of ≥ 2 adjacent paragraphs/headings shaped "Label: value".
		$html = (string) preg_replace_callback(
			'#(?:<(?:p|h[2-6])>\s*<strong>[^<]{1,' . self::MAX_SPEC_LABEL . '}</strong>[^<]{0,' . self::MAX_SPEC_VALUE . '}?(?:<(?:strong|em|b|bdi|code)>[^<]*</(?:strong|em|b|bdi|code)>[^<]*)*</(?:p|h[2-6])>\s*){2,}#iu',
			function ( array $m ) use ( &$specs ): string {
				preg_match_all( '#<(p|h[2-6])>(.*?)</\1>#is', $m[0], $blocks );
				$rows = [];
				foreach ( $blocks[2] as $block ) {
					$spec = self::label_value( $block );
					if ( ! $spec ) {
						return $m[0];
					}
					$rows[] = $spec;
				}
				foreach ( $rows as $spec ) {
					$specs[] = $spec;
				}
				return "\n";
			},
			$html
		);

		if ( $specs ) {
			$html = self::remove_orphan_spec_headings( $html );
		}

		return [
			'html'  => self::tidy_whitespace( $html ),
			'specs' => self::merge_specs( [], $specs ),
		];
	}

	/**
	 * Short description → excerpt + specs. Handles HTML, bare "<strong>Label:</strong> value"
	 * lines separated by blank lines, and the plain-text "مشخصه مقدار" pseudo-table.
	 *
	 * @return array{excerpt:string,specs:array<int,array{0:string,1:string}>}
	 */
	public function short_description( string $html ): array {
		$html = trim( str_replace( [ "\r\n", "\r" ], "\n", $html ) );
		if ( '' === $html ) {
			return [
				'excerpt' => '',
				'specs'   => [],
			];
		}

		$specs = [];
		if ( $html === wp_strip_all_tags( $html ) ) {
			$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $html ) ), 'strlen' ) );
			if ( $lines && preg_match( '/^(مشخصه|مشخصات|ویژگی)\s*(مقدار|توضیحات)$/u', Normalize::persian( $lines[0] ) ) ) {
				$rows = [];
				foreach ( array_slice( $lines, 1 ) as $line ) {
					$parts = preg_split( '/\t+|\s{2,}|\s+:\s+|\s*：\s*/u', $line, 2 );
					if ( ! is_array( $parts ) || 2 !== count( $parts ) ) {
						$rows = [];
						break;
					}
					$spec = self::spec_row( $parts[0], $parts[1] );
					if ( $spec ) {
						$rows[] = $spec;
					}
				}
				if ( $rows ) {
					return [
						'excerpt' => '',
						'specs'   => self::merge_specs( [], $rows ),
					];
				}
			}
			$paragraphs = array_filter( array_map( 'trim', preg_split( '/\n\s*\n/', $html ) ?: [] ), 'strlen' );
			return [
				'excerpt' => implode( "\n\n", array_map( static fn( string $p ): string => '<p>' . esc_html( $p ) . '</p>', $paragraphs ) ),
				'specs'   => [],
			];
		}

		$html     = $this->html( $html );
		$segments = preg_split( '/\n\s*\n|(?<=<\/p>)\n|(?<=<\/h[2-6]>)\n/u', $html ) ?: [ $html ];
		$kept     = [];
		foreach ( $segments as $segment ) {
			$segment = trim( $segment );
			if ( '' === $segment ) {
				continue;
			}
			$inner = $segment;
			if ( preg_match( '#^<(p|h[2-6])>(.*)</\1>$#is', $segment, $m ) ) {
				$inner = $m[2];
			}
			$spec = ( ! str_contains( $inner, '<li' ) && ! str_contains( $inner, '<table' ) ) ? self::label_value( $inner ) : null;
			if ( $spec ) {
				$specs[] = $spec;
				continue;
			}
			$kept[] = preg_match( '/^<(p|h[2-6]|ul|ol|table|blockquote|details)\b/i', $segment ) ? $segment : '<p>' . $segment . '</p>';
		}
		return [
			'excerpt' => implode( "\n\n", $kept ),
			'specs'   => self::merge_specs( [], $specs ),
		];
	}

	/**
	 * Drops every attribute not whitelisted, fixes links and images (one tag-processor pass).
	 */
	private function process_tags( string $html ): string {
		if ( ! class_exists( WP_HTML_Tag_Processor::class ) ) {
			return self::strip_attributes_regex( $html );
		}
		$p = new WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag() ) {
			if ( $p->is_tag_closer() ) {
				continue;
			}
			$tag     = strtolower( (string) $p->get_tag() );
			$allowed = self::ALLOWED_ATTRIBUTES[ $tag ] ?? [];
			$names   = $p->get_attribute_names_with_prefix( '' ) ?? [];
			foreach ( $names as $name ) {
				if ( ! in_array( strtolower( (string) $name ), $allowed, true ) ) {
					$p->remove_attribute( (string) $name );
				}
			}
			if ( 'a' === $tag ) {
				$this->process_link( $p );
			} elseif ( 'img' === $tag ) {
				$this->process_image( $p );
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * Links: old-host URLs become root-relative, utm_* dropped, external links nofollow,
	 * anchors without a real target are unwrapped later.
	 */
	private function process_link( WP_HTML_Tag_Processor $p ): void {
		$href = trim( (string) $p->get_attribute( 'href' ) );
		if ( '' === $href || '#' === $href || preg_match( '/^(javascript|data|vbscript):/i', $href ) ) {
			$p->remove_attribute( 'href' );
			$p->remove_attribute( 'rel' );
			$p->set_attribute( 'data-gtm-unwrap', '1' );
			return;
		}
		if ( preg_match( '/^(mailto|tel|sms):/i', $href ) ) {
			return;
		}
		$href = self::strip_tracking( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
		if ( in_array( $host, self::OLD_HOSTS, true ) ) {
			$path  = (string) wp_parse_url( $href, PHP_URL_PATH );
			$query = (string) wp_parse_url( $href, PHP_URL_QUERY );
			$frag  = (string) wp_parse_url( $href, PHP_URL_FRAGMENT );
			$path  = '' === $path ? '/' : $path;
			if ( str_starts_with( $path, '/wp-content/uploads/' ) && null !== $this->url_rewriter ) {
				$new = (string) call_user_func( $this->url_rewriter, $href );
				if ( '' !== $new ) {
					$path = $new;
				}
			}
			$href = $path . ( '' !== $query ? '?' . $query : '' ) . ( '' !== $frag ? '#' . $frag : '' );
			$p->set_attribute( 'href', $href );
			$p->remove_attribute( 'rel' );
			return;
		}
		if ( '' !== $host ) {
			$p->set_attribute( 'href', $href );
			$p->set_attribute( 'rel', 'nofollow noopener' );
			return;
		}
		$p->set_attribute( 'href', $href );
	}

	/**
	 * Images: rewrite old upload URLs; unresolvable ones are removed later.
	 */
	private function process_image( WP_HTML_Tag_Processor $p ): void {
		$src = trim( html_entity_decode( (string) $p->get_attribute( 'src' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $src || str_starts_with( $src, 'data:' ) ) {
			$p->set_attribute( 'data-gtm-remove', '1' );
			return;
		}
		if ( null !== $this->url_rewriter ) {
			$new = (string) call_user_func( $this->url_rewriter, $src );
			if ( '' === $new ) {
				$p->set_attribute( 'data-gtm-remove', '1' );
				return;
			}
			$src = $new;
		}
		$p->set_attribute( 'src', $src );
		$p->set_attribute( 'loading', 'lazy' );
		$p->set_attribute( 'decoding', 'async' );
	}

	/**
	 * Lowercases tag names and collapses the whitespace the tag processor leaves behind
	 * (`<p >`, `<a  href="…" >`), so the structural regexes below can rely on `<p>`, `<li>`, `<ul>`.
	 */
	public static function normalize_tags( string $html ): string {
		$html = (string) preg_replace_callback(
			'#<([a-zA-Z][a-zA-Z0-9]*)(\s[^<>]*)?>#',
			static function ( array $m ): string {
				$attrs = isset( $m[2] ) ? trim( (string) preg_replace( '/\s+/', ' ', $m[2] ) ) : '';
				$attrs = rtrim( $attrs, '/ ' );
				return '<' . strtolower( $m[1] ) . ( '' !== $attrs ? ' ' . $attrs : '' ) . '>';
			},
			$html
		);
		return (string) preg_replace_callback(
			'#</([a-zA-Z][a-zA-Z0-9]*)\s*>#',
			static fn( array $m ): string => '</' . strtolower( $m[1] ) . '>',
			$html
		);
	}

	/**
	 * `<a>question</a><p>answer</p>` pairs left by the old accordion → <details>.
	 */
	public static function faq_to_details( string $html ): string {
		return (string) preg_replace_callback(
			'#<a\b[^>]*data-gtm-unwrap="1"[^>]*>\s*(.+?)\s*</a>\s*((?:<p>.*?</p>\s*)+)#isu',
			static function ( array $m ): string {
				$question = self::text( $m[1] );
				if ( '' === $question || mb_strlen( $question ) > 300 ) {
					return $m[0];
				}
				return '<details>' . "\n" . '<summary>' . esc_html( $question ) . '</summary>' . "\n" . trim( $m[2] ) . "\n" . '</details>' . "\n";
			},
			$html
		);
	}

	/**
	 * `<strong>Label:</strong> value` (colon inside or outside the strong) → [ label, value ],
	 * or null when the fragment is not a spec row (no label, long value, nested blocks).
	 *
	 * @return array{0:string,1:string}|null
	 */
	public static function label_value( string $fragment ): ?array {
		$fragment = trim( $fragment );
		if ( preg_match( '#<(ul|ol|table|h[1-6]|p|li|details|img)\b#i', $fragment ) ) {
			return null;
		}
		// "<strong>برند</strong><strong>:</strong> value" → one strong run.
		$fragment = (string) preg_replace( '#</strong>(\s*)<strong>#i', '$1', $fragment );

		if ( preg_match( '#^<strong>\s*(.+?)\s*</strong>\s*([:：\-–]?)\s*(.*)$#isu', $fragment, $m ) ) {
			$label_raw = trim( $m[1] );
			$rest      = self::text( $m[3] );
			if ( '' === $rest && '' === $m[2] ) {
				// Entirely bold "Label: value".
				if ( preg_match( '/^([^:：]{1,' . self::MAX_SPEC_LABEL . '}?)\s*[:：]\s*(.+)$/su', self::text( $label_raw ), $mm ) ) {
					return self::spec_row( $mm[1], $mm[2] );
				}
				return null;
			}
			$has_colon = '' !== $m[2] || str_ends_with( $label_raw, ':' ) || str_ends_with( $label_raw, '：' );
			if ( ! $has_colon ) {
				return null; // "<strong>Bold phrase</strong> rest of sentence" is prose, not a spec.
			}
			return self::spec_row( $label_raw, $rest );
		}

		// Plain "Label: value" (the old builder's table rows unwrapped to text).
		$plain = self::text( $fragment );
		if ( preg_match( '/^([^:：.؟!،]{2,' . self::MAX_SPEC_LABEL . '}?)\s*[:：]\s*(.{1,' . self::MAX_SPEC_VALUE . '})$/su', $plain, $mm ) && ! preg_match( '#^(https?|mailto|tel|ftp)$#i', trim( $mm[1] ) ) ) {
			return self::spec_row( $mm[1], $mm[2] );
		}
		return null;
	}

	/**
	 * Validated spec row (label ≤ 50 chars, value ≤ 100 chars, not a sentence) or null.
	 *
	 * @return array{0:string,1:string}|null
	 */
	public static function spec_row( string $label, string $value ): ?array {
		$label = rtrim( self::text( $label ), ':： ' );
		$value = self::text( $value );
		if ( '' === $label || '' === $value ) {
			return null;
		}
		if ( mb_strlen( $label ) > self::MAX_SPEC_LABEL || mb_strlen( $value ) > self::MAX_SPEC_VALUE ) {
			return null;
		}
		if ( preg_match( '/[.؟?!]$/u', $value ) && mb_strlen( $value ) > 40 ) {
			return null;
		}
		// "وضعیت: اصل و اورجینال" is the title boilerplate again, not a specification.
		if ( TitleCleaner::has_boilerplate( $value ) ) {
			$stripped = TitleCleaner::short( $value );
			if ( $stripped === $value || '' === $stripped ) {
				return null;
			}
			$value = $stripped;
		}
		return [ $label, $value ];
	}

	/**
	 * Plain text of an HTML fragment: tags stripped, entities decoded, whitespace collapsed.
	 */
	public static function text( string $html ): string {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\u{00A0}", ' ', $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Merges spec lists, first occurrence of a label wins (labels compared normalised), capped.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function merge_specs( array $base, array $extra ): array {
		$out  = [];
		$seen = [];
		foreach ( array_merge( $base, $extra ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[0], $row[1] ) ) {
				continue;
			}
			$key = Normalize::text( (string) $row[0] );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = [ (string) $row[0], (string) $row[1] ];
			if ( count( $out ) >= ProductExtras::MAX_SPEC_ROWS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Headings that merely repeat the product title (the old pages opened with an H1 = title).
	 *
	 * @param string[] $titles Titles to compare against.
	 */
	private function remove_title_headings( string $html, array $titles ): string {
		if ( ! $titles ) {
			return $html;
		}
		$keys = array_map( static fn( string $t ): string => Normalize::text( $t ), $titles );
		return (string) preg_replace_callback(
			'#<h([2-6])>((?:(?!</?h[1-6]\b).)*)</h\1>#is',
			static function ( array $m ) use ( $keys ): string {
				$text = Normalize::text( self::text( $m[2] ) );
				return in_array( $text, $keys, true ) ? '' : $m[0];
			},
			$html
		);
	}

	/**
	 * Headings about "specifications" that lost their list: followed by another heading or the end.
	 */
	private static function remove_orphan_spec_headings( string $html ): string {
		return (string) preg_replace_callback(
			'#<h([2-6])>((?:(?!</?h[1-6]\b).)*)</h\1>\s*(?=<h[2-6]>|\z)#isu',
			static function ( array $m ): string {
				$text = Normalize::persian( self::text( $m[2] ) );
				return preg_match( '/مشخصات|ویژگی/u', $text ) ? '' : $m[0];
			},
			$html
		);
	}

	/**
	 * Header row detection for two-column tables ("مشخصات / توضیحات", "ویژگی / مقدار", …).
	 */
	private static function is_spec_header( string $a, string $b ): bool {
		$a = Normalize::persian( mb_strtolower( $a ) );
		$b = Normalize::persian( mb_strtolower( $b ) );
		return (bool) preg_match( '/^(مشخصات|مشخصه|ویژگی|ویژگی‌ها|ویژگی ها|عنوان|پارامتر|specification|specifications|feature|attribute|item)$/u', $a )
			|| (bool) preg_match( '/^(توضیحات|توضیح|مقدار|value|description|details)$/u', $b );
	}

	/**
	 * Removes utm_* and similar tracking parameters from a URL.
	 */
	private static function strip_tracking( string $url ): string {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		if ( '' === $query ) {
			return $url;
		}
		parse_str( $query, $params );
		$params = array_filter(
			$params,
			static fn( $k ): bool => ! preg_match( '/^(utm_|fbclid$|gclid$|mc_|ref$|srsltid$)/i', (string) $k ),
			ARRAY_FILTER_USE_KEY
		);
		$base = (string) preg_replace( '/\?.*$/s', '', $url );
		$frag = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );
		$base = (string) preg_replace( '/#.*$/s', '', $base );
		return $base . ( $params ? '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) : '' ) . ( '' !== $frag ? '#' . $frag : '' );
	}

	/**
	 * Empty paragraphs, headings, list items and inline wrappers (repeated until stable).
	 */
	private static function remove_empty_blocks( string $html ): string {
		$pattern = '#<(p|h[1-6]|li|ul|ol|strong|em|b|i|blockquote|details|summary|a)\b[^>]*>(?:\s|&nbsp;|&\#160;|\x{00A0}|<br\s*/?>)*</\1>#iu';
		for ( $i = 0; $i < 6; $i++ ) {
			$next = (string) preg_replace( $pattern, '', $html );
			if ( $next === $html ) {
				break;
			}
			$html = $next;
		}
		return $html;
	}

	/**
	 * Trims line ends, collapses blank-line runs.
	 */
	private static function tidy_whitespace( string $html ): string {
		$html = (string) preg_replace( '/[ \t\x{00A0}]+$/mu', '', $html );
		$html = (string) preg_replace( '/^[ \t]+/m', '', $html );
		$html = (string) preg_replace( '/(<(?:p|h[1-6]|li|td|th|summary)>)\s+/', '$1', $html );
		$html = (string) preg_replace( '/\s+(<\/(?:p|h[1-6]|li|td|th|summary)>)/', '$1', $html );
		$html = (string) preg_replace( '/\n{3,}/', "\n\n", $html );
		return trim( $html );
	}

	/**
	 * Regex fallback for the attribute whitelist when the tag processor is unavailable.
	 */
	private static function strip_attributes_regex( string $html ): string {
		return (string) preg_replace_callback(
			'#<([a-z][a-z0-9]*)(\s[^>]*)?>#i',
			static function ( array $m ): string {
				$tag     = strtolower( $m[1] );
				$allowed = self::ALLOWED_ATTRIBUTES[ $tag ] ?? [];
				$attrs   = '';
				if ( ! empty( $m[2] ) && $allowed && preg_match_all( '/([a-zA-Z:-]+)\s*=\s*("[^"]*"|\'[^\']*\')/', $m[2], $pairs, PREG_SET_ORDER ) ) {
					foreach ( $pairs as $pair ) {
						if ( in_array( strtolower( $pair[1] ), $allowed, true ) ) {
							$attrs .= ' ' . strtolower( $pair[1] ) . '=' . $pair[2];
						}
					}
				}
				return '<' . $tag . $attrs . '>';
			},
			$html
		);
	}
}
