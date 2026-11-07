#!/usr/bin/env node
// Visual QA: full-page screenshots of the Local site through headless Edge/Chrome and the
// DevTools protocol (real mobile emulation, colour scheme, reduced motion). Dev machine only.
//
// Usage:
//   node tools/screenshot.mjs --out <dir> [--scheme light|dark] [--motion reduce] [--full]
//        [--slice <css px>] [--scale <0.1-1>] [--wait <ms>] [--eval "<page js>"] [--viewport]
//        <name>=<url>@<device> ...
// --slice splits a full page into readable parts (name-01.png, name-02.png, ...).
// --viewport captures exactly what is on screen at the current scroll position (after --eval), with
// fixed and sticky layers where a visitor sees them; the default captures from the top of the page.
// Devices: mobile (390x844 @2x, touch), tablet (820x1180 @2x), desktop (1440x900 @1).
// Example:
//   node tools/screenshot.mjs --out shots --full home-m=http://gadgetim.local/@mobile home-d=http://gadgetim.local/@desktop
// Environment: GADGETIM_BROWSER overrides the browser path; GADGETIM_BROWSER_ARGS adds browser flags
// (space-separated), e.g. --host-resolver-rules="MAP gadgetim.local 127.0.0.1:10077" when Local's router is down.

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const DEVICES = {
	mobile: { width: 390, height: 844, deviceScaleFactor: 2, mobile: true },
	tablet: { width: 820, height: 1180, deviceScaleFactor: 2, mobile: true },
	desktop: { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false },
};
const MOBILE_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';

const args = process.argv.slice(2);
const opt = { out: 'shots', scheme: 'light', motion: '', full: false, slice: 0, scale: 1, wait: 2500, jobs: [] };
for (let i = 0; i < args.length; i++) {
	const a = args[i];
	if (a === '--out') opt.out = args[++i];
	else if (a === '--scheme') opt.scheme = args[++i];
	else if (a === '--motion') opt.motion = args[++i];
	else if (a === '--full') opt.full = true;
	else if (a === '--slice') { opt.slice = Number(args[++i]) || 0; opt.full = true; }
	else if (a === '--scale') opt.scale = Number(args[++i]) || 1;
	else if (a === '--wait') opt.wait = Number(args[++i]) || 2500;
	else if (a === '--eval') opt.eval = args[++i];
	else if (a === '--viewport') opt.viewport = true;
	else if (a.includes('=')) {
		const [name, rest] = a.split(/=(.+)/);
		const at = rest.lastIndexOf('@');
		const url = at > 0 ? rest.slice(0, at) : rest;
		const device = at > 0 ? rest.slice(at + 1) : 'desktop';
		if (!DEVICES[device]) throw new Error(`unknown device ${device}`);
		opt.jobs.push({ name, url, device });
	}
}
if (!opt.jobs.length) {
	console.error('usage: node tools/screenshot.mjs --out <dir> [--scheme light|dark] [--motion reduce] [--full] name=url@device ...');
	process.exit(2);
}

const candidates = [
	process.env.GADGETIM_BROWSER,
	'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
	'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
	'C:/Program Files/Google/Chrome/Application/chrome.exe',
].filter(Boolean);
const browser = candidates.find((p) => existsSync(p));
if (!browser) throw new Error('no Edge/Chrome found; set GADGETIM_BROWSER');

const profile = mkdtempSync(join(tmpdir(), 'gtm-shots-'));
const port = 9300 + Math.floor(Math.random() * 500);
const proc = spawn(browser, [
	'--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
	`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
	...(process.env.GADGETIM_BROWSER_ARGS || '').split(/\s+(?=--)/).filter(Boolean), 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function targetWs() {
	for (let i = 0; i < 60; i++) {
		try {
			const list = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
			const page = list.find((t) => t.type === 'page');
			if (page) return page.webSocketDebuggerUrl;
		} catch { /* not up yet */ }
		await sleep(250);
	}
	throw new Error('browser did not start');
}

function client(wsUrl) {
	const ws = new WebSocket(wsUrl);
	let id = 0;
	const pending = new Map();
	const listeners = [];
	ws.onmessage = (ev) => {
		const msg = JSON.parse(ev.data);
		if (msg.id && pending.has(msg.id)) {
			const { resolve: ok, reject } = pending.get(msg.id);
			pending.delete(msg.id);
			msg.error ? reject(new Error(msg.error.message)) : ok(msg.result);
		} else if (msg.method) listeners.forEach((l) => l(msg));
	};
	const ready = new Promise((r) => { ws.onopen = r; });
	return {
		ready,
		send: (method, params = {}) => new Promise((ok, reject) => { const n = ++id; pending.set(n, { resolve: ok, reject }); ws.send(JSON.stringify({ id: n, method, params })); }),
		once: (method) => new Promise((r) => { const l = (m) => { if (m.method === method) { listeners.splice(listeners.indexOf(l), 1); r(m.params); } }; listeners.push(l); }),
		on: (method, fn) => listeners.push((m) => { if (m.method === method) fn(m.params); }),
		close: () => ws.close(),
	};
}

const outDir = resolve(opt.out);
mkdirSync(outDir, { recursive: true });
const cdp = client(await targetWs());
await cdp.ready;
await cdp.send('Page.enable');
await cdp.send('Runtime.enable');
// Collect uncaught page exceptions and console errors for the report.
const errors = [];
let current = '';
cdp.on('Runtime.exceptionThrown', (p) => errors.push(`${current}: ${p.exceptionDetails?.exception?.description || p.exceptionDetails?.text}`));
cdp.on('Runtime.consoleAPICalled', (p) => { if (p.type === 'error') errors.push(`${current}: console.error ${p.args.map((a) => a.value ?? a.description ?? '').join(' ')}`); });

const features = [{ name: 'prefers-color-scheme', value: opt.scheme }];
if (opt.motion) features.push({ name: 'prefers-reduced-motion', value: opt.motion });
await cdp.send('Emulation.setEmulatedMedia', { features });

const report = [];
for (const job of opt.jobs) {
	current = job.name;
	const d = DEVICES[job.device];
	await cdp.send('Emulation.setDeviceMetricsOverride', { width: d.width, height: d.height, deviceScaleFactor: d.deviceScaleFactor, mobile: d.mobile });
	await cdp.send('Emulation.setTouchEmulationEnabled', { enabled: d.mobile, maxTouchPoints: d.mobile ? 5 : 1 });
	await cdp.send('Emulation.setUserAgentOverride', { userAgent: d.mobile ? MOBILE_UA : '' });
	const loaded = cdp.once('Page.loadEventFired');
	await cdp.send('Page.navigate', { url: job.url });
	await Promise.race([loaded, sleep(20000)]);
	await sleep(opt.wait);
	// Scroll through the page so lazy images and reveal-on-scroll content render, then return to the top.
	const info = await cdp.send('Runtime.evaluate', { returnByValue: true, awaitPromise: true, expression: `(async () => {
		const h = () => document.documentElement.scrollHeight;
		for (let y = 0; y < h(); y += innerHeight * 0.8) { scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); }
		scrollTo(0, 0); await new Promise(r => setTimeout(r, 400));
		const de = document.documentElement;
		return { title: document.title, scrollW: de.scrollWidth, clientW: de.clientWidth, height: h(), dir: de.dir, lang: de.lang,
			overflowX: de.scrollWidth > de.clientWidth + 1 };
	})()` });
	const v = info.result.value || {};
	// --eval "<js>": run page JS after load/scroll (e.g. open a menu) and let it settle before the capture.
	if (opt.eval) {
		await cdp.send('Runtime.evaluate', { expression: opt.eval, awaitPromise: true });
		await sleep(900);
	}
	const total = Math.min(v.height || d.height, 20000);
	// --viewport: one screenshot of the visible area where the page is scrolled to now (clip in page coordinates).
	if (opt.viewport) {
		const pos = await cdp.send('Runtime.evaluate', { returnByValue: true, expression: '({ x: scrollX, y: scrollY })' });
		const at = pos.result.value || { x: 0, y: 0 };
		const clip = { x: at.x, y: at.y, width: d.width, height: d.height, scale: opt.scale };
		const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, clip });
		const file = join(outDir, `${job.name}.png`);
		writeFileSync(file, Buffer.from(shot.data, 'base64'));
		report.push({ name: job.name, device: job.device, file, ...v });
		console.log(`${job.name}: viewport at y=${Math.round(at.y)} in ${outDir} dir=${v.dir} lang=${v.lang} overflowX=${v.overflowX}`);
		continue;
	}
	// --slice N: several readable screenshots of N CSS px each instead of one very tall image.
	const sliceH = opt.slice > 0 ? opt.slice : (opt.full ? total : d.height);
	const parts = opt.slice > 0 ? Math.ceil(total / sliceH) : 1;
	for (let i = 0; i < parts; i++) {
		const y = i * sliceH;
		const clip = { x: 0, y, width: d.width, height: Math.min(sliceH, total - y), scale: opt.scale };
		const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip });
		const file = join(outDir, parts > 1 ? `${job.name}-${String(i + 1).padStart(2, '0')}.png` : `${job.name}.png`);
		writeFileSync(file, Buffer.from(shot.data, 'base64'));
		report.push({ name: job.name, device: job.device, file, ...v });
	}
	console.log(`${job.name}: ${parts} file(s) in ${outDir} ${d.width}x${total} dir=${v.dir} lang=${v.lang} overflowX=${v.overflowX}`);
}
cdp.close();
proc.kill();
await sleep(300);
try { rmSync(profile, { recursive: true, force: true }); } catch { /* browser may still hold files */ }
if (errors.length) console.log('page errors:', errors);
