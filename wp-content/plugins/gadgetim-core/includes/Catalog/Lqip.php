<?php
/**
 * LQIP placeholders generated with GD on upload (architecture §5.5, §6) plus the packshot
 * edge-sampling flag (decisions: kept for later use).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\Settings\Admin\Tools;
use Gadgetim\Core\Settings\Capabilities;
use GdImage;
use WP_Error;

/**
 * Attachment meta `_gtm_lqip` = 16-px data URI (JPEG; PNG when the source may be transparent),
 * `_gtm_packshot` = 1 when ≥ 97 % of a 4-px edge strip is near-white.
 */
final class Lqip {

	public const META = '_gtm_lqip';

	public const META_PACKSHOT = '_gtm_packshot';

	public const WIDTH = 16;

	public const JPEG_QUALITY = 40;

	public const PACKSHOT_THRESHOLD = 0.97;

	public const PACKSHOT_WHITE = 245;

	public const TOOL_ID = 'regenerate-lqip';

	public const AJAX_ACTION = 'gadgetim_lqip_batch';

	public const BATCH_SIZE = 20;

	private const JOB_PREFIX = 'gadgetim_lqip_job_';

	/**
	 * Hooks: generation on metadata creation, the Tools entry and its batch handler.
	 */
	public static function register(): void {
		add_filter( 'wp_generate_attachment_metadata', [ self::class, 'on_metadata' ], 10, 3 );
		add_action( 'admin_init', [ self::class, 'register_tool' ], 20 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'ajax' ] );
	}

	/**
	 * wp_generate_attachment_metadata: generate for images (create and regenerate).
	 *
	 * @param array  $metadata      Attachment metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       'create' or 'update'.
	 */
	public static function on_metadata( $metadata, $attachment_id, $context = 'create' ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
			self::update( $attachment_id );
		}
		return $metadata;
	}

	/**
	 * Generates and stores the placeholder (and the packshot flag) for an attachment.
	 *
	 * @return bool Whether a placeholder was stored.
	 */
	public static function update( int $attachment_id ): bool {
		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
			return false;
		}
		$uri = self::generate( $file );
		if ( '' === $uri ) {
			return false;
		}
		update_post_meta( $attachment_id, self::META, $uri );

		$packshot = self::is_packshot_file( $file );
		if ( null !== $packshot ) {
			update_post_meta( $attachment_id, self::META_PACKSHOT, $packshot ? 1 : 0 );
		}

		/**
		 * Fires after an LQIP placeholder was (re)generated.
		 *
		 * @param int    $attachment_id Attachment ID.
		 * @param string $uri           Data URI.
		 */
		do_action( 'gadgetim_lqip_generated', $attachment_id, $uri );
		return true;
	}

	/**
	 * Stored placeholder data URI ('' when none).
	 */
	public static function get( int $attachment_id ): string {
		return (string) get_post_meta( absint( $attachment_id ), self::META, true );
	}

	/**
	 * Whether the attachment was flagged as a white-background packshot.
	 */
	public static function is_packshot( int $attachment_id ): bool {
		return '1' === (string) get_post_meta( absint( $attachment_id ), self::META_PACKSHOT, true );
	}

	/**
	 * Data URI of a 16-px wide rendition of an image file ('' on failure).
	 */
	public static function generate( string $file ): string {
		$mime = (string) ( wp_check_filetype( $file )['type'] ?? '' );
		if ( ! str_starts_with( $mime, 'image/' ) || 'image/svg+xml' === $mime ) {
			return '';
		}
		$uri = self::generate_gd( $file, $mime );
		if ( '' === $uri ) {
			$uri = self::generate_editor( $file );
		}
		return $uri;
	}

	/**
	 * Edge sampling: true when ≥ 97 % of a 4-px strip along all four edges is ≥ #F5F5F5.
	 * Null when the image could not be read.
	 */
	public static function is_packshot_file( string $file ): ?bool {
		$img = self::load_gd( $file );
		if ( ! $img ) {
			return null;
		}
		$w = imagesx( $img );
		$h = imagesy( $img );
		if ( $w < 16 || $h < 16 ) {
			imagedestroy( $img );
			return null;
		}
		$stride = max( 1, (int) floor( max( $w, $h ) / 200 ) );
		$total  = 0;
		$white  = 0;
		$sample = static function ( int $x, int $y ) use ( $img, &$total, &$white ): void {
			$rgb = imagecolorat( $img, $x, $y );
			$r   = ( $rgb >> 16 ) & 0xFF;
			$g   = ( $rgb >> 8 ) & 0xFF;
			$b   = $rgb & 0xFF;
			++$total;
			if ( $r >= self::PACKSHOT_WHITE && $g >= self::PACKSHOT_WHITE && $b >= self::PACKSHOT_WHITE ) {
				++$white;
			}
		};
		for ( $x = 0; $x < $w; $x += $stride ) {
			for ( $d = 0; $d < 4; $d++ ) {
				$sample( $x, $d );
				$sample( $x, $h - 1 - $d );
			}
		}
		for ( $y = 4; $y < $h - 4; $y += $stride ) {
			for ( $d = 0; $d < 4; $d++ ) {
				$sample( $d, $y );
				$sample( $w - 1 - $d, $y );
			}
		}
		imagedestroy( $img );
		return $total > 0 && ( $white / $total ) >= self::PACKSHOT_THRESHOLD;
	}

	/**
	 * IDs of image attachments without a placeholder (all images when $force).
	 *
	 * @return int[]
	 */
	public static function pending_ids( bool $force = false ): array {
		$args = [
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => 'image',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		];
		if ( ! $force ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => self::META,
					'compare' => 'NOT EXISTS',
				],
			];
		}
		$ids = get_posts( $args );
		return array_values( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : [] ), static fn( int $id ): bool => 'image/svg+xml' !== get_post_mime_type( $id ) ) );
	}

	/**
	 * admin_init: Tools entry (batch UI when the Import module ships it, synchronous fallback otherwise).
	 */
	public static function register_tool(): void {
		if ( ! class_exists( Tools::class ) ) {
			return;
		}
		Tools::add(
			self::TOOL_ID,
			__( 'Regenerate image placeholders (LQIP)', 'gadgetim-core' ),
			[ self::class, 'tool_run' ],
			Capabilities::ADMIN,
			[
				'description' => __( 'Creates the 16-pixel blur placeholder and the packshot flag for every image attachment that does not have one yet. New uploads get theirs automatically.', 'gadgetim-core' ),
				'button'      => __( 'Generate missing placeholders', 'gadgetim-core' ),
				'order'       => 40,
				'render'      => [ self::class, 'render_tool' ],
			]
		);
	}

	/**
	 * Tool UI: progress runner (JS) with the plain form as the no-JS fallback.
	 */
	public static function render_tool( array $tool ): void {
		if ( class_exists( 'Gadgetim\Core\Import\Admin\BatchUi' ) ) {
			\Gadgetim\Core\Import\Admin\BatchUi::render(
				[
					'id'           => 'gtm-lqip',
					'action'       => self::AJAX_ACTION,
					'nonce_action' => self::AJAX_ACTION,
					'start'        => (string) $tool['button'],
					'fields'       => static function (): void {
						echo '<label class="gtm-batch__option"><input type="checkbox" name="force" value="1"> ' . esc_html__( 'Regenerate existing placeholders too', 'gadgetim-core' ) . '</label>';
					},
					'fallback'     => static function () use ( $tool ): void {
						Tools::form( $tool, __( 'Run now (no JavaScript)', 'gadgetim-core' ) );
					},
				]
			);
			return;
		}
		Tools::form( $tool );
	}

	/**
	 * Synchronous tool run (no-JS fallback): processes as many pending images as fit in ~20 s.
	 */
	public static function tool_run(): string|WP_Error {
		$ids   = self::pending_ids( false );
		$total = count( $ids );
		if ( 0 === $total ) {
			return __( 'Every image already has a placeholder.', 'gadgetim-core' );
		}
		$deadline = microtime( true ) + 20;
		$done     = 0;
		foreach ( $ids as $id ) {
			self::update( $id );
			++$done;
			if ( microtime( true ) > $deadline ) {
				break;
			}
		}
		if ( $done < $total ) {
			/* translators: 1: processed count, 2: total count */
			return sprintf( __( 'Generated %1$d of %2$d placeholders; run the tool again to continue.', 'gadgetim-core' ), $done, $total );
		}
		/* translators: %d: processed count */
		return sprintf( __( 'Generated %d placeholders.', 'gadgetim-core' ), $done );
	}

	/**
	 * AJAX batch handler (manage_options + nonce): op=start|continue|stop, 20 images per call.
	 */
	public static function ajax(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::ADMIN ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to run this tool.', 'gadgetim-core' ) ], 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer() above.
		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : 'start';
		$job = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( (string) $_POST['job'] ) ) : '';
		// phpcs:enable

		if ( 'stop' === $op ) {
			if ( '' !== $job ) {
				delete_transient( self::JOB_PREFIX . $job );
			}
			wp_send_json_success( [ 'finished' => true ] );
		}

		if ( 'start' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$force = ! empty( $_POST['force'] );
			$ids   = self::pending_ids( $force );
			$job   = substr( md5( wp_generate_uuid4() ), 0, 12 );
			$state = [
				'ids'      => $ids,
				'position' => 0,
			];
			set_transient( self::JOB_PREFIX . $job, $state, DAY_IN_SECONDS );
			wp_send_json_success(
				[
					'job'      => $job,
					'total'    => count( $ids ),
					'done'     => 0,
					'finished' => 0 === count( $ids ),
					'status'   => 0 === count( $ids ) ? __( 'Every image already has a placeholder.', 'gadgetim-core' ) : __( 'Starting…', 'gadgetim-core' ),
					'log'      => [],
				]
			);
		}

		$state = '' !== $job ? get_transient( self::JOB_PREFIX . $job ) : false;
		if ( ! is_array( $state ) ) {
			wp_send_json_error( [ 'message' => __( 'The job has expired; start again.', 'gadgetim-core' ) ], 410 );
		}
		$ids      = (array) $state['ids'];
		$position = (int) $state['position'];
		$total    = count( $ids );
		$deadline = microtime( true ) + 20;
		$log      = [];
		$count    = 0;
		while ( $position < $total && $count < self::BATCH_SIZE && microtime( true ) < $deadline ) {
			$id = (int) $ids[ $position ];
			if ( ! self::update( $id ) ) {
				$log[] = [
					'level'   => 'warning',
					/* translators: %d: attachment ID */
					'message' => sprintf( __( 'Attachment #%d: placeholder could not be generated.', 'gadgetim-core' ), $id ),
				];
			}
			++$position;
			++$count;
		}
		$state['position'] = $position;
		$finished          = $position >= $total;
		if ( $finished ) {
			delete_transient( self::JOB_PREFIX . $job );
		} else {
			set_transient( self::JOB_PREFIX . $job, $state, DAY_IN_SECONDS );
		}
		wp_send_json_success(
			[
				'job'      => $job,
				'total'    => $total,
				'done'     => $position,
				'finished' => $finished,
				/* translators: 1: processed count, 2: total count */
				'status'   => sprintf( __( '%1$d of %2$d images processed.', 'gadgetim-core' ), $position, $total ),
				'log'      => $log,
			]
		);
	}

	/**
	 * GD path: decode, downscale, encode. '' when GD cannot handle the file.
	 */
	private static function generate_gd( string $file, string $mime ): string {
		$img = self::load_gd( $file );
		if ( ! $img ) {
			return '';
		}
		$w  = imagesx( $img );
		$h  = imagesy( $img );
		$tw = min( self::WIDTH, $w );
		$th = max( 1, (int) round( $h * $tw / max( 1, $w ) ) );

		$alpha = in_array( $mime, [ 'image/png', 'image/gif', 'image/webp' ], true ) && self::has_transparency( $img );
		$small = imagecreatetruecolor( $tw, $th );
		if ( ! $small ) {
			imagedestroy( $img );
			return '';
		}
		if ( $alpha ) {
			imagealphablending( $small, false );
			imagesavealpha( $small, true );
			$transparent = imagecolorallocatealpha( $small, 0, 0, 0, 127 );
			imagefilledrectangle( $small, 0, 0, $tw, $th, (int) $transparent );
		} else {
			$white = imagecolorallocate( $small, 255, 255, 255 );
			imagefilledrectangle( $small, 0, 0, $tw, $th, (int) $white );
		}
		imagecopyresampled( $small, $img, 0, 0, 0, 0, $tw, $th, $w, $h );
		imagedestroy( $img );

		ob_start();
		if ( $alpha ) {
			imagepng( $small, null, 9 );
		} else {
			imagejpeg( $small, null, self::JPEG_QUALITY );
		}
		$bytes = (string) ob_get_clean();
		imagedestroy( $small );
		if ( '' === $bytes ) {
			return '';
		}
		return 'data:' . ( $alpha ? 'image/png' : 'image/jpeg' ) . ';base64,' . base64_encode( $bytes );
	}

	/**
	 * WP_Image_Editor fallback (Imagick, or GD through the editor API).
	 */
	private static function generate_editor( string $file ): string {
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return '';
		}
		$size = $editor->get_size();
		$w    = (int) ( $size['width'] ?? 0 );
		$h    = (int) ( $size['height'] ?? 0 );
		if ( $w < 1 || $h < 1 ) {
			return '';
		}
		$tw = min( self::WIDTH, $w );
		$th = max( 1, (int) round( $h * $tw / $w ) );
		$editor->resize( $tw, $th, false );
		$editor->set_quality( self::JPEG_QUALITY );
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( 'gadgetim-lqip.jpg' );
		if ( '' === $tmp ) {
			return '';
		}
		$saved = $editor->save( $tmp, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
			if ( is_file( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return '';
		}
		$bytes = (string) file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $saved['path'] );
		if ( $saved['path'] !== $tmp && is_file( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		return '' === $bytes ? '' : 'data:image/jpeg;base64,' . base64_encode( $bytes );
	}

	/**
	 * Decodes an image with GD (truecolor), or null. Images above 25 MP are left to the editor path.
	 */
	private static function load_gd( string $file ): ?GdImage {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) || ( (int) $info[0] * (int) $info[1] ) > 25000000 ) {
			return null;
		}
		wp_raise_memory_limit( 'image' );
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $data || '' === $data ) {
			return null;
		}
		$img = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $img instanceof GdImage ) {
			return null;
		}
		if ( ! imageistruecolor( $img ) ) {
			imagepalettetotruecolor( $img );
		}
		return $img;
	}

	/**
	 * Cheap transparency probe: samples a grid of pixels for alpha > 0.
	 */
	private static function has_transparency( GdImage $img ): bool {
		$w     = imagesx( $img );
		$h     = imagesy( $img );
		$steps = 12;
		for ( $i = 0; $i < $steps; $i++ ) {
			for ( $j = 0; $j < $steps; $j++ ) {
				$x     = (int) floor( $i * ( $w - 1 ) / max( 1, $steps - 1 ) );
				$y     = (int) floor( $j * ( $h - 1 ) / max( 1, $steps - 1 ) );
				$alpha = ( imagecolorat( $img, $x, $y ) >> 24 ) & 0x7F;
				if ( $alpha > 0 ) {
					return true;
				}
			}
		}
		return false;
	}
}
