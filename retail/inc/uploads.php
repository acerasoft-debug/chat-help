<?php
/**
 * Satıcı görsel yükleme
 * =====================
 * Yüklenen dosya İÇERİĞİNE bakılarak kabul edilir, adına/uzantısına göre değil.
 * Katmanlar:
 *
 *  1) getimagesize() ile gerçek tür tespiti — "resim.jpg" adlı bir PHP dosyası
 *     buradan geçemez.
 *  2) GD varsa dosya YENİDEN KODLANIR. Bu hem EXIF/GPS gibi kişisel veriyi
 *     temizler (DSGVO veri minimizasyonu) hem de görselin içine gizlenmiş
 *     yürütülebilir yükü (polyglot) yok eder.
 *  3) Dosya adı bizim ürettiğimiz rastgele addır; kullanıcıdan gelen ad hiç
 *     kullanılmaz (yol kaçışı / çift uzantı riski kalmaz).
 *  4) Hedef klasöre PHP çalıştırmayı kapatan bir .htaccess yazılır.
 *
 * Hedef: /uploads/retail/<satici>/… — canlı sitenin görsel şeması (/uploads/…)
 * ile aynı, böylece mevcut katalog araçları bu yolları da tanıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_UPLOAD_MAX_BYTES = 8388608;   // 8 MB
const VR_UPLOAD_MAX_FILES = 6;
const VR_UPLOAD_MAX_EDGE  = 2000;      // px — daha büyükse küçültülür

/**
 * Web köküne (doc root) mutlak yol — /uploads/... yollarının çözüldüğü yer.
 *
 * Burada dikkat: web isteğinde DOCUMENT_ROOT hazır gelir, CLI'da gelmez. İkisi
 * farklı dizini gösterirse yükleme bir yere yazılır, moderasyon aracı başka
 * yerde arar ve "dosya yok" der. Bu yüzden CLI tarafında dizini TAHMİN etmek
 * yerine uploads/ klasörünü ARIYORUZ — hangi seviyede duruyorsa web kökü odur.
 * Kesin çözüm: VR_DOC_ROOT ortam değişkenini ayarlamak (bkz. README).
 */
function vr_doc_root(): string
{
    static $root = null;
    if ($root !== null) return $root;

    $env = getenv('VR_DOC_ROOT');
    if (is_string($env) && $env !== '' && is_dir($env)) return $root = rtrim($env, '/');

    $dr = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($dr !== '' && is_dir($dr)) return $root = rtrim($dr, '/');

    // uploads/ nerede duruyorsa web kökü orası: retail/ tek başına bir vhost
    // olabilir (kök = VR_ROOT) ya da public_html altında bir alt klasör olabilir
    // (kök = üst dizin).
    foreach ([VR_ROOT, VR_ROOT . '/..'] as $cand) {
        if (is_dir($cand . '/uploads')) return $root = rtrim(realpath($cand) ?: $cand, '/');
    }
    return $root = rtrim(realpath(VR_ROOT) ?: VR_ROOT, '/');
}

/** /uploads/... yolu sunucuda gerçekten var mı? */
function vr_upload_exists(string $webPath): bool
{
    if (!preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $webPath)) return false;
    return is_file(vr_doc_root() . $webPath);
}

/**
 * $_FILES['x'] (çoklu) girdisini işle.
 * Dönüş: ['paths' => ['/uploads/...'], 'errors' => ['...']]
 */
function vr_upload_images(array $files, string $sellerId): array
{
    $paths  = [];
    $errors = [];

    if (empty($files['name']) || !is_array($files['name'])) return ['paths' => $paths, 'errors' => $errors];

    $dirWeb = '/uploads/retail/' . preg_replace('/[^A-Za-z0-9-]/', '', $sellerId);
    $dirAbs = vr_doc_root() . $dirWeb;

    if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true)) {
        return ['paths' => [], 'errors' => ['Upload-Ordner konnte nicht erstellt werden: ' . $dirWeb]];
    }
    vr_upload_harden($dirAbs);

    $count = min(count($files['name']), VR_UPLOAD_MAX_FILES);

    for ($i = 0; $i < $count; $i++) {
        $err = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;

        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload-Fehler (' . $err . ') bei Datei ' . ($i + 1);
            continue;
        }

        $tmp  = (string)($files['tmp_name'][$i] ?? '');
        $size = (int)($files['size'][$i] ?? 0);

        // is_uploaded_file: yalnızca gerçekten bu istekle yüklenmiş geçici
        // dosyayı kabul et — yerel bir yolun enjekte edilmesini engeller.
        if ($tmp === '' || !is_uploaded_file($tmp)) { $errors[] = 'Ungültiger Upload.'; continue; }
        if ($size <= 0 || $size > VR_UPLOAD_MAX_BYTES) {
            $errors[] = 'Datei ' . ($i + 1) . ' ist größer als ' . (int)(VR_UPLOAD_MAX_BYTES / 1048576) . ' MB.';
            continue;
        }

        $info = @getimagesize($tmp);
        if ($info === false) { $errors[] = 'Datei ' . ($i + 1) . ' ist kein Bild.'; continue; }

        $ext = match ((int)$info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
            default        => '',
        };
        if ($ext === '') { $errors[] = 'Nur JPG, PNG oder WebP.'; continue; }

        $name    = bin2hex(random_bytes(8)) . '.' . $ext;
        $absDest = $dirAbs . '/' . $name;

        if (!vr_upload_store($tmp, $absDest, (int)$info[2], (int)$info[0], (int)$info[1])) {
            $errors[] = 'Datei ' . ($i + 1) . ' konnte nicht gespeichert werden.';
            continue;
        }

        @chmod($absDest, 0644);
        $paths[] = $dirWeb . '/' . $name;
    }

    return ['paths' => $paths, 'errors' => $errors];
}

/**
 * Dosyayı hedefe yaz. GD varsa yeniden kodla (metadata temizliği + küçültme),
 * yoksa doğrudan taşı.
 */
function vr_upload_store(string $tmp, string $dest, int $type, int $w, int $h): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        return @move_uploaded_file($tmp, $dest);
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default        => false,
    };
    if (!$src) return @move_uploaded_file($tmp, $dest);

    // Uzun kenarı sınırla: 6000 px'lik telefon fotoğrafı vitrine gerekmiyor.
    $scale = min(1.0, VR_UPLOAD_MAX_EDGE / max(1, max($w, $h)));
    if ($scale < 1.0) {
        $nw  = max(1, (int)round($w * $scale));
        $nh  = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($src, $dest, 86),
        IMAGETYPE_PNG  => imagepng($src, $dest, 7),
        IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($src, $dest, 86) : false,
        default        => false,
    };
    imagedestroy($src);
    return (bool)$ok;
}

/**
 * Yükleme klasörünü sertleştir: Apache'de PHP/CGI çalıştırmayı kapat.
 * nginx'te .htaccess okunmaz — README'de nginx karşılığı da veriliyor.
 */
function vr_upload_harden(string $dirAbs): void
{
    $f = $dirAbs . '/.htaccess';
    if (is_file($f)) return;

    @file_put_contents($f, implode("\n", [
        '# Yuklenen dosyalar SADECE statik icerik olarak servis edilir.',
        'php_flag engine off',
        'RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps',
        'RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps',
        '<FilesMatch "\\.(?i:php[0-9]?|phtml|phps|pl|py|cgi|sh|htaccess)$">',
        '  Require all denied',
        '</FilesMatch>',
        '',
    ]));
}
