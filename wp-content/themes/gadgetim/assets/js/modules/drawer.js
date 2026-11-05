/**
 * Gadgetim Aurora — drawer.js: drawers and modals on native <dialog> (showModal: top layer,
 * focus containment, inert page, Escape). One open at a time.
 *
 * Markup:   <dialog class="drawer [drawer--end]" id="cart-drawer" aria-labelledby="…" data-drawer> … <button data-close> … </dialog>
 *           <dialog class="modal [modal--sheet]" id="…" aria-labelledby="…" data-drawer> … </dialog>
 * Triggers: any element with data-open="<dialog id>" (+ aria-controls="<id>" aria-expanded="false");
 *           app.js delegates the click and calls toggle(id, trigger).
 * Behaviour: slides in (.is-open after showModal), closes with an animation awaited through
 *           getAnimations(); backdrop click, Escape and [data-close] close; links marked
 *           [data-close-link] close without restoring focus; swipe towards the outer edge closes a drawer.
 *           Focus returns to the trigger. html.is-locked stops background scrolling.
 * Events:   document "gtm:drawer" { id, open, el, trigger }.
 * API:      open(id, trigger), close(), toggle(id, trigger), current().
 */

let ctx = null;
let openEl = null;
let lastTrigger = null;

const doc = document;
const root = doc.documentElement;

function controllers(id) {
	return Array.from(doc.querySelectorAll(`[aria-controls="${CSS.escape(id)}"]`));
}

function settle(el) {
	const anims = typeof el.getAnimations === 'function' ? el.getAnimations({ subtree: false }) : [];
	if (!anims.length || ctx.reduced()) return Promise.resolve();
	return Promise.race([
		Promise.allSettled(anims.map((a) => a.finished)),
		new Promise((r) => setTimeout(r, 650)),
	]);
}

export function current() {
	return openEl;
}

export function open(id, trigger = null) {
	const el = doc.getElementById(id);
	if (!el || typeof el.showModal !== 'function') return;
	if (openEl === el) return;
	if (openEl) close(true, false);
	lastTrigger = trigger || doc.activeElement;
	openEl = el;
	doc.querySelectorAll('#toasts .toast').forEach((x) => x.remove());
	el.showModal();
	root.classList.add('is-locked');
	controllers(id).forEach((b) => b.setAttribute('aria-expanded', 'true'));
	requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('is-open')));
	const focusTarget = el.querySelector('[autofocus]') || el.querySelector('[data-close]');
	if (focusTarget) focusTarget.focus({ preventScroll: true });
	ctx.emit('gtm:drawer', { id, open: true, el, trigger: lastTrigger });
}

export function close(instant = false, restore = true) {
	const el = openEl;
	if (!el) return;
	openEl = null;
	el.classList.remove('is-open');
	controllers(el.id).forEach((b) => b.setAttribute('aria-expanded', 'false'));
	const done = () => {
		if (!el.classList.contains('is-open') && el.open) el.close();
		if (!openEl) root.classList.remove('is-locked');
		el.style.removeProperty('transform');
	};
	if (instant) done();
	else settle(el).then(done);
	if (restore && lastTrigger && typeof lastTrigger.focus === 'function' && doc.contains(lastTrigger)) {
		lastTrigger.focus({ preventScroll: true });
	}
	ctx.emit('gtm:drawer', { id: el.id, open: false, el });
}

export function toggle(id, trigger = null) {
	if (openEl && openEl.id === id) close();
	else open(id, trigger);
}

/* Swipe towards the outer edge to close (drawers only). */
function swipe(el) {
	if (!el.classList.contains('drawer')) return;
	let sx = 0;
	let sy = 0;
	let dx = 0;
	let active = false;
	let pid = null;
	const endSide = el.classList.contains('drawer--end');
	// Closing direction in screen x: start-side drawers leave towards the inline-start edge.
	const rtl = (ctx.cfg.dir || root.dir) === 'rtl';
	const sign = (endSide ? -1 : 1) * (rtl ? 1 : -1);
	el.addEventListener('pointerdown', (e) => {
		if (e.pointerType === 'mouse' || e.target.closest('input, select, textarea, .qty')) return;
		pid = e.pointerId;
		sx = e.clientX;
		sy = e.clientY;
		dx = 0;
		active = false;
	});
	el.addEventListener('pointermove', (e) => {
		if (pid !== e.pointerId) return;
		const mx = e.clientX - sx;
		const my = e.clientY - sy;
		if (!active) {
			if (Math.abs(mx) > 12 && Math.abs(mx) > Math.abs(my) * 1.3 && mx * sign > 0) {
				active = true;
				el.style.transition = 'none';
			} else if (Math.abs(my) > 14) {
				pid = null;
				return;
			}
		}
		if (active) {
			dx = mx * sign > 0 ? mx : 0;
			el.style.transform = `translateX(${dx}px)`;
		}
	});
	const end = (e) => {
		if (pid !== e.pointerId) return;
		pid = null;
		el.style.transition = '';
		if (active && Math.abs(dx) > 80) close();
		else el.style.removeProperty('transform');
		active = false;
	};
	el.addEventListener('pointerup', end);
	el.addEventListener('pointercancel', end);
}

export function init(c) {
	ctx = c;
	doc.querySelectorAll('dialog[data-drawer]').forEach((el) => {
		el.addEventListener('cancel', (e) => {
			e.preventDefault();
			close();
		});
		el.addEventListener('click', (e) => {
			// Clicks on ::backdrop report the dialog itself as the target.
			if (e.target === el) {
				const r = el.getBoundingClientRect();
				const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
				if (!inside) close();
				return;
			}
			if (e.target.closest('[data-close]')) {
				e.preventDefault();
				close();
				return;
			}
			if (e.target.closest('[data-close-link]')) close(false, false);
		});
		swipe(el);
	});
	// A drawer left open while the layout switches (rotate, resize to desktop) stays usable;
	// the header cart opens the drawer on every width.
}
