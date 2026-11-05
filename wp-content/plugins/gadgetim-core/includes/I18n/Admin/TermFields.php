<?php
/**
 * Per-language name/slug/description/status on the term screens (architecture §3.3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Search;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Settings\Admin\Notices;
use Gadgetim\Core\Support\Context as SupportContext;
use Gadgetim\Core\Support\Html;
use WP_Term;

/**
 * Add/edit forms of product_cat, product_tag, product_brand, category, post_tag and the
 * attribute taxonomies get one block per secondary language. Saved on created_/edited_{tax}
 * with a nonce and the taxonomy's edit_terms capability.
 */
final class TermFields {

	public const ACTION = 'gadgetim_term_translations';

	public const NONCE = 'gadgetim_term_translations_nonce';

	public const FIELD = 'gtm_term_tr';

	public const REINDEX_LIMIT = 500;

	/**
	 * Hooks (taxonomies exist after init).
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'hook_taxonomies' ], 40 );
	}

	/**
	 * init (40): per-taxonomy form and save hooks.
	 */
	public static function hook_taxonomies(): void {
		if ( ! Languages::secondary_codes() ) {
			return;
		}
		foreach ( TermTranslations::taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			add_action( $taxonomy . '_add_form_fields', [ self::class, 'render_add' ], 20, 1 );
			add_action( $taxonomy . '_edit_form_fields', [ self::class, 'render_edit' ], 20, 2 );
			add_action( 'created_' . $taxonomy, [ self::class, 'save' ], 20, 2 );
			add_action( 'edited_' . $taxonomy, [ self::class, 'save' ], 20, 2 );
		}
	}

	/**
	 * Add-term form.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function render_add( $taxonomy ): void {
		echo '<div class="gtm-term-tr" data-gtm-tr>';
		echo '<h3>' . esc_html__( 'Translations', 'gadgetim-core' ) . '</h3>';
		wp_nonce_field( self::ACTION, self::NONCE );
		self::render_tabs( null, (string) $taxonomy );
		echo '</div>';
	}

	/**
	 * Edit-term form (table rows).
	 *
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function render_edit( $term, $taxonomy = '' ): void {
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Translations', 'gadgetim-core' ) . '</th><td>';
		echo '<div class="gtm-term-tr" data-gtm-tr>';
		wp_nonce_field( self::ACTION, self::NONCE );
		self::render_tabs( $term, (string) $taxonomy );
		echo '</div>';
		echo '</td></tr>';
	}

	/**
	 * Tabs + panels shared by both forms.
	 */
	private static function render_tabs( ?WP_Term $term, string $taxonomy ): void {
		$langs = Languages::secondary_codes();
		echo '<div class="gtm-tr__tabs" role="tablist">';
		foreach ( $langs as $lang ) {
			$status = $term ? TermTranslations::status( $term->term_id, $lang ) : '';
			echo '<button type="button" class="gtm-tr__tab" role="tab" data-gtm-tab="' . esc_attr( $lang ) . '" aria-selected="false">';
			echo '<span class="gtm-tr__code">' . esc_html( $lang ) . '</span> ' . esc_html( Languages::label( $lang ) );
			if ( '' !== $status ) {
				echo ' <span class="gtm-tr__badge gtm-tr__badge--' . esc_attr( $status ) . '">' . esc_html( TranslationMetabox::status_label( $status ) ) . '</span>';
			}
			echo '</button>';
		}
		echo '</div>';

		foreach ( $langs as $lang ) {
			$values = $term ? TermTranslations::all( $term->term_id, $lang ) : array_fill_keys( TermTranslations::FIELDS, '' );
			$dir    = Languages::dir( $lang );
			echo '<div class="gtm-tr__panel" data-gtm-panel="' . esc_attr( $lang ) . '" role="tabpanel" hidden>';
			self::input( $lang, 'name', __( 'Name', 'gadgetim-core' ), $values['name'], $dir, true );
			self::input( $lang, 'slug', __( 'Slug', 'gadgetim-core' ), $values['slug'], 'ltr', true );
			echo '<div class="gtm-tr__row"><label for="gtm_tr_' . esc_attr( $lang ) . '_desc">' . esc_html__( 'Description', 'gadgetim-core' ) . '</label><div class="gtm-tr__control">';
			echo '<textarea id="gtm_tr_' . esc_attr( $lang ) . '_desc" name="' . esc_attr( self::FIELD . '[' . $lang . '][desc]' ) . '" rows="3" dir="' . esc_attr( $dir ) . '">' . esc_textarea( $values['desc'] ) . '</textarea>';
			echo ' <button type="button" class="button-link gtm-tr__copy" data-gtm-copy="desc" data-gtm-lang="' . esc_attr( $lang ) . '">' . esc_html__( 'Copy from Persian', 'gadgetim-core' ) . '</button>';
			echo '</div></div>';
			echo '<div class="gtm-tr__row"><label for="gtm_tr_' . esc_attr( $lang ) . '_status">' . esc_html__( 'Status', 'gadgetim-core' ) . '</label><div class="gtm-tr__control">';
			echo '<select id="gtm_tr_' . esc_attr( $lang ) . '_status" name="' . esc_attr( self::FIELD . '[' . $lang . '][status]' ) . '" class="gtm-tr__status">';
			foreach ( [ '' => __( 'Not translated', 'gadgetim-core' ), 'draft' => __( 'Draft', 'gadgetim-core' ), 'published' => __( 'Published', 'gadgetim-core' ) ] as $option => $text ) {
				echo '<option value="' . esc_attr( $option ) . '" ' . selected( $values['status'], $option, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
			echo '<p class="gtm-tr__hint">' . esc_html__( 'Published terms appear in menus, tiles, filters, sitemaps and hreflang of that language.', 'gadgetim-core' ) . '</p>';
			echo '</div></div>';
			echo '</div>';
		}

		$source = $term ? [
			'name' => $term->name,
			'slug' => $term->slug,
			'desc' => $term->description,
		] : [];
		echo '<script type="application/json" data-gtm-source>' . Html::json( $source ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HEX flags.
	}

	/**
	 * One text input row.
	 */
	private static function input( string $lang, string $field, string $label, string $value, string $dir, bool $copy ): void {
		$id = 'gtm_tr_' . $lang . '_' . $field;
		echo '<div class="gtm-tr__row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div class="gtm-tr__control">';
		echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::FIELD . '[' . $lang . '][' . $field . ']' ) . '" value="' . esc_attr( $value ) . '" dir="' . esc_attr( $dir ) . '">';
		if ( $copy ) {
			echo ' <button type="button" class="button-link gtm-tr__copy" data-gtm-copy="' . esc_attr( $field ) . '" data-gtm-lang="' . esc_attr( $lang ) . '">' . esc_html__( 'Copy from Persian', 'gadgetim-core' ) . '</button>';
		}
		echo '</div></div>';
	}

	/**
	 * created_{tax} / edited_{tax}: nonce, capability, sanitisation, slug validation.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Term taxonomy ID.
	 */
	public static function save( $term_id, $tt_id = 0 ): void {
		$term_id = (int) $term_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			return;
		}
		// created_{taxonomy} / edited_{taxonomy}: the taxonomy is in the hook name (a bare term id
		// could be ambiguous for legacy shared terms).
		$taxonomy = preg_replace( '/^(?:created|edited)_/', '', (string) current_action() ) ?? '';
		if ( '' === $taxonomy || ! TermTranslations::supports( $taxonomy ) ) {
			return;
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$tax = get_taxonomy( $term->taxonomy );
		if ( ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		if ( ! is_array( $input ) ) {
			return;
		}
		SupportContext::with_suspended(
			static function () use ( $term, $input ): void {
				self::persist( $term, $input );
			}
		);
	}

	/**
	 * Writes the submitted languages and re-indexes the term's posts.
	 */
	private static function persist( WP_Term $term, array $input ): void {
		$changed = false;
		foreach ( Languages::secondary_codes() as $lang ) {
			if ( ! isset( $input[ $lang ] ) || ! is_array( $input[ $lang ] ) ) {
				continue;
			}
			$row  = $input[ $lang ];
			$name = TermTranslations::sanitize( 'name', $row['name'] ?? '' );
			TermTranslations::set( $term->term_id, 'name', $lang, $name );
			TermTranslations::set( $term->term_id, 'desc', $lang, $row['desc'] ?? '' );

			$old_slug  = TermTranslations::slug( $term->term_id, $lang );
			$requested = sanitize_title( (string) ( $row['slug'] ?? '' ) );
			if ( '' === $requested && '' !== $name ) {
				$requested = sanitize_title( $name );
			}
			$slug = '' === $requested ? '' : Slugs::validate( $requested, $lang, $term->taxonomy, $term->term_id, 'term' );
			if ( '' !== $requested && $slug !== $requested && class_exists( Notices::class ) ) {
				Notices::add(
					'tr-term-' . $term->term_id . '-' . $lang,
					sprintf(
						/* translators: 1: term name, 2: language code, 3: requested slug, 4: slug saved */
						__( '"%1$s": the %2$s slug "%3$s" was already in use; "%4$s" was saved instead.', 'gadgetim-core' ),
						$term->name,
						strtoupper( $lang ),
						$requested,
						$slug
					),
					'warning',
					[
						'link' => [
							'url'   => (string) get_edit_term_link( $term->term_id, $term->taxonomy ),
							'label' => __( 'Edit', 'gadgetim-core' ),
						],
					]
				);
			}
			if ( '' !== $old_slug && '' !== $slug && $old_slug !== $slug ) {
				Slugs::remember_previous( 'term', $term->term_id, $lang, $old_slug, $slug );
			}
			TermTranslations::set( $term->term_id, 'slug', $lang, $slug );

			$status = TermTranslations::sanitize( 'status', $row['status'] ?? '' );
			if ( 'published' === $status && '' === $name ) {
				$status = 'draft';
			}
			TermTranslations::set( $term->term_id, 'status', $lang, $status );
			$changed = true;
		}
		if ( $changed ) {
			Slugs::invalidate_all();
			self::reindex_posts( $term );
		}
	}

	/**
	 * Search blobs of the term's posts contain its names: re-index them (bounded).
	 */
	private static function reindex_posts( WP_Term $term ): void {
		$objects = get_objects_in_term( $term->term_id, $term->taxonomy );
		if ( ! is_array( $objects ) || ! $objects ) {
			return;
		}
		foreach ( array_slice( array_map( 'intval', $objects ), 0, self::REINDEX_LIMIT ) as $post_id ) {
			Search::index_post( $post_id );
		}
	}
}
