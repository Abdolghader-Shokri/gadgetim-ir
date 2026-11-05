<?php
/**
 * Tabbed translation metabox for products and pages (architecture §3.3, §5.10).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\Settings\Admin\Notices;
use Gadgetim\Core\Support\Context as SupportContext;
use Gadgetim\Core\Support\Html;
use WP_Post;

/**
 * One tab per secondary language: title, slug (greyed for WooCommerce pages), excerpt,
 * content (wp_editor), subtitle, SEO title/description, status, with "copy from Persian"
 * buttons. Other modules add fields through the gadgetim_translation_fields filter.
 */
final class TranslationMetabox {

	public const ID = 'gadgetim-translations';

	public const ACTION = 'gadgetim_translations';

	public const NONCE = 'gadgetim_translations_nonce';

	public const FIELD = 'gtm_tr';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add' ], 10, 2 );
		add_action( 'save_post', [ self::class, 'save' ], 20, 2 );
	}

	/**
	 * add_meta_boxes.
	 *
	 * @param string       $post_type Post type.
	 * @param WP_Post|null $post      Post.
	 */
	public static function add( $post_type, $post = null ): void {
		if ( ! PostTranslations::supports( (string) $post_type ) || ! Languages::secondary_codes() ) {
			return;
		}
		add_meta_box( self::ID, __( 'Translations', 'gadgetim-core' ), [ self::class, 'render' ], (string) $post_type, 'normal', 'high' );
	}

	/**
	 * Field definitions for a post type (filter gadgetim_translation_fields).
	 *
	 * Keys: type (text|textarea|editor|slug|status), label, source ('post_title', 'post_name',
	 * 'post_excerpt', 'post_content' or 'meta:_gtm_subtitle'), rows, post_types, key (meta key
	 * pattern with {lang} for non-core fields), sanitize (callable($value, $field, $lang)).
	 *
	 * @return array<string,array>
	 */
	public static function fields( string $post_type ): array {
		$is_product = 'product' === $post_type;
		$fields     = [
			'title'     => [
				'type'   => 'text',
				'label'  => __( 'Title', 'gadgetim-core' ),
				'source' => 'post_title',
			],
			'slug'      => [
				'type'   => 'slug',
				'label'  => __( 'Slug', 'gadgetim-core' ),
				'source' => 'post_name',
			],
			'subtitle'  => [
				'type'       => 'text',
				'label'      => __( 'Subtitle', 'gadgetim-core' ),
				'source'     => 'meta:_gtm_subtitle',
				'post_types' => [ 'product' ],
			],
			'excerpt'   => [
				'type'   => 'textarea',
				'label'  => $is_product ? __( 'Short description', 'gadgetim-core' ) : __( 'Excerpt', 'gadgetim-core' ),
				'source' => 'post_excerpt',
				'rows'   => 4,
			],
			'content'   => [
				'type'   => 'editor',
				'label'  => $is_product ? __( 'Description', 'gadgetim-core' ) : __( 'Content', 'gadgetim-core' ),
				'source' => 'post_content',
			],
			'seo_title' => [
				'type'   => 'text',
				'label'  => __( 'SEO title', 'gadgetim-core' ),
				'source' => 'meta:_gtm_seo_title',
			],
			'seo_desc'  => [
				'type'   => 'textarea',
				'label'  => __( 'SEO description', 'gadgetim-core' ),
				'source' => 'meta:_gtm_seo_desc',
				'rows'   => 2,
			],
			'status'    => [
				'type'  => 'status',
				'label' => __( 'Translation status', 'gadgetim-core' ),
			],
		];

		/**
		 * Filters the translation metabox fields (Catalog adds specs, FAQ and guarantee).
		 *
		 * @param array  $fields    [ id => definition ].
		 * @param string $post_type Post type.
		 */
		$fields = (array) apply_filters( 'gadgetim_translation_fields', $fields, $post_type );

		$out = [];
		foreach ( $fields as $id => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$id = sanitize_key( (string) $id );
			if ( '' === $id ) {
				continue;
			}
			if ( ! empty( $def['post_types'] ) && ! in_array( $post_type, (array) $def['post_types'], true ) ) {
				continue;
			}
			$out[ $id ] = $def;
		}
		// Status always comes last.
		if ( isset( $out['status'] ) ) {
			$status = $out['status'];
			unset( $out['status'] );
			$out['status'] = $status;
		}
		return $out;
	}

	/**
	 * Persian source values for the "copy from Persian" buttons.
	 *
	 * @return array<string,string>
	 */
	public static function sources( WP_Post $post, array $fields ): array {
		$sources = [];
		foreach ( $fields as $id => $def ) {
			$source = (string) ( $def['source'] ?? '' );
			if ( '' === $source ) {
				continue;
			}
			if ( str_starts_with( $source, 'meta:' ) ) {
				$value = get_post_meta( $post->ID, substr( $source, 5 ), true );
			} else {
				$value = $post->{$source} ?? '';
			}
			$sources[ $id ] = is_scalar( $value ) ? (string) $value : '';
		}
		return $sources;
	}

	/**
	 * Renders the metabox.
	 */
	public static function render( WP_Post $post ): void {
		$fields  = self::fields( $post->post_type );
		$langs   = Languages::secondary_codes();
		$wc_page = Slugs::is_wc_page( $post->ID );

		wp_nonce_field( self::ACTION . '_' . $post->ID, self::NONCE );
		echo '<div class="gtm-tr" data-gtm-tr>';
		echo '<div class="gtm-tr__tabs" role="tablist">';
		foreach ( $langs as $lang ) {
			$status = PostTranslations::status( $post->ID, $lang );
			echo '<button type="button" class="gtm-tr__tab" role="tab" data-gtm-tab="' . esc_attr( $lang ) . '" aria-controls="gtm-tr-panel-' . esc_attr( $lang ) . '" aria-selected="false">';
			echo '<span class="gtm-tr__code">' . esc_html( $lang ) . '</span> ' . esc_html( Languages::label( $lang ) );
			if ( '' !== $status ) {
				echo ' <span class="gtm-tr__badge gtm-tr__badge--' . esc_attr( $status ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
			}
			echo '</button>';
		}
		echo '</div>';

		foreach ( $langs as $lang ) {
			$values = PostTranslations::all( $post->ID, $lang );
			echo '<div class="gtm-tr__panel" id="gtm-tr-panel-' . esc_attr( $lang ) . '" data-gtm-panel="' . esc_attr( $lang ) . '" role="tabpanel" hidden>';
			echo '<p class="gtm-tr__hint">';
			echo '<button type="button" class="button button-small" data-gtm-copy="*" data-gtm-lang="' . esc_attr( $lang ) . '">' . esc_html__( 'Copy everything from Persian', 'gadgetim-core' ) . '</button> ';
			if ( '' !== $values['slug'] && 'published' === $values['status'] ) {
				$url = Languages::with_lang( $lang, static fn(): string => (string) get_permalink( $post ) );
				if ( '' !== $url ) {
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View translation', 'gadgetim-core' ) . '</a>';
				}
			}
			echo '</p>';
			foreach ( $fields as $id => $def ) {
				$value = $values[ $id ] ?? self::extra_value( $post->ID, $id, $def, $lang );
				self::render_field( $post, $lang, $id, $def, (string) $value, $wc_page );
			}
			echo '</div>';
		}

		echo '<span class="screen-reader-text" data-gtm-live aria-live="polite"></span>';
		echo '<script type="application/json" data-gtm-source>' . Html::json( self::sources( $post, $fields ) ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HEX flags.
		echo '</div>';
	}

	/**
	 * One field row.
	 */
	private static function render_field( WP_Post $post, string $lang, string $id, array $def, string $value, bool $wc_page ): void {
		$type   = (string) ( $def['type'] ?? 'text' );
		$label  = (string) ( $def['label'] ?? $id );
		$name   = self::FIELD . '[' . $lang . '][' . $id . ']';
		$dom_id = 'gtm_tr_' . $lang . '_' . $id;
		$dir    = Languages::dir( $lang );
		$copy   = ! empty( $def['source'] ) && 'status' !== $type
			? ' <button type="button" class="button-link gtm-tr__copy" data-gtm-copy="' . esc_attr( $id ) . '" data-gtm-lang="' . esc_attr( $lang ) . '">' . esc_html__( 'Copy from Persian', 'gadgetim-core' ) . '</button>'
			: '';

		echo '<div class="gtm-tr__row gtm-tr__row--' . esc_attr( $type ) . '">';
		if ( 'editor' === $type ) {
			echo '<span class="gtm-tr__label">' . esc_html( $label ) . '</span>';
		} else {
			echo '<label for="' . esc_attr( $dom_id ) . '">' . esc_html( $label ) . '</label>';
		}
		echo '<div class="gtm-tr__control">';

		switch ( $type ) {
			case 'slug':
				if ( $wc_page ) {
					echo '<input type="text" class="regular-text" id="' . esc_attr( $dom_id ) . '" value="' . esc_attr( $post->post_name ) . '" dir="ltr" disabled>';
					echo '<p class="gtm-tr__hint">' . esc_html__( 'WooCommerce pages keep their slug in every language (/en/shop/, /en/cart/ ...).', 'gadgetim-core' ) . '</p>';
				} else {
					echo '<input type="text" class="regular-text" id="' . esc_attr( $dom_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" dir="ltr" autocomplete="off">' . $copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					echo '<p class="gtm-tr__hint">' . esc_html__( 'Left empty, the slug is derived from the translated title. Collisions get a numeric suffix; previous slugs redirect.', 'gadgetim-core' ) . '</p>';
				}
				break;

			case 'textarea':
				$rows = max( 2, (int) ( $def['rows'] ?? 4 ) );
				echo '<textarea id="' . esc_attr( $dom_id ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) $rows ) . '" dir="' . esc_attr( $dir ) . '">' . esc_textarea( $value ) . '</textarea>' . $copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;

			case 'editor':
				echo $copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				wp_editor(
					$value,
					$dom_id,
					[
						'textarea_name' => $name,
						'textarea_rows' => 12,
						'editor_height' => 260,
						'media_buttons' => true,
						'teeny'         => false,
						'quicktags'     => true,
					]
				);
				break;

			case 'status':
				echo '<select id="' . esc_attr( $dom_id ) . '" name="' . esc_attr( $name ) . '" class="gtm-tr__status">';
				foreach ( [ '' => __( 'Not translated', 'gadgetim-core' ), 'draft' => __( 'Draft (editors only)', 'gadgetim-core' ), 'published' => __( 'Published', 'gadgetim-core' ) ] as $option => $text ) {
					echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $text ) . '</option>';
				}
				echo '</select>';
				echo '<p class="gtm-tr__hint">' . esc_html__( 'Only published translations are visible under the language prefix: in archives, search, related products, sitemaps and hreflang.', 'gadgetim-core' ) . '</p>';
				break;

			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $dom_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" dir="' . esc_attr( $dir ) . '">' . $copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></div>';
	}

	/**
	 * Current value of a non-core (filter-added) field.
	 */
	private static function extra_value( int $post_id, string $id, array $def, string $lang ): string {
		$key   = self::extra_key( $id, $def, $lang );
		$value = get_post_meta( $post_id, $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Meta key of a non-core field ('key' with {lang}, default _gtm_{id}_{lang}).
	 */
	private static function extra_key( string $id, array $def, string $lang ): string {
		$pattern = (string) ( $def['key'] ?? '_gtm_' . $id . '_{lang}' );
		return str_replace( '{lang}', $lang, $pattern );
	}

	/**
	 * save_post (20): nonce, capability, sanitisation per §5.10, slug validation.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function save( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( ! $post instanceof WP_Post || ! PostTranslations::supports( $post->post_type ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitised per field below.
		if ( ! is_array( $input ) ) {
			return;
		}
		SupportContext::with_suspended(
			static function () use ( $post, $input ): void {
				self::persist( $post, $input );
			}
		);
	}

	/**
	 * Writes every submitted language.
	 */
	private static function persist( WP_Post $post, array $input ): void {
		$fields  = self::fields( $post->post_type );
		$wc_page = Slugs::is_wc_page( $post->ID );

		foreach ( Languages::secondary_codes() as $lang ) {
			if ( ! isset( $input[ $lang ] ) || ! is_array( $input[ $lang ] ) ) {
				continue; // Panel not submitted: leave this language untouched.
			}
			$row   = $input[ $lang ];
			$title = PostTranslations::sanitize( 'title', $row['title'] ?? '' );

			foreach ( $fields as $id => $def ) {
				if ( in_array( $id, [ 'slug', 'status' ], true ) ) {
					continue;
				}
				$raw = $row[ $id ] ?? '';
				if ( in_array( $id, PostTranslations::FIELDS, true ) ) {
					PostTranslations::set( $post->ID, $id, $lang, $raw );
					continue;
				}
				self::persist_extra( $post->ID, $id, $def, $lang, $raw );
			}

			// Slug: never for WooCommerce pages; auto from the translated title when empty.
			if ( array_key_exists( 'slug', $row ) || '' !== $title ) {
				$old_slug  = PostTranslations::slug( $post->ID, $lang );
				$requested = $wc_page ? '' : sanitize_title( (string) ( $row['slug'] ?? '' ) );
				if ( '' === $requested && ! $wc_page && '' !== $title ) {
					$requested = sanitize_title( $title );
				}
				$slug = '' === $requested ? '' : Slugs::validate( $requested, $lang, $post->post_type, $post->ID, 'post' );
				if ( '' !== $requested && $slug !== $requested ) {
					self::notice(
						$post,
						$lang,
						sprintf(
							/* translators: 1: language code, 2: requested slug, 3: slug saved */
							__( 'The %1$s slug "%2$s" was already in use; "%3$s" was saved instead.', 'gadgetim-core' ),
							strtoupper( $lang ),
							$requested,
							$slug
						)
					);
				}
				if ( '' !== $old_slug && '' !== $slug && $old_slug !== $slug ) {
					Slugs::remember_previous( 'post', $post->ID, $lang, $old_slug, $slug );
				}
				PostTranslations::set( $post->ID, 'slug', $lang, $slug );
			}

			// Status: a published translation needs a title.
			$status = PostTranslations::sanitize( 'status', $row['status'] ?? '' );
			if ( 'published' === $status && '' === $title ) {
				$status = 'draft';
				self::notice(
					$post,
					$lang,
					sprintf(
						/* translators: %s: language code */
						__( 'The %s translation has no title, so it was saved as a draft.', 'gadgetim-core' ),
						strtoupper( $lang )
					)
				);
			}
			PostTranslations::set( $post->ID, 'status', $lang, $status );
		}
	}

	/**
	 * Writes a filter-added field with its own or a type-based sanitiser.
	 */
	private static function persist_extra( int $post_id, string $id, array $def, string $lang, mixed $raw ): void {
		$key = self::extra_key( $id, $def, $lang );
		if ( is_callable( $def['sanitize'] ?? null ) ) {
			$value = call_user_func( $def['sanitize'], $raw, $def, $lang );
		} else {
			$type  = (string) ( $def['type'] ?? 'text' );
			$value = is_scalar( $raw ) ? (string) $raw : '';
			$value = in_array( $type, [ 'textarea', 'editor' ], true ) ? wp_kses_post( $value ) : sanitize_text_field( $value );
		}
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Persistent notice about a save adjustment.
	 */
	private static function notice( WP_Post $post, string $lang, string $message ): void {
		if ( ! class_exists( Notices::class ) ) {
			return;
		}
		$link = get_edit_post_link( $post->ID, 'raw' );
		Notices::add(
			'tr-' . $post->ID . '-' . $lang,
			/* translators: 1: post title, 2: message */
			sprintf( __( '"%1$s": %2$s', 'gadgetim-core' ), get_the_title( $post ), $message ),
			'warning',
			[
				'link'       => $link ? [
					'url'   => $link,
					'label' => __( 'Edit', 'gadgetim-core' ),
				] : [],
				'product_id' => 'product' === $post->post_type ? $post->ID : 0,
			]
		);
	}

	/**
	 * Human label of a translation status.
	 */
	public static function status_label( string $status ): string {
		return match ( $status ) {
			'published' => __( 'Published', 'gadgetim-core' ),
			'draft'     => __( 'Draft', 'gadgetim-core' ),
			default     => __( 'Not translated', 'gadgetim-core' ),
		};
	}
}
