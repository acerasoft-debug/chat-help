/**
 * Renk → galeri canlı denemesi: node colour-check.js https://sarvesto.com /product.php?id=… out/
 * iPhone (WebKit) ve masaüstü (Chromium): sayfayı aç, her renge tıkla, görünen
 * galerinin ilk karesinin adresi o rengin kodunu taşıyor mu bak, ekran görüntüsü al.
 */
'use strict';
const { chromium, webkit, devices } = require('playwright');
const fs = require('fs');
const [base, path, out] = [process.argv[2], process.argv[3], process.argv[4] || 'out'];
fs.mkdirSync(out, { recursive: true });
(async () => {
  for (const [name, type, dev] of [['iphone', webkit, devices['iPhone 14']], ['desktop', chromium, { viewport: { width: 1366, height: 900 } }]]) {
    const b = await type.launch(); const ctx = await b.newContext({ ...dev }); const page = await ctx.newPage();
    const errs = []; page.on('pageerror', e => errs.push(e.message));
    await page.goto(base + path, { waitUntil: 'networkidle', timeout: 60000 });
    const n = await page.locator('input[name="colour"][data-cset]').count();
    console.log(`${name}: renk sayısı ${n}`);
    await page.screenshot({ path: `${out}/${name}-0.png` });
    for (let i = 0; i < n; i++) {
      const r = page.locator(`input[name="colour"][data-cset="${i}"]`);
      await r.locator('xpath=..').click();
      await page.waitForTimeout(700);
      const info = await page.evaluate(i => {
        const g = document.querySelector(`[data-gallery][data-cset="${i}"]`);
        const vis = Array.from(document.querySelectorAll('[data-gallery]')).filter(x => !x.hidden).map(x => x.getAttribute('data-cset'));
        const img = g && g.querySelector('img'); const val = document.querySelector('input[name="colour"]:checked').value;
        return { vis, val, src: img ? (img.currentSrc || img.src) : '', n: g ? g.querySelectorAll('img').length : 0, now: (document.querySelector('[data-cset-now]') || {}).textContent };
      }, i);
      const code = (info.val.match(/\((\w+)\)/) || [])[1] || '';
      console.log(`  ${i}: seçili="${info.val}" görünen galeri=${info.vis.join(',')} kare=${info.n} başlık="${info.now}" ilk kare kodu taşıyor=${code && info.src.includes(code) ? 'EVET' : 'HAYIR'} ${info.src.split('/').pop().slice(0, 60)}`);
      if (i === 1 || i === n - 1) await page.screenshot({ path: `${out}/${name}-${i}.png`, fullPage: false });
    }
    console.log(`  JS hataları: ${JSON.stringify(errs)}`);
    await b.close();
  }
})();
