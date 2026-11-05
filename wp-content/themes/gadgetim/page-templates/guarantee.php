<?php
/**
 * Template Name: Authenticity guarantee
 * Template Post Type: page
 *
 * «ضمانت اصالت کالا»: a hero with an animated seal, the promises (trust.badges), the verification
 * steps (trust.verification_steps) as a numbered path, the page's own text with the help aside,
 * the guarantee questions from the FAQ page and a closing band. The page is created by
 * gadgetim_pages_install() (inc/pages.php) and linked from trust.guarantee_page.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	$gadgetim_post = get_post();
	if ( post_password_required( $gadgetim_post ) ) {
		gadgetim_part( 'pages/page-body', [ 'post' => $gadgetim_post ] );
		continue;
	}
	$gadgetim_prep    = gadgetim_pages_prepare( gadgetim_pages_post_html( $gadgetim_post ), get_the_title() );
	$gadgetim_promise = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( gadgetim_t( 'trust.guarantee' ) ) ) );
	$gadgetim_wa      = gadgetim_whatsapp_url( __( 'Hello! I have a question about the authenticity of a product.', 'gadgetim' ) );
	$gadgetim_contact = gadgetim_pages_role_url( 'contact' );
	$gadgetim_faq     = gadgetim_pages_faq_pick( gadgetim_pages_faq_of_page(), [ 'اصالت', 'ضمانت', 'گارانتی', 'اورجینال', 'genuine', 'guarantee', 'warranty', 'original', 'ضمان', 'أصلي' ], 4 );
	$gadgetim_faq_url = gadgetim_pages_role_url( 'faq' );

	$gadgetim_chips = [];
	foreach ( [ 'trust.return_window' => 'return', 'trust.delivery' => 'truck' ] as $gadgetim_path => $gadgetim_icon ) {
		$gadgetim_text = trim( wp_strip_all_tags( gadgetim_t( $gadgetim_path ) ) );
		if ( '' !== $gadgetim_text ) {
			$gadgetim_chips[] = [
				'icon' => $gadgetim_icon,
				'text' => $gadgetim_text,
			];
		}
	}

	$gadgetim_actions = [
		[
			'label'   => __( 'Browse the shop', 'gadgetim' ),
			'href'    => gadgetim_shop_url(),
			'variant' => 'white',
			'icon'    => 'arrow',
		],
	];
	if ( '' !== $gadgetim_wa ) {
		$gadgetim_actions[] = [
			'label'      => __( 'Ask about a product', 'gadgetim' ),
			'href'       => $gadgetim_wa,
			'class'      => 'phero__glass',
			'icon'       => 'chat',
			'icon_start' => true,
			'attrs'      => [
				'target' => '_blank',
				'rel'    => 'noopener',
			],
		];
	}

	// The seal: a turning ring of words, a scalloped badge, a shield and a check that draws itself.
	$gadgetim_seal = '<span class="gseal"><svg class="gseal__svg" viewBox="0 0 220 220" focusable="false">'
		. '<defs><linearGradient id="gs-g" x1="40" y1="30" x2="180" y2="190" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#34d399"/><stop offset=".5" stop-color="#0d9488"/><stop offset="1" stop-color="#4f46e5"/></linearGradient>'
		. '<linearGradient id="gs-s" x1="70" y1="60" x2="150" y2="170" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#f59e0b"/></linearGradient>'
		. '<path id="gs-arc" d="M110 110m-86 0a86 86 0 1 1 172 0a86 86 0 1 1-172 0"/></defs>'
		. '<g class="gseal__spin"><circle cx="110" cy="110" r="104" fill="none" stroke="rgba(255,255,255,.45)" stroke-width="1.5" stroke-dasharray="2 8"/>'
		. '<text class="gseal__words" direction="ltr"><textPath href="#gs-arc" textLength="530" lengthAdjust="spacing">GENUINE • ORIGINAL • GADGETIM • AUTHENTIC •</textPath></text></g>'
		. '<path class="gseal__badge" d="M110 49l13 11 17-3 7 16 16 7-3 17 11 13-11 13 3 17-16 7-7 16-17-3-13 11-13-11-17 3-7-16-16-7 3-17-11-13 11-13-3-17 16-7 7-16 17 3Z" fill="#fff"/>'
		. '<path class="gseal__shield" d="M110 69 78 82v25c0 20 13.6 37 32 44 18.4-7 32-24 32-44V82Z" fill="url(#gs-g)"/>'
		. '<path class="gseal__check" pathLength="1" d="m94 111 11 11 21-22" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<circle class="gseal__dot" cx="160" cy="62" r="9" fill="url(#gs-s)"/></svg>'
		. '<i class="gseal__glow"></i></span>';

	// Verification steps: the setting's lines; the default lines get a sentence of explanation.
	$gadgetim_known = [
		'بسته‌بندی پلمب'   => __( 'Make sure the box arrives sealed and undamaged before you open it.', 'gadgetim' ),
		'Sealed packaging' => __( 'Make sure the box arrives sealed and undamaged before you open it.', 'gadgetim' ),
		'شناسهٔ گارانتی'   => __( 'Compare the warranty or serial number on the box with the one on your invoice.', 'gadgetim' ),
		'Warranty ID'      => __( 'Compare the warranty or serial number on the box with the one on your invoice.', 'gadgetim' ),
		'فاکتور رسمی'      => __( 'Keep the official invoice: it is your proof of an original purchase.', 'gadgetim' ),
		'Official invoice' => __( 'Keep the official invoice: it is your proof of an original purchase.', 'gadgetim' ),
	];
	$gadgetim_desc  = [];
	foreach ( $gadgetim_known as $gadgetim_k => $gadgetim_v ) {
		$gadgetim_desc[ gadgetim_pages_norm( $gadgetim_k ) ] = $gadgetim_v;
	}
	$gadgetim_steps = [];
	$gadgetim_sicon = [ 'box', 'tag', 'receipt2', 'eye', 'sparkle' ];
	foreach ( gadgetim_list_setting( 'trust.verification_steps' ) as $gadgetim_i => $gadgetim_title ) {
		$gadgetim_steps[] = [
			'icon'  => $gadgetim_sicon[ min( $gadgetim_i, count( $gadgetim_sicon ) - 1 ) ],
			'title' => $gadgetim_title,
			'text'  => $gadgetim_desc[ gadgetim_pages_norm( $gadgetim_title ) ] ?? '',
		];
	}
	$gadgetim_steps[] = [
		'icon'  => 'chat',
		'title' => __( 'Still unsure? Ask us', 'gadgetim' ),
		'text'  => __( 'Send us a photo of the box, its labels and the invoice on WhatsApp before you use the product.', 'gadgetim' ),
	];
	$gadgetim_paths = gadgetim_pages_icon_paths();
	?>
	<div class="container pg-shell gpg-shell">
		<?php gadgetim_breadcrumbs(); ?>
		<article <?php post_class( 'pg-art gpg' ); ?> aria-labelledby="page-title">
			<?php
			gadgetim_part(
				'pages/hero',
				[
					'title'   => get_the_title(),
					'kicker'  => __( 'Shop with confidence', 'gadgetim' ),
					'lead'    => has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : ( '' !== $gadgetim_promise ? $gadgetim_promise : __( 'Every product at Gadgetim is genuine and original.', 'gadgetim' ) ),
					'icon'    => 'shield',
					'tone'    => 'green',
					'size'    => 'lg',
					'chips'   => $gadgetim_chips,
					'actions' => $gadgetim_actions,
					'art'     => $gadgetim_seal,
					'class'   => 'phero--guarantee',
				]
			);
			?>

			<?php
			gadgetim_part(
				'pages/values',
				[
					'title' => __( 'What the guarantee means', 'gadgetim' ),
					'sub'   => __( 'Promises that come with every order', 'gadgetim' ),
					'id'    => 'gpg-vals-t',
				]
			);
			?>

			<section class="sect gsteps" aria-labelledby="gpg-steps-t">
				<?php
				gadgetim_the_section_head(
					[
						'title' => __( 'Check it yourself', 'gadgetim' ),
						'sub'   => __( 'A few simple checks when your order arrives', 'gadgetim' ),
						'id'    => 'gpg-steps-t',
						'icon'  => 'check',
						'tone'  => 'cool',
					]
				);
				?>
				<ol class="gsteps__list">
					<?php foreach ( $gadgetim_steps as $gadgetim_i => $gadgetim_step ) : ?>
						<li class="gstep" data-reveal style="--i:<?php echo (int) $gadgetim_i; ?>">
							<span class="gstep__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_i + 1 ) ); ?></span>
							<span class="gstep__ic" aria-hidden="true"><svg class="pval__svg" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_paths[ $gadgetim_step['icon'] ] ?? $gadgetim_paths['sparkle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg></span>
							<h3 class="gstep__t"><?php echo gadgetim_bidi( (string) $gadgetim_step['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></h3>
							<?php if ( '' !== (string) $gadgetim_step['text'] ) : ?>
								<p class="gstep__p"><?php echo esc_html( (string) $gadgetim_step['text'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>

			<?php if ( '' !== trim( wp_strip_all_tags( $gadgetim_prep['html'] ) ) ) : ?>
				<section class="sect gpg__text" aria-labelledby="gpg-text-t">
					<h2 class="sr-only" id="gpg-text-t"><?php esc_html_e( 'The guarantee in detail', 'gadgetim' ); ?></h2>
					<div class="pg">
						<div class="pg__main">
							<div class="pg__body prose" data-reveal><?php echo $gadgetim_prep['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output. ?></div>
						</div>
						<aside class="pg__aside" aria-label="<?php esc_attr_e( 'Help and related pages', 'gadgetim' ); ?>">
							<?php
							gadgetim_part(
								'pages/help',
								[
									'title' => __( 'A doubt about a product?', 'gadgetim' ),
									'text'  => __( 'Ask us before you use it. We check every question about authenticity.', 'gadgetim' ),
									'id'    => 'gpg-help-t',
								]
							);
							?>
						</aside>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $gadgetim_faq ) : ?>
				<section class="sect gpg__faq" aria-labelledby="gpg-faq-t">
					<?php
					gadgetim_the_section_head(
						[
							'title'      => __( 'Questions about the guarantee', 'gadgetim' ),
							'id'         => 'gpg-faq-t',
							'icon'       => 'info',
							'tone'       => 'violet',
							'more_url'   => $gadgetim_faq_url,
							'more_label' => __( 'All questions', 'gadgetim' ),
						]
					);
					gadgetim_part(
						'pages/faq-list',
						[
							'items'   => $gadgetim_faq,
							'id'      => 'gpg-faq',
							'numbers' => false,
							'open'    => true,
						]
					);
					?>
				</section>
			<?php endif; ?>
		</article>
		<?php
		$gadgetim_cta = [];
		if ( '' !== $gadgetim_wa ) {
			$gadgetim_cta[] = [
				'label'      => __( 'Ask on WhatsApp', 'gadgetim' ),
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
		if ( '' !== $gadgetim_contact ) {
			$gadgetim_cta[] = [
				'label' => __( 'Send a message', 'gadgetim' ),
				'href'  => add_query_arg( 'topic', 'guarantee', $gadgetim_contact ) . '#contact-form',
				'class' => 'pcta__glass',
				'icon'  => 'arrow',
			];
		}
		gadgetim_part(
			'pages/cta',
			[
				'title'   => __( 'Not sure a product is genuine?', 'gadgetim' ),
				'text'    => __( 'Ask us before you buy or before you use it. We will look into it with you.', 'gadgetim' ),
				'icon'    => 'shield',
				'actions' => $gadgetim_cta,
			]
		);
		?>
	</div>
	<?php
endwhile;

get_footer();
