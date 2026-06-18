<?php
/**
 * ChatHelp — sendMail: GoDaddy relay öncelikli (kayıt mailleri gitsin)
 * --------------------------------------------------------------------
 * sendMail önce GoDaddy yerel relay'i (relay-hosting.secureserver.net:25,
 * auth/TLS yok) dener; ulaşılamazsa eski Host Europe yoluna (:587 STARTTLS+AUTH)
 * düşer. Böylece regresyon riski yok, ama relay açıksa mailler artık gider.
 *
 * KULLANIM: chat-help.com/chat/apply-mail-relay.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/auth.php';
if (!file_exists($file)) { exit("HATA: auth.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-mailrelay-' . date('Ymd-His'), $src);

$old = <<<'OLD'
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
OLD;

$new = <<<'NEW'
    // ChatHelp: önce GoDaddy yerel relay (:25, auth/TLS yok), olmazsa Host Europe (:587 STARTTLS+AUTH)
    $useRelay = false;
    $fp = @fsockopen('relay-hosting.secureserver.net', 25, $e, $s, 15);
    if ($fp) { $useRelay = true; }
    else { $fp = @fsockopen(SMTP_HOST, SMTP_PORT, $e, $s, 20); }
    if (!$fp) { error_log('SMTP connect fail: ' . $s); return; }
    stream_set_timeout($fp, 20);
    $read = function() use ($fp) { $d = ''; while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
    $cmd  = function($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $read();
    $cmd('EHLO chat-help.com');
    if (!$useRelay) {
        $cmd('STARTTLS');
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); error_log('SMTP TLS fail'); return; }
        $cmd('EHLO chat-help.com');
        $cmd('AUTH LOGIN');
        $cmd(base64_encode(SMTP_USER));
        $cmd(base64_encode(SMTP_PASS));
    }
NEW;

$n = substr_count($src, $old);
if ($n > 0) { $src = str_replace($old, $new, $src); }

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Mail relay raporu\n============================\n\n";
echo ($n > 0 ? "  ✓ sendMail relay öncelikli yapıldı  →  $n\n"
             : "  ✗ Kalıp bulunamadı (auth.php zaten değişmiş olabilir). HABER VER.\n");
echo "\n" . ($changed ? "DURUM: auth.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nTest: yeni bir hesap kaydı yap -> doğrulama maili gelmeli (SPAM'i de kontrol et).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-mail-relay.php\n";
