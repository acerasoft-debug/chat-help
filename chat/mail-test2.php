<?php
/**
 * ChatHelp — E-posta yolu testi (hangi SMTP açık? gerçekten gönderebiliyor mu?)
 * KULLANIM:
 *   Bağlantı testi:  curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/mail-test2.php"
 *   Gerçek gönderim: ...mail-test2.php?to=acerasoft@gmail.com
 * Çıktıyı bana gönder. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/db.php';

$to = $_GET['to'] ?? '';

echo "ChatHelp — E-posta yolu testi\n=============================\n\n";
echo "Tanımlı ayarlar:\n";
echo "  SMTP_HOST = " . (defined('SMTP_HOST') ? SMTP_HOST : '(yok)') . "\n";
echo "  SMTP_PORT = " . (defined('SMTP_PORT') ? SMTP_PORT : '(yok)') . "\n";
echo "  SMTP_FROM = " . (defined('SMTP_FROM') ? SMTP_FROM : '(yok)') . "\n\n";

/* ── Bağlantı testleri ── */
function conn($host, $port) {
    $t0 = microtime(true);
    $fp = @fsockopen($host, $port, $e, $s, 12);
    $ms = round((microtime(true) - $t0) * 1000);
    if (!$fp) return "  ✗ $host:$port  AÇILMADI  ($s) [${ms}ms]";
    $banner = @fgets($fp, 256);
    fclose($fp);
    return "  ✓ $host:$port  AÇIK  [${ms}ms]  banner: " . trim((string)$banner);
}

echo "[Bağlantı testleri]\n";
echo conn('smtp.hosteurope.de', 587) . "\n";
echo conn('smtp.hosteurope.de', 465) . "\n";
echo conn('relay-hosting.secureserver.net', 25) . "\n";
echo conn('localhost', 25) . "\n";

/* ── Gerçek gönderim (relay üzerinden) ── */
if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "\n[GERÇEK GÖNDERİM -> $to  (GoDaddy relay :25)]\n";
    $log = [];
    $ok = relaySend('relay-hosting.secureserver.net', 25, $to, 'ChatHelp Test', '<h2>ChatHelp Test</h2><p>Wenn Sie das lesen, funktioniert der Mailversand. ✅</p>', $log);
    foreach ($log as $l) echo "  $l\n";
    echo "\n  SONUÇ: " . ($ok ? "✅ relay kabul etti — gelen kutunu (ve SPAM) kontrol et" : "❌ relay reddetti/başarısız") . "\n";

    if (!$ok) {
        echo "\n[YEDEK: localhost :25 deneniyor]\n";
        $log2 = [];
        $ok2 = relaySend('localhost', 25, $to, 'ChatHelp Test (local)', '<h2>ChatHelp Test (localhost)</h2><p>Via local MTA. ✅</p>', $log2);
        foreach ($log2 as $l) echo "  $l\n";
        echo "\n  SONUÇ: " . ($ok2 ? "✅ localhost MTA kabul etti" : "❌ localhost da olmadı") . "\n";
    }
} else {
    echo "\n[Gerçek gönderim atlandı]\n";
    echo "  Test maili almak için: mail-test2.php?to=SENIN@gmail.com\n";
}

function relaySend($host, $port, $to, $subject, $html, &$log): bool {
    $fp = @fsockopen($host, $port, $e, $s, 15);
    if (!$fp) { $log[] = "connect FAIL: $s ($e)"; return false; }
    stream_set_timeout($fp, 15);
    $read = function() use ($fp) { $d=''; while($l=fgets($fp,515)){ $d.=$l; if(isset($l[3])&&$l[3]===' ')break; } return trim($d); };
    $cmd  = function($c) use ($fp,$read,&$log){ fwrite($fp,$c."\r\n"); $r=$read(); $log[]="> ".$c."   <= ".$r; return $r; };
    $log[] = "banner: " . $read();
    $cmd('EHLO chat-help.com');
    $cmd('MAIL FROM:<' . (defined('SMTP_FROM')?SMTP_FROM:'support@chat-help.de') . '>');
    $rcpt = $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');
    $from = defined('SMTP_FROM')?SMTP_FROM:'support@chat-help.de';
    $name = defined('SMTP_NAME')?SMTP_NAME:'ChatHelp';
    $msg  = 'From: '.$name.' <'.$from.">\r\n";
    $msg .= 'To: <'.$to.">\r\n";
    $msg .= 'Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html));
    fwrite($fp, $msg . "\r\n.\r\n");
    $final = $read(); $log[] = "DATA sonucu <= " . $final;
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return (strpos($final, '250') === 0);
}

echo "\n=============================\nBİTTİ. Çıktıyı gönder. SİL: rm mail-test2.php\n";
