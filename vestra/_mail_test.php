<?php
/**
 * TEMPORARY mail diagnostic — admin-gated. DELETE from the server after use.
 * Usage:  /_mail_test.php?key=<admin_pass>                 → show config only
 *         /_mail_test.php?key=<admin_pass>&to=you@mail.com → send a live test
 * Shows the provider's raw HTTP response so we can see the exact reason mail fails.
 */
error_reporting(E_ALL); ini_set('display_errors', '1');
require __DIR__.'/inc/notify.php';
header('Content-Type: text/plain; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

$key  = (string)($_GET['key'] ?? '');
$pass = (string)vestra_cfg('admin_pass', '');
if (empty($_SESSION['vadmin']) && !($pass !== '' && hash_equals($pass, $key))) {
    http_response_code(403);
    echo "403 — append ?key=<your admin_pass>  (or log into /admin first)\n";
    exit;
}

function _mask(string $k): string {
    return $k === '' ? '(MISSING)' : substr($k, 0, 10).'…'.substr($k, -4).'  [length '.strlen($k).']';
}

$provider = strtolower((string)vestra_cfg('mail_api_provider', 'brevo'));
$k        = (string)vestra_cfg('mail_api_key', '');
$from     = (string)vestra_cfg('mail_from', '');
$name     = (string)vestra_cfg('smtp_name', 'VESTRA');
$enabled  = (bool)vestra_cfg('mail_enabled', false);

echo "VESTRA — mail diagnostic\n========================\n\n";
echo "inc/config.php readable : ".(is_readable(__DIR__.'/inc/config.php') ? 'YES' : 'NO — there is no config file!')."\n";
echo "mail_enabled            : ".var_export($enabled, true).($enabled ? '' : "   ← must be true, nothing sends otherwise!")."\n";
echo "mail_api_provider       : ".$provider."\n";
echo "mail_api_key            : "._mask($k)."\n";
$prefixNote = str_starts_with($k, 'xkeysib-') ? 'xkeysib-  ✓ correct Brevo API key'
    : (str_starts_with($k, 'xsmtpsib-') ? 'xsmtpsib-  ✗ this is an SMTP key, NOT the API key — WRONG TYPE'
    : (str_starts_with($k, 're_') ? 're_  (Resend key)' : '(unrecognised prefix)'));
echo "mail_api_key type       : ".$prefixNote."\n";
echo "mail_from (sender)      : ".($from !== '' ? $from : '(MISSING)')."\n";
echo "smtp_name               : ".$name."\n";
echo "require_email_verify    : ".var_export(vestra_cfg('require_email_verify', false), true)."\n";
echo "curl available          : ".(function_exists('curl_init') ? 'YES' : 'NO — cannot use the HTTP API!')."\n\n";

$to = trim((string)($_GET['to'] ?? ''));
if ($to === '') { echo "→ Add  &to=you@example.com  to send a live test and see the provider's reply.\n"; exit; }
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { echo "→ '$to' is not a valid email address.\n"; exit; }
if ($k === '') { echo "→ No mail_api_key set — fill it in inc/config.php first.\n"; exit; }

if ($provider === 'resend') {
    $url = 'https://api.resend.com/emails';
    $headers = ['Authorization: Bearer '.$k, 'Content-Type: application/json'];
    $payload = ['from' => "$name <$from>", 'to' => [$to], 'subject' => 'VESTRA test '.date('H:i:s'), 'text' => 'VESTRA mail transport test.'];
} else {
    $url = 'https://api.brevo.com/v3/smtp/email';
    $headers = ['api-key: '.$k, 'Content-Type: application/json', 'Accept: application/json'];
    $payload = ['sender' => ['name' => $name, 'email' => $from], 'to' => [['email' => $to]], 'subject' => 'VESTRA test '.date('H:i:s'), 'textContent' => 'VESTRA mail transport test.'];
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 20,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

echo "=== live send via ".strtoupper($provider)." (HTTPS/443) ===\n";
echo "to        : $to\n";
echo "HTTP code : ".$code."\n";
if ($resp === false) echo "curl error: ".$cerr."\n";
echo "response  : ".substr((string)$resp, 0, 900)."\n\n";

if ($code >= 200 && $code < 300) {
    echo "✅ Provider ACCEPTED the message. Check the inbox AND the spam folder.\n";
    echo "   In spam? Later authenticate your domain in Brevo (Senders → Domains) to add SPF/DKIM.\n";
} elseif ($code === 401) {
    echo "❌ 401 Unauthorised — wrong key or wrong key type.\n";
    echo "   Use an API key (xkeysib-…) from Brevo → SMTP & API → API Keys (not the SMTP tab).\n";
} elseif ($code === 400) {
    echo "❌ 400 — most often the SENDER is not verified.\n";
    echo "   Brevo → Senders → add & confirm '".$from."' (click the link Brevo emails there).\n";
} elseif ($code === 0) {
    echo "❌ No HTTP response — outbound HTTPS to the provider may be blocked, or curl failed (see above).\n";
} else {
    echo "❌ Send failed (HTTP $code) — read the response body above for the exact reason.\n";
}
echo "\nWhen done, DELETE this file:  rm ".__FILE__."\n";
