<?php
/**
 * ChatHelp — Instagram Login token'ını yeniler (~60 gün).
 * Instagram Login akışında yenileme APP SECRET GEREKTİRMEZ (graph.instagram.com).
 * Ayda bir cron ile çalıştır; süresi dolmadan tazeler. (Token en az 24 saatlik olmalı.)
 */
$cfg = require __DIR__ . '/config.php';

$tok = trim(@file_get_contents($cfg['token_file']));
if (!$tok) { fwrite(STDERR, "token.txt eksik\n"); exit(1); }

$url = 'https://graph.instagram.com/refresh_access_token?' . http_build_query([
    'grant_type'   => 'ig_refresh_token',
    'access_token' => $tok,
]);

$j = json_decode(@file_get_contents($url), true);
if (!empty($j['access_token'])) {
    file_put_contents($cfg['token_file'], $j['access_token']);
    @chmod($cfg['token_file'], 0600);
    echo "token yenilendi (" . date('c') . "), " . ($j['expires_in'] ?? '?') . " sn geçerli\n";
} else {
    fwrite(STDERR, "yenileme başarısız: " . json_encode($j) . "\n");
    exit(1);
}
