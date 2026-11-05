/**
 * Gadgetim Aurora — pages.js (pages layer, script module "@gadgetim/pages").
 *
 * Enqueued by inc/pages.php gadgetim_pages_use_script() only on pages that need it. Every feature
 * enhances server-rendered markup that already works without JavaScript:
 *
 *   FAQ          input [data-faq-q][aria-controls=<root>] filters [data-faq] live (Persian/Arabic letter
 *                variants, digit sets and ZWNJ folded), highlights the words in the questions, opens the
 *                matches, keeps ?faq= in the URL, counts results ([data-counts] per count, all plural
 *                forms from PHP); deep links (#question-id) open their answer; [data-copy-link] copies it.
 *   Contact      form [data-contact-form] validates on submit and then live, posts with ajax=1 to
 *                admin-post.php and shows the success card ([data-cform-done]); message counter.
 *   Copy         [data-copy="text"] copies text (Clipboard API, execCommand fallback on http).
 *   TOC          [data-toc] links get aria-current for the section being read.
 *
 * Shared UI (toasts) comes from app.js through window.gadgetim when it is there.
 */

const doc = document;
const $ = (sel, el = doc) => el.querySelector(sel);
const $$ = (sel, el = doc) => Array.from(el.querySelectorAll(sel));
const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

let cfg = {};
try {
	cfg = JSON.parse(($('#gadgetim-config') || {}).textContent || '{}');
} catch {
	cfg = {};
}
const nf = new Intl.NumberFormat(cfg.locale || 'fa-IR', { maximumFractionDigits: 0 });
const fmt = (n) => nf.format(Number(n) || 0);

const FA = '۰۱۲۳۴۵۶۷۸۹';
const AR = '٠١٢٣٤٥٦٧٨٩';
const toAscii = (s) => String(s || '').replace(/[۰-۹]/g, (d) => String(FA.indexOf(d))).replace(/[٠-٩]/g, (d) => String(AR.indexOf(d)));

/** Comparable text: lowercase, ي/ك/ة/أ folded, marks and ZWNJ removed, ASCII digits, single spaces. */
const norm = (s) => toAscii(s)
	.toLowerCase()
	.replace(/[يى]/g, 'ی')
	.replace(/ك/g, 'ک')
	.replace(/ة/g, 'ه')
	.replace(/[أإآ]/g, 'ا')
	.replace(/[ً-ٰٟ‌‍]/g, '')
	.replace(/\s+/g, ' ')
	.trim();

const toast = (opts) => {
	const g = window.gadgetim;
	if (g && typeof g.toast === 'function') g.toast(opts);
};

/* ------------------------------------------------------------------ */
/* Copy to the clipboard                                              */
/* ------------------------------------------------------------------ */

async function copyText(text) {
	try {
		if (navigator.clipboard && window.isSecureContext) {
			await navigator.clipboard.writeText(text);
			return true;
		}
	} catch {
		/* fall through to the legacy path */
	}
	const ta = doc.createElement('textarea');
	ta.value = text;
	ta.setAttribute('readonly', '');
	ta.style.cssText = 'position:fixed;inset-block-start:-1000px;opacity:0';
	doc.body.appendChild(ta);
	ta.select();
	let ok = false;
	try {
		ok = doc.execCommand('copy');
	} catch {
		ok = false;
	}
	ta.remove();
	return ok;
}

function flash(btn, label) {
	btn.classList.add('is-copied');
	clearTimeout(btn._gtmCopied);
	btn._gtmCopied = setTimeout(() => btn.classList.remove('is-copied'), 1800);
	if (label) toast({ title: label, icon: 'check', type: 'success', duration: 2200 });
}

function setupCopy() {
	doc.addEventListener('click', async (e) => {
		const target = e.target instanceof Element ? e.target : null;
		const btn = target && target.closest('[data-copy], [data-copy-link]');
		if (!btn) return;
		e.preventDefault();
		let text = btn.dataset.copy || '';
		if (btn.hasAttribute('data-copy-link')) {
			const id = decodeURIComponent((btn.getAttribute('href') || '').replace(/^#/, ''));
			let url = location.origin + location.pathname;
			try {
				url = decodeURI(url);
			} catch {
				/* keep the encoded URL */
			}
			text = `${url}#${id}`;
			history.replaceState(history.state, '', `#${encodeURIComponent(id)}`);
		}
		if (text && (await copyText(text))) flash(btn, btn.dataset.copied || '');
	});
}

/* ------------------------------------------------------------------ */
/* FAQ: live filter, highlight, deep links                            */
/* ------------------------------------------------------------------ */

const LETTERS = { 'ی': '[یيى]', 'ک': '[کك]', 'ه': '[هة]', 'ا': '[اأإآ]' };
const escRe = (c) => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** A regex that finds the (normalised) words in original text, letter variants and marks included. */
function wordsRe(words) {
	const parts = words.map((w) => Array.from(w).map((c) => {
		const d = '0123456789'.indexOf(c);
		const base = d >= 0 ? `[${c}${FA[d]}${AR[d]}]` : (LETTERS[c] || escRe(c));
		return `${base}[\\u064B-\\u065F\\u0670\\u200C\\u200D]*`;
	}).join(''));
	return new RegExp(`(${parts.join('|')})`, 'giu');
}

function mark(el, words) {
	const re = wordsRe(words);
	const walker = doc.createTreeWalker(el, NodeFilter.SHOW_TEXT);
	const nodes = [];
	while (walker.nextNode()) nodes.push(walker.currentNode);
	nodes.forEach((node) => {
		const text = node.nodeValue || '';
		re.lastIndex = 0;
		if (!re.test(text)) return;
		re.lastIndex = 0;
		const frag = doc.createDocumentFragment();
		let last = 0;
		text.replace(re, (m, g, at) => {
			frag.append(text.slice(last, at));
			const mk = doc.createElement('mark');
			mk.textContent = m;
			frag.append(mk);
			last = at + m.length;
			return m;
		});
		frag.append(text.slice(last));
		node.replaceWith(frag);
	});
}

function setupFaq() {
	$$('[data-faq-q]').forEach((input) => {
		const root = doc.getElementById(input.getAttribute('aria-controls') || 'faq');
		if (!root || !root.hasAttribute('data-faq')) return;
		const form = input.closest('form');
		let counts = [];
		try {
			counts = JSON.parse(root.dataset.counts || '[]');
		} catch {
			counts = [];
		}
		const items = $$('[data-faq-item]', root).map((el) => {
			const qt = $('[data-faq-qt]', el);
			const item = { el, qt, html: qt ? qt.innerHTML.replace(/<\/?mark>/g, '') : '', hay: norm(el.textContent), auto: el.open && el.hidden === false && !!input.value, expect: 0 };
			el.addEventListener('toggle', () => {
				if (item.expect > 0) {
					item.expect -= 1;
					return;
				}
				item.auto = false; // the visitor opened or closed it: leave it alone from now on
			});
			return item;
		});
		const groups = $$('[data-faq-group]', root);
		const count = $('[data-faq-count]', root);
		const none = $('[data-faq-none]', root);
		const clears = $$('[data-faq-clear]', root);

		const setOpen = (it, open) => {
			if (it.el.open === open) return;
			it.expect += 1;
			it.el.open = open;
		};
		const apply = () => {
			const raw = input.value.trim();
			const words = norm(raw).split(' ').filter(Boolean);
			let shown = 0;
			items.forEach((it) => {
				const hit = !words.length || words.every((w) => it.hay.includes(w));
				it.el.hidden = !hit;
				if (it.qt) {
					it.qt.innerHTML = it.html;
					if (hit && words.length) mark(it.qt, words);
				}
				if (hit) shown += 1;
				if (words.length && hit && !it.el.open) {
					setOpen(it, true);
					it.auto = true;
				} else if ((!hit || !words.length) && it.auto) {
					setOpen(it, false);
					it.auto = false;
				}
			});
			groups.forEach((g) => {
				g.hidden = !$$('[data-faq-item]', g).some((el) => !el.hidden);
			});
			if (count) count.textContent = words.length ? (counts[shown] || fmt(shown)) : '';
			if (none) none.hidden = !(words.length && shown === 0);
			clears.forEach((b) => {
				b.hidden = !words.length;
			});
			const url = new URL(location.href);
			if (raw) url.searchParams.set('faq', raw);
			else url.searchParams.delete('faq');
			history.replaceState(history.state, '', url);
			return shown;
		};

		let timer = 0;
		input.addEventListener('input', () => {
			clearTimeout(timer);
			timer = setTimeout(apply, 110);
		});
		input.addEventListener('search', apply);
		if (form) {
			form.addEventListener('submit', (e) => {
				e.preventDefault();
				clearTimeout(timer);
				apply();
				const first = items.find((it) => !it.el.hidden);
				if (first) first.el.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
			});
		}
		clears.forEach((b) => b.addEventListener('click', () => {
			input.value = '';
			apply();
			input.focus();
		}));
		if (input.value.trim()) apply();
	});

	// Deep links: #question-id opens its answer (clearing a filter that hides it).
	const openHash = () => {
		let id = '';
		try {
			id = decodeURIComponent(location.hash.slice(1));
		} catch {
			id = '';
		}
		const el = id ? doc.getElementById(id) : null;
		if (!el || !el.matches('[data-faq-item]')) return;
		if (el.hidden) {
			const root = el.closest('[data-faq]');
			const input = root ? $(`[data-faq-q][aria-controls="${root.id}"]`) : null;
			if (input) {
				input.value = '';
				input.dispatchEvent(new Event('search'));
			}
		}
		el.open = true;
		requestAnimationFrame(() => el.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' }));
	};
	addEventListener('hashchange', openHash);
	openHash();
}

/* ------------------------------------------------------------------ */
/* Contact form                                                       */
/* ------------------------------------------------------------------ */

function setupContact() {
	const form = $('[data-contact-form]');
	if (!form) return;
	const section = form.closest('.cform') || form.parentElement;
	let msg = {};
	try {
		msg = JSON.parse(form.dataset.msg || '{}');
	} catch {
		msg = {};
	}
	const errs = msg.errors || {};
	form.noValidate = true;
	const alertBox = $('[data-cform-alert]', form);
	const done = $('[data-cform-done]', section);
	const field = (name) => form.elements.namedItem(name);
	const keys = ['name', 'phone', 'email', 'message'];
	let tried = false;

	const say = (text) => {
		if (!alertBox) return;
		const span = $('span', alertBox);
		if (span) span.textContent = text || '';
		alertBox.hidden = !text;
		if (text && !reduced()) {
			alertBox.classList.remove('is-shake');
			void alertBox.offsetWidth;
			alertBox.classList.add('is-shake');
		}
	};
	const setErr = (name, text) => {
		const input = field(name);
		const out = $(`[data-err-for="${name}"]`, form);
		if (input && input.setAttribute) {
			if (text) input.setAttribute('aria-invalid', 'true');
			else input.removeAttribute('aria-invalid');
		}
		if (out) out.textContent = text || '';
	};
	const show = (e) => keys.forEach((k) => setErr(k, e[k] || ''));
	const validate = () => {
		const e = {};
		const val = (k) => (field(k) && field(k).value ? field(k).value.trim() : '');
		if (val('name').length < 2) e.name = errs.name;
		const phone = val('phone');
		const email = val('email');
		if (phone) {
			const n = toAscii(phone).replace(/\D/g, '').length;
			if (n < 8 || n > 15) e.phone = errs.phone;
		}
		if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) e.email = errs.email;
		if (!phone && !email) {
			e.phone = errs.reach;
			e.email = errs.reach;
		}
		if (val('message').length < 10) e.message = errs.message;
		return e;
	};

	const ta = field('message');
	const cnt = $('[data-cform-count]', form);
	const max = ta ? Number(ta.getAttribute('maxlength')) || 3000 : 3000;
	const counter = () => {
		if (!ta || !cnt) return;
		const n = ta.value.length;
		cnt.textContent = n ? String(msg.count || '%1$s / %2$s').replace('%1$s', fmt(n)).replace('%2$s', fmt(max)) : '';
		cnt.classList.toggle('is-near', n > max * 0.9);
	};

	form.addEventListener('input', () => {
		counter();
		if (tried) show(validate());
	});
	form.addEventListener('focusout', () => {
		if (tried) show(validate());
	});

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		tried = true;
		const e = validate();
		show(e);
		const bad = keys.filter((k) => e[k]);
		if (bad.length) {
			say(msg.invalid || '');
			const first = field(bad[0]);
			if (first && first.focus) first.focus();
			return;
		}
		say('');
		const btn = $('button[type="submit"]', form);
		if (btn) {
			btn.classList.add('is-loading');
			btn.disabled = true;
		}
		section.classList.add('is-sending');
		try {
			const body = new FormData(form);
			body.set('ajax', '1');
			// getAttribute: the hidden <input name="action"> shadows form.action.
			const res = await fetch(form.getAttribute('action') || location.href, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
			const data = await res.json().catch(() => ({}));
			if (data && data.ok) {
				form.reset();
				counter();
				tried = false;
				show({});
				form.hidden = true;
				if (done) {
					done.hidden = false;
					done.focus({ preventScroll: true });
					done.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'center' });
				}
				return;
			}
			const server = data && data.errors ? Object.keys(data.errors) : [];
			if (server.length) {
				show(data.errors);
				const first = field(server[0]);
				if (first && first.focus) first.focus();
			}
			say((data && data.message) || msg.error || '');
		} catch {
			say(msg.error || '');
		} finally {
			if (btn) {
				btn.classList.remove('is-loading');
				btn.disabled = false;
			}
			section.classList.remove('is-sending');
		}
	});

	const again = $('[data-cform-again]', section);
	if (again && done) {
		again.addEventListener('click', (ev) => {
			ev.preventDefault();
			done.hidden = true;
			form.hidden = false;
			say('');
			const url = new URL(location.href);
			url.searchParams.delete('contact');
			url.searchParams.delete('cf');
			history.replaceState(history.state, '', url);
			const first = field('name');
			if (first && first.focus) first.focus();
		});
	}
	counter();
}

/* ------------------------------------------------------------------ */
/* Table of contents: the section being read                          */
/* ------------------------------------------------------------------ */

function setupToc() {
	const lists = $$('[data-toc]');
	if (!lists.length) return;
	const idOf = (a) => {
		try {
			return decodeURIComponent((a.getAttribute('href') || '').slice(1));
		} catch {
			return '';
		}
	};
	const links = lists.flatMap((l) => $$('a[href^="#"]', l));
	const heads = [...new Set(links.map(idOf))].map((id) => doc.getElementById(id)).filter(Boolean);
	if (!heads.length) return;
	let current = null;
	const update = () => {
		const line = Math.min(260, innerHeight * 0.3);
		let pick = '';
		heads.forEach((h) => {
			if (h.getBoundingClientRect().top - line <= 0) pick = h.id;
		});
		if (pick === current) return;
		current = pick;
		links.forEach((a) => {
			if (idOf(a) === pick) a.setAttribute('aria-current', 'true');
			else a.removeAttribute('aria-current');
		});
	};
	let ticking = false;
	addEventListener('scroll', () => {
		if (ticking) return;
		ticking = true;
		requestAnimationFrame(() => {
			ticking = false;
			update();
		});
	}, { passive: true });
	update();
	// On phones the inline table closes after a jump, so the reader lands on the text.
	lists.forEach((l) => l.addEventListener('click', (e) => {
		const box = l.closest('details');
		if (box && e.target instanceof Element && e.target.closest('a')) box.open = false;
	}));
}

function boot() {
	setupCopy();
	setupFaq();
	setupContact();
	setupToc();
}

if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();
