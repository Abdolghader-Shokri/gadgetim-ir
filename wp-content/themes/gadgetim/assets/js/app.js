/**
 * Gadgetim Aurora — app.js (the only enqueued script module).
 *
 * Boots the shared behaviour of every page and loads the other modules on demand through the
 * import map ("@gadgetim/<name>", registered by inc/assets.php only when the file exists):
 *
 *   motion     always, on idle            reveals, count-up, ripple, card glow, parallax
 *   fx         always, on idle            tilt, magnets, confetti, scroll fallbacks, pausing (fx.css)
 *   carousel   [data-carousel], .rail[id]  fade/slide carousels and scroll rails
 *   countdown  [data-countdown]           real-deadline timers
 *   drawer     first [data-open] click    <dialog> drawers and modals
 *   toast      first toast                toast notifications
 *   cart       [data-add-to-cart] click, cart drawer open
 *   search     focus / pointer on #q      live suggestions
 *   filters    data-page="archive"        (archive builder)
 *   gallery    data-page="product"        (product builder)
 *
 * Every module exports init(ctx) (called once) and its own API. ctx is the object built below:
 * { cfg, t, $, $$, esc, fmt, digits, reduced, mq, load, emit, on, cookie, store, badges, bnav, toast }.
 * Here (no module needed): header tuck/shadow, promo ticker, back-to-top, cart/wishlist badges
 * (cookie gtm_cart_count + localStorage), wishlist hearts, mega menu, accordions, tabs,
 * bottom-nav indicator, search shortcut, newsletter submit.
 */

const doc = document;
const root = doc.documentElement;

const $ = (sel, el = doc) => el.querySelector(sel);
const $$ = (sel, el = doc) => Array.from(el.querySelectorAll(sel));

let cfg = {};
try {
	cfg = JSON.parse(($('#gadgetim-config') || {}).textContent || '{}');
} catch {
	cfg = {};
}
cfg.strings = cfg.strings || {};

const mq = {
	reduce: matchMedia('(prefers-reduced-motion: reduce)'),
	phone: matchMedia('(max-width: 767.98px)'),
	desk: matchMedia('(min-width: 1000px)'),
	hover: matchMedia('(hover: hover) and (pointer: fine)'),
};
const reduced = () => mq.reduce.matches || root.classList.contains('lite');

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ESC[c]);
const nf = new Intl.NumberFormat(cfg.locale || 'fa-IR', { maximumFractionDigits: 0 });
const fmt = (n) => nf.format(Number(n) || 0);
const DIGITS = { fa: '۰۱۲۳۴۵۶۷۸۹', arab: '٠١٢٣٤٥٦٧٨٩' };
const digits = (s) => {
	const set = DIGITS[cfg.digits];
	return set ? String(s).replace(/\d/g, (d) => set[d]) : String(s);
};
/** Translated string from the config with sprintf-style %s / %1$s placeholders. */
const t = (key, ...args) => {
	let s = cfg.strings[key] ?? key;
	let i = 0;
	s = s.replace(/%(\d+)\$s/g, (_, n) => args[Number(n) - 1] ?? '');
	return s.replace(/%s/g, () => args[i++] ?? '');
};

const cookie = (name) => {
	const m = doc.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&')}=([^;]*)`));
	return m ? decodeURIComponent(m[1]) : null;
};
const store = {
	get(key, fallback) {
		try {
			const v = localStorage.getItem(key);
			return v ? JSON.parse(v) : fallback;
		} catch {
			return fallback;
		}
	},
	set(key, value) {
		try {
			localStorage.setItem(key, JSON.stringify(value));
		} catch {
			/* storage unavailable: the page works without it */
		}
	},
};

const emit = (name, detail = {}) => doc.dispatchEvent(new CustomEvent(name, { detail }));
const on = (name, fn) => doc.addEventListener(name, (e) => fn(e.detail, e));

// Open overlays (drawers, dialogs, the mega menu, the live-search list). Whenever the set changes,
// :root.has-overlay toggles and 'gtm:overlay' { open } fires, so carousels and decorative loops can
// pause behind them (motion behind a modal distracts and costs CPU on mid-range phones).
const overlays = new Set();
const overlay = (id, open) => {
	const was = overlays.size > 0;
	if (open) overlays.add(id);
	else overlays.delete(id);
	const now = overlays.size > 0;
	if (was === now) return;
	root.classList.toggle('has-overlay', now);
	emit('gtm:overlay', { open: now });
};

/* ------------------------------------------------------------------ */
/* Module loader                                                      */
/* ------------------------------------------------------------------ */

const loaded = new Map();
/** Imports "@gadgetim/<name>" once and calls its init(ctx). Resolves to the module (null when absent). */
function load(name) {
	if (!loaded.has(name)) {
		loaded.set(name, import(`@gadgetim/${name}`).then((m) => {
			if (typeof m.init === 'function') m.init(ctx);
			return m;
		}).catch((err) => {
			if (!/resolve|specifier|Failed to fetch/i.test(String(err))) console.error(`[gadgetim] ${name}`, err);
			return null;
		}));
	}
	return loaded.get(name);
}

const idle = (fn) => ('requestIdleCallback' in window ? requestIdleCallback(fn, { timeout: 1500 }) : setTimeout(fn, 200));

/* ------------------------------------------------------------------ */
/* Badges (cart cookie, wishlist storage)                             */
/* ------------------------------------------------------------------ */

const WISH = cfg.wishKey || 'gtm_wish';
const wish = new Set((store.get(WISH, []) || []).map(Number).filter(Boolean));
let lastCart = -1;

const cartCount = () => {
	const n = parseInt(cookie(cfg.cartCookie || 'gtm_cart_count') || '0', 10);
	return Number.isFinite(n) && n > 0 ? n : 0;
};

const badges = {
	/** Re-reads the cookie / wishlist and refreshes every badge; bump: 'cart' | 'wish' plays the pop. */
	update(bump = '') {
		const n = cartCount();
		const set = (sel, value, doBump) => $$(sel).forEach((b) => {
			b.textContent = value ? fmt(value) : '';
			b.classList.toggle('is-empty', !value);
			if (doBump && value) {
				b.classList.remove('bump');
				void b.offsetWidth;
				b.classList.add('bump');
			}
		});
		set('[data-cart-count]', n, bump === 'cart' || (lastCart >= 0 && n > lastCart && bump !== 'none'));
		set('[data-wish-count]', wish.size, bump === 'wish');
		lastCart = n;
		$$('[data-cart-label]').forEach((el) => el.setAttribute('aria-label', n ? t('cartLabel', fmt(n)) : t('cartEmpty')));
		$$('[data-wish-label]').forEach((el) => el.setAttribute('aria-label', wish.size ? t('wishLabel', fmt(wish.size)) : t('wishEmpty')));
		return n;
	},
	cart: cartCount,
	wish: () => [...wish],
};

function syncHearts() {
	$$('[data-wish]').forEach((b) => b.setAttribute('aria-pressed', String(wish.has(Number(b.dataset.wish)))));
}

function toggleWish(btn) {
	const id = Number(btn.dataset.wish);
	if (!id) return;
	const onNow = !wish.has(id);
	if (onNow) wish.add(id);
	else wish.delete(id);
	store.set(WISH, [...wish]);
	syncHearts();
	if (onNow && !reduced()) {
		const burst = doc.createElement('span');
		burst.className = 'burst';
		burst.setAttribute('aria-hidden', 'true');
		burst.innerHTML = ['#ec4899', '#f59e0b', '#8b5cf6', '#f43f5e', '#6366f1', '#fbbf24'].map((c, i) => `<i style="--a:${i * 60}deg;--c:${c}"></i>`).join('');
		btn.appendChild(burst);
		setTimeout(() => burst.remove(), 700);
	}
	badges.update(onNow ? 'wish' : 'none');
	emit('gtm:wish', { id, on: onNow, ids: [...wish] });
	ctx.toast({ title: onNow ? t('wishAdded') : t('wishRemoved'), icon: 'heart', duration: 2600, action: onNow ? { label: t('viewWish'), open: 'wish-drawer' } : null });
}

/* ------------------------------------------------------------------ */
/* Bottom nav indicator                                               */
/* ------------------------------------------------------------------ */

const bnavEl = $('#bnav');
const bnavHome = bnavEl ? Number(bnavEl.dataset.idx) : -1;
function bnav(i = bnavHome) {
	if (!bnavEl) return;
	const ind = $('.bnav__ind', bnavEl);
	if (i >= 0) bnavEl.style.setProperty('--idx', String(i));
	if (ind) ind.classList.toggle('is-none', i < 0);
	$$('.bnav__i', bnavEl).forEach((b) => b.classList.toggle('is-active', Number(b.dataset.idx) === i));
}

/* ------------------------------------------------------------------ */
/* ctx                                                                */
/* ------------------------------------------------------------------ */

const ctx = {
	cfg, t, $, $$, esc, fmt, digits, reduced, mq, load, emit, on, cookie, store, badges, bnav,
	/** True while a drawer, dialog, the mega menu or the live-search list is open. */
	overlayOpen: () => overlays.size > 0,
	/** Lets a module report its own overlay (e.g. the product lightbox): ctx.overlay('lightbox', true). */
	overlay,
	/** Shows a toast (loads toast.js on first use). Options: title, text, img, icon, type, action {label, href|open}, duration. */
	toast(opts) {
		return load('toast').then((m) => (m ? m.show(opts) : null));
	},
};
window.gadgetim = ctx; // Debug / page-builder access.

/* ------------------------------------------------------------------ */
/* Header: tuck on scroll (phones), shadow, back-to-top               */
/* ------------------------------------------------------------------ */

function setupHeader() {
	const hdr = $('#hdr');
	const toTop = $('#to-top');
	const search = $('#search');
	if (!hdr) return;
	const measure = () => {
		if (search) hdr.style.setProperty('--row1', `${Math.max(0, search.offsetTop - 6)}px`);
		root.style.setProperty('--hdr-real', `${hdr.offsetHeight}px`);
	};
	measure();
	addEventListener('resize', measure, { passive: true });
	let lastY = scrollY;
	let ticking = false;
	const busy = () => doc.activeElement === $('#q') || root.classList.contains('is-locked') || ($('#mega') && $('#mega').classList.contains('is-open')) || ($('#sugg') && !$('#sugg').hidden);
	const update = () => {
		ticking = false;
		const y = scrollY;
		hdr.classList.toggle('is-scrolled', y > 8);
		if (y < 160 || busy()) hdr.classList.remove('is-tucked');
		else if (y > lastY + 6) hdr.classList.add('is-tucked');
		else if (y < lastY - 6) hdr.classList.remove('is-tucked');
		if (Math.abs(y - lastY) > 6) lastY = y;
		if (toTop) toTop.classList.toggle('is-on', y > 900);
	};
	addEventListener('scroll', () => {
		if (!ticking) {
			ticking = true;
			requestAnimationFrame(update);
		}
	}, { passive: true });
	update();
	if (toTop) {
		toTop.addEventListener('click', () => {
			scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
			const main = $('#main');
			if (main) main.focus({ preventScroll: true });
		});
	}
}

/* Promo ticker: one message at a time, paused on hover/focus and in hidden tabs. */
function setupTicker() {
	const list = $('[data-ticker]');
	const items = list ? $$('li', list) : [];
	if (items.length < 2 || reduced()) return;
	let i = 0;
	let hold = false;
	list.addEventListener('pointerenter', () => { hold = true; });
	list.addEventListener('pointerleave', () => { hold = false; });
	setInterval(() => {
		if (doc.hidden || hold) return;
		const cur = items[i];
		i = (i + 1) % items.length;
		const nx = items[i];
		cur.classList.remove('is-on');
		cur.classList.add('is-out');
		cur.setAttribute('aria-hidden', 'true');
		nx.classList.remove('is-out');
		nx.classList.add('is-on');
		nx.removeAttribute('aria-hidden');
		setTimeout(() => cur.classList.remove('is-out'), 700);
	}, 4200);
}

/* ------------------------------------------------------------------ */
/* Mega menu (desktop): hover intent, click, Escape, pane switching   */
/* ------------------------------------------------------------------ */

function setupMega() {
	const wrap = $('[data-mega]');
	const btn = $('[data-mega-btn]');
	const panel = $('#mega');
	const scrim = $('#mega-scrim');
	if (!wrap || !btn || !panel) return;
	let openT = 0;
	let closeT = 0;
	const isOpen = () => panel.classList.contains('is-open');
	// Panes 1…n are fetched once (cached fragment) on the first sign of intent; pane 0 ships with the page.
	let hydrating = null;
	const hydrate = () => {
		if (hydrating || !panel.dataset.megaSrc || !$('[data-lazy]', panel)) return hydrating;
		hydrating = fetch(panel.dataset.megaSrc, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
			.then((res) => (res.ok ? res.text() : Promise.reject(new Error(`HTTP ${res.status}`))))
			.then((html) => {
				const tpl = doc.createElement('template');
				tpl.innerHTML = html;
				$$('.mega__pane[data-pane]', tpl.content).forEach((pane) => {
					const old = $(`.mega__pane[data-lazy][data-pane="${pane.dataset.pane}"]`, panel);
					if (!old) return;
					pane.classList.toggle('is-active', old.classList.contains('is-active'));
					old.replaceWith(pane);
				});
			})
			.catch(() => {
				hydrating = null; // Retry on the next hover; the group links still lead to the categories.
			});
		return hydrating;
	};
	const open = () => {
		clearTimeout(closeT);
		if (!mq.desk.matches || isOpen()) return;
		panel.classList.add('is-open');
		btn.setAttribute('aria-expanded', 'true');
		if (scrim) scrim.classList.add('is-on');
		const hdr = $('#hdr');
		if (hdr) hdr.classList.remove('is-tucked');
		emit('gtm:mega', { open: true });
	};
	const close = () => {
		clearTimeout(openT);
		if (!isOpen()) return;
		panel.classList.remove('is-open');
		btn.setAttribute('aria-expanded', 'false');
		if (scrim) scrim.classList.remove('is-on');
		emit('gtm:mega', { open: false });
	};
	const activate = (g) => {
		const i = g.dataset.g;
		$$('.mega__g', panel).forEach((x) => x.classList.toggle('is-active', x === g));
		$$('.mega__pane', panel).forEach((x) => x.classList.toggle('is-active', x.dataset.pane === i));
	};
	wrap.addEventListener('pointerenter', (e) => {
		if (e.pointerType !== 'mouse') return;
		hydrate();
		clearTimeout(closeT);
		openT = setTimeout(open, 140);
	});
	wrap.addEventListener('pointerleave', (e) => {
		if (e.pointerType !== 'mouse') return;
		clearTimeout(openT);
		closeT = setTimeout(close, 260);
	});
	btn.addEventListener('focus', hydrate);
	// Desktop: warm the fragment (HTTP-cached) once the page has settled, so the first hover is instant.
	if (mq.desk.matches) {
		const warm = () => setTimeout(() => idle(hydrate), 2000);
		if (doc.readyState === 'complete') warm();
		else addEventListener('load', warm, { once: true });
	}
	btn.addEventListener('click', () => {
		hydrate();
		if (isOpen()) close();
		else {
			open();
			if (btn.matches(':focus-visible')) {
				const first = $('.mega__g', panel);
				if (first) first.focus();
			}
		}
	});
	let hoverT = 0;
	panel.addEventListener('pointerover', (e) => {
		const g = e.target.closest('.mega__g');
		clearTimeout(hoverT);
		if (g && !g.classList.contains('is-active')) hoverT = setTimeout(() => activate(g), 60);
	});
	panel.addEventListener('focusin', (e) => {
		hydrate();
		const g = e.target.closest('.mega__g');
		if (g) activate(g);
	});
	wrap.addEventListener('focusout', (e) => {
		if (!wrap.contains(e.relatedTarget)) close();
	});
	if (scrim) scrim.addEventListener('click', close);
	doc.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && isOpen()) {
			close();
			btn.focus();
		}
	});
	mq.desk.addEventListener('change', close);
	on('gtm:drawer', close);
	on('gtm:search-open', close);
}

/* ------------------------------------------------------------------ */
/* Accordions and tabs                                                */
/* ------------------------------------------------------------------ */

function toggleAcc(btn) {
	const list = btn.closest('[data-accordion]');
	const openNow = btn.getAttribute('aria-expanded') !== 'true';
	const group = list && list.dataset.accordion !== 'multi' ? $$('.acc__btn', list) : [btn];
	group.forEach((b) => {
		const on2 = b === btn ? openNow : false;
		b.setAttribute('aria-expanded', String(on2));
		const panel = doc.getElementById(b.getAttribute('aria-controls'));
		if (panel) panel.inert = !on2;
	});
}

/** Tabs: [role=tablist] > [role=tab][aria-controls]; arrow keys follow the reading direction. */
function selectTab(tab, focus = false) {
	const list = tab.closest('[role="tablist"]');
	if (!list) return;
	$$('[role="tab"]', list).forEach((x) => {
		const sel = x === tab;
		x.setAttribute('aria-selected', String(sel));
		x.tabIndex = sel ? 0 : -1;
		const panel = doc.getElementById(x.getAttribute('aria-controls') || '');
		if (panel && list.dataset.tabs !== 'shared') panel.hidden = !sel;
	});
	if (focus) tab.focus();
	emit('gtm:tab', { tab, list, index: $$('[role="tab"]', list).indexOf(tab) });
}

function setupTabsKeys() {
	doc.addEventListener('keydown', (e) => {
		const tab = e.target.closest && e.target.closest('[role="tab"]');
		if (!tab) return;
		const tabs = $$('[role="tab"]', tab.closest('[role="tablist"]'));
		const i = tabs.indexOf(tab);
		const rtl = (cfg.dir || root.dir) === 'rtl';
		const map = { ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1, Home: -999, End: 999 };
		if (!(e.key in map)) return;
		e.preventDefault();
		const n = Math.max(0, Math.min(tabs.length - 1, i + map[e.key]));
		selectTab(tabs[n], true);
	});
}

/* ------------------------------------------------------------------ */
/* Delegated clicks                                                   */
/* ------------------------------------------------------------------ */

function focusSearch() {
	const q = $('#q');
	if (!q) return;
	const hdr = $('#hdr');
	if (hdr) hdr.classList.remove('is-tucked');
	q.focus({ preventScroll: true });
	if (scrollY > 400) scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
	bnav(2);
}

function setupDelegates() {
	doc.addEventListener('click', (e) => {
		const t2 = e.target;
		if (!(t2 instanceof Element)) return;

		const add = t2.closest('[data-add-to-cart]');
		if (add) {
			e.preventDefault();
			load('cart').then((m) => {
				if (m) m.add(add);
				else location.href = add.href;
			});
			return;
		}
		const heart = t2.closest('[data-wish]');
		if (heart) {
			e.preventDefault();
			toggleWish(heart);
			return;
		}
		const opener = t2.closest('[data-open]');
		if (opener) {
			const id = opener.dataset.open;
			if (!doc.getElementById(id)) return;
			e.preventDefault();
			load('drawer').then((m) => {
				if (m) m.toggle(id, opener);
				else if (opener.href) location.href = opener.href;
			});
			return;
		}
		const act = t2.closest('[data-act="search"]');
		if (act) {
			e.preventDefault();
			focusSearch();
			return;
		}
		const acc = t2.closest('.acc__btn');
		if (acc) {
			toggleAcc(acc);
			return;
		}
		const tab = t2.closest('[role="tab"]');
		if (tab) selectTab(tab);
	});

	// Search: load the module on first intent.
	const q = $('#q');
	if (q) {
		const go = () => load('search').then((m) => m && m.attach && m.attach());
		q.addEventListener('focus', go, { once: true });
		q.addEventListener('pointerdown', go, { once: true });
		q.addEventListener('input', go, { once: true });
		q.addEventListener('keydown', go, { once: true });
		if (doc.activeElement === q) go();
	}

	// "/" focuses the search (desktop habit), unless typing.
	doc.addEventListener('keydown', (e) => {
		if (e.key === '/' && !e.ctrlKey && !e.metaKey && !/INPUT|TEXTAREA|SELECT/.test((doc.activeElement || {}).tagName || '') && !(doc.activeElement || {}).isContentEditable) {
			e.preventDefault();
			focusSearch();
		}
	});

	// Missing resized image: fall back to the full-size file.
	doc.addEventListener('error', (e) => {
		const img = e.target;
		if (img && img.tagName === 'IMG' && img.dataset.full && !img.dataset.failed) {
			img.dataset.failed = '1';
			img.src = img.dataset.full;
		}
	}, true);
}

/* Newsletter: in-place submit with the same admin-post handler (ajax=1 → JSON). */
function setupNewsletter() {
	const form = $('[data-newsletter]');
	if (!form) return;
	const msg = $('.news__msg', form);
	const input = $('input[type="email"]', form);
	form.addEventListener('submit', async (e) => {
		e.preventDefault();
		const ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(input.value.trim());
		if (!ok) {
			msg.classList.add('is-err');
			msg.textContent = t('newsInvalid');
			input.setAttribute('aria-invalid', 'true');
			input.focus();
			return;
		}
		input.removeAttribute('aria-invalid');
		const btn = $('button[type="submit"]', form);
		btn.classList.add('is-loading');
		try {
			const body = new FormData(form);
			body.set('ajax', '1');
			const res = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin' });
			const data = await res.json();
			msg.classList.toggle('is-err', !data.ok);
			msg.textContent = data.message || '';
			if (data.ok) form.reset();
		} catch {
			msg.classList.add('is-err');
			msg.textContent = t('error');
		} finally {
			btn.classList.remove('is-loading');
		}
	});
}

/* ------------------------------------------------------------------ */
/* Breadcrumbs: on overflow, start at the current page, fade the edges  */
/* ------------------------------------------------------------------ */

function setupCrumbs() {
	const list = $('.crumbs__list');
	if (!list) return;
	const sync = () => {
		const max = list.scrollWidth - list.clientWidth;
		const over = max > 2;
		list.classList.toggle('is-over', over);
		if (!over) return;
		const pos = Math.abs(list.scrollLeft);
		list.classList.toggle('at-start', pos < 2);
		list.classList.toggle('at-end', pos > max - 2);
	};
	const max = list.scrollWidth - list.clientWidth;
	// RTL scrollLeft runs 0 → negative; LTR 0 → positive. The end holds the current page.
	if (max > 2) list.scrollLeft = root.dir === 'rtl' ? -max : max;
	sync();
	list.addEventListener('scroll', sync, { passive: true });
	addEventListener('resize', sync, { passive: true });
}

/* ------------------------------------------------------------------ */
/* Boot                                                               */
/* ------------------------------------------------------------------ */

function boot() {
	badges.update('none');
	syncHearts();
	setupHeader();
	setupTicker();
	setupMega();
	setupDelegates();
	setupTabsKeys();
	setupNewsletter();
	setupCrumbs();

	// Keep the badge honest when another tab changes the cart or the wishlist.
	addEventListener('pageshow', () => badges.update('none'));
	addEventListener('storage', (e) => {
		if (e.key === WISH) {
			wish.clear();
			(store.get(WISH, []) || []).map(Number).forEach((id) => wish.add(id));
			syncHearts();
			badges.update('none');
		}
	});
	doc.addEventListener('visibilitychange', () => {
		if (!doc.hidden) badges.update('none');
	});
	on('gtm:drawer', ({ id, open }) => {
		if (!open) bnav(bnavHome);
		else if (id === 'menu-drawer') bnav(1);
		else if (id === 'cart-drawer') bnav(3);
		if (open && id === 'cart-drawer') load('cart').then((m) => m && m.refresh());
		if (open && id === 'wish-drawer') load('cart').then((m) => m && m.renderWish());
	});
	on('gtm:cart', () => badges.update());
	on('gtm:drawer', ({ id, open }) => overlay(id || 'drawer', !!open));
	on('gtm:mega', ({ open }) => overlay('mega', !!open));
	on('gtm:search-open', () => overlay('search', true));
	on('gtm:search-close', () => overlay('search', false));

	// Language switcher (<details data-lang>): one open at a time, close on outside click and Escape.
	doc.addEventListener('click', (e) => {
		doc.querySelectorAll('details[data-lang][open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
	});
	doc.addEventListener('keydown', (e) => {
		if (e.key !== 'Escape') return;
		doc.querySelectorAll('details[data-lang][open]').forEach((d) => { d.removeAttribute('open'); d.querySelector('summary')?.focus(); });
	});

	// Modules by presence.
	if ($('[data-carousel], .rail[id], [data-rail]')) load('carousel');
	if ($('[data-countdown]')) load('countdown');
	const page = doc.body.dataset.page || cfg.page;
	if (page === 'archive') load('filters');
	if (page === 'product') load('gallery');
	idle(() => {
		load('motion');
		load('fx');
	});
}

if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();
