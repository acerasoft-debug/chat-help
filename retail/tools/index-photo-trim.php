<?php
/**
 * Çerçeveli fotoğraf indeksi — CLI
 * ================================
 *   php tools/index-photo-trim.php            (yalnız yeni/değişmiş kareler)
 *   php tools/index-photo-trim.php --all      (hepsini yeniden ölç)
 *   php tools/index-photo-trim.php --list     (bulunanları yaz, dosyaya dokunma)
 *
 * NEDEN
 * -----
 * Operatör, 14 Eki 2026: "bazı fotolar görünüşte katmanlı duruyor". Toptancının
 * bazı kareleri bir fotoğrafın içinde başka bir fotoğraf: gri stüdyo çekimi,
 * dört yanında düz beyaz bir pervaz. Kartta bu, taş tonunun üstünde beyaz bir
 * kutu ve onun içinde gri bir kutu olarak — üç katman — görünüyordu.
 *
 * Bu araç pervazı ölçüyor ve data/photo-trim.json'a yazıyor:
 *
 *   "/uploads/…/x.jpg": { "c": [0.031, 0.022, 0.968, 0.990], "bg": "#e9e8e6", "t": 1760000000 }
 *
 *   c  — içteki fotoğrafın kutusu, kaynak boyutuna oranla (x0, y0, x1, y1)
 *   bg — içteki fotoğrafın kenar rengi (kart zemini bu renge uyuyor)
 *   t  — ölçülen dosyanın değişiklik zamanı (dosya değişince yeniden ölçülür)
 *
 * Pervazı olmayan kare { "t": … } olarak kalır, bir sonraki turda atlanır.
 * Kırpmayı küçük kopya üreticisi yapıyor (inc/thumbs.php: vr_thumb_prepare);
 * kaynak dosyaya dokunulmuyor.
 *
 * KURAL — beyaz zeminli paket çekimiyle karışmasın diye tutucu:
 *   · kenardan içeri, düz (renk sapması çok küçük) ve açık satır/sütunlar
 *     pervaz adayı;
 *   · pervazın hemen içindeki çizginin UÇLARI (köşeye yakın parçalar) pervaz
 *     renginden belirgin farklı olmalı — içteki fotoğrafın kendi zemini.
 *     Beyaz paket çekiminde beyaz zemin içeride de sürüyor, adım yok;
 *   · en az üç yanda ya da karşılıklı iki yanda pervaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$all  = in_array('--all', $argv, true);
$only = in_array('--list', $argv, true);
$out  = $all ? [] : (array)vr_store_read('photo-trim.json', []);
$root = vr_doc_root();

$list = [];
foreach (vr_catalog() as $p) {
    foreach ((array)($p['images'] ?? []) as $img) {
        $img = (string)$img;
        if ($img !== '' && str_starts_with($img, '/uploads/')) $list[$img] = true;
    }
}
foreach (vr_face_crops() as $c) {
    $to = (string)($c['to'] ?? '');
    if ($to !== '' && str_starts_with($to, '/uploads/')) $list[$to] = true;
}
$list = array_keys($list);

/** Ölçüm kopyası: uzun kenar 400 px. Pervaz 6–30 px'lik; 120 px yetmiyor. */
function pt_open(string $abs): ?GdImage
{
    $im = @imagecreatefromstring((string)@file_get_contents($abs));
    if (!$im instanceof GdImage) return null;
    $w = imagesx($im); $h = imagesy($im);
    $s = 400 / max($w, $h);
    if ($s >= 1) return $im;
    $d = imagecreatetruecolor((int)max(1, round($w * $s)), (int)max(1, round($h * $s)));
    imagecopyresampled($d, $im, 0, 0, 0, 0, imagesx($d), imagesy($d), $w, $h);
    imagedestroy($im);
    return $d;
}

/** Bir çizginin (satır ya da sütun) pikselleri, [r,g,b] listesi; $a..$b parçası. */
function pt_line(GdImage $im, bool $row, int $i, float $a = 0.0, float $b = 1.0): array
{
    $n = $row ? imagesx($im) : imagesy($im);
    $from = (int)floor($n * $a); $to = (int)ceil($n * $b);
    $px = [];
    for ($k = $from; $k < $to; $k++) {
        $c = $row ? imagecolorat($im, $k, $i) : imagecolorat($im, $i, $k);
        $px[] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    }
    return $px;
}

/** [ortalama r,g,b], en büyük sapma (95. yüzdelik, kanal başına) */
function pt_stat(array $px): array
{
    $n = max(1, count($px));
    $m = [0, 0, 0];
    foreach ($px as $p) { $m[0] += $p[0]; $m[1] += $p[1]; $m[2] += $p[2]; }
    $m = [$m[0] / $n, $m[1] / $n, $m[2] / $n];
    $dev = [];
    foreach ($px as $p) $dev[] = max(abs($p[0] - $m[0]), abs($p[1] - $m[1]), abs($p[2] - $m[2]));
    sort($dev);
    return [$m, $dev ? $dev[(int)floor(0.95 * (count($dev) - 1))] : 0];
}

function pt_dist(array $a, array $b): float { return max(abs($a[0] - $b[0]), abs($a[1] - $b[1]), abs($a[2] - $b[2])); }

/**
 * Bir yandaki pervazın kalınlığı (piksel) ve içteki fotoğrafın o yandaki
 * kenar rengi — ya da null.
 */
function pt_side(GdImage $im, string $side): ?array
{
    $W = imagesx($im); $H = imagesy($im);
    $row = $side === 't' || $side === 'b';
    $len = $row ? $H : $W;
    $max = (int)floor($len * 0.2);
    $frame = null; $k = 0;
    for (; $k < $max; $k++) {
        $i = ($side === 't' || $side === 'l') ? $k : $len - 1 - $k;
        // Çizginin orta %90'ı: köşede komşu yanın pervazı da olabilir, sorun değil.
        [$m, $dv] = pt_stat(pt_line($im, $row, $i, 0.05, 0.95));
        $lum = ($m[0] + $m[1] + $m[2]) / 3;
        if ($dv > 14 || $lum < 238) break;
        if ($frame === null) $frame = $m;
        elseif (pt_dist($m, $frame) > 5) break;
    }
    if ($frame === null || $k < 2 || $k >= $max) return null;
    // Pervaz içerideki ilk çizgiye bir pikselle karışmış olabilir (yeniden
    // örnekleme): iki çizgi içeriden bak.
    $in = ($side === 't' || $side === 'l') ? $k + 2 : $len - 1 - $k - 2;
    if ($in < 0 || $in >= $len) return null;
    // İçteki çizginin neredeyse tamamı pervaz renginden farklı olmalı: içte
    // başka bir fotoğrafın zemini ya da giysi var. Beyaz paket çekiminde
    // beyaz zemin içeride de sürüyor, bu oran düşük kalıyor.
    $px = pt_line($im, $row, $in, 0.04, 0.96);
    $diff = 0; $bgPx = [];
    foreach ($px as $p) {
        if (pt_dist($p, $frame) > 9) { $diff++; if (abs($p[0] - $p[1]) < 30 && ($p[0] + $p[1] + $p[2]) / 3 > 120) $bgPx[] = $p; }
    }
    if ($diff < 0.88 * count($px)) return null;
    // İçteki fotoğrafın zemini: farklı piksellerin açık-nötr olanlarının
    // ortancası (giysi değil zemin). Yoksa çizginin ortancası.
    $pool = $bgPx ?: $px;
    $ch = [array_column($pool, 0), array_column($pool, 1), array_column($pool, 2)];
    foreach ($ch as &$c) sort($c);
    unset($c);
    $mid = (int)floor(count($pool) / 2);
    $bg = [$ch[0][$mid], $ch[1][$mid], $ch[2][$mid]];
    return ['px' => $k, 'bg' => $bg];
}

/** Ölçüm sürümü: kural değişince eski "temiz" kayıtlar yeniden ölçülür. */
const PT_VER = 2;

/**
 * Altyazı şeridi: toptancının bazı karelerinde ürünün altında beyaz zeminde
 * küçük siyah bir model kodu yazısı ("DOLCE&GABBANA G8PT1T G7JV9"). Kartta
 * fotoğrafın içinde yazı gibi duruyor. Aşağıdan yukarı: (beyaz) → ince yazı
 * bandı → en az birkaç satır tam beyaz boşluk → ürün. Bulunursa yazı
 * bandının üstündeki boşluğun ortasının oranı (0..1) döner.
 *
 * Tutucu: bant yüksekliği ≤ %10, boşluktan aşağısı toplam ≤ %18, bant
 * satırlarında koyu piksel oranı düşük (yazı seyrek; giysi kenarı yoğun),
 * bandın yatay yayılımı ≤ %75 (yazı ortada ya da bir köşede; giysi eteği
 * genişte).
 */
function pt_caption(GdImage $im): ?float
{
    $W = imagesx($im); $H = imagesy($im);
    $rowInfo = function (int $y) use ($im, $W): array {
        $dark = 0; $nonwhite = 0; $minx = $W; $maxx = -1;
        for ($x = 0; $x < $W; $x++) {
            $c = imagecolorat($im, $x, $y);
            $l = ((($c >> 16) & 255) + (($c >> 8) & 255) + ($c & 255)) / 3;
            if ($l < 236) { $nonwhite++; $minx = min($minx, $x); $maxx = max($maxx, $x); }
            if ($l < 150) $dark++;
        }
        return [$nonwhite / $W, $dark / $W, $maxx >= $minx ? ($maxx - $minx + 1) / $W : 0];
    };
    $y = $H - 1;
    $lim = (int)floor($H * 0.18);
    // alt beyaz
    while ($y > $H - 1 - $lim && $rowInfo($y)[0] < 0.004) $y--;
    $bandBot = $y; $ink = 0; $span = 0.0; $rows = 0; $sumDark = 0.0;
    while ($y > $H - 1 - $lim) {
        [$nw, $dk, $sp] = $rowInfo($y);
        if ($nw < 0.004) break;             // boşluğa geldik
        if ($nw > 0.35) return null;        // yoğun satır: giysi
        $rows++; $sumDark += $dk; $span = max($span, $sp); if ($dk > 0) $ink++;
        $y--;
    }
    if ($rows < 3 || $rows > $H * 0.10 || $ink < 2) return null;
    if ($span > 0.75 || $sumDark / $rows < 0.01) return null;
    $bandTop = $y + 1;
    // boşluk: en az %1.2 tam beyaz satır, üstünde içerik
    $gap = 0;
    while ($y > 0 && $rowInfo($y)[0] < 0.004) { $gap++; $y--; }
    if ($gap < max(3, (int)round($H * 0.012)) || $y <= $H * 0.5) return null;
    return ($bandTop - $gap / 2) / $H;
}

$found = $done = $skip = $fail = $caps = 0;
$hits = [];
foreach ($list as $rel) {
    $abs = $root . $rel;
    $mt  = (int)@filemtime($abs);
    if (!$mt) { $fail++; continue; }
    if (!$all && isset($out[$rel]) && (int)($out[$rel]['t'] ?? 0) === $mt && (int)($out[$rel]['v'] ?? 1) === PT_VER) { $skip++; continue; }
    $im = pt_open($abs);
    if (!$im) { $fail++; continue; }
    $W = imagesx($im); $H = imagesy($im);
    $s = [];
    foreach (['t', 'b', 'l', 'r'] as $sd) { $r = pt_side($im, $sd); if ($r) $s[$sd] = $r; }
    $cap = pt_caption($im);
    imagedestroy($im);
    $done++;
    $ok = count($s) >= 3 || (isset($s['t'], $s['b']) || isset($s['l'], $s['r']));
    if (!$ok && $cap !== null) {
        // Pervaz yok ama altta model kodu yazısı var: yalnız alttan kes.
        $out[$rel] = ['c' => [0.0, 0.0, 1.0, round($cap, 4)], 'bg' => '#ffffff', 't' => $mt, 'v' => PT_VER, 'k' => 'cap'];
        $found++; $caps++;
        $hits[] = sprintf('%-4s %s', 'cap', $rel);
        continue;
    }
    if (!$ok) { $out[$rel] = ['t' => $mt, 'v' => PT_VER]; continue; }
    // Yanlar aynı içteki fotoğrafı göstermeli: zemin renkleri yakın.
    $bgs = array_column($s, 'bg');
    $spread = 0;
    foreach ($bgs as $a) foreach ($bgs as $b) $spread = max($spread, pt_dist($a, $b));
    if ($spread > 40) { $out[$rel] = ['t' => $mt]; continue; }
    // Kesim bir piksel daha içeriden: pervazla fotoğraf arasındaki geçiş
    // pikseli küçük kopyada ince bir çizgi olarak kalmasın.
    $pad = 1.5;
    $c = [
        isset($s['l']) ? ($s['l']['px'] + $pad) / $W : 0.0,
        isset($s['t']) ? ($s['t']['px'] + $pad) / $H : 0.0,
        isset($s['r']) ? 1 - ($s['r']['px'] + $pad) / $W : 1.0,
        isset($s['b']) ? 1 - ($s['b']['px'] + $pad) / $H : 1.0,
    ];
    if ($c[2] - $c[0] < 0.6 || $c[3] - $c[1] < 0.6) { $out[$rel] = ['t' => $mt]; continue; }
    $n = count($bgs);
    $bg = [0, 0, 0];
    foreach ($bgs as $b) { $bg[0] += $b[0] / $n; $bg[1] += $b[1] / $n; $bg[2] += $b[2] / $n; }
    $out[$rel] = [
        'c'  => array_map(fn($v) => round($v, 4), $c),
        'bg' => sprintf('#%02x%02x%02x', (int)round($bg[0]), (int)round($bg[1]), (int)round($bg[2])),
        't'  => $mt,
        'v'  => PT_VER,
    ];
    $found++;
    $hits[] = sprintf('%-4s %s', implode('', array_keys($s)), $rel);
}

// Katalogdan düşen karelerin kaydı kalmasın.
$keep = array_flip($list);
foreach (array_keys($out) as $k) if (!isset($keep[$k])) unset($out[$k]);

$total = count(array_filter($out, fn($e) => isset($e['c'])));
printf("Çerçeveli kare indeksi: %d kare · ölçülen %d · atlanan %d · okunamayan %d · bu turda bulunan %d (altyazı %d) · toplam kırpılan %d\n",
       count($list), $done, $skip, $fail, $found, $caps, $total);
foreach (array_slice($hits, 0, 60) as $h) echo "  $h\n";
if (!$only) vr_store_write('photo-trim.json', $out);
