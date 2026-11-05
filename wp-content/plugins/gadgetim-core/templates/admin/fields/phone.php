<?php
/**
 * Field: phone (stored normalised by Support\Phone::normalize; preview of the E.164 form).
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;
use Gadgetim\Core\Support\Phone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_value = is_scalar( $args['value'] ) ? (string) $args['value'] : '';
$gtm_e164  = '' !== $gtm_value ? Phone::to_e164_iran( $gtm_value ) : '';
?>
<input type="tel"
	class="regular-text gtm-input gtm-input--phone"
	id="<?php echo esc_attr( $args['id'] ); ?>"
	name="<?php echo esc_attr( $args['name'] ); ?>"
	value="<?php echo esc_attr( $gtm_value ); ?>"
	inputmode="tel"
	autocomplete="off"
	<?php echo '' !== $args['field']['placeholder'] ? 'placeholder="' . esc_attr( $args['field']['placeholder'] ) . '"' : ''; ?>
	<?php echo Fields::attrs( $args['field'], [ 'dir' => 'ltr' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>>
<?php if ( '' !== $gtm_value ) : ?>
	<span class="gtm-input__hint">
		<?php if ( '' !== $gtm_e164 ) : ?>
			<code dir="ltr"><?php echo esc_html( $gtm_e164 ); ?></code>
		<?php else : ?>
			<span class="gtm-input__warning"><?php esc_html_e( 'Not recognised as a phone number.', 'gadgetim-core' ); ?></span>
		<?php endif; ?>
	</span>
<?php endif; ?>
