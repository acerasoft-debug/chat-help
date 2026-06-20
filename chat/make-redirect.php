<?php
/**
 * ChatHelp — chat-help.de -> chat-help.com/chat (301) .htaccess
 *  • chat-help.de/chat/...  -> chat-help.com/chat/...  (path korunur)
 *  • chat-help.de (kök/diğer) -> chat-help.com/chat
 * Web köküne (html/.htaccess) güvenle ekler: yedekler, varsa tekrar eklemez,
 * kuralı en başa koyar (sadece .de host'unu etkiler, .com'a dokunmaz).
 * Sadece .de aynı sunucuya bakıyorsa gerekir. GoDaddy panel forwarding varsa GEREKMEZ.
 * KULLANIM: chat-help.com/chat/make-redirect.php
 */
header('Content-Type: text/plain; charset=UTF-8');

$rule = "# ChatHelp: chat-help.de -> chat-help.com/chat (301)\n"
      . "<IfModule mod_rewrite.c>\n"
      . "RewriteEngine On\n"
      . "RewriteCond %{HTTP_HOST} (^|\\.)chat-help\\.de\$ [NC]\n"
      . "RewriteRule ^chat(/.*)?\$ https://chat-help.com/chat\$1 [R=301,L]\n"
      . "RewriteCond %{HTTP_HOST} (^|\\.)chat-help\\.de\$ [NC]\n"
      . "RewriteRule ^.*\$ https://chat-help.com/chat [R=301,L]\n"
      . "</IfModule>\n\n";

echo "ChatHelp — .de -> .com/chat yönlendirme\n=======================================\n\n";

$ht = dirname(__DIR__) . '/.htaccess';   // web kök: html/.htaccess
$dir = dirname($ht);

if (!is_writable($dir) && !(file_exists($ht) && is_writable($ht))) {
    echo "  ⚠️  Web köküne yazılamıyor: $ht\n";
    echo "      -> GoDaddy panel forwarding kullan (https://chat-help.com/chat, 301).\n";
    exit;
}
$cur = file_exists($ht) ? (file_get_contents($ht) ?: '') : '';
if (strpos($cur, 'chat-help.de -> chat-help.com/chat') !== false) {
    echo "  ✓ Kural zaten var: $ht\n";
} else {
    if ($cur !== '') { file_put_contents($ht . '.bak-' . date('Ymd-His'), $cur); echo "  • Mevcut .htaccess yedeklendi.\n"; }
    if (file_put_contents($ht, $rule . $cur) !== false) echo "  ✓ Kural eklendi (en başa): $ht\n";
    else echo "  ✗ Yazılamadı: $ht\n";
}

echo "\nTEST (tarayıcıda):\n";
echo "  https://chat-help.de         -> https://chat-help.com/chat olmalı\n";
echo "  https://chat-help.de/chat    -> https://chat-help.com/chat olmalı\n";
echo "\nYönlenmiyorsa: chat-help.de bu sunucuya bakmıyor demektir -> GoDaddy panel forwarding.\n";
echo "SİL: rm make-redirect.php\n";
