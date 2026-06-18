// Mondimart — tüm ürünleri stokta yap
// Kullanım: SHOPIFY_TOKEN='...' node shopify-stock-all.mjs
// Opsiyonel: STOCK_QTY=999 (varsayılan: 100)

const TOKEN    = process.env.SHOPIFY_TOKEN;
const SHOP     = 'pftzey-y0.myshopify.com';
const BASE     = `https://${SHOP}/admin/api/2024-04`;
const H        = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };
const STOCK    = parseInt(process.env.STOCK_QTY || '100', 10);

if (!TOKEN) { console.error('❌ SHOPIFY_TOKEN gerekli'); process.exit(1); }

let reqCount = 0;

async function api(method, path, body) {
  // Shopify rate limit: ~2 req/sn (bucket 40, leak rate 2/sn)
  if (++reqCount % 4 === 0) await new Promise(r => setTimeout(r, 600));
  const opts = { method, headers: H };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(`${BASE}${path}`, opts);
  if (r.status === 429) {
    const wait = parseInt(r.headers.get('Retry-After') || '5') * 1000;
    console.log(`  ⏳ Rate limit, ${wait / 1000}s bekleniyor...`);
    await new Promise(res => setTimeout(res, wait));
    return api(method, path, body);
  }
  return r.json();
}

// Tüm ürünleri sayfalayarak çek
async function getAllProducts() {
  const products = [];
  let url = `${BASE}/products.json?limit=250&fields=id,title,variants`;
  while (url) {
    const r = await fetch(url, { headers: H });
    const data = await r.json();
    products.push(...(data.products || []));
    // Link header ile sonraki sayfaya geç
    const link = r.headers.get('link') || '';
    const next = link.match(/<([^>]+)>;\s*rel="next"/);
    url = next ? next[1] : null;
    console.log(`  📦 ${products.length} ürün çekildi...`);
  }
  return products;
}

// Tüm lokasyonları getir
async function getLocations() {
  const d = await api('GET', '/locations.json?limit=250');
  if (d.errors) {
    console.error('❌ API Hatası:', JSON.stringify(d.errors));
    console.error('   → Token geçersiz veya yetersiz yetki olabilir.');
    process.exit(1);
  }
  return (d.locations || []).filter(l => l.active);
}

async function main() {
  console.log(`\n🏪 Mondimart — Stok Güncelleme`);
  console.log(`📊 Hedef miktar: ${STOCK} adet\n`);

  console.log('📍 Lokasyonlar alınıyor...');
  const locations = await getLocations();
  if (!locations.length) { console.error('❌ Aktif lokasyon bulunamadı (tüm lokasyonlar pasif?)'); process.exit(1); }
  console.log(`✅ ${locations.length} aktif lokasyon:`);
  for (const l of locations) console.log(`   • ${l.name} (${l.id})`);

  console.log('\n📦 Ürünler alınıyor...');
  const products = await getAllProducts();
  console.log(`✅ Toplam ${products.length} ürün\n`);

  let updated = 0, skipped = 0, errors = 0;

  for (const product of products) {
    const variants = product.variants || [];
    for (const v of variants) {
      if (v.inventory_management !== 'shopify') {
        // Shopify takibinde değil → zaten sınırsız satılır
        skipped++;
        continue;
      }
      if (v.inventory_quantity >= STOCK) {
        skipped++;
        continue;
      }
      // Her lokasyona stok yaz
      for (const loc of locations) {
        const r = await api('POST', '/inventory_levels/set.json', {
          location_id: loc.id,
          inventory_item_id: v.inventory_item_id,
          available: STOCK,
        });
        if (r.inventory_level) {
          updated++;
          process.stdout.write(`\r  ✅ ${updated} varyant güncellendi`);
        } else {
          errors++;
          // Lokasyona bağlı değilse önce connect et
          if (r.errors) {
            await api('POST', '/inventory_levels/connect.json', {
              location_id: loc.id,
              inventory_item_id: v.inventory_item_id,
            });
            const r2 = await api('POST', '/inventory_levels/set.json', {
              location_id: loc.id,
              inventory_item_id: v.inventory_item_id,
              available: STOCK,
            });
            if (r2.inventory_level) { updated++; errors--; process.stdout.write(`\r  ✅ ${updated} varyant güncellendi`); }
          }
        }
      }
    }
  }

  console.log(`\n\n${'='.repeat(50)}`);
  console.log(`✅ Tamamlandı!`);
  console.log(`   Güncellenen varyantlar : ${updated}`);
  console.log(`   Atlanan (zaten stokta) : ${skipped}`);
  if (errors > 0) console.log(`   Hata                   : ${errors}`);
  console.log('='.repeat(50) + '\n');
}

main().catch(e => { console.error('❌ Hata:', e.message); process.exit(1); });
