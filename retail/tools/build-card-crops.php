<?php
/**
 * Vitrin kartı için tek panel kesimi — CLI
 * ========================================
 *   php tools/build-card-crops.php --out=DIR --json=FILE   (deneme: kesimler DIR'e)
 *   php tools/build-card-crops.php --apply                  (uploads/_cards + data/face-crops.json)
 *
 * NEDEN (14 Eki 2026, operatör: "yan duran fotolar iyi görünmüyor, daha
 * estetik ve uyumlu göstermek gerekli koleksiyonları")
 * Toptancının bir kısım karesi tek çekim değil: önden ve arkadan iki manken
 * yan yana, altında "DOLCE&GABBANA G8NV2ZG7BZQ" yazısı. Kart 4:5; böyle bir
 * kare karta sığdırılınca küçücük kalıyor, alt-üstte bulanık dolgu bandı
 * oluşuyor ve ızgara depo çıktısı gibi görünüyordu. split-strips.php yalnız
 * aralarında boş oluk olan şeritleri bölebiliyordu; mankenler yan yana
 * değiyorsa oluk yok.
 *
 * NASIL
 *   1. Alttaki künye yazısı: ürünün altında tamamen boş bir satır ve onun
 *      altında az dolu, alçak bir bant varsa bant kesilir.
 *   2. Panel sınırı: kadrajın ortasına yakın (%36–64), içerikten en boş sütun.
 *      O sütun da doluysa (tek özne kadrajın ortasından geçiyor) bölünmez;
 *      kare tek ürün sayılır ve yalnız 4:5'e kırpılır.
 *   3. Soldaki panel (önden görünüş) içeriğin ağırlık merkezine göre 4:5'e
 *      kırpılır, 900x1125 yazılır.
 * Orijinal dosyaya dokunulmaz; ürün sayfası bütün kareyi göstermeye devam
 * eder. Elle yapılmış kesimler (face-crops.json'da "auto" olmayan) korunur.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$apply = in_array('--apply', $argv, true);
$out = ''; $jsonOut = ''; $only = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--out='))  $out = substr($a, 6);
    if (str_starts_with($a, '--json=')) $jsonOut = substr($a, 7);
    if (str_starts_with($a, '--only=')) $only = substr($a, 7);
}
const CC_VER = 'c7';
$root  = vr_doc_root();
$shape = vr_photo_shape_index();
$crops = (array)vr_store_read('face-crops.json', []);
$pins  = vr_category_fix();

function cc_load(string $file): ?GdImage
{
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $im = @imagecreatefromstring($raw);
    return $im ?: null;
}

/** [satır doluluğu[], sütun doluluğu[], zemin] — küçük kopyada. */
function cc_profile(GdImage $im, int $x0, int $y0, int $w, int $h): array
{
    $rgb = static fn(int $c): array => [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    $pts = [];
    foreach ([[$x0, $y0], [$x0 + $w - 4, $y0], [$x0, $y0 + $h - 4], [$x0 + $w - 4, $y0 + $h - 4]] as [$cx, $cy]) {
        for ($y = 0; $y < 4; $y++) for ($x = 0; $x < 4; $x++) $pts[] = $rgb(imagecolorat($im, $cx + $x, $cy + $y));
    }
    $med = static function (array $v): int { sort($v); return $v[intdiv(count($v), 2)]; };
    $bg = [$med(array_column($pts, 0)), $med(array_column($pts, 1)), $med(array_column($pts, 2))];
    $rows = array_fill(0, $h, 0); $cols = array_fill(0, $w, 0);
    for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
        $c = $rgb(imagecolorat($im, $x0 + $x, $y0 + $y));
        if (abs($c[0] - $bg[0]) + abs($c[1] - $bg[1]) + abs($c[2] - $bg[2]) > 48) { $rows[$y]++; $cols[$x]++; }
    }
    foreach ($rows as $i => $v) $rows[$i] = $v / $w;
    foreach ($cols as $i => $v) $cols[$i] = $v / $h;
    return [$rows, $cols, $bg];
}

/** Kesim kutusu (asıl görsel koordinatında) ya da null. */
function cc_plan(GdImage $src): ?array
{
    $W = imagesx($src); $H = imagesy($src);
    $s = min(1.0, 900 / $W);
    $w = max(40, (int)round($W * $s)); $h = max(40, (int)round($H * $s));
    $sm = imagecreatetruecolor($w, $h);
    imagecopyresampled($sm, $src, 0, 0, 0, 0, $w, $h, $W, $H);
    [$rows, , ] = cc_profile($sm, 0, 0, $w, $h);

    // 1) Alt künye bandı: ürünün altında boş bir satır, onun altında alçak ve
    //    seyrek bir bant (model kodu yazısı, kenar boşluğu). Aşağıdan değil
    //    YUKARIDAN aranıyor: en alttaki boş satır çoğu zaman yazının altındaki
    //    kenar boşluğudur ve oradan kesmek yazıyı kartta bırakıyordu.
    $bottom = $h;
    for ($y = (int)($h * 0.72); $y < $h; $y++) {
        if ($rows[$y] >= 0.004) continue;
        $below = array_slice($rows, $y, $h - $y);
        if (max($below) < 0.55 && array_sum($below) / count($below) < 0.16 && ($h - $y) < $h * 0.24) { $bottom = $y; break; }
    }
    // Üstte tamamen boş şerit varsa o da gider.
    $top = 0;
    while ($top < $bottom * 0.25 && $rows[$top] < 0.004) $top++;
    $hh = $bottom - $top;
    if ($hh < $h * 0.5) { imagedestroy($sm); return null; }

    [, $cols, ] = cc_profile($sm, 0, $top, $w, $hh);
    $lum = static function (int $c): int { return (int)((($c >> 16) & 255) * 0.3 + (($c >> 8) & 255) * 0.59 + ($c & 255) * 0.11); };
    $L = [];
    for ($y = $top; $y < $bottom; $y += 2) for ($x = 0; $x < $w; $x++) $L[$y][$x] = $lum(imagecolorat($sm, $x, $y));
    $nRows = count($L);

    // 2) Panel sınırı = "basamak": kolajın panelleri yapıştırılmış, sınırda
    //    görüntü boydan boya bir adımda değişiyor. Basamak komşu sütunlardaki
    //    farktan belirgin büyük olmalı — kumaş dokusu ve yumuşak zemin
    //    geçişi her sütunda aynı farkı verir, basamak vermez. Aynı zeminli
    //    iki stüdyo çekimi arasında bile ton farkı bu ölçüye yakalanıyor.
    $stepAt = function (int $x) use ($L, $nRows): float {
        $n = 0;
        foreach ($L as $row) {
            $d0 = abs($row[$x + 1] - $row[$x]);
            $dl = abs($row[$x] - $row[$x - 1]);
            $dr = abs($row[$x + 2] - $row[$x + 1]);
            if ($d0 >= 4 && $d0 > 2.5 * max($dl, $dr, 1)) $n++;
        }
        return $n / max(1, $nRows);
    };
    $x1 = 0; $x2 = $w; $k = 1;
    $minPanel = (int)($hh * 0.38);
    $bestX = null; $bestS = 0.0;
    for ($x = max(2, $minPanel); $x < $w - max(3, $minPanel); $x++) {
        $st = $stepAt($x);
        if ($st > 0.40 && ($bestX === null || $x <= $bestX + 2)) {
            if ($bestX === null || $st > $bestS) { $bestX = $x; $bestS = $st; }
        } elseif ($bestX !== null) break;                 // ilk basamak grubunun tepesi
    }
    if ($bestX !== null) { $x2 = $bestX + 1; $k = 2; }

    // Yedek: oluklu ya da eşit bölünmüş kolaj (zemin rengine göre doluluk).
    if ($k === 1) {
        $avg = array_sum($cols) / max(1, count($cols));
        foreach ([4, 3, 2] as $kk) {
            $c = (int)round($w / $kk);
            if ($c < $minPanel) continue;
            $best = null; $bestV = 9.0;
            for ($x = (int)($c - $w * 0.04); $x <= (int)($c + $w * 0.04); $x++) {
                if ($x < 2 || $x > $w - 3) continue;
                $v = ($cols[$x - 1] + $cols[$x] + $cols[$x + 1]) / 3;
                if ($v < $bestV) { $bestV = $v; $best = $x; }
            }
            if (getenv('CC_DEBUG')) fprintf(STDERR, "  k=%d x=%d v=%.3f avg=%.3f\n", $kk, (int)$best, $bestV, $avg);
            if ($best !== null && ($bestV < 0.03 || ($bestV < 0.30 && $bestV < 0.35 * $avg))) { $x2 = $best; $k = $kk; break; }
        }
    }
    if (getenv('CC_DEBUG')) fprintf(STDERR, "  seam=%s s=%.2f x2=%d w=%d k=%d\n", var_export($bestX, true), $bestS, $x2, $w, $k);
    if ($k === 1) { imagedestroy($sm); return null; }       // tek özne: kırpma, karta sığdır
    $x2 = max($x1 + 8, $x2 - (int)round($w * 0.008));      // dikişten kalan ince şeridi alma
    // Panelin kendi sütunlarında alttan boş/yazılı kalan satırları da at
    // (yazı panelin altına denk gelebiliyor, kolajın başka yerine değil).
    [$prow, , ] = cc_profile($sm, $x1, $top, max(8, $x2 - $x1), $hh);
    for ($y = (int)($hh * 0.70); $y < $hh; $y++) {
        if ($prow[$y] >= 0.006) continue;
        $below = array_slice($prow, $y, $hh - $y);
        if (max($below) < 0.6 && array_sum($below) / count($below) < 0.18) { $bottom = $top + $y; $hh = $y; break; }
    }
    [, $cols, ] = cc_profile($sm, 0, $top, $w, $hh);
    // c) Seçilen panelin içinde iki manken yan yana olabilir (temiz zemin,
    //    basamak yok): aralarında en az 3 sütunluk boş zemin koridoru varsa
    //    ilk panel orada biter.
    if ($k >= 2) {
        $run = 0;
        for ($x = (int)($minPanel * 0.8); $x < $x2 - (int)($minPanel * 0.5); $x++) {
            if ($cols[$x] < 0.005) { if (++$run >= 3) { $x2 = $x - 1; break; } } else $run = 0;
        }
    }
    // 3) Panel içinde içeriğin yatay ağırlık merkezi.
    $sum = 0.0; $mass = 0.0;
    for ($x = $x1; $x < $x2; $x++) { $sum += $x * $cols[$x]; $mass += $cols[$x]; }
    $cx = $mass > 0 ? $sum / $mass : ($x1 + $x2) / 2;
    $pw = $x2 - $x1;
    // 4:5 kutu. Panel yeterince genişse (≥ 0,7 oran) içerik merkezine göre
    // kırpılır; dar bir boy çekimiyse kırpmak baş ya da ayak keserdi — o
    // zaman panel olduğu gibi alınır ve kendi zemin renginde karta oturtulur.
    $pw = $x2 - $x1;
    if ($pw / $hh < 0.62) {
        [, , $pbg] = cc_profile($sm, $x1, $top, max(8, $pw), $hh);
        imagedestroy($sm);
        return ['x' => $x1 / $s, 'y' => $top / $s, 'w' => $pw / $s, 'h' => $hh / $s, 'k' => $k, 'fit' => 'contain', 'bg' => $pbg];
    }
    $bw = $hh * 0.8; $bh = (float)$hh; $by = (float)$top;
    if ($bw > $pw) { $bw = $pw; $bh = $pw / 0.8; $by = $top + ($hh - $bh) * 0.35; }
    $bx = max($x1, min($x2 - $bw, $cx - $bw / 2));
    imagedestroy($sm);
    return ['x' => $bx / $s, 'y' => $by / $s, 'w' => $bw / $s, 'h' => $bh / $s, 'k' => $k];
}

/* Gözle bakılıp otomatik kesimin yanlış çıktığı ürünler (beyaz zeminde beyaz
   tişört, dikişsiz bitişik paneller): bunlarda kesim yapılmaz, kart kareyi
   olduğu gibi gösterir. data/card-crop-skip.json ile genişletilebilir. */
$skip = array_flip(array_merge(
    ['blc-612966tnvn4w', 'bur-8055206', 'dgn-m4a13tismd3', 'dgn-g8pb9thu7ma', 'blc-612966tlvf1', 'fashionb-lacoste-men-lightweight', 'dsq-s74lb0907', 'dgn-gy07cdg8fr4', 'dgx-gy07cd-g8fr4', 'dgx-g8pl4t-g7f2h', 'dgx-g8kbaz-g7c6', 'dgx-g8pb9t-hu7ma', 'dgn-g8qi4tfu7eqw'],
    array_map('strval', (array)vr_store_read('card-crop-skip.json', []))
));
$res = []; $made = 0;
foreach (vr_catalog() as $id => $p) {
    $id = (string)$id;
    if ($only !== '' && !str_contains($id, $only)) continue;
    if (isset($crops[$id]) && empty($crops[$id]['auto'])) continue;          // elle yapılmış kesim
    $skipHit = isset($skip[$id]);
    foreach (array_keys($skip) as $sk) if (str_starts_with($id, $sk)) $skipHit = true;
    if ($skipHit) { unset($crops[$id]); continue; }
    $pin = (string)($pins[$id]['face'] ?? '');
    $srcRel = ($pin !== '' && in_array($pin, (array)$p['images'], true)) ? $pin : (vr_card_frames($p)[0] ?? '');
    if ($srcRel === '' || !str_starts_with($srcRel, '/uploads/')) continue;
    $sh = $shape[$srcRel] ?? null;
    $r = (float)($sh['r'] ?? 0);
    if ($r > 0 && $r < 1.12) { unset($crops[$id]); continue; }                // zaten dikey
    $im = cc_load($root . $srcRel);
    if (!$im) continue;
    if ($r <= 0) $r = imagesx($im) / max(1, imagesy($im));
    if ($r < 1.12) { imagedestroy($im); continue; }
    $plan = cc_plan($im);
    if (!$plan) { imagedestroy($im); continue; }
    $dst = imagecreatetruecolor(900, 1125);
    if (($plan['fit'] ?? '') === 'contain') {
        [$br, $bgc, $bb] = $plan['bg'];
        imagefill($dst, 0, 0, imagecolorallocate($dst, $br, $bgc, $bb));
        $sc = min(900 / $plan['w'], 1125 / $plan['h']);
        $dw = (int)round($plan['w'] * $sc); $dh = (int)round($plan['h'] * $sc);
        imagecopyresampled($dst, $im, (int)((900 - $dw) / 2), (int)((1125 - $dh) / 2), (int)$plan['x'], (int)$plan['y'], $dw, $dh, (int)$plan['w'], (int)$plan['h']);
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $im, 0, 0, (int)$plan['x'], (int)$plan['y'], 900, 1125, (int)$plan['w'], (int)$plan['h']);
    }
    imagedestroy($im);
    $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($id)) . '-' . substr(sha1($srcRel . CC_VER), 0, 8) . '.jpg';
    $rel = '/uploads/_cards/' . $name;
    if ($apply) {
        @mkdir($root . '/uploads/_cards', 0755, true);
        imagejpeg($dst, $root . $rel, 86);
        $crops[$id] = ['from' => $srcRel, 'to' => $rel, 'auto' => 1];
    } elseif ($out !== '') {
        @mkdir($out, 0755, true);
        imagejpeg($dst, $out . '/' . $name, 82);
    }
    imagedestroy($dst);
    $res[] = ['id' => $id, 'from' => $srcRel, 'file' => $name, 'k' => $plan['k'], 'r' => round($r, 2)];
    $made++;
}
if ($apply) {
    ksort($crops);
    vr_store_write('face-crops.json', $crops);
}
if ($jsonOut !== '') file_put_contents($jsonOut, json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
printf("Kart kesimleri: %d ürün%s\n", $made, $apply ? ' (uygulandı)' : '');
