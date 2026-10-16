<?php
/**
 * ChatHelp ops — cleanup-tools: web'den erişilebilir tek-seferlik yama/teşhis betiklerini bul ve karantinaya al
 * =========================================================================================================
 * chat/ altında apply-*.php, fix-*.php, diag-*.php, dump-*.php, open-signup.php, mail-api.php, mail-final.php,
 * opcache-reset.php, *-test.php gibi dosyalar web'den çağrılabilir ve DB/dosya değiştirir → yayında kalmamalı.
 * ÖNİZLEME (varsayılan): adayları listeler (ad, boyut, tarih, web'den 200 dönüyor mu).
 * UYGULA: apply=1 → dosyalar $HOME/_ch_quarantine/<ts>/ altına TAŞINIR (web dışı). Taşınamazsa chmod 000.
 *   Geri alma: mv $HOME/_ch_quarantine/<ts>/<ad> html/chat/
 * Hiçbir uygulama dosyasına (auth.php, db.php, index.php, api.php, *-api.php, geo.php, …) dokunmaz.
 */
header('Content-Type: text/plain; charset=UTF-8');
$CH = __DIR__; $apply = isset($_GET['apply']);
echo "ChatHelp — cleanup-tools  (".date('c').")   MOD: ".($apply ? 'UYGULA' : 'ÖNİZLEME')."\n\n";

$pat = ['apply-*.php','fix-*.php','diag-*.php','dump-*.php','open-signup.php','mail-api.php','mail-final.php','opcache-reset.php','*-test.php','test-*.php','patch-*.php','debug-*.php','_fx_*.php','_opc_*.php'];
$keep = ['fix-geo.php' => false]; // hepsi aday; koruma listesi gerekirse buraya
$files = [];
foreach ($pat as $p) foreach (glob("$CH/$p") ?: [] as $f) $files[$f] = true;
$files = array_keys($files); sort($files);
if (!$files) { echo "chat/ altında yama/teşhis betiği yok ✓\n"; exit; }

echo "=== adaylar (".count($files).") ===\n";
foreach ($files as $f) {
    $b = basename($f);
    $ch = curl_init("https://chat-help.com/chat/$b"); curl_setopt_array($ch, [CURLOPT_NOBODY=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_FOLLOWLOCATION=>false]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    echo sprintf("  %-40s %7d B  %s  web:%s\n", $b, filesize($f), date('Y-m-d H:i', filemtime($f)), $code ?: '?');
}
if (!$apply) { echo "\nÖNİZLEME: taşınmadı.\n"; exit; }

$home = getenv('HOME') ?: dirname(dirname($CH));
$q = rtrim($home, '/')."/_ch_quarantine/".date('Ymd-His');
$ok = @mkdir($q, 0700, true) || is_dir($q);
echo "\n=== karantina: $q ".($ok ? '(hazır)' : '(OLUŞTURULAMADI → chmod 000 uygulanır)')." ===\n";
foreach ($files as $f) {
    $b = basename($f);
    if ($ok && @rename($f, "$q/$b")) { echo "  taşındı: $b\n"; continue; }
    if (@chmod($f, 0000)) echo "  chmod 000: $b (taşınamadı)\n"; else echo "  !! başarısız: $b\n";
}
echo "\nGeri alma: mv $q/<ad> $CH/\n";
