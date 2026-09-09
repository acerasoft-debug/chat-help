#!/usr/bin/env node
/**
 * Single-file navigable preview of the built site (dist/ → dist/preview.html).
 * A curated set of pages is embedded and swapped by a hash router; CSS, JS and
 * the therapist directory are inlined, so the file works anywhere a plain HTML
 * page can be hosted. Forms run in the front-end's demo mode (no API).
 */
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const DIST = path.join(ROOT, 'dist');
const read = (p) => readFileSync(path.join(DIST, p), 'utf8');

const PAGES = [
  '/de/', '/en/', '/es/', '/fr/', '/it/',
  '/de/behandlungen/', '/de/hautpflege/', '/de/staedte/', '/de/ablauf/', '/de/prive/', '/de/journal/',
  '/de/therapeut-werden/', '/de/buchen/', '/de/anmelden/', '/de/registrieren/', '/de/passwort-zuruecksetzen/',
  '/de/gutscheine/', '/de/unternehmen/', '/de/kontakt/', '/de/impressum/', '/de/datenschutz/', '/de/agb/',
  '/de/behandlungen/anti-cellulite/', '/de/behandlungen/signature-lumea/', '/de/behandlungen/hifu-lifting/',
  '/de/behandlungen/lymphatic-drainage/', '/de/behandlungen/deep-tissue/', '/de/behandlungen/signature-facial/',
  '/de/staedte/berlin/', '/de/staedte/marbella/', '/de/staedte/zuerich/', '/de/staedte/berlin/anti-cellulite/',
  '/de/therapeuten/berlin-01/', '/de/therapeuten/berlin-03/', '/de/therapeuten/marbella-01/',
  '/de/journal/cellulite-was-massage-wirklich-kann/',
  '/en/treatments/', '/en/treatments/anti-cellulite/', '/en/cities/london/'.replace('london', 'berlin'), '/en/become-a-therapist/'
];

const pages = {};
let chrome = null;
for (const p of PAGES) {
  let html;
  try { html = read(path.join(p, 'index.html')); } catch { continue; }
  const title = (html.match(/<title>([^<]*)<\/title>/)?.[1] || 'LUMÉA').replace(/&amp;/g, '&').replace(/&#39;/g, "'").replace(/&quot;/g, '"');
  const main = html.match(/<main id="main">([\s\S]*?)<\/main>/)?.[1] || '';
  const globals = html.match(/<script>(window\.__x=[\s\S]*?)<\/script>/)?.[1] || '';
  const header = html.match(/<header class="header"[\s\S]*?<\/header>/)?.[0] || '';
  const footer = html.match(/<footer class="footer">[\s\S]*?<\/footer>/)?.[0] || '';
  const extras = html.match(/<\/footer>([\s\S]*?)<script>window\.__x/)?.[1] || '';
  const locale = p.split('/')[1];
  pages[p] = { title, main, globals, header, footer, extras, locale };
  if (!chrome) chrome = { header, footer, extras, locale };
}
const inlineImg = (s) => s.replace(/src="\/assets\/img\/([^"]+)"/g, (m, f) => {
  try { const b = readFileSync(path.join(DIST, 'assets/img', f)); return `src="data:image/${f.endsWith('.png') ? 'png' : f.endsWith('.webp') ? 'webp' : 'jpeg'};base64,${b.toString('base64')}"`; } catch { return m; }
});
const rewrite = (s) => inlineImg(s.replace(/(href|action)="\/(?!\/)/g, '$1="#/'));

const css = read('assets/styles.css');
const js = read('assets/app.js');
const data = read('assets/data.json');
const notFound = read('de/404.html').match(/<main id="main">([\s\S]*?)<\/main>/)?.[1] || '';

const out = `<title>LUMÉA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@400;500;600;700&display=swap">
<style>${css}
.preview-note{position:fixed;top:0;left:0;right:0;z-index:90;background:#f1f1ef;color:#7b7e86;border-bottom:1px solid rgba(36,38,43,.08);font:600 .68rem/1.4 Inter,system-ui,sans-serif;letter-spacing:.14em;text-transform:uppercase;text-align:center;padding:.35rem .6rem}
.header{top:24px}.nav{inset:97px 0 auto}body{padding-top:24px}</style>
<div class="preview-note">Vorschau · Preview · Statische Demo — API/Backend nicht verbunden</div>
<div id="chromeHeader">${rewrite(chrome.header)}</div>
<main id="main">${rewrite(pages['/de/'].main)}</main>
<div id="chromeFooter">${rewrite(chrome.footer)}${rewrite(chrome.extras)}</div>
<script>window.__lumeaData=${data};window.__pages=${JSON.stringify(Object.fromEntries(Object.entries(pages).map(([k, v]) => [k, { t: v.title, m: rewrite(v.main), g: v.globals, h: rewrite(v.header), f: rewrite(v.footer), x: rewrite(v.extras), l: v.locale }])))};window.__notFound=${JSON.stringify(rewrite(notFound))};</script>
<script>
(function(){
  var body=document.body; body.dataset.base='#'; body.dataset.locale='de';
  function norm(h){ h=(h||'').replace(/^#/,''); if(!h||h[0]!=='/') return null; h=h.split('?')[0]; if(!/\\/$/.test(h)) h+='/'; return h; }
  function render(){
    var p=norm(location.hash)||'/de/'; var q=(location.hash.split('?')[1]||'');
    var pg=window.__pages[p];
    var main=document.getElementById('main');
    if(!pg){ main.innerHTML=window.__notFound; window.scrollTo(0,0); return; }
    body.dataset.locale=pg.l; document.documentElement.lang=pg.l; document.title=pg.t;
    document.getElementById('chromeHeader').innerHTML=pg.h; document.getElementById('chromeFooter').innerHTML=pg.f+pg.x;
    main.innerHTML=pg.m;
    try{ (0,eval)(pg.g); }catch(e){}
    history.replaceState(null,'',location.pathname+(q?'?'+q:'')+location.hash);
    if(window.__lumeaBoot) window.__lumeaBoot();
    window.scrollTo(0,0);
  }
  // Query strings inside the hash (…/buchen/?service=x) must reach app.js via location.search: mirror them.
  var origSearch=Object.getOwnPropertyDescriptor(Location.prototype,'search');
  window.addEventListener('hashchange',render);
  document.addEventListener('click',function(e){ var a=e.target.closest('a[href^="#/"]'); if(a){ if(a.getAttribute('href')===location.hash){e.preventDefault();render();} } });
  render();
})();
</script>
<script>${js.replace('new URLSearchParams(location.search)', "new URLSearchParams((location.hash.split('?')[1]||''))").replace(/new URLSearchParams\(location\.search\)/g, "new URLSearchParams((location.hash.split('?')[1]||''))")}</script>`;

writeFileSync(path.join(DIST, 'preview.html'), out);
console.log(`preview.html → ${(Buffer.byteLength(out) / 1048576).toFixed(2)} MB, ${Object.keys(pages).length} pages`);
