<?php
/** ChatHelp — dump-authinit (SADECE OKUR) — hesap kimlik sorununun kesin cozumu icin:
 *  Backend (doLogin/doMe) DOGRU. Sorun client'ta: ust bar + Konto localStorage
 *  ch_user'dan besleniyor, TOKEN DOGRULAMADAN. Ve doRegister token dondurmuyor
 *  (email-verify gerekli) -> yeni kayit olan login OLMUYOR ama eski ch_user
 *  (owner Acerasoft/Elite) gorunmeye devam ediyor. Tam duzeltme icin sayfa
 *  acilis auth akisini gormem lazim:
 *  [1] init: ch_token okunup 'me'/doMe cagriliyor mu (token dogrulama)
 *  [2] register form handler: basari sonrasi ne oluyor (eski oturum temizleniyor mu)
 *  [3] login form handler: yeni user nasil yaziliyor
 *  [4] chFillKontoUser: token yoksa da calisiyor mu (stale sizinti noktasi) */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function occ($src,$needle,$pre,$len,$label,$max=4){
    echo "\n──── $label ('$needle') ────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
function fnBody($s,$decl,$cap=1400){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,200);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…":"");
}

echo "════ [1] init: token dogrulama ('action=me' / doMe fetch) ════\n";
occ($idx,"action=me",-120,340,"'me' action fetch",5);
occ($idx,"'me'",-80,180,"'me' gecen yerler (auth)",6);

echo "\n════ [2] register form handler (basari sonrasi) ════\n";
foreach(['function submitRegister','function doRegisterSubmit','function handleRegister','function ch_register','function authRegister'] as $fn){
    $bb=fnBody($idx,$fn,1300);
    if(strpos($bb,'YOK')===false){ echo "---- $fn ----\n$bb\n"; }
}
occ($idx,"action:'register'",-100,300,"register POST",3);
occ($idx,'action:"register"',-100,300,'register POST (cift tirnak)',3);
occ($idx,'Bitte bestätigen Sie Ihre E-Mail',-200,300,'register basari mesaji isleme',2);

echo "\n════ [3] login form handler (basari sonrasi) ════\n";
occ($idx,"action:'login'",-60,320,'login POST',3);
occ($idx,'action:"login"',-60,320,'login POST (cift tirnak)',3);

echo "\n════ [4] chFillKontoUser + init cagrilari ════\n";
echo fnBody($idx,'function chFillKontoUser(',1200)."\n";
occ($idx,'chFillKontoUser()',-60,120,'chFillKontoUser cagrilari',6);
occ($idx,'fillKonto()',-60,120,'fillKonto cagrilari',5);

echo "\n════ [5] logout / clearAuth ════\n";
occ($idx,"removeItem('ch_token')",-80,220,'ch_token silme noktalari',5);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
