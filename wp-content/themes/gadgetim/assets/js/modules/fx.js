/**
 * Gadgetim Aurora — fx.js: the interactive half of the delight layer (assets/css/fx.css). app.js loads
 * it on idle on every page, next to motion.js. It only enhances: without it the CSS-only effects still
 * play and nothing is ever hidden.
 *
 * - Pausing: html.fx-hidden while the tab is hidden; .fx-off on ambient containers while off-screen.
 * - Reveals for blocks without data-reveal ([data-fx] = rise | zoom | slide | slide-end | pop, stagger
 *   --fx-i): what is already on screen is marked at once, the rest waits for the viewport, and only then
 *   does html.fx-ready switch the hidden states on (nothing blinks). .fx-done hands the element's own
 *   transitions back once it has arrived.
 * - Hero: .fx-moved after the first slide change, so the text waits for the circular wipe.
 * - Fine pointer: 3D tilt with a specular highlight on product cards, category tiles and service cards
 *   (--rx/--ry/--sx/--sy), a pointer light on promo banners (--mx/--my) and magnetic buttons (Web
 *   Animations on the `translate` property, so no page transition is overridden).
 * - Ripples on the controls motion.js does not cover; on touch, cards get a delayed ripple that is
 *   cancelled when the touch turns into a scroll. Story circles and bottom-nav items pulse when tapped.
 * - Add to cart (cart.js events): confetti + sparkles from the button ("gtm:cart-added", bigger on the
 *   product page, where the buy box rings), then a ring, a small burst and a floating "+1" on the cart
 *   once the packshot has landed ("gtm:cart").
 * - Bottom nav: the indicator stretches while it travels (.fx-morph).
 * - Where CSS scroll timelines are missing: progress bar, back-to-top ring and parallax driven here.
 * - Primary CTAs on screen get .fx-vis (their shimmer only loops then).
 * - Cross-document view transitions are skipped when the language changes.
 * Reduced motion or Save-Data: none of the above runs (the CSS is calm too).
 *
 * API: burst(x, y, count = 18, power = 1), pulse(el, className, ms).
 */

let ctx = null;
const doc = document;
const root = doc.documentElement;
const raf = (fn) => requestAnimationFrame(fn);
const clamp = (n, a, b) => Math.min(b, Math.max(a, n));
const hasIO = 'IntersectionObserver' in window;
const scrollTimelines = () => typeof CSS !== 'undefined' && typeof CSS.supports === 'function' && CSS.supports('animation-timeline: view()');

const timers = new WeakMap();
/** Adds a class for `ms` milliseconds, restarting its CSS animation when it is already there. */
export function pulse(el, cls, ms) {
	if (!el) return;
	let own = timers.get(el);
	if (!own) {
		own = {};
		timers.set(el, own);
	}
	clearTimeout(own[cls]);
	el.classList.remove(cls);
	void el.offsetWidth;
	el.classList.add(cls);
	own[cls] = setTimeout(() => el.classList.remove(cls), ms);
}

/* ------------------------------------------------------------------ */
/* Pausing                                                            */
/* ------------------------------------------------------------------ */

// Story circles count one by one: a phone shows five of twelve, the rest are scrolled out of the rail.
const OFF = '.aurora, .stories__rail > li, .hero, .services, .offers, .banners, .sect, .brands, .home-contact, .ftr, .ahero, .gal, .pdp-sec';

function pausing() {
	const sync = () => root.classList.toggle('fx-hidden', doc.hidden);
	doc.addEventListener('visibilitychange', sync);
	sync();
	if (!hasIO) return;
	const io = new IntersectionObserver((entries) => {
		entries.forEach((en) => en.target.classList.toggle('fx-off', !en.isIntersecting));
	}, { rootMargin: '160px 0px' });
	doc.querySelectorAll(OFF).forEach((el) => io.observe(el));
}

/* ------------------------------------------------------------------ */
/* Reveals for blocks without data-reveal                             */
/* ------------------------------------------------------------------ */

const SCOPES = [
	['.services__list > .svc', 'rise'],
	['.flash', 'zoom'],
	['.offers__band', 'zoom'],
	['.featured__tabs', 'rise'],
	['.marquee:not(.marquee--rev)', 'slide'],
	['.marquee--rev', 'slide-end'],
	['.ftr__bottom', 'rise'],
	['.trust > *', 'pop'],
	['.social > a', 'pop'],
];

function reveals() {
	if (!hasIO) return;
	const vh = innerHeight;
	const later = [];
	SCOPES.forEach(([sel, kind]) => {
		doc.querySelectorAll(sel).forEach((el, i) => {
			if (el.dataset.fx) return;
			el.dataset.fx = kind;
			el.style.setProperty('--fx-i', String(Math.min(i, 8)));
			const r = el.getBoundingClientRect();
			if (r.bottom > 0 && r.top < vh && r.height > 0) el.classList.add('fx-in', 'fx-done');
			else later.push(el);
		});
	});
	const arrive = (el) => {
		io.unobserve(el);
		el.classList.add('fx-in');
		const i = Number(el.style.getPropertyValue('--fx-i')) || 0;
		setTimeout(() => el.classList.add('fx-done'), 1150 + i * 70);
	};
	const io = new IntersectionObserver((entries) => {
		entries.forEach((en) => {
			if (en.isIntersecting) arrive(en.target);
		});
	}, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
	later.forEach((el) => io.observe(el));
	// The last blocks of a page (the footer's bottom row on desktop) can never cross the shrunken
	// viewport: their hidden state sits lower than the page can scroll. Reaching the end of the page
	// lets whatever is still waiting arrive.
	let ticking = false;
	const onScroll = () => {
		if (ticking) return;
		ticking = true;
		raf(() => {
			ticking = false;
			if (innerHeight + scrollY < root.scrollHeight - 4) return;
			removeEventListener('scroll', onScroll);
			later.forEach((el) => {
				if (!el.classList.contains('fx-in')) arrive(el);
			});
		});
	};
	if (later.length) addEventListener('scroll', onScroll, { passive: true });
	// Hidden states exist only from here on, after everything on screen was marked as arrived.
	root.classList.add('fx-ready');
}

/* ------------------------------------------------------------------ */
/* Hero                                                               */
/* ------------------------------------------------------------------ */

function hero() {
	const slider = doc.querySelector('[data-hero]');
	if (slider) slider.addEventListener('carousel:change', () => slider.classList.add('fx-moved'), { once: true });
}

/* ------------------------------------------------------------------ */
/* Tilt, specular highlight, banner light (fine pointer)              */
/* ------------------------------------------------------------------ */

// [selector, max X tilt, max Y tilt, measure the (untransformed) parent instead of the element]
const TILT = [
	['.pc__in', 14, 16, true],
	['.ctile', 16, 18, true],
	['.svc', 14, 16, false],
];
const TILT_SEL = TILT.map((t) => t[0]).join(', ');

function tilt() {
	let cur = null;
	let kind = null;
	let ev = null;
	let tick = 0;
	const clear = () => {
		if (!cur) return;
		cur.classList.remove('fx-tilt');
		['--rx', '--ry', '--sx', '--sy'].forEach((p) => cur.style.removeProperty(p));
		cur.style.removeProperty('will-change');
		cur = null;
	};
	const frame = () => {
		tick = 0;
		const t = ev && ev.target;
		if (!t || !t.closest) return;
		const banner = t.closest('.bnr');
		if (banner) {
			const b = banner.getBoundingClientRect();
			banner.style.setProperty('--mx', `${Math.round(ev.clientX - b.left)}px`);
			banner.style.setProperty('--my', `${Math.round(ev.clientY - b.top)}px`);
		}
		const el = t.closest(TILT_SEL);
		if (el !== cur) {
			clear();
			if (el) {
				cur = el;
				kind = TILT.find((k) => el.matches(k[0])) || TILT[0];
				el.style.willChange = 'transform';
				el.classList.add('fx-tilt');
			}
		}
		if (!el || !kind) return;
		const box = kind[3] && el.parentElement ? el.parentElement : el;
		const r = box.getBoundingClientRect();
		if (!r.width || !r.height) return;
		const x = clamp((ev.clientX - r.left) / r.width, 0, 1);
		const y = clamp((ev.clientY - r.top) / r.height, 0, 1);
		el.style.setProperty('--rx', `${((0.5 - y) * kind[1]).toFixed(2)}deg`);
		el.style.setProperty('--ry', `${((x - 0.5) * kind[2]).toFixed(2)}deg`);
		el.style.setProperty('--sx', `${(x * 100).toFixed(1)}%`);
		el.style.setProperty('--sy', `${(y * 100).toFixed(1)}%`);
	};
	doc.addEventListener('pointermove', (e) => {
		if (e.pointerType !== 'mouse') return;
		ev = e;
		if (!tick) tick = raf(frame);
	}, { passive: true });
	doc.addEventListener('mouseout', (e) => {
		if (!e.relatedTarget) clear();
	});
	addEventListener('scroll', clear, { passive: true });
	addEventListener('blur', clear);
}

/* ------------------------------------------------------------------ */
/* Magnetic buttons (fine pointer)                                    */
/* ------------------------------------------------------------------ */

const MAG = '.btn:not(.btn--block), .act, .glass-btn, .rail-btn, .rail-arrow, .to-top, .search__go, .sect__more, .bnr__cta, .social a, .add, .mega-btn, .ctile__go';
const SPRING = 'cubic-bezier(.34, 1.56, .64, 1)';

function magnet() {
	let cur = null;
	let ev = null;
	let tick = 0;
	const own = (a) => {
		a.id = 'fx-mag';
		return a;
	};
	const release = (el) => {
		const a = own(el.animate([{ translate: '0px 0px' }], { duration: 650, easing: SPRING, fill: 'forwards' }));
		a.onfinish = () => {
			if (cur === el) return;
			el.getAnimations().forEach((x) => {
				if (x.id === 'fx-mag') x.cancel();
			});
		};
	};
	const frame = () => {
		tick = 0;
		const t = ev && ev.target;
		const el = t && t.closest ? t.closest(MAG) : null;
		if (el !== cur) {
			if (cur) release(cur);
			cur = el;
		}
		if (!el || el.disabled) return;
		const r = el.getBoundingClientRect();
		// The rect already includes our own pull: measure from the element's resting centre.
		const tr = getComputedStyle(el).translate;
		const [tx = 0, ty = 0] = tr && tr !== 'none' ? tr.split(' ').map(parseFloat) : [];
		const cx = r.left + r.width / 2 - (tx || 0);
		const cy = r.top + r.height / 2 - (ty || 0);
		const max = clamp(Math.min(r.width, r.height) * 0.2, 4, 10);
		const x = clamp((ev.clientX - cx) * 0.32, -max, max);
		const y = clamp((ev.clientY - cy) * 0.32, -max, max);
		own(el.animate([{ translate: `${x.toFixed(1)}px ${y.toFixed(1)}px` }], { duration: 260, easing: 'cubic-bezier(.2, .75, .2, 1)', fill: 'forwards' }));
	};
	doc.addEventListener('pointermove', (e) => {
		if (e.pointerType !== 'mouse') return;
		ev = e;
		if (!tick) tick = raf(frame);
	}, { passive: true });
	doc.addEventListener('mouseout', (e) => {
		if (!e.relatedTarget && cur) {
			release(cur);
			cur = null;
		}
	});
}

/* ------------------------------------------------------------------ */
/* Ripples and tap pulses                                             */
/* ------------------------------------------------------------------ */

// motion.js already ripples .btn, .add, .search__go, .tab and [data-ripple].
const RIP = '.act, .icon-btn, .glass-btn, .chip-q, .rail-btn, .rail-arrow, .pager__num, .pager__arrow, .bnav__i, .mega-btn, .sect__more, .to-top, .bnr__cta, .social a, .mega__g, .drawer__links a, .acc__btn, .achip';
// Large touch targets inside scrolling areas: ripple only once the touch is clearly a tap.
const CARD = '.pc__in, .svc, .ctile, .rk, .pcx, .story__ring';

function ripple(el, x, y, color) {
	let wrap = el.querySelector(':scope > .fx-ripw');
	if (!wrap) {
		wrap = doc.createElement('span');
		wrap.className = 'fx-ripw';
		wrap.setAttribute('aria-hidden', 'true');
		el.appendChild(wrap);
	}
	const r = el.getBoundingClientRect();
	const s = doc.createElement('span');
	s.className = 'ripple';
	s.style.left = `${x - r.left}px`;
	s.style.top = `${y - r.top}px`;
	s.style.setProperty('--rs', ((Math.max(r.width, r.height) / 12) * 2.6).toFixed(1));
	if (color) s.style.color = color;
	wrap.appendChild(s);
	s.addEventListener('animationend', () => s.remove());
}

function ripples() {
	let pending = null;
	const cancel = () => {
		if (!pending) return;
		clearTimeout(pending.t);
		pending = null;
	};
	doc.addEventListener('pointerdown', (e) => {
		if (e.button !== 0 || !(e.target instanceof Element)) return;
		const t = e.target;
		const small = t.closest(RIP);
		if (small) ripple(small, e.clientX, e.clientY);
		const item = t.closest('.bnav__i');
		if (item) pulse(item, 'fx-tap', 560);
		if (e.pointerType === 'mouse') return;
		const story = t.closest('.story');
		if (story) pulse(story, 'fx-tap', 820);
		const card = small ? null : t.closest(CARD);
		if (!card) return;
		cancel();
		const x = e.clientX;
		const y = e.clientY;
		pending = { x, y, t: setTimeout(() => {
			pending = null;
			ripple(card, x, y, '#7c3aed');
		}, 90) };
	}, { passive: true });
	doc.addEventListener('pointermove', (e) => {
		if (pending && (Math.abs(e.clientX - pending.x) > 8 || Math.abs(e.clientY - pending.y) > 8)) cancel();
	}, { passive: true });
	doc.addEventListener('pointercancel', cancel, { passive: true });
}

/* ------------------------------------------------------------------ */
/* Confetti, cart celebration                                         */
/* ------------------------------------------------------------------ */

const COLORS = ['#6366f1', '#a855f7', '#ec4899', '#f59e0b', '#22d3ee', '#10b981', '#fde047', '#f43f5e'];

/** Confetti + sparkles from a viewport point (fixed layer, removed when done). */
export function burst(x, y, count = 18, power = 1) {
	if (!ctx || ctx.reduced()) return;
	const box = doc.createElement('div');
	box.className = 'fx-burst';
	box.setAttribute('aria-hidden', 'true');
	box.style.left = `${x}px`;
	box.style.top = `${y}px`;
	doc.body.appendChild(box);
	let longest = 0;
	for (let i = 0; i < count; i++) {
		const p = doc.createElement('i');
		const shape = i % 4;
		if (shape === 1) p.className = 'fx-dot';
		else if (shape === 3) p.className = 'fx-star';
		p.style.background = COLORS[(i * 3) % COLORS.length];
		box.appendChild(p);
		const angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.7;
		const dist = (40 + Math.random() * 60) * power;
		const dx = Math.cos(angle) * dist;
		const dy = Math.sin(angle) * dist - 26 * power;
		const spin = (Math.random() - 0.5) * 760;
		const dur = 850 + Math.random() * 450;
		longest = Math.max(longest, dur);
		p.animate([
			{ transform: 'translate(-50%, -50%) translate(0, 0) rotate(0deg) scale(.3)', opacity: 1 },
			{ transform: `translate(-50%, -50%) translate(${(dx * 0.72).toFixed(1)}px, ${(dy * 0.72).toFixed(1)}px) rotate(${(spin / 2).toFixed(0)}deg) scale(1)`, opacity: 1, offset: 0.32 },
			{ transform: `translate(-50%, -50%) translate(${dx.toFixed(1)}px, ${(dy + 70 * power).toFixed(1)}px) rotate(${spin.toFixed(0)}deg) scale(.55)`, opacity: 0 },
		], { duration: dur, easing: 'cubic-bezier(.22, .7, .32, 1)', fill: 'forwards' });
	}
	setTimeout(() => box.remove(), longest + 80);
}

/** The cart icon the packshot flies to (same choice as cart.js). */
function cartTarget() {
	const phone = doc.querySelector('[data-cart-target="phone"] .bnav__icw');
	if (phone && ctx.mq.phone.matches && phone.getBoundingClientRect().width) return phone;
	return doc.querySelector('.hdr [data-cart-target]');
}

function celebrate() {
	let added = 0;
	let qty = 1;
	ctx.on('gtm:cart-added', (d = {}) => {
		added = performance.now();
		qty = Number(d.qty) || 1;
		const btn = d.btn;
		if (!btn || !btn.getBoundingClientRect) return;
		const r = btn.getBoundingClientRect();
		if (!r.width) return;
		const big = btn.matches('.buy__add, .buy-add, .pbar__add');
		burst(r.left + r.width / 2, r.top + r.height / 2, big ? 30 : 18, big ? 1.5 : 1);
		const box = big ? doc.getElementById('buybox') : null;
		if (box && box.contains(btn)) pulse(box, 'fx-yay', 1050);
	});
	ctx.on('gtm:cart', () => {
		// Quantity changes in the drawer emit gtm:cart too: only celebrate a fresh add.
		if (performance.now() - added > 2500) return;
		added = 0;
		const target = cartTarget();
		if (!target) return;
		pulse(target.closest('.act--cart, .bnav__i--cart') || target, 'fx-got', 800);
		const r = target.getBoundingClientRect();
		const plus = doc.createElement('span');
		// Near the top edge (the sticky header) the "+1" drops below the icon instead of rising out of view.
		const down = r.top < 70;
		plus.className = down ? 'fx-plus fx-plus--down' : 'fx-plus';
		plus.setAttribute('aria-hidden', 'true');
		plus.dir = 'ltr';
		plus.textContent = `+${ctx.fmt(qty)}`;
		plus.style.left = `${r.left + r.width / 2}px`;
		plus.style.top = `${down ? r.bottom + 2 : r.top - 8}px`;
		doc.body.appendChild(plus);
		setTimeout(() => plus.remove(), 1200);
		burst(r.left + r.width / 2, r.top + r.height / 2, 10, 0.6);
	});
}

/* ------------------------------------------------------------------ */
/* Bottom nav, back to top, primary CTAs                              */
/* ------------------------------------------------------------------ */

function bnavMorph() {
	const nav = doc.getElementById('bnav');
	const ind = nav ? nav.querySelector('.bnav__ind') : null;
	if (!ind || !('MutationObserver' in window)) return;
	let idx = nav.style.getPropertyValue('--idx');
	new MutationObserver(() => {
		const v = nav.style.getPropertyValue('--idx');
		if (v === idx) return;
		idx = v;
		pulse(ind, 'fx-morph', 600);
	}).observe(nav, { attributes: true, attributeFilter: ['style'] });
}

function toTop() {
	const btn = doc.getElementById('to-top');
	if (btn) btn.addEventListener('click', () => pulse(btn, 'fx-launch', 720));
}

function ctaVisibility() {
	if (!hasIO) return;
	const io = new IntersectionObserver((entries) => {
		entries.forEach((en) => en.target.classList.toggle('fx-vis', en.isIntersecting));
	}, { threshold: 0.5 });
	doc.querySelectorAll('.btn:not(.btn--soft, .btn--outline, .btn--ghost, .btn--glow)').forEach((b) => io.observe(b));
}

/* ------------------------------------------------------------------ */
/* Scroll fallbacks (no CSS scroll timelines)                         */
/* ------------------------------------------------------------------ */

const PARALLAX = [['.bnr__art', 26], ['.offers__fx', 40], ['.ftr__fx', 60], ['.ctile__art', 10], ['.gal__glow', 22]];

function scrollFallbacks() {
	if (scrollTimelines()) return;
	const bar = doc.querySelector('.fx-progress');
	const ring = doc.querySelector('.to-top__ring circle');
	const aurora = doc.querySelector('.aurora');
	const layers = [];
	PARALLAX.forEach(([sel, f]) => doc.querySelectorAll(sel).forEach((el) => layers.push({ el, f, on: !hasIO })));
	if (hasIO && layers.length) {
		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				const l = layers.find((x) => x.el === en.target);
				if (l) l.on = en.isIntersecting;
			});
		}, { rootMargin: '80px 0px' });
		layers.forEach((l) => io.observe(l.el));
	}
	let tick = 0;
	const frame = () => {
		tick = 0;
		const max = root.scrollHeight - innerHeight;
		const p = max > 0 ? clamp(scrollY / max, 0, 1) : 0;
		if (bar) bar.style.transform = `scaleX(${p.toFixed(4)})`;
		if (ring) ring.style.strokeDashoffset = (100 - p * 100).toFixed(2);
		if (aurora) aurora.style.translate = `0 ${((Math.min(scrollY, 1200) / 1200) * 280).toFixed(1)}px`;
		const vh = innerHeight;
		layers.forEach((l) => {
			if (!l.on) return;
			const r = l.el.getBoundingClientRect();
			const k = clamp((r.top + r.height / 2 - vh / 2) / (vh / 2 + r.height / 2), -1, 1);
			l.el.style.translate = `0 ${(k * l.f).toFixed(1)}px`;
		});
	};
	const queue = () => {
		if (!tick) tick = raf(frame);
	};
	addEventListener('scroll', queue, { passive: true });
	addEventListener('resize', queue, { passive: true });
	frame();
}

/* ------------------------------------------------------------------ */
/* View transitions: none across a language switch                    */
/* ------------------------------------------------------------------ */

function viewTransitions() {
	const lang = (path) => (path.match(/^\/([a-z]{2})(?:\/|$)/) || [])[1] || '';
	addEventListener('pageswap', (e) => {
		if (!e.viewTransition || !e.activation || !e.activation.entry) return;
		try {
			const to = new URL(e.activation.entry.url);
			if (to.origin !== location.origin || lang(to.pathname) !== lang(location.pathname)) e.viewTransition.skipTransition();
		} catch {
			/* keep the transition */
		}
	});
}

/* ------------------------------------------------------------------ */
/* Boot                                                               */
/* ------------------------------------------------------------------ */

export function init(c) {
	ctx = c;
	viewTransitions();
	// The header intro is over by now (or was never played): drop the flag so nothing replays it.
	if (root.classList.contains('fx-intro')) setTimeout(() => root.classList.remove('fx-intro'), Math.max(0, 2200 - performance.now()));
	if (ctx.reduced()) return;
	pausing();
	reveals();
	hero();
	ripples();
	celebrate();
	bnavMorph();
	toTop();
	ctaVisibility();
	scrollFallbacks();
	if (ctx.mq.hover.matches) {
		tilt();
		magnet();
	}
}
