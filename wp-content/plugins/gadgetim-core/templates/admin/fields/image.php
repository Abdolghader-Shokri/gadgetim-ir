<?php
/**
 * Field: image (attachment id + media picker).
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_attachment = absint( is_scalar( $args['value'] ) ? $args['value'] : 0 );
$gtm_preview    = $gtm_attachment ? wp_get_attachment_image( $gtm_attachment, 'medium', false, [ 'alt' => '' ] ) : '';
?>
<div class="gtm-image<?php echo '' !== $gtm_preview ? ' has-image' : ''; ?>" data-gtm-image>
	<input type="hidden"
		id="<?php echo esc_attr( $args['id'] ); ?>"
		name="<?php echo esc_attr( $args['name'] ); ?>"
		value="<?php echo esc_attr( $gtm_attachment ? (string) $gtm_attachment : '' ); ?>">
	<div class="gtm-image__preview" aria-live="polite"><?php echo $gtm_preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?></div>
	<div class="gtm-image__buttons">
		<button type="button" class="button gtm-image__select" data-gtm-action="select"><?php esc_html_e( 'Select image', 'gadgetim-core' ); ?></button>
		<button type="button" class="button-link button-link-delete gtm-image__remove" data-gtm-action="remove"><?php esc_html_e( 'Remove', 'gadgetim-core' ); ?></button>
	</div>
</div>
