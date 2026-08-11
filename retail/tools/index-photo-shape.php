<?php
/**
 * Fotoğraf biçim indeksi — CLI
 * ============================
 *   php tools/index-photo-shape.php            (yalnız yeni kareler)
 *   php tools/index-photo-shape.php --all      (hepsini yeniden ölç)
 *
 * NEDEN
 * -----
 * Vitrin kartı 4:5 (0.80). Elimizdeki kareler değil: bir kısmı 0.5 gibi upuzun
 * (askıda çekilmiş palto), bir kısmı kare (paket çekimi). Hepsine "object-fit:
 * cover" verirsek dar olanın başı ve ayağı, geniş olanın omuzları kesiliyor —
 * ana sayfada göze en çok batan şey buydu.
 *
 * Doğru davranış kareden kareye değişiyor, yani çalışma anında bilinmesi
 * gerekiyor; ama her istekte 1700 dosyayı açmak saçma. Bu araç bir kez ölçüp
 * data/photo-shape.json'a yazıyor:
 *
 *   "/uploads/…/x.jpg": { "r": 0.67, "bg": "#f7f6f4", "cx": 50, "cy": 34 }
 *
 *   r  — en/boy oranı
 *   bg — kenarlar tek renkse o renk (paket çekimi). Yoksa alan yok.
 *   cx/cy — özne kütlesinin ağırlık merkezi, yüzde. Kırpma buna göre kayıyor;
 *           uzun bir paltonun yüzü kesilmesin diye.
 *
 * Ölçüm küçültülmüş kopya üzerinde yapılıyor (en fazla 120 px) — 1700 dosya
 * yaklaşık yarım dakika sürüyor, tam çözünürlükte on dakikayı geçiyordu.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$all = in_array('--all', $argv, true);

$out = $all ? [] : (array)vr_store_read('photo-shape.json', []);
$root = vr_doc_root();

// ---- ölçülecek kareler: katalogdaki her fotoğraf
$catalog = vr_catalog();
$list = [];
foreach ($catalog as $p) {
    foreach ((array)($p['images'] ?? []) as $img) {
        $img = (string)$img;
        if ($img !== '' && str_starts_with($img, '/uploads/')) $list[$img] = true;
    }
}
/* Şeritten kesilmiş kart yüzleri de ölçülmeli. Bunlar ürünün images
   dizisinde DEĞİL (ürün sayfasında şerit kalıyor, karoda kesim çıkıyor), o
   yüzden yukarıdaki döngü onları görmüyordu. Ölçüsüz kalırlarsa karo
   oran/doluluk mantığı varsayılana düşüyor ve kesimin bütün amacı kaçıyor. */
foreach (vr_face_crops() as $c) {
    $to = (string)($c['to'] ?? '');
    if ($to !== '' && str_starts_with($to, '/uploads/')) $list[$to] = true;
}

$list = array_keys($list);

printf("MAXSALES — fotoğraf biçim indeksi\n%s\n", str_repeat('=', 62));
printf("kare sayısı: %d%s\n\n", count($list), $all ? ' (tam yeniden ölçüm)' : '');

/** Küçültülmüş kopya — ölçüm için 120 px yeter. */
function ps_thumb(string $abs): ?GdImage
{
    $info = @getimagesize($abs);
    if (!$info) return null;
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($abs),
        IMAGETYPE_PNG  => @imagecreatefrompng($abs),
        IMAGETYPE_WEBP => @imagecreatefromwebp($abs),
        default        => null,
    };
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src);
    $s = 120 / max($w, $h);
    if ($s >= 1) return $src;
    $dst = imagecreatetruecolor((int)max(1, round($w * $s)), (int)max(1, round($h * $s)));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
    imagedestroy($src);
    return $dst;
}

$done = $skip = $fail = 0;
$flat = 0;

foreach ($list as $rel) {
    if (!$all && isset($out[$rel])) { $skip++; continue; }

    $abs = $root . $rel;
    if (!is_file($abs)) { $fail++; continue; }

    $info = @getimagesize($abs);
    if (!$info || $info[0] < 8 || $info[1] < 8) { $fail++; continue; }

    // Piksel ölçüsü de saklanıyor: kart 300 px'te basıyor, ürün sayfası
    // 900'de. "Vitrine uygun değil" kararı ikisi için aynı olamaz — 500×640
    // bir kare kartta gayet net, ürün sayfasında bulanık.
    $entry = ['r' => round($info[0] / $info[1], 3), 'w' => (int)$info[0], 'h' => (int)$info[1]];

    $im = ps_thumb($abs);
    if ($im) {
        $w = imagesx($im); $h = imagesy($im);

        // ---- kenar rengi: dört kenardan örnek al, hepsi birbirine yakınsa
        //      bu bir paket çekimi ve o rengi zemin olarak kullanabiliyoruz.
        $edge = [];
        $step = max(1, (int)floor(min($w, $h) / 12));
        for ($x = 0; $x < $w; $x += $step) { $edge[] = imagecolorat($im, $x, 0); $edge[] = imagecolorat($im, $x, $h - 1); }
        for ($y = 0; $y < $h; $y += $step) { $edge[] = imagecolorat($im, 0, $y); $edge[] = imagecolorat($im, $w - 1, $y); }

        $rs = $gs = $bs = 0; $n = count($edge);
        foreach ($edge as $c) { $rs += ($c >> 16) & 255; $gs += ($c >> 8) & 255; $bs += $c & 255; }
        $ar = $rs / $n; $ag = $gs / $n; $ab = $bs / $n;

        $var = 0.0;
        foreach ($edge as $c) {
            $var += abs((($c >> 16) & 255) - $ar) + abs((($c >> 8) & 255) - $ag) + abs(($c & 255) - $ab);
        }
        $var /= $n; // kenar başına ortalama sapma (0–765)

        // Kenar ortalaması her karede saklanıyor: oranı tutmayan bir fotoğrafı
        // kırpmak yerine kutuya sığdırıp arkasını bu renkle dolduruyoruz, ek
        // olan şerit karenin kendi kenarıyla aynı tonda olsun diye.
        $entry['bg'] = sprintf('#%02x%02x%02x', (int)round($ar), (int)round($ag), (int)round($ab));

        // 16'nın altı: göz "düz zemin" olarak görüyor — paket çekimi, arkası
        // düz renkle doldurulunca dikiş yeri görünmüyor. Üstü sokak çekimi ya
        // da mekân; orada düz renk yerine karenin bulanık kopyası konuyor.
        if ($var < 16.0) { $entry['flat'] = 1; $flat++; }

        // ---- özne ağırlık merkezi: zeminden sapan pikseller
        $tx = $ty = 0.0; $m = 0.0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $d = abs((($c >> 16) & 255) - $ar) + abs((($c >> 8) & 255) - $ag) + abs(($c & 255) - $ab);
                if ($d < 30) continue;
                $wgt = min(1.0, $d / 150);
                $tx += $x * $wgt; $ty += $y * $wgt; $m += $wgt;
            }
        }
        if ($m > 5) {
            $entry['cx'] = (int)round(($tx / $m) / max(1, $w - 1) * 100);
            $entry['cy'] = (int)round(($ty / $m) / max(1, $h - 1) * 100);
        }

        // Doluluk: kadrajın yüzde kaçı zeminden farklı. Beslemede içi boş
        // kareler var — kimi tamamen tek renk, kimi köşesinde bir gölge.
        // Kartta bembeyaz bir kutu olarak çıkıyorlar. Ölçü burada duruyor,
        // ayıklamayı tools/drop-blank-photos.php yapıyor.
        $px = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                if (abs((($c >> 16) & 255) - $ar) + abs((($c >> 8) & 255) - $ag) + abs(($c & 255) - $ab) > 30) $px++;
            }
        }
        $entry['fill'] = round($px / max(1, $w * $h), 4);

        // ---- kaç özne var: düz zeminli karelerde sütun kütlesi profili
        //
        // Toptancının bazı kareleri iki ya da üç mankeni yan yana koyuyor.
        // Ürün sayfasında bilgi; 4:5 vitrin karosunda ise aynı yüz iki kez
        // görünüyor ve sayfa çoğaltılmış gibi duruyor. Sütun profilinde zemin
        // boşluğuyla ayrılmış küme sayısı bunu ele veriyor.
        if (!empty($entry['flat']) || $var < 34.0) {
            $col = array_fill(0, $w, 0.0);
            for ($x = 0; $x < $w; $x++) {
                for ($y = 0; $y < $h; $y++) {
                    $c = imagecolorat($im, $x, $y);
                    $d = abs((($c >> 16) & 255) - $ar) + abs((($c >> 8) & 255) - $ag) + abs(($c & 255) - $ab);
                    if ($d > 30) $col[$x] += 1.0;
                }
            }
            $peak = max($col);
            if ($peak > 0) {
                $groups = 0; $run = 0; $gap = 0; $minRun = max(2, (int)round($w * 0.06));
                foreach ($col as $v) {
                    if ($v > $peak * 0.14) {
                        if ($gap >= $minRun || $run === 0) { /* yeni küme başlıyor */ }
                        $run++; $gap = 0;
                    } else {
                        if ($run >= $minRun) { $groups++; }
                        if ($run > 0) $run = 0;
                        $gap++;
                    }
                }
                if ($run >= $minRun) $groups++;
                if ($groups > 1) $entry['n'] = $groups;
            }

            // ---- alt kenardaki yazı şeridi
            //
            // Bölünmüş kontakt panellerinin bir kısmında altta model kodu
            // kalıyor ("DSQUARED2 S74GL0064"). Kütle profilinde ürünle şerit
            // arasında temiz bir zemin boşluğu var; boşluğun altındaki ince
            // bant yazıdan başka bir şey olamaz.
            $row = array_fill(0, $h, 0.0);
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorat($im, $x, $y);
                    $d = abs((($c >> 16) & 255) - $ar) + abs((($c >> 8) & 255) - $ag) + abs(($c & 255) - $ab);
                    if ($d > 30) $row[$y] += 1.0;
                }
            }
            $rpeak = max($row);
            if ($rpeak > 0) {
                $thr = $rpeak * 0.05;
                // en alttan yukarı: yazı bandı, sonra boşluk, sonra ürün
                $y = $h - 1;
                while ($y >= 0 && $row[$y] <= $thr) $y--;      // alttaki boş kenar
                $bandBottom = $y;
                while ($y >= 0 && $row[$y] > $thr)  $y--;      // bandın kendisi
                $bandTop = $y + 1;
                $gapEnd = $y;
                while ($y >= 0 && $row[$y] <= $thr) $y--;      // boşluk
                $gapLen = $gapEnd - $y;

                $bandH = $bandBottom - $bandTop + 1;
                // Bant ince (yükseklikte %9'un altı), üstünde gerçek bir boşluk
                // var ve boşluğun üstünde hâlâ ürün duruyor — üç şart birden.
                if ($y >= (int)($h * 0.25) && $bandH > 0 && $bandH < $h * 0.09
                    && $gapLen >= max(2, (int)($h * 0.02))) {
                    $entry['trim'] = round(($h - $bandTop) / $h, 3);
                }
            }
        }

        imagedestroy($im);
    }

    $out[$rel] = $entry;
    $done++;
    if ($done % 100 === 0) { printf("  … %d\n", $done); }
}

ksort($out);
vr_store_write('photo-shape.json', $out);

printf("\nölçülen : %d\n", $done);
printf("atlanan : %d (indekste vardı)\n", $skip);
printf("okunmadı: %d\n", $fail);
printf("düz zemin: %d\n", $flat);
printf("toplam indeks: %d\n", count($out));
echo "Kaydedildi: " . vr_store_path('photo-shape.json') . "\n";
