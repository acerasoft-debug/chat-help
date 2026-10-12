<?php
/**
 * Uygulama simgeleri ve açılış ekranları — php tools/build-app-icons.php
 * ====================================================================
 * Tek kaynak: Bodoni Moda (SIL OFL, tools/fonts/) ile beyaz bir "S" — nokta
 * YOK — siyah zemin üzerinde. Üretilenler:
 *
 *   assets/app/icon-192.png, icon-512.png   web manifest, "any": köşeleri
 *                                           yuvarlatılmış, dışı saydam
 *   assets/app/icon-maskable-512.png        "maskable": tam dolu siyah, S güvenli
 *                                           bölgede (merkezdeki %80)
 *   assets/app/apple-touch-icon.png         180 px, tam dolu (iOS kendisi yuvarlar)
 *   assets/app/badge-96.png                 bildirim rozeti: saydam üstüne beyaz S
 *   ../sarvesto-app/assets/icon-only.png    1024, tam dolu (iOS / mağaza)
 *   ../sarvesto-app/assets/icon-foreground.png  1024, Android uyarlanabilir ön plan
 *   ../sarvesto-app/assets/icon-background.png  1024, düz siyah
 *   ../sarvesto-app/assets/splash.png / splash-dark.png  2732², SARVESTO yazısı
 *
 * Her şey 4× büyük çizilip küçültülür (kenar yumuşatma). Yalnız komut satırı.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$font = __DIR__ . '/fonts/BodoniModa[opsz,wght].ttf';
$sans = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
if (!is_file($font)) { fwrite(STDERR, "yazı tipi yok: $font\n"); exit(1); }

/** Saydam, alfa korunan tuval. */
function ai_canvas(int $w, int $h): \GdImage
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    return $im;
}

/** Köşeleri yuvarlatılmış dolu kare (süper örnekleme sayesinde pürüzsüz). */
function ai_rounded(\GdImage $im, int $size, int $radius, int $col): void
{
    imagefilledrectangle($im, $radius, 0, $size - 1 - $radius, $size - 1, $col);
    imagefilledrectangle($im, 0, $radius, $size - 1, $size - 1 - $radius, $col);
    foreach ([[$radius, $radius], [$size - 1 - $radius, $radius], [$radius, $size - 1 - $radius], [$size - 1 - $radius, $size - 1 - $radius]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $radius * 2, $radius * 2, $col);
    }
}

/**
 * "S" harfini tuvalin ortasına, yüksekliği $heightFrac × $size olacak şekilde yaz.
 * Mürekkep kutusu ortalanır; göz ortalaması için %1,5 yukarı.
 */
function ai_glyph(\GdImage $im, int $size, float $heightFrac, int $col, string $font, string $text = 'S'): void
{
    // Puntoyu hedef yüksekliğe göre ölç: önce 100 pt ile kutu, sonra oranla.
    $bb = imagettfbbox(100, 0, $font, $text);
    $h100 = $bb[1] - $bb[7];
    $pt = 100 * ($size * $heightFrac) / max(1, $h100);
    $bb = imagettfbbox($pt, 0, $font, $text);
    $w = $bb[2] - $bb[0]; $h = $bb[1] - $bb[7];
    $x = (int)round(($size - $w) / 2 - $bb[0]);
    $y = (int)round(($size + $h) / 2 - $bb[1] - $size * 0.015);
    imagettftext($im, $pt, 0, $x, $y, $col, $font, $text);
}

/** 4× tuvali hedef boyuta indir (alfa ile). */
function ai_down(\GdImage $big, int $size): \GdImage
{
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $big, 0, 0, 0, 0, $size, $size, imagesx($big), imagesy($big));
    return $out;
}

/**
 * Simge: $size px. $corner: köşe yarıçapı oranı (0 = tam dolu kare),
 * $glyph: S yüksekliği oranı, $bg: zemin (null = saydam).
 */
function ai_icon(string $dst, int $size, float $corner, float $glyph, ?array $bg, array $fg, string $font): void
{
    $S = $size * 4;
    $im = ai_canvas($S, $S);
    if ($bg !== null) {
        $c = imagecolorallocate($im, ...$bg);
        $corner > 0 ? ai_rounded($im, $S, (int)round($S * $corner), $c) : imagefilledrectangle($im, 0, 0, $S - 1, $S - 1, $c);
    }
    ai_glyph($im, $S, $glyph, imagecolorallocate($im, ...$fg), $font);
    $out = ai_down($im, $size);
    @mkdir(dirname($dst), 0755, true);
    imagepng($out, $dst, 9);
    printf("  %-56s %4d px\n", str_replace(dirname(__DIR__, 2) . '/', '', $dst), $size);
}

/** Açılış ekranı: ortada harf aralıklı SARVESTO, altında ince slogan. */
function ai_splash(string $dst, int $size, array $bg, array $fg, array $muted, string $font, string $sans): void
{
    $im = imagecreatetruecolor($size, $size);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$bg));
    $ink = imagecolorallocate($im, ...$fg);
    $grey = imagecolorallocate($im, ...$muted);

    // Sözcük: her harf ayrı, harf aralığı 0,34 em
    $word = 'SARVESTO'; $pt = $size * 0.052; $track = $pt * 0.34;
    $widths = []; $total = 0;
    foreach (str_split($word) as $ch) { $b = imagettfbbox($pt, 0, $font, $ch); $widths[$ch . count($widths)] = [$b[2] - $b[0], $b[0]]; $total += $b[2] - $b[0]; }
    $total += $track * (strlen($word) - 1);
    $x = ($size - $total) / 2; $y = $size * 0.505;
    $i = 0;
    foreach (str_split($word) as $ch) { [$w, $off] = $widths[$ch . $i]; imagettftext($im, $pt, 0, (int)round($x - $off), (int)round($y), $ink, $font, $ch); $x += $w + $track; $i++; }

    // Slogan
    $tag = 'CURATED LUXURY   ·   VERIFIED PROVENANCE'; $tp = $size * 0.0105; $tt = $tp * 0.42;
    $tw = 0; $parts = [];
    foreach (preg_split('//u', $tag, -1, PREG_SPLIT_NO_EMPTY) as $ch) { $b = imagettfbbox($tp, 0, $sans, $ch); $parts[] = [$ch, $b[2] - $b[0], $b[0]]; $tw += $b[2] - $b[0] + $tt; }
    $x = ($size - $tw) / 2; $y = $size * 0.56;
    foreach ($parts as [$ch, $w, $off]) { if ($ch !== ' ') imagettftext($im, $tp, 0, (int)round($x - $off), (int)round($y), $grey, $sans, $ch); $x += $w + $tt; }

    imagepng($im, $dst, 6);
    printf("  %-56s %4d px\n", str_replace(dirname(__DIR__, 2) . '/', '', $dst), $size);
}

$black = [0, 0, 0]; $white = [255, 255, 255];
$web = "$root/assets/app"; $app = dirname($root) . '/sarvesto-app/assets';

echo "Web (manifest, iOS ana ekran, bildirim):\n";
ai_icon("$web/icon-192.png",          192, 0.22, 0.56, $black, $white, $font);
ai_icon("$web/icon-512.png",          512, 0.22, 0.56, $black, $white, $font);
ai_icon("$web/icon-maskable-512.png", 512, 0.00, 0.44, $black, $white, $font);
ai_icon("$web/apple-touch-icon.png",  180, 0.00, 0.56, $black, $white, $font);
ai_icon("$web/badge-96.png",           96, 0.00, 0.80, null,   $white, $font);

echo "Yerel uygulama (Capacitor):\n";
ai_icon("$app/icon-only.png",        1024, 0.00, 0.56, $black, $white, $font);
ai_icon("$app/icon-foreground.png",  1024, 0.00, 0.40, null,   $white, $font);
ai_icon("$app/icon-background.png",  1024, 0.00, 0.00, $black, $black, $font);
ai_splash("$app/splash.png",      2732, $white, [10, 10, 10], [128, 128, 128], $font, $sans);
ai_splash("$app/splash-dark.png", 2732, $black, $white,       [150, 150, 150], $font, $sans);
echo "tamam\n";
