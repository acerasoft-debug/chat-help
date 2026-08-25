const TOKEN = process.env.SHOPIFY_TOKEN || 'BURAYA_TOKEN_YAZIN';
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const H = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

async function api(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, {
        method, headers: H,
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
    const r = await fetch(url, { headers: H });
    const data = await r.json();
    const key = Object.keys(data)[0];
    all = all.concat(data[key] || []);
    const link = r.headers.get('link') || '';
    url = link.match(/<([^>]+)>;\s*rel="next"/)?.[1] || null;
    await new Promise(r => setTimeout(r, 300));
  }
  return all;
}

const delay = ms => new Promise(r => setTimeout(r, ms));

// Koleksiyona göre hangi ürünler OLMAMALI kuralları
// Her kural: fonksiyon → true ise bu ürün bu koleksiyonda OLMAMALI
const REMOVAL_RULES = {
  686987411781: { // Alimentation Bébé
    name: 'Alimentation Bébé',
    shouldRemove: (p, tags, type) =>
      tags.has('viande') || tags.has('halal') || tags.has('mezze') ||
      tags.has('charcuterie') || tags.has('entretien') ||
      type === 'Mezze & Spécialités' || type === 'Charcuterie Halal' ||
      type === 'Viandes Halal' || type === "Viande d'Agneau" || type === 'Viande de Veau' ||
      type === 'Volaille Halal' || type === 'Entretien Ménager' || type === 'Hygiène & Beauté'
  },
  686919057733: { // Hygiène & Beauté
    name: 'Hygiène & Beauté',
    shouldRemove: (p, tags, type) =>
      (tags.has('viande') || tags.has('halal') || tags.has('turque') && !tags.has('hygiène') &&
      type !== 'Hygiène & Beauté' && type !== 'Entretien Ménager') &&
      !p.title.toLowerCase().includes('shampoing') &&
      !p.title.toLowerCase().includes('savon') &&
      !p.title.toLowerCase().includes('gel douche') &&
      !p.title.toLowerCase().includes('eyup sabri') &&
      !p.title.toLowerCase().includes('tuncer') &&
      !p.title.toLowerCase().includes('dentifrice') &&
      !p.title.toLowerCase().includes('déodorant')
  },
  686915551557: { // Cuisine Turque
    name: 'Cuisine Turque',
    shouldRemove: (p, tags, type) =>
      !tags.has('turque') && !tags.has('bizim') && !tags.has('ulker') && !tags.has('eti') &&
      type === 'Karaca' // Karaca Türkçe marka ama mutfak koleksiyonunda olmayacak
  },
  686918402373: { // Viandes Halal
    name: 'Viandes Halal',
    shouldRemove: (p, tags, type) =>
      !tags.has('halal') || !tags.has('viande')
  },
  686915518789: { // Boucherie
    name: 'Boucherie',
    shouldRemove: (p, tags, type) =>
      !tags.has('viande') && !tags.has('halal') && !tags.has('charcuterie')
  },
  686918598981: { // Charcuterie
    name: 'Charcuterie',
    shouldRemove: (p, tags, type) =>
      !tags.has('charcuterie') &&
      type !== 'Charcuterie Halal' && type !== 'Charcuterie'
  },
  687866380613: { // Charcuterie Halal
    name: 'Charcuterie Halal',
    shouldRemove: (p, tags, type) =>
      !tags.has('charcuterie') &&
      type !== 'Charcuterie Halal' && type !== 'Charcuterie'
  },
  686918992197: { // Boissons
    name: 'Boissons',
    shouldRemove: (p, tags, type) =>
      !tags.has('boissons') && !tags.has('energie') && !tags.has('energisant') &&
      !tags.has('boissons-laitieres') && !tags.has('jus') &&
      type !== 'Boissons' && type !== 'Boissons Énergisantes' && type !== 'Boissons Laitières' &&
      type !== 'Thés & Cafés' &&
      !p.title.toLowerCase().includes('ayran') &&
      !p.title.toLowerCase().includes('red bull') &&
      !p.title.toLowerCase().includes('jus ')
  },
  686916337989: { // Épices
    name: 'Épices',
    shouldRemove: (p, tags, type) =>
      !tags.has('epices') && !tags.has('epices-condiments') && !tags.has('bouillon') &&
      type !== 'Épices & Condiments' && type !== 'Condiments & Sauces' &&
      !tags.has('condiment') && !tags.has('sauces')
  },
  686920565061: { // Entretien
    name: 'Entretien',
    shouldRemove: (p, tags, type) =>
      !tags.has('entretien') && !tags.has('entretien-menager') && !tags.has('produits-de-nettoyage') &&
      type !== 'Entretien Ménager' && type !== 'Entretien'
  },
  686915289413: { // Cuisine Asiatique
    name: 'Cuisine Asiatique',
    shouldRemove: (p, tags, type) =>
      !tags.has('asiatique') && !tags.has('ayam')
  },
  686915223877: { // Cuisine Italienne
    name: 'Cuisine Italienne',
    shouldRemove: (p, tags, type) =>
      !tags.has('italienne') && !tags.has('balconi') &&
      !p.title.toLowerCase().includes('italian') &&
      !p.title.toLowerCase().includes('barilla') &&
      !p.title.toLowerCase().includes('balconi')
  },
  686915420485: { // Cuisine Maghrébine
    name: 'Cuisine Maghrébine',
    shouldRemove: (p, tags, type) =>
      !tags.has('maghreb') && !tags.has('maghrebine') && !tags.has('algerienne') &&
      !tags.has('arabe') && !tags.has('tunisienne')
  },
  686917550405: { // Pâtes
    name: 'Pâtes',
    shouldRemove: (p, tags, type) =>
      !tags.has('pates') && !tags.has('legumineuses') && !tags.has('cereales-feculents') &&
      type !== 'Pâtes & Féculents'
  },
  687768404293: { // Fruits Secs & Noix
    name: 'Fruits Secs & Noix',
    shouldRemove: (p, tags, type) =>
      !tags.has('fruits-secs') && !tags.has('fruits-secs-a-coque') &&
      type !== 'Fruits Secs & Noix'
  },
  686918304069: { // Produits Frais
    name: 'Produits Frais',
    shouldRemove: (p, tags, type) =>
      !tags.has('frais')
  },
  686917910853: { // Épicerie Fine - bu genel bir koleksiyon, sadece Karaca ürünlerini çıkar
    name: 'Épicerie Fine',
    shouldRemove: (p, tags, type) =>
      type === 'Karaca'
  },
  687037448517: { // Karaca
    name: 'Karaca',
    shouldRemove: (p, tags, type) =>
      type !== 'Karaca' && type !== 'Vaisselle'
  },
  686918828357: { // Jus
    name: 'Jus',
    shouldRemove: (p, tags, type) =>
      !p.title.toLowerCase().includes('jus') &&
      !tags.has('jus') && !tags.has('nectar')
  },
  686917583173: { // Baklava
    name: 'Baklava',
    shouldRemove: (p, tags, type) =>
      !tags.has('baklava') && !tags.has('douceurs-orientales') &&
      !p.title.toLowerCase().includes('baklava') &&
      !p.title.toLowerCase().includes('lokoum') && !p.title.toLowerCase().includes('lokum')
  },
  686918631749: { // Poissons
    name: 'Poissons',
    shouldRemove: (p, tags, type) =>
      !tags.has('poisson') && type !== 'Poissons' && type !== 'Conserves de Poissons' &&
      !p.title.toLowerCase().includes('thon') &&
      !p.title.toLowerCase().includes('saumon') &&
      !p.title.toLowerCase().includes('sardine') &&
      !p.title.toLowerCase().includes('maquereau')
  },
  687022866757: { // Hygiène Bébé
    name: 'Hygiène Bébé',
    shouldRemove: (p, tags, type) =>
      !tags.has('couches') && !tags.has('changes') && !tags.has('hygiene-bebe') &&
      !p.title.toLowerCase().includes('couche') &&
      !p.title.toLowerCase().includes('pampers') &&
      !p.title.toLowerCase().includes('huggies') &&
      !p.title.toLowerCase().includes('lingette bébé')
  },
  687022833989: { // Couches & Changes
    name: 'Couches & Changes',
    shouldRemove: (p, tags, type) =>
      !tags.has('couches') && !tags.has('changes') &&
      !p.title.toLowerCase().includes('couche') &&
      !p.title.toLowerCase().includes('pampers')
  },
};

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('Tüm ürünler yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,title,product_type,tags');
const productMap = new Map(products.map(p => [p.id, p]));
console.log(`${products.length} ürün yüklendi\n`);

console.log('Tüm koleksiyon ilişkileri yükleniyor...');
const allCollects = await fetchAll('collects.json?fields=id,product_id,collection_id&limit=250');
console.log(`${allCollects.length} ilişki yüklendi\n`);

// Koleksiyona göre grupla
const collectsByCol = new Map();
for (const c of allCollects) {
  if (!collectsByCol.has(c.collection_id)) collectsByCol.set(c.collection_id, []);
  collectsByCol.get(c.collection_id).push(c);
}

let totalRemoved = 0, totalErrors = 0;
const removeStats = {};

// Her kontrol edilecek koleksiyon için
for (const [colId, rule] of Object.entries(REMOVAL_RULES)) {
  const numColId = parseInt(colId);
  const collects = collectsByCol.get(numColId) || [];
  if (collects.length === 0) continue;

  console.log(`\n📁 ${rule.name} (${collects.length} ürün kontrol ediliyor)...`);
  let removed = 0;

  for (const c of collects) {
    const p = productMap.get(c.product_id);
    if (!p) continue;

    const tags = new Set((p.tags || '').toLowerCase().split(',').map(t => t.trim()).filter(Boolean));
    const type = p.product_type || '';

    if (rule.shouldRemove(p, tags, type)) {
      const res = await api('DELETE', `collects/${c.id}.json`);
      if (res.ok || res.status === 200 || res.status === 204) {
        removed++;
        totalRemoved++;
        process.stdout.write('-');
      } else {
        totalErrors++;
        process.stdout.write('!');
      }
      await delay(200);
    }
  }

  if (removed > 0) {
    removeStats[rule.name] = removed;
    console.log(`\n  → ${removed} ürün çıkarıldı`);
  } else {
    console.log('  → Temiz, değişiklik yok');
  }
}

console.log('\n\n=== TEMİZLİK SONUCU ===');
console.log(`✅ Toplam çıkarılan: ${totalRemoved} | Hata: ${totalErrors}`);
if (Object.keys(removeStats).length) {
  console.log('\nKoleksiyona göre:');
  Object.entries(removeStats).sort((a,b) => b[1]-a[1])
    .forEach(([k,v]) => console.log(`  ${k}: -${v} ürün`));
}
console.log('\nNot: Silinen ürünler mağazadan SİLİNMEDİ, sadece yanlış koleksiyondan çıkarıldı.');
