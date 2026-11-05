<?php
/**
 * "May this output filter translate right now?" (architecture §3.3, §3.8).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Support\Context as SupportContext;

/**
 * Combines the Support\Context render window and suspension counter with the e-mail context
 * owned by I18n\Emails. Every output filter in this module asks may_translate() first, so
 * nothing changes for Persian requests, in wp-admin, WP-CLI, cron or REST writes.
 */
final class Context {

	/**
	 * True when the current language is not Persian and translations are not suspended.
	 * The explicit suspension counter always wins; an active e-mail context overrides the
	 * implicit admin/cron/CLI suspension (deferred and resent e-mails run there).
	 */
	public static function may_translate(): bool {
		if ( Languages::DEFAULT === Languages::current() ) {
			return false;
		}
		if ( SupportContext::$suspend_translations > 0 ) {
			return false;
		}
		if ( Emails::active() ) {
			return true;
		}
		return ! SupportContext::translations_suspended();
	}

	/**
	 * Storefront render window (template_redirect -> shutdown on a front-end request).
	 */
	public static function is_storefront(): bool {
		return SupportContext::is_storefront_render();
	}

	/**
	 * Output that a shopper will read: the storefront render window, a customer e-mail, or a
	 * read-only storefront API request (GET /wc/store/*, /gadgetim/v1/* — the cart drawer reads
	 * the Store API with ?lang=). Admin e-mails, admin screens, wc/v3, wp/v2, REST writes and
	 * reports are not customer output.
	 */
	public static function is_customer_output(): bool {
		if ( Emails::in_customer_email() || SupportContext::is_storefront_render() ) {
			return true;
		}
		return SupportContext::is_rest_request() && SupportContext::is_read_method() && Request::is_readonly_api();
	}

	/**
	 * Current language code (shorthand for templates).
	 */
	public static function lang(): string {
		return Languages::current();
	}
}
