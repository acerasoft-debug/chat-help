<?php
/**
 * ChatHelp — chat-help.de -> chat-help.com 301 yönlendirme (.htaccess)
 * Web kök .htaccess'ine kuralı GÜVENLE ekler (yedekler, varsa tekrar eklemez).
 * Sadece .de aynı sunucuya bakıyorsa gerekir. GoDaddy panel forwarding varsa GEREKMEZ.
 * KULLANIM: chat-help.com/chat/make-redirect.php   (ve gerekirse ?root=1)
 */
header('Content-Type: text/plain; charset=UTF-8');

$rule = "# ChatHelp: chat-help.de -> chat-help.com (301)\n"
      . "<IfModule mod_rewrite.c>\n"
      . "RewriteEngine On\n"
      . "RewriteCond %{HTTP_HOST} (^|\\.)chat-help\\.de\$ [NC]\n"
      . "RewriteRule ^(.*)\$ https://chat-help.com/\$1 [R=301,L]\n"
      . "</IfModule>\n\n";

// Hedef: web kök (html/) ve /chat — ikisine de koy (hangisi erişilebilirse)
$targets = [ dirname(__DIR__) . '/.htaccess', __DIR__ . '/.htaccess' ];

echo "ChatHelp — .de -> .com yönlendirme\n==================================\n\n";
foreach ($targets as $ht) {
    $dir = dirname($ht);
    if (!is_writable($dir) && !(file_exists($ht) && is_writable($ht))) {
        echo "  ⚠️  yazılamıyor: $ht  (izin yok — panel forwarding kullan)\n";
        continue;
    }
    $cur = file_exists($ht) ? (file_get_contents($ht) ?: '') : '';
    if (strpos($cur, 'chat-help.de') !== false) {
        echo "  ✓ zaten var: $ht\n";
        continue;
    }
    if ($cur !== '') file_put_contents($ht . '.bak-' . date('Ymd-His'), $cur); // yedek
    if (file_put_contents($ht, $rule . $cur) !== false) {
        echo "  ✓ kural eklendi: $ht\n";
    } else {
        echo "  ✗ yazılamadı: $ht\n";
    }
}

echo "\nTest: tarayıcıda  https://chat-help.de/chat  -> chat-help.com/chat'e atmalı.\n";
echo "Olmadıysa: chat-help.de muhtemelen aynı sunucuya bakmıyor -> GoDaddy panel forwarding kullan.\n";
echo "SİL: rm make-redirect.php\n";
