<?php
/**
 * Template tags: icons, buttons, badges, prices, discount pills, rating stars, the product card
 * renderer, section heads, breadcrumbs, pagination, countdowns, empty states, the logo and the
 * contact helpers. Every function escapes its own output; functions named gadgetim_the_* echo,
 * the others return HTML strings.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/* ---------------------------------------------------------------------------
 * Low-level helpers
 * ------------------------------------------------------------------------- */

/**
 * Loads templates/<slug>.php with $args available to the part.
 */
function gadgetim_part( string $slug, array $args = [] ): void {
	get_template_part( 'templates/' . $slug, null, $args );
}

/**
 * Attribute string from a map; true → bare attribute, null/false → omitted; href/src/action escaped as URLs.
 *
 * @param array<string,mixed> $attrs Attributes.
 */
function gadgetim_attr( array $attrs ): string {
	$out = '';
	foreach ( $attrs as $name => $value ) {
		$name = strtolower( (string) preg_replace( '/[^A-Za-z0-9_:.-]/', '', (string) $name ) );
		if ( '' === $name || null === $value || false === $value ) {
			continue;
		}
		if ( true === $value ) {
			$out .= ' ' . $name;
			continue;
		}
		if ( is_array( $value ) ) {
			$value = implode( ' ', array_filter( array_map( 'strval', $value ) ) );
		}
		$value = (string) $value;
		if ( in_array( $name, [ 'href', 'src', 'action', 'poster' ], true ) ) {
			$out .= ' ' . $name . '="' . esc_url( $value ) . '"';
		} else {
			$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
	}
	return $out;
}

/**
 * Inline SVG icon from the sprite (assets/icons/sprite.svg, symbols "i-<name>").
 * Add the class "ic--dir" to icons that point forward/back (arrows, chevrons): they mirror in LTR.
 *
 * @param string $name  Icon name (search, user, heart, bag, menu, close, chev-l, chev-r, chev-d, arrow, …).
 * @param string $class Extra classes.
 * @param array  $attrs Extra attributes.
 */
function gadgetim_icon( string $name, string $class = '', array $attrs = [] ): string {
	$name  = sanitize_html_class( $name );
	$attrs = array_merge(
		[
			'class'       => trim( 'ic ' . $class ),
			'aria-hidden' => 'true',
			'focusable'   => 'false',
		],
		$attrs
	);
	return '<svg' . gadgetim_attr( $attrs ) . '><use href="#i-' . esc_attr( $name ) . '"></use></svg>';
}

/**
 * Echoes an icon.
 */
function gadgetim_the_icon( string $name, string $class = '' ): void {
	echo gadgetim_icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in gadgetim_icon().
}

/**
 * Escaped text with Latin runs (brand and model names) isolated in <bdi>, so neutral characters
 * never reorder around them in RTL; codes with a hyphen or slash (USB-C, OS-E02) never break.
 */
function gadgetim_bidi( string $text ): string {
	if ( 'ltr' === gadgetim_lang_dir() ) {
		return esc_html( $text );
	}
	$pattern = "/[A-Za-z0-9][A-Za-z0-9.+&'’\\/-]*(?:[ \u{00A0}][A-Za-z0-9][A-Za-z0-9.+&'’\\/-]*)*/u";
	$out     = '';
	$last    = 0;
	if ( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[0] as [ $run, $offset ] ) {
			// Pure numbers inside Persian wording stay in the flow (they are digit-converted later).
			if ( preg_match( '/^[0-9.\/]+$/', $run ) ) {
				continue;
			}
			$out  .= esc_html( substr( $text, $last, $offset - $last ) );
			$words = array_map(
				static fn( string $w ): string => preg_match( '#[-/]#', $w ) ? '<span class="nw">' . esc_html( $w ) . '</span>' : esc_html( $w ),
				explode( ' ', $run )
			);
			$out  .= '<bdi>' . implode( ' ', $words ) . '</bdi>';
			$last  = $offset + strlen( $run );
		}
	}
	return $out . esc_html( substr( $text, $last ) );
}

/**
 * Allowed inline HTML for short settings texts.
 */
function gadgetim_kses_inline( string $html ): string {
	return wp_kses(
		$html,
		[
			'a'      => [
				'href'   => true,
				'title'  => true,
				'rel'    => true,
				'target' => true,
			],
			'strong' => [],
			'b'      => [],
			'em'     => [],
			'i'      => [],
			'span'   => [
				'class' => true,
				'dir'   => true,
				'lang'  => true,
			],
			'bdi'    => [],
			'br'     => [],
		]
	);
}

/**
 * Persian/Arabic digits → ASCII.
 */
function gadgetim_ascii_digits( string $text ): string {
	return str_replace(
		[ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ],
		[ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
		$text
	);
}

/**
 * Percentage in the language's digits and sign: «۱۵٪», "15%".
 */
function gadgetim_percent( int $value ): string {
	return gadgetim_number( $value ) . ( 'en' === gadgetim_lang() ? '%' : "\u{066A}" );
}

/* ---------------------------------------------------------------------------
 * Product data helpers
 * ------------------------------------------------------------------------- */

/**
 * Resolves a product from an object, ID or post (null without WooCommerce).
 *
 * @param WC_Product|WP_Post|int|null $subject Subject.
 */
function gadgetim_product( $subject = null ): ?WC_Product {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}
	if ( $subject instanceof WC_Product ) {
		return $subject;
	}
	if ( null === $subject ) {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		$subject = get_the_ID();
	}
	$found = wc_get_product( $subject instanceof WP_Post ? $subject->ID : $subject );
	return $found instanceof WC_Product ? $found : null;
}

/**
 * Short title (ProductExtras::short_title: translated title or _gtm_short_title or the name with
 * the authenticity boilerplate stripped).
 *
 * @param WC_Product|WP_Post|int $subject Product.
 */
function gadgetim_short_title( $subject ): string {
	if ( class_exists( 'Gadgetim\Core\Catalog\ProductExtras' ) ) {
		$short = (string) Gadgetim\Core\Catalog\ProductExtras::short_title( $subject instanceof WC_Product || $subject instanceof WP_Post ? $subject : (int) $subject );
		$product = gadgetim_product( $subject );
		return (string) apply_filters( 'gadgetim_short_title', $short, $product );
	}
	$product = gadgetim_product( $subject );
	if ( ! $product ) {
		return '';
	}
	$short = trim( (string) get_post_meta( $product->get_id(), '_gtm_short_title', true ) );
	if ( '' === $short ) {
		$short = (string) preg_replace( '/\s*[|،,\-–—]?\s*(اصل و اورجینال|با ضمانت اصالت کالا|ضمانت اصالت کالا|اورجینال)\s*/u', ' ', (string) $product->get_name() );
		$short = trim( (string) preg_replace( '/^[\s|،,\-–—]+|[\s|،,\-–—]+$/u', '', (string) preg_replace( '/\s{2,}/u', ' ', $short ) ) );
		if ( '' === $short ) {
			$short = (string) $product->get_name();
		}
	}
	/**
	 * Filters a product's short title.
	 *
	 * @param string          $short   Short title.
	 * @param WC_Product|null $product Product.
	 */
	return (string) apply_filters( 'gadgetim_short_title', $short, $product );
}

/**
 * The product's brand term (native product_brand taxonomy), or null.
 */
function gadgetim_product_brand( WC_Product $product ): ?WP_Term {
	if ( class_exists( 'Gadgetim\Core\Catalog\Brands' ) ) {
		$term = Gadgetim\Core\Catalog\Brands::primary( $product );
		if ( $term instanceof WP_Term ) {
			$kept = (array) apply_filters( 'gadgetim_brand_terms', [ $term ], gadgetim_lang() );
			return $kept ? $term : null;
		}
		return null;
	}
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return null;
	}
	$terms = get_the_terms( $product->get_id(), 'product_brand' );
	if ( ! is_array( $terms ) || ! $terms ) {
		return null;
	}
	$terms = (array) apply_filters( 'gadgetim_brand_terms', $terms, gadgetim_lang() );
	$term  = reset( $terms );
	return $term instanceof WP_Term ? $term : null;
}

/**
 * The product's most specific category (the default category skipped), or null.
 */
function gadgetim_product_lane( WC_Product $product ): ?WP_Term {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	if ( ! is_array( $terms ) || ! $terms ) {
		return null;
	}
	$terms   = (array) apply_filters( 'gadgetim_menu_terms', $terms, gadgetim_lang() );
	$default = (int) get_option( 'default_product_cat' );
	$best    = null;
	$depth   = -1;
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term || $term->term_id === $default ) {
			continue;
		}
		$d = count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
		if ( $d > $depth ) {
			$depth = $d;
			$best  = $term;
		}
	}
	return $best;
}

/**
 * Sale thresholds: struck old price at ≥ strike %, discount pill/sticker at ≥ sticker % (design §5.5).
 *
 * @return array{strike:int,sticker:int}
 */
function gadgetim_sale_thresholds(): array {
	if ( class_exists( 'Gadgetim\Core\Pricing\Display' ) && method_exists( 'Gadgetim\Core\Pricing\Display', 'thresholds' ) ) {
		$t = (array) Gadgetim\Core\Pricing\Display::thresholds();
	} else {
		$t = [
			'strike'  => (int) gadgetim_setting( 'pricing.strike_threshold', 10 ),
			'sticker' => (int) gadgetim_setting( 'pricing.sticker_threshold', 15 ),
		];
	}
	return [
		'strike'  => (int) ( $t['strike'] ?? 10 ),
		'sticker' => (int) ( $t['sticker'] ?? 15 ),
	];
}

/**
 * Discount percentage from the final Toman pair (0 when not on sale).
 */
function gadgetim_product_discount( WC_Product $product ): int {
	$off = 0;
	if ( $product->is_on_sale() ) {
		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_regular_price' ) ) {
			$was = (float) $product->get_variation_regular_price( 'min' );
			$now = (float) $product->get_variation_price( 'min' );
		} else {
			$was = (float) $product->get_regular_price();
			$now = (float) $product->get_price();
		}
		if ( $was > 0 && $now >= 0 && $now < $was ) {
			$off = class_exists( 'Gadgetim\Core\Pricing\Calculator' ) && method_exists( 'Gadgetim\Core\Pricing\Calculator', 'percent_off' )
				? (int) Gadgetim\Core\Pricing\Calculator::percent_off( $was, $now )
				: (int) round( ( $was - $now ) / $was * 100 );
		}
	}
	/**
	 * Filters the discount percentage shown for a product.
	 *
	 * @param int        $off     Percentage.
	 * @param WC_Product $product Product.
	 */
	return (int) apply_filters( 'gadgetim_product_discount', $off, $product );
}

/**
 * Amount saved on a sale (Toman, 0 when not on sale).
 */
function gadgetim_product_saving( WC_Product $product ): float {
	if ( ! $product->is_on_sale() || $product->is_type( 'variable' ) ) {
		return 0.0;
	}
	return max( 0.0, (float) $product->get_regular_price() - (float) $product->get_price() );
}

/**
 * Real sale end timestamp of a product (WooCommerce's scheduled "sale price dates to"), 0 when none or past.
 */
function gadgetim_sale_end( WC_Product $product ): int {
	$to = $product->get_date_on_sale_to();
	$ts = $to ? $to->getTimestamp() : 0;
	return $ts > time() ? $ts : 0;
}

/**
 * Whether the product was published in the last N days (filter gadgetim_new_days, default 30).
 */
function gadgetim_is_new( WC_Product $product ): bool {
	$created = $product->get_date_created();
	if ( ! $created ) {
		return false;
	}
	$days = (int) apply_filters( 'gadgetim_new_days', 30 );
	return $created->getTimestamp() > ( time() - $days * DAY_IN_SECONDS );
}

/**
 * Managed stock quantity when it is low (product page, instant offer, cart: 1…3, filter
 * gadgetim_low_stock_max; product cards: 1, filter gadgetim_card_low_stock_max), else 0.
 */
function gadgetim_low_stock( WC_Product $product, string $context = 'page' ): int {
	if ( ! $product->is_in_stock() || ! $product->managing_stock() ) {
		return 0;
	}
	$qty = (int) $product->get_stock_quantity();
	// Cards: only the very last items (most of the catalogue holds 1-3, so "Only 3 left" on every card is noise).
	$max = 'card' === $context
		? (int) apply_filters( 'gadgetim_card_low_stock_max', 1 )
		: (int) apply_filters( 'gadgetim_low_stock_max', 3 );
	return ( $qty > 0 && $qty <= $max ) ? $qty : 0;
}

/**
 * Call-for-price mode: ContactMode::applies() for a product, or the global toggle without a product.
 */
function gadgetim_contact_mode( ?WC_Product $product = null ): bool {
	if ( class_exists( 'Gadgetim\Core\ContactMode\ContactMode' ) ) {
		$cls = 'Gadgetim\Core\ContactMode\ContactMode';
		return $product ? (bool) $cls::applies( $product ) : (bool) $cls::enabled();
	}
	$enabled = (bool) gadgetim_setting( 'contact.enabled', false );
	return (bool) apply_filters( 'gadgetim_contact_mode', $enabled, $product );
}

/* ---------------------------------------------------------------------------
 * Prices, pills, stars, badges, buttons
 * ------------------------------------------------------------------------- */

/**
 * Price block for a product: the plugin's price partial (no base hint unless asked), or the
 * contact-mode slip (compact on cards, full on the product page).
 *
 * @param WC_Product $product Product.
 * @param array      $opts    gadgetim_price() options plus 'slip' => 'compact'|'full'.
 */
function gadgetim_price_html( WC_Product $product, array $opts = [] ): string {
	$slip = (string) ( $opts['slip'] ?? 'compact' );
	unset( $opts['slip'] );
	$opts = array_merge(
		[
			'tag'       => 'div',
			'context'   => 'card',
			'base_hint' => false,
		],
		$opts
	);
	$price = gadgetim_price( $product, $opts );
	if ( gadgetim_contact_mode( $product ) && class_exists( 'Gadgetim\Core\ContactMode\Display' ) ) {
		return (string) Gadgetim\Core\ContactMode\Display::slip( $product, $slip, $price );
	}
	return $price;
}

/**
 * Echoes the price block.
 *
 * @param WC_Product|WP_Post|int|null $subject Product.
 * @param array                       $opts    Options (see gadgetim_price_html()).
 */
function gadgetim_the_price( $subject = null, array $opts = [] ): void {
	$product = gadgetim_product( $subject );
	if ( $product ) {
		echo gadgetim_price_html( $product, $opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}

/**
 * Discount pill «۱۵٪» (red gradient), only at or above the sticker threshold ('' otherwise).
 *
 * @param WC_Product|int $subject Product or a percentage.
 * @param string         $class   Extra classes (off--sm, off--lg).
 */
function gadgetim_discount_pill( $subject, string $class = '' ): string {
	$off = $subject instanceof WC_Product ? gadgetim_product_discount( $subject ) : (int) $subject;
	if ( $off <= 0 || $off < gadgetim_sale_thresholds()['sticker'] ) {
		return '';
	}
	return '<span class="' . esc_attr( trim( 'off ' . $class ) ) . '"><span class="sr-only">' . esc_html__( 'Discount', 'gadgetim' ) . ' </span>' . esc_html( gadgetim_percent( $off ) ) . '</span>';
}

/**
 * Rating stars + count, only when the product has reviews ('' otherwise).
 *
 * @param WC_Product $product Product.
 * @param array      $args    link (bool: link to #reviews), count (bool, default true), class.
 */
function gadgetim_rating_stars( WC_Product $product, array $args = [] ): string {
	$count = (int) $product->get_review_count();
	if ( $count <= 0 || ( function_exists( 'wc_review_ratings_enabled' ) && ! wc_review_ratings_enabled() ) ) {
		return '';
	}
	$average = (float) $product->get_average_rating();
	/* translators: 1: average rating, 2: number of reviews */
	$label = sprintf( __( 'Rated %1$s out of 5 from %2$s reviews', 'gadgetim' ), gadgetim_number( $average, 1 ), gadgetim_number( $count ) );
	$stars = '<span class="stars" role="img" aria-label="' . esc_attr( $label ) . '" style="--rating:' . esc_attr( (string) round( $average, 2 ) ) . '">'
		. '<span class="stars__track" aria-hidden="true">' . str_repeat( gadgetim_icon( 'star' ), 5 ) . '</span>'
		. '<span class="stars__fill" aria-hidden="true">' . str_repeat( gadgetim_icon( 'star' ), 5 ) . '</span></span>';
	if ( false !== ( $args['count'] ?? true ) ) {
		$stars .= '<span class="rating__n" aria-hidden="true">' . esc_html( gadgetim_number( $average, 1 ) ) . ' <small>(' . esc_html( gadgetim_number( $count ) ) . ')</small></span>';
	}
	$class = trim( 'rating ' . (string) ( $args['class'] ?? '' ) );
	return ! empty( $args['link'] )
		? '<a class="' . esc_attr( $class ) . '" href="#reviews">' . $stars . '</a>'
		: '<span class="' . esc_attr( $class ) . '">' . $stars . '</span>';
}

/**
 * Small badge/tag. Variants: soft (violet on white), new (sparkle tag), hot (red), success, warn, dark, glass.
 *
 * @param string $text    Text.
 * @param string $variant Variant.
 * @param array  $args    icon, class, attrs.
 */
function gadgetim_badge( string $text, string $variant = 'soft', array $args = [] ): string {
	$attrs          = (array) ( $args['attrs'] ?? [] );
	$attrs['class'] = trim( 'tag tag--' . sanitize_html_class( $variant ) . ' ' . (string) ( $args['class'] ?? '' ) );
	$icon           = ! empty( $args['icon'] ) ? gadgetim_icon( (string) $args['icon'] ) : '';
	return '<span' . gadgetim_attr( $attrs ) . '>' . $icon . esc_html( $text ) . '</span>';
}

/**
 * Round amber sticker («تا / ۵۷٪ / تخفیف»), tilted, with a wobble.
 *
 * @param string $big   Big middle line (e.g. the percentage).
 * @param string $top   Small top line.
 * @param string $after Small bottom line.
 * @param string $class Extra classes (sticker--lg, sticker--static).
 */
function gadgetim_sticker( string $big, string $top = '', string $after = '', string $class = '' ): string {
	return '<span class="' . esc_attr( trim( 'sticker ' . $class ) ) . '"><span>'
		. ( '' !== $top ? esc_html( $top ) : '' ) . '<b>' . esc_html( $big ) . '</b>' . ( '' !== $after ? esc_html( $after ) : '' )
		. '</span></span>';
}

/**
 * Button or link button.
 *
 * @param string $label Label (plain text).
 * @param array  $args  href (link when set), variant (primary|white|soft|ghost|outline|success),
 *                      size (sm|md|lg), icon, icon_start (bool: icon before the label), glow (bool),
 *                      block (bool), type (button|submit), class, attrs.
 */
function gadgetim_button( string $label, array $args = [] ): string {
	$classes = [ 'btn' ];
	$variant = (string) ( $args['variant'] ?? 'primary' );
	if ( 'primary' !== $variant ) {
		$classes[] = 'btn--' . sanitize_html_class( $variant );
	}
	if ( ! empty( $args['size'] ) && 'md' !== $args['size'] ) {
		$classes[] = 'btn--' . sanitize_html_class( (string) $args['size'] );
	}
	if ( ! empty( $args['glow'] ) ) {
		$classes[] = 'btn--glow';
	}
	if ( ! empty( $args['block'] ) ) {
		$classes[] = 'btn--block';
	}
	if ( ! empty( $args['class'] ) ) {
		$classes[] = (string) $args['class'];
	}
	$icon  = ! empty( $args['icon'] ) ? gadgetim_icon( (string) $args['icon'], in_array( $args['icon'], [ 'arrow', 'chev-l', 'chev-r' ], true ) ? 'ic--dir' : '' ) : '';
	$inner = ! empty( $args['icon_start'] ) ? $icon . '<span>' . esc_html( $label ) . '</span>' : '<span>' . esc_html( $label ) . '</span>' . $icon;
	$attrs = array_merge( [ 'class' => implode( ' ', $classes ) ], (array) ( $args['attrs'] ?? [] ) );
	if ( ! empty( $args['href'] ) ) {
		$attrs = array_merge( [ 'href' => (string) $args['href'] ], $attrs );
		return '<a' . gadgetim_attr( $attrs ) . '>' . $inner . '</a>';
	}
	$attrs = array_merge( [ 'type' => (string) ( $args['type'] ?? 'button' ) ], $attrs );
	return '<button' . gadgetim_attr( $attrs ) . '>' . $inner . '</button>';
}

/**
 * Card add-to-cart control: a real ?add-to-cart link that cart.js upgrades (fly-to-cart, badge
 * bump, toast); the product page for variable products; nothing when the product cannot be bought.
 *
 * @param WC_Product $product Product.
 * @param array      $args    wide (bool: icon + label), class.
 */
function gadgetim_add_button( WC_Product $product, array $args = [] ): string {
	$title = gadgetim_short_title( $product );
	$wide  = ! empty( $args['wide'] );
	$class = trim( 'add' . ( $wide ? ' add--wide' : '' ) . ' ' . (string) ( $args['class'] ?? '' ) );
	if ( gadgetim_contact_mode( $product ) ) {
		$url = gadgetim_whatsapp_url( $title . ' ' . $product->get_permalink() );
		if ( '' === $url ) {
			return '';
		}
		/* translators: %s: product title */
		$label = sprintf( __( 'Ask about “%s” on WhatsApp', 'gadgetim' ), $title );
		return '<a class="' . esc_attr( $class . ' add--contact' ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow" aria-label="' . esc_attr( $label ) . '">' . gadgetim_icon( 'chat' ) . ( $wide ? '<span>' . esc_html__( 'Ask', 'gadgetim' ) . '</span>' : '' ) . '</a>';
	}
	if ( ! $product->is_in_stock() ) {
		return '';
	}
	if ( $product->is_type( 'simple' ) && $product->is_purchasable() ) {
		/* translators: %s: product title */
		$label = sprintf( __( 'Add “%s” to cart', 'gadgetim' ), $title );
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $product->add_to_cart_url() ) . '" rel="nofollow" role="button" data-add-to-cart="' . esc_attr( (string) $product->get_id() ) . '" data-quantity="1" aria-label="' . esc_attr( $label ) . '">'
			. gadgetim_icon( 'plus', 'ic-plus' ) . gadgetim_icon( 'check', 'ic-check' )
			. ( $wide ? '<span>' . esc_html__( 'Buy', 'gadgetim' ) . '</span>' : '' ) . '</a>';
	}
	/* translators: %s: product title */
	$label = sprintf( __( 'Choose options for “%s”', 'gadgetim' ), $title );
	return '<a class="' . esc_attr( $class . ' add--options' ) . '" href="' . esc_url( $product->get_permalink() ) . '" aria-label="' . esc_attr( $label ) . '">' . gadgetim_icon( 'arrow', 'ic--dir' ) . ( $wide ? '<span>' . esc_html__( 'Options', 'gadgetim' ) . '</span>' : '' ) . '</a>';
}

/**
 * Wishlist heart (localStorage list; app.js syncs aria-pressed and the header badge).
 */
function gadgetim_wish_button( WC_Product $product, string $class = 'pc__wish' ): string {
	/* translators: %s: product title */
	$label = sprintf( __( 'Save “%s” to your wishlist', 'gadgetim' ), gadgetim_short_title( $product ) );
	return '<button class="' . esc_attr( $class ) . '" type="button" data-wish="' . esc_attr( (string) $product->get_id() ) . '" aria-pressed="false" aria-label="' . esc_attr( $label ) . '">' . gadgetim_icon( 'heart' ) . '</button>';
}

/* ---------------------------------------------------------------------------
 * Product card
 * ------------------------------------------------------------------------- */

/**
 * Product card HTML.
 *
 * Variants:
 *   default  grid card (.pc): packshot, second image crossfade on hover, discount pill + "new" tag,
 *            wishlist heart, brand, two-line title, low stock / rating, price + animated add button.
 *   rail     the same card sized for horizontal rails (.pc.pc--rail, sizes "rail").
 *   compact  horizontal mini card (.pcx): 72 px thumb, title, price, small add button.
 *   rank     ranked row (.rk): thumb, outlined rank number (gradient for the top three), title, price.
 *   mini     plain link row (.pmini): 52 px thumb, title, price (search, drawers, mega menu).
 *
 * @param WC_Product|WP_Post|int $subject Product.
 * @param string                 $variant default|rail|compact|rank|mini.
 * @param array                  $args    index (int, stagger + rank number), reveal (bool, default true for
 *                                        default/rail/rank), eager (bool), sizes, class, tag (li|div|article,
 *                                        default li), new (bool|null: force the "new" tag), wish (bool, default true).
 */
function gadgetim_product_card( $subject, string $variant = 'default', array $args = [] ): string {
	$product = gadgetim_product( $subject );
	if ( ! $product || ( ! $product->is_visible() && empty( $args['force'] ) ) ) {
		return '';
	}
	$variant = in_array( $variant, [ 'default', 'rail', 'compact', 'rank', 'mini' ], true ) ? $variant : 'default';
	$index   = (int) ( $args['index'] ?? 0 );
	$tag     = in_array( $args['tag'] ?? 'li', [ 'li', 'div', 'article' ], true ) ? (string) ( $args['tag'] ?? 'li' ) : 'li';
	$url     = (string) $product->get_permalink();
	$title   = gadgetim_short_title( $product );
	$in      = $product->is_in_stock();
	$loading = ! empty( $args['eager'] ) ? [ 'loading' => 'eager' ] : [];

	$html = match ( $variant ) {
		'mini'    => gadgetim_card_mini( $product, $url, $title, $args, $loading ),
		'compact' => gadgetim_card_compact( $product, $url, $title, $args, $loading ),
		'rank'    => gadgetim_card_rank( $product, $url, $title, $index, $args, $loading ),
		default   => gadgetim_card_default( $product, $variant, $url, $title, $in, $args, $loading ),
	};

	$reveal  = $args['reveal'] ?? in_array( $variant, [ 'default', 'rail', 'rank' ], true );
	$classes = [
		'mini'    => 'pmini-li',
		'compact' => 'pcx-li',
		'rank'    => 'rk-li',
		'default' => 'pc',
		'rail'    => 'pc pc--rail',
	][ $variant ];
	if ( ! $in && in_array( $variant, [ 'default', 'rail' ], true ) ) {
		$classes .= ' is-out';
	}
	if ( ! empty( $args['class'] ) ) {
		$classes .= ' ' . (string) $args['class'];
	}
	$attrs = [
		'class'       => $classes,
		'data-reveal' => $reveal ? true : null,
		'style'       => $reveal ? '--i:' . min( $index, 7 ) : null,
	];
	return '<' . $tag . gadgetim_attr( $attrs ) . '>' . $html . '</' . $tag . '>';
}

/**
 * Echoes a product card.
 *
 * @param WC_Product|WP_Post|int $subject Product.
 * @param string                 $variant Variant.
 * @param array                  $args    Arguments.
 */
function gadgetim_the_product_card( $subject, string $variant = 'default', array $args = [] ): void {
	echo gadgetim_product_card( $subject, $variant, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
}

/**
 * Default / rail card body.
 *
 * @param array $args    Card args.
 * @param array $loading Loading override.
 */
function gadgetim_card_default( WC_Product $product, string $variant, string $url, string $title, bool $in, array $args, array $loading ): string {
	$sizes  = (string) ( $args['sizes'] ?? ( 'rail' === $variant ? 'rail' : 'card' ) );
	$img2id = gadgetim_product_image_id( $product, 1 );
	$img1   = gadgetim_product_image(
		$product,
		'gtm-card',
		array_merge(
			[
				'class' => 'pc__img' . ( $img2id ? ' pc__img--has2' : '' ),
				'sizes' => $sizes,
			],
			$loading
		)
	);
	$img2   = $img2id ? gadgetim_product_image(
		$img2id,
		'gtm-card',
		[
			'class'   => 'pc__img2',
			'alt'     => '',
			'sizes'   => $sizes,
			'loading' => 'lazy',
			'lqip'    => false,
		]
	) : '';

	$contact = gadgetim_contact_mode( $product );
	$flags   = '';
	if ( $in && ! $contact ) {
		$flags .= gadgetim_discount_pill( $product );
	}
	$is_new = $args['new'] ?? gadgetim_is_new( $product );
	if ( $is_new && $in ) {
		$flags .= gadgetim_badge( __( 'New', 'gadgetim' ), 'new', [ 'icon' => 'sparkle' ] );
	}

	$brand      = gadgetim_product_brand( $product );
	$brand_html = $brand
		? '<span class="pc__brand" dir="auto">' . esc_html( gadgetim_term_name( $brand ) ) . '</span>'
		: '<span class="pc__brand" aria-hidden="true"></span>';

	$meta = '';
	$low  = gadgetim_low_stock( $product, 'card' );
	if ( $low > 0 ) {
		/* translators: %s: number of items left */
		$meta .= '<span class="pc__low"><i class="dotty" aria-hidden="true"></i>' . esc_html( sprintf( __( 'Only %s left', 'gadgetim' ), gadgetim_number( $low ) ) ) . '</span>';
	}
	$meta .= gadgetim_rating_stars( $product, [ 'class' => 'pc__rate' ] );

	if ( ! $in ) {
		$foot = '<span class="soldout">' . esc_html__( 'Out of stock', 'gadgetim' ) . '</span>';
	} else {
		$foot = gadgetim_price_html( $product ) . gadgetim_add_button( $product );
	}

	return '<article class="pc__in" data-product-id="' . esc_attr( (string) $product->get_id() ) . '">'
		. '<div class="pc__media">' . $img1 . $img2 . '</div>'
		. ( '' !== $flags ? '<div class="pc__flags">' . $flags . '</div>' : '' )
		. ( false !== ( $args['wish'] ?? true ) ? gadgetim_wish_button( $product ) : '' )
		. '<div class="pc__body">'
		. $brand_html
		. '<h3 class="pc__title"><a href="' . esc_url( $url ) . '">' . gadgetim_bidi( $title ) . '</a></h3>'
		. '<div class="pc__meta">' . $meta . '</div>'
		. '<div class="pc__foot">' . $foot . '</div>'
		. '</div></article>';
}

/**
 * Compact (horizontal) card.
 *
 * @param array $args    Card args.
 * @param array $loading Loading override.
 */
function gadgetim_card_compact( WC_Product $product, string $url, string $title, array $args, array $loading ): string {
	$img  = gadgetim_product_image( $product, 'gtm-thumb', array_merge( [ 'sizes' => 'thumb', 'alt' => '' ], $loading ) );
	$pill = $product->is_in_stock() && ! gadgetim_contact_mode( $product ) ? gadgetim_discount_pill( $product, 'off--sm' ) : '';
	$foot = $product->is_in_stock()
		? gadgetim_price_html( $product ) . gadgetim_add_button( $product, [ 'class' => 'add--sm' ] )
		: '<span class="soldout">' . esc_html__( 'Out of stock', 'gadgetim' ) . '</span>';
	return '<div class="pcx" data-product-id="' . esc_attr( (string) $product->get_id() ) . '">'
		. '<span class="pcx__img">' . $img . $pill . '</span>'
		. '<span class="pcx__body"><a class="pcx__t" href="' . esc_url( $url ) . '">' . gadgetim_bidi( $title ) . '</a>'
		. '<span class="pcx__foot">' . $foot . '</span></span></div>';
}

/**
 * Ranked row.
 *
 * @param array $args    Card args.
 * @param array $loading Loading override.
 */
function gadgetim_card_rank( WC_Product $product, string $url, string $title, int $index, array $args, array $loading ): string {
	$rank = (int) ( $args['rank'] ?? ( $index + 1 ) );
	$img  = gadgetim_product_image( $product, 'gtm-thumb', array_merge( [ 'sizes' => 'rank' ], $loading ) );
	$pill = $product->is_in_stock() && ! gadgetim_contact_mode( $product ) ? gadgetim_discount_pill( $product, 'off--sm' ) : '';
	$price = $product->is_in_stock() ? gadgetim_price_html( $product ) : '<span class="soldout">' . esc_html__( 'Out of stock', 'gadgetim' ) . '</span>';
	return '<div class="rk' . ( $rank <= 3 ? ' rk--top' : '' ) . '">'
		. '<span class="rk__img">' . $img . '</span>'
		. '<span class="rk__n" aria-hidden="true">' . esc_html( gadgetim_number( $rank ) ) . '</span>'
		/* translators: %s: rank number */
		. '<div class="rk__body"><h3 class="rk__t"><span class="sr-only">' . esc_html( sprintf( __( 'Rank %s:', 'gadgetim' ), gadgetim_number( $rank ) ) ) . ' </span><a href="' . esc_url( $url ) . '">' . gadgetim_bidi( $title ) . '</a></h3>'
		. '<div class="rk__p">' . $pill . $price . '</div></div></div>';
}

/**
 * Mini link row.
 *
 * @param array $args    Card args.
 * @param array $loading Loading override.
 */
function gadgetim_card_mini( WC_Product $product, string $url, string $title, array $args, array $loading ): string {
	$img   = gadgetim_product_image( $product, 'gtm-thumb', array_merge( [ 'sizes' => 'mini', 'alt' => '' ], $loading ) );
	$price = $product->is_in_stock() ? gadgetim_price_html( $product, [ 'tag' => 'span' ] ) : '<span class="soldout">' . esc_html__( 'Out of stock', 'gadgetim' ) . '</span>';
	return '<a class="pmini" href="' . esc_url( $url ) . '">' . $img . '<span class="pmini__t">' . gadgetim_bidi( $title ) . '</span><span class="pmini__p">' . $price . '</span></a>';
}

/**
 * Card skeleton (same box as the default card) for JS-filled grids.
 */
function gadgetim_card_skeleton( string $tag = 'li' ): string {
	$tag = in_array( $tag, [ 'li', 'div' ], true ) ? $tag : 'li';
	return '<' . $tag . ' class="pc pc--sk" aria-hidden="true"><div class="pc__in"><div class="pc__media sk"></div><div class="pc__body"><i class="sk sk--b"></i><i class="sk sk--t"></i><i class="sk sk--t2"></i><span class="pc__foot"><i class="sk sk--p"></i></span></div></div></' . $tag . '>';
}

/* ---------------------------------------------------------------------------
 * Sections, navigation
 * ------------------------------------------------------------------------- */

/**
 * Section heading: gradient icon tile, title + sub line, «مشاهده همه» link and optional rail arrows.
 *
 * @param array $args title (required), sub, id (heading id), icon (default sparkle), tone (violet|warm|cool|gold|pink|green),
 *                    more_url, more_label (default "View all"), rail (rail element id → prev/next buttons),
 *                    tag (h2), center (bool), class, reveal (bool, default true), after (raw HTML, e.g. tabs).
 */
function gadgetim_section_head( array $args ): string {
	$tag     = preg_match( '/^h[1-6]$/', (string) ( $args['tag'] ?? '' ) ) ? (string) $args['tag'] : 'h2';
	$tone    = sanitize_html_class( (string) ( $args['tone'] ?? 'violet' ) );
	$classes = [ 'sect__head' ];
	if ( ! empty( $args['center'] ) ) {
		$classes[] = 'sect__head--center';
	}
	if ( ! empty( $args['class'] ) ) {
		$classes[] = (string) $args['class'];
	}
	$reveal = false !== ( $args['reveal'] ?? true );
	$id     = (string) ( $args['id'] ?? '' );
	$html   = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $reveal ? ' data-reveal' : '' ) . '>';
	$html  .= '<span class="sect__ic sect__ic--' . esc_attr( $tone ) . '">' . gadgetim_icon( (string) ( $args['icon'] ?? 'sparkle' ) ) . '</span>';
	// .sect__tt carries the gradient underline that draws itself once the head is revealed (fx.css).
	$html  .= '<div class="sect__titles"><' . $tag . ' class="sect__title"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '><span class="sect__tt">' . esc_html( (string) ( $args['title'] ?? '' ) ) . '</span></' . $tag . '>';
	if ( ! empty( $args['sub'] ) ) {
		$html .= '<p class="sect__sub">' . esc_html( (string) $args['sub'] ) . '</p>';
	}
	$html .= '</div>';
	if ( ! empty( $args['more_url'] ) ) {
		$label = (string) ( $args['more_label'] ?? __( 'View all', 'gadgetim' ) );
		$html .= '<a class="sect__more" href="' . esc_url( (string) $args['more_url'] ) . '">' . esc_html( $label ) . gadgetim_icon( 'arrow', 'ic--dir' ) . '</a>';
	}
	if ( ! empty( $args['rail'] ) ) {
		$html .= gadgetim_rail_nav( (string) $args['rail'] );
	}
	if ( ! empty( $args['after'] ) ) {
		$html .= (string) $args['after'];
	}
	return $html . '</div>';
}

/**
 * Echoes a section heading.
 *
 * @param array $args See gadgetim_section_head().
 */
function gadgetim_the_section_head( array $args ): void {
	echo gadgetim_section_head( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
}

/**
 * Prev/next buttons for a rail (carousel.js wires [data-rail]; disabled at the edges).
 *
 * @param string $rail_id Rail element id.
 * @param string $style   light (white round) | glass (on gradients).
 */
function gadgetim_rail_nav( string $rail_id, string $style = 'light' ): string {
	$cls = 'glass' === $style ? 'rail-arrow' : 'rail-btn';
	return '<div class="rail-nav">'
		. '<button class="' . $cls . '" type="button" data-rail="' . esc_attr( $rail_id ) . '" data-dir="prev" aria-label="' . esc_attr__( 'Previous items', 'gadgetim' ) . '" disabled>' . gadgetim_icon( 'chev-r', 'ic--dir' ) . '</button>'
		. '<button class="' . $cls . '" type="button" data-rail="' . esc_attr( $rail_id ) . '" data-dir="next" aria-label="' . esc_attr__( 'Next items', 'gadgetim' ) . '">' . gadgetim_icon( 'chev-l', 'ic--dir' ) . '</button>'
		. '</div>';
}

/**
 * Breadcrumbs: WooCommerce's trail on shop pages, a simple trail elsewhere. Nothing on the home page.
 */
function gadgetim_breadcrumbs(): void {
	if ( is_front_page() ) {
		return;
	}
	$crumbs = [];
	if ( class_exists( 'WC_Breadcrumb' ) && function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		$breadcrumbs = new WC_Breadcrumb();
		$breadcrumbs->add_crumb( __( 'Home', 'gadgetim' ), home_url( '/' ) );
		foreach ( $breadcrumbs->generate() as $crumb ) {
			$crumbs[] = [ wp_strip_all_tags( (string) ( $crumb[0] ?? '' ) ), (string) ( $crumb[1] ?? '' ) ];
		}
	} else {
		$crumbs[] = [ __( 'Home', 'gadgetim' ), home_url( '/' ) ];
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				if ( 'page' === $post->post_type ) {
					foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
						$crumbs[] = [ get_the_title( $ancestor ), (string) get_permalink( $ancestor ) ];
					}
				} elseif ( 'post' === $post->post_type && (int) get_option( 'page_for_posts' ) > 0 ) {
					$blog     = (int) get_option( 'page_for_posts' );
					$crumbs[] = [ get_the_title( $blog ), (string) get_permalink( $blog ) ];
				}
				$crumbs[] = [ get_the_title( $post ), '' ];
			}
		} elseif ( is_search() ) {
			$crumbs[] = [ __( 'Search results', 'gadgetim' ), '' ];
		} elseif ( is_404() ) {
			$crumbs[] = [ __( 'Page not found', 'gadgetim' ), '' ];
		} elseif ( is_archive() || is_home() ) {
			$crumbs[] = [ wp_strip_all_tags( get_the_archive_title() ), '' ];
		}
	}
	gadgetim_render_crumbs( (array) apply_filters( 'gadgetim_breadcrumbs', $crumbs ) );
}

/**
 * Prints a breadcrumb list from [ [ label, url ], … ] (the last item is the current page).
 *
 * @param array<int,array{0:string,1:string}> $crumbs Crumbs.
 */
function gadgetim_render_crumbs( array $crumbs ): void {
	if ( count( $crumbs ) < 2 ) {
		return;
	}
	$last = count( $crumbs ) - 1;
	echo '<nav class="crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'gadgetim' ) . '"><ol class="crumbs__list">';
	foreach ( array_values( $crumbs ) as $i => $crumb ) {
		$label = (string) ( $crumb[0] ?? '' );
		$url   = (string) ( $crumb[1] ?? '' );
		echo '<li class="crumbs__item">';
		if ( $i === $last || '' === $url ) {
			echo '<span class="crumbs__current" aria-current="page">' . gadgetim_bidi( $label ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi().
		} else {
			echo '<a class="crumbs__link" href="' . esc_url( $url ) . '">' . ( 0 === $i ? gadgetim_icon( 'home' ) : '' ) . gadgetim_bidi( $label ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
			echo gadgetim_icon( 'chev-l', 'crumbs__sep ic--dir' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
		}
		echo '</li>';
	}
	echo '</ol></nav>';
}

/**
 * Numbered pagination: real URLs, locale digits, rel prev/next, gradient current page.
 *
 * @param array $args total, current, query (WP_Query), base (sprintf pattern with %#% like paginate_links), class.
 */
function gadgetim_pagination( array $args = [] ): void {
	global $wp_query;
	$query   = $args['query'] ?? $wp_query;
	$total   = (int) ( $args['total'] ?? ( $query instanceof WP_Query ? $query->max_num_pages : 0 ) );
	$current = (int) ( $args['current'] ?? max( 1, (int) get_query_var( 'paged' ) ) );
	if ( $total <= 1 ) {
		return;
	}
	$current = min( max( 1, $current ), $total );
	$base    = (string) ( $args['base'] ?? '' );
	$link    = static fn( int $n ): string => '' !== $base ? str_replace( '%#%', (string) $n, $base ) : (string) get_pagenum_link( $n );
	$pages   = [];
	for ( $i = 1; $i <= $total; $i++ ) {
		if ( 1 === $i || $total === $i || abs( $i - $current ) <= 1 ) {
			$pages[] = $i;
		}
	}
	echo '<nav class="' . esc_attr( trim( 'pager ' . (string) ( $args['class'] ?? '' ) ) ) . '" aria-label="' . esc_attr__( 'Pagination', 'gadgetim' ) . '"><ul class="pager__list">';
	if ( $current > 1 ) {
		echo '<li><a class="pager__arrow" rel="prev" href="' . esc_url( $link( $current - 1 ) ) . '" aria-label="' . esc_attr__( 'Previous page', 'gadgetim' ) . '">' . gadgetim_icon( 'chev-r', 'ic--dir' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	}
	$prev = 0;
	foreach ( $pages as $page ) {
		if ( $page - $prev > 1 ) {
			echo '<li class="pager__gap" aria-hidden="true">…</li>';
		}
		$prev = $page;
		if ( $page === $current ) {
			echo '<li><span class="pager__num is-current" aria-current="page">' . esc_html( gadgetim_number( $page ) ) . '</span></li>';
		} else {
			$rel = 1 === $page - $current ? ' rel="next"' : ( -1 === $page - $current ? ' rel="prev"' : '' );
			echo '<li><a class="pager__num"' . $rel . ' href="' . esc_url( $link( $page ) ) . '">' . esc_html( gadgetim_number( $page ) ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rel is fixed.
		}
	}
	if ( $current < $total ) {
		echo '<li><a class="pager__arrow" rel="next" href="' . esc_url( $link( $current + 1 ) ) . '" aria-label="' . esc_attr__( 'Next page', 'gadgetim' ) . '">' . gadgetim_icon( 'chev-l', 'ic--dir' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	}
	echo '</ul></nav>';
}

/**
 * Empty state: gradient icon tile (or an illustration), title, text, actions.
 *
 * @param array $args icon (default search-x), art (raw SVG, overrides icon), title, text, actions (array of gadgetim_button() arg arrays with 'label'), class, tag (h1|h2, default h2).
 */
function gadgetim_empty_state( array $args ): string {
	$tag  = in_array( $args['tag'] ?? 'h2', [ 'h1', 'h2', 'h3' ], true ) ? (string) ( $args['tag'] ?? 'h2' ) : 'h2';
	$html = '<div class="' . esc_attr( trim( 'empty ' . (string) ( $args['class'] ?? '' ) ) ) . '">';
	$html .= ! empty( $args['art'] )
		? '<span class="empty__art" aria-hidden="true">' . $args['art'] . '</span>'
		: '<span class="empty__ic" aria-hidden="true">' . gadgetim_icon( (string) ( $args['icon'] ?? 'search-x' ) ) . '</span>';
	$html .= '<' . $tag . ' class="empty__t">' . esc_html( (string) ( $args['title'] ?? '' ) ) . '</' . $tag . '>';
	if ( ! empty( $args['text'] ) ) {
		$html .= '<p class="empty__p">' . esc_html( (string) $args['text'] ) . '</p>';
	}
	if ( ! empty( $args['actions'] ) && is_array( $args['actions'] ) ) {
		$html .= '<div class="empty__actions">';
		foreach ( $args['actions'] as $action ) {
			$html .= gadgetim_button( (string) ( $action['label'] ?? '' ), (array) $action );
		}
		$html .= '</div>';
	}
	return $html . '</div>';
}

/**
 * Countdown markup for countdown.js. Only for a REAL deadline (sale end date or the admin-set
 * campaign end): returns '' when the timestamp is 0 or already past — never a fake timer.
 *
 * @param int   $deadline Unix timestamp.
 * @param array $args     style (flip|chip, default flip), label (accessible prefix), class, ended (text when it ends; default hides).
 */
function gadgetim_countdown( int $deadline, array $args = [] ): string {
	$left = $deadline - time();
	if ( $deadline <= 0 || $left <= 0 ) {
		return '';
	}
	$style = 'chip' === ( $args['style'] ?? 'flip' ) ? 'chip' : 'flip';
	$days  = intdiv( $left, DAY_IN_SECONDS );
	$h     = intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS );
	$m     = intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
	$s     = $left % MINUTE_IN_SECONDS;
	$units = [];
	if ( $days > 0 ) {
		$units['d'] = [ $days, __( 'days', 'gadgetim' ) ];
	}
	$units['h'] = [ $h, __( 'hours', 'gadgetim' ) ];
	$units['m'] = [ $m, __( 'minutes', 'gadgetim' ) ];
	$units['s'] = [ $s, __( 'seconds', 'gadgetim' ) ];
	/* translators: 1: hours, 2: minutes */
	$label = sprintf( __( '%1$s hours and %2$s minutes left', 'gadgetim' ), gadgetim_number( $days * 24 + $h ), gadgetim_number( $m ) );
	if ( ! empty( $args['label'] ) ) {
		$label = (string) $args['label'] . ': ' . $label;
	}
	$html = '<div class="' . esc_attr( trim( 'cd cd--' . $style . ' ' . (string) ( $args['class'] ?? '' ) ) ) . '" role="timer" dir="ltr"'
		. ' data-countdown="' . esc_attr( gmdate( 'c', $deadline ) ) . '"'
		. ( ! empty( $args['label'] ) ? ' data-countdown-label="' . esc_attr( (string) $args['label'] ) . '"' : '' )
		. ( ! empty( $args['ended'] ) ? ' data-countdown-ended="' . esc_attr( (string) $args['ended'] ) . '"' : '' )
		. ' aria-label="' . esc_attr( $label ) . '">';
	$i = 0;
	foreach ( $units as $key => [ $value, $unit_label ] ) {
		if ( $i++ > 0 ) {
			$html .= '<span class="cd__sep" aria-hidden="true">:</span>';
		}
		$html .= '<span class="cd__u" aria-hidden="true"><span class="cd__d" data-u="' . esc_attr( $key ) . '">' . esc_html( gadgetim_digits( str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ) ) . '</span>'
			. ( 'flip' === $style ? '<span class="cd__l">' . esc_html( $unit_label ) . '</span>' : '' ) . '</span>';
	}
	return $html . '</div>';
}

/* ---------------------------------------------------------------------------
 * Brand
 * ------------------------------------------------------------------------- */

/**
 * The brand's per-language display name.
 */
function gadgetim_brand_name(): string {
	return gadgetim_t( 'brand.name', get_bloginfo( 'name' ) );
}

/**
 * The Aurora monogram (gradient squircle, the two strokes of گ, the amber spark) as inline SVG.
 *
 * @param string $uid   Unique id prefix for the gradients (several logos per page).
 * @param string $class Classes.
 */
function gadgetim_monogram( string $uid = 'lm', string $class = 'logo__mark' ): string {
	$uid = sanitize_html_class( $uid );
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 44 44" aria-hidden="true" focusable="false">'
		. '<defs><linearGradient id="' . $uid . '-g" x1="2" y1="2" x2="42" y2="42" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#4f46e5"/><stop offset=".55" stop-color="#9333ea"/><stop offset="1" stop-color="#ec4899"/></linearGradient>'
		. '<radialGradient id="' . $uid . '-h" cx="34" cy="6" r="26" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fbbf24" stop-opacity=".85"/><stop offset="1" stop-color="#fbbf24" stop-opacity="0"/></radialGradient></defs>'
		. '<rect x="1" y="1" width="42" height="42" rx="13" fill="url(#' . $uid . '-g)"/>'
		. '<rect x="1" y="1" width="42" height="42" rx="13" fill="url(#' . $uid . '-h)"/>'
		. '<path d="M3 15C10 6 24 2.5 41 5V4a3 3 0 0 0-3-3H6a5 5 0 0 0-5 5v9Z" fill="#fff" opacity=".16"/>'
		. '<g fill="none" stroke="#fff" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"><path class="logo__k1" d="M14.5 13.8 27.5 9.8"/><path class="logo__k2" d="M14.5 19.3 27.5 15.3"/><path d="M30.5 15.8V27a5.5 5.5 0 0 1-5.5 5.5H13"/></g>'
		. '<circle class="logo__spark" cx="14" cy="26.2" r="2.4" fill="#fcd34d"/></svg>';
}

/**
 * The logo link: the uploaded logo (brand.logo_id or Customizer custom logo) when set, else the
 * Aurora lockup (monogram + gradient wordmark «گجتیم» + spaced GADGETIM).
 *
 * @param array $args uid (gradient id prefix), class (extra classes, e.g. logo--ftr).
 */
function gadgetim_logo( array $args = [] ): string {
	$name  = gadgetim_brand_name();
	$class = trim( 'logo ' . (string) ( $args['class'] ?? '' ) );
	/* translators: %s: shop name */
	$label = sprintf( __( '%s, home page', 'gadgetim' ), $name );
	$html  = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( home_url( '/' ) ) . '" rel="home" aria-label="' . esc_attr( $label ) . '">';

	$logo_id = (int) gadgetim_setting( 'brand.logo_id', 0 );
	if ( $logo_id <= 0 && has_custom_logo() ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
	}
	if ( $logo_id > 0 && wp_attachment_is_image( $logo_id ) ) {
		$html .= wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			[
				'class'    => 'logo__img',
				'alt'      => '',
				'loading'  => 'eager',
				'decoding' => 'async',
			]
		);
	} else {
		// The spaced Latin line only under a non-Latin wordmark (never "Gadgetim / GADGETIM" twice, e.g. on /ar/
		// while brand.name has no Arabic value).
		$latin = preg_match( '/\p{Arabic}/u', $name ) ?'<span class="logo__latin">GADGETIM</span>' : '';
		$html .= gadgetim_monogram( (string) ( $args['uid'] ?? 'lm' ) )
			. '<span class="logo__txt"><span class="logo__word">' . esc_html( $name ) . '</span>' . $latin . '</span>';
	}
	return $html . '</a>';
}

/* ---------------------------------------------------------------------------
 * Contact helpers
 * ------------------------------------------------------------------------- */

/**
 * A contact setting (phone, whatsapp, telegram, instagram, email) as a string.
 */
function gadgetim_contact( string $key ): string {
	$value = gadgetim_setting( 'contact.' . $key );
	if ( is_array( $value ) ) {
		return gadgetim_t( 'contact.' . $key );
	}
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * tel: href for a stored phone number.
 */
function gadgetim_tel_href( string $raw = '' ): string {
	$raw = '' !== $raw ? $raw : gadgetim_contact( 'phone' );
	if ( '' === $raw ) {
		return '';
	}
	if ( class_exists( 'Gadgetim\Core\Support\Phone' ) ) {
		return (string) Gadgetim\Core\Support\Phone::tel_href( $raw );
	}
	$digits = preg_replace( '/[^\d+]/', '', gadgetim_ascii_digits( $raw ) ) ?? '';
	return '' !== $digits ? 'tel:' . $digits : '';
}

/**
 * Phone number for display (national format, language digits), LTR-isolated.
 */
function gadgetim_phone_display( string $raw = '' ): string {
	$raw = '' !== $raw ? $raw : gadgetim_contact( 'phone' );
	if ( '' === $raw ) {
		return '';
	}
	if ( class_exists( 'Gadgetim\Core\Support\Phone' ) ) {
		$raw = (string) Gadgetim\Core\Support\Phone::national( $raw, gadgetim_lang() );
	} else {
		$raw = gadgetim_digits( $raw );
	}
	return '<bdi dir="ltr" class="phone">' . esc_html( $raw ) . '</bdi>';
}

/**
 * wa.me URL for the shop's WhatsApp number with optional prefilled text ('' when unset).
 */
function gadgetim_whatsapp_url( string $text = '' ): string {
	$number = gadgetim_contact( 'whatsapp' );
	if ( '' === $number ) {
		$number = gadgetim_contact( 'phone' );
	}
	if ( '' === $number ) {
		return '';
	}
	if ( class_exists( 'Gadgetim\Core\Support\Phone' ) ) {
		return (string) Gadgetim\Core\Support\Phone::whatsapp_url( $number, $text );
	}
	$digits = preg_replace( '/\D/', '', gadgetim_ascii_digits( $number ) ) ?? '';
	if ( str_starts_with( $digits, '0' ) ) {
		$digits = '98' . substr( $digits, 1 );
	}
	if ( '' === $digits ) {
		return '';
	}
	return 'https://wa.me/' . $digits . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );
}

/**
 * t.me URL for the shop's Telegram handle ('' when unset).
 */
function gadgetim_telegram_url(): string {
	$handle = gadgetim_contact( 'telegram' );
	if ( '' === $handle ) {
		return '';
	}
	if ( class_exists( 'Gadgetim\Core\Support\Phone' ) ) {
		return (string) Gadgetim\Core\Support\Phone::telegram_url( $handle );
	}
	$handle = ltrim( preg_replace( '#^https?://t\.me/#', '', $handle ) ?? '', '@' );
	$handle = preg_replace( '/[^A-Za-z0-9_]/', '', $handle ) ?? '';
	return '' !== $handle ? 'https://t.me/' . $handle : '';
}

/**
 * Instagram profile URL ('' when unset).
 */
function gadgetim_instagram_url(): string {
	$handle = gadgetim_contact( 'instagram' );
	$handle = ltrim( preg_replace( '#^https?://(www\.)?instagram\.com/#', '', $handle ) ?? '', '@' );
	$handle = preg_replace( '/[^A-Za-z0-9_.]/', '', rtrim( $handle, '/' ) ) ?? '';
	return '' !== $handle ? 'https://www.instagram.com/' . $handle . '/' : '';
}

/**
 * The guarantee page URL from settings ('' when none).
 */
function gadgetim_guarantee_url(): string {
	$id = (int) gadgetim_setting( 'trust.guarantee_page', 0 );
	if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
		return (string) get_permalink( $id );
	}
	return '';
}

/**
 * Prints WooCommerce notices (shop pages).
 */
function gadgetim_the_notices(): void {
	if ( function_exists( 'woocommerce_output_all_notices' ) ) {
		woocommerce_output_all_notices();
	}
}

/**
 * Label of the account entry (header, drawer, checkout): "My account" when signed in, otherwise
 * "Log in | Sign up" only when WooCommerce offers registration on the account page, else "Log in".
 */
function gadgetim_account_label( bool $long = false ): string {
	if ( is_user_logged_in() ) {
		return __( 'My account', 'gadgetim' );
	}
	if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) {
		return $long ? __( 'Log in or sign up', 'gadgetim' ) : __( 'Log in | Sign up', 'gadgetim' );
	}
	return __( 'Log in', 'gadgetim' );
}

/**
 * Wraps a user query (with the quotes around it) in Unicode isolates (FSI … PDI) inside a plain-text
 * label, so a Latin query in Persian text (or the reverse) keeps its quotes in place. Text only: the
 * result is still escaped normally.
 */
function gadgetim_isolate_query( string $text, string $query ): string {
	$query = trim( $query );
	if ( '' === $query || false === strpos( $text, $query ) ) {
		return $text;
	}
	$pattern = '/([“«"‹„]?)' . preg_quote( $query, '/' ) . '([”»"›“]?)/u';
	return (string) preg_replace_callback(
		$pattern,
		static fn( array $m ): string => "\u{2068}" . $m[0] . "\u{2069}",
		$text,
		1
	);
}
