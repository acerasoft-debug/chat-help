<?php
/**
 * ChatHelp — dump-konto-full (SADECE OKUR) — KOMPLE Konto/auth teshisi.
 *  Sikayet: (a) kullanicilar + bilgileri KAYIT OLMUYOR, (b) "Aktif abonelik
 *  bulunamadi" (abonelik var ama bulunamiyor). Bu tani TUM zinciri gosterir:
 *  router, register/login/me, profil-kaydet, users tablosu semasi, DB baglantisi,
 *  abonelik/billing. SIR DEGERLERI (sk_/whsec_/price_/DB pw) maskeli. YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-konto-full.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$auth=(string)@file_get_contents("$D/auth.php");
$idx =(string)@file_get_contents("$D/index.php");
$db  =(string)@file_get_contents("$D/db.php");
echo "=== dump-konto-full — SADECE OKUR ===\n";
echo "boyut: auth=".strlen($auth)." index=".strlen($idx)." db=".strlen($db)."\n\n";

function bodyOf($s,$decl,$cap=1800){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)\n";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,180)."\n";
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)\n":"\n");
}
function firstDecl($s,$names){ foreach($names as $n){ if(strpos($s,$n)!==false) return $n; } return $names[0]; }
function occ($label,$hay,$needle,$pre,$len,$max=6){
  echo "── $label ('$needle') ──\n";
  $off=0;$n=0;$tot=substr_count($hay,$needle);
  while(($p=strpos($hay,$needle,$off))!==false && $n<$max){
    $seg=substr($hay,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if(!$tot) echo "  (yok)\n"; echo "  [toplam $tot]\n";
}

echo "════ [1] auth.php ROUTER (action => handler) ════\n";
$off=0;$n=0;
while(($p=strpos($auth,'=> do',$off))!==false && $n<40){
  $seg=substr($auth,max(0,$p-26),46); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  ".$seg."\n"; $off=$p+5;$n++;
}
echo "\n";

echo "════ [2] KAYIT (register/signup) ════\n";
echo bodyOf($auth, firstDecl($auth,['function doRegister','function doSignup','function doAnmeldung','function doRegister']), 2200);

echo "════ [3] LOGIN ════\n";
echo bodyOf($auth, firstDecl($auth,['function doLogin','function doAuth']), 1400);

echo "════ [4] doMe (Konto ne okuyor) ════\n";
echo bodyOf($auth, 'function doMe', 1400);

echo "════ [5] PROFIL/BILGI KAYDET ════\n";
echo bodyOf($auth, firstDecl($auth,['function doSaveProfile','function doUpdateProfile','function doSaveMe','function doProfile','function doSave']), 1400);
occ('save endpointleri (auth)',$auth,'function doSave',0,60,8);
echo "\n";

echo "════ [6] users TABLOSU semasi + INSERT/UPDATE ════\n";
occ('CREATE TABLE users',$auth,'CREATE TABLE',0,220,6);
occ('INSERT INTO users',$auth,'INSERT INTO',0,200,10);
occ('UPDATE users',$auth,'UPDATE ',0,160,10);
echo "\n";

echo "════ [7] DB BAGLANTISI (db.php) ════\n";
if($db!==''){
  echo bodyOf($db, firstDecl($db,['function db','function getDb','function pdo']), 1400);
  /* DSN/host goster ama sifre maskele */
  $dbm=preg_replace('/(pass\w*|pwd|password)\s*[=:]\s*[\'"][^\'"]*[\'"]/i','$1=***MASKED***',$db);
  occ('DSN/host (maskeli)',$dbm,'mysql:',0,140,3);
  occ('new PDO',$dbm,'new PDO',0,120,3);
} else echo "  db.php yok/bos — baglanti auth.php icinde olabilir:\n";
occ('auth ici PDO/mysqli',$auth,'new PDO',0,120,3);
occ('auth ici getenv/DB',$auth,'getenv(',0,90,8);
echo "\n";

echo "════ [8] ABONELIK / 'Aktif abonelik bulunamadi' ════\n";
foreach(['auth.php'=>$auth,'index.php'=>$idx] as $fn=>$h){
  echo "  $fn 'Aktif abonelik bulunamad': ".substr_count($h,'Aktif abonelik bulunamad')." kez\n";
}
occ('mesaj baglami (index)',$idx,'Aktif abonelik',60,200,4);
echo "\n-- doBillingPortal --\n".bodyOf($auth,'function doBillingPortal',1800);
occ('stripe_customer_id (auth)',$auth,'customer',20,110,8);

echo "════ [9] FRONTEND kayit formu + Konto kaydet ════\n";
occ("register cagrisi",$idx,'action=register',20,110,4);
occ("signup cagrisi",$idx,'signup',20,110,4);
occ("doMe/loadMe cagrisi",$idx,'action=me',20,110,4);
occ("Konto bilgi kaydet",$idx,'saveProfile',20,120,5);
occ("Plan verwalten",$idx,'verwalten',30,130,5);

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
