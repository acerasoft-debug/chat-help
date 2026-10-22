<?php
/**
 * ChatHelp — apply-restore-lastgood — ACIL KURTARMA
 *  index.php'yi denetler:
 *   • Lint HATALIYSA veya bos/asiri kucukse: en yeni SAGLAM (lint gecen)
 *     yedegi bulur ve GERI YUKLER (bozuk hal index.php.broken-* olarak saklanir).
 *   • Lint TEMIZSE: hicbir seye dokunmaz, sadece rapor verir (guvenli).
 * KULLANIM: pull-updates.php?files=apply-restore-lastgood.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
$D = __DIR__;
$idx = $D.'/index.php';
echo "apply-restore-lastgood BASLADI OK\n\n";

function lintOk(string $path): bool {
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($path).' 2>&1',$lo,$rc);
    return $rc===0;
}

$size = @filesize($idx);
$ok = @is_file($idx) && $size > 200000 && lintOk($idx);
echo "index.php: ".number_format((int)$size)." B · lint ".($ok?"TEMIZ":"SORUNLU/EKSIK")."\n";

if ($ok) {
    echo "\n✓ index.php saglikli gorunuyor — GERI YUKLEME YAPILMADI.\n";
    echo "  (Site yine acilmiyorsa sorun PHP-disi olabilir: dump-site-down.php ciktisini gonderin.)\n";
    exit;
}

echo "\n⚠ index.php sorunlu — en yeni SAGLAM yedek araniyor…\n";
$baks = glob($D.'/index.php.bak-*');
usort($baks, fn($a,$b)=>filemtime($b)-filemtime($a));
$restored = false;
foreach ($baks as $b) {
    if (filesize($b) > 200000 && lintOk($b)) {
        @copy($idx, $idx.'.broken-'.date('Ymd-His'));
        if (@copy($b, $idx)) {
            echo "  ✓ GERI YUKLENDI: ".basename($b)." (".date('m-d H:i:s',filemtime($b)).", ".number_format(filesize($b))." B)\n";
            echo "  (bozuk hal index.php.broken-* olarak saklandi)\n";
            $restored = true;
        }
        break;
    }
    echo "  – atlandi (lint/boyut): ".basename($b)."\n";
}
echo $restored ? "\n✓ Site eski haline dondu — hemen test edin.\n"
               : "\n✗✗ SAGLAM YEDEK BULUNAMADI — dump-site-down.php ciktisini gonderin.\n";
