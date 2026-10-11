/**
 * Canlı sitede fotoğraflar GERÇEK tarayıcıda görünüyor mu?
 * --------------------------------------------------------
 * Kullanım: node browser-photo-check.js https://sarvesto.com out/
 *
 * İki motor: Chromium (masaüstü) ve WebKit (iPhone — Safari'nin motoru).
 * Her ürün sayfası + mağaza sayfaları açılır, sayfa yavaşça aşağı kaydırılır
 * (tembel yükleme tetiklensin), sonra:
 *   • ağda hata veren her görsel isteği (4xx/5xx, kopan bağlantı, görsel
 *     olmayan içerik türü)
 *   • tarayıcının çözemediği görseller (complete && naturalWidth == 0)
 *   • 12 sn sonra hâlâ yüklenmemiş görseller
 * toplanır. Sorunlu sayfaların ilk 30'unun ekran görüntüsü alınır.
 * Hiçbir şey yazmaz; yalnızca okur.
 */
'use strict';
const { chromium, webkit, devices } = require('playwright');
const fs = require('fs');

const BASE = (process.argv[2] || 'https://sarvesto.com').replace(/\/$/, '');
const OUT = process.argv[3] || 'out';
const CONC = 4;
const QUICK = process.argv[4] === 'quick';   // birkaç sayfa, doğal bekleme, hepsinin ekran görüntüsü
fs.mkdirSync(OUT + '/shots', { recursive: true });

async function productIds() {
  const xml = await (await fetch(BASE + '/sitemap.php')).text();
  const ids = new Set();
  for (const m of xml.matchAll(/product\.php\?id=([^&<"\s]+)/g)) ids.add(decodeURIComponent(m[1]));
  return [...ids];
}

async function checkPage(ctx, url, label) {
  const page = await ctx.newPage();
  const net = [];
  page.on('response', r => {
    const req = r.request();
    if (req.resourceType() !== 'image') return;
    const ct = (r.headers()['content-type'] || '');
    if (r.status() >= 400 || (r.status() === 200 && !ct.startsWith('image/')))
      net.push({ url: r.url(), status: r.status(), type: ct });
  });
  page.on('requestfailed', req => {
    if (req.resourceType() === 'image') net.push({ url: req.url(), status: 0, type: (req.failure() || {}).errorText || 'failed' });
  });
  let status = 0, err = '';
  try {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    status = resp ? resp.status() : 0;
    if (QUICK) {
      // Gerçek kullanıcı gibi: açılışta kaydırmadan 4 sn bekle, ilk ekranı çek.
      await page.waitForTimeout(4000);
      await page.screenshot({ path: `${OUT}/shots/quick_${label.replace(/[^a-z0-9-]/gi, '_').slice(0, 80)}_a.png` }).catch(() => {});
    }
    // Kaydır: tembel görseller yüklensin
    await page.evaluate(async () => {
      const step = Math.max(300, innerHeight * 0.8);
      for (let y = 0; y < document.body.scrollHeight; y += step) { scrollTo(0, y); await new Promise(r => setTimeout(r, 180)); }
      scrollTo(0, 0);
    });
    await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
    // Tembel yükleme bazı motorlarda (WebKit) yalnız kaydırma olayında
    // tetikleniyor: başa dönüşten sonra küçük bir dürtme ve kısa bekleme.
    await page.evaluate(() => { scrollBy(0, 2); scrollBy(0, -2); });
    await page.waitForTimeout(QUICK ? 2500 : 1200);
    await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
    var imgs = await page.evaluate(() => Array.from(document.images).map(i => ({
      src: i.currentSrc || i.src, ok: i.complete && i.naturalWidth > 0, done: i.complete,
      gallery: !!i.closest('.pdp__shot, .lot__media, .card'),
    })));
  } catch (e) { err = String(e.message || e).slice(0, 200); imgs = []; }
  const broken = imgs.filter(i => i.done && !i.ok && i.src);
  const pending = imgs.filter(i => !i.done && i.src);
  const r = { label, url, status, err, images: imgs.length,
              gallery: imgs.filter(i => i.gallery).length,
              broken: broken.map(i => i.src), pending: pending.map(i => i.src), net };
  r.bad = !!(err || status >= 400 || broken.length || net.length || pending.length);
  if ((r.bad || QUICK) && shotCount < 30) {
    shotCount++;
    await page.screenshot({ path: `${OUT}/shots/${label.replace(/[^a-z0-9-]/gi, '_').slice(0, 80)}.png`, fullPage: false }).catch(() => {});
  }
  await page.close();
  return r;
}
let shotCount = 0;

(async () => {
  const ids = await productIds();
  const pages = [['home', '/'], ['outlet', '/outlet.php'], ['brands', '/brands.php']];
  for (let n = 1; n <= (QUICK ? 2 : 40); n++) pages.push(['shop-' + n, '/shop.php?page=' + n]);
  for (const id of (QUICK ? ids.slice(0, 6) : ids)) pages.push(['p-' + id, '/product.php?id=' + encodeURIComponent(id)]);
  console.log(`ürün: ${ids.length} · sayfa: ${pages.length}`);

  const engines = [
    ['iphone-webkit', webkit, devices['iPhone 14']],
    ['desktop-chromium', chromium, { viewport: { width: 1366, height: 900 } }],
  ];
  const report = {};
  for (const [name, type, dev] of engines) {
    shotCount = name === 'iphone-webkit' ? 0 : 20;   // ekran görüntülerinin çoğu iPhone'dan
    const browser = await type.launch();
    const ctx = await browser.newContext({ ...dev });
    const results = [];
    let i = 0;
    await Promise.all(Array.from({ length: CONC }, async () => {
      while (i < pages.length) {
        const [label, path] = pages[i++];
        results.push(await checkPage(ctx, BASE + path, name + '_' + label));
      }
    }));
    await browser.close();
    const bad = results.filter(r => r.bad);
    report[name] = { pages: results.length, bad: bad.length, results: bad };
    console.log(`\n== ${name}: ${results.length} sayfa · sorunlu ${bad.length}`);
    const byStatus = {};
    for (const r of bad) for (const n of r.net) byStatus[n.status + ' ' + n.type] = (byStatus[n.status + ' ' + n.type] || 0) + 1;
    console.log('  ağ hataları:', JSON.stringify(byStatus));
    console.log('  çözülemeyen görsel:', bad.reduce((a, r) => a + r.broken.length, 0),
                '· yüklenmeyen:', bad.reduce((a, r) => a + r.pending.length, 0),
                '· sayfa hatası:', bad.filter(r => r.err || r.status >= 400).length);
    for (const r of bad.slice(0, 40)) {
      console.log(`  ${r.label} [${r.status}] broken=${r.broken.length} pending=${r.pending.length} net=${r.net.length} ${r.err}`);
      for (const n of r.net.slice(0, 3)) console.log(`      net ${n.status} ${n.type} ${n.url.replace(BASE, '')}`);
      for (const b of r.broken.slice(0, 3)) console.log(`      broken ${b.replace(BASE, '')}`);
      for (const b of r.pending.slice(0, 2)) console.log(`      pending ${b.replace(BASE, '')}`);
    }
  }
  fs.writeFileSync(OUT + '/report.json', JSON.stringify(report, null, 1));
})();
