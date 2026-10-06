<?php
/* ACIK TEKLIF HATIRLATMASI (operatör, 6 Eyl 2026: "bunlari müsteriye gönder
 * ya kabul ya teklif yada red, kücük hatirlatma yap").
 *
 * Mektup TEK kalemde de COKLU kalemde de ayni: 4 Eyl 2026'da ayni aliciya ayni
 * Burberry hoodie'nin UC ayri teklifi acik kaldi (O795BA / OED4CC / O7A484) ve
 * kalem basina bir mektup, tek bir hatirlatmayi ayni kutuya dusen uc e-postaya
 * bolerdi.
 *
 * Tutulanlar:
 *   - UC yol da yazili: kabul / KARSI TEKLIF / red. Karsi teklif eskiden metinde
 *     HIC gecmiyordu -- musteri bizim fiyatimizi kabul etmekle bitirmek arasinda
 *     sikisiyordu, oysa KURAL 4'e gore turu duruyor.
 *   - Kabul baglantisi KALEM BASINA: token teklifin kendisine ait.
 *   - Uzlasilmis kalem YOKSA "gerisi hazir/faturalanmaya hazir" DENMEZ.
 *   - Rakamlar parametreden basilir, metne gomulmez (KURAL 6'nin ayni dersi).
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/email_templates.php');
if (!preg_match('/^function vestra_tpl_offer_nudge\(.*?^}/ms', $src, $m)) { echo "HATA: vestra_tpl_offer_nudge bulunamadi\n"; exit(1); }
eval($m[0]);

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$item = fn(array $o=[]) => array_merge([
    'ref'=>'O795BA', 'product'=>'Burberry Hoodie 8045006', 'qty'=>10,
    'ours'=>110.0, 'theirs'=>100.0,
    'url'=>'https://vestrasales.com/p/bur-8045006',
    'accept'=>'https://vestrasales.com/offer/accept?ref=O795BA&t=tok1',
], $o);

echo "== 1. Bos liste mektup uretmez ==\n";
[$s0,$b0,] = vestra_tpl_offer_nudge([], [], 'Hello');
$t('bos girdi bos mektup', $s0 === '' && $b0 === '');

echo "\n== 2. Tek kalem ==\n";
[$s1,$b1,] = vestra_tpl_offer_nudge([$item()], [], 'Hello Marianne');
$t('konu tek kalemi adiyla anar', str_contains($s1, 'One open item') && str_contains($s1, 'Burberry Hoodie 8045006'));
$t('ref govdede',                 str_contains($b1, 'Reference O795BA'));
$t('adet govdede',                str_contains($b1, '10 pcs'));
$t('onun fiyati',                 str_contains($b1, 'Your offer:  EUR 100.00'));
$t('bizim fiyatimiz',             str_contains($b1, 'Our price:   EUR 110.00'));
$t('urun linki',                  str_contains($b1, 'https://vestrasales.com/p/bur-8045006'));
$t('kabul linki',                 str_contains($b1, 'tok1'));
$t('tekil dil',                   str_contains($b1, 'One item is still open'));

echo "\n== 3. UC yol da yazili ==\n";
foreach (['Accept'=>'· Accept', 'Counter'=>'· Counter', 'Decline'=>'· Decline'] as $n2 => $needle) {
    $t("secenek: {$n2}", str_contains($b1, $needle));
}
$t('karsi teklif turu kaldigini soyler', str_contains($b1, 'rounds left'));

echo "\n== 4. Coklu kalem: TEK mektup ==\n";
$three = [
    $item(),
    $item(['ref'=>'OED4CC','product'=>'Burberry Hoodie 80450158045005','accept'=>'https://vestrasales.com/offer/accept?ref=OED4CC&t=tok2']),
    $item(['ref'=>'O7A484','product'=>'Burberry Hoodie 80450048045013','accept'=>'https://vestrasales.com/offer/accept?ref=O7A484&t=tok3']),
];
[$s3,$b3,] = vestra_tpl_offer_nudge($three, [], 'Hello Marianne');
$t('konu adedi soyler',        str_contains($s3, '3 open items'));
$t('uc ref de govdede',        str_contains($b3,'O795BA') && str_contains($b3,'OED4CC') && str_contains($b3,'O7A484'));
$t('KALEM BASINA kabul linki', str_contains($b3,'tok1') && str_contains($b3,'tok2') && str_contains($b3,'tok3'));
$t('uc OPEN blogu',            substr_count($b3, 'OPEN — ') === 3);
$t('cogul dil',                str_contains($b3, 'The 3 items below'));
$t('cogul: they go',           str_contains($b3, 'they go'));

echo "\n== 5. Uzlasilmis kalem yoksa 'gerisi hazir' DENMEZ ==\n";
$t('bos anlasmada faturalanmaya hazir yok', !str_contains($b3, 'everything else is agreed'));
$t('bos anlasmada liste basligi yok',       !str_contains($b3, 'Already agreed'));
[$s4,$b4,] = vestra_tpl_offer_nudge($three, ['20 pcs — Gros Grain Black  (OCF7F5)'], 'Hello Marianne');
$t('anlasma varsa soylenir',   str_contains($b4, 'everything else is agreed'));
$t('anlasilan kalem listelenir',str_contains($b4, 'OCF7F5'));

echo "\n== 6. Para birimi parametreden ==\n";
[, $b5, ] = vestra_tpl_offer_nudge([$item()], [], 'Hello', 'USD');
$t('USD basilir',      str_contains($b5, 'USD 100.00'));
$t('EUR gomulu degil', !str_contains($b5, 'EUR'));

echo "\n== 7. Eksik/sifir fiyat satiri hic basilmaz ==\n";
[, $b6, ] = vestra_tpl_offer_nudge([$item(['ours'=>null,'theirs'=>0.0,'url'=>'','accept'=>''])], [], 'Hello');
$t('bizim fiyat satiri yok', !str_contains($b6, 'Our price:'));
$t('onun fiyat satiri yok',  !str_contains($b6, 'Your offer:'));
$t('bos kabul linki yok',    !str_contains($b6, 'Open this item:'));
$t('panel yolu yine yazili', str_contains($b6, 'buyer?tab=offers'));

echo "\n== 8. Imza ==\n";
[, $b7, ] = vestra_tpl_offer_nudge([$item()], [], 'Hello', 'EUR', 'Elena Romano');
$t('imzaci parametreden', str_contains($b7, 'Elena Romano') && !str_contains($b7, 'Marco Bellini'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
