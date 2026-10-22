<?php
/**
 * ChatHelp — dump-planbug — acerasoft@gmail.com paketinin Basic'e donmesi (OKUR).
 *  (1) sunucuda plan kaynagi (login/session, DB) — 'basic' fallback var mi?
 *  (2) Stripe webhook / plan-yazma kodu (api.php)
 *  (3) client'ta 'ch_profile' (tekil) / curPlan/writePlan — plan'i EZEBILECEK ikinci kaynak
 * KULLANIM: pull2.php?key=...&files=dump-planbug.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;

echo "== dump-planbug ==\n\n";

$api=@file_get_contents("$D/api.php");
if($api!==false){
  echo "=== [A] api.php: 'plan' alani nerede yaziliyor/okunuyor (DB/login) ===\n";
  foreach(["'plan'=>","->plan","plan = 'basic'","plan='basic'","DEFAULT 'basic'","?? 'basic'",'plan ??','stripe_price','price_id','webhook'] as $kw){
    $c=substr_count($api,$kw); if($c>0) echo "  '$kw': $c\n";
  }
  echo "\n  -- ilk 'webhook' baglami --\n";
  $p=stripos($api,'webhook');
  if($p!==false) echo "  ...".substr($api,max(0,$p-100),500)."...\n";
  echo "\n  -- login/user donusu (SELECT ... plan) --\n";
  $off=0;$n=0;
  while(($p=stripos($api,'plan',$off))!==false && $n<10){
    $seg=trim(substr($api,max(0,$p-80),160));
    if(stripos($seg,'select')!==false || stripos($seg,'user')!==false || stripos($seg,'basic')!==false){
      echo "  [$n] @$p ...".$seg."...\n"; $n++;
    }
    $off=$p+4;
  }
} else echo "api.php YOK\n";

echo "\n\n";
$idx=@file_get_contents("$D/index.php");
if($idx!==false){
  echo "=== [B] index.php: 'ch_profile' (TEKIL, plural degil) TUM referanslari ===\n";
  $off=0;$n=0;
  while(($p=strpos($idx,"'ch_profile'",$off))!==false && $n<10){
    echo "  [$n] @$p ...".substr($idx,max(0,$p-100),220)."...\n\n"; $off=$p+12;$n++;
  }
  $off=0;$n=0;
  while(($p=strpos($idx,'"ch_profile"',$off))!==false && $n<10){
    echo "  [dq$n] @$p ...".substr($idx,max(0,$p-100),220)."...\n\n"; $off=$p+12;$n++;
  }

  echo "=== [C] curPlan()/writePlan() TAM govde ===\n";
  $p=strpos($idx,'function curPlan');
  if($p!==false) echo substr($idx,$p,400)."\n\n";
  $p=strpos($idx,'function writePlan');
  if($p!==false) echo substr($idx,$p,500)."\n\n";

  echo "=== [D] 'basic' literal atamalari (fallback/default supheli) ===\n";
  $off=0;$n=0;
  while(($p=strpos($idx,"='basic'",$off))!==false && $n<10){
    echo "  [$n] @$p ...".substr($idx,max(0,$p-120),200)."...\n\n"; $off=$p+8;$n++;
  }
  $off=0;$n=0;
  while(($p=strpos($idx,"plan||'basic'",$off))!==false && $n<6){
    echo "  [fb$n] @$p ...".substr($idx,max(0,$p-100),200)."...\n\n"; $off=$p+13;$n++;
  }

  echo "=== [E] 'acerasoft' ozel-durum kodu var mi? ===\n";
  $c=substr_count($idx,'acerasoft'); echo "  'acerasoft' (index.php): $c\n";
}
if($api!==false){ $c=substr_count($api,'acerasoft'); echo "  'acerasoft' (api.php): $c\n"; }

echo "\n== BITTI ==\n";
