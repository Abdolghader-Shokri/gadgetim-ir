<?php
/**
 * IndexNow: tells Bing, Yandex and the other participating engines which URLs changed.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Sitemaps;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Settings\Settings;
use WP_Post;
use WP_Term;

/**
 * Bing's index feeds ChatGPT search and Copilot, and the store has no Bing Webmaster account
 * (checklist AIS.3-06 and TEC.18-01, 2026-11-15). When a product, page or post is published or
 * updated, its URL in every public language where it is published is queued. A new product
 * also queues its category and brand archives, whose listings changed. One cron event a minute
 * later posts the whole queue to api.indexnow.org, which shares it with the other engines.
 *
 * The key is created on the first queued change and served at /{key}.txt (protocol option 1,
 * which covers /en/ and /ar/ as well). Nothing is queued or sent from a copy that is not the
 * public production site: a local, staging or development copy, or one that asks search
 * engines not to index it.
 */
final class IndexNow {

	public const OPTION_KEY   = 'gadgetim_indexnow_key';
	public const OPTION_QUEUE = 'gadgetim_indexnow_queue';
	public const OPTION_LOG   = 'gadgetim_indexnow_log';
	public const CRON_HOOK    = 'gadgetim_indexnow_send';

	private const ENDPOINT     = 'https://api.indexnow.org/indexnow';
	private const POST_TYPES   = [ 'product', 'page', 'post' ];
	private const DELAY        = 60;
	private const RETRY_DELAY  = HOUR_IN_SECONDS;
	private const MAX_ATTEMPTS = 6;
	private const MAX_URLS     = 10000;
	private const DAILY_CAP    = 2000;
	private const LOG_ROWS     = 20;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'serve_key' ], 0 );
		add_action( 'transition_post_status', [ self::class, 'on_transition' ], 20, 3 );
		add_action( 'edited_term', [ self::class, 'on_term_edit' ], 20, 3 );
		add_action( self::CRON_HOOK, [ self::class, 'send' ] );
	}

	/**
	 * Whether this copy may notify search engines: the setting is on, WordPress runs as
	 * production, and the home URL is a public https host that allows indexing.
	 */
	public static function enabled(): bool {
		$on = (bool) Settings::get( 'seo.indexnow', true )
			&& 'production' === wp_get_environment_type()
			&& self::public_home();

		/**
		 * Filters whether IndexNow notifications are queued and sent.
		 *
		 * @param bool $on Result of the built-in checks.
		 */
		return (bool) apply_filters( 'gadgetim_indexnow_enabled', $on );
	}

	/**
	 * The key, created on first use. '' when $create is false and none exists yet.
	 *
	 * @param bool $create Create the key when missing.
	 */
	public static function key( bool $create = true ): string {
		$key = (string) get_option( self::OPTION_KEY, '' );
		if ( self::valid_key( $key ) ) {
			return $key;
		}
		if ( ! $create ) {
			return '';
		}
		$key = bin2hex( random_bytes( 16 ) );
		update_option( self::OPTION_KEY, $key, true );
		return $key;
	}

	/**
	 * Public URL of the key file.
	 *
	 * @param string $key Key.
	 */
	public static function key_url( string $key ): string {
		return untrailingslashit( (string) get_option( 'home' ) ) . '/' . $key . '.txt';
	}

	/**
	 * init (priority 0): answers /{key}.txt with the key, as the protocol verifies it.
	 */
	public static function serve_key(): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never printed.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! str_ends_with( $path, '.txt' ) ) {
			return;
		}
		$key = self::key( false );
		if ( '' === $key ) {
			return;
		}
		$home_path = rtrim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		if ( $path !== $home_path . '/' . $key . '.txt' ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $key; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 32 hex characters.
		exit;
	}

	/**
	 * transition_post_status: queues a published or updated product, page or post.
	 *
	 * @param mixed $new_status New status.
	 * @param mixed $old_status Old status.
	 * @param mixed $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || ! $post instanceof WP_Post || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		if ( '' !== (string) $post->post_password || in_array( (int) $post->ID, Sitemaps::excluded_pages(), true ) || ! self::enabled() ) {
			return;
		}
		$urls = self::post_urls( (int) $post->ID );
		if ( 'publish' !== $old_status && 'product' === $post->post_type ) {
			$urls = array_merge( $urls, self::archive_urls( (int) $post->ID ) );
		}
		self::queue( $urls );
	}

	/**
	 * edited_term: queues a category or brand archive whose name or description changed.
	 *
	 * @param mixed $term_id  Term ID.
	 * @param mixed $tt_id    Term taxonomy ID.
	 * @param mixed $taxonomy Taxonomy.
	 */
	public static function on_term_edit( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( ! in_array( (string) $taxonomy, [ 'product_cat', Brands::TAXONOMY ], true ) || ! self::enabled() ) {
			return;
		}
		$term = get_term( (int) $term_id, (string) $taxonomy );
		// An archive without products is noindex (Robots::filter_robots): nothing to announce.
		if ( $term instanceof WP_Term && $term->count > 0 ) {
			self::queue( self::term_urls( $term ) );
		}
	}

	/**
	 * Cron: posts the queue in one request; keeps it for a later attempt when the engine is
	 * busy or unreachable.
	 */
	public static function send(): void {
		$queue = self::read_queue();
		if ( [] === $queue['urls'] ) {
			return;
		}
		if ( ! self::enabled() ) {
			delete_option( self::OPTION_QUEUE );
			return;
		}
		$room = self::DAILY_CAP - self::sent_today();
		if ( $room <= 0 ) {
			self::log( 0, count( $queue['urls'] ), 'daily cap' );
			wp_schedule_single_event( strtotime( 'tomorrow' ) + self::DELAY, self::CRON_HOOK );
			return;
		}
		$urls = array_slice( $queue['urls'], 0, min( self::MAX_URLS, $room ) );
		$key  = self::key();
		$body = [
			'host'        => (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ),
			'key'         => $key,
			'keyLocation' => self::key_url( $key ),
			'urlList'     => $urls,
		];

		/**
		 * Filters the IndexNow endpoint (tests point it elsewhere).
		 *
		 * @param string $endpoint URL.
		 */
		$endpoint = (string) apply_filters( 'gadgetim_indexnow_endpoint', self::ENDPOINT );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
				'body'    => (string) wp_json_encode( $body, JSON_UNESCAPED_SLASHES ),
			]
		);
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$note = is_wp_error( $response ) ? $response->get_error_message() : '';

		// 200 received, 202 received while the key file is being checked. 429 means too many
		// requests and 5xx or no answer a busy engine: try again later. 400, 403 and 422 say
		// the request itself is wrong, so sending it again would not help.
		$retry = 0 === $code || 429 === $code || $code >= 500;
		if ( $retry && $queue['attempts'] + 1 < self::MAX_ATTEMPTS ) {
			self::write_queue( $queue['urls'], $queue['attempts'] + 1 );
			wp_schedule_single_event( time() + self::RETRY_DELAY, self::CRON_HOOK );
		} else {
			// URLs queued while this request ran stay for the next event.
			$left = array_values( array_diff( self::read_queue()['urls'], $urls ) );
			self::write_queue( $left, 0 );
			if ( [] !== $left && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK );
			}
		}
		self::log( $code, count( $urls ), $note );
	}

	/**
	 * Last submissions, newest first, for the settings screen.
	 *
	 * @return array<int,array{time:int,code:int,count:int,note:string}>
	 */
	public static function log_rows(): array {
		$rows = get_option( self::OPTION_LOG, [] );
		return is_array( $rows ) ? array_values( $rows ) : [];
	}

	/**
	 * URLs of a post in every public language where it is published.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function post_urls( int $post_id ): array {
		$urls = [];
		foreach ( Languages::public_codes() as $lang ) {
			if ( Languages::DEFAULT !== $lang && ! PostTranslations::is_published( $post_id, $lang ) ) {
				continue;
			}
			$url = Languages::with_lang( $lang, static fn() => get_permalink( $post_id ) );
			if ( is_string( $url ) && '' !== $url ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Category and brand archives of a product, ancestors included.
	 *
	 * @param int $post_id Product ID.
	 * @return string[]
	 */
	private static function archive_urls( int $post_id ): array {
		$urls = [];
		foreach ( [ 'product_cat', Brands::TAXONOMY ] as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$ids = array_merge( [ (int) $term->term_id ], array_map( 'intval', get_ancestors( (int) $term->term_id, $taxonomy, 'taxonomy' ) ) );
				foreach ( $ids as $id ) {
					$t = get_term( $id, $taxonomy );
					if ( $t instanceof WP_Term ) {
						$urls = array_merge( $urls, self::term_urls( $t ) );
					}
				}
			}
		}
		return $urls;
	}

	/**
	 * Archive URL of a term in every public language where its translation is published.
	 *
	 * @param WP_Term $term Term.
	 * @return string[]
	 */
	private static function term_urls( WP_Term $term ): array {
		$urls = [];
		foreach ( Languages::public_codes() as $lang ) {
			if ( Languages::DEFAULT !== $lang && ! TermTranslations::is_published( (int) $term->term_id, $lang ) ) {
				continue;
			}
			$url = Languages::with_lang( $lang, static fn() => get_term_link( $term ) );
			if ( is_string( $url ) && '' !== $url ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Adds URLs of this host to the queue and schedules the send.
	 *
	 * @param string[] $urls URLs.
	 */
	private static function queue( array $urls ): void {
		$host = strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
		$add  = [];
		foreach ( $urls as $url ) {
			$url = Text::encode_url( (string) $url );
			if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === $host ) {
				$add[] = $url;
			}
		}
		if ( [] === $add ) {
			return;
		}
		$queue = self::read_queue();
		self::write_queue( array_slice( array_values( array_unique( array_merge( $queue['urls'], $add ) ) ), 0, self::MAX_URLS ), $queue['attempts'] );
		// The key file must answer before the engine checks it.
		self::key();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK );
		}
	}

	/**
	 * @return array{urls:string[],attempts:int}
	 */
	private static function read_queue(): array {
		$raw = get_option( self::OPTION_QUEUE, [] );
		$raw = is_array( $raw ) ? $raw : [];
		return [
			'urls'     => array_values( array_filter( array_map( 'strval', (array) ( $raw['urls'] ?? [] ) ) ) ),
			'attempts' => (int) ( $raw['attempts'] ?? 0 ),
		];
	}

	/**
	 * @param string[] $urls     URLs.
	 * @param int      $attempts Failed attempts of the current batch.
	 */
	private static function write_queue( array $urls, int $attempts ): void {
		if ( [] === $urls ) {
			delete_option( self::OPTION_QUEUE );
			return;
		}
		update_option(
			self::OPTION_QUEUE,
			[
				'urls'     => array_values( $urls ),
				'attempts' => $attempts,
			],
			false
		);
	}

	/**
	 * URLs accepted today (site time zone).
	 */
	private static function sent_today(): int {
		$today = wp_date( 'Y-m-d' );
		$count = 0;
		foreach ( self::log_rows() as $row ) {
			if ( wp_date( 'Y-m-d', (int) $row['time'] ) === $today && in_array( (int) $row['code'], [ 200, 202 ], true ) ) {
				$count += (int) $row['count'];
			}
		}
		return $count;
	}

	/**
	 * Keeps the last LOG_ROWS submissions.
	 *
	 * @param int    $code  HTTP status, 0 when nothing came back.
	 * @param int    $count URLs in the request.
	 * @param string $note  Error message.
	 */
	private static function log( int $code, int $count, string $note ): void {
		$rows = self::log_rows();
		array_unshift(
			$rows,
			[
				'time'  => time(),
				'code'  => $code,
				'count' => $count,
				'note'  => mb_substr( $note, 0, 200 ),
			]
		);
		update_option( self::OPTION_LOG, array_slice( $rows, 0, self::LOG_ROWS ), false );
	}

	/**
	 * Key format of the protocol: 8 to 128 characters of a-z, A-Z, 0-9 and '-'.
	 *
	 * @param string $key Candidate.
	 */
	private static function valid_key( string $key ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9-]{8,128}$/', $key );
	}

	/**
	 * Whether the home URL is a public https host that allows indexing.
	 */
	private static function public_home(): bool {
		$home = (string) get_option( 'home' );
		$host = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		if ( 'https' !== wp_parse_url( $home, PHP_URL_SCHEME ) || ! str_contains( $host, '.' ) || ! get_option( 'blog_public' ) ) {
			return false;
		}
		foreach ( [ '.local', '.test', '.localhost', '.example', '.invalid' ] as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return false;
			}
		}
		return true;
	}
}
