<?php
/**
 * Tools tab: last result + one card per registered tool.
 *
 * @var array $args { tab: array, tools: array, result: ?array }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_tools  = $args['tools'];
$gtm_result = $args['result'];
?>
<div class="gtm-tools">
	<?php if ( is_array( $gtm_result ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $gtm_result['type'] ); ?> inline gtm-tools__result">
			<p><?php echo wp_kses_post( $gtm_result['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $gtm_tools ) : ?>
		<p class="description"><?php esc_html_e( 'No tools are registered.', 'gadgetim-core' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $gtm_tools as $gtm_tool ) : ?>
		<?php
		if ( ! current_user_can( $gtm_tool['capability'] ) ) {
			continue;
		}
		?>
		<div class="gtm-tool<?php echo $gtm_tool['danger'] ? ' gtm-tool--danger' : ''; ?>" id="gtm-tool-<?php echo esc_attr( $gtm_tool['id'] ); ?>">
			<div class="gtm-tool__text">
				<h3 class="gtm-tool__title"><?php echo esc_html( $gtm_tool['label'] ); ?></h3>
				<?php if ( '' !== $gtm_tool['description'] ) : ?>
					<p class="description"><?php echo wp_kses_post( $gtm_tool['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="gtm-tool__action">
				<?php
				if ( is_callable( $gtm_tool['render'] ) ) {
					call_user_func( $gtm_tool['render'], $gtm_tool );
				} else {
					Tools::form( $gtm_tool );
				}
				?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
