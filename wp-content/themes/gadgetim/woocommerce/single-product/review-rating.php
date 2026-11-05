<?php
/**
 * The template to display the reviewers star rating in reviews
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/review-rating.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: the theme's two-row stars (.stars, --rating) instead of WooCommerce's star font.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $comment;
$rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own variable name.

if ( $rating && wc_review_ratings_enabled() ) {
	/* translators: %s: rating out of 5 */
	$gadgetim_label = sprintf( __( 'Rated %s out of 5', 'gadgetim' ), gadgetim_number( $rating ) );
	echo '<span class="stars rv-card__stars" role="img" aria-label="' . esc_attr( $gadgetim_label ) . '" style="--rating:' . esc_attr( (string) $rating ) . '">'
		. '<span class="stars__track" aria-hidden="true">' . str_repeat( gadgetim_icon( 'star' ), 5 ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icons.
		. '<span class="stars__fill" aria-hidden="true">' . str_repeat( gadgetim_icon( 'star' ), 5 ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icons.
}
