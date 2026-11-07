<?php
/**
 * SMTP delivery for wp_mail() and the mail() guard.
 *
 * Settings live in gadgetim_settings under "mail.*" (tab "E-mail", manage_options). The password is
 * never printed back into the form; saving with the field empty keeps the stored one.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Mail;

use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;

/**
 * SMTP configuration, guard and test message.
 */
final class Smtp {

	public const TAB = 'mail';

	private const TEST_ACTION = 'gadgetim_mail_test';

	private const TEST_RESULT = 'gadgetim_mail_test_result';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_settings_tab' ], 5 );
		add_action( 'phpmailer_init', [ self::class, 'configure' ], 5 );
		add_action( 'phpmailer_init', [ self::class, 'fallback' ], PHP_INT_MAX );
		add_filter( 'pre_wp_mail', [ self::class, 'guard' ], 999, 2 );
		add_action( 'admin_post_' . self::TEST_ACTION, [ self::class, 'send_test' ] );
		add_action( 'admin_notices', [ self::class, 'test_notice' ] );
	}

	/**
	 * Whether SMTP is switched on and has a host.
	 */
	public static function enabled(): bool {
		return (bool) Settings::get( 'mail.enabled', false ) && '' !== trim( (string) Settings::get( 'mail.host', '' ) );
	}

	/**
	 * phpmailer_init (last): when PHPMailer would still call the disabled mail() function, hand the
	 * message to the server's own mail transfer agent over SMTP on localhost instead (cPanel hosts
	 * run one). If that fails, PHPMailer throws its own exception, which wp_mail() catches and turns
	 * into a plain "not sent" — never a fatal error.
	 *
	 * @param mixed $phpmailer PHPMailer instance.
	 */
	public static function fallback( $phpmailer ): void {
		if ( ! is_object( $phpmailer ) || 'mail' !== $phpmailer->Mailer || function_exists( 'mail' ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host        = 'localhost';
		$phpmailer->Port        = 25;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
		$phpmailer->Timeout     = 8;
		self::log_once( 'Gadgetim: PHP mail() is disabled on this server; e-mail is handed to localhost:25. Set up SMTP under Gadgetim > Settings > E-mail for reliable delivery.' );
	}

	/**
	 * pre_wp_mail (999): last line of defence — mail() disabled, SMTP off and no socket functions
	 * either, so not even the localhost fallback could run: skip sending (wp_mail() returns false).
	 *
	 * @param null|bool $short Short-circuit value from earlier filters.
	 * @param array     $atts  wp_mail() arguments.
	 * @return null|bool
	 */
	public static function guard( $short, $atts ) {
		if ( null !== $short || function_exists( 'mail' ) || self::enabled() || function_exists( 'stream_socket_client' ) ) {
			return $short;
		}
		static $logged = false;
		if ( ! $logged ) {
			$logged = true;
			error_log( 'Gadgetim: PHP mail() is disabled on this server and SMTP is not configured; e-mail "' . sanitize_text_field( (string) ( $atts['subject'] ?? '' ) ) . '" was not sent. Set up SMTP under Gadgetim > Settings > E-mail.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return false;
	}

	/**
	 * phpmailer_init (5): send through the configured SMTP server.
	 *
	 * @param mixed $phpmailer PHPMailer instance.
	 */
	public static function configure( $phpmailer ): void {
		if ( ! self::enabled() || ! is_object( $phpmailer ) ) {
			return;
		}
		$secure = (string) Settings::get( 'mail.secure', 'ssl' );
		$user   = trim( (string) Settings::get( 'mail.username', '' ) );
		$phpmailer->isSMTP();
		$phpmailer->Host        = trim( (string) Settings::get( 'mail.host', '' ) );
		$phpmailer->Port        = (int) Settings::get( 'mail.port', 465 );
		$phpmailer->SMTPSecure  = in_array( $secure, [ 'ssl', 'tls' ], true ) ? $secure : '';
		$phpmailer->SMTPAutoTLS = 'tls' === $secure;
		$phpmailer->SMTPAuth    = '' !== $user;
		$phpmailer->Username    = $user;
		$phpmailer->Password    = (string) Settings::get( 'mail.password', '' );
		$phpmailer->Timeout     = 20;
		$from = sanitize_email( (string) Settings::get( 'mail.from_email', '' ) );
		if ( '' === $from && is_email( $user ) ) {
			$from = $user; // Most servers refuse a sender other than the authenticated mailbox.
		}
		if ( '' !== $from ) {
			$name = trim( (string) Settings::get( 'mail.from_name', '' ) );
			$phpmailer->setFrom( $from, '' !== $name ? $name : (string) $phpmailer->FromName, false );
			$phpmailer->Sender = $from;
		}
	}

	/**
	 * init (5): the "E-mail" settings tab.
	 */
	public static function register_settings_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		$mail_ok = function_exists( 'mail' );
		Schema::add_tab(
			self::TAB,
			__( 'E-mail', 'gadgetim-core' ),
			Capabilities::ADMIN,
			[
				'smtp' => [
					'label'       => __( 'Sending through SMTP', 'gadgetim-core' ),
					'description' => $mail_ok
						? __( 'Order e-mails, password resets and site messages are sent through this mailbox. Use the details of an e-mail account created in cPanel (for example info@gadgetim.ir).', 'gadgetim-core' )
						: __( 'PHP mail() is disabled on this server, so no e-mail leaves the site until SMTP is set up here. Use the details of an e-mail account created in cPanel (for example info@gadgetim.ir).', 'gadgetim-core' ),
					'fields'      => [
						'mail.enabled'    => [
							'type'    => 'toggle',
							'label'   => __( 'Send e-mail through SMTP', 'gadgetim-core' ),
							'default' => false,
						],
						'mail.host'       => [
							'type'        => 'text',
							'label'       => __( 'SMTP server', 'gadgetim-core' ),
							'placeholder' => 'mail.gadgetim.ir',
							'attrs'       => [ 'dir' => 'ltr' ],
							'sanitize'    => [ self::class, 'sanitize_host' ],
						],
						'mail.port'       => [
							'type'    => 'number',
							'label'   => __( 'Port', 'gadgetim-core' ),
							'default' => 465,
							'min'     => 1,
							'max'     => 65535,
						],
						'mail.secure'     => [
							'type'    => 'select',
							'label'   => __( 'Encryption', 'gadgetim-core' ),
							'default' => 'ssl',
							'options' => [
								'ssl'  => 'SSL (465)',
								'tls'  => 'TLS (587)',
								'none' => __( 'None (25)', 'gadgetim-core' ),
							],
						],
						'mail.username'   => [
							'type'        => 'text',
							'label'       => __( 'Username', 'gadgetim-core' ),
							'description' => __( 'Usually the full e-mail address.', 'gadgetim-core' ),
							'placeholder' => 'info@gadgetim.ir',
							'attrs'       => [ 'dir' => 'ltr', 'autocomplete' => 'off' ],
						],
						'mail.password'   => [
							'type'     => 'text',
							'label'    => __( 'Password', 'gadgetim-core' ),
							'render'   => [ self::class, 'render_password' ],
							'sanitize' => [ self::class, 'sanitize_password' ],
						],
						'mail.from_email' => [
							'type'        => 'email',
							'label'       => __( 'Sender address', 'gadgetim-core' ),
							'description' => __( 'Leave empty to use the username. Most servers only accept the address of the mailbox you log in with.', 'gadgetim-core' ),
							'placeholder' => 'info@gadgetim.ir',
						],
						'mail.from_name'  => [
							'type'        => 'text',
							'label'       => __( 'Sender name', 'gadgetim-core' ),
							'placeholder' => 'گجتیم',
						],
						'mail.test'       => [
							'type'   => 'text',
							'label'  => __( 'Test', 'gadgetim-core' ),
							'render' => [ self::class, 'render_test' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Password field: never echoes the stored password.
	 *
	 * @param string $path  Settings path.
	 * @param array  $field Field definition.
	 * @param mixed  $value Stored value.
	 */
	public static function render_password( string $path, array $field, $value ): void {
		$name = \Gadgetim\Core\Settings\Admin\Fields::name( $path );
		$id   = \Gadgetim\Core\Settings\Admin\Fields::id( $path );
		$set  = is_string( $value ) && '' !== $value;
		printf(
			'<input type="password" name="%1$s" id="%2$s" value="" class="regular-text" dir="ltr" autocomplete="new-password" placeholder="%3$s">',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $set ? __( 'Saved — leave empty to keep it', 'gadgetim-core' ) : '' )
		);
	}

	/**
	 * Empty submission keeps the stored password.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_password( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' !== $value ? $value : (string) Settings::get( 'mail.password', '' );
	}

	/**
	 * Host names only.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_host( $value ): string {
		$value = strtolower( trim( is_string( $value ) ? $value : '' ) );
		$value = (string) preg_replace( '#^[a-z]+://#', '', $value );
		return (string) preg_replace( '/[^a-z0-9.\-]/', '', $value );
	}

	/**
	 * "Send a test e-mail" link (outside the settings form flow: a nonce-protected admin-post GET).
	 */
	public static function render_test(): void {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TEST_ACTION ), self::TEST_ACTION );
		/* translators: %s: admin e-mail address */
		echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Send a test e-mail', 'gadgetim-core' ) . '</a> ';
		echo '<span class="description">' . esc_html( sprintf( __( 'Save the settings first. The message goes to %s.', 'gadgetim-core' ), (string) get_option( 'admin_email' ) ) ) . '</span>';
	}

	/**
	 * admin-post: send a test message to the admin address and report the result.
	 */
	public static function send_test(): void {
		if ( ! current_user_can( Capabilities::ADMIN ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'gadgetim-core' ), 403 );
		}
		check_admin_referer( self::TEST_ACTION );
		$error = '';
		$catch = static function ( $wp_error ) use ( &$error ): void {
			$error = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : '';
		};
		add_action( 'wp_mail_failed', $catch );
		$sent = wp_mail( (string) get_option( 'admin_email' ), __( 'Gadgetim test e-mail', 'gadgetim-core' ), __( 'If you can read this, the site can send e-mail.', 'gadgetim-core' ) );
		remove_action( 'wp_mail_failed', $catch );
		if ( ! $sent && '' === $error ) {
			$error = function_exists( 'mail' ) || self::enabled() ? __( 'The message could not be sent.', 'gadgetim-core' ) : __( 'PHP mail() is disabled on this server and SMTP is not switched on.', 'gadgetim-core' );
		}
		set_transient( self::TEST_RESULT . '_' . get_current_user_id(), [ $sent, $error ], 120 );
		wp_safe_redirect( SettingsPage::url( self::TAB ) );
		exit;
	}

	/**
	 * Result of the last test message.
	 */
	public static function test_notice(): void {
		$key    = self::TEST_RESULT . '_' . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );
		[ $sent, $error ] = $result;
		if ( $sent ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Test e-mail sent to %s. Check the inbox (and the spam folder).', 'gadgetim-core' ), (string) get_option( 'admin_email' ) ) ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The test e-mail was not sent:', 'gadgetim-core' ) . ' ' . esc_html( (string) $error ) . '</p></div>';
		}
	}

	/**
	 * Writes a server-log line once per request.
	 */
	private static function log_once( string $message ): void {
		static $done = [];
		if ( isset( $done[ $message ] ) ) {
			return;
		}
		$done[ $message ] = true;
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
