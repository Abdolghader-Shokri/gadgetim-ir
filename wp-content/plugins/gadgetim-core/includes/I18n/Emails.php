<?php
/**
 * Order-based e-mail language and locale (architecture §3.8).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use WC_Email;
use WC_Order;

/**
 * Context is set FROM THE ORDER, never from the action name: woocommerce_email_recipient_{id}
 * fires after $email->object is set and before subject, heading and content are built, for
 * normal sends, admin resends and deferred (Action Scheduler) sends alike. Customer e-mails
 * switch to the order language's locale; admin e-mails are forced to fa_IR regardless of the
 * request locale. WooCommerce's own locale switching is disabled; we restore ourselves.
 */
final class Emails {

	/** @var array<int,array{prev:string,switched:bool,customer:bool,lang:string}> Keyed by spl_object_id( $email ). */
	private static array $entered = [];

	private static int $resend_depth = 0;

	/**
	 * E-mails whose trigger() has started (setup_locale ran), keyed by spl_object_id( $email ).
	 * get_recipient() is also called outside any send (WC → Settings → Emails, the settings REST
	 * schema), so the context is entered only for e-mails in this list.
	 *
	 * @var array<int,true>
	 */
	private static array $pending = [];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_allow_switching_email_locale', [ self::class, 'on_setup_locale' ], 20, 2 );
		add_filter( 'woocommerce_allow_restoring_email_locale', [ self::class, 'on_restore' ], 20, 2 );
		add_action( 'woocommerce_email', [ self::class, 'hook_recipients' ], 10, 1 );
		add_action( 'woocommerce_before_resend_order_emails', [ self::class, 'on_resend' ], 1, 2 );
		add_action( 'woocommerce_after_resend_order_email', [ self::class, 'on_after_resend' ], 999, 2 );
	}

	/**
	 * woocommerce_allow_switching_email_locale (fires in WC_Email::setup_locale() at the start of
	 * every trigger()): marks the send as in progress and disables WooCommerce's own switch.
	 *
	 * @param mixed $allow Default.
	 * @param mixed $email WC_Email.
	 */
	public static function on_setup_locale( $allow, $email = null ): bool {
		if ( $email instanceof WC_Email ) {
			self::$pending[ spl_object_id( $email ) ] = true;
		}
		return false;
	}

	/**
	 * Whether any e-mail is being rendered in our context.
	 */
	public static function active(): bool {
		return ! empty( self::$entered );
	}

	/**
	 * Whether a CUSTOMER e-mail is being rendered (admin e-mails are Persian, never translated).
	 */
	public static function in_customer_email(): bool {
		if ( ! self::$entered ) {
			return false;
		}
		$current = end( self::$entered );
		return is_array( $current ) && ! empty( $current['customer'] );
	}

	/**
	 * woocommerce_email: one recipient filter per registered e-mail id.
	 *
	 * @param mixed $mailer WC_Emails.
	 */
	public static function hook_recipients( $mailer ): void {
		if ( ! is_object( $mailer ) || ! method_exists( $mailer, 'get_emails' ) ) {
			return;
		}
		foreach ( (array) $mailer->get_emails() as $email ) {
			if ( $email instanceof WC_Email && '' !== (string) $email->id ) {
				add_filter( 'woocommerce_email_recipient_' . $email->id, [ self::class, 'on_recipient' ], 1, 3 );
			}
		}
	}

	/**
	 * woocommerce_email_recipient_{id}: enters the context for this e-mail.
	 *
	 * @param mixed $recipient Recipient.
	 * @param mixed $object    Order, refund, user... the e-mail is about.
	 * @param mixed $email     WC_Email.
	 */
	public static function on_recipient( $recipient, $object = null, $email = null ) {
		if ( $email instanceof WC_Email && isset( self::$pending[ spl_object_id( $email ) ] ) ) {
			$order = self::order_from( $object );
			$lang  = $order instanceof WC_Order ? Orders::lang( $order ) : Languages::current();
			self::enter( $lang, $email );
		}
		return $recipient;
	}

	/**
	 * Enters the language + locale context for an e-mail (idempotent per e-mail instance).
	 *
	 * @param string   $lang  Order language (customer e-mails); admin e-mails always get Persian.
	 * @param WC_Email $email E-mail.
	 */
	public static function enter( string $lang, WC_Email $email ): void {
		$key = spl_object_id( $email );
		if ( isset( self::$entered[ $key ] ) ) {
			return;
		}
		$customer = (bool) $email->is_customer_email();
		$target   = $customer && Languages::exists( $lang ) ? $lang : Languages::DEFAULT;
		$prev     = Languages::current();
		$switched = self::switch_locale( Languages::locale( $target ) );

		Languages::force( $target );
		self::$entered[ $key ] = [
			'prev'     => $prev,
			'switched' => $switched,
			'customer' => $customer,
			'lang'     => $target,
		];
	}

	/**
	 * Leaves the context of an e-mail (the innermost one when $email is null).
	 */
	public static function leave( ?WC_Email $email = null ): void {
		if ( ! self::$entered ) {
			return;
		}
		$key = null !== $email ? spl_object_id( $email ) : array_key_last( self::$entered );
		if ( ! isset( self::$entered[ $key ] ) ) {
			return;
		}
		$state = self::$entered[ $key ];
		unset( self::$entered[ $key ] );

		Languages::restore();
		if ( $state['switched'] && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
			self::reload_woocommerce_textdomain();
		}
		if ( ! self::$entered && self::$resend_depth > 0 ) {
			self::$resend_depth--;
			Languages::restore();
		}
	}

	/**
	 * woocommerce_allow_restoring_email_locale (fires for every e-mail at the end of trigger()):
	 * we restore ourselves and tell WooCommerce not to.
	 *
	 * @param mixed $allow Default.
	 * @param mixed $email WC_Email.
	 */
	public static function on_restore( $allow, $email = null ) {
		if ( $email instanceof WC_Email ) {
			unset( self::$pending[ spl_object_id( $email ) ] );
			self::leave( $email );
		}
		return false;
	}

	/**
	 * woocommerce_after_resend_order_email: releases the resend language when no e-mail entered
	 * (disabled e-mail, no recipient), so it cannot leak into the rest of the admin request.
	 *
	 * @param mixed $order Order.
	 * @param mixed $type  E-mail type.
	 */
	public static function on_after_resend( $order = null, $type = '' ): void {
		if ( self::$resend_depth > 0 && ! self::$entered ) {
			self::$resend_depth--;
			Languages::restore();
		}
	}

	/**
	 * woocommerce_before_resend_order_emails: pre-sets the order language so code hooking
	 * before the recipient filter already sees it. Released when the e-mail leaves.
	 *
	 * @param mixed $order Order.
	 * @param mixed $type  'customer_invoice' | 'new_order'.
	 */
	public static function on_resend( $order, $type = '' ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		Languages::force( 'new_order' === $type ? Languages::DEFAULT : Orders::lang( $order ) );
		self::$resend_depth++;
	}

	/**
	 * The order an e-mail object refers to (orders, refunds), or null.
	 */
	private static function order_from( $object ): ?WC_Order {
		if ( $object instanceof WC_Order ) {
			return $object;
		}
		if ( is_object( $object ) && method_exists( $object, 'get_parent_id' ) && function_exists( 'wc_get_order' ) && is_a( $object, 'WC_Order_Refund' ) ) {
			$parent = wc_get_order( (int) $object->get_parent_id() );
			return $parent instanceof WC_Order ? $parent : null;
		}
		return null;
	}

	/**
	 * switch_to_locale() when the locale differs; WooCommerce's textdomain is reloaded like
	 * wc_switch_to_site_locale() does. Returns whether a switch happened.
	 */
	private static function switch_locale( string $locale ): bool {
		if ( ! function_exists( 'switch_to_locale' ) || determine_locale() === $locale ) {
			return false;
		}
		$switched = (bool) switch_to_locale( $locale );
		if ( $switched ) {
			self::reload_woocommerce_textdomain();
		}
		return $switched;
	}

	private static function reload_woocommerce_textdomain(): void {
		if ( function_exists( 'WC' ) && method_exists( WC(), 'load_plugin_textdomain' ) ) {
			WC()->load_plugin_textdomain();
		}
	}

	/**
	 * Forgets every e-mail context (tests).
	 */
	public static function reset(): void {
		self::$entered      = [];
		self::$pending      = [];
		self::$resend_depth = 0;
	}
}
