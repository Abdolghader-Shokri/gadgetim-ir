<?php
/**
 * Template Name: About
 * Template Post Type: page
 *
 * About page: a hero with two tilted packshot tiles, the brand monogram and the 100 % sticker, the content as story
 * chapters (split at its h2 headings) with product collages, the content's numeric highlights as
 * counting tiles (live catalogue numbers when the content has none), the promises (trust badges),
 * the brands we carry and a closing band. Used automatically by the page with _gtm_page_role = about.
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
	$gadgetim_about   = gadgetim_pages_about( gadgetim_pages_post_html( $gadgetim_post ) );
	$gadgetim_popular = gadgetim_pages_popular_ids( 8 );
	$gadgetim_tagline = trim( wp_strip_all_tags( gadgetim_t( 'brand.tagline' ) ) );
	$gadgetim_contact = gadgetim_pages_role_url( 'contact' );

	// Hero art: two tilted white tiles with popular packshots (white-background images preferred,
	// photos cropped rather than notched), the brand monogram on a glass card and a sticker — the same language as the home banners and the story chapters below.
	$gadgetim_tile = static function ( int $pid, string $mod ): string {
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			return '';
		}
		return '<span class="hcol__tile hcol__tile--' . esc_attr( $mod ) . '">' . gadgetim_decor_image(
			gadgetim_decor_pick( $product, [ 0, 1, 2 ] ),
			'gtm-card',
			[
				'sizes'         => 'a' === $mod ? '(min-width: 1000px) 210px, 120px' : '(min-width: 1000px) 160px, 96px',
				'loading'       => 'eager',
				'fetchpriority' => 'auto',
				'crop'          => 'auto',
			]
		) . '</span>';
	};
	$gadgetim_art = '<span class="hcol"><i class="hcol__glow"></i>'
		. ( isset( $gadgetim_popular[0] ) ? $gadgetim_tile( $gadgetim_popular[0], 'a' ) : '' )
		. ( isset( $gadgetim_popular[1] ) ? $gadgetim_tile( $gadgetim_popular[1], 'b' ) : '' )
		. '<span class="hcol__mono">' . gadgetim_monogram( 'ab', 'hcol__mark' ) . '</span>'
		// The chapters below already carry «۱۰۰٪ اصل»: the hero sticker shows the return window instead.
		. ( gadgetim_pages_return_days() > 0
			? gadgetim_sticker( gadgetim_number( gadgetim_pages_return_days() ), '', __( 'days to return', 'gadgetim' ), 'hcol__sticker' )
			: gadgetim_sticker( gadgetim_percent( 100 ), '', __( 'original', 'gadgetim' ), 'hcol__sticker' ) )
		. '<i class="hcol__dot d1"></i><i class="hcol__dot d2"></i></span>';

	$gadgetim_actions = [
		[
			'label'   => __( 'Browse the shop', 'gadgetim' ),
			'href'    => gadgetim_shop_url(),
			'variant' => 'white',
			'icon'    => 'arrow',
		],
	];
	if ( '' !== $gadgetim_contact ) {
		$gadgetim_actions[] = [
			'label'      => __( 'Contact us', 'gadgetim' ),
			'href'       => $gadgetim_contact,
			'class'      => 'phero__glass',
			'icon'       => 'phone',
			'icon_start' => true,
		];
	}

	// Numbers: the content's own highlights, else the live catalogue.
	$gadgetim_stats = [];
	$gadgetim_icons = [ 'box', 'user', 'truck', 'star', 'tag' ];
	foreach ( $gadgetim_about['highlights'] as $gadgetim_i => $gadgetim_h ) {
		$gadgetim_stats[] = [
			'icon'   => $gadgetim_icons[ $gadgetim_i % count( $gadgetim_icons ) ],
			'n'      => $gadgetim_h['n'],
			'suffix' => $gadgetim_h['suffix'],
			'label'  => $gadgetim_h['label'],
			'text'   => $gadgetim_h['text'],
		];
	}
	$gadgetim_live = ! $gadgetim_stats;
	if ( $gadgetim_live ) {
		$gadgetim_counts = gadgetim_pages_catalog_counts();
		$gadgetim_stats  = [
			[
				'icon'  => 'box',
				'n'     => $gadgetim_counts['products'],
				'label' => __( 'original products', 'gadgetim' ),
			],
			[
				'icon'  => 'tag',
				'n'     => $gadgetim_counts['brands'],
				'label' => __( 'trusted brands', 'gadgetim' ),
			],
			[
				'icon'  => 'layers',
				'n'     => $gadgetim_counts['categories'],
				'label' => __( 'categories', 'gadgetim' ),
			],
			[
				'icon'  => 'return',
				'n'     => gadgetim_pages_return_days(),
				'label' => __( 'day return guarantee', 'gadgetim' ),
			],
		];
	}
	?>
	<div class="container pg-shell apg-shell">
		<?php gadgetim_breadcrumbs(); ?>
		<article <?php post_class( 'pg-art apg' ); ?> aria-labelledby="page-title">
			<?php
			gadgetim_part(
				'pages/hero',
				[
					'title'   => get_the_title(),
					'kicker'  => gadgetim_brand_name(),
					'lead'    => has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : ( '' !== $gadgetim_tagline ? $gadgetim_tagline : __( 'An online shop for original, special and useful gadgets.', 'gadgetim' ) ),
					'icon'    => 'sparkle',
					'tone'    => 'night',
					'size'    => 'lg',
					'actions' => $gadgetim_actions,
					'art'     => $gadgetim_art,
					'class'   => 'phero--about',
				]
			);
			?>

			<?php foreach ( $gadgetim_about['chapters'] as $gadgetim_i => $gadgetim_chapter ) : ?>
				<?php
				$gadgetim_pick = array_slice( $gadgetim_popular, 3 + $gadgetim_i * 2, 2 );
				$gadgetim_cid  = 'achap-' . ( $gadgetim_i + 1 );
				?>
				<section class="achap<?php echo 1 === $gadgetim_i % 2 ? ' achap--flip' : ''; ?>"<?php echo '' !== $gadgetim_chapter['title'] ? ' aria-labelledby="' . esc_attr( $gadgetim_cid ) . '"' : ''; ?>>
					<div class="achap__text">
						<?php if ( '' !== $gadgetim_chapter['title'] ) : ?>
							<p class="achap__kicker"><?php echo gadgetim_pages_icon( 0 === $gadgetim_i ? 'sparkle' : 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( 0 === $gadgetim_i ? __( 'Our story', 'gadgetim' ) : gadgetim_brand_name() ); ?></span></p>
							<h2 class="achap__t" id="<?php echo esc_attr( $gadgetim_cid ); ?>"><?php echo gadgetim_bidi( $gadgetim_chapter['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></h2>
						<?php endif; ?>
						<div class="prose achap__prose"><?php echo $gadgetim_chapter['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output. ?></div>
					</div>
					<div class="achap__art" aria-hidden="true">
						<span class="achap__plate"></span>
						<?php foreach ( $gadgetim_pick as $gadgetim_k => $gadgetim_pid ) : ?>
							<?php $gadgetim_product = wc_get_product( $gadgetim_pid ); ?>
							<?php if ( $gadgetim_product ) : ?>
								<span class="achap__tile achap__tile--<?php echo 0 === $gadgetim_k ? 'a' : 'b'; ?>">
									<?php
									echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
										$gadgetim_product,
										'gtm-card',
										[
											'index'   => 1,
											'sizes'   => '(min-width: 1100px) 260px, 42vw',
											'loading' => ( 0 === $gadgetim_i && 0 === $gadgetim_k ) ? 'eager' : 'lazy',
										]
									);
									?>
								</span>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php echo gadgetim_sticker( gadgetim_percent( 100 ), '', __( 'original', 'gadgetim' ), 'sticker--lg achap__sticker' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					</div>
				</section>
			<?php endforeach; ?>

			<section class="sect apg__nums" aria-labelledby="apg-nums-t">
				<?php
				gadgetim_the_section_head(
					[
						'title' => $gadgetim_live ? __( 'Gadgetim today', 'gadgetim' ) : __( 'Gadgetim in numbers', 'gadgetim' ),
						'sub'   => $gadgetim_live ? __( 'Live from our showcase right now', 'gadgetim' ) : __( 'The trust of our customers is our biggest asset', 'gadgetim' ),
						'id'    => 'apg-nums-t',
						'icon'  => 'trend',
						'tone'  => 'pink',
					]
				);
				gadgetim_part( 'pages/stats', [ 'items' => $gadgetim_stats ] );
				?>
			</section>

			<?php gadgetim_part( 'pages/values' ); ?>
			<?php gadgetim_part( 'pages/brands' ); ?>
		</article>
		<?php
		gadgetim_part(
			'pages/cta',
			[
				'title' => '' !== $gadgetim_about['closing'] ? $gadgetim_about['closing'] : __( 'Ask us for the best', 'gadgetim' ),
				'text'  => __( 'Not sure which gadget is right for you? Tell us what you need and we will help you choose.', 'gadgetim' ),
				'icon'  => 'sparkle',
			]
		);
		?>
	</div>
	<?php
endwhile;

get_footer();
