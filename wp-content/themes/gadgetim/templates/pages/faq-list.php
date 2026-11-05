<?php
/**
 * FAQ accordion: native <details> cards (work without JS, find-in-page opens them), numbered,
 * grouped by topic, each with a deep link. With search, pages.js filters the questions live
 * (input [data-faq-q][aria-controls=<root id>], anywhere on the page) and highlights the words;
 * without JS the ?faq=<words> GET parameter filters on the server.
 *
 * Args:
 *   faq     parsed FAQ (gadgetim_pages_faq()) — or items: a flat list of questions
 *   id      root id (default faq)
 *   search  (bool) enable filtering (counter, empty state)
 *   query   (string) server-side filter (the ?faq= value)
 *   numbers (bool, default true) numbered questions
 *   open    (bool) open the first question
 *   class   extra classes
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_q = wp_parse_args(
	$args ?? [],
	[
		'faq'     => null,
		'items'   => null,
		'id'      => 'faq',
		'search'  => false,
		'query'   => '',
		'numbers' => true,
		'open'    => false,
		'class'   => '',
	]
);
if ( is_array( $gadgetim_q['items'] ) ) {
	$gadgetim_groups = [
		[
			'title' => '',
			'id'    => '',
			'note'  => '',
			'items' => $gadgetim_q['items'],
		],
	];
} else {
	$gadgetim_groups = (array) ( $gadgetim_q['faq']['groups'] ?? [] );
}
if ( ! $gadgetim_groups ) {
	return;
}
$gadgetim_search = ! empty( $gadgetim_q['search'] );
if ( $gadgetim_search ) {
	gadgetim_pages_use_script();
}

// Server-side filter (no-JS search): every word must appear in the question or the answer.
$gadgetim_words = array_values( array_filter( explode( ' ', gadgetim_pages_norm( (string) $gadgetim_q['query'] ) ) ) );
$gadgetim_shown = 0;
$gadgetim_total = 0;
foreach ( $gadgetim_groups as &$gadgetim_group ) {
	foreach ( $gadgetim_group['items'] as &$gadgetim_item ) {
		++$gadgetim_total;
		$gadgetim_item['hit'] = true;
		if ( $gadgetim_words ) {
			$gadgetim_hay = gadgetim_pages_norm( $gadgetim_item['q'] . ' ' . $gadgetim_item['a'] );
			foreach ( $gadgetim_words as $gadgetim_word ) {
				if ( ! str_contains( $gadgetim_hay, $gadgetim_word ) ) {
					$gadgetim_item['hit'] = false;
					break;
				}
			}
		}
		$gadgetim_shown += $gadgetim_item['hit'] ? 1 : 0;
	}
	unset( $gadgetim_item );
}
unset( $gadgetim_group );

$gadgetim_root = sanitize_html_class( (string) $gadgetim_q['id'] );
// Result counter for every possible count (plural rules differ per language, Arabic has six forms).
$gadgetim_counts = [];
for ( $gadgetim_n = 0; $gadgetim_n <= $gadgetim_total; $gadgetim_n++ ) {
	/* translators: %s: number of questions */
	$gadgetim_counts[] = sprintf( _n( '%s question found', '%s questions found', $gadgetim_n, 'gadgetim' ), gadgetim_number( $gadgetim_n ) );
}
$gadgetim_wa      = gadgetim_whatsapp_url( __( 'Hello! I have a question for Gadgetim.', 'gadgetim' ) );
$gadgetim_contact = gadgetim_pages_role_url( 'contact' );
$gadgetim_first   = true;
?>
<div class="<?php echo esc_attr( trim( 'faq ' . ( $gadgetim_q['numbers'] ? 'faq--num ' : '' ) . (string) $gadgetim_q['class'] ) ); ?>" id="<?php echo esc_attr( $gadgetim_root ); ?>"<?php echo $gadgetim_search ? ' data-faq data-counts="' . esc_attr( (string) wp_json_encode( $gadgetim_counts, JSON_UNESCAPED_UNICODE ) ) . '"' : ''; ?>>
	<?php if ( $gadgetim_search ) : ?>
		<p class="faq__count" data-faq-count role="status" aria-live="polite"><?php echo $gadgetim_words ? esc_html( $gadgetim_counts[ $gadgetim_shown ] ?? '' ) : ''; ?></p>
	<?php endif; ?>
	<?php foreach ( $gadgetim_groups as $gadgetim_group ) : ?>
		<?php
		$gadgetim_has_title = '' !== (string) $gadgetim_group['title'];
		$gadgetim_visible   = array_filter( $gadgetim_group['items'], static fn( array $i ): bool => $i['hit'] );
		?>
		<section class="faq__group" data-faq-group<?php echo $gadgetim_has_title ? ' aria-labelledby="' . esc_attr( (string) $gadgetim_group['id'] ) . '"' : ''; ?><?php echo $gadgetim_visible ? '' : ' hidden'; ?>>
			<?php if ( $gadgetim_has_title ) : ?>
				<h2 class="faq__gt" id="<?php echo esc_attr( (string) $gadgetim_group['id'] ); ?>"><?php echo gadgetim_bidi( (string) $gadgetim_group['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></h2>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) $gadgetim_group['note'] ) ) : ?>
				<div class="faq__note prose"><?php echo wp_kses_post( (string) $gadgetim_group['note'] ); ?></div>
			<?php endif; ?>
			<div class="faq__list">
				<?php foreach ( $gadgetim_group['items'] as $gadgetim_item ) : ?>
					<?php
					$gadgetim_open  = ( $gadgetim_words && $gadgetim_item['hit'] ) || ( ! $gadgetim_words && $gadgetim_q['open'] && $gadgetim_first );
					$gadgetim_first = false;
					?>
					<details class="faq__item" id="<?php echo esc_attr( (string) $gadgetim_item['id'] ); ?>" data-faq-item<?php echo $gadgetim_open ? ' open' : ''; ?><?php echo $gadgetim_item['hit'] ? '' : ' hidden'; ?>>
						<summary class="faq__q">
							<?php if ( $gadgetim_q['numbers'] ) : ?>
								<span class="faq__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( (int) $gadgetim_item['n'] ) ); ?></span>
							<?php endif; ?>
							<span class="faq__qt" data-faq-qt><?php echo $gadgetim_words ? gadgetim_pages_highlight( (string) $gadgetim_item['q'], (string) $gadgetim_q['query'] ) : gadgetim_bidi( (string) $gadgetim_item['q'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helpers. ?></span>
							<span class="faq__pm" aria-hidden="true"><i></i><i></i></span>
						</summary>
						<div class="faq__a">
							<div class="faq__ain prose"><?php echo wp_kses_post( (string) $gadgetim_item['a'] ); ?></div>
							<a class="faq__link" href="#<?php echo esc_attr( (string) $gadgetim_item['id'] ); ?>" data-copy-link data-copied="<?php esc_attr_e( 'Link copied', 'gadgetim' ); ?>"><?php echo gadgetim_pages_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Link to this answer', 'gadgetim' ); ?></span></a>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
	<?php if ( $gadgetim_search ) : ?>
		<div class="faq__none" data-faq-none<?php echo $gadgetim_words && 0 === $gadgetim_shown ? '' : ' hidden'; ?>>
			<span class="faq__none-ic" aria-hidden="true"><?php gadgetim_the_icon( 'search-x' ); ?></span>
			<p class="faq__none-t"><?php esc_html_e( 'No question matches your search', 'gadgetim' ); ?></p>
			<p class="faq__none-p"><?php esc_html_e( 'Try other words, or ask us directly. We are happy to help.', 'gadgetim' ); ?></p>
			<div class="faq__none-acts">
				<?php if ( '' !== $gadgetim_wa ) : ?>
					<a class="btn btn--success btn--sm" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Ask on WhatsApp', 'gadgetim' ); ?></span></a>
				<?php endif; ?>
				<?php if ( '' !== $gadgetim_contact ) : ?>
					<a class="btn btn--outline btn--sm" href="<?php echo esc_url( $gadgetim_contact ); ?>"><span><?php esc_html_e( 'Contact us', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
				<?php endif; ?>
				<button class="btn btn--ghost btn--sm" type="button" data-faq-clear hidden><?php gadgetim_the_icon( 'close' ); ?><span><?php esc_html_e( 'Clear the search', 'gadgetim' ); ?></span></button>
			</div>
		</div>
	<?php endif; ?>
</div>
