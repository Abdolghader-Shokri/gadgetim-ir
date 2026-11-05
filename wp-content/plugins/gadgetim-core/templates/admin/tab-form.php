<?php
/**
 * Schema-driven settings form for one tab (saved through options.php).
 *
 * @var array $args { tab_id: string, tab: array, languages: array }
 * @package Gadgetim\Core
 */

use Gadgetim\Core\Settings\Admin\Fields;
use Gadgetim\Core\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_tab_id = $args['tab_id'];
$gtm_tab    = $args['tab'];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="gtm-form" novalidate>
	<?php settings_fields( Settings::GROUP ); ?>
	<input type="hidden" name="<?php echo esc_attr( Settings::TAB_FIELD ); ?>" value="<?php echo esc_attr( $gtm_tab_id ); ?>">

	<?php if ( ! $gtm_tab['sections'] ) : ?>
		<p class="description"><?php esc_html_e( 'This tab has no settings yet.', 'gadgetim-core' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $gtm_tab['sections'] as $gtm_section_id => $gtm_section ) : ?>
		<?php
		$gtm_fields = array_filter(
			$gtm_section['fields'],
			static fn( array $field ): bool => '' === $field['capability'] || current_user_can( $field['capability'] )
		);
		if ( ! $gtm_fields ) {
			continue;
		}
		?>
		<section class="gtm-section" id="gtm-section-<?php echo esc_attr( $gtm_section_id ); ?>">
			<?php if ( '' !== $gtm_section['label'] ) : ?>
				<h2 class="gtm-section__title"><?php echo esc_html( $gtm_section['label'] ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $gtm_section['description'] ) : ?>
				<p class="description gtm-section__description"><?php echo wp_kses_post( $gtm_section['description'] ); ?></p>
			<?php endif; ?>

			<table class="form-table gtm-table" role="presentation">
				<tbody>
				<?php foreach ( $gtm_fields as $gtm_path => $gtm_field ) : ?>
					<tr class="gtm-field gtm-field--<?php echo esc_attr( $gtm_field['type'] ); ?><?php echo $gtm_field['i18n'] ? ' gtm-field--i18n' : ''; ?>">
						<th scope="row">
							<?php if ( in_array( $gtm_field['type'], [ 'toggle', 'repeater', 'image', 'richtext' ], true ) || $gtm_field['i18n'] ) : ?>
								<span class="gtm-field__label"><?php echo esc_html( $gtm_field['label'] ); ?></span>
							<?php else : ?>
								<label for="<?php echo esc_attr( Fields::id( $gtm_path ) ); ?>"><?php echo esc_html( $gtm_field['label'] ); ?></label>
							<?php endif; ?>
						</th>
						<td>
							<?php Fields::render( $gtm_path, $gtm_field, Settings::get( $gtm_path ) ); ?>
							<?php if ( '' !== $gtm_field['description'] ) : ?>
								<p class="description"><?php echo wp_kses_post( $gtm_field['description'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endforeach; ?>

	<?php if ( $gtm_tab['sections'] ) : ?>
		<?php submit_button( __( 'Save changes', 'gadgetim-core' ) ); ?>
	<?php endif; ?>
</form>
