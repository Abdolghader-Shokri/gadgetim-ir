/**
 * Gadgetim Aurora — gallery.js: the product page module (app.js loads it when data-page="product").
 *
 * Everything here enhances server-rendered markup (content-single-product.php, templates/product/*);
 * without it the page still works: the gallery track swipes natively, thumbnails jump to their slide,
 * a tap opens the full-size file, the add-to-cart form posts normally and the sections are plain anchors.
 *
 *   gallery    [data-gallery]: active slide from an IntersectionObserver on the scroll-snap track,
 *              dots, counter, thumbnails (aria-current), prev/next, arrow keys (reading direction),
 *              hover zoom under a fine pointer, slide tap → lightbox.
 *   lightbox   #pdp-lb (<dialog data-drawer>, opened through drawer.js): own swipe track, pinch,
 *              double-tap / double-click, wheel and +/−/0 keys zoom, pan while zoomed, swipe down to
 *              close, thumbnails, focus handled by drawer.js; the page gallery follows on close.
 *   quantity   [data-qstep]: +/− with min/max/step, Persian digits over the real <input>, syncs
 *              data-quantity on every add button of this product and the WhatsApp order text.
 *   buy        upgrades the simple add-to-cart <button> to cart.js (data-add-to-cart) when the form
 *              carries nothing but the quantity; positions the fly-to-cart proxy over the visible
 *              packshot; loading state on «خرید سریع».
 *   bar        [data-pbar]: shown (< 1100 px) once the main buy row scrolled under the header; hides
 *              the bottom nav and becomes the fly-to-cart target meanwhile.
 *   nav        keeps --pdp-top = visible header bottom (follows the header tuck), scroll-spy on the
 *              chips (aria-current), smooth jumps with focus on the section.
 *   clamp      [data-clamp]: «ادامه مطلب» for long descriptions.
 *   share      Web Share API on phones, copy link (clipboard + toast), popover elsewhere.
 *   popovers   .pdp-pop: positioned under their button from 768 px (a bottom sheet on phones);
 *              a small fallback where the Popover API is missing.
 *   reviews    star picker wording, «write a review» jump.
 *   recent     localStorage gtm_recent → GET gadgetim/v1/cards (server-rendered rail cards).
 */

let ctx = null;
const doc = document;
const $ = (sel, el = doc) => el.querySelector(sel);
const $$ = (sel, el = doc) => Array.from(el.querySelectorAll(sel));
const clamp = (n, a, b) => Math.min(b, Math.max(a, n));
const isRtl = () => (ctx.cfg.dir || doc.documentElement.dir) === 'rtl';
const behavior = () => (ctx.reduced() ? 'auto' : 'smooth');

/** Scrolls a horizontal box so `el` lines up with its inline-start edge (RTL-safe: uses rects, never scrollLeft). */
function alignStart(box, el, smooth = true) {
	const b = box.getBoundingClientRect();
	const r = el.getBoundingClientRect();
	const dx = isRtl() ? r.right - b.right : r.left - b.left;
	if (Math.abs(dx) > 1) box.scrollBy({ left: dx, behavior: smooth ? behavior() : 'auto' });
}

/** Scrolls a horizontal box just enough to show `el` (no-op when already visible). */
function reveal(box, el) {
	if (!box || !el) return;
	const b = box.getBoundingClientRect();
	const r = el.getBoundingClientRect();
	let dx = 0;
	if (r.left < b.left + 4) dx = r.left - b.left - 12;
	else if (r.right > b.right - 4) dx = r.right - b.right + 12;
	if (dx) box.scrollBy({ left: dx, behavior: behavior() });
}

/* ------------------------------------------------------------------ */
/* Gallery                                                            */
/* ------------------------------------------------------------------ */

function gallery(root) {
	const gal = $('[data-gallery]', root);
	if (!gal) return;
	const plate = $('.gal__plate', gal);
	const track = $('[data-gal-track]', gal);
	const slides = $$('[data-gal-slide]', gal);
	const thumbs = $$('[data-gal-thumb]', gal);
	const thumbBox = $('.gal__thumbs', gal);
	const dots = $$('.gal__dots i', gal);
	const count = $('[data-gal-count]', gal);
	const prev = $('[data-gal-go="prev"]', gal);
	const next = $('[data-gal-go="next"]', gal);
	const expand = $('[data-lb-open-current]', gal);
	const n = slides.length;
	if (!track || !n) return;
	let cur = 0;

	// Roving tabindex: only the active slide and the active thumbnail sit in the tab order;
	// the arrow keys move between images.
	const rove = (i) => {
		slides.forEach((s, k) => $$('a, button, video', s).forEach((f) => { f.tabIndex = k === i ? 0 : -1; }));
		thumbs.forEach((t, k) => { t.tabIndex = k === i ? 0 : -1; });
	};
	const set = (i) => {
		if (i === cur && slides[i].classList.contains('is-active')) return;
		cur = i;
		slides.forEach((s, k) => s.classList.toggle('is-active', k === i));
		thumbs.forEach((t, k) => {
			t.classList.toggle('is-active', k === i);
			if (k === i) t.setAttribute('aria-current', 'true');
			else t.removeAttribute('aria-current');
		});
		rove(i);
		dots.forEach((d, k) => d.classList.toggle('is-active', k === i));
		if (count) count.textContent = `${ctx.fmt(i + 1)} / ${ctx.fmt(n)}`;
		if (prev) prev.disabled = i <= 0;
		if (next) next.disabled = i >= n - 1;
		reveal(thumbBox, thumbs[i]);
		plate.classList.remove('is-zoom');
		[i - 1, i + 1].forEach((k) => {
			const img = slides[k] && $('img[loading="lazy"]', slides[k]);
			if (img) img.loading = 'eager';
		});
	};
	const go = (i, smooth = true) => {
		const k = clamp(i, 0, n - 1);
		alignStart(track, slides[k], smooth);
		set(k);
	};

	rove(0);
	if (n > 1 && 'IntersectionObserver' in window) {
		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				if (en.isIntersecting && en.intersectionRatio >= 0.55) set(Number(en.target.dataset.galSlide));
			});
		}, { root: track, threshold: [0.6] });
		slides.forEach((s) => io.observe(s));
	}

	thumbs.forEach((t) => t.addEventListener('click', (e) => {
		e.preventDefault();
		go(Number(t.dataset.galThumb));
	}));
	if (prev) prev.addEventListener('click', () => go(cur - 1));
	if (next) next.addEventListener('click', () => go(cur + 1));

	gal.addEventListener('keydown', (e) => {
		const from = e.target.closest('.gal__open, .gal__thumb, [data-gal-go]');
		if (!from || n < 2) return;
		const fwd = isRtl() ? 'ArrowLeft' : 'ArrowRight';
		const back = isRtl() ? 'ArrowRight' : 'ArrowLeft';
		let to = null;
		if (e.key === fwd) to = cur + 1;
		else if (e.key === back) to = cur - 1;
		else if (e.key === 'Home') to = 0;
		else if (e.key === 'End') to = n - 1;
		if (to === null) return;
		e.preventDefault();
		to = clamp(to, 0, n - 1);
		go(to);
		const target = from.classList.contains('gal__thumb') ? thumbs[to] : $('.gal__open', slides[to]);
		if (target) target.focus({ preventScroll: true });
	});

	gal.addEventListener('click', (e) => {
		const link = e.target.closest('[data-lb-open]');
		if (!link || !$('#pdp-lb')) return;
		e.preventDefault();
		openLightbox(Number(link.dataset.lbOpen), link);
	});
	if (expand && $('#pdp-lb')) {
		expand.hidden = false;
		expand.addEventListener('click', () => {
			const link = $('[data-lb-open]', slides[cur]);
			openLightbox(link ? Number(link.dataset.lbOpen) : 0, expand);
		});
	}

	// Hover zoom (fine pointer): the active packshot scales around the pointer.
	if (ctx.mq.hover.matches && !ctx.reduced()) {
		let raf = 0;
		let ev = null;
		plate.addEventListener('pointermove', (e) => {
			if (e.pointerType !== 'mouse') return;
			if (!e.target.closest('.gal__open')) {
				plate.classList.remove('is-zoom');
				return;
			}
			ev = e;
			if (raf) return;
			raf = requestAnimationFrame(() => {
				raf = 0;
				const img = $('.gal__img', slides[cur]);
				if (!img) return;
				const r = slides[cur].getBoundingClientRect();
				const x = clamp((ev.clientX - r.left) / r.width, 0, 1) * 100;
				const y = clamp((ev.clientY - r.top) / r.height, 0, 1) * 100;
				img.style.transformOrigin = `${x.toFixed(1)}% ${y.toFixed(1)}%`;
				plate.classList.add('is-zoom');
			});
		});
		plate.addEventListener('pointerleave', () => plate.classList.remove('is-zoom'));
		track.addEventListener('scroll', () => plate.classList.remove('is-zoom'), { passive: true });
	}

	// The page gallery follows the lightbox when it closes.
	ctx.on('gtm:drawer', ({ id, open }) => {
		if (id !== 'pdp-lb' || open || !lb) return;
		const link = $(`[data-lb-open="${lb.index()}"]`, gal);
		const slide = link ? link.closest('[data-gal-slide]') : null;
		if (slide) go(Number(slide.dataset.galSlide), false);
	});
}

/* ------------------------------------------------------------------ */
/* Lightbox                                                           */
/* ------------------------------------------------------------------ */

let lb = null;

function openLightbox(index, trigger) {
	const el = $('#pdp-lb');
	const fallback = () => {
		if (trigger && trigger.href) location.href = trigger.href;
	};
	if (!el) {
		fallback();
		return;
	}
	ctx.load('drawer').then((drawer) => {
		if (!drawer) {
			fallback();
			return;
		}
		if (!lb) lb = lightbox(el, drawer);
		lb.show(index, trigger);
	});
}

function lightbox(el, drawer) {
	const stage = $('[data-lb-stage]', el);
	const slides = $$('[data-lb-slide]', el);
	const thumbs = $$('[data-lb-thumb]', el);
	const thumbBox = $('.lb__thumbs', el);
	const count = $('[data-lb-count]', el);
	const zin = $('[data-lb-zoom="1"]', el);
	const zout = $('[data-lb-zoom="-1"]', el);
	const prevB = $('[data-lb-go="-1"]', el);
	const nextB = $('[data-lb-go="1"]', el);
	const n = slides.length;
	const MAX = 4;
	const z = { s: 1, x: 0, y: 0 };
	let i = 0;

	const img = () => (slides[i] ? $('img', slides[i]) : null);
	const isForward = (dx) => (isRtl() ? dx > 0 : dx < 0);

	const apply = (animate = true) => {
		const im = img();
		el.classList.toggle('is-gesture', !animate);
		if (im) im.style.transform = z.s > 1.001 ? `translate3d(${z.x.toFixed(1)}px, ${z.y.toFixed(1)}px, 0) scale(${z.s.toFixed(3)})` : '';
		el.classList.toggle('is-zoomed', z.s > 1.001);
		if (zout) zout.disabled = z.s <= 1.001;
		if (zin) zin.disabled = z.s >= MAX - 0.01;
	};
	const clampPan = () => {
		const im = img();
		if (!im) return;
		const fr = slides[i].getBoundingClientRect();
		const mx = Math.max(0, (im.offsetWidth * z.s - fr.width) / 2);
		const my = Math.max(0, (im.offsetHeight * z.s - fr.height) / 2);
		z.x = clamp(z.x, -mx, mx);
		z.y = clamp(z.y, -my, my);
	};
	/** Zooms to scale s keeping the image point under (cx, cy) in place. */
	const zoomTo = (s, cx, cy, animate = true) => {
		if (!img()) return;
		const r = slides[i].getBoundingClientRect();
		const ox = r.left + r.width / 2;
		const oy = r.top + r.height / 2;
		const px = (cx ?? ox) - ox;
		const py = (cy ?? oy) - oy;
		const next = clamp(s, 1, MAX);
		const k = next / z.s;
		z.x = px - (px - z.x) * k;
		z.y = py - (py - z.y) * k;
		z.s = next;
		if (z.s <= 1.001) {
			z.s = 1;
			z.x = 0;
			z.y = 0;
		}
		clampPan();
		apply(animate);
	};
	const reset = (animate = false) => {
		z.s = 1;
		z.x = 0;
		z.y = 0;
		apply(animate);
	};

	const go = (k, animate = true) => {
		const to = clamp(k, 0, n - 1);
		if (to !== i) reset(false);
		i = to;
		if (!animate) el.classList.add('is-dragging');
		el.style.setProperty('--lb-i', String(i));
		slides.forEach((s, j) => {
			s.classList.toggle('is-active', j === i);
			s.setAttribute('aria-hidden', String(j !== i));
		});
		thumbs.forEach((t, j) => {
			t.classList.toggle('is-active', j === i);
			if (j === i) t.setAttribute('aria-current', 'true');
			else t.removeAttribute('aria-current');
		});
		if (count) count.textContent = `${ctx.fmt(i + 1)} / ${ctx.fmt(n)}`;
		if (prevB) prevB.disabled = i <= 0;
		if (nextB) nextB.disabled = i >= n - 1;
		reveal(thumbBox, thumbs[i]);
		[i - 1, i + 1].forEach((j) => {
			const im = slides[j] ? $('img', slides[j]) : null;
			if (im && im.loading === 'lazy') im.loading = 'eager';
		});
		if (!animate) requestAnimationFrame(() => requestAnimationFrame(() => el.classList.remove('is-dragging')));
		apply(false);
	};

	/* Pointer gestures on the stage (touch-action: none). */
	const pts = new Map();
	let g = null;
	let lastTap = { t: 0, x: 0, y: 0 };
	const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y) || 1;
	const startOne = (x, y, moved = false) => ({ type: z.s > 1.001 ? 'pan' : 'swipe', sx: x, sy: y, x0: z.x, y0: z.y, t0: performance.now(), moved, axis: moved ? 'x' : '' });

	stage.addEventListener('pointerdown', (e) => {
		if ((e.pointerType === 'mouse' && e.button !== 0) || e.target.closest('button')) return;
		try { stage.setPointerCapture(e.pointerId); } catch { /* released already */ }
		pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
		if (pts.size === 2) {
			const [a, b] = [...pts.values()];
			g = { type: 'pinch', d0: dist(a, b), s0: z.s, x0: z.x, y0: z.y, mx: (a.x + b.x) / 2, my: (a.y + b.y) / 2 };
			el.classList.add('is-gesture');
			el.style.setProperty('--lb-dx', '0px');
			el.style.setProperty('--lb-dy', '0px');
		} else if (pts.size === 1) {
			g = startOne(e.clientX, e.clientY);
		}
	});
	stage.addEventListener('pointermove', (e) => {
		if (!pts.has(e.pointerId) || !g) return;
		pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
		if (g.type === 'pinch') {
			if (pts.size < 2) return;
			const [a, b] = [...pts.values()];
			const r = slides[i].getBoundingClientRect();
			const px = g.mx - (r.left + r.width / 2);
			const py = g.my - (r.top + r.height / 2);
			const s = clamp((g.s0 * dist(a, b)) / g.d0, 1, MAX);
			const k = s / g.s0;
			z.s = s;
			z.x = px - (px - g.x0) * k + ((a.x + b.x) / 2 - g.mx);
			z.y = py - (py - g.y0) * k + ((a.y + b.y) / 2 - g.my);
			clampPan();
			apply(false);
			return;
		}
		const dx = e.clientX - g.sx;
		const dy = e.clientY - g.sy;
		if (!g.moved && Math.hypot(dx, dy) > 7) {
			g.moved = true;
			g.axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
		}
		if (!g.moved) return;
		if (g.type === 'pan') {
			z.x = g.x0 + dx;
			z.y = g.y0 + dy;
			clampPan();
			apply(false);
			return;
		}
		el.classList.add('is-dragging');
		if (g.axis === 'x') {
			const edge = (i === 0 && !isForward(dx)) || (i === n - 1 && isForward(dx));
			el.style.setProperty('--lb-dx', `${(edge ? dx / 3 : dx).toFixed(1)}px`);
		} else if (dy > 0) {
			el.style.setProperty('--lb-dy', `${dy.toFixed(1)}px`);
		}
	});
	const end = (e) => {
		if (!pts.has(e.pointerId)) return;
		pts.delete(e.pointerId);
		if (!g) return;
		if (g.type === 'pinch') {
			if (pts.size === 1) {
				const [p] = [...pts.values()];
				g = startOne(p.x, p.y, true);
			} else {
				g = null;
				if (z.s < 1.08) reset(true);
				else apply(true);
			}
			return;
		}
		if (pts.size > 0) return;
		const dx = e.clientX - g.sx;
		const dy = e.clientY - g.sy;
		const fast = performance.now() - g.t0 < 260;
		el.classList.remove('is-dragging', 'is-gesture');
		if (g.type === 'swipe' && g.moved) {
			el.style.setProperty('--lb-dx', '0px');
			el.style.setProperty('--lb-dy', '0px');
			if (g.axis === 'x' && (Math.abs(dx) > 60 || (fast && Math.abs(dx) > 24))) go(isForward(dx) ? i + 1 : i - 1);
			else if (g.axis === 'y' && (dy > 110 || (fast && dy > 50))) drawer.close();
		} else if (!g.moved && e.type === 'pointerup') {
			const now = performance.now();
			if (now - lastTap.t < 320 && Math.hypot(e.clientX - lastTap.x, e.clientY - lastTap.y) < 32) {
				lastTap = { t: 0, x: 0, y: 0 };
				if (z.s > 1.001) reset(true);
				else zoomTo(2.5, e.clientX, e.clientY);
			} else {
				lastTap = { t: now, x: e.clientX, y: e.clientY };
			}
		}
		g = null;
	};
	stage.addEventListener('pointerup', end);
	stage.addEventListener('pointercancel', end);
	stage.addEventListener('wheel', (e) => {
		e.preventDefault();
		zoomTo(z.s * Math.exp(-e.deltaY * (e.ctrlKey ? 0.012 : 0.0025)), e.clientX, e.clientY, false);
	}, { passive: false });
	stage.addEventListener('dragstart', (e) => e.preventDefault());

	el.addEventListener('click', (e) => {
		const zb = e.target.closest('[data-lb-zoom]');
		if (zb) {
			const up = Number(zb.dataset.lbZoom) > 0;
			zoomTo(up ? z.s * 1.6 : (z.s / 1.6 < 1.15 ? 1 : z.s / 1.6));
			return;
		}
		const gb = e.target.closest('[data-lb-go]');
		if (gb) {
			go(i + Number(gb.dataset.lbGo));
			return;
		}
		const tb = e.target.closest('[data-lb-thumb]');
		if (tb) go(Number(tb.dataset.lbThumb));
	});
	el.addEventListener('keydown', (e) => {
		const fwd = isRtl() ? 'ArrowLeft' : 'ArrowRight';
		const back = isRtl() ? 'ArrowRight' : 'ArrowLeft';
		let used = true;
		if (e.key === fwd) go(i + 1);
		else if (e.key === back) go(i - 1);
		else if (e.key === 'Home') go(0);
		else if (e.key === 'End') go(n - 1);
		else if (e.key === '+' || e.key === '=') zoomTo(z.s * 1.6);
		else if (e.key === '-' || e.key === '_') zoomTo(z.s / 1.6 < 1.15 ? 1 : z.s / 1.6);
		else if (e.key === '0') reset(true);
		else used = false;
		if (used) e.preventDefault();
	});
	addEventListener('resize', () => {
		if (el.open) reset(false);
	}, { passive: true });
	ctx.on('gtm:drawer', ({ id, open }) => {
		if (id === 'pdp-lb' && !open) {
			reset(false);
			el.style.setProperty('--lb-dx', '0px');
			el.style.setProperty('--lb-dy', '0px');
		}
	});

	return {
		show(k, trigger) {
			slides.forEach((s) => {
				const im = $('img', s);
				if (im && Math.abs(Number(s.dataset.lbSlide) - k) <= 1) im.loading = 'eager';
			});
			drawer.open('pdp-lb', trigger);
			go(k, false);
		},
		index: () => i,
	};
}

/* ------------------------------------------------------------------ */
/* Quantity stepper                                                   */
/* ------------------------------------------------------------------ */

function quantity(root) {
	const form = $('form[data-buy-form]', root);
	const input = form ? $('input[name="quantity"]', form) : null;
	const step = $('[data-qstep]', root);
	const id = root.dataset.pdp;
	if (step && (!input || input.type === 'hidden')) step.hidden = true;
	const wa = $$('[data-wa]', root);
	const min = input ? Number(input.min) || 1 : 1;
	const max = input && input.max ? Number(input.max) || Infinity : Infinity;
	const stepN = input ? Number(input.step) || 1 : 1;
	const val = step ? $('[data-qstep-val]', step) : null;
	const plus = step ? $('[data-step="1"]', step) : null;
	const minus = step ? $('[data-step="-1"]', step) : null;

	const read = () => {
		const v = parseFloat(String(input ? input.value : '').replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
		return Number.isFinite(v) ? v : min;
	};
	const sync = (bump = false) => {
		const q = clamp(Math.round(read() / stepN) * stepN, min, max);
		if (input && doc.activeElement !== input && String(q) !== input.value) input.value = String(q);
		if (val) {
			val.textContent = ctx.fmt(q);
			if (bump && !ctx.reduced()) {
				val.classList.remove('bump');
				void val.offsetWidth;
				val.classList.add('bump');
			}
		}
		if (plus) plus.disabled = q >= max;
		if (minus) minus.disabled = q <= min;
		$$(`[data-add-to-cart="${CSS.escape(String(id))}"]`).forEach((a) => { a.dataset.quantity = String(q); });
		wa.forEach((a) => { a.href = a.dataset.wa.replace('__QTY__', encodeURIComponent(ctx.digits(String(q)))); });
		return q;
	};
	root.pdpQty = () => sync();
	if (!input) return;
	if (step) {
		step.addEventListener('click', (e) => {
			const b = e.target.closest('[data-step]');
			if (!b || b.disabled) return;
			input.value = String(clamp(read() + Number(b.dataset.step) * stepN, min, max));
			input.dispatchEvent(new Event('change', { bubbles: true }));
			sync(true);
		});
	}
	input.addEventListener('input', () => sync());
	input.addEventListener('change', () => sync());
	input.addEventListener('blur', () => sync());
	sync();
}

/* ------------------------------------------------------------------ */
/* Buy box: AJAX add, fly-to-cart source, quick buy                   */
/* ------------------------------------------------------------------ */

function buy(root) {
	const form = $('form[data-buy-form]', root);
	const add = form ? $('[data-buy-add]', form) : null;
	if (form && add && ctx.cfg.wcAjax) {
		// Only the quantity and the product id: anything else (add-ons, gift wrap…) keeps the normal POST.
		const simple = Array.from(form.elements).every((f) => !f.name || f.name === 'quantity' || f.name === 'add-to-cart');
		if (simple) {
			add.dataset.addToCart = add.value;
			add.dataset.quantity = add.dataset.quantity || '1';
			// app.js navigates to button.href when cart.js cannot load: give the button a real URL.
			Object.defineProperty(add, 'href', {
				configurable: true,
				get: () => `${form.action}${form.action.includes('?') ? '&' : '?'}add-to-cart=${encodeURIComponent(add.value)}&quantity=${encodeURIComponent(add.dataset.quantity || '1')}`,
			});
			if (root.pdpQty) root.pdpQty();
		}
	}

	// cart.js flies the first <img> of the button's [data-product-id] box: aim that proxy at the
	// packshot the shopper is looking at (or the button when the gallery is off screen).
	const box = $('#buybox', root);
	const proxy = box ? $('.buy__fly', box) : null;
	if (box && proxy) {
		box.addEventListener('click', (e) => {
			const btn = e.target.closest('[data-add-to-cart]');
			if (!btn) return;
			const pic = $('.gal__slide.is-active .gal__img', root) || $('.gal__img', root);
			let r = pic ? pic.getBoundingClientRect() : null;
			const visible = r && r.width > 0 && r.bottom > 60 && r.top < innerHeight - 60;
			if (!visible) r = btn.getBoundingClientRect();
			const size = Math.min(r.width, r.height);
			proxy.style.cssText = `inset:auto;left:${(r.left + (r.width - size) / 2).toFixed(1)}px;top:${(r.top + (r.height - size) / 2).toFixed(1)}px;width:${size.toFixed(1)}px;height:${size.toFixed(1)}px`;
			if (pic) proxy.src = pic.currentSrc || pic.src;
		}, true);
	}

	if (form) {
		form.addEventListener('submit', (e) => {
			const by = e.submitter;
			if (by && by.matches('[data-buy-quick]')) by.classList.add('is-loading');
		});
		addEventListener('pageshow', () => $$('[data-buy-quick].is-loading', form).forEach((b) => b.classList.remove('is-loading')));
	}
}

/* ------------------------------------------------------------------ */
/* Sticky buy bar                                                     */
/* ------------------------------------------------------------------ */

function stickyBar(root) {
	const bar = $('[data-pbar]', root);
	const anchor = $('[data-buy-anchor]', root) || $('#buybox', root);
	if (!bar || !anchor) return;
	const bnav = $('#bnav');
	const cart = $('[data-pbar-cart]', bar);
	const mq = matchMedia('(max-width: 1099.98px)');
	let on = false;
	// Desktop: the mini buy box beside the sections only shows once the main buy box has left the screen,
	// so both are never visible together (it keeps its place: opacity/translate only, no layout shift).
	const mini = $('.pdp-mini', root);
	if (mini) mini.classList.add('is-managed');
	let miniOn = null;
	const setMini = (v) => {
		if (!mini || v === miniOn) return;
		miniOn = v;
		mini.classList.toggle('is-on', v);
		mini.inert = !v;
	};
	let tick = false;
	const set = (v) => {
		if (v === on) return;
		on = v;
		bar.classList.toggle('is-on', v);
		bar.inert = !v;
		if (bnav) bnav.classList.toggle('is-hidden', v);
		// cart.js aims at the first [data-cart-target="phone"] .bnav__icw: the bar's (it comes before the bottom nav).
		if (cart) {
			if (v) cart.setAttribute('data-cart-target', 'phone');
			else cart.removeAttribute('data-cart-target');
		}
	};
	// Rect check on scroll (rAF): unlike an IntersectionObserver it also notices jumps that skip the
	// anchor entirely (scrollTo(0), #anchor links, the back-to-top button).
	const update = () => {
		tick = false;
		const gone = anchor.getBoundingClientRect().bottom < pdpTop + 8;
		set(mq.matches && gone);
		setMini(!mq.matches && gone);
	};
	const queue = () => {
		if (!tick) {
			tick = true;
			requestAnimationFrame(update);
		}
	};
	addEventListener('scroll', queue, { passive: true });
	addEventListener('resize', queue, { passive: true });
	mq.addEventListener('change', queue);
	update();
}

/* ------------------------------------------------------------------ */
/* Header offset, in-page chips (scroll-spy)                           */
/* ------------------------------------------------------------------ */

let pdpTop = 0;

function headerOffset(root) {
	const hdr = $('#hdr');
	if (!hdr) return;
	const sync = () => {
		let h = hdr.offsetHeight;
		if (hdr.classList.contains('is-tucked')) {
			if (ctx.mq.phone.matches) h -= parseFloat(hdr.style.getPropertyValue('--row1')) || 0;
			else if (ctx.mq.desk.matches) {
				const row = $('.hdr__nav', hdr);
				h -= row ? row.offsetHeight : 0;
			}
		}
		pdpTop = Math.max(0, Math.round(h));
		root.style.setProperty('--pdp-top', `${pdpTop}px`);
	};
	sync();
	if ('MutationObserver' in window) new MutationObserver(sync).observe(hdr, { attributes: true, attributeFilter: ['class'] });
	addEventListener('resize', sync, { passive: true });
}

function nav(root) {
	const el = $('[data-pdp-nav]', root);
	if (!el) return;
	const list = $('.pdp-nav__list', el);
	const links = $$('a[data-spy]', el);
	const targets = links.map((a) => doc.getElementById(a.dataset.spy));
	let active = -1;
	let tick = false;
	const spy = () => {
		tick = false;
		const navBox = el.getBoundingClientRect();
		const line = navBox.bottom + 28;
		let cur = -1;
		targets.forEach((t, k) => {
			if (t && !t.hidden && t.getBoundingClientRect().top <= line) cur = k;
		});
		if (cur !== active) {
			if (links[active]) links[active].removeAttribute('aria-current');
			active = cur;
			if (links[cur]) {
				links[cur].setAttribute('aria-current', 'true');
				reveal(list, links[cur]);
			}
		}
		el.classList.toggle('is-stuck', navBox.top <= pdpTop + 14);
	};
	addEventListener('scroll', () => {
		if (!tick) {
			tick = true;
			requestAnimationFrame(spy);
		}
	}, { passive: true });
	spy();
	links.forEach((a, k) => a.addEventListener('click', (e) => {
		const t = targets[k];
		if (!t || t.hidden) return;
		e.preventDefault();
		t.scrollIntoView({ behavior: behavior(), block: 'start' });
		history.replaceState(null, '', `#${a.dataset.spy}`);
		t.focus({ preventScroll: true });
	}));
}

/* ------------------------------------------------------------------ */
/* Description «ادامه مطلب»                                           */
/* ------------------------------------------------------------------ */

function clampText(root) {
	$$('[data-clamp]', root).forEach((box) => {
		const body = $('[data-clamp-body]', box);
		const btn = $('[data-clamp-btn]', box);
		if (!body || !btn) return;
		const label = $('[data-clamp-label]', btn);
		const limit = parseFloat(getComputedStyle(box).getPropertyValue('--clamp')) || 460;
		if (body.scrollHeight <= limit * 1.35) return;
		box.classList.add('is-clamped');
		btn.hidden = false;
		btn.addEventListener('click', () => {
			const open = !box.classList.contains('is-open');
			box.classList.toggle('is-open', open);
			btn.setAttribute('aria-expanded', String(open));
			if (label) label.textContent = ctx.t(open ? 'pdpLess' : 'pdpMore');
			if (!open && box.getBoundingClientRect().top < pdpTop) box.scrollIntoView({ block: 'start', behavior: behavior() });
		});
	});
}

/* ------------------------------------------------------------------ */
/* Share                                                              */
/* ------------------------------------------------------------------ */

/** Copies text: the async Clipboard API on secure origins, a hidden textarea otherwise. */
async function copyText(text) {
	if (navigator.clipboard && window.isSecureContext) {
		await navigator.clipboard.writeText(text);
		return;
	}
	const area = doc.createElement('textarea');
	area.value = text;
	area.setAttribute('readonly', '');
	area.style.cssText = 'position:fixed;inset:auto;top:0;left:0;opacity:0;pointer-events:none';
	doc.body.appendChild(area);
	area.select();
	const ok = doc.execCommand && doc.execCommand('copy');
	area.remove();
	if (!ok) throw new Error('copy');
}

function share(root) {
	const btn = $('[data-share]', root);
	const copy = $('[data-copy]', root);
	if (copy) {
		copy.hidden = false;
		copy.addEventListener('click', async () => {
			const pop = copy.closest('[popover]');
			try {
				await copyText(copy.dataset.copy);
				ctx.toast({ title: ctx.t('pdpCopied'), icon: 'check', type: 'success', duration: 2400 });
			} catch {
				ctx.toast({ title: ctx.t('pdpCopyFail'), type: 'error' });
			}
			if (pop && typeof pop.hidePopover === 'function' && pop.matches(':popover-open')) pop.hidePopover();
		});
	}
	if (btn && typeof navigator.share === 'function' && ctx.mq.phone.matches) {
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			navigator.share({ title: btn.dataset.shareTitle, url: btn.dataset.shareUrl }).catch(() => { /* dismissed */ });
		});
	}
}

/* ------------------------------------------------------------------ */
/* Popovers                                                           */
/* ------------------------------------------------------------------ */

function popovers(root) {
	const pops = $$('.pdp-pop', root);
	if (!pops.length) return;
	const native = typeof HTMLElement.prototype.showPopover === 'function';
	let open = null;
	let raf = 0;
	const invoker = (p) => $(`[popovertarget="${CSS.escape(p.id)}"]`, root);
	const place = (p) => {
		if (ctx.mq.phone.matches) {
			p.style.removeProperty('left');
			p.style.removeProperty('top');
			return;
		}
		const b = invoker(p);
		if (!b) return;
		const r = b.getBoundingClientRect();
		const w = p.offsetWidth || Math.min(340, innerWidth - 24);
		const h = p.offsetHeight || 160;
		const left = clamp(isRtl() ? r.right - w : r.left, 12, Math.max(12, innerWidth - w - 12));
		let top = r.bottom + 10;
		if (top + h > innerHeight - 12 && r.top - h - 10 > 12) top = r.top - h - 10;
		p.style.left = `${Math.round(left)}px`;
		p.style.top = `${Math.round(clamp(top, 12, Math.max(12, innerHeight - h - 12)))}px`;
	};
	const follow = () => {
		if (!open || raf) return;
		raf = requestAnimationFrame(() => {
			raf = 0;
			if (open) place(open);
		});
	};
	addEventListener('scroll', follow, { passive: true });
	addEventListener('resize', follow, { passive: true });

	if (native) {
		pops.forEach((p) => {
			p.addEventListener('beforetoggle', (e) => {
				if (e.newState === 'open') place(p);
			});
			p.addEventListener('toggle', (e) => {
				if (e.newState === 'open') {
					open = p;
					place(p);
				} else if (open === p) {
					open = null;
				}
			});
		});
		return;
	}

	// Fallback without the Popover API: toggle .is-open, Escape and outside clicks close.
	const close = () => {
		if (!open) return;
		open.classList.remove('is-open');
		const b = invoker(open);
		if (b) b.setAttribute('aria-expanded', 'false');
		open = null;
	};
	$$('[popovertarget]', root).forEach((b) => b.setAttribute('aria-expanded', 'false'));
	doc.addEventListener('click', (e) => {
		const b = e.target.closest && e.target.closest('[popovertarget]');
		if (b && root.contains(b)) {
			const p = doc.getElementById(b.getAttribute('popovertarget'));
			if (!p) return;
			e.preventDefault();
			const was = open === p;
			close();
			if (!was) {
				p.classList.add('is-open');
				b.setAttribute('aria-expanded', 'true');
				open = p;
				place(p);
			}
			return;
		}
		if (open && !open.contains(e.target)) close();
	});
	doc.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && open) {
			const b = invoker(open);
			close();
			if (b) b.focus();
		}
	});
}

/* ------------------------------------------------------------------ */
/* Reviews                                                            */
/* ------------------------------------------------------------------ */

function reviews(root) {
	const pick = $('[data-rate-pick]', root);
	if (pick) {
		const word = $('[data-rate-word]', pick);
		const idle = word ? word.textContent : '';
		const show = (v) => {
			if (word) word.textContent = v ? ctx.t(`pdpRate${v}`) : idle;
		};
		pick.addEventListener('change', (e) => {
			if (e.target.name === 'rating') show(e.target.value);
		});
		if (ctx.mq.hover.matches) {
			pick.addEventListener('pointerover', (e) => {
				const label = e.target.closest('.rate-pick__star');
				if (label) show(label.htmlFor.replace('rating-', ''));
			});
			pick.addEventListener('pointerleave', () => {
				const c = $('input[name="rating"]:checked', pick);
				show(c ? c.value : '');
			});
		}
	}
	$$('[data-review-go]', root).forEach((a) => a.addEventListener('click', (e) => {
		const form = $('#review_form_wrapper', root);
		if (!form) return;
		e.preventDefault();
		form.scrollIntoView({ behavior: behavior(), block: 'start' });
		const first = $('.rate-pick__in', form) || $('#comment', form);
		if (first) setTimeout(() => first.focus({ preventScroll: true }), ctx.reduced() ? 0 : 500);
	}));
}

/* ------------------------------------------------------------------ */
/* Recently viewed                                                    */
/* ------------------------------------------------------------------ */

function recent(root) {
	const id = Number(root.dataset.pdp);
	const pdp = ctx.cfg.pdp || {};
	const key = pdp.recentKey || 'gtm_recent';
	const seen = (ctx.store.get(key, []) || []).map(Number).filter((x) => x > 0 && x !== id);
	if (id > 0) ctx.store.set(key, [id, ...seen].slice(0, 12));
	const sec = $('[data-pdp-recent]', root);
	const rail = sec ? $('[data-recent-rail]', sec) : null;
	if (!sec || !rail || !pdp.cards || !seen.length) return;
	const ids = seen.slice(0, 8);
	let done = false;
	const fill = async () => {
		if (done) return;
		done = true;
		try {
			const url = `${pdp.cards}${pdp.cards.includes('?') ? '&' : '?'}ids=${ids.join(',')}&lang=${encodeURIComponent(ctx.cfg.lang || 'fa')}`;
			const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
			const data = res.ok ? await res.json() : null;
			if (!data || !data.count || !data.html) {
				sec.hidden = true;
				return;
			}
			rail.innerHTML = data.html;
			rail.setAttribute('aria-busy', 'false');
			const wish = new Set(ctx.badges.wish());
			$$('[data-wish]', rail).forEach((b) => b.setAttribute('aria-pressed', String(wish.has(Number(b.dataset.wish)))));
			ctx.load('carousel').then((m) => m && m.railState && m.railState(rail));
			ctx.load('motion').then((m) => m && m.observe && m.observe(sec));
		} catch {
			sec.hidden = true;
		}
	};
	sec.hidden = false;
	if ('IntersectionObserver' in window) {
		const io = new IntersectionObserver(([en]) => {
			if (!en.isIntersecting) return;
			io.disconnect();
			fill();
		}, { rootMargin: '600px 0px' });
		io.observe(sec);
	} else {
		fill();
	}
}

/* ------------------------------------------------------------------ */
/* Boot                                                               */
/* ------------------------------------------------------------------ */

export function init(c) {
	ctx = c;
	const root = $('[data-pdp]');
	if (!root) return;
	const steps = [headerOffset, gallery, quantity, buy, stickyBar, nav, clampText, share, popovers, reviews, recent];
	steps.forEach((fn) => {
		try {
			fn(root);
		} catch (err) {
			console.error('[gadgetim] gallery', fn.name, err);
		}
	});
}

export { openLightbox };
