/**
 * Gadgetim Aurora — home.js: front-page enhancements. Registered by inc/home.php (import map
 * "@gadgetim/home") and printed as a module script; it boots itself through app.js's loader so
 * init(ctx) runs exactly once. Everything it touches is already rendered on the server.
 *
 * - Hero depth: --sy (0…1 over the first 700 px of scroll) for the outline word and the packshots;
 *   decorative loops pause while the slider is off-screen (.is-off). Off under reduced motion.
 * - Instant offer: the top progress bar restarts in step with carousel.js (dots appear / carousel:change).
 * - Services: the icon strokes draw in once the strip is seen (.is-drawn).
 * - Brand marquees pause off-screen (.is-off); hover/focus pauses are CSS.
 * - Featured tabs: server-rendered fragments (?gtm_home=tab&group=ID) fetched on selection, prefetched
 *   on hover/focus, cached; skeletons while loading; staggered card pop; hearts re-synced; a failed
 *   fetch opens the category page instead.
 * - Story rings turn grey for a day after their category was opened (localStorage gtm_seen_stories).
 */

let ctx = null;
const doc = document;
const SEEN_KEY = 'gtm_seen_stories';
const DAY = 864e5;

function heroDepth() {
	const slider = doc.querySelector('[data-hero]');
	if (!slider) return;
	let visible = true;
	if ('IntersectionObserver' in window) {
		new IntersectionObserver(([en]) => {
			visible = en.isIntersecting;
			slider.classList.toggle('is-off', !visible);
		}).observe(slider);
	}
	if (ctx.reduced()) return;
	let raf = 0;
	const update = () => {
		raf = 0;
		if (!visible) return;
		const y = Math.min(1, Math.max(0, scrollY / 700));
		slider.style.setProperty('--sy', y.toFixed(3));
	};
	addEventListener('scroll', () => {
		if (!raf) raf = requestAnimationFrame(update);
	}, { passive: true });
	update();
}

function flashBar() {
	const flash = doc.querySelector('[data-flash]');
	const bar = flash && flash.querySelector('.flash__bar i');
	if (!bar || !flash.hasAttribute('data-carousel')) return;
	const restart = () => {
		if (ctx.reduced() || flash.classList.contains('no-auto')) return;
		flash.classList.remove('is-running');
		void bar.offsetWidth;
		flash.classList.add('is-running');
	};
	flash.addEventListener('carousel:change', restart);
	// carousel.js builds the dots (and starts their progress) when it mounts: start together.
	const dots = flash.querySelector('[data-carousel-dots]');
	if (dots && dots.children.length) restart();
	else if (dots) {
		const mo = new MutationObserver(() => {
			if (dots.children.length) {
				mo.disconnect();
				restart();
			}
		});
		mo.observe(dots, { childList: true });
	}
}

function draw() {
	const list = doc.querySelector('[data-services]');
	if (!list) return;
	if (ctx.reduced() || !('IntersectionObserver' in window)) {
		list.classList.add('is-drawn');
		return;
	}
	const io = new IntersectionObserver(([en]) => {
		if (!en.isIntersecting) return;
		io.disconnect();
		list.classList.add('is-drawn');
	}, { threshold: 0.3 });
	io.observe(list);
}

function marquees() {
	const rows = doc.querySelectorAll('[data-marquee]');
	if (!rows.length || !('IntersectionObserver' in window)) return;
	const io = new IntersectionObserver((entries) => {
		entries.forEach((en) => en.target.classList.toggle('is-off', !en.isIntersecting));
	}, { rootMargin: '80px 0px' });
	rows.forEach((row) => io.observe(row));
}

function syncHearts(scope) {
	const ids = new Set((ctx.badges && ctx.badges.wish ? ctx.badges.wish() : []).map(Number));
	scope.querySelectorAll('[data-wish]').forEach((b) => b.setAttribute('aria-pressed', String(ids.has(Number(b.dataset.wish)))));
}

function featured() {
	const panel = doc.querySelector('[data-featured]');
	const list = doc.querySelector('[data-featured-tabs]');
	const grid = panel && panel.querySelector('.featured__grid');
	if (!panel || !list || !grid) return;
	const skeleton = doc.getElementById('featured-sk');
	const cache = new Map([['0', grid.innerHTML]]);
	const pending = new Map();
	let current = '0';
	let token = 0;

	const fetchTab = (tab) => {
		const key = tab.dataset.group;
		if (cache.has(key)) return Promise.resolve(cache.get(key));
		if (pending.has(key)) return pending.get(key);
		const src = tab.dataset.src;
		if (!src) return Promise.reject(new Error('no source'));
		const p = fetch(src, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
			.then((res) => {
				if (!res.ok) throw new Error(`HTTP ${res.status}`);
				return res.text();
			})
			.then((html) => {
				if (!html.trim()) throw new Error('empty');
				cache.set(key, html);
				pending.delete(key);
				return html;
			})
			.catch((err) => {
				pending.delete(key);
				throw err;
			});
		pending.set(key, p);
		return p;
	};

	const show = (html) => {
		grid.innerHTML = html;
		grid.querySelectorAll(':scope > .pc').forEach((card, i) => {
			card.style.setProperty('--i', String(i % 10));
			card.classList.remove('pop');
			if (!ctx.reduced()) card.classList.add('pop');
			card.removeAttribute('data-reveal');
		});
		syncHearts(grid);
	};

	ctx.on('gtm:tab', ({ tab, list: tablist }) => {
		if (tablist !== list || !tab) return;
		const key = tab.dataset.group;
		if (key === current) return;
		current = key;
		const mine = ++token;
		panel.setAttribute('aria-labelledby', tab.id);
		if (!cache.has(key)) {
			panel.setAttribute('aria-busy', 'true');
			if (skeleton) grid.innerHTML = skeleton.innerHTML;
		}
		fetchTab(tab)
			.then((html) => {
				if (mine !== token) return;
				show(html);
			})
			.catch(() => {
				if (mine === token && tab.dataset.href) location.href = tab.dataset.href;
			})
			.finally(() => {
				if (mine === token) panel.removeAttribute('aria-busy');
			});
	});

	// Prefetch on intent so a click usually swaps instantly.
	const warm = (e) => {
		const tab = e.target.closest && e.target.closest('[role="tab"][data-src]');
		if (tab) fetchTab(tab).catch(() => {});
	};
	list.addEventListener('pointerover', warm, { passive: true });
	list.addEventListener('focusin', warm);
}

function stories() {
	const rail = doc.querySelector('[data-stories]');
	if (!rail) return;
	const now = Date.now();
	const seen = ctx.store.get(SEEN_KEY, {}) || {};
	Object.keys(seen).forEach((id) => {
		if (!(now - Number(seen[id]) < DAY)) delete seen[id];
	});
	rail.querySelectorAll('[data-story]').forEach((a) => {
		if (seen[a.dataset.story]) a.classList.add('is-seen');
	});
	rail.addEventListener('click', (e) => {
		const hot = e.target.closest('a[href^="#"]');
		if (hot) {
			// «شگفت‌انگیزها»: glide to the offers band and move focus there.
			const target = doc.getElementById(hot.getAttribute('href').slice(1));
			if (target) {
				e.preventDefault();
				target.scrollIntoView({ behavior: ctx.reduced() ? 'auto' : 'smooth', block: 'start' });
				if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
				target.focus({ preventScroll: true });
			}
			return;
		}
		const a = e.target.closest('[data-story]');
		if (!a) return;
		seen[a.dataset.story] = Date.now();
		ctx.store.set(SEEN_KEY, seen);
	});
}

export function init(c) {
	ctx = c;
	heroDepth();
	flashBar();
	draw();
	marquees();
	featured();
	stories();
}

// Self-boot through the theme loader (app.js may run before or after this module).
const boot = () => {
	if (window.gadgetim && typeof window.gadgetim.load === 'function') window.gadgetim.load('home');
};
if (window.gadgetim) boot();
else doc.addEventListener('DOMContentLoaded', boot, { once: true });
