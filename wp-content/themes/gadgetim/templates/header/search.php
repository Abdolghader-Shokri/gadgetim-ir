<?php
/**
 * Product search: category selector (≥ 1000 px), input as an ARIA combobox, submit button and
 * the suggestion listbox filled by search.js (GET gadgetim/v1/search). Works without JS as a
 * plain GET form to the product search results (?s=…&post_type=product[&product_cat=…]).
 *
 * The <template> holds the idle / no-results content (popular searches, categories, today's
 * deals) rendered on the server, so search.js needs no extra request for it.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_groups  = gadgetim_category_groups();
$gadgetim_query   = get_search_query();
$gadgetim_cat     = isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( (string) $_GET['product_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search filter.
$gadgetim_popular = gadgetim_list_setting( 'theme.search_popular' );
$gadgetim_deals   = array_values( array_filter( array_map( static fn( array $g ): int => (int) $g['feature'], $gadgetim_groups ) ) );
?>
<form class="search" id="search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" data-search>
	<?php if ( $gadgetim_groups ) : ?>
		<div class="search__cat">
			<label class="sr-only" for="q-cat"><?php esc_html_e( 'Search in category', 'gadgetim' ); ?></label>
			<select id="q-cat" name="product_cat">
				<option value=""><?php esc_html_e( 'All categories', 'gadgetim' ); ?></option>
				<?php foreach ( $gadgetim_groups as $gadgetim_group ) : ?>
					<option value="<?php echo esc_attr( (string) $gadgetim_group['slug'] ); ?>" data-id="<?php echo esc_attr( (string) $gadgetim_group['id'] ); ?>"<?php selected( $gadgetim_cat, sanitize_title( (string) $gadgetim_group['slug'] ) ); ?>><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php gadgetim_the_icon( 'chev-d' ); ?>
		</div>
	<?php endif; ?>
	<label class="sr-only" for="q"><?php esc_html_e( 'Search products', 'gadgetim' ); ?></label>
	<input id="q" name="s" type="search" value="<?php echo esc_attr( $gadgetim_query ); ?>" autocomplete="off" spellcheck="false" enterkeyhint="search" placeholder="<?php esc_attr_e( 'Search original gadgets…', 'gadgetim' ); ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugg" aria-haspopup="listbox">
	<input type="hidden" name="post_type" value="product">
	<button class="search__go" type="submit" aria-label="<?php esc_attr_e( 'Search', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'search' ); ?></button>
	<div class="sugg" id="sugg" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'gadgetim' ); ?>" hidden></div>
	<template id="sugg-idle">
		<?php if ( $gadgetim_popular ) : ?>
			<div role="group" aria-label="<?php esc_attr_e( 'Popular searches', 'gadgetim' ); ?>" data-part="popular">
				<div class="sugg__h" aria-hidden="true"><?php gadgetim_the_icon( 'trend' ); ?><?php esc_html_e( 'Popular searches', 'gadgetim' ); ?></div>
				<div class="sugg__chips">
					<?php foreach ( $gadgetim_popular as $gadgetim_term ) : ?>
						<span class="chip-q" role="option" aria-selected="false" data-q="<?php echo esc_attr( $gadgetim_term ); ?>"><?php gadgetim_the_icon( 'search' ); ?><?php echo esc_html( $gadgetim_term ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $gadgetim_groups ) : ?>
			<div role="group" aria-label="<?php esc_attr_e( 'Categories', 'gadgetim' ); ?>" data-part="cats">
				<div class="sugg__h" aria-hidden="true"><?php gadgetim_the_icon( 'grid' ); ?><?php esc_html_e( 'Browse categories', 'gadgetim' ); ?></div>
				<div class="sugg__chips">
					<?php foreach ( array_slice( $gadgetim_groups, 0, 8 ) as $gadgetim_group ) : ?>
						<a class="chip-q" role="option" aria-selected="false" href="<?php echo esc_url( (string) $gadgetim_group['url'] ); ?>" data-name="<?php echo esc_attr( (string) $gadgetim_group['name'] ); ?>"><?php gadgetim_the_icon( (string) $gadgetim_group['icon'] ); ?><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></a>
					<?php endforeach; ?>
					<?php foreach ( $gadgetim_groups as $gadgetim_group ) : ?>
						<?php foreach ( (array) $gadgetim_group['links'] as $gadgetim_link ) : ?>
							<a class="chip-q" role="option" aria-selected="false" href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>" data-name="<?php echo esc_attr( (string) $gadgetim_link['name'] ); ?>" data-sub hidden><?php gadgetim_the_icon( 'grid' ); ?><?php echo esc_html( (string) $gadgetim_link['name'] ); ?></a>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $gadgetim_deals && ! gadgetim_contact_mode() ) : ?>
			<div role="group" aria-label="<?php esc_attr_e( 'Today’s best deals', 'gadgetim' ); ?>" data-part="deals">
				<div class="sugg__h" aria-hidden="true"><?php gadgetim_the_icon( 'flame' ); ?><?php esc_html_e( 'Today’s best deals', 'gadgetim' ); ?></div>
				<?php
				foreach ( array_slice( array_unique( $gadgetim_deals ), 0, 3 ) as $gadgetim_deal ) {
					$gadgetim_card = gadgetim_product_card(
						$gadgetim_deal,
						'mini',
						[
							'tag'    => 'div',
							'reveal' => false,
						]
					);
					// The mini card's link becomes a listbox option.
					echo str_replace( '<a class="pmini"', '<a class="pmini sugg__opt" role="option" aria-selected="false"', $gadgetim_card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
				}
				?>
			</div>
		<?php endif; ?>
	</template>
</form>
