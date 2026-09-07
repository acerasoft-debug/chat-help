<?php
/* ANA SAYFADAKI ILK SIPARIS INDIRIMI (operatör, 6 Eyl 2026: "Ana Sayfaya belirgin
 * sekilde ilk siparise yüzde 5 Indirim yaz").
 *
 * Vaat, kasanin gercekten uyguladigi seyle ayni olmak zorunda: hos geldin kuponu
 * yuzdesi VESTRA_WELCOME_PCT sabitinde ve kampanya da onu varsayilan aliyor.
 * Dokuz dilin metnine "5" yazilsaydi, yuzde degistigi gun ana sayfa bir sey vaat
 * edip kupon baskasini verirdi -- escrow tavaninda yasanan hatanin aynisi (KURAL 6).
 *
 * Tutulanlar:
 *   - sabit TEK KAYNAK ve kampanya onu kullaniyor,
 *   - dokuz dilin HEPSINDE iki anahtar da var (eksik olan sessizce bos basardi),
 *   - metinlerde rakam GOMULU DEGIL, %s ile geliyor,
 *   - "ucretsiz kayit olun" satiri yalnizca GIRIS YAPMAMISA (uyeye yaptigi isi
 *     tekrar soylemek),
 *   - kupon modulu yuklenemezse duyuru HIC basilmiyor (olmayan bir indirimi
 *     vaat etmektense hic yazmamak),
 *   - stil ana sayfanin KENDI CSS'inde: index.php inc/style.css yuklemiyor.
 */
$idx  = (string)@file_get_contents(__DIR__.'/../vestra/index.php');
$vou  = (string)@file_get_contents(__DIR__.'/../vestra/inc/vouchers.php');

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "== 1. Yuzde tek kaynakta ==\n";
$t('VESTRA_WELCOME_PCT tanimli',        (bool)preg_match("~define\('VESTRA_WELCOME_PCT',\s*\d+\)~", $vou));
$t('kampanya sabiti varsayilan aliyor', str_contains($vou, "\$opts['percent'] ?? VESTRA_WELCOME_PCT"));
$t('kampanyada cıplak 5 kalmadi',       !str_contains($vou, "\$opts['percent'] ?? 5"));

require_once __DIR__.'/../vestra/inc/vouchers.php';
$t('sabit okunabiliyor', defined('VESTRA_WELCOME_PCT') && (float)VESTRA_WELCOME_PCT > 0);

echo "\n== 2. Dokuz dilde iki anahtar ==\n";
$langs = ['en','fr','it','es','de','pt','ru','ja','ar'];
foreach ($langs as $lg) {
    $i = strpos($idx, "\n'{$lg}'=>[\n");
    $block = $i === false ? '' : substr($idx, $i, strpos($idx, "\n],\n", $i) - $i);
    $t("{$lg}: off_t var", str_contains($block, "'off_t'=>"));
    $t("{$lg}: off_s var", str_contains($block, "'off_s'=>"));
}

echo "\n== 3. Rakam metne GOMULU degil ==\n";
preg_match_all("~'off_t'=>\"(.*?)\",\n~", $idx, $m);
$t('dokuz off_t bulundu', count($m[1]) === 9);
$embedded = [];
foreach ($m[1] as $line) {
    if (!str_contains($line, '%s')) { $embedded[] = "yer tutucu yok: {$line}"; continue; }
    /* %% kacisi disindaki her rakam gomulu sayidir. */
    if (preg_match('~\d~', str_replace(['%s','%%'], '', $line))) $embedded[] = "rakam gomulu: {$line}";
}
$t('her off_t %s tasiyor ve rakam gomulu degil', $embedded === []);
foreach ($embedded as $e) echo "       -> {$e}\n";

echo "\n== 4. Kablolama ==\n";
$t('sabitten okunuyor',        str_contains($idx, "defined('VESTRA_WELCOME_PCT')"));
$t('sprintf ile basiliyor',    str_contains($idx, "sprintf(\$t['off_t']"));
$t('modul yoksa basilmiyor',   str_contains($idx, '$WELCOME_PCT !== null'));
$t('kayit satiri girisliye yok', (bool)preg_match('~if\(!\$LOGGED\): \?><span class="offer-s"~', $idx));
$t('giris yapana /shop, digerine kayit',
   str_contains($idx, "\$LOGGED ? '/shop' : '/register?type=buyer'"));

echo "\n== 5. Stil ana sayfanin kendi CSS'inde ==\n";
/* index.php inc/style.css YUKLEMIYOR; sinif orada tanimli degilse band ciplak cikar. */
$t('.offer kurali var',     str_contains($idx, '.offer{display:inline-flex'));
$t('.offer-tag kurali var', str_contains($idx, '.offer-tag{'));
$t('mobil kurali var',      str_contains($idx, '.offer{gap:11px'));
$t('RTL hizalamasi var',    str_contains($idx, '[dir="rtl"] .offer{text-align:right}'));

echo "\n== 6. Konum: birincil dugmelerin USTUNDE ==\n";
$po = strpos($idx, 'class="offer"');
$pb = strpos($idx, '<div class="btns">');
$t('duyuru dugmelerden once basiliyor', $po !== false && $pb !== false && $po < $pb);

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
