<?php
/** ChatHelp — dump-aichat-prompt (SADECE OKUR)
 *  Canli api.php'deki doAiChat sistem-promptunu VE [[DOC]] isleme mantigini
 *  TAM olarak gosterir (yerel apply-*.php dosyalarindan tahmin degil,
 *  sunucudaki GERCEK kod). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api = @file_get_contents(__DIR__.'/api.php');
if ($api===false) exit("api.php okunamadi\n");

echo "════════ doAiChat TESHIS ════════\n\n";

/* ── doAiChat fonksiyonunun tamamini bul ── */
$p = strpos($api, 'function doAiChat');
if ($p===false) { echo "doAiChat fonksiyonu bulunamadi!\n"; exit; }
/* fonksiyon govdesini kaba parantez sayaciyla cikar */
$brace = strpos($api, '{', $p);
$depth = 0; $i = $brace; $len = strlen($api);
for (; $i < $len; $i++) {
    if ($api[$i] === '{') $depth++;
    elseif ($api[$i] === '}') { $depth--; if ($depth === 0) { $i++; break; } }
}
$fn = substr($api, $p, $i - $p);
echo "###### doAiChat TAM FONKSIYON (".strlen($fn)." bayt) ######\n";
echo $fn."\n\n";

echo "###### CH_ MARKER ENVANTERI (bu fonksiyon icinde) ######\n";
preg_match_all('/CH_[A-Z0-9_]+/', $fn, $mm);
echo implode(", ", array_unique($mm[0]))."\n\n";

echo "════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-aichat-prompt.php ════════\n";
