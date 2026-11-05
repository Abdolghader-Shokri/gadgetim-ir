<?php
/**
 * Beside / under the buy box: key features (first spec rows → «مشخصات فنی»), the short description
 * when the product has one, and the authenticity card (ProductExtras::guarantee, the trust
 * verification steps, the guarantee page link).
 *
 * @package Gadgetim
 *
 * @var array $args { product: WC_Product }
 */

declare( strict_types=1 );

$gadgetim_product = $args['product'] ?? null;
if ( ! $gadgetim_product instanceof WC_Product ) {
	return;
}

$gadgetim_keys      = gadgetim_pdp_key_specs( $gadgetim_product, 4 );
$gadgetim_has_specs = (bool) gadgetim_pdp_specs( $gadgetim_product );
$gadgetim_short     = trim( (string) apply_filters( 'woocommerce_short_description', $gadgetim_product->get_short_description() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce filter.
$gadgetim_attrs     = gadgetim_pdp_content_attrs();
$gadgetim_extras    = 'Gadgetim\Core\Catalog\ProductExtras';
$gadgetim_guarantee = class_exists( $gadgetim_extras ) ? trim( (string) $gadgetim_extras::guarantee( $gadgetim_product ) ) : '';
$gadgetim_steps     = gadgetim_list_setting( 'trust.verification_steps' );
$gadgetim_g_url     = gadgetim_guarantee_url();
?>
<div class="pdp__side">
	<?php if ( $gadgetim_keys || '' !== $gadgetim_short ) : ?>
		<section class="pdp-feat" aria-labelledby="pdp-feat-t">
			<h2 class="pdp-feat__t" id="pdp-feat-t"><?php gadgetim_the_icon( 'layers' ); ?><?php esc_html_e( 'Key features', 'gadgetim' ); ?></h2>
			<?php if ( $gadgetim_keys ) : ?>
				<ul class="pdp-feat__list"<?php echo $gadgetim_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
					<?php foreach ( $gadgetim_keys as $gadgetim_row ) : ?>
						<li><span><?php echo gadgetim_pdp_text( $gadgetim_row[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span><b><?php echo gadgetim_pdp_text( $gadgetim_row[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></b></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_short ) : ?>
				<div class="pdp-feat__shortw" data-clamp>
					<div class="pdp-feat__short prose" id="pdp-short" data-clamp-body<?php echo $gadgetim_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>><?php echo gadgetim_pdp_html_digits( wp_kses_post( $gadgetim_short ) ); ?></div>
					<button class="pdp-feat__toggle" type="button" data-clamp-btn aria-expanded="false" aria-controls="pdp-short" hidden><span data-clamp-label><?php esc_html_e( 'Read more', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'chev-d' ); ?></button>
				</div>
			<?php endif; ?>
			<?php if ( $gadgetim_has_specs ) : ?>
				<a class="pdp-feat__more" href="#tab-specs"><?php esc_html_e( 'All specifications', 'gadgetim' ); ?><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="pdp-guar" aria-labelledby="pdp-guar-t">
		<span class="pdp-guar__seal" aria-hidden="true"><?php echo gadgetim_pdp_svg( 'seal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div class="pdp-guar__body">
			<h2 class="pdp-guar__t" id="pdp-guar-t"><?php esc_html_e( 'Authenticity guarantee', 'gadgetim' ); ?></h2>
			<div class="pdp-guar__x">
				<?php
				if ( '' !== $gadgetim_guarantee ) {
					echo gadgetim_pdp_html_digits( wp_kses_post( $gadgetim_guarantee ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output, digits in text nodes only.
				} else {
					echo '<p>' . esc_html__( 'Every product is genuine, with an authenticity guarantee.', 'gadgetim' ) . '</p>';
				}
				?>
			</div>
			<?php if ( $gadgetim_steps ) : ?>
				<ul class="pdp-guar__steps">
					<?php foreach ( $gadgetim_steps as $gadgetim_step ) : ?>
						<li><?php gadgetim_the_icon( 'check' ); ?><?php echo esc_html( gadgetim_digits( $gadgetim_step ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_g_url ) : ?>
				<a class="pdp-guar__more" href="<?php echo esc_url( $gadgetim_g_url ); ?>"><?php esc_html_e( 'How the guarantee works', 'gadgetim' ); ?><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
</div>
