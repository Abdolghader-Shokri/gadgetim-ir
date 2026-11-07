<?php
/**
 * Extra order status "Shipped" (wc-shipped). The old gadgetim.ir theme registered it and imported
 * orders carry it; without a registered status those orders would be hidden from the order list.
 * It counts as paid (reports, analytics) and gets a "Change status to shipped" bulk action.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

/**
 * Registers wc-shipped.
 */
final class OrderStatuses {

	public const SHIPPED = 'wc-shipped';

	/**
	 * Hooks (woocommerce_loaded).
	 */
	public static function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_post_status' ], 9 );
		add_filter( 'wc_order_statuses', [ self::class, 'order_statuses' ] );
		add_filter( 'woocommerce_order_is_paid_statuses', [ self::class, 'paid_statuses' ] );
		add_filter( 'woocommerce_reports_order_statuses', [ self::class, 'paid_statuses' ] );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ self::class, 'bulk_actions' ], 20 );
		add_filter( 'bulk_actions-edit-shop_order', [ self::class, 'bulk_actions' ], 20 );
	}

	/**
	 * init (9): the post status behind the order status (HPOS reads registered statuses too).
	 */
	public static function register_post_status(): void {
		register_post_status(
			self::SHIPPED,
			[
				'label'                     => _x( 'Shipped', 'Order status', 'gadgetim-core' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>', 'gadgetim-core' ),
			]
		);
	}

	/**
	 * wc_order_statuses: insert "Shipped" right after "Processing".
	 *
	 * @param array<string,string> $statuses Status key => label.
	 * @return array<string,string>
	 */
	public static function order_statuses( $statuses ): array {
		$statuses = (array) $statuses;
		$out      = [];
		foreach ( $statuses as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$out[ self::SHIPPED ] = _x( 'Shipped', 'Order status', 'gadgetim-core' );
			}
		}
		if ( ! isset( $out[ self::SHIPPED ] ) ) {
			$out[ self::SHIPPED ] = _x( 'Shipped', 'Order status', 'gadgetim-core' );
		}
		return $out;
	}

	/**
	 * Paid-status lists take statuses without the "wc-" prefix.
	 *
	 * @param mixed $statuses Status slugs.
	 * @return array<int,string>
	 */
	public static function paid_statuses( $statuses ): array {
		$statuses = is_array( $statuses ) ? $statuses : [];
		if ( ! in_array( 'shipped', $statuses, true ) ) {
			$statuses[] = 'shipped';
		}
		return $statuses;
	}

	/**
	 * Order list bulk action; WooCommerce handles "mark_<status>" for every registered status.
	 *
	 * @param array<string,string> $actions Bulk actions.
	 * @return array<string,string>
	 */
	public static function bulk_actions( $actions ): array {
		$actions                 = (array) $actions;
		$actions['mark_shipped'] = __( 'Change status to shipped', 'gadgetim-core' );
		return $actions;
	}
}
