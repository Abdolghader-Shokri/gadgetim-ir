<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * Contact page: a hero with WhatsApp and call buttons, the channel cards from the settings
 * (contact.phone / whatsapp / telegram / instagram / email / address / hours), the contact form
 * (e-mails the site admin), the page's own content as "more information", and three quick
 * answers from the FAQ page. Used automatically by the page with _gtm_page_role = contact.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	$gadgetim_post      = get_post();
	$gadgetim_protected = post_password_required( $gadgetim_post );
	$gadgetim_html      = $gadgetim_protected ? '' : gadgetim_pages_post_html( $gadgetim_post );
	$gadgetim_prep      = gadgetim_pages_prepare( $gadgetim_html, get_the_title() );
	$gadgetim_wa        = gadgetim_whatsapp_url( __( 'Hello! I have a question for Gadgetim.', 'gadgetim' ) );
	$gadgetim_tel       = gadgetim_tel_href();
	$gadgetim_faq       = gadgetim_pages_faq_pick( gadgetim_pages_faq_of_page(), [], 3 );
	$gadgetim_faq_url   = gadgetim_pages_role_url( 'faq' );
	$gadgetim_actions   = [];
	if ( '' !== $gadgetim_wa ) {
		$gadgetim_actions[] = [
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
	if ( '' !== $gadgetim_tel ) {
		$gadgetim_actions[] = [
			'label'      => __( 'Call us', 'gadgetim' ),
			'href'       => $gadgetim_tel,
			'class'      => 'phero__glass',
			'icon'       => 'phone',
			'icon_start' => true,
		];
	}
	$gadgetim_art = '<span class="chatart">'
		. '<span class="chatart__b chatart__b--in"><i></i><i></i><i></i></span>'
		. '<span class="chatart__b chatart__b--out">' . gadgetim_icon( 'sparkle' ) . esc_html__( 'Hello!', 'gadgetim' ) . '</span>'
		. '<span class="chatart__b chatart__b--in2">' . gadgetim_icon( 'heart' ) . '</span>'
		. '<span class="chatart__phone">' . gadgetim_icon( 'phone' ) . '</span></span>';
	?>
	<div class="container pg-shell cpg-shell">
		<?php gadgetim_breadcrumbs(); ?>
		<article <?php post_class( 'pg-art cpg' ); ?> aria-labelledby="page-title">
			<?php
			gadgetim_part(
				'pages/hero',
				[
					'title'   => get_the_title(),
					'kicker'  => __( 'Customer service', 'gadgetim' ),
					'lead'    => has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : __( 'A question about a product, an order or authenticity? Message us or call: a real person from our team will answer.', 'gadgetim' ),
					'icon'    => 'headphones',
					'tone'    => 'green',
					'size'    => 'lg',
					'actions' => $gadgetim_actions,
					'art'     => $gadgetim_art,
					'class'   => 'phero--contact',
				]
			);
			?>
			<?php gadgetim_part( 'pages/contact-cards', [ 'class' => 'cpg__cards' ] ); ?>

			<div class="cpg__grid">
				<?php gadgetim_part( 'pages/contact-form' ); ?>
				<div class="cpg__side">
					<?php if ( '' !== trim( wp_strip_all_tags( $gadgetim_prep['html'] ) ) || $gadgetim_protected ) : ?>
						<section class="cpg__more" aria-labelledby="cpg-more-t">
							<h2 class="cpg__st" id="cpg-more-t"><span class="cpg__sic" aria-hidden="true"><?php gadgetim_the_icon( 'info' ); ?></span><?php esc_html_e( 'More about us', 'gadgetim' ); ?></h2>
							<div class="prose cpg__prose">
								<?php echo $gadgetim_protected ? get_the_password_form() : $gadgetim_prep['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output / core form. ?>
							</div>
						</section>
					<?php endif; ?>
					<?php if ( $gadgetim_faq ) : ?>
						<section class="cpg__faq" aria-labelledby="cpg-faq-t">
							<h2 class="cpg__st" id="cpg-faq-t"><span class="cpg__sic cpg__sic--q" aria-hidden="true"><?php echo gadgetim_pages_icon( 'question' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php esc_html_e( 'Quick answers', 'gadgetim' ); ?></h2>
							<?php
							gadgetim_part(
								'pages/faq-list',
								[
									'items'   => $gadgetim_faq,
									'id'      => 'cpg-faq',
									'numbers' => false,
									'class'   => 'faq--compact',
								]
							);
							?>
							<?php if ( '' !== $gadgetim_faq_url ) : ?>
								<a class="cpg__all" href="<?php echo esc_url( $gadgetim_faq_url ); ?>"><span><?php esc_html_e( 'All frequently asked questions', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
							<?php endif; ?>
						</section>
					<?php endif; ?>
				</div>
			</div>
		</article>
	</div>
	<?php
endwhile;

get_footer();
