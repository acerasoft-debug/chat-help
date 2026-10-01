<?php
/**
 * Angebot mektubu: COK MARKALI baslik + RENKSIZ ilan (1 Eki 2026,
 * Burberry + Fred Perry + DSQUARED2 kampanyasinin operator kopyasi OKUNARAK
 * bulunan iki kusur).
 *
 *   1. "Burberry & Fred Perry & DSQUARED2 ist bei uns lieferbar" -- iki-uc marka
 *      cogul ozne; fiil her dilde tekil kaliyordu.
 *   2. DSQUARED2'nin kayitta renk alani olmayan ilanlari mektupta bos bir
 *      "Farben (0):" satiri basiyor ve foto seridinde HIC gorunmuyordu --
 *      "gorsellerle, estetik" bir kampanyada fotosuz iki model.
 *
 * Iki yon: tek markali mektup BIREBIR eskisi kalir; renkli ilan kapak karesiyle
 * ikinci kez gosterilmez.
 */
require_once __DIR__ . '/../vestra/inc/email_templates.php';
$yml = file_get_contents(__DIR__ . '/../.github/workflows/send-outreach.yml');

$pass = 0; $fail = 0;
$t = function (string $what, bool $ok) use (&$pass, &$fail) {
    if ($ok) { $pass++; } else { $fail++; echo "  KIRMIZI: {$what}\n"; }
};

$col = fn(string $id, string $brand, string $name, array $cols, int $moq = 20) => [
    'p' => ['id' => $id, 'name' => $name, 'brand' => $brand, 'moq' => $moq],
    'tag' => strtoupper($id),
    'pairs' => array_map(fn($c) => ['colour' => $c, 'img' => 'https://vestrasales.com/u/' . $id . '-' . strtolower($c) . '.jpg'], $cols),
    'rungs' => [['min' => $moq, 'price' => 59.9]],
];
$bur = $col('bur-1', 'Burberry', 'Burberry Polo', ['Green', 'Black', 'Navy']);
$fp  = $col('fp-1', 'Fred Perry', 'Fred Perry Shirt', ['White', 'Navy']);
$dsq = ['p' => ['id' => 'dsq-9', 'name' => 'Clean Wash Jeans', 'brand' => 'DSQUARED2', 'moq' => 10],
        'tag' => 'S74LB0658', 'pairs' => [], 'rungs' => [['min' => 10, 'price' => 127.5]],
        'cover' => 'https://vestrasales.com/uploads/dsq-9.png'];
$dsqNoCover = $dsq; unset($dsqNoCover['cover']);

/* ── 1. FIIL UYUMU — her dilde cogul, tek markada tekil ─────────────────── */
$PLURAL = [
    'en' => ['are in stock', ' is in stock'],
    'de' => ['sind bei uns lieferbar', ' ist bei uns'],
    'fr' => ['sont disponibles chez nous', ' est disponible chez nous'],
    'it' => ['sono disponibili da noi', ' è disponibile da noi'],
    'es' => ['están disponibles en nuestro stock', ' está disponible en nuestro stock'],
    'pt' => ['estão disponíveis connosco', ' está disponível connosco'],
    'nl' => ['zijn bij ons leverbaar', ' is bij ons leverbaar'],
];
foreach ($PLURAL as $lg => [$plur, $sing]) {
    [, $b3] = vestra_tpl_listing_offer($lg, 'ACME', [$bur, $fp, $dsq], true);
    [, $b1] = vestra_tpl_listing_offer($lg, 'ACME', [$bur], true);
    $t("{$lg}: uc markada cogul fiil", str_contains($b3, $plur));
    $t("{$lg}: uc markada tekil fiil YOK", !str_contains($b3, $sing));
    $t("{$lg}: tek markada tekil fiil KALIR", str_contains($b1, $sing) || ($lg === 'en' && str_contains($b1, 'is in stock')));
    $t("{$lg}: tek markada cogul fiil YOK", !str_contains($b1, $plur));
}
/* Lead surumu (ol) ayni uyumu tasimali. */
[, $lead3] = vestra_tpl_listing_offer('de', 'ACME', [$bur, $fp], false, '', true);
$t('lead (de, iki marka): sind … jetzt ab Lager', str_contains($lead3, 'sind Burberry & Fred Perry jetzt ab Lager'));
[, $lead1] = vestra_tpl_listing_offer('de', 'ACME', [$bur], false, '', true);
$t('lead (de, tek marka): ist Burberry jetzt ab Lager KALIR', str_contains($lead1, 'ist Burberry jetzt ab Lager'));
[, $leadFr] = vestra_tpl_listing_offer('fr', 'ACME', [$bur, $fp], false, '', true);
$t('lead (fr, iki marka): sont désormais disponibles', str_contains($leadFr, 'sont désormais disponibles du stock'));
/* Desteklenmeyen dil Ingilizceye dusuyor: fiil de Ingilizce cogul olmali. */
[, $ru] = vestra_tpl_listing_offer('ru', 'ACME', [$bur, $fp], true);
$t('desteklenmeyen dil (ru): Ingilizce yedek, cogul', str_contains($ru, 'are in stock'));
/* 4+ marka = "VESTRA" (tek ozne) -> fiile dokunulmaz. */
$mk = fn(string $b) => $col('x-' . $b, $b, $b . ' item', ['Black']);
[, $b4] = vestra_tpl_listing_offer('de', 'ACME', [$mk('A1'), $mk('B2'), $mk('C3'), $mk('D4')], true);
$t('dort marka: VESTRA ist bei uns lieferbar (tekil)', str_contains($b4, 'VESTRA ist bei uns lieferbar'));

/* ── 2. RENKSIZ ILAN ─────────────────────────────────────────────────────── */
[$s, $body, $opts] = vestra_tpl_listing_offer('de', 'ACME', [$bur, $fp, $dsq], true);
$t('"Farben (0)" satiri BASILMIYOR', !str_contains($body, 'Farben (0)'));
$t('renkli ilanlarin renk satiri KALIYOR (Burberry 3)', str_contains($body, 'Farben (3): Green, Black, Navy'));
$t('renkli ilanlarin renk satiri KALIYOR (Fred Perry 2)', str_contains($body, 'Farben (2): White, Navy'));
$t('renksiz ilanin adi ve baglantisi mektupta', str_contains($body, "Clean Wash Jeans\nhttps://vestrasales.com/product?id=dsq-9\n"));
$t('renksiz ilanin Mindestabnahme/Preis satiri mektupta', str_contains($body, 'Mindestabnahme: 10 Stück') && str_contains($body, '127,50'));
$shots = (array)$opts['shots'];
$imgs = array_column($shots, 'img');
$t('seride renksiz ilanin KAPAK karesi VAR', in_array('https://vestrasales.com/uploads/dsq-9.png', $imgs, true));
$t('kapak karesi SKU etiketiyle', in_array('S74LB0658', array_column($shots, 'label'), true));
$t('kapak karesi ilanin kendi sayfasina bagli', in_array('https://vestrasales.com/product?id=dsq-9', array_column($shots, 'url'), true));
$t('seri = 3 + 2 renk + 1 kapak = 6', count($shots) === 6);
$t('konudaki renk sayisi yalniz GERCEK renkleri sayar (5)', str_contains($s, '3 Modelle, 5 Farben'));
$t('"Alle N Farben" basligi 5', ($opts['shots_title'] ?? '') === 'Alle 5 Farben');
$rows = (array)$opts['rows'];
$dsqRow = null; foreach ($rows as $r) if (($r['label'] ?? '') === 'Clean Wash Jeans') $dsqRow = $r;
$t('ozet satirinda bas ayirac yok', $dsqRow !== null && !str_starts_with((string)$dsqRow['value'], ' ·') && !str_starts_with((string)$dsqRow['value'], '·'));
/* Kapagi OLMAYAN renksiz ilan eski davranisi koruyor (cagiran kapak vermiyor). */
[, $legacy, $lopts] = vestra_tpl_listing_offer('de', 'ACME', [$bur, $dsqNoCover], true);
$t('kapaksiz renksiz ilan: seride yeni kare YOK', count((array)$lopts['shots']) === 3);
/* Renkli ilana yanlislikla kapak verilirse renk karelerinin yerine GECMEZ. */
$burCover = $bur; $burCover['cover'] = 'https://vestrasales.com/uploads/ignored.png';
[, , $copts] = vestra_tpl_listing_offer('de', 'ACME', [$burCover], true);
$t('renkli ilanda kapak yok sayilir (yalniz 3 renk karesi)', count((array)$copts['shots']) === 3
    && !in_array('https://vestrasales.com/uploads/ignored.png', array_column((array)$copts['shots'], 'img'), true));

/* ── 3. IS AKISI KABLOLAMASI — gercek blok sentetik ilanla kosturuluyor ──── */
if (!preg_match('/(\$cover = \'\';\s*\n\s*if \(!\$sh\[\'pairs\'\].*?\$cover = \$abs\(\$im0\);\s*\n\s*\})/s', $yml, $m)) {
    echo "HATA: is akisinda kapak blogu bulunamadi\n"; exit(1);
}
$coverBlock = $m[1];
$cEval = function (array $prod, array $sh) use ($coverBlock): string {
    $pid = (string)($prod['id'] ?? 'x');
    $abs = fn(string $u) => (str_starts_with($u, 'http') ? $u : 'https://vestrasales.com' . $u);
    $code = str_replace(['fwrite(STDERR, ', 'exit(1);'], ['$__err = (', 'throw new RuntimeException("durdu");'], $coverBlock);
    try { return (string)eval($code . ' return $cover;'); }
    catch (RuntimeException $e) { return '<<DURDU>>'; }
};
$t('is akisi: renksiz ilan -> kapak mutlak adrese cevrilir',
    $cEval(['id' => 'd1', 'images' => ['/uploads/d1.png', '/uploads/d1b.png']], ['pairs' => []]) === 'https://vestrasales.com/uploads/d1.png');
$t('is akisi: renksiz ve FOTOSUZ ilan isi DURDURUR',
    $cEval(['id' => 'd2', 'images' => []], ['pairs' => []]) === '<<DURDU>>');
$t('is akisi: renkli ilan (pairs dolu) -> kapak BOS',
    $cEval(['id' => 'b1', 'colors' => ['Black'], 'images' => ['/u/b1.png']], ['pairs' => [['colour' => 'Black', 'img' => 'x']]]) === '');
$t('is akisi: renk listesi VAR ama pairs bos -> kapak BOS (renk fotografi eksikligi baska kapida durur)',
    $cEval(['id' => 'b2', 'colors' => ['Black', 'Red'], 'images' => ['/u/b2.png']], ['pairs' => []]) === '');
$t('is akisi: blok kapagi $blocks[] kaydina geciriyor', str_contains($yml, "'tag'=>\$tag, 'stock'=>\$stk, 'cover'=>\$cover]"));
$t('is akisi: kapak, gorsel yoksa durmadan ONCE kontrol edilir (STDERR + exit)',
    (bool)preg_match('/\$im0 === \'\'\) \{ fwrite\(STDERR[^\n]*exit\(1\); \}/', $yml));

echo "\noffer_multibrand_test: {$pass} iddia gecti" . ($fail ? ", {$fail} KIRMIZI" : '') . "\n";
exit($fail ? 1 : 0);
