<?php
/**
 * Çok görünümlü şeritleri ayır — CLI
 * ==================================
 *   php tools/split-multiview.php --dry
 *   php tools/split-multiview.php --sheet      (kontrol kolajı üretir)
 *   php tools/split-multiview.php
 *
 * NE YAPIYOR
 * ----------
 * Toptancının kadrajlarının bir kısmı tek bir fotoğraf değil, AYNI ürünün iki
 * ile beş görünümü yan yana: önden, arkadan, düz serilmiş. Bunlar ürün
 * sayfasında bilgi, ama 4:5 vitrin karosunda üç minik silüet olarak duruyor.
 *
 * Şeridi görünümlere ayırınca iki sorun birden çözülüyor: karo dolu bir tek
 * kare gösteriyor VE ürünün ikinci-üçüncü fotoğrafı oluyor. Bu ürünlerin
 * çoğunun başka karesi yoktu; fotoğraf sayısı buradan geliyor.
 *
 * split-contact-sheets.php'den farkı: o araç bir kadrajdaki FARKLI ürünleri
 * ayırıp her birini kendi ürününe bağlamaya çalışıyor. Bu araç aynı ürünün
 * görünümlerini ayırıyor — hangi ürüne ait olduğu zaten belli, eşleştirme
 * sorunu yok, o yüzden çok daha güvenli.
 *
 * NASIL KARAR VERİYOR
 * -------------------
 * Sütun kütlesi profili: zeminden sapan piksellerin sütun sütun toplamı. Düz
 * zeminli çekimde görünümler arasında kütlenin sıfıra indiği temiz koridorlar
 * var. Koridor yeterince genişse (kadrajın %2.5'i) orası kesim yeri.
 *
 * Koridorun boş değil yalnızca İNCE olduğu şeritler için ikinci bir yöntem
 * var (mv_dips): kütle sıfıra inmiyor ama belirgin biçimde çöküyor. Orada
 * asıl fren çöküşün kendisi değil, parçaların eşit çıkması — panel şeridi
 * eşit böler, bir paltonun beli bölmez.
 *
 * Denenip BIRAKILAN üçüncü bir yol: "paneller eşittir, kadrajı k'ya böl".
 * Ölçüm gerektirmediği için cazipti; kesim çizgileri mankenlerin ortasından
 * geçti, çünkü paneller çoğu zaman eşit değil ve eşitlik tek başına yeterli
 * kanıt sayılamıyor. Hiçbir yöntemin tutmadığı şeritler olduğu gibi kalıyor;
 * kart onları kırpmadan, bulanık zemin üstünde bütün gösteriyor (vr_img_fit).
 * Kötü bir kesim, sığdırılmış bir şeritten çok daha kötü.
 *
 * Güvenlik frenleri — biri bile tutmazsa dosyaya dokunulmuyor:
 *   · zemin düz olacak (ölçüm: data/photo-shape.json "flat")
 *   · en az iki, en çok altı parça çıkacak
 *   · her parça en az 300 px geniş, oranı 0.28–1.70 arasında
 *   · her parça toplam kütlenin en az %7'sini taşıyacak (boş dilim olmasın)
 *   · parçaların toplam genişliği kadrajın %60'ından fazla olacak
 *
 * GERİ ALINABİLİR
 * ---------------
 * Özgün dosya diskte kalıyor, yalnızca ürünün fotoğraf listesinden çıkıyor
 * (parçalar onun yerine geçiyor). --restore listeyi eski hâline döndürür.
 *
 * Ardından: php tools/index-photo-shape.php && php tools/index-photo-quality.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$dry     = in_array('--dry', $argv, true);
$sheet   = in_array('--sheet', $argv, true);
$restore = in_array('--restore', $argv, true);

$root  = vr_doc_root();
$file  = vr_data_dir() . '/retail-imported.json';
$ledger = vr_data_dir() . '/multiview-split.json';

// ---------------------------------------------------------------- geri alma
if ($restore) {
    $log = json_decode((string)@file_get_contents($ledger), true);
    if (!is_array($log) || !$log) { echo "Kayıt yok — geri alınacak bir şey yok.\n"; exit(0); }
    $rows = json_decode((string)@file_get_contents($file), true);
    if (!is_array($rows)) { echo "HATA: retail-imported.json okunamadı.\n"; exit(1); }
    $n = 0;
    foreach ($rows as $k => $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '' || empty($log[$id])) continue;
        $rows[$k]['images'] = $log[$id];
        $n++;
    }
    file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    @unlink($ledger);
    printf("%d ürünün fotoğraf listesi eski hâline döndü.\n", $n);
    exit(0);
}

$shape = (array)vr_store_read('photo-shape.json', []);
if (!$shape) { echo "Önce: php tools/index-photo-shape.php\n"; exit(1); }

/**
 * Şeridi görünümlere ayır. Dönüş: [[x, genişlik], …] ya da boş dizi.
 *
 * Ölçüm 400 px genişliğe küçültülmüş kopyada; sonuç tam çözünürlüğe
 * ölçeklenerek dönüyor.
 */
function mv_columns(string $abs, int $fullW, int $fullH, ?array &$profile = null): array
{
    $src = @imagecreatefromjpeg($abs);
    if (!$src) return [];

    $w = imagesx($src); $h = imagesy($src);
    $s = 400 / max(1, $w);
    if ($s < 1) {
        $t = imagecreatetruecolor(400, (int)max(1, round($h * $s)));
        imagecopyresampled($t, $src, 0, 0, 0, 0, imagesx($t), imagesy($t), $w, $h);
        imagedestroy($src);
        $src = $t;
    }
    $w = imagesx($src); $h = imagesy($src);

    // zemin rengi: dört kenarın ortalaması
    $rs = $gs = $bs = 0; $n = 0;
    for ($x = 0; $x < $w; $x += 2) {
        foreach ([0, $h - 1] as $y) { $c = imagecolorat($src, $x, $y); $rs += ($c >> 16) & 255; $gs += ($c >> 8) & 255; $bs += $c & 255; $n++; }
    }
    for ($y = 0; $y < $h; $y += 2) {
        foreach ([0, $w - 1] as $x) { $c = imagecolorat($src, $x, $y); $rs += ($c >> 16) & 255; $gs += ($c >> 8) & 255; $bs += $c & 255; $n++; }
    }
    $ar = $rs / $n; $ag = $gs / $n; $ab = $bs / $n;

    // Alt şerit ölçüme girmiyor. Kadrajların altında toptancının bastığı model
    // kodu var ve o yazı ortada, tam koridorun geçtiği yerde duruyor: koridoru
    // dolduruyor, profil "her sütun dolu" diyor ve üç görünümlük apaçık bir
    // şerit bölünmeden kalıyordu. Ölçülen dört örnekte sebep buydu.
    $yMax = (int)round($h * 0.88);

    $col = array_fill(0, $w, 0.0);
    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $yMax; $y++) {
            $c = imagecolorat($src, $x, $y);
            $d = abs((($c >> 16) & 255) - $ar) + abs((($c >> 8) & 255) - $ag) + abs(($c & 255) - $ab);
            if ($d > 30) $col[$x] += 1.0;
        }
    }
    imagedestroy($src);

    // Profil ikinci yönteme de lazım — bu yöntem boş dönse bile elde kalsın.
    $profile = ['col' => $col, 'w' => $w];

    $peak = max($col);
    if ($peak <= 0) return [];

    $thr    = $peak * 0.035;                 // bunun altı "boş sütun"
    $minGap = max(3, (int)round($w * 0.016));

    // dolu koşuları bul
    $runs = []; $start = null;
    for ($x = 0; $x < $w; $x++) {
        if ($col[$x] > $thr) { if ($start === null) $start = $x; }
        else {
            if ($start !== null) { $runs[] = [$start, $x - 1]; $start = null; }
        }
    }
    if ($start !== null) $runs[] = [$start, $w - 1];
    if (count($runs) < 2) return [];

    // aralarındaki boşluk dar olan koşuları birleştir (aynı görünümün kolu
    // gövdeden ayrı düşmüş olabilir)
    $merged = [$runs[0]];
    for ($i = 1; $i < count($runs); $i++) {
        $last = &$merged[count($merged) - 1];
        if ($runs[$i][0] - $last[1] - 1 < $minGap) { $last[1] = $runs[$i][1]; }
        else { $merged[] = $runs[$i]; }
        unset($last);
    }
    if (count($merged) < 2 || count($merged) > 6) return [];

    // her parçanın kütlesi
    $total = array_sum($col);
    $out = [];
    foreach ($merged as [$a, $b]) {
        $mass = 0.0;
        for ($x = $a; $x <= $b; $x++) $mass += $col[$x];
        if ($mass < $total * 0.07) return [];        // boş dilim → hepsini bırak

        // küçük bir pay bırak, kenarı yapışık durmasın
        $pad = max(2, (int)round(($b - $a) * 0.04));
        $a = max(0, $a - $pad); $b = min($w - 1, $b + $pad);

        $x0 = (int)round($a / $w * $fullW);
        $x1 = (int)round(($b + 1) / $w * $fullW);
        $pw = $x1 - $x0;
        if ($pw < 300) return [];
        $r = $pw / $fullH;
        if ($r < 0.28 || $r > 1.70) return [];
        $out[] = [$x0, $pw];
    }

    $sum = 0; foreach ($out as [, $pw]) $sum += $pw;
    if ($sum < $fullW * 0.60) return [];

    return $out;
}

/**
 * İkinci yöntem: koridoru boş DEĞİL, yalnızca ince olan şeritler.
 *
 * Bir kısım kadrajda paneller bitişik ve zemin gri: iki görünüm arasında
 * kütle sıfıra inmiyor, sadece belirgin biçimde çöküyor (ölçülen bir Fendi
 * karesinde 344'ten 175'e). Birinci yöntem burada hiçbir şey göremiyor.
 *
 * Yalnız "çöküş var" demek tehlikeli: bir paltonun beli de çöküş yapar.
 * Bu yüzden asıl fren çöküşün kendisi değil, SONUCUN EŞİTLİĞİ — panel
 * şeritleri eşit bölünür, bir giysinin beli eşit bölmez. Parçalar
 * birbirinden %12'den fazla farklıysa kesim yapılmıyor.
 *
 * @return array<int,array{0:int,1:int}> [[x, genişlik], …]
 */
function mv_dips(array $col, int $w, int $fullW, int $fullH): array
{
    $sorted = $col; sort($sorted);
    $median = $sorted[intdiv(count($sorted), 2)];
    if ($median <= 1) return [];

    // çöküş koşuları
    $dips = []; $start = null;
    for ($x = 0; $x < $w; $x++) {
        if ($col[$x] < $median * 0.65) { if ($start === null) $start = $x; }
        elseif ($start !== null) { $dips[] = [$start, $x - 1]; $start = null; }
    }
    if ($start !== null) $dips[] = [$start, $w - 1];

    $cuts = [];
    foreach ($dips as [$a, $b]) {
        $len = $b - $a + 1;
        if ($len < max(2, (int)round($w * 0.008)) || $len > $w * 0.07) continue;
        // kenardaki çöküş kesim değil, kadrajın kendi boşluğu
        if ($a < $w * 0.12 || $b > $w * 0.88) continue;
        // iki yanı da dolu olacak: gerçek bir dikiş, tek taraflı bir iniş değil
        $l = $col[max(0, $a - 5)] ?? 0;
        $r = $col[min($w - 1, $b + 5)] ?? 0;
        if ($l < $median * 0.92 || $r < $median * 0.92) continue;
        $cuts[] = (int)round(($a + $b) / 2);
    }
    if (!$cuts || count($cuts) > 4) return [];

    // kesim noktalarından parçalar
    $bounds = array_merge([0], $cuts, [$w]);
    $parts = [];
    for ($i = 0; $i < count($bounds) - 1; $i++) {
        $pw = $bounds[$i + 1] - $bounds[$i];
        if ($pw < $w * 0.15) return [];
        $parts[] = [$bounds[$i], $pw];
    }

    // EŞİTLİK FRENİ — panel şeridi eşit böler, giysinin beli bölmez
    $widths = array_column($parts, 1);
    if (max($widths) > min($widths) * 1.12) return [];

    // tam çözünürlüğe ölçekle ve ortak frenlerden geçir
    $out = [];
    foreach ($parts as [$px, $pw]) {
        $x0 = (int)round($px / $w * $fullW);
        $x1 = (int)round(($px + $pw) / $w * $fullW);
        $fw = $x1 - $x0;
        if ($fw < 300) return [];
        $r = $fw / $fullH;
        if ($r < 0.28 || $r > 1.70) return [];
        $out[] = [$x0, $fw];
    }
    return $out;
}

// ---------------------------------------------------------------- çalışma
$catalog = vr_catalog();
printf("MAXSALES — çok görünümlü şerit ayırma%s\n%s\n", $dry ? ' (Probelauf)' : '', str_repeat('=', 66));

$plan = [];      // id => ['old' => [...], 'new' => [...], 'cuts' => [rel => [[x,w],…]]]
$look = $split = $keep = 0;

foreach ($catalog as $id => $p) {
    $imgs = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));
    if (!$imgs) continue;

    $newList = [];
    $cuts = [];
    $touched = false;

    foreach ($imgs as $rel) {
        $s = $shape[$rel] ?? null;
        $n = (int)($s['n'] ?? 1);
        // Ön eleme yok: karar koridor testinin kendisinde. Ölçümdeki özne
        // sayısı yalnızca düz zeminli karelerde güvenilir, oysa şeritlerin bir
        // kısmının zemini hafif gölgeli; ön elemeye güvenince yarısı
        // kaçıyordu. 1722 kareyi 400 px'te taramak bir dakika sürüyor,
        // araç zaten elde çalıştırılıyor.
        if (!str_ends_with(strtolower($rel), '.jpg')) {
            $newList[] = $rel;
            continue;
        }
        $look++;

        $abs = $root . $rel;
        $info = @getimagesize($abs);
        if (!$info) { $newList[] = $rel; continue; }

        $profile = null;
        $parts = mv_columns($abs, (int)$info[0], (int)$info[1], $profile);

        // Boş koridor yoksa ince koridor arıyoruz (bkz. mv_dips).
        if (count($parts) < 2 && is_array($profile)) {
            $parts = mv_dips($profile['col'], $profile['w'], (int)$info[0], (int)$info[1]);
        }

        if (count($parts) < 2) { $newList[] = $rel; $keep++; continue; }

        $cuts[$rel] = $parts;
        $base = preg_replace('/\.jpg$/i', '', $rel);
        foreach ($parts as $i => $_) $newList[] = $base . '-v' . ($i + 1) . '.jpg';
        $split++;
        $touched = true;
    }

    if ($touched) $plan[$id] = ['old' => $imgs, 'new' => array_values(array_unique($newList)), 'cuts' => $cuts];
}

printf("bakılan şerit : %d\n", $look);
printf("ayrılacak     : %d\n", $split);
printf("dokunulmayan  : %d (koridor bulunamadı ya da fren tuttu)\n", $keep);
printf("etkilenen ürün: %d\n\n", count($plan));

// ---- kontrol kolajı: kesim çizgileriyle birlikte ilk 12 şerit
if ($sheet) {
    $cols = 4; $cw = 380; $ch = 300; $i = 0;
    $out = imagecreatetruecolor($cols * $cw, 3 * $ch);
    imagefilledrectangle($out, 0, 0, $cols * $cw, 3 * $ch, imagecolorallocate($out, 235, 235, 235));
    $red = imagecolorallocate($out, 230, 30, 30);
    foreach ($plan as $pl) {
        foreach ($pl['cuts'] as $rel => $parts) {
            if ($i >= 12) break 2;
            $im = @imagecreatefromjpeg($root . $rel);
            if (!$im) continue;
            $iw = imagesx($im); $ih = imagesy($im);
            $x = ($i % $cols) * $cw; $y = intdiv($i, $cols) * $ch;
            imagecopyresampled($out, $im, $x, $y, 0, 0, $cw, $ch, $iw, $ih);
            foreach ($parts as [$px, $pw]) {
                $lx = $x + (int)round($px / $iw * $cw);
                $rx = $x + (int)round(($px + $pw) / $iw * $cw);
                imageline($out, $lx, $y, $lx, $y + $ch, $red);
                imageline($out, $rx, $y, $rx, $y + $ch, $red);
            }
            imagedestroy($im);
            $i++;
        }
    }
    $dst = sys_get_temp_dir() . '/multiview-check.jpg';
    imagejpeg($out, $dst, 88);
    echo "Kontrol kolajı: {$dst}\n";
}

if ($dry || !$plan) { echo $dry ? "Probelauf — nichts geschrieben.\n" : "Yapılacak bir şey yok.\n"; exit(0); }

// ---- kesimleri diske yaz
$written = 0; $failed = 0;
foreach ($plan as $id => $pl) {
    foreach ($pl['cuts'] as $rel => $parts) {
        $src = @imagecreatefromjpeg($root . $rel);
        if (!$src) { $failed++; continue; }
        $ih = imagesy($src);
        $base = preg_replace('/\.jpg$/i', '', $rel);
        foreach ($parts as $i => [$x0, $pw]) {
            $dst = imagecreatetruecolor($pw, $ih);
            imagecopy($dst, $src, 0, 0, $x0, 0, $pw, $ih);
            $abs = $root . $base . '-v' . ($i + 1) . '.jpg';
            $tmp = $abs . '.tmp';
            if (imagejpeg($dst, $tmp, 90) && @rename($tmp, $abs)) { @chmod($abs, 0644); $written++; }
            else { @unlink($tmp); $failed++; }
            imagedestroy($dst);
        }
        imagedestroy($src);
    }
}

// ---- ürün listelerini güncelle
$rows = json_decode((string)@file_get_contents($file), true);
if (!is_array($rows)) { echo "HATA: retail-imported.json okunamadı.\n"; exit(1); }

// Kayıt defteri BİRİKİYOR: araç birden fazla kez çalıştırılıyor (bir turda
// ayrılan parçanın içinde yeni bir koridor çıkabiliyor). Dosyayı her turda
// baştan yazsaydık ilk turun özgün listeleri kaybolur ve --restore yarım
// kalırdı. Bir ürünün ilk kaydı korunuyor — geri dönülecek yer orası.
$log = json_decode((string)@file_get_contents($ledger), true);
if (!is_array($log)) $log = [];
$touched = 0;
foreach ($rows as $k => $row) {
    if (!is_array($row)) continue;
    $id = (string)($row['id'] ?? '');
    if ($id === '' || empty($plan[$id])) continue;
    if (!isset($log[$id])) $log[$id] = array_values(array_filter((array)($row['images'] ?? []), 'strlen'));
    $rows[$k]['images'] = $plan[$id]['new'];
    $touched++;
}
file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
file_put_contents($ledger, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

printf("yazılan parça : %d\n", $written);
printf("hata          : %d\n", $failed);
printf("güncellenen ürün: %d\n\n", $touched);
echo "Geri almak için: php tools/split-multiview.php --restore\n";
echo "Ardından: php tools/index-photo-shape.php && php tools/index-photo-quality.php\n";
