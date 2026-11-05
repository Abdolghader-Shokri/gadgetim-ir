<?php
/**
 * Tools tab: a registry of one-shot admin actions run through a nonce-checked admin-post handler.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings\Admin;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Support\Cache;
use Throwable;
use WP_Error;

/**
 * Tools::add( $id, $label, $callable, $capability, $args ) registers a tool; the Tools tab
 * (manage_options) shows a button per tool; the callable runs on admin-post and its result
 * is shown as a notice on the tab. Register tools on admin_init or later.
 */
final class Tools {

	public const ACTION = 'gadgetim_run_tool';

	public const TAB = 'tools';

	private const RESULT_TTL = 5 * MINUTE_IN_SECONDS;

	/** @var array<string,array> */
	private static array $tools = [];

	/**
	 * Registers a tool.
	 *
	 * @param string   $id         Tool id (sanitize_key).
	 * @param string   $label      Translated title.
	 * @param callable $callable   Runs the tool. Return string (success message), WP_Error, bool,
	 *                             or [ 'message' => …, 'type' => success|error|warning|info ].
	 * @param string   $capability Required capability in addition to manage_options (e.g. install_languages).
	 * @param array    $args       'description', 'button' (label), 'confirm' (question), 'order', 'danger' (bool),
	 *                             'render' (callable($tool) printing custom UI instead of the button).
	 */
	public static function add( string $id, string $label, callable $callable, string $capability = 'manage_options', array $args = [] ): void {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return;
		}
		self::$tools[ $id ] = [
			'id'          => $id,
			'label'       => $label,
			'callable'    => $callable,
			'capability'  => '' !== $capability ? $capability : Capabilities::ADMIN,
			'description' => (string) ( $args['description'] ?? '' ),
			'button'      => (string) ( $args['button'] ?? '' ),
			'confirm'     => (string) ( $args['confirm'] ?? '' ),
			'order'       => (int) ( $args['order'] ?? 10 ),
			'danger'      => ! empty( $args['danger'] ),
			'render'      => is_callable( $args['render'] ?? null ) ? $args['render'] : null,
			'_index'      => count( self::$tools ),
		];
	}

	/**
	 * Removes a tool.
	 */
	public static function remove( string $id ): void {
		unset( self::$tools[ sanitize_key( $id ) ] );
	}

	/**
	 * All tools, sorted.
	 */
	public static function all(): array {
		$tools = self::$tools;
		uasort( $tools, static fn( array $a, array $b ): int => [ $a['order'], $a['_index'] ] <=> [ $b['order'], $b['_index'] ] );
		return $tools;
	}

	/**
	 * One tool or null.
	 */
	public static function get( string $id ): ?array {
		return self::$tools[ sanitize_key( $id ) ] ?? null;
	}

	/**
	 * Prints the nonce-protected form that runs a tool (used by the tab template and by custom renderers).
	 */
	public static function form( array $tool, string $button = '' ): void {
		Plugin::view(
			'admin/tool-form',
			[
				'tool'   => $tool,
				'button' => '' !== $button ? $button : ( '' !== $tool['button'] ? $tool['button'] : __( 'Run', 'gadgetim-core' ) ),
			]
		);
	}

	/**
	 * Tools tab renderer (schema 'render' callback).
	 */
	public static function render_tab( array $tab ): void {
		Plugin::view(
			'admin/tools-tab',
			[
				'tab'    => $tab,
				'tools'  => self::all(),
				'result' => self::take_result(),
			]
		);
	}

	/**
	 * admin-post handler: nonce, capabilities, run, store the result, redirect back to the tab.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified just below.
		$id   = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( (string) $_POST['tool'] ) ) : '';
		$tool = self::get( $id );
		if ( null === $tool ) {
			wp_die( esc_html__( 'Unknown tool.', 'gadgetim-core' ), 404 );
		}
		check_admin_referer( self::ACTION . '_' . $id );

		if ( ! current_user_can( Capabilities::for_tab( self::TAB ) ) || ! current_user_can( $tool['capability'] ) ) {
			wp_die( esc_html__( 'You are not allowed to run this tool.', 'gadgetim-core' ), 403 );
		}

		$result = self::run( $tool );
		set_transient( self::result_key(), $result, self::RESULT_TTL );

		wp_safe_redirect( SettingsPage::url( self::TAB ) );
		exit;
	}

	/**
	 * Runs a tool and normalises its result to [ 'tool', 'type', 'message' ].
	 */
	public static function run( array $tool ): array {
		try {
			$raw = call_user_func( $tool['callable'], $tool );
		} catch ( Throwable $e ) {
			$raw = new WP_Error( 'gadgetim_tool_exception', $e->getMessage() );
		}

		$type    = 'success';
		$message = '';
		if ( $raw instanceof WP_Error ) {
			$type    = 'error';
			$message = $raw->get_error_message();
		} elseif ( is_array( $raw ) ) {
			$type    = in_array( $raw['type'] ?? '', [ 'success', 'error', 'warning', 'info' ], true ) ? $raw['type'] : 'success';
			$message = (string) ( $raw['message'] ?? '' );
		} elseif ( is_string( $raw ) ) {
			$message = $raw;
		} elseif ( false === $raw ) {
			$type = 'error';
		}
		if ( '' === $message ) {
			$message = 'error' === $type
				/* translators: %s: tool name */
				? sprintf( __( '"%s" failed.', 'gadgetim-core' ), $tool['label'] )
				/* translators: %s: tool name */
				: sprintf( __( '"%s" finished.', 'gadgetim-core' ), $tool['label'] );
		}
		return [
			'tool'    => $tool['id'],
			'type'    => $type,
			'message' => $message,
		];
	}

	/**
	 * Reads and deletes the last result of the current user.
	 */
	public static function take_result(): ?array {
		$result = get_transient( self::result_key() );
		if ( ! is_array( $result ) ) {
			return null;
		}
		delete_transient( self::result_key() );
		return $result;
	}

	/**
	 * admin_init: the core tools.
	 */
	public static function register_core_tools(): void {
		self::add(
			'purge-cache',
			__( 'Purge page cache', 'gadgetim-core' ),
			[ self::class, 'tool_purge_cache' ],
			Capabilities::ADMIN,
			[
				'description' => __( 'Clears the whole page cache through the installed cache plugin (LiteSpeed Cache, WP Super Cache, Cache Enabler or WP Fastest Cache) and fires gadgetim_cache_purged_all.', 'gadgetim-core' ),
				'button'      => __( 'Purge cache', 'gadgetim-core' ),
				'order'       => 10,
			]
		);
		self::add(
			'flush-rewrites',
			__( 'Flush permalinks', 'gadgetim-core' ),
			[ self::class, 'tool_flush_rewrites' ],
			Capabilities::ADMIN,
			[
				'description' => __( 'Regenerates the rewrite rules in Persian context (the only safe context for a flush, see architecture 3.2).', 'gadgetim-core' ),
				'button'      => __( 'Flush permalinks', 'gadgetim-core' ),
				'order'       => 20,
			]
		);
	}

	/**
	 * Tool: purge cache.
	 */
	public static function tool_purge_cache(): string {
		$adapter = Cache::adapter_label();
		Cache::purge_all();
		/* translators: %s: cache plugin name */
		return sprintf( __( 'Page cache purged (%s).', 'gadgetim-core' ), $adapter );
	}

	/**
	 * Tool: flush rewrite rules inside the Persian language context when I18n is present.
	 */
	public static function tool_flush_rewrites(): string {
		$flush = static function (): void {
			flush_rewrite_rules( false );
		};
		if ( class_exists( 'Gadgetim\Core\I18n\Languages' ) && method_exists( 'Gadgetim\Core\I18n\Languages', 'with_lang' ) ) {
			\Gadgetim\Core\I18n\Languages::with_lang( 'fa', $flush );
		} else {
			$flush();
		}
		return __( 'Rewrite rules regenerated.', 'gadgetim-core' );
	}

	private static function result_key(): string {
		return 'gadgetim_tool_result_' . get_current_user_id();
	}
}
