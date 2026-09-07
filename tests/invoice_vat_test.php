<?php
/* KDV FIYATIN ICINDE (operatör, 7 Eyl 2026: "yüzde 21 vat ücreti fiyatin icinde
 * olsun. Faturayi bu sekilde yap").
 *
 * Fiyatlar BRUT: odenecek tutar degismiyor. Ama bir KDV faturasi MATRAHI, ORANI
 * ve KDV TUTARINI ayri ayri gostermek zorunda -- yalnizca brut yazan bir belgeyle
 * alicinin muhasebesi indirim yapamaz, saticinin beyani da dayanaksiz kalir.
 *
 * Tutulanlar:
 *   - toplam DEGISMIYOR (KDV eklenmiyor, icinden ayrisiyor),
 *   - matrah + KDV = toplam, KURUSU KURUSUNA (ayri ayri yuvarlamak kaydirir),
 *   - oran yoksa belgede KDV satiri HIC yok -- mevcut KDV'siz faturalara
 *     sessizce vergi eklenmiyor,
 *   - kargo da matraha dahil (brut toplam uzerinden ayrisiyor).
 */
require __DIR__.'/../vestra/inc/pdf.php';
function vestra_product_label(string $b, string $n): string { return trim($b.' '.$n); }
function vestra_tax_id_hint(string $c): array { return ['label'=>'VAT ID','placeholder'=>'','short'=>'VAT']; }
$src   = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);
preg_match_all('/^function \w+\(.*?^}/ms', $src, $fns);
foreach ($fns[0] as $f) eval($strip($f));

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$seller = ['id'=>'tyrex','company'=>'TYREX INTERNATIONAL BV.','invoice_name'=>'TYREX INTERNATIONAL BV.',
           'country'=>'Netherlands','address'=>'Rotterdam','bank_holder'=>'TYREX',
           'bank_iban'=>'NL02ABNA0123456789','bank_eur_bic'=>'ABNANL2A','vat_id'=>'NL853943576B01'];
$buyer  = ['company'=>'Stock&chic','name'=>'Marianne HECQUET','email'=>'b@e.fr',
           'country'=>'France','address'=>'Paris','vat'=>'','reg'=>''];
/* Uc Burberry hoodie teklifi: 3 x 10 adet x 100 EUR + 20 EUR kargo = 3.020 brut. */
$items = [];
foreach (['8045006','80450158045005','80450048045013'] as $sku) {
    $items[] = ['sku'=>$sku,'brand'=>'Burberry','name'=>'Burberry Hoodie','colors'=>[],
                'qty'=>10,'unit'=>100.00,'line'=>1000.00];
}
$mk = fn(array $extra) => vestra_render_invoice_pdf(
    array_merge(['ref'=>'O795BA','date'=>'2026-09-07T10:00:00+00:00','buyer'=>$buyer], $extra),
    $items, $seller, 'INV-2026-1100', false);

echo "== 1. Aritmetik ==\n";
/* 3.020,00 / 1,21 = 2.495,867... -> 2.495,87 ; KDV = 3.020,00 - 2.495,87 = 524,13 */
$gross = 3020.00; $net = round($gross / 1.21, 2); $vat = round($gross - $net, 2);
$t('matrah 2.495,87',        abs($net - 2495.87) < 0.005);
$t('KDV 524,13',             abs($vat - 524.13) < 0.005);
$t('matrah + KDV = brut',    abs(($net + $vat) - $gross) < 0.0001);

echo "\n== 2. Belgede ==\n";
$pdf = $mk(['shipping'=>20.00,'vat_rate'=>21.0,'vat_included'=>true,
            'vat_note'=>'VAT 21% included — Netherlands domestic supply']);
$t('PDF uretildi',            str_starts_with($pdf, '%PDF'));
$t('kargo satiri',            str_contains($pdf, '(Shipping)') || str_contains($pdf, 'Shipping'));
$t('brut toplam basili',      str_contains($pdf, '3,020.00'));
$t('matrah basili',           str_contains($pdf, '2,495.87'));
$t('KDV tutari basili',       str_contains($pdf, '524.13'));
$t('oran yazili',             str_contains($pdf, 'VAT 21%'));
$t('"dahil" oldugu yazili',   str_contains($pdf, 'included in the total above'));
$t('matrah etiketi',          str_contains($pdf, 'Taxable amount'));

echo "\n== 3. Oran yoksa KDV satiri HIC yok ==\n";
/* Mevcut butun faturalar KDV'siz kesildi; davranis degismemeli. */
$plain = $mk(['shipping'=>20.00]);
$t('matrah satiri yok',       !str_contains($plain, 'Taxable amount'));
$t('"dahil" ibaresi yok',     !str_contains($plain, 'included in the total above'));
$t('toplam yine 3.020,00',    str_contains($plain, '3,020.00'));

echo "\n== 4. Oran var ama 'dahil' isaretlenmemisse basmaz ==\n";
/* vat_included, oranin FIYATIN ICINDE oldugunu soyleyen isaret; onsuz oran tek
   basina belgeye ne matrah ne de tutar yazdirmali. */
$half = $mk(['shipping'=>20.00,'vat_rate'=>21.0]);
$t('isaretsiz oran basmaz',   !str_contains($half, 'Taxable amount'));

echo "\n== 5. Kargosuz da ayrisir ==\n";
$noship = $mk(['vat_rate'=>21.0,'vat_included'=>true]);
$t('3.000,00 brut',           str_contains($noship, '3,000.00'));
/* 3.000 / 1,21 = 2.479,3388 -> 2.479,34 ; KDV = 520,66 */
$t('matrah 2.479,34',         str_contains($noship, '2,479.34'));
$t('KDV 520,66',              str_contains($noship, '520.66'));

echo "\n== 6. Yuk kurucular orani tasiyor ==\n";
foreach (['inc/offers.php', 'inc/invoice.php'] as $f) {
    $c = (string)@file_get_contents(__DIR__.'/../vestra/'.$f);
    $t("{$f} vat_rate tasiyor", str_contains($c, "'vat_rate'"));
}
$adm = (string)@file_get_contents(__DIR__.'/../vestra/admin.php');
$t('panelde oran alani var',   str_contains($adm, "name=\"vat_rate\""));
$t('panel oranı kaydediyor',   str_contains($adm, "invoice_vat_rate"));
/* Yazim hatasiyla girilen "210" belgeyi sacmalatir. */
$t('oran %100 ile sinirli',    str_contains($adm, 'min(100.0'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
