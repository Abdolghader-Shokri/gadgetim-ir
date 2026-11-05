<?php
/**
 * Settings page wrapper: title, notices, tab navigation, tab body.
 *
 * @var array $args { tabs: array, current: string, tab: array }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_tabs    = $args['tabs'];
$gtm_current = $args['current'];
$gtm_tab     = $args['tab'];
?>
<div class="wrap gtm-admin">
	<h1 class="gtm-admin__title">
		<span class="dashicons dashicons-store" aria-hidden="true"></span>
		<?php esc_html_e( 'Gadgetim', 'gadgetim-core' ); ?>
		<span class="gtm-admin__version">v<?php echo esc_html( GADGETIM_CORE_VERSION ); ?></span>
	</h1>

	<?php settings_errors( Settings::GROUP ); ?>

	<nav class="nav-tab-wrapper gtm-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'gadgetim-core' ); ?>">
		<?php foreach ( $gtm_tabs as $gtm_id => $gtm_def ) : ?>
			<?php
			$gtm_is_link = '' !== $gtm_def['url'];
			$gtm_href    = $gtm_is_link ? $gtm_def['url'] : SettingsPage::url( (string) $gtm_id );
			$gtm_active  = ! $gtm_is_link && $gtm_id === $gtm_current;
			?>
			<a class="nav-tab<?php echo $gtm_active ? ' nav-tab-active' : ''; ?><?php echo $gtm_is_link ? ' gtm-tabs__link' : ''; ?>"
				href="<?php echo esc_url( $gtm_href ); ?>"
				<?php echo $gtm_active ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $gtm_def['label'] ); ?>
				<?php if ( $gtm_is_link ) : ?>
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="gtm-tab" id="gtm-tab-<?php echo esc_attr( $gtm_current ); ?>">
		<?php if ( '' !== $gtm_tab['description'] ) : ?>
			<p class="gtm-tab__description"><?php echo wp_kses_post( $gtm_tab['description'] ); ?></p>
		<?php endif; ?>
		<?php SettingsPage::render_tab_content( $gtm_current, $gtm_tab ); ?>
	</div>
</div>
