<?php
/**
 * Images: srcset/sizes contexts, LQIP backgrounds, the eager budget for the first row, the LCP
 * preload and the decorative-image watermark mask.
 *
 * Watermark: almost every catalogue packshot carries the old «گجتیم GADGETIM.IR» mark in the
 * bottom-right corner and a faint "GT" ghost in the centre. Decorative large images (hero, banners,
 * category tiles, stories, mega-menu art) must use gadgetim_decor_image(): class "wm" applies a
 * stepped mask that hides only the corner glyphs (outline measured from a per-pixel median of the
 * catalogue) and the #wm-lift SVG filter that pushes the near-white ghost to pure white. Add
 * 'fade' => true for products standing on the bottom edge (class "wm--fade" dissolves the base
 * instead of notching the corner). Product cards and the product gallery show photos unmasked.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * The `sizes` attribute for a rendering context (filter gadgetim_image_sizes_attr).
 */
function gadgetim_image_sizes_attr( string $context = 'card' ): string {
	$sizes = [
		'card'   => '(min-width:1100px) 240px, (min-width:768px) 31vw, 46vw',
		'rail'   => '(min-width:1100px) 230px, (min-width:768px) 214px, 166px',
		'hero'   => '(min-width:1100px) 350px, (min-width:768px) 270px, 192px',
		'banner' => '(min-width:1100px) 260px, (min-width:768px) 200px, 140px',
		'tile'   => '(min-width:1100px) 200px, 104px',
		'pdp'    => '(min-width:1100px) 620px, 100vw',
		'story'  => '86px',
		'rank'   => '74px',
		'thumb'  => '64px',
		'mini'   => '52px',
		'micro'  => '32px',
	];
	return (string) apply_filters( 'gadgetim_image_sizes_attr', $sizes[ $context ] ?? $sizes['card'], $context );
}

/**
 * LQIP data URI stored by Catalog\Lqip in attachment meta _gtm_lqip ('' when absent or malformed).
 */
function gadgetim_lqip( int $attachment_id ): string {
	if ( $attachment_id <= 0 ) {
		return '';
	}
	$lqip = (string) get_post_meta( $attachment_id, '_gtm_lqip', true );
	if ( '' === $lqip || strlen( $lqip ) > 4096 || ! preg_match( '#^data:image/(png|webp|gif|jpeg);base64,[A-Za-z0-9+/=]+$#', $lqip ) ) {
		return '';
	}
	return $lqip;
}

/**
 * Eager-loading budget for the product images that follow: the first $count images render with
 * loading="eager"; the very first also gets fetchpriority="high" when $high is true. Call it right
 * before printing the first row of an above-the-fold grid or rail.
 */
function gadgetim_set_eager_budget( int $count, bool $high = false ): void {
	$GLOBALS['gadgetim_eager'] = [
		'left' => max( 0, $count ),
		'high' => $high,
	];
}

/**
 * Consumes one slot of the eager budget: [ loading, fetchpriority|null ].
 *
 * @return array{0:string,1:?string}
 */
function gadgetim_next_loading(): array {
	$state = $GLOBALS['gadgetim_eager'] ?? [
		'left' => 0,
		'high' => false,
	];
	if ( $state['left'] <= 0 ) {
		return [ 'lazy', null ];
	}
	$priority                  = $state['high'] ? 'high' : null;
	$GLOBALS['gadgetim_eager'] = [
		'left' => $state['left'] - 1,
		'high' => false,
	];
	return [ 'eager', $priority ];
}

/**
 * Placeholder plate image (no product image).
 */
function gadgetim_placeholder_src(): string {
	return gadgetim_asset_url( 'assets/img/placeholder-product.svg' );
}
add_filter( 'woocommerce_placeholder_img_src', 'gadgetim_placeholder_src' );

/**
 * An <img> for a product (its main image) or an attachment id, with srcset, sizes, LQIP
 * background, width/height and loading rules.
 *
 * @param WC_Product|int $subject Product or attachment ID.
 * @param string         $size    Registered image size (gtm-thumb, gtm-card, gtm-hero, full).
 * @param array          $args    class, alt, sizes (context key or raw), loading, fetchpriority, attrs (extra attributes), lqip (bool, default true).
 */
function gadgetim_product_image( $subject, string $size = 'gtm-card', array $args = [] ): string {
	$attachment_id = 0;
	$alt           = (string) ( $args['alt'] ?? '' );

	if ( class_exists( 'WC_Product', false ) && $subject instanceof WC_Product ) {
		$attachment_id = (int) $subject->get_image_id();
		if ( ! array_key_exists( 'alt', $args ) ) {
			$alt   = gadgetim_short_title( $subject );
			$brand = gadgetim_product_brand( $subject );
			if ( $brand ) {
				$alt .= ' — ' . gadgetim_term_name( $brand );
			}
		}
	} elseif ( is_numeric( $subject ) ) {
		$attachment_id = (int) $subject;
	}

	$sizes = (string) ( $args['sizes'] ?? 'card' );
	if ( ! str_contains( $sizes, 'px' ) && ! str_contains( $sizes, 'vw' ) ) {
		$sizes = gadgetim_image_sizes_attr( $sizes );
	}

	$loading  = $args['loading'] ?? null;
	$priority = $args['fetchpriority'] ?? null;
	if ( null === $loading ) {
		[ $loading, $auto_priority ] = gadgetim_next_loading();
		$priority                     = $priority ?? $auto_priority;
	}

	$attrs = [
		'class'         => trim( (string) ( $args['class'] ?? '' ) ),
		'alt'           => $alt,
		'sizes'         => $sizes,
		'loading'       => $loading,
		'decoding'      => 'async',
		'fetchpriority' => $priority,
		'draggable'     => 'false',
	];
	foreach ( (array) ( $args['attrs'] ?? [] ) as $name => $value ) {
		$attrs[ $name ] = $value;
	}

	if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
		// The blurred preview only for images in the first screen: lazy images are fetched well
		// before they scroll into view, so their base64 previews were ~1 kB of HTML each that no
		// one saw (46 on the home page, 11% of its compressed size, 2026-11-13).
		if ( false !== ( $args['lqip'] ?? true ) && 'lazy' !== $loading ) {
			$lqip = gadgetim_lqip( $attachment_id );
			if ( '' !== $lqip ) {
				$attrs['style'] = 'background:url(' . $lqip . ') center/contain no-repeat';
			}
		} else {
			$attrs['style'] = 'background:none';
		}
		$attrs = array_filter(
			$attrs,
			static fn( $v, $k ): bool => 'alt' === $k || ( null !== $v && false !== $v && '' !== $v ),
			ARRAY_FILTER_USE_BOTH
		);
		$html  = wp_get_attachment_image( $attachment_id, $size, false, $attrs );
		if ( '' !== $html ) {
			return $html;
		}
	}

	$dims             = [
		'gtm-thumb' => 160,
		'gtm-card'  => 400,
		'gtm-hero'  => 800,
	];
	$dim              = $dims[ $size ] ?? 400;
	$attrs['src']     = gadgetim_placeholder_src();
	$attrs['width']   = (string) $dim;
	$attrs['height']  = (string) $dim;
	$attrs['class']   = trim( $attrs['class'] . ' is-placeholder' );
	unset( $attrs['sizes'], $attrs['fetchpriority'], $attrs['style'] );
	return '<img' . gadgetim_attr( $attrs ) . '>';
}

/**
 * Attachment id of a product's n-th image: 0 = main image, 1 = first gallery image, … (0 when absent).
 */
function gadgetim_product_image_id( WC_Product $product, int $index = 0 ): int {
	if ( $index <= 0 ) {
		return (int) $product->get_image_id();
	}
	$gallery = array_values( array_map( 'intval', (array) $product->get_gallery_image_ids() ) );
	return (int) ( $gallery[ $index - 1 ] ?? 0 );
}

/**
 * Decorative product art (hero, banners, tiles, stories, mega menu) with the watermark mask.
 *
 * @param WC_Product|int $subject Product (main image, or 'index' => n for the n-th image) or attachment ID.
 * @param string         $size    Image size.
 * @param array          $args    index, fade (bool), crop ("auto": crop photos instead of notching them,
 *                                see gadgetim_wm_mode(); true: always crop), class, alt (default ''),
 *                                sizes, loading, fetchpriority, attrs.
 */
function gadgetim_decor_image( $subject, string $size = 'gtm-card', array $args = [] ): string {
	$id = 0;
	if ( class_exists( 'WC_Product', false ) && $subject instanceof WC_Product ) {
		$id = gadgetim_product_image_id( $subject, (int) ( $args['index'] ?? 0 ) );
		if ( $id <= 0 ) {
			$id = (int) $subject->get_image_id();
		}
	} elseif ( is_numeric( $subject ) ) {
		$id = (int) $subject;
	}
	$crop          = $args['crop'] ?? false;
	$crop          = true === $crop || ( 'auto' === $crop && 'crop' === gadgetim_wm_mode( $id ) );
	$args['class'] = $crop
		? trim( 'wm-crop ' . (string) ( $args['class'] ?? '' ) )
		: gadgetim_wm_class( ! empty( $args['fade'] ), (string) ( $args['class'] ?? '' ) );
	$args['alt']   = (string) ( $args['alt'] ?? '' );
	$args['lqip']  = false; // A blurred LQIP square would show around the masked shape.
	unset( $args['index'], $args['fade'], $args['crop'] );
	return gadgetim_product_image( $id, $size, $args );
}

/**
 * Class list for the watermark mask: "wm" (+ "wm--fade").
 */
function gadgetim_wm_class( bool $fade = false, string $extra = '' ): string {
	return trim( 'wm' . ( $fade ? ' wm--fade' : '' ) . ' ' . $extra );
}

/**
 * How a decorative image hides the old corner mark: "mask" (white packshot: the stepped notch is
 * invisible on white) or "crop" (photo or coloured background: the notch would show as a white bite,
 * so the image is scaled from its top-left corner and the bottom-right strip clipped away).
 *
 * Decided once per attachment by sampling the pixels just outside the mark with GD, then kept in
 * the _gtm_wm_mode meta. Filter gadgetim_wm_mode to force a mode.
 */
function gadgetim_wm_mode( int $attachment_id ): string {
	if ( $attachment_id <= 0 ) {
		return 'mask';
	}
	$mode = (string) get_post_meta( $attachment_id, '_gtm_wm_mode', true );
	if ( 'mask' !== $mode && 'crop' !== $mode ) {
		$mode = gadgetim_wm_probe( $attachment_id );
		if ( '' !== $mode ) {
			update_post_meta( $attachment_id, '_gtm_wm_mode', $mode );
		} else {
			$mode = 'mask';
		}
	}
	/**
	 * Filters the watermark handling of a decorative image.
	 *
	 * @param string $mode          "mask" or "crop".
	 * @param int    $attachment_id Attachment.
	 */
	$mode = (string) apply_filters( 'gadgetim_wm_mode', $mode, $attachment_id );
	return 'crop' === $mode ? 'crop' : 'mask';
}

/**
 * The first of a product's images (by gallery index, 0 = main) that sits on white, so decorative art
 * can keep the transparent-looking mask; falls back to the first existing one.
 *
 * @param int[] $order Indexes to try in order.
 */
function gadgetim_decor_pick( WC_Product $product, array $order = [ 0, 1, 2 ] ): int {
	$first = 0;
	foreach ( $order as $index ) {
		$id = gadgetim_product_image_id( $product, (int) $index );
		if ( $id <= 0 ) {
			continue;
		}
		if ( 'mask' === gadgetim_wm_mode( $id ) ) {
			return $id;
		}
		$first = $first ?: $id;
	}
	return $first ?: (int) $product->get_image_id();
}

/**
 * Samples the background around the old mark; "" when the file cannot be read.
 */
function gadgetim_wm_probe( int $attachment_id ): string {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return '';
	}
	$file = '';
	foreach ( [ 'gtm-thumb', 'thumbnail', 'full' ] as $size ) {
		$src = image_get_intermediate_size( $attachment_id, $size );
		if ( is_array( $src ) && ! empty( $src['path'] ) ) {
			$path = trailingslashit( (string) wp_get_upload_dir()['basedir'] ) . $src['path'];
			if ( is_readable( $path ) ) {
				$file = $path;
				break;
			}
		}
	}
	if ( '' === $file ) {
		$file = (string) get_attached_file( $attachment_id );
	}
	if ( '' === $file || ! is_readable( $file ) || filesize( $file ) > 4 * MB_IN_BYTES ) {
		return '';
	}
	$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
	$img  = false !== $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unsupported format just means "unknown".
	if ( ! $img ) {
		return '';
	}
	$w = imagesx( $img );
	$h = imagesy( $img );
	// Points (fractions of the frame) around the mark: left of it, above it, and the far corners.
	$points = [ [ .50, .93 ], [ .53, .97 ], [ .97, .74 ], [ .93, .78 ], [ .03, .97 ], [ .97, .03 ], [ .75, .80 ], [ .40, .96 ] ];
	$dark   = 0;
	foreach ( $points as $p ) {
		$rgb = imagecolorat( $img, (int) min( $w - 1, $p[0] * $w ), (int) min( $h - 1, $p[1] * $h ) );
		$c   = imagecolorsforindex( $img, $rgb );
		if ( min( $c['red'], $c['green'], $c['blue'] ) < 228 ) {
			++$dark;
		}
	}
	imagedestroy( $img );
	return $dark >= 2 ? 'crop' : 'mask';
}

/**
 * Attachment image filter: async decoding everywhere, LQIP background for attachments that carry one.
 *
 * @param array   $attr       Attributes.
 * @param WP_Post $attachment Attachment.
 * @return array
 */
function gadgetim_attachment_attributes( array $attr, WP_Post $attachment ): array {
	if ( is_admin() ) {
		return $attr;
	}
	if ( empty( $attr['decoding'] ) ) {
		$attr['decoding'] = 'async';
	}
	if ( ! isset( $attr['style'] ) ) {
		$lqip = 'lazy' === ( $attr['loading'] ?? '' ) ? '' : gadgetim_lqip( (int) $attachment->ID );
		if ( '' !== $lqip ) {
			$attr['style'] = 'background:url(' . $lqip . ') center/contain no-repeat';
		}
	} elseif ( 'background:none' === $attr['style'] ) {
		unset( $attr['style'] );
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'gadgetim_attachment_attributes', 10, 2 );

/**
 * The page's LCP image: [ attachment id, size, sizes context ], or null.
 * Default: the product page's main image. Page layers set their own through the filter
 * gadgetim_lcp_image (e.g. the home hero's first packshot).
 *
 * @return array{0:int,1:string,2:string}|null
 */
function gadgetim_lcp_image(): ?array {
	$lcp = null;
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product && $product->get_image_id() ) {
			$lcp = [ (int) $product->get_image_id(), 'gtm-hero', 'pdp' ];
		}
	}
	/**
	 * Filters the LCP image preloaded in <head>: [ attachment id, size, sizes context ] or null.
	 *
	 * @param array|null $lcp LCP image.
	 */
	$lcp = apply_filters( 'gadgetim_lcp_image', $lcp );
	if ( ! is_array( $lcp ) || empty( $lcp[0] ) ) {
		return null;
	}
	return [ (int) $lcp[0], (string) ( $lcp[1] ?? 'gtm-card' ), (string) ( $lcp[2] ?? 'card' ) ];
}

/**
 * wp_head 4: preloads the LCP image with its srcset.
 */
function gadgetim_preload_lcp_image(): void {
	$lcp = gadgetim_lcp_image();
	if ( ! $lcp ) {
		return;
	}
	[ $id, $size, $context ] = $lcp;
	$src                     = wp_get_attachment_image_src( $id, $size );
	if ( ! $src ) {
		return;
	}
	$srcset = (string) wp_get_attachment_image_srcset( $id, $size );
	echo '<link rel="preload" as="image" href="' . esc_url( $src[0] ) . '"'
		. ( '' !== $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="' . esc_attr( gadgetim_image_sizes_attr( $context ) ) . '"' : '' )
		. ' fetchpriority="high">' . "\n";
}
add_action( 'wp_head', 'gadgetim_preload_lcp_image', 4 );

/**
 * Term thumbnail (WooCommerce's thumbnail_id term meta) as an <img>, or ''.
 */
function gadgetim_term_image( WP_Term $term, string $size = 'gtm-thumb', array $args = [] ): string {
	$id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $id <= 0 ) {
		return '';
	}
	$args['alt'] = $args['alt'] ?? gadgetim_term_name( $term );
	return gadgetim_product_image( $id, $size, $args );
}
