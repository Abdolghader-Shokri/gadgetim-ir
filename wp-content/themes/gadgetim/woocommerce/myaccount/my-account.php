<?php
/**
 * My Account page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a side column with the profile card (gradient initials avatar, name, contact,
 * member since) and the navigation; the endpoint content in the main column. On phones the
 * navigation becomes a row of scrolling chips under the profile card.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

$gadgetim_profile = gadgetim_account_profile();
?>
<div class="myac">
	<aside class="myac__side">
		<div class="cm-card myac-prof">
			<span class="myac-prof__av" aria-hidden="true"><span><?php echo esc_html( $gadgetim_profile['initials'] ); ?></span></span>
			<div class="myac-prof__b">
				<p class="myac-prof__n"><?php echo esc_html( $gadgetim_profile['name'] ); ?></p>
				<?php if ( '' !== $gadgetim_profile['contact'] ) : ?>
					<p class="myac-prof__c"><bdi dir="ltr"><?php echo esc_html( $gadgetim_profile['contact'] ); ?></bdi></p>
				<?php endif; ?>
				<?php if ( '' !== $gadgetim_profile['since'] ) : ?>
					<?php /* translators: %s: registration date */ ?>
					<p class="myac-prof__s"><?php gadgetim_the_icon( 'sparkle' ); ?><?php echo esc_html( sprintf( __( 'Member since %s', 'gadgetim' ), $gadgetim_profile['since'] ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		/**
		 * My Account navigation.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_navigation' );
		?>
	</aside>

	<div class="woocommerce-MyAccount-content myac__main">
		<?php
			/**
			 * My Account content.
			 *
			 * @since 2.6.0
			 */
			do_action( 'woocommerce_account_content' );
		?>
	</div>
</div>
