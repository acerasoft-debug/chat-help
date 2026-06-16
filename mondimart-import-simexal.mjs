// ============================================================
// MONDIMART — simexal.fr eksik ürün içe aktarma (DeepSeek destekli)
// Adımlar:
//   1. simexal.fr katalogunu çek (Shopify / WooCommerce / sitemap otomatik)
//   2. Mondimart'taki mevcut ürünlerle karşılaştır → eksikleri bul
//   3. DeepSeek ile: koleksiyon, product_type, SEO başlık/açıklama, tag,
//      piyasa değeri araştır
//   4. Fiyat = piyasa değeri × 1.20 (+%20) ile ürünü oluştur, koleksiyona ekle
//
// Kullanım:
//   SHOPIFY_TOKEN='...' DEEPSEEK_KEY='...' node mondimart-import-simexal.mjs
//   Önizleme (hiçbir şey oluşturmaz):  DRY=1 SHOPIFY_TOKEN=... DEEPSEEK_KEY=... node ...
//   Test için sınırla:                 LIMIT=10 ...
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const DEEPSEEK_KEY  = process.env.DEEPSEEK_KEY;
const DRY   = process.env.DRY === '1';
const LIMIT = process.env.LIMIT ? parseInt(process.env.LIMIT) : 0;
const SOURCE = (process.env.SRC || 'https://simexal.fr').replace(/\/+$/, '');
const MARKUP = 1.20; // piyasa değeri +%20

const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const SH = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

if (!SHOPIFY_TOKEN || !DEEPSEEK_KEY) {
  console.error('❌ Eksik: SHOPIFY_TOKEN ve DEEPSEEK_KEY gerekli');
  process.exit(1);
}
if (/ANAHTAR|YOUR_|PLACEHOLDER|xxx/i.test(DEEPSEEK_KEY)) {
  console.error(`❌ DEEPSEEK_KEY gerçek bir anahtar değil ("${DEEPSEEK_KEY}").
   https://platform.deepseek.com/api_keys adresinden alın (sk-... ile başlar).`);
  process.exit(1);
}
const delay = ms => new Promise(r => setTimeout(r, ms));

async function shopify(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, {
        method, headers: SH, body: body ? JSON.stringify(body) : undefined
      });
      if (method === 'DELETE') return { ok: r.ok, status: r.status };
      return await r.json();
    } catch (e) {
      if (i === 4) throw e;
      await delay(2000 * i);
    }
  }
}

async function fetchAll(path) {
  let url = `${BASE}/${path}`, all = [];
  while (url) {
    const r = await fetch(url, { headers: SH });
    const d = await r.json();
    const key = Object.keys(d)[0];
    all = all.concat(d[key] || []);
    const link = r.headers.get('link') || '';
    url = link.match(/<([^>]+)>;\s*rel="next"/)?.[1] || null;
    await delay(300);
  }
  return all;
}

// ============================================================
// KOLEKSİYON HARİTASI
// ============================================================
const COLLECTIONS = {
  'Alimentation Bébé':686987411781,'Hygiène & Beauté':686919057733,'Hygiène Bébé':687022866757,
  'Couches & Changes':687022833989,'Entretien':686920565061,'Viandes Halal':686918402373,
  'Viandes Conventionnelles':686918467909,'Boucherie':686915518789,'Halal Certifié':687825879365,
  'Charcuterie Halal':687866380613,'Charcuterie':686918598981,'Produits Frais':686918304069,
  'Poissons':686918631749,'Conserves de Poissons':687866609989,'Boissons':686918992197,
  'Jus':686918828357,'Thés':686918730053,'Snacks & Apéro':687825617221,'Pâtes à Tartiner':687825813829,
  'Fruits Secs & Noix':687768404293,'Pâtes':686917550405,'Céréales & Légumineuses':687026569541,
  "Huile d'Olive":686916829509,'Épices':686916337989,'Sauces & Condiments':687825846597,
  'Tomates':686917353797,'Légumes Exotiques':686918074693,'Baklava':686917583173,
  'Petit-Déjeuner':687866347845,'Fromages Frais':686918172997,'Œufs & Laitages':687822635333,
  'Cuisine Turque':686915551557,'Cuisine Italienne':686915223877,'Cuisine Maghrébine':686915420485,
  'Cuisine Asiatique':686915289413,'Cuisine Espagnole':686915486021,'Cuisine Portugaise':686915322181,
  'Gastronomie Française':687026504005,'Karaca':687037448517,'Promotions':687026471237,
  'Sans Gluten':687037481285,'Bio Certifié':687037546821,'Vegan':686915354949,'Épicerie Fine':686917910853,
};
const PRODUCT_TYPES = [
  'Hygiène & Beauté','Alimentation Bébé','Entretien Ménager','Viandes Halal','Charcuterie Halal',
  'Mezze & Spécialités','Boissons','Thés & Cafés','Biscuits & Gâteaux','Confiserie & Bonbons',
  'Chocolats & Pâtes à Tartiner','Douceurs Orientales','Fruits Secs & Noix','Pâtes & Féculents',
  'Huiles & Matières Grasses','Épices & Condiments','Condiments & Sauces','Soupes & Potages',
  'Légumes & Conserves','Poissons','Pâtisserie & Desserts','Cuisine Asiatique','Épicerie Fine',
  'Snacks & Apéro','Petit-Déjeuner',
];

// ============================================================
// 1. SIMEXAL KATALOGUNU ÇEK (otomatik platform tespiti)
// ============================================================
function normTitle(t) {
  return (t || '')
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '') // aksanları kaldır
    .replace(/\b\d+([.,]\d+)?\s?(g|kg|gr|ml|cl|l|cm|mm|pcs|pi[eè]ces?|x\d+)\b/g, ' ') // boyut/adet
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ').trim();
}

async function scrapeShopify() {
  const out = [];
  for (let page = 1; page <= 200; page++) {
    let r;
    try { r = await fetch(`${SOURCE}/products.json?limit=250&page=${page}`, { headers: { 'User-Agent': UA } }); }
    catch { break; }
    if (!r.ok) { if (page === 1) return null; break; }
    let d; try { d = await r.json(); } catch { if (page === 1) return null; break; }
    const prods = d.products || [];
    if (!prods.length) break;
    for (const p of prods) {
      const v = p.variants?.[0] || {};
      out.push({
        title: p.title,
        vendor: p.vendor || '',
        type: p.product_type || '',
        tags: Array.isArray(p.tags) ? p.tags.join(', ') : (p.tags || ''),
        price: parseFloat(v.price) || 0,
        sku: v.sku || '',
        grams: v.grams || 0,
        body_html: p.body_html || '',
        images: (p.images || []).map(i => i.src).filter(Boolean),
        handle: p.handle,
      });
    }
    await delay(400);
  }
  return out.length ? out : null;
}

async function scrapeWoo() {
  const out = [];
  for (let page = 1; page <= 200; page++) {
    let r;
    try { r = await fetch(`${SOURCE}/wp-json/wc/store/v1/products?per_page=100&page=${page}`, { headers: { 'User-Agent': UA } }); }
    catch { break; }
    if (!r.ok) { if (page === 1) return null; break; }
    let d; try { d = await r.json(); } catch { if (page === 1) return null; break; }
    if (!Array.isArray(d) || !d.length) break;
    for (const p of d) {
      out.push({
        title: p.name,
        vendor: '',
        type: (p.categories?.[0]?.name) || '',
        tags: (p.categories || []).map(c => c.name).join(', '),
        price: parseFloat(p.prices?.price || 0) / Math.pow(10, p.prices?.currency_minor_unit ?? 2),
        sku: p.sku || '',
        grams: 0,
        body_html: p.description || '',
        images: (p.images || []).map(i => i.src).filter(Boolean),
        handle: p.slug,
      });
    }
    await delay(400);
  }
  return out.length ? out : null;
}

const SRC_HOST = SOURCE.replace(/^https?:\/\//, '');
console.log(`🌍 ${SRC_HOST} katalogu çekiliyor...`);
let catalog = await scrapeShopify();
if (catalog) console.log(`   ✅ Shopify modu — ${catalog.length} ürün`);
if (!catalog) {
  catalog = await scrapeWoo();
  if (catalog) console.log(`   ✅ WooCommerce modu — ${catalog.length} ürün`);
}
if (!catalog) {
  console.error(`   ❌ ${SRC_HOST} otomatik çekilemedi (Shopify/Woo değil ya da erişim engelli).
   Lütfen şu komutun çıktısını paylaşın, scraper'ı ona göre uyarlayayım:
   curl -sS -D - "${SOURCE}/" -o /dev/null | grep -iE "server|powered|shopify|woocommerce|prestashop"`);
  process.exit(1);
}

// ============================================================
// 2. MONDIMART MEVCUT ÜRÜNLER → eksikleri bul
// ============================================================
console.log(`\n📦 Mondimart mevcut ürünler yükleniyor...`);
const existing = await fetchAll('products.json?limit=250&fields=id,title,variants');
const existTitles = new Set(existing.map(p => normTitle(p.title)));
const existSkus = new Set();
existing.forEach(p => (p.variants || []).forEach(v => v.sku && existSkus.add(v.sku.trim().toLowerCase())));
console.log(`   ${existing.length} mevcut ürün`);

let missing = catalog.filter(p => {
  if (!p.title) return false;
  if (existTitles.has(normTitle(p.title))) return false;
  if (p.sku && existSkus.has(p.sku.trim().toLowerCase())) return false;
  return true;
});
// aynı normalize başlıktan tek kopya
const seen = new Set();
missing = missing.filter(p => { const k = normTitle(p.title); if (seen.has(k)) return false; seen.add(k); return true; });

// Hızlı sayım modu: DeepSeek'siz, sadece toplam eksik + başlık listesi
if (process.env.COUNT === '1') {
  console.log(`\n🔍 ${SRC_HOST}'de ${catalog.length} ürün | sitenizde ${existing.length} ürün`);
  console.log(`📊 TOPLAM EKSİK (sitenizde olmayan): ${missing.length}\n`);
  missing.forEach((p, i) => console.log(`   ${String(i+1).padStart(4)}. ${p.title}${p.price ? `  (${p.price}€)` : ''}`));
  console.log(`\nHepsini eklemek için COUNT'suz, DRY'sız çalıştırın.`);
  process.exit(0);
}

if (LIMIT) missing = missing.slice(0, LIMIT);

console.log(`\n🔍 ${missing.length} eksik ürün bulundu${LIMIT ? ` (LIMIT=${LIMIT})` : ''}.`);
if (!missing.length) { console.log('Eklenecek yeni ürün yok 🎉'); process.exit(0); }

// ============================================================
// 3. DEEPSEEK ZENGİNLEŞTİRME (5'erli batch)
// ============================================================
async function enrich(batch) {
  const list = batch.map((p, i) =>
    `${i+1}. Titre: "${p.title}" | Marque: "${p.vendor}" | Catégorie source: "${p.type}" | Prix source: ${p.price}€`
  ).join('\n');
  const cols = Object.keys(COLLECTIONS).join(', ');
  const types = PRODUCT_TYPES.join(', ');
  const prompt = `Tu es expert e-commerce d'une épicerie du monde (Mondimart). Pour chaque produit, renvoie un objet JSON.

Produits:
${list}

Pour CHAQUE produit fournis:
- "collections": tableau de 1 à 3 noms EXACTEMENT depuis cette liste: ${cols}
- "product_type": EXACTEMENT un parmi: ${types}
- "seo_title": titre SEO accrocheur en français (max 60 caractères)
- "seo_description": meta description vendeuse en français (140-155 caractères)
- "body_html": description produit riche en HTML français (2-3 phrases + <ul> de 3 atouts)
- "tags": tableau de 8 à 12 tags pertinents en français (origine, type, usage, halal/bio si pertinent)
- "clean_title": titre produit PROPRE et bien présenté en français (corrige la casse type "Rakı Yeni 70cl 47°", garde la contenance et le degré d'alcool, RETIRE tout caractère parasite comme ===, (2), (00.0), codes internes)
- "unit_count": nombre d'unités individuelles dans ce lot/pack (ex: "Lot de 6" -> 6, "Pack x3" -> 3, vendu à l'unité -> 1)
- "single_title": titre PROPRE du produit pour UNE SEULE unité, SANS la mention du lot/pack (ex: "Eau minérale 1L" au lieu de "Eau minérale 1L Lot de 6")
- "unit_price_eur": prix de marché moyen RÉALISTE en France pour UNE SEULE unité (nombre, ex: 4.90)

Réponds UNIQUEMENT avec un tableau JSON de ${batch.length} objets, dans le même ordre. Pas de texte autour.`;

  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const r = await fetch('https://api.deepseek.com/chat/completions', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${DEEPSEEK_KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: 'deepseek-chat', messages: [{ role: 'user', content: prompt }], temperature: 0.2, max_tokens: 3000 })
      });
      const d = await r.json();
      let c = d.choices?.[0]?.message?.content?.trim();
      if (!c) {
        const apiErr = d.error?.message || d.message || JSON.stringify(d).slice(0, 200);
        throw new Error(`boş cevap (HTTP ${r.status}: ${apiErr})`);
      }
      c = c.replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
      const parsed = JSON.parse(c);
      const arr = Array.isArray(parsed) ? parsed : (parsed.results || parsed.products || Object.values(parsed)[0]);
      if (Array.isArray(arr)) return arr;
      return [parsed];
    } catch (e) {
      if (attempt === 3) { console.error(`   DeepSeek hata: ${e.message}`); return null; }
      await delay(3000 * attempt);
    }
  }
}

// ============================================================
// 4. ÜRÜN OLUŞTUR + KOLEKSİYONA EKLE
// ============================================================
let created = 0, skipped = 0;
for (let i = 0; i < missing.length; i += 5) {
  const batch = missing.slice(i, i + 5);
  console.log(`\n🧠 DeepSeek analiz: ${i + 1}-${i + batch.length} / ${missing.length}`);
  const results = await enrich(batch);
  if (!results) { skipped += batch.length; continue; }

  for (let j = 0; j < batch.length; j++) {
    const p = batch[j];
    const r = results[j] || {};

    // Paket adedini tespit et (DeepSeek > başlık regex fallback)
    const titleN = (() => {
      const m = (p.title || '').match(/(?:lot|pack|paquet|set|x|×)\s*(?:de\s*)?(\d{1,3})|(\d{1,3})\s*(?:pi[eè]ces?|pcs|unit[eé]s?)/i);
      return m ? parseInt(m[1] || m[2]) : 0;
    })();
    let unitCount = parseInt(r.unit_count) || titleN || 1;
    if (unitCount < 1) unitCount = 1;
    const splitToSingle = unitCount >= 3;          // 3+ ise tek adet olarak listele

    const unitPrice = parseFloat(r.unit_price_eur) || (p.price && unitCount ? p.price / unitCount : p.price) || 0;
    // 3+ → adet başı fiyat; aksi halde paketi olduğu gibi (adet × birim) fiyatla
    const market = splitToSingle ? unitPrice : unitPrice * unitCount;
    const price = (market > 0 ? market : p.price) * MARKUP;
    const priceStr = price > 0 ? price.toFixed(2) : '0.00';

    const baseTitle = r.clean_title || p.title;
    const finalTitle = splitToSingle ? (r.single_title || baseTitle) : baseTitle;
    const cols = (r.collections || []).filter(c => COLLECTIONS[c]);
    const ptype = PRODUCT_TYPES.includes(r.product_type) ? r.product_type : (p.type || 'Épicerie Fine');
    const tags = Array.isArray(r.tags) ? r.tags.join(', ') : (r.tags || p.tags || '');
    const seoTitle = (r.seo_title || p.title).slice(0, 70);
    const seoDesc = (r.seo_description || '').slice(0, 320);
    const body = r.body_html || p.body_html || `<p>${p.title}</p>`;

    const packNote = splitToSingle ? ` [${unitCount}'lü → TEKLİ, adet başı ${unitPrice}€]` : '';
    if (DRY) {
      console.log(`   [ÖNİZLEME] "${finalTitle}"${packNote} → ${priceStr}€ (piyasa ${market.toFixed(2)}€) | ${ptype} | koleksiyon: ${cols.join(', ') || '—'}`);
      created++; continue;
    }

    const productBody = {
      product: {
        title: finalTitle,
        body_html: body,
        vendor: p.vendor || 'Mondimart',
        product_type: ptype,
        tags,
        status: 'active',
        variants: [{
          price: priceStr,
          sku: p.sku || '',
          inventory_management: null,           // stok takipsiz (her zaman satılabilir)
          grams: p.grams || 0,
        }],
        images: p.images.slice(0, 6).map(src => ({ src })),
        metafields: [
          { namespace: 'global', key: 'title_tag', value: seoTitle, type: 'single_line_text_field' },
          { namespace: 'global', key: 'description_tag', value: seoDesc, type: 'single_line_text_field' },
        ],
      }
    };

    const res = await shopify('POST', 'products.json', productBody);
    if (!res?.product?.id) { console.log(`   ❌ "${p.title}" oluşturulamadı: ${JSON.stringify(res?.errors || res).slice(0,160)}`); skipped++; continue; }
    const pid = res.product.id;

    for (const cName of cols) {
      await shopify('POST', 'collects.json', { collect: { product_id: pid, collection_id: COLLECTIONS[cName] } });
      await delay(250);
    }
    console.log(`   ✅ "${finalTitle}"${packNote} → ${priceStr}€ | ${ptype} | ${cols.join(', ') || '—'}`);
    created++;
    await delay(500);
  }
}

console.log(`\n🎉 Bitti. ${DRY ? 'Önizlenen' : 'Oluşturulan'}: ${created} | Atlanan: ${skipped}`);
if (DRY) console.log('Gerçek ekleme için DRY=1 olmadan tekrar çalıştırın.');
