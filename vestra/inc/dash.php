<?php
/** VESTRA — dashboard layout helpers (premium sidebar panel). */
require_once __DIR__.'/messages.php';
/* Light ("premium white") theme for the whole buyer/seller dashboard — mirrors the
   admin panel. Emitted once per page. Only overrides the palette INSIDE .dashwrap so
   the shared dark site header keeps its light-on-dark text; body + footer are repainted
   explicitly because these are dashboard-only entry points (buyer.php / seller.php). */
function dash_theme_css(): string {
  return '<style>
  body{background:#f4f2ee}
  .dashwrap{--bg:#f4f2ee;--bg2:#ffffff;--bg3:#faf8f4;--ink:#211d17;--mut:#6f695e;--acc:#a97f2c;--line:#e6e0d5;--ok:#1f9d63;--bad:#c0392b;color:var(--ink)}
  .dashwrap h1,.dashwrap h2,.dashwrap h3{color:var(--ink)}
  .dashwrap .panelcard,.dashwrap .statcard{box-shadow:0 1px 3px rgba(60,50,30,.05)}
  .dashwrap input,.dashwrap select,.dashwrap textarea{background:#fff;color:var(--ink)}
  .dashwrap .status.open{background:rgba(169,127,44,.13);color:#8a6420}
  .dashwrap .status.offers{background:rgba(31,157,99,.14);color:#1f9d63}
  .dashwrap .modechip{border:1px solid var(--line)}
  footer{background:#14110c;border-top:0;color:#b8b2a4;margin-top:0}
  footer a{color:#d8bd86}
  </style>';
}
function dash_open($role,$section,$title,$subtitle=''){
  $unreadN = vestra_msg_unread_count($_SESSION['uid'] ?? '');
  echo dash_theme_css();
  $nav = $role==='seller' ? [
      ['overview',t('Overview'),'/seller'],
      ['add','＋ '.t('Add product'),'/seller?tab=add'],
      ['listings',t('My listings'),'/seller?tab=listings'],
      ['prices',t('Prices & MOQ'),'/seller?tab=prices'],
      ['orders',t('Orders'),'/seller?tab=orders'],
      ['offers',t('Offers received'),'/seller?tab=offers'],
      ['messages',t('Messages'),'/seller?tab=messages'],
      ['find','🎯 '.t('Find customers'),'/seller?tab=find'],
      ['kyc',t('Verification'),'/seller?tab=kyc'],
      ['profile',t('My profile'),'/seller?tab=profile'],
    ] : [
      ['overview',t('Overview'),'/buyer'],
      ['orders',t('My orders'),'/buyer?tab=orders'],
      ['requests',t('My requests'),'/buyer?tab=requests'],
      ['offers',t('My offers'),'/buyer?tab=offers'],
      ['messages',t('Messages'),'/buyer?tab=messages'],
      ['kyc',t('Verification'),'/buyer?tab=kyc'],
      ['profile',t('My profile'),'/buyer?tab=profile'],
    ];
  echo '<div class="wrap dashwrap"><div class="dashtop">';
  echo '<div><div class="crumbs"><a href="/">'.t('Home').'</a> · '.($role==='seller'?t('Seller'):t('Buyer')).' '.t('panel').'</div>';
  echo '<h1>'.htmlspecialchars($title).'</h1>';
  if($subtitle) echo '<p class="hint" style="margin:2px 0 0">'.htmlspecialchars($subtitle).'</p>';
  echo '</div><span class="rolepill">'.($role==='seller'?'🏷️ '.t('Seller workspace'):'🛍️ '.t('Buyer workspace')).'</span>';
  echo '</div><div class="dashlayout"><aside class="dashside">';
  foreach($nav as $n){
    $label = htmlspecialchars($n[1]);
    if($n[0]==='messages' && $unreadN>0) $label .= ' <span class="navdot">'.$unreadN.'</span>';
    echo '<a href="'.$n[2].'"'.($n[0]===$section?' class="on"':'').'>'.$label.'</a>';
  }
  echo '<div class="dashlang"><span>'.t('Language').'</span>'.vlang_switcher('dashsw').'</div>';
  echo '<a class="signout" href="/login?signout=1">'.t('Sign out').'</a>';
  echo '</aside><main class="dashmain">';
}
function dash_close(){ echo '</main></div></div>'; }

function stat_cards($cards){
  echo '<div class="statgrid">';
  foreach($cards as $c){ echo '<div class="statcard"><div class="sv">'.$c[0].'</div><div class="sl">'.htmlspecialchars($c[1]).'</div></div>'; }
  echo '</div>';
}
function dash_empty($msg){ echo '<div class="empty">'.htmlspecialchars($msg).'</div>'; }
