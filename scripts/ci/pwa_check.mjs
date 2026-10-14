// VESTRA uygulaması (PWA) kurulabilir mi — canlı sitede gerçek Chrome ile (14 Eki 2026, operatör: "Vestra App indirilebiliyor mu kontrol et").
// Salt okuma: sayfaları açar, Chrome'un kendi kurulabilirlik denetimini (CDP Page.getInstallabilityErrors) ve manifest hatalarını okur.
import { chromium } from 'playwright-core';
const BASE = 'https://vestrasales.com';
const exe = process.env.CHROME || '/usr/bin/google-chrome';
/* Normal (kalıcı) profil: gizli pencerede Chrome hiçbir siteyi kurulabilir saymaz ("in-incognito"). */
import { mkdtempSync } from 'node:fs'; import { tmpdir } from 'node:os'; import { join } from 'node:path';
let bad = 0;
for (const [name, path, mobile] of [['EN masaüstü', '/?lang=en', false], ['DE telefon (Android)', '/?lang=de', true], ['AR telefon', '/?lang=ar', true], ['TR telefon', '/?lang=tr', true]]) {
  const ctx = await chromium.launchPersistentContext(mkdtempSync(join(tmpdir(), 'vpwa-')), { executablePath: exe, args: ['--headless=new'], ...(mobile ? { viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36' } : { viewport: { width: 1366, height: 860 } }) });
  const p = await ctx.newPage(); const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  let bip = false; await p.exposeFunction('__bip', () => { bip = true; });
  await p.addInitScript(() => window.addEventListener('beforeinstallprompt', () => window.__bip && window.__bip()));
  await p.goto(BASE + path, { waitUntil: 'load', timeout: 60000 });
  const sw = await p.evaluate(() => Promise.race([navigator.serviceWorker.ready.then(r => 'aktif: ' + (r.active && r.active.scriptURL)), new Promise(r => setTimeout(() => r('20 sn içinde hazır olmadı'), 20000))]));
  await p.reload({ waitUntil: 'load' }); await p.waitForTimeout(3000);
  const cdp = await ctx.newCDPSession(p);
  const inst = await cdp.send('Page.getInstallabilityErrors').catch(e => ({ installabilityErrors: [{ errorId: 'cdp:' + e.message }] }));
  const man = await cdp.send('Page.getAppManifest').catch(() => ({}));
  const ie = (inst.installabilityErrors || []).map(x => x.errorId + (x.errorArguments && x.errorArguments.length ? '(' + x.errorArguments.map(a => a.value).join(',') + ')' : ''));
  const me = (man.errors || []).map(x => x.message);
  let mj = {}; try { mj = JSON.parse(man.data || '{}'); } catch {}
  const btn = await p.evaluate(() => ({ android: !!document.getElementById('btnAndroid'), apk: !!document.querySelector('a[href="/app/vestra.apk"]'), ios: !!document.getElementById('btnIos') }));
  let hint = '';
  if (btn.android) { await p.click('#btnAndroid').catch(() => {}); await p.waitForTimeout(800); hint = await p.evaluate(() => { const h = document.getElementById('appHint'); return h && h.style.display !== 'none' ? h.textContent : ''; }); }
  const ok = ie.length === 0 && me.length === 0;
  if (!ok) bad++;
  console.log(`\n== ${name} ${path}\n   kurulabilir: ${ok ? 'EVET' : 'HAYIR'} ${ie.length ? '· engeller: ' + ie.join(', ') : ''}${me.length ? ' · manifest hataları: ' + me.join(' | ') : ''}`);
  console.log(`   manifest: ${man.url || '-'} · ad "${mj.name || ''}" · kısa "${mj.short_name || ''}" · display ${mj.display || ''} · ikon ${(mj.icons || []).length} · start_url ${mj.start_url || ''}`);
  console.log(`   service worker: ${sw} · beforeinstallprompt: ${bip ? 'geldi' : 'gelmedi (headless/oturum kuralı olabilir)'} · düğmeler: android ${btn.android} ios ${btn.ios} apk ${btn.apk}${hint ? ' · tıklama ipucu: ' + hint.slice(0, 90) : ''} · JS hata ${errs.length}`);
  await ctx.close();
}
console.log(`\nSONUC: ${bad === 0 ? 'tüm sayfalarda kurulabilir' : bad + ' sayfada sorun'}`);
