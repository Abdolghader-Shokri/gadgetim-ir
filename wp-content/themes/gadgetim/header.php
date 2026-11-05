<?php
/**
 * Document head and site header: promo strip, sticky header (bar + desktop nav with the mega
 * menu), then <main id="main"> (closed in footer.php).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );
?><!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-page="<?php echo esc_attr( gadgetim_page_kind() ); ?>">
<?php wp_body_open(); ?>
<a class="skip" href="#main"><?php esc_html_e( 'Skip to content', 'gadgetim' ); ?></a>
<?php if ( 'archive' === gadgetim_page_kind() ) : ?>
<a class="skip" href="#products"><?php esc_html_e( 'Skip to products', 'gadgetim' ); ?></a>
<?php endif; ?>
<?php gadgetim_part( 'header/promo' ); ?>
<header class="hdr" id="hdr">
	<?php gadgetim_part( 'header/bar' ); ?>
	<?php gadgetim_part( 'header/nav' ); ?>
</header>
<div class="mega-scrim" id="mega-scrim" aria-hidden="true"></div>
<main id="main" class="site-main" tabindex="-1">
	<div class="aurora<?php echo 'home' === gadgetim_page_kind() ? '' : ' aurora--short'; ?>" aria-hidden="true"><i class="aurora__b a1"></i><i class="aurora__b a2"></i><i class="aurora__b a3"></i><i class="aurora__b a4"></i></div>
