<?php
/**
 * VESTRA — seed the Dolce & Gabbana supplier line-sheet catalog (scripts/dg_catalog.json)
 * into a live site's data/listings.json. CLI only. Idempotent (skips existing ids).
 *
 * Usage (on the server):
 *   php ~/repo/scripts/seed_dg_listings.php ~/public_html seller-account@email.com
 *
 * Every model becomes a make-an-offer listing owned by the given seller account —
 * fully editable (photos, colours, prices, mode) from that seller's panel.
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
$added = 0; $noimg = 0;

foreach ($catalog as $i => $m) {
    $id = 'dg-'.$m['sku'];
    if (in_array($id, $existing, true)) { echo "atlandı (zaten var): $id\n"; continue; }
    $images = array_values(array_filter($m['images'], fn($u) => is_file($docroot.$u)));
    if (count($images) < count($m['images'])) $noimg += count($m['images']) - count($images);
    $item = [
        'id'=>$id, 'brand'=>'Dolce & Gabbana', 'name'=>$m['name'],
        'mode'=>'offer', 'status'=>'approved', 'added_at'=>$now,
        'cat'=>$m['cat'], 'sku'=>$m['sku'], 'moq'=>200, 'unit'=>'pc',
        'desc'=>'Original Dolce & Gabbana, model '.$m['model'].'. 100% cotton. '.$m['sizes'].'. EEA stock with full invoice trail.',
        'seller'=>$company, 'origin'=>'EEA stock · invoice on request',
        'verified'=>true, 'accent'=>$palette[$i % count($palette)],
        'guide'=>'Price on request — make an offer',
        'sizes'=>$m['sizes'],
        'colors'=>$m['colors'],
        'tiers'=>[['min'=>200,'price'=>0]],
        'seller_uid'=>$uid,
    ];
    if ($m['sku'] === '101208') $item['moq'] = 150;
    if ($images) { $item['images'] = $images; $item['image'] = $images[0]; }
    $list[] = $item; $added++;
}
file_put_contents($listFile, json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
echo "Bitti. $added yeni D&G ilanı yazıldı (sahip: $company) → $listFile\n";
if ($noimg) echo "UYARI: $noimg görsel dosyası docroot/uploads/dg altında bulunamadı — önce rsync ile deploy edin.\n";
