<?php
/**
 * VESTRA — seed the Dolce & Gabbana supplier line-sheet catalog (scripts/dg_catalog.json)
 * into a live site's data/listings.json. CLI only. Upserts by id — safe to re-run after
 * changing pricing rules below; existing dg-* listings get their commercial terms refreshed
 * (mode/moq/tiers/sizes) without touching seller-edited photos.
 *
 * Usage (on the server):
 *   php ~/repo/scripts/seed_dg_listings.php ~/public_html seller-account@email.com
 *
 * Pricing (excl. Lacoste/Ralph Lauren/Amiri, which live in inc/products.php):
 *   T-Shirts → fixed €50/pc · Polos → fixed €65/pc · everything else → open to offers.
 *   All sold in packs of 10, MOQ 20. Owned by the given seller account — fully editable
 *   (photos, colours, prices, mode) from that seller's panel.
 * Images must already be deployed under <docroot>/uploads/dg/ (rsync does this).
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$docroot = rtrim($argv[1] ?? '', '/');
$sellerEmail = strtolower(trim($argv[2] ?? ''));
if ($docroot === '' || $sellerEmail === '' || !is_dir($docroot)) {
    exit("Usage: php seed_dg_listings.php <docroot e.g. ~/public_html> <seller-email>\n");
}

$catalog = json_decode((string)file_get_contents(__DIR__.'/dg_catalog.json'), true);
if (!is_array($catalog) || !$catalog) exit("HATA: dg_catalog.json okunamadı\n");

$accounts = json_decode((string)@file_get_contents($docroot.'/data/accounts.json'), true) ?: [];
$seller = null;
foreach ($accounts as $a) if (strtolower($a['email'] ?? '') === $sellerEmail) { $seller = $a; break; }
if (!$seller) exit("HATA: '$sellerEmail' ile eşleşen hesap yok (data/accounts.json)\n");
if (($seller['type'] ?? '') !== 'seller') exit("HATA: '$sellerEmail' bir satıcı hesabı değil\n");

$uid = $seller['id'];
$company = $seller['company'] ?: ($seller['name'] ?: 'Seller');
$now = date('c');
$palette = ['#1a0a12','#1a2030','#3a0808','#1a2e18','#23323a','#392b4a','#3a3320','#44454e'];

$listFile = $docroot.'/data/listings.json';
$list = json_decode((string)@file_get_contents($listFile), true) ?: [];
$existing = array_column($list, 'id');
$added = 0; $updated = 0; $noimg = 0;
$moq = 20; $packSize = 10;
/* Pack-of-10 size curve (S/M/L/XL/XXL), same ratio used across the whole catalog. */
$sizes = 'S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/pack';

foreach ($catalog as $i => $m) {
    $id = 'dg-'.$m['sku'];
    $cat = $m['cat'];
    if ($cat === 'T-Shirts')      { $mode = 'fixed'; $price = 50.00; }
    elseif ($cat === 'Polos')     { $mode = 'fixed'; $price = 65.00; }
    else                          { $mode = 'offer'; $price = 0; } // Hoodies & Sweatshirts — no fixed price set yet

    $images = array_values(array_filter($m['images'], fn($u) => is_file($docroot.$u)));
    if (count($images) < count($m['images'])) $noimg += count($m['images']) - count($images);
    $desc = 'Original Dolce & Gabbana, model '.$m['model'].'. 100% cotton. '.$sizes.'. EEA stock with full invoice trail.';

    $fields = [
        'mode'=>$mode, 'cat'=>$cat, 'moq'=>$moq, 'size_step'=>$packSize,
        'desc'=>$desc, 'sizes'=>$sizes, 'colors'=>$m['colors'],
        'tiers'=>[['min'=>$moq,'price'=>$price]],
    ];
    if ($mode === 'offer') $fields['guide'] = 'Price on request — make an offer';

    $idx = array_search($id, $existing, true);
    if ($idx !== false) {
        unset($list[$idx]['guide']);
        foreach ($fields as $k=>$v) $list[$idx][$k] = $v;
        if ($images) { $list[$idx]['images'] = $images; $list[$idx]['image'] = $images[0]; }
        $updated++; continue;
    }
    $item = [
        'id'=>$id, 'brand'=>'Dolce & Gabbana', 'name'=>$m['name'],
        'status'=>'approved', 'added_at'=>$now,
        'sku'=>$m['sku'], 'unit'=>'pc',
        'seller'=>$company, 'origin'=>'EEA stock · invoice on request',
        'verified'=>true, 'accent'=>$palette[$i % count($palette)],
        'seller_uid'=>$uid,
    ] + $fields;
    if ($images) { $item['images'] = $images; $item['image'] = $images[0]; }
    $list[] = $item; $added++;
}
file_put_contents($listFile, json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
echo "Bitti. $added yeni, $updated güncellenmiş D&G ilanı (sahip: $company) → $listFile\n";
if ($noimg) echo "UYARI: $noimg görsel dosyası docroot/uploads/dg altında bulunamadı — önce rsync ile deploy edin.\n";
