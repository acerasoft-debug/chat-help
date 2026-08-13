<?php
/**
 * ChatHelp — günlük cron girişi.
 * Verilen dil için konu bankasından SIRADAKI reel'i Instagram'a atar ve
 * durumu ilerletir (böylece her gün farklı konu gelir, banka bitince başa döner).
 *
 * Kullanım (cron):   php schedule.php de     (veya en, fr, tr, es, ru)
 */
require __DIR__ . '/ig-post.php';

$cfg  = ig_cfg();
$lang = $argv[1] ?? 'de';

$content = json_decode(@file_get_contents($cfg['content_file']), true);
if (!$content || empty($content['items'])) { fwrite(STDERR, "content.json okunamadı\n"); exit(1); }

// Bu dile ait yayınlanabilir öğeler
$items = array_values(array_filter($content['items'], function ($x) use ($lang) {
    return ($x['lang'] ?? '') === $lang && !empty($x['video']) && !empty($x['caption']);
}));
if (!$items) { fwrite(STDERR, "dil için içerik yok: $lang\n"); exit(1); }

$state = is_file($cfg['state_file']) ? json_decode(file_get_contents($cfg['state_file']), true) : [];

// Aynı gün ikinci kez atmayı engelle (cron yanlışlıkla iki kez tetiklenirse)
$today = date('Y-m-d');
if (!empty($cfg['guard_same_day']) && (($state['last_day'][$lang] ?? '') === $today)) {
    echo "zaten bugün atıldı: $lang\n"; exit(0);
}

$idx  = $state['idx'][$lang] ?? 0;
$item = $items[$idx % count($items)];   // sırayla döner

try {
    $mid = ig_publish_reel($item['video'], $item['caption']);
    $state['idx'][$lang]      = $idx + 1;
    $state['last_day'][$lang] = $today;
    file_put_contents($cfg['state_file'], json_encode($state, JSON_PRETTY_PRINT));
    echo "OK  $lang  ->  {$item['id']}  (media $mid)\n";
} catch (Exception $e) {
    ig_log($cfg, "HATA $lang [{$item['id']}]: " . $e->getMessage());
    fwrite(STDERR, "HATA: " . $e->getMessage() . "\n");
    exit(1);
}
