<?php
/**
 * VESTRA — IndexNow gunluk bildirimi (cron / CLI).
 *
 * Son kosudan beri eklenen/degisen ilanlari (+ marka/kategori sayfalari) ve yeni
 * journal yazilarini Bing'e bildirir; ChatGPT aramasi buyuk olcude o dizini
 * kullaniyor (inc/indexnow.php). Degisen yoksa hicbir sey gondermez.
 *
 * Kullanim: php cron_indexnow.php [--dry-run] [--all]
 *   --all     sitemap'in TAMAMI (ilk kurulumda bir kez)
 *   --dry-run gonderilecekleri yazar, gondermez, durumu degistirmez
 * Zamanlama: SUNUCU crontab'i (07:30 UTC), deploy-vestra.yml idempotent kurar.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/seo.php';
require_once __DIR__.'/inc/journal.php';
require_once __DIR__.'/inc/indexnow.php';

$argv = $argv ?? [];
$DRY = in_array('--dry-run', $argv, true);
$ALL = in_array('--all', $argv, true);
$stateF = vestra_data_dir().'/indexnow_state.json';
$state  = is_readable($stateF) ? (json_decode((string)file_get_contents($stateF), true) ?: []) : [];
$now    = time();
/* Ilk kosu (durum yok): son 2 gun. Sonraki kosular: son basarili kosudan beri, bir
   saatlik ortusmeyle -- cron dakikasi kaysa da arada kalan ilan kacmasin. */
$since  = (int)($state['last_run'] ?? 0) ?: ($now - 2 * 86400);
$urls   = $ALL ? vestra_indexnow_all_urls() : vestra_indexnow_changed_urls($since - 3600);

echo '['.date('c').'] indexnow: '.($ALL ? 'TUM sitemap' : 'degisen (son kosu '.date('Y-m-d H:i', $since).')').': '.count($urls)." adres\n";
$save = function (array $st) use ($stateF): void { @file_put_contents($stateF, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX); };
if (!$urls) {
    if (!$DRY) { $state['last_run'] = $now; $save($state); }
    echo "  gonderilecek degisiklik yok\n"; exit(0);
}
if ($DRY) {
    foreach (array_slice($urls, 0, 20) as $u) echo "  $u\n";
    if (count($urls) > 20) echo "  ... (+".(count($urls) - 20).")\n";
    echo "KURU KOSU -- gonderilmedi, durum degismedi\n"; exit(0);
}
$r = vestra_indexnow_submit($urls);
echo "  gonderildi: {$r['sent']} | HTTP {$r['code']}".($r['error'] !== '' ? " | {$r['error']}" : '').' -> '.($r['ok'] ? 'TAMAM' : 'HATA')."\n";
if ($r['ok']) {
    $state['last_run'] = $now; $state['last_sent'] = $r['sent']; $state['last_code'] = $r['code'];
    if ($ALL) $state['all_sent_at'] = date('c', $now);
    $save($state);
}
exit($r['ok'] ? 0 : 1);
