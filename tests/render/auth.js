/* Authenticated flows on the LOCAL server: register a buyer and a seller, then
   screenshot the panel pages and the gated storefront states (viewport-only). */
const { chromium, devices } = require('playwright');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8085';
const OUT  = process.env.OUT  || require('path').join(__dirname, 'out');
const VIEWS = {
  mobile:  { ...devices['iPhone 13'], viewport: { width: 390, height: 844 } },
  desktop: { viewport: { width: 1366, height: 768 }, deviceScaleFactor: 1 },
};
const stamp = Date.now().toString(36);
const ACCOUNTS = [
  { type: 'buyer',  email: `buyer-${stamp}@example.test`,  name: 'Test Buyer',  company: 'Boutique Test SRL', country: 'IT' },
  { type: 'seller', email: `seller-${stamp}@example.test`, name: 'Test Seller', company: 'Atelier Test GmbH', country: 'DE' },
];
const PAGES = {
  buyer:  [['b-kyc', '/buyer?tab=kyc'], ['b-overview', '/buyer'], ['b-shop', '/shop'], ['b-product', '/product?id=lac-pique-polo'],
           ['b-cart', '/cart'], ['b-pricelist', '/price-list'], ['b-profile', '/buyer?tab=profile'], ['b-orders', '/buyer?tab=orders'], ['b-messages', '/buyer?tab=messages']],
  seller: [['s-kyc', '/seller?tab=kyc'], ['s-overview', '/seller'], ['s-add', '/seller?tab=add'], ['s-listings', '/seller?tab=listings'],
           ['s-profile', '/seller?tab=profile'], ['s-orders', '/seller?tab=orders']],
};
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME || undefined });
  const report = [];
  for (const acc of ACCOUNTS) {
    for (const [vname, opts] of Object.entries(VIEWS)) {
      const ctx = await browser.newContext(opts);
      const page = await ctx.newPage();
      await page.route('**/*', r => { const u = r.request().url(); if (u.startsWith(BASE) || u.startsWith('data:')) return r.continue(); return r.abort(); });
      const errs = [];
      page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 120)); });
      // register (first viewport) or log in (second viewport)
      if (vname === 'mobile') {
        await page.goto(BASE + '/register?type=' + acc.type, { waitUntil: 'load', timeout: 20000 });
        await page.check(`input[name=type][value=${acc.type}]`, { force: true }).catch(() => {});
        await page.fill('input[name=name]', acc.name);
        await page.fill('input[name=email]', acc.email);
        await page.fill('input[name=password]', 'Passw0rd!Test');
        await page.fill('input[name=password2]', 'Passw0rd!Test');
        await page.fill('input[name=company]', acc.company);
        await page.fill('input[name=country]', acc.country);
        await page.screenshot({ path: `${OUT}/reg-${acc.type}-filled-${vname}-top.png` });
        await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 20000 }).catch(() => {}), page.click('#regsubmit')]);
      } else {
        await page.goto(BASE + '/login', { waitUntil: 'load', timeout: 20000 });
        await page.fill('input[name=email]', acc.email);
        await page.fill('input[name=password]', 'Passw0rd!Test');
        await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 20000 }).catch(() => {}), page.click('button[type=submit]')]);
      }
      report.push({ acc: acc.type, vname, after: page.url().replace(BASE, ''), title: await page.title() });
      for (const [name, path] of PAGES[acc.type]) {
        try {
          await page.goto(BASE + path, { waitUntil: 'load', timeout: 20000 });
          await page.waitForTimeout(500);
          const ov = await page.evaluate(() => ({ w: innerWidth, sw: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight,
            banner: Array.from(document.querySelectorAll('.banner,.vpending,.gate')).map(e => e.innerText.trim().replace(/\s+/g, ' ').slice(0, 140)).slice(0, 4) }));
          await page.screenshot({ path: `${OUT}/${name}-${vname}-top.png` });
          await page.screenshot({ path: `${OUT}/${name}-${vname}.png`, fullPage: true });
          report.push({ name, vname, path, url: page.url().replace(BASE, ''), ...ov, errs: errs.splice(0) });
        } catch (e) { report.push({ name, vname, path, error: String(e).slice(0, 160) }); }
      }
      await ctx.close();
    }
  }
  await browser.close();
  fs.writeFileSync(`${OUT}/auth-report.json`, JSON.stringify(report, null, 1));
  for (const r of report) {
    if (r.acc) { console.log(`[${r.acc}/${r.vname}] after auth -> ${r.after} (${r.title})`); continue; }
    const flags = [];
    if (r.error) flags.push('ERR ' + r.error);
    if (r.sw > r.w + 1) flags.push(`HSCROLL ${r.sw}>${r.w}`);
    if (r.errs && r.errs.length) flags.push('CONSOLE ' + r.errs.slice(0, 2).join(' | '));
    console.log(`${String(r.name).padEnd(12)} ${String(r.vname).padEnd(8)} ${String(r.url || '').padEnd(24)} h=${String(r.h || '-').padEnd(6)} ${(r.banner || []).join(' || ').slice(0, 220)} ${flags.join(' ; ')}`);
  }
})();
