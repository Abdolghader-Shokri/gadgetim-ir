<?php
/**
 * Active filters as removable chips (real links: each removes one value) and «حذف همه».
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_chips = gadgetim_archive_active_chips();
if ( ! $gadgetim_chips ) {
	return;
}
$gadgetim_icons = [
	'instock' => 'box',
	'sale'    => 'percent',
	'brand'   => 'crown',
	'price'   => 'tag',
];
?>
<div class="actives" role="group" aria-label="<?php esc_attr_e( 'Active filters', 'gadgetim' ); ?>">
	<ul class="actives__list">
		<?php foreach ( $gadgetim_chips as $gadgetim_i => $gadgetim_chip ) : ?>
			<li style="--i:<?php echo esc_attr( (string) $gadgetim_i ); ?>">
				<a class="actv actv--<?php echo esc_attr( $gadgetim_chip['type'] ); ?>" href="<?php echo esc_url( $gadgetim_chip['url'] ); ?>" data-arch-link>
					<?php gadgetim_the_icon( $gadgetim_icons[ $gadgetim_chip['type'] ] ?? 'filter', 'actv__ic' ); ?>
					<span dir="auto"><?php echo esc_html( $gadgetim_chip['label'] ); ?></span>
					<span class="actv__x" aria-hidden="true"><?php gadgetim_the_icon( 'close' ); ?></span>
					<span class="sr-only"><?php esc_html_e( '(remove this filter)', 'gadgetim' ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( count( $gadgetim_chips ) > 1 ) : ?>
		<a class="actives__clear" href="<?php echo esc_url( gadgetim_archive_url( [] ) ); ?>" data-arch-link><?php esc_html_e( 'Clear all', 'gadgetim' ); ?></a>
	<?php endif; ?>
</div>
