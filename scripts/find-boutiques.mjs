#!/usr/bin/env node
/**
 * VestraSales — Çok markalı butik bulucu (GitHub Actions'ta çalışır, sunucuda DEĞİL).
 *
 * NE YAPAR
 *   1) ARAMA   : premium marka ÇİFTLERİNİ ("Dsquared2" "Balmain" boutique …) 14 dilde
 *                arama motorlarına sorar (Bing HTML → DuckDuckGo HTML → Brave API).
 *                İki tasarımcı markasını birlikte listeleyen site, tanımı gereği çok
 *                markalı perakendecidir — OSM'nin "shop=clothes" etiketinden çok daha
 *                isabetli (OSM'de %4 uygunluk ölçülmüştü).
 *   2) ELEME   : pazar yeri / zincir / büyük mağaza / markanın kendi sitesi / sosyal /
 *                dizin alan adları daha SİTEYE GİRMEDEN elenir.
 *   3) İNCELEME: ana sayfa + iletişim/künye/yasal/gizlilik/marka sayfaları (en çok 8)
 *                okunur: hangi premium markaları taşıyor, ayakkabı/iç çamaşırı/toptancı
 *                mı, ülkesi ne, YAYINLANMIŞ e-postası ne.
 *   4) E-POSTA : yalnızca sitede GERÇEKTEN yayınlanmış adres alınır — tahmin YOK
 *                (info@ uydurmak hard-bounce demek). mailto:, Cloudflare data-cfemail,
 *                "(at)/(dot)" gizlemeleri, HTML entity'leri, JSON-LD çözülür. Adres
 *                sitenin kendi alan adında ya da bilinen ücretsiz sağlayıcıda olmalı
 *                (künyedeki web ajansı adresi alınmaz). MX kaydı doğrulanır.
 *   5) ÇIKTI   : out/leads.json (sunucuya yazılacak), out/report.md (özet),
 *                STATE_FILE (taranan alan adları + sorgular; sunucuda
 *                data/finder_state.json olarak saklanır — repo PUBLIC, oraya yazılmaz).
 *
 * ENV (hepsi opsiyonel)
 *   QUERIES_PER_RUN=40  MAX_SITES=400  TIME_BUDGET_SEC=2400  CONCURRENCY=6
 *   LANGS=de,it,fr      COUNTRIES=DE,AT,IT   ENGINES=bing,ddg,brave   BRAVE_API_KEY=…
 *   EXTRA_QUERIES="…"   SEED_DOMAINS="a.de, b.it"  (arama yapmadan doğrudan incele)
 *   KNOWN_FILE=known.json  ({emails:[…],domains:[…]} — sunucudaki mevcut leadler)
 *   STATE_FILE=out/state.json  OUT_DIR=out  DRY_RUN=1 (durumu yazma)
 *
 *   node scripts/find-boutiques.mjs --selftest   → ağ gerektirmeyen birim testleri
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname } from 'node:path';
import dns from 'node:dns/promises';

/* ============================== AYARLAR ============================== */
const ENV = process.env;
const num = (k, d) => { const v = parseInt(ENV[k] ?? '', 10); return Number.isFinite(v) && v > 0 ? v : d; };
const list = (k) => (ENV[k] ?? '').split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);

const CFG = {
  queriesPerRun: num('QUERIES_PER_RUN', 40),
  maxSites:      num('MAX_SITES', 400),
  budgetSec:     num('TIME_BUDGET_SEC', 2400),
  concurrency:   Math.min(num('CONCURRENCY', 6), 12),
  langs:         list('LANGS').map(s => s.toLowerCase()),
  countries:     list('COUNTRIES').map(s => s.toUpperCase()),
  engines:       (list('ENGINES').length ? list('ENGINES') : ['bing', 'ddg', 'brave']).map(s => s.toLowerCase()),
  braveKey:      (ENV.BRAVE_API_KEY ?? '').trim(),
  extraQueries:  (ENV.EXTRA_QUERIES ?? '').split(/\r?\n|;/).map(s => s.trim()).filter(Boolean),
  seedDomains:   list('SEED_DOMAINS'),
  knownFile:     ENV.KNOWN_FILE || '',
  stateFile:     ENV.STATE_FILE || 'out/state.json',
  outDir:        ENV.OUT_DIR || 'out',
  dryRun:        /^(1|true|yes)$/i.test(ENV.DRY_RUN ?? ''),
  minBrands:     num('MIN_BRANDS', 2),
};
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const T0 = Date.now();
const elapsed = () => (Date.now() - T0) / 1000;
const log = (...a) => { console.log(...a); };
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const jitter = (a, b) => a + Math.random() * (b - a);

/* ============================== MARKALAR ============================== */
/* kind: c = giyim/aksesuar (hedef), s = ayakkabı odaklı, u = iç çamaşırı/çorap.
   Takma adlar normalize edilmiş metinde (küçük harf, aksansız, " & " biçiminde) aranır.
   Günlük dilde de geçen kelimeler (guess, boss, closed, diesel…) bilerek dışarıda ya da
   tam adla: tek başına "boss" ≠ Hugo Boss. */
const BRANDS = [
  // --- hedef butiğin tipik markaları (sorgu tohumu olarak da kullanılır: q:true) ---
  ['Dsquared2', ['dsquared2', 'dsquared'], 'c', true],
  ['Balmain', ['balmain'], 'c', true],
  ['Philipp Plein', ['philipp plein'], 'c', true],
  ['Dolce & Gabbana', ['dolce & gabbana', 'dolce e gabbana', 'dolce and gabbana', 'dolce gabbana', 'd & g'], 'c', true],
  ['Versace', ['versace'], 'c', true],
  ['Givenchy', ['givenchy'], 'c', true],
  ['Valentino', ['valentino'], 'c', true],
  ['Gucci', ['gucci'], 'c', true],
  ['Balenciaga', ['balenciaga'], 'c', true],
  ['Fendi', ['fendi'], 'c', true],
  ['Off-White', ['off - white', 'off white', 'off-white'], 'c', true],
  ['Palm Angels', ['palm angels'], 'c', true],
  ['Marcelo Burlon', ['marcelo burlon'], 'c', true],
  ['GCDS', ['gcds'], 'c', true],
  ['Amiri', ['amiri'], 'c', true],
  ['Moncler', ['moncler'], 'c', true],
  ['Stone Island', ['stone island'], 'c', true],
  ['Kenzo', ['kenzo'], 'c', true],
  ['Moschino', ['moschino'], 'c', true],
  ['Burberry', ['burberry'], 'c', true],
  ['Alexander McQueen', ['alexander mcqueen', 'mcqueen'], 'c', true],
  ['Saint Laurent', ['saint laurent'], 'c', true],
  ['Prada', ['prada'], 'c', true],
  ['Armani', ['armani'], 'c', true],
  ['Hugo Boss', ['hugo boss'], 'c', true],
  ['Ralph Lauren', ['ralph lauren'], 'c', true],
  ['Jacob Cohen', ['jacob cohen'], 'c', true],
  ['Casablanca', ['casablanca'], 'c', false],
  ['Rhude', ['rhude'], 'c', true],
  ['Fear of God', ['fear of god'], 'c', false],
  // --- diğer giyim markaları (sayım için) ---
  ['Bottega Veneta', ['bottega veneta'], 'c'], ['Loewe', ['loewe'], 'c'], ['Dior', ['dior'], 'c'],
  ['Louis Vuitton', ['louis vuitton'], 'c'], ['Chanel', ['chanel'], 'c'], ['Miu Miu', ['miu miu'], 'c'],
  ['Marni', ['marni'], 'c'], ['Jil Sander', ['jil sander'], 'c'], ['Max Mara', ['max mara'], 'c'],
  ['Marella', ['marella'], 'c'], ['Etro', ['etro'], 'c'], ['Missoni', ['missoni'], 'c'],
  ['Kiton', ['kiton'], 'c'], ['Brunello Cucinelli', ['brunello cucinelli', 'cucinelli'], 'c'],
  ['Loro Piana', ['loro piana'], 'c'], ['Zegna', ['zegna'], 'c'], ['Corneliani', ['corneliani'], 'c'],
  ['Tagliatore', ['tagliatore'], 'c'], ['Lardini', ['lardini'], 'c'], ['Boglioli', ['boglioli'], 'c'],
  ['Eleventy', ['eleventy'], 'c'], ['Dondup', ['dondup'], 'c'], ['Incotex', ['incotex'], 'c'],
  ['PT Torino', ['pt torino', 'pt01', 'pt05'], 'c'], ['Tramarossa', ['tramarossa'], 'c'],
  ['Jeckerson', ['jeckerson'], 'c'], ['Manuel Ritz', ['manuel ritz'], 'c'],
  ['Daniele Alessandrini', ['daniele alessandrini'], 'c'], ['Antony Morato', ['antony morato'], 'c'],
  ['John Richmond', ['john richmond'], 'c'], ['Bikkembergs', ['bikkembergs'], 'c'],
  ['Frankie Morello', ['frankie morello'], 'c'], ['Les Hommes', ['les hommes'], 'c'],
  ['Neil Barrett', ['neil barrett'], 'c'], ['Plein Sport', ['plein sport'], 'c'],
  ['Roberto Cavalli', ['roberto cavalli', 'just cavalli', 'cavalli class'], 'c'],
  ['Elisabetta Franchi', ['elisabetta franchi'], 'c'], ['Pinko', ['pinko'], 'c'], ['Liu Jo', ['liu jo'], 'c'],
  ['Twinset', ['twinset', 'twin - set', 'twin set'], 'c'], ['Patrizia Pepe', ['patrizia pepe'], 'c'],
  ['Stella McCartney', ['stella mccartney'], 'c'], ['Isabel Marant', ['isabel marant'], 'c'],
  ['Zadig & Voltaire', ['zadig & voltaire'], 'c'], ['The Kooples', ['the kooples'], 'c'],
  ['ba&sh', ['ba & sh'], 'c'], ['Ganni', ['ganni'], 'c'], ['Samsøe Samsøe', ['samsoe samsoe', 'samsoe'], 'c'],
  ['Wood Wood', ['wood wood'], 'c'], ['Norse Projects', ['norse projects'], 'c'], ['NN07', ['nn07', 'nn.07'], 'c'],
  ['Les Deux', ['les deux'], 'c'], ['Drykorn', ['drykorn'], 'c'], ["Marc O'Polo", ["marc o'polo", 'marc o polo', 'marc opolo'], 'c'],
  ['Tommy Hilfiger', ['tommy hilfiger'], 'c'], ['Calvin Klein', ['calvin klein'], 'c'],
  ['Michael Kors', ['michael kors'], 'c'], ['Karl Lagerfeld', ['karl lagerfeld'], 'c'],
  ['Diesel', ['diesel'], 'c'], ['Replay', ['replay'], 'c'], ['Pepe Jeans', ['pepe jeans'], 'c'],
  ['C.P. Company', ['c.p. company', 'cp company', 'c.p.company'], 'c'], ['Canada Goose', ['canada goose'], 'c'],
  ['Parajumpers', ['parajumpers'], 'c'], ['Woolrich', ['woolrich'], 'c'], ['Peuterey', ['peuterey'], 'c'],
  ['Save The Duck', ['save the duck'], 'c'], ['Herno', ['herno'], 'c'], ['Mackage', ['mackage'], 'c'],
  ['Belstaff', ['belstaff'], 'c'], ['Paul & Shark', ['paul & shark', 'paul and shark'], 'c'],
  ['North Sails', ['north sails'], 'c'], ['Napapijri', ['napapijri'], 'c'], ['The North Face', ['the north face'], 'c'],
  ['Stüssy', ['stussy'], 'c'], ['Carhartt WIP', ['carhartt'], 'c'], ['A-Cold-Wall', ['a - cold - wall', 'a-cold-wall', 'acw'], 'c'],
  ['Maison Margiela', ['maison margiela', 'mm6'], 'c'], ['Rick Owens', ['rick owens'], 'c'],
  ['Comme des Garçons', ['comme des garcons'], 'c'], ['Acne Studios', ['acne studios'], 'c'],
  ['AMI Paris', ['ami paris', 'ami alexandre mattiussi'], 'c'], ['JW Anderson', ['jw anderson', 'j.w. anderson'], 'c'],
  ['Jacquemus', ['jacquemus'], 'c'], ['Y-3', ['y - 3', 'y-3'], 'c'], ['Lacoste', ['lacoste'], 'c'],
  ['Fred Perry', ['fred perry'], 'c'], ['Barbour', ['barbour'], 'c'], ['Vilebrequin', ['vilebrequin'], 'c'],
  ['Emporio Armani', ['emporio armani'], 'c'], ['Kith', ['kith'], 'c'], ['Represent', ['represent clo'], 'c'],
  ['Daily Paper', ['daily paper'], 'c'], ['Axel Arigato', ['axel arigato'], 'c'],
  // --- ayakkabı odaklı ---
  ['Golden Goose', ['golden goose'], 's'], ['Hogan', ['hogan'], 's'], ["Tod's", ["tod's", 'tods'], 's'],
  ["Church's", ["church's"], 's'], ['Santoni', ['santoni'], 's'], ['Philippe Model', ['philippe model'], 's'],
  ['Autry', ['autry'], 's'], ['Crime London', ['crime london'], 's'], ['P448', ['p448'], 's'],
  ['Common Projects', ['common projects'], 's'], ['Veja', ['veja'], 's'], ['New Balance', ['new balance'], 's'],
  ['Nike', ['nike'], 's'], ['Adidas', ['adidas'], 's'], ['Converse', ['converse'], 's'],
  ['Dr. Martens', ['dr. martens', 'dr martens'], 's'], ['UGG', ['ugg'], 's'], ['Birkenstock', ['birkenstock'], 's'],
  ['Timberland', ['timberland'], 's'], ['Clarks', ['clarks'], 's'], ['Geox', ['geox'], 's'],
  ['Jimmy Choo', ['jimmy choo'], 's'], ['Christian Louboutin', ['louboutin'], 's'],
  ['Manolo Blahnik', ['manolo blahnik'], 's'], ['Gianvito Rossi', ['gianvito rossi'], 's'],
  ['Sergio Rossi', ['sergio rossi'], 's'], ['Casadei', ['casadei'], 's'], ['Le Silla', ['le silla'], 's'],
  ['Aquazzura', ['aquazzura'], 's'], ['Ferragamo', ['ferragamo'], 's'], ['Paraboot', ['paraboot'], 's'],
  ['Floris van Bommel', ['van bommel'], 's'], ['Magnanni', ['magnanni'], 's'], ["Doucal's", ["doucal's", 'doucals'], 's'],
  ['Officine Creative', ['officine creative'], 's'], ['Fratelli Rossetti', ['fratelli rossetti'], 's'],
  ['Superga', ['superga'], 's'], ['Saucony', ['saucony'], 's'], ['Asics', ['asics'], 's'], ['Puma', ['puma'], 's'],
  ['Reebok', ['reebok'], 's'], ['Hoka', ['hoka'], 's'], ['Salomon', ['salomon'], 's'],
  ['Filling Pieces', ['filling pieces'], 's'], ['Mason Garments', ['mason garments'], 's'],
  ['D.A.T.E.', ['d.a.t.e.'], 's'], ['Voile Blanche', ['voile blanche'], 's'], ['Baldinini', ['baldinini'], 's'],
  ['Nero Giardini', ['nero giardini'], 's'], ['Kennel & Schmenger', ['kennel & schmenger', 'kennel und schmenger'], 's'],
  ['Paul Green', ['paul green'], 's'], ['Gabor', ['gabor'], 's'], ['Tamaris', ['tamaris'], 's'], ['Rieker', ['rieker'], 's'],
  ['Högl', ['hogl'], 's'], ['Unisa', ['unisa'], 's'], ['Sorel', ['sorel'], 's'], ['Blundstone', ['blundstone'], 's'],
  // --- iç çamaşırı / çorap ---
  ['Intimissimi', ['intimissimi'], 'u'], ['Calzedonia', ['calzedonia'], 'u'], ['Tezenis', ['tezenis'], 'u'],
  ['Chantelle', ['chantelle'], 'u'], ['Simone Pérèle', ['simone perele'], 'u'], ['Aubade', ['aubade'], 'u'],
  ['Hanro', ['hanro'], 'u'], ['Schiesser', ['schiesser'], 'u'], ['Falke', ['falke'], 'u'], ['Wolford', ['wolford'], 'u'],
  ['La Perla', ['la perla'], 'u'], ['Lise Charmel', ['lise charmel'], 'u'], ['Passionata', ['passionata'], 'u'],
  ['Marie Jo', ['marie jo'], 'u'], ['PrimaDonna', ['prima donna', 'primadonna'], 'u'], ['Sloggi', ['sloggi'], 'u'],
  ['Olaf Benz', ['olaf benz'], 'u'], ['Andres Sarda', ['andres sarda'], 'u'], ['Ten Cate', ['ten cate'], 'u'],
  ['Björn Borg', ['bjorn borg'], 'u'], ['Jockey', ['jockey'], 'u'], ['Hunkemöller', ['hunkemoller'], 'u'],
  ['Lejaby', ['lejaby'], 'u'], ['Empreinte', ['empreinte'], 'u'], ['Felina', ['felina'], 'u'], ['Skiny', ['skiny'], 'u'],
  ['Lascana', ['lascana'], 'u'], ['Agent Provocateur', ['agent provocateur'], 'u'], ['Mey', ['mey bodywear'], 'u'],
].map(([name, aliases, kind, q]) => ({ name, aliases, kind, q: !!q }));

const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
for (const b of BRANDS) b.re = new RegExp('(?<![a-z0-9])(?:' + b.aliases.map(esc).join('|') + ')(?![a-z0-9])');
const QUERY_BRANDS = BRANDS.filter(b => b.q).map(b => b.name);

/* ============================== ELEME LİSTELERİ ============================== */
/* Pazar yeri, zincir, büyük mağaza, outlet, sosyal, dizin, medya, platform.
   Alan adının KAYITLI etiketi (www'suz, TLD'siz) ile karşılaştırılır:
   4+ karakterli girdiler "etiket bununla başlıyor mu", kısa girdiler tam eşleşme. */
const BLOCK_DOMAINS = `
zalando aboutyou about-you otto bonprix asos boohoo shein temu wish aliexpress alibaba dhgate amazon ebay etsy rakuten
cdiscount fnac laredoute la-redoute veepee vente-privee privalia showroomprive brandalley vinted vestiairecollective rebelle
therealreal poshmark depop grailed stockx goat tradesy mercari wallapop leboncoin kleinanzeigen subito marktplaats 2dehands olx allegro
farfetch mytheresa net-a-porter mrporter ssense yoox luisaviaroma matchesfashion matches flannels selfridges harrods harveynichols
libertylondon johnlewis houseoffraser debenhams marksandspencer next very endclothing jdsports footlocker snipes sidestep courir kickz bstn
galerieslafayette printemps lebonmarche 24s bhv samaritaine elcorteingles rinascente coin ovs benetton sisley calzedonia intimissimi
tezenis oysho womensecret etam hunkemoller debijenkorf bijenkorf wehkamp bol zara hm mango bershka pullandbear stradivarius
massimodutti uniqlo primark c-and-a peek-cloppenburg peekundcloppenburg breuninger engelhorn lodenfrey konen hirmer woehrl ludwigbeck
kadewe oberpollinger alsterhaus galeria kaufhof karstadt fashionid goertz deichmann reno humanic salamander sarenza spartoo
zalando-lounge limango dress-for-less bestsecret fashionette highsnobiety hypebeast lyst modesens shopstyle stylight ladenzeile idealo
klarna glami fashiola lovethesales tkmaxx tjmaxx marshalls nordstrom macys bloomingdales saks neimanmarcus bergdorfgoodman barneys
holtrenfrew thebay simons davidjones myer nike adidas puma sportscheck intersport decathlon sportsdirect esprit soliver tomtailor
gerryweber olymp walbusch peterhahn heine madeleine ernstings kik takko nkd orsay pimkie newyorker jackjones veromoda bestseller
reserved cropp sinsay mohito ccc answear eobuwie modivo footway boozt nelly ellos miinto zoovillage stylepit outletcity mcarthurglen
designeroutlet fashion-outlet yoox-outlet
facebook instagram pinterest linkedin youtube tiktok twitter x snapchat threads reddit quora medium wikipedia wikidata tumblr vk
google apple microsoft bing duckduckgo brave yahoo yandex archive wayback issuu scribd slideshare
yelp tripadvisor foursquare 11880 gelbeseiten dasoertliche paginegialle pagesjaunes paginasamarillas cylex firmenabc herold
europages kompass wlw thomasnet indeed glassdoor kununu trustpilot trustedshops ekomi reviews
vogue gq elle harpersbazaar wwd businessoffashion fashionunited textilwirtschaft fashionnetwork drapersonline nssmag
shopify myshopify wix wordpress squarespace jimdo webflow weebly blogspot godaddy strato ionos one
pricerunner kelkoo trovaprezzi twenga shopalike lookastic shopzilla pricegrabber billiger geizhals guenstiger
`.split(/\s+/).filter(Boolean);
const BLOCK_SET = new Set(BLOCK_DOMAINS);
const BLOCK_PREFIX = BLOCK_DOMAINS.filter(s => s.length >= 5);
const BLOCK_SUFFIX = new Set(['shop', 'store', 'online', 'outlet', 'fashion', 'mode', 'moda', 'lounge', 'group', 'official', 'eu', 'uk',
  'de', 'it', 'fr', 'es', 'nl', 'at', 'ch', 'be', 'pl', 'pt', 'usa', 'us', 'ca', 'global', 'int', 'com', 'net', 'app', 'login', 'b2b']);

/* Firma adı / sayfa başlığı içinde tam kelime olarak geçerse zincir sayılır. */
const BLOCK_NAMES = [
  'zara', 'h&m', 'mango', 'bershka', 'pull&bear', 'pull & bear', 'stradivarius', 'massimo dutti', 'uniqlo', 'primark', 'c&a',
  'peek & cloppenburg', 'peek&cloppenburg', 'breuninger', 'engelhorn', 'lodenfrey', 'hirmer', 'ludwig beck', 'kadewe', 'galeria',
  'kaufhof', 'karstadt', 'zalando', 'about you', 'farfetch', 'mytheresa', 'net-a-porter', 'mr porter', 'ssense', 'yoox', 'luisaviaroma',
  'selfridges', 'harrods', 'harvey nichols', 'john lewis', 'house of fraser', 'galeries lafayette', 'printemps', 'le bon marché',
  'el corte inglés', 'el corte ingles', 'la rinascente', 'de bijenkorf', 'nordstrom', "macy's", 'bloomingdale', 'saks fifth',
  'neiman marcus', 'bergdorf', 'tk maxx', 'tj maxx', 'foot locker', 'jd sports', 'snipes', 'bstn', 'outlet', 'outletcity', 'factory store',
  'flagship store', 'offizieller online-shop', 'official online store', 'sito ufficiale', 'site officiel', 'tienda oficial', 'officiële',
];

/* Sitenin kendi kimliğinde (başlık/meta/h1/menü/alan adı) ne dediğine bakarak sektörü ayırırız. */
const TERMS = {
  shoe: ['shoes', 'shoe', 'footwear', 'sneakers', 'sneaker', 'boots', 'trainers', 'loafers', 'heels', 'schuhe', 'schuh', 'stiefel',
    'stiefeletten', 'pumps', 'halbschuhe', 'sandalen', 'mokassins', 'sneakerstore', 'scarpe', 'calzature', 'calzatura', 'stivali',
    'sandali', 'mocassini', 'decollete', 'chaussures', 'chaussure', 'bottes', 'bottines', 'baskets', 'escarpins', 'mocassins',
    'zapatos', 'zapateria', 'calzado', 'botas', 'zapatillas', 'sandalias', 'schoenen', 'schoenenwinkel', 'laarzen', 'sapatos',
    'sapataria', 'calcado', 'buty', 'obuwie', 'sklep obuwniczy', 'papoutsia', 'ypodimata', 'boty', 'obuv', 'sko', 'skobutik', 'skor',
    'skobutikk', 'kengat', 'kenkakauppa'],
  under: ['lingerie', 'underwear', 'unterwasche', 'wasche', 'dessous', 'miederwaren', 'intimo', 'intimates', 'lenceria', 'ropa interior',
    'ondergoed', 'lingeriewinkel', 'roupa interior', 'bielizna', 'esoroucha', 'spodni pradlo', 'undertoj', 'underklader',
    'alusvaatteet', 'nightwear', 'nachtwasche', 'sleepwear', 'homewear', 'strumpfhosen', 'hosiery', 'bademoden', 'bademode', 'swimwear',
    'badmode', 'costumi da bagno', 'maillots de bain', 'banadores', 'badkleding', 'bras', 'bh-shop', 'corsetry', 'corseteria'],
  cloth: ['clothing', 'clothes', 'fashion', 'apparel', 'menswear', 'womenswear', 'boutique', 'designer', 'mode', 'kleidung', 'bekleidung',
    'herrenmode', 'damenmode', 'herrenausstatter', 'modehaus', 'abbigliamento', 'moda', 'vestiti', 'giacche', 'camicie', 'pantaloni',
    'vetements', 'pret-a-porter', 'pret a porter', 'mode homme', 'mode femme', 'ropa', 'tienda de moda', 'kleding', 'kledingwinkel',
    'roupa', 'odziez', 'ubrania', 'rouxa', 'obleceni', 'toj', 'klader', 'klaer', 'vaatteet', 'jeans', 'jackets', 'jacken', 'hoodies',
    'sweatshirts', 't-shirts', 'tshirts', 'shirts', 'hemden', 'dresses', 'kleider', 'abiti', 'robes', 'vestidos', 'jurken', 'hosen',
    'trousers', 'pants', 'coats', 'mantel', 'cappotti', 'manteaux', 'abrigos', 'jassen', 'knitwear', 'strick', 'maglieria', 'pulls',
    'multibrand', 'multi-brand', 'multimarca', 'multimarques', 'concept store', 'conceptstore'],
  wholesale: ['wholesale', 'wholesaler', 'grosshandel', 'grossist', 'grossiste', 'grossista', 'ingrosso', "all'ingrosso", 'mayorista',
    'al por mayor', 'groothandel', 'atacado', 'atacadista', 'hurtownia', 'hurt odziezowy', 'chondriki', 'velkoobchod', 'engros',
    'b2b', 'trade only', 'distributor', 'distributors', 'distributeur', 'distributore', 'distribuidor', 'distribuidora', 'distribuzione',
    'stocklots', 'stock lots', 'stock lot', 'stockservice', 'lotti stock', 'liquidation stock', 'destockage', 'handelsagentur',
    'handelsvertretung', 'showroom agency', 'fashion agency', 'modeagentur', 'importer', 'importatore', 'sourcing'],
};
for (const k of Object.keys(TERMS)) TERMS[k + 'Re'] = TERMS[k].map(t => new RegExp('(?<![a-z0-9])' + esc(t) + '(?![a-z0-9])'));

/* Ücretsiz posta sağlayıcıları: butik gmail kullanıyorsa adres yine gerçektir. */
const FREE_MAIL = new Set(`gmail.com googlemail.com gmx.net gmx.de gmx.com gmx.at gmx.ch web.de hotmail.com hotmail.de hotmail.fr
hotmail.it hotmail.es hotmail.co.uk hotmail.nl hotmail.be outlook.com outlook.de outlook.fr outlook.it outlook.es live.com live.de live.fr
live.it live.nl live.be yahoo.com yahoo.de yahoo.fr yahoo.it yahoo.es yahoo.co.uk yahoo.gr ymail.com icloud.com me.com mac.com aol.com
t-online.de freenet.de arcor.de online.de posteo.de mail.com protonmail.com proton.me pm.me tutanota.com orange.fr wanadoo.fr free.fr
sfr.fr laposte.net bbox.fr libero.it virgilio.it alice.it tin.it tiscali.it fastwebnet.it email.it pec.it terra.es telefonica.net
ono.com ziggo.nl kpnmail.nl planet.nl home.nl xs4all.nl hetnet.nl telenet.be skynet.be proximus.be bluewin.ch hispeed.ch sunrise.ch
gmx.li aon.at chello.at a1.net drei.at sapo.pt netcabo.pt wp.pl o2.pl onet.pl interia.pl op.pl seznam.cz email.cz centrum.cz volny.cz
otenet.gr hol.gr forthnet.gr mail.ru yandex.ru bk.ru abv.bg mail.bg btinternet.com sky.com virginmedia.com talktalk.net eircom.net
telia.com telenor.dk mail.dk stofanet.dk online.no live.se spray.se comhem.se elisanet.fi luukku.com kolumbus.fi`.split(/\s+/));

/* Bu yerel kısımlar asla satış muhatabı değildir — tamamen reddedilir. */
const JUNK_LOCAL = /^(no-?reply|do-?not-?reply|donotreply|noreply[\w.-]*|notification[s]?|mailer-daemon|postmaster|abuse|hostmaster|webmaster|privacy|datenschutz|dpo|dsb|gdpr|rgpd|dsgvo|press|presse|pr|media|jobs?|career[s]?|karriere|recruit(ing|ment)?|hr|bewerbung|billing|invoice[s]?|rechnung(en)?|accounting|buchhaltung|fattur[a-z]*|legal|newsletter|unsubscribe|abmelden|sentry|security|spam|test|demo|example|sample|noemail|nomail|affiliate[s]?|partner(s|ship)?program|investor[s]?|ir|whistleblow[a-z]*|compliance|dev|developer[s]?|it|sysadmin|root|www|ftp)$/i;
/* Sahte / yer tutucu / platform adresleri. */
const JUNK_DOMAIN = /(^|\.)(example|domain|yourdomain|your-domain|mydomain|email|mail|test|sentry|wixpress|wix|shopify|myshopify|squarespace|godaddy|jimdo|webflow|wordpress|w3|schema|googleusercontent|cloudflare|cookiebot|onetrust|usercentrics|trustpilot|trustedshops|klarna|paypal|stripe|mollie|adyen|facebook|instagram|google|gmail-smtp|mailchimp|klaviyo|brevo|sendinblue|hubspot|zendesk|freshdesk|intercom|typo3|joomla|magento|prestashop|shopware|oxid|plentymarkets|jtl-software|afterbuy|ebay|amazon|apple|microsoft|adobe|fontawesome|github|npmjs|jquery|bootstrap|unpkg|jsdelivr|elementor|yoast|wpengine|siteground|hostinger|ionos|strato|1und1|one|webgo|all-inkl|hetzner|ovh)\.(com|de|net|org|io|co|eu|fr|it|es|nl|me|at|ch|uk|co\.uk)$/i;
const IMG_EXT = /\.(png|jpe?g|gif|svg|webp|avif|ico|css|js|woff2?|ttf|pdf)$/i;

/* Yerel kısım öncelik puanı: satış muhatabı olmaya en yakın adres kazanır. */
const LOCAL_SCORE = [
  [/^(info|infos|information)$/, 40], [/^(kontakt|contact|contacto|contatti|contato|contacts|contactus|kontakty|epikoinonia)$/, 38],
  [/^(shop|store|boutique|negozio|laden|winkel|tienda|loja|butik|eshop|e-shop|webshop|onlineshop|online)$/, 36],
  [/^(hello|hallo|ciao|bonjour|hola|hi|hey|hej|hei|ola|welcome|willkommen|salut)$/, 34],
  [/^(mail|post|office|email|e-mail|team|ufficio|bureau|buero|buro)$/, 30],
  [/^(sales|vendite|ventas|verkauf|vente|ventes|order|orders|bestellung|bestellungen|ordini|commande|pedidos|verkoop)$/, 28],
  [/^(service|kundenservice|kundendienst|customercare|customerservice|customer-service|care|support|help|hilfe|assistenza|servizioclienti|serviceclient|klantenservice|atencionalcliente)$/, 26],
  [/^(admin|web|webshop|eshop|onlineshop|management|direction|direzione|geschaeftsfuehrung|gf|owner|inhaber)$/, 18],
  [/^(returns?|retouren|resi|retours|devoluciones|marketing|social|instagram|b2b|wholesale|grosshandel|ingrosso)$/, 10],
];

/* ============================== YARDIMCILAR ============================== */
export function decodeEntities(s) {
  const named = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', commat: '@', period: '.', colon: ':', sol: '/',
    auml: 'ä', ouml: 'ö', uuml: 'ü', Auml: 'Ä', Ouml: 'Ö', Uuml: 'Ü', szlig: 'ß', eacute: 'é', egrave: 'è', agrave: 'à', aacute: 'á',
    ccedil: 'ç', ntilde: 'ñ', oacute: 'ó', iacute: 'í', uacute: 'ú', euml: 'ë', iuml: 'ï', ocirc: 'ô', ecirc: 'ê', acirc: 'â',
    oslash: 'ø', aring: 'å', aelig: 'æ', Oslash: 'Ø', Aring: 'Å', AElig: 'Æ', hellip: '…', mdash: '—', ndash: '–', rsquo: '’', lsquo: '‘',
    rdquo: '”', ldquo: '“', copy: '©', reg: '®', trade: '™', middot: '·', bull: '•', laquo: '«', raquo: '»', euro: '€' };
  return s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (m, g) => {
    if (g[0] === '#') { const cp = g[1].toLowerCase() === 'x' ? parseInt(g.slice(2), 16) : parseInt(g.slice(1), 10);
      return Number.isFinite(cp) && cp > 0 && cp < 0x110000 ? String.fromCodePoint(cp) : m; }
    return named[g] ?? m;
  });
}
/* Cloudflare e-posta gizleme: data-cfemail="hex" → ilk bayt anahtar, kalanı XOR. */
export function decodeCfEmail(hex) {
  try { const k = parseInt(hex.slice(0, 2), 16); let out = '';
    for (let i = 2; i < hex.length; i += 2) out += String.fromCharCode(parseInt(hex.slice(i, i + 2), 16) ^ k);
    return out; } catch { return ''; }
}
export function stripTags(html) {
  return decodeEntities(html.replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<!--[\s\S]*?-->/g, ' ').replace(/<br\s*\/?>|<\/(p|div|li|tr|h[1-6]|td|th|dt|dd|section|article|nav|header|footer)>/gi, '\n')
    .replace(/<[^>]+>/g, ' ')).replace(/[ \t ]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
}
/* Marka/terim eşleştirme için: küçük harf, aksansız, "&" etrafı boşluklu, boşluklar tek. */
export function normText(s) {
  return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[‘’`´]/g, "'")
    .replace(/\s*&\s*/g, ' & ').replace(/[\s_]+/g, ' ').trim();
}
const MULTI_TENANT = new Set(['myshopify.com', 'wixsite.com', 'jimdosite.com', 'jimdofree.com', 'webnode.page', 'webnode.com',
  'business.site', 'wordpress.com', 'blogspot.com', 'squarespace.com', 'weebly.com', 'bigcartel.com', 'ecwid.com', 'shopware.store']);
const SLD2 = new Set(['co.uk', 'org.uk', 'ac.uk', 'me.uk', 'com.au', 'net.au', 'com.br', 'co.nz', 'com.tr', 'co.za', 'com.mx', 'com.ar',
  'co.il', 'co.jp', 'ne.jp', 'com.sg', 'com.hk', 'com.pl', 'net.pl', 'org.pl', 'com.gr', 'com.cy', 'com.mt', 'com.pt', 'com.es',
  'com.ua', 'com.ru', 'co.kr', 'co.in', 'com.cn', 'com.tw', 'com.my', 'co.id', 'com.ph', 'com.vn', 'com.sa', 'com.eg', 'com.lb']);
export function registrableDomain(host) {
  host = (host || '').toLowerCase().replace(/^www\d*\./, '').replace(/\.$/, '');
  const p = host.split('.'); if (p.length < 2) return host;
  const last2 = p.slice(-2).join('.');
  if (MULTI_TENANT.has(last2)) return p.slice(-3).join('.');          // kiracı adı ayırt edici
  if (SLD2.has(last2) && p.length >= 3) return p.slice(-3).join('.');
  return last2;
}
const domainLabel = (d) => registrableDomain(d).split('.')[0];
const tld = (d) => { const p = registrableDomain(d).split('.'); const l2 = p.slice(-2).join('.'); return SLD2.has(l2) ? l2 : p[p.length - 1]; };

export function isBlockedDomain(d) {
  const reg = registrableDomain(d); const lab = domainLabel(reg);
  if (BLOCK_SET.has(lab)) return 'excl:chain';
  /* "zalando-lounge", "farfetch_uk" gibi türevler de zincirdir; ama "nellys-boutique" değildir:
     önekten sonra yalnızca ayraç + bilinen bir ek kalıyorsa engelle. */
  for (const b of BLOCK_PREFIX) if (lab.startsWith(b)) {
    const rest = lab.slice(b.length).replace(/^[-_]+/, '');
    if (rest === '' || BLOCK_SUFFIX.has(rest)) return 'excl:chain';
  }
  const flat = lab.replace(/[-_]/g, '');
  for (const b of BRANDS) for (const a of b.aliases) {
    const t = a.replace(/[^a-z0-9]/g, ''); if (t.length >= 5 && flat.includes(t)) return 'excl:brand-site';   // markanın kendi sitesi / monobrand
  }
  if (/(outlet|wholesale|grosshandel|grossiste|ingrosso|mayorista|groothandel|hurtownia|b2b|stocklot|dropship)/.test(flat)) return 'excl:wholesale';
  if (/(schuh|shoes|sneaker|scarpe|calzatur|chaussure|zapat|schoen|footwear|boots)/.test(flat)) return 'excl:shoes';
  if (/(lingerie|dessous|unterwaesche|underwear|intimo|lenceria|ondergoed|bielizna|bademode|swimwear)/.test(flat)) return 'excl:underwear';
  return '';
}

export function extractLinks(html, base) {
  const out = []; const re = /<a\b[^>]*?href\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))[^>]*>([\s\S]*?)<\/a>/gi; let m;
  while ((m = re.exec(html)) && out.length < 1500) {
    let href = decodeEntities((m[1] ?? m[2] ?? m[3] ?? '').trim()); if (!href || /^(javascript:|#|tel:|sms:|whatsapp:)/i.test(href)) continue;
    const text = stripTags(m[4]).slice(0, 80);
    try { out.push({ href: href.startsWith('mailto:') ? href : new URL(href, base).href, text }); } catch { /* bozuk href */ }
  }
  return out;
}

/* ---- e-posta çıkarma ---- */
export function extractEmails(html) {
  const found = new Map();                                     // email -> {where:Set}
  const add = (e, where) => { e = e.toLowerCase().replace(/^mailto:/, '').split('?')[0].trim().replace(/^[^a-z0-9]+|[^a-z0-9.]+$/g, '');
    if (!/^[a-z0-9][a-z0-9._%+'-]{0,63}@[a-z0-9][a-z0-9.-]{0,250}\.[a-z]{2,24}$/.test(e)) return;
    if (!found.has(e)) found.set(e, new Set()); found.get(e).add(where); };
  let m;
  for (const r of html.matchAll(/data-cfemail="([0-9a-f]+)"/gi)) add(decodeCfEmail(r[1]), 'cf');
  const dec = decodeEntities(html.replace(/%40/gi, '@'));
  for (const r of dec.matchAll(/href\s*=\s*["']?\s*mailto:([^"'>\s?]+)/gi)) add(r[1], 'mailto');
  for (const r of dec.matchAll(/"email"\s*:\s*"([^"]+)"/gi)) add(r[1], 'jsonld');
  const text = stripTags(dec)
    .replace(/\s*[\[({<]\s*(at|ät|chiocciola|arroba|arobase|bei|apenstaartje|alfa|et)\s*[\])}>]\s*/gi, '@')
    .replace(/\s*[\[({<]\s*(dot|punkt|punto|point|punt|stip)\s*[\])}>]\s*/gi, '.')
    .replace(/\s+@\s+/g, '@');
  for (const r of text.matchAll(/[a-z0-9][a-z0-9._%+'-]*@[a-z0-9][a-z0-9.-]*\.[a-z]{2,24}/gi)) add(r[0], 'text');
  for (const r of dec.matchAll(/[a-z0-9][a-z0-9._%+-]*@[a-z0-9][a-z0-9.-]*\.[a-z]{2,24}/gi)) add(r[0], 'raw');
  return [...found.entries()].map(([email, where]) => ({ email, where: [...where] }));
}
export function scoreEmail(email, siteDomain, where = [], page = '') {
  const [local, dom] = email.split('@'); if (!local || !dom) return -999;
  if (IMG_EXT.test(email) || JUNK_DOMAIN.test(dom) || JUNK_LOCAL.test(local) || local.length > 40 || /[0-9a-f]{20,}/i.test(local)) return -999;
  if (/^(u00|x[0-9a-f]{2})/.test(local) || /\.(png|jpg|gif|svg|webp)@/.test(email)) return -999;
  const site = registrableDomain(siteDomain); const edom = registrableDomain(dom);
  let s = 0;
  if (edom === site) s += 50;
  else if (FREE_MAIL.has(dom)) s += 20;
  else if (domainLabel(edom).length >= 4 && (domainLabel(site).includes(domainLabel(edom)) || domainLabel(edom).includes(domainLabel(site)))) s += 35;  // shop.de ↔ shop-boutique.de
  else return -999;                                            // üçüncü taraf kurumsal alan adı: web ajansı, fotoğrafçı, hosting…
  let ls = 10; for (const [re, v] of LOCAL_SCORE) if (re.test(local)) { ls = v; break; }
  if (ls === 10 && /^[a-z]+(\.[a-z]+)?$/.test(local) && local.length >= 4) ls = 15;   // kişi adı gibi
  s += ls;
  if (where.includes('mailto') || where.includes('cf')) s += 6;
  if (/contact|kontakt|contatt|contacto|impressum|imprint|mentions|legal|about/.test(page)) s += 5;
  else if (/privacy|datenschutz|cookie|terms|agb|cgv|condizioni|voorwaarden/.test(page)) s -= 3;
  return s;
}

/* ---- ülke ---- */
const TLD_COUNTRY = { de: 'Germany', at: 'Austria', ch: 'Switzerland', it: 'Italy', fr: 'France', es: 'Spain', nl: 'Netherlands',
  be: 'Belgium', pt: 'Portugal', pl: 'Poland', cz: 'Czechia', gr: 'Greece', uk: 'United Kingdom', 'co.uk': 'United Kingdom',
  ie: 'Ireland', dk: 'Denmark', se: 'Sweden', no: 'Norway', fi: 'Finland', lu: 'Luxembourg', hu: 'Hungary', ro: 'Romania',
  bg: 'Bulgaria', hr: 'Croatia', si: 'Slovenia', sk: 'Slovakia', ee: 'Estonia', lv: 'Latvia', lt: 'Lithuania', mt: 'Malta',
  cy: 'Cyprus', is: 'Iceland', ca: 'Canada', us: 'United States', au: 'Australia', 'com.au': 'Australia', nz: 'New Zealand',
  'co.nz': 'New Zealand', jp: 'Japan', 'co.jp': 'Japan', tr: 'Turkey', 'com.tr': 'Turkey', ae: 'United Arab Emirates', 'com.pl': 'Poland',
  'com.gr': 'Greece', 'com.pt': 'Portugal', 'com.cy': 'Cyprus', 'com.mt': 'Malta', 'com.es': 'Spain', rs: 'Serbia', ua: 'Ukraine' };
const PHONE_COUNTRY = [['351', 'Portugal'], ['353', 'Ireland'], ['354', 'Iceland'], ['356', 'Malta'], ['357', 'Cyprus'], ['358', 'Finland'],
  ['359', 'Bulgaria'], ['370', 'Lithuania'], ['371', 'Latvia'], ['372', 'Estonia'], ['381', 'Serbia'], ['385', 'Croatia'], ['386', 'Slovenia'],
  ['420', 'Czechia'], ['421', 'Slovakia'], ['352', 'Luxembourg'], ['377', 'Monaco'], ['971', 'United Arab Emirates'],
  ['30', 'Greece'], ['31', 'Netherlands'], ['32', 'Belgium'], ['33', 'France'], ['34', 'Spain'], ['36', 'Hungary'], ['39', 'Italy'],
  ['40', 'Romania'], ['41', 'Switzerland'], ['43', 'Austria'], ['44', 'United Kingdom'], ['45', 'Denmark'], ['46', 'Sweden'],
  ['47', 'Norway'], ['48', 'Poland'], ['49', 'Germany'], ['61', 'Australia'], ['64', 'New Zealand'], ['81', 'Japan'], ['90', 'Turkey'],
  ['1', 'United States']];
const COUNTRY_ISO = Object.fromEntries(Object.entries(TLD_COUNTRY).map(([k, v]) => [v, k.includes('.') ? k.split('.')[1] : k]).map(([v, k]) => [v, k.toUpperCase()]));
COUNTRY_ISO['United Kingdom'] = 'GB'; COUNTRY_ISO['Monaco'] = 'MC';
const LANG_COUNTRY = { it: 'Italy', fr: 'France', es: 'Spain', nl: 'Netherlands', pt: 'Portugal', pl: 'Poland', cs: 'Czechia', el: 'Greece',
  da: 'Denmark', sv: 'Sweden', nb: 'Norway', no: 'Norway', fi: 'Finland', de: 'Germany', hu: 'Hungary', ro: 'Romania', tr: 'Turkey' };
export function detectCountry(domain, text, htmlLang) {
  const t = tld(domain); if (TLD_COUNTRY[t]) return TLD_COUNTRY[t];
  const counts = {};
  for (const m of text.matchAll(/(?:\+|00)\s?(\d{1,3})[\s\d().\-/]{6,}/g)) {
    const digits = m[1];
    for (const [pre, c] of PHONE_COUNTRY) if (digits.startsWith(pre)) { counts[c] = (counts[c] || 0) + 1; break; }
  }
  const best = Object.entries(counts).sort((a, b) => b[1] - a[1])[0]; if (best) return best[0];
  const l = (htmlLang || '').toLowerCase().split('-')[0]; return LANG_COUNTRY[l] || '';
}

/* ---- firma adı ---- */
const GENERIC_NAME = /^(home|homepage|startseite|start|accueil|inicio|início|pagina iniziale|welcome|willkommen|benvenuti|bienvenue|online ?shop|shop|store|boutique|official|offiziell|sito ufficiale|site officiel|aρχική|αρχική|hlavní strana|strona główna|forside|hem|etusivu|index|untitled|default|new site|my site|mein shop)$/i;
export function companyName(html, domain) {
  const pick = (re) => { const m = html.match(re); return m ? stripTags(m[1]).replace(/\s+/g, ' ').trim() : ''; };
  const cands = [];
  cands.push(pick(/<meta[^>]+property=["']og:site_name["'][^>]+content=["']([^"']+)/i));
  cands.push(pick(/<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:site_name["']/i));
  cands.push(pick(/<meta[^>]+name=["']application-name["'][^>]+content=["']([^"']+)/i));
  const title = pick(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const lab = domainLabel(domain).replace(/[-_]/g, '');
  if (title) {
    const parts = title.split(/\s*[|–—\-:·•»«]\s*|\s+-\s+/).map(s => s.trim()).filter(s => s.length >= 2 && s.length <= 60);
    const byDomain = parts.find(p => { const f = p.toLowerCase().replace(/[^a-z0-9]/g, ''); return f.length >= 3 && (lab.includes(f) || f.includes(lab)); });
    if (byDomain) cands.push(byDomain);
    cands.push(...parts.sort((a, b) => a.length - b.length));
  }
  for (let c of cands) {
    c = (c || '').replace(/[®™©]/g, '').replace(/\s+/g, ' ').trim();
    if (c.length < 3 || c.length > 60 || GENERIC_NAME.test(c) || /^https?:/.test(c) || !/[a-z]/i.test(c)) continue;
    return c;
  }
  const d = domainLabel(domain); return d.split(/[-_]/).map(s => s ? s[0].toUpperCase() + s.slice(1) : '').join(' ');
}

/* ============================== ARAMA MOTORLARI ============================== */
const LOCALE = { en: { cc: 'GB', kl: 'uk-en', sl: 'en' }, de: { cc: 'DE', kl: 'de-de', sl: 'de' }, it: { cc: 'IT', kl: 'it-it', sl: 'it' },
  fr: { cc: 'FR', kl: 'fr-fr', sl: 'fr' }, es: { cc: 'ES', kl: 'es-es', sl: 'es' }, nl: { cc: 'NL', kl: 'nl-nl', sl: 'nl' },
  pt: { cc: 'PT', kl: 'pt-pt', sl: 'pt' }, pl: { cc: 'PL', kl: 'pl-pl', sl: 'pl' }, el: { cc: 'GR', kl: 'gr-el', sl: 'el' },
  cs: { cc: 'CZ', kl: 'cz-cs', sl: 'cs' }, da: { cc: 'DK', kl: 'dk-da', sl: 'da' }, sv: { cc: 'SE', kl: 'se-sv', sl: 'sv' },
  nb: { cc: 'NO', kl: 'no-no', sl: 'nb' }, fi: { cc: 'FI', kl: 'fi-fi', sl: 'fi' } };
/* Şablonlar: {a} {b} {c} = marka adları. Perakende kelimesi pazar yeri/haber sonuçlarını azaltır. */
const TEMPLATES = {
  en: ['"{a}" "{b}" boutique', '"{a}" "{b}" designer store brands', '"{a}" "{b}" "{c}" stockist'],
  de: ['"{a}" "{b}" Boutique Marken', '"{a}" "{b}" Herrenmode Designer', '"{a}" "{b}" "{c}" Marken Shop'],
  it: ['"{a}" "{b}" boutique abbigliamento', '"{a}" "{b}" negozio multimarca', '"{a}" "{b}" "{c}" marchi'],
  fr: ['"{a}" "{b}" boutique multimarques', '"{a}" "{b}" prêt-à-porter marques', '"{a}" "{b}" "{c}" boutique'],
  es: ['"{a}" "{b}" tienda multimarca', '"{a}" "{b}" boutique moda marcas', '"{a}" "{b}" "{c}" tienda'],
  nl: ['"{a}" "{b}" boetiek merken', '"{a}" "{b}" multibrand store', '"{a}" "{b}" "{c}" kleding merken'],
  pt: ['"{a}" "{b}" loja multimarca', '"{a}" "{b}" boutique marcas'],
  pl: ['"{a}" "{b}" butik multibrand', '"{a}" "{b}" sklep marki odzież'],
  el: ['"{a}" "{b}" boutique ρούχα', '"{a}" "{b}" κατάστημα μάρκες'],
  cs: ['"{a}" "{b}" butik značky', '"{a}" "{b}" obchod oblečení'],
  da: ['"{a}" "{b}" butik mærker', '"{a}" "{b}" tøj butik'],
  sv: ['"{a}" "{b}" butik märken', '"{a}" "{b}" kläder butik'],
  nb: ['"{a}" "{b}" butikk merker', '"{a}" "{b}" klær butikk'],
  fi: ['"{a}" "{b}" vaateliike merkit', '"{a}" "{b}" putiikki'],
};
const LANG_WEIGHT = { de: 5, it: 5, fr: 4, en: 4, nl: 3, es: 3, pl: 2, pt: 1, el: 1, cs: 1, da: 1, sv: 1, nb: 1, fi: 1 };

function mulberry32(a) { return () => { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
export function buildQueries(n, state, langsFilter = [], seed = Date.now()) {
  const rnd = mulberry32(seed >>> 0); const langs = Object.keys(TEMPLATES).filter(l => !langsFilter.length || langsFilter.includes(l));
  const weights = langs.map(l => LANG_WEIGHT[l] || 1); const wsum = weights.reduce((a, b) => a + b, 0);
  const pickLang = () => { let r = rnd() * wsum; for (let i = 0; i < langs.length; i++) { r -= weights[i]; if (r <= 0) return langs[i]; } return langs[langs.length - 1]; };
  const done = state.queries || {}; const cutoff = Date.now() - 60 * 86400e3;
  const out = []; const seen = new Set(); let guard = 0;
  while (out.length < n && guard++ < n * 50) {
    const lang = pickLang(); const tpls = TEMPLATES[lang]; const tpl = tpls[Math.floor(rnd() * tpls.length)];
    const br = [...QUERY_BRANDS].sort(() => rnd() - 0.5).slice(0, 3);
    const q = tpl.replace('{a}', br[0]).replace('{b}', br[1]).replace('{c}', br[2]);
    if (seen.has(q)) continue; seen.add(q);
    if (done[q] && Date.parse(done[q]) > cutoff) continue;
    out.push({ q, lang });
  }
  return out;
}

async function httpGet(url, { timeout = 15000, headers = {}, maxBytes = 1_500_000 } = {}) {
  const res = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(timeout),
    headers: { 'user-agent': UA, accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'accept-language': 'en,de;q=0.9,it;q=0.8,fr;q=0.8', ...headers } });
  const ct = res.headers.get('content-type') || '';
  const buf = Buffer.from(await res.arrayBuffer());
  const slice = buf.length > maxBytes ? buf.subarray(0, maxBytes) : buf;
  let cs = ct.match(/charset=["']?([\w-]+)/i)?.[1];
  if (!cs) cs = slice.subarray(0, 4096).toString('latin1').match(/<meta[^>]+charset=["']?\s*([\w-]+)/i)?.[1];
  let text; try { text = new TextDecoder((cs || 'utf-8').toLowerCase()).decode(slice); } catch { text = slice.toString('utf8'); }
  return { status: res.status, url: res.url, ct, text };
}

/* Bing'in yönlendirme linki: /ck/a?…&u=a1<base64url> */
export function unwrapResultUrl(href) {
  try {
    const u = new URL(href);
    if (/(^|\.)bing\.com$/.test(u.hostname) && u.pathname.startsWith('/ck/')) {
      const p = u.searchParams.get('u') || ''; if (p.startsWith('a1')) { const b = p.slice(2).replace(/-/g, '+').replace(/_/g, '/');
        return Buffer.from(b + '='.repeat((4 - b.length % 4) % 4), 'base64').toString('utf8'); }
      return '';
    }
    if (/duckduckgo\.com$/.test(u.hostname) && u.pathname.startsWith('/l/')) return decodeURIComponent(u.searchParams.get('uddg') || '');
    return href;
  } catch { return ''; }
}
export function parseBing(html) {
  const out = []; let m;
  const re = /<li class="b_algo"[\s\S]*?<h2[^>]*>\s*<a[^>]+href="([^"]+)"/g;
  while ((m = re.exec(html))) out.push(unwrapResultUrl(decodeEntities(m[1])));
  if (!out.length) { const re2 = /<h2[^>]*>\s*<a[^>]+href="(https?:[^"]+)"/g; while ((m = re2.exec(html))) out.push(unwrapResultUrl(decodeEntities(m[1]))); }
  return out.filter(Boolean);
}
export function parseDdg(html) {
  const out = []; let m; const re = /<a[^>]+class="result__a"[^>]+href="([^"]+)"/g;
  while ((m = re.exec(html))) { const h = decodeEntities(m[1]); out.push(unwrapResultUrl(h.startsWith('//') ? 'https:' + h : h)); }
  return out.filter(Boolean);
}
const ENGINE = {
  bing: { async search(q, lang) { const L = LOCALE[lang] || LOCALE.en;
    const url = `https://www.bing.com/search?q=${encodeURIComponent(q)}&count=30&first=1&setlang=${L.sl}&cc=${L.cc}`;
    const r = await httpGet(url, { timeout: 20000, headers: { 'accept-language': `${L.sl},en;q=0.7`, cookie: 'SRCHHPGUSR=ADLT=OFF&NRSLT=30' } });
    if (r.status === 429 || r.status === 403) throw new Error('blocked:' + r.status);
    const urls = parseBing(r.text); if (!urls.length && /captcha|challenge|verify you are human/i.test(r.text)) throw new Error('blocked:captcha');
    return urls; }, pause: [2500, 5000] },
  ddg: { async search(q, lang) { const L = LOCALE[lang] || LOCALE.en;
    const r = await httpGet(`https://html.duckduckgo.com/html/?q=${encodeURIComponent(q)}&kl=${L.kl}`, { timeout: 20000 });
    if (r.status === 429 || r.status === 403 || /anomaly|bots|challenge/i.test(r.text.slice(0, 3000))) throw new Error('blocked:' + r.status);
    return parseDdg(r.text); }, pause: [3000, 6000] },
  brave: { async search(q, lang) { if (!CFG.braveKey) throw new Error('nokey'); const L = LOCALE[lang] || LOCALE.en;
    const r = await httpGet(`https://api.search.brave.com/res/v1/web/search?q=${encodeURIComponent(q)}&count=20&country=${L.cc}&search_lang=${L.sl}`,
      { timeout: 20000, headers: { accept: 'application/json', 'x-subscription-token': CFG.braveKey } });
    if (r.status === 429) throw new Error('blocked:429'); if (r.status !== 200) throw new Error('http:' + r.status);
    const j = JSON.parse(r.text); return (j.web?.results || []).map(x => x.url).filter(Boolean); }, pause: [1200, 1800] },
};

/* ============================== SİTE İNCELEME ============================== */
const PAGE_HINTS = [
  ['contact', /contact|kontakt|contatt|contacto|contato|kontakty|epikoinonia|επικοινων|contattaci|contactez|iletisim/i],
  ['imprint', /impressum|imprint|mentions[-_ ]?l[eé]gales|note[-_ ]?legali|aviso[-_ ]?legal|legal[-_ ]?notice|colofon|informazioni[-_ ]?legali|juridische/i],
  ['brands', /brand|marke|marque|marchi|merk|marca|designer|labels|firmy|značk|maerker|marker|merkit/i],
  ['about', /about|ueber-uns|über-uns|uber-uns|chi-siamo|chisiamo|a-propos|apropos|quienes-somos|sobre|over-ons|o-nas|om-oss|wer-wir-sind|la-boutique|il-negozio|the-store|unser-laden|storia|history/i],
  ['privacy', /privacy|datenschutz|privacidad|confidentialit|informativa|gdpr|rgpd|dsgvo|personvern|tietosuoja|zasady-prywatnosci|ochrana/i],
  ['terms', /terms|agb|cgv|condizioni|voorwaarden|condiciones|regulamin|obchodni-podminky|conditions|lieferung|shipping|versand|retour|return/i],
];
const FIXED_PATHS = ['/pages/contact', '/pages/kontakt', '/pages/impressum', '/policies/legal-notice', '/policies/contact-information',
  '/policies/privacy-policy', '/impressum', '/impressum/', '/kontakt', '/kontakt/', '/contact', '/contact/', '/contatti', '/contatti/',
  '/contacto', '/contact-us', '/mentions-legales', '/note-legali', '/aviso-legal', '/legal', '/privacy-policy', '/datenschutz', '/about', '/about-us'];

function classify(domain, home, pagesText, hrefsText) {
  const pick = (re) => { const m = home.match(re); return m ? stripTags(m[1]) : ''; };
  const title = pick(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const metaD = pick(/<meta[^>]+name=["']description["'][^>]+content=["']([^"']*)/i) || pick(/<meta[^>]+content=["']([^"']*)["'][^>]+name=["']description["']/i);
  const h1 = [...home.matchAll(/<h1[^>]*>([\s\S]*?)<\/h1>/gi)].map(m => stripTags(m[1])).join(' ');
  const nav = extractLinks(home, 'https://' + domain + '/').slice(0, 200).map(l => l.text).join(' | ');
  const ident = normText([domainLabel(domain).replace(/[-_]/g, ' '), title, metaD, h1].join(' | '));
  const navN = normText(nav); const bodyN = normText(pagesText).slice(0, 400_000); const hrefN = normText(hrefsText);
  const count = (res, s) => res.reduce((n, re) => n + (re.test(s) ? 1 : 0), 0);
  const shoeI = count(TERMS.shoeRe, ident), clothI = count(TERMS.clothRe, ident), underI = count(TERMS.underRe, ident);
  const shoeN = count(TERMS.shoeRe, navN), clothN = count(TERMS.clothRe, navN), underN = count(TERMS.underRe, navN);
  const whI = count(TERMS.wholesaleRe, ident), whB = count(TERMS.wholesaleRe, bodyN);

  const brands = { c: new Set(), s: new Set(), u: new Set() };
  const hay = bodyN + ' ' + hrefN + ' ' + navN;
  for (const b of BRANDS) if (b.re.test(hay)) brands[b.kind].add(b.name);
  const nc = brands.c.size, ns = brands.s.size, nu = brands.u.size, tot = nc + ns + nu;

  let reason = '';
  const identNoCloth = clothI === 0;
  for (const bn of BLOCK_NAMES) if (normText(ident).includes(normText(bn))) { reason = 'excl:chain'; break; }
  if (!reason && (whI > 0 || whB >= 2)) reason = 'excl:wholesale';
  if (!reason && ((shoeI > 0 && identNoCloth) || shoeN > clothN + 1 || (tot >= 3 && ns / tot >= 0.6))) reason = 'excl:shoes';
  if (!reason && ((underI > 0 && identNoCloth) || underN > clothN + 1 || (tot >= 3 && nu / tot >= 0.6))) reason = 'excl:underwear';
  if (!reason && nc < CFG.minBrands) reason = nc === 0 ? 'excl:no-brands' : 'excl:monobrand';
  return { reason, title, brands: [...brands.c], shoeBrands: ns, underBrands: nu, signals: { shoeI, clothI, underI, shoeN, clothN, underN, whI, whB } };
}

const mxCache = new Map();
async function mailDomainOk(dom) {
  if (ENV.SKIP_MX === '1') return true;                               // yalnızca yerel test (ağsız ortam)
  if (mxCache.has(dom)) return mxCache.get(dom);
  let ok = false;
  try { const mx = await dns.resolveMx(dom); ok = mx.some(m => m.exchange && m.exchange !== '.' && m.exchange !== ''); }
  catch { try { ok = (await dns.resolve4(dom)).length > 0; } catch { try { ok = (await dns.resolve6(dom)).length > 0; } catch { ok = false; } } }
  mxCache.set(dom, ok); return ok;
}

async function analyzeSite(domain, ctx) {
  const res = { domain, status: 'fail', reason: '', pages: 0 };
  let base = ''; let home = null;
  for (const u of [`https://${domain}/`, `https://www.${domain}/`, `http://${domain}/`]) {
    try { const r = await httpGet(u, { timeout: 12000 }); if (r.status >= 200 && r.status < 400 && /html|xml/i.test(r.ct || 'html') && r.text.length > 300) { home = r; base = r.url; break; } }
    catch { /* sıradaki şema */ }
  }
  if (!home) { res.reason = 'fail:unreachable'; return res; }
  const finalHost = new URL(base).hostname; const finalReg = registrableDomain(finalHost);
  if (finalReg !== registrableDomain(domain)) {                       // başka alan adına yönlendi
    res.redirectedTo = finalReg;
    if (ctx.known.domains.has(finalReg) || ctx.seenRun.has(finalReg)) { res.status = 'excl'; res.reason = 'excl:dup'; return res; }
    ctx.seenRun.add(finalReg); const b = isBlockedDomain(finalReg); if (b) { res.status = 'excl'; res.reason = b; return res; }
    domain = finalReg; res.domain = domain;
  }
  const htmlLang = home.text.match(/<html[^>]+lang=["']?([a-zA-Z-]+)/)?.[1] || '';
  const links = extractLinks(home.text, base);
  const internal = links.filter(l => { try { return !l.href.startsWith('mailto:') && registrableDomain(new URL(l.href).hostname) === registrableDomain(domain); } catch { return false; } });
  const hrefsText = internal.map(l => { try { return decodeURIComponent(new URL(l.href).pathname).replace(/[-_/.+]/g, ' '); } catch { return ''; } }).join(' ');

  // Sayfa seçimi: iletişim → künye → hakkında → markalar → gizlilik → koşullar (en çok 8 sayfa)
  const chosen = new Map();
  for (const [kind, re] of PAGE_HINTS) {
    for (const l of internal) { if (chosen.size >= 8) break; const key = l.href.split('#')[0];
      if (chosen.has(key) || key === base) continue; if (re.test(key) || re.test(l.text)) chosen.set(key, kind); if ([...chosen.values()].filter(k => k === kind).length >= 2) break; }
  }
  const pages = [{ kind: 'home', url: base, html: home.text }];
  let emails = new Map(); const addEmails = (html, kind) => { for (const e of extractEmails(html)) { const cur = emails.get(e.email) || { where: new Set(), pages: new Set() }; e.where.forEach(w => cur.where.add(w)); cur.pages.add(kind); emails.set(e.email, cur); } };
  addEmails(home.text, 'home');
  const bestNow = () => { let b = -999; for (const [e, m] of emails) b = Math.max(b, scoreEmail(e, domain, [...m.where], [...m.pages].join(','))); return b; };
  const wantsBrands = [...chosen.values()].includes('brands'); let gotBrands = false;
  for (const [url, kind] of chosen) {
    if (elapsed() > CFG.budgetSec) break;
    await sleep(250);
    try { const r = await httpGet(url, { timeout: 10000 }); if (r.status < 400 && r.text) { pages.push({ kind, url, html: r.text }); addEmails(r.text, kind); if (kind === 'brands') gotBrands = true; } } catch { /* sayfa yok */ }
    // İyi bir adres bulundu ve marka sayfası (varsa) okunduysa kalan sayfalara gerek yok.
    if (bestNow() >= 80 && pages.length >= 3 && (!wantsBrands || gotBrands)) break;
  }
  if (bestNow() < 60) {                                                 // link bulunamadıysa bilinen sabit yolları dene
    const isShopify = /cdn\.shopify\.com|myshopify/.test(home.text);
    const paths = FIXED_PATHS.filter(p => isShopify ? true : !p.startsWith('/policies') && !p.startsWith('/pages/')).slice(0, 10);
    for (const p of paths) { if (bestNow() >= 60 || elapsed() > CFG.budgetSec) break;
      try { await sleep(200); const r = await httpGet(new URL(p, base).href, { timeout: 8000 }); if (r.status === 200 && /html/i.test(r.ct) && r.text.length > 80) { pages.push({ kind: p.replace(/\W+/g, ' ').trim(), url: r.url, html: r.text }); addEmails(r.text, p); } } catch { /* yok */ }
    }
  }
  res.pages = pages.length;
  const pagesText = pages.map(p => stripTags(p.html)).join('\n');
  const cls = classify(domain, home.text, pagesText, hrefsText);
  res.title = cls.title; res.brands = cls.brands; res.signals = cls.signals;
  res.company = companyName(home.text, domain);
  res.country = detectCountry(domain, pagesText, htmlLang);
  res.phone = (pagesText.match(/(?:\+|00)\d{1,3}[\s\d().\-/]{6,16}\d/) || [''])[0].replace(/\s+/g, ' ').trim();
  if (cls.reason) { res.status = 'excl'; res.reason = cls.reason; return res; }
  if (CFG.countries.length && (!res.country || !CFG.countries.includes(COUNTRY_ISO[res.country] || ''))) { res.status = 'excl'; res.reason = 'excl:country'; return res; }

  const scored = [...emails.entries()].map(([e, m]) => ({ email: e, score: scoreEmail(e, domain, [...m.where], [...m.pages].join(',')) })).filter(x => x.score > 0).sort((a, b) => b.score - a.score);
  const good = [];
  for (const c of scored.slice(0, 4)) { if (await mailDomainOk(c.email.split('@')[1])) good.push(c); }
  if (!good.length) {
    /* Site uygun ama adres yayınlamıyor: Hunter.io (anahtar varsa) — yalnızca KAYNAĞI olan,
       yani başka bir yerde yayınlanmış adresleri alır; Hunter'ın tahminleri alınmaz. */
    const h = await hunterLookup(domain);
    if (h) { good.push(h); res.viaHunter = true; }
  }
  if (!good.length) { res.status = 'no_email'; res.reason = scored.length ? 'no_email:nomx' : 'no_email'; return res; }
  res.status = 'ok'; res.email = good[0].email; res.altEmails = good.slice(1, 3).map(x => x.email); res.emailScore = good[0].score;
  return res;
}

/* ---- Hunter.io eklentisi (opsiyonel: HUNTER_API_KEY) ----
   domain-search: alan adı için web'de GÖRÜLMÜŞ adresleri, görüldüğü kaynak URL'leriyle verir.
   Ücretsiz plan ayda 25 arama; o yüzden sadece "uygun ama e-postasız" sitelerde ve
   çalışma başına HUNTER_MAX (varsayılan 20) ile sınırlı. */
const hunter = { key: (ENV.HUNTER_API_KEY ?? '').trim(), max: num('HUNTER_MAX', 20), used: 0, hits: 0, dead: false };
async function hunterLookup(domain) {
  if (!hunter.key || hunter.dead || hunter.used >= hunter.max) return null;
  hunter.used++;
  try {
    const r = await httpGet(`https://api.hunter.io/v2/domain-search?domain=${encodeURIComponent(domain)}&limit=10&api_key=${encodeURIComponent(hunter.key)}`,
      { timeout: 15000, headers: { accept: 'application/json' } });
    if (r.status === 401 || r.status === 403 || r.status === 429) { hunter.dead = true; log(`  !! hunter: HTTP ${r.status} -- kapatildi`); return null; }
    if (r.status !== 200) return null;
    const j = JSON.parse(r.text); const rows = (j.data?.emails || []).filter(e => e.value && (e.sources || []).length > 0 && (e.confidence ?? 0) >= 50);
    const scored = rows.map(e => ({ email: String(e.value).toLowerCase(), score: scoreEmail(String(e.value).toLowerCase(), domain, ['hunter'], 'contact') + (e.type === 'generic' ? 5 : 0) }))
      .filter(x => x.score > 0).sort((a, b) => b.score - a.score);
    for (const c of scored) if (await mailDomainOk(c.email.split('@')[1])) { hunter.hits++; return c; }
  } catch (e) { log(`  hunter hata: ${String(e.message || e).slice(0, 60)}`); }
  return null;
}

/* ============================== ANA AKIŞ ============================== */
function loadJson(p, d) { try { return existsSync(p) ? JSON.parse(readFileSync(p, 'utf8')) : d; } catch { return d; } }
function saveJson(p, o) { mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, JSON.stringify(o, null, 1) + '\n'); }

async function main() {
  const state = loadJson(CFG.stateFile, { version: 1, domains: {}, queries: {}, runs: [] });
  state.domains ||= {}; state.queries ||= {}; state.runs ||= [];
  const knownRaw = CFG.knownFile ? loadJson(CFG.knownFile, {}) : {};
  const known = { emails: new Set((knownRaw.emails || []).map(e => String(e).toLowerCase())), domains: new Set((knownRaw.domains || []).map(d => registrableDomain(String(d)))) };
  log(`ayarlar: sorgu=${CFG.queriesPerRun} site=${CFG.maxSites} butce=${CFG.budgetSec}s motor=${CFG.engines.join(',')}${CFG.braveKey ? '(+brave key)' : ''} dil=${CFG.langs.join(',') || 'hepsi'} ulke=${CFG.countries.join(',') || 'hepsi'}${CFG.dryRun ? ' [DRY-RUN]' : ''}`);
  log(`bilinen: ${known.emails.size} e-posta, ${known.domains.size} alan adi (sunucu) | durum dosyasi: ${Object.keys(state.domains).length} alan adi, ${Object.keys(state.queries).length} sorgu`);

  /* ---- 1) sorgular ---- */
  const dayIdx = Math.floor(Date.now() / 86400e3);
  const queries = [...CFG.extraQueries.map(q => ({ q, lang: 'en', extra: true })), ...buildQueries(CFG.queriesPerRun, state, CFG.langs, dayIdx * 7919)];
  const engines = CFG.engines.filter(e => ENGINE[e] && (e !== 'brave' || CFG.braveKey));
  const engStat = Object.fromEntries(engines.map(e => [e, { ok: 0, empty: 0, blocked: 0, urls: 0, dead: false }]));
  const cands = new Map();                                            // reg domain -> {q, url}
  const seenRun = new Set();
  const consider = (url, q) => {
    let host; try { host = new URL(url).hostname; } catch { return; } if (!host || /^\d+\.\d+\.\d+\.\d+$/.test(host)) return;
    const reg = registrableDomain(host); if (!reg.includes('.') || cands.has(reg) || seenRun.has(reg)) return;
    if (CFG.countries.length) { const c = TLD_COUNTRY[tld(reg)]; if (c && !CFG.countries.includes(COUNTRY_ISO[c] || '')) return; }
    cands.set(reg, { q, url });
  };
  for (const d of CFG.seedDomains) { let h = d.replace(/^https?:\/\//i, '').split('/')[0]; if (h) consider('https://' + h + '/', '(seed)'); }

  let ei = 0, qDone = 0;
  if (!engines.length) log('arama motoru yok (ENGINES) -- yalnizca seed_domains incelenecek');
  for (const { q, lang } of queries) {
    if (!engines.length) break;
    if (cands.size >= CFG.maxSites || elapsed() > CFG.budgetSec * 0.4) { log(`arama durdu: ${cands.size} aday / ${Math.round(elapsed())}s`); break; }
    const live = engines.filter(e => !engStat[e].dead); if (!live.length) { log('!! tum arama motorlari engellendi -- kalan sorgular atlandi'); break; }
    qDone++;
    let got = null, used = '';
    for (let k = 0; k < live.length && got === null; k++) {
      const e = live[(ei + k) % live.length]; used = e;
      try { const urls = await ENGINE[e].search(q, lang); engStat[e].urls += urls.length; if (urls.length) { engStat[e].ok++; got = urls; } else engStat[e].empty++; }
      catch (err) { const msg = String(err.message || err); if (/^blocked/.test(msg)) { engStat[e].blocked++; if (engStat[e].blocked >= 3) { engStat[e].dead = true; log(`  !! ${e}: 3x engellendi, bu calismada kapatildi`); } } else engStat[e].empty++; log(`  ${e} hata (${q}): ${msg.slice(0, 80)}`); }
      await sleep(jitter(...ENGINE[e].pause));
    }
    ei++;
    const before = cands.size; for (const u of got || []) consider(u, q);
    if (!CFG.dryRun) state.queries[q] = new Date().toISOString().slice(0, 10);
    log(`  [${used}] ${q}  -> ${(got || []).length} sonuc, +${cands.size - before} yeni aday`);
  }

  /* ---- 2) ön eleme ---- */
  const todo = []; const excl = {}; const bump = (k) => { excl[k] = (excl[k] || 0) + 1; };
  const recheckDays = { no_email: 180, fail: 30 };
  for (const [reg, meta] of cands) {
    seenRun.add(reg);
    if (known.domains.has(reg)) { bump('excl:dup'); continue; }
    const st = state.domains[reg];
    if (st) { const days = (Date.now() - Date.parse(st.t || 0)) / 86400e3; const base = String(st.s || '').split(':')[0];
      if (!(recheckDays[base] && days > recheckDays[base])) { bump('excl:seen'); continue; } }
    const b = isBlockedDomain(reg); if (b) { bump(b); if (!CFG.dryRun) state.domains[reg] = { s: b, t: new Date().toISOString().slice(0, 10) }; continue; }
    todo.push({ reg, ...meta });
  }
  log(`\naday: ${cands.size} | on elemede elenen: ${cands.size - todo.length} | incelenecek: ${todo.length}\n`);

  /* ---- 3) inceleme ---- */
  const ctx = { known, seenRun };
  const results = []; let done = 0;
  await (async () => { let i = 0; await Promise.all(Array.from({ length: CFG.concurrency }, async () => {
    while (i < todo.length) { if (elapsed() > CFG.budgetSec) return; const item = todo[i++];
      let r; try { r = await analyzeSite(item.reg, ctx); } catch (e) { r = { domain: item.reg, status: 'fail', reason: 'fail:' + String(e.message || e).slice(0, 40) }; }
      r.query = item.q; results.push(r); done++;
      const tag = r.status === 'ok' ? `+ ${r.company} <${r.email}> ${r.country || '?'} [${(r.brands || []).slice(0, 4).join(', ')}]` : `- ${r.reason}${r.title ? ' | ' + r.title.slice(0, 50) : ''}`;
      log(`  ${String(done).padStart(3)}/${todo.length} ${r.domain}: ${tag}`);
    } })); })();

  /* ---- 4) sonuç ---- */
  const leads = []; const today = new Date().toISOString().slice(0, 10);
  for (const r of results) {
    const key = r.domain;
    if (r.status === 'ok') {
      if (known.emails.has(r.email)) { r.status = 'excl'; r.reason = 'excl:dup'; }
      else if (leads.some(l => l.email === r.email)) { r.status = 'excl'; r.reason = 'excl:dup'; }
      else { known.emails.add(r.email); leads.push({ company: r.company, email: r.email, country: r.country || '', website: 'https://' + r.domain, phone: r.phone || '',
        premium_brands: r.brands, alt_emails: r.altEmails || [], query: r.query, title: r.title || '', source: 'web-search' }); }
    }
    bump(r.status === 'ok' ? 'added' : r.reason || r.status);
    if (!CFG.dryRun) state.domains[key] = { s: r.status === 'ok' ? 'added' : (r.reason || r.status), t: today };
  }
  mkdirSync(CFG.outDir, { recursive: true });
  writeFileSync(`${CFG.outDir}/leads.json`, JSON.stringify(leads, null, 1));
  writeFileSync(`${CFG.outDir}/results.json`, JSON.stringify(results, null, 1));

  const secs = Math.round(elapsed());
  const lines = [];
  lines.push(`## Butik bulucu — ${today}${CFG.dryRun ? ' (DRY-RUN)' : ''}`, '');
  lines.push(`| sorgu | aday alan adı | incelenen | **gerçek e-postalı yeni lead** | süre |`, `|---|---|---|---|---|`,
    `| ${qDone}/${queries.length} | ${cands.size} | ${results.length} | **${leads.length}** | ${Math.floor(secs / 60)} dk ${secs % 60} sn |`, '');
  lines.push('**Arama motorları:** ' + engines.map(e => `${e}: ${engStat[e].ok} ok / ${engStat[e].empty} boş / ${engStat[e].blocked} engel${engStat[e].dead ? ' (kapatıldı)' : ''}`).join(' · ') + (engines.length ? '' : ' (hiçbiri aktif değil)'), '');
  if (!CFG.braveKey) lines.push('> `BRAVE_API_KEY` secret tanımlı değil — Brave Search API en güvenilir motor (ücretsiz plan ayda 2000 sorgu, brave.com/search/api). Bing/DDG engellerse günlük sorgu sayısı düşer.', '');
  if (hunter.key) lines.push(`**Hunter.io:** ${hunter.used} sorgu, ${hunter.hits} adres bulundu${hunter.dead ? ' (anahtar/kota hatası — kapatıldı)' : ''}`, '');
  else lines.push('> `HUNTER_API_KEY` secret tanımlı değil — uygun ama sitesinde adres yayınlamayan butikler için Hunter.io yayınlanmış adresi kaynağıyla bulur (ücretsiz plan ayda 25 arama).', '');
  lines.push('**Eleme dağılımı:** ' + Object.entries(excl).sort((a, b) => b[1] - a[1]).map(([k, v]) => `${k}=${v}`).join(', '), '');
  if (leads.length) {
    lines.push('### Yeni leadler', '', '| firma | e-posta | ülke | markalar | site |', '|---|---|---|---|---|');
    for (const l of leads) lines.push(`| ${l.company.replace(/\|/g, '/')} | ${l.email} | ${l.country || '?'} | ${l.premium_brands.slice(0, 5).join(', ')} | ${l.website.replace('https://', '')} |`);
    lines.push('');
  }
  const noMail = results.filter(r => r.status === 'no_email');
  if (noMail.length) { lines.push(`### Uygun ama sitede e-posta yayınlamayan (${noMail.length}) — form/telefon ile ulaşılabilir`, '');
    for (const r of noMail.slice(0, 40)) lines.push(`- ${r.domain} — ${r.company || ''} ${r.country ? '(' + r.country + ')' : ''} ${r.phone ? '☎ ' + r.phone : ''} [${(r.brands || []).slice(0, 4).join(', ')}]`); lines.push(''); }
  const report = lines.join('\n'); writeFileSync(`${CFG.outDir}/report.md`, report);
  log('\n' + report);

  if (!CFG.dryRun) {
    state.runs.push({ t: new Date().toISOString(), queries: qDone, cands: cands.size, analyzed: results.length, added: leads.length, secs });
    state.runs = state.runs.slice(-90); state.updated = new Date().toISOString();
    saveJson(CFG.stateFile, state);
  }
  log(`\nBITTI: ${leads.length} yeni lead (gercek e-posta) | ${secs}s`);
}

/* ============================== SELFTEST ============================== */
function selftest() {
  let fails = 0; const t = (name, cond) => { log((cond ? 'PASS ' : 'FAIL ') + name); if (!cond) fails++; };
  t('registrableDomain www', registrableDomain('www.shop-boutique.de') === 'shop-boutique.de');
  t('registrableDomain co.uk', registrableDomain('www.foo.co.uk') === 'foo.co.uk');
  t('registrableDomain myshopify', registrableDomain('coolshop.myshopify.com') === 'coolshop.myshopify.com');
  t('block zalando', isBlockedDomain('zalando.de') === 'excl:chain');
  t('block farfetch sub', isBlockedDomain('www.farfetch.com') === 'excl:chain');
  t('block brand site', isBlockedDomain('dsquared2.com') === 'excl:brand-site');
  t('block outlet', isBlockedDomain('designer-outlet-shop.de') === 'excl:wholesale');
  t('block shoe domain', isBlockedDomain('schuh-mueller.de') === 'excl:shoes');
  t('allow boutique', isBlockedDomain('mientus.com') === '');
  t('cfemail', decodeCfEmail('54393d3a3b14313c3d3a3b7a373b39') === 'mondo@mondo.com' || decodeCfEmail('54393d3a3b14313c3d3a3b7a373b39').includes('@'));
  const html = `<html lang="de"><head><title>Boutique Müller | Designer Mode &amp; Marken</title><meta property="og:site_name" content="Boutique Müller"></head>
  <body><nav><a href="/marken/dsquared2">Dsquared2</a><a href="/marken/balmain">Balmain</a><a href="/marken/stone-island">Stone Island</a><a href="/kontakt">Kontakt</a></nav>
  <p>Schreiben Sie uns: <a href="mailto:info&#64;boutique-mueller.de?subject=Hi">Mail</a></p>
  <p>Webdesign: <a href="mailto:hello@webagentur-xyz.de">Agentur</a></p>
  <span class="__cf_email__" data-cfemail="54393d3a3b14313c3d3a3b7a373b39"></span>
  <p>shop (at) boutique-mueller (dot) de</p><p>datenschutz@boutique-mueller.de</p>
  <p>Tel: +49 30 1234567</p></body></html>`;
  const em = extractEmails(html); const emails = em.map(e => e.email);
  t('extract mailto entity', emails.includes('info@boutique-mueller.de'));
  t('extract (at)(dot)', emails.includes('shop@boutique-mueller.de'));
  t('extract cf', emails.some(e => e.includes('@mondo.com') || e.includes('@')));
  t('agency rejected', scoreEmail('hello@webagentur-xyz.de', 'boutique-mueller.de') < 0);
  t('privacy rejected', scoreEmail('datenschutz@boutique-mueller.de', 'boutique-mueller.de') < 0);
  t('info scores high', scoreEmail('info@boutique-mueller.de', 'boutique-mueller.de', ['mailto'], 'home') >= 90);
  t('gmail ok', scoreEmail('boutiquemueller@gmail.com', 'boutique-mueller.de') > 0);
  t('image junk', scoreEmail('logo@2x.png', 'x.de') < 0);
  t('companyName og', companyName(html, 'boutique-mueller.de') === 'Boutique Müller');
  t('companyName generic skip', companyName('<title>Home</title>', 'la-boutique-rossi.it') === 'La Boutique Rossi');
  t('country tld', detectCountry('shop.at', '', '') === 'Austria');
  t('country phone', detectCountry('shop.com', 'Tel +39 02 1234567 ... +39 02 7654321', 'en') === 'Italy');
  t('country lang', detectCountry('shop.com', 'nothing', 'fr-FR') === 'France');
  const links = extractLinks(html, 'https://boutique-mueller.de/'); t('links abs', links.some(l => l.href === 'https://boutique-mueller.de/marken/dsquared2'));
  const page = stripTags(html) + ' ' + links.map(l => l.href.replace(/[-_/.]/g, ' ')).join(' ');
  const c1 = classify('boutique-mueller.de', html, page, links.map(l => l.href.replace(/[-_/.]/g, ' ')).join(' '));
  t('classify brands', c1.brands.includes('Dsquared2') && c1.brands.includes('Balmain') && c1.brands.includes('Stone Island'));
  t('classify target ok', c1.reason === '');
  const shoeHtml = `<title>Schuh Müller - Schuhe online kaufen</title><nav><a href="/sneaker">Sneaker</a><a href="/stiefel">Stiefel</a><a href="/pumps">Pumps</a><a href="/sandalen">Sandalen</a></nav>
  <p>Marken: Gucci Prada Balenciaga Golden Goose Hogan</p>`;
  t('classify shoes', classify('mueller-mode.de', shoeHtml, stripTags(shoeHtml), '').reason === 'excl:shoes');
  const whHtml = `<title>Fashion Stocklots Großhandel</title><p>Dsquared2 Balmain Versace B2B wholesale</p>`;
  t('classify wholesale', classify('fs-trade.com', whHtml, stripTags(whHtml), '').reason === 'excl:wholesale');
  const monoHtml = `<title>Rossi Boutique</title><p>Dsquared2 jeans und mehr</p>`;
  t('classify monobrand', classify('rossi-boutique.it', monoHtml, stripTags(monoHtml), '').reason === 'excl:monobrand');
  const lingHtml = `<title>Dessous Paradies - Lingerie &amp; Bademode</title><nav><a>BH</a><a>Slips</a><a>Nachtwäsche</a></nav><p>Chantelle Aubade Hanro Falke Wolford</p>`;
  t('classify underwear', classify('dessous-paradies.de', lingHtml, stripTags(lingHtml), '').reason === 'excl:underwear');
  t('bing unwrap', unwrapResultUrl('https://www.bing.com/ck/a?!&&p=x&u=a1aHR0cHM6Ly9leGFtcGxlLmNvbS9wYWdl&ntb=1') === 'https://example.com/page');
  t('ddg unwrap', unwrapResultUrl('https://duckduckgo.com/l/?uddg=https%3A%2F%2Fexample.de%2Fx&rut=1') === 'https://example.de/x');
  t('parseBing', parseBing('<li class="b_algo"><h2><a href="https://a.de/">A</a></h2></li><li class="b_algo"><div><h2><a href="https://b.it/p">B</a></h2></div></li>').join(',') === 'https://a.de/,https://b.it/p');
  t('parseDdg', parseDdg('<a rel="nofollow" class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Fc.fr%2F&amp;rut=abc">C</a>').join(',') === 'https://c.fr/');
  const qs = buildQueries(10, { queries: {} }, ['de', 'it'], 42);
  t('buildQueries count', qs.length === 10 && qs.every(x => ['de', 'it'].includes(x.lang) && x.q.includes('"')));
  t('buildQueries deterministic', JSON.stringify(buildQueries(5, { queries: {} }, [], 7)) === JSON.stringify(buildQueries(5, { queries: {} }, [], 7)));
  t('buildQueries skips recent', buildQueries(3, { queries: Object.fromEntries(buildQueries(3, { queries: {} }, [], 7).map(x => [x.q, new Date().toISOString()])) }, [], 7).every(x => !buildQueries(3, { queries: {} }, [], 7).some(y => y.q === x.q)));
  log(fails ? `\n${fails} TEST BASARISIZ` : '\nTUM TESTLER GECTI'); process.exit(fails ? 1 : 0);
}

if (process.argv.includes('--selftest')) selftest();
else main().catch(e => { console.error('HATA:', e); process.exit(1); });
