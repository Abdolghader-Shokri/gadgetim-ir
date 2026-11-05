<?php
/**
 * Single Product tabs
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/tabs/tabs.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: no tab widget — stacked sections that the sticky chips (templates/product/nav.php)
 * scroll to, in this order: «معرفی» (description, collapsed behind «ادامه مطلب» by gallery.js;
 * fully open without JS), «مشخصات فنی» (zebra table: ProductExtras::specs + attributes),
 * «نظرات» (comments_template → single-product-reviews.php), «سوالات متداول» (accordion), then any
 * third-party tab as its own section and woocommerce_product_after_tabs. The tabs still come from
 * the woocommerce_product_tabs filter, so a store that unsets a tab hides that section and its chip.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

/**
 * Filter tabs and allow third parties to add their own.
 *
 * Each tab is an array containing title, callback and priority.
 *
 * @see woocommerce_default_product_tabs()
 */
$product_tabs = gadgetim_pdp_tabs( $product ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable name; the filtered woocommerce_product_tabs, cached.

$gadgetim_sections = gadgetim_pdp_sections( $product );
$gadgetim_specs    = gadgetim_pdp_specs( $product );
$gadgetim_faq      = gadgetim_pdp_faq( $product );
$gadgetim_attrs    = gadgetim_pdp_content_attrs();
$gadgetim_title    = gadgetim_digits( gadgetim_short_title( $product ) );
$gadgetim_handled  = [ 'description', 'additional_information', 'reviews' ];
$gadgetim_stats    = gadgetim_pdp_review_stats( $product );
?>
<div class="pdp-secs woocommerce-tabs wc-tabs-wrapper">

	<?php if ( isset( $gadgetim_sections['tab-description'], $product_tabs['description'] ) ) : ?>
		<section class="pdp-sec pdp-sec--desc woocommerce-Tabs-panel woocommerce-Tabs-panel--description" id="tab-description" aria-labelledby="tab-description-title" tabindex="-1">
			<?php
			gadgetim_the_section_head(
				[
					'title' => $gadgetim_sections['tab-description'],
					/* translators: %s: product title */
					'sub'   => sprintf( __( 'Get to know %s', 'gadgetim' ), $gadgetim_title ),
					'id'    => 'tab-description-title',
					'icon'  => 'sparkle',
					'tone'  => 'violet',
				]
			);
			?>
			<div class="pdp-card pdp-desc" data-clamp>
				<div class="pdp-desc__body prose" id="pdp-desc-body" data-clamp-body<?php echo $gadgetim_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
					<?php
					if ( isset( $product_tabs['description']['callback'] ) ) {
						ob_start();
						call_user_func( $product_tabs['description']['callback'], 'description', $product_tabs['description'] );
						echo gadgetim_pdp_html_digits( (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content() output; digits converted in text nodes only.
					}
					?>
				</div>
				<div class="pdp-desc__more">
					<button class="btn btn--soft btn--sm pdp-desc__btn" type="button" data-clamp-btn aria-expanded="false" aria-controls="pdp-desc-body" hidden><span data-clamp-label><?php esc_html_e( 'Read more', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'chev-d' ); ?></button>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( isset( $gadgetim_sections['tab-specs'] ) && $gadgetim_specs ) : ?>
		<section class="pdp-sec pdp-sec--specs woocommerce-Tabs-panel woocommerce-Tabs-panel--additional_information" id="tab-specs" aria-labelledby="tab-specs-title" tabindex="-1">
			<?php
			gadgetim_the_section_head(
				[
					'title' => $gadgetim_sections['tab-specs'],
					/* translators: %s: number of rows */
					'sub'   => sprintf( _n( '%s detail at a glance', '%s details at a glance', count( $gadgetim_specs ), 'gadgetim' ), gadgetim_number( count( $gadgetim_specs ) ) ),
					'id'    => 'tab-specs-title',
					'icon'  => 'layers',
					'tone'  => 'cool',
				]
			);
			?>
			<div class="pdp-card pdp-specs" data-reveal>
				<table class="specs woocommerce-product-attributes shop_attributes"<?php echo $gadgetim_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
					<?php /* translators: %s: product title */ ?>
					<caption class="sr-only"><?php echo esc_html( sprintf( __( 'Specifications of %s', 'gadgetim' ), $gadgetim_title ) ); ?></caption>
					<tbody>
						<?php foreach ( $gadgetim_specs as $gadgetim_i => $gadgetim_row ) : ?>
							<tr style="--i:<?php echo esc_attr( (string) min( $gadgetim_i, 12 ) ); ?>">
								<th scope="row"><?php echo gadgetim_pdp_text( $gadgetim_row[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></th>
								<td><?php echo gadgetim_pdp_text( $gadgetim_row[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
				/**
				 * Hook: woocommerce_product_additional_information (the default attribute table is
				 * rendered above as rows; the hook stays for third-party additions).
				 */
				do_action( 'woocommerce_product_additional_information', $product );
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( isset( $gadgetim_sections['tab-reviews'], $product_tabs['reviews'] ) ) : ?>
		<section class="pdp-sec pdp-sec--reviews woocommerce-Tabs-panel woocommerce-Tabs-panel--reviews" id="tab-reviews" aria-labelledby="tab-reviews-title" tabindex="-1">
			<?php
			gadgetim_the_section_head(
				[
					'title' => $gadgetim_sections['tab-reviews'],
					'sub'   => $gadgetim_stats['count'] > 0
						/* translators: %s: number of reviews */
						? sprintf( _n( '%s customer shared an opinion', '%s customers shared their opinion', $gadgetim_stats['count'], 'gadgetim' ), gadgetim_number( $gadgetim_stats['count'] ) )
						: __( 'What buyers say about this product', 'gadgetim' ),
					'id'    => 'tab-reviews-title',
					'icon'  => 'star',
					'tone'  => 'gold',
				]
			);
			if ( isset( $product_tabs['reviews']['callback'] ) ) {
				call_user_func( $product_tabs['reviews']['callback'], 'reviews', $product_tabs['reviews'] );
			}
			?>
		</section>
	<?php endif; ?>

	<?php if ( isset( $gadgetim_sections['tab-faq'] ) && $gadgetim_faq['rows'] ) : ?>
		<section class="pdp-sec pdp-sec--faq" id="tab-faq" aria-labelledby="tab-faq-title" tabindex="-1">
			<?php
			gadgetim_the_section_head(
				[
					'title' => $gadgetim_sections['tab-faq'],
					'sub'   => $gadgetim_faq['own'] ? __( 'Answers about this product', 'gadgetim' ) : __( 'Answers to what shoppers ask us most', 'gadgetim' ),
					'id'    => 'tab-faq-title',
					'icon'  => 'info',
					'tone'  => 'pink',
				]
			);
			?>
			<div class="pdp-card pdp-faq" data-reveal>
				<ul class="acc pdp-faq__list" data-accordion="multi"<?php echo $gadgetim_faq['own'] ? $gadgetim_attrs : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
					<?php foreach ( $gadgetim_faq['rows'] as $gadgetim_i => $gadgetim_row ) : ?>
						<li>
							<button class="acc__btn" type="button" aria-expanded="false" aria-controls="pdp-faq-<?php echo esc_attr( (string) $gadgetim_i ); ?>">
								<span class="acc__ic" aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_i + 1 ) ); ?></span>
								<span class="acc__label"><?php echo gadgetim_pdp_text( (string) $gadgetim_row['q'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span>
								<?php gadgetim_the_icon( 'chev-d', 'acc__chev' ); ?>
							</button>
							<div class="acc__panel" id="pdp-faq-<?php echo esc_attr( (string) $gadgetim_i ); ?>" inert>
								<div><div class="acc__text"><?php echo gadgetim_pdp_html_digits( wp_kses_post( wpautop( (string) $gadgetim_row['a'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output, digits in text nodes only. ?></div></div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php $gadgetim_ask = gadgetim_whatsapp_url( sprintf( /* translators: %s: product title */ __( 'Hello, I have a question about “%s”.', 'gadgetim' ), $gadgetim_title ) . ' ' . $product->get_permalink() ); ?>
				<?php if ( '' !== $gadgetim_ask ) : ?>
					<div class="pdp-faq__ask">
						<span><?php gadgetim_the_icon( 'chat' ); ?><?php esc_html_e( 'Still have a question? We answer on WhatsApp.', 'gadgetim' ); ?></span>
						<a class="btn btn--sm buy__wa" href="<?php echo esc_url( $gadgetim_ask ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Ask us', 'gadgetim' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php foreach ( $product_tabs as $key => $product_tab ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable names. ?>
		<?php
		if ( in_array( $key, $gadgetim_handled, true ) ) {
			continue;
		}
		$gadgetim_key = sanitize_html_class( (string) $key );
		?>
		<section class="pdp-sec pdp-sec--extra woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $gadgetim_key ); ?>" id="tab-<?php echo esc_attr( $gadgetim_key ); ?>" aria-labelledby="tab-title-<?php echo esc_attr( $gadgetim_key ); ?>" tabindex="-1">
			<?php
			gadgetim_the_section_head(
				[
					/** This filter is documented in woocommerce/templates/single-product/tabs/tabs.php */
					'title' => wp_strip_all_tags( (string) apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'] ?? '', $key ) ),
					'id'    => 'tab-title-' . $gadgetim_key,
					'icon'  => 'info',
					'tone'  => 'green',
				]
			);
			?>
			<div class="pdp-card prose">
				<?php
				if ( isset( $product_tab['callback'] ) ) {
					call_user_func( $product_tab['callback'], $key, $product_tab );
				}
				?>
			</div>
		</section>
	<?php endforeach; ?>

	<?php do_action( 'woocommerce_product_after_tabs' ); ?>
</div>
