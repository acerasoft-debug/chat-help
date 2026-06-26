<?php
/**
 * VESTRA — sample catalog (single source of truth).
 * Pricing modes:  'fixed' (tiered), 'sale' (discounted vs list), 'offer' (make-an-offer / negotiate).
 * B2B: MOQ (min order) + tiered pricing (more qty -> lower unit price). Demo data.
 */
/* Platform commission — single source of truth (used by order.php + cart.php). */
if(!defined('VESTRA_FEE_SELLER')) define('VESTRA_FEE_SELLER', 0.07); // 7% from seller's payout
if(!defined('VESTRA_FEE_BUYER'))  define('VESTRA_FEE_BUYER',  0.02); // 2% buyer-protection fee
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/notify.php';
if(!defined('VESTRA_TERMS_VERSION')) define('VESTRA_TERMS_VERSION','2026-06-26'); // legal acceptance version

function vestra_demo_products(){
  return [
    [
      'id'=>'lac-pique-polo','brand'=>'Lacoste','name'=>'Classic Piqué Polo','mode'=>'fixed',
      'cat'=>'Polos','sku'=>'LAC-PP-001','moq'=>12,'unit'=>'pc',
      'desc'=>'Iconic cotton piqué polo. Assorted sizes (S–XXL) and colours per carton.',
      'seller'=>'Maison Textile SARL','origin'=>'EEA stock · proof on request','verified'=>true,'accent'=>'#1b5e3a',
      'tiers'=>[['min'=>12,'price'=>34.00],['min'=>60,'price'=>29.50],['min'=>180,'price'=>25.00]],
    ],
    [
      'id'=>'rl-oxford-shirt','brand'=>'Ralph Lauren','name'=>'Custom Fit Oxford Shirt','mode'=>'sale','list'=>49.00,
      'cat'=>'Shirts','sku'=>'RL-OX-014','moq'=>10,'unit'=>'pc',
      'desc'=>'Cotton oxford shirt, custom fit. End-of-season clearance — limited stock.',
      'seller'=>'Atlantic Wholesale GmbH','origin'=>'EEA stock','verified'=>true,'accent'=>'#0f2f5c',
      'tiers'=>[['min'=>10,'price'=>39.00],['min'=>50,'price'=>34.00],['min'=>150,'price'=>29.00]],
    ],
    [
      'id'=>'amiri-core-tee','brand'=>'Amiri','name'=>'Core Logo Tee','mode'=>'offer',
      'guide'=>'Indicative €90–110 / pc · limited allocation',
      'cat'=>'T-Shirts','sku'=>'AMI-CT-007','moq'=>6,'unit'=>'pc',
      'desc'=>'Premium cotton tee. Limited allocation — open to offers. Authenticity verified on delivery.',
      'seller'=>'Lux Source Srl','origin'=>'Authorised allocation','verified'=>true,'accent'=>'#3a0f12',
      'tiers'=>[['min'=>6,'price'=>110.00],['min'=>24,'price'=>98.00],['min'=>60,'price'=>90.00]],
    ],
    [
      'id'=>'basic-crew-tee','brand'=>'VESTRA Essentials','name'=>'Crew Neck Tee — blank','mode'=>'fixed',
      'cat'=>'Basics','sku'=>'VE-CT-100','moq'=>50,'unit'=>'pc',
      'desc'=>'180gsm combed cotton blank tee. Bulk packs, all sizes & colours.',
      'seller'=>'VESTRA Essentials','origin'=>'White-label','verified'=>true,'accent'=>'#44454e',
      'tiers'=>[['min'=>50,'price'=>4.20],['min'=>300,'price'=>3.40],['min'=>1000,'price'=>2.80]],
    ],
    [
      'id'=>'cotton-socks','brand'=>'VESTRA Essentials','name'=>'Cotton Socks — 12 pack','mode'=>'sale','list'=>11.90,
      'cat'=>'Basics','sku'=>'VE-SK-220','moq'=>100,'unit'=>'pack',
      'desc'=>'Combed cotton crew socks, 12 pairs per pack. Clearance pricing.',
      'seller'=>'VESTRA Essentials','origin'=>'White-label','verified'=>true,'accent'=>'#283b49',
      'tiers'=>[['min'=>100,'price'=>9.50],['min'=>500,'price'=>7.90],['min'=>2000,'price'=>6.50]],
    ],
    [
      'id'=>'boxer-briefs','brand'=>'VESTRA Essentials','name'=>'Boxer Briefs — 3 pack','mode'=>'fixed',
      'cat'=>'Underwear','sku'=>'VE-UW-330','moq'=>100,'unit'=>'pack',
      'desc'=>'Stretch cotton boxer briefs, 3 per pack. Assorted sizes & colours.',
      'seller'=>'VESTRA Essentials','origin'=>'White-label','verified'=>true,'accent'=>'#392b4a',
      'tiers'=>[['min'=>100,'price'=>7.80],['min'=>500,'price'=>6.40],['min'=>2000,'price'=>5.30]],
    ],
  ];
}
/* seller-added listings (saved by the seller panel) merged into the live catalog */
function vestra_data_dir(){ return dirname(__DIR__).'/data'; }
function vestra_listings(){ $f=vestra_data_dir().'/listings.json'; if(is_readable($f)){ $d=json_decode((string)file_get_contents($f),true); if(is_array($d)) return $d; } return []; }
function vestra_read_csv($name){ $f=vestra_data_dir().'/'.$name; $rows=[]; if(is_readable($f)&&($h=@fopen($f,'r'))){ $head=fgetcsv($h); while(($r=fgetcsv($h))!==false){ if($head){ $n=count($head); $r=array_slice(array_pad($r,$n,''),0,$n); $rows[]=array_combine($head,$r);} } fclose($h);} return array_reverse($rows); }
function vestra_products(){ return array_merge(vestra_demo_products(), vestra_listings()); }
function vestra_find($id){ foreach(vestra_products() as $p){ if($p['id']===$id) return $p; } return null; }
function vestra_cats(){ $c=[]; foreach(vestra_products() as $p){ $c[$p['cat']]=1; } return array_keys($c); }
/* Full Fashion & Accessories taxonomy (grouped) — used by the seller's product form. */
function vestra_all_cats(){
  return [
    'Tops'               => ['T-Shirts','Polos','Shirts','Blouses','Sweaters & Knitwear','Cardigans','Hoodies & Sweatshirts','Tank Tops'],
    'Bottoms'            => ['Trousers & Chinos','Jeans','Shorts','Skirts','Leggings'],
    'Outerwear'          => ['Jackets','Coats','Blazers','Vests & Gilets'],
    'Dresses & Suits'    => ['Dresses','Suits','Jumpsuits & Playsuits'],
    'Activewear & Swim'  => ['Activewear','Sportswear','Tracksuits','Swimwear'],
    'Underwear & Socks'  => ['Underwear','Lingerie','Socks & Hosiery','Sleepwear','Loungewear','Basics'],
    'Footwear'           => ['Sneakers','Boots','Sandals','Heels','Flats','Loafers','Slippers'],
    'Bags & Luggage'     => ['Handbags','Backpacks','Tote Bags','Wallets & Purses','Travel & Luggage'],
    'Accessories'        => ['Belts','Hats & Caps','Scarves & Shawls','Gloves','Sunglasses','Eyewear','Ties','Hair Accessories','Phone Cases'],
    'Jewelry & Watches'  => ['Jewelry','Watches'],
    'Kids & Baby'        => ['Kidswear','Babywear'],
  ];
}
function vestra_unit_price($p,$qty){ $price=$p['tiers'][0]['price']; foreach($p['tiers'] as $t){ if($qty>=$t['min']) $price=(float)$t['price']; } return $price; }
function vestra_from_price($p){ $m=null; foreach($p['tiers'] as $t){ $m=($m===null)?$t['price']:min($m,$t['price']); } return $m; }
function vestra_discount($p){ if(($p['mode']??'')!=='sale'||empty($p['list'])) return 0; return (int)round(100*($p['list']-vestra_from_price($p))/$p['list']); }
function eur($n){ return '€'.number_format((float)$n,2,'.',','); }

/* Sourcing requests board (buyers post what they need; sellers make offers). Demo seed. */
function vestra_requests(){
  return [
    ['id'=>'r1042','title'=>'Lacoste polos — mixed sizes, EEA stock','cat'=>'Polos','qty'=>'300 pc','target'=>'€24 / pc','country'=>'DE','offers'=>3,'age'=>'2h'],
    ['id'=>'r1041','title'=>'Ralph Lauren oxford shirts','cat'=>'Shirts','qty'=>'150 pc','target'=>'€28 / pc','country'=>'FR','offers'=>5,'age'=>'5h'],
    ['id'=>'r1039','title'=>'Blank cotton tees 180gsm, white','cat'=>'Basics','qty'=>'2,000 pc','target'=>'€2.60 / pc','country'=>'IT','offers'=>1,'age'=>'1d'],
    ['id'=>'r1038','title'=>'Branded socks, bulk clearance','cat'=>'Basics','qty'=>'1,000 pack','target'=>'best offer','country'=>'ES','offers'=>0,'age'=>'1d'],
  ];
}
