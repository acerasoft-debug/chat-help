<?php
/**
 * ChatHelp — dump-auth-plan (SADECE OKUR) — canli auth.php + api.php'de plan/
 *  abonelik tanima mantiginin GERCEK durumu: hangi marker'lar var, plan nasil
 *  cozumleniyor (JWT'ye mi gomuluyor, DB'den mi okunuyor), doMe/free/kota
 *  kapilari nerede. SIRLAR gosterilmez. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-auth-plan.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-auth-plan — SADECE OKUR ===\n\n";

$auth=(string)@file_get_contents("$D/auth.php");
$api =(string)@file_get_contents("$D/api.php");
echo "auth.php: ".strlen($auth)." bayt | api.php: ".strlen($api)." bayt\n\n";

echo "[1] auth.php marker durumu:\n";
foreach(['CH_FREEDOC','CH_ADMIN_BLOCK','CH_FIX_ABO','CH_KONTO_BE','ch_plan_of','ch_norm_plan','doFreeStatus','doFreeUse','doMe','generateJWT'] as $m)
  echo "  ".str_pad($m,16).": ".substr_count($auth,$m)." kez\n";

echo "\n[2] api.php marker durumu:\n";
foreach(['CH_FREEDOC','doFreeStatus','doFreeUse','free_status','free_use','ch_plan_of','quota','plan'] as $m)
  echo "  ".str_pad($m,16).": ".substr_count($api,$m)." kez\n";

function occ($label,$src,$needle,$pre=120,$len=520,$max=4){
  echo "[$label] '$needle' (".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n"; echo "\n";
}

echo "\n───── auth.php plan cozumleme ─────\n\n";
occ('A1',$auth,'generateJWT(',80,300,4);
occ('A2',$auth,'user_plans',100,360,5);
occ('A3',$auth,"function doMe",40,600,1);
occ('A4',$auth,"'plan'",60,180,10);

echo "\n───── api.php kota/plan kapisi ─────\n\n";
occ('B1',$api,'user_plans',100,360,4);
occ('B2',$api,'function requireAuth',40,500,1);
occ('B3',$api,'free',60,200,8);

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
