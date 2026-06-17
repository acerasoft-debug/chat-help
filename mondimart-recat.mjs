// ============================================================
// MONDIMART — Mevcut ürünleri yeniden kategorize et + fiyat yuvarla
// Adımlar:
//   1. Sitedeki TÜM ürünleri çek (Karaca hariç tutulur)
//   2. DeepSeek ile her ürün için doğru koleksiyonları belirle
//   3. Doğru koleksiyona EKLE + yanlış (yönetilen) koleksiyonlardan ÇIKAR
//   4. Fiyatı en yakın 0,10'a yuvarla (7.88 -> 7.90)
//
// Kullanım:
//   SHOPIFY_TOKEN='...' DEEPSEEK_KEY='...' node mondimart-recat.mjs
//   Önizleme (hiçbir şey değiştirmez): DRY=1 ...
//   Test için sınırla:                 LIMIT=20 ...
//   Sadece fiyat yuvarla (DeepSeek yok): PRICEONLY=1 ...
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const DEEPSEEK_KEY  = process.env.DEEPSEEK_KEY;
const DRY   = process.env.DRY === '1';
const LIMIT = process.env.LIMIT ? parseInt(process.env.LIMIT) : 0;
const PRICEONLY = process.env.PRICEONLY === '1';
const MARKUP = process.env.MARKUP ? parseFloat(process.env.MARKUP) : 1.20; // varsayılan +%20

const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const SH = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN || (!PRICEONLY && !DEEPSEEK_KEY)) {
  console.error('❌ Eksik: SHOPIFY_TOKEN (ve PRICEONLY değilse DEEPSEEK_KEY) gerekli');
  process.exit(1);
}
const delay = ms => new Promise(r => setTimeout(r, ms));

async function shopify(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, { method, headers: SH, body: body ? JSON.stringify(body) : undefined });
      if (method === 'DELETE') return { ok: r.ok, status: r.status };
      return await r.json();
    } catch (e) { if (i === 4) throw e; await delay(2000 * i); }
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
// KOLEKSİYON HARİTASI (Karaca dahil DEĞİL — gıda kategorileri)
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
  'Gastronomie Française':687026504005,'Promotions':687026471237,
  'Sans Gluten':687037481285,'Bio Certifié':687037546821,'Vegan':686915354949,'Épicerie Fine':686917910853,
  'Fromages Affinés':687859990853,'Légumes Frais':687821750597,'Pain':687825486149,
  'Plats Préparés':687822963013,'Surgelés':687822766405,'Vins':686918926661,
  'Alternatives Végétales':687030796613,'Boissons Végétales':687030862149,
  'Conserves Vegan':687029813573,'Sucreries Vegan':687030894917,
  'Coffrets':687026405701,'Coffrets Cadeaux':687866478917,"Coffrets d'Épices":687863824709,
  'Compléments Alimentaires':687825977669,'Sans Sucre':687850357061,'Sport':687860711749,
  'Laits Bébé':687866446149,'Jus Bébé':687826239813,'Snacks Bébé':687827910981,
};
// Yönetilen koleksiyon ID'leri — yalnızca bunlardan çıkarma yapılır (Karaca asla)
const MANAGED_IDS = new Set(Object.values(COLLECTIONS));
const ID_TO_NAME = Object.fromEntries(Object.entries(COLLECTIONS).map(([k, v]) => [v, k]));
const KARACA_ID = 687037448517; // genel Karaca koleksiyonu — asla dokunma

const PRODUCT_TYPES = [
  'Hygiène & Beauté','Alimentation Bébé','Entretien Ménager','Viandes Halal','Charcuterie Halal',
  'Mezze & Spécialités','Boissons','Thés & Cafés','Biscuits & Gâteaux','Confiserie & Bonbons',
  'Chocolats & Pâtes à Tartiner','Douceurs Orientales','Fruits Secs & Noix','Pâtes & Féculents',
  'Huiles & Matières Grasses','Épices & Condiments','Condiments & Sauces','Soupes & Potages',
  'Légumes & Conserves','Poissons','Pâtisserie & Desserts','Cuisine Asiatique','Épicerie Fine',
  'Snacks & Apéro','Petit-Déjeuner',
];

// ============================================================
// DeepSeek: doğru koleksiyon + product_type belirle
// ============================================================
async function enrich(batch) {
  const list = batch.map((p, i) => {
    const desc = (p.body_html || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 150);
    return `${i+1}. Titre: "${p.title}" | Marque: "${p.vendor}" | Prix actuel: ${p.variants?.[0]?.price||'?'}€ | Type actuel: "${p.product_type}" | Tags: "${(p.tags||'').slice(0,120)}" | Description: "${desc}"`;
  }).join('\n');
  const cols = Object.keys(COLLECTIONS).filter(c => c !== 'Karaca').join(', ');
  const types = PRODUCT_TYPES.join(', ');
  const prompt = `Tu es expert e-commerce d'une épicerie du monde (Mondimart) basée en France. Pour chaque produit, détermine le bon classement ET le prix de marché français réaliste pour un consommateur final.

Produits:
${list}

Pour CHAQUE produit fournis un objet JSON avec:
- "collections": tableau de 1 à 3 noms EXACTEMENT depuis cette liste: ${cols}
- "product_type": EXACTEMENT un parmi: ${types}
- "market_price_eur": prix de vente au détail en France (nombre décimal, ex: 3.50) — recherche le prix réel du marché français pour ce produit. Si inconnu, mets null.

Réponds UNIQUEMENT avec un tableau JSON de ${batch.length} objets, dans le même ordre. Pas de texte autour.`;

  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const r = await fetch('https://api.deepseek.com/chat/completions', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${DEEPSEEK_KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: 'deepseek-chat', messages: [{ role: 'user', content: prompt }], temperature: 0.2, max_tokens: 2000 })
      });
      const d = await r.json();
      let c = d.choices?.[0]?.message?.content?.trim();
      if (!c) { const e = d.error?.message || JSON.stringify(d).slice(0,200); throw new Error(`boş cevap (HTTP ${r.status}: ${e})`); }
      c = c.replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
      const parsed = JSON.parse(c);
      const arr = Array.isArray(parsed) ? parsed : (parsed.results || parsed.products || Object.values(parsed)[0]);
      return Array.isArray(arr) ? arr : [parsed];
    } catch (e) {
      if (attempt === 3) { console.error(`   DeepSeek hata: ${e.message}`); return null; }
      await delay(3000 * attempt);
    }
  }
}

function roundPrice(v) {
  const n = parseFloat(v);
  if (!n || n <= 0) return null;
  return Math.round(n).toFixed(2); // tam sayıya yuvarla (7.89 → 8.00)
}

function calcPrice(marketPrice, currentPrice) {
  const mp = parseFloat(marketPrice);
  if (mp && mp > 0) return roundPrice(mp * MARKUP);
  // piyasa fiyatı bilinmiyorsa mevcut fiyatı yuvarla
  return roundPrice(currentPrice);
}

// ============================================================
// ANA AKIŞ
// ============================================================
console.log('📦 Sitedeki ürünler yükleniyor...');
const all = await fetchAll('products.json?limit=250&fields=id,title,vendor,product_type,tags,variants,body_html');
console.log(`   ${all.length} ürün`);

// Karaca markasını / Karaca ürünlerini ayrı tut
let targets = all.filter(p =>
  (p.vendor || '').toLowerCase() !== 'karaca' && !/karaca/i.test(p.title || '')
);
console.log(`   Karaca hariç işlenecek: ${targets.length} ürün`);
if (LIMIT) targets = targets.slice(0, LIMIT);

let priced = 0, recat = 0, skipped = 0;

for (let i = 0; i < targets.length; i += 5) {
  const batch = targets.slice(i, i + 5);
  console.log(`\n🧠 ${i + 1}-${i + batch.length} / ${targets.length}`);

  if (PRICEONLY) {
    for (const p of batch) {
      const v = p.variants?.[0];
      if (!v) continue;
      const rp = roundPrice(v.price);
      if (rp && rp !== v.price) {
        if (DRY) console.log(`   [ÖNİZLEME] 💶 "${p.title}" ${v.price}€ → ${rp}€`);
        else { await shopify('PUT', `variants/${v.id}.json`, { variant: { id: v.id, price: rp } }); await delay(250); }
        priced++;
      }
    }
    continue;
  }

  // --- KATEGORİ + FİYAT (DeepSeek piyasa fiyatı araştırır) ---
  const results = await enrich(batch);
  if (!results) { skipped += batch.length; continue; }

  for (let j = 0; j < batch.length; j++) {
    const p = batch[j];
    const r = results[j] || {};

    // Fiyat: DeepSeek piyasa fiyatı × MARKUP, yoksa mevcut fiyatı yuvarla
    const v = p.variants?.[0];
    if (v) {
      const np = calcPrice(r.market_price_eur, v.price);
      if (np && np !== v.price) {
        const src = r.market_price_eur ? `piyasa ${parseFloat(r.market_price_eur).toFixed(2)}€ × ${MARKUP}` : 'yuvarla';
        if (DRY) console.log(`   [ÖNİZLEME] 💶 "${p.title}" ${v.price}€ → ${np}€ (${src})`);
        else { await shopify('PUT', `variants/${v.id}.json`, { variant: { id: v.id, price: np } }); await delay(250); }
        priced++;
      }
    }
    const desiredNames = (r.collections || []).filter(c => COLLECTIONS[c] && c !== 'Karaca');
    const desiredIds = new Set(desiredNames.map(c => COLLECTIONS[c]));
    if (!desiredIds.size) { continue; }

    // ürünün mevcut koleksiyonları
    const collects = await fetchAll(`collects.json?product_id=${p.id}&limit=250&fields=id,collection_id`);
    const currentIds = new Set(collects.map(c => c.collection_id));

    // EKLE: istenen ama mevcut olmayanlar
    const toAdd = [...desiredIds].filter(id => !currentIds.has(id));
    // ÇIKAR: yönetilen sette olan ama istenmeyenler (Karaca asla)
    const toRemove = collects.filter(c =>
      MANAGED_IDS.has(c.collection_id) && c.collection_id !== KARACA_ID && !desiredIds.has(c.collection_id)
    );

    const addNames = toAdd.map(id => ID_TO_NAME[id] || id);
    const remNames = toRemove.map(c => ID_TO_NAME[c.collection_id] || c.collection_id);

    if (!toAdd.length && !toRemove.length) {
      // sadece product_type güncellenebilir
    }

    if (DRY) {
      console.log(`   [ÖNİZLEME] 🏷️ "${p.title}"`);
      if (addNames.length) console.log(`        + ${addNames.join(', ')}`);
      if (remNames.length) console.log(`        − ${remNames.join(', ')}`);
      if (r.product_type && r.product_type !== p.product_type && PRODUCT_TYPES.includes(r.product_type))
        console.log(`        type: ${p.product_type || '—'} → ${r.product_type}`);
      recat++;
      continue;
    }

    for (const id of toAdd) {
      await shopify('POST', 'collects.json', { collect: { product_id: p.id, collection_id: id } });
      await delay(200);
    }
    for (const c of toRemove) {
      await shopify('DELETE', `collects/${c.id}.json`);
      await delay(200);
    }
    if (r.product_type && r.product_type !== p.product_type && PRODUCT_TYPES.includes(r.product_type)) {
      await shopify('PUT', `products/${p.id}.json`, { product: { id: p.id, product_type: r.product_type } });
      await delay(200);
    }
    console.log(`   ✅ "${p.title}"${addNames.length ? ` +[${addNames.join(', ')}]` : ''}${remNames.length ? ` −[${remNames.join(', ')}]` : ''}`);
    recat++;
  }
}

console.log(`\n🎉 Bitti. ${DRY ? 'Önizlenen' : 'Güncellenen'} → fiyat: ${priced} | kategori: ${recat} | atlanan: ${skipped}`);
