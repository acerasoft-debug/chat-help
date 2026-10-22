<?php
/**
 * ChatHelp — pull2 — ANAHTAR KORUMALI guncelleme kanali (pull-updates'in yerine)
 *  • Her istek ?key=... ister; anahtar sunucudaki pull-key.php'de durur
 *    (git'te YOK — kurulumda rastgele uretildi). Anahtarsiz/yanlis istege
 *    duz 404 doner: disaridan bakan icin bu dosya "yok" gibidir.
 *  • Yalniz sabit repo+branch'ten, yalniz apply-... ve dump-... indirilir; uretim
 *    dosyalari isim filtresiyle korunur (pull-updates ile ayni kurallar).
 *  • Calistirilan TUM betikler (dump'lar dahil) is bitince silinir — sunucuda
 *    kimlik dogrulamasiz cagrilabilir hicbir teshis ucu birakmaz. (&keep=1 saklar)
 *  • files bos ise: hizli saglik raporu (lint + stabilite isaretleri).
 * KULLANIM: /chat/pull2.php?key=ANAHTAR&files=apply-x.php,dump-y.php
 */
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(300);

/* ── anahtar kapisi ── */
$__kf = __DIR__.'/pull-key.php';
$__secret = is_file($__kf) ? (string)@include($__kf) : '';
if ($__secret === '' || !hash_equals($__secret, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Not Found\n");
}

header('Content-Type: text/plain; charset=UTF-8');
echo "ChatHelp — pull2 (guvenli kanal)\n================================\n\n";

$RAW_BASE = 'https://raw.githubusercontent.com/acerasoft-debug/chat-help/claude/diagnostics-catalog-work-5gmxqw/chat/';
$PROTECT = ['index.php','api.php','auth.php','db.php','config.php','fall-api.php','intake-api.php',
            'send-doc.php','form-engine.js','stripe-checkout.php','stripe-webhook.php',
            'opcache-reset.php','cleanup.php','pull-updates.php','pull2.php','pull-key.php','geo.php','translate.php'];

$run  = !isset($_GET['run']) || $_GET['run'] !== '0';
$keep = isset($_GET['keep']) && $_GET['keep'] === '1';

/* ── files bos: saglik raporu ── */
if (empty($_GET['files'])) {
    echo "SAGLIK RAPORU\n";
    foreach (['index.php','api.php','fall-api.php'] as $f) {
        $p = __DIR__."/$f";
        if (!file_exists($p)) { echo "  ✗ $f YOK!\n"; continue; }
        $lo=[]; $rc=0; exec('php -l '.escapeshellarg($p).' 2>&1',$lo,$rc);
        echo "  ".($rc===0?"✓":"✗")." $f  ".number_format(filesize($p))." B  lint:".($rc===0?"TEMIZ":implode(' ',$lo))."\n";
    }
    $idx=(string)@file_get_contents(__DIR__.'/index.php');
    foreach (['CH_UI_STABILITY','CH_STAB_FINAL2','CH_STAB_FINAL3','CH_STAB_FINAL4','CH_EXQ_NATIVE','CH_I18N_TRUCE','CH_PDF_PREMIUM','CH_EDGE_BYPASS'] as $m)
        echo "  ".str_pad($m,16).": ".(strpos($idx,$m)!==false?"VAR":"yok")."\n";
    exit("\nKullanim: &files=apply-x.php,dump-y.php\n");
}

/* ── isim filtresi ── */
$todo = [];
foreach (array_filter(array_map('trim', explode(',', $_GET['files']))) as $f) {
    $b = basename($f);
    if (in_array($b, $PROTECT, true)) { echo "  ✗ atlandi (korumali): $b\n"; continue; }
    if (!preg_match('~^(apply|dump)[A-Za-z0-9._-]*\.php$~', $b)) { echo "  ✗ atlandi (sadece apply-*/dump-*): $b\n"; continue; }
    $todo[] = $b;
}
if (!$todo) exit("\nIndirilecek gecerli dosya yok.\n");

function ch_fetch($url) {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_USERAGENT=>'ChatHelp-pull/2.0']);
        $body = curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($body !== false && $code >= 200 && $code < 300) return $body;
        return null;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>60,'user_agent'=>'ChatHelp-pull/2.0']]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body === false) ? null : $body;
}

/* ── 1) indir ── */
echo "[1/3] GitHub'dan indiriliyor\n";
$downloaded = [];
foreach ($todo as $b) {
    $body = ch_fetch($RAW_BASE . rawurlencode($b) . '?_nc=' . str_replace('.','',microtime(true)));
    if ($body === null || strpos($body, '<?php') !== 0) { echo "  ✗ indirilemedi: $b\n"; continue; }
    if (file_put_contents(__DIR__.'/'.$b, $body) === false) { echo "  ✗ yazilamadi: $b\n"; continue; }
    echo "  ✓ indirildi: $b (".strlen($body)." bayt)\n";
    $downloaded[] = $b;
}
if (!$downloaded) exit("\nHicbir dosya indirilemedi.\n");

if (!$run) {
    echo "\n[run=0] Calistirma atlandi; dosyalar sunucuda hazir.\n";
    exit;
}

/* ── 2) calistir (ayri HTTP istegi — exit izolasyonu) ── */
$host = $_SERVER['HTTP_HOST'] ?? 'chat-help.com';
$dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/chat/pull2.php'), '/');
$base = 'https://' . $host . $dir . '/';
echo "\n[2/3] Calistiriliyor ($base)\n";
$ran = [];
foreach ($downloaded as $b) {
    echo "\n----- $b -----\n";
    $out = ch_fetch($base . rawurlencode($b));
    if ($out === null) { echo "  ✗ calistirilamadi (HTTP hatasi)\n"; continue; }
    echo trim($out) . "\n";
    $ran[] = $b;
}

/* ── 3) opcache + temizlik (dump'lar DAHIL silinir) ── */
echo "\n[3/3] opcache + temizlik\n";
$op = ch_fetch($base . 'opcache-reset.php');
if ($op !== null) { echo "  ✓ opcache-reset: " . trim(strtok($op, "\n")) . "\n"; }
elseif (function_exists('opcache_reset')) { @opcache_reset(); echo "  ✓ opcache_reset() dogrudan.\n"; }
else { echo "  ⚠ opcache-reset erisilemedi.\n"; }

if (!$keep) {
    foreach ($downloaded as $b) {
        if (@unlink(__DIR__.'/'.$b)) echo "  🗑 silindi: $b\n";
    }
    echo "  (sunucuda kimlik dogrulamasiz cagrilabilir betik BIRAKILMADI)\n";
} else {
    echo "  [keep=1] dosyalar silinmedi.\n";
}

echo "\nBITTI.\n";
