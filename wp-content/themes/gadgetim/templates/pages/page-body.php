<?php
/**
 * Generic page body: breadcrumbs, gradient hero, the content as readable prose in a white panel
 * (a table of contents for long pages: inline card on phones, sticky list on desktops) and the help
 * aside. Used by page.php and by the special templates when the content does not fit them.
 *
 * Args: post (WP_Post, default the current post), hero (array merged into the hero args).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_post = get_post( $args['post'] ?? null );
if ( ! $gadgetim_post instanceof WP_Post ) {
	return;
}
$gadgetim_role      = gadgetim_page_role( $gadgetim_post );
$gadgetim_style     = gadgetim_pages_role_style( $gadgetim_role );
$gadgetim_title     = get_the_title( $gadgetim_post );
$gadgetim_protected = post_password_required( $gadgetim_post );
$gadgetim_prep      = gadgetim_pages_prepare( gadgetim_pages_post_html( $gadgetim_post ), $gadgetim_title );
$gadgetim_toc       = $gadgetim_protected ? [] : $gadgetim_prep['toc'];
$gadgetim_chips     = [];
if ( ! $gadgetim_protected ) {
	$gadgetim_chips[] = [
		'icon' => 'calendar',
		/* translators: %s: date */
		'text' => sprintf( __( 'Updated %s', 'gadgetim' ), gadgetim_date( (int) get_post_modified_time( 'U', true, $gadgetim_post ), 'medium' ) ),
	];
	if ( $gadgetim_prep['minutes'] > 1 ) {
		$gadgetim_chips[] = [
			'icon' => 'clock',
			'text' => gadgetim_pages_minutes_label( $gadgetim_prep['minutes'] ),
		];
	}
}
$gadgetim_hero = array_merge(
	[
		'title'  => $gadgetim_title,
		'kicker' => $gadgetim_style['kicker'],
		'lead'   => has_excerpt( $gadgetim_post ) ? wp_strip_all_tags( get_the_excerpt( $gadgetim_post ) ) : $gadgetim_style['lead'],
		'icon'   => $gadgetim_style['icon'],
		'tone'   => $gadgetim_style['tone'],
		'chips'  => $gadgetim_chips,
	],
	(array) ( $args['hero'] ?? [] )
);
?>
<div class="container pg-shell">
	<?php gadgetim_breadcrumbs(); ?>
	<article <?php post_class( 'pg-art', $gadgetim_post ); ?> aria-labelledby="page-title">
		<?php gadgetim_part( 'pages/hero', $gadgetim_hero ); ?>
		<div class="pg<?php echo $gadgetim_toc ? ' pg--toc' : ''; ?>">
			<div class="pg__main">
				<?php if ( $gadgetim_toc ) : ?>
					<?php
					gadgetim_part(
						'pages/toc',
						[
							'items' => $gadgetim_toc,
							'mode'  => 'inline',
						]
					);
					?>
				<?php endif; ?>
				<div class="pg__body prose">
					<?php echo $gadgetim_prep['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output (editor HTML), heading ids escaped. ?>
					<?php
					wp_link_pages(
						[
							'before' => '<nav class="pg__pages" aria-label="' . esc_attr__( 'Page sections', 'gadgetim' ) . '">',
							'after'  => '</nav>',
						]
					);
					?>
				</div>
			</div>
			<aside class="pg__aside" aria-label="<?php esc_attr_e( 'Help and related pages', 'gadgetim' ); ?>">
				<?php if ( $gadgetim_toc ) : ?>
					<?php
					gadgetim_part(
						'pages/toc',
						[
							'items' => $gadgetim_toc,
							'mode'  => 'aside',
						]
					);
					?>
				<?php endif; ?>
				<?php gadgetim_part( 'pages/help' ); ?>
			</aside>
		</div>
	</article>
	<?php if ( '' === $gadgetim_role && ( comments_open( $gadgetim_post ) || get_comments_number( $gadgetim_post ) ) ) : ?>
		<?php comments_template(); ?>
	<?php endif; ?>
</div>
