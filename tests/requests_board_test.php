<?php
/* TALEP PANOSU ("Anfragen") — ORNEKLER ile GERCEK talepler ayri kalmali.
 *
 * Pano iki liste basiyor: requests.csv'den gelen GERCEK alici talepleri ve
 * kod icindeki $exampleReqs. Ornekler bilerek var (bos bir pano, arz yoklugu
 * gibi okunuyor) ama uc sarti var ve ucu de dosyanin kendi yorumunda yazili:
 *   1. her kartta "Example" rozeti,
 *   2. sayilara GIRMEZ (acik talep / teklif sayaci),
 *   3. "Make an offer" dugmesi YOK -- teklif veren satici firmasini,
 *      e-postasini, birim fiyatini ve teslim sartlarini request_offers.csv'ye
 *      yaziyor; uydurma bir alicida bunlarin ulasacagi hicbir adres yok.
 *
 * Dorduncusu bu testle geliyor: ornegin KATEGORISI vestra_all_cats() sozlugunde
 * GERCEKTEN olmali. Karta `t($x['cat'])` ile basiliyor; sozlukte olmayan bir ad
 * cevrilemez, ham dizge olarak cikar ve pano "kategori" diye var olmayan bir sey
 * gosterir -- KURAL 9'un "stokta olmayana sayfa acma" mantiginin pano hali.
 */
require __DIR__.'/../vestra/inc/products.php';

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = (string)@file_get_contents(__DIR__.'/../vestra/requests.php');

/* Ornek dizisini kaynaktan cikarip degerlendiriyoruz: sayfayi calistirmak
   oturum/baslik istiyor, oysa olculecek sey verinin kendisi. */
$t('requests.php okundu', $src !== '');
preg_match('/\$exampleReqs\s*=\s*\[(.*?)\n\];/s', $src, $m);
$t('$exampleReqs bulundu', !empty($m[1]));
$body = $m[1] ?? '';

preg_match_all("/'cat'\s*=>\s*'([^']+)'/", $body, $mc);
$cats = $mc[1] ?? [];
$t('ornek sayisi arttI (>=15)', count($cats) >= 15);

/* 1) KATEGORILER GERCEK olmali. */
$valid = [];
foreach (vestra_all_cats() as $group => $subs) { $valid[] = $group; foreach ($subs as $s) $valid[] = $s; }
$bad = array_values(array_diff(array_unique($cats), $valid));
$t('her kategori sozlukte var'.($bad ? ' — YOK: '.implode(', ', $bad) : ''), $bad === []);

/* 2) Her ornegin dolu alanlari olmali -- eksik alan karta bos kutu basar. */
preg_match_all('/\[\s*\'title\'.*?\]\s*,/s', $body, $mb);
$blocks = $mb[0] ?? [];
$t('her ornek bir blok', count($blocks) === count($cats));
$missing = 0;
foreach ($blocks as $b) {
    foreach (["'title'", "'cat'", "'qty'", "'target'", "'country'", "'notes'"] as $k) {
        if (!str_contains($b, $k)) { $missing++; break; }
    }
}
$t('hicbir ornekte eksik alan yok', $missing === 0);
/* Ulke kodu iki harf: karta "📍 <kod>" olarak basiliyor. */
preg_match_all("/'country'\s*=>\s*'([^']*)'/", $body, $mo);
$t('ulke kodlari iki harf', $mo[1] && count(array_filter($mo[1], fn($c) => (bool)preg_match('/^[A-Z]{2}$/', $c))) === count($mo[1]));

/* 3) ORNEKLER SAYILARA GIRMEZ. */
$t('acik talep sayaci yalniz gercek taleplerden', str_contains($src, '$openCount=count($userReqs);'));
$t('teklif sayaci yalniz gercek tekliflerden',    str_contains($src, "\$offerCount=array_sum(array_map('count',\$offersByRef));"));
$t('ornekler requests.csv\'ye YAZILMIYOR',        !preg_match('/exampleReqs.*(fputcsv|file_put_contents)/s', $src));

/* 4) Her ornek kartinda ROZET, hicbirinde TEKLIF dugmesi yok. */
preg_match('/foreach\(\$exampleReqs as \$x\).*?endforeach;/s', $src, $mr);
$card = $mr[0] ?? '';
$t('ornek kart blogu bulundu', $card !== '');
$t('kartta "Example" rozeti',  str_contains($card, "t('Example')"));
$t('kartta teklif dugmesi YOK', !preg_match('/Make an offer|request-offer\.php/i', $card));
/* Bolum basligi okuyucuya bunlarin canli alici OLMADIGINI soylemeli. */
$t('bolum basligi ornek oldugunu yaziyor',
   str_contains($src, "These are illustrations, not live buyers"));

/* 5) Gercek talepler ORNEKLERIN USTUNDE render ediliyor. */
$posU = strpos($src, 'foreach($userReqs as $r)');
$posE = strpos($src, 'foreach($exampleReqs as $x)');
$t('gercek talepler once basiliyor', $posU !== false && $posE !== false && $posU < $posE);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
