<?php
/**
 * Field: text.
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<input type="text"
	class="regular-text gtm-input"
	id="<?php echo esc_attr( $args['id'] ); ?>"
	name="<?php echo esc_attr( $args['name'] ); ?>"
	value="<?php echo esc_attr( is_scalar( $args['value'] ) ? (string) $args['value'] : '' ); ?>"
	<?php echo '' !== $args['field']['placeholder'] ? 'placeholder="' . esc_attr( $args['field']['placeholder'] ) . '"' : ''; ?>
	<?php echo Fields::attrs( $args['field'], '' !== $args['dir'] ? [ 'dir' => $args['dir'] ] : [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>>
