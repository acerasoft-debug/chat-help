<?php
/**
 * Küçültülmüş kopyalar — assets/img.php ve tools/warm-thumbs.php ortak çekirdeği
 * ============================================================================
 * Önbellek yolu ve üretim TEK yerde: önceden üreten araç (warm-thumbs) ile
 * isteğe bağlı üreten img.php aynı dosya adını hesaplamazsa ısıtma boşa gider.
 *
 * Neden önceden üretmek? Paylaşımlı sunucuda aynı anda çalışabilen PHP süreci
 * sınırlı. Henüz kopyası olmayan bir ürün sayfası açılınca tarayıcı on-yirmi
 * görseli birden istiyor; her biri 2000+ piksellik bir AVIF/JPEG'i çözüp
 * küçültüyor. Sınır aşılınca sunucu bir kısmına 503/508 dönüyor ve o kareler
 * telefonda boş kalıyor. Kopyalar hazırsa img.php yalnızca dosya gönderiyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/uploads.php';

/** İzin verilen genişlikler. Serbest sayı = sınırsız önbellek = kaynak tüketimi. */
const VR_IMG_WIDTHS = [180, 300, 420, 600, 900, 1400];

/** Önbellekteki kopyanın mutlak yolu ($src: kaynağın mutlak yolu). */
function vr_thumb_path(string $rel, string $src, int $w, string $ext): string
{
    $key = sha1($rel . '|' . filemtime($src) . '|' . $w . '|' . $ext);
    return vr_doc_root() . '/uploads/.thumbs/' . substr($key, 0, 2) . '/' . $key . '.' . $ext;
}

/**
 * Kaynağı çöz. Büyük dosyada bellek yetmezse null döner (çağıran orijinali
 * gönderir) — ölümcül hata yerine.
 */
function vr_thumb_open(string $src): ?\GdImage
{
    $info = @getimagesize($src);
    if ($info && $info[0] * $info[1] > 40_000_000) return null;   // > 40 MP: GD'ye yük
    if ($info) {
        // Tahmini ihtiyaç: piksel × 5 bayt + pay. Gerekirse sınırı bu istek için yükselt.
        $need = (int)($info[0] * $info[1] * 5) + 32 * 1048576;
        $lim  = vr_thumb_bytes((string)ini_get('memory_limit'));
        if ($lim > 0 && memory_get_usage() + $need > $lim) @ini_set('memory_limit', (string)max($need + memory_get_usage(), 256 * 1048576));
    }
    $im = @imagecreatefromstring((string)file_get_contents($src));
    return $im instanceof \GdImage ? $im : null;
}

function vr_thumb_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return 0;
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
}

/** Çözülmüş görselden $w genişlikte kopya yaz (atomik). Başarıda true. */
function vr_thumb_write(\GdImage $im, int $w, string $ext, string $dest): bool
{
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

    $dir = dirname($dest);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    // Önce geçici dosyaya, sonra yerine taşı: yarım yazılmış bir dosya
    // önbellekte kalmasın (aynı anda iki istek gelebilir).
    $tmp = $dest . '.' . getmypid() . '.tmp';
    $ok  = $ext === 'webp' ? @imagewebp($out, $tmp, 82) : @imagejpeg($out, $tmp, 84);
    imagedestroy($out);
    if (!$ok || !is_file($tmp) || filesize($tmp) === 0) { @unlink($tmp); return false; }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $dest)) { @unlink($tmp); return is_file($dest); }
    return true;
}
