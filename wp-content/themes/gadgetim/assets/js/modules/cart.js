/**
 * Gadgetim Aurora — cart.js: add to cart, the cart drawer and the wishlist drawer.
 *
 * Add to cart: any element with data-add-to-cart="<product id>" [data-quantity="1"] (cards render a
 *   real ?add-to-cart link, so it works without JS). POST ?wc-ajax=add_to_cart (WooCommerce's own
 *   endpoint, response fragments ignored), then: the button morphs to a check (.is-done), the
 *   packshot flies along an arc to the cart (bottom nav on phones, header elsewhere), the badge
 *   bumps (count re-read from the gtm_cart_count cookie the plugin writes), the cart icon wiggles
 *   and a toast offers «مشاهده سبد». Variable products answer with product_url → navigate there.
 * Cart drawer (#cart-drawer): filled on open from the Store API (GET wc/store/v1/cart) — only when
 *   a cart exists (cookie > 0 or woocommerce_items_in_cart), so guests without a cart cost zero
 *   requests. Quantity +/− and remove use POST cart/update-item | cart/remove-item with the Nonce
 *   and Cart-Token headers from the last response.
 * Wishlist drawer (#wish-drawer): ids from localStorage (app.js) → GET wc/store/v1/products?include=…
 *
 * API: add(button), refresh(), renderWish().  Events: emits "gtm:cart-added" { btn, id, qty } the moment
 * the server confirms (fx.js), then "gtm:cart" { count } once the packshot has landed.
 */

let ctx = null;
const doc = document;
let nonce = '';
let cartToken = '';
let cache = null;
let busy = false;

const decoder = doc.createElement('textarea');
const decode = (s) => {
	decoder.innerHTML = String(s ?? '');
	return decoder.value;
};

const hasCart = () => ctx.badges.cart() > 0 || /(?:^|; )woocommerce_items_in_cart=1/.test(doc.cookie);

function money(amount, minor = 0) {
	const v = Number(amount || 0) / 10 ** Number(minor || 0);
	return `${ctx.fmt(v)} <small>${ctx.esc(ctx.cfg.currency ? ctx.cfg.currency.unit : '')}</small>`;
}

async function api(path, opts = {}) {
	const headers = { Accept: 'application/json', 'X-Gadgetim-Lang': ctx.cfg.lang || 'fa', ...(opts.headers || {}) };
	if (opts.body) headers['Content-Type'] = 'application/json';
	if (nonce) headers.Nonce = nonce;
	if (cartToken) headers['Cart-Token'] = cartToken;
	const res = await fetch(ctx.cfg.store + path, {
		method: opts.method || 'GET',
		credentials: 'same-origin',
		headers,
		body: opts.body ? JSON.stringify(opts.body) : undefined,
	});
	nonce = res.headers.get('Nonce') || res.headers.get('X-WC-Store-API-Nonce') || nonce;
	cartToken = res.headers.get('Cart-Token') || cartToken;
	const data = await res.json().catch(() => null);
	if (!res.ok) throw Object.assign(new Error((data && data.message) || `HTTP ${res.status}`), { data });
	return data;
}

/* ------------------------------------------------------------------ */
/* Fly to cart                                                        */
/* ------------------------------------------------------------------ */

function flyTarget() {
	const phone = doc.querySelector('[data-cart-target="phone"] .bnav__icw');
	if (phone && ctx.mq.phone.matches && phone.getBoundingClientRect().width) return phone;
	return doc.querySelector('.hdr [data-cart-target]');
}

function fly(img) {
	return new Promise((resolve) => {
		const target = flyTarget();
		if (!img || !target || ctx.reduced() || !img.getBoundingClientRect().width) {
			resolve();
			return;
		}
		const a = img.getBoundingClientRect();
		const b = target.getBoundingClientRect();
		const size = Math.min(a.width, a.height, 150);
		const box = doc.createElement('div');
		box.className = 'fly';
		box.style.cssText = `left:${a.left + a.width / 2 - size / 2}px;top:${a.top + a.height / 2 - size / 2}px;width:${size}px;height:${size}px`;
		const pic = doc.createElement('img');
		pic.src = img.currentSrc || img.src;
		pic.alt = '';
		box.appendChild(pic);
		doc.body.appendChild(box);
		const dx = b.left + b.width / 2 - (a.left + a.width / 2);
		const dy = b.top + b.height / 2 - (a.top + a.height / 2);
		const lift = Math.min(dy, 0) - 90;
		const dur = 820;
		box.animate([{ transform: 'translateX(0)' }, { transform: `translateX(${dx}px)` }], { duration: dur, easing: 'cubic-bezier(.5,0,.5,1)', fill: 'forwards' });
		const anim = pic.animate([
			{ transform: 'translateY(0) scale(1) rotate(0deg)', opacity: 1 },
			{ transform: `translateY(${lift}px) scale(.62) rotate(-10deg)`, opacity: 1, offset: 0.38 },
			{ transform: `translateY(${dy}px) scale(.14) rotate(-28deg)`, opacity: 0.5 },
		], { duration: dur, easing: 'cubic-bezier(.33,0,.67,1)', fill: 'forwards' });
		anim.onfinish = () => {
			box.remove();
			resolve();
		};
		anim.oncancel = anim.onfinish;
	});
}

function wiggle() {
	doc.querySelectorAll('.act--cart').forEach((el) => {
		el.classList.remove('wiggle');
		void el.offsetWidth;
		el.classList.add('wiggle');
	});
}

/* ------------------------------------------------------------------ */
/* Add to cart                                                        */
/* ------------------------------------------------------------------ */

export async function add(btn) {
	if (!btn || btn.classList.contains('is-busy')) return;
	const id = btn.dataset.addToCart;
	const qty = btn.dataset.quantity || '1';
	const endpoint = (ctx.cfg.wcAjax || '').replace('%%endpoint%%', 'add_to_cart');
	if (!endpoint) {
		location.href = btn.href;
		return;
	}
	const holder = btn.closest('.pc, .pcx, .rk, [data-product-id], .citem');
	const img = holder ? holder.querySelector('img') : null;
	const titleEl = holder ? holder.querySelector('.pc__title, .pcx__t, .rk__t, .citem__t, [data-title]') : null;
	const title = titleEl ? titleEl.textContent.trim() : '';
	const before = ctx.badges.cart();
	btn.classList.add('is-busy');
	btn.setAttribute('aria-busy', 'true');
	try {
		const body = new URLSearchParams({ product_id: id, quantity: qty });
		const res = await fetch(endpoint, { method: 'POST', body, credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' } });
		const data = await res.json().catch(() => null);
		if (!data || data.error) {
			if (data && data.product_url) {
				location.href = data.product_url;
				return;
			}
			throw new Error('add_to_cart');
		}
		cache = null;
		btn.classList.add('is-done');
		clearTimeout(btn.doneTimer);
		btn.doneTimer = setTimeout(() => btn.classList.remove('is-done'), 1700);
		// fx.js celebrates (confetti from the button, "+1" on the cart once the packshot lands).
		ctx.emit('gtm:cart-added', { btn, id: Number(id), qty: Number(qty) || 1 });
		const flight = fly(img);
		ctx.toast({
			title: ctx.t('added'),
			text: title,
			img: img ? img.currentSrc || img.src : '',
			action: doc.getElementById('cart-drawer') ? { label: ctx.t('viewCart'), open: 'cart-drawer' } : { label: ctx.t('viewCart'), href: ctx.cfg.cart },
		});
		await flight;
		let count = ctx.badges.update('cart');
		if (count <= before) {
			// Without the plugin's cookie the badge still moves: count the item locally until reload.
			count = before + Number(qty);
			doc.querySelectorAll('[data-cart-count]').forEach((b) => {
				b.textContent = ctx.fmt(count);
				b.classList.remove('is-empty');
			});
		}
		wiggle();
		ctx.emit('gtm:cart', { count });
		const drawer = doc.getElementById('cart-drawer');
		if (drawer && drawer.open) refresh();
	} catch {
		ctx.toast({ title: ctx.t('error'), type: 'error' });
	} finally {
		btn.classList.remove('is-busy');
		btn.removeAttribute('aria-busy');
	}
}

/* ------------------------------------------------------------------ */
/* Cart drawer                                                        */
/* ------------------------------------------------------------------ */

const $d = (sel) => doc.querySelector(`#cart-drawer ${sel}`);

function renderEmpty() {
	const body = $d('[data-cart-body]');
	const tpl = doc.getElementById('cart-empty-tpl');
	if (body && tpl) body.replaceChildren(tpl.content.cloneNode(true));
	const foot = $d('[data-cart-foot]');
	if (foot) foot.hidden = true;
	const count = $d('[data-cart-drawer-count]');
	if (count) count.textContent = '';
}

function renderSkeleton() {
	const body = $d('[data-cart-body]');
	if (!body) return;
	body.innerHTML = Array.from({ length: 3 }, () => '<div class="citem" aria-hidden="true"><i class="sk" style="width:68px;height:68px;border-radius:14px"></i><div><i class="sk sk-line" style="margin-top:6px"></i><i class="sk sk-line" style="width:60%;margin-top:10px"></i><i class="sk sk-line" style="width:40%;height:26px;margin-top:12px"></i></div></div>').join('');
}

function renderCart(cart) {
	const body = $d('[data-cart-body]');
	const foot = $d('[data-cart-foot]');
	if (!body) return;
	const items = (cart && cart.items) || [];
	if (!items.length) {
		renderEmpty();
		return;
	}
	const { esc, t } = ctx;
	const minor = (cart.totals && cart.totals.currency_minor_unit) || 0;
	let saved = 0;
	body.innerHTML = `<ul>${items.map((it) => {
		const name = decode(it.name);
		const img = it.images && it.images[0] ? it.images[0].thumbnail : '';
		const p = it.prices || {};
		const m = p.currency_minor_unit || 0;
		saved += Math.max(0, Number(p.regular_price || 0) - Number(p.price || 0)) * it.quantity / 10 ** m;
		const max = it.quantity_limits ? it.quantity_limits.maximum : 99;
		return `<li class="citem" data-key="${esc(it.key)}">
	${img ? `<img src="${esc(img)}" alt="" width="68" height="68" loading="lazy">` : '<span></span>'}
	<div><a class="citem__t" href="${esc(it.permalink)}" data-close-link>${esc(name)}</a>
	<div class="citem__row"><div class="qty" role="group" aria-label="${esc(name)}">
		<button type="button" data-qty="1" aria-label="${esc(t('increase', name))}"${it.quantity >= max ? ' disabled' : ''}><svg class="ic" aria-hidden="true"><use href="#i-plus"></use></svg></button>
		<output aria-live="polite">${ctx.fmt(it.quantity)}</output>
		<button type="button" data-qty="-1" aria-label="${esc(it.quantity > 1 ? t('decrease', name) : t('removeItem', name))}"><svg class="ic" aria-hidden="true"><use href="#i-${it.quantity > 1 ? 'minus' : 'trash'}"></use></svg></button>
	</div><span class="citem__price">${money(it.totals ? it.totals.line_total : 0, it.totals ? it.totals.currency_minor_unit : m)}</span></div></div>
</li>`;
	}).join('')}</ul>`;
	const n = cart.items_count || items.reduce((s, x) => s + x.quantity, 0);
	const count = $d('[data-cart-drawer-count]');
	if (count) count.textContent = `(${t('items', ctx.fmt(n))})`;
	const total = $d('[data-cart-total]');
	if (total) total.innerHTML = money(cart.totals ? cart.totals.total_items : 0, minor);
	const save = $d('[data-cart-save]');
	if (save) {
		save.hidden = saved <= 0;
		save.innerHTML = saved > 0 ? `<svg class="ic" aria-hidden="true"><use href="#i-sparkle"></use></svg>${esc(t('saved', `${ctx.fmt(saved)} ${ctx.cfg.currency ? ctx.cfg.currency.unit : ''}`))}` : '';
	}
	if (foot) foot.hidden = false;
}

/** Re-fetches the cart into the drawer (skeleton while loading; empty state when there is no cart). */
export async function refresh() {
	if (!hasCart()) {
		cache = null;
		renderEmpty();
		return;
	}
	if (cache) renderCart(cache);
	else renderSkeleton();
	try {
		cache = await api('cart');
		renderCart(cache);
		ctx.badges.update('none');
	} catch {
		const body = $d('[data-cart-body]');
		if (body) body.innerHTML = `<div class="cart-empty"><b>${ctx.esc(ctx.t('error'))}</b></div>`;
	}
}

async function changeQty(btn) {
	if (busy) return;
	const li = btn.closest('.citem');
	const key = li && li.dataset.key;
	const item = cache && cache.items ? cache.items.find((x) => x.key === key) : null;
	if (!item) return;
	const next = item.quantity + Number(btn.dataset.qty);
	busy = true;
	li.classList.add('is-busy');
	try {
		cache = next > 0
			? await api('cart/update-item', { method: 'POST', body: { key, quantity: next } })
			: await api('cart/remove-item', { method: 'POST', body: { key } });
		renderCart(cache);
		const count = ctx.badges.update('none');
		ctx.emit('gtm:cart', { count });
		const again = doc.querySelector(`#cart-drawer .citem[data-key="${CSS.escape(key)}"] [data-qty="${btn.dataset.qty}"]`);
		(again || doc.querySelector('#cart-drawer [data-close]')).focus({ preventScroll: true });
	} catch (err) {
		li.classList.remove('is-busy');
		ctx.toast({ title: (err && err.message) || ctx.t('error'), type: 'error' });
	} finally {
		busy = false;
	}
}

/* ------------------------------------------------------------------ */
/* Wishlist drawer                                                    */
/* ------------------------------------------------------------------ */

let wishEmptyHTML = '';

export async function renderWish() {
	const body = doc.querySelector('#wish-drawer [data-wish-body]');
	const count = doc.querySelector('#wish-drawer [data-wish-drawer-count]');
	if (!body) return;
	if (!wishEmptyHTML) wishEmptyHTML = body.innerHTML;
	const ids = ctx.badges.wish();
	if (count) count.textContent = ids.length ? `(${ctx.t('items', ctx.fmt(ids.length))})` : '';
	if (!ids.length) {
		body.innerHTML = wishEmptyHTML;
		return;
	}
	renderSkeletonInto(body, Math.min(ids.length, 4));
	try {
		const list = await api(`products?include=${ids.join(',')}&per_page=${Math.min(100, ids.length)}`);
		const { esc, t } = ctx;
		const byId = new Map((list || []).map((p) => [p.id, p]));
		const rows = ids.map((id) => byId.get(id)).filter(Boolean);
		if (!rows.length) {
			body.innerHTML = wishEmptyHTML;
			return;
		}
		body.innerHTML = `<ul>${rows.map((p) => {
			const name = decode(p.name);
			const img = p.images && p.images[0] ? p.images[0].thumbnail : '';
			const pr = p.prices || {};
			const buy = p.is_in_stock && p.is_purchasable && p.type === 'simple' && !ctx.cfg.contactMode
				? `<a class="add add--sm" href="${esc(p.add_to_cart && p.add_to_cart.url ? p.add_to_cart.url : p.permalink)}" rel="nofollow" role="button" data-add-to-cart="${p.id}" data-quantity="1" aria-label="${esc(`${t('add')}: ${name}`)}"><svg class="ic ic-plus" aria-hidden="true"><use href="#i-plus"></use></svg><svg class="ic ic-check" aria-hidden="true"><use href="#i-check"></use></svg></a>`
				: (p.is_in_stock ? '' : `<span class="soldout">${esc(t('outOfStock'))}</span>`);
			return `<li class="citem" data-product-id="${p.id}">
	${img ? `<img src="${esc(img)}" alt="" width="68" height="68" loading="lazy">` : '<span></span>'}
	<div><a class="citem__t" href="${esc(p.permalink)}" data-close-link>${esc(name)}</a>
	<div class="citem__row"><span class="citem__price">${ctx.cfg.contactMode ? '' : money(pr.price, pr.currency_minor_unit)}</span>
	<span style="display:inline-flex;gap:6px">${buy}<button class="icon-btn wish-btn" type="button" data-wish="${p.id}" aria-pressed="true" aria-label="${esc(`${t('remove')}: ${name}`)}"><svg class="ic" aria-hidden="true"><use href="#i-heart"></use></svg></button></span></div></div>
</li>`;
		}).join('')}</ul>`;
	} catch {
		body.innerHTML = `<div class="cart-empty"><b>${ctx.esc(ctx.t('error'))}</b></div>`;
	}
}

function renderSkeletonInto(body, n) {
	body.innerHTML = Array.from({ length: n }, () => '<div class="citem" aria-hidden="true"><i class="sk" style="width:68px;height:68px;border-radius:14px"></i><div><i class="sk sk-line" style="margin-top:6px"></i><i class="sk sk-line" style="width:50%;margin-top:12px;height:22px"></i></div></div>').join('');
}

export function init(c) {
	ctx = c;
	// app.js calls refresh() / renderWish() when the cart or wishlist drawer opens.
	ctx.on('gtm:wish', () => {
		const d = doc.getElementById('wish-drawer');
		if (d && d.open) renderWish();
	});
	doc.addEventListener('click', (e) => {
		const q = e.target.closest && e.target.closest('#cart-drawer [data-qty]');
		if (q) changeQty(q);
	});
}
