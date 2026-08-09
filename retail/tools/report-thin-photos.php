<?php
/**
 * Zayıf kareleri raporla — CLI
 * ============================
 *   php tools/report-thin-photos.php
 *   php tools/report-thin-photos.php --sheet     (kontrol kolajı üretir)
 *
 * NE YAPIYOR — VE NEDEN SİLMİYOR
 * ------------------------------
 * Beslemede içi boş görünen kareler var. Bunları otomatik ayıklamayı denedim
 * ve DENEME BAŞARISIZ: ölçüt ne olursa olsun beyaz zemindeki beyaz bir Lacoste
 * tişörtü ile gerçekten boş bir kadraj aynı sayıyı veriyor.
 *
 *   · zeminden renk farkı  → beyaz tişört %2, boş kadraj %0–2
 *   · kenar enerjisi       → beyaz tişört 1.7, boş kadraj 1.2–3.0
 *
 * Yani iki grup çakışıyor. Yanlış pozitifin bedeli iyi bir ürün fotoğrafını
 * silmek; yanlış negatifin bedeli bir karenin galeride durması. Bu takasta
 * otomatik silme yanlış taraf.
 *
 * O yüzden burada tek yapılan raporlamak. Kart yüzü seçiminde bu kareler
 * zaten geriye atılıyor (vr_card_frames), yani vitrinde görünmüyorlar;
 * galeride duruyorlar. Gerçekten atılması gereken varsa gözle bakıp
 * data/photo-exclude.json'a yazmak doğru yol.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$sheet = in_array('--sheet', $argv, true);
$shape = (array)vr_store_read('photo-shape.json', []);
if (!$shape) { echo "Önce: php tools/index-photo-shape.php\n"; exit(1); }

const THIN = 0.03;

$rows = [];
$facesFixed = 0;
foreach (vr_catalog() as $id => $p) {
    $imgs = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));
    $thin = [];
    foreach ($imgs as $rel) {
        $f = $shape[$rel]['fill'] ?? null;
        if ($f !== null && (float)$f < THIN) $thin[] = $rel;
    }
    if (!$thin) continue;
    $rows[$id] = ['thin' => $thin, 'all' => count($imgs), 'brand' => $p['brand']];
    // Kart yüzü zaten dolu bir kareye kaçmış mı?
    if ((float)($shape[vr_card_image($p)]['fill'] ?? 1) >= THIN) $facesFixed++;
}

printf("MAXSALES — zayıf kare raporu\n%s\n", str_repeat('=', 58));
printf("zayıf kare içeren ürün : %d\n", count($rows));
$tot = 0; foreach ($rows as $r) $tot += count($r['thin']);
printf("zayıf kare             : %d\n", $tot);
printf("kart yüzü dolu kareye kaçabilmiş: %d ürün\n\n", $facesFixed);

foreach ($rows as $id => $r) {
    $onlyOne = count($r['thin']) === $r['all'];
    printf("  %-56s %d/%d%s\n", $id, count($r['thin']), $r['all'],
        $onlyOne ? '   ← BAŞKA KARESİ YOK, fotoğraf gerekiyor' : '');
}

if ($sheet) {
    $all = [];
    foreach ($rows as $r) foreach ($r['thin'] as $rel) $all[] = $rel;
    $all = array_slice($all, 0, 24);
    $cols = 6; $cw = 190; $ch = 240; $rn = max(1, (int)ceil(count($all) / $cols));
    $im = imagecreatetruecolor($cols * $cw, $rn * $ch);
    imagefilledrectangle($im, 0, 0, imagesx($im), imagesy($im), imagecolorallocate($im, 205, 205, 205));
    foreach ($all as $i => $rel) {
        $src = @imagecreatefromstring((string)@file_get_contents(vr_doc_root() . $rel));
        if (!$src) continue;
        imagecopyresampled($im, $src, ($i % $cols) * $cw + 2, intdiv($i, $cols) * $ch + 2,
            0, 0, $cw - 4, $ch - 4, imagesx($src), imagesy($src));
        imagedestroy($src);
    }
    $dst = sys_get_temp_dir() . '/thin-check.jpg';
    imagejpeg($im, $dst, 88);
    echo "\nKontrol kolajı: {$dst}\n";
    echo "Gerçekten boş olanları data/photo-exclude.json'a yazın.\n";
}
