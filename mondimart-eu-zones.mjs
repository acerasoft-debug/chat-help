const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const H = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

async function gql(query, variables = {}) {
  const r = await fetch(`https://${SHOP}/admin/api/2024-04/graphql.json`, {
    method: 'POST', headers: H,
    body: JSON.stringify({ query, variables })
  });
  const d = await r.json();
  if (d.errors) { console.error('Hata:', d.errors.map(e => e.message).join(' | ')); return null; }
  return d.data;
}

const DEFAULT_PROFILE_ID = 'gid://shopify/DeliveryProfile/143568896325';
const LOCATION_GROUP_ID  = 'gid://shopify/DeliveryLocationGroup/154261815621';

// Sadece BE/LU ve AT/CH — ES, PT, DE, NL, IT zaten baska zone'da olabilir
const ZONES = [
  {
    name: 'Belgique / Luxembourg',
    countries: [
      { code: 'BE', includeAllProvinces: true },
      { code: 'LU', includeAllProvinces: true }
    ],
    methodDefinitionsToCreate: [
      { name: '🚚 Livraison Belgique / Luxembourg', active: true, rateDefinition: { price: { amount: '18.00', currencyCode: 'EUR' } } },
      { name: '🎁 Livraison Gratuite BE/LU (dès 149€)', active: true, rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } } }
    ]
  },
  {
    name: 'Autriche / Suisse',
    countries: [
      { code: 'AT', includeAllProvinces: true },
      { code: 'CH', includeAllProvinces: true }
    ],
    methodDefinitionsToCreate: [
      { name: '🚚 Livraison Europe', active: true, rateDefinition: { price: { amount: '18.00', currencyCode: 'EUR' } } },
      { name: '🎁 Livraison Gratuite Europe (dès 149€)', active: true, rateDefinition: { price: { amount: '0.00', currencyCode: 'EUR' } } }
    ]
  }
];

console.log('🌍 Avrupa kargo bölgeleri ekleniyor (BE/LU, AT/CH)...\n');

const result = await gql(`
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
      zonesToCreate: ZONES
    }]
  }
});

const errs = result?.deliveryProfileUpdate?.userErrors;
if (errs?.length) {
  console.log('⚠️  Hata:', errs.map(e => `${e.field}: ${e.message}`).join('\n'));
} else if (result?.deliveryProfileUpdate?.profile) {
  console.log('✅ Bölgeler eklendi!');
  console.log('   🇧🇪🇱🇺 Belçika/Lüksemburg: 18€');
  console.log('   🇦🇹🇨🇭 Avusturya/İsviçre: 18€');
}
