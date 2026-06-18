import { readFileSync } from 'fs';

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const SH = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN) { console.error('❌ SHOPIFY_TOKEN gerekli'); process.exit(1); }

const delay = ms => new Promise(r => setTimeout(r, ms));

async function getThemes() {
  const r = await fetch(`${BASE}/themes.json`, { headers: SH });
  const d = await r.json();
  return d.themes || [];
}

async function uploadAsset(themeId, key, filePath) {
  const content = readFileSync(filePath);
  const isText = /\.(liquid|css|js|svg|json)$/.test(filePath);
  const body = isText
    ? { asset: { key, value: content.toString('utf8') } }
    : { asset: { key, attachment: content.toString('base64') } };
  const r = await fetch(`${BASE}/themes/${themeId}/assets.json`, {
    method: 'PUT', headers: SH, body: JSON.stringify(body)
  });
  const d = await r.json();
  if (d.errors) throw new Error(JSON.stringify(d.errors));
  return d.asset;
}

// Yüklenecek dosyalar: [Shopify asset key, local path]
const FILES = [
  ['assets/mondimart-logo.svg',      'mondimart/assets/mondimart-logo.svg'],
  ['assets/mondimart-premium.css',   'mondimart/assets/mondimart-premium.css'],
  ['layout/checkout.liquid',         'mondimart/layout/checkout.liquid'],
  ['sections/header.liquid',         'mondimart/sections/header.liquid'],
  ['snippets/cart-drawer.liquid',    'mondimart/snippets/cart-drawer.liquid'],
  ['templates/index.liquid',         'mondimart/templates/index.liquid'],
  ['templates/page.confidentialite.liquid', 'mondimart/templates/page.confidentialite.liquid'],
  ['templates/page.faq.liquid',      'mondimart/templates/page.faq.liquid'],
];

console.log('🔍 Temalar yükleniyor...');
const themes = await getThemes();
themes.forEach(t => console.log(`   ${t.id} — ${t.name} [${t.role}]`));

// Aktif temayı bul
const active = themes.find(t => t.role === 'main');
if (!active) { console.error('❌ Aktif tema bulunamadı'); process.exit(1); }
console.log(`\n✅ Aktif tema: "${active.name}" (ID: ${active.id})\n`);

for (const [key, path] of FILES) {
  try {
    await uploadAsset(active.id, key, path);
    console.log(`   ✅ ${key}`);
    await delay(400);
  } catch (e) {
    console.error(`   ❌ ${key}: ${e.message}`);
  }
}

console.log('\n🎉 Yükleme tamamlandı!');
