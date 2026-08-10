<?php
/**
 * Kararı gözle doğrula — CLI
 *   php tools/decision-sheet.php RED-renk 14 0
 *   php tools/decision-sheet.php kabul    14 0   (renk payı düşükten)
 * Solda ürünün MEVCUT karesi (çerçeveli), sağında aday. Etikette karar.
 * Eşiği veriye bakarak seçtik; bu araç seçimin doğru olduğunu gösteriyor.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$want = $argv[1] ?? 'RED-renk';
$take = (int)($argv[2] ?? 14);
$skip = (int)($argv[3] ?? 0);

$csv = shell_exec('php ' . escapeshellarg(__DIR__ . '/check-attach-colour.php') . ' --csv');
$lines = array_filter(explode("\n", (string)$csv));
$head = str_getcsv((string)array_shift($lines));
$rows = [];
foreach ($lines as $l) { $c = str_getcsv($l); if (count($c) === count($head)) $rows[] = array_combine($head, $c); }

$sel = array_values(array_filter($rows, fn($r) => $want === 'RED' ? str_starts_with($r['karar'], 'RED') : $r['karar'] === $want));
usort($sel, fn($a, $b) => (float)$a['renk_payi'] <=> (float)$b['renk_payi']);
$sel = array_slice($sel, $skip, $take);

$catalog = [];
foreach (vr_catalog() as $p) $catalog[(string)$p['id']] = $p;
$base = dirname(__DIR__) . '/';

$CELL = 165; $GAP = 5;
$W = 2 * ($CELL + $GAP) + $GAP + 330;
$H = max(1, count($sel)) * ($CELL + $GAP) + $GAP;
$im = imagecreatetruecolor($W, $H);
imagefill($im, 0, 0, imagecolorallocate($im, 252, 251, 249));
$ink = imagecolorallocate($im, 15, 15, 15);
$red = imagecolorallocate($im, 185, 35, 35);
$grn = imagecolorallocate($im, 20, 110, 60);

$y = $GAP;
foreach ($sel as $r) {
    $p = $catalog[$r['urun']] ?? null;
    $ref = '';
    if ($p) foreach ((array)vr_product_gallery($p) as $g) {
        $u = is_array($g) ? ($g['src'] ?? '') : $g;
        if (is_string($u) && $u !== '') { $ref = ltrim($u, '/'); break; }
    }
    $x = $GAP;
    foreach ([$ref, $r['dosya']] as $k => $f) {
        $src = @imagecreatefromstring((string)@file_get_contents($base . $f));
        if ($src) {
            $sw = imagesx($src); $sh = imagesy($src); $sc = min($CELL / $sw, $CELL / $sh);
            imagecopyresampled($im, $src, $x + (int)(($CELL - $sw * $sc) / 2), $y + (int)(($CELL - $sh * $sc) / 2),
                               0, 0, (int)($sw * $sc), (int)($sh * $sc), $sw, $sh);
            imagedestroy($src);
        }
        if ($k === 0) imagerectangle($im, $x, $y, $x + $CELL, $y + $CELL, $ink);
        $x += $CELL + $GAP;
    }
    $c = str_starts_with($r['karar'], 'RED') ? $red : $grn;
    imagestring($im, 5, $x + 8, $y + 6,  $r['karar'], $c);
    imagestring($im, 3, $x + 8, $y + 28, 'renk payi ' . round((float)$r['renk_payi'] * 100) . '%', $ink);
    imagestring($im, 3, $x + 8, $y + 44, substr((string)($p['brand'] ?? '?'), 0, 24), $ink);
    imagestring($im, 3, $x + 8, $y + 60, substr($r['urun'], 0, 32), $ink);
    $y += $CELL + $GAP;
}
$out = sprintf('%s/karar-%s-%d.jpg', sys_get_temp_dir(), preg_replace('/[^a-z]/i', '', $want), $skip);
imagejpeg($im, $out, 84);
echo "$out\n";
