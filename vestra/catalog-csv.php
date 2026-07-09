<?php
/** VESTRA — catalog export as CSV (opens in Excel). */
require __DIR__.'/inc/products.php';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="vestra-catalog-'.date('Y-m-d').'.csv"');
$out=fopen('php://output','w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
fputcsv($out,['SKU','Brand','Product','Category','Unit','MOQ',
  'Tier1 Qty','Tier1 Price (EUR)','Tier2 Qty','Tier2 Price (EUR)','Tier3 Qty','Tier3 Price (EUR)',
  'Seller','Origin'],',','"','\\');
foreach(vestra_products() as $p){
  $t=$p['tiers'];
  fputcsv($out,[
    $p['sku']??'',$p['brand']??'',$p['name']??'',$p['cat']??'',$p['unit']??'pc',$p['moq']??'',
    $t[0]['min']??'',$t[0]['price']??'', $t[1]['min']??'',$t[1]['price']??'', $t[2]['min']??'',$t[2]['price']??'',
    empty($p['hide_seller'])?($p['seller']??''):'via VESTRA',$p['origin']??'',
  ],',','"','\\');
}
fclose($out);
