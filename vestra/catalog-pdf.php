<?php
/** VESTRA — print-ready catalog (use the browser's "Save as PDF"). Auto-opens print dialog. */
require __DIR__.'/inc/products.php';
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>VESTRA — Catalog</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}
  body{font-family:'Inter',Arial,sans-serif;color:#15151a;margin:0;padding:36px 40px;font-size:12px}
  .top{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #c9a86a;padding-bottom:14px;margin-bottom:18px}
  .brand{font-family:'Playfair Display',serif;font-size:30px;letter-spacing:2px}
  .brand b{color:#c9a86a}
  .sub{color:#666;font-size:12px}
  .meta{text-align:right;color:#666;font-size:11px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:9px 8px;border-bottom:1px solid #e6e3da;text-align:left;vertical-align:top}
  th{background:#faf8f3;font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#888}
  .brandcell{font-weight:600}
  .sku{color:#999;font-size:10px}
  .price{white-space:nowrap}
  .price b{color:#15151a}
  .foot{margin-top:22px;color:#888;font-size:10px;border-top:1px solid #e6e3da;padding-top:10px}
  .bar{position:fixed;top:0;left:0;right:0;background:#0e0e11;color:#f4f1ea;padding:10px 16px;text-align:center;font-family:'Inter',sans-serif}
  .bar a{color:#c9a86a;cursor:pointer;text-decoration:underline}
  @media print{.bar{display:none}body{padding:0}}
</style>
</head><body>
<div class="bar">Use <b>Print → Save as PDF</b> to download. <a onclick="window.print()">Print / Save as PDF</a> · <a href="/shop">Back to catalog</a></div>
<div style="height:14px"></div>

<div class="top">
  <div>
    <div class="brand">VESTRA<b>.</b></div>
    <div class="sub">Verified B2B fashion wholesale — price list</div>
  </div>
  <div class="meta">
    Generated <?=date('d M Y')?><br>
    Prices EUR, ex-fees · MOQ = minimum order<br>
    Wholesale terms — verified buyers only
  </div>
</div>

<table>
  <thead><tr>
    <th>Product</th><th>Category</th><th>Unit</th><th>MOQ</th>
    <th>Tiered price (qty → unit)</th><th>Seller</th>
  </tr></thead>
  <tbody>
  <?php foreach(vestra_products() as $p): ?>
    <tr>
      <td class="brandcell"><?=htmlspecialchars($p['brand'])?> — <?=htmlspecialchars($p['name'])?>
        <div class="sku">SKU <?=htmlspecialchars($p['sku'])?></div></td>
      <td><?=htmlspecialchars($p['cat'])?></td>
      <td><?=htmlspecialchars($p['unit'])?></td>
      <td><?=$p['moq']?></td>
      <td class="price"><?php
        $parts=[]; foreach($p['tiers'] as $t){ $parts[]=$t['min'].'+ → <b>'.eur($t['price']).'</b>'; }
        echo implode('&nbsp;&nbsp;·&nbsp;&nbsp;',$parts); ?></td>
      <td><?=htmlspecialchars($p['seller'])?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="foot">
  VESTRA — acerasoft LLC. Prices indicative, exclusive of platform/escrow fees, taxes and shipping.
  Branded goods sold by verified sellers; authenticity &amp; right-to-sell warranted. Subject to availability.
</div>

<script>setTimeout(function(){ try{ window.print(); }catch(e){} }, 700);</script>
</body></html>
