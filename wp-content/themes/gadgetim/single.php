<?php
/**
 * Single article: a reading-progress line, the gradient hero (categories, title, Jalali date,
 * reading time), the featured image (the LCP: eager, fetchpriority=high, preloaded through
 * gadgetim_lcp_image), the content as prose with a table of contents for long articles, tags and
 * share links, previous/next articles, related articles and the comments.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	$gadgetim_post      = get_post();
	$gadgetim_protected = post_password_required( $gadgetim_post );
	$gadgetim_prep      = gadgetim_pages_prepare( gadgetim_pages_post_html( $gadgetim_post ), get_the_title() );
	$gadgetim_toc       = $gadgetim_protected ? [] : $gadgetim_prep['toc'];
	$gadgetim_cats      = gadgetim_pages_post_categories( $gadgetim_post );
	$gadgetim_thumb     = (int) get_post_thumbnail_id( $gadgetim_post );
	$gadgetim_comments  = (int) get_comments_number( $gadgetim_post );
	$gadgetim_chips     = [
		[
			'icon' => 'calendar',
			'text' => gadgetim_date( (int) get_post_time( 'U', true, $gadgetim_post ), 'medium' ),
		],
		[
			'icon' => 'clock',
			'text' => gadgetim_pages_minutes_label( $gadgetim_prep['minutes'] ),
		],
	];
	if ( $gadgetim_comments > 0 ) {
		$gadgetim_chips[] = [
			'icon' => 'chat',
			/* translators: %s: number of comments */
			'text' => sprintf( _n( '%s comment', '%s comments', $gadgetim_comments, 'gadgetim' ), gadgetim_number( $gadgetim_comments ) ),
		];
	}
	$gadgetim_tags    = get_the_tags( $gadgetim_post->ID );
	$gadgetim_prev    = get_previous_post();
	$gadgetim_next    = get_next_post();
	$gadgetim_related = gadgetim_pages_related_posts( $gadgetim_post, 3 );
	gadgetim_pages_use_script();
	?>
	<div class="rprog" aria-hidden="true"></div>
	<div class="container pg-shell post-shell">
		<?php gadgetim_breadcrumbs(); ?>
		<article <?php post_class( 'pg-art post' ); ?> aria-labelledby="page-title">
			<?php
			gadgetim_part(
				'pages/hero',
				[
					'title'  => get_the_title(),
					'kicker' => $gadgetim_cats ? $gadgetim_cats[0]->name : __( 'Article', 'gadgetim' ),
					'lead'   => has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : '',
					'icon'   => 'doc',
					'tone'   => 'warm',
					'chips'  => $gadgetim_chips,
					'art'    => $gadgetim_thumb > 0 ? false : '',
					'class'  => 'phero--post' . ( $gadgetim_thumb > 0 ? ' phero--has-media' : '' ),
				]
			);
			?>
			<?php if ( $gadgetim_thumb > 0 ) : ?>
				<figure class="post__media">
					<?php
					echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						$gadgetim_thumb,
						'large',
						[
							'class'         => 'post__img',
							'alt'           => trim( (string) get_post_meta( $gadgetim_thumb, '_wp_attachment_image_alt', true ) ),
							'sizes'         => 'post',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
						]
					);
					?>
					<?php $gadgetim_caption = wp_get_attachment_caption( $gadgetim_thumb ); ?>
					<?php if ( $gadgetim_caption ) : ?>
						<figcaption><?php echo esc_html( $gadgetim_caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="pg pg--post<?php echo $gadgetim_toc ? ' pg--toc' : ''; ?>">
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
					<div class="pg__body prose post__body">
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
					<footer class="post__foot">
						<?php if ( $gadgetim_tags && ! is_wp_error( $gadgetim_tags ) ) : ?>
							<ul class="post__tags" aria-label="<?php esc_attr_e( 'Tags', 'gadgetim' ); ?>">
								<?php foreach ( $gadgetim_tags as $gadgetim_tag ) : ?>
									<li><a class="chip-q" href="<?php echo esc_url( (string) get_tag_link( $gadgetim_tag ) ); ?>"><?php gadgetim_the_icon( 'tag' ); ?><?php echo esc_html( $gadgetim_tag->name ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<div class="share" role="group" aria-label="<?php esc_attr_e( 'Share this article', 'gadgetim' ); ?>">
							<span class="share__l"><?php gadgetim_the_icon( 'share' ); ?><?php esc_html_e( 'Share', 'gadgetim' ); ?></span>
							<?php foreach ( gadgetim_pages_share_links( $gadgetim_post ) as $gadgetim_share ) : ?>
								<a class="share__b share__b--<?php echo esc_attr( $gadgetim_share['key'] ); ?>" href="<?php echo esc_url( $gadgetim_share['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $gadgetim_share['label'] ); ?>"><?php gadgetim_the_icon( $gadgetim_share['icon'] ); ?></a>
							<?php endforeach; ?>
							<button class="share__b share__b--copy" type="button" data-copy="<?php echo esc_attr( rawurldecode( (string) get_permalink() ) ); ?>" data-copied="<?php esc_attr_e( 'Link copied', 'gadgetim' ); ?>" aria-label="<?php esc_attr_e( 'Copy the link', 'gadgetim' ); ?>"><?php echo gadgetim_pages_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
						</div>
					</footer>
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
					<?php
					gadgetim_part(
						'pages/help',
						[
							'title' => __( 'Need advice before buying?', 'gadgetim' ),
							'text'  => __( 'Tell us what you need and we will help you choose the right gadget.', 'gadgetim' ),
							'links' => false,
						]
					);
					?>
				</aside>
			</div>

			<?php if ( $gadgetim_prev || $gadgetim_next ) : ?>
				<nav class="pnav" aria-label="<?php esc_attr_e( 'More articles', 'gadgetim' ); ?>">
					<?php if ( $gadgetim_prev instanceof WP_Post ) : ?>
						<a class="pnav__a pnav__a--prev" href="<?php echo esc_url( (string) get_permalink( $gadgetim_prev ) ); ?>" rel="prev">
							<span class="pnav__l"><?php gadgetim_the_icon( 'chev-r', 'ic--dir' ); ?><?php esc_html_e( 'Previous article', 'gadgetim' ); ?></span>
							<b class="pnav__t"><?php echo gadgetim_bidi( get_the_title( $gadgetim_prev ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></b>
						</a>
					<?php endif; ?>
					<?php if ( $gadgetim_next instanceof WP_Post ) : ?>
						<a class="pnav__a pnav__a--next" href="<?php echo esc_url( (string) get_permalink( $gadgetim_next ) ); ?>" rel="next">
							<span class="pnav__l"><?php esc_html_e( 'Next article', 'gadgetim' ); ?><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></span>
							<b class="pnav__t"><?php echo gadgetim_bidi( get_the_title( $gadgetim_next ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></b>
						</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</article>

		<?php if ( $gadgetim_related ) : ?>
			<section class="sect prel" aria-labelledby="prel-t">
				<?php
				gadgetim_the_section_head(
					[
						'title' => __( 'Read next', 'gadgetim' ),
						'sub'   => __( 'More guides from Gadgetim', 'gadgetim' ),
						'id'    => 'prel-t',
						'icon'  => 'sparkle',
						'tone'  => 'warm',
					]
				);
				?>
				<ul class="bgrid bgrid--3">
					<?php foreach ( $gadgetim_related as $gadgetim_i => $gadgetim_rel ) : ?>
						<?php
						gadgetim_part(
							'pages/post-card',
							[
								'post'    => $gadgetim_rel,
								'index'   => $gadgetim_i + 1,
								'heading' => 'h3',
							]
						);
						?>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php gadgetim_part( 'pages/popular', [ 'id' => 'post-pop', 'limit' => 6 ] ); ?>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
