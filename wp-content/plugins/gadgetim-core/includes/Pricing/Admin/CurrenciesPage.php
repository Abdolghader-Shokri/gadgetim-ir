<?php
/**
 * Gadgetim → Currencies tab (architecture §4.4, manage_options).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing\Admin;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Pricing\Calculator;
use Gadgetim\Core\Pricing\Currencies;
use Gadgetim\Core\Pricing\Products;
use Gadgetim\Core\Pricing\Reprice;
use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Options;

/**
 * Rates inline, rounding mode/step, enable toggle, affected product count, "Apply rates" with a
 * confirmation (last-backup age) and a progress bar polling Reprice::ajax_progress(), rate
 * history. Saving never touches products; only "Apply rates" does.
 */
final class CurrenciesPage {

	public const TAB = 'currencies';

	public const SAVE_ACTION   = 'gadgetim_save_currencies';
	public const APPLY_ACTION  = 'gadgetim_apply_rates';
	public const DELETE_ACTION = 'gadgetim_delete_currency';

	public const SCRIPT = 'gadgetim-pricing-currencies';
	public const STYLE  = 'gadgetim-pricing-admin';

	public const HISTORY_ROWS   = 50;
	public const BACKUP_MAX_AGE = DAY_IN_SECONDS;

	/**
	 * init: the tab (Capabilities forces manage_options for 'currencies' regardless).
	 */
	public static function register_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		Schema::add_tab(
			self::TAB,
			__( 'Currencies', 'gadgetim-core' ),
			'manage_options',
			[],
			[
				'order'       => 40,
				'render'      => [ self::class, 'render' ],
				'description' => __( 'Base currencies, their Toman rates and rounding. Saving a rate changes no product; "Apply rates" re-saves every product priced in that currency (in the background for large sets) and purges the page cache once at the end. Take a backup first.', 'gadgetim-core' ),
			]
		);
	}

	/**
	 * Tab renderer.
	 */
	public static function render( array $tab = [] ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage currencies.', 'gadgetim-core' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$job      = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( (string) $_GET['job'] ) ) : '';
		$progress = '' !== $job ? Reprice::progress( $job ) : null;

		self::view(
			'currencies-page',
			[
				'currencies'     => Currencies::all(),
				'counts'         => Products::counts_by_currency(),
				'modes'          => Calculator::mode_labels(),
				'languages'      => Options::languages(),
				'history'        => Currencies::history( self::HISTORY_ROWS ),
				'backup'         => self::backup_info(),
				'job'            => $job,
				'progress'       => $progress,
				'last'           => Reprice::last(),
				'page_url'       => self::url(),
				'sync_threshold' => Reprice::SYNC_THRESHOLD,
				'legacy_count'   => Products::count_with_legacy_meta(),
			]
		);
	}

	/**
	 * admin_enqueue_scripts: assets on the Currencies tab only.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! class_exists( SettingsPage::class ) || ! SettingsPage::is_our_screen( $hook ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		if ( self::TAB !== $tab ) {
			return;
		}
		wp_enqueue_style( self::STYLE, Plugin::url( 'includes/Pricing/Admin/assets/pricing-admin.css' ), [], GADGETIM_CORE_VERSION );
		wp_enqueue_script( self::SCRIPT, Plugin::url( 'includes/Pricing/Admin/assets/currencies.js' ), [], GADGETIM_CORE_VERSION, true );
		wp_localize_script(
			self::SCRIPT,
			'gadgetimCurrencies',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => Reprice::AJAX_ACTION,
				'nonce'        => wp_create_nonce( Reprice::NONCE ),
				'pollInterval' => 1500,
				'doneUrl'      => self::url(),
				'i18n'         => [
					/* translators: 1: number of products, 2: currency code */
					'confirmApply'  => __( 'Recompute the Toman prices of %1$s products priced in %2$s with the saved rate?', 'gadgetim-core' ),
					'backupStale'   => __( 'Warning: the last known backup is older than 24 hours (or unknown). The runbook requires a fresh backup before applying rates.', 'gadgetim-core' ),
					'saveFirst'     => __( 'You changed values in the table. Save the currencies first, then apply the rates.', 'gadgetim-core' ),
					'confirmDelete' => __( 'Delete this currency? Only currencies without products can be deleted.', 'gadgetim-core' ),
					/* translators: 1: processed products, 2: total products */
					'progress'      => __( '%1$s of %2$s products recomputed…', 'gadgetim-core' ),
					/* translators: 1: processed products, 2: failed products */
					'finished'      => __( 'Done: %1$s products recomputed (%2$s failed). Reloading…', 'gadgetim-core' ),
					'error'         => __( 'The progress could not be read. The job keeps running in the background; reload the page to check.', 'gadgetim-core' ),
				],
			]
		);
	}

	/**
	 * admin-post gadgetim_save_currencies.
	 */
	public static function handle_save(): void {
		check_admin_referer( self::SAVE_ACTION );
		self::authorize();

		$user          = get_current_user_id();
		$before        = Currencies::all();
		$errors        = [];
		$changed_rates = [];

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- every value is sanitized field by field inside Currencies::save().
		$rows = isset( $_POST['currencies'] ) && is_array( $_POST['currencies'] ) ? wp_unslash( $_POST['currencies'] ) : [];
		foreach ( $rows as $code => $fields ) {
			$code = Currencies::normalize_code( (string) $code );
			if ( '' === $code || ! isset( $before[ $code ] ) || ! is_array( $fields ) ) {
				continue;
			}
			$data = [ 'enabled' => ! empty( $fields['enabled'] ) ];
			foreach ( [ 'rate', 'rounding_mode', 'rounding_step', 'decimals', 'symbol' ] as $key ) {
				if ( array_key_exists( $key, $fields ) ) {
					$data[ $key ] = $fields[ $key ];
				}
			}
			if ( isset( $fields['label'] ) && is_array( $fields['label'] ) ) {
				$data['label'] = $fields['label'];
			}
			$saved = Currencies::save( $code, $data, $user );
			if ( is_wp_error( $saved ) ) {
				$errors[] = $saved->get_error_message();
				continue;
			}
			// Rate, mode or step now differ from what the products were priced with.
			if ( Currencies::has_pending( $saved ) ) {
				$changed_rates[] = $code;
			}
		}

		$new      = isset( $_POST['new'] ) && is_array( $_POST['new'] ) ? wp_unslash( $_POST['new'] ) : [];
		$new_raw  = is_scalar( $new['code'] ?? null ) ? trim( (string) $new['code'] ) : '';
		$new_code = Currencies::normalize_code( $new_raw );
		if ( '' !== $new_raw ) {
			if ( '' === $new_code ) {
				$errors[] = __( 'The new currency code must be three Latin letters (ISO 4217).', 'gadgetim-core' );
			} elseif ( isset( $before[ $new_code ] ) ) {
				/* translators: %s: currency code */
				$errors[] = sprintf( __( '%s already exists.', 'gadgetim-core' ), $new_code );
			} else {
				$saved = Currencies::save(
					$new_code,
					[
						'rate'          => $new['rate'] ?? 0,
						'label'         => [ 'fa' => is_scalar( $new['label'] ?? null ) ? (string) $new['label'] : '' ],
						'symbol'        => is_scalar( $new['symbol'] ?? null ) && '' !== trim( (string) $new['symbol'] ) ? (string) $new['symbol'] : $new_code,
						'rounding_mode' => Calculator::MODE_NEAREST,
						'rounding_step' => 10000,
						'decimals'      => 2,
						'enabled'       => ! empty( $new['enabled'] ),
					],
					$user
				);
				if ( is_wp_error( $saved ) ) {
					$errors[] = $saved->get_error_message();
				}
			}
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		if ( $errors ) {
			self::add_notice( 'gadgetim_currencies_error', implode( ' ', $errors ), 'error' );
		}
		$message = __( 'Currencies saved.', 'gadgetim-core' );
		$type    = 'success';
		if ( $changed_rates ) {
			$counts = Products::counts_by_currency();
			$parts  = [];
			foreach ( $changed_rates as $code ) {
				/* translators: 1: currency code, 2: number of products */
				$parts[] = sprintf( __( '%1$s (%2$s products)', 'gadgetim-core' ), $code, number_format_i18n( $counts[ $code ] ?? 0 ) );
			}
			/* translators: %s: list of currencies with product counts */
			$message .= ' ' . sprintf( __( 'Rates or rounding changed for %s. No product price changed yet, and product saves keep using the previous values until you use "Apply rates".', 'gadgetim-core' ), implode( ', ', $parts ) );
			$type     = 'warning';
		}
		self::redirect_with_notice( 'gadgetim_currencies_saved', $message, $type );
	}

	/**
	 * admin-post gadgetim_apply_rates: starts the reprice job for one currency.
	 */
	public static function handle_apply(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce action carries the code; verified on the next line.
		$code = isset( $_POST['code'] ) ? Currencies::normalize_code( sanitize_text_field( wp_unslash( (string) $_POST['code'] ) ) ) : '';
		check_admin_referer( self::APPLY_ACTION . '_' . $code );
		self::authorize();

		$job = Reprice::reprice_currency( $code, get_current_user_id() );
		if ( is_wp_error( $job ) ) {
			self::redirect_with_notice( 'gadgetim_apply_error', $job->get_error_message(), 'error' );
		}
		$progress = Reprice::progress( $job );
		if ( is_array( $progress ) && $progress['finished'] ) {
			self::redirect_with_notice(
				'gadgetim_apply_done',
				sprintf(
					/* translators: 1: number of products, 2: currency code, 3: number of failures */
					__( 'Toman prices of %1$s products priced in %2$s were recomputed (%3$s failed). The page cache was purged.', 'gadgetim-core' ),
					number_format_i18n( $progress['done'] ),
					$code,
					number_format_i18n( $progress['failed'] )
				),
				$progress['failed'] > 0 ? 'warning' : 'success'
			);
		}
		wp_safe_redirect( self::url( [ 'job' => $job ] ) );
		exit;
	}

	/**
	 * admin-post gadgetim_delete_currency.
	 */
	public static function handle_delete(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce action carries the code; verified on the next line.
		$code = isset( $_POST['code'] ) ? Currencies::normalize_code( sanitize_text_field( wp_unslash( (string) $_POST['code'] ) ) ) : '';
		check_admin_referer( self::DELETE_ACTION . '_' . $code );
		self::authorize();

		$result = Currencies::delete( $code );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'gadgetim_delete_error', $result->get_error_message(), 'error' );
		}
		/* translators: %s: currency code */
		self::redirect_with_notice( 'gadgetim_deleted', sprintf( __( 'Currency %s deleted.', 'gadgetim-core' ), $code ), 'success' );
	}

	/**
	 * URL of the tab, with extra query arguments.
	 */
	public static function url( array $args = [] ): string {
		if ( class_exists( SettingsPage::class ) ) {
			return SettingsPage::url( self::TAB, $args );
		}
		return add_query_arg(
			$args + [
				'page' => 'gadgetim',
				'tab'  => self::TAB,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Last known backup from the marker file written by tools/server/backup.sh (architecture §7.2).
	 * The marker holds a Unix timestamp or an ISO date; its mtime is the fallback.
	 *
	 * @return array{path:string,time:int,age:?int,stale:bool}
	 */
	public static function backup_info(): array {
		/**
		 * Filters the path of the last-backup marker file.
		 *
		 * @param string $path Absolute path.
		 */
		$path = (string) apply_filters( 'gadgetim_backup_marker', trailingslashit( dirname( ABSPATH ) ) . 'backups/last-backup' );
		$time = 0;
		if ( '' !== $path && is_file( $path ) && is_readable( $path ) ) {
			$content = trim( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- small local marker file.
			if ( '' !== $content && ctype_digit( $content ) ) {
				$time = (int) $content;
			} elseif ( '' !== $content && false !== strtotime( $content ) ) {
				$time = (int) strtotime( $content );
			} else {
				$time = (int) filemtime( $path );
			}
		}
		return [
			'path'  => $path,
			'time'  => $time,
			'age'   => $time > 0 ? max( 0, time() - $time ) : null,
			'stale' => $time <= 0 || ( time() - $time ) > self::BACKUP_MAX_AGE,
		];
	}

	private static function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage currencies.', 'gadgetim-core' ), 403 );
		}
	}

	/**
	 * Queues a notice for the settings page (the Settings API transient, read on settings-updated).
	 */
	private static function add_notice( string $id, string $message, string $type ): void {
		$group = class_exists( Settings::class ) ? Settings::GROUP : 'gadgetim_settings';
		add_settings_error( $group, $id, $message, $type );
	}

	/**
	 * Stores the queued notices and redirects back to the tab.
	 */
	private static function redirect_with_notice( string $id, string $message, string $type, array $args = [] ): void {
		self::add_notice( $id, $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( self::url( [ 'settings-updated' => 'true' ] + $args ) );
		exit;
	}

	/**
	 * Includes a view from Admin/views/ (the view reads $args).
	 */
	private static function view( string $name, array $args ): void {
		$file = __DIR__ . '/views/' . preg_replace( '/[^a-z0-9-]/', '', $name ) . '.php';
		if ( ! is_file( $file ) ) {
			return;
		}
		( static function ( string $file, array $args ): void {
			include $file;
		} )( $file, $args );
	}
}
