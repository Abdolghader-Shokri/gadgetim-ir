<?php
/**
 * The filter sheet (native <dialog>, opened by drawer.js): a bottom sheet on phones and a
 * start-side drawer on tablets. filters.js moves the filter form (#filters) into the slot before
 * it opens and back into the sidebar on wide screens. Also the floating «فیلتر و مرتب‌سازی» pill
 * shown on phones once the toolbar has scrolled away, and the polite live region for results.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_state = gadgetim_archive_state();
$gadgetim_n     = (int) $gadgetim_state['active_count'];
?>
<dialog class="fsheet" id="filter-sheet" aria-labelledby="filters-title" data-drawer>
	<div class="fsheet__slot" data-filters-slot></div>
</dialog>
<div class="fpill" data-fpill hidden>
	<button class="fpill__btn" type="button" data-filters-open aria-controls="filter-sheet" aria-expanded="false">
		<?php gadgetim_the_icon( 'filter' ); ?>
		<span><?php esc_html_e( 'Filter and sort', 'gadgetim' ); ?></span>
		<b class="fpill__n<?php echo $gadgetim_n ? '' : ' is-empty'; ?>" data-filters-badge aria-hidden="true"><?php echo esc_html( $gadgetim_n ? gadgetim_number( $gadgetim_n ) : '' ); ?></b>
	</button>
</div>
<p class="sr-only" id="arch-live" aria-live="polite" aria-atomic="true" data-arch-live></p>
