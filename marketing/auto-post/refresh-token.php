<?php
/**
 * ChatHelp — uzun ömürlü token'ı yeniler (~60 gün).
 * Ayda bir cron ile çalıştır; süresi dolmadan tazeler.
 */
$cfg = require __DIR__ . '/config.php';

$tok    = trim(@file_get_contents($cfg['token_file']));
$secret = $cfg['app_secret'] ?: trim(@file_get_contents($cfg['secret_file']));
if (!$tok || !$secret) { fwrite(STDERR, "token.txt veya app_secret.txt eksik\n"); exit(1); }

$url = "https://graph.facebook.com/{$cfg['graph_version']}/oauth/access_token?" . http_build_query([
    'grant_type'        => 'fb_exchange_token',
    'client_id'         => $cfg['app_id'],
    'client_secret'     => $secret,
    'fb_exchange_token' => $tok,
]);

$j = json_decode(@file_get_contents($url), true);
if (!empty($j['access_token'])) {
    file_put_contents($cfg['token_file'], $j['access_token']);
    @chmod($cfg['token_file'], 0600);
    echo "token yenilendi (" . date('c') . ")\n";
} else {
    fwrite(STDERR, "yenileme başarısız: " . json_encode($j) . "\n");
    exit(1);
}
