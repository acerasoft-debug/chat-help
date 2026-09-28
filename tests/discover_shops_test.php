<?php
/* BUTIK AYAKKABI + AKSESUAR KESFI (discover-shops.yml) -- 28 Eyl 2026, operator:
 * "bana workflow kur butik ayakkabi ve aksesuar arayan distribitör haric".
 *
 * Dort parca, dordu de GERCEKTEN kosturuluyor (kaynak taramasi yalniz is akisi
 * guvenligi icin):
 *   1. discover_overture.py classify() -- sentetik Overture satirlari, iki yon:
 *      zincir / dagitici / cok subeli / kapali / sitesiz / rehber ELENIR,
 *      bagimsiz butik, 2 kapili dukkan, ayni barindiricidaki ILGISIZ dukkanlar GECER.
 *   2. discover_resolve.php -- karar fonksiyonu sahte ag ile; CLI yerel php -S
 *      "dukkanlariyla" uctan uca. Adresin kendisi HIC yazilmiyor.
 *   3. discover_known.php -- kum havuzu HOME'unda; serbest posta saglayicisi ve
 *      kisi verisi BASILMIYOR.
 *   4. discover_report.py -- kovalar, <=20'lik partiler, mektup onerisi, '@' yok.
 * Gonderim / yazma yok: is akisi yalniz okur ve rapor basar. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_discover_'.bin2hex(random_bytes(4));
mkdir($sand, 0777, true);
$rm = function (string $d) use (&$rm) { foreach (glob($d.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $f) is_dir($f) && !is_link($f) ? $rm($f) : unlink($f); @rmdir($d); };
$run = function (array $cmd, string $stdin = '', ?array $env = null) {
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    fwrite($pipes[0], $stdin); fclose($pipes[0]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), (string)$o, (string)$e];
};
/* Vekil degiskenleri alt surece verilmez: yerel "dukkanlar" 127.0.0.1'de ve olu
   alan adi denemesi DNS'te hemen dusmeli (GitHub makinesinde vekil zaten yok). */
$env = [];
foreach (getenv() as $k => $v) if (!preg_match('/^(https?|all|no)_proxy$/i', $k)) $env[$k] = $v;

echo "== 1. classify(): eleme ve gecis, iki yon ==\n";
$R = function (string $name, array $webs, string $city, array $x = []) {
    return array_merge(['id' => 'x', 'name' => $name, 'cat' => 'shoe_store', 'websites' => $webs, 'emails' => [],
        'brand_wd' => null, 'brand_name' => null, 'city' => $city, 'postcode' => '', 'confidence' => 0.9,
        'operating_status' => 'open'], $x);
};
$fr = [
    $R('Chaussures Chainco', ['https://chainco.fr'], 'Paris', ['brand_name' => 'Chainco']),
    $R('Showroom Milano Calzature', ['https://smc.fr'], 'Paris'),
    $R('La Showroomerie', ['https://showroomerie.fr'], 'Lyon'),
    $R('Distributori Calzature Rossi', ['https://dcr.fr'], 'Nice'),
    $R('Grossi Calzature', ['https://grossicalzature.fr'], 'Nice'),
    $R('Boutique Fermee', ['https://fermee.fr'], 'Paris', ['operating_status' => 'permanently_closed']),
    $R('Sans Site', [], 'Paris'),
    $R('Insta Only', ['https://www.instagram.com/instaonly'], 'Paris'),
    $R('Chaussures Annuaire', ['https://www.pagesjaunes.fr/pros/123'], 'Paris'),
    $R('Multi Shoes Paris', ['https://www.multishoes.fr/paris'], 'Paris'),
    $R('Multi Shoes Lyon', ['https://multishoes.fr/lyon'], 'Lyon'),
    $R('Multi Shoes Nice', ['https://multishoes.fr/nice'], 'Nice'),
    $R('Chaussures Durand', ['https://durand-paris.fr'], 'Paris'),
    $R('Chaussures Durand', ['https://durand-lyon.fr'], 'Lyon'),
    $R('Chaussures Durand', ['https://durand-nice.fr'], 'Nice'),
    $R('Deux Portes Chaussures', ['https://deuxportes.fr'], 'Rennes'),
    $R('Deux Portes Chaussures', ['https://deuxportes.fr/brest'], 'Brest'),
    $R('Centre Ville Chaussures', ['https://centreville.fr'], 'Toulouse'),
    $R('Centre Ville Chaussures', ['https://centreville.fr/b'], 'Toulouse'),
    $R('Centre Ville Chaussures', ['https://centreville.fr/c'], 'Toulouse'),
    $R('Deja Connu', ['https://dejaconnu.fr'], 'Paris'),
    $R('Ortho Pied', ['https://orthopied.fr'], 'Paris', ['cat' => 'orthopedic_shoe_store']),
    $R('Peu Sur', ['https://peusur.fr'], 'Paris', ['confidence' => 0.2]),
    $R('Chaussures Martin', ['http://chaussuresmartin.free.fr'], 'Rennes'),
    $R('Boutique Lea', ['http://boutiquelea.free.fr/'], 'Brest'),
    $R('Sac et Cuir', ['http://sacetcuir.free.fr'], 'Nantes', ['cat' => 'leather_goods_store']),
    $R('Chaussures Le Carrer', ['https://chaussures-lecarrer.fr/'], 'Lanester', ['emails' => ['contact@chaussures-lecarrer.fr']]),
    $R('Le Gal et Cano', ['https://www.kipling.com/fr'], 'Vannes', ['cat' => 'handbag_store']),
    // hiyerarsiden SIZAN bir alt dal (sorgu fashion_accessories_store'un butun
    // alt dallarini getiriyor) -- kabul listesinde yok -> aday OLMAZ, sebebiyle elenir
    $R('Bijouterie Or Fin', ['https://bijouterieorfin.fr'], 'Lille', ['cat' => 'jewelry_store']),
    $R('Chapellerie Mercier', ['https://chapelleriemercier.fr'], 'Lille', ['cat' => 'hat_store']),
];
$it = [
    $R('Negozio Uno', ['https://uno.it'], 'Roma', ['confidence' => 0.99]),
    $R('Negozio Due', ['https://due.it'], 'Roma', ['confidence' => 0.95]),
    $R('Negozio Tre', ['https://tre.it'], 'Roma', ['confidence' => 0.80]),
    $R('Negozio Quattro', ['https://quattro.it'], 'Roma', ['confidence' => 0.90]),
    $R('Negozio Cinque', ['https://cinque.it'], 'Roma', ['confidence' => 0.60, 'emails' => ['cinque@example.it']]),
];
file_put_contents($sand.'/known.txt', "dejaconnu.fr\n@shopmail.fr\nwww.Other-Shop.com\n@orange.fr\n");
$py = <<<'PY'
import importlib.util, json, sys
sys.dont_write_bytecode = True   # depoda scripts/__pycache__ birakmasin
spec = importlib.util.spec_from_file_location("dov", sys.argv[1])
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
inp = json.load(sys.stdin)
known = m.load_known(inp["known_file"])
final, dropped = m.classify({"FR": inp["rows"]["FR"]}, 100, inp["min_conf"], known)
capped, _ = m.classify({"IT": inp["rows"]["IT"]}, inp["limit"], inp["min_conf"], known)
print(json.dumps({"final": final, "dropped": dropped, "capped": capped, "known": sorted(known),
    "ident": {h: m.site_identity(h) for h in inp["hosts"]},
    "match": [m.name_matches_site(n, s) for n, s in inp["pairs"]],
    "sql": m.build_sql("SRC", ["fr", "IT"], ["shoe_store", "x'y"], True),
    "sql_nobox": m.build_sql("SRC", ["FR"], ["shoe_store"], False),
    "sql_unknown": m.build_sql("SRC", ["FR", "XX"], ["shoe_store"], True),
    "mix": m.category_mix([{"cat": "shoe_store"}, {"cat": "hat_store"}, {"cat": "shoe_store"}, {"cat": None}]),
    "mix_empty": m.category_mix([])}))
PY;
$hosts = ['https://www.Chaussures-Lecarrer.fr/contact', 'shop.example.co.uk', 'https://x.wixsite.com/shop',
          'https://m.facebook.com/x', 'https://www.pagesjaunes.fr/x', 'localhost', 'https://negozio.altervista.org',
          'mailto:x@y.fr', 'www.boutique.com.au', 'a.b.example.de'];
$pairs = [['Chaussures Le Carrer', 'chaussures-lecarrer.fr'], ['Le Gal et Cano', 'kipling.com'],
          ['My Shoes', 'my-shoes.com'], ['Chaussures Martin', 'chaussuresmartin.free.fr'], ['Chaussures Martin', 'chaussures.fr'],
          ['Schuhhaus Müller', 'schuhhaus-mueller.de'], ['Schuhhaus Müller', 'muller-schuhe.de']];
[$rc, $o, $e] = $run(['python3', '-c', $py, $root.'/scripts/discover_overture.py'],
    json_encode(['rows' => ['FR' => $fr, 'IT' => $it], 'limit' => 3, 'min_conf' => 0.5,
                 'known_file' => $sand.'/known.txt', 'hosts' => $hosts, 'pairs' => $pairs]));
$J = json_decode($o, true);
$t('python classify kostu', $rc === 0 && is_array($J));
if (!is_array($J)) { echo $e; $rm($sand); exit(1); }
$why = []; foreach ($J['dropped'] as $d) $why[$d['name']][] = $d['why'];
$cand = []; foreach ($J['final'] as $c) $cand[$c['name']] = $c;
$has = function (string $n, string $w) use ($why) { foreach ($why[$n] ?? [] as $x) if (str_starts_with($x, $w)) return true; return false; };
$t('marka eslesmesi -> ZINCIR', $has('Chaussures Chainco', 'ZINCIR'));
$t('adda "Showroom" -> DAGITICI', $has('Showroom Milano Calzature', 'DAGITICI'));
$t('KELIME SINIRI: "La Showroomerie" GECER (mango/zara dersi)', isset($cand['La Showroomerie']));
$t('cogul "Distributori" -> DAGITICI', $has('Distributori Calzature Rossi', 'DAGITICI'));
$t('soyad "Grossi Calzature" GECER', isset($cand['Grossi Calzature']));
$t('kapali -> KAPALI', $has('Boutique Fermee', 'KAPALI'));
$t('site yok -> SITESIZ', $has('Sans Site', 'SITESIZ'));
$t('yalniz instagram -> SITESIZ', $has('Insta Only', 'SITESIZ'));
$t('rehber sitesi (pagesjaunes) -> SITESIZ', $has('Chaussures Annuaire', 'SITESIZ'));
$t('ayni site 3 yerde 3 sehirde -> COK SUBELI (ucu de)',
   $has('Multi Shoes Paris', 'COK SUBELI') && $has('Multi Shoes Lyon', 'COK SUBELI') && $has('Multi Shoes Nice', 'COK SUBELI'));
$t('ayni ad 3 yerde 3 sehirde -> COK SUBELI', count(array_filter($why['Chaussures Durand'] ?? [], fn($x) => str_starts_with($x, 'COK SUBELI'))) === 3);
$t('2 kapi 2 sehir (KURAL 38: bagimsiz) -> TEK aday, branches=2',
   isset($cand['Deux Portes Chaussures']) && $cand['Deux Portes Chaussures']['branches'] === 2 && !isset($why['Deux Portes Chaussures']));
$t('ayni sehirde 3 kapi -> elenmez, TEK aday', isset($cand['Centre Ville Chaussures']) && $cand['Centre Ville Chaussures']['branches'] === 3);
$t('sunucuda kayitli -> ZATEN KAYITLI', $has('Deja Connu', 'ZATEN KAYITLI'));
$t('ortopedi dali -> hedef disi', $has('Ortho Pied', 'hedef disi'));
$t('KABUL LISTESINDE OLMAYAN alt dal (jewelry_store) -> "hedef disi dal (jewelry_store)", aday DEGIL',
   $has('Bijouterie Or Fin', 'hedef disi dal (jewelry_store)') && !isset($cand['Bijouterie Or Fin']));
$t('hedef alt dallar GECER (hat_store, handbag_store) -- ters yon',
   isset($cand['Chapellerie Mercier'], $cand['Le Gal et Cano']));
$t('dusuk guven -> elenir', $has('Peu Sur', 'dusuk guven'));
$t('AYNI barindiricidaki (free.fr) ILGISIZ uc dukkan: ucu de aday, cok subeli DEGIL',
   isset($cand['Chaussures Martin'], $cand['Boutique Lea'], $cand['Sac et Cuir'])
   && $cand['Boutique Lea']['site'] === 'boutiquelea.free.fr');
$t('Overture e-postasi adaya YAZILMAZ (yalniz var/yok)',
   ($cand['Chaussures Le Carrer']['overture_email'] ?? null) === true && !str_contains(json_encode($J['final']), '@'));
$t('marka sitesi yazmis bayi -> site_matches_name=false', ($cand['Le Gal et Cano']['site_matches_name'] ?? null) === false);
$t('kendi sitesi -> site_matches_name=true', ($cand['Chaussures Le Carrer']['site_matches_name'] ?? null) === true);
$itc = $J['capped'];
$t('ulke basina tavan (3) ve siralama: Overture e-postali once, sonra guven',
   count($itc) === 3 && $itc[0]['name'] === 'Negozio Cinque' && $itc[1]['name'] === 'Negozio Uno' && $itc[2]['name'] === 'Negozio Due');
$t('ulke adi kayitta (add-and-send country girdisi)', ($cand['Grossi Calzature']['country'] ?? '') === 'France');
$I = $J['ident'];
$t('site kimligi: www + yol atilir, harf kucuk', $I['https://www.Chaussures-Lecarrer.fr/contact'] === 'chaussures-lecarrer.fr');
$t('site kimligi: co.uk iki parcali', $I['shop.example.co.uk'] === 'example.co.uk');
$t('site kimligi: platform alt alan adi TAM host', $I['https://x.wixsite.com/shop'] === 'x.wixsite.com' && $I['https://negozio.altervista.org'] === 'negozio.altervista.org');
$t('site kimligi: sosyal / rehber / noktasiz / mailto -> bos',
   $I['https://m.facebook.com/x'] === '' && $I['https://www.pagesjaunes.fr/x'] === '' && $I['localhost'] === '' && $I['mailto:x@y.fr'] === '');
$t('ad-site eslesmesi: kendi adi / marka sitesi / tamami genel ad / platform', array_slice($J['match'], 0, 4) === [true, false, true, true]);
$t('ayirt edici kelime KARAR VERIR: "Chaussures Martin" -> chaussures.fr ESLESMEZ', $J['match'][4] === false);
$t('Almanca: Müller -> mueller VE muller', $J['match'][5] === true && $J['match'][6] === true);
$Q = preg_replace('/\s+/', ' ', $J['sql']);
$t('TEK gecis: iki ulkenin BIRLESIK kutusu (FR bati/kuzey + IT dogu/guney)',
   str_contains($Q, 'bbox.xmin >= -5.14') && str_contains($Q, 'bbox.xmax <= 18.52') && str_contains($Q, 'bbox.ymin >= 35.49') && str_contains($Q, 'bbox.ymax <= 51.09'));
$t('asil karar adresin ulke kodu (buyuk harfe cekilmis IN listesi)', str_contains($Q, "upper(addresses[1].country) IN ('FR','IT')"));
$t('kategori tirnaklari kacisli (SQL enjeksiyonu yok)', str_contains($Q, "'x''y'") && !str_contains($Q, "'x'y'"));
$t('kutusu olmayan ulke varsa kutu HIC uygulanmaz (sessiz budama yok)', !str_contains($J['sql_unknown'], 'bbox.') && str_contains($J['sql_unknown'], "IN ('FR','XX')"));
$t('yerel dosyada kutu yok', !str_contains($J['sql_nobox'], 'bbox.') && str_contains($J['sql_nobox'], "IN ('FR')"));
$t('ulke basina kategori dagilimi basilir (en kalabalik once, bos kategori "?")',
   $J['mix'] === 'shoe_store 2, hat_store 1, ? 1' && $J['mix_empty'] === '-');
$t('kayitli liste: www atilir, @alanadi alan adina doner',
   in_array('other-shop.com', $J['known'], true) && in_array('shopmail.fr', $J['known'], true) && in_array('dejaconnu.fr', $J['known'], true));

echo "\n== 1b. main() UCTAN UCA: GERCEK SQL, DuckDB, sentetik Overture parquet ==\n";
/* build_sql yukarida yalniz METIN olarak sinaniyor; burada sorgu DuckDB'de gercekten
   kosuyor. Olculen: hiyerarsi genislemesi hedef disi bir alt dali (mucevher) GERCEKTEN
   getiriyor ve kabul listesi onu aday yapmiyor; kucuk harfli ulke kodu kaybolmuyor;
   hedef olmayan dal ve baska ulke hic gelmiyor; ulke basina kategori dagilimi basiliyor. */
[$drc] = $run(['python3', '-c', 'import duckdb']);
if ($drc !== 0) {
    echo "  ATLANDI: python duckdb yok (is akisi onu pip ile kuruyor)\n";
} else {
    $pq = "$sand/ov.parquet";
    $gen = <<<'PY'
import duckdb, sys
con = duckdb.connect()
def row(i, name, prim, hier, web, cc):
    return (f"('{i}', {{'primary': '{name}'}}, {{'primary': '{prim}', 'hierarchy': {hier}}}, ['{web}'],"
            f" []::VARCHAR[], []::VARCHAR[], {{'wikidata': NULL::VARCHAR, 'names': {{'primary': NULL::VARCHAR}}}},"
            f" [{{'locality': 'Paris', 'country': '{cc}', 'postcode': '75001'}}], 0.9, 'open')")
S, A = "'shopping','fashion_and_apparel_store'", "'fashion_accessories_store'"
rows = [row(1, "Chaussures Alpha", "shoe_store", f"[{S},'shoe_store']", "https://alpha-chaussures.fr", "FR"),
        row(2, "Bijoux Beta", "jewelry_store", f"[{S},{A},'jewelry_store']", "https://bijouxbeta.fr", "FR"),
        row(3, "Vetements Gamma", "clothing_store", f"[{S},'clothing_store']", "https://gamma.fr", "FR"),
        row(4, "Chapeaux Delta", "hat_store", f"[{S},{A},'hat_store']", "https://chapeauxdelta.fr", "fr"),
        row(5, "Schuhhaus Epsilon", "shoe_store", f"[{S},'shoe_store']", "https://epsilon.de", "DE"),
        row(6, "Podologie Zeta", "orthopedic_shoe_store", f"[{S},'shoe_store','orthopedic_shoe_store']", "https://zeta.fr", "FR")]
con.execute("COPY (SELECT * FROM (VALUES " + ",".join(rows) + ") t(id, names, taxonomy, websites, emails, phones, brand,"
            " addresses, confidence, operating_status)) TO '" + sys.argv[1] + "' (FORMAT PARQUET)")
PY;
    [$grc, , $ge] = $run(['python3', '-c', $gen, $pq]);
    $t('sentetik parquet uretildi', $grc === 0 && is_file($pq));
    if ($grc !== 0) echo $ge;
    [$mrc, $mo, $me] = $run(['python3', $root.'/scripts/discover_overture.py', '--countries', 'FR', '--kinds', 'shoes,accessories',
        '--sample-parquet', $pq, '--out', "$sand/mc.json", '--dropped-out', "$sand/md.json"]);
    $mc = json_decode((string)@file_get_contents("$sand/mc.json"), true) ?: [];
    $md = json_decode((string)@file_get_contents("$sand/md.json"), true) ?: [];
    $mcn = array_column($mc, 'name'); sort($mcn);
    $mdw = []; foreach ($md as $d) $mdw[$d['name']] = $d['why'];
    $t('main kostu', $mrc === 0);
    if ($mrc !== 0) echo $me;
    $t('SQL tek geciste 4 yer getirdi (hedef disi dal, kucuk harfli ulke dahil; giyim + DE yok)', str_contains($mo, 'tek gecis: 4 yer'));
    $t('ulke basina kategori dagilimi GERCEK kosuda basiliyor',
       (bool)preg_match("/FR: Overture'da 4 yer \| .*jewelry_store 1/", $mo) && str_contains($mo, 'hat_store 1') && str_contains($mo, 'orthopedic_shoe_store 1'));
    $t('adaylar yalniz hedef dallar: ayakkabi + sapka (kucuk harfli "fr" kaybolmadi)', $mcn === ['Chapeaux Delta', 'Chaussures Alpha']);
    $t('hiyerarsiden SIZAN mucevher dali elendi, SEBEBIYLE', ($mdw['Bijoux Beta'] ?? '') === 'hedef disi dal (jewelry_store)');
    $t('ortopedi elendi, sebebiyle', ($mdw['Podologie Zeta'] ?? '') === 'hedef disi dal (orthopedic_shoe_store)');
    $t('hedef olmayan dal ve baska ulke sorguya HIC girmedi', !isset($mdw['Vetements Gamma']) && !isset($mdw['Schuhhaus Epsilon'])
       && !in_array('Vetements Gamma', $mcn, true) && !in_array('Schuhhaus Epsilon', $mcn, true));
}

echo "\n== 2. PHP kok alan adi = Python site kimligi (iki dil ayni kural) ==\n";
require_once $root.'/vestra/inc/notify.php';
require_once $root.'/scripts/discover_resolve.php';
foreach (['https://www.Chaussures-Lecarrer.fr/contact' => 'chaussures-lecarrer.fr', 'shop.example.co.uk' => 'example.co.uk',
          'www.boutique.com.au' => 'boutique.com.au', 'a.b.example.de' => 'example.de'] as $h => $want) {
    $t("discover_site_root($h) == python ($want)", discover_site_root($h) === $want && $I[$h] === $want);
}
$t('adres dukkanin alan adinda', discover_email_on_site('boutiquex.fr', 'boutiquex.fr') && discover_email_on_site('mail.boutiquex.fr', 'boutiquex.fr'));
$t('benzer ama BASKA alan adi eslesmez (alt dize degil)', !discover_email_on_site('otherboutiquex.fr', 'boutiquex.fr') && !discover_email_on_site('boutiquex.fr.evil.com', 'boutiquex.fr'));

echo "\n== 3. discover_resolve_one(): karar, sahte ag ile ==\n";
$scrapeCalls = 0;
$S = function (string $ret) use (&$scrapeCalls) { return function () use ($ret, &$scrapeCalls) { $scrapeCalls++; return $ret; }; };
$up = fn() => true; $down = fn() => false;
$c = ['name' => 'Boutique X', 'website' => 'https://boutiquex.fr', 'site' => 'boutiquex.fr'];
$r = discover_resolve_one(['name' => 'Deichmann Schuhe', 'website' => 'https://x.de', 'site' => 'x.de'], $S('a@b.de'), $up);
$t('zincir adi -> KURAL 1 (ad / site)', $r['why'] === 'KURAL 1 (ad / site)');
$r = discover_resolve_one(['name' => 'HugeDomains', 'website' => 'https://hd.com', 'site' => 'hd.com'], $S(''), $up);
$t('park alan adi -> PARK', $r['why'] === 'PARK ALAN ADI');
$scrapeCalls = 0;
$r = discover_resolve_one($c, $S('info@boutiquex.fr'), $down);
$t('site acilmadi -> kendi kovasi, kaziyici HIC cagrilmaz', str_starts_with($r['why'], 'SITE ACILMADI') && $scrapeCalls === 0);
$r = discover_resolve_one($c, $S(''), $up);
$t('site acik, adres yok -> SITEDE ADRES YOK', $r['why'] === 'SITEDE ADRES YOK' && $r['email'] === 'yok');
$r = discover_resolve_one($c, $S('support@tawk.to'), $up);
$t('widget adresi -> SERVIS SAGLAYICI (KURAL 1h)', str_starts_with($r['why'], 'SERVIS SAGLAYICI'));
$r = discover_resolve_one($c, $S('service@deichmann.com'), $up);
$t('zincirin alan adindaki adres -> KURAL 1 (adres)', $r['why'] === 'KURAL 1 (adres)');
$r = discover_resolve_one($c, $S('boutiquex.paris@gmail.com'), $up);
$t('gmail -> VAR, ortak', $r['email'] === 'VAR' && $r['kind'] === 'ortak (gmail vb.)' && $r['why'] === '');
$r = discover_resolve_one($c, $S('Contact@BoutiqueX.fr'), $up);
$t('kendi alan adi (harf duyarsiz)', $r['kind'] === 'kendi alan adi');
$r = discover_resolve_one($c, $S('hello@agence-web.com'), $up);
$t('baska alan adi -> BASKA ALAN ADI (elle dogrulanacak)', $r['kind'] === 'BASKA ALAN ADI');
$t('sonucta adresin kendisi YOK', !str_contains(json_encode($r), '@') && array_keys($r) === ['why', 'email', 'kind']);

echo "\n== 4. discover_resolve.php CLI, yerel php -S dukkanlari, uctan uca ==\n";
$ports = [];
$srv = [];
foreach (['a' => '<html><body><a href="mailto:boutique.anna.paris@gmail.com">Contact</a></body></html>',
          'b' => '<html><body>Horaires: 10h-19h. Aucune adresse ici.</body></html>'] as $k => $html) {
    $port = 0;
    for ($try = 0; $try < 20 && !$port; $try++) {
        $p = random_int(20000, 60000);
        $s = @stream_socket_server("tcp://127.0.0.1:$p");
        if ($s) { fclose($s); $port = $p; }
    }
    mkdir("$sand/www_$k", 0777, true);
    file_put_contents("$sand/www_$k/router.php", '<?php header("Content-Type: text/html"); echo '.var_export($html, true).';');
    $srv[$k] = proc_open(['php', '-S', "127.0.0.1:$port", "$sand/www_$k/router.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp);
    $ports[$k] = $port;
}
$dead = 0;
for ($try = 0; $try < 20 && !$dead; $try++) { $p = random_int(20000, 60000); $s = @stream_socket_server("tcp://127.0.0.1:$p"); if ($s) { fclose($s); $dead = $p; } }
foreach ($ports as $port) {
    for ($w = 0; $w < 50; $w++) { $fp = @fsockopen('127.0.0.1', $port, $en, $es, 0.2); if ($fp) { fclose($fp); break; } usleep(100000); }
}
$cands = [
    ['name' => 'Boutique A', 'website' => "http://127.0.0.1:{$ports['a']}/", 'site' => "127.0.0.1:{$ports['a']}"],
    ['name' => 'Boutique B', 'website' => "http://127.0.0.1:{$ports['b']}/", 'site' => "127.0.0.1:{$ports['b']}"],
    ['name' => 'Boutique Morte', 'website' => "http://127.0.0.1:{$dead}/", 'site' => "127.0.0.1:{$dead}"],
    ['name' => 'Deichmann', 'website' => 'https://deichmann.example', 'site' => 'deichmann.example'],
];
file_put_contents("$sand/cands.json", json_encode($cands));
$errs = '';
foreach ([0, 1] as $w) {
    [$rc, $o, $e] = $run(['php', $root.'/scripts/discover_resolve.php', "$sand/cands.json", "$sand/res_$w.json", (string)$w, '2', '120'], '', $env);
    $errs .= $o.$e;
    $t("isci $w/2 kostu", $rc === 0 && is_file("$sand/res_$w.json"));
}
foreach ($srv as $h) { proc_terminate($h); proc_close($h); }
$res = array_merge(json_decode((string)@file_get_contents("$sand/res_0.json"), true) ?: [], json_decode((string)@file_get_contents("$sand/res_1.json"), true) ?: []);
$by = []; foreach ($res as $r) $by[$r['i']] = $r;
ksort($by);
$t('iki isci adaylari BOLUSTU (her aday tam bir kez)', array_keys($by) === [0, 1, 2, 3] && count($res) === 4);
$t('A: sitede gmail adresi -> VAR / ortak', ($by[0]['email'] ?? '') === 'VAR' && ($by[0]['kind'] ?? '') === 'ortak (gmail vb.)');
$t('B: site acik, adres yok', ($by[1]['why'] ?? '') === 'SITEDE ADRES YOK');
$t('olu port -> SITE ACILMADI', str_starts_with($by[2]['why'] ?? '', 'SITE ACILMADI'));
$t('zincir ada ag yok -> KURAL 1', ($by[3]['why'] ?? '') === 'KURAL 1 (ad / site)');
$all = (string)@file_get_contents("$sand/res_0.json").(string)@file_get_contents("$sand/res_1.json").$errs;
$t('ADRES HICBIR CIKTIDA YOK (dosya + stdout + stderr)', !str_contains($all, '@') && !str_contains($all, 'boutique.anna'));

echo "\n== 5. discover_known.php (sunucu tarafi), kum havuzu HOME ==\n";
mkdir("$sand/home/public_html/data", 0777, true);
symlink($root.'/vestra/inc', "$sand/home/public_html/inc");
file_put_contents("$sand/home/public_html/data/leads.json", json_encode([
    ['company' => 'Shop One', 'name' => 'Jean Dupont', 'website' => 'https://www.Shop-One.fr/contact', 'email' => 'jean.dupont@shop-one.fr'],
    ['company' => 'Gmail Shop', 'website' => '', 'email' => 'gmailshop.owner@gmail.com'],
    ['company' => 'Orange Shop', 'email' => 'orangeshop@orange.fr'],
    ['company' => 'Free Page Shop', 'website' => 'http://two.free.fr', 'email' => ''],
]));
file_put_contents("$sand/home/public_html/data/accounts.json", json_encode([
    'u1' => ['company' => 'BuyerCo', 'name' => 'Hans Muster', 'email' => 'hans.muster@buyerco.de', 'phone' => '+49 1'],
]));
$before = md5_file("$sand/home/public_html/data/leads.json").md5_file("$sand/home/public_html/data/accounts.json");
$kenv = $env; $kenv['HOME'] = "$sand/home";
[$rc, $o, $e] = $run(['php'], (string)file_get_contents($root.'/scripts/discover_known.php'), $kenv);
$lines = array_values(array_filter(array_map('trim', explode("\n", $o))));
sort($lines);
$t('kostu', $rc === 0);
$t('yalniz host + @alanadi', $lines === ['@buyerco.de', '@shop-one.fr', 'two.free.fr', 'www.shop-one.fr']);
$t('serbest posta saglayicisi BASILMADI (@gmail / @orange)', !str_contains($o, 'gmail') && !str_contains($o, 'orange'));
$t('kisi / firma / yerel kisim YOK', !preg_match('/dupont|hans|muster|shop one|buyerco |jean|[a-z0-9]@/i', $o));
$t('salt okunur', md5_file("$sand/home/public_html/data/leads.json").md5_file("$sand/home/public_html/data/accounts.json") === $before);

echo "\n== 6. discover_report.py ==\n";
$rc_ = []; $rs = [];
$mk = function (string $n, string $cat, string $site, bool $m = true) { return ['name' => $n, 'website' => "https://$site", 'site' => $site,
    'city' => 'Lyon', 'postcode' => '', 'cc' => 'FR', 'country' => 'France', 'category' => $cat, 'confidence' => 0.9,
    'overture_id' => '', 'branches' => 1, 'overture_email' => false, 'site_matches_name' => $m]; };
for ($i = 0; $i < 45; $i++) { $rc_[] = $mk("Shoe $i", 'shoe_store', "shoe$i.fr"); $rs[] = ['i' => $i, 'why' => '', 'email' => 'VAR', 'kind' => 'kendi alan adi']; }
$rc_[] = $mk('Bag One', 'handbag_store', 'bagone.fr');           $rs[] = ['i' => 45, 'why' => '', 'email' => 'VAR', 'kind' => 'ortak (gmail vb.)'];
$rc_[] = $mk('Brand Dealer', 'shoe_store', 'bigbrand.com', false); $rs[] = ['i' => 46, 'why' => '', 'email' => 'VAR', 'kind' => 'kendi alan adi'];
$rc_[] = $mk('Agency Mail', 'shoe_store', 'agencymail.fr');      $rs[] = ['i' => 47, 'why' => '', 'email' => 'VAR', 'kind' => 'BASKA ALAN ADI'];
$rc_[] = $mk('Dead Site', 'shoe_store', 'deadsite.fr');          $rs[] = ['i' => 48, 'why' => 'SITE ACILMADI (olu alan adi ya da bot engeli)', 'email' => 'yok', 'kind' => ''];
$rc_[] = $mk('Chain Named', 'shoe_store', 'chainnamed.fr');      $rs[] = ['i' => 49, 'why' => 'KURAL 1 (ad / site)', 'email' => 'yok', 'kind' => ''];
$rc_[] = $mk('Leaky', 'shoe_store', 'leaky.fr');                 $rs[] = ['i' => 50, 'why' => '', 'email' => 'leak.test@leaky.fr', 'kind' => ''];
$rc_[] = $mk('Not Measured', 'shoe_store', 'notmeasured.fr');    // sonuc yok -> OLCULMEDI
$rc_[] = array_merge($mk('Scarpe Roma', 'shoe_store', 'scarperoma.it'), ['cc' => 'IT', 'country' => 'Italy']);
$rs[] = ['i' => 52, 'why' => '', 'email' => 'VAR', 'kind' => 'kendi alan adi'];
$rc_[] = array_merge($mk('Sapatos Porto', 'shoe_store', 'sapatosporto.pt'), ['cc' => 'PT', 'country' => 'Portugal']);
$rs[] = ['i' => 53, 'why' => '', 'email' => 'VAR', 'kind' => 'kendi alan adi'];
file_put_contents("$sand/rc.json", json_encode($rc_));
file_put_contents("$sand/rd.json", json_encode([['name' => 'X', 'cc' => 'FR', 'why' => 'ZINCIR (Overture marka eslesmesi: X)'], ['name' => 'Y', 'cc' => 'DE', 'why' => 'SITESIZ (yok)'],
    ['name' => 'J1', 'cc' => 'FR', 'category' => 'jewelry_store', 'why' => 'hedef disi dal (jewelry_store)'],
    ['name' => 'J2', 'cc' => 'FR', 'category' => 'jewelry_store', 'why' => 'hedef disi dal (jewelry_store)'],
    ['name' => 'O1', 'cc' => 'FR', 'category' => 'orthopedic_shoe_store', 'why' => 'hedef disi dal (orthopedic_shoe_store)']]));
file_put_contents("$sand/rr_0.json", json_encode(array_slice($rs, 0, 30)));
file_put_contents("$sand/rr_1.json", json_encode(array_slice($rs, 30)));
[$rc, $o, $e] = $run(['python3', $root.'/scripts/discover_report.py', "$sand/rc.json", "$sand/rd.json", "$sand/rr_0.json", "$sand/rr_1.json"]);
$t('kostu', $rc === 0);
if ($rc !== 0) echo $e;
$parts = [];
preg_match_all('/^add-and-send \(country=([^,]+), letter=(\w+).*?\n((?:  parti \d+: .*\n)+)/m', $o, $mm, PREG_SET_ORDER);
$fw = []; $ds = []; $maxLinks = 0;
foreach ($mm as $g) {
    preg_match_all('/^  parti \d+: (.*)$/m', $g[3], $pl);
    if ($g[1] !== 'France') continue;   // IT / PT hukuk satiri testinin adaylari asagida ayrica
    foreach ($pl[1] as $line) { $l = explode(' ', trim($line)); $maxLinks = max($maxLinks, count($l)); if ($g[2] === 'footwear') $fw = array_merge($fw, $l); else $ds = array_merge($ds, $l); }
}
$t('ayakkabi dukkanlari -> letter=footwear, 45 link', count($fw) === 45 && $fw[0] === 'https://shoe0.fr');
$t('parti basina <=20 link (add-and-send 45 dk tavani)', $maxLinks === 20 && preg_match_all('/country=France, letter=footwear/', $o) === 1 && substr_count($o, '  parti 3: ') === 1);
$t('canta dukkani -> letter=designer (katalogda aksesuar YOK)', $ds === ['https://bagone.fr']);
$t('site adla uyusmayan + baska alan adi -> ELLE DOGRULA, partide DEGIL',
   str_contains($o, 'ELLE DOGRULA') && !in_array('https://bigbrand.com', $fw, true) && !in_array('https://agencymail.fr', $fw, true)
   && str_contains($o, 'bigbrand.com | site adla uyusmuyor') && str_contains($o, 'agencymail.fr | adres baska alan adinda'));
$t('olu site -> SITE ACILMADI listesinde, partide DEGIL', str_contains($o, "SITE ACILMADI (GitHub'dan") && str_contains($o, 'deadsite.fr') && !in_array('https://deadsite.fr', $fw, true));
$t('olculmeyen aday sayiliyor', str_contains($o, 'OLCULMEDI 1'));
$t('send=false uyarisi basiliyor', str_contains($o, 'ONCE send=false'));
$t('adres sonuca SIZSA BILE basilmiyor', !str_contains($o, '@') && !in_array('https://leaky.fr', $fw, true));
$t('Overture elemesi ulke basina', str_contains($o, 'Overture elemesi: hedef disi dal 3, ZINCIR 1') && str_contains($o, '===== DE (DE) ====='));
$sec = function (string $cc) use ($o) { return preg_match('/===== '.$cc.' \(.*?(?=\n===== |\nTOPLAM)/s', $o, $m) ? $m[0] : ''; };
$t('HUKUK satiri her ulkede: FR serbest (kosullu), IT izin sart, PT arastirilmadi (sessizce serbest sayilmaz)',
   str_contains($sec('FR'), 'soguk B2B e-posta: SERBEST (kosullu)') && str_contains($sec('IT'), 'soguk B2B e-posta: IZIN SART -- Codice Privacy art. 130')
   && str_contains($sec('PT'), 'soguk B2B e-posta: ARASTIRILMADI') && str_contains($sec('DE'), 'soguk B2B e-posta: IZIN SART'));
$t('izin isteyen / arastirilmamis ulkenin partisinin USTUNE uyari; FR partisinde uyari yok',
   (bool)preg_match('/UYARI: IT -- soguk B2B e-posta IZIN SART .*\nadd-and-send \(country=Italy, letter=footwear/', $sec('IT'))
   && (bool)preg_match('/UYARI: PT -- soguk B2B e-posta ARASTIRILMADI .*\nadd-and-send \(country=Portugal/', $sec('PT'))
   && !str_contains($sec('FR'), 'UYARI:'));
$t('uyari partiyi SAKLAMAZ (karar operatorun): IT linki yine basiliyor', str_contains($sec('IT'), 'parti 1: https://scarperoma.it'));
$t('hedef disi dallar ALT KATEGORI basina sayiliyor (tek sayiya gomulmez)',
   str_contains($o, 'hedef disi dallar (aday yapilmadi): jewelry_store 2, orthopedic_shoe_store 1'));

echo "\n== 7. is akisi guvenligi (discover-shops.yml) ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/discover-shops.yml');
$t('push tetikleyicisi YALNIZ bu dosya + bu dal (her push\'ta kosmaz)',
   (bool)preg_match('~\n  push:\n    branches: \[claude/wizardly-planck-7ylnmk\]\n    paths: \[\.github/workflows/discover-shops\.yml\]\n~', $wf));
$t('varsayilanlar tek yerde: bos girdi (push kosusu dahil) env yedegine duser',
   str_contains($wf, "github.event.inputs.countries || 'FR,BE,NL,GB'") && str_contains($wf, "github.event.inputs.per_country || '80'")
   && !preg_match('/default: "(FR|shoes|80|0\.5)/', $wf));
$t('is tavani >= 90 dk: Overture (~20 dk) + adres olcumu (1500 sn tavan) + rapor sigar',
   preg_match('/timeout-minutes: (\d+)/', $wf, $tm) === 1 && (int)$tm[1] >= 90);
$t('izin yalniz okuma', (bool)preg_match('/^permissions:\s*\n\s+contents:\s*read\s*$/m', $wf) && !preg_match('/contents:\s*write/', $wf));
$runs = [];
preg_match_all('/^\s+run: \|\n((?:\s{10,}.*\n|\s*\n)+)/m', $wf, $rm2);
$runText = implode("\n", $rm2[1]);
$t('run bloklari bulundu', count($rm2[1]) >= 4);
$t('girdiler run icine GOMULMEZ (${{ }} yalniz env ile)', !str_contains($runText, '${{'));
$t('kayitli alan adlari gunluge basilmaz (dosyaya)', str_contains($runText, '> known.txt') && !preg_match('/\b(cat|head|tail|less|more)\s+known\.txt/', $runText));
$t('ssh anahtari is biter bitmez silinir', str_contains($runText, 'rm -f "$RUNNER_TEMP/dk"') && str_contains($runText, 'umask 077'));
$t('gonderim / ekleme / baska is tetikleme yok', !preg_match('/dispatches|gh workflow|gh api|vestra_send_mail|\b(curl|wget)\s+(-|https?:)/', $runText));
$t('sunucuya TEK ssh komutu ve yalniz discover_known.php', preg_match_all('/\bssh\s/', $runText) === 1 && str_contains($runText, 'php < scripts/discover_known.php > known.txt'));
$scripts = '';
foreach (['discover_overture.py', 'discover_resolve.php', 'discover_report.py', 'discover_known.php'] as $f) $scripts .= (string)file_get_contents($root.'/scripts/'.$f);
$t('betiklerde yazici / gonderici yok', !preg_match('/vestra_send_mail|vestra_write_json|vestra_save_leads|vestra_write_csv|auth_save_accounts/', $scripts));
$kn = (string)file_get_contents($root.'/scripts/discover_known.php');
$t('discover_known yazmiyor', !preg_match('/file_put_contents|fopen\([^)]*[\'"][wa]|unlink|rename\(/', $kn));

$rm($sand);
echo "\n".($bad ? "FAIL: {$bad}" : "hepsi yesil").", {$ok} ok\n";
exit($bad ? 1 : 0);
