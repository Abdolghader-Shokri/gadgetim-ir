<?php
/**
 * Display single product reviews (comments)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product-reviews.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: summary (average, stars, count, «write a review») + rating histogram, review cards
 * (single-product/review.php), then WooCommerce's own comment_form() restyled, with the rating as
 * five radio stars (name="rating", required when WooCommerce requires a rating) instead of the
 * select WooCommerce's jQuery turns into stars — so the picker works without any script.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! comments_open() || ! $product instanceof WC_Product ) {
	return;
}

$gadgetim_stats   = gadgetim_pdp_review_stats( $product );
$gadgetim_ratings = wc_review_ratings_enabled();
$gadgetim_count   = $gadgetim_stats['count'];
$gadgetim_can     = 'no' === get_option( 'woocommerce_review_rating_verification_required' ) || wc_customer_bought_product( '', get_current_user_id(), $product->get_id() );
?>
<div id="reviews" class="woocommerce-Reviews rv">

	<div class="rv__top<?php echo $gadgetim_stats['rated'] > 0 ? '' : ' rv__top--empty'; ?>" data-reveal>
		<div class="rv-sum">
			<?php if ( $gadgetim_ratings && $gadgetim_stats['rated'] > 0 ) : ?>
				<p class="rv-sum__avg"><bdi><?php echo esc_html( gadgetim_number( $gadgetim_stats['average'], 1 ) ); ?></bdi><small><?php echo esc_html( '/ ' . gadgetim_number( 5 ) ); ?></small></p>
				<?php
				/* translators: 1: average rating, 2: number of reviews */
				$gadgetim_label = sprintf( __( 'Rated %1$s out of 5 from %2$s reviews', 'gadgetim' ), gadgetim_number( $gadgetim_stats['average'], 1 ), gadgetim_number( $gadgetim_stats['rated'] ) );
				?>
				<span class="stars rv-sum__stars" role="img" aria-label="<?php echo esc_attr( $gadgetim_label ); ?>" style="--rating:<?php echo esc_attr( (string) round( $gadgetim_stats['average'], 2 ) ); ?>">
					<span class="stars__track" aria-hidden="true"><?php echo str_repeat( gadgetim_icon( 'star' ), 5 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icons. ?></span>
					<span class="stars__fill" aria-hidden="true"><?php echo str_repeat( gadgetim_icon( 'star' ), 5 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icons. ?></span>
				</span>
				<?php /* translators: %s: number of reviews */ ?>
				<p class="rv-sum__n"><?php echo esc_html( sprintf( _n( 'From %s review', 'From %s reviews', $gadgetim_count, 'gadgetim' ), gadgetim_number( $gadgetim_count ) ) ); ?></p>
			<?php else : ?>
				<span class="rv-sum__art" aria-hidden="true"><?php gadgetim_the_icon( 'star' ); ?></span>
				<p class="rv-sum__empty-t"><?php esc_html_e( 'No reviews yet', 'gadgetim' ); ?></p>
				<p class="rv-sum__empty-p"><?php esc_html_e( 'Bought it? Tell others how it works for you.', 'gadgetim' ); ?></p>
			<?php endif; ?>
			<?php if ( $gadgetim_can ) : ?>
				<a class="btn btn--sm rv-sum__go" href="#review_form" data-review-go><?php gadgetim_the_icon( 'plus' ); ?><span><?php echo $gadgetim_count > 0 ? esc_html__( 'Write a review', 'gadgetim' ) : esc_html__( 'Write the first review', 'gadgetim' ); ?></span></a>
			<?php endif; ?>
		</div>

		<?php if ( $gadgetim_ratings && $gadgetim_stats['rated'] > 0 ) : ?>
			<ul class="rv-hist" aria-label="<?php esc_attr_e( 'Rating breakdown', 'gadgetim' ); ?>">
				<?php foreach ( $gadgetim_stats['bars'] as $gadgetim_star => $gadgetim_n ) : ?>
					<?php $gadgetim_w = $gadgetim_stats['rated'] > 0 ? $gadgetim_n / $gadgetim_stats['rated'] : 0; ?>
					<li class="rv-hist__row" style="--w:<?php echo esc_attr( (string) round( $gadgetim_w, 3 ) ); ?>;--i:<?php echo esc_attr( (string) ( 5 - $gadgetim_star ) ); ?>">
						<span class="rv-hist__l" aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_star ) ); ?><?php gadgetim_the_icon( 'star' ); ?></span>
						<span class="rv-hist__bar" aria-hidden="true"><i></i></span>
						<span class="rv-hist__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_n ) ); ?></span>
						<?php /* translators: 1: number of stars, 2: number of reviews */ ?>
						<span class="sr-only"><?php echo esc_html( sprintf( _n( '%1$s stars: %2$s review', '%1$s stars: %2$s reviews', $gadgetim_n, 'gadgetim' ), gadgetim_number( $gadgetim_star ), gadgetim_number( $gadgetim_n ) ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div id="comments" class="rv__list-wrap">
		<h3 class="woocommerce-Reviews-title rv__title">
			<?php
			if ( $gadgetim_count && $gadgetim_ratings ) {
				/* translators: 1: reviews count 2: product name */
				$reviews_title = sprintf( esc_html( _n( '%1$s review for %2$s', '%1$s reviews for %2$s', $gadgetim_count, 'gadgetim' ) ), esc_html( gadgetim_number( $gadgetim_count ) ), '<span>' . gadgetim_pdp_text( gadgetim_short_title( $product ) ) . '</span>' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable name.
				/** This filter is documented in woocommerce/templates/single-product-reviews.php */
				echo apply_filters( 'woocommerce_reviews_title', $reviews_title, $gadgetim_count, $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the base heading is escaped above; the filter returns extension-controlled markup by design.
			} else {
				esc_html_e( 'Reviews', 'gadgetim' );
			}
			?>
		</h3>

		<?php if ( have_comments() ) : ?>
			<ol class="commentlist rv__list">
				<?php wp_list_comments( apply_filters( 'woocommerce_product_review_list_args', [ 'callback' => 'woocommerce_comments' ] ) ); ?>
			</ol>

			<?php
			if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
				echo '<nav class="woocommerce-pagination rv__pager" aria-label="' . esc_attr__( 'Review pages', 'gadgetim' ) . '">';
				paginate_comments_links(
					apply_filters(
						'woocommerce_comment_pagination_args',
						[
							'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
							'next_text' => is_rtl() ? '&larr;' : '&rarr;',
							'type'      => 'list',
						]
					)
				);
				echo '</nav>';
			endif;
			?>
		<?php else : ?>
			<p class="woocommerce-noreviews rv__none"><?php esc_html_e( 'There are no reviews yet.', 'gadgetim' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $gadgetim_can ) : ?>
		<div id="review_form_wrapper" class="rv-form">
			<div id="review_form">
				<?php
				$commenter    = wp_get_current_commenter(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable name.
				$comment_form = [ // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable name.
					/* translators: %s is product title */
					'title_reply'         => have_comments() ? esc_html__( 'Add a review', 'gadgetim' ) : sprintf( esc_html__( 'Be the first to review “%s”', 'gadgetim' ), esc_html( gadgetim_digits( gadgetim_short_title( $product ) ) ) ),
					/* translators: %s is product title */
					'title_reply_to'      => esc_html__( 'Leave a reply to %s', 'gadgetim' ),
					'title_reply_before'  => '<span id="reply-title" class="comment-reply-title rv-form__t" role="heading" aria-level="3">',
					'title_reply_after'   => '</span>',
					'comment_notes_after' => '',
					'label_submit'        => esc_html__( 'Submit review', 'gadgetim' ),
					'logged_in_as'        => '',
					'comment_field'       => '',
				];

				$name_email_required = (bool) get_option( 'require_name_email', 1 );
				$fields              = [
					'author' => [
						'label'        => __( 'Name', 'gadgetim' ),
						'type'         => 'text',
						'value'        => $commenter['comment_author'],
						'required'     => $name_email_required,
						'autocomplete' => 'name',
					],
					'email'  => [
						'label'        => __( 'Email', 'gadgetim' ),
						'type'         => 'email',
						'value'        => $commenter['comment_author_email'],
						'required'     => $name_email_required,
						'autocomplete' => 'email',
					],
				];

				$comment_form['fields'] = [];

				foreach ( $fields as $key => $field ) {
					$field_html  = '<p class="comment-form-' . esc_attr( $key ) . ' rv-form__f">';
					$field_html .= '<label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] );

					if ( $field['required'] ) {
						$field_html .= '&nbsp;<span class="required" aria-hidden="true">*</span>';
					}

					$field_html .= '</label><input id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $field['type'] ) . '" autocomplete="' . esc_attr( $field['autocomplete'] ) . '" value="' . esc_attr( $field['value'] ) . '" size="30" ' . ( $field['required'] ? 'required' : '' ) . ' /></p>';

					$comment_form['fields'][ $key ] = $field_html;
				}

				$account_page_url = wc_get_page_permalink( 'myaccount' );
				if ( $account_page_url ) {
					/* translators: %s opening and closing link tags respectively */
					$comment_form['must_log_in'] = '<p class="must-log-in">' . sprintf( esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'gadgetim' ), '<a href="' . esc_url( $account_page_url ) . '">', '</a>' ) . '</p>';
				}

				if ( $gadgetim_ratings ) {
					$gadgetim_required = wc_review_ratings_required();
					$gadgetim_words    = [
						5 => __( 'Perfect', 'gadgetim' ),
						4 => __( 'Good', 'gadgetim' ),
						3 => __( 'Average', 'gadgetim' ),
						2 => __( 'Not that bad', 'gadgetim' ),
						1 => __( 'Very poor', 'gadgetim' ),
					];
					$gadgetim_stars    = '';
					foreach ( $gadgetim_words as $gadgetim_value => $gadgetim_word ) {
						/* translators: 1: number of stars, 2: rating word */
						$gadgetim_aria   = sprintf( _n( '%1$s star — %2$s', '%1$s stars — %2$s', $gadgetim_value, 'gadgetim' ), gadgetim_number( $gadgetim_value ), $gadgetim_word );
						$gadgetim_stars .= '<input class="rate-pick__in" type="radio" name="rating" id="rating-' . esc_attr( (string) $gadgetim_value ) . '" value="' . esc_attr( (string) $gadgetim_value ) . '"' . ( $gadgetim_required && 5 === $gadgetim_value ? ' required' : '' ) . '>'
							. '<label class="rate-pick__star" for="rating-' . esc_attr( (string) $gadgetim_value ) . '" title="' . esc_attr( $gadgetim_word ) . '" data-word="' . esc_attr( $gadgetim_word ) . '">' . gadgetim_icon( 'star' ) . '<span class="sr-only">' . esc_html( $gadgetim_aria ) . '</span></label>';
					}
					$comment_form['comment_field'] = '<fieldset class="comment-form-rating rate-pick" data-rate-pick><legend id="comment-form-rating-label">' . esc_html__( 'Your rating', 'gadgetim' ) . ( $gadgetim_required ? '&nbsp;<span class="required" aria-hidden="true">*</span>' : '' ) . '</legend>'
						. '<div class="rate-pick__row"><div class="rate-pick__stars">' . $gadgetim_stars . '</div><output class="rate-pick__word" data-rate-word aria-live="polite">' . esc_html__( 'Tap a star', 'gadgetim' ) . '</output></div></fieldset>';
				}

				$comment_form['comment_field'] .= '<p class="comment-form-comment rv-form__f rv-form__f--full"><label for="comment">' . esc_html__( 'Your review', 'gadgetim' ) . '&nbsp;<span class="required" aria-hidden="true">*</span></label><textarea id="comment" name="comment" cols="45" rows="6" required placeholder="' . esc_attr__( 'What did you like? How is the quality? Would you recommend it?', 'gadgetim' ) . '"></textarea></p>';

				comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ) );
				?>
			</div>
		</div>
	<?php else : ?>
		<p class="woocommerce-verification-required rv__verify"><?php gadgetim_the_icon( 'lock' ); ?><?php esc_html_e( 'Only logged in customers who have purchased this product may leave a review.', 'gadgetim' ); ?></p>
	<?php endif; ?>
</div>
