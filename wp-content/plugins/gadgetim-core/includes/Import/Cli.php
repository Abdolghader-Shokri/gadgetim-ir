<?php
/**
 * Plain-PHP import entry point (architecture §2.6, §6.1): bootstraps WordPress and runs the
 * importer without WP-CLI.
 *
 *   php -c <php.ini> includes/Import/Cli.php [--wp=<dir>] [--host=<host>] [--from=<dir>]
 *        [--terms] [--products] [--images] [--pages] [--redirects] [--dry-run] [--limit=N]
 *        [--user=<id|login>] [--apply-redirects] [--out=<dir>] [-h]
 *
 * No step flag means every step. The class is also used by Cli\Commands (WP-CLI) for the
 * shared argument handling, so the bottom of the file runs main() only when this file is
 * the script being executed.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

/**
 * Command-line runner.
 */
final class Cli {

	public const DEFAULT_WP_PATH = 'C:/Users/GHADER/Local Sites/gadgetim/app/public';

	public const DEFAULT_HOST = 'gadgetim.local';

	/**
	 * Entry point. Returns the exit code.
	 *
	 * @param string[] $argv Raw arguments including the script name.
	 */
	public static function main( array $argv ): int {
		$options = self::parse( array_slice( $argv, 1 ) );
		if ( $options['help'] ) {
			fwrite( STDOUT, self::usage() );
			return 0;
		}
		if ( $options['unknown'] ) {
			fwrite( STDERR, 'Unknown option(s): ' . implode( ' ', $options['unknown'] ) . PHP_EOL . self::usage() );
			return 1;
		}

		$wp_root = rtrim( str_replace( '\\', '/', $options['wp'] ), '/' );
		if ( ! is_file( $wp_root . '/wp-load.php' ) ) {
			fwrite( STDERR, 'wp-load.php not found under: ' . $wp_root . PHP_EOL . 'Pass --wp=<dir> or set GADGETIM_WP_PATH.' . PHP_EOL );
			return 2;
		}

		self::bootstrap( $wp_root, $options['host'] );

		if ( ! defined( 'GADGETIM_CORE_VERSION' ) ) {
			$plugin = dirname( __DIR__, 2 ) . '/gadgetim-core.php';
			if ( is_file( $plugin ) ) {
				require_once $plugin; // Plugin not active on the site: boot it for this process.
			}
		}
		if ( ! class_exists( Importer::class ) ) {
			fwrite( STDERR, 'gadgetim-core could not be loaded.' . PHP_EOL );
			return 2;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			fwrite( STDERR, 'WooCommerce is not active on this site.' . PHP_EOL );
			return 2;
		}

		self::set_user( $options['user'] );

		return self::run_import( $options );
	}

	/**
	 * Builds and runs an importer from parsed options, printing progress. Returns the exit code.
	 *
	 * @param array         $options Parsed options (see parse()).
	 * @param callable|null $printer Line printer (STDOUT by default).
	 */
	public static function run_import( array $options, ?callable $printer = null ): int {
		$printer ??= static function ( string $line ): void {
			fwrite( STDOUT, $line . PHP_EOL );
		};
		$importer = Importer::create(
			[
				'source'          => '' !== $options['from'] ? $options['from'] : Paths::default_source_dir(),
				'steps'           => $options['steps'],
				'dry_run'         => $options['dry_run'],
				'limit'           => $options['limit'],
				'apply_redirects' => $options['apply_redirects'],
				'output_dir'      => $options['out'],
				'author'          => get_current_user_id(),
			],
			static function ( array $line ) use ( $printer ): void {
				$printer( Log::format( $line ) );
			}
		);
		$job = $importer->job();
		$printer( sprintf( 'Source: %s', $importer->source()->dir() ) );
		$printer( sprintf( 'Steps: %s%s%s', implode( ', ', $job->steps ), $job->dry_run ? ' (dry run)' : '', $job->limit > 0 ? ' limit ' . $job->limit : '' ) );

		$check = $importer->validate();
		if ( is_wp_error( $check ) ) {
			$printer( '!! ' . $check->get_error_message() );
			return 1;
		}
		$started = microtime( true );
		$importer->run_all();
		$printer( sprintf( 'Done in %.1f s — %s', microtime( true ) - $started, $job->summary() ) );
		return $importer->log()->errors() > 0 ? 1 : 0;
	}

	/**
	 * Parses the argument list.
	 *
	 * @param string[] $args Arguments without the script name.
	 * @return array{wp:string,host:string,from:string,steps:string[],dry_run:bool,limit:int,user:string,apply_redirects:bool,out:string,help:bool,unknown:string[]}
	 */
	public static function parse( array $args ): array {
		$env_path = getenv( 'GADGETIM_WP_PATH' );
		$env_host = getenv( 'GADGETIM_WP_HOST' );
		$options  = [
			'wp'              => is_string( $env_path ) && '' !== $env_path ? $env_path : self::DEFAULT_WP_PATH,
			'host'            => is_string( $env_host ) && '' !== $env_host ? $env_host : self::DEFAULT_HOST,
			'from'            => '',
			'steps'           => [],
			'dry_run'         => false,
			'limit'           => 0,
			'user'            => '',
			'apply_redirects' => false,
			'out'             => '',
			'help'            => false,
			'unknown'         => [],
		];
		foreach ( $args as $arg ) {
			if ( '-h' === $arg || '--help' === $arg ) {
				$options['help'] = true;
			} elseif ( in_array( $arg, [ '--terms', '--products', '--images', '--pages', '--redirects' ], true ) ) {
				$options['steps'][] = substr( $arg, 2 );
			} elseif ( '--dry-run' === $arg ) {
				$options['dry_run'] = true;
			} elseif ( '--apply-redirects' === $arg ) {
				$options['apply_redirects'] = true;
			} elseif ( str_starts_with( $arg, '--from=' ) ) {
				$options['from'] = substr( $arg, 7 );
			} elseif ( str_starts_with( $arg, '--limit=' ) ) {
				$options['limit'] = max( 0, (int) substr( $arg, 8 ) );
			} elseif ( str_starts_with( $arg, '--wp=' ) || str_starts_with( $arg, '--path=' ) ) {
				$options['wp'] = substr( $arg, strpos( $arg, '=' ) + 1 );
			} elseif ( str_starts_with( $arg, '--host=' ) ) {
				$options['host'] = substr( $arg, 7 );
			} elseif ( str_starts_with( $arg, '--user=' ) ) {
				$options['user'] = substr( $arg, 7 );
			} elseif ( str_starts_with( $arg, '--out=' ) ) {
				$options['out'] = substr( $arg, 6 );
			} else {
				$options['unknown'][] = $arg;
			}
		}
		return $options;
	}

	/**
	 * Help text.
	 */
	public static function usage(): string {
		return <<<'TXT'
Gadgetim import (plain PHP, no WP-CLI)

  php -c <php.ini> includes/Import/Cli.php [options]

Options:
  --from=<dir>        Export directory (default: <repo>/first-backup/extracted)
  --terms --products --images --pages --redirects
                      Steps to run (default: all of them)
  --dry-run           Report only; nothing is written
  --limit=<n>         Only the first n products
  --apply-redirects   Also write the redirects into the gadgetim_redirects option
  --out=<dir>         Directory for redirects.csv (default: <repo>/data)
  --wp=<dir>          WordPress root (default: $GADGETIM_WP_PATH or the Local site)
  --host=<host>       HTTP_HOST of the simulated request (default: gadgetim.local)
  --user=<id|login>   Run as this user (default: the first administrator)
  -h, --help          This text

Exit codes: 0 ok, 1 finished with errors / bad arguments, 2 WordPress or WooCommerce not found.

TXT;
	}

	/**
	 * Loads WordPress for a fake GET / request (same recipe as tools/wp-bootstrap.php).
	 */
	private static function bootstrap( string $wp_root, string $host ): void {
		$port = '80';
		if ( str_contains( $host, ':' ) ) {
			[ $host, $port ] = explode( ':', $host, 2 );
		}
		$_SERVER['HTTP_HOST']       = $host;
		$_SERVER['SERVER_NAME']     = $host;
		$_SERVER['SERVER_PORT']     = $port;
		$_SERVER['REQUEST_URI']     = '/';
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['QUERY_STRING']    = '';
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['SCRIPT_NAME']     = '/index.php';
		$_SERVER['PHP_SELF']        = '/index.php';
		$_SERVER['SCRIPT_FILENAME'] = $wp_root . '/index.php';
		$_SERVER['DOCUMENT_ROOT']   = $wp_root;
		$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
		$_SERVER['HTTP_USER_AGENT'] = 'gadgetim-import-cli';
		unset( $_SERVER['HTTPS'] );

		if ( ! defined( 'FS_METHOD' ) ) {
			define( 'FS_METHOD', 'direct' );
		}
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}
		ini_set( 'display_errors', 'stderr' );
		if ( '-1' !== trim( (string) ini_get( 'memory_limit' ) ) ) {
			@ini_set( 'memory_limit', '512M' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		require_once $wp_root . '/wp-load.php';
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		ini_set( 'display_errors', 'stderr' );
	}

	/**
	 * Current user for the run: the given id/login, else the first administrator.
	 */
	private static function set_user( string $user ): void {
		$account = null;
		if ( '' !== $user ) {
			$account = is_numeric( $user ) ? get_user_by( 'id', (int) $user ) : get_user_by( 'login', $user );
			if ( ! $account ) {
				$account = get_user_by( 'email', $user );
			}
			if ( ! $account ) {
				fwrite( STDERR, 'User not found: ' . $user . PHP_EOL );
			}
		}
		if ( ! $account ) {
			$admins = get_users(
				[
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
					'order'   => 'ASC',
				]
			);
			$account = $admins ? $admins[0] : null;
		}
		if ( $account ) {
			wp_set_current_user( (int) $account->ID );
		}
	}
}

if ( 'cli' === PHP_SAPI && isset( $_SERVER['argv'][0] ) && is_string( $_SERVER['argv'][0] ) && realpath( $_SERVER['argv'][0] ) === realpath( __FILE__ ) ) {
	exit( Cli::main( $_SERVER['argv'] ) );
}
