<?php
/**
 * Field: repeater of text lines (add / remove / reorder; no library).
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_items = is_array( $args['value'] ) ? array_values( array_filter( $args['value'], 'is_scalar' ) ) : [];
$gtm_name  = $args['name'] . '[]';
$gtm_dir   = $args['dir'];

$gtm_row = static function ( string $value ) use ( $gtm_name, $gtm_dir ): void {
	?>
	<li class="gtm-repeater__row">
		<span class="gtm-repeater__handle" aria-hidden="true">&#8942;</span>
		<input type="text" class="regular-text gtm-input" name="<?php echo esc_attr( $gtm_name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo '' !== $gtm_dir ? 'dir="' . esc_attr( $gtm_dir ) . '"' : ''; ?>>
		<span class="gtm-repeater__buttons">
			<button type="button" class="button-link gtm-repeater__btn" data-gtm-action="up" aria-label="<?php esc_attr_e( 'Move up', 'gadgetim-core' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			<button type="button" class="button-link gtm-repeater__btn" data-gtm-action="down" aria-label="<?php esc_attr_e( 'Move down', 'gadgetim-core' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
			<button type="button" class="button-link button-link-delete gtm-repeater__btn" data-gtm-action="remove" aria-label="<?php esc_attr_e( 'Remove', 'gadgetim-core' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</span>
	</li>
	<?php
};
?>
<div class="gtm-repeater" data-gtm-repeater id="<?php echo esc_attr( $args['id'] ); ?>">
	<ol class="gtm-repeater__list">
		<?php
		foreach ( $gtm_items as $gtm_item ) {
			$gtm_row( (string) $gtm_item );
		}
		?>
	</ol>
	<template class="gtm-repeater__template"><?php $gtm_row( '' ); ?></template>
	<button type="button" class="button gtm-repeater__add" data-gtm-action="add">
		<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
		<?php esc_html_e( 'Add line', 'gadgetim-core' ); ?>
	</button>
</div>
