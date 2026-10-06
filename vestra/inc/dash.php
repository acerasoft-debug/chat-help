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
  /* Her sekmenin bir SIMGESI var (6 Eki 2026): on satirlik duz bir metin listesi
     taranmiyordu; simge, sekmeyi okumadan once tanitir. Cizgi simgeler (stroke),
     emoji degil: emoji platforma gore baska cizilir ve koyu/acik temada renk tutmaz.
     Eski "＋" ve "🎯" on ekleri bu yuzden kalkti. */
  $ic = [
    'overview' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v4.5l3 2"/>',
    'add'      => '<circle cx="12" cy="12" r="8.5"/><path d="M12 8v8M8 12h8"/>',
    'listings' => '<rect x="4" y="5" width="16" height="14" rx="2.5"/><path d="M8 10h8M8 14h5"/>',
    'prices'   => '<path d="M4 7h16M4 12h10M4 17h7"/><circle cx="18" cy="16" r="2.5"/>',
    'orders'   => '<path d="M6 4h12l1 16H5L6 4z"/><path d="M9.5 8a2.5 2.5 0 0 0 5 0"/>',
    'offers'   => '<path d="M4 12l8-8 8 8-8 8-8-8z"/><path d="M12 9v6"/>',
    'messages' => '<path d="M4 5h16v12H8l-4 4V5z"/>',
    'find'     => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.3-4.3"/>',
    'kyc'      => '<path d="M12 3l7 3v5c0 4.6-3 7.7-7 9-4-1.3-7-4.4-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
    'profile'  => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c1.2-3.6 4-5.2 7-5.2s5.8 1.6 7 5.2"/>',
    'requests' => '<path d="M5 4h11l3 3v13H5z"/><path d="M8.5 11h7M8.5 15h4.5"/>',
  ];
  $nav = $role==='seller' ? [
      ['overview',t('Overview'),'/seller'],
      ['add',t('Add product'),'/seller?tab=add'],
      ['listings',t('My listings'),'/seller?tab=listings'],
      ['prices',t('Prices & MOQ'),'/seller?tab=prices'],
      ['orders',t('Orders'),'/seller?tab=orders'],
      ['offers',t('Offers received'),'/seller?tab=offers'],
      ['messages',t('Messages'),'/seller?tab=messages'],
      ['find',t('Find customers'),'/seller?tab=find'],
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
    $label = '<span>'.htmlspecialchars($n[1]).'</span>';
    if($n[0]==='messages' && $unreadN>0) $label .= ' <span class="navdot">'.$unreadN.'</span>';
    $svg = isset($ic[$n[0]]) ? '<svg class="dsi" viewBox="0 0 24 24" aria-hidden="true">'.$ic[$n[0]].'</svg>' : '';
    echo '<a href="'.$n[2].'"'.($n[0]===$section?' class="on"':'').'>'.$svg.$label.'</a>';
  }
  echo '<div class="dashlang"><span>'.t('Language').'</span>'.vlang_switcher('dashsw','flat').'</div>';
  echo '<a class="signout" href="/login?signout=1">'.t('Sign out').'</a>';
  /* Telefonda serit yana kayar ve acik sekme ("Messages" besinci) ilk ekranda
     GORUNMUYORDU (7 Eyl 2026 olcumu): kullanici hangi sekmede oldugunu
     goremiyordu. Acik sekmeyi seridin ortasina getir — yalniz seridin kendi
     yatay kaydirmasi, sayfa dikeyde oynamaz (scrollIntoView degil). */
  echo '<script>(function(){var s=document.querySelector(".dashside"),a=s&&s.querySelector("a.on");'
     . 'if(!a||s.scrollWidth<=s.clientWidth)return;s.scrollLeft=Math.max(0,a.offsetLeft-(s.clientWidth-a.offsetWidth)/2);})();</script>';
  echo '</aside><main class="dashmain">';
}
function dash_close(){ echo '</main></div></div>'; }

function stat_cards($cards){
  echo '<div class="statgrid">';
  foreach($cards as $c){ echo '<div class="statcard"><div class="sv">'.$c[0].'</div><div class="sl">'.htmlspecialchars($c[1]).'</div></div>'; }
  echo '</div>';
}
function dash_empty($msg){ echo '<div class="empty">'.htmlspecialchars($msg).'</div>'; }
