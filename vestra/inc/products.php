<?php
/**
 * VESTRA — sample catalog (single source of truth).
 * Pricing modes:  'fixed' (tiered), 'sale' (discounted vs list), 'offer' (make-an-offer / negotiate).
 * B2B: MOQ (min order) + tiered pricing (more qty -> lower unit price). Demo data.
 */
/* Platform fees — single source of truth (used by order.php + cart.php).
   Both are 0 while VESTRA runs on the seller-membership model: the buyer pays the
   seller's invoice directly by bank transfer, so a platform fee on top would never
   match the invoice total. Cart/emails hide fee lines automatically while 0. */
if(!defined('VESTRA_FEE_SELLER')) define('VESTRA_FEE_SELLER', 0.0);
if(!defined('VESTRA_FEE_BUYER'))  define('VESTRA_FEE_BUYER',  0.0);
/* Escrow (Treuhand) only: a fixed buyer-protection fee added to the buyer's total,
   collected together with the seller's tiered commission as the Stripe application
   fee on the direct charge. Bank-transfer orders are unaffected (buyer pays 0). */
if(!defined('VESTRA_ESCROW_FEE_BUYER')) define('VESTRA_ESCROW_FEE_BUYER', 0.038);
/* Escrow ile odenebilecek EN YUKSEK SIPARIS tutari (EUR). Olculen sey SIPARIS,
   yani kupon sonrasi mal bedeli -- karttan cekilen toplam degil. Fark onemli:
   koruma ucreti dahil olculseydi tam 3500 EUR'luk bir siparis sinirin ustune
   cikip reddedilirdi, oysa kural "en fazla 3500 EUR siparis". Ucret bunun
   uzerine biniyor ve karttan 3500 + %3,8 cekilebiliyor.
   Sinir hem sepette hem order.php'de sinaniyor: sepetteki kontrol bir gorunum
   kolayligi, gecerli olan sunucudaki. */
if(!defined('VESTRA_ESCROW_MAX')) define('VESTRA_ESCROW_MAX', 3500.00);
/* Seller commission — a SEPARATE mechanism from the fees above: a % of each paid order's
   goods value, charged directly to the seller's card on file via Stripe (inc/commission.php)
   once the order is marked paid. Never touches the buyer-facing cart/invoice total. This
   constant is the Starter-tier (and fallback) rate; Pro/Elite get a lower rate — see
   vestra_seller_commission_rate() below. */
if(!defined('VESTRA_COMMISSION_RATE')) define('VESTRA_COMMISSION_RATE', 0.035);
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/notify.php';
if(!defined('VESTRA_TERMS_VERSION')) define('VESTRA_TERMS_VERSION','2026-06-26'); // legal acceptance version

function vestra_demo_products(){
  $P = [
    [
      'id'=>'lac-pique-polo','brand'=>'Lacoste','name'=>'L1212 Classic Piqué Polo','mode'=>'fixed',
      /* 'list' = the one price the trade list quotes, valid at the 80 pc MOQ. Without it
         this article had no price of its own, only the tier ladder below -- so the price
         list left it out entirely, and the lowest number on the ladder (EUR 25.00 at 320 pc)
         was the only Lacoste figure a reader ever saw. */
      'cat'=>'Polos','sku'=>'LAC-L1212','moq'=>80,'unit'=>'pc','sample_price'=>50.0,'list'=>29.90,
      'desc'=>'Iconic L.12.12 cotton piqué polo, regular fit, short sleeves, 100% cotton. Pre-order — in stock from 5 May. Sold in lots of 8 (8+8 cartons); minimum order 80 pc (10 lots), at least 4 colours.',
      'seller'=>'GARAGE LE PARIS','seller_uid'=>'7ab30f26afedd840','origin'=>'EEA stock · proof on request','verified'=>true,'accent'=>'#1b5e3a',
      'sizes'=>'Lots of 8 · sizes 3–8 · min 80 pc (10 lots)','size_step'=>8,'min_colors'=>4,
      'colors'=>['Black','White','Beige','Navy','Yellow','Pink','Bordeaux','Green','Blue','Light Blue'],
      'images'=>['/uploads/lacoste/l1212-black.jpg','/uploads/lacoste/l1212-white.jpg','/uploads/lacoste/l1212-beige.jpg',
                 '/uploads/lacoste/l1212-navy.jpg','/uploads/lacoste/l1212-yellow.jpg','/uploads/lacoste/l1212-pink.jpg',
                 '/uploads/lacoste/l1212-bordeaux.jpg','/uploads/lacoste/l1212-green.jpg','/uploads/lacoste/l1212-blue.jpg',
                 '/uploads/lacoste/l1212-lightblue.png'],
      'linesheet'=>true,'sheet_file'=>'lacoste-l1212-poloshirt-preorder.pdf',
      'specs'=>[
        'Composition'=>'100% cotton piqué',
        'Fabric weight'=>'≈ 200 gsm',
        'Fit'=>'Regular fit · ribbed collar & cuffs · 2-button placket',
        'Care'=>'Machine wash 30°C · do not tumble dry',
        'Packaging'=>'Cartons of 8 per colourway (8+8)',
        'Lead time'=>'Pre-order — in stock from 5 May',
        'Season'=>'SS26 · core carryover',
        'Made in'=>'France / EU',
        'Customs code (HS)'=>'6105.10.00',
      ],
      'variants'=>[
        ['art'=>'LCMP103200','model'=>'L.12.12 00 031','color'=>'Black','image'=>'/uploads/lacoste/l1212-black.jpg'],
        ['art'=>'LCMP103201','model'=>'L.12.12 00 001','color'=>'White','image'=>'/uploads/lacoste/l1212-white.jpg'],
        ['art'=>'LCMP103202','model'=>'L.12.12 00 025','color'=>'Beige','image'=>'/uploads/lacoste/l1212-beige.jpg'],
        ['art'=>'LCMP103203','model'=>'L.12.12 00 166','color'=>'Navy','image'=>'/uploads/lacoste/l1212-navy.jpg'],
        ['art'=>'LCMP103205','model'=>'L.12.12 00 107','color'=>'Yellow','image'=>'/uploads/lacoste/l1212-yellow.jpg'],
        ['art'=>'LCMP103206','model'=>'L.12.12 00 T03','color'=>'Pink','image'=>'/uploads/lacoste/l1212-pink.jpg'],
        ['art'=>'LCMP103207','model'=>'L.12.12 00 476','color'=>'Bordeaux','image'=>'/uploads/lacoste/l1212-bordeaux.jpg'],
        ['art'=>'LCMP103208','model'=>'L.12.12 00 132','color'=>'Green','image'=>'/uploads/lacoste/l1212-green.jpg'],
        ['art'=>'LCMP103209','model'=>'L.12.12 00 4XA','color'=>'Blue','image'=>'/uploads/lacoste/l1212-blue.jpg'],
        ['art'=>'LCMP103210','model'=>'L.12.12 00 HBP','color'=>'Light Blue','image'=>'/uploads/lacoste/l1212-lightblue.png'],
      ],
      'tiers'=>[['min'=>80,'price'=>34.00],['min'=>160,'price'=>29.50],['min'=>320,'price'=>25.00]],
      /* Havuz operator istegiyle kapatildi. group_seed=96 / group_seed_n=5 de birlikte
         kaldirildi: o iki sayi "5 dogrulanmis butik, 96 adet taahhut etti" diye
         gorunuyordu ama karsiliginda TEK bir gercek taahhut yoktu -- groups.csv bos.
         Ziyaretci, kimsenin katilmadigi bir havuzu dolmakta sanip katiliyordu.
         Kapatirken sayilari birakmak, havuz yeniden acildiginda ayni yanilticiligin
         sessizce geri gelmesi demekti. */
      'group'=>false,
    ],
    [
      'id'=>'amiri-core-polo','brand'=>'Amiri','name'=>'Core Logo Polo — Ami de Cœur','mode'=>'fixed',
      'cat'=>'Polos','sku'=>'AMI-PL-014','moq'=>50,'unit'=>'pc','sample_price'=>65.0,
      'desc'=>'Signature Ami de Cœur piqué polo in 100% organic cotton, regular fit, with the tonal embroidered heart-A crest at the chest. Sold in cartons of 10 per colour (mixed sizes S–XXL); minimum order 50 pc, at least 2 colours. Authenticity verified on delivery.',
      'seller'=>'GARAGE LE PARIS','seller_uid'=>'7ab30f26afedd840','origin'=>'EEA stock · proof on request','verified'=>true,'accent'=>'#4a1420',
      'sizes'=>'Cartons of 10 · sizes S–XXL · min 50 pc (≥2 colours)','size_step'=>10,'min_colors'=>2,
      'colors'=>['Black','White','Navy','Grey'],
      'images'=>['/uploads/amiri/amiri-core-polo-black.png','/uploads/amiri/amiri-core-polo-white.png',
                 '/uploads/amiri/amiri-core-polo-navy.png','/uploads/amiri/amiri-core-polo-grey.png'],
      'linesheet'=>true,'sheet_file'=>'ami-paris-polo.pdf',
      'specs'=>[
        'Composition'=>'100% organic cotton piqué',
        'Fit'=>'Regular fit · ribbed collar & cuffs · 2-button placket',
        'Signature'=>'Ami de Cœur embroidered heart-A crest',
        'Care'=>'Machine wash 30°C · wash inside out · do not tumble dry',
        'Packaging'=>'Cartons of 10 per colour · mixed sizes S·M·L·XL·XXL',
        'Season'=>'SS26 · Summer',
        'Made in'=>'Portugal / EU',
        'Authenticity'=>'Verified on delivery · proof of sourcing on request',
      ],
      'variants'=>[
        ['art'=>'BFUPL001.760.001','model'=>'Ami de Cœur','color'=>'Black','image'=>'/uploads/amiri/amiri-core-polo-black.png'],
        ['art'=>'BFUPL001.760.100','model'=>'Ami de Cœur','color'=>'White','image'=>'/uploads/amiri/amiri-core-polo-white.png'],
        ['art'=>'BFUPL001.760.430','model'=>'Ami de Cœur','color'=>'Navy','image'=>'/uploads/amiri/amiri-core-polo-navy.png'],
        ['art'=>'BFUPL001.760.095','model'=>'Ami de Cœur','color'=>'Grey','image'=>'/uploads/amiri/amiri-core-polo-grey.png'],
      ],
      'tiers'=>[['min'=>50,'price'=>42.00],['min'=>150,'price'=>36.00],['min'=>300,'price'=>32.00]],
    ],
  ];
  return vestra_apply_price_overrides($P);
}
/* ── Admin price/MOQ overrides for the built-in demo products ──────────────
   The demo product(s) above are hard-coded, but the admin "Prices" editor lets
   the owner retune their MOQ, list price and tier pricing without touching code.
   Those edits live in data/product_overrides.json ({id => {moq,list,tiers}}) and
   are layered on top here so a redeploy never wipes them. Live seller listings
   are edited directly in listings.json instead (they are already mutable). */
function vestra_product_overrides(): array {
  $f = vestra_data_dir().'/product_overrides.json';
  if(is_readable($f)){ $d=json_decode((string)file_get_contents($f),true); if(is_array($d)) return $d; }
  return [];
}
function vestra_apply_price_overrides(array $products): array {
  $ov = vestra_product_overrides();
  if(!$ov) return $products;
  foreach($products as &$p){
    $o = $ov[$p['id']??''] ?? null;
    if(!is_array($o)) continue;
    if(isset($o['moq']))  $p['moq']  = (int)$o['moq'];
    if(isset($o['list'])) $p['list'] = (float)$o['list'];
    if(isset($o['mode']) && $o['mode']!=='') $p['mode'] = (string)$o['mode'];
    if(isset($o['tiers']) && is_array($o['tiers']) && $o['tiers']){
      $t=[];
      foreach($o['tiers'] as $row){
        if(!isset($row['min'],$row['price'])) continue;
        $t[]=['min'=>(int)$row['min'],'price'=>(float)$row['price']];
      }
      if($t){ usort($t, fn($a,$b)=>$a['min']<=>$b['min']); $p['tiers']=$t; }
    }
  }
  unset($p);
  return $products;
}
function vestra_save_product_overrides(array $ov): void {
  $f = vestra_data_dir().'/product_overrides.json';
  file_put_contents($f, json_encode($ov, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
}
/* Which products are demo (override-backed) vs live listings (listings.json-backed). */
function vestra_is_demo_product(string $id): bool {
  foreach(['lac-pique-polo','amiri-core-polo'] as $d){ if($d===$id) return true; }
  return false;
}
/* ── Brand logo SVGs (inline) ─────────────────────────────────────────── */
function vestra_brand_logo($brand){
  $L=[
    'DSQUARED2'=>
      '<svg viewBox="0 0 230 72" xmlns="http://www.w3.org/2000/svg" class="brand-logo">'.
      '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="white" '.
      'font-family="Georgia,\'Times New Roman\',serif" font-size="24" font-weight="900" letter-spacing="2">'.
      'DSQUARED<tspan dy="-10" font-size="15">2</tspan></text></svg>',

    'Lacoste'=>
      '<svg viewBox="0 0 200 62" xmlns="http://www.w3.org/2000/svg" class="brand-logo">'.
      '<text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" '.
      'font-family="\'Helvetica Neue\',Helvetica,Arial,sans-serif" font-size="26" font-weight="700" letter-spacing="6">'.
      'LACOSTE</text></svg>',

    'Ralph Lauren'=>
      '<svg viewBox="0 0 220 72" xmlns="http://www.w3.org/2000/svg" class="brand-logo">'.
      '<text x="50%" y="36%" dominant-baseline="middle" text-anchor="middle" fill="white" '.
      'font-family="Georgia,\'Times New Roman\',serif" font-size="19" font-weight="400" letter-spacing="4">'.
      'RALPH LAUREN</text>'.
      '<line x1="28%" y1="58%" x2="72%" y2="58%" stroke="rgba(255,255,255,.45)" stroke-width="0.8"/>'.
      '<text x="50%" y="78%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,.65)" '.
      'font-family="Georgia,\'Times New Roman\',serif" font-size="11" letter-spacing="4">POLO</text></svg>',

    'Amiri'=>
      '<svg viewBox="0 0 180 62" xmlns="http://www.w3.org/2000/svg" class="brand-logo">'.
      '<text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" '.
      'font-family="Georgia,\'Times New Roman\',serif" font-size="34" font-weight="400" letter-spacing="7">'.
      'AMIRI</text></svg>',

    'VESTRA Essentials'=>
      '<svg viewBox="0 0 200 68" xmlns="http://www.w3.org/2000/svg" class="brand-logo">'.
      '<text x="50%" y="38%" dominant-baseline="middle" text-anchor="middle" fill="white" '.
      'font-family="\'Helvetica Neue\',Arial,sans-serif" font-size="22" font-weight="700" letter-spacing="5">'.
      'VESTRA</text>'.
      '<text x="50%" y="72%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,.55)" '.
      'font-family="\'Helvetica Neue\',Arial,sans-serif" font-size="10" font-weight="300" letter-spacing="5">'.
      'ESSENTIALS</text></svg>',

    'Dolce & Gabbana'=>
      '<svg viewBox="0 0 220 72" xmlns="http://www.w3.org/2000/svg" class="brand-logo">'.
      '<text x="50%" y="40%" dominant-baseline="middle" text-anchor="middle" fill="white" '.
      'font-family="Georgia,\'Times New Roman\',serif" font-size="26" font-weight="700" letter-spacing="5">'.
      'D&amp;G</text>'.
      '<line x1="22%" y1="60%" x2="78%" y2="60%" stroke="rgba(255,255,255,.28)" stroke-width="0.7"/>'.
      '<text x="50%" y="78%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,.52)" '.
      'font-family="\'Helvetica Neue\',Arial,sans-serif" font-size="8.5" font-weight="400" letter-spacing="4">'.
      'DOLCE &amp; GABBANA</text></svg>',
  ];
  if (isset($L[$brand])) return $L[$brand];

  /* Wordmarks for the brands added from the supplier folders. Each is set in the house
     that brand actually uses -- a serif with wide tracking for the Paris/Milan houses, a
     tight grotesque for the streetwear labels -- so a catalogue page of mixed brands reads
     as designed rather than as a list of fallback text. Type only: these are typographic
     settings of the name, not reproductions of anyone's logo artwork. */
  $serif  = "Georgia,'Times New Roman',serif";
  $sans   = "'Helvetica Neue',Helvetica,Arial,sans-serif";
  $W = [
    'BALMAIN'        => [$serif, 22, 400, 8,   'BALMAIN',        'PARIS'],
    'Balenciaga'     => [$sans,  19, 500, 5.5, 'BALENCIAGA',     null],
    'Burberry'       => [$serif, 21, 400, 5,   'BURBERRY',       'LONDON'],
    'Casablanca'     => [$serif, 22, 400, 5,   'CASABLANCA',     'PARIS'],
    'Fendi'          => [$sans,  27, 700, 7,   'FENDI',          'ROMA'],
    'Fred Perry'     => [$serif, 20, 400, 4,   'FRED PERRY',     'EST. 1952'],
    'Givenchy'       => [$serif, 21, 400, 6,   'GIVENCHY',       'PARIS'],
    'Gucci'          => [$serif, 28, 400, 8,   'GUCCI',          null],
    'Jacquemus'      => [$sans,  25, 500, 6,   'JACQUEMUS',      null],
    'Valentino'      => [$serif, 21, 400, 5,   'VALENTINO',      'GARAVANI'],
    'Versace'        => [$serif, 22, 400, 6,   'VERSACE',        'MILANO'],
    'Marcelo Burlon' => [$sans,  15, 700, 2.4, 'MARCELO BURLON', 'COUNTY OF MILAN'],
    'GCDS'           => [$sans,  32, 800, 4,   'GCDS',           null],
  ];
  if (isset($W[$brand])) {
    [$ff, $size, $weight, $track, $main, $sub] = $W[$brand];
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $svg = '<svg viewBox="0 0 230 72" xmlns="http://www.w3.org/2000/svg" class="brand-logo">';
    if ($sub === null) {
      $svg .= '<text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" '
            . 'font-family="'.$e($ff).'" font-size="'.$size.'" font-weight="'.$weight.'" '
            . 'letter-spacing="'.$track.'">'.$e($main).'</text>';
    } else {
      $svg .= '<text x="50%" y="40%" dominant-baseline="middle" text-anchor="middle" fill="white" '
            . 'font-family="'.$e($ff).'" font-size="'.$size.'" font-weight="'.$weight.'" '
            . 'letter-spacing="'.$track.'">'.$e($main).'</text>'
            . '<line x1="26%" y1="60%" x2="74%" y2="60%" stroke="rgba(255,255,255,.28)" stroke-width="0.7"/>'
            . '<text x="50%" y="78%" dominant-baseline="middle" text-anchor="middle" '
            . 'fill="rgba(255,255,255,.55)" font-family="'.$e($sans).'" font-size="8.5" '
            . 'font-weight="400" letter-spacing="3.4">'.$e($sub).'</text>';
    }
    return $svg.'</svg>';
  }
  return null;
}

/* Brand card for a product with no photo. Returns the brand's wordmark when there is one,
   otherwise a monogram card built from the name -- so a brand nobody has drawn a wordmark
   for still renders as a designed tile instead of a bare string. Every caller that used to
   write `$logo ?: '<span>'.$brand.'</span>'` should use this instead. */
function vestra_brand_card($brand): string {
    $brand = trim((string)$brand);
    if ($brand === '') return '';
    $logo = vestra_brand_logo($brand);
    if ($logo) return $logo;

    /* Initials: first letter of each of the first two words ("Marcelo Burlon" -> MB), or the
       first two letters when the name is a single word ("Amiri" -> AM). */
    $words = preg_split('/[\s&]+/u', $brand, -1, PREG_SPLIT_NO_EMPTY) ?: [$brand];
    if (count($words) >= 2) {
        $mark = mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
    } else {
        $mark = mb_strtoupper(mb_substr($brand, 0, 2));
    }
    return '<span class="bmono"><span class="bmono-mark">'.htmlspecialchars($mark).'</span>'
         . '<span class="bmono-name">'.htmlspecialchars($brand).'</span></span>';
}

/* Card background colour. Products added through the batch importer carry no 'accent'
   field -- the seed catalogue set one by hand and nothing else ever did -- so every one of
   them rendered with an empty gradient AND logged an "Undefined array key" warning on each
   page view. Rather than patch a default into five call sites, resolve it here: an explicit
   accent wins, otherwise the brand name picks a stable colour from a small palette, so two
   products of the same brand always match and a new brand still looks deliberate. */
function vestra_accent(array $p): string {
    $a = trim((string)($p['accent'] ?? ''));
    if ($a !== '') return $a;
    $pal = ['#2f3140','#3a2f2a','#26323a','#332a38','#2a3a30','#3a3226','#2c2c34','#382a2a'];
    $brand = strtolower(trim((string)($p['brand'] ?? '')));
    if ($brand === '') return $pal[0];
    return $pal[hexdec(substr(md5($brand), 0, 2)) % count($pal)];
}

/* seller-added listings (saved by the seller panel) merged into the live catalog */
function vestra_data_dir(){ return dirname(__DIR__).'/data'; }
function vestra_listings(){ $f=vestra_data_dir().'/listings.json'; if(is_readable($f)){ $d=json_decode((string)file_get_contents($f),true); if(is_array($d)) return $d; } return []; }
function vestra_read_csv($name){ $f=vestra_data_dir().'/'.$name; $rows=[]; if(is_readable($f)&&($h=@fopen($f,'r'))){ $head=fgetcsv($h, null, ',', '"', '\\'); while(($r=fgetcsv($h, null, ',', '"', '\\'))!==false){ if($head){ $n=count($head); $r=array_slice(array_pad($r,$n,''),0,$n); $rows[]=array_combine($head,$r);} } fclose($h);} return array_reverse($rows); }
/* Upgrade a CSV's header row in place when new trailing columns are added to a schema after
   the file already exists on a live server — data rows are never touched (the reader above
   already pads short rows with ''), only the first line is rewritten, and only when the
   existing header is exactly a prefix of the new one (anything unexpected is left alone). */
function vestra_csv_ensure_header(string $name, array $header): void {
    $f = vestra_data_dir().'/'.$name;
    if (!is_file($f)) return;
    $fh = @fopen($f, 'r'); if (!$fh) return;
    $firstLine = fgets($fh);
    if ($firstLine === false) { fclose($fh); return; }
    $current = str_getcsv(rtrim($firstLine, "\r\n"), ',', '"', '\\');
    $rest = stream_get_contents($fh);
    fclose($fh);
    if ($current === $header || array_slice($header, 0, count($current)) !== $current) return;
    $tmp = fopen('php://temp', 'r+');
    fputcsv($tmp, $header, ',', '"', '\\');
    rewind($tmp); $newHeaderLine = stream_get_contents($tmp); fclose($tmp);
    file_put_contents($f, $newHeaderLine.$rest, LOCK_EX);
}
function vestra_live_listings(){ return array_values(array_filter(vestra_listings(), fn($p)=>($p['status']??'approved')==='approved')); }
/* Bundled catalogue drops shipped in code (e.g. the DSQUARED2 model list). They show
   in the catalogue straight after a deploy — no import click needed — but are hidden
   for any item already present as a real listing (same id or brand+SKU), so importing
   them into listings.json never double-lists them. */
function vestra_seed_catalog(){
    $f = __DIR__.'/dsquared_seed.json';
    if(is_readable($f)){ $d=json_decode((string)file_get_contents($f),true); if(is_array($d)) return $d; }
    return [];
}
function vestra_products(){
    $live = vestra_live_listings();
    $seen = [];
    foreach($live as $l){
        $seen['id:'.strtolower((string)($l['id']??''))] = true;
        $seen['bs:'.strtolower(trim(($l['brand']??'').'|'.($l['sku']??'')))] = true;
    }
    $seed = [];
    foreach(vestra_seed_catalog() as $p){
        $id='id:'.strtolower((string)($p['id']??''));
        $bs='bs:'.strtolower(trim(($p['brand']??'').'|'.($p['sku']??'')));
        if(isset($seen[$id]) || isset($seen[$bs])) continue;
        $seed[] = $p;
    }
    return array_merge(vestra_demo_products(), $live, $seed);
}
/**
 * Which storefront section a product belongs to.
 *
 * The catalogue was one flat list of 344 items — brand and category, no notion of a
 * collection. That worked while everything in it was curated designer stock. It stops
 * working the moment a partner's own range arrives: footwear from a Spanish wholesaler
 * next to Balenciaga on the same grid reads as one assortment, and it flattens both.
 *
 * A stored field rather than a rule derived from brand or seller. A rule would be
 * wrong the first time it is tested — a partner may well carry a premium house, and a
 * curated line may be footwear — and a mis-shelved product is not a bug anyone reports,
 * it is a product nobody finds. The operator decides, per item, and the field says so.
 *
 * Anything without the field is premium: that is what the existing catalogue is, and a
 * default that silently empties the main section on deploy would be the worst outcome.
 */
function vestra_sections(): array {
    return ['premium' => 'Premium Brands', 'footwear' => 'Footwear'];
}
function vestra_product_section(array $p): string {
    $s = strtolower(trim((string)($p['section'] ?? '')));
    return isset(vestra_sections()[$s]) ? $s : 'premium';
}
function vestra_section_label(string $s): string {
    return vestra_sections()[strtolower(trim($s))] ?? vestra_sections()['premium'];
}

function vestra_find($id){ foreach(vestra_products() as $p){ if($p['id']===$id) return $p; } return null; }
function vestra_cats(){ $c=[]; foreach(vestra_products() as $p){ $c[$p['cat']]=1; } return array_keys($c); }
function vestra_primary_image(array $p): string { if(!empty($p['images'])&&is_array($p['images'])) return $p['images'][0]; return $p['image']??''; }
/* Mask a seller/company name for viewers who are not yet approved (freigeschaltet):
   "Milano Fashion GmbH" → "M···". Never reveals more than the first letter. */
function vestra_mask_seller(string $s): string {
    $s = trim($s);
    return $s === '' ? '' : mb_strtoupper(mb_substr($s, 0, 1)).'···';
}
/* Full Fashion & Accessories taxonomy (grouped) — used by the seller's product form. */
/* Satici panelindeki kategori acilir listesi buradan geliyor (seller.php: urun ekle
   ve urun duzenle). Bu yuzden listede OLMAYAN bir kategori sadece "secilemez" degil:
   duzenleme formu, urunun kategorisi listede yoksa "Other" secenegini SECILI getiriyor
   (seller.php'deki in_array kontrolu), yani satici o urunu acip kaydettiginde dogru
   kategori "Other" ile eziliyor. Katalogda kullanilan 6 kategori (Jeans Shorts,
   Swim Shorts, Tracksuit Sets ve uc kadin kategorisi) burada yoktu ve o kategorilerde
   34 canli urun duruyor -- hepsi bu tuzagin icindeydi. Taksonomi katalogun gercekten
   sattigi seyi yansitmali; asagidakiler o yuzden eklendi. */
function vestra_all_cats(){
  return [
    'Tops'               => ['T-Shirts',"Women's T-Shirts",'Polos','Shirts','Blouses','Sweaters & Knitwear','Cardigans','Hoodies & Sweatshirts','Tank Tops'],
    'Bottoms'            => ['Trousers & Chinos','Jeans',"Women's Jeans",'Shorts','Jeans Shorts','Skirts','Leggings'],
    'Outerwear'          => ['Jackets','Coats','Blazers','Vests & Gilets'],
    'Dresses & Suits'    => ['Dresses','Suits','Jumpsuits & Playsuits'],
    'Activewear & Swim'  => ['Activewear','Sportswear','Tracksuits','Tracksuit Sets','Swimwear','Swim Shorts',"Women's Swimwear"],
    'Underwear & Socks'  => ['Underwear','Lingerie','Socks & Hosiery','Sleepwear','Loungewear','Basics'],
    'Footwear'           => ['Sneakers','Boots','Sandals','Heels','Flats','Loafers','Slippers'],
    'Bags & Luggage'     => ['Handbags','Backpacks','Tote Bags','Wallets & Purses','Travel & Luggage'],
    'Accessories'        => ['Belts','Hats & Caps','Scarves & Shawls','Gloves','Sunglasses','Eyewear','Ties','Hair Accessories','Phone Cases'],
    'Jewelry & Watches'  => ['Jewelry','Watches'],
    'Kids & Baby'        => ['Kidswear','Babywear'],
  ];
}
/* Curated colour palette for listings (name => swatch hex). Names are t()-translated at render. */
function vestra_colors(){
  return [
    'Black'=>'#17181c','Navy'=>'#1f2a44','Blue'=>'#2b46c4','Light Blue'=>'#8db8d8','White'=>'#f2f1ec',
    'Grey'=>'#8e9094','Dark Grey'=>'#4a4c52','Red'=>'#b3242c','Bordeaux'=>'#5c1a24','Green'=>'#14532d',
    'Beige'=>'#d9c9a3','Pink'=>'#e0a3b6','Yellow'=>'#e3c14f','Orange'=>'#d97b29','Brown'=>'#6b4a2f',
    'Cream'=>'#f1e8d2','Khaki'=>'#6a704c','Fuchsia'=>'#d1256e',
  ];
}
/* Small colour-dot row (shop cards, product page, admin). $withNames adds the label after each dot. */
function vestra_color_dots(array $colors, int $max=7, bool $withNames=false): string {
  $pal=vestra_colors(); $out=''; $shown=0;
  foreach($colors as $c){
    if(!isset($pal[$c])) continue;
    if($shown>=$max){ $out.='<span class="cmore">+'.(count($colors)-$shown).'</span>'; break; }
    $ring = in_array($c,['Black','Navy','Bordeaux','Brown','Green'],true) ? 'rgba(255,255,255,.28)' : 'rgba(0,0,0,.25)';
    $out.='<span class="cdot" title="'.htmlspecialchars(t($c)).'" style="background:'.$pal[$c].';box-shadow:inset 0 0 0 1px '.$ring.'"></span>';
    if($withNames) $out.='<span class="cname">'.htmlspecialchars(t($c)).'</span>';
    $shown++;
  }
  return $out ? '<span class="cdots">'.$out.'</span>' : '';
}
/* True for listings that use the per-colour carton picker (e.g. Lacoste/Ralph Lauren polos:
   min 4 colours, cartons of 8 or 10 per colour) instead of a plain colour checklist. */
function vestra_is_colorqty_listing(array $p): bool {
  return !empty($p['colors']) && !empty($p['min_colors']) && (int)($p['size_step'] ?? 0) > 1;
}
/* Singular-safe "at least N colour(s)" phrasing — most listings require 4, but some
   only require 1, where "at least 1 colours" would read wrong. */
function vestra_colours_phrase(int $n): string {
  return $n === 1 ? t('at least 1 colour') : sprintf(t('at least %d colours'), $n);
}
function vestra_colours_warn(int $n): string {
  return $n === 1 ? t('Please select at least 1 colour.') : sprintf(t('Please select at least %d colours.'), $n);
}
/* Validate + snap posted per-colour quantities ($posted = ['ColourName'=>qty,...], e.g. from
   $_POST['cq']) against a listing's own colour list and pack step. Only colours the listing
   actually offers count, and every quantity is snapped down to the nearest step multiple —
   never trusts the client. Returns null when the listing isn't in per-colour-qty mode.
   Otherwise returns ['lines'=>['Black ×16','Navy ×8',...], 'qty'=>24] — a lines entry is
   included only once its snapped quantity is > 0, so it also doubles as "colours selected". */
function vestra_parse_colorqty(array $p, array $posted): ?array {
  if (!vestra_is_colorqty_listing($p)) return null;
  $step = (int)$p['size_step'];
  $allowed = array_flip((array)$p['colors']);
  $lines = []; $qty = 0;
  foreach ($posted as $name => $raw) {
    $name = (string)$name;
    if (!isset($allowed[$name])) continue;
    $n = (int)(round(max(0, (int)$raw) / $step) * $step);
    if ($n <= 0) continue;
    $lines[] = $name.' ×'.$n;
    $qty += $n;
  }
  return ['lines' => $lines, 'qty' => $qty];
}
/* Same as vestra_parse_colorqty() but for the cart/JS path, which submits the client-built
   "Black ×16" style tokens (cart.php just displays these verbatim) instead of a raw
   ['Name'=>qty] map. Re-derives the map from the tokens and re-validates from scratch —
   the client's numbers are never trusted, only which colour+step they point at. */
function vestra_parse_colorqty_tokens(array $p, array $tokens): ?array {
  $map = [];
  foreach ($tokens as $tok) {
    if (preg_match('/^(.*?)\s*×\s*(\d+)$/u', trim((string)$tok), $m)) $map[$m[1]] = (int)$m[2];
  }
  return vestra_parse_colorqty($p, $map);
}
function vestra_unit_price($p,$qty){ if(empty($p['tiers'])) return 0.0; $price=$p['tiers'][0]['price']; foreach($p['tiers'] as $t){ if($qty>=$t['min']) $price=(float)$t['price']; } return $price; }
function vestra_from_price($p){ if(empty($p['tiers'])) return 0.0; $m=null; foreach($p['tiers'] as $t){ $m=($m===null)?$t['price']:min($m,$t['price']); } return $m; }
function vestra_discount($p){ if(($p['mode']??'')!=='sale'||empty($p['list'])) return 0; return (int)round(100*($p['list']-vestra_from_price($p))/$p['list']); }
/* Bir urun ancak liste fiyati gercekten kademe fiyatinin USTUNDEyse "indirimli"dir.
   Veri kayiyor: kademe fiyatini guncelleyip 'list' alanina dokunmayinca mode='sale'
   ama list == fiyat kaliyordu; vitrinde "-%0" rozeti ve ayni sayinin uzeri cizili
   hali cikiyordu. Musteriye donuk her yer artik ham mode'u degil bunu soruyor. */
function vestra_on_sale($p){ return ($p['mode'] ?? '') === 'sale' && vestra_discount($p) > 0; }
/* Vitrinde gecerli mod: gercek indirimi olmayan bir "sale" urunu sadece sabit
   fiyatli bir urundur — rozeti de, ustu cizili fiyati da, filtresi de oyle davranir. */
function vestra_display_mode($p){ $m = $p['mode'] ?? 'fixed'; return ($m === 'sale' && !vestra_on_sale($p)) ? 'fixed' : $m; }
function eur($n){ return '€'.number_format((float)$n,2,'.',','); }

/* ───────────────────────── GROUP ORDERS (collective wholesale) ─────────────────────────
 * Small buyers pool their quantities on one product until the seller's wholesale MOQ is
 * reached — then the lowest tier price unlocks for everyone. VESTRA runs the countdown +
 * escrow; the seller just ticks "open for group buying". A pool is 1:1 with a product id.
 */
if(!defined('VESTRA_GROUP_DEFAULT_DAYS')) define('VESTRA_GROUP_DEFAULT_DAYS', 14);

/* Target = qty that unlocks the wholesale price (seller override, else the top tier's min). */
function vestra_group_target($p){
  if(!empty($p['group_target'])) return max(1,(int)$p['group_target']);
  $last=end($p['tiers']); return max(1,(int)($last['min']??$p['moq']));
}
/* Per-buyer minimum commitment for a pool. Separate from the product's own moq on
   purpose: a pool can demand a far larger ticket than the same article's ordinary
   wholesale minimum (this one asks 104 against a catalogue moq of 20), and writing
   that into moq would move the minimum everywhere the product is sold. */
function vestra_group_min_qty($p){
  if(!empty($p['group_min_qty'])) return max(1,(int)$p['group_min_qty']);
  return max(1,(int)($p['moq'] ?? 1));
}
/* ─── Assortment pools ──────────────────────────────────────────────────────
   A pool is otherwise 1:1 with one product. An assortment pool still HAS a host
   listing (so ids, seller, order plumbing all keep working) but covers several
   catalogue models: the buyer commits a total quantity and spreads it across
   them. group_models holds the member ids.

   Ids that no longer resolve are dropped silently HERE but rejected at write
   time by set-product.yml — a pool advertised as "10 models" that quietly
   renders 8 would shortchange the buyer without anyone noticing. */
function vestra_group_models($p): array {
  $ids = $p['group_models'] ?? [];
  if(!is_array($ids) || !$ids) return [];
  $out = [];
  foreach($ids as $id){
    $m = vestra_find((string)$id);
    if(!$m) continue;
    $out[] = ['id'=>$m['id'], 'name'=>(string)($m['name']??''), 'sku'=>(string)($m['sku']??''),
              'image'=>vestra_primary_image($m)];
  }
  return $out;
}
function vestra_group_is_assortment($p): bool { return count(vestra_group_models($p)) > 1; }
/* Minimum number of colours a pool commitment must pick. Falls back to the
   listing's own min_colors so a pool inherits the carton rule the product
   already sells under; group_min_colors only exists to let a pool demand a
   WIDER spread than the ordinary order flow does. 0 = no colour choice. */
function vestra_group_min_colors($p): int {
  if(isset($p['group_min_colors'])) return max(0,(int)$p['group_min_colors']);
  return max(0,(int)($p['min_colors'] ?? 0));
}
/* Every photo the catalogue holds for a pool — the pool page shows the whole
   set, not just the hero. Assortment pools show their members' photos instead
   (see vestra_group_models). */
function vestra_group_gallery($p): array {
  $imgs = $p['images'] ?? [];
  if(!is_array($imgs)) return [];
  $out = [];
  foreach($imgs as $im){ $im=trim((string)$im); if($im!=='') $out[]=$im; }
  return $out;
}
/* Display name for a pool. An assortment is not "one product", so it carries its
   own title; without one it falls back to the host listing's name. */
function vestra_group_title($p): string {
  $t = trim((string)($p['group_title'] ?? ''));
  return $t !== '' ? $t : (string)($p['name'] ?? '');
}
/* Unlocked unit price once the target is met (seller override, else the lowest tier price). */
function vestra_group_price($p){
  if(!empty($p['group_price'])) return (float)$p['group_price'];
  return (float)vestra_from_price($p);
}
/* Deadline, honouring the one-time extension. A pool that misses its target is
   extended once (group_extend_days) before anyone's deposit is refunded, so the
   effective deadline moves — group_deadline itself is left untouched as the
   record of what was originally promised. */
function vestra_group_deadline($p){
  if(!empty($p['group_extended_to'])) return $p['group_extended_to'];
  if(!empty($p['group_deadline'])) return $p['group_deadline'];
  $start=$p['group_started'] ?? date('c');
  return date('c', strtotime($start.' +'.VESTRA_GROUP_DEFAULT_DAYS.' days'));
}
/* Buyer commitments for one pool (newest first).
   Two sources, deliberately merged rather than migrated: the legacy no-payment
   rows in data/groups.csv (pools opened before deposits existed — they are real
   commitments and must keep counting) and the deposit-paid records in
   data/pool_commits.json. Deposit records are normalised to the CSV row shape so
   every caller — the pool page, the progress bar, admin — stays unaware of which
   store a commitment came from. */
function vestra_group_commits($poolId){
  $rows=vestra_read_csv('groups.csv');
  $out=array_values(array_filter($rows, function($r) use ($poolId){ return ($r['pool_id']??'')===$poolId; }));

  require_once __DIR__.'/pools.php';
  foreach(pool_commits_for($poolId) as $c){
    $out[]=[
      'timestamp'=>$c['created']??'', 'pool_id'=>$poolId, 'ref'=>$c['ref']??'',
      'company'=>$c['company']??'', 'name'=>$c['name']??'', 'email'=>$c['email']??'',
      'country'=>$c['country']??'', 'qty'=>$c['qty']??0,
      'unit_price'=>$c['unit_price']??0, 'est_total'=>$c['total']??0,
      'deposit_paid'=>$c['deposit']??0, 'status'=>$c['status']??'',
    ];
  }
  usort($out, function($a,$b){ return strcmp($b['timestamp']??'', $a['timestamp']??''); });
  return $out;
}
/* Enrich a product with live pool state (committed qty, % progress, days left, status). */
function vestra_group_enrich($p){
  $target=vestra_group_target($p);
  $commits=vestra_group_commits($p['id']);
  $committed=(int)($p['group_seed']??0);
  foreach($commits as $c){ $committed+=(int)($c['qty']??0); }
  $deadline=vestra_group_deadline($p);
  $secsLeft=strtotime($deadline)-time();
  $daysLeft=max(0,(int)ceil($secsLeft/86400));
  $pct=$target>0?max(0,min(100,(int)round(100*$committed/$target))):0;
  $remaining=max(0,$target-$committed);
  $status = $committed>=$target ? 'funded' : ($secsLeft<=0 ? 'expired' : 'open');
  return $p + [
    '_target'=>$target,'_gprice'=>vestra_group_price($p),'_committed'=>$committed,
    '_remaining'=>$remaining,'_participants'=>count($commits)+(int)($p['group_seed_n']??0),
    '_deadline'=>$deadline,'_daysLeft'=>$daysLeft,'_pct'=>$pct,'_status'=>$status,'_commits'=>$commits,
  ];
}
/* All products opened for group buying, enriched + sorted (almost-funded first). */
function vestra_group_pools(){
  $pools=[];
  foreach(vestra_products() as $p){ if(!empty($p['group'])) $pools[]=vestra_group_enrich($p); }
  usort($pools, function($a,$b){ return $b['_pct']<=>$a['_pct']; });
  return $pools;
}
function vestra_group_pool($id){ $p=vestra_find($id); if(!$p||empty($p['group'])) return null; return vestra_group_enrich($p); }

/* ─── Uploads ─── */
/* Validate + store one uploaded product photo; returns '/uploads/…' or '' on any failure.
   Shared by seller-add (new listing) and seller edit (replace photos). */
function vestra_save_upload_photo(array $f): string {
  $updir = dirname(__DIR__).'/uploads';
  if(!is_dir($updir)) @mkdir($updir,0755,true);
  /* Rewritten when the sheet rule is missing, not only when the file is absent: the
     deny-code rule shipped first, so every existing install already has an .htaccess
     here and a create-if-missing check would never add the line-sheet rule to it. */
  $ht = $updir.'/.htaccess';
  if(!is_file($ht) || strpos((string)@file_get_contents($ht), '^sheet_') === false){
    @file_put_contents($ht,
      "Options -Indexes\n<FilesMatch \"(?i)\\.(php\\d*|phtml|phar|pl|py|cgi|sh|asp|aspx|jsp)$\">\n  Require all denied\n</FilesMatch>\n"
     ."<FilesMatch \"(?i)^sheet_\">\n  Require all denied\n</FilesMatch>\n");
  }
  if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($f['size']??0)<=0||$f['size']>5*1024*1024) return '';
  $info=@getimagesize($f['tmp_name']); if(!$info) return '';
  $ext=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp',IMAGETYPE_GIF=>'gif'][$info[2]]??'';
  if($ext==='') return '';
  $name='img_'.bin2hex(random_bytes(8)).'.'.$ext;
  return @move_uploaded_file($f['tmp_name'],$updir.'/'.$name)?'/uploads/'.$name:'';
}
/* Collect photos[] uploads (up to $max) → list of stored URLs. */
function vestra_collect_photo_uploads(string $field='photos', int $max=6): array {
  $out=[];
  if(isset($_FILES[$field]['name'])&&is_array($_FILES[$field]['name'])){
    for($i=0;$i<min(count($_FILES[$field]['name']),$max);$i++){
      $f=['name'=>$_FILES[$field]['name'][$i],'type'=>$_FILES[$field]['type'][$i],
          'tmp_name'=>$_FILES[$field]['tmp_name'][$i],'error'=>$_FILES[$field]['error'][$i],'size'=>$_FILES[$field]['size'][$i]];
      if($url=vestra_save_upload_photo($f)) $out[]=$url;
    }
  }
  return $out;
}

/* ─── Listings & status helpers ─── */
function vestra_save_listings(array $list): void {
    $f = vestra_data_dir().'/listings.json';
    file_put_contents($f, json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function vestra_listing_by_id(string $id): ?array {
    foreach (vestra_listings() as $l) if (($l['id']??'') === $id) return $l; return null;
}
/* ─── Ownership helpers (multi-seller safety — every seller only ever touches their own data) ─── */
function vestra_seller_listings(string $uid): array {
    if ($uid === '') return [];
    return array_values(array_filter(vestra_listings(), fn($p) => ($p['seller_uid']??'') === $uid));
}
function vestra_listing_owner(string $id): ?string {
    $l = vestra_listing_by_id($id);
    return $l ? ($l['seller_uid'] ?? '') : null;
}

/* ─── Monthly listing quota ────────────────────────────────────────────────────
   Satici tarafi UCRETSIZ: platform yalnizca satistan komisyon aliyor. Kota bir
   odeme kaldiraciydi (Starter 10/ay, Pro 100/ay -- "daha fazlasi icin yukselt"),
   odeme kalkinca dayanagi da kalmadi. Herkes icin null = sinirsiz.
   $tier hala aliniyor: cagiran taraflarin hepsi tier gecirlyor ve imzayi
   degistirmek bu isle ilgisiz dosyalari da degistirmek olurdu. */
function vestra_seller_monthly_quota_limit(string $tier): ?int {
    return null;
}
/* ─── Commission rate — artik platformun TEK gelir kalemi ──────────────────────
   Eskiden kademeye gore degisiyordu (Pro %3,2 · Elite %2,8) ve aylik uyeligin
   USTUNE biniyordu; dusuk oran, ucret odeyenlere verilen bir oduldu. Uyelik
   kalkti, dolayisiyla indirimli oranlarin karsiligi da kalmadi: herkes ayni
   orani odüyor. Tier'i olan eski hesaplar da bu orana geliyor -- birakilsalardi
   ucret odemeyi biraktiklari halde indirimli oranda kalirlardi.
   Alici tarafi bundan hic etkilenmiyor (bkz. vestra_charge_order_commission()). */
function vestra_seller_commission_rate(string $tier): float {
    return VESTRA_COMMISSION_RATE;
}
/* ─── Urun adi: markayi iki kez yazma ──────────────────────────────────────────
   Bazi katalog kayitlarinda marka adi urun ADININ icinde de duruyor
   (brand "Balenciaga" + name "Balenciaga Print T-Shirt"). Duz birlestirme
   "Balenciaga Balenciaga Print T-Shirt" uretiyor -- musteriye ve gumruge giden
   belgelerde ucuz duruyor.

   Kural TEK yerde: fatura ve e-posta ayni fonksiyonu cagiriyor. Iki kopya
   birakilsaydi biri duzeltilip digeri unutulurdu; bu projede bugun birkac kez
   goruldu (urun ekleme kapisi, teklif yaniti, KYB onayi). */
function vestra_product_label(string $brand, string $name): string {
    $brand = trim($brand); $name = trim($name);
    if ($brand === '') return $name;
    if ($name === '')  return $brand;
    return stripos($name, $brand) === 0 ? $name : $brand.' '.$name;
}

/* ─── Vergi numarasi alani: soruyu ULKEYE gore sor ─────────────────────────────
   Alan hep "VAT / Tax ID" etiketi ve "DE123456789" ipucu ile cikiyordu. ABD'de
   KDV YOK; oradaki karsiligi IRS'in verdigi EIN. Amerikali bir kullaniciya Alman
   KDV numarasi sorunca ya bos birakiyor ya "n/a" yaziyor -- canli kayitta tam
   olarak bu var (vat_id = "n/a"), ustelik yanina bir "vat_cert" belgesi de
   onaylanmis durumda. Alan zaten dogru alandi; yanlis olan soruydu.
   Faturada da ayni etiket kullaniliyor: ABD'li bir firmaya "VAT ID" yazmak,
   gumruk ve muhasebe tarafinda var olmayan bir numarayi ariyormus gibi durur. */
function vestra_tax_id_hint(string $country): array {
    $c = strtoupper(trim($country));
    // Hem ISO kodu hem tam ad gelebiliyor (kayit formu ad, teshis kodu yaziyor).
    if ($c === 'US' || $c === 'USA' || $c === 'UNITED STATES') {
        return ['label' => 'EIN (Federal Tax ID)', 'placeholder' => '12-3456789', 'short' => 'EIN'];
    }
    if ($c === 'GB' || $c === 'UK' || $c === 'UNITED KINGDOM') {
        return ['label' => 'VAT registration number', 'placeholder' => 'GB123456789', 'short' => 'VAT no.'];
    }
    if ($c === 'CH' || $c === 'SWITZERLAND' || $c === 'SCHWEIZ') {
        return ['label' => 'UID / MWST number', 'placeholder' => 'CHE-123.456.789 MWST', 'short' => 'UID'];
    }
    if ($c === 'DE' || $c === 'GERMANY' || $c === 'DEUTSCHLAND' || $c === 'AT' || $c === 'AUSTRIA') {
        return ['label' => 'USt-IdNr.', 'placeholder' => 'DE123456789', 'short' => 'USt-IdNr.'];
    }
    if ($c === 'TR' || $c === 'TURKEY' || $c === 'TÜRKIYE' || $c === 'TURKIYE') {
        return ['label' => 'Vergi kimlik numarası', 'placeholder' => '1234567890', 'short' => 'VKN'];
    }
    return ['label' => 'VAT / Tax ID', 'placeholder' => 'DE123456789', 'short' => 'VAT ID'];
}
/* Nullable on purpose: seller.php reads this straight off $AUTH_USER, which is null when a
   session has expired between page loads. With an `array` type that was a fatal TypeError —
   the whole seller dashboard 500'd instead of bouncing the visitor to the login page (the
   live error log had it five times). No account simply means nothing used yet. */
function vestra_seller_monthly_quota_used(?array $acc): int {
    $rec = is_array($acc) ? ($acc['listing_quota'] ?? null) : null;
    if (!is_array($rec) || ($rec['month'] ?? '') !== date('Y-m')) return 0;
    return (int)($rec['count'] ?? 0);
}
function vestra_seller_monthly_quota_bump(string $uid): void {
    $acc = null;
    foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $uid) { $acc = $a; break; } }
    if (!$acc) return;
    auth_update($uid, ['listing_quota' => ['month' => date('Y-m'), 'count' => vestra_seller_monthly_quota_used($acc) + 1]]);
}
/** True when this seller has hit their monthly quota (always false for uncapped tiers). */
function vestra_seller_quota_exhausted(array $acc): bool {
    $limit = vestra_seller_monthly_quota_limit($acc['membership_tier'] ?? '');
    return $limit !== null && vestra_seller_monthly_quota_used($acc) >= $limit;
}
function vestra_listing_by_sku(string $sku): ?array {
    if ($sku === '') return null;
    foreach (vestra_listings() as $l) if (($l['sku']??'') === $sku) return $l;
    return null;
}
/* Parse the "12x SKU-123 @19.99 | 5x SKU-456 @9.99" string order.php writes into orders.csv's items column.
 *
 * The SKU is matched greedily up to the final "@price" rather than as a single non-space run:
 * some catalogue codes genuinely contain spaces ("G80A3T FU7EQ W"). With \S+ the whole segment
 * failed to match and the line was dropped without a trace — the order stayed intact in
 * orders.csv and in the confirmation mail, but every later read of it (buyer/seller/admin views,
 * the invoice and its per-seller split, order_has_seller_sku) silently lost those items and the
 * money attached to them. */
function vestra_parse_order_items(string $items): array {
    $out = [];
    foreach (explode(' | ', $items) as $seg) {
        if (preg_match('/^(\d+)x\s+(.+)\s+@([\d.]+)$/', trim($seg), $m)) {
            $out[] = ['qty'=>(int)$m[1], 'sku'=>trim($m[2]), 'unit'=>(float)$m[3]];
        }
    }
    return $out;
}
/* Render an order's stored items string for a table cell.
 *
 * The stored format is one segment per line item ("80x sku-a @32.00 | 40x sku-b @39.90"), so it
 * grows without bound as an order gets larger — real B2B carts run to a dozen lines. Printed raw
 * into a <td> that made the whole table unusable: a table cell in the default (auto) layout
 * algorithm IGNORES max-width, so the column simply widened to fit the string and squeezed every
 * other column out of the viewport. A block-level wrapper is not sized by the table algorithm and
 * does honour max-width, which is why the markup below is a <div> inside the cell rather than
 * styling on the cell itself.
 *
 * Shows the first $show lines and folds the rest into a count; the untruncated string stays
 * reachable as the title tooltip. Falls back to the raw (but bounded) text for rows whose format
 * predates the parser. */
function vestra_order_items_cell(string $items, int $show = 2, int $px = 210): string {
    $wrap = '<div class="itemscell" style="max-width:'.(int)$px.'px"';
    $lines = vestra_parse_order_items($items);
    if (!$lines) {
        return $items === '' ? '<span class="itemsmore">—</span>'
                             : $wrap.'>'.htmlspecialchars($items).'</div>';
    }
    $out = '';
    foreach (array_slice($lines, 0, $show) as $l) {
        $out .= '<div class="itemsline"><b>'.(int)$l['qty'].'×</b> '.htmlspecialchars($l['sku']).'</div>';
    }
    if (($rest = count($lines) - $show) > 0) {
        $out .= '<div class="itemsmore">+'.$rest.' '.htmlspecialchars(t('more')).'</div>';
    }
    return $wrap.' title="'.htmlspecialchars($items).'">'.$out.'</div>';
}

/* An order can bundle SKUs from several sellers (buyer's cart isn't seller-partitioned) — true if
   at least one line item in this order row belongs to the given seller's SKU list. */
function vestra_order_has_seller_sku(array $orderRow, array $sellerSkus): bool {
    if (!$sellerSkus) return false;
    foreach (vestra_parse_order_items($orderRow['items'] ?? '') as $it) {
        if (in_array($it['sku'], $sellerSkus, true)) return true;
    }
    return false;
}
/* ── SEO: the houses actually in stock ───────────────────────────────────────────────
 *
 * Buyers do not search "B2B fashion marketplace". They search "Lacoste Großhandel" or
 * "comprar Gucci al por mayor" — a brand name plus the wholesale word in their own
 * language. These helpers build that from live inventory, so the tags describe what is
 * genuinely on the site today rather than a list someone has to remember to update.
 *
 * Nominative use only: naming a brand we hold genuine EEA stock of. Nothing here claims
 * to be an official or authorised dealer, which is why no such word appears.
 */
function vestra_seo_brands(int $max = 14): array {
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (vestra_products() as $p) {
            $b = trim((string)($p['brand'] ?? ''));
            if ($b !== '' && !in_array($b, $all, true)) $all[] = $b;
        }
        sort($all);
    }
    return $max > 0 ? array_slice($all, 0, $max) : $all;
}

/** "wholesale" in the visitor's language — the word that actually appears in the query. */
function vestra_seo_wholesale_word(string $lang): string {
    return ['en'=>'wholesale','fr'=>'en gros','it'=>'ingrosso','es'=>'al por mayor','de'=>'Großhandel'][$lang] ?? 'wholesale';
}

/* Brand <-> URL slug. The landing pages live at /wholesale/<slug>, so the slug has to
   survive a round trip: "DSQUARED2" -> "dsquared2", "Fred Perry" -> "fred-perry". The
   reverse lookup goes through live stock rather than un-slugifying, because there is no
   rule that turns "fred-perry" back into the exact capitalisation the catalogue uses. */
function vestra_brand_slug(string $brand): string {
    $s = strtolower(trim($brand));
    $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? $s;
    return trim($s, '-');
}
function vestra_brand_from_slug(string $slug): ?string {
    $slug = vestra_brand_slug($slug);
    foreach (vestra_seo_brands(0) as $b) if (vestra_brand_slug($b) === $slug) return $b;
    return null;
}

/* The words a trade buyer actually types. "Lacoste wholesale" is one query; the buyer who
   is ready to order searches "Lacoste B2B supplier", "Lacoste bulk", "Lacoste stock lot".
   Those are the ones worth ranking for -- they carry intent, not curiosity. */
function vestra_seo_b2b_terms(string $lang): array {
    return [
        'en' => ['wholesale', 'B2B supplier', 'bulk', 'stock lot', 'trade prices', 'for boutiques'],
        'de' => ['Großhandel', 'B2B Lieferant', 'Restposten', 'Posten', 'Händlerpreise', 'für Boutiquen'],
        'fr' => ['en gros', 'fournisseur B2B', 'grossiste', 'destockage', 'prix professionnels', 'pour boutiques'],
        'es' => ['al por mayor', 'proveedor B2B', 'mayorista', 'lote de stock', 'precios de mayorista', 'para boutiques'],
        'it' => ['ingrosso', 'fornitore B2B', 'grossista', 'stock lotto', 'prezzi allingrosso', 'per boutique'],
    ][$lang] ?? [];
}

/** "Lacoste wholesale, Gucci wholesale, …" in the visitor's language; '' when nothing is stocked. */
function vestra_seo_brand_keywords(string $lang, int $max = 12): string {
    $w = vestra_seo_wholesale_word($lang);
    return implode(', ', array_map(fn($b) => $b.' '.$w, vestra_seo_brands($max)));
}

/** One brand crossed with every B2B term: the keyword line for that brand's landing page. */
function vestra_seo_brand_b2b_keywords(string $brand, string $lang): string {
    $out = [];
    foreach (vestra_seo_b2b_terms($lang) as $term) $out[] = $brand.' '.$term;
    /* English too, on every language: a Greek or Polish buyer sourcing internationally
       searches in English as often as in their own language, and we have no Greek or
       Polish page to send them to. */
    if ($lang !== 'en') foreach (vestra_seo_b2b_terms('en') as $term) $out[] = $brand.' '.$term;
    return implode(', ', $out);
}

function vestra_read_json(string $name): array {
    $f = vestra_data_dir().'/'.$name;
    if (!is_readable($f)) return [];
    return json_decode((string)file_get_contents($f), true) ?: [];
}
function vestra_write_json(string $name, array $data): void {
    $f = vestra_data_dir().'/'.$name;
    file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* Sourcing requests board (buyers post what they need; sellers make offers). Demo seed. */
function vestra_requests(){
  return [
    ['id'=>'r1042','title'=>'Lacoste polos — mixed sizes, EEA stock','cat'=>'Polos','qty'=>'300 pc','target'=>'€24 / pc','country'=>'DE','offers'=>3,'age'=>'2h'],
    ['id'=>'r1041','title'=>'Ralph Lauren oxford shirts','cat'=>'Shirts','qty'=>'150 pc','target'=>'€28 / pc','country'=>'FR','offers'=>5,'age'=>'5h'],
    ['id'=>'r1039','title'=>'Blank cotton tees 180gsm, white','cat'=>'Basics','qty'=>'2,000 pc','target'=>'€2.60 / pc','country'=>'IT','offers'=>1,'age'=>'1d'],
    ['id'=>'r1038','title'=>'Branded socks, bulk clearance','cat'=>'Basics','qty'=>'1,000 pack','target'=>'best offer','country'=>'ES','offers'=>0,'age'=>'1d'],
  ];
}

/* The size-mix string is stored as free text on the listing (e.g.
   "S×1 · M×2 · L×3 · XL×3 · XXL×1 · 10/pack"), so the trailing unit word would
   otherwise appear in one fixed language on every localised page. Translate just
   that token at render time and leave the numbers and size codes alone -- they are
   the same in every market. Accepts the Turkish and English spellings that already
   exist in the data so old listings localise without a migration. */
function vestra_sizes_label(string $sizes): string {
    if ($sizes === '') return '';
    return preg_replace_callback(
        '~(\d+)\s*/\s*(pack|paket|packs|seri|series|serie)\b~iu',
        function ($m) {
            $isSeries = stripos($m[2], 'ser') === 0;
            return $m[1].'/'.t($isSeries ? 'series' : 'pack');
        },
        $sizes
    ) ?? $sizes;
}

/* ── Tek parca satisi icin secilebilir beden / renk ────────────────────────
   'sizes' alani bir liste DEGIL, paket kuralini anlatan bir cumle:
   "S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10", "Cartons of 10 · sizes S–XXL",
   "Lots of 8 · sizes 3–8 · min 80 pc". Toptan sayfasinda dogru olan bu cumle,
   dropship'te yanlis: orada ortak kendi musterisi icin TEK parca aliyor, karton
   dagilimini degil bedeni seciyor -- "S×1 · M×3 · ..." yazan bir alan ona
   secemeyecegi bir sey gosteriyor.
   Asagidaki ayristirici o cumleden yalnizca beden ADLARINI cikariyor. Cikaramazsa
   BOS donuyor ve cagiran taraf serbest metne dusuyor: uydurulmus bir liste,
   olmayan bedeni varmis gibi gostermek olurdu. */

const VESTRA_SIZE_LADDER = ['XXS','XS','S','M','L','XL','XXL','XXXL','XXXXL'];

/* "2xl" → "XXL", "3XL" → "XXXL", "s" → "S"; sayisal bedenler oldugu gibi kalir. */
function vestra_size_norm(string $tok): string {
    $t = strtoupper(trim($tok));
    if ($t === '') return '';
    if (preg_match('~^([2-5])X{1,2}L$~', $t, $m)) $t = str_repeat('X', (int)$m[1]).'L';
    if (in_array($t, VESTRA_SIZE_LADDER, true)) return $t;
    if (preg_match('~^\d{1,3}([.,]5)?$~', $t)) return str_replace(',', '.', $t);
    return '';
}

function vestra_size_options(array $p): array {
    $s = trim((string)($p['sizes'] ?? ''));
    if ($s === '') return [];
    /* "os" disindakiler govde eslesmesi: /u kipinde \b harfli ekleri de kelime
       sayiyor, "Einheitsgröße" sonuna sinir koymak eslesmeyi kacirtiyordu. */
    if (preg_match('~(one\s?size|einheitsgr|tek\s?beden|taille\s?unique|\bos\b)~iu', $s)) return ['One size'];

    $push = function (array &$out, string $tok): void {
        $n = vestra_size_norm($tok);
        if ($n !== '' && !in_array($n, $out, true)) $out[] = $n;
    };

    /* 1) Acik dagilim -- "S×1 · M×3 · XL×2". Carpimdan ONCEKI ad bedendir; sonraki
          sayi karton adedi ve tek parca alan ortagi ilgilendirmiyor. */
    $out = [];
    if (preg_match_all('~([A-Za-z0-9]{1,5})\s*[×xX*]\s*\d+~u', $s, $m)) {
        foreach ($m[1] as $tok) $push($out, $tok);
        if (count($out) > 1) return $out;
    }

    /* 2) Aralik -- "sizes S–XXL", "sizes 3–8". Merdiveni iki ucu arasinda ac.
          Tireli her ikili aday: "T-shirt sizes S-XXL" gibi bir metinde ilk tire
          bedene ait degil, o yuzden ilk COZULEN ikiliye kadar bakiliyor. */
    if (preg_match_all('~([A-Za-z]{1,5}|\d{1,3})\s*[-–—]\s*([A-Za-z]{1,5}|\d{1,3})~u', $s, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $a = vestra_size_norm($m[1]);
            $b = vestra_size_norm($m[2]);
            if ($a === '' || $b === '') continue;
            $ia = array_search($a, VESTRA_SIZE_LADDER, true);
            $ib = array_search($b, VESTRA_SIZE_LADDER, true);
            if ($ia !== false && $ib !== false && $ia <= $ib) {
                return array_slice(VESTRA_SIZE_LADDER, $ia, $ib - $ia + 1);
            }
            /* Sayisal aralik (ayakkabi/cocuk). Ust sinir, "1–100" gibi bir yazim
               hatasinin acilir listeyi doldurmasini engelliyor. */
            if (is_numeric($a) && is_numeric($b) && $a <= $b && ($b - $a) <= 23) {
                $r = [];
                for ($v = (float)$a; $v <= (float)$b; $v++) $r[] = (string)(int)$v;
                if (count($r) > 1) return $r;
            }
        }
    }

    /* 3) Duz liste -- "S · M · L · XL" ya da "S, M, L". Paketleme kelimeleri
          ("Cartons of 10") beden gibi gorunmedigi icin kendiliginden eleniyor. */
    $out = [];
    foreach (preg_split('~[·,;/|]+~u', $s) ?: [] as $part) {
        foreach (preg_split('~\s+~u', trim($part)) ?: [] as $tok) $push($out, $tok);
    }
    /* Tek bir sayi ("Cartons of 10") beden degil, adet. Iki ve uzeri gercek bir liste. */
    return count($out) > 1 ? $out : [];
}

/* Ilanin renkleri. 'colors' bos birakilmis ama renk bazli varyantlar girilmisse
   renk oradan da okunabiliyor -- ayni bilgi iki alanda duruyor ve alicinin
   hangisinin doldurulduguyla isi yok. */
function vestra_colour_options(array $p): array {
    $c = [];
    foreach ((array)($p['colors'] ?? []) as $x) {
        $x = trim((string)$x);
        if ($x !== '' && !in_array($x, $c, true)) $c[] = $x;
    }
    if (!$c && !empty($p['variants']) && is_array($p['variants'])) {
        foreach ($p['variants'] as $v) {
            $x = trim((string)($v['color'] ?? ''));
            if ($x !== '' && !in_array($x, $c, true)) $c[] = $x;
        }
    }
    return $c;
}
