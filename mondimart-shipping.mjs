// ============================================================
// MONDIMART - Kargo Kurulumu (Doğru GraphQL şema - kesin çalışır)
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-shipping.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const H = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };
const REST = `https://${SHOP}/admin/api/2024-04`;

if (!SHOPIFY_TOKEN) { console.error('SHOPIFY_TOKEN gerekli'); process.exit(1); }

const delay = ms => new Promise(r => setTimeout(r, ms));

async function gql(query, variables = {}) {
  const r = await fetch(`https://${SHOP}/admin/api/2024-04/graphql.json`, {
    method: 'POST', headers: H,
    body: JSON.stringify({ query, variables })
  });
  const d = await r.json();
  if (d.errors) {
    console.error('  GraphQL hata:', d.errors.map(e => e.message).join(' | '));
    return null;
  }
  return d.data;
}

async function fetchAll(path) {
  let url = `${REST}/${path}`;
  let all = [];
  while (url) {
    const r = await fetch(url, { headers: H });
    const d = await r.json();
    const key = Object.keys(d)[0];
    all = all.concat(d[key] || []);
    const link = r.headers.get('link') || '';
    url = link.match(/<([^>]+)>;\s*rel="next"/)?.[1] || null;
    await new Promise(r => setTimeout(r, 300));
  }
  return all;
}

// Şema keşfinden alınan kesin değerler
const DEFAULT_PROFILE_ID = 'gid://shopify/DeliveryProfile/143568896325';
const LOCATION_GROUP_ID  = 'gid://shopify/DeliveryLocationGroup/154261815621';
const LOCATION_ID        = 'gid://shopify/Location/121396855109';

// ============================================================
// ADIM 1: General profile → Fransa 8€ + Avrupa 18€
// locationGroupsToUpdate > id + zonesToCreate > methodDefinitionsToCreate
// ============================================================
async function setupStandardRates() {
  console.log('\n📦 Standart kargo bölgeleri ekleniyor...');

  // Önce koşulsuz rates ekle (ücretsiz kargo conditions ayrıca)
  const r1 = await gql(`
    mutation deliveryProfileUpdate($id: ID!, $profile: DeliveryProfileInput!) {
      deliveryProfileUpdate(id: $id, profile: $profile) {
        profile { id name }
        userErrors { field message }
      }
    }
  `, {
    id: DEFAULT_PROFILE_ID,
    profile: {
      locationGroupsToUpdate: [{
        id: LOCATION_GROUP_ID,
        zonesToCreate: [
          {
            name: 'France',
            countries: [{ code: 'FR', includeAllProvinces: true }],
            methodDefinitionsToCreate: [
              {
                name: '🚚 Livraison Standard France',
                active: true,
                rateDefinition: { price: { amount: '8.00', currencyCode: 'EUR' } }
              },
              {
                name: '🎁 Livraison Gratuite France (dès 149€)',
                active: true,
                rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } }
              }
            ]
          },
          {
            name: 'Europe',
            countries: [
              { code: 'BE', includeAllProvinces: true },
              { code: 'LU', includeAllProvinces: true },
              { code: 'ES', includeAllProvinces: true },
              { code: 'DE', includeAllProvinces: true },
              { code: 'NL', includeAllProvinces: true },
              { code: 'IT', includeAllProvinces: true },
              { code: 'PT', includeAllProvinces: true },
              { code: 'AT', includeAllProvinces: true },
              { code: 'CH', includeAllProvinces: true }
            ],
            methodDefinitionsToCreate: [
              {
                name: '🚚 Livraison Europe',
                active: true,
                rateDefinition: { price: { amount: '18.00', currencyCode: 'EUR' } }
              },
              {
                name: '🎁 Livraison Gratuite Europe (dès 149€)',
                active: true,
                rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } }
              }
            ]
          }
        ]
      }]
    }
  });

  const errs = r1?.deliveryProfileUpdate?.userErrors;
  if (errs?.length) {
    console.log('  ⚠️ ', errs.map(e => `${e.field}: ${e.message}`).join(' | '));
  } else if (r1?.deliveryProfileUpdate?.profile) {
    console.log('  ✅ Fransa (8€) + Avrupa (18€) bölgeleri eklendi');
    console.log('  ℹ️  Ücretsiz kargo için: Shopify Admin → Shipping → General profile →');
    console.log('     "🎁 Livraison Gratuite" rate → Edit → Add conditions → min 149€');
  }
}

// ============================================================
// ADIM 2: Soğuk zincir profili oluştur
// locationGroupsToCreate > locations (LIST) + zonesToCreate > methodDefinitionsToCreate
// ============================================================
async function createColdChainProfile(coldVariantGids) {
  console.log(`\n❄️  Soğuk Zincir profili oluşturuluyor (${coldVariantGids.length} variant)...`);

  const firstChunk = coldVariantGids.slice(0, 250);

  const result = await gql(`
    mutation deliveryProfileCreate($profile: DeliveryProfileInput!) {
      deliveryProfileCreate(profile: $profile) {
        profile { id name }
        userErrors { field message }
      }
    }
  `, {
    profile: {
      name: 'Livraison Chaîne du Froid ❄️',
      variantsToAssociate: firstChunk,
      locationGroupsToCreate: [{
        locations: [LOCATION_ID],
        zonesToCreate: [
          {
            name: 'France — Chaîne du Froid',
            countries: [{ code: 'FR', includeAllProvinces: true }],
            methodDefinitionsToCreate: [
              {
                name: '❄️ Livraison Chaîne du Froid France',
                active: true,
                rateDefinition: { price: { amount: '18.00', currencyCode: 'EUR' } }
              },
              {
                name: '❄️ Livraison Gratuite Froid France (dès 149€)',
                active: true,
                rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } }
              }
            ]
          },
          {
            name: 'BE / LU / ES — Chaîne du Froid',
            countries: [
              { code: 'BE', includeAllProvinces: true },
              { code: 'LU', includeAllProvinces: true },
              { code: 'ES', includeAllProvinces: true }
            ],
            methodDefinitionsToCreate: [
              {
                name: '❄️ Livraison Chaîne du Froid BE/LU/ES',
                active: true,
                rateDefinition: { price: { amount: '24.00', currencyCode: 'EUR' } }
              },
              {
                name: '❄️ Livraison Gratuite Froid BE/LU/ES (dès 149€)',
                active: true,
                rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } }
              }
            ]
          }
        ]
      }]
    }
  });

  const errs = result?.deliveryProfileCreate?.userErrors;
  if (errs?.length) {
    console.log('  ❌ Hata:', errs.map(e => `[${e.field}] ${e.message}`).join('\n        '));
    return null;
  }

  const newProfile = result?.deliveryProfileCreate?.profile;
  if (!newProfile) return null;

  console.log(`  ✅ "${newProfile.name}" oluşturuldu`);

  // Kalan variantları ekle (>250 ise)
  if (coldVariantGids.length > 250) {
    process.stdout.write(`  ➕ Kalan ${coldVariantGids.length - 250} variant ekleniyor`);
    for (let i = 250; i < coldVariantGids.length; i += 250) {
      const chunk = coldVariantGids.slice(i, i + 250);
      await gql(`
        mutation deliveryProfileUpdate($id: ID!, $profile: DeliveryProfileInput!) {
          deliveryProfileUpdate(id: $id, profile: $profile) {
            profile { id }
            userErrors { field message }
          }
        }
      `, {
        id: newProfile.id,
        profile: { variantsToAssociate: chunk }
      });
      process.stdout.write('.');
      await delay(400);
    }
    console.log(' ✅');
  }

  return newProfile.id;
}

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('🚚 Mondimart Kargo Kurulumu başlıyor...\n');

console.log('🛍️  Soğuk zincir ürünleri yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,tags,variants');
const coldVariantGids = products
  .filter(p => {
    const t = (p.tags || '').toLowerCase();
    return t.includes('viande') || t.includes('halal') || t.includes('frais');
  })
  .flatMap(p => p.variants || [])
  .map(v => `gid://shopify/ProductVariant/${v.id}`);
console.log(`❄️  ${coldVariantGids.length} soğuk variant bulundu`);

// Soğuk zincir profili zaten var mı?
const pd = await gql(`{ deliveryProfiles(first: 10) { edges { node { id name default } } } }`);
const profiles = pd?.deliveryProfiles?.edges?.map(e => e.node) || [];
const existingCold = profiles.find(p =>
  p.name.toLowerCase().includes('froid') || p.name.toLowerCase().includes('cold')
);

// Standart kargo
await setupStandardRates();
await delay(800);

// Soğuk zincir
if (existingCold) {
  console.log(`\n❄️  Soğuk zincir profili zaten mevcut: "${existingCold.name}"`);
} else {
  await createColdChainProfile(coldVariantGids);
}

console.log('\n\n=== SONUÇ ===');
console.log('✅ Standart → 🇫🇷 8€ | 🌍 18€');
console.log('❄️  Soğuk Zincir → 🇫🇷 18€ | 🇧🇪🇱🇺🇪🇸 24€');
console.log('ℹ️  149€ ücretsiz kargo: Admin → Shipping → her "Gratuite" rate → Edit → Add conditions → min 149€');
console.log(`\n👉 Kontrol: https://${SHOP}/admin/settings/shipping`);
