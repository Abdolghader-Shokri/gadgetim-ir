<?php
/**
 * Closes <main>, then the footer (newsletter, columns, trust, bottom row), the mobile bottom
 * navigation, the drawers (menu, cart, wishlist), the toast region and back-to-top (its ring fills
 * with the scroll progress, fx.css / fx.js).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );
?>
</main>
<footer class="ftr">
	<div class="ftr__fx" aria-hidden="true"><i></i><i></i></div>
	<div class="container">
		<?php gadgetim_part( 'footer/newsletter' ); ?>
		<?php gadgetim_part( 'footer/columns' ); ?>
		<?php gadgetim_part( 'footer/bottom' ); ?>
	</div>
</footer>
<?php gadgetim_part( 'footer/bnav' ); ?>
<?php gadgetim_part( 'footer/drawers' ); ?>
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
<button class="to-top" id="to-top" type="button" aria-label="<?php esc_attr_e( 'Back to top', 'gadgetim' ); ?>"><svg class="to-top__ring" viewBox="0 0 50 50" aria-hidden="true" focusable="false"><circle cx="25" cy="25" r="23" pathLength="100"/></svg><?php gadgetim_the_icon( 'up' ); ?></button>
<?php wp_footer(); ?>
</body>
</html>
