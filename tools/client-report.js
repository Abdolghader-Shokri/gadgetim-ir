#!/usr/bin/env node
// Validate release/gadgetim.json and render release/gadgetim-report.html.
// Usage: node tools/client-report.js [path/to/report.json]
'use strict';
const fs = require('fs');
const path = require('path');

const src = path.resolve(process.argv[2] || path.join(__dirname, '..', 'release', 'gadgetim.json'));
const j = JSON.parse(fs.readFileSync(src, 'utf8'));
const out = src.replace(/\.json$/, '-report.html');

// --- validation -------------------------------------------------------------
const errors = [];
const sectionIds = new Set(j.sections.map(s => s.id));
const TYPES = { new: 'تازه', improve: 'بهبود', fix: 'اصلاح', plan: 'برنامه' };
const reportNos = new Set();
for (const r of j.reports) {
  if (reportNos.has(r.no)) errors.push(`duplicate report no ${r.no}`);
  reportNos.add(r.no);
  if (!(r.from <= r.date && r.from <= r.to)) errors.push(`report ${r.no}: bad date range`);
  if (!r.summary?.fa) errors.push(`report ${r.no}: missing summary`);
}
const ids = new Set();
for (const e of j.entries) {
  if (ids.has(e.id)) errors.push(`duplicate entry id ${e.id}`);
  ids.add(e.id);
  if (!reportNos.has(e.report)) errors.push(`${e.id}: unknown report ${e.report}`);
  if (!sectionIds.has(e.section)) errors.push(`${e.id}: unknown section ${e.section}`);
  if (!TYPES[e.type]) errors.push(`${e.id}: unknown type ${e.type}`);
  for (const k of ['title', 'summary', 'impact']) if (!e[k]?.fa) errors.push(`${e.id}: missing ${k}`);
  for (const ref of e.refs || []) if (!j.entries.some(x => x.id === ref)) errors.push(`${e.id}: unknown ref ${ref}`);
}
// Report date ranges must be contiguous: every day from project start is covered.
const sorted = [...j.reports].sort((a, b) => a.no - b.no);
const nextDay = d => new Date(Date.parse(d + 'T12:00:00Z') + 864e5).toISOString().slice(0, 10);
// A second report on the day the previous one ended (work finished after it went out) covers
// that one day again; the seo-engineer skill's client_report.py publishes such follow-ups.
let expect = j.project.started;
let last = null;
for (const r of sorted) {
  const sameDay = last !== null && r.from === last.to && r.to === last.to;
  if (r.from !== expect && !sameDay) errors.push(`report ${r.no}: starts ${r.from}, expected ${expect} (gap or overlap)`);
  expect = nextDay(r.to);
  last = r;
}
if (errors.length) { console.error('Report invalid:\n- ' + errors.join('\n- ')); process.exit(1); }

// --- render -----------------------------------------------------------------
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const jdate = d => fmt.format(new Date(d + 'T12:00:00Z')).replace(/ هجری شمسی$/, '');
const num = n => Number(n).toLocaleString('fa-IR');

const reportHtml = r => {
  const es = j.entries.filter(e => e.report === r.no);
  const range = r.from === r.to ? jdate(r.from) : `${jdate(r.from)} تا ${jdate(r.to)}`;
  let h = `<section class="report"><header><span class="no">گزارش ${num(r.no)}</span><span class="date">${range}</span><span class="count">${num(es.length)} کار</span></header><p class="summary">${esc(r.summary.fa)}</p>`;
  for (const s of j.sections) {
    const list = es.filter(e => e.section === s.id);
    if (!list.length) continue;
    h += `<h3>${esc(s.title.fa)}</h3>`;
    for (const e of list) {
      h += `<article class="entry"><div class="head"><span class="badge ${e.type}">${TYPES[e.type]}</span><h4>${esc(e.title.fa)}</h4><span class="id">${e.id}</span></div>`;
      h += `<p>${esc(e.summary.fa)}</p>`;
      if (e.items?.fa?.length) h += `<ul>${e.items.fa.map(i => `<li>${esc(i)}</li>`).join('')}</ul>`;
      h += `<p class="impact"><b>اثر:</b> ${esc(e.impact.fa)}</p>`;
      if (e.refs?.length) h += `<p class="refs">مرتبط با: ${e.refs.join(', ')}</p>`;
      h += `</article>`;
    }
  }
  return h + `</section>`;
};

const p = j.project;
const html = `<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>${esc(p.name.fa)} — گزارش کار</title>
<style>
:root { --accent: ${p.accent}; --gold: ${p.gold}; --bg: #f6f7f8; --card: #fff; --text: #1d2327; --muted: #5c6670; --line: #e3e6e9; }
@media (prefers-color-scheme: dark) { :root { --bg: #0f1418; --card: #171e24; --text: #e8edf0; --muted: #9aa6b0; --line: #26313a; } }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.9 Vazirmatn, Tahoma, "Segoe UI", sans-serif; }
.wrap { max-width: 860px; margin: 0 auto; padding: 24px 16px 60px; }
.top { border-bottom: 3px solid var(--accent); margin-bottom: 20px; padding-bottom: 12px; }
.top h1 { margin: 0; font-size: 24px; } .top p { margin: 0; color: var(--muted); }
.report { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; margin-bottom: 22px; }
.report header { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; font-weight: 700; }
.no { color: var(--accent); font-size: 18px; } .date, .count { color: var(--muted); font-weight: 500; }
.summary { background: color-mix(in srgb, var(--accent) 8%, transparent); border-inline-start: 4px solid var(--accent); padding: 10px 14px; border-radius: 8px; }
h3 { margin: 22px 0 8px; font-size: 16px; color: var(--accent); }
.entry { border-top: 1px solid var(--line); padding: 12px 0; }
.head { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.head h4 { margin: 0; font-size: 15.5px; flex: 1; } .id { color: var(--muted); font-size: 12px; direction: ltr; }
.badge { font-size: 12px; padding: 1px 10px; border-radius: 99px; color: #fff; background: var(--accent); }
.badge.improve { background: #3b6fd8; } .badge.fix { background: #c2410c; } .badge.plan { background: #7a6a2c; }
.entry p { margin: 6px 0; overflow-wrap: anywhere; } .impact { color: var(--muted); } .refs { color: var(--muted); font-size: 13px; }
.entry ul { margin: 6px 0; padding-inline-start: 22px; }
</style></head><body><div class="wrap">
<div class="top"><h1>${esc(p.name.fa)}</h1><p>${esc(p.subtitle.fa)}</p></div>
${sorted.slice().reverse().map(reportHtml).join('\n')}
</div></body></html>
`;
fs.writeFileSync(out, html);
console.log(`OK: ${j.reports.length} reports, ${j.entries.length} entries -> ${out}`);
