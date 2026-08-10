<?php
/**
 * Renk mesafesi eşiğini gözle kalibre et — CLI
 *   php tools/calib-sheet.php <baslangic> <adet>
 * check-attach-colour.php'nin sıralamasındaki dilimi kolaja basar; her
 * satırın başında mesafe yazar. Eşiği tahminle değil, nereden sonra
 * gerçekten yanlış ürün başlıyor diye BAKARAK seçmek için.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';
require_once __DIR__ . '/lib-photo-match.php';

$from = (int)($argv[1] ?? 0);
$take = (int)($argv[2] ?? 16);

$csv = shell_exec('php ' . escapeshellarg(__DIR__ . '/check-attach-colour.php') . ' --csv');
$lines = array_filter(explode("\n", (string)$csv));
array_shift($lines);
$rows = [];
foreach ($lines as $l) { $c = str_getcsv($l); if (count($c) >= 4) $rows[] = $c; }
$slice = array_slice($rows, $from, $take);

$catalog = [];
foreach (vr_catalog() as $p) $catalog[(string)$p['id']] = $p;
$base = dirname(__DIR__) . '/';

$CELL = 170; $GAP = 6;
$W = 3 * ($CELL + $GAP) + $GAP + 320;
$H = count($slice) * ($CELL + $GAP) + $GAP;
$im = imagecreatetruecolor($W, $H);
imagefill($im, 0, 0, imagecolorallocate($im, 252, 251, 249));
$ink = imagecolorallocate($im, 15, 15, 15);
$red = imagecolorallocate($im, 190, 40, 40);

$y = $GAP;
foreach ($slice as $r) {
    [$id, $rel, $dist, $frac] = $r;
    $p = $catalog[$id] ?? null;
    $ref = '';
    if ($p) foreach ((array)vr_product_gallery($p) as $g) {
        $u = is_array($g) ? ($g['src'] ?? '') : $g;
        if (is_string($u) && $u !== '') { $ref = ltrim($u, '/'); break; }
    }
    $x = $GAP;
    foreach ([$ref, $rel] as $k => $f) {
        $src = @imagecreatefromstring((string)@file_get_contents($base . $f));
        if ($src) {
            $sw = imagesx($src); $sh = imagesy($src);
            $sc = min($CELL / $sw, $CELL / $sh);
            imagecopyresampled($im, $src, $x + (int)(($CELL - $sw * $sc) / 2), $y + (int)(($CELL - $sh * $sc) / 2),
                               0, 0, (int)($sw * $sc), (int)($sh * $sc), $sw, $sh);
            imagedestroy($src);
        }
        if ($k === 0) imagerectangle($im, $x, $y, $x + $CELL, $y + $CELL, $ink);
        $x += $CELL + $GAP;
    }
    $lbl = sprintf('mesafe %s', $dist);
    imagestring($im, 5, $x + 8, $y + 8, $lbl, (float)$dist > 250 ? $red : $ink);
    imagestring($im, 3, $x + 8, $y + 30, substr((string)($p['brand'] ?? '?'), 0, 22), $ink);
    imagestring($im, 3, $x + 8, $y + 46, substr($id, 0, 34), $ink);
    imagestring($im, 3, $x + 8, $y + 62, 'ozne ' . round((float)$frac * 100) . '%', $ink);
    $y += $CELL + $GAP;
}
$out = sprintf('%s/calib-%03d.jpg', sys_get_temp_dir(), $from);
imagejpeg($im, $out, 84);
echo "$out\n";
