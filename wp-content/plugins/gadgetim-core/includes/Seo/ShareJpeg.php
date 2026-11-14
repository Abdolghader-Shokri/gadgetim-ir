<?php
/**
 * JPEG copies of WebP images for link previews.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

/**
 * Product photos are WebP, and so was every product's og:image. Link previews do not read
 * WebP everywhere: guides and support threads disagree about WhatsApp and LinkedIn, and
 * WhatsApp (the shop's ordering channel) is where a missing preview costs the most. JPEG is
 * read by every preview bot, so the share image of a WebP attachment is a JPEG copy, at most
 * 1200 pixels wide, made on the first page view that needs it (uploads/gadgetim-share/) and
 * reused after that. The page itself keeps showing the light WebP.
 *
 * Transparent pixels are flattened onto white (JPEG has no alpha). Without GD's WebP support
 * the WebP stays the share image.
 */
final class ShareJpeg {

	/** Attachment meta: { file, width, height, source } of the JPEG copy. */
	public const META = '_gtm_share_jpeg';

	/** Folder under uploads. */
	public const DIR = 'gadgetim-share';

	/** Widest copy (Open Graph's recommended width). */
	private const MAX_WIDTH = 1200;

	private const QUALITY = 85;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'delete_attachment', [ self::class, 'forget' ] );
	}

	/**
	 * The share image of an attachment: $src unchanged unless it is a WebP, then the JPEG copy.
	 *
	 * @param int   $id  Attachment ID.
	 * @param array $src wp_get_attachment_image_src() of the full size: [ url, width, height, … ].
	 * @return array [ url, width, height ]
	 */
	public static function for_attachment( int $id, array $src ): array {
		if ( 'image/webp' !== get_post_mime_type( $id ) ) {
			return $src;
		}
		$file = (string) get_attached_file( $id );
		if ( '' === $file || ! is_readable( $file ) ) {
			return $src;
		}
		$source  = md5( $file . '|' . (string) filemtime( $file ) );
		$uploads = wp_get_upload_dir();
		$meta    = get_post_meta( $id, self::META, true );
		if ( is_array( $meta ) && ( $meta['source'] ?? '' ) === $source && ! empty( $meta['file'] )
			&& is_file( trailingslashit( $uploads['basedir'] ) . $meta['file'] ) ) {
			return [ trailingslashit( $uploads['baseurl'] ) . $meta['file'], (int) $meta['width'], (int) $meta['height'] ];
		}
		$made = self::make( $id, $file, $source, $uploads );
		if ( null === $made ) {
			return $src;
		}
		update_post_meta( $id, self::META, $made );
		return [ trailingslashit( $uploads['baseurl'] ) . $made['file'], $made['width'], $made['height'] ];
	}

	/**
	 * Writes the JPEG copy; null when GD cannot read the WebP or the file cannot be written.
	 *
	 * @param array $uploads wp_get_upload_dir().
	 * @return array{file:string,width:int,height:int,source:string}|null
	 */
	private static function make( int $id, string $file, string $source, array $uploads ): ?array {
		if ( ! function_exists( 'imagecreatefromwebp' ) || ! empty( $uploads['error'] ) ) {
			return null;
		}
		$image = @imagecreatefromwebp( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unreadable file just keeps the WebP.
		if ( false === $image ) {
			return null;
		}
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$out_w  = min( $width, self::MAX_WIDTH );
		$out_h  = max( 1, (int) round( $height * $out_w / max( 1, $width ) ) );
		$canvas = imagecreatetruecolor( $out_w, $out_h );
		imagefill( $canvas, 0, 0, (int) imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagecopyresampled( $canvas, $image, 0, 0, 0, 0, $out_w, $out_h, $width, $height );
		imagedestroy( $image );

		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			imagedestroy( $canvas );
			return null;
		}
		$name = $id . '-' . substr( $source, 0, 8 ) . '.jpg';
		$ok   = imagejpeg( $canvas, $dir . '/' . $name, self::QUALITY );
		imagedestroy( $canvas );
		if ( ! $ok ) {
			return null;
		}
		self::remove_old( $id, $name, $dir );
		return [
			'file'   => self::DIR . '/' . $name,
			'width'  => $out_w,
			'height' => $out_h,
			'source' => $source,
		];
	}

	/**
	 * Removes earlier copies of the same attachment (made before the image file changed).
	 */
	private static function remove_old( int $id, string $keep, string $dir ): void {
		foreach ( glob( $dir . '/' . $id . '-*.jpg' ) ?: [] as $old ) {
			if ( basename( $old ) !== $keep ) {
				wp_delete_file( $old );
			}
		}
	}

	/**
	 * delete_attachment: the copy goes with its attachment.
	 */
	public static function forget( $post_id ): void {
		$meta = get_post_meta( (int) $post_id, self::META, true );
		if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
			wp_delete_file( trailingslashit( wp_get_upload_dir()['basedir'] ) . $meta['file'] );
		}
	}
}
