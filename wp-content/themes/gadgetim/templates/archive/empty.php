<?php
/**
 * Empty results: an illustrated empty shelf and the way out that fits the cause —
 * filters (clear them, ask on WhatsApp), a search without results (popular searches and
 * categories), a language section without translated products (the Persian catalogue), an empty
 * category (the shop) — then a rail of in-stock products from the same context or the shop.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_state = gadgetim_archive_state();
$gadgetim_info  = gadgetim_archive_info();
$gadgetim_kind  = (string) $gadgetim_info['kind'];
$gadgetim_wa    = gadgetim_whatsapp_url( __( 'Hello, I am looking for a product:', 'gadgetim' ) . ' ' . ( 'search' === $gadgetim_kind ? (string) $gadgetim_state['context']['s'] : (string) $gadgetim_info['name'] ) );
$gadgetim_total = (int) $gadgetim_state['facets']['total'];
$gadgetim_tips  = [];

$gadgetim_art = '<svg viewBox="0 0 160 140" aria-hidden="true" focusable="false"><defs><linearGradient id="ae-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset=".55" stop-color="#a855f7"/><stop offset="1" stop-color="#ec4899"/></linearGradient><linearGradient id="ae-s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4f1ff"/><stop offset="1" stop-color="#fdf2f8"/></linearGradient></defs>'
	. '<ellipse cx="80" cy="124" rx="58" ry="9" fill="#ede9fe"/>'
	. '<path d="M30 62h100v50a8 8 0 0 1-8 8H38a8 8 0 0 1-8-8V62Z" fill="url(#ae-s)" stroke="url(#ae-g)" stroke-width="3.5" stroke-linejoin="round"/>'
	. '<path d="M30 62 46 36h68l16 26" fill="#fff" stroke="url(#ae-g)" stroke-width="3.5" stroke-linejoin="round"/>'
	. '<path d="M62 84h36" stroke="url(#ae-g)" stroke-width="3.5" stroke-linecap="round"/>'
	. '<circle class="ae-q" cx="80" cy="22" r="11" fill="#fff" stroke="#f59e0b" stroke-width="3"/><path class="ae-q" d="M77 19.5a3.2 3.2 0 1 1 4.6 2.9c-1 .5-1.6 1.2-1.6 2.3" fill="none" stroke="#f59e0b" stroke-width="2.6" stroke-linecap="round"/>'
	. '<path d="M134 30c1 5 3 7 8 8-5 1-7 3-8 8-1-5-3-7-8-8 5-1 7-3 8-8Z" fill="#fbbf24"/><circle cx="22" cy="44" r="4" fill="#f9a8d4"/><circle cx="142" cy="96" r="3" fill="#a5b4fc"/></svg>';

if ( ! empty( $gadgetim_state['active'] ) ) {
	$gadgetim_title   = __( 'No product matches these filters', 'gadgetim' );
	$gadgetim_text    = __( 'Remove a filter or two, or widen the price range, and the list fills up again.', 'gadgetim' );
	$gadgetim_actions = [
		[
			'label' => __( 'Clear filters', 'gadgetim' ),
			'href'  => gadgetim_archive_url( [] ),
			'icon'  => 'trash',
			'attrs' => [ 'data-arch-link' => '' ],
		],
	];
} elseif ( 'search' === $gadgetim_kind ) {
	/* translators: %s: search query */
	$gadgetim_title   = gadgetim_isolate_query( sprintf( __( 'Nothing found for “%s”', 'gadgetim' ), (string) $gadgetim_state['context']['s'] ), (string) $gadgetim_state['context']['s'] );
	$gadgetim_text    = __( 'Check the spelling or try one of these categories.', 'gadgetim' );
	$gadgetim_actions = [
		[
			'label' => __( 'Browse the shop', 'gadgetim' ),
			'href'  => gadgetim_shop_url(),
			'icon'  => 'arrow',
		],
	];
	$gadgetim_tips = gadgetim_list_setting( 'theme.search_popular' );
} elseif ( 'fa' !== gadgetim_lang() && 0 === $gadgetim_total ) {
	$gadgetim_title   = __( 'This section is not available in your language yet', 'gadgetim' );
	$gadgetim_text    = __( 'The products are being translated. Meanwhile you can browse the Persian catalogue.', 'gadgetim' );
	$gadgetim_actions = [];
	$gadgetim_fa_url  = gadgetim_archive_persian_url();
	if ( '' !== $gadgetim_fa_url ) {
		$gadgetim_actions[] = [
			'label' => __( 'View the Persian catalogue', 'gadgetim' ),
			'href'  => $gadgetim_fa_url,
			'icon'  => 'globe',
			'attrs' => [ 'hreflang' => 'fa' ],
		];
	}
} else {
	$gadgetim_title   = __( 'This category is empty for now', 'gadgetim' );
	$gadgetim_text    = __( 'New original gadgets arrive every week. Ask us on WhatsApp and we will find it for you.', 'gadgetim' );
	$gadgetim_actions = [
		[
			'label' => __( 'Browse the shop', 'gadgetim' ),
			'href'  => gadgetim_shop_url(),
			'icon'  => 'arrow',
		],
	];
}
if ( '' !== $gadgetim_wa && ( 'fa' === gadgetim_lang() || $gadgetim_total > 0 ) ) {
	$gadgetim_actions[] = [
		'label'   => __( 'Ask on WhatsApp', 'gadgetim' ),
		'href'    => $gadgetim_wa,
		'icon'    => 'chat',
		'variant' => 'outline',
		'attrs'   => [
			'target' => '_blank',
			'rel'    => 'noopener',
		],
	];
}

// Suggestions: in-stock products of the same context with the biggest discounts, else the shop's.
$gadgetim_ids  = [];
$gadgetim_same = false;
if ( gadgetim_archive_filters_ready() ) {
	foreach ( [ 'same' => (array) $gadgetim_state['context'], 'shop' => [] ] as $gadgetim_which => $gadgetim_ctx ) {
		$gadgetim_rows = array_filter( Gadgetim\Core\Catalog\Filters::rows( $gadgetim_ctx ), static fn( array $r ): bool => 'outofstock' !== $r['stock'] );
		uasort( $gadgetim_rows, static fn( array $a, array $b ): int => (int) $b['off'] <=> (int) $a['off'] );
		$gadgetim_ids = array_slice( array_keys( $gadgetim_rows ), 0, 10 );
		if ( count( $gadgetim_ids ) >= 4 ) {
			$gadgetim_same = 'same' === $gadgetim_which && in_array( $gadgetim_kind, [ 'category', 'tag', 'brand' ], true );
			break;
		}
	}
}
if ( $gadgetim_same ) {
	$gadgetim_rail_t = 'brand' === $gadgetim_kind ? __( 'Best deals of this brand', 'gadgetim' ) : __( 'Best deals in this category', 'gadgetim' );
} else {
	$gadgetim_rail_t = __( 'Best deals right now', 'gadgetim' );
}
?>
<div class="aempty">
	<?php
	echo gadgetim_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		[
			'art'     => $gadgetim_art,
			'title'   => $gadgetim_title,
			'text'    => $gadgetim_text,
			'actions' => $gadgetim_actions,
			'class'   => 'aempty__state',
		]
	);
	?>
	<?php if ( $gadgetim_tips ) : ?>
		<div class="aempty__tips">
			<span class="aempty__tips-t"><?php esc_html_e( 'Popular searches', 'gadgetim' ); ?></span>
			<ul class="aempty__chips">
				<?php foreach ( array_slice( $gadgetim_tips, 0, 8 ) as $gadgetim_tip ) : ?>
					<li><a class="chip-q" href="<?php echo esc_url( add_query_arg( [ 's' => rawurlencode( $gadgetim_tip ), 'post_type' => 'product' ], home_url( '/' ) ) ); ?>"><?php gadgetim_the_icon( 'search' ); ?><?php echo esc_html( $gadgetim_tip ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<?php if ( 'search' === $gadgetim_kind ) : ?>
		<?php $gadgetim_groups = array_slice( gadgetim_category_groups(), 0, 8 ); ?>
		<?php if ( $gadgetim_groups ) : ?>
			<ul class="aempty__chips aempty__chips--cats">
				<?php foreach ( $gadgetim_groups as $gadgetim_group ) : ?>
					<li><a class="chip-q" href="<?php echo esc_url( (string) $gadgetim_group['url'] ); ?>"><?php gadgetim_the_icon( (string) $gadgetim_group['icon'] ); ?><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php if ( count( $gadgetim_ids ) >= 4 ) : ?>
	<section class="sect aempty__rail" aria-labelledby="aempty-rail-t">
		<?php
		gadgetim_the_section_head(
			[
				'title'  => $gadgetim_rail_t,
				'id'     => 'aempty-rail-t',
				'icon'   => 'flame',
				'tone'   => 'warm',
				'rail'   => 'aempty-rail',
				'reveal' => false,
			]
		);
		?>
		<ul class="rail prail" id="aempty-rail">
			<?php foreach ( $gadgetim_ids as $gadgetim_i => $gadgetim_id ) : ?>
				<?php
				echo gadgetim_product_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
					$gadgetim_id,
					'rail',
					[
						'index'  => $gadgetim_i,
						'reveal' => false,
					]
				);
				?>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
