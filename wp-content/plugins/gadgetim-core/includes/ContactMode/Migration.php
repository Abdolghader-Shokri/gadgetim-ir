<?php
/**
 * Migration of the old call-for-price plugin's `cfp_options` into gadgetim_settings['contact'].
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\ContactMode;

use Gadgetim\Core\Settings\Admin\Tools;
use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Phone;
use WP_Error;

/**
 * Old structure (call-for-price.php 1.2.0):
 * { enabled, label, phone, show_price, wa_enabled, wa_number, wa_id, tg_enabled, tg_number, tg_id }
 * where the flags are 0/1 and the numbers are already digits with an optional leading "+".
 *
 * The old database export is not part of first-backup/extracted, so the Tools entry accepts the
 * option pasted as JSON (or as the PHP-serialized string straight from the SQL dump).
 */
final class Migration {

	public const TOOL = 'migrate-contact-mode';

	public const FIELD = 'gadgetim_cfp_payload';

	/**
	 * Pure mapping: old cfp_options -> dotted gadgetim_settings paths (no database access).
	 *
	 * Channel numbers land on the brand contact paths (contact.phone, contact.whatsapp,
	 * contact.telegram); apply() moves them to the contact.mode.* overrides when the brand
	 * value already exists and differs.
	 *
	 * @param array $cfp Old option value.
	 * @return array<string,mixed> [ 'contact.enabled' => bool, 'contact.label.fa' => string, … ]
	 */
	public static function from_cfp_options( array $cfp ): array {
		$map = [
			'contact.enabled'    => ! empty( $cfp['enabled'] ),
			'contact.show_price' => ! empty( $cfp['show_price'] ),
		];

		$label = isset( $cfp['label'] ) && is_scalar( $cfp['label'] ) ? sanitize_text_field( (string) $cfp['label'] ) : '';
		if ( '' !== $label ) {
			$map['contact.label.fa'] = $label;
		}

		$phone = isset( $cfp['phone'] ) && is_scalar( $cfp['phone'] ) ? Phone::normalize( (string) $cfp['phone'] ) : '';
		if ( '' !== $phone ) {
			$map['contact.phone'] = $phone;
		}

		$wa_number = isset( $cfp['wa_number'] ) && is_scalar( $cfp['wa_number'] ) ? Phone::normalize( (string) $cfp['wa_number'] ) : '';
		if ( '' !== $wa_number ) {
			$map['contact.whatsapp'] = $wa_number;
		}

		$tg_id = isset( $cfp['tg_id'] ) && is_scalar( $cfp['tg_id'] ) ? Phone::handle( (string) $cfp['tg_id'] ) : '';
		if ( '' !== $tg_id ) {
			$map['contact.telegram'] = $tg_id;
		}

		$wa_on = ! empty( $cfp['wa_enabled'] ) && '' !== $wa_number;
		$tg_on = ! empty( $cfp['tg_enabled'] ) && '' !== $tg_id;
		if ( $wa_on ) {
			$map['contact.primary'] = 'whatsapp';
		} elseif ( '' !== $phone ) {
			$map['contact.primary'] = 'call';
		} elseif ( $tg_on ) {
			$map['contact.primary'] = 'telegram';
		}

		return $map;
	}

	/**
	 * Parses the pasted payload: JSON object, or a PHP-serialized array (objects are never
	 * instantiated: allowed_classes is false).
	 *
	 * @return array|WP_Error
	 */
	public static function parse( string $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return new WP_Error( 'gadgetim_cfp_empty', __( 'Paste the old cfp_options value first.', 'gadgetim-core' ) );
		}
		if ( strlen( $raw ) > 20000 ) {
			return new WP_Error( 'gadgetim_cfp_too_long', __( 'The pasted value is too long to be a cfp_options option.', 'gadgetim-core' ) );
		}

		$data = null;
		if ( '{' === $raw[0] || '[' === $raw[0] ) {
			$data = json_decode( $raw, true );
		} elseif ( str_starts_with( $raw, 'a:' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- allowed_classes => false (no object instantiation); a malformed string only yields false.
			$data = @unserialize( $raw, [ 'allowed_classes' => false ] );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gadgetim_cfp_invalid', __( 'The value is neither a JSON object nor a serialized PHP array.', 'gadgetim-core' ) );
		}

		// Accept the whole wp_options row export: { "option_name": "cfp_options", "option_value": "a:9:{…}" }.
		if ( isset( $data['option_value'] ) && is_string( $data['option_value'] ) ) {
			return self::parse( $data['option_value'] );
		}

		$known = [ 'enabled', 'label', 'phone', 'show_price', 'wa_enabled', 'wa_number', 'wa_id', 'tg_enabled', 'tg_number', 'tg_id' ];
		if ( ! array_intersect( $known, array_keys( $data ) ) ) {
			return new WP_Error( 'gadgetim_cfp_unknown', __( 'None of the cfp_options keys (enabled, label, phone, show_price, wa_*, tg_*) were found.', 'gadgetim-core' ) );
		}
		return array_intersect_key( $data, array_flip( $known ) );
	}

	/**
	 * Writes the mapped values into gadgetim_settings.
	 *
	 * @param array $cfp Old option value.
	 * @return array{written: string[], skipped: string[], notes: string[]}
	 */
	public static function apply( array $cfp ): array {
		$map     = self::from_cfp_options( $cfp );
		$written = [];
		$skipped = [];
		$notes   = [];
		$updates = [];

		foreach ( [ 'phone', 'whatsapp', 'telegram' ] as $channel ) {
			$path = 'contact.' . $channel;
			if ( ! isset( $map[ $path ] ) ) {
				continue;
			}
			$incoming = (string) $map[ $path ];
			$existing = trim( (string) Settings::get( $path, '' ) );
			unset( $map[ $path ] );
			if ( '' === $existing || $existing === $incoming ) {
				$updates[ $path ] = $incoming;
			} else {
				// Keep the site-wide number; the old call-for-price number becomes the mode override.
				$updates[ 'contact.mode.' . $channel ] = $incoming;
				/* translators: 1: settings path, 2: settings path */
				$notes[] = sprintf( __( '%1$s already had a different value, so the old number was stored as %2$s.', 'gadgetim-core' ), $path, 'contact.mode.' . $channel );
			}
		}

		if ( isset( $map['contact.label.fa'] ) ) {
			$label = Settings::get( 'contact.label' );
			$label = is_array( $label ) ? $label : [];
			$label['fa'] = (string) $map['contact.label.fa'];
			$updates['contact.label'] = $label;
			unset( $map['contact.label.fa'] );
		}

		foreach ( $map as $path => $value ) {
			$updates[ $path ] = $value;
		}

		if ( ! empty( $cfp['tg_number'] ) && empty( $cfp['tg_id'] ) ) {
			$skipped[] = 'tg_number';
			$notes[]   = __( 'tg_number was skipped: Telegram links need a username, not a phone number.', 'gadgetim-core' );
		}
		if ( ! empty( $cfp['wa_id'] ) && empty( $cfp['wa_number'] ) ) {
			$skipped[] = 'wa_id';
			$notes[]   = __( 'wa_id was skipped: WhatsApp links are built from the number only.', 'gadgetim-core' );
		}

		if ( $updates ) {
			Settings::update( $updates );
			ContactMode::reset();
			$written = array_keys( $updates );
		}

		return [
			'written' => $written,
			'skipped' => $skipped,
			'notes'   => $notes,
		];
	}

	/**
	 * admin_init: registers the Tools entry (manage_options, D11).
	 */
	public static function register_tool(): void {
		if ( ! class_exists( Tools::class ) ) {
			return;
		}
		Tools::add(
			self::TOOL,
			__( 'Migrate call-for-price settings', 'gadgetim-core' ),
			[ self::class, 'run_tool' ],
			Capabilities::ADMIN,
			[
				'description' => __( 'Copies the old call-for-price plugin\'s cfp_options (enabled, label, phone, show_price, WhatsApp and Telegram) into the "Contact instead of price" settings. Paste the option value from the old database as JSON or as the serialized PHP string from the SQL dump. Existing site-wide numbers are kept; a different old number becomes the mode override.', 'gadgetim-core' ),
				'button'      => __( 'Migrate', 'gadgetim-core' ),
				'order'       => 40,
				'render'      => [ self::class, 'render_tool' ],
			]
		);
	}

	/**
	 * Custom tool UI: the payload textarea inside the nonce-protected admin-post form.
	 *
	 * @param array $tool Tool definition.
	 */
	public static function render_tool( array $tool ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gtm-tool__form gtm-tool__form--stacked">
			<input type="hidden" name="action" value="<?php echo esc_attr( Tools::ACTION ); ?>">
			<input type="hidden" name="tool" value="<?php echo esc_attr( $tool['id'] ); ?>">
			<?php wp_nonce_field( Tools::ACTION . '_' . $tool['id'] ); ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( self::FIELD ); ?>"><?php esc_html_e( 'Old cfp_options value', 'gadgetim-core' ); ?></label>
			<textarea id="<?php echo esc_attr( self::FIELD ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>" class="large-text code gtm-textarea" rows="4" dir="ltr" spellcheck="false" placeholder='{"enabled":0,"label":"تماس بگیرید","phone":"+98917…","show_price":1,"wa_enabled":0,"wa_number":"","wa_id":"","tg_enabled":0,"tg_number":"","tg_id":""}'></textarea>
			<p>
				<button type="submit" class="button button-secondary"><?php echo esc_html( $tool['button'] ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Tool callable (Tools::handle verified the nonce and the capabilities).
	 *
	 * @param array $tool Tool definition.
	 * @return string|WP_Error|array
	 */
	public static function run_tool( array $tool ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tools::handle() ran check_admin_referer() for this tool.
		$raw = isset( $_POST[ self::FIELD ] ) ? (string) wp_unslash( $_POST[ self::FIELD ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and whitelisted in parse().
		$cfp = self::parse( $raw );
		if ( $cfp instanceof WP_Error ) {
			return $cfp;
		}

		$result = self::apply( $cfp );
		if ( ! $result['written'] ) {
			return [
				'type'    => 'warning',
				'message' => __( 'Nothing to migrate: the pasted value carried no usable settings.', 'gadgetim-core' ),
			];
		}

		$message = sprintf(
			/* translators: %s: comma-separated list of settings paths */
			__( 'Call-for-price settings migrated: %s.', 'gadgetim-core' ),
			implode( ', ', array_map( 'esc_html', $result['written'] ) )
		);
		if ( $result['notes'] ) {
			$message .= ' ' . implode( ' ', array_map( 'esc_html', $result['notes'] ) );
		}
		if ( ! ContactMode::enabled() ) {
			$message .= ' ' . __( 'The mode stays off until you enable it on the "Contact instead of price" tab.', 'gadgetim-core' );
		}
		return [
			'type'    => 'success',
			'message' => $message,
		];
	}
}
