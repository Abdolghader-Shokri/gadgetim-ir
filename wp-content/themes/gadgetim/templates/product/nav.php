<?php
/**
 * In-page chips «معرفی · مشخصات فنی · نظرات · سوالات متداول · مرتبط»: sticky under the header,
 * plain anchors without JS; gallery.js adds the scroll-spy (aria-current), smooth scrolling and
 * keeps the bar under the header when the header tucks.
 *
 * @package Gadgetim
 *
 * @var array $args { sections: array<string,string> id => label }
 */

declare( strict_types=1 );

$gadgetim_sections = (array) ( $args['sections'] ?? [] );
if ( count( $gadgetim_sections ) < 2 ) {
	return;
}
?>
<nav class="pdp-nav" aria-label="<?php esc_attr_e( 'Product sections', 'gadgetim' ); ?>" data-pdp-nav>
	<ul class="pdp-nav__list">
		<?php foreach ( $gadgetim_sections as $gadgetim_id => $gadgetim_label ) : ?>
			<li><a class="pdp-nav__a" href="#<?php echo esc_attr( (string) $gadgetim_id ); ?>" data-spy="<?php echo esc_attr( (string) $gadgetim_id ); ?>"><?php echo esc_html( (string) $gadgetim_label ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
