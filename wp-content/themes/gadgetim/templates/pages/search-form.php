<?php
/**
 * A large search form for page bodies (404, site search). Scope "products" searches the shop
 * (WooCommerce product results), "site" searches articles and pages (search.php) and shows the
 * matching products on top. Plain GET form: works without JS.
 *
 * Args: id (input id, unique on the page), scope (products|site), value, label, placeholder, class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_f     = wp_parse_args(
	$args ?? [],
	[
		'id'          => 'psearch-q',
		'scope'       => 'products',
		'value'       => '',
		'label'       => __( 'Search products', 'gadgetim' ),
		'placeholder' => __( 'Search original gadgets…', 'gadgetim' ),
		'class'       => '',
	]
);
$gadgetim_input = sanitize_html_class( (string) $gadgetim_f['id'] );
?>
<form class="<?php echo esc_attr( trim( 'psearch ' . (string) $gadgetim_f['class'] ) ); ?>" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
	<label class="sr-only" for="<?php echo esc_attr( $gadgetim_input ); ?>"><?php echo esc_html( (string) $gadgetim_f['label'] ); ?></label>
	<span class="psearch__ic" aria-hidden="true"><?php gadgetim_the_icon( 'search' ); ?></span>
	<input id="<?php echo esc_attr( $gadgetim_input ); ?>" name="s" type="search" value="<?php echo esc_attr( (string) $gadgetim_f['value'] ); ?>" placeholder="<?php echo esc_attr( (string) $gadgetim_f['placeholder'] ); ?>" autocomplete="off" spellcheck="false" enterkeyhint="search" maxlength="64">
	<?php if ( 'products' === $gadgetim_f['scope'] ) : ?>
		<input type="hidden" name="post_type" value="product">
	<?php endif; ?>
	<button class="btn psearch__go" type="submit"><?php gadgetim_the_icon( 'search' ); ?><span><?php esc_html_e( 'Search', 'gadgetim' ); ?></span></button>
</form>
