<?php
/**
 * ChatHelp — Teşhis scripti (sadece okur, hiçbir şey değiştirmez)
 * Sunucudaki index.php'nin gerçek durumunu raporlar.
 * KULLANIM: html/chat/ içine yükle -> chat-help.com/chat/diagnostic.php aç
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$src = @file_get_contents(__DIR__ . '/index.php');
if ($src === false) { exit("HATA: index.php okunamadı.\n"); }

function chk($label, $bool) { echo ($bool ? "  ✓ " : "  ✗ ") . $label . "\n"; }
function dump($src, $needle, $len) {
    $p = strpos($src, $needle);
    if ($p === false) { echo "  (BULUNAMADI: $needle)\n"; return; }
    echo substr($src, $p, $len) . "\n";
}

echo "=== ChatHelp Teşhis Raporu ===\n";
echo "index.php boyutu: " . strlen($src) . " byte\n";
echo "Tarih: " . date('Y-m-d H:i:s') . "\n\n";

echo "[Fonksiyon/Tanım kontrolü]\n";
chk("function getMonth(  (eksikse staatliche çöker)", strpos($src, 'function getMonth(') !== false);
chk("function showStaatKat(", strpos($src, 'function showStaatKat(') !== false);
chk("function checkFormQuota(", strpos($src, 'function checkFormQuota(') !== false);
chk("function openStaatForm(", strpos($src, 'function openStaatForm(') !== false);
chk("STAATLICHE_FORMS verisi", strpos($src, 'STAATLICHE_FORMS') !== false);
chk("welcome onclick showStaatKat('jobcenter')", strpos($src, "showStaatKat('jobcenter')") !== false);
chk("openStaatForm artik herkese acik (window.location.href = fileUrl)", strpos($src, 'window.location.href = fileUrl;') !== false);
chk("Hedefleyici sorular: doc.q kullaniliyor", strpos($src, '(doc.q||doc.qs||[])') !== false);
chk("Estetik input stili ekli", strpos($src, 'ch-input-style') !== false);
echo "\n";

echo "[Yedek dosyalar]\n";
foreach (glob(__DIR__ . '/index.php.bak-*') as $b) { echo "  • " . basename($b) . "\n"; }
echo "\n";

echo "===== openStaatForm (gerçek kod) =====\n";
dump($src, 'function openStaatForm(', 420);
echo "\n===== checkFormQuota (gerçek kod) =====\n";
dump($src, 'function checkFormQuota(', 420);
echo "\n===== getMonth (gerçek kod) =====\n";
dump($src, 'function getMonth(', 220);
echo "\n===== showStaatKat (ilk 700 karakter) =====\n";
dump($src, 'function showStaatKat(', 700);
echo "\n=== Rapor sonu — bu çıktının TAMAMINI yapıştır ===\n";
