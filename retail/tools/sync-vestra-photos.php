<?php
/**
 * Ürün fotoğraflarını mağazanın kendi klasörüne al — CLI
 * ======================================================
 *   php tools/sync-vestra-photos.php [--dry]
 *
 * NİYE
 * ----
 * Ürünler Vestra kataloğundan geliyor ve fotoğraf yolları ("/uploads/…")
 * Vestra'nın web köküne göre yazılmış. Mağaza kendi kökünde (~/maxsales_de)
 * duruyor; yeni gelen ürünlerin dosyaları orada yok ve vitrinde fotoğraf
 * yerine yer tutucu çıkıyordu (ölçüldü: 604 ürün, dosyaların hepsi Vestra
 * klasöründe mevcut).
 *
 * Bu betik, VİTRİNDEKİ (premium süzgecinden geçmiş) ürünlerin ihtiyaç
 * duyduğu dosyaları Vestra'nın uploads/ klasöründen mağazanın uploads/
 * klasörüne kopyalar. Kurallar:
 *   • Vestra tarafına HİÇBİR ŞEY yazılmaz — yalnızca okunur.
 *   • Mağazada zaten olan dosyanın üzerine yazılmaz.
 *   • Yalnızca /uploads/ altındaki, güvenli karakterli yollar.
 * Deploy'da ve saatlik işte çalışır; yeni ürünün fotoğrafı en geç bir saat
 * içinde vitrine gelir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/uploads.php';

$dry = in_array('--dry', $argv, true);

$vroot = trim((string)getenv('VR_VESTRA_ROOT'));
if ($vroot === '') $vroot = dirname(realpath(VR_ROOT) ?: VR_ROOT) . '/public_html';
$shop = vr_doc_root();

if (realpath($vroot) === realpath($shop)) { echo "Mağaza ve Vestra aynı kökte — kopyalama gerekmiyor.\n"; exit(0); }
if (!is_dir($vroot . '/uploads')) { echo "Vestra uploads/ bulunamadı: $vroot/uploads — atlandı.\n"; exit(0); }

$copied = 0; $bytes = 0; $have = 0; $missing = []; $products = 0; $noPhoto = [];
foreach (vr_catalog() as $id => $p) {
    $products++;
    $imgs = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));
    $okAny = false;
    foreach ($imgs as $img) {
        if (!preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $img) || str_contains($img, '..')) continue;
        $dst = $shop . $img;
        if (is_file($dst)) { $have++; $okAny = true; continue; }
        $src = $vroot . $img;
        if (!is_file($src)) { $missing[] = "$id $img"; continue; }
        if (!$dry) {
            @mkdir(dirname($dst), 0755, true);
            if (!@copy($src, $dst)) { $missing[] = "$id $img (kopyalanamadı)"; continue; }
            @chmod($dst, 0644);
        }
        $copied++; $bytes += (int)filesize($src); $okAny = true;
    }
    if (!$okAny) $noPhoto[] = $id . '  ' . $p['brand'] . ' — ' . vr_card_name($p);
}

printf("%s: %d ürün · zaten vardı %d dosya · %s %d dosya (%.1f MB)\n",
    $dry ? 'KURU' : 'TAMAM', $products, $have, $dry ? 'kopyalanacak' : 'kopyalandı', $copied, $bytes / 1048576);
printf("hiçbir yerde bulunamayan dosya: %d · hiç fotoğrafı olmayan ürün: %d\n", count($missing), count($noPhoto));
foreach (array_slice($missing, 0, 20) as $m) echo "  yok: $m\n";
foreach ($noPhoto as $n) echo "  fotoğrafsız: $n\n";
