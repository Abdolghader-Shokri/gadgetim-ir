// Gadgetim "Aurora" homepage prototype: plain JavaScript, no dependencies.
// Every product section is rendered from /_proto/data/catalog.json at runtime.

const DATA_URL = '/_proto/data/catalog.json';
const doc = document;
const root = doc.documentElement;

const mq = {
  reduce: matchMedia('(prefers-reduced-motion: reduce)'),
  desk: matchMedia('(min-width: 1000px)'),
  phone: matchMedia('(max-width: 767.98px)'),
  hover: matchMedia('(hover: hover) and (pointer: fine)'),
};
const reduced = () => mq.reduce.matches;

const $ = (sel, el = doc) => el.querySelector(sel);
const $$ = (sel, el = doc) => Array.from(el.querySelectorAll(sel));
const nf = new Intl.NumberFormat('fa-IR');
const fmt = (n) => nf.format(n);
const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const faNum = (s) => String(s).replace(/\d/g, (d) => FA_DIGITS[d]);
const pad2 = (n) => faNum(String(n).padStart(2, '0'));
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ESC[c]);
const ic = (id, cls = '') => `<svg class="ic ${cls}" aria-hidden="true"><use href="#i-${id}"/></svg>`;
const sized = (u, s = '400x400') => (u ? u.replace(/\.(webp|jpe?g|png)$/i, `-${s}.$1`) : '');

const decoder = doc.createElement('textarea');
function decode(s) {
  decoder.innerHTML = s || '';
  return decoder.value;
}

const store = {
  get(key, fallback) {
    try {
      const v = localStorage.getItem(key);
      return v ? JSON.parse(v) : fallback;
    } catch {
      return fallback;
    }
  },
  set(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch {
      /* storage unavailable: the page works without it */
    }
  },
};

// Persian digits for numbers that sit inside Persian wording; model codes such as 65W or USB 3.0 stay Latin.
function faName(s) {
  const isFa = (c) => /[؀-ۿ]/.test(c);
  const isLat = (c) => /[A-Za-z]/.test(c);
  return s.replace(/(^|[\s(،])(\d+(?:[./]\d+)?)(?=$|[\s)،])/g, (m, pre, num, off, str) => {
    const prev = str.slice(0, off + pre.length).trimEnd().replace(/[()،]$/, '').trimEnd().slice(-1);
    const next = str.slice(off + m.length).trimStart().charAt(0);
    if (isFa(prev) || (isFa(next) && !isLat(prev))) return pre + faNum(num).replace('.', '٫');
    return m;
  });
}

// Display clean-up for names typed in the shop admin: ZWNJ before plural suffixes, a few known typos.
const ZWNJ = '‌';
const NBSP = ' ';
function tidyFa(s) {
  // A Latin word typed twice in a row ("E&Z E&Z") is shown once.
  const dedup = String(s || '').split(' ').filter((w, i, a) => !(i && w === a[i - 1] && /[A-Za-z]/.test(w))).join(' ');
  return faName(dedup
    .replace(/ (ها|های|هایی)(?=$|[\s،.])/g, `${ZWNJ}$1`)
    .replace(/وتصویری/g, 'و تصویری')
    .replace(/دیجیتیال/g, 'دیجیتال')
    .replace(/مچ بند/g, `مچ${ZWNJ}بند`)
    .replace(/پاور بانک/g, 'پاوربانک')
    .replace(/خوشبو کننده/g, 'خوشبوکننده')
    // Two-word brand names never break across lines.
    .replace(/(Green|گرین) (Lion|لاین)/gi, `$1${NBSP}$2`));
}

// Latin runs (brand and model names) are isolated with <bdi> so neutral characters never reorder around them;
// model codes with a hyphen or slash (OS-E02, USB-C) never break across lines.
const LATIN_RUN = /[A-Za-z0-9][A-Za-z0-9.+&'’/-]*(?:[  ][A-Za-z0-9][A-Za-z0-9.+&'’/-]*)*/g;
const latinRun = (m) => m.split(' ').map((w) => (/[-/]/.test(w) ? `<span class="nw">${esc(w)}</span>` : esc(w))).join(' ');
function bidi(s) {
  const str = String(s || '');
  let out = '';
  let last = 0;
  str.replace(LATIN_RUN, (m, off) => {
    out += `${esc(str.slice(last, off))}<bdi>${latinRun(m)}</bdi>`;
    last = off + m.length;
    return m;
  });
  return out + esc(str.slice(last));
}

/* Title fitting: CSS line-clamp puts its ellipsis inside Latin runs in RTL text and chops letters off
   model names, so titles are cut at a word boundary in JS (measured on a canvas with the real font)
   and the ellipsis is appended after the last whole word. The rest stays available to screen readers. */
const fitCtx = doc.createElement('canvas').getContext('2d');
const WEAK_TAIL = new Set(['با', 'و', 'به', 'مدل', 'برای', 'از', 'در', 'دارای', 'همراه', 'ظرفیت', 'توان', 'طول', 'سری', 'مجهز', 'ضد', 'قابلیت', 'نسل', '-', '–', '|', '،', '+']);
const fitRender = new WeakMap();
function countLines(words, maxW, measure, spaceW) {
  let lines = 1;
  let cur = -1;
  for (const w of words) {
    const ww = measure(w);
    if (cur < 0) cur = ww;
    else if (cur + spaceW + ww <= maxW) cur += spaceW + ww;
    else {
      lines += 1;
      cur = ww;
    }
    while (cur > maxW) {
      lines += 1;
      cur -= maxW;
    }
  }
  return lines;
}
function fitString(full, maxW, lines, font) {
  fitCtx.font = font;
  const cache = new Map();
  const measure = (w) => {
    let v = cache.get(w);
    if (v === undefined) {
      v = fitCtx.measureText(w).width;
      cache.set(w, v);
    }
    return v;
  };
  const spaceW = measure(' ');
  // Only ordinary spaces are break points (no-break spaces glue brand names).
  const words = full.split(/ +/).filter(Boolean);
  if (countLines(words, maxW, measure, spaceW) <= lines) return null;
  let lo = 1;
  let hi = words.length - 1;
  let best = 1;
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    const cand = words.slice(0, mid);
    cand[mid - 1] += '…';
    if (countLines(cand, maxW, measure, spaceW) <= lines) {
      best = mid;
      lo = mid + 1;
    } else hi = mid - 1;
  }
  let k = best;
  while (k > 1 && WEAK_TAIL.has(words[k - 1].replace(/[،,:;()]+$/g, ''))) k -= 1;
  return { shown: words.slice(0, k).join(' ').replace(/[\s،,:;(–-]+$/, ''), rest: words.slice(k).join(' ') };
}
// Elements marked [data-fit] carry the full text in data-full; lines come from the CSS custom property --lines.
function fitAll(scope = doc) {
  const els = $$('[data-fit]', scope);
  if (!els.length) return;
  const jobs = els.map((el) => {
    const w = el.clientWidth;
    if (!w) return null;
    const cs = getComputedStyle(el);
    const lines = parseInt(cs.getPropertyValue('--lines'), 10) || 2;
    return { el, w, lines, font: `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}` };
  }).filter(Boolean);
  for (const { el, w, lines, font } of jobs) {
    const key = `${w}|${lines}|${font}`;
    if (el.dataset.fitKey === key) continue;
    el.dataset.fitKey = key;
    const target = el.firstElementChild && el.firstElementChild.tagName === 'A' ? el.firstElementChild : el;
    const render = fitRender.get(el) || bidi;
    const full = el.dataset.full || '';
    const r = fitString(full, w - 1.5, lines, font);
    target.innerHTML = r ? `${render(r.shown)}<span aria-hidden="true">…</span><span class="sr-only"> ${esc(r.rest)}</span>` : render(full);
  }
}
const fontsReady = (doc.fonts && doc.fonts.load)
  ? Promise.all([doc.fonts.load('600 13px Vazirmatn'), doc.fonts.load('700 13px Vazirmatn'), doc.fonts.load('800 14px Estedad')]).catch(() => null).then(() => doc.fonts.ready)
  : Promise.resolve();
const refit = (scope) => { fontsReady.then(() => fitAll(scope)); };

/* ------------------------------------------------------------------ */
/* State                                                               */
/* ------------------------------------------------------------------ */

const S = {
  products: [],
  byId: new Map(),
  cats: new Map(),
  kids: new Map(),
  brands: [],
  cart: store.get('aurora-cart', []),
  wish: new Set(store.get('aurora-wish', [])),
  ready: false,
};

const GROUPS = [
  { t: 'لوازم جانبی موبایل', ic: 'mobile', main: 68, subs: [66, 67, 60, 82, 84, 61, 116, 106] },
  { t: 'صوتی و تصویری', ic: 'headphones', main: 72, subs: [76, 71, 108, 109, 111, 110] },
  { t: 'ساعت و مچ‌بند هوشمند', ic: 'watch', main: 58, subs: [58, 69] },
  { t: 'کامپیوتر و حافظه', ic: 'usb', main: 63, subs: [102, 95, 84] },
  { t: 'لوازم جانبی خودرو', ic: 'car', main: 73, subs: [89, 86, 98, 82, 77] },
  { t: 'خانه و آشپزخانه', ic: 'cup', main: 90, subs: [87, 81, 104, 105, 83, 88, 80] },
  { t: 'زیبایی و سلامت', ic: 'drop', main: 74, subs: [79, 78, 112, 115, 103, 100] },
  { t: 'کمپینگ و سفر', ic: 'tent', main: 75, subs: [101, 113, 114, 85, 107, 80] },
];

const THEMES = {
  indigo: { base: 'linear-gradient(135deg,#eceffe 0%,#f2edff 52%,#ffedf6 100%)', c1: 'rgba(129,140,248,.62)', c2: 'rgba(244,114,182,.5)', c3: 'rgba(192,132,252,.5)', tg: 'linear-gradient(90deg,#4f46e5,#9333ea 55%,#db2777)', accent: '#ec4899', stroke: 'rgba(79,70,229,.14)', stroke2: 'rgba(99,102,241,.28)' },
  violet: { base: 'linear-gradient(135deg,#f1edff 0%,#e9f6ff 55%,#f5eeff 100%)', c1: 'rgba(167,139,250,.62)', c2: 'rgba(34,211,238,.42)', c3: 'rgba(129,140,248,.5)', tg: 'linear-gradient(90deg,#6d28d9,#4f46e5 50%,#0891b2)', accent: '#06b6d4', stroke: 'rgba(109,40,217,.13)', stroke2: 'rgba(124,58,237,.28)' },
  rose: { base: 'linear-gradient(135deg,#fff0f5 0%,#fff3ea 52%,#f6efff 100%)', c1: 'rgba(251,113,133,.5)', c2: 'rgba(253,186,116,.55)', c3: 'rgba(196,181,253,.55)', tg: 'linear-gradient(90deg,#db2777,#e11d48 50%,#ea580c)', accent: '#f43f5e', stroke: 'rgba(225,29,72,.12)', stroke2: 'rgba(244,63,94,.26)' },
  amber: { base: 'linear-gradient(135deg,#fff7e8 0%,#fff0f1 50%,#eef0ff 100%)', c1: 'rgba(251,191,36,.55)', c2: 'rgba(244,114,182,.45)', c3: 'rgba(129,140,248,.45)', tg: 'linear-gradient(90deg,#d97706,#db2777 55%,#7c3aed)', accent: '#f59e0b', stroke: 'rgba(217,119,6,.14)', stroke2: 'rgba(245,158,11,.3)' },
};

// Two products per slide: the main one carries the price plate, the second sits behind it for depth.
const SLIDES = [
  { word: 'SOUND', theme: 'indigo', eyebrow: 'هدفون و هندزفری', brands: 'Green Lion · Anker · JBL', t1: 'صدای ناب،', t2: 'آزادی بی‌سیم', sub: 'هدفون و هندزفری‌های اورجینال با ضمانت اصالت کالا', subM: 'هدفون و هندزفری اورجینال، با ضمانت اصالت', cta: 'خرید هدفون و هندزفری', ctaM: 'خرید هدفون', cat: 76, scope: [76, 71, 108], art: [[641, 1], [773, 1, 'f']], chip: { ic: 'shield', t: '۱۰۰٪ اصل و اورجینال' } },
  { word: 'POWER', theme: 'violet', eyebrow: 'پاوربانک، شارژر و کابل', brands: 'Green Lion · Samsung', t1: 'همیشه شارژ،', t2: 'هرجا که باشی', sub: 'پاوربانک، شارژر و کابل‌های پرسرعت اورجینال', subM: 'پاوربانک، شارژر و کابل پرسرعت اورجینال', cta: 'مشاهده پاوربانک‌ها', ctaM: 'پاوربانک‌ها', cat: 60, scope: [60, 66, 67], art: [[658, 1], [699, 1]], chip: { ic: 'bolt', t: 'شارژ فوق سریع' } },
  { word: 'HOME', theme: 'rose', eyebrow: 'خانه و آشپزخانه', brands: 'LePresso · Green Lion · Porodo', t1: 'گجت‌های هوشمند', t2: 'برای هر روز', sub: 'شیکر شارژی، اسپرسوساز سفری و گجت‌های خانه', subM: 'شیکر شارژی، اسپرسوساز سفری و گجت خانه', cta: 'گجت‌های خانگی', ctaM: 'گجت خانگی', cat: 90, scope: [90, 87, 104, 105, 81, 80], art: [[785, 3, 'f'], [648, 1, 'f']], chip: { ic: 'sparkle', t: 'شیکر شارژی ۶ تیغه' } },
  { word: 'ROAD', theme: 'amber', eyebrow: 'لوازم جانبی خودرو', brands: 'Porodo · Green Lion · Koranco', t1: 'همراه همیشگی', t2: 'جاده و سفر', sub: 'پمپ باد شارژی، جت فن و شارژر فندکی سوپرفست', subM: 'پمپ باد شارژی، جت فن و شارژر فندکی', cta: 'لوازم جانبی خودرو', ctaM: 'لوازم خودرو', cat: 73, scope: [73, 89, 86, 82, 77, 98], art: [[768, 1, 'f'], [553, 1]], chip: { ic: 'gauge', t: 'فشار تا ۱۵۰ PSI' } },
];

const BANNER_THEMES = {
  violet: { bg: 'linear-gradient(to left,#2e1065 0%,#5b21b6 40%,#7c3aed 72%,#c026d3 118%)', glow: 'rgba(109,40,217,.8)' },
  sunset: { bg: 'linear-gradient(to left,#9f1239 0%,#e11d48 40%,#f97316 80%,#fbbf24 125%)', glow: 'rgba(225,29,72,.75)' },
  ocean: { bg: 'linear-gradient(to left,#0c4a6e 0%,#0369a1 42%,#0ea5e9 84%,#67e8f9 130%)', glow: 'rgba(3,105,161,.75)' },
};

// Banner and tile packshots are picked so no product reaches into the masked watermark corner.
const BANNERS = [
  { theme: 'violet', brand: 'GREEN LION', t1: 'پاوربانک‌های', t2: 'گرین لاین', sub: 'شارژ سریع، ظرفیت بالا و طراحی خوش‌دست برای روزهای پرمشغله', cat: 60, scope: { cats: [60], brand: 'Green Lion' }, imgs: [[658, 1], [658, 0]] },
  { theme: 'sunset', brand: 'JBL', t1: 'دنیای صدای', t2: 'JBL', sub: 'هدفون‌های بی‌سیم و اسپیکرهای اورجینال جی‌بی‌ال', brandSlug: 'jbl', scope: { brand: 'JBL' }, imgs: [[421, 3, 'f'], [421, 1, 'f']] },
  { theme: 'ocean', brand: 'KORANCO', t1: 'کابل، شارژر و فلش', t2: 'کورانکو', sub: 'لوازم جانبی پرسرعت و بادوام برای استفاده‌ی هر روز', brandSlug: 'koranco', scope: { brand: 'Koranco' }, imgs: [[349, 0], [557, 0]] },
];

const STORY_CATS = [76, 71, 109, 60, 66, 67, 102, 58, 73, 90, 74, 75, 78, 87, 108];
// Shorter story labels so every circle keeps a one-line caption.
const STORY_LABEL = { 73: 'لوازم خودرو', 90: 'گجت خانگی', 87: 'قهوه‌ساز', 74: 'زیبایی', 58: 'ساعت هوشمند', 75: 'کمپینگ' };

const TILES = [
  { id: 68, big: true, imgs: [[840, 1], [846, 1]] },
  { id: 72, big: true, imgs: [[599, 1, 'f'], [641, 5]] },
  { id: 102, imgs: [[434, 2]] },
  { id: 66, imgs: [[345, 1]] },
  { id: 67, imgs: [[699, 2]] },
  { id: 71, imgs: [[746, 2]] },
  { id: 60, imgs: [[658, 1]] },
  { id: 76, imgs: [[653, 1]] },
  { id: 74, imgs: [[703, 1, 'f']] },
  { id: 73, imgs: [[757, 0]] },
  { id: 75, imgs: [[637, 0]] },
  { id: 78, imgs: [[709, 1]] },
];
const PASTELS = [['#eef0ff', '#dde2ff'], ['#fdf0f7', '#fadbec'], ['#f4efff', '#e5daff'], ['#fff5e9', '#ffe1c2'], ['#eafcff', '#ccf3fb'], ['#effcf3', '#d1f4dd'], ['#fffbe6', '#fcefb4'], ['#f9f0ff', '#eddaff']];

const FEAT_TABS = [
  { t: 'همه', ids: null },
  { t: 'صوتی', ids: [72, 76, 71, 108] },
  { t: 'شارژ و کابل', ids: [66, 67, 60, 82, 84] },
  { t: 'خانه و آشپزخانه', ids: [90, 87, 81, 80, 104, 105, 83, 88] },
  { t: 'زیبایی و سلامت', ids: [74, 78, 79] },
  { t: 'خودرو و سفر', ids: [73, 75, 89, 86, 77, 85, 98] },
];

const BRAND_STYLE = {
  'green-lion': { label: 'GREEN LION', c: '#15803d', w: 900, ls: '.14em' },
  koranco: { label: 'KORANCO', c: '#b45309', w: 900, ls: '.08em', fs: 'italic' },
  porodo: { label: 'porodo', c: '#111827', w: 800, ls: '.01em' },
  onesam: { label: 'ONESAM', c: '#0284c7', w: 900, ls: '.12em' },
  samsung: { label: 'SAMSUNG', c: '#1d4ed8', w: 800, ls: '.2em' },
  sandisk: { label: 'SanDisk', c: '#dc2626', w: 800, ls: '0' },
  anker: { label: 'ANKER', c: '#0ea5e9', w: 800, ls: '.14em' },
  apple: { label: 'Apple', c: '#111827', w: 600, ls: '0' },
  jbl: { label: 'JBL', c: '#ea580c', w: 900, ls: '.06em' },
  lepresso: { label: 'LEPRESSO', c: '#92400e', w: 600, ls: '.26em' },
  powerology: { label: 'Powerology', c: '#334155', w: 700, ls: '.02em' },
  philips: { label: 'PHILIPS', c: '#1d4ed8', w: 800, ls: '.18em' },
  xiaomi: { label: 'Xiaomi', c: '#ea580c', w: 700, ls: '.02em' },
  ez: { label: 'E&Z', c: '#7c3aed', w: 900, ls: '.08em' },
  enzo: { label: 'ENZO', c: '#be185d', w: 800, ls: '.22em' },
  jbq: { label: 'JBQ', c: '#0f766e', w: 900, ls: '.1em' },
  depak: { label: 'DEPAK', c: '#4338ca', w: 800, ls: '.16em' },
  pentica: { label: 'PENTICA', c: '#9333ea', w: 700, ls: '.18em' },
};

const POPULAR = ['پاوربانک', 'هندزفری بلوتوثی', 'JBL', 'شارژر فست', 'فلش مموری', 'کابل تایپ سی'];
const SYNONYMS = {
  'green lion': 'گرین لاین گرینلاین', koranco: 'کورانکو', porodo: 'پرودو پورودو', onesam: 'وانسم', jbl: 'جی بی ال',
  anker: 'انکر ساندکور soundcore', sandisk: 'سن دیسک سندیسک', lepresso: 'لپرسو', apple: 'اپل', samsung: 'سامسونگ',
  xiaomi: 'شیائومی', philips: 'فیلیپس', powerology: 'پاورولوژی',
};

/* ------------------------------------------------------------------ */
/* Data helpers                                                        */
/* ------------------------------------------------------------------ */

function prepare(data) {
  S.cats = new Map(data.categories.map((c) => [c.id, { ...c, name: tidyFa(decode(c.name)) }]));
  S.kids = new Map();
  for (const c of S.cats.values()) {
    if (!S.kids.has(c.parent)) S.kids.set(c.parent, []);
    S.kids.get(c.parent).push(c.id);
  }
  S.brands = (data.brands || []).map((b) => ({ ...b, name: decode(b.name) }));
  // Products saved without a brand: recognise it from the name (JBL, PORODO, Anker/Soundcore…).
  const brandRes = S.brands.map((b) => {
    const alias = { anker: '|soundcore|انکر', porodo: '|پرودو', jbl: '|جی بی ال' }[b.slug] || '';
    const word = b.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return [b.name, new RegExp(`(?:^|[^A-Za-z])(?:${word}${alias})(?:$|[^A-Za-z])`, 'i')];
  });
  S.products = data.products.map((p) => {
    const name = decode(p.name);
    let brand = decode(p.brand || '');
    if (!brand) {
      const hit = brandRes.find(([, re]) => re.test(name));
      if (hit) brand = hit[0];
    }
    return {
      ...p,
      name,
      title: tidyFa(name),
      brand,
      thumb: p.thumb || sized(p.img),
      thumb2: p.img2 ? sized(p.img2) : '',
      cat_ids: p.cat_ids || [],
      gallery: p.gallery || [],
    };
  });
  S.byId = new Map(S.products.map((p) => [p.id, p]));
  S.cart = S.cart.filter((x) => S.byId.has(x.id) && S.byId.get(x.id).in_stock);
  S.ready = true;
}

function subtree(ids) {
  const out = new Set();
  const walk = (id) => {
    if (out.has(id)) return;
    out.add(id);
    (S.kids.get(id) || []).forEach(walk);
  };
  ids.forEach(walk);
  return out;
}
const inSet = (p, set) => p.cat_ids.some((id) => set.has(id));
const productsIn = (ids) => {
  const set = subtree(ids);
  return S.products.filter((p) => inSet(p, set));
};
const maxOff = (list) => list.filter((p) => p.in_stock).reduce((m, p) => Math.max(m, p.off || 0), 0);
const catUrl = (id) => (S.cats.get(id) || {}).url || '/shop/';
const brandUrl = (slug) => (S.brands.find((b) => b.slug === slug) || {}).url || '/shop/';
// Art entries are [productId, galleryIndex, 'f'?]; 'f' marks a product standing on the bottom edge,
// whose base is dissolved softly (wm--fade) rather than notched where the watermark is masked.
const galleryImg = (id, i, size = '400x400', flag = '') => {
  const p = S.byId.get(id);
  if (!p) return null;
  const full = p.gallery[i] || p.img;
  return { p, full, src: size ? sized(full) : full, cls: flag === 'f' ? 'wm wm--fade' : 'wm' };
};
function catImg(c) {
  if (c.img) return c.img;
  const p = productsIn([c.id]).find((x) => x.thumb2 || x.thumb);
  return p ? p.thumb2 || p.thumb : '';
}
const priceHTML = (p, cls = '') => `<div class="price ${cls}">${p.regular > p.price ? `<del><span class="sr-only">قیمت قبلی: </span>${fmt(p.regular)}</del>` : ''}<span class="price__now"><span class="sr-only">قیمت: </span><b>${fmt(p.price)}</b><small>تومان</small></span></div>`;
// A fitted title: the element keeps the full text in data-full and is shortened by fitAll() after layout.
const fitted = (tag, cls, text, href = '') => `<${tag} class="${cls}" data-fit data-full="${esc(text)}">${href ? `<a href="${href}">${bidi(text)}</a>` : bidi(text)}</${tag}>`;
const addBtn = (p, wide = false) => `<button class="add${wide ? ' add--wide' : ''}" type="button" data-add="${p.id}" aria-label="افزودن به سبد خرید: ${esc(p.name)}">${ic('plus', 'ic-plus')}${ic('check', 'ic-check')}${wide ? '<span>خرید</span>' : ''}</button>`;

/* ------------------------------------------------------------------ */
/* Templates                                                           */
/* ------------------------------------------------------------------ */

function card(p, i = 0, opt = {}) {
  const out = !p.in_stock;
  const has2 = Boolean(p.thumb2);
  const brand = `<span class="pc__brand"${p.brand ? '' : ' aria-hidden="true"'}>${esc(p.brand)}</span>`;
  let meta = '';
  if (!out && p.low_stock) meta = `<span class="dotty" aria-hidden="true"></span>تنها ${fmt(p.low_stock)} عدد در انبار`;
  if (p.rating > 0) meta += `<span class="pc__rate">${ic('star')}${fmt(p.rating)}${p.reviews ? ` <small>(${fmt(p.reviews)})</small>` : ''}</span>`;
  const lazy = opt.eager ? 'eager' : 'lazy';
  const cls = ['pc', out ? 'is-out' : '', opt.pop ? 'pop' : '', opt.revealed ? 'is-in' : ''].filter(Boolean).join(' ');
  const flags = `${p.off > 0 && !out ? `<span class="off"><span class="sr-only">تخفیف </span>${fmt(p.off)}٪</span>` : ''}${opt.isNew ? `<span class="pc__tag">${ic('sparkle')}جدید</span>` : ''}`;
  return `<li class="${cls}" data-reveal style="--i:${Math.min(i, 7)}">
  <article class="pc__in">
    <div class="pc__media">
      <img class="pc__img${has2 ? ' pc__img--has2' : ''}" src="${p.thumb}" data-full="${p.img}" alt="${esc(p.name)}" width="400" height="400" loading="${lazy}" decoding="async">
      ${has2 ? `<img class="pc__img2" src="${p.thumb2}" data-full="${p.img2}" alt="" width="400" height="400" loading="lazy" decoding="async">` : ''}
    </div>
    ${flags ? `<div class="pc__flags">${flags}</div>` : ''}
    <button class="pc__wish" type="button" data-wish="${p.id}" aria-pressed="${S.wish.has(p.id)}" aria-label="افزودن به علاقه‌مندی‌ها: ${esc(p.name)}">${ic('heart')}</button>
    <div class="pc__body">
      ${brand}
      ${fitted('h3', 'pc__title', p.title, p.url)}
      <div class="pc__meta">${meta}</div>
      <div class="pc__foot">${out ? '<span class="soldout">ناموجود</span>' : `${priceHTML(p)}${addBtn(p)}`}</div>
    </div>
  </article>
</li>`;
}

const skCard = () => `<li class="pc pc--sk" aria-hidden="true"><div class="pc__in"><div class="pc__media sk"></div><div class="pc__body"><span class="pc__brand"><i class="sk sk--b"></i></span><span class="pc__title"><i class="sk sk--t"></i><i class="sk sk--t2"></i></span><span class="pc__meta"></span><span class="pc__foot"><i class="sk sk--p"></i></span></div></div></li>`;
const repeat = (n, fn) => Array.from({ length: n }, (_, i) => fn(i)).join('');

function skeletons() {
  $('#stories').innerHTML = repeat(12, () => '<li class="story story--sk" aria-hidden="true"><span class="story__ring"><span class="story__img"></span></span><span class="story__name"></span></li>');
  $('#slides').innerHTML = '<div class="slide slide--sk is-active" aria-hidden="true"><div class="slide__text"><i class="sk sk--eyebrow"></i><i class="sk sk--t1"></i><i class="sk sk--t2"></i><i class="sk sk--btn"></i></div><div class="slide__art"><i class="sk sk--art"></i></div></div>';
  $('#flashStage').innerHTML = '<div class="fitem fitem--sk is-active" aria-hidden="true"><div class="sk sk--media"></div><div class="fitem__body"><i class="sk sk--l"></i><i class="sk sk--l" style="width:70%"></i><i class="sk sk--l" style="width:45%;height:22px"></i></div></div>';
  ['#offersRail', '#newestRail'].forEach((s) => { $(s).innerHTML = repeat(6, skCard); });
  $('#featGrid').innerHTML = repeat(10, skCard);
  $('#banners').innerHTML = repeat(3, () => '<div class="bnr bnr--sk sk" aria-hidden="true"></div>');
  $('#rank').innerHTML = repeat(12, () => '<li aria-hidden="true"><div class="rk rk--sk"><i class="sk sk--img"></i><i class="sk sk--n"></i><span><i class="sk sk--l"></i><i class="sk sk--l" style="width:55%"></i></span></div></li>');
  $('#cgrid').innerHTML = repeat(12, (i) => `<li class="${i < 2 ? 'ctile-li--big' : ''}"><div class="ctile ctile--sk sk${i < 2 ? ' ctile--big' : ''}" aria-hidden="true"></div></li>`);
}

/* ------------------------------------------------------------------ */
/* Renderers                                                           */
/* ------------------------------------------------------------------ */

function renderStories() {
  const items = [`<li style="--i:0"><a class="story story--hot" href="#offers"><span class="story__ring"><span class="story__img">${ic('flame')}</span></span><span class="story__name">شگفت‌انگیزها</span></a></li>`];
  let i = 1;
  for (const id of STORY_CATS) {
    const c = S.cats.get(id);
    if (!c || !c.count) continue;
    const img = catImg(c);
    const label = STORY_LABEL[id] || c.name;
    items.push(`<li style="--i:${i++}"><a class="story" href="${c.url}"${label !== c.name ? ` aria-label="${esc(c.name)}"` : ''}><span class="story__ring"><span class="story__img">${img ? `<img class="wm" src="${img}" alt="" width="80" height="80" decoding="async">` : ic('grid')}</span></span><span class="story__name">${esc(label)}</span></a></li>`);
  }
  const el = $('#stories');
  el.innerHTML = items.join('');
  el.classList.add('is-ready');
  railState(el);
}

function renderHero() {
  const n = SLIDES.length;
  const html = SLIDES.map((s, i) => {
    const t = THEMES[s.theme];
    const scopeOff = maxOff(productsIn(s.scope));
    const art = s.art.map(([id, gi, f]) => galleryImg(id, gi, '', f)).filter(Boolean).slice(0, 2);
    const main = art[0] && art[0].p;
    const vars = `--base:${t.base};--c1:${t.c1};--c2:${t.c2};--c3:${t.c3};--tg:${t.tg};--accent:${t.accent};--stroke:${t.stroke};--stroke-2:${t.stroke2}`;
    const depth = [16, 34];
    const figs = art.map((a, k) => {
      const img = `<img class="${a.cls}" src="${a.full}" alt="${k === 0 ? esc(a.p.name) : ''}" width="800" height="800" decoding="async" ${i === 0 ? (k === 0 ? 'fetchpriority="high"' : '') : 'loading="lazy"'}>`;
      return `<figure class="hp hp--${k + 1}" style="--d:${k};--z:${depth[k]}"><div class="hp__par"><div class="hp__float">${k === 0 ? `<a href="${a.p.url}" tabindex="-1">${img}</a>` : img}</div></div><i class="hp__shadow" aria-hidden="true"></i></figure>`;
    }).join('');
    const priceInner = main ? `<small>${main.off ? `<s>${fmt(main.regular)}</s><em>${fmt(main.off)}٪</em>` : 'قیمت ویژه'}</small><b>${fmt(main.price)} <small>تومان</small></b>` : '';
    const chipB = main ? `<span class="chip chip--b chip--price" style="--d:1" aria-hidden="true">${priceInner}</span>` : '';
    const priceM = main ? `<span class="slide__price" aria-hidden="true">${priceInner}</span>` : '';
    return `<div class="slide${i === 0 ? ' is-active' : ''}" role="group" aria-roledescription="اسلاید" aria-label="${fmt(i + 1)} از ${fmt(n)}: ${esc(s.t1)} ${esc(s.t2)}" style="${vars}"${i ? ' aria-hidden="true" inert' : ''}>
  <div class="slide__bg" aria-hidden="true"><i class="blob b1"></i><i class="blob b2"></i><i class="blob b3"></i><span class="slide__dots"></span><span class="slide__word">${s.word}</span>${[1, 2, 3, 4].map((n) => `<svg class="slide__spark k${n}" viewBox="0 0 24 24"><path d="M12 1c1 7 4 10 11 11-7 1-10 4-11 11-1-7-4-10-11-11 7-1 10-4 11-11Z"/></svg>`).join('')}</div>
  <span class="slide__num" aria-hidden="true"><b>${pad2(i + 1)}</b> / ${pad2(n)}</span>
  <div class="slide__text">
    <span class="eyebrow" data-in style="--d:0"><i></i>${esc(s.eyebrow)}<span class="eyebrow__b">&nbsp;· ${esc(s.brands)}</span></span>
    <h2 class="slide__title"><span data-in style="--d:1">${esc(s.t1)}</span><span data-in style="--d:2"><span class="gt">${esc(s.t2)}</span></span></h2>
    <p class="slide__sub" data-in style="--d:3"><span class="lbl-d">${esc(s.sub)}</span><span class="lbl-m">${esc(s.subM || s.sub)}</span></p>
    <div class="slide__cta" data-in style="--d:4"><a class="btn btn--glow" href="${catUrl(s.cat)}"><span class="lbl-d">${esc(s.cta)}</span><span class="lbl-m">${esc(s.ctaM || s.cta)}</span>${ic('arrow')}</a>${priceM}${scopeOff ? `<span class="deal">تا <b>${fmt(scopeOff)}٪</b> تخفیف</span>` : ''}</div>
    ${main ? `<p class="sr-only">${esc(main.name)}: ${fmt(main.price)} تومان</p>` : ''}
  </div>
  ${scopeOff ? `<span class="deal deal--sticker" aria-hidden="true">تا<b>${fmt(scopeOff)}٪</b>تخفیف</span>` : ''}
  <div class="slide__art">
    <div class="stage" aria-hidden="true"><i class="stage__halo"></i><svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="98" stroke-dasharray="2 7"/><circle cx="100" cy="100" r="81"/><circle cx="100" cy="100" r="64" stroke-dasharray="46 12 4 12"/></svg></div>
    ${figs}
    <span class="chip chip--a" style="--d:0" aria-hidden="true"><span class="chip__ic">${ic(s.chip.ic)}</span>${esc(s.chip.t)}</span>
    ${chipB}
  </div>
</div>`;
  }).join('');
  $('#slides').innerHTML = html;
  initSlider($('#slider'));
}

function renderFlash() {
  const list = S.products.filter((p) => p.in_stock && p.off > 0).sort((a, b) => b.off - a.off || a.price - b.price).slice(0, 5);
  const stage = $('#flashStage');
  stage.innerHTML = list.map((p, i) => {
    const img = p.thumb2 || p.thumb;
    const save = p.regular - p.price;
    return `<article class="fitem${i === 0 ? ' is-active' : ''}"${i ? ' aria-hidden="true" inert' : ''}>
  <div class="fitem__media"><span class="off fitem__off"><span class="sr-only">تخفیف </span>${fmt(p.off)}٪</span><div class="fitem__img"><img class="wm wm--fade" src="${img}" data-full="${p.img2 || p.img}" alt="${esc(p.name)}" width="400" height="400" decoding="async"></div></div>
  <div class="fitem__body">
    ${fitted('h3', 'fitem__name', p.title, p.url)}
    ${save > 0 ? `<p class="fitem__save">${ic('sparkle')}سود شما: <b>${fmt(save)}</b> تومان</p>` : ''}
    ${p.low_stock ? `<p class="fitem__low"><span class="dotty" aria-hidden="true"></span>تنها ${fmt(p.low_stock)} عدد باقی مانده</p>` : ''}
    <div class="fitem__cta">${priceHTML(p)}${addBtn(p, true)}</div>
  </div>
</article>`;
  }).join('');
  initFlash($('#flash'), list.length);
  refit(stage);
}

function renderRail(sel, list, optFn = () => ({})) {
  const el = $(sel);
  el.innerHTML = list.map((p, i) => card(p, i, optFn(p, i))).join('');
  observe(el);
  railState(el);
  refit(el);
}

const offerList = () => S.products.filter((p) => p.in_stock && p.off >= 15).sort((a, b) => b.off - a.off || b.price - a.price).slice(0, 16);
const newestList = () => [...S.products].sort((a, b) => b.id - a.id).slice(0, 14);
function bestList() {
  // No sales numbers in the prototype data: scarce in-stock items first, then deeper discounts (the theme will use total_sales).
  const seen = new Set();
  return S.products
    .filter((p) => p.in_stock)
    .sort((a, b) => (b.low_stock ? 1 : 0) - (a.low_stock ? 1 : 0) || b.off - a.off || a.price - b.price)
    .filter((p) => {
      const k = p.cat_ids[0];
      if (seen.has(k)) return false;
      seen.add(k);
      return true;
    })
    .slice(0, 12);
}

function renderBanners() {
  const html = BANNERS.map((b, i) => {
    const t = BANNER_THEMES[b.theme];
    let scope = S.products;
    if (b.scope.cats) scope = productsIn(b.scope.cats);
    if (b.scope.brand) scope = scope.filter((p) => p.brand.toLowerCase() === b.scope.brand.toLowerCase());
    const off = maxOff(scope);
    const href = b.cat ? catUrl(b.cat) : brandUrl(b.brandSlug);
    const imgs = b.imgs.map(([id, gi, f]) => galleryImg(id, gi, '400x400', f)).filter(Boolean);
    const tile = (a, cls) => (a ? `<span class="bnr__tile ${cls}"><img class="${a.cls}" src="${a.src}" data-full="${a.full}" alt="" width="400" height="400" loading="lazy" decoding="async"></span>` : '');
    return `<a class="bnr" href="${href}" data-reveal style="--i:${i};--bg-g:${t.bg};--glow:${t.glow}">
  <span class="bnr__fx" aria-hidden="true"><i></i><svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="98"/><circle cx="100" cy="100" r="76" stroke-dasharray="3 7"/><circle cx="100" cy="100" r="54"/></svg></span>
  <span class="bnr__text"><span class="bnr__brand">${esc(b.brand)}</span><span class="bnr__title">${esc(b.t1)}<br>${esc(b.t2)}</span><span class="bnr__sub">${esc(b.sub)}</span><span class="bnr__cta">مشاهده و خرید${ic('arrow')}</span></span>
  <span class="bnr__art" aria-hidden="true">${tile(imgs[1], 'bnr__tile--b')}${tile(imgs[0], 'bnr__tile--a')}${off ? `<span class="bnr__badge"><span>تا<b>${fmt(off)}٪</b>تخفیف</span></span>` : ''}</span>
</a>`;
  }).join('');
  const el = $('#banners');
  el.innerHTML = html;
  observe(el);
}

function renderRank() {
  const el = $('#rank');
  el.innerHTML = bestList().map((p, i) => `<li data-reveal style="--i:${i % 6}"><div class="rk${i < 3 ? ' rk--top' : ''}">
  <span class="rk__img"><img src="${p.thumb}" data-full="${p.img}" alt="${esc(p.name)}" width="400" height="400" loading="lazy" decoding="async"></span>
  <span class="rk__n" aria-hidden="true">${fmt(i + 1)}</span>
  <div class="rk__body">${fitted('h3', 'rk__t', p.title, p.url)}
  <div class="rk__p">${p.off ? `<span class="off">${fmt(p.off)}٪</span>` : ''}<span class="price__now">${fmt(p.price)}<small>تومان</small></span></div></div>
</div></li>`).join('');
  observe(el);
  railState(el);
  refit(el);
}

function renderCats() {
  const el = $('#cgrid');
  el.innerHTML = TILES.map((tile, i) => {
    const c = S.cats.get(tile.id);
    if (!c) return '';
    const [c1, c2] = PASTELS[i % PASTELS.length];
    let imgs = tile.imgs.map(([id, gi, f]) => galleryImg(id, gi, '400x400', f)).filter(Boolean).slice(0, tile.big ? 2 : 1);
    if (!imgs.length && catImg(c)) imgs = [{ src: catImg(c), full: catImg(c), cls: 'wm' }];
    const art = imgs.map((a) => `<img class="${a.cls}" src="${a.src}" data-full="${a.full}" alt="" width="400" height="400" loading="lazy" decoding="async">`).join('');
    return `<li class="${tile.big ? 'ctile-li--big' : ''}" data-reveal style="--i:${i % 6}"><a class="ctile${tile.big ? ' ctile--big' : ''}" href="${c.url}" style="--c1:${c1};--c2:${c2}">
  <span class="ctile__art" aria-hidden="true">${art}</span>
  <span class="ctile__name">${esc(c.name)}</span>
  <span class="ctile__count"><b data-count="${c.count}">${fmt(c.count)}</b> کالا</span>
  <span class="ctile__go" aria-hidden="true">${ic('arrow')}</span>
</a></li>`;
  }).join('');
  observe(el);
}

let featTab = 0;
function featList(idx) {
  // Premium picks: priciest in-stock items, with one unavailable item in sixth place so the sold-out state stays honest and visible.
  const tab = FEAT_TABS[idx];
  const list = [...(tab.ids ? productsIn(tab.ids) : S.products)].sort((a, b) => b.price - a.price);
  const inStock = list.filter((p) => p.in_stock);
  const out = list.find((p) => !p.in_stock);
  const picks = inStock.slice(0, out ? 9 : 10);
  if (out) picks.splice(Math.min(5, picks.length), 0, out);
  return picks;
}
function renderFeatTabs() {
  const el = $('#featTabs');
  el.innerHTML = FEAT_TABS.map((t, i) => `<button class="tab" type="button" role="tab" id="ftab-${i}" aria-selected="${i === featTab}" aria-controls="featPanel" tabindex="${i === featTab ? 0 : -1}">${esc(t.t)}</button>`).join('');
  el.addEventListener('click', (e) => {
    const b = e.target.closest('.tab');
    if (b) selectTab(Number(b.id.split('-')[1]));
  });
  el.addEventListener('keydown', (e) => {
    const keys = { ArrowLeft: 1, ArrowRight: -1, Home: -99, End: 99 };
    if (!(e.key in keys)) return;
    e.preventDefault();
    const n = clamp(featTab + keys[e.key], 0, FEAT_TABS.length - 1);
    selectTab(n);
    $(`#ftab-${n}`).focus();
  });
}
function selectTab(i, first = false) {
  featTab = i;
  $$('#featTabs .tab').forEach((b, k) => {
    b.setAttribute('aria-selected', String(k === i));
    b.tabIndex = k === i ? 0 : -1;
  });
  const grid = $('#featGrid');
  $('#featPanel').setAttribute('aria-labelledby', `ftab-${i}`);
  grid.innerHTML = featList(i).map((p, k) => card(p, k, first ? {} : { pop: true, revealed: true })).join('');
  if (first) observe(grid);
  refit(grid);
}

function renderBrands() {
  const list = [...S.brands].sort((a, b) => b.count - a.count);
  const half = Math.ceil(list.length / 2);
  const chip = (b, dup) => {
    const st = BRAND_STYLE[b.slug] || { label: b.name, c: '#7c3aed', w: 800, ls: '.06em' };
    const style = `--bc:${st.c};--w:${st.w};--ls:${st.ls}${st.fs ? `;--fs:${st.fs}` : ''}`;
    return `<a class="brand" href="${b.url}" style="${style}"${dup ? ' aria-hidden="true" tabindex="-1"' : ''}><span class="brand__name" dir="ltr">${esc(st.label)}</span><span class="brand__count">${fmt(b.count)} کالا</span></a>`;
  };
  const fill = (el, items) => {
    if (reduced()) {
      el.classList.add('marquee--static');
      el.innerHTML = `<div class="marquee__track">${items.map((b) => chip(b, false)).join('')}</div>`;
      return;
    }
    el.innerHTML = `<div class="marquee__track">${items.map((b) => chip(b, false)).join('')}${items.map((b) => chip(b, true)).join('')}</div>`;
  };
  fill($('#mq1'), list.slice(0, half));
  fill($('#mq2'), list.slice(half));
}

function renderStats() {
  const set = (sel, n) => {
    const el = $(sel);
    el.dataset.count = n;
    const box = el.closest('[data-reveal]');
    if (!box || box.classList.contains('is-in')) countUp(el);
  };
  set('#stProducts', S.products.length);
  set('#stBrands', S.brands.length);
  set('#stCats', S.cats.size);
}

function renderMega() {
  const panes = GROUPS.map((g, i) => {
    const subs = g.subs.map((id) => S.cats.get(id)).filter((c) => c && c.count);
    const inGroup = productsIn([g.main, ...g.subs]);
    const feat = inGroup.filter((p) => p.in_stock && p.thumb2).sort((a, b) => b.off - a.off)[0];
    const links = subs.map((c) => `<li><a class="mega__link" href="${c.url}"><img src="${catImg(c)}" alt="" width="46" height="46" loading="lazy" decoding="async"><span>${esc(c.name)}<small>${fmt(c.count)} کالا</small></span></a></li>`).join('');
    const counts = new Map();
    inGroup.forEach((p) => { if (p.brand) counts.set(p.brand, (counts.get(p.brand) || 0) + 1); });
    const brandChips = [...counts].sort((a, b) => b[1] - a[1]).slice(0, 6).map(([name, n]) => {
      const b = S.brands.find((x) => x.name.toLowerCase() === name.toLowerCase());
      return `<a class="mega__brand" href="${b ? b.url : '/shop/'}"><span dir="ltr">${esc(name)}</span><small>${fmt(n)}</small></a>`;
    }).join('');
    const brandsHTML = brandChips ? `<div class="mega__brands"><span class="mega__brands-t">برندهای محبوب این دسته</span><div>${brandChips}</div></div>` : '';
    // Short panes get a row of the group's best deals so the panel never looks empty.
    const tops = subs.length < 6 ? inGroup.filter((p) => p.in_stock && p !== feat).sort((a, b) => b.off - a.off || b.price - a.price).slice(0, 3) : [];
    const miniHTML = tops.length ? `<div class="mega__mini"><span class="mega__brands-t">پرتخفیف‌های این دسته</span><ul>${tops.map((p) => `<li><a class="mega__prod" href="${p.url}"><span class="mega__prod-img"><img src="${p.thumb}" alt="" width="400" height="400" loading="lazy" decoding="async">${p.off ? `<span class="off">${fmt(p.off)}٪</span>` : ''}</span>${fitted('span', 'mega__prod-t', p.title)}<span class="price__now">${fmt(p.price)}<small>تومان</small></span></a></li>`).join('')}</ul></div>` : '';
    const featHTML = feat ? `<a class="mega__feat" href="${feat.url}"><span class="mega__feat-tag">${ic('flame')}پیشنهاد ویژه این دسته</span><span class="mega__feat-art" aria-hidden="true"><img class="wm" src="${feat.thumb2}" alt="" width="400" height="400" loading="lazy" decoding="async"></span>${fitted('b', 'mega__feat-t', feat.title)}<span class="price">${feat.off ? `<span class="off">${fmt(feat.off)}٪</span>` : ''}<span class="price__now">${fmt(feat.price)}<small>تومان</small></span></span></a>` : '';
    return `<div class="mega__pane${i === 0 ? ' is-active' : ''}" data-pane="${i}"><div class="mega__main"><div class="mega__head"><strong class="mega__t">${esc(g.t)}</strong><a href="${catUrl(g.main)}">مشاهده همه${ic('arrow')}</a></div><ul class="mega__links">${links}</ul>${brandsHTML}${miniHTML}</div>${featHTML}</div>`;
  }).join('');
  const groups = GROUPS.map((g, i) => `<li><a class="mega__g${i === 0 ? ' is-active' : ''}" href="${catUrl(g.main)}" data-g="${i}"><span class="gi">${ic(g.ic)}</span><span>${esc(g.t)}</span>${ic('chev-l')}</a></li>`).join('');
  $('#mega').innerHTML = `<div class="mega__panel"><ul class="mega__groups">${groups}</ul><div class="mega__body">${panes}</div></div>`;
}

function renderDrawerCats() {
  $('#drawerCats').innerHTML = GROUPS.map((g, i) => {
    const subs = g.subs.map((id) => S.cats.get(id)).filter((c) => c && c.count);
    return `<li><button class="acc__btn" type="button" aria-expanded="false" aria-controls="acc-${i}"><span class="acc__ic">${ic(g.ic)}</span><span>${esc(g.t)}</span>${ic('chev-d', 'acc__chev')}</button>
<div class="acc__panel" id="acc-${i}" inert><div><ul>${subs.map((c) => `<li><a href="${c.url}"><img src="${catImg(c)}" alt="" width="30" height="30" loading="lazy" decoding="async">${esc(c.name)}</a></li>`).join('')}<li class="acc__all-li"><a class="acc__all" href="${catUrl(g.main)}">همه‌ی ${esc(g.t)}</a></li></ul></div></div></li>`;
  }).join('');
}

function renderFooterCats() {
  const ids = [68, 72, 66, 67, 102, 73];
  $('#ftrCats').innerHTML = ids.map((id) => S.cats.get(id)).filter(Boolean).map((c) => `<li><a href="${c.url}">${esc(c.name)}</a></li>`).join('');
}

function renderSearchCats() {
  const sel = $('#q-cat');
  sel.innerHTML = '<option value="">همه دسته‌ها</option>' + GROUPS.map((g, i) => {
    const c = S.cats.get(g.main);
    let slug = '';
    try {
      slug = c ? decodeURIComponent(c.slug) : '';
    } catch {
      slug = c ? c.slug : '';
    }
    return `<option value="${esc(slug)}" data-g="${i}">${esc(g.t)}</option>`;
  }).join('');
}

/* ------------------------------------------------------------------ */
/* Hero slider                                                         */
/* ------------------------------------------------------------------ */

function initSlider(el) {
  const slides = $$('.slide', el);
  const dotsEl = $('#dots');
  const pauseBtn = $('#slPause');
  if (slides.length < 2) return;
  let cur = 0;
  let userPaused = false;
  let hover = false;
  let focus = false;
  let visible = true;
  const auto = !reduced();
  el.style.setProperty('--dur', '6.5s');
  if (!auto) {
    el.classList.add('no-auto');
    pauseBtn.hidden = true;
  }
  dotsEl.innerHTML = slides.map((_, i) => `<button class="dot${i === 0 ? ' is-active' : ''}" type="button" aria-label="اسلاید ${fmt(i + 1)}"${i === 0 ? ' aria-current="true"' : ''}><span class="dot__bar"></span></button>`).join('');
  const dots = $$('.dot', dotsEl);

  const sync = () => el.classList.toggle('is-paused', userPaused || hover || focus || !visible || doc.hidden);
  function go(n, dir = 1) {
    n = (n + slides.length) % slides.length;
    if (n === cur) return;
    const prev = slides[cur];
    const next = slides[n];
    $$('img[loading="lazy"]', next).forEach((img) => { img.loading = 'eager'; });
    next.style.setProperty('--dir', dir);
    prev.classList.remove('is-active');
    prev.setAttribute('aria-hidden', 'true');
    prev.inert = true;
    next.classList.add('is-active');
    next.removeAttribute('aria-hidden');
    next.inert = false;
    dots[cur].classList.remove('is-active');
    dots[cur].removeAttribute('aria-current');
    dots[n].classList.add('is-active');
    dots[n].setAttribute('aria-current', 'true');
    cur = n;
    const after = slides[(n + 1) % slides.length];
    $$('img[loading="lazy"]', after).forEach((img) => { img.loading = 'eager'; });
  }
  dotsEl.addEventListener('click', (e) => {
    const d = e.target.closest('.dot');
    if (!d) return;
    const i = dots.indexOf(d);
    go(i, i > cur ? 1 : -1);
  });
  $('#slNext').addEventListener('click', () => go(cur + 1, 1));
  $('#slPrev').addEventListener('click', () => go(cur - 1, -1));
  pauseBtn.addEventListener('click', () => {
    userPaused = !userPaused;
    pauseBtn.setAttribute('aria-pressed', String(userPaused));
    pauseBtn.setAttribute('aria-label', userPaused ? 'ادامه نمایش خودکار' : 'توقف نمایش خودکار');
    sync();
  });
  if (auto) {
    el.addEventListener('animationend', (e) => {
      if (e.animationName === 'dotFill' && e.target.classList.contains('dot__bar')) go(cur + 1, 1);
    });
  }
  el.addEventListener('pointerenter', (e) => {
    if (e.pointerType === 'mouse') { hover = true; sync(); }
  });
  el.addEventListener('focusin', () => { focus = true; sync(); });
  el.addEventListener('focusout', (e) => {
    if (!el.contains(e.relatedTarget)) { focus = false; sync(); }
  });
  doc.addEventListener('visibilitychange', sync);
  new IntersectionObserver(([en]) => { visible = en.isIntersecting; sync(); }, { threshold: 0.25 }).observe(el);
  el.addEventListener('keydown', (e) => {
    if (e.target.closest('.dot, .glass-btn, .btn, a')) {
      if (e.key === 'ArrowLeft') { go(cur + 1, 1); e.preventDefault(); }
      if (e.key === 'ArrowRight') { go(cur - 1, -1); e.preventDefault(); }
    }
  });

  // Swipe (RTL: dragging to the right reveals the next slide) and pointer parallax.
  let pid = null;
  let sx = 0;
  let sy = 0;
  let dx = 0;
  let dragging = false;
  let moved = false;
  let raf = 0;
  let tx = 0;
  let ty = 0;
  const parallax = (e) => {
    if (e.pointerType !== 'mouse' || reduced()) return;
    const r = el.getBoundingClientRect();
    tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
    ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
    el.classList.add('is-tracking');
    if (!raf) {
      raf = requestAnimationFrame(() => {
        raf = 0;
        el.style.setProperty('--px', tx.toFixed(3));
        el.style.setProperty('--py', ty.toFixed(3));
      });
    }
  };
  el.addEventListener('pointerdown', (e) => {
    if (e.button !== 0 || e.target.closest('.slider__ui')) return;
    pid = e.pointerId;
    sx = e.clientX;
    sy = e.clientY;
    dx = 0;
    dragging = false;
    moved = false;
  });
  el.addEventListener('pointermove', (e) => {
    parallax(e);
    if (pid !== e.pointerId) return;
    const mx = e.clientX - sx;
    const my = e.clientY - sy;
    if (!dragging) {
      if (Math.abs(mx) > 8 && Math.abs(mx) > Math.abs(my) * 1.2) {
        dragging = true;
        moved = true;
        try { el.setPointerCapture(pid); } catch { /* pointer already released */ }
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
      if (Math.abs(dx) > 48) go(dx > 0 ? cur + 1 : cur - 1, dx > 0 ? 1 : -1);
      el.style.setProperty('--drag', '0px');
      el.classList.remove('is-dragging');
    }
    pid = null;
    dragging = false;
  };
  el.addEventListener('pointerup', end);
  el.addEventListener('pointercancel', end);
  el.addEventListener('pointerleave', (e) => {
    if (e.pointerType === 'mouse') {
      hover = false;
      el.classList.remove('is-tracking');
      el.style.setProperty('--px', '0');
      el.style.setProperty('--py', '0');
      sync();
    }
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

/* Instant offer rotation */
function initFlash(el, count) {
  const items = $$('.fitem', el);
  const dotsEl = $('#flashDots');
  if (items.length < 2) return;
  let cur = 0;
  let hold = false;
  dotsEl.innerHTML = items.map((_, i) => `<button class="dot${i === 0 ? ' is-active' : ''}" type="button" aria-label="پیشنهاد ${fmt(i + 1)} از ${fmt(count)}"${i === 0 ? ' aria-current="true"' : ''}></button>`).join('');
  const dots = $$('.dot', dotsEl);
  const bar = $('.flash__bar i', el);
  const restart = () => {
    if (reduced()) return;
    el.classList.remove('is-running');
    void bar.offsetWidth;
    el.classList.add('is-running');
  };
  const show = (n) => {
    n = (n + items.length) % items.length;
    if (n === cur) return;
    items[cur].classList.remove('is-active');
    items[cur].setAttribute('aria-hidden', 'true');
    items[cur].inert = true;
    items[n].classList.add('is-active');
    items[n].removeAttribute('aria-hidden');
    items[n].inert = false;
    dots[cur].classList.remove('is-active');
    dots[cur].removeAttribute('aria-current');
    dots[n].classList.add('is-active');
    dots[n].setAttribute('aria-current', 'true');
    cur = n;
    restart();
  };
  dotsEl.addEventListener('click', (e) => {
    const d = e.target.closest('.dot');
    if (d) show(dots.indexOf(d));
  });
  el.style.setProperty('--fdur', '7s');
  bar.addEventListener('animationend', () => show(cur + 1));
  const sync = () => el.classList.toggle('is-paused', hold || doc.hidden);
  el.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') { hold = true; sync(); } });
  el.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') { hold = false; sync(); } });
  el.addEventListener('focusin', () => { hold = true; sync(); });
  el.addEventListener('focusout', (e) => { if (!el.contains(e.relatedTarget)) { hold = false; sync(); } });
  doc.addEventListener('visibilitychange', sync);
  restart();
}

/* ------------------------------------------------------------------ */
/* Countdowns                                                          */
/* ------------------------------------------------------------------ */

const endOfDay = () => {
  const d = new Date();
  d.setHours(24, 0, 0, 0);
  return d.getTime();
};
const nextSlot = (hours = 3) => {
  const d = new Date();
  d.setHours((Math.floor(d.getHours() / hours) + 1) * hours, 0, 0, 0);
  return d.getTime();
};
const split = (ms) => {
  const s = Math.max(0, Math.floor(ms / 1000));
  return [Math.floor(s / 3600), Math.floor((s % 3600) / 60), s % 60];
};

const flipDigit = () => '<span class="flip"><span class="flip__t"><i>۰</i></span><span class="flip__b"><i>۰</i></span><span class="flip__ft"><i>۰</i></span><span class="flip__fb"><i>۰</i></span></span>';
function buildFlipClock(el) {
  const units = [['h', 'ساعت'], ['m', 'دقیقه'], ['s', 'ثانیه']];
  el.innerHTML = units.map(([k, label], i) => `${i ? '<span class="cd__sep" aria-hidden="true">:</span>' : ''}<span class="cd__u" aria-hidden="true"><span class="cd__d" data-u="${k}">${flipDigit()}${flipDigit()}</span><span class="cd__l">${label}</span></span>`).join('');
}
function setFlip(el, d) {
  if (el.dataset.v === d) return;
  const old = el.dataset.v;
  el.dataset.v = d;
  const [t, b, ft, fb] = Array.from(el.children).map((x) => x.firstElementChild);
  if (old === undefined || reduced()) {
    t.textContent = d;
    b.textContent = d;
    return;
  }
  t.textContent = d;
  b.textContent = old;
  ft.textContent = old;
  fb.textContent = d;
  el.classList.remove('go');
  void el.offsetWidth;
  el.classList.add('go');
  clearTimeout(el.tm);
  el.tm = setTimeout(() => {
    b.textContent = d;
    el.classList.remove('go');
  }, 680);
}

function startClocks() {
  const cd = $('#offersCd');
  buildFlipClock(cd);
  const flashTimer = $('#flashTimer');
  const flashSpans = $$('span', flashTimer);
  let lastLabel = '';
  const tick = () => {
    const now = Date.now();
    const [h, m, s] = split(endOfDay() - now);
    const digits = pad2(h) + pad2(m) + pad2(s);
    $$('.flip', cd).forEach((f, i) => setFlip(f, digits[i]));
    const label = `${fmt(h)} ساعت و ${fmt(m)} دقیقه تا پایان پیشنهادهای امروز`;
    if (label !== lastLabel) {
      cd.setAttribute('aria-label', label);
      lastLabel = label;
    }
    const [fh, fm, fs] = split(nextSlot(3) - now);
    [fh, fm, fs].forEach((v, i) => { flashSpans[i].textContent = pad2(v); });
    flashTimer.setAttribute('aria-label', `${fmt(fh)} ساعت و ${fmt(fm)} دقیقه باقی‌مانده`);
    setTimeout(tick, 1000 - (Date.now() % 1000) + 8);
  };
  tick();
}

/* ------------------------------------------------------------------ */
/* Rails                                                               */
/* ------------------------------------------------------------------ */

// Edge state drives the soft fade masks: a fade only shows on a side that still has cards to reveal.
function railState(rail) {
  if (!rail || !rail.id) return;
  const max = rail.scrollWidth - rail.clientWidth;
  const pos = Math.abs(rail.scrollLeft);
  const atStart = pos <= 2;
  const atEnd = pos >= max - 2;
  rail.classList.toggle('is-scrollable', max > 2);
  rail.classList.toggle('at-start', atStart);
  rail.classList.toggle('at-end', atEnd);
  $$(`[data-rail="${rail.id}"]`).forEach((b) => {
    const prev = b.dataset.dir === 'prev';
    b.disabled = max <= 2 || (prev ? atStart : atEnd);
  });
}
function setupRails() {
  doc.addEventListener('click', (e) => {
    const b = e.target.closest('[data-rail]');
    if (!b) return;
    const rail = doc.getElementById(b.dataset.rail);
    if (!rail) return;
    const rtl = getComputedStyle(rail).direction === 'rtl';
    const step = rail.clientWidth * 0.86 * (b.dataset.dir === 'next' ? 1 : -1) * (rtl ? -1 : 1);
    rail.scrollBy({ left: step, behavior: reduced() ? 'auto' : 'smooth' });
  });
  const rails = () => $$('.rail[id], #rank');
  rails().forEach((rail) => {
    let t = 0;
    rail.addEventListener('scroll', () => {
      if (t) return;
      t = requestAnimationFrame(() => { t = 0; railState(rail); });
    }, { passive: true });
  });
  let rt = 0;
  addEventListener('resize', () => {
    clearTimeout(rt);
    rt = setTimeout(() => {
      rails().forEach(railState);
      fitAll();
    }, 120);
  });
}

/* ------------------------------------------------------------------ */
/* Reveal + count-up                                                   */
/* ------------------------------------------------------------------ */

function countUp(el) {
  const to = Number(el.dataset.count) || 0;
  if (reduced() || !to) {
    el.textContent = fmt(to);
    return;
  }
  const t0 = performance.now();
  const dur = 1300;
  const step = (t) => {
    const k = clamp((t - t0) / dur, 0, 1);
    el.textContent = fmt(Math.round(to * (1 - (1 - k) ** 3)));
    if (k < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
  for (const en of entries) {
    if (!en.isIntersecting) continue;
    const el = en.target;
    io.unobserve(el);
    el.classList.add('is-in');
    $$('[data-count]', el).forEach(countUp);
    if (el.matches('[data-count]')) countUp(el);
  }
}, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 }) : null;

function observe(scope = doc) {
  const els = $$('[data-reveal]:not(.is-in)', scope);
  if (!io || reduced()) {
    els.forEach((el) => {
      el.classList.add('is-in');
      $$('[data-count]', el).forEach(countUp);
    });
    return;
  }
  els.forEach((el) => io.observe(el));
}

/* ------------------------------------------------------------------ */
/* Cart, wishlist, toasts, fly-to-cart                                 */
/* ------------------------------------------------------------------ */

const cartQty = () => S.cart.reduce((n, x) => n + x.qty, 0);
function updateBadges(bump = '') {
  const set = (sel, n, doBump) => $$(sel).forEach((b) => {
    b.textContent = fmt(n);
    b.classList.toggle('is-empty', n === 0);
    if (doBump && n > 0) {
      b.classList.remove('bump');
      void b.offsetWidth;
      b.classList.add('bump');
    }
  });
  set('[data-cart-count]', cartQty(), bump === 'cart');
  set('[data-wish-count]', S.wish.size, bump === 'wish');
  const n = cartQty();
  const label = n ? `سبد خرید، ${fmt(n)} کالا` : 'سبد خرید، خالی';
  $('#cartBtn').setAttribute('aria-label', label);
  $('#bnavCart').setAttribute('aria-label', label);
  const w = S.wish.size;
  $('.act--wish').setAttribute('aria-label', w ? `علاقه‌مندی‌ها، ${fmt(w)} کالا` : 'علاقه‌مندی‌ها');
}

function flyToCart(img, src) {
  return new Promise((resolve) => {
    const target = mq.phone.matches ? $('#bnavCart .ic') : $('#cartBtn');
    if (!img || !target || reduced() || !img.getBoundingClientRect().width) {
      resolve();
      return;
    }
    const a = img.getBoundingClientRect();
    const b = target.getBoundingClientRect();
    const size = Math.min(a.width, a.height, 150);
    const fly = doc.createElement('div');
    fly.className = 'fly';
    fly.style.cssText = `left:${a.left + a.width / 2 - size / 2}px;top:${a.top + a.height / 2 - size / 2}px;width:${size}px;height:${size}px`;
    const pic = doc.createElement('img');
    pic.src = src;
    pic.alt = '';
    fly.appendChild(pic);
    doc.body.appendChild(fly);
    const dx = b.left + b.width / 2 - (a.left + a.width / 2);
    const dy = b.top + b.height / 2 - (a.top + a.height / 2);
    const lift = Math.min(dy, 0) - 90;
    const dur = 820;
    fly.animate([{ transform: 'translateX(0)' }, { transform: `translateX(${dx}px)` }], { duration: dur, easing: 'cubic-bezier(.5,0,.5,1)', fill: 'forwards' });
    const anim = pic.animate([
      { transform: 'translateY(0) scale(1) rotate(0deg)', opacity: 1 },
      { transform: `translateY(${lift}px) scale(.62) rotate(-10deg)`, opacity: 1, offset: 0.38 },
      { transform: `translateY(${dy}px) scale(.14) rotate(-28deg)`, opacity: 0.5 },
    ], { duration: dur, easing: 'cubic-bezier(.33,0,.67,1)', fill: 'forwards' });
    anim.onfinish = () => {
      fly.remove();
      resolve();
    };
  });
}

function toast(p) {
  const wrap = $('#toasts');
  const el = doc.createElement('div');
  el.className = 'toast';
  el.innerHTML = `<img src="${p.thumb}" alt=""><span class="toast__ok" aria-hidden="true">${ic('check')}</span><div><b>به سبد خرید اضافه شد</b>${fitted('span', 'toast__t', p.title)}</div><button class="toast__btn" type="button" data-open="cart">مشاهده سبد</button>`;
  wrap.appendChild(el);
  while (wrap.children.length > 3) wrap.firstElementChild.remove();
  refit(el);
  let left = 3600;
  let started = Date.now();
  let timer = setTimeout(kill, left);
  function kill() {
    el.classList.add('is-out');
    setTimeout(() => el.remove(), 360);
  }
  el.addEventListener('pointerenter', () => {
    clearTimeout(timer);
    left -= Date.now() - started;
    el.style.setProperty('--tps', 'paused');
  });
  el.addEventListener('pointerleave', () => {
    started = Date.now();
    timer = setTimeout(kill, Math.max(left, 800));
    el.style.removeProperty('--tps');
  });
}

function addToCart(id, btn) {
  const p = S.byId.get(id);
  if (!p || !p.in_stock) return;
  const item = S.cart.find((x) => x.id === id);
  if (item) item.qty += 1;
  else S.cart.push({ id, qty: 1 });
  store.set('aurora-cart', S.cart);
  btn.classList.add('is-done');
  clearTimeout(btn.tm);
  btn.tm = setTimeout(() => btn.classList.remove('is-done'), 1700);
  const holder = btn.closest('.pc, .fitem');
  const img = holder ? $('img', holder) : null;
  flyToCart(img, p.thumb).then(() => {
    updateBadges('cart');
    const cb = $('#cartBtn');
    cb.classList.remove('wiggle');
    void cb.offsetWidth;
    cb.classList.add('wiggle');
  });
  toast(p);
  if ($('#cart').classList.contains('is-open')) renderCart();
}

function toggleWish(id, btn) {
  const on = !S.wish.has(id);
  if (on) S.wish.add(id);
  else S.wish.delete(id);
  store.set('aurora-wish', [...S.wish]);
  $$(`[data-wish="${id}"]`).forEach((b) => b.setAttribute('aria-pressed', String(on)));
  if (on && !reduced()) {
    const burst = doc.createElement('span');
    burst.className = 'burst';
    burst.setAttribute('aria-hidden', 'true');
    const colors = ['#ec4899', '#f59e0b', '#8b5cf6', '#f43f5e', '#6366f1', '#fbbf24'];
    burst.innerHTML = colors.map((c, i) => `<i style="--a:${i * 60}deg;--c:${c}"></i>`).join('');
    btn.appendChild(burst);
    setTimeout(() => burst.remove(), 700);
  }
  updateBadges(on ? 'wish' : '');
}

const emptyCart = `<div class="cart-empty"><svg viewBox="0 0 120 120" aria-hidden="true"><defs><linearGradient id="ce-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset=".6" stop-color="#a855f7"/><stop offset="1" stop-color="#ec4899"/></linearGradient></defs><circle cx="60" cy="62" r="46" fill="#f4f1ff"/><path d="M36 46h48l-4 40a6 6 0 0 1-6 5.5H46a6 6 0 0 1-6-5.5L36 46Z" fill="#fff" stroke="url(#ce-g)" stroke-width="3.5" stroke-linejoin="round"/><path d="M48 54V42a12 12 0 0 1 24 0v12" fill="none" stroke="url(#ce-g)" stroke-width="3.5" stroke-linecap="round"/><path d="M92 22c1 6 3.6 8.6 9.6 9.6-6 1-8.6 3.6-9.6 9.6-1-6-3.6-8.6-9.6-9.6 6-1 8.6-3.6 9.6-9.6Z" fill="#fbbf24"/><circle cx="24" cy="34" r="3.4" fill="#f9a8d4"/></svg><b>سبد خرید شما خالی است</b><span>گجت‌های اورجینال را با دکمه «+» به سبد اضافه کنید.</span><a class="btn btn--sm" href="#offers" data-close-link>دیدن پیشنهادهای شگفت‌انگیز</a></div>`;

function renderCart() {
  const body = $('#cartBody');
  const foot = $('#cartFoot');
  const items = S.cart.map((x) => ({ ...x, p: S.byId.get(x.id) })).filter((x) => x.p);
  const n = cartQty();
  $('#cartCount').textContent = n ? `(${fmt(n)} کالا)` : '';
  if (!items.length) {
    body.innerHTML = emptyCart;
    foot.hidden = true;
    return;
  }
  body.innerHTML = `<ul>${items.map(({ p, qty }) => `<li class="citem"><img src="${p.thumb}" alt="${esc(p.name)}" width="68" height="68" loading="lazy"><div>${fitted('p', 'citem__t', p.title, p.url)}<div class="citem__row"><div class="qty"><button type="button" data-qty="${p.id}" data-d="1" aria-label="افزایش تعداد">${ic('plus')}</button><output aria-live="polite">${fmt(qty)}</output><button type="button" data-qty="${p.id}" data-d="-1" aria-label="${qty > 1 ? 'کاهش تعداد' : 'حذف از سبد خرید'}">${ic(qty > 1 ? 'minus' : 'trash')}</button></div><span class="price__now">${fmt(p.price * qty)}<small>تومان</small></span></div></div></li>`).join('')}</ul>`;
  const total = items.reduce((s, x) => s + x.p.price * x.qty, 0);
  const saved = items.reduce((s, x) => s + Math.max(0, x.p.regular - x.p.price) * x.qty, 0);
  $('#cartTotal').innerHTML = `${fmt(total)} <small>تومان</small>`;
  $('#cartSave').innerHTML = saved ? `${ic('sparkle')} سود شما از این خرید: ${fmt(saved)} تومان` : '';
  foot.hidden = false;
  refit(body);
}

/* ------------------------------------------------------------------ */
/* Drawers                                                             */
/* ------------------------------------------------------------------ */

const drawers = (() => {
  const scrim = $('#scrim');
  let open = null;
  let lastFocus = null;
  const focusables = (el) => $$('a[href], button:not([disabled]), input, select, [tabindex]:not([tabindex="-1"])', el).filter((x) => x.offsetParent !== null && !x.closest('[inert]'));
  function show(id, trigger) {
    const d = doc.getElementById(id);
    if (!d) return;
    if (open === d) return;
    if (open) hide(true);
    closeMega();
    closeSugg();
    lastFocus = trigger || doc.activeElement;
    open = d;
    // Toasts never sit on top of an open drawer.
    $$('#toasts .toast').forEach((t) => t.remove());
    if (id === 'cart') renderCart();
    d.hidden = false;
    scrim.hidden = false;
    root.classList.add('is-locked');
    refit(d);
    requestAnimationFrame(() => requestAnimationFrame(() => {
      d.classList.add('is-open');
      scrim.classList.add('is-on');
    }));
    $$(`[aria-controls="${id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'true'));
    setTimeout(() => {
      const f = $('[data-close]', d);
      if (f) f.focus({ preventScroll: true });
    }, 80);
    bnavSet(id === 'drawer' ? 1 : 3);
  }
  function hide(instant = false, restore = true) {
    if (!open) return;
    const d = open;
    open = null;
    d.classList.remove('is-open');
    scrim.classList.remove('is-on');
    root.classList.remove('is-locked');
    $$(`[aria-controls="${d.id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'false'));
    const done = () => {
      if (!d.classList.contains('is-open')) d.hidden = true;
      if (!open) scrim.hidden = true;
    };
    if (instant || reduced()) done();
    else setTimeout(done, 480);
    if (restore && lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    bnavSet(0);
  }
  scrim.addEventListener('click', () => hide());
  doc.addEventListener('keydown', (e) => {
    if (!open) return;
    if (e.key === 'Escape') {
      hide();
      return;
    }
    if (e.key === 'Tab') {
      const f = focusables(open);
      if (!f.length) return;
      const first = f[0];
      const last = f[f.length - 1];
      if (e.shiftKey && doc.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && doc.activeElement === last) { first.focus(); e.preventDefault(); }
    }
  });
  return { show, hide, get open() { return open; } };
})();

function bnavSet(i) {
  const nav = $('#bnav');
  nav.style.setProperty('--idx', i);
  $$('.bnav__i', nav).forEach((b) => {
    const on = Number(b.dataset.idx) === i;
    b.classList.toggle('is-active', on);
    if (b.tagName === 'A') {
      if (on && i === 0) b.setAttribute('aria-current', 'page');
      else b.removeAttribute('aria-current');
    }
  });
}

/* ------------------------------------------------------------------ */
/* Mega menu (desktop)                                                 */
/* ------------------------------------------------------------------ */

const megaEl = $('#mega');
const megaBtn = $('#megaBtn');
const megaWrap = $('#megaWrap');
const megaScrim = $('#megaScrim');
let megaOpenT = 0;
let megaCloseT = 0;
function openMega() {
  clearTimeout(megaCloseT);
  if (!mq.desk.matches || !megaEl.innerHTML) return;
  if (!megaEl.hidden) return;
  closeSugg();
  megaEl.hidden = false;
  megaEl.classList.add('is-open');
  megaBtn.setAttribute('aria-expanded', 'true');
  megaScrim.classList.add('is-on');
  $('#hdr').classList.remove('is-tucked');
  refit($('.mega__pane.is-active', megaEl) || megaEl);
}
function closeMega() {
  clearTimeout(megaOpenT);
  if (megaEl.hidden) return;
  megaEl.hidden = true;
  megaEl.classList.remove('is-open');
  megaBtn.setAttribute('aria-expanded', 'false');
  megaScrim.classList.remove('is-on');
}
function setupMega() {
  megaWrap.addEventListener('pointerenter', (e) => {
    if (e.pointerType !== 'mouse') return;
    clearTimeout(megaCloseT);
    megaOpenT = setTimeout(openMega, 140);
  });
  megaWrap.addEventListener('pointerleave', (e) => {
    if (e.pointerType !== 'mouse') return;
    clearTimeout(megaOpenT);
    megaCloseT = setTimeout(closeMega, 260);
  });
  megaBtn.addEventListener('click', () => {
    if (megaEl.hidden) {
      openMega();
      const first = $('.mega__g', megaEl);
      if (first && megaBtn.matches(':focus-visible')) first.focus();
    } else closeMega();
  });
  const activate = (g) => {
    const i = g.dataset.g;
    $$('.mega__g', megaEl).forEach((x) => x.classList.toggle('is-active', x === g));
    $$('.mega__pane', megaEl).forEach((x) => x.classList.toggle('is-active', x.dataset.pane === i));
    refit($(`.mega__pane[data-pane="${i}"]`, megaEl));
  };
  megaEl.addEventListener('pointerover', (e) => {
    const g = e.target.closest('.mega__g');
    if (g && !g.classList.contains('is-active')) activate(g);
  });
  megaEl.addEventListener('focusin', (e) => {
    const g = e.target.closest('.mega__g');
    if (g) activate(g);
  });
  megaWrap.addEventListener('focusout', (e) => {
    if (!megaWrap.contains(e.relatedTarget)) closeMega();
  });
  megaScrim.addEventListener('click', closeMega);
  doc.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !megaEl.hidden) {
      closeMega();
      megaBtn.focus();
    }
  });
  mq.desk.addEventListener('change', closeMega);
}

/* ------------------------------------------------------------------ */
/* Search with live suggestions                                        */
/* ------------------------------------------------------------------ */

const qInput = $('#q');
const qCat = $('#q-cat');
const sugg = $('#sugg');
let INDEX = [];
let activeOpt = -1;
let suggTimer = 0;

function norm(s) {
  return String(s || '').toLowerCase()
    .replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/[ۀة]/g, 'ه').replace(/[أإآ]/g, 'ا')
    .replace(/[ً-ٰٟ]/g, '')
    .replace(/[۰-۹]/g, (d) => String(FA_DIGITS.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/‌/g, ' ')
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();
}
function buildIndex() {
  INDEX = S.products.map((p) => {
    const syn = SYNONYMS[p.brand.toLowerCase()] || '';
    const hay = norm(`${p.name} ${p.brand} ${p.cats.join(' ')} ${syn}`);
    return { p, hay, compact: hay.replace(/ /g, ''), nname: norm(p.name), nbrand: norm(`${p.brand} ${syn}`) };
  });
}
function runSearch(q) {
  const nq = norm(q);
  if (!nq) return { products: [], cats: [], total: 0 };
  const toks = nq.split(' ').filter(Boolean);
  const cq = nq.replace(/ /g, '');
  const opt = qCat.selectedOptions[0];
  const g = opt && opt.dataset.g !== undefined ? GROUPS[Number(opt.dataset.g)] : null;
  const scope = g ? subtree([g.main, ...g.subs]) : null;
  const hits = [];
  for (const it of INDEX) {
    if (scope && !inSet(it.p, scope)) continue;
    const all = toks.every((t) => it.hay.includes(t) || it.compact.includes(t));
    if (!all && !it.compact.includes(cq)) continue;
    let score = 0;
    if (it.nname.startsWith(nq)) score += 50;
    if (it.nname.includes(nq)) score += 30;
    if (it.compact.includes(cq)) score += 10;
    if (toks.some((t) => it.nbrand.includes(t))) score += 8;
    if (it.p.in_stock) score += 12;
    score += it.p.off / 10;
    hits.push([score, it.p]);
  }
  hits.sort((a, b) => b[0] - a[0]);
  const cats = [...S.cats.values()].filter((c) => c.count > 0 && norm(c.name).replace(/ /g, '').includes(cq)).slice(0, 4);
  return { products: hits.slice(0, 6).map((h) => h[1]), cats, total: hits.length };
}
const VARIANTS = { 'ی': '[یيى]', 'ک': '[کك]', 'ه': '[هۀة]', 'ا': '[اآأإ]' };
function tokRe(t) {
  return Array.from(t).map((ch) => {
    if (VARIANTS[ch]) return VARIANTS[ch];
    if (/\d/.test(ch)) return `[${ch}${FA_DIGITS[ch]}]`;
    return ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }).join('‌?');
}
function highlight(text, q) {
  const toks = norm(q).split(' ').filter((t) => t.length > 0);
  if (!toks.length) return esc(text);
  const re = new RegExp(toks.map(tokRe).join('|'), 'gi');
  let out = '';
  let last = 0;
  text.replace(re, (m, off) => {
    if (!m) return m;
    out += `${esc(text.slice(last, off))}<mark>${esc(m)}</mark>`;
    last = off + m.length;
    return m;
  });
  return out + esc(text.slice(last));
}
const optProduct = (p, q, i) => `<a class="sugg__opt" role="option" id="sg-${i}" href="${p.url}" aria-selected="false"><img src="${p.thumb}" alt="" width="52" height="52" loading="lazy"><span><span class="sugg__name" data-fit data-full="${esc(p.title)}" data-hl="${esc(q)}">${q ? highlight(p.title, q) : esc(p.title)}</span><span class="sugg__meta">${esc(p.brand || p.cats[0] || '')}${p.in_stock ? '' : ' · ناموجود'}</span></span><span class="sugg__price">${p.in_stock ? `${fmt(p.price)}<small>تومان</small>` : '<small>ناموجود</small>'}</span></a>`;
// Leaf categories with the most products, offered when a search finds nothing.
const topCats = (n) => [...S.cats.values()].filter((c) => c.count > 0 && !(S.kids.get(c.id) || []).length).sort((a, b) => b.count - a.count).slice(0, n);
function fitSugg() {
  $$('.sugg__name[data-fit]', sugg).forEach((el) => {
    const q = el.getAttribute('data-hl') || '';
    fitRender.set(el, (t) => (q ? highlight(t, q) : esc(t)));
  });
  refit(sugg);
}

function renderSugg() {
  if (!S.ready) {
    sugg.innerHTML = '<div class="sugg__empty">در حال بارگذاری محصولات…</div>';
    openSugg();
    return;
  }
  const q = qInput.value.trim();
  let html = '';
  let i = 0;
  if (!q) {
    const picks = S.products.filter((p) => p.in_stock && p.thumb2).sort((a, b) => b.off - a.off).slice(0, 3);
    html += `<div role="group" aria-label="جست‌وجوهای پرطرفدار"><div class="sugg__h" aria-hidden="true">${ic('trend')}جست‌وجوهای پرطرفدار</div><div class="sugg__chips">${POPULAR.map((t) => `<span class="chip-q" role="option" id="sg-${i++}" aria-selected="false" data-q="${esc(t)}">${ic('search')}${esc(t)}</span>`).join('')}</div></div>`;
    html += `<div role="group" aria-label="پیشنهاد گجتیم"><div class="sugg__h" aria-hidden="true">${ic('flame')}پرتخفیف‌های امروز</div>${picks.map((p) => optProduct(p, '', i++)).join('')}</div>`;
  } else {
    const r = runSearch(q);
    if (r.cats.length) {
      html += `<div role="group" aria-label="دسته‌بندی‌ها"><div class="sugg__h" aria-hidden="true">${ic('grid')}دسته‌بندی‌ها</div><div class="sugg__chips">${r.cats.map((c) => `<a class="chip-q" role="option" id="sg-${i++}" aria-selected="false" href="${c.url}">${ic('grid')}${highlight(c.name, q)}</a>`).join('')}</div></div>`;
    }
    if (r.products.length) {
      html += `<div role="group" aria-label="محصولات"><div class="sugg__h" aria-hidden="true">${ic('box')}محصولات</div>${r.products.map((p) => optProduct(p, q, i++)).join('')}</div>`;
      const url = `/?s=${encodeURIComponent(q)}&post_type=product${qCat.value ? `&product_cat=${encodeURIComponent(qCat.value)}` : ''}`;
      html += `<div class="sugg__foot"><a class="sugg__all" role="option" id="sg-${i++}" aria-selected="false" href="${url}">مشاهده همه ${fmt(r.total)} نتیجه برای «${esc(q)}»${ic('arrow')}</a></div>`;
    } else if (!r.cats.length) {
      html += `<div class="sugg__none" role="status"><span class="sugg__none-ic" aria-hidden="true">${ic('search-x')}</span><b>نتیجه‌ای برای «${esc(q)}» پیدا نشد</b><span>املای واژه را بررسی کنید یا یکی از پیشنهادهای زیر را ببینید.</span></div>`;
      html += `<div role="group" aria-label="پیشنهاد جست‌وجو"><div class="sugg__h" aria-hidden="true">${ic('sparkle')}پیشنهاد جست‌وجو</div><div class="sugg__chips">${topCats(6).map((c) => `<a class="chip-q" role="option" id="sg-${i++}" aria-selected="false" href="${c.url}">${ic('grid')}${esc(c.name)}</a>`).join('')}</div></div>`;
      html += `<div role="group" aria-label="پرفروش‌ترین‌ها"><div class="sugg__h" aria-hidden="true">${ic('trend')}پرفروش‌ترین‌ها</div>${bestList().slice(0, 3).map((p) => optProduct(p, '', i++)).join('')}</div>`;
    }
  }
  sugg.innerHTML = html;
  activeOpt = -1;
  qInput.removeAttribute('aria-activedescendant');
  openSugg();
  fitSugg();
}
function openSugg() {
  if (sugg.hidden) {
    sugg.hidden = false;
    qInput.setAttribute('aria-expanded', 'true');
    closeMega();
  }
}
function closeSugg() {
  if (sugg.hidden) return;
  sugg.hidden = true;
  qInput.setAttribute('aria-expanded', 'false');
  qInput.removeAttribute('aria-activedescendant');
  activeOpt = -1;
  if (mq.phone.matches) bnavSet(drawers.open ? (drawers.open.id === 'cart' ? 3 : 1) : 0);
}
function moveOpt(d) {
  const opts = $$('[role="option"]', sugg);
  if (!opts.length) return;
  if (activeOpt >= 0 && opts[activeOpt]) opts[activeOpt].setAttribute('aria-selected', 'false');
  activeOpt = (activeOpt + d + opts.length) % opts.length;
  const o = opts[activeOpt];
  o.setAttribute('aria-selected', 'true');
  qInput.setAttribute('aria-activedescendant', o.id);
  o.scrollIntoView({ block: 'nearest' });
}
function setupSearch() {
  qInput.addEventListener('focus', () => {
    renderSugg();
    if (mq.phone.matches) bnavSet(2);
  });
  qInput.addEventListener('input', () => {
    clearTimeout(suggTimer);
    suggTimer = setTimeout(renderSugg, 70);
  });
  qCat.addEventListener('change', () => {
    if (!sugg.hidden || qInput.value) renderSugg();
  });
  qInput.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') { if (sugg.hidden) renderSugg(); moveOpt(1); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { moveOpt(-1); e.preventDefault(); }
    else if (e.key === 'Escape') {
      if (!sugg.hidden) { closeSugg(); e.preventDefault(); }
      else if (qInput.value) qInput.value = '';
    } else if (e.key === 'Enter' && activeOpt >= 0) {
      const o = $$('[role="option"]', sugg)[activeOpt];
      if (o) { e.preventDefault(); o.click(); }
    }
  });
  sugg.addEventListener('mousedown', (e) => {
    if (e.target.closest('[data-q]')) e.preventDefault();
  });
  sugg.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-q]');
    if (!chip) return;
    e.preventDefault();
    qInput.value = chip.dataset.q;
    qInput.focus();
    renderSugg();
  });
  $('#search').addEventListener('submit', (e) => {
    if (!qInput.value.trim()) {
      e.preventDefault();
      qInput.focus();
    }
  });
  $('#search').addEventListener('focusout', (e) => {
    if (!$('#search').contains(e.relatedTarget)) setTimeout(() => { if (!$('#search').contains(doc.activeElement)) closeSugg(); }, 120);
  });
  doc.addEventListener('pointerdown', (e) => {
    if (!sugg.hidden && !e.target.closest('#search')) closeSugg();
  });
}

/* ------------------------------------------------------------------ */
/* Header behaviour, scroll effects                                    */
/* ------------------------------------------------------------------ */

function setupScroll() {
  const hdr = $('#hdr');
  const toTop = $('#toTop');
  const slider = $('#slider');
  let lastY = scrollY;
  let ticking = false;
  const measure = () => {
    const s = $('#search');
    hdr.style.setProperty('--row1', `${Math.max(0, s.offsetTop - 8)}px`);
  };
  measure();
  addEventListener('resize', measure);
  const update = () => {
    ticking = false;
    const y = scrollY;
    hdr.classList.toggle('is-scrolled', y > 8);
    const busy = !megaEl.hidden || !sugg.hidden || drawers.open || doc.activeElement === qInput;
    if (y < 160 || busy) hdr.classList.remove('is-tucked');
    else if (y > lastY + 6) hdr.classList.add('is-tucked');
    else if (y < lastY - 6) hdr.classList.remove('is-tucked');
    if (Math.abs(y - lastY) > 6) lastY = y;
    toTop.classList.toggle('is-on', y > 900);
    if (!reduced() && slider) slider.style.setProperty('--sy', clamp(y / 700, 0, 1).toFixed(3));
  };
  addEventListener('scroll', () => {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(update);
    }
  }, { passive: true });
  toTop.addEventListener('click', () => {
    scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
    $('#main').focus({ preventScroll: true });
  });
}

function setupTicker() {
  const items = $$('#ticker li');
  if (items.length < 2 || reduced()) return;
  let i = 0;
  setInterval(() => {
    if (doc.hidden) return;
    const cur = items[i];
    i = (i + 1) % items.length;
    const nx = items[i];
    cur.classList.remove('is-on');
    cur.classList.add('is-out');
    nx.classList.remove('is-out');
    nx.classList.add('is-on');
    setTimeout(() => cur.classList.remove('is-out'), 700);
  }, 4200);
}

/* ------------------------------------------------------------------ */
/* Delegated interactions                                              */
/* ------------------------------------------------------------------ */

function setupDelegates() {
  doc.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    if (add) {
      e.preventDefault();
      addToCart(Number(add.dataset.add), add);
      return;
    }
    const wish = e.target.closest('[data-wish]');
    if (wish) {
      e.preventDefault();
      toggleWish(Number(wish.dataset.wish), wish);
      return;
    }
    const opener = e.target.closest('[data-open]');
    if (opener) {
      e.preventDefault();
      if (drawers.open && drawers.open.id === opener.dataset.open) drawers.hide();
      else drawers.show(opener.dataset.open, opener);
      return;
    }
    if (e.target.closest('[data-close]')) {
      drawers.hide();
      return;
    }
    if (e.target.closest('[data-close-link]')) drawers.hide(false, false);
    const anchor = e.target.closest('a[href^="#"]');
    if (anchor) {
      const id = anchor.getAttribute('href').slice(1);
      const target = id ? doc.getElementById(id) : null;
      if (target) {
        e.preventDefault();
        const behavior = reduced() ? 'auto' : 'smooth';
        if (anchor.closest('#bnav')) scrollTo({ top: 0, behavior });
        else target.scrollIntoView({ behavior, block: 'start' });
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
      }
      return;
    }
    const acc = e.target.closest('.acc__btn');
    if (acc) {
      const open = acc.getAttribute('aria-expanded') !== 'true';
      $$('.acc__btn').forEach((b) => {
        const on = b === acc ? open : false;
        b.setAttribute('aria-expanded', String(on));
        doc.getElementById(b.getAttribute('aria-controls')).inert = !on;
      });
      return;
    }
    const qty = e.target.closest('[data-qty]');
    if (qty) {
      const id = Number(qty.dataset.qty);
      const item = S.cart.find((x) => x.id === id);
      if (item) {
        item.qty += Number(qty.dataset.d);
        if (item.qty <= 0) S.cart = S.cart.filter((x) => x.id !== id);
        store.set('aurora-cart', S.cart);
        renderCart();
        updateBadges();
        const again = $(`[data-qty="${id}"][data-d="${qty.dataset.d}"]`);
        (again || $('#cart [data-close]')).focus({ preventScroll: true });
      }
      return;
    }
    const act = e.target.closest('[data-act="search"]');
    if (act) {
      scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
      $('#hdr').classList.remove('is-tucked');
      qInput.focus({ preventScroll: true });
      bnavSet(2);
    }
  });

  // Ripple on press.
  doc.addEventListener('pointerdown', (e) => {
    const t = e.target.closest('.btn, .add, .search__go, .tab');
    if (!t || reduced()) return;
    const r = t.getBoundingClientRect();
    const s = doc.createElement('span');
    s.className = 'ripple';
    s.style.left = `${e.clientX - r.left}px`;
    s.style.top = `${e.clientY - r.top}px`;
    s.style.setProperty('--rs', ((Math.max(r.width, r.height) / 12) * 2.6).toFixed(1));
    t.appendChild(s);
    s.addEventListener('animationend', () => s.remove());
  }, { passive: true });

  // Cursor-following glow on cards.
  let glowRaf = 0;
  let glowEv = null;
  doc.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse') return;
    glowEv = e;
    if (glowRaf) return;
    glowRaf = requestAnimationFrame(() => {
      glowRaf = 0;
      const c = glowEv.target.closest && glowEv.target.closest('.pc__in');
      if (!c) return;
      const r = c.getBoundingClientRect();
      c.style.setProperty('--mx', `${glowEv.clientX - r.left}px`);
      c.style.setProperty('--my', `${glowEv.clientY - r.top}px`);
    });
  }, { passive: true });

  // Missing resized image: fall back to the full-size file.
  doc.addEventListener('error', (e) => {
    const img = e.target;
    if (img.tagName === 'IMG' && img.dataset.full && !img.dataset.failed) {
      img.dataset.failed = '1';
      img.src = img.dataset.full;
    }
  }, true);

  const news = $('#newsForm');
  news.addEventListener('submit', (e) => {
    e.preventDefault();
    const input = $('#newsEmail');
    const msg = $('#newsMsg');
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(input.value.trim());
    msg.classList.toggle('is-err', !ok);
    msg.textContent = ok ? 'ممنون! در نسخه‌ی نهایی، خبرنامه به همین ایمیل ارسال می‌شود.' : 'لطفاً یک ایمیل معتبر وارد کنید.';
    if (!ok) input.focus();
  });
}

/* ------------------------------------------------------------------ */
/* Boot                                                                */
/* ------------------------------------------------------------------ */

function renderAll() {
  renderStories();
  renderHero();
  renderFlash();
  renderRail('#offersRail', offerList());
  renderRail('#newestRail', newestList(), (p, i) => ({ isNew: i < 6 }));
  renderBanners();
  renderRank();
  renderCats();
  renderFeatTabs();
  selectTab(0, true);
  renderBrands();
  renderStats();
  renderMega();
  renderDrawerCats();
  renderFooterCats();
  renderSearchCats();
  buildIndex();
  updateBadges();
  observe();
  if (!sugg.hidden) renderSugg();
  fontsReady.then(() => {
    fitAll();
    $$('.rail[id], #rank').forEach(railState);
  });
}

function showError() {
  const box = '<li class="load-err">بارگذاری محصولات ناموفق بود. <button class="btn btn--sm" type="button" id="retry">تلاش دوباره</button></li>';
  ['#offersRail', '#newestRail', '#featGrid'].forEach((s) => { $(s).innerHTML = box; });
  doc.addEventListener('click', (e) => {
    if (e.target.id === 'retry') location.reload();
  });
}

async function load() {
  try {
    const res = await fetch(DATA_URL, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    prepare(await res.json());
    renderAll();
    root.classList.add('is-ready');
  } catch (err) {
    console.error('catalog load failed', err);
    showError();
  }
}

function boot() {
  skeletons();
  setupDelegates();
  setupRails();
  setupMega();
  setupSearch();
  setupScroll();
  setupTicker();
  startClocks();
  updateBadges();
  observe();
  load();
}

boot();
