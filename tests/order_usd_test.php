<?php
/* SIPARISIN YANINDA USD KARSILIGI (operatör, 7 Eyl 2026: "her siparisin yanina
 * usd ye cevir bölümü ciksin").
 *
 * Siparis EUR uzerinden kesiliyor ve havale EUR bekleniyor; USD rakami BILGI
 * AMACLI. Bu ayrim yazilmazsa alici USD tutarini odenecek tutar sanip eksik
 * havale yapar -- KURAL 6'nin (escrow tavani) ve fatura CJK'sinin ayni dersi:
 * ekranda gorunen sayi ile kasanin uyguladigi sayi ayrisirsa fark parada cikar.
 *
 * Tutulanlar:
 *   - kur YOKSA hicbir sey yazilmaz (uydurma kur, eksik satirdan pahali),
 *   - cumle TEK KAYNAKTAN cikiyor, dort ekranda da ayni,
 *   - kurun kaynagi ve tarihi yaziliyor,
 *   - "bilgi amacli, EUR faturalanir" ibaresi HER ZAMAN var.
 */
require_once __DIR__.'/../vestra/inc/money.php';

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

/* Kuru testin kendisi belirliyor: canli ECB'ye baglanan bir test, ag kesildigi
   gun kirmizi yanar ve kimse koda bakmaz. */
vestra_fx_set_manual(['USD' => 1.0757], '2026-09-07');

echo "== 1. Cevrim ==\n";
$u = vestra_usd_equiv(1000.0);
$t('cevrim uretildi',          $u !== []);
$t('tutar dogru',              ($u['amount'] ?? '') === 'US$1,075.70');
$t('kur dondu',                abs(($u['rate'] ?? 0) - 1.0757) < 1e-9);
$t('kaynak ve tarih notta',    str_contains($u['note'] ?? '', 'manual') && str_contains($u['note'] ?? '', '2026-09-07'));
$t('kur notta yazili',         str_contains($u['note'] ?? '', '1 EUR = 1.0757 USD'));
$t('binlik ayraci var',        str_contains((string)(vestra_usd_equiv(12345.0)['amount'] ?? ''), ','));

echo "\n== 2. Sifir ve negatif ==\n";
$t('sifir tutar cevrilmez',    vestra_usd_equiv(0.0) === []);
$t('negatif tutar cevrilmez',  vestra_usd_equiv(-5.0) === []);

echo "\n== 3. HTML parcasi ==\n";
$h = vestra_usd_hint_html(1000.0);
$t('yaklasik isareti',         str_contains($h, '≈'));
$t('tutar iceriyor',           str_contains($h, 'US$1,075.70'));
$t('BILGI AMACLI ibaresi',     str_contains($h, 'invoiced in EUR'));
$t('kur satiri',               str_contains($h, '1 EUR = 1.0757 USD'));
$t('sinif verilebiliyor',      str_contains(vestra_usd_hint_html(10.0, 'ahint'), 'class="ahint"'));
$t('sinif kacisliyor',         !str_contains(vestra_usd_hint_html(10.0, '"><script>'), '<script>'));

echo "\n== 4. Kur yoksa HICBIR SEY yazilmaz ==\n";
/* Uydurulmus bir kur, musterinin onune faturayla tutmayan bir rakam koyar.
   Kuru olmayan bir para birimiyle olculuyor: vestra_fx() bilinmeyen kodda 0.0
   donuyor, yani ayni koruma calisiyor. Manuel kuru silip olcmek MUMKUN DEGIL --
   vestra_fx_state() ilk cagrida sabitleniyor (vlang() ile ayni tuzak). */
$t('kuru olmayan birim: cevrim bos', vestra_usd_equiv(1000.0, 'GBP') === []);
$t('kuru olmayan birim: HTML bos',   vestra_usd_hint_html(1000.0, 'hint', 'GBP') === '');
$t('desteklenen ikinci birim calisiyor', vestra_usd_equiv(100.0, 'USD') !== []);

echo "\n== 5. Dort ekran da AYNI kaynaktan cagiriyor ==\n";
/* Ayni cumleyi dort yerde elle yazmak, birinde "bilgi amacli" ibaresinin
   unutulmasi demekti. */
foreach (['buyer.php', 'seller.php', 'admin.php', 'inc/orders.php'] as $f) {
    $src = (string)@file_get_contents(__DIR__.'/../vestra/'.$f);
    $t("{$f} yardimciyi cagiriyor", str_contains($src, 'vestra_usd_hint_html('));
    /* Cumleyi kendi kuran bir ekran, "bilgi amacli" ibaresini unutan ekrandir.
       (admin.php'de vestra_fx('USD') var ama o bir SAGLIK KONTROLU -- kur var mi
       diye bakiyor, tutar bicimlendirmiyor; olcut cumlenin kendisi olmali.) */
    $t("{$f} cumleyi kendi kurmuyor", !str_contains($src, '1 EUR = '));
}

/* Not tek yerde kuruluyor: money.php disinda hicbir dosyada olmamali. */
$moneySrc = (string)@file_get_contents(__DIR__.'/../vestra/inc/money.php');
$t('cumle money.php icinde kuruluyor', str_contains($moneySrc, '1 EUR = '));

echo "\n== 6. Ceviri anahtari 8 sozlukte de var ==\n";
foreach (['de','fr','it','es','pt','ru','ja','ar'] as $lg) {
    $d = require __DIR__.'/../vestra/inc/lang/'.$lg.'.php';
    $t("{$lg}: anahtar var", isset($d['indicative, invoiced in EUR']) && trim($d['indicative, invoiced in EUR']) !== '');
}

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
