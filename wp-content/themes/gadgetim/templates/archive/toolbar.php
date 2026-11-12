<?php
/**
 * Toolbar: the result count (the results heading), the filter button with its badge (phones and
 * tablets), sort chips (≥ 768 px; real links, kept out of the crawl by robots.txt) or a sort
 * select (phones; submits without JS), and the grid density switch (≥ 1100 px; 4 or 5 per
 * row, remembered on the device).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

global $wp_query;

$gadgetim_state = gadgetim_archive_state();
$gadgetim_count = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
$gadgetim_sorts = gadgetim_archive_sort_options();
$gadgetim_sort  = gadgetim_archive_orderby();
$gadgetim_f     = (array) $gadgetim_state['filters'];
$gadgetim_n     = (int) $gadgetim_state['active_count'];
$gadgetim_ready = gadgetim_archive_has_filters();

// Hidden inputs of the sort form: the active filters and the base URL's own parameters.
$gadgetim_keep = gadgetim_archive_filters_ready() ? Gadgetim\Core\Catalog\Filters::query_args( $gadgetim_f ) : [];
$gadgetim_qs   = (string) wp_parse_url( (string) $gadgetim_state['base_url'], PHP_URL_QUERY );
if ( '' !== $gadgetim_qs ) {
	$gadgetim_base_args = [];
	wp_parse_str( $gadgetim_qs, $gadgetim_base_args );
	$gadgetim_keep = array_merge( $gadgetim_base_args, $gadgetim_keep );
}
?>
<div class="tb<?php echo 0 === $gadgetim_count ? ' tb--empty' : ''; ?>" data-toolbar>
	<h2 class="tb__count" id="results-title">
		<?php
		/* translators: %s: number of products */
		echo sprintf( esc_html( _n( '%s product', '%s products', $gadgetim_count, 'gadgetim' ) ), '<b class="tb__n">' . esc_html( gadgetim_number( $gadgetim_count ) ) . '</b>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both parts escaped.
		?>
	</h2>

	<?php if ( $gadgetim_ready ) : ?>
		<button class="tb__filter" type="button" data-filters-open aria-controls="filter-sheet" aria-expanded="false">
			<?php gadgetim_the_icon( 'filter' ); ?>
			<span><?php esc_html_e( 'Filters', 'gadgetim' ); ?></span>
			<b class="tb__badge<?php echo $gadgetim_n ? '' : ' is-empty'; ?>" data-filters-badge aria-hidden="true"><?php echo esc_html( $gadgetim_n ? gadgetim_number( $gadgetim_n ) : '' ); ?></b>
			<?php if ( $gadgetim_n ) : ?>
				<span class="sr-only">
					<?php
					/* translators: %s: number of active filters */
					echo esc_html( sprintf( __( '%s active', 'gadgetim' ), gadgetim_number( $gadgetim_n ) ) );
					?>
				</span>
			<?php endif; ?>
		</button>
	<?php endif; ?>

	<?php if ( count( $gadgetim_sorts ) > 1 && $gadgetim_count > 1 ) : ?>
		<nav class="sorts" aria-label="<?php esc_attr_e( 'Sort products', 'gadgetim' ); ?>">
			<span class="sorts__l" aria-hidden="true"><?php gadgetim_the_icon( 'sort' ); ?><?php esc_html_e( 'Sort:', 'gadgetim' ); ?></span>
			<ul class="sorts__list">
				<?php foreach ( $gadgetim_sorts as $gadgetim_key => $gadgetim_label ) : ?>
					<li><a class="sorts__i" href="<?php echo esc_url( gadgetim_archive_url( $gadgetim_f, (string) $gadgetim_key ) ); ?>" data-arch-link data-sort="<?php echo esc_attr( (string) $gadgetim_key ); ?>"<?php echo $gadgetim_key === $gadgetim_sort ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $gadgetim_label ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<form class="sortf tb__sortf" method="get" action="<?php echo esc_url( (string) strtok( (string) $gadgetim_state['base_url'], '?' ) ); ?>">
			<label class="sr-only" for="tb-sort"><?php esc_html_e( 'Sort by', 'gadgetim' ); ?></label>
			<span class="sortf__field">
				<?php gadgetim_the_icon( 'sort' ); ?>
				<select class="sortf__select" id="tb-sort" name="orderby" data-sort-select>
					<?php foreach ( $gadgetim_sorts as $gadgetim_key => $gadgetim_label ) : ?>
						<option value="<?php echo esc_attr( (string) $gadgetim_key ); ?>"<?php selected( $gadgetim_sort, $gadgetim_key ); ?>><?php echo esc_html( $gadgetim_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
			<button class="btn btn--sm btn--soft sortf__go" type="submit"><?php esc_html_e( 'Sort', 'gadgetim' ); ?></button>
			<?php foreach ( $gadgetim_keep as $gadgetim_name => $gadgetim_value ) : ?>
				<?php if ( is_scalar( $gadgetim_value ) && '' !== (string) $gadgetim_value && 'orderby' !== $gadgetim_name ) : ?>
					<input type="hidden" name="<?php echo esc_attr( (string) $gadgetim_name ); ?>" value="<?php echo esc_attr( (string) $gadgetim_value ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
		</form>
	<?php endif; ?>

	<?php if ( $gadgetim_count > 4 ) : ?>
		<div class="tb__dens" role="group" aria-label="<?php esc_attr_e( 'Products per row', 'gadgetim' ); ?>">
			<button class="tb__d tb__d--4" type="button" data-density="0" aria-pressed="true" aria-label="<?php esc_attr_e( 'Four products per row', 'gadgetim' ); ?>">
				<svg class="ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/></svg>
			</button>
			<button class="tb__d tb__d--5" type="button" data-density="1" aria-pressed="false" aria-label="<?php esc_attr_e( 'Five products per row', 'gadgetim' ); ?>">
				<svg class="ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="3.5" width="4.6" height="4.6" rx="1.4"/><rect x="9.7" y="3.5" width="4.6" height="4.6" rx="1.4"/><rect x="15.9" y="3.5" width="4.6" height="4.6" rx="1.4"/><rect x="3.5" y="9.7" width="4.6" height="4.6" rx="1.4"/><rect x="9.7" y="9.7" width="4.6" height="4.6" rx="1.4"/><rect x="15.9" y="9.7" width="4.6" height="4.6" rx="1.4"/><rect x="3.5" y="15.9" width="4.6" height="4.6" rx="1.4"/><rect x="9.7" y="15.9" width="4.6" height="4.6" rx="1.4"/><rect x="15.9" y="15.9" width="4.6" height="4.6" rx="1.4"/></svg>
			</button>
		</div>
	<?php endif; ?>
</div>
