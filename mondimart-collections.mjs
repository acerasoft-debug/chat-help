const TOKEN = process.env.SHOPIFY_TOKEN || 'BURAYA_TOKEN_YAZIN';
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const H = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

async function api(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, { method, headers: H, body: body ? JSON.stringify(body) : undefined });
      return await r.json();
    } catch(e) {
      if (i === 4) throw e;
      await new Promise(r => setTimeout(r, 2000 * i));
    }
  }
}

async function fetchAll(path) {
  let url = `${BASE}/${path}?limit=250`;
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

const COL = {
  ALIMENTATION_BEBE:    686987411781,
  ALTERNATIVES_VEG:     687030796613,
  BAKLAVA:              686917583173,
  BIO_CERTIFIE:         687037448517,
  BOISSONS:             686918992197,
  BOISSONS_VEG:         687030862149,
  BOUCHERIE:            686915518789,
  CEREALES_LEG:         687026569541,
  CHARCUTERIE:          686918598981,
  CHARCUTERIE_HALAL:    687866380613,
  COFFRETS:             687026405701,
  COFFRETS_CADEAUX:     687866478917,
  COFFRETS_EPICES:      687863824709,
  COMPLEMENTS:          687825977669,
  CONSERVES_VEGAN:      687029813573,
  CONSERVES_POISSONS:   687866609989,
  COUCHES:              687022833989,
  CUISINE_ASIATIQUE:    686915289413,
  CUISINE_ESPAGNOLE:    686915486021,
  CUISINE_ITALIENNE:    686915223877,
  CUISINE_MAGHREBINE:   686915420485,
  CUISINE_PORTUGAISE:   686915322181,
  CUISINE_TURQUE:       686915551557,
  ENTRETIEN:            686920565061,
  EPICERIE_FINE:        686917910853,
  EPICES:               686916337989,
  FROMAGES_AFFINES:     687859990853,
  FROMAGES_FRAIS:       686918172997,
  FRUITS_SECS:          687768404293,
  GASTRONOMIE_FR:       687026504005,
  HALAL_CERTIFIE:       687825879365,
  HUILE_OLIVE:          686916829509,
  HYGIENE_BEAUTE:       686919057733,
  HYGIENE_BEBE:         687022866757,
  JUS:                  686918828357,
  JUS_BEBE:             687826239813,
  KARACA:               687037448517,
  KARACA_ACCESSOIRES:   687036334405,
  KARACA_ASSIETTES:     687035384133,
  KARACA_BATTERIE:      687036727621,
  KARACA_BOLS:          687035482437,
  KARACA_CAFE:          687036105029,
  KARACA_COUVERTS:      687035842885,
  KARACA_DECO:          687037350213,
  KARACA_ELECTRO:       687037186373,
  KARACA_MACHINES:      687037219141,
  KARACA_PETIT_DEJ:     687036236101,
  KARACA_POELES:        687036924229,
  KARACA_SERVICES:      687034335557,
  KARACA_TAPIS:         687037415749,
  KARACA_TEXTILE:       687037284677,
  KARACA_USTENSILES:    687037088069,
  KARACA_V12:           687034696005,
  KARACA_V4:            687035285829,
  KARACA_V6:            687035220293,
  KARACA_VERRES:        687035580741,
  LAITS_BEBE:           687866446149,
  LEGUMES_EXOTIQUES:    686918074693,
  LEGUMES_FRAIS:        687821750597,
  PAIN:                 687825486149,
  PATES:                686917550405,
  PATES_TARTINER:       687825813829,
  PETIT_DEJ:            687866347845,
  PLATS_PREPARES:       687822963013,
  POISSONS:             686918631749,
  PRODUITS_FRAIS:       686918304069,
  PROMOTIONS:           687026471237,
  SANS_GLUTEN:          687037481285,
  SANS_SUCRE:           687850357061,
  SAUCES_COND:          687825617221,
  SNACKS:               687825617221,
  SNACKS_BEBE:          687827910981,
  SPORT:                687860711749,
  SUCRERIES_VEGAN:      687030894917,
  SURGELES:             687822766405,
  THES:                 686918730053,
  TOMATES:              686917353797,
  USTENSILES:           687026241861,
  VAISSELLE:            687026372933,
  VEGAN:                686915354949,
  VIANDES_CONV:         686918467909,
  VIANDES_HALAL:        686918402373,
  VINS:                 686918926661,
  OEUFS_LAITAGES:       687822635333,
};

function getCollections(p) {
  const tags = (p.tags || '').toLowerCase();
  const title = p.title.toLowerCase();
  const type = p.product_type || '';
  const tagSet = new Set(tags.split(',').map(t => t.trim()));
  const cols = new Set();

  const hasTag = t => tagSet.has(t) || tags.includes(t);

  // Ülke koleksiyonları
  if (hasTag('turque')) cols.add(COL.CUISINE_TURQUE);
  if (hasTag('italienne') && !hasTag('balconi')) cols.add(COL.CUISINE_ITALIENNE);
  if (hasTag('asiatique') || hasTag('ayam')) cols.add(COL.CUISINE_ASIATIQUE);
  if (hasTag('maghreb') || hasTag('maghrebine') || hasTag('algerienne')) cols.add(COL.CUISINE_MAGHREBINE);
  if (hasTag('espagnole') || hasTag('espagne')) cols.add(COL.CUISINE_ESPAGNOLE);
  if (hasTag('portugaise') || hasTag('portugal')) cols.add(COL.CUISINE_PORTUGAISE);
  if (hasTag('francaise') || hasTag('gastronomie-fr')) cols.add(COL.GASTRONOMIE_FR);

  // Hijyen & Bebek
  if (type === 'Hygiène & Beauté' || hasTag('hygiène') || hasTag('hygiene') || hasTag('beaute') ||
      title.includes('shampoing') || title.includes('gel douche') || title.includes('savon') ||
      title.includes('dentifrice') || title.includes('déodorant') || title.includes('eyup sabri') ||
      title.includes('tuncer') || title.includes('bioderma')) {
    cols.add(COL.HYGIENE_BEAUTE);
  }
  if (hasTag('couches') || hasTag('changes') || hasTag('hygiene-bebe') ||
      title.includes('couche') || title.includes('pampers') || title.includes('huggies')) {
    cols.add(COL.HYGIENE_BEBE);
    cols.add(COL.COUCHES);
  }
  if (type === 'Alimentation Bébé' && !hasTag('viande') && !hasTag('halal')) {
    cols.add(COL.ALIMENTATION_BEBE);
  }
  if (hasTag('jus-bebe') || hasTag('compote') ||
      (hasTag('alimentation-bebe') && (title.includes('jus') || title.includes('compote')))) {
    cols.add(COL.JUS_BEBE);
  }
  if (hasTag('lait-bebe') || hasTag('farine-bebe') || hasTag('laits-bebe')) {
    cols.add(COL.LAITS_BEBE);
  }
  if (hasTag('snacks-bebe') || hasTag('snack-bebe')) cols.add(COL.SNACKS_BEBE);

  // Et & Şarküteri
  if (hasTag('halal') && hasTag('viande')) {
    cols.add(COL.VIANDES_HALAL);
    cols.add(COL.BOUCHERIE);
    cols.add(COL.HALAL_CERTIFIE);
    cols.add(COL.PRODUITS_FRAIS);
  }
  if (hasTag('charcuterie') || type === 'Charcuterie Halal') {
    cols.add(COL.CHARCUTERIE_HALAL);
    cols.add(COL.CHARCUTERIE);
  }
  if (hasTag('viande') && !hasTag('halal')) cols.add(COL.VIANDES_CONV);

  // Boissons
  if (hasTag('boissons') || type === 'Boissons' || type === 'Boissons Énergisantes' || type === 'Boissons Laitières') {
    cols.add(COL.BOISSONS);
  }
  if (hasTag('jus') || title.includes('jus ') || title.includes(' jus')) cols.add(COL.JUS);
  if (hasTag('the') || hasTag('cafe') || title.includes('thé') || title.includes('café')) cols.add(COL.THES);
  if (hasTag('boissons-vegetales')) cols.add(COL.BOISSONS_VEG);

  // Tatlılar
  if (hasTag('chocolat') || hasTag('pate-a-tartiner')) cols.add(COL.PATES_TARTINER);
  if (hasTag('fruits-secs') || hasTag('fruits-secs-a-coque')) cols.add(COL.FRUITS_SECS);
  if (hasTag('baklava') || hasTag('douceurs-orientales')) cols.add(COL.BAKLAVA);

  // Makarna & Tahıl
  if (hasTag('pates') || hasTag('legumineuses') || type === 'Pâtes & Féculents') cols.add(COL.PATES);
  if (hasTag('cereales') || hasTag('cereales-feculents')) cols.add(COL.CEREALES_LEG);

  // Baharat & Sos
  if (hasTag('epices') || hasTag('epices-condiments') || hasTag('bouillon') || type === 'Épices & Condiments') {
    cols.add(COL.EPICES);
  }
  if (hasTag('sauces') || hasTag('condiment') || type === 'Condiments & Sauces') cols.add(COL.SAUCES_COND);
  if (hasTag('tomate') || title.includes('tomate')) cols.add(COL.TOMATES);

  // Yağlar
  if ((hasTag('huile') || hasTag('huiles')) && (title.includes('olive') || title.includes('zeytin'))) {
    cols.add(COL.HUILE_OLIVE);
  }

  // Temizlik
  if (type === 'Entretien Ménager' || hasTag('entretien') || hasTag('entretien-menager') || hasTag('produits-de-nettoyage')) {
    cols.add(COL.ENTRETIEN);
  }

  // Sebze & Konserve
  if (hasTag('conserves') || hasTag('legumes-en-saumure') || type === 'Légumes & Conserves') {
    cols.add(COL.LEGUMES_EXOTIQUES);
  }
  if (hasTag('poisson') || hasTag('conserves-poissons')) cols.add(COL.POISSONS);

  // Bio & Vegan
  if (hasTag('bio') || hasTag('vegan') || hasTag('biologique') || type === 'Bio & Vegan') {
    cols.add(COL.BIO_CERTIFIE);
    cols.add(COL.VEGAN);
  }
  if (hasTag('sans-gluten') || hasTag('gluten-free')) cols.add(COL.SANS_GLUTEN);

  // Karaca
  if (type === 'Karaca' || type === 'Vaisselle') {
    cols.add(COL.KARACA);
    if (title.includes('assiette')) cols.add(COL.KARACA_ASSIETTES);
    if (title.includes('verre') || title.includes('kadeh')) cols.add(COL.KARACA_VERRES);
    if (title.includes('casserole') || title.includes('poêle') || title.includes('batterie')) cols.add(COL.KARACA_BATTERIE);
    if (title.includes('café') || title.includes('thé') || title.includes('théière')) cols.add(COL.KARACA_CAFE);
    if (title.includes('airfryer') || title.includes('robot') || title.includes('électr')) cols.add(COL.KARACA_ELECTRO);
    if (title.includes('bol') || title.includes('saladier')) cols.add(COL.KARACA_BOLS);
    if (title.includes('couvert') || title.includes('cuillère') || title.includes('fourchette')) cols.add(COL.KARACA_COUVERTS);
  }

  // Ürünler frais ise
  if (hasTag('frais') && !hasTag('viande')) cols.add(COL.PRODUITS_FRAIS);

  // Snacks
  if (hasTag('snacks') || hasTag('bonbons') || hasTag('confiserie')) cols.add(COL.SNACKS);

  // Petit déjeuner
  if (hasTag('petit-dejeuner') || title.includes('miel') || title.includes('confiture')) {
    cols.add(COL.PETIT_DEJ);
  }

  // İndirimli ürünler
  const price = parseFloat(p.variants?.[0]?.price || 0);
  const compare = parseFloat(p.variants?.[0]?.compare_at_price || 0);
  if (compare > 0 && compare > price) cols.add(COL.PROMOTIONS);

  return [...cols];
}

// Ana çalışma
console.log('Ürünler yükleniyor...');
const products = await fetchAll('products.json?fields=id,title,product_type,tags,variants');
console.log(`${products.length} ürün yüklendi\n`);

console.log('Mevcut koleksiyon ilişkileri yükleniyor...');
const existingCollects = await fetchAll('collects.json?fields=product_id,collection_id');
const existingSet = new Set(existingCollects.map(c => `${c.product_id}-${c.collection_id}`));
console.log(`${existingCollects.length} mevcut ilişki yüklendi\n`);

let added = 0, skipped = 0, errors = 0;
const stats = {};

console.log('Koleksiyonlara ekleniyor...\n');
for (let i = 0; i < products.length; i++) {
  const p = products[i];
  const targetCols = getCollections(p);

  for (const colId of targetCols) {
    const key = `${p.id}-${colId}`;
    if (existingSet.has(key)) { skipped++; continue; }

    const res = await api('POST', 'collects.json', { collect: { product_id: p.id, collection_id: colId } });
    if (res.collect) {
      added++;
      existingSet.add(key);
      const colName = Object.entries(COL).find(([,v]) => v === colId)?.[0] || colId;
      stats[colName] = (stats[colName] || 0) + 1;
      process.stdout.write('+');
    } else {
      errors++;
      process.stdout.write('x');
    }
    await delay(220);
  }

  if ((i + 1) % 50 === 0) process.stdout.write(`[${i+1}/${products.length}]\n`);
}

console.log('\n\n=== SONUÇ ===');
console.log(`✅ Eklenen: ${added} | Zaten var: ${skipped} | Hata: ${errors}`);
console.log('\nKoleksiyona göre:');
Object.entries(stats).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${k}: +${v}`));
