<?php
/**
 * The nonce-protected form that runs one tool through admin-post.php.
 *
 * @var array $args { tool: array, button: string }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_tool   = $args['tool'];
$gtm_button = $args['button'];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gtm-tool__form"
	<?php echo '' !== $gtm_tool['confirm'] ? 'data-gtm-confirm="' . esc_attr( $gtm_tool['confirm'] ) . '"' : ''; ?>>
	<input type="hidden" name="action" value="<?php echo esc_attr( Tools::ACTION ); ?>">
	<input type="hidden" name="tool" value="<?php echo esc_attr( $gtm_tool['id'] ); ?>">
	<?php wp_nonce_field( Tools::ACTION . '_' . $gtm_tool['id'] ); ?>
	<button type="submit" class="button <?php echo $gtm_tool['danger'] ? 'button-link-delete gtm-button--danger' : 'button-secondary'; ?>">
		<?php echo esc_html( $gtm_button ); ?>
	</button>
</form>
