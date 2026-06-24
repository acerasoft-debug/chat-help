#!/usr/bin/env node
/**
 * Mondimart — Tema teşhis + foto düzeltme (Shopify Admin REST Asset API)
 * =====================================================================
 *   node shopify-theme.mjs              # DUMP (salt-okunur): temayı inceler, CSS/liquid'i theme-dump/'a yazar
 *   node shopify-theme.mjs --fix-photos # ürün foto ORTALAMA CSS'ini ekler (yedekli, geri alınabilir)
 *
 * ENV:
 *   SHOPIFY_TOKEN         (zorunlu)  Admin API token  — dump için read_themes, fix için write_themes
 *   SHOPIFY_STORE         (vars. pftzey-y0.myshopify.com)
 *   SHOPIFY_API_VERSION   (vars. 2024-04)
 */
import { mkdir, writeFile } from 'node:fs/promises';

const STORE = (process.env.SHOPIFY_STORE || 'pftzey-y0.myshopify.com')
  .replace(/^https?:\/\//, '').replace(/\/.*$/, '').trim();
const TOKEN = process.env.SHOPIFY_TOKEN || '';
const VER = process.env.SHOPIFY_API_VERSION || '2024-04';
const FIX = process.argv.includes('--fix-photos');

if (!TOKEN) { console.error('❌ SHOPIFY_TOKEN env gerekli.'); process.exit(1); }

const baseUrl = `https://${STORE}/admin/api/${VER}`;
async function rest(path, opts = {}) {
  const res = await fetch(baseUrl + path, {
    ...opts,
    headers: { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json', ...(opts.headers || {}) },
  });
  const txt = await res.text();
  let json; try { json = JSON.parse(txt); } catch { json = txt; }
  if (!res.ok) throw new Error(`HTTP ${res.status} ${path}\n${typeof json === 'string' ? json : JSON.stringify(json)}`);
  return json;
}

const MARKER = '/* mondimart-foto-fix */';
const FIX_CSS = `
${MARKER}
.product__media-wrapper,.product__media-list,.product__media,.product__media-item,.product-single__media{margin-left:auto!important;margin-right:auto!important;float:none!important;}
.product__media-list{justify-content:center!important;}
.product__media img,.product__media-item img,.product__media-image,.product-single__photo,.product-single__photo img{display:block!important;margin-left:auto!important;margin-right:auto!important;width:100%!important;max-width:100%!important;object-fit:contain!important;transform:none!important;}
`;

(async () => {
  // 1) Yayınlı (main) tema
  const { themes } = await rest('/themes.json');
  const main = themes.find(t => t.role === 'main') || themes[0];
  console.log(`Mağaza: ${STORE}  ·  API ${VER}`);
  console.log(`Yayınlı tema: ${main.name}  (id ${main.id})`);
  console.log(`Tüm temalar: ${themes.map(t => `${t.name}[${t.role}]`).join(', ')}`);

  // 2) Asset listesi
  const { assets } = await rest(`/themes/${main.id}/assets.json`);
  const cssKeys = assets.map(a => a.key).filter(k => /\.css(\.liquid)?$/.test(k));
  const prodKeys = assets.map(a => a.key).filter(k => /(main-product|product-media|product-grid-item|product-card|card-product|product\.liquid)/i.test(k));

  if (FIX) {
    const target = cssKeys.find(k => /assets\/base\.css$/.test(k))
      || cssKeys.find(k => /assets\/theme\.css$/.test(k))
      || cssKeys.find(k => /assets\/.*\.css$/.test(k) && !/\.liquid$/.test(k));
    if (!target) { console.error('❌ Hedef CSS (base.css/theme.css) bulunamadı. Önce dump çalıştır.'); process.exit(1); }
    const a = await rest(`/themes/${main.id}/assets.json?asset[key]=${encodeURIComponent(target)}`);
    const value = a.asset.value || '';
    if (value.includes(MARKER)) { console.log(`\n✓ Düzeltme zaten ekli (${target}). Tekrar eklenmedi.`); return; }
    await mkdir('theme-backup', { recursive: true });
    await writeFile(`theme-backup/${target.replace(/\//g, '__')}.bak`, value);
    await rest(`/themes/${main.id}/assets.json`, {
      method: 'PUT',
      body: JSON.stringify({ asset: { key: target, value: value + '\n' + FIX_CSS } }),
    });
    console.log(`\n✅ Foto ortalama CSS eklendi → ${target}`);
    console.log(`   Yedek: theme-backup/${target.replace(/\//g, '__')}.bak`);
    console.log('   Ürün sayfasını yenile. Geri almak için yedeği aynı asset\'e PUT et.');
    return;
  }

  // DUMP (salt-okunur) — tüm CSS + ürün şablonları + ilgili snippet/section'lar
  console.log(`\nCSS dosyaları (${cssKeys.length}):`);
  cssKeys.forEach(k => console.log('  ' + k));
  console.log(`\nÜrün/şablon dosyaları (${prodKeys.length}):`);
  prodKeys.forEach(k => console.log('  ' + k));

  await mkdir('theme-dump', { recursive: true });
  const extra = assets.map(a => a.key)
    .filter(k => /(snippets|sections)\/.*(product|media|image|photo|card|gallery|thumb)/i.test(k));
  const want = [...new Set([...cssKeys, ...prodKeys, ...extra])];
  const contents = {};
  for (const key of want) {
    try {
      const a = await rest(`/themes/${main.id}/assets.json?asset[key]=${encodeURIComponent(key)}`);
      contents[key] = a.asset.value ?? '';
      await writeFile(`theme-dump/${key.replace(/\//g, '__')}`, contents[key]);
    } catch (e) { console.log(`  (okunamadı: ${key})`); }
  }
  console.log(`\n✓ ${Object.keys(contents).length} dosya theme-dump/ klasörüne kaydedildi.`);

  // FOKUS: ürün-SAYFASI CSS kuralları (tam) + galeri JS — kaymanın olduğu yer
  const PRX = /(product-layout|product-page|pg-wrap|pg-strip|pg-thumb|pg-dot|pg-lb|product-info-wrap|product-main|product-vendor|product-gallery|product-media)/i;
  for (const key of cssKeys) {
    const v = contents[key]; if (!v) continue;
    const hits = v.split('\n').map((l, i) => [i + 1, l]).filter(([, l]) => PRX.test(l) && l.trim());
    console.log(`\n##### ÜRÜN-SAYFASI CSS — ${key}  (${hits.length} satır) #####`);
    hits.forEach(([n, l]) => console.log(String(n).padStart(5) + '  ' + l.trim().slice(0, 260)));
  }
  // Galeri JS: product.liquid içindeki <script> bloklarını TAM bas
  for (const key of prodKeys) {
    const v = contents[key]; if (!v) continue;
    const scripts = v.match(/<script[\s\S]*?<\/script>/gi) || [];
    const gal = scripts.filter(s => /(pg-strip|pgGoTo|pgStripClick|pgClose|pgLb|touchstart|swipe|pg-thumb|pgDrag|pgSnap)/i.test(s));
    console.log(`\n##### GALERİ JS — ${key}  (${gal.length}/${scripts.length} script bloğu) #####`);
    (gal.length ? gal : scripts).forEach((s, idx) => {
      console.log(`--- script #${idx + 1} ---`);
      console.log(s.length > 9000 ? s.slice(0, 9000) + '\n…(kesildi)' : s);
    });
  }
  console.log('\n→ "#####" başlıklı blokları (ÜRÜN-SAYFASI CSS + GALERİ JS) bana yapıştır; kaymayı kesin düzelteceğim.');
})();
