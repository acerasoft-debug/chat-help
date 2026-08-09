<?php
/**
 * Görsel ölçekleyici — /assets/img.php?p=<yol>&w=<genişlik>
 * =========================================================
 *
 * NEDEN
 * -----
 * Toptancının dosyaları 2000–3500 piksel ve ortalama 286 KB. Bunlar ürün
 * kartına olduğu gibi gidiyordu: telefonda kart 170 piksel genişliğinde ama
 * inen dosya 2744 piksel. Yirmi dört kartlık bir mağaza sayfası yaklaşık
 * 7 MB — mobil bağlantıda sayfa ya çok geç çiziliyor ya hiç çizilmiyor.
 *
 * Burası istenen genişlikte bir kopya üretiyor ve diske yazıyor. İkinci
 * istekte dosya hazır; PHP yalnızca gönderiyor.
 *
 * WEBP
 * ----
 * Tarayıcı Accept başlığında webp diyorsa webp veriliyor: aynı kadraj
 * JPEG'in yaklaşık üçte biri kadar. Demeyen tarayıcı JPEG alıyor. İki biçim
 * ayrı önbellekleniyor, yani <picture> etiketine ve sunucu yapılandırmasına
 * gerek yok.
 *
 * GÜVENLİK
 * --------
 * Yol yalnızca /uploads/ altından olabilir. Önce desen, sonra realpath
 * kontrolü var: ".." ile dışarı çıkmak, sembolik bağla kaçmak ya da
 * uploads dışındaki bir dosyayı okutmak mümkün değil. Genişlik sabit bir
 * listeden seçiliyor — istediği sayıyı yazan biri sunucuya sınırsız iş
 * yaptıramasın.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/uploads.php';

/** İzin verilen genişlikler. Serbest sayı = sınırsız önbellek = kaynak tüketimi. */
const VR_IMG_WIDTHS = [180, 300, 420, 600, 900, 1400];

$rel = (string)($_GET['p'] ?? '');
$w   = (int)($_GET['w'] ?? 600);

if (!in_array($w, VR_IMG_WIDTHS, true)) $w = 600;

// ---- yol denetimi
if (!preg_match('#^/uploads/[A-Za-z0-9._/\-]+\.(jpe?g|png|webp|avif)$#i', $rel) || str_contains($rel, '..')) {
    http_response_code(400);
    exit;
}

$root = realpath(vr_doc_root() . '/uploads');
$src  = realpath(vr_doc_root() . $rel);
if ($root === false || $src === false || !str_starts_with($src, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}

// ---- biçim seçimi
$wantWebp = function_exists('imagewebp')
         && str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'image/webp');
$ext  = $wantWebp ? 'webp' : 'jpg';
$mime = $wantWebp ? 'image/webp' : 'image/jpeg';

// ---- önbellek yolu: kaynak yolu + değişiklik zamanı özeti
$key   = sha1($rel . '|' . filemtime($src) . '|' . $w . '|' . $ext);
$dir   = vr_doc_root() . '/uploads/.thumbs/' . substr($key, 0, 2);
$cache = $dir . '/' . $key . '.' . $ext;

/** Hazır dosyayı gönder. */
function vr_img_send(string $file, string $mime): void
{
    $etag = '"' . substr(sha1($file . filemtime($file)), 0, 20) . '"';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    // Dosya adı içeriğin özetini taşıdığı için içerik hiç değişmiyor;
    // tarayıcı ikinci kez hiç sormasın.
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if (is_file($cache) && filesize($cache) > 0) vr_img_send($cache, $mime);

// ---- üret
$im = @imagecreatefromstring((string)file_get_contents($src));
if (!$im instanceof \GdImage) {
    // Okunamayan dosya: orijinali olduğu gibi ver, kart boş kalmasın.
    header('Content-Type: ' . (mime_content_type($src) ?: 'image/jpeg'));
    header('Cache-Control: public, max-age=86400');
    readfile($src);
    exit;
}

$sw = imagesx($im);
$sh = imagesy($im);
// Büyütme yok: 400 pikselli bir kaynağı 900'e çıkarmak dosyayı büyütüp
// görüntüyü bozar, sadece bulanıklığı kalınlaştırır.
$scale = min(1.0, $w / max(1, $sw));
$nw = max(1, (int)round($sw * $scale));
$nh = max(1, (int)round($sh * $scale));

$out = imagecreatetruecolor($nw, $nh);
// Şeffaf PNG'ler ürün fotoğrafı olarak beyaz zemine oturur; kartların zemini
// zaten açık, siyah bir dörtgen çıkmasın.
imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
imagedestroy($im);

if (!is_dir($dir)) @mkdir($dir, 0755, true);

// Önce geçici dosyaya, sonra yerine taşı: yarım yazılmış bir dosya
// önbellekte kalmasın (aynı anda iki istek gelebilir).
$tmp = $cache . '.' . getmypid() . '.tmp';
$ok  = $wantWebp ? @imagewebp($out, $tmp, 82) : @imagejpeg($out, $tmp, 84);
imagedestroy($out);

if ($ok && is_file($tmp)) {
    @chmod($tmp, 0644);
    @rename($tmp, $cache);
    if (is_file($cache)) vr_img_send($cache, $mime);
    // Taşınamadıysa (izin sorunu) geçici dosyayı yine de gönder.
    vr_img_send($tmp, $mime);
}

@unlink($tmp);
header('Content-Type: ' . (mime_content_type($src) ?: 'image/jpeg'));
readfile($src);
