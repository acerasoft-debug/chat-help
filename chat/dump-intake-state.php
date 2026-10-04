<?php
/** ChatHelp — dump-intake-state: canli intake-api.php + api.php'nin
 *  halusinasyon/§§ prompt durumunu gosterir. SADECE OKUR.
 *  Amac: apply-safe-laws icin DOGRU anchor metnini almak. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);

function reg($src, $needle, $before, $len, $label) {
    echo "###### $label ######\n";
    $p = strpos($src, $needle);
    if ($p === false) { echo "  (BULUNAMADI: '$needle')\n\n"; return; }
    echo substr($src, max(0, $p - $before), $len) . "\n---\n\n";
}

$it = @file_get_contents(__DIR__ . '/intake-api.php');
if ($it === false) { echo "intake-api.php okunamadi\n"; }
else {
    echo "=== intake-api.php (" . strlen($it) . " bayt) ===\n";
    echo "CH_NOHALLUCINATE marker: " . (strpos($it,'CH_NOHALLUCINATE')!==false ? "VAR" : "YOK") . "\n";
    echo "CH_SAFE_LAWS marker: " . (strpos($it,'CH_SAFE_LAWS')!==false ? "VAR" : "YOK") . "\n";
    echo "draftSys var mi: " . (strpos($it,'draftSys')!==false ? "EVET (iki-asamali aktif)" : "HAYIR") . "\n";
    echo "eski \$sys Rechtsanwalt var mi: " . (strpos($it,'erfahrener deutscher Rechtsanwalt')!==false ? "EVET (tek-asamali eski)" : "HAYIR") . "\n\n";
    reg($it, 'draftSys', 0, 1400, "draftSys bloku (varsa)");
    reg($it, 'verifySys', 0, 1000, "verifySys bloku (varsa)");
    reg($it, 'erfahrener deutscher Rechtsanwalt', 20, 900, "eski \$sys (varsa)");
    reg($it, 'Paragraphen', 40, 260, "ilk 'Paragraphen' gecisi");
}

echo "\n\n";
$api = @file_get_contents(__DIR__ . '/api.php');
if ($api === false) { echo "api.php okunamadi\n"; }
else {
    echo "=== api.php (" . strlen($api) . " bayt) ===\n";
    echo "CH_AICHAT_NOHALL marker: " . (strpos($api,'CH_AICHAT_NOHALL')!==false ? "VAR" : "YOK") . "\n";
    echo "CH_SAFE_LAWS marker: " . (strpos($api,'CH_SAFE_LAWS')!==false ? "VAR" : "YOK") . "\n";
    echo "CH_GEN_NOHALL marker: " . (strpos($api,'CH_GEN_NOHALL')!==false ? "VAR" : "YOK") . "\n";
    echo "chat 'invent' metni var mi: " . (strpos($api,'invent no extra facts')!==false ? "SAFE (guncel)" : (strpos($api,'invent nothing')!==false ? "STRICT (eski)" : "?")) . "\n\n";
    reg($api, 'invent no extra facts', 80, 320, "chat [[DOC]] SAFE (varsa)");
    reg($api, 'invent nothing', 80, 320, "chat [[DOC]] STRICT (varsa)");
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-intake-state.php ===\n";
