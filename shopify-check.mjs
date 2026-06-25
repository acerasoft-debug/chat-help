#!/usr/bin/env node
/**
 * Shopify bağlantı & izin testi (Admin API — shpat_ token)
 * ========================================================
 * Ne yapar (SADECE OKUR, hiçbir şeyi değiştirmez):
 *   1) Token + mağaza domaini doğru mu?  → /shop.json ile bağlantıyı test eder
 *   2) Hangi izinler (access scopes) verilmiş?  → /oauth/access_scopes.json
 *   3) Mağazada ne kadar veri var?  → ürün / koleksiyon / müşteri / sipariş sayıları
 *
 * Kullanım:
 *   SHOPIFY_STORE=1ngnep-0e.myshopify.com SHOPIFY_TOKEN=shpat_xxx node shopify-check.mjs
 *
 * Gerekli ENV:
 *   SHOPIFY_STORE   örn. 1ngnep-0e.myshopify.com  (zorunlu)
 *   SHOPIFY_TOKEN   Admin API access token (shpat_...)  (zorunlu)
 * Opsiyonel ENV:
 *   SHOPIFY_API_VERSION  (varsayılan 2025-07)
 *
 * GÜVENLİK: Token'ı sohbete/koda YAZMA. Sadece env değişkeni olarak ver
 *           veya GitHub Secrets'a koy. Bu script token'ı hiçbir yere kaydetmez.
 */

const STORE = (process.env.SHOPIFY_STORE || '').replace(/^https?:\/\//, '').replace(/\/.*$/, '').trim();
const TOKEN = process.env.SHOPIFY_TOKEN || '';
const API_VERSION = process.env.SHOPIFY_API_VERSION || '2025-07';

// Göç (migration) için tipik olarak istenen OKUMA izinleri — eksik varsa uyarır.
const RECOMMENDED_READ = [
  'read_products', 'read_inventory', 'read_orders', 'read_customers',
  'read_content', 'read_themes', 'read_price_rules', 'read_discounts',
  'read_fulfillments', 'read_locations', 'read_shipping',
];

function die(msg) { console.error('\n❌ ' + msg + '\n'); process.exit(1); }

if (!STORE || !TOKEN) {
  die('SHOPIFY_STORE ve SHOPIFY_TOKEN env zorunlu.\n' +
      '   Örn: SHOPIFY_STORE=1ngnep-0e.myshopify.com SHOPIFY_TOKEN=shpat_... node shopify-check.mjs');
}
if (!/\.myshopify\.com$/.test(STORE)) {
  console.warn(`⚠️  Mağaza domaini "*.myshopify.com" gibi görünmüyor: ${STORE}`);
  console.warn('   (Özel alan adı değil, mağazanın myshopify.com adresini kullan: ör. 1ngnep-0e.myshopify.com)\n');
}
if (!/^shpat_/.test(TOKEN)) {
  console.warn('⚠️  Token "shpat_" ile başlamıyor. Admin API access token (shpat_...) bekleniyor.');
  console.warn('   "shpss_" → Storefront token (farklı), "shpca_/shppa_" → başka tür. Doğru token kullan.\n');
}

const BASE = `https://${STORE}/admin/api/${API_VERSION}`;
const HEADERS = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

async function get(url, tries = 4) {
  for (let attempt = 1; attempt <= tries; attempt++) {
    let res;
    try {
      res = await fetch(url, { headers: HEADERS, signal: AbortSignal.timeout(30000) });
    } catch (e) {
      if (attempt < tries) { await sleep(1000 * attempt); continue; }
      return { ok: false, status: 0, error: e.message || String(e) };
    }
    if (res.status === 429 || res.status >= 500) {
      if (attempt < tries) { await sleep(1500 * attempt); continue; }
    }
    const json = await res.json().catch(() => null);
    return { ok: res.ok, status: res.status, json };
  }
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function explainStatus(status) {
  switch (status) {
    case 401: return 'Token geçersiz / yanlış kopyalanmış (401 Unauthorized).';
    case 402: return 'Mağaza pasif/ödeme bekliyor (402).';
    case 403: return 'Bu kaynak için izin (scope) verilmemiş (403 Forbidden).';
    case 404: return 'Bulunamadı — mağaza domaini yanlış olabilir (404).';
    case 0:   return 'Ağ hatası — internet/erişim sorunu.';
    default:  return `Beklenmeyen durum: HTTP ${status}.`;
  }
}

(async () => {
  console.log(`# Shopify bağlantı testi`);
  console.log(`Mağaza: ${STORE} · API ${API_VERSION}\n`);

  // 1) Bağlantı + mağaza bilgisi -------------------------------------------
  console.log('1) Token & mağaza bağlantısı');
  const shop = await get(`${BASE}/shop.json`);
  if (!shop.ok) {
    die(`Bağlantı başarısız — ${explainStatus(shop.status)}` +
        (shop.error ? `\n   Detay: ${shop.error}` : '') +
        `\n\n   Kontrol et:` +
        `\n   • SHOPIFY_STORE doğru mu? (ör. 1ngnep-0e.myshopify.com)` +
        `\n   • Token tam mı, baş/son boşluk yok mu? ("Reveal token once" ile alınan shpat_...)` +
        `\n   • Uygulama bu mağazaya "Install app" ile kurulu mu?`);
  }
  const s = shop.json.shop;
  console.log(`   ✓ Bağlantı OK — "${s.name}"`);
  console.log(`     plan: ${s.plan_display_name || s.plan_name || '—'} · para birimi: ${s.currency} · ülke: ${s.country_code || '—'}`);
  console.log(`     domain: ${s.domain || '—'} · e-posta: ${s.email || '—'}\n`);

  // 2) Verilen izinler (access scopes) -------------------------------------
  console.log('2) Verilen izinler (access scopes)');
  const sc = await get(`https://${STORE}/admin/oauth/access_scopes.json`);
  let granted = [];
  if (sc.ok && Array.isArray(sc.json?.access_scopes)) {
    granted = sc.json.access_scopes.map(x => x.handle).sort();
    console.log(`   ✓ ${granted.length} izin verilmiş:`);
    console.log('     ' + (granted.join(', ') || '(yok)'));
    const missing = RECOMMENDED_READ.filter(x => !granted.includes(x));
    if (missing.length) {
      console.log(`   ⚠️  Göç için faydalı ama EKSİK okuma izinleri: ${missing.join(', ')}`);
      console.log('      (İhtiyacın olanları admin > app > "API access scopes" bölümünden ekleyip "Save" + reinstall.)');
    } else {
      console.log('   ✓ Göç için önerilen okuma izinlerinin hepsi mevcut.');
    }
  } else {
    console.log(`   ⚠️  İzin listesi okunamadı (${explainStatus(sc.status)}). Bağlantı yine de çalışıyor.`);
  }
  console.log('');

  // 3) Mağaza içeriği (özet sayımlar) --------------------------------------
  console.log('3) Mağaza içeriği (özet sayımlar)');
  const counts = [
    { label: 'Ürün',          path: 'products/count.json',           scope: 'read_products' },
    { label: 'Koleksiyon (custom)', path: 'custom_collections/count.json', scope: 'read_products' },
    { label: 'Koleksiyon (smart)',  path: 'smart_collections/count.json',  scope: 'read_products' },
    { label: 'Müşteri',       path: 'customers/count.json',          scope: 'read_customers' },
    { label: 'Sipariş',       path: 'orders/count.json?status=any',  scope: 'read_orders' },
  ];
  for (const c of counts) {
    const r = await get(`${BASE}/${c.path}`);
    if (r.ok && typeof r.json?.count === 'number') {
      console.log(`   • ${c.label}: ${r.json.count}`);
    } else if (r.status === 403) {
      console.log(`   • ${c.label}: ⛔ izin yok (${c.scope} gerekli)`);
    } else {
      console.log(`   • ${c.label}: ⚠️ okunamadı (${explainStatus(r.status)})`);
    }
  }

  console.log('\n✅ Test bitti. Bağlantı çalışıyorsa bir sonraki adım: tüm veriyi JSON/CSV olarak dışa aktarmak.');
})();
