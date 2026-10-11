<?php
/**
 * Fotoğraf parmak izi — php tools/index-photo-hash.php [--report] [--sheets=DIR]
 * =============================================================================
 * Aynı fotoğraf bir üründe iki kez görünüyordu: toptancı aynı kareyi farklı
 * adla (x-1.jpg / x-1.avif, bölünmüş panel + panelin kendisi) iki kez veriyor,
 * ya da aynı model kodlu iki satırın kareleri birleşince (catalog.php adım 5)
 * aynı kare iki addan geliyor. Dosya adı karşılaştırması bunları yakalamıyor.
 *
 * Her kare için (önce kenarındaki düz zemin kırpılır, böylece farklı boşlukla
 * kaydedilmiş aynı kare de eşleşir):
 *   h  — 256 bitlik fark özeti (17×16 gri, yatay gradyan), 64 hex
 *   c  — 4×4 RGB ortalaması (48 bayt, base64): renk farkı (lacivert/siyah
 *        aynı kesim iki renk varyantı birbirinin kopyası SAYILMASIN)
 *   m  — dosya içeriğinin md5'i (birebir aynı dosya)
 * data/photo-hash.json'a yazılır (yol → {h,c,m,t}). Artımlı: dosya tarihi
 * değişmeyen kareye dokunmaz. Vitrin (inc/catalog.php, adım 5c) yalnızca bu
 * dosyayı okur; dosya yoksa hiçbir şey değişmez.
 *
 * Ayrıca data/photo-dupes.json (ürün → düşecek kareler) yazılır; vitrin
 * (inc/catalog.php adım 5c) yalnız bunu uygular. Kopya sayılan: birebir aynı
 * dosya YA DA (bit farkı ≤ --max-bits VE renk farkı ≤ --max-colour). Eşikler
 * kolajlara bakılarak seçildi; önü/arkası gibi benzer ama farklı kareler
 * bunların çok üstünde kalıyor. Her üründe İLK görünen kare kalır.
 *
 *   --report         her üründe birbirine yakın kare çiftlerini mesafeyle yazar
 *   --sheets=DIR     yakın çiftlerin kontrol kolajlarını DIR'e yazar (gözle onay)
 *   --dry            photo-dupes.json yazma (yalnız ölç)
 *   --max-bits=N     her zaman kopya sayılan bit farkı (varsayılan 20)
 *   --soft-bits=N    bu farka kadar, renk de ≤ --soft-colour ise kopya (30 / 12)
 *
 * Eşikler kolajlara bakılarak seçildi (2043 kare, 405 yakın çift): 31 bitin
 * altındaki çiftlerin tamamı aynı çekim (yalnız altyazı şeridi/kırpma
 * farkı); önü/arkası gibi gerçekten farklı kareler 32 bitten başlıyor.
 *
 * Ek kural — bölünmüş kolaj: X.jpg birden çok kareyi tek görselde taşıyan
 * bir kolajsa (data/photo-quality.json) ve aynı üründe ondan kesilmiş
 * X-p1/X-p2… kareleri de varsa, kolajın kendisi galeride ikinci kez aynı
 * görüntüleri göstermiş olur — düşer.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Ölçerken vitrinin kendi kopya ayıklaması devre dışı: ham galeriler lazım.
const VR_NO_PHOTO_DEDUPE = true;

require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/thumbs.php';

$report = in_array('--report', $argv, true);
$dry    = in_array('--dry', $argv, true);
$sheets = ''; $maxBits = 20; $softBits = 30; $softCol = 12.0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--sheets=')) $sheets = substr($a, 9);
    if (preg_match('/^--max-bits=(\d+)$/', $a, $m)) $maxBits = (int)$m[1];
    if (preg_match('/^--soft-bits=(\d+)$/', $a, $m)) $softBits = (int)$m[1];
    if (preg_match('/^--soft-colour=([\d.]+)$/', $a, $m)) $softCol = (float)$m[1];
}

$file = 'photo-hash.json';
$idx  = (array)vr_store_read($file, []);
$root = vr_doc_root();

/** Kenardaki düz zemini kırp (beyaz/açık gri ya da köşe rengi). */
function ph_trim(\GdImage $im): \GdImage
{
    $c = @imagecropauto($im, IMG_CROP_THRESHOLD, 0.10, imagecolorat($im, 0, 0));
    if ($c instanceof \GdImage && imagesx($c) > 20 && imagesy($c) > 20) return $c;
    return $im;
}

function ph_sig(string $abs): ?array
{
    $raw = @file_get_contents($abs);
    if ($raw === false) return null;
    $im = @imagecreatefromstring($raw);
    if (!$im instanceof \GdImage) return null;
    if (!imageistruecolor($im)) imagepalettetotruecolor($im);
    $t = ph_trim($im);
    // 17×16 gri → 16×16 = 256 bit yatay fark
    $g = imagecreatetruecolor(17, 16);
    imagecopyresampled($g, $t, 0, 0, 0, 0, 17, 16, imagesx($t), imagesy($t));
    $bits = '';
    for ($y = 0; $y < 16; $y++) {
        $prev = null;
        for ($x = 0; $x < 17; $x++) {
            $p = imagecolorat($g, $x, $y);
            $l = (($p >> 16) & 255) * 299 + (($p >> 8) & 255) * 587 + ($p & 255) * 114;
            if ($prev !== null) $bits .= $l > $prev ? '1' : '0';
            $prev = $l;
        }
    }
    $hex = '';
    foreach (str_split($bits, 4) as $nib) $hex .= dechex(bindec($nib));
    // 4×4 renk
    $s = imagecreatetruecolor(4, 4);
    imagecopyresampled($s, $t, 0, 0, 0, 0, 4, 4, imagesx($t), imagesy($t));
    $col = '';
    for ($y = 0; $y < 4; $y++) for ($x = 0; $x < 4; $x++) {
        $p = imagecolorat($s, $x, $y);
        $col .= chr(($p >> 16) & 255) . chr(($p >> 8) & 255) . chr($p & 255);
    }
    return ['h' => $hex, 'c' => base64_encode($col), 'm' => md5($raw)];
}

/** İki imza arası: [bit farkı (0-256), renk farkı (ortalama mutlak, 0-255)]. */
function ph_dist(array $a, array $b): array
{
    $d = 0;
    for ($i = 0; $i < 64; $i++) $d += substr_count(decbin(hexdec($a['h'][$i]) ^ hexdec($b['h'][$i])), '1');
    $ca = base64_decode($a['c']); $cb = base64_decode($b['c']); $cd = 0;
    for ($i = 0; $i < 48; $i++) $cd += abs(ord($ca[$i]) - ord($cb[$i]));
    return [$d, $cd / 48];
}

$cat = [];
foreach (vr_catalog() as $pid => $p) {
    $cat[$pid] = array_values(array_unique(array_filter((array)($p['images'] ?? []), fn($r) => is_string($r) && str_starts_with($r, '/uploads/'))));
}
$all = [];
foreach ($cat as $imgs) foreach ($imgs as $r) $all[$r] = 1;
$new = 0; $miss = 0; $t0 = microtime(true);
foreach (array_keys($all) as $rel) {
    $abs = $root . $rel;
    $mt  = @filemtime($abs);
    if ($mt === false) { $miss++; continue; }
    if (isset($idx[$rel]) && ($idx[$rel]['t'] ?? 0) === $mt) continue;
    // Hazır 420'lik kopya varsa onu oku (çok daha hızlı); yoksa kaynağı.
    $th  = $root . vr_thumb_rel($rel, 420, 'webp');
    $sig = ph_sig(is_file($th) ? $th : $abs) ?? null;
    if ($sig === null) { $miss++; continue; }
    // md5 her zaman kaynağın kendisinden: birebir aynı dosya kesin kopya.
    $sig['m'] = md5_file($abs) ?: $sig['m'];
    $sig['t'] = $mt;
    $idx[$rel] = $sig;
    $new++;
}
// Vitrinde olmayan eski kayıtları at
$idx = array_intersect_key($idx, $all);
vr_store_write($file, $idx);
printf("Parmak izi: %d kare · yeni %d · okunamayan %d · %.0f sn\n", count($idx), $new, $miss, microtime(true) - $t0);

// ---- ürün başına düşecek kareler (ilk görünen kalır)
$grid = (array)vr_store_read('photo-quality.json', []);
$drop = []; $why = ['hash' => 0, 'collage' => 0];
foreach ($cat as $pid => $imgs) {
    // Bölünmüş kolaj: X.ext kolaj + aynı üründe X-pN.* varsa X düşer
    $set = array_flip($imgs);
    foreach ($imgs as $r) {
        if (!isset($grid[$r])) continue;
        $stem = preg_replace('/\.[a-z0-9]+$/i', '', $r);
        foreach ($imgs as $o) if ($o !== $r && preg_match('#^' . preg_quote($stem, '#') . '-p\d#', $o)) {
            $drop[$pid][] = $r; $why['collage']++; unset($set[$r]); break;
        }
    }
    $keep = [];
    foreach ($imgs as $r) {
        if (!isset($set[$r])) continue;
        $dup = false;
        if (isset($idx[$r])) foreach ($keep as $k) {
            if (!isset($idx[$k])) continue;
            if ($idx[$r]['m'] === $idx[$k]['m']) { $dup = true; break; }
            [$d, $cd] = ph_dist($idx[$r], $idx[$k]);
            if ($d <= $maxBits || ($d <= $softBits && $cd <= $softCol)) { $dup = true; break; }
        }
        if ($dup) { $drop[$pid][] = $r; $why['hash']++; } else $keep[] = $r;
    }
    // Hiçbir ürün karesiz kalmasın (emniyet)
    if (!$keep && isset($drop[$pid])) unset($drop[$pid]);
}
printf("Kopya kare: %d üründe %d kare (aynı çekim %d · bölünmüş kolaj %d; eşik ≤%d bit ya da ≤%d bit + renk ≤%.0f)%s\n",
       count($drop), array_sum(array_map('count', $drop)), $why['hash'], $why['collage'], $maxBits, $softBits, $softCol,
       $dry ? ' — YAZILMADI (--dry)' : '');
if (!$dry) vr_store_write('photo-dupes.json', $drop);

if (!$report && $sheets === '') exit;

// ---- ürün içi yakın çiftler
$pairs = [];
foreach ($cat as $pid => $imgs) {
    $imgs = array_values(array_filter($imgs, fn($r) => isset($idx[$r])));
    for ($i = 0; $i < count($imgs); $i++) for ($j = $i + 1; $j < count($imgs); $j++) {
        [$d, $cd] = ph_dist($idx[$imgs[$i]], $idx[$imgs[$j]]);
        $same = $idx[$imgs[$i]]['m'] === $idx[$imgs[$j]]['m'];
        if ($same || $d <= 48) $pairs[] = [$pid, $imgs[$i], $imgs[$j], $same ? 0 : $d, round($cd, 1), $same];
    }
}
usort($pairs, fn($a, $b) => $a[3] <=> $b[3] ?: $a[4] <=> $b[4]);
$hist = [];
foreach ($pairs as $p) $hist[min(48, intdiv($p[3], 4) * 4)] = ($hist[min(48, intdiv($p[3], 4) * 4)] ?? 0) + 1;
ksort($hist);
echo "Yakın çift (≤48 bit): " . count($pairs) . " · birebir aynı dosya: " . count(array_filter($pairs, fn($p) => $p[5])) . "\n";
echo "Bit farkı dağılımı: "; foreach ($hist as $k => $n) echo "$k-" . ($k + 3) . ":$n  "; echo "\n";
foreach (array_slice($pairs, 0, 400) as $p) printf("  %3d bit · renk %5.1f %s %-46s %s | %s\n", $p[3], $p[4], $p[5] ? 'AYNI' : '    ', $p[0], $p[1], $p[2]);

if ($sheets === '') exit;
@mkdir($sheets, 0755, true);
// Mesafe bandı başına kolaj: her satır bir çift (sol, sağ, etiket)
$bands = [[0, 4], [5, 8], [9, 12], [13, 16], [17, 20], [21, 26], [27, 34], [35, 48]];
$font = 2;
foreach ($bands as [$lo, $hi]) {
    $rows = array_values(array_filter($pairs, fn($p) => $p[3] >= $lo && $p[3] <= $hi));
    foreach (array_chunk($rows, 12) as $k => $chunk) {
        $cw = 150; $chh = 190; $W = 6 * ($cw * 2 + 30); $H = intdiv(count($chunk) + 5, 6) * ($chh + 36);
        $sheet = imagecreatetruecolor($W, max($H, $chh + 36));
        imagefill($sheet, 0, 0, imagecolorallocate($sheet, 255, 255, 255));
        $ink = imagecolorallocate($sheet, 0, 0, 0); $red = imagecolorallocate($sheet, 200, 0, 0);
        foreach ($chunk as $n => $p) {
            $ox = ($n % 6) * ($cw * 2 + 30); $oy = intdiv($n, 6) * ($chh + 36);
            foreach ([1, 2] as $s) {
                $th = $root . vr_thumb_rel($p[$s], 300, 'webp');
                $im = @imagecreatefromstring((string)@file_get_contents(is_file($th) ? $th : $root . $p[$s]));
                if (!$im) continue;
                $sc = min($cw / imagesx($im), $chh / imagesy($im));
                imagecopyresampled($sheet, $im, $ox + ($s - 1) * ($cw + 6), $oy, 0, 0, (int)(imagesx($im) * $sc), (int)(imagesy($im) * $sc), imagesx($im), imagesy($im));
            }
            imagestring($sheet, $font, $ox, $oy + $chh + 4, sprintf('#%d %dbit c%.0f %s', $n + 1, $p[3], $p[4], $p[5] ? 'AYNI' : ''), $p[5] ? $red : $ink);
            imagestring($sheet, 1, $ox, $oy + $chh + 20, substr($p[0], 0, 50), $ink);
        }
        imagejpeg($sheet, sprintf('%s/dist-%02d-%02d-%d.jpg', $sheets, $lo, $hi, $k + 1), 80);
    }
}
file_put_contents($sheets . '/pairs.tsv', implode("\n", array_map(fn($p) => implode("\t", $p), $pairs)) . "\n");
echo "Kolajlar: $sheets\n";
