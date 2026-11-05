<?php
/**
 * Hashed-IP token bucket (architecture §3.6, §6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

/**
 * Rate limiter for public endpoints (search, views, currencies).
 *
 * Storage:
 * - With a persistent object cache, every bucket is its own cache key in the gadgetim_rl group,
 *   counted atomically with wp_cache_incr() in fixed windows.
 * - Without one, all buckets of the current hour live in ONE transient (gadgetim_rl_YYYYMMDDHH),
 *   so wp_options never grows with the number of clients. Buckets that have refilled completely
 *   carry no state and are pruned on every write, so the table only holds clients active in the
 *   last window; it is also capped at MAX_ENTRIES. This path is a soft limit: concurrent requests
 *   can lose an update (read-modify-write without a lock), which is accepted for these endpoints.
 *
 * IPv6 clients are bucketed by their /64 (one host usually owns the whole /64), and every key also
 * has a global bucket (all clients together) so mass address rotation is still capped.
 */
final class RateLimit {

	public const TRANSIENT_PREFIX = 'gadgetim_rl_';

	public const CACHE_GROUP = 'gadgetim_rl';

	private const MAX_ENTRIES = 1000;

	/**
	 * Global ceiling per window = per-client limit × this factor (filterable).
	 */
	private const GLOBAL_FACTOR = 40;

	/**
	 * In-request copy of the hourly table (one read per request).
	 *
	 * @var array<string,array{t:float,u:float,f:float}>|null
	 */
	private static ?array $table = null;

	/**
	 * Takes one token from the bucket of ($key, client IP) and from the key's global bucket.
	 * Returns false when either is exhausted.
	 *
	 * @param string      $key    Bucket name, e.g. 'search'.
	 * @param int         $limit  Bucket capacity (burst) — e.g. 30.
	 * @param int         $window Seconds in which $limit tokens refill — e.g. 60.
	 * @param string|null $ip     Client IP; defaults to the request's.
	 */
	public static function allow( string $key, int $limit, int $window, ?string $ip = null ): bool {
		$limit  = max( 1, $limit );
		$window = max( 1, $window );

		if ( ! self::take( self::hash( $key, $ip ?? self::client_ip() ), $limit, $window ) ) {
			return false;
		}
		return self::take( self::global_hash( $key ), self::global_limit( $key, $limit ), $window );
	}

	/**
	 * Takes one token from a bucket that is not tied to a client (e.g. a per-product cap).
	 */
	public static function allow_shared( string $key, int $limit, int $window ): bool {
		return self::take( self::global_hash( 'shared:' . $key ), max( 1, $limit ), max( 1, $window ) );
	}

	/**
	 * Seconds until the next token is available (0 when a request would pass now).
	 * Does not consume a token.
	 */
	public static function retry_after( string $key, int $limit, int $window, ?string $ip = null ): int {
		$limit  = max( 1, $limit );
		$window = max( 1, $window );
		$hash   = self::hash( $key, $ip ?? self::client_ip() );

		if ( wp_using_ext_object_cache() ) {
			$count = (int) wp_cache_get( self::cache_key( $hash, $window ), self::CACHE_GROUP );
			return $count >= $limit ? max( 1, $window - ( time() % $window ) ) : 0;
		}

		$entry = self::load()[ $hash ] ?? null;
		if ( null === $entry ) {
			return 0;
		}
		$rate   = $limit / $window;
		$tokens = min( (float) $limit, (float) $entry['t'] + ( microtime( true ) - (float) $entry['u'] ) * $rate );
		if ( $tokens >= 1.0 ) {
			return 0;
		}
		return (int) ceil( ( 1.0 - $tokens ) / $rate );
	}

	/**
	 * Forgets the bucket of ($key, IP).
	 */
	public static function reset( string $key, ?string $ip = null ): void {
		$hash = self::hash( $key, $ip ?? self::client_ip() );
		if ( wp_using_ext_object_cache() ) {
			foreach ( [ 1, 60, 3600 ] as $window ) {
				wp_cache_delete( self::cache_key( $hash, $window ), self::CACHE_GROUP );
			}
			return;
		}
		$buckets = self::load();
		unset( $buckets[ $hash ] );
		self::save( $buckets );
	}

	/**
	 * Client IP: REMOTE_ADDR only, unless the gadgetim_client_ip filter trusts a proxy header.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '0.0.0.0';
		}

		/**
		 * Filters the client IP used for rate limiting and view de-duplication.
		 * Hook this to read a proxy header ONLY behind a trusted proxy.
		 *
		 * @param string $ip IP address.
		 */
		$ip = (string) apply_filters( 'gadgetim_client_ip', $ip );
		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '0.0.0.0' : $ip;
	}

	/**
	 * The address a client is bucketed by: IPv4 as is, IPv6 reduced to its /64 network
	 * (IPv4-mapped IPv6 addresses become the IPv4 address).
	 */
	public static function network( string $ip ): string {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return $ip;
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $ip;
		}
		$packed = inet_pton( $ip );
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip;
		}
		if ( str_repeat( "\0", 10 ) . "\xff\xff" === substr( $packed, 0, 12 ) ) {
			$v4 = inet_ntop( substr( $packed, 12 ) );
			return false === $v4 ? $ip : $v4;
		}
		$net = inet_ntop( substr( $packed, 0, 8 ) . str_repeat( "\0", 8 ) );
		return false === $net ? $ip : $net . '/64';
	}

	/**
	 * Salted, truncated hash of (key, client network). Never stores or logs the IP itself.
	 */
	public static function hash( string $key, string $ip ): string {
		return substr( hash( 'sha256', $key . '|' . self::network( $ip ) . '|' . wp_salt( 'nonce' ) ), 0, 20 );
	}

	/**
	 * Hashed network for the current client (for view de-duplication keys etc.).
	 */
	public static function ip_hash( string $scope = 'ip' ): string {
		return self::hash( $scope, self::client_ip() );
	}

	/**
	 * Name of the current hour's transient.
	 */
	public static function bucket_name(): string {
		return self::TRANSIENT_PREFIX . gmdate( 'YmdH' );
	}

	/**
	 * Drops the in-request copy of the hourly table (tests).
	 */
	public static function flush(): void {
		self::$table = null;
	}

	/**
	 * Global ceiling for a key (all clients together, per window).
	 */
	private static function global_limit( string $key, int $limit ): int {
		/**
		 * Filters the global per-window ceiling of a rate-limit key (all clients together).
		 *
		 * @param int    $ceiling Default: per-client limit × 40.
		 * @param string $key     Bucket name, e.g. 'search'.
		 * @param int    $limit   Per-client limit.
		 */
		return max( 1, (int) apply_filters( 'gadgetim_rate_limit_global', $limit * self::GLOBAL_FACTOR, $key, $limit ) );
	}

	private static function global_hash( string $key ): string {
		return substr( hash( 'sha256', 'global|' . $key . '|' . wp_salt( 'nonce' ) ), 0, 20 );
	}

	private static function cache_key( string $hash, int $window ): string {
		return $hash . ':' . $window . ':' . intdiv( time(), $window );
	}

	/**
	 * One token from one bucket.
	 */
	private static function take( string $hash, int $limit, int $window ): bool {
		if ( wp_using_ext_object_cache() ) {
			// Fixed window, atomic increment.
			$ckey = self::cache_key( $hash, $window );
			wp_cache_add( $ckey, 0, self::CACHE_GROUP, $window + 5 );
			$count = wp_cache_incr( $ckey, 1, self::CACHE_GROUP );
			if ( false === $count ) {
				// Back end without incr support: fail open rather than block everyone.
				return true;
			}
			return (int) $count <= $limit;
		}

		$now     = microtime( true );
		$rate    = $limit / $window;
		$buckets = self::load();
		$entry   = $buckets[ $hash ] ?? [
			't' => (float) $limit,
			'u' => $now,
		];
		$tokens  = min( (float) $limit, (float) $entry['t'] + ( $now - (float) $entry['u'] ) * $rate );

		$allowed = $tokens >= 1.0;
		if ( $allowed ) {
			$tokens -= 1.0;
		}
		$buckets[ $hash ] = [
			't' => round( $tokens, 3 ),
			'u' => round( $now, 3 ),
			// Time at which the bucket is full again (and can be forgotten).
			'f' => round( $now + ( $limit - $tokens ) / $rate, 3 ),
		];
		self::save( $buckets );

		return $allowed;
	}

	/**
	 * @return array<string,array{t:float,u:float,f:float}>
	 */
	private static function load(): array {
		if ( null === self::$table ) {
			$buckets     = get_transient( self::bucket_name() );
			self::$table = is_array( $buckets ) ? $buckets : [];
		}
		return self::$table;
	}

	/**
	 * Prunes refilled buckets, caps the table (evicting the buckets that refill soonest, which an
	 * address-rotating client's are), and writes it back.
	 *
	 * @param array<string,array{t:float,u:float,f:float}> $buckets Buckets.
	 */
	private static function save( array $buckets ): void {
		$now     = microtime( true );
		$buckets = array_filter( $buckets, static fn( $b ): bool => is_array( $b ) && (float) ( $b['f'] ?? 0 ) > $now );
		if ( count( $buckets ) > self::MAX_ENTRIES ) {
			uasort( $buckets, static fn( array $a, array $b ): int => (float) $b['f'] <=> (float) $a['f'] );
			$buckets = array_slice( $buckets, 0, self::MAX_ENTRIES, true );
		}
		self::$table = $buckets;
		set_transient( self::bucket_name(), $buckets, 2 * HOUR_IN_SECONDS );
	}
}
