<?php
/**
 * ChatHelp — SMTP düzeltme + test
 * -------------------------------
 *   1) Bir test e-postası gönderir (smtp.hosteurope.de) ve tüm SMTP
 *      konuşmasını ekrana basar — çalışıyor mu hemen görürüz.
 *   2) auth.php'deki sendMail()'i (mail() -> gerçek SMTP) ile değiştirir,
 *      böylece doğrulama/şifre maillerinin GİDER.
 *
 * KULLANIM:
 *   chat-help.com/chat/smtp-fix.php            (test -> acerasoft@gmail.com)
 *   chat-help.com/chat/smtp-fix.php?to=...     (başka adrese test)
 * Sonra opcache-reset.php aç. İŞ BİTİNCE SİL.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=UTF-8');

/* ── Verbose SMTP gönderici (test için, konuşmayı loglar) ── */
function smtp_test($to, $subject, $html, &$log) {
    $log = '';
    $fp = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 20);
    if (!$fp) { $log .= "BAĞLANTI HATASI: $errstr ($errno)\n"; return false; }
    stream_set_timeout($fp, 20);
    $read = function() use ($fp, &$log) {
        $d = '';
        while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        $log .= "S: " . trim($d) . "\n"; return $d;
    };
    $cmd = function($c, $hide = false) use ($fp, $read, &$log) {
        $log .= "C: " . ($hide ? '***' : $c) . "\n"; fwrite($fp, $c . "\r\n"); return $read();
    };
    $read();
    $cmd("EHLO chat-help.com");
    $cmd("STARTTLS");
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $log .= "TLS BAŞARISIZ\n"; fclose($fp); return false; }
    $cmd("EHLO chat-help.com");
    $cmd("AUTH LOGIN");
    $cmd(base64_encode(SMTP_USER), true);
    $r = $cmd(base64_encode(SMTP_PASS), true);
    if (strpos($r, '235') === false) { $log .= "!! AUTH BAŞARISIZ (şifre?) !!\n"; }
    $cmd("MAIL FROM:<" . SMTP_FROM . ">");
    $cmd("RCPT TO:<$to>");
    $cmd("DATA");
    $msg  = "From: " . SMTP_NAME . " <" . SMTP_FROM . ">\r\n";
    $msg .= "To: <$to>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html));
    fwrite($fp, $msg . "\r\n.\r\n"); $read();
    $cmd("QUIT");
    fclose($fp);
    return true;
}

echo "=== 1) SMTP TEST ===\n";
$to = $_GET['to'] ?? 'acerasoft@gmail.com';
echo "Hedef: $to   Sunucu: " . SMTP_HOST . ":" . SMTP_PORT . "\n\n";
$log = '';
$ok = smtp_test($to, 'ChatHelp SMTP Test ✅', '<h2 style="color:#d4a84a">ChatHelp SMTP çalışıyor ✅</h2><p>Bu bir test e-postasıdır.</p>', $log);
echo $log . "\n";
echo $ok ? ">>> Test gönderildi. Gelen kutunu + SPAM klasörünü kontrol et.\n" : ">>> Test BAŞARISIZ (yukarıdaki log'a bak).\n";

/* ── 2) auth.php sendMail() -> gerçek SMTP ── */
echo "\n=== 2) auth.php sendMail -> SMTP ===\n";
$af = __DIR__ . '/auth.php';
if (!file_exists($af)) { echo "auth.php bulunamadı.\n"; exit; }
$src = file_get_contents($af);
file_put_contents($af . '.bak-smtp-' . date('Ymd-His'), $src);

$old = <<<'OLD'
function sendMail(string $to, string $subject, string $html): void {
    // PHPMailer alternative: use mail() or install PHPMailer via Composer
    // Simple fallback: use PHP mail()
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SMTP_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To: ' . SMTP_FROM,
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);
    mail($to, $subject, $html, $headers);
}
OLD;

$new = <<<'NEW'
function sendMail(string $to, string $subject, string $html): void {
    $fp = @fsockopen(SMTP_HOST, SMTP_PORT, $e, $s, 20);
    if (!$fp) { error_log('SMTP connect fail: ' . $s); return; }
    stream_set_timeout($fp, 20);
    $read = function() use ($fp) { $d = ''; while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
    $cmd  = function($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $read();
    $cmd('EHLO chat-help.com');
    $cmd('STARTTLS');
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); error_log('SMTP TLS fail'); return; }
    $cmd('EHLO chat-help.com');
    $cmd('AUTH LOGIN');
    $cmd(base64_encode(SMTP_USER));
    $cmd(base64_encode(SMTP_PASS));
    $cmd('MAIL FROM:<' . SMTP_FROM . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');
    $msg  = 'From: ' . SMTP_NAME . ' <' . SMTP_FROM . ">\r\n";
    $msg .= 'To: <' . $to . ">\r\n";
    $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html));
    fwrite($fp, $msg . "\r\n.\r\n"); $read();
    $cmd('QUIT');
    fclose($fp);
}
NEW;

$n = substr_count($src, $old);
if ($n > 0) {
    $src = str_replace($old, $new, $src);
    file_put_contents($af, $src);
    echo "  ✓ sendMail SMTP'ye çevrildi ($n yerde)\n";
} else {
    echo "  ✗ Eski sendMail kalıbı bulunamadı (zaten değişmiş olabilir) — bana haber ver\n";
}

echo "\nSONRA: opcache-reset.php'yi bir kez aç (değişiklik canlı olsun).\n";
echo "Sil:  rm smtp-fix.php\n";
