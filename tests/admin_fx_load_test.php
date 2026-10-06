<?php
/* FATURA SEKMESI, YUKLENMEMIS KUR DOSYASI YUZUNDEN ORTASINDA OLUYORDU
 * (operator, 7 Eyl 2026: "fatura kesemyorum bu siparise button yok duzelt ve
 * fatura kes" — VES-0A2571C2 / GARAGE LE PARIS).
 *
 * Canli hata gunlugu, siparisin geldigi dakikalarda dort kere ayni satiri
 * yaziyordu:
 *
 *   [07-Sep-2026 19:33:25 UTC] PHP Fatal error: Uncaught Error:
 *   Call to undefined function vestra_order_fx() in .../admin.php:4319
 *
 * Sebep: inc/fx_orders.php admin.php'nin bas kisminda DEGIL, yalnizca
 * (a) fx_backfill POST'unda, (b) orders_usd indirmesinde, (c) SIPARISLER
 * sekmesinde yukleniyordu. FATURALAR sekmesi ayni fonksiyonu cagiriyor ama
 * dosyayi hic yuklemiyor. Tek kurtaran yol vestra_order_invoice_payloads()
 * icindeki require idi ve o da SADECE siparise farkli bir fatura para birimi
 * secilmisse calisiyor:
 *
 *   if ($wantCur !== '' && $wantCur !== $orderCur) { require_once fx_orders; }
 *
 * Yani USD'ye cevrilmis siparis (VES-6B53D265) sayfayi ayakta tutuyor, sade
 * EUR siparis (VES-0A2571C2) oldurüyordu. Olen sayfa "Approve & issue"
 * dugmesinden BIR FORM once kesiliyor -- dugme "yok" degildi, HTML hic
 * gonderilmemisti. Kapilar (escrow notu, kesilmis mi, dilimler) acikti; sorun
 * kapida degil, ciziciydi. Bu ayrim kayda geciyor: bir dugme gorunmuyorsa
 * once o sayfanin SONUNA kadar cizilip cizilmedigi olculur.
 *
 * Cozum: fx_orders.php admin.php'nin onsozune giriyor. Dosya yuklenirken ag
 * kullanmiyor, sadece iki require + iki sabit + fonksiyon tanimliyor; bu da
 * asagida test ediliyor, yoksa onsoze konan bir dosya her panel acilisinda
 * ECB'ye gidebilirdi.
 */
$root = __DIR__ . '/../vestra';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$adm = (string)@file_get_contents($root . '/admin.php');

/* admin.php'nin ONSOZU: dosyanin basindaki, girintisiz require satirlari.
   Liste buraya elle yazilmaz -- kaynaktan okunur, yoksa admin.php degisince
   test eski listeyi sinar ve yesil kalir. */
$prelude = [];
/* Yorumlar ve dizeler tokenla ayiklanir: kaynak yorumuna gore satir eleyen bir
   tarayici, onsoze KONAN dosyanin ustune bir yorum blogu yazildigi anda o
   dosyayi gormez olur ve test yesil kalir. Bu tam olarak bir kere oldu. */
$toks = @token_get_all($adm);
$depth = 0; $stopped = false; $pending = null;
foreach ($toks as $x) {
    if ($x === '{') { $depth++; continue; }
    if ($x === '}') { $depth--; continue; }
    if (!is_array($x)) { continue; }
    if ($depth !== 0) continue;
    if (in_array($x[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) continue;
    /* Ilk kosul/dongu/fonksiyon: kosulsuz onsoz burada biter. */
    if (in_array($x[0], [T_IF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_FUNCTION, T_TRY], true)) { $stopped = true; break; }
    if (in_array($x[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) { $pending = true; continue; }
    if ($pending && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
        $v = trim($x[1], "'\"");
        if (str_starts_with($v, '/inc/')) $prelude[] = $v;
        $pending = null;
    }
}

echo "== 1. Onsoz kaynaktan okundu ==\n";
$t('onsozde en az 10 dosya var',        count($prelude) >= 10);
$t('tarama kosulsuz bolumun sonunda durdu', $stopped);
$t('inc/invoice.php onsozde',           in_array('/inc/invoice.php', $prelude, true));
$t('inc/orders.php onsozde',            in_array('/inc/orders.php', $prelude, true));

echo "\n== 2. Onsoz yuklendikten sonra kur fonksiyonlari TANIMLI (canli fatal buydu) ==\n";
/* Ayri surecte olculur: bu test dosyasi baska bir testin yukledigi
   fx_orders.php'yi miras alirsa olcum yalan soyler. */
$php  = PHP_BINARY ?: 'php';
$args = implode(' ', array_map(fn($f) => escapeshellarg($root . $f), $prelude));
$probe = escapeshellarg($root);
$cmd = escapeshellcmd($php) . ' -r ' . escapeshellarg(
    'foreach (array_slice($argv,1) as $f) { @require_once $f; }'
  . 'echo function_exists("vestra_order_fx")?"FX_YES":"FX_NO"; echo "|";'
  . 'echo function_exists("vestra_order_fx_note")?"NOTE_YES":"NOTE_NO"; echo "|";'
  . 'echo function_exists("vestra_order_usd")?"USD_YES":"USD_NO";'
) . ' -- ' . $args . ' 2>/dev/null';
$out = (string)@shell_exec($cmd);
$t('vestra_order_fx onsozden sonra tanimli',      str_contains($out, 'FX_YES'));
$t('vestra_order_fx_note onsozden sonra tanimli', str_contains($out, 'NOTE_YES'));
$t('vestra_order_usd onsozden sonra tanimli',     str_contains($out, 'USD_YES'));
$t('olcum gercekten calisti (bos cikti degil)',   $out !== '');

echo "\n== 3. FATURALAR sekmesi kur fonksiyonu cagiriyor ve kendi require'i yok ==\n";
/* Sekme sinirlari kaynaktan bulunur. Bu blok, fatal'in dustugu yer. */
$lines = explode("\n", $adm);
$tabStart = null; $tabEnd = null;
foreach ($lines as $i => $line) {
    if (preg_match("#^elseif\(\\\$tab==='([a-z_]+)'\)#", trim($line), $m)) {
        if ($m[1] === 'invoices') $tabStart = $i;
        elseif ($tabStart !== null && $tabEnd === null) { $tabEnd = $i; break; }
    }
}
$t('faturalar sekmesi bulundu',  $tabStart !== null && $tabEnd !== null && $tabEnd > $tabStart);
$tab = ($tabStart !== null && $tabEnd !== null) ? implode("\n", array_slice($lines, $tabStart, $tabEnd - $tabStart)) : '';
$t('sekme kur fonksiyonu cagiriyor',        str_contains($tab, 'vestra_order_fx('));
$t('sekme kur notunu da cagiriyor',         str_contains($tab, 'vestra_order_fx_note('));
$t('sekme fx_orders.php yuklemiyor',        !str_contains($tab, "fx_orders.php'"));
/* Yukaridaki ikisi bir arada: sekme dosyayi yuklemedigi icin ONSOZ yuklemek
   ZORUNDA. 2. bolum bunu olcuyor; burasi sartin hala gecerli oldugunu tutar. */

echo "\n== 4. Fatal, dugmeyi HTML'den siliyordu (sira kayda geciyor) ==\n";
$fxPos  = strpos($tab, 'vestra_order_fx(');
$btnPos = strpos($tab, "value=\"issue_invoice\"");
$t('issue_invoice dugmesi sekmede var',   $btnPos !== false);
$t('kur cagrisi dugmeden ONCE geliyor',   $fxPos !== false && $btnPos !== false && $fxPos < $btnPos);

echo "\n== 5. fx_orders.php onsoze konulabilir: yuklenirken is yapmiyor ==\n";
$fxSrc = (string)@file_get_contents($root . '/inc/fx_orders.php');
$t('kaynak okundu', $fxSrc !== '');
$toks = @token_get_all($fxSrc);
$depth = 0; $topLevelWork = [];
foreach ($toks as $x) {
    if ($x === '{') { $depth++; continue; }
    if ($x === '}') { $depth--; continue; }
    if (!is_array($x) || $depth !== 0) continue;
    if (in_array($x[0], [T_ECHO, T_PRINT, T_EXIT, T_EVAL], true)) $topLevelWork[] = $x[1];
}
$t('en ust seviyede echo/exit/eval yok', $topLevelWork === []);
$t('ag cagrisi yukleme aninda degil (fonksiyon icinde)',
   !preg_match('#^\s*(?:\$\w+\s*=\s*)?(?:curl_init|file_get_contents)\s*\(#m', preg_replace('#function\s+\w+.*#s', '', $fxSrc)));
$t('kendi bagimliliklari zaten onsozde',
   in_array('/inc/money.php', $prelude, true) && in_array('/inc/products.php', $prelude, true));

echo "\n== 6. Kur dosyasini yukleyen DIGER yollar duruyor (geri alma degil, ekleme) ==\n";
$t('siparisler sekmesindeki require duruyor', substr_count($adm, "/inc/fx_orders.php'") >= 3);
$t('fatura tarafindaki kosullu require duruyor',
   str_contains((string)@file_get_contents($root . '/inc/invoice.php'), "fx_orders.php'"));

echo "\n--- $ok gecti, $fail kaldi ---\n";
exit($fail ? 1 : 0);
