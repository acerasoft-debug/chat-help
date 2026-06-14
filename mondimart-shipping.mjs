// ============================================================
// MONDIMART - Kargo Profilleri Kurulumu (GraphQL)
// - Standart: Fransa 8€, AB 18€, 149€ üstü ücretsiz
// - Soğuk Zincir (viande/halal): Fransa 18€, BE/LU/ES 24€, 149€ üstü ücretsiz
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-shipping.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const GQL = `https://${SHOP}/admin/api/2024-04/graphql.json`;
const REST = `https://${SHOP}/admin/api/2024-04`;
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
        console.error('GraphQL hata:', JSON.stringify(d.errors, null, 2));
        return null;
      }
      return d.data;
    } catch(e) {
      if (i === 4) throw e;
      await new Promise(r => setTimeout(r, 2000 * i));
    }
  }
}

async function rest(method, path, body) {
  const r = await fetch(`${REST}/${path}`, {
    method, headers: H,
    body: body ? JSON.stringify(body) : undefined
  });
  return r.json();
}

// ============================================================
// ADIM 1: Soğuk zincir ürünlerini bul (halal veya viande tag'i olanlar)
// ============================================================
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

// ============================================================
// ADIM 2: Mevcut profilleri kontrol et
// ============================================================
async function getExistingProfiles() {
  const data = await gql(`{
    deliveryProfiles(first: 20) {
      edges {
        node {
          id
          name
          default
        }
      }
    }
  }`);
  return data?.deliveryProfiles?.edges?.map(e => e.node) || [];
}

// ============================================================
// ADIM 3: Varsayılan profil için kargo bölgelerini ayarla
// (Standart kargo: Fransa 8€, AB 18€, 149€ üstü ücretsiz)
// ============================================================
async function setupDefaultProfile(profileId) {
  console.log('\n📦 Standart kargo profili ayarlanıyor...');

  // Önce mevcut lokasyonları al
  const locData = await gql(`{
    deliveryProfile(id: "${profileId}") {
      profileLocationGroups {
        locationGroup {
          id
        }
        profileLocations {
          location {
            id
            name
          }
        }
      }
    }
  }`);

  const locationGroups = locData?.deliveryProfile?.profileLocationGroups || [];
  if (locationGroups.length === 0) {
    console.log('  ⚠️  Lokasyon grubu bulunamadı, manuel kurulum gerekli');
    return;
  }

  const locationGroupId = locationGroups[0].locationGroup.id;

  // Kargo bölgeleri tanımla
  const zones = [
    {
      name: 'France',
      countries: [{ code: 'FR', includeAllProvinces: true }],
      methodDefinitions: [
        {
          name: 'Livraison Standard',
          active: true,
          rateDefinition: {
            price: { amount: '8.00', currencyCode: 'EUR' }
          },
          weightConditionsToCreate: [],
          priceConditionsToCreate: []
        },
        {
          name: 'Livraison Gratuite (149€+)',
          active: true,
          rateDefinition: {
            price: { amount: '0.00', currencyCode: 'EUR' }
          },
          weightConditionsToCreate: [],
          priceConditionsToCreate: [{ criteria: { greaterThanOrEqualTo: { amount: '149.00', currencyCode: 'EUR' } } }]
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
        { code: 'CH', includeAllProvinces: true },
      ],
      methodDefinitions: [
        {
          name: 'Livraison Europe',
          active: true,
          rateDefinition: {
            price: { amount: '18.00', currencyCode: 'EUR' }
          },
          weightConditionsToCreate: [],
          priceConditionsToCreate: []
        },
        {
          name: 'Livraison Gratuite (149€+)',
          active: true,
          rateDefinition: {
            price: { amount: '0.00', currencyCode: 'EUR' }
          },
          weightConditionsToCreate: [],
          priceConditionsToCreate: [{ criteria: { greaterThanOrEqualTo: { amount: '149.00', currencyCode: 'EUR' } } }]
        }
      ]
    }
  ];

  for (const zone of zones) {
    const mutation = `
      mutation deliveryProfileUpdate($id: ID!, $profile: DeliveryProfileInput!) {
        deliveryProfileUpdate(id: $id, profile: $profile) {
          profile { id name }
          userErrors { field message }
        }
      }
    `;

    const result = await gql(mutation, {
      id: profileId,
      profile: {
        locationGroupsToCreate: [{
          locationGroup: { id: locationGroupId },
          zonesToCreate: [zone]
        }]
      }
    });

    if (result?.deliveryProfileUpdate?.userErrors?.length > 0) {
      console.log(`  ⚠️  ${zone.name}: ${result.deliveryProfileUpdate.userErrors.map(e => e.message).join(', ')}`);
    } else {
      console.log(`  ✅ ${zone.name} bölgesi ayarlandı`);
    }
    await delay(500);
  }
}

// ============================================================
// ADIM 4: Soğuk zincir profili oluştur
// ============================================================
async function createColdChainProfile(coldProductGids) {
  console.log('\n❄️  Soğuk Zincir kargo profili oluşturuluyor...');
  console.log(`   ${coldProductGids.length} soğuk ürün bu profile atanacak`);

  // Önce lokasyon gruplarını al
  const locData = await gql(`{
    locations(first: 10) {
      edges {
        node {
          id
          name
        }
      }
    }
  }`);

  const locationIds = locData?.locations?.edges?.map(e => e.node.id) || [];
  if (locationIds.length === 0) {
    console.log('  ⚠️  Lokasyon bulunamadı');
    return null;
  }

  const mutation = `
    mutation deliveryProfileCreate($profile: DeliveryProfileInput!) {
      deliveryProfileCreate(profile: $profile) {
        profile {
          id
          name
        }
        userErrors {
          field
          message
        }
      }
    }
  `;

  const profileInput = {
    name: 'Livraison Chaîne du Froid ❄️',
    variantsToAssociate: coldProductGids,
    locationGroupsToCreate: [
      {
        locationGroup: { locationIds },
        zonesToCreate: [
          {
            name: 'France — Chaîne du Froid',
            countries: [{ code: 'FR', includeAllProvinces: true }],
            methodDefinitions: [
              {
                name: '❄️ Livraison Chaîne du Froid',
                active: true,
                rateDefinition: {
                  price: { amount: '18.00', currencyCode: 'EUR' }
                }
              },
              {
                name: '❄️ Livraison Gratuite Chaîne du Froid (149€+)',
                active: true,
                rateDefinition: {
                  price: { amount: '0.00', currencyCode: 'EUR' }
                },
                priceConditionsToCreate: [
                  { criteria: { greaterThanOrEqualTo: { amount: '149.00', currencyCode: 'EUR' } } }
                ]
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
            methodDefinitions: [
              {
                name: '❄️ Livraison Chaîne du Froid BE/LU/ES',
                active: true,
                rateDefinition: {
                  price: { amount: '24.00', currencyCode: 'EUR' }
                }
              },
              {
                name: '❄️ Livraison Gratuite Chaîne du Froid (149€+)',
                active: true,
                rateDefinition: {
                  price: { amount: '0.00', currencyCode: 'EUR' }
                },
                priceConditionsToCreate: [
                  { criteria: { greaterThanOrEqualTo: { amount: '149.00', currencyCode: 'EUR' } } }
                ]
              }
            ]
          }
        ]
      }
    ]
  };

  const result = await gql(mutation, { profile: profileInput });

  if (result?.deliveryProfileCreate?.userErrors?.length > 0) {
    console.log('  ❌ Hata:', result.deliveryProfileCreate.userErrors.map(e => e.message).join('\n  '));
    return null;
  }

  const newProfile = result?.deliveryProfileCreate?.profile;
  if (newProfile) {
    console.log(`  ✅ Soğuk zincir profili oluşturuldu: ${newProfile.name} (${newProfile.id})`);
    return newProfile.id;
  }
  return null;
}

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('🚚 Mondimart Kargo Kurulumu başlıyor...\n');

// Ürünleri yükle
console.log('Ürünler yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,title,tags,product_type,variants');
console.log(`${products.length} ürün yüklendi`);

// Soğuk zincir ürünlerini belirle
const coldProducts = products.filter(p => {
  const tags = (p.tags || '').toLowerCase();
  return tags.includes('viande') || tags.includes('halal') || tags.includes('frais');
});
console.log(`❄️  ${coldProducts.length} soğuk zincir ürünü tespit edildi`);

// Variant GID'leri topla
const coldVariantGids = coldProducts
  .flatMap(p => p.variants || [])
  .map(v => `gid://shopify/ProductVariant/${v.id}`)
  .filter(Boolean);

console.log(`   ${coldVariantGids.length} variant soğuk zincir profiline atanacak\n`);

// Mevcut profilleri kontrol et
console.log('Mevcut kargo profilleri kontrol ediliyor...');
const profiles = await getExistingProfiles();
console.log('Mevcut profiller:');
profiles.forEach(p => console.log(`  - ${p.name} (${p.default ? 'VARSAYILAN' : 'özel'}) → ${p.id}`));

const defaultProfile = profiles.find(p => p.default);

// Soğuk zincir profili zaten var mı?
const existingCold = profiles.find(p => p.name.toLowerCase().includes('froid') || p.name.toLowerCase().includes('cold'));

// Standart profili güncelle
if (defaultProfile) {
  await setupDefaultProfile(defaultProfile.id);
} else {
  console.log('\n⚠️  Varsayılan profil bulunamadı!');
}

await delay(1000);

// Soğuk zincir profilini oluştur (yoksa)
if (existingCold) {
  console.log(`\n❄️  Soğuk zincir profili zaten mevcut: ${existingCold.name}`);
  console.log('   Güncelleme yapmak için Shopify Admin > Shipping > Shipping profiles sayfasını kullanın.');
} else {
  if (coldVariantGids.length > 0) {
    await createColdChainProfile(coldVariantGids);
  } else {
    console.log('\n⚠️  Soğuk zincir ürünü bulunamadı (halal/viande tag eksik olabilir)');
  }
}

console.log('\n\n=== KARGO KURULUM ÖZETI ===');
console.log('✅ Standart kargo (varsayılan profil):');
console.log('   🇫🇷 Fransa: 8€ | 149€+ ücretsiz');
console.log('   🌍 Avrupa: 18€ | 149€+ ücretsiz');
console.log('❄️  Soğuk Zincir profili:');
console.log('   🇫🇷 Fransa: 18€ | 149€+ ücretsiz');
console.log('   🇧🇪🇱🇺🇪🇸 BE/LU/ES: 24€ | 149€+ ücretsiz');
console.log('\nKontrol: Shopify Admin → Settings → Shipping and delivery');
