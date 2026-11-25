#!/usr/bin/env node
// Brands product photos for the shop (product-listing skill, site.json "image_format"): the photo on
// white in an 800x800 frame, a bottom band with the logo and favicon on one side and the support
// phone on the other, and a very faint GADGETIM across the middle. Text is set by the browser with the
// theme's own fonts, so Persian letters and digits come out right (Gemini garbles text and digits).
//
// Usage:
//   node tools/product-image-band.cjs <out-dir> "<photo>=><name>" ["<photo>=><name>" ...]
// Writes <out-dir>/<name>.png (800x800). Convert each to WebP q85 for the package, e.g. with Local's PHP:
//   php -n -d extension_dir=<php>/ext -d extension=gd -r 'imagewebp(imagecreatefrompng($argv[1]), $argv[2], 85);' in.png out.webp
// Photos should already be on pure white (whiten near-white backgrounds of Gemini output first).
// Environment: GADGETIM_BROWSER overrides the browser (default: Microsoft Edge).
//
// The extra browser flags matter: without them headless Edge took more than 5 minutes per image on
// this machine (first-run and background network work); with them it takes about 2 seconds.

const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const repo = path.resolve(__dirname, '..').replace(/\\/g, '/');
const theme = repo + '/wp-content/themes/gadgetim/assets';
const browser = process.env.GADGETIM_BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const work = path.join(os.tmpdir(), 'gadgetim-image-band');
const sleep = (ms) => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
const [, , outDir, ...jobs] = process.argv;

if (!outDir || !jobs.length) {
	console.error('usage: node tools/product-image-band.cjs <out-dir> "<photo>=><name>" [...]');
	process.exit(2);
}

const site = JSON.parse(fs.readFileSync(repo + '/site.json', 'utf8'));
const fa = (s) => s.replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const digits = String(site.support_phone || '').replace(/\D/g, '');
// 07152855731 -> ۰۷۱ ۵۲۸۵ ۵۷۳۱ (area code, then two groups of four).
const phone = fa(digits.length === 11 ? `${digits.slice(0, 3)} ${digits.slice(3, 7)} ${digits.slice(7)}` : digits);
const svg = fs.readFileSync(theme + '/img/favicon.svg', 'utf8');
const fileUrl = (p) => 'file:///' + p.replace(/\\/g, '/').replace(/\[/g, '%5B').replace(/\]/g, '%5D');

const page = (src) => `<!doctype html><html dir="rtl"><head><meta charset="utf-8"><style>
@font-face{font-family:E;src:url("${fileUrl(theme + '/fonts/Estedad[wght].woff2')}") format("woff2");font-weight:100 900}
@font-face{font-family:V;src:url("${fileUrl(theme + '/fonts/Vazirmatn[wght].woff2')}") format("woff2");font-weight:100 900}
*{box-sizing:border-box}html,body{margin:0;width:800px;height:800px;background:#fff;overflow:hidden}
.p{position:absolute;inset:8px 18px 92px;display:flex;align-items:center;justify-content:center}
.p img{max-width:100%;max-height:100%;object-fit:contain;display:block}
.wm{position:absolute;left:0;right:0;top:300px;text-align:center;font:900 92px/1 E;letter-spacing:.14em;color:#4f46e5;opacity:.055;transform:rotate(-16deg);direction:ltr}
.b{position:absolute;left:14px;right:14px;bottom:12px;height:70px;border-radius:22px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;background:linear-gradient(120deg,#f4f1ff,#fdf2f8);border:1.5px solid #ece7ff}
.l{display:flex;align-items:center;gap:10px}.l svg{width:48px;height:48px}
.w{display:flex;flex-direction:column;line-height:1}.w b{font:900 28px/1.1 E;background:linear-gradient(90deg,#4f46e5,#9333ea 55%,#db2777);-webkit-background-clip:text;color:transparent}
.w i{font:700 10px/1 V;font-style:normal;letter-spacing:.42em;color:#65638a;margin-top:4px;direction:ltr;text-align:right}
.t{display:flex;align-items:center;gap:10px;padding:8px 18px 8px 10px;border-radius:999px;color:#fff;background:linear-gradient(120deg,#4f46e5,#7c3aed 42%,#c026d3 72%,#db2777)}
.t svg{width:34px;height:34px;padding:7px;border-radius:50%;background:rgba(255,255,255,.2);fill:none;stroke:#fff;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.t div{display:flex;flex-direction:column;gap:3px}.t small{font:700 11px/1 V}.t b{font:900 20px/1 V;direction:ltr;letter-spacing:.02em}
</style></head><body>
<div class="p"><img src="${src}"></div><div class="wm">${site.site_name_latin || 'GADGETIM'}</div>
<div class="b"><div class="l">${svg}<div class="w"><b>${site.site_name || 'گجتیم'}</b><i>${site.site_name_latin || 'GADGETIM'}</i></div></div>
<div class="t"><svg viewBox="0 0 24 24"><path d="M6.6 3.5h2.5l1.4 4-1.9 1.4a12 12 0 0 0 6.5 6.5l1.4-1.9 4 1.4v2.5a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2Z"/></svg><div><small>پشتیبانی و مشاوره خرید</small><b>${phone}</b></div></div></div>
</body></html>`;

fs.mkdirSync(outDir, { recursive: true });
fs.mkdirSync(work, { recursive: true });
for (const job of jobs) {
	const [src, name] = job.split('=>');
	if (!src || !name || !fs.existsSync(src)) {
		console.error('bad job (expected "<existing photo>=><name>"): ' + job);
		process.exit(1);
	}
	const html = path.join(work, name + '.html');
	fs.writeFileSync(html, page(fileUrl(path.resolve(src))));
	const png = path.join(outDir, name + '.png');
	fs.rmSync(png, { force: true });
	// Edge can return before its screenshot is on disk (2026-11-25: the first image of a run appeared a moment
	// after the size check failed), so wait for the file, and run the browser once more if it never comes.
	for (let attempt = 1; attempt <= 2 && !fs.existsSync(png); attempt++) {
		execFileSync(browser, [
			'--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions',
			'--disable-background-networking', '--disable-component-update', '--disable-sync', '--hide-scrollbars',
			'--allow-file-access-from-files', '--force-device-scale-factor=1', '--window-size=800,800',
			'--virtual-time-budget=3000', '--user-data-dir=' + path.join(work, 'profile'), '--screenshot=' + png, fileUrl(html),
		], { stdio: 'ignore' });
		for (let waited = 0; waited < 15000 && !fs.existsSync(png); waited += 200) {
			sleep(200);
		}
	}
	if (!fs.existsSync(png)) {
		console.error('no screenshot was written for ' + name);
		process.exit(1);
	}
	console.log(name, fs.statSync(png).size);
}
