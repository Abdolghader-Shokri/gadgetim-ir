<?php
/**
 * Page-cache purge adapter (architecture §7.4).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

use WP_Post;
use WP_Term;

/**
 * One purge API in front of LiteSpeed Cache, WP Super Cache, Cache Enabler and
 * WP Fastest Cache, with suspend()/resume() for batch jobs (importer, reprice).
 *
 * Actions: gadgetim_cache_purge_all, gadgetim_cache_purge_post, gadgetim_cache_purge_url
 * (fired when a purge is requested, before the adapters) and gadgetim_cache_purged_all
 * (after a purge-all). Filter: gadgetim_cache_purge_urls (I18n adds /en/ and /ar/ variants).
 */
final class Cache {

	private static int $suspended = 0;

	private static bool $pending_all = false;

	/** @var array<int,true> */
	private static array $pending_posts = [];

	/** @var array<string,true> */
	private static array $pending_urls = [];

	/** @var array<int,true> Posts purged during this request (dedupe). */
	private static array $purged_posts = [];

	/**
	 * Suspends purging (nestable). While suspended, purge_all() is remembered and
	 * purge_post()/purge_url() are queued; nothing reaches the cache plugin.
	 */
	public static function suspend(): void {
		self::$suspended++;
	}

	/**
	 * Ends one suspend(). Does NOT flush what was queued: callers either call
	 * purge_all() explicitly (reprice, importer) or flush_pending().
	 */
	public static function resume(): void {
		self::$suspended = max( 0, self::$suspended - 1 );
	}

	/**
	 * Whether purging is currently suspended.
	 */
	public static function is_suspended(): bool {
		return self::$suspended > 0;
	}

	/**
	 * Flushes what was queued while suspended: a pending purge-all wins over the
	 * individual posts/URLs. No-op while still suspended.
	 */
	public static function flush_pending(): void {
		if ( self::$suspended > 0 ) {
			return;
		}
		if ( self::$pending_all ) {
			self::purge_all();
			return;
		}
		$posts = array_keys( self::$pending_posts );
		$urls  = array_keys( self::$pending_urls );
		self::$pending_posts = [];
		self::$pending_urls  = [];
		foreach ( $posts as $post_id ) {
			self::purge_post( (int) $post_id );
		}
		foreach ( $urls as $url ) {
			self::purge_url( (string) $url );
		}
	}

	/**
	 * Purges the whole page cache.
	 */
	public static function purge_all(): void {
		if ( self::$suspended > 0 ) {
			self::$pending_all = true;
			return;
		}
		self::$pending_all   = false;
		self::$pending_posts = [];
		self::$pending_urls  = [];

		/**
		 * Fires when a full purge is requested (before the built-in adapters run).
		 */
		do_action( 'gadgetim_cache_purge_all' );

		switch ( self::adapter() ) {
			case 'litespeed':
				do_action( 'litespeed_purge_all' );
				break;
			case 'wp-super-cache':
				wp_cache_clear_cache();
				break;
			case 'cache-enabler':
				do_action( 'cache_enabler_clear_complete_cache' );
				break;
			case 'wp-fastest-cache':
				wpfc_clear_all_cache( true );
				break;
		}

		/**
		 * Fires after the whole page cache was purged (exactly once per purge-all).
		 */
		do_action( 'gadgetim_cache_purged_all' );
	}

	/**
	 * Purges a post together with the home page, the shop page and every archive of
	 * its terms (ancestors included), in every public language through the
	 * gadgetim_cache_purge_urls filter.
	 */
	public static function purge_post( int $post_id ): void {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}
		if ( self::$suspended > 0 ) {
			self::$pending_posts[ $post_id ] = true;
			return;
		}
		if ( isset( self::$purged_posts[ $post_id ] ) ) {
			return;
		}
		self::$purged_posts[ $post_id ] = true;

		$urls = self::expand_urls( self::urls_for_post( $post_id ), $post_id, [ 'type' => 'post' ] );

		/**
		 * Fires when a post purge is requested (before the built-in adapters run).
		 *
		 * @param int      $post_id Post ID.
		 * @param string[] $urls    Every URL that will be purged.
		 */
		do_action( 'gadgetim_cache_purge_post', $post_id, $urls );

		self::adapter_purge_post( $post_id );
		foreach ( $urls as $url ) {
			self::adapter_purge_url( $url );
		}
	}

	/**
	 * Purges a term archive (with its ancestors), the home page and the shop page,
	 * in every public language.
	 */
	public static function purge_term( int $term_id, string $taxonomy ): void {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			self::purge_urls( self::expand_urls( self::base_urls(), 0, [ 'type' => 'term', 'taxonomy' => $taxonomy ] ) );
			return;
		}
		$urls = array_merge( self::base_urls(), self::term_urls( $term ) );
		self::purge_urls( self::expand_urls( $urls, 0, [ 'type' => 'term', 'term_id' => $term->term_id, 'taxonomy' => $taxonomy ] ) );
	}

	/**
	 * Purges one exact URL (no language expansion; pass each variant yourself).
	 */
	public static function purge_url( string $url ): void {
		$url = trim( $url );
		if ( '' === $url ) {
			return;
		}
		if ( self::$suspended > 0 ) {
			self::$pending_urls[ $url ] = true;
			return;
		}

		/**
		 * Fires when a URL purge is requested (before the built-in adapters run).
		 *
		 * @param string $url Absolute URL.
		 */
		do_action( 'gadgetim_cache_purge_url', $url );

		self::adapter_purge_url( $url );
	}

	/**
	 * Purges several exact URLs.
	 *
	 * @param string[] $urls Absolute URLs.
	 */
	public static function purge_urls( array $urls ): void {
		foreach ( array_unique( array_filter( array_map( 'strval', $urls ) ) ) as $url ) {
			self::purge_url( $url );
		}
	}

	/**
	 * Detected cache plugin: 'litespeed', 'wp-super-cache', 'cache-enabler', 'wp-fastest-cache' or 'none'.
	 */
	public static function adapter(): string {
		if ( has_action( 'litespeed_purge_all' ) || defined( 'LSCWP_V' ) ) {
			return 'litespeed';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			return 'wp-super-cache';
		}
		if ( has_action( 'cache_enabler_clear_complete_cache' ) || class_exists( 'Cache_Enabler', false ) ) {
			return 'cache-enabler';
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			return 'wp-fastest-cache';
		}
		return 'none';
	}

	/**
	 * Human label of the detected adapter (translated).
	 */
	public static function adapter_label(): string {
		return match ( self::adapter() ) {
			'litespeed'        => 'LiteSpeed Cache',
			'wp-super-cache'   => 'WP Super Cache',
			'cache-enabler'    => 'Cache Enabler',
			'wp-fastest-cache' => 'WP Fastest Cache',
			default            => __( 'no page-cache plugin detected', 'gadgetim-core' ),
		};
	}

	/**
	 * Wires the purge triggers: post save/delete, term changes, menu save, settings save,
	 * review approval. Called once from Plugin::boot(). Order status changes purge nothing.
	 */
	public static function register_triggers(): void {
		add_action( 'save_post', [ self::class, 'on_save_post' ], 20, 2 );
		add_action( 'deleted_post', [ self::class, 'on_deleted_post' ], 10, 2 );
		add_action( 'created_term', [ self::class, 'on_term_changed' ], 10, 3 );
		add_action( 'edited_term', [ self::class, 'on_term_changed' ], 10, 3 );
		add_action( 'delete_term', [ self::class, 'on_term_deleted' ], 10, 3 );
		add_action( 'wp_update_nav_menu', [ self::class, 'purge_all' ], 10, 0 );
		add_action( 'gadgetim_settings_saved', [ self::class, 'purge_all' ], 10, 0 );
		add_action( 'transition_comment_status', [ self::class, 'on_comment_status' ], 10, 3 );
		add_action( 'comment_post', [ self::class, 'on_comment_post' ], 10, 2 );
	}

	/**
	 * save_post trigger (viewable post types only; autosaves and revisions ignored).
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public static function on_save_post( int $post_id, $post = null ): void {
		if ( ! $post instanceof WP_Post || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( in_array( $post->post_status, [ 'auto-draft', 'inherit' ], true ) || 'attachment' === $post->post_type ) {
			return;
		}
		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}
		self::purge_post( $post_id );
	}

	/**
	 * deleted_post trigger: the post URL is gone, but home/shop/archives changed.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public static function on_deleted_post( int $post_id, $post = null ): void {
		if ( ! $post instanceof WP_Post || ! is_post_type_viewable( $post->post_type ) || 'attachment' === $post->post_type ) {
			return;
		}
		self::purge_urls( self::expand_urls( self::base_urls(), $post_id, [ 'type' => 'post' ] ) );
	}

	/**
	 * created_term / edited_term trigger.
	 */
	public static function on_term_changed( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! is_taxonomy_viewable( $taxonomy ) ) {
			return;
		}
		self::purge_term( $term_id, $taxonomy );
	}

	/**
	 * delete_term trigger.
	 */
	public static function on_term_deleted( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! is_taxonomy_viewable( $taxonomy ) ) {
			return;
		}
		self::purge_urls( self::expand_urls( self::base_urls(), 0, [ 'type' => 'term', 'taxonomy' => $taxonomy ] ) );
	}

	/**
	 * Review approved/unapproved -> product page changes.
	 *
	 * @param int|string $new     New status.
	 * @param int|string $old     Old status.
	 * @param object     $comment Comment object.
	 */
	public static function on_comment_status( $new, $old, $comment ): void {
		if ( $new === $old || ! is_object( $comment ) || empty( $comment->comment_post_ID ) ) {
			return;
		}
		if ( 'approved' === $new || 'approved' === $old ) {
			self::purge_post( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * New approved comment (reviews without moderation).
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   1, 0 or 'spam'.
	 */
	public static function on_comment_post( int $comment_id, $approved ): void {
		if ( 1 !== (int) $approved ) {
			return;
		}
		$comment = get_comment( $comment_id );
		if ( $comment && ! empty( $comment->comment_post_ID ) ) {
			self::purge_post( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Home page and shop page URLs (Persian context; languages are added by the filter).
	 *
	 * @return string[]
	 */
	public static function base_urls(): array {
		$urls = [ home_url( '/' ) ];
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );
			if ( is_string( $shop ) && '' !== $shop ) {
				$urls[] = $shop;
			}
		}
		return $urls;
	}

	/**
	 * Permalink, home, shop and the archives of every public term of the post (plus ancestors).
	 *
	 * @return string[]
	 */
	public static function urls_for_post( int $post_id ): array {
		$urls      = self::base_urls();
		$permalink = get_permalink( $post_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			array_unshift( $urls, $permalink );
		}
		$post_type = (string) get_post_type( $post_id );
		if ( '' === $post_type ) {
			return $urls;
		}
		foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
			if ( ! is_taxonomy_viewable( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$urls = array_merge( $urls, self::term_urls( $term ) );
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Archive URL of a term and of each of its ancestors.
	 *
	 * @return string[]
	 */
	public static function term_urls( WP_Term $term ): array {
		$urls = [];
		$link = get_term_link( $term );
		if ( is_string( $link ) ) {
			$urls[] = $link;
		}
		if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
			foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor_id ) {
				$link = get_term_link( (int) $ancestor_id, $term->taxonomy );
				if ( is_string( $link ) ) {
					$urls[] = $link;
				}
			}
		}
		return $urls;
	}

	/**
	 * Applies the gadgetim_cache_purge_urls filter and cleans the list.
	 *
	 * @param string[] $urls    Base URLs (Persian context).
	 * @param int      $post_id Post ID, or 0.
	 * @param array    $context Extra context ('type' => 'post'|'term', 'term_id', 'taxonomy').
	 * @return string[]
	 */
	private static function expand_urls( array $urls, int $post_id, array $context ): array {
		/**
		 * Filters the URLs purged for a post or term. I18n adds the /en/ and /ar/ variants
		 * of every URL for the public languages.
		 *
		 * @param string[] $urls    Absolute URLs.
		 * @param int      $post_id Post ID, or 0 for term/global purges.
		 * @param array    $context Extra context.
		 */
		$urls = (array) apply_filters( 'gadgetim_cache_purge_urls', $urls, $post_id, $context );
		return array_values( array_unique( array_filter( array_map( 'strval', $urls ) ) ) );
	}

	private static function adapter_purge_post( int $post_id ): void {
		switch ( self::adapter() ) {
			case 'litespeed':
				do_action( 'litespeed_purge_post', $post_id );
				break;
			case 'wp-super-cache':
				if ( function_exists( 'wp_cache_post_change' ) ) {
					wp_cache_post_change( $post_id );
				}
				break;
			case 'cache-enabler':
				do_action( 'cache_enabler_clear_page_cache_by_post', $post_id );
				break;
			case 'wp-fastest-cache':
				if ( function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
					wpfc_clear_post_cache_by_id( $post_id );
				}
				break;
		}
	}

	private static function adapter_purge_url( string $url ): void {
		switch ( self::adapter() ) {
			case 'litespeed':
				do_action( 'litespeed_purge_url', $url );
				break;
			case 'wp-super-cache':
				if ( function_exists( 'wpsc_delete_url_cache' ) ) {
					wpsc_delete_url_cache( $url );
				} else {
					self::purge_url_via_post_id( $url );
				}
				break;
			case 'cache-enabler':
				do_action( 'cache_enabler_clear_page_cache_by_url', $url );
				break;
			case 'wp-fastest-cache':
				self::purge_url_via_post_id( $url );
				break;
		}
	}

	/**
	 * Fallback for adapters without a URL purge: resolve the URL to a post and purge that;
	 * the home URL goes through WP Fastest Cache's own method when present.
	 */
	private static function purge_url_via_post_id( string $url ): void {
		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			self::adapter_purge_post( $post_id );
			return;
		}
		if ( 'wp-fastest-cache' === self::adapter() && untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) ) ) {
			$wpfc = $GLOBALS['wp_fastest_cache'] ?? null;
			if ( is_object( $wpfc ) && method_exists( $wpfc, 'deleteHomePageCache' ) ) {
				$wpfc->deleteHomePageCache( false );
			}
		}
	}
}
