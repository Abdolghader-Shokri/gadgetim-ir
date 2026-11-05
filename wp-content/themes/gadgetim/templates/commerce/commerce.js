/**
 * Gadgetim Aurora — commerce.js: cart, checkout, account and order-tracking enhancements.
 * Enqueued by inc/commerce.php as the script module "@gadgetim/commerce" on commerce screens only
 * (after "@gadgetim/app", whose ctx it reuses through window.gadgetim for toasts and badges).
 * WooCommerce's own jQuery scripts keep doing the cart/checkout work (AJAX updates, coupons,
 * fragments, validation); this module never uses jQuery — it only clicks WooCommerce's controls and
 * watches the DOM it replaces (MutationObserver).
 *
 * Cart:     quantity steppers (+/− with a debounced update through WooCommerce's hidden «update
 *           cart» button; − at one removes the line), language digits over the number input,
 *           remove → toast with Undo (the hidden WooCommerce notice keeps the real undo link),
 *           coupon disclosure, phone bottom bar while the totals button is off screen, focus kept
 *           on the stepper after an update, header badge refresh, cart refresh after an add from
 *           the suggestions rail.
 * Checkout: steps indicator follows the payment section, order items collapsible on phones,
 *           pay bar while the payment section is off screen, smooth jump to payment.
 * Account:  sign-in / sign-up tabs (#register opens sign-up), the current menu chip scrolled into view.
 * Received: copy the order number.
 * Without this module every screen works as plain WooCommerce markup (nothing is hidden by CSS
 * until html.cm-js is set here).
 */

const doc = document;
const root = doc.documentElement;
const $ = (sel, el = doc) => el.querySelector(sel);
const $$ = (sel, el = doc) => Array.from(el.querySelectorAll(sel));

const shell = $('[data-commerce]');
const view = shell ? shell.dataset.commerce : '';

let cfg = {};
try {
	cfg = JSON.parse(($('#gadgetim-config') || {}).textContent || '{}');
} catch {
	cfg = {};
}
const S = cfg.strings || {};
const t = (key, ...args) => {
	let s = S[key] ?? key;
	let i = 0;
	s = s.replace(/%(\d+)\$s/g, (_, n) => args[Number(n) - 1] ?? '');
	return s.replace(/%s/g, () => args[i++] ?? '');
};
const SETS = { fa: '۰۱۲۳۴۵۶۷۸۹', arab: '٠١٢٣٤٥٦٧٨٩' };
const digits = (s) => {
	const set = SETS[cfg.digits];
	return set ? String(s).replace(/\d/g, (d) => set[d]) : String(s);
};
const ascii = (s) => String(s ?? '')
	.replace(/[۰-۹]/g, (d) => String(SETS.fa.indexOf(d)))
	.replace(/[٠-٩]/g, (d) => String(SETS.arab.indexOf(d)));
const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches || root.classList.contains('lite');
const phone = matchMedia('(max-width: 767.98px)');
const ctx = () => window.gadgetim || null;
const toast = (opts) => {
	const c = ctx();
	return c && typeof c.toast === 'function' ? c.toast(opts) : Promise.resolve(null);
};
const say = (msg) => {
	const el = $('[data-cm-live]');
	if (!el || !msg) return;
	el.textContent = '';
	setTimeout(() => {
		el.textContent = msg;
	}, 80);
};
const badges = () => {
	const c = ctx();
	if (c && c.badges && typeof c.badges.update === 'function') c.badges.update();
};

/* ------------------------------------------------------------------ */
/* Cart                                                               */
/* ------------------------------------------------------------------ */

let updateTimer = 0;
let pendingFocus = null;
let synthetic = false;
let couponOpen = false;
let refreshing = false;

function syncStepper(box, value) {
	const input = $('input.qty', box);
	if (!input) return;
	const max = input.max !== '' && Number(input.max) > 0 ? Number(input.max) : Infinity;
	const out = $('.cqty__v', box);
	if (out) out.textContent = digits(value);
	const row = box.closest('tr');
	if (row) row.classList.toggle('ci--one', value <= 1);
	const inc = $('[data-cqty-step="1"]', box);
	if (inc) inc.disabled = value >= max;
}

function queueUpdate(key, dir) {
	clearTimeout(updateTimer);
	pendingFocus = key ? { key, dir } : null;
	updateTimer = setTimeout(() => {
		const btn = $('.woocommerce-cart-form [name="update_cart"]');
		if (!btn) return;
		say(t('cartUpdating'));
		btn.disabled = false;
		btn.click();
	}, 650);
}

function step(btn) {
	const box = btn.closest('[data-cqty]');
	const input = box ? $('input.qty', box) : null;
	if (!input || input.disabled || input.readOnly) return;
	const dir = Number(btn.dataset.cqtyStep) || 0;
	const inc = Number(input.step) > 0 ? Number(input.step) : 1;
	const min = input.min !== '' ? Number(input.min) : 0;
	const max = input.max !== '' && Number(input.max) > 0 ? Number(input.max) : Infinity;
	const cur = Number(ascii(input.value)) || 0;
	let next = cur + dir * inc;
	if (dir < 0 && next < 1) {
		// Down from one: remove the line (WooCommerce's remove link → undo toast).
		const row = box.closest('tr');
		const rm = row ? $('.product-remove > a', row) : null;
		if (rm) {
			rm.click();
			return;
		}
		next = Math.max(min, 0);
	}
	next = Math.min(max, Math.max(min, next));
	if (next === cur) return;
	input.value = String(next);
	syncStepper(box, next);
	synthetic = true;
	input.dispatchEvent(new Event('input', { bubbles: true }));
	input.dispatchEvent(new Event('change', { bubbles: true }));
	synthetic = false;
	say(t('qtyNow', digits(next)));
	queueUpdate(box.dataset.cqty, dir);
}

function setCoupon(open, focus = false) {
	const wrap = $('[data-ccoupon]');
	if (!wrap) return;
	couponOpen = open;
	wrap.classList.toggle('is-open', open);
	const btn = $('[data-ccoupon-toggle]', wrap);
	if (btn) btn.setAttribute('aria-expanded', String(open));
	if (open && focus) {
		const input = $('#coupon_code', wrap);
		if (input) input.focus();
	}
}

function showUndo(node) {
	if (!node || node.dataset.gtmUndoSeen) return;
	node.dataset.gtmUndoSeen = '1';
	const link = $('a.restore-item', node);
	const box = $('.ntc__x', node) || node;
	const text = box.textContent.replace(link ? link.textContent : '', '').replace(/\s+/g, ' ').trim();
	toast({
		title: t('removed'),
		text,
		icon: 'trash',
		type: 'info',
		duration: 7000,
		action: link ? { label: t('undo'), href: link.href } : null,
	}).then((el) => {
		if (!el) return;
		node.classList.add('is-toasted');
		const btn = $('.toast__btn', el);
		if (!btn || !link) return;
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			if (doc.contains(link)) link.click();
			else location.href = link.href;
		});
	});
	badges();
}

async function refreshCart() {
	if (refreshing) return;
	refreshing = true;
	try {
		const res = await fetch(location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
		const html = await res.text();
		const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.cm__body .woocommerce');
		const current = $('.cm__body .woocommerce');
		if (fresh && current) {
			current.replaceWith(fresh);
			const c = ctx();
			if (c && c.load) {
				c.load('carousel').then((m) => m && m.mount && m.mount(fresh));
				c.load('motion').then((m) => m && m.observe && m.observe(fresh));
			}
		}
	} catch {
		/* the cart page still works; the next navigation shows the new line */
	} finally {
		refreshing = false;
	}
}

function enhanceCart() {
	// Coupon: open when it has an error or a value (WooCommerce re-renders the form after updates).
	const wrap = $('[data-ccoupon]');
	if (wrap) {
		const input = $('#coupon_code', wrap);
		setCoupon(couponOpen || Boolean($('.coupon-error-notice', wrap)) || Boolean(input && input.value));
	}
	$$('[data-cqty]').forEach((box) => {
		const input = $('input.qty', box);
		if (input) syncStepper(box, Number(ascii(input.value)) || 0);
	});
	if (pendingFocus) {
		const target = $(`[data-cqty="${CSS.escape(pendingFocus.key)}"] [data-cqty-step="${pendingFocus.dir > 0 ? '1' : '-1'}"]`);
		if (target && !$('.blockUI', target.closest('form') || doc)) {
			target.focus({ preventScroll: true });
			pendingFocus = null;
			say(t('cartUpdated'));
		}
	}
	$$('[data-gtm-undo]').forEach(showUndo);
	watchBar('.wc-proceed-to-checkout');
	badges();
}

function cartClicks(e) {
	const el = e.target instanceof Element ? e.target : null;
	if (!el) return;
	const stepBtn = el.closest('[data-cqty-step]');
	if (stepBtn) {
		e.preventDefault();
		step(stepBtn);
		return;
	}
	const toggle = el.closest('[data-ccoupon-toggle]');
	if (toggle) {
		e.preventDefault();
		setCoupon(!couponOpen, !couponOpen);
		return;
	}
	const rm = el.closest('.woocommerce-cart-form .product-remove > a');
	if (rm) {
		const row = rm.closest('tr');
		if (row) row.classList.add('is-removing');
	}
}

function cartInit() {
	doc.addEventListener('click', cartClicks);
	// Typed quantities: update after the field is left (the steppers queue their own update).
	doc.addEventListener('change', (e) => {
		const input = e.target instanceof HTMLInputElement && e.target.matches('.woocommerce-cart-form input.qty') ? e.target : null;
		if (!input || synthetic) return;
		const box = input.closest('[data-cqty]');
		if (!box) return;
		syncStepper(box, Number(ascii(input.value)) || 0);
		queueUpdate(box.dataset.cqty, 0);
	});
	// A product added from the suggestions rail: show it in the cart without a reload.
	doc.addEventListener('gtm:cart', () => refreshCart());
	enhanceCart();
}

/* ------------------------------------------------------------------ */
/* Sticky bars (cart totals button / checkout payment off screen)     */
/* ------------------------------------------------------------------ */

let barIO = null;
let barTarget = null;
let barHidden = false;

/** Applies the last known state to the bar (WooCommerce re-renders it with the totals). */
function applyBar() {
	const bar = $('[data-cbar]');
	if (bar) bar.classList.toggle('is-off', barHidden);
	root.classList.toggle('has-cbar', Boolean(bar) && !barHidden && phone.matches);
}

function watchBar(selector) {
	const target = $(selector);
	if (!target || !('IntersectionObserver' in window)) {
		barHidden = false;
		applyBar();
		return;
	}
	if (target !== barTarget) {
		if (barIO) barIO.disconnect();
		barTarget = target;
		barIO = new IntersectionObserver((entries) => {
			barHidden = entries.some((en) => en.isIntersecting);
			applyBar();
		}, { rootMargin: '0px 0px -40px 0px' });
		barIO.observe(target);
	}
	applyBar();
}

/* ------------------------------------------------------------------ */
/* Checkout                                                           */
/* ------------------------------------------------------------------ */

let itemsOpen = true;

function setItems(open) {
	const aside = $('[data-co-aside]');
	const btn = $('[data-co-items]');
	if (!aside || !btn) return;
	itemsOpen = open;
	aside.classList.toggle('is-collapsed', !open);
	btn.setAttribute('aria-expanded', String(open));
	const label = $('[data-co-items-label]', btn);
	if (label) label.textContent = open ? t('hideItems') : t('showItems');
}

function setPayStep(onPay) {
	const steps = $('[data-steps]');
	if (!steps) return;
	const li = (key) => $(`.csteps__i[data-step="${key}"]`, steps);
	const details = li('details');
	const pay = li('payment');
	if (!details || !pay) return;
	const icon = (item, name) => {
		const use = $('.csteps__dot use', item);
		if (use) use.setAttribute('href', `#i-${name}`);
	};
	details.classList.toggle('is-done', onPay);
	details.classList.toggle('is-current', !onPay);
	pay.classList.toggle('is-current', onPay);
	pay.classList.toggle('is-next', !onPay);
	icon(details, onPay ? 'check' : 'pin');
	if (onPay) {
		pay.setAttribute('aria-current', 'step');
		details.removeAttribute('aria-current');
	} else {
		details.setAttribute('aria-current', 'step');
		pay.removeAttribute('aria-current');
	}
	steps.style.setProperty('--p', onPay ? '1' : '0.5');
}

function checkoutInit() {
	const btn = $('[data-co-items]');
	if (btn) {
		btn.addEventListener('click', () => setItems(!itemsOpen));
		if (phone.matches) setItems(false);
		phone.addEventListener('change', (e) => setItems(e.matches ? itemsOpen : true));
	}
	const pay = $('#co-pay');
	if (pay && 'IntersectionObserver' in window) {
		new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				if (en.isIntersecting) setPayStep(true);
				else if (en.boundingClientRect.top > 0) setPayStep(false);
			});
		}, { threshold: 0.18 }).observe(pay);
	}
	pay && pay.addEventListener('focusin', () => setPayStep(true));
	doc.addEventListener('click', (e) => {
		const go = e.target instanceof Element ? e.target.closest('[data-cbar-go]') : null;
		if (!go || !pay) return;
		e.preventDefault();
		pay.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
		const first = $('input[name="payment_method"]:checked, input[name="payment_method"], #place_order', pay);
		if (first) setTimeout(() => first.focus({ preventScroll: true }), reduced() ? 0 : 450);
	});
	watchBar('#co-pay');
}

/* ------------------------------------------------------------------ */
/* Account, received                                                  */
/* ------------------------------------------------------------------ */

function authInit() {
	doc.addEventListener('gtm:tab', (e) => {
		const list = e.detail && e.detail.list;
		if (!list || !list.classList.contains('auth__tabs')) return;
		$$('.auth__panel').forEach((p) => p.classList.remove('is-inactive'));
		const tab = e.detail.tab;
		if (tab && tab.id && history.replaceState) history.replaceState(null, '', tab.id === 'auth-tab-register' ? '#register' : location.pathname + location.search);
	});
	if (location.hash === '#register') {
		const tab = $('#auth-tab-register');
		if (tab && tab.getAttribute('aria-selected') !== 'true') tab.click();
	}
}

function accountInit() {
	const current = $('.myac-nav [aria-current="page"]');
	if (current && phone.matches) current.scrollIntoView({ block: 'nearest', inline: 'center' });
}

function receivedInit() {
	doc.addEventListener('click', async (e) => {
		const btn = e.target instanceof Element ? e.target.closest('[data-copy]') : null;
		if (!btn) return;
		const value = btn.dataset.copy || '';
		try {
			await navigator.clipboard.writeText(value);
		} catch {
			const area = doc.createElement('textarea');
			area.value = value;
			area.setAttribute('readonly', '');
			area.style.cssText = 'position:fixed;opacity:0';
			doc.body.appendChild(area);
			area.select();
			try { doc.execCommand('copy'); } catch { /* nothing more to try */ }
			area.remove();
		}
		btn.classList.add('is-done');
		setTimeout(() => btn.classList.remove('is-done'), 1600);
		toast({ title: t('copied'), icon: 'check', type: 'success', duration: 2200 });
	});
}

/* ------------------------------------------------------------------ */
/* Boot: watch WooCommerce's replacements                             */
/* ------------------------------------------------------------------ */

function enhance() {
	if (view === 'cart') enhanceCart();
	else if (view === 'checkout') watchBar('#co-pay');
	$$('[data-gtm-undo]').forEach(showUndo);
}

function boot() {
	if (!shell) return;
	root.classList.add('cm-js');
	if (view === 'cart') cartInit();
	if (view === 'checkout') checkoutInit();
	if (view === 'auth') authInit();
	if (view === 'account') accountInit();
	if (view === 'received') receivedInit();

	let queued = false;
	new MutationObserver((records) => {
		if (queued) return;
		const relevant = records.some((r) => Array.from(r.addedNodes).some((n) => n.nodeType === 1));
		if (!relevant) return;
		queued = true;
		requestAnimationFrame(() => {
			queued = false;
			enhance();
		});
	}).observe(shell, { childList: true, subtree: true });
}

if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();
