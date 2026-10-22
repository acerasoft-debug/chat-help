<?php
/** ChatHelp — dump-genprompt2: doGenerate()'in TAMAMI + $sysLaw montaji +
 *  AI cagrisi (tek/cift asama, hangi model) + doPhoto (foto analizi) + AI helper imzalari.
 *  SADECE OKUR. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api = @file_get_contents(__DIR__ . '/api.php');
if ($api === false) exit("api.php okunamadi\n");

/* doGenerate tamamini bas (baslangictan bir sonraki 'function ' e kadar) */
$s = strpos($api, 'function doGenerate');
if ($s !== false) {
    $e = strpos($api, "\nfunction ", $s + 10);
    $body = substr($api, $s, ($e === false ? 5000 : $e - $s));
    echo "###### doGenerate() TAM ######\n" . $body . "\n\n";
} else echo "doGenerate bulunamadi\n";

/* doPhoto / analyze_photo */
$s = strpos($api, 'function doPhoto');
if ($s !== false) {
    $e = strpos($api, "\nfunction ", $s + 10);
    echo "###### doPhoto() TAM ######\n" . substr($api, $s, ($e === false ? 2500 : min($e - $s, 2500))) . "\n\n";
} else echo "###### doPhoto bulunamadi ######\n\n";

/* AI helper imzalari — hangi model, tek/cift */
echo "###### AI helper imzalari ######\n";
foreach (['function callClaude','function callDeepSeek','function callDeepSeekChat'] as $fn) {
    $p = strpos($api, $fn);
    echo "  " . ($p !== false ? substr($api, $p, 120) : "($fn yok)") . "\n";
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-genprompt2.php ===\n";
