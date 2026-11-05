<?php
/**
 * WP-CLI commands: wp gadgetim import | reprice | migrate-currency | redirects | translations.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Cli;

use Gadgetim\Core\Import\Cli as ImportCli;
use Gadgetim\Core\Import\Paths;
use Gadgetim\Core\Import\Redirects;
use Gadgetim\Core\Support\Options;
use WP_CLI;

/**
 * Manages the Gadgetim storefront: import, pricing, redirects, translations.
 */
final class Commands {

	/**
	 * Imports the old site from the extracted backup.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<dir>]
	 * : Export directory. Default: <repo>/first-backup/extracted.
	 *
	 * [--terms]
	 * : Import categories, brands and tags.
	 *
	 * [--products]
	 * : Import products.
	 *
	 * [--images]
	 * : Import product images (with --products: inline).
	 *
	 * [--pages]
	 * : Import the content pages.
	 *
	 * [--redirects]
	 * : Write data/redirects.csv.
	 *
	 * [--dry-run]
	 * : Report only; nothing is written.
	 *
	 * [--limit=<n>]
	 * : Only the first n products.
	 *
	 * [--apply-redirects]
	 * : Also write the redirects into the gadgetim_redirects option.
	 *
	 * [--out=<dir>]
	 * : Directory for redirects.csv. Default: <repo>/data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gadgetim import --dry-run
	 *     wp gadgetim import --terms --products --images
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Flags.
	 */
	public function import( array $args, array $assoc_args ): void {
		$options = ImportCli::parse( [] );
		$steps   = [];
		foreach ( [ 'terms', 'products', 'images', 'pages', 'redirects' ] as $step ) {
			if ( WP_CLI\Utils\get_flag_value( $assoc_args, $step, false ) ) {
				$steps[] = $step;
			}
		}
		$options['steps']           = $steps;
		$options['from']            = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'from', '' );
		$options['dry_run']         = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$options['limit']           = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$options['apply_redirects'] = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply-redirects', false );
		$options['out']             = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'out', '' );

		if ( ! get_current_user_id() ) {
			$admins = get_users(
				[
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
					'order'   => 'ASC',
				]
			);
			if ( $admins ) {
				wp_set_current_user( (int) $admins[0]->ID );
			}
		}

		$code = ImportCli::run_import(
			$options,
			static function ( string $line ): void {
				WP_CLI::log( $line );
			}
		);
		if ( 0 === $code ) {
			WP_CLI::success( 'Import finished.' );
		} else {
			WP_CLI::error( 'Import finished with errors.' );
		}
	}

	/**
	 * Re-applies the current rates to every product priced in a currency (Pricing module).
	 *
	 * ## OPTIONS
	 *
	 * [--currency=<code>]
	 * : Currency code (default: every enabled currency).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gadgetim reprice --currency=AED
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Flags.
	 */
	public function reprice( array $args, array $assoc_args ): void {
		$class = 'Gadgetim\Core\Pricing\Reprice';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'reprice_currency' ) ) {
			WP_CLI::error( 'The Pricing module is not available (Gadgetim\Core\Pricing\Reprice::reprice_currency).' );
		}
		$code       = strtoupper( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'currency', '' ) );
		$currencies = Options::currencies();
		$codes      = '' !== $code ? [ $code ] : array_keys( array_filter( $currencies, static fn( $c ): bool => is_array( $c ) && ! empty( $c['enabled'] ) ) );
		if ( ! $codes ) {
			WP_CLI::error( 'No enabled currency found.' );
		}
		foreach ( $codes as $c ) {
			if ( ! isset( $currencies[ $c ] ) ) {
				WP_CLI::warning( sprintf( 'Unknown currency %s.', $c ) );
				continue;
			}
			WP_CLI::log( sprintf( 'Repricing %s products…', $c ) );
			call_user_func( [ $class, 'reprice_currency' ], $c, get_current_user_id() );
		}
		WP_CLI::success( 'Reprice scheduled/finished (see Gadgetim → Currencies for progress).' );
	}

	/**
	 * Moves the old `_gadgetim_currency_*` meta onto the multi-currency fields (Pricing module).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gadgetim migrate-currency
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Flags.
	 */
	public function migrate_currency( array $args, array $assoc_args ): void {
		$class = 'Gadgetim\Core\Pricing\Migration';
		if ( ! class_exists( $class ) ) {
			WP_CLI::error( 'The Pricing module is not available (Gadgetim\Core\Pricing\Migration).' );
		}
		$method = method_exists( $class, 'run' ) ? 'run' : ( method_exists( $class, 'migrate' ) ? 'migrate' : '' );
		if ( '' === $method ) {
			WP_CLI::error( 'Gadgetim\Core\Pricing\Migration has no run()/migrate() method.' );
		}
		$result = call_user_func( [ $class, $method ] );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( is_scalar( $result ) ? (string) $result : 'Currency migration finished.' );
	}

	/**
	 * Imports data/redirects.csv into the redirect option, or exports the option to CSV.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : CSV path. Default: <repo>/data/redirects.csv.
	 *
	 * [--export]
	 * : Write the current gadgetim_redirects option to the file instead of importing.
	 *
	 * [--dry-run]
	 * : List the rows without writing the option.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gadgetim redirects
	 *     wp gadgetim redirects data/redirects.csv --dry-run
	 *     wp gadgetim redirects exported.csv --export
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Flags.
	 */
	public function redirects( array $args, array $assoc_args ): void {
		$file = isset( $args[0] ) && '' !== $args[0] ? (string) $args[0] : Paths::data_file( Redirects::FILE );

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'export', false ) ) {
			$rows = [];
			foreach ( Options::redirects() as $from => $entry ) {
				$rows[] = [
					'from'   => (string) $from,
					'to'     => (string) ( $entry[0] ?? '' ),
					'status' => (int) ( $entry[1] ?? 301 ),
					'note'   => '',
				];
			}
			$written = Redirects::write_csv( $rows, $file );
			if ( false === $written ) {
				WP_CLI::error( sprintf( 'Could not write %s.', $file ) );
			}
			WP_CLI::success( sprintf( 'Exported %d redirects to %s.', $written, $file ) );
			return;
		}

		$rows = Redirects::parse_csv( $file );
		if ( ! $rows ) {
			WP_CLI::error( sprintf( 'No redirect rows found in %s.', $file ) );
		}
		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			foreach ( $rows as $row ) {
				WP_CLI::log( sprintf( '%s -> %s (%d) %s', $row['from'], '' !== $row['to'] ? $row['to'] : '-', $row['status'], $row['note'] ) );
			}
			WP_CLI::success( sprintf( '%d rows (dry run).', count( $rows ) ) );
			return;
		}
		$applied = Redirects::apply( $rows );
		WP_CLI::success( sprintf( 'Applied %d redirects to the gadgetim_redirects option.', $applied ) );
	}

	/**
	 * Exports or imports the en/ar translation meta of products, pages and terms as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : export or import.
	 *
	 * --file=<path>
	 * : JSON file to write (export) or read (import).
	 *
	 * [--lang=<code>]
	 * : Only this language (en, ar). Default: every non-Persian language.
	 *
	 * [--type=<types>]
	 * : Comma list of product, page, product_cat, product_brand, product_tag. Default: all.
	 *
	 * [--dry-run]
	 * : Import: report what would change without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gadgetim translations export --file=translations.json --lang=en
	 *     wp gadgetim translations import --file=translations.json
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Flags.
	 */
	public function translations( array $args, array $assoc_args ): void {
		$action = strtolower( (string) ( $args[0] ?? '' ) );
		$file   = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'file', '' );
		$lang   = sanitize_key( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'lang', '' ) );
		$types  = array_filter( array_map( 'trim', explode( ',', (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'type', '' ) ) ) );
		if ( ! in_array( $action, [ 'export', 'import' ], true ) ) {
			WP_CLI::error( 'Usage: wp gadgetim translations <export|import> --file=<path>' );
		}
		if ( '' === $file ) {
			WP_CLI::error( '--file=<path> is required.' );
		}
		$io = new TranslationsIo( '' !== $lang ? [ $lang ] : [], $types );
		if ( 'export' === $action ) {
			$result = $io->export_to_file( $file );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			WP_CLI::success( sprintf( 'Exported %d objects to %s.', $result, $file ) );
			return;
		}
		$dry    = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$result = $io->import_from_file( $file, $dry );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result['log'] as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::success( sprintf( '%s%d fields on %d objects.', $dry ? '[dry run] ' : '', $result['fields'], $result['objects'] ) );
	}
}
