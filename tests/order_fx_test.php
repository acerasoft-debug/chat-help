<?php
/* SIPARISIN USD KARSILIGI, SIPARIS TARIHINDEKI KURLA (operator, 7 Eyl 2026:
 * "siparisleri aninda siparis zamanindaki kur ile USD'ye cevirecek bir sistem
 * koy admin paneline").
 *
 * Tutulan ilkeler:
 *   - damga SIPARIS YAZILIRKEN duser (order.php + teklif kabulu), sonra degismez
 *   - damga yoksa "—": bugunun kuruyla DOLDURULMAZ (KURAL 3'un kur hali)
 *   - hafta sonu / tatil: o gun yururlukte olan kur = onceki yayim gunu, ve
 *     damga O tarihi yazar, siparis tarihini degil
 *   - kaynak etiketi damgadan gelir; ECB olmayana "ECB" denmez
 *   - saf fonksiyonlar (lookup) agsiz test edilir; diske yazan fonksiyonlar
 *     yerel kaydi yedekleyip geri koyar
 */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/fx_orders.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);

echo "== 1. Gecmis tablosunda arama (saf) ==\n";
$hist = ['2026-09-03' => 1.1701, '2026-09-04' => 1.1690];   // Prs, Cum; Cmt/Paz yok
$t('yayim gunu dogrudan',            vestra_fx_history_lookup($hist, '2026-09-04') == ['usd' => 1.1690, 'date' => '2026-09-04']);
$t('Cumartesi -> Cuma kuru',         vestra_fx_history_lookup($hist, '2026-09-05')['date'] === '2026-09-04');
$t('Pazar -> Cuma kuru',             vestra_fx_history_lookup($hist, '2026-09-06')['date'] === '2026-09-04');
$t('damga YAYIM tarihini tasir',     vestra_fx_history_lookup($hist, '2026-09-06')['date'] !== '2026-09-06');
$t('geriye bakis siniri asilinca null', vestra_fx_history_lookup($hist, '2026-09-20') === null);
$t('bos tablo -> null',              vestra_fx_history_lookup([], '2026-09-04') === null);
$t('sifir kur yok sayilir',          vestra_fx_history_lookup(['2026-09-04' => 0], '2026-09-04') === null);
$t('meta anahtarlari kur degil',     vestra_fx_history_lookup(['_fail_ts' => 99, '_fetched_at' => 'x'], '2026-09-04') === null);

echo "\n== 2. Aralik ucu ve kaynak etiketi ==\n";
$t('frankfurter aralik URL',  vestra_fx_history_url('2026-08-01', '2026-09-07') === 'https://api.frankfurter.app/2026-08-01..2026-09-07?from=EUR&to=USD');
$t('ecb -> ECB',              vestra_fx_source_label('ecb') === 'ECB');
$t('market ECB DEGIL',        vestra_fx_source_label('market') !== 'ECB');
$t('manual ECB DEGIL',        vestra_fx_source_label('manual') !== 'ECB');

echo "\n== 3. USD hesabi ==\n";
$fx = ['usd' => 1.1690, 'date' => '2026-09-04', 'source' => 'ecb'];
$t('total x kur, 2 hane',     vestra_order_usd(['ref' => 'X', 'total' => '4680.00'], $fx) === 5470.92);
$t('virgullu toplam okunur',  vestra_order_usd(['ref' => 'X', 'total' => '10,50'], $fx) === 12.27);
$t('sifir toplam -> null',    vestra_order_usd(['ref' => 'X', 'total' => '0'], $fx) === null);
$t('bicim US$',               vestra_usd(5470.92) === 'US$5,470.92');
$t('kur notu',                vestra_order_fx_note($fx) === '@1.1690 · ECB 4 Sep 2026');

echo "\n== 4. Damga: idempotent, tahmin yok ==\n";
$file = vestra_data_dir() . '/order_statuses.json';
$bak  = is_file($file) ? file_get_contents($file) : null;
$hbak = _vsec_read(VESTRA_FX_HISTORY_FILE);
try {
    $ref = 'FXTEST-' . strtoupper(bin2hex(random_bytes(3)));
    /* Gecmiste bu tarih yok ve canli degil -> damga DUSMEZ, kayit yazilmaz. */
    _vsec_write(VESTRA_FX_HISTORY_FILE, []);
    $t('kur yokken damga yok (tahmin yok)', vestra_order_fx_stamp($ref, '2026-09-04T10:00:00+00:00') === null);
    $t('kayit da yazilmadi',                !isset(vestra_read_json('order_statuses.json')[$ref]['fx']));
    /* Gecmis gelince damga duser, hafta sonu siparisi Cuma kurunu tasir. */
    _vsec_write(VESTRA_FX_HISTORY_FILE, ['2026-09-04' => 1.1690]);
    $s1 = vestra_order_fx_stamp($ref, '2026-09-06T09:00:00+00:00');   // Pazar
    $t('gecmisten damga',                   $s1 !== null && $s1['usd'] === 1.1690 && $s1['source'] === 'ecb');
    $t('Pazar siparisi Cuma tarihini tasir', ($s1['date'] ?? '') === '2026-09-04');
    $t('okuma damgayi verir',               (vestra_order_fx($ref)['usd'] ?? 0) === 1.1690);
    /* Ikinci damga, farkli kurla bile, ilkine DOKUNMAZ. */
    _vsec_write(VESTRA_FX_HISTORY_FILE, ['2026-09-04' => 9.9]);
    $s2 = vestra_order_fx_stamp($ref, '2026-09-06T09:00:00+00:00');
    $t('idempotent: ikinci damga ilkini ezmez', $s2['usd'] === 1.1690);
    /* Toplu geri doldurma, ag KAPALI: damgasizi sayar, cekmez. */
    $r = vestra_orders_fx_backfill([['ref' => $ref, 'timestamp' => '2026-09-06T09:00:00+00:00'],
                                    ['ref' => $ref . '-B', 'timestamp' => '2030-01-01T00:00:00+00:00']], false);
    $t('geri doldurma: 1 damgasiz, 0 damgalandi, cekilmedi', $r['missing'] === 1 && $r['stamped'] === 0 && $r['fetched'] === false && $r['still_missing'] === 1);
    $t('harita damgaliyi verir', isset(vestra_orders_fx_map([['ref' => $ref]])[$ref]));
} finally {
    if ($bak === null) @unlink($file); else file_put_contents($file, $bak);
    _vsec_write(VESTRA_FX_HISTORY_FILE, is_array($hbak) ? $hbak : []);
}

echo "\n== 5. Iki yazma yolu da damgaliyor ==\n";
/* Siparis satirini iki yer yaziyor. Birini unutmak, o yoldan dogan siparisin
   sonsuza kadar "—" kalmasi demek. */
$t('order.php damgaliyor (canli kur)',         str_contains($src('order.php'), "vestra_order_fx_stamp(\$ref, date('c'), true)"));
$t('teklif kabulu damgaliyor (canli kur)',     str_contains($src('inc/offers.php'), "vestra_order_fx_stamp(\$ref, date('c'), true)"));
$t('damga satir DISKE yazildiktan sonra',      strpos($src('order.php'), 'vestra_order_fx_stamp') > strpos($src('order.php'), 'fclose($fh);'));

echo "\n== 6. Admin: liste, dosya, toplam, CSV, geri doldurma ==\n";
$adm = $src('admin.php');
$t('sekme acilinca geri doldurma denenir',     str_contains($adm, 'vestra_orders_fx_backfill($orders, true)'));
$t('listede USD satiri',                       str_contains($adm, "vestra_order_usd(\$o,\$__fx)"));
$t('damgasiz satir "US$ —" (tahmin yok)',      str_contains($adm, "'US$ —'"));
$t('dosyada "rate on order date"',             str_contains($adm, 'In USD (rate on order date)'));
$t('USD toplam karti',                         str_contains($adm, 'Total volume in USD'));
$t('CSV: usd_rate/usd_rate_date/source/total', str_contains($adm, "['usd_rate','usd_rate_date','usd_rate_source','total_usd']"));
$t('geri doldurma dugmesi + isleyici',         str_contains($adm, "value=\"fx_backfill\"") && str_contains($adm, "if(\$act==='fx_backfill')"));
/* Admin'in kendi hesabi yok: her deger fx_orders.php'den. Ikinci bir carpim
   kopyasi, kur degisince ekranlarin ayrisacagi gun demek. */
$t('admin kendi basina total*kur carpmiyor',   !preg_match('~\$o\[.total.\]\s*\*\s*\$~', $adm));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
