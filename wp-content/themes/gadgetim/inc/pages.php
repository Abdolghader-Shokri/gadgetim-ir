<?php
/**
 * Pages layer (owned by the pages builder): content pages, the blog, site search, 404, comments.
 *
 * Templates
 *   page.php                     generic page: gradient hero, readable prose, table of contents for
 *                                long pages, help aside. Cart/checkout/account keep the plain branch.
 *   page-templates/about.php     brand story chapters, highlights/stats, promises, brands, CTA
 *   page-templates/contact.php   channel cards (contact.* settings), contact form, FAQ teaser
 *   page-templates/faq.php       searchable accordion built from the page's <details> blocks
 *   page-templates/guarantee.php the authenticity guarantee: seal, promises, verification steps
 *   single.php / index.php / archive.php / search.php / 404.php / comments.php
 *   templates/pages/*.php        the parts (hero, toc, help, cta, post-card, contact-*, faq-list, …)
 *
 * Page roles: the importer stores _gtm_page_role (about, contact, faq, privacy, terms) on the five
 * content pages; gadgetim_pages_install() creates the guarantee page with the role "guarantee".
 * A page without an explicitly chosen template uses page-templates/<role>.php when it exists.
 *
 * Contact form: admin-post action gadgetim_contact (nonce, honeypot "website", 2-second time trap,
 * Support\RateLimit 5 per hour per hashed IP for submissions that would send mail), wp_mail() to the
 * site admin (filter gadgetim_contact_recipient), composed in Persian through I18n\Languages::with_locale().
 * Without JS the form posts and redirects back with ?contact=sent|invalid|limited|error|expired
 * (+ &cf=<field keys>, never values); with JS (templates/pages/pages.js) it posts with ajax=1 and gets
 * JSON { ok, status, message, errors }.
 *
 * Script: templates/pages/pages.js (FAQ live search and deep links, contact form in place, copy
 * buttons, table-of-contents scroll spy) is enqueued as the script module "@gadgetim/pages" only by
 * the parts that need it (gadgetim_pages_use_script()); classic themes print modules in wp_footer,
 * so a part may ask for it while the body renders.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/* ---------------------------------------------------------------------------
 * Page roles and their templates
 * ------------------------------------------------------------------------- */

/**
 * Role of a page: _gtm_page_role meta (importer / installer), else the page-templates/<role>.php
 * template it uses. '' for other pages and post types.
 *
 * @param WP_Post|int|null $post Page.
 */
function gadgetim_page_role( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return '';
	}
	$role = sanitize_key( (string) get_post_meta( $post->ID, '_gtm_page_role', true ) );
	if ( '' === $role ) {
		$template = (string) get_page_template_slug( $post );
		if ( preg_match( '#^page-templates/([a-z0-9-]+)\.php$#', $template, $m ) ) {
			$role = $m[1];
		}
	}
	/**
	 * Filters the role of a page (about, contact, faq, guarantee, privacy, terms, '').
	 *
	 * @param string  $role Role.
	 * @param WP_Post $post Page.
	 */
	return (string) apply_filters( 'gadgetim_page_role', $role, $post );
}

/**
 * Roles with their own template file.
 *
 * @return array<string,string>
 */
function gadgetim_pages_role_templates(): array {
	return [
		'about'     => 'page-templates/about.php',
		'contact'   => 'page-templates/contact.php',
		'faq'       => 'page-templates/faq.php',
		'guarantee' => 'page-templates/guarantee.php',
	];
}

/**
 * page_template_hierarchy: a page without an explicitly chosen template uses its role's template.
 *
 * @param string[] $templates Candidate templates.
 * @return string[]
 */
function gadgetim_pages_template_hierarchy( array $templates ): array {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || '' !== (string) get_page_template_slug( $post ) ) {
		return $templates;
	}
	$map  = gadgetim_pages_role_templates();
	$role = gadgetim_page_role( $post );
	if ( isset( $map[ $role ] ) ) {
		array_unshift( $templates, $map[ $role ] );
	}
	return $templates;
}
add_filter( 'page_template_hierarchy', 'gadgetim_pages_template_hierarchy' );

/**
 * body_class: page-role-<role>.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function gadgetim_pages_body_class( array $classes ): array {
	if ( is_page() ) {
		$role = gadgetim_page_role( get_queried_object() );
		if ( '' !== $role ) {
			$classes[] = 'page-role-' . sanitize_html_class( $role );
		}
	}
	return $classes;
}
add_filter( 'body_class', 'gadgetim_pages_body_class' );

/**
 * body_class (late): drops WordPress's "search" class on search results. It collides with the
 * header's .search component (layout.css: display flex, max width), which turned <body> into a
 * flex row on /?s= and on the product search. "search-results" / "search-no-results" stay, and
 * "is-search" replaces it. Can go once the foundation scopes that component (e.g. form.search).
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function gadgetim_pages_body_class_search( array $classes ): array {
	if ( is_search() && in_array( 'search', $classes, true ) ) {
		$classes   = array_values( array_diff( $classes, [ 'search' ] ) );
		$classes[] = 'is-search';
	}
	return $classes;
}
add_filter( 'body_class', 'gadgetim_pages_body_class_search', 99 );

/**
 * Slugs to try when no page carries the role yet (the imported Persian slugs first).
 *
 * @return array<string,string[]>
 */
function gadgetim_pages_role_slugs(): array {
	return [
		'about'     => [ 'درباره-ما', 'about', 'about-us' ],
		'contact'   => [ 'ارتباط-با-ما', 'contact', 'contact-us' ],
		'faq'       => [ 'سوالات-متداول', 'faq' ],
		'guarantee' => [ 'ضمانت-اصالت-کالا', 'guarantee' ],
		'privacy'   => [ 'حفظ-حریم-خصوصی', 'privacy-policy', 'privacy' ],
		'terms'     => [ 'شرایط-و-قوانین', 'terms' ],
	];
}

/**
 * The published page of a role in the current language (null when none). Gated like every
 * page query, so an untranslated page is never linked from /en/ or /ar/.
 */
function gadgetim_pages_role_page( string $role ): ?WP_Post {
	static $memo = [];
	$key = $role . '|' . gadgetim_lang();
	if ( array_key_exists( $key, $memo ) ) {
		return $memo[ $key ];
	}
	$page = null;
	if ( 'guarantee' === $role ) {
		$id = (int) gadgetim_setting( 'trust.guarantee_page', 0 );
		if ( $id > 0 && 'publish' === get_post_status( $id ) && gadgetim_pages_in_lang( $id ) ) {
			$page = get_post( $id );
		}
	}
	if ( ! $page ) {
		$ids = get_posts(
			[
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_key'         => '_gtm_page_role', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $role, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			]
		);
		if ( $ids ) {
			$page = get_post( (int) $ids[0] );
		}
	}
	if ( ! $page ) {
		foreach ( gadgetim_pages_role_slugs()[ $role ] ?? [] as $slug ) {
			$found = get_page_by_path( $slug );
			if ( $found instanceof WP_Post && 'publish' === $found->post_status && gadgetim_pages_in_lang( (int) $found->ID ) ) {
				$page = $found;
				break;
			}
		}
	}
	$memo[ $key ] = $page instanceof WP_Post ? $page : null;
	return $memo[ $key ];
}

/**
 * Whether a page exists in the current language (always in Persian; elsewhere only when its
 * translation is published, the plugin's visibility rule), so /en/ and /ar/ never link to a 404.
 */
function gadgetim_pages_in_lang( int $post_id ): bool {
	$lang = gadgetim_lang();
	if ( 'fa' === $lang || ! class_exists( 'Gadgetim\Core\I18n\PostTranslations' ) ) {
		return true;
	}
	return 'published' === (string) get_post_meta( $post_id, Gadgetim\Core\I18n\PostTranslations::key( 'status', $lang ), true );
}

/**
 * URL of a role's page ('' when none).
 */
function gadgetim_pages_role_url( string $role ): string {
	$page = gadgetim_pages_role_page( $role );
	return $page ? (string) get_permalink( $page ) : '';
}

/**
 * Hero tone, icon, kicker and default lead line per page role (generic page template).
 *
 * @return array{tone:string,icon:string,kicker:string,lead:string}
 */
function gadgetim_pages_role_style( string $role ): array {
	$styles = [
		'privacy' => [
			'tone'   => 'cool',
			'icon'   => 'lock',
			'kicker' => __( 'Your privacy', 'gadgetim' ),
			'lead'   => __( 'How we collect, use and protect your information when you shop with us.', 'gadgetim' ),
		],
		'terms'   => [
			'tone'   => 'violet',
			'icon'   => 'layers',
			'kicker' => __( 'Rules and policies', 'gadgetim' ),
			'lead'   => __( 'The rules for using the site, placing orders, delivery and returns.', 'gadgetim' ),
		],
	];
	$style = $styles[ $role ] ?? [
		'tone'   => 'violet',
		'icon'   => 'sparkle',
		'kicker' => gadgetim_brand_name(),
		'lead'   => '',
	];
	/**
	 * Filters the hero style of the generic page template.
	 *
	 * @param array  $style tone (violet|cool|warm|green|gold|pink|night), icon, kicker, lead.
	 * @param string $role  Page role.
	 */
	return (array) apply_filters( 'gadgetim_pages_role_style', $style, $role ) + $style;
}

/* ---------------------------------------------------------------------------
 * Content helpers
 * ------------------------------------------------------------------------- */

/**
 * A post's content through the_content filters, exactly as the_content() would print it
 * (memoised per post: the FAQ JSON-LD in <head> and the template share one pass).
 */
function gadgetim_pages_post_html( ?WP_Post $post = null ): string {
	static $memo = [];
	$post = $post ?? get_post();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	if ( ! isset( $memo[ $post->ID ] ) ) {
		$html               = (string) apply_filters( 'the_content', get_the_content( null, false, $post ) );
		$memo[ $post->ID ] = str_replace( ']]>', ']]&gt;', $html );
	}
	return $memo[ $post->ID ];
}

/**
 * Comparable plain text: tags stripped, Arabic letter variants folded, digits in ASCII, lowercase.
 */
function gadgetim_pages_norm( string $text ): string {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( class_exists( 'Gadgetim\Core\Support\Normalize' ) && method_exists( 'Gadgetim\Core\Support\Normalize', 'text' ) ) {
		return trim( (string) Gadgetim\Core\Support\Normalize::text( $text ) );
	}
	$text = str_replace( [ 'ي', 'ك', 'ة', "\u{200C}" ], [ 'ی', 'ک', 'ه', '' ], $text );
	$text = (string) preg_replace( '/\s+/u', ' ', gadgetim_ascii_digits( $text ) );
	return trim( mb_strtolower( $text ) );
}

/**
 * An id for a heading: its words joined by hyphens (Persian letters kept), unique on the page.
 *
 * @param string              $text Heading text.
 * @param array<string,bool>  $used Ids already used.
 */
function gadgetim_pages_slug( string $text, array $used, string $prefix = '' ): string {
	$slug = (string) preg_replace( '/[^\p{L}\p{N}]+/u', '-', mb_strtolower( $text ) );
	$slug = trim( (string) preg_replace( '/-+/', '-', $slug ), '-' );
	if ( mb_strlen( $slug ) > 48 ) {
		$slug = rtrim( mb_substr( $slug, 0, 48 ), '-' );
	}
	if ( '' === $slug ) {
		$slug = 's';
	}
	$slug = $prefix . $slug;
	if ( preg_match( '/^[0-9-]/', $slug ) ) {
		$slug = 's-' . $slug;
	}
	$id = $slug;
	for ( $n = 2; isset( $used[ $id ] ); $n++ ) {
		$id = $slug . '-' . $n;
	}
	return $id;
}

/**
 * Prepares editor HTML for reading: headings h1 demoted to h2 (one H1 per page: the hero), a
 * first heading that repeats the page title dropped, ids on every h2/h3, and the table of contents
 * (the h2s when there are at least three, else the h3s; none below three).
 *
 * @return array{html:string,toc:array<int,array{id:string,text:string,level:int}>,minutes:int}
 */
function gadgetim_pages_prepare( string $html, string $title = '' ): array {
	$html = (string) preg_replace( '#<h1(\s[^>]*)?>(.*?)</h1>#is', '<h2$1>$2</h2>', $html );
	if ( '' !== $title && preg_match( '#^\s*<h([23])\b[^>]*>(.*?)</h\1>\s*#is', $html, $m ) && gadgetim_pages_norm( $m[2] ) === gadgetim_pages_norm( $title ) ) {
		$html = substr( $html, strlen( $m[0] ) );
	}
	$used  = [];
	$found = [
		2 => [],
		3 => [],
	];
	$html  = (string) preg_replace_callback(
		'#<h([23])(\s[^>]*)?>(.*?)</h\1>#is',
		static function ( array $m ) use ( &$used, &$found ): string {
			$level = (int) $m[1];
			$attrs = (string) ( $m[2] ?? '' );
			$text  = trim( html_entity_decode( wp_strip_all_tags( (string) $m[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' === $text ) {
				return $m[0];
			}
			if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs, $idm ) ) {
				$id = (string) $idm[2];
			} else {
				$id     = gadgetim_pages_slug( $text, $used );
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ]       = true;
			$found[ $level ][] = [
				'id'    => $id,
				'text'  => $text,
				'level' => $level,
			];
			return '<h' . $level . $attrs . '>' . $m[3] . '</h' . $level . '>';
		},
		$html
	);
	$toc = count( $found[2] ) >= 3 ? $found[2] : ( count( $found[3] ) >= 3 ? $found[3] : [] );
	return [
		'html'    => $html,
		'toc'     => $toc,
		'minutes' => gadgetim_pages_reading_minutes( $html ),
	];
}

/**
 * Reading time in minutes (≈ 200 words a minute, at least 1).
 */
function gadgetim_pages_reading_minutes( string $html ): int {
	$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( strip_shortcodes( $html ) ) ), -1, PREG_SPLIT_NO_EMPTY );
	return max( 1, (int) ceil( count( (array) $words ) / 200 ) );
}

/**
 * «۵ دقیقه مطالعه» in the language's digits.
 */
function gadgetim_pages_minutes_label( int $minutes ): string {
	/* translators: %s: number of minutes */
	return sprintf( _n( '%s min read', '%s min read', $minutes, 'gadgetim' ), gadgetim_number( $minutes ) );
}

/**
 * FAQ structure from editor HTML: <details><summary>question</summary>answer</details> blocks
 * (the importer's format), grouped by the h2/h3 headings placed between them. Content before the
 * first question is the intro, content after the last one the outro.
 *
 * @return array{intro:string,outro:string,count:int,groups:array<int,array{title:string,id:string,note:string,items:array<int,array{id:string,q:string,a:string,n:int}>}>}
 */
function gadgetim_pages_faq( string $html, string $prefix = 'q-' ): array {
	$out = [
		'intro'  => '',
		'outro'  => '',
		'count'  => 0,
		'groups' => [],
	];
	if ( ! preg_match_all( '#<details\b[^>]*>\s*<summary\b[^>]*>(.*?)</summary>(.*?)</details>#is', $html, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		return $out;
	}
	$used  = [];
	$pos   = 0;
	$group = [
		'title' => '',
		'id'    => '',
		'note'  => '',
		'items' => [],
	];
	$n     = 0;
	foreach ( $all as $i => $match ) {
		$start = (int) $match[0][1];
		$gap   = trim( substr( $html, $pos, $start - $pos ) );
		$pos   = $start + strlen( (string) $match[0][0] );
		if ( '' !== $gap ) {
			$heading = '';
			if ( preg_match_all( '#<h[2-4]\b[^>]*>(.*?)</h[2-4]>#is', $gap, $hm ) ) {
				$heading = trim( wp_strip_all_tags( (string) end( $hm[1] ) ) );
				$gap     = trim( (string) preg_replace( '#<h[2-4]\b[^>]*>.*?</h[2-4]>#is', '', $gap ) );
			}
			if ( 0 === $i ) {
				$out['intro'] = $gap;
				$gap          = '';
			}
			if ( '' !== $heading ) {
				if ( $group['items'] ) {
					$out['groups'][] = $group;
				}
				$id           = gadgetim_pages_slug( $heading, $used, 'topic-' );
				$used[ $id ]  = true;
				$group        = [
					'title' => $heading,
					'id'    => $id,
					'note'  => $gap,
					'items' => [],
				];
			} elseif ( '' !== $gap ) {
				$group['note'] .= $gap;
			}
		}
		$question = trim( html_entity_decode( wp_strip_all_tags( (string) $match[1][0] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $question ) {
			continue;
		}
		$id               = gadgetim_pages_slug( implode( ' ', array_slice( preg_split( '/\s+/u', $question ) ?: [], 0, 6 ) ), $used, $prefix );
		$used[ $id ]      = true;
		$group['items'][] = [
			'id' => $id,
			'q'  => $question,
			'a'  => trim( (string) $match[2][0] ),
			'n'  => ++$n,
		];
	}
	if ( $group['items'] ) {
		$out['groups'][] = $group;
	}
	$out['outro'] = trim( substr( $html, $pos ) );
	$out['count'] = $n;
	return $out;
}

/**
 * About page content as story chapters (split at the h2 headings) and numeric highlights: an
 * h2–h4 heading that starts with a number («1000+ محصول با کیفیت») and the paragraph after it
 * become a counting tile. A last heading without text (a slogan) becomes the closing line.
 *
 * @return array{chapters:array<int,array{title:string,html:string}>,highlights:array<int,array{n:int,suffix:string,label:string,text:string}>,closing:string}
 */
function gadgetim_pages_about( string $html ): array {
	$highlights = [];
	$html       = (string) preg_replace_callback(
		'#<h([2-4])\b[^>]*>\s*(.*?)\s*</h\1>\s*(?:<p\b[^>]*>(.*?)</p>)?#is',
		static function ( array $m ) use ( &$highlights ): string {
			$text = trim( html_entity_decode( wp_strip_all_tags( (string) $m[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( ! preg_match( '/^([0-9][0-9,٬.]*)\s*(\+|%|٪)?\s*(\S.*)$/u', gadgetim_ascii_digits( $text ), $nm ) ) {
				return $m[0];
			}
			$n = (int) str_replace( [ ',', '٬', '.' ], '', $nm[1] );
			if ( $n <= 0 ) {
				return $m[0];
			}
			$highlights[] = [
				'n'      => $n,
				'suffix' => '%' === ( $nm[2] ?? '' ) ? ( 'en' === gadgetim_lang() ? '%' : '٪' ) : (string) ( $nm[2] ?? '' ),
				'label'  => trim( (string) $nm[3] ),
				'text'   => trim( html_entity_decode( wp_strip_all_tags( (string) ( $m[3] ?? '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			];
			return '';
		},
		$html
	);
	$chapters = [];
	$current  = [
		'title' => '',
		'html'  => '',
	];
	foreach ( preg_split( '#(<h2\b[^>]*>.*?</h2>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [] as $part ) {
		if ( preg_match( '#^\s*<h2\b[^>]*>(.*?)</h2>\s*$#is', $part, $hm ) ) {
			if ( '' !== $current['title'] || '' !== trim( wp_strip_all_tags( $current['html'] ) ) ) {
				$chapters[] = $current;
			}
			$current = [
				'title' => trim( html_entity_decode( wp_strip_all_tags( (string) $hm[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
				'html'  => '',
			];
		} else {
			$current['html'] .= $part;
		}
	}
	if ( '' !== $current['title'] || '' !== trim( wp_strip_all_tags( $current['html'] ) ) ) {
		$chapters[] = $current;
	}
	$closing = '';
	$last    = end( $chapters );
	if ( $last && '' !== $last['title'] && '' === trim( wp_strip_all_tags( $last['html'] ) ) ) {
		$closing = $last['title'];
		array_pop( $chapters );
	}
	$chapters = array_values( array_filter( $chapters, static fn( array $c ): bool => '' !== trim( wp_strip_all_tags( $c['html'] ) ) ) );
	return [
		'chapters'   => $chapters,
		'highlights' => $highlights,
		'closing'    => $closing,
	];
}

/**
 * Parsed FAQ of the FAQ page (the contact and guarantee pages show a few of its questions).
 *
 * @return array{intro:string,outro:string,count:int,groups:array}
 */
function gadgetim_pages_faq_of_page(): array {
	$page = gadgetim_pages_role_page( 'faq' );
	if ( ! $page || post_password_required( $page ) ) {
		return gadgetim_pages_faq( '' );
	}
	return gadgetim_pages_faq( gadgetim_pages_post_html( $page ) );
}

/**
 * Questions of a parsed FAQ as one flat list, optionally only those whose text holds one of the
 * keywords (normalised match), at most $limit.
 *
 * @param array    $faq      Parsed FAQ.
 * @param string[] $keywords Keywords ([] = all).
 * @return array<int,array{id:string,q:string,a:string,n:int}>
 */
function gadgetim_pages_faq_pick( array $faq, array $keywords = [], int $limit = 3 ): array {
	$picked = [];
	$needle = array_values( array_filter( array_map( 'gadgetim_pages_norm', $keywords ) ) );
	foreach ( (array) ( $faq['groups'] ?? [] ) as $group ) {
		foreach ( (array) $group['items'] as $item ) {
			if ( $needle ) {
				$hay = gadgetim_pages_norm( $item['q'] . ' ' . $item['a'] );
				$hit = false;
				foreach ( $needle as $word ) {
					if ( str_contains( $hay, $word ) ) {
						$hit = true;
						break;
					}
				}
				if ( ! $hit ) {
					continue;
				}
			}
			$picked[] = $item;
			if ( count( $picked ) >= $limit ) {
				return $picked;
			}
		}
	}
	return $picked;
}

/**
 * Escaped text with the words of a search query wrapped in <mark> (Arabic/Persian letter
 * variants and digit sets matched alike).
 */
function gadgetim_pages_highlight( string $text, string $query ): string {
	$text  = trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	$words = array_filter( preg_split( '/\s+/u', trim( $query ) ) ?: [], static fn( string $w ): bool => mb_strlen( $w ) >= 2 );
	if ( ! $words ) {
		return esc_html( $text );
	}
	$classes = [
		'ی' => '[یيى]',
		'ي' => '[یيى]',
		'ک' => '[کك]',
		'ك' => '[کك]',
		'ه' => '[هة]',
	];
	$digits  = [ '0' => '[0۰٠]', '1' => '[1۱١]', '2' => '[2۲٢]', '3' => '[3۳٣]', '4' => '[4۴٤]', '5' => '[5۵٥]', '6' => '[6۶٦]', '7' => '[7۷٧]', '8' => '[8۸٨]', '9' => '[9۹٩]' ];
	$parts   = [];
	foreach ( array_slice( $words, 0, 6 ) as $word ) {
		$pattern = '';
		foreach ( preg_split( '//u', gadgetim_ascii_digits( $word ), -1, PREG_SPLIT_NO_EMPTY ) ?: [] as $char ) {
			$pattern .= $classes[ $char ] ?? ( $digits[ $char ] ?? preg_quote( $char, '/' ) );
			$pattern .= "\u{200C}?";
		}
		$parts[] = $pattern;
	}
	$regex = '/(' . implode( '|', $parts ) . ')/iu';
	$out   = '';
	foreach ( preg_split( $regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [ $text ] as $k => $piece ) {
		$out .= 1 === $k % 2 ? '<mark>' . esc_html( $piece ) . '</mark>' : esc_html( $piece );
	}
	return $out;
}

/**
 * A short excerpt around the first match of the query (plain text, ≈ 34 words).
 */
function gadgetim_pages_search_excerpt( WP_Post $post, string $query ): string {
	$text = has_excerpt( $post ) ? (string) $post->post_excerpt : (string) $post->post_content;
	$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( $text ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	$norm = gadgetim_pages_norm( $text );
	$word = (string) ( preg_split( '/\s+/u', gadgetim_pages_norm( $query ) ) ?: [ '' ] )[0];
	$at   = '' !== $word ? mb_strpos( $norm, $word ) : false;
	if ( false !== $at && $at > 120 ) {
		// Start a few words before the match (positions are close enough after normalisation).
		$space = mb_strrpos( mb_substr( $text, 0, max( 0, $at - 60 ) ), ' ' );
		$text  = '…' . mb_substr( $text, false === $space ? 0 : $space + 1 );
	}
	return wp_trim_words( $text, 34, '…' );
}

/* ---------------------------------------------------------------------------
 * Data for the parts
 * ------------------------------------------------------------------------- */

/**
 * In-stock catalogue products, best sellers first, then the most viewed (cached per language
 * with the navigation data; the version bumps on product saves).
 *
 * @return int[]
 */
function gadgetim_pages_popular_ids( int $limit = 6 ): array {
	if ( ! post_type_exists( 'product' ) || ! function_exists( 'gadgetim_nav_cached' ) ) {
		return [];
	}
	$ids = gadgetim_nav_cached(
		'pages_popular',
		static function (): array {
			$args = [
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => 300,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => '_stock_status',
						'value' => 'instock',
					],
				],
			];
			if ( taxonomy_exists( 'product_visibility' ) ) {
				$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => [ 'exclude-from-catalog' ],
						'operator' => 'NOT IN',
					],
				];
			}
			$ids = array_map( 'intval', (array) ( new WP_Query( $args ) )->posts );
			update_meta_cache( 'post', $ids );
			$score = [];
			foreach ( $ids as $id ) {
				$score[ $id ] = [ (int) get_post_meta( $id, 'total_sales', true ), (int) get_post_meta( $id, '_gtm_views', true ), $id ];
			}
			usort( $ids, static fn( int $a, int $b ): int => $score[ $b ] <=> $score[ $a ] );
			return array_slice( $ids, 0, 24 );
		}
	);
	return array_slice( array_map( 'intval', $ids ), 0, max( 1, $limit ) );
}

/**
 * Live catalogue numbers: products, brands with products, categories with products.
 *
 * @return array{products:int,brands:int,categories:int}
 */
function gadgetim_pages_catalog_counts(): array {
	$counts = [
		'products'   => 0,
		'brands'     => 0,
		'categories' => 0,
	];
	if ( post_type_exists( 'product' ) ) {
		$counts['products'] = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	}
	if ( function_exists( 'gadgetim_brands' ) ) {
		$counts['brands'] = count( gadgetim_brands( 200 ) );
	}
	if ( function_exists( 'gadgetim_category_terms' ) ) {
		$counts['categories'] = count( array_filter( gadgetim_category_terms(), static fn( WP_Term $t ): bool => gadgetim_term_count( $t ) > 0 ) );
	}
	return $counts;
}

/**
 * A per-language list setting only when it exists in the current language (gadgetim_list_setting()
 * falls back to Persian, which would put Persian chips on /en/).
 *
 * @return string[]
 */
function gadgetim_pages_own_list( string $path ): array {
	$raw  = gadgetim_setting( $path, [] );
	$lang = gadgetim_lang();
	if ( 'fa' !== $lang && is_array( $raw ) && ! array_is_list( $raw ) && ! array_filter( (array) ( $raw[ $lang ] ?? [] ) ) ) {
		return [];
	}
	return gadgetim_list_setting( $path );
}

/**
 * Days of the return window from trust.return_window («۷ روز ضمانت بازگشت کالا» → 7; 0 when none).
 */
function gadgetim_pages_return_days(): int {
	$text = gadgetim_ascii_digits( wp_strip_all_tags( gadgetim_t( 'trust.return_window' ) ) );
	return preg_match( '/(\d{1,3})/', $text, $m ) ? (int) $m[1] : 0;
}

/**
 * The promise cards (trust.badges setting) with an icon and a line of explanation each.
 *
 * @return array<int,array{icon:string,title:string,text:string,c1:string,c2:string}>
 */
function gadgetim_pages_promises(): array {
	$kinds = [
		'shield' => [ [ 'اصل', 'اورجینال', 'اصالت', 'genuine', 'original', 'authentic', 'أصل' ], __( 'Every product is checked for authenticity and quality before it ships.', 'gadgetim' ), '#6366f1', '#9333ea' ],
		'return' => [ [ 'بازگشت', 'مرجوع', 'return', 'إرجاع' ], __( 'A clear return process if the product does not match its description.', 'gadgetim' ), '#10b981', '#0d9488' ],
		'truck'  => [ [ 'ارسال', 'تحویل', 'delivery', 'shipping', 'توصيل', 'شحن' ], __( 'Safe, protective packaging and delivery across Iran.', 'gadgetim' ), '#0ea5e9', '#4f46e5' ],
		'chat'   => [ [ 'پشتیبانی', 'واتساپ', 'تلگرام', 'مشاوره', 'support', 'whatsapp', 'دعم' ], __( 'Advice before you buy and help after it, on WhatsApp and Telegram.', 'gadgetim' ), '#22c55e', '#059669' ],
		'lock'   => [ [ 'پرداخت', 'امن', 'payment', 'secure', 'دفع' ], __( 'Secure payment through trusted bank gateways.', 'gadgetim' ), '#ec4899', '#e11d48' ],
	];
	$items = [];
	$taken = [];
	foreach ( gadgetim_list_setting( 'trust.badges' ) as $badge ) {
		$hay  = mb_strtolower( $badge );
		$kind = '';
		foreach ( $kinds as $name => $def ) {
			if ( isset( $taken[ $name ] ) ) {
				continue;
			}
			foreach ( $def[0] as $needle ) {
				if ( str_contains( $hay, $needle ) ) {
					$kind = $name;
					break 2;
				}
			}
		}
		if ( '' === $kind ) {
			$kind = 'sparkle';
		}
		$taken[ $kind ] = true;
		$def            = $kinds[ $kind ] ?? [ [], '', '#f59e0b', '#ec4899' ];
		$items[]        = [
			'icon'  => $kind,
			'title' => $badge,
			'text'  => (string) $def[1],
			'c1'    => (string) $def[2],
			'c2'    => (string) $def[3],
		];
	}
	if ( ! isset( $taken['lock'] ) && count( $items ) < 5 ) {
		$items[] = [
			'icon'  => 'lock',
			'title' => __( 'Secure payment', 'gadgetim' ),
			'text'  => (string) $kinds['lock'][1],
			'c1'    => '#ec4899',
			'c2'    => '#e11d48',
		];
	}
	/**
	 * Filters the promise cards of the about and guarantee pages.
	 *
	 * @param array $items Items: icon, title, text, c1, c2.
	 */
	return (array) apply_filters( 'gadgetim_pages_promises', $items );
}

/**
 * Contact channels from the settings, in the order they are shown.
 *
 * @return array<int,array{key:string,icon:string,label:string,value:string,href:string,external:bool,action:string,copy:string,hint:string}>
 */
function gadgetim_pages_contact_channels(): array {
	$items   = [];
	$phone   = gadgetim_contact( 'phone' );
	$wa_raw  = gadgetim_contact( 'whatsapp' );
	$wa      = gadgetim_whatsapp_url( __( 'Hello! I have a question for Gadgetim.', 'gadgetim' ) );
	$tg      = gadgetim_telegram_url();
	$ig      = gadgetim_instagram_url();
	$email   = gadgetim_contact( 'email' );
	$address = trim( wp_strip_all_tags( gadgetim_t( 'contact.address' ) ) );
	$hours   = trim( wp_strip_all_tags( gadgetim_t( 'contact.hours' ) ) );

	if ( '' !== $wa ) {
		$items[] = [
			'key'      => 'whatsapp',
			'icon'     => 'chat',
			'label'    => __( 'WhatsApp', 'gadgetim' ),
			'value'    => gadgetim_phone_display( '' !== $wa_raw ? $wa_raw : $phone ),
			'href'     => $wa,
			'external' => true,
			'action'   => __( 'Start a chat', 'gadgetim' ),
			'copy'     => '',
			'hint'     => __( 'The quickest way to reach us', 'gadgetim' ),
		];
	}
	if ( '' !== $phone ) {
		$items[] = [
			'key'      => 'phone',
			'icon'     => 'phone',
			'label'    => __( 'Phone', 'gadgetim' ),
			'value'    => gadgetim_phone_display( $phone ),
			'href'     => gadgetim_tel_href( $phone ),
			'external' => false,
			'action'   => __( 'Call now', 'gadgetim' ),
			'copy'     => gadgetim_ascii_digits( $phone ),
			'hint'     => '' !== $hours ? $hours : __( 'Call or send a text message', 'gadgetim' ),
		];
	}
	if ( '' !== $tg ) {
		$items[] = [
			'key'      => 'telegram',
			'icon'     => 'telegram',
			'label'    => __( 'Telegram', 'gadgetim' ),
			'value'    => '<bdi dir="ltr">' . esc_html( '@' . ltrim( (string) wp_parse_url( $tg, PHP_URL_PATH ), '/' ) ) . '</bdi>',
			'href'     => $tg,
			'external' => true,
			'action'   => __( 'Open Telegram', 'gadgetim' ),
			'copy'     => '',
			'hint'     => '',
		];
	}
	if ( '' !== $ig ) {
		$items[] = [
			'key'      => 'instagram',
			'icon'     => 'insta',
			'label'    => __( 'Instagram', 'gadgetim' ),
			'value'    => '<bdi dir="ltr">' . esc_html( '@' . trim( (string) wp_parse_url( $ig, PHP_URL_PATH ), '/' ) ) . '</bdi>',
			'href'     => $ig,
			'external' => true,
			'action'   => __( 'See our page', 'gadgetim' ),
			'copy'     => '',
			'hint'     => '',
		];
	}
	if ( '' !== $email && is_email( $email ) ) {
		$items[] = [
			'key'      => 'email',
			'icon'     => 'mail',
			'label'    => __( 'Email', 'gadgetim' ),
			'value'    => '<bdi dir="ltr">' . esc_html( $email ) . '</bdi>',
			'href'     => 'mailto:' . $email,
			'external' => false,
			'action'   => __( 'Write an email', 'gadgetim' ),
			'copy'     => $email,
			'hint'     => '',
		];
	}
	if ( '' !== $address ) {
		$items[] = [
			'key'      => 'address',
			'icon'     => 'pin',
			'label'    => __( 'Address', 'gadgetim' ),
			'value'    => esc_html( $address ),
			'href'     => '',
			'external' => false,
			'action'   => '',
			'copy'     => $address,
			'hint'     => '',
		];
	}
	if ( '' !== $hours && '' === $phone ) {
		$items[] = [
			'key'      => 'hours',
			'icon'     => 'clock',
			'label'    => __( 'Opening hours', 'gadgetim' ),
			'value'    => esc_html( $hours ),
			'href'     => '',
			'external' => false,
			'action'   => '',
			'copy'     => '',
			'hint'     => '',
		];
	}
	/**
	 * Filters the contact channel cards (contact page, help asides).
	 *
	 * @param array $items Items.
	 */
	return (array) apply_filters( 'gadgetim_pages_contact_channels', $items );
}

/**
 * Links to the help and policy pages, the current page left out.
 *
 * @return array<int,array{label:string,url:string,icon:string}>
 */
function gadgetim_pages_help_links(): array {
	$current = is_singular() ? (int) get_queried_object_id() : 0;
	$defs    = [
		'faq'       => [ __( 'Frequently asked questions', 'gadgetim' ), 'info' ],
		'guarantee' => [ __( 'Authenticity guarantee', 'gadgetim' ), 'shield' ],
		'contact'   => [ __( 'Contact us', 'gadgetim' ), 'phone' ],
		'terms'     => [ __( 'Terms and conditions', 'gadgetim' ), 'layers' ],
		'privacy'   => [ __( 'Privacy policy', 'gadgetim' ), 'lock' ],
		'about'     => [ __( 'About Gadgetim', 'gadgetim' ), 'sparkle' ],
	];
	$links   = [];
	foreach ( $defs as $role => [ $label, $icon ] ) {
		$page = gadgetim_pages_role_page( $role );
		if ( ! $page || (int) $page->ID === $current ) {
			continue;
		}
		$links[] = [
			'label' => $label,
			'url'   => (string) get_permalink( $page ),
			'icon'  => $icon,
		];
	}
	return $links;
}

/* ---------------------------------------------------------------------------
 * Icons not in the sprite (24 px stroke icons, same drawing rules)
 * ------------------------------------------------------------------------- */

/**
 * Paths of the extra icons and of the drawn ("pathLength=1") promise icons.
 *
 * @return array<string,string>
 */
function gadgetim_pages_icon_paths(): array {
	return [
		'copy'     => '<rect x="8.4" y="8.4" width="11.4" height="11.4" rx="2.6"/><path d="M15.6 8.4V6.6a2.2 2.2 0 0 0-2.2-2.2H6.6a2.2 2.2 0 0 0-2.2 2.2v6.8a2.2 2.2 0 0 0 2.2 2.2h1.8"/>',
		'link'     => '<path d="M10.4 13.6a3.8 3.8 0 0 0 5.4 0l3-3a3.8 3.8 0 0 0-5.4-5.4l-1 1"/><path d="M13.6 10.4a3.8 3.8 0 0 0-5.4 0l-3 3a3.8 3.8 0 0 0 5.4 5.4l1-1"/>',
		'send'     => '<path d="M20.4 3.6 3.8 10.2l6.5 3 3 6.6 7.1-16.2Z"/><path d="m10.3 13.2 4.5-4.5"/>',
		'list'     => '<path d="M9.4 6.6h10.2M9.4 12h10.2M9.4 17.4h10.2"/><path d="M4.6 6.6h.01M4.6 12h.01M4.6 17.4h.01"/>',
		'question' => '<circle cx="12" cy="12" r="8.6"/><path d="M9.5 9.5a2.6 2.6 0 1 1 3.6 2.4c-.7.3-1.1.9-1.1 1.6v.5"/><path d="M12 16.8h.01"/>',
		'calendar' => '<rect x="4" y="5.4" width="16" height="14.6" rx="3"/><path d="M4 10h16M8.4 3.6v3.4M15.6 3.6v3.4"/>',
		'doc'      => '<path d="M13.8 3.6H7.4a2 2 0 0 0-2 2v12.8a2 2 0 0 0 2 2h9.2a2 2 0 0 0 2-2V8.4Z"/><path d="M13.8 3.6v4.8h4.8M8.8 12.6h6.4M8.8 16.2h4.2"/>',
		'receipt'  => '<path d="M6 3.6h12v16.8l-2.4-1.6-2.4 1.6-2.4-1.6-2.4 1.6L6 20.4Z"/><path d="M9 8.4h6M9 12h6M9 15.6h3.4"/>',
		'seal'     => '<path d="M12 3.2 14.1 5l2.7-.4.9 2.6 2.4 1.3-.6 2.7 1.3 2.4-2 1.9-.1 2.8-2.7.6-1.6 2.3L12 20l-2.4 1.2-1.6-2.3-2.7-.6-.1-2.8-2-1.9 1.3-2.4-.6-2.7 2.4-1.3.9-2.6 2.7.4Z"/><path d="m8.8 12.2 2.2 2.2 4.2-4.4"/>',
		'scan'     => '<path d="M4 8.4V6.2A2.2 2.2 0 0 1 6.2 4h2.2M15.6 4h2.2A2.2 2.2 0 0 1 20 6.2v2.2M20 15.6v2.2a2.2 2.2 0 0 1-2.2 2.2h-2.2M8.4 20H6.2A2.2 2.2 0 0 1 4 17.8v-2.2"/><path d="M7.6 12h8.8"/>',
		'user'     => '<circle cx="12" cy="8.2" r="3.9"/><path d="M4.6 20.4a7.4 7.4 0 0 1 14.8 0"/>',
		'shield'   => '<path pathLength="1" d="M12 3.2 5.2 6v5.4c0 4.3 2.9 7.9 6.8 9.4 3.9-1.5 6.8-5.1 6.8-9.4V6L12 3.2Z"/><path pathLength="1" d="m9 12.1 2.1 2.1 4-4.2"/>',
		'truck'    => '<path pathLength="1" d="M2.8 6.6h11v9.2h-11z"/><path pathLength="1" d="M13.8 9.4h3.7l3.7 3.8v2.6h-7.4"/><circle pathLength="1" cx="6.8" cy="17.6" r="1.9"/><circle pathLength="1" cx="17" cy="17.6" r="1.9"/>',
		'return'   => '<path pathLength="1" d="M4.3 12.4A7.8 7.8 0 1 0 6.6 6.4"/><path pathLength="1" d="M4.4 3.8v4.4h4.4"/><path pathLength="1" d="M10.2 9.4h3.9l-2.4 5.6"/>',
		'chat'     => '<path pathLength="1" d="M4.6 19.6 5.7 16A8.2 8.2 0 1 1 8.5 18.7l-3.9.9Z"/><path pathLength="1" d="M9.3 9.2c.3 2.4 2.2 4.6 4.7 5.3l1.2-1.2-1.8-1.1-.9.6a4.2 4.2 0 0 1-1.9-1.9l.6-.9-1.1-1.8-.8 1Z"/>',
		'lock'     => '<rect pathLength="1" x="5" y="10.4" width="14" height="10.2" rx="2.6"/><path pathLength="1" d="M8.2 10.4V8a3.8 3.8 0 0 1 7.6 0v2.4"/><path pathLength="1" d="M12 14.3v2.4"/>',
		'box'      => '<path pathLength="1" d="m12 3.4 7.6 4.2v8.8L12 20.6l-7.6-4.2V7.6L12 3.4Z"/><path pathLength="1" d="m4.6 7.8 7.4 4.1 7.4-4.1M12 11.9v8.6"/>',
		'tag'      => '<path pathLength="1" d="M3.6 12.5V5.1a1.5 1.5 0 0 1 1.5-1.5h7.4l7.9 7.9-8.9 8.9-7.9-7.9Z"/><path pathLength="1" d="M8 8h.01"/>',
		'sparkle'  => '<path pathLength="1" d="M11 3.5c.7 4.6 2.7 6.6 7.3 7.3-4.6.7-6.6 2.7-7.3 7.3-.7-4.6-2.7-6.6-7.3-7.3 4.6-.7 6.6-2.7 7.3-7.3Z"/><path pathLength="1" d="M18.6 15.2c.3 1.6 1 2.3 2.6 2.6-1.6.3-2.3 1-2.6 2.6-.3-1.6-1-2.3-2.6-2.6 1.6-.3 2.3-1 2.6-2.6Z"/>',
		'eye'      => '<path pathLength="1" d="M2.8 12s3.4-6.4 9.2-6.4 9.2 6.4 9.2 6.4-3.4 6.4-9.2 6.4S2.8 12 2.8 12Z"/><circle pathLength="1" cx="12" cy="12" r="2.8"/>',
		'receipt2' => '<path pathLength="1" d="M6 3.6h12v16.8l-2.4-1.6-2.4 1.6-2.4-1.6-2.4 1.6L6 20.4Z"/><path pathLength="1" d="M9 8.4h6M9 12h6M9 15.6h3.4"/>',
	];
}

/**
 * An extra icon (or a drawn promise icon) as inline SVG; the sprite icon when the name is unknown.
 */
function gadgetim_pages_icon( string $name, string $class = '' ): string {
	$paths = gadgetim_pages_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return gadgetim_icon( $name, $class );
	}
	return '<svg class="' . esc_attr( trim( 'ic ' . $class ) ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/* ---------------------------------------------------------------------------
 * Script module
 * ------------------------------------------------------------------------- */

/**
 * Enqueues templates/pages/pages.js once (FAQ search, contact form, copy buttons, TOC spy).
 * Safe to call while the body renders: classic themes print script modules in wp_footer.
 */
function gadgetim_pages_use_script(): void {
	static $done = false;
	if ( $done || ! function_exists( 'wp_enqueue_script_module' ) ) {
		return;
	}
	$done = true;
	/**
	 * Filters the theme-relative path of the pages script module.
	 *
	 * @param string $path Path.
	 */
	$path = (string) apply_filters( 'gadgetim_pages_script', 'templates/pages/pages.js' );
	if ( ! is_readable( GADGETIM_THEME_DIR . $path ) ) {
		return;
	}
	wp_enqueue_script_module( '@gadgetim/pages', gadgetim_asset_url( $path ), [], gadgetim_asset_version( $path ) );
}

/* ---------------------------------------------------------------------------
 * Images: LCP of a single post, sizes contexts
 * ------------------------------------------------------------------------- */

/**
 * gadgetim_lcp_image: a single post's featured image is its LCP.
 *
 * @param array|null $lcp LCP image.
 * @return array|null
 */
function gadgetim_pages_lcp_image( $lcp ) {
	if ( null === $lcp && is_singular( 'post' ) ) {
		$thumb = (int) get_post_thumbnail_id( get_queried_object_id() );
		if ( $thumb > 0 ) {
			return [ $thumb, 'large', 'post' ];
		}
	}
	return $lcp;
}
add_filter( 'gadgetim_lcp_image', 'gadgetim_pages_lcp_image' );

/**
 * gadgetim_image_sizes_attr: the post page image and the blog card image.
 */
function gadgetim_pages_sizes_attr( string $sizes, string $context ): string {
	return match ( $context ) {
		'post'      => '(min-width: 1100px) 860px, calc(100vw - 32px)',
		'post-card' => '(min-width: 1100px) 420px, (min-width: 768px) 45vw, calc(100vw - 32px)',
		default     => $sizes,
	};
}
add_filter( 'gadgetim_image_sizes_attr', 'gadgetim_pages_sizes_attr', 10, 2 );

/* ---------------------------------------------------------------------------
 * Site search: products are listed in their own strip, the main loop shows posts and pages
 * ------------------------------------------------------------------------- */

/**
 * pre_get_posts (9, before the language gate): the site search (no post_type) lists posts and
 * pages only, without the shop's cart/checkout/account pages. Product searches are untouched.
 *
 * @param WP_Query $query Query.
 */
function gadgetim_pages_search_query( $query ): void {
	if ( ! $query instanceof WP_Query || is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$type = $query->get( 'post_type' );
	if ( ! empty( $type ) && 'any' !== $type ) {
		return;
	}
	$query->set( 'post_type', [ 'post', 'page' ] );
	if ( function_exists( 'wc_get_page_id' ) ) {
		$exclude = array_filter( array_map( 'intval', [ wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ), wc_get_page_id( 'shop' ) ] ), static fn( int $id ): bool => $id > 0 );
		if ( $exclude ) {
			$query->set( 'post__not_in', array_values( $exclude ) );
		}
	}
	$query->set( 'posts_per_page', 12 );
}
add_action( 'pre_get_posts', 'gadgetim_pages_search_query', 9 );

/**
 * Products matching a search query (language gate applied by the plugin): ids and the total.
 *
 * @return array{ids:int[],total:int}
 */
function gadgetim_pages_search_products( string $q, int $limit = 10 ): array {
	$q = trim( $q );
	if ( '' === $q || ! post_type_exists( 'product' ) ) {
		return [
			'ids'   => [],
			'total' => 0,
		];
	}
	$args = [
		'post_type'           => 'product',
		'post_status'         => 'publish',
		's'                   => $q,
		'posts_per_page'      => $limit,
		'fields'              => 'ids',
		'ignore_sticky_posts' => true,
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
	return [
		'ids'   => array_map( 'intval', (array) $query->posts ),
		'total' => (int) $query->found_posts,
	];
}

/* ---------------------------------------------------------------------------
 * Contact form
 * ------------------------------------------------------------------------- */

/**
 * Topics of the contact form (filter gadgetim_contact_subjects).
 *
 * @return array<string,string>
 */
function gadgetim_contact_subjects(): array {
	$subjects = [
		'product'   => __( 'A question about a product', 'gadgetim' ),
		'order'     => __( 'An order I placed', 'gadgetim' ),
		'guarantee' => __( 'Authenticity and guarantee', 'gadgetim' ),
		'return'    => __( 'Returns and exchanges', 'gadgetim' ),
		'business'  => __( 'Wholesale and partnership', 'gadgetim' ),
		'other'     => __( 'Something else', 'gadgetim' ),
	];
	/**
	 * Filters the contact form topics.
	 *
	 * @param array<string,string> $subjects key => label.
	 */
	return (array) apply_filters( 'gadgetim_contact_subjects', $subjects );
}

/**
 * Status messages of the contact form.
 *
 * @return array<string,string>
 */
function gadgetim_contact_messages(): array {
	return [
		'sent'    => __( 'Thank you! Your message has been sent. We will get back to you soon.', 'gadgetim' ),
		'invalid' => __( 'Please check the highlighted fields.', 'gadgetim' ),
		'limited' => __( 'You have sent several messages in a short time. Please try again later, or message us on WhatsApp.', 'gadgetim' ),
		'error'   => __( 'Your message could not be sent. Please try again, or contact us by phone or WhatsApp.', 'gadgetim' ),
		'expired' => __( 'This form has expired. Please reload the page and send it again.', 'gadgetim' ),
	];
}

/**
 * Field error messages of the contact form.
 *
 * @return array<string,string>
 */
function gadgetim_contact_field_errors(): array {
	return [
		'name'    => __( 'Please enter your name.', 'gadgetim' ),
		'phone'   => __( 'Please enter a valid phone number.', 'gadgetim' ),
		'email'   => __( 'Please enter a valid email address.', 'gadgetim' ),
		'reach'   => __( 'Enter a phone number or an email address so we can reply.', 'gadgetim' ),
		'message' => __( 'Please write your message (at least 10 characters).', 'gadgetim' ),
	];
}

/**
 * Validates posted contact data. Returns [ clean data, field errors ].
 *
 * @param array<string,mixed> $raw Unslashed $_POST.
 * @return array{0:array<string,string>,1:array<string,string>}
 */
function gadgetim_contact_validate( array $raw ): array {
	$messages = gadgetim_contact_field_errors();
	$subjects = gadgetim_contact_subjects();
	$data     = [
		'name'    => mb_substr( sanitize_text_field( (string) ( $raw['name'] ?? '' ) ), 0, 80 ),
		'phone'   => mb_substr( sanitize_text_field( gadgetim_ascii_digits( (string) ( $raw['phone'] ?? '' ) ) ), 0, 24 ),
		'email'   => mb_substr( sanitize_email( (string) ( $raw['email'] ?? '' ) ), 0, 120 ),
		'subject' => sanitize_key( (string) ( $raw['subject'] ?? '' ) ),
		'order'   => mb_substr( (string) preg_replace( '/[^A-Za-z0-9\-#]/', '', gadgetim_ascii_digits( (string) ( $raw['order'] ?? '' ) ) ), 0, 32 ),
		'message' => mb_substr( sanitize_textarea_field( (string) ( $raw['message'] ?? '' ) ), 0, 3000 ),
	];
	if ( ! isset( $subjects[ $data['subject'] ] ) ) {
		$data['subject'] = 'other';
	}
	$errors = [];
	if ( mb_strlen( trim( $data['name'] ) ) < 2 ) {
		$errors['name'] = $messages['name'];
	}
	$digits = (string) preg_replace( '/\D/', '', $data['phone'] );
	if ( '' !== $data['phone'] && ( strlen( $digits ) < 8 || strlen( $digits ) > 15 ) ) {
		$errors['phone'] = $messages['phone'];
	}
	$raw_email = trim( (string) ( $raw['email'] ?? '' ) );
	if ( '' !== $raw_email && ( '' === $data['email'] || ! is_email( $data['email'] ) ) ) {
		$errors['email'] = $messages['email'];
	}
	if ( '' === $data['phone'] && '' === $raw_email ) {
		$errors['phone'] = $messages['reach'];
		$errors['email'] = $messages['reach'];
	}
	if ( mb_strlen( trim( $data['message'] ) ) < 10 ) {
		$errors['message'] = $messages['message'];
	}
	return [ $data, $errors ];
}

/**
 * Whether one more message may be sent from this client (5 per hour, plugin token bucket; a
 * transient counter per hashed IP without the plugin).
 */
function gadgetim_contact_rate_ok(): bool {
	if ( class_exists( 'Gadgetim\Core\Support\RateLimit' ) ) {
		return Gadgetim\Core\Support\RateLimit::allow( 'contact', 5, HOUR_IN_SECONDS );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'gadgetim_cf_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return false;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Sends the message to the site admin (Persian e-mail, plain text, Reply-To the sender).
 *
 * @param array<string,string> $data Clean data.
 */
function gadgetim_contact_send( array $data ): bool {
	/**
	 * Filters the recipient of contact form messages (default: the site admin e-mail).
	 *
	 * @param string $to   Recipient.
	 * @param array  $data Message data.
	 */
	$to = (string) apply_filters( 'gadgetim_contact_recipient', (string) get_option( 'admin_email' ), $data );
	if ( ! is_email( $to ) ) {
		return false;
	}
	$lang    = gadgetim_lang();
	$page    = wp_get_referer() ? (string) wp_get_referer() : home_url( '/' );
	$compose = static function () use ( $data, $lang, $page ): array {
		$subjects = gadgetim_contact_subjects();
		$topic    = $subjects[ $data['subject'] ] ?? $data['subject'];
		$lines    = [
			/* translators: %s: shop name */
			sprintf( __( 'A new message from the contact form of %s', 'gadgetim' ), gadgetim_brand_name() ),
			'',
			__( 'Name', 'gadgetim' ) . ': ' . $data['name'],
			__( 'Phone', 'gadgetim' ) . ': ' . ( '' !== $data['phone'] ? $data['phone'] : '—' ),
			__( 'Email', 'gadgetim' ) . ': ' . ( '' !== $data['email'] ? $data['email'] : '—' ),
			__( 'Topic', 'gadgetim' ) . ': ' . $topic,
		];
		if ( '' !== $data['order'] ) {
			$lines[] = __( 'Order number', 'gadgetim' ) . ': ' . $data['order'];
		}
		$lines[] = __( 'Sent from', 'gadgetim' ) . ': ' . $page . ' (' . strtoupper( $lang ) . ')';
		$lines[] = __( 'Date', 'gadgetim' ) . ': ' . gadgetim_date( time(), 'datetime' );
		$lines[] = '';
		$lines[] = __( 'Message', 'gadgetim' ) . ':';
		$lines[] = $data['message'];
		/* translators: 1: shop name, 2: topic, 3: sender name */
		$subject = sprintf( __( '[%1$s] %2$s — %3$s', 'gadgetim' ), gadgetim_brand_name(), $topic, $data['name'] );
		return [ $subject, implode( "\n", $lines ) ];
	};
	if ( class_exists( 'Gadgetim\Core\I18n\Languages' ) && method_exists( 'Gadgetim\Core\I18n\Languages', 'with_locale' ) ) {
		[ $subject, $body ] = Gadgetim\Core\I18n\Languages::with_locale( 'fa', $compose );
	} else {
		[ $subject, $body ] = $compose();
	}
	$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
	if ( '' !== $data['email'] && is_email( $data['email'] ) ) {
		$name      = trim( (string) preg_replace( '/[\r\n"<>,;:]+/', ' ', $data['name'] ) );
		$headers[] = 'Reply-To: ' . ( '' !== $name ? $name . ' ' : '' ) . '<' . $data['email'] . '>';
	}
	return (bool) wp_mail( $to, wp_specialchars_decode( $subject, ENT_QUOTES ), $body, $headers );
}

/**
 * Where a non-JS submission returns: the referring page on this site, else the contact page.
 */
function gadgetim_contact_back_url(): string {
	$fallback = gadgetim_pages_role_url( 'contact' );
	$fallback = '' !== $fallback ? $fallback : home_url( '/' );
	$back     = wp_get_referer();
	$back     = $back ? wp_validate_redirect( (string) $back, $fallback ) : $fallback;
	return remove_query_arg( [ 'contact', 'cf' ], (string) $back );
}

/**
 * admin-post(_nopriv)_gadgetim_contact.
 */
function gadgetim_contact_handle(): void {
	$ajax   = isset( $_POST['ajax'] ) && '1' === $_POST['ajax']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only picks the response format; the nonce is checked below.
	$finish = static function ( string $status, array $errors = [] ) use ( $ajax ): void {
		$messages = gadgetim_contact_messages();
		if ( $ajax ) {
			$codes = [
				'sent'    => 200,
				'invalid' => 422,
				'limited' => 429,
				'expired' => 403,
			];
			nocache_headers();
			wp_send_json(
				[
					'ok'      => 'sent' === $status,
					'status'  => $status,
					'message' => $messages[ $status ] ?? $messages['error'],
					'errors'  => (object) $errors,
				],
				$codes[ $status ] ?? 500
			);
		}
		$url = add_query_arg( 'contact', $status, gadgetim_contact_back_url() );
		if ( $errors ) {
			$url = add_query_arg( 'cf', implode( ',', array_map( 'sanitize_key', array_keys( $errors ) ) ), $url );
		}
		wp_safe_redirect( $url . '#contact-form', 303 );
		exit;
	};

	if ( ! isset( $_POST['_gtm_contact'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_gtm_contact'] ) ), 'gadgetim_contact' ) ) {
		$finish( 'expired' );
	}
	// Honeypot: people never see this field; bots fill every field. Pretend success, send nothing.
	if ( ! empty( $_POST['website'] ) ) {
		$finish( 'sent' );
	}
	// Time trap: a form sent within two seconds of rendering was not typed by a person.
	$rendered = isset( $_POST['_gtm_t'] ) ? absint( wp_unslash( $_POST['_gtm_t'] ) ) : 0;
	if ( $rendered <= 0 || time() - $rendered < 2 ) {
		$finish( 'error' );
	}

	[ $data, $errors ] = gadgetim_contact_validate( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitised in gadgetim_contact_validate().
	if ( $errors ) {
		$finish( 'invalid', $errors );
	}
	if ( ! gadgetim_contact_rate_ok() ) {
		$finish( 'limited' );
	}
	$sent = gadgetim_contact_send( $data );
	/**
	 * Fires after a contact form message was processed.
	 *
	 * @param array $data Clean message data (name, phone, email, subject, order, message).
	 * @param bool  $sent Whether wp_mail() accepted it.
	 */
	do_action( 'gadgetim_contact_submitted', $data, $sent );
	$finish( $sent ? 'sent' : 'error' );
}
add_action( 'admin_post_gadgetim_contact', 'gadgetim_contact_handle' );
add_action( 'admin_post_nopriv_gadgetim_contact', 'gadgetim_contact_handle' );

/* ---------------------------------------------------------------------------
 * The guarantee page («ضمانت اصالت کالا»)
 * ------------------------------------------------------------------------- */

/**
 * Seed content of the guarantee page (Persian, the shop's language; the client edits it in the
 * editor afterwards). Kept to the promises the site already makes (trust badges, FAQ, terms).
 */
function gadgetim_pages_guarantee_seed(): string {
	return implode(
		"\n\n",
		[
			'<p>در گجتیم همهٔ کالاها اصل و اورجینال هستند و با <strong>ضمانت اصالت کالا</strong> عرضه می‌شوند. هر محصول پیش از ارسال از نظر اصالت و سلامت بررسی می‌شود تا آنچه به دست شما می‌رسد، همان کالایی باشد که در سایت دیده‌اید.</p>',
			'<h2>ضمانت اصالت چه چیزهایی را پوشش می‌دهد؟</h2>',
			"<ul>\n<li>کالا اصل است و از مسیر معتبر تهیه شده است.</li>\n<li>کالا نو، در بسته‌بندی اصلی و پلمب ارسال می‌شود.</li>\n<li>مشخصات کالا با توضیحات صفحهٔ محصول مطابقت دارد.</li>\n</ul>",
			'<h2>اگر در اصالت کالا تردید داشتید</h2>',
			'<p>پیش از استفاده از کالا با ما در واتساپ یا تلفن تماس بگیرید و تصویر بسته‌بندی، برچسب‌ها و فاکتور را بفرستید. کارشناسان ما موضوع را بررسی می‌کنند و اگر کالا با توضیحات سایت مطابقت نداشته باشد، طبق شرایط بازگشت کالا تعویض یا وجه آن بازگردانده می‌شود.</p>',
			'<p>شرایط گارانتی هر محصول در صفحهٔ همان محصول یا همراه کالا آمده است. برای جزئیات بیشتر، صفحهٔ شرایط و قوانین و پرسش‌های متداول را ببینید.</p>',
		]
	);
}

/**
 * The page carrying a role (any status), 0 when none.
 */
function gadgetim_pages_find_role_page( string $role ): int {
	$ids = get_posts(
		[
			'post_type'     => 'page',
			'post_status'   => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'numberposts'   => 1,
			'fields'        => 'ids',
			'no_found_rows' => true,
			'orderby'       => 'ID',
			'order'         => 'ASC',
			'meta_key'      => '_gtm_page_role', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'    => $role, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		]
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Creates the guarantee page when missing (published, template page-templates/guarantee.php,
 * role "guarantee") and points the trust.guarantee_page setting at it when that is unset, so the
 * footer's customer-service fallback, the buy-box receipt and the seal band link to it.
 * Idempotent. Runs on after_switch_theme, from Gadgetim → Settings → Tools, and from the CLI:
 *   php tools/wp-bootstrap.php -r 'print_r( gadgetim_pages_install() );'
 *
 * @return string[] What was done (English, for the CLI and the tools notice).
 */
function gadgetim_pages_install(): array {
	$log = [];
	$id  = gadgetim_pages_find_role_page( 'guarantee' );
	if ( $id <= 0 ) {
		$existing = get_page_by_path( 'ضمانت-اصالت-کالا', OBJECT, 'page' );
		if ( $existing instanceof WP_Post ) {
			$id = (int) $existing->ID;
			update_post_meta( $id, '_gtm_page_role', 'guarantee' );
			$log[] = sprintf( 'Guarantee page #%d found by its slug; role set.', $id );
		}
	}
	if ( $id <= 0 ) {
		$result = wp_insert_post(
			wp_slash(
				[
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => 'ضمانت اصالت کالا',
					'post_name'      => 'ضمانت-اصالت-کالا',
					'post_content'   => gadgetim_pages_guarantee_seed(),
					'post_author'    => (int) get_current_user_id() > 0 ? (int) get_current_user_id() : 1,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
					'meta_input'     => [
						'_gtm_page_role'    => 'guarantee',
						'_wp_page_template' => 'page-templates/guarantee.php',
					],
				]
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return [ 'Could not create the guarantee page: ' . $result->get_error_message() ];
		}
		$id    = (int) $result;
		$log[] = sprintf( 'Guarantee page #%d created: %s', $id, rawurldecode( (string) get_permalink( $id ) ) );
	} else {
		$log[] = sprintf( 'Guarantee page #%d already exists (%s).', $id, (string) get_post_status( $id ) );
	}

	$current = (int) gadgetim_setting( 'trust.guarantee_page', 0 );
	if ( $current > 0 && 'publish' === get_post_status( $current ) ) {
		$log[] = sprintf( 'trust.guarantee_page already points at #%d.', $current );
	} elseif ( class_exists( 'Gadgetim\Core\Settings\Settings' ) && method_exists( 'Gadgetim\Core\Settings\Settings', 'set' ) ) {
		Gadgetim\Core\Settings\Settings::set( 'trust.guarantee_page', (string) $id );
		$log[] = sprintf( 'trust.guarantee_page set to #%d.', $id );
	} else {
		$log[] = 'gadgetim-core is not active: trust.guarantee_page was not set.';
	}
	if ( function_exists( 'gadgetim_flush_nav_cache' ) ) {
		gadgetim_flush_nav_cache();
	}
	return $log;
}
add_action( 'after_switch_theme', 'gadgetim_pages_install' );

/**
 * admin_init: "Create the guarantee page" in Gadgetim → Settings → Tools.
 */
function gadgetim_pages_register_tool(): void {
	if ( ! class_exists( 'Gadgetim\Core\Settings\Admin\Tools' ) || ! method_exists( 'Gadgetim\Core\Settings\Admin\Tools', 'add' ) ) {
		return;
	}
	Gadgetim\Core\Settings\Admin\Tools::add(
		'gadgetim-guarantee-page',
		__( 'Authenticity guarantee page', 'gadgetim' ),
		static fn(): string => implode( ' ', gadgetim_pages_install() ),
		'manage_options',
		[
			'description' => __( 'Creates the «ضمانت اصالت کالا» page with its template when it is missing, and links it from the trust settings.', 'gadgetim' ),
			'button'      => __( 'Create or link the page', 'gadgetim' ),
			'order'       => 60,
		]
	);
}
add_action( 'admin_init', 'gadgetim_pages_register_tool' );

/* ---------------------------------------------------------------------------
 * FAQ structured data (until the plugin's Seo module emits it)
 * ------------------------------------------------------------------------- */

/**
 * wp_head 30: FAQPage JSON-LD on the FAQ page.
 */
function gadgetim_pages_faq_jsonld(): void {
	if ( ! is_page() || class_exists( 'Gadgetim\Core\Seo\JsonLd' ) ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || 'faq' !== gadgetim_page_role( $post ) || post_password_required( $post ) ) {
		return;
	}
	/**
	 * Whether the theme prints FAQPage JSON-LD on the FAQ page.
	 *
	 * @param bool $print Print it.
	 */
	if ( ! apply_filters( 'gadgetim_pages_faq_jsonld', true ) ) {
		return;
	}
	$faq      = gadgetim_pages_faq( gadgetim_pages_post_html( $post ) );
	$entities = [];
	foreach ( $faq['groups'] as $group ) {
		foreach ( $group['items'] as $item ) {
			$entities[] = [
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $item['a'] ) ) ),
				],
			];
		}
	}
	if ( ! $entities ) {
		return;
	}
	$json = wp_json_encode(
		[
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		],
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
	);
	if ( false !== $json ) {
		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the HEX flags.
	}
}
add_action( 'wp_head', 'gadgetim_pages_faq_jsonld', 30 );

/* ---------------------------------------------------------------------------
 * Posts: terms, related posts, share links, comments
 * ------------------------------------------------------------------------- */

/**
 * Categories of a post worth showing (the default "Uncategorized" left out).
 *
 * @return WP_Term[]
 */
function gadgetim_pages_post_categories( ?WP_Post $post = null ): array {
	$post    = $post ?? get_post();
	$default = (int) get_option( 'default_category' );
	$terms   = $post ? get_the_category( $post->ID ) : [];
	return array_values( array_filter( (array) $terms, static fn( $t ): bool => $t instanceof WP_Term && (int) $t->term_id !== $default ) );
}

/**
 * Up to $limit published posts sharing a category with the post, newest first, else the newest.
 *
 * @return WP_Post[]
 */
function gadgetim_pages_related_posts( WP_Post $post, int $limit = 3 ): array {
	$cats = wp_list_pluck( gadgetim_pages_post_categories( $post ), 'term_id' );
	$args = [
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'post__not_in'        => [ $post->ID ],
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	];
	if ( $cats ) {
		$args['category__in'] = array_map( 'intval', $cats );
	}
	$posts = get_posts( $args + [ 'suppress_filters' => false ] );
	if ( count( $posts ) < $limit && $cats ) {
		unset( $args['category__in'] );
		$args['post__not_in']   = array_merge( [ $post->ID ], wp_list_pluck( $posts, 'ID' ) );
		$args['posts_per_page'] = $limit - count( $posts );
		$posts                  = array_merge( $posts, get_posts( $args + [ 'suppress_filters' => false ] ) );
	}
	return array_values( array_filter( $posts, static fn( $p ): bool => $p instanceof WP_Post ) );
}

/**
 * Share links of a post (no third-party scripts: plain URLs).
 *
 * @return array<int,array{key:string,label:string,url:string,icon:string}>
 */
function gadgetim_pages_share_links( WP_Post $post ): array {
	$url   = rawurldecode( (string) get_permalink( $post ) ); // Readable Persian URL in the shared message.
	$title = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return [
		[
			'key'   => 'whatsapp',
			'label' => __( 'Share on WhatsApp', 'gadgetim' ),
			'url'   => 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ),
			'icon'  => 'chat',
		],
		[
			'key'   => 'telegram',
			'label' => __( 'Share on Telegram', 'gadgetim' ),
			'url'   => 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
			'icon'  => 'telegram',
		],
	];
}

/**
 * Initials avatar (no Gravatar request): the first letter of a name in a gradient circle.
 */
function gadgetim_pages_avatar( string $name, string $class = 'cmt__av' ): string {
	$name   = trim( $name );
	$letter = '' !== $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '?';
	$hue    = abs( crc32( $name ) ) % 6;
	return '<span class="' . esc_attr( $class . ' av--' . $hue ) . '" aria-hidden="true">' . esc_html( $letter ) . '</span>';
}

/**
 * wp_list_comments() callback: initials avatar, author, Jalali date, moderation note, text, reply.
 *
 * @param WP_Comment $comment Comment.
 * @param array      $args    List arguments.
 * @param int        $depth   Depth.
 */
function gadgetim_pages_comment( $comment, $args, $depth ): void {
	if ( ! $comment instanceof WP_Comment ) {
		return;
	}
	$tag  = 'div' === ( $args['style'] ?? 'ol' ) ? 'div' : 'li';
	$time = (int) strtotime( $comment->comment_date_gmt . ' UTC' );
	?>
	<<?php echo esc_attr( $tag ); ?> id="comment-<?php echo esc_attr( (string) $comment->comment_ID ); ?>" <?php comment_class( 'cmt', $comment ); ?>>
		<article class="cmt__in">
			<header class="cmt__head">
				<?php echo gadgetim_pages_avatar( get_comment_author( $comment ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				<div class="cmt__who">
					<b class="cmt__name"><?php echo esc_html( get_comment_author( $comment ) ); ?></b>
					<time class="cmt__date" datetime="<?php echo esc_attr( gmdate( DATE_W3C, $time ) ); ?>"><?php echo esc_html( gadgetim_date( $time, 'medium' ) ); ?></time>
				</div>
				<?php if ( '0' === (string) $comment->comment_approved ) : ?>
					<?php echo gadgetim_badge( __( 'Awaiting moderation', 'gadgetim' ), 'warn', [ 'icon' => 'clock' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				<?php endif; ?>
			</header>
			<div class="cmt__body"><?php comment_text( $comment ); ?></div>
			<?php
			$reply = get_comment_reply_link(
				array_merge(
					(array) $args,
					[
						'depth'      => $depth,
						'max_depth'  => (int) ( $args['max_depth'] ?? 1 ),
						'reply_text' => gadgetim_icon( 'chat' ) . '<span>' . esc_html__( 'Reply', 'gadgetim' ) . '</span>',
						'before'     => '<footer class="cmt__foot">',
						'after'      => '</footer>',
					]
				),
				$comment
			);
			echo $reply ? $reply : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup (escaped by get_comment_reply_link()), the icon is static.
			?>
		</article>
	<?php
	// The closing tag is printed by wp_list_comments() (end-callback).
}

/**
 * comment_reply_link: the reply link carries the button classes.
 */
function gadgetim_pages_reply_link_class( string $link ): string {
	return (string) preg_replace( '/class=(["\'])comment-reply-link/', 'class=$1comment-reply-link btn btn--ghost btn--sm', $link, 1 );
}
add_filter( 'comment_reply_link', 'gadgetim_pages_reply_link_class' );

/* ---------------------------------------------------------------------------
 * Local digits in page bodies
 * ------------------------------------------------------------------------- */

/**
 * Persian/Arabic digits in the text of content pages (the imported pages were typed with Latin
 * digits: «07152855731»). Only text between tags is touched; <a>/<code>/<pre>/<script>/<style>/
 * <kbd>/<samp> contents, URLs and e-mail addresses keep their digits. Pages only (posts and
 * product descriptions are left alone); filter gadgetim_content_digits to opt out.
 *
 * @param string $html Content.
 */
function gadgetim_pages_content_digits( $html ) {
	if ( ! is_string( $html ) || '' === $html || is_admin() || is_feed() || 'en' === gadgetim_lang() ) {
		return $html;
	}
	if ( ! is_singular( 'page' ) || 'page' !== get_post_type() ) {
		return $html;
	}
	/**
	 * Whether page bodies get local digits.
	 *
	 * @param bool $on Default true.
	 */
	if ( ! apply_filters( 'gadgetim_content_digits', true ) || ! preg_match( '/\d/', $html ) ) {
		return $html;
	}
	$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) {
		return $html;
	}
	$skip = 0;
	foreach ( $parts as $i => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( preg_match( '#^<(/?)(a|code|pre|script|style|kbd|samp|textarea)\b#i', $part, $m ) ) {
				$skip = max( 0, $skip + ( '/' === $m[1] ? -1 : 1 ) );
			}
			continue;
		}
		if ( $skip > 0 || ! preg_match( '/\d/', $part ) ) {
			continue;
		}
		$parts[ $i ] = (string) preg_replace_callback(
			'#(?:https?://|www\.)\S+|[\w.+-]+@[\w-]+(?:\.[\w-]+)+|(\d+)#u',
			static fn( array $mm ): string => isset( $mm[1] ) && '' !== $mm[1] ? gadgetim_digits( $mm[1] ) : $mm[0],
			$part
		);
	}
	return implode( '', $parts );
}
add_filter( 'the_content', 'gadgetim_pages_content_digits', 30 );
