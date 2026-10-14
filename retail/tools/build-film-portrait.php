<?php
/**
 * Telefon için dikey kampanya filmi — CLI
 * =======================================
 *   php tools/build-film-portrait.php [--ffmpeg=PATH] [--width=540] [--shot=3.0] [--crf=28]
 *
 * Operatör, 14 Eki 2026: "ana sayfaya app'e video koyabilirsin".
 *
 * Masaüstündeki hero.mp4 yatay bir şerit; telefonda object-fit:cover onu
 * dikey alana yayınca kare üç kat büyüyor ve ürün kesiliyordu (12 Eki'de bu
 * yüzden telefonda film kaldırılmıştı). Bu film baştan 9:16 çekiliyor:
 * seçilmiş manken fotoğrafları tek tek, her biri yavaşça yakınlaşarak ve bir
 * sonrakine yumuşakça geçerek. Son kare ilk kareye geçtiği için döngü
 * dikişsiz.
 *
 * Kareler data/hero-frames.json'dan, aşağıdaki sırayla (telefon kadrajına
 * uyanlar gözle seçildi: yüksek çözünürlüklü, özne ortada).
 *
 * Çıktı: assets/media/hero-portrait.mp4 ve hero-portrait-poster.jpg.
 * H.264 şart: iOS'taki uygulama görünümü WebM/VP8'i her sürümde oynatmıyor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$opt = ['ffmpeg' => 'ffmpeg', 'width' => 540, 'shot' => 3.0, 'fade' => 0.8, 'crf' => 28, 'fps' => 24, 'zoom' => 0.10];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(\w+)=(.+)$/', $a, $m) && array_key_exists($m[1], $opt)) {
        $opt[$m[1]] = is_string($opt[$m[1]]) ? $m[2] : (float)$m[2];
    }
}
$W = (int)$opt['width']; $W -= $W % 2;
$H = (int)round($W * 16 / 9); $H -= $H % 2;

/* hero-frames.json'daki sıra numaraları (0'dan). */
$pick = [2, 0, 9, 5, 12, 18, 11, 16];
$all  = array_values((array)vr_store_read('hero-frames.json', []));
$shots = [];
foreach ($pick as $k) {
    $rel = (string)($all[$k] ?? '');
    $file = vr_doc_root() . $rel;
    if ($rel === '' || !is_file($file)) { echo "  ! yok: #$k $rel\n"; continue; }
    $src = @imagecreatefromstring((string)file_get_contents($file));
    if (!$src) continue;
    // Bir kez "cover" ölçeğine getir, yakınlaşma payıyla: kareler buradan kesilir.
    $zw = (int)ceil($W * (1 + $opt['zoom'])); $zh = (int)ceil($H * (1 + $opt['zoom']));
    $sw = imagesx($src); $sh = imagesy($src);
    $sc = max($zw / $sw, $zh / $sh);
    $cw = (int)round($zw / $sc); $ch = (int)round($zh / $sc);
    $big = imagecreatetruecolor($zw, $zh);
    imagecopyresampled($big, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2 * 0.5), $zw, $zh, $cw, $ch);
    imagedestroy($src);
    $shots[] = $big;
    echo "  + $rel\n";
}
$n = count($shots);
if ($n < 3) { echo "Yeterli kare yok.\n"; exit(1); }

$fps = (int)$opt['fps']; $shot = (float)$opt['shot']; $fade = (float)$opt['fade'];
$total = (int)round($n * $shot * $fps);

/** i. çekim, kendi yerel zamanında (−fade … shot): yakınlaşma ve yön. */
$render = function (int $i, float $tl) use ($shots, $W, $H, $shot, $fade, $opt): GdImage {
    $p = ($tl + $fade) / ($shot + $fade);               // 0 … 1
    $p = max(0.0, min(1.0, $p));
    $e = $p * $p * (3 - 2 * $p) * 0.35 + $p * 0.65;      // yumuşak ama durmayan
    $z = ($i % 2) ? $opt['zoom'] * (1 - $e) : $opt['zoom'] * $e;   // sırayla yaklaş / uzaklaş
    $big = $shots[$i];
    $vw = $W * (1 + $opt['zoom']) / (1 + $z);            // görünen pencere (büyük kare ölçüsünde)
    $vh = $H * (1 + $opt['zoom']) / (1 + $z);
    $bx = (imagesx($big) - $vw) / 2; $by = (imagesy($big) - $vh) * 0.42;
    $f = imagecreatetruecolor($W, $H);
    imagecopyresampled($f, $big, 0, 0, (int)$bx, (int)$by, $W, $H, (int)$vw, (int)$vh);
    return $f;
};

$tmp = sys_get_temp_dir() . '/film-portrait-' . getmypid();
@mkdir($tmp, 0755, true);
echo "Kareler: $total ($fps fps, {$W}x{$H}) …\n";
$len = $n * $shot;
for ($fi = 0; $fi < $total; $fi++) {
    $t = $fi / $fps;
    $i = (int)floor($t / $shot) % $n;
    $tl = $t - $i * $shot;                               // 0 … shot
    $frame = $render($i, $tl);
    // Son "fade" saniyesinde bir sonraki çekim üstüne biner (sonuncudan sonra ilki).
    if ($tl > $shot - $fade) {
        $j = ($i + 1) % $n;
        $a = ($tl - ($shot - $fade)) / $fade;
        $a = $a * $a * (3 - 2 * $a);
        $next = $render($j, $tl - $shot);
        imagecopymerge($frame, $next, 0, 0, 0, 0, $W, $H, (int)round($a * 100));
        imagedestroy($next);
    }
    imagejpeg($frame, sprintf('%s/%05d.jpg', $tmp, $fi), 92);
    if ($fi === 0) imagejpeg($frame, VR_ROOT . '/assets/media/hero-portrait-poster.jpg', 82);
    imagedestroy($frame);
}

$out = VR_ROOT . '/assets/media/hero-portrait.mp4';
$cmd = sprintf('cat %s/*.jpg | %s -y -loglevel error -f image2pipe -c:v mjpeg -framerate %d -i pipe:0 '
             . '-c:v libx264 -preset slow -crf %d -profile:v high -pix_fmt yuv420p -movflags +faststart -an %s 2>&1',
    escapeshellarg($tmp), escapeshellcmd((string)$opt['ffmpeg']), $fps, (int)$opt['crf'], escapeshellarg($out));
echo (string)shell_exec($cmd);
array_map('unlink', glob($tmp . '/*.jpg') ?: []);
@rmdir($tmp);
if (!is_file($out) || filesize($out) < 10240) { echo "Film üretilemedi.\n"; exit(1); }
printf("Film: assets/media/hero-portrait.mp4  %.2f MB · %.0f sn · %dx%d\n", filesize($out) / 1048576, $len, $W, $H);
