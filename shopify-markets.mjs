#!/usr/bin/env node
/**
 * Mondimart — Pazar açılışı + Soğuk Zincir Kargo (Shopify Admin GraphQL)
 * ====================================================================
 * Ne yapar (ürün TİPİ / product_type bazlı):
 *   1) ET ürünleri  → sadece FR, BE, ES, LU pazarlarına açık
 *                     + "Livraison Chaîne du Froid ❄️" soğuk zincir kargo profili (zone = FR/BE/ES/LU)
 *   2) KURU GIDA    → tüm Avrupa Birliği'ne (27 ülke) açık (görünürlük + EU kargo profili)
 *   3) TÜM ürünler  → FR/BE/ES/LU pazarlarında satışa açık (publication'a eklenir)
 *
 * GÜVENLİK: Canlı mağaza. Varsayılan **DRY-RUN** (sadece planı yazar, hiçbir şeyi değiştirmez).
 *           Gerçekten uygulamak için:  node shopify-markets.mjs --apply
 *
 * Adım seçimi:  --only=markets,delivery,catalogs   (veya env STEPS=...)
 * Yardım:       node shopify-markets.mjs --help
 *
 * Gerekli ENV (GitHub Secrets):
 *   SHOPIFY_STORE   örn. mondimart.myshopify.com   (zorunlu)
 *   SHOPIFY_TOKEN   Admin API access token         (zorunlu)
 * Opsiyonel ENV:
 *   SHOPIFY_API_VERSION  (varsayılan 2025-07)
 *   MEAT_TYPES  virgülle: et product_type listesi (soğuk zincir)
 *   DRY_TYPES   virgülle: kuru gıda product_type listesi (AB)
 *   COLD_RATE   soğuk zincir kargo ücreti (varsayılan 9.90)
 *   DRY_RATE    kuru gıda kargo ücreti   (varsayılan 5.90)
 *   RATE_CURRENCY (varsayılan EUR)
 */

// ---------------------------------------------------------------------------
// Yapılandırma
// ---------------------------------------------------------------------------
const STORE = (process.env.SHOPIFY_STORE || '').replace(/^https?:\/\//, '').replace(/\/.*$/, '').trim();
const TOKEN = process.env.SHOPIFY_TOKEN || '';
const API_VERSION = process.env.SHOPIFY_API_VERSION || '2025-07';

// Hedef pazarlar (et + tüm ürünler) ve AB-27 (kuru gıda)
const TARGET4 = ['FR', 'BE', 'ES', 'LU'];
const EU27 = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];

// Ürünler Shopify'da product_type ile ayrılmış (audit ile doğrulandı).
// ET (soğuk zincir) tipleri. İstenirse peynir/süt de eklenebilir:
//   'Fromages Frais','Œufs & Laitages' → MEAT_TYPES env ile geçersiz kıl.
const MEAT_TYPES = splitTypes(process.env.MEAT_TYPES, [
  'Viandes Halal', 'Charcuterie Halal',
]);
// KURU GIDA (tüm AB-27): raf-stabil yiyecek tipleri (ev/kozmetik/temizlik HARİÇ).
const DRY_TYPES = splitTypes(process.env.DRY_TYPES, [
  'Épicerie Fine', 'Pâtes & Féculents', 'Thés & Cafés', 'Fruits Secs & Noix',
  'Légumes & Conserves', 'Condiments & Sauces', 'Épices & Condiments',
  'Biscuits & Gâteaux', 'Confiserie & Bonbons', 'Chocolats & Pâtes à Tartiner',
  'Snacks & Apéro', 'Huiles & Matières Grasses', 'Douceurs Orientales',
  'Pâtisserie & Desserts', 'Boissons', 'Petit-Déjeuner', 'Soupes & Potages',
  'Cuisine Asiatique', 'Mezze & Spécialités',
]);

const COLD_RATE = process.env.COLD_RATE || '9.90';
const DRY_RATE = process.env.DRY_RATE || '5.90';
const RATE_CURRENCY = process.env.RATE_CURRENCY || 'EUR';

const COLD_PROFILE_NAME = 'Livraison Chaîne du Froid ❄️';
const DRY_PROFILE_NAME = 'Épicerie sèche — Union Européenne';

// CLI argümanları
const ARGS = process.argv.slice(2);
const APPLY = ARGS.includes('--apply') || /^(1|true|yes)$/i.test(process.env.APPLY || '');
const STEPS = parseSteps();
if (ARGS.includes('--help') || ARGS.includes('-h')) { printHelp(); process.exit(0); }

// ---------------------------------------------------------------------------
// Yardımcılar
// ---------------------------------------------------------------------------
function splitEnv(v, def) {
  if (!v) return def;
  return v.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
}
// product_type değerleri: büyük/küçük harf + aksan korunur
function splitTypes(v, def) {
  if (!v) return def;
  return v.split(',').map(s => s.trim()).filter(Boolean);
}
function parseSteps() {
  const onlyArg = ARGS.find(a => a.startsWith('--only='));
  const raw = onlyArg ? onlyArg.split('=')[1] : (process.env.STEPS || 'markets,delivery,catalogs');
  const set = new Set(raw.split(',').map(s => s.trim().toLowerCase()).filter(Boolean));
  return { markets: set.has('markets'), delivery: set.has('delivery'), catalogs: set.has('catalogs') };
}
function printHelp() {
  console.log(`Mondimart pazar + soğuk zincir aracı

Kullanım:
  node shopify-markets.mjs [--apply] [--only=markets,delivery,catalogs]

  (flag yoksa DRY-RUN: sadece plan yazılır, değişiklik yapılmaz)

Örnekler:
  node shopify-markets.mjs                      # her şeyi önizle (dry-run)
  node shopify-markets.mjs --only=delivery      # sadece kargo profillerini önizle
  node shopify-markets.mjs --apply              # her şeyi UYGULA
`);
}

const LOG = [];
function log(line = '') { console.log(line); LOG.push(line); }
function section(t) { log(''); log(`## ${t}`); }

let GQL_CALLS = 0;
async function gql(query, variables = {}, tries = 5) {
  const url = `https://${STORE}/admin/api/${API_VERSION}/graphql.json`;
  for (let attempt = 1; attempt <= tries; attempt++) {
    GQL_CALLS++;
    let res, json;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Shopify-Access-Token': TOKEN },
        body: JSON.stringify({ query, variables }),
        signal: AbortSignal.timeout(30000),
      });
    } catch (e) {
      if (attempt < tries) { await sleep(1000 * attempt); continue; }
      throw new Error(`Ağ hatası: ${e.message || e}`);
    }
    if (res.status === 429 || res.status >= 500) {
      if (attempt < tries) { await sleep(1500 * attempt); continue; }
      throw new Error(`HTTP ${res.status} (tekrar denemeler tükendi)`);
    }
    json = await res.json();
    // Maliyet bazlı throttle
    const throttled = (json.errors || []).some(e => (e.extensions?.code || '') === 'THROTTLED');
    if (throttled && attempt < tries) { await sleep(2000 * attempt); continue; }
    if (json.errors?.length) {
      throw new Error('GraphQL hatası: ' + JSON.stringify(json.errors, null, 2));
    }
    return json.data;
  }
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function checkUserErrors(label, payload) {
  const ue = payload?.userErrors || [];
  if (ue.length) throw new Error(`${label} userErrors: ` + JSON.stringify(ue));
}

// product_type OR sorgusu (kuru/et için ürün arama)
function typeQuery(types) {
  return types.map(t => `product_type:'${t.replace(/'/g, "\\'")}'`).join(' OR ');
}

async function fetchProductsByTypes(types) {
  const q = `(${typeQuery(types)})`;
  const products = [];
  let cursor = null;
  do {
    const data = await gql(
      `query P($q:String!,$c:String){
        products(first:100, query:$q, after:$c){
          nodes{ id title status variants(first:100){ nodes{ id } } }
          pageInfo{ hasNextPage endCursor }
        }
      }`, { q, c: cursor });
    for (const p of data.products.nodes) {
      products.push({ id: p.id, title: p.title, status: p.status, variantIds: p.variants.nodes.map(v => v.id) });
    }
    cursor = data.products.pageInfo.hasNextPage ? data.products.pageInfo.endCursor : null;
  } while (cursor);
  return products;
}

async function fetchLocations() {
  const data = await gql(`query { locations(first:50){ nodes { id name isActive } } }`);
  return data.locations.nodes.filter(l => l.isActive !== false);
}

async function fetchMarkets() {
  const markets = [];
  let cursor = null;
  do {
    const data = await gql(
      `query M($c:String){
        markets(first:50, after:$c){
          nodes{
            id name handle enabled
            regions(first:250){ nodes{ ... on MarketRegionCountry { code } } }
          }
          pageInfo{ hasNextPage endCursor }
        }
      }`, { c: cursor });
    for (const m of data.markets.nodes) {
      const codes = (m.regions?.nodes || []).map(r => r.code).filter(Boolean);
      markets.push({ id: m.id, name: m.name, handle: m.handle, enabled: m.enabled, countries: codes });
    }
    cursor = data.markets.pageInfo.hasNextPage ? data.markets.pageInfo.endCursor : null;
  } while (cursor);
  return markets;
}

// Plus: MARKET tipi katalogları + publication + bağlı market
async function fetchMarketCatalogs() {
  const out = [];
  let cursor = null;
  do {
    const data = await gql(
      `query C($c:String){
        catalogs(first:50, after:$c, type: MARKET){
          nodes{
            id title status
            publication{ id }
            ... on MarketCatalog { markets(first:25){ nodes { id name } } }
          }
          pageInfo{ hasNextPage endCursor }
        }
      }`, { c: cursor });
    for (const c of data.catalogs.nodes) {
      out.push({
        id: c.id, title: c.title, status: c.status,
        publicationId: c.publication?.id || null,
        marketIds: (c.markets?.nodes || []).map(m => m.id),
      });
    }
    cursor = data.catalogs.pageInfo.hasNextPage ? data.catalogs.pageInfo.endCursor : null;
  } while (cursor);
  return out;
}

function chunk(arr, n) { const o = []; for (let i = 0; i < arr.length; i += n) o.push(arr.slice(i, i + n)); return o; }
function intersect(a, b) { const s = new Set(b); return a.filter(x => s.has(x)); }
function subsetOf(a, b) { const s = new Set(b); return a.length > 0 && a.every(x => s.has(x)); }

// ---------------------------------------------------------------------------
// ADIM: Kargo profilleri (soğuk zincir + kuru gıda EU)
// ---------------------------------------------------------------------------
async function ensureDeliveryProfile({ name, productLabel, variantIds, countries, zoneName, methodName, rate }) {
  // Var mı?
  const data = await gql(`query { deliveryProfiles(first:50){ nodes { id name } } }`);
  const existing = data.deliveryProfiles.nodes.find(p => p.name === name);

  if (existing) {
    log(`  • "${name}" profili zaten var (${existing.id}).`);
    log(`    → ${productLabel}: ${variantIds.length} varyant bu profile bağlanacak (zone'lara dokunulmaz).`);
    if (APPLY && variantIds.length) {
      for (const part of chunk(variantIds, 200)) {
        const r = await gql(
          `mutation U($id:ID!,$p:DeliveryProfileInput!){
            deliveryProfileUpdate(id:$id, profile:$p){ profile{id} userErrors{field message} }
          }`, { id: existing.id, p: { variantsToAssociate: part } });
        checkUserErrors('deliveryProfileUpdate', r.deliveryProfileUpdate);
      }
      log(`    ✓ Varyantlar bağlandı.`);
    }
    return existing.id;
  }

  log(`  • "${name}" profili OLUŞTURULACAK.`);
  log(`    zone: ${countries.join(', ')}  |  ücret: ${rate} ${RATE_CURRENCY}  |  ${productLabel}: ${variantIds.length} varyant`);
  if (!APPLY) return null;

  const locations = (await fetchLocations()).map(l => l.id);
  if (!locations.length) throw new Error('Aktif lokasyon bulunamadı (kargo profili için gerekli).');

  const profile = {
    name,
    locationGroupsToCreate: [{
      locations,
      zonesToCreate: [{
        name: zoneName,
        countries: countries.map(c => ({ code: c, includeAllProvinces: true })),
        methodDefinitionsToCreate: [{
          name: methodName,
          active: true,
          rateDefinition: { price: { amount: String(rate), currencyCode: RATE_CURRENCY } },
        }],
      }],
    }],
    // İlk 200 varyant create ile; kalanı update ile (nested derinliği sınırlı)
    variantsToAssociate: variantIds.slice(0, 200),
  };
  const r = await gql(
    `mutation C($p:DeliveryProfileInput!){
      deliveryProfileCreate(profile:$p){ profile{ id name } userErrors{ field message } }
    }`, { p: profile });
  checkUserErrors('deliveryProfileCreate', r.deliveryProfileCreate);
  const id = r.deliveryProfileCreate.profile.id;
  log(`    ✓ Oluşturuldu: ${id}`);

  // Kalan varyantlar
  const rest = variantIds.slice(200);
  for (const part of chunk(rest, 200)) {
    const u = await gql(
      `mutation U($id:ID!,$p:DeliveryProfileInput!){
        deliveryProfileUpdate(id:$id, profile:$p){ profile{id} userErrors{field message} }
      }`, { id, p: { variantsToAssociate: part } });
    checkUserErrors('deliveryProfileUpdate', u.deliveryProfileUpdate);
  }
  if (rest.length) log(`    ✓ +${rest.length} varyant daha bağlandı.`);
  return id;
}

async function stepDelivery(meat, dry) {
  section('Adım: Kargo profilleri (soğuk zincir + AB kuru gıda)');
  const meatVariants = meat.flatMap(p => p.variantIds);
  const dryVariants = dry.flatMap(p => p.variantIds);

  await ensureDeliveryProfile({
    name: COLD_PROFILE_NAME, productLabel: 'ET (soğuk zincir)',
    variantIds: meatVariants, countries: TARGET4,
    zoneName: 'Chaîne du Froid — FR/BE/ES/LU',
    methodName: 'Livraison réfrigérée ❄️', rate: COLD_RATE,
  });

  await ensureDeliveryProfile({
    name: DRY_PROFILE_NAME, productLabel: 'KURU GIDA (AB)',
    variantIds: dryVariants, countries: EU27,
    zoneName: 'Union Européenne (27)',
    methodName: 'Livraison standard', rate: DRY_RATE,
  });

  log('');
  log('  Not: Bir ürünü özel profile bağlamak onu genel (General) profilden çıkarır;');
  log('  böylece etin kargosu yalnız FR/BE/ES/LU, kuru gıdanınki tüm AB olur.');
}

// ---------------------------------------------------------------------------
// ADIM: Pazar görünürlüğü (Plus katalog/publication)
// ---------------------------------------------------------------------------
async function publicationChange(pubId, ids, mode) {
  // mode: 'add' | 'remove'
  if (!APPLY || !ids.length || !pubId) return;
  const key = mode === 'add' ? 'publishablesToAdd' : 'publishablesToRemove';
  for (const part of chunk(ids, 100)) {
    const r = await gql(
      `mutation P($id:ID!,$in:PublicationUpdateInput!){
        publicationUpdate(id:$id, input:$in){ publication{ id } userErrors{ field message } }
      }`, { id: pubId, in: { [key]: part } });
    checkUserErrors('publicationUpdate', r.publicationUpdate);
  }
}

async function stepCatalogs(meat, dry, markets) {
  section('Adım: Pazar görünürlüğü (Shopify Plus — katalog/publication)');
  let catalogs;
  try {
    catalogs = await fetchMarketCatalogs();
  } catch (e) {
    log(`  ⚠️ Market katalogları okunamadı (API sürümü farklı olabilir): ${e.message}`);
    log('  → Bu adımı atlıyorum. Kargo (delivery) adımı iş hedefini yine de sağlıyor.');
    return;
  }
  const byMarket = new Map();
  for (const c of catalogs) for (const mId of c.marketIds) byMarket.set(mId, c);

  const meatIds = meat.map(p => p.id);
  const dryIds = dry.map(p => p.id);

  for (const m of markets) {
    const cat = byMarket.get(m.id);
    const c4 = intersect(m.countries, TARGET4);
    const cEU = intersect(m.countries, EU27);
    const only4 = subsetOf(m.countries, TARGET4);     // pazar tamamen 4 ülke içinde mi
    const hasNon4 = m.countries.some(x => !TARGET4.includes(x));
    const tags = [];
    if (c4.length) tags.push(`hedef4:${c4.join('/')}`);
    if (cEU.length) tags.push(`AB:${cEU.length}`);
    log(`  • ${m.name} [${m.countries.join(',') || '—'}] ${m.enabled ? '' : '(pasif)'} ${tags.length ? '— ' + tags.join(' ') : ''}`);

    if (!cat || !cat.publicationId) {
      log(`    ⚠️ Bu pazarın katalog/publication'ı bulunamadı, atlanıyor.`);
      continue;
    }

    // Kuru gıda → AB'deki her pazara ekle
    if (cEU.length && dryIds.length) {
      log(`    → kuru gıda yayınla: ${dryIds.length} ürün`);
      await publicationChange(cat.publicationId, dryIds, 'add');
    }
    // Tam 4 ülke pazarı → TÜM ürünler (et dahil) yayınlansın
    if (only4) {
      log(`    → tüm ürünler (et dahil) yayınla: et=${meatIds.length}`);
      await publicationChange(cat.publicationId, meatIds, 'add');
    }
    // 4 dışı ülke içeren pazar → eti KALDIR (et yalnız 4 ülkede)
    if (hasNon4 && meatIds.length) {
      log(`    → et KALDIR (et yalnız FR/BE/ES/LU): ${meatIds.length} ürün`);
      await publicationChange(cat.publicationId, meatIds, 'remove');
      if (c4.length) {
        log(`    ⚠️ ÇAKIŞMA: bu pazar hem hedef ülke (${c4.join('/')}) hem 4-dışı ülke içeriyor.`);
        log(`       Tek pazarda et bu hedef ülkelere de kapanır. Öneri: ${c4.join('/')} için ayrı pazar kurun.`);
      }
    }
  }
  if (!APPLY) log('\n  (dry-run: yukarıdaki yayınlama/kaldırma işlemleri --apply ile uygulanır)');
}

// ---------------------------------------------------------------------------
// ADIM: Pazar keşfi/doğrulama (salt okunur)
// ---------------------------------------------------------------------------
function stepMarketsReport(markets) {
  section('Adım: Pazar keşfi & doğrulama (salt okunur)');
  const covered = new Set();
  for (const m of markets) for (const c of m.countries) if (m.enabled) covered.add(c);

  log('  Mevcut pazarlar:');
  for (const m of markets) {
    log(`   - ${m.enabled ? '🟢' : '⚪'} ${m.name} [${m.countries.join(', ') || '—'}]`);
  }
  const miss4 = TARGET4.filter(c => !covered.has(c));
  const missEU = EU27.filter(c => !covered.has(c));
  log('');
  log(`  Hedef pazarlar (FR/BE/ES/LU) kapsama: ${miss4.length ? '❌ eksik → ' + miss4.join(', ') : '✅ hepsi aktif bir pazarda'}`);
  log(`  AB-27 kapsama (kuru gıda için): ${missEU.length ? '⚠️ eksik → ' + missEU.join(', ') : '✅ hepsi aktif bir pazarda'}`);
  if (miss4.length || missEU.length) {
    log('  → Eksik ülkeler için Shopify Admin > Ayarlar > Markets bölümünden pazar ekleyin/etkinleştirin.');
  }
}

// ---------------------------------------------------------------------------
// main
// ---------------------------------------------------------------------------
(async () => {
  log(`# Mondimart — Pazar + Soğuk Zincir (${APPLY ? '🔴 APPLY' : '🟡 DRY-RUN'})`);
  log(`_${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC · API ${API_VERSION}_`);

  if (!STORE || !TOKEN) {
    log('');
    log('❌ SHOPIFY_STORE ve SHOPIFY_TOKEN env zorunlu.');
    log('   Örn: SHOPIFY_STORE=mondimart.myshopify.com SHOPIFY_TOKEN=shpat_... node shopify-markets.mjs');
    process.exit(1);
  }
  log(`Mağaza: ${STORE}`);
  log(`Adımlar: ${Object.entries(STEPS).filter(([, v]) => v).map(([k]) => k).join(', ') || '(yok)'}`);
  log(`Et tipleri (soğuk zincir): ${MEAT_TYPES.join(', ')}`);
  log(`Kuru gıda tipleri (AB): ${DRY_TYPES.join(', ')}`);

  try {
    section('Ürünler (product_type’a göre)');
    const meat = await fetchProductsByTypes(MEAT_TYPES);
    const dry = await fetchProductsByTypes(DRY_TYPES);
    log(`  ET: ${meat.length} ürün, ${meat.reduce((n, p) => n + p.variantIds.length, 0)} varyant`);
    log(`  KURU GIDA: ${dry.length} ürün, ${dry.reduce((n, p) => n + p.variantIds.length, 0)} varyant`);
    if (!meat.length) log('  ⚠️ Et tipiyle ürün bulunamadı — MEAT_TYPES değerini kontrol edin.');
    if (!dry.length) log('  ⚠️ Kuru gıda tipiyle ürün bulunamadı — DRY_TYPES değerini kontrol edin.');

    const markets = (STEPS.markets || STEPS.catalogs) ? await fetchMarkets() : [];

    if (STEPS.markets) stepMarketsReport(markets);
    if (STEPS.delivery) await stepDelivery(meat, dry);
    if (STEPS.catalogs) await stepCatalogs(meat, dry, markets);

    section('Özet');
    log(`  GraphQL çağrısı: ${GQL_CALLS}`);
    log(APPLY
      ? '  ✅ Değişiklikler uygulandı. Shopify Admin > Markets ve Settings > Shipping bölümünden doğrulayın.'
      : '  🟡 DRY-RUN bitti — hiçbir şey değişmedi. Uygulamak için --apply ekleyin.');
  } catch (e) {
    log('');
    log('❌ HATA: ' + (e.message || e));
    writeSummary();
    process.exit(1);
  }
  writeSummary();
})();

function writeSummary() {
  if (process.env.GITHUB_STEP_SUMMARY) {
    import('node:fs').then(fs => {
      try { fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, LOG.join('\n') + '\n'); } catch {}
    });
  }
}
