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

// Koleksiyon ID'leri
const COL = {
  ALIMENTATION_BEBE:  686987411781,
  BAKLAVA:            686917583173,
  BOISSONS:           686918992197,
  BOUCHERIE:          686915518789,
  CEREALES_LEG:       687026569541,
  CHARCUTERIE:        686918598981,
  CHARCUTERIE_HALAL:  687866380613,
  COUCHES:            687022833989,
  CUISINE_ASIATIQUE:  686915289413,
  CUISINE_ESPAGNOLE:  686915486021,
  CUISINE_ITALIENNE:  686915223877,
  CUISINE_MAGHREBINE: 686915420485,
  CUISINE_PORTUGAISE: 686915322181,
  CUISINE_TURQUE:     686915551557,
  ENTRETIEN:          686920565061,
  EPICES:             686916337989,
  FRUITS_SECS:        687768404293,
  GASTRONOMIE_FR:     687026504005,
  HALAL_CERTIFIE:     687825879365,
  HUILE_OLIVE:        686916829509,
  HYGIENE_BEAUTE:     686919057733,
  HYGIENE_BEBE:       687022866757,
  JUS:                686918828357,
  JUS_BEBE:           687826239813,
  KARACA:             687037448517,
  KARACA_ASSIETTES:   687035384133,
  KARACA_BATTERIE:    687036727621,
  KARACA_BOLS:        687035482437,
  KARACA_CAFE:        687036105029,
  KARACA_COUVERTS:    687035842885,
  KARACA_DECO:        687037350213,
  KARACA_ELECTRO:     687037186373,
  KARACA_POELES:      687036924229,
  KARACA_SERVICES:    687034335557,
  KARACA_VERRES:      687035580741,
  LAITS_BEBE:         687866446149,
  LEGUMES_EXOTIQUES:  686918074693,
  PATES:              686917550405,
  PATES_TARTINER:     687825813829,
  PETIT_DEJ:          687866347845,
  POISSONS:           686918631749,
  PRODUITS_FRAIS:     686918304069,
  PROMOTIONS:         687026471237,
  SANS_GLUTEN:        687037481285,
  SAUCES_COND:        687825846597,
  SNACKS:             687825617221,
  SNACKS_BEBE:        687827910981,
  THES:               686918730053,
  TOMATES:            686917353797,
  VAISSELLE:          687026372933,
  VEGAN:              686915354949,
  VIANDES_CONV:       686918467909,
  VIANDES_HALAL:      686918402373,
};

// === AKILLI SINIFLANDIRMA ===
function analyze(p) {
  const title = p.title.toLowerCase();
  const body = (p.body_html || '').replace(/<[^>]+>/g, ' ').toLowerCase();
  const text = title + ' ' + body;
  const existingTags = new Set((p.tags || '').toLowerCase().split(',').map(t => t.trim()).filter(Boolean));

  const tagsToAdd = new Set();
  const tagsToRemove = new Set();
  let newType = null;
  const cols = new Set();

  // ============================================================
  // ÜLKE TESPİTİ
  // ============================================================
  const turkishBrands = ['bizim', 'ulker', 'ülker', 'eti ', 'eker ', 'bingo', 'berrak', 'albeni',
    'biskrem', 'baltat', 'kenton', 'bagdat', 'divan', 'taris', 'komili', 'aydede',
    'torku', 'pinar', 'pınar', 'mis ', 'bebeto', 'balcali', 'ulker', 'cipso',
    'ringo', 'dankek', 'mis süt', 'sek ', 'eyup sabri', 'tuncer', 'bingo'];
  const turkishWords = ['türk', 'turque', 'istanbul', 'izmir', 'ankara', 'anadolu',
    'sehriye', 'şehriye', 'corbasi', 'çorbası', 'bulgur', 'ayran', 'baldo', 'pirinç',
    'kofte', 'kofta', 'kebap', 'kebab', 'lokum', 'helva', 'tahin', 'tarator',
    'sucuk', 'pastirma', 'pastırma', 'acuka', 'biber', 'salça', 'salca',
    'muhallebi', 'sütlaç', 'kazandibi', 'baklava', 'börek', 'borek',
    'çay', ' cay ', 'çikolata', 'cikolata', 'fistik', 'fıstık', 'findik', 'fındık',
    'hazelnut turkish', 'türkish', 'turkish'];

  const italianBrands = ['balconi', 'barilla', 'mutti', 'divella', 'de cecco', 'rummo', 'voiello'];
  const italianWords = ['italiano', 'italiana', 'tiramisu', 'espresso', 'pesto', 'focaccia',
    'gnocchi', 'risotto', 'polenta', 'parmesan', 'parmigiano', 'pecorino', 'mozzarella'];

  const asianBrands = ['ayam', 'blue dragon', 'kikkoman', 'tiger brand', 'mama noodle'];
  const asianWords = ['curry', 'thai ', 'satay', 'wok ', 'soja', 'miso', 'ramen',
    'sushi', 'kimchi', 'sriracha', 'coconut milk', 'lait coco', 'tamari',
    'pad thai', 'nasi', 'sambal', 'tempura'];

  const maghrebWords = ['couscous', 'harissa', 'ras el hanout', 'merguez', 'chorba',
    'maghreb', 'algeri', 'tunisi', 'marocain', 'chermoula', 'makroud',
    'msemen', 'batbout', 'briouats', 'bastilla'];

  const portugueseWords = ['portuguesa', 'portugaise', 'bacalhau', 'pastel de nata', 'porto'];
  const spanishWords = ['española', 'espagnole', 'chorizo', 'manchego', 'gazpacho', 'paella'];

  // Ülke tespiti
  const isTurkish = turkishBrands.some(b => text.includes(b)) || turkishWords.some(w => text.includes(w));
  const isItalian = italianBrands.some(b => text.includes(b)) || italianWords.some(w => text.includes(w));
  const isAsian = asianBrands.some(b => text.includes(b)) || asianWords.some(w => text.includes(w));
  const isMaghreb = maghrebWords.some(w => text.includes(w));
  const isPortuguese = portugueseWords.some(w => text.includes(w));
  const isSpanish = spanishWords.some(w => text.includes(w));

  if (isTurkish && !existingTags.has('turque')) tagsToAdd.add('turque');
  if (isItalian && !existingTags.has('italienne')) tagsToAdd.add('italienne');
  if (isAsian && !existingTags.has('asiatique')) tagsToAdd.add('asiatique');
  if (isMaghreb && !existingTags.has('maghrebine')) tagsToAdd.add('maghrebine');
  if (isPortuguese && !existingTags.has('portugaise')) tagsToAdd.add('portugaise');
  if (isSpanish && !existingTags.has('espagnole')) tagsToAdd.add('espagnole');

  if (isTurkish) cols.add(COL.CUISINE_TURQUE);
  if (isItalian && !existingTags.has('balconi') && !text.includes('balconi')) cols.add(COL.CUISINE_ITALIENNE);
  if (isAsian) cols.add(COL.CUISINE_ASIATIQUE);
  if (isMaghreb) cols.add(COL.CUISINE_MAGHREBINE);
  if (isPortuguese) cols.add(COL.CUISINE_PORTUGAISE);
  if (isSpanish) cols.add(COL.CUISINE_ESPAGNOLE);

  // ============================================================
  // KATEGORİ TESPİTİ
  // ============================================================

  // --- HİJYEN & GÜZELLIK ---
  const hygieneKeywords = ['shampoing', 'shampooing', 'gel douche', 'savon ', 'soap', 'dentifrice',
    'déodorant', 'deodorant', 'crème corps', 'lotion corps', 'after shave', 'rasage',
    'brosse à dents', 'fil dentaire', 'bain de bouche', 'mousse à raser',
    'eye', 'serum', 'fond de teint', 'rouge à lèvres', 'mascara', 'parfum',
    'eau de toilette', 'bioderma', 'eyup sabri tuncer', 'garnier', 'nivea',
    'dove ', 'head shoulders', 'pantene', 'l\'oreal', 'olay ', 'colgate',
    'signal ', 'oral-b', 'gillette', 'wilkinson', 'veet ', 'nair ',
    'tonique capillaire', 'après shampoing', 'masque cheveux', 'huile cheveux',
    'soin cheveux', 'conditioner'];

  const isHygiene = hygieneKeywords.some(k => text.includes(k)) ||
    p.product_type === 'Hygiène & Beauté';

  if (isHygiene) {
    if (!existingTags.has('hygiène')) tagsToAdd.add('hygiène');
    if (existingTags.has('epicerie-fine')) tagsToRemove.add('epicerie-fine');
    newType = 'Hygiène & Beauté';
    cols.add(COL.HYGIENE_BEAUTE);
  }

  // --- COUCHES / HİJYEN BEBEK ---
  const bebeHygieneWords = ['couche', 'couches', 'changes bébé', 'pampers', 'huggies',
    'lingette bébé', 'coton bébé', 'crème bébé', 'lait bébé corps'];
  if (bebeHygieneWords.some(k => text.includes(k))) {
    cols.add(COL.HYGIENE_BEBE);
    cols.add(COL.COUCHES);
    newType = 'Hygiène Bébé';
  }

  // --- ET & KASAP ---
  const meatWords = ['agneau', 'veau', 'bœuf', 'boeuf', 'poulet', 'volaille', 'dinde',
    'mouton', 'chèvre', 'chevre', 'lapin', 'canard', 'oie',
    'kebap', 'kebab', 'köfte', 'kofte', 'kofta', 'boulette', 'haché', 'hache',
    'biftek', 'entrecôte', 'entrecote', 'fajita', 'schnitzel',
    'côtelette', 'cotelette', 'jarret', 'épaule', 'epaule', 'cuisse',
    'collier', 'sauté', 'saute', 'brochette', 'merguez', 'saucisse'];

  const charWords = ['bacon', 'jambon', 'fumé', 'fume', 'charcuterie', 'saucisson',
    'mortadelle', 'salami', 'pastrami', 'langue fumée', 'veau fumé'];

  const isHalal = existingTags.has('halal') || text.includes('halal');
  const isMeat = meatWords.some(w => text.includes(w)) || existingTags.has('viande');
  const isCharcuterie = charWords.some(w => text.includes(w)) || existingTags.has('charcuterie');

  if (isMeat && !isHygiene) {
    if (!existingTags.has('viande')) tagsToAdd.add('viande');

    // Gerçek bebek yiyeceği değilse alimentation-bebe tag'ini kaldır
    if (existingTags.has('alimentation-bebe')) {
      tagsToRemove.add('alimentation-bebe');
    }
    // bebe tag'i var ama et ürünüyse kaldırma (bebe kuzu = genç kuzu)
    // sadece product_type'ı düzelt
    if (p.product_type === 'Alimentation Bébé') {
      if (text.includes('agneau') || existingTags.has('agneau')) {
        newType = "Viande d'Agneau";
      } else if (text.includes('poulet') || text.includes('volaille') || text.includes('dinde')) {
        newType = 'Volaille Halal';
      } else if (isCharcuterie) {
        newType = 'Charcuterie Halal';
      } else {
        newType = 'Viandes Halal';
      }
    }

    if (isHalal) {
      if (!existingTags.has('halal')) tagsToAdd.add('halal');
      cols.add(COL.VIANDES_HALAL);
      cols.add(COL.BOUCHERIE);
      cols.add(COL.HALAL_CERTIFIE);
      cols.add(COL.PRODUITS_FRAIS);
    } else {
      cols.add(COL.VIANDES_CONV);
    }
  }

  if (isCharcuterie) {
    if (!existingTags.has('charcuterie')) tagsToAdd.add('charcuterie');
    cols.add(COL.CHARCUTERIE_HALAL);
    cols.add(COL.CHARCUTERIE);
    if (!newType) newType = 'Charcuterie Halal';
  }

  // --- MEZZE & SPECİALİTES ---
  const mezzeWords = ['mezze', 'hummus', 'houmous', 'baba ganoush', 'haydari', 'fava ',
    'tarama', 'tarator', 'muhammara', 'cacık', 'cacik', 'tzatziki',
    'dolma', 'sarma', 'yaprak', 'börek', 'borek', 'sigara',
    'arnavut ciğeri', 'pilaki', 'barbunya', 'patlıcan', 'aubergine farci',
    'piment cerise', 'arap salade', 'amerikan salade', 'girit',
    'çerkez tavuğu', 'cerkez'];
  if (mezzeWords.some(w => text.includes(w)) && !newType) {
    if (!existingTags.has('mezze')) tagsToAdd.add('mezze');
    if (!existingTags.has('specialites')) tagsToAdd.add('specialites');
    newType = 'Mezze & Spécialités';
  }

  // --- BOİSSONS ---
  const drinkWords = ['jus ', 'juice', 'boisson', 'eau ', 'limonade', 'soda', 'cola',
    'sprite', 'fanta', 'red bull', 'monster', 'ayran', 'kefir', 'lait uht',
    'nectar', 'sirop', 'smoothie', 'ice tea', 'thé glacé'];
  if (drinkWords.some(w => text.includes(w)) && !isMeat && !isHygiene) {
    if (!existingTags.has('boissons')) tagsToAdd.add('boissons');
    cols.add(COL.BOISSONS);
    if (text.includes('jus') || text.includes('nectar')) cols.add(COL.JUS);
    if (!newType) newType = 'Boissons';
  }

  // --- ÇAY & KAHVE ---
  const teaCoffeeWords = ['thé ', 'tea ', 'çay', 'café ', 'coffee', 'nescafé', 'nescafe',
    'cappuccino', 'expresso', 'espresso', 'infusion', 'tisane', 'camomille',
    'menthe', 'sahlep', 'salep', 'mochaccino'];
  if (teaCoffeeWords.some(w => text.includes(w))) {
    cols.add(COL.THES);
    if (!newType) newType = 'Thés & Cafés';
  }

  // --- HİJYEN BEBEK MAMASI (GERÇEK) ---
  const realBabyWords = ['blédina', 'bledina', 'nestlé bébé', 'nestle bebe', 'petits pots',
    'lait 1er âge', 'lait 2ème âge', 'lait infantile', 'lait maternisé',
    'compote bébé', 'purée bébé', 'biscuits bébé', 'céréales bébé',
    'farines bébé', 'porridge bébé', 'petit pot'];
  if (realBabyWords.some(w => text.includes(w))) {
    if (!existingTags.has('alimentation-bebe')) tagsToAdd.add('alimentation-bebe');
    cols.add(COL.ALIMENTATION_BEBE);
    newType = 'Alimentation Bébé';
  }

  // --- HİJYEN ---
  const cleaningWords = ['nettoyant', 'nettoyage', 'détergent', 'deterjan', 'lessive',
    'assouplissant', 'javel', 'désinfectant', 'désodorisant', 'spray nettoyant',
    'liquide vaisselle', 'lave-vaisselle', 'wc ', 'toilet', 'salle de bain',
    'vitre', 'sols ', 'multi-surfaces', 'allesreiniger', 'camsil', 'ernet',
    'domestos', 'ajax ', 'mr propre', 'flash ', 'cillit', 'ariel ', 'skip ',
    'persil ', 'dash ', 'mir ', 'fairy ', 'paic '];
  if (cleaningWords.some(w => text.includes(w)) && !isHygiene) {
    if (!existingTags.has('entretien')) tagsToAdd.add('entretien');
    if (!existingTags.has('entretien-menager')) tagsToAdd.add('entretien-menager');
    cols.add(COL.ENTRETIEN);
    if (!newType) newType = 'Entretien Ménager';
  }

  // --- BAHARAT & SOS ---
  const spiceWords = ['épice', 'epice', 'curcuma', 'cumin', 'coriandre', 'paprika',
    'poivre ', 'cannelle', 'cardamome', 'gingembre', 'sumac', 'zaatar',
    'za\'atar', 'ras el hanout', 'harissa', 'bouillon', 'bouyon',
    'condiment', 'cesnisi', 'harç'];
  const sauceWords = ['sauce ', 'ketchup', 'mayonnaise', 'moutarde', 'vinaigre',
    'huile de sésame', 'sauce soja', 'nuoc mam', 'tabasco', 'pesto',
    'tahini', 'tahin', 'nar ekşisi', 'nar eksisi', 'sirkesi'];
  if (spiceWords.some(w => text.includes(w))) {
    if (!existingTags.has('epices')) tagsToAdd.add('epices');
    cols.add(COL.EPICES);
    if (!newType) newType = 'Épices & Condiments';
  }
  if (sauceWords.some(w => text.includes(w))) {
    cols.add(COL.SAUCES_COND);
    if (!newType) newType = 'Condiments & Sauces';
  }

  // --- BİSKÜVİ & TATLILAR ---
  const biscuitWords = ['biscuit', 'biskuvi', 'biskuit', 'gâteau', 'gateau', 'cake',
    'cookie', 'cracker', 'wafer', 'gaufrette', 'madeleine', 'brownie',
    'donut', 'kek ', 'pasta '];
  const sweetWords = ['bonbon', 'candy', 'caramel', 'réglisse', 'lollipop', 'sucette',
    'dragée', 'guimauve', 'marshmallow', 'halva', 'helva', 'lokum',
    'confiserie', 'gélifié', 'gommeux'];
  const chocWords = ['chocolat', 'cikolata', 'cacao', 'nutella', 'praline', 'noisette chocolat',
    'pâte à tartiner', 'pate a tartiner'];
  if (biscuitWords.some(w => text.includes(w)) && !isMeat && !isHygiene) {
    cols.add(COL.SNACKS);
    if (!newType) newType = 'Biscuits & Gâteaux';
  }
  if (sweetWords.some(w => text.includes(w)) && !isMeat) {
    cols.add(COL.SNACKS);
    if (!newType) newType = 'Confiserie & Bonbons';
  }
  if (chocWords.some(w => text.includes(w)) && !isMeat) {
    cols.add(COL.PATES_TARTINER);
    if (!newType) newType = 'Chocolats & Pâtes à Tartiner';
  }

  // --- KURUYEMIS ---
  const nutsWords = ['amande', 'noisette', 'noix', 'pistache', 'cacahuète', 'cacahuete',
    'tournesol', 'pépite', 'pepite', 'graine', 'yer fistik', 'findik',
    'fıstık', 'badem', 'ceviz', 'antep', 'fruits secs'];
  if (nutsWords.some(w => text.includes(w)) && !isMeat) {
    if (!existingTags.has('fruits-secs')) tagsToAdd.add('fruits-secs');
    cols.add(COL.FRUITS_SECS);
    cols.add(COL.SNACKS);
    if (!newType) newType = 'Fruits Secs & Noix';
  }

  // --- MAKARNA & TAHIL ---
  const pastaWords = ['pâtes ', 'pasta ', 'macaroni', 'spaghetti', 'tagliatelle', 'fusilli',
    'farfalle', 'penne ', 'linguine', 'orzo', 'sehriye', 'şehriye',
    'riz ', 'pirinç', 'pirinc', 'baldo', 'bulgur', 'couscous', 'polenta',
    'farine ', 'un ', 'nişasta', 'nisasta'];
  if (pastaWords.some(w => text.includes(w)) && !isMeat) {
    if (!existingTags.has('pates')) tagsToAdd.add('pates');
    cols.add(COL.PATES);
    cols.add(COL.CEREALES_LEG);
    if (!newType) newType = 'Pâtes & Féculents';
  }

  // --- YAĞLAR ---
  const oilWords = ['huile d\'olive', 'huile olive', 'zeytinyağı', 'zeytinyagi', 'zeytin yağ'];
  const fatWords = ['huile ', 'margarine', 'beurre ', 'yağ ', 'tereyağ'];
  if (oilWords.some(w => text.includes(w))) {
    cols.add(COL.HUILE_OLIVE);
    if (!newType) newType = 'Huiles & Matières Grasses';
  } else if (fatWords.some(w => text.includes(w)) && !isMeat && !isHygiene) {
    if (!newType) newType = 'Huiles & Matières Grasses';
  }

  // --- TOMATES ---
  if (text.includes('tomate') || text.includes('domates')) {
    cols.add(COL.TOMATES);
  }

  // --- POİSSON ---
  const fishWords = ['poisson', 'saumon', 'thon ', 'sardine', 'maquereau', 'anchois',
    'morue', 'cabillaud', 'crevette', 'calamar', 'poulpe', 'balık', 'balik',
    'ton balığı', 'hamsi', 'uskumru', 'levrek', 'çipura'];
  if (fishWords.some(w => text.includes(w))) {
    if (!existingTags.has('poisson')) tagsToAdd.add('poisson');
    cols.add(COL.POISSONS);
    if (!newType) newType = 'Poissons';
  }

  // --- LEGUMES & CONSERVES ---
  const vegetableWords = ['légume', 'legume', 'concombre', 'poivron', 'aubergine',
    'courgette', 'épinard', 'spinach', 'pois chiche', 'lentille',
    'haricot', 'fève', 'feve', 'saumure', 'mariné', 'marine',
    'asma yaprak', 'conserve', 'bocal'];
  if (vegetableWords.some(w => text.includes(w)) && !isMeat) {
    cols.add(COL.LEGUMES_EXOTIQUES);
    if (!newType) newType = 'Légumes & Conserves';
  }

  // --- FRAIS ---
  if (existingTags.has('frais') && !isMeat) {
    cols.add(COL.PRODUITS_FRAIS);
  }

  // --- BIO / VEGAN ---
  if (text.includes('bio ') || text.includes('biologique') || text.includes('organic') ||
      existingTags.has('bio') || existingTags.has('vegan')) {
    cols.add(COL.VEGAN);
  }
  if (text.includes('sans gluten') || text.includes('gluten free') || text.includes('gluten-free')) {
    cols.add(COL.SANS_GLUTEN);
  }

  // --- KARACA ---
  if (p.product_type === 'Karaca' || p.product_type === 'Vaisselle') {
    cols.add(COL.KARACA);
    cols.add(COL.VAISSELLE);
    if (title.includes('assiette')) cols.add(COL.KARACA_ASSIETTES);
    if (title.includes('verre') || title.includes('kadeh')) cols.add(COL.KARACA_VERRES);
    if (title.includes('batterie') || title.includes('casserole') || title.includes('tencere')) cols.add(COL.KARACA_BATTERIE);
    if (title.includes('poêle') || title.includes('tava')) cols.add(COL.KARACA_POELES);
    if (title.includes('café') || title.includes('thé') || title.includes('théière') || title.includes('çay')) cols.add(COL.KARACA_CAFE);
    if (title.includes('airfryer') || title.includes('robot') || title.includes('machine')) cols.add(COL.KARACA_ELECTRO);
    if (title.includes('bol') || title.includes('kase')) cols.add(COL.KARACA_BOLS);
    if (title.includes('couvert') || title.includes('cuillère') || title.includes('fourchette')) cols.add(COL.KARACA_COUVERTS);
    if (title.includes('service') || title.includes('vaisselle')) cols.add(COL.KARACA_SERVICES);
    newType = null; // Karaca type'ını değiştirme
  }

  // --- PROMOTIONS ---
  const price = parseFloat(p.variants?.[0]?.price || 0);
  const compare = parseFloat(p.variants?.[0]?.compare_at_price || 0);
  if (compare > 0 && compare > price) cols.add(COL.PROMOTIONS);

  return { tagsToAdd, tagsToRemove, newType, cols };
}

// === ANA ÇALIŞMA ===
console.log('Tüm ürünler yükleniyor...');
const products = await fetchAll('products.json?limit=250&fields=id,title,product_type,tags,body_html,variants');
console.log(`${products.length} ürün yüklendi\n`);

console.log('Mevcut koleksiyon ilişkileri yükleniyor...');
const existingCollects = await fetchAll('collects.json?fields=product_id,collection_id&limit=250');
const existingSet = new Set(existingCollects.map(c => `${c.product_id}-${c.collection_id}`));
console.log(`${existingCollects.length} mevcut ilişki\n`);

let tagFixed = 0, typeFixed = 0, colAdded = 0, errors = 0;
const typeStats = {}, colStats = {};

console.log('Analiz ve düzeltme başlıyor...\n');

for (let i = 0; i < products.length; i++) {
  const p = products[i];
  const { tagsToAdd, tagsToRemove, newType, cols } = analyze(p);

  const currentTags = new Set((p.tags || '').split(',').map(t => t.trim()).filter(Boolean));
  let needsUpdate = false;

  // Tag güncelleme
  const updatedTags = new Set(currentTags);
  for (const t of tagsToAdd) {
    if (!updatedTags.has(t)) { updatedTags.add(t); needsUpdate = true; }
  }
  for (const t of tagsToRemove) {
    if (updatedTags.has(t)) { updatedTags.delete(t); needsUpdate = true; }
  }

  // Product type güncelleme
  const needsTypeUpdate = newType && newType !== p.product_type && p.product_type !== 'Karaca';

  if (needsUpdate || needsTypeUpdate) {
    const updateBody = { product: { id: p.id } };
    if (needsUpdate) updateBody.product.tags = [...updatedTags].join(', ');
    if (needsTypeUpdate) updateBody.product.product_type = newType;

    const res = await api('PUT', `products/${p.id}.json`, updateBody);
    if (res.product) {
      if (needsUpdate) tagFixed++;
      if (needsTypeUpdate) {
        typeFixed++;
        typeStats[newType] = (typeStats[newType] || 0) + 1;
      }
      process.stdout.write('T');
    } else {
      errors++;
      process.stdout.write('!');
    }
    await delay(250);
  }

  // Koleksiyon ekleme
  for (const colId of cols) {
    const key = `${p.id}-${colId}`;
    if (existingSet.has(key)) continue;

    const res = await api('POST', 'collects.json', { collect: { product_id: p.id, collection_id: colId } });
    if (res.collect) {
      colAdded++;
      existingSet.add(key);
      const colName = Object.entries(COL).find(([,v]) => v === colId)?.[0] || colId;
      colStats[colName] = (colStats[colName] || 0) + 1;
      process.stdout.write('+');
    } else {
      process.stdout.write('x');
    }
    await delay(200);
  }

  if ((i + 1) % 50 === 0) process.stdout.write(`\n[${i+1}/${products.length}]\n`);
}

console.log('\n\n=== SONUÇ ===');
console.log(`Tag/Type düzeltilen: ${tagFixed} | Tip değiştirilen: ${typeFixed} | Koleksiyona eklenen: ${colAdded} | Hata: ${errors}`);

if (Object.keys(typeStats).length) {
  console.log('\nTip değişiklikleri:');
  Object.entries(typeStats).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${k}: ${v}`));
}
if (Object.keys(colStats).length) {
  console.log('\nKoleksiyona eklemeler:');
  Object.entries(colStats).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${k}: +${v}`));
}
