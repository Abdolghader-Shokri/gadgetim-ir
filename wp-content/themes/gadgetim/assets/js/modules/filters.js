/**
 * Gadgetim Aurora — filters.js: catalogue archives (shop, category, brand, tag, product search).
 * Loaded by app.js on data-page="archive"; everything works without it (plain GET form, real links).
 *
 * - One filter form (#filters). ≥ 1000 px it lives in the sticky sidebar and every change applies at
 *   once; below, it moves into the <dialog id="filter-sheet"> (bottom sheet on phones, start drawer on
 *   tablets) whose pinned button shows the live count from GET /wp-json/gadgetim/v1/count.
 * - Applying fetches the same URL with the header "X-Requested-With: fragment"; the plugin answers with
 *   the results block only (toolbar, grid, pagination), which is swapped in with a staggered entrance,
 *   history.pushState and a polite announcement. Back/forward re-fetch the state of that URL.
 * - Sort chips/select, active-filter chips, pagination links and «نمایش بیشتر» (appends the next page,
 *   history.replaceState) use the same fragments. Errors fall back to a normal navigation.
 * - Price: a dual range slider on a proportional scale (deciles of the category's prices from the
 *   server) synced with two inputs that accept Persian/Arabic digits.
 * - Grid density (4 or 5 per row ≥ 1100 px, localStorage "gtm_dense"), the floating
 *   «فیلتر و مرتب‌سازی» pill on phones, swipe-down to close the sheet, the collapsed description.
 *
 * API: apply(url, opts), count(), open(), close().
 */

let ctx = null;
const doc = document;
const root = doc.documentElement;

const S = {
	form: null,
	side: null,
	sheet: null,
	slot: null,
	live: null,
	pill: null,
	context: {},
	defaultOrderby: '',
	price: null,
	ctrl: null,
	countCtrl: null,
	countTimer: 0,
	applyTimer: 0,
	applying: false,
	wide: matchMedia('(min-width: 1000px)'),
};

const $ = (sel, el = doc) => el.querySelector(sel);
const $$ = (sel, el = doc) => Array.from(el.querySelectorAll(sel));
const results = () => $('#results');

const parse = (text, fallback) => {
	try {
		return JSON.parse(text);
	} catch {
		return fallback;
	}
};

/** Any digit set → ASCII digits only ("۱٬۲۰۰٬۰۰۰ تومان" → "1200000"). */
const ascii = (s) => String(s ?? '')
	.replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
	.replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
	.replace(/\D/g, '');

const modified = (e) => e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey;

/* ------------------------------------------------------------------ */
/* Announcements, badges, labels                                      */
/* ------------------------------------------------------------------ */

function announce(text) {
	if (!S.live) return;
	S.live.textContent = '';
	requestAnimationFrame(() => { S.live.textContent = text; });
}

function tick(el) {
	if (!el || ctx.reduced()) return;
	el.classList.remove('is-tick');
	void el.offsetWidth;
	el.classList.add('is-tick');
}

/** Active filter values in the form: switches, brands, the price range (once). */
function activeCount() {
	if (!S.form) return 0;
	const fd = new FormData(S.form);
	const [mn, mx] = priceRange();
	return (fd.get('instock') ? 1 : 0) + (fd.get('sale') ? 1 : 0) + fd.getAll('brand[]').length + (mn || mx ? 1 : 0);
}

function paintBadges(n = activeCount()) {
	$$('[data-filters-badge], [data-filters-n]').forEach((b) => {
		b.textContent = n ? ctx.fmt(n) : '';
		b.classList.toggle('is-empty', !n);
	});
	$$('[data-filters-clear], [data-filters-reset]').forEach((a) => { a.hidden = !n; });
	$$('[data-filters-open]').forEach((b) => b.classList.toggle('has-n', n > 0));
}

function applyLabel(n) {
	const btn = S.form && $('[data-filters-apply]', S.form);
	const label = btn && $('[data-filters-label]', btn);
	if (!label) return;
	let text = ctx.t('showAny');
	if (n === 0) text = ctx.t('showNone');
	else if (typeof n === 'number') text = ctx.t(n === 1 ? 'showOne' : 'showMany', ctx.fmt(n));
	btn.setAttribute('aria-disabled', String(n === 0));
	if (label.textContent !== text) {
		label.textContent = text;
		tick(btn);
	}
}

function setCount(el, n) {
	if (!el) return;
	const v = ctx.fmt(Number(n) || 0);
	if (el.textContent !== v) {
		el.textContent = v;
		tick(el);
	}
}

/** Facet counts from the count endpoint or a fragment's JSON. */
function paintFacets(f) {
	if (!S.form || !f) return;
	setCount($('[data-facet="instock"]', S.form), f.instock);
	setCount($('[data-facet="sale"]', S.form), f.sale);
	const brands = f.brands || {};
	$$('[data-facet-brand]', S.form).forEach((el) => {
		const n = Number(brands[el.dataset.facetBrand] || 0);
		setCount(el, n);
		const li = el.closest('li');
		const input = li && $('input', li);
		if (li) li.classList.toggle('is-zero', n === 0 && !(input && input.checked));
	});
	applyLabel(typeof f.count === 'number' ? f.count : null);
}

/* ------------------------------------------------------------------ */
/* Price range                                                        */
/* ------------------------------------------------------------------ */

/** Rounds a price to a friendly step (10k below 1M, 50k below 10M, else 100k), down or up. */
function nice(p, up) {
	const step = p < 1e6 ? 1e4 : p < 1e7 ? 5e4 : 1e5;
	return (up ? Math.ceil(p / step) : Math.floor(p / step)) * step;
}

function setupPrice() {
	const box = $('[data-price]', S.form);
	if (!box) return;
	const steps = (parse(box.dataset.steps, []) || []).map(Number).filter((n) => n > 0);
	const lo = Number(box.dataset.min) || 0;
	const hi = Number(box.dataset.max) || 0;
	const iLo = $('[data-price-lo]', box);
	const iHi = $('[data-price-hi]', box);
	const P = { box, lo, hi, iLo, iHi, steps, slider: false };
	S.price = P;
	if (steps.length > 1 && hi > lo) {
		P.slider = true;
		P.rLo = $('[data-range-lo]', box);
		P.rHi = $('[data-range-hi]', box);
		const range = $('[data-range]', box);
		if (range) range.hidden = false;
	}
	const n = steps.length - 1;
	// Slider position (0…1000) ↔ price, piecewise linear over the deciles: equal distances hold equal numbers of products.
	P.toPrice = (v, up) => {
		if (v <= 0) return lo;
		if (v >= 1000) return hi;
		const x = (v / 1000) * n;
		const i = Math.min(n - 1, Math.floor(x));
		const raw = steps[i] + (x - i) * (steps[i + 1] - steps[i]);
		return Math.min(hi, Math.max(lo, nice(raw, up)));
	};
	P.toPos = (p) => {
		if (p <= steps[0]) return 0;
		if (p >= steps[n]) return 1000;
		for (let i = 0; i < n; i++) {
			if (p <= steps[i + 1]) {
				const span = steps[i + 1] - steps[i] || 1;
				return ((i + (p - steps[i]) / span) / n) * 1000;
			}
		}
		return 1000;
	};

	const sync = () => {
		if (!P.slider) return;
		const a = Number(ascii(iLo.value)) || lo;
		const b = Number(ascii(iHi.value)) || hi;
		P.rLo.value = String(Math.round(P.toPos(Math.min(a, b))));
		P.rHi.value = String(Math.round(P.toPos(Math.max(a, b))));
		paintRange();
	};
	P.sync = sync;
	sync();

	if (P.slider) {
		const onRange = (which) => {
			let a = Number(P.rLo.value);
			let b = Number(P.rHi.value);
			if (a > b - 10) {
				if (which === 'lo') a = Math.max(0, b - 10);
				else b = Math.min(1000, a + 10);
				P.rLo.value = String(a);
				P.rHi.value = String(b);
			}
			P.rLo.classList.toggle('is-top', which === 'lo' || a > 900);
			P.rHi.classList.toggle('is-top', which === 'hi' && a <= 900);
			const pa = P.toPrice(a, false);
			const pb = P.toPrice(b, true);
			iLo.value = a <= 0 ? '' : ctx.fmt(pa);
			iHi.value = b >= 1000 ? '' : ctx.fmt(pb);
			paintRange();
		};
		P.rLo.addEventListener('input', () => onRange('lo'));
		P.rHi.addEventListener('input', () => onRange('hi'));
		[P.rLo, P.rHi].forEach((r) => r.addEventListener('change', () => changed('price')));
	}

	const format = (inp) => {
		const raw = ascii(inp.value);
		inp.value = raw ? ctx.fmt(Number(raw)) : '';
	};
	[iLo, iHi].forEach((inp) => {
		inp.addEventListener('input', sync);
		inp.addEventListener('change', () => {
			format(inp);
			sync();
			changed('price');
		});
		inp.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') {
				e.preventDefault();
				format(inp);
				sync();
				changed('price', true);
			}
		});
	});
}

function paintRange() {
	const P = S.price;
	if (!P || !P.slider) return;
	const a = Number(P.rLo.value);
	const b = Number(P.rHi.value);
	P.box.style.setProperty('--a', `${a / 10}%`);
	P.box.style.setProperty('--b', `${b / 10}%`);
	const unit = (ctx.cfg.currency && ctx.cfg.currency.unit) || '';
	P.rLo.setAttribute('aria-valuetext', ctx.t('priceText', ctx.fmt(P.toPrice(a, false)), unit));
	P.rHi.setAttribute('aria-valuetext', ctx.t('priceText', ctx.fmt(P.toPrice(b, true)), unit));
}

/** [min, max] for the URL: ASCII integers, 0 when unset or at the category's bounds. */
function priceRange() {
	const P = S.price;
	if (!P) return [0, 0];
	let a = Number(ascii(P.iLo.value)) || 0;
	let b = Number(ascii(P.iHi.value)) || 0;
	if (a && b && a > b) [a, b] = [b, a];
	if (a && a <= P.lo) a = 0;
	if (b && b >= P.hi) b = 0;
	return [a, b];
}

/* ------------------------------------------------------------------ */
/* Form state ↔ URL                                                   */
/* ------------------------------------------------------------------ */

/** Canonical URL of the form state (same parameter order and form as the server's links). */
function formUrl() {
	const fd = new FormData(S.form);
	const parts = [];
	const add = (k, v) => parts.push(`${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
	$$('[data-keep]', S.form).forEach((i) => { if (i.value) add(i.name, i.value); });
	if (fd.get('instock')) add('instock', '1');
	if (fd.get('sale')) add('sale', '1');
	// Brand slugs are URL-safe already (WordPress stores non-Latin slugs percent-encoded).
	const brands = fd.getAll('brand[]').map(String).filter(Boolean).sort();
	if (brands.length) parts.push(`brand=${brands.join(',')}`);
	const [mn, mx] = priceRange();
	if (mn) add('min_price', mn);
	if (mx) add('max_price', mx);
	const ob = String(fd.get('orderby') || '');
	if (ob && ob !== S.defaultOrderby) add('orderby', ob);
	const base = S.form.getAttribute('action') || location.pathname;
	return parts.length ? `${base}?${parts.join('&')}` : base;
}

/** Sets the form controls from a URL (after link navigation, back/forward). */
function syncForm(url) {
	if (!S.form) return;
	const q = new URL(url, location.href).searchParams;
	const set = (name, on) => { const el = $(`input[name="${name}"]`, S.form); if (el) el.checked = on; };
	set('instock', q.get('instock') === '1');
	set('sale', q.get('sale') === '1');
	const brands = new Set((q.get('brand') || '').split(',').filter(Boolean).map((s) => s.toLowerCase()));
	$$('input[name="brand[]"]', S.form).forEach((el) => {
		el.checked = brands.has(el.value.toLowerCase()) || brands.has(decodeURIComponent(el.value).toLowerCase());
		const li = el.closest('li');
		if (li && el.checked) li.classList.remove('is-extra');
	});
	const ob = q.get('orderby') || S.defaultOrderby;
	const radio = $(`input[name="orderby"][value="${CSS.escape(ob)}"]`, S.form);
	if (radio) radio.checked = true;
	if (S.price) {
		const mn = Number(ascii(q.get('min_price')));
		const mx = Number(ascii(q.get('max_price')));
		S.price.iLo.value = mn ? ctx.fmt(mn) : '';
		S.price.iHi.value = mx ? ctx.fmt(mx) : '';
		S.price.sync();
	}
	paintBadges();
}

function resetForm() {
	if (!S.form) return;
	$$('input[type="checkbox"]', S.form).forEach((el) => { el.checked = false; });
	if (S.price) {
		S.price.iLo.value = '';
		S.price.iHi.value = '';
		S.price.sync();
	}
	paintBadges(0);
}

function setSort(key) {
	if (!S.form || !key) return;
	const radio = $(`input[name="orderby"][value="${CSS.escape(key)}"]`, S.form);
	if (radio) radio.checked = true;
}

/* ------------------------------------------------------------------ */
/* Live count (sheet)                                                 */
/* ------------------------------------------------------------------ */

function countUrl() {
	const p = new URLSearchParams();
	const c = S.context || {};
	if (c.tax && c.term) {
		p.set('tax', c.tax);
		p.set('term', String(c.term));
	}
	if (c.s) p.set('s', c.s);
	const fd = new FormData(S.form);
	if (fd.get('instock')) p.set('instock', '1');
	if (fd.get('sale')) p.set('sale', '1');
	const brands = fd.getAll('brand[]').map(String).filter(Boolean).sort();
	if (brands.length) p.set('brand', brands.join(','));
	const [mn, mx] = priceRange();
	if (mn) p.set('min_price', String(mn));
	if (mx) p.set('max_price', String(mx));
	p.set('lang', ctx.cfg.lang || 'fa');
	p.sort();
	return `${ctx.cfg.rest || '/wp-json/gadgetim/v1/'}count?${p}`;
}

export function count() {
	clearTimeout(S.countTimer);
	S.countTimer = setTimeout(async () => {
		if (S.countCtrl) S.countCtrl.abort();
		const ctrl = new AbortController();
		S.countCtrl = ctrl;
		const btn = $('[data-filters-apply]', S.form);
		if (btn) btn.classList.add('is-counting');
		try {
			const res = await fetch(countUrl(), { signal: ctrl.signal, credentials: 'omit' });
			if (!res.ok) throw new Error(String(res.status));
			paintFacets(await res.json());
		} catch (err) {
			if (err.name !== 'AbortError') applyLabel(null);
		} finally {
			if (btn && S.countCtrl === ctrl) btn.classList.remove('is-counting');
		}
	}, 240);
}

/* ------------------------------------------------------------------ */
/* Fragments: apply, swap, append                                     */
/* ------------------------------------------------------------------ */

function headerOffset() {
	const v = parseFloat(getComputedStyle(root).getPropertyValue('--hdr-real'));
	return (Number.isFinite(v) && v > 0 ? v : 112) + 12;
}

/** Brings the top of the results under the sticky header: always when forced (sheet, pager), else only
 *  when the visitor has scrolled past it (a sidebar change never yanks the page down). */
function scrollToResults(force = false) {
	const el = results();
	if (!el) return;
	const top = el.getBoundingClientRect().top;
	const off = headerOffset();
	if (!force && top >= off - 4) return;
	if (force && Math.abs(top - off) < 8) return;
	scrollTo({ top: Math.max(0, top + scrollY - off), behavior: ctx.reduced() ? 'auto' : 'smooth' });
}

function readFacets(scope) {
	const s = $('script[data-facets]', scope);
	return s ? parse(s.textContent, null) : null;
}

function syncHearts(scope) {
	const wish = new Set((ctx.store.get(ctx.cfg.wishKey || 'gtm_wish', []) || []).map(Number));
	$$('[data-wish]', scope).forEach((b) => b.setAttribute('aria-pressed', String(wish.has(Number(b.dataset.wish)))));
}

function enter(items) {
	items.forEach((li, i) => {
		li.style.setProperty('--i', String(Math.min(i, 11)));
		li.classList.add('is-enter');
		li.addEventListener('animationend', () => li.classList.remove('is-enter'), { once: true });
	});
}

/** After new results arrive: hearts, rails, counts, title, badges, announcement. */
function settle(scope, facets) {
	syncHearts(scope);
	if ($('.rail[id]', scope)) ctx.load('carousel').then((m) => m && m.mount && m.mount(scope));
	if ($('[data-reveal]', scope)) ctx.load('motion').then((m) => m && m.observe && m.observe(scope));
	if (facets) {
		if (facets.title) doc.title = facets.title;
		paintFacets(facets);
		paintBadges(typeof facets.active === 'number' ? facets.active : activeCount());
	}
	watchToolbar();
}

/**
 * Fetches a catalogue URL as a fragment and swaps (or appends) the results.
 *
 * @param {string} url
 * @param {{push?: boolean, scroll?: boolean, append?: boolean, focus?: string|null, sync?: boolean}} opts
 */
export async function apply(url, opts = {}) {
	const { push = true, scroll = true, append = false, focus = null, sync = false } = opts;
	const cur = results();
	if (!cur) {
		location.assign(url);
		return;
	}
	if (S.ctrl) S.ctrl.abort();
	const ctrl = new AbortController();
	S.ctrl = ctrl;
	if (!append) {
		cur.classList.add('is-busy');
		cur.setAttribute('aria-busy', 'true');
	}
	let html = '';
	let finalUrl = url;
	try {
		const res = await fetch(url, { headers: { 'X-Requested-With': 'fragment' }, credentials: 'same-origin', cache: 'no-store', signal: ctrl.signal });
		if (!res.ok) throw new Error(String(res.status));
		if (res.redirected && res.url) finalUrl = res.url;
		html = await res.text();
	} catch (err) {
		if (err.name === 'AbortError') return;
		location.assign(url);
		return;
	}
	if (S.ctrl !== ctrl) return;
	const tpl = doc.createElement('template');
	tpl.innerHTML = html;
	const next = tpl.content.querySelector('[data-results]');
	if (!next) {
		location.assign(url);
		return;
	}
	const facets = readFacets(next);

	if (append) {
		appendPage(cur, next, facets);
		history.replaceState({ gtmArch: 1 }, '', finalUrl);
		return;
	}

	if (push) history.pushState({ gtmArch: 1 }, '', finalUrl);
	else history.replaceState({ gtmArch: 1 }, '', finalUrl);

	const active = doc.activeElement;
	const focusSort = active && cur.contains(active) && active.dataset ? active.dataset.sort : '';
	const hadFocus = active && cur.contains(active);
	cur.replaceWith(next);
	enter($$('.arch__grid > li', next));
	if (sync) syncForm(finalUrl);
	settle(next, facets);

	const n = facets ? Number(facets.count) : $$('.arch__grid > li', next).length;
	announce(n === 0 ? ctx.t('foundNone') : ctx.t(n === 1 ? 'foundOne' : 'foundMany', ctx.fmt(n)));

	let target = null;
	if (focus === 'results' || (hadFocus && !focusSort)) target = $('#results-title', next);
	if (focusSort) target = $(`[data-sort="${CSS.escape(focusSort)}"]`, next) || $('#results-title', next);
	if (target) {
		if (!target.hasAttribute('tabindex') && target.id === 'results-title') target.setAttribute('tabindex', '-1');
		target.focus({ preventScroll: true });
	}
	if (scroll) scrollToResults(focus === 'results');
}

function appendPage(cur, next, facets) {
	const grid = $('.arch__grid', cur);
	const add = $$('.arch__grid > li', next);
	if (!grid || !add.length) return;
	add.forEach((li) => grid.appendChild(li));
	enter(add);
	const btn = $('[data-load-more]', cur);
	const first = btn ? Number(btn.dataset.first) || 1 : 1;
	const more = $('[data-more]', cur);
	const nextMore = $('[data-more]', next);
	if (more && nextMore) more.replaceWith(nextMore);
	const pager = $('.arch__pager', cur);
	const nextPager = $('.arch__pager', next);
	if (pager && nextPager) pager.replaceWith(nextPager);
	else if (pager) pager.remove();
	const json = $('script[data-facets]', cur);
	const nextJson = $('script[data-facets]', next);
	if (json && nextJson) json.replaceWith(nextJson);
	const merged = $('[data-more]', cur);
	if (merged && facets) {
		const text = $('[data-more-text]', merged);
		if (text) text.textContent = ctx.t('shownRange', ctx.fmt(first), ctx.fmt(facets.last), ctx.fmt(facets.count));
		const nb = $('[data-load-more]', merged);
		if (nb) nb.dataset.first = String(first);
		const bar = $('.more__bar i', merged);
		if (bar && facets.count) bar.style.setProperty('--p', String(Math.min(1, facets.last / facets.count)));
	}
	syncHearts(cur);
	if (facets && facets.title) doc.title = facets.title;
	announce(ctx.t('pageLoaded', ctx.fmt(facets ? facets.page : '')));
	const link = $('.pc__title a', add[0]);
	if (link) link.focus({ preventScroll: true });
}

function loadMore(btn) {
	if (btn.classList.contains('is-loading')) return;
	btn.classList.add('is-loading');
	btn.setAttribute('aria-busy', 'true');
	apply(btn.href, { append: true, push: false, scroll: false }).finally(() => {
		if (doc.contains(btn)) {
			btn.classList.remove('is-loading');
			btn.removeAttribute('aria-busy');
		}
	});
}

/* ------------------------------------------------------------------ */
/* Form wiring: instant on wide screens, live count in the sheet      */
/* ------------------------------------------------------------------ */

const inSheet = () => !!(S.sheet && S.form && S.slot && S.slot.contains(S.form));

function changed(kind, now = false) {
	paintBadges();
	if (kind === 'sort') {
		if (!inSheet()) apply(formUrl(), { scroll: true });
		return;
	}
	if (inSheet()) {
		count();
		return;
	}
	clearTimeout(S.applyTimer);
	S.applyTimer = setTimeout(() => apply(formUrl(), { scroll: true }), now ? 0 : kind === 'price' ? 380 : 220);
}

function setupForm() {
	S.form.addEventListener('change', (e) => {
		const t = e.target;
		if (!(t instanceof HTMLInputElement)) return;
		if (t.matches('[data-price-lo], [data-price-hi], .frange__in')) return; // handled by setupPrice()
		if (t.name === 'brand[]') {
			const li = t.closest('li');
			if (li) li.classList.toggle('is-zero', false);
		}
		changed(t.name === 'orderby' ? 'sort' : 'facet');
	});
	S.form.addEventListener('submit', (e) => {
		e.preventDefault();
		const btn = $('[data-filters-apply]', S.form);
		if (btn && btn.getAttribute('aria-disabled') === 'true') return;
		const url = formUrl();
		const fromSheet = inSheet() && S.sheet.open;
		if (fromSheet) {
			S.applying = true;
			close();
		}
		apply(url, { scroll: true, focus: fromSheet ? 'results' : null });
	});

	const more = $('[data-brands-more]', S.form);
	const list = $('[data-brands]', S.form);
	if (more && list) {
		more.hidden = false;
		more.addEventListener('click', () => {
			const on = !list.classList.contains('is-all');
			list.classList.toggle('is-all', on);
			more.setAttribute('aria-expanded', String(on));
			if (on) {
				const first = $('li.is-extra input', list);
				if (first) first.focus();
			}
		});
	}
}

/* ------------------------------------------------------------------ */
/* Sheet: placement, open/close, swipe down                           */
/* ------------------------------------------------------------------ */

function place() {
	if (!S.form || !S.side || !S.slot) return;
	if (S.wide.matches) {
		if (S.form.parentElement !== S.side) S.side.appendChild(S.form);
		if (S.sheet && S.sheet.open) ctx.load('drawer').then((m) => m && m.close(true, false));
	}
}

export function open(trigger = null) {
	if (!S.sheet || !S.form) return;
	if (S.form.parentElement !== S.slot) S.slot.appendChild(S.form);
	ctx.load('drawer').then((m) => {
		if (m) m.open('filter-sheet', trigger);
	});
}

export function close() {
	ctx.load('drawer').then((m) => {
		if (m && m.current() && m.current().id === 'filter-sheet') m.close();
	});
}

function setupSheet() {
	if (!S.sheet) return;
	S.wide.addEventListener('change', place);
	place();
	const head = $('.filters__head', S.form);
	if (!head) return;
	let y0 = 0;
	let dy = 0;
	let pid = null;
	head.addEventListener('pointerdown', (e) => {
		if (!inSheet() || !ctx.mq.phone.matches || e.pointerType === 'mouse' || e.target.closest('button, a')) return;
		pid = e.pointerId;
		y0 = e.clientY;
		dy = 0;
		S.sheet.classList.add('is-dragging');
		try { head.setPointerCapture(pid); } catch { /* not capturable */ }
	});
	head.addEventListener('pointermove', (e) => {
		if (pid !== e.pointerId) return;
		dy = Math.max(0, e.clientY - y0);
		S.sheet.style.transform = `translateY(${dy}px)`;
	});
	const end = (e) => {
		if (pid !== e.pointerId) return;
		pid = null;
		S.sheet.classList.remove('is-dragging');
		if (dy > 90) {
			requestAnimationFrame(() => {
				S.sheet.style.transform = 'translateY(102%)';
				close();
			});
		} else {
			S.sheet.style.removeProperty('transform');
		}
	};
	head.addEventListener('pointerup', end);
	head.addEventListener('pointercancel', end);
}

/* ------------------------------------------------------------------ */
/* Floating pill (phones/tablets)                                     */
/* ------------------------------------------------------------------ */

let pillTick = false;
function pillFrame() {
	pillTick = false;
	if (!S.pill) return;
	const tb = $('[data-toolbar]');
	const res = results();
	let on = false;
	if (tb && res && !S.wide.matches) {
		const r = tb.getBoundingClientRect();
		const b = res.getBoundingClientRect();
		on = r.bottom < 0 && b.bottom > innerHeight * 0.55;
	}
	S.pill.classList.toggle('is-on', on);
	S.pill.inert = !on;
}

function watchToolbar() {
	if (!S.pill) return;
	requestAnimationFrame(pillFrame);
}

function setupPill() {
	if (!S.pill || !S.sheet) return;
	S.pill.hidden = false;
	S.pill.inert = true;
	addEventListener('scroll', () => {
		if (!pillTick) {
			pillTick = true;
			requestAnimationFrame(pillFrame);
		}
	}, { passive: true });
	addEventListener('resize', watchToolbar, { passive: true });
	pillFrame();
}

/* ------------------------------------------------------------------ */
/* Density, description                                               */
/* ------------------------------------------------------------------ */

function setDensity(dense) {
	root.classList.toggle('is-dense', dense);
	try {
		localStorage.setItem('gtm_dense', dense ? '1' : '0');
	} catch { /* storage unavailable */ }
	$$('[data-density]').forEach((b) => b.setAttribute('aria-pressed', String((b.dataset.density === '1') === dense)));
	enter($$('.arch__grid > li'));
}

function setupDesc() {
	const body = $('[data-desc]');
	const btn = $('[data-desc-toggle]');
	if (!body || !btn) return;
	const box = body.closest('.adesc');
	box.classList.add('is-clamp');
	if (body.scrollHeight <= body.clientHeight + 24) {
		box.classList.remove('is-clamp');
		return;
	}
	btn.hidden = false;
	btn.addEventListener('click', () => {
		const on = !body.classList.contains('is-open');
		body.classList.toggle('is-open', on);
		btn.setAttribute('aria-expanded', String(on));
		if (!on) box.scrollIntoView({ block: 'nearest', behavior: ctx.reduced() ? 'auto' : 'smooth' });
	});
}

/* ------------------------------------------------------------------ */
/* Delegated clicks and changes                                       */
/* ------------------------------------------------------------------ */

function setupDelegates() {
	doc.addEventListener('click', (e) => {
		const t = e.target;
		if (!(t instanceof Element) || e.defaultPrevented) return;

		const opener = t.closest('[data-filters-open]');
		if (opener) {
			e.preventDefault();
			open(opener);
			return;
		}

		const more = t.closest('a[data-load-more]');
		if (more && !modified(e)) {
			e.preventDefault();
			loadMore(more);
			return;
		}

		const link = t.closest('a[data-arch-link], #results .pager a');
		if (link && !modified(e) && link.origin === location.origin) {
			e.preventDefault();
			if (link.matches('[data-filters-clear], [data-filters-reset]') && inSheet()) {
				resetForm();
				count();
				return;
			}
			if (link.dataset.sort) {
				setSort(link.dataset.sort);
				tick(link);
			}
			const fromSheet = inSheet() && S.sheet.open;
			if (fromSheet) {
				S.applying = true;
				close();
			}
			const pager = !!link.closest('.pager');
			apply(link.href, { scroll: true, sync: !link.dataset.sort, focus: pager || fromSheet ? 'results' : null });
			return;
		}

		const dens = t.closest('[data-density]');
		if (dens) {
			setDensity(dens.dataset.density === '1');
		}
	});

	doc.addEventListener('change', (e) => {
		const sel = e.target;
		if (!(sel instanceof HTMLSelectElement) || !sel.matches('[data-sort-select]')) return;
		const key = sel.value;
		setSort(key);
		const chip = $(`.sorts__i[data-sort="${CSS.escape(key)}"]`);
		const url = S.form ? formUrl() : (chip ? chip.href : '');
		if (url) apply(url, { scroll: false });
	});
}

/* ------------------------------------------------------------------ */
/* Boot                                                               */
/* ------------------------------------------------------------------ */

export function init(c) {
	ctx = c;
	if (!results()) return;
	S.form = $('#filters');
	S.side = $('#arch-side');
	S.sheet = $('#filter-sheet');
	S.slot = S.sheet ? $('[data-filters-slot]', S.sheet) : null;
	S.live = $('#arch-live');
	S.pill = $('[data-fpill]');
	if (S.form) {
		S.context = parse(S.form.dataset.ctx || '{}', {}) || {};
		S.defaultOrderby = S.form.dataset.defaultOrderby || '';
		setupPrice();
		setupForm();
		setupSheet();
	}
	setupDelegates();
	setupPill();
	setupDesc();
	syncHearts(doc);
	const dense = root.classList.contains('is-dense');
	$$('[data-density]').forEach((b) => b.setAttribute('aria-pressed', String((b.dataset.density === '1') === dense)));

	history.replaceState({ gtmArch: 1 }, '', location.href);
	addEventListener('popstate', (e) => {
		if (e.state && e.state.gtmArch) apply(location.href, { push: false, scroll: true, sync: true });
	});
	// The sheet dismissed without «نمایش …»: forget the unapplied changes (badge, counts, controls).
	ctx.on('gtm:drawer', ({ id, open: isOpen }) => {
		if (id !== 'filter-sheet' || isOpen) return;
		if (!S.applying && S.form) {
			syncForm(location.href);
			paintFacets(readFacets(results() || doc));
		}
		S.applying = false;
	});
	// Another tab or the cart drawer changed the wishlist.
	ctx.on('gtm:wish', () => syncHearts(doc));
}
