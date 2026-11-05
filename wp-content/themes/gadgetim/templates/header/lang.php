<?php
/**
 * Language switcher in the header bar (phones, tablets and desktop): the current language's flag
 * and code in a pill; a small menu with the three languages, each with its flag and native name.
 * Built on <details>, so it works without JS; app.js closes it on outside click and Escape.
 * Links come from the plugin (I18n\Hreflang::switcher) and point at the equivalent page.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_langs = gadgetim_language_links();
if ( ! $gadgetim_langs ) {
	return;
}
$gadgetim_flags = [
	'fa' => [ 'flag' => 'ir', 'code' => 'FA' ],
	'en' => [ 'flag' => 'gb', 'code' => 'EN' ],
	'ar' => [ 'flag' => 'ae', 'code' => 'AR' ],
];
$gadgetim_current = (string) gadgetim_lang();
foreach ( $gadgetim_langs as $gadgetim_code => $gadgetim_row ) {
	if ( ! empty( $gadgetim_row['current'] ) ) {
		$gadgetim_current = (string) $gadgetim_code;
	}
}
$gadgetim_cur      = $gadgetim_flags[ $gadgetim_current ] ?? [ 'flag' => '', 'code' => strtoupper( $gadgetim_current ) ];
$gadgetim_cur_name = (string) ( $gadgetim_langs[ $gadgetim_current ]['label'] ?? $gadgetim_cur['code'] );
?>
<details class="lang" data-lang>
	<summary class="lang__btn" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: current language name, e.g. فارسی */ __( 'Language: %s', 'gadgetim' ), $gadgetim_cur_name ) ); ?>">
		<?php if ( '' !== $gadgetim_cur['flag'] ) : ?>
			<svg class="flag" aria-hidden="true" focusable="false"><use href="#i-flag-<?php echo esc_attr( $gadgetim_cur['flag'] ); ?>"></use></svg>
		<?php endif; ?>
		<span class="lang__code" dir="ltr"><?php echo esc_html( $gadgetim_cur['code'] ); ?></span>
		<?php gadgetim_the_icon( 'chev-d', 'lang__caret' ); ?>
	</summary>
	<ul class="lang__menu" role="list">
		<?php foreach ( $gadgetim_langs as $gadgetim_code => $gadgetim_row ) : ?>
			<?php $gadgetim_f = $gadgetim_flags[ $gadgetim_code ] ?? [ 'flag' => '', 'code' => strtoupper( (string) $gadgetim_code ) ]; ?>
			<li>
				<a class="lang__item" href="<?php echo esc_url( (string) $gadgetim_row['url'] ); ?>" hreflang="<?php echo esc_attr( (string) $gadgetim_row['hreflang'] ); ?>" lang="<?php echo esc_attr( (string) $gadgetim_code ); ?>"<?php echo ! empty( $gadgetim_row['current'] ) ? ' aria-current="true"' : ''; ?>>
					<?php if ( '' !== $gadgetim_f['flag'] ) : ?>
						<svg class="flag flag--lg" aria-hidden="true" focusable="false"><use href="#i-flag-<?php echo esc_attr( $gadgetim_f['flag'] ); ?>"></use></svg>
					<?php endif; ?>
					<span class="lang__name"><?php echo esc_html( (string) $gadgetim_row['label'] ); ?></span>
					<span class="lang__tag" dir="ltr"><?php echo esc_html( $gadgetim_f['code'] ); ?></span>
					<?php if ( ! empty( $gadgetim_row['current'] ) ) : ?>
						<?php gadgetim_the_icon( 'check', 'lang__check' ); ?>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</details>
