<?php
/**
 * ChatHelp — Mail taşıma teşhisi
 * Hangi yöntem çalışıyor: PHP mail() mi, yoksa hangi SMTP relay mi?
 * KULLANIM: chat-help.com/chat/mail-test.php  (veya ?to=baska@mail.com)
 * İŞ BİTİNCE SİL.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=UTF-8');

$to = $_GET['to'] ?? 'acerasoft@gmail.com';

echo "=== 1) PHP mail() testi ===\n";
$headers = "MIME-Version: 1.0\r\n"
         . "Content-Type: text/html; charset=UTF-8\r\n"
         . "From: " . SMTP_NAME . " <" . SMTP_FROM . ">\r\n";
$ok = @mail($to, 'ChatHelp mail() Test', '<h2 style="color:#d4a84a">ChatHelp mail() testi ✅</h2><p>Bu mail() ile gönderildi.</p>', $headers);
echo "  mail() döndü: " . ($ok ? "true ✅ (sunucu kabul etti)" : "false ❌ (mail() çalışmıyor)") . "\n";
echo "  Hedef: $to  →  gelen kutusu + SPAM kontrol et!\n\n";

echo "=== 2) Hangi SMTP relay açık? ===\n";
$relays = [
    'localhost:25',
    '127.0.0.1:25',
    'localhost:587',
    'relay-hosting.secureserver.net:25',   // GoDaddy resmi relay
    'smtpout.secureserver.net:25',          // GoDaddy
    'smtpout.secureserver.net:465',
    'smtp.hosteurope.de:587',
];
foreach ($relays as $t) {
    [$h, $p] = explode(':', $t);
    $fp = @fsockopen($h, (int)$p, $e, $s, 5);
    echo "  " . ($fp ? "✓ AÇIK  " : "✗ kapalı") . "  $t" . ($fp ? "" : "   ($s)") . "\n";
    if ($fp) fclose($fp);
}

echo "\n=== 3) DNS çözümleme ===\n";
foreach (['smtp.hosteurope.de', 'relay-hosting.secureserver.net', 'smtpout.secureserver.net', 'localhost'] as $h) {
    $ip = @gethostbyname($h);
    echo "  $h  ->  " . ($ip !== $h ? $ip : "ÇÖZÜLEMEDİ") . "\n";
}

echo "\n>>> Bu çıktının TAMAMINI bana yapıştır — doğru email yöntemini buna göre kuracağım.\n";
echo "Sil:  rm mail-test.php\n";
