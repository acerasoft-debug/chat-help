// Mondimart — TEŞHİS (sadece okur, hiçbir şey değiştirmez)
// Ürünlerin satın alınabilirliği + kargo (Versand) profilleri durumu.
// Kullanım: SHOPIFY_TOKEN='shpat_...' node shopify-diag.mjs

const TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP  = 'pftzey-y0.myshopify.com';
const VER   = '2024-04';
const BASE  = `https://${SHOP}/admin/api/${VER}`;
const H     = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };
if (!TOKEN) { console.error('❌ SHOPIFY_TOKEN gerekli'); process.exit(1); }

async function getAllProducts() {
  const out = [];
  let url = `${BASE}/products.json?limit=250&fields=id,title,status,published_at,variants`;
  while (url) {
    const r = await fetch(url, { headers: H });
    if (r.status === 429) { await new Promise(s=>setTimeout(s,3000)); continue; }
    const data = await r.json();
    out.push(...(data.products || []));
    const link = r.headers.get('link') || '';
    const m = link.match(/<([^>]+)>;\s*rel="next"/);
    url = m ? m[1] : null;
    if (url) await new Promise(s=>setTimeout(s,400));
  }
  return out;
}

async function gql(query) {
  const r = await fetch(`${BASE}/graphql.json`, { method:'POST', headers:H, body: JSON.stringify({ query }) });
  return r.json();
}

(async () => {
  console.log('Mondimart — Teşhis\n==================\n');

  // ── Ürünler / satın alınabilirlik ──
  const prods = await getAllProducts();
  let variants = 0, draft=0, active=0, archived=0, notPublished=0;
  let noPrice=0, noShipping=0, notTracked=0, policyDeny=0, policyContinue=0, zeroStock=0;
  for (const p of prods) {
    if (p.status==='draft') draft++; else if (p.status==='active') active++; else archived++;
    if (!p.published_at) notPublished++;
    for (const v of (p.variants||[])) {
      variants++;
      if (!v.price || parseFloat(v.price)===0) noPrice++;
      if (v.requires_shipping===false) noShipping++;
      if (!v.inventory_management) notTracked++;
      if (v.inventory_policy==='deny') policyDeny++; else if (v.inventory_policy==='continue') policyContinue++;
      if ((v.inventory_quantity||0)<=0) zeroStock++;
    }
  }
  console.log(`[Ürünler]  toplam: ${prods.length}  varyant: ${variants}`);
  console.log(`  status -> active: ${active} | draft: ${draft} | archived: ${archived}`);
  console.log(`  yayında DEĞİL (published_at boş): ${notPublished}`);
  console.log(`\n[Satın alınabilirlik engelleri]`);
  console.log(`  fiyatı 0/yok varyant     : ${noPrice}   ${noPrice?'⚠️ (fiyatsız satılmaz)':'✓'}`);
  console.log(`  requires_shipping=false  : ${noShipping}`);
  console.log(`  stok takip edilmiyor     : ${notTracked}`);
  console.log(`  inventory_policy=deny    : ${policyDeny}   (stok 0 ise satılmaz)`);
  console.log(`  inventory_policy=continue: ${policyContinue} (stok 0'da bile satılır)`);
  console.log(`  stok <= 0 varyant        : ${zeroStock}   ${zeroStock?'⚠️':'✓'}`);

  // ── Kargo (Versand) profilleri ──
  console.log(`\n[Kargo / Versand profilleri]`);
  try {
    const q = `{
      deliveryProfiles(first: 25) {
        edges { node {
          id name default
          profileLocationGroups {
            locationGroupZones(first: 50) {
              edges { node {
                zone { name }
                methodDefinitionCounts { rateDefinitionCount participantDefinitionCount }
              } }
            }
          }
        } }
      }
    }`;
    const res = await gql(q);
    if (res.errors) { console.log('  GraphQL hata:', JSON.stringify(res.errors).slice(0,300)); }
    const profs = res?.data?.deliveryProfiles?.edges || [];
    if (!profs.length) console.log('  (profil bulunamadı)');
    for (const e of profs) {
      const n = e.node;
      console.log(`\n  • Profil: "${n.name}"${n.default?' (VARSAYILAN)':''}`);
      let zoneCount=0, withRates=0;
      for (const lg of (n.profileLocationGroups||[])) {
        for (const z of (lg.locationGroupZones?.edges||[])) {
          zoneCount++;
          const c = z.node.methodDefinitionCounts || {};
          const rates = (c.rateDefinitionCount||0) + (c.participantDefinitionCount||0);
          if (rates>0) withRates++;
          console.log(`      Zone "${z.node.zone?.name}" -> ${rates} kargo oran(ı) ${rates?'✓':'⚠️ ORAN YOK (checkout bloke!)'}`);
        }
      }
      if (!zoneCount) console.log('      ⚠️ Bu profilde ZONE/ülke yok -> teslimat tanımsız!');
    }
  } catch (err) {
    console.log('  Kargo profili okunamadı:', String(err.message||err));
  }

  console.log('\n==================');
  console.log('Bu raporu gönder -> ürünleri satın alınabilir + doğru kargoya bağlayan düzeltme script\'ini yazacağım.');
})();

