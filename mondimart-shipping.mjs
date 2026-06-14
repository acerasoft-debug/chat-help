// ============================================================
// MONDIMART - Kargo Kurulumu (REST API)
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-shipping.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const H = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN) { console.error('SHOPIFY_TOKEN gerekli'); process.exit(1); }

const delay = ms => new Promise(r => setTimeout(r, ms));

async function gql(query, variables = {}) {
  const r = await fetch(`https://${SHOP}/admin/api/2024-04/graphql.json`, {
    method: 'POST', headers: H,
    body: JSON.stringify({ query, variables })
  });
  const d = await r.json();
  if (d.errors) throw new Error(d.errors.map(e => e.message).join(' | '));
  return d.data;
}

// ============================================================
// Schema keşfi — hangi field'lar var?
// ============================================================
async function discoverSchema() {
  console.log('\n🔍 DeliveryProfileLocationGroupInput field\'ları:');
  const r1 = await gql(`{
    __type(name: "DeliveryProfileLocationGroupInput") {
      inputFields { name type { name kind ofType { name kind } } }
    }
  }`);
  (r1?.__type?.inputFields || []).forEach(f =>
    console.log(`   - ${f.name}: ${f.type?.name || f.type?.ofType?.name || f.type?.kind}`)
  );

  console.log('\n🔍 DeliveryLocationGroupZoneInput field\'ları:');
  const r2 = await gql(`{
    __type(name: "DeliveryLocationGroupZoneInput") {
      inputFields { name type { name kind ofType { name kind } } }
    }
  }`);
  (r2?.__type?.inputFields || []).forEach(f =>
    console.log(`   - ${f.name}: ${f.type?.name || f.type?.ofType?.name || f.type?.kind}`)
  );

  console.log('\n🔍 DeliveryMethodDefinitionInput field\'ları:');
  const r3 = await gql(`{
    __type(name: "DeliveryMethodDefinitionInput") {
      inputFields { name type { name kind ofType { name kind } } }
    }
  }`);
  (r3?.__type?.inputFields || []).forEach(f =>
    console.log(`   - ${f.name}: ${f.type?.name || f.type?.ofType?.name || f.type?.kind}`)
  );

  console.log('\n🔍 DeliveryConditionInput field\'ları:');
  const r4 = await gql(`{
    __type(name: "DeliveryConditionInput") {
      inputFields { name type { name kind ofType { name kind } } }
    }
  }`);
  (r4?.__type?.inputFields || []).forEach(f =>
    console.log(`   - ${f.name}: ${f.type?.name || f.type?.ofType?.name || f.type?.kind}`)
  );

  console.log('\n🔍 DeliveryRateDefinitionInput field\'ları:');
  const r5 = await gql(`{
    __type(name: "DeliveryRateDefinitionInput") {
      inputFields { name type { name kind ofType { name kind } } }
    }
  }`);
  (r5?.__type?.inputFields || []).forEach(f =>
    console.log(`   - ${f.name}: ${f.type?.name || f.type?.ofType?.name || f.type?.kind}`)
  );

  console.log('\n🔍 DeliveryProfileInput field\'ları:');
  const r6 = await gql(`{
    __type(name: "DeliveryProfileInput") {
      inputFields { name type { name kind ofType { name kind } } }
    }
  }`);
  (r6?.__type?.inputFields || []).forEach(f =>
    console.log(`   - ${f.name}: ${f.type?.name || f.type?.ofType?.name || f.type?.kind}`)
  );

  console.log('\n🔍 Mevcut profil + location group bilgileri:');
  const r7 = await gql(`{
    deliveryProfiles(first: 5) {
      edges {
        node {
          id name default
          profileLocationGroups {
            locationGroup {
              id
              locations(first: 5) {
                edges { node { id name } }
              }
            }
          }
        }
      }
    }
  }`);
  const profiles = r7?.deliveryProfiles?.edges || [];
  profiles.forEach(e => {
    const p = e.node;
    console.log(`\n   Profil: "${p.name}" (${p.default ? 'VARSAYILAN' : 'özel'})`);
    console.log(`   ID: ${p.id}`);
    (p.profileLocationGroups || []).forEach(g => {
      console.log(`   LocationGroup ID: ${g.locationGroup?.id}`);
      (g.locationGroup?.locations?.edges || []).forEach(le =>
        console.log(`     Lokasyon: ${le.node.name} → ${le.node.id}`)
      );
    });
  });
}

console.log('🔍 Shopify GraphQL şeması keşfediliyor...\n');
await discoverSchema();
console.log('\n\n✅ Şema bilgisi alındı. Yukarıdaki çıktıyı paylaş — doğru kargo scriptini yazacağım.');
