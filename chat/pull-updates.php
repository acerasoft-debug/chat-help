<?php
/**
 * ChatHelp — TEK KOMUTLA GÜNCELLEME KANALI
 * -----------------------------------------
 * GitHub'daki (public repo) apply/dump betiklerini sunucuya indirir, istenirse
 * sırayla ÇALIŞTIRIR (her biri ayrı HTTP isteğinde — birinin exit()'i diğerini
 * etkilemez), sonda opcache-reset çağırır ve çalışan apply dosyalarını siler.
 *
 * KURULUM (bir kez): bu dosyayı html/chat/ içine yükle. Sonrası hep terminal:
 *
 *   # Bu turun paketi (TR+FR katalog genişletme) — indir + çalıştır + temizle:
 *   curl -s "https://chat-help.com/chat/pull-updates.php?run=1"
 *
 *   # İleride herhangi bir dosya için:
 *   curl -s "https://chat-help.com/chat/pull-updates.php?files=apply-x.php,dump-y.php&run=1"
 *
 *   # Sadece indir, çalıştırma:  ?run=0   |  Çalışan apply'ları silme: &keep=1
 *
 * GÜVENLİK: yalnız aşağıdaki sabit repo+branch'ten, yalnız apply-* ve dump-* adlı
 * dosyalar indirilir; üretim dosyaları (index/api/auth...) isim filtresiyle
 * korunur. Apply betikleri zaten idempotent (marker kontrolü + .bak yedeği).
 */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
set_time_limit(300);

$RAW_BASE = 'https://raw.githubusercontent.com/acerasoft-debug/chat-help/claude/diagnostics-catalog-work-5gmxqw/chat/';
$DEFAULT_FILES = ['apply-turkiye-expand.php','apply-france-expand2.php'];
$PROTECT = ['index.php','api.php','auth.php','db.php','config.php','fall-api.php','intake-api.php',
            'send-doc.php','form-engine.js','stripe-checkout.php','stripe-webhook.php',
            'opcache-reset.php','cleanup.php','pull-updates.php','geo.php','translate.php'];

echo "ChatHelp — pull-updates\n=======================\n\n";

/* ---- parametreler ---- */
$run  = !isset($_GET['run']) || $_GET['run'] !== '0';   // varsayılan: çalıştır
$keep = isset($_GET['keep']) && $_GET['keep'] === '1';
$files = $DEFAULT_FILES;
if (!empty($_GET['files'])) {
    $files = array_filter(array_map('trim', explode(',', $_GET['files'])));
}

/* ---- isim güvenlik filtresi ---- */
$todo = [];
foreach ($files as $f) {
    $b = basename($f);
    if (in_array($b, $PROTECT, true)) { echo "  ✗ atlandı (korumalı): $b\n"; continue; }
    if (!preg_match('~^(apply|dump)[A-Za-z0-9._-]*\.php$~', $b)) { echo "  ✗ atlandı (izinli kalıp değil, sadece apply-*/dump-*): $b\n"; continue; }
    $todo[] = $b;
}
if (!$todo) exit("\nİndirilecek geçerli dosya yok.\n");

/* ---- HTTP GET (curl varsa curl, yoksa file_get_contents) ---- */
function ch_fetch($url) {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_USERAGENT=>'ChatHelp-pull/1.0']);
        $body = curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($body !== false && $code >= 200 && $code < 300) return $body;
        return null;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>60,'user_agent'=>'ChatHelp-pull/1.0']]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body === false) ? null : $body;
}

/* ---- 1) indir ---- */
echo "[1/3] GitHub'dan indiriliyor (branch: claude/diagnostics-catalog-work-5gmxqw)\n";
$downloaded = [];
foreach ($todo as $b) {
    $body = ch_fetch($RAW_BASE . rawurlencode($b));
    if ($body === null || strpos($body, '<?php') !== 0) {
        echo "  ✗ indirilemedi: $b (GitHub'a erişim / dosya adı kontrol et)\n";
        continue;
    }
    if (file_put_contents(__DIR__ . '/' . $b, $body) === false) {
        echo "  ✗ yazılamadı: $b (dizin yazma izni?)\n";
        continue;
    }
    echo "  ✓ indirildi: $b (" . strlen($body) . " bayt)\n";
    $downloaded[] = $b;
}
if (!$downloaded) exit("\nHiçbir dosya indirilemedi.\n");

if (!$run) {
    echo "\n[run=0] Çalıştırma atlandı. Tarayıcıda sırayla aç:\n";
    foreach ($downloaded as $b) echo "  https://" . ($_SERVER['HTTP_HOST'] ?? 'chat-help.com') . dirname($_SERVER['SCRIPT_NAME'] ?? '/chat/x') . "/$b\n";
    exit("Sonra: opcache-reset.php\n");
}

/* ---- 2) çalıştır (her biri ayrı HTTP isteği — exit() izolasyonu) ---- */
$host = $_SERVER['HTTP_HOST'] ?? 'chat-help.com';
$dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/chat/pull-updates.php'), '/');
$base = 'https://' . $host . $dir . '/';
echo "\n[2/3] Çalıştırılıyor ($base)\n";
$ran = [];
foreach ($downloaded as $b) {
    echo "\n----- $b -----\n";
    $out = ch_fetch($base . rawurlencode($b));
    if ($out === null) { echo "  ✗ çalıştırılamadı (HTTP hatası). Tarayıcıda elle aç: $base$b\n"; continue; }
    echo trim($out) . "\n";
    $ran[] = $b;
}

/* ---- 3) opcache + temizlik ---- */
echo "\n[3/3] opcache + temizlik\n";
$op = ch_fetch($base . 'opcache-reset.php');
if ($op !== null) { echo "  ✓ opcache-reset: " . trim(strtok($op, "\n")) . "\n"; }
elseif (function_exists('opcache_reset')) { @opcache_reset(); echo "  ✓ opcache_reset() doğrudan çağrıldı.\n"; }
else { echo "  ⚠ opcache-reset.php erişilemedi — tarayıcıda elle aç.\n"; }

if (!$keep) {
    foreach ($ran as $b) {
        if (strpos($b, 'apply') === 0 && @unlink(__DIR__ . '/' . $b)) echo "  🗑 silindi: $b\n";
    }
    echo "  (dump-* dosyaları silinmez — çıktıları hâlâ gerekebilir)\n";
} else {
    echo "  [keep=1] dosyalar silinmedi.\n";
}

echo "\nBİTTİ. Siteyi gizli pencerede test et.\n";
echo "pull-updates.php sunucuda kalabilir — sonraki güncellemeler için kalıcı kanal.\n";
