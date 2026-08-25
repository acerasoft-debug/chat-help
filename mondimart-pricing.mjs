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

// ============================================================
// KATEGORİYE GÖRE ORTALAMA MARKET FİYATLARI (Belçika/Fransa)
// ============================================================
function getMarketPrice(p) {
  const title = p.title.toLowerCase();
  const type = p.product_type || '';
  const tags = (p.tags || '').toLowerCase();

  // Karaca → mevcut fiyat × 1.25
  if (type === 'Karaca' || type === 'Vaisselle') {
    const cur = parseFloat(p.variants?.[0]?.price || 0);
    if (cur > 0) return Math.round(cur * 1.25 * 20) / 20; // 0.05'e yuvarlama
    return null;
  }

  // Soğuk zincir et ürünleri
  if (tags.includes('viande') && tags.includes('halal')) {
    if (title.includes('agneau') || type.includes('Agneau')) {
      if (title.includes('2,5kg') || title.includes('2.5kg')) return 89.90;
      if (title.includes('2kg')) return 72.90;
      if (title.includes('1,4kg') || title.includes('1400g')) return 49.90;
      if (title.includes('1kg') || title.includes('1000g')) return 38.90;
      if (title.includes('750g') || title.includes('700g')) return 29.90;
      if (title.includes('500g')) return 19.90;
      if (title.includes('200g')) return 11.90;
      return 19.90; // default 500g
    }
    if (title.includes('veau') || type.includes('Veau') || tags.includes('veau')) {
      if (title.includes('600g')) return 36.90;
      if (title.includes('500g')) return 28.90;
      if (title.includes('200g')) return 13.90;
      if (title.includes('1kg') || title.includes('1000g')) return 48.90;
      return 28.90;
    }
    if (title.includes('poulet') || title.includes('volaille') || title.includes('dinde') ||
        title.includes('schnitzel') || title.includes('kanat')) {
      if (title.includes('500g')) return 9.90;
      if (title.includes('1kg')) return 17.90;
      return 9.90;
    }
    if (title.includes('haché') || title.includes('hache') || title.includes('kofte') || title.includes('köfte') || title.includes('boulette')) {
      if (title.includes('500g')) return 14.90;
      if (title.includes('1kg')) return 26.90;
      return 14.90;
    }
    if (title.includes('brochette') || title.includes('kebap')) {
      return 15.90;
    }
    if (title.includes('ciğer') || title.includes('cigeri') || title.includes('langue')) {
      return 12.90;
    }
    // Genel et
    if (title.includes('500g')) return 16.90;
    if (title.includes('200g')) return 9.90;
    return 16.90;
  }

  // Mezze & Spécialités (200g soğuk)
  if (type === 'Mezze & Spécialités' || tags.includes('mezze')) {
    return 6.90;
  }

  // Charcuterie Halal
  if (type === 'Charcuterie Halal' || type === 'Charcuterie') {
    if (title.includes('200g')) return 11.90;
    if (title.includes('120g')) return 5.90;
    if (title.includes('160g')) return 6.90;
    return 8.90;
  }

  // Alimentation Bébé
  if (type === 'Alimentation Bébé') {
    return 3.99;
  }

  // Hygiène & Beauté
  if (type === 'Hygiène & Beauté') {
    if (title.includes('500ml') || title.includes('500 ml')) return 6.90;
    if (title.includes('250ml')) return 4.90;
    if (title.includes('1l') || title.includes('1000ml')) return 9.90;
    return 5.90;
  }

  // Entretien Ménager
  if (type === 'Entretien Ménager' || type === 'Entretien') {
    if (title.includes('9kg')) return 14.90;
    if (title.includes('4l') || title.includes('4000ml')) return 8.90;
    if (title.includes('2l') || title.includes('2000ml') || title.includes('2L')) return 5.90;
    if (title.includes('5l') || title.includes('5L')) return 9.90;
    if (title.includes('1l') || title.includes('1000ml')) return 4.90;
    if (title.includes('435ml')) return 3.90;
    return 4.90;
  }

  // Boissons Énergisantes
  if (type === 'Boissons Énergisantes') {
    if (title.includes('24')) return 39.90; // pack 24
    if (title.includes('12')) return 21.90;
    return 2.49; // unitaire
  }

  // Boissons / Boissons Laitières
  if (type === 'Boissons' || type === 'Boissons Laitières') {
    if (title.includes('293ml') || title.includes('330ml')) return 1.99;
    if (title.includes('1l') || title.includes('1L')) return 2.49;
    if (title.includes('2l')) return 3.49;
    return 1.99;
  }

  // Biscuits & Gâteaux
  if (type === 'Biscuits & Gâteaux' || type === 'Biscuits Turcs' || type === 'Biscuits Italiens') {
    if (title.includes('15x') || title.includes('20x') || title.includes('24x') || title.includes('18x')) {
      // Büyük koliler
      return 19.90;
    }
    if (title.includes('350g') || title.includes('300g')) return 3.49;
    if (title.includes('280g') || title.includes('270g')) return 2.99;
    if (title.includes('180g') || title.includes('150g') || title.includes('170g')) return 2.29;
    if (title.includes('130g') || title.includes('100g')) return 1.99;
    return 2.49;
  }

  // Confiserie & Bonbons
  if (type === 'Confiserie & Bonbons') {
    if (title.includes('1080g')) return 8.90;
    if (title.includes('300g')) return 3.49;
    if (title.includes('180g')) return 2.49;
    if (title.includes('50g') || title.includes('40g') || title.includes('45g')) return 0.99;
    return 2.99;
  }

  // Chocolats & Pâtes à Tartiner
  if (type === 'Chocolats & Pâtes à Tartiner') {
    if (title.includes('65g')) return 2.49;
    if (title.includes('40g')) return 1.49;
    if (title.includes('72g')) return 1.99;
    return 2.99;
  }

  // Douceurs Orientales
  if (type === 'Douceurs Orientales') {
    if (title.includes('570g')) return 9.90;
    if (title.includes('1kg') || title.includes('1000g')) return 14.90;
    return 7.90;
  }

  // Fruits Secs & Noix
  if (type === 'Fruits Secs & Noix') {
    if (title.includes('200g')) return 4.49;
    if (title.includes('250g')) return 5.49;
    if (title.includes('150g')) return 3.49;
    return 4.49;
  }

  // Pâtes & Féculents
  if (type === 'Pâtes & Féculents') {
    if (title.includes('500g')) return 1.79;
    if (title.includes('2.5kg') || title.includes('2,5kg')) return 4.99;
    if (title.includes('1kg')) return 2.49;
    return 1.99;
  }

  // Huiles & Matières Grasses
  if (type === 'Huiles & Matières Grasses') {
    if (title.includes('olive') || title.includes('zeytin')) {
      if (title.includes('3l') || title.includes('3L')) return 19.90;
      if (title.includes('750ml') || title.includes('750 ml')) return 7.90;
      if (title.includes('1l') || title.includes('1L')) return 9.90;
      return 7.90;
    }
    if (title.includes('margarin') || title.includes('margarine')) {
      if (title.includes('500g')) return 2.49;
      if (title.includes('250g')) return 1.79;
      return 2.49;
    }
    if (title.includes('1l') || title.includes('1L')) return 3.49;
    if (title.includes('5l') || title.includes('5L')) return 12.90;
    return 3.99;
  }

  // Épices & Condiments
  if (type === 'Épices & Condiments') {
    if (title.includes('bulyon') || title.includes('bouillon')) return 2.49;
    if (title.includes('harç') || title.includes('harcı') || title.includes('cesnisi')) return 1.99;
    if (title.includes('45g') || title.includes('50g') || title.includes('65g')) return 1.79;
    if (title.includes('80g')) return 1.99;
    if (title.includes('240g')) return 3.99;
    return 2.29;
  }

  // Condiments & Sauces
  if (type === 'Condiments & Sauces') {
    if (title.includes('1000ml') || title.includes('1l')) return 4.49;
    if (title.includes('750ml')) return 3.99;
    if (title.includes('500ml') || title.includes('375ml')) return 3.49;
    if (title.includes('220g') || title.includes('185g')) return 2.99;
    return 2.99;
  }

  // Soupes & Potages
  if (type === 'Soupes & Potages') {
    return 1.99;
  }

  // Légumes & Conserves
  if (type === 'Légumes & Conserves') {
    if (title.includes('800g')) return 5.90;
    if (title.includes('400g')) return 3.90;
    return 3.90;
  }

  // Pâtisserie & Desserts
  if (type === 'Pâtisserie & Desserts') {
    if (title.includes('107g') || title.includes('118g')) return 2.49;
    if (title.includes('75g')) return 1.99;
    if (title.includes('45g')) return 0.99;
    if (title.includes('80g')) return 1.49;
    return 1.99;
  }

  // Emballage Horeca
  if (type === 'Emballage Horeca' || type === 'Aluminium & Film' || type === 'Emballage Soupe' ||
      type === 'Emballage Boissons' || type === 'Serviettes & Couverts') {
    if (title.includes('100st')) return 8.90;
    if (title.includes('50st')) return 5.90;
    if (title.includes('25st')) return 3.90;
    if (title.includes('1400g') || title.includes('1600g')) return 7.90;
    if (title.includes('800g')) return 5.90;
    return 6.90;
  }

  // Cuisine Asiatique
  if (type === 'Cuisine Asiatique') {
    if (title.includes('185g') || title.includes('220g')) return 3.49;
    return 3.99;
  }

  return null; // Fiyat önerisi yok
}

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('Ürünler yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,title,product_type,tags,variants');
console.log(`${products.length} ürün yüklendi\n`);

let updated = 0, skipped = 0, noPrice = 0;
const stats = {};

console.log('Fiyatlar hesaplanıyor ve güncelleniyor...\n');

for (let i = 0; i < products.length; i++) {
  const p = products[i];
  const curPrice = parseFloat(p.variants?.[0]?.price || 0);
  const variantId = p.variants?.[0]?.id;

  if (!variantId) { skipped++; continue; }

  // Karaca dışındaki ürünlerde mevcut fiyat varsa atla
  const isKaraca = p.product_type === 'Karaca' || p.product_type === 'Vaisselle';
  if (!isKaraca && curPrice > 0) { skipped++; continue; }

  const newPrice = getMarketPrice(p);

  if (!newPrice) {
    if (curPrice === 0) {
      noPrice++;
      // console.log(`  ⚠️  ${p.title.substring(0,50)} — fiyat önerisi yok`);
    } else {
      skipped++;
    }
    continue;
  }

  // Karaca için: mevcut fiyat zaten yüksekse güncelleme
  if (isKaraca && curPrice === newPrice) { skipped++; continue; }

  // Fiyat güncelle
  const res = await api('PUT', `variants/${variantId}.json`, {
    variant: {
      id: variantId,
      price: newPrice.toFixed(2),
      compare_at_price: isKaraca ? curPrice.toFixed(2) : null
    }
  });

  if (res.variant) {
    updated++;
    const type = p.product_type || 'Diğer';
    stats[type] = (stats[type] || 0) + 1;
    process.stdout.write(isKaraca ? 'K' : '+');
  } else {
    process.stdout.write('x');
  }

  await delay(220);

  if ((i + 1) % 50 === 0) process.stdout.write(`\n[${i+1}/${products.length}]\n`);
}

console.log('\n\n=== FİYAT GÜNCELLEME SONUCU ===');
console.log(`✅ Güncellenen: ${updated} | Atlanan: ${skipped} | Fiyatsız kalan: ${noPrice}`);
console.log('\nKategoriye göre:');
Object.entries(stats).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${k}: ${v} ürün`));
console.log('\nNot: K = Karaca +25%, + = Yeni fiyat eklendi');
