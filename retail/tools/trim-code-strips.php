<?php
/**
 * Alttaki tedarikçi kod şeridini parlaklık sıçramasından bul ve kes — CLI
 * =======================================================================
 *   php tools/trim-code-strips.php --dry     (rapor + kontrol kolajı)
 *   php tools/trim-code-strips.php           (kes, yedekleyerek)
 *   php tools/trim-code-strips.php --restore
 *
 * NİYE İKİNCİ BİR ARAÇ
 * --------------------
 * trim-caption-bands.php şeridi photo-shape.json'daki "trim" alanından
 * okuyor; o ölçüm ürünle şerit arasında TEMİZ BİR ZEMİN BOŞLUĞU arıyor.
 * Boşluk yoksa şerit görünmez oluyor: 2018 karenin yalnızca 96'sında trim
 * var. Vitrinde iki örnek yakalandı — Gucci polo karesinin altında "IC6J",
 * Valentino tişörtünün altında "VLNT VV3MG" yazıyor. İkisinde de şerit
 * BEYAZ ve fotoğrafın zemini de açık, o yüzden eski ölçüm sınırı görmedi.
 *
 * NASIL AYIRT EDİLİYOR
 * --------------------
 * Şerit, fotoğrafın zemininden AYRI bir yüzey: neredeyse saf beyaz, satır
 * boyunca dümdüz, ve üstündeki fotoğraf zemini ondan ölçülebilir biçimde
 * daha koyu (Gucci'de #ececec, Valentino'da #f2f0ec — beyazdan 13 ve 8
 * kademe uzak). Alttan yukarı taranıyor; bir satır "şerit satırı" sayılıyorsa
 *   · taban parlaklığı ≥ 246 (satırın 80. yüzdeliği), VE
 *   · piksellerin ≥ %97'si o tabandan ±8 içinde  (yazı harfleri hariç)
 * Dipten başlayan en uzun kesintisiz şerit alınıyor.
 *
 * ÜÇ FREN — hiçbiri olmadan giysi kesilebilir
 * -------------------------------------------
 *  1. Şerit yüksekliği kadrajın %2'si ile %22'si arasında olmalı. Daha
 *     büyüğü fotoğrafın kendisidir.
 *  2. Şeridin hemen ÜSTÜNDEKİ satır şerit satırı OLMAMALI — gerçek bir
 *     sınır olsun. Beyaz zeminde beyaz tişörtte sınır yoktur, orada kesim
 *     yapılmaz.
 *  3. Üstteki bölgenin taban rengi şeritten en az 4 kademe koyu olmalı.
 *     Aksi hâlde iki yüzey aynıdır ve ortada şerit değil, sadece boşluk
 *     vardır — boşluğu kesmek de zararsız ama bu araç onun için değil.
 *
 * Kesmeden önce özgün dosya uploads/.orig/ altına kopyalanıyor; --restore
 * geri koyuyor. Kesimden sonra: php tools/index-photo-shape.php --all
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "Nur CLI.\n"; exit(1); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$dry     = in_array('--dry', $argv, true);
$restore = in_array('--restore', $argv, true);

$root = dirname(__DIR__);
$orig = $root . '/uploads/.orig';

/** Bir satırın tabanı (80. yüzdelik) ve o tabana yapışıklık oranı. */
function tcs_row(array $lum): array
{
    $s = $lum; sort($s);
    $base = $s[(int)(count($s) * 0.8)];
    $near = 0;
    foreach ($lum as $v) if (abs($v - $base) <= 8) $near++;
    return [$base, $near / count($lum)];
}

/**
 * Alttaki şeridin yüksekliğini piksel olarak döndür (yoksa 0).
 * @return array{0:int,1:string} [yükseklik, gerekçe]
 */
function tcs_detect(string $abs): array
{
    $raw = @file_get_contents($abs);
    if ($raw === false) return [0, 'okunamadi'];
    $src = @imagecreatefromstring($raw);
    if (!$src) return [0, 'acilmadi'];

    $ow = imagesx($src); $oh = imagesy($src);

    // KÜÇÜK KOPYA ÜZERİNDE ÇALIŞ.
    // İlk sürüm tam çözünürlükte her satırı okuyordu: 2000 kare × ~1100
    // satır × 160 örnek ≈ 350 milyon okuma, on bir dakikada bitmedi.
    // Şerit kadrajın yüzde birkaçı; 128×512'lik bir kopyada da rahat
    // görünüyor ve iş kırktan fazla kısalıyor.
    $pw = 128;
    $ph = max(64, min(512, (int)round($oh * $pw / max(1, $ow))));
    $im = imagecreatetruecolor($pw, $ph);
    imagecopyresampled($im, $src, 0, 0, 0, 0, $pw, $ph, $ow, $oh);
    imagedestroy($src);

    $h = $ph; $w = $pw;
    $rows = [];
    // Yalnızca alt %30 taranıyor: şerit her zaman dipte.
    $from = (int)($h * 0.70);
    for ($y = $from; $y < $h; $y++) {
        $lum = [];
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($im, $x, $y);
            $lum[] = (int)(0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255));
        }
        $rows[$y] = tcs_row($lum);
    }
    imagedestroy($im);
    // Ölçüm küçük kopyada; kesim oranı aynı, piksele asıl boyutta çevrilecek.
    $scale = $oh / $h;

    $isStrip = static fn(int $y): bool => isset($rows[$y]) && $rows[$y][0] >= 246 && $rows[$y][1] >= 0.97;

    if (!$isStrip($h - 1)) return [0, 'dipte serit yok'];

    $top = $h - 1;
    while ($top > $from && $isStrip($top - 1)) $top--;

    // YAZI SATIRLARINI DA ŞERİDE KAT.
    // Katı "düz satır" testi harflerin geçtiği satırlarda düşüyor, çünkü
    // koyu glifler yüzde üçü aşıyor. Sonuç: şerit yazının ALTINDA kalıyor,
    // ince bir dilim kesiliyor ve kod satırı yerinde duruyor — kolajda
    // "DLC&GBN" ve "N9T G7NTW" karelerinde tam olarak bu görüldü.
    // Yüzey hâlâ beyaz olduğu sürece (taban ≥ 246) yukarı devam ediyoruz;
    // harfler azınlıkta kaldığı sürece (yapışıklık ≥ %55) satır şeridin
    // parçasıdır. Taban düşünce fotoğrafın kendisine gelmişizdir, dururuz.
    $isInk = static fn(int $y): bool => isset($rows[$y]) && $rows[$y][0] >= 246 && $rows[$y][1] >= 0.55;
    while ($top > $from && $isInk($top - 1)) $top--;

    $band = $h - $top;

    // 1) boyut freni
    if ($band < $h * 0.02) return [0, 'serit cok ince'];
    if ($band > $h * 0.22) return [0, 'serit cok kalin (fotografin kendisi olabilir)'];

    // 2) sinir freni — hemen ustteki satir seridin parcasi olmamali
    if ($top - 1 < $from || $isInk($top - 1)) return [0, 'sinir yok'];

    // 3) kontrast freni — ustteki bolgenin tabani seritten koyu olmali
    $above = [];
    for ($y = max($from, $top - 24); $y < $top; $y++) $above[] = $rows[$y][0];
    if (!$above) return [0, 'ust bolge yok'];
    sort($above);
    $aboveBase = $above[(int)(count($above) / 2)];
    $stripBase = $rows[$h - 1][0];
    if ($stripBase - $aboveBase < 4) return [0, 'kontrast yok (ayni yuzey)'];

    // Kucuk kopyadaki yukseklik, asil dosyadaki piksele cevriliyor.
    $bandOrig = (int)round($band * $scale);
    return [$bandOrig, sprintf('serit %d px · taban %d vs ust %d', $bandOrig, $stripBase, $aboveBase)];
}

// ---- hangi dosyalar
$catalog = vr_catalog();
$files = [];
foreach ($catalog as $p) {
    foreach ((array)($p['images'] ?? []) as $u) {
        $u = ltrim((string)$u, '/');
        if ($u === '' || str_contains($u, '/.orig/')) continue;
        $files[$u] = true;
    }
}
$files = array_keys($files);
sort($files);

// ---- geri al
if ($restore) {
    $n = 0;
    foreach ($files as $rel) {
        $b = $orig . '/' . preg_replace('#^uploads/#', '', $rel);
        if (is_file($b)) { copy($b, $root . '/' . $rel); $n++; }
    }
    printf("%d dosya geri kondu.\n", $n);
    exit(0);
}

printf("MAXSALES — kod şeridi kırpma%s\n", $dry ? ' (Probelauf)' : '');
echo str_repeat('=', 66) . "\n";
printf("taranan kare: %d\n\n", count($files));

$hits = [];
foreach ($files as $rel) {
    [$band, $why] = tcs_detect($root . '/' . $rel);
    if ($band > 0) $hits[$rel] = $band;
}

printf("şerit bulunan kare: %d\n", count($hits));
if (!$hits) exit(0);

$i = 0;
foreach ($hits as $rel => $band) {
    if ($i++ >= 15) { printf("  … ve %d kare daha\n", count($hits) - 15); break; }
    printf("  %-58s %d px\n", substr($rel, -58), $band);
}

// ---- kontrol kolajı: kesim çizgisiyle
$sheetDir = sys_get_temp_dir() . '/code-strips';
@mkdir($sheetDir, 0777, true);
foreach (glob("$sheetDir/*.jpg") ?: [] as $g) @unlink($g);
$CELL = 200; $GAP = 6; $per = 10;
$chunks = array_chunk($hits, $per, true);
foreach ($chunks as $ci => $chunk) {
    $W = count($chunk) * ($CELL + $GAP) + $GAP;
    $im = imagecreatetruecolor($W, $CELL + $GAP * 2);
    imagefill($im, 0, 0, imagecolorallocate($im, 245, 245, 245));
    $red = imagecolorallocate($im, 220, 30, 30);
    $x = $GAP;
    foreach ($chunk as $rel => $band) {
        $s = @imagecreatefromstring((string)@file_get_contents($root . '/' . $rel));
        if ($s) {
            $sw = imagesx($s); $sh = imagesy($s); $sc = min($CELL / $sw, $CELL / $sh);
            $dw = (int)($sw * $sc); $dh = (int)($sh * $sc);
            $ox = $x + (int)(($CELL - $dw) / 2); $oy = $GAP + (int)(($CELL - $dh) / 2);
            imagecopyresampled($im, $s, $ox, $oy, 0, 0, $dw, $dh, $sw, $sh);
            $cut = $oy + $dh - (int)($band * $sc);
            imageline($im, $ox, $cut, $ox + $dw, $cut, $red);
            imagedestroy($s);
        }
        $x += $CELL + $GAP;
    }
    imagejpeg($im, sprintf('%s/strip-%02d.jpg', $sheetDir, $ci + 1), 86);
    imagedestroy($im);
}
printf("\nkontrol kolajı: %s/strip-*.jpg  (kırmızı çizgi = kesim yeri)\n", $sheetDir);

if ($dry) { echo "\nProbelauf — kesilmedi.\n"; exit(0); }

// ---- kes
$done = 0;
foreach ($hits as $rel => $band) {
    $abs = $root . '/' . $rel;
    $s = @imagecreatefromstring((string)@file_get_contents($abs));
    if (!$s) continue;
    $w = imagesx($s); $h = imagesy($s);
    $nh = $h - $band;
    if ($nh < 40) { imagedestroy($s); continue; }

    $b = $orig . '/' . preg_replace('#^uploads/#', '', $rel);
    @mkdir(dirname($b), 0775, true);
    if (!is_file($b)) @copy($abs, $b);

    $d = imagecreatetruecolor($w, $nh);
    imagecopy($d, $s, 0, 0, 0, 0, $w, $nh);
    imagejpeg($d, $abs, 90);
    imagedestroy($d); imagedestroy($s);
    $done++;
}
printf("\n%d kare kırpıldı. Şimdi: php tools/index-photo-shape.php --all\n", $done);
