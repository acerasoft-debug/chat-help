<?php
/** ChatHelp — fiyat bölgeleri teşhisi (sadece okur). Sil sonra. */
header('Content-Type: text/plain; charset=UTF-8');
$src = file_get_contents(__DIR__ . '/index.php');

echo "=== Fiyat izleri ===\n";
foreach (['2,99€','9,99€','79€','Monatsabo','Jahresabo','13,99','29,99','99,99','Formular Basis','price_1Tf71j','price_1TgyGd'] as $k) {
    echo "  '$k' : " . (substr_count($src, $k)) . " kez\n";
}

function dump($src, $needle, $len) {
    $p = strpos($src, $needle);
    echo "\n----- $needle (" . ($p === false ? 'YOK' : "@$p") . ") -----\n";
    if ($p !== false) echo substr($src, $p, $len) . "\n";
}
dump($src, 'function showProModal', 800);
dump($src, 'const TIER_CONFIG', 450);
dump($src, '<div class="pm-grid">', 600);
echo "\n=== son ===\nBu çıktının TAMAMINI yapıştır.\n";
