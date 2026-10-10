<?php
/**
 * Ürün rengini fotoğraftan çıkar — CLI
 * ====================================
 *   php tools/detect-colours.php [--out=data/product-colours.auto.json] [--sheets=DIR] [--only-missing]
 *
 * NİYE
 * ----
 * Renk seçimi ürün sayfasında görünsün diye her ürünün rengini bilmemiz
 * lazım. Üç kaynak var, bu sırayla:
 *   1) Vestra satırının "colors" alanı (["White"] gibi) — en güvenilir
 *   2) ürün adındaki ek ("… — Navy", "…, Black")
 *   3) fotoğraf — bu betik
 * Betik yalnızca ilk ikisinde rengi OLMAYAN ürünlere bakar ve sonucu ayrı
 * bir dosyaya yazar. O dosya doğrudan vitrine gitmez: kontrol kolajları
 * (--sheets) gözle incelenir, yanlışlar data/product-colours.json'a elle
 * düzeltilerek yazılır. Vitrin yalnızca o dosyayı okur.
 *
 * NASIL ÖLÇÜYOR
 * -------------
 * lib-photo-colour.php'nin özne ayırması: kenar pikselleri zemin sayılıyor,
 * zeminden belirgin farklı pikseller giysi. Giysinin baskın rengi isimli bir
 * renge çevriliyor (HSL eşikleri). Beyaz zeminde beyaz giysi ayrılamıyor —
 * özne çok küçük ve zemin açıksa sonuç "White", kolajda ayrıca işaretleniyor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/uploads.php';
require_once __DIR__ . '/lib-photo-colour.php';

$opt = getopt('', ['out::', 'sheets::', 'only-missing', 'ids::']);
$out    = (string)($opt['out'] ?? (VR_ROOT . '/data/product-colours.auto.json'));
$sheets = (string)($opt['sheets'] ?? '');
$only   = isset($opt['only-missing']);
$idList = isset($opt['ids']) ? array_filter(explode(',', (string)$opt['ids'])) : [];

/** RGB → isimli renk. Eşikler HSL'de; giyim renk adları (Vestra'nın kullandığı adlar). */
function dc_name(array $rgb): string
{
    [$r, $g, $b] = array_map(fn($v) => $v / 255, $rgb);
    $mx = max($r, $g, $b); $mn = min($r, $g, $b);
    $l = ($mx + $mn) / 2;
    $d = $mx - $mn;
    $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));
    $h = 0.0;
    if ($d != 0) {
        if ($mx == $r)     $h = 60 * fmod((($g - $b) / $d), 6);
        elseif ($mx == $g) $h = 60 * ((($b - $r) / $d) + 2);
        else               $h = 60 * ((($r - $g) / $d) + 4);
    }
    if ($h < 0) $h += 360;

    if ($l < 0.16) return ($s > 0.35 && $h >= 200 && $h < 260) ? 'Navy' : 'Black';
    if ($s < 0.12) {
        if ($l > 0.86) return 'White';
        if ($l > 0.72) return 'Light Grey';
        if ($l < 0.30) return 'Charcoal';
        return 'Grey';
    }
    if ($l > 0.80 && $s < 0.45) {
        if ($h >= 20 && $h < 60) return 'Cream';
        if ($h >= 180 && $h < 260) return 'Light Blue';
        if ($h >= 300 || $h < 20) return 'Pink';
        return 'White';
    }
    if ($h < 12 || $h >= 345) return $l < 0.32 ? 'Burgundy' : ($l > 0.68 ? 'Pink' : 'Red');
    if ($h < 40)  return $l < 0.35 ? 'Brown' : (($s < 0.45 || $l > 0.62) ? 'Beige' : 'Orange');
    if ($h < 68)  return ($s < 0.40 || $l < 0.38) ? 'Khaki' : 'Yellow';
    if ($h < 165) return ($s < 0.35 && $l < 0.45) ? 'Khaki' : 'Green';
    if ($h < 200) return $l > 0.55 ? 'Light Blue' : 'Teal';
    if ($h < 255) return $l < 0.30 ? 'Navy' : ($l > 0.62 ? 'Light Blue' : 'Blue');
    if ($h < 300) return 'Purple';
    return $l > 0.62 ? 'Pink' : 'Fuchsia';
}

$cat = vr_catalog();
$res = [];
$rows = [];
foreach ($cat as $id => $p) {
    if ($idList && !in_array($id, $idList, true)) continue;
    $known = vr_product_colours($p, false);
    if ($only && $known) continue;

    $img = vr_swatch_image($p);
    $abs = str_starts_with($img, '/uploads/') ? vr_doc_root() . $img : '';
    if ($abs === '' || !is_file($abs)) { $rows[] = [$id, $p, $img, null, '?', 0.0, 'foto yok']; continue; }

    $px = cac_pixels($abs);
    if (!$px) { $rows[] = [$id, $p, $img, null, '?', 0.0, 'okunamadı']; continue; }
    [$pix, $frac] = $px;

    // Zemin rengi: kenar ortalaması (cac_pixels'ın kullandığıyla aynı yaklaşım).
    $note = '';
    if ($frac < 0.06) {
        $name = 'White'; $dom = [245, 245, 245]; $note = 'özne seçilemedi → açık zeminde açık giysi';
    } else {
        $dom = cac_dominant($pix);
        $name = dc_name($dom);
    }
    $res[$id] = ['colours' => [$name], 'hex' => sprintf('#%02x%02x%02x', ...$dom), 'src' => 'photo', 'subject' => round($frac, 3)];
    $rows[] = [$id, $p, $img, $dom, $name, $frac, $note];
}

file_put_contents($out, json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, count($res) . " ürünün rengi ölçüldü → $out\n");

// ---------------------------------------------------------------- kolajlar
if ($sheets !== '') {
    @mkdir($sheets, 0755, true);
    $per = 30; $cols = 6; $tw = 200; $th = 250; $lab = 46;
    $chunks = array_chunk($rows, $per);
    foreach ($chunks as $si => $chunk) {
        $rowsN = (int)ceil(count($chunk) / $cols);
        $im = imagecreatetruecolor($cols * $tw, $rowsN * ($th + $lab));
        $bg = imagecolorallocate($im, 255, 255, 255); imagefill($im, 0, 0, $bg);
        $ink = imagecolorallocate($im, 20, 20, 20); $warn = imagecolorallocate($im, 200, 0, 0);
        foreach ($chunk as $k => [$id, $p, $img, $dom, $name, $frac, $note]) {
            $x = ($k % $cols) * $tw; $y = intdiv($k, $cols) * ($th + $lab);
            $abs = str_starts_with((string)$img, '/uploads/') ? vr_doc_root() . $img : '';
            if ($abs !== '' && is_file($abs) && ($src = @imagecreatefromstring((string)file_get_contents($abs)))) {
                $sw = imagesx($src); $sh = imagesy($src); $sc = min(($tw - 8) / $sw, ($th - 8) / $sh);
                $dw = (int)($sw * $sc); $dh = (int)($sh * $sc);
                imagecopyresampled($im, $src, $x + (int)(($tw - $dw) / 2), $y + (int)(($th - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
                imagedestroy($src);
            }
            if ($dom) {
                $c = imagecolorallocate($im, $dom[0], $dom[1], $dom[2]);
                imagefilledrectangle($im, $x + 4, $y + $th + 4, $x + 30, $y + $th + 26, $c);
                imagerectangle($im, $x + 4, $y + $th + 4, $x + 30, $y + $th + 26, $ink);
            }
            imagestring($im, 3, $x + 36, $y + $th + 4, ($si * $per + $k + 1) . ' ' . $name, $note !== '' ? $warn : $ink);
            imagestring($im, 1, $x + 36, $y + $th + 22, substr($id, 0, 30), $ink);
            imagestring($im, 1, $x + 4, $y + $th + 32, substr(vr_card_name($p), 0, 38), $ink);
        }
        imagejpeg($im, sprintf('%s/colours-%02d.jpg', rtrim($sheets, '/'), $si + 1), 78);
        imagedestroy($im);
    }
    // Kolajdaki numara → ürün kimliği: gözle düzeltme bu listeye göre yazılıyor.
    $tsv = '';
    foreach ($rows as $k => [$id, $p, $img, $dom, $name]) $tsv .= ($k + 1) . "\t" . $id . "\t" . $name . "\n";
    file_put_contents(rtrim($sheets, '/') . '/index.tsv', $tsv);
    fwrite(STDERR, count($chunks) . " kolaj → $sheets\n");
}
