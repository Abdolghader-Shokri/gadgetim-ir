<?php
/**
 * hreflang links and switcher data (architecture §3.5).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use WP_Post;
use WP_Rewrite;
use WP_Term;

/**
 * One <link rel="alternate" hreflang> per public language where the equivalent exists and is
 * published, plus x-default -> Persian. Equivalent URLs are built with
 * Languages::with_lang( $lang, fn() => current_object_url() ), so translated slugs apply.
 * Switcher links are server-rendered and identical for every visitor (cacheable): no cookie,
 * no redirect.
 */
final class Hreflang {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'print_links' ], 2 );
	}

	/**
	 * wp_head: prints the alternate links.
	 */
	public static function print_links(): void {
		foreach ( self::links() as $hreflang => $url ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $url ) . '">' . "\n";
		}
	}

	/**
	 * [ hreflang => url ] including x-default, or [] when the page has no alternates.
	 *
	 * @return array<string,string>
	 */
	public static function links(): array {
		if ( ! self::applicable() ) {
			return [];
		}
		$alternates = self::alternates( true, true );
		if ( count( $alternates ) < 2 ) {
			return [];
		}
		$out = [];
		foreach ( $alternates as $code => $row ) {
			$out[ (string) $row['hreflang'] ] = (string) $row['url'];
		}
		if ( isset( $alternates[ Languages::DEFAULT ] ) ) {
			$out['x-default'] = (string) $alternates[ Languages::DEFAULT ]['url'];
		}
		return $out;
	}

	/**
	 * Equivalent of the current page in every language.
	 *
	 * @param bool $public_only   Skip non-public languages.
	 * @param bool $existing_only Skip languages where the equivalent does not exist (the row
	 *                            would otherwise point at that language's home page).
	 * @return array<string,array{url:string,label:string,public:bool,exists:bool,current:bool,dir:string,hreflang:string}>
	 */
	public static function alternates( bool $public_only = false, bool $existing_only = true ): array {
		$out = [];
		foreach ( Languages::codes() as $code ) {
			if ( $public_only && ! Languages::is_public( $code ) ) {
				continue;
			}
			$url    = Languages::with_lang( $code, static fn(): ?string => self::current_object_url( $code ) );
			$exists = is_string( $url ) && '' !== $url;
			if ( ! $exists ) {
				if ( $existing_only ) {
					continue;
				}
				$url = Languages::with_lang( $code, static fn(): string => home_url( '/' ) );
			}
			$out[ $code ] = [
				'url'      => (string) $url,
				'label'    => Languages::label( $code ),
				'public'   => Languages::is_public( $code ),
				'exists'   => $exists,
				'current'  => Languages::current() === $code,
				'dir'      => Languages::dir( $code ),
				'hreflang' => Languages::hreflang( $code ),
			];
		}
		return $out;
	}

	/**
	 * Rows for the theme's language switcher: every language, 'visible' false for non-public
	 * languages unless the visitor is logged in (translators review them).
	 *
	 * @return array<string,array>
	 */
	public static function switcher(): array {
		$rows      = self::alternates( false, false );
		$logged_in = is_user_logged_in();
		foreach ( $rows as $code => $row ) {
			$rows[ $code ]['visible'] = $row['public'] || $logged_in;
		}
		return $rows;
	}

	/**
	 * Whether the current page may carry hreflang at all.
	 */
	public static function applicable(): bool {
		if ( is_admin() || is_404() || is_search() || is_feed() || is_preview() || is_robots() || is_trackback() || is_embed() ) {
			return false;
		}
		if ( ! Languages::is_public( Languages::current() ) ) {
			return false;
		}
		if ( Visibility::fallback_active() || Visibility::is_empty_archive() ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return false;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
			return false;
		}
		if ( is_singular() && PostTranslations::is_preview( (int) get_queried_object_id() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * URL of the current page in the language context that is active, or null when the
	 * equivalent does not exist in $lang (untranslated post, untranslated or empty term).
	 */
	public static function current_object_url( string $lang ): ?string {
		global $wp_rewrite;
		$url = null;

		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) {
				return null;
			}
			if ( PostTranslations::supports( $post->post_type ) && ! Slugs::is_wc_page( $post->ID ) && ! PostTranslations::is_published( $post->ID, $lang ) ) {
				return null;
			}
			$permalink = get_permalink( $post );
			$url       = is_string( $permalink ) ? $permalink : null;
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop = wc_get_page_permalink( 'shop' );
			$url  = is_string( $shop ) ? $shop : null;
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return null;
			}
			if ( TermTranslations::supports( $term->taxonomy ) && Languages::DEFAULT !== $lang ) {
				if ( ! TermTranslations::is_published( $term->term_id, $lang ) || ! Visibility::term_has_content( $term, $lang ) ) {
					return null;
				}
			}
			$link = get_term_link( $term );
			$url  = is_string( $link ) ? $link : null;
		} elseif ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? (string) reset( $type ) : (string) $type;
			$link = '' !== $type ? get_post_type_archive_link( $type ) : false;
			$url  = is_string( $link ) ? $link : null;
		} elseif ( is_home() ) {
			$page = (int) get_option( 'page_for_posts' );
			$link = $page > 0 ? get_permalink( $page ) : home_url( '/' );
			$url  = is_string( $link ) ? $link : null;
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		if ( $paged > 1 ) {
			$base = $wp_rewrite instanceof WP_Rewrite ? $wp_rewrite->pagination_base : 'page';
			$url  = trailingslashit( $url ) . user_trailingslashit( $base . '/' . $paged, 'paged' );
		}
		return $url;
	}
}
