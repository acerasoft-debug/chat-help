<?php
/**
 * Versand & Lieferung
 * Tutarlar ve süreler yapılandırmadan okunuyor — kasada gösterilen bedelle bu
 * sayfadaki bedel hiçbir zaman ayrışmasın (PAngV §6'nın ruhu da bu).
 *
 * Bölge ETİKETLERİ içerik dosyasında durur (dile göre değişir); ülke listeleri
 * ve tutarlar buradan gider.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$ship = (array)vr_config('shipping');
$countries = [
    'de' => (array)vr_config('shipping_countries_de', []),
    'eu' => (array)vr_config('shipping_countries_eu', []),
    'ch' => (array)vr_config('shipping_countries_ch', []),
];
// Yukarıdaki üç bölgeye girmeyen her hedef "weitere Zielgebiete"
$countries['world'] = array_values(array_diff(
    vr_shipping_countries(), $countries['de'], $countries['eu'], $countries['ch']
));

vr_doc_page('versand', 'legal_shipping', '2026-08-01', compact('ship', 'countries'));
