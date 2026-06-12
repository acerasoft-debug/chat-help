<?php
/**
 * ChatHelp — Sohbet akışı teşhisi (SADECE OKUR)
 * Ana sohbetin (kategori chat) bağlamı nasıl taşıdığını dökeriz:
 *  - api.php: chat/strategie/analyse action handler'ları
 *  - index.php: mesaj gönderen fonksiyon (history dahil mi?)
 * KULLANIM: chat-help.com/chat/dump-chat.php aç, çıktıyı bana yapıştır. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

function region($src, $needle, $before, $len, $label) {
    echo "\n===== $label  ('$needle') =====\n";
    $p = strpos($src, $needle);
    if ($p === false) { echo "  (BULUNAMADI)\n"; return; }
    $s = max(0, $p - $before);
    echo substr($src, $s, $len) . "\n";
}

/* ---- api.php ---- */
$api = @file_get_contents(__DIR__ . '/api.php');
echo "### API.PHP ###  boyut=" . ($api === false ? 'YOK' : strlen($api)) . "\n";
if ($api !== false) {
    // match() routing tablosu
    region($api, "match(", 0, 700, "api match routing");
    // chat handler
    region($api, "function doChat", 0, 1600, "doChat");
    region($api, "function doStrategie", 0, 1600, "doStrategie");
    region($api, "function doAnalyse", 0, 1200, "doAnalyse");
    // hangi action isimleri var
    foreach (['\'chat\'','\'strategie\'','\'analyse\'','\'message\'','\'ask\''] as $a) {
        echo "  action $a  -> " . (strpos($api, $a) !== false ? 'VAR' : 'yok') . "\n";
    }
}

/* ---- index.php ---- */
$idx = @file_get_contents(__DIR__ . '/index.php');
echo "\n\n### INDEX.PHP ###  boyut=" . ($idx === false ? 'YOK' : strlen($idx)) . "\n";
if ($idx !== false) {
    // ana sohbet gönderimi
    region($idx, "action:'chat'", 200, 900, "index action:'chat' gönderimi");
    region($idx, "action:'strategie'", 200, 900, "index action:'strategie'");
    region($idx, "async function send", 0, 1400, "send() fonksiyonu");
    region($idx, "function sendMsg", 0, 1400, "sendMsg()");
    // history / messages array taşınıyor mu
    foreach (['messages:', 'history', 'convo', 'window.CHAT', 'CTX', '_thread', 'currentThread'] as $k) {
        echo "  anahtar '$k' -> " . (strpos($idx, $k) !== false ? 'VAR' : 'yok') . "\n";
    }
}
echo "\n=== SON ===\n";
