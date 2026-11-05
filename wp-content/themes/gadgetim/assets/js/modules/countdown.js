/**
 * Gadgetim Aurora — countdown.js: timers for REAL deadlines only (a sale's end date or the admin-set
 * campaign end). Markup comes from gadgetim_countdown() in PHP:
 *
 *   <div class="cd cd--flip|cd--chip" role="timer" data-countdown="2026-11-30T20:29:00+00:00"
 *        [data-countdown-label="…"] [data-countdown-ended="…"] aria-label="…">
 *     <span class="cd__u"><span class="cd__d" data-u="d|h|m|s">00</span><span class="cd__l">…</span></span> …
 *   </div>
 *
 * cd--flip digits turn into flip cards (top/bottom halves fold on change); cd--chip keeps dark
 * chips. Ticks are aligned to the second and stop while the timer is off-screen or the tab is
 * hidden. The accessible label updates once a minute. When the deadline passes the timer shows
 * data-countdown-ended (and gets .is-ended) or hides itself (its [data-countdown-wrap] ancestor
 * when there is one). Reduced motion: digits change without flipping.
 *
 * API: mount(scope = document) for timers added later.
 */

let ctx = null;
const doc = document;
const timers = new Set();
let ticking = false;

const flipDigit = (d) => `<span class="flip"><span class="flip__t"><i>${d}</i></span><span class="flip__b"><i>${d}</i></span><span class="flip__ft"><i>${d}</i></span><span class="flip__fb"><i>${d}</i></span></span>`;

function setFlip(el, d) {
	if (el.dataset.v === d) return;
	const old = el.dataset.v;
	el.dataset.v = d;
	const [top, bottom, ftop, fbottom] = Array.from(el.children).map((x) => x.firstElementChild);
	if (old === undefined || ctx.reduced()) {
		top.textContent = d;
		bottom.textContent = d;
		return;
	}
	top.textContent = d;
	bottom.textContent = old;
	ftop.textContent = old;
	fbottom.textContent = d;
	el.classList.remove('go');
	void el.offsetWidth;
	el.classList.add('go');
	clearTimeout(el.flipTimer);
	el.flipTimer = setTimeout(() => {
		bottom.textContent = d;
		el.classList.remove('go');
	}, 680);
}

function build(el) {
	const end = Date.parse(el.dataset.countdown || '');
	if (!Number.isFinite(end)) return null;
	const flip = el.classList.contains('cd--flip');
	const units = {};
	el.querySelectorAll('.cd__d[data-u]').forEach((d) => {
		const txt = d.textContent.trim();
		if (flip) d.innerHTML = Array.from(txt).map(flipDigit).join('');
		units[d.dataset.u] = d;
	});
	return { el, end, flip, units, visible: true, lastLabel: '' };
}

function finish(tm) {
	timers.delete(tm);
	const text = tm.el.dataset.countdownEnded;
	if (text) {
		tm.el.classList.add('is-ended');
		tm.el.textContent = text;
		tm.el.setAttribute('aria-label', text);
		return;
	}
	const wrap = tm.el.closest('[data-countdown-wrap]') || tm.el;
	wrap.hidden = true;
	ctx.emit('gtm:countdown-ended', { el: tm.el });
}

function render(tm, now) {
	let s = Math.max(0, Math.floor((tm.end - now) / 1000));
	if (s <= 0) {
		finish(tm);
		return;
	}
	const hasDays = Boolean(tm.units.d);
	const d = Math.floor(s / 86400);
	if (hasDays) s -= d * 86400;
	const h = Math.floor(s / 3600);
	const m = Math.floor((s % 3600) / 60);
	const sec = s % 60;
	const values = { d, h, m, s: sec };
	Object.entries(tm.units).forEach(([u, el]) => {
		const txt = ctx.digits(String(values[u]).padStart(2, '0'));
		if (tm.flip) {
			const cards = el.querySelectorAll('.flip');
			if (cards.length !== txt.length) el.innerHTML = Array.from(txt).map(flipDigit).join('');
			el.querySelectorAll('.flip').forEach((f, i) => setFlip(f, txt[i]));
		} else if (el.textContent !== txt) {
			el.textContent = txt;
		}
	});
	const totalH = (hasDays ? d * 24 : 0) + h;
	let label = ctx.t('timeLeft', ctx.fmt(totalH), ctx.fmt(m));
	if (tm.el.dataset.countdownLabel) label = `${tm.el.dataset.countdownLabel}: ${label}`;
	if (label !== tm.lastLabel) {
		tm.el.setAttribute('aria-label', label);
		tm.lastLabel = label;
	}
}

function tick() {
	if (!timers.size || doc.hidden) {
		ticking = false;
		return;
	}
	const now = Date.now();
	timers.forEach((tm) => { if (tm.visible) render(tm, now); });
	setTimeout(tick, 1000 - (Date.now() % 1000) + 8);
}

function start() {
	if (ticking) return;
	ticking = true;
	tick();
}

const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
	entries.forEach((en) => {
		const tm = Array.from(timers).find((x) => x.el === en.target);
		if (tm) {
			tm.visible = en.isIntersecting;
			if (tm.visible) render(tm, Date.now());
		}
	});
}, { rootMargin: '120px' }) : null;

export function mount(scope = doc) {
	scope.querySelectorAll('[data-countdown]:not([data-cd-ready])').forEach((el) => {
		el.dataset.cdReady = '1';
		const tm = build(el);
		if (!tm) return;
		timers.add(tm);
		render(tm, Date.now());
		if (io) io.observe(el);
	});
	start();
}

export function init(c) {
	ctx = c;
	mount();
	doc.addEventListener('visibilitychange', () => {
		if (!doc.hidden) start();
	});
}
