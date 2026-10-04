<?php
/** ChatHelp — dump-healthcheck: canli sunucunun genel saglik kontrolu. SADECE OKUR.
 *  index.php + api.php butunlugu, tum CH_* marker'lari, script dengesi,
 *  kalinti debug bloklari, yedek yigilmasi. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__ . '/index.php');
$api = @file_get_contents(__DIR__ . '/api.php');
if ($idx === false) exit("index.php okunamadi\n");
echo "###### GENEL ######\n";
echo "index.php: " . strlen($idx) . " bayt · api.php: " . ($api === false ? 'OKUNAMADI' : strlen($api) . ' bayt') . "\n";
$o = substr_count($idx, '<script'); $c = substr_count($idx, '</script>');
echo "script dengesi: acilis=$o kapanis=$c => " . ($o === $c ? 'TEMIZ' : 'BOZUK (fark=' . ($o - $c) . ')') . "\n";
echo "php lint: ";
$tmp = tempnam(sys_get_temp_dir(), 'hc') . '.php'; file_put_contents($tmp, $idx);
$lo = []; $rc = 0; exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc); @unlink($tmp);
echo ($rc === 0 ? "OK\n" : "HATA: " . implode(' ', $lo) . "\n");

echo "\n###### INDEX MARKER'LARI ######\n";
$mIdx = ['CH_CV_STUDIO','CH_VST','CH_FIXPACK10','CH_MODPACK','CH_MODHUB','CH_MODHUB_NAV','CH_JOBS_TOCARD','CH_TOPBAR_PREMIUM','CH_AICHAT_FE','CH_VST_EXPAND','CH_VST_EXPAND2','CH_CV_PHOTOCROP','CH_CV_PHOTO_MANAGE','CH_PREMIUM_POLISH','CH_POLISH_FIX','CH_KONTO_LAW','CH_CC_GATE_LEGAL','CH_TR_VISA_MODULE','CH_VERTRAG_BLUR','CH_HIDE_PRICES','CH_AICHAT_DOCPDF','CH_VERTRAG_HOME','CH_CHAT_REFINE','CH_CLEAN_CHAT','CH_VERLAUF_FIX'];
foreach ($mIdx as $m) echo "  " . (strpos($idx, $m) !== false ? '✓' : '✗ EKSIK') . "  $m\n";

echo "\n###### API MARKER'LARI ######\n";
if ($api !== false) {
    foreach (['CH_AICHAT_BE','CH_AICHAT_INTAKE','TEMPLATE FIRST','CH_TR_LAWSYS'] as $m)
        echo "  " . (strpos($api, $m) !== false ? '✓' : '✗ EKSIK') . "  $m\n";
    echo "  eski intake kaldi mi: " . (strpos($api, 'INTAKE FIRST: when the user describes') !== false ? 'EVET (SORUN)' : 'hayir (temiz)') . "\n";
}

echo "\n###### KALINTI DEBUG BLOKLARI (hepsi 'yok' olmali) ######\n";
foreach (['ch-navdebug"','ch-navorder-debug"','ch-sbdebug"'] as $d)
    echo "  " . (strpos($idx, '<script id="' . rtrim($d,'"') . '"') !== false ? 'VAR (SORUN)' : 'yok') . "  $d\n";

echo "\n###### SUNUCU DOSYA YIGILMASI ######\n";
$baks = glob(__DIR__ . '/index.php.bak-*') ?: [];
$dumps = glob(__DIR__ . '/dump-*.php') ?: [];
$applies = glob(__DIR__ . '/apply-*.php') ?: [];
$bakSize = 0; foreach ($baks as $f) $bakSize += filesize($f);
echo "  index.php.bak-*: " . count($baks) . " dosya, toplam " . round($bakSize/1048576, 1) . " MB\n";
echo "  dump-*.php: " . count($dumps) . " dosya\n";
echo "  apply-*.php (silinmemis kalinti): " . count($applies) . " dosya" . (count($applies) ? ' -> ' . implode(', ', array_map('basename', array_slice($applies, 0, 10))) : '') . "\n";

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
