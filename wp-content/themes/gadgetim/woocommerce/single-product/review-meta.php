<?php
/**
 * The template to display the reviewers meta data (name, verified owner, review date)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/review-meta.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: author, «خریدار این کالا» tag for verified owners, Jalali date (machine date in datetime).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.4.0
 */

defined( 'ABSPATH' ) || exit;

global $comment;
$verified = wc_review_is_from_verified_owner( $comment->comment_ID ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable name.

if ( '0' === $comment->comment_approved ) { ?>

	<p class="meta rv-card__meta">
		<em class="woocommerce-review__awaiting-approval rv-card__wait">
			<?php gadgetim_the_icon( 'clock' ); ?><?php esc_html_e( 'Your review is awaiting approval', 'gadgetim' ); ?>
		</em>
	</p>

<?php } else { ?>

	<p class="meta rv-card__meta">
		<strong class="woocommerce-review__author rv-card__name"><?php comment_author(); ?></strong>
		<?php if ( 'yes' === get_option( 'woocommerce_review_rating_verification_label' ) && $verified ) : ?>
			<em class="woocommerce-review__verified verified rv-card__buyer"><?php gadgetim_the_icon( 'check' ); ?><?php esc_html_e( 'Verified buyer', 'gadgetim' ); ?></em>
		<?php endif; ?>
		<time class="woocommerce-review__published-date rv-card__date" datetime="<?php echo esc_attr( get_comment_date( 'c' ) ); ?>"><?php echo esc_html( gadgetim_date( (int) strtotime( $comment->comment_date_gmt . ' +0000' ), 'medium' ) ); ?></time>
	</p>

	<?php
}
