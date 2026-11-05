<?php
/**
 * Show messages
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/notices/notice.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the text in one flex item and WooCommerce's .button links after it; the info
 * icon is drawn in CSS (wc_print_notices() strips SVG <use> through wc_kses_notice()).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $notices ) {
	return;
}

?>

<?php foreach ( $notices as $notice ) : ?>
	<?php
	$gadgetim_html    = wc_kses_notice( $notice['notice'] );
	$gadgetim_buttons = '';
	if ( preg_match_all( '#<a\s[^>]*class="[^"]*\bbutton\b[^"]*"[^>]*>.*?</a>#is', $gadgetim_html, $gadgetim_m ) ) {
		$gadgetim_buttons = implode( '', $gadgetim_m[0] );
		$gadgetim_html    = trim( str_replace( $gadgetim_m[0], '', $gadgetim_html ) );
	}
	?>
	<div class="woocommerce-info ntc ntc--info"<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="status">
		<span class="ntc__x"><?php echo $gadgetim_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_kses_notice() above. ?></span>
		<?php echo $gadgetim_buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_kses_notice() above. ?>
	</div>
<?php endforeach; ?>
