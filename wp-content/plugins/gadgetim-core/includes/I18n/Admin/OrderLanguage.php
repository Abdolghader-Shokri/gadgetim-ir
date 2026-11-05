<?php
/**
 * Order language in the admin: list column and a small meta box (architecture §3.8).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Orders;
use WC_Order;
use WP_Post;

/**
 * Works with HPOS (wc-orders screen) and the legacy shop_order post type.
 */
final class OrderLanguage {

	public const COLUMN = 'gadgetim_lang';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_shop_order_list_table_columns', [ self::class, 'add_column' ], 20, 1 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ self::class, 'render_hpos_column' ], 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', [ self::class, 'add_column' ], 20, 1 );
		add_action( 'manage_shop_order_posts_custom_column', [ self::class, 'render_legacy_column' ], 10, 2 );
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_box' ], 30, 2 );
	}

	/**
	 * Column after the status column.
	 *
	 * @param mixed $columns Columns.
	 */
	public static function add_column( $columns ) {
		if ( ! is_array( $columns ) || isset( $columns[ self::COLUMN ] ) ) {
			return $columns;
		}
		$out = [];
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out[ self::COLUMN ] = __( 'Language', 'gadgetim-core' );
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Language', 'gadgetim-core' );
		}
		return $out;
	}

	/**
	 * HPOS column cell.
	 *
	 * @param mixed $column Column.
	 * @param mixed $order  Order.
	 */
	public static function render_hpos_column( $column, $order = null ): void {
		if ( self::COLUMN === $column && $order instanceof WC_Order ) {
			echo esc_html( self::label( $order ) );
		}
	}

	/**
	 * Legacy column cell.
	 *
	 * @param mixed $column  Column.
	 * @param mixed $post_id Post ID.
	 */
	public static function render_legacy_column( $column, $post_id = 0 ): void {
		if ( self::COLUMN !== $column || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( (int) $post_id );
		if ( $order instanceof WC_Order ) {
			echo esc_html( self::label( $order ) );
		}
	}

	/**
	 * add_meta_boxes: "Language" box on the order screen (HPOS and legacy).
	 *
	 * @param mixed $screen_or_type Screen id or post type.
	 * @param mixed $object         Order or post.
	 */
	public static function add_meta_box( $screen_or_type, $object = null ): void {
		if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
			return;
		}
		$screen = wc_get_page_screen_id( 'shop-order' );
		if ( '' === $screen || $screen_or_type !== $screen ) {
			return;
		}
		add_meta_box(
			'gadgetim-order-language',
			__( 'Language', 'gadgetim-core' ),
			[ self::class, 'render_meta_box' ],
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Meta box body.
	 *
	 * @param mixed $object Order (HPOS) or WP_Post (legacy).
	 */
	public static function render_meta_box( $object ): void {
		$order = $object instanceof WC_Order ? $object : ( $object instanceof WP_Post && function_exists( 'wc_get_order' ) ? wc_get_order( $object->ID ) : null );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$lang = Orders::lang( $order );
		echo '<p><strong>' . esc_html( self::label( $order ) ) . '</strong> <code>' . esc_html( $lang ) . '</code> &middot; <code>' . esc_html( Languages::locale( $lang ) ) . '</code></p>';
		echo '<p class="description">' . esc_html__( 'Customer e-mails and the customer-facing order pages for this order use this language; admin e-mails and this screen stay Persian.', 'gadgetim-core' ) . '</p>';
	}

	private static function label( WC_Order $order ): string {
		$lang = Orders::lang( $order );
		return Languages::label( $lang );
	}
}
