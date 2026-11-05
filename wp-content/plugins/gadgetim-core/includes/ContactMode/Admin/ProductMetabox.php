<?php
/**
 * Product side metabox for the _gtm_contact_only override (inherit / yes / no).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\ContactMode\Admin;

use Gadgetim\Core\ContactMode\ContactMode;
use WP_Post;

/**
 * Metabox + save handler + register_post_meta (architecture §5.10: whitelist sanitiser).
 */
final class ProductMetabox {

	public const ID = 'gadgetim-contact-mode';

	public const FIELD = 'gadgetim_contact_only';

	public const NONCE = 'gadgetim_contact_only_nonce';

	/**
	 * init: meta registration (no REST exposure; the wc/v3 meta_data path still runs the sanitizer).
	 */
	public static function register_meta(): void {
		register_post_meta(
			'product',
			ContactMode::META_OVERRIDE,
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => [ ContactMode::class, 'sanitize_override' ],
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_products' ),
			]
		);
	}

	/**
	 * add_meta_boxes.
	 *
	 * @param string       $post_type Post type.
	 * @param WP_Post|null $post      Post.
	 */
	public static function add( string $post_type, $post = null ): void {
		if ( 'product' !== $post_type ) {
			return;
		}
		add_meta_box(
			self::ID,
			__( 'Contact instead of price', 'gadgetim-core' ),
			[ self::class, 'render' ],
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Metabox content.
	 */
	public static function render( WP_Post $post ): void {
		$current = ContactMode::override( (int) $post->ID );
		$global  = ContactMode::enabled()
			? __( 'on', 'gadgetim-core' )
			: __( 'off', 'gadgetim-core' );

		$options = [
			/* translators: %s: "on" or "off" */
			'inherit' => sprintf( __( 'Follow the global setting (currently %s)', 'gadgetim-core' ), $global ),
			'yes'     => __( 'Contact only: hide the price and disable purchasing', 'gadgetim-core' ),
			'no'      => __( 'Always sell with a price', 'gadgetim-core' ),
		];

		wp_nonce_field( self::NONCE . '_' . $post->ID, self::NONCE );
		echo '<p><label class="screen-reader-text" for="' . esc_attr( self::FIELD ) . '">' . esc_html__( 'Contact instead of price', 'gadgetim-core' ) . '</label>';
		echo '<select id="' . esc_attr( self::FIELD ) . '" name="' . esc_attr( self::FIELD ) . '" class="widefat">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="description">' . esc_html__( 'In contact mode the product shows the call, WhatsApp and Telegram buttons instead of a buy button and cannot be added to the cart.', 'gadgetim-core' ) . '</p>';
	}

	/**
	 * save_post_product.
	 *
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Whether this is an update.
	 */
	public static function save( int $post_id, $post = null, bool $update = false ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE ], $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ), self::NONCE . '_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = ContactMode::sanitize_override( sanitize_key( wp_unslash( (string) $_POST[ self::FIELD ] ) ) );
		if ( '' === $value ) {
			delete_post_meta( $post_id, ContactMode::META_OVERRIDE );
		} else {
			update_post_meta( $post_id, ContactMode::META_OVERRIDE, $value );
		}
		// Support\Cache purges the product (and its archives) on save_post priority 20.
	}
}
