<?php
/**
 * VESTRA — seed/refresh the Lacoste & Ralph Lauren supplier line-sheet catalog
 * (from the 5 uploaded PDFs) into a live site's data/listings.json. CLI only.
 * Upserts by id — safe to re-run; refreshes commercial terms, photos, variants
 * and line-sheet links on existing rows.
 *
 * Also applies the pack rules to the earlier Lacoste seeds if present:
 *   - lac-paris-polo-allcol  → 8+8 carton (16 pc), seller hidden
 *   - lac-basic-tee-7col     → packs of 10, seller hidden
 * (L1212 + RL oxford live in inc/products.php and are updated in code.)
 *
 * Usage (on the server):
 *   php ~/repo/scripts/seed_lr_listings.php ~/public_html seller-account@email.com
 *
 * Photos must already be deployed under <docroot>/uploads/lacoste|rl/ and the
 * PDFs under <docroot>/sheets/ (rsync does both).
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$docroot = rtrim($argv[1] ?? '', '/');
$sellerEmail = strtolower(trim($argv[2] ?? ''));
if ($docroot === '' || $sellerEmail === '' || !is_dir($docroot)) {
    exit("Usage: php seed_lr_listings.php <docroot e.g. ~/public_html> <seller-email>\n");
}

$accounts = json_decode((string)@file_get_contents($docroot.'/data/accounts.json'), true) ?: [];
$seller = null;
foreach ($accounts as $a) if (strtolower($a['email'] ?? '') === $sellerEmail) { $seller = $a; break; }
if (!$seller) exit("HATA: '$sellerEmail' ile eşleşen hesap yok (data/accounts.json)\n");
if (($seller['type'] ?? '') !== 'seller') exit("HATA: '$sellerEmail' bir satıcı hesabı değil\n");
$uid = $seller['id'];
$company = $seller['company'] ?: ($seller['name'] ?: 'Seller');
$now = date('c');

$lac = fn($c) => '/uploads/lacoste/'.$c;
$rl  = fn($c) => '/uploads/rl/'.$c;

$catalog = [
    [
        'id'=>'lac-vneck-tee','brand'=>'Lacoste','name'=>"Men's Pima Cotton T-Shirt — V-Neck (TH6710)",
        'mode'=>'offer','cat'=>'T-Shirts','sku'=>'LAC-TH6710','moq'=>100,'size_step'=>10,'min_colors'=>4,'unit'=>'pc',
        'desc'=>"Original Lacoste men's Pima cotton T-shirt TH6710, V-neck, short sleeves, regular fit, 100% cotton. Summer season. Packs of 10 — minimum order 100 pc (10 packs), at least 4 colours.",
        'guide'=>'Retail €60 — price on request','accent'=>'#1b5e3a',
        'sizes'=>'Sizes 3–7 · 10/pack · min 100 pc',
        'colors'=>['Black','White','Green','Navy'],
        'images'=>[$lac('th6710-black.jpg'),$lac('th6710-white.jpg'),$lac('th6710-green.jpg'),$lac('th6710-navy.jpg')],
        'linesheet'=>true,'sheet_file'=>'lacoste-pima-vneck-tshirt.pdf',
        'variants'=>[
            ['art'=>'LCMP16000','model'=>'TH6710 51 031','color'=>'Black','image'=>$lac('th6710-black.jpg')],
            ['art'=>'LCMP16001','model'=>'TH6710 51 001','color'=>'White','image'=>$lac('th6710-white.jpg')],
            ['art'=>'LCMP16002','model'=>'TH6710 51 132','color'=>'Green','image'=>$lac('th6710-green.jpg')],
            ['art'=>'LCMP16003','model'=>'TH6710 51 166','color'=>'Navy','image'=>$lac('th6710-navy.jpg')],
        ],
        'tiers'=>[['min'=>100,'price'=>0]],
    ],
    [
        'id'=>'lac-sweatshirt','brand'=>'Lacoste','name'=>'Men Sweatshirt (SH9608)',
        'mode'=>'offer','cat'=>'Hoodies & Sweatshirts','sku'=>'LAC-SH9608','moq'=>20,'size_step'=>10,'unit'=>'pc',
        'desc'=>"Original Lacoste men's crewneck sweatshirt SH9608, regular fit, long sleeves, 84% organic cotton / 16% polyester. Winter season. Ships in packs of 10.",
        'guide'=>'Retail €130 — price on request','accent'=>'#14352a',
        'sizes'=>'Sizes 3–7 · 10/pack',
        'colors'=>['Navy','Black','Red','Green','White','Blue','Beige','Bordeaux','Pink','Light Blue'],
        'images'=>[$lac('sh9608-navy.jpg'),$lac('sh9608-black.jpg'),$lac('sh9608-red.png'),$lac('sh9608-green.jpg'),
                   $lac('sh9608-white.jpg'),$lac('sh9608-blue.png'),$lac('sh9608-beige.jpg'),$lac('sh9608-bordeaux.jpg'),
                   $lac('sh9608-lightpink.png'),$lac('sh9608-lightblue.png')],
        'linesheet'=>true,'sheet_file'=>'lacoste-sweatshirt.pdf',
        'variants'=>[
            ['art'=>'','model'=>'SH9608 00 166','color'=>'Navy','image'=>$lac('sh9608-navy.jpg')],
            ['art'=>'','model'=>'SH9608 00 031','color'=>'Black','image'=>$lac('sh9608-black.jpg')],
            ['art'=>'','model'=>'SH9608 00 240','color'=>'Red','image'=>$lac('sh9608-red.png')],
            ['art'=>'','model'=>'SH9608 00 132','color'=>'Green','image'=>$lac('sh9608-green.jpg')],
            ['art'=>'','model'=>'SH9608 00 001','color'=>'White','image'=>$lac('sh9608-white.jpg')],
            ['art'=>'','model'=>'SH9608 00 4XA','color'=>'Blue','image'=>$lac('sh9608-blue.png')],
            ['art'=>'','model'=>'SH9608 00 02S','color'=>'Beige','image'=>$lac('sh9608-beige.jpg')],
            ['art'=>'','model'=>'SH9608 00 BZD','color'=>'Bordeaux','image'=>$lac('sh9608-bordeaux.jpg')],
            ['art'=>'','model'=>'SH9608 00 T03','color'=>'Pink','image'=>$lac('sh9608-lightpink.png')],
            ['art'=>'','model'=>'SH9608 00 T01','color'=>'Light Blue','image'=>$lac('sh9608-lightblue.png')],
        ],
        'tiers'=>[['min'=>20,'price'=>0]],
    ],
    [
        'id'=>'rl-slim-tee','brand'=>'Ralph Lauren','name'=>'Men Custom Slim Fit T-Shirt',
        'mode'=>'offer','cat'=>'T-Shirts','sku'=>'RL-CSF-TS','moq'=>100,'size_step'=>10,'min_colors'=>4,'unit'=>'pc',
        'desc'=>"Original Ralph Lauren men's Custom Slim Fit T-shirt, short sleeves, 100% cotton. Made in Cambodia. Summer season. Packs of 10 — minimum order 100 pc (10 packs), at least 4 colours.",
        'guide'=>'Retail €79 — price on request','accent'=>'#0f2f5c',
        'sizes'=>'S–XXL mixed · 10/pack · min 100 pc',
        'colors'=>['Navy','Black','White','Grey','Khaki','Red','Light Blue','Cream'],
        'images'=>[$rl('csf-tee-navy.jpg'),$rl('csf-tee-black.jpg'),$rl('csf-tee-white.jpg'),$rl('csf-tee-grey.jpg'),
                   $rl('csf-tee-khaki.png'),$rl('csf-tee-red.jpg'),$rl('csf-tee-lightblue.jpg'),$rl('csf-tee-cream.jpg')],
        'linesheet'=>true,'sheet_file'=>'ralphlauren-slimfit-tshirt.pdf',
        'variants'=>[
            ['art'=>'40100','model'=>'710680785004','color'=>'Navy','image'=>$rl('csf-tee-navy.jpg')],
            ['art'=>'40101','model'=>'710680785001','color'=>'Black','image'=>$rl('csf-tee-black.jpg')],
            ['art'=>'40102','model'=>'710680785003','color'=>'White','image'=>$rl('csf-tee-white.jpg')],
            ['art'=>'40103','model'=>'710680785002','color'=>'Grey','image'=>$rl('csf-tee-grey.jpg')],
            ['art'=>'40104','model'=>'710671438341','color'=>'Khaki','image'=>$rl('csf-tee-khaki.png')],
            ['art'=>'40105','model'=>'710671438302','color'=>'Red','image'=>$rl('csf-tee-red.jpg')],
            ['art'=>'40106','model'=>'710671438337','color'=>'Light Blue','image'=>$rl('csf-tee-lightblue.jpg')],
            ['art'=>'40107','model'=>'710671438350','color'=>'Cream','image'=>$rl('csf-tee-cream.jpg')],
        ],
        'tiers'=>[['min'=>100,'price'=>0]],
    ],
    [
        'id'=>'rl-slim-polo','brand'=>'Ralph Lauren','name'=>'Men Custom Slim Fit Polo Shirt',
        'mode'=>'offer','cat'=>'Polos','sku'=>'RL-CSF-PL','moq'=>80,'size_step'=>8,'min_colors'=>4,'unit'=>'pc',
        'desc'=>"Original Ralph Lauren men's Custom Slim Fit polo shirt, short sleeves, 100% cotton. Summer season. Sold in lots of 8 (8+8 cartons); minimum order 80 pc (10 lots), at least 4 colours.",
        'guide'=>'Price on request — make an offer','accent'=>'#12365f',
        'sizes'=>'Lots of 8 · S–XXL · min 80 pc (10 lots)',
        'colors'=>['White','Green','Fuchsia','Yellow','Orange','Pink'],
        'images'=>[$rl('csf-polo-white.png'),$rl('csf-polo-darkgreen.jpg'),$rl('csf-polo-fuchsia.png'),
                   $rl('csf-polo-yellow.jpg'),$rl('csf-polo-orange.jpg'),$rl('csf-polo-pink.jpg')],
        'linesheet'=>true,'sheet_file'=>'ralphlauren-slimfit-polo.pdf',
        'variants'=>[
            ['art'=>'101202','model'=>'710548797001','color'=>'White','image'=>$rl('csf-polo-white.png')],
            ['art'=>'101206','model'=>'710536856394','color'=>'Green','image'=>$rl('csf-polo-darkgreen.jpg')],
            ['art'=>'101210','model'=>'710782592031','color'=>'Fuchsia','image'=>$rl('csf-polo-fuchsia.png')],
            ['art'=>'101211','model'=>'710536856407','color'=>'Yellow','image'=>$rl('csf-polo-yellow.jpg')],
            ['art'=>'101212','model'=>'710536856408','color'=>'Orange','image'=>$rl('csf-polo-orange.jpg')],
            ['art'=>'101214','model'=>'710536856396','color'=>'Pink','image'=>$rl('csf-polo-pink.jpg')],
        ],
        'tiers'=>[['min'=>80,'price'=>0]],
    ],
];

$listFile = $docroot.'/data/listings.json';
$list = json_decode((string)@file_get_contents($listFile), true) ?: [];
$byId = [];
foreach ($list as $i => $l) $byId[$l['id'] ?? ''] = $i;
$added = 0; $updated = 0; $noimg = 0;

foreach ($catalog as $item) {
    // only attach images that actually exist on this server
    $imgs = array_values(array_filter($item['images'], fn($u) => is_file($docroot.$u)));
    $noimg += count($item['images']) - count($imgs);
    $item['images'] = $imgs;
    if ($imgs) $item['image'] = $imgs[0]; else unset($item['image']);
    if (!empty($item['sheet_file']) && !is_file($docroot.'/sheets/'.$item['sheet_file'])) {
        echo "UYARI: sheets/{$item['sheet_file']} sunucuda yok — PDF butonu gizlenecek\n";
    }
    $item += [
        'status'=>'approved','added_at'=>$now,'seller'=>$company,'seller_uid'=>$uid,
        'hide_seller'=>true,'verified'=>true,'origin'=>'EEA stock · invoice on request',
    ];
    if (isset($byId[$item['id']])) {
        $i = $byId[$item['id']];
        $keep = array_intersect_key($list[$i], array_flip(['added_at','status','seller','seller_uid']));
        $list[$i] = array_merge($item, $keep);
        $updated++;
    } else {
        $list[] = $item; $added++;
    }
}

/* Pack rules for the earlier Lacoste seeds, if they exist on this server */
foreach ($list as &$l) {
    if (($l['id'] ?? '') === 'lac-paris-polo-allcol') {
        $l['moq'] = 80; $l['size_step'] = 8; $l['min_colors'] = 4; $l['hide_seller'] = true;
        $l['sizes'] = 'Lots of 8 · S–XXL · min 80 pc (10 lots)';
        $l['tiers'] = [['min'=>80,'price'=>95.00],['min'=>160,'price'=>88.00],['min'=>320,'price'=>82.00]];
        echo "güncellendi: lac-paris-polo-allcol → MOQ 80 (8'li lot), min 4 renk, satıcı gizli\n";
    }
    if (($l['id'] ?? '') === 'lac-basic-tee-7col') {
        $l['size_step'] = 10; $l['moq'] = 100; $l['min_colors'] = 4; $l['hide_seller'] = true;
        if (!empty($l['tiers'][0]['min']) && $l['tiers'][0]['min'] < 100) $l['tiers'][0]['min'] = 100;
        if (strpos((string)($l['sizes'] ?? ''), '10/pack') === false) $l['sizes'] = trim(($l['sizes'] ?? '')).' · 10/pack';
        echo "güncellendi: lac-basic-tee-7col → MOQ 100, 10'lu paket, min 4 renk, satıcı gizli\n";
    }
}
unset($l);

file_put_contents($listFile, json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
echo "Bitti. $added yeni, $updated güncellenmiş Lacoste/RL ilanı (sahip: $company) → $listFile\n";
if ($noimg) echo "UYARI: $noimg görsel dosyası docroot/uploads altında bulunamadı — önce rsync ile deploy edin.\n";
