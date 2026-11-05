<?php
/**
 * Mega menu (≥ 1000 px): category groups on the inline-start, the active group's sub-categories
 * (with images), popular brands, best deals and a featured product. Server-rendered plain links
 * (crawlable); app.js opens it with hover intent / click and switches panes on hover/focus.
 * Without JS it opens on :hover / :focus-within. Images are lazy and load only when shown. Only the
 * first pane is in the page; app.js fetches the others (?gtm_mega=1, see gadgetim_mega_fragment()).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_groups = gadgetim_category_groups();
if ( ! $gadgetim_groups ) {
	return;
}
?>
<div class="mega" id="mega" data-mega-panel data-mega-src="<?php echo esc_url( add_query_arg( 'gtm_mega', '1', home_url( '/' ) ) ); ?>">
	<div class="mega__panel">
		<ul class="mega__groups">
			<?php foreach ( $gadgetim_groups as $gadgetim_i => $gadgetim_group ) : ?>
				<li><a class="mega__g<?php echo 0 === $gadgetim_i ? ' is-active' : ''; ?>" href="<?php echo esc_url( (string) $gadgetim_group['url'] ); ?>" data-g="<?php echo esc_attr( (string) $gadgetim_i ); ?>"><span class="gi"><?php gadgetim_the_icon( (string) $gadgetim_group['icon'] ); ?></span><span><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></span><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></a></li>
			<?php endforeach; ?>
			<li class="mega__all-li"><a class="mega__all" href="<?php echo esc_url( gadgetim_shop_url() ); ?>"><?php esc_html_e( 'All products', 'gadgetim' ); ?><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a></li>
		</ul>
		<div class="mega__body">
			<?php foreach ( $gadgetim_groups as $gadgetim_i => $gadgetim_group ) : ?>
				<?php
				if ( 0 === $gadgetim_i ) {
					gadgetim_part(
						'header/mega-pane',
						[
							'group' => $gadgetim_group,
							'index' => 0,
						]
					);
					continue;
				}
				?>
				<div class="mega__pane mega__pane--lazy" data-pane="<?php echo esc_attr( (string) $gadgetim_i ); ?>" data-lazy><span class="mega__wait" aria-hidden="true"><i></i><i></i><i></i></span></div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
