<?php
/**
 * Sahipsiz fotoğrafı ürüne eşleştirme kuralı — ORTAK KİTAPLIK
 * ===========================================================
 * attach-orphan-photos.php ve review-attach.php bunu paylaşıyor. Ayrı ayrı
 * yazılmıştı ve bir düzeltme birinde yapılıp diğerinde unutulduğunda denetim
 * kolajı, gerçekte bağlanacak olandan başka bir şey gösteriyordu — yani
 * denetim işe yaramaz hâle geliyordu. Tek yerde duruyor.
 *
 * KURAL, SIKIDAN GEVŞEĞE
 * ----------------------
 *  1. SKU (en güvenilir). Dosya adı bir ürünün model kodunun TAMAMINI
 *     içeriyorsa o ürüne aittir. Birden çok kod uyuyorsa EN UZUNU kazanır.
 *  2. GÖVDE. Kod geçmiyorsa, dosya adının kare ekleri atılmış hâli, ürünün
 *     mevcut bir karesinin aynı biçimde sadeleşmiş hâliyle örtüşüyorsa aynı
 *     çekimin başka karesidir.
 *
 * "EN UZUN KOD KAZANIR" NİYE ŞART
 * -------------------------------
 * Katalogda hem G8OL6ZG7C8G (turuncu tişört) hem G8OL6ZG7C8G1 (beyaz tişört)
 * var — tek fark sondaki "1". Eski gövde kuralı dosya adının sonundaki
 * "-1"i kare numarası sanıp atıyordu, böylece BEYAZ ürünün kareleri
 * TURUNCU ürüne düşüyordu. Kolajda görüldü: turuncu tişörtün yanında üç
 * beyaz kare. Aynı hata siyah bir boksere beyaz kareler, gri bir tişörte
 * mavi kare bağlıyordu.
 *
 * Uzunluk sıralaması bunu kökten çözüyor: "…c8g1p1" adı hem "…c8g" hem
 * "…c8g1" kodunu içeriyor, uzun olan seçiliyor ve kare doğru varyanta
 * gidiyor. Renk kodu ürün kimliğinin parçası, atılabilir bir süs değil.
 */

declare(strict_types=1);

/** Karşılaştırma için sadeleştir: yalnızca harf ve rakam. */
function pm_norm(string $s): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($s)) ?? '';
}

/**
 * Kare eklerini at — SADECE gövde eşleşmesi için.
 * Sondaki çıplak rakam da atılıyor ("photo-2"), ama bu adım artık yalnızca
 * SKU denemesi başarısız olduğunda çalışıyor; varyant kodunu yiyemez.
 */
function pm_stem(string $rel): string
{
    $base = strtolower(pathinfo($rel, PATHINFO_FILENAME));
    $base = preg_replace('/(-p\d+)+$/', '', $base) ?? $base;
    $base = preg_replace('/[-_](b|back|front|f|v|r|\d{1,2})$/', '', $base) ?? $base;
    // Yaygın boyut ekleri
    $base = preg_replace('/[-_](large|medium|small|thumb|\d{3,4}x\d{3,4})$/', '', $base) ?? $base;
    return pm_norm($base);
}

/**
 * Ürün kimliği => sadeleştirilmiş kodlar; ve kodlar uzundan kısaya sıralı
 * bir liste. En az altı karakter: daha kısası rastgele eşleşiyor — ölçüldü,
 * dört karakterde bir Burberry kazağı bir mayoya bağlanıyordu.
 */
function pm_build_index(array $catalog): array
{
    $byCode = [];
    foreach ($catalog as $id => $p) {
        foreach ([$p['sku'] ?? '', $p['model'] ?? ''] as $c) {
            // "VS-" ile başlayanlar bizim iç referansımız; dosya adlarında
            // hiç geçmiyor, indekse girerse yalnızca yanlış eşleşme üretir.
            if (str_starts_with(strtolower(trim((string)$c)), 'vs-')) continue;
            $c = pm_norm((string)$c);
            if (strlen($c) >= 6) $byCode[$c][(string)$id] = true;
        }
    }
    // strval ZORUNLU: PHP sayı gibi duran dizi anahtarlarını int'e çeviriyor,
    // "612966" gibi bir Balenciaga kodu int oluyor ve strlen() PHP 8'de
    // ölümcül hata veriyor. Ölçüldü.
    $codes = array_map('strval', array_keys($byCode));
    usort($codes, static fn($a, $b) => strlen($b) <=> strlen($a));
    return ['byCode' => $byCode, 'codes' => $codes];
}

/**
 * Bir dosya hangi ürüne ait?
 * @return array{0:string[],1:string} [eşleşen ürün kimlikleri, yöntem]
 */
function pm_match(string $rel, array $idx, array $byStem): array
{
    $full = pm_norm(pathinfo($rel, PATHINFO_FILENAME));

    // 1) SKU — en uzun kod kazanır
    foreach ($idx['codes'] as $code) {
        if (str_contains($full, $code)) {
            return [array_keys($idx['byCode'][$code]), 'kod'];
        }
    }

    // 2) gövde
    $stem = pm_stem($rel);
    if (strlen($stem) >= 6 && !empty($byStem[$stem])) {
        return [array_keys($byStem[$stem]), 'gövde'];
    }

    return [[], ''];
}

/** Ürünlerin mevcut karelerinden gövde dizini. */
function pm_stem_index(array $catalog): array
{
    $byStem = [];
    foreach ($catalog as $id => $p) {
        foreach ((array)vr_product_gallery($p) as $g) {
            $u = is_array($g) ? ($g['src'] ?? $g[0] ?? '') : $g;
            if (!is_string($u) || $u === '') continue;
            $s = pm_stem(ltrim($u, '/'));
            if (strlen($s) >= 6) $byStem[$s][(string)$id] = true;
        }
    }
    return $byStem;
}
