<?php
/**
 * Field: textarea.
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<textarea class="large-text gtm-textarea"
	id="<?php echo esc_attr( $args['id'] ); ?>"
	name="<?php echo esc_attr( $args['name'] ); ?>"
	rows="<?php echo esc_attr( (string) max( 2, (int) $args['field']['rows'] ) ); ?>"
	<?php echo '' !== $args['field']['placeholder'] ? 'placeholder="' . esc_attr( $args['field']['placeholder'] ) . '"' : ''; ?>
	<?php echo Fields::attrs( $args['field'], '' !== $args['dir'] ? [ 'dir' => $args['dir'] ] : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>><?php echo esc_textarea( is_scalar( $args['value'] ) ? (string) $args['value'] : '' ); ?></textarea>
