<?php
/**
 * Shipment card (gadgetim_order_shipment_card()): the shipping company and the date the parcel was
 * sent, the tracking code with a copy button, and a button to the company's tracking page (the
 * company's pages ask for the code themselves, so the hint says to paste it). Shown on the
 * account's order view and on the order tracking page once the shop has saved a tracking code.
 *
 * @package Gadgetim
 *
 * @var array $args { order: WC_Order, shipment: array{carrier: string, name: string, code: string, url: string, date: int} }
 */

declare( strict_types=1 );

$gadgetim_order    = $args['order'] ?? null;
$gadgetim_shipment = (array) ( $args['shipment'] ?? [] );
$gadgetim_code     = (string) ( $gadgetim_shipment['code'] ?? '' );
if ( ! $gadgetim_order instanceof WC_Order || '' === $gadgetim_code ) {
	return;
}
$gadgetim_uid  = 'shp-' . (int) $gadgetim_order->get_id();
$gadgetim_name = (string) ( $gadgetim_shipment['name'] ?? '' );
$gadgetim_url  = (string) ( $gadgetim_shipment['url'] ?? '' );
$gadgetim_when = ! empty( $gadgetim_shipment['date'] ) ? gadgetim_date( (int) $gadgetim_shipment['date'], 'medium' ) : '';
?>
<section class="cm-card shp" aria-labelledby="<?php echo esc_attr( $gadgetim_uid ); ?>-t">
	<header class="cm-card__head">
		<span class="cm-card__ic cm-card__ic--green" aria-hidden="true"><?php gadgetim_the_icon( 'truck' ); ?></span>
		<div class="cm-card__titles">
			<h2 class="cm-card__t" id="<?php echo esc_attr( $gadgetim_uid ); ?>-t"><?php esc_html_e( 'Your parcel', 'gadgetim' ); ?></h2>
			<p class="cm-card__sub">
				<?php
				echo esc_html(
					'' !== $gadgetim_when
						/* translators: 1: shipping company, 2: date the parcel was sent */
						? sprintf( __( 'Sent with %1$s on %2$s', 'gadgetim' ), $gadgetim_name, $gadgetim_when )
						/* translators: %s: shipping company */
						: sprintf( __( 'Sent with %s', 'gadgetim' ), $gadgetim_name )
				);
				?>
			</p>
		</div>
	</header>

	<div class="shp__code">
		<span class="shp__k"><?php esc_html_e( 'Tracking code', 'gadgetim' ); ?></span>
		<?php // kbd: what the shopper types on the carrier's page, and gadgetim_pages_content_digits() leaves it alone, so the code keeps the Latin digits those pages expect. ?>
		<kbd class="shp__v" dir="ltr" lang="en" translate="no"><?php echo esc_html( $gadgetim_code ); ?></kbd>
		<button type="button" class="btn btn--outline btn--sm shp__copy" data-copy="<?php echo esc_attr( $gadgetim_code ); ?>" data-copied="<?php esc_attr_e( 'Tracking code copied', 'gadgetim' ); ?>" aria-label="<?php esc_attr_e( 'Copy the tracking code', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'copy' ); ?><span><?php esc_html_e( 'Copy', 'gadgetim' ); ?></span></button>
	</div>

	<?php if ( '' !== $gadgetim_url ) : ?>
		<p class="shp__go">
			<a class="btn btn--sm shp__link" href="<?php echo esc_url( $gadgetim_url ); ?>" target="_blank" rel="noopener noreferrer"><?php gadgetim_the_icon( 'search' ); ?><span>
			<?php
			/* translators: %s: shipping company */
			echo esc_html( sprintf( __( 'Track it on the %s website', 'gadgetim' ), $gadgetim_name ) );
			?>
			</span></a>
		</p>
		<p class="shp__hint"><?php esc_html_e( 'Copy the code, then paste it on the page that opens.', 'gadgetim' ); ?></p>
	<?php else : ?>
		<p class="shp__hint">
			<?php
			/* translators: %s: shipping company */
			echo esc_html( sprintf( __( 'Track your parcel with this code on the website or app of %s.', 'gadgetim' ), $gadgetim_name ) );
			?>
		</p>
	<?php endif; ?>
</section>
