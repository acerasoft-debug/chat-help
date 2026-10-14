<?php
/**
 * Salt okunur denetim: istatistik kaydı sunucuda çalışıyor mu?
 *   php tools/audit-stats.php [--root=DIR]
 *
 * Çıktı JSON: data/stats dosyaları ve satır sayıları, son kaydın alanları
 * (IP ve tarayıcı kimliği YAZILMAZ), engel listesi ve denetim günlüğü
 * boyutu, yönetici dosyasının varlığı (içeriği değil). Hiçbir şey yazmaz.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/view.php';

$dir = vr_stats_dir();
$files = [];
foreach (glob($dir . '/*') ?: [] as $f) {
    $n = 0;
    if (str_ends_with($f, '.jsonl')) { $h = fopen($f, 'r'); while ($h && fgets($h) !== false) $n++; if ($h) fclose($h); }
    $files[basename($f)] = ['bytes' => filesize($f), 'lines' => $n, 'mtime' => date('c', (int)filemtime($f))];
}
$last = null;
$today = $dir . '/hits-' . gmdate('Y-m-d') . '.jsonl';
if (is_readable($today)) {
    $lines = file($today, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $r = json_decode((string)end($lines), true);
    if (is_array($r)) { unset($r['ip'], $r['ua'], $r['s']); $last = $r; }
}
$day = vr_stats_day(gmdate('Y-m-d'));
echo json_encode([
    'data_dir'   => vr_data_dir(),
    'stats_dir'  => $dir,
    'writable'   => is_writable($dir),
    'files'      => $files,
    'last_row'   => $last,
    'today'      => ['pv' => $day['pv'], 'uv' => $day['uv'], 'ips' => $day['ips'], 'bots' => $day['bots'], 'cc' => $day['cc'], 'd' => $day['d'], 'p' => array_slice($day['p'], 0, 8, true)],
    'live_5min'  => count(vr_stats_live(300)),
    'blocklist'  => count(vr_ip_blocklist()),
    'audit_log'  => is_file(vr_data_dir() . '/admin-audit.log') ? filesize(vr_data_dir() . '/admin-audit.log') : 0,
    'admin_json' => is_file(vr_data_dir() . '/admin.json'),
    'secret'     => is_file(vr_data_dir() . '/stats-secret.txt'),
    'flash_deal' => (vr_store_read('flash-deal.json', [])['id'] ?? ''),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
