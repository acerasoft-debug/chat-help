<?php
/**
 * ChatHelp — Kapsamlı Teşhis v2 (SADECE OKUR, hiçbir şey değiştirmez)
 * -----------------------------------------------------------------------
 * api.php, index.php ve auth.php'nin kritik bölgelerini döker.
 * Amac: patcher yazabilmek için gerçek metni görmek.
 * KULLANIM: chat-help.com/chat/dump-chat2.php — çıktıyı kopyala, yapıştır. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

function sec($title) { echo "\n" . str_repeat('=', 60) . "\n$title\n" . str_repeat('=', 60) . "\n"; }
function region($src, $needle, $before, $len, $label) {
    echo "\n--- $label ---\n";
    $p = strpos($src, $needle);
    if ($p === false) { echo "  (BULUNAMADI: '$needle')\n"; return; }
    echo substr($src, max(0, $p - $before), $len) . "\n";
}
function hasStr($src, $needle) { return strpos($src, $needle) !== false; }
function chk($label, $ok) { echo ($ok ? "  ✓ " : "  ✗ ") . $label . "\n"; }

/* ═══════════════════════════ api.php ═══════════════════════════ */
$api = @file_get_contents(__DIR__ . '/api.php');
sec("API.PHP — boyut=" . ($api === false ? 'YOK' : strlen($api) . ' byte'));

if ($api !== false) {
    echo "[Mevcut action'lar]\n";
    foreach (['chat','strategie','analyse','revise','generate','document','message'] as $a) {
        echo "  '$a'  ->  " . (hasStr($api, "'$a'") || hasStr($api, "\"$a\"") ? 'VAR' : 'yok') . "\n";
    }

    echo "\n[Plan kısıtlamaları]\n";
    foreach (['elite','pro','basic','free'] as $pl) {
        echo "  plan '$pl' -> " . (hasStr($api, "'$pl'") || hasStr($api, "\"$pl\"") ? 'VAR' : 'yok') . "\n";
    }

    echo "\n[AI adları kullanıcıya gösteriliyor mu?]\n";
    chk("'DeepSeek' UI metninde",  preg_match("/['\"].*[Dd]eep[Ss]eek.*['\"].*(response|reply|error|Dienst)/", $api));
    chk("'Claude' UI metninde",    preg_match("/['\"].*[Cc]laude.*['\"].*(response|reply|error|Dienst)/", $api));
    chk("error_reporting(0) ekli", hasStr($api, 'error_reporting(0)'));

    region($api, 'match(', 0, 800, 'match() routing tablosu');
    region($api, 'doStrategie', 0, 1200, 'doStrategie fonksiyonu');
    region($api, 'doChat', 0, 1200, 'doChat fonksiyonu');
    region($api, "plan.*strategie\|strategie.*plan", 0, 600, 'Strategie plan kontrolü');

    // Gerçek strategie kod bloğunu bul
    echo "\n--- Strategie etrafındaki gerçek metin (150 char çevre) ---\n";
    $p = strpos($api, 'strategie');
    if ($p !== false) echo "..." . substr($api, max(0,$p-100), 400) . "...\n";
}

/* ═══════════════════════════ index.php ═══════════════════════════ */
$idx = @file_get_contents(__DIR__ . '/index.php');
sec("INDEX.PHP — boyut=" . ($idx === false ? 'YOK' : strlen($idx) . ' byte'));

if ($idx !== false) {
    echo "[Genel kontroller]\n";
    chk("ch-theme CSS ekli",          hasStr($idx, 'id="ch-theme"'));
    chk("chFillKontoUser ekli",       hasStr($idx, 'chFillKontoUser'));
    chk("Beyaz tema CSS ekli",        hasStr($idx, 'data-chtheme="white"'));
    chk("Ton seçici mevcut",          hasStr($idx, 'chtheme-btn') && (hasStr($idx, 'formell') || hasStr($idx, 'Ton')));
    chk("Fotoğraf upload mevcut",     hasStr($idx, 'type="file"') && hasStr($idx, 'accept="image'));
    chk("Durdur/İptal butonu",        hasStr($idx, 'AbortController') || hasStr($idx, 'abort') || hasStr($idx, 'Abbrechen'));
    chk("chRevise() mevcut",          hasStr($idx, 'chRevise'));
    chk("showProfileForm mevcut",     hasStr($idx, 'showProfileForm'));

    echo "\n[AI adları kullanıcıya gösteriliyor mu?]\n";
    chk("'DeepSeek' görünür metinde", preg_match('/["\']DeepSeek["\']|>DeepSeek</', $idx));
    chk("'Claude' görünür metinde",   preg_match('/["\']Claude AI["\']|>Claude</', $idx));

    region($idx, "action:'chat'", 200, 800, "Ana sohbet gönderimi (action:'chat')");
    region($idx, "action:'strategie'", 200, 700, "Strategie gönderimi");
    region($idx, 'fetch(', 0, 1200, 'İlk fetch() çağrısı');
    region($idx, 'AbortController', 0, 500, 'AbortController (iptal)');
    region($idx, 'Abbrechen', 0, 400, '"Abbrechen" butonu');
    region($idx, 'type="file"', 0, 500, 'Dosya yükleme alanı');

    // Dilekçe form submit / loading durumu
    region($idx, 'showQuestions', 0, 800, 'showQuestions fonksiyonu');
    region($idx, 'genDoc', 0, 900, 'genDoc fonksiyonu');
    region($idx, 'async function send', 0, 1000, 'send() fonksiyonu');

    // Strategie plan kontrolü (index tarafında)
    region($idx, "plan.*strategie\|strategie.*elite\|strategie.*pro", 0, 500, 'Strategie plan kısıtı');
    $sp = strpos($idx, 'Strategie');
    if ($sp !== false) echo "\n--- 'Strategie' etrafı ---\n..." . substr($idx, max(0,$sp-80), 350) . "...\n";
}

/* ═══════════════════════════ auth.php ═══════════════════════════ */
$auth = @file_get_contents(__DIR__ . '/auth.php');
sec("AUTH.PHP — boyut=" . ($auth === false ? 'YOK' : strlen($auth) . ' byte'));

if ($auth !== false) {
    echo "[Kontroller]\n";
    chk("sendMail() var",           hasStr($auth, 'function sendMail') || hasStr($auth, 'sendMail('));
    chk("register action var",      hasStr($auth, "'register'") || hasStr($auth, '"register"'));
    chk("Email doğrulama token",    hasStr($auth, 'verify') || hasStr($auth, 'token'));
    chk("Hoşgeldin emaili",         hasStr($auth, 'Willkommen') || hasStr($auth, 'registriert') || hasStr($auth, 'Anmeldung'));

    region($auth, 'register', 200, 1200, 'register action kodu');
    region($auth, 'sendMail', 200, 900, 'sendMail çağrısı');
    region($auth, 'Willkommen', 100, 600, 'Email içeriği (Willkommen)');
    region($auth, 'Subject', 100, 400, 'Email Subject');
    region($auth, 'verify', 100, 600, 'Doğrulama kodu/token');
}

sec("MEVCUT TEMALAR (index.php CSS kontrolü)");
if ($idx !== false) {
    foreach (['anthracite','navy','white'] as $t) {
        echo "  '$t' tema CSS: " . (hasStr($idx, "data-chtheme=\"$t\"") ? '✓ VAR' : '✗ yok') . "\n";
    }
}

echo "\n=== RAPOR BİTTİ ===\n";
echo "Bu dosyayı SİL: rm dump-chat2.php\n";
