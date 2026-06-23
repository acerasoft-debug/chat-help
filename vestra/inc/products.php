<?php
/**
 * VESTRA — sample product catalog (single source of truth).
 * B2B wholesale: MOQ (min order) + tiered pricing (more qty → lower unit price).
 * Demo data — replace with real seller listings in Phase 1.
 */
function vestra_products(){
  return [
    [
      'id'=>'lac-pique-polo','brand'=>'Lacoste','name'=>'Classic Piqué Polo',
      'cat'=>'Polos','sku'=>'LAC-PP-001','moq'=>12,'unit'=>'pc',
      'desc'=>'Iconic cotton piqué polo. Assorted sizes (S–XXL) and colours per carton.',
      'seller'=>'Maison Textile SARL','origin'=>'EEA stock · proof on request','verified'=>true,
      'accent'=>'#1b5e3a',
      'tiers'=>[['min'=>12,'price'=>34.00],['min'=>60,'price'=>29.50],['min'=>180,'price'=>25.00]],
    ],
    [
      'id'=>'rl-oxford-shirt','brand'=>'Ralph Lauren','name'=>'Custom Fit Oxford Shirt',
      'cat'=>'Shirts','sku'=>'RL-OX-014','moq'=>10,'unit'=>'pc',
      'desc'=>'Cotton oxford shirt, custom fit. Assorted sizes and colours.',
      'seller'=>'Atlantic Wholesale GmbH','origin'=>'EEA stock','verified'=>true,
      'accent'=>'#0f2f5c',
      'tiers'=>[['min'=>10,'price'=>39.00],['min'=>50,'price'=>34.00],['min'=>150,'price'=>29.00]],
    ],
    [
      'id'=>'amiri-core-tee','brand'=>'Amiri','name'=>'Core Logo Tee',
      'cat'=>'T-Shirts','sku'=>'AMI-CT-007','moq'=>6,'unit'=>'pc',
      'desc'=>'Premium cotton tee. Limited allocation. Authenticity verified on delivery.',
      'seller'=>'Lux Source Srl','origin'=>'Authorised allocation','verified'=>true,
      'accent'=>'#3a0f12',
      'tiers'=>[['min'=>6,'price'=>120.00],['min'=>24,'price'=>105.00],['min'=>60,'price'=>92.00]],
    ],
    [
      'id'=>'basic-crew-tee','brand'=>'VESTRA Essentials','name'=>'Crew Neck Tee — blank',
      'cat'=>'Basics','sku'=>'VE-CT-100','moq'=>50,'unit'=>'pc',
      'desc'=>'180gsm combed cotton blank tee. Bulk packs, all sizes & colours.',
      'seller'=>'VESTRA Essentials','origin'=>'White-label','verified'=>true,
      'accent'=>'#44454e',
      'tiers'=>[['min'=>50,'price'=>4.20],['min'=>300,'price'=>3.40],['min'=>1000,'price'=>2.80]],
    ],
    [
      'id'=>'cotton-socks','brand'=>'VESTRA Essentials','name'=>'Cotton Socks — 12 pack',
      'cat'=>'Basics','sku'=>'VE-SK-220','moq'=>100,'unit'=>'pack',
      'desc'=>'Combed cotton crew socks, 12 pairs per pack. Mixed sizes.',
      'seller'=>'VESTRA Essentials','origin'=>'White-label','verified'=>true,
      'accent'=>'#283b49',
      'tiers'=>[['min'=>100,'price'=>9.50],['min'=>500,'price'=>7.90],['min'=>2000,'price'=>6.50]],
    ],
    [
      'id'=>'boxer-briefs','brand'=>'VESTRA Essentials','name'=>'Boxer Briefs — 3 pack',
      'cat'=>'Underwear','sku'=>'VE-UW-330','moq'=>100,'unit'=>'pack',
      'desc'=>'Stretch cotton boxer briefs, 3 per pack. Assorted sizes & colours.',
      'seller'=>'VESTRA Essentials','origin'=>'White-label','verified'=>true,
      'accent'=>'#392b4a',
      'tiers'=>[['min'=>100,'price'=>7.80],['min'=>500,'price'=>6.40],['min'=>2000,'price'=>5.30]],
    ],
  ];
}
function vestra_find($id){ foreach(vestra_products() as $p){ if($p['id']===$id) return $p; } return null; }
function vestra_cats(){ $c=[]; foreach(vestra_products() as $p){ $c[$p['cat']]=1; } return array_keys($c); }
/* unit price for qty = highest tier whose min <= qty */
function vestra_unit_price($p,$qty){ $price=$p['tiers'][0]['price']; foreach($p['tiers'] as $t){ if($qty>=$t['min']) $price=(float)$t['price']; } return $price; }
function vestra_from_price($p){ $m=null; foreach($p['tiers'] as $t){ $m=($m===null)?$t['price']:min($m,$t['price']); } return $m; }
function eur($n){ return '€'.number_format((float)$n,2,'.',','); }
