<?php
/**
 * ChatHelp — dump11 (SADECE OKUR): ana sayfa + fiyat + doc sonuç
 * Verträge'yi ana sayfaya (Staatliche'den sonra) koymak, 1,99/plan-bedava
 * fiyatı ve kısa avukat notu için gerçek kod.
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump11.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx = @file_get_contents(__DIR__ . '/index.php');
$api = @file_get_contents(__DIR__ . '/api.php');
if ($idx === false) exit("index.php yok\n");
function R($s,$needle,$before,$len,$lbl){
    echo "\n──── $lbl ────\n";
    if($s===false){echo "(dosya yok)\n";return;}
    $p=strpos($s,$needle);
    if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}
    echo substr($s,max(0,$p-$before),$len)."\n";
}

echo "### ANA SAYFA: Staatliche Formulare bölümü ###\n";
R($idx,'Staatliche Formulare',-300,700,'Staatliche bölüm yerleşimi (HTML)');
R($idx,'wcat-grid',-200,700,'welcome kategori ızgarası (.wcat-grid)');
R($idx,'function showStaatKat',0,500,'showStaatKat');
R($idx,'wlc-staat',-100,500,'wlc-staat (varsa)');
// Ana sayfada kategori/staatliche kartlarının çizildiği yer
R($idx,'wcat',-60,300,'wcat kart örneği');

echo "\n### FİYAT / PAYWALL ###\n";
R($idx,'function getDocPrice',0,700,'getDocPrice');
R($idx,'function showPaywall',0,1000,'showPaywall');
R($idx,'price_1TgyKu',-200,300,'Formular Plus 1,99 price ID kullanımı');
R($idx,'price_1TgyGd',-120,260,'Formular Basis 1,00 price ID');
R($idx,'einzel',-80,260,'einzel/Einzelkauf geçişi');
R($idx,'function buyForm',0,600,'buyForm/checkout (varsa)');
R($idx,'stripe-checkout',-150,300,'stripe-checkout çağrısı');

echo "\n### DOC SONUÇ (avukat notu yeri) ###\n";
R($idx,'window._lastGenAns',-400,500,'genDoc bitiş/sonuç');
R($idx,'makePDF(',-200,300,'makePDF kullanımı (sonuç gösterimi)');
R($idx,'Anwalt',-80,200,'mevcut Anwalt/Hinweis (varsa)');

echo "\n### STAATLICHE fiyat/plan kontrolü ###\n";
R($idx,'function openStaatForm',0,900,'openStaatForm (plan/fiyat mantığı)');

echo "\n=== BİTTİ. SİL: rm dump11.php ===\n";
