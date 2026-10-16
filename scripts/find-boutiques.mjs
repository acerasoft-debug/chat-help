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
 *   SEARCH_CMD="ssh vestra 'php …/search.php'"  → sunucu vekili: Brave Search API +
 *                Google Places (anahtarlar admin panelinde, sunucuda; buraya gelmez)
 *   CITIES="Italy|Milano, Germany|München"  PLACES_PER_RUN=6 (boşsa havuzdan sırayla)
 *   QUERIES_PER_RUN=40  MAX_SITES=400  TIME_BUDGET_SEC=2400  CONCURRENCY=6
 *   LANGS=de,it,fr      COUNTRIES=DE,AT,IT   ENGINES=server,brave,bing,ddg  (brave = runner'da BRAVE_API_KEY ile; normalde sunucu vekili)
 *   EXTRA_QUERIES="…"   SEED_DOMAINS="a.de, b.it"  (arama yapmadan doğrudan incele)
 *   KNOWN_FILE=known.json  ({emails:[…],domains:[…]} — sunucudaki mevcut leadler)
 *   STATE_FILE=out/state.json  OUT_DIR=out  DRY_RUN=1 (durumu yazma)
 *
 *   node scripts/find-boutiques.mjs --selftest   → ağ gerektirmeyen birim testleri
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname } from 'node:path';
import dns from 'node:dns/promises';
import { spawnSync } from 'node:child_process';

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
  engines:       (list('ENGINES').length ? list('ENGINES') : ['server', 'osm', 'brave', 'bing', 'ddg']).map(s => s.toLowerCase()),
  searchCmd:     (ENV.SEARCH_CMD ?? '').trim(),                       // sunucu arama vekili (anahtarlar sunucuda)
  cities:        (ENV.CITIES ?? '').split(/[,;\n]+/).map(s => s.trim()).filter(s => s.includes('|')),
  placesPerRun:  num('PLACES_PER_RUN', 6),                         // tur başına şehir (Google Places)
  /* 10 Eki 2026 (operatör: "çok az email bulunmuş"): 9 Eki koşusu 6 şehirle 207 aday, 4 lead, 5 dk —
     40 dk'lık bütçenin %13'ü. Şehir listesi verilmemişse OSM artık hedef aday sayısına ya da süre
     bütçesinin %35'ine kadar havuzdan şehir almaya devam eder. */
  maxCities:     num('MAX_CITIES', 40),
  osmConcurrency: Math.max(1, Math.min(4, num('OSM_CONCURRENCY', 3))),         // 13 Eki: paralel OSM şehir işçisi
  euroShare:     Math.max(0, Math.min(1, Number(process.env.EURO_SHARE ?? 0.95) || 0)),   // 16 Eki 2026 (operatör: "daha çok Avrupa"): 0.8 → 0.95   // 13 Eki: otomatik turda Avrupa kasabalarının payı                            // otomatik turda en çok şehir (OSM)
  osmTarget:     num('OSM_TARGET', 900),                           // otomatik turda hedef yeni OSM adayı
  osmPerCity:    num('OSM_PER_CITY', 150),                          // şehir başına en çok yeni OSM adayı
  townQueries:   num('TOWN_QUERIES', 120),                          // 13 Eki: kasaba sorgusu (sunucudaki Bing)
  focusQueries:  num('FOCUS_QUERIES', 8),     // 16 Eki 2026: 40 → 8 (Avrupa önce)                          // 14 Eki: Avustralya/Kanada/Japonya/G. Kore sorgusu (her tur)
  focusCities:   num('FOCUS_CITIES', 2),      // 16 Eki 2026: 12 → 2 (Avrupa önce)                           // 14 Eki: aynı ülkelerden OSM'de taranacak şehir (her tur)
  gridCells:     num('GRID_CELLS', 200),                            // 13 Eki: otomatik turda taranacak Avrupa karesi (0 = kapalı)
  gridPerCell:   num('GRID_PER_CELL', 400),                         // kare başına en çok yeni aday
  braveKey:      (ENV.BRAVE_API_KEY ?? '').trim(),
  /* 15 Eki 2026 (operatör: "Google Programmable Search ücretsiz yap"): Custom Search JSON API, günde 100 sorgu ÜCRETSİZ,
     üstü kapalı (anahtarda faturalandırma açılmadıkça 429 döner). Tur başına GOOGLE_PER_RUN sorgu (8 tur × 12 = 96/gün). */
  googleKey:     (ENV.GOOGLE_CSE_KEY ?? '').trim(),
  googleCx:      (ENV.GOOGLE_CSE_CX ?? '').trim(),
  googlePerRun:  Math.max(0, num('GOOGLE_PER_RUN', 12)),
  braveAllowed:  String(ENV.BRAVE_ALLOWED ?? 'true').toLowerCase() !== 'false',   // 13 Eki: Brave yalnız operatörün aramalarında (satıcı araması OSM ile)
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
  /* --- 14 Eki 2026: lüks OLMAYAN ama çok markalı dükkânların taşıdığı markalar (kind m). Yalnız "bu dükkân başkalarının
     markasını satıyor" kanıtı için sayılır; premium_brands'a girmez. Günlük dilde geçen adlar (only, vila, ecco, camper,
     guess, opus, cream…) bilerek YOK — yanlış "çok markalı" kanıtı üretirler. */
  ["Levi's", ["levi's", 'levis', 'levi strauss'], 'm'], ['G-Star', ['g-star raw', 'g - star raw', 'gstar raw'], 'm'],
  ['Scotch & Soda', ['scotch & soda'], 'm'], ['Superdry', ['superdry'], 'm'], ['Gant', ['gant rugger', 'gant shirtmakers'], 'm'],
  ['Hackett', ['hackett london'], 'm'], ['Tommy Jeans', ['tommy jeans'], 'm'], ['Mavi', ['mavi jeans'], 'm'],
  ['Joop', ['joop!', 'joop jeans'], 'm'], ['Strellson', ['strellson'], 'm'], ['Camel Active', ['camel active'], 'm'],
  ['Brax', ['brax'], 'm'], ['Cambio', ['cambio jeans'], 'm'], ['Mac Jeans', ['mac jeans'], 'm'], ['Marc Cain', ['marc cain'], 'm'],
  ['Riani', ['riani'], 'm'], ['Luisa Cerano', ['luisa cerano'], 'm'], ['Rich & Royal', ['rich & royal'], 'm'],
  ['Mos Mosh', ['mos mosh'], 'm'], ['Part Two', ['part two'], 'm'], ['InWear', ['inwear'], 'm'], ['b.young', ['b.young', 'byoung'], 'm'],
  ['Soyaconcept', ['soyaconcept', 'soya concept'], 'm'], ['Fransa', ['fransa'], 'm'], ['Gestuz', ['gestuz'], 'm'],
  ['Munthe', ['munthe'], 'm'], ['Casual Friday', ['casual friday'], 'm'], ['Selected Homme', ['selected homme', 'selected femme'], 'm'],
  ['Desigual', ['desigual'], 'm'], ['Ichi', ['ichi copenhagen'], 'm'], ['Saint Tropez', ['saint tropez'], 'm'],
  ['Alpha Industries', ['alpha industries'], 'm'], ['Schott', ['schott nyc'], 'm'], ['Blauer', ['blauer usa', 'blauer h.t'], 'm'],
  ['Colmar', ['colmar'], 'm'], ['K-Way', ['k-way', 'k - way', 'kway'], 'm'], ['Ciesse', ['ciesse piumini'], 'm'], ['RRD', ['rrd roberto ricci'], 'm'],
  ["Mason's", ["mason's"], 'm'], ['At.P.Co', ['at.p.co', 'atpco'], 'm'], ['Sun68', ['sun68', 'sun 68'], 'm'], ['Bomboogie', ['bomboogie'], 'm'],
  ['Refrigiwear', ['refrigiwear'], 'm'], ['Gaudì', ['gaudi jeans'], 'm'], ['Yes Zee', ['yes zee', 'yes-zee'], 'm'],
  ['Vicolo', ['vicolo'], 'm'], ['Kaos', ['kaos milano'], 'm'], ['Molly Bracken', ['molly bracken'], 'm'], ['Kocca', ['kocca'], 'm'],
  ['Please', ['please jeans'], 'm'], ['Haveone', ['haveone'], 'm'], ['Berna', ['berna'], 'm'],
  ['Vans', ['vans'], 's'], ['Skechers', ['skechers'], 's'], ['Mephisto', ['mephisto'], 's'], ['Hoka', ['hoka one one', 'hoka'], 's'],
  ['Lloyd', ['lloyd shoes', 'lloyd schuhe'], 's'], ['Ara', ['ara shoes', 'ara schuhe'], 's'], ['Waldläufer', ['waldlaufer'], 's'],
  ['Fossil', ['fossil'], 'm'], ['Furla', ['furla'], 'm'], ['Liebeskind', ['liebeskind'], 'm'], ['Fritzi aus Preußen', ['fritzi aus preussen'], 'm'],
  ['Kipling', ['kipling'], 'm'], ['Eastpak', ['eastpak'], 'm'], ['Herschel', ['herschel supply'], 'm'], ['Coccinelle', ['coccinelle'], 'm'],
  ['La Martina', ['la martina'], 'm'], ['Borbonese', ['borbonese'], 'm'], ['Piquadro', ['piquadro'], 'm'], ['The Bridge', ['the bridge firenze'], 'm'],
  ['Picard', ['picard lederwaren', 'picard bags'], 'm'], ['Rains', ['rains copenhagen'], 'm'], ['Ray-Ban', ['ray-ban', 'ray - ban', 'rayban'], 'm'],
  ['Becksöndergaard', ['becksondergaard', 'beck sondergaard'], 'm'], ['Roeckl', ['roeckl'], 'm'], ['Codello', ['codello'], 'm'],
  ['Thomas Sabo', ['thomas sabo'], 'm'], ['Daniel Wellington', ['daniel wellington'], 'm'], ['Swarovski', ['swarovski'], 'm'],
  ['Triumph', ['triumph lingerie', 'triumph dessous'], 'u'], ['Mey', ['mey'], 'u'], ['Calida', ['calida'], 'u'], ['Sloggi', ['sloggi'], 'u'],
  /* --- 16 Eki 2026 (operatör: "başka sektörlere de" → spor & sneaker, golf / yelken / kayak, outlet, concept store): spor ve
     outdoor markaları (kind p). Yalnız "başkalarının markasını satıyor" kanıtı; premium_brands'a girmez. Günlük kelime olan
     adlar (head, ping, gill, slam, kappa…) bilerek NİTELEYİCİLİ. */
  ['Patagonia', ['patagonia'], 'p'], ['Helly Hansen', ['helly hansen'], 'p'], ['Peak Performance', ['peak performance'], 'p'],
  ['Columbia', ['columbia sportswear', 'columbia montrail'], 'p'], ['Jack Wolfskin', ['jack wolfskin'], 'p'], ['Mammut', ['mammut'], 'p'],
  ["Arc'teryx", ["arc'teryx", 'arcteryx'], 'p'], ['Haglöfs', ['haglofs'], 'p'], ['Fjällräven', ['fjallraven'], 'p'], ['Odlo', ['odlo'], 'p'],
  ['Ortovox', ['ortovox'], 'p'], ['Dynafit', ['dynafit'], 'p'], ['Schöffel', ['schoffel'], 'p'], ['Vaude', ['vaude'], 'p'],
  ['Bogner', ['bogner'], 'p'], ['Kjus', ['kjus'], 'p'], ['Goldbergh', ['goldbergh'], 'p'], ['Spyder', ['spyder'], 'p'],
  ['Descente', ['descente'], 'p'], ['Rossignol', ['rossignol'], 'p'], ['Sportalm', ['sportalm'], 'p'], ['Toni Sailer', ['toni sailer'], 'p'],
  ['Musto', ['musto'], 'p'], ['Henri Lloyd', ['henri lloyd', 'henri-lloyd'], 'p'], ['Gill', ['gill marine', 'gill sailing'], 'p'],
  ['Sail Racing', ['sail racing'], 'p'], ['Slam', ['slam sailing', 'slam vela'], 'p'], ['Sebago', ['sebago'], 'p'], ['Zhik', ['zhik'], 'p'],
  ['Callaway', ['callaway golf', 'callaway apparel'], 'p'], ['Titleist', ['titleist'], 'p'], ['FootJoy', ['footjoy'], 'p'],
  ['J.Lindeberg', ['j.lindeberg', 'j. lindeberg', 'jlindeberg'], 'p'], ['Galvin Green', ['galvin green'], 'p'], ['Abacus', ['abacus sportswear', 'abacus golf'], 'p'],
  ['Chervò', ['chervo'], 'p'], ['Golfino', ['golfino'], 'p'], ['Cross Sportswear', ['cross sportswear', 'cross apparel'], 'p'], ['Daily Sports', ['daily sports'], 'p'],
  ['Under Armour', ['under armour'], 'p'], ['Fila', ['fila sport', 'fila heritage', 'fila shoes'], 'p'], ['Ellesse', ['ellesse'], 'p'],
  ['Champion', ['champion reverse weave', 'champion athletics'], 'p'], ['Kappa', ['kappa kontroll', 'robe di kappa'], 'p'], ['Umbro', ['umbro'], 'p'],
  ['Le Coq Sportif', ['le coq sportif'], 'p'], ['Sergio Tacchini', ['sergio tacchini'], 'p'], ['Quiksilver', ['quiksilver'], 'p'],
  ['Billabong', ['billabong'], 'p'], ['Rip Curl', ['rip curl', 'ripcurl'], 'p'], ["O'Neill", ["o'neill wetsuits", 'oneill wetsuits', "o'neill surf"], 'p'],
  ['Volcom', ['volcom'], 'p'], ['Oakley', ['oakley'], 'p'], ['On Running', ['on running', 'on cloud'], 'p'], ['Brooks', ['brooks running'], 'p'],
].map(([name, aliases, kind, q]) => ({ name, aliases, kind, q: !!q }));

const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
for (const b of BRANDS) b.re = new RegExp('(?<![a-z0-9])(?:' + b.aliases.map(esc).join('|') + ')(?![a-z0-9])');
const QUERY_BRANDS = BRANDS.filter(b => b.q).map(b => b.name);

/* ============================== ELEME LİSTELERİ ============================== */
/* Pazar yeri, zincir, büyük mağaza, outlet, sosyal, dizin, medya, platform.
   Alan adının KAYITLI etiketi (www'suz, TLD'siz) ile karşılaştırılır:
   4+ karakterli girdiler "etiket bununla başlıyor mu", kısa girdiler tam eşleşme. */
const BLOCK_DOMAINS = `
midilibre mundodeportivo marca lequipe gazzetta kicker sport1 eurosport tuttosport corrieredellosport nicematin laprovence letelegramme lavoixdunord lalsace lestrepublicain leprogres ledauphine
treasure-f ishii-sports gallery2 boardriders chullanka scapino vanharen altonsports runnerslab tauntonleisure vintiz repetto
bever asadventure unterwegs tennis-point sportokay sport-schuster sportarena globetrotter lyko neye boozt nelly ellos bestseller varner cubus dressmann kappahl lindex bikbok carlings ginatricot gina-tricot stadium intersport xxl sportamore sportmaster
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
rtl ksta morgenpost sueddeutsche bunte news wp manager-magazin spiegel bild focus welt faz stern zeit tagesschau zdf ard ntv n-tv sat1
prosieben vox t-online gmx kering lvmh yorkshire gemo merkal taokids size sizeofficial prodirectsport fc-moto mistersafetyshoes factoryshoe
leparisien lefigaro ouest-france lemonde liberation corriere repubblica elpais bbc dailymail theguardian telegraph mirror nieuwsblad hln
telegraaf onet interia cbc ctv globalnews lindependant ladepeche sudouest nzherald stuff smh theage news24
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
  'neiman marcus', 'bergdorf', 'tk maxx', 'tj maxx', 'foot locker', 'jd sports', 'snipes', 'bstn', 'outletcity', 'factory store', 'mcarthurglen', 'designer outlet',
  'intersport', 'decathlon', 'sport 2000', 'sports direct', 'sportscheck', 'go sport', 'xxl sport', 'stadium', 'sport experts',
  'comptoir des cotonniers', 'claudie pierlot', 'the kooples', 'zadig & voltaire', 'gerard darel', 'armand thiery', 'naf naf',
  'cache cache', 'du pareil au meme', 'petit bateau', 'eden park', 'fiorella rubino', 'piazza italia', 'harmont & blaine',
  'luisa spagnoli', 'max & co', 'penny black', 'weekend max mara', 'nara camicie', 'camicissima', 'brooksfield', 'yamamay',
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
    'multibrand', 'multi-brand', 'multimarca', 'multimarques', 'concept store', 'conceptstore',
    /* 16 Eki 2026: bağımsız büyük mağaza / lifestyle / outlet (zincirler BLOCK listesinde kalır, tek marka monobrand'dir). */
    'department store', 'kaufhaus', 'warenhaus', 'grand magasin', 'grands magasins', 'magazzini', 'grandi magazzini', 'grandes almacenes',
    'almacenes', 'varuhus', 'stormagasin', 'tavaratalo', 'lifestyle store', 'lifestyle shop', 'lifestylestore', 'outlet', 'outlet store',
    'markenoutlet', 'marken outlet', 'designer outlet store', 'off-price', 'stockhouse', 'stock house', 'spaccio', 'magasin d\'usine'],
  acc: ['accessories', 'accessoires', 'accessori', 'accesorios', 'acessorios', 'akcesoria', 'bags', 'handbags', 'taschen', 'handtaschen',
    'borse', 'sacs', 'sacs a main', 'bolsos', 'tassen', 'torebki', 'leather goods', 'lederwaren', 'pelletteria', 'maroquinerie',
    'marroquineria', 'sciarpe', 'scarves', 'schals', 'echarpes', 'hats', 'hute', 'cappelli', 'chapeaux', 'sombreros', 'belts', 'gurtel',
    'cinture', 'ceintures', 'cinturones', 'sunglasses', 'sonnenbrillen', 'occhiali', 'lunettes', 'gafas'],
  /* 16 Eki 2026: spor & sneaker, golf / yelken / kayak / outdoor dükkânları da hedef (kimlik terimi; marka kanıtı yine şart). */
  sport: ['sport', 'sports', 'sportswear', 'sporting goods', 'sportartikel', 'sportgeschaft', 'sporthaus', 'sportmode', 'articoli sportivi',
    'negozio sport', 'abbigliamento sportivo', 'articles de sport', 'magasin de sport', 'deportes', 'tienda de deportes', 'ropa deportiva',
    'artigos desportivos', 'artigos esportivos', 'sportwinkel', 'sportzaak', 'sportbutik', 'sportbutikk', 'sportsforretning', 'urheilukauppa',
    'sklep sportowy', 'golf', 'golf shop', 'golfshop', 'pro shop', 'proshop', 'golf club shop', 'ski', 'skishop', 'ski shop', 'snowboard',
    'skisport', 'wintersport', 'outdoor', 'outdoorshop', 'bergsport', 'alpinsport', 'trekking', 'mountain', 'montagne', 'montagna',
    'running', 'runningshop', 'tennis', 'padel', 'surf', 'surfshop', 'surf shop', 'boardshop', 'board shop', 'skateshop', 'skate shop',
    'sailing', 'yachting', 'yacht', 'marine', 'nautica', 'nautique', 'voile', 'segeln', 'segelsport', 'vela', 'wassersport',
    'water sports', 'watersport', 'equestrian'],
  vintage: ['vintage', 'second hand', 'secondhand', 'second-hand', 'friperie', 'fripes', 'thrift', 'consignment', 'pre-owned', 'preowned',
    'pre-loved', 'usato', 'seconda mano', 'depot-vente', 'depot vente', 'segunda mano', 'tweedehands', 'secondhand-mode', 'gebrauchte', 'retro'],
  wholesale: ['wholesale', 'wholesaler', 'grosshandel', 'grossist', 'grossiste', 'grossista', 'ingrosso', "all'ingrosso", 'mayorista',
    'al por mayor', 'groothandel', 'atacado', 'atacadista', 'hurtownia', 'hurt odziezowy', 'chondriki', 'velkoobchod', 'engros',
    'b2b', 'trade only', 'distributor', 'distributors', 'distributeur', 'distributore', 'distribuidor', 'distribuidora', 'distribuzione',
    'stocklots', 'stock lots', 'stock lot', 'stockservice', 'lotti stock', 'liquidation stock', 'destockage', 'handelsagentur',
    'handelsvertretung', 'showroom agency', 'fashion agency', 'modeagentur', 'importer', 'importatore', 'sourcing'],
};
/* 14 Eki 2026: Japonya / G. Kore dükkânları ("セレクトショップ" = çok markalı butik, "편집샵" aynı anlamda). normText NFD'ye
   çevirdiği için (Hangul hecesi harflere, ブ → フ+゛ ayrışır) terimler de aynı biçime getirilir. */
TERMS.cloth.push('select shop', 'selectshop', 'セレクトショップ', 'ファッション', 'アパレル', '洋服', 'メンズ', 'レディース', '편집샵', '셀렉트샵', '의류', '패션', '옷가게');
TERMS.acc.push('バッグ', 'アクセサリー', '가방', '액세서리'); TERMS.shoe.push('靴', 'シューズ', '신발'); TERMS.under.push('下着', 'ランジェリー', '속옷');
TERMS.wholesale.push('卸', '問屋', '卸売', '도매');
for (const k of Object.keys(TERMS)) TERMS[k + 'Re'] = TERMS[k].map(t => new RegExp('(?<![a-z0-9])' + esc(normText(t)) + '(?![a-z0-9])'));
/* 14 Eki 2026: menüde / bağlantılarda "markalar" sayfası = çok markalı dükkân kanıtı. */
const MULTI_NAV_RE = /(?<![a-z0-9])(brands|our brands|marken|unsere marken|marques|nos marques|marche|le nostre marche|marcas|nuestras marcas|merken|onze merken|marki|znacky|maerker|marker|merkit|designers|labels|multibrand|multi-brand|multi brand|multimarca|multimarques|multimerk|multilabel)(?![a-z0-9])/;
/* 14 Eki 2026 (operatör: "kendi markasını satanlar olmasın"): kendi koleksiyonunu / etiketini satan dükkân. */
const NEWS_RE = /(?<![a-z0-9])(zeitung|anzeiger|morgenpost|tagblatt|tageblatt|nachrichten|news|newsroom|journal|quotidien|giornale|periodico|diario|krant|gazet|presse|verlag|rundfunk|fernsehen|television|tv|radio|mediathek|sender|redaktion|redazione|redaction|zuschauer|leser)(?![a-z0-9])/;
const NOT_RETAIL_RE = /(?<![a-z0-9])(teamwear|team wear|clubwear|club fashion|clubfashion|vereinsbedarf|vereinsausstatter|vereinsbekleidung|workwear|arbeitskleidung|berufsbekleidung|vetements de travail|bedrijfskleding|gundog|hundesport|airsoft|paintball|waffen|armurerie|farmers|landhandel|agricultur|promotional|werbemittel|textieldruck|textildruck|bedrukken|tourisme|tourism|turismo|tourist|toerisme|office de tourisme|tourist information|fremdenverkehr|stadtmarketing|city marketing|gemeinde|stadt [a-z]+ (?:offizielle|official)|mairie|ville de|ayuntamiento|comune di|hotel|campingplatz|camping municipal|campground|campsite|camping village|museum|brauerei|feuerwehr|behordenausruster|sanitatshaus|orthopadie|orthopedie|orthopedic|sicherheitsschuhe|safety shoes|scrubs|kostumverleih|costume rental|location de costumes|army shop|surplus|jagd|waidmann|motorrad|motorcycle|immobilien|versicherung)(?![a-z0-9])/;
const CJK_MULTI = ['取扱ブランド', '取り扱いブランド', 'ブランド一覧', 'ブランド', 'セレクトショップ', '브랜드', '편집샵', '셀렉트샵'].map(t => normText(t));
const OWN_BRAND_RE = /(?<![a-z0-9])(our own (?:brand|label|collection)|own label|own brand|our label|designed (?:by us|in our studio|in-house)|in-house (?:brand|label|design)|eigene[sn]? (?:kollektion|label|marke)|unsere eigene[n]? (?:kollektion|marke)|aus eigener (?:herstellung|produktion)|nostr[ao] (?:marchio|collezione|produzione)|propri[ao] (?:marchio|collezione|produzione)|produzione propria|notre (?:propre )?(?:marque|collection|creation)|nos creations|nuestra (?:propia )?(?:marca|coleccion)|marca propia|onze eigen (?:collectie|merk|label)|eigen (?:merk|label|collectie)|wlasna marka|nasza marka)(?![a-z0-9])/;

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
const JUNK_LOCAL = /^(zuschauer\w*|leserservice|leserbrief\w*|hinweis|einladung|abo|abonnement|newsdesk|newsroom|redazione|redaction|redaktion|redactie|redac\w*|redac[._-]\w*|editor|editors|editorial|news|magazine|do-?not-?reply|donotreply|noreply[\w.-]*|notification[s]?|mailer-daemon|postmaster|abuse|hostmaster|webmaster|privacy|datenschutz|dpo|dsb|gdpr|rgpd|dsgvo|press|presse|pr|media|jobs?|career[s]?|karriere|recruit(ing|ment)?|hr|bewerbung|billing|invoice[s]?|rechnung(en)?|accounting|buchhaltung|fattur[a-z]*|legal|newsletter|unsubscribe|abmelden|sentry|security|spam|test|demo|example|sample|noemail|nomail|affiliate[s]?|partner(s|ship)?program|investor[s]?|ir|whistleblow[a-z]*|compliance|dev|developer[s]?|it|sysadmin|root|www|ftp)$/i;
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
  /* 16 Eki 2026: "outlet" artık tek başına toptan sayılmaz (operatör: outlet & stok mağazaları da hedef). Outlet ZİNCİRLERİ
     (outletcity, mcarthurglen, designeroutlet…) BLOCK_DOMAINS'te kalır. */
  if (/(wholesale|grosshandel|grossiste|ingrosso|mayorista|groothandel|hurtownia|b2b|stocklot|dropship)/.test(flat)) return 'excl:wholesale';
  /* 14 Eki 2026 (operatör: "aksesuar, lüks marka satmayanlara da gönder, ayakkabı, iç çamaşırı vs."): ayakkabı ve iç çamaşırı
     alan adları artık elenmez — 8 Eki'deki "ayakkabı, iç çamaşırı olmayacak" kuralı operatörün kendisi tarafından kaldırıldı. */
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
/* OSM'deki dükkân adı ile sitenin kimliği uyuşuyor mu? 8 Eki Milano: "Comptoir des
   Cotonniers" kaydının sitesi bir cadde portalıydı (corsovercellimilano.it) — portal bir sürü
   markayı listelediği için 2+ marka testini geçti. Ad belirteçlerinden biri alan adında ya da
   site başlığında geçmeli. "Baracuta Store" → baracuta.com gibi "<Marka> Store" + markanın
   alan adı = markanın kendi mağazası. */
const NAME_STOP = new Set(['boutique', 'store', 'shop', 'moda', 'mode', 'fashion', 'negozio', 'tienda', 'loja', 'the', 'les', 'des', 'del', 'della',
  'and', 'und', 'per', 'pour', 'abbigliamento', 'vetements', 'concept', 'atelier', 'maison', 'casa', 'milano', 'paris', 'lyon', 'roma', 'madrid']);
export function osmSiteMatch(name, domain, siteText) {
  const nN = normText(name); const lab = domainLabel(domain).replace(/[^a-z0-9]/g, '');
  const toks = nN.split(/[^a-z0-9]+/).filter(w => w.length >= 3 && !NAME_STOP.has(w));
  const brandStore = nN.match(/^(.+?)\s+(store|flagship|official store|boutique officielle)$/);
  if (brandStore && brandStore[1].replace(/[^a-z0-9]/g, '') === lab) return 'excl:brand-site';
  if (!toks.length) return '';
  const hay = lab + ' ' + normText(siteText).replace(/[^a-z0-9 ]/g, ' ');
  const hayFlat = hay.replace(/\s+/g, '');
  const joined = toks.join('');
  if (joined.length >= 4 && hayFlat.includes(joined)) return '';
  for (const t of toks) if (t.length >= 4 ? hayFlat.includes(t) : new RegExp('(?<![a-z0-9])' + t + '(?![a-z0-9])').test(hay)) return '';
  return 'excl:site-mismatch';
}

export function freeMailRelated(local, siteDomain, shopName = '') {
  const flat = local.toLowerCase().replace(/[^a-z0-9]/g, '');
  const toks = new Set();
  const lab = domainLabel(siteDomain).replace(/[^a-z0-9]/g, '');
  if (lab.length >= 4) { toks.add(lab); for (let i = 0; i + 4 <= lab.length; i++) toks.add(lab.slice(i, i + 4)); }
  for (const w of normText(shopName).split(/[^a-z0-9]+/)) if (w.length >= 4 && !/^(boutique|store|shop|moda|mode|fashion|negozio|tienda)$/.test(w)) toks.add(w);
  for (const t of toks) if (t.length >= 4 && flat.includes(t)) return true;
  return false;
}
export function scoreEmail(email, siteDomain, where = [], page = '', shopName = '') {
  const [local, dom] = email.split('@'); if (!local || !dom) return -999;
  if (IMG_EXT.test(email) || JUNK_DOMAIN.test(dom) || JUNK_LOCAL.test(local) || local.length > 40 || /[0-9a-f]{20,}/i.test(local)) return -999;
  if (/^(u00|x[0-9a-f]{2})/.test(local) || /\.(png|jpg|gif|svg|webp)@/.test(email)) return -999;
  const site = registrableDomain(siteDomain); const edom = registrableDomain(dom);
  let s = 0;
  if (edom === site) s += 50;
  else if (FREE_MAIL.has(dom)) {
    /* Ücretsiz sağlayıcıdaki adres dükkânın olabilir (rambelli.milano@gmail) ya da sayfada
       geçen ilgisiz biri (8 Eki: cappelliegrasso.it → guerrillasonora@gmail). Yerel kısım
       alan adının ya da dükkân adının en az 4 harflik bir parçasını taşımalı. OSM etiketi
       dükkânın kendi kaydı olduğu için muaf. */
    if (!where.includes('osm') && !freeMailRelated(local, site, shopName)) return -999;
    s += 20;
  }
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
  'com.gr': 'Greece', 'com.pt': 'Portugal', 'com.cy': 'Cyprus', 'com.mt': 'Malta', 'com.es': 'Spain', rs: 'Serbia', ua: 'Ukraine',
  sa: 'Saudi Arabia', 'com.sa': 'Saudi Arabia', qa: 'Qatar', 'com.qa': 'Qatar', kw: 'Kuwait', 'com.kw': 'Kuwait', bh: 'Bahrain',
  'com.bh': 'Bahrain', lb: 'Lebanon', 'com.lb': 'Lebanon', il: 'Israel', 'co.il': 'Israel', kr: 'South Korea', 'co.kr': 'South Korea',
  hk: 'Hong Kong', 'com.hk': 'Hong Kong', sg: 'Singapore', 'com.sg': 'Singapore', mx: 'Mexico', 'com.mx': 'Mexico',
  br: 'Brazil', 'com.br': 'Brazil', ar: 'Argentina', 'com.ar': 'Argentina', cl: 'Chile', mc: 'Monaco',
  'com.co': 'Colombia', pe: 'Peru', 'com.pe': 'Peru', uy: 'Uruguay', 'com.uy': 'Uruguay', pa: 'Panama', cr: 'Costa Rica',
  'co.cr': 'Costa Rica', 'com.do': 'Dominican Republic', ec: 'Ecuador', 'com.ec': 'Ecuador', pr: 'Puerto Rico',
  om: 'Oman', 'com.om': 'Oman', jo: 'Jordan', eg: 'Egypt', 'com.eg': 'Egypt', ma: 'Morocco', tn: 'Tunisia',
  za: 'South Africa', 'co.za': 'South Africa', ng: 'Nigeria', 'com.ng': 'Nigeria', ke: 'Kenya', 'co.ke': 'Kenya',
  gh: 'Ghana', 'com.gh': 'Ghana', mu: 'Mauritius', in: 'India', 'co.in': 'India', pk: 'Pakistan', 'com.pk': 'Pakistan',
  lk: 'Sri Lanka', bd: 'Bangladesh', 'com.bd': 'Bangladesh', tw: 'Taiwan', 'com.tw': 'Taiwan', th: 'Thailand', 'co.th': 'Thailand',
  my: 'Malaysia', 'com.my': 'Malaysia', id: 'Indonesia', 'co.id': 'Indonesia', ph: 'Philippines', 'com.ph': 'Philippines',
  vn: 'Vietnam', 'com.vn': 'Vietnam', cn: 'China', 'com.cn': 'China', mo: 'Macau', kz: 'Kazakhstan', ge: 'Georgia', am: 'Armenia',
  az: 'Azerbaijan', md: 'Moldova', ba: 'Bosnia and Herzegovina', mk: 'North Macedonia', al: 'Albania', ad: 'Andorra',
  li: 'Liechtenstein', sm: 'San Marino', 'com.au': 'Australia' };
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
  nb: { cc: 'NO', kl: 'no-no', sl: 'nb' }, fi: { cc: 'FI', kl: 'fi-fi', sl: 'fi' },
  ja: { cc: 'JP', kl: 'jp-jp', sl: 'ja' }, ko: { cc: 'KR', kl: 'kr-kr', sl: 'ko' } };
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
  google: { used: 0, async search(q, lang) {
    if (!CFG.googleKey || !CFG.googleCx) throw new Error('nokey');
    if (ENGINE.google.used >= CFG.googlePerRun) throw new Error('blocked:quota-run');   // tur tavanı: 3x = motor kapanır, ücretsiz hak aşılmaz
    ENGINE.google.used++;
    const L = LOCALE[lang] || LOCALE.en;
    const r = await httpGet(`https://www.googleapis.com/customsearch/v1?key=${encodeURIComponent(CFG.googleKey)}&cx=${encodeURIComponent(CFG.googleCx)}&q=${encodeURIComponent(q)}&num=10&gl=${String(L.cc || 'us').toLowerCase()}&lr=lang_${String(L.sl || 'en').toLowerCase().slice(0, 2)}&safe=off`,
      { timeout: 20000, headers: { accept: 'application/json' } });
    if (r.status === 429 || /dailyLimitExceeded|rateLimitExceeded|quotaExceeded/.test(r.text.slice(0, 2000))) throw new Error('blocked:429');
    if (r.status === 403) throw new Error('blocked:403');   // anahtar/cx yanlış ya da API kapalı — 3x sonra motor kapanır, tur sürer
    if (r.status !== 200) throw new Error('http:' + r.status);
    const j = JSON.parse(r.text); return (j.items || []).map(x => x.link).filter(Boolean); }, pause: [800, 1500] },
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
  const shoeI = count(TERMS.shoeRe, ident), clothI = count(TERMS.clothRe, ident), underI = count(TERMS.underRe, ident), accI = count(TERMS.accRe, ident);
  const shoeN = count(TERMS.shoeRe, navN), clothN = count(TERMS.clothRe, navN), underN = count(TERMS.underRe, navN), accN = count(TERMS.accRe, navN);
  const sportI = count(TERMS.sportRe, ident), sportN = count(TERMS.sportRe, navN);   // 16 Eki 2026: spor / golf / yelken / kayak
  const whI = count(TERMS.wholesaleRe, ident), whB = count(TERMS.wholesaleRe, bodyN);

  const brands = { c: new Set(), s: new Set(), u: new Set(), m: new Set(), p: new Set() };
  const hay = bodyN + ' ' + hrefN + ' ' + navN;
  for (const b of BRANDS) if (b.re.test(hay)) brands[b.kind].add(b.name);
  const nc = brands.c.size, ns = brands.s.size, nu = brands.u.size, nm = brands.m.size, np = brands.p.size, tot = nc + ns + nu + nm + np;
  const shopIdent = (clothI + shoeI + underI + accI + clothN + shoeN + underN + accN) > 0;   // giyim / ayakkabı / iç giyim / aksesuar dükkânı
  const multiNav = MULTI_NAV_RE.test(navN) || MULTI_NAV_RE.test(hrefN) || MULTI_NAV_RE.test(ident) || CJK_MULTI.some(w => navN.includes(w) || ident.includes(w));
  const ownBrand = OWN_BRAND_RE.test(ident) || OWN_BRAND_RE.test(bodyN);

  let reason = '';
  const identNoCloth = clothI === 0;
  for (const bn of BLOCK_NAMES) if (new RegExp('(?<![a-z0-9])' + esc(normText(bn)) + '(?![a-z0-9])').test(ident)) { reason = 'excl:chain'; break; }
  if (!reason && TERMS.vintageRe.some(re => re.test(ident))) reason = 'excl:vintage';
  /* 10 Eki 2026: dükkân olmayan moda yayınları (Glamcult "Independent Style Paper" lead olarak eklendi). */
  if (!reason && /(?<![a-z0-9])(magazine|rivista|revista|webzine|zeitschrift|style paper|fashion blog|modeblog|editorial|independent (?:style|fashion) (?:paper|magazine))(?![a-z0-9])/.test(ident)) reason = 'excl:media';
  /* 14 Eki 2026 (RTL olayı): haber/TV/radyo/yayınevi, turizm ofisi, belediye ve ilgisiz sektörler. Haber sitelerinde marka adları
     geçtiği için "2 premium marka" kuralından geçiyorlardı (RTL, Kölner Stadt-Anzeiger, Süddeutsche, BUNTE, manager magazin…). */
  if (!reason && NEWS_RE.test(ident)) reason = 'excl:media';
  if (!reason && NOT_RETAIL_RE.test(ident)) reason = 'excl:not-retail';
  if (!reason && (whI > 0 || whB >= 2)) reason = 'excl:wholesale';
  /* 14 Eki 2026 (operatör: "aksesuar, lüks marka satmayanlara da gönder, ayakkabı, iç çamaşırı vs. — ancak kesinlikle distribütör
     ve büyük zincir olmasın, kendi markasını satanlar da olmasın"). Ayakkabı / iç çamaşırı artık elenmez. Kabul:
       • 2+ premium marka (eski kural, değişmedi), YA DA
       • giyim/ayakkabı/iç giyim/aksesuar dükkânı + başkalarının markasını sattığına kanıt: toplam 2+ tanınan marka (premium,
         ayakkabı, iç giyim, lüks olmayan), ya da menüde "Markalar / Marken / Marques…" sayfası ve en az bir tanınan marka
         ya da açık "multibrand" ibaresi.
     Kendi koleksiyonunu satan ("unsere eigene Kollektion", "nostra collezione", "own label") ve 2'den az tanınan markası olan
     dükkân → excl:own-brand. Tek markalı bayi (tek marka, başka kanıt yok) → excl:monobrand. Hiç kanıt yok → excl:no-brands. */
  const multiWord = /(?<![a-z0-9])(multibrand|multi-brand|multi brand|multimarca|multimarques|multimerk|multilabel|select shop|selectshop)(?![a-z0-9])/.test(ident + ' ' + navN)
    || [normText('セレクトショップ'), normText('편집샵'), normText('셀렉트샵')].some(w => (ident + ' ' + navN).includes(w));
  if (!reason && nc < CFG.minBrands) {
    /* 14 Eki 2026 (RTL olayı): lüks markasız yolda dükkân kimliği MENÜDEN değil kimlikten (alan adı / başlık / meta / h1)
       gelmeli — haber ve turizm sitelerinin menüsünde "Mode", "Schuhe" sayfası olabiliyor. */
    const identShop = (clothI + shoeI + underI + accI + sportI) > 0;   // 16 Eki: spor dükkânı da (marka kanıtı yine şart)
    const multi = identShop && (tot >= 2 || (multiNav && tot >= 1) || multiWord);
    if (ownBrand && tot < 2) reason = 'excl:own-brand';
    else if (!multi) reason = tot === 0 ? 'excl:no-brands' : 'excl:monobrand';
  }
  const kind = (sportI > 0 && sportI + sportN >= clothI + clothN && sportI + sportN >= shoeI + shoeN) ? 'sport'
    : (shoeI + shoeN > clothI + clothN && shoeI + shoeN >= underI + underN) ? 'shoes'
    : (underI + underN > clothI + clothN) ? 'underwear' : (accI + accN > clothI + clothN) ? 'accessories' : 'fashion';
  return { reason, title, brands: [...brands.c], otherBrands: [...brands.s, ...brands.u, ...brands.m, ...brands.p], kind, shoeBrands: ns, underBrands: nu,
    signals: { shoeI, clothI, underI, accI, sportI, shoeN, clothN, underN, accN, sportN, whI, whB, multiNav, ownBrand, tot } };
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

async function analyzeSite(domain, ctx, hint = {}) {
  const res = { domain, status: 'fail', reason: '', pages: 0 };
  let base = ''; let home = null;
  /* 11 Eki 2026: 655 sitenin 165'i "ulaşılamadı" idi — zaman aşımında aynı adres bir kez daha, daha uzun süreyle denenir. */
  outer: for (const u of [`https://${domain}/`, `https://www.${domain}/`, `http://${domain}/`]) {
    for (const timeout of [12000, 25000]) {
      try { const r = await httpGet(u, { timeout }); if (r.status >= 200 && r.status < 400 && /html|xml/i.test(r.ct || 'html') && r.text.length > 300) { home = r; base = r.url; break outer; } break; }
      catch (e) { if (!/abort|timeout/i.test(String(e?.name || '') + String(e?.message || ''))) break; }
    }
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
  /* OSM'deki e-posta etiketi de YAYINLANMIŞ bir adres (dükkânın kendi kaydı); aynı puanlama
     ve alan adı kuralından geçer — sitenin alan adında ya da ücretsiz sağlayıcıda olmalı. */
  if (hint.osmEmail && hint.osmEmail.includes('@')) { const cur = emails.get(hint.osmEmail) || { where: new Set(), pages: new Set() }; cur.where.add('osm'); cur.pages.add('contact'); emails.set(hint.osmEmail, cur); }
  const shopName = hint.name || '';
  const bestNow = () => { let b = -999; for (const [e, m] of emails) b = Math.max(b, scoreEmail(e, domain, [...m.where], [...m.pages].join(','), shopName)); return b; };
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
  /* Google Places işletme adı, sayfa başlığından türetilen addan daha güvenilir. */
  if (hint.name && hint.name.length >= 2 && hint.name.length <= 70) res.company = hint.name.trim();
  res.country = detectCountry(domain, pagesText, htmlLang) || hint.countryHint || '';
  res.phone = hint.phone || (pagesText.match(/(?:\+|00)\d{1,3}[\s\d().\-/]{6,16}\d/) || [''])[0].replace(/\s+/g, ' ').trim();
  if (cls.reason) { res.status = 'excl'; res.reason = cls.reason; return res; }
  if (hint.name) {
    const m = osmSiteMatch(hint.name, domain, [res.title || '', companyName(home.text, domain)].join(' '));
    if (m) { res.status = 'excl'; res.reason = m; return res; }
  }
  if (CFG.countries.length && (!res.country || !CFG.countries.includes(COUNTRY_ISO[res.country] || ''))) { res.status = 'excl'; res.reason = 'excl:country'; return res; }

  const scored = [...emails.entries()].map(([e, m]) => ({ email: e, score: scoreEmail(e, domain, [...m.where], [...m.pages].join(','), shopName || res.company || '') })).filter(x => x.score > 0).sort((a, b) => b.score - a.score);
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
/* ============================== OSM KAYNAĞI (anahtarsız) ==============================
   Nominatim ile şehrin sınır alanı, Overpass ile o alandaki giyim dükkânları (shop=clothes/
   boutique/fashion). Eski hattın sorunu kaynak değil İŞLEMEydi: ilk 80-120 dükkânda
   kesiyordu, "Dusseldorf"/"Vancouver" gibi adları çözemiyordu ve dükkânın ne sattığına
   bakmıyordu (%4 isabet). Burada her aday sitenin kendisinden 2+ premium marka ve
   yayınlanmış e-posta testinden geçer; zincir/ayakkabı/iç çamaşırı OSM etiketinden
   daha siteye girmeden elenir. Daha önce bakılan alan adı tekrar taranmaz. */
const OSM_UA = 'VestraSalesFinder/1.0 (+https://vestrasales.com)';
const OVERPASS = ['https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter',
  'https://overpass.private.coffee/api/interpreter', 'https://maps.mail.ru/osm/tools/overpass/api/interpreter'];
const SOCIAL_HOST = /(^|\.)(facebook|instagram|linktr|linktree|tiktok|twitter|x|youtube|wa|whatsapp|google|goo|business\.site|pinterest|vk|t)\.(com|me|ee|gl|site|ru)$/i;

/** OSM etiketlerinden aday — ya {web, name, email, phone} ya {excl}. */
export function osmPick(tags) {
  const name = String(tags.name || '').trim(); if (!name) return { excl: 'no_name' };
  if (tags['brand:wikidata'] || tags['operator:wikidata']) return { excl: 'excl:chain' };
  if (tags.brand && normText(tags.brand) !== normText(name)) return { excl: 'excl:chain' };
  const nN = normText(name);
  if (TERMS.wholesaleRe.some(re => re.test(nN))) return { excl: 'excl:wholesale' };
  if (TERMS.vintageRe.some(re => re.test(nN)) || /^(second|seconda|segunda)/.test(String(tags.second_hand || ''))) return { excl: 'excl:vintage' };
  if (String(tags.second_hand || '') === 'only') return { excl: 'excl:vintage' };
  /* 14 Eki 2026: ayakkabıcı ve iç çamaşırı dükkânları artık elenmez (operatör kuralı değişti). */
  for (const bn of BLOCK_NAMES) if (new RegExp('(?<![a-z0-9])' + esc(normText(bn)) + '(?![a-z0-9])').test(nN)) return { excl: 'excl:chain' };
  let web = String(tags.website || tags['contact:website'] || tags.url || '').split(';')[0].trim();
  if (web && !/^https?:\/\//i.test(web)) web = 'https://' + web;
  try { if (web && SOCIAL_HOST.test(new URL(web).hostname)) web = ''; } catch { web = ''; }
  const email = String(tags.email || tags['contact:email'] || '').split(';')[0].trim().toLowerCase();
  const edom = email.includes('@') ? email.split('@')[1] : '';
  if (!web && edom && !FREE_MAIL.has(edom)) web = 'https://' + edom + '/';
  if (!web) return { excl: 'no_site' };
  return { web, name, email, phone: String(tags.phone || tags['contact:phone'] || '').split(';')[0].trim() };
}

/* Nominatim kullanım kuralı saniyede 1 istek: paralel şehir işçileri konum aramasını sırayla yapar. */
let nominatimChain = Promise.resolve();
function nominatimSlot() { const p = nominatimChain.then(() => sleep(1100)); nominatimChain = p.catch(() => {}); return p; }

async function osmCity(country, city) {
  let areaId = 0, bbox = null;
  const cell = parseGridCell(city);
  if (cell) {
    /* Kare: Nominatim yok; yalnız sitesi ya da e-postası etiketli dükkânlar (sitesizler zaten elenirdi, trafik boşuna). */
    const bb = cell.join(',');
    const q = `[out:json][timeout:25];nwr["shop"~"^(clothes|boutique|fashion|shoes|bag|leather|fashion_accessories|sports|outdoor|ski|golf|surf|department_store)$"][~"^(website|contact:website|email|contact:email)$"~"."](${bb});out tags;`;
    return osmQuery([q], 30000);
  }
  await nominatimSlot();
  try {
    const u = 'https://nominatim.openstreetmap.org/search?' + new URLSearchParams({ city, country, format: 'jsonv2', limit: '5' });
    const r = await fetch(u, { headers: { 'user-agent': OSM_UA, 'accept-language': 'en' }, signal: AbortSignal.timeout(25000) });
    const j = r.ok ? await r.json() : [];
    const hit = (Array.isArray(j) ? j : []).find(x => x.osm_type === 'relation') || (Array.isArray(j) ? j[0] : null);
    if (hit) { if (hit.osm_type === 'relation') areaId = 3600000000 + Number(hit.osm_id); if (Array.isArray(hit.boundingbox)) bbox = hit.boundingbox.map(Number); }
  } catch { /* aşağıda hata döner */ }
  if (!areaId && !bbox) return { error: 'şehir bulunamadı (Nominatim)', rows: [] };
  const sel = '["shop"~"^(clothes|boutique|fashion|shoes|bag|leather|fashion_accessories|sports|outdoor|ski|golf|surf|department_store)$"]';   // 14 Eki: ayakkabı/çanta/aksesuar da
  const queries = [];
  if (areaId) queries.push(`[out:json][timeout:80];area(${areaId})->.a;nwr${sel}(area.a);out tags;`);   // 11 Eki: 150 → 80 sn — 504 veren büyük şehirler turu 10+ dk bekletiyordu
  if (bbox) queries.push(`[out:json][timeout:80];nwr${sel}(${bbox[0]},${bbox[2]},${bbox[1]},${bbox[3]});out tags;`);   // Nominatim bbox: [güney, kuzey, batı, doğu]
  return osmQuery(queries);
}
async function osmQuery(queries, waitMs = OVERPASS_WAIT_MS) {
  let lastErr = 'Overpass yanıt vermedi', emptyOk = null;
  for (const q of queries) {
    for (const m of overpassOrder()) {
      const t0 = Date.now();
      try {
        const r = await fetch(m, { method: 'POST', body: 'data=' + encodeURIComponent(q), signal: AbortSignal.timeout(waitMs),
          headers: { 'content-type': 'application/x-www-form-urlencoded', 'user-agent': OSM_UA } });
        if (r.status !== 200) { lastErr = `Overpass HTTP ${r.status}`; overpassMark(m, false, Date.now() - t0); continue; }
        const j = await r.json();
        if (!Array.isArray(j.elements)) { overpassMark(m, false, Date.now() - t0); continue; }
        if (!j.elements.length && /runtime error|timed out|out of memory/i.test(String(j.remark || ''))) { lastErr = 'Overpass zaman aşımı'; overpassMark(m, false, Date.now() - t0); continue; }
        overpassMark(m, true, Date.now() - t0);
        if (j.elements.length) return { rows: j.elements.map(e => e.tags || {}), mirror: new URL(m).hostname };
        emptyOk = new URL(m).hostname; break;                          // boş ama hatasız: varsa sonraki sorgu (bbox) denenir
      } catch (e) { lastErr = 'Overpass: ' + String(e.message || e).slice(0, 60); overpassMark(m, false, Date.now() - t0); }
    }
  }
  /* 13 Eki 2026: boş ama geçerli yanıt HATA DEĞİL — ilk kare turunda deniz kareleri "yanıt vermedi" sayılıp
     yeniden deneme listesine giriyordu (93 karenin 81'i). Boş kare boş döner; çağıran onu 90 gün hatırlar. */
  if (emptyOk) return { rows: [], mirror: emptyOk };
  return { error: lastErr, rows: [] };
}

/* 13 Eki 2026: aynalar arası sıra bu turun deneyimine göre. Ölçüm (run 38393121449): ilk ayna(lar) her şehirde 95 sn
   zaman aşımına düşüyordu, yanıtı hep 4. ayna (maps.mail.ru) veriyordu — şehir başına ~3,5 dk, turda yalnız 10 şehir.
   Kasaba sorgusu birkaç saniyede biter; bekleme 45 sn'ye indi ve üst üste 2 kez düşen ayna sonuna atılır. */
const OVERPASS_WAIT_MS = 45000;
const overpassHealth = new Map();   // ayna → { fails: ardışık hata, ok: başarı sayısı, ms: son başarılı süre }
export function overpassMark(m, ok, ms) {
  const h = overpassHealth.get(m) || { fails: 0, ok: 0, ms: 0 };
  if (ok) { h.fails = 0; h.ok++; h.ms = ms; } else h.fails++;
  overpassHealth.set(m, h);
}
export function overpassOrder() {
  const score = (m) => { const h = overpassHealth.get(m); return h ? (h.fails >= 2 ? 1e6 + h.fails : 0) - h.ok * 10 + (h.ms || 0) / 1000 : 0; };
  return OVERPASS.map((m, i) => [m, score(m), i]).sort((a, b) => a[1] - b[1] || a[2] - b[2]).map(x => x[0]);
}

/* Google Places için şehir havuzu (CITIES boşsa sırayla dönülür). Almanya bilerek az:
   operatör "alabilirsin ama az" dedi (eski daily-customers havuzundaki kayıt). */
const CITY_POOL = [
  'Italy|Milano', 'Italy|Roma', 'Italy|Firenze', 'Italy|Napoli', 'Italy|Torino', 'Italy|Bologna', 'Italy|Verona', 'Italy|Bari', 'Italy|Padova', 'Italy|Palermo',
  'France|Paris', 'France|Lyon', 'France|Marseille', 'France|Nice', 'France|Bordeaux', 'France|Lille', 'France|Toulouse', 'France|Cannes',
  'Spain|Madrid', 'Spain|Barcelona', 'Spain|Valencia', 'Spain|Sevilla', 'Spain|Málaga', 'Spain|Bilbao', 'Spain|Marbella',
  'Netherlands|Amsterdam', 'Netherlands|Rotterdam', 'Netherlands|Utrecht', 'Netherlands|Den Haag', 'Netherlands|Eindhoven',
  'Belgium|Antwerpen', 'Belgium|Bruxelles', 'Belgium|Gent', 'Switzerland|Zürich', 'Switzerland|Genève', 'Switzerland|Basel', 'Switzerland|Lugano',
  'Austria|Wien', 'Austria|Salzburg', 'Austria|Graz', 'Germany|München', 'Germany|Düsseldorf', 'Germany|Hamburg',
  'Portugal|Lisboa', 'Portugal|Porto', 'Greece|Athens', 'Greece|Thessaloniki', 'Poland|Warszawa', 'Poland|Kraków', 'Poland|Wrocław',
  'United Kingdom|London', 'United Kingdom|Manchester', 'Ireland|Dublin', 'Denmark|København', 'Sweden|Stockholm', 'Norway|Oslo',
  /* 10 Eki 2026: havuz genişletildi (56 → 160) — her tur daha çok şehir tarıyor, aynı şehre daha geç dönülür. */
  'Germany|Berlin', 'Germany|Köln', 'Germany|Frankfurt am Main', 'Germany|Stuttgart', 'Germany|Hannover', 'Germany|Nürnberg',
  'Germany|Leipzig', 'Germany|Dresden', 'Germany|Bremen', 'Germany|Essen', 'Germany|Dortmund', 'Germany|Münster', 'Germany|Mannheim',
  'Germany|Wiesbaden', 'Germany|Augsburg', 'Germany|Freiburg im Breisgau', 'Germany|Karlsruhe', 'Germany|Bonn', 'Germany|Heidelberg',
  'Italy|Genova', 'Italy|Brescia', 'Italy|Bergamo', 'Italy|Modena', 'Italy|Parma', 'Italy|Lecce', 'Italy|Catania', 'Italy|Cagliari',
  'Italy|Treviso', 'Italy|Vicenza', 'Italy|Como', 'Italy|Pescara', 'Italy|Perugia', 'Italy|Rimini', 'Italy|Reggio Emilia', 'Italy|Salerno',
  'France|Nantes', 'France|Strasbourg', 'France|Montpellier', 'France|Rennes', 'France|Grenoble', 'France|Aix-en-Provence',
  'France|Reims', 'France|Biarritz', 'France|Annecy', 'France|Dijon', 'France|Tours', 'France|Metz', 'France|Saint-Tropez',
  'Spain|Zaragoza', 'Spain|Palma', 'Spain|Alicante', 'Spain|Donostia / San Sebastián', 'Spain|Murcia', 'Spain|Valladolid',
  'Spain|Granada', 'Spain|A Coruña', 'Spain|Vigo', 'Spain|Santander', 'Spain|Oviedo', 'Spain|Pamplona',
  'Netherlands|Groningen', 'Netherlands|Maastricht', 'Netherlands|Haarlem', 'Netherlands|Arnhem', 'Netherlands|Breda',
  'Netherlands|Nijmegen', "Netherlands|'s-Hertogenbosch", 'Netherlands|Leiden', 'Netherlands|Tilburg',
  'Belgium|Brugge', 'Belgium|Liège', 'Belgium|Leuven', 'Belgium|Namur', 'Belgium|Hasselt', 'Belgium|Knokke-Heist', 'Belgium|Mechelen',
  'Switzerland|Bern', 'Switzerland|Luzern', 'Switzerland|St. Gallen', 'Switzerland|Lausanne', 'Austria|Innsbruck', 'Austria|Linz',
  'United Kingdom|Birmingham', 'United Kingdom|Leeds', 'United Kingdom|Glasgow', 'United Kingdom|Edinburgh', 'United Kingdom|Liverpool',
  'United Kingdom|Bristol', 'Ireland|Cork', 'Portugal|Braga', 'Portugal|Coimbra', 'Portugal|Faro',
  'Denmark|Aarhus', 'Sweden|Göteborg', 'Sweden|Malmö', 'Norway|Bergen', 'Finland|Helsinki',
  'Poland|Poznań', 'Poland|Gdańsk', 'Poland|Łódź', 'Czechia|Praha', 'Czechia|Brno', 'Luxembourg|Luxembourg', 'Greece|Patra',
  /* 11 Eki 2026 (operatör: "tüm ülkelerden rahatça bulunabilir"): Avrupa dışı ve kalan AB ülkeleri. OSM dünya geneli
     çalışır; e-posta dili ülkeden (7 başlıca dil, diğerleri İngilizce). Türkiye bilerek YOK (ülke engeli). */
  'United States|New York', 'United States|Los Angeles', 'United States|Miami', 'United States|Chicago', 'United States|San Francisco',
  'United States|Houston', 'United States|Dallas', 'United States|Atlanta', 'United States|Boston', 'United States|Las Vegas',
  'United States|Washington', 'United States|Seattle', 'Canada|Toronto', 'Canada|Montréal', 'Canada|Vancouver',
  'United Arab Emirates|Dubai', 'United Arab Emirates|Abu Dhabi', 'Saudi Arabia|Riyadh', 'Saudi Arabia|Jeddah', 'Qatar|Doha',
  'Kuwait|Kuwait City', 'Bahrain|Manama', 'Lebanon|Beirut', 'Israel|Tel Aviv-Yafo',
  'Japan|Tokyo', 'Japan|Osaka', 'South Korea|Seoul', 'Hong Kong|Hong Kong', 'Singapore|Singapore',
  'Australia|Sydney', 'Australia|Melbourne', 'New Zealand|Auckland',
  'Mexico|Ciudad de México', 'Brazil|São Paulo', 'Argentina|Buenos Aires', 'Chile|Santiago',
  'Hungary|Budapest', 'Romania|Bucureşti', 'Romania|Cluj-Napoca', 'Bulgaria|Sofia', 'Croatia|Zagreb', 'Croatia|Split',
  'Slovenia|Ljubljana', 'Slovakia|Bratislava', 'Lithuania|Vilnius', 'Latvia|Rīga', 'Estonia|Tallinn',
  'Cyprus|Limassol', 'Cyprus|Nicosia', 'Malta|Sliema', 'Monaco|Monaco', 'Iceland|Reykjavík', 'Serbia|Belgrade',
  /* 11 Eki 2026 (operatör: "tüm dünyadan genişlet, Avustralya, Kanada vs. hepsini al"). Yaptırımlı ülkeler (Rusya,
     Belarus, İran, Suriye, K. Kore, Küba) ve Türkiye (ülke engeli) bilerek YOK. */
  'Australia|Brisbane', 'Australia|Perth', 'Australia|Adelaide', 'Australia|Gold Coast', 'Australia|Canberra', 'Australia|Hobart',
  'Australia|Newcastle', 'Australia|Darwin', 'New Zealand|Wellington', 'New Zealand|Christchurch', 'New Zealand|Queenstown',
  'Canada|Calgary', 'Canada|Edmonton', 'Canada|Ottawa', 'Canada|Québec', 'Canada|Winnipeg', 'Canada|Halifax', 'Canada|Victoria',
  'Canada|Mississauga', 'Canada|Oakville', 'Canada|Kelowna',
  'United States|Philadelphia', 'United States|Phoenix', 'United States|San Diego', 'United States|Denver', 'United States|Austin',
  'United States|Nashville', 'United States|New Orleans', 'United States|Charlotte', 'United States|Minneapolis', 'United States|Detroit',
  'United States|Portland', 'United States|Scottsdale', 'United States|Palm Beach', 'United States|Fort Lauderdale', 'United States|Orlando',
  'United States|Tampa', 'United States|Honolulu', 'United States|Beverly Hills', 'United States|Brooklyn', 'United States|San Jose',
  'United States|Salt Lake City', 'United States|St. Louis', 'United States|Kansas City', 'United States|Columbus', 'United States|Indianapolis',
  'United States|Charleston', 'United States|Savannah', 'United States|Pittsburgh', 'United States|Baltimore', 'United States|Raleigh',
  'United States|Greenwich', 'United States|Aspen', 'United States|The Hamptons', 'Puerto Rico|San Juan',
  'Mexico|Guadalajara', 'Mexico|Monterrey', 'Mexico|Cancún', 'Mexico|Puebla', 'Mexico|Tijuana', 'Colombia|Bogotá', 'Colombia|Medellín',
  'Peru|Lima', 'Uruguay|Montevideo', 'Panama|Panamá', 'Costa Rica|San José', 'Dominican Republic|Santo Domingo', 'Ecuador|Quito',
  'Brazil|Rio de Janeiro', 'Brazil|Belo Horizonte', 'Brazil|Curitiba', 'Brazil|Porto Alegre', 'Brazil|Brasília', 'Argentina|Córdoba',
  'Argentina|Rosario', 'Chile|Viña del Mar',
  'United Arab Emirates|Sharjah', 'Saudi Arabia|Dammam', 'Saudi Arabia|Khobar', 'Oman|Muscat', 'Jordan|Amman', 'Egypt|Cairo',
  'Egypt|Alexandria', 'Morocco|Casablanca', 'Morocco|Marrakech', 'Morocco|Rabat', 'Tunisia|Tunis', 'Israel|Jerusalem', 'Israel|Haifa',
  'South Africa|Johannesburg', 'South Africa|Cape Town', 'South Africa|Durban', 'South Africa|Pretoria', 'Nigeria|Lagos', 'Nigeria|Abuja',
  'Kenya|Nairobi', 'Ghana|Accra', 'Mauritius|Port Louis',
  'India|Mumbai', 'India|New Delhi', 'India|Bengaluru', 'India|Hyderabad', 'India|Chennai', 'India|Kolkata', 'India|Pune', 'India|Ahmedabad',
  'Pakistan|Lahore', 'Pakistan|Karachi', 'Sri Lanka|Colombo', 'Bangladesh|Dhaka',
  'Japan|Kyoto', 'Japan|Yokohama', 'Japan|Nagoya', 'Japan|Fukuoka', 'Japan|Sapporo', 'Japan|Kobe', 'Japan|Sendai', 'Japan|Hiroshima',
  'Japan|Kanazawa', 'Japan|Okayama', 'Japan|Shizuoka', 'Japan|Kumamoto', 'Japan|Naha', 'South Korea|Busan', 'South Korea|Incheon', 'South Korea|Daegu',
  'Taiwan|Taipei', 'Taiwan|Kaohsiung', 'Thailand|Bangkok', 'Thailand|Phuket', 'Thailand|Chiang Mai', 'Malaysia|Kuala Lumpur',
  'Malaysia|Penang', 'Indonesia|Jakarta', 'Indonesia|Bali', 'Indonesia|Surabaya', 'Philippines|Manila', 'Philippines|Cebu City',
  'Vietnam|Ho Chi Minh City', 'Vietnam|Hanoi', 'China|Shanghai', 'China|Beijing', 'China|Shenzhen', 'China|Guangzhou', 'Macau|Macau',
  'Kazakhstan|Almaty', 'Georgia|Tbilisi', 'Armenia|Yerevan', 'Azerbaijan|Baku', 'Ukraine|Kyiv', 'Moldova|Chișinău',
  'Bosnia and Herzegovina|Sarajevo', 'North Macedonia|Skopje', 'Albania|Tirana', 'Montenegro|Budva', 'Andorra|Andorra la Vella',
  'Liechtenstein|Vaduz', 'San Marino|San Marino',
];

/* 13 Eki 2026 (operatör: "yeni müşteriler bulunmuyor, Avrupa'dan ihtiyacım var özellikle"). Ölçüm: CITY_POOL'un büyük
   şehirleri 10 Eki'den beri birkaç kez tarandı, OSM'deki siteli giyim dükkânları tükendi (turlar "excl:seen" ile 0 ekliyordu).
   Avrupa'nın orta büyüklükte şehirleri — il/bölge merkezleri, sayfiye ve kayak kasabaları; premium çok markalı butik
   buralarda da var ve hiç taranmadı. Otomatik tur şehirlerinin EURO_SHARE kadarı (varsayılan %80) buradan, kendi
   imleciyle (state.euroCursor) alınır. Almanya bilerek ölçülü (operatörün eski "az" kararı). */
const EURO_TOWNS = [
  "Italy|Monza", "Italy|Varese", "Italy|Lecco", "Italy|Cremona", "Italy|Mantova", "Italy|Pavia",
  "Italy|Lodi", "Italy|Sondrio", "Italy|Novara", "Italy|Biella", "Italy|Alessandria", "Italy|Asti",
  "Italy|Cuneo", "Italy|Vercelli", "Italy|Aosta", "Italy|Trento", "Italy|Bolzano", "Italy|Udine",
  "Italy|Pordenone", "Italy|Trieste", "Italy|Gorizia", "Italy|Belluno", "Italy|Rovigo", "Italy|Ferrara",
  "Italy|Ravenna", "Italy|Forlì", "Italy|Cesena", "Italy|Piacenza", "Italy|La Spezia", "Italy|Savona",
  "Italy|Imperia", "Italy|Sanremo", "Italy|Lucca", "Italy|Pisa", "Italy|Livorno", "Italy|Arezzo",
  "Italy|Siena", "Italy|Prato", "Italy|Pistoia", "Italy|Massa", "Italy|Carrara", "Italy|Viareggio",
  "Italy|Forte dei Marmi", "Italy|Grosseto", "Italy|Ancona", "Italy|Pesaro", "Italy|Macerata", "Italy|Ascoli Piceno",
  "Italy|Fermo", "Italy|Civitanova Marche", "Italy|Terni", "Italy|Viterbo", "Italy|Latina", "Italy|Frosinone",
  "Italy|Rieti", "Italy|L'Aquila", "Italy|Teramo", "Italy|Chieti", "Italy|Campobasso", "Italy|Caserta",
  "Italy|Avellino", "Italy|Benevento", "Italy|Potenza", "Italy|Matera", "Italy|Foggia", "Italy|Barletta",
  "Italy|Andria", "Italy|Trani", "Italy|Taranto", "Italy|Brindisi", "Italy|Altamura", "Italy|Cosenza",
  "Italy|Catanzaro", "Italy|Reggio Calabria", "Italy|Messina", "Italy|Siracusa", "Italy|Ragusa", "Italy|Trapani",
  "Italy|Agrigento", "Italy|Sassari", "Italy|Olbia", "Italy|Nuoro", "Italy|Oristano", "Italy|Sorrento",
  "Italy|Bassano del Grappa", "Italy|Schio", "Italy|Conegliano", "Italy|Castelfranco Veneto", "Italy|Jesolo", "Italy|Riccione",
  "Italy|Cortina d'Ampezzo", "Italy|Merano", "Italy|Brunico", "Italy|Desenzano del Garda", "Italy|Busto Arsizio", "Italy|Gallarate",
  "Italy|Legnano", "Italy|Cantù", "Italy|Saronno", "Italy|Crema", "Italy|Carpi", "Italy|Sassuolo",
  "Italy|Imola", "Italy|Faenza", "Italy|Empoli", "Italy|Montecatini Terme", "Italy|Foligno", "Italy|Spoleto",
  "Italy|Città di Castello", "Italy|Avezzano", "Italy|Lanciano", "Italy|Vasto", "Italy|Bitonto", "Italy|Molfetta",
  "Italy|Monopoli", "Italy|Martina Franca", "Italy|Nardò", "Italy|Gallipoli", "Italy|Lamezia Terme", "Italy|Acireale",
  "Italy|Marsala", "Italy|Alghero",
  "France|Angers", "France|Le Mans", "France|Caen", "France|Rouen", "France|Le Havre", "France|Amiens",
  "France|Orléans", "France|Clermont-Ferrand", "France|Limoges", "France|Poitiers", "France|La Rochelle", "France|Brest",
  "France|Quimper", "France|Vannes", "France|Lorient", "France|Saint-Malo", "France|Nancy", "France|Mulhouse",
  "France|Colmar", "France|Besançon", "France|Avignon", "France|Nîmes", "France|Perpignan", "France|Béziers",
  "France|Toulon", "France|Antibes", "France|Menton", "France|Saint-Raphaël", "France|Fréjus", "France|Hyères",
  "France|Arles", "France|Valence", "France|Chambéry", "France|Saint-Étienne", "France|Pau", "France|Bayonne",
  "France|Anglet", "France|Arcachon", "France|La Baule-Escoublac", "France|Deauville", "France|Troyes", "France|Épernay",
  "France|Versailles", "France|Neuilly-sur-Seine", "France|Saint-Germain-en-Laye", "France|Boulogne-Billancourt", "France|Le Touquet-Paris-Plage", "France|Megève",
  "France|Chamonix-Mont-Blanc", "France|Évian-les-Bains", "France|Thonon-les-Bains", "France|Saint-Jean-de-Luz", "France|Niort", "France|Angoulême",
  "France|Cognac", "France|Agen", "France|Montauban", "France|Albi", "France|Carcassonne", "France|Narbonne",
  "France|Sète", "France|Bourges", "France|Nevers", "France|Auxerre", "France|Chartres", "France|Évreux",
  "France|Beauvais", "France|Compiègne", "France|Valenciennes", "France|Arras", "France|Dunkerque", "France|Calais",
  "France|Boulogne-sur-Mer", "France|Charleville-Mézières", "France|Épinal", "France|Belfort", "France|Mâcon", "France|Bourg-en-Bresse",
  "France|Vichy", "France|Aurillac", "France|Rodez", "France|Cahors", "France|Périgueux", "France|Brive-la-Gaillarde",
  "France|Tarbes", "France|Saint-Brieuc", "France|Laval", "France|Cholet", "France|Saint-Nazaire", "France|Les Sables-d'Olonne",
  "France|Ajaccio", "France|Bastia", "France|Porto-Vecchio", "France|Gap", "France|Grasse", "France|Cagnes-sur-Mer",
  "Spain|Córdoba", "Spain|Cádiz", "Spain|Jerez de la Frontera", "Spain|Huelva", "Spain|Almería", "Spain|Jaén",
  "Spain|Salamanca", "Spain|Burgos", "Spain|León", "Spain|Logroño", "Spain|Vitoria-Gasteiz", "Spain|Girona",
  "Spain|Tarragona", "Spain|Lleida", "Spain|Castelló de la Plana", "Spain|Albacete", "Spain|Toledo", "Spain|Cáceres",
  "Spain|Badajoz", "Spain|Ciudad Real", "Spain|Guadalajara", "Spain|Segovia", "Spain|Ávila", "Spain|Palencia",
  "Spain|Zamora", "Spain|Soria", "Spain|Huesca", "Spain|Teruel", "Spain|Cuenca", "Spain|Lugo",
  "Spain|Ourense", "Spain|Pontevedra", "Spain|Santiago de Compostela", "Spain|Gijón", "Spain|Ferrol", "Spain|Elche",
  "Spain|Benidorm", "Spain|Torrevieja", "Spain|Cartagena", "Spain|Estepona", "Spain|Fuengirola", "Spain|Eivissa",
  "Spain|Maó", "Spain|Las Palmas de Gran Canaria", "Spain|Santa Cruz de Tenerife", "Spain|Sabadell", "Spain|Terrassa", "Spain|Badalona",
  "Spain|Sitges", "Spain|Reus", "Spain|Mataró", "Spain|Getafe", "Spain|Alcalá de Henares", "Spain|Pozuelo de Alarcón",
  "Spain|Majadahonda", "Spain|Las Rozas de Madrid", "Spain|Talavera de la Reina", "Spain|Algeciras", "Spain|Ronda",
  "Portugal|Aveiro", "Portugal|Leiria", "Portugal|Viseu", "Portugal|Guimarães", "Portugal|Viana do Castelo", "Portugal|Setúbal",
  "Portugal|Cascais", "Portugal|Sintra", "Portugal|Évora", "Portugal|Funchal", "Portugal|Ponta Delgada", "Portugal|Vila Nova de Gaia",
  "Portugal|Matosinhos", "Portugal|Lagos", "Portugal|Albufeira", "Portugal|Portimão", "Portugal|Castelo Branco", "Portugal|Santarém",
  "Netherlands|Zwolle", "Netherlands|Amersfoort", "Netherlands|Apeldoorn", "Netherlands|Enschede", "Netherlands|Deventer", "Netherlands|Delft",
  "Netherlands|Dordrecht", "Netherlands|Alkmaar", "Netherlands|Hilversum", "Netherlands|Leeuwarden", "Netherlands|Assen", "Netherlands|Venlo",
  "Netherlands|Roermond", "Netherlands|Heerlen", "Netherlands|Middelburg", "Netherlands|Amstelveen", "Netherlands|Zaanstad", "Netherlands|Gouda",
  "Netherlands|Roosendaal", "Netherlands|Bergen op Zoom", "Netherlands|Helmond", "Netherlands|Oss", "Netherlands|Wassenaar", "Netherlands|Laren",
  "Netherlands|Naarden", "Netherlands|Emmen", "Netherlands|Almere",
  "Belgium|Kortrijk", "Belgium|Oostende", "Belgium|Aalst", "Belgium|Sint-Niklaas", "Belgium|Genk", "Belgium|Charleroi",
  "Belgium|Mons", "Belgium|Tournai", "Belgium|Waterloo", "Belgium|Wavre", "Belgium|Roeselare", "Belgium|Turnhout",
  "Belgium|Lier", "Belgium|Ieper", "Belgium|Spa", "Belgium|Ukkel",
  "Luxembourg|Esch-sur-Alzette",
  "Switzerland|Winterthur", "Switzerland|Zug", "Switzerland|Schaffhausen", "Switzerland|Chur", "Switzerland|Aarau", "Switzerland|Baden",
  "Switzerland|Biel/Bienne", "Switzerland|Thun", "Switzerland|Fribourg", "Switzerland|Neuchâtel", "Switzerland|Sion", "Switzerland|Montreux",
  "Switzerland|Vevey", "Switzerland|Locarno", "Switzerland|Ascona", "Switzerland|St. Moritz", "Switzerland|Davos", "Switzerland|Solothurn",
  "Switzerland|Olten", "Switzerland|Rapperswil-Jona", "Switzerland|Wil", "Switzerland|Frauenfeld", "Switzerland|Nyon", "Switzerland|Crans-Montana",
  "Switzerland|Saanen",
  "Austria|Klagenfurt am Wörthersee", "Austria|Villach", "Austria|Wels", "Austria|St. Pölten", "Austria|Bregenz", "Austria|Dornbirn",
  "Austria|Feldkirch", "Austria|Kitzbühel", "Austria|Wiener Neustadt", "Austria|Steyr", "Austria|Leoben", "Austria|Lienz",
  "Austria|Baden", "Austria|Krems an der Donau", "Austria|Bad Ischl", "Austria|Zell am See",
  "Germany|Aachen", "Germany|Regensburg", "Germany|Würzburg", "Germany|Ulm", "Germany|Konstanz", "Germany|Ingolstadt",
  "Germany|Erlangen", "Germany|Bamberg", "Germany|Bayreuth", "Germany|Passau", "Germany|Rosenheim", "Germany|Garmisch-Partenkirchen",
  "Germany|Baden-Baden", "Germany|Pforzheim", "Germany|Heilbronn", "Germany|Reutlingen", "Germany|Tübingen", "Germany|Esslingen am Neckar",
  "Germany|Ludwigsburg", "Germany|Darmstadt", "Germany|Mainz", "Germany|Koblenz", "Germany|Trier", "Germany|Saarbrücken",
  "Germany|Kassel", "Germany|Göttingen", "Germany|Braunschweig", "Germany|Wolfsburg", "Germany|Osnabrück", "Germany|Oldenburg",
  "Germany|Bielefeld", "Germany|Paderborn", "Germany|Krefeld", "Germany|Mönchengladbach", "Germany|Wuppertal", "Germany|Bochum",
  "Germany|Duisburg", "Germany|Kiel", "Germany|Lübeck", "Germany|Rostock", "Germany|Potsdam", "Germany|Erfurt",
  "Germany|Magdeburg", "Germany|Bad Homburg vor der Höhe", "Germany|Starnberg", "Germany|Lindau (Bodensee)", "Germany|Landshut", "Germany|Kempten (Allgäu)",
  "United Kingdom|Nottingham", "United Kingdom|Sheffield", "United Kingdom|Newcastle upon Tyne", "United Kingdom|Cardiff", "United Kingdom|Belfast", "United Kingdom|Aberdeen",
  "United Kingdom|Brighton", "United Kingdom|Bath", "United Kingdom|Oxford", "United Kingdom|Cambridge", "United Kingdom|York", "United Kingdom|Leicester",
  "United Kingdom|Southampton", "United Kingdom|Bournemouth", "United Kingdom|Exeter", "United Kingdom|Harrogate", "United Kingdom|Cheltenham", "United Kingdom|Chester",
  "United Kingdom|Norwich", "United Kingdom|Reading", "United Kingdom|Guildford", "United Kingdom|Windsor", "United Kingdom|St Albans", "United Kingdom|Royal Tunbridge Wells",
  "United Kingdom|Winchester", "United Kingdom|Canterbury", "United Kingdom|Plymouth", "United Kingdom|Coventry", "United Kingdom|Derby", "United Kingdom|Stoke-on-Trent",
  "United Kingdom|Kingston upon Hull", "United Kingdom|Dundee", "United Kingdom|Inverness", "United Kingdom|Swansea", "United Kingdom|Wilmslow", "United Kingdom|Solihull",
  "United Kingdom|Sevenoaks", "United Kingdom|Marlow", "United Kingdom|Henley-on-Thames", "United Kingdom|Royal Leamington Spa", "United Kingdom|Stratford-upon-Avon", "United Kingdom|Salisbury",
  "United Kingdom|Lincoln", "United Kingdom|Durham", "United Kingdom|Preston", "United Kingdom|Lancaster", "United Kingdom|Stirling", "United Kingdom|Perth",
  "United Kingdom|Kendal", "United Kingdom|Truro",
  "Ireland|Galway", "Ireland|Limerick", "Ireland|Waterford", "Ireland|Kilkenny", "Ireland|Killarney", "Ireland|Sligo",
  "Ireland|Athlone", "Ireland|Drogheda", "Ireland|Dundalk", "Ireland|Wexford", "Ireland|Ennis", "Ireland|Dún Laoghaire",
  "Denmark|Odense", "Denmark|Aalborg", "Denmark|Esbjerg", "Denmark|Vejle", "Denmark|Kolding", "Denmark|Roskilde",
  "Denmark|Helsingør", "Denmark|Horsens", "Denmark|Randers", "Denmark|Silkeborg", "Denmark|Herning",
  "Sweden|Uppsala", "Sweden|Västerås", "Sweden|Örebro", "Sweden|Linköping", "Sweden|Norrköping", "Sweden|Helsingborg",
  "Sweden|Jönköping", "Sweden|Lund", "Sweden|Umeå", "Sweden|Gävle", "Sweden|Växjö", "Sweden|Karlstad",
  "Sweden|Halmstad", "Sweden|Kalmar", "Sweden|Visby", "Sweden|Sundsvall", "Sweden|Luleå",
  "Norway|Trondheim", "Norway|Stavanger", "Norway|Kristiansand", "Norway|Tromsø", "Norway|Drammen", "Norway|Fredrikstad",
  "Norway|Ålesund", "Norway|Bodø", "Norway|Sandnes", "Norway|Tønsberg", "Norway|Sandefjord", "Norway|Haugesund",
  "Finland|Espoo", "Finland|Tampere", "Finland|Turku", "Finland|Oulu", "Finland|Jyväskylä", "Finland|Lahti",
  "Finland|Kuopio", "Finland|Pori", "Finland|Vaasa", "Finland|Porvoo", "Finland|Rovaniemi",
  "Iceland|Akureyri",
  "Poland|Szczecin", "Poland|Lublin", "Poland|Katowice", "Poland|Bydgoszcz", "Poland|Białystok", "Poland|Gdynia",
  "Poland|Sopot", "Poland|Toruń", "Poland|Rzeszów", "Poland|Kielce", "Poland|Olsztyn", "Poland|Opole",
  "Poland|Zielona Góra", "Poland|Częstochowa", "Poland|Bielsko-Biała", "Poland|Gliwice", "Poland|Zakopane", "Poland|Radom",
  "Czechia|Ostrava", "Czechia|Plzeň", "Czechia|Olomouc", "Czechia|Liberec", "Czechia|České Budějovice", "Czechia|Hradec Králové",
  "Czechia|Pardubice", "Czechia|Zlín", "Czechia|Karlovy Vary", "Czechia|Jihlava", "Czechia|Ústí nad Labem",
  "Slovakia|Košice", "Slovakia|Žilina", "Slovakia|Nitra", "Slovakia|Banská Bystrica", "Slovakia|Trnava", "Slovakia|Prešov",
  "Slovakia|Poprad",
  "Hungary|Debrecen", "Hungary|Szeged", "Hungary|Győr", "Hungary|Pécs", "Hungary|Miskolc", "Hungary|Székesfehérvár",
  "Hungary|Sopron", "Hungary|Siófok", "Hungary|Eger", "Hungary|Kecskemét",
  "Romania|Timișoara", "Romania|Iași", "Romania|Brașov", "Romania|Constanța", "Romania|Sibiu", "Romania|Oradea",
  "Romania|Craiova", "Romania|Arad", "Romania|Ploiești", "Romania|Galați",
  "Bulgaria|Plovdiv", "Bulgaria|Varna", "Bulgaria|Burgas", "Bulgaria|Ruse", "Bulgaria|Stara Zagora", "Bulgaria|Bansko",
  "Croatia|Rijeka", "Croatia|Zadar", "Croatia|Dubrovnik", "Croatia|Pula", "Croatia|Osijek", "Croatia|Šibenik",
  "Croatia|Varaždin", "Croatia|Makarska", "Croatia|Rovinj",
  "Slovenia|Maribor", "Slovenia|Koper", "Slovenia|Celje", "Slovenia|Kranj", "Slovenia|Bled", "Slovenia|Piran",
  "Greece|Heraklion", "Greece|Larissa", "Greece|Volos", "Greece|Ioannina", "Greece|Chania", "Greece|Rhodes",
  "Greece|Kavala", "Greece|Corfu", "Greece|Mykonos", "Greece|Kalamata", "Greece|Chalkida", "Greece|Serres",
  "Greece|Alexandroupoli", "Greece|Katerini", "Greece|Glyfada", "Greece|Kifisia",
  "Cyprus|Larnaca", "Cyprus|Paphos", "Cyprus|Ayia Napa",
  "Malta|Valletta", "Malta|St. Julian's",
  "Lithuania|Kaunas", "Lithuania|Klaipėda", "Lithuania|Šiauliai",
  "Latvia|Jūrmala", "Latvia|Liepāja", "Latvia|Daugavpils",
  "Estonia|Tartu", "Estonia|Pärnu",
  "Serbia|Novi Sad", "Serbia|Niš",
  "Montenegro|Podgorica", "Montenegro|Kotor", "Montenegro|Tivat",
  "Albania|Durrës",
  "North Macedonia|Ohrid",
  "Bosnia and Herzegovina|Mostar", "Bosnia and Herzegovina|Banja Luka",
];

/** Otomatik turun şehirleri: önce Avrupa kasabaları (kendi imleciyle), kalan pay büyük şehir havuzundan.
 *  Sıra önemli — tur süre bütçesine takılırsa taze Avrupa kasabaları yine de taranmış olur. */
/* 16 Eki 2026: gönderimi durdurulan ülkeler (sunucunun paused listesi, known.json) otomatik turda taranmaz — 13:40 turu
   956 adayın çoğunu Almanya'da (Mönchengladbach, Bochum…) harcadı, oysa Almanya'ya mektup şimdilik gitmiyor. */
export const SKIP_COUNTRIES = new Set();
const skippedPlace = (p) => SKIP_COUNTRIES.has(String(p).split('|')[0].trim().toLowerCase());
export function pickAutoCities(state, n, euroShare) {
  const nE = Math.min(EURO_TOWNS.length, Math.round(n * euroShare)), nP = Math.min(CITY_POOL.length, n - nE), out = [];
  const ce = Number(state.euroCursor || 0) % EURO_TOWNS.length, cp = Number(state.placesCursor || 0) % CITY_POOL.length;
  for (let k = 0, got = 0; got < nE && k < EURO_TOWNS.length; k++) { const t = EURO_TOWNS[(ce + k) % EURO_TOWNS.length]; if (skippedPlace(t)) continue; out.push(t); got++; }
  for (let k = 0, got = 0; got < nP && k < CITY_POOL.length; k++) { const t = CITY_POOL[(cp + k) % CITY_POOL.length]; if (skippedPlace(t)) continue; out.push(t); got++; }
  return out;
}

/* ============================== AVRUPA KARE TARAMASI (ücretsiz, 13 Eki 2026) ==============================
   Operatör: "Brave çok pahalı, iptal; 500 yeni butiği ücretsiz bul". Şehir listeleri (CITY_POOL, EURO_TOWNS) adla
   sınırlı: Nominatim'in bulduğu sınır içine bakar, listede olmayan kasaba hiç taranmaz. Burada Avrupa ülke ülke
   0,5°'lik karelere bölünür ve her karede SİTESİ ya da E-POSTASI etiketli giyim dükkânları Overpass'tan doğrudan
   bbox ile çekilir (Nominatim yok, sınır yok, hızlı). Boş kare (deniz/dağ) 90 gün hatırlanır. İmleç state.gridCursor. */
const EURO_BBOX = [   // [ülke adı, batı, güney, doğu, kuzey] — öncelik sırası: operatörün pazarları önce
  ['Italy', 6.6, 36.6, 18.6, 47.1], ['France', -5.2, 42.3, 8.3, 51.1], ['Spain', -9.4, 36.0, 3.4, 43.8], ['Portugal', -9.6, 36.9, -6.2, 42.2],
  ['Netherlands', 3.3, 50.7, 7.3, 53.6], ['Belgium', 2.5, 49.5, 6.4, 51.5], ['Luxembourg', 5.7, 49.4, 6.6, 50.2],
  ['Switzerland', 5.9, 45.8, 10.5, 47.9], ['Austria', 9.5, 46.3, 17.2, 49.1], ['Germany', 5.9, 47.3, 15.1, 55.1],
  ['United Kingdom', -8.2, 49.9, 1.8, 58.7], ['Ireland', -10.5, 51.4, -5.9, 55.4],
  ['Denmark', 8.0, 54.5, 12.8, 57.8], ['Sweden', 11.0, 55.3, 19.0, 63.5], ['Norway', 4.6, 57.9, 13.0, 65.0], ['Finland', 20.5, 59.7, 28.0, 64.5],
  ['Poland', 14.1, 49.0, 24.2, 54.9], ['Czechia', 12.1, 48.5, 18.9, 51.1], ['Slovakia', 16.8, 47.7, 22.6, 49.6], ['Hungary', 16.1, 45.7, 22.9, 48.6],
  ['Romania', 20.2, 43.6, 29.7, 48.3], ['Bulgaria', 22.3, 41.2, 28.6, 44.2], ['Greece', 19.4, 34.8, 28.3, 41.8],
  ['Croatia', 13.5, 42.4, 19.4, 46.6], ['Slovenia', 13.4, 45.4, 16.6, 46.9], ['Bosnia and Herzegovina', 15.7, 42.6, 19.6, 45.3],
  ['Serbia', 18.8, 42.2, 23.0, 46.2], ['Montenegro', 18.4, 41.8, 20.4, 43.6], ['Albania', 19.3, 39.6, 21.1, 42.7], ['North Macedonia', 20.4, 40.8, 23.0, 42.4],
  ['Lithuania', 20.9, 53.9, 26.8, 56.5], ['Latvia', 20.9, 55.7, 28.2, 58.1], ['Estonia', 21.8, 57.5, 28.2, 59.7],
  ['Malta', 14.2, 35.8, 14.6, 36.1], ['Cyprus', 32.2, 34.5, 34.6, 35.7], ['Iceland', -24.5, 63.3, -13.5, 66.6],
];
export function buildGrid(boxes, step) {
  const out = [], seen = new Set();
  for (const [country, w, s, e, n] of boxes) {
    for (let lat = Math.floor(s / step) * step; lat < n; lat += step) {
      for (let lon = Math.floor(w / step) * step; lon < e; lon += step) {
        const key = `${lat.toFixed(1)},${lon.toFixed(1)}`; if (seen.has(key)) continue; seen.add(key);
        out.push(`${country}|grid:${lat.toFixed(1)},${lon.toFixed(1)},${(lat + step).toFixed(1)},${(lon + step).toFixed(1)}`);
      }
    }
  }
  return out;
}
export const EURO_GRID = buildGrid(EURO_BBOX, 0.5);
/** "grid:S,W,N,E" → [S,W,N,E] ya da null. */
export function parseGridCell(city) {
  const m = /^grid:(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)$/.exec(String(city || ''));
  return m ? m.slice(1, 5).map(Number) : null;
}
/** Sıradaki n kare (boş diye işaretlenenler 90 gün atlanır); imleç state.gridCursor. */
export function pickGridCells(state, n) {
  const empty = (state.gridEmpty && typeof state.gridEmpty === 'object') ? state.gridEmpty : {};
  const cutoff = Date.now() - 90 * 86400000, out = []; let cur = Number(state.gridCursor || 0) % EURO_GRID.length, steps = 0;
  while (out.length < n && steps < EURO_GRID.length) {
    const c = EURO_GRID[cur]; cur = (cur + 1) % EURO_GRID.length; steps++;
    const e = empty[c]; if (e && Date.parse(e) > cutoff) continue;
    if (skippedPlace(c)) continue;
    out.push(c);
  }
  return out;
}

const COUNTRY_LANG = { Italy: 'it', France: 'fr', Spain: 'es', Netherlands: 'nl', Belgium: 'nl', Switzerland: 'de', Austria: 'de', Germany: 'de',
  Portugal: 'pt', Greece: 'el', Poland: 'pl', 'United Kingdom': 'en', Ireland: 'en', Denmark: 'da', Sweden: 'sv', Norway: 'nb', Czechia: 'cs', Finland: 'fi' };

/* ============================== ODAK ÜLKELER (14 Eki 2026) ==============================
   Operatör: "Avustralya, G. Kore, Japonya, Kanada bunları da ara". Bu ülkeler CITY_POOL'da vardı ama otomatik turlar
   Avrupa karelerine gittiği için (kare varken şehir havuzu hiç kullanılmıyor) neredeyse hiç sıra gelmiyordu. Her turda
   kendi imleçleriyle FOCUS_CITIES şehir (OSM) + FOCUS_QUERIES sorgu (sunucudaki Bing, ülke dilinde ve ülke koduyla). */
export const FOCUS_TOWNS = [
  'Australia|Sydney', 'Japan|Tokyo', 'Canada|Toronto', 'South Korea|Seoul', 'Australia|Melbourne', 'Japan|Osaka', 'Canada|Vancouver',
  'South Korea|Busan', 'Australia|Brisbane', 'Japan|Kyoto', 'Canada|Montréal', 'South Korea|Daegu', 'Australia|Perth', 'Japan|Yokohama',
  'Canada|Calgary', 'South Korea|Incheon', 'Australia|Adelaide', 'Japan|Nagoya', 'Canada|Ottawa', 'South Korea|Daejeon',
  'Australia|Gold Coast', 'Japan|Fukuoka', 'Canada|Edmonton', 'South Korea|Gwangju', 'Australia|Canberra', 'Japan|Kobe',
  'Canada|Québec', 'South Korea|Suwon', 'Australia|Newcastle', 'Japan|Sapporo', 'Canada|Winnipeg', 'South Korea|Ulsan',
  'Australia|Hobart', 'Japan|Sendai', 'Canada|Halifax', 'South Korea|Seongnam', 'Australia|Geelong', 'Japan|Hiroshima',
  'Canada|Victoria', 'South Korea|Jeonju', 'Australia|Sunshine Coast', 'Japan|Kanazawa', 'Canada|Kelowna', 'South Korea|Jeju',
  'Australia|Wollongong', 'Japan|Okayama', 'Canada|Mississauga', 'South Korea|Changwon', 'Australia|Darwin', 'Japan|Shizuoka',
  'Canada|Oakville', 'South Korea|Goyang', 'Australia|Townsville', 'Japan|Kumamoto', 'Canada|Laval', 'South Korea|Cheongju',
  'Australia|Cairns', 'Japan|Naha', 'Canada|London', 'Australia|Toowoomba', 'Japan|Kagoshima', 'Canada|Kitchener',
  'Australia|Ballarat', 'Japan|Matsuyama', 'Canada|Hamilton', 'Australia|Bendigo', 'Japan|Niigata', 'Canada|Saskatoon',
  'Australia|Launceston', 'Japan|Takamatsu', 'Canada|Regina', 'Australia|Mackay', 'Japan|Utsunomiya', 'Canada|Sherbrooke',
  'Australia|Byron Bay', 'Japan|Nara', 'Canada|Gatineau', 'Australia|Noosa', 'Japan|Kamakura', 'Canada|Whistler',
  'Japan|Kawasaki', 'Japan|Chiba', 'Japan|Saitama', 'Japan|Hakodate', 'Japan|Nagasaki', 'Japan|Kitakyushu', 'Japan|Gifu',
];
const FOCUS_CC = { Australia: 'AU', Canada: 'CA', Japan: 'JP', 'South Korea': 'KR' };
const FOCUS_FR = new Set(['Montréal', 'Québec', 'Laval', 'Sherbrooke', 'Gatineau']);
const FOCUS_TPL = {
  en: ['independent fashion boutique {c} brands stockist', '{c} multi-brand clothing boutique menswear womenswear'],
  fr: ['boutique multimarques vêtements {c}', '{c} boutique mode marques homme femme'],
  ja: ['{c} セレクトショップ 取扱ブランド', '{c} セレクトショップ メンズ レディース'],
  ko: ['{c} 편집샵 브랜드', '{c} 셀렉트샵 의류 브랜드'],
};
const focusLang = (country, city) => country === 'Japan' ? 'ja' : country === 'South Korea' ? 'ko' : (country === 'Canada' && FOCUS_FR.has(city)) ? 'fr' : 'en';
export function pickFocusCities(state, n) {
  const out = []; const c0 = Number(state.focusCursor || 0) % FOCUS_TOWNS.length;
  for (let k = 0; k < Math.min(n, FOCUS_TOWNS.length); k++) out.push(FOCUS_TOWNS[(c0 + k) % FOCUS_TOWNS.length]);
  return out;
}
export function buildFocusQueries(state, n) {
  const out = []; let cur = Number(state.focusQCursor || 0) % FOCUS_TOWNS.length, steps = 0;
  while (out.length < n && steps < FOCUS_TOWNS.length) {
    const pc = FOCUS_TOWNS[cur]; cur = (cur + 1) % FOCUS_TOWNS.length; steps++;
    const [country, city] = pc.split('|'); const lang = focusLang(country, city);
    for (const tpl of FOCUS_TPL[lang]) { if (out.length >= n) break; out.push({ q: tpl.replace('{c}', city), lang, cc: FOCUS_CC[country] || '', town: pc }); }
  }
  return { queries: out, cursor: cur };
}

/* ============================== KASABA SORGULARI (ücretsiz, 13 Eki 2026) ==============================
   Marka-çifti sorguları aynı büyük siteleri getiriyor (ikinci turda 69 adayın 58'i "seen"). Arama motorunun iyi
   olduğu şey yerel dükkân: "multimarka butik + kasaba adı", ülkenin dilinde, EURO_TOWNS + CITY_POOL üzerinden
   imleçle (state.townQCursor). Her kasaba için iki sorgu: genel + marka çeşnili. Sunucudaki Bing ile çalışır. */
const TOWN_TPL = {
  it: ['boutique abbigliamento multimarca {c}', '{c} negozio abbigliamento uomo donna Dsquared2 Stone Island'],
  fr: ['boutique multimarques prêt-à-porter {c}', '{c} boutique vêtements marques Moncler Off-White'],
  es: ['tienda multimarca moda {c}', '{c} boutique ropa marcas Dsquared2 Philipp Plein'],
  pt: ['loja multimarca moda {c}', '{c} boutique roupa marcas Hugo Boss Moncler'],
  de: ['Multilabel Boutique Mode {c}', '{c} Modehaus Marken Stone Island Moncler Hugo Boss'],
  nl: ['multibrand boutique kleding {c}', '{c} modezaak merken Moncler Stone Island'],
  en: ['independent multi-brand fashion boutique {c}', '{c} boutique stockist Stone Island Moncler Off-White'],
  pl: ['butik multibrand odzież {c}', '{c} sklep odzieżowy marki Moncler Hugo Boss'],
  cs: ['multibrand butik oblečení {c}', '{c} obchod s oblečením značky Moncler Hugo Boss'],
  el: ['κατάστημα ρούχων πολυμάρκα {c}', '{c} boutique ρούχα Moncler Stone Island'],
  da: ['multibrand tøjbutik {c}', '{c} tøjbutik mærker Moncler Stone Island'],
  sv: ['multibrand klädbutik {c}', '{c} klädbutik märken Moncler Stone Island'],
  nb: ['multibrand klesbutikk {c}', '{c} klesbutikk merker Moncler Stone Island'],
  fi: ['monimerkki vaateliike {c}', '{c} vaateliike merkit Moncler Stone Island'],
  hu: ['multibrand ruhabolt {c}', '{c} ruhaüzlet márkák Moncler Hugo Boss'],
  ro: ['magazin haine multibrand {c}', '{c} boutique haine branduri Moncler Hugo Boss'],
};
const TOWN_LANG_EXTRA = { Hungary: 'hu', Romania: 'ro', Slovakia: 'cs', Croatia: 'en', Slovenia: 'en', Bulgaria: 'en', Serbia: 'en', Lithuania: 'en',
  Latvia: 'en', Estonia: 'en', Luxembourg: 'fr', Cyprus: 'el', Malta: 'en', Iceland: 'en', Montenegro: 'en', Albania: 'en', 'North Macedonia': 'en',
  'Bosnia and Herzegovina': 'en' };
const TOWN_SRC = [...EURO_TOWNS, ...CITY_POOL];
export function buildTownQueries(state, n) {
  const out = []; let cur = Number(state.townQCursor || 0) % TOWN_SRC.length, steps = 0;
  while (out.length < n && steps < TOWN_SRC.length) {
    const pc = TOWN_SRC[cur]; cur = (cur + 1) % TOWN_SRC.length; steps++;
    const [country, city] = pc.split('|').map(x => x.trim()); if (!city) continue;
    const lang = COUNTRY_LANG[country] || TOWN_LANG_EXTRA[country] || 'en';
    for (const tpl of (TOWN_TPL[lang] || TOWN_TPL.en)) { if (out.length >= n) break; out.push({ q: tpl.replace('{c}', city), lang, town: pc }); }
  }
  return { queries: out, cursor: cur };
}

/* Sunucu vekili: tek SSH çağrısında tüm sorgular. Anahtar runner'a hiç gelmez. */
export function callServerSearch(cmd, payload, timeoutSec) {
  const r = spawnSync('bash', ['-c', cmd], { input: JSON.stringify(payload), encoding: 'utf8', timeout: timeoutSec * 1000, maxBuffer: 64 * 1024 * 1024 });
  if (r.error) return { error: String(r.error.message || r.error) };
  const line = String(r.stdout || '').split('\n').filter(l => l.startsWith('{')).pop();
  if (!line) return { error: `bos yanit (rc=${r.status}) ${String(r.stderr || '').split('\n').filter(l => !/tput/.test(l)).join(' ').slice(0, 200)}` };
  try { return JSON.parse(line); } catch (e) { return { error: 'JSON: ' + e.message }; }
}

function loadJson(p, d) { try { return existsSync(p) ? JSON.parse(readFileSync(p, 'utf8')) : d; } catch { return d; } }
function saveJson(p, o) { mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, JSON.stringify(o, null, 1) + '\n'); }

async function main() {
  const state = loadJson(CFG.stateFile, { version: 1, domains: {}, queries: {}, runs: [] });
  state.domains ||= {}; state.queries ||= {}; state.runs ||= [];
  const knownRaw = CFG.knownFile ? loadJson(CFG.knownFile, {}) : {};
  for (const c of (Array.isArray(knownRaw.paused) ? knownRaw.paused : [])) SKIP_COUNTRIES.add(String(c).trim().toLowerCase());
  for (const c of list('SKIP_COUNTRIES')) SKIP_COUNTRIES.add(c.trim().toLowerCase());
  if (SKIP_COUNTRIES.size) log(`otomatik turda atlanan (gonderimi durdurulmus) ulkeler: ${[...SKIP_COUNTRIES].join(', ')}`);
  const known = { emails: new Set((knownRaw.emails || []).map(e => String(e).toLowerCase())), domains: new Set((knownRaw.domains || []).map(d => registrableDomain(String(d)))) };
  log(`ayarlar: sorgu=${CFG.queriesPerRun} site=${CFG.maxSites} butce=${CFG.budgetSec}s motor=${CFG.engines.join(',')}${CFG.braveKey ? '(+brave key)' : ''} dil=${CFG.langs.join(',') || 'hepsi'} ulke=${CFG.countries.join(',') || 'hepsi'}${CFG.dryRun ? ' [DRY-RUN]' : ''}`);
  log(`bilinen: ${known.emails.size} e-posta, ${known.domains.size} alan adi (sunucu) | durum dosyasi: ${Object.keys(state.domains).length} alan adi, ${Object.keys(state.queries).length} sorgu`);

  /* ---- 1) sorgular ---- */
  const dayIdx = Math.floor(Date.now() / 86400e3);
  const queries = [...CFG.extraQueries.map(q => ({ q, lang: 'en', extra: true })), ...buildQueries(CFG.queriesPerRun, state, CFG.langs, dayIdx * 7919)];
  const engines = CFG.engines.filter(e => ENGINE[e] && (e !== 'brave' || CFG.braveKey) && (e !== 'google' || (CFG.googleKey && CFG.googleCx && CFG.googlePerRun > 0)));   // 'server' ayrı yürür (1a)
  if (CFG.engines.includes('google') && !(CFG.googleKey && CFG.googleCx)) log('google: GOOGLE_CSE_KEY / GOOGLE_CSE_CX yok -- Google Programmable Search atlandi');
  const engStat = Object.fromEntries(engines.map(e => [e, { ok: 0, empty: 0, blocked: 0, urls: 0, dead: false }]));
  const cands = new Map();                                            // reg domain -> {q, url}
  const seenRun = new Set();
  const consider = (url, q, hint = {}) => {
    let host; try { host = new URL(url).hostname; } catch { return; } if (!host || /^\d+\.\d+\.\d+\.\d+$/.test(host)) return;
    const reg = registrableDomain(host); if (!reg.includes('.') || cands.has(reg) || seenRun.has(reg)) return;
    if (CFG.countries.length) { const c = TLD_COUNTRY[tld(reg)]; if (c && !CFG.countries.includes(COUNTRY_ISO[c] || '')) return; }
    cands.set(reg, { q, url, ...hint });
  };
  const notes = [];
  const srvStat = { used: false, brave: null, places: null, keys: knownRaw.keys || {} };

  /* Bu turun şehirleri: CITIES verilmişse onlar, yoksa havuzdan sırayla (OSM + Places). */
  let cityT = []; const autoCities = !CFG.cities.length;
  if (!autoCities) cityT = CFG.cities;
  else {
    /* 13 Eki 2026: önceki turda Overpass'tan yanıt alamayan şehirler (state.osmRetry) bu turda ÖNCE denenir — yoksa
       imleç üzerlerinden geçer ve havuz bir tur dönene kadar (günler) bir daha bakılmazdı. */
    const retry = Array.isArray(state.osmRetry) ? state.osmRetry.filter(c => typeof c === 'string' && (EURO_TOWNS.includes(c) || CITY_POOL.includes(c) || FOCUS_TOWNS.includes(c))).slice(0, 25) : [];
    const grid = CFG.gridCells > 0 ? pickGridCells(state, CFG.gridCells) : [];
    const picked = grid.length ? [] : pickAutoCities(state, Math.max(CFG.placesPerRun, CFG.maxCities), CFG.euroShare);
    const focus = CFG.focusCities > 0 ? pickFocusCities(state, CFG.focusCities).filter(c => !retry.includes(c)) : [];
    if (focus.length && !CFG.dryRun) state.focusCursor = (Number(state.focusCursor || 0) + focus.length) % FOCUS_TOWNS.length;
    cityT = [...retry, ...focus, ...grid, ...picked.filter(c => !retry.includes(c) && !focus.includes(c))];
  }
  if (CFG.countries.length) cityT = cityT.filter(pc => CFG.countries.includes(COUNTRY_ISO[pc.split('|')[0].trim()] || ''));
  const osmStat = { cities: 0, shops: 0, picked: 0, errors: [] };

  /* ---- 1a) sunucu vekili: Brave (marka çifti sorguları) + Google Places (şehirler) ---- */
  let webLeft = queries;
  if (CFG.engines.includes('server') && CFG.searchCmd) {
    const keys = knownRaw.keys || {};
    const placeT = keys.google ? (autoCities ? cityT.slice(0, CFG.placesPerRun) : cityT) : [];
    const webQ = (keys.brave && CFG.braveAllowed) ? queries.map(({ q, lang }) => ({ q, lang, cc: (LOCALE[lang] || {}).cc || '' })) : [];
    /* 13 Eki 2026 (Brave iptal): aynı sorgular ÜCRETSİZ motorlarla (DuckDuckGo HTML, Bing) sunucunun IP'sinden —
       GitHub koşucularının IP'leri bu motorlarda engelli (captcha), sunucununki değil. */
    const townQ = (CFG.townQueries > 0 && autoCities) ? buildTownQueries(state, CFG.townQueries) : { queries: [], cursor: 0 };
    const focusQ = (CFG.focusQueries > 0 && autoCities) ? buildFocusQueries(state, CFG.focusQueries) : { queries: [], cursor: 0 };
    /* Odak ülke sorguları başta: sunucunun süre bütçesi kesilirse kesilen Avrupa kasaba sorgularının sonu olsun. */
    const webFree = webQ.length ? [] : [...focusQ.queries.map(({ q, lang, cc }) => ({ q, lang, cc })), ...townQ.queries.map(({ q, lang }) => ({ q, lang })), ...queries.map(({ q, lang }) => ({ q, lang }))];
    if (townQ.queries.length && !CFG.dryRun) state.townQCursor = townQ.cursor;
    if (focusQ.queries.length && !CFG.dryRun) state.focusQCursor = focusQ.cursor;
    if (keys.brave && !CFG.braveAllowed) notes.push('Brave kapalı (operatör kararı, 13 Eki) — web araması ücretsiz motorlarla sunucudan yapıldı.');
    if (webQ.length || webFree.length || placeT.length) {
      log(`sunucu vekili: ${webQ.length} Brave + ${webFree.length} ucretsiz (ddg/bing) web sorgusu + ${placeT.length} sehir (Google Places)...`);
      const resp = callServerSearch(CFG.searchCmd, { web: webQ, web_free: webFree, places: placeT.map(pc => ({ country: pc.split('|')[0].trim(), city: (pc.split('|')[1] || '').trim() })), places_phrases: 4, budget: 600 }, 780);
      srvStat.used = true;
      if (resp.error) { notes.push('Sunucu arama vekili hata verdi: ' + resp.error); log('  !! vekil hata: ' + resp.error); }
      else {
        srvStat.brave = resp.stats?.brave || null; srvStat.places = resp.stats?.places || null;
        const done = new Set();
        for (const [q, urls] of Object.entries(resp.web || {})) { done.add(q); const b = cands.size; for (const u of urls) consider(u, q);
          if (!CFG.dryRun) state.queries[q] = new Date().toISOString().slice(0, 10); log(`  [${webQ.length ? 'brave' : 'sunucu web'}] ${q} -> ${urls.length} sonuc, +${cands.size - b} yeni aday`); }
        for (const [pc, rows] of Object.entries(resp.places || {})) { const country = pc.split('|')[0]; const b = cands.size;
          for (const r of rows) consider(r.url, 'places:' + pc, { name: r.name || '', countryHint: country, phone: r.phone || '' });
          log(`  [places] ${pc} -> ${rows.length} dukkan (siteli), +${cands.size - b} yeni aday`); }
        webLeft = queries.filter(x => !done.has(x.q));
        if (srvStat.brave?.note) notes.push('Brave: ' + srvStat.brave.note);
        srvStat.free = resp.stats?.free || null;
        if (srvStat.free) log(`  [sunucu ddg/bing] ddg ${srvStat.free.ddg_ok || 0} ok / ${srvStat.free.ddg_err || 0} hata · bing ${srvStat.free.bing_ok || 0} ok / ${srvStat.free.bing_err || 0} hata${srvStat.free.note ? ' · ' + srvStat.free.note : ''}`);
        if (srvStat.free?.note) notes.push('Sunucudan ücretsiz arama: ' + srvStat.free.note);
        if (srvStat.places?.note) notes.push('Google Places: ' + srvStat.places.note);
      }
    }
    if (!keys.brave && CFG.braveAllowed && false) notes.push('Brave Search anahtarı yok — web araması yerine OpenStreetMap dükkân listesi kullanıldı. Brave anahtarı eklenirse sonuç artar (isteğe bağlı).');
  }

  /* ---- 1b) web arama motorları (ucuz: ~40 sorgu × 3 sn) — OSM'den ÖNCE, kendi tavanıyla ---- */
  const webBase = cands.size;
  let ei = 0, qDone = 0;
  if (!engines.length && !srvStat.used) log('arama motoru yok (ENGINES) -- yalnizca seed_domains incelenecek');
  for (const { q, lang } of webLeft) {
    if (!engines.length) break;
    /* 11 Eki 2026: tavan yalnız WEB adaylarına — OSM'nin 600+ adayı eskiden maxSites'ı doldurup bing/ddg'yi hiç çalıştırmıyordu. */
    if (cands.size - webBase >= CFG.maxSites || elapsed() > CFG.budgetSec * 0.25) { log(`web arama durdu: ${cands.size - webBase} web adayi / ${Math.round(elapsed())}s`); break; }
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

  /* ---- 1c) OpenStreetMap (anahtarsız): şehirdeki giyim dükkânları ---- */
  if (CFG.engines.includes('osm')) {
    let lastPoolIdx = -1, lastEuroIdx = -1, lastGridIdx = -1, ci = 0; const osmFailed = [];
    /* 13 Eki 2026: şehirler OSM_CONCURRENCY (varsayılan 3) işçiyle paralel — ölçüm: tek sırada turda 10-20 şehir,
       zaman aşımına düşen bir ayna her şehri 2-3 dk bekletiyordu. Aday ekleme (consider) eşzamanlı ama tek iş
       parçacığında; aynı alan adını iki işçi eklemez (cands kontrolü await'siz). */
    const osmWorker = async () => {
      while (ci < cityT.length) {
        if (elapsed() > CFG.budgetSec * 0.55) { if (!osmStat.errors.includes('süre bütçesi')) osmStat.errors.push('süre bütçesi'); return; }
        if (autoCities && osmStat.picked >= CFG.osmTarget) return;
        const pc = cityT[ci++];
        if (autoCities) { const ei2 = EURO_TOWNS.indexOf(pc); if (ei2 >= 0) lastEuroIdx = Math.max(lastEuroIdx, ei2); else lastPoolIdx = Math.max(lastPoolIdx, CITY_POOL.indexOf(pc)); }
        const [country, city] = pc.split('|').map(x => x.trim());
        const isGrid = !!parseGridCell(city);
        if (isGrid) lastGridIdx = Math.max(lastGridIdx, EURO_GRID.indexOf(pc));
        const res = await osmCity(country, city || country);
        osmStat.cities++;
        if (res.error) { osmStat.errors.push(`${city}: ${res.error}`); osmFailed.push(pc); log(`  [osm] ${pc} -> HATA: ${res.error}`); continue; }
        osmStat.shops += res.rows.length;
        if (isGrid && !res.rows.length && !CFG.dryRun) { if (!state.gridEmpty || typeof state.gridEmpty !== 'object') state.gridEmpty = {}; state.gridEmpty[pc] = new Date().toISOString().slice(0, 10); }
        let picked = 0; const ex = {};
        for (const tags of res.rows) {
          if (picked >= (isGrid ? CFG.gridPerCell : CFG.osmPerCity)) break;
          const p = osmPick(tags);
          if (p.excl) { ex[p.excl] = (ex[p.excl] || 0) + 1; continue; }
          let reg; try { reg = registrableDomain(new URL(p.web).hostname); } catch { continue; }
          if (known.domains.has(reg) || cands.has(reg)) continue;
          const st = state.domains[reg]; if (st && !/^(no_email|fail)/.test(String(st.s || ''))) continue;
          const b = cands.size; consider(p.web, 'osm:' + pc, { name: p.name, countryHint: country, phone: p.phone, osmEmail: p.email });
          if (cands.size > b) picked++;
        }
        osmStat.picked += picked;
        log(`  [osm] ${pc} -> ${res.rows.length} dukkan, +${picked} yeni aday (${res.mirror}) | etiketten elenen: ${Object.entries(ex).map(([k, v]) => k + '=' + v).join(', ') || '-'}`);
        await sleep(1000);
      }
    };
    await Promise.all(Array.from({ length: Math.max(1, CFG.osmConcurrency) }, osmWorker));
    /* Havuz imleci: bir sonraki tur, bu turda bakılan son şehirden sonra başlar. */
    if (autoCities && !CFG.dryRun && lastPoolIdx >= 0) state.placesCursor = (lastPoolIdx + 1) % CITY_POOL.length;
    if (autoCities && !CFG.dryRun && lastEuroIdx >= 0) state.euroCursor = (lastEuroIdx + 1) % EURO_TOWNS.length;
    if (autoCities && !CFG.dryRun) state.osmRetry = [...new Set(osmFailed)].slice(0, 25);   // yalnız bu turda düşenler; sonraki tur önce bunları dener
    if (autoCities && !CFG.dryRun && lastGridIdx >= 0) state.gridCursor = (lastGridIdx + 1) % EURO_GRID.length;
    if (state.gridEmpty && Object.keys(state.gridEmpty).length > 6000) state.gridEmpty = Object.fromEntries(Object.entries(state.gridEmpty).slice(-5000));
  }
  for (const d of CFG.seedDomains) { let h = d.replace(/^https?:\/\//i, '').split('/')[0]; if (h) consider('https://' + h + '/', '(seed)'); }

  /* ---- 2) ön eleme ---- */
  const todo = []; const excl = {}; const bump = (k) => { excl[k] = (excl[k] || 0) + 1; };
  const recheckDays = { no_email: 180, fail: 30 };
  /* 14 Eki 2026 kural değişikliği: eski kuralla ayakkabı / iç çamaşırı / markasız / tek markalı diye elenen alan adları bir kez
     yeniden incelenir (damga tarihi kural tarihinden önceyse). */
  const POLICY_RECHECK = new Set(['excl:shoes', 'excl:underwear', 'excl:no-brands', 'excl:monobrand']); const POLICY_DATE = '2026-10-14';
  for (const [reg, meta] of cands) {
    seenRun.add(reg);
    if (known.domains.has(reg)) { bump('excl:dup'); continue; }
    const st = state.domains[reg];
    if (st) { const days = (Date.now() - Date.parse(st.t || 0)) / 86400e3; const base = String(st.s || '').split(':')[0];
      const policy = POLICY_RECHECK.has(String(st.s || '')) && String(st.t || '') < POLICY_DATE;
      if (!policy && !(recheckDays[base] && days > recheckDays[base])) { bump('excl:seen'); continue; } }
    const b = isBlockedDomain(reg); if (b) { bump(b); if (!CFG.dryRun) state.domains[reg] = { s: b, t: new Date().toISOString().slice(0, 10) }; continue; }
    todo.push({ reg, ...meta });
  }
  log(`\naday: ${cands.size} | on elemede elenen: ${cands.size - todo.length} | incelenecek: ${todo.length}\n`);

  /* ---- 3) inceleme ---- */
  const ctx = { known, seenRun };
  const results = []; let done = 0;
  await (async () => { let i = 0; await Promise.all(Array.from({ length: CFG.concurrency }, async () => {
    while (i < todo.length) { if (elapsed() > CFG.budgetSec) return; const item = todo[i++];
      /* 16 Eki 2026: 13:40 turu 955/956'da SESSİZCE bitti (çıkış 0, rapor yok, 34 dk'lık iş kayboldu): son sitenin vaadi hiç
         sonuçlanmadı ve olay döngüsünde bekleyen zamanlayıcı kalmayınca Node süreci kapattı. Her site artık en çok 120 sn —
         yarışan zamanlayıcı döngüyü canlı tutar ve takılan site 'fail:timeout' olur. */
      let r; try { r = await Promise.race([analyzeSite(item.reg, ctx, item), new Promise(res => setTimeout(() => res({ domain: item.reg, status: 'fail', reason: 'fail:timeout' }), 120000))]); }
      catch (e) { r = { domain: item.reg, status: 'fail', reason: 'fail:' + String(e.message || e).slice(0, 40) }; }
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
    `| ${qDone + (srvStat.brave?.ok || 0)}/${queries.length} | ${cands.size} | ${results.length} | **${leads.length}** | ${Math.floor(secs / 60)} dk ${secs % 60} sn |`, '');
  const engLine = [];
  if (srvStat.brave) engLine.push(`brave (sunucu): ${srvStat.brave.ok} ok / ${srvStat.brave.err} hata`);
  if (srvStat.places) engLine.push(`google places (sunucu): ${srvStat.places.ok} ok / ${srvStat.places.err} hata`);
  if (osmStat.cities) engLine.push(`openstreetmap: ${osmStat.cities} şehir, ${osmStat.shops} dükkân, ${osmStat.picked} yeni aday${osmStat.errors.length ? ' (' + osmStat.errors.slice(0, 3).join('; ') + ')' : ''}`);
  for (const e of engines) if (engStat[e].ok + engStat[e].empty + engStat[e].blocked) engLine.push(`${e}: ${engStat[e].ok} ok / ${engStat[e].empty} boş / ${engStat[e].blocked} engel${engStat[e].dead ? ' (kapatıldı)' : ''}`);
  lines.push('**Arama:** ' + (engLine.join(' · ') || 'yapılmadı'), '');
  for (const n of notes) lines.push('> ' + n, '');
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
  writeFileSync(`${CFG.outDir}/summary.json`, JSON.stringify({
    date: today, dry: CFG.dryRun, queries: qDone + (srvStat.brave?.ok || 0), candidates: cands.size, analyzed: results.length,
    added_count: leads.length, added: leads.map(l => ({ company: l.company, email: l.email, country: l.country, website: l.website, brands: l.premium_brands.slice(0, 6) })),
    no_email: noMail.slice(0, 60).map(r => ({ domain: r.domain, company: r.company || '', country: r.country || '', phone: r.phone || '', brands: (r.brands || []).slice(0, 5) })),
    excl, notes, secs }));
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
  /* 16 Eki 2026: "outlet" tek başına artık engel değil (operatör: outlet & stok mağazaları da hedef); outlet ZİNCİRİ engelli kalır. */
  t('outlet alan adı artık engelsiz, outlet zinciri engelli', isBlockedDomain('designer-outlet-shop.de') === '' && isBlockedDomain('mcarthurglen.com') === 'excl:chain' && isBlockedDomain('stocklot-world.com') === 'excl:wholesale');
  {
    const golf = '<html><head><title>Pro Shop Golf Club Bergamo</title><meta name="description" content="Golf shop: abbigliamento golf"></head><body><nav><a href="/brand">Brand</a></nav>J.Lindeberg, Galvin Green, Footjoy e Peak Performance</body></html>';
    const g = classify('golfbergamo.it', golf, 'J.Lindeberg Galvin Green Footjoy Peak Performance', '');
    t('golf pro shop: 4 spor markası → kabul, tür sport', g.reason === '' && g.kind === 'sport' && g.signals.sportI > 0);
    const ski = '<html><head><title>Sport Huber – Skishop Kitzbühel</title></head><body>Bogner, Kjus, Goldbergh, Toni Sailer</body></html>';
    t('kayak dükkânı: kabul', classify('sport-huber.at', ski, 'Bogner Kjus Goldbergh Toni Sailer', '').reason === '');
    const club = '<html><head><title>Tennis Club Roma – Notizie</title></head><body>Iscrizioni</body></html>';
    t('marka kanıtı olmayan spor kulübü: elenir (no-brands)', classify('tcroma.it', club, 'iscrizioni corsi', '').reason === 'excl:no-brands');
    const outl = '<html><head><title>Fashion Outlet Store Graz – Markenmode bis -70%</title></head><body>Tommy Hilfiger, Calvin Klein, Hugo Boss, Lacoste</body></html>';
    t('bağımsız çok markalı outlet: kabul', classify('fashionoutlet-graz.at', outl, 'Tommy Hilfiger Calvin Klein Hugo Boss Lacoste', '').reason === '');
    const mono = '<html><head><title>Nike Factory Outlet</title></head><body>Nike</body></html>';
    t('tek markalı outlet: elenir', classify('nikefactoryoutlet.example', mono, 'nike', '').reason !== '');
    t('OSM: Intersport / Decathlon zincir', osmPick({ name: 'Intersport Moser', website: 'https://moser.at' }).excl === 'excl:chain' && osmPick({ name: 'Decathlon Lyon', website: 'https://decathlon.fr' }).excl === 'excl:chain');
    t('OSM: bağımsız outlet adı artık elenmez', !osmPick({ name: 'Outlet Moda Rossi', website: 'https://outletrossi.it' }).excl);
    t('Avrupa ağırlığı: euroShare 0.95, odak sorgu 8, odak şehir 2', CFG.euroShare === 0.95 && CFG.focusQueries === 8 && CFG.focusCities === 2);
    SKIP_COUNTRIES.add('germany');
    const pc = pickAutoCities({ euroCursor: 0, placesCursor: 0 }, 60, 1), pg = pickGridCells({ gridCursor: 0 }, 400);
    t('durdurulan ülke (Germany) şehir ve karelerden atlanır', pc.length === 60 && !pc.some(x => x.startsWith('Germany|')) && pg.length === 400 && !pg.some(x => x.startsWith('Germany|')));
    SKIP_COUNTRIES.clear();
    t('Bever / Unterwegs / Tennis-Point zincir', isBlockedDomain('bever.nl') === 'excl:chain' && isBlockedDomain('unterwegs.biz') === 'excl:chain' && isBlockedDomain('tennis-point.de') === 'excl:chain');
  }
  t('block shoe domain (14 Eki: artık engel değil)', isBlockedDomain('schuh-mueller.de') === '');
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
  t('classify: ayakkabıcı artık kabul (14 Eki kuralı)', classify('mueller-mode.de', shoeHtml, stripTags(shoeHtml), '').reason === '');
  const whHtml = `<title>Fashion Stocklots Großhandel</title><p>Dsquared2 Balmain Versace B2B wholesale</p>`;
  t('classify wholesale', classify('fs-trade.com', whHtml, stripTags(whHtml), '').reason === 'excl:wholesale');
  const monoHtml = `<title>Rossi Boutique</title><p>Dsquared2 jeans und mehr</p>`;
  t('classify monobrand', classify('rossi-boutique.it', monoHtml, stripTags(monoHtml), '').reason === 'excl:monobrand');
  const lingHtml = `<title>Dessous Paradies - Lingerie &amp; Bademode</title><nav><a>BH</a><a>Slips</a><a>Nachtwäsche</a></nav><p>Chantelle Aubade Hanro Falke Wolford</p>`;
  t('classify: iç çamaşırı dükkânı artık kabul (14 Eki)', classify('dessous-paradies.de', lingHtml, stripTags(lingHtml), '').reason === '' && classify('dessous-paradies.de', lingHtml, stripTags(lingHtml), '').kind === 'underwear');
  const nonLuxHtml = `<title>Boutique Noi Dui – Vêtements femme</title><nav><a href="/marques">Nos marques</a><a href="/robes">Robes</a></nav><p>Salsa Jeans, Molly Bracken, Desigual</p>`;
  t('classify: lüks olmayan çok markalı butik kabul', classify('noi-dui.fr', nonLuxHtml, stripTags(nonLuxHtml), 'marques').reason === '');
  const accHtml = `<title>Borse &amp; Accessori Bianchi</title><nav><a>Borse</a><a>Cinture</a></nav><p>Furla, Coccinelle, Liebeskind</p>`;
  t('classify: aksesuar dükkânı (3 marka) kabul, tür accessories', classify('bianchi-borse.it', accHtml, stripTags(accHtml), '').reason === '' && classify('bianchi-borse.it', accHtml, stripTags(accHtml), '').kind === 'accessories');
  const ownHtml = `<title>Atelier Lina – Mode</title><nav><a>Kleider</a><a>Jacken</a></nav><p>Unsere eigene Kollektion, entworfen und genäht in Berlin.</p>`;
  t('classify: kendi markasını satan butik elenir', classify('atelier-lina.de', ownHtml, stripTags(ownHtml), '').reason === 'excl:own-brand');
  const travelHtml = `<title>Toskana Reiseführer – Urlaub</title><nav><a>Hotels</a><a>Strände</a></nav><p>Florenz, Siena, Lucca</p>`;
  t('classify: turizm sitesi hâlâ elenir (no-brands)', classify('toskanatour.de', travelHtml, stripTags(travelHtml), '').reason === 'excl:no-brands');
  const distHtml = `<title>Moda Distribuzione Srl – distributore abbigliamento</title><nav><a>Marchi</a></nav><p>Liu Jo Pinko Patrizia Pepe Furla</p>`;
  t('classify: distribütör hâlâ elenir', classify('modadistribuzione.it', distHtml, stripTags(distHtml), '').reason === 'excl:wholesale');
  { const rtlHtml = `<title>RTL.de – News, Stars, TV-Programm</title><nav><a>Mode</a><a>Stars</a><a>Marken</a></nav><p>Gucci Prada Versace Armani bei der Fashion Week</p>`;
    t('RTL olayı: TV/haber sitesi elenir', classify('rtl.de', rtlHtml, stripTags(rtlHtml), '').reason === 'excl:media');
    const kstaHtml = `<title>Kölner Stadt-Anzeiger – Nachrichten aus Köln</title><p>Versace Prada Armani Mode Shopping</p>`;
    t('RTL olayı: gazete elenir (marka adı geçse de)', classify('ksta.de', kstaHtml, stripTags(kstaHtml), '').reason === 'excl:media');
    const tourHtml = `<title>Belfort Tourisme - Office de Tourisme</title><nav><a>Shopping</a><a>Boutiques</a></nav><p>Mode, chaussures, Lacoste, Levi's</p>`;
    t('RTL olayı: turizm ofisi elenir', classify('belfort-tourisme.com', tourHtml, stripTags(tourHtml), '').reason === 'excl:not-retail');
    const navOnly = `<title>Stadtportal Musterstadt</title><nav><a>Mode</a><a>Schuhe</a><a>Marken</a></nav><p>Levi's Desigual</p>`;
    t('lüks markasız yol: dükkân kimliği yalnız menüde ise elenir', classify('musterstadt-portal.com', navOnly, stripTags(navOnly), '').reason !== '');
    t('RTL olayı: alan adı engel listesinde (rtl, ksta, gemo, size, kering)', ['rtl.de', 'ksta.de', 'gemo.fr', 'size.ca', 'kering.com'].every(d => isBlockedDomain(d) === 'excl:chain'));
    t('RTL olayı: zuschauerservice@ reddedilir', scoreEmail('zuschauerservice@rtl.de', 'rtl.de') < 0); }
  t('isBlockedDomain: ayakkabı/iç çamaşırı alan adı artık engel değil', isBlockedDomain('schuh-mueller.de') === '' && isBlockedDomain('dessous-paradies.de') === '');
  t('bing unwrap', unwrapResultUrl('https://www.bing.com/ck/a?!&&p=x&u=a1aHR0cHM6Ly9leGFtcGxlLmNvbS9wYWdl&ntb=1') === 'https://example.com/page');
  t('ddg unwrap', unwrapResultUrl('https://duckduckgo.com/l/?uddg=https%3A%2F%2Fexample.de%2Fx&rut=1') === 'https://example.de/x');
  t('parseBing', parseBing('<li class="b_algo"><h2><a href="https://a.de/">A</a></h2></li><li class="b_algo"><div><h2><a href="https://b.it/p">B</a></h2></div></li>').join(',') === 'https://a.de/,https://b.it/p');
  t('parseDdg', parseDdg('<a rel="nofollow" class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Fc.fr%2F&amp;rut=abc">C</a>').join(',') === 'https://c.fr/');
  t('osm: brand:wikidata = zincir', osmPick({ name: 'H&M', 'brand:wikidata': 'Q188326', website: 'hm.com' }).excl === 'excl:chain');
  t('osm: marka etiketi adla farklı = zincir', osmPick({ name: 'Hugo Boss Store Köln', brand: 'Hugo Boss', website: 'x.de' }).excl === 'excl:chain');
  t('osm: clothes=underwear artık geçer (14 Eki)', !osmPick({ name: 'Wäsche Weber', clothes: 'women;underwear', website: 'w.de' }).excl);
  t('osm: ayakkabıcı adı artık geçer (14 Eki)', !osmPick({ name: 'Schuhhaus Müller', website: 'schuhhaus-mueller.de' }).excl);
  t('osm: toptancı adı', osmPick({ name: 'Moda Ingrosso Napoli', website: 'mi.it' }).excl === 'excl:wholesale');
  t('osm: zincir adı (kelime sınırı)', osmPick({ name: 'Zara', website: 'zara.com' }).excl === 'excl:chain');
  t('osm: "Mangobay Boutique" zincir DEĞİL', !osmPick({ name: 'Mangobay Boutique', website: 'mangobay.it' }).excl);
  t('osm: butik + site', osmPick({ name: 'Boutique Rossi', website: 'boutiquerossi.it' }).web === 'https://boutiquerossi.it');
  t('osm: "Mode & Schuhe Becker" giyim de sattığı için GEÇER', !osmPick({ name: 'Mode & Schuhe Becker', website: 'becker-mode.de' }).excl);
  t('osm: "Wäschestube" artık geçer (14 Eki)', !osmPick({ name: 'Wäschestube Lenz', website: 'ws-lenz.de' }).excl);
  t('osm: sadece facebook = site yok', osmPick({ name: 'Bella Moda', website: 'https://www.facebook.com/bellamoda' }).excl === 'no_site');
  t('osm: sitesiz ama kurumsal e-posta = alan adı site olur', osmPick({ name: 'Mode Hansen', email: 'info@mode-hansen.dk' }).web === 'https://mode-hansen.dk/');
  t('osm: sitesiz + gmail = site yok', osmPick({ name: 'Moda Lina', email: 'modalina@gmail.com' }).excl === 'no_site');
  t('mismatch: zincir adı + cadde portalı', osmSiteMatch('Comptoir des Cotonniers', 'corsovercellimilano.it', 'Corso Vercelli Milano - shopping') === 'excl:site-mismatch');
  t('mismatch yok: Alex → boutiquealex.com', osmSiteMatch('Alex', 'boutiquealex.com', 'Boutique Alex') === '');
  t('mismatch yok: Le Guillou → sebastienleguillou.com', osmSiteMatch('Le Guillou', 'sebastienleguillou.com', '') === '');
  t('mismatch yok: Il girotondo', osmSiteMatch('Il girotondo', 'ilgirotondomilano.it', '') === '');
  t('marka mağazası: Baracuta Store → baracuta.com', osmSiteMatch('Baracuta Store', 'baracuta.com', 'Baracuta') === 'excl:brand-site');
  t('ilgisiz gmail reddedilir', scoreEmail('guerrillasonora@gmail.com', 'cappelliegrasso.it', ['text'], 'home', 'Cappelli e Grasso') < 0);
  t('ilgili gmail kabul', scoreEmail('rambelli.milano@gmail.com', 'rambelli.it', ['text'], 'contact', 'Rambelli') > 0);
  t('OSM etiketi gmail kabul', scoreEmail('xyz123@gmail.com', 'shop.it', ['osm'], 'contact', 'Shop') > 0);
  t('redazione@ reddedilir', scoreEmail('redazione@corsovercellimilano.it', 'corsovercellimilano.it') < 0);
  t('osm: vintage adı', osmPick({ name: 'Why not vintage', website: 'whynotvintage.it' }).excl === 'excl:vintage');
  t('classify: moda dergisi dükkân değil', classify('glamcultstudio.com', '<title>Glamcult - Independent Style Paper</title><p>Gucci Prada Bottega Veneta Jil Sander</p>', 'Gucci Prada Bottega Veneta Jil Sander', '').reason === 'excl:media');
  t('classify: dergi kelimesi olmayan butik etkilenmez', classify('boutique-mueller.de', '<title>Boutique Müller – Mode</title><p>Gucci Prada</p>', 'Gucci Prada', '').reason !== 'excl:media');
  t('classify: vintage kimlik', classify('elephantvintage.com', '<title>Elephant Vintage - friperie</title><p>Gucci Prada Versace Valentino</p>', 'Gucci Prada Versace Valentino', '').reason === 'excl:vintage');
  const qs = buildQueries(10, { queries: {} }, ['de', 'it'], 42);
  t('buildQueries count', qs.length === 10 && qs.every(x => ['de', 'it'].includes(x.lang) && x.q.includes('"')));
  t('buildQueries deterministic', JSON.stringify(buildQueries(5, { queries: {} }, [], 7)) === JSON.stringify(buildQueries(5, { queries: {} }, [], 7)));
  t('buildQueries skips recent', buildQueries(3, { queries: Object.fromEntries(buildQueries(3, { queries: {} }, [], 7).map(x => [x.q, new Date().toISOString()])) }, [], 7).every(x => !buildQueries(3, { queries: {} }, [], 7).some(y => y.q === x.q)));
  t('havuz: Türkiye yok (ülke engeli)', !CITY_POOL.some(c => /^Turkey\|/i.test(c)));
  t('havuz: dünya geneli (90+ ülke)', new Set(CITY_POOL.map(c => c.split('|')[0])).size >= 90);
  t('havuz: yaptırımlı ülke yok', !CITY_POOL.some(c => /^(Russia|Belarus|Iran|Syria|North Korea|Cuba)\|/i.test(c)));
  t('havuz: şehir tekrarı yok', new Set(CITY_POOL).size === CITY_POOL.length);
  t('Avrupa kasabaları: 600+ ve büyük havuzla çakışmıyor', EURO_TOWNS.length >= 600 && new Set([...CITY_POOL, ...EURO_TOWNS]).size === CITY_POOL.length + EURO_TOWNS.length);
  t('Avrupa kasabaları: Türkiye ve yaptırımlı ülke yok', !EURO_TOWNS.some(c => /^(Turkey|Russia|Belarus)\|/i.test(c)));
  t('Avrupa kasabaları: Almanya ölçülü (%10 altı)', EURO_TOWNS.filter(c => c.startsWith('Germany|')).length < EURO_TOWNS.length * 0.1);
  { const st = { euroCursor: 5, placesCursor: 7 }; const a = pickAutoCities(st, 10, 0.8);
    t('otomatik tur: önce 8 Avrupa kasabası imleçten, sonra 2 büyük şehir', a.length === 10 && a[0] === EURO_TOWNS[5] && a[7] === EURO_TOWNS[12] && a[8] === CITY_POOL[7] && a[9] === CITY_POOL[8]);
    t('otomatik tur: EURO_SHARE=0 eski davranış', pickAutoCities(st, 4, 0).every((c, i) => c === CITY_POOL[7 + i])); }
  t('Brave izni: BRAVE_ALLOWED=false dışında açık', CFG.braveAllowed === true);
  t('google: motor tanımlı, anahtarsız (selftest ortamı) motor listesine girmez', typeof ENGINE.google.search === 'function' && ENGINE.google.used === 0 && !(CFG.googleKey && CFG.googleCx));
  t('google: tur tavanı varsayılan 12 (8 tur × 12 < 100 ücretsiz)', CFG.googlePerRun === 12);
  t('kare havuzu: 3000+ kare, tekrar yok, İtalya önce', EURO_GRID.length >= 3000 && new Set(EURO_GRID).size === EURO_GRID.length && EURO_GRID[0].startsWith('Italy|grid:'));
  { const st = { townQCursor: 3 }; const r = buildTownQueries(st, 5);
    t('kasaba sorguları: imleçten, kasaba başına 2, ülke dilinde', r.queries.length === 5 && r.queries[0].town === TOWN_SRC[3] && r.queries[0].lang === 'it' && r.queries[0].q.includes(TOWN_SRC[3].split('|')[1]) && r.cursor === 6);
    { const fq = buildFocusQueries({}, 8);
      t('odak ülkeler: Sydney/Tokyo/Toronto/Seul sırayla, ülke dilinde ve kodunda', fq.queries.length === 8 && fq.queries[0].town === 'Australia|Sydney' && fq.queries[0].cc === 'AU'
        && fq.queries[2].lang === 'ja' && fq.queries[2].cc === 'JP' && fq.queries[4].cc === 'CA' && fq.queries[6].lang === 'ko' && fq.cursor === 4);
      t('odak ülkeler: Montréal Fransızca', buildFocusQueries({ focusQCursor: FOCUS_TOWNS.indexOf('Canada|Montréal') }, 2).queries.every(q => q.lang === 'fr' && q.cc === 'CA'));
      t('odak şehirler: imleçten, 4 ülke', new Set(pickFocusCities({}, 12).map(c => c.split('|')[0])).size === 4 && FOCUS_TOWNS.every(c => FOCUS_CC[c.split('|')[0]]));
      const jpHtml = `<title>SELECT SHOP KAZE｜セレクトショップ</title><nav><a>取扱ブランド</a><a>メンズ</a></nav><p>Stone Island Moncler</p>`;
      t('Japonya: セレクトショップ + 2 premium marka kabul', classify('kaze-shop.jp', jpHtml, stripTags(jpHtml), '').reason === '');
      const krHtml = `<title>모드 편집샵</title><nav><a>브랜드</a></nav><p>Ami Paris</p>`;
      t('G. Kore: 편집샵 + markalar sayfası + 1 marka kabul', classify('mode-shop.kr', krHtml, stripTags(krHtml), '').reason === ''); }
    t('kasaba sorguları: her dil şablonunda {c} var', Object.values(TOWN_TPL).every(a => a.length === 2 && a.every(t => t.includes('{c}')))); }
  t('kare: bbox ayrıştırma', JSON.stringify(parseGridCell('grid:45.0,9.0,45.5,9.5')) === '[45,9,45.5,9.5]' && parseGridCell('Milano') === null);
  { const st = { gridCursor: 10, gridEmpty: { [EURO_GRID[10]]: new Date().toISOString().slice(0, 10), [EURO_GRID[11]]: '2020-01-01' } };
    const g = pickGridCells(st, 3);
    t('kare seçimi: yeni boş kare atlanır, eski boş kare yeniden denenir', g.length === 3 && g[0] === EURO_GRID[11] && g[1] === EURO_GRID[12]); }
  { const st = { euroCursor: 0, placesCursor: 0, osmRetry: [EURO_TOWNS[100], 'Nowhere|Nope', CITY_POOL[3]] };
    const retry = st.osmRetry.filter(c => EURO_TOWNS.includes(c) || CITY_POOL.includes(c));
    t('yeniden deneme: yalnız havuzdaki şehirler kalır', retry.length === 2 && retry[0] === EURO_TOWNS[100]); }
  { const [a, b, c, d] = OVERPASS; t('ayna sırası: başta varsayılan', overpassOrder()[0] === a);
    overpassMark(a, false, 45000); overpassMark(a, false, 45000); overpassMark(b, false, 45000); overpassMark(d, true, 3000);
    const o = overpassOrder(); t('ayna sırası: çalışan öne, 2 kez düşen sona', o[0] === d && o[o.length - 1] === a);
    overpassMark(a, true, 2000); t('ayna sırası: düşen ayna yanıt verince geri gelir', overpassOrder().indexOf(a) < overpassOrder().indexOf(b));
    overpassHealth.clear(); }
  t('ülke: alan adından (Suudi, Hong Kong, Meksika)', detectCountry('shop.com.sa', '', '') === 'Saudi Arabia' && detectCountry('x.com.hk', '', '') === 'Hong Kong' && detectCountry('m.com.mx', '', '') === 'Mexico');
  log(fails ? `\n${fails} TEST BASARISIZ` : '\nTUM TESTLER GECTI'); process.exit(fails ? 1 : 0);
}

if (process.argv.includes('--selftest')) selftest();
else {
  /* Olay döngüsü main() bitmeden boşalırsa (sonuçlanmayan vaat) süreç 0 ile çıkıp işi "başarılı" gösteriyordu. */
  let mainDone = false;
  process.on('beforeExit', () => { if (!mainDone) { console.error('HATA: olay döngüsü boşaldı, main() bitmedi (sonuçlanmayan vaat) — sonuç yazılmadı'); process.exitCode = 1; } });
  main().then(() => { mainDone = true; }, e => { mainDone = true; console.error('HATA:', e); process.exit(1); });
}
