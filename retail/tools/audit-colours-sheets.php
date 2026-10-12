<?php
/**
 * Renk denetiminin kolajları — php tools/audit-colours-sheets.php audit.json srv/ out/
 * ===================================================================================
 * tools/audit-colours.php çıktısından göz kontrolü için kolaj üretir:
 *   chips-NN.jpg   rengi fotoğrafa bağlanmamış ürünler: üstte ürünün renk
 *                  listesi, altta her kare + ölçülen baskın renk + en yakın ad
 *   groups-NN.jpg  renk başına ayrı satır açılmış modeller: her satırın şerit
 *                  karesi, etiketi ve ölçülen rengi (yanlış etiket avı)
 * Yalnız komut satırı; sunucuya değil, denetim makinesinde çalışır.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
[, $json, $srv, $out] = $argv + [null, null, null, null];
if (!$json || !$srv || !$out) { fwrite(STDERR, "kullanım: audit.json srv/ out/\n"); exit(1); }
@mkdir($out, 0755, true);
$a = json_decode((string)file_get_contents($json), true);

function as_load(string $srv, string $rel): ?\GdImage
{
    $im = @imagecreatefromstring((string)@file_get_contents(rtrim($srv, '/') . $rel));
    return $im ?: null;
}

function as_put(\GdImage $s, ?\GdImage $im, int $x, int $y, int $w, int $h): void
{
    if (!$im) return;
    $sc = min($w / imagesx($im), $h / imagesy($im));
    $dw = (int)(imagesx($im) * $sc); $dh = (int)(imagesy($im) * $sc);
    imagecopyresampled($s, $im, $x + intdiv($w - $dw, 2), $y + intdiv($h - $dh, 2), 0, 0, $dw, $dh, imagesx($im), imagesy($im));
}

function as_hex(\GdImage $s, ?string $hex): int
{
    if (!$hex) return imagecolorallocate($s, 200, 200, 200);
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    return imagecolorallocate($s, $r, $g, $b);
}

// ---- chips: ürün başına bir satır, 6 ürün bir sayfa
$cw = 170; $ch = 212; $per = 6;
foreach (array_chunk($a['chips'] ?? [], $per) as $pi => $chunk) {
    $cols = 1; foreach ($chunk as $p) $cols = max($cols, min(10, count($p['images'])));
    $rowH = 34 + $ch + 34;
    $s = imagecreatetruecolor(max(900, $cols * ($cw + 6) + 6), count($chunk) * $rowH + 6);
    imagefill($s, 0, 0, imagecolorallocate($s, 255, 255, 255));
    $ink = imagecolorallocate($s, 0, 0, 0); $grey = imagecolorallocate($s, 110, 110, 110);
    foreach ($chunk as $ri => $p) {
        $y0 = 6 + $ri * $rowH;
        imagestring($s, 3, 6, $y0, substr($p['id'] . ' | ' . $p['name'], 0, 140), $ink);
        imagestring($s, 2, 6, $y0 + 16, substr('renkler: ' . implode(', ', $p['colours']) . ($p['reviewed'] ? '  (gozle dogrulanmis)' : ''), 0, 160), $grey);
        foreach (array_slice($p['images'], 0, 10) as $i => $m) {
            $x0 = 6 + $i * ($cw + 6); $y = $y0 + 34;
            as_put($s, as_load($srv, $m['img']), $x0, $y, $cw, $ch);
            imagefilledrectangle($s, $x0, $y + $ch + 4, $x0 + 22, $y + $ch + 26, as_hex($s, $m['dom']));
            imagerectangle($s, $x0, $y + $ch + 4, $x0 + 22, $y + $ch + 26, $grey);
            imagestring($s, 2, $x0 + 26, $y + $ch + 4, ($i + 1) . ' ' . $m['name'], $ink);
            imagestring($s, 1, $x0 + 26, $y + $ch + 17, substr(basename($m['img']), 0, 26), $grey);
        }
    }
    imagejpeg($s, sprintf('%s/chips-%02d.jpg', $out, $pi + 1), 82);
}

// ---- groups: model başına bir satır
$per = 6;
foreach (array_chunk($a['groups'] ?? [], $per) as $pi => $chunk) {
    $cols = 1; foreach ($chunk as $g) $cols = max($cols, min(10, count($g['rows'])));
    $rowH = 20 + $ch + 48;
    $s = imagecreatetruecolor(max(900, $cols * ($cw + 6) + 6), count($chunk) * $rowH + 6);
    imagefill($s, 0, 0, imagecolorallocate($s, 255, 255, 255));
    $ink = imagecolorallocate($s, 0, 0, 0); $grey = imagecolorallocate($s, 110, 110, 110); $red = imagecolorallocate($s, 200, 0, 0);
    foreach ($chunk as $ri => $g) {
        $y0 = 6 + $ri * $rowH;
        imagestring($s, 3, 6, $y0, substr($g['key'] . ($g['issues'] ? '  !! ' . implode(' ', $g['issues']) : ''), 0, 160), $g['issues'] ? $red : $ink);
        foreach (array_slice($g['rows'], 0, 10) as $i => $r) {
            $x0 = 6 + $i * ($cw + 6); $y = $y0 + 20;
            if ($r['real']) as_put($s, as_load($srv, $r['swatch']), $x0, $y, $cw, $ch);
            else imagestring($s, 3, $x0 + 10, $y + 90, 'FOTO YOK', $red);
            $m = $r['measured'] ?? null;
            imagefilledrectangle($s, $x0, $y + $ch + 4, $x0 + 18, $y + $ch + 22, as_hex($s, $m['dom'] ?? null));
            $lab = $r['colours'][0] ?? $r['variant_colour'];
            imagestring($s, 2, $x0 + 22, $y + $ch + 2, substr(($r['shown'] ? '' : '[gizli] ') . ($lab ?: '(etiket yok)'), 0, 24), $r['shown'] ? $ink : $red);
            imagestring($s, 1, $x0 + 22, $y + $ch + 16, substr('olcum: ' . ($m['name'] ?? '-') . ' st:' . $r['stock'], 0, 28), $grey);
            imagestring($s, 1, $x0, $y + $ch + 30, substr($r['id'], 0, 34), $grey);
        }
    }
    imagejpeg($s, sprintf('%s/groups-%02d.jpg', $out, $pi + 1), 82);
}
echo "kolajlar: ", count(glob("$out/*.jpg")), "\n";
