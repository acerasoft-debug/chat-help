// Shopify koleksiyon & ürün audit
// Kullanım: SHOPIFY_TOKEN='...' node shopify-audit.mjs

const TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP  = 'pftzey-y0.myshopify.com';
const BASE  = `https://${SHOP}/admin/api/2024-04`;
const H     = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

if (!TOKEN) { console.error('❌ SHOPIFY_TOKEN gerekli'); process.exit(1); }

async function get(url) {
  const r = await fetch(url, { headers: H });
  return r.json();
}

async function getCollections() {
  const customs = await get(`${BASE}/custom_collections.json?limit=250&fields=id,title,handle`);
  const smarts  = await get(`${BASE}/smart_collections.json?limit=250&fields=id,title,handle`);
  return [
    ...(customs.custom_collections || []).map(c => ({ ...c, type: 'custom' })),
    ...(smarts.smart_collections   || []).map(c => ({ ...c, type: 'smart'  })),
  ].sort((a, b) => a.title.localeCompare(b.title));
}

async function getProducts(collectionId) {
  const d = await get(`${BASE}/collections/${collectionId}/products.json?limit=250&fields=id,title,product_type,tags,vendor`);
  return d.products || [];
}

const collections = await getCollections();
console.log(`\n🏪 Mondimart — ${collections.length} koleksiyon\n`);
console.log('='.repeat(60));

for (const col of collections) {
  const products = await getProducts(col.id);
  const icon = col.type === 'smart' ? '🤖' : '📦';
  console.log(`\n${icon} ${col.title}  [${col.handle}]  (${products.length} ürün)`);
  for (const p of products) {
    const type = p.product_type ? `  type:${p.product_type}` : '';
    console.log(`   • ${p.title}${type}`);
  }
}

console.log('\n' + '='.repeat(60));
console.log('✅ Audit tamamlandı\n');
