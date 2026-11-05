/**
 * Gadgetim Aurora — motion.js: the decorative motion layer (loaded on idle on every page).
 *
 * - Reveal on scroll: [data-reveal] (+ style="--i:n" for a 60 ms stagger) gets .is-in when 6 % is
 *   visible. Elements already on screen at start are revealed at once. Rule: never put
 *   data-reveal on first-viewport content (the LCP must never start hidden). html.motion-ready
 *   switches off the 2 s CSS fallback. Reduced motion / no IntersectionObserver → everything shown.
 * - Count-up: [data-count-to="107"] counts from 0 in the language digits when revealed.
 * - Ripple: pointerdown on .btn, .add, .search__go, .tab, [data-ripple].
 * - Pointer glow: .pc__in gets --mx/--my under a fine pointer (the card's radial highlight).
 * - Parallax helper: [data-parallax="0.15"] moves by factor × scroll offset (translate3d),
 *   only while on screen, rAF-throttled; off under reduced motion.
 *
 * API: observe(scope), countUp(el), parallax(el, factor).
 */

let ctx = null;
const doc = document;
let io = null;

export function countUp(el) {
	const to = Number(el.dataset.countTo) || 0;
	if (ctx.reduced() || !to) {
		el.textContent = ctx.fmt(to);
		return;
	}
	const t0 = performance.now();
	const dur = 1300;
	const step = (t) => {
		const k = Math.min(1, Math.max(0, (t - t0) / dur));
		el.textContent = ctx.fmt(Math.round(to * (1 - (1 - k) ** 3)));
		if (k < 1) requestAnimationFrame(step);
	};
	requestAnimationFrame(step);
}

function reveal(el) {
	el.classList.add('is-in');
	if (el.matches('[data-count-to]')) countUp(el);
	el.querySelectorAll('[data-count-to]').forEach(countUp);
}

export function observe(scope = doc) {
	const els = Array.from(scope.querySelectorAll('[data-reveal]:not(.is-in)'));
	if (scope !== doc && scope.matches && scope.matches('[data-reveal]:not(.is-in)')) els.push(scope);
	if (!io || ctx.reduced()) {
		els.forEach(reveal);
		scope.querySelectorAll('[data-count-to]').forEach(countUp);
		return;
	}
	const vh = innerHeight;
	els.forEach((el) => {
		const r = el.getBoundingClientRect();
		if (r.top < vh && r.bottom > 0) reveal(el);
		else io.observe(el);
	});
	scope.querySelectorAll('[data-count-to]').forEach((el) => {
		if (!el.closest('[data-reveal]')) io.observe(el);
	});
}

/* Parallax */
const layers = new Set();
let pTick = false;
function parallaxFrame() {
	pTick = false;
	const vh = innerHeight;
	layers.forEach((l) => {
		if (!l.on) return;
		const r = l.el.getBoundingClientRect();
		const off = (r.top + r.height / 2 - vh / 2) * l.factor;
		l.el.style.transform = `translate3d(0, ${off.toFixed(1)}px, 0)`;
	});
}
export function parallax(el, factor = 0.15) {
	if (ctx.reduced()) return;
	const l = { el, factor: Number(factor) || 0.15, on: false };
	layers.add(l);
	if ('IntersectionObserver' in window) {
		new IntersectionObserver(([en]) => { l.on = en.isIntersecting; }, { rootMargin: '100px' }).observe(el);
	} else {
		l.on = true;
	}
}

export function init(c) {
	ctx = c;
	doc.documentElement.classList.add('motion-ready');
	if ('IntersectionObserver' in window) {
		io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				if (!en.isIntersecting) return;
				io.unobserve(en.target);
				if (en.target.matches('[data-reveal]')) reveal(en.target);
				else countUp(en.target);
			});
		}, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
	}
	observe();

	// Ripple
	doc.addEventListener('pointerdown', (e) => {
		const target = e.target.closest && e.target.closest('.btn, .add, .search__go, .tab, [data-ripple]');
		if (!target || ctx.reduced()) return;
		const r = target.getBoundingClientRect();
		const s = doc.createElement('span');
		s.className = 'ripple';
		s.style.left = `${e.clientX - r.left}px`;
		s.style.top = `${e.clientY - r.top}px`;
		s.style.setProperty('--rs', ((Math.max(r.width, r.height) / 12) * 2.6).toFixed(1));
		target.appendChild(s);
		s.addEventListener('animationend', () => s.remove());
	}, { passive: true });

	// Pointer glow on cards
	if (ctx.mq.hover.matches) {
		let raf = 0;
		let ev = null;
		doc.addEventListener('pointermove', (e) => {
			if (e.pointerType !== 'mouse') return;
			ev = e;
			if (raf) return;
			raf = requestAnimationFrame(() => {
				raf = 0;
				const card = ev.target.closest && ev.target.closest('.pc__in');
				if (!card) return;
				const r = card.getBoundingClientRect();
				card.style.setProperty('--mx', `${ev.clientX - r.left}px`);
				card.style.setProperty('--my', `${ev.clientY - r.top}px`);
			});
		}, { passive: true });
	}

	// Parallax layers
	doc.querySelectorAll('[data-parallax]').forEach((el) => parallax(el, el.dataset.parallax));
	if (layers.size) {
		addEventListener('scroll', () => {
			if (!pTick) {
				pTick = true;
				requestAnimationFrame(parallaxFrame);
			}
		}, { passive: true });
		parallaxFrame();
	}
}
