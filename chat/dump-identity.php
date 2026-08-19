<?php
/** ChatHelp — dump-identity (SADECE OKUR) — KRITIK #89: yeni musteri kayit
 *  olsa bile owner "Acerasoft" kimligi + Elite plan goruyor.
 *  [1] hardcoded 'Acerasoft' / 'elite' / owner e-posta izleri (idx + auth + api)
 *  [2] auth.php doRegister (kayitta plan/isim atamasi) + doMe + doLogin
 *  [3] generateJWT (plan JWT'ye nasil giriyor)
 *  [4] client: profil (P.f1..f7) nereden yukleniyor/kaydediliyor (localStorage
 *      anahtari? sunucu? paylasimli mi)
 *  [5] plan gosterimi: P.plan / ch_user.plan set eden yerler
 *  HASSAS: e-posta/anahtar maskeli. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$auth=(string)@file_get_contents("$D/auth.php");
$api=(string)@file_get_contents("$D/api.php");

function fnBody($s,$decl,$cap=1400){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kesildi)":"");
}
function raw($src,$needle,$pre,$len,$label,$max=8,$ci=false){
    echo "\n──────── $label ('$needle') ────────\n";
    $hay=$ci?strtolower($src):$src; $nd=$ci?strtolower($needle):$needle;
    $off=0;$n=0;$tot=substr_count($hay,$nd);
    while(($p=strpos($hay,$nd,$off))!==false && $n<$max){
        $line=substr($src,max(0,$p-$pre),$len);
        $line=preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/','***@***',$line);
        echo "[@$p] ".str_replace("\n","⏎",$line)."\n";
        $off=$p+strlen($nd);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] hardcoded Acerasoft/elite/owner izleri ════\n";
raw($idx,'Acerasoft',-30,90,'Acerasoft (idx)',8);
raw($auth,'Acerasoft',-30,90,'Acerasoft (auth)',5);
raw($api,'Acerasoft',-30,90,'Acerasoft (api)',5);
raw($idx,"'elite'",-40,80,"'elite' (idx)",10);
raw($idx,'"elite"',-40,80,'"elite" (idx)',6);
raw($auth,'elite',-30,70,'elite (auth)',6);

echo "\n════ [2] doRegister / doMe / doLogin ════\n";
echo "-- doRegister --\n".fnBody($auth,'function doRegister(',1200)."\n";
echo "\n-- doMe --\n".fnBody($auth,'function doMe(',900)."\n";
echo "\n-- doLogin --\n".fnBody($auth,'function doLogin(',1100)."\n";

echo "\n════ [3] generateJWT ════\n";
echo fnBody($auth,'function generateJWT(',700)."\n";

echo "\n════ [4] client profil yukleme/kaydetme ════\n";
raw($idx,'P.f1',-20,80,'P.f1 kullanimi',10);
raw($idx,'ch_profile',-30,110,'ch_profile localStorage',8);
raw($idx,'chathelp_profiles',-20,90,'profiles tablosu',3);
raw($idx,'loadProfile',-20,110,'loadProfile',5);
raw($idx,'saveProfile',-20,110,'saveProfile',5);

echo "\n════ [5] plan set eden yerler (client) ════\n";
raw($idx,'P.plan=',-30,90,'P.plan=',10);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
