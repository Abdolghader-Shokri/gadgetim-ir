<?php
/**
 * Product suggestions rail (cart and empty cart): section head with rail arrows and a scroll rail
 * of product cards with the animated add button. Cards below the fold reveal on scroll.
 *
 * @package Gadgetim
 *
 * @var array $args { ids: int[], title: string, sub: string, id: string, reveal: bool (default true;
 *                    false when the rail may sit in the first viewport) }
 */

declare( strict_types=1 );

$gadgetim_ids = array_values( array_filter( array_map( 'intval', (array) ( $args['ids'] ?? [] ) ) ) );
if ( ! $gadgetim_ids ) {
	return;
}
$gadgetim_id     = sanitize_html_class( (string) ( $args['id'] ?? 'csugg' ) );
$gadgetim_reveal = false !== ( $args['reveal'] ?? true );
gadgetim_set_eager_budget( 0 );
?>
<section class="sect csugg" aria-labelledby="<?php echo esc_attr( $gadgetim_id ); ?>-t">
	<?php
	gadgetim_the_section_head(
		[
			'title'    => (string) ( $args['title'] ?? __( 'Goes well with your cart', 'gadgetim' ) ),
			'sub'      => (string) ( $args['sub'] ?? '' ),
			'id'       => $gadgetim_id . '-t',
			'icon'     => 'sparkle',
			'tone'     => 'warm',
			'more_url' => gadgetim_shop_url(),
			'rail'     => $gadgetim_id . '-rail',
			'reveal'   => $gadgetim_reveal,
		]
	);
	?>
	<ul class="rail prail" id="<?php echo esc_attr( $gadgetim_id ); ?>-rail">
		<?php foreach ( $gadgetim_ids as $gadgetim_i => $gadgetim_pid ) : ?>
			<?php
			gadgetim_the_product_card(
				$gadgetim_pid,
				'rail',
				[
					'index'  => $gadgetim_i,
					'reveal' => $gadgetim_reveal,
				]
			);
			?>
		<?php endforeach; ?>
	</ul>
</section>
