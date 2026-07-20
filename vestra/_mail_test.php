<?php
/**
 * VESTRA — mail diagnostic (TEMPORARY; delete after use).
 * Shows which mail transport is active and why sending may fail, and can send a
 * real test email. Gate: ?key=<admin password> (config admin_pass) or be logged
 * into /admin. Send a test:  ?key=…&to=you@example.com
 */
error_reporting(E_ALL); ini_set('display_errors','1');
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__.'/inc/notify.php';           // vestra_cfg(), vestra_send_mail()
if (session_status() === PHP_SESSION_NONE) session_start();

$adminPass = (string) vestra_cfg('admin_pass','');
$key = (string) ($_GET['key'] ?? '');
if (empty($_SESSION['vadmin']) && !($adminPass !== '' && hash_equals($adminPass, $key))) {
    http_response_code(403);
    echo "403 — pass ?key=<admin password> or log into /admin first.\n";
    exit;
}

$line = str_repeat('─', 58);
$yn   = fn($b) => $b ? 'yes' : 'NO';

$enabled  = (bool) vestra_cfg('mail_enabled', false);
$apiKey   = (string) vestra_cfg('mail_api_key', '');
$provider = (string) vestra_cfg('mail_api_provider', 'brevo');
$from     = (string) vestra_cfg('mail_from', '');
$smtpHost = (string) vestra_cfg('smtp_host', '');
$name     = (string) vestra_cfg('smtp_name', 'VESTRA');

echo "VESTRA MAIL DIAGNOSTIC\n$line\n\n";

echo "1) Configuration (inc/config.php)\n";
echo "   mail_enabled      : ".$yn($enabled).($enabled ? '' : "   ← EMAILS ARE OFF. Set 'mail_enabled' => true")."\n";
echo "   mail_from         : ".($from !== '' ? $from : "MISSING   ← set a verified sender, e.g. support@vestrasales.com")."\n";
echo "   mail_api_key      : ".($apiKey !== '' ? 'set ('.substr($apiKey,0,8).'…)' : 'MISSING')."\n";
echo "   mail_api_provider : ".$provider."\n";
echo "   smtp_host         : ".($smtpHost !== '' ? $smtpHost : '— (not used)')."\n\n";

echo "2) Active transport\n";
if (!$enabled)              echo "   NONE — mail_enabled is off; vestra_send_mail() returns false immediately.\n";
elseif ($apiKey !== '')     echo "   HTTP API ($provider) over HTTPS/443 — the recommended path. ✓\n";
elseif ($smtpHost !== '')   echo "   SMTP ($smtpHost) — needs an open outbound SMTP port (often blocked on shared hosting).\n";
else                        echo "   Local mail() — usually lands in spam or is rejected (no SPF/DKIM for this server IP).\n"
                                ."   → Configure the Brevo HTTP API instead: mail_api_key + mail_from.\n";
echo "\n";

echo "3) Test send\n";
$to = trim($_GET['to'] ?? '');
if ($to === '') {
    echo "   Add  &to=you@example.com  to send a real test email.\n";
} elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "   '$to' is not a valid email address.\n";
} else {
    echo "   Sending test email to $to …\n";
    $r = vestra_send_mail($to, 'VESTRA — mail test',
        "This is a VESTRA mail diagnostic.\n\nIf you received this, password-reset and verification emails will work.\n\n— VESTRA · vestrasales.com");
    echo "   Result: ".($r ? "✅ SENT — check the inbox AND the spam folder." : "❌ FAILED.")."\n";
    if (!$r) {
        if (!$enabled)                        echo "   Reason: mail_enabled is off.\n";
        elseif ($apiKey === '' && $smtpHost === '') echo "   Reason: no API key / SMTP configured — local mail() failed or was rejected.\n";
        elseif ($apiKey !== '')               echo "   Reason: Brevo rejected the request. Usual causes: wrong API key, or\n"
                                                  ."           mail_from is NOT a verified sender in Brevo (Senders, Domains & IPs →\n"
                                                  ."           verify the sender/domain and add the SPF + DKIM DNS records).\n"
                                                  ."           The exact HTTP error is in the server error log ([VESTRA API]).\n";
        else                                  echo "   Reason: SMTP send failed (port blocked or wrong credentials).\n";
    }
}
echo "\n$line\nDelete this file when done:  rm ".__FILE__."\n";
