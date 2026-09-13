<?php
/* Bölgesel kalıcı indirim — kimin hak ettiği (operatör, 13 Eyl 2026).
 *
 * Bu testin asıl işi TERS YÖN: kapsamda OLMAYAN bir ülkeye indirim vermemek.
 * Yanlış pozitif burada bir etiket hatası değil, hiç karar verilmemiş bir
 * firmaya KALICI %10 demek. Bu yüzden yakın kod/ad tuzakları tek tek yazılı.
 */
$root = dirname(__DIR__);
require_once $root.'/vestra/inc/region_discount.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$cc  = fn(string $s) => vestra_country_region_discount_cc($s);
$pct = fn(string $s) => vestra_region_discount_pct(['country' => $s]);

echo "\n== 1. Güney Amerika: 12 ülkenin hepsi ==\n";
$sa = ['Argentina'=>'AR','Bolivia'=>'BO','Brazil'=>'BR','Chile'=>'CL','Colombia'=>'CO',
       'Ecuador'=>'EC','Guyana'=>'GY','Paraguay'=>'PY','Peru'=>'PE','Suriname'=>'SR',
       'Uruguay'=>'UY','Venezuela'=>'VE'];
foreach ($sa as $name => $code) $t("{$name} -> {$code}", $cc($name) === $code);
$t('12 ülkenin hepsi listede', count(array_intersect(array_values($sa), vestra_region_discount_codes())) === 12);
/* Liste 13 Eyl 2026'da iki kez buyudu (once GA+APAC, sonra CZ+PL). Sayi
   burada yazili ki bir sonraki ekleme sessizce gecmesin. */
$t('kapsam toplam 18 ülke', count(vestra_region_discount_codes()) === 18);
$t('kod listesinde tekrar yok', count(vestra_region_discount_codes()) === count(array_unique(vestra_region_discount_codes())));

echo "\n== 2. Asya-Pasifik dördü ==\n";
foreach (['Japan'=>'JP','Australia'=>'AU','Singapore'=>'SG','Hong Kong'=>'HK'] as $n => $c) {
    $t("{$n} -> {$c}", $cc($n) === $c);
}
/* JP/AU/SG yazımları KOPYALANMADI, otomatik açılan ülke tablosundan okunuyor. */
$t('japonya (tr) -> JP',   $cc('Japonya') === 'JP');
$t('日本 (ja) -> JP',       $cc('日本') === 'JP');
$t('avustralya (tr) -> AU', $cc('Avustralya') === 'AU');
$t('シンガポール (ja) -> SG', $cc('シンガポール') === 'SG');
/* Hong Kong: bu depoda gerçek bir alıcı var (VES-6B53D265, 中国香港特别行政区). */
$t('香港 -> HK',            $cc('香港') === 'HK');
$t('中国香港特别行政区 -> HK', $cc('中国香港特别行政区') === 'HK');
$t('Hong Kong SAR China -> HK', $cc('Hong Kong SAR China') === 'HK');

echo "\n== 2b. Orta Avrupa: Çekya ve Polonya ==\n";
foreach (['Czechia'=>'CZ','Czech Republic'=>'CZ','Česko'=>'CZ','Ceska Republika'=>'CZ',
          'Tschechien'=>'CZ','Çekya'=>'CZ','чехия'=>'CZ',
          'Poland'=>'PL','Polska'=>'PL','Polen'=>'PL','Polonya'=>'PL','польша'=>'PL'] as $n => $c) {
    $t("{$n} -> {$c}", $cc($n) === $c);
}
$t("'CZ' ve 'PL' çıplak kod",   $cc('CZ') === 'CZ' && $cc('PL') === 'PL');
$t('ikisi de %10 alıyor',       $pct('Česko') === 10.0 && $pct('Poland') === 10.0);

echo "\n== 3. Yakın kod tuzakları — indirim YOK ==\n";
/* Bunların her biri gerçek bir karışma: yanlış pozitif kalıcı %10 demek. */
$t("'CH' İsviçre, 'CL' Şili DEĞİL",      $cc('CH') === '' && $cc('CL') === 'CL');
$t("'AT' Avusturya, 'AU' DEĞİL",         $cc('AT') === '' && $cc('AU') === 'AU');
$t("'SA' Suudi Arabistan kapsam DIŞI",   $cc('SA') === '' && $cc('Saudi Arabia') === '');
$t("'CN' Çin anakarası kapsam DIŞI",     $cc('CN') === '' && $cc('China') === '');
$t("'GF' Fransız Guyanası kapsam DIŞI",  $cc('GF') === '' && $cc('French Guiana') === '');
$t("'SN' Senegal, 'SG' DEĞİL",           $cc('SN') === '');
$t("'PT' Portekiz, 'PE'/'PY' DEĞİL",     $cc('PT') === '');
$t("'US' ve 'UY' ayrı",                  $cc('US') === '' && $cc('UY') === 'UY');
$t("'SE' İsveç, 'SR' DEĞİL",             $cc('SE') === '');
$t("'BE'/'BG' Brezilya DEĞİL",           $cc('BE') === '' && $cc('BG') === '');
$t('Germany indirim almaz',              $cc('Germany') === '' && $pct('Germany') === 0.0);
$t('Turkey indirim almaz',               $cc('Turkey') === '');
/* CZ/PL'nin komsulari: bu dordu de kapsam DISI ve kodlari bir harf farkli. */
$t("'SK' Slovakya kapsam DIŞI",          $cc('SK') === '' && $cc('Slovakia') === '');
$t("'SI' Slovenya kapsam DIŞI",          $cc('SI') === '' && $cc('Slovenia') === '');
$t("'PT' Portekiz 'PL' DEĞİL",           $cc('PT') === '' && $cc('Portugal') === '');
$t("'CH'/'CZ' ayrımı",                   $cc('CH') === '' && $cc('CZ') === 'CZ');
/* Slovakca 'Ceskoslovensko' tarihi bir ad; tam esleşme onu da almaz. */
$t("'Ceskoslovensko' eşleşmez",          $cc('Ceskoslovensko') === '');

echo "\n== 4. Alt dize ASLA eşleşmez ==\n";
/* mango -> Mangobay dersi: kısa bir ad başka bir adın içinde bulunmamalı. */
$t("'Chilean office' eşleşmez",          $cc('Chilean office') === '');
$t("'New Brazil Trading Ltd' eşleşmez",  $cc('New Brazil Trading Ltd') === '');
$t("'Japan Street 12' eşleşmez",         $cc('Japan Street 12') === '');
$t("'Hong Kong Road, London' eşleşmez",  $cc('Hong Kong Road, London') === '');
$t("'Peruvian' eşleşmez",                $cc('Peruvian') === '');
$t("'Poland Street' eşleşmez",           $cc('Poland Street') === '');

echo "\n== 5. Biçim toleransı ==\n";
$t('baştaki/sondaki boşluk',    $cc('  Brazil  ') === 'BR');
$t('büyük/küçük harf',          $cc('bRaZiL') === 'BR');
$t('iç boşluk normalize',       $cc('Hong   Kong') === 'HK');
$t('tire ayraç',                $cc('Hong-Kong') === 'HK');
$t('aksanlı yazım (Perú)',      $cc('Perú') === 'PE');
$t('boş değer indirim vermez',  $cc('') === '' && $pct('') === 0.0);

echo "\n== 6. Yüzde ve tutar tek kaynaktan ==\n";
$t('sabit tanımlı',              defined('VESTRA_REGION_DISCOUNT_PCT'));
$t('kapsamdaki ülke %10 alıyor',  $pct('Brazil') === 10.0 && $pct('JP') === 10.0);
$t('hesap YOKSA 0',               vestra_region_discount_pct(null) === 0.0);
$t("'country' alanı boşsa 0",     vestra_region_discount_pct(['name'=>'x']) === 0.0);
$t('1000 -> 100.00',              vestra_region_discount_amount(1000.0, 10.0) === 100.00);
$t('kuruş yuvarlama (89.90x20)',  vestra_region_discount_amount(1798.0, 10.0) === 179.80);
$t('yukarı yuvarlama (33.33)',    vestra_region_discount_amount(33.33, 10.0) === 3.33);
$t('yüzde 0 ise tutar 0',         vestra_region_discount_amount(500.0, 0.0) === 0.0);
$t('negatif/sıfır ara toplam 0',  vestra_region_discount_amount(0.0, 10.0) === 0.0);

echo "\n".($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
