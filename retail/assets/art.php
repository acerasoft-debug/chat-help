<?php
/**
 * Üretilmiş ürün görseli (SVG)
 * ============================
 * Fotoğrafı olmayan satır için deterministik bir kompozisyon üretir: arka plan
 * dokusu + ÜRÜNÜN KENDİ SİLUETİ (tişört, hoodie, jean, mont, çanta …).
 *
 * Neden siluet: soyut bir doku "görsel yok" demenin şık bir yolu ama vitrinde
 * hangi parçaya baktığını söylemiyor. Kategoriye göre çizilen çizgi siluet,
 * fotoğraf taklidi yapmadan ürünü okunur kılıyor — ve tamamen hesaplanmış SVG
 * olduğu için ne dış istek ne disk yazması gerekiyor.
 *
 * Aynı ürün her zaman AYNI görseli alır (tohum = ürün id'sinin hash'i).
 */

declare(strict_types=1);

$seed = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['s'] ?? 'maxsales')) ?: 'maxsales';
$cat  = mb_substr((string)($_GET['c'] ?? ''), 0, 40);
$w    = max(120, min(1600, (int)($_GET['w'] ?? 800)));
$h    = max(120, min(1600, (int)($_GET['h'] ?? 1000)));
/**
 * Görünüm: 0 = önden, 1 = yakın detay, 2 = düz yerleşim (flat lay),
 *          3 = arkadan, 4 = kumaş makro.
 * Aynı ürünün galerisinde tek bir kare yerine beş farklı kadraj olsun diye —
 * fotoğrafı olmayan satır da gerçek bir ürün sayfası gibi dursun.
 */
$view = max(0, min(4, (int)($_GET['v'] ?? 0)));
/**
 * Kampanya kadrajı (m=campaign): anasayfanın sahne görseli. Tek bir ürün
 * yerine bir defile sırası — farklı ölçeklerde üç siluet, güçlü yan ışık,
 * çerçeve ve filigran yok (üstünde zaten devasa tipografi duruyor).
 */
$campaign = ($_GET['m'] ?? '') === 'campaign';

// Tohumdan deterministik sayı üretici (kriptografik değil, sadece tekrarlanabilir).
$state = hexdec(substr(hash('sha256', $seed . '|' . $cat), 0, 12)) ?: 1;
$rnd = static function (float $min = 0.0, float $max = 1.0) use (&$state): float {
    $state = ($state * 6364136223846793005 + 1442695040888963407) & 0x7FFFFFFFFFFFFFF;
    return $min + (($state >> 17) % 100000) / 100000 * ($max - $min);
};

/**
 * Palet: mürekkep/kemik/pirinç ailesi. Amaç fotoğraf taklidi değil, kumaş
 * ve gölge hissi. Son palet açık tonlu — ızgarada hep koyu kart olmasın.
 */
$palettes = [
    ['#15151d', '#26262f', '#c9a24d'],   // ink + brass
    ['#1d1a17', '#332c25', '#d8c39a'],   // tütün
    ['#171b1a', '#252d2b', '#a8b5ab'],   // adaçayı
    ['#1a1618', '#2c2429', '#c98d8d'],   // gül
    ['#14171d', '#222836', '#9aa8c0'],   // gece mavisi
    ['#2a2622', '#3b352e', '#e0cfa8'],   // deve tüyü
    ['#efe9dd', '#ded5c4', '#8d8171'],   // kemik (açık kart)
];
// Kampanya sahnesi her zaman koyu: son palet açık tonlu ve üstündeki
// beyaz tipografi orada okunmaz.
$pi = (int)floor($rnd(0, count($palettes) - ($campaign ? 1 : 0) - 0.001));
[$c1, $c2, $c3] = $palettes[$pi];
$light = $pi === count($palettes) - 1;              // açık paletde çizgi koyu olmalı
$ink   = $light ? '#3a352c' : $c3;

/**
 * Kategoriden siluet seç. Veri İngilizce geliyor (T-Shirts, Hoodies &
 * Sweatshirts, Jeans …) ama Almanca/İtalyanca yazılmış satıcı kategorileri de
 * yakalansın diye anahtar kelimeler geniş tutuldu.
 */
$k = mb_strtolower($cat);
$shape = match (true) {
    (bool)preg_match('/hood|sweat|kapuz|felpa/u', $k)                 => 'hoodie',
    (bool)preg_match('/polo/u', $k)                                    => 'polo',
    (bool)preg_match('/jean|denim|trouser|pant|hose|pantalon/u', $k)   => 'trousers',
    (bool)preg_match('/short|bermuda/u', $k)                           => 'shorts',
    (bool)preg_match('/swim|bade|beach|costume/u', $k)                 => 'swim',
    (bool)preg_match('/coat|jacket|mantel|jacke|blazer|parka/u', $k)   => 'coat',
    (bool)preg_match('/knit|pullover|cardigan|strick|maglia/u', $k)    => 'knit',
    (bool)preg_match('/dress|kleid|rock|skirt/u', $k)                  => 'dress',
    (bool)preg_match('/bag|tasche|borsa|clutch|backpack/u', $k)        => 'bag',
    (bool)preg_match('/shoe|sneaker|schuh|boot|scarpa/u', $k)          => 'shoe',
    (bool)preg_match('/belt|gürtel|cap|hat|mütze|scarf|schal|access/u', $k) => 'accessory',
    default                                                            => 'tee',
};

/**
 * Siluetler 100×125'lik bir kutuda tanımlı; aşağıda görsel boyutuna ölçekleniyor.
 * Her giysi ana hat + birkaç detay çizgisinden oluşuyor (dikiş, cep, kemer).
 */
$art = match ($shape) {
    'tee' => [
        'main' => 'M34,26 L44,21 Q50,27 56,21 L66,26 L80,34 L72,47 L66,43 L66,101 Q50,105 34,101 L34,43 L28,47 L20,34 Z',
        'lines' => ['M44,21 Q50,29 56,21', 'M34,95 Q50,98 66,95'],
    ],
    'polo' => [
        'main' => 'M34,26 L44,21 Q50,27 56,21 L66,26 L80,34 L72,47 L66,43 L66,101 Q50,105 34,101 L34,43 L28,47 L20,34 Z',
        'lines' => ['M44,21 L50,33 L56,21', 'M50,33 L50,49', 'M46,36 L46,37', 'M46,44 L46,45', 'M34,95 Q50,98 66,95'],
    ],
    'hoodie' => [
        // Kapüşon gövdenin ARKASINA çiziliyor (behind) — omuz hattı üstüne binsin.
        // Kusursuz kubbe kask gibi duruyordu; tepesi hafif yassı ve asimetrik.
        'behind' => 'M33,33 C31,14 40,8 50,8 C61,8 68,14 66,33 Z',
        // Yaka kavisi kapüşonun altında kaldığı için düz tutuldu; eskiden
        // y=16'ya kadar çıkıp kapüşonun içinde ikinci bir kubbe çiziyordu.
        'main' => 'M33,29 Q50,23 67,29 L82,37 L74,52 L67,47 L67,104 Q50,108 33,104 L33,47 L26,52 L18,37 Z',
        'lines' => [
            'M39,31 C38,18 44,15 50,15 C56,15 62,18 61,31',   // kapüşon ağzı
            'M45,30 L44,46', 'M55,30 L54,44',                 // büzgü ipleri
            'M36,77 L64,77 L64,89 L36,89 Z',                  // kanguru cep
            'M33,98 Q50,101 67,98',                           // bel ribanası
        ],
    ],
    'knit' => [
        'main' => 'M34,27 Q50,19 66,27 L80,35 L73,49 L66,45 L66,100 Q50,104 34,100 L34,45 L27,49 L20,35 Z',
        'lines' => ['M40,26 Q50,33 60,26', 'M34,92 Q50,95 66,92', 'M34,96 Q50,99 66,96', 'M44,45 L44,88', 'M56,45 L56,88'],
    ],
    'coat' => [
        'main' => 'M33,25 L45,20 L50,27 L55,20 L67,25 L80,34 L74,50 L68,46 L70,112 Q50,116 30,112 L32,46 L26,50 L20,34 Z',
        'lines' => ['M45,20 L50,34 L50,110', 'M55,20 L50,34', 'M53,44 L53,45', 'M53,58 L53,59', 'M53,72 L53,73', 'M36,74 L44,74', 'M64,74 L56,74'],
    ],
    'dress' => [
        'main' => 'M36,25 L45,20 Q50,26 55,20 L64,25 L72,33 L66,44 L62,41 L72,108 Q50,113 28,108 L38,41 L34,44 L28,33 Z',
        'lines' => ['M45,20 Q50,28 55,20', 'M40,60 Q50,63 60,60'],
    ],
    'trousers' => [
        'main' => 'M32,22 L68,22 L70,34 L66,112 L54,112 L50,58 L46,112 L34,112 L30,34 Z',
        'lines' => ['M32,30 L68,30', 'M44,26 L44,27', 'M50,58 L50,34'],
    ],
    'shorts' => [
        'main' => 'M32,26 L68,26 L70,38 L67,74 L54,74 L50,50 L46,74 L33,74 L30,38 Z',
        'lines' => ['M32,34 L68,34', 'M50,50 L50,36'],
    ],
    'swim' => [
        'main' => 'M32,30 Q50,26 68,30 L70,42 L67,72 L54,72 L50,52 L46,72 L33,72 L30,42 Z',
        'lines' => ['M44,30 L44,36', 'M56,30 L56,36', 'M50,30 L50,34', 'M32,38 Q50,41 68,38'],
    ],
    'bag' => [
        'main' => 'M28,48 L72,48 L76,102 L24,102 Z',
        'lines' => ['M38,48 Q38,28 50,28 Q62,28 62,48', 'M28,60 L72,60', 'M46,70 L54,70'],
    ],
    'shoe' => [
        'main' => 'M18,84 Q22,58 40,56 Q52,55 60,66 Q68,76 82,80 Q86,82 86,88 L86,94 L18,94 Z',
        'lines' => ['M40,58 L46,72', 'M48,60 L54,74', 'M56,64 L61,76', 'M18,88 L86,88'],
    ],
    default => [   // accessory: katlanmış eşarp / kutu
        'main' => 'M26,44 L74,44 L74,86 L26,86 Z',
        'lines' => ['M26,55 L74,55', 'M26,75 L74,75', 'M40,44 L40,86', 'M60,44 L60,86'],
    ],
};

// ---------------------------------------------------------------- kompozisyon
/**
 * Kadraj görünüme göre değişiyor:
 *  0 önden      — tam boy, ortalanmış
 *  1 detay      — üst gövdeye yakınlaşma (yaka/omuz bölgesi)
 *  2 flat lay   — hafif küçültülmüş ve döndürülmüş, masaya serilmiş gibi
 *  3 arkadan    — aynalanmış, detay çizgileri olmadan
 */
[$zoom, $panX, $panY, $tilt, $mirror] = match ($view) {
    1 => [2.00, 0.00,  0.46, 0.0,  false],   // yaka + omuz
    2 => [0.80, 0.00,  0.00, -13.0, false],
    3 => [0.92, 0.00,  0.00, 0.0,  true],
    // Etek ucu / yan dikiş makrosu. 3.4 kat yakınlaşma kadrajı çerçevenin
    // kenarına düşürüyordu; 2.2 kat gövdenin sol alt köşesine (≈41,86) oturuyor.
    4 => [2.20, 0.18, -0.42, 0.0,  false],
    default => [0.92, 0.0, 0.0, 0.0, false],
};

$s  = min($w / 100, $h / 125) * $zoom;
$ox = ($w - 100 * $s) / 2 + $panX * $w;
$oy = ($h - 125 * $s) / 2 + $panY * $h;

$glowX = round($rnd(0.3, 0.7), 3);
$glowY = round($rnd(0.2, 0.45), 3);

// Arka planda birkaç yumuşak kumaş kıvrımı — siluetin arkasında derinlik.
$folds = '';
for ($i = 0, $n = (int)$rnd(3, 6); $i < $n; $i++) {
    $x = round($w * $rnd(0.05, 0.95), 1);
    $folds .= '<path d="M' . $x . ' -20 C' . round($x + $rnd(-90, 90), 1) . ' ' . round($h * 0.35, 1)
            . ',' . round($x + $rnd(-90, 90), 1) . ' ' . round($h * 0.7, 1) . ',' . $x . ' ' . ($h + 20)
            . '" stroke="' . $c3 . '" stroke-opacity="' . round($rnd(0.04, 0.10), 3)
            . '" stroke-width="' . round($rnd(0.8, 2.2), 2) . '" fill="none"/>';
}

$detail = '';
foreach ($art['lines'] as $d) {
    $detail .= '<path d="' . $d . '" fill="none" stroke="' . $ink . '" stroke-opacity="0.34"'
             . ' stroke-width="0.8" stroke-linecap="round"/>';
}

/**
 * Defile sırası. Altı figür kadrajın ALT yarısında bir sıra oluşturuyor:
 * üstteki yarı başlık tipografisine kalıyor, siluetler onun altından geçiyor.
 * Kenara doğru hem alçalıyorlar hem soluyorlar — uzaklık hissi.
 *
 * Ölçekler sabit; tohum yalnızca birkaç pikselik kaydırma ve opaklık için
 * kullanılıyor, böylece kadraj her yenilemede aynı kalıyor ama ölü bir
 * simetriye de düşmüyor.
 */
$runway = '';
if ($campaign) {
    $figures = [
        // [siluet, x oranı, boy oranı, opaklık]
        ['knit',     0.085, 0.52, 0.42],
        ['dress',    0.225, 0.66, 0.60],
        ['coat',     0.380, 0.82, 0.92],
        ['trousers', 0.545, 0.72, 0.74],
        ['dress',    0.700, 0.60, 0.54],
        ['coat',     0.880, 0.48, 0.38],
    ];
    $shapes = [
        'knit'     => 'M34,27 Q50,19 66,27 L80,35 L73,49 L66,45 L66,100 Q50,104 34,100 L34,45 L27,49 L20,35 Z',
        'coat'     => 'M33,25 L45,20 L50,27 L55,20 L67,25 L80,34 L74,50 L68,46 L70,112 Q50,116 30,112 L32,46 L26,50 L20,34 Z',
        'dress'    => 'M36,25 L45,20 Q50,26 55,20 L64,25 L72,33 L66,44 L62,41 L72,108 Q50,113 28,108 L38,41 L34,44 L28,33 Z',
        'trousers' => 'M32,22 L68,22 L70,34 L66,112 L54,112 L50,58 L46,112 L34,112 L30,34 Z',
    ];
    $ground = $h * 0.965;                      // ortak zemin çizgisi
    foreach ($figures as [$key, $fx, $fs, $fo]) {
        $fh = $h * 0.62 * $fs;                 // en uzun figür kadrajın ~%51'i
        $sc = $fh / 125;
        $x  = $w * $fx - 50 * $sc + $rnd(-0.008, 0.008) * $w;
        $y  = $ground - 125 * $sc;
        $runway .= '<g transform="translate(' . round($x, 1) . ' ' . round($y, 1) . ') scale(' . round($sc, 4) . ')"'
                 . ' opacity="' . round($fo + $rnd(-0.025, 0.025), 3) . '">'
                 . '<path d="' . $shapes[$key] . '" fill="url(#cloth)" stroke="' . $ink . '"'
                 . ' stroke-opacity="0.95" stroke-width="0.9" stroke-linejoin="round"/></g>';
    }
    // Zemin: figürlerin altında ince bir ışık çizgisi, podyum hissi.
    $runway .= '<rect x="0" y="' . round($ground, 1) . '" width="' . $w . '" height="1" fill="' . $c3
             . '" fill-opacity="0.22"/>';
}

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '"'
    . ' viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="MAXSALES">'
    . '<defs>'
    . '<linearGradient id="bg" x1="0" y1="0" x2="0.55" y2="1">'
    . '<stop offset="0" stop-color="' . $c2 . '"/><stop offset="1" stop-color="' . $c1 . '"/></linearGradient>'
    . '<radialGradient id="glow" cx="' . $glowX . '" cy="' . $glowY . '" r="0.75">'
    . '<stop offset="0" stop-color="' . $c3 . '" stop-opacity="0.26"/>'
    . '<stop offset="1" stop-color="' . $c3 . '" stop-opacity="0"/></radialGradient>'
    // Siluetin içi: üstten alta hafifçe koyulaşan bir kumaş dolgusu.
    . '<linearGradient id="cloth" x1="0" y1="0" x2="0.3" y2="1">'
    . '<stop offset="0" stop-color="' . $ink . '" stop-opacity="' . ($light ? '0.14' : '0.20') . '"/>'
    . '<stop offset="1" stop-color="' . $ink . '" stop-opacity="' . ($light ? '0.05' : '0.07') . '"/></linearGradient>'
    . '<filter id="grain"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch"/>'
    . '<feColorMatrix type="saturate" values="0"/></filter>'
    . '</defs>'

    . '<rect width="100%" height="100%" fill="url(#bg)"/>'
    . '<rect width="100%" height="100%" fill="url(#glow)"/>'
    . $folds

    // Kampanya kadrajında tek ürün siluetinin yerini defile sırası alıyor.
    . ($campaign ? $runway : '<g transform="translate(' . round($ox, 2) . ' ' . round($oy, 2) . ') scale(' . round($s, 4) . ')'
    . ($tilt != 0.0 ? ' rotate(' . round($tilt, 1) . ' 50 62)' : '')
    . ($mirror ? ' translate(100 0) scale(-1 1)' : '') . '">'
    // Kapüşon gibi parçalar gövdenin arkasında kalmalı.
    . (isset($art['behind'])
        ? '<path d="' . $art['behind'] . '" fill="url(#cloth)" stroke="' . $ink . '" stroke-opacity="0.45"'
          . ' stroke-width="' . ($view === 1 ? '0.7' : '1.1') . '" stroke-linejoin="round"/>'
        : '')
    . '<path d="' . $art['main'] . '" fill="url(#cloth)" stroke="' . $ink . '" stroke-opacity="0.55"'
    . ' stroke-width="' . ($view === 1 ? '0.7' : '1.1') . '" stroke-linejoin="round"/>'
    // Arkadan görünüşte ön detayları (cep, placket, düğme) çizmiyoruz.
    . ($view === 3 ? '' : $detail)
    . '</g>')

    . '<rect width="100%" height="100%" filter="url(#grain)" opacity="0.05"/>'
    // Kampanya kadrajında çerçeve ve filigran yok: üstüne devasa tipografi
    // ve altın bir çerçeve zaten CSS tarafından biniyor.
    . ($campaign ? '' :
        '<rect x="10.5" y="10.5" width="' . ($w - 21) . '" height="' . ($h - 21) . '" fill="none"'
      . ' stroke="' . $ink . '" stroke-opacity="' . ($view === 1 ? '0.10' : '0.18') . '"/>'
      . '<text x="50%" y="' . round($h - $h * 0.05, 1) . '" text-anchor="middle" fill="' . $ink . '"'
      . ' fill-opacity="0.40" font-family="Georgia,serif"'
      . ' font-size="' . max(9, (int)round(min($w, $h) * 0.030)) . '"'
      . ' letter-spacing="' . max(2, (int)round(min($w, $h) * 0.011)) . '">MAXSALES</text>')
    . '</svg>';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');   // tohum sabit → içerik sabit
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($svg));
echo $svg;
