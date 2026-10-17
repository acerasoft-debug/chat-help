// ============================================================
// MONDIMART - Toplu paket başlıklarını tek ürüne dönüştür
// "Biscuits Ülker 15x200g" → "Biscuits Ülker 200g"
// Kullanım: SHOPIFY_TOKEN='...' DEEPSEEK_KEY='...' node mondimart-singletitle.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const DEEPSEEK_KEY = process.env.DEEPSEEK_KEY;
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const SH = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN || !DEEPSEEK_KEY) {
  console.error('Eksik: SHOPIFY_TOKEN ve DEEPSEEK_KEY gerekli');
  process.exit(1);
}

async function shopify(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, {
        method, headers: SH,
        body: body ? JSON.stringify(body) : undefined
      });
      return await r.json();
    } catch(e) {
      if (i === 4) throw e;
      await new Promise(r => setTimeout(r, 2000 * i));
    }
  }
}

async function fetchAll(path) {
  let url = `${BASE}/${path}`;
  let all = [];
  while (url) {
    const r = await fetch(url, { headers: SH });
    const d = await r.json();
    const key = Object.keys(d)[0];
    all = all.concat(d[key] || []);
    const link = r.headers.get('link') || '';
    url = link.match(/<([^>]+)>;\s*rel="next"/)?.[1] || null;
    await new Promise(r => setTimeout(r, 300));
  }
  return all;
}

const delay = ms => new Promise(r => setTimeout(r, ms));

// Başlıkta toplu paket ifadesi var mı kontrol et
// Eşleşen desenler: 15x, 10x, 5x, 12x, 24x, 6x, 4x, 3x, 2x
// Ayrıca: "Lot de 10", "Pack 6", "x24", "24 pièces" vb.
function hasBulkIndicator(title) {
  return /\b(\d+)\s*[xX×]\s*\d|\b[xX×]\s*(\d+)\b|\bLot\s+de\s+\d|\bPack\s+\d|\bx\s*\d{2}|\b\d+\s*pièces|\b\d+\s*st\b|\b\d+\s*stuks/i.test(title);
}

// DeepSeek'e toplu başlıkları gönder, tek ürün başlığı + açıklama döndür
async function cleanWithDeepSeek(products) {
  const list = products.map((p, i) =>
    `${i+1}. Titre actuel: "${p.title}" | Description: "${(p.body_html || '').replace(/<[^>]+>/g, '').substring(0, 200)}"`
  ).join('\n');

  const prompt = `Tu es expert en e-commerce d'épicerie mondiale. Ces produits ont des titres qui indiquent des packs/lots (ex: "15x200g", "10x", "Pack 6", "Lot de 12", "x24").

Pour chaque produit:
1. Crée un nouveau titre pour UNE SEULE unité (supprime le multiplicateur, garde la marque, le produit, le grammage/volume d'une unité)
2. Crée une courte description HTML en français (2-3 phrases) décrivant le produit individuel — sans mentionner de lot ou pack
3. Met le prix unitaire réaliste France/Belgique pour UNE unité

EXEMPLES:
- "Biscuits Ülker 15x200g" → titre: "Biscuits Ülker 200g"
- "Chips Eti 12x100g Paprika" → titre: "Chips Eti 100g Paprika"
- "Jus Cappy Pack 6x1L Orange" → titre: "Jus Cappy 1L Orange"
- "Bebeto Bonbons Halal 24x80g" → titre: "Bebeto Bonbons Halal 80g"
- "Café Kurukahveci x12 500g" → titre: "Café Kurukahveci 500g"

Produits:
${list}

Réponds UNIQUEMENT en JSON (array), sans texte:
[
  {
    "index": 1,
    "new_title": "...",
    "description_html": "<p>...</p>",
    "unit_price": 2.49
  }
]`;

  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const r = await fetch('https://api.deepseek.com/chat/completions', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${DEEPSEEK_KEY}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          model: 'deepseek-chat',
          messages: [{ role: 'user', content: prompt }],
          temperature: 0.1,
          max_tokens: 3000
        })
      });

      const d = await r.json();
      if (!d.choices?.[0]?.message?.content) throw new Error('No content');

      let content = d.choices[0].message.content.trim();
      content = content.replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
      const parsed = JSON.parse(content);
      if (Array.isArray(parsed)) return parsed;
      const arr = parsed.results || parsed.products || Object.values(parsed)[0];
      if (Array.isArray(arr)) return arr;
      return [parsed];
    } catch(e) {
      if (attempt === 3) {
        console.error(`DeepSeek hata: ${e.message}`);
        return null;
      }
      await delay(3000 * attempt);
    }
  }
}

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('🚀 Mondimart - Toplu paket başlıkları temizleniyor...\n');

console.log('Ürünler yükleniyor...');
const allProducts = await fetchAll('products.json?limit=250&fields=id,title,product_type,tags,variants,body_html');
console.log(`${allProducts.length} ürün yüklendi\n`);

// Toplu paket ifadesi içeren ürünleri filtrele
const toProcess = allProducts.filter(p => hasBulkIndicator(p.title));
console.log(`${toProcess.length} ürün toplu paket başlığı içeriyor\n`);

if (toProcess.length === 0) {
  console.log('İşlenecek ürün yok. Çıkılıyor.');
  process.exit(0);
}

// Önizleme
console.log('Örnek başlıklar:');
toProcess.slice(0, 10).forEach(p => console.log(`  - ${p.title}`));
if (toProcess.length > 10) console.log(`  ... ve ${toProcess.length - 10} tane daha\n`);
else console.log();

let updated = 0, errors = 0;
const BATCH = 5;

for (let i = 0; i < toProcess.length; i += BATCH) {
  const batch = toProcess.slice(i, i + BATCH);

  process.stdout.write(`[${i+1}-${Math.min(i+BATCH, toProcess.length)}/${toProcess.length}] İşleniyor...`);

  const results = await cleanWithDeepSeek(batch);

  if (!results) {
    errors += batch.length;
    process.stdout.write(' ❌ hata\n');
    await delay(5000);
    continue;
  }

  for (let j = 0; j < batch.length; j++) {
    const p = batch[j];
    const result = results.find(r => r.index === j + 1) || results[j];
    if (!result) continue;

    // Başlık değişmemişse atla
    if (!result.new_title || result.new_title.trim() === p.title.trim()) {
      process.stdout.write('=');
      continue;
    }

    const updateBody = {
      product: {
        id: p.id,
        title: result.new_title,
      }
    };

    // Açıklama varsa güncelle
    if (result.description_html) {
      updateBody.product.body_html = result.description_html;
    }

    // Fiyat güncelle (sadece 0.00 ise)
    const curPrice = parseFloat(p.variants?.[0]?.price || 0);
    if (result.unit_price && curPrice === 0 && result.unit_price > 0) {
      // Variant güncellemesi ayrı API çağrısı gerektirir, aşağıda yapılacak
    }

    const res = await shopify('PUT', `products/${p.id}.json`, updateBody);
    if (res.product) {
      process.stdout.write('✓');
      updated++;

      // Fiyat güncellemesi
      if (result.unit_price && curPrice === 0 && result.unit_price > 0) {
        const variantId = p.variants?.[0]?.id;
        if (variantId) {
          await shopify('PUT', `variants/${variantId}.json`, {
            variant: { id: variantId, price: result.unit_price.toFixed(2) }
          });
          process.stdout.write('€');
          await delay(200);
        }
      }
    } else {
      errors++;
      process.stdout.write('!');
    }

    await delay(300);
  }

  process.stdout.write(' ✅\n');
  await delay(1000);
}

console.log('\n\n=== SONUÇ ===');
console.log(`✅ Güncellenen: ${updated} | Hata: ${errors}`);
console.log('\nNot: Başlıklar artık tek ürün olarak gösterilecek.');
