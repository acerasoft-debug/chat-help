<?php
/** VESTRA — dashboard layout helpers (premium sidebar panel). */
function dash_open($role,$section,$title,$subtitle=''){
  $nav = $role==='seller' ? [
      ['overview','Overview','seller.php'],
      ['add','＋ Add product','seller.php?tab=add'],
      ['listings','My listings','seller.php?tab=listings'],
      ['orders','Orders','seller.php?tab=orders'],
      ['offers','Offers received','seller.php?tab=offers'],
      ['kyc','Verification','seller.php?tab=kyc'],
    ] : [
      ['overview','Overview','buyer.php'],
      ['orders','My orders','buyer.php?tab=orders'],
      ['requests','My requests','buyer.php?tab=requests'],
      ['offers','My offers','buyer.php?tab=offers'],
      ['kyc','Verification','buyer.php?tab=kyc'],
    ];
  echo '<div class="wrap"><div class="dashtop">';
  echo '<div><div class="crumbs"><a href="/">Home</a> · '.($role==='seller'?'Seller':'Buyer').' panel</div>';
  echo '<h1>'.htmlspecialchars($title).'</h1>';
  if($subtitle) echo '<p class="hint" style="margin:2px 0 0">'.htmlspecialchars($subtitle).'</p>';
  echo '</div><span class="rolepill">'.($role==='seller'?'🏷️ Seller workspace':'🛍️ Buyer workspace').'</span>';
  echo '</div><div class="dashlayout"><aside class="dashside">';
  foreach($nav as $n){ echo '<a href="'.$n[2].'"'.($n[0]===$section?' class="on"':'').'>'.htmlspecialchars($n[1]).'</a>'; }
  echo '<a class="signout" href="/?demo_signout=1">Sign out</a>';
  echo '</aside><main class="dashmain">';
}
function dash_close(){ echo '</main></div></div>'; }

function stat_cards($cards){
  echo '<div class="statgrid">';
  foreach($cards as $c){ echo '<div class="statcard"><div class="sv">'.$c[0].'</div><div class="sl">'.htmlspecialchars($c[1]).'</div></div>'; }
  echo '</div>';
}
function dash_empty($msg){ echo '<div class="empty">'.htmlspecialchars($msg).'</div>'; }
