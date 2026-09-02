/* VESTRA local render review: screenshots at mobile + desktop, console errors,
   failed requests, horizontal overflow and offending elements. */
const { chromium, devices } = require('playwright');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8085';
const OUT  = process.env.OUT  || require('path').join(__dirname, 'out');
const only = process.argv.slice(2);
const PAGES = [
  ['home', '/'], ['shop', '/shop'], ['shop-q', '/shop?q=polo'], ['product', '/product?id=lac-pique-polo'],
  ['product-sample', '/product?id=lac-l1212-musterstueck'], ['price-list', '/price-list'], ['price-lists', '/price-lists'],
  ['catalog', '/catalog'], ['cart', '/cart'], ['login', '/login'], ['register', '/register'], ['forgot', '/forgot'],
  ['wholesale', '/wholesale/lacoste'], ['groups', '/groups'], ['faq', '/faq'], ['help', '/help'], ['journal', '/journal'],
  ['dropshipping', '/dropshipping'], ['join', '/join'], ['seller-invite', '/seller-invite'], ['requests', '/requests'],
  ['request', '/request'], ['legal', '/legal'], ['membership', '/membership'], ['404', '/this-does-not-exist'],
  ['buyer', '/buyer'], ['seller', '/seller'], ['showroom', '/showroom?id=7ab30f26afedd840'],
];
const VIEWS = {
  mobile:  { ...devices['iPhone 13'], viewport: { width: 390, height: 844 } },
  desktop: { viewport: { width: 1366, height: 768 }, deviceScaleFactor: 1 },
};
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME || undefined });
  const report = [];
  for (const [name, path] of PAGES) {
    if (only.length && !only.includes(name)) continue;
    for (const [vname, opts] of Object.entries(VIEWS)) {
      const ctx = await browser.newContext(opts);
      const page = await ctx.newPage();
      await page.route('**/*', r => { const u = r.request().url(); if (u.startsWith(BASE) || u.startsWith('data:')) return r.continue(); return r.abort(); });
      const errs = [], failed = [];
      page.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') errs.push(m.type() + ': ' + m.text().slice(0, 160)); });
      page.on('requestfailed', r => failed.push(r.url().replace(BASE, '') + ' ' + (r.failure() || {}).errorText));
      page.on('response', r => { if (r.status() >= 400 && !r.url().includes('favicon')) failed.push(r.url().replace(BASE, '') + ' HTTP ' + r.status()); });
      let status = 0, title = '';
      try {
        const resp = await page.goto(BASE + path, { waitUntil: 'load', timeout: 20000 });
        status = resp ? resp.status() : 0;
        title = await page.title();
        await page.waitForTimeout(700);
        const ov = await page.evaluate(() => {
          const w = window.innerWidth;
          const sw = document.documentElement.scrollWidth;
          const bad = [];
          for (const el of document.querySelectorAll('body *')) {
            const r = el.getBoundingClientRect();
            if (r.width === 0 || r.height === 0) continue;
            const cs = getComputedStyle(el);
            if (cs.position === 'fixed') continue;
            if (r.right > w + 2 && r.left < w) {
              const id = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
              bad.push(id + ' right=' + Math.round(r.right) + ' w=' + Math.round(r.width));
              if (bad.length > 8) break;
            }
          }
          const fontsSmall = [];
          for (const el of document.querySelectorAll('body *')) {
            const cs = getComputedStyle(el);
            if (el.children.length === 0 && el.textContent.trim().length > 12 && parseFloat(cs.fontSize) < 11 && cs.display !== 'none') {
              fontsSmall.push(el.tagName.toLowerCase() + ':' + cs.fontSize + ' "' + el.textContent.trim().slice(0, 30) + '"');
              if (fontsSmall.length > 5) break;
            }
          }
          return { w, sw, hscroll: sw > w + 1, bad, fontsSmall, h: document.documentElement.scrollHeight };
        });
        await page.screenshot({ path: `${OUT}/${name}-${vname}.png`, fullPage: true });
        await page.screenshot({ path: `${OUT}/${name}-${vname}-top.png`, fullPage: false });
        report.push({ name, vname, path, status, title, ...ov, errs: errs.slice(0, 6), failed: failed.slice(0, 6) });
      } catch (e) {
        report.push({ name, vname, path, status, error: String(e).slice(0, 200), errs, failed });
      }
      await ctx.close();
    }
  }
  await browser.close();
  fs.writeFileSync(`${OUT}/report.json`, JSON.stringify(report, null, 1));
  for (const r of report) {
    const flags = [];
    if (r.error) flags.push('ERR ' + r.error);
    if (r.status && r.status >= 400) flags.push('HTTP ' + r.status);
    if (r.hscroll) flags.push(`HSCROLL sw=${r.sw}>${r.w}`);
    if (r.bad && r.bad.length) flags.push('OVERFLOW ' + r.bad.slice(0, 3).join(' | '));
    if (r.fontsSmall && r.fontsSmall.length) flags.push('SMALLFONT ' + r.fontsSmall.slice(0, 2).join(' | '));
    if (r.errs && r.errs.length) flags.push('CONSOLE ' + r.errs.slice(0, 2).join(' | '));
    if (r.failed && r.failed.length) flags.push('FAILED ' + r.failed.slice(0, 3).join(' | '));
    console.log(`${r.name.padEnd(14)} ${r.vname.padEnd(8)} ${String(r.status).padEnd(4)} h=${String(r.h || '-').padEnd(6)} ${flags.join(' ; ') || 'ok'}`);
  }
})();
