<?php
/**
 * Commerce shell (routed by gadgetim_commerce_template() in inc/commerce.php) for the cart,
 * checkout, order received, order pay, account, sign in / register and order tracking pages:
 * steps indicator and heading (templates/commerce/head.php), then the WooCommerce shortcode output.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();
$gadgetim_view = gadgetim_commerce_view();
?>
<div class="container cm cm--<?php echo esc_attr( $gadgetim_view ); ?>" data-commerce="<?php echo esc_attr( $gadgetim_view ); ?>">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<?php gadgetim_part( 'commerce/head', [ 'view' => $gadgetim_view ] ); ?>
		<div class="cm__body">
			<?php the_content(); ?>
		</div>
	<?php endwhile; ?>
	<p class="sr-only" aria-live="polite" data-cm-live></p>
</div>
<?php
get_footer();
