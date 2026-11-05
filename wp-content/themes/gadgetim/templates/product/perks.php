<?php
/**
 * Buy box promise cards (delivery · return · secure payment). Each card opens a popover (native
 * Popover API; gallery.js positions it on wide screens, phones show it as a bottom sheet) with the
 * policy text from Storefront → Product page (built-in sentence when empty).
 *
 * @package Gadgetim
 *
 * @var array $args { perks: array<string,array{icon:string,tone:string,title:string,body:string}> }
 */

declare( strict_types=1 );

$gadgetim_perks = (array) ( $args['perks'] ?? [] );
if ( ! $gadgetim_perks ) {
	return;
}
?>
<ul class="perks" aria-label="<?php esc_attr_e( 'Our promises', 'gadgetim' ); ?>">
	<?php foreach ( $gadgetim_perks as $gadgetim_id => $gadgetim_perk ) : ?>
		<?php
		$gadgetim_key = sanitize_html_class( (string) $gadgetim_id );
		$gadgetim_pop = 'pdp-pop-' . $gadgetim_key;
		?>
		<li>
			<button class="perk perk--<?php echo esc_attr( sanitize_html_class( (string) ( $gadgetim_perk['tone'] ?? 'violet' ) ) ); ?>" type="button" popovertarget="<?php echo esc_attr( $gadgetim_pop ); ?>">
				<span class="perk__ic"><?php gadgetim_the_icon( (string) ( $gadgetim_perk['icon'] ?? 'shield' ) ); ?></span>
				<span class="perk__t"><?php echo esc_html( gadgetim_digits( (string) ( $gadgetim_perk['title'] ?? '' ) ) ); ?></span>
			</button>
			<div class="pdp-pop" id="<?php echo esc_attr( $gadgetim_pop ); ?>" popover aria-labelledby="<?php echo esc_attr( $gadgetim_pop ); ?>-t">
				<p class="pdp-pop__t" id="<?php echo esc_attr( $gadgetim_pop ); ?>-t"><span class="perk__ic perk__ic--sm perk--<?php echo esc_attr( sanitize_html_class( (string) ( $gadgetim_perk['tone'] ?? 'violet' ) ) ); ?>"><?php gadgetim_the_icon( (string) ( $gadgetim_perk['icon'] ?? 'shield' ) ); ?></span><?php echo esc_html( gadgetim_digits( (string) ( $gadgetim_perk['title'] ?? '' ) ) ); ?></p>
				<p><?php echo esc_html( gadgetim_digits( (string) ( $gadgetim_perk['body'] ?? '' ) ) ); ?></p>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
