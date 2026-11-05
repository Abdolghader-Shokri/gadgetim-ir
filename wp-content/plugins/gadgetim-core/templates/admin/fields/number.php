<?php
/**
 * Field: number.
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_field = $args['field'];
$gtm_extra = [ 'dir' => 'ltr' ];
foreach ( [ 'min', 'max', 'step' ] as $gtm_attr ) {
	if ( is_numeric( $gtm_field[ $gtm_attr ] ?? null ) ) {
		$gtm_extra[ $gtm_attr ] = (string) $gtm_field[ $gtm_attr ];
	}
}
?>
<input type="number"
	class="small-text gtm-input gtm-input--number"
	id="<?php echo esc_attr( $args['id'] ); ?>"
	name="<?php echo esc_attr( $args['name'] ); ?>"
	value="<?php echo esc_attr( is_numeric( $args['value'] ) ? (string) $args['value'] : '' ); ?>"
	inputmode="numeric"
	<?php echo '' !== $gtm_field['placeholder'] ? 'placeholder="' . esc_attr( $gtm_field['placeholder'] ) . '"' : ''; ?>
	<?php echo Fields::attrs( $gtm_field, $gtm_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>>
