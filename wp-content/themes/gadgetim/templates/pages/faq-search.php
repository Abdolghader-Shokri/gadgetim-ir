<?php
/**
 * Search box of the FAQ (hero of the FAQ page). A GET form on the same page (?faq=<words>) that
 * pages.js turns into a live filter of the accordion named in aria-controls.
 *
 * Args: controls (FAQ root id, default faq), value, id (input id), class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_controls = sanitize_html_class( (string) ( $args['controls'] ?? 'faq' ) );
$gadgetim_input    = sanitize_html_class( (string) ( $args['id'] ?? 'faq-q' ) );
?>
<form class="<?php echo esc_attr( trim( 'faqs ' . (string) ( $args['class'] ?? '' ) ) ); ?>" role="search" action="<?php echo esc_url( (string) get_permalink() ); ?>#<?php echo esc_attr( $gadgetim_controls ); ?>" method="get" data-faq-form>
	<label class="sr-only" for="<?php echo esc_attr( $gadgetim_input ); ?>"><?php esc_html_e( 'Search the questions', 'gadgetim' ); ?></label>
	<span class="faqs__ic" aria-hidden="true"><?php gadgetim_the_icon( 'search' ); ?></span>
	<input id="<?php echo esc_attr( $gadgetim_input ); ?>" name="faq" type="search" value="<?php echo esc_attr( (string) ( $args['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Search the questions…', 'gadgetim' ); ?>" autocomplete="off" spellcheck="false" enterkeyhint="search" maxlength="64" data-faq-q aria-controls="<?php echo esc_attr( $gadgetim_controls ); ?>">
	<button class="faqs__go" type="submit"><span><?php esc_html_e( 'Search', 'gadgetim' ); ?></span></button>
</form>
