<?php
/**
 * Delight layer: the markup hooks and helpers behind assets/css/fx.css (@layer fx) and
 * assets/js/modules/fx.js.
 *
 *   wp_head 5        @view-transition opt-in (cross-document fade/slide), except on cart and checkout.
 *   wp_body_open 2   one hidden SVG with the shared gradients (story rings, back-to-top ring), the fixed
 *                    ambient sky (pre-blurred radial blobs, transform-only drift) and the scroll-progress bar.
 *   gadgetim_fx_words()  word spans for the hero headline's word-by-word reveal (accessible copy kept whole).
 *
 * Everything here is decoration: without fx.css the elements are empty and invisible, without fx.js
 * the CSS-only parts still play and nothing is ever hidden.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * Splits escaped inline HTML (e.g. gadgetim_bidi() output) into word spans for the hero headline.
 *
 * Returns the untouched text for assistive technology (.sr-only) plus an aria-hidden copy whose
 * words are <span class="fxw" style="--w:n"> (inline-block, so they can move). Only plain spaces
 * split words: a no-break space or a zero-width non-joiner keeps its word whole, tags (bdi, the
 * no-wrap span) are kept around their words.
 *
 * @param string $html  Escaped inline HTML.
 * @param int    $start First word index (stagger offset).
 */
function gadgetim_fx_words( string $html, int $start = 0 ): string {
	$parts = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $parts ) || '' === trim( wp_strip_all_tags( $html ) ) ) {
		return $html;
	}
	$out = '';
	$i   = $start;
	foreach ( $parts as $part ) {
		if ( '<' === $part[0] ) {
			$out .= $part;
			continue;
		}
		$chunks = preg_split( '/( +)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		foreach ( (array) $chunks as $chunk ) {
			if ( '' === trim( $chunk, ' ' ) ) {
				$out .= $chunk;
				continue;
			}
			$out .= '<span class="fxw" style="--w:' . (int) $i . '">' . $chunk . '</span>';
			++$i;
		}
	}
	return '<span class="sr-only">' . $html . '</span><span class="fxw-line" aria-hidden="true">' . $out . '</span>';
}

/**
 * wp_head 5: cross-document view transitions (a short fade and rise between pages; the header and
 * the bottom nav stay put). Not on cart and checkout, where the forms must feel instant and steady.
 * Only for visitors who did not ask for reduced motion.
 */
function gadgetim_fx_view_transitions(): void {
	$kind = gadgetim_page_kind();
	if ( ! apply_filters( 'gadgetim_view_transitions', ! in_array( $kind, [ 'cart', 'checkout' ], true ), $kind ) ) {
		return;
	}
	echo '<style id="gadgetim-vt">@media (prefers-reduced-motion:no-preference){@view-transition{navigation:auto}}</style>' . "\n";
}
add_action( 'wp_head', 'gadgetim_fx_view_transitions', 5 );

/**
 * wp_body_open 2 (right after the icon sprite): shared SVG gradients, the ambient sky and the
 * scroll-progress bar. All aria-hidden; fx.css positions and animates them.
 */
function gadgetim_fx_layers(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	echo '<svg class="fx-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs>'
		. '<linearGradient id="fx-g-ring" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f59e0b"/><stop offset=".32" stop-color="#ec4899"/><stop offset=".64" stop-color="#a855f7"/><stop offset="1" stop-color="#22d3ee"/></linearGradient>'
		. '<linearGradient id="fx-g-hot" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fde047"/><stop offset=".45" stop-color="#f97316"/><stop offset="1" stop-color="#ec4899"/></linearGradient>'
		. '<linearGradient id="fx-g-main" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4f46e5"/><stop offset=".5" stop-color="#a855f7"/><stop offset="1" stop-color="#ec4899"/></linearGradient>'
		. '</defs></svg>' . "\n"
		. '<div class="fx-sky" aria-hidden="true"><i></i><i></i><i></i></div>' . "\n"
		. '<div class="fx-progress" aria-hidden="true"></div>' . "\n";
}
add_action( 'wp_body_open', 'gadgetim_fx_layers', 2 );
