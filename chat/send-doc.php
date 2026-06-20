<?php
/**
 * ChatHelp — Üretilen dilekçeyi/sözleşmeyi e-posta ile gönder (GoDaddy relay)
 * POST {to, subject, doc}
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
error_reporting(0); @ini_set('display_errors','0');

require_once __DIR__ . '/db.php';

$b   = json_decode(file_get_contents('php://input'), true) ?: [];
$to  = trim($b['to'] ?? '');
$sub = trim($b['subject'] ?? 'Ihr Dokument — ChatHelp');
$doc = (string)($b['doc'] ?? '');

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(['error' => 'Ungültige E-Mail-Adresse']); exit; }
if (strlen(trim($doc)) < 20)               { echo json_encode(['error' => 'Kein Dokument vorhanden']); exit; }
if (strlen($doc) > 200000)                  $doc = substr($doc, 0, 200000);

$esc  = htmlspecialchars($doc, ENT_QUOTES, 'UTF-8');
$html = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;color:#111">'
      . '<h2 style="color:#b8862f">ChatHelp — Ihr Dokument</h2>'
      . '<p>Im Anhang dieser Nachricht finden Sie Ihr mit ChatHelp erstelltes Dokument:</p>'
      . '<pre style="white-space:pre-wrap;font-family:\'Times New Roman\',Georgia,serif;font-size:14px;line-height:1.55;'
      . 'background:#f7f7fb;border:1px solid #e3e3ee;border-radius:10px;padding:20px;color:#15151f">' . $esc . '</pre>'
      . '<p style="color:#888;font-size:12px;margin-top:16px">KI-gestützt erstellt — bei wichtigen Dokumenten empfehlen wir eine anwaltliche Prüfung.</p>'
      . '<p style="color:#aaa;font-size:11px">chat-help.com</p></div>';

$ok = ch_send_relay($to, $sub, $html);
echo json_encode(['success' => $ok, 'sent_to' => $ok ? $to : null]);

/* GoDaddy yerel relay (auth/TLS yok). Olmazsa Host Europe STARTTLS yedeği. */
function ch_send_relay(string $to, string $subject, string $html): bool {
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'support@chat-help.de';
    $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    $body = 'From: ' . $name . ' <' . $from . ">\r\n"
          . 'To: <' . $to . ">\r\n"
          . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
          . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html));

    // 1) Relay :25
    $fp = @fsockopen('relay-hosting.secureserver.net', 25, $e, $s, 15);
    if ($fp) {
        stream_set_timeout($fp, 15);
        $read = function() use ($fp) { $d=''; while($l=fgets($fp,515)){ $d.=$l; if(isset($l[3])&&$l[3]===' ')break; } return $d; };
        $cmd  = function($c) use ($fp,$read){ fwrite($fp,$c."\r\n"); return $read(); };
        $read(); $cmd('EHLO chat-help.com');
        $cmd('MAIL FROM:<'.$from.'>'); $cmd('RCPT TO:<'.$to.'>'); $cmd('DATA');
        fwrite($fp, $body."\r\n.\r\n"); $final=$read(); $cmd('QUIT'); fclose($fp);
        if (strpos($final,'250')===0) return true;
    }
    // 2) Host Europe STARTTLS yedeği
    if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
        $fp2 = @fsockopen(SMTP_HOST, defined('SMTP_PORT')?SMTP_PORT:587, $e2, $s2, 20);
        if (!$fp2) return false;
        stream_set_timeout($fp2, 20);
        $read = function() use ($fp2){ $d=''; while($l=fgets($fp2,515)){ $d.=$l; if(isset($l[3])&&$l[3]===' ')break; } return $d; };
        $cmd  = function($c) use ($fp2,$read){ fwrite($fp2,$c."\r\n"); return $read(); };
        $read(); $cmd('EHLO chat-help.com'); $cmd('STARTTLS');
        if (!@stream_socket_enable_crypto($fp2, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp2); return false; }
        $cmd('EHLO chat-help.com'); $cmd('AUTH LOGIN'); $cmd(base64_encode(SMTP_USER)); $cmd(base64_encode(SMTP_PASS));
        $cmd('MAIL FROM:<'.$from.'>'); $cmd('RCPT TO:<'.$to.'>'); $cmd('DATA');
        fwrite($fp2, $body."\r\n.\r\n"); $final=$read(); $cmd('QUIT'); fclose($fp2);
        return strpos($final,'250')===0;
    }
    return false;
}
