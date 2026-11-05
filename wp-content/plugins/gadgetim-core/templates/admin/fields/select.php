<?php
/**
 * Field: select.
 *
 * @var array $args { name, id, value, field, path, lang, dir, options }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_current = is_scalar( $args['value'] ) ? (string) $args['value'] : '';
?>
<select class="gtm-select"
	id="<?php echo esc_attr( $args['id'] ); ?>"
	name="<?php echo esc_attr( $args['name'] ); ?>"
	<?php echo Fields::attrs( $args['field'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>>
	<?php foreach ( $args['options'] as $gtm_value => $gtm_label ) : ?>
		<option value="<?php echo esc_attr( (string) $gtm_value ); ?>" <?php selected( $gtm_current, (string) $gtm_value ); ?>><?php echo esc_html( (string) $gtm_label ); ?></option>
	<?php endforeach; ?>
</select>
