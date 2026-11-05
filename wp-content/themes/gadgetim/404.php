<?php
/**
 * 404: a "low battery" scene (the 0 of 404 is a charging ring with a flickering bolt, an unplugged
 * cable swings above it, lost gadgets float around), a product search, home/shop buttons, the
 * popular categories and the popular products. Pure CSS animation; calm under reduced motion.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

$gadgetim_four  = gadgetim_digits( '4' );
$gadgetim_spark = '<path d="M12 1c1 7 4 10 11 11-7 1-10 4-11 11-1-7-4-10-11-11 7-1 10-4 11-11Z"/>';
?>
<div class="container nf-shell">
	<section class="nf" aria-labelledby="nf-t">
		<div class="nf__fx" aria-hidden="true">
			<i class="nf__orb o1"></i><i class="nf__orb o2"></i><i class="nf__orb o3"></i>
			<svg class="nf__spark s1" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
			<svg class="nf__spark s2" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
			<svg class="nf__spark s3" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		</div>

		<div class="nf__art" aria-hidden="true">
			<svg class="nf__plug" viewBox="0 0 120 190" focusable="false">
				<defs>
					<linearGradient id="nf-c" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a5b4fc"/><stop offset="1" stop-color="#7c3aed"/></linearGradient>
					<linearGradient id="nf-h" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset=".6" stop-color="#9333ea"/><stop offset="1" stop-color="#ec4899"/></linearGradient>
				</defs>
				<g class="nf__swing">
					<path d="M60 0C60 46 28 64 60 112" fill="none" stroke="url(#nf-c)" stroke-width="6" stroke-linecap="round"/>
					<rect x="42" y="108" width="36" height="34" rx="10" fill="url(#nf-h)"/>
					<rect x="49" y="117" width="22" height="4" rx="2" fill="#fff" opacity=".45"/>
					<rect x="49" y="141" width="7" height="18" rx="2.5" fill="#c4b5fd"/>
					<rect x="64" y="141" width="7" height="18" rx="2.5" fill="#c4b5fd"/>
					<g class="nf__zap" fill="#fbbf24">
						<path d="M44 170l5 7-6 0 4 8"/>
						<path d="M60 168l2 9-5-1 3 9" />
						<path d="M76 170l-5 7 6 0-4 8"/>
					</g>
				</g>
			</svg>
			<div class="nf__num" dir="ltr">
				<span class="nf__d"><?php echo esc_html( $gadgetim_four ); ?></span>
				<span class="nf__zero">
					<svg class="nf__ring" viewBox="0 0 120 120" focusable="false">
						<defs><linearGradient id="nf-r" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f43f5e"/><stop offset="1" stop-color="#f59e0b"/></linearGradient></defs>
						<circle class="nf__track" cx="60" cy="60" r="46"/>
						<circle class="nf__low" cx="60" cy="60" r="46" pathLength="100" stroke="url(#nf-r)"/>
						<circle class="nf__dash" cx="60" cy="60" r="57"/>
					</svg>
					<svg class="nf__bolt" viewBox="0 0 24 24" focusable="false"><path d="M13.2 3 5.4 13.4h6.1L10.8 21l7.8-10.4h-6.1L13.2 3Z"/></svg>
				</span>
				<span class="nf__d"><?php echo esc_html( $gadgetim_four ); ?></span>
			</div>
			<span class="nf__float f1"><?php gadgetim_the_icon( 'headphones' ); ?></span>
			<span class="nf__float f2"><?php gadgetim_the_icon( 'watch' ); ?></span>
			<span class="nf__float f3"><?php gadgetim_the_icon( 'speaker' ); ?></span>
			<span class="nf__float f4"><?php gadgetim_the_icon( 'mobile' ); ?></span>
		</div>

		<div class="nf__text">
			<p class="nf__kicker"><span class="nf__batt" aria-hidden="true"><i></i></span><?php /* translators: %s: the error code (404) in local digits */ echo esc_html( sprintf( __( 'Error %s · low battery', 'gadgetim' ), gadgetim_number( 404 ) ) ); ?></p>
			<h1 class="nf__t" id="nf-t"><?php esc_html_e( 'This page could not be found', 'gadgetim' ); ?></h1>
			<p class="nf__p"><?php esc_html_e( 'Looks like this page ran out of charge. The link may be old or mistyped. Search for what you need, or start again from the home page.', 'gadgetim' ); ?></p>
			<?php
			gadgetim_part(
				'pages/search-form',
				[
					'id'    => 'nf-q',
					'scope' => 'products',
					'class' => 'psearch--nf',
				]
			);
			?>
			<div class="nf__acts">
				<?php
				echo gadgetim_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					__( 'Back to the home page', 'gadgetim' ),
					[
						'href'       => home_url( '/' ),
						'icon'       => 'home',
						'icon_start' => true,
					]
				);
				echo gadgetim_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					__( 'Browse the shop', 'gadgetim' ),
					[
						'href'    => gadgetim_shop_url(),
						'variant' => 'outline',
						'icon'    => 'arrow',
					]
				);
				?>
			</div>
		</div>
	</section>

	<?php
	gadgetim_part(
		'pages/stories',
		[
			'id'     => 'nf-cats',
			'reveal' => false,
		]
	);
	?>
	<?php gadgetim_part( 'pages/popular', [ 'id' => 'nf-best' ] ); ?>
</div>
<?php
get_footer();
