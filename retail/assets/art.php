<?php
/**
 * Üretilmiş ürün görseli (SVG)
 * ----------------------------
 * Fotoğrafı olmayan satır için deterministik, sakin bir kompozisyon üretir.
 * Neden: vitrinde "kırık görsel" kutusu ya da damgalı gri placeholder yerine
 * markaya yakışan bir doku dursun. Aynı ürün her zaman AYNI görseli alır
 * (tohum = ürün id'sinin hash'i), böylece sayfa her yüklemede değişmez.
 *
 * Dış istek yok, GD yok, yazma yok — sadece hesaplanmış SVG.
 */

declare(strict_types=1);

$seed = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['s'] ?? 'vestra')) ?: 'vestra';
$cat  = mb_substr((string)($_GET['c'] ?? ''), 0, 40);
$w    = max(120, min(1600, (int)($_GET['w'] ?? 800)));
$h    = max(120, min(1600, (int)($_GET['h'] ?? 1000)));

// Tohumdan deterministik sayı üretici (xorshift benzeri, kriptografik değil).
$hash  = hash('sha256', $seed . '|' . $cat);
$state = hexdec(substr($hash, 0, 12)) ?: 1;
$rnd = static function (float $min = 0.0, float $max = 1.0) use (&$state): float {
    $state = ($state * 6364136223846793005 + 1442695040888963407) & 0x7FFFFFFFFFFFFFF;
    return $min + (($state >> 17) % 100000) / 100000 * ($max - $min);
};

/**
 * Palet: mürekkep/kemik/pirinç ailesinden, kategoriye göre hafif kayan tonlar.
 * Amaç fotoğraf taklidi değil — kumaş/gölge hissi.
 */
$palettes = [
    ['#15151d', '#26262f', '#c9a24d'],   // ink + brass
    ['#1d1a17', '#332c25', '#d8c39a'],   // tütün
    ['#171b1a', '#252d2b', '#a8b5ab'],   // adaçayı
    ['#1a1618', '#2c2429', '#c98d8d'],   // gül
    ['#14171d', '#222836', '#9aa8c0'],   // gece mavisi
    ['#efe9dd', '#ded5c4', '#8d8171'],   // kemik (açık kart)
];
$pi = (int)floor($rnd(0, count($palettes) - 0.001));
[$c1, $c2, $c3] = $palettes[$pi];

$cx = $w * $rnd(0.34, 0.66);
$cy = $h * $rnd(0.36, 0.6);
$r  = min($w, $h) * $rnd(0.26, 0.40);
$rot = $rnd(-24, 24);

// Kategoriye göre ana biçim: giyimde dikey akış, aksesuarda dairesel odak.
$vertical = (bool)preg_match('/shirt|polo|sweat|hood|jean|trouser|short|dress|coat|jacket|knit/i', $cat);

$lines = '';
$n = (int)$rnd(5, 11);
for ($i = 0; $i < $n; $i++) {
    $o  = round($rnd(0.05, 0.16), 3);
    $sw = round($rnd(0.6, 2.0), 2);
    if ($vertical) {
        $x = round($w * $rnd(0.08, 0.92), 1);
        $lines .= '<path d="M' . $x . ' -20 C' . round($x + $rnd(-70, 70), 1) . ' ' . round($h * 0.36, 1)
                . ',' . round($x + $rnd(-70, 70), 1) . ' ' . round($h * 0.68, 1) . ',' . $x . ' ' . ($h + 20)
                . '" stroke="' . $c3 . '" stroke-opacity="' . $o . '" stroke-width="' . $sw . '" fill="none"/>';
    } else {
        $rr = round($r * $rnd(0.5, 1.7), 1);
        $lines .= '<circle cx="' . round($cx, 1) . '" cy="' . round($cy, 1) . '" r="' . $rr
                . '" stroke="' . $c3 . '" stroke-opacity="' . $o . '" stroke-width="' . $sw . '" fill="none"/>';
    }
}

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="MAXSALES">'
    . '<defs>'
    . '<linearGradient id="bg" x1="0" y1="0" x2="0.6" y2="1">'
    . '<stop offset="0" stop-color="' . $c2 . '"/><stop offset="1" stop-color="' . $c1 . '"/>'
    . '</linearGradient>'
    . '<radialGradient id="glow" cx="' . round($cx / $w, 3) . '" cy="' . round($cy / $h, 3) . '" r="0.72">'
    . '<stop offset="0" stop-color="' . $c3 . '" stop-opacity="0.30"/>'
    . '<stop offset="1" stop-color="' . $c3 . '" stop-opacity="0"/>'
    . '</radialGradient>'
    // Hafif grain: düz gradyan yerine kumaş hissi versin.
    . '<filter id="grain"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch"/>'
    . '<feColorMatrix type="saturate" values="0"/></filter>'
    . '</defs>'
    . '<rect width="100%" height="100%" fill="url(#bg)"/>'
    . '<rect width="100%" height="100%" fill="url(#glow)"/>'
    . '<g transform="rotate(' . round($rot, 2) . ' ' . round($cx, 1) . ' ' . round($cy, 1) . ')">' . $lines . '</g>'
    . '<rect width="100%" height="100%" filter="url(#grain)" opacity="0.055"/>'
    // İnce çerçeve + monogram: kimliksiz bir doku olarak durmasın.
    . '<rect x="10.5" y="10.5" width="' . ($w - 21) . '" height="' . ($h - 21) . '" fill="none" stroke="' . $c3 . '" stroke-opacity="0.20"/>'
    . '<text x="50%" y="' . round($h - $h * 0.055, 1) . '" text-anchor="middle" fill="' . $c3 . '" fill-opacity="0.42"'
    . ' font-family="Georgia,serif" font-size="' . max(9, (int)round(min($w, $h) * 0.032)) . '"'
    . ' letter-spacing="' . max(2, (int)round(min($w, $h) * 0.012)) . '">MAXSALES</text>'
    . '</svg>';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');   // tohum sabit → içerik sabit
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($svg));
echo $svg;
