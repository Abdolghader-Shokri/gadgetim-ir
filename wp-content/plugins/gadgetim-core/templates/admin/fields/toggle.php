<?php
/**
 * Field: toggle (checkbox styled as a switch; absent from POST = false).
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_on = rest_sanitize_boolean( is_scalar( $args['value'] ) ? $args['value'] : false );
?>
<label class="gtm-toggle" for="<?php echo esc_attr( $args['id'] ); ?>">
	<input type="checkbox"
		class="gtm-toggle__input"
		id="<?php echo esc_attr( $args['id'] ); ?>"
		name="<?php echo esc_attr( $args['name'] ); ?>"
		value="1"
		role="switch"
		<?php checked( $gtm_on ); ?>>
	<span class="gtm-toggle__track" aria-hidden="true"><span class="gtm-toggle__thumb"></span></span>
	<span class="gtm-toggle__text"><?php echo esc_html( $args['field']['label'] ); ?></span>
</label>
