/**
 * Gadgetim Aurora — carousel.js: accessible carousels and scroll rails.
 *
 * 1. Stacked carousels (hero slider, instant offer):
 *    <div class="carousel carousel--fade" data-carousel [data-autoplay="6500"] [data-pointer-parallax]
 *         role="region" aria-roledescription="carousel" aria-label="…">
 *      <div class="carousel__track">
 *        <div data-slide class="is-active" role="group" aria-roledescription="slide" aria-label="1 / 4">…</div>
 *        <div data-slide role="group" aria-roledescription="slide" aria-label="2 / 4" aria-hidden="true" inert>…</div>
 *      </div>
 *      <div class="dots" data-carousel-dots></div>          (filled with progress dots)
 *      <button data-carousel-prev> <button data-carousel-next> <button class="glass-btn" data-carousel-pause aria-pressed="false">
 *    </div>
 *    Slides swap with .is-active (CSS animates; [data-in] children can stagger). Autoplay advances when
 *    the active dot's progress bar ends (CSS var --dur = data-autoplay ms); it pauses on hover, focus,
 *    off-screen, hidden tab and the pause button; reduced motion → no autoplay. Swipe/drag changes
 *    slides (RTL aware). Arrow keys on the controls. Pointer parallax sets --px/--py (−1…1).
 *    Event "carousel:change" { index, slide } on the root.
 *
 * 2. Rails: <ul class="rail prail" id="newest-rail"> … plus buttons [data-rail="newest-rail"][data-dir="prev|next"]
 *    (gadgetim_rail_nav()). Buttons scroll by ~86 % of the width, disable at the edges; the rail gets
 *    .is-scrollable/.at-start/.at-end for the edge fade. Desktop mouse drag scrolls. Optional
 *    data-rail-autoplay="5000" advances one page at a time (loops), paused on hover/focus/off-screen.
 *
 * API: mount(scope = document) for content added later; railState(rail).
 */

let ctx = null;
const doc = document;
const isRtl = () => (ctx.cfg.dir || doc.documentElement.dir) === 'rtl';

/* ------------------------------------------------------------------ */
/* Stacked carousel                                                   */
/* ------------------------------------------------------------------ */

function carousel(el) {
	const slides = Array.from(el.querySelectorAll('[data-slide]'));
	if (slides.length < 2) return;
	const dotsEl = el.querySelector('[data-carousel-dots]');
	const pauseBtn = el.querySelector('[data-carousel-pause]');
	const ms = Number(el.dataset.autoplay) || 0;
	const auto = ms > 0 && !ctx.reduced();
	let cur = Math.max(0, slides.findIndex((s) => s.classList.contains('is-active')));
	let userPaused = false;
	let hover = false;
	let focus = false;
	let visible = true;
	let timer = 0;

	el.style.setProperty('--dur', `${ms || 6500}ms`);
	if (!auto) {
		el.classList.add('no-auto');
		if (pauseBtn) pauseBtn.hidden = true;
	}
	slides.forEach((s, i) => {
		s.classList.toggle('is-active', i === cur);
		if (i !== cur) {
			s.setAttribute('aria-hidden', 'true');
			s.inert = true;
		}
	});

	let dots = [];
	if (dotsEl) {
		dotsEl.setAttribute('role', 'group');
		dotsEl.innerHTML = slides.map((_, i) => `<button class="dot${i === cur ? ' is-active' : ''}" type="button" aria-label="${ctx.esc(ctx.t('slideOf', ctx.fmt(i + 1), ctx.fmt(slides.length)))}"${i === cur ? ' aria-current="true"' : ''}><span class="dot__bar"></span></button>`).join('');
		dots = Array.from(dotsEl.querySelectorAll('.dot'));
	}

	const covered = () => !!(ctx.overlayOpen && ctx.overlayOpen());
	const paused = () => userPaused || hover || focus || !visible || doc.hidden || covered();
	const sync = () => {
		el.classList.toggle('is-paused', paused());
		if (!dots.length && auto) {
			clearTimeout(timer);
			if (!paused()) timer = setTimeout(() => go(cur + 1, 1), ms);
		}
	};

	function go(n, dir = 1) {
		n = (n + slides.length) % slides.length;
		if (n === cur) return;
		const prev = slides[cur];
		const next = slides[n];
		next.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
		el.style.setProperty('--dir-x', String(dir));
		prev.classList.remove('is-active');
		prev.setAttribute('aria-hidden', 'true');
		prev.inert = true;
		next.classList.add('is-active');
		next.removeAttribute('aria-hidden');
		next.inert = false;
		if (dots.length) {
			dots[cur].classList.remove('is-active');
			dots[cur].removeAttribute('aria-current');
			dots[n].classList.add('is-active');
			dots[n].setAttribute('aria-current', 'true');
		}
		cur = n;
		// Warm up the following slide's images.
		slides[(n + 1) % slides.length].querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
		el.dispatchEvent(new CustomEvent('carousel:change', { detail: { index: n, slide: next } }));
		sync();
	}
	el.carousel = { go: (n) => go(n, n > cur ? 1 : -1), next: () => go(cur + 1, 1), prev: () => go(cur - 1, -1), get index() { return cur; } };

	if (dotsEl) {
		dotsEl.addEventListener('click', (e) => {
			const d = e.target.closest('.dot');
			if (!d) return;
			const i = dots.indexOf(d);
			go(i, i > cur ? 1 : -1);
		});
	}
	el.querySelectorAll('[data-carousel-next]').forEach((b) => b.addEventListener('click', () => go(cur + 1, 1)));
	el.querySelectorAll('[data-carousel-prev]').forEach((b) => b.addEventListener('click', () => go(cur - 1, -1)));
	if (pauseBtn) {
		pauseBtn.addEventListener('click', () => {
			userPaused = !userPaused;
			pauseBtn.setAttribute('aria-pressed', String(userPaused));
			pauseBtn.setAttribute('aria-label', userPaused ? ctx.t('play') : ctx.t('pause'));
			sync();
		});
	}
	if (auto && dots.length) {
		el.addEventListener('animationend', (e) => {
			if (e.target.classList.contains('dot__bar') && e.target.closest('.dot.is-active')) go(cur + 1, 1);
		});
	}
	el.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') { hover = true; sync(); } });
	el.addEventListener('focusin', () => { focus = true; sync(); });
	el.addEventListener('focusout', (e) => { if (!el.contains(e.relatedTarget)) { focus = false; sync(); } });
	ctx.on('gtm:overlay', sync); // Drawers, dialogs, mega menu, live search.
	doc.addEventListener('visibilitychange', sync);
	if ('IntersectionObserver' in window) {
		new IntersectionObserver(([en]) => { visible = en.isIntersecting; sync(); }, { threshold: 0.25 }).observe(el);
	}
	el.addEventListener('keydown', (e) => {
		if (!e.target.closest('.dot, [data-carousel-prev], [data-carousel-next], [data-carousel-pause]')) return;
		const fwd = isRtl() ? 'ArrowLeft' : 'ArrowRight';
		const back = isRtl() ? 'ArrowRight' : 'ArrowLeft';
		if (e.key === fwd) { go(cur + 1, 1); e.preventDefault(); }
		if (e.key === back) { go(cur - 1, -1); e.preventDefault(); }
	});

	// Swipe: in RTL dragging to the right reveals the next slide.
	let pid = null;
	let sx = 0;
	let sy = 0;
	let dx = 0;
	let dragging = false;
	let moved = false;
	let raf = 0;
	el.addEventListener('pointerdown', (e) => {
		if (e.button !== 0 || e.target.closest('.dots, [data-carousel-prev], [data-carousel-next], [data-carousel-pause]')) return;
		pid = e.pointerId;
		sx = e.clientX;
		sy = e.clientY;
		dx = 0;
		dragging = false;
		moved = false;
	});
	el.addEventListener('pointermove', (e) => {
		if ('pointerParallax' in el.dataset && e.pointerType === 'mouse' && !ctx.reduced()) {
			const r = el.getBoundingClientRect();
			const px = ((e.clientX - r.left) / r.width - 0.5) * 2;
			const py = ((e.clientY - r.top) / r.height - 0.5) * 2;
			el.classList.add('is-tracking');
			if (!raf) raf = requestAnimationFrame(() => { raf = 0; el.style.setProperty('--px', px.toFixed(3)); el.style.setProperty('--py', py.toFixed(3)); });
		}
		if (pid !== e.pointerId) return;
		const mx = e.clientX - sx;
		const my = e.clientY - sy;
		if (!dragging) {
			if (Math.abs(mx) > 8 && Math.abs(mx) > Math.abs(my) * 1.2) {
				dragging = true;
				moved = true;
				try { el.setPointerCapture(pid); } catch { /* released */ }
				el.classList.add('is-dragging');
			} else if (Math.abs(my) > 12) {
				pid = null;
			}
		}
		if (dragging) {
			dx = mx;
			el.style.setProperty('--drag', `${dx}px`);
		}
	});
	const end = (e) => {
		if (pid !== e.pointerId) return;
		if (dragging) {
			const forward = isRtl() ? dx > 0 : dx < 0;
			if (Math.abs(dx) > 48) go(forward ? cur + 1 : cur - 1, forward ? 1 : -1);
			el.style.setProperty('--drag', '0px');
			el.classList.remove('is-dragging');
		}
		pid = null;
		dragging = false;
	};
	el.addEventListener('pointerup', end);
	el.addEventListener('pointercancel', end);
	el.addEventListener('pointerleave', (e) => {
		if (e.pointerType !== 'mouse') return;
		hover = false;
		el.classList.remove('is-tracking');
		el.style.setProperty('--px', '0');
		el.style.setProperty('--py', '0');
		sync();
	});
	el.addEventListener('click', (e) => {
		if (moved) {
			e.preventDefault();
			e.stopPropagation();
			moved = false;
		}
	}, true);
	el.addEventListener('dragstart', (e) => e.preventDefault());
	sync();
}

/* ------------------------------------------------------------------ */
/* Rails                                                              */
/* ------------------------------------------------------------------ */

export function railState(rail) {
	if (!rail) return;
	const max = rail.scrollWidth - rail.clientWidth;
	const pos = Math.abs(rail.scrollLeft);
	const atStart = pos <= 2;
	const atEnd = pos >= max - 2;
	rail.classList.toggle('is-scrollable', max > 2);
	rail.classList.toggle('at-start', atStart);
	rail.classList.toggle('at-end', atEnd);
	if (rail.id) {
		doc.querySelectorAll(`[data-rail="${CSS.escape(rail.id)}"]`).forEach((b) => {
			b.disabled = max <= 2 || (b.dataset.dir === 'prev' ? atStart : atEnd);
		});
	}
}

function page(rail, dir) {
	const step = rail.clientWidth * 0.86 * (dir === 'next' ? 1 : -1) * (isRtl() ? -1 : 1);
	rail.scrollBy({ left: step, behavior: ctx.reduced() ? 'auto' : 'smooth' });
}

function rail(el) {
	let t = 0;
	el.addEventListener('scroll', () => {
		if (t) return;
		t = requestAnimationFrame(() => { t = 0; railState(el); });
	}, { passive: true });
	railState(el);

	// Desktop: drag with the mouse; a drag never triggers the link under the pointer.
	let down = false;
	let sx = 0;
	let sl = 0;
	let moved = false;
	el.addEventListener('pointerdown', (e) => {
		if (e.pointerType !== 'mouse' || e.button !== 0) return;
		down = true;
		moved = false;
		sx = e.clientX;
		sl = el.scrollLeft;
	});
	el.addEventListener('pointermove', (e) => {
		if (!down) return;
		const dx = e.clientX - sx;
		if (!moved && Math.abs(dx) > 6) {
			moved = true;
			el.classList.add('is-dragging');
			try { el.setPointerCapture(e.pointerId); } catch { /* released */ }
		}
		if (moved) el.scrollLeft = sl - dx;
	});
	const up = () => {
		if (!down) return;
		down = false;
		if (moved) {
			el.classList.remove('is-dragging');
			setTimeout(() => { moved = false; }, 0);
		}
	};
	el.addEventListener('pointerup', up);
	el.addEventListener('pointercancel', up);
	el.addEventListener('click', (e) => {
		if (moved) {
			e.preventDefault();
			e.stopPropagation();
		}
	}, true);

	const ms = Number(el.dataset.railAutoplay) || 0;
	if (ms > 0 && !ctx.reduced()) {
		let hold = false;
		let visible = false;
		el.addEventListener('pointerenter', () => { hold = true; });
		el.addEventListener('pointerleave', () => { hold = false; });
		el.addEventListener('focusin', () => { hold = true; });
		el.addEventListener('focusout', () => { hold = false; });
		if ('IntersectionObserver' in window) new IntersectionObserver(([en]) => { visible = en.isIntersecting; }, { threshold: 0.4 }).observe(el);
		setInterval(() => {
			if (hold || !visible || doc.hidden || (ctx.overlayOpen && ctx.overlayOpen()) || !el.classList.contains('is-scrollable')) return;
			if (el.classList.contains('at-end')) el.scrollTo({ left: 0, behavior: 'smooth' });
			else page(el, 'next');
		}, ms);
	}
}

export function mount(scope = doc) {
	scope.querySelectorAll('[data-carousel]:not([data-cr-ready])').forEach((el) => {
		el.dataset.crReady = '1';
		carousel(el);
	});
	scope.querySelectorAll('.rail[id]:not([data-cr-ready]), [data-rail-target]:not([data-cr-ready])').forEach((el) => {
		el.dataset.crReady = '1';
		rail(el);
	});
}

export function init(c) {
	ctx = c;
	doc.addEventListener('click', (e) => {
		const b = e.target.closest && e.target.closest('[data-rail]');
		if (!b) return;
		const el = doc.getElementById(b.dataset.rail);
		if (el) page(el, b.dataset.dir);
	});
	let rt = 0;
	addEventListener('resize', () => {
		clearTimeout(rt);
		rt = setTimeout(() => doc.querySelectorAll('.rail[id]').forEach(railState), 120);
	}, { passive: true });
	mount();
	if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(() => doc.querySelectorAll('.rail[id]').forEach(railState));
}
