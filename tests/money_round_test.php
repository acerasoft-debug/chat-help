<?php
/* Cevrilmis fiyatin ONDALIK ADIMI -- operator, 17 Eyl 2026:
 *   "usd de tum katalog fiyatlarini kuurat varsa duzlestir ornek 34,56 - 34,60"
 *
 * Katalogun EUR tarafi hep duz (39,00 / 85,00 / 120,00) cunku rakamlari operator
 * yaziyor. Cevrilmis taraf degildi: kur carpani 34,56 / 45,3258 / 65,4027 gibi
 * rastgele kuruslar uretiyordu.
 *
 * IDDIALAR OLGUYA BAGLI, yazima degil: aritmetigin kendisi, hangi yolun
 * yuvarlayiciyi CAGIRDIGI, ve -- bu depoda tekrar tekrar bedeli odenmis olan --
 * hangi yolun cagirMAMASI gerektigi (fatura). Tek yon yazilsaydi "her yeri
 * yuvarlayan" bir kusur da yesil kalirdi.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__);
require_once $root.'/vestra/inc/money.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root.'/'.$f);

echo "== 1. Aritmetik: 10 kurusa YUKARI ==\n";
$t('sabit 0.10',                         abs(VESTRA_MONEY_STEP - 0.10) < 1e-9);
/* Operatorun kendi ornegi: bu iddia sabitin adina degil VERDIGI ORNEGE bagli. */
$t('34,56 -> 34,60 (operatorun ornegi)', abs(vestra_money_round(34.56, 'USD') - 34.60) < 1e-9);
$t('34,51 -> 34,60',                     abs(vestra_money_round(34.51, 'USD') - 34.60) < 1e-9);
$t('34,60 -> 34,60 (zaten duz, ITILMEZ)',abs(vestra_money_round(34.60, 'USD') - 34.60) < 1e-9);
$t('34,50 -> 34,50 (zaten duz)',         abs(vestra_money_round(34.50, 'USD') - 34.50) < 1e-9);
$t('45,3258 -> 45,40',                   abs(vestra_money_round(45.3258, 'USD') - 45.40) < 1e-9);
$t('0 -> 0',                             abs(vestra_money_round(0.0, 'USD')) < 1e-9);
$t('AUD da yuvarlaniyor',                abs(vestra_money_round(65.4027, 'AUD') - 65.50) < 1e-9);
$t('CAD da yuvarlaniyor',                abs(vestra_money_round(63.2411, 'CAD') - 63.30) < 1e-9);
/* EUR KATALOGUN KENDI BIRIMI: bir kurus bile oynatmak ilan edilen fiyati
   degistirirdi. */
$t('EUR hic dokunulmuyor',               vestra_money_round(34.56, 'EUR') === 34.56);

echo "\n== 2. YON: asla ASAGI yuvarlamaz, hep 10 kurustan az ekler ==\n";
/* Mekanizma iddiasi: en yakina yuvarlayan bir kusur (34,54 -> 34,50) burada
   kirmizi doner -- ilan edilen EUR fiyatin ALTINDA bir rakam satmak olurdu. */
$down = 0; $far = 0; $notFlat = 0; $n = 0;
foreach ([1.1622, 1.1592, 1.6161, 1.5810, 1.6064, 0.9973] as $rate) {
    for ($c = 100; $c <= 30000; $c += 7) {
        $raw = ($c / 100) * $rate;
        $r   = vestra_money_round($raw, 'USD');
        $n++;
        if ($r < $raw - 1e-9)               $down++;
        if ($r - $raw >= 0.10 - 1e-9)       $far++;
        if (abs($r * 100 - round($r * 100)) > 1e-6
            || (int)round($r * 100) % 10 !== 0) $notFlat++;
    }
}
$t("asagi yuvarlanan 0 ({$n} cevrim)", $down === 0);
$t('eklenen fark her zaman < 10 kurus', $far === 0);
$t('sonuc her zaman 10 kurusun kati',   $notFlat === 0);
$t('olcum gercekten kostu (n > 20000)', $n > 20000);

echo "\n== 3. vestra_money(): tek yuvarlayicidan geciyor ==\n";
/* Kum havuzu: inc/ kopyalanip YANINA data/fx_rates.json tohumlaniyor.
   _vsec_dir() money.php tek basina yuklendiginde dirname(__DIR__).'/data'
   okuyor, yani depodaki gercek veriye DOKUNULMUYOR ve ag cagrisi da olmuyor
   (taze ts). */
$sand = sys_get_temp_dir().'/vestra_moneyround_'.getmypid();
@mkdir($sand.'/data', 0777, true);
exec('cp -r '.escapeshellarg($root.'/vestra/inc').' '.escapeshellarg($sand.'/inc'));
file_put_contents($sand.'/data/fx_rates.json', json_encode([
    'ts' => time(), 'date' => '2026-09-16', 'source' => 'ecb',
    'rates' => ['USD' => 1.1622, 'AUD' => 1.6350, 'CAD' => 1.5810],
]));
$run = function (string $php, array $get = []) use ($sand): string {
    $f = tempnam(sys_get_temp_dir(), 'mr').'.php';
    file_put_contents($f, '<?php $_GET='.var_export($get, true).';$_COOKIE=[];'
        . 'require '.var_export($sand.'/inc/money.php', true).'; '.$php);
    $out = (string)shell_exec('php '.escapeshellarg($f).' 2>&1');
    @unlink($f);
    return trim($out);
};
/* 40,00 x 1,1622 = 46,488 -> yuvarlanmadan US$46.49, yuvarlanmis US$46.50. */
$t('40 EUR -> US$46.50 (46,488 degil)', $run('echo vestra_money(40.0, "USD");') === 'US$46.50');
$t('40 EUR -> A$65.40',                 $run('echo vestra_money(40.0, "AUD");') === 'A$65.40');
$t('EUR ciktisi degismedi',             $run('echo vestra_money(34.56, "EUR");') === '€34.56');
$t('kur yoksa EUR basiyor (uydurma yok)', $run('echo vestra_money(40.0, "XXX");') === '€40.00');
$t('vestra_money yuvarlayiciyi cagiriyor',
   (bool)preg_match('~number_format\(vestra_money_round\(\$eurAmount \* \$rate, \$cur\)~', $src('vestra/inc/money.php')));

echo "\n== 4. URUN SAYFASININ CANLI HESAPLAYICISI (JS) ayni adimi kullaniyor ==\n";
/* Ayni sayfada kademe tablosu US$45,40 derken canli toplamin US$45,33 demesi,
   bu deponun tekrar tekrar kaydettigi "sayfada bir, kasada baska rakam". */
$pp = $src('vestra/product.php');
$t('JS adimi SABITTEN aliyor (elle 0.10 degil)',
   str_contains($pp, 'json_encode(vestra_money_converted() ? (float)VESTRA_MONEY_STEP : 0.0)'));
$t('fmtMoney yukari yuvarliyor',  (bool)preg_match('~fmtMoney\(n\)\{[^}]*Math\.ceil~s', $pp));
$t('EUR gorende adim 0 (JS de dokunmuyor)', str_contains($pp, ': 0.0) ?>};'));

echo "\n== 5. DROPSHIP: sayfa ile Stripe ayni rakami soyluyor ==\n";
$ds = $src('vestra/inc/dropship.php');
$t('USD birim yuvarlayicidan',     str_contains($ds, "return vestra_money_round(\$unit * \$r, 'USD');"));
$t('Stripe tahsilati ayni govdeden',
   str_contains($ds, "(int) round(vestra_money_round(\$unit * \$fxRate, 'USD') * 100)"));
$t('navlun da ayni govdeden',
   str_contains($ds, "(int)round(vestra_money_round(\$zFee * \$fxRate, 'USD') * 100)"));
$t('ham (yuvarlanmamis) cevrim kalmadi',
   !preg_match('~round\(\$unit \* \$fxRate \* 100\)~', $ds)
   && !preg_match('~round\(\$zFee \* \$fxRate \* 100\)~', $ds));

echo "\n== 6. FATURA yuvarlanMAZ (KURAL 5i) ==\n";
/* TERS YON. Belgede birim x adet = satir tutmak zorunda ve cevrim siparis
   tarihinin damgasiyla, tam kurusla yapiliyor. "Guzellestirmek" belgeyi kendi
   icinde tutmaz hale getirirdi. */
$inv = $src('vestra/inc/invoice.php');
$t('invoice.php yuvarlayiciyi HIC cagirmiyor', !str_contains($inv, 'vestra_money_round'));
$t('invoice.php cevirici yerinde',             str_contains($inv, 'function vestra_invoice_convert_payload'));
$t('sepet/siparis EUR kaydi degismedi',        !str_contains($src('vestra/order.php'), 'vestra_money_round'));

echo "\n== 7. Cevrim notu yuvarlamayi SOYLUYOR, 9 dilde ==\n";
$key = 'Converted amounts are rounded up to the nearest 10 cents.';
$t('not yuvarlama cumlesini ekliyor', str_contains($src('vestra/inc/money.php'), $key));
$missing = [];
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $L) {
    if (!str_contains($src("vestra/inc/lang/$L.php"), $key)) $missing[] = $L;
}
$t('8 cevirinin hepsinde var'.($missing ? ' — eksik: '.implode(',', $missing) : ''), !$missing);
/* Not yalnizca CEVRIM YAPILIYORKEN cikiyor, o yuzden birim secilerek olculuyor:
   `?cur` vermeden olcmek EUR goren bir ziyaretciyi olcer ve not zaten bos
   doner -- yani iddia hicbir seyi tutmazdi. */
$noteUsd = $run('echo vestra_money_note();', ['cur' => 'USD']);
$t('not gercekten basiliyor',  str_contains($noteUsd, '10 cents'));
$t('not kuru da soyluyor',     str_contains($noteUsd, 'USD') && str_contains($noteUsd, 'invoiced in EUR'));
$t('EUR gorende not BOS',      $run('echo vestra_money_note();') === '');

exec('rm -rf '.escapeshellarg($sand));
printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
