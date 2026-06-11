<?php
/**
 * ChatHelp — OPcache teşhis + sıfırlama
 * -------------------------------------
 * "Eski versiyon görünüyor" sorununun gerçek sebebi PHP OPcache olabilir
 * (validate_timestamps=0 → değişen dosyalar yeniden derlenmez). Bu script
 * OPcache durumunu raporlar ve sıfırlar. Sıfırlayınca index.php'deki tüm
 * değişiklikler anında görünür hale gelir.
 *
 * KULLANIM: html/chat/ -> chat-help.com/chat/opcache-reset.php
 * Her index.php güncellemesinden sonra bir kez aç. İşin bitince SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

echo "=== ChatHelp OPcache Teşhis + Sıfırlama ===\n";
echo "Tarih: " . date('Y-m-d H:i:s') . "\n\n";

echo "[Durum]\n";
if (function_exists('opcache_get_configuration')) {
    $cfg = opcache_get_configuration();
    $d = $cfg['directives'] ?? [];
    echo "  opcache.enable              : " . var_export($d['opcache.enable'] ?? null, true) . "\n";
    echo "  opcache.validate_timestamps : " . var_export($d['opcache.validate_timestamps'] ?? null, true) . "  <-- 0 ise SUÇLU bu!\n";
    echo "  opcache.revalidate_freq     : " . var_export($d['opcache.revalidate_freq'] ?? null, true) . "\n";
    if (function_exists('opcache_get_status')) {
        $st = @opcache_get_status(false);
        if (is_array($st)) {
            echo "  cache durumu                : " . (($st['opcache_enabled'] ?? false) ? 'AÇIK' : 'KAPALI') . "\n";
            echo "  önbellekteki script sayısı  : " . ($st['opcache_statistics']['num_cached_scripts'] ?? '?') . "\n";
        }
    }
} else {
    echo "  OPcache yapılandırması okunamıyor (OPcache kapalı olabilir).\n";
}

echo "\n[Sıfırlama]\n";
$did = false;
if (function_exists('opcache_reset')) {
    $r = @opcache_reset();
    echo "  opcache_reset(): " . ($r ? "OK ✅" : "false (yine de invalidate denenecek)") . "\n";
    $did = true;
}
if (function_exists('opcache_invalidate')) {
    foreach (['index.php', 'form-engine.js'] as $f) {
        $p = __DIR__ . '/' . $f;
        if (file_exists($p)) { @opcache_invalidate($p, true); echo "  invalidate: $f\n"; }
    }
    $did = true;
}
clearstatcache(true);
echo "  realpath/stat cache temizlendi\n";

echo "\n" . ($did
    ? "DURUM: OPcache sıfırlandı. ✅\n"
    : "DURUM: opcache_reset/invalidate yok — OPcache kapalı, sorun başka olabilir.\n");

echo "\nŞİMDİ:\n";
echo "  1) GİZLİ pencerede aç:  https://chat-help.com/chat/\n";
echo "  2) Ctrl+Shift+R ile yenile.\n";
echo "  3) Değişiklikler (Konto kartları, staatliche, vb.) GÖRÜNÜYOR mu?\n\n";
echo "Bana söyle: yukarıda validate_timestamps kaç çıktı, ve değişiklikler göründü mü?\n";
echo "Görünüyorsa SUÇLU OPcache'ti — her güncellemeden sonra bu dosyayı bir kez açman yeter.\n";
echo "(İş bitince sil:  rm opcache-reset.php)\n";
