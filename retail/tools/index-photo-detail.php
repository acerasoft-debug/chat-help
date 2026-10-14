<?php
/**
 * Tam ürün mü, yakın çekim mi — CLI
 * =================================
 *   php tools/index-photo-detail.php          (yalnız yeni kareler)
 *   php tools/index-photo-detail.php --all    (hepsini yeniden ölç)
 *
 * NEDEN
 * -----
 * Operatör (12 Eki 2026): "ilk görünen hep tam ürünü gösteren olmalı".
 * Lacoste kapüşonlusunda karoya ve galerinin başına kapüşonun yakın çekimi
 * çıkıyordu; ürünün ne olduğu ilk bakışta anlaşılmıyor.
 *
 * NASIL
 * -----
 * Stüdyo çekiminde ürünün etrafında zemin var: dört köşe aynı açık renkte,
 * ürün kadrajın ortasında ve yan kenarlara dayanmıyor. Yakın çekimde kumaş
 * kadrajı dolduruyor: köşeler kumaşın kendisi, ürün iki yan kenardan taşıyor.
 *
 *   bg    köşelerin medyan rengi
 *   light zemin açık ve nötr mü (stüdyo zemini: parlaklık ≥ 185, renk farkı küçük)
 *   e     dört kenarın ürüne değen payı [üst, sağ, alt, sol]
 *   m     kadrajın zeminden farklı payı
 *   d     1 = yakın çekim / tam ürünü göstermiyor
 *   c     ürünün baskın rengi (zeminden ayrılan piksellerde)
 *   sk/sa baş bölgesinde / bütün üründe ten rengi payı (arkadan çekim tespiti)
 *
 * Karar: zemin açık değilse (köşeler kumaş) ya da ürün iki yan kenara da
 * dayanıyorsa ya da kadrajın %80'inden fazlası üründeyse yakın çekim.
 * Mankenli çekimde baş üst kenara, bacaklar alt kenara değebilir — bu tek
 * başına yakın çekim sayılmaz.
 *
 * Sonuç data/photo-detail.json'da; vitrin karosu ve galerinin ilk karesi
 * yakın çekimi geriye atıyor (vr_card_frames, vr_colour_sets).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$all = in_array('--all', $argv, true);
$out = $all ? [] : (array)vr_store_read('photo-detail.json', []);
$root = vr_doc_root();

$list = [];
foreach (vr_catalog() as $p) foreach ((array)($p['images'] ?? []) as $img) {
    if (is_string($img) && str_starts_with($img, '/uploads/')) $list[$img] = 1;
}
foreach ((array)vr_store_read('product-images-extra.json', []) as $imgs) foreach ((array)$imgs as $img) {
    if (is_string($img) && str_starts_with($img, '/uploads/')) $list[$img] = 1;
}

function pd_thumb(string $abs): ?GdImage
{
    $raw = @file_get_contents($abs);
    if ($raw === false) return null;
    $src = @imagecreatefromstring($raw);
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src);
    $s = 120 / max($w, $h);
    $dw = (int)max(1, round($w * min(1, $s))); $dh = (int)max(1, round($h * min(1, $s)));
    $dst = imagecreatetruecolor($dw, $dh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);
    imagedestroy($src);
    return $dst;
}

/** Tek kare ölçüsü. */
function pd_measure(GdImage $im): array
{
    $w = imagesx($im); $h = imagesy($im);
    $rgb = static fn(int $c): array => [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    // Köşe yamaları (her biri 4×4) → medyan zemin rengi
    $pts = [];
    foreach ([[0, 0], [$w - 4, 0], [0, $h - 4], [$w - 4, $h - 4]] as [$x0, $y0]) {
        for ($y = 0; $y < 4; $y++) for ($x = 0; $x < 4; $x++) $pts[] = $rgb(imagecolorat($im, max(0, $x0 + $x), max(0, $y0 + $y)));
    }
    $med = static function (array $v): int { sort($v); return $v[intdiv(count($v), 2)]; };
    $bg = [$med(array_column($pts, 0)), $med(array_column($pts, 1)), $med(array_column($pts, 2))];
    $lum = 0.299 * $bg[0] + 0.587 * $bg[1] + 0.114 * $bg[2];
    $chroma = max($bg) - min($bg);
    $light = $lum >= 185 && $chroma <= 28;

    $diff = static fn(array $c): int => abs($c[0] - $bg[0]) + abs($c[1] - $bg[1]) + abs($c[2] - $bg[2]);
    // Ürünün baskın rengi: zeminden ayrılan piksellerde en kalabalık 32'lik
    // kutunun ortalaması. Zemin açık değilse (yakın çekim) bütün kare ürün.
    $m = 0; $bin = [];
    for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
        $c = $rgb(imagecolorat($im, $x, $y));
        $in = $diff($c) > 40;
        if ($in) $m++;
        if ($in || !$light) {
            $k = (($c[0] >> 5) << 6) | (($c[1] >> 5) << 3) | ($c[2] >> 5);
            $bin[$k] ??= [0, 0, 0, 0];
            $bin[$k][0] += $c[0]; $bin[$k][1] += $c[1]; $bin[$k][2] += $c[2]; $bin[$k][3]++;
        }
    }
    $dom = null;
    if ($bin) { uasort($bin, fn($a, $b) => $b[3] <=> $a[3]); $t = reset($bin); $dom = sprintf('#%02x%02x%02x', intdiv($t[0], $t[3]), intdiv($t[1], $t[3]), intdiv($t[2], $t[3])); }
    $edge = static function (array $coords) use ($im, $rgb, $diff): float {
        $hit = 0;
        foreach ($coords as [$x, $y]) if ($diff($rgb(imagecolorat($im, $x, $y))) > 40) $hit++;
        return round($hit / max(1, count($coords)), 2);
    };
    $top = $bottom = $left = $right = [];
    for ($x = 0; $x < $w; $x++) { $top[] = [$x, 1]; $bottom[] = [$x, $h - 2]; }
    for ($y = 0; $y < $h; $y++) { $left[] = [1, $y]; $right[] = [$w - 2, $y]; }
    $e = [$edge($top), $edge($right), $edge($bottom), $edge($left)];
    $mm = round($m / max(1, $w * $h), 3);

    // Arkadan çekim ölçüsü. Ürünün kapladığı kutuda:
    //   sk = baş bölgesinde (üst %18, orta şerit) ten rengi payı — yüz
    //   sa = bütün kutuda ten rengi payı — kare bir mankeni mi gösteriyor
    // Mankenin arkadan çekiminde kollar/boyun görünür (sa var) ama yüz yok
    // (sk düşük). Düz serili ürün çekiminde ikisi de sıfır; ona karar
    // verilmez (logo önde de arkada da olabilir). Bkz. vr_back_penalty().
    $x0 = $w; $y0 = $h; $x1 = 0; $y1 = 0;
    for ($y = 0; $y < $h; $y += 2) for ($x = 0; $x < $w; $x += 2) {
        if ($diff($rgb(imagecolorat($im, $x, $y))) > 40) { $x0 = min($x0, $x); $x1 = max($x1, $x); $y0 = min($y0, $y); $y1 = max($y1, $y); }
    }
    // Ten: kırmızı > yeşil ≥ mavi ve farklar ölçülü. Bordo kumaş ya da pembe
    // baskı (yeşil maviden düşük) ten sayılmasın.
    $isSkin = static fn(array $c): bool => $c[0] > 95 && $c[1] > 40 && $c[2] > 20 && $c[0] > $c[1] && $c[1] >= $c[2] - 4
        && $c[0] - $c[1] > 15 && $c[0] - $c[1] < 100 && $c[0] - $c[2] < 140;
    $sk = 0.0; $sa = 0.0; $hd = 0.0;
    if ($x1 > $x0 + 10 && $y1 > $y0 + 10) {
        $bw = $x1 - $x0; $bh = $y1 - $y0;
        $n = 0; $k = 0;
        for ($y = $y0; $y < (int)($y0 + $bh * 0.18); $y++) for ($x = (int)($x0 + $bw * 0.30); $x < (int)($x0 + $bw * 0.70); $x++) {
            $n++; if ($isSkin($rgb(imagecolorat($im, $x, $y)))) $k++;
        }
        $sk = $n ? round($k / $n, 3) : 0.0;
        // Baş bölgesinde koyu pay (saç / şapka): arkadan çekimde başın arkası.
        $n = 0; $k = 0;
        for ($y = $y0; $y < (int)($y0 + $bh * 0.12); $y++) for ($x = (int)($x0 + $bw * 0.38); $x < (int)($x0 + $bw * 0.62); $x++) {
            $c = $rgb(imagecolorat($im, $x, $y)); $n++;
            if (0.299 * $c[0] + 0.587 * $c[1] + 0.114 * $c[2] < 95) $k++;
        }
        $hd = $n ? round($k / $n, 3) : 0.0;
        $n = 0; $k = 0;
        for ($y = $y0; $y <= $y1; $y += 2) for ($x = $x0; $x <= $x1; $x += 2) {
            $c = $rgb(imagecolorat($im, $x, $y));
            if ($diff($c) <= 40) continue;
            $n++; if ($isSkin($c)) $k++;
        }
        $sa = $n ? round($k / $n, 3) : 0.0;
    }
    $d = (!$light || ($e[1] > 0.45 && $e[3] > 0.45) || $mm > 0.80) ? 1 : 0;
    return ['bg' => sprintf('#%02x%02x%02x', ...$bg), 'light' => $light ? 1 : 0, 'e' => $e, 'm' => $mm, 'd' => $d, 'c' => $dom, 'sk' => $sk, 'sa' => $sa, 'hd' => $hd];
}

$done = $skip = $fail = $det = 0;
foreach (array_keys($list) as $rel) {
    if (!$all && isset($out[$rel]['hd'])) { $skip++; continue; }       // 'hd' yoksa (eski ölçü) yeniden ölç
    $im = pd_thumb($root . $rel);
    if (!$im) { $fail++; continue; }
    $out[$rel] = pd_measure($im);
    imagedestroy($im);
    $det += $out[$rel]['d']; $done++;
}
$out = array_intersect_key($out, $list);
vr_store_write('photo-detail.json', $out);
printf("Tam ürün / yakın çekim: %d kare ölçüldü (%d yakın çekim) · atlanan %d · okunamayan %d · toplam %d\n",
       $done, $det, $skip, $fail, count($out));
