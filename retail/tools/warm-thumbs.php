<?php
/**
 * Küçültülmüş kopyaları önceden üret — php tools/warm-thumbs.php [--budget=600]
 * ===========================================================================
 * Vitrindeki her ürünün her karesi için img.php'nin isteyeceği kopyaları
 * (tüm genişlikler webp; 600 ve 1400 ayrıca jpeg) şimdiden diske yazar.
 * Böylece ziyaretçi bir ürün sayfası açtığında sunucu yalnızca hazır dosya
 * gönderir; aynı anda yirmi görseli üretmeye çalışıp süreç sınırına takılmaz
 * (telefonda boş kalan kareler — bkz. inc/thumbs.php).
 *
 * Artımlı: hazır olan kopyaya dokunmaz. Kaynak bir kez çözülür, eksik bütün
 * genişlikler ondan üretilir. --budget saniyesi dolunca durur; bir sonraki
 * çalıştırma kaldığı yerden devam eder. Komut satırından çalışır (web'den
 * erişilemez: tools/ dizini .htaccess ile kapalı).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/thumbs.php';

$budget = 600;
foreach ($argv as $a) if (preg_match('/^--budget=(\d+)$/', $a, $m)) $budget = (int)$m[1];
$t0 = microtime(true);

$want = [];
foreach (VR_IMG_WIDTHS as $w) $want[] = [$w, 'webp'];
foreach ([600, 1400] as $w) $want[] = [$w, 'jpg'];

$rels = [];
foreach (vr_catalog() as $p) foreach ((array)($p['images'] ?? []) as $r) if (is_string($r) && str_starts_with($r, '/uploads/')) $rels[$r] = 1;
$rels = array_keys($rels);

$root = realpath(vr_doc_root() . '/uploads');
$st = ['images' => count($rels), 'ready' => 0, 'made' => 0, 'files' => 0, 'failed' => 0, 'missing' => 0, 'big' => 0];
$fail = [];
foreach ($rels as $n => $rel) {
    if (microtime(true) - $t0 > $budget) { echo "Süre doldu — kalanı bir sonraki çalıştırmada.\n"; break; }
    $src = realpath(vr_doc_root() . $rel);
    if ($root === false || $src === false || !str_starts_with($src, $root . DIRECTORY_SEPARATOR)) { $st['missing']++; continue; }
    $todo = [];
    foreach ($want as [$w, $ext]) {
        $dest = vr_thumb_path($rel, $src, $w, $ext);
        if (!is_file($dest) || filesize($dest) === 0) $todo[] = [$w, $ext, $dest];
    }
    if (!$todo) { $st['ready']++; continue; }
    $im = vr_thumb_open($src);
    if ($im === null) { $st['big']++; $fail[] = $rel . ' (çözülemedi/çok büyük)'; continue; }
    $okAll = true;
    foreach ($todo as [$w, $ext, $dest]) {
        if (vr_thumb_write($im, $w, $ext, $dest)) $st['files']++; else { $okAll = false; $fail[] = "$rel w=$w $ext"; }
    }
    imagedestroy($im);
    $okAll ? $st['made']++ : $st['failed']++;
    if (($n + 1) % 200 === 0) printf("  %d/%d · %.0f sn\n", $n + 1, count($rels), microtime(true) - $t0);
}

printf("Kare: %d · zaten hazır %d · bu turda üretilen %d (%d dosya) · hata %d · çözülemeyen %d · dosyası yok %d · %.0f sn\n",
       $st['images'], $st['ready'], $st['made'], $st['files'], $st['failed'], $st['big'], $st['missing'], microtime(true) - $t0);
foreach (array_slice($fail, 0, 30) as $f) echo "  HATA $f\n";
