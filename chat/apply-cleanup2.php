<?php
/**
 * ChatHelp — apply-cleanup2 — eski oturumlardan artakalan apply-* betikleri
 *  Bu 9 dosya, pull-updates'in otomatik silme ozelliginden ONCEKI donemden
 *  kalma tek-seferlik yamalar; icerdikleri degisiklikler coktan index'te.
 *  Hepsi git'te durur (gerekirse geri indirilebilir). Yanlislikla URL ile
 *  tekrar calistirilma riskini kaldirmak icin sunucudan silinir.
 * KULLANIM: pull-updates.php?files=apply-cleanup2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D = __DIR__;
echo "apply-cleanup2 BASLADI OK\n\n";
$list = ['apply-france-catdocs.php','apply-france-expand.php','apply-france-fix.php',
         'apply-frdebug.php','apply-geo-multi.php','apply-payment-freeze-fix.php',
         'apply-reset-fix.php','apply-turkiye-backend.php','apply-turkiye.php'];
$n=0;
foreach ($list as $f) {
    $p="$D/$f";
    if (!file_exists($p)) { echo "  (zaten yok) $f\n"; continue; }
    echo (@unlink($p) ? "  🗑 silindi: $f\n" : "  ✗ silinemedi: $f\n");
    $n++;
}
echo "\n✓ Bitti ($n dosya). Site dosyalarina ve yedeklere dokunulmadi.\n";
