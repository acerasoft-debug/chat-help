<?php
/** VESTRA — dashboard layout helpers (premium sidebar panel). */
function dash_open($role,$section,$title,$subtitle=''){
  $nav = $role==='seller' ? [
      ['overview',t('Overview'),'/seller'],
      ['add','＋ '.t('Add product'),'/seller?tab=add'],
      ['listings',t('My listings'),'/seller?tab=listings'],
      ['orders',t('Orders'),'/seller?tab=orders'],
      ['offers',t('Offers received'),'/seller?tab=offers'],
      ['kyc',t('Verification'),'/seller?tab=kyc'],
    ] : [
      ['overview',t('Overview'),'/buyer'],
      ['orders',t('My orders'),'/buyer?tab=orders'],
      ['requests',t('My requests'),'/buyer?tab=requests'],
      ['offers',t('My offers'),'/buyer?tab=offers'],
      ['kyc',t('Verification'),'/buyer?tab=kyc'],
    ];
  echo '<div class="wrap"><div class="dashtop">';
  echo '<div><div class="crumbs"><a href="/">'.t('Home').'</a> · '.($role==='seller'?t('Seller'):t('Buyer')).' '.t('panel').'</div>';
  echo '<h1>'.htmlspecialchars($title).'</h1>';
  if($subtitle) echo '<p class="hint" style="margin:2px 0 0">'.htmlspecialchars($subtitle).'</p>';
  echo '</div><span class="rolepill">'.($role==='seller'?'🏷️ '.t('Seller workspace'):'🛍️ '.t('Buyer workspace')).'</span>';
  echo '</div><div class="dashlayout"><aside class="dashside">';
  foreach($nav as $n){ echo '<a href="'.$n[2].'"'.($n[0]===$section?' class="on"':'').'>'.htmlspecialchars($n[1]).'</a>'; }
  echo '<a class="signout" href="/?demo_signout=1">'.t('Sign out').'</a>';
  echo '</aside><main class="dashmain">';
}
function dash_close(){ echo '</main></div></div>'; }

function stat_cards($cards){
  echo '<div class="statgrid">';
  foreach($cards as $c){ echo '<div class="statcard"><div class="sv">'.$c[0].'</div><div class="sl">'.htmlspecialchars($c[1]).'</div></div>'; }
  echo '</div>';
}
function dash_empty($msg){ echo '<div class="empty">'.htmlspecialchars($msg).'</div>'; }
