/**
 * Gerçek tarayıcıyla web araması ve sayfa taraması (runner'da, salt okunur)
 * ========================================================================
 *   node web-search.js jobs.json out.json
 *
 * jobs.json: [{"q": "Burberry 8099165"}, {"url": "https://…/product"}]
 *   q   → Bing'de aranır (gerçek Chromium; düz istekte Bing genel sonuç
 *         döndürüyordu). Sonuç: başlık + gerçek adres.
 *   url → sayfa açılır; ürün kareleri toplanır: JSON-LD Product.image,
 *         og:image, sayfadaki en büyük <img>'ler (doğal boyutuyla).
 * Hiçbir şey indirilmez ya da bir yere yazılmaz; çıktı yalnız out.json.
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const [jobsFile, outFile] = process.argv.slice(2);
const jobs = JSON.parse(fs.readFileSync(jobsFile, 'utf8'));

(async () => {
  const b = await chromium.launch({ args: ['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({
    viewport: { width: 1366, height: 900 }, locale: 'en-GB',
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
  });
  const page = await ctx.newPage();
  const out = [];
  for (const j of jobs) {
    const rec = { ...j };
    try {
      if (j.q) {
        await page.goto('https://www.bing.com/search?q=' + encodeURIComponent(j.q) + '&setlang=en', { waitUntil: 'domcontentloaded', timeout: 45000 });
        await page.waitForTimeout(1500);
        rec.results = await page.evaluate(() => Array.from(document.querySelectorAll('#b_results > li.b_algo')).map(li => {
          const a = li.querySelector('h2 a'); if (!a) return null;
          let href = a.href;
          const m = href.match(/[?&]u=a1([^&]+)/);
          if (m) { try { href = atob(m[1].replace(/-/g, '+').replace(/_/g, '/')); } catch (e) {} }
          const cite = li.querySelector('cite');
          return { title: a.textContent.trim(), url: href, cite: cite ? cite.textContent.trim() : '' };
        }).filter(Boolean));
      } else if (j.url) {
        await page.goto(j.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForTimeout(j.wait || 4000);
        // Tembel yüklenen galeriler için aşağı kaydır.
        for (let y = 0; y < 4; y++) { await page.mouse.wheel(0, 900); await page.waitForTimeout(400); }
        rec.final = page.url();
        rec.title = await page.title();
        rec.data = await page.evaluate(() => {
          const imgs = new Set(), codes = new Set();
          document.querySelectorAll('script[type="application/ld+json"]').forEach(s => {
            try {
              const walk = (n) => {
                if (!n || typeof n !== 'object') return;
                if (Array.isArray(n)) return n.forEach(walk);
                for (const [k, v] of Object.entries(n)) {
                  if (k === 'image') [].concat(v).forEach(x => { if (typeof x === 'string') imgs.add(x); else if (x && x.url) imgs.add(x.url); });
                  else if ((k === 'sku' || k === 'mpn' || k === 'gtin13') && v) codes.add(String(v));
                  else if (typeof v === 'object') walk(v);
                }
              };
              walk(JSON.parse(s.textContent));
            } catch (e) {}
          });
          const og = Array.from(document.querySelectorAll('meta[property^="og:image"]')).map(m => m.content);
          const big = Array.from(document.images)
            .map(i => ({ src: i.currentSrc || i.src, w: i.naturalWidth, h: i.naturalHeight, alt: i.alt || '' }))
            .filter(i => i.w >= 400 && i.h >= 400 && /^https?:/.test(i.src));
          const text = document.body ? document.body.innerText.slice(0, 4000) : '';
          return { ld: Array.from(imgs), og, big: big.slice(0, 40), codes: Array.from(codes), text };
        });
      }
    } catch (e) { rec.error = String(e.message || e).slice(0, 200); }
    out.push(rec);
    console.log((j.q || j.url).slice(0, 90), '→', rec.error || (rec.results ? rec.results.length + ' sonuç' : (rec.data ? rec.data.big.length + ' büyük kare, ' + rec.data.ld.length + ' ld' : '')));
    await page.waitForTimeout(1200 + Math.random() * 1500);
  }
  fs.writeFileSync(outFile, JSON.stringify(out, null, 1));
  await b.close();
})();
