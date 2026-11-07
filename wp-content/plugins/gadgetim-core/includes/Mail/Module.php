<?php
/**
 * Mail module: outgoing e-mail through SMTP (Gadgetim > Settings > E-mail) and a guard that keeps
 * pages alive on hosts where PHP mail() is disabled.
 *
 * Without the guard, PHPMailer calls the disabled mail() function, PHP throws an Error that
 * wp_mail() does not catch, and the request dies with "critical error" — after a payment return,
 * an order note for the customer or any admin action that sends a message.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Mail;

/**
 * Module entry point (booted by Plugin::boot()). Registers hooks only.
 */
final class Module {

	/**
	 * Registers the mail hooks.
	 */
	public static function register(): void {
		Smtp::register();
	}
}
