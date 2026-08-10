<?php
/**
 * Bağlanacak kareleri gözle denetle — CLI
 * =======================================
 *   php tools/review-attach.php            (tüm eşleşmeler, kolajlar hâlinde)
 *   php tools/review-attach.php --how=kod  (yalnızca model kodu eşleşmeleri)
 *
 * NEDEN AYRI BİR ARAÇ
 * -------------------
 * attach-orphan-photos.php 243 dosyayı 153 ürüne bağlamayı öneriyor. Öneri
 * doğru olabilir ama yanlış ürüne fotoğraf koymak, fotoğrafsız bırakmaktan
 * kötü: müşteri satın aldığı şeyi görmüyor demektir. Onun için hiçbir şey
 * yazılmadan önce her eşleşme bir kolaja basılıyor — solda ürünün MEVCUT
 * karesi, sağında bağlanacak yeni kareler. Aynı ürün mü, tek bakışta belli
 * oluyor.
 *
 * Model kodu eşleşmeleri ayrı süzülebiliyor (--how=kod): gövde eşleşmesi
 * dosya adının aynı olmasına dayanıyor ve neredeyse kesin, kod eşleşmesi
 * ise asıl risk taşıyan yer.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "Nur CLI.\n"; exit(1); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';
require_once __DIR__ . '/lib-photo-match.php';

$onlyHow = '';
foreach ($argv as $a) if (str_starts_with($a, '--how=')) $onlyHow = substr($a, 6);
$perSheet = 8;

/** attach aracıyla AYNI gövde kuralı — iki yerde ayrışmasın diye birebir. */
function rv_stem(string $rel): string
{
    $base = strtolower(pathinfo($rel, PATHINFO_FILENAME));
    $base = preg_replace('/(-p\d+)+$/', '', $base) ?? $base;
    $base = preg_replace('/[-_](b|back|front|f|\d{1,2})$/', '', $base) ?? $base;
    return preg_replace('/[^a-z0-9]/', '', $base) ?? $base;
}

$catalog = [];
foreach (vr_catalog() as $p) $catalog[(string)$p['id']] = $p;

// sahipsizleri ve mevcutları topla
$owned = $byStem = $byCode = [];
foreach ($catalog as $id => $p) {
    foreach ((array)vr_product_gallery($p) as $g) {
        $u = is_array($g) ? ($g['src'] ?? $g[0] ?? '') : $g;
        if (!is_string($u) || $u === '') continue;
        $rel = ltrim($u, '/');
        $owned[strtolower($rel)] = $id;
        $byStem[rv_stem($rel)][$id] = true;
    }
    foreach ([$p['sku'] ?? '', $p['model'] ?? ''] as $c) {
        $c = preg_replace('/[^a-z0-9]/', '', strtolower((string)$c)) ?? '';
        if (strlen($c) >= 6) $byCode[$c][$id] = true;
    }
}

$root = dirname(__DIR__) . '/uploads';
$orphans = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = 'uploads/' . ltrim(str_replace($root, '', $f->getPathname()), '/');
    if (!preg_match('/\.(jpe?g|png|webp)$/i', $rel)) continue;
    foreach (['/.thumbs/', '/.orig/', '/candidates/'] as $skip) if (str_contains($rel, $skip)) continue 2;
    if (isset($owned[strtolower($rel)])) continue;
    $orphans[] = $rel;
}

$idx = pm_build_index($catalog);
$byStem2 = pm_stem_index($catalog);

$pairs = [];
foreach ($orphans as $rel) {
    // attach aracıyla BİREBİR aynı fonksiyon: kolajda görülen, yazılacak
    // olanın ta kendisi. Ayrı kopya tutulsaydı biri düzeltilip diğeri
    // unutulduğunda denetim yanlış şeyi gösterirdi.
    [$hits, $how] = pm_match($rel, $idx, $byStem2);
    if (count($hits) !== 1) continue;
    $how = ($how === 'gövde') ? 'govde' : $how;
    if ($onlyHow !== '' && $how !== $onlyHow) continue;
    $pairs[$hits[0]]['how'] = $how;
    $pairs[$hits[0]]['new'][] = $rel;
}

printf("denetlenecek urun: %d (%s)\n", count($pairs), $onlyHow ?: 'hepsi');
if (!$pairs) exit(0);

$CELL = 190; $GAP = 6;
$sheets = array_chunk($pairs, $perSheet, true);
$out = sys_get_temp_dir() . '/attach-review';
@mkdir($out, 0777, true);
foreach (glob("$out/*.jpg") ?: [] as $g) @unlink($g);

foreach ($sheets as $si => $chunk) {
    $maxCols = 1;
    foreach ($chunk as $d) $maxCols = max($maxCols, 1 + count($d['new']));
    $W = $maxCols * ($CELL + $GAP) + $GAP;
    $H = count($chunk) * ($CELL + $GAP + 18) + $GAP;
    $im = imagecreatetruecolor($W, $H);
    imagefill($im, 0, 0, imagecolorallocate($im, 250, 249, 246));
    $ink = imagecolorallocate($im, 20, 20, 20);
    $red = imagecolorallocate($im, 190, 40, 40);

    $y = $GAP;
    foreach ($chunk as $id => $d) {
        $p = $catalog[$id];
        $cur = '';
        foreach ((array)vr_product_gallery($p) as $g) {
            $u = is_array($g) ? ($g['src'] ?? $g[0] ?? '') : $g;
            if (is_string($u) && $u !== '') { $cur = ltrim($u, '/'); break; }
        }
        $label = sprintf('%s · %s · [%s]', $p['brand'] ?? '?', substr(vr_card_name($p), 0, 42), $d['how']);
        imagestring($im, 3, $GAP, $y, $label, $d['how'] === 'kod' ? $red : $ink);
        $ry = $y + 16;
        $files = array_merge([$cur], $d['new']);
        foreach ($files as $ci => $rel) {
            $x = $GAP + $ci * ($CELL + $GAP);
            $abs = dirname(__DIR__) . '/' . $rel;
            $src = @imagecreatefromstring((string)@file_get_contents($abs));
            if (!$src) { imagerectangle($im, $x, $ry, $x + $CELL, $ry + $CELL, $red); continue; }
            $sw = imagesx($src); $sh = imagesy($src);
            $sc = min($CELL / $sw, $CELL / $sh);
            $dw = (int)($sw * $sc); $dh = (int)($sh * $sc);
            imagecopyresampled($im, $src, $x + (int)(($CELL - $dw) / 2), $ry + (int)(($CELL - $dh) / 2),
                               0, 0, $dw, $dh, $sw, $sh);
            imagedestroy($src);
            // ilk hucre MEVCUT kare: ayirt edilsin diye cerceveli
            if ($ci === 0) imagerectangle($im, $x, $ry, $x + $CELL, $ry + $CELL, $ink);
        }
        $y = $ry + $CELL + $GAP;
    }
    $path = sprintf('%s/sheet-%02d.jpg', $out, $si + 1);
    imagejpeg($im, $path, 82);
    imagedestroy($im);
    printf("  %s\n", $path);
}
echo "\nSoldaki cerceveli kare MEVCUT, sagindakiler BAGLANACAK olanlar.\n";
echo "Kirmizi baslik = model kodu eslesmesi (asil risk orada).\n";
