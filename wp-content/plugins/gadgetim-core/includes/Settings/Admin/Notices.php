<?php
/**
 * Persistent admin notices, including per-product flags (architecture §4.3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings\Admin;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Settings\Capabilities;

/**
 * Notices survive across requests (option gadgetim_notices) until dismissed or cleared by code.
 * Shown on the dashboard, the Gadgetim pages and the product screens.
 */
final class Notices {

	public const OPTION = 'gadgetim_notices';

	public const DISMISS_ACTION = 'gadgetim_dismiss_notice';

	private const MAX = 100;

	/**
	 * Adds or replaces a notice.
	 *
	 * @param string $id      Stable id (same id = same notice, updated in place).
	 * @param string $message Plain text or limited HTML (wp_kses_post on output).
	 * @param string $type    error | warning | success | info.
	 * @param array  $args    'dismissible' (true), 'capability' (default capability), 'link' => [ 'url', 'label' ], 'product_id'.
	 */
	public static function add( string $id, string $message, string $type = 'warning', array $args = [] ): void {
		$id = sanitize_key( $id );
		if ( '' === $id || '' === trim( $message ) ) {
			return;
		}
		$notices = self::all();
		$link    = [];
		if ( ! empty( $args['link']['url'] ) ) {
			$link = [
				'url'   => esc_url_raw( (string) $args['link']['url'] ),
				'label' => sanitize_text_field( (string) ( $args['link']['label'] ?? __( 'Open', 'gadgetim-core' ) ) ),
			];
		}
		$notices[ $id ] = [
			'message'     => wp_kses_post( $message ),
			'type'        => in_array( $type, [ 'error', 'warning', 'success', 'info' ], true ) ? $type : 'warning',
			'dismissible' => (bool) ( $args['dismissible'] ?? true ),
			'capability'  => sanitize_key( (string) ( $args['capability'] ?? '' ) ),
			'link'        => $link,
			'product_id'  => absint( $args['product_id'] ?? 0 ),
			'time'        => time(),
		];
		if ( count( $notices ) > self::MAX ) {
			uasort( $notices, static fn( array $a, array $b ): int => $b['time'] <=> $a['time'] );
			$notices = array_slice( $notices, 0, self::MAX, true );
		}
		update_option( self::OPTION, $notices, true );
	}

	/**
	 * Flags a product with a persistent warning linking to its edit screen (one flag per product;
	 * a new message replaces the previous one).
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $message    Problem description.
	 * @param array  $args       See add(); 'type' may override the warning level.
	 */
	public static function flag_product( int $product_id, string $message, array $args = [] ): void {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}
		$title   = get_the_title( $product_id );
		$edit_id = $product_id;
		$post    = get_post( $product_id );
		if ( $post && 'product_variation' === $post->post_type && $post->post_parent ) {
			$edit_id = (int) $post->post_parent;
		}
		$label = '' !== $title ? $title : sprintf( '#%d', $product_id );
		/* translators: 1: product name, 2: problem description */
		$text = sprintf( __( 'Product "%1$s": %2$s', 'gadgetim-core' ), $label, $message );
		$link = get_edit_post_link( $edit_id, 'raw' );

		self::add(
			'product-' . $product_id,
			$text,
			(string) ( $args['type'] ?? 'warning' ),
			array_merge(
				$args,
				[
					'product_id' => $product_id,
					'link'       => $link ? [ 'url' => $link, 'label' => __( 'Edit product', 'gadgetim-core' ) ] : [],
				]
			)
		);
	}

	/**
	 * Removes a notice.
	 */
	public static function clear( string $id ): void {
		$id      = sanitize_key( $id );
		$notices = self::all();
		if ( isset( $notices[ $id ] ) ) {
			unset( $notices[ $id ] );
			update_option( self::OPTION, $notices, true );
		}
	}

	/**
	 * Removes the flag of a product.
	 */
	public static function clear_product( int $product_id ): void {
		self::clear( 'product-' . absint( $product_id ) );
	}

	/**
	 * Removes every notice (used by tools/tests).
	 */
	public static function clear_all(): void {
		delete_option( self::OPTION );
	}

	/**
	 * All stored notices [ id => notice ].
	 */
	public static function all(): array {
		$notices = get_option( self::OPTION, [] );
		return is_array( $notices ) ? $notices : [];
	}

	/**
	 * Whether a notice exists.
	 */
	public static function has( string $id ): bool {
		return isset( self::all()[ sanitize_key( $id ) ] );
	}

	/**
	 * admin_notices: prints the notices the current user may see on the relevant screens.
	 */
	public static function render(): void {
		if ( ! self::screen_allowed() ) {
			return;
		}
		foreach ( self::all() as $id => $notice ) {
			$cap = '' !== $notice['capability'] ? $notice['capability'] : Capabilities::default_capability();
			if ( ! current_user_can( $cap ) ) {
				continue;
			}
			Plugin::view(
				'admin/notice',
				[
					'id'     => (string) $id,
					'notice' => $notice,
				]
			);
		}
	}

	/**
	 * admin-post handler for the dismiss button.
	 */
	public static function handle_dismiss(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified just below.
		$id = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( (string) $_POST['notice'] ) ) : '';
		if ( '' === $id ) {
			wp_die( esc_html__( 'Missing notice id.', 'gadgetim-core' ), 400 );
		}
		check_admin_referer( self::DISMISS_ACTION . '_' . $id );

		$notice = self::all()[ $id ] ?? null;
		$cap    = $notice && '' !== $notice['capability'] ? $notice['capability'] : Capabilities::default_capability();
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'gadgetim-core' ), 403 );
		}
		self::clear( $id );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/**
	 * Dashboard, Gadgetim pages, product list and product edit screens.
	 */
	public static function screen_allowed(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		if ( 'dashboard' === $screen->id || str_contains( $screen->id, SettingsPage::SLUG ) ) {
			return true;
		}
		return in_array( $screen->post_type ?? '', [ 'product', 'product_variation' ], true ) || 'edit-product' === $screen->id || 'product' === $screen->id;
	}
}
