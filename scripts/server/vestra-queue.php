<?php
/* Sunucuda (CLI) çalışır — find-customers-queue.yml (10 dakikada bir) tarafından.
   Paneldeki "Aramayı başlat" düğmesi GitHub token'ı yoksa isteği yalnızca
   data/finder_runs.json'a status=requested olarak yazar. Bu betik sıradakini alır;
   workflow onu GitHub'ın kendi yerleşik yetkisiyle (GITHUB_TOKEN) başlatır. Böylece
   panelde hiçbir GitHub anahtarı gerekmez.
     php vestra-queue.php take            → sıradaki isteği "running" yapar, JSON basar ({} = boş)
     php vestra-queue.php fail <id> <not> → başlatılamadıysa kaydı "failed" yapar
   STDOUT'ta SADECE JSON (take) — uyarılar STDERR'e. */
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);
$home = getenv("HOME");
require $home."/public_html/inc/products.php";

$cmd = (string)($argv[1] ?? '');
$runs = vestra_read_json('finder_runs.json');
$now = time();

if ($cmd === 'take') {
  $pick = null; $changed = false;
  /* En ESKİ bekleyen istek önce (liste yeniden eskiye sıralı). */
  for ($i = count($runs) - 1; $i >= 0; $i--) {
    $r = $runs[$i];
    if (($r['status'] ?? '') !== 'requested' || !empty($r['dispatched_at'])) continue;
    $age = $now - (int)strtotime((string)($r['requested_at'] ?? ''));
    if ($age > 6 * 3600) {                                     // 6 saattir başlamadıysa bayat: kilidi tutmasın
      $runs[$i]['status'] = 'failed'; $runs[$i]['finished_at'] = date('c');
      $runs[$i]['notes'] = ['İstek 6 saat içinde başlatılamadı (sıra işi çalışmadı).']; $changed = true; continue;
    }
    if ($pick === null) $pick = $i;
  }
  /* Zaten çalışan bir arama varsa (95 dk içinde) yenisini başlatma: workflow'un
     concurrency grubu üçüncü isteği sessizce iptal ediyor. */
  foreach ($runs as $r) {
    $live = in_array(($r['status'] ?? ''), ['running', 'requested'], true) && !empty($r['dispatched_at']);   // token ile anında başlatılan da sayılır
    if ($live && $now - (int)strtotime((string)$r['dispatched_at']) < 95 * 60) { $pick = null; break; }
  }
  if ($pick === null) { if ($changed) vestra_write_json('finder_runs.json', $runs); echo "{}\n"; exit(0); }
  $runs[$pick]['status'] = 'running';
  $runs[$pick]['dispatched_at'] = date('c');
  $runs[$pick]['via'] = 'queue';
  vestra_write_json('finder_runs.json', $runs);
  $r = $runs[$pick];
  echo json_encode(['id' => (string)$r['id'], 'owner' => (string)($r['owner'] ?? ''), 'params' => (array)($r['params'] ?? [])],
                   JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), "\n";
  exit(0);
}

if ($cmd === 'fail') {
  $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($argv[2] ?? ''));
  $note = mb_substr((string)($argv[3] ?? 'başlatılamadı'), 0, 200);
  foreach ($runs as &$r) if (($r['id'] ?? '') === $id) { $r['status'] = 'failed'; $r['finished_at'] = date('c'); $r['notes'] = ['Arama başlatılamadı: '.$note]; }
  unset($r);
  vestra_write_json('finder_runs.json', $runs);
  echo "ok\n"; exit(0);
}

fwrite(STDERR, "kullanim: php vestra-queue.php take | fail <id> <not>\n");
exit(2);
