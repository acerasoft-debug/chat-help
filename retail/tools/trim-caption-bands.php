<?php
/**
 * Alt kenardaki model kodu şeridini kırp — CLI
 * ============================================
 *   php tools/trim-caption-bands.php --dry
 *   php tools/trim-caption-bands.php
 *   php tools/trim-caption-bands.php --restore
 *
 * NEDEN
 * -----
 * Kontakt sayfalarından ayrılan panellerin bir kısmında, ürünün altında
 * toptancının bastığı model kodu duruyor: "DSQUARED2 S74GL0064". Ürün
 * sayfasında kimsenin umurunda değil, ama vitrin karosunda göze ilk çarpan şey
 * o satır oluyor — mağaza bir anda katalog çıktısına benziyor.
 *
 * Şerit nerede biliniyor: data/photo-shape.json ölçümünde "trim" alanı, alttan
 * kesilecek oranı veriyor (ürünle şerit arasında temiz bir zemin boşluğu
 * olduğu için ayrım güvenli). Bu araç o oranı uygulayıp dosyayı yerinde
 * yeniden yazıyor.
 *
 * GERİ ALINABİLİR
 * ---------------
 * Kesmeden önce özgün dosya uploads/.orig/ altına aynı yolla kopyalanıyor;
 * --restore hepsini geri koyuyor. Kesim yanlış giderse kaynak kontakt
 * sayfasını yeniden bölmeye gerek yok.
 *
 * uploads/.orig depoya girmiyor (25 MB'lık ikinci bir kopya). Asıl güvence
 * git geçmişi: fotoğraflar izleniyor, kesimden önceki hâlleri orada duruyor
 * ve `git checkout <commit> -- retail/uploads/...` ile geri gelir.
 *
 * Kesimden sonra: php tools/index-photo-shape.php --all
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
$restore = in_array('--restore', $argv, true);

$root = vr_doc_root();
$orig = $root . '/uploads/.orig';

// ---------------------------------------------------------------- geri alma
if ($restore) {
    if (!is_dir($orig)) { echo "uploads/.orig yok — geri alınacak bir şey yok.\n"; exit(0); }
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($orig, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = substr($f->getPathname(), strlen($orig));
        $dst = $root . '/uploads' . $rel;
        if (!is_dir(dirname($dst))) continue;
        if (@copy($f->getPathname(), $dst)) { $n++; }
    }
    printf("%d dosya geri konuldu.\n", $n);
    echo "Ardından: php tools/index-photo-shape.php --all\n";
    exit(0);
}

// ---------------------------------------------------------------- kırpma
$shape = (array)vr_store_read('photo-shape.json', []);
if (!$shape) { echo "data/photo-shape.json yok — önce php tools/index-photo-shape.php\n"; exit(1); }

printf("MAXSALES — yazı şeridi kırpma%s\n%s\n", $dry ? ' (Probelauf)' : '', str_repeat('=', 62));

$cut = $skipSmall = $skipOdd = $fail = 0;
$empty = [];

foreach ($shape as $rel => $s) {
    $tr = (float)($s['trim'] ?? 0);
    if ($tr <= 0) continue;

    // %16'dan fazlasını kesmek şerit değil, ürünün bir parçasını atmak olur.
    // %1'in altı ölçüm gürültüsü — dosyayı boşuna yeniden yazmayalım.
    if ($tr > 0.16 || $tr < 0.012) { $skipOdd++; continue; }

    $abs = $root . $rel;
    if (!is_file($abs)) { $fail++; continue; }

    $info = @getimagesize($abs);
    if (!$info || $info[2] !== IMAGETYPE_JPEG) { $skipOdd++; continue; }

    $w = $info[0]; $h = $info[1];
    $nh = (int)round($h * (1 - $tr));
    if ($nh < 400 || $nh >= $h) { $skipSmall++; continue; }

    if ($dry) { printf("  %-62s %d → %d px\n", substr($rel, -62), $h, $nh); $cut++; continue; }

    $src = @imagecreatefromjpeg($abs);
    if (!$src) { $fail++; continue; }

    $dst = imagecreatetruecolor($w, $nh);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $nh);
    imagedestroy($src);

    // özgün dosyayı sakla
    $bak = $orig . substr($rel, strlen('/uploads'));
    if (!is_dir(dirname($bak))) @mkdir(dirname($bak), 0755, true);
    if (!is_file($bak)) @copy($abs, $bak);

    $tmp = $abs . '.tmp';
    if (!imagejpeg($dst, $tmp, 88)) { imagedestroy($dst); $fail++; @unlink($tmp); continue; }
    imagedestroy($dst);
    @chmod($tmp, 0644);
    if (!@rename($tmp, $abs)) { @unlink($tmp); $fail++; continue; }
    $cut++;
}

printf("\nkırpıldı  : %d\n", $cut);
printf("oran tuhaf: %d (%%1.2 altı ya da %%16 üstü — dokunulmadı)\n", $skipOdd);
printf("çok küçük : %d\n", $skipSmall);
printf("hata      : %d\n", $fail);

if (!$dry && $cut) {
    echo "\nÖzgün dosyalar: uploads/.orig/ (geri almak için --restore)\n";
    echo "Ardından: php tools/index-photo-shape.php --all\n";
}
