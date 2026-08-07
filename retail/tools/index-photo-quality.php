<?php
/**
 * Fotoğraf kalite indeksi — CLI
 * =============================
 *   php tools/index-photo-quality.php
 *
 * Her fotoğraf için "tek özneli çekim mi, ızgara kontakt sayfası mı" sorusunu
 * bir kez cevaplar ve data/photo-quality.json'a yazar.
 *
 * NEDEN ÖNCEDEN
 * -------------
 * Testin kendisi ucuz değil (her dosya için birkaç bin piksel okuması) ve
 * sayfa isteği sırasında yapılacak iş değil. Ama cevabı bilmek çok işe
 * yarıyor: ana sayfadaki tam boy editoryal karolar ve kampanya filmi, içinde
 * sekiz küçük ürün ve toptancı kodu olan bir kadraj gösterdiğinde site
 * anında depo çıktısına benziyor.
 *
 * Sonuç yalnızca SIRALAMA için kullanılıyor, eleme için değil: ızgara olan
 * fotoğraf da ürün sayfasında görünmeye devam ediyor — orası zaten ürünün
 * kendi yeri ve alıcı bedenleri o kadrajda görüyor. Sadece vitrine
 * çıkmıyor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

/** Bir satır/sütunun "içerik" oranı (0 = tamamen beyaz). */
function pq_line(\GdImage $im, int $i, bool $cols, int $inner, int $step): float
{
    $dark = 0; $n = 0;
    for ($j = 0; $j < $inner; $j += $step) {
        $c = imagecolorat($im, $cols ? $i : $j, $cols ? $j : $i);
        if ((($c >> 16 & 255) + ($c >> 8 & 255) + ($c & 255)) / 3 < 244) $dark++;
        $n++;
    }
    return $n ? $dark / $n : 0.0;
}

/**
 * Tek özneli çekim mi, yoksa toptancının ızgara kontakt sayfası mı?
 *
 * İki ölçüt, ikisi de gerçek örneklerden ayarlandı:
 *
 * 1. ORTADAN GEÇEN BEYAZ KORİDOR. Tek gövdenin kadrajı ortasından baştan
 *    sona beyaz bir satır geçmez; ızgarada satırlar arasında geçer. Aynısı
 *    sütunlar için. Eşik bilerek gevşek: ölçtüğümüz bir Givenchy sayfasında
 *    koridor yüksekliğin yalnızca %0,8'i kadardı ve %1,2'lik eşikten
 *    kaçmıştı.
 *
 * 2. ALTTAKİ KOD YAZISI. Bazı panellerde ayraç bandı yok ama model kodu
 *    kadrajın alt kenarına basılı ("BURBERRY 801729"). İmza şu: alt şerit
 *    neredeyse tamamen beyaz, ama içinde küçük bir koyu küme var. Böyle bir
 *    kare vitrinde toptancı ilanı gibi duruyor.
 */
function pq_is_single(string $file): ?bool
{
    $s = @getimagesize($file);
    if (!$s) return null;
    $im = match ($s[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG  => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($file) : false,
        default        => false,
    };
    if (!$im instanceof \GdImage) return null;

    $w = imagesx($im); $h = imagesy($im);
    $single = true;

    // 1) Koridor.
    foreach ([false, true] as $cols) {
        $outer  = $cols ? $w : $h;
        $inner  = $cols ? $h : $w;
        $stepIn = max(1, (int)($inner / 80));
        $need   = max(3, (int)($outer * 0.006));
        $lo = (int)($outer * 0.18); $hi = (int)($outer * 0.82);
        $run = 0;

        for ($i = $lo; $i < $hi && $single; $i++) {
            if (pq_line($im, $i, $cols, $inner, $stepIn) < 0.04) {
                if (++$run >= $need) $single = false;
            } else {
                $run = 0;
            }
        }
        if (!$single) break;
    }

    // 2) Alt kenardaki kod yazısı.
    if ($single) {
        $stepIn = max(1, (int)($w / 100));
        $from = (int)($h * 0.86);
        $sum = 0.0; $max = 0.0; $n = 0;
        for ($y = $from; $y < $h; $y++) {
            $v = pq_line($im, $y, false, $w, $stepIn);
            $sum += $v; $max = max($max, $v); $n++;
        }
        $avg = $n ? $sum / $n : 1.0;
        // Neredeyse boş bir şerit + içinde belirgin bir koyu küme = yazı.
        if ($avg < 0.10 && $max > 0.04) $single = false;
    }

    imagedestroy($im);
    return $single;
}

$index = [];
$single = $grid = $bad = 0;
$seen = [];

foreach (vr_catalog() as $p) {
    foreach (array_filter((array)$p['images'], 'strlen') as $rel) {
        if (isset($seen[$rel])) continue;
        $seen[$rel] = true;
        $file = vr_doc_root() . $rel;
        if (!is_file($file)) continue;

        $v = pq_is_single($file);
        if ($v === null) { $bad++; continue; }
        // Yalnızca ızgara olanları yazıyoruz: dosya küçük kalsın, varsayılan
        // "sorun yok" olsun. Bilinmeyen bir fotoğraf cezalandırılmasın.
        if (!$v) { $index[$rel] = 1; $grid++; } else { $single++; }
    }
}

vr_store_write('photo-quality.json', $index);

printf("Geprüft : %d\n", $single + $grid);
printf("  Einzelaufnahme : %d\n", $single);
printf("  Kontaktbogen   : %d  (werden im Schaufenster nachrangig behandelt)\n", $grid);
if ($bad) printf("  nicht lesbar   : %d\n", $bad);
echo "\nGespeichert: " . vr_store_path('photo-quality.json') . "\n";
