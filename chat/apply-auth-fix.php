<?php
/**
 * ChatHelp — auth.php hata düzeltmeleri
 * -------------------------------------
 *   #1 action'ı body'den de oku (reset_password/forgot_password yönlensin)
 *   #2 global $pdo  ->  $pdo = db()   (şifre sıfırlama çökmesini önler)
 *   #3 sendEmail()  ->  sendMail()    (tanımsız fonksiyon hatası)
 *   #4 email/verify linkleri chat-help.de -> chat-help.com
 *
 * KULLANIM: html/chat/ -> chat-help.com/chat/apply-auth-fix.php
 * Sonra OPcache temizle (opcache-reset.php). İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/auth.php';
if (!file_exists($file)) { exit("HATA: auth.php bulunamadı.\n"); }

$src   = file_get_contents($file);
$start = $src;
$bak = $file . '.bak-' . date('Ymd-His');
file_put_contents($bak, $src);

$report = [];
function rep(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) { $src = str_replace($old, $new, $src); }
    $report[] = [$label, $n];
}

/* #1 — action'ı body'den de oku */
$old1 = <<<'O'
$action = $_GET['action'] ?? 'login';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
O;
$new1 = <<<'N'
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($body['action'] ?? 'login');
N;
rep($src, '#1 action body routing', $old1, $new1);

/* #2 — global $pdo -> $pdo = db()  (2 yerde) */
rep($src, '#2 global pdo -> db()', 'global $pdo;', '$pdo = db();');

/* #3 — sendEmail -> sendMail */
$old3 = <<<'O'
sendEmail($email, $user['name'], $subject, $body);
O;
$new3 = <<<'N'
sendMail($email, $subject, $body);
N;
rep($src, '#3 sendEmail -> sendMail', $old3, $new3);

/* #4 — domain .de -> .com (sadece /chat/ URL'leri; email adresleri dokunulmaz) */
rep($src, '#4 link domain .de -> .com', 'https://chat-help.de/chat/', 'https://chat-help.com/chat/');

$changed = ($src !== $start);
if ($changed) { file_put_contents($file, $src); }

echo "ChatHelp — auth.php düzeltme raporu\n";
echo "===================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
foreach ($report as [$l, $n]) { echo ($n > 0 ? "  ✓ " : "  ✗ ") . $l . "  →  $n\n"; }
echo "\n" . ($changed ? "DURUM: auth.php güncellendi. ✅\n" : "DURUM: Değişiklik yok (zaten uygulanmış olabilir).\n");
echo "\nSONRA: opcache-reset.php'yi bir kez aç (PHP değişikliği görünür olsun).\n";
echo "Sil:  rm apply-auth-fix.php\n";
echo "\nNot: '✗ 0' satır varsa kalıp bulunamadı — bana haber ver.\n";
