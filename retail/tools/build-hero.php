<?php
/**
 * Kampanya filmi — CLI
 * ====================
 *   php tools/build-hero.php --dry
 *   php tools/build-hero.php --seconds=48 --width=1600
 *
 * Ana sayfanın sahnesi için assets/media/hero.mp4 üretir: kendi ürün
 * fotoğraflarımızın üzerinde yavaşça yana kayan bir lookbook şeridi.
 *
 * NEDEN BÖYLE
 * -----------
 * Stok video satın alınamıyor, internetten marka kampanyası indirmek de
 * telif açısından yapılacak iş değil. Elimizde olan tek meşru hareketli
 * malzeme: kendi fotoğraflarımız. Bunları yan yana dizip üzerinde gezinmek
 * hem gerçek bir video üretiyor hem de "kampanya çekimi" hissi veriyor;
 * çapraz geçişli slayt gösterisinden daha az ucuz duruyor.
 *
 * KUSURSUZ DÖNGÜ
 * --------------
 * Şerit iki kez yan yana yazılıyor ve kamera tam bir şerit boyu kayıyor.
 * Bitiş karesi başlangıç karesiyle birebir aynı oluyor, döngü görünmüyor.
 *
 * SEÇİM
 * -----
 * Model üzerinde çekilmiş, dolu kadrajlı fotoğraflar tercih ediliyor:
 * beyaz zeminde kesilmiş düz ürün çekimi bir katalog şeridi gibi durur,
 * kampanya gibi durmaz. Ölçüt beyaz piksel oranı — %55'in üstü eleniyor.
 * Marka başına en fazla iki kare, ki şerit tek eve dönüşmesin.
 *
 * Film yoksa hiçbir şey bozulmuyor: vr_hero_media() null döner ve sahne
 * üretilmiş kampanya karelerine düşer (index.php).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$opt = [
    'seconds' => 48,
    'width'   => 1600,      // çıktı genişliği
    'frames'  => 8,         // şeritteki kare sayısı
    'crf'     => 30,
    'dry'     => false,
    'ffmpeg'  => '',
    'name'    => 'hero',   // assets/media/<name>.webm
    'skip'    => 0,        // seçimde ilk N adayı atla — ikinci film başka kareler alsın
    'ratio'   => '16:9',
];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--seconds='))    $opt['seconds'] = max(12, (int)substr($a, 10));
    elseif (str_starts_with($a, '--width='))  $opt['width']   = max(960, (int)substr($a, 8));
    elseif (str_starts_with($a, '--frames=')) $opt['frames']  = max(4, min(14, (int)substr($a, 9)));
    elseif (str_starts_with($a, '--crf='))    $opt['crf']     = max(18, min(36, (int)substr($a, 6)));
    elseif (str_starts_with($a, '--ffmpeg=')) $opt['ffmpeg']  = substr($a, 9);
    elseif (str_starts_with($a, '--name='))   $opt['name']    = preg_replace('/[^a-z0-9-]/', '', substr($a, 7));
    elseif (str_starts_with($a, '--skip='))   $opt['skip']    = max(0, (int)substr($a, 7));
    elseif (str_starts_with($a, '--ratio='))  $opt['ratio']   = substr($a, 8);
    elseif ($a === '--dry')                   $opt['dry']     = true;
}

/** ffmpeg'i bul. Yoksa şerit yine de yazılır, video atlanır. */
function bh_ffmpeg(string $hint): ?string
{
    $cands = array_filter([$hint, 'ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg']);
    foreach (glob('/opt/pw-browsers/ffmpeg-*/ffmpeg-linux') ?: [] as $g) $cands[] = $g;
    foreach ($cands as $c) {
        $out = @shell_exec(escapeshellcmd($c) . ' -version 2>/dev/null');
        if (is_string($out) && str_contains($out, 'ffmpeg version')) return $c;
    }
    return null;
}

echo "MAXSALES — Kampagnenfilm" . ($opt['dry'] ? ' (Probelauf)' : '') . "\n";
echo str_repeat('=', 68) . "\n";

$H = 1080;                                   // şerit yüksekliği
$FW = (int)round($H * 2 / 3);                // kare genişliği (2:3 dikey)
$outW = $opt['width'];
[$rw, $rh] = array_map('intval', explode(':', $opt['ratio']) + [1 => 9]);
if ($rw < 1 || $rh < 1) { $rw = 16; $rh = 9; }
$outH = (int)round($outW * $rh / $rw);

// H.264 tek sayılı kenar kabul etmiyor: 1100x825 istendiğinde libx264 hiç
// çıktı vermeden kapanıyor ve dosya 0 baytta kalıyor (ölçüldü). VP8 buna
// katlanıyordu, o yüzden eski bantlar sorunsuz üretilmişti. İki kenarı da
// çift sayıya yuvarlıyoruz — bir pikselin görüntüye etkisi yok.
$outW -= $outW % 2;
$outH -= $outH % 2;

// ------------------------------------------------------------------ seçim
/** Beyaz piksel oranı — düz ürün çekimini modelli çekimden ayırt eder. */
function bh_whiteness(string $file): float
{
    $s = @getimagesize($file);
    if (!$s || $s[0] < 400 || $s[1] < 500) return 1.0;
    $im = match ($s[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG  => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        default        => false,
    };
    if (!$im instanceof \GdImage) return 1.0;
    $w = imagesx($im); $h = imagesy($im);
    $white = 0; $n = 0;
    for ($y = 0; $y < $h; $y += max(1, (int)($h / 40))) {
        for ($x = 0; $x < $w; $x += max(1, (int)($w / 40))) {
            $c = imagecolorat($im, $x, $y);
            $l = (($c >> 16 & 255) + ($c >> 8 & 255) + ($c & 255)) / 3;
            if ($l > 240) $white++;
            $n++;
        }
    }
    imagedestroy($im);
    return $n ? $white / $n : 1.0;
}

/**
 * Tek özneli bir çekim mi, yoksa ızgara kontakt sayfası mı?
 *
 * Toptancının dosyalarının bir kısmı yan yana DEĞİL, satır satır dizilmiş:
 * bir kadrajda sekiz küçük ürün ve altında model kodu. Bunlar sahnede
 * felaket duruyor — kampanya değil, depo çıktısı gibi.
 *
 * Ayırt etmenin ucuz ve güvenilir yolu: tek bir gövde kadrajı ortasından
 * baştan sona beyaz bir SATIR geçmez. Izgarada ise satırlar arasında mutlaka
 * geçer. Aynısı sütunlar için de geçerli. Kadrajın ortasındaki %20–%80
 * bandında böyle bir beyaz koridor bulursak bu bir ızgaradır.
 */
function bh_is_single(string $file): bool
{
    $s = @getimagesize($file);
    if (!$s) return false;
    $im = match ($s[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG  => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        default        => false,
    };
    if (!$im instanceof \GdImage) return false;

    $w = imagesx($im); $h = imagesy($im);
    $single = true;

    foreach ([false, true] as $cols) {          // önce satırlar, sonra sütunlar
        $outer = $cols ? $w : $h;
        $inner = $cols ? $h : $w;
        $stepIn = max(1, (int)($inner / 60));
        $run = 0;
        $need = max(3, (int)($outer * 0.012));  // koridor sayılacak kalınlık
        $lo = (int)($outer * 0.20); $hi = (int)($outer * 0.80);

        for ($i = $lo; $i < $hi && $single; $i++) {
            $dark = 0; $n = 0;
            for ($j = 0; $j < $inner; $j += $stepIn) {
                $c = imagecolorat($im, $cols ? $i : $j, $cols ? $j : $i);
                if ((($c >> 16 & 255) + ($c >> 8 & 255) + ($c & 255)) / 3 < 242) $dark++;
                $n++;
            }
            if ($n && $dark / $n < 0.02) {
                if (++$run >= $need) $single = false;
            } else {
                $run = 0;
            }
        }
        if (!$single) break;
    }

    imagedestroy($im);
    return $single;
}

// ------------------------------------------------- elle seçilmiş kare listesi
/**
 * data/hero-frames.json varsa kareler ORADAN gelir, sırasıyla.
 *
 * Otomatik seçim işe yaramadı ve neden yaramadığı öğretici: toptancının
 * dosyalarının bir kısmı tek bir çekim değil, kadraja sıkıştırılmış altı
 * ürünlük ızgara ya da üstüne model kodu basılmış panel. Bunları piksel
 * ölçerek ayıklamak için üç ayrı sezgisel yazıldı; her biri bir örneği
 * kurtarıp başkasını kaçırdı. Vitrinin sekiz karesi için bu doğru takas
 * değil — sekiz kareye bakıp seçmek beş dakika sürüyor ve sonucu kesin.
 *
 * Otomatik seçim yine de duruyor: liste yoksa devreye giriyor, yeni bir
 * koleksiyon geldiğinde boş sahne kalmasın diye.
 */
$manual = vr_store_read('hero-frames.json', []);
if (is_array($manual) && $manual) {
    // --skip ve --frames burada da geçerli: aynı listeden birden fazla film
    // çıkarılabilsin diye. Liste dairesel okunuyor, yani skip listenin
    // sonunu aşsa bile film boş kalmıyor.
    $manual = array_values($manual);
    $n = count($manual);
    $want = min($opt['frames'], $n);
    $slice = [];
    for ($i = 0; $i < $want; $i++) $slice[] = $manual[($opt['skip'] + $i) % $n];
    $manual = $slice;

    $picked = [];
    foreach ($manual as $rel) {
        $rel  = (string)$rel;
        $file = vr_doc_root() . $rel;
        if (!is_file($file)) { echo "  ! fehlt: {$rel}\n"; continue; }
        $picked[] = ['rel' => $rel, 'file' => $file, 'brand' => 'kuratiert', 'white' => -2];
    }
    if ($picked) {
        echo "Quelle     : data/hero-frames.json (kuratiert)\n";
        echo "Ausgewählt : " . count($picked) . "\n\n";
        foreach ($picked as $i => $c) printf("  %d. %s\n", $i + 1, $c['rel']);
        if ($opt['dry']) { echo "\nProbelauf — nichts geschrieben.\n"; exit(0); }
        goto strip;
    }
}

$pool = [];
foreach (vr_catalog() as $p) {
    foreach (array_filter((array)$p['images'], 'strlen') as $rel) {
        $file = vr_doc_root() . $rel;
        if (!is_file($file)) continue;
        $s = @getimagesize($file);
        if (!$s) continue;
        $ratio = $s[0] / max(1, $s[1]);
        // Dikey ya da kareye yakın, yeterince büyük kareler.
        if ($ratio < 0.45 || $ratio > 1.05 || $s[1] < 700) continue;
        $pool[] = ['rel' => $rel, 'file' => $file, 'brand' => $p['brand'], 'px' => $s[0] * $s[1]];
    }
}
echo "Kandidaten : " . count($pool) . "\n";
if (!$pool) { echo "Keine geeigneten Aufnahmen gefunden.\n"; exit(1); }

// Büyükten küçüğe bak, beyazlık ölç, marka başına iki kare.
usort($pool, static fn($a, $b) => $b['px'] <=> $a['px']);
$picked = []; $perBrand = []; $checked = 0; $skipped = 0;
foreach ($pool as $c) {
    if (count($picked) >= $opt['frames']) break;
    if (($perBrand[$c['brand']] ?? 0) >= 2) continue;
    if (++$checked > 500) break;
    if ($skipped < $opt['skip']) { $skipped++; continue; }
    $wf = bh_whiteness($c['file']);
    if ($wf > 0.55) continue;
    if (!bh_is_single($c['file'])) continue;      // ızgara kontakt sayfası — sahnede işi yok
    $c['white'] = $wf;
    $picked[] = $c;
    $perBrand[$c['brand']] = ($perBrand[$c['brand']] ?? 0) + 1;
}
// Yeterli modelli kare çıkmadıysa kalanla tamamla — boş şerit olmasın.
if (count($picked) < $opt['frames']) {
    foreach ($pool as $c) {
        if (count($picked) >= $opt['frames']) break;
        if (in_array($c['rel'], array_column($picked, 'rel'), true)) continue;
        if (($perBrand[$c['brand']] ?? 0) >= 3) continue;
        if (!bh_is_single($c['file'])) continue;
        $c['white'] = -1;
        $picked[] = $c;
        $perBrand[$c['brand']] = ($perBrand[$c['brand']] ?? 0) + 1;
    }
}

echo "Ausgewählt : " . count($picked) . "\n\n";
foreach ($picked as $i => $c) {
    printf("  %d. %-46s %-18s %s\n", $i + 1, mb_substr(basename($c['rel']), 0, 46), $c['brand'],
           $c['white'] >= 0 ? sprintf('weiß %.0f%%', $c['white'] * 100) : 'Auffüller');
}

if ($opt['dry']) { echo "\nProbelauf — nichts geschrieben.\n"; exit(0); }

// ------------------------------------------------------------------ şerit
strip:
$stripW = $FW * count($picked);
$strip  = imagecreatetruecolor($stripW * 2, $H);     // iki kez: kusursuz döngü
imagefill($strip, 0, 0, imagecolorallocate($strip, 17, 16, 14));

foreach ($picked as $i => $c) {
    $src = match (@getimagesize($c['file'])[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($c['file']),
        IMAGETYPE_PNG  => @imagecreatefrompng($c['file']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($c['file']),
        default        => false,
    };
    if (!$src instanceof \GdImage) continue;
    $sw = imagesx($src); $sh = imagesy($src);

    // "cover": kadrajı doldur, taşanı kırp.
    $scale = max($FW / $sw, $H / $sh);
    $cw = (int)round($FW / $scale);
    $ch = (int)round($H / $scale);
    $cx = (int)(($sw - $cw) / 2);
    $cy = (int)(($sh - $ch) / 2 * 0.6);          // biraz yukarıdan: yüz kadrajda kalsın

    foreach ([0, $stripW] as $off) {
        imagecopyresampled($strip, $src, $off + $i * $FW, 0, $cx, $cy, $FW, $H, $cw, $ch);
    }
    imagedestroy($src);
}

$mediaDir = VR_ROOT . '/assets/media';
@mkdir($mediaDir, 0755, true);
$stripFile = $mediaDir . '/.' . $opt['name'] . '-strip.jpg';
imagejpeg($strip, $stripFile, 90);
imagedestroy($strip);
printf("\nStreifen   : %dx%d  (%.1f MB)\n", $stripW * 2, $H, filesize($stripFile) / 1048576);

// ------------------------------------------------------------------ video
$ff = bh_ffmpeg($opt['ffmpeg']);
if (!$ff) {
    echo "ffmpeg nicht gefunden — Film übersprungen. Der Streifen liegt bereit.\n";
    exit(0);
}

// Hangi kodlayıcı var? Bazı ffmpeg derlemelerinde (ör. Playwright'ınki)
// yalnızca libvpx bulunuyor; WebM de tarayıcıların tamamına yakınında oynar
// ve zaten vr_hero_media() hero.webm'i de tanıyor.
$enc = (string)@shell_exec(escapeshellcmd($ff) . ' -encoders 2>/dev/null');
$h264 = str_contains($enc, 'libx264');
$vp8  = str_contains($enc, 'libvpx');
if (!$h264 && !$vp8) {
    echo "Weder libx264 noch libvpx in diesem ffmpeg — Film übersprungen.\n";
    exit(1);
}

$out = $mediaDir . '/' . $opt['name'] . '.' . ($h264 ? 'mp4' : 'webm');
$jpg = $mediaDir . '/' . $opt['name'] . '-poster.jpg';
$t   = $opt['seconds'];

$codec = $h264
    ? sprintf('-c:v libx264 -preset slow -crf %d -profile:v high -movflags +faststart', $opt['crf'])
    // VP8: crf tek başına yetmiyor, üst sınır da gerekiyor yoksa dosya şişiyor.
    : sprintf('-c:v libvpx -crf %d -b:v 1400k -deadline good -cpu-used 2 -auto-alt-ref 0', $opt['crf'] + 4);

/**
 * Kareleri BİZ üretiyoruz, ffmpeg'in kırpma filtresi değil.
 *
 * Sebep: eldeki ffmpeg derlemesinde yalnızca "image2pipe" demuxer'ı var —
 * tek bir JPEG dosyasını girdi olarak açamıyor, dolayısıyla "resmi yükle,
 * üzerinde gez" numarası çalışmıyor. Kareleri GD ile kesip arka arkaya
 * boruya vermek hem çalışıyor hem de kamerayı tamamen bizim denetimimize
 * bırakıyor.
 */
$fps    = 20;
$total  = $t * $fps;
$srcW   = (int)round($H * $rw / $rh);
$tmpDir = $mediaDir . '/.frames-' . $opt['name'];
@mkdir($tmpDir, 0755, true);
array_map('unlink', glob($tmpDir . '/*.jpg') ?: []);

$stripIm = imagecreatefromjpeg($stripFile);
echo "Kareler    : {$total} ({$fps} fps) …\n";
for ($i = 0; $i < $total; $i++) {
    $x = (int)round($stripW * $i / $total);          // tam bir şerit boyu
    $frame = imagecreatetruecolor($outW, $outH);
    imagecopyresampled($frame, $stripIm, 0, 0, $x, 0, $outW, $outH, $srcW, $H);
    imagejpeg($frame, sprintf('%s/%05d.jpg', $tmpDir, $i), 88);
    imagedestroy($frame);
}
imagedestroy($stripIm);

$cmd = sprintf(
    // "-c:v mjpeg" ve "pipe:0" ŞART: bu ffmpeg derlemesinde yalnızca
    // image2pipe demuxer'ı ve pipe protokolü var, "-i -" tanınmıyor ve
    // kodlayıcı belirtilmezse akışta hiç video izi oluşmuyor.
    'cat %s/*.jpg | %s -y -loglevel error -f image2pipe -c:v mjpeg -framerate %d -i pipe:0 %s -an %s',
    escapeshellarg($tmpDir), escapeshellcmd($ff), $fps, $codec, escapeshellarg($out)
);
echo "ffmpeg (" . ($h264 ? 'H.264/MP4' : 'VP8/WebM') . ") …\n";
@shell_exec($cmd . ' 2>&1');
array_map('unlink', glob($tmpDir . '/*.jpg') ?: []);
@rmdir($tmpDir);

if (!is_file($out) || filesize($out) < 10240) {
    echo "Film konnte nicht erzeugt werden.\n";
    exit(1);
}

// Poster: ilk kare. Video yüklenene kadar görünen şey bu. Bu ffmpeg'de
// JPEG kodlayıcı olmayabiliyor, o yüzden PNG üzerinden GD ile çeviriyoruz.
$png = $mediaDir . '/.' . $opt['name'] . '-poster.png';
@shell_exec(sprintf('%s -y -loglevel error -i %s -vframes 1 %s 2>&1',
    escapeshellcmd($ff), escapeshellarg($out), escapeshellarg($png)));
if (is_file($png)) {
    $pim = @imagecreatefrompng($png);
    if ($pim instanceof \GdImage) { imagejpeg($pim, $jpg, 82); imagedestroy($pim); }
    @unlink($png);
}

@chmod($out, 0644);
if (is_file($jpg)) @chmod($jpg, 0644);
@unlink($stripFile);

printf("\nFilm       : assets/media/%s  %.1f MB · %ds · %dx%d\n",
       basename($out), filesize($out) / 1048576, $t, $outW, $outH);
if (is_file($jpg)) printf("Poster     : assets/media/%s-poster.jpg  %d KB\n", $opt['name'], (int)(filesize($jpg) / 1024));
echo "\nDie Startseite nimmt den Film ab sofort automatisch (vr_hero_media).\n";
