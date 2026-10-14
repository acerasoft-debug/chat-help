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

require_once __DIR__ . '/../inc/thumbs.php';   // VR_IMG_WIDTHS, önbellek yolu, üretim

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

// ---- önbellek yolu: kaynak yolu + değişiklik zamanı özeti (bkz. inc/thumbs.php)
$cache = vr_thumb_path($rel, $src, $w, $ext);

/** Hazır dosyayı gönder. */
function vr_img_send(string $file, string $mime): void
{
    $etag = '"' . substr(sha1($file . filemtime($file)), 0, 20) . '"';
    header('Content-Type: ' . $mime);
    // Aynı adres webp ya da jpeg dönebiliyor: aradaki önbellek karıştırmasın.
    header('Vary: Accept');
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

if (vr_thumb_fresh($cache, $src)) vr_img_send($cache, $mime);

// ---- üret (önbellekte yoksa — normalde tools/warm-thumbs.php önceden üretir)
$im = vr_thumb_open($src);
if ($im !== null) $im = vr_thumb_prepare($im, $rel);
if ($im === null) {
    // Okunamayan ya da çok büyük dosya: orijinali olduğu gibi ver, kart boş kalmasın.
    vr_img_original($src);
}
vr_thumb_dir_ready();
$ok = vr_thumb_write($im, $w, $ext, $cache);
imagedestroy($im);
if ($ok && is_file($cache)) vr_img_send($cache, $mime);
vr_img_original($src);

/** Orijinali tür başlığıyla gönder (son çare). */
function vr_img_original(string $src): never
{
    $t = @getimagesize($src);
    header('Content-Type: ' . ($t['mime'] ?? (mime_content_type($src) ?: 'image/jpeg')));
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($src));
    readfile($src);
    exit;
}
