// ============================================================
// MONDIMART - DeepSeek AI ile Akıllı Ürün Kategorizasyonu
// Kullanım: SHOPIFY_TOKEN='...' DEEPSEEK_KEY='...' node ~/mondimart-deepseek.mjs
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
      if (method === 'DELETE') return { ok: r.ok };
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

// ============================================================
// KOLEKSİYON HARİTASI
// ============================================================
const COLLECTIONS = {
  'Alimentation Bébé':      686987411781,
  'Hygiène & Beauté':       686919057733,
  'Hygiène Bébé':           687022866757,
  'Couches & Changes':      687022833989,
  'Entretien':              686920565061,
  'Viandes Halal':          686918402373,
  'Viandes Conventionnelles': 686918467909,
  'Boucherie':              686915518789,
  'Halal Certifié':         687825879365,
  'Charcuterie Halal':      687866380613,
  'Charcuterie':            686918598981,
  'Produits Frais':         686918304069,
  'Poissons':               686918631749,
  'Conserves de Poissons':  687866609989,
  'Boissons':               686918992197,
  'Jus':                    686918828357,
  'Thés':                   686918730053,
  'Snacks & Apéro':         687825617221,
  'Pâtes à Tartiner':       687825813829,
  'Fruits Secs & Noix':     687768404293,
  'Pâtes':                  686917550405,
  'Céréales & Légumineuses': 687026569541,
  'Huile d\'Olive':         686916829509,
  'Épices':                 686916337989,
  'Sauces & Condiments':    687825846597,
  'Tomates':                686917353797,
  'Légumes Exotiques':      686918074693,
  'Baklava':                686917583173,
  'Petit-Déjeuner':         687866347845,
  'Fromages Frais':         686918172997,
  'Œufs & Laitages':        687822635333,
  'Cuisine Turque':         686915551557,
  'Cuisine Italienne':      686915223877,
  'Cuisine Maghrébine':     686915420485,
  'Cuisine Asiatique':      686915289413,
  'Cuisine Espagnole':      686915486021,
  'Cuisine Portugaise':     686915322181,
  'Gastronomie Française':  687026504005,
  'Karaca':                 687037448517,
  'Karaca Assiettes':       687035384133,
  'Karaca Verres':          687035580741,
  'Karaca Batterie de Cuisine': 687036727621,
  'Karaca Électroménager':  687037186373,
  'Karaca Café & Thé':      687036105029,
  'Karaca Bols':            687035482437,
  'Karaca Couverts':        687035842885,
  'Karaca Poêles':          687036924229,
  'Promotions':             687026471237,
  'Sans Gluten':            687037481285,
  'Bio Certifié':           687037546821,
  'Vegan':                  686915354949,
  'Épicerie Fine':          686917910853,
};

const PRODUCT_TYPES = [
  'Hygiène & Beauté', 'Alimentation Bébé', 'Entretien Ménager',
  'Viandes Halal', "Viande d'Agneau", 'Viande de Veau', 'Volaille Halal',
  'Charcuterie Halal', 'Mezze & Spécialités', 'Boissons', 'Boissons Énergisantes',
  'Boissons Laitières', 'Thés & Cafés', 'Biscuits & Gâteaux', 'Biscuits Turcs',
  'Biscuits Italiens', 'Confiserie & Bonbons', 'Chocolats & Pâtes à Tartiner',
  'Douceurs Orientales', 'Fruits Secs & Noix', 'Pâtes & Féculents',
  'Huiles & Matières Grasses', 'Épices & Condiments', 'Condiments & Sauces',
  'Soupes & Potages', 'Légumes & Conserves', 'Poissons', 'Pâtisserie & Desserts',
  'Emballage Horeca', 'Aluminium & Film', 'Emballage Soupe', 'Emballage Boissons',
  'Serviettes & Couverts', 'Cuisine Asiatique', 'Karaca', 'Vaisselle',
  'Épicerie Fine', 'Snacks & Apéro', 'Petit-Déjeuner',
];

// ============================================================
// DEEPSEEK ANALİZİ - 5 ürünü toplu gönder
// ============================================================
async function analyzeWithDeepSeek(products) {
  const productList = products.map((p, i) =>
    `${i+1}. Titre: "${p.title}" | Tags actuels: "${p.tags}" | Type actuel: "${p.product_type}" | Prix actuel: ${p.variants?.[0]?.price || '0.00'}€`
  ).join('\n');

  const collectionList = Object.keys(COLLECTIONS).join(', ');
  const typeList = PRODUCT_TYPES.join(', ');

  const prompt = `Tu es un expert en épicerie mondiale (Turkish, Maghreb, Italian, Asian, Halal products) et en pricing pour la France et la Belgique.

Analyse ces ${products.length} produits et détermine pour chacun:
1. Le bon "product_type" (choisis EXACTEMENT parmi: ${typeList})
2. Les bonnes "collections" (choisis parmi: ${collectionList})
3. Les "tags" corrects à ajouter (en minuscules)
4. Les "tags_remove" à supprimer si incorrects
5. Le "price" de vente réaliste en France/Belgique (épicerie mondiale, prix moyen marché 2024)
   - Basé sur le grammage/volume dans le titre si possible
   - Prix compétitif mais rentable (marge 30-40% sur prix grossiste estimé)
   - Si le prix actuel est déjà > 0, ne pas le changer (mettre null)

RÈGLES CATÉGORIE:
- "bebe" en turc = jeune animal (bebe kuzu = agneau), PAS aliment bébé
- Alimentation Bébé = UNIQUEMENT vrais aliments bébé (Blédina, petits pots, lait infantile)
- Mezze = baba ganoush, haydari, fava, hummus, dolma, börek, salade turque (200g ≈ 6.90€)
- Produits Karaca = vaisselle/cuisine uniquement
- Emballage Horeca = contenants professionnels (bakjes, folie, serviettes)
- Turque = marques Bizim, Ülker, Eti, Eker, Bingo, Baltat, Bebeto, etc.
- Si "halal" ET "viande" → Viandes Halal / Boucherie
- Charcuterie = bacon dinde, délice poulet tranché, saucisse, fumé

RÉFÉRENCES PRIX FRANCE/BELGIQUE:
- Agneau 500g: 18-22€ | Agneau 1kg: 35-42€ | Agneau 2.5kg: 80-95€
- Veau 500g: 25-32€ | Poulet 500g: 8-12€
- Mezze/Salades 200g: 6-8€
- Biscuits paquet 300g: 2.50-3.50€ | Biscuits koli (15-20 pcs): 18-25€
- Bonbons 1kg: 8-10€ | Bonbons 180g: 2-3€
- Épices 50-100g: 1.80-3€ | Bouillon 120g: 2-2.50€
- Pâtes 500g: 1.50-2€ | Riz 2.5kg: 5-7€
- Huile olive 750ml: 7-10€ | Margarine 500g: 2-3€
- Nettoyant 1L: 4-6€ | Lessive 4L: 8-12€
- Shampoing 500ml: 5-8€
- Emballage pro 100 pcs: 8-12€

Produits à analyser:
${productList}

Réponds UNIQUEMENT en JSON valide (array), sans texte:
[
  {
    "index": 1,
    "product_type": "...",
    "collections": ["Collection1", "Collection2"],
    "tags_add": ["tag1"],
    "tags_remove": ["mauvais_tag"],
    "price": 6.90
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
          max_tokens: 2000
        })
      });

      const d = await r.json();
      if (!d.choices?.[0]?.message?.content) throw new Error('No content');

      let content = d.choices[0].message.content.trim();
      // Markdown kod bloğunu temizle
      content = content.replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
      // JSON parse
      const parsed = JSON.parse(content);
      if (Array.isArray(parsed)) return parsed;
      // Obje içindeyse array'i bul
      const arr = parsed.results || parsed.products || parsed.items || Object.values(parsed)[0];
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
console.log('🚀 Mondimart DeepSeek AI Kategorizasyonu başlıyor...\n');

console.log('Ürünler yükleniyor...');
const allProducts = await fetchAll('products.json?limit=250&fields=id,title,product_type,tags,variants');
console.log(`${allProducts.length} ürün yüklendi\n`);

// Sadece düzeltilmesi gereken ürünleri seç:
// 1. product_type = 'Épicerie Fine' (genel, spesifik değil)
// 2. Fiyatı 0.00 olan ürünler
// 3. tag'i sadece 'epicerie-fine' olan ürünler
const toProcess = allProducts.filter(p => {
  const price = parseFloat(p.variants?.[0]?.price || 0);
  const type = p.product_type || '';
  const tags = (p.tags || '').toLowerCase();
  return type === 'Épicerie Fine' ||
         type === 'Viandes' ||
         type === 'Entretien' ||
         price === 0 ||
         (tags === 'epicerie-fine') ||
         (tags.includes('epicerie-fine') && !tags.includes('turque') && !tags.includes('halal'));
});

console.log(`${toProcess.length} ürün AI ile analiz edilecek\n`);

// Mevcut collects
console.log('Koleksiyon ilişkileri yükleniyor...');
const allCollects = await fetchAll('collects.json?fields=id,product_id,collection_id&limit=250');
const existingSet = new Set(allCollects.map(c => `${c.product_id}-${c.collection_id}`));
console.log(`${allCollects.length} mevcut ilişki\n`);

let processed = 0, typeFixed = 0, tagFixed = 0, colAdded = 0, errors = 0;
const BATCH = 5; // 5 ürün per API call

console.log('AI analizi başlıyor...\n');

for (let i = 0; i < toProcess.length; i += BATCH) {
  const batch = toProcess.slice(i, i + BATCH);

  process.stdout.write(`[${i+1}-${Math.min(i+BATCH, toProcess.length)}/${toProcess.length}] Analiz ediliyor...`);

  const results = await analyzeWithDeepSeek(batch);

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

    // --- PRODUCT TYPE güncelleme ---
    const typeChanged = result.product_type &&
      result.product_type !== p.product_type &&
      p.product_type !== 'Karaca' &&
      PRODUCT_TYPES.includes(result.product_type);

    if (tagsChanged || typeChanged) {
      const updateBody = { product: { id: p.id } };
      if (tagsChanged) updateBody.product.tags = [...newTags].join(', ');
      if (typeChanged) updateBody.product.product_type = result.product_type;

      const res = await shopify('PUT', `products/${p.id}.json`, updateBody);
      if (res.product) {
        if (typeChanged) typeFixed++;
        if (tagsChanged) tagFixed++;
        process.stdout.write('T');
      } else {
        errors++;
        process.stdout.write('!');
      }
      await delay(250);
    }

    // --- FİYAT güncelleme (sadece 0.00 ise) ---
    const curPrice = parseFloat(p.variants?.[0]?.price || 0);
    if (result.price && curPrice === 0 && result.price > 0) {
      const variantId = p.variants?.[0]?.id;
      if (variantId) {
        const res = await shopify('PUT', `variants/${variantId}.json`, {
          variant: { id: variantId, price: result.price.toFixed(2) }
        });
        if (res.variant) {
          process.stdout.write('€');
        }
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
  await delay(1000); // DeepSeek rate limit için
}

console.log('\n\n=== DEEPSEEK ANALİZ SONUCU ===');
console.log(`✅ İşlenen: ${processed} | Tip düzeltilen: ${typeFixed} | Tag düzeltilen: ${tagFixed}`);
console.log(`📁 Koleksiyona eklenen: ${colAdded} | Hata: ${errors}`);
console.log('\nNot: Sonuçları Shopify Admin\'den kontrol edin.');
