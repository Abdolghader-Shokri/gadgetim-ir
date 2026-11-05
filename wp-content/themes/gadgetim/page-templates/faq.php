<?php
/**
 * Template Name: FAQ
 * Template Post Type: page
 *
 * Frequently asked questions: a hero with a live search box, the page's <details> questions as a
 * numbered accordion (topics from the headings between them, deep links, highlighted matches),
 * the intro and outro as prose, the help aside and a closing "still have a question?" band.
 * Falls back to the generic page body when the content holds no <details> question.
 * Used automatically by the page with _gtm_page_role = faq.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	$gadgetim_post = get_post();
	$gadgetim_faq  = post_password_required( $gadgetim_post ) ? null : gadgetim_pages_faq( gadgetim_pages_post_html( $gadgetim_post ) );
	if ( ! $gadgetim_faq || $gadgetim_faq['count'] < 1 ) {
		gadgetim_part( 'pages/page-body', [ 'post' => $gadgetim_post ] );
		continue;
	}
	$gadgetim_query = isset( $_GET['faq'] ) ? mb_substr( sanitize_text_field( wp_unslash( (string) $_GET['faq'] ) ), 0, 64 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.

	ob_start();
	gadgetim_part(
		'pages/faq-search',
		[
			'controls' => 'faq',
			'value'    => $gadgetim_query,
		]
	);
	$gadgetim_search = (string) ob_get_clean();

	$gadgetim_qmark = is_rtl() ? '؟' : '?';
	$gadgetim_art   = '<span class="faqart"><span class="faqart__b faqart__b--q">' . esc_html( $gadgetim_qmark ) . '</span>'
		. '<span class="faqart__b faqart__b--chat">' . gadgetim_icon( 'chat' ) . '</span>'
		. '<span class="faqart__b faqart__b--ok">' . gadgetim_icon( 'check' ) . '</span>'
		. '<i class="faqart__dot d1"></i><i class="faqart__dot d2"></i><i class="faqart__dot d3"></i></span>';
	?>
	<div class="container pg-shell faqpg-shell">
		<?php gadgetim_breadcrumbs(); ?>
		<article <?php post_class( 'pg-art faqpg-art' ); ?> aria-labelledby="page-title">
			<?php
			gadgetim_part(
				'pages/hero',
				[
					'title'  => get_the_title(),
					'kicker' => __( 'Help center', 'gadgetim' ),
					'lead'   => has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : __( 'Quick answers about authenticity, ordering, delivery and returns.', 'gadgetim' ),
					'icon'   => 'question',
					'tone'   => 'violet',
					'size'   => 'lg',
					'chips'  => [
						[
							'icon' => 'question',
							/* translators: %s: number of questions */
							'text' => sprintf( _n( '%s question', '%s questions', $gadgetim_faq['count'], 'gadgetim' ), gadgetim_number( $gadgetim_faq['count'] ) ),
						],
					],
					'art'    => $gadgetim_art,
					'after'  => $gadgetim_search,
					'class'  => 'phero--faq',
				]
			);
			?>
			<div class="pg faqpg">
				<div class="pg__main">
					<?php if ( '' !== trim( wp_strip_all_tags( $gadgetim_faq['intro'] ) ) ) : ?>
						<div class="faqpg__intro prose"><?php echo wp_kses_post( $gadgetim_faq['intro'] ); ?></div>
					<?php endif; ?>
					<?php
					gadgetim_part(
						'pages/faq-list',
						[
							'faq'    => $gadgetim_faq,
							'id'     => 'faq',
							'search' => true,
							'query'  => $gadgetim_query,
							'open'   => true,
						]
					);
					?>
					<?php if ( '' !== trim( wp_strip_all_tags( $gadgetim_faq['outro'] ) ) ) : ?>
						<div class="faqpg__outro prose"><?php echo wp_kses_post( $gadgetim_faq['outro'] ); ?></div>
					<?php endif; ?>
				</div>
				<aside class="pg__aside" aria-label="<?php esc_attr_e( 'Help and related pages', 'gadgetim' ); ?>">
					<?php
					gadgetim_part(
						'pages/help',
						[
							'title' => __( 'Did not find your answer?', 'gadgetim' ),
							'text'  => __( 'Ask us directly. We answer questions about products, orders and authenticity.', 'gadgetim' ),
						]
					);
					?>
				</aside>
			</div>
		</article>
		<?php gadgetim_part( 'pages/cta' ); ?>
	</div>
	<?php
endwhile;

get_footer();
