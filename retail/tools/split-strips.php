<?php

/**
 * ŞERİT KARELERİ BÖLER — vitrin karosu için tek panel çıkarır.
 *
 * PROBLEM
 * Tedarikçi karelerinin bir kısmı tek çekim değil, yan yana dizilmiş 2-6
 * panel: aynı ürünün önden, arkadan, detay çekimi tek dosyada. Ürün
 * sayfasında bunda sakınca yok — alıcı hepsini görüyor. Ama vitrin karosunda
 * altı küçük tişört yan yana duruyor ve mağaza depo çıktısına dönüyor.
 * Katalogda kart yüzü böyle olan 53 ürün var.
 *
 * Bu kareleri ATMAK çözüm değildi: çoğu üründe elimizdeki TEK kare o.
 * Fotoğraf eksik değil, kadraj yanlış — o yüzden kesiyoruz.
 *
 * NASIL
 * Zemin rengi dört köşenin ortancasından bulunuyor. Her sütun için "zemine
 * benzeyen piksel oranı" hesaplanıyor; oran çok yüksek olan sütunlar oluk.
 * Oluk dizileri panel sınırı sayılıyor ve panellerden en iyisi seçiliyor.
 *
 * FRENLER — hepsi ölçüldü, hiçbiri süs değil:
 *   · 2-8 panel olmalı. Tek panel çıkarsa kare zaten şerit değildir; sekizden
 *     fazlası oluk gürültüsüdür.
 *   · Panel eni görselin en az %8'i olmalı, yoksa oluk sanılan şey ürünün
 *     kendi boşluğudur.
 *   · Seçilen panelin doluluğu en az %10 olmalı — boş panel seçmektense
 *     şeridi olduğu gibi bırakmak iyi.
 *   · Panel oranı 0,45-1,30 arasında olmalı. Bunun dışına çıkan bir kesim
 *     giysiyi ortadan böler.
 * Fren tutmazsa dosya ATLANIYOR ve şerit olduğu gibi kalıyor.
 *
 * Çıktı yeni bir dosya; orijinale dokunulmuyor. Bağlama işi ayrı adımda
 * (data/product-review.json → face).
 *
 * Kullanım:  php tools/split-strips.php [--apply] [--min-ratio=1.35] [--limit=N]
 */

declare(strict_types=1);

chdir(dirname(__DIR__));
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/view.php';

$apply    = in_array('--apply', $argv, true);
$minRatio = 1.35;
$limit    = 0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--min-ratio=')) $minRatio = (float)substr($a, 12);
    if (str_starts_with($a, '--limit='))     $limit    = (int)substr($a, 8);
}

/** Görseli en fazla $max piksel enine indirger (analiz için). */
function ss_proxy(\GdImage $im, int $max = 700): array
{
    $w = imagesx($im); $h = imagesy($im);
    if ($w <= $max) return [$im, 1.0, false];
    $s  = $max / $w;
    $nw = $max; $nh = max(1, (int)round($h * $s));
    $p  = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($p, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return [$p, $s, true];
}

/** Dört köşeden zemin rengi (ortanca). */
function ss_bg(\GdImage $im): array
{
    $w = imagesx($im); $h = imagesy($im);
    $pts = [[2, 2], [$w - 3, 2], [2, $h - 3], [$w - 3, $h - 3]];
    $R = $G = $B = [];
    foreach ($pts as [$x, $y]) {
        $c = imagecolorat($im, $x, $y);
        $R[] = ($c >> 16) & 255; $G[] = ($c >> 8) & 255; $B[] = $c & 255;
    }
    sort($R); sort($G); sort($B);
    return [(int)(($R[1] + $R[2]) / 2), (int)(($G[1] + $G[2]) / 2), (int)(($B[1] + $B[2]) / 2)];
}

/** Sütun başına "zemine benzeyen piksel" oranı. */
function ss_columns(\GdImage $im, array $bg, int $tol = 26): array
{
    $w = imagesx($im); $h = imagesy($im);
    $step = max(1, intdiv($h, 220));
    $out = [];
    for ($x = 0; $x < $w; $x++) {
        $same = 0; $n = 0;
        for ($y = 0; $y < $h; $y += $step) {
            $c = imagecolorat($im, $x, $y);
            $d = abs((($c >> 16) & 255) - $bg[0]) + abs((($c >> 8) & 255) - $bg[1]) + abs(($c & 255) - $bg[2]);
            if ($d <= $tol) $same++;
            $n++;
        }
        $out[$x] = $n ? $same / $n : 1.0;
    }
    return $out;
}

/** Oluklardan panel aralıkları. */
function ss_panels(array $col, int $w): array
{
    $isGutter = static fn(float $v): bool => $v >= 0.985;
    $panels = []; $start = null;
    for ($x = 0; $x < $w; $x++) {
        if (!$isGutter($col[$x])) { if ($start === null) $start = $x; }
        else { if ($start !== null) { $panels[] = [$start, $x - 1]; $start = null; } }
    }
    if ($start !== null) $panels[] = [$start, $w - 1];

    // Çok dar parçalar oluk gürültüsü; at.
    $minW = max(8, (int)round($w * 0.08));
    return array_values(array_filter($panels, fn($p) => ($p[1] - $p[0] + 1) >= $minW));
}

/**
 * Panelin ten rengi oranı.
 *
 * Şeritteki panellerden biri çoğu zaman yüz/boyun yakın çekimi oluyor. O panel
 * "dolu" görünüyor (kadrajı ten kaplıyor) ve puanlamada kazanıyordu; sonuç
 * vitrinde giysinin değil modelin yüzünün durması. Ölçüldü: on yedi kesimden
 * ikisi tam olarak böyle çıktı (bur-8041966 ve bur-8057370).
 *
 * Ten testi kaba ama bu iş için yeterli: kırmızı yeşilden, yeşil maviden
 * büyük ve kırmızı-mavi farkı dar bir bantta. Giysi renkleri bu bandı nadiren
 * dolduruyor; dolduranlar da (bej, deve tüyü) eşiğin altında kalıyor.
 */
function ss_skin(\GdImage $im, int $x0, int $x1): float
{
    $h = imagesy($im); $stepY = max(1, intdiv($h, 140)); $stepX = max(1, intdiv($x1 - $x0 + 1, 100));
    $skin = 0; $n = 0;
    for ($y = 0; $y < $h; $y += $stepY) {
        for ($x = $x0; $x <= $x1; $x += $stepX) {
            $c = imagecolorat($im, $x, $y);
            $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
            $n++;
            if ($r > 95 && $g > 40 && $b > 20 && $r > $g && $g > $b
                && ($r - $b) > 15 && ($r - $b) < 110 && (max($r, $g, $b) - min($r, $g, $b)) > 15) $skin++;
        }
    }
    return $n ? $skin / $n : 0.0;
}

/** Panelin doluluğu (zemin olmayan piksel oranı). */
function ss_fill(\GdImage $im, array $bg, int $x0, int $x1, int $tol = 26): float
{
    $h = imagesy($im); $stepY = max(1, intdiv($h, 160)); $stepX = max(1, intdiv($x1 - $x0 + 1, 120));
    $on = 0; $n = 0;
    for ($y = 0; $y < $h; $y += $stepY) {
        for ($x = $x0; $x <= $x1; $x += $stepX) {
            $c = imagecolorat($im, $x, $y);
            $d = abs((($c >> 16) & 255) - $bg[0]) + abs((($c >> 8) & 255) - $bg[1]) + abs(($c & 255) - $bg[2]);
            if ($d > $tol) $on++;
            $n++;
        }
    }
    return $n ? $on / $n : 0.0;
}

// ---------------------------------------------------------------- çalıştır
$shape = vr_photo_shape_index();
$targets = [];
foreach (vr_catalog() as $id => $p) {
    $face = vr_card_image($p);
    if (!str_starts_with($face, '/uploads/')) continue;
    $r = (float)($shape[$face]['r'] ?? 0);
    if ($r < $minRatio) continue;
    $targets[$id] = $face;
}

echo "MAXSALES — şerit kare bölme\n";
echo str_repeat('=', 68), "\n";
echo 'aday kart yüzü: ', count($targets), ($apply ? " (YAZILACAK)\n" : " (kuru çalıştırma)\n"), "\n";

$done = 0; $skip = []; $out = [];
foreach ($targets as $id => $rel) {
    if ($limit && $done >= $limit) break;
    $abs = '.' . $rel;
    if (!is_file($abs)) { $skip['dosya yok'] = ($skip['dosya yok'] ?? 0) + 1; continue; }
    $src = @imagecreatefromstring((string)file_get_contents($abs));
    if (!$src) { $skip['okunamadı'] = ($skip['okunamadı'] ?? 0) + 1; continue; }

    [$pim, $scale, $tmp] = ss_proxy($src);
    $bg  = ss_bg($pim);
    $col = ss_columns($pim, $bg);
    $pan = ss_panels($col, imagesx($pim));

    if (count($pan) < 2 || count($pan) > 8) {
        $skip['panel ' . count($pan)] = ($skip['panel ' . count($pan)] ?? 0) + 1;
        if ($tmp) imagedestroy($pim); imagedestroy($src); continue;
    }

    // En iyi panel: dolu, oranı 0,78'e yakın, mümkünse ilk paneller.
    $best = null; $bestScore = -INF;
    $ph = imagesy($pim);
    foreach ($pan as $i => [$x0, $x1]) {
        $pw   = $x1 - $x0 + 1;
        $fill = ss_fill($pim, $bg, $x0, $x1);
        $ar   = $pw / max(1, $ph);
        if ($fill < 0.10) continue;
        if ($ar < 0.45 || $ar > 1.30) continue;

        // Yüz yakın çekimi olan paneli eleme. Eşik %34: ölçülen iyi
        // kesimlerin en teni bol olanı %21'de kalıyor, kötü iki kesim %45 ve
        // %52 veriyordu, yani bant geniş ve sınır rahat.
        $skin = ss_skin($pim, $x0, $x1);
        if ($skin > 0.34) continue;

        /* Doluluk ÖDÜLLENDİRİLMİYOR, HEDEFLENİYOR.
           Önceki puanlama doluluğu ne kadar çok o kadar iyi sayıyordu ve bu
           yanlıştı: kadrajı kenardan kenara dolduran şey giysinin tamamı değil,
           yaka/göğüs yakın çekimi oluyor. Tam boy çekimde öznenin çevresinde
           zemin kalır, yani doluluk orta bandadır. Ölçüldü: iyi kesimler
           0,45-0,75 arasında, kötü iki kesim 0,93 ve 0,96 veriyordu.
           İlk panel tercihi de biraz güçlendi — tedarikçi şeritlerinde ana
           çekim genelde başta duruyor. */
        $score = -abs($fill - 0.58) * 2.4 - abs(log($ar / 0.78)) * 1.2 - $i * 0.10 - $skin * 1.5;
        if ($score > $bestScore) { $bestScore = $score; $best = [$x0, $x1, $fill, $ar]; }
    }

    if ($best === null) {
        $skip['uygun panel yok'] = ($skip['uygun panel yok'] ?? 0) + 1;
        if ($tmp) imagedestroy($pim); imagedestroy($src); continue;
    }

    // Tam çözünürlükte kes.
    [$px0, $px1, $fill, $ar] = $best;
    $fx0 = (int)floor($px0 / $scale);
    $fx1 = (int)ceil($px1 / $scale);
    $fw  = max(1, min(imagesx($src) - $fx0, $fx1 - $fx0 + 1));
    $fh  = imagesy($src);

    $dstRel = preg_replace('/\.(jpe?g|png|webp|avif)$/i', '', $rel) . '-c1.jpg';
    $out[$id] = ['from' => $rel, 'to' => $dstRel, 'panels' => count($pan), 'fill' => round($fill, 2), 'ar' => round($ar, 2)];

    if ($apply) {
        $crop = imagecreatetruecolor($fw, $fh);
        imagecopy($crop, $src, 0, 0, $fx0, 0, $fw, $fh);
        imagejpeg($crop, '.' . $dstRel, 92);
        imagedestroy($crop);
    }
    if ($tmp) imagedestroy($pim);
    imagedestroy($src);
    $done++;
}

printf("kesildi : %d\n", count($out));
foreach ($skip as $k => $v) printf("atlandı : %-18s %d\n", $k, $v);

if ($apply) {
    file_put_contents('data/face-crops.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "\ndata/face-crops.json yazıldı.\n";
} else {
    echo "\n--apply verilmedi, dosya yazılmadı.\n";
    $i = 0;
    foreach ($out as $id => $o) { if ($i++ >= 8) break; printf("   %-28s %d panel · doluluk %.2f · oran %.2f\n", $id, $o['panels'], $o['fill'], $o['ar']); }
}
