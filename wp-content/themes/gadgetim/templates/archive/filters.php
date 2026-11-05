<?php
/**
 * Filter form: a plain GET form (works without JS; the plugin normalises brand[]=…, empty
 * prices and Persian digits with a redirect). filters.js moves the same form between the sticky
 * sidebar (≥ 1000 px, changes apply at once) and the bottom sheet (phones/tablets, the pinned
 * button shows the live count from GET /wp-json/gadgetim/v1/count).
 *
 * Sections: sort (sheet only), in stock / discounted switches with counts, price range (dual
 * range slider on a proportional scale + two inputs that accept Persian digits), brands with
 * counts (disjunctive: each count keeps the other filters), categories as chips.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_state   = gadgetim_archive_state();
$gadgetim_info    = gadgetim_archive_info();
$gadgetim_f       = (array) $gadgetim_state['filters'];
$gadgetim_facets  = (array) $gadgetim_state['facets'];
$gadgetim_price   = (array) $gadgetim_facets['price'];
$gadgetim_brands  = (array) $gadgetim_state['brands'];
$gadgetim_hidden  = ! empty( $gadgetim_state['prices_hidden'] );
$gadgetim_count   = (int) $gadgetim_facets['count'];
$gadgetim_sorts   = gadgetim_archive_sort_options();
$gadgetim_sort    = gadgetim_archive_orderby();
$gadgetim_base    = (string) $gadgetim_state['base_url'];
$gadgetim_action  = (string) strtok( $gadgetim_base, '?' );
$gadgetim_context = (array) $gadgetim_state['context'];

// Query parameters of the base URL (search words, category refinement) travel as hidden inputs:
// a GET form replaces the action's own query string.
$gadgetim_keep = [];
$gadgetim_qs   = (string) wp_parse_url( $gadgetim_base, PHP_URL_QUERY );
if ( '' !== $gadgetim_qs ) {
	wp_parse_str( $gadgetim_qs, $gadgetim_keep );
}

$gadgetim_show_price = ! $gadgetim_hidden && (int) $gadgetim_price['max'] > (int) $gadgetim_price['min'] && count( (array) $gadgetim_price['steps'] ) > 1;
$gadgetim_show_brand = count( $gadgetim_brands ) > 1;
$gadgetim_limit      = 7;

// Categories: the hero chips, each link keeping the active filters and sort.
$gadgetim_carry = gadgetim_archive_filters_ready() ? Gadgetim\Core\Catalog\Filters::query_args( $gadgetim_f ) : [];
if ( $gadgetim_sort !== $gadgetim_state['default_orderby'] && 'relevance' !== $gadgetim_sort ) {
	$gadgetim_carry['orderby'] = $gadgetim_sort;
}
$gadgetim_cats = array_values( array_filter( (array) $gadgetim_info['chips']['items'], static fn( array $c ): bool => empty( $c['all'] ) ) );

/* translators: %s: number of products */
$gadgetim_apply = $gadgetim_count > 0 ? sprintf( _n( 'Show %s product', 'Show %s products', $gadgetim_count, 'gadgetim' ), gadgetim_number( $gadgetim_count ) ) : __( 'No product matches', 'gadgetim' );
?>
<form class="filters" id="filters" method="get" action="<?php echo esc_url( $gadgetim_action ); ?>" data-filters
	data-ctx="<?php echo esc_attr( (string) wp_json_encode( $gadgetim_context ) ); ?>"
	data-default-orderby="<?php echo esc_attr( (string) $gadgetim_state['default_orderby'] ); ?>"
	novalidate>

	<div class="filters__head">
		<span class="filters__grab" aria-hidden="true"></span>
		<h2 class="filters__t" id="filters-title">
			<span class="filters__ic" aria-hidden="true"><?php gadgetim_the_icon( 'filter' ); ?></span>
			<span><?php esc_html_e( 'Filters', 'gadgetim' ); ?></span>
			<b class="filters__n<?php echo $gadgetim_state['active_count'] ? '' : ' is-empty'; ?>" data-filters-n><?php echo esc_html( $gadgetim_state['active_count'] ? gadgetim_number( (int) $gadgetim_state['active_count'] ) : '' ); ?></b>
		</h2>
		<a class="filters__clear" href="<?php echo esc_url( gadgetim_archive_url( [], $gadgetim_sort ) ); ?>" data-arch-link data-filters-clear<?php echo $gadgetim_state['active'] ? '' : ' hidden'; ?>><?php gadgetim_the_icon( 'trash' ); ?><span><?php esc_html_e( 'Clear all', 'gadgetim' ); ?></span></a>
		<button class="icon-btn filters__close" type="button" data-close aria-label="<?php esc_attr_e( 'Close the filters', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'close' ); ?></button>
	</div>

	<div class="filters__body">
		<?php if ( count( $gadgetim_sorts ) > 1 ) : ?>
			<fieldset class="fsec fsec--sort">
				<legend class="fsec__t"><?php gadgetim_the_icon( 'sort' ); ?><?php esc_html_e( 'Sort by', 'gadgetim' ); ?></legend>
				<div class="fsorts">
					<?php foreach ( $gadgetim_sorts as $gadgetim_key => $gadgetim_label ) : ?>
						<label class="fsort">
							<input type="radio" name="orderby" value="<?php echo esc_attr( (string) $gadgetim_key ); ?>"<?php checked( $gadgetim_sort, $gadgetim_key ); ?>>
							<span><?php echo esc_html( $gadgetim_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<div class="fsec fsec--toggles">
			<label class="fswitch">
				<span class="fswitch__ic" aria-hidden="true"><?php gadgetim_the_icon( 'box' ); ?></span>
				<span class="fswitch__t"><?php esc_html_e( 'Only products in stock', 'gadgetim' ); ?> <small class="fcount" data-facet="instock"><?php echo esc_html( gadgetim_number( (int) $gadgetim_facets['instock'] ) ); ?></small></span>
				<input class="fswitch__in" type="checkbox" role="switch" name="instock" value="1"<?php checked( ! empty( $gadgetim_f['instock'] ) ); ?>>
				<span class="fswitch__ui" aria-hidden="true"></span>
			</label>
			<?php if ( ! $gadgetim_hidden ) : ?>
				<label class="fswitch">
					<span class="fswitch__ic fswitch__ic--hot" aria-hidden="true"><?php gadgetim_the_icon( 'percent' ); ?></span>
					<span class="fswitch__t"><?php esc_html_e( 'Only discounted products', 'gadgetim' ); ?> <small class="fcount" data-facet="sale"><?php echo esc_html( gadgetim_number( (int) $gadgetim_facets['sale'] ) ); ?></small></span>
					<input class="fswitch__in" type="checkbox" role="switch" name="sale" value="1"<?php checked( ! empty( $gadgetim_f['sale'] ) ); ?>>
					<span class="fswitch__ui" aria-hidden="true"></span>
				</label>
			<?php endif; ?>
		</div>

		<?php if ( $gadgetim_show_price ) : ?>
			<?php
			$gadgetim_lo = (int) $gadgetim_price['min'];
			$gadgetim_hi = (int) $gadgetim_price['max'];
			?>
			<fieldset class="fsec fsec--price" data-price
				data-min="<?php echo esc_attr( (string) $gadgetim_lo ); ?>"
				data-max="<?php echo esc_attr( (string) $gadgetim_hi ); ?>"
				data-steps="<?php echo esc_attr( (string) wp_json_encode( array_map( 'intval', (array) $gadgetim_price['steps'] ) ) ); ?>">
				<legend class="fsec__t"><?php gadgetim_the_icon( 'tag' ); ?><?php esc_html_e( 'Price range', 'gadgetim' ); ?> <small>(<?php echo esc_html( gadgetim_price_unit() ); ?>)</small></legend>
				<div class="frange" hidden data-range>
					<div class="frange__track" aria-hidden="true"><i class="frange__fill"></i></div>
					<input class="frange__in frange__in--lo" type="range" min="0" max="1000" step="5" value="0" aria-label="<?php esc_attr_e( 'Lowest price', 'gadgetim' ); ?>" data-range-lo>
					<input class="frange__in frange__in--hi" type="range" min="0" max="1000" step="5" value="1000" aria-label="<?php esc_attr_e( 'Highest price', 'gadgetim' ); ?>" data-range-hi>
				</div>
				<div class="fprice">
					<label class="fprice__f">
						<span class="fprice__l"><?php esc_html_e( 'From', 'gadgetim' ); ?></span>
						<input type="text" inputmode="numeric" autocomplete="off" name="min_price" dir="ltr"
							value="<?php echo esc_attr( (int) $gadgetim_f['min'] > 0 ? gadgetim_number( (int) $gadgetim_f['min'] ) : '' ); ?>"
							placeholder="<?php echo esc_attr( gadgetim_number( $gadgetim_lo ) ); ?>" data-price-lo>
					</label>
					<span class="fprice__sep" aria-hidden="true"></span>
					<label class="fprice__f">
						<span class="fprice__l"><?php esc_html_e( 'To', 'gadgetim' ); ?></span>
						<input type="text" inputmode="numeric" autocomplete="off" name="max_price" dir="ltr"
							value="<?php echo esc_attr( (int) $gadgetim_f['max'] > 0 ? gadgetim_number( (int) $gadgetim_f['max'] ) : '' ); ?>"
							placeholder="<?php echo esc_attr( gadgetim_number( $gadgetim_hi ) ); ?>" data-price-hi>
					</label>
				</div>
			</fieldset>
		<?php endif; ?>

		<?php if ( $gadgetim_show_brand ) : ?>
			<fieldset class="fsec fsec--brands">
				<legend class="fsec__t"><?php gadgetim_the_icon( 'crown' ); ?><?php esc_html_e( 'Brands', 'gadgetim' ); ?></legend>
				<ul class="fbrands" id="fbrands" data-brands>
					<?php foreach ( $gadgetim_brands as $gadgetim_i => $gadgetim_brand ) : ?>
						<?php
						$gadgetim_slug = (string) $gadgetim_brand['slug'];
						$gadgetim_on   = in_array( $gadgetim_slug, (array) $gadgetim_f['brand'], true );
						$gadgetim_n    = (int) ( $gadgetim_facets['brands'][ $gadgetim_slug ] ?? 0 );
						?>
						<li class="<?php echo $gadgetim_i >= $gadgetim_limit && ! $gadgetim_on ? 'is-extra' : ''; ?><?php echo 0 === $gadgetim_n && ! $gadgetim_on ? ' is-zero' : ''; ?>">
							<label class="fcheck">
								<input class="fcheck__in" type="checkbox" name="brand[]" value="<?php echo esc_attr( $gadgetim_slug ); ?>"<?php checked( $gadgetim_on ); ?>>
								<span class="fcheck__box" aria-hidden="true"><?php gadgetim_the_icon( 'check' ); ?></span>
								<span class="fcheck__t" dir="auto"><?php echo esc_html( (string) $gadgetim_brand['name'] ); ?></span>
								<small class="fcount" data-facet-brand="<?php echo esc_attr( $gadgetim_slug ); ?>"><?php echo esc_html( gadgetim_number( $gadgetim_n ) ); ?></small>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $gadgetim_brands ) > $gadgetim_limit ) : ?>
					<button class="fmore" type="button" aria-expanded="false" aria-controls="fbrands" data-brands-more hidden>
						<span class="fmore__on">
							<?php
							/* translators: %s: number of brands */
							echo esc_html( sprintf( __( 'Show all %s brands', 'gadgetim' ), gadgetim_number( count( $gadgetim_brands ) ) ) );
							?>
						</span>
						<span class="fmore__off"><?php esc_html_e( 'Show fewer brands', 'gadgetim' ); ?></span>
						<?php gadgetim_the_icon( 'chev-d' ); ?>
					</button>
				<?php endif; ?>
			</fieldset>
		<?php endif; ?>

		<?php if ( $gadgetim_cats ) : ?>
			<div class="fsec fsec--cats">
				<h3 class="fsec__t"><?php gadgetim_the_icon( 'layers' ); ?><?php echo esc_html( (string) $gadgetim_info['chips']['label'] ); ?></h3>
				<ul class="fcats">
					<?php foreach ( array_slice( $gadgetim_cats, 0, 16 ) as $gadgetim_cat ) : ?>
						<li><a class="fcat<?php echo ! empty( $gadgetim_cat['current'] ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( $gadgetim_carry ? add_query_arg( $gadgetim_carry, (string) $gadgetim_cat['url'] ) : (string) $gadgetim_cat['url'] ); ?>"<?php echo ! empty( $gadgetim_cat['current'] ) ? ' aria-current="page"' : ''; ?>>
							<?php echo gadgetim_bidi( (string) $gadgetim_cat['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?>
							<small aria-hidden="true"><?php echo esc_html( gadgetim_number( (int) $gadgetim_cat['count'] ) ); ?></small>
						</a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php foreach ( $gadgetim_keep as $gadgetim_name => $gadgetim_value ) : ?>
			<?php if ( is_scalar( $gadgetim_value ) && '' !== (string) $gadgetim_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( (string) $gadgetim_name ); ?>" value="<?php echo esc_attr( (string) $gadgetim_value ); ?>" data-keep>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<div class="filters__foot">
		<button class="btn btn--block btn--lg filters__apply" type="submit" data-filters-apply<?php echo 0 === $gadgetim_count ? ' aria-disabled="true"' : ''; ?>>
			<span data-filters-label><?php echo esc_html( $gadgetim_apply ); ?></span>
		</button>
		<a class="btn btn--ghost btn--sm filters__reset" href="<?php echo esc_url( gadgetim_archive_url( [], $gadgetim_sort ) ); ?>" data-filters-reset<?php echo $gadgetim_state['active'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear filters', 'gadgetim' ); ?></a>
	</div>
</form>
