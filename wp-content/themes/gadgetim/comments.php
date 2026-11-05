<?php
/**
 * Comments of articles and pages (product reviews use WooCommerce's single-product-reviews.php):
 * a counted heading, the threaded list (initials avatars — no Gravatar request — Jalali dates,
 * moderation note; gadgetim_pages_comment()), navigation and the comment form. The form keeps
 * the default fields, so the plugin's hidden language field (comment_form_default_fields) stays.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

if ( post_password_required() ) {
	return;
}
$gadgetim_count = (int) get_comments_number();
?>
<section id="comments" class="cmts sect" aria-labelledby="comments-title">
	<div class="cmts__head">
		<span class="sect__ic sect__ic--pink" aria-hidden="true"><?php gadgetim_the_icon( 'chat' ); ?></span>
		<div class="sect__titles">
			<h2 class="sect__title" id="comments-title">
				<?php
				echo esc_html(
					$gadgetim_count > 0
						/* translators: %s: number of comments */
						? sprintf( _n( '%s comment', '%s comments', $gadgetim_count, 'gadgetim' ), gadgetim_number( $gadgetim_count ) )
						: __( 'Comments', 'gadgetim' )
				);
				?>
			</h2>
			<p class="sect__sub"><?php echo esc_html( $gadgetim_count > 0 ? __( 'What readers say about this article', 'gadgetim' ) : __( 'Be the first to share your thoughts', 'gadgetim' ) ); ?></p>
		</div>
	</div>

	<?php if ( have_comments() ) : ?>
		<ol class="cmts__list">
			<?php
			wp_list_comments(
				[
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 0,
					'callback'    => 'gadgetim_pages_comment',
				]
			);
			?>
		</ol>
		<?php
		the_comments_navigation(
			[
				'prev_text' => gadgetim_icon( 'chev-r', 'ic--dir' ) . '<span>' . esc_html__( 'Older comments', 'gadgetim' ) . '</span>',
				'next_text' => '<span>' . esc_html__( 'Newer comments', 'gadgetim' ) . '</span>' . gadgetim_icon( 'chev-l', 'ic--dir' ),
				'class'     => 'cmts__nav',
			]
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && $gadgetim_count > 0 ) : ?>
		<p class="cmts__closed"><?php gadgetim_the_icon( 'lock' ); ?><span><?php esc_html_e( 'Comments are closed.', 'gadgetim' ); ?></span></p>
	<?php endif; ?>

	<?php
	comment_form(
		[
			'class_container'      => 'cmts__form panel',
			'class_form'           => 'cmts__fields',
			'title_reply'          => __( 'Leave a comment', 'gadgetim' ),
			/* translators: %s: comment author being replied to */
			'title_reply_to'       => __( 'Reply to %s', 'gadgetim' ),
			'title_reply_before'   => '<h3 id="reply-title" class="cmts__rt">',
			'title_reply_after'    => '</h3>',
			'cancel_reply_link'    => __( 'Cancel reply', 'gadgetim' ),
			'comment_notes_before' => '<p class="cmts__note">' . esc_html__( 'Your email address will not be published. Required fields are marked *', 'gadgetim' ) . '</p>',
			'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your comment', 'gadgetim' ) . ' <span class="fld__req" aria-hidden="true">*</span></label><textarea id="comment" name="comment" rows="5" maxlength="65525" required></textarea></p>',
			'class_submit'         => 'btn',
			'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s"><span>%4$s</span></button>',
			'label_submit'         => __( 'Post comment', 'gadgetim' ),
		]
	);
	?>
</section>
