// Bangun diagram HTML dari blok Mermaid di analisis-sistem.md.
// Pakai (dari folder docs): MODE=r1 node tools/build-diagram.cjs analisis-sistem.md diagram.html
//                           MODE=r2 node tools/build-diagram.cjs analisis-sistem.md diagram-r2.html
// Blok dengan '(R2)' di judul masuk halaman R2; sisanya R1.
const fs = require('fs');
const src = process.argv[2], out = process.argv[3];
const lines = fs.readFileSync(src, 'utf8').split(/\r?\n/);
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const clean = s => s.replace(/\*\*/g, '').replace(/`/g, '').trim();

let heading = '', group = '', inBlock = false, buf = [], items = [];
for (const l of lines) {
  if (!inBlock) {
    const m = l.match(/^(#{2,4})\s+(.*)/);
    if (m) { heading = clean(m[2]); if (m[1] === '##') group = clean(m[2]); }
    const b = l.match(/^\*\*([a-d]\. .*)\*\*$/);
    if (b) heading = '6.2 DB Toko — ' + b[1];
    if (/^```mermaid/.test(l)) { inBlock = true; buf = []; }
  } else if (/^```/.test(l)) {
    inBlock = false;
    items.push({ heading, group, code: buf.join('\n') });
  } else buf.push(l);
}

const kind = g => (/^5\./.test(g) ? 'BPMN' : /^6\./.test(g) ? 'ERD' : 'Lainnya');
const rel = h => (/\bR1\b/.test(h) && /\bR2\b/.test(h) ? 'mix' : /\bR2\b/.test(h) ? 'r2' : 'r1');
const isR2 = h => rel(h) === 'r2';
const MODE = process.env.MODE === 'r2' ? 'r2' : 'r1';
items = items.filter(it => (rel(it.heading) === 'r2') === (MODE === 'r2'));
const OTHER = MODE === 'r2' ? ['diagram.html', '← Diagram R1'] : ['diagram-r2.html', 'Diagram R2 (menyusul) →'];
const short = h => h.replace(/^\d+(\.\d+)?[a-z]?\s+/, '').replace(/\s*\(R2\)\s*$/, '');
const num = h => (h.match(/^(\d+(\.\d+)?[a-z]?)\s/) || [, ''])[1];

let nav = '', lastKind = '';
items.forEach((it, i) => {
  const k = kind(it.group);
  if (k !== lastKind) { nav += `<div class="grp">${k === 'BPMN' ? 'Alur proses (BPMN)' : k === 'ERD' ? 'Skema database (ERD)' : k}</div>`; lastKind = k; }
  nav += `<a href="#d${i}"><span class="n">${esc(num(it.heading))}</span>${esc(short(it.heading))}${isR2(it.heading) ? '<span class="rel">R2</span>' : ''}</a>`;
});
const body = items.map((it, i) => `<section id="d${i}"><div class="sh"><h2>${esc(it.heading)}</h2><span class="tag ${rel(it.heading)}">${{ r1: 'R1', r2: 'R2', mix: 'R1 + R2' }[rel(it.heading)]}</span></div><pre class="mermaid">${esc(it.code)}</pre></section>`).join('\n');

const html = `<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagram POS Toko — ${MODE.toUpperCase()}</title>
<style>
:root{--bg:#f6f6f7;--surface:#fff;--ink:#111318;--ink2:#4b5160;--mute:#9aa0ab;--line:#e8e9ed;--soft:#f2f3f5;--brand:#e02a2a;--brand2:#fdecec;--ok:#059669;--ok2:#e7f8f1;--r:14px;--shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(15,23,42,.06)}
@media (prefers-color-scheme:dark){:root{--bg:#0e0f12;--surface:#15171c;--ink:#e6e8ec;--ink2:#9aa0ab;--mute:#6b7280;--line:#2a2d35;--soft:#1d2027;--brand:#ff5a5a;--brand2:#3a1618;--ok:#34d399;--ok2:#10302a;--shadow:none}}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 Inter,"Segoe UI",system-ui,-apple-system,sans-serif;-webkit-font-smoothing:antialiased}
.doc{display:grid;grid-template-columns:288px 1fr;min-height:100vh}
nav{background:var(--surface);border-right:1px solid var(--line);position:sticky;top:0;height:100vh;overflow:auto;padding:22px 14px 30px}
nav h1{font-size:17px;margin:0 8px 2px;letter-spacing:-.01em}
nav .sub{margin:0 8px 14px;color:var(--mute);font-size:12.5px}
nav .grp{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--mute);margin:16px 8px 6px}
nav a{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:9px;color:var(--ink2);text-decoration:none;font-size:13.5px}
nav a:hover{background:var(--soft)}
nav a.on{background:var(--brand2);color:var(--brand);font-weight:600}
nav a .n{min-width:30px;font-size:11.5px;color:var(--mute);font-variant-numeric:tabular-nums}
nav a .rel{margin-left:auto;font-size:9.5px;font-weight:700;border-radius:99px;padding:0 7px;background:var(--soft);color:var(--ink2)}
main{padding:28px 32px 70px;min-width:0;max-width:1500px}
.top{margin-bottom:20px}.top h2{margin:0;font-size:22px;letter-spacing:-.02em}.top p{margin:2px 0 0;color:var(--ink2)}
section{background:var(--surface);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:18px 22px;margin-bottom:20px;overflow:auto;scroll-margin-top:16px}
.sh{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
.sh h2{margin:0;font-size:16px;letter-spacing:-.01em}
.tag{font-size:10.5px;font-weight:700;letter-spacing:.04em;border-radius:99px;padding:2px 10px;background:var(--ok2);color:var(--ok);white-space:nowrap}
.tag.r2{background:var(--soft);color:var(--ink2)}
nav a.xlink{display:block;text-align:center;margin:18px 8px 0;padding:9px 12px;border:1px dashed var(--brand);border-radius:10px;color:var(--brand);font-weight:600}
.mermaid{text-align:center}
@page{size:A3 landscape;margin:12mm}
@media print{
*{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{background:#fff}.doc{display:block}nav{display:none}main{padding:0;max-width:none}
section{box-shadow:none;break-after:page;break-inside:avoid;overflow:visible;margin:0 0 6mm}
section:last-of-type{break-after:auto}
.sh{break-after:avoid}
.mermaid svg{max-height:195mm}
}
@media screen and (max-width:980px){.doc{grid-template-columns:1fr}nav{position:static;height:auto;border-right:0;border-bottom:1px solid var(--line)}main{padding:20px 14px 60px}}
</style></head><body>
<div class="doc">
<nav>
<h1>Diagram POS Toko · ${MODE.toUpperCase()}</h1>
<p class="sub">BPMN dan ERD · v0.9 · online only</p>
${nav}
<a class="xlink" href="${OTHER[0]}">${OTHER[1]}</a>
</nav>
<main>
<div class="top"><h2>${MODE === 'r2' ? 'Alur proses dan skema database — R2 (menyusul)' : 'Alur proses dan skema database — R1 (dibangun dulu)'}</h2><p>${items.length} diagram dirender dari blok Mermaid di analisis-sistem.md. ${MODE === 'r2' ? 'Tabel R2 hanya menampilkan penambahan di atas skema R1.' : 'Skema R1 berdiri sendiri; penambahan R2 ada di halaman terpisah.'}</p></div>
${body}
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.min.js"></script>
<script>
var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
var light = {primaryColor:'#fdecec',primaryBorderColor:'#f6b3b3',primaryTextColor:'#0f172a',lineColor:'#64748b',clusterBkg:'#f6f6f7',clusterBorder:'#dcdde2',edgeLabelBackground:'#ffffff',fontFamily:'Inter,Segoe UI,system-ui,sans-serif'};
mermaid.initialize({startOnLoad:true, theme: dark ? 'dark' : 'base', themeVariables: dark ? {fontFamily:'Inter,Segoe UI,system-ui,sans-serif'} : light, securityLevel:'strict', flowchart:{htmlLabels:true, curve:'basis'}, er:{useMaxWidth:true}});
var links=[].slice.call(document.querySelectorAll('nav a')), secs=links.map(function(a){return document.querySelector(a.getAttribute('href'))});
function spy(){var y=window.scrollY+80,c=0;secs.forEach(function(s,i){if(s.offsetTop<=y)c=i});links.forEach(function(a,i){a.classList.toggle('on',i===c)})}
window.addEventListener('scroll',spy,{passive:true});spy();
</script>
</body></html>`;
fs.writeFileSync(out, html);
console.log('blocks:', items.length);
items.forEach((it, i) => console.log(i, it.heading));
