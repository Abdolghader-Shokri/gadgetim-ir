<?php
/**
 * Brand logo field on the product_brand add/edit screens (term meta _gtm_logo_id).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog\Admin;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\Plugin;
use WP_Term;

/**
 * Reuses the plugin's admin image picker (assets/admin/admin.js, data-gtm-image).
 */
final class BrandFields {

	public const NONCE_ACTION = 'gadgetim_brand_fields';

	public const NONCE_FIELD = 'gadgetim_brand_fields_nonce';

	public const FIELD = 'gtm_brand_logo_id';

	/**
	 * Hooks on the taxonomy screens.
	 */
	public static function register(): void {
		$tax = Brands::TAXONOMY;
		add_action( $tax . '_add_form_fields', [ self::class, 'add_form_field' ] );
		add_action( $tax . '_edit_form_fields', [ self::class, 'edit_form_field' ], 10, 1 );
		add_action( 'created_' . $tax, [ self::class, 'save' ], 10, 1 );
		add_action( 'edited_' . $tax, [ self::class, 'save' ], 10, 1 );
		add_filter( 'manage_edit-' . $tax . '_columns', [ self::class, 'columns' ] );
		add_filter( 'manage_' . $tax . '_custom_column', [ self::class, 'column' ], 10, 3 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * admin_enqueue_scripts: media picker + plugin admin assets on the brand screens only.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Brands::TAXONOMY !== $screen->taxonomy ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'gadgetim-admin', Plugin::url( 'assets/admin/admin.css' ), [], GADGETIM_CORE_VERSION );
		wp_enqueue_script( 'gadgetim-admin', Plugin::url( 'assets/admin/admin.js' ), [ 'jquery' ], GADGETIM_CORE_VERSION, true );
		wp_localize_script(
			'gadgetim-admin',
			'gadgetimAdmin',
			[
				'i18n' => [
					'selectImage' => __( 'Select image', 'gadgetim-core' ),
					'useImage'    => __( 'Use this image', 'gadgetim-core' ),
					'remove'      => __( 'Remove', 'gadgetim-core' ),
					'confirm'     => __( 'Are you sure?', 'gadgetim-core' ),
				],
			]
		);
	}

	/**
	 * Add-term form.
	 */
	public static function add_form_field(): void {
		?>
		<div class="form-field term-gtm-logo-wrap">
			<label for="<?php echo esc_attr( self::FIELD ); ?>"><?php esc_html_e( 'Brand logo', 'gadgetim-core' ); ?></label>
			<?php self::picker( 0 ); ?>
			<p class="description"><?php esc_html_e( 'Monochrome SVG or PNG. Until a logo is set the theme shows a typographic sticker.', 'gadgetim-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit-term form.
	 */
	public static function edit_form_field( WP_Term $term ): void {
		?>
		<tr class="form-field term-gtm-logo-wrap">
			<th scope="row"><label for="<?php echo esc_attr( self::FIELD ); ?>"><?php esc_html_e( 'Brand logo', 'gadgetim-core' ); ?></label></th>
			<td>
				<?php self::picker( Brands::logo_id( $term ) ); ?>
				<p class="description"><?php esc_html_e( 'Monochrome SVG or PNG. Until a logo is set the theme shows a typographic sticker.', 'gadgetim-core' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * created_/edited_product_brand: saves the logo (nonce + capability checked).
	 */
	public static function save( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified just below.
		if ( ! isset( $_POST[ self::NONCE_FIELD ], $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
			return;
		}
		$taxonomy = get_taxonomy( Brands::TAXONOMY );
		$cap      = $taxonomy && isset( $taxonomy->cap->edit_terms ) ? (string) $taxonomy->cap->edit_terms : 'manage_product_terms';
		if ( ! current_user_can( $cap ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		Brands::set_logo( $term_id, absint( wp_unslash( (string) $_POST[ self::FIELD ] ) ) );
	}

	/**
	 * Adds the logo column to the brands list.
	 */
	public static function columns( array $columns ): array {
		$out = [];
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$out['gtm_logo'] = __( 'Logo', 'gadgetim-core' );
			}
			$out[ $key ] = $label;
		}
		return $out;
	}

	/**
	 * Renders the logo column.
	 */
	public static function column( string $content, string $column, int $term_id ): string {
		if ( 'gtm_logo' !== $column ) {
			return $content;
		}
		$logo = Brands::logo( $term_id, 'thumbnail', [ 'style' => 'max-width:48px;height:auto' ] );
		return '' !== $logo ? $logo : '<span aria-hidden="true">—</span>';
	}

	/**
	 * The picker control (same markup as the settings image field).
	 */
	private static function picker( int $attachment_id ): void {
		$preview = $attachment_id ? wp_get_attachment_image( $attachment_id, 'medium', false, [ 'alt' => '' ] ) : '';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="gtm-image<?php echo '' !== $preview ? ' has-image' : ''; ?>" data-gtm-image>
			<input type="hidden" id="<?php echo esc_attr( self::FIELD ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( $attachment_id ? (string) $attachment_id : '' ); ?>">
			<div class="gtm-image__preview" aria-live="polite"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?></div>
			<div class="gtm-image__buttons">
				<button type="button" class="button gtm-image__select" data-gtm-action="select"><?php esc_html_e( 'Select image', 'gadgetim-core' ); ?></button>
				<button type="button" class="button-link button-link-delete gtm-image__remove" data-gtm-action="remove"><?php esc_html_e( 'Remove', 'gadgetim-core' ); ?></button>
			</div>
		</div>
		<?php
	}
}
