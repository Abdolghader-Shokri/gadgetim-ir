/**
 * Gadgetim Aurora — search.js: live product suggestions for the header search (form[data-search]).
 *
 * - Debounced (220 ms), abortable GET gadgetim/v1/search?q=&lang= from 2 characters; answers are
 *   memoised per query. Items carry title, subtitle, url, thumb and price_html (server-escaped).
 * - Persian/Arabic letter normalisation (ي/ى→ی, ك→ک, ة/ۀ→ه, أ/إ/آ→ا, diacritics and ZWNJ, any
 *   digit set → ASCII) for matching categories and highlighting; the query is sent as typed
 *   (the endpoint normalises too).
 * - Idle state (empty box): popular searches, categories and today's deals from <template id="sugg-idle">.
 * - No results: a friendly message plus category suggestions and the deals.
 * - ARIA combobox: input[role=combobox] + #sugg[role=listbox]; ArrowUp/Down move, Enter opens the
 *   option, Escape closes then clears. Category matches come from the template (no request).
 * The category <select> scopes the full results page (?product_cat=…) and the "see all" link.
 *
 * API: attach() (called by app.js on first focus/pointer).
 */

let ctx = null;
let form = null;
let input = null;
let sugg = null;
let select = null;
let tpl = null;
let attached = false;
let active = -1;
let timer = 0;
let ctrl = null;

// Wraps the query and its quotes in Unicode isolates (FSI … PDI): a Latin query inside Persian text, or the
// reverse, keeps its quotes in place (same as gadgetim_isolate_query() in PHP).
const iso = (text, q) => {
	const i = q ? text.indexOf(q) : -1;
	if (i < 0) return text;
	let a = i;
	let b = i + q.length;
	if (a > 0 && '“«"‹„'.includes(text[a - 1])) a -= 1;
	if (b < text.length && '”»"›“'.includes(text[b])) b += 1;
	return `${text.slice(0, a)}⁨${text.slice(a, b)}⁩${text.slice(b)}`;
};
let seq = 0;
const memo = new Map();
const doc = document;

const VARIANTS = { 'ی': '[یيى]', 'ک': '[کك]', 'ه': '[هۀة]', 'ا': '[اآأإ]' };
const FA = '۰۱۲۳۴۵۶۷۸۹';
const AR = '٠١٢٣٤٥٦٧٨٩';

export function norm(s) {
	return String(s || '').toLowerCase()
		.replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/[ۀة]/g, 'ه').replace(/[أإآ]/g, 'ا')
		.replace(/[ً-ٰٟ]/g, '')
		.replace(/[۰-۹]/g, (d) => String(FA.indexOf(d)))
		.replace(/[٠-٩]/g, (d) => String(AR.indexOf(d)))
		.replace(/‌/g, ' ')
		.replace(/[^\p{L}\p{N}]+/gu, ' ')
		.trim();
}

function tokRe(tok) {
	return Array.from(tok).map((ch) => {
		if (VARIANTS[ch]) return VARIANTS[ch];
		if (/\d/.test(ch)) return `[${ch}${FA[ch]}${AR[ch]}]`;
		return ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}).join('‌?');
}

function highlight(text, q) {
	const { esc } = ctx;
	const toks = norm(q).split(' ').filter(Boolean);
	if (!toks.length) return esc(text);
	let re;
	try {
		re = new RegExp(toks.map(tokRe).join('|'), 'giu');
	} catch {
		return esc(text);
	}
	let out = '';
	let last = 0;
	String(text).replace(re, (m, off) => {
		if (!m) return m;
		out += `${esc(text.slice(last, off))}<mark>${esc(m)}</mark>`;
		last = off + m.length;
		return m;
	});
	return out + esc(String(text).slice(last));
}

/* ------------------------------------------------------------------ */
/* Rendering                                                          */
/* ------------------------------------------------------------------ */

function part(name) {
	return tpl ? tpl.content.querySelector(`[data-part="${name}"]`) : null;
}

function numberOptions() {
	Array.from(sugg.querySelectorAll('[role="option"]')).forEach((o, i) => {
		o.id = `sg-${i}`;
		o.setAttribute('aria-selected', 'false');
	});
	active = -1;
	input.removeAttribute('aria-activedescendant');
}

function idle() {
	const frag = doc.createDocumentFragment();
	['popular', 'cats', 'deals'].forEach((name) => {
		const p = part(name);
		if (p) frag.appendChild(p.cloneNode(true));
	});
	sugg.replaceChildren(frag);
	sugg.querySelectorAll('[data-sub]').forEach((el) => el.remove());
	numberOptions();
	open();
}

function matchCats(q) {
	const cats = part('cats');
	if (!cats) return [];
	const cq = norm(q).replace(/ /g, '');
	if (cq.length < 2) return [];
	return Array.from(cats.querySelectorAll('.chip-q'))
		.filter((c) => norm(c.dataset.name).replace(/ /g, '').includes(cq))
		.slice(0, 4);
}

function message(html) {
	sugg.innerHTML = `<div class="sugg__empty">${html}</div>`;
	numberOptions();
	open();
}

function results(q, items) {
	const { esc, t } = ctx;
	const frag = [];
	const cats = matchCats(q);
	if (cats.length) {
		frag.push(`<div role="group" aria-label="${esc(t('categories'))}"><div class="sugg__h" aria-hidden="true"><svg class="ic" aria-hidden="true"><use href="#i-grid"></use></svg>${esc(t('categories'))}</div><div class="sugg__chips">${cats.map((c) => `<a class="chip-q" role="option" href="${esc(c.getAttribute('href'))}"><svg class="ic" aria-hidden="true"><use href="#i-grid"></use></svg>${highlight(c.dataset.name, q)}</a>`).join('')}</div></div>`);
	}
	if (items.length) {
		frag.push(`<div role="group" aria-label="${esc(t('products'))}"><div class="sugg__h" aria-hidden="true"><svg class="ic" aria-hidden="true"><use href="#i-box"></use></svg>${esc(t('products'))}</div>${items.map((it) => `<a class="sugg__opt" role="option" href="${esc(it.url)}">${it.thumb ? `<img src="${esc(it.thumb)}" alt="" width="52" height="52" loading="lazy">` : '<span></span>'}<span><span class="sugg__name">${highlight(it.title || '', q)}</span>${it.subtitle ? `<span class="sugg__meta" dir="auto">${esc(it.subtitle)}</span>` : ''}</span><span class="sugg__price">${it.price_html || ''}</span></a>`).join('')}</div>`);
		const url = new URL(form.action, location.href);
		url.searchParams.set('s', q);
		url.searchParams.set('post_type', 'product');
		if (select && select.value) url.searchParams.set('product_cat', select.value);
		frag.push(`<div class="sugg__foot"><a class="sugg__all" role="option" href="${esc(url.href)}">${esc(iso(t('seeAll', q), q))}<svg class="ic ic--dir" aria-hidden="true"><use href="#i-arrow"></use></svg></a></div>`);
		sugg.innerHTML = frag.join('');
	} else if (!cats.length) {
		sugg.innerHTML = `<div class="sugg__none" role="status"><span class="sugg__none-ic" aria-hidden="true"><svg class="ic" aria-hidden="true"><use href="#i-search-x"></use></svg></span><b>${esc(iso(t('noResults', q), q))}</b><span>${esc(t('noResultsTip'))}</span></div>`;
		['cats', 'deals'].forEach((name) => {
			const p = part(name);
			if (p) sugg.appendChild(p.cloneNode(true));
		});
		sugg.querySelectorAll('[data-sub]').forEach((el) => el.remove());
	} else {
		sugg.innerHTML = frag.join('');
	}
	numberOptions();
	open();
}

/* ------------------------------------------------------------------ */
/* Fetching                                                           */
/* ------------------------------------------------------------------ */

async function run() {
	const q = input.value.trim();
	if (norm(q).length < 2) {
		if (!q) idle();
		else results(q, []);
		return;
	}
	const key = `${ctx.cfg.lang}|${norm(q)}`;
	if (memo.has(key)) {
		results(q, memo.get(key));
		return;
	}
	if (ctrl) ctrl.abort();
	ctrl = new AbortController();
	const mine = ++seq;
	if (sugg.hidden || !sugg.querySelector('.sugg__opt')) message(ctx.esc(ctx.t('searching')));
	try {
		const url = `${ctx.cfg.rest}search?q=${encodeURIComponent(q)}&lang=${encodeURIComponent(ctx.cfg.lang || '')}`;
		const res = await fetch(url, { signal: ctrl.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
		if (res.status === 429) {
			message(ctx.esc(ctx.t('rateLimited')));
			return;
		}
		const data = await res.json();
		const items = Array.isArray(data.items) ? data.items : [];
		memo.set(key, items);
		if (mine === seq && input.value.trim() === q) results(q, items);
	} catch (err) {
		if (err && err.name === 'AbortError') return;
		message(ctx.esc(ctx.t('error')));
	}
}

/* ------------------------------------------------------------------ */
/* Open / close / keyboard                                            */
/* ------------------------------------------------------------------ */

function open() {
	if (!sugg.hidden) return;
	sugg.hidden = false;
	input.setAttribute('aria-expanded', 'true');
	ctx.emit('gtm:search-open');
}

function close() {
	if (sugg.hidden) return;
	sugg.hidden = true;
	input.setAttribute('aria-expanded', 'false');
	input.removeAttribute('aria-activedescendant');
	active = -1;
	if (ctx.mq.phone.matches) ctx.bnav();
	ctx.emit('gtm:search-close');
}

function move(d) {
	const opts = Array.from(sugg.querySelectorAll('[role="option"]'));
	if (!opts.length) return;
	if (active >= 0 && opts[active]) opts[active].setAttribute('aria-selected', 'false');
	active = (active + d + opts.length) % opts.length;
	const o = opts[active];
	o.setAttribute('aria-selected', 'true');
	input.setAttribute('aria-activedescendant', o.id);
	o.scrollIntoView({ block: 'nearest' });
}

export function attach() {
	if (attached) return;
	form = doc.querySelector('[data-search]');
	if (!form) return;
	input = form.querySelector('input[type="search"]');
	sugg = form.querySelector('.sugg');
	select = form.querySelector('select');
	tpl = form.querySelector('template');
	if (!input || !sugg) return;
	attached = true;

	const schedule = () => {
		clearTimeout(timer);
		timer = setTimeout(run, 220);
	};
	input.addEventListener('focus', () => {
		run();
		if (ctx.mq.phone.matches) ctx.bnav(2);
	});
	input.addEventListener('input', schedule);
	if (select) select.addEventListener('change', () => { if (!sugg.hidden) run(); });
	input.addEventListener('keydown', (e) => {
		if (e.key === 'ArrowDown') {
			if (sugg.hidden) run();
			move(1);
			e.preventDefault();
		} else if (e.key === 'ArrowUp') {
			move(-1);
			e.preventDefault();
		} else if (e.key === 'Escape') {
			if (!sugg.hidden) {
				close();
				e.preventDefault();
			} else if (input.value) input.value = '';
		} else if (e.key === 'Enter' && active >= 0) {
			const o = sugg.querySelectorAll('[role="option"]')[active];
			if (o) {
				e.preventDefault();
				o.click();
			}
		}
	});
	sugg.addEventListener('mousedown', (e) => {
		if (e.target.closest('[data-q]')) e.preventDefault();
	});
	sugg.addEventListener('click', (e) => {
		const chip = e.target.closest('[data-q]');
		if (!chip) return;
		e.preventDefault();
		input.value = chip.dataset.q;
		input.focus();
		run();
	});
	form.addEventListener('submit', (e) => {
		if (!input.value.trim()) {
			e.preventDefault();
			input.focus();
		}
	});
	form.addEventListener('focusout', (e) => {
		if (!form.contains(e.relatedTarget)) setTimeout(() => { if (!form.contains(doc.activeElement)) close(); }, 120);
	});
	doc.addEventListener('pointerdown', (e) => {
		if (!sugg.hidden && !e.target.closest('[data-search]')) close();
	});
	ctx.on('gtm:drawer', close);
	ctx.on('gtm:mega', (d) => { if (d && d.open) close(); });
	if (doc.activeElement === input || input.value) run();
}

export function init(c) {
	ctx = c;
}
