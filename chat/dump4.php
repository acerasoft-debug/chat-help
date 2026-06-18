<?php
/**
 * ChatHelp — dump4 (SADECE OKUR): doGenerate'in AI çağrısı (foto backend için)
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump4.php"
 * Çıktıyı bana gönder. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$api = @file_get_contents(__DIR__ . '/api.php');
if ($api === false) exit("api.php yok\n");

echo "### doGenerate TAM (AI çağrısı dahil) ###\n";
$p = strpos($api, 'function doGenerate');
echo ($p === false ? "(doGenerate bulunamadı)\n" : substr($api, $p, 5200)) . "\n";

echo "\n\n### AI çağrı fonksiyon adı (anthropic/x-api-key/curl) ###\n";
foreach (['anthropic.com', 'x-api-key', 'api.deepseek.com', 'function callClaude', 'function ai', 'function ask', 'function llm', 'CLAUDE_KEY', 'function callAI', 'function anthropic'] as $kw) {
    $q = strpos($api, $kw);
    echo "  '$kw' -> " . ($q === false ? 'yok' : "VAR @".$q) . "\n";
}

echo "\n### x-api-key çevresi (Claude çağrı bloğu) ###\n";
$q = strpos($api, 'x-api-key');
echo ($q === false ? "(yok)\n" : substr($api, max(0,$q-400), 900)) . "\n";

echo "\n=== BİTTİ. SİL: rm dump4.php ===\n";
