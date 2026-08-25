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

// ============================================================
// BAŞLIĞA BAKARAK DOĞRU KATEGORİ KÜMESI BELIRLEME
// Bu fonksiyon her ürün için hangi koleksiyonlarda OLMASI gerektiğine karar verir
// ============================================================
function trueBelongsTo(title, tags, type) {
  const t = title.toLowerCase();
  const tg = new Set(tags.toLowerCase().split(',').map(x => x.trim()).filter(Boolean));

  const belongs = new Set();

  // === KARACA ürünleri ===
  if (type === 'Karaca' || type === 'Vaisselle') {
    belongs.add('karaca');
    belongs.add('vaisselle');
    return belongs; // Karaca ürünleri sadece Karaca koleksiyonlarında
  }

  // === GERÇEK BEBEK MAMASI ===
  // Gerçek bebek ürünleri: Blédina, petits pots, lait infantile, snacks bebé markası
  const realBaby = ['bledina', 'blédina', 'petit pot', 'petits pots', 'lait infantile',
    'lait maternisé', 'lait 1er', 'lait 2ème', 'compote bébé', 'céréales bébé',
    'farines bébé', 'biscuits bébé', 'nestlé bébé', 'nestle bebe', 'snacks bébé marque'];
  if (realBaby.some(w => t.includes(w))) {
    belongs.add('alimentation_bebe');
    belongs.add('bebe');
  }

  // === HİJYEN & GÜZELLIK ===
  const hygieneWords = ['shampoing', 'shampooing', 'gel douche', 'savon ', 'dentifrice',
    'déodorant', 'deodorant', 'après shampoing', 'masque cheveux', 'soin cheveux',
    'crème corps', 'lotion', 'bain de bouche', 'brosse à dents', 'rasage',
    'mousse à raser', 'after shave', 'parfum ', 'eau de toilette',
    'bioderma', 'eyup sabri', 'tuncer', 'colgate', 'signal ', 'oral-b',
    'head shoulders', 'pantene', 'garnier', 'nivea ', 'dove ', 'l\'oreal',
    'gillette', 'wilkinson', 'veet ', 'nair ', 'fond de teint', 'mascara',
    'rouge à lèvres', 'vernis', 'tonique capillaire', 'huile cheveux'];
  if (hygieneWords.some(w => t.includes(w)) || type === 'Hygiène & Beauté') {
    belongs.add('hygiene_beaute');
    return belongs; // Hijyen ürünleri başka yere gitmesin
  }

  // === COUCHES / HİJYEN BEBEK ===
  if (t.includes('couche') || t.includes('pampers') || t.includes('huggies') ||
      t.includes('lingette bébé') || t.includes('changes bébé') || tg.has('couches')) {
    belongs.add('hygiene_bebe');
    belongs.add('couches');
    return belongs;
  }

  // === TEMİZLİK ÜRÜNLERİ ===
  const cleanWords = ['nettoyant', 'nettoyage', 'détergent', 'deterjan', 'lessive',
    'assouplissant', 'javel', 'désinfectant', 'spray nettoyant', 'liquide vaisselle',
    'lave-vaisselle', 'wc ', 'allesreiniger', 'camsil', 'ernet',
    'domestos', 'ajax ', 'mr propre', 'ariel ', 'skip ', 'persil ', 'dash ',
    'mir ', 'fairy ', 'paic ', 'toz deterjan', 'sivi deterjan', 'matik',
    'nettoyeur de joints', 'derz temizleyici', 'yuzey temizleyici',
    'temizleyici', 'temizleme', 'nettoyeur'];
  if (cleanWords.some(w => t.includes(w)) || type === 'Entretien Ménager' || type === 'Entretien') {
    belongs.add('entretien');
    if (tg.has('turque') || tg.has('belgique')) {
      if (tg.has('turque')) belongs.add('cuisine_turque');
    }
    return belongs;
  }

  // === ET ÜRÜNLERİ (başlıktan kesin tespit) ===
  const meatWords = ['agneau', 'veau', 'bœuf', 'boeuf', 'poulet', 'volaille', 'dinde',
    'mouton', 'lapin', 'canard', 'chèvre', 'chevre',
    'kebap', 'kebab', 'köfte', 'kofte', 'kofta', 'boulette', 'haché', 'hache',
    'biftek', 'entrecôte', 'entrecote', 'fajita', 'schnitzel',
    'côtelette', 'cotelette', 'jarret', 'épaule', 'epaule', 'cuisse',
    'collier', 'brochette', 'côte ', 'cote ', 'sauté', 'saute',
    'ciğer', 'cigeri', 'langue fumée', 'langue fumee'];
  const charWords = ['bacon', 'jambon', 'fumé veau', 'fumé poulet', 'saucisse',
    'saucisson', 'mortadelle', 'tranche délice', 'tranche bacon', 'veau fumé',
    'langue fumée', 'délice de poulet', 'délice de volaille'];

  const isMeat = meatWords.some(w => t.includes(w));
  const isCharcuterie = charWords.some(w => t.includes(w)) || tg.has('charcuterie');
  const isHalal = tg.has('halal');

  if (isMeat || isCharcuterie) {
    // ET ürünleri kesinlikle Alimentation Bébé'de OLMAYACAK
    if (isCharcuterie) {
      belongs.add('charcuterie_halal');
      belongs.add('charcuterie');
      belongs.add('boucherie');
    }
    if (isMeat) {
      if (isHalal) {
        belongs.add('viandes_halal');
        belongs.add('boucherie');
        belongs.add('halal_certifie');
        belongs.add('produits_frais');
      } else {
        belongs.add('viandes_conv');
      }
    }
    // Mezze mi et mi?
    const mezzeWords = ['salade', 'ganoush', 'haydari', 'fava', 'hummus', 'pilaki',
      'tzatziki', 'cacık', 'cacik', 'patlıcan', 'aubergine farci', 'dolma',
      'yaprak', 'börek meze', 'arnavut', 'barbunya', 'girit', 'çerkez',
      'purée pimentée', 'purée de', 'lentille boulette'];
    if (mezzeWords.some(w => t.includes(w))) {
      belongs.add('mezze'); // Mezze de ekle
    }
    belongs.add('cuisine_turque'); // Çoğu halal et Türk
    return belongs;
  }

  // === MEZZE (et olmayan) ===
  const mezzeOnlyWords = ['hummus', 'houmous', 'baba ganoush', 'haydari', 'fava ',
    'tarama', 'muhammara', 'tarator', 'cacık', 'tzatziki', 'pilaki',
    'aubergine farci', 'piment cerise farci', 'arap salade',
    'amerikan salade', 'girit kabağı', 'çerkez tavuğu', 'arnavut ciğeri',
    'barbunya', 'purée pimentée', 'lentille boulette'];
  if (mezzeOnlyWords.some(w => t.includes(w))) {
    belongs.add('mezze');
    belongs.add('cuisine_turque');
    return belongs;
  }

  // === BALIK ===
  const fishWords = ['thon ', 'saumon', 'sardine', 'maquereau', 'anchois', 'morue',
    'crevette', 'calamar', 'poulpe', 'ton balığı', 'hamsi', 'uskumru', 'levrek'];
  if (fishWords.some(w => t.includes(w))) {
    belongs.add('poissons');
    return belongs;
  }

  // === BOİSSONS ===
  const drinkWords = ['red bull', 'monster energy', 'ayran ', 'jus d\'', 'jus de',
    'nectar ', 'limonade', 'soda ', 'coca-cola', 'pepsi', 'sprite', 'fanta',
    'ice tea', 'thé glacé', 'smoothie ', 'eau minérale', 'eau de source',
    'kefir', 'lait uht', 'sirop ', 'boisson'];
  if (drinkWords.some(w => t.includes(w)) || type === 'Boissons' || type === 'Boissons Énergisantes') {
    belongs.add('boissons');
    if (t.includes('jus') || t.includes('nectar')) belongs.add('jus');
    return belongs;
  }

  // === ÇAY & KAHVE ===
  const teaWords = ['thé ', 'çay', 'tea ', 'infusion', 'tisane', 'camomille',
    'menthe poivrée', 'sahlep', 'salep ', 'café ', 'nescafé', 'coffee',
    'cappuccino', 'espresso'];
  if (teaWords.some(w => t.includes(w))) {
    belongs.add('thes');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === BİSKÜVİ & PASTANE ===
  const biscuitWords = ['biscuit', 'biskuvi', 'gâteau', 'gateau', 'kek ', 'cake ',
    'cookie', 'cracker', 'wafer', 'gaufrette', 'madeleine', 'donut',
    'ringo ', 'dankek', 'alpella', 'biskrem', 'albeni'];
  if (biscuitWords.some(w => t.includes(w))) {
    belongs.add('snacks');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    if (tg.has('italienne') && !t.includes('balconi')) belongs.add('cuisine_italienne');
    if (t.includes('balconi') || tg.has('balconi')) belongs.add('cuisine_italienne');
    return belongs;
  }

  // === ÇIKOLATA & TATLI ===
  const chocolateWords = ['chocolat', 'cikolata', 'çikolata', 'cacao', 'nutella',
    'pâte à tartiner', 'praline', 'noisette enrob'];
  if (chocolateWords.some(w => t.includes(w))) {
    belongs.add('pates_tartiner');
    belongs.add('snacks');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === ŞEKER & BONBON ===
  const candyWords = ['bonbon', 'candy', 'caramel', 'lollipop', 'sucette', 'halva',
    'helva', 'lokum', 'gélifié', 'gommeux', 'confiserie', 'bebeto ', 'haribo'];
  if (candyWords.some(w => t.includes(w))) {
    belongs.add('snacks');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === BAKLAVA & DOUCEURS ===
  if (t.includes('baklava') || t.includes('lokoum') || t.includes('lokum') ||
      tg.has('douceurs-orientales')) {
    belongs.add('baklava');
    belongs.add('snacks');
    return belongs;
  }

  // === KURUYEMIŞ ===
  const nutsWords = ['amande', 'noisette', 'noix ', 'pistache', 'cacahuète', 'cacahuete',
    'tournesol', 'graine de', 'yer fistik', 'findik', 'fıstık', 'badem', 'ceviz'];
  if (nutsWords.some(w => t.includes(w))) {
    belongs.add('fruits_secs');
    belongs.add('snacks');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === MAKARNA & TAHıL ===
  const pastaWords = ['pâtes ', 'macaroni', 'spaghetti', 'tagliatelle', 'fusilli',
    'farfalle', 'penne ', 'orzo ', 'sehriye', 'şehriye', 'dirsek ',
    'bulgur', 'couscous', 'riz ', 'pirinç', 'pirinc', 'baldo '];
  if (pastaWords.some(w => t.includes(w))) {
    belongs.add('pates');
    belongs.add('cereales');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    if (tg.has('italienne')) belongs.add('cuisine_italienne');
    return belongs;
  }

  // === YAĞLAR ===
  if (t.includes('huile d\'olive') || t.includes('zeytinyağı') || t.includes('zeytin yağ')) {
    belongs.add('huile_olive');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }
  if (t.includes('margarine') || t.includes('margarin') || t.includes('huile ') || t.includes('yağ ')) {
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === BAHARAT & BULYON ===
  const spiceWords = ['épice', 'epice', 'curcuma', 'cumin ', 'paprika', 'poivre ',
    'cannelle', 'cardamome', 'gingembre', 'sumac', 'zaatar', 'harissa',
    'bulyon', 'bouillon', 'cesnisi', 'harç', 'harcı', 'baharat'];
  if (spiceWords.some(w => t.includes(w))) {
    belongs.add('epices');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === SOS & KONDIMAN ===
  const sauceWords = ['sauce ', 'ketchup', 'mayonnaise', 'moutarde', 'vinaigre',
    'sirke', 'sirkesi', 'nar ekşisi', 'nar eksisi', 'sos ', 'tahini', 'tahin'];
  if (sauceWords.some(w => t.includes(w))) {
    belongs.add('sauces_cond');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === ÇORBA ===
  if (t.includes('corba') || t.includes('çorba') || t.includes('soupe') || t.includes('potage')) {
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === TOMATES ===
  if (t.includes('tomate') || t.includes('domates') || t.includes('salca') || t.includes('salça')) {
    belongs.add('tomates');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === SEBZE & KONSERVE ===
  const vegWords = ['asma yaprak', 'yaprak ', 'légumes', 'conserve', 'saumure',
    'concombre', 'poivron séché', 'courgette', 'aubergine séchée'];
  if (vegWords.some(w => t.includes(w))) {
    belongs.add('legumes_exotiques');
    if (tg.has('turque')) belongs.add('cuisine_turque');
    return belongs;
  }

  // === ASİYATİK ===
  if (tg.has('asiatique') || tg.has('ayam') || t.includes('curry') || t.includes('satay') ||
      t.includes('thai') || t.includes('wok') || t.includes('soja') || t.includes('ayam ')) {
    belongs.add('cuisine_asiatique');
    return belongs;
  }

  // === İTALYAN ===
  if (tg.has('italienne') || tg.has('balconi') || t.includes('balconi') || t.includes('barilla')) {
    belongs.add('cuisine_italienne');
    return belongs;
  }

  // === MAĞREB ===
  if (tg.has('maghreb') || tg.has('maghrebine') || tg.has('algerienne')) {
    belongs.add('cuisine_maghrebine');
    return belongs;
  }

  // === TÜRK (genel) ===
  if (tg.has('turque') || tg.has('bizim') || tg.has('ulker') || tg.has('eti')) {
    belongs.add('cuisine_turque');
  }

  // === EMBALLAGE HORECA ===
  if (tg.has('horeca') || tg.has('emballage-horeca') || tg.has('aluminium-film') ||
      tg.has('serviettes') || tg.has('emballage-boissons')) {
    belongs.add('horeca');
  }

  return belongs;
}

// Koleksiyon ID → anahtar eşlemesi
const COL_KEY_TO_ID = {
  karaca: 687037448517,
  vaisselle: 687026372933,
  alimentation_bebe: 686987411781,
  hygiene_beaute: 686919057733,
  hygiene_bebe: 687022866757,
  couches: 687022833989,
  entretien: 686920565061,
  viandes_halal: 686918402373,
  viandes_conv: 686918467909,
  boucherie: 686915518789,
  halal_certifie: 687825879365,
  charcuterie_halal: 687866380613,
  charcuterie: 686918598981,
  mezze: null, // Mezze koleksiyonu yoksa null
  produits_frais: 686918304069,
  poissons: 686918631749,
  boissons: 686918992197,
  jus: 686918828357,
  thes: 686918730053,
  snacks: 687825617221,
  pates_tartiner: 687825813829,
  fruits_secs: 687768404293,
  pates: 686917550405,
  cereales: 687026569541,
  huile_olive: 686916829509,
  epices: 686916337989,
  sauces_cond: 687825846597,
  tomates: 686917353797,
  legumes_exotiques: 686918074693,
  baklava: 686917583173,
  cuisine_turque: 686915551557,
  cuisine_italienne: 686915223877,
  cuisine_maghrebine: 686915420485,
  cuisine_asiatique: 686915289413,
  cuisine_espagnole: 686915486021,
  cuisine_portugaise: 686915322181,
  horeca: null,
  bebe: 686987411781,
};

// Hangi koleksiyonlarda yanlış ürün varsa çıkar
// Kontrol edilecek koleksiyonlar (dikkatli olunacaklar)
const COLLECTIONS_TO_AUDIT = [
  { id: 686987411781, key: 'alimentation_bebe', name: 'Alimentation Bébé' },
  { id: 686919057733, key: 'hygiene_beaute', name: 'Hygiène & Beauté' },
  { id: 687022866757, key: 'hygiene_bebe', name: 'Hygiène Bébé' },
  { id: 686920565061, key: 'entretien', name: 'Entretien' },
  { id: 686918402373, key: 'viandes_halal', name: 'Viandes Halal' },
  { id: 686915518789, key: 'boucherie', name: 'Boucherie' },
  { id: 687866380613, key: 'charcuterie_halal', name: 'Charcuterie Halal' },
  { id: 686918598981, key: 'charcuterie', name: 'Charcuterie' },
  { id: 686918992197, key: 'boissons', name: 'Boissons' },
  { id: 686918730053, key: 'thes', name: 'Thés' },
  { id: 687768404293, key: 'fruits_secs', name: 'Fruits Secs & Noix' },
  { id: 686916337989, key: 'epices', name: 'Épices' },
  { id: 686915551557, key: 'cuisine_turque', name: 'Cuisine Turque' },
  { id: 686915223877, key: 'cuisine_italienne', name: 'Cuisine Italienne' },
  { id: 686915420485, key: 'cuisine_maghrebine', name: 'Cuisine Maghrébine' },
  { id: 686915289413, key: 'cuisine_asiatique', name: 'Cuisine Asiatique' },
  { id: 686918631749, key: 'poissons', name: 'Poissons' },
  { id: 686916829509, key: 'huile_olive', name: 'Huile d\'Olive' },
  { id: 686917550405, key: 'pates', name: 'Pâtes' },
  { id: 687825813829, key: 'pates_tartiner', name: 'Pâtes à Tartiner' },
  { id: 686918304069, key: 'produits_frais', name: 'Produits Frais' },
];

// ============================================================
// ANA ÇALIŞMA
// ============================================================
console.log('Tüm ürünler yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,title,product_type,tags');
const productMap = new Map(products.map(p => [p.id, p]));
console.log(`${products.length} ürün\n`);

console.log('Tüm koleksiyon ilişkileri yükleniyor...');
const allCollects = await fetchAll('collects.json?fields=id,product_id,collection_id&limit=250');
console.log(`${allCollects.length} ilişki\n`);

// Koleksiyona göre grupla
const collectsByCol = new Map();
for (const c of allCollects) {
  if (!collectsByCol.has(c.collection_id)) collectsByCol.set(c.collection_id, []);
  collectsByCol.get(c.collection_id).push(c);
}

let totalRemoved = 0, totalErrors = 0;

for (const col of COLLECTIONS_TO_AUDIT) {
  const collects = collectsByCol.get(col.id) || [];
  if (collects.length === 0) continue;

  console.log(`\n📁 ${col.name} (${collects.length} ürün):`);
  let removed = 0, kept = 0;

  for (const c of collects) {
    const p = productMap.get(c.product_id);
    if (!p) continue;

    const correctCols = trueBelongsTo(p.title, p.tags || '', p.product_type || '');

    // Bu ürün bu koleksiyonda olmalı mı?
    const shouldBeHere = correctCols.has(col.key);

    if (!shouldBeHere) {
      // Çıkar
      const res = await api('DELETE', `collects/${c.id}.json`);
      if (res.ok || res.status === 200 || res.status === 204) {
        removed++;
        totalRemoved++;
        process.stdout.write('-');
        // Kısaca logla
        if (removed <= 5) {
          console.log(`\n  ❌ Çıkarıldı: "${p.title.substring(0, 55)}" → gerçek yer: [${[...correctCols].join(', ')}]`);
        }
      } else {
        totalErrors++;
        process.stdout.write('!');
      }
      await delay(200);
    } else {
      kept++;
    }
  }

  console.log(`\n  → Çıkarılan: ${removed} | Doğru yerde: ${kept}`);
}

console.log(`\n\n=== SONUÇ ===`);
console.log(`✅ Toplam çıkarılan: ${totalRemoved} | Hata: ${totalErrors}`);
console.log('Ürünler silinmedi — sadece yanlış koleksiyonlardan çıkarıldı.');
