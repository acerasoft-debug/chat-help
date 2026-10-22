// ============================================================
// MONDIMART - Kapsamlı AI Güncellemesi (DeepSeek)
// - Toplu paket başlıklarını tek ürüne dönüştür (15x200g → 200g)
// - Piyasa fiyatı araştır ve yaz (Karaca hariç → +25%)
// - Tag, koleksiyon ve product_type düzelt
// Kullanım: SHOPIFY_TOKEN='...' DEEPSEEK_KEY='...' node mondimart-full.mjs
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
      if (method === 'DELETE') return { ok: r.ok, status: r.status };
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

function hasBulkIndicator(title) {
  return /(\d+)\s*[xX×]\s*\d|\bLot\s+de\s+\d|\bPack\s+\d|\bx\s*\d{2,}|\b\d+\s*pièces|\b\d+\s*st\b|\b\d+\s*stuks/i.test(title);
}

const PRODUCT_TYPES = [
  'Hygiène & Beauté', 'Alimentation Bébé', 'Entretien Ménager',
  'Viandes Halal', "Viande d'Agneau", 'Viande de Veau', 'Volaille Halal',
  'Charcuterie Halal', 'Mezze & Spécialités', 'Boissons', 'Boissons Énergisantes',
  'Boissons Laitières', 'Thés & Cafés', 'Biscuits & Gâteaux', 'Biscuits Turcs',
  'Biscuits Italiens', 'Confiserie & Bonbons', 'Chocolats & Pâtes à Tartiner',
  'Douceurs Orientales', 'Fruits Secs & Noix', 'Pâtes & Féculents',
  'Huiles & Matières Grasses', 'Épices & Condiments', 'Condiments & Sauces',
  'Soupes & Potages', 'Légumes & Conserves', 'Poissons', 'Conserves de Poissons',
  'Pâtisserie & Desserts', 'Emballage Horeca', 'Aluminium & Film',
  'Emballage Soupe', 'Emballage Boissons', 'Serviettes & Couverts',
  'Cuisine Asiatique', 'Karaca', 'Vaisselle', 'Épicerie Fine',
  'Snacks & Apéro', 'Petit-Déjeuner', 'Céréales & Légumineuses',
  'Fromages Frais', 'Poissons Frais',
];

// ============================================================
// DEEPSEEK TOPLU ANALİZ
// ============================================================
async function analyzeWithDeepSeek(products, collectionNames) {
  const isKaraca = p => p.product_type === 'Karaca' || p.product_type === 'Vaisselle' ||
    p.title.toLowerCase().includes('karaca');

  const productList = products.map((p, i) => {
    const bulk = hasBulkIndicator(p.title);
    const karaca = isKaraca(p);
    const curPrice = parseFloat(p.variants?.[0]?.price || 0);
    return `${i+1}. Titre: "${p.title}"
   Tags: "${p.tags}" | Type: "${p.product_type}" | Prix actuel: ${curPrice}€
   Est un lot/pack: ${bulk ? 'OUI' : 'non'} | Est Karaca: ${karaca ? 'OUI (ne pas changer prix)' : 'non'}
   Description (extrait): "${(p.body_html || '').replace(/<[^>]+>/g, '').substring(0, 150)}"`;
  }).join('\n\n');

  const colList = collectionNames.join(', ');
  const typeList = PRODUCT_TYPES.join(', ');

  const prompt = `Tu es expert en épicerie mondiale (produits turcs, maghrébins, italiens, asiatiques, halal) et en pricing pour la France et la Belgique.

Analyse ces ${products.length} produits et pour chacun:

1. **new_title**: Si le titre contient un multiplicateur de lot (ex: "15x200g", "Pack 12", "x24", "Lot de 6"), crée un nouveau titre pour UNE SEULE unité en supprimant le multiplicateur. Sinon, garde null.
   Exemples: "Biscuits Ülker 15x200g" → "Biscuits Ülker 200g" | "Bebeto 24x80g" → "Bebeto 80g"

2. **description_html**: Si new_title n'est pas null, génère une description HTML en français (2-3 phrases) pour l'unité individuelle, sans mention de lot/pack.

3. **product_type**: Choisis EXACTEMENT parmi: ${typeList}

4. **collections**: Choisis les collections pertinentes parmi: ${colList}

5. **tags_add**: Tags à ajouter (minuscules, en rapport avec le produit)

6. **tags_remove**: Tags incorrects à supprimer

7. **price**: Prix de vente réaliste pour UNE unité en France/Belgique (épicerie mondiale, marge 35-40% sur grossiste).
   - Si "Est Karaca: OUI" → mettre null (Karaca géré séparément)
   - Si prix actuel > 0 ET n'est pas Karaca → mettre null (ne pas changer)
   - Si prix actuel = 0 → donner le prix de marché estimé

RÈGLES IMPORTANTES:
- "bebe" en turc = jeune animal (bebe kuzu = agneau), PAS aliment bébé
- Alimentation Bébé = UNIQUEMENT vrais aliments bébé (Blédina, petits pots, lait infantile, Gallia, Hipp)
- Emballage Horeca = contenants pro (bakjes, folie, barquettes, serviettes, couverts)
- Si "halal" + "viande" → Viandes Halal + Boucherie (pas Alimentation Bébé)
- Produits frais (viande, fromage, yaourt) → tag "frais" + collection "Produits Frais"
- Marques turques (Ülker, Eti, Bingo, Baltat, Bebeto, Bizim, Eker, Torku) → tag "turque"
- Produits italiens (Barilla, Mutti, Ferrero, Lavazza) → tag "italienne"
- Produits asiatiques (Ayam, Kikkoman, Yeo's) → tag "asiatique"
- Produits maghrébins → tag "maghreb"

RÉFÉRENCES PRIX FRANCE/BELGIQUE (par unité):
- Agneau 500g: 18-22€ | 1kg: 35-42€ | 2kg+: 70-90€
- Veau 500g: 25-32€ | Poulet/Volaille 500g: 8-12€
- Charcuterie halal 200g: 10-13€ | 120g: 5-7€
- Mezze/Salades 200g: 6-8€ | Börek/Dolma: 5-7€
- Biscuits 300g: 2.50-3.50€ | 180g: 2-2.50€ | 100g: 1.50-2€
- Bonbons 1kg: 8-10€ | 300g: 3-4€ | 80g: 0.90-1.20€
- Épices 50-100g: 1.80-3€ | Bouillon 120g: 2-2.50€
- Pâtes 500g: 1.50-2€ | Riz 2.5kg: 5-7€ | 1kg: 2.50-3.50€
- Huile olive 750ml: 7-10€ | 3L: 18-22€
- Margarine 500g: 2-3€ | Huile 1L: 3-4.50€
- Nettoyant 1L: 4-6€ | Lessive 4L: 8-12€ | Lessive 9kg: 14-18€
- Shampoing 500ml: 5-8€ | Gel douche 400ml: 4-6€
- Boissons 330ml: 1.50-2€ | Jus 1L: 2-3€ | Ayran 300ml: 1.50-2€
- Energy drink unité: 2-3€ | Pack 24: 35-42€
- Conserves légumes 400g: 3-4.50€ | 800g: 5-7€
- Emballage 100 pcs: 8-12€ | 50 pcs: 5-7€

Produits:
${productList}

Réponds UNIQUEMENT en JSON array, sans texte avant ou après:
[
  {
    "index": 1,
    "new_title": null,
    "description_html": null,
    "product_type": "Biscuits Turcs",
    "collections": ["Cuisine Turque", "Snacks & Apéro"],
    "tags_add": ["turque", "biscuits"],
    "tags_remove": [],
    "price": null
  }
]`;

  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const r = await fetch('https://api.deepseek.com/chat/completions', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${DEEPSEEK_KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model: 'deepseek-chat',
          messages: [{ role: 'user', content: prompt }],
          temperature: 0.1,
          max_tokens: 4000
        })
      });

      const d = await r.json();
      if (!d.choices?.[0]?.message?.content) throw new Error('No content from DeepSeek');

      let content = d.choices[0].message.content.trim();
      content = content.replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
      const parsed = JSON.parse(content);
      if (Array.isArray(parsed)) return parsed;
      const arr = parsed.results || parsed.products || parsed.items || Object.values(parsed)[0];
      if (Array.isArray(arr)) return arr;
      return [parsed];
    } catch(e) {
      if (attempt === 3) { console.error(`\n  DeepSeek hata: ${e.message}`); return null; }
      await delay(3000 * attempt);
    }
  }
}

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('🚀 Mondimart FULL AI Güncellemesi başlıyor...\n');

// 1. Tüm koleksiyonları Shopify'dan çek
console.log('📁 Koleksiyonlar yükleniyor...');
const rawCollections = await fetchAll('custom_collections.json?limit=250&fields=id,title');
const COLLECTIONS = {};
for (const c of rawCollections) COLLECTIONS[c.title] = c.id;
const collectionNames = Object.keys(COLLECTIONS);
console.log(`${collectionNames.length} koleksiyon yüklendi`);
console.log('Koleksiyonlar:', collectionNames.join(' | '));
console.log();

// 2. Tüm ürünleri çek
console.log('🛍️  Ürünler yükleniyor...');
const allProducts = await fetchAll(
  'products.json?limit=250&fields=id,title,product_type,tags,variants,body_html'
);
console.log(`${allProducts.length} ürün yüklendi\n`);

// 3. Mevcut collect ilişkileri
console.log('🔗 Koleksiyon ilişkileri yükleniyor...');
const allCollects = await fetchAll('collects.json?fields=id,product_id,collection_id&limit=250');
const existingSet = new Set(allCollects.map(c => `${c.product_id}-${c.collection_id}`));
console.log(`${allCollects.length} mevcut ilişki yüklendi\n`);

// 4. Hangi ürünler işlenecek?
// Hepsini işle — deepseek başlık/tag/koleksiyon/fiyat hepsini kontrol eder
// Karaca ürünleri fiyat güncellemesi için ayrı işlenecek
const nonKaraca = allProducts.filter(p =>
  p.product_type !== 'Karaca' && p.product_type !== 'Vaisselle' &&
  !p.title.toLowerCase().includes('karaca')
);
const karacaProducts = allProducts.filter(p =>
  p.product_type === 'Karaca' || p.product_type === 'Vaisselle' ||
  p.title.toLowerCase().includes('karaca')
);

console.log(`📊 ${nonKaraca.length} normal ürün + ${karacaProducts.length} Karaca ürünü\n`);

let processed = 0, titleFixed = 0, typeFixed = 0, tagFixed = 0, colAdded = 0, priceFixed = 0, errors = 0;
const BATCH = 5;

// ============================================================
// NORMAL ÜRÜNLER → DeepSeek ile tam analiz
// ============================================================
console.log('=== NORMAL ÜRÜNLER (AI analizi) ===\n');

for (let i = 0; i < nonKaraca.length; i += BATCH) {
  const batch = nonKaraca.slice(i, i + BATCH);

  process.stdout.write(`[${i+1}-${Math.min(i+BATCH, nonKaraca.length)}/${nonKaraca.length}] `);

  const results = await analyzeWithDeepSeek(batch, collectionNames);

  if (!results) {
    errors += batch.length;
    process.stdout.write('❌ hata\n');
    await delay(5000);
    continue;
  }

  for (let j = 0; j < batch.length; j++) {
    const p = batch[j];
    const result = results.find(r => r.index === j + 1) || results[j];
    if (!result) continue;

    // --- BAŞLIK + AÇIKLAMA güncelleme ---
    let productUpdate = { product: { id: p.id } };
    let needsProductUpdate = false;

    if (result.new_title && result.new_title.trim() !== p.title.trim()) {
      productUpdate.product.title = result.new_title.trim();
      needsProductUpdate = true;
      titleFixed++;
    }

    if (result.description_html && result.new_title) {
      productUpdate.product.body_html = result.description_html;
      needsProductUpdate = true;
    }

    // --- TAG güncelleme ---
    const currentTags = new Set((p.tags || '').split(',').map(t => t.trim()).filter(Boolean));
    const newTags = new Set(currentTags);
    let tagsChanged = false;
    for (const t of (result.tags_add || [])) {
      if (t && !newTags.has(t)) { newTags.add(t); tagsChanged = true; }
    }
    for (const t of (result.tags_remove || [])) {
      if (t && newTags.has(t)) { newTags.delete(t); tagsChanged = true; }
    }
    if (tagsChanged) {
      productUpdate.product.tags = [...newTags].join(', ');
      needsProductUpdate = true;
      tagFixed++;
    }

    // --- PRODUCT TYPE güncelleme ---
    const typeOk = result.product_type && PRODUCT_TYPES.includes(result.product_type);
    if (typeOk && result.product_type !== p.product_type) {
      productUpdate.product.product_type = result.product_type;
      needsProductUpdate = true;
      typeFixed++;
    }

    if (needsProductUpdate) {
      const res = await shopify('PUT', `products/${p.id}.json`, productUpdate);
      if (res.product) process.stdout.write('U');
      else { errors++; process.stdout.write('!'); }
      await delay(250);
    }

    // --- FİYAT güncelleme (sadece 0.00 ise) ---
    const curPrice = parseFloat(p.variants?.[0]?.price || 0);
    if (result.price && result.price > 0 && curPrice === 0) {
      const variantId = p.variants?.[0]?.id;
      if (variantId) {
        const res = await shopify('PUT', `variants/${variantId}.json`, {
          variant: { id: variantId, price: result.price.toFixed(2) }
        });
        if (res.variant) { priceFixed++; process.stdout.write('€'); }
        await delay(220);
      }
    }

    // --- KOLEKSİYON ekleme ---
    for (const colName of (result.collections || [])) {
      const colId = COLLECTIONS[colName];
      if (!colId) continue;
      const key = `${p.id}-${colId}`;
      if (existingSet.has(key)) continue;
      const res = await shopify('POST', 'collects.json', {
        collect: { product_id: p.id, collection_id: colId }
      });
      if (res.collect) {
        colAdded++;
        existingSet.add(key);
        process.stdout.write('+');
      }
      await delay(200);
    }

    processed++;
  }

  process.stdout.write(' ✅\n');
  await delay(1000); // DeepSeek rate limit
}

// ============================================================
// KARACA ÜRÜNLER → Sadece +25% fiyat
// ============================================================
console.log('\n=== KARACA ÜRÜNLER (+25% fiyat) ===\n');
let karacaUpdated = 0;

for (const p of karacaProducts) {
  const curPrice = parseFloat(p.variants?.[0]?.price || 0);
  const variantId = p.variants?.[0]?.id;

  if (!variantId || curPrice <= 0) continue;

  // +25%, 0.05'e yuvarla
  const newPrice = Math.round(curPrice * 1.25 * 20) / 20;

  if (newPrice === curPrice) continue;

  const res = await shopify('PUT', `variants/${variantId}.json`, {
    variant: {
      id: variantId,
      price: newPrice.toFixed(2),
      compare_at_price: curPrice.toFixed(2)
    }
  });

  if (res.variant) {
    karacaUpdated++;
    process.stdout.write('K');
  } else {
    process.stdout.write('!');
  }
  await delay(220);
}

console.log(`\n${karacaUpdated} Karaca ürünü güncellendi`);

// ============================================================
// ÖZET
// ============================================================
console.log('\n\n=== SONUÇ ===');
console.log(`✅ İşlenen: ${processed} | Başlık düzeltilen: ${titleFixed}`);
console.log(`🏷️  Tag düzeltilen: ${tagFixed} | Tip değiştirilen: ${typeFixed}`);
console.log(`💶 Fiyat eklenen: ${priceFixed} | 📁 Koleksiyona eklenen: ${colAdded}`);
console.log(`💎 Karaca +25%: ${karacaUpdated} | ❌ Hata: ${errors}`);
console.log('\nNot: U=güncellendi, €=fiyat, +=koleksiyon, K=Karaca');
