<?php
/**
 * Field: richtext (TinyMCE, teeny).
 *
 * @var array $args { name, id, value, field, path, lang, dir }
 * @package Gadgetim\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_rows = max( 3, (int) $args['field']['rows'] );
?>
<div class="gtm-richtext" <?php echo '' !== $args['dir'] ? 'dir="' . esc_attr( $args['dir'] ) . '"' : ''; ?>>
	<?php
	wp_editor(
		is_scalar( $args['value'] ) ? (string) $args['value'] : '',
		$args['id'],
		[
			'textarea_name' => $args['name'],
			'textarea_rows' => $gtm_rows,
			'editor_height' => 40 + $gtm_rows * 24,
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => [ 'buttons' => 'strong,em,link,ul,ol,li' ],
			'tinymce'       => [
				'toolbar1'                => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
				'directionality'          => 'ltr' === $args['dir'] ? 'ltr' : 'rtl',
				'wpautop'                 => true,
				'paste_as_text'           => true,
				'browser_spellcheck'      => true,
				'remove_linebreaks'       => false,
			],
		]
	);
	?>
</div>
