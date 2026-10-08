<?php
/* Sunucuda (CLI) çalışır — find-customers.yml'nin son adımı.
   Çalışmanın sonucunu data/finder_runs.json'a yazar; admin ve satıcı panelindeki
   "Web'den müşteri bul" kartı bu kaydı okur (durum, eklenen firmalar, rapor, link).
     php vestra-finder-report.php <id> <summary.json|-> <report.md|-> <run_url> <job_status> <dry> <owner_uid>
   Paneldeki istek kaydı (status=requested) varsa güncellenir; takvimli çalışma için
   yeni kayıt açılır. Son 60 kayıt tutulur. Hiçbir e-posta GÖNDERMEZ. */
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);
$home = getenv("HOME");
require       $home."/public_html/inc/products.php";

[$_, $id, $sumF, $repF, $runUrl, $jobStatus, $dry, $owner] = array_pad($argv, 8, '');
$id    = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$id);
$owner = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$owner);
if ($id === '') { fwrite(STDERR, "id gerekli\n"); exit(2); }

$sum = ($sumF !== '-' && is_readable($sumF)) ? json_decode((string)file_get_contents($sumF), true) : null;
$rep = ($repF !== '-' && is_readable($repF)) ? (string)file_get_contents($repF) : '';
if (!is_array($sum)) $sum = [];

$runs = vestra_read_json('finder_runs.json');
$idx = null;
foreach ($runs as $i => $r) if (($r['id'] ?? '') === $id) { $idx = $i; break; }
$rec = $idx !== null ? $runs[$idx] : ['id' => $id, 'owner' => $owner, 'requested_at' => date('c'), 'by' => 'cron'];

$ok = ($jobStatus === 'success') && $sum;
$rec['status']      = $ok ? 'done' : 'failed';
$rec['finished_at'] = date('c');
$rec['run_url']     = (string)$runUrl;
$rec['dry']         = ($dry === 'true');
$rec['added_count'] = (int)($sum['added_count'] ?? 0);
$rec['analyzed']    = (int)($sum['analyzed'] ?? 0);
$rec['candidates']  = (int)($sum['candidates'] ?? 0);
$rec['queries']     = (int)($sum['queries'] ?? 0);
$rec['added']       = array_slice((array)($sum['added'] ?? []), 0, 200);
$rec['no_email']    = array_slice((array)($sum['no_email'] ?? []), 0, 60);
$rec['excl']        = (array)($sum['excl'] ?? []);
$rec['notes']       = array_slice(array_map('strval', (array)($sum['notes'] ?? [])), 0, 10);
$rec['report_md']   = mb_substr($rep, 0, 60000);
if (!$ok && !$rec['notes']) $rec['notes'] = ['Çalışma tamamlanamadı (iş durumu: '.($jobStatus ?: '?').') — ayrıntı için GitHub Actions linkine bakın.'];

if ($idx !== null) $runs[$idx] = $rec; else array_unshift($runs, $rec);
usort($runs, fn($a, $b) => strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? '')));
$runs = array_slice($runs, 0, 60);
vestra_write_json('finder_runs.json', $runs);
echo "rapor kaydedildi: {$id} | durum={$rec['status']} | eklenen={$rec['added_count']}".($rec['dry'] ? ' (DRY-RUN)' : '')."\n";
