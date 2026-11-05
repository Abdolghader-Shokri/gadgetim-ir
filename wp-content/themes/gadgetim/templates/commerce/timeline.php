<?php
/**
 * Order status timeline (gadgetim_order_timeline()): placed → payment → preparing → completed,
 * with dates where known, the current step pulsing, a stopped step for cancelled/failed/refunded
 * orders. Vertical on phones, horizontal from 768 px.
 *
 * @package Gadgetim
 *
 * @var array $args { order: WC_Order, steps: array, title: bool, class: string }
 */

declare( strict_types=1 );

$gadgetim_order = $args['order'] ?? null;
$gadgetim_steps = (array) ( $args['steps'] ?? [] );
if ( ! $gadgetim_order instanceof WC_Order || ! $gadgetim_steps ) {
	return;
}
$gadgetim_uid    = 'otl-' . (int) $gadgetim_order->get_id();
$gadgetim_states = [
	'done'    => __( 'Done', 'gadgetim' ),
	'current' => __( 'In progress', 'gadgetim' ),
	'next'    => __( 'Up next', 'gadgetim' ),
	'stopped' => __( 'Stopped', 'gadgetim' ),
];
$gadgetim_done   = count( array_filter( $gadgetim_steps, static fn( array $s ): bool => 'done' === ( $s['state'] ?? '' ) ) );
$gadgetim_total  = max( 1, count( $gadgetim_steps ) - 1 );
$gadgetim_p      = min( 1, max( 0, ( $gadgetim_done - 1 + ( in_array( 'current', array_column( $gadgetim_steps, 'state' ), true ) ? 0.5 : 0 ) ) / $gadgetim_total ) );
?>
<section class="<?php echo esc_attr( trim( 'cm-card otl ' . (string) ( $args['class'] ?? '' ) ) ); ?>" aria-labelledby="<?php echo esc_attr( $gadgetim_uid ); ?>-t">
	<?php if ( false !== ( $args['title'] ?? true ) ) : ?>
		<header class="cm-card__head">
			<span class="cm-card__ic" aria-hidden="true"><?php gadgetim_the_icon( 'clock' ); ?></span>
			<div class="cm-card__titles">
				<h2 class="cm-card__t" id="<?php echo esc_attr( $gadgetim_uid ); ?>-t"><?php esc_html_e( 'Order status', 'gadgetim' ); ?></h2>
				<p class="cm-card__sub">
					<?php
					/* translators: %s: order number */
					echo esc_html( sprintf( __( 'Order %s', 'gadgetim' ), gadgetim_order_number( $gadgetim_order ) ) );
					?>
				</p>
			</div>
			<?php echo gadgetim_order_status_chip( $gadgetim_order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		</header>
	<?php else : ?>
		<h2 class="sr-only" id="<?php echo esc_attr( $gadgetim_uid ); ?>-t"><?php esc_html_e( 'Order status', 'gadgetim' ); ?></h2>
	<?php endif; ?>
	<ol class="otl__list" style="--p:<?php echo esc_attr( (string) round( $gadgetim_p, 3 ) ); ?>">
		<?php foreach ( $gadgetim_steps as $gadgetim_step ) : ?>
			<?php $gadgetim_state = (string) ( $gadgetim_step['state'] ?? 'next' ); ?>
			<li class="otl__i is-<?php echo esc_attr( $gadgetim_state ); ?>"<?php echo 'current' === $gadgetim_state ? ' aria-current="step"' : ''; ?>>
				<span class="otl__dot" aria-hidden="true"><?php gadgetim_the_icon( 'done' === $gadgetim_state ? 'check' : (string) ( $gadgetim_step['icon'] ?? 'clock' ) ); ?></span>
				<span class="otl__b">
					<b class="otl__t"><?php echo esc_html( (string) ( $gadgetim_step['label'] ?? '' ) ); ?><span class="sr-only"> — <?php echo esc_html( $gadgetim_states[ $gadgetim_state ] ?? '' ); ?></span></b>
					<?php if ( ! empty( $gadgetim_step['text'] ) ) : ?>
						<span class="otl__x"><?php echo esc_html( (string) $gadgetim_step['text'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $gadgetim_step['date'] ) ) : ?>
						<span class="otl__d"><?php echo esc_html( (string) $gadgetim_step['date'] ); ?></span>
					<?php endif; ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>
</section>
