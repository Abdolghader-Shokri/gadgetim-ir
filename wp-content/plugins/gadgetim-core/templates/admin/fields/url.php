<?php
/**
 * Field: url.
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<input type="url"
	class="regular-text code gtm-input"
	id="<?php echo esc_attr( $args['id'] ); ?>"
	name="<?php echo esc_attr( $args['name'] ); ?>"
	value="<?php echo esc_attr( is_scalar( $args['value'] ) ? (string) $args['value'] : '' ); ?>"
	<?php echo '' !== $args['field']['placeholder'] ? 'placeholder="' . esc_attr( $args['field']['placeholder'] ) . '"' : 'placeholder="https://"'; ?>
	<?php echo Fields::attrs( $args['field'], [ 'dir' => 'ltr' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>>
