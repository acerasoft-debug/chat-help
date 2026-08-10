<?php
/**
 * Bağlanacak karelerin rengini ve içeriğini ölç — CLI
 * ===================================================
 *   php tools/check-attach-colour.php            (tablo)
 *   php tools/check-attach-colour.php --csv      (işlenebilir çıktı)
 *
 * NİYE
 * ----
 * Dosya adı eşleşmesi ürünü buluyor ama RENGİ garanti etmiyor. Kolajda iki
 * kez görüldü: lacivert bir BALMAIN tişörtün yanına bej kareler, siyah bir
 * D&G tişörtün yanına beyaz kareler düştü. Tedarikçi aynı gövde kodunu
 * bütün renkler için kullanınca ad tek başına ayırt edemiyor.
 *
 * İkinci sorun: bazı "fotoğraf"lar aslında fotoğraf değil — üzerinde
 * yalnızca marka ve model numarası yazan beyaz tedarikçi kartları
 * ("BURBERRY 8055318 8055338"). Ürün sayfasına konacak son şey.
 *
 * NASIL ÖLÇÜLÜYOR
 * ---------------
 * Her kare 64 px'e indiriliyor; kenarlardan zemin rengi okunuyor ve zemine
 * yakın pikseller atılıyor. Kalanlar GİYSİ. Onların ortalama rengi "özne
 * rengi". Aday karenin özne rengi, ürünün mevcut karesininkinden çok
 * uzaksa başka bir renk varyantıdır.
 *
 * Yazı kartı için iki işaret birlikte aranıyor: zemin oranı çok yüksek VE
 * özne neredeyse siyah (mürekkep). Tek başına hiçbiri yeterli değil —
 * beyaz zeminde siyah bir tişört de aynı iki değeri verir; ayıran şey
 * öznenin ne kadar YER kapladığı, giysi kadrajın beşte birinden fazlasını
 * doldurur, yazı doldurmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "Nur CLI.\n"; exit(1); }

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';
require_once __DIR__ . '/lib-photo-match.php';
require_once __DIR__ . '/lib-photo-colour.php';

$csv = in_array('--csv', $argv, true);

$catalog = [];
foreach (vr_catalog() as $p) $catalog[(string)$p['id']] = $p;

$idx    = pm_build_index($catalog);
$byStem = pm_stem_index($catalog);

$owned = [];
foreach ($catalog as $id => $p) {
    foreach ((array)vr_product_gallery($p) as $g) {
        $u = is_array($g) ? ($g['src'] ?? $g[0] ?? '') : $g;
        if (is_string($u) && $u !== '') $owned[strtolower(ltrim($u, '/'))] = $id;
    }
}

$root = dirname(__DIR__) . '/uploads';
$orphans = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = 'uploads/' . ltrim(str_replace($root, '', $f->getPathname()), '/');
    if (!preg_match('/\.(jpe?g|png|webp)$/i', $rel)) continue;
    foreach (['/.thumbs/', '/.orig/', '/candidates/'] as $s) if (str_contains($rel, $s)) continue 2;
    if (isset($owned[strtolower($rel)])) continue;
    $orphans[] = $rel;
}

$base = dirname(__DIR__) . '/';
$refCache = [];

if ($csv) echo "urun,dosya,mesafe,ozne_orani,yazi_karti,renk_payi,ref_orani,karar\n";
else printf("%-28s %-36s %7s %6s %6s  %s\n", 'URUN', 'ADAY KARE', 'RENKPAY', 'OZNE', 'REF', 'KARAR');

$rows = [];
foreach ($orphans as $rel) {
    [$hits, $how] = pm_match($rel, $idx, $byStem);
    if (count($hits) !== 1) continue;
    $id = $hits[0];
    $p  = $catalog[$id];

    if (!isset($refCache[$id])) {
        $ref = null;
        foreach ((array)vr_product_gallery($p) as $g) {
            $u = is_array($g) ? ($g['src'] ?? $g[0] ?? '') : $g;
            if (!is_string($u) || $u === '') continue;
            $m = cac_pixels($base . ltrim($u, '/'));
            if ($m && $m[1] > 0.03) { $ref = [cac_dominant($m[0]), $m[1], $m[0]]; break; }
        }
        $refCache[$id] = $ref;
    }
    $ref = $refCache[$id];
    $cm  = cac_pixels($base . $rel);
    if (!$ref || !$cm) continue;

    [$px, $frac] = $cm;
    [$refCol, $refFrac, $refPx] = $ref;
    [$karar, $pres] = cac_decide($refPx, $refFrac, $px, $frac);
    $dom  = cac_dominant($px) ?? [0, 0, 0];
    $dist = cac_dist($refCol, $dom);
    $card = str_starts_with($karar, 'RED-yazi') ? 'YAZI-KARTI?' : '';

    $rows[] = [$id, $rel, $dist, $frac, $card, $pres, $refFrac, $karar];
}

usort($rows, static fn($a, $b) => $a[5] <=> $b[5]);   // renk payi dusukten yukseye
foreach ($rows as $r) {
    if ($csv) printf("%s,%s,%.1f,%.3f,%s,%.3f,%.3f,%s\n", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7]);
    else printf("%-28s %-36s %6.1f%% %5.0f%% %5.0f%%  %s\n", substr($r[0], 0, 28), substr($r[1], -36), $r[5]*100, $r[3]*100, $r[6]*100, $r[7]);
}

if (!$csv) {
    $n = count($rows);
    $red = count(array_filter($rows, static fn($r) => $r[7] !== 'kabul'));
    printf("\ntoplam aday: %d · kabul: %d · red: %d\n", $n, $n - $red, $red);
}
