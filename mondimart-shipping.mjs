// ============================================================
// MONDIMART - Kargo Profilleri Kurulumu (GraphQL 2024-04)
// - Standart: Fransa 8€, AB 18€, 149€ üstü ücretsiz
// - Soğuk Zincir (viande/halal/frais): Fransa 18€, BE/LU/ES 24€, 149€ üstü ücretsiz
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-shipping.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const GQL = `https://${SHOP}/admin/api/2024-04/graphql.json`;
const REST_BASE = `https://${SHOP}/admin/api/2024-04`;
const H = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN) { console.error('SHOPIFY_TOKEN gerekli'); process.exit(1); }

const delay = ms => new Promise(r => setTimeout(r, ms));

async function gql(query, variables = {}) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(GQL, {
        method: 'POST', headers: H,
        body: JSON.stringify({ query, variables })
      });
      const d = await r.json();
      if (d.errors) {
        console.error('GraphQL hata:', JSON.stringify(d.errors.map(e => e.message), null, 2));
        return null;
      }
      return d.data;
    } catch(e) {
      if (i === 4) throw e;
      await new Promise(r => setTimeout(r, 2000 * i));
    }
  }
}

async function fetchAll(path) {
  let url = `${REST_BASE}/${path}`;
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

// ============================================================
// ADIM 1: Lokasyon ID'lerini al
// ============================================================
async function getLocationIds() {
  const data = await gql(`{
    locations(first: 10, includeLegacy: false) {
      edges { node { id name } }
    }
  }`);
  const locs = data?.locations?.edges?.map(e => e.node) || [];
  locs.forEach(l => console.log(`   📍 ${l.name}: ${l.id}`));
  return locs.map(l => l.id);
}

// ============================================================
// ADIM 2: Varsayılan profil bilgilerini al
// ============================================================
async function getDefaultProfile() {
  const data = await gql(`{
    deliveryProfiles(first: 20) {
      edges {
        node {
          id
          name
          default
          profileLocationGroups {
            locationGroup { id }
          }
        }
      }
    }
  }`);
  const profiles = data?.deliveryProfiles?.edges?.map(e => e.node) || [];
  profiles.forEach(p => console.log(`   - ${p.name} ${p.default ? '(VARSAYILAN)' : ''} → ${p.id}`));
  return profiles.find(p => p.default);
}

// ============================================================
// ADIM 3: Standart profili güncelle (Fransa 8€, AB 18€)
// ============================================================
async function setupStandardShipping(profileId, locationGroupId) {
  console.log('\n📦 Standart kargo bölgeleri ekleniyor...');

  const zones = [
    {
      name: 'France — Livraison Standard',
      countries: [{ code: 'FR', includeAllProvinces: true }],
      methodDefinitionsToCreate: [
        {
          name: '🚚 Livraison Standard France',
          active: true,
          rateDefinition: { price: { amount: '8.00', currencyCode: 'EUR' } }
        },
        {
          name: '🎁 Livraison Gratuite France (149€+)',
          active: true,
          rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } },
          priceConditionsToCreate: [{
            field: 'TOTAL_PRICE',
            operator: 'GREATER_THAN_OR_EQUAL_TO',
            criteria: { amount: '149.00', currencyCode: 'EUR' }
          }]
        }
      ]
    },
    {
      name: 'Europe — Livraison Standard',
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
          name: '🎁 Livraison Gratuite Europe (149€+)',
          active: true,
          rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } },
          priceConditionsToCreate: [{
            field: 'TOTAL_PRICE',
            operator: 'GREATER_THAN_OR_EQUAL_TO',
            criteria: { amount: '149.00', currencyCode: 'EUR' }
          }]
        }
      ]
    }
  ];

  for (const zone of zones) {
    const result = await gql(`
      mutation deliveryProfileUpdate($id: ID!, $profile: DeliveryProfileInput!) {
        deliveryProfileUpdate(id: $id, profile: $profile) {
          profile { id name }
          userErrors { field message }
        }
      }
    `, {
      id: profileId,
      profile: {
        locationGroupsToUpdate: [{
          locationGroupId,
          zonesToCreate: [zone]
        }]
      }
    });

    const errs = result?.deliveryProfileUpdate?.userErrors;
    if (errs?.length > 0) {
      console.log(`   ⚠️  ${zone.name}: ${errs.map(e => e.message).join(' | ')}`);
    } else if (result?.deliveryProfileUpdate?.profile) {
      console.log(`   ✅ ${zone.name} eklendi`);
    }
    await delay(600);
  }
}

// ============================================================
// ADIM 4: Soğuk zincir profili oluştur
// ============================================================
async function createColdChainProfile(coldVariantGids, locationIds) {
  console.log(`\n❄️  Soğuk Zincir profili oluşturuluyor (${coldVariantGids.length} variant)...`);

  // Çok fazla variant varsa ilk 250'yi al (Shopify limiti)
  const variantsChunk = coldVariantGids.slice(0, 250);

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
      variantsToAssociate: variantsChunk,
      locationGroupsToCreate: [{
        locationIds,
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
                name: '❄️ Livraison Gratuite Chaîne du Froid (149€+)',
                active: true,
                rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } },
                priceConditionsToCreate: [{
                  field: 'TOTAL_PRICE',
                  operator: 'GREATER_THAN_OR_EQUAL_TO',
                  criteria: { amount: '149.00', currencyCode: 'EUR' }
                }]
              }
            ]
          },
          {
            name: 'Belgique / Luxembourg / Espagne — Chaîne du Froid',
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
                name: '❄️ Livraison Gratuite Chaîne du Froid (149€+)',
                active: true,
                rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } },
                priceConditionsToCreate: [{
                  field: 'TOTAL_PRICE',
                  operator: 'GREATER_THAN_OR_EQUAL_TO',
                  criteria: { amount: '149.00', currencyCode: 'EUR' }
                }]
              }
            ]
          }
        ]
      }]
    }
  });

  const errs = result?.deliveryProfileCreate?.userErrors;
  if (errs?.length > 0) {
    console.log('   ❌ Hata:', errs.map(e => `[${e.field}] ${e.message}`).join('\n        '));
    return null;
  }

  const newProfile = result?.deliveryProfileCreate?.profile;
  if (newProfile) {
    console.log(`   ✅ Profil oluşturuldu: "${newProfile.name}" (${newProfile.id})`);

    // Kalan variantları ekle (250'den fazlaysa)
    if (coldVariantGids.length > 250) {
      console.log(`   ➕ Kalan ${coldVariantGids.length - 250} variant ekleniyor...`);
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
        await delay(500);
      }
      console.log(' tamamlandı');
    }

    return newProfile.id;
  }
  return null;
}

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('🚚 Mondimart Kargo Kurulumu başlıyor...\n');

// Lokasyonları al
console.log('📍 Lokasyonlar yükleniyor...');
const locationIds = await getLocationIds();
if (locationIds.length === 0) {
  console.error('❌ Lokasyon bulunamadı!');
  process.exit(1);
}

await delay(500);

// Ürünleri ve soğuk zincir filtresi
console.log('\n🛍️  Ürünler yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,title,tags,variants');
console.log(`${products.length} ürün yüklendi`);

const coldProducts = products.filter(p => {
  const tags = (p.tags || '').toLowerCase();
  return tags.includes('viande') || tags.includes('halal') || tags.includes('frais');
});
const coldVariantGids = coldProducts
  .flatMap(p => p.variants || [])
  .map(v => `gid://shopify/ProductVariant/${v.id}`);

console.log(`❄️  ${coldProducts.length} soğuk ürün, ${coldVariantGids.length} variant`);

await delay(500);

// Profilleri al
console.log('\n📋 Mevcut kargo profilleri:');
const defaultProfile = await getDefaultProfile();

if (!defaultProfile) {
  console.error('❌ Varsayılan profil bulunamadı!');
  process.exit(1);
}

const locationGroupId = defaultProfile.profileLocationGroups?.[0]?.locationGroup?.id;
console.log(`\nVarsayılan profil ID: ${defaultProfile.id}`);
console.log(`Lokasyon grubu ID: ${locationGroupId || 'bulunamadı'}`);

// Soğuk zincir profili zaten var mı?
const allProfilesData = await gql(`{
  deliveryProfiles(first: 20) {
    edges { node { id name default } }
  }
}`);
const allProfiles = allProfilesData?.deliveryProfiles?.edges?.map(e => e.node) || [];
const existingCold = allProfiles.find(p =>
  p.name.toLowerCase().includes('froid') || p.name.toLowerCase().includes('cold chain')
);

// Standart kargo kurulumu
if (locationGroupId) {
  await setupStandardShipping(defaultProfile.id, locationGroupId);
} else {
  console.log('\n⚠️  Lokasyon grubu ID alınamadı, standart kargo manuel kurulumu gerekli');
}

await delay(1000);

// Soğuk zincir profili
if (existingCold) {
  console.log(`\n❄️  Soğuk zincir profili zaten mevcut: "${existingCold.name}"`);
  console.log('   Shopify Admin > Settings > Shipping and delivery > Shipping profiles üzerinden düzenleyebilirsiniz.');
} else {
  await createColdChainProfile(coldVariantGids, locationIds);
}

console.log('\n\n=== KARGO KURULUM ÖZETI ===');
console.log('✅ Standart (General profile):');
console.log('   🇫🇷 Fransa: 8€ | 149€+ ücretsiz');
console.log('   🌍 AB (BE/LU/ES/DE/NL/IT/PT/AT/CH): 18€ | 149€+ ücretsiz');
console.log('❄️  Soğuk Zincir profili (Chaîne du Froid):');
console.log('   🇫🇷 Fransa: 18€ | 149€+ ücretsiz');
console.log('   🇧🇪🇱🇺🇪🇸 BE/LU/ES: 24€ | 149€+ ücretsiz');
console.log('\n👉 Kontrol: Shopify Admin → Settings → Shipping and delivery');
