<?php
/**
 * ChatHelp — apply-cleanup-secdumps: hassas dump dosyalarini sunucudan siler.
 *   dump-mkrev.php (inceleme hesabi sifresi icerir!) + dump-revacct.php + dump-legalinfo.php
 * KULLANIM: pull-updates.php?files=apply-cleanup-secdumps.php  -> apply otomatik silinir.
 */
header('Content-Type: text/plain; charset=UTF-8');
echo "apply-cleanup-secdumps BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$files = ['dump-mkrev.php', 'dump-revacct.php', 'dump-legalinfo.php'];
foreach ($files as $f) {
    $p = __DIR__ . '/' . $f;
    if (!file_exists($p)) { echo "  - zaten yok: $f\n"; continue; }
    echo (@unlink($p) ? "  🗑 silindi: $f\n" : "  ✗ SILINEMEDI (elle sil!): $f\n");
}

/* kontrol: hicbiri kalmamali */
$left = [];
foreach ($files as $f) if (file_exists(__DIR__.'/'.$f)) $left[] = $f;
echo $left ? "\nUYARI: hala duruyor: ".implode(', ', $left)."\n" : "\nTemiz ✓ — hassas dump dosyasi kalmadi.\n";
echo "BITTI.\n";
