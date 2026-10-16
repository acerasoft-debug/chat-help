<?php
/**
 * ChatHelp ops — mail-envelope: auth.php sendMail() → mail() mit Envelope-Absender (-f)
 * =====================================================================================
 * Befund: mail() aus dem Web-Kontext (PHP-FPM) wird ohne "-f" mit www-data@host als Envelope-From verschickt und
 * kommt nicht an (CLI-Tests kamen an). Fix: fünfter mail()-Parameter '-f'.$from.
 * ÖNİZLEME (varsayılan): anchor sayar. apply=1: yedek auth.php.bak-envelope-<ts>, patch, php -l, hata → geri al.
 * test_to=<mail>: nach dem Patch (oder im Preview) Testmail über die gepatchte Funktion aus CLI.
 */
header('Content-Type: text/plain; charset=UTF-8');
$apply = isset($_GET['apply']); $af = __DIR__.'/auth.php';
echo "ChatHelp — mail-envelope  (".date('c').")   MOD: ".($apply ? 'UYGULA' : 'ÖNİZLEME')."\n\n";
if (!is_file($af)) exit("auth.php yok\n");
$src = (string)file_get_contents($af); $orig = $src;
$old = "    if (@mail(\$to, '=?UTF-8?B?'.base64_encode(\$subject).'?=', \$html, \$hdr)) return;";
$new = "    if (@mail(\$to, '=?UTF-8?B?'.base64_encode(\$subject).'?=', \$html, \$hdr, '-f'.\$from)) return; /*CH_ENVELOPE*/\n    if (@mail(\$to, '=?UTF-8?B?'.base64_encode(\$subject).'?=', \$html, \$hdr)) return;";
$n = substr_count($src, $old); $done = str_contains($src, 'CH_ENVELOPE');
echo "  anchor: $n  · bereits gepatcht: ".($done ? 'ja' : 'nein')."\n";
if ($apply && $n && !$done) {
    $bak = $af.'.bak-envelope-'.date('Ymd-His'); copy($af, $bak); echo "  yedek: ".basename($bak)."\n";
    file_put_contents($af, str_replace($old, $new, $src));
    $lint = (string)shell_exec('php -l '.escapeshellarg($af).' 2>&1');
    if (stripos($lint, 'No syntax errors') === false) { file_put_contents($af, $orig); exit("  ✗ SÖZDİZİMİ HATASI → geri alındı: ".trim($lint)."\n"); }
    echo "  ✓ auth.php sendMail(): Envelope-Absender gesetzt  php -l: ".trim($lint)."\n";
} elseif (!$apply) echo "  ÖNİZLEME: auth.php değişmedi.\n";
if (!empty($_GET['test_to'])) {
    echo "\n=== Testmail über auth.php sendMail() → ".$_GET['test_to']." ===\n";
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET['action'] = '__none__';
    $out = (string)shell_exec('php -r '.escapeshellarg('$_GET=["action"=>"__none__"]; ob_start(); try { include "'.$af.'"; } catch (Throwable $e) {} ob_end_clean(); if (function_exists("sendMail")) { sendMail("'.addslashes($_GET['test_to']).'", "ChatHelp Bestätigung Test ✅ '.date('H:i').'", "<p>sendMail() aus auth.php · '.date('c').'</p>"); echo "gesendet"; } else echo "sendMail fehlt";').' 2>&1');
    echo "  ".mb_substr(trim($out), 0, 300)."\n";
}
echo "\nBİTTİ\n";
