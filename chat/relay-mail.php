<?php
/**
 * ChatHelp — sendMail'i GoDaddy relay'ine bağla (+ test)
 * -----------------------------------------------------
 * relay-hosting.secureserver.net:25 (GoDaddy resmi relay, auth gerektirmez).
 *   1) Relay üzerinden test maili gönderir, SMTP konuşmasını basar.
 *   2) auth.php'deki sendMail()'i relay SMTP ile değiştirir.
 *
 * KULLANIM: chat-help.com/chat/relay-mail.php  (veya ?to=...)
 * Sonra opcache-reset.php. İŞ BİTİNCE SİL.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=UTF-8');

define('RELAY_HOST', 'relay-hosting.secureserver.net');
define('RELAY_PORT', 25);

function relay_test($to, $subject, $html, &$log) {
    $log = '';
    $fp = @fsockopen(RELAY_HOST, RELAY_PORT, $errno, $errstr, 15);
    if (!$fp) { $log .= "BAĞLANTI HATASI: $errstr ($errno)\n"; return false; }
    stream_set_timeout($fp, 15);
    $read = function() use ($fp, &$log) {
        $d = '';
        while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        $log .= "S: " . trim($d) . "\n"; return $d;
    };
    $cmd = function($c) use ($fp, $read, &$log) { $log .= "C: $c\n"; fwrite($fp, $c . "\r\n"); return $read(); };
    $read();
    $cmd("EHLO chat-help.com");
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

echo "=== 1) GoDaddy relay test ===\n";
$to = $_GET['to'] ?? 'acerasoft@gmail.com';
echo "Hedef: $to   Relay: " . RELAY_HOST . ":" . RELAY_PORT . "\n\n";
$log = '';
relay_test($to, 'ChatHelp Relay Test ✅', '<h2 style="color:#d4a84a">ChatHelp e-posta çalışıyor ✅</h2><p>Bu GoDaddy relay üzerinden gönderildi.</p>', $log);
echo $log . "\n>>> '250' kodları + sonda 'queued/OK' görürsen mail kuyruğa girdi. Gelen kutusu + SPAM kontrol et.\n";

/* ── 2) auth.php sendMail -> relay (esnek regex ile) ── */
echo "\n=== 2) auth.php sendMail -> relay ===\n";
$af = __DIR__ . '/auth.php';
if (!file_exists($af)) { echo "auth.php yok.\n"; exit; }
$src = file_get_contents($af);
file_put_contents($af . '.bak-relay-' . date('Ymd-His'), $src);

$new = <<<'NEW'
function sendMail(string $to, string $subject, string $html): void {
    $fp = @fsockopen('relay-hosting.secureserver.net', 25, $e, $s, 15);
    if (!$fp) { error_log('Mail relay fail: ' . $s); return; }
    stream_set_timeout($fp, 15);
    $read = function() use ($fp) { $d = ''; while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
    $cmd  = function($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $read();
    $cmd('EHLO chat-help.com');
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

$pattern = '/function sendMail\(string \$to, string \$subject, string \$html\): void \{.*?\n\}/s';
$cnt = 0;
$src2 = preg_replace_callback($pattern, function($m) use ($new) { return $new; }, $src, 1, $cnt);

if ($cnt > 0 && $src2 !== null) {
    file_put_contents($af, $src2);
    echo "  ✓ sendMail relay'e çevrildi ($cnt yerde)\n";
} else {
    echo "  ✗ sendMail fonksiyonu bulunamadı — bana haber ver\n";
}

echo "\nSONRA: opcache-reset.php aç. İŞ BİTİNCE: rm relay-mail.php\n";
