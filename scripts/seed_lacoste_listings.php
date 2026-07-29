<?php
/**
 * VESTRA — seed the requested Lacoste listings into a live site's data/listings.json.
 * CLI only. Idempotent: skips any listing id that already exists.
 *
 * Usage (on the server):
 *   php ~/repo/scripts/seed_lacoste_listings.php ~/public_html seller-account@email.com
 *
 * The listings are attached (seller_uid) to the account matching the given email,
 * so they are fully editable — photos, colours, prices — from that seller's panel.
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$docroot = rtrim($argv[1] ?? '', '/');
$sellerEmail = strtolower(trim($argv[2] ?? ''));
if ($docroot === '' || $sellerEmail === '' || !is_dir($docroot)) {
    exit("Usage: php seed_lacoste_listings.php <docroot e.g. ~/public_html> <seller-email>\n");
}

$accFile = $docroot.'/data/accounts.json';
$accounts = is_readable($accFile) ? (json_decode(file_get_contents($accFile), true) ?: []) : [];
$seller = null;
foreach ($accounts as $a) {
    if (strtolower($a['email'] ?? '') === $sellerEmail) { $seller = $a; break; }
}
if (!$seller) exit("HATA: '$sellerEmail' ile eşleşen hesap yok (data/accounts.json)\n");
if (($seller['type'] ?? '') !== 'seller') exit("HATA: '$sellerEmail' bir satıcı hesabı değil (type={$seller['type']})\n");

$uid = $seller['id'];
$company = $seller['company'] ?: ($seller['name'] ?: 'Seller');
$colors7 = ['Black','Navy','White','Grey','Red','Green','Light Blue'];
$now = date('c');

$new = [
    [
        'id'=>'lac-paris-polo-allcol','brand'=>'Lacoste','name'=>'Paris Regular Fit Stretch Polo — All Colours',
        'mode'=>'offer','status'=>'approved','added_at'=>$now,
        'cat'=>'Polos','sku'=>'LAC-PARIS','moq'=>50,'unit'=>'pc',
        'desc'=>'Lacoste Paris polo, regular fit, stretch cotton piqué, hidden button placket. All colourways available, sizes S–XXL assorted per carton. EEA stock with full invoice trail.',
        'seller'=>$company,'origin'=>'EEA stock · invoice on request','verified'=>true,'accent'=>'#1f2a44',
        'guide'=>'Indicative €82–95 / pc · all colours',
        'colors'=>$colors7,
        'sizes'=>'S–XXL assorted',
        'tiers'=>[['min'=>50,'price'=>95.00],['min'=>100,'price'=>88.00],['min'=>300,'price'=>82.00]],
        'seller_uid'=>$uid,
    ],
    [
        'id'=>'lac-basic-tee-7col','brand'=>'Lacoste','name'=>'Basic Crew Neck T-Shirt — 7 Colours',
        'mode'=>'fixed','status'=>'approved','added_at'=>$now,
        'cat'=>'T-Shirts','sku'=>'LAC-TH6709','moq'=>100,'unit'=>'pc',
        'desc'=>'Lacoste basic crew neck tee, 100% cotton jersey. Black, navy, white, grey, red, green and light blue; sizes S–XXL assorted per carton.',
        'seller'=>$company,'origin'=>'EEA stock · invoice on request','verified'=>true,'accent'=>'#17181c',
        'colors'=>$colors7,
        'sizes'=>'S–XXL assorted',
        'tiers'=>[['min'=>100,'price'=>19.50],['min'=>300,'price'=>17.50],['min'=>1000,'price'=>15.90]],
        'offers'=>true,
        'seller_uid'=>$uid,
    ],
];

$listFile = $docroot.'/data/listings.json';
$list = is_readable($listFile) ? (json_decode(file_get_contents($listFile), true) ?: []) : [];
$existing = array_column($list, 'id');
$added = 0;
foreach ($new as $item) {
    if (in_array($item['id'], $existing, true)) { echo "atlandı (zaten var): {$item['id']}\n"; continue; }
    $list[] = $item; $added++;
    echo "eklendi: {$item['brand']} — {$item['name']} (sahip: {$company})\n";
}
file_put_contents($listFile, json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
echo "Bitti. $added yeni ilan yazıldı → $listFile\n";
