<?php
/**
 * Below the grid: «نمایش ۱–۲۴ از ۸۴ کالا» with a progress bar, «نمایش بیشتر» (a real link to the
 * next page; filters.js appends that page's grid instead) and the numbered pagination.
 * The footer always stays reachable: there is no infinite scroll.
 *
 * Args: page, pages, first, last, total.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_page  = (int) ( $args['page'] ?? 1 );
$gadgetim_pages = (int) ( $args['pages'] ?? 1 );
$gadgetim_first = (int) ( $args['first'] ?? 1 );
$gadgetim_last  = (int) ( $args['last'] ?? 0 );
$gadgetim_total = (int) ( $args['total'] ?? 0 );
if ( $gadgetim_total <= 0 ) {
	return;
}
$gadgetim_share = $gadgetim_total > 0 ? min( 1, $gadgetim_last / $gadgetim_total ) : 1;
?>
<div class="more" data-more>
	<p class="more__t" data-more-text>
		<?php
		/* translators: 1: first product shown, 2: last product shown, 3: total products */
		echo esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s products', 'gadgetim' ), gadgetim_number( $gadgetim_first ), gadgetim_number( $gadgetim_last ), gadgetim_number( $gadgetim_total ) ) );
		?>
	</p>
	<span class="more__bar" aria-hidden="true"><i style="--p:<?php echo esc_attr( (string) round( $gadgetim_share, 3 ) ); ?>"></i></span>
	<?php if ( $gadgetim_page < $gadgetim_pages ) : ?>
		<a class="btn btn--outline more__btn" href="<?php echo esc_url( (string) get_pagenum_link( $gadgetim_page + 1 ) ); ?>" data-load-more data-first="<?php echo esc_attr( (string) $gadgetim_first ); ?>">
			<span><?php esc_html_e( 'Show more products', 'gadgetim' ); ?></span>
			<?php gadgetim_the_icon( 'chev-d' ); ?>
		</a>
	<?php endif; ?>
</div>
<?php
gadgetim_pagination(
	[
		'total'   => $gadgetim_pages,
		'current' => $gadgetim_page,
		'class'   => 'arch__pager',
	]
);
