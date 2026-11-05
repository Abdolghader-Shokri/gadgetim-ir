/**
 * Gadgetim Aurora — toast.js: toast notifications in #toasts (role="status", polite).
 * Bottom-centre above the bottom nav on phones, bottom inline-end on wider screens; at most three;
 * auto-dismiss with a gradient progress line, paused on hover and focus; errors use role="alert".
 *
 * API: show({ title, text, img, icon, type: 'info'|'success'|'error', action: { label, href | open }, duration }) → element
 *      clear()
 * The action with open="<dialog id>" opens that drawer (data-open, delegated by app.js).
 */

let ctx = null;
const doc = document;

export function clear() {
	doc.querySelectorAll('#toasts .toast').forEach((el) => el.remove());
}

export function show(opts = {}) {
	const wrap = doc.getElementById('toasts');
	if (!wrap) return null;
	const { esc } = ctx;
	const type = opts.type || (opts.img ? 'success' : 'info');
	const el = doc.createElement('div');
	el.className = `toast toast--${type}${opts.img ? '' : ' toast--plain'}`;
	if (type === 'error') el.setAttribute('role', 'alert');
	const icon = opts.icon || (type === 'error' ? 'alert' : 'check');
	const media = opts.img
		? `<img src="${esc(opts.img)}" alt="" width="46" height="46"><span class="toast__ok" aria-hidden="true"><svg class="ic" aria-hidden="true"><use href="#i-check"></use></svg></span>`
		: `<span class="toast__ic" aria-hidden="true"><svg class="ic" aria-hidden="true"><use href="#i-${esc(icon)}"></use></svg></span>`;
	let action = '';
	if (opts.action && opts.action.label) {
		action = opts.action.href
			? `<a class="toast__btn" href="${esc(opts.action.href)}">${esc(opts.action.label)}</a>`
			: `<button class="toast__btn" type="button" data-open="${esc(opts.action.open || '')}" aria-controls="${esc(opts.action.open || '')}">${esc(opts.action.label)}</button>`;
	}
	el.innerHTML = `${media}<div><b>${esc(opts.title || '')}</b>${opts.text ? `<span class="toast__t">${esc(opts.text)}</span>` : ''}</div>${action || '<span></span>'}`;
	const duration = Math.max(1500, Number(opts.duration) || 3600);
	el.style.setProperty('--tdur', `${duration}ms`);
	wrap.appendChild(el);
	while (wrap.children.length > 3) wrap.firstElementChild.remove();

	let left = duration;
	let started = Date.now();
	let timer = setTimeout(kill, left);
	function kill() {
		el.classList.add('is-out');
		setTimeout(() => el.remove(), 360);
	}
	const pause = () => {
		clearTimeout(timer);
		left -= Date.now() - started;
		el.style.setProperty('--tps', 'paused');
	};
	const resume = () => {
		started = Date.now();
		clearTimeout(timer);
		timer = setTimeout(kill, Math.max(left, 800));
		el.style.removeProperty('--tps');
	};
	el.addEventListener('pointerenter', pause);
	el.addEventListener('pointerleave', resume);
	el.addEventListener('focusin', pause);
	el.addEventListener('focusout', resume);
	el.addEventListener('click', (e) => {
		if (e.target.closest('.toast__btn')) setTimeout(kill, 50);
	});
	return el;
}

export function init(c) {
	ctx = c;
}
