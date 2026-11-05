<?php
/**
 * Closing call-to-action band (deep aurora gradient, drifting light, sparks): a title, a line of
 * text and up to three buttons; by default WhatsApp + the contact page + the shop.
 *
 * Args: title, text, icon, actions ([ gadgetim_button() args + label ] — replaces the defaults), id, class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_c = wp_parse_args(
	$args ?? [],
	[
		'title'   => __( 'Still have a question?', 'gadgetim' ),
		'text'    => __( 'Message us on WhatsApp or send us a note. A real person from our team will answer.', 'gadgetim' ),
		'icon'    => 'chat',
		'actions' => null,
		'id'      => 'pcta-t',
		'class'   => '',
	]
);
if ( null === $gadgetim_c['actions'] ) {
	$gadgetim_c['actions'] = [];
	$gadgetim_wa           = gadgetim_whatsapp_url( __( 'Hello! I have a question for Gadgetim.', 'gadgetim' ) );
	if ( '' !== $gadgetim_wa ) {
		$gadgetim_c['actions'][] = [
			'label'      => __( 'Chat on WhatsApp', 'gadgetim' ),
			'href'       => $gadgetim_wa,
			'variant'    => 'white',
			'icon'       => 'chat',
			'icon_start' => true,
			'attrs'      => [
				'target' => '_blank',
				'rel'    => 'noopener',
			],
		];
	}
	$gadgetim_contact = gadgetim_pages_role_page( 'contact' );
	if ( $gadgetim_contact && (int) get_queried_object_id() !== (int) $gadgetim_contact->ID ) {
		$gadgetim_c['actions'][] = [
			'label' => __( 'Contact us', 'gadgetim' ),
			'href'  => (string) get_permalink( $gadgetim_contact ),
			'class' => 'pcta__glass',
			'icon'  => 'arrow',
		];
	} else {
		$gadgetim_c['actions'][] = [
			'label' => __( 'Browse the shop', 'gadgetim' ),
			'href'  => gadgetim_shop_url(),
			'class' => 'pcta__glass',
			'icon'  => 'arrow',
		];
	}
}
$gadgetim_spark = '<path d="M12 1c1 7 4 10 11 11-7 1-10 4-11 11-1-7-4-10-11-11 7-1 10-4 11-11Z"/>';
?>
<section class="<?php echo esc_attr( trim( 'pcta ' . (string) $gadgetim_c['class'] ) ); ?>" aria-labelledby="<?php echo esc_attr( (string) $gadgetim_c['id'] ); ?>" data-reveal>
	<div class="pcta__fx" aria-hidden="true">
		<i class="pcta__orb o1"></i><i class="pcta__orb o2"></i><i class="pcta__orb o3"></i>
		<svg class="pcta__spark s1" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="pcta__spark s2" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="pcta__spark s3" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
	</div>
	<span class="pcta__ic" aria-hidden="true"><?php echo gadgetim_pages_icon( (string) $gadgetim_c['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
	<div class="pcta__text">
		<h2 class="pcta__t" id="<?php echo esc_attr( (string) $gadgetim_c['id'] ); ?>"><?php echo esc_html( (string) $gadgetim_c['title'] ); ?></h2>
		<?php if ( '' !== (string) $gadgetim_c['text'] ) : ?>
			<p class="pcta__p"><?php echo esc_html( (string) $gadgetim_c['text'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $gadgetim_c['actions'] ) : ?>
		<div class="pcta__acts">
			<?php foreach ( (array) $gadgetim_c['actions'] as $gadgetim_action ) : ?>
				<?php echo gadgetim_button( (string) ( $gadgetim_action['label'] ?? '' ), (array) $gadgetim_action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
