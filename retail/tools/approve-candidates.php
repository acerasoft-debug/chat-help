<?php
/**
 * Onaylanan adayları ürüne bağla — CLI
 * ====================================
 *   php tools/approve-candidates.php --list
 *   php tools/approve-candidates.php <ürün-id> 1 3        (1. ve 3. adayı al)
 *   php tools/approve-candidates.php <ürün-id> none       (hiçbirini alma, kaydı temizle)
 *   php tools/approve-candidates.php --restore
 *
 * NEDEN AYRI BİR ADIM
 * -------------------
 * İndirme (fetch-photo-candidates.php) runner'da çalışıyor ve gözü yok.
 * Model kodu araması doğru sayfayı çoğu zaman buluyor ama aynı kodun beş
 * rengi, kadın/erkek bedeni, farklı sezonu olabiliyor. Onay bu yüzden elle:
 * uploads/candidates/_sheets/<id>.jpg açılır, solda eldeki kare, sağda
 * adaylar; hangileri AYNI parçaysa numaraları buraya yazılır.
 *
 * Onaylanan dosya uploads/licensed/<marka>/ altına taşınıyor — kaynağı belli
 * olsun diye ayrı klasör. Onaylanmayanlar candidates/ altında kalıyor ve
 * yayımlanmıyor (kimse oradan okumuyor).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "Nur über die Kommandozeile.\n"; exit(1); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$args   = array_slice($argv, 1);
$root   = vr_doc_root();
$file   = vr_data_dir() . '/retail-imported.json';
$ledger = vr_data_dir() . '/approved-photos.json';

$cand = (array)vr_store_read('photo-candidates.json', []);

if (in_array('--restore', $args, true)) {
    $log = json_decode((string)@file_get_contents($ledger), true);
    if (!is_array($log) || !$log) { echo "Kayıt yok.\n"; exit(0); }
    $rows = json_decode((string)@file_get_contents($file), true);
    if (!is_array($rows)) { echo "HATA: retail-imported.json okunamadı.\n"; exit(1); }
    $n = 0;
    foreach ($rows as $k => $r) {
        $id = (string)($r['id'] ?? '');
        if ($id === '' || empty($log[$id])) continue;
        $rows[$k]['images'] = $log[$id]; $n++;
    }
    file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    @unlink($ledger);
    printf("%d ürün geri alındı.\n", $n);
    exit(0);
}

if (!$cand) { echo "data/photo-candidates.json yok — önce indirme çalışsın.\n"; exit(1); }

if (!$args || in_array('--list', $args, true)) {
    printf("Bekleyen aday: %d ürün\n%s\n", count($cand), str_repeat('=', 60));
    foreach ($cand as $pid => $row) {
        $p = vr_product((string)$pid);
        printf("\n%s  %s\n", $pid, $p ? $p['brand'] . ' ' . vr_card_name($p) : '(katalogda yok)');
        printf("  kolaj : uploads/candidates/_sheets/%s.jpg\n", $pid);
        foreach ($row['candidates'] as $i => $c) {
            printf("  %d) %s\n", $i + 1, $c['file']);
        }
    }
    echo "\nOnay:  php tools/approve-candidates.php <id> 1 2\n";
    echo "Ret :  php tools/approve-candidates.php <id> none\n";
    exit(0);
}

$pid = array_shift($args);
if (!isset($cand[$pid])) { echo "Bu id için aday yok: {$pid}\n"; exit(1); }
$p = vr_product((string)$pid);
if ($p === null) { echo "Katalogda yok: {$pid}\n"; exit(1); }

$picks = [];
if ($args !== ['none']) {
    foreach ($args as $a) {
        $i = (int)$a - 1;
        if (!isset($cand[$pid]['candidates'][$i])) { echo "Aday {$a} yok.\n"; exit(1); }
        $picks[] = $cand[$pid]['candidates'][$i]['file'];
    }
}

// ---- onaylananları licensed/ altına taşı
$brandDir = 'licensed/' . (preg_replace('/[^a-z0-9]+/', '-', mb_strtolower((string)$p['brand'])) ?: 'other');
$moved = [];
foreach ($picks as $rel) {
    $abs = $root . $rel;
    if (!is_file($abs)) { echo "Dosya yok: {$rel}\n"; continue; }
    $dst = '/uploads/' . $brandDir . '/' . basename($rel);
    $dstAbs = $root . $dst;
    if (!is_dir(dirname($dstAbs))) @mkdir(dirname($dstAbs), 0755, true);
    if (@rename($abs, $dstAbs)) { @chmod($dstAbs, 0644); $moved[] = $dst; }
}

// ---- ürün kaydına ekle
if ($moved) {
    $rows = json_decode((string)@file_get_contents($file), true);
    if (!is_array($rows)) { echo "HATA: retail-imported.json okunamadı.\n"; exit(1); }
    $log = json_decode((string)@file_get_contents($ledger), true);
    if (!is_array($log)) $log = [];

    $hit = false;
    foreach ($rows as $k => $r) {
        if ((string)($r['id'] ?? '') !== (string)$pid) continue;
        $imgs = array_values(array_filter((array)($r['images'] ?? []), 'strlen'));
        if (!isset($log[$pid])) $log[$pid] = $imgs;
        foreach ($moved as $m) if (!in_array($m, $imgs, true)) $imgs[] = $m;
        $rows[$k]['images'] = $imgs;
        $hit = true;
        break;
    }
    if (!$hit) { echo "UYARI: {$pid} retail-imported.json içinde yok, kayıt güncellenmedi.\n"; }
    else {
        file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        file_put_contents($ledger, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

// ---- bu ürünü bekleyenlerden düş
unset($cand[$pid]);
vr_store_write('photo-candidates.json', $cand);

printf("%s: %d kare eklendi, %d ürün bekliyor.\n", $pid, count($moved), count($cand));
if ($moved) echo "Ardından: php tools/index-photo-shape.php && php tools/index-photo-quality.php\n";
