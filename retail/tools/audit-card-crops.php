<?php
/**
 * Otomatik kart kesimlerinin gözle kontrolü — SALT OKUNUR
 *   php tools/audit-card-crops.php [--root=DIR]
 * data/face-crops.json'daki "auto" kesimleri kaynağıyla yan yana kolaja
 * dizer; kolajlar base64 JPEG olarak JSON'da döner (diag/tool altına düşer).
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
require_once $root . '/inc/uploads.php';
$doc = vr_doc_root();
$rows = [];
foreach ((array)vr_store_read('face-crops.json', []) as $id => $c) if (!empty($c['auto'])) $rows[] = [$id, (string)$c['from'], (string)$c['to']];
$sheets = []; $rowH = 150;
foreach (array_chunk($rows, 14) as $ch) {
    $o = imagecreatetruecolor(1000, $rowH * count($ch)); imagefill($o, 0, 0, 0xffffff);
    foreach ($ch as $i => [$id, $from, $to]) {
        $s = @imagecreatefromstring((string)@file_get_contents($doc . $from));
        if ($s) { $sc = min(640 / imagesx($s), ($rowH - 8) / imagesy($s)); imagecopyresampled($o, $s, 0, $i * $rowH, 0, 0, (int)(imagesx($s) * $sc), (int)(imagesy($s) * $sc), imagesx($s), imagesy($s)); imagedestroy($s); }
        $c = @imagecreatefromstring((string)@file_get_contents($doc . $to));
        if ($c) { imagecopyresampled($o, $c, 660, $i * $rowH, 0, 0, 114, 142, imagesx($c), imagesy($c)); imagedestroy($c); }
        imagestring($o, 3, 785, $i * $rowH + 5, $id, 0xff0000);
    }
    ob_start(); imagejpeg($o, null, 72); $sheets[] = base64_encode((string)ob_get_clean()); imagedestroy($o);
}
echo json_encode(['count' => count($rows), 'ids' => array_column($rows, 0), 'sheets' => $sheets]), "\n";
