<?php
/**
 * One persistent admin notice with its dismiss form.
 *
 * @var array $args { id: string, notice: array }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Notices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_id     = $args['id'];
$gtm_notice = $args['notice'];
?>
<div class="notice notice-<?php echo esc_attr( $gtm_notice['type'] ); ?> gtm-notice" id="gtm-notice-<?php echo esc_attr( $gtm_id ); ?>">
	<p class="gtm-notice__text">
		<strong><?php esc_html_e( 'Gadgetim', 'gadgetim-core' ); ?>:</strong>
		<?php echo wp_kses_post( $gtm_notice['message'] ); ?>
		<?php if ( ! empty( $gtm_notice['link']['url'] ) ) : ?>
			<a href="<?php echo esc_url( $gtm_notice['link']['url'] ); ?>" class="gtm-notice__link"><?php echo esc_html( $gtm_notice['link']['label'] ); ?></a>
		<?php endif; ?>
	</p>
	<?php if ( $gtm_notice['dismissible'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gtm-notice__dismiss">
			<input type="hidden" name="action" value="<?php echo esc_attr( Notices::DISMISS_ACTION ); ?>">
			<input type="hidden" name="notice" value="<?php echo esc_attr( $gtm_id ); ?>">
			<?php wp_nonce_field( Notices::DISMISS_ACTION . '_' . $gtm_id ); ?>
			<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss', 'gadgetim-core' ); ?></button>
		</form>
	<?php endif; ?>
</div>
