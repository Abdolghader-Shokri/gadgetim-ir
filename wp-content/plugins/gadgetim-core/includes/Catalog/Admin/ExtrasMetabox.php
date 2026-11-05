<?php
/**
 * "Gadgetim" product metabox: subtitle, short title, spec table, FAQ, guarantee, views.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog\Admin;

use Gadgetim\Core\Catalog\ProductExtras;
use Gadgetim\Core\Catalog\TitleCleaner;
use Gadgetim\Core\Plugin;
use WC_Product;
use WP_Post;

/**
 * Persian values only; the per-language variants belong to the I18n translation metabox.
 * Saving runs on woocommerce_admin_process_product_object so the meta lands in the same
 * save as WooCommerce's own fields (architecture §4.3 hook order).
 */
final class ExtrasMetabox {

	public const ID = 'gadgetim-product-extras';

	public const NONCE_ACTION = 'gadgetim_save_extras';

	public const NONCE_FIELD = 'gadgetim_extras_nonce';

	public const FIELD = 'gtm_extras';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_product', [ self::class, 'add_metabox' ] );
		add_action( 'woocommerce_admin_process_product_object', [ self::class, 'save' ], 20, 1 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * add_meta_boxes_product.
	 */
	public static function add_metabox(): void {
		add_meta_box(
			self::ID,
			__( 'Gadgetim — product extras', 'gadgetim-core' ),
			[ self::class, 'render' ],
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * admin_enqueue_scripts: the plugin's admin assets (repeater behaviour) on product screens.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'gadgetim-admin', Plugin::url( 'assets/admin/admin.css' ), [], GADGETIM_CORE_VERSION );
		wp_enqueue_script( 'gadgetim-admin', Plugin::url( 'assets/admin/admin.js' ), [ 'jquery' ], GADGETIM_CORE_VERSION, true );
		wp_add_inline_style(
			'gadgetim-admin',
			'.gtm-extras{display:grid;gap:1rem}.gtm-extras__field label.gtm-field__label{display:block;margin-block-end:.25rem}'
			. '.gtm-extras .gtm-repeater__row .gtm-input--label{flex:0 1 240px}.gtm-extras .gtm-repeater__row textarea{flex:1 1 auto;min-height:2.4em}'
			. '.gtm-extras__hint{color:#646970;font-size:12px;margin:.25rem 0 0}'
		);
	}

	/**
	 * Renders the metabox.
	 */
	public static function render( WP_Post $post ): void {
		$id        = (int) $post->ID;
		$subtitle  = (string) get_post_meta( $id, ProductExtras::META_SUBTITLE, true );
		$short     = (string) get_post_meta( $id, ProductExtras::META_SHORT_TITLE, true );
		$specs     = ProductExtras::decode_specs( (string) get_post_meta( $id, ProductExtras::META_SPECS, true ) );
		$faq       = ProductExtras::decode_faq( (string) get_post_meta( $id, ProductExtras::META_FAQ, true ) );
		$guarantee = (string) get_post_meta( $id, ProductExtras::META_GUARANTEE, true );
		$views     = (int) get_post_meta( $id, ProductExtras::META_VIEWS, true );
		$fallback  = TitleCleaner::short( (string) $post->post_title );
		$field     = self::FIELD;

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="gtm-extras gtm-admin">
			<div class="gtm-extras__field">
				<label class="gtm-field__label" for="gtm-extras-short-title"><?php esc_html_e( 'Short title', 'gadgetim-core' ); ?></label>
				<input type="text" class="widefat gtm-input" id="gtm-extras-short-title" name="<?php echo esc_attr( $field ); ?>[short_title]" value="<?php echo esc_attr( $short ); ?>" placeholder="<?php echo esc_attr( $fallback ); ?>">
				<p class="gtm-extras__hint">
					<?php
					/* translators: %s: automatically derived short title */
					echo esc_html( sprintf( __( 'Shown on cards and as the H1. Leave empty to use the automatic version: “%s”.', 'gadgetim-core' ), $fallback ) );
					?>
				</p>
			</div>

			<div class="gtm-extras__field">
				<label class="gtm-field__label" for="gtm-extras-subtitle"><?php esc_html_e( 'Subtitle', 'gadgetim-core' ); ?></label>
				<input type="text" class="widefat gtm-input" id="gtm-extras-subtitle" name="<?php echo esc_attr( $field ); ?>[subtitle]" value="<?php echo esc_attr( $subtitle ); ?>">
				<p class="gtm-extras__hint"><?php esc_html_e( 'One sentence under the title. Subtitles shorter than 12 characters or all-caps model codes are hidden on the storefront.', 'gadgetim-core' ); ?></p>
			</div>

			<div class="gtm-extras__field">
				<span class="gtm-field__label"><?php esc_html_e( 'Specifications', 'gadgetim-core' ); ?></span>
				<?php self::specs_repeater( $specs ); ?>
				<p class="gtm-extras__hint"><?php esc_html_e( 'Label and value per row; the storefront renders them as the spec table. The importer fills this from the old descriptions.', 'gadgetim-core' ); ?></p>
			</div>

			<div class="gtm-extras__field">
				<span class="gtm-field__label"><?php esc_html_e( 'Frequently asked questions', 'gadgetim-core' ); ?></span>
				<?php self::faq_repeater( $faq ); ?>
			</div>

			<div class="gtm-extras__field">
				<label class="gtm-field__label" for="gtm-extras-guarantee"><?php esc_html_e( 'Guarantee text override', 'gadgetim-core' ); ?></label>
				<textarea class="widefat gtm-textarea" id="gtm-extras-guarantee" name="<?php echo esc_attr( $field ); ?>[guarantee]" rows="3"><?php echo esc_textarea( $guarantee ); ?></textarea>
				<p class="gtm-extras__hint"><?php esc_html_e( 'Leave empty to show the default guarantee text from Gadgetim → Settings → Trust & policies.', 'gadgetim-core' ); ?></p>
			</div>

			<div class="gtm-extras__field">
				<label class="gtm-field__label" for="gtm-extras-views"><?php esc_html_e( 'View counter', 'gadgetim-core' ); ?></label>
				<input type="number" class="gtm-input gtm-input--number" id="gtm-extras-views" name="<?php echo esc_attr( $field ); ?>[views]" value="<?php echo esc_attr( (string) $views ); ?>" min="0" step="1">
				<p class="gtm-extras__hint"><?php esc_html_e( 'Sampled views used as the tiebreaker of the “Popular” sort. Set to 0 to reset.', 'gadgetim-core' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * woocommerce_admin_process_product_object: writes the fields onto the product before its save.
	 */
	public static function save( WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified just below.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
			return;
		}
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$raw = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field below.

		self::set_or_delete( $product, ProductExtras::META_SHORT_TITLE, ProductExtras::sanitize_text( $raw['short_title'] ?? '' ) );
		self::set_or_delete( $product, ProductExtras::META_SUBTITLE, ProductExtras::sanitize_text( $raw['subtitle'] ?? '' ) );
		self::set_or_delete( $product, ProductExtras::META_SPECS, ProductExtras::sanitize_specs( $raw['specs'] ?? [] ) );
		self::set_or_delete( $product, ProductExtras::META_FAQ, ProductExtras::sanitize_faq( $raw['faq'] ?? [] ) );
		self::set_or_delete( $product, ProductExtras::META_GUARANTEE, ProductExtras::sanitize_guarantee( $raw['guarantee'] ?? '' ) );

		if ( isset( $raw['views'] ) ) {
			$product->update_meta_data( ProductExtras::META_VIEWS, ProductExtras::sanitize_views( $raw['views'] ) );
		}
	}

	/**
	 * Sets a meta value or deletes the key when the value is empty.
	 */
	private static function set_or_delete( WC_Product $product, string $key, string $value ): void {
		if ( '' === trim( $value ) ) {
			$product->delete_meta_data( $key );
			return;
		}
		$product->update_meta_data( $key, $value );
	}

	/**
	 * Spec rows repeater (label + value inputs).
	 *
	 * @param array<int,array{0:string,1:string}> $rows Rows.
	 */
	private static function specs_repeater( array $rows ): void {
		$field = self::FIELD;
		$row   = static function ( string $label, string $value ) use ( $field ): void {
			?>
			<li class="gtm-repeater__row">
				<span class="gtm-repeater__handle" aria-hidden="true">&#8942;</span>
				<input type="text" class="gtm-input gtm-input--label" name="<?php echo esc_attr( $field ); ?>[specs][label][]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Label', 'gadgetim-core' ); ?>">
				<input type="text" class="gtm-input" name="<?php echo esc_attr( $field ); ?>[specs][value][]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'Value', 'gadgetim-core' ); ?>">
				<?php self::row_buttons(); ?>
			</li>
			<?php
		};
		?>
		<div class="gtm-repeater" data-gtm-repeater id="gtm-extras-specs">
			<ol class="gtm-repeater__list">
				<?php
				foreach ( $rows as $pair ) {
					$row( (string) $pair[0], (string) $pair[1] );
				}
				?>
			</ol>
			<template class="gtm-repeater__template"><?php $row( '', '' ); ?></template>
			<button type="button" class="button gtm-repeater__add" data-gtm-action="add">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add row', 'gadgetim-core' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * FAQ repeater (question input + answer textarea).
	 *
	 * @param array<int,array{q:string,a:string}> $rows Rows.
	 */
	private static function faq_repeater( array $rows ): void {
		$field = self::FIELD;
		$row   = static function ( string $q, string $a ) use ( $field ): void {
			?>
			<li class="gtm-repeater__row">
				<span class="gtm-repeater__handle" aria-hidden="true">&#8942;</span>
				<input type="text" class="gtm-input gtm-input--label" name="<?php echo esc_attr( $field ); ?>[faq][q][]" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Question', 'gadgetim-core' ); ?>">
				<textarea class="gtm-textarea" name="<?php echo esc_attr( $field ); ?>[faq][a][]" rows="2" placeholder="<?php esc_attr_e( 'Answer', 'gadgetim-core' ); ?>"><?php echo esc_textarea( $a ); ?></textarea>
				<?php self::row_buttons(); ?>
			</li>
			<?php
		};
		?>
		<div class="gtm-repeater" data-gtm-repeater id="gtm-extras-faq">
			<ol class="gtm-repeater__list">
				<?php
				foreach ( $rows as $pair ) {
					$row( (string) $pair['q'], (string) $pair['a'] );
				}
				?>
			</ol>
			<template class="gtm-repeater__template"><?php $row( '', '' ); ?></template>
			<button type="button" class="button gtm-repeater__add" data-gtm-action="add">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add question', 'gadgetim-core' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Move/remove buttons shared by the repeaters (handled by admin.js).
	 */
	private static function row_buttons(): void {
		?>
		<span class="gtm-repeater__buttons">
			<button type="button" class="button-link gtm-repeater__btn" data-gtm-action="up" aria-label="<?php esc_attr_e( 'Move up', 'gadgetim-core' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			<button type="button" class="button-link gtm-repeater__btn" data-gtm-action="down" aria-label="<?php esc_attr_e( 'Move down', 'gadgetim-core' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
			<button type="button" class="button-link button-link-delete gtm-repeater__btn" data-gtm-action="remove" aria-label="<?php esc_attr_e( 'Remove', 'gadgetim-core' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</span>
		<?php
	}
}
