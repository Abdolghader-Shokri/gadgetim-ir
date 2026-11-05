<?php
/**
 * Product title area: brand chip, category chip, authenticity chip, short title (H1), subtitle,
 * rating / reviews link, views, SKU, the WooCommerce meta hooks, share button with its popover.
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

$gadgetim_extras   = 'Gadgetim\Core\Catalog\ProductExtras';
$gadgetim_brand    = gadgetim_product_brand( $gadgetim_product );
$gadgetim_lane     = gadgetim_product_lane( $gadgetim_product );
$gadgetim_subtitle = class_exists( $gadgetim_extras ) ? (string) $gadgetim_extras::subtitle( $gadgetim_product ) : '';
$gadgetim_views    = class_exists( $gadgetim_extras ) && (bool) gadgetim_setting( 'views.enabled', true ) ? (int) $gadgetim_extras::views( $gadgetim_product ) : 0;
$gadgetim_sku      = (string) $gadgetim_product->get_sku();
$gadgetim_attrs    = gadgetim_pdp_content_attrs();
$gadgetim_stats    = gadgetim_pdp_review_stats( $gadgetim_product );
$gadgetim_reviews  = comments_open( $gadgetim_product->get_id() ) && function_exists( 'wc_reviews_enabled' ) && wc_reviews_enabled();
$gadgetim_ratings  = ! function_exists( 'wc_review_ratings_enabled' ) || wc_review_ratings_enabled();
$gadgetim_share    = gadgetim_pdp_share_links( $gadgetim_product );

// The meta hooks still fire (GTIN, custom fields and other plugins print into the meta row).
ob_start();
/** Hook: woocommerce_product_meta_start. */
do_action( 'woocommerce_product_meta_start' );
$gadgetim_meta_start = trim( (string) ob_get_clean() );
ob_start();
/** Hook: woocommerce_product_meta_end. */
do_action( 'woocommerce_product_meta_end' );
$gadgetim_meta_end = trim( (string) ob_get_clean() );
?>
<header class="pdp-head">
	<div class="pdp-head__chips">
		<?php if ( $gadgetim_brand ) : ?>
			<?php $gadgetim_brand_url = get_term_link( $gadgetim_brand ); ?>
			<a class="pdp-chip pdp-chip--brand" href="<?php echo esc_url( is_string( $gadgetim_brand_url ) ? $gadgetim_brand_url : '' ); ?>" dir="auto"><i class="pdp-chip__dot" aria-hidden="true"></i><?php echo esc_html( gadgetim_term_name( $gadgetim_brand ) ); ?></a>
		<?php endif; ?>
		<?php if ( $gadgetim_lane ) : ?>
			<?php $gadgetim_lane_url = get_term_link( $gadgetim_lane ); ?>
			<a class="pdp-chip" href="<?php echo esc_url( is_string( $gadgetim_lane_url ) ? $gadgetim_lane_url : '' ); ?>"><?php gadgetim_the_icon( function_exists( 'gadgetim_category_icon' ) ? gadgetim_category_icon( $gadgetim_lane ) : 'grid' ); ?><?php echo esc_html( gadgetim_term_name( $gadgetim_lane ) ); ?></a>
		<?php endif; ?>
		<span class="pdp-chip pdp-chip--ok"><?php gadgetim_the_icon( 'shield' ); ?><?php esc_html_e( 'Original product', 'gadgetim' ); ?></span>
	</div>

	<?php $gadgetim_long = mb_strlen( gadgetim_short_title( $gadgetim_product ) ) > 64; ?>
	<h1 class="pdp-head__title product_title entry-title<?php echo $gadgetim_long ? ' pdp-head__title--long' : ''; ?>"<?php echo $gadgetim_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>><?php echo gadgetim_pdp_text( gadgetim_short_title( $gadgetim_product ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></h1>

	<?php if ( '' !== $gadgetim_subtitle ) : ?>
		<p class="pdp-head__sub"<?php echo $gadgetim_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>><?php echo gadgetim_pdp_text( $gadgetim_subtitle ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></p>
	<?php endif; ?>

	<div class="pdp-head__meta product_meta">
		<?php echo $gadgetim_meta_start; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of WooCommerce hooks. ?>

		<?php if ( $gadgetim_reviews && $gadgetim_ratings && $gadgetim_stats['count'] > 0 && $gadgetim_stats['average'] > 0 ) : ?>
			<a class="pdp-head__rate" href="#tab-reviews">
				<?php
				/* translators: 1: average rating, 2: number of reviews */
				$gadgetim_rate_label = sprintf( __( 'Rated %1$s out of 5 from %2$s reviews', 'gadgetim' ), gadgetim_number( $gadgetim_stats['average'], 1 ), gadgetim_number( $gadgetim_stats['count'] ) );
				?>
				<span class="stars" role="img" aria-label="<?php echo esc_attr( $gadgetim_rate_label ); ?>" style="--rating:<?php echo esc_attr( (string) round( $gadgetim_stats['average'], 2 ) ); ?>">
					<span class="stars__track" aria-hidden="true"><?php echo str_repeat( gadgetim_icon( 'star' ), 5 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icons. ?></span>
					<span class="stars__fill" aria-hidden="true"><?php echo str_repeat( gadgetim_icon( 'star' ), 5 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icons. ?></span>
				</span>
				<b aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_stats['average'], 1 ) ); ?></b>
				<?php /* translators: %s: number of reviews */ ?>
				<span aria-hidden="true"><?php echo esc_html( sprintf( _n( '(%s review)', '(%s reviews)', $gadgetim_stats['count'], 'gadgetim' ), gadgetim_number( $gadgetim_stats['count'] ) ) ); ?></span>
			</a>
		<?php elseif ( $gadgetim_reviews ) : ?>
			<a class="pdp-head__rate pdp-head__rate--none" href="#tab-reviews"><?php gadgetim_the_icon( 'star' ); ?><?php echo $gadgetim_stats['count'] > 0 ? esc_html( sprintf( /* translators: %s: number of reviews */ _n( '%s review', '%s reviews', $gadgetim_stats['count'], 'gadgetim' ), gadgetim_number( $gadgetim_stats['count'] ) ) ) : esc_html__( 'Be the first to review', 'gadgetim' ); ?></a>
		<?php endif; ?>

		<?php if ( $gadgetim_views >= 20 ) : ?>
			<?php /* translators: %s: number of views */ ?>
			<span class="pdp-head__views"><?php gadgetim_the_icon( 'eye' ); ?><?php echo esc_html( sprintf( __( '%s views', 'gadgetim' ), gadgetim_number( $gadgetim_views ) ) ); ?></span>
		<?php endif; ?>

		<?php if ( '' !== $gadgetim_sku ) : ?>
			<span class="pdp-head__sku sku_wrapper"><?php esc_html_e( 'SKU', 'gadgetim' ); ?> <bdi class="sku" dir="ltr"><?php echo esc_html( $gadgetim_sku ); ?></bdi></span>
		<?php endif; ?>

		<?php echo $gadgetim_meta_end; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of WooCommerce hooks. ?>

		<button class="pdp-head__share" type="button" popovertarget="pdp-pop-share" data-share data-share-title="<?php echo esc_attr( gadgetim_short_title( $gadgetim_product ) ); ?>" data-share-url="<?php echo esc_url( (string) $gadgetim_product->get_permalink() ); ?>"><?php gadgetim_the_icon( 'share' ); ?><span><?php esc_html_e( 'Share', 'gadgetim' ); ?></span></button>
	</div>

	<div class="pdp-pop pdp-pop--share" id="pdp-pop-share" popover aria-labelledby="pdp-pop-share-t">
		<p class="pdp-pop__t" id="pdp-pop-share-t"><?php esc_html_e( 'Share this product', 'gadgetim' ); ?></p>
		<div class="pdp-share">
			<?php foreach ( $gadgetim_share as $gadgetim_link ) : ?>
				<a class="pdp-share__a pdp-share__a--<?php echo esc_attr( (string) $gadgetim_link['id'] ); ?>" href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>" target="_blank" rel="noopener nofollow"><span class="pdp-share__ic"><?php gadgetim_the_icon( (string) $gadgetim_link['icon'] ); ?></span><?php echo esc_html( (string) $gadgetim_link['label'] ); ?></a>
			<?php endforeach; ?>
			<button class="pdp-share__a pdp-share__a--copy" type="button" data-copy="<?php echo esc_url( (string) $gadgetim_product->get_permalink() ); ?>" hidden><span class="pdp-share__ic"><?php echo gadgetim_pdp_svg( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php esc_html_e( 'Copy link', 'gadgetim' ); ?></button>
		</div>
		<?php
		/** Hook: woocommerce_share (share plugins print their buttons here). */
		do_action( 'woocommerce_share' );
		?>
	</div>
</header>
