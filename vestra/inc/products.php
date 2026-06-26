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
      'group'=>true,'group_seed'=>96,'group_seed_n'=>5, // group-buy: pool to 180 pc → €25/pc
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
      'group'=>true,'group_seed'=>640,'group_seed_n'=>11, // group-buy: pool to 1000 pc → €2.80/pc
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
/* Unlocked unit price once the target is met (seller override, else the lowest tier price). */
function vestra_group_price($p){
  if(!empty($p['group_price'])) return (float)$p['group_price'];
  return (float)vestra_from_price($p);
}
function vestra_group_deadline($p){
  if(!empty($p['group_deadline'])) return $p['group_deadline'];
  $start=$p['group_started'] ?? date('c');
  return date('c', strtotime($start.' +'.VESTRA_GROUP_DEFAULT_DAYS.' days'));
}
/* Buyer commitments for one pool (newest first), read from data/groups.csv. */
function vestra_group_commits($poolId){
  $rows=vestra_read_csv('groups.csv');
  return array_values(array_filter($rows, function($r) use ($poolId){ return ($r['pool_id']??'')===$poolId; }));
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

/* Sourcing requests board (buyers post what they need; sellers make offers). Demo seed. */
function vestra_requests(){
  return [
    ['id'=>'r1042','title'=>'Lacoste polos — mixed sizes, EEA stock','cat'=>'Polos','qty'=>'300 pc','target'=>'€24 / pc','country'=>'DE','offers'=>3,'age'=>'2h'],
    ['id'=>'r1041','title'=>'Ralph Lauren oxford shirts','cat'=>'Shirts','qty'=>'150 pc','target'=>'€28 / pc','country'=>'FR','offers'=>5,'age'=>'5h'],
    ['id'=>'r1039','title'=>'Blank cotton tees 180gsm, white','cat'=>'Basics','qty'=>'2,000 pc','target'=>'€2.60 / pc','country'=>'IT','offers'=>1,'age'=>'1d'],
    ['id'=>'r1038','title'=>'Branded socks, bulk clearance','cat'=>'Basics','qty'=>'1,000 pack','target'=>'best offer','country'=>'ES','offers'=>0,'age'=>'1d'],
  ];
}
